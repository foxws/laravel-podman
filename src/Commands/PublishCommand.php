<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

#[AsCommand(name: 'podman:publish')]
class PublishCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:publish
        {runtime? : The name of the runtime to publish}
        {--force : Overwrite existing files}
    ';

    public $description = 'Publish the Podman container runtime files for customization.';

    public function handle(): int
    {
        $runtime = $this->argument('runtime') ?? select(
            label: 'Select a runtime to publish',
            options: $this->getPodmanQuadletRuntimes(),
            required: true,
        );

        if (! $this->publishPodmanQuadletRuntime($runtime, force: $this->option('force'))) {
            return self::FAILURE;
        }

        if (! $this->publishPodmanQuadletProxy(force: $this->option('force'))) {
            return self::FAILURE;
        }

        info("Runtime {$runtime} published to {$this->getPodmanQuadletContainerPublishPath()}");
        info("Proxy configuration published to {$this->getPodmanQuadletProxyPublishPath()}");

        return self::SUCCESS;
    }
}
