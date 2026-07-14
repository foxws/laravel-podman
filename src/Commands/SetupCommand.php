<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;

#[AsCommand(name: 'podman:setup')]
class SetupCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:setup
        {--service=* : Override the default set of services to install}
        {--runtime=* : Override the default set of runtimes to publish}
        {--application= : The name of the application, installed in its own subdirectory (requires Podman 6+)}
        {--no-replace : Do not replace a service if it already exists}
        {--no-secrets : Do not prompt for and set the secrets required by each service}
        {--force : Overwrite existing published runtime files}
        {--no-install : Prepare services at the configured publish path without installing them}
    ';

    public $description = 'Publish the default set of runtimes and install the default set of services for the application to run with Podman';

    public function handle(): int
    {
        $runtimes = Arr::wrap($this->option('runtime')) ?: ($this->getPodmanQuadletDefaultRuntimes() ?: multiselect(
            label: 'Select the runtimes to publish',
            options: $this->getPodmanQuadletRuntimes(),
            required: true,
        ));

        $failedRuntimes = $this->publishPodmanRuntimes($runtimes, force: $this->option('force'));

        if ($failedRuntimes !== []) {
            error('Failed to publish: '.implode(', ', $failedRuntimes));

            return self::FAILURE;
        }

        $services = Arr::wrap($this->option('service')) ?: $this->getPodmanQuadletDefaultServices();

        if ($services === []) {
            error('No default services are configured. Set podman.services in the config file, or pass --service.');

            return self::FAILURE;
        }

        $failedServices = $this->installPodmanQuadlets(
            services: $services,
            application: $this->option('application'),
            replace: ! $this->option('no-replace'),
            secrets: ! $this->option('no-secrets'),
            install: ! $this->option('no-install'),
        );

        if ($failedServices !== []) {
            error('Failed to install: '.implode(', ', $failedServices));

            return self::FAILURE;
        }

        info('Setup complete. Installed: '.implode(', ', $services));

        return self::SUCCESS;
    }
}
