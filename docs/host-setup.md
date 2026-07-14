# Setting up without PHP on the host

Before installing a service, `podman:setup`/`podman:install` always render its `.quadlets` file (with the `{{...}}` substitutions described in [Customizing](customizing.md)) and write it to the `publish_path` config key (`storage/app/podman` by default) — install or not, that rendered file is left behind for inspection. Actually installing it needs the `podman` binary; pass `--no-install` to skip that step yourself, or it's skipped automatically when `podman` can't be found (for example because you're running the command inside a disposable [`php`](https://hub.docker.com/_/php) container that doesn't have it), printing the `podman quadlet install` command to run on the host afterwards. Secrets are skipped in both cases, since setting them also requires `podman` — run `podman:secret` for the service once `podman` is available.

This makes it possible to run the PHP half of setup somewhere PHP is convenient (a container, CI, a machine without PHP installed) and the Podman half on the host where Podman actually runs:

```bash
# Renders every default service's .quadlets file into storage/app/podman,
# without needing podman inside the container:
podman run --rm --userns=keep-id -u "$(id -u):$(id -g)" \
    -v "$PWD":/var/www/html:Z -w /var/www/html php:8.5-cli \
    php artisan podman:setup --no-install

# On the host, install the prepared files (the exact command, including any
# --application/--replace flags, is also printed by the command above):
podman quadlet install storage/app/podman/*.quadlets --replace
```

> **Note:** The official `php` image only ships PHP itself. Depending on what your app's `composer.json` requires, you may need a variant with extra extensions installed (e.g. `docker-php-ext-install pdo mbstring`) — see the [image's documentation](https://hub.docker.com/_/php) for details.

> **Note:** `--userns=keep-id -u "$(id -u):$(id -g)"` runs the container as your host user instead of root, so the rendered `.quadlets` files (and anything else written under the bind mount) come out owned by you rather than root. Drop it if you're running rootful Podman or don't mind fixing ownership afterwards. On SELinux hosts, the bind mount also needs the `:Z` label (as above) or the container won't be permitted to read `artisan` at all — Podman will fail with `Could not open input file: artisan` without it.

## The `setup-podman` shortcut

`vendor/bin/setup-podman` wraps both commands above into one — it runs the container (with the same `--userns=keep-id`/`-u`/`:Z` flags) and then installs the rendered units with the host's own `podman`:

```bash
vendor/bin/setup-podman

# Options after "setup-podman" are forwarded to "podman:setup"
vendor/bin/setup-podman --runtime=frankenphp-octane --service=app --application=my-app

# Override the PHP image or publish path
SETUP_PODMAN_PHP_IMAGE=php:8.5-cli-alpine vendor/bin/setup-podman
```
