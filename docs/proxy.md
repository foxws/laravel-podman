# Proxy

The bundled `proxy` preset runs [Caddy](https://caddyserver.com/) as a reverse proxy. It handles HTTPS (local certificates in development, Let's Encrypt in production) and routes subdomains to your app, Vite, Reverb, and RustFS containers.

> To use Traefik, Nginx, or another proxy, omit the `proxy` preset when running `podman:setup` (and remove it from `presets`), then point your own proxy to `app`, `reverb`, and `rustfs`.

## Setup

`proxy` is one of the default presets (see the `presets` key in `config/podman.php`), so a plain setup already generates it:

```bash
php artisan podman:setup
lpod install proxy/proxy.quadlets --replace
```

To generate and install just the proxy on its own:

```bash
php artisan podman:generate proxy
lpod install proxy/proxy.quadlets --replace
```

## Configuring Caddy

The preset Caddy config lives in `stubs/proxy/runtimes/` (`Caddyfile` and `sites/laravel.Caddyfile`) until you customize it. `podman:generate proxy` renders those files into `podman/proxy/runtimes/`, which the running container mounts at `/etc/caddy`. That generated folder is overwritten on each regenerate, so use it only for quick temporary changes:

```bash
vi podman/proxy/runtimes/Caddyfile
lpod proxy restart
```

For persistent changes, publish the preset first, edit the published copy, then regenerate and restart:

```bash
php artisan podman:publish proxy   # copies stubs/proxy into containers/stubs/proxy
vi containers/stubs/proxy/runtimes/Caddyfile containers/stubs/proxy/runtimes/sites/laravel.Caddyfile
php artisan podman:generate proxy
lpod proxy restart
```

The bundled `sites/laravel.Caddyfile` routes your app domain (from `APP_URL`) and a few subdomains:

| Subdomain | Routes to            |
| --------- | --------------------- |
| (root)    | The app (`app`)       |
| `vite.`   | Vite dev server        |
| `ws.`     | Reverb (WebSockets)    |
| `s3.`     | RustFS (S3 API)        |
| `fs.`     | RustFS console         |
| `mail.`   | Mailpit (dev mail catcher) |

Add or edit `*.Caddyfile` files under `sites/` for additional domains or services.

## Starting the Proxy

```bash
vendor/bin/lpod proxy up
vendor/bin/lpod proxy status
vendor/bin/lpod proxy restart   # after editing the Caddyfile
```

## DNS

For local development, point your app's domain and subdomains at `127.0.0.1` in `/etc/hosts` (adjust `laravel.test` to your actual `APP_URL` host):

```text
127.0.0.1 laravel.test vite.laravel.test ws.laravel.test s3.laravel.test fs.laravel.test mail.laravel.test
::1       laravel.test vite.laravel.test ws.laravel.test s3.laravel.test fs.laravel.test mail.laravel.test
```

For homelab or multi-device setups, use a local DNS resolver (for example [AdGuard Home](https://adguard.com/en/adguard-home/overview.html)) with a wildcard rewrite from `*.laravel.test` to your server IP. This avoids editing hosts files on every device.

## Trusting the local certificate

Local development uses Caddy's own certificate authority (`local_certs` in the bundled `Caddyfile`). Trust it once so your browser/OS stop warning:

```bash
podman cp systemd-proxy:/data/caddy/pki/authorities/local/root.crt ~/proxy.crt

# macOS
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ~/proxy.crt

# Linux (Arch/Debian/Ubuntu)
sudo cp ~/proxy.crt /usr/local/share/ca-certificates/caddy.crt && sudo update-ca-certificates
```

This is for local development only. In production, point `APP_URL` (and domains in `sites/*.Caddyfile`) to a real public domain and Caddy will automatically obtain and renew a Let's Encrypt certificate.

## Troubleshooting

- **Certificate not trusted** — re-import the CA certificate (above) and restart your browser.
- **Connection refused** — check `vendor/bin/lpod proxy status`, and confirm ports 80/443 aren't already in use by something else on the host.
- **404 / wrong container** — verify the domain in `sites/laravel.Caddyfile` matches `APP_URL`, and that the target service (e.g. `reverb`) is installed and running.
- **Changes not applying** — Caddy picks up `podman/proxy/runtimes/` changes only after `vendor/bin/lpod proxy restart`; if you changed `containers/stubs/proxy/runtimes/`, run `php artisan podman:generate proxy` first.
