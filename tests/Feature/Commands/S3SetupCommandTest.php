<?php

declare(strict_types=1);

use Aws\Command;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Foxws\Podman\Support\PodmanS3Manager;
use Illuminate\Support\Facades\File;

function bindPodmanS3Manager(MockHandler $handler): void
{
    $client = new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1',
        'credentials' => ['key' => 'test', 'secret' => 'test'],
        'handler' => $handler,
    ]);

    app()->instance(PodmanS3Manager::class, new PodmanS3Manager($client));
}

beforeEach(function () {
    $this->stubsPath = sys_get_temp_dir().'/podman-s3-stubs-'.uniqid();

    File::ensureDirectoryExists("{$this->stubsPath}/s3");
    File::put("{$this->stubsPath}/s3/cors.json", json_encode([
        'CORSRules' => [
            ['AllowedOrigins' => ['*'], 'AllowedMethods' => ['GET', 'HEAD']],
        ],
    ]));

    config([
        'podman.stubs_path' => $this->stubsPath,
        'podman.s3_buckets' => ['local', 'conversions'],
        'podman.s3_cors_buckets' => ['conversions'],
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->stubsPath);
});

it('creates the configured buckets and applies the CORS policy', function () {
    bindPodmanS3Manager(new MockHandler([
        new Result([]),
        new Result([]),
        new Result([]),
    ]));

    $this->artisan('podman:s3-setup')
        ->expectsOutputToContain('Creating buckets...')
        ->expectsOutputToContain('  -> local')
        ->expectsOutputToContain('  -> conversions')
        ->expectsOutputToContain('Applying CORS policy...')
        ->expectsOutputToContain('Done.')
        ->assertExitCode(0);
});

it('fails when no buckets are configured', function () {
    config(['podman.s3_buckets' => []]);

    $this->artisan('podman:s3-setup')
        ->expectsOutputToContain('No S3 buckets are configured.')
        ->assertExitCode(1);
});

it('reports the buckets that failed to be created', function () {
    bindPodmanS3Manager(new MockHandler([
        new Result([]),
        new S3Exception('denied', new Command('CreateBucket'), ['code' => 'AccessDenied']),
    ]));

    $this->artisan('podman:s3-setup')
        ->expectsOutputToContain('Failed to create: conversions')
        ->assertExitCode(1);
});

it('fails when the CORS policy file is missing', function () {
    File::delete("{$this->stubsPath}/s3/cors.json");

    bindPodmanS3Manager(new MockHandler([
        new Result([]),
        new Result([]),
    ]));

    $this->artisan('podman:s3-setup')
        ->expectsOutputToContain('CORS policy file not found')
        ->assertExitCode(1);
});

it('reports the buckets that failed to receive the CORS policy', function () {
    bindPodmanS3Manager(new MockHandler([
        new Result([]),
        new Result([]),
        new S3Exception('denied', new Command('PutBucketCors'), ['code' => 'AccessDenied']),
    ]));

    $this->artisan('podman:s3-setup')
        ->expectsOutputToContain('Failed to apply the CORS policy to: conversions')
        ->assertExitCode(1);
});

it('skips the CORS step when no CORS buckets are configured', function () {
    config(['podman.s3_cors_buckets' => []]);

    bindPodmanS3Manager(new MockHandler([
        new Result([]),
        new Result([]),
    ]));

    $this->artisan('podman:s3-setup')
        ->doesntExpectOutputToContain('Applying CORS policy...')
        ->expectsOutputToContain('Done.')
        ->assertExitCode(0);
});
