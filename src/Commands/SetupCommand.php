<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

#[AsCommand(name: 'podman:setup')]
class SetupCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:setup
        {--service=* : Override the default set of services to install}
        {--runtime= : The name of the runtime to publish}
        {--application= : The name of the application, installed in its own subdirectory (requires Podman 6+)}
        {--replace : Replace a service if it already exists}
        {--secrets : Also prompt for and set the secrets required by each service}
        {--force : Overwrite existing published runtime files}
    ';

    public $description = 'Publish the runtime and install the default set of services for the application to run with Podman';

    public function handle(): int
    {
        $runtimes = $this->getPodmanQuadletRuntimes();

        $runtime = $this->option('runtime') ?? (
            count($runtimes) === 1
                ? Arr::first($runtimes)
                : select(label: 'Select a runtime to publish', options: $runtimes, required: true)
        );

        if (! $this->publishPodmanRuntime($runtime, force: $this->option('force'))) {
            return self::FAILURE;
        }

        info("Runtime {$runtime} published to {$this->getPodmanRuntimePath()}");

        $services = Arr::wrap($this->option('service')) ?: $this->getPodmanQuadletDefaultServices();

        if ($services === []) {
            error('No default services are configured. Set podman.services in the config file, or pass --service.');

            return self::FAILURE;
        }

        $failed = $this->installPodmanQuadlets(
            services: $services,
            application: $this->option('application'),
            replace: $this->option('replace'),
            secrets: $this->option('secrets'),
        );

        if ($failed !== []) {
            error('Failed to install: '.implode(', ', $failed));

            return self::FAILURE;
        }

        info('Setup complete. Installed: '.implode(', ', $services));

        return self::SUCCESS;
    }
}
