<?php

declare(strict_types=1);

namespace Foxws\Podman\Support\Idle;

use Illuminate\Support\Facades\Config;

/**
 * The idle checks "podman:idle" runs. Register your own from a service
 * provider; without them, the "idle.checks" config applies.
 */
class PodmanIdle
{
    /**
     * @var array<int, IdleCheck>
     */
    protected array $checks = [];

    /**
     * @param  array<int, IdleCheck>  $checks
     */
    public function checks(array $checks): static
    {
        $this->checks = [...$this->checks, ...$checks];

        return $this;
    }

    /**
     * @return array<string, IdleCheck>
     */
    public function registeredChecks(): array
    {
        $checks = $this->checks !== [] ? $this->checks : $this->configuredChecks();

        $named = [];

        foreach ($checks as $check) {
            $named[$check->name()] = $check;
        }

        return $named;
    }

    /**
     * The checks for what the app uses, going by its config.
     *
     * @return array<string, IdleCheck>
     */
    public function enabledChecks(): array
    {
        return array_filter($this->registeredChecks(), fn (IdleCheck $check): bool => $check->isEnabled());
    }

    /**
     * @return array<int, IdleCheck>
     */
    protected function configuredChecks(): array
    {
        /** @var array<int, class-string<IdleCheck>> $checks */
        $checks = Config::array('podman.idle.checks', []);

        return array_map(fn (string $check): IdleCheck => $check::new(), $checks);
    }
}
