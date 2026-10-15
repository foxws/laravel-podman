---
section: Configuration
order: 6
---

# Installer

The `installer` preset is the starting point for a one-time installer for your app, built on [podman-installer](https://github.com/foxws/podman-installer). People run it once on their server: it checks the host, then runs your app's setup steps in a browser, such as the migrations and creating the first admin, and removes itself when stopped.

## Render and customize it

```bash
php artisan podman:publish installer
```

Edit the steps in `containers/stubs/installer/runtimes/installer/`:

- `manifest.yml` lists the steps. It runs the migrations in the app container, `systemd-{{application}}`, and has a commented-out step for creating the first admin with your app's own command. Laravel has no such command itself.
- Each step has a Markdown file, which the installer shows above it.

See [the manifest](https://github.com/foxws/podman-installer#the-manifest) for every option. Placeholders such as `{{appName}}` and `{{application}}` work in these files too.

## Build and run it

```bash
php artisan podman:generate installer
podman build -t my-app-installer podman/installer/runtimes
```

Push the image to a registry, then start it on the server with [lpod](lpod.md):

```bash
lpod installer ghcr.io/me/my-app-installer
```

`lpod installer` passes in the host checks of `lpod doctor` and the Podman socket the steps run through, and listens on `127.0.0.1:8085` only. Open the link it prints.
