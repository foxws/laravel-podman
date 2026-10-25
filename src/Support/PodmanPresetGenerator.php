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
     * Render every ".quadlets" file, plain systemd unit and drop-in, and
     * runtime build file for a preset
     * (from the vendor copy, or the published copy if it exists) into the
     * configured publish path, ready for "lpod install"/"podman quadlet
     * install" on the host. The preset's previous output is removed first,
     * so files dropped or renamed since then don't linger.
     *
     * Returns what was rendered, and where.
     */
    public function generate(string $preset): PodmanRenderedPreset
    {
        $file = new PodmanQuadletFile($this->path, $this->config);

        $target = $this->path->presetPublishPath($preset);

        if ($this->isOutputDisposable($preset, $target)) {
            File::deleteDirectory($target);
        }

        $quadlets = [];
        $systemdUnits = [];
        $runtimesPath = null;

        $quadletsSource = $this->path->presetQuadletsPath($preset);

        if (File::isDirectory($quadletsSource)) {
            foreach (File::files($quadletsSource) as $quadlet) {
                if ($quadlet->getExtension() !== 'quadlets') {
                    continue;
                }

                $quadlets[] = $file->prepareSource($quadlet->getRealPath(), "{$target}/{$quadlet->getFilename()}", $preset);
            }
        }

        $systemdDropIns = [];

        $unitsSource = $this->path->presetSystemdPath($preset);

        if (File::isDirectory($unitsSource)) {
            foreach (File::files($unitsSource) as $unit) {
                $systemdUnits[] = $file->prepareSource($unit->getRealPath(), "{$target}/{$this->systemdUnitName($unit->getFilename())}", $preset);
            }

            foreach (File::directories($unitsSource) as $directory) {
                if (! Str::endsWith($directory, '.d')) {
                    continue;
                }

                $dropInTarget = "{$target}/".strtr(basename($directory), $file->substitutions($preset));

                foreach (File::files($directory) as $dropIn) {
                    $systemdDropIns[] = $file->prepareSource($dropIn->getRealPath(), "{$dropInTarget}/{$dropIn->getFilename()}", $preset);
                }
            }
        }

        $runtimesSource = $this->path->presetRuntimesPath($preset);

        if (File::isDirectory($runtimesSource)) {
            $file->publishDirectory($runtimesSource, $runtimesPath = $this->path->presetPublishRuntimesPath($preset), $preset);
        }

        return new PodmanRenderedPreset($preset, $target, $quadlets, $systemdUnits, $runtimesPath, $systemdDropIns);
    }

    /**
     * The name a plain systemd unit is rendered under: the app's prefix in
     * front, as in "my-app-ondemand.socket". Templates, such as
     * "laravel-idle@.service", are shared by every app on the host and keep
     * their name.
     */
    public function systemdUnitName(string $fileName): string
    {
        if (Str::contains($fileName, '@.')) {
            return $fileName;
        }

        return "{$this->config->prefix()}-{$fileName}";
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
