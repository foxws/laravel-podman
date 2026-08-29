# `lpod` CLI

`lpod` lives in its own repo: **[foxws/lpod](https://github.com/foxws/lpod)**. A single, dependency-free bash script — no PHP, Composer, or this package required. See that repo for installation, the full command reference, and tips & tricks.

```bash
curl -fsSL -o ~/.local/bin/lpod https://github.com/foxws/lpod/releases/latest/download/lpod
chmod +x ~/.local/bin/lpod
```

## Secrets moved into `lpod`

No more separate `lpod-secrets` script — merged in as a per-service command:

```bash
lpod my-app secrets
```

(Previously `lpod secrets my-app`, or `vendor/bin/lpod-secrets my-app`.)

## `lpod-setup`

`lpod-setup` renders presets inside a disposable container, for hosts with Podman but no PHP — see [Setting up without PHP](host-setup.md). It now ships alongside `lpod` in [foxws/lpod](https://github.com/foxws/lpod) rather than with this package; `lpod setup` is a shortcut for it.
