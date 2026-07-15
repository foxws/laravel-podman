# Setting up without PHP on the host

`podman:generate`/`podman:setup` only ever render — they substitute the `{{...}}` placeholders described in [Customizing](customizing.md) and write the result to the `publish_path` config key (`podman` by default, one subfolder per preset: `podman/{preset}/`). They never touch the `podman` binary, so they can run anywhere PHP is available, even without Podman installed at all. Actually installing the rendered files — which does need `podman` — is a separate step, handled by [`lpod`](lpod.md) on the host.

This split makes it possible to run the PHP half of setup somewhere PHP is convenient (a container, CI, a machine without PHP installed) and the Podman half on the host where Podman actually runs:

```bash
# Renders the default presets' .quadlets/runtime files into podman/{preset}/,
# without needing podman inside the container:
podman run --rm --userns=keep-id -u "$(id -u):$(id -g)" \
    -e PODMAN_WORKING_PATH="$PWD" \
    -v "$PWD":/var/www/html:Z -w /var/www/html docker.io/dunglas/frankenphp:latest \
    php artisan podman:setup

# On the host, install each rendered service:
vendor/bin/lpod install frankenphp-octane/app.quadlets --replace
vendor/bin/lpod install frankenphp-octane/pgsql.quadlets --replace
# ...and so on for every service you need.
```

> **Note:** `docker.io/dunglas/frankenphp:latest` is the same base image the bundled `frankenphp-octane` preset builds `FROM` (see `stubs/frankenphp-octane/runtimes/Containerfile`) — reusing it here means Podman only has to pull one image on the host instead of two. It ships PHP plus the extensions FrankenPHP itself needs; if your app's `composer.json` requires more, either swap in a `php` image with the ones you need (e.g. `php:8.5-cli`, see the [image's documentation](https://hub.docker.com/_/php)), or install them the same way `stubs/frankenphp-octane/runtimes/Containerfile` does.

> **Note:** `-e PODMAN_WORKING_PATH="$PWD"` only changes what gets *written into* the rendered `.quadlets` file — e.g. `app`'s `SetWorkingDirectory=`, and the `File=`/`IgnoreFile=` paths pointing at the generated `Containerfile` — not where the render step itself reads or writes. Rendering still happens against the container's own view of the project (`/var/www/html`, and `podman/` under it, which is exactly `$PWD`'s `podman/` on the host thanks to the bind mount). But those baked-in paths get read by `podman quadlet install`/systemd *outside* any container, on the real host filesystem, so they need to be `$PWD` as seen from the host — not `/var/www/html`. Omitting this flag would render `/var/www/html` into those paths instead, which don't exist once you're back on the host.

> **Note:** `--userns=keep-id -u "$(id -u):$(id -g)"` runs the container as your host user instead of root, so the rendered files (and anything else written under the bind mount) come out owned by you rather than root. Drop it if you're running rootful Podman or don't mind fixing ownership afterwards. On SELinux hosts, the bind mount also needs the `:Z` label (as above) or the container won't be permitted to read `artisan` at all — Podman will fail with `Could not open input file: artisan` without it.

See [The `lpod` CLI](lpod.md) for `install`/`secrets`/`remove`/`list`/`print`/`uninstall` and everything else `lpod` does once services are running.
