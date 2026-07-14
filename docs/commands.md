# Command Reference

The package discovers its Quadlet service definitions (`*.quadlets` files) and its container runtimes (folders containing a `Containerfile`) on disk, and exposes them through the commands below. Every command that needs a service or runtime name will prompt you to select one interactively when it's omitted.

## Setup Application

Publishes the default runtimes and installs the default set of services in one go — the quickest way to get an application running (see [Quick Start](../README.md#quick-start)).

```bash
php artisan podman:setup

# Override the default runtimes and/or services
php artisan podman:setup --runtime=frankenphp-octane --service=app --service=pgsql

# Skip secret prompts, and don't replace services that already exist
php artisan podman:setup --no-secrets --no-replace

# Install into a named application subdirectory (requires Podman 6+)
php artisan podman:setup --application=my-app

# Prepare services at the publish path without installing them (see
# "Setting up without PHP on the host")
php artisan podman:setup --no-install
```

## Publish Container Runtime

Publishes a container runtime (e.g. the bundled `frankenphp-octane` runtime) so it can be customized before you build your application image.

> **Note:** This is a requirement for `podman:install` to work, since the runtime must be present on disk before Podman can build the image.

```bash
php artisan podman:publish frankenphp-octane

# Overwrite files that were already published
php artisan podman:publish frankenphp-octane --force
```

## Install Services

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

# Prepare the service(s) at the publish path without installing them (see
# "Setting up without PHP on the host")
php artisan podman:install pgsql --no-install
```

See [Setting up without PHP on the host](host-setup.md) for more on `--no-install`.

## Set Secrets

Prompts for and sets the Podman secrets used by a service, without installing it.

```bash
php artisan podman:secret pgsql

# Replace secrets that already exist
php artisan podman:secret pgsql --replace
```

Secrets are read from the `Secret=` directives in a service's `.quadlets` file. `type=env` secrets prompt for a value directly, while `type=mount` secrets prompt for a file path (defaulting to your project's `.env`) whose contents are stored as the secret.

## List Services

Lists the Quadlets configured for the current user.

```bash
php artisan podman:list

php artisan podman:list --filter=status=running --format=json --noheading
```

## `podman:print`

Prints the generated systemd unit for a service, as Podman would install it.

```bash
php artisan podman:print pgsql
```

## Remove Services

> **Note:** A service's `.volume` Quadlets (e.g. `pgsql`'s database volume, `rustfs`'s storage volumes) are removed along with it, deleting the underlying Podman volume and everything stored in it. Back this up first if you need to keep it — see [Backing up volumes](#backing-up-volumes) below.

Removes an installed Quadlet service.

```bash
php artisan podman:remove pgsql

# Force removal of a running service, ignoring missing services
php artisan podman:remove pgsql --force --ignore
```

## Uninstall Application

> **WARNING**: This command is destructive and will remove all of the services installed for the application, including any data stored in volumes (databases, uploaded files, search indexes, etc). This cannot be undone — back up anything you need to keep first, see [Backing up volumes](#backing-up-volumes) below.

Removes an application and all of its installed services in one go.

```bash
php artisan podman:uninstall my-app

php artisan podman:uninstall my-app --force
```

## Setup S3 Buckets

Creates the S3 buckets your app needs and applies a CORS policy to the ones that serve requests directly to a browser. Requires `aws/aws-sdk-php` (`composer require aws/aws-sdk-php`) — not installed by default, since most apps don't need S3. See [S3 Buckets](s3.md) for the full guide.

```bash
php artisan podman:s3-setup
```

## Backing up volumes

`podman:remove` and `podman:uninstall` delete the Podman volumes owned by the services they remove, along with their data — there's no undo. Before running either against a service holding data you care about (`pgsql`, `valkey`, `rustfs`, `typesense`, `mailpit`), back it up:

```bash
# Generic: archive any named volume to a tarball
podman volume export laravel-pgsql -o pgsql-backup.tar

# Database-specific dumps are usually more portable than a raw volume export
lpod my-app run pg_dump -U postgres -d laravel > backup.sql
```

Restore with `podman volume import laravel-pgsql pgsql-backup.tar` (before reinstalling the service) or by replaying the database-specific dump, depending on which approach you used to back up.
