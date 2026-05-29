<?php

namespace Webmapp\HetznerMonitoring;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class HetznerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function () {
            $this->routes();
        });

        Nova::serving(function (ServingNova $event) {
            Nova::script('hetzner-monitoring', __DIR__ . '/../dist/js/card.js');
            Nova::style('hetzner-monitoring', __DIR__ . '/../dist/css/card.css');
        });
    }

    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['nova'])
            ->prefix('nova-vendor/hetzner-monitoring')
            ->group(__DIR__ . '/../routes/api.php');
    }
}
