# Laravel Podman

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-podman.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-podman)
[![GitHub Tests Action Status](https://github.com/foxws/laravel-podman/actions/workflows/run-tests.yml/badge.svg)](https://github.com/foxws/laravel-podman/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/foxws/laravel-podman/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/foxws/laravel-podman/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-podman.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-podman)

Renders [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-quadlet.1.html) units from your Laravel app's config, then installs them as [systemd-managed](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html) containers on your host — no all-in-one runtime, no lock-in. Swap any bundled part (Caddy for Nginx, Postgres for MySQL) for your own.

See [`docs/`](docs) for the full reference: [Commands](docs/commands.md), [Customizing](docs/customizing.md), [Proxy](docs/proxy.md), [S3 Buckets](docs/s3.md), [The `lpod` CLI](docs/lpod.md), [Setting up without PHP](docs/host-setup.md), [Comparison](docs/comparison.md).

## Requirements

- **Linux with systemd** (rootless or system-wide) — macOS, Windows, and WSL are not supported
- **Podman** with the `quadlet` CLI plugin (`podman quadlet --help` should work)

## Installation

```bash
composer require foxws/laravel-podman --dev
```

```bash
php artisan vendor:publish --tag="podman-config"
```

Only needed to render Quadlet units — install as a dev dependency and skip it in production. See [Customizing](docs/customizing.md) for every config key.

## Presets

| Preset | What it is |
| --- | --- |
| `development` | App + services, working copy live-mounted for local editing. **Enabled by default.** |
| `frankenphp-octane` | Production-style image, app code baked in. Commented out by default. |
| `devcontainer` | VS Code/JetBrains [Dev Containers](https://containers.dev/) image. **Enabled by default.** |
| `proxy` | [Caddy](https://caddyserver.com/) reverse proxy in front of the other services. |
| `s3` | CORS policy for S3-compatible storage buckets. |

Custom presets: publish one (`php artisan podman:publish frankenphp-octane`) without touching the others — see [Customizing](docs/customizing.md).

## Quick start

1. **Render** the default presets:

    ```bash
    php artisan podman:setup
    ```

2. **Install [`lpod`](https://github.com/foxws/lpod)** once per host — a dependency-free script, no PHP/Composer needed:

    ```bash
    curl -fsSL -o ~/.local/bin/lpod https://github.com/foxws/lpod/releases/latest/download/lpod
    chmod +x ~/.local/bin/lpod
    ```

3. **Install** each rendered service (the only step that needs `podman` itself):

    ```bash
    lpod install development/app.quadlets --replace
    lpod install development/pgsql.quadlets --replace
    lpod install development/valkey.quadlets --replace
    lpod install proxy/proxy.quadlets --replace
    ```

4. **Set secrets, then start:**

    ```bash
    lpod my-app secrets
    lpod pgsql secrets
    lpod my-app up
    lpod my-app open
    ```

Trust the proxy's local certificate once — see [Proxy](docs/proxy.md#trusting-the-local-certificate).

No PHP on the host? `lpod setup` renders the same way without it — see [Setting up without PHP](docs/host-setup.md).

## Commands reference

| Command | Description |
| --- | --- |
| `podman:setup` | Generate the default set of presets in one go |
| `podman:publish PRESET` | Publish a preset for customization |
| `podman:generate PRESET` | Render a single preset |
| `podman:s3-setup` | Create S3 buckets and a CORS policy (requires `aws/aws-sdk-php`) |

Installing, listing, removing, and setting secrets is [`lpod`](https://github.com/foxws/lpod)'s job, not Artisan's. Full flag reference: [Commands](docs/commands.md).

> **Warning:** `lpod remove`/`lpod uninstall` delete the Podman volumes they own (databases, uploads, search indexes), with no undo — see [Backing up volumes](docs/commands.md#backing-up-volumes).

## The `lpod` utility

[`lpod`](https://github.com/foxws/lpod) is a separate, dependency-free bash script — no PHP, Composer, or this package required to run it:

```bash
curl -fsSL -o ~/.local/bin/lpod https://github.com/foxws/lpod/releases/latest/download/lpod
chmod +x ~/.local/bin/lpod
```

```bash
lpod SERVICE COMMAND [options] [arguments]

lpod my-app up
lpod my-app artisan queue:work
lpod my-app shell

lpod install development/app.quadlets --replace
lpod my-app secrets
```

This package ships one Composer binary, **`vendor/bin/lpod-setup`**, which renders presets inside a disposable container for hosts with Podman but no PHP. `lpod setup` is a shortcut for it — copy `bin/lpod-setup` next to wherever `lpod` lives on your `PATH`.

Full command reference, shortening the call, and tips & tricks: [foxws/lpod](https://github.com/foxws/lpod).

## Testing

```bash
composer test
```

## Links

- [CHANGELOG](CHANGELOG.md)
- [Security policy](../../security/policy)
- [foxws/lpod](https://github.com/foxws/lpod) — the CLI this package pairs with
- [Podman Quadlet reference](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html)

## Credits

- [francoism90](https://github.com/foxws)
- [All Contributors](../../contributors)

AI, specifically [Claude](https://claude.com/product/claude-code), was used to help build this package. All AI-assisted output is reviewed by me, and I retain final say over everything that is implemented and released.

## License

MIT. See [License File](LICENSE.md).
