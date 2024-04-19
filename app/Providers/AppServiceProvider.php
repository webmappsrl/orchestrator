<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Story;
use App\Observers\StoryObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Story::observe(StoryObserver::class);
    }
}
