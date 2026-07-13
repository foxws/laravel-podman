<?php

declare(strict_types=1);

use Foxws\Podman\Support\PodmanQuadletPath;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->path = new PodmanQuadletPath;
});

it('resolves the vendor path from the installed package', function () {
    expect($this->path->vendorPath())->toBeString()->not->toBeEmpty();
});

it('resolves the current process uid and gid by default', function () {
    expect($this->path->uid())->toBe(posix_getuid())
        ->and($this->path->gid())->toBe(posix_getgid());
});

it('uses the configured uid and gid when set', function () {
    config(['podman.quadlet_uid' => 2000, 'podman.quadlet_gid' => 2001]);

    expect($this->path->uid())->toBe(2000)
        ->and($this->path->gid())->toBe(2001);
});

it('defaults the quadlets path to the vendor quadlets directory', function () {
    expect($this->path->quadletsPath())->toBe("{$this->path->vendorPath()}/quadlets");
});

it('uses the configured quadlets path when set', function () {
    $path = $this->makeQuadletsPath(['pgsql']);

    expect($this->path->quadletsPath())->toBe($path);

    File::deleteDirectory($path);
});

it('falls back to the vendor quadlets directory when the configured path does not exist', function () {
    config(['podman.quadlets_path' => sys_get_temp_dir().'/podman-quadlets-missing-'.uniqid()]);

    expect($this->path->quadletsPath())->toBe("{$this->path->vendorPath()}/quadlets");
});

it('defaults the runtimes path to the vendor runtimes directory', function () {
    expect($this->path->runtimesPath())->toBe("{$this->path->vendorPath()}/runtimes");
});

it('uses the configured runtimes path when it exists', function () {
    $path = sys_get_temp_dir().'/podman-runtimes-'.uniqid();
    File::ensureDirectoryExists($path);
    config(['podman.runtimes_path' => $path]);

    expect($this->path->runtimesPath())->toBe($path);

    File::deleteDirectory($path);
});

it('falls back to the vendor runtimes directory when the configured runtimes path does not exist', function () {
    config(['podman.runtimes_path' => sys_get_temp_dir().'/podman-runtimes-missing-'.uniqid()]);

    expect($this->path->runtimesPath())->toBe("{$this->path->vendorPath()}/runtimes");
});

it('resolves a relative runtime path against the base path', function () {
    config(['podman.runtime_path' => 'runtimes']);

    expect($this->path->runtimePath())->toBe(base_path('runtimes'));
});

it('keeps an absolute runtime path as-is', function () {
    config(['podman.runtime_path' => '/srv/runtimes']);

    expect($this->path->runtimePath())->toBe('/srv/runtimes');
});

it('resolves a relative config path against the base path', function () {
    config(['podman.config_path' => 'runtimes/config']);

    expect($this->path->configPath())->toBe(base_path('runtimes/config'));
});

it('keeps an absolute config path as-is', function () {
    config(['podman.config_path' => '/srv/runtimes/config']);

    expect($this->path->configPath())->toBe('/srv/runtimes/config');
});

it('resolves the domain from the app url', function () {
    config(['app.url' => 'https://example.test']);

    expect($this->path->domain())->toBe('example.test');
});

it('kebab-cases the configured quadlet prefix', function () {
    config(['podman.quadlet_prefix' => 'My App']);

    expect($this->path->prefix())->toBe('my-app');
});

it('defaults reload systemd to true', function () {
    expect($this->path->shouldReloadSystemd())->toBeTrue();
});

it('disables reload systemd when configured', function () {
    config(['podman.reload_systemd' => false]);

    expect($this->path->shouldReloadSystemd())->toBeFalse();
});

it('defaults selinux volume mapping to true', function () {
    expect($this->path->shouldUseSelinuxVolumeMapping())->toBeTrue();
});

it('disables selinux volume mapping when configured', function () {
    config(['podman.selinux_volume_mapping' => false]);

    expect($this->path->shouldUseSelinuxVolumeMapping())->toBeFalse();
});

it('splits the configured comma-separated services into an array', function () {
    config(['podman.services' => 'pgsql,valkey,app']);

    expect($this->path->defaultServices())->toBe(['pgsql', 'valkey', 'app']);
});

it('accepts the configured services as a plain array', function () {
    config(['podman.services' => ['pgsql', 'valkey', 'app']]);

    expect($this->path->defaultServices())->toBe(['pgsql', 'valkey', 'app']);
});

it('trims whitespace and drops empty entries from an array of configured services', function () {
    config(['podman.services' => [' pgsql ', '', ' valkey ']]);

    expect($this->path->defaultServices())->toBe(['pgsql', 'valkey']);
});

it('trims whitespace and drops empty entries from the configured services', function () {
    config(['podman.services' => ' pgsql ,, valkey ']);

    expect($this->path->defaultServices())->toBe(['pgsql', 'valkey']);
});

it('returns no default services when none are configured', function () {
    config(['podman.services' => '']);

    expect($this->path->defaultServices())->toBe([]);
});
