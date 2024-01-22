<?php

namespace Foxws\LaravelPodman;

use Foxws\LaravelPodman\Commands\PublishCommand;
use Illuminate\Contracts\Support\DeferrableProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPodmanServiceProvider extends PackageServiceProvider implements DeferrableProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-podman')
            ->hasCommand(PublishCommand::class);
    }
}
