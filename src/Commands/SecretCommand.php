<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

#[AsCommand(name: 'podman:secret')]
class SecretCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:secret
        {service? : The name of the service to configure}
        {--replace : Replace the secret if it already exists}
    ';

    public $description = 'Set the Podman secrets used by a Quadlet service.';

    public function handle(): int
    {
        $service = $this->argument('service') ?? select(
            label: 'Select a service to configure',
            options: $this->getPodmanQuadletServices(),
            required: true,
        );

        if ($this->getPodmanQuadletSecrets($service) === []) {
            info("Service {$service} does not use any secrets.");

            return self::SUCCESS;
        }

        if (! $this->promptForPodmanQuadletSecrets($service, replace: $this->option('replace'))) {
            return self::FAILURE;
        }

        info("Secrets for service {$service} have been set.");

        return self::SUCCESS;
    }
}
