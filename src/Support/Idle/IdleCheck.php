<?php

declare(strict_types=1);

namespace Foxws\Podman\Support\Idle;

/**
 * Decides whether a part of the app, like its queue or database, still has
 * work in progress, so the idle check may stop the workers while the app
 * sleeps.
 */
abstract class IdleCheck
{
    final public function __construct() {}

    public static function new(): static
    {
        return new static;
    }

    /**
     * The name to select this check with, e.g. "podman:idle --services=queue".
     */
    abstract public function name(): string;

    /**
     * Whether the app uses what this check looks at, going by its config
     * (e.g. QUEUE_CONNECTION). "podman:idle" skips unused checks.
     */
    public function isEnabled(): bool
    {
        return true;
    }

    abstract public function run(): IdleResult;
}
