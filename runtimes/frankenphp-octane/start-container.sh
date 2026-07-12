#!/usr/bin/env bash
set -euo pipefail

APP_ENV=${APP_ENV:-'production'}
APP_WAIT_TIMEOUT=${APP_WAIT_TIMEOUT:-60}

log() {
    local type="$1"
    local message="$2"
    echo "[$type] $message"
}

# Read a single value out of the runtime .env file. This is a lightweight
# lookup for the wait-for-service checks below, not a full dotenv parser;
# Laravel itself still parses /app/.env properly once the app boots.
env_get() {
    local key="$1" line value

    line=$(grep -m1 -E "^${key}=" /app/.env 2>/dev/null || true)

    if [ -z "$line" ]; then
        return
    fi

    value="${line#*=}"
    value="${value%\"}"
    value="${value#\"}"
    value="${value%\'}"
    value="${value#\'}"

    echo "$value"
}

# The default port used by a driver when DB_PORT/REDIS_PORT isn't set...
default_port_for_driver() {
    case "$1" in
        mysql|mariadb) echo 3306 ;;
        pgsql) echo 5432 ;;
        sqlsrv) echo 1433 ;;
        redis) echo 6379 ;;
        *) return ;;
    esac
}

# Block until a TCP host:port accepts connections, or fail after
# APP_WAIT_TIMEOUT seconds. Setting APP_WAIT_TIMEOUT=0 disables waiting.
wait_for_tcp() {
    local label="$1" host="$2" port="$3" waited=0

    if [ -z "$host" ] || [ -z "$port" ] || [ "${APP_WAIT_TIMEOUT}" -le 0 ]; then
        return 0
    fi

    log "INFO" "Waiting for ${label} at ${host}:${port}..."

    until (exec 3<>"/dev/tcp/${host}/${port}") 2>/dev/null; do
        waited=$((waited + 1))

        if [ "$waited" -ge "${APP_WAIT_TIMEOUT}" ]; then
            log "ERROR" "Timed out after ${APP_WAIT_TIMEOUT}s waiting for ${label} at ${host}:${port}."
            exit 1
        fi

        sleep 1
    done

    exec 3>&- 2>/dev/null || true

    log "INFO" "${label} is accepting connections."
}

# Wait for whichever database driver is configured in the runtime .env,
# so this stays agnostic to pgsql, mysql, mariadb, sqlsrv, etc. Services
# that don't need a network round trip (sqlite, or nothing configured)
# are skipped.
wait_for_database() {
    local driver host port
    driver="$(env_get DB_CONNECTION)"

    if [ -z "$driver" ] || [ "$driver" = "sqlite" ]; then
        return 0
    fi

    host="$(env_get DB_HOST)"
    port="$(env_get DB_PORT)"
    port="${port:-$(default_port_for_driver "$driver")}"

    wait_for_tcp "database (${driver})" "$host" "$port"
}

# Wait for the configured cache store, when it's a redis-compatible
# backend (Valkey speaks the same protocol).
wait_for_cache() {
    local host port
    if [ "$(env_get CACHE_STORE)" != "redis" ]; then
        return 0
    fi

    host="$(env_get REDIS_HOST)"
    port="$(env_get REDIS_PORT)"
    port="${port:-$(default_port_for_driver redis)}"

    wait_for_tcp "cache (redis)" "$host" "$port"
}

# Set up SQLite database
if [ ! -f "database/database.sqlite" ]; then
    log "INFO" "Creating SQLite database..."
    touch database/database.sqlite
fi

# Set up environment configuration
if [ ! -f "/config/app.env" ]; then
    log "ERROR" "Missing /config/app.env. Provide a Laravel env file mounted at: /config/app.env"
    exit 1
fi

log "INFO" "Loading runtime environment configuration from /config/app.env..."
cp /config/app.env /app/.env

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

# Wait for the configured database and cache backends to be reachable...
wait_for_database
wait_for_cache

# Clear any stale caches
log "INFO" "Clearing stale caches..."
${FRANKEN_CLI} optimize:clear

# Create storage symlinks
log "INFO" "Creating storage symlinks..."
${FRANKEN_CLI} storage:link

# Optimize for production
if [ "${APP_ENV}" = "production" ]; then
    # Ensure all caches are warmed up
    log "INFO" "Optimizing application..."
    ${FRANKEN_CLI} optimize
fi
