# Customizing

Everything the package does is driven by `config/podman.php` (publish it with `php artisan vendor:publish --tag="laravel-podman-config"`) plus the `*.quadlets`/runtime template files it reads from disk. This page covers the ways to customize both.

## Config keys

| Key                       | Env variable                  | Default                                                | Purpose                                                                |
| -------------------------- | ------------------------------ | ------------------------------------------------------- | ------------------------------------------------------------------------ |
| `quadlet_prefix`           | `PODMAN_QUADLET_PREFIX`        | `APP_NAME` (falls back to `laravel`)                     | Namespaces installed services, e.g. `laravel-pgsql`                      |
| `proxy_prefix`              | `PODMAN_PROXY_PREFIX`          | `proxy`                                                  | Namespace used for the `proxy` service/network                          |
| `quadlets_path`            | `PODMAN_QUADLETS_PATH`         | `containers/quadlets`                                    | Where to look for `*.quadlets` templates before falling back to vendor   |
| `runtimes_path`            | `PODMAN_RUNTIMES_PATH`         | `containers/runtimes`                                    | Where to look for runtime template folders before falling back to vendor |
| `quadlet_uid`/`quadlet_gid` | `PODMAN_QUADLET_UID`/`_GID`    | current user's UID/GID                                   | UID/GID baked into generated Quadlet files                              |
| `runtime_path`             | `PODMAN_RUNTIME_PATH`          | `runtimes`                                               | Where `podman:publish` writes published runtime files                   |
| `config_path`              | `PODMAN_CONFIG_PATH`           | `runtimes`                                               | Where service configs (e.g. the proxy's Caddy config) are mounted from   |
| `selinux_volume_mapping`   | `PODMAN_SELINUX_VOLUME_MAPPING`| `true`                                                   | Keep `Z`/`z`/`U` volume flags; disable on non-SELinux hosts               |
| `reload_systemd`           | `PODMAN_RELOAD_SYSTEMD`        | `true`                                                   | Reload systemd after install/remove                                      |
| `services`                  | `PODMAN_DEFAULT_SERVICES`      | see `config/podman.php`                                  | Services `podman:setup` installs when none are given                     |
| `runtimes`                  | `PODMAN_DEFAULT_RUNTIMES`      | see `config/podman.php`                                  | Runtimes `podman:setup` publishes when none are given                    |
| `s3_buckets`                | `PODMAN_S3_BUCKETS`            | see `config/podman.php`                                  | Buckets `podman:s3-setup` creates (see [S3 Buckets](s3.md))              |
| `s3_cors_buckets`           | `PODMAN_S3_CORS_BUCKETS`       | see `config/podman.php`                                  | Which of `s3_buckets` get the CORS policy applied                        |

`services`/`runtimes`/`s3_buckets`/`s3_cors_buckets` accept either a comma-separated string (handy for the env variable form) or a plain PHP array in the config file.

## Custom templates

If `quadlets_path`/`runtimes_path` point at a directory that exists, the package looks there **instead of** its own bundled `quadlets/`/`runtimes/` — it's a full swap, not a per-file overlay. That means:

- To tweak one existing service (e.g. bump `pgsql`'s memory limit), copy the *entire* bundled `quadlets/` directory into `containers/quadlets` first, then edit the one file you care about. `podman:list`/`podman:install` etc. will only see files under `containers/quadlets` once it exists — nothing from the vendor directory is merged in.
- To add a brand new service, create `containers/quadlets/my-service.quadlets` (following the same `# FileName=...` / `---`-separated block format as the bundled ones — see any existing file for the syntax) and install it with `php artisan podman:install my-service`. Add it to the `services` config to include it in `podman:setup`.
- The same applies to `runtimes_path`/`containers/runtimes` for customizing the application image build (`Containerfile`, `entrypoint.sh`, php ini files) or the proxy runtime's Caddy templates.

Template files can use the placeholders below, substituted at install/publish time:

| Placeholder        | Value                                                    |
| ------------------- | ---------------------------------------------------------- |
| `{{application}}`  | The kebab-cased `quadlet_prefix`                            |
| `{{proxy}}`         | The kebab-cased `proxy_prefix`                              |
| `{{app-env}}`       | `app.env` config value                                     |
| `{{app-name}}`      | `app.name` config value                                    |
| `{{app-url}}`       | `app.url` config value                                     |
| `{{app-host}}`      | Host portion of `app.url`                                  |
| `{{app-uid}}`/`{{app-gid}}` | Resolved `quadlet_uid`/`quadlet_gid`                 |
| `{{base-path}}`     | `base_path()`                                               |
| `{{config-path}}`   | Resolved `config_path`                                      |
| `{{runtime-path}}`  | Resolved `runtime_path`                                      |

## Example: increasing a service's memory limit

```bash
cp -r vendor/foxws/laravel-podman/quadlets containers/quadlets
```

Edit `containers/quadlets/pgsql.quadlets` and add a `Memory=` line under `[Container]`, then reinstall:

```bash
php artisan podman:install pgsql --replace
```

## Multi-application hosts

Running more than one application on the same host? Pass `--application=` to `podman:install`/`podman:setup` (requires Podman 6+) to install each app's services into their own subdirectory, avoiding name clashes — see the [README](../README.md#usage) for details.

## See also

- [Proxy](proxy.md) — the bundled Caddy reverse proxy
- [S3 Buckets](s3.md) — `podman:s3-setup` and CORS
- [lpod tips & tricks](lpod.md) — the `lpod` CLI
- [README](../README.md) — Quick Start and full command reference
