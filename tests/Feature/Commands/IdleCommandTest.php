<?php

declare(strict_types=1);

use Foxws\Podman\Support\Idle\PodmanIdle;
use Foxws\Podman\Support\Idle\QueueIdleCheck;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['podman.quadlet_prefix' => 'acme']);

    Queue::fake();
});

it('succeeds when no jobs are left', function () {
    $this->artisan('podman:idle')->assertExitCode(0);
});

it('fails while jobs are waiting on the default queue', function () {
    Queue::pushOn('default', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle')
        ->expectsOutputToContain('QueueIdleCheck: 1 jobs on sync:default')
        ->assertExitCode(1);
});

it('checks the queues of every horizon supervisor', function () {
    config(['horizon.defaults' => [
        'supervisor-1' => ['connection' => 'redis', 'queue' => ['default', 'media']],
    ]]);

    Queue::pushOn('media', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle', ['--service' => ['horizon']])
        ->expectsOutputToContain('1 jobs on redis:media')
        ->assertExitCode(1);
});

it('matches services with or without the application prefix', function (string $service) {
    Queue::pushOn('default', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle', ['--service' => [$service]])->assertExitCode(1);
})->with(['horizon', 'acme-horizon', 'acme-queue']);

it('treats a service without checks as idle', function () {
    Queue::pushOn('default', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle', ['--service' => ['acme-rustfs']])->assertExitCode(0);
});

it('runs the checks mapped in the config', function () {
    config(['podman.idle.checks' => ['typesense' => [QueueIdleCheck::class]]]);

    Queue::pushOn('default', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle', ['--service' => ['horizon']])->assertExitCode(0);
    $this->artisan('podman:idle', ['--service' => ['typesense']])->assertExitCode(1);
});

it('prefers registered checks over the config', function () {
    app(PodmanIdle::class)->checks([
        QueueIdleCheck::new()->services('horizon')->queues(['media']),
    ]);

    Queue::pushOn('default', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle', ['--service' => ['horizon']])->assertExitCode(0);

    Queue::pushOn('media', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle', ['--service' => ['horizon']])
        ->expectsOutputToContain('1 jobs on sync:media')
        ->assertExitCode(1);
});
