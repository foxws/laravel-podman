---
section: Advanced
order: 1
---

# Setting up without PHP on the host

`podman:setup` and `podman:generate` only render files, so the server that runs your services doesn't need PHP or this package. There are three ways to get the rendered files onto it.

## Render elsewhere and copy the files

This is the usual way. Render on your dev machine or in CI, copy the `podman/` folder to the server, and install it there with [`lpod`](lpod.md):

```bash
php artisan podman:setup
rsync -a podman/ server:my-app/podman/
```

## Render on the server with `lpod setup`

`lpod setup` runs Composer and Artisan in throwaway containers on the server. Add `--install` to install the rendered services right away, and `--secrets` to also set their secrets:

```bash
lpod setup --install --secrets
```

Run it from a checkout of the project. It installs `vendor/` itself with `composer install --no-dev`, then runs `php artisan podman:setup`.

## Render by hand in containers

This is what `lpod setup` does for you. It uses the same `composer` and `php:8.5-cli` images:

```bash
# vendor/ must exist before "podman:setup" can run
podman run --rm --userns=keep-id -u "$(id -u):$(id -g)" \
    -v "$PWD":/app:Z -w /app docker.io/library/composer:2 \
    install --no-dev --optimize-autoloader --no-interaction

podman run --rm --userns=keep-id -u "$(id -u):$(id -g)" \
    -e PODMAN_WORKING_PATH="$PWD" \
    -v "$PWD":/var/www/html:Z -w /var/www/html docker.io/library/php:8.5-cli \
    php artisan podman:setup --preset=production

# Back on the host: install and set secrets
lpod install production/pgsql.quadlets --replace
lpod my-app-pgsql secrets
```

For an app named `acme`, this is what `podman/production/valkey.quadlets` looks like:

```ini
# FileName=acme-valkey
[Unit]
Description=Valkey container

[Container]
Image=docker.io/valkey/valkey:9
AutoUpdate=registry
Exec=valkey-server --save --loglevel warning
Volume=acme-valkey:/data:rw,Z,U
Network=acme.network
ExposeHostPort=6379

[Service]
Restart=always
RestartSec=5
TimeoutStartSec=120
TimeoutStopSec=60
---
# FileName=acme-valkey
[Volume]
Label=acme-valkey
VolumeName=systemd-acme-valkey
```

```bash
lpod install production/valkey.quadlets --replace
```

Notes:

- `PODMAN_WORKING_PATH` (or `--working-path=`) sets the host path written into the rendered files. The container itself always renders from `/var/www/html`.
- `--userns=keep-id -u "$(id -u):$(id -g)"` makes you, not root, the owner of the generated files. Keep `:Z` on SELinux hosts.

See [`lpod` CLI](lpod.md) for all commands.
