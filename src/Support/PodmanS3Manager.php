<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Config;

class PodmanS3Manager
{
    public function __construct(
        protected S3Client $client,
    ) {}

    /**
     * Build a client from the credentials already configured on the
     * application's "s3" filesystem disk, so setup uses the same
     * endpoint and keys the app itself connects with.
     */
    public static function fromConfig(): self
    {
        $disk = Config::get('filesystems.disks.s3', []);

        return new self(new S3Client([
            'version' => 'latest',
            'region' => $disk['region'] ?? 'us-east-1',
            'endpoint' => $disk['endpoint'] ?? null,
            'use_path_style_endpoint' => (bool) ($disk['use_path_style_endpoint'] ?? false),
            'credentials' => [
                'key' => (string) ($disk['key'] ?? ''),
                'secret' => (string) ($disk['secret'] ?? ''),
            ],
        ]));
    }

    public function createBucket(string $bucket): bool
    {
        try {
            $this->client->createBucket(['Bucket' => $bucket]);

            return true;
        } catch (S3Exception $e) {
            return in_array($e->getAwsErrorCode(), ['BucketAlreadyOwnedByYou', 'BucketAlreadyExists'], true);
        }
    }

    /**
     * Create the given buckets. Returns the names of the buckets that failed.
     *
     * @param  array<int, string>  $buckets
     * @return array<int, string>
     */
    public function createBuckets(array $buckets): array
    {
        return array_values(array_filter(
            $buckets,
            fn (string $bucket): bool => ! $this->createBucket($bucket),
        ));
    }

    /**
     * @param  array{CORSRules: array<int, array<string, mixed>>}  $policy
     */
    public function applyCors(string $bucket, array $policy): bool
    {
        try {
            $this->client->putBucketCors([
                'Bucket' => $bucket,
                'CORSConfiguration' => $policy,
            ]);

            return true;
        } catch (S3Exception) {
            return false;
        }
    }

    /**
     * Apply the CORS policy to the given buckets. Returns the names of the
     * buckets that failed.
     *
     * @param  array<int, string>  $buckets
     * @param  array{CORSRules: array<int, array<string, mixed>>}  $policy
     * @return array<int, string>
     */
    public function applyCorsToBuckets(array $buckets, array $policy): array
    {
        return array_values(array_filter(
            $buckets,
            fn (string $bucket): bool => ! $this->applyCors($bucket, $policy),
        ));
    }
}
