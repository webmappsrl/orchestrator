<?php

namespace App\Observers;

use App\Models\Story;
use App\Models\StoryLog;
use App\Enums\StoryStatus;
use App\Actions\StoryTimeService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Http;

class StoryObserver
{
    /**
     * Handle the Story "created" event.
     */
    public function created(Story $story): void
    {
        //
    }

    /**
     * Handle the Story "updated" event.
     */
    public function updated(Story $story): void
    {
        $this->syncStoryCalendarIfStatusChanged($story);
        $this->createStoryLog($story);
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

    private function createStoryLog(Story $story): void
    {
        $dirtyFields = $story->getDirty();

        $user = Auth::user();
        if (is_null($user))
            $user = User::where('email', 'orchestrator_artisan@webmapp.it')->first(); //there is a seeder for this user (PhpArtisanUserSeeder)

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
        //
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
