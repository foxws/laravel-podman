# Command Reference

The package finds preset folders on disk (`quadlets/` + `runtimes/`) and exposes them through the Artisan commands below. Omit the preset name and it prompts you.

These commands only render files — never touch `podman`, so they work anywhere PHP runs. Installing, listing, removing, and secrets are [`lpod`](https://github.com/foxws/lpod)'s job — see [The `lpod` CLI](lpod.md). Rendered output (`publish_path`, default `podman/`) is a build artifact: don't commit it.

## `podman:setup`

Generates the default set of presets in one go — see [Quick Start](../README.md#quick-start).

```bash
php artisan podman:setup

# Override the default presets
php artisan podman:setup --preset=frankenphp-octane
```

## `podman:publish PRESET`

Publishes a preset's `quadlets/` and `runtimes/` files for customization.

```bash
php artisan podman:publish frankenphp-octane

# Overwrite already-published files
php artisan podman:publish frankenphp-octane --force
```

## `podman:generate PRESET`

Renders a single preset (see [Customizing](customizing.md) for placeholders) into the publish path, ready for `lpod install`.

```bash
php artisan podman:generate frankenphp-octane

# Override podman.working_path for this run
php artisan podman:generate development --working-path=/srv/my-app
```

`--working-path` overrides `working_path` (normally `PODMAN_WORKING_PATH`) for one run, without touching `.env` — see [Setting up without PHP](host-setup.md).

## `podman:s3-setup`

Creates S3 buckets and applies a CORS policy to the ones browsers read directly. Requires `aws/aws-sdk-php` — see [S3 Buckets](s3.md).

```bash
php artisan podman:s3-setup
```

## Backing up volumes

`lpod remove`/`lpod uninstall` delete the Podman volumes they own, with no undo. Back up first for anything holding data (`pgsql`, `valkey`, `rustfs`, `typesense`, `mailpit`):

```bash
# Generic: archive any named volume to a tarball
podman volume export laravel-pgsql -o pgsql-backup.tar

# Database dumps are usually more portable than a raw volume export
lpod my-app run pg_dump -U postgres -d laravel > backup.sql
```

Restore with `podman volume import laravel-pgsql pgsql-backup.tar`, or replay the dump.
