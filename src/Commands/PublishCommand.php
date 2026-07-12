<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

#[AsCommand(name: 'podman:publish')]
class PublishCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:publish
        {--force : Overwrite existing files}
    ';

    public $description = 'Publish the Podman container runtime files for customization.';

    public function handle(): int
    {
        $runtime = select(
            label: 'Select a runtime to publish',
            options: $this->getPodmanQuadletRuntimes(),
            required: true,
        );

        $source = "{$this->getPodmanQuadletVendorPath()}/runtimes/{$runtime}";
        $target = $this->getPodmanQuadletContainerPath();

        if (File::exists("{$target}/Containerfile") && ! $this->option('force')) {
            error("A Containerfile already exists at {$target}. Use --force to overwrite.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists("{$target}/runtimes");
        File::copy("{$source}/Containerfile", "{$target}/Containerfile");

        foreach (File::files($source) as $file) {
            if ($file->getFilename() === 'Containerfile') {
                continue;
            }

            File::copy($file->getPathname(), "{$target}/runtimes/{$file->getFilename()}");
        }

        info("Runtime {$runtime} published to {$target}");

        return self::SUCCESS;
    }
}
