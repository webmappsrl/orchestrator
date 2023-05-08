<?php

namespace App\Observers;

use App\Models\Epic;
use App\Enums\EpicStatus;
use Laravel\Nova\URL;
use Laravel\Nova\Notifications\NovaNotification;

class EpicObserver
{
    /**
     * Handle the Epic "created" event.
     */
    public function created(Epic $epic): void
    {
        $user = $epic->user;
        $user->notify(NovaNotification::make()
            ->message('New Epic ' . $epic->name . ' has been assigned to you')
            ->type('info')
            ->icon('at-symbol')
            ->action('View Epic', URL::remote('https://orchestrator.maphub.it/resources/epics/' . $epic->id)));
    }

    /**
     * Handle the Epic "updated" event.
     */
    public function updated(Epic $epic)
    {
        $user = $epic->user;
        if ($epic->wasChanged('status')) {
            if ($epic->status == 'done') {
                $user->notify(NovaNotification::make()
                    ->message('Your Epic ' . $epic->name . ' has been marked as done')
                    ->icon('check-circle')
                    ->type('success')
                    ->action('View Epic', URL::remote('https://orchestrator.dev.maphub.it/resources/epics/' . $epic->id)));
            } elseif ($epic->status == 'rejected') {
                $user->notify(NovaNotification::make()
                    ->message('Your Epic ' . $epic->name . ' has been marked as rejected')
                    ->icon('exclamation-circle')
                    ->type('error')
                    ->action('View Epic', URL::remote('https://orchestrator.dev.maphub.it/resources/epics/' . $epic->id)));
            }
        }
    }

    /**
     * Handle the Epic "deleted" event.
     */
    public function deleted(Epic $epic): void
    {
        $user = $epic->user;
        $user->notify(NovaNotification::make()
            ->message('Your Epic ' . $epic->name . ' has been deleted')
            ->icon('trash')
            ->type('info'));
    }

    /**
     * Handle the Epic "restored" event.
     */
    public function restored(Epic $epic): void
    {
        //
    }

    /**
     * Handle the Epic "force deleted" event.
     */
    public function forceDeleted(Epic $epic): void
    {
        //
    }
}
