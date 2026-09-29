<?php

declare(strict_types=1);

namespace Foxws\Podman\Support\Idle\Checks;

use Foxws\Podman\Support\Idle\IdleCheck;
use Foxws\Podman\Support\Idle\IdleResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Busy while clients are connected to Reverb, asked through its Pusher
 * compatible HTTP API. A Reverb server that doesn't answer counts as idle:
 * it sleeps with the app, and nobody can be connected to it then.
 */
class BroadcastCheck extends IdleCheck
{
    protected ?string $connection = null;

    public function name(): string
    {
        return 'broadcast';
    }

    public function isEnabled(): bool
    {
        return $this->config('driver') === 'reverb';
    }

    public function connection(string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function run(): IdleResult
    {
        $path = "/apps/{$this->config('app_id')}/connections";

        $query = [
            'auth_key' => (string) $this->config('key'),
            'auth_timestamp' => (string) time(),
            'auth_version' => '1.0',
        ];

        $query['auth_signature'] = hash_hmac('sha256', "GET\n{$path}\n".http_build_query($query), (string) $this->config('secret'));

        $url = sprintf(
            '%s://%s:%s%s',
            $this->config('options.scheme', 'http'),
            $this->config('options.host', '127.0.0.1'),
            $this->config('options.port', 8080),
            $path,
        );

        try {
            $connections = (int) Http::timeout(5)->get($url, $query)->throw()->json('connections', 0);
        } catch (Throwable) {
            return IdleResult::idle();
        }

        return $connections === 0
            ? IdleResult::idle()
            : IdleResult::busy("{$connections} clients connected");
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        $connection = $this->connection ?? Config::string('broadcasting.default');

        return Config::get("broadcasting.connections.{$connection}.{$key}", $default);
    }
}
