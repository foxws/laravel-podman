#!/usr/bin/env bash
set -euo pipefail

# Renumber the "docker" user/group to match the host's PUID/PGID, then drop
# from root down to it. The image itself is always built with UID/GID 1000;
# this is what lets one shared, prebuilt image still write correctly-owned
# files on hosts where the deploying user isn't 1000.
if [ "$(id -u)" = '0' ]; then
    PUID=${PUID:-1000}
    PGID=${PGID:-1000}

    if [ "$(id -g docker)" != "${PGID}" ]; then
        groupmod -o -g "${PGID}" docker
    fi

    if [ "$(id -u docker)" != "${PUID}" ]; then
        usermod -o -u "${PUID}" docker
    fi

    # Non-recursive: /home/docker/.ssh is a read-only host bind mount that
    # already lines up via UserNS=keep-id, so recursing into it would fail.
    chown docker:docker /home/docker
    chown -R docker:docker "${PNPM_HOME}"

    exec gosu docker "$0" "$@"
fi

exec "$@"
