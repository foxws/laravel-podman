# Command Reference

The package discovers preset folders (each containing a `quadlets/` directory of `*.quadlets` files and a `runtimes/` directory of container build files) on disk, and exposes them through the Artisan commands below. Every command that needs a preset name will prompt you to select one interactively when it's omitted.

These commands only ever render — none of them touch the `podman` binary, so they work anywhere PHP is available, including inside a container without Podman installed. Installing, listing, printing, removing, and setting secrets for the rendered services is the job of the `lpod`/`lpod-setup`/`lpod-secrets` Composer binaries instead — see [The `lpod` CLI](lpod.md). All three of those run on the host, since they need the real `podman`/`systemctl` binaries.

## Setup Application

Generates the default set of presets in one go — the quickest way to get an application's Quadlet units rendered (see [Quick Start](../README.md#quick-start)).

```bash
php artisan podman:setup

# Override the default presets
php artisan podman:setup --preset=frankenphp-octane
```

## Publish Preset

Publishes a preset (its `quadlets/` and `runtimes/` files) so it can be customized before generating.

```bash
php artisan podman:publish frankenphp-octane

# Overwrite files that were already published
php artisan podman:publish frankenphp-octane --force
```

## Generate Preset

Renders a single preset's `.quadlets` files and runtime build files (substituting the `{{...}}` placeholders described in [Customizing](customizing.md)) into the configured publish path (`podman/{preset}/` by default), ready for [`lpod install`](lpod.md).

```bash
php artisan podman:generate frankenphp-octane
```

See [Setting up without PHP on the host](host-setup.md) for running this somewhere PHP is convenient but Podman isn't.

## Setup S3 Buckets

Creates the S3 buckets your app needs and applies a CORS policy to the ones that serve requests directly to a browser. Requires `aws/aws-sdk-php` (`composer require aws/aws-sdk-php`) — not installed by default, since most apps don't need S3. See [S3 Buckets](s3.md) for the full guide.

```bash
php artisan podman:s3-setup
```

## Backing up volumes

`lpod remove` and `lpod uninstall` delete the Podman volumes owned by the services they remove, along with their data — there's no undo. Before running either against a service holding data you care about (`pgsql`, `valkey`, `rustfs`, `typesense`, `mailpit`), back it up:

```bash
# Generic: archive any named volume to a tarball
podman volume export laravel-pgsql -o pgsql-backup.tar

# Database-specific dumps are usually more portable than a raw volume export
lpod my-app run pg_dump -U postgres -d laravel > backup.sql
```

Restore with `podman volume import laravel-pgsql pgsql-backup.tar` (before reinstalling the service) or by replaying the database-specific dump, depending on which approach you used to back up.
