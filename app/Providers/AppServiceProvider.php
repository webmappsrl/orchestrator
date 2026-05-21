<?php

namespace App\Providers;

use App\Models\Story;
use App\Models\TagGroup;
use App\Observers\MediaObserver;
use App\Observers\StoryObserver;
use App\Observers\TagGroupObserver;
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
        // The wm-package overrides media-library.media_model with
        // Wm\WmPackage\Models\Media, whose observer expects app_id/geometry
        // columns on the 'media' table. This project's media table only has
        // the standard Spatie columns, so force the default Spatie model.
        // AppServiceProvider::register() runs after package auto-discovered
        // providers, so this override wins.
        config(['media-library.media_model' => \Spatie\MediaLibrary\MediaCollections\Models\Media::class]);
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
        TagGroup::observe(TagGroupObserver::class);

        if ($this->app->runningInConsole()) {
            Actions::registerCommands();
        }

        Translatable::fallback(
            fallbackAny: true,
        );
    }
}
