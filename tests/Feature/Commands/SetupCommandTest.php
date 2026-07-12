<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->quadletsPath = $this->makeQuadletsPath(['pgsql', 'valkey', 'mailpit']);
    $this->runtimesPath = base_path('runtimes');

    config(['podman.services' => [
        'pgsql' => ['default' => true, 'requires' => []],
        'valkey' => ['default' => true, 'requires' => []],
        'mailpit' => ['default' => false, 'requires' => []],
    ]]);
});

afterEach(function () {
    File::deleteDirectory($this->quadletsPath);
    File::deleteDirectory($this->runtimesPath);
});

it('publishes the runtime and installs the default services without prompting', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:setup')
        ->expectsOutputToContain("Runtime frankenphp-octane published to {$this->runtimesPath}")
        ->expectsOutputToContain('Service pgsql installed successfully.')
        ->expectsOutputToContain('Service valkey installed successfully.')
        ->expectsOutputToContain('Setup complete. Installed: pgsql, valkey')
        ->assertExitCode(0);

    expect(File::exists("{$this->runtimesPath}/frankenphp-octane/Containerfile"))->toBeTrue();
});

it('accepts the service option, overriding the default services', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:setup', ['--service' => ['mailpit']])
        ->expectsOutputToContain('Service mailpit installed successfully.')
        ->expectsOutputToContain('Setup complete. Installed: mailpit')
        ->assertExitCode(0);
});

it('accepts the runtime option, skipping the prompt', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:setup', ['--runtime' => 'frankenphp-octane'])
        ->expectsOutputToContain("Runtime frankenphp-octane published to {$this->runtimesPath}")
        ->assertExitCode(0);
});

it('reports an error and stops when no default services are configured and none are given', function () {
    config(['podman.services' => []]);
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:setup')
        ->expectsOutputToContain('No default services are configured.')
        ->assertExitCode(1);
});

it('refuses to overwrite an existing runtimes directory without the force option', function () {
    File::ensureDirectoryExists("{$this->runtimesPath}/frankenphp-octane");
    File::put("{$this->runtimesPath}/frankenphp-octane/Containerfile", 'existing');

    $this->artisan('podman:setup')
        ->assertExitCode(1);

    expect(File::get("{$this->runtimesPath}/frankenphp-octane/Containerfile"))->toBe('existing');
});

it('continues installing the remaining services when one fails, and reports a summary', function () {
    $this->useFakePodmanBinary(1);

    $this->artisan('podman:setup')
        ->expectsOutputToContain('Failed to install: pgsql, valkey')
        ->assertExitCode(1);
});
