# Command Reference

The package finds preset folders on disk (`quadlets/` + `runtimes/`) and exposes them through the Artisan commands below. If you don't pass a preset name, the command prompts you to choose one.

These commands only render files. They never call the `podman` binary, so they work anywhere PHP runs (including inside a container without Podman). Installing, listing, printing, removing, and setting secrets is handled by the host-side `lpod`/`lpod-setup`/`lpod-secrets` binaries — see [The `lpod` CLI](lpod.md).

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

Creates the S3 buckets your app needs and applies a CORS policy to the ones browsers read directly. Requires `aws/aws-sdk-php` (`composer require aws/aws-sdk-php`), which is optional and not installed by default. See [S3 Buckets](s3.md) for details.

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

Restore with `podman volume import laravel-pgsql pgsql-backup.tar` (before reinstalling the service), or replay the database dump if you used that method.
