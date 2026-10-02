# Laravel Podman

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-podman.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-podman)
[![GitHub Tests Action Status](https://github.com/foxws/laravel-podman/actions/workflows/run-tests.yml/badge.svg)](https://github.com/foxws/laravel-podman/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/foxws/laravel-podman/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/foxws/laravel-podman/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-podman.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-podman)

Run your Laravel app and its services as [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html) containers, managed by systemd on your own Linux machine or server.

The package renders ready-to-use presets from your app's config: the app on Octane and FrankenPHP, a database, cache, queue worker, search, object storage, a mail catcher and a Caddy proxy. You install them with one command each. There's no all-in-one runtime and no lock-in: the output is plain Quadlet files you can read, change or replace.

Upgrading from an earlier major version? See [UPGRADING.md](UPGRADING.md).

## Features

**Presets for every stage**

- **`development`**: your working copy mounted live, Octane with file watching, Xdebug (off until you turn it on), and an optional Vite dev server.
- **`production`**: a FrankenPHP and Octane image with your code baked in, Inertia SSR, and the queue worker and scheduler running independently of the app.
- **`devcontainer`**: a [Dev Containers](https://containers.dev/) image for VS Code and JetBrains, with an optional AI variant (Claude Code, Codex, Laravel LSP).
- **`proxy`**: [Caddy](https://caddyserver.com/) in front of everything, with HTTPS (local certificates in development, Let's Encrypt in production) and subdomains for Vite, Reverb, S3 and Mailpit.

**Services included, one per category**

| Category | Services (default first) |
| --- | --- |
| Database | PostgreSQL, MariaDB, MySQL, MongoDB |
| Cache | Valkey, Redis, Memcached |
| Queue worker | `queue:work`, Horizon |
| Search | Typesense, Meilisearch |
| Object storage | RustFS (S3-compatible) |
| Mail catcher | Mailpit |
| Also | Scheduler, Reverb, Inertia SSR |

**Scale to zero**

- The app starts on its first request and stops after 10 minutes without traffic ([on-demand services](https://foxws.nl/laravel-podman/ondemand)). Change the timeout with `PODMAN_ONDEMAND_IDLE_TIMEOUT`, e.g. `5min`, `30min` or `2h`.
- The database, cache and other services sleep too, once no jobs are left.
- Works behind the bundled proxy or your own, such as a NAS or Nginx on another machine.
- Set `PODMAN_ONDEMAND_ENABLED=false` to keep everything running all the time.

**Native to your system**

- Plain systemd units that work rootless or system-wide. Services restart on failure. The proxy, the app's socket and the production workers start at boot.
- Database, cache and proxy images are pinned to a major version, and every container is set up for `podman auto-update`.
- Production secrets, like your `.env`, are stored as Podman secrets instead of files.
- SELinux-aware volume mounts, which you can turn off on hosts without SELinux.

**Yours to customize**

- Publish just the preset you want to change. The rest keep following package updates.
- Swap services, add your own, or set memory limits with plain Quadlet files and `{{placeholders}}`.
- Add your own placeholders from `config/podman.php`.

**Tooling**

- [`lpod`](https://github.com/foxws/lpod), a small CLI for installing services and running Artisan, Composer, Node and tests inside your containers.
- `lpod` also turns Xdebug on and off, and checks your host with `lpod doctor`.
- `podman:s3-setup` creates your S3 buckets and applies a CORS policy.
- Render presets on a host without PHP with `lpod setup`.
- An example CI workflow builds and pushes a multi-arch production image.
- [Laravel Boost](https://github.com/laravel/boost) skills, so AI agents know how to run commands in, customize and upgrade your setup.

## Requirements

- **Linux with systemd** (rootless or system-wide). macOS, Windows and WSL aren't supported.
- **Podman** with Quadlet support (`podman quadlet --help` should work).

## Installation

```bash
composer require foxws/laravel-podman
php artisan vendor:publish --tag="podman-config"
```

If you run production in these containers, install it as a regular dependency, not with `--dev`: the idle check runs `php artisan podman:idle` inside them, and production images are built without dev dependencies. If you only use it for development and deploy elsewhere, `--dev` is preferred. Where it is installed but shouldn't touch services, set `PODMAN_ENABLED=false`: `podman:generate`, `podman:setup`, `podman:publish` and `podman:s3-setup` then refuse to run. See [Customizing](https://foxws.nl/laravel-podman/customizing) for every config key.

## Quick start

1. **Render** the default presets (`development`, `ondemand` and `proxy`):

    ```bash
    php artisan podman:setup
    ```

2. **Install [`lpod`](https://github.com/foxws/lpod)** once per host. It's a single bash script and doesn't need PHP. Upgrade it later with `lpod self-update`:

    ```bash
    curl -fsSL https://github.com/foxws/lpod/releases/latest/download/install.sh | bash
    ```

3. **Install** the services. Replace `my-app` with your app's name (`APP_NAME`, kebab-cased):

    ```bash
    lpod install development/app.quadlets --replace
    lpod install development/pgsql.quadlets --replace
    lpod install development/valkey.quadlets --replace
    lpod install ondemand/my-app-ondemand.socket --replace
    lpod install proxy/proxy.quadlets --replace
    lpod idle enable my-app
    ```

4. **Open** your app. The first request starts it:

    ```bash
    lpod my-app open
    ```

5. **Trust the proxy's local certificate** once. See [Proxy](https://foxws.nl/laravel-podman/proxy#trusting-the-local-certificate).

For frontend work, run `pnpm install` and add the Vite dev server:

```bash
lpod install development/vite.quadlets --replace
lpod my-app-vite up
```

For `production`, also set the secrets it expects, such as your `.env` and the database password, with `lpod my-app secrets` and `lpod my-app-pgsql secrets`.

## Commands

| Command | Description |
| --- | --- |
| `podman:setup` | Render the default presets |
| `podman:publish PRESET` | Copy a preset into your project to customize it |
| `podman:generate PRESET` | Render a single preset |
| `podman:s3-setup` | Create S3 buckets and apply a CORS policy (needs `aws/aws-sdk-php`) |
| `podman:idle` | Succeed when the app has no work in progress, for stopping idle queue workers |

Everything else, like installing, starting, removing and setting secrets, is done with [`lpod`](https://foxws.nl/laravel-podman/lpod):

```bash
lpod my-app artisan migrate
lpod my-app composer require laravel/horizon
lpod my-app pest
lpod my-app shell
```

> **Warning:** `lpod remove` and `lpod uninstall` delete the service's Podman volumes, including databases and uploads. There's no undo. See [Backing up volumes](https://foxws.nl/laravel-podman/commands#backing-up-volumes).

## Documentation

The full documentation is at [foxws.nl/laravel-podman](https://foxws.nl/laravel-podman/commands):

- **Getting started:** [Commands](https://foxws.nl/laravel-podman/commands), [`lpod` CLI](https://foxws.nl/laravel-podman/lpod)
- **Configuration:** [Customizing](https://foxws.nl/laravel-podman/customizing), [Devcontainer](https://foxws.nl/laravel-podman/devcontainer), [Proxy](https://foxws.nl/laravel-podman/proxy), [S3 Buckets](https://foxws.nl/laravel-podman/s3), [On-demand services](https://foxws.nl/laravel-podman/ondemand)
- **Advanced:** [Setting up without PHP](https://foxws.nl/laravel-podman/host-setup), [CI: Building a Container Image](https://foxws.nl/laravel-podman/ci-build)
- **Reference:** [Comparison with Sail, Herd and others](https://foxws.nl/laravel-podman/comparison)

## Testing

```bash
composer test
```

## Links

- [CHANGELOG](CHANGELOG.md)
- [Upgrade guide](UPGRADING.md)
- [Security policy](../../security/policy)
- [foxws/lpod](https://github.com/foxws/lpod), the CLI this package pairs with
- [Podman Quadlet reference](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html)

## Credits

- [francoism90](https://github.com/foxws)
- [All Contributors](../../contributors)
- [Spatie](https://spatie.be): the idle checks follow the design of [spatie/laravel-health](https://github.com/spatie/laravel-health)'s checks

AI, specifically [Claude](https://claude.com/product/claude-code), was used to help build this package. All AI-assisted output is reviewed by me, and I retain final say over everything that is implemented and released.

## License

MIT. See [License File](LICENSE.md).
