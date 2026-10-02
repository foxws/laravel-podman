---
title: Introduction
metadata:
  role: Containers
  group: deploy
  eyebrow: "Containers · Podman Quadlet · systemd"
  desc: "Turn your Laravel app's config into Podman Quadlet containers systemd can manage."
  lead: "Run your Laravel app and its services as Podman containers that systemd manages, in development and production. When nobody is using them, they sleep."
  requires: "PHP ^8.4"
  laravel: "11.x / 12.x / 13.x"
  runtime: "Podman (Quadlet)"
  licence: MIT
  used_by:
    - name: Stry
      desc: "A self-hosted video streaming app."
      href: "https://github.com/francoism90/stry"
    - name: foxws.nl
      desc: "This site."
      href: "https://foxws.nl"
---

# Introduction

This package turns your Laravel app's config into [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-quadlet.1.html) units. [systemd runs them](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html) as containers on your host. Every bundled part can be swapped for your own, like Nginx instead of Caddy or MySQL instead of Postgres.

## Requirements

- **Linux with systemd**, rootless or system-wide. macOS, Windows and WSL are not supported.
- **Podman** with the `quadlet` CLI plugin. Check with `podman quadlet --help`.

## Installation

```bash
composer require foxws/laravel-podman
```

```bash
php artisan vendor:publish --tag="podman-config"
```

Install it as a regular dependency, not with `--dev`. The idle check runs `php artisan podman:idle` inside your production containers. See [Customizing](customizing.md) for all config keys.

If you use [Laravel Boost](https://github.com/laravel/boost), run `php artisan boost:install` (or `boost:update`) after installing. Your AI agent then gets skills for `lpod`, presets, S3 setup and upgrading.

## Presets

| Preset              | What it is                                                                                                                            |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `development`       | App and services, with your working copy mounted for local editing. **Enabled by default.**                                           |
| `production`        | [FrankenPHP](https://frankenphp.dev/) and [Octane](https://laravel.com/docs/octane) image with the app code baked in, for servers. Commented out by default. |
| `devcontainer`      | [Dev Containers](https://containers.dev/) image for VS Code/JetBrains. See [Devcontainer](devcontainer.md). Commented out by default. |
| `ondemand`          | Socket that starts the app on its first request, so it and its services can sleep when idle. See [On-demand services](ondemand.md). **Enabled by default.** |
| `proxy`             | [Caddy](https://caddyserver.com/) reverse proxy in front of the other services. **Enabled by default.**                               |
| `s3`                | CORS policy for S3-compatible storage buckets.                                                                                        |

To change a preset, publish it with `php artisan podman:publish production`. See [Customizing](customizing.md).

## Quick start

1. **Render** the default presets:

    ```bash
    php artisan podman:setup
    ```

2. **Install [`lpod`](lpod.md)** once per host. It's a single bash script and doesn't need PHP. Upgrade it later with `lpod self-update`:

    ```bash
    curl -fsSL https://github.com/foxws/lpod/releases/latest/download/install.sh | bash
    ```

3. **Install** the rendered services:

    ```bash
    lpod install development/app.quadlets --replace
    lpod install development/pgsql.quadlets --replace
    lpod install development/valkey.quadlets --replace
    lpod install ondemand/my-app-ondemand.socket --replace
    lpod install proxy/proxy.quadlets --replace
    lpod idle enable my-app
    ```

    Replace `my-app` with your app's name (`APP_NAME`, kebab-cased).

4. **Open** your app. The first request starts it:

    ```bash
    lpod my-app open
    ```

   The app starts on its first request and stops again after 10 minutes without traffic. See [On-demand services](ondemand.md) to change that, or to keep it running with `PODMAN_ONDEMAND_ENABLED=false`.

5. **Trust the proxy's local certificate** once. See [Proxy](proxy.md#trusting-the-local-certificate).

For frontend work, install the Vite dev server too. Run `pnpm install` first, or it will keep crashing and restarting:

```bash
lpod install development/vite.quadlets --replace
lpod my-app-vite up
```

For `production`, also set the secrets it expects, such as your `.env` and the database password, with `lpod my-app secrets` and `lpod my-app-pgsql secrets`.

To render presets on a host without PHP, see [Setting up without PHP](host-setup.md).

## Commands

| Command                  | Description                                                      |
| ------------------------ | ---------------------------------------------------------------- |
| `podman:setup`           | Render the default presets                                       |
| `podman:publish PRESET`  | Copy a preset into your project so you can edit it               |
| `podman:generate PRESET` | Render a single preset                                           |
| `podman:s3-setup`        | Create S3 buckets and a CORS policy (needs `aws/aws-sdk-php`)    |
| `podman:idle`            | Succeed when the app has no work in progress. The idle check runs it |

Everything else (installing, starting, removing, secrets) is done with [`lpod`](lpod.md). See [Commands](commands.md) for all flags.

> **Warning:** `lpod remove` and `lpod uninstall` delete the service's Podman volumes, including databases and uploads. There's no undo. See [Backing up volumes](commands.md#backing-up-volumes).

## Links

- [CHANGELOG](https://github.com/foxws/laravel-podman/blob/main/CHANGELOG.md)
- [foxws/lpod](https://github.com/foxws/lpod)
- [Podman Quadlet reference](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html)
- [Flatpak-packaged editors](flatpak.md)
