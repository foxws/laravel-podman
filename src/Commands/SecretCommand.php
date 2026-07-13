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

#[AsCommand(name: 'podman:secret')]
class SecretCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:secret
        {service?* : The name(s) of the service to configure}
        {--replace : Replace the secret if it already exists}
    ';

    public $description = 'Set the Podman secrets used by a Quadlet service.';

    public function handle(): int
    {
        $services = Arr::wrap($this->argument('service')) ?: multiselect(
            label: 'Select the services to configure',
            options: $this->getPodmanQuadlets(),
            required: true,
        );

        $failed = [];

        foreach ($services as $service) {
            if ($this->getPodmanQuadletSecrets($service) === []) {
                info("Service {$service} does not use any secrets.");

                continue;
            }

            if (! $this->promptForPodmanQuadletSecrets($service, replace: $this->option('replace'))) {
                $failed[] = $service;

                continue;
            }

            info("Secrets for service {$service} have been set.");
        }

        if ($failed !== []) {
            error('Failed to set secrets for: '.implode(', ', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
