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

| Host | Placeholder | Routes to |
| --- | --- | --- |
| (root) | `{{appHost}}` | The app, through its [on-demand socket](ondemand.md) when on-demand is on |
| `vite.` | `{{viteHost}}` | Vite dev server (`{{viteUpstream}}`) |
| `ws.` | `{{reverbHost}}` | Reverb (`{{reverbUpstream}}`) |
| `s3.` | `{{s3Host}}` | RustFS S3 API (`{{s3Upstream}}`) |
| `fs.` | `{{s3ConsoleHost}}` | RustFS console (`{{s3ConsoleUpstream}}`) |
| `mail.` | `{{mailHost}}` | Mailpit web UI (`{{mailUpstream}}`) |

### Changing hosts and services

Every host and upstream is a [placeholder](customizing.md#your-own-placeholders), so you can change one in `substitutions` without publishing the preset. Take the hosts from `.env` so the proxy and your app read the same values:

```php
// config/podman.php
'substitutions' => [
    'viteHost' => env('VITE_HMR_HOST', 'vite.{{appHost}}'),
    'reverbHost' => env('VITE_REVERB_HOST', 'ws.{{appHost}}'),
    's3Upstream' => 'systemd-{{application}}-minio:9000',
],
```

Keep a default, as above: a site with an empty host stops Caddy from starting. To drop services you don't run, or to add others, publish the preset and edit `sites/laravel.Caddyfile`. Caddy loads every `*.Caddyfile` in `sites/`, so you can also add your own files there.

Reverb and Mailpit can also run under a path of the app's domain, such as `/ws` and `/mail`, without a site of their own. The app's embedded Caddy routes them, so the proxy needs no extra site. See [Paths instead of subdomains](customizing.md#paths-instead-of-subdomains).

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

When `APP_ENV` is `local`, Caddy signs certificates with its own CA (`local_certs`). Trust it once:

```bash
lpod proxy export-cert   # writes ~/proxy.crt (pass a path to override)

# macOS
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ~/proxy.crt

# Debian, Ubuntu
sudo cp ~/proxy.crt /usr/local/share/ca-certificates/caddy.crt && sudo update-ca-certificates

# Fedora, RHEL
sudo cp ~/proxy.crt /etc/pki/ca-trust/source/anchors/caddy.crt && sudo update-ca-trust

# Arch
sudo trust anchor ~/proxy.crt
```

You don't need this in other environments. Use a real domain in `APP_URL` and `sites/*.Caddyfile`, and Caddy gets a Let's Encrypt certificate automatically. To choose yourself, set the `proxyCerts` [substitution](customizing.md#your-own-placeholders), e.g. `'proxyCerts' => ''` for Let's Encrypt in `local` too.

## Troubleshooting

| Problem | What to do |
| --- | --- |
| Certificate not trusted | Import the CA certificate again (see above) and restart your browser |
| Connection refused | Check `lpod proxy status`, and make sure nothing else uses ports 80/443 |
| Proxy fails to start rootless, "permission denied" on port 80 | Allow rootless containers to use ports from 80: `echo 'net.ipv4.ip_unprivileged_port_start=80' \| sudo tee /etc/sysctl.d/99-lpod.conf && sudo sysctl --system` |
| Not sure what's wrong | Run `lpod doctor`. It checks the ports, the certificate, `APP_URL` and more, and prints how to fix them |
| 404 or wrong container | Check that `sites/laravel.Caddyfile` matches `APP_URL`, and that the service is installed and running |
| Changes don't apply | Run `lpod proxy restart`. If you edited `containers/stubs/`, run `podman:generate proxy` first |
