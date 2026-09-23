---
section: Configuration
order: 2
---

# Devcontainer

The `devcontainer` preset is a [Dev Containers](https://containers.dev/) image for VS Code and JetBrains. You write code inside the container. The `development` and `frankenphp-octane` presets are different: they run your app as a service.

It's commented out by default. Add it to `presets` in `config/podman.php`, or generate it directly.

## Setup

```bash
php artisan podman:generate devcontainer
```

This writes a `Containerfile`, `entrypoint.sh` and four devcontainer configs to `podman/devcontainer/runtimes/`. Your editor looks for `.devcontainer/devcontainer.json`, so symlink the config you want:

```bash
mkdir -p .devcontainer
ln -sf ../podman/devcontainer/runtimes/devcontainer.json .devcontainer/devcontainer.json
```

With a symlink, re-running `podman:generate devcontainer` updates it automatically.

## Choosing a config

Pick prebuilt or local, and with or without AI tools:

| Config | Image | Use it when |
| --- | --- | --- |
| `devcontainer.json` (default) | Prebuilt `ghcr.io/foxws/laravel-podman-devcontainer:php-8.5` (see [CI](ci-build.md)) | You want to start right away |
| `devcontainer-local.json` | Builds the `Containerfile` locally (`--target=base`) | You published the preset and changed the `Containerfile` (extra `PHP_EXTENSIONS`, apt packages, ...) |
| `devcontainer-ai.json` | Prebuilt `php-8.5-ai` image | You want the [AI tools](#ai-variant) without building |
| `devcontainer-local-ai.json` | Builds the `ai` stage locally (`--target=ai`) | You want the AI tools and a local build |

To switch, point the symlink at another config.

## What's inside

Debian (`php:8.5-cli`) with:

- `default-mysql-client`, a PostgreSQL client (`POSTGRES_VERSION`) and `sqlite3`
- PHP extensions: `apcu bcmath exif ffi gd igbinary imagick intl pcntl pdo_mysql pdo_pgsql pdo_sqlite redis sockets zip`, plus any in the `PHP_EXTENSIONS` build arg
- Node.js (`NODE_VERSION`) with `pnpm` and `yarn` through Corepack, and `bun`
- `cpx` (like `npx`, for Composer packages), `gh` and `awscli`

## AI variant

The `ai` configs add these on top of [What's inside](#whats-inside). Each has a build arg that defaults to `latest`. Set it to `false` to skip the tool, or to a version to pin it:

| Tool | Build arg | Pin example |
| --- | --- | --- |
| Claude Code | `CLAUDE_CLI` | `stable`, `2.1.89` |
| OpenAI Codex CLI | `CODEX_CLI` | any npm version |
| [Laravel LSP](https://github.com/laravel/lsp) (`laravel-lsp`) | `AI_LARAVEL_LSP` | `0.0.32` |

To add other npm-based agent CLIs, list them in `AI_NPM_PACKAGES`, e.g. `@google/gemini-cli`. Build args only apply when you build locally.

### Laravel Boost

For Laravel-specific context (routes, database schema, config, Tinker), add [`laravel/boost`](https://github.com/laravel/boost) to your app:

```bash
composer require laravel/boost --dev
php artisan boost:install
```

### Laravel agent skills

Claude Code uses `laravel-lsp` through the `laravel-lsp` plugin from [laravel/agent-skills](https://github.com/laravel/agent-skills). To enable it, and the `laravel` plugin with its agents and skills, add this to `.claude/settings.json` in your project (next to `artisan`, not in `~/.claude`):

```json
{
  "extraKnownMarketplaces": {
    "laravel": {
      "source": { "source": "github", "repo": "laravel/agent-skills" }
    }
  },
  "enabledPlugins": {
    "laravel@laravel": true,
    "laravel-lsp@laravel": true
  }
}
```

- Commit it, and everyone on the project is asked to install the plugins when they trust the project.
- To keep it to yourself, use `.claude/settings.local.json` instead.
- Installed plugins are stored in `~/.claude/plugins`, which the host and container share.
- `laravel-cloud@laravel` and `laravel-nightwatch@laravel` are also available.

### Login persistence

The `ai` configs mount `~/.claude`, `~/.claude.json` and `~/.codex` from your host, so you stay logged in after rebuilding the container.

Create `~/.claude.json` before the first start if it doesn't exist:

```bash
touch ~/.claude.json
```

Otherwise Podman creates a directory with that name, and Claude Code can't use it.

### Using API keys instead

To keep your host logins out of the container, remove the `.claude`/`.codex` mounts from your config and set `ANTHROPIC_API_KEY` or `OPENAI_API_KEY` in `containerEnv`.

API keys are billed separately, per token. A Claude.ai or ChatGPT subscription doesn't include one, so you need an account at [console.anthropic.com](https://console.anthropic.com) or [platform.openai.com](https://platform.openai.com).

## File ownership (UID/GID)

Files you create in the container should be owned by your host user. This works as follows:

1. `podman:generate` writes your host UID/GID into `PUID`/`PGID` in `containerEnv`.
2. The container starts as root, and `entrypoint.sh` changes the `docker` user to that UID/GID.
3. It then switches to `docker` with `gosu`.

Together with `--userns=keep-id` in `runArgs`, this works for local builds and for the prebuilt image, which is always built with UID/GID 1000.

## Links

- [CI: Building a Container Image](ci-build.md)
- [Customizing](customizing.md)
- [Flatpak-packaged editors](flatpak.md), if your editor runs as a Flatpak
- [Introduction](index.md)
