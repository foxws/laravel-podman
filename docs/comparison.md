---
section: Reference
order: 1
---

# Comparison

Laravel Podman is not a desktop app. It's a Laravel package that renders config into Podman Quadlet services, which systemd runs on your host. You get more control, but swapping a part (say, Nginx for Caddy) takes more setup on the host.

| Tool | What it is | Limits | How Laravel Podman differs |
| --- | --- | --- | --- |
| Laravel Sail | Docker Compose setup for Laravel | Development only, Docker per project | Several presets (`development`, `frankenphp-octane`, ...), run by Podman and systemd |
| Laravel Herd | Native local dev app from Laravel | macOS and Windows only | Linux only, with Podman and systemd (rootless or system-wide) |
| Lerd ([docs](https://lerd.sh/getting-started/comparison)) | Open-source local dev tool on rootless Podman (Linux/macOS) | Different scope and design | Renders templates into Quadlet units, includes a Caddy `proxy` preset, and works with the standalone [`lpod`](https://github.com/foxws/lpod) CLI |
