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
        {preset? : The name of the preset to publish}
        {--force : Overwrite existing files}
    ';

    public $description = 'Publish a preset (its quadlets and runtime files) for customization.';

    public function handle(): int
    {
        if (! $this->ensurePodmanIsEnabled()) {
            return self::FAILURE;
        }

        $preset = $this->argument('preset') ?? select(
            label: 'Select a preset to publish',
            options: $this->getPodmanQuadletPresets(),
            required: true,
        );

        if (! $this->publishPodmanPreset($preset, force: $this->option('force'))) {
            return self::FAILURE;
        }

        info("Preset {$preset} published to {$this->podmanQuadletPath()->publishedPresetPath($preset)}");

        return self::SUCCESS;
    }
}
