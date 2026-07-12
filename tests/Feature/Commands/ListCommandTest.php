<?php

declare(strict_types=1);

it('lists the configured quadlets', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:list')->assertExitCode(0);
});

it('accepts filter, format and noheading options', function () {
    $this->useFakePodmanBinary(0);

    $this->artisan('podman:list', [
        '--filter' => 'status=running',
        '--format' => 'json',
        '--noheading' => true,
    ])->assertExitCode(0);
});

it('reports an error when the list process fails', function () {
    $this->useFakePodmanBinary(1);

    $this->artisan('podman:list')->assertExitCode(1);
});
