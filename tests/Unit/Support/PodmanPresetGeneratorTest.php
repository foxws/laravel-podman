<?php

declare(strict_types=1);

use Foxws\Podman\Support\PodmanPresetGenerator;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->basePath = sys_get_temp_dir().'/podman-base-'.uniqid();

    config([
        'podman.base_path' => $this->basePath,
        'podman.working_path' => '/home/francois/acme',
        'podman.quadlet_prefix' => 'acme',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->basePath);
});

it('renders a preset into the base path and returns where it went', function () {
    $target = (new PodmanPresetGenerator)->generate('production');

    expect($target)->toBe("{$this->basePath}/podman/production")
        ->and(File::exists("{$target}/app.quadlets"))->toBeTrue()
        ->and(File::exists("{$target}/runtimes/Containerfile"))->toBeTrue()
        ->and(File::get("{$target}/app.quadlets"))
        ->toContain('/home/francois/acme/podman/production/runtimes')
        ->not->toContain('{{');
});

it('reads published presets from the stubs path under the base path', function () {
    File::ensureDirectoryExists("{$this->basePath}/containers/stubs/custom/quadlets");
    File::put("{$this->basePath}/containers/stubs/custom/quadlets/app.quadlets", "# FileName={{application}}\n");

    $target = (new PodmanPresetGenerator)->generate('custom');

    expect(File::get("{$target}/app.quadlets"))->toBe("# FileName=acme\n");
});
