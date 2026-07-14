<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;
use function Laravel\Prompts\multiselect;

#[AsCommand(name: 'podman:install')]
class InstallCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:install
        {service?* : The name(s) of the service to install}
        {--application= : The name of the application, installed in its own subdirectory (requires Podman 6+)}
        {--replace : Replace the service if it already exists}
        {--secrets : Also prompt for and set the secrets required by the service}
        {--no-install : Prepare the service(s) at the configured publish path without installing them}
    ';

    public $description = 'Install one or more services for the application to run with Podman';

    public function handle(): int
    {
        $services = Arr::wrap($this->argument('service')) ?: multiselect(
            label: 'Select the services to install',
            options: $this->getPodmanQuadlets(),
            required: true,
        );

        $failed = $this->installPodmanQuadlets(
            services: $services,
            application: $this->option('application'),
            replace: $this->option('replace'),
            secrets: $this->option('secrets'),
            install: ! $this->option('no-install'),
        );

        if ($failed !== []) {
            error('Failed to install: '.implode(', ', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
