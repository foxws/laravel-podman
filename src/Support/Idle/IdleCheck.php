<?php

declare(strict_types=1);

namespace Foxws\Podman\Support\Idle;

/**
 * Decides whether a service has work left, so the idle check may stop it
 * while the app sleeps. Checks without services apply to every service.
 */
abstract class IdleCheck
{
    /**
     * @var array<int, string>
     */
    protected array $services = [];

    final public function __construct() {}

    public static function new(): static
    {
        return new static;
    }

    public function services(string ...$services): static
    {
        $this->services = array_values($services);

        return $this;
    }

    public function appliesTo(string $service): bool
    {
        return $this->services === [] || in_array($service, $this->services, true);
    }

    public function name(): string
    {
        return class_basename($this);
    }

    abstract public function run(): IdleResult;
}
