<?php

declare(strict_types=1);

namespace Foxws\Podman\Concerns;

use Foxws\Podman\Support\PodmanConfig;
use Foxws\Podman\Support\PodmanPresetGenerator;
use Foxws\Podman\Support\PodmanQuadletFile;
use Foxws\Podman\Support\PodmanQuadletPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

trait InteractsWithPodmanQuadlet
{
    protected function podmanQuadletPath(): PodmanQuadletPath
    {
        return new PodmanQuadletPath;
    }

    protected function podmanConfig(): PodmanConfig
    {
        return new PodmanConfig;
    }

    protected function podmanQuadletFile(): PodmanQuadletFile
    {
        return new PodmanQuadletFile($this->podmanQuadletPath(), $this->podmanConfig());
    }

    /**
     * Guard "podman:*" commands behind "podman.enabled".
     */
    protected function ensurePodmanIsEnabled(): bool
    {
        if ($this->podmanConfig()->isEnabled()) {
            return true;
        }

        error('Podman is disabled. Set PODMAN_ENABLED=true (or podman.enabled) to run this command.');

        return false;
    }

    /**
     * Copy a preset's vendor-provided "quadlets/"/"runtimes/" files into the
     * configured stubs path for customization, keeping "{{placeholder}}"
     * tokens intact so "podman:generate" can still substitute them later.
     */
    protected function publishPodmanPreset(string $preset, bool $force = false): bool
    {
        $path = $this->podmanQuadletPath();
        $source = $path->vendorPresetPath($preset);
        $target = $path->publishedPresetPath($preset);

        if (File::isDirectory($target) && ! $force) {
            error("The preset {$preset} already exists at {$target}. Use --force to overwrite.");

            return false;
        }

        File::copyDirectory($source, $target);

        return true;
    }

    /**
     * Publish one or more presets. Returns the names of the presets that failed.
     *
     * @param  array<int, string>  $presets
     * @return array<int, string>
     */
    protected function publishPodmanPresets(array $presets, bool $force = false): array
    {
        $failed = [];

        foreach ($presets as $preset) {
            if (! $this->publishPodmanPreset($preset, force: $force)) {
                $failed[] = $preset;

                continue;
            }

            info("Preset {$preset} published to {$this->podmanQuadletPath()->publishedPresetPath($preset)}");
        }

        return $failed;
    }

    /**
     * Render a preset into the configured publish path.
     *
     * @see PodmanPresetGenerator::generate()
     */
    protected function generatePodmanPreset(string $preset): void
    {
        (new PodmanPresetGenerator($this->podmanQuadletPath(), $this->podmanConfig()))->generate($preset);
    }

    /**
     * Generate one or more presets.
     *
     * @param  array<int, string>  $presets
     */
    protected function generatePodmanPresets(array $presets): void
    {
        foreach ($presets as $preset) {
            $this->generatePodmanPreset($preset);

            info("Preset {$preset} generated to {$this->podmanQuadletPath()->presetPublishPath($preset)}");
        }
    }

    /**
     * The given "preset" argument, or the preset the user picks when it's
     * omitted.
     */
    protected function resolvePodmanPreset(mixed $preset, string $label): string
    {
        if (is_string($preset) && $preset !== '') {
            return $preset;
        }

        return (string) select(
            label: $label,
            options: $this->getPodmanQuadletPresets(),
            required: true,
        );
    }

    /**
     * The names of every preset available, from both the vendor-provided
     * "stubs/" directory and the configured stubs path (deduplicated).
     *
     * @return array<string, string>
     */
    protected function getPodmanQuadletPresets(): array
    {
        $path = $this->podmanQuadletPath();

        $presets = Collection::make(File::directories("{$path->vendorPath()}/stubs"))
            ->map(fn (string $dir): string => basename($dir));

        $stubsPath = $path->stubsPath();

        if ($stubsPath && File::isDirectory($stubsPath)) {
            $presets = $presets->merge(
                Collection::make(File::directories($stubsPath))
                    ->map(fn (string $dir): string => basename($dir)),
            );
        }

        return $presets->unique()
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $preset): array => [$preset => $preset])
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    protected function getPodmanQuadletDefaultPresets(): array
    {
        return $this->podmanConfig()->defaultPresets();
    }
}
