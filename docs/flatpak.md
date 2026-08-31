# Flatpak-packaged editors

VSCode, JetBrains IDEs, and Zed installed via Flatpak run sandboxed — they can't reach the host's `podman` the way the [Devcontainer](devcontainer.md) workflow (or any Podman-based dev setup) expects. [`org.freedesktop.Sdk.Extension.podman`](https://github.com/francoism90/org.freedesktop.Sdk.Extension.podman) is a community SDK extension (same author as this package) that bridges that gap.

## Installing the extension

```bash
flatpak remote-add --if-not-exists francoism90-podman \
    https://francoism90.github.io/org.freedesktop.Sdk.Extension.podman/index.flatpakrepo
flatpak install francoism90-podman org.freedesktop.Sdk.Extension.podman
```

## Enabling it for an app

```bash
flatpak override --user --env=FLATPAK_ENABLE_SDK_EXT=podman <app-id>
```

| Editor   | `<app-id>`               |
| -------- | ------------------------ |
| VSCode   | `com.visualstudio.code`  |
| PhpStorm | `com.jetbrains.PhpStorm` |
| Zed      | `dev.zed.Zed`            |

## Socket access

Podman's rootless socket needs to be running and exposed to the sandbox:

```bash
systemctl --user enable podman.socket --now
flatpak override --user --filesystem=xdg-run/podman:ro <app-id>
```

## Why not just mount `~/.local/bin`?

Bind-mounting a directory with the host's `podman` binary into the sandbox doesn't actually work, for reasons beyond Flatpak convention:

- The binary is dynamically linked against host libraries (glibc, libselinux, ...) that may not match what's inside the Flatpak runtime — it can fail to exec or crash outright.
- Even if it ran, creating rootless containers needs `newuidmap`/`newgidmap`, `/etc/subuid`/`/etc/subgid` entries, `crun`, `conmon`, `slirp4netns`/`pasta`, and cgroup v2 delegation — none of which a plain bind-mounted directory grants inside bubblewrap's sandbox, which is deliberately isolating exactly those things.
- It's also an unbounded escape hatch: anything in that directory becomes executable with sandbox-adjacent privileges, versus `--filesystem=xdg-run/podman:ro` which exposes only the API socket.

The extension instead runs `podman` fully outside the sandbox as a service and talks to it over that socket — the same model Docker Desktop uses, not a workaround.

## Remote Podman

Talking to a remote Podman host instead of a local rootless socket? Set `PODMAN_FLATPAK_FORCE_REMOTE=1` to route through `podman-remote` instead.

## Caveats

Community-maintained, not an official Flatpak or Podman project — upstream rejected including it directly. Use it at your own risk, and check its repo for current VS Code/Zed/PhpStorm-specific configuration.

## Links

- [org.freedesktop.Sdk.Extension.podman](https://github.com/francoism90/org.freedesktop.Sdk.Extension.podman)
- [Devcontainer](devcontainer.md)
- [Introduction](index.md)
