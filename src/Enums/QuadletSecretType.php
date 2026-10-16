<?php

declare(strict_types=1);

namespace Foxws\Podman\Enums;

/**
 * How a container gets a Podman secret ("Secret=…,type="): as a file mounted
 * into it, or as an environment variable.
 */
enum QuadletSecretType: string
{
    case Mount = 'mount';
    case Env = 'env';
}
