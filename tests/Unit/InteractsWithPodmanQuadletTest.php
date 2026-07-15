<?php

declare(strict_types=1);

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Support\Facades\File;

uses(InteractsWithPodmanQuadlet::class);

it('lists the presets available in the vendor stubs directory', function () {
    expect($this->getPodmanQuadletPresets())->toBe([
        'devcontainer' => 'devcontainer',
        'development' => 'development',
        'frankenphp-octane' => 'frankenphp-octane',
        'proxy' => 'proxy',
        's3' => 's3',
    ]);
});

it('merges presets discovered in the configured stubs path with the vendor ones', function () {
    $stubsPath = sys_get_temp_dir().'/podman-stubs-'.uniqid();
    File::ensureDirectoryExists("{$stubsPath}/php-container/quadlets");
    config(['podman.stubs_path' => $stubsPath]);

    expect($this->getPodmanQuadletPresets())->toBe([
        'devcontainer' => 'devcontainer',
        'development' => 'development',
        'frankenphp-octane' => 'frankenphp-octane',
        'php-container' => 'php-container',
        'proxy' => 'proxy',
        's3' => 's3',
    ]);

    File::deleteDirectory($stubsPath);
});

it('returns the configured list of default presets', function () {
    config(['podman.presets' => 'proxy,frankenphp-octane']);

    expect($this->getPodmanQuadletDefaultPresets())->toBe(['proxy', 'frankenphp-octane']);
});

it('publishes a preset from the vendor stubs into the configured stubs path', function () {
    $stubsPath = sys_get_temp_dir().'/podman-stubs-'.uniqid();
    config(['podman.stubs_path' => $stubsPath]);

    expect($this->publishPodmanPreset('proxy'))->toBeTrue();

    expect(File::exists("{$stubsPath}/proxy/quadlets/proxy.quadlets"))->toBeTrue()
        ->and(File::exists("{$stubsPath}/proxy/runtimes/Caddyfile"))->toBeTrue();

    File::deleteDirectory($stubsPath);
});

it('refuses to overwrite an already-published preset without the force option', function () {
    $stubsPath = sys_get_temp_dir().'/podman-stubs-'.uniqid();
    File::ensureDirectoryExists("{$stubsPath}/proxy");
    File::put("{$stubsPath}/proxy/marker", 'existing');
    config(['podman.stubs_path' => $stubsPath]);

    expect($this->publishPodmanPreset('proxy'))->toBeFalse();

    expect(File::exists("{$stubsPath}/proxy/marker"))->toBeTrue();

    File::deleteDirectory($stubsPath);
});

it('overwrites an already-published preset when the force option is passed', function () {
    $stubsPath = sys_get_temp_dir().'/podman-stubs-'.uniqid();
    File::ensureDirectoryExists("{$stubsPath}/proxy");
    File::put("{$stubsPath}/proxy/marker", 'existing');
    config(['podman.stubs_path' => $stubsPath]);

    expect($this->publishPodmanPreset('proxy', force: true))->toBeTrue();

    expect(File::exists("{$stubsPath}/proxy/quadlets/proxy.quadlets"))->toBeTrue();

    File::deleteDirectory($stubsPath);
});

it('publishes multiple presets, returning no failures on success', function () {
    $stubsPath = sys_get_temp_dir().'/podman-stubs-'.uniqid();
    config(['podman.stubs_path' => $stubsPath]);

    expect($this->publishPodmanPresets(['frankenphp-octane', 'proxy']))->toBe([]);

    expect(File::isDirectory("{$stubsPath}/frankenphp-octane"))->toBeTrue()
        ->and(File::isDirectory("{$stubsPath}/proxy"))->toBeTrue();

    File::deleteDirectory($stubsPath);
});

