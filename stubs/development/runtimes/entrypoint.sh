#!/usr/bin/env bash
set -euo pipefail

# Unlike the other presets, this image is always built locally with the
# host's actual UID/GID baked in at build time (see the app quadlet's
# [Build] Environment=UID/GID), and the Containerfile ends as USER docker,
# not root -- so there's no renumbering to do here.

APP_COMMAND=${APP_COMMAND:-'/usr/bin/bash'}

log() {
    local type="$1"
    local message="$2"
    echo "[$type] $message"
}

# Set up SQLite database
if [ ! -f "/app/database/database.sqlite" ]; then
    log "INFO" "Creating SQLite database..."
    touch /app/database/database.sqlite
fi

# Set up environment configuration
if [ ! -f "/app/.env" ]; then
    log "ERROR" "Missing /app/.env. Provide a Laravel env file mounted at: /app/.env"
    exit 1
fi

log "INFO" "Loading runtime environment configuration from /app/.env..."

# Ensure APP_KEY is provided in runtime configuration
if ! grep -q '^APP_KEY=.' /app/.env && [ -z "${APP_KEY:-}" ]; then
    GENERATED_KEY="$(${FRANKEN_CLI} key:generate --show || true)"

    if [ -n "${GENERATED_KEY}" ]; then
        log "ERROR" "APP_KEY is missing from runtime configuration. Paste this line into app.env: APP_KEY=${GENERATED_KEY}"
    else
        log "ERROR" "APP_KEY is missing from runtime configuration and generation failed."
    fi

    exit 1
fi

# Clear any stale caches
log "INFO" "Clearing stale caches..."
${FRANKEN_CLI} optimize:clear

# Run the provided command
log "INFO" "Starting command..."
exec ${APP_COMMAND}
