---
title: Introduction
metadata:
  role: Containers
  eyebrow: "Containers · Podman Quadlet · systemd"
  desc: "Turn your Laravel app's config into Podman Quadlet containers systemd can manage."
  requires: "PHP ^8.4"
  laravel: "11.x / 12.x / 13.x"
  runtime: "Podman (Quadlet)"
  licence: MIT
---

# Introduction

This package turns your Laravel app's config into [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-quadlet.1.html) units. [systemd runs them](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html) as containers on your host. Every bundled part can be swapped for your own, like Nginx instead of Caddy or MySQL instead of Postgres.

## Requirements

- **Linux with systemd**, rootless or system-wide. macOS, Windows and WSL are not supported.
- **Podman** with the `quadlet` CLI plugin. Check with `podman quadlet --help`.

## Installation

```bash
composer require foxws/laravel-podman --dev
```

```bash
php artisan vendor:publish --tag="podman-config"
```

The package only renders files, so a dev dependency is enough. See [Customizing](customizing.md) for all config keys.

Using [Laravel Boost](https://github.com/laravel/boost)? Run `php artisan boost:install` (or `boost:update`) after installing, and your AI agent gets skills for `lpod`, presets and S3 setup.

## Presets

| Preset              | What it is                                                                                                                            |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `development`       | App and services, with your working copy mounted for local editing. **Enabled by default.**                                           |
| `frankenphp-octane` | Production-style image with the app code baked in. Commented out by default.                                                          |
| `devcontainer`      | [Dev Containers](https://containers.dev/) image for VS Code/JetBrains. See [Devcontainer](devcontainer.md). Commented out by default. |
| `proxy`             | [Caddy](https://caddyserver.com/) reverse proxy in front of the other services. **Enabled by default.**                               |
| `s3`                | CORS policy for S3-compatible storage buckets.                                                                                        |

To change a preset, publish it with `php artisan podman:publish frankenphp-octane`. See [Customizing](customizing.md).

## Quick start

1. **Render** the default presets:

    ```bash
    php artisan podman:setup
    ```

2. **Install [`lpod`](lpod.md)** once per host. It's a single bash script and doesn't need PHP:

    ```bash
    curl -fsSL -o ~/.local/bin/lpod https://github.com/foxws/lpod/releases/latest/download/lpod
    chmod +x ~/.local/bin/lpod
    ```

3. **Install** the rendered services:

    ```bash
    lpod install development/app.quadlets --replace
    lpod install development/pgsql.quadlets --replace
    lpod install development/valkey.quadlets --replace
    lpod install proxy/proxy.quadlets --replace
    ```

4. **Set secrets and start:**

    ```bash
    lpod my-app secrets
    lpod pgsql secrets
    lpod my-app up
    lpod my-app open
    ```

5. **Trust the proxy's local certificate** once. See [Proxy](proxy.md#trusting-the-local-certificate).

For frontend work, install the Vite dev server too. Run `pnpm install` first, or it will keep crashing and restarting:

```bash
lpod install development/vite.quadlets --replace
lpod vite up
```

No PHP on the host? See [Setting up without PHP](host-setup.md).

## Commands

| Command                  | Description                                                      |
| ------------------------ | ---------------------------------------------------------------- |
| `podman:setup`           | Render the default presets                                       |
| `podman:publish PRESET`  | Copy a preset into your project so you can edit it               |
| `podman:generate PRESET` | Render a single preset                                           |
| `podman:s3-setup`        | Create S3 buckets and a CORS policy (needs `aws/aws-sdk-php`)    |

Everything else (installing, starting, removing, secrets) is done with [`lpod`](lpod.md). See [Commands](commands.md) for all flags.

> **Warning:** `lpod remove` and `lpod uninstall` delete the service's Podman volumes, including databases and uploads. There's no undo. See [Backing up volumes](commands.md#backing-up-volumes).

## Links

- [CHANGELOG](https://github.com/foxws/laravel-podman/blob/main/CHANGELOG.md)
- [foxws/lpod](https://github.com/foxws/lpod)
- [Podman Quadlet reference](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html)
- [Flatpak-packaged editors](flatpak.md)
