<?php

namespace App\Observers;

use App\Models\Story;
use App\Models\StoryLog;
use App\Enums\StoryStatus;
use App\Actions\StoryTimeService;
use App\Services\StoryDateService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdateUsersStoriesLogJob;

class StoryObserver
{
    private static $createdStories = [];

    /**
     * Handle the Story "created" event.
     */
    public function created(Story $story): void
    {
        // Mark this story as newly created
        self::$createdStories[$story->id] = true;

        // Determine user_id for the log
        // If story has user_id, use it; otherwise use logged in user; if null, don't create log
        $userId = $story->user_id;
        if (!$userId) {
            $loggedUser = Auth::user();
            $userId = $loggedUser ? $loggedUser->id : null;
        }

        // Create StoryLog entry for story creation only if we have a user_id
        // (user_id is required in story_logs table)
        if ($userId) {
            // Ensure status is set (default to 'new' if null)
            $status = $story->status ?? StoryStatus::New->value;
            
            StoryLog::create([
                'story_id' => $story->id,
                'user_id' => $userId,
                'viewed_at' => now()->format('Y-m-d H:i:s'),
                'changes' => [
                    'status' => $status,
                    'updated_at' => $story->created_at ? $story->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                ],
            ]);
        }

        $user = Auth::user();
        if (is_null($user)) {
            $user = User::where('email', 'orchestrator_artisan@webmapp.it')->first();
        }

        if ($user) {
            // Log activity to activity.log file
            $message = sprintf(
                '%s (%s) created story #%d "%s" on %s',
                $user->name,
                $user->email,
                $story->id,
                $story->name,
                now()->format('d-m-Y H:i:s')
            );

            $context = [
                'story_id' => $story->id,
                'story_name' => $story->name,
                'story_status' => $story->status,
                'story_type' => $story->type,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ];

            Log::channel('user-activity')->info($message, $context);
        }
    }

    /**
     * Handle the Story "updated" event.
     */
    public function updated(Story $story): void
    {
        $this->syncStoryCalendarIfStatusChanged($story);
        $this->createStoryLog($story);
        $this->updateReleaseAndDoneDates($story);
        $this->notifyDeveloperIfIdle($story);
    }

    /**
     * Handle the Story "updated" event.
     */
    public function saving(Story $story): void
    {
        // Only one progress story per user is allowed
        // moves all other progress stories in Todo
        if ($story->isDirty('status') && $story->status === StoryStatus::Progress->value) {
            Story::where('user_id', $story->user_id)
                ->where('status', StoryStatus::Progress->value)
                ->whereNot('id', $story->id)
                //->update('status', StoryStatus::Todo->value); -> this doesn't trigger events
                ->get()
                ->each(function (Story $progressStory) {
                    $progressStory->status = StoryStatus::Todo->value;
                    $progressStory->save(); // -> this triggers events (like the gogle calendar update)
                });
        }
    }

    /**
     * Handle the Story "updating" event.
     */
    public function updating(Story $story): void
    {
        $user = auth()->user();
        if (
            !$story->wasRecentlyCreated
            && $story->isDirty('customer_request')
            && $user && $story->user
            && $user->id != $story->user->id
            && $story->user->hasRole(UserRole::Customer)
        ) {
            $story->status = StoryStatus::Todo->value;
        }
    }

    private function syncStoryCalendarIfStatusChanged(Story $story): void
    {
        if ($story->isDirty('status')) {
            $developerId = $story->user_id;
            if ($developerId) {
                $developer = DB::table('users')->where('id', $developerId)->first();
                if ($developer && $developer->email) {
                    Artisan::call('sync:stories-calendar', ['developerEmail' => $developer->email]);
                }
            }
            if ($story->status === StoryStatus::Test->value) {
                $tester = $story->tester;
                if ($tester && $tester->email) {
                    Artisan::call('sync:stories-calendar', ['developerEmail' => $tester->email]);
                }
            }
        }
    }

    /**
     * Update released_at and done_at dates based on current status or from logs
     * If status is Done but no done_at date found, use released_at as done_at
     */
    private function updateReleaseAndDoneDates(Story $story): void
    {
        $dateService = app(StoryDateService::class);
        $statusChanged = $story->isDirty('status');

        // If status changed to Released, set released_at to current time
        if ($statusChanged && $story->status === StoryStatus::Released->value && !$story->released_at) {
            $story->released_at = now();
            $story->saveQuietly();
        }

        // If status changed to Done, try to get done_at from logs first
        // If not found, use released_at if available
        if ($statusChanged && $story->status === StoryStatus::Done->value && !$story->done_at) {
            // Recalculate dates from logs first
            $dateService->updateDates($story);
            
            // If done_at is still null after recalculation, use released_at
            if (!$story->done_at && $story->released_at) {
                $story->done_at = $story->released_at;
            } else if (!$story->done_at) {
                // If neither done_at nor released_at, set done_at to current time
                $story->done_at = now();
            }
            
            $story->saveQuietly();
        }

        // Recalculate dates from logs if they are not set
        if (!$story->released_at || (!$story->done_at && $story->status === StoryStatus::Done->value)) {
            $dateService->updateDates($story);
            if ($story->isDirty(['released_at', 'done_at'])) {
                $story->saveQuietly();
            }
        }
    }

