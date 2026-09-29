<?php

declare(strict_types=1);

namespace Foxws\Podman\Support\Idle;

use Illuminate\Support\Facades\Config;

/**
 * Busy while queued Scout indexing jobs are left. Without "scout.queue",
 * Scout indexes right away, so there's nothing to wait for.
 */
class ScoutIdleCheck extends IdleCheck
{
    public function name(): string
    {
        return 'scout';
    }

    public function isEnabled(): bool
    {
        return ! in_array(Config::get('scout.driver'), [null, 'null', 'collection', 'database'], true);
    }

    public function run(): IdleResult
    {
        $queue = Config::get('scout.queue');

        if (! $queue) {
            return IdleResult::idle();
        }

        $check = QueueIdleCheck::new();

        if (is_array($queue) && isset($queue['connection'])) {
            $check->connection((string) $queue['connection']);
        }

        if (is_array($queue) && isset($queue['queue'])) {
            $check->queues([(string) $queue['queue']]);
        }

        return $check->run();
    }
}
