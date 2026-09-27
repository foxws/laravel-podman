---
section: Configuration
order: 5
---

# On-demand services

On-demand services start your app on its first request and stop it again after it has been idle for a while (scale-to-zero). It's handy on a development machine or a homelab that hosts several apps: an app nobody is using doesn't hold on to Octane workers, SSR or a Vite server.

It's on by default for the `development` and `frankenphp-octane` presets. It only uses systemd and Quadlet, nothing else runs on the host.

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
| `ondemand.enabled` | `PODMAN_ONDEMAND_ENABLED` | `true` | Render presets on-demand |
| `ondemand.listen` | `PODMAN_ONDEMAND_LISTEN` | `8000` | Where the socket listens (systemd `ListenStream=`), e.g. `8000` or `192.168.1.10:8000` |
| `ondemand.port` | `PODMAN_ONDEMAND_PORT` | `18000` | Loopback port the app is published on for the socket proxy |
| `ondemand.idle_timeout` | `PODMAN_ONDEMAND_IDLE_TIMEOUT` | `10min` | How long the app may be idle before it stops |

Running several apps on one host? Give each its own `listen` and `port`.

Render and install as usual. The socket and its proxy service are plain systemd units, because Quadlet has no unit type for them. They live in the preset's `systemd/` folder and are rendered next to the `.quadlets` files:

```bash
php artisan podman:generate development
lpod install development/app.quadlets --replace
lpod install development/my-app-ondemand.socket --replace
```

`lpod install` copies the socket and its proxy service to `~/.config/systemd/user/` and enables the socket. On a server, also run `loginctl enable-linger` once, so the socket listens after a reboot without logging in.

With the `proxy` preset, regenerate it too. Caddy then sends app traffic to the socket (`host.containers.internal:8000`) instead of the container.

## What keeps running

| Service | `development` | `frankenphp-octane` |
| --- | --- | --- |
| Vite, Reverb, Inertia SSR | Stop with the app | Stop with the app |
| Queue worker / Horizon | Stops with the app | Keeps running, starts at boot |
| Scheduler | Stops with the app | A timer runs `schedule:run` every minute |
| Database, cache | Keep running | Keep running |

The presets are the same with on-demand on or off. Only `StopWhenUnneeded=` on the app changes (`{{ondemand}}` renders `yes` or `no`), along with where the `proxy` preset sends traffic. The socket units are always rendered, but only take effect once installed. Sidecars use `PartOf=` the app rather than `BindsTo=`: `BindsTo=` would count as needing the app and keep it running.

## External proxies

The socket is a plain TCP port on the host, so any reverse proxy can sit in front of it, such as a Synology NAS, Nginx or Traefik on another machine. You don't need the `proxy` preset then:

1. Listen on the LAN: `PODMAN_ONDEMAND_LISTEN=0.0.0.0:8000` (or the host's LAN IP).
2. Point the proxy at `http://your-host:8000` and let it handle HTTPS.
3. Set `APP_URL` to the public `https://` URL, and make sure Laravel trusts the proxy's `X-Forwarded-*` headers (`trustProxies`).
4. Only allow the proxy to reach port `8000`, e.g. with a firewalld rich rule. Traffic between the proxy and the host is plain HTTP.

A cold start takes a few seconds, well within the default timeouts of most proxies. The proxy doesn't need to retry: the connection is held until the app is ready.

## Caveats

- **Cold start.** The first request after idling takes about 1–3 seconds with `frankenphp-octane`, and longer with the `development` preset's file watcher.
- **Connections keep the app awake.** Open WebSockets or SSE through the app, and uptime monitors that request it more often than `idle_timeout`, prevent it from stopping. Point monitors at the proxy instead.
- **`lpod my-app up` doesn't keep it running.** Nothing needs the app, so systemd stops it again. Send a request instead, e.g. `lpod my-app open`. Run `lpod my-app artisan ...` while it's awake.
- **Health checks.** The app is checked every 2 seconds on `/up` while it runs, and only counts as started once `/up` answers. Publish the preset to change the path or interval.
