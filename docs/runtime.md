---
section: Configuration
order: 7
---

# Runtime

The `runtime` preset is the base image of the `production` preset: a [static FrankenPHP build](https://frankenphp.dev/docs/static/) on `debian:trixie-slim`. It has no app code, so it builds straight from this repo, and [`build-runtime.yml`](../.github/workflows/build-runtime.yml) publishes it to `ghcr.io/foxws/laravel-podman-runtime:php-8.5`. You can use it as the base of your own images too:

```dockerfile
FROM ghcr.io/foxws/laravel-podman-runtime:php-8.5
```

## What's inside

- FrankenPHP with PHP 8.5 as `/usr/local/bin/frankenphp`, compiled with `apcu bcmath exif gd igbinary imagick intl opcache pcntl pdo_mysql pdo_pgsql pdo_sqlite redis sockets uv zip` and PHP's usual built-in extensions
- A `php` command that runs FrankenPHP's `php-cli` (see [The php command](#the-php-command))
- Composer, `curl`, `gosu` and the `docker` user (UID/GID 1000)
- PHP's `php.ini-production` as `/etc/frankenphp/php.ini`. Add your own settings to `/etc/frankenphp/php.d/`

The image exposes no ports. The quadlets expose the ports they use with `ExposeHostPort=`.

## The php command

The static build has no separate `php` binary. `/usr/local/bin/php` is a script that runs `frankenphp php-cli`. It also does two things `php-cli` doesn't do on PHP 8.5:

- It applies the `-d` settings before the script, such as `php -d variables_order=EGPCS artisan`, by writing them to an ini file that PHP loads through `PHP_INI_SCAN_DIR`.
- It sets `PHP_BINARY` to itself instead of the `frankenphp` binary. Horizon, the scheduler, `queue:listen` and Composer start their child processes with `PHP_BINARY`, so they keep working.

## Other PHP extensions

Extensions are compiled into the binary, so you can't install them on top of the image. To change them, publish the preset and build your own runtime with another `PHP_EXTENSIONS` list (comma-separated, it replaces the default):

```bash
php artisan podman:generate runtime
podman build -f podman/runtime/runtimes/Containerfile \
    --build-arg PHP_EXTENSIONS="apcu,bcmath,...,mongodb" \
    -t my-runtime .
```

See the [static-php-cli extension list](https://static-php.dev/en/guide/extensions.html) for what's supported. The build compiles PHP and every extension from source, so it takes a while. Then point the `production` preset at it with `--build-arg RUNTIME_IMAGE=my-runtime`, or `BuildArg=RUNTIME_IMAGE=my-runtime` under `[Build]`.

## Links

- [CI: Building a Container Image](ci-build.md)
- [Customizing](customizing.md)
- [FrankenPHP static builds](https://frankenphp.dev/docs/static/)
