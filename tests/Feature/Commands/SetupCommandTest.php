<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->publishPath = sys_get_temp_dir().'/podman-publish-'.uniqid();
    config(['podman.publish_path' => $this->publishPath]);
});

afterEach(function () {
    File::deleteDirectory($this->publishPath);
});

it('generates the default presets', function () {
    config(['podman.presets' => 'frankenphp-octane,proxy']);

    $this->artisan('podman:setup')
        ->expectsOutputToContain("Preset frankenphp-octane generated to {$this->publishPath}/frankenphp-octane")
        ->expectsOutputToContain("Preset proxy generated to {$this->publishPath}/proxy")
        ->expectsOutputToContain('Setup complete. Generated: frankenphp-octane, proxy')
        ->assertExitCode(0);

    expect(File::exists("{$this->publishPath}/frankenphp-octane/app.quadlets"))->toBeTrue()
        ->and(File::exists("{$this->publishPath}/proxy/proxy.quadlets"))->toBeTrue();
});

it('accepts the preset option, overriding the default presets', function () {
    config(['podman.presets' => 'frankenphp-octane,proxy']);

    $this->artisan('podman:setup', ['--preset' => ['proxy']])
        ->expectsOutputToContain('Setup complete. Generated: proxy')
        ->assertExitCode(0);

    expect(File::exists("{$this->publishPath}/frankenphp-octane"))->toBeFalse()
        ->and(File::exists("{$this->publishPath}/proxy/proxy.quadlets"))->toBeTrue();
});

it('reports an error and stops when no default presets are configured and none are given', function () {
    config(['podman.presets' => '']);

    $this->artisan('podman:setup')
        ->expectsOutputToContain('No default presets are configured.')
        ->assertExitCode(1);
});
