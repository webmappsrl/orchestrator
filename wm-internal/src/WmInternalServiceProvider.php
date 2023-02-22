<?php

namespace Wm\WmInternal;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Wm\WmInternal\Commands\WmInternalCommand;

class WmInternalServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('wm-internal')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_wm-internal_table')
            ->hasCommand(WmInternalCommand::class);
    }
}
