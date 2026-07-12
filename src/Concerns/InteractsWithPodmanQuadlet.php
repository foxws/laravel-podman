<?php

declare(strict_types=1);

namespace Foxws\Podman\Concerns;

use Composer\InstalledVersions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

trait InteractsWithPodmanQuadlet
{
    protected function installPodmanQuadlet(
        string $service,
        ?string $application = null,
        ?bool $replace = null,
    ): Process {
        $command = ['podman', 'quadlet', 'install'];

        if ($application) {
            $command[] = '--application';
            $command[] = $application;
        }

        if ($replace) {
            $command[] = '--replace';
        }

        if (! $this->shouldReloadSystemd()) {
            $command[] = '--reload-systemd=false';
        }

        $command[] = $this->preparePodmanQuadletSource($service);

        return new Process($command);
    }

    protected function removePodmanQuadlet(
        string $service,
        ?bool $ignore = null,
        ?bool $force = null,
    ): Process {
        $command = ['podman', 'quadlet', 'rm', $service];

        if ($ignore) {
            $command[] = '--ignore';
        }

        if ($force) {
            $command[] = '--force';
        }

        if (! $this->shouldReloadSystemd()) {
            $command[] = '--reload-systemd=false';
        }

        return new Process($command);
    }

    protected function listPodmanQuadlet(
        ?string $filter = null,
        ?string $format = null,
        ?bool $noheading = null,
    ): Process {
        $command = ['podman', 'quadlet', 'list'];

        if ($filter) {
            $command[] = '--filter';
            $command[] = $filter;
        }

        if ($format) {
            $command[] = '--format';
            $command[] = $format;
        }

        if ($noheading) {
            $command[] = '--noheading';
        }

        return new Process($command);
    }

    protected function printPodmanQuadlet(
        string $service,
    ): Process {
        $command = ['podman', 'quadlet', 'print', $service];

        return new Process($command);
    }

    protected function uninstallPodmanQuadlet(
        string $application,
        ?bool $force = null,
    ): Process {
        $command = ['podman', 'quadlet', 'rm', '--recursive', $application];

        if ($force) {
            $command[] = '--force';
        }

        if (! $this->shouldReloadSystemd()) {
            $command[] = '--reload-systemd=false';
        }

        return new Process($command);
    }

    protected function setPodmanSecret(
        string $secret,
        string $value,
        ?bool $replace = null,
    ): Process {
        $command = ['podman', 'secret', 'create', $secret, '-'];

        if ($replace) {
            $command[] = '--replace';
        }

        $process = new Process($command);
        $process->setInput($value);

        return $process;
    }

    protected function promptForPodmanQuadletSecrets(string $service, ?bool $replace = null): bool
    {
        foreach ($this->getPodmanQuadletSecrets($service) as $secret => $definition) {
            if ($definition['type'] === 'mount') {
                $path = text(
                    label: "Enter the file path for {$secret} (".implode(', ', $definition['targets']).')',
                    default: base_path('.env'),
                    required: true,
                );

                if (! File::exists($path)) {
                    error("The file {$path} does not exist.");

                    return false;
                }

                $value = File::get($path);
            } else {
                $value = password(
                    label: "Enter the value for {$secret} (".implode(', ', $definition['targets']).')',
                    required: true,
                );
            }

            $process = $this->setPodmanSecret(
                secret: $secret,
                value: $value,
                replace: $replace,
            );

            $process->run();

            if (! $process->isSuccessful()) {
                error($process->getErrorOutput());

                return false;
            }
        }

        return true;
    }

    protected function getPodmanQuadletSubstitutions(): array
    {
        return [
            '{{app-env}}' => Config::string('app.env'),
            '{{app-uid}}' => (string) $this->getPodmanQuadletUid(),
            '{{app-gid}}' => (string) $this->getPodmanQuadletGid(),
            '{{application}}' => $this->getPodmanQuadletPrefix(),
            '{{base-path}}' => base_path(),
            '{{container-path}}' => $this->getPodmanQuadletContainerPath(),
            '{{proxy}}' => $this->getPodmanQuadletProxyPrefix(),
            '{{proxy-path}}' => $this->getPodmanQuadletProxyPath(),
            '{{site-address}}' => $this->getPodmanQuadletSiteAddress(),
        ];
    }

    protected function publishPodmanQuadletDirectory(string $source, string $target): void
    {
        File::ensureDirectoryExists($target);

        foreach (File::allFiles($source) as $file) {
            $destination = "{$target}/{$file->getRelativePathname()}";

            File::ensureDirectoryExists(dirname($destination));

            File::put($destination, strtr(File::get($file->getPathname()), $this->getPodmanQuadletSubstitutions()));
        }
    }

    protected function publishPodmanQuadletRuntime(string $runtime, ?bool $force = null): bool
    {
        $source = "{$this->getPodmanQuadletVendorPath()}/runtimes/{$runtime}";
        $target = $this->getPodmanQuadletContainerPublishPath();

        if (File::exists("{$target}/Containerfile") && ! $force) {
            error("A Containerfile already exists at {$target}. Use --force to overwrite.");

            return false;
        }

        File::ensureDirectoryExists("{$target}/runtimes");
        File::copy("{$source}/Containerfile", "{$target}/Containerfile");

        foreach (File::files($source) as $file) {
            if ($file->getFilename() === 'Containerfile') {
                continue;
            }

            File::copy($file->getPathname(), "{$target}/runtimes/{$file->getFilename()}");
        }

        return true;
    }

    protected function publishPodmanQuadletProxy(?bool $force = null): bool
    {
        $source = $this->getPodmanQuadletProxyVendorPath();
        $target = $this->getPodmanQuadletProxyPublishPath();

        if (File::exists("{$target}/Caddyfile") && ! $force) {
            error("A Caddyfile already exists at {$target}. Use --force to overwrite.");

            return false;
        }

        $this->publishPodmanQuadletDirectory($source, $target);

        return true;
    }

