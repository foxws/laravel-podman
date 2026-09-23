---
section: Advanced
order: 3
---

# Flatpak-packaged editors

VS Code, JetBrains IDEs and Zed installed as Flatpaks run in a sandbox, so they can't reach the host's `podman`. That breaks the [Devcontainer](devcontainer.md) workflow. [`org.freedesktop.Sdk.Extension.podman`](https://github.com/francoism90/org.freedesktop.Sdk.Extension.podman), a community extension by the author of this package, fixes that.

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

Start Podman's rootless socket and give the sandbox access to it:

```bash
systemctl --user enable podman.socket --now
flatpak override --user --filesystem=xdg-run/podman:ro <app-id>
```

## Why not just mount `~/.local/bin`?

Mounting the host's `podman` binary into the sandbox doesn't work:

- The binary needs host libraries (glibc, libselinux, ...) that may differ from the Flatpak runtime, so it may not start.
- Rootless containers also need `newuidmap`/`newgidmap`, `/etc/subuid`/`/etc/subgid`, `crun`, `conmon`, `slirp4netns`/`pasta` and cgroup v2 delegation. The sandbox blocks all of these on purpose.
- Everything in the mounted folder could run from the sandbox. `--filesystem=xdg-run/podman:ro` exposes only the API socket.

The extension runs `podman` outside the sandbox and talks to it through that socket. Docker Desktop works the same way.

## Remote Podman

To use a remote Podman host instead of the local socket, set `PODMAN_FLATPAK_FORCE_REMOTE=1`. This uses `podman-remote`.

## Caveats

The extension is maintained by the community. It's not an official Flatpak or Podman project, and upstream declined to include it. Use it at your own risk. Its repo has the latest setup notes for VS Code, Zed and PhpStorm.

## Links

- [org.freedesktop.Sdk.Extension.podman](https://github.com/francoism90/org.freedesktop.Sdk.Extension.podman)
- [Devcontainer](devcontainer.md)
- [Introduction](index.md)
