<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Renders presets into the publish path, without a console. Used by
 * "podman:setup"/"podman:generate", and by apps that render presets
 * themselves, such as an installer.
 */
class PodmanPresetGenerator
{
    public function __construct(
        protected PodmanQuadletPath $path = new PodmanQuadletPath,
        protected PodmanConfig $config = new PodmanConfig,
    ) {}

    /**
     * Render every ".quadlets" file and runtime build file for a preset
     * (from the vendor copy, or the published copy if it exists) into the
     * configured publish path, ready for "lpod install"/"podman quadlet
     * install" on the host. The preset's previous output is removed first,
     * so files dropped or renamed since then don't linger.
     *
     * Returns the directory the preset was rendered to.
     */
    public function generate(string $preset): string
    {
        $file = new PodmanQuadletFile($this->path, $this->config);

        $target = $this->path->presetPublishPath($preset);

        if ($this->isOutputDisposable($preset, $target)) {
            File::deleteDirectory($target);
        }

        $quadletsSource = $this->path->presetQuadletsPath($preset);

        if (File::isDirectory($quadletsSource)) {
            foreach (File::files($quadletsSource) as $quadlet) {
                if ($quadlet->getExtension() !== 'quadlets') {
                    continue;
                }

                $file->prepareSource($quadlet->getRealPath(), "{$target}/{$quadlet->getFilename()}", $preset);
            }
        }

        $unitsSource = $this->path->presetSystemdPath($preset);

        if (File::isDirectory($unitsSource)) {
            foreach (File::files($unitsSource) as $unit) {
                $file->prepareSource($unit->getRealPath(), "{$target}/{$this->config->prefix()}-{$unit->getFilename()}", $preset);
            }
        }

        $runtimesSource = $this->path->presetRuntimesPath($preset);

        if (File::isDirectory($runtimesSource)) {
            $file->publishDirectory($runtimesSource, $this->path->presetPublishRuntimesPath($preset), $preset);
        }

        return $target;
    }

    /**
     * Whether a preset's output directory only holds generated files. It
     * doesn't when "publish_path" points at (or above) the preset's own
     * templates, which must never be deleted.
     */
    public function isOutputDisposable(string $preset, string $target): bool
    {
        $target = realpath($target);
        $source = realpath($this->path->presetPath($preset));

        if ($target === false || $source === false) {
            return $target !== false;
        }

        return ! Str::startsWith("{$source}/", "{$target}/");
    }
}
