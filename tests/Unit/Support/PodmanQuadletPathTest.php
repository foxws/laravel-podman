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

it('resolves the base path to Laravel\'s base_path', function () {
    expect($this->path->basePath())->toBe(base_path());
});

it('resolves the working path to the base path by default', function () {
    expect($this->path->workingPath())->toBe($this->path->basePath());
});

it('uses the configured working path when set, without affecting the base path', function () {
    config(['podman.working_path' => '/home/francois/app']);

    expect($this->path->workingPath())->toBe('/home/francois/app')
        ->and($this->path->basePath())->toBe(base_path());
});

it('does not let the configured working path affect where runtimes/config/publish paths resolve', function () {
    config(['podman.working_path' => '/home/francois/app', 'podman.runtime_path' => 'runtimes']);

    expect($this->path->runtimePath())->toBe(base_path('runtimes'));
});

it('resolves the working runtime and config paths against the working path when set', function () {
    config([
        'podman.working_path' => '/home/francois/app',
        'podman.runtime_path' => 'runtimes',
        'podman.config_path' => 'runtimes/config',
    ]);

    expect($this->path->workingRuntimePath())->toBe('/home/francois/app/runtimes')
        ->and($this->path->workingConfigPath())->toBe('/home/francois/app/runtimes/config');
});

it('keeps an absolute runtime or config path as-is for the working variants, ignoring the working path', function () {
    config([
        'podman.working_path' => '/home/francois/app',
        'podman.runtime_path' => '/srv/runtimes',
        'podman.config_path' => '/srv/runtimes/config',
    ]);

    expect($this->path->workingRuntimePath())->toBe('/srv/runtimes')
        ->and($this->path->workingConfigPath())->toBe('/srv/runtimes/config');
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

it('resolves a relative publish path against the base path', function () {
    config(['podman.publish_path' => 'storage/app/podman']);

    expect($this->path->publishPath())->toBe(base_path('storage/app/podman'));
});

it('keeps an absolute publish path as-is', function () {
    config(['podman.publish_path' => '/srv/podman']);

    expect($this->path->publishPath())->toBe('/srv/podman');
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

it('splits the configured comma-separated runtimes into an array', function () {
    config(['podman.runtimes' => 'frankenphp-octane,proxy']);

    expect($this->path->defaultRuntimes())->toBe(['frankenphp-octane', 'proxy']);
});

it('accepts the configured runtimes as a plain array', function () {
    config(['podman.runtimes' => ['frankenphp-octane', 'proxy']]);

    expect($this->path->defaultRuntimes())->toBe(['frankenphp-octane', 'proxy']);
});

it('trims whitespace and drops empty entries from the configured runtimes', function () {
    config(['podman.runtimes' => ' frankenphp-octane ,, proxy ']);

    expect($this->path->defaultRuntimes())->toBe(['frankenphp-octane', 'proxy']);
});

it('returns no default runtimes when none are configured', function () {
    config(['podman.runtimes' => '']);

    expect($this->path->defaultRuntimes())->toBe([]);
});

it('splits the configured comma-separated s3 buckets into an array', function () {
    config(['podman.s3_buckets' => 'local,conversions,secrets']);

    expect($this->path->s3Buckets())->toBe(['local', 'conversions', 'secrets']);
});

it('accepts the configured s3 buckets as a plain array', function () {
    config(['podman.s3_buckets' => ['local', 'conversions', 'secrets']]);

    expect($this->path->s3Buckets())->toBe(['local', 'conversions', 'secrets']);
});

it('returns no s3 buckets when none are configured', function () {
    config(['podman.s3_buckets' => '']);

    expect($this->path->s3Buckets())->toBe([]);
});

it('splits the configured comma-separated s3 cors buckets into an array', function () {
    config(['podman.s3_cors_buckets' => 'conversions,secrets']);

    expect($this->path->s3CorsBuckets())->toBe(['conversions', 'secrets']);
});

it('returns no s3 cors buckets when none are configured', function () {
    config(['podman.s3_cors_buckets' => '']);

    expect($this->path->s3CorsBuckets())->toBe([]);
});

it('resolves the s3 cors policy path against the runtime path', function () {
    config(['podman.runtime_path' => 'runtimes']);

    expect($this->path->s3CorsPolicyPath())->toBe(base_path('runtimes').'/s3/cors.json');
});
