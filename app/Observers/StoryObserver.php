<?php

namespace App\Observers;

use App\Models\Story;
use Illuminate\Support\Facades\Auth;

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
        $user = Auth::user(); // Ottiene l'utente corrente
        $userName = $user ? $user->name : 'Unknown User'; // Controlla se l'utente è loggato

        if ($story->isDirty('status')) {
            $changes[] = "Status changed to <strong>{$story->status}</strong>";
        }

        if ($story->isDirty('assigned_to')) {
            $assignedUserName = $story->assignedTo->name ?? 'None';
            $changes[] = "Assigned to <strong>{$assignedUserName}</strong>";
        }

        if ($story->isDirty('type')) {
            $changes[] = "Type changed to <strong>{$story->type}</strong>";
        }


        if (!empty($changes)) {
            $timestamp = now()->format('Y-m-d H:i');
            $newLogEntry = "{$timestamp}: {$userName} - " . implode(', ', $changes) . "<br>";
            $story->history_log = $story->history_log . $newLogEntry;
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
