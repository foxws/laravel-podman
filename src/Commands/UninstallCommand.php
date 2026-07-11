<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

#[AsCommand(name: "podman:uninstall")]
class UninstallCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:uninstall
        {--force : Force removal of running services}
        {--reload-systemd : Reload systemd after removal}
    ';

    public $description = "Uninstall a Podman Quadlet application and remove its service.";

    public function handle(): int
    {
        $application = text(
            label: "Enter the application name to remove",
            required: true,
        );

        $process = $this->uninstallPodmanQuadlet(
            application: $application,
            force: $this->option('force'),
        );

        $process->run();

        if (!$process->isSuccessful()) {
            error($process->getErrorOutput());

            return self::FAILURE;
        }

        info("Application '{$application}' has been uninstalled successfully.");

        return self::SUCCESS;
    }
}
