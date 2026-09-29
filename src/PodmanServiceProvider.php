<?php

declare(strict_types=1);

namespace Foxws\Podman;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PodmanServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-podman')
            ->hasConfigFile('podman')
            ->hasCommands(
                Commands\GenerateCommand::class,
                Commands\IdleCommand::class,
                Commands\PublishCommand::class,
                Commands\S3SetupCommand::class,
                Commands\SetupCommand::class,
            );
    }

    public function packageRegistered(): void
    {
        $this->app->bind(
            Support\PodmanS3Manager::class,
            fn (): Support\PodmanS3Manager => Support\PodmanS3Manager::fromConfig(),
        );

        $this->app->singleton(Support\Idle\PodmanIdle::class);
    }
}
