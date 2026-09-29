<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Foxws\Podman\Support\Idle\IdleCheck;
use Foxws\Podman\Support\Idle\PodmanIdle;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'podman:idle')]
class IdleCommand extends Command
{
    public $signature = 'podman:idle
        {--service=* : The services to check, e.g. "horizon" or "my-app-horizon" (default: every registered check)}
    ';

    public $description = 'Exit successfully when the given services have no work left, so the idle check can stop them while the app sleeps.';

    public function handle(PodmanIdle $idle): int
    {
        $busy = $this->checks($idle)
            ->map(fn (IdleCheck $check): array => [$check, $check->run()])
            ->reject(fn (array $result): bool => $result[1]->isIdle);

        foreach ($busy as [$check, $result]) {
            $this->line("{$check->name()}: {$result->message}");
        }

        return $busy->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Collection<int, IdleCheck>
     */
    protected function checks(PodmanIdle $idle): Collection
    {
        /** @var array<int, string> $services */
        $services = (array) $this->option('service');

        if ($services === []) {
            return Collection::make($idle->registeredChecks());
        }

        return Collection::make($services)
            ->flatMap(fn (string $service): array => $idle->checksFor($service))
            ->unique(fn (IdleCheck $check): int => spl_object_id($check))
            ->values();
    }
}
