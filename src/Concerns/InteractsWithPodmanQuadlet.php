<?php

declare(strict_types=1);

namespace Foxws\Podman\Concerns;

use Composer\InstalledVersions;
use Foxws\Podman\Enums\PodmanMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Process\Process;

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
        $command[] = $this->getPodmanQuadletPath();

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

    protected function preparePodmanQuadletSource(string $service): string
    {
        $source = "{$this->getPodmanQuadletServicesPath()}/{$service}.quadlets";

        $contents = Str::replace(
            'stub',
            $this->getPodmanQuadletPrefix(),
            File::get($source),
        );

        if (! $this->shouldUseSelinuxVolumeMapping()) {
            $contents = $this->removeSelinuxVolumeFlags($contents);
        }

        $path = "{$this->getPodmanQuadletTemporaryPath()}/quadlet-".Str::random(16);

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

    protected function getPodmanQuadletTemporaryPath(): string
    {
        return Config::string('podman.temporary_path');
    }

    protected function getPodmanQuadletVendorPath(): string
    {
        return Str::rtrim(
            InstalledVersions::getInstallPath('foxws/laravel-podman'),
            '/',
        );
    }

    protected function getPodmanQuadletPath(): string
    {
        return match ($this->getPodmanQuadletMode()) {
            PodmanMode::Root => Config::string('podman.quadlet_root_path'),
            PodmanMode::Rootless => Config::string('podman.quadlet_rootless_path'),
        };
    }

    protected function getPodmanQuadletMode(): PodmanMode
    {
        return PodmanMode::from(Config::string('podman.quadlet_mode'));
    }

    protected function getPodmanQuadletServicesPath(): string
    {
        return Config::get('podman.quadlet_services_path')
            ?: "{$this->getPodmanQuadletVendorPath()}/quadlets";
    }

    protected function getPodmanQuadletPrefix(): string
    {
        return Config::string('podman.quadlet_prefix');
    }

    protected function shouldReloadSystemd(): bool
    {
        return Config::boolean('podman.reload_systemd');
    }

    protected function shouldUseSelinuxVolumeMapping(): bool
    {
        return Config::boolean('podman.selinux_volume_mapping');
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
}
