---
section: Configuration
order: 5
---

# On-demand services

On-demand services start your app on its first request and stop it again after it has been idle for a while (scale-to-zero). It's handy on a development machine or a homelab that hosts several apps: an app nobody is using doesn't hold on to Octane workers, SSR or a Vite server.

It's on by default for the `development` and `production` presets. It only uses systemd and Quadlet, nothing else runs on the host.

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
3. The app container reports ready once `/up` answers (`Notify=healthy`), so the first request waits for Octane instead of failing.
4. After `idle_timeout` without connections, the proxy exits. Nothing needs the app anymore, so systemd stops it (`StopWhenUnneeded=yes`) along with its sidecars.

## Configuring it

To keep the app running all the time instead, turn it off. The app is then published to the proxy directly, as before:

```ini
PODMAN_ONDEMAND_ENABLED=false
```

| Config key | Env variable | Default | Description |
| --- | --- | --- | --- |
| `ondemand.enabled` | `PODMAN_ONDEMAND_ENABLED` | `true` | Render presets on-demand, including [sleeping services](#sleeping-services) |
| `ondemand.listen` | `PODMAN_ONDEMAND_LISTEN` | `8000` | Where the socket listens (systemd `ListenStream=`), e.g. `8000` or `192.168.1.10:8000` |
| `ondemand.port` | `PODMAN_ONDEMAND_PORT` | `18000` | Loopback port the app is published on for the socket proxy |
| `ondemand.idle_timeout` | `PODMAN_ONDEMAND_IDLE_TIMEOUT` | `10min` | How long the app may be idle before it stops |

Running several apps on one host? Give each its own `listen` and `port`.

Render and install as usual. The socket, its proxy service and the [idle check](#the-idle-check) are plain systemd units, because Quadlet has no unit type for them. They live in the `ondemand` preset, which works with both `development` and `production`:

```bash
php artisan podman:generate development
php artisan podman:generate ondemand
lpod install development/app.quadlets --replace
lpod install ondemand/my-app-ondemand.socket --replace
lpod install ondemand/my-app-idle.timer --replace
```

`lpod install` copies the socket and its proxy service to `~/.config/systemd/user/` and enables the socket. On a server, also run `loginctl enable-linger` once, so the socket listens after a reboot without logging in.

With the `proxy` preset, regenerate it too. Caddy then sends app traffic to the socket (`host.containers.internal:8000`) instead of the container.

## What keeps running

| Service | `development` | `production` |
| --- | --- | --- |
| Vite, Inertia SSR | Stop with the app | Stop with the app |
| Queue worker / Horizon | Stops with the app ([opt out](#keeping-the-queue-worker-running)) | Starts at boot, stops once no jobs are left ([idle check](#the-idle-check)) |
| Scheduler | Stops with the app | A timer runs `schedule:run` every minute while the app is awake |
| Database, cache, Reverb, Mailpit, other services | [Sleep](#sleeping-services) once nothing needs them | [Sleep](#sleeping-services) once nothing needs them |

The presets are the same with on-demand on or off. Only `StopWhenUnneeded=` on the app and services changes (`{{ondemand}}` renders `yes` or `no`), along with where the `proxy` preset sends traffic. The socket and timer units are always rendered, but only take effect once installed. Sidecars use `PartOf=` the app rather than `BindsTo=`: `BindsTo=` would count as needing the app and keep it running.

### Keeping the queue worker running

In `development`, the queue worker and Horizon are `PartOf=` the app, so they stop when it goes idle. A job still running then gets `TimeoutStopSec=` (60 seconds) to finish before it's killed, and is retried on the next start.

To keep the worker running while the app is idle, for example for long imports or media processing, publish the preset:

```bash
php artisan podman:publish development
```

In `containers/stubs/development/quadlets/queue.quadlets` (or `horizon.quadlets`), replace the app dependency under `[Unit]` with the database and cache your app uses:

```ini
# Remove:
After={{application}}.container
PartOf={{application}}.container

# Add:
Requires={{application}}-pgsql.container {{application}}-valkey.container
After={{application}}-pgsql.container {{application}}-valkey.container
```

The app still starts the worker through its `Wants=` line, but no longer stops it. Regenerate and reinstall the worker, and install the [idle check](#the-idle-check) so it stops once its jobs are done:

```bash
php artisan podman:generate development
lpod install development/queue.quadlets --replace   # or horizon.quadlets
lpod install ondemand/my-app-idle.timer --replace
```

In `production`, the queue worker and Horizon already work this way.

A worker that keeps running also needs the services its jobs use, such as `rustfs` for uploads, `typesense` for search indexing, `reverb` for broadcast events or `mailpit` for queued mail. Otherwise they [sleep](#sleeping-services) with the app while jobs still use them. Add a `Wants=` line under the worker's `[Unit]`:

```ini
Wants={{application}}-rustfs.container {{application}}-typesense.container
```

## Sleeping services

The database, cache and other services sleep too, once nothing needs them anymore: the app is idle and no jobs are left. Nothing runs until the next request, much like hibernation on a managed platform.

`PODMAN_ONDEMAND_ENABLED=false` keeps the app and every service running. Do that when something outside the app needs them, such as a database client, a backup job or a public S3 bucket (see [caveats](#caveats)).

To keep only one service running, such as the database for a client, publish the preset and set `StopWhenUnneeded=no` in that service's quadlet.

### How it works

`{{ondemand}}` renders `StopWhenUnneeded=yes` on the database, cache, search and storage services. systemd then stops a service once no running unit `Requires=` or `Wants=` it anymore:

1. The app goes idle and stops, along with its sidecars.
2. The [idle check](#the-idle-check) stops the queue workers once no jobs are left, and the `production` scheduler timer.
3. Nothing needs the database and cache anymore, so they stop too.
4. The next request starts the app, which starts the services, workers and scheduler timer it `Requires=` or `Wants=`. The services report ready through a health check (`Notify=healthy`). The app waits for the ones on its `After=` line, the database and cache, so it doesn't connect before they accept connections.

Traffic between containers doesn't pass through the on-demand socket. A service stays up because a running unit needs it, not because of an idle timer, so a query from the app or a worker never hits a stopped database.

**Every service must be needed by something.** A service with `StopWhenUnneeded=yes` that nothing `Requires=` or `Wants=` stops right after it starts. The app `Requires=` the database and cache. Using another service, such as `rustfs`, `typesense` or `meilisearch`? Publish the preset and add it to the app's `Wants=` line (see [Customizing](customizing.md)), and to a [kept-running worker's](#keeping-the-queue-worker-running) if its jobs use it:

```ini
Wants={{application}}-mailpit.container {{application}}-queue.container {{application}}-schedule.container {{application}}-rustfs.container
```

`Wants=` starts the service alongside the app, but the app doesn't wait for it. If the first request after waking needs it, such as media from `rustfs` or search from `typesense`, also add it to the app's `After=` line. The app then starts once the service is healthy, which makes waking slower by the time the service takes to start. Do the same for a kept-running worker, so it doesn't pick up a job before the service is ready:

```ini
After={{application}}-pgsql.container {{application}}-valkey.container {{application}}-rustfs.container
```

### The idle check

Queue workers that keep running while the app is idle would keep the database and cache awake. The `ondemand` preset's idle check (`lpod install ondemand/my-app-idle.timer`) handles this for both `development` and `production`.

Once a minute, while the app is asleep, it runs `php artisan podman:idle` in each running queue worker or Horizon. If the app has no work in progress, it stops the workers and the scheduler timer, if one is running (`production`). A worker that isn't running is skipped, so the scheduler timer still stops. Before stopping anything, it checks again that the app is still asleep, so a request that wakes the app during the check keeps its workers running. The next request starts them again through the app's `Wants=` line, which lists `queue` and, in `production`, `schedule.timer`. Using Horizon? Put `horizon` on that line instead of `queue`.

A long job keeps the stack awake until it's done. Delayed jobs count too, so they run on time. If `podman:idle` fails or doesn't exist, for example because the package was installed with `--dev` and the production image leaves it out, the workers keep running. The check does nothing when `PODMAN_ONDEMAND_ENABLED=false`.

#### Idle checks

`podman:idle` runs every check for something your app uses, going by its config, much like [Laravel Health](https://spatie.be/docs/laravel-health) checks:

| Check | Used when | Busy while |
| --- | --- | --- |
| `queue` | `QUEUE_CONNECTION` isn't `sync` or `null` | Jobs are waiting, running or delayed, on the default queue or any Horizon supervisor's queues |
| `database` | `DB_CONNECTION` is set | Another client runs a query or holds a transaction open (PostgreSQL, MySQL, MariaDB), or an operation runs on the app's MongoDB database (with `mongodb/laravel-mongodb`). Idle connections, like a worker waiting for jobs, don't count |
| `scout` | `SCOUT_DRIVER` is a search engine, like `typesense` or `meilisearch` | Queued indexing jobs are left (`scout.queue`), or Meilisearch still processes indexing tasks in the background |

Pick checks with `--services`. Unknown names fail, so a typo keeps the workers running rather than stopping them:

```bash
php artisan podman:idle --services=queue,database
```

You can change the checks in two ways. To add or remove checks, edit the list in `config/podman.php` (publish it with `php artisan vendor:publish --tag="podman-config"`). The list takes class names, so each check runs with its defaults:

```php
'idle' => [
    'checks' => [
        QueueCheck::class,
        DatabaseCheck::class,
        ScoutCheck::class,
    ],
],
```

To configure a check, like the queues it watches, register the checks from a service provider's `boot()` method instead. Registered checks replace the config list, so include every check you want:

```php
use Foxws\Podman\Support\Idle\Checks\DatabaseCheck;
use Foxws\Podman\Support\Idle\Checks\QueueCheck;
use Foxws\Podman\Support\Idle\PodmanIdle;

app(PodmanIdle::class)->checks([
    QueueCheck::new()->connection('redis')->queues(['default', 'media']),
    DatabaseCheck::new(),
]);
```

Write your own by extending `IdleCheck`. Give it a `name()`, return an `IdleResult` from `run()`, and optionally override `isEnabled()`:

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

### Caveats

- **Cold start.** The first request after idling also waits for the services, which adds a few seconds.
- **The scheduler doesn't run while the stack sleeps.** Tasks that must run on time, like nightly backups or reports, need `PODMAN_ONDEMAND_ENABLED=false`.
- **Host ports don't wake anything.** A database client, or a request to RustFS or Mailpit through the `proxy` preset, can't start a sleeping service. Open the app first, e.g. with `lpod my-app open`. Starting the service by hand doesn't help: nothing needs it, so systemd stops it again.
- **Health checks.** Every service reports ready through a health check, except Memcached (it starts right away) and Reverb. Those count as started as soon as their container runs.

## External proxies

The socket is a plain TCP port on the host, so any reverse proxy can sit in front of it, such as a Synology NAS, Nginx or Traefik on another machine. You don't need the `proxy` preset then:

1. Listen on the LAN: `PODMAN_ONDEMAND_LISTEN=0.0.0.0:8000` (or the host's LAN IP).
2. Point the proxy at `http://your-host:8000` and let it handle HTTPS.
3. Set `APP_URL` to the public `https://` URL, and make sure Laravel trusts the proxy's `X-Forwarded-*` headers (`trustProxies`).
4. Only allow the proxy to reach port `8000`, e.g. with a firewalld rich rule. Traffic between the proxy and the host is plain HTTP.

A cold start takes a few seconds, well within the default timeouts of most proxies. The proxy doesn't need to retry: the connection is held until the app is ready.

## Caveats

- **Cold start.** The first request after idling takes about 1–3 seconds with `production`, and longer with the `development` preset's file watcher.
- **Connections keep the app awake.** Open WebSockets or SSE through the app, and uptime monitors that request it more often than `idle_timeout`, prevent it from stopping. Point monitors at the proxy instead.
- **`lpod my-app up` doesn't keep it running.** Nothing needs the app, so systemd stops it again. Send a request instead, e.g. `lpod my-app open`. Run `lpod my-app artisan ...` while it's awake.
- **Health checks.** While the app starts, a startup check polls `/up` every second, and the app counts as started as soon as it answers. After that, `/up` is checked once a minute. Publish the preset to change the path or interval.
