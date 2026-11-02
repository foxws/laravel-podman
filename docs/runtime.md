---
section: Configuration
order: 7
---

# Runtime Image

The `production` preset's image is built on a [static FrankenPHP build](https://frankenphp.dev/docs/static/) on `debian:trixie-slim`: the `runtime` stage of its `Containerfile`. It has no app code, so [`build-runtime.yml`](../.github/workflows/build-runtime.yml) builds it straight from this repo and publishes it to `ghcr.io/foxws/laravel-podman-runtime:php-8.5`. The app stage starts from that image (the `RUNTIME_IMAGE` build arg), so building your app skips compiling FrankenPHP.

You can build your own images on it too, for example to add tools your app needs:

```dockerfile
FROM ghcr.io/foxws/laravel-podman-runtime:php-8.5

RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*
```

## What's inside

- FrankenPHP with PHP 8.5 as `/usr/local/bin/frankenphp`, compiled with `apcu bcmath exif gd igbinary imagick intl opcache pcntl pdo_mysql pdo_pgsql pdo_sqlite redis sockets uv zip` and PHP's usual built-in extensions
- A `php` command that runs FrankenPHP's `php-cli` (see [The php command](#the-php-command))
- Composer, `curl`, `gosu` and the `docker` user (UID/GID 1000)
- PHP's `php.ini-production` as `/etc/frankenphp/php.ini`, with `variables_order = "EGPCS"` so `$_ENV` has the container's environment. Add your own settings to `/etc/frankenphp/php.d/`

The image exposes no ports. The quadlets expose the ports they use with `ExposeHostPort=`.

## The php command

The static build has no separate `php` binary. `/usr/local/bin/php` is a script that runs `frankenphp php-cli`. It also does two things `php-cli` doesn't do on PHP 8.5:

- It applies the `-d` settings before the script, by writing them to an ini file that PHP loads through `PHP_INI_SCAN_DIR`. Composer passes `-d allow_url_fopen=… -d disable_functions=… -d memory_limit=…` to every `@php` script, such as `@php artisan package:discover`.
- It sets `PHP_BINARY` to itself instead of the `frankenphp` binary. Horizon, the scheduler, `queue:listen` and Composer start their child processes with `PHP_BINARY`, so they keep working.

## Other PHP extensions

Extensions are compiled into the binary, so you can't install them on top of the image. To change them, publish the `production` preset, build its `runtime` stage with another `PHP_EXTENSIONS` list (comma-separated, it replaces the default), then build the app on it:

```bash
php artisan podman:publish production
php artisan podman:generate production

podman build -f podman/production/runtimes/Containerfile --target=runtime \
    --build-arg PHP_EXTENSIONS="apcu,bcmath,...,mongodb" \
    -t localhost/my-app-runtime .
```

Then pass `--build-arg RUNTIME_IMAGE=localhost/my-app-runtime`, or add `BuildArg=RUNTIME_IMAGE=localhost/my-app-runtime` under `[Build]`. See the [static-php-cli extension list](https://static-php.dev/en/guide/extensions.html) for what's supported. The build compiles PHP and every extension from source, so it takes a while.

## Links

- [CI: Building a Container Image](ci-build.md)
- [Customizing](customizing.md)
- [FrankenPHP static builds](https://frankenphp.dev/docs/static/)
