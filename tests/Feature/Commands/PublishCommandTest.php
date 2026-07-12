<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->containerPath = sys_get_temp_dir().'/podman-publish-'.uniqid();
    File::ensureDirectoryExists($this->containerPath);
    config(['podman.quadlet_container_path' => $this->containerPath]);
});

afterEach(function () {
    File::deleteDirectory($this->containerPath);
});

it('publishes the selected runtime to the container path', function () {
    $this->artisan('podman:publish')
        ->expectsQuestion('Select a runtime to publish', 'frankenphp-octane')
        ->expectsOutputToContain("Runtime frankenphp-octane published to {$this->containerPath}")
        ->assertExitCode(0);

    expect(File::exists("{$this->containerPath}/Containerfile"))->toBeTrue()
        ->and(File::exists("{$this->containerPath}/runtimes/entrypoint.sh"))->toBeTrue()
        ->and(File::exists("{$this->containerPath}/runtimes/php-production.ini"))->toBeTrue()
        ->and(File::exists("{$this->containerPath}/runtimes/php-development.ini"))->toBeTrue()
        ->and(File::exists("{$this->containerPath}/runtimes/Containerfile"))->toBeFalse();
});

it('accepts the runtime name as an argument, skipping the prompt', function () {
    $this->artisan('podman:publish', ['runtime' => 'frankenphp-octane'])
        ->expectsOutputToContain("Runtime frankenphp-octane published to {$this->containerPath}")
        ->assertExitCode(0);

    expect(File::exists("{$this->containerPath}/Containerfile"))->toBeTrue();
});

it('refuses to overwrite an existing Containerfile without the force option', function () {
    File::put("{$this->containerPath}/Containerfile", 'existing');

    $this->artisan('podman:publish')
        ->expectsQuestion('Select a runtime to publish', 'frankenphp-octane')
        ->assertExitCode(1);

    expect(File::get("{$this->containerPath}/Containerfile"))->toBe('existing');
});

it('overwrites existing files when the force option is passed', function () {
    File::put("{$this->containerPath}/Containerfile", 'existing');

    $this->artisan('podman:publish', ['--force' => true])
        ->expectsQuestion('Select a runtime to publish', 'frankenphp-octane')
        ->assertExitCode(0);

    expect(File::get("{$this->containerPath}/Containerfile"))->not->toBe('existing');
});
