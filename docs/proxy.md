---
section: Configuration
order: 3
---

# Proxy

The bundled `proxy` preset runs [Caddy](https://caddyserver.com/) as a reverse proxy. It handles HTTPS (local certificates in development, Let's Encrypt in production) and routes subdomains to your app, Vite, Reverb, and RustFS containers.

Prefer Traefik, Nginx, or another proxy? Leave `proxy` out of `presets` and point your proxy at `app`, `reverb`, and `rustfs` yourself.

## Setup

`proxy` is a default preset, so a plain setup already generates it:

```bash
php artisan podman:setup
lpod install proxy/proxy.quadlets --replace
```

Or generate and install just the proxy on its own:

```bash
php artisan podman:generate proxy
lpod install proxy/proxy.quadlets --replace
```

## Configuring Caddy

`podman:generate proxy` renders `stubs/proxy/runtimes/` (`Caddyfile`, `sites/laravel.Caddyfile`) into `podman/proxy/runtimes/`, which the container mounts at `/etc/caddy`. That folder is overwritten every time you regenerate — fine for quick, temporary changes:

```bash
vi podman/proxy/runtimes/Caddyfile
lpod proxy restart
```

For changes you want to keep, publish first:

```bash
php artisan podman:publish proxy
vi containers/stubs/proxy/runtimes/Caddyfile containers/stubs/proxy/runtimes/sites/laravel.Caddyfile
php artisan podman:generate proxy
lpod proxy restart
```

The bundled `sites/laravel.Caddyfile` routes your app domain (`APP_URL`) and a few subdomains:

| Subdomain | Routes to |
| --- | --- |
| (root) | The app (`app`) |
| `vite.` | Vite dev server |
| `ws.` | Reverb (WebSockets) |
| `s3.` | RustFS (S3 API) |
| `fs.` | RustFS console |
| `mail.` | Mailpit (dev mail catcher) |

Add more `*.Caddyfile` files under `sites/` for more domains/services.

## Starting the proxy

```bash
lpod proxy up
lpod proxy status
lpod proxy restart   # after editing the Caddyfile
```

## DNS

Point your domain and subdomains at `127.0.0.1` in `/etc/hosts` (swap `laravel.test` for your `APP_URL` host):

```text
127.0.0.1 laravel.test vite.laravel.test ws.laravel.test s3.laravel.test fs.laravel.test mail.laravel.test
::1       laravel.test vite.laravel.test ws.laravel.test s3.laravel.test fs.laravel.test mail.laravel.test
```

On a homelab or with multiple devices, a local DNS resolver (e.g. [AdGuard Home](https://adguard.com/en/adguard-home/overview.html)) with a wildcard rewrite from `*.laravel.test` to your server IP is easier than editing hosts files everywhere.

## Trusting the local certificate

Local dev uses Caddy's own CA (`local_certs`). Trust it once:

```bash
lpod proxy export-cert   # writes ~/proxy.crt (pass a path to override)

# macOS
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ~/proxy.crt

# Linux (Arch/Debian/Ubuntu)
sudo cp ~/proxy.crt /usr/local/share/ca-certificates/caddy.crt && sudo update-ca-certificates
```

This is only needed for local development. In production, point `APP_URL`/`sites/*.Caddyfile` at a real domain and Caddy handles Let's Encrypt automatically.

## Troubleshooting

| Problem | What to do |
| --- | --- |
| Certificate not trusted | Re-import the CA certificate (above), then restart your browser |
| Connection refused | Check `lpod proxy status`, confirm ports 80/443 aren't already in use |
| 404 / wrong container | Verify `sites/laravel.Caddyfile` matches `APP_URL`, and that the target service is installed and running |
| Changes not applying | `lpod proxy restart` picks up `podman/proxy/runtimes/` changes; if you edited `containers/stubs/`, run `podman:generate proxy` first |
