# Customizing

This package is driven by `config/podman.php` (publish it with `php artisan vendor:publish --tag="podman-config"`) and preset template files on disk. This page shows how to customize both.

## Config keys

| Key                       | Env variable                  | Default                                                | Purpose                                                                |
| -------------------------- | ------------------------------ | ------------------------------------------------------- | ------------------------------------------------------------------------ |
| `enabled`                  | `PODMAN_ENABLED`               | `true`                                                   | Master switch for `podman:generate`/`podman:setup`/`podman:publish`/`podman:s3-setup`. Disable in environments where `lpod` over SSH should be the only way to touch services (e.g. production) |
| `quadlet_prefix`           | `PODMAN_QUADLET_PREFIX`        | `APP_NAME` (falls back to `laravel`)                     | Namespaces installed services, e.g. `laravel-pgsql`                      |
| `proxy_prefix`              | `PODMAN_PROXY_PREFIX`          | `proxy`                                                  | Namespace used for the `proxy` service/network                          |
| `stubs_path`               | `PODMAN_STUBS_PATH`            | `containers/stubs`                                       | Where to look for preset folders before falling back to the vendor one, per preset |
| `working_path`             | `PODMAN_WORKING_PATH`          | Laravel's `base_path()`                                  | Real host path baked into `{{workingPath}}`/`{{runtimePath}}`; doesn't affect where files are read/written. Override per run with `--working-path=` on `podman:generate` |
| `config_path`              | `PODMAN_CONFIG_PATH`           | `working_path`                                           | Host path baked into `{{configPath}}`, for a service's config living outside the project (e.g. `{{configPath}}/{{proxy}}`) |
| `quadlet_uid`/`quadlet_gid` | `PODMAN_QUADLET_UID`/`_GID`    | current user's UID/GID                                   | UID/GID baked into generated Quadlet files                              |
| `publish_path`             | `PODMAN_PUBLISH_PATH`          | `podman`                                                 | Where `podman:generate` writes rendered presets. Generated artifact output only: don't commit this path; delete/re-generate as needed. See [Setting up without PHP on the host](host-setup.md) |
| `selinux_volume_mapping`   | `PODMAN_SELINUX_VOLUME_MAPPING`| `true`                                                   | Keep `Z`/`z`/`U` volume flags; disable on non-SELinux hosts               |
| `presets`                  | `PODMAN_DEFAULT_PRESETS`       | see `config/podman.php`                                  | Presets `podman:setup` publishes/generates when none are given           |
| `s3_buckets`                | `PODMAN_S3_BUCKETS`            | see `config/podman.php`                                  | Buckets `podman:s3-setup` creates (see [S3 Buckets](s3.md))              |
| `s3_cors_buckets`           | `PODMAN_S3_CORS_BUCKETS`       | see `config/podman.php`                                  | Which of `s3_buckets` get the CORS policy applied                        |
| `substitutions`             | *(none)*                       | `[]`                                                     | Extra `{{placeholder}}` => value pairs merged into every template — see [Custom substitutions](#custom-substitutions) |

`presets`/`s3_buckets`/`s3_cors_buckets` accept either a comma-separated string (handy for the env variable form) or a plain PHP array in the config file.

## Custom presets

A preset is a folder with a `quadlets/` directory (`*.quadlets` files) and a `runtimes/` directory (container build files), for example the bundled `frankenphp-octane` and `proxy` presets. `stubs_path` is the lookup root for custom presets. For each preset name, the package uses `stubs_path/{preset}` if it exists; otherwise it uses the vendor preset. Once `stubs_path/{preset}` exists, that preset is a full replacement (no file-by-file merge), and other presets are unchanged.

- To tweak one existing service (for example, `pgsql` memory), publish the preset first (`php artisan podman:publish frankenphp-octane`), then edit the file under `containers/stubs/frankenphp-octane/quadlets/`.
- To add a new service to a preset, create `containers/stubs/frankenphp-octane/quadlets/my-service.quadlets` (same `# FileName=...` + `---` block format as existing files), run `php artisan podman:generate frankenphp-octane`, then `lpod install frankenphp-octane/my-service.quadlets`.
- The same rule applies to `runtimes/` when customizing app build files (`Containerfile`, `entrypoint.sh`, php ini files) or proxy Caddy templates.
- To add a brand new preset (for example, another PHP runtime), create `containers/stubs/my-preset/quadlets/` and `containers/stubs/my-preset/runtimes/` directly.

Template files can use the placeholders below, substituted at publish/generate time:

| Placeholder        | Value                                                    |
| ------------------- | ---------------------------------------------------------- |
| `{{application}}`  | The kebab-cased `quadlet_prefix`                            |
| `{{proxy}}`         | The kebab-cased `proxy_prefix`                              |
| `{{appEnv}}`        | `app.env` config value                                     |
| `{{appName}}`       | `app.name` config value                                    |
| `{{appUrl}}`        | `app.url` config value                                     |
| `{{appHost}}`       | Host portion of `app.url`                                  |
| `{{appUid}}`/`{{appGid}}` | Resolved `quadlet_uid`/`quadlet_gid`                   |
| `{{workingPath}}`   | Resolved `working_path`                                     |
| `{{configPath}}`    | Resolved `config_path` (defaults to `working_path`)          |
| `{{runtimePath}}`   | The preset's generated `runtimes/` folder, against `working_path` (e.g. `podman/frankenphp-octane/runtimes`) |

By default `{{configPath}}` equals `working_path`, so nothing changes unless you set it. Set `PODMAN_CONFIG_PATH` to keep a service's live config in a stable directory outside the project — for example, in your own custom preset's `proxy.quadlets`:

```ini
Volume={{configPath}}/{{proxy}}:/etc/caddy:rw,z,U
```

## Custom substitutions

Set the `substitutions` config key to merge your own `{{placeholder}}` => value pairs into every rendered template. Values are plain PHP, so `env(...)` works here just like anywhere else in the config file:

```php
'substitutions' => [
    '{{apiEndpoint}}' => env('API_ENDPOINT'),
],
```

```ini
Environment=API_ENDPOINT={{apiEndpoint}}
```

A substitution here can also override a built-in placeholder of the same name (for example, to compute `{{appHost}}` differently) — whatever you set in `substitutions` wins.

## Available services

Each preset (except `devcontainer`/`s3`) bundles `app` plus these sibling services. Only one per category is meant to run at a time — they're alternatives, not additions:

| Category       | Services (default first)               |
| -------------- | ---------------------------------------- |
| Database       | `pgsql`, `mariadb`, `mysql`, `mongodb`   |
| Cache/queue    | `valkey`, `redis`, `memcached`           |
| Search         | `typesense`, `meilisearch`               |
| Object storage | `rustfs`                                 |
| Mail catcher   | `mailpit`                                |

`frankenphp-octane` additionally bundles `horizon`, `reverb`, `schedule`, and `inertia-ssr` — these run alongside `app`, not as alternatives to it.

## Swapping a service

The default database/cache (`pgsql`/`valkey`) are wired into `app.quadlets`' `[Unit]` section, not auto-detected — swapping to an alternative means editing that wiring too:

```bash
php artisan podman:publish frankenphp-octane   # or development
```

Edit `containers/stubs/frankenphp-octane/quadlets/app.quadlets`, replacing `pgsql`/`valkey` with your chosen services in `Requires=`/`After=`:

```ini
Requires={{application}}-mysql.container {{application}}-redis.container
After={{application}}-mysql.container {{application}}-redis.container
```

Regenerate and reinstall both the new service and `app`:

```bash
php artisan podman:generate frankenphp-octane
lpod install frankenphp-octane/mysql.quadlets --replace
lpod install frankenphp-octane/app.quadlets --replace
```

Also update your app's own `.env` (`DB_CONNECTION`, `DB_HOST`, etc.) — Podman only wires the containers together, Laravel still needs to know which one to talk to.

### `Requires=`, `After=`, `Wants=`, and friends

These `[Unit]` directives control startup order and failure propagation between services. They don't start anything by themselves — that's still `lpod SERVICE up` (or whatever chain that triggers):

| Directive   | Meaning                                                                       | Used for                                        |
| ----------- | ------------------------------------------------------------------------------ | ------------------------------------------------- |
| `Requires=` | Hard dependency — if the target fails, this unit stops too                     | `app` → its database + cache                      |
| `After=`    | Ordering only, no failure propagation — start after the target                 | Paired with `Requires=`/`Wants=` above             |
| `Wants=`    | Soft dependency — tries to start the target too, but doesn't fail if it can't  | `app` → `mailpit`/`horizon`/`reverb`/`schedule`    |
| `BindsTo=`  | Like `Requires=`, but also stops this unit when the target *stops*, not just fails | `horizon`/`reverb`/`schedule`/`inertia-ssr` → `app` |
| `PartOf=`   | Stop/restart of the target propagates here, one-directional                    | `typesense`/`mailpit` → `app`                      |

## Example: increasing a service's memory limit

```bash
php artisan podman:publish frankenphp-octane
```

Edit `containers/stubs/frankenphp-octane/quadlets/pgsql.quadlets` and add a `Memory=` line under `[Container]`, then regenerate and reinstall:

```bash
php artisan podman:generate frankenphp-octane
lpod install frankenphp-octane/pgsql.quadlets --replace
```

## Multi-application hosts

Running more than one application on the same host? Pass `--application=` to `lpod install` (requires Podman 6+) so each app gets its own install subdirectory and names do not clash — see [The `lpod` CLI](lpod.md).

## See also

- [Command Reference](commands.md) — every Artisan command, with flags and examples
- [Setting up without PHP on the host](host-setup.md)
- [Proxy](proxy.md) — the bundled Caddy reverse proxy
- [S3 Buckets](s3.md) — `podman:s3-setup` and CORS
- [The `lpod` CLI](lpod.md) — command reference, shortening the call, and tips & tricks
- [README](../README.md) — Quick Start
