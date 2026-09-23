---
section: Configuration
order: 3
---

# Proxy

The `proxy` preset runs [Caddy](https://caddyserver.com/) as a reverse proxy. It handles HTTPS (local certificates in development, Let's Encrypt in production) and sends each subdomain to the right container: app, Vite, Reverb or RustFS.

To use Traefik, Nginx or another proxy instead, remove `proxy` from `presets` and point your proxy at `app`, `reverb` and `rustfs`.

## Setup

`proxy` is a default preset, so `podman:setup` already renders it:

```bash
php artisan podman:setup
lpod install proxy/proxy.quadlets --replace
```

To render only the proxy:

```bash
php artisan podman:generate proxy
lpod install proxy/proxy.quadlets --replace
```

## Configuring Caddy

`podman:generate proxy` writes the `Caddyfile` and `sites/laravel.Caddyfile` to `podman/proxy/runtimes/`. The container mounts that folder at `/etc/caddy`. It's overwritten on every regenerate, so only edit it there for quick tests:

```bash
vi podman/proxy/runtimes/Caddyfile
lpod proxy restart
```

To keep your changes, publish the preset and edit it there:

```bash
php artisan podman:publish proxy
vi containers/stubs/proxy/runtimes/Caddyfile containers/stubs/proxy/runtimes/sites/laravel.Caddyfile
php artisan podman:generate proxy
lpod proxy restart
```

`sites/laravel.Caddyfile` routes your app domain (`APP_URL`) and these subdomains:

| Subdomain | Routes to |
| --- | --- |
| (root) | The app (`app`) |
| `vite.` | Vite dev server |
| `ws.` | Reverb (WebSockets) |
| `s3.` | RustFS (S3 API) |
| `fs.` | RustFS console |
| `mail.` | Mailpit (dev mail catcher) |

For more domains or services, add `*.Caddyfile` files to `sites/`.

## Starting the proxy

```bash
lpod proxy up
lpod proxy status
lpod proxy restart   # after editing the Caddyfile
```

## DNS

Add your domain and subdomains to `/etc/hosts`. Replace `laravel.test` with the host from your `APP_URL`:

```text
127.0.0.1 laravel.test vite.laravel.test ws.laravel.test s3.laravel.test fs.laravel.test mail.laravel.test
::1       laravel.test vite.laravel.test ws.laravel.test s3.laravel.test fs.laravel.test mail.laravel.test
```

With several devices, it's easier to run a local DNS server like [AdGuard Home](https://adguard.com/en/adguard-home/overview.html) and point `*.laravel.test` at your server's IP.

## Trusting the local certificate

In development, Caddy signs certificates with its own CA (`local_certs`). Trust it once:

```bash
lpod proxy export-cert   # writes ~/proxy.crt (pass a path to override)

# macOS
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ~/proxy.crt

# Linux (Arch/Debian/Ubuntu)
sudo cp ~/proxy.crt /usr/local/share/ca-certificates/caddy.crt && sudo update-ca-certificates
```

You don't need this in production. Use a real domain in `APP_URL` and `sites/*.Caddyfile`, and Caddy gets a Let's Encrypt certificate automatically.

## Troubleshooting

| Problem | What to do |
| --- | --- |
| Certificate not trusted | Import the CA certificate again (see above) and restart your browser |
| Connection refused | Check `lpod proxy status`, and make sure nothing else uses ports 80/443 |
| 404 or wrong container | Check that `sites/laravel.Caddyfile` matches `APP_URL`, and that the service is installed and running |
| Changes don't apply | Run `lpod proxy restart`. If you edited `containers/stubs/`, run `podman:generate proxy` first |
