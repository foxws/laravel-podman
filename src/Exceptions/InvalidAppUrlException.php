<?php

declare(strict_types=1);

namespace Foxws\Podman\Exceptions;

use RuntimeException;

class InvalidAppUrlException extends RuntimeException
{
    public static function missingHost(string $url): self
    {
        return new self("APP_URL must be a full URL with a scheme and host, like https://example.test, but is \"{$url}\".");
    }
}
