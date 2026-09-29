<?php

declare(strict_types=1);

namespace Foxws\Podman\Support\Idle;

class IdleResult
{
    public function __construct(
        public readonly bool $isIdle,
        public readonly string $message = '',
    ) {}

    public static function idle(string $message = ''): self
    {
        return new self(true, $message);
    }

    public static function busy(string $message): self
    {
        return new self(false, $message);
    }
}
