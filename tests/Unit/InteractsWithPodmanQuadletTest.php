<?php

declare(strict_types=1);

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Support\Facades\File;

uses(InteractsWithPodmanQuadlet::class);

it('lists the available quadlet services discovered in the quadlets path', function () {
    $path = $this->makeQuadletsPath(['pgsql', 'mariadb']);
    File::put("{$path}/README.md", 'not a service');

    expect($this->getPodmanQuadlets())->toBe([
        'mariadb' => 'mariadb',
        'pgsql' => 'pgsql',
    ]);

    File::deleteDirectory($path);
});

it('lists the available runtimes discovered in the vendor runtimes directory', function () {
    expect($this->getPodmanQuadletRuntimes())->toBe([
        'frankenphp-octane' => 'frankenphp-octane',
        'proxy' => 'proxy',
    ]);
});

it('returns the configured list of default services', function () {
    config(['podman.services' => 'valkey,app']);

    expect($this->getPodmanQuadletDefaultServices())->toBe(['valkey', 'app']);
});

it('returns the configured list of default runtimes', function () {
    config(['podman.runtimes' => 'proxy,frankenphp-octane']);

    expect($this->getPodmanQuadletDefaultRuntimes())->toBe(['proxy', 'frankenphp-octane']);
});

it('resolves the configured runtime path against the base path', function () {
    config(['podman.runtime_path' => 'runtimes']);

    expect($this->getPodmanRuntimePath())->toBe(base_path('runtimes'));
});

it('discovers the secrets required by a service, grouping shared secrets by target', function () {
    $path = $this->makeQuadletsPath(['pgsql']);
    File::put(
        "{$path}/pgsql.quadlets",
        "Secret={{application}}-pgsql-db,type=env,target=POSTGRES_DB\n".
        "Secret={{application}}-pgsql-password,type=env,target=POSTGRES_PASSWORD\n".
        "Secret={{application}}-pgsql-password,type=env,target=PGPASSWORD\n",
    );

    expect($this->getPodmanQuadletSecrets('pgsql'))->toBe([
        'laravel-pgsql-db' => ['type' => 'env', 'targets' => ['POSTGRES_DB']],
        'laravel-pgsql-password' => ['type' => 'env', 'targets' => ['POSTGRES_PASSWORD', 'PGPASSWORD']],
    ]);

    File::deleteDirectory($path);
});

it('defaults a secret to the mount type when type is not specified', function () {
    $path = $this->makeQuadletsPath(['app']);
    File::put("{$path}/app.quadlets", "Secret={{application}}-env,target=/config/app.env,mode=0400\n");

    expect($this->getPodmanQuadletSecrets('app'))->toBe([
        'laravel-env' => ['type' => 'mount', 'targets' => ['/config/app.env']],
    ]);

    File::deleteDirectory($path);
});

it('returns no secrets for a service that does not define any', function () {
    $path = $this->makeQuadletsPath(['pgsql']);

    expect($this->getPodmanQuadletSecrets('pgsql'))->toBe([]);

    File::deleteDirectory($path);
});

it('builds the install quadlet process command', function () {
    $path = $this->makeQuadletsPath(['pgsql']);

    $process = $this->installPodmanQuadlet(service: 'pgsql', application: 'my-app', replace: true);

    expect($process->getCommandLine())
        ->toContain("'podman' 'quadlet' 'install'")
        ->toContain("'--application' 'my-app'")
        ->toContain("'--replace'");

    File::deleteDirectory($path);
});

it('omits reload-systemd flag by default when installing', function () {
    $path = $this->makeQuadletsPath(['pgsql']);

    $process = $this->installPodmanQuadlet(service: 'pgsql');

    expect($process->getCommandLine())->not->toContain('--reload-systemd');

    File::deleteDirectory($path);
});

it('appends reload-systemd=false when reloading systemd is disabled', function () {
    $path = $this->makeQuadletsPath(['pgsql']);
    config(['podman.reload_systemd' => false]);

    $process = $this->installPodmanQuadlet(service: 'pgsql');

    expect($process->getCommandLine())->toContain("'--reload-systemd=false'");

    File::deleteDirectory($path);
});

it('materializes the install source inside a dedicated temporary directory', function () {
    $path = $this->makeQuadletsPath(['pgsql']);

    $this->installPodmanQuadlet(service: 'pgsql');
    $temporaryDirectory = $this->podmanQuadletTemporaryDirectory();

    expect(File::exists($temporaryDirectory->path('pgsql.quadlets')))->toBeTrue()
        ->and($temporaryDirectory->path())->not->toBe($path);

    File::deleteDirectory($path);
});

it('deletes the temporary directory once it is no longer referenced', function () {
    $path = $this->makeQuadletsPath(['pgsql']);

    $this->installPodmanQuadlet(service: 'pgsql');
    $location = $this->podmanQuadletTemporaryDirectory()->path();

    $this->podmanQuadletTemporaryDirectory = null;
    gc_collect_cycles();

    expect(File::isDirectory($location))->toBeFalse();

    File::deleteDirectory($path);
});

it('installs multiple services, returning no failures on success', function () {
    $path = $this->makeQuadletsPath(['pgsql', 'valkey']);
    $this->useFakePodmanBinary(0);

    expect($this->installPodmanQuadlets(['pgsql', 'valkey']))->toBe([]);

    File::deleteDirectory($path);
});

it('reports the services that failed to install while continuing with the rest', function () {
    $path = $this->makeQuadletsPath(['pgsql', 'valkey']);
    $this->useFakePodmanBinary(1);

    expect($this->installPodmanQuadlets(['pgsql', 'valkey']))->toBe(['pgsql', 'valkey']);

    File::deleteDirectory($path);
});

it('publishes multiple runtimes, returning no failures on success', function () {
    $target = base_path('runtimes');

    expect($this->publishPodmanRuntimes(['frankenphp-octane', 'proxy']))->toBe([]);

    expect(File::exists("{$target}/frankenphp-octane/Containerfile"))->toBeTrue()
        ->and(File::exists("{$target}/proxy/Caddyfile"))->toBeTrue();

    File::deleteDirectory($target);
});

it('reports the runtimes that failed to publish while continuing with the rest', function () {
    $target = base_path('runtimes');
    File::ensureDirectoryExists("{$target}/frankenphp-octane");
    File::put("{$target}/frankenphp-octane/Containerfile", 'existing');

    expect($this->publishPodmanRuntimes(['frankenphp-octane', 'proxy']))->toBe(['frankenphp-octane']);

    expect(File::get("{$target}/frankenphp-octane/Containerfile"))->toBe('existing')
        ->and(File::exists("{$target}/proxy/Caddyfile"))->toBeTrue();

    File::deleteDirectory($target);
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

it('builds the set secret process command', function () {
    $process = $this->setPodmanSecret(secret: 'laravel-pgsql-db', value: 'myapp', replace: true);

    expect($process->getCommandLine())
        ->toContain("'podman' 'secret' 'create' 'laravel-pgsql-db' '-'")
        ->toContain("'--replace'");

    expect($process->getInput())->toBe('myapp');
});

it('omits the replace flag by default when setting a secret', function () {
    $process = $this->setPodmanSecret(secret: 'laravel-pgsql-db', value: 'myapp');

    expect($process->getCommandLine())->not->toContain('--replace');
});
