<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->quadletsPath = $this->makeQuadletServicesPath(['pgsql']);
});

afterEach(function () {
    File::deleteDirectory($this->quadletsPath);
});

it('installs a service for an application', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install')
        ->expectsQuestion('Enter the application name', 'my-app')
        ->expectsQuestion('Select a service to install', 'pgsql')
        ->expectsOutputToContain('Service pgsql installed for application my-app')
        ->assertExitCode(0);
});

it('reports an error when the install process fails', function () {
    $this->useFakePodmanBinary(1);

    $this->artisan('podman:install')
        ->expectsQuestion('Enter the application name', 'my-app')
        ->expectsQuestion('Select a service to install', 'pgsql')
        ->assertExitCode(1);
});

it('accepts the replace option', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install', ['--replace' => true])
        ->expectsQuestion('Enter the application name', 'my-app')
        ->expectsQuestion('Select a service to install', 'pgsql')
        ->assertExitCode(0);
});

it('prompts for and sets secrets before installing when the secrets option is passed', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-pgsql-db,type=env,target=POSTGRES_DB\n");

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install', ['--secrets' => true])
        ->expectsQuestion('Enter the application name', 'my-app')
        ->expectsQuestion('Select a service to install', 'pgsql')
        ->expectsQuestion('Enter the value for laravel-pgsql-db (POSTGRES_DB)', 'myapp')
        ->expectsOutputToContain('Service pgsql installed for application my-app')
        ->assertExitCode(0);
});

it('does not prompt for secrets when the secrets option is not passed', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-pgsql-db,type=env,target=POSTGRES_DB\n");

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:install')
        ->expectsQuestion('Enter the application name', 'my-app')
        ->expectsQuestion('Select a service to install', 'pgsql')
        ->assertExitCode(0);
});

it('reports an error when setting a secret fails before installing', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-pgsql-db,type=env,target=POSTGRES_DB\n");

    $this->useFakePodmanBinary(1);

    $this->artisan('podman:install', ['--secrets' => true])
        ->expectsQuestion('Enter the application name', 'my-app')
        ->expectsQuestion('Select a service to install', 'pgsql')
        ->expectsQuestion('Enter the value for laravel-pgsql-db (POSTGRES_DB)', 'myapp')
        ->assertExitCode(1);
});
