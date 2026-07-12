<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->runtimesPath = base_path('runtimes');
});

afterEach(function () {
    File::deleteDirectory($this->runtimesPath);
});

it('publishes the selected runtime to the runtimes directory, creating it if needed', function () {
    expect(File::isDirectory($this->runtimesPath))->toBeFalse();

    $this->artisan('podman:publish')
        ->expectsQuestion('Select a runtime to publish', 'frankenphp-octane')
        ->expectsOutputToContain('Runtime frankenphp-octane published to runtimes')
        ->assertExitCode(0);

    expect(File::exists("{$this->runtimesPath}/Containerfile"))->toBeTrue()
        ->and(File::exists("{$this->runtimesPath}/entrypoint.sh"))->toBeTrue()
        ->and(File::exists("{$this->runtimesPath}/start-container.sh"))->toBeTrue()
        ->and(File::exists("{$this->runtimesPath}/php-production.ini"))->toBeTrue()
        ->and(File::exists("{$this->runtimesPath}/php-development.ini"))->toBeTrue();
});

it('accepts the runtime name as an argument, skipping the prompt', function () {
    $this->artisan('podman:publish', ['runtime' => 'frankenphp-octane'])
        ->expectsOutputToContain('Runtime frankenphp-octane published to runtimes')
        ->assertExitCode(0);

    expect(File::exists("{$this->runtimesPath}/Containerfile"))->toBeTrue();
});

it('refuses to overwrite an existing runtimes directory without the force option', function () {
    File::ensureDirectoryExists($this->runtimesPath);
    File::put("{$this->runtimesPath}/Containerfile", 'existing');

    $this->artisan('podman:publish')
        ->expectsQuestion('Select a runtime to publish', 'frankenphp-octane')
        ->assertExitCode(1);

    expect(File::get("{$this->runtimesPath}/Containerfile"))->toBe('existing');
});

it('overwrites existing files when the force option is passed', function () {
    File::ensureDirectoryExists($this->runtimesPath);
    File::put("{$this->runtimesPath}/Containerfile", 'existing');

    $this->artisan('podman:publish', ['--force' => true])
        ->expectsQuestion('Select a runtime to publish', 'frankenphp-octane')
        ->assertExitCode(0);

    expect(File::get("{$this->runtimesPath}/Containerfile"))->not->toBe('existing');
});
