<?php

namespace App\Observers;

use App\Models\Story;
use App\Models\StoryLog;
use App\Enums\StoryStatus;
use App\Actions\StoryTimeService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SyncDeveloperCalendarJob;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Log;
use App\Services\TagService;

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

        $tagService = app(TagService::class);

        try {
            $tagService->attachQuarterTagToStory($story);
        } catch (\Throwable $e) {
            Log::error("Auto-tagging (quarter) failed for story #{$story->id}: " . $e->getMessage());
        }

        try {
            $tagService->attachCustomerTagToStory($story);
        } catch (\Throwable $e) {
            Log::error("Auto-tagging (customer) failed for story #{$story->id}: " . $e->getMessage());
        }

        try {
            $tagService->attachTagsFromTextToStory($story);
        } catch (\Throwable $e) {
            Log::error("Auto-tagging (text) failed for story #{$story->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the Story "updated" event.
     */
    public function updated(Story $story): void
    {
        $this->dispatchCalendarSyncIfNeeded($story);

        // Sync commit GitHub quando la story viene chiusa
        if (
            $story->wasChanged('status')
            && in_array($story->status, [StoryStatus::Done->value, StoryStatus::Released->value])
        ) {
            \App\Jobs\SyncStoryGithubCommitsJob::dispatch($story->id);
        }

        $this->createStoryLog($story);
        $this->notifyDeveloperIfIdle($story);
    }


    /**
     * Handle the Story "updated" event.
     */
    public function saving(Story $story): void
    {
        // If the "Answer to ticket" field is saved by a non-assigned user,
        // force the status to `todo` even if the same submit tries to set
        // another value (e.g. `released`).
        if ($story->forceTodoOnAnswerToTicket === true) {
            $user = auth()->user();
            if ($user && $user->id != $story->user_id) {
                $story->status = StoryStatus::Todo->value;
            }

            // IMPORTANT: this flag is runtime-only. Eloquent was including it
            // in the UPDATE as if it were a real database column, causing the
            // "Undefined column: forceTodoOnAnswerToTicket" error.
            // Remove it from model attributes before saving.
            try {
                $story->offsetUnset('forceTodoOnAnswerToTicket');
            } catch (\Throwable $e) {
                // Fallback: if ArrayAccess doesn't behave as expected, ignore.
            }
        }

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
            && $user
            && $user->hasRole(UserRole::Customer)
        ) {
            $story->status = StoryStatus::Todo->value;
        }
    }

    private function dispatchCalendarSyncIfNeeded(Story $story): void
    {
        $emails = [];

        if ($story->isDirty('status')) {
            $emails[] = $this->userEmail($story->user_id);

            // Sync the tester calendar both when the story enters and when it
            // leaves the testing status (the 2BETESTED event must disappear).
            if (
                $story->status === StoryStatus::Test->value
                || $story->getOriginal('status') === StoryStatus::Test->value
            ) {
                $tester = $story->tester;
                $emails[] = $tester?->email;
            }
        }

        // On reassignment sync both calendars: the old assignee loses the
        // event, the new one gains it.
        if ($story->isDirty('user_id')) {
            $emails[] = $this->userEmail($story->getOriginal('user_id'));
            $emails[] = $this->userEmail($story->user_id);
        }

        // The job is delayed (debounce) and unique per email: bursts of saves
        // collapse into a single sync that reads the final state from the DB.
        foreach (array_unique(array_filter($emails)) as $email) {
            SyncDeveloperCalendarJob::dispatch($email);
        }
    }

    private function userEmail(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        return DB::table('users')->where('id', $userId)->value('email');
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
                fn($key, $value) => "{$key}: " . (is_string($value) ? $value : json_encode($value)),
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
