<?php

namespace App\Providers;

use App\Http\Responses\NovaLoginViewResponse;
use App\Models\Story;
use App\Observers\MediaObserver;
use App\Observers\StoryObserver;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginViewResponse;
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
        // Registra il binding per LoginViewResponse di Fortify per usare la vista di login di Nova
        $this->app->singleton(LoginViewResponse::class, NovaLoginViewResponse::class);
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
            true
        );
    }
}
