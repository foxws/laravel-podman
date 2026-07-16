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

it('resolves the vendor preset path for a preset that has not been published', function () {
    expect($this->path->presetPath('frankenphp-octane'))->toBe($this->path->vendorPresetPath('frankenphp-octane'))
        ->and($this->path->vendorPresetPath('frankenphp-octane'))->toBe("{$this->path->vendorPath()}/stubs/frankenphp-octane");
});

it('uses the published preset path when it exists', function () {
    $preset = $this->makePresetPath('frankenphp-octane', ['app']);

    expect($this->path->presetPath('frankenphp-octane'))->toBe($preset)
        ->and($this->path->publishedPresetPath('frankenphp-octane'))->toBe($preset);

    File::deleteDirectory(dirname($preset));
});

it('resolves presetQuadletsPath and presetRuntimesPath relative to the preset path', function () {
    expect($this->path->presetQuadletsPath('frankenphp-octane'))->toBe($this->path->vendorPresetPath('frankenphp-octane').'/quadlets')
        ->and($this->path->presetRuntimesPath('frankenphp-octane'))->toBe($this->path->vendorPresetPath('frankenphp-octane').'/runtimes');
});

it('resolves a relative publish path against the base path', function () {
    config(['podman.publish_path' => 'podman']);

    expect($this->path->publishPath())->toBe(base_path('podman'));
});

it('keeps an absolute publish path as-is', function () {
    config(['podman.publish_path' => '/srv/podman']);

    expect($this->path->publishPath())->toBe('/srv/podman');
});

it('resolves the preset publish path and its runtimes subfolder', function () {
    config(['podman.publish_path' => 'podman']);

    expect($this->path->presetPublishPath('frankenphp-octane'))->toBe(base_path('podman/frankenphp-octane'))
        ->and($this->path->presetPublishRuntimesPath('frankenphp-octane'))->toBe(base_path('podman/frankenphp-octane/runtimes'));
});

it('resolves the working preset runtime path against the working path when set', function () {
    config(['podman.publish_path' => 'podman', 'podman.working_path' => '/home/francois/app']);

    expect($this->path->workingPresetRuntimePath('frankenphp-octane'))->toBe('/home/francois/app/podman/frankenphp-octane/runtimes');
});

it('resolves the working preset runtime path against the base path by default', function () {
    config(['podman.publish_path' => 'podman']);

    expect($this->path->workingPresetRuntimePath('frankenphp-octane'))->toBe(base_path('podman/frankenphp-octane/runtimes'));
});

it('resolves the domain from the app url', function () {
    config(['app.url' => 'https://example.test']);

    expect($this->path->domain())->toBe('example.test');
});

it('kebab-cases the configured quadlet prefix', function () {
    config(['podman.quadlet_prefix' => 'My App']);

    expect($this->path->prefix())->toBe('my-app');
});

it('defaults selinux volume mapping to true', function () {
    expect($this->path->shouldUseSelinuxVolumeMapping())->toBeTrue();
});

it('disables selinux volume mapping when configured', function () {
    config(['podman.selinux_volume_mapping' => false]);

    expect($this->path->shouldUseSelinuxVolumeMapping())->toBeFalse();
});

it('splits the configured comma-separated presets into an array', function () {
    config(['podman.presets' => 'frankenphp-octane,proxy']);

    expect($this->path->defaultPresets())->toBe(['frankenphp-octane', 'proxy']);
});

it('accepts the configured presets as a plain array', function () {
    config(['podman.presets' => ['frankenphp-octane', 'proxy']]);

    expect($this->path->defaultPresets())->toBe(['frankenphp-octane', 'proxy']);
});

it('trims whitespace and drops empty entries from the configured presets', function () {
    config(['podman.presets' => ' frankenphp-octane ,, proxy ']);

    expect($this->path->defaultPresets())->toBe(['frankenphp-octane', 'proxy']);
});

it('returns no default presets when none are configured', function () {
    config(['podman.presets' => '']);

    expect($this->path->defaultPresets())->toBe([]);
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

it('resolves the s3 cors policy path against the s3 preset path', function () {
    expect($this->path->s3CorsPolicyPath())->toBe($this->path->vendorPresetPath('s3').'/cors.json');
});
