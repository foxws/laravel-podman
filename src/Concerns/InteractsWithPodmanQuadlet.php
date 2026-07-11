<?php

declare(strict_types=1);

namespace Foxws\Podman\Concerns;

use Illuminate\Support\Facades\Config;

trait InteractsWithPodmanQuadlet
{
    protected function getPodmanQuadletPath(): string
    {
        return Config::string('podman.quadlet_path');
    }
}
