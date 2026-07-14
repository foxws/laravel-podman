<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->quadletsPath = $this->makeQuadletsPath(['pgsql', 'valkey']);
});

afterEach(function () {
    File::deleteDirectory($this->quadletsPath);
});

it('prompts for the services when no argument is given', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install')
        ->expectsQuestion('Select the services to install', ['pgsql'])
        ->expectsOutputToContain('Service pgsql installed successfully.')
        ->assertExitCode(0);
});

it('accepts the service as an argument, skipping the prompt', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install', ['service' => 'pgsql'])
        ->expectsOutputToContain('Service pgsql installed successfully.')
        ->assertExitCode(0);
});

it('accepts multiple services as arguments, installing each of them', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install', ['service' => ['pgsql', 'valkey']])
        ->expectsOutputToContain('Service pgsql installed successfully.')
        ->expectsOutputToContain('Service valkey installed successfully.')
        ->assertExitCode(0);
});

it('accepts multiple services selected from the prompt, installing each of them', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install')
        ->expectsQuestion('Select the services to install', ['pgsql', 'valkey'])
        ->expectsOutputToContain('Service pgsql installed successfully.')
        ->expectsOutputToContain('Service valkey installed successfully.')
        ->assertExitCode(0);
});

it('reports an error when the install process fails', function () {
    $this->useFakePodmanBinary(1);

    $this->artisan('podman:install')
        ->expectsQuestion('Select the services to install', ['pgsql'])
        ->assertExitCode(1);
});

it('continues installing the remaining services when one fails, and reports a summary', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-pgsql-db,target=/config/missing.env,mode=0400\n");

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install', ['service' => ['pgsql', 'valkey'], '--secrets' => true])
        ->expectsQuestion('Enter the file path for laravel-pgsql-db (/config/missing.env)', '/nonexistent/.env')
        ->expectsOutputToContain('Service valkey installed successfully.')
        ->expectsOutputToContain('Failed to install: pgsql')
        ->assertExitCode(1);
});

it('accepts the replace option', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install', ['--replace' => true])
        ->expectsQuestion('Select the services to install', ['pgsql'])
        ->assertExitCode(0);
});

it('accepts the application option', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install', ['service' => 'pgsql', '--application' => 'my-app'])
        ->expectsOutputToContain('Service pgsql installed successfully.')
        ->assertExitCode(0);
});

it('prompts for and sets secrets before installing when the secrets option is passed', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-pgsql-db,type=env,target=POSTGRES_DB\n");

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install', ['service' => 'pgsql', '--secrets' => true])
        ->expectsQuestion('Enter the value for laravel-pgsql-db (POSTGRES_DB)', 'myapp')
        ->expectsOutputToContain('Service pgsql installed successfully.')
        ->assertExitCode(0);
});

it('does not prompt for secrets when the secrets option is not passed', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-pgsql-db,type=env,target=POSTGRES_DB\n");

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install', ['service' => 'pgsql'])
        ->assertExitCode(0);
});

it('reports an error when setting a secret fails before installing', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-pgsql-db,type=env,target=POSTGRES_DB\n");

    $this->useFakePodmanBinary(1);

    $this->artisan('podman:install', ['service' => 'pgsql', '--secrets' => true])
        ->expectsQuestion('Enter the value for laravel-pgsql-db (POSTGRES_DB)', 'myapp')
        ->assertExitCode(1);
});

it('publishes services to storage instead of installing them when the publish option is passed', function () {
    $publishPath = sys_get_temp_dir().'/podman-publish-'.uniqid();
    config(['podman.publish_path' => $publishPath]);

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install', ['service' => 'pgsql', '--publish' => true])
        ->expectsOutputToContain("Service pgsql prepared at {$publishPath}/pgsql.quadlets")
        ->assertExitCode(0);

    expect(File::exists("{$publishPath}/pgsql.quadlets"))->toBeTrue();

    File::deleteDirectory($publishPath);
});

it('publishes services to storage and skips secrets when the podman binary is unavailable', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-pgsql-db,type=env,target=POSTGRES_DB\n");
    $publishPath = sys_get_temp_dir().'/podman-publish-'.uniqid();
    config(['podman.publish_path' => $publishPath]);

    $this->makePodmanBinaryUnavailable();

    $this->artisan('podman:install', ['service' => 'pgsql', '--secrets' => true])
        ->doesntExpectOutputToContain('Enter the value for laravel-pgsql-db')
        ->expectsOutputToContain("Service pgsql prepared at {$publishPath}/pgsql.quadlets")
        ->assertExitCode(0);

    File::deleteDirectory($publishPath);
});
