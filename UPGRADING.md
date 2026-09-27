# Upgrading

## From v3 to v4

v4 makes [on-demand services](docs/ondemand.md) the default. The app starts on its first request and stops after 10 minutes without traffic. A systemd socket listens on port `8000`, and the app itself is published on `127.0.0.1:18000`.

Using an AI agent with [Laravel Boost](https://github.com/laravel/boost)? Run `php artisan boost:update` after upgrading, then ask it to upgrade laravel-podman. The `podman-upgrade` skill walks it through the steps below, including your published presets.

### 1. Update the package

```bash
composer require foxws/laravel-podman:^4.0 --dev
```

If you published `config/podman.php`, copy the new `ondemand` block from the [package config](config/podman.php). Without it, the defaults apply.

### 2. Decide whether to keep the app running

On-demand is on by default. To keep the app running all the time, as in v3, add this to `.env` and skip the socket in step 4:

```ini
PODMAN_ONDEMAND_ENABLED=false
```

Running several apps on one host? Give each its own `PODMAN_ONDEMAND_LISTEN` and `PODMAN_ONDEMAND_PORT`.

### 3. Update published presets

Skip this step if you haven't published any presets (nothing in `containers/stubs/`, or whatever `stubs_path` is). Otherwise, make these changes to each published `development` or `frankenphp-octane` preset. Compare with the new files in `vendor/foxws/laravel-podman/stubs/{preset}`.

- **`quadlets/app.quadlets`:**
  - Add `StopWhenUnneeded={{ondemand}}` under `[Unit]`.
  - Under `[Container]`, add `PublishPort=127.0.0.1:{{ondemandPort}}:8000`. Remove any `PublishPort=8000:8000`, because the socket uses that port now.
  - Under `[Container]`, add the health check, and remove any existing `HealthInterval=`/`HealthTimeout=`/`HealthRetries=` lines:

    ```ini
    Notify=healthy
    HealthCmd=curl -fsS -o /dev/null http://127.0.0.1:8000/up
    HealthInterval=2s
    HealthStartPeriod=120s
    HealthTimeout=5s
    HealthRetries=3
    ```

- **Every other `quadlets/*.quadlets`:** replace `BindsTo={{application}}.container` with `PartOf={{application}}.container`.
- **`systemd/`:** copy the folder from the package preset. It holds `ondemand.socket` and `ondemand.service`, plus `schedule.timer` for `frankenphp-octane`.
- **`frankenphp-octane` only:**
  - **`queue.quadlets` / `horizon.quadlets`:**
    - Drop the app from `After=`.
    - Use `Requires=`/`After=` on the database and cache instead.
    - Add `[Install]` `WantedBy=default.target`.
  - **`schedule.quadlets`:**
    - Run `artisan schedule:run` instead of `schedule:work`.
    - Depend on the database and cache instead of the app.
    - Replace the `[Service]` section with `Type=oneshot`, `TimeoutStartSec=300` and `TimeoutStopSec=60`.
  - **`app.quadlets`:** remove the queue worker (or Horizon) and the scheduler from `Wants=`.

### 4. Regenerate and reinstall

Stop the app first, so its old port is free:

```bash
lpod my-app down
php artisan podman:setup
lpod install development/app.quadlets --replace
lpod install development/my-app-ondemand.socket --replace   # skip when PODMAN_ONDEMAND_ENABLED=false
lpod install proxy/proxy.quadlets --replace
```

Also reinstall every sidecar you use (`queue`, `horizon`, `schedule`, `vite`, `reverb`, `inertia-ssr`, ...) with `--replace`, so they pick up `PartOf=`.

For `frankenphp-octane`, also install the scheduler timer, and start the queue worker (or Horizon), which now runs on its own:

```bash
lpod install frankenphp-octane/my-app-schedule.timer --replace
lpod my-app-queue up   # or my-app-horizon
```

Installing sockets and timers needs [`lpod`](https://github.com/foxws/lpod) v2.1.0 or later. Update it with the install command in the [`lpod` docs](docs/lpod.md#installation).

On a server, run `loginctl enable-linger` once, so the socket listens after a reboot without a login.

### 5. Check it

```bash
lpod my-app open
```

The first request starts the app. It needs a working `GET /up` route, which Laravel 11+ apps have by default. Without it, the app never counts as started.

Other behaviour changes:

- `lpod my-app up` no longer keeps the app running. Nothing needs it, so systemd stops it again. Send a request instead.
- `lpod my-app artisan ...` only works while the app is awake.
