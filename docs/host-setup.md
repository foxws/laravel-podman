# Setting up without PHP on the host

`podman:generate` and `podman:setup` only render files. They replace the `{{...}}` placeholders described in [Customizing](customizing.md) and write output to `publish_path` (`podman/{preset}/` by default). They never call the `podman` binary, so they can run anywhere PHP runs, even where Podman is not installed. Installing rendered services and setting secrets is a separate host-side step done with `lpod`/`lpod-secrets` (see [The `lpod` CLI](lpod.md)). This output is generated artifact data, so do not commit it; after install, you can delete and regenerate it at any time.

This split makes it possible to run the PHP half of setup somewhere PHP is convenient (a container, CI, a machine without PHP installed) and the Podman half on the host where Podman actually runs:

```bash
# Renders the default presets' .quadlets/runtime files into podman/{preset}/,
# without needing podman inside the container:
podman run --rm --userns=keep-id -u "$(id -u):$(id -g)" \
    -e PODMAN_WORKING_PATH="$PWD" \
    -v "$PWD":/var/www/html:Z -w /var/www/html docker.io/dunglas/frankenphp:latest \
    php artisan podman:setup

# On the host, install each rendered service:
vendor/bin/lpod install development/app.quadlets --replace
vendor/bin/lpod install development/pgsql.quadlets --replace
# ...and so on for every service you need.

# Then, also on the host, set each service's secrets:
vendor/bin/lpod secrets app
vendor/bin/lpod secrets pgsql
```

> **Note:** `-e PODMAN_WORKING_PATH="$PWD"` only changes host paths *written into* generated `.quadlets` files (`SetWorkingDirectory=`, `File=`, `IgnoreFile=`). It does not change where rendering reads/writes files. Rendering still happens from `/var/www/html` in the container and writes to the mounted project `podman/` folder. This flag is needed so generated paths point to real host paths, not `/var/www/html`. When rendering a single preset directly with `podman:generate`, pass `--working-path=` instead of setting the env variable (`php artisan podman:generate development --working-path="$PWD"`).

> **Note:** `--userns=keep-id -u "$(id -u):$(id -g)"` runs the container as your host user, so generated files are owned by you (not root). On SELinux hosts, keep `:Z` on the bind mount, or Podman may fail to read `artisan` (`Could not open input file: artisan`).

> **Note:** Once `lpod` is available on the host, `lpod setup` is a shortcut for the `podman run ...` command above. It wraps `vendor/bin/lpod-setup`, forwards arguments to `podman:setup` (for example `lpod setup --preset=frankenphp-octane`), and prints ready-to-run `podman quadlet install ...` commands for each rendered file. Pass `--install` to also run those commands immediately instead — useful since `lpod-setup` runs directly on the host anyway. Pass `--secrets` to additionally prompt for and set each installed service's secrets right away (implies `--install`). `lpod secrets` is likewise a shortcut for `vendor/bin/lpod-secrets`.

> **Note:** `lpod`/`lpod-setup`/`lpod-secrets` are plain bash scripts with no dependency on Composer's autoloader. You don't need this Composer package installed on the host at all to use them — copy `bin/lpod`, `bin/lpod-setup`, and `bin/lpod-secrets` there yourself (onto `PATH`, or tracked in your dotfiles) and run them standalone.

See [The `lpod` CLI](lpod.md) for `setup`/`install`/`secrets`/`remove`/`list`/`print`/`uninstall` and the rest of the workflow.
