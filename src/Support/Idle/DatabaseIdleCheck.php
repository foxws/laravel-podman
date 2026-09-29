<?php

declare(strict_types=1);

namespace Foxws\Podman\Support\Idle;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Busy while another client runs a query or holds a transaction open, e.g.
 * a long migration or report. Idle connections, like a worker waiting for
 * its next job, don't count. Only PostgreSQL, MySQL and MariaDB are checked.
 */
class DatabaseIdleCheck extends IdleCheck
{
    protected ?string $connection = null;

    public function name(): string
    {
        return 'database';
    }

    public function isEnabled(): bool
    {
        $connection = $this->connection ?? Config::string('database.default');

        return Config::get("database.connections.{$connection}.driver") !== null;
    }

    public function connection(string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function run(): IdleResult
    {
        $connection = DB::connection($this->connection);

        $query = match ($connection->getDriverName()) {
            'pgsql' => "select count(*) from pg_stat_activity where datname = current_database() and pid <> pg_backend_pid() and backend_type = 'client backend' and state <> 'idle'",
            'mysql', 'mariadb' => "select count(*) from information_schema.processlist where id <> connection_id() and db = database() and command <> 'Sleep'",
            default => null,
        };

        $active = $query !== null ? (int) $connection->scalar($query) : 0;

        return $active === 0
            ? IdleResult::idle()
            : IdleResult::busy("{$active} active queries on {$connection->getName()}");
    }
}
