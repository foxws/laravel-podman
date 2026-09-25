---
section: Configuration
order: 1
---

# Customizing

You customize the package in two places: `config/podman.php` (publish it with `php artisan vendor:publish --tag="podman-config"`) and the preset template files.

## Config keys

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
| `substitutions` | *(none)* | `[]` | Your own `{{placeholder}}` values. See [Custom substitutions](#custom-substitutions) |

`presets`, `s3_buckets` and `s3_cors_buckets` take a PHP array or a comma-separated string.

## Custom presets

A preset is a folder with two directories: `quadlets/` for the `*.quadlets` files and `runtimes/` for build files. If `stubs_path/{preset}` exists, it's used instead of the bundled preset. It replaces the whole preset; files are not merged one by one.

- **Change a service:** run `php artisan podman:publish frankenphp-octane`, then edit `containers/stubs/frankenphp-octane/quadlets/pgsql.quadlets`.
- **Add a service:** create `containers/stubs/frankenphp-octane/quadlets/my-service.quadlets` in the same `# FileName=...` / `---` format. Then run `php artisan podman:generate frankenphp-octane` and `lpod install frankenphp-octane/my-service.quadlets`.
- **Change build files:** same idea, in `runtimes/` (`Containerfile`, `entrypoint.sh`, PHP ini, Caddy templates).
- **New preset:** create `containers/stubs/my-preset/quadlets/` and `containers/stubs/my-preset/runtimes/`.

`podman:publish` leaves placeholders as they are. `podman:generate` fills them in:

| Placeholder | Value |
| --- | --- |
| `{{application}}` | `quadlet_prefix`, kebab-cased |
| `{{proxy}}` | `proxy_prefix`, kebab-cased |
| `{{appEnv}}` | `app.env` |
| `{{appName}}` | `app.name` |
| `{{appUrl}}` | `app.url` |
| `{{appHost}}` | Host part of `app.url` |
| `{{appUid}}`/`{{appGid}}` | `quadlet_uid`/`quadlet_gid` |
| `{{workingPath}}` | `working_path` |
| `{{configPath}}` | `config_path` (same as `working_path` unless set) |
| `{{runtimePath}}` | The preset's rendered `runtimes/` folder, e.g. `podman/frankenphp-octane/runtimes` |

`{{configPath}}` is useful for keeping a service's config outside the project, for example in your own `proxy.quadlets`:

```ini
Volume={{configPath}}/{{proxy}}:/etc/caddy:rw,z,U
```

## Custom substitutions

Add your own placeholders with `substitutions`. It's plain PHP, so `env()` works:

```php
'substitutions' => [
    '{{apiEndpoint}}' => env('API_ENDPOINT'),
],
```

```ini
Environment=API_ENDPOINT={{apiEndpoint}}
```

You can also override a built-in placeholder such as `{{appHost}}` this way. `substitutions` always wins.

## Available services

Every preset except `devcontainer` and `s3` includes `app` plus these services. Pick one per category; they replace each other.

| Category | Services (default first) |
| --- | --- |
| Database | `pgsql`, `mariadb`, `mysql`, `mongodb` |
| Cache/queue | `valkey`, `redis`, `memcached` |
| Queue worker | `queue`, `horizon` |
| Search | `typesense`, `meilisearch` |
| Object storage | `rustfs` |
| Mail catcher | `mailpit` |

`queue` runs a plain `php artisan queue:work` worker, which works with any queue connection. If your app uses [Laravel Horizon](https://laravel.com/docs/horizon) (Redis or Valkey queues only), use `horizon` instead. See [Replacing the queue worker with Horizon](#replacing-the-queue-worker-with-horizon).

`frankenphp-octane` also includes `schedule` and `inertia-ssr`, which always run alongside the app. `reverb` ([Laravel Reverb](https://laravel.com/docs/reverb)) is included too, but doesn't start by default. See [Adding Reverb](#adding-reverb).

## Swapping a service

`app.quadlets` names its database and cache in its `[Unit]` section. To switch, publish the preset:

```bash
php artisan podman:publish frankenphp-octane   # or development
```

Edit `containers/stubs/frankenphp-octane/quadlets/app.quadlets`:

```ini
Requires={{application}}-mysql.container {{application}}-redis.container
After={{application}}-mysql.container {{application}}-redis.container
```

Regenerate and reinstall both:

```bash
php artisan podman:generate frankenphp-octane
lpod install frankenphp-octane/mysql.quadlets --replace
lpod install frankenphp-octane/app.quadlets --replace
```

Then update `.env` (`DB_CONNECTION`, `DB_HOST`, ...) so Laravel connects to the new service.

### Replacing the queue worker with Horizon

`app.quadlets` starts `queue` alongside the app through its `Wants=` line. To use `horizon` instead, publish the preset and change `queue` to `horizon` on that line:

```ini
Wants={{application}}-mailpit.container {{application}}-horizon.container {{application}}-schedule.container
```

Regenerate, then install `horizon` and reinstall `app`:

```bash
php artisan podman:generate frankenphp-octane
lpod install frankenphp-octane/horizon.quadlets --replace
lpod install frankenphp-octane/app.quadlets --replace
```

If `queue` was already running, stop it with `systemctl --user stop {app}-queue`. It won't start again, because nothing wants it anymore.

### Adding Reverb

`reverb` isn't started by default, because it needs [Laravel Reverb](https://laravel.com/docs/reverb) installed in your app. To run it, publish the preset and add it to the `Wants=` line in `app.quadlets`:

```ini
Wants={{application}}-mailpit.container {{application}}-queue.container {{application}}-reverb.container {{application}}-schedule.container
```

Regenerate, then install `reverb` and reinstall `app`:

```bash
php artisan podman:generate frankenphp-octane
lpod install frankenphp-octane/reverb.quadlets --replace
lpod install frankenphp-octane/app.quadlets --replace
```

### `[Unit]` directives

| Directive | Meaning | Used for |
| --- | --- | --- |
| `Requires=` | Hard dependency. If the target fails, this unit stops too | `app` → database and cache |
| `After=` | Start order only | Together with `Requires=`/`Wants=` |
| `Wants=` | Soft dependency. Tries to start the target, but doesn't fail without it | `app` → `mailpit`/`queue`/`schedule` |
| `BindsTo=` | Like `Requires=`, and also stops when the target stops | `horizon`/`queue`/`reverb`/`schedule`/`inertia-ssr` → `app` |
| `PartOf=` | Stopping or restarting the target also stops or restarts this unit | `typesense`/`mailpit` → `app` |

## Increasing a service's memory limit

```bash
php artisan podman:publish frankenphp-octane
```

Add `Memory=` under `[Container]` in `containers/stubs/frankenphp-octane/quadlets/pgsql.quadlets`, then:

```bash
php artisan podman:generate frankenphp-octane
lpod install frankenphp-octane/pgsql.quadlets --replace
```

## Multiple apps on one host

Pass `--application=` to `lpod install` (needs Podman 6+). Each app then gets its own install folder.

## Proxying services without the `proxy` preset

`frankenphp-octane` runs [Octane with FrankenPHP](https://laravel.com/docs/octane#frankenphp), which has Caddy built in. This is separate from the `proxy` preset's Caddy container (see [Proxy](proxy.md)).

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

Notes:

- Use `env()` here, not `config()`. Config files can't rely on each other's load order.
- An entry with an empty hostname or upstream (unset env var) is skipped.
- `render()` uses `http://` by default. With a bare hostname, Caddy would try automatic HTTPS on port 443. The `frankenphp-octane` image runs as a non-root user without permission to bind that port, so the server would crash. Only pass the third `$scheme` argument if your Caddy is allowed to bind ports below 1024.

## Links

- [Command Reference](commands.md)
- [Setting up without PHP](host-setup.md)
- [Proxy](proxy.md)
- [S3 Buckets](s3.md)
- [`lpod` CLI](lpod.md)
- [CI: Building a Container Image](ci-build.md)
- [Introduction](index.md)
