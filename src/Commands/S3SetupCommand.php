<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Aws\S3\S3Client;
use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Foxws\Podman\Support\PodmanS3Manager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

#[AsCommand(name: 'podman:s3-setup')]
class S3SetupCommand extends Command
{
    use InteractsWithPodmanQuadlet;

    public $signature = 'podman:s3-setup';

    public $description = 'Create the configured S3 buckets and apply the CORS policy.';

    public function handle(): int
    {
        if (! class_exists(S3Client::class)) {
            error('The AWS SDK is required to use this command. Install it with "composer require aws/aws-sdk-php".');

            return self::FAILURE;
        }

        $path = $this->podmanQuadletPath();

        $buckets = $path->s3Buckets();

        if ($buckets === []) {
            error('No S3 buckets are configured. Set podman.s3_buckets in the config file.');

            return self::FAILURE;
        }

        $manager = app(PodmanS3Manager::class);

        info('Creating buckets...');

        $failedBuckets = $manager->createBuckets($buckets);

        foreach (array_diff($buckets, $failedBuckets) as $bucket) {
            info("  -> {$bucket}");
        }

        if ($failedBuckets !== []) {
            error('Failed to create: '.implode(', ', $failedBuckets));

            return self::FAILURE;
        }

        $corsBuckets = $path->s3CorsBuckets();

        if ($corsBuckets === []) {
            info('Done.');

            return self::SUCCESS;
        }

        $corsPolicyPath = $path->s3CorsPolicyPath();

        if (! File::exists($corsPolicyPath)) {
            error("CORS policy file not found at {$corsPolicyPath}. Run \"podman:publish s3\" first.");

            return self::FAILURE;
        }

        try {
            $policy = json_decode(File::get($corsPolicyPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error("The CORS policy at {$corsPolicyPath} is not valid JSON: {$e->getMessage()}");

            return self::FAILURE;
        }

        info('Applying CORS policy...');

        $failedCorsBuckets = $manager->applyCorsToBuckets($corsBuckets, $policy);

        foreach (array_diff($corsBuckets, $failedCorsBuckets) as $bucket) {
            info("  -> {$bucket}");
        }

        if ($failedCorsBuckets !== []) {
            error('Failed to apply the CORS policy to: '.implode(', ', $failedCorsBuckets));

            return self::FAILURE;
        }

        info('Done.');

        return self::SUCCESS;
    }
}
