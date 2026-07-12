<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;
use function Laravel\Prompts\text;

#[AsCommand(name: 'podman:print')]
class PrintCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:print {service? : The name of the service to print}';

    public $description = 'List all Quadlets configured for the current user.';

    public function handle(): int
    {
        $service = $this->argument('service') ?? text(
            label: 'Enter the name of the service to print',
            required: true,
        );

        $process = $this->printPodmanQuadlet(
            service: $service,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            error($process->getErrorOutput());

            return self::FAILURE;
        }

        $this->line($process->getOutput());

        return self::SUCCESS;
    }
}
