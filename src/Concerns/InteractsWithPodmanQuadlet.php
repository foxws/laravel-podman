<?php

declare(strict_types=1);

namespace Foxws\Podman\Concerns;

use Foxws\Podman\Enums\PodmanService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
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

        $command[] = "{$service}.quadlets";

        return new Process($command);
    }

    protected function getPodmanQuadletPath(): string
    {
        return Config::string('podman.quadlet_path');
    }

    protected function shouldReloadSystemd(): bool
    {
        return Config::boolean('podman.reload_systemd');
    }

    protected function getPodmanQuadletServices(): array
    {
        return Collection::make(PodmanService::cases())
            ->pluck('name', 'value')
            ->toArray();
    }
}
