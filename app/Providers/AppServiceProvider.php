<?php

namespace App\Providers;

use App\Models\Story;
use App\Models\ActivityReport;
use App\Models\Documentation;
use App\Observers\MediaObserver;
use App\Observers\StoryObserver;
use App\Observers\ActivityReportObserver;
use App\Observers\DocumentationObserver;
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
        ActivityReport::observe(ActivityReportObserver::class);
        Documentation::observe(DocumentationObserver::class);

        if ($this->app->runningInConsole()) {
            Actions::registerCommands();
        }

        Translatable::fallback(
            fallbackAny: true,
        );
    }
}
