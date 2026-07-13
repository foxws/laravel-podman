# Laravel Podman

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-podman.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-podman)
[![GitHub Tests Action Status](https://github.com/foxws/laravel-podman/actions/workflows/run-tests.yml/badge.svg)](https://github.com/foxws/laravel-podman/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/foxws/laravel-podman/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/foxws/laravel-podman/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-podman.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-podman)

Laravel Podman brings [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html) support to your Laravel application. It ships a set of Artisan commands to install, list, print, and remove the Quadlet-managed services that make up your app (the application itself and sibling services such as a database, cache, or search engine), manage the Podman secrets those services need, and publish the container runtime used to build your application image.

A Sail-inspired `lpod` CLI script is also included for day-to-day interaction with the running containers (starting/stopping services, opening a shell, running Artisan/Composer/Node commands, and more).

See the [`docs/`](docs) folder for more: [Proxy](docs/proxy.md), [lpod tips & tricks](docs/lpod.md), [Customizing](docs/customizing.md).

## Requirements

- Linux with systemd (rootless or system-wide); macOS and Windows, including WSL, are not supported
- A recent version of Podman with the `quadlet` CLI plugin (`podman quadlet --help` should work); the `--application` option used by `podman:install` requires Podman 6+
- PHP 8.4+

## Installation

You can install the package via composer:

```bash
composer require foxws/laravel-podman
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-podman-config"
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

    'selinux_volume_mapping' => env('PODMAN_SELINUX_VOLUME_MAPPING', true),

    'reload_systemd' => env('PODMAN_RELOAD_SYSTEMD', true),
];
```

`quadlet_prefix` is used to namespace the services installed for your application (for example `laravel-pgsql`), and defaults to your `APP_NAME`. `quadlet_uid`/`quadlet_gid` default to the UID/GID of the user running the Artisan command.

**Custom templates.** `quadlets_path`/`runtimes_path` control where the package looks for `*.quadlets` files and runtime folders (each containing a `Containerfile`), and default to `containers/quadlets`/`containers/runtimes` in your project. If either directory exists, the package uses it exclusively instead of the vendor-provided one — so to customize an existing service (e.g. tweak `pgsql.quadlets`) or add your own, copy the full set of files you need into that directory, not just the ones you're changing. Leave a directory absent (the default until you create it) to keep using the one bundled with the package.

## Quick Start

The fastest way to get an application running is `podman:setup`. It publishes the default runtimes and installs the default services in one go, so you don't need to call `podman:publish`/`podman:install` per service:

```bash
composer require foxws/laravel-podman

php artisan podman:setup
```

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

The bundled `proxy` runtime terminates HTTPS with a locally-trusted certificate. For your browser/OS to trust it too, export and install Caddy's CA certificate:

```bash
podman cp systemd-proxy:/data/caddy/pki/authorities/local/root.crt ~/proxy.crt

# macOS
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ~/proxy.crt

# Linux (Arch/Debian/Ubuntu)
sudo cp ~/proxy.crt /usr/local/share/ca-certificates/caddy.crt && sudo update-ca-certificates
```

> **Note:** This is for local development only — production deployments should use real certificates (e.g. Let's Encrypt), which Caddy handles automatically once it has a public domain to serve.

## Usage

The package discovers its Quadlet service definitions (`*.quadlets` files) and its container runtimes (folders containing a `Containerfile`) on disk, and exposes them through the commands below. Every command that needs a service or runtime name will prompt you to select one interactively when it's omitted.

### Setup Application

Publishes the default runtimes and installs the default set of services in one go — the quickest way to get an application running (see [Quick Start](#quick-start)).

```bash
php artisan podman:setup

# Override the default runtimes and/or services
php artisan podman:setup --runtime=frankenphp-octane --service=app --service=pgsql

# Skip secret prompts, and don't replace services that already exist
php artisan podman:setup --no-secrets --no-replace

# Install into a named application subdirectory (requires Podman 6+)
php artisan podman:setup --application=my-app
```

### Publish Container Runtime

Publishes a container runtime (e.g. the bundled `frankenphp-octane` runtime) so it can be customized before you build your application image.

> **Note:** This is a requirement for `podman:install` to work, since the runtime must be present on disk before Podman can build the image.

```bash
php artisan podman:publish frankenphp-octane

# Overwrite files that were already published
php artisan podman:publish frankenphp-octane --force
```

### Install Services

Installs one or more Quadlet services so systemd can manage them. Omitting the service name(s) prompts you to select one or more services from a checklist. If installing multiple services in one run, a failure on one service doesn't stop the rest — failures are collected and reported in a summary at the end, and the command exits non-zero if any service failed.

```bash
php artisan podman:install pgsql

# Install multiple services in one go
php artisan podman:install pgsql valkey

# Install into a named application subdirectory (requires Podman 6+)
php artisan podman:install pgsql --application=my-app

# Replace the service(s) if they already exist
php artisan podman:install pgsql --replace

# Prompt for and set the secrets required by each service before installing
php artisan podman:install pgsql --secrets
```

### Set Secrets

Prompts for and sets the Podman secrets used by a service, without installing it.

```bash
php artisan podman:secret pgsql

# Replace secrets that already exist
php artisan podman:secret pgsql --replace
```

Secrets are read from the `Secret=` directives in a service's `.quadlets` file. `type=env` secrets prompt for a value directly, while `type=mount` secrets prompt for a file path (defaulting to your project's `.env`) whose contents are stored as the secret.

### List Services

Lists the Quadlets configured for the current user.

```bash
php artisan podman:list

php artisan podman:list --filter=status=running --format=json --noheading
```

### `podman:print`

Prints the generated systemd unit for a service, as Podman would install it.

```bash
php artisan podman:print pgsql
```

### Remove Services

> **Note:** A service's `.volume` Quadlets (e.g. `pgsql`'s database volume, `rustfs`'s storage volumes) are removed along with it, deleting the underlying Podman volume and everything stored in it. Back this up first if you need to keep it — see [Backing up volumes](#backing-up-volumes) below.

Removes an installed Quadlet service.

```bash
php artisan podman:remove pgsql

# Force removal of a running service, ignoring missing services
php artisan podman:remove pgsql --force --ignore
```

### Uninstall Application

> **WARNING**: This command is destructive and will remove all of the services installed for the application, including any data stored in volumes (databases, uploaded files, search indexes, etc). This cannot be undone — back up anything you need to keep first, see [Backing up volumes](#backing-up-volumes) below.

Removes an application and all of its installed services in one go.

```bash
php artisan podman:uninstall my-app

php artisan podman:uninstall my-app --force
```

### Backing up volumes

`podman:remove` and `podman:uninstall` delete the Podman volumes owned by the services they remove, along with their data — there's no undo. Before running either against a service holding data you care about (`pgsql`, `valkey`, `rustfs`, `typesense`, `mailpit`), back it up:

```bash
# Generic: archive any named volume to a tarball
podman volume export laravel-pgsql -o pgsql-backup.tar

# Database-specific dumps are usually more portable than a raw volume export
lpod my-app run pg_dump -U postgres -d laravel > backup.sql
```

Restore with `podman volume import laravel-pgsql pgsql-backup.tar` (before reinstalling the service) or by replaying the database-specific dump, depending on which approach you used to back up.

## The `lpod` utility

The package ships a `lpod` CLI script, installed as a Composer binary at `vendor/bin/lpod`. It's a thin wrapper around `podman exec` and `systemctl` for the Quadlet services you installed with `podman:install`, similar in spirit to Laravel Sail's `sail` script. Any command it doesn't recognize is passed straight through to the `podman` binary.

```bash
vendor/bin/lpod SERVICE COMMAND [options] [arguments]
```

`SERVICE` is the name of a Quadlet service (e.g. your application's service, or a sibling service such as `pgsql`).

### Shortening the `vendor/bin/lpod` call

Typing `vendor/bin/lpod` for every command gets old fast, so pick one of the following.

**Add a shell alias.** This resolves `lpod` relative to your current directory, so it keeps working correctly no matter which project you're in.

Bash or Zsh, in `~/.bashrc` / `~/.zshrc`:

```bash
alias lpod='[ -f vendor/bin/lpod ] && bash vendor/bin/lpod || bash "$(git rev-parse --show-toplevel)/vendor/bin/lpod"'
```

Fish, in `~/.config/fish/config.fish`:

```fish
function lpod
    if test -f vendor/bin/lpod
        bash vendor/bin/lpod $argv
    else
        bash (git rev-parse --show-toplevel)/vendor/bin/lpod $argv
    end
end
```

**Or install it onto your `PATH`.** This is simplest if you're only working with a single Podman-managed application on the machine, since the symlink always points at the `vendor/bin/lpod` of the project you created it from:

```bash
ln -s "$(pwd)/vendor/bin/lpod" ~/.local/bin/lpod

# or, to make it available to every user on the machine
sudo ln -s "$(pwd)/vendor/bin/lpod" /usr/local/bin/lpod
```

Make sure the target directory (`~/.local/bin` or `/usr/local/bin`) is on your `PATH`. Once installed either way, the examples below can be run as `lpod ...` instead of `vendor/bin/lpod ...`.

**Lifecycle**

```bash
lpod my-app up        # Start the "my-app" service
lpod my-app down       # Stop the "my-app" service
lpod my-app restart    # Restart the "my-app" service
lpod my-app status     # Show the status of the "my-app" service
```

**Artisan, PHP, and Composer**

```bash
lpod my-app artisan queue:work
lpod my-app art queue:work     # Alias for "artisan"
lpod my-app a queue:work       # Alias for "artisan"
lpod my-app php -v
lpod my-app composer require laravel/sanctum
lpod my-app debug queue:work   # Artisan command with Xdebug enabled
lpod my-app tinker
```

**Node, npm, pnpm, Yarn, and Bun**

```bash
lpod my-app node --version
lpod my-app npm run prod
lpod my-app npx ...
lpod my-app pnpm run prod
lpod my-app pnpx ...
lpod my-app yarn run prod
lpod my-app bun run prod
lpod my-app bunx ...
```

**Testing**

```bash
lpod my-app test               # php artisan test
lpod my-app phpunit ...
lpod my-app pest ...
lpod my-app pint ...
lpod my-app dusk                # Requires laravel/dusk
lpod my-app dusk:fails
```

**Container CLI and binaries**

```bash
lpod my-app shell        # Alias: bash
lpod my-app root-shell   # Alias: root-bash
lpod my-app bin phpstan  # Run vendor/bin/phpstan
lpod my-app run whoami   # Run an arbitrary command in the container
```

**Other**

```bash
lpod my-app open                     # Open the application URL in your browser
lpod my-app artisan podman:publish   # Publish the Podman container runtime files
lpod --help                          # Print the full list of commands
```

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
