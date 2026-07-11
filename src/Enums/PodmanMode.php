<?php

declare(strict_types=1);

namespace Foxws\Podman\Enums;

enum PodmanMode: string
{
    case Root = 'root';
    case Rootless = 'rootless';
}
