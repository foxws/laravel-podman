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
                Commands\PublishCommand::class,
                Commands\RemoveCommand::class,
                Commands\S3SetupCommand::class,
                Commands\SecretCommand::class,
                Commands\SetupCommand::class,
                Commands\UninstallCommand::class,
            );
    }

    public function packageRegistered(): void
    {
        $this->app->bind(
            Support\PodmanS3Manager::class,
            fn (): Support\PodmanS3Manager => Support\PodmanS3Manager::fromConfig(),
        );
    }
}
