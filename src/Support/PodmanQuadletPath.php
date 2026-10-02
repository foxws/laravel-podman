<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Where presets are read from and rendered to.
 */
class PodmanQuadletPath
{
    public function vendorPath(): string
    {
        // Composer knows no install path when the package isn't installed
        // through it, e.g. in its own test suite, so fall back to its root.
        return Str::rtrim(
            InstalledVersions::getInstallPath('foxws/laravel-podman') ?? dirname(__DIR__, 2),
            '/',
        );
    }

    public function stubsPath(): string
    {
        return Config::string('podman.stubs_path');
    }

    /**
     * The preset's original, vendor-provided source directory. Always the
     * vendor copy regardless of whether it's been published, since this is
     * what "podman:publish" copies from.
     */
    public function vendorPresetPath(string $preset): string
    {
        return "{$this->vendorPath()}/stubs/{$preset}";
    }

    /**
     * Where "podman:publish" copies a preset to for customization.
     */
    public function publishedPresetPath(string $preset): string
    {
        return "{$this->stubsPath()}/{$preset}";
    }

    /**
     * A preset's source directory ("quadlets/" + "runtimes/") to read
     * templates from. Falls back to the vendor-provided preset per preset,
     * not as a whole directory swap — customizing one preset doesn't
     * require copying every other preset too.
     */
    public function presetPath(string $preset): string
    {
        $candidate = $this->publishedPresetPath($preset);

        if (File::isDirectory($candidate)) {
            return $candidate;
        }

        return $this->vendorPresetPath($preset);
    }

    public function presetQuadletsPath(string $preset): string
    {
        return "{$this->presetPath($preset)}/quadlets";
    }

    public function presetRuntimesPath(string $preset): string
    {
        return "{$this->presetPath($preset)}/runtimes";
    }

    /**
     * A preset's plain systemd units (the on-demand socket and its proxy
     * service, timers), rendered next to its ".quadlets" files. Quadlet has
     * no unit type for these.
     */
    public function presetSystemdPath(string $preset): string
    {
        return "{$this->presetPath($preset)}/systemd";
    }

    public function basePath(): string
    {
        return base_path();
    }

    /**
     * The real, host-visible project path, used only for values baked into
     * rendered Quadlet content ("{{workingPath}}"/"{{runtimePath}}") — not
     * for where this process itself reads or writes files (that's always
     * relative to basePath()). They differ when Artisan renders templates
     * from somewhere whose filesystem view of the project isn't the host's,
     * e.g. inside the disposable container used by "Setting up without PHP
     * on the host".
     */
    public function workingPath(): string
    {
        return Config::get('podman.working_path') ?: $this->basePath();
    }

    /**
     * The host path baked into the "{{configPath}}" placeholder, for
     * services that keep their configuration outside the project itself.
     * Falls back to workingPath() when unset.
     */
    public function configPath(): string
    {
        return Config::get('podman.config_path') ?: $this->workingPath();
    }

    public function publishPath(): string
    {
        return $this->resolvePath(Config::get('podman.publish_path'), $this->basePath());
    }

    /**
     * Where a preset's generated ".quadlets" files and "runtimes/" build
     * files are written to, relative to this process's own filesystem.
     */
    public function presetPublishPath(string $preset): string
    {
        return "{$this->publishPath()}/{$preset}";
    }

    public function presetPublishRuntimesPath(string $preset): string
    {
        return "{$this->presetPublishPath($preset)}/runtimes";
    }

    /**
     * The host-visible equivalent of presetPublishRuntimesPath(), baked into
     * the "{{runtimePath}}" placeholder.
     */
    public function workingPresetRuntimePath(string $preset): string
    {
        $publishPath = $this->resolvePath(Config::get('podman.publish_path'), $this->workingPath());

        return "{$publishPath}/{$preset}/runtimes";
    }

    public function s3CorsPolicyPath(): string
    {
        return "{$this->presetPath('s3')}/cors.json";
    }

    protected function resolvePath(string $path, string $base): string
    {
        return Str::startsWith($path, '/') ? $path : Str::rtrim($base, '/')."/{$path}";
    }
}
