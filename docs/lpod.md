# The `lpod` CLI

The package ships three Composer binaries that run **on the host**: `vendor/bin/lpod`, `vendor/bin/lpod-setup`, and `vendor/bin/lpod-secrets`. They use the real `podman`/`systemctl` binaries. Artisan `podman:*` commands only render files and can run anywhere PHP is available (see [Command Reference](commands.md)).

`lpod` is a thin wrapper around `podman exec`, `podman quadlet`, and `systemctl` for services rendered by Artisan (`podman:setup`/`podman:generate`) and installed with `lpod install`. Any command it does not recognize is passed to `podman` directly. `lpod setup` and `lpod secrets` are convenience aliases for `lpod-setup` and `lpod-secrets` — see [Quadlet management](#quadlet-management).

```bash
vendor/bin/lpod SERVICE COMMAND [options] [arguments]
```

`SERVICE` is the name of a Quadlet service (e.g. your application's service, or a sibling service such as `pgsql`).

`setup`/`install`/`secrets`/`remove`/`list`/`print`/`uninstall` are the exception — they manage Quadlets themselves rather than talking to a running service, so they skip the `SERVICE` argument (see [Quadlet management](#quadlet-management) below).

## Shortening the `vendor/bin/lpod` call

Typing `vendor/bin/lpod` for every command gets old fast, so pick one of the following.

**Add a shell alias.** This resolves `lpod` relative to your current directory, so it keeps working correctly no matter which project you're in.

Bash or Zsh, in `~/.bashrc` / `~/.zshrc`:

```bash
alias lpod='[ -f vendor/bin/lpod ] && bash vendor/bin/lpod || bash "$(git rev-parse --show-toplevel)/vendor/bin/lpod"'
```

Fish, in `~/.config/fish/config.fish`:

```fish
function lpod
    if test -f vendor/bin/lpod
        bash vendor/bin/lpod $argv
    else
        bash (git rev-parse --show-toplevel)/vendor/bin/lpod $argv
    end
end
```

**Or install it on your `PATH`.** This is simplest when you work with one Podman-managed app on the machine, because the symlink points to one project's `vendor/bin/lpod`:

```bash
ln -s "$(pwd)/vendor/bin/lpod" ~/.local/bin/lpod

# or, to make it available to every user on the machine
sudo ln -s "$(pwd)/vendor/bin/lpod" /usr/local/bin/lpod
```

Make sure the target directory (`~/.local/bin` or `/usr/local/bin`) is on your `PATH`. Once installed either way, the examples below can be run as `lpod ...` instead of `vendor/bin/lpod ...`.

## Quadlet management

After Artisan renders presets (`php artisan podman:setup` or `podman:generate frankenphp-octane`), `install` turns a rendered file into a running systemd-managed service. It takes a `PRESET/SERVICE.quadlets` path (relative to `publish_path`, `podman` by default), not a service name. The other commands (`secrets`, `remove`, `list`, `print`) target already-installed units by name. Extra flags are forwarded to `podman` (`--replace`, `--application=my-app`, `--force`, `--ignore`, etc.).

`lpod setup` is an alias for `vendor/bin/lpod-setup`. It runs `php artisan podman:setup` inside a container, which is useful when PHP is not installed on the host. When rendering finishes, it prints `podman quadlet install` commands (with full host paths) for each rendered service — or, with `--install`, runs them itself. `--install` is consumed by `lpod-setup` itself (not forwarded); every other argument is forwarded to `podman:setup`. See [Setting up without PHP on the host](host-setup.md).

```bash
lpod setup                                              # Renders the default presets without PHP on the host
lpod setup --preset=frankenphp-octane
lpod setup --install                                    # Also installs (--replace) each rendered service, since this runs on the host

lpod install frankenphp-octane/app.quadlets --replace
lpod install frankenphp-octane/app.quadlets --application=my-app

lpod secrets app                                        # Prompts for and sets the service's Podman secrets
lpod secrets app --replace

lpod remove pgsql --force --ignore                      # Removes an installed service by its unit name
lpod uninstall my-app --force                            # Removes an application and all of its services

lpod list --filter=status=running --format=json --noheading
lpod print pgsql                                         # Prints the generated systemd unit for a service
```

`secrets` is an alias for `vendor/bin/lpod-secrets`. It reads `Secret=` directives from an installed unit (`podman quadlet print SERVICE.container`), so the service must be installed first. `type=env` prompts for a value (masked input). `type=mount` (default if `type=` is omitted) prompts for a file path, defaulting to `.env`, and stores that file contents as the secret. If one secret is reused under multiple names (for example `POSTGRES_PASSWORD` and `PGPASSWORD`), it is prompted once.

> **Warning:** `remove`/`uninstall` delete the Podman volumes owned by the services they remove, with no undo — see [Backing up volumes](commands.md#backing-up-volumes).

## Command reference

**Lifecycle**

```bash
lpod my-app up          # Start the "my-app" service
lpod my-app down        # Stop the "my-app" service
lpod my-app restart     # Restart the "my-app" service
lpod my-app status      # Show the status of the "my-app" service
```

**Artisan, PHP, and Composer**

```bash
lpod my-app artisan queue:work
lpod my-app art queue:work     # Alias for "artisan"
lpod my-app a queue:work       # Alias for "artisan"
lpod my-app php -v
lpod my-app composer require laravel/sanctum
lpod my-app debug queue:work   # Artisan command with Xdebug enabled
lpod my-app tinker
```

**Node, npm, pnpm, Yarn, and Bun**

```bash
lpod my-app node --version
lpod my-app npm run prod
lpod my-app npx ...
lpod my-app pnpm run prod
lpod my-app pnpx ...
lpod my-app yarn run prod
lpod my-app bun run prod
lpod my-app bunx ...
```

**Testing**

```bash
lpod my-app test               # php artisan test
lpod my-app phpunit ...
lpod my-app pest ...
lpod my-app pint ...
lpod my-app dusk                # Requires laravel/dusk
lpod my-app dusk:fails
```

**Container CLI and binaries**

```bash
lpod my-app shell        # Alias: bash
lpod my-app root-shell   # Alias: root-bash
lpod my-app bin phpstan  # Run vendor/bin/phpstan
lpod my-app run whoami   # Run an arbitrary command in the container
```

**Other**

```bash
lpod my-app open                     # Open the application URL in your browser
lpod my-app artisan podman:publish   # Publish the Podman container runtime files
lpod --help                          # Print the full list of commands
```

## Tips & tricks

### `SERVICE` is only used for container commands

`lpod SERVICE COMMAND ...` always starts with `SERVICE`, but `SERVICE` is only used for built-in lifecycle/exec commands (`up`, `shell`, `run`, `artisan`, ...). For commands `lpod` does not recognize, it passes through to `podman` and ignores `SERVICE`:

```bash
# SERVICE ("_") is ignored here — this just runs `podman logs -f systemd-app`
lpod _ logs -f systemd-app
lpod _ inspect systemd-pgsql
lpod _ stats
```

Container names follow the `systemd-<service>` pattern (for example `systemd-pgsql`, `systemd-app-horizon`). This is useful for `podman cp`, `podman logs`, `podman inspect`, and similar commands.

### `run` for one-off commands

`lpod SERVICE run CMD [args...]` runs any command in a service container when there is no dedicated shortcut:

```bash
lpod pgsql run pg_dump -U postgres -d app > backup.sql
lpod valkey run valkey-cli flushall
lpod app run env FOO=bar php artisan some:command   # inject a one-off env var via `env`
```

When output is redirected (`> backup.sql`), `lpod` automatically skips pseudo-TTY allocation. This avoids TTY control characters in redirected or piped output.

### `bin` for Composer binaries

`lpod SERVICE bin TOOL [args...]` runs `./vendor/bin/TOOL` inside the container — no need for `run ./vendor/bin/...`:

```bash
lpod app bin phpstan analyse
lpod app bin pest --filter=SomeTest
```

### `debug` for a one-off Xdebug run

`lpod SERVICE debug ARTISAN_COMMAND` runs one Artisan command with `XDEBUG_TRIGGER=1` instead of enabling Xdebug all the time:

```bash
lpod app debug queue:work
lpod app debug tinker
```

### `root-shell` vs `shell`

`shell`/`bash` enter the container as the mapped app user (`docker` by default). `root-shell`/`root-bash` enter as `root` for tasks like package installs or permission fixes.

### AI agent env vars are forwarded automatically

If `lpod` detects a supported AI coding agent (Claude Code, Cursor, Copilot, Codex, Gemini CLI, and others; see `AGENT_ENV_VARS` in `bin/lpod`), it forwards those env vars into the container for `exec`-based commands.

### Rootless vs system-wide, automatically

`up`/`down`/`restart`/`status` use `systemctl --user` for normal users and plain `systemctl` for `root`, matching whichever manager owns the installed units.

### Swapping the Podman binary

Set `LPOD_PODMAN_BINARY` to point `lpod` at a different single executable (e.g. a `podman-remote` wrapper) instead of whatever `podman` resolves to on `PATH`:

```bash
LPOD_PODMAN_BINARY=/usr/local/bin/podman-remote lpod app shell
```

It must be a single executable path. `LPOD_PODMAN_BINARY="sudo podman"` will not work because `lpod` calls it directly, not through a shell.

### `dusk`/`dusk:fails` expect a Selenium container

Both set `APP_URL` to the app container hostname and `DUSK_DRIVER_URL` to `http://selenium:4444/wd/hub` for that test run. Install a `selenium` service on the same network first; [laravel/dusk](https://github.com/laravel/dusk) is not wired otherwise.
