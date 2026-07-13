# Proxy

The bundled `proxy` runtime/service runs [Caddy](https://caddyserver.com/) as a reverse proxy. It terminates HTTPS — with automatic local certificates in development and automatic Let's Encrypt certificates in production — and routes subdomains to your app, Vite dev server, Reverb, and RustFS containers.

> To use Traefik, Nginx, or another proxy instead, omit `proxy` when running `podman:setup` (`--service=` without `proxy`, and drop it from `--runtime=`/the `runtimes` config too) and point your own proxy at the `app`, `reverb`, and `rustfs` containers.

## Setup

`proxy` is one of the default runtimes and services (see the `runtimes`/`services` keys in `config/podman.php`), so a plain setup already publishes and installs it:

```bash
php artisan podman:setup
```

To publish and install just the proxy on its own:

```bash
php artisan podman:publish proxy
php artisan podman:install proxy
```

## Configuring Caddy

`podman:publish proxy` writes the editable Caddy config into your project at `runtimes/proxy/` (`Caddyfile` and `sites/laravel.Caddyfile`). The running container mounts this same directory at `/etc/caddy`, so edits take effect on the next restart:

```bash
vi runtimes/proxy/Caddyfile runtimes/proxy/sites/laravel.Caddyfile
```

The bundled `sites/laravel.Caddyfile` routes your app's domain (derived from `APP_URL`) and a few subdomains to their containers:

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

For local development, point your app's domain and subdomains at `127.0.0.1` in `/etc/hosts` (adjust `app.test` to your actual `APP_URL` host):

```text
127.0.0.1 app.test vite.app.test ws.app.test s3.app.test fs.app.test mail.app.test
::1       app.test vite.app.test ws.app.test s3.app.test fs.app.test mail.app.test
```

For a homelab/multi-device setup, a local DNS resolver (e.g. [AdGuard Home](https://adguard.com/en/adguard-home/overview.html)) with a wildcard rewrite for `*.app.test` → your server IP avoids editing hosts files per device.

## Trusting the local certificate

Local development uses Caddy's own certificate authority (`local_certs` in the bundled `Caddyfile`). Trust it once so your browser/OS stop flagging it:

```bash
podman cp systemd-proxy:/data/caddy/pki/authorities/local/root.crt ~/proxy.crt

# macOS
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ~/proxy.crt

# Linux (Arch/Debian/Ubuntu)
sudo cp ~/proxy.crt /usr/local/share/ca-certificates/caddy.crt && sudo update-ca-certificates
```

This is for local development only — in production, point `APP_URL` (and the domains in `sites/*.Caddyfile`) at a real public domain, and Caddy obtains/renews a proper Let's Encrypt certificate automatically.

## Troubleshooting

- **Certificate not trusted** — re-import the CA certificate (above) and restart your browser.
- **Connection refused** — check `vendor/bin/lpod proxy status`, and confirm ports 80/443 aren't already in use by something else on the host.
- **404 / wrong container** — verify the domain in `sites/laravel.Caddyfile` matches `APP_URL`, and that the target service (e.g. `reverb`) is installed and running.
- **Changes not applying** — Caddy only picks up `runtimes/proxy/` edits after `vendor/bin/lpod proxy restart`.
