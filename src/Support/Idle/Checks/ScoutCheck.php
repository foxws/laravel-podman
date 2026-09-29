<?php

declare(strict_types=1);

namespace Foxws\Podman\Support\Idle\Checks;

use Foxws\Podman\Support\Idle\IdleCheck;
use Foxws\Podman\Support\Idle\IdleResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Busy while queued Scout indexing jobs are left ("scout.queue"), or while
 * Meilisearch still processes indexing tasks: it accepts documents right
 * away and indexes them in the background. Typesense indexes right away.
 */
class ScoutCheck extends IdleCheck
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
        $result = $this->queuedJobs();

        if (! $result->isIdle || Config::get('scout.driver') !== 'meilisearch') {
            return $result;
        }

        return $this->meilisearchTasks();
    }

    protected function queuedJobs(): IdleResult
    {
        $queue = Config::get('scout.queue');

        if (! $queue) {
            return IdleResult::idle();
        }

        $check = QueueCheck::new();

        if (is_array($queue) && isset($queue['connection'])) {
            $check->connection((string) $queue['connection']);
        }

        if (is_array($queue) && isset($queue['queue'])) {
            $check->queues([(string) $queue['queue']]);
        }

        return $check->run();
    }

    /**
     * A Meilisearch server that doesn't answer counts as idle: it sleeps
     * when nothing needs it, and processes no tasks then.
     */
    protected function meilisearchTasks(): IdleResult
    {
        $host = rtrim((string) Config::get('scout.meilisearch.host', 'http://127.0.0.1:7700'), '/');
        $key = (string) Config::get('scout.meilisearch.key');

        try {
            $tasks = (int) Http::timeout(5)
                ->when($key !== '', fn ($request) => $request->withToken($key))
                ->get("{$host}/tasks", ['statuses' => 'enqueued,processing', 'limit' => 1])
                ->throw()
                ->json('total', 0);
        } catch (Throwable) {
            return IdleResult::idle();
        }

        return $tasks === 0
            ? IdleResult::idle()
            : IdleResult::busy("{$tasks} Meilisearch tasks enqueued or processing");
    }
}
