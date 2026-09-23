---
section: Getting Started
order: 1
---

# Command Reference

These Artisan commands only render files. They never call `podman`, so they work anywhere PHP runs. If you leave out the preset name, you'll be asked to pick one.

Installing, starting and removing services is done with [`lpod`](lpod.md). The rendered output goes to `podman/` by default. Don't commit it; you can regenerate it any time.

## `podman:setup`

Renders all default presets. See [Quick start](index.md#quick-start).

```bash
php artisan podman:setup

# Use other presets than the defaults
php artisan podman:setup --preset=frankenphp-octane
```

## `podman:publish PRESET`

Copies a preset's `quadlets/` and `runtimes/` files into your project so you can edit them.

```bash
php artisan podman:publish frankenphp-octane

# Overwrite files you already published
php artisan podman:publish frankenphp-octane --force
```

## `podman:generate PRESET`

Renders one preset into `podman/`, ready for `lpod install`. See [Customizing](customizing.md) for the placeholders it fills in.

```bash
php artisan podman:generate frankenphp-octane

# Use a different host path for this run only
php artisan podman:generate development --working-path=/srv/my-app
```

`--working-path` overrides `PODMAN_WORKING_PATH` for a single run, without changing `.env`. See [Setting up without PHP](host-setup.md).

## `podman:s3-setup`

Creates S3 buckets and adds a CORS policy to the ones browsers read from. Needs `aws/aws-sdk-php`. See [S3 Buckets](s3.md).

```bash
php artisan podman:s3-setup
```

## Backing up volumes

`lpod remove` and `lpod uninstall` delete the service's volumes, and there's no undo. Back up anything you want to keep first (`pgsql`, `valkey`, `rustfs`, `typesense`, `mailpit`):

```bash
# Archive any named volume
podman volume export laravel-pgsql -o pgsql-backup.tar

# For databases, a dump is usually easier to restore elsewhere
lpod my-app run pg_dump -U postgres -d laravel > backup.sql
```

To restore, run `podman volume import laravel-pgsql pgsql-backup.tar`, or import the dump.
