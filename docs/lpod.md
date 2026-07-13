# The `lpod` CLI: tips & tricks

`vendor/bin/lpod` is a thin wrapper around `podman exec`/`systemctl` for the Quadlet services you installed with `podman:install`. The base commands are covered in the [README](../README.md#the-lpod-utility) — this page collects less obvious behavior worth knowing about.

## `SERVICE` is only used for container commands

`lpod SERVICE COMMAND ...` always needs a `SERVICE` as the first word, but it's only actually used for the built-in lifecycle/exec commands (`up`, `shell`, `run`, `artisan`, ...). Any command `lpod` doesn't recognize is passed straight through to the `podman` binary instead, and `SERVICE` is discarded entirely:

```bash
# SERVICE ("_") is ignored here — this just runs `podman logs -f systemd-app`
lpod _ logs -f systemd-app
lpod _ inspect systemd-pgsql
lpod _ stats
```

Container names always follow the `systemd-<service>` pattern (e.g. `systemd-pgsql`, `systemd-app-horizon`), since that's what Quadlet names them to avoid clashing with unmanaged containers — useful for `podman cp`, `podman logs`, `podman inspect`, etc.

## `run` for one-off commands

`lpod SERVICE run CMD [args...]` execs an arbitrary command in a service's container — handy for things there isn't a dedicated shortcut for:

```bash
lpod pgsql run pg_dump -U postgres -d app > backup.sql
lpod valkey run valkey-cli flushall
lpod app run env FOO=bar php artisan some:command   # inject a one-off env var via `env`
```

Redirecting output (`> backup.sql`) makes `lpod` skip allocating a pseudo-TTY automatically (it only requests one when stdout is actually a terminal), so piping/redirecting a command's output doesn't get corrupted by TTY control characters.

## `bin` for Composer binaries

`lpod SERVICE bin TOOL [args...]` runs `./vendor/bin/TOOL` inside the container — no need for `run ./vendor/bin/...`:

```bash
lpod app bin phpstan analyse
lpod app bin pest --filter=SomeTest
```

## `debug` for a one-off Xdebug run

`lpod SERVICE debug ARTISAN_COMMAND` runs an Artisan command with `XDEBUG_TRIGGER=1` set for just that invocation, instead of leaving Xdebug enabled permanently:

```bash
lpod app debug queue:work
lpod app debug tinker
```

## `root-shell` vs `shell`

`shell`/`bash` exec into the container as the mapped app user (`docker` by default); `root-shell`/`root-bash` exec as `root` — reach for the latter when you need to install packages or fix permissions ad hoc inside a running container.

## AI agent env vars are forwarded automatically

If `lpod` detects it's running under a recognized AI coding agent (Claude Code, Cursor, Copilot, Codex, Gemini CLI, and a handful of others — see the `AGENT_ENV_VARS` list in `bin/lpod`), it forwards those identifying environment variables into the container for every `exec`-based command. Useful if application code (or a package) branches on agent presence.

## Rootless vs system-wide, automatically

`up`/`down`/`restart`/`status` use `systemctl --user` when you run `lpod` as a normal user, and plain `systemctl` when run as `root` — matching whichever manager actually owns your installed Quadlet units. You don't need to remember which one applies.

## Swapping the Podman binary

Set `LPOD_PODMAN_BINARY` to point `lpod` at a different single executable (e.g. a `podman-remote` wrapper) instead of whatever `podman` resolves to on `PATH`:

```bash
LPOD_PODMAN_BINARY=/usr/local/bin/podman-remote lpod app shell
```

It must be a single executable path — `LPOD_PODMAN_BINARY="sudo podman"` won't work, since it's invoked directly rather than through a shell.

## `dusk`/`dusk:fails` expect a Selenium container

Both point `APP_URL` at the app's own container hostname and `DUSK_DRIVER_URL` at `http://selenium:4444/wd/hub` for the duration of the test run — install a `selenium` service on the same network first, [laravel/dusk](https://github.com/laravel/dusk) isn't otherwise wired up.
