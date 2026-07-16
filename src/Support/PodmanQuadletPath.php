<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Composer\InstalledVersions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

class PodmanQuadletPath
{
    public function vendorPath(): string
    {
        return Str::rtrim(
            InstalledVersions::getInstallPath('foxws/laravel-podman'),
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

    public function domain(): string
    {
        return Uri::of(Config::string('app.url'))->host();
    }

    public function prefix(): string
    {
        return Str::kebab(Config::string('podman.quadlet_prefix'));
    }

    public function proxy(): string
    {
        return Str::kebab(Config::string('podman.proxy_prefix'));
    }

    public function uid(): int
    {
        $uid = Config::get('podman.quadlet_uid');

        if ($uid !== null) {
            return (int) $uid;
        }

        return function_exists('posix_getuid') ? posix_getuid() : 1000;
    }

    public function gid(): int
    {
        $gid = Config::get('podman.quadlet_gid');

        if ($gid !== null) {
            return (int) $gid;
        }

        return function_exists('posix_getgid') ? posix_getgid() : 1000;
    }

    /**
     * @return array<int, string>
     */
    public function defaultPresets(): array
    {
        return $this->parseNameList(Config::get('podman.presets', []));
    }

    /**
     * @return array<int, string>
     */
    public function s3Buckets(): array
    {
        return $this->parseNameList(Config::get('podman.s3_buckets', []));
    }

    /**
     * @return array<int, string>
     */
    public function s3CorsBuckets(): array
    {
        return $this->parseNameList(Config::get('podman.s3_cors_buckets', []));
    }

    public function s3CorsPolicyPath(): string
    {
        return "{$this->presetPath('s3')}/cors.json";
    }

    public function shouldUseSelinuxVolumeMapping(): bool
    {
        return Config::boolean('podman.selinux_volume_mapping');
    }

    protected function resolvePath(string $path, string $base): string
    {
        return Str::startsWith($path, '/') ? $path : Str::rtrim($base, '/')."/{$path}";
    }

    /**
     * Normalize a config value that may be either a comma-separated string
     * or a plain array into a list of trimmed, non-empty names.
     *
     * @return array<int, string>
     */
    protected function parseNameList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        $value = Arr::map($value, fn (mixed $item): string => trim((string) $item));

        return array_values(Arr::where($value, fn (string $item): bool => $item !== ''));
    }
}