    protected function preparePodmanQuadletSource(string $service): string
    {
        $source = "{$this->getPodmanQuadletServicesPath()}/{$service}.quadlets";

        $contents = strtr(File::get($source), $this->getPodmanQuadletSubstitutions());

        if (! $this->shouldUseSelinuxVolumeMapping()) {
            $contents = $this->removeSelinuxVolumeFlags($contents);
        }

        $path = "{$this->getPodmanQuadletTemporaryPath()}/".Str::random(16).'.quadlets';

        File::put($path, $contents);

        register_shutdown_function(static fn () => is_file($path) && unlink($path));

        return $path;
    }

    protected function removeSelinuxVolumeFlags(string $contents): string
    {
        return preg_replace_callback(
            '/^Volume=(.*)$/m',
            function (array $matches): string {
                $segments = Str::of($matches[1])->explode(':');

                if ($segments->count() < 3) {
                    return "Volume={$matches[1]}";
                }

                $options = Str::of($segments->get(2))
                    ->explode(',')
                    ->diff(['Z', 'z', 'U']);

                $segments = $options->isEmpty()
                    ? $segments->take(2)
                    : $segments->put(2, $options->implode(','));

                return 'Volume='.$segments->implode(':');
            },
            $contents,
        );
    }

    protected function getPodmanQuadletVendorPath(): string
    {
        return Str::rtrim(
            InstalledVersions::getInstallPath('foxws/laravel-podman'),
            '/',
        );
    }

    protected function getPodmanQuadletServicesPath(): string
    {
        $path = Config::get('podman.quadlet_services_path');

        if ($path && File::isDirectory($path)) {
            return $path;
        }

        return "{$this->getPodmanQuadletVendorPath()}/quadlets";
    }

    protected function getPodmanQuadletContainerPath(): string
    {
        $path = Config::get('podman.quadlet_container_path');

        if ($path && File::isDirectory($path)) {
            return $path;
        }

        return base_path();
    }

    protected function getPodmanQuadletProxyPath(): string
    {
        $path = Config::get('podman.quadlet_proxy_path');

        if ($path && File::isDirectory($path)) {
            return $path;
        }

        return base_path();
    }

    protected function getPodmanQuadletContainerPublishPath(): string
    {
        return Config::get('podman.quadlet_container_path') ?: base_path();
    }

    protected function getPodmanQuadletProxyPublishPath(): string
    {
        return Config::get('podman.quadlet_proxy_path') ?: base_path();
    }

    protected function getPodmanQuadletProxyPrefix(): string
    {
        return Str::kebab(Config::string('podman.quadlet_proxy_prefix'));
    }

    protected function getPodmanQuadletSiteAddress(): string
    {
        return Config::string('podman.quadlet_site_address');
    }

    protected function getPodmanQuadletProxyVendorPath(): string
    {
        return "{$this->getPodmanQuadletVendorPath()}/runtimes/proxy";
    }

    protected function getPodmanQuadletServices(): array
    {
        return Collection::make(File::files($this->getPodmanQuadletServicesPath()))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'quadlets')
            ->map(fn (SplFileInfo $file): string => $file->getBasename('.'.$file->getExtension()))
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $service): array => [$service => $service])
            ->toArray();
    }

    protected function getPodmanQuadletRuntimes(): array
    {
        return Collection::make(File::directories("{$this->getPodmanQuadletVendorPath()}/runtimes"))
            ->map(fn (string $path): string => basename($path))
            ->reject(fn (string $runtime): bool => $runtime === 'proxy')
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $runtime): array => [$runtime => $runtime])
            ->toArray();
    }

    protected function getPodmanQuadletSecrets(string $service): array
    {
        $source = $this->preparePodmanQuadletSource($service);

        $secrets = [];

        foreach (explode("\n", File::get($source)) as $line) {
            if (! Str::startsWith($line, 'Secret=')) {
                continue;
            }

            $options = explode(',', Str::after($line, 'Secret='));
            $name = array_shift($options);
            $type = 'mount';
            $target = $name;

            foreach ($options as $option) {
                if (Str::startsWith($option, 'type=')) {
                    $type = Str::after($option, 'type=');
                }

                if (Str::startsWith($option, 'target=')) {
                    $target = Str::after($option, 'target=');
                }
            }

            $secrets[$name]['type'] = $type;
            $secrets[$name]['targets'][] = $target;
        }

        return $secrets;
    }

    protected function getPodmanQuadletTemporaryPath(): string
    {
        $path = Config::get('podman.temporary_path');

        if ($path && File::isDirectory($path)) {
            return $path;
        }

        return sys_get_temp_dir();
    }

    protected function getPodmanQuadletPrefix(): string
    {
        return Str::kebab(Config::string('podman.quadlet_prefix'));
    }

    protected function getPodmanQuadletUid(): int
    {
        $uid = Config::get('podman.quadlet_uid');

        if ($uid !== null) {
            return (int) $uid;
        }

        return function_exists('posix_getuid') ? posix_getuid() : 1000;
    }

    protected function getPodmanQuadletGid(): int
    {
        $gid = Config::get('podman.quadlet_gid');

        if ($gid !== null) {
            return (int) $gid;
        }

        return function_exists('posix_getgid') ? posix_getgid() : 1000;
    }

    protected function shouldReloadSystemd(): bool
    {
        return Config::boolean('podman.reload_systemd');
    }

    protected function shouldUseSelinuxVolumeMapping(): bool
    {
        return Config::boolean('podman.selinux_volume_mapping');
    }
}
