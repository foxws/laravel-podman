# Setting up without PHP on the host

`podman:generate`/`podman:setup` only render files — substituting the `{{...}}` placeholders (see [Customizing](customizing.md)) into `publish_path` (`podman/{preset}/` by default). They never touch the `podman` binary, so they run anywhere PHP does. Generated output is a build artifact: don't commit it, and feel free to delete/regenerate it any time.

**Normal workflow:** render on your development machine like usual (`php artisan podman:setup`), then copy the generated `podman/` folder to production and install it there with the standalone `lpod`/`lpod-secrets` scripts — no `vendor/bin`, no PHP needed on production at all.

## No PHP anywhere

If you don't have PHP on the rendering machine either (a bare server, minimal CI image), render inside a disposable container and install on the host where Podman actually runs — using the same `php:8.5-cli` image `lpod-setup` uses by default:

```bash
podman run --rm --userns=keep-id -u "$(id -u):$(id -g)" \
    -e PODMAN_WORKING_PATH="$PWD" \
    -v "$PWD":/var/www/html:Z -w /var/www/html docker.io/library/php:8.5-cli \
    php artisan podman:setup --preset=frankenphp-octane

# On the host: install each rendered service, then set its secrets
vendor/bin/lpod install frankenphp-octane/pgsql.quadlets --replace
vendor/bin/lpod secrets pgsql
```

For example, an app named `acme` renders `podman/frankenphp-octane/valkey.quadlets` as:

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

`lpod install frankenphp-octane/valkey.quadlets --replace` turns that into a running service.

> **Notes**
> - `PODMAN_WORKING_PATH` (or `--working-path=` on `podman:generate`) only changes host paths *baked into* the rendered files (`SetWorkingDirectory=`, etc.) — rendering itself always happens from `/var/www/html` here.
> - `--userns=keep-id -u "$(id -u):$(id -g)"` keeps generated files owned by you, not root; keep `:Z` on the mount on SELinux hosts.
> - Once `lpod` is on the host, `lpod setup` wraps the `podman run` command above. Add `--install` to install each rendered service immediately, or `--secrets` to also set secrets (implies `--install`).
> - `lpod`/`lpod-setup`/`lpod-secrets` are plain bash scripts, copyable anywhere on `PATH` (or your dotfiles) with no Composer dependency of their own. `lpod`/`lpod-secrets` need nothing else once services are installed — that's what makes production install truly standalone. `lpod-setup` still shells out to `php artisan podman:setup`, so wherever it renders still needs this project's own `vendor/` — which is exactly why copying already-rendered output is the normal path for production, rather than rendering there too.

See [The `lpod` CLI](lpod.md) for the full command reference.
