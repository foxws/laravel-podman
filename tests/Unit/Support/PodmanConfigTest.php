<?php

declare(strict_types=1);

use Foxws\Podman\Support\PodmanConfig;

beforeEach(function () {
    $this->config = new PodmanConfig;
});

it('resolves the current process uid and gid by default', function () {
    expect($this->config->uid())->toBe(posix_getuid())
        ->and($this->config->gid())->toBe(posix_getgid());
});

it('uses the configured uid and gid when set', function () {
    config(['podman.quadlet_uid' => 2000, 'podman.quadlet_gid' => 2001]);

    expect($this->config->uid())->toBe(2000)
        ->and($this->config->gid())->toBe(2001);
});

it('resolves the domain from the app url', function () {
    config(['app.url' => 'https://example.test']);

    expect($this->config->domain())->toBe('example.test');
});

it('kebab-cases the configured quadlet prefix', function () {
    config(['podman.quadlet_prefix' => 'My App']);

    expect($this->config->prefix())->toBe('my-app');
});

it('defaults selinux volume mapping to true', function () {
    expect($this->config->shouldUseSelinuxVolumeMapping())->toBeTrue();
});

it('disables selinux volume mapping when configured', function () {
    config(['podman.selinux_volume_mapping' => false]);

    expect($this->config->shouldUseSelinuxVolumeMapping())->toBeFalse();
});

it('defaults enabled to true', function () {
    expect($this->config->isEnabled())->toBeTrue();
});

it('disables when configured', function () {
    config(['podman.enabled' => false]);

    expect($this->config->isEnabled())->toBeFalse();
});

it('splits the configured comma-separated presets into an array', function () {
    config(['podman.presets' => 'production,proxy']);

    expect($this->config->defaultPresets())->toBe(['production', 'proxy']);
});

it('accepts the configured presets as a plain array', function () {
    config(['podman.presets' => ['production', 'proxy']]);

    expect($this->config->defaultPresets())->toBe(['production', 'proxy']);
});

it('trims whitespace and drops empty entries from the configured presets', function () {
    config(['podman.presets' => ' production ,, proxy ']);

    expect($this->config->defaultPresets())->toBe(['production', 'proxy']);
});

it('returns no default presets when none are configured', function () {
    config(['podman.presets' => '']);

    expect($this->config->defaultPresets())->toBe([]);
});

it('splits the configured comma-separated s3 buckets into an array', function () {
    config(['podman.s3_buckets' => 'local,conversions,secrets']);

    expect($this->config->s3Buckets())->toBe(['local', 'conversions', 'secrets']);
});

it('accepts the configured s3 buckets as a plain array', function () {
    config(['podman.s3_buckets' => ['local', 'conversions', 'secrets']]);

    expect($this->config->s3Buckets())->toBe(['local', 'conversions', 'secrets']);
});

it('returns no s3 buckets when none are configured', function () {
    config(['podman.s3_buckets' => '']);

    expect($this->config->s3Buckets())->toBe([]);
});

it('splits the configured comma-separated s3 cors buckets into an array', function () {
    config(['podman.s3_cors_buckets' => 'conversions,secrets']);

    expect($this->config->s3CorsBuckets())->toBe(['conversions', 'secrets']);
});

it('returns no s3 cors buckets when none are configured', function () {
    config(['podman.s3_cors_buckets' => '']);

    expect($this->config->s3CorsBuckets())->toBe([]);
});

it('renders the on-demand setting as a systemd boolean', function () {
    config(['podman.ondemand.enabled' => true]);

    expect($this->config->onDemand())->toBe('yes');

    config(['podman.ondemand.enabled' => false]);

    expect($this->config->onDemand())->toBe('no');
});

it('reads the port from the on-demand listen address', function (string $listen) {
    config(['podman.ondemand.listen' => $listen]);

    expect($this->config->onDemandListenPort())->toBe('9000');
})->with(['9000', '0.0.0.0:9000', '[::]:9000']);

it('uses the user service manager unless the units are installed as root', function () {
    config(['podman.quadlet_uid' => 1000]);

    expect($this->config->systemctl())->toBe('systemctl --user');

    config(['podman.quadlet_uid' => 0]);

    expect($this->config->systemctl())->toBe('systemctl');
});

it('defaults the on-demand worker to the queue worker without Horizon', function () {
    expect($this->config->onDemandWorker())->toBe('queue');
});

it('uses the configured on-demand worker', function () {
    config(['podman.ondemand.worker' => 'horizon']);

    expect($this->config->onDemandWorker())->toBe('horizon');
});
