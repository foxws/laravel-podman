<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->quadletsPath = $this->makeQuadletsPath(['pgsql', 'valkey', 'mailpit']);
    $this->runtimesPath = base_path('runtimes');

    config([
        'podman.services' => 'pgsql,valkey',
        'podman.runtimes' => 'frankenphp-octane,proxy',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->quadletsPath);
    File::deleteDirectory($this->runtimesPath);
});

it('publishes the default runtimes and installs the default services without prompting', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:setup')
        ->expectsOutputToContain("Runtime frankenphp-octane published to {$this->runtimesPath}")
        ->expectsOutputToContain("Runtime proxy published to {$this->runtimesPath}")
        ->expectsOutputToContain('Service pgsql installed successfully.')
        ->expectsOutputToContain('Service valkey installed successfully.')
        ->expectsOutputToContain('Setup complete. Installed: pgsql, valkey')
        ->assertExitCode(0);

    expect(File::exists("{$this->runtimesPath}/frankenphp-octane/Containerfile"))->toBeTrue()
        ->and(File::exists("{$this->runtimesPath}/proxy/Caddyfile"))->toBeTrue();
});

it('accepts the replace option, which is the default behavior', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:setup', ['--service' => ['mailpit'], '--replace' => true])
        ->expectsOutputToContain('Service mailpit installed successfully.')
        ->assertExitCode(0);
});

it('accepts the service option, overriding the default services', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:setup', ['--service' => ['mailpit']])
        ->expectsOutputToContain('Service mailpit installed successfully.')
        ->expectsOutputToContain('Setup complete. Installed: mailpit')
        ->assertExitCode(0);
});

it('prompts for and sets secrets by default without the --secrets flag', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-pgsql-db,type=env,target=POSTGRES_DB\n");

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:setup', ['--service' => ['pgsql']])
        ->expectsQuestion('Enter the value for laravel-pgsql-db (POSTGRES_DB)', 'myapp')
        ->expectsOutputToContain('Service pgsql installed successfully.')
        ->assertExitCode(0);
});

it('does not prompt for secrets when the no-secrets option is passed', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-pgsql-db,type=env,target=POSTGRES_DB\n");

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:setup', ['--service' => ['pgsql'], '--no-secrets' => true])
        ->expectsOutputToContain('Service pgsql installed successfully.')
        ->assertExitCode(0);
});

it('accepts the runtime option, overriding the default runtimes and skipping the prompt', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:setup', ['--runtime' => ['frankenphp-octane']])
        ->expectsOutputToContain("Runtime frankenphp-octane published to {$this->runtimesPath}")
        ->assertExitCode(0);

    expect(File::exists("{$this->runtimesPath}/proxy"))->toBeFalse();
});

it('prompts for the runtimes to publish when none are configured and none are given', function () {
    config(['podman.runtimes' => '']);
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:setup')
        ->expectsQuestion('Select the runtimes to publish', ['frankenphp-octane'])
        ->expectsOutputToContain("Runtime frankenphp-octane published to {$this->runtimesPath}")
        ->assertExitCode(0);

    expect(File::exists("{$this->runtimesPath}/proxy"))->toBeFalse();
});

it('reports an error and stops when no default services are configured and none are given', function () {
    config(['podman.services' => '']);
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

it('continues publishing the remaining runtimes when one fails, and reports a summary', function () {
    File::ensureDirectoryExists("{$this->runtimesPath}/frankenphp-octane");
    File::put("{$this->runtimesPath}/frankenphp-octane/Containerfile", 'existing');

    $this->artisan('podman:setup')
        ->expectsOutputToContain("Runtime proxy published to {$this->runtimesPath}")
        ->expectsOutputToContain('Failed to publish: frankenphp-octane')
        ->assertExitCode(1);

    expect(File::exists("{$this->runtimesPath}/proxy/Caddyfile"))->toBeTrue();
});

it('continues installing the remaining services when one fails, and reports a summary', function () {
    $this->useFakePodmanBinary(1);

    $this->artisan('podman:setup')
        ->expectsOutputToContain('Failed to install: pgsql, valkey')
        ->assertExitCode(1);
});

it('prepares services at the publish path instead of installing them when the no-install option is passed', function () {
    $publishPath = sys_get_temp_dir().'/podman-publish-'.uniqid();
    config(['podman.publish_path' => $publishPath]);

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:setup', ['--no-install' => true])
        ->expectsOutputToContain("Service pgsql prepared at {$publishPath}/pgsql.quadlets")
        ->expectsOutputToContain("Service valkey prepared at {$publishPath}/valkey.quadlets")
        ->expectsOutputToContain('Setup complete. Installed: pgsql, valkey')
        ->assertExitCode(0);

    expect(File::exists("{$publishPath}/pgsql.quadlets"))->toBeTrue()
        ->and(File::exists("{$publishPath}/valkey.quadlets"))->toBeTrue();

    File::deleteDirectory($publishPath);
});

it('prepares services at the publish path automatically when the podman binary is unavailable', function () {
    $publishPath = sys_get_temp_dir().'/podman-publish-'.uniqid();
    config(['podman.publish_path' => $publishPath]);

    $this->makePodmanBinaryUnavailable();

    $this->artisan('podman:setup')
        ->expectsOutputToContain("Service pgsql prepared at {$publishPath}/pgsql.quadlets")
        ->assertExitCode(0);

    File::deleteDirectory($publishPath);
});
