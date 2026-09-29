<?php

declare(strict_types=1);

use Foxws\Podman\Support\Idle\Checks\QueueCheck;
use Foxws\Podman\Support\Idle\PodmanIdle;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['queue.default' => 'redis']);

    Queue::fake();
});

it('succeeds when no work is left', function () {
    $this->artisan('podman:idle')->assertExitCode(0);
});

it('fails while jobs are waiting', function () {
    Queue::pushOn('default', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle')
        ->expectsOutputToContain('queue: 1 jobs on redis:default')
        ->assertExitCode(1);
});

it('checks the queues of every horizon supervisor', function () {
    config(['horizon.defaults' => [
        'supervisor-1' => ['connection' => 'redis', 'queue' => ['default', 'media']],
    ]]);

    Queue::pushOn('media', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle')
        ->expectsOutputToContain('queue: 1 jobs on redis:media')
        ->assertExitCode(1);
});

it('skips checks the app does not use', function () {
    config(['queue.default' => 'sync']);

    Queue::pushOn('default', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle')->assertExitCode(0);
});

it('only runs the given checks', function () {
    Queue::pushOn('default', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle', ['--services' => 'database'])->assertExitCode(0);
    $this->artisan('podman:idle', ['--services' => 'database, queue'])->assertExitCode(1);
});

it('fails on unknown checks', function () {
    $this->artisan('podman:idle', ['--services' => 'queue,session'])
        ->expectsOutputToContain('Unknown idle checks: session.')
        ->assertExitCode(1);
});

it('waits for queued scout indexing', function () {
    config(['scout.driver' => 'typesense', 'scout.queue' => ['connection' => 'redis', 'queue' => 'scout']]);

    Queue::pushOn('scout', 'Laravel\\Scout\\Jobs\\MakeSearchable');

    $this->artisan('podman:idle', ['--services' => 'scout'])
        ->expectsOutputToContain('scout: 1 jobs on redis:scout')
        ->assertExitCode(1);
});

it('prefers registered checks over the config', function () {
    app(PodmanIdle::class)->checks([
        QueueCheck::new()->queues(['media']),
    ]);

    Queue::pushOn('default', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle')->assertExitCode(0);

    $this->artisan('podman:idle', ['--services' => 'database'])
        ->expectsOutputToContain('Unknown idle checks: database.')
        ->assertExitCode(1);
});
