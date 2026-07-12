<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Composer\InstalledVersions;
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

    public function runtimePath(): string
    {
        return Config::get('podman.runtime_path');
    }

    public function configPath(): string
    {
        return Config::get('podman.config_path');
    }

    public function temporaryPath(): string
    {
        return Config::get('podman.temporary_path');
    }

    public function domain(): string
    {
        return Uri::of(Config::string('app.url'))->host();
    }

    public function prefix(): string
    {
        return Str::kebab(Config::string('podman.quadlet_prefix'));
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

    public function shouldReloadSystemd(): bool
    {
        return Config::boolean('podman.reload_systemd');
    }

    public function shouldUseSelinuxVolumeMapping(): bool
    {
        return Config::boolean('podman.selinux_volume_mapping');
    }
}
