<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->publishPath = sys_get_temp_dir().'/podman-publish-'.uniqid();
    config(['podman.publish_path' => $this->publishPath]);
});

afterEach(function () {
    File::deleteDirectory($this->publishPath);
});

it('generates the selected preset to the publish path', function () {
    $this->artisan('podman:generate')
        ->expectsQuestion('Select a preset to generate', 'frankenphp-octane')
        ->expectsOutputToContain("Preset frankenphp-octane generated to {$this->publishPath}/frankenphp-octane")
        ->assertExitCode(0);

    expect(File::exists("{$this->publishPath}/frankenphp-octane/app.quadlets"))->toBeTrue()
        ->and(File::exists("{$this->publishPath}/frankenphp-octane/runtimes/Containerfile"))->toBeTrue();
});

it('accepts the preset name as an argument, skipping the prompt', function () {
    $this->artisan('podman:generate', ['preset' => 'proxy'])
        ->expectsOutputToContain("Preset proxy generated to {$this->publishPath}/proxy")
        ->assertExitCode(0);

    expect(File::exists("{$this->publishPath}/proxy/proxy.quadlets"))->toBeTrue()
        ->and(File::exists("{$this->publishPath}/proxy/runtimes/Caddyfile"))->toBeTrue();
});

it('substitutes placeholders in the generated quadlets file', function () {
    config(['podman.quadlet_prefix' => 'acme']);

    $this->artisan('podman:generate', ['preset' => 'frankenphp-octane'])
        ->assertExitCode(0);

    expect(File::get("{$this->publishPath}/frankenphp-octane/app.quadlets"))->toContain('localhost/acme:latest');
});

it('overrides the working path for this run via --working-path', function () {
    $this->artisan('podman:generate', [
        'preset' => 'frankenphp-octane',
        '--working-path' => '/srv/my-app',
    ])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/frankenphp-octane/app.quadlets"))->toContain('/srv/my-app');
    expect(config('podman.working_path'))->toBe('/srv/my-app');
});

it('refuses to run when podman is disabled', function () {
    config(['podman.enabled' => false]);

    $this->artisan('podman:generate', ['preset' => 'proxy'])
        ->expectsOutputToContain('Podman is disabled.')
        ->assertExitCode(1);

    expect(File::isDirectory("{$this->publishPath}/proxy"))->toBeFalse();
});

it('starts a queue worker alongside the app instead of horizon', function (string $preset) {
    $this->artisan('podman:generate', ['preset' => $preset])->assertExitCode(0);

    $application = config('podman.quadlet_prefix');

    expect(File::get("{$this->publishPath}/{$preset}/queue.quadlets"))
        ->toContain("# FileName={$application}-queue")
        ->toContain('artisan queue:work --sleep=3 --tries=3 --max-time=3600')
        ->toContain("BindsTo={$application}.container")
        ->and(File::get("{$this->publishPath}/{$preset}/app.quadlets"))
        ->toMatch("/^Wants=.*{$application}-queue\\.container/m")
        ->not->toContain("{$application}-horizon.container");
})->with(['development', 'frankenphp-octane']);
