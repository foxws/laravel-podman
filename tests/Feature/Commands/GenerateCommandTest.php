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
        ->expectsQuestion('Select a preset to generate', 'production')
        ->expectsOutputToContain("Preset production generated to {$this->publishPath}/production")
        ->assertExitCode(0);

    expect(File::exists("{$this->publishPath}/production/app.quadlets"))->toBeTrue()
        ->and(File::exists("{$this->publishPath}/production/runtimes/Containerfile"))->toBeTrue();
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

    $this->artisan('podman:generate', ['preset' => 'production'])
        ->assertExitCode(0);

    expect(File::get("{$this->publishPath}/production/app.quadlets"))->toContain('localhost/acme:latest');
});

it('passes the uid and gid to the image build', function (string $preset) {
    config(['podman.quadlet_uid' => 1234, 'podman.quadlet_gid' => 5678]);

    $this->artisan('podman:generate', ['preset' => $preset])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/{$preset}/app.quadlets"))
        ->toContain("BuildArg=UID=1234\nBuildArg=GID=5678");
})->with(['development', 'production']);

it('overrides the working path for this run via --working-path', function () {
    $this->artisan('podman:generate', [
        'preset' => 'production',
        '--working-path' => '/srv/my-app',
    ])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/production/app.quadlets"))->toContain('/srv/my-app');
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
})->with(['development', 'production']);

it('does not start reverb with the app by default', function (string $preset) {
    $this->artisan('podman:generate', ['preset' => $preset])->assertExitCode(0);

    $application = config('podman.quadlet_prefix');

    expect(File::exists("{$this->publishPath}/{$preset}/reverb.quadlets"))->toBeTrue()
        ->and(File::get("{$this->publishPath}/{$preset}/app.quadlets"))
        ->not->toContain("{$application}-reverb.container");
})->with(['development', 'production']);

it('renders the app always-on when on-demand is disabled', function (string $preset) {
    config(['podman.ondemand.enabled' => false]);

    $this->artisan('podman:generate', ['preset' => $preset])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/{$preset}/app.quadlets"))->toContain('StopWhenUnneeded=no');
})->with(['development', 'production']);

it('renders the app on-demand by default', function (string $preset) {
    config([
        'podman.quadlet_prefix' => 'acme',
        'podman.ondemand.port' => 19000,
    ]);

    $this->artisan('podman:generate', ['preset' => $preset])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/{$preset}/app.quadlets"))
        ->toContain('StopWhenUnneeded=yes')
        ->toContain('PublishPort=127.0.0.1:19000:8000')
        ->toContain("Notify=healthy\nHealthStartupCmd=")
        ->toContain('HealthStartupInterval=1s');
})->with(['development', 'production']);

it('renders the on-demand socket in its own preset', function () {
    config([
        'podman.quadlet_prefix' => 'acme',
        'podman.ondemand.listen' => '0.0.0.0:9000',
        'podman.ondemand.port' => 19000,
        'podman.ondemand.idle_timeout' => '5min',
    ]);

    $this->artisan('podman:generate', ['preset' => 'ondemand'])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/ondemand/acme-ondemand.socket"))
        ->toContain('ListenStream=0.0.0.0:9000')
        ->and(File::get("{$this->publishPath}/ondemand/acme-ondemand.service"))
        ->toContain('Requires=acme.service acme-ondemand.socket')
        ->toContain('--exit-idle-time=5min 127.0.0.1:19000');
});

it('lets services sleep with the app by default', function (string $preset) {
    $this->artisan('podman:generate', ['preset' => $preset])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/{$preset}/pgsql.quadlets"))
        ->toContain('StopWhenUnneeded=yes')
        ->toContain("Notify=healthy\nHealthStartupCmd=pg_isready -q -h 127.0.0.1 -U \"\$\$POSTGRES_USER\" -d postgres")
        ->toContain("HealthCmd=pg_isready -q -h 127.0.0.1 -U \"\$\$POSTGRES_USER\" -d postgres")
        ->and(File::get("{$this->publishPath}/{$preset}/rustfs.quadlets"))
        ->toContain('StopWhenUnneeded=yes')
        ->and(File::get("{$this->publishPath}/{$preset}/typesense.quadlets"))
        ->toContain('StopWhenUnneeded=yes')
        ->not->toContain('PartOf=');

    foreach (['pgsql', 'mysql', 'mariadb', 'mongodb', 'valkey', 'redis', 'rustfs', 'typesense', 'meilisearch', 'mailpit'] as $service) {
        expect(File::get("{$this->publishPath}/{$preset}/{$service}.quadlets"))
            ->toContain("Notify=healthy\nHealthStartupCmd=");
    }

    foreach (['mailpit', 'reverb'] as $service) {
        expect(File::get("{$this->publishPath}/{$preset}/{$service}.quadlets"))
            ->toContain('StopWhenUnneeded=yes')
            ->not->toContain('PartOf=');
    }
})->with(['development', 'production']);

