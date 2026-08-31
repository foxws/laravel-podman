---
slug: /
sidebar_position: 1
---

# Introduction

Renders [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-quadlet.1.html) units from your Laravel app's config, then installs them as [systemd-managed](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html) containers on your host — no all-in-one runtime, no lock-in. Swap any bundled part (Caddy for Nginx, Postgres for MySQL) for your own.

## Requirements

- **Linux with systemd** (rootless or system-wide) — macOS, Windows, and WSL are not supported
- **Podman** with the `quadlet` CLI plugin (`podman quadlet --help` should work)

## Installation

```bash
composer require foxws/laravel-podman --dev
```

```bash
php artisan vendor:publish --tag="podman-config"
```

Only needed to render Quadlet units — install as a dev dependency and skip it in production. See [Customizing](customizing.md) for every config key.

## Presets

| Preset              | What it is                                                                                                                        |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `development`       | App + services, working copy live-mounted for local editing. **Enabled by default.**                                              |
| `frankenphp-octane` | Production-style image, app code baked in. Commented out by default.                                                              |
| `devcontainer`      | VS Code/JetBrains [Dev Containers](https://containers.dev/) image. Commented out by default. See [Devcontainer](devcontainer.md). |
| `proxy`             | [Caddy](https://caddyserver.com/) reverse proxy in front of the other services. **Enabled by default.**                           |
| `s3`                | CORS policy for S3-compatible storage buckets.                                                                                    |

Custom presets: publish one (`php artisan podman:publish frankenphp-octane`) without touching the others — see [Customizing](customizing.md).

## Quick start

1. **Render** the default presets:

    ```bash
    php artisan podman:setup
    ```

2. **Install [`lpod`](https://github.com/foxws/lpod)** once per host — a dependency-free script, no PHP/Composer needed:

    ```bash
    curl -fsSL -o ~/.local/bin/lpod https://github.com/foxws/lpod/releases/latest/download/lpod
    chmod +x ~/.local/bin/lpod
    ```

3. **Install** each rendered service (the only step that needs `podman` itself):

    ```bash
    lpod install development/app.quadlets --replace
    lpod install development/pgsql.quadlets --replace
    lpod install development/valkey.quadlets --replace
    lpod install proxy/proxy.quadlets --replace
    ```

4. **Set secrets, then start:**

    ```bash
    lpod my-app secrets
    lpod pgsql secrets
    lpod my-app up
    lpod my-app open
    ```

Trust the proxy's local certificate once — see [Proxy](proxy.md#trusting-the-local-certificate).

Working on frontend assets? Vite's dev server is opt-in, not part of the default bundle (it needs `pnpm install` run first, or it'll crash-loop):

```bash
lpod install development/vite.quadlets --replace
lpod vite up
```

No PHP on the host? `lpod setup` renders the same way without it — see [Setting up without PHP](host-setup.md).

## Commands reference

| Command                  | Description                                                      |
| ------------------------ | ---------------------------------------------------------------- |
| `podman:setup`           | Generate the default set of presets in one go                    |
| `podman:publish PRESET`  | Publish a preset for customization                               |
| `podman:generate PRESET` | Render a single preset                                           |
| `podman:s3-setup`        | Create S3 buckets and a CORS policy (requires `aws/aws-sdk-php`) |

Installing, listing, removing, and setting secrets is [`lpod`](https://github.com/foxws/lpod)'s job, not Artisan's. Full flag reference: [Commands](commands.md).

> **Warning:** `lpod remove`/`lpod uninstall` delete the Podman volumes they own (databases, uploads, search indexes), with no undo — see [Backing up volumes](commands.md#backing-up-volumes).

## The `lpod` utility

[`lpod`](https://github.com/foxws/lpod) is a separate, dependency-free bash script — no PHP, Composer, or this package required to run it. [`lpod-setup`](https://github.com/foxws/lpod), which ships alongside it, renders presets inside a disposable container for hosts with Podman but no PHP. `lpod setup` is a shortcut for it. See [`lpod` CLI](lpod.md).

## Links

- [CHANGELOG](https://github.com/foxws/laravel-podman/blob/main/CHANGELOG.md)
- [foxws/lpod](https://github.com/foxws/lpod) — the CLI this package pairs with
- [Podman Quadlet reference](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html)
- [Flatpak-packaged editors](flatpak.md) — running Podman-based workflows from a sandboxed VSCode/JetBrains/Zed
