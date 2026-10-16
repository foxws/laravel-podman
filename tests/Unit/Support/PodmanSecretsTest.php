<?php

declare(strict_types=1);

use Foxws\Podman\Support\PodmanSecrets;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('lists the secrets that exist', function () {
    Process::fake(['*' => Process::result(output: "acme-env\nacme-pgsql-password\n")]);

    $secrets = new PodmanSecrets;

    expect($secrets->names())->toBe(['acme-env', 'acme-pgsql-password'])
        ->and($secrets->exists('acme-env'))->toBeTrue()
        ->and($secrets->exists('acme-key'))->toBeFalse();

    Process::assertRan(fn (PendingProcess $process) => $process->command === ['podman', 'secret', 'ls', '--format', '{{.Name}}']);
});

it('creates a secret with its value on stdin', function () {
    Process::fake();
    config(['podman.binary' => 'podman-remote']);

    (new PodmanSecrets)->create('acme-pgsql-password', 'correct-horse');

    Process::assertRan(fn (PendingProcess $process) => $process->command === ['podman-remote', 'secret', 'create', '--replace', 'acme-pgsql-password', '-']
        && $process->input === 'correct-horse');
});

it('creates a secret from a file', function () {
    Process::fake();
    $file = tempnam(sys_get_temp_dir(), 'env');

    (new PodmanSecrets)->createFromFile('acme-env', $file);

    Process::assertRan(fn (PendingProcess $process) => $process->command === ['podman', 'secret', 'create', '--replace', 'acme-env', $file]);

    unlink($file);
});

it('fails with what podman said', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Error: cannot connect to Podman', exitCode: 125)]);

    (new PodmanSecrets)->names();
})->throws(RuntimeException::class, 'Error: cannot connect to Podman');

it('refuses a file that does not exist', function () {
    Process::fake();

    (new PodmanSecrets)->createFromFile('acme-env', '/nope/.env');
})->throws(RuntimeException::class, 'The file /nope/.env does not exist.');
