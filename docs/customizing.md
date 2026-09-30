---
section: Configuration
order: 1
---

# Customizing

You customize the package in two places:

- **`config/podman.php`**, for names, paths and on/off switches. Publish it with `php artisan vendor:publish --tag="podman-config"`.
- **The preset templates**, for the services themselves: images, dependencies, memory limits and build files.

## Config

| Key | Env variable | Default | Purpose |
| --- | --- | --- | --- |
| `enabled` | `PODMAN_ENABLED` | `true` | Turns all `podman:*` commands on or off |
| `quadlet_prefix` | `PODMAN_QUADLET_PREFIX` | `APP_NAME` (or `laravel`) | Prefix for service names, e.g. `laravel-pgsql` |
| `proxy_prefix` | `PODMAN_PROXY_PREFIX` | `proxy` | Name of the `proxy` service and network |
| `stubs_path` | `PODMAN_STUBS_PATH` | `containers/stubs` | Where your own presets live |
| `working_path` | `PODMAN_WORKING_PATH` | Laravel's `base_path()` | Host path for `{{workingPath}}`/`{{runtimePath}}`. Override per run with `podman:generate --working-path=` |
| `config_path` | `PODMAN_CONFIG_PATH` | `working_path` | Host path for `{{configPath}}`, for service config kept outside the project |
| `quadlet_uid`/`quadlet_gid` | `PODMAN_QUADLET_UID`/`_GID` | Your UID/GID | Written into the Quadlet files |
| `publish_path` | `PODMAN_PUBLISH_PATH` | `podman` | Where rendered presets go. Don't commit this folder |
| `selinux_volume_mapping` | `PODMAN_SELINUX_VOLUME_MAPPING` | `true` | Keeps the `Z`/`z`/`U` volume flags. Turn off on hosts without SELinux |
| `presets` | `PODMAN_DEFAULT_PRESETS` | see `config/podman.php` | Presets that `podman:setup` renders |
| `s3_buckets` | `PODMAN_S3_BUCKETS` | see `config/podman.php` | Buckets `podman:s3-setup` creates. See [S3 Buckets](s3.md) |
| `s3_cors_buckets` | `PODMAN_S3_CORS_BUCKETS` | see `config/podman.php` | Which of those buckets get the CORS policy |
| `substitutions` | *(none)* | `[]` | Your own `{{placeholder}}` values. See [Your own placeholders](#your-own-placeholders) |
| `ondemand.*` | `PODMAN_ONDEMAND_*` | enabled | Start the app on its first request and stop it when idle. See [On-demand services](ondemand.md) |

`presets`, `s3_buckets` and `s3_cors_buckets` take a PHP array or a comma-separated string.

## Presets and templates

A preset is a folder with two directories: `quadlets/` for the `*.quadlets` files, and `runtimes/` for build files such as the `Containerfile`, `entrypoint.sh`, PHP ini and Caddy templates.

To change a preset, publish it to `stubs_path` (default `containers/stubs`) and edit the copy:

```bash
php artisan podman:publish production
# edit containers/stubs/production/quadlets/pgsql.quadlets
php artisan podman:generate production
lpod install production/pgsql.quadlets --replace
```

A published preset replaces the bundled one completely. Files aren't merged one by one, so the published folder must hold every file the preset needs.

- **Add a service:** create `containers/stubs/production/quadlets/my-service.quadlets` in the same `# FileName=...` / `---` format as the others. Then generate the preset and run `lpod install production/my-service.quadlets`.
- **Create a new preset:** create `containers/stubs/my-preset/quadlets/` and `containers/stubs/my-preset/runtimes/`.

### Placeholders

Templates contain `{{placeholder}}` tokens. `podman:publish` leaves them as they are, and `podman:generate` fills them in:

| Placeholder | Value |
| --- | --- |
| `{{application}}` | `quadlet_prefix`, kebab-cased |
| `{{proxy}}` | `proxy_prefix`, kebab-cased |
| `{{appEnv}}` | `app.env` |
| `{{appName}}` | `app.name` |
| `{{appUrl}}` | `app.url` |
| `{{appHost}}` | The host part of `app.url` |
| `{{appUid}}`/`{{appGid}}` | `quadlet_uid`/`quadlet_gid` |
| `{{workingPath}}` | `working_path` |
| `{{configPath}}` | `config_path` (the same as `working_path` unless set) |
| `{{runtimePath}}` | The preset's rendered `runtimes/` folder, e.g. `podman/production/runtimes` |
| `{{systemctl}}` | `systemctl --user`, or `systemctl` when `quadlet_uid` is `0` (root) |
| `{{ondemand}}`, `{{ondemandListen}}`, `{{ondemandPort}}`, `{{ondemandIdleTimeout}}`, `{{appUpstream}}` | [On-demand](ondemand.md) settings |

`{{configPath}}` is useful for keeping a service's config outside the project, for example in your own `proxy.quadlets`:

```ini
Volume={{configPath}}/{{proxy}}:/etc/caddy:rw,z,U
```

### Your own placeholders

Add your own placeholders with `substitutions` in `config/podman.php`. It's plain PHP, so `env()` works:

```php
'substitutions' => [
    '{{apiEndpoint}}' => env('API_ENDPOINT'),
],
```

```ini
Environment=API_ENDPOINT={{apiEndpoint}}
```

A substitution with the same name as a built-in placeholder, such as `{{appHost}}`, replaces it.

## Services

Every preset except `devcontainer` and `s3` includes `app` plus these services. Use one per category.

| Category | Services (default first) |
| --- | --- |
| Database | `pgsql`, `mariadb`, `mysql`, `mongodb` |
| Cache/queue | `valkey`, `redis`, `memcached` |
| Queue worker | `queue`, `horizon` |
| Search | `typesense`, `meilisearch` |
| Object storage | `rustfs` |
| Mail catcher | `mailpit` |

`queue` runs a plain `php artisan queue:work` worker, which works with any queue connection. If your app uses [Laravel Horizon](https://laravel.com/docs/horizon) (Redis or Valkey queues only), use `horizon` instead. See [Replacing the queue worker with Horizon](#replacing-the-queue-worker-with-horizon).

`production` also includes `schedule` and `inertia-ssr`, which run alongside the app. `reverb` ([Laravel Reverb](https://laravel.com/docs/reverb)) is included too, but doesn't start by default. See [Adding Reverb](#adding-reverb).

In `production`, the queue worker and Horizon start at boot, and `systemd/schedule.timer` runs `schedule:run` every minute. Install the timer with `lpod install production/my-app-schedule.timer`.

### Swapping the database or cache

`app.quadlets` names its database and cache in its `[Unit]` section. To switch, publish the preset:

```bash
php artisan podman:publish production   # or development
```

Edit `containers/stubs/production/quadlets/app.quadlets`:

```ini
Requires={{application}}-mysql.container {{application}}-redis.container
After={{application}}-mysql.container {{application}}-redis.container
```

Regenerate and reinstall both:

```bash
php artisan podman:generate production
lpod install production/mysql.quadlets --replace
lpod install production/app.quadlets --replace
```

Then update `.env` (`DB_CONNECTION`, `DB_HOST` and so on) so Laravel connects to the new service. Quadlet names containers `systemd-{unit}`, so the host is, for example, `systemd-my-app-mysql`.

### Replacing the queue worker with Horizon

`app.quadlets` starts `queue` alongside the app through its `Wants=` line. To use `horizon` instead, publish the preset and change `queue` to `horizon` on that line (in `production`, the line ends with `schedule.timer`):

```ini
Wants={{application}}-mailpit.container {{application}}-horizon.container {{application}}-schedule.container
```

Regenerate, then install `horizon` and reinstall `app`:

```bash
php artisan podman:generate production
lpod install production/horizon.quadlets --replace
lpod install production/app.quadlets --replace
```

If `queue` was already running, stop it with `systemctl --user stop {app}-queue`. It won't start again, because nothing wants it anymore.

### Adding Reverb

`reverb` isn't started by default, because it needs [Laravel Reverb](https://laravel.com/docs/reverb) installed in your app. To run it, publish the preset and add it to the `Wants=` line in `app.quadlets`:

```ini
Wants={{application}}-mailpit.container {{application}}-queue.container {{application}}-reverb.container {{application}}-schedule.container
```

Regenerate, then install `reverb` and reinstall `app`:

```bash
php artisan podman:generate production
lpod install production/reverb.quadlets --replace
lpod install production/app.quadlets --replace
```

### How services depend on each other

| Directive | Meaning | Used for |
| --- | --- | --- |
| `Requires=` | Hard dependency. If the target fails, this unit stops too | `app` → database and cache |
| `After=` | Start order only | Together with `Requires=`/`Wants=` |
| `Wants=` | Soft dependency. Tries to start the target, but doesn't fail without it | `app` → `mailpit`/`reverb`/`vite`, `queue`/`schedule` in `development`, and `queue`/`schedule.timer` in `production` |
| `PartOf=` | Stopping or restarting the target also stops or restarts this unit | `vite`/`inertia-ssr`, and `horizon`/`queue`/`schedule` in `development` → `app` |
| `BindsTo=` | Like `Requires=`, and also stops when the target stops. Not used: it counts as needing the target, which keeps an [on-demand](ondemand.md) app running | |

## Other tasks

### Increasing a service's memory limit

Publish the preset, add `Memory=1G` under `[Container]` in that service's quadlet, for example `containers/stubs/production/quadlets/pgsql.quadlets`, then regenerate and reinstall it:

```bash
php artisan podman:generate production
lpod install production/pgsql.quadlets --replace
```

### Running several apps on one host

Pass `--application=` to `lpod install` (needs Podman 6 or later), so each app gets its own install folder. With on-demand services, also give each app its own `ondemand.listen` and `ondemand.port`.

### Proxying services without the `proxy` preset

`production` runs [Octane with FrankenPHP](https://laravel.com/docs/octane#frankenphp), which has Caddy built in. This is separate from the `proxy` preset's Caddy container (see [Proxy](proxy.md)).

If you don't use the `proxy` preset, for example behind an external load balancer or on Laravel Cloud, the built-in Caddy can proxy other services (Reverb, Mailpit, S3 storage) itself. You configure it through Octane's `CADDY_EXTRA_CONFIG` environment variable.

`Foxws\Podman\Support\PodmanCaddySites` builds that value from env vars. Use it in `config/octane.php`:

```php
use Foxws\Podman\Support\PodmanCaddySites;

'caddy' => [
    'env' => [
        // Port must match the "--port" passed to "octane:frankenphp" in APP_COMMAND.
        'CADDY_EXTRA_CONFIG' => PodmanCaddySites::render([
            PodmanCaddySites::hostFromUrl((string) env('AWS_URL')) => PodmanCaddySites::hostPortFromUrl((string) env('AWS_ENDPOINT')),
            (string) env('VITE_REVERB_HOST', env('REVERB_HOST')) => PodmanCaddySites::hostPort(env('REVERB_HOST'), env('REVERB_PORT', 6001)),
            (string) env('MAILPIT_UI_HOST') => PodmanCaddySites::hostPort(env('MAIL_HOST'), 8025),
        ], (int) env('OCTANE_PORT', 8000)),
    ],
],
```

With `AWS_URL=https://s3.laravel.test`, `VITE_REVERB_HOST=ws.laravel.test` and `MAILPIT_UI_HOST=mail.laravel.test` in `.env` (the same subdomains the [`proxy` preset](proxy.md) uses), you get:

| Hostname | Upstream | Env vars |
| --- | --- | --- |
| `s3.laravel.test` | e.g. `minio:9000` | `AWS_URL`, `AWS_ENDPOINT` |
| `ws.laravel.test` | e.g. `reverb:6001` | `VITE_REVERB_HOST` (or `REVERB_HOST`), `REVERB_HOST`, `REVERB_PORT` |
| `mail.laravel.test` | e.g. `mailpit:8025` | `MAILPIT_UI_HOST`, `MAIL_HOST` |

Add or remove entries to match the services you use.

A few things to know:

- Use `env()` here, not `config()`. Config files can't rely on each other's load order.
- An entry with an empty hostname or upstream (unset env var) is skipped.
- `render()` uses `http://` by default. With a bare hostname, Caddy would try automatic HTTPS on port 443. The `production` image runs as a non-root user without permission to bind that port, so the server would crash. Only pass the third `$scheme` argument if your Caddy is allowed to bind ports below 1024.

## Links

- [Command Reference](commands.md)
- [Setting up without PHP](host-setup.md)
- [Proxy](proxy.md)
- [S3 Buckets](s3.md)
- [`lpod` CLI](lpod.md)
- [CI: Building a Container Image](ci-build.md)
- [Introduction](index.md)
