---
section: Getting Started
order: 2
---

# `lpod` CLI

`lpod` is a single, dependency-free bash script for managing [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html) services — no PHP, Composer, or this package needed to run it. It wraps `podman exec`, `podman quadlet`, and `systemctl` behind one command; anything else is passed straight through to `podman`. Full docs and releases live at [foxws/lpod](https://github.com/foxws/lpod).

## Installation

```bash
curl -fsSL -o ~/.local/bin/lpod https://github.com/foxws/lpod/releases/latest/download/lpod
chmod +x ~/.local/bin/lpod
```

To pin a version instead of always installing `latest`, download a tagged release directly (e.g. `.../releases/download/v0.1.0/lpod`). `lpod --version` prints the installed version.

## Usage

```bash
lpod SERVICE COMMAND [options] [arguments]
```

`SERVICE` is the name of a Quadlet service — your app, or a sibling service like `pgsql`. Commands that manage Quadlets themselves rather than a running service skip it: `setup`, `install`, `remove`, `uninstall`, `list`, `print`, `reload`.

Quadlet names the actual container `systemd-SERVICE` (e.g. `systemd-my-app`). `lpod` handles that prefix for you in commands that run inside the container (`shell`, `run`, `artisan`, and similar) — always refer to a service by its plain name.

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
| `lpod setup ...`                       | Render presets without PHP — see [Setting up without PHP](host-setup.md) |

Every command above except `reload` accepts the same extra flags as `podman quadlet` itself (`--replace`, `--application`, `--force`, `--ignore`, ...).

> **Warning:** `remove` and `uninstall` delete the Podman volumes owned by the services they remove — see [Backing up volumes](commands.md#backing-up-volumes).

### Secrets

`lpod app secrets` reads the `Secret=` lines from an installed unit and asks you for each one:

| Secret type       | What it asks for                                                |
| ------------------ | ------------------------------------------------------------------ |
| `env`             | A masked value, entered directly                                  |
| `mount` (default) | A file path (defaults to `.env`); `lpod` stores that file's contents |

A secret used more than once is only asked for once. For an `env` secret, leaving the value blank keeps its current value untouched — handy for updating a single secret with `lpod app secrets --replace` without re-entering the rest.

## Configuration

`lpod` reads its settings from environment variables:

| Variable             | Default        | Description                                                                                                     |
| ---------------------- | ---------------- | ------------------------------------------------------------------------------------------------------------------ |
| `LPOD_PODMAN_BINARY` | `podman`       | The Podman binary to use.                                                                                       |
| `LPOD_PUBLISH_PATH`  | `podman`       | Where `lpod` looks for rendered `.quadlets` files when you run `install`.                                       |
| `APP_PORT`            | `80`           | Used by `lpod SERVICE open`.                                                                                    |
| `APP_USER`            | `$(id -u)`     | The user `exec`-based commands run as inside the container. Empty string uses the image's default user. `root-shell`/`root-bash` always run as `root`. |

`lpod` also loads `.env` and `.env.$APP_ENV` from the current directory, and forwards known AI coding agent environment variables (Claude Code, Cursor, Copilot, Codex, Gemini CLI, and others) into `exec`-based commands.

## `lpod-setup`

`lpod-setup` renders presets inside a disposable container, for hosts that have Podman but no PHP — see [Setting up without PHP](host-setup.md). It ships alongside `lpod` in [foxws/lpod](https://github.com/foxws/lpod). `lpod setup` is a shortcut for it.
