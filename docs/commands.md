---
section: Getting Started
order: 1
---

# Command Reference

The package looks for preset folders on disk (`quadlets/` + `runtimes/`) and exposes them through the Artisan commands below. Leave out the preset name and it will ask you to pick one.

These commands only render files. They never touch `podman`, so they work anywhere PHP runs. Installing, listing, removing, and setting secrets is [`lpod`](https://github.com/foxws/lpod)'s job instead — see [`lpod` CLI](lpod.md). The rendered output (`publish_path`, `podman/` by default) is a build artifact, so don't commit it.

## `podman:setup`

Generates the default set of presets in one go — see [Quick Start](index.md#quick-start).

```bash
php artisan podman:setup

# Override the default presets
php artisan podman:setup --preset=frankenphp-octane
```

## `podman:publish PRESET`

Publishes a preset's `quadlets/` and `runtimes/` files so you can customize them.

```bash
php artisan podman:publish frankenphp-octane

# Overwrite already-published files
php artisan podman:publish frankenphp-octane --force
```

## `podman:generate PRESET`

Renders a single preset (see [Customizing](customizing.md) for the available placeholders) into the publish path, ready for `lpod install`.

```bash
php artisan podman:generate frankenphp-octane

# Override podman.working_path for this run
php artisan podman:generate development --working-path=/srv/my-app
```

`--working-path` overrides `working_path` (normally set via `PODMAN_WORKING_PATH`) for one run only, without touching `.env` — see [Setting up without PHP](host-setup.md).

## `podman:s3-setup`

Creates S3 buckets and applies a CORS policy to the ones browsers read directly. Requires `aws/aws-sdk-php` — see [S3 Buckets](s3.md).

```bash
php artisan podman:s3-setup
```

## Backing up volumes

`lpod remove`/`lpod uninstall` delete the Podman volumes they own, with no undo. Back these up first if they hold data you care about (`pgsql`, `valkey`, `rustfs`, `typesense`, `mailpit`):

```bash
# Generic: archive any named volume to a tarball
podman volume export laravel-pgsql -o pgsql-backup.tar

# Database dumps are usually more portable than a raw volume export
lpod my-app run pg_dump -U postgres -d laravel > backup.sql
```

Restore with `podman volume import laravel-pgsql pgsql-backup.tar`, or replay the dump.
