<?php

declare(strict_types=1);

namespace Foxws\Podman\Support\Idle;

use Foxws\Podman\Support\PodmanConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * The idle checks "podman:idle" runs. Register your own from a service
 * provider; without them, the "idle.checks" config map applies.
 */
class PodmanIdle
{
    /**
     * @var array<int, IdleCheck>
     */
    protected array $checks = [];

    public function __construct(
        protected PodmanConfig $config,
    ) {}

    /**
     * @param  array<int, IdleCheck>  $checks
     */
    public function checks(array $checks): static
    {
        $this->checks = [...$this->checks, ...$checks];

        return $this;
    }

    /**
     * @return array<int, IdleCheck>
     */
    public function registeredChecks(): array
    {
        return $this->checks !== [] ? $this->checks : $this->configuredChecks();
    }

    /**
     * @return array<int, IdleCheck>
     */
    public function checksFor(string $service): array
    {
        $service = $this->serviceName($service);

        return array_values(array_filter(
            $this->registeredChecks(),
            fn (IdleCheck $check): bool => $check->appliesTo($service),
        ));
    }

    /**
     * A Quadlet service name without the application prefix, so
     * "my-app-horizon" and "horizon" both match checks for "horizon".
     */
    public function serviceName(string $service): string
    {
        return Str::replaceStart("{$this->config->prefix()}-", '', $service);
    }

    /**
     * @return array<int, IdleCheck>
     */
    protected function configuredChecks(): array
    {
        /** @var array<string, array<int, class-string<IdleCheck>>> $services */
        $services = Config::array('podman.idle.checks', []);

        return Collection::make($services)
            ->flatMap(fn (array $checks, string $service): array => array_map(
                fn (string $check): IdleCheck => $check::new()->services($service),
                $checks,
            ))
            ->values()
            ->all();
    }
}
