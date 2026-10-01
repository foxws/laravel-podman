---
section: Reference
order: 1
---

# Comparison

Laravel Podman is not a desktop app. It's a Laravel package that renders config into Podman Quadlet services, which systemd runs on your host. You get more control, but swapping a part (say, Nginx for Caddy) takes more setup on the host.

| Tool | What it is | Limits | How Laravel Podman differs |
| --- | --- | --- | --- |
| Laravel Sail | Docker Compose setup for Laravel | Development only, Docker per project | Several presets (`development`, `production`, ...), run by Podman and systemd |
| Laravel Herd | Native local dev app from Laravel | macOS and Windows only | Linux only, with Podman and systemd (rootless or system-wide) |
| Lerd ([docs](https://lerd.sh/getting-started/comparison)) | Herd-like dev environment on rootless Podman for many PHP sites and frameworks, with a web UI (Linux, macOS, WSL2) | Local development only; one shared environment owns your containers | One Laravel app from development to production with the same FrankenPHP image, scale to zero, and plain Quadlet files you can read and change, managed with the standalone [`lpod`](https://github.com/foxws/lpod) CLI |

Pick Lerd for many PHP projects on one workstation, with a dashboard. Pick Laravel Podman when the same setup should also run your app on a server.
