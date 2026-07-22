<?php

namespace App\Providers;

use App\Models\Story;
use App\Models\TagGroup;
use App\Observers\MediaObserver;
use App\Observers\StoryObserver;
use App\Observers\TagGroupObserver;
use App\Services\MediaLibrary\OrchestratorPathGenerator;
use Dedoc\Scramble\Scramble;
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

        // oc:8028 — wm-package sovrascrive path_generator con WmfePathGenerator e disk_name
        // con wmfe (S3), rendendo irraggiungibili i 605/631 media scritti con layout legacy.
        // disk_name è hardcodato a 'public' perché tutti i file storici sono su quel disco.
        config([
            'media-library.path_generator' => OrchestratorPathGenerator::class,
            'media-library.disk_name'      => 'public',
        ]);
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

        // oc:8287 — Scramble di default documenta TUTTE le route sotto /api, incluse
        // quelle registrate da wm-package (mobile app, ec/ugc, wallet, elasticsearch,
        // export). Limitiamo la doc pubblica alle sole route definite in routes/api.php
        // del repo principale.
        Scramble::routes(function (\Illuminate\Routing\Route $route) {
            $action = $route->getActionName();

            if (str_starts_with($action, 'App\\Http\\Controllers\\Api\\')) {
                return true;
            }

            return in_array($route->uri(), ['api/app/{id}/config.json', 'api/me'], true);
        });

        // oc:8287 — impedisce che "Try it out" nella doc pubblica /docs/api possa
        // eseguire scritture reali con un Bearer token trapelato: le operazioni
        // mutanti perdono il requisito di sicurezza (nessun Authorize iniettabile),
        // restano comunque documentate ma non eseguibili con un click.
        Scramble::afterOpenApiGenerated(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) {
            foreach ($openApi->paths as $path) {
                foreach ($path->operations as $operation) {
                    if (strtoupper($operation->method) !== 'GET') {
                        $operation->security = [];
                    }
                }
            }
        });
    }
}
