# Comparison

This is a quick high-level comparison of Laravel Podman with common Laravel local tooling.

| Tool | What it is | Main limits | How Laravel Podman differs |
| --- | --- | --- | --- |
| Laravel Sail | Docker Compose setup for Laravel development. | Mostly a development workflow with per-project Docker setup. | Laravel Podman uses preset-based generation and supports multiple presets/runtimes (for example `production`, `development`, `devcontainer`, `proxy`) with Podman Quadlet + systemd-managed services. |
| Laravel Herd | Native local dev app from Laravel. | macOS and Windows only. | Laravel Podman targets Linux with Podman + systemd only (rootless or system-wide). |
| Lerd ([docs](https://lerd.sh/getting-started/comparison)) | Open-source local dev tool with rootless Podman (Linux/macOS). | Different scope and architecture from this package. | Laravel Podman is a Laravel package that renders preset templates into Podman Quadlet units, and includes a Caddy-based `proxy` preset plus `lpod`/`lpod-setup`/`lpod-secrets` host tooling. |

