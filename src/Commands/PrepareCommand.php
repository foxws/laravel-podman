<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'podman:prepare')]
class PrepareCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:prepare
        {--path : The path to the application directory}
        {--replace : Replace the existing service if it already exists}
    ';

    public $description = 'Prepare the application for Podman Quadlet.';

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

        $process = $this->installPodmanQuadlet(
            application: $application,
            service: $service,
            replace: $this->option('replace'),
        );

        $process->run();

        if (! $process->isSuccessful()) {
            error($process->getErrorOutput());

            return self::FAILURE;
        }

        info("Service {$service} installed for application {$application}");

        return self::SUCCESS;
    }
}
