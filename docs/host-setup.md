# Setting up without PHP on the host

`podman:generate`/`podman:setup` only render files — they never touch the `podman` binary, so they run anywhere PHP does. Output is a build artifact: don't commit it, delete/regenerate it any time.

**Normal workflow:** render on your dev machine as usual (`php artisan podman:setup`), copy the generated `podman/` folder to production, install it there with [`lpod`](https://github.com/foxws/lpod). No PHP needed on production, and `lpod` doesn't need this package installed either.

## No PHP anywhere

No PHP on the rendering machine either? Render inside a disposable container, using the same `php:8.5-cli` image `lpod-setup` defaults to:

```bash
podman run --rm --userns=keep-id -u "$(id -u):$(id -g)" \
    -e PODMAN_WORKING_PATH="$PWD" \
    -v "$PWD":/var/www/html:Z -w /var/www/html docker.io/library/php:8.5-cli \
    php artisan podman:setup --preset=frankenphp-octane

# On the host: install, then set secrets
lpod install frankenphp-octane/pgsql.quadlets --replace
lpod pgsql secrets
```

Example output for an app named `acme` — `podman/frankenphp-octane/valkey.quadlets`:

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
```

```bash
lpod install frankenphp-octane/valkey.quadlets --replace
```

- `PODMAN_WORKING_PATH`/`--working-path=` only change host paths *baked into* the rendered files — rendering itself always happens from `/var/www/html` here.
- `--userns=keep-id -u "$(id -u):$(id -g)"` keeps generated files owned by you, not root. Keep `:Z` on SELinux hosts.
- `lpod setup` wraps the `podman run` command above, once [`lpod`](https://github.com/foxws/lpod) and this package's `lpod-setup` (copied next to it) are both on the host. Add `--install` to install immediately, or `--secrets` to also set secrets.
- `lpod` needs nothing from this project once services are installed — that's what makes production install truly standalone. `lpod-setup` still shells out to `php artisan podman:setup`, needing this project's `vendor/` — why copying already-rendered output is the normal path for production.

See [The `lpod` CLI](lpod.md) for the full command reference.
