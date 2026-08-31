# Devcontainer

The `devcontainer` preset is a [VS Code/JetBrains Dev Containers](https://containers.dev/) image for developing your Laravel app itself inside a container — separate from the `development`/`frankenphp-octane` presets, which run the app as a service. It's commented out by default; add it to `presets` in `config/podman.php` (or generate it directly) to use it.

## Setup

```bash
php artisan podman:generate devcontainer
```

This renders `stubs/devcontainer/runtimes/` into `podman/devcontainer/runtimes/` — `Containerfile`, `entrypoint.sh`, and four devcontainer configs (see below). VS Code/JetBrains look for `.devcontainer/devcontainer.json` at the project root, not under `podman/`, so symlink whichever config you want to use:

```bash
mkdir -p .devcontainer
ln -sf ../podman/devcontainer/runtimes/devcontainer.json .devcontainer/devcontainer.json
```

A symlink (rather than a copy) means re-running `podman:generate devcontainer` keeps it current automatically.

## Four configs, two choices

Two independent choices, prebuilt-vs-local and default-vs-ai, give four configs:

- **`devcontainer.json`** (default) — `"image": "ghcr.io/foxws/laravel-podman-devcontainer:php-8.5"`, the prebuilt image from this repo's own CI (see [CI: Building a Container Image](ci-build.md)). Starts immediately, no local build.
- **`devcontainer-local.json`** — builds `podman/devcontainer/runtimes/Containerfile` (`--target=base`) instead. Use this one if you ran `podman:publish devcontainer` and edited the Containerfile (extra `PHP_EXTENSIONS`, apt packages, etc.) — the prebuilt image won't reflect those changes.
- **`devcontainer-ai.json`** — same as `devcontainer.json`, but pulls the `php-8.5-ai` tag, which adds the AI CLIs below.
- **`devcontainer-local-ai.json`** — same as `devcontainer-local.json`, but builds the Containerfile's `ai` stage (`--target=ai`).

Symlink `.devcontainer/devcontainer.json` at whichever one you need; switching later is just repointing the symlink.

## What's inside

Debian-based (`php:8.5-cli`), with:

- `default-mysql-client`, PostgreSQL client (version-pinned via `POSTGRES_VERSION`), `sqlite3`
- PHP extensions: `apcu bcmath exif ffi gd igbinary imagick intl pcntl pdo_mysql pdo_pgsql pdo_sqlite redis sockets zip`, plus whatever you pass via the `PHP_EXTENSIONS` build arg
- Node.js (version-pinned via `NODE_VERSION`) with `pnpm`/`yarn` via Corepack, and `bun`
- `cpx` (Composer's `npx` equivalent), `gh`, `awscli`

## AI variant

`devcontainer-ai.json`/`devcontainer-local-ai.json` add `@anthropic-ai/claude-code` and `@openai/codex` on top of everything in [What's inside](#whats-inside), plus whatever npm-installable agent CLI you pass via the `AI_NPM_PACKAGES` build arg (e.g. `@google/gemini-cli`) — only relevant if you're building locally, since it only takes effect on the `ai` target.

### Laravel-specific context

For Laravel-specific context (routes, DB schema, config, Tinker) rather than a generic filesystem view, pair either CLI with [`laravel/boost`](https://github.com/laravel/boost) in your app itself — it's a per-project Composer package (`composer require laravel/boost --dev && php artisan boost:install`), not something this Containerfile installs.

### Login persistence

The `ai` configs bind-mount `~/.claude`, `~/.claude.json`, and `~/.codex` from the host into the container (read-write, unlike the read-only `.ssh` mount), so `claude`/`codex` stay logged in across container rebuilds instead of asking you to authenticate every time.

`~/.claude.json` is a file, not a directory — if it doesn't exist yet on your host, create it before first launching the container (`touch ~/.claude.json`), otherwise Podman will create an empty *directory* in its place and Claude Code won't be able to use it. `~/.claude` and `~/.codex` don't have this problem; Podman creates them as directories automatically if missing.

### Using API keys instead

If you'd rather not share host credentials with the container at all, drop the `.claude`/`.codex` mounts from your copy of the config and set `ANTHROPIC_API_KEY`/`OPENAI_API_KEY` in `containerEnv` instead. Note this isn't just a simpler login: it's a different product with separate billing — a Claude.ai/ChatGPT consumer subscription can't be used as an API key, so this route requires a pay-per-token account at [console.anthropic.com](https://console.anthropic.com)/[platform.openai.com](https://platform.openai.com) in addition to (or instead of) your regular subscription.

## UID/GID handling

The container starts as root; `entrypoint.sh` renumbers the `docker` user to `PUID`/`PGID` (from `containerEnv`, which `podman:generate` fills in from your actual host UID/GID) before dropping privileges via `gosu`. Combined with `--userns=keep-id:uid=...,gid=...` in `runArgs`, this keeps the container's `docker` user aligned with your host user's file ownership on the bind-mounted workspace — whether you're running the locally-built image or the prebuilt one from GHCR (which is always built with UID/GID 1000, regardless of your actual host UID).

## Links

- [CI: Building a Container Image](ci-build.md)
- [Customizing](customizing.md)
- [Flatpak-packaged editors](flatpak.md) — if VS Code/JetBrains itself runs sandboxed
- [Introduction](index.md)
