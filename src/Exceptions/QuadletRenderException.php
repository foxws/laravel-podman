<?php

declare(strict_types=1);

namespace Foxws\Podman\Exceptions;

use RuntimeException;

class QuadletRenderException extends RuntimeException
{
    public static function volumeFlagsNotRemoved(string $error): self
    {
        return new self("Removing SELinux volume flags failed: {$error}");
    }
}
