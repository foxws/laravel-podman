<?php

declare(strict_types=1);

namespace Foxws\Podman\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'podman:idle')]
class IdleCommand extends Command
{
    public $signature = 'podman:idle
        {--connection= : The queue connection to check, instead of the default connection and Horizon\'s supervisors}
        {--queue=* : The queues to check, instead of the connection\'s default queue}
    ';

    public $description = 'Exit successfully when no queued or running jobs are left, so an idle stack can stop its queue workers.';

    public function handle(): int
    {
        $busy = $this->queues()->filter(
            fn (array $queue): bool => Queue::connection($queue['connection'])->size($queue['queue']) > 0,
        );

        foreach ($busy as $queue) {
            $this->line("Jobs left on {$queue['connection']}:{$queue['queue']}");
        }

        return $busy->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The given queues, or the default connection's queue and those of
     * every Horizon supervisor.
     *
     * @return Collection<int, array{connection: string, queue: string}>
     */
    protected function queues(): Collection
    {
        $connection = $this->option('connection');
        $connection = is_string($connection) ? $connection : '';
        $queues = (array) $this->option('queue');

        if ($connection !== '' || $queues !== []) {
            $connection = $connection !== '' ? $connection : Config::string('queue.default');

            return $this->queuesOn($connection, $queues !== [] ? $queues : [$this->defaultQueue($connection)]);
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
