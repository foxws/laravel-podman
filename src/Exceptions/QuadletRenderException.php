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

    public static function invalidUnit(string $source, string $reason): self
    {
        return new self("{$source} is not a valid .quadlets file: {$reason}.");
    }

    public static function invalidIdleWorker(string $worker): self
    {
        return new self("The idle worker \"{$worker}\" may only contain letters, digits, dashes and underscores.");
    }
}
