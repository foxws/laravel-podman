<?php

namespace Foxws\LaravelPodman;

use Foxws\LaravelPodman\Commands\LaravelPodmanCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPodmanServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-podman')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel-podman_table')
            ->hasCommand(LaravelPodmanCommand::class);
    }
}
