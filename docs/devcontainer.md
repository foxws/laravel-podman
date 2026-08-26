# Devcontainer

The `devcontainer` preset is a [VS Code/JetBrains Dev Containers](https://containers.dev/) image for developing your Laravel app itself inside a container — separate from the `development`/`frankenphp-octane` presets, which run the app as a service. It's commented out by default; add it to `presets` in `config/podman.php` (or generate it directly) to use it.

## Setup

```bash
php artisan podman:generate devcontainer
```

This renders `stubs/devcontainer/runtimes/` into `podman/devcontainer/runtimes/` — `Containerfile`, `entrypoint.sh`, and two devcontainer configs (see below). VS Code/JetBrains look for `.devcontainer/devcontainer.json` at the project root, not under `podman/`, so symlink whichever config you want to use:

```bash
mkdir -p .devcontainer
ln -sf ../podman/devcontainer/runtimes/devcontainer.json .devcontainer/devcontainer.json
```

A symlink (rather than a copy) means re-running `podman:generate devcontainer` keeps it current automatically.

## Two configs, one choice

- **`devcontainer.json`** (default) — `"image": "ghcr.io/foxws/laravel-podman-devcontainer:php-8.5"`, the prebuilt image from this repo's own CI (see [CI: Building a Container Image](ci-build.md)). Starts immediately, no local build.
- **`devcontainer-local.json`** — builds `podman/devcontainer/runtimes/Containerfile` instead. Use this one if you ran `podman:publish devcontainer` and edited the Containerfile (extra `PHP_EXTENSIONS`, apt packages, etc.) — the prebuilt image won't reflect those changes.

Symlink `.devcontainer/devcontainer.json` at whichever one you need; switching later is just repointing the symlink.

## What's inside

Debian-based (`php:8.5-cli`), with:

- `default-mysql-client`, PostgreSQL client (version-pinned via `POSTGRES_VERSION`), `sqlite3`
- PHP extensions: `apcu bcmath exif ffi gd igbinary imagick intl pcntl pdo_mysql pdo_pgsql pdo_sqlite redis sockets zip`, plus whatever you pass via the `PHP_EXTENSIONS` build arg
- Node.js (version-pinned via `NODE_VERSION`) with `pnpm`/`yarn` via Corepack, and `bun`
- `cpx` (Composer's `npx` equivalent), `gh`, `awscli`

## UID/GID handling

The container starts as root; `entrypoint.sh` renumbers the `docker` user to `PUID`/`PGID` (from `containerEnv`, which `podman:generate` fills in from your actual host UID/GID) before dropping privileges via `gosu`. Combined with `--userns=keep-id:uid=...,gid=...` in `runArgs`, this keeps the container's `docker` user aligned with your host user's file ownership on the bind-mounted workspace — whether you're running the locally-built image or the prebuilt one from GHCR (which is always built with UID/GID 1000, regardless of your actual host UID).

## Links

- [CI: Building a Container Image](ci-build.md)
- [Customizing](customizing.md)
- [Introduction](index.md)
