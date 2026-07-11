<?php

declare(strict_types=1);

namespace Foxws\Podman\Concerns;

use Composer\InstalledVersions;
use Foxws\Podman\Enums\PodmanService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

trait InteractsWithPodmanQuadlet
{
    protected function installPodmanQuadlet(
        string $service,
        ?string $application = null,
        ?bool $replace = null,
    ): Process {
        $command = ["podman", "quadlet", "install"];

        if ($application) {
            $command[] = "--application";
            $command[] = $application;
        }

        if ($replace) {
            $command[] = "--replace";
        }

        $command[] = $this->preparePodmanQuadletSource($service);
        $command[] = $this->getPodmanQuadletPath();

        return new Process($command);
    }

    protected function preparePodmanQuadletSource(string $service): string
    {
        $source = "{$this->getPodmanQuadletVendorPath()}/quadlets/{$service}.quadlets";

        $contents = Str::replace(
            "stub",
            $this->getPodmanQuadletPrefix(),
            File::get($source),
        );

        if (! $this->shouldUseSelinuxVolumeMapping()) {
            $contents = $this->removeSelinuxVolumeFlags($contents);
        }

        $path = "{$this->getPodmanQuadletTemporaryPath()}/quadlet-".Str::random(16);

        File::put($path, $contents);

        register_shutdown_function(static fn () => File::exists($path) && File::delete($path));

        return $path;
    }

    protected function removeSelinuxVolumeFlags(string $contents): string
    {
        return preg_replace_callback(
            '/^Volume=(.*)$/m',
            function (array $matches): string {
                $segments = explode(":", $matches[1]);

                if (count($segments) < 3) {
                    return "Volume={$matches[1]}";
                }

                $options = array_diff(
                    explode(",", $segments[2]),
                    ["Z", "z", "U"],
                );

                if ($options === []) {
                    unset($segments[2]);
                } else {
                    $segments[2] = implode(",", $options);
                }

                return "Volume=".implode(":", $segments);
            },
            $contents,
        );
    }

    protected function getPodmanQuadletTemporaryPath(): string
    {
        return Config::string("podman.temporary_path");
    }

    protected function getPodmanQuadletVendorPath(): string
    {
        return rtrim(
            InstalledVersions::getInstallPath("foxws/laravel-podman"),
            "/",
        );
    }

    protected function getPodmanQuadletPath(): string
    {
        return Config::string("podman.quadlet_path");
    }

    protected function getPodmanQuadletPrefix(): string
    {
        return Config::string("podman.quadlet_prefix");
    }

    protected function shouldReloadSystemd(): bool
    {
        return Config::boolean("podman.reload_systemd");
    }

    protected function shouldUseSelinuxVolumeMapping(): bool
    {
        return Config::boolean("podman.selinux_volume_mapping");
    }

    protected function getPodmanQuadletServices(): array
    {
        return Collection::make(PodmanService::cases())
            ->pluck("name", "value")
            ->toArray();
    }
}
