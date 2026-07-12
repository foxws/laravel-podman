#!/usr/bin/env bash
set -euo pipefail

APP_COMMAND=${APP_COMMAND:-'/usr/bin/bash'}

log() {
    local type="$1"
    local message="$2"
    echo "[$type] $message"
}

# Prepare the application (env, database/cache readiness, caches, storage)...
/usr/local/bin/start-container.sh

log "INFO" "Starting command..."
exec ${APP_COMMAND}
