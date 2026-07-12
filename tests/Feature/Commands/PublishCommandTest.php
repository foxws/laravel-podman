<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->containerPath = sys_get_temp_dir().'/podman-publish-'.uniqid();
    $this->proxyPath = sys_get_temp_dir().'/podman-publish-proxy-'.uniqid();
    config([
        'podman.quadlet_container_path' => $this->containerPath,
        'podman.quadlet_proxy_path' => $this->proxyPath,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->containerPath);
    File::deleteDirectory($this->proxyPath);
});

it('publishes the selected runtime to the container path, creating it if needed', function () {
    expect(File::isDirectory($this->containerPath))->toBeFalse();

    $this->artisan('podman:publish')
        ->expectsQuestion('Select a runtime to publish', 'frankenphp-octane')
        ->expectsOutputToContain("Runtime frankenphp-octane published to {$this->containerPath}")
        ->assertExitCode(0);

    expect(File::exists("{$this->containerPath}/Containerfile"))->toBeTrue()
        ->and(File::exists("{$this->containerPath}/runtimes/entrypoint.sh"))->toBeTrue()
        ->and(File::exists("{$this->containerPath}/runtimes/php-production.ini"))->toBeTrue()
        ->and(File::exists("{$this->containerPath}/runtimes/php-development.ini"))->toBeTrue()
        ->and(File::exists("{$this->containerPath}/runtimes/Containerfile"))->toBeFalse();
});

it('also publishes the proxy configuration alongside the runtime, creating it if needed', function () {
    expect(File::isDirectory($this->proxyPath))->toBeFalse();

    $this->artisan('podman:publish')
        ->expectsQuestion('Select a runtime to publish', 'frankenphp-octane')
        ->expectsOutputToContain("Proxy configuration published to {$this->proxyPath}")
        ->assertExitCode(0);

    expect(File::exists("{$this->proxyPath}/Caddyfile"))->toBeTrue()
        ->and(File::exists("{$this->proxyPath}/sites/app.Caddyfile"))->toBeTrue();
});

it('accepts the runtime name as an argument, skipping the prompt', function () {
    $this->artisan('podman:publish', ['runtime' => 'frankenphp-octane'])
        ->expectsOutputToContain("Runtime frankenphp-octane published to {$this->containerPath}")
        ->assertExitCode(0);

    expect(File::exists("{$this->containerPath}/Containerfile"))->toBeTrue();
});

it('refuses to overwrite an existing Containerfile without the force option', function () {
    File::ensureDirectoryExists($this->containerPath);
    File::put("{$this->containerPath}/Containerfile", 'existing');

    $this->artisan('podman:publish')
        ->expectsQuestion('Select a runtime to publish', 'frankenphp-octane')
        ->assertExitCode(1);

    expect(File::get("{$this->containerPath}/Containerfile"))->toBe('existing');
});

it('refuses to overwrite an existing Caddyfile without the force option', function () {
    File::ensureDirectoryExists($this->proxyPath);
    File::put("{$this->proxyPath}/Caddyfile", 'existing');

    $this->artisan('podman:publish')
        ->expectsQuestion('Select a runtime to publish', 'frankenphp-octane')
        ->assertExitCode(1);

    expect(File::get("{$this->proxyPath}/Caddyfile"))->toBe('existing');
});

it('overwrites existing files when the force option is passed', function () {
    File::ensureDirectoryExists($this->containerPath);
    File::ensureDirectoryExists($this->proxyPath);
    File::put("{$this->containerPath}/Containerfile", 'existing');
    File::put("{$this->proxyPath}/Caddyfile", 'existing');

    $this->artisan('podman:publish', ['--force' => true])
        ->expectsQuestion('Select a runtime to publish', 'frankenphp-octane')
        ->assertExitCode(0);

    expect(File::get("{$this->containerPath}/Containerfile"))->not->toBe('existing')
        ->and(File::get("{$this->proxyPath}/Caddyfile"))->not->toBe('existing');
});
