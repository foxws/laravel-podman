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

#[AsCommand(name: 'podman:install')]
class InstallCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:install
        {service?* : The name(s) of the service to install}
        {--application= : The name of the application, installed in its own subdirectory (requires Podman 6+)}
        {--replace : Replace the service if it already exists}
        {--secrets : Also prompt for and set the secrets required by the service}
    ';

    public $description = 'Install one or more services for the application to run with Podman';

    public function handle(): int
    {
        $services = Arr::wrap($this->argument('service')) ?: multiselect(
            label: 'Select the services to install',
            options: $this->getPodmanQuadlets(),
            required: true,
        );

        $failed = [];

        foreach ($services as $service) {
            if ($this->option('secrets') && ! $this->promptForPodmanQuadletSecrets($service, replace: $this->option('replace'))) {
                $failed[] = $service;

                continue;
            }

            $process = $this->installPodmanQuadlet(
                service: $service,
                application: $this->option('application'),
                replace: $this->option('replace'),
            );

            $process->run();

            if (! $process->isSuccessful()) {
                error($process->getErrorOutput());

                $failed[] = $service;

                continue;
            }

            info("Service {$service} installed successfully.");
        }

        if ($failed !== []) {
            error('Failed to install: '.implode(', ', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
