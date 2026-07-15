# Customizing

This package is driven by `config/podman.php` (publish it with `php artisan vendor:publish --tag="podman-config"`) and preset template files on disk. This page shows how to customize both.

## Config keys

| Key                       | Env variable                  | Default                                                | Purpose                                                                |
| -------------------------- | ------------------------------ | ------------------------------------------------------- | ------------------------------------------------------------------------ |
| `quadlet_prefix`           | `PODMAN_QUADLET_PREFIX`        | `APP_NAME` (falls back to `laravel`)                     | Namespaces installed services, e.g. `laravel-pgsql`                      |
| `proxy_prefix`              | `PODMAN_PROXY_PREFIX`          | `proxy`                                                  | Namespace used for the `proxy` service/network                          |
| `stubs_path`               | `PODMAN_STUBS_PATH`            | `containers/stubs`                                       | Where to look for preset folders before falling back to the vendor one, per preset |
| `working_path`             | `PODMAN_WORKING_PATH`          | Laravel's `base_path()`                                  | Real host path baked into the `{{workingPath}}`/`{{runtimePath}}` placeholders only — doesn't affect where files are actually read/written, see [Setting up without PHP on the host](host-setup.md) |
| `quadlet_uid`/`quadlet_gid` | `PODMAN_QUADLET_UID`/`_GID`    | current user's UID/GID                                   | UID/GID baked into generated Quadlet files                              |
| `publish_path`             | `PODMAN_PUBLISH_PATH`          | `podman`                                                 | Where `podman:generate` writes rendered presets. Generated artifact output only: don't commit this path; delete/re-generate as needed. See [Setting up without PHP on the host](host-setup.md) |
| `selinux_volume_mapping`   | `PODMAN_SELINUX_VOLUME_MAPPING`| `true`                                                   | Keep `Z`/`z`/`U` volume flags; disable on non-SELinux hosts               |
| `reload_systemd`           | `PODMAN_RELOAD_SYSTEMD`        | `true`                                                   | Reload systemd after install/remove (used by `lpod`)                     |
| `presets`                  | `PODMAN_DEFAULT_PRESETS`       | see `config/podman.php`                                  | Presets `podman:setup` publishes/generates when none are given           |
| `s3_buckets`                | `PODMAN_S3_BUCKETS`            | see `config/podman.php`                                  | Buckets `podman:s3-setup` creates (see [S3 Buckets](s3.md))              |
| `s3_cors_buckets`           | `PODMAN_S3_CORS_BUCKETS`       | see `config/podman.php`                                  | Which of `s3_buckets` get the CORS policy applied                        |

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

Running more than one application on the same host? Pass `--application=` to `lpod install` (requires Podman 6+) so each app gets its own install subdirectory and names do not clash — see [The `lpod` CLI](lpod.md).

## See also

- [Command Reference](commands.md) — every Artisan command, with flags and examples
- [Setting up without PHP on the host](host-setup.md)
- [Proxy](proxy.md) — the bundled Caddy reverse proxy
- [S3 Buckets](s3.md) — `podman:s3-setup` and CORS
- [The `lpod` CLI](lpod.md) — command reference, shortening the call, and tips & tricks
- [README](../README.md) — Quick Start
