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

it('generates the selected preset to the publish path', function () {
    $this->artisan('podman:generate')
        ->expectsQuestion('Select a preset to generate', 'frankenphp-octane')
        ->expectsOutputToContain("Preset frankenphp-octane generated to {$this->publishPath}/frankenphp-octane")
        ->assertExitCode(0);

    expect(File::exists("{$this->publishPath}/frankenphp-octane/app.quadlets"))->toBeTrue()
        ->and(File::exists("{$this->publishPath}/frankenphp-octane/runtimes/Containerfile"))->toBeTrue();
});

it('accepts the preset name as an argument, skipping the prompt', function () {
    $this->artisan('podman:generate', ['preset' => 'proxy'])
        ->expectsOutputToContain("Preset proxy generated to {$this->publishPath}/proxy")
        ->assertExitCode(0);

    expect(File::exists("{$this->publishPath}/proxy/proxy.quadlets"))->toBeTrue()
        ->and(File::exists("{$this->publishPath}/proxy/runtimes/Caddyfile"))->toBeTrue();
});

it('substitutes placeholders in the generated quadlets file', function () {
    config(['podman.quadlet_prefix' => 'acme']);

    $this->artisan('podman:generate', ['preset' => 'frankenphp-octane'])
        ->assertExitCode(0);

    expect(File::get("{$this->publishPath}/frankenphp-octane/app.quadlets"))->toContain('localhost/acme:latest');
});
