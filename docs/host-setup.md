---
section: Advanced
order: 1
---

# Setting up without PHP on the host

`podman:setup` and `podman:generate` only render files. You can delete and regenerate the output any time, so don't commit it.

**Usual workflow:** render on your dev machine with `php artisan podman:setup`, copy the `podman/` folder to the server, and install it there with [`lpod`](lpod.md). The server needs neither PHP nor this package.

## No PHP anywhere

If the machine you render on has no PHP either, run Composer and Artisan in throwaway containers. These are the same `composer` and `php:8.5-cli` images `lpod setup` uses:

```bash
# vendor/ must exist before "podman:setup" can run
podman run --rm --userns=keep-id -u "$(id -u):$(id -g)" \
    -v "$PWD":/app:Z -w /app docker.io/library/composer:2 \
    install --no-dev --optimize-autoloader --no-interaction

podman run --rm --userns=keep-id -u "$(id -u):$(id -g)" \
    -e PODMAN_WORKING_PATH="$PWD" \
    -v "$PWD":/var/www/html:Z -w /var/www/html docker.io/library/php:8.5-cli \
    php artisan podman:setup --preset=frankenphp-octane

# Back on the host: install and set secrets
lpod install frankenphp-octane/pgsql.quadlets --replace
lpod pgsql secrets
```

For an app named `acme`, this is what `podman/frankenphp-octane/valkey.quadlets` looks like:

```ini
# FileName=acme-valkey
[Unit]
Description=Valkey container

[Container]
Image=docker.io/valkey/valkey:latest
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
lpod install frankenphp-octane/valkey.quadlets --replace
```

Good to know:

- `PODMAN_WORKING_PATH` (or `--working-path=`) sets the host path written into the rendered files. The container itself always renders from `/var/www/html`.
- `--userns=keep-id -u "$(id -u):$(id -g)"` makes you, not root, the owner of the generated files. Keep `:Z` on SELinux hosts.
- `lpod setup` runs the commands above for you. Add `--install` to install right away, or `--secrets` to also set secrets.
- `lpod setup` still needs this project's `vendor/`, because it runs `php artisan podman:setup`. That's why copying pre-rendered files is the usual way to deploy.

See [`lpod` CLI](lpod.md) for all commands.
