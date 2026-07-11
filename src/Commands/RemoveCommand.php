<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

#[AsCommand(name: "podman:remove")]
class RemoveCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:remove
        {--force : Force removal of running services}
        {--ignore: Ignore missing services and continue with removal}
        {--reload-systemd : Reload systemd after removal}
    ';

    public $description = "Remove a Podman Quadlet service or application.";

    public function handle(): int
    {
        $service = select(
            label: "Select a service to remove",
            options: $this->getPodmanQuadletServices(),
            required: true,
        );

        $process = $this->removePodmanQuadlet(
            service: $service,
            ignore: $this->option('ignore'),
            force: $this->option('force'),
        );

        $process->run();

        if (!$process->isSuccessful()) {
            error($process->getErrorOutput());

            return self::FAILURE;
        }

        info("Service {$service} removed successfully.");

        return self::SUCCESS;
    }
}
