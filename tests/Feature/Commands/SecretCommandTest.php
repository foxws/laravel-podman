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

it('reads the file at the provided path for a mount-type secret', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-env,target=/config/app.env,mode=0400\n");

    $envPath = sys_get_temp_dir().'/podman-env-'.uniqid();
    File::put($envPath, "APP_NAME=Test\n");

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:secret')
        ->expectsQuestion('Select a service to configure', 'pgsql')
        ->expectsQuestion('Enter the file path for laravel-env (/config/app.env)', $envPath)
        ->expectsOutputToContain('Secrets for service pgsql have been set.')
        ->assertExitCode(0);

    File::delete($envPath);
});

it('reports an error when the provided file path for a mount-type secret does not exist', function () {
    File::put("{$this->quadletsPath}/pgsql.quadlets", "Secret=laravel-env,target=/config/app.env,mode=0400\n");

    $this->useFakePodmanBinary(0);

    $this->artisan('podman:secret')
        ->expectsQuestion('Select a service to configure', 'pgsql')
        ->expectsQuestion('Enter the file path for laravel-env (/config/app.env)', '/nonexistent/.env')
        ->assertExitCode(1);
});
