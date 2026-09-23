---
section: Getting Started
order: 2
---

# `lpod` CLI

`lpod` is a bash script for managing [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html) services. It doesn't need PHP, Composer or this package. It combines `podman exec`, `podman quadlet` and `systemctl` in one command, and passes unknown commands on to `podman`. Source and releases are at [foxws/lpod](https://github.com/foxws/lpod).

## Installation

```bash
curl -fsSL -o ~/.local/bin/lpod https://github.com/foxws/lpod/releases/latest/download/lpod
chmod +x ~/.local/bin/lpod
```

To pin a version, download a tagged release instead, e.g. `.../releases/download/v0.1.0/lpod`. Check the installed version with `lpod --version`.

## Usage

```bash
lpod SERVICE COMMAND [options] [arguments]
```

`SERVICE` is a Quadlet service name, like your app or `pgsql`. These commands don't take a service: `setup`, `install`, `remove`, `uninstall`, `list`, `print`, `reload`.

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
| `lpod app tinker`             | Start a Tinker session                      |

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
| `lpod remove NAME`                     | Remove an installed Quadlet                       |
| `lpod uninstall APPLICATION`           | Remove an application and all of its Quadlets     |
| `lpod list`                            | List installed Quadlets                           |
| `lpod print NAME`                      | Print the generated systemd unit                  |
| `lpod reload`                          | Reload the systemd manager configuration (`daemon-reload`) |
| `lpod setup ...`                       | Render presets without PHP. See [Setting up without PHP](host-setup.md) |

All of these except `reload` accept the same flags as `podman quadlet` (`--replace`, `--application`, `--force`, `--ignore`, ...).

> **Warning:** `remove` and `uninstall` also delete the service's volumes. See [Backing up volumes](commands.md#backing-up-volumes).

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

`lpod-setup` comes with `lpod` and renders presets in a throwaway container, for hosts with Podman but no PHP. `lpod setup` runs it. See [Setting up without PHP](host-setup.md).
