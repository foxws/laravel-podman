# Comparison

Not a full desktop app — a Laravel package that renders presets into host-managed Podman Quadlet/systemd services. Swap bundled parts (Caddy for Nginx) for more host-level setup, in exchange for more control.

| Tool | What it is | Main limits | How Laravel Podman differs |
| --- | --- | --- | --- |
| Laravel Sail | Docker Compose setup for Laravel development. | Development-only, per-project Docker setup. | Multiple presets/runtimes (`development`, `frankenphp-octane`, etc.) with Podman Quadlet + systemd-managed services. |
| Laravel Herd | Native local dev app from Laravel. | macOS and Windows only. | Linux with Podman + systemd only (rootless or system-wide). |
| Lerd ([docs](https://lerd.sh/getting-started/comparison)) | Open-source local dev tool, rootless Podman (Linux/macOS). | Different scope and architecture. | Renders preset templates into Quadlet units, bundles a Caddy `proxy` preset and a `lpod-setup` binary, pairs with the standalone [`lpod`](https://github.com/foxws/lpod) host tool. |
