<?php

declare(strict_types=1);

it('prompts for the service name when no argument is given', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:print')
        ->expectsQuestion('Enter the name of the service to print', 'pgsql')
        ->assertExitCode(0);
});

it('accepts the service name as an argument, skipping the prompt', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:print', ['service' => 'pgsql'])
        ->assertExitCode(0);
});

it('reports an error when the print process fails', function () {
    $this->useFakePodmanBinary(1);

    $this->artisan('podman:print')
        ->expectsQuestion('Enter the name of the service to print', 'pgsql')
        ->assertExitCode(1);
});
