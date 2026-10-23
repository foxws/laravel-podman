<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Foxws\Podman\Exceptions\InvalidAppUrlException;
use Foxws\Podman\Exceptions\QuadletRenderException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

/**
 * Typed access to the "podman" config values used while rendering presets.
 */
class PodmanConfig
{
    public function domain(): string
    {
        $url = Config::string('app.url');

        return Uri::of($url)->host()
            ?? throw InvalidAppUrlException::missingHost($url);
    }

    /**
     * A subdomain of the app's domain, e.g. "vite.laravel.test" for "vite".
     */
    public function subdomain(string $name): string
    {
        return "{$name}.{$this->domain()}";
    }

    public function prefix(): string
    {
        return Str::kebab(Config::string('podman.quadlet_prefix'));
    }

    /**
     * What Quadlet puts in front of a unit's name to name its container or
     * network: "systemd-" unless the units name them themselves.
     */
    public function containerPrefix(): string
    {
        return (string) Config::get('podman.container_prefix', 'systemd-');
    }

    public function uid(): int
    {
        $uid = Config::get('podman.quadlet_uid');

        if ($uid !== null) {
            return (int) $uid;
        }

        return function_exists('posix_getuid') ? posix_getuid() : 1000;
    }

    public function gid(): int
    {
        $gid = Config::get('podman.quadlet_gid');

        if ($gid !== null) {
            return (int) $gid;
        }

        return function_exists('posix_getgid') ? posix_getgid() : 1000;
    }

    /**
     * The systemctl command for the service manager the units are installed
     * in: the system manager for root, the user's own manager otherwise.
     */
    public function systemctl(): string
    {
        return $this->uid() === 0 ? 'systemctl' : 'systemctl --user';
    }

    /**
     * @return array<int, string>
     */
    public function defaultPresets(): array
    {
        return $this->parseNameList(Config::get('podman.presets', []));
    }

    /**
     * @return array<int, string>
     */
    public function s3Buckets(): array
    {
        return $this->parseNameList(Config::get('podman.s3_buckets', []));
    }

    /**
     * @return array<int, string>
     */
    public function s3CorsBuckets(): array
    {
        return $this->parseNameList(Config::get('podman.s3_cors_buckets', []));
    }

    /**
     * Extra "{{placeholder}}" => value pairs merged into every rendered
     * template, on top of the built-in ones. Keys may leave out the braces
     * ("viteUrl" is "{{viteUrl}}"), and a null value, such as an unset env
     * variable, renders as an empty string.
     *
     * @return array<string, string>
     */
    public function customSubstitutions(): array
    {
        return Collection::make(Config::array('podman.substitutions', []))
            ->mapWithKeys(fn (mixed $value, string $key): array => [
                Str::of($key)->start('{{')->finish('}}')->toString() => (string) $value,
            ])
            ->all();
    }

    public function isEnabled(): bool
    {
        return Config::boolean('podman.enabled');
    }

    public function shouldUseSelinuxVolumeMapping(): bool
    {
        return Config::boolean('podman.selinux_volume_mapping');
    }

    public function isOnDemandEnabled(): bool
    {
        return Config::boolean('podman.ondemand.enabled');
    }

    /**
     * Whether on-demand services are enabled, as a systemd boolean for
     * directives like "StopWhenUnneeded=".
     */
    public function onDemand(): string
    {
        return $this->isOnDemandEnabled() ? 'yes' : 'no';
    }

    /**
     * The systemd "ListenStream=" value the on-demand socket listens on,
     * e.g. "8000" or "0.0.0.0:8000".
     */
    public function onDemandListen(): string
    {
        return (string) Config::get('podman.ondemand.listen');
    }

    public function onDemandListenPort(): string
    {
        return Str::afterLast($this->onDemandListen(), ':');
    }

    /**
     * The loopback port the app is published on, for the socket proxy to
     * forward to.
     */
    public function onDemandPort(): int
    {
        return (int) Config::get('podman.ondemand.port');
    }

    public function onDemandIdleTimeout(): string
    {
        return (string) Config::get('podman.ondemand.idle_timeout');
    }

    /**
     * The workers the idle check stops, by service name without the prefix,
     * e.g. ["queue", "horizon"]. They're rendered into a shell loop, so only
     * plain unit names are accepted.
     *
     * @return array<int, string>
     */
    public function onDemandIdleWorkers(): array
    {
        $workers = $this->parseNameList(Config::get('podman.ondemand.idle_workers', 'queue,horizon'));

        foreach ($workers as $worker) {
            if (! preg_match('/^[A-Za-z0-9_-]+$/', $worker)) {
                throw QuadletRenderException::invalidIdleWorker($worker);
            }
        }

        return $workers;
    }

    /**
     * Where the "proxy" preset sends app traffic: straight to the app
     * container, or to the on-demand socket on the host so a request can
     * start the app.
     */
    public function appUpstream(): string
    {
        if ($this->isOnDemandEnabled()) {
            return "host.containers.internal:{$this->onDemandListenPort()}";
        }

        return $this->serviceUpstream(null, 8000);
    }

    /**
     * The "host:port" a preset's service is reached at on the app's network,
     * e.g. "systemd-my-app-vite:5173" for ("vite", 5173), or the app
     * container itself ("systemd-my-app:8000") when the service is null.
     * The "systemd-" part is the container prefix.
     */
    public function serviceUpstream(?string $service, int $port): string
    {
        $container = $service === null ? $this->prefix() : "{$this->prefix()}-{$service}";

        return "{$this->containerPrefix()}{$container}:{$port}";
    }

    /**
     * Normalize a config value that may be either a comma-separated string
     * or a plain array into a list of trimmed, non-empty names.
     *
     * @return array<int, string>
     */
    protected function parseNameList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        $value = Arr::map($value, fn (mixed $item): string => trim((string) $item));

        return array_values(Arr::where($value, fn (string $item): bool => $item !== ''));
    }
}
