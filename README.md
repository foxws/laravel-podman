# Laravel Podman

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-podman.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-podman)
[![GitHub Tests Action Status](https://github.com/foxws/laravel-podman/actions/workflows/run-tests.yml/badge.svg)](https://github.com/foxws/laravel-podman/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/foxws/laravel-podman/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/foxws/laravel-podman/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-podman.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-podman)

Laravel Podman brings [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html) support to your Laravel application, installing your app and its sibling services (database, cache, search engine, proxy, ...) as rootless, systemd-managed containers.

## Features

- **Artisan-driven Quadlet management** — install, list, print, and remove the systemd-managed services that make up your app, through a set of Artisan commands.
- **Podman secrets, not plaintext `.env` files** — application and service credentials (database passwords, your `.env` file, ...) are stored as Podman secrets and mounted into containers at runtime, rather than baked into the image or passed around as plain environment variables.
- **FrankenPHP application image** — a multi-stage `Containerfile` for your app with dedicated `local` (development) and `production` build targets sharing the same FrankenPHP runtime.
- **Sail-inspired `lpod` CLI** — a `lpod` script for day-to-day container interaction (starting/stopping services, opening a shell, running Artisan/Composer/Node commands, and more).
- **Customizable, like Sail's `docker-compose.yml`** — publish the bundled `*.quadlets`/runtime templates into your project to tweak an existing service or add your own, the same way Sail lets you edit its published `docker-compose.yml`. See [Customizing](docs/customizing.md).

See the [`docs/`](docs) folder for more: [Command Reference](docs/commands.md), [Setting up without PHP on the host](docs/host-setup.md), [Proxy](docs/proxy.md), [S3 Buckets](docs/s3.md), [The `lpod` CLI](docs/lpod.md), [Customizing](docs/customizing.md).

## Requirements

- Linux with systemd (rootless or system-wide); macOS and Windows, including WSL, are not supported
- A recent version of Podman with the `quadlet` CLI plugin (`podman quadlet --help` should work); the `--application` option used by `podman:install` requires Podman 6+

## Installation

You can install the package via composer:

```bash
composer require foxws/laravel-podman
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="podman-config"
```

This is the contents of the published config file:

```php
return [
    'quadlet_prefix' => env('PODMAN_QUADLET_PREFIX', env('APP_NAME', 'laravel')),

    'quadlet_uid' => env('PODMAN_QUADLET_UID'),

    'quadlet_gid' => env('PODMAN_QUADLET_GID'),

    'quadlets_path' => env('PODMAN_QUADLETS_PATH', 'containers/quadlets'),

    'runtimes_path' => env('PODMAN_RUNTIMES_PATH', 'containers/runtimes'),

    'runtime_path' => env('PODMAN_RUNTIME_PATH', 'runtimes'),

    'config_path' => env('PODMAN_CONFIG_PATH', 'runtimes/config'),

    'publish_path' => env('PODMAN_PUBLISH_PATH', 'podman'),

    'selinux_volume_mapping' => env('PODMAN_SELINUX_VOLUME_MAPPING', true),

    'reload_systemd' => env('PODMAN_RELOAD_SYSTEMD', true),
];
```

`quadlet_prefix` is used to namespace the services installed for your application (for example `laravel-pgsql`), and defaults to your `APP_NAME`. `quadlet_uid`/`quadlet_gid` default to the UID/GID of the user running the Artisan command.

**Custom templates.** `quadlets_path`/`runtimes_path` control where the package looks for `*.quadlets` files and runtime folders (each containing a `Containerfile`), and default to `containers/quadlets`/`containers/runtimes` in your project. If either directory exists, the package uses it exclusively instead of the vendor-provided one — so to customize an existing service (e.g. tweak `pgsql.quadlets`) or add your own, copy the full set of files you need into that directory, not just the ones you're changing. Leave a directory absent (the default until you create it) to keep using the one bundled with the package.

## Quick Start

The fastest way to get an application running is `podman:setup`. It publishes the default runtimes and installs the default services in one go, so you don't need to call `podman:publish`/`podman:install` per service:

```bash
php artisan podman:setup
```

> **Note:** This step requires PHP, since it's an Artisan command, and normally requires the `podman` binary too, since it installs Quadlet units and Podman secrets directly on your system. It's a one-time cost — once your services are installed, everyday commands (starting services, running Artisan/Composer/Node commands, opening a shell, etc.) go through [`lpod`](#the-lpod-utility) instead, which runs entirely inside the containers and doesn't need PHP on the host. If you don't have PHP on the host at all, see [Setting up without PHP on the host](docs/host-setup.md).

By default it also prompts for and sets any secrets the installed services need (e.g. your application's `.env` file, database credentials) and replaces services that already exist, so re-running it is safe. Pass `--no-secrets` and/or `--no-replace` to opt out:

```bash
php artisan podman:setup --no-secrets --no-replace
```

The runtimes and services it publishes/installs by default come from the `runtimes` and `services` config keys (`frankenphp-octane`/`proxy` and `proxy`/`app`/`pgsql`/`valkey`/`horizon`/`reverb`/`schedule` out of the box) — edit those, set `PODMAN_DEFAULT_RUNTIMES`/`PODMAN_DEFAULT_SERVICES`, or override per run:

```bash
php artisan podman:setup --runtime=frankenphp-octane --service=app --service=pgsql --service=valkey
```

Once installed, use the `lpod` CLI (see [below](#the-lpod-utility)) to start everything:

```bash
vendor/bin/lpod my-app up
vendor/bin/lpod my-app open   # Opens the application URL in your browser
```

The bundled `proxy` runtime terminates HTTPS with a locally-trusted certificate — trust it once so your browser/OS stop flagging it, see [Trusting the local certificate](docs/proxy.md#trusting-the-local-certificate).

Setting up somewhere PHP isn't installed (a disposable container, CI)? See [Setting up without PHP on the host](docs/host-setup.md).

## Usage

The package discovers its Quadlet service definitions (`*.quadlets` files) and its container runtimes (folders containing a `Containerfile`) on disk, and exposes them through Artisan commands. Every command that needs a service or runtime name will prompt you to select one interactively when it's omitted. Full flag reference and examples: [Command Reference](docs/commands.md).

| Command | Description |
| --- | --- |
| `podman:setup` | Publish default runtimes and install default services in one go (see [Quick Start](#quick-start)) |
| `podman:publish RUNTIME` | Publish a container runtime so it can be customized before building |
| `podman:install SERVICE...` | Install one or more Quadlet services |
| `podman:secret SERVICE` | Prompt for and set a service's Podman secrets |
| `podman:list` | List installed Quadlets |
| `podman:print SERVICE` | Print the generated systemd unit for a service |
| `podman:remove SERVICE` | Remove an installed service |
| `podman:uninstall APPLICATION` | Remove an application and all of its services |
| `podman:s3-setup` | Create S3 buckets and a CORS policy (requires `aws/aws-sdk-php`, see [S3 Buckets](docs/s3.md)) |

> **Warning:** `podman:remove`/`podman:uninstall` delete the Podman volumes owned by the services they remove (databases, uploaded files, search indexes), with no undo. Back up first — see [Backing up volumes](docs/commands.md#backing-up-volumes).

## The `lpod` utility

The package ships a `lpod` CLI script, installed as a Composer binary at `vendor/bin/lpod`. It's a thin wrapper around `podman exec` and `systemctl` for the Quadlet services you installed with `podman:install`, similar in spirit to Laravel Sail's `sail` script.

```bash
vendor/bin/lpod SERVICE COMMAND [options] [arguments]

vendor/bin/lpod my-app up
vendor/bin/lpod my-app artisan queue:work
vendor/bin/lpod my-app shell
```

See [The `lpod` CLI](docs/lpod.md) for the full command reference, shortening the call with an alias/`PATH` entry, and tips & tricks.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [francoism90](https://github.com/foxws)
- [All Contributors](../../contributors)

## Disclaimer

AI, specifically [Claude](https://claude.com/product/claude-code), was used to help build this package. All AI-assisted output is reviewed by me, and I retain final say over everything that is implemented and released.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
