<?php

namespace App\Providers;

use App\Models\Story;
use App\Observers\MediaObserver;
use App\Observers\StoryObserver;
use Illuminate\Support\ServiceProvider;
use Lorisleiva\Actions\Facades\Actions;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\Facades\Translatable;

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
        Media::observe(MediaObserver::class);

        if ($this->app->runningInConsole()) {
            Actions::registerCommands();
        }

        Translatable::fallback(
            fallbackAny: true,
        );
    }
}
