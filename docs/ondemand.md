---
section: Configuration
order: 5
---

# On-demand services

With on-demand services, your app starts on its first request and stops again after it has been idle for a while (scale-to-zero). Its database, cache and other services stop with it. This is useful on a development machine or a homelab with several apps: an app nobody uses doesn't hold on to memory.

On-demand is on by default for the `development` and `production` presets. It only uses systemd and Quadlet. Nothing else runs on the host.

## Installing

Render the presets and install the socket as well as the app:

```bash
php artisan podman:generate development   # or production
php artisan podman:generate ondemand
lpod install development/app.quadlets --replace
lpod install ondemand/my-app-ondemand.socket --replace
lpod idle enable my-app
```

- `lpod install` copies the socket and its proxy service to `~/.config/systemd/user/` and enables the socket.
- `lpod idle enable` turns on the [idle check](#the-idle-check), which lets the database and cache sleep too. It needs `lpod` v2.2.0 or later.
- On a server, run `loginctl enable-linger` once, so the socket keeps listening after a reboot without logging in. The `lpod` installer offers to do this.
- If you use the `proxy` preset, regenerate and reinstall it too. Caddy then sends app traffic to the socket instead of the container.

## How it works

```text
proxy ──▶ my-app-ondemand.socket   (always listening, no process)
              │ first connection
              ▼
          my-app-ondemand.service  (systemd-socket-proxyd, exits when idle)
              │ starts, and waits until healthy
              ▼
          my-app.container         (published on 127.0.0.1:18000, stops when unneeded)
```

1. A systemd socket listens on port `8000`. Nothing runs yet.
2. The first connection starts `systemd-socket-proxyd`, which starts the app container.
3. The app reports ready once `/up` answers. The first request waits for this, so it doesn't fail while Octane boots.
4. After `idle_timeout` without connections, the proxy exits. Nothing needs the app anymore, so systemd stops it, along with its sidecars.

## Configuration

| Config key | Env variable | Default | Description |
| --- | --- | --- | --- |
| `ondemand.enabled` | `PODMAN_ONDEMAND_ENABLED` | `true` | Let the app and its [services](#sleeping-services) sleep |
| `ondemand.listen` | `PODMAN_ONDEMAND_LISTEN` | `8000` | Where the socket listens, e.g. `8000` or `192.168.1.10:8000` |
| `ondemand.port` | `PODMAN_ONDEMAND_PORT` | `18000` | The loopback port the app is published on, for the socket proxy |
| `ondemand.idle_timeout` | `PODMAN_ONDEMAND_IDLE_TIMEOUT` | `10min` | How long the app may be idle before it stops |

If you run several apps on one host, give each its own `listen` and `port`.

To keep the app and all its services running all the time, turn on-demand off. The `proxy` preset then sends traffic straight to the app container:

```ini
PODMAN_ONDEMAND_ENABLED=false
```

## What keeps running

| Service | `development` | `production` |
| --- | --- | --- |
| Vite, Inertia SSR | Stop with the app | Stop with the app |
| Queue worker, Horizon | Stop with the app ([or keep running](#keeping-the-queue-worker-running)) | Start at boot, stop once no jobs are left |
| Scheduler | Stops with the app | Runs `schedule:run` every minute while the app is awake |
| Database, cache, Reverb, Mailpit and other services | [Sleep](#sleeping-services) once nothing needs them | [Sleep](#sleeping-services) once nothing needs them |

## Sleeping services

The database, cache and other services stop once nothing needs them: the app is asleep and no jobs are left. Nothing runs until the next request.

Each of these services has `StopWhenUnneeded=yes`. systemd stops such a service as soon as no running unit `Requires=` or `Wants=` it. The order is:

1. The app goes idle and stops, along with its sidecars.
2. The [idle check](#the-idle-check) stops the queue workers once no jobs are left, and the `production` scheduler timer.
3. Nothing needs the database and cache anymore, so they stop too.
4. The next request starts the app. The app starts the services, workers and scheduler timer it needs, and waits until the database and cache are healthy before it connects.

Containers talk to each other directly, not through the on-demand socket. A service only stops once nothing needs it, so a query from the app or a worker never reaches a stopped database.

### Every service must be needed by something

A service with `StopWhenUnneeded=yes` that nothing `Requires=` or `Wants=` stops right after it starts. The app `Requires=` the database and cache. For any other service you use, such as `rustfs`, `typesense` or `meilisearch`, publish the preset and add it to the app's `Wants=` line (see [Customizing](customizing.md)):

```ini
Wants={{application}}-mailpit.container {{application}}-queue.container {{application}}-schedule.container {{application}}-rustfs.container
```

`Wants=` starts the service with the app, but the app doesn't wait for it. If the first request needs it, such as media from `rustfs` or search results from `typesense`, add it to the app's `After=` line too. The app then waits until the service is healthy, which makes waking up a bit slower:

```ini
After={{application}}-pgsql.container {{application}}-valkey.container {{application}}-rustfs.container
```

Add the service to a [kept-running worker's](#keeping-the-queue-worker-running) `Wants=` and `After=` lines too if its jobs use it.

### Keeping one service running

To keep a single service running, such as the database for an external client, publish the preset and set `StopWhenUnneeded=no` in that service's quadlet. To keep everything running, set `PODMAN_ONDEMAND_ENABLED=false`.

## The idle check

Queue workers that keep running while the app sleeps would keep the database and cache awake too. The idle check stops them once there's no work left. It's part of [`lpod`](lpod.md) v2.2.0 or later, and you turn it on per app:

```bash
lpod idle enable my-app                  # run the check every minute
lpod idle my-app                         # run it once
lpod idle disable my-app
journalctl --user -u lpod-idle@my-app    # see what it did
```

Every minute, while the app is asleep, the check:

1. Skips this minute if a scheduled `schedule:run` is still running, so the jobs it dispatches still find a worker.
2. Runs `php artisan podman:idle` in each running queue worker or Horizon container. If the app has work in progress, it stops here and keeps everything running. A worker that isn't running is skipped.
3. Checks that the app is still asleep, in case a request woke it during the check.
4. Stops the workers, and the scheduler timer in `production`.

The next request starts the workers again through the app's `Wants=` line. That line lists `queue` by default. If you use Horizon, put `horizon` there instead.

The check does nothing when the app's on-demand socket isn't installed. If `podman:idle` fails or doesn't exist, the workers keep running. This happens, for example, when the package was installed with `--dev` and the production image leaves it out.

### Stopping another worker

To have the check look at another worker too, such as `my-app-imports`, open a drop-in with `systemctl --user edit lpod-idle@my-app.service` and add:

```ini
[Service]
Environment=LPOD_IDLE_WORKERS=imports
```

### Older lpod versions

Before `lpod` v2.2.0, the idle check was a timer in the `ondemand` preset (`lpod install ondemand/my-app-idle.timer --replace`). It still works the same way, but it's deprecated and will be removed in the next major version. Don't install both. To switch, run `lpod remove my-app-idle.timer`, then `lpod idle enable my-app`.

## What counts as work in progress

`podman:idle` decides whether the app is busy. It runs a check for each thing your app uses, based on its config:

| Check | Runs when | Busy while |
| --- | --- | --- |
| `queue` | `QUEUE_CONNECTION` isn't `sync` or `null` | Jobs are waiting, running or delayed, on the default queue or any Horizon supervisor's queues |
| `database` | `DB_CONNECTION` is set | Another client runs a query or holds a transaction open (PostgreSQL, MySQL, MariaDB), or an operation runs on the app's MongoDB database. Idle connections, like a worker waiting for jobs, don't count |
| `scout` | `SCOUT_DRIVER` is a search engine, like `typesense` or `meilisearch` | Indexing jobs are queued, or Meilisearch is still indexing in the background |

A long job keeps the whole stack awake until it's done. Delayed jobs count too, so they run on time.

### Running specific checks

Pass `--services` to run only some checks. An unknown name fails, so a typo keeps the workers running rather than stopping them:

```bash
php artisan podman:idle --services=queue,database
```

### Choosing the checks

The checks are listed in `config/podman.php`. Publish the config with `php artisan vendor:publish --tag="podman-config"` to add or remove checks:

```php
'idle' => [
    'checks' => [
        QueueCheck::class,
        DatabaseCheck::class,
        ScoutCheck::class,
    ],
],
```

To change how a check works, such as which queues it watches, register the checks in a service provider's `boot()` method instead. These replace the config list, so include every check you want:

```php
use Foxws\Podman\Support\Idle\Checks\DatabaseCheck;
use Foxws\Podman\Support\Idle\Checks\QueueCheck;
use Foxws\Podman\Support\Idle\PodmanIdle;

app(PodmanIdle::class)->checks([
    QueueCheck::new()->connection('redis')->queues(['default', 'media']),
    DatabaseCheck::new(),
]);
```

### Writing your own check

Extend `IdleCheck`, give it a `name()`, and return an `IdleResult` from `run()`. Override `isEnabled()` to skip the check when your app doesn't need it:

```php
use Foxws\Podman\Support\Idle\IdleCheck;
use Foxws\Podman\Support\Idle\IdleResult;

class ImportCheck extends IdleCheck
{
    public function name(): string
    {
        return 'imports';
    }

    public function run(): IdleResult
    {
        $running = Import::query()->whereNull('finished_at')->count();

        return $running === 0
            ? IdleResult::idle()
            : IdleResult::busy("{$running} imports running");
    }
}
```

## Keeping the queue worker running

In `development`, the queue worker and Horizon stop with the app. A job that's still running gets 60 seconds (`TimeoutStopSec=`) to finish before it's killed, and is retried on the next start. In `production`, the workers already keep running.

To keep the development worker running while the app sleeps, for example for long imports or media processing:

1. Publish the preset:

    ```bash
    php artisan podman:publish development
    ```

2. In `containers/stubs/development/quadlets/queue.quadlets` (or `horizon.quadlets`), replace the app dependency under `[Unit]` with the database and cache your app uses:

    ```ini
    # Remove:
    After={{application}}.container
    PartOf={{application}}.container

    # Add:
    Requires={{application}}-pgsql.container {{application}}-valkey.container
    After={{application}}-pgsql.container {{application}}-valkey.container
    ```

3. Add the services its jobs use, such as `rustfs` for uploads, `typesense` for search indexing, `reverb` for broadcasts or `mailpit` for queued mail. Otherwise they sleep while jobs still need them:

    ```ini
    Wants={{application}}-rustfs.container {{application}}-typesense.container
    ```

4. Regenerate, reinstall the worker, and turn on the idle check so the worker stops once its jobs are done:

    ```bash
    php artisan podman:generate development
    lpod install development/queue.quadlets --replace   # or horizon.quadlets
    lpod idle enable my-app
    ```

The app still starts the worker through its `Wants=` line, but no longer stops it.

## External proxies

The socket is a plain TCP port on the host, so any reverse proxy can sit in front of it, such as a Synology NAS, or Nginx or Traefik on another machine. You don't need the `proxy` preset then:

1. Listen on the LAN: `PODMAN_ONDEMAND_LISTEN=0.0.0.0:8000`, or the host's LAN IP.
2. Point the proxy at `http://your-host:8000` and let it handle HTTPS.
3. Set `APP_URL` to the public `https://` URL, and make sure Laravel trusts the proxy's `X-Forwarded-*` headers (`trustProxies`).
4. Only allow the proxy to reach port `8000`, for example with a firewalld rich rule. Traffic between the proxy and the host is plain HTTP.

The proxy doesn't need to retry. The connection is held until the app is ready, which takes a few seconds, well within most proxies' default timeouts.

## Caveats

- **Cold starts.** The first request after idling takes about 1–3 seconds with `production`, and longer with the `development` preset's file watcher. When the services were asleep too, it also waits for them to start.
- **The scheduler doesn't run while the stack sleeps.** Tasks that must run on time, like nightly backups or reports, need `PODMAN_ONDEMAND_ENABLED=false`.
- **Open connections keep the app awake.** WebSockets or SSE through the app, and uptime monitors that request it more often than `idle_timeout`, keep it from stopping. Point monitors at the proxy instead.
- **Host ports don't wake anything.** A database client, or a request to RustFS or Mailpit through the `proxy` preset, can't start a sleeping service. Open the app first, e.g. with `lpod my-app open`. Starting the service by hand doesn't help, because nothing needs it and systemd stops it again.
- **`lpod my-app up` doesn't keep the app running**, for the same reason. Send a request instead, e.g. with `lpod my-app open`, and run `lpod my-app artisan ...` while it's awake.
- **Health checks.** While the app starts, `/up` is checked every second until it answers. After that, it's checked once a minute. Every service has a health check too, except Memcached and Reverb, which count as started as soon as their container runs.

## Under the hood

- The presets are the same with on-demand on or off. `{{ondemand}}` renders `StopWhenUnneeded=yes` or `no` on the app and services, and the `proxy` preset points at the socket or the container.
- The socket and its proxy service are plain systemd units, because Quadlet has no unit type for sockets. They're always rendered, but only take effect once installed.
- Sidecars use `PartOf=` the app, not `BindsTo=`. `BindsTo=` would count as needing the app, which would keep it running.
