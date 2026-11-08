---
section: Configuration
order: 7
---

# FrankenPHP Image

The `production` and `development` presets build the app on `ghcr.io/foxws/laravel-frankenphp`, a FrankenPHP image with everything that doesn't change between builds of the app. [`build-frankenphp.yml`](../.github/workflows/build-frankenphp.yml) builds it from [`images/frankenphp/Containerfile`](../images/frankenphp/Containerfile) and publishes it. Building your app pulls it and only copies the app in, installs its dependencies and builds its assets, so the slow `apt-get` and PHP extension layers don't run again.

| Tag | Used by | What it is |
| --- | --- | --- |
| `php-8.5` | `production` | PHP 8.5 with `php.ini-production` |
| `php-8.5-dev` | `development` | PHP 8.5 with `php.ini-development`, Xdebug and Playwright's browser deps |
| `php-8.4`, `php-8.4-dev` | | The same with PHP 8.4 |
| `php-8.5-6.6.0`, `php-8.5-6.6`, `php-8.5-dev-6.6.0`, ... | | The image as it was at that release |

The presets' Containerfiles start from it through the `RUNTIME_IMAGE` build arg. For PHP 8.4, add it under `[Build]` in the app's quadlet:

```ini
[Build]
BuildArg=RUNTIME_IMAGE=ghcr.io/foxws/laravel-frankenphp:php-8.4
```

The image is rebuilt every week and on every release, for FrankenPHP, PHP and Debian updates. Rebuild your app to pick them up. To keep an image until you choose to update, use a release tag or a digest (`ghcr.io/foxws/laravel-frankenphp@sha256:...`).

## What's inside

- The official [FrankenPHP image](https://frankenphp.dev/docs/docker/) (`dunglas/frankenphp:1-php8.5`), with `install-php-extensions` and `docker-php-ext-*`
- The PHP extensions `apcu bcmath exif ffi gd igbinary imagick inotify intl opcache pcntl pdo_pgsql redis sockets uv zip`, plus `xdebug` in the `-dev` image
- Composer, Node.js 26 with npm, pnpm and svgo, the PostgreSQL 18 client, `gosu`, and `libavif-bin jpegoptim optipng pngquant gifsicle` for image optimization
- The `docker` user (UID/GID 1000). The presets renumber it when the app quadlet's `BuildArg=UID=`/`GID=` differ
- `frankenphp` without file capabilities, so it runs as the `docker` user. The quadlets publish unprivileged ports

## No inherited ports or health check

The official image has `EXPOSE 80/tcp 443/tcp 443/udp 2019/tcp`, a `HEALTHCHECK` on the admin API and Caddy as its entrypoint. A Containerfile can't remove those, so `podman ps` listed the ports on every container built from it, and every container had its health check. This image copies the official image's files into a `FROM scratch` image and sets its environment again, which leaves them out. The containers only have the ports, health checks and commands their quadlets set.

## Extra PHP extensions or packages

The image still has `install-php-extensions`, so add extensions in your app's Containerfile, or pass them in the `PHP_EXTENSIONS` build arg (space-separated):

```ini
[Build]
BuildArg=PHP_EXTENSIONS=mongodb pdo_mysql
```

Packages work the same way, for example in a published `production` preset:

```dockerfile
FROM ${RUNTIME_IMAGE}

RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*
```

These layers run again when the runtime image changes, so put ones that take long in an image of your own built on this one, or build the runtime yourself.

## Building it yourself

For another Node.js or PostgreSQL version, or to bake your own packages into the base, build the image from the package and set `RUNTIME_IMAGE` to its tag:

```bash
podman build -f vendor/foxws/laravel-podman/images/frankenphp/Containerfile \
    --target=runtime \
    --build-arg NODE_VERSION=24 --build-arg POSTGRES_VERSION=17 \
    -t localhost/my-app-runtime vendor/foxws/laravel-podman/images/frankenphp
```

Use `--target=development` for the `-dev` image, and `--build-arg FRANKENPHP_VERSION=1-php8.4` for PHP 8.4. Then add `BuildArg=RUNTIME_IMAGE=localhost/my-app-runtime` under `[Build]`.

## Links

- [CI: Building a Container Image](ci-build.md)
- [Customizing](customizing.md)
- [FrankenPHP Docker images](https://frankenphp.dev/docs/docker/)
