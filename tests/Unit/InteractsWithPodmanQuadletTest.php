<?php

declare(strict_types=1);

use Foxws\Podman\Concerns\InteractsWithPodmanQuadlet;
use Illuminate\Support\Facades\File;

uses(InteractsWithPodmanQuadlet::class);

it('resolves the current process uid and gid by default', function () {
    expect($this->getPodmanQuadletUid())->toBe(posix_getuid())
        ->and($this->getPodmanQuadletGid())->toBe(posix_getgid());
});

it('uses the configured uid and gid when set', function () {
    config(['podman.quadlet_uid' => 2000, 'podman.quadlet_gid' => 2001]);

    expect($this->getPodmanQuadletUid())->toBe(2000)
        ->and($this->getPodmanQuadletGid())->toBe(2001);
});

it('defaults the services path to the vendor quadlets directory', function () {
    expect($this->getPodmanQuadletServicesPath())
        ->toBe("{$this->getPodmanQuadletVendorPath()}/quadlets");
});

it('uses the configured services path when set', function () {
    $path = $this->makeQuadletServicesPath(['pgsql']);

    expect($this->getPodmanQuadletServicesPath())->toBe($path);

    File::deleteDirectory($path);
});

it('falls back to the vendor quadlets directory when the configured path does not exist', function () {
    config(['podman.quadlet_services_path' => sys_get_temp_dir().'/podman-quadlets-missing-'.uniqid()]);

    expect($this->getPodmanQuadletServicesPath())
        ->toBe("{$this->getPodmanQuadletVendorPath()}/quadlets");
});

it('defaults the container path to the project base path', function () {
    expect($this->getPodmanQuadletContainerPath())->toBe(base_path());
});

it('uses the configured container path when it exists', function () {
    $path = sys_get_temp_dir().'/podman-containers-'.uniqid();
    File::ensureDirectoryExists($path);
    config(['podman.quadlet_container_path' => $path]);

    expect($this->getPodmanQuadletContainerPath())->toBe($path);

    File::deleteDirectory($path);
});

it('falls back to the project base path when the configured container path does not exist', function () {
    config(['podman.quadlet_container_path' => sys_get_temp_dir().'/podman-containers-missing-'.uniqid()]);

    expect($this->getPodmanQuadletContainerPath())->toBe(base_path());
});

it('defaults the proxy prefix to "proxy"', function () {
    expect($this->getPodmanQuadletProxyPrefix())->toBe('proxy');
});

it('uses the configured proxy prefix when set', function () {
    config(['podman.quadlet_proxy_prefix' => 'Edge Proxy']);

    expect($this->getPodmanQuadletProxyPrefix())->toBe('edge-proxy');
});

it('defaults the proxy path to the project base path', function () {
    expect($this->getPodmanQuadletProxyPath())->toBe(base_path());
});

it('uses the configured proxy path when it exists', function () {
    $path = sys_get_temp_dir().'/podman-proxy-'.uniqid();
    File::ensureDirectoryExists($path);
    config(['podman.quadlet_proxy_path' => $path]);

    expect($this->getPodmanQuadletProxyPath())->toBe($path);

    File::deleteDirectory($path);
});

it('falls back to the project base path when the configured proxy path does not exist', function () {
    config(['podman.quadlet_proxy_path' => sys_get_temp_dir().'/podman-proxy-missing-'.uniqid()]);

    expect($this->getPodmanQuadletProxyPath())->toBe(base_path());
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

it('lists the available runtimes discovered in the vendor runtimes directory', function () {
    expect($this->getPodmanQuadletRuntimes())->toBe([
        'frankenphp-octane' => 'frankenphp-octane',
    ]);
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

    expect($source)->toEndWith('.quadlets');

    File::delete($source);
    File::deleteDirectory($path);
});

it('replaces the app-path placeholder with the application base path', function () {
    $path = $this->makeQuadletServicesPath();
    File::put("{$path}/app.quadlets", "SetWorkingDirectory={{base-path}}\nEnvironmentFile={{base-path}}/env\n");

    $source = $this->preparePodmanQuadletSource('app');

    expect(File::get($source))->toBe('SetWorkingDirectory='.base_path()."\nEnvironmentFile=".base_path()."/env\n");

    File::delete($source);
    File::deleteDirectory($path);
});

it('replaces the container-path placeholder with the configured container path', function () {
    $path = $this->makeQuadletServicesPath();
    $containerPath = sys_get_temp_dir().'/podman-containers-'.uniqid();
    File::ensureDirectoryExists($containerPath);
    config(['podman.quadlet_container_path' => $containerPath]);
    File::put("{$path}/app.quadlets", "File={{container-path}}/Containerfile\n");

    $source = $this->preparePodmanQuadletSource('app');

    expect(File::get($source))->toBe("File={$containerPath}/Containerfile\n");

    File::delete($source);
    File::deleteDirectory($path);
    File::deleteDirectory($containerPath);
});

it('replaces the proxy and proxy-path placeholders', function () {
    $path = $this->makeQuadletServicesPath();
    $proxyPath = sys_get_temp_dir().'/podman-proxy-'.uniqid();
    File::ensureDirectoryExists($proxyPath);
    config(['podman.quadlet_proxy_prefix' => 'edge', 'podman.quadlet_proxy_path' => $proxyPath]);
    File::put("{$path}/app.quadlets", "Network={{proxy}}.network\nVolume={{proxy-path}}:/etc/caddy:rw,Z,U\n");

    $source = $this->preparePodmanQuadletSource('app');

    expect(File::get($source))->toBe("Network=edge.network\nVolume={$proxyPath}:/etc/caddy:rw,Z,U\n");

    File::delete($source);
    File::deleteDirectory($path);
    File::deleteDirectory($proxyPath);
});

it('replaces the app-env, app-uid and app-gid placeholders', function () {
    $path = $this->makeQuadletServicesPath();
    config(['app.env' => 'testing']);
    File::put("{$path}/app.quadlets", "Environment=APP_ENV={{app-env}}\nEnvironment=UID={{app-uid}}\nEnvironment=GID={{app-gid}}\n");

    $source = $this->preparePodmanQuadletSource('app');

    expect(File::get($source))->toBe(
        "Environment=APP_ENV=testing\nEnvironment=UID={$this->getPodmanQuadletUid()}\nEnvironment=GID={$this->getPodmanQuadletGid()}\n",
    );

    File::delete($source);
    File::deleteDirectory($path);
});

it('strips selinux volume flags while preparing the quadlet source when disabled', function () {
    $path = $this->makeQuadletServicesPath();
    File::put("{$path}/pgsql.quadlets", "Volume={{application}}-pgsql:/var/lib/postgresql:rw,Z,U\n");
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

it('discovers the secrets required by a service, grouping shared secrets by target', function () {
    $path = $this->makeQuadletServicesPath(['pgsql']);
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
    $path = $this->makeQuadletServicesPath(['app']);
    File::put("{$path}/app.quadlets", "Secret={{application}}-env,target=/config/app.env,mode=0400\n");

    expect($this->getPodmanQuadletSecrets('app'))->toBe([
        'laravel-env' => ['type' => 'mount', 'targets' => ['/config/app.env']],
    ]);

    File::deleteDirectory($path);
});

it('returns no secrets for a service that does not define any', function () {
    $path = $this->makeQuadletServicesPath(['pgsql']);

    expect($this->getPodmanQuadletSecrets('pgsql'))->toBe([]);

    File::deleteDirectory($path);
});
