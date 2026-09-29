<?php

declare(strict_types=1);

use Foxws\Podman\Support\Idle\Checks\QueueCheck;
use Foxws\Podman\Support\Idle\PodmanIdle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

it('waits for clients connected to reverb', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'app-key',
            'secret' => 'app-secret',
            'app_id' => 'app-id',
            'options' => ['host' => 'systemd-acme-reverb', 'port' => 6001, 'scheme' => 'http'],
        ],
    ]);

    Http::fake(['systemd-acme-reverb:6001/apps/app-id/connections*' => Http::response(['connections' => 2])]);

    $this->artisan('podman:idle', ['--services' => 'broadcast'])
        ->expectsOutputToContain('broadcast: 2 clients connected')
        ->assertExitCode(1);

    Http::assertSent(fn (Request $request): bool => $request['auth_key'] === 'app-key'
        && hash_equals(
            hash_hmac('sha256', "GET\n/apps/app-id/connections\nauth_key=app-key&auth_timestamp={$request['auth_timestamp']}&auth_version=1.0", 'app-secret'),
            $request['auth_signature'],
        ));
});

it('treats a sleeping reverb server as idle', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => ['driver' => 'reverb', 'key' => 'k', 'secret' => 's', 'app_id' => 'a'],
    ]);

    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->artisan('podman:idle', ['--services' => 'broadcast'])->assertExitCode(0);
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
