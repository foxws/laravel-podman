<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->stubsPath = sys_get_temp_dir().'/podman-stubs-'.uniqid();
    config(['podman.stubs_path' => $this->stubsPath]);
});

afterEach(function () {
    File::deleteDirectory($this->stubsPath);
});

it('publishes the selected preset to the stubs path, creating it if needed', function () {
    expect(File::isDirectory("{$this->stubsPath}/production"))->toBeFalse();

    $this->artisan('podman:publish')
        ->expectsQuestion('Select a preset to publish', 'production')
        ->expectsOutputToContain("Preset production published to {$this->stubsPath}/production")
        ->assertExitCode(0);

    expect(File::exists("{$this->stubsPath}/production/runtimes/Containerfile"))->toBeTrue()
        ->and(File::exists("{$this->stubsPath}/production/quadlets/app.quadlets"))->toBeTrue();
});

it('keeps "{{placeholder}}" tokens intact instead of substituting them', function () {
    config(['podman.quadlet_prefix' => 'acme']);

    $this->artisan('podman:publish', ['preset' => 'production'])
        ->assertExitCode(0);

    expect(File::get("{$this->stubsPath}/production/quadlets/app.quadlets"))
        ->toContain('{{application}}')
        ->not->toContain('acme');
});

it('accepts the preset name as an argument, skipping the prompt', function () {
    $this->artisan('podman:publish', ['preset' => 'production'])
        ->expectsOutputToContain("Preset production published to {$this->stubsPath}/production")
        ->assertExitCode(0);

    expect(File::exists("{$this->stubsPath}/production/runtimes/Containerfile"))->toBeTrue();
});

it('refuses to overwrite an existing published preset without the force option', function () {
    File::ensureDirectoryExists("{$this->stubsPath}/production");
    File::put("{$this->stubsPath}/production/marker", 'existing');

    $this->artisan('podman:publish', ['preset' => 'production'])
        ->assertExitCode(1);

    expect(File::get("{$this->stubsPath}/production/marker"))->toBe('existing');
});

it('overwrites existing files when the force option is passed', function () {
    File::ensureDirectoryExists("{$this->stubsPath}/production");
    File::put("{$this->stubsPath}/production/marker", 'existing');

    $this->artisan('podman:publish', ['preset' => 'production', '--force' => true])
        ->assertExitCode(0);

    expect(File::exists("{$this->stubsPath}/production/quadlets/app.quadlets"))->toBeTrue();
});

it('refuses to run when podman is disabled', function () {
    config(['podman.enabled' => false]);

    $this->artisan('podman:publish', ['preset' => 'production'])
        ->expectsOutputToContain('Podman is disabled.')
        ->assertExitCode(1);

    expect(File::isDirectory("{$this->stubsPath}/production"))->toBeFalse();
});