    private function createStoryLog(Story $story): void
    {
        // Don't log as "updated" if the story was just created in this request
        if ($story->wasRecentlyCreated || isset(self::$createdStories[$story->id])) {
            // Clean up the flag after checking
            unset(self::$createdStories[$story->id]);

            return;
        }

        $dirtyFields = $story->getDirty();

        $user = Auth::user();
        if (is_null($user)) {
            $user = User::where('email', 'orchestrator_artisan@webmapp.it')->first();
        } //there is a seeder for this user (PhpArtisanUserSeeder)

        $jsonChanges = [];

        foreach ($dirtyFields as $field => $newValue) {
            // Skip null values (they don't represent meaningful changes)
            if ($newValue === null) {
                continue;
            }
            
            if ($field === 'description') {
                $newValue = 'change description';
            }
            $jsonChanges[$field] = $newValue;
        }

        if (count($jsonChanges) > 0) {
            $timestamp = now()->format('Y-m-d H:i');
            $storyLog = StoryLog::create([
                'story_id' => $story->id,
                'user_id' => $user->id,
                'viewed_at' => $timestamp,
                'changes' => $jsonChanges,
            ]);

            // Log activity to activity.log file
            $changesText = implode(', ', array_map(
                fn ($key, $value) => "{$key}: ".(is_string($value) ? $value : json_encode($value)),
                array_keys($jsonChanges),
                $jsonChanges
            ));

            $message = sprintf(
                '%s (%s) updated story #%d "%s" on %s - Changes: %s',
                $user->name,
                $user->email,
                $story->id,
                $story->name,
                now()->format('d-m-Y H:i:s'),
                $changesText
            );

            $context = [
                'story_id' => $story->id,
                'story_name' => $story->name,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'changes' => $jsonChanges,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ];

            Log::channel('activity')->info($message, $context);

            $story->saveQuietly();
            StoryTimeService::run($storyLog->story);
            
            // Dispatch job to update users_stories_log
            UpdateUsersStoriesLogJob::dispatch($story->id, null);
        }
    }

    private function notifyDeveloperIfIdle(Story $story): void
    {
        $developer = $story->user;
        if (!$developer) {
            return;
        }

        // Controllo se l'orario corrente è >= 15:30; se sì, non dispatcho il job
        if (now()->greaterThanOrEqualTo(now()->setTime(15, 30))) {
            return;
        }

        // Verifica se ci sono storie di tipo 'scrum' per il developer
        $hasScrum = \App\Models\Story::where('user_id', $developer->id)
            ->where('type', 'scrum')
            ->exists();
        if (!$hasScrum) {
            return;
        }

        // Controllo se ci sono storie in stato 'Progress'
        $hasProgress = \App\Models\Story::where('user_id', $developer->id)
            ->where('status', \App\Enums\StoryStatus::Progress->value)
            ->exists();

        if (!$hasProgress) {
            \App\Jobs\CheckDeveloperProgressJob::dispatch($developer->id)->delay(now()->addMinutes(30));
        }
    }

    /**
     * Handle the Story "deleted" event.
     */
    public function deleted(Story $story): void
    {
        $user = Auth::user();
        if (is_null($user)) {
            $user = User::where('email', 'orchestrator_artisan@webmapp.it')->first();
        }

        if ($user) {
            // Log activity to activity.log file
            $message = sprintf(
                '%s (%s) deleted story #%d "%s" on %s',
                $user->name,
                $user->email,
                $story->id,
                $story->name,
                now()->format('d-m-Y H:i:s')
            );

            $context = [
                'story_id' => $story->id,
                'story_name' => $story->name,
                'story_status' => $story->status,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ];

            Log::channel('user-activity')->warning($message, $context);
        }
    }

    /**
     * Handle the Story "restored" event.
     */
    public function restored(Story $story): void
    {
        //
    }

    /**
     * Handle the Story "force deleted" event.
     */
    public function forceDeleted(Story $story): void
    {
        //
    }
}
