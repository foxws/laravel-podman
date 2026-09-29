<?php

declare(strict_types=1);

namespace Foxws\Podman\Support\Idle\Checks;

use Foxws\Podman\Support\Idle\IdleCheck;
use Foxws\Podman\Support\Idle\IdleResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

/**
 * Busy while jobs are waiting, running or delayed. Checks the default
 * connection's queue and every Horizon supervisor's queues, unless given
 * a connection or queues.
 */
class QueueCheck extends IdleCheck
{
    protected ?string $connection = null;

    /**
     * @var array<int, string>
     */
    protected array $queues = [];

    public function name(): string
    {
        return 'queue';
    }

    public function isEnabled(): bool
    {
        $connection = $this->connection ?? Config::string('queue.default');

        return ! in_array(Config::get("queue.connections.{$connection}.driver"), [null, 'sync', 'null'], true);
    }

    public function connection(string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @param  array<int, string>  $queues
     */
    public function queues(array $queues): static
    {
        $this->queues = $queues;

        return $this;
    }

    public function run(): IdleResult
    {
        $busy = $this->watchedQueues()
            ->map(fn (array $queue): array => [...$queue, 'size' => Queue::connection($queue['connection'])->size($queue['queue'])])
            ->filter(fn (array $queue): bool => $queue['size'] > 0);

        if ($busy->isEmpty()) {
            return IdleResult::idle();
        }

        return IdleResult::busy($busy
            ->map(fn (array $queue): string => "{$queue['size']} jobs on {$queue['connection']}:{$queue['queue']}")
            ->implode(', '));
    }

    /**
     * @return Collection<int, array{connection: string, queue: string}>
     */
    protected function watchedQueues(): Collection
    {
        if ($this->connection !== null || $this->queues !== []) {
            $connection = $this->connection ?? Config::string('queue.default');

            return $this->queuesOn($connection, $this->queues !== [] ? $this->queues : [$this->defaultQueue($connection)]);
        }

        $connection = Config::string('queue.default');

        return $this->queuesOn($connection, [$this->defaultQueue($connection)])
            ->merge($this->horizonQueues())
            ->unique(fn (array $queue): string => "{$queue['connection']}:{$queue['queue']}")
            ->values();
    }

    protected function defaultQueue(string $connection): string
    {
        return (string) Config::get("queue.connections.{$connection}.queue", 'default');
    }

    /**
     * The queues Horizon's supervisors work on in this environment.
     *
     * @return Collection<int, array{connection: string, queue: string}>
     */
    protected function horizonQueues(): Collection
    {
        /** @var array<string, array<string, mixed>> $defaults */
        $defaults = Config::array('horizon.defaults', []);

        /** @var array<string, array<string, mixed>> $environment */
        $environment = Config::array('horizon.environments.'.app()->environment(), []);

        return Collection::make(array_replace_recursive($defaults, $environment))
            ->flatMap(fn (array $supervisor): Collection => $this->queuesOn(
                (string) ($supervisor['connection'] ?? 'redis'),
                Arr::wrap($supervisor['queue'] ?? 'default'),
            ));
    }

    /**
     * @param  array<int, mixed>  $queues
     * @return Collection<int, array{connection: string, queue: string}>
     */
    protected function queuesOn(string $connection, array $queues): Collection
    {
        return Collection::make($queues)
            ->flatMap(fn (mixed $queue): array => explode(',', (string) $queue))
            ->map(fn (string $queue): string => trim($queue))
            ->filter()
            ->map(fn (string $queue): array => ['connection' => $connection, 'queue' => $queue])
            ->values();
    }
}
