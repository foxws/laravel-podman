<?php

declare(strict_types=1);

use Aws\Command;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Foxws\Podman\Support\PodmanS3Manager;

function makeMockedS3Client(MockHandler $handler): S3Client
{
    return new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1',
        'credentials' => ['key' => 'test', 'secret' => 'test'],
        'handler' => $handler,
    ]);
}

it('creates a bucket', function () {
    $handler = new MockHandler([new Result([])]);
    $manager = new PodmanS3Manager(makeMockedS3Client($handler));

    expect($manager->createBucket('local'))->toBeTrue()
        ->and($handler)->toHaveCount(0);
});

it('treats an already-owned bucket as success', function () {
    $handler = new MockHandler([
        new S3Exception('exists', new Command('CreateBucket'), ['code' => 'BucketAlreadyOwnedByYou']),
    ]);
    $manager = new PodmanS3Manager(makeMockedS3Client($handler));

    expect($manager->createBucket('local'))->toBeTrue();
});

it('reports an unrelated bucket creation failure', function () {
    $handler = new MockHandler([
        new S3Exception('denied', new Command('CreateBucket'), ['code' => 'AccessDenied']),
    ]);
    $manager = new PodmanS3Manager(makeMockedS3Client($handler));

    expect($manager->createBucket('local'))->toBeFalse();
});

it('returns the buckets that failed to be created', function () {
    $handler = new MockHandler([
        new Result([]),
        new S3Exception('denied', new Command('CreateBucket'), ['code' => 'AccessDenied']),
        new Result([]),
    ]);
    $manager = new PodmanS3Manager(makeMockedS3Client($handler));

    expect($manager->createBuckets(['local', 'conversions', 'secrets']))->toBe(['conversions']);
});

it('applies a CORS policy to a bucket', function () {
    $handler = new MockHandler([new Result([])]);
    $manager = new PodmanS3Manager(makeMockedS3Client($handler));

    $policy = ['CORSRules' => [['AllowedOrigins' => ['*'], 'AllowedMethods' => ['GET']]]];

    expect($manager->applyCors('conversions', $policy))->toBeTrue();
});

it('returns the buckets that failed to receive the CORS policy', function () {
    $handler = new MockHandler([
        new Result([]),
        new S3Exception('denied', new Command('PutBucketCors'), ['code' => 'AccessDenied']),
    ]);
    $manager = new PodmanS3Manager(makeMockedS3Client($handler));

    $policy = ['CORSRules' => [['AllowedOrigins' => ['*'], 'AllowedMethods' => ['GET']]]];

    expect($manager->applyCorsToBuckets(['conversions', 'secrets'], $policy))->toBe(['secrets']);
});
