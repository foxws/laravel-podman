<?php

declare(strict_types=1);

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Support\Facades\File;

uses(InteractsWithPodmanQuadlet::class);

it('defaults the services path to the vendor quadlets directory', function () {
    expect($this->getPodmanQuadletServicesPath())
        ->toBe("{$this->getPodmanQuadletVendorPath()}/quadlets");
});

it('uses the configured services path when set', function () {
    $path = $this->makeQuadletServicesPath(['pgsql']);

    expect($this->getPodmanQuadletServicesPath())->toBe($path);

    File::deleteDirectory($path);
});

it('lists the available services discovered in the services path', function () {
    $path = $this->makeQuadletServicesPath(['pgsql', 'mariadb']);
    File::put("{$path}/README.md", 'not a service');

    expect($this->getPodmanQuadletServices())->toBe([
        'mariadb' => 'mariadb',
        'pgsql' => 'pgsql',
    ]);

    File::deleteDirectory($path);
});

it('removes selinux volume flags from quadlet contents', function () {
    $contents = "Volume=stub-pgsql:/var/lib/postgresql:rw,Z,U\nOther=value";

    expect($this->removeSelinuxVolumeFlags($contents))
        ->toBe("Volume=stub-pgsql:/var/lib/postgresql:rw\nOther=value");
});

it('keeps volume entries without selinux flags untouched', function () {
    $contents = 'Volume=stub-pgsql:/var/lib/postgresql';

    expect($this->removeSelinuxVolumeFlags($contents))->toBe($contents);
});

it('prepares a quadlet source file with the prefix replaced', function () {
    $path = $this->makeQuadletServicesPath(['pgsql']);
    config(['podman.quadlet_prefix' => 'acme']);

    $source = $this->preparePodmanQuadletSource('pgsql');

    expect(File::get($source))
        ->toContain('acme-pgsql')
        ->not->toContain('stub-pgsql');

    File::delete($source);
    File::deleteDirectory($path);
});

it('strips selinux volume flags while preparing the quadlet source when disabled', function () {
    $path = $this->makeQuadletServicesPath();
    File::put("{$path}/pgsql.quadlets", "Volume=stub-pgsql:/var/lib/postgresql:rw,Z,U\n");
    config(['podman.selinux_volume_mapping' => false]);

    $source = $this->preparePodmanQuadletSource('pgsql');

    expect(File::get($source))->toBe("Volume=laravel-pgsql:/var/lib/postgresql:rw\n");

    File::delete($source);
    File::deleteDirectory($path);
});

it('builds the install quadlet process command', function () {
    $path = $this->makeQuadletServicesPath(['pgsql']);

    $process = $this->installPodmanQuadlet(service: 'pgsql', application: 'my-app', replace: true);

    expect($process->getCommandLine())
        ->toContain("'podman' 'quadlet' 'install'")
        ->toContain("'--application' 'my-app'")
        ->toContain("'--replace'");

    File::deleteDirectory($path);
});

it('omits reload-systemd flag by default when installing', function () {
    $path = $this->makeQuadletServicesPath(['pgsql']);

    $process = $this->installPodmanQuadlet(service: 'pgsql');

    expect($process->getCommandLine())->not->toContain('--reload-systemd');

    File::deleteDirectory($path);
});

it('appends reload-systemd=false when reloading systemd is disabled', function () {
    $path = $this->makeQuadletServicesPath(['pgsql']);
    config(['podman.reload_systemd' => false]);

    $process = $this->installPodmanQuadlet(service: 'pgsql');

    expect($process->getCommandLine())->toContain("'--reload-systemd=false'");

    File::deleteDirectory($path);
});

it('builds the remove quadlet process command', function () {
    $process = $this->removePodmanQuadlet(service: 'pgsql', ignore: true, force: true);

    expect($process->getCommandLine())
        ->toContain("'podman' 'quadlet' 'rm' 'pgsql'")
        ->toContain("'--ignore'")
        ->toContain("'--force'");
});

it('builds the uninstall quadlet process command', function () {
    $process = $this->uninstallPodmanQuadlet(application: 'my-app', force: true);

    expect($process->getCommandLine())
        ->toContain("'podman' 'quadlet' 'rm' '--recursive' 'my-app'")
        ->toContain("'--force'");
});

it('builds the list quadlet process command', function () {
    $process = $this->listPodmanQuadlet(filter: 'status=running', format: 'json', noheading: true);

    expect($process->getCommandLine())
        ->toContain("'podman' 'quadlet' 'list'")
        ->toContain("'--filter' 'status=running'")
        ->toContain("'--format' 'json'")
        ->toContain("'--noheading'");
});

it('builds the print quadlet process command', function () {
    $process = $this->printPodmanQuadlet(service: 'pgsql');

    expect($process->getCommandLine())->toBe("'podman' 'quadlet' 'print' 'pgsql'");
});
