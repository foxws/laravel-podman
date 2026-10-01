---
section: Getting Started
order: 2
---

# `lpod` CLI

`lpod` is a bash script for managing [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html) services. It wraps `podman exec`, `podman quadlet` and `systemctl`, and passes unknown commands on to `podman`. It doesn't need PHP, Composer or this package.

The source and releases are at [foxws/lpod](https://github.com/foxws/lpod).

## Installation

```bash
curl -fsSL https://github.com/foxws/lpod/releases/latest/download/install.sh | bash
```

The installer:

- puts `lpod` and `lpod-setup` in `~/.local/bin`, or `/usr/local/bin` as root;
- checks the downloads against the release's checksums;
- writes the systemd templates for the [idle check](ondemand.md#the-idle-check);
- offers to enable linger, so your services start at boot.

To install a specific version, set `LPOD_VERSION`, e.g. `LPOD_VERSION=v2.2.0`. You can also [install it by hand](https://github.com/foxws/lpod/blob/main/docs/installation.md).

### Upgrading

```bash
lpod --version
lpod self-update           # the latest release
lpod self-update v2.2.0    # or a specific one
```

## Usage

```bash
lpod SERVICE COMMAND [options] [arguments]
```

`SERVICE` is a Quadlet service name, like your app or `pgsql`. These commands don't take a service: `setup`, `install`, `remove`, `uninstall`, `list`, `print`, `reload`, `idle` and `self-update`.

Quadlet names the container `systemd-SERVICE` (e.g. `systemd-my-app`). `lpod` adds that prefix for you, so always use the plain name.

## Commands

### Lifecycle

| Command             | Description                                      |
| -------------------- | -------------------------------------------------- |
| `lpod app up`      | Start the service                                 |
| `lpod app down`    | Stop the service                                  |
| `lpod app restart` | Restart the service                               |
| `lpod app status`  | Show the service's status                         |
| `lpod app secrets` | Prompt for and set the service's Quadlet secrets  |

### Artisan, PHP & Composer

| Command                     | Description                                |
| ----------------------------- | -------------------------------------------- |
| `lpod app artisan ...`      | Run an Artisan command (`art`/`a`)         |
| `lpod app php ...`           | Run PHP                                     |
| `lpod app composer ...`      | Run Composer                                |
| `lpod app debug ARTISAN...` | Run an Artisan command with Xdebug enabled |
| `lpod app xdebug on [MODE]` | Turn [Xdebug](#xdebug) on for web requests and workers |
| `lpod app xdebug off`        | Turn Xdebug off again                      |
| `lpod app tinker`             | Start a Tinker session                      |

### Databases

| Command | Description |
| --- | --- |
| `lpod my-app-pgsql client ...` | Open `psql`, `mysql`, `mariadb` or `mongosh`, already logged in |

This works on the running `pgsql`, `mysql`, `mariadb` and `mongodb` services. `lpod` logs in with the credentials in the container's environment, from plain values or Podman secrets. Extra arguments go to the client, e.g. `lpod my-app-pgsql client -c 'select 1'`.

### Node, npm, pnpm, Yarn & Bun

| Command            | Description |
| -------------------- | ----------- |
| `lpod app node ...` | Run Node    |
| `lpod app npm ...`  | Run npm     |
| `lpod app pnpm ...` | Run pnpm    |
| `lpod app yarn ...` | Run Yarn    |
| `lpod app bun ...`  | Run Bun     |

`npx`, `pnpx`, and `bunx` work the same way.

### Testing

| Command                | Description                              |
| ------------------------ | ------------------------------------------- |
| `lpod app test`        | `php artisan test`                       |
| `lpod app phpunit ...` | Run PHPUnit                              |
| `lpod app pest ...`    | Run Pest                                 |
| `lpod app pint ...`    | Run Pint                                 |
| `lpod app dusk`        | Run Dusk tests (requires `laravel/dusk`) |
| `lpod app dusk:fails`  | Re-run previously failed Dusk tests      |

### Container CLI & other

| Command                | Description                                       |
| ------------------------- | ---------------------------------------------------- |
| `lpod app shell`      | Shell into the container (alias `bash`)             |
| `lpod app root-shell` | Root shell into the container (alias `root-bash`)   |
| `lpod app bin TOOL`   | Run `./vendor/bin/TOOL`                             |
| `lpod app run CMD`    | Run an arbitrary command in the container           |
| `lpod app open`       | Open the app URL in the browser                     |
| `lpod proxy export-cert [PATH]` | Export the proxy's local CA certificate (default `~/proxy.crt`) |

### Quadlet management

| Command                                | Description                                     |
| ----------------------------------------- | -------------------------------------------------- |
| `lpod install PRESET/SERVICE.quadlets` | Install a rendered Quadlet                       |
| `lpod install PRESET/UNIT.socket`      | Install and enable a rendered socket or timer (see [On-demand services](ondemand.md)) |
| `lpod install devcontainer/CONFIG.json` | Copy a rendered config to `.devcontainer/devcontainer.json` (see [Devcontainer](devcontainer.md)) |
| `lpod remove NAME`                     | Remove an installed Quadlet, socket or timer      |
| `lpod uninstall APPLICATION`           | Remove an application and all of its Quadlets     |
| `lpod list`                            | List installed Quadlets                           |
| `lpod print NAME`                      | Print the generated systemd unit                  |
| `lpod reload`                          | Reload the systemd manager configuration (`daemon-reload`) |
| `lpod setup ...`                       | Render presets without PHP. See [Setting up without PHP](host-setup.md) |

All of these except `reload` accept the same flags as `podman quadlet` (`--replace`, `--application`, `--force`, `--ignore`, ...).

> **Warning:** `remove` and `uninstall` also delete the service's volumes. See [Backing up volumes](commands.md#backing-up-volumes).

### On-demand idle check

| Command | Description |
| --- | --- |
| `lpod idle enable APP` | Run the [idle check](ondemand.md#the-idle-check) for the app every minute |
| `lpod idle disable APP` | Stop running it |
| `lpod idle APP` | Run the check once |

### Xdebug

The `development` image has Xdebug with `xdebug.mode=off`, so it costs nothing until you turn it on:

```bash
lpod my-app xdebug on                 # or a mode, e.g. "debug,profile"
lpod my-app xdebug off
lpod my-app debug queue:work          # one Artisan command
```

`xdebug on` adds a Quadlet drop-in that sets `XDEBUG_MODE`, and restarts the app. Sessions start for requests with `XDEBUG_TRIGGER` or `XDEBUG_SESSION`, e.g. from a browser extension. Xdebug connects to `host.containers.internal:9003`, so your editor on the host must listen on port 9003. Only the `development` image includes Xdebug.

### Troubleshooting

`lpod doctor` checks the host and prints how to fix what's missing, without changing anything:

- Podman 5.6 or newer, with `podman quadlet`, and a reachable systemd manager;
- for rootless services: linger, `/etc/subuid` and `/etc/subgid`, and whether the proxy may use ports 80 and 443;
- the idle check's templates, the proxy's local certificate, and whether `APP_URL` resolves;
- failed services.

### Secrets

`lpod app secrets` asks for a value for each `Secret=` line in the installed unit:

| Secret type       | What it asks for                                                |
| ------------------ | ------------------------------------------------------------------ |
| `env`             | A masked value, entered directly                                  |
| `mount` (default) | A file path (default `.env`). `lpod` stores the file's contents |

Each secret is asked only once, even if it's used more than once. For `env` secrets, leave the value empty to keep the current one. That way `lpod app secrets --replace` can update one secret without retyping the others.

## Configuration

`lpod` is configured with environment variables:

| Variable             | Default        | Description                                                                                                     |
| ---------------------- | ---------------- | ------------------------------------------------------------------------------------------------------------------ |
| `LPOD_PODMAN_BINARY` | `podman`       | Podman binary to use                                                                                            |
| `LPOD_PUBLISH_PATH`  | `podman`       | Where `install` looks for rendered `.quadlets` files                                                            |
| `APP_PORT`            | `80`           | Port for `lpod SERVICE open`                                                                                    |
| `APP_USER`            | `$(id -u)`     | User for commands run in the container. Empty means the image's default user. `root-shell`/`root-bash` always use `root` |

`lpod` also loads `.env` and `.env.$APP_ENV` from the current directory. It passes environment variables of AI coding agents (Claude Code, Cursor, Copilot, Codex, Gemini CLI, ...) into the container.

## `lpod-setup`

`lpod-setup` comes with `lpod`. It renders presets in a throwaway container, for hosts that have Podman but no PHP. Run it as `lpod setup`. See [Setting up without PHP](host-setup.md).
