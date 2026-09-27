<?php

declare(strict_types=1);

namespace Foxws\Podman\Concerns;

use Foxws\Podman\Support\PodmanQuadletFile;
use Foxws\Podman\Support\PodmanQuadletPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

trait InteractsWithPodmanQuadlet
{
    protected function podmanQuadletPath(): PodmanQuadletPath
    {
        return new PodmanQuadletPath;
    }

    protected function podmanQuadletFile(): PodmanQuadletFile
    {
        return new PodmanQuadletFile($this->podmanQuadletPath());
    }

    /**
     * Guard "podman:*" commands behind "podman.enabled".
     */
    protected function ensurePodmanIsEnabled(): bool
    {
        if ($this->podmanQuadletPath()->isEnabled()) {
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
    protected function publishPodmanPreset(string $preset, ?bool $force = null): bool
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
    protected function publishPodmanPresets(array $presets, ?bool $force = null): array
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
     * Render every ".quadlets" file and runtime build file for a preset
     * (from the vendor copy, or the published copy if it exists) into the
     * configured publish path, ready for "lpod install"/"podman quadlet
     * install" on the host.
     */
    protected function generatePodmanPreset(string $preset): void
    {
        $path = $this->podmanQuadletPath();
        $file = $this->podmanQuadletFile();

        $target = $path->presetPublishPath($preset);

        foreach ($this->getPodmanPresetQuadlets($preset) as $filename => $source) {
            $file->prepareSource($source, "{$target}/{$filename}", $preset);
        }

        foreach ($this->getPodmanPresetOnDemandUnits($preset) as $filename => $source) {
            $file->prepareSource($source, "{$target}/{$path->prefix()}-{$filename}", $preset);
        }

        $runtimesSource = $path->presetRuntimesPath($preset);

        if (File::isDirectory($runtimesSource)) {
            $file->publishDirectory($runtimesSource, $path->presetPublishRuntimesPath($preset), $preset);
        }
    }

    /**
     * A preset's ".quadlets" sources keyed by filename. When the preset is
     * rendered on-demand, its "ondemand/quadlets/" files replace the
     * preset's own files of the same name.
     *
     * @return array<string, string>
     */
    protected function getPodmanPresetQuadlets(string $preset): array
    {
        $path = $this->podmanQuadletPath();

        $directories = [$path->presetQuadletsPath($preset)];

        if ($path->usesOnDemand($preset)) {
            $directories[] = "{$path->presetOnDemandPath($preset)}/quadlets";
        }

        return Collection::make($directories)
            ->filter(fn (string $directory): bool => File::isDirectory($directory))
            ->flatMap(fn (string $directory): array => File::files($directory))
            ->filter(fn (SplFileInfo $quadlet): bool => $quadlet->getExtension() === 'quadlets')
            ->mapWithKeys(fn (SplFileInfo $quadlet): array => [$quadlet->getFilename() => $quadlet->getRealPath()])
            ->all();
    }

    /**
     * A preset's on-demand systemd units (sockets, services, timers) keyed
     * by filename, or none when the preset isn't rendered on-demand.
     *
     * @return array<string, string>
     */
    protected function getPodmanPresetOnDemandUnits(string $preset): array
    {
        $path = $this->podmanQuadletPath();

        $directory = "{$path->presetOnDemandPath($preset)}/systemd";

        if (! $path->usesOnDemand($preset) || ! File::isDirectory($directory)) {
            return [];
        }

        return Collection::make(File::files($directory))
            ->mapWithKeys(fn (SplFileInfo $unit): array => [$unit->getFilename() => $unit->getRealPath()])
            ->all();
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
        return $this->podmanQuadletPath()->defaultPresets();
    }
}
