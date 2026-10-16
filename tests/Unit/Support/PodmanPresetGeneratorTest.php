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
    $rendered = (new PodmanPresetGenerator)->generate('production');
    $target = $rendered->path;

    expect($target)->toBe("{$this->basePath}/podman/production")
        ->and($rendered->preset)->toBe('production')
        ->and($rendered->quadlets)->toContain("{$target}/app.quadlets")
        ->and($rendered->systemdUnits)->toBe(["{$target}/acme-schedule.timer"])
        ->and($rendered->runtimesPath)->toBe("{$target}/runtimes")
        ->and(File::exists("{$target}/app.quadlets"))->toBeTrue()
        ->and(File::exists("{$target}/runtimes/Containerfile"))->toBeTrue()
        ->and(File::get("{$target}/app.quadlets"))
        ->toContain('/home/francois/acme/podman/production/runtimes')
        ->not->toContain('{{');
});

it('reads published presets from the stubs path under the base path', function () {
    File::ensureDirectoryExists("{$this->basePath}/containers/stubs/custom/quadlets");
    File::put("{$this->basePath}/containers/stubs/custom/quadlets/app.quadlets", "# FileName={{application}}\n");

    $rendered = (new PodmanPresetGenerator)->generate('custom');

    expect(File::get("{$rendered->path}/app.quadlets"))->toBe("# FileName=acme\n")
        ->and($rendered->systemdUnits)->toBe([])
        ->and($rendered->runtimesPath)->toBeNull();
});

it('lists the units, sockets, timers and secrets it rendered', function () {
    $rendered = (new PodmanPresetGenerator)->generate('production');

    expect(array_map(fn ($unit) => $unit->fileName(), $rendered->units()))->toContain('acme.build', 'acme.container')
        ->and($rendered->systemdUnitsOfType('timer'))->toBe(["{$rendered->path}/acme-schedule.timer"])
        ->and($rendered->systemdUnitsOfType('socket'))->toBe([])
        ->and(array_map(fn ($secret) => $secret->name, $rendered->secrets()))->toContain('acme-env');
});

it('renders every bundled preset into valid units', function (string $preset) {
    $rendered = (new PodmanPresetGenerator)->generate($preset);

    $contents = implode('', array_map(fn ($unit) => $unit->contents, $rendered->units()));

    expect($contents)->not->toContain('{{')
        ->and($contents)->not->toContain('VolumeName=systemd-');
})->with(['devcontainer', 'development', 'ondemand', 'production', 'proxy']);
