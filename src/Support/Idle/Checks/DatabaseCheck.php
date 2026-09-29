<?php

declare(strict_types=1);

namespace Foxws\Podman\Support\Idle\Checks;

use Foxws\Podman\Support\Idle\IdleCheck;
use Foxws\Podman\Support\Idle\IdleResult;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Busy while another client runs a query or holds a transaction open, e.g.
 * a long migration or report. Idle connections, like a worker waiting for
 * its next job, don't count. PostgreSQL, MySQL, MariaDB and MongoDB (through
 * mongodb/laravel-mongodb) are checked; other databases count as idle.
 */
class DatabaseCheck extends IdleCheck
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

        $active = match ($connection->getDriverName()) {
            'pgsql' => (int) $connection->scalar("select count(*) from pg_stat_activity where datname = current_database() and pid <> pg_backend_pid() and backend_type = 'client backend' and state <> 'idle'"),
            'mysql', 'mariadb' => (int) $connection->scalar("select count(*) from information_schema.processlist where id <> connection_id() and db = database() and command <> 'Sleep'"),
            'mongodb' => $this->activeMongoOperations($connection),
            default => 0,
        };

        return $active === 0
            ? IdleResult::idle()
            : IdleResult::busy("{$active} active queries on {$connection->getName()}");
    }

    /**
     * Running operations on the app's database, through the "currentOp"
     * admin command of mongodb/laravel-mongodb's client.
     */
    protected function activeMongoOperations(Connection $connection): int
    {
        if (! method_exists($connection, 'getClient')) {
            return 0;
        }

        $result = $connection->getClient()->selectDatabase('admin')->command([
            'currentOp' => true,
            'active' => true,
            'ns' => ['$regex' => '^'.preg_quote($connection->getDatabaseName()).'\\.'],
        ])->toArray();

        return count($result[0]['inprog'] ?? []);
    }
}
