<?php

declare(strict_types=1);

it('uninstalls the given application', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:uninstall')
        ->expectsQuestion('Enter the application name to remove', 'my-app')
        ->expectsOutputToContain("Application 'my-app' has been uninstalled successfully.")
        ->assertExitCode(0);
});

it('accepts the force option', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:uninstall', ['--force' => true])
        ->expectsQuestion('Enter the application name to remove', 'my-app')
        ->assertExitCode(0);
});

it('reports an error when the uninstall process fails', function () {
    $this->useFakePodmanBinary(1);

    $this->artisan('podman:uninstall')
        ->expectsQuestion('Enter the application name to remove', 'my-app')
        ->assertExitCode(1);
});
