<?php

namespace App\Observers;

use App\Models\App;
use Illuminate\Support\Facades\Log;

class AppObserver
{
    /**
     * Handle the App "created" event.
     */
    public function created(App $app): void
    {
        //
    }

    /**
     * Handle the App "updated" event.
     */
    public function updated(App $app): void
    {
        $app->BuildConfJson();
        Log::info('updated');
    }

    /**
     * Handle the App "deleted" event.
     */
    public function deleted(App $app): void
    {
        //
    }

    /**
     * Handle the App "restored" event.
     */
    public function restored(App $app): void
    {
        //
    }

    /**
     * Handle the App "force deleted" event.
     */
    public function forceDeleted(App $app): void
    {
        //
    }
}
