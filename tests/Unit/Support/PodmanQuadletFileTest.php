<?php

declare(strict_types=1);

use Foxws\Podman\Support\PodmanQuadletFile;
use Foxws\Podman\Support\PodmanQuadletPath;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->path = new PodmanQuadletPath;
    $this->file = new PodmanQuadletFile($this->path);
});

it('removes selinux volume flags from quadlet contents', function () {
    $contents = "Volume=stub-pgsql:/var/lib/postgresql:rw,Z,U\nOther=value";

    expect($this->file->removeSelinuxVolumeFlags($contents))
        ->toBe("Volume=stub-pgsql:/var/lib/postgresql:rw\nOther=value");
});

it('keeps volume entries without selinux flags untouched', function () {
    $contents = 'Volume=stub-pgsql:/var/lib/postgresql';

    expect($this->file->removeSelinuxVolumeFlags($contents))->toBe($contents);
});

it('renders a quadlet source without writing a temporary file', function () {
    $source = sys_get_temp_dir().'/podman-source-'.uniqid().'.quadlets';
    File::put($source, "Volume={{application}}-pgsql:/var/lib/postgresql:rw\n");
    config(['podman.quadlet_prefix' => 'acme']);

    expect($this->file->renderSource($source, 'frankenphp-octane'))->toContain('acme-pgsql');

    File::delete($source);
});

it('prepares a quadlet source file with the prefix placeholder replaced', function () {
    $source = sys_get_temp_dir().'/podman-source-'.uniqid().'.quadlets';
    $target = sys_get_temp_dir().'/podman-target-'.uniqid().'.quadlets';
    File::put($source, "Volume={{application}}-pgsql:/var/lib/postgresql:rw\n");
    config(['podman.quadlet_prefix' => 'acme']);

    $this->file->prepareSource($source, $target, 'frankenphp-octane');

    expect(File::get($target))->toContain('acme-pgsql');

    File::delete($source);
    File::delete($target);
});

it('replaces the workingPath and runtimePath placeholders', function () {
    $source = sys_get_temp_dir().'/podman-source-'.uniqid().'.quadlets';
    $target = sys_get_temp_dir().'/podman-target-'.uniqid().'.quadlets';
    config(['podman.publish_path' => 'podman']);
    File::put($source, "SetWorkingDirectory={{workingPath}}\nRuntime={{runtimePath}}\n");

    $this->file->prepareSource($source, $target, 'frankenphp-octane');

    expect(File::get($target))->toBe(
        'SetWorkingDirectory='.base_path()."\nRuntime=".base_path('podman/frankenphp-octane/runtimes')."\n",
    );

    File::delete($source);
    File::delete($target);
});

it('uses the configured working path for the workingPath and runtimePath placeholders', function () {
    $source = sys_get_temp_dir().'/podman-source-'.uniqid().'.quadlets';
    $target = sys_get_temp_dir().'/podman-target-'.uniqid().'.quadlets';
    config(['podman.working_path' => '/home/francois/app', 'podman.publish_path' => 'podman']);
    File::put($source, "SetWorkingDirectory={{workingPath}}\nRuntime={{runtimePath}}\n");

    $this->file->prepareSource($source, $target, 'frankenphp-octane');

    expect(File::get($target))->toBe(
        "SetWorkingDirectory=/home/francois/app\nRuntime=/home/francois/app/podman/frankenphp-octane/runtimes\n",
    );

    File::delete($source);
    File::delete($target);
});

it('scopes the runtimePath placeholder to the given preset', function () {
    $source = sys_get_temp_dir().'/podman-source-'.uniqid().'.quadlets';
    $target = sys_get_temp_dir().'/podman-target-'.uniqid().'.quadlets';
    config(['podman.publish_path' => 'podman']);
    File::put($source, "Runtime={{runtimePath}}\n");

    $this->file->prepareSource($source, $target, 'proxy');

    expect(File::get($target))->toBe('Runtime='.base_path('podman/proxy/runtimes')."\n");

    File::delete($source);
    File::delete($target);
});

it('replaces the appEnv, appUid and appGid placeholders', function () {
    $source = sys_get_temp_dir().'/podman-source-'.uniqid().'.quadlets';
    $target = sys_get_temp_dir().'/podman-target-'.uniqid().'.quadlets';
    config(['app.env' => 'testing']);
    File::put($source, "Environment=APP_ENV={{appEnv}}\nEnvironment=UID={{appUid}}\nEnvironment=GID={{appGid}}\n");

    $this->file->prepareSource($source, $target, 'frankenphp-octane');

    expect(File::get($target))->toBe(
        "Environment=APP_ENV=testing\nEnvironment=UID={$this->path->uid()}\nEnvironment=GID={$this->path->gid()}\n",
    );

    File::delete($source);
    File::delete($target);
});

it('strips selinux volume flags while preparing the source when disabled', function () {
    $source = sys_get_temp_dir().'/podman-source-'.uniqid().'.quadlets';
    $target = sys_get_temp_dir().'/podman-target-'.uniqid().'.quadlets';
    File::put($source, "Volume={{application}}-pgsql:/var/lib/postgresql:rw,Z,U\n");
    config(['podman.selinux_volume_mapping' => false, 'podman.quadlet_prefix' => 'laravel']);

    $this->file->prepareSource($source, $target, 'frankenphp-octane');

    expect(File::get($target))->toBe("Volume=laravel-pgsql:/var/lib/postgresql:rw\n");

    File::delete($source);
    File::delete($target);
});

it('publishes a directory recursively while substituting placeholders in every file', function () {
    config(['podman.quadlet_prefix' => 'acme']);

    $source = sys_get_temp_dir().'/podman-publish-source-'.uniqid();
    $target = sys_get_temp_dir().'/podman-publish-target-'.uniqid();
    File::ensureDirectoryExists("{$source}/sites");
    File::put("{$source}/Containerfile", "FROM base\n");
    File::put("{$source}/sites/app.quadlets", "Network={{application}}.network\n");

    $this->file->publishDirectory($source, $target, 'frankenphp-octane');

    expect(File::get("{$target}/Containerfile"))->toBe("FROM base\n")
        ->and(File::get("{$target}/sites/app.quadlets"))->toBe("Network=acme.network\n");

    File::deleteDirectory($source);
    File::deleteDirectory($target);
});
