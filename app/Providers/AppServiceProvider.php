<?php

namespace App\Providers;

use App\Models\Story;
use App\Observers\StoryObserver;
use Illuminate\Support\ServiceProvider;
use Lorisleiva\Actions\Facades\Actions;

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
        if ($this->app->runningInConsole()) {
            Actions::registerCommands();
        }
    }
}
