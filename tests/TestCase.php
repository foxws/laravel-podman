<?php

namespace Foxws\Podman\Tests;

use Foxws\Podman\PodmanServiceProvider;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected string $originalPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPath = (string) getenv('PATH');
    }

    protected function tearDown(): void
    {
        $this->restorePodmanBinary();

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            PodmanServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }

    /**
     * Prepend a fake "podman" executable to the PATH so commands that shell
     * out to it can be exercised without a real Podman installation.
     */
    protected function useFakePodmanBinary(int $exitCode = 0): void
    {
        $path = __DIR__.'/Fixtures/bin'.PATH_SEPARATOR.$this->originalPath;

        putenv("PATH={$path}");
        putenv("PODMAN_TEST_EXIT_CODE={$exitCode}");

        $_SERVER['PATH'] = $path;
        $_ENV['PATH'] = $path;
        $_SERVER['PODMAN_TEST_EXIT_CODE'] = (string) $exitCode;
        $_ENV['PODMAN_TEST_EXIT_CODE'] = (string) $exitCode;
    }

    protected function restorePodmanBinary(): void
    {
        putenv("PATH={$this->originalPath}");
        putenv('PODMAN_TEST_EXIT_CODE');

        $_SERVER['PATH'] = $this->originalPath;
        $_ENV['PATH'] = $this->originalPath;
        unset($_SERVER['PODMAN_TEST_EXIT_CODE'], $_ENV['PODMAN_TEST_EXIT_CODE']);
    }

    /**
     * Clear the PATH so commands that shell out to "podman" can be exercised
     * as if it isn't installed, regardless of whether the host machine
     * running the tests actually has it.
     */
    protected function makePodmanBinaryUnavailable(): void
    {
        $path = sys_get_temp_dir().'/podman-unavailable-'.uniqid();

        putenv("PATH={$path}");

        $_SERVER['PATH'] = $path;
        $_ENV['PATH'] = $path;
    }

    /**
     * Create a temporary directory of ".quadlets" service definitions and
     * point the package's "quadlets_path" config at it.
     *
     * @param  array<int, string>  $services
     */
    protected function makeQuadletsPath(array $services = ['pgsql', 'mariadb']): string
    {
        $path = sys_get_temp_dir().'/podman-quadlets-'.uniqid();

        File::ensureDirectoryExists($path);

        foreach ($services as $service) {
            File::put("{$path}/{$service}.quadlets", "# FileName={{application}}-{$service}\n[Unit]\nDescription={$service} container\n");
        }

        config(['podman.quadlets_path' => $path]);

        return $path;
    }
}
