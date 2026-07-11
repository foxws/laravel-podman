<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;
use function Laravel\Prompts\select;

#[AsCommand(name: 'podman:print')]
class PrintCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:print';

    public $description = 'List all Quadlets configured for the current user.';

    public function handle(): int
    {
        $service = select(
            label: 'Select a service to print',
            options: $this->getPodmanQuadletServices(),
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

        return self::SUCCESS;
    }
}
