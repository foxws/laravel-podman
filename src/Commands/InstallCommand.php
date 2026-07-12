<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

#[AsCommand(name: 'podman:install')]
class InstallCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:install
        {service? : The name of the service to install}
        {--application= : The name of the application, installed in its own subdirectory (requires Podman 6+)}
        {--replace : Replace the service if it already exists}
        {--secrets : Also prompt for and set the secrets required by the service}
    ';

    public $description = 'Install a service for the application to run with Podman';

    public function handle(): int
    {
        $service = $this->argument('service') ?? select(
            label: 'Select a service to install',
            options: $this->getPodmanQuadlets(),
            required: true,
        );

        if ($this->option('secrets') && ! $this->promptForPodmanQuadletSecrets($service, replace: $this->option('replace'))) {
            return self::FAILURE;
        }

        $process = $this->installPodmanQuadlet(
            service: $service,
            application: $this->option('application'),
            replace: $this->option('replace'),
        );

        $process->run();

        if (! $process->isSuccessful()) {
            error($process->getErrorOutput());

            return self::FAILURE;
        }

        info("Service {$service} installed successfully.");

        return self::SUCCESS;
    }
}
