<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('succeeds when no jobs are left', function () {
    $this->artisan('podman:idle')->assertExitCode(0);
});

it('fails while jobs are waiting on the default queue', function () {
    Queue::pushOn('default', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle')
        ->expectsOutputToContain('Jobs left on sync:default')
        ->assertExitCode(1);
});

it('checks the queues of every horizon supervisor', function () {
    config(['horizon.defaults' => [
        'supervisor-1' => ['connection' => 'redis', 'queue' => ['default', 'media']],
    ]]);

    Queue::pushOn('media', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle')
        ->expectsOutputToContain('Jobs left on redis:media')
        ->assertExitCode(1);
});

it('only checks the given queues', function () {
    Queue::pushOn('media', 'App\\Jobs\\ProcessVideo');

    $this->artisan('podman:idle', ['--queue' => ['default']])->assertExitCode(0);

    $this->artisan('podman:idle', ['--queue' => ['default,media']])->assertExitCode(1);
});
