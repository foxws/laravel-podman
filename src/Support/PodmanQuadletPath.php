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

    public function quadletsPath(): string
    {
        $path = Config::get('podman.quadlets_path');

        if ($path && File::isDirectory($path)) {
            return $path;
        }

        return "{$this->vendorPath()}/quadlets";
    }

    public function runtimesPath(): string
    {
        $path = Config::get('podman.runtimes_path');

        if ($path && File::isDirectory($path)) {
            return $path;
        }

        return "{$this->vendorPath()}/runtimes";
    }

    public function basePath(): string
    {
        return base_path();
    }

    /**
     * The real, host-visible project path, used only for values baked into
     * rendered Quadlet content ("{{base-path}}" and the other placeholders
     * below) — not for where this process itself reads or writes files
     * (that's always relative to basePath()). They differ when Artisan
     * renders templates from somewhere whose filesystem view of the project
     * isn't the host's, e.g. inside the disposable container used by
     * "Setting up without PHP on the host".
     */
    public function workingPath(): string
    {
        return Config::get('podman.working_path') ?: $this->basePath();
    }

    public function runtimePath(): string
    {
        return $this->resolvePath(Config::get('podman.runtime_path'), $this->basePath());
    }

    public function workingRuntimePath(): string
    {
        return $this->resolvePath(Config::get('podman.runtime_path'), $this->workingPath());
    }

    public function configPath(): string
    {
        return $this->resolvePath(Config::get('podman.config_path'), $this->basePath());
    }

    public function workingConfigPath(): string
    {
        return $this->resolvePath(Config::get('podman.config_path'), $this->workingPath());
    }

    public function publishPath(): string
    {
        return $this->resolvePath(Config::get('podman.publish_path'), $this->basePath());
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
    public function defaultServices(): array
    {
        return $this->parseNameList(Config::get('podman.services', []));
    }

    /**
     * @return array<int, string>
     */
    public function defaultRuntimes(): array
    {
        return $this->parseNameList(Config::get('podman.runtimes', []));
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
        return "{$this->runtimePath()}/s3/cors.json";
    }

    public function shouldReloadSystemd(): bool
    {
        return Config::boolean('podman.reload_systemd');
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
