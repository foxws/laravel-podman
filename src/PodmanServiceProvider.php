<?php

declare(strict_types=1);

namespace Foxws\Podman;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PodmanServiceProvider extends PackageServiceProvider
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
            ->hasConfigFile('podman')
            ->hasCommands(
                Commands\InstallCommand::class,
                Commands\ListCommand::class,
                Commands\PrintCommand::class,
                Commands\RemoveCommand::class,
                Commands\SecretCommand::class,
                Commands\UninstallCommand::class,
            );
    }
}
