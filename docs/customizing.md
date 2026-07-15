# Customizing

Everything the package does is driven by `config/podman.php` (publish it with `php artisan vendor:publish --tag="laravel-podman-config"`) plus the preset template files it reads from disk. This page covers the ways to customize both.

## Config keys

| Key                       | Env variable                  | Default                                                | Purpose                                                                |
| -------------------------- | ------------------------------ | ------------------------------------------------------- | ------------------------------------------------------------------------ |
| `quadlet_prefix`           | `PODMAN_QUADLET_PREFIX`        | `APP_NAME` (falls back to `laravel`)                     | Namespaces installed services, e.g. `laravel-pgsql`                      |
| `proxy_prefix`              | `PODMAN_PROXY_PREFIX`          | `proxy`                                                  | Namespace used for the `proxy` service/network                          |
| `stubs_path`               | `PODMAN_STUBS_PATH`            | `containers/stubs`                                       | Where to look for preset folders before falling back to the vendor one, per preset |
| `working_path`             | `PODMAN_WORKING_PATH`          | Laravel's `base_path()`                                  | Real host path baked into the `{{workingPath}}`/`{{runtimePath}}` placeholders only — doesn't affect where files are actually read/written, see [Setting up without PHP on the host](host-setup.md) |
| `quadlet_uid`/`quadlet_gid` | `PODMAN_QUADLET_UID`/`_GID`    | current user's UID/GID                                   | UID/GID baked into generated Quadlet files                              |
| `publish_path`             | `PODMAN_PUBLISH_PATH`          | `podman`                                                 | Where `podman:generate` writes every rendered preset — see [Setting up without PHP on the host](host-setup.md) |
| `selinux_volume_mapping`   | `PODMAN_SELINUX_VOLUME_MAPPING`| `true`                                                   | Keep `Z`/`z`/`U` volume flags; disable on non-SELinux hosts               |
| `reload_systemd`           | `PODMAN_RELOAD_SYSTEMD`        | `true`                                                   | Reload systemd after install/remove (used by `lpod`)                     |
| `presets`                  | `PODMAN_DEFAULT_PRESETS`       | see `config/podman.php`                                  | Presets `podman:setup` publishes/generates when none are given           |
| `s3_buckets`                | `PODMAN_S3_BUCKETS`            | see `config/podman.php`                                  | Buckets `podman:s3-setup` creates (see [S3 Buckets](s3.md))              |
| `s3_cors_buckets`           | `PODMAN_S3_CORS_BUCKETS`       | see `config/podman.php`                                  | Which of `s3_buckets` get the CORS policy applied                        |

`presets`/`s3_buckets`/`s3_cors_buckets` accept either a comma-separated string (handy for the env variable form) or a plain PHP array in the config file.

## Custom presets

A preset is a folder containing a `quadlets/` directory of `*.quadlets` files and a `runtimes/` directory of container build files (e.g. the bundled `frankenphp-octane` and `proxy` presets). If `stubs_path` (e.g. `containers/stubs/frankenphp-octane`) exists for a given preset, the package looks there **instead of** the vendor-provided one — it's a full swap per preset, not a per-file overlay, and other presets are unaffected. That means:

- To tweak one existing service (e.g. bump `pgsql`'s memory limit), publish the whole preset first (`php artisan podman:publish frankenphp-octane`), then edit the one file you care about under `containers/stubs/frankenphp-octane/quadlets/`. `podman:generate` will only see files under `containers/stubs/frankenphp-octane` once it exists — nothing from the vendor preset is merged in.
- To add a brand new service to a preset, create `containers/stubs/frankenphp-octane/quadlets/my-service.quadlets` (following the same `# FileName=...` / `---`-separated block format as the bundled ones — see any existing file for the syntax) and generate it with `php artisan podman:generate frankenphp-octane`, then `lpod install frankenphp-octane/my-service.quadlets`.
- The same applies to a preset's `runtimes/` folder for customizing the application image build (`Containerfile`, `entrypoint.sh`, php ini files) or the proxy preset's Caddy templates.
- To add a whole new preset (e.g. an alternative PHP runtime), create `containers/stubs/my-preset/quadlets/`/`containers/stubs/my-preset/runtimes/` directly — there's no vendor fallback to swap out since the preset doesn't exist upstream.

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
| `{{runtimePath}}`   | The preset's generated `runtimes/` folder, against `working_path` (e.g. `podman/frankenphp-octane/runtimes`) |

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

Running more than one application on the same host? Pass `--application=` to `lpod install` (requires Podman 6+) to install each app's services into their own subdirectory, avoiding name clashes — see [The `lpod` CLI](lpod.md) for details.

## See also

- [Command Reference](commands.md) — every Artisan command, with flags and examples
- [Setting up without PHP on the host](host-setup.md)
- [Proxy](proxy.md) — the bundled Caddy reverse proxy
- [S3 Buckets](s3.md) — `podman:s3-setup` and CORS
- [The `lpod` CLI](lpod.md) — command reference, shortening the call, and tips & tricks
- [README](../README.md) — Quick Start
