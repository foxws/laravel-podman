<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands {
    use Foxws\Podman\Tests\Feature\Commands\FakeSdkState;

    /**
     * Overrides the "class_exists" call made by S3SetupCommand so a test can
     * simulate the AWS SDK not being installed, without actually removing it.
     * PHP resolves unqualified function calls in the caller's namespace
     * first, so this only intercepts the check made from this namespace.
     */
    function class_exists(string $class, bool $autoload = true): bool
    {
        return FakeSdkState::$missing
            ? false
            : \class_exists($class, $autoload);
    }
}

namespace Foxws\Podman\Tests\Feature\Commands {
    class FakeSdkState
    {
        public static bool $missing = false;
    }
}

namespace {
    use Foxws\Podman\Tests\Feature\Commands\FakeSdkState;

    afterEach(function () {
        FakeSdkState::$missing = false;
    });

    it('reports a friendly error when the AWS SDK is not installed', function () {
        FakeSdkState::$missing = true;

        $this->artisan('podman:s3-setup')
            ->expectsOutputToContain('The AWS SDK is required to use this command. Install it with "composer require aws/aws-sdk-php".')
            ->assertExitCode(1);
    });
}
