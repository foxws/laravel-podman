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
