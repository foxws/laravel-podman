<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

#[AsCommand(name: 'podman:setup')]
class SetupCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:setup
        {--preset=* : Override the default set of presets to generate}
    ';

    public $description = 'Generate the default set of presets for the application to run with Podman.';

    public function handle(): int
    {
        $presets = Arr::wrap($this->option('preset')) ?: $this->getPodmanQuadletDefaultPresets();

        if ($presets === []) {
            error('No default presets are configured. Set podman.presets in the config file, or pass --preset.');

            return self::FAILURE;
        }

        $this->generatePodmanPresets($presets);

        info('Setup complete. Generated: '.implode(', ', $presets));

        return self::SUCCESS;
    }
}