it('keeps services running when on-demand is disabled', function () {
    config(['podman.quadlet_prefix' => 'acme', 'podman.ondemand.enabled' => false]);

    $this->artisan('podman:generate', ['preset' => 'development'])->assertExitCode(0);
    $this->artisan('podman:generate', ['preset' => 'ondemand'])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/development/pgsql.quadlets"))->toContain('StopWhenUnneeded=no')
        ->and(File::get("{$this->publishPath}/ondemand/acme-idle.service"))->toContain('ExecCondition=/usr/bin/test no = yes');
});

it('renders an idle check that stops the queue workers, then the scheduler timer', function () {
    config(['podman.quadlet_prefix' => 'acme', 'podman.quadlet_uid' => 1000]);

    $this->artisan('podman:generate', ['preset' => 'ondemand'])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/ondemand/acme-idle.timer"))
        ->toContain('WantedBy=timers.target')
        ->and(File::get("{$this->publishPath}/ondemand/acme-idle.service"))
        ->toContain(<<<'UNIT'
            TimeoutStartSec=50
            ExecCondition=/usr/bin/test yes = yes
            ExecCondition=/bin/sh -c '! systemctl --user --quiet is-active acme.service'
            ExecCondition=/bin/sh -c '! systemctl --user --quiet is-active acme-schedule.service'
            ExecCondition=/bin/sh -c '! systemctl --user --quiet is-active acme-queue.service || exec podman exec systemd-acme-queue php -d variables_order=EGPCS /app/artisan podman:idle'
            ExecCondition=/bin/sh -c '! systemctl --user --quiet is-active acme-horizon.service || exec podman exec systemd-acme-horizon php -d variables_order=EGPCS /app/artisan podman:idle'
            ExecCondition=/bin/sh -c '! systemctl --user --quiet is-active acme.service'
            ExecStart=-systemctl --user stop --no-block acme-queue.service
            ExecStart=-systemctl --user stop --no-block acme-horizon.service
            ExecStart=-systemctl --user stop --no-block acme-schedule.timer
            UNIT);
});

it('renders an idle check for the system service manager when installed as root', function () {
    config(['podman.quadlet_prefix' => 'acme', 'podman.quadlet_uid' => 0]);

    $this->artisan('podman:generate', ['preset' => 'ondemand'])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/ondemand/acme-idle.service"))
        ->toContain('ExecStart=-systemctl stop --no-block acme-schedule.timer')
        ->not->toContain('--user');
});

it('wakes the production queue worker and scheduler timer with the app', function () {
    config(['podman.quadlet_prefix' => 'acme']);

    $this->artisan('podman:generate', ['preset' => 'production'])->assertExitCode(0);

    expect(File::get("{$this->publishPath}/production/app.quadlets"))
        ->toMatch('/^Wants=.*acme-queue\\.container acme-schedule\\.timer$/m');
});

it('runs the production queue worker and scheduler independently of the app', function () {
    $this->artisan('podman:generate', ['preset' => 'production'])->assertExitCode(0);

    $application = config('podman.quadlet_prefix');
    $path = "{$this->publishPath}/production";

    expect(File::get("{$path}/queue.quadlets"))
        ->toContain('WantedBy=default.target')
        ->not->toContain("{$application}.container")
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
    $this->artisan('podman:generate', ['preset' => 'production'])->assertExitCode(0);

    $containerfile = File::get("{$this->publishPath}/production/runtimes/Containerfile");

    expect(strpos($containerfile, 'key:generate'))->toBeInt()
        ->toBeLessThan(strpos($containerfile, 'pnpm build'));
});

it('stops development queue workers with the on-demand app', function (string $worker) {
    $this->artisan('podman:generate', ['preset' => 'development'])->assertExitCode(0);

    $application = config('podman.quadlet_prefix');

    expect(File::get("{$this->publishPath}/development/{$worker}.quadlets"))
        ->toContain("PartOf={$application}.container")
        ->not->toContain("BindsTo={$application}.container");
})->with(['queue', 'horizon']);

it('keeps production queue workers running while the on-demand app is idle', function (string $worker) {
    $this->artisan('podman:generate', ['preset' => 'production'])->assertExitCode(0);

    $application = config('podman.quadlet_prefix');

    expect(File::get("{$this->publishPath}/production/{$worker}.quadlets"))
        ->not->toContain("PartOf={$application}.container")
        ->toContain("Requires={$application}-pgsql.container {$application}-valkey.container");
})->with(['queue', 'horizon']);
