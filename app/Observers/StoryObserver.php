<?php

namespace App\Observers;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\StoryLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        $changes = [];
        $jsonChanges = [];
        $user = Auth::user();
        $userName = $user ? $user->name : 'Unknown User';


        // Se lo status è cambiato in progress, lancia il comando
        if ($story->isDirty('status') && $story->status === StoryStatus::Progress->value) {
            $developerId = $story->user_id;
            if ($developerId) {
                $developer = DB::table('users')->where('id', $developerId)->first();
                if ($developer && $developer->email) {
                    Artisan::call('sync:stories-calendar', ['developerEmail' => $developer->email]);
                }
            }
        }
        $dirtyFields = $story->getDirty();
        foreach ($dirtyFields as $field => $newValue) {
            $originalValue = $story->getOriginal($field);
            if ($field === 'description') {
                $newValue = 'change description';
            }
            $changes[] = ucfirst($field) . " changed from <strong>{$originalValue}</strong> to <strong>{$newValue}</strong>";
            $jsonChanges[$field] = $newValue;
        }

        if (!empty($changes)) {
            $timestamp = now()->format('Y-m-d H:i');
            $newLogEntry = "{$timestamp}: {$userName} - " . implode(', ', $changes) . "<br>";
            $story->history_log = $story->history_log . $newLogEntry;
            StoryLog::create([
                'story_id' => $story->id,
                'user_id' => $user ? $user->id : null,
                'viewed_at' => $timestamp,
                'changes' => $jsonChanges,
            ]);
            $story->saveQuietly();
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
