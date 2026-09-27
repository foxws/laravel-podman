# Upgrading

## From v3 to v4

v4 makes [on-demand services](docs/ondemand.md) the default. The app starts on its first request and stops after 10 minutes without traffic. A systemd socket listens on port `8000`, and the app itself is published on `127.0.0.1:18000`.

Using an AI agent with [Laravel Boost](https://github.com/laravel/boost)? Run `php artisan boost:update` after upgrading, then ask it to upgrade laravel-podman. The `podman-upgrade` skill walks it through the steps below, including your published presets.

Using the package's classes directly? The `Podman` facade and class are removed, and the config readers (`prefix()`, `uid()`, `s3Buckets()`, the on-demand settings, ...) moved from `PodmanQuadletPath` to a new `PodmanConfig`.

### 1. Update the package

```bash
composer require foxws/laravel-podman:^4.0 --dev
```

If you published `config/podman.php`, copy the new `ondemand` block from the [package config](config/podman.php). Without it, the defaults apply.

### 2. Decide whether to keep the app running

On-demand is on by default. To keep the app running all the time, as in v3, add this to `.env` and skip the socket in step 5:

```ini
PODMAN_ONDEMAND_ENABLED=false
```

Running several apps on one host? Give each its own `PODMAN_ONDEMAND_LISTEN` and `PODMAN_ONDEMAND_PORT`.

### 3. Check your database and cache versions

Database and cache images are now pinned to a major version instead of `latest`:

| Service | Image |
| --- | --- |
| `pgsql` | `postgres:18` |
| `mysql` | `mysql:26` |
| `mariadb` | `mariadb:13` |
| `mongodb` | `mongo:8` |
| `valkey` | `valkey:9` |
| `redis` | `redis:8` |
| `memcached` | `memcached:1.6` |
| `meilisearch` | `meilisearch:v1.54` |
| `proxy` | `caddy:2` |

These match what `latest` pointed to when v4 was released. If your services were auto-updated, you're already on them. If not, check first, e.g. `lpod my-app-pgsql run postgres --version`. A database can't open data written by a newer major, and moving to a newer major may need a migration (for example `pg_upgrade` for Postgres). If you're on another major, publish the preset and set the image tag you run.

### 4. Update published presets

Skip this step if you haven't published any presets (nothing in `containers/stubs/`, or whatever `stubs_path` is). Otherwise, make these changes to each published `development` or `frankenphp-octane` preset. Compare with the new files in `vendor/foxws/laravel-podman/stubs/{preset}`.

- **`quadlets/app.quadlets`:**
  - Add `StopWhenUnneeded={{ondemand}}` under `[Unit]`.
  - Under `[Container]`, add `PublishPort=127.0.0.1:{{ondemandPort}}:8000`. Remove any `PublishPort=8000:8000`, because the socket uses that port now.
  - Under `[Container]`, add the health check, and remove any existing `HealthInterval=`/`HealthTimeout=`/`HealthRetries=` lines:

    ```ini
    Notify=healthy
    HealthStartupCmd=curl -fsS -o /dev/null http://127.0.0.1:8000/up
    HealthStartupInterval=1s
    HealthStartupTimeout=5s
    HealthCmd=curl -fsS -o /dev/null http://127.0.0.1:8000/up
    HealthInterval=1m
    HealthTimeout=5s
    HealthRetries=3
    ```

  - Under `[Build]`, replace `Environment=UID={{appUid}}` and `Environment=GID={{appGid}}` with `BuildArg=UID={{appUid}}` and `BuildArg=GID={{appGid}}`. `Environment=` never reached the Containerfile, so the image's user was always built as 1000.
- **Every other `quadlets/*.quadlets`:** replace `BindsTo={{application}}.container` with `PartOf={{application}}.container`.
- **`development` sidecars** (`queue`, `horizon`, `schedule`, `reverb`, `vite`): add `HealthCmd=none` under `[Container]`. They don't run FrankenPHP's web server, so its built-in health check always fails.
- **Database and cache quadlets:** pin the image tags, as in step 3.
- **`runtimes/Containerfile`:** replace `FROM docker.io/dunglas/frankenphp:latest` with `ARG FRANKENPHP_VERSION=1-php8.5` followed by `FROM docker.io/dunglas/frankenphp:${FRANKENPHP_VERSION}`. You can also drop the final "Clean up unnecessary files" layer and, in `frankenphp-octane`, the build-time `key:generate`.
- **`systemd/`:** copy the folder from the package preset. It holds `ondemand.socket` and `ondemand.service`, plus `schedule.timer` for `frankenphp-octane`.
- **`frankenphp-octane` only:**
  - **`queue.quadlets` / `horizon.quadlets`:**
    - Drop the app from `After=`.
    - Use `Requires=`/`After=` on the database and cache instead.
    - Add `[Install]` `WantedBy=default.target`.
  - **`schedule.quadlets`:**
    - Run `artisan schedule:run` instead of `schedule:work`.
    - Depend on the database and cache instead of the app.
    - Add `Environment=APP_OPTIMIZE=false` under `[Container]`.
    - Replace the `[Service]` section with `Type=oneshot`, `TimeoutStartSec=300` and `TimeoutStopSec=60`.
  - **`runtimes/entrypoint.sh`:** copy the new `chown` loop and the `APP_OPTIMIZE` check from the package. Without them, the scheduler would `chown -R` your volumes and run `optimize` every minute.
  - **`app.quadlets`:** remove the queue worker (or Horizon) and the scheduler from `Wants=`.

### 5. Regenerate and reinstall

`podman:generate` now removes a preset's previous output (`podman/{preset}/`) before rendering, so don't keep hand edits there.

Stop the app first, so its old port is free:

```bash
lpod my-app down
php artisan podman:setup
lpod install development/app.quadlets --replace
lpod install development/my-app-ondemand.socket --replace   # skip when PODMAN_ONDEMAND_ENABLED=false
lpod install proxy/proxy.quadlets --replace
```

Rebuild the app image once, so it picks up the base image tag and the `UID`/`GID` build args:

```bash
lpod my-app-build restart
```

Also reinstall every other service you use with `--replace`: the sidecars (`queue`, `horizon`, `schedule`, `vite`, `reverb`, `inertia-ssr`, ...) pick up `PartOf=`, and the database and cache services pick up their pinned image.

For `frankenphp-octane`, also install the scheduler timer, and start the queue worker (or Horizon), which now runs on its own:

```bash
lpod install frankenphp-octane/my-app-schedule.timer --replace
lpod my-app-queue up   # or my-app-horizon
```

Installing sockets and timers needs the latest [`lpod`](https://github.com/foxws/lpod) (added in [foxws/lpod#11](https://github.com/foxws/lpod/pull/11)). Update it with the install command in the [`lpod` docs](docs/lpod.md#installation).

On a server, run `loginctl enable-linger` once, so the socket listens after a reboot without a login.

### 6. Check it

```bash
lpod my-app open
```

The first request starts the app. It needs a working `GET /up` route, which Laravel 11+ apps have by default. Without it, the app never counts as started.

Other behaviour changes:

- `lpod my-app up` no longer keeps the app running. Nothing needs it, so systemd stops it again. Send a request instead.
- `lpod my-app artisan ...` only works while the app is awake.
