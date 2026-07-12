<?php

declare(strict_types=1);

it('prompts for the application name when no argument is given', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:uninstall')
        ->expectsQuestion('Enter the application name to remove', 'my-app')
        ->expectsOutputToContain("Application 'my-app' has been uninstalled successfully.")
        ->assertExitCode(0);
});

it('accepts the application name as an argument, skipping the prompt', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:uninstall', ['application' => 'my-app'])
        ->expectsOutputToContain("Application 'my-app' has been uninstalled successfully.")
        ->assertExitCode(0);
});

it('accepts the force option', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:uninstall', ['application' => 'my-app', '--force' => true])
        ->assertExitCode(0);
});

it('reports an error when the uninstall process fails', function () {
    $this->useFakePodmanBinary(1);

    $this->artisan('podman:uninstall', ['application' => 'my-app'])
        ->assertExitCode(1);
});
