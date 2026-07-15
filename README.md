# Laravel Podman

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-podman.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-podman)
[![GitHub Tests Action Status](https://github.com/foxws/laravel-podman/actions/workflows/run-tests.yml/badge.svg)](https://github.com/foxws/laravel-podman/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/foxws/laravel-podman/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/foxws/laravel-podman/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-podman.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-podman)

Laravel Podman brings [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html) support to your Laravel application, installing your app and its sibling services (database, cache, search engine, proxy, ...) as rootless, systemd-managed containers.

## Features

- **Artisan renders, `lpod` installs** — Artisan commands substitute your app's config into a preset's Quadlet units and never touch the `podman` binary, so they work even without Podman installed; `lpod` handles installing, listing, printing, and removing the systemd-managed services on the host.
- **A preset per environment** — `frankenphp-octane` (production-style app plus its full set of sibling services), `development` (the same services with your working copy live-mounted for local editing instead of baked into the image), `devcontainer` (a VS Code Dev Containers image), and `proxy` (Caddy) ship out of the box — pick the ones you need, or add your own. See [Presets](#presets) below.
- **Podman secrets, not plaintext `.env` files** — application and service credentials (database passwords, your `.env` file, ...) are stored as Podman secrets and mounted into containers at runtime, rather than baked into the image or passed around as plain environment variables.
- **FrankenPHP application image** — a multi-stage `Containerfile` for your app with dedicated `development` and `frankenphp-octane` (production) build targets sharing the same FrankenPHP runtime.
- **S3-compatible object storage setup** — the `s3` preset and `podman:s3-setup` command create buckets and apply a CORS policy against any AWS-compatible service (MinIO, RustFS, Garage, real S3, ...). See [S3 Buckets](docs/s3.md).
- **Sail-inspired `lpod` CLI** — a `lpod` script for day-to-day container interaction (starting/stopping services, opening a shell, running Artisan/Composer/Node commands, and more).
- **Customizable, like Sail's `docker-compose.yml`** — publish the bundled `*.quadlets`/runtime templates into your project to tweak an existing service or add your own, the same way Sail lets you edit its published `docker-compose.yml`. See [Customizing](docs/customizing.md).

See the [`docs/`](docs) folder for more: [Command Reference](docs/commands.md), [Setting up without PHP on the host](docs/host-setup.md), [Proxy](docs/proxy.md), [S3 Buckets](docs/s3.md), [The `lpod` CLI](docs/lpod.md), [Customizing](docs/customizing.md).

## Requirements

- Linux with systemd (rootless or system-wide); macOS and Windows, including WSL, are not supported
- A recent version of Podman with the `quadlet` CLI plugin (`podman quadlet --help` should work); the `--application` option (`lpod install ... --application=my-app`) requires Podman 6+

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
    'presets' => env('PODMAN_DEFAULT_PRESETS', [
        // 'development',
        'devcontainer',
        'frankenphp-octane',
        'proxy',
    ]),

    's3_buckets' => env('PODMAN_S3_BUCKETS', [
        'local',
        'assets',
        'media',
        'conversions',
    ]),

    's3_cors_buckets' => env('PODMAN_S3_CORS_BUCKETS', [
        'conversions',
        'assets',
    ]),

    'quadlet_prefix' => env('PODMAN_QUADLET_PREFIX', env('APP_NAME', 'laravel')),

    'proxy_prefix' => env('PODMAN_PROXY_PREFIX', 'proxy'),

    'stubs_path' => env('PODMAN_STUBS_PATH', 'containers/stubs'),

    'working_path' => env('PODMAN_WORKING_PATH'),

    'quadlet_uid' => env('PODMAN_QUADLET_UID'),

    'quadlet_gid' => env('PODMAN_QUADLET_GID'),

    'publish_path' => env('PODMAN_PUBLISH_PATH', 'podman'),

    'selinux_volume_mapping' => env('PODMAN_SELINUX_VOLUME_MAPPING', true),

    'reload_systemd' => env('PODMAN_RELOAD_SYSTEMD', true),
];
```

`quadlet_prefix` is used to namespace the services installed for your application (for example `laravel-pgsql`), and defaults to your `APP_NAME`. `quadlet_uid`/`quadlet_gid` default to the UID/GID of the user running the Artisan command. `s3_buckets`/`s3_cors_buckets` are used by `podman:s3-setup` — see [S3 Buckets](docs/s3.md).

`development` is commented out by default — enable it (or pass `--preset=development` to `podman:setup`/`podman:generate`) if you want your working copy live-mounted into the container instead of baked into the image.

## Presets

| Preset              | Purpose                                                                                                                                                            |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `frankenphp-octane` | Production-style app image (code baked in) plus its full set of sibling services — database, cache, queue worker, WebSockets, scheduler, SSR, search, S3-compatible storage, mail catcher. |
| `development`       | The same services, but with your working copy live-mounted into the container instead of baked in, for local editing. Commented out by default — see the config above. |
| `devcontainer`       | An image for the VS Code/JetBrains [Dev Containers](https://containers.dev/) workflow. Not Quadlet-managed — just a `Containerfile` and `devcontainer.json`, no `quadlets/`. |
| `proxy`              | [Caddy](https://caddyserver.com/) reverse proxy terminating HTTPS in front of the other services. See [Proxy](docs/proxy.md).                                     |
| `s3`                 | A `cors.json` policy applied by `podman:s3-setup` against your S3-compatible storage buckets. Not Quadlet-managed either — no `quadlets/`/`runtimes/`, just the one file. See [S3 Buckets](docs/s3.md). |

**Custom presets.** A preset is a folder containing a `quadlets/` directory of `*.quadlets` files and a `runtimes/` directory of container build files (the `devcontainer`/`s3` presets are exceptions — see the table above). `stubs_path` controls where the package looks for preset folders, and defaults to `containers/stubs` in your project. The fallback happens per preset, not as a whole directory swap — publish one preset (`php artisan podman:publish frankenphp-octane`) to customize it without needing to touch any other preset. Leave a preset unpublished to keep using the one bundled with the package.

## Quick Start

The fastest way to render an application's Quadlet units is `podman:setup`. It generates the default presets' `.quadlets`/runtime build files in one go, so you don't need to call `podman:generate` per preset:

```bash
php artisan podman:setup
```

> **Note:** This step only renders files — it substitutes your app's config into the preset's templates and writes the result to the `publish_path` config key (`podman` by default, one subfolder per preset). It never touches the `podman` binary, so it works even without Podman installed (e.g. inside a disposable `php` container in CI). If you don't have PHP on the host at all, see [Setting up without PHP on the host](docs/host-setup.md).

The presets it generates by default come from the `presets` config key (`devcontainer`/`frankenphp-octane`/`proxy` out of the box — see [Presets](#presets)) — edit that, set `PODMAN_DEFAULT_PRESETS`, or override per run:

```bash
php artisan podman:setup --preset=frankenphp-octane
```

Installing is a separate step, handled by [`lpod`](#the-lpod-utility) on the host (this is the one step that actually needs the `podman` binary):

```bash
vendor/bin/lpod install frankenphp-octane/app.quadlets --replace
vendor/bin/lpod install frankenphp-octane/pgsql.quadlets --replace
vendor/bin/lpod install frankenphp-octane/valkey.quadlets --replace
vendor/bin/lpod install proxy/proxy.quadlets --replace
# ...and so on for every service you need.
```

> **Note:** `devcontainer` and `s3` aren't Quadlet-managed (see [Presets](#presets)), so there's nothing to `lpod install` for them — build the devcontainer image through your editor's Dev Containers extension, and run `s3`'s CORS policy through `podman:s3-setup` instead.

Secrets a service needs (e.g. your application's `.env` file, database credentials) are prompted for and set once the service is installed, by unit name:

```bash
vendor/bin/lpod secrets app
vendor/bin/lpod secrets pgsql
```

Once installed, use `lpod` to start everything:

```bash
vendor/bin/lpod my-app up
vendor/bin/lpod my-app open   # Opens the application URL in your browser
```

The bundled `proxy` preset terminates HTTPS with a locally-trusted certificate — trust it once so your browser/OS stop flagging it, see [Trusting the local certificate](docs/proxy.md#trusting-the-local-certificate).

Setting up somewhere PHP isn't installed (a disposable container, CI)? Once `lpod` itself is on the host, `vendor/bin/lpod setup` (a shortcut for the `lpod-setup` binary — see [The `lpod` utility](#the-lpod-utility)) renders the default presets the same way, without needing PHP:

```bash
vendor/bin/lpod setup
vendor/bin/lpod setup --preset=frankenphp-octane
```

See [Setting up without PHP on the host](docs/host-setup.md) for the raw `podman run ...` equivalent (e.g. before `lpod` is available at all) and the details of what gets rendered where.

## Usage

The package discovers preset folders (each containing a `quadlets/` directory of `*.quadlets` files and a `runtimes/` directory of container build files) on disk, and exposes them through Artisan commands that only ever render — never install. Every command that needs a preset name will prompt you to select one interactively when it's omitted. Full flag reference and examples: [Command Reference](docs/commands.md).

| Command | Description |
| --- | --- |
| `podman:setup` | Generate the default set of presets in one go (see [Quick Start](#quick-start)) |
| `podman:publish PRESET` | Publish a preset (its quadlets and runtime files) for customization |
| `podman:generate PRESET` | Render a single preset's quadlets and runtime files |
| `podman:s3-setup` | Create S3 buckets and a CORS policy (requires `aws/aws-sdk-php`, see [S3 Buckets](docs/s3.md)) |

Installing, listing, printing, removing, and setting secrets for the rendered services is `lpod`'s job, not Artisan's — see below.

> **Warning:** `lpod remove`/`lpod uninstall` delete the Podman volumes owned by the services they remove (databases, uploaded files, search indexes), with no undo. Back up first — see [Backing up volumes](docs/commands.md#backing-up-volumes).

## The `lpod` utility

The package ships three Composer binaries that all run **on the host** — they talk to the real `podman`/`systemctl` binaries, unlike the Artisan `podman:*` commands, which only render templates and can run anywhere PHP is available:

- **`vendor/bin/lpod`** — a thin wrapper around `podman exec`, `podman quadlet`, and `systemctl` for the Quadlet services rendered by Artisan, similar in spirit to Laravel Sail's `sail` script.
- **`vendor/bin/lpod-setup`** — renders presets by running `php artisan podman:setup` inside a disposable container, for hosts that have Podman but not PHP. See [Setting up without PHP on the host](docs/host-setup.md).
- **`vendor/bin/lpod-secrets`** — prompts for and stores the Podman secrets an installed Quadlet unit needs.

`lpod setup` and `lpod secrets` are convenience aliases for the latter two — call `vendor/bin/lpod-setup`/`vendor/bin/lpod-secrets` directly if you'd rather skip the `lpod` wrapper.

```bash
vendor/bin/lpod SERVICE COMMAND [options] [arguments]

vendor/bin/lpod my-app up
vendor/bin/lpod my-app artisan queue:work
vendor/bin/lpod my-app shell

# Installing, secrets, and other Quadlet management (see below)
vendor/bin/lpod install frankenphp-octane/app.quadlets --replace
vendor/bin/lpod secrets app
```

See [The `lpod` CLI](docs/lpod.md) for the full command reference (including `install`/`secrets`/`remove`/`list`/`print`/`uninstall`), shortening the call with an alias/`PATH` entry, and tips & tricks.

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
