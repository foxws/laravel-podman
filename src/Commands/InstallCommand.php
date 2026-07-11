<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'podman:install')]
class InstallCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:install
        {--reload-systemd : Reload systemd after installation}
        {--replace : Replace the service if it already exists}
    ';

    public $description = 'Install a service for the application to run with Podman';

    public function handle(): int
    {
        $application = text(
            label: 'Enter the application name',
            placeholder: 'my-app',
        );

        $service = select(
            label: 'Select a service to install',
            options: $this->getPodmanQuadletServices(),
            required: true,
        );

        return self::SUCCESS;
    }
}
