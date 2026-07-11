<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->quadletsPath = $this->makeQuadletServicesPath(['pgsql']);
});

afterEach(function () {
    File::deleteDirectory($this->quadletsPath);
});

it('prints the selected service', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:print')
        ->expectsQuestion('Select a service to print', 'pgsql')
        ->assertExitCode(0);
});

it('reports an error when the print process fails', function () {
    $this->useFakePodmanBinary(1);

    $this->artisan('podman:print')
        ->expectsQuestion('Select a service to print', 'pgsql')
        ->assertExitCode(1);
});
