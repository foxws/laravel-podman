<?php

declare(strict_types=1);

it('removes the given service', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:remove')
        ->expectsQuestion('Enter the name of the service to remove', 'pgsql')
        ->expectsOutputToContain('Service pgsql removed successfully.')
        ->assertExitCode(0);
});

it('accepts the force and ignore options', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:remove', ['--force' => true, '--ignore' => true])
        ->expectsQuestion('Enter the name of the service to remove', 'pgsql')
        ->assertExitCode(0);
});

it('reports an error when the remove process fails', function () {
    $this->useFakePodmanBinary(1);

    $this->artisan('podman:remove')
        ->expectsQuestion('Enter the name of the service to remove', 'pgsql')
        ->assertExitCode(1);
});
