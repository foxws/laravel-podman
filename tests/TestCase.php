<?php

namespace Foxws\Podman\Tests;

use Foxws\Podman\PodmanServiceProvider;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            PodmanServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }

    /**
     * Create a temporary preset directory (with a "quadlets/" folder of
     * ".quadlets" service definitions) and point the package's "stubs_path"
     * config at its parent, so "{preset}" resolves to it.
     *
     * @param  array<int, string>  $services
     */
    protected function makePresetPath(string $preset = 'stub-preset', array $services = ['pgsql', 'mariadb']): string
    {
        $stubsPath = sys_get_temp_dir().'/podman-stubs-'.uniqid();
        $quadletsPath = "{$stubsPath}/{$preset}/quadlets";

        File::ensureDirectoryExists($quadletsPath);

        foreach ($services as $service) {
            File::put("{$quadletsPath}/{$service}.quadlets", "# FileName={{application}}-{$service}\n[Unit]\nDescription={$service} container\n");
        }

        config(['podman.stubs_path' => $stubsPath]);

        return "{$stubsPath}/{$preset}";
    }
}
