# The `lpod` CLI

The package ships a `lpod` CLI script, installed as a Composer binary at `vendor/bin/lpod`. It's a thin wrapper around `podman exec`, `podman quadlet`, and `systemctl` for the Quadlet services rendered by Artisan (`podman:setup`/`podman:generate`) and installed with `lpod install`, similar in spirit to Laravel Sail's `sail` script. Any command it doesn't recognize is passed straight through to the `podman` binary.

```bash
vendor/bin/lpod SERVICE COMMAND [options] [arguments]
```

`SERVICE` is the name of a Quadlet service (e.g. your application's service, or a sibling service such as `pgsql`).

`install`/`secrets`/`remove`/`list`/`print`/`uninstall` are the exception — they manage Quadlets themselves rather than talking to a running service, so they skip the `SERVICE` argument (see [Quadlet management](#quadlet-management) below).

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

**Or install it onto your `PATH`.** This is simplest if you're only working with a single Podman-managed application on the machine, since the symlink always points at the `vendor/bin/lpod` of the project you created it from:

```bash
ln -s "$(pwd)/vendor/bin/lpod" ~/.local/bin/lpod

# or, to make it available to every user on the machine
sudo ln -s "$(pwd)/vendor/bin/lpod" /usr/local/bin/lpod
```

Make sure the target directory (`~/.local/bin` or `/usr/local/bin`) is on your `PATH`. Once installed either way, the examples below can be run as `lpod ...` instead of `vendor/bin/lpod ...`.

## Quadlet management

Once Artisan has rendered a preset (`php artisan podman:setup`/`podman:generate frankenphp-octane`), these commands take it from a rendered file to a running, systemd-managed service. They take a `PRESET/SERVICE.quadlets` path (relative to the `publish_path` config key, `podman` by default) rather than a `SERVICE` name, and forward any extra flags straight to `podman` — pass `--replace`, `--application=my-app`, `--force`, `--ignore`, etc. as needed.

```bash
lpod install frankenphp-octane/app.quadlets --replace
lpod install frankenphp-octane/app.quadlets --application=my-app

lpod secrets frankenphp-octane/app.quadlets            # Prompts for and sets the service's Podman secrets
lpod secrets frankenphp-octane/app.quadlets --replace

lpod remove pgsql --force --ignore                      # Removes an installed service by its unit name
lpod uninstall my-app --force                            # Removes an application and all of its services

lpod list --filter=status=running --format=json --noheading
lpod print pgsql                                         # Prints the generated systemd unit for a service
```

`secrets` reads the `Secret=` directives straight from the rendered `.quadlets` file — `type=env` secrets prompt for a value directly (masked input), while `type=mount` secrets (the default when `type=` is omitted) prompt for a file path, defaulting to `.env`, whose contents are stored as the secret. A secret needed under several names (e.g. a database password used as both `POSTGRES_PASSWORD` and `PGPASSWORD`) is only prompted for once.

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

`lpod SERVICE COMMAND ...` always needs a `SERVICE` as the first word, but it's only actually used for the built-in lifecycle/exec commands (`up`, `shell`, `run`, `artisan`, ...). Any command `lpod` doesn't recognize is passed straight through to the `podman` binary instead, and `SERVICE` is discarded entirely:

```bash
# SERVICE ("_") is ignored here — this just runs `podman logs -f systemd-app`
lpod _ logs -f systemd-app
lpod _ inspect systemd-pgsql
lpod _ stats
```

Container names always follow the `systemd-<service>` pattern (e.g. `systemd-pgsql`, `systemd-app-horizon`), since that's what Quadlet names them to avoid clashing with unmanaged containers — useful for `podman cp`, `podman logs`, `podman inspect`, etc.

### `run` for one-off commands

`lpod SERVICE run CMD [args...]` execs an arbitrary command in a service's container — handy for things there isn't a dedicated shortcut for:

```bash
lpod pgsql run pg_dump -U postgres -d app > backup.sql
lpod valkey run valkey-cli flushall
lpod app run env FOO=bar php artisan some:command   # inject a one-off env var via `env`
```

Redirecting output (`> backup.sql`) makes `lpod` skip allocating a pseudo-TTY automatically (it only requests one when stdout is actually a terminal), so piping/redirecting a command's output doesn't get corrupted by TTY control characters.

### `bin` for Composer binaries

`lpod SERVICE bin TOOL [args...]` runs `./vendor/bin/TOOL` inside the container — no need for `run ./vendor/bin/...`:

```bash
lpod app bin phpstan analyse
lpod app bin pest --filter=SomeTest
```

### `debug` for a one-off Xdebug run

`lpod SERVICE debug ARTISAN_COMMAND` runs an Artisan command with `XDEBUG_TRIGGER=1` set for just that invocation, instead of leaving Xdebug enabled permanently:

```bash
lpod app debug queue:work
lpod app debug tinker
```

### `root-shell` vs `shell`

`shell`/`bash` exec into the container as the mapped app user (`docker` by default); `root-shell`/`root-bash` exec as `root` — reach for the latter when you need to install packages or fix permissions ad hoc inside a running container.

### AI agent env vars are forwarded automatically

If `lpod` detects it's running under a recognized AI coding agent (Claude Code, Cursor, Copilot, Codex, Gemini CLI, and a handful of others — see the `AGENT_ENV_VARS` list in `bin/lpod`), it forwards those identifying environment variables into the container for every `exec`-based command. Useful if application code (or a package) branches on agent presence.

### Rootless vs system-wide, automatically

`up`/`down`/`restart`/`status` use `systemctl --user` when you run `lpod` as a normal user, and plain `systemctl` when run as `root` — matching whichever manager actually owns your installed Quadlet units. You don't need to remember which one applies.

### Swapping the Podman binary

Set `LPOD_PODMAN_BINARY` to point `lpod` at a different single executable (e.g. a `podman-remote` wrapper) instead of whatever `podman` resolves to on `PATH`:

```bash
LPOD_PODMAN_BINARY=/usr/local/bin/podman-remote lpod app shell
```

It must be a single executable path — `LPOD_PODMAN_BINARY="sudo podman"` won't work, since it's invoked directly rather than through a shell.

### `dusk`/`dusk:fails` expect a Selenium container

Both point `APP_URL` at the app's own container hostname and `DUSK_DRIVER_URL` at `http://selenium:4444/wd/hub` for the duration of the test run — install a `selenium` service on the same network first, [laravel/dusk](https://github.com/laravel/dusk) isn't otherwise wired up.
