<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

#[AsCommand(name: 'podman:generate')]
class GenerateCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:generate
        {preset? : The name of the preset to generate}
    ';

    public $description = 'Render a preset\'s quadlets and runtime files, ready to install with "lpod install".';

    public function handle(): int
    {
        if (! $this->ensurePodmanIsEnabled()) {
            return self::FAILURE;
        }

        $preset = $this->argument('preset') ?? select(
            label: 'Select a preset to generate',
            options: $this->getPodmanQuadletPresets(),
            required: true,
        );

        $this->generatePodmanPreset($preset);

        info("Preset {$preset} generated to {$this->podmanQuadletPath()->presetPublishPath($preset)}");

        return self::SUCCESS;
    }
}
