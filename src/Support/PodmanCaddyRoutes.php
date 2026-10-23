<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

/**
 * Builds Caddyfile routes that reverse proxy sibling services under a path
 * of the app's own domain, for a container's embedded Caddy instance (e.g.
 * FrankenPHP's, via "CADDY_SERVER_EXTRA_DIRECTIVES"):
 *
 *     PodmanCaddyRoutes::make()
 *         ->route(env('VITE_REVERB_PATH'), env('REVERB_HOST'), env('REVERB_PORT', 6001))
 *         ->route(env('MAILPIT_PATH'), env('MAIL_HOST'), 8025, stripPrefix: false)
 *         ->render();
 *
 * @see PodmanCaddySites::routes()
 */
class PodmanCaddyRoutes
{
    /**
     * @var array<int, array{path: string, upstream: string, stripPrefix: bool}>
     */
    protected array $routes = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * Send a path to "host:port". The route is skipped when the path is
     * empty or "/", or when the host or port is missing (e.g. an unset env
     * variable).
     *
     * The path is stripped before the request is passed on, so the service
     * keeps serving from "/". Pass `$stripPrefix: false` for services that
     * serve from the path themselves, such as Mailpit with "MP_WEBROOT".
     */
    public function route(?string $path, ?string $host, int|string|null $port, bool $stripPrefix = true): static
    {
        return $this->upstream($path, PodmanCaddySites::hostPort($host, $port), $stripPrefix);
    }

    /**
     * Send a path to an upstream given as "host:port", such as the result of
     * PodmanCaddySites::hostPortFromUrl().
     */
    public function upstream(?string $path, ?string $upstream, bool $stripPrefix = true): static
    {
        $path = '/'.trim((string) $path, '/');

        if ($path === '/' || empty($upstream)) {
            return $this;
        }

        $this->routes[] = ['path' => $path, 'upstream' => $upstream, 'stripPrefix' => $stripPrefix];

        return $this;
    }

    public function render(): string
    {
        $blocks = [];

        foreach ($this->routes as ['path' => $path, 'upstream' => $upstream, 'stripPrefix' => $stripPrefix]) {
            $directive = $stripPrefix ? 'handle_path' : 'handle';

            $blocks[] = "redir {$path} {$path}/\n\n{$directive} {$path}/* {\n\treverse_proxy {$upstream}\n}";
        }

        return implode("\n\n", $blocks);
    }
}
