# Customizing

Driven by `config/podman.php` (`php artisan vendor:publish --tag="podman-config"`) and preset template files on disk.

## Config keys

| Key | Env variable | Default | Purpose |
| --- | --- | --- | --- |
| `enabled` | `PODMAN_ENABLED` | `true` | Master switch for `podman:generate`/`podman:setup`/`podman:publish`/`podman:s3-setup` |
| `quadlet_prefix` | `PODMAN_QUADLET_PREFIX` | `APP_NAME` (falls back to `laravel`) | Namespaces installed services, e.g. `laravel-pgsql` |
| `proxy_prefix` | `PODMAN_PROXY_PREFIX` | `proxy` | Namespace used for the `proxy` service/network |
| `stubs_path` | `PODMAN_STUBS_PATH` | `containers/stubs` | Lookup root for custom presets |
| `working_path` | `PODMAN_WORKING_PATH` | Laravel's `base_path()` | Host path baked into `{{workingPath}}`/`{{runtimePath}}`. Override per run with `--working-path=` on `podman:generate` |
| `config_path` | `PODMAN_CONFIG_PATH` | `working_path` | Host path baked into `{{configPath}}`, for a service's config living outside the project |
| `quadlet_uid`/`quadlet_gid` | `PODMAN_QUADLET_UID`/`_GID` | Current user's UID/GID | Baked into generated Quadlet files |
| `publish_path` | `PODMAN_PUBLISH_PATH` | `podman` | Where `podman:generate` writes rendered presets. Build artifact — don't commit it |
| `selinux_volume_mapping` | `PODMAN_SELINUX_VOLUME_MAPPING` | `true` | Keep `Z`/`z`/`U` volume flags; disable on non-SELinux hosts |
| `presets` | `PODMAN_DEFAULT_PRESETS` | see `config/podman.php` | Presets `podman:setup` publishes/generates by default |
| `s3_buckets` | `PODMAN_S3_BUCKETS` | see `config/podman.php` | Buckets `podman:s3-setup` creates — see [S3 Buckets](s3.md) |
| `s3_cors_buckets` | `PODMAN_S3_CORS_BUCKETS` | see `config/podman.php` | Which of `s3_buckets` get the CORS policy |
| `substitutions` | *(none)* | `[]` | Extra `{{placeholder}}` => value pairs merged into every template — see [Custom substitutions](#custom-substitutions) |

`presets`/`s3_buckets`/`s3_cors_buckets` accept a comma-separated string or a plain PHP array.

## Custom presets

A preset is a folder with a `quadlets/` directory (`*.quadlets` files) and a `runtimes/` directory (container build files). `stubs_path` is the lookup root — `stubs_path/{preset}` is used if it exists, otherwise the bundled vendor preset. It's a full replacement, not a file-by-file merge.

- **Tweak an existing service** — publish first (`php artisan podman:publish frankenphp-octane`), then edit `containers/stubs/frankenphp-octane/quadlets/pgsql.quadlets`.
- **Add a service to a preset** — create `containers/stubs/frankenphp-octane/quadlets/my-service.quadlets` (same `# FileName=...` + `---` format), then `php artisan podman:generate frankenphp-octane` and `lpod install frankenphp-octane/my-service.quadlets`.
- **Customize build files** — same rule for `runtimes/` (`Containerfile`, `entrypoint.sh`, php ini, Caddy templates).
- **Add a new preset** — create `containers/stubs/my-preset/quadlets/` and `.../runtimes/` directly.

Placeholders, substituted at publish/generate time:

| Placeholder | Value |
| --- | --- |
| `{{application}}` | Kebab-cased `quadlet_prefix` |
| `{{proxy}}` | Kebab-cased `proxy_prefix` |
| `{{appEnv}}` | `app.env` config value |
| `{{appName}}` | `app.name` config value |
| `{{appUrl}}` | `app.url` config value |
| `{{appHost}}` | Host portion of `app.url` |
| `{{appUid}}`/`{{appGid}}` | Resolved `quadlet_uid`/`quadlet_gid` |
| `{{workingPath}}` | Resolved `working_path` |
| `{{configPath}}` | Resolved `config_path` (defaults to `working_path`) |
| `{{runtimePath}}` | Preset's generated `runtimes/` folder, e.g. `podman/frankenphp-octane/runtimes` |

Defaults to `working_path`, so nothing changes unless set. Handy for keeping a service's live config outside the project, e.g. in your own preset's `proxy.quadlets`:

```ini
Volume={{configPath}}/{{proxy}}:/etc/caddy:rw,z,U
```

## Custom substitutions

Merge your own `{{placeholder}}` => value pairs into every template via `substitutions`. Values are plain PHP — `env(...)` works like anywhere else in the config:

```php
'substitutions' => [
    '{{apiEndpoint}}' => env('API_ENDPOINT'),
],
```

```ini
Environment=API_ENDPOINT={{apiEndpoint}}
```

Can also override a built-in placeholder of the same name (e.g. `{{appHost}}`) — `substitutions` always wins.

## Available services

Each preset (except `devcontainer`/`s3`) bundles `app` plus these siblings. One per category runs at a time — alternatives, not additions:

| Category | Services (default first) |
| --- | --- |
| Database | `pgsql`, `mariadb`, `mysql`, `mongodb` |
| Cache/queue | `valkey`, `redis`, `memcached` |
| Search | `typesense`, `meilisearch` |
| Object storage | `rustfs` |
| Mail catcher | `mailpit` |

`frankenphp-octane` also bundles `horizon`, `reverb`, `schedule`, and `inertia-ssr` — always-on, not alternatives.

## Swapping a service

`pgsql`/`valkey` are wired into `app.quadlets`' `[Unit]` section, not auto-detected:

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

Also update `.env` (`DB_CONNECTION`, `DB_HOST`, etc.) — Podman wires the containers, Laravel still needs to know which one to talk to.

### `[Unit]` directives

| Directive | Meaning | Used for |
| --- | --- | --- |
| `Requires=` | Hard dependency — target fails, this unit stops too | `app` → its database + cache |
| `After=` | Ordering only, no failure propagation | Paired with `Requires=`/`Wants=` |
| `Wants=` | Soft dependency — tries to start the target, doesn't fail if it can't | `app` → `mailpit`/`horizon`/`reverb`/`schedule` |
| `BindsTo=` | Like `Requires=`, but also stops this unit when the target *stops* | `horizon`/`reverb`/`schedule`/`inertia-ssr` → `app` |
| `PartOf=` | Stop/restart of the target propagates here, one-directional | `typesense`/`mailpit` → `app` |

## Increasing a service's memory limit

```bash
php artisan podman:publish frankenphp-octane
```

Add `Memory=` under `[Container]` in `containers/stubs/frankenphp-octane/quadlets/pgsql.quadlets`, then:

```bash
php artisan podman:generate frankenphp-octane
lpod install frankenphp-octane/pgsql.quadlets --replace
```

## Multi-application hosts

Pass `--application=` to `lpod install` (requires Podman 6+) so each app gets its own install subdirectory.

## Links

- [Command Reference](commands.md)
- [Setting up without PHP](host-setup.md)
- [Proxy](proxy.md)
- [S3 Buckets](s3.md)
- [The `lpod` CLI](lpod.md)
- [README](../README.md)
