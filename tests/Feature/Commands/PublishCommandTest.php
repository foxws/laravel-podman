<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->stubsPath = sys_get_temp_dir().'/podman-stubs-'.uniqid();
    config(['podman.stubs_path' => $this->stubsPath]);
});

afterEach(function () {
    File::deleteDirectory($this->stubsPath);
});

it('publishes the selected preset to the stubs path, creating it if needed', function () {
    expect(File::isDirectory("{$this->stubsPath}/frankenphp-octane"))->toBeFalse();

    $this->artisan('podman:publish')
        ->expectsQuestion('Select a preset to publish', 'frankenphp-octane')
        ->expectsOutputToContain("Preset frankenphp-octane published to {$this->stubsPath}/frankenphp-octane")
        ->assertExitCode(0);

    expect(File::exists("{$this->stubsPath}/frankenphp-octane/runtimes/Containerfile"))->toBeTrue()
        ->and(File::exists("{$this->stubsPath}/frankenphp-octane/quadlets/app.quadlets"))->toBeTrue();
});

it('accepts the preset name as an argument, skipping the prompt', function () {
    $this->artisan('podman:publish', ['preset' => 'frankenphp-octane'])
        ->expectsOutputToContain("Preset frankenphp-octane published to {$this->stubsPath}/frankenphp-octane")
        ->assertExitCode(0);

    expect(File::exists("{$this->stubsPath}/frankenphp-octane/runtimes/Containerfile"))->toBeTrue();
});

it('refuses to overwrite an existing published preset without the force option', function () {
    File::ensureDirectoryExists("{$this->stubsPath}/frankenphp-octane");
    File::put("{$this->stubsPath}/frankenphp-octane/marker", 'existing');

    $this->artisan('podman:publish', ['preset' => 'frankenphp-octane'])
        ->assertExitCode(1);

    expect(File::get("{$this->stubsPath}/frankenphp-octane/marker"))->toBe('existing');
});

it('overwrites existing files when the force option is passed', function () {
    File::ensureDirectoryExists("{$this->stubsPath}/frankenphp-octane");
    File::put("{$this->stubsPath}/frankenphp-octane/marker", 'existing');

    $this->artisan('podman:publish', ['preset' => 'frankenphp-octane', '--force' => true])
        ->assertExitCode(0);

    expect(File::exists("{$this->stubsPath}/frankenphp-octane/quadlets/app.quadlets"))->toBeTrue();
});
