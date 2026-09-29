<?php

declare(strict_types=1);

use Foxws\Podman\Support\Idle\Checks\QueueCheck;
use Foxws\Podman\Support\Idle\PodmanIdle;
use Illuminate\Database\Connection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
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

it('waits for queued scout indexing', function () {
    config(['scout.driver' => 'typesense', 'scout.queue' => ['connection' => 'redis', 'queue' => 'scout']]);

    Queue::pushOn('scout', 'Laravel\\Scout\\Jobs\\MakeSearchable');

    $this->artisan('podman:idle', ['--services' => 'scout'])
        ->expectsOutputToContain('scout: 1 jobs on redis:scout')
        ->assertExitCode(1);
});

it('waits for running mongodb operations on the app database', function () {
    $cursor = Mockery::mock();
    $cursor->shouldReceive('toArray')->andReturn([['inprog' => [['op' => 'query'], ['op' => 'update']]]]);

    $admin = Mockery::mock();
    $admin->shouldReceive('command')
        ->withArgs(fn (array $command): bool => $command['active'] === true && $command['ns'] === ['$regex' => '^laravel\\.'])
        ->andReturn($cursor);

    $client = Mockery::mock();
    $client->shouldReceive('selectDatabase')->with('admin')->andReturn($admin);

    $connection = new class(fn () => null, 'laravel', '', ['name' => 'mongodb', 'driver' => 'mongodb']) extends Connection
    {
        public mixed $client = null;

        public function getClient(): mixed
        {
            return $this->client;
        }
    };

    $connection->client = $client;

    DB::extend('mongodb', fn () => $connection);

    config([
        'database.default' => 'mongodb',
        'database.connections.mongodb' => ['driver' => 'mongodb', 'database' => 'laravel'],
    ]);

    $this->artisan('podman:idle', ['--services' => 'database'])
        ->expectsOutputToContain('database: 2 active queries on mongodb')
        ->assertExitCode(1);
});

it('waits for meilisearch to process its tasks', function () {
    config([
        'scout.driver' => 'meilisearch',
        'scout.meilisearch' => ['host' => 'http://systemd-acme-meilisearch:7700', 'key' => 'masterKey'],
    ]);

    Http::fake(['systemd-acme-meilisearch:7700/tasks*' => Http::response(['results' => [], 'total' => 3])]);

    $this->artisan('podman:idle', ['--services' => 'scout'])
        ->expectsOutputToContain('scout: 3 Meilisearch tasks enqueued or processing')
        ->assertExitCode(1);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer masterKey')
        && $request['statuses'] === 'enqueued,processing');
});

it('treats a sleeping meilisearch server as idle', function () {
    config(['scout.driver' => 'meilisearch']);

    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->artisan('podman:idle', ['--services' => 'scout'])->assertExitCode(0);
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
