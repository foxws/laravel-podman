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

it('resolves the config path to the working path by default', function () {
    expect($this->path->configPath())->toBe($this->path->workingPath());
});

it('uses the configured config path when set, without affecting the working path', function () {
    config(['podman.config_path' => '/etc/laravel-podman', 'podman.working_path' => '/home/francois/app']);

    expect($this->path->configPath())->toBe('/etc/laravel-podman')
        ->and($this->path->workingPath())->toBe('/home/francois/app');
});

it('resolves the vendor preset path for a preset that has not been published', function () {
    expect($this->path->presetPath('production'))->toBe($this->path->vendorPresetPath('production'))
        ->and($this->path->vendorPresetPath('production'))->toBe("{$this->path->vendorPath()}/stubs/production");
});

it('uses the published preset path when it exists', function () {
    $preset = $this->makePresetPath('production', ['app']);

    expect($this->path->presetPath('production'))->toBe($preset)
        ->and($this->path->publishedPresetPath('production'))->toBe($preset);

    File::deleteDirectory(dirname($preset));
});

it('resolves presetQuadletsPath and presetRuntimesPath relative to the preset path', function () {
    expect($this->path->presetQuadletsPath('production'))->toBe($this->path->vendorPresetPath('production').'/quadlets')
        ->and($this->path->presetRuntimesPath('production'))->toBe($this->path->vendorPresetPath('production').'/runtimes');
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

    expect($this->path->presetPublishPath('production'))->toBe(base_path('podman/production'))
        ->and($this->path->presetPublishRuntimesPath('production'))->toBe(base_path('podman/production/runtimes'));
});

it('resolves the working preset runtime path against the working path when set', function () {
    config(['podman.publish_path' => 'podman', 'podman.working_path' => '/home/francois/app']);

    expect($this->path->workingPresetRuntimePath('production'))->toBe('/home/francois/app/podman/production/runtimes');
});

it('resolves the working preset runtime path against the base path by default', function () {
    config(['podman.publish_path' => 'podman']);

    expect($this->path->workingPresetRuntimePath('production'))->toBe(base_path('podman/production/runtimes'));
});

it('resolves the s3 cors policy path against the s3 preset path', function () {
    expect($this->path->s3CorsPolicyPath())->toBe($this->path->vendorPresetPath('s3').'/cors.json');
});
