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

it('passes the uid and gid to the image build', function (string $preset) {
    config(['podman.quadlet_uid' => 1234, 'podman.quadlet_gid' => 5678]);

    $this->artisan('podman:generate', ['preset' => $preset])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/{$preset}/app.quadlets"))
        ->toContain("BuildArg=UID=1234\nBuildArg=GID=5678");
})->with(['development', 'frankenphp-octane']);

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

it('starts a queue worker alongside the app instead of horizon in development', function () {
    $this->artisan('podman:generate', ['preset' => 'development'])->assertExitCode(0);

    $application = config('podman.quadlet_prefix');

    expect(File::get("{$this->publishPath}/development/queue.quadlets"))
        ->toContain("# FileName={$application}-queue")
        ->toContain('artisan queue:work --sleep=3 --tries=3 --max-time=3600')
        ->toContain("PartOf={$application}.container")
        ->and(File::get("{$this->publishPath}/development/app.quadlets"))
        ->toMatch("/^Wants=.*{$application}-queue\\.container/m")
        ->not->toContain("{$application}-horizon.container");
});

it('does not bind sidecars to the app', function (string $preset) {
    $this->artisan('podman:generate', ['preset' => $preset])->assertExitCode(0);

    foreach (File::glob("{$this->publishPath}/{$preset}/*.quadlets") as $quadlet) {
        expect(File::get($quadlet))->not->toContain('BindsTo=');
    }
})->with(['development', 'frankenphp-octane']);

it('does not start reverb with the app by default', function (string $preset) {
    $this->artisan('podman:generate', ['preset' => $preset])->assertExitCode(0);

    $application = config('podman.quadlet_prefix');

    expect(File::exists("{$this->publishPath}/{$preset}/reverb.quadlets"))->toBeTrue()
        ->and(File::get("{$this->publishPath}/{$preset}/app.quadlets"))
        ->not->toContain("{$application}-reverb.container");
})->with(['development', 'frankenphp-octane']);

it('renders the app always-on when on-demand is disabled', function (string $preset) {
    config(['podman.ondemand.enabled' => false]);

    $this->artisan('podman:generate', ['preset' => $preset])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/{$preset}/app.quadlets"))->toContain('StopWhenUnneeded=no');
})->with(['development', 'frankenphp-octane']);

it('renders the app on-demand by default', function (string $preset) {
    config([
        'podman.quadlet_prefix' => 'acme',
        'podman.ondemand.listen' => '0.0.0.0:9000',
        'podman.ondemand.port' => 19000,
        'podman.ondemand.idle_timeout' => '5min',
    ]);

    $this->artisan('podman:generate', ['preset' => $preset])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/{$preset}/acme-ondemand.socket"))
        ->toContain('ListenStream=0.0.0.0:9000')
        ->and(File::get("{$this->publishPath}/{$preset}/acme-ondemand.service"))
        ->toContain('Requires=acme.service acme-ondemand.socket')
        ->toContain('--exit-idle-time=5min 127.0.0.1:19000')
        ->and(File::get("{$this->publishPath}/{$preset}/app.quadlets"))
        ->toContain('StopWhenUnneeded=yes')
        ->toContain('PublishPort=127.0.0.1:19000:8000')
        ->toContain("Notify=healthy\nHealthStartupCmd=")
        ->toContain('HealthStartupInterval=1s');
})->with(['development', 'frankenphp-octane']);

it('runs the frankenphp-octane queue worker and scheduler independently of the app', function () {
    $this->artisan('podman:generate', ['preset' => 'frankenphp-octane'])->assertExitCode(0);

    $application = config('podman.quadlet_prefix');
    $path = "{$this->publishPath}/frankenphp-octane";

    expect(File::get("{$path}/queue.quadlets"))
        ->toContain('WantedBy=default.target')
        ->not->toContain("{$application}.container")
        ->and(File::get("{$path}/app.quadlets"))
        ->not->toContain("{$application}-queue.container")
        ->and(File::get("{$path}/schedule.quadlets"))
        ->toContain('artisan schedule:run')
        ->toContain('Type=oneshot')
        ->and(File::get("{$path}/{$application}-schedule.timer"))
        ->toContain('OnCalendar=*-*-* *:*:00');
});

it('points the proxy at the app or its on-demand socket', function () {
    config(['podman.quadlet_prefix' => 'acme', 'podman.ondemand.enabled' => false]);

    $this->artisan('podman:generate', ['preset' => 'proxy'])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/proxy/runtimes/sites/laravel.Caddyfile"))
        ->toContain('reverse_proxy systemd-acme:8000');

    config(['podman.ondemand.enabled' => true, 'podman.ondemand.listen' => '0.0.0.0:9000']);

    $this->artisan('podman:generate', ['preset' => 'proxy'])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/proxy/runtimes/sites/laravel.Caddyfile"))
        ->toContain('reverse_proxy host.containers.internal:9000');
});

it('removes output left over from a previous generate', function () {
    File::ensureDirectoryExists("{$this->publishPath}/proxy");
    File::put("{$this->publishPath}/proxy/stale.quadlets", '');

    $this->artisan('podman:generate', ['preset' => 'proxy'])->assertExitCode(0);

    expect(File::exists("{$this->publishPath}/proxy/stale.quadlets"))->toBeFalse()
        ->and(File::exists("{$this->publishPath}/proxy/proxy.quadlets"))->toBeTrue();
});

it('never removes preset templates that live in the publish path', function () {
    $presetPath = $this->makePresetPath('stub-preset', ['pgsql']);
    config(['podman.publish_path' => dirname($presetPath)]);

    $this->artisan('podman:generate', ['preset' => 'stub-preset'])->assertExitCode(0);

    expect(File::exists("{$presetPath}/quadlets/pgsql.quadlets"))->toBeTrue();

    File::deleteDirectory(dirname($presetPath));
});

it('generates an app key before building the frontend of the production image', function () {
    $this->artisan('podman:generate', ['preset' => 'frankenphp-octane'])->assertExitCode(0);

    $containerfile = File::get("{$this->publishPath}/frankenphp-octane/runtimes/Containerfile");

    expect(strpos($containerfile, 'key:generate'))->toBeInt()
        ->toBeLessThan(strpos($containerfile, 'pnpm build'));
});
