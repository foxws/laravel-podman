---
section: Getting Started
order: 1
---

# Command Reference

`podman:setup`, `podman:publish` and `podman:generate` only render files. They never call `podman`, so they work anywhere PHP runs. If you leave out the preset name, you're asked to pick one.

The rendered output goes to `podman/` by default. Don't commit it: you can regenerate it any time. Installing, starting and removing services is done with [`lpod`](lpod.md).

## `podman:setup`

Renders all default presets. See [Quick start](index.md#quick-start).

```bash
php artisan podman:setup

# Use other presets than the defaults
php artisan podman:setup --preset=production
```

## `podman:publish PRESET`

Copies a preset's `quadlets/` and `runtimes/` files into your project so you can edit them.

```bash
php artisan podman:publish production

# Overwrite files you already published
php artisan podman:publish production --force
```

## `podman:generate PRESET`

Renders one preset into `podman/`, ready for `lpod install`. See [Customizing](customizing.md) for the placeholders it fills in.

```bash
php artisan podman:generate production

# Use a different host path for this run only
php artisan podman:generate development --working-path=/srv/my-app
```

`--working-path` overrides `PODMAN_WORKING_PATH` for a single run, without changing `.env`. See [Setting up without PHP](host-setup.md).

## `podman:s3-setup`

Creates S3 buckets and adds a CORS policy to the ones browsers read from. Needs `aws/aws-sdk-php`. See [S3 Buckets](s3.md).

```bash
php artisan podman:s3-setup
```

## `podman:idle`

Exits successfully when the app has no work in progress, and fails otherwise. `lpod`'s [idle check](ondemand.md#the-idle-check) runs it in the queue worker before stopping the workers. See [What counts as work in progress](ondemand.md#what-counts-as-work-in-progress).

```bash
# Every check for something the app uses (queue, database, scout)
php artisan podman:idle

# Only these checks
php artisan podman:idle --services=queue,database
```

## Backing up volumes

`lpod remove` and `lpod uninstall` delete the service's volumes, and there's no undo.

Databases (`pgsql`, `mysql`, `mariadb`, `mongodb`) are dumped with `lpod`, to `~/.local/share/lpod/backups` by default. `remove` and `uninstall` do this for the running databases they remove, unless you pass `--no-backup`:

```bash
lpod my-app-pgsql backup
lpod my-app-pgsql restore     # lists the backups
lpod my-app-pgsql restore ~/.local/share/lpod/backups/my-app-pgsql-20261001-120000.sql.gz
```

For the other services (`valkey`, `rustfs`, `typesense`, `meilisearch`, `mailpit`), archive the volume. Quadlet names them `systemd-{app}-{service}`; check with `podman volume ls`:

```bash
podman volume export systemd-my-app-rustfs -o rustfs-backup.tar
podman volume import systemd-my-app-rustfs rustfs-backup.tar
```

Database backups need `lpod` v2.3.0 or later. See [`lpod` CLI](lpod.md#databases).
