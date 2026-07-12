<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->quadletsPath = $this->makeQuadletServicesPath(['pgsql']);
});

afterEach(function () {
    File::deleteDirectory($this->quadletsPath);
});

it('sets the secrets used by a service', function () {
    File::put(
        "{$this->quadletsPath}/pgsql.quadlets",
        "Secret=laravel-pgsql-db,type=env,target=POSTGRES_DB\n".
        "Secret=laravel-pgsql-password,type=env,target=POSTGRES_PASSWORD\n".
        "Secret=laravel-pgsql-password,type=env,target=PGPASSWORD\n",
    );

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:secret')
        ->expectsQuestion('Select a service to configure', 'pgsql')
        ->expectsQuestion('Enter the value for laravel-pgsql-db (POSTGRES_DB)', 'myapp')
        ->expectsQuestion('Enter the value for laravel-pgsql-password (POSTGRES_PASSWORD, PGPASSWORD)', 'super-secret')
        ->expectsOutputToContain('Secrets for service pgsql have been set.')
        ->assertExitCode(0);
});

it('reports when the selected service does not use any secrets', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:secret')
        ->expectsQuestion('Select a service to configure', 'pgsql')
        ->expectsOutputToContain('Service pgsql does not use any secrets.')
        ->assertExitCode(0);
});

it('reports an error when setting a secret fails', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-pgsql-db,type=env,target=POSTGRES_DB\n");

    $this->useFakePodmanBinary(1);

    $this->artisan('podman:secret')
        ->expectsQuestion('Select a service to configure', 'pgsql')
        ->expectsQuestion('Enter the value for laravel-pgsql-db (POSTGRES_DB)', 'myapp')
        ->assertExitCode(1);
});

it('accepts the replace option', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-pgsql-db,type=env,target=POSTGRES_DB\n");

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:secret', ['--replace' => true])
        ->expectsQuestion('Select a service to configure', 'pgsql')
        ->expectsQuestion('Enter the value for laravel-pgsql-db (POSTGRES_DB)', 'myapp')
        ->assertExitCode(0);
});