it('reports the presets that failed to publish while continuing with the rest', function () {
    $stubsPath = sys_get_temp_dir().'/podman-stubs-'.uniqid();
    File::ensureDirectoryExists("{$stubsPath}/proxy");
    File::put("{$stubsPath}/proxy/marker", 'existing');
    config(['podman.stubs_path' => $stubsPath]);

    expect($this->publishPodmanPresets(['proxy', 'frankenphp-octane']))->toBe(['proxy']);

    expect(File::exists("{$stubsPath}/proxy/marker"))->toBeTrue()
        ->and(File::isDirectory("{$stubsPath}/frankenphp-octane"))->toBeTrue();

    File::deleteDirectory($stubsPath);
});

it('generates a preset\'s quadlets and runtime files into the configured publish path', function () {
    $stubsPath = sys_get_temp_dir().'/podman-stubs-'.uniqid();
    File::ensureDirectoryExists("{$stubsPath}/my-preset/quadlets");
    File::ensureDirectoryExists("{$stubsPath}/my-preset/runtimes");
    File::put("{$stubsPath}/my-preset/quadlets/app.quadlets", "Runtime={{runtimePath}}\n");
    File::put("{$stubsPath}/my-preset/quadlets/README.md", 'not a quadlet');
    File::put("{$stubsPath}/my-preset/runtimes/Containerfile", "FROM base\n");

    $publishPath = sys_get_temp_dir().'/podman-publish-'.uniqid();
    config(['podman.stubs_path' => $stubsPath, 'podman.publish_path' => $publishPath]);

    $this->generatePodmanPreset('my-preset');

    expect(File::exists("{$publishPath}/my-preset/app.quadlets"))->toBeTrue()
        ->and(File::exists("{$publishPath}/my-preset/README.md"))->toBeFalse()
        ->and(File::get("{$publishPath}/my-preset/app.quadlets"))->toBe("Runtime={$publishPath}/my-preset/runtimes\n")
        ->and(File::get("{$publishPath}/my-preset/runtimes/Containerfile"))->toBe("FROM base\n");

    File::deleteDirectory($stubsPath);
    File::deleteDirectory($publishPath);
});

it('generates only the quadlets or only the runtimes when the preset has just one of them', function () {
    $stubsPath = sys_get_temp_dir().'/podman-stubs-'.uniqid();
    File::ensureDirectoryExists("{$stubsPath}/quadlets-only/quadlets");
    File::put("{$stubsPath}/quadlets-only/quadlets/app.quadlets", "Foo=bar\n");

    $publishPath = sys_get_temp_dir().'/podman-publish-'.uniqid();
    config(['podman.stubs_path' => $stubsPath, 'podman.publish_path' => $publishPath]);

    $this->generatePodmanPreset('quadlets-only');

    expect(File::exists("{$publishPath}/quadlets-only/app.quadlets"))->toBeTrue()
        ->and(File::isDirectory("{$publishPath}/quadlets-only/runtimes"))->toBeFalse();

    File::deleteDirectory($stubsPath);
    File::deleteDirectory($publishPath);
});

it('generates multiple presets', function () {
    $stubsPath = sys_get_temp_dir().'/podman-stubs-'.uniqid();
    File::ensureDirectoryExists("{$stubsPath}/preset-a/quadlets");
    File::ensureDirectoryExists("{$stubsPath}/preset-b/quadlets");
    File::put("{$stubsPath}/preset-a/quadlets/app.quadlets", "Foo=bar\n");
    File::put("{$stubsPath}/preset-b/quadlets/app.quadlets", "Foo=bar\n");

    $publishPath = sys_get_temp_dir().'/podman-publish-'.uniqid();
    config(['podman.stubs_path' => $stubsPath, 'podman.publish_path' => $publishPath]);

    $this->generatePodmanPresets(['preset-a', 'preset-b']);

    expect(File::exists("{$publishPath}/preset-a/app.quadlets"))->toBeTrue()
        ->and(File::exists("{$publishPath}/preset-b/app.quadlets"))->toBeTrue();

    File::deleteDirectory($stubsPath);
    File::deleteDirectory($publishPath);
});
