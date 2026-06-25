#!/usr/bin/env bash
# sync-from-prod.sh — copy the production PostgreSQL database to dev.
# Run this ON the dev machine.
#
# Usage:
#   ./scripts/sync-from-prod.sh                        # use defaults below
#   PROD_SSH=deploy@1.2.3.4 ./scripts/sync-from-prod.sh
#
# Required for remote prod: set PROD_SSH (and optionally PROD_SSH_KEY).
# If prod and dev are on the same machine, leave PROD_SSH empty.

set -euo pipefail

# ── Configuration ─────────────────────────────────────────────────────────────

# Remote prod host — leave empty if prod containers are on this machine
PROD_SSH="${PROD_SSH:-}"            # e.g. "deploy@192.168.1.100"
PROD_SSH_KEY="${PROD_SSH_KEY:-}"    # optional SSH key, e.g. "~/.ssh/forgedesk_prod"

# Container names (must match your compose files)
PROD_PG_CONTAINER="${PROD_PG_CONTAINER:-forgedesk_postgres}"    # docker-compose.prod.yml
DEV_PG_CONTAINER="${DEV_PG_CONTAINER:-forgedesk-prod_postgres}" # docker-compose.yml
DEV_APP_CONTAINER="${DEV_APP_CONTAINER:-forgedesk-prod_app}"
DEV_QUEUE_CONTAINER="${DEV_QUEUE_CONTAINER:-forgedesk-prod_queue}"

# Database
DB_NAME="${DB_NAME:-forgedesk}"
DB_USER="${DB_USER:-forgedesk}"

# ──────────────────────────────────────────────────────────────────────────────

log() { echo ">>> [$(date +%H:%M:%S)] $*"; }

prod_cmd() {
    # Run a command on the prod host (remote or local)
    if [[ -n "$PROD_SSH" ]]; then
        local opts="-o StrictHostKeyChecking=accept-new -o BatchMode=yes -o ConnectTimeout=10"
        [[ -n "$PROD_SSH_KEY" ]] && opts="$opts -i $PROD_SSH_KEY"
        # shellcheck disable=SC2086
        ssh $opts "$PROD_SSH" "$@"
    else
        bash -c "$*"
    fi
}

# ── Pre-flight checks ─────────────────────────────────────────────────────────

log "Starting prod → dev database sync"

if [[ -n "$PROD_SSH" ]]; then
    log "Prod host : $PROD_SSH"
    log "Testing SSH connection..."
    prod_cmd "echo 'SSH OK'" || { echo "ERROR: Cannot reach $PROD_SSH"; exit 1; }
else
    log "Prod is local (same machine as dev)"
fi

log "Checking dev containers are running..."
docker inspect "$DEV_PG_CONTAINER" --format '{{.State.Status}}' | grep -q running \
    || { echo "ERROR: Dev postgres container '$DEV_PG_CONTAINER' is not running"; exit 1; }

# ── Stream dump from prod → restore to dev ────────────────────────────────────

log "Pausing dev app containers (releases DB connections)..."
docker stop "$DEV_APP_CONTAINER" "$DEV_QUEUE_CONTAINER" 2>/dev/null || true

log "Resetting dev database..."
docker exec "$DEV_PG_CONTAINER" psql -U "$DB_USER" -d postgres -q \
    -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='$DB_NAME' AND pid<>pg_backend_pid();" \
    -c "DROP DATABASE IF EXISTS $DB_NAME;" \
    -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"

log "Streaming dump from prod → restoring to dev (no temp file needed)..."
prod_cmd "docker exec $PROD_PG_CONTAINER pg_dump -U $DB_USER -d $DB_NAME --no-owner --no-privileges -Fc" \
    | docker exec -i "$DEV_PG_CONTAINER" pg_restore \
        -U "$DB_USER" -d "$DB_NAME" --no-owner --no-privileges \
    && log "Restore complete." \
    || log "pg_restore finished with warnings (usually harmless — check output above)."

# ── Restart dev and run post-sync steps ──────────────────────────────────────

log "Restarting dev app containers..."
docker start "$DEV_APP_CONTAINER" "$DEV_QUEUE_CONTAINER"

log "Waiting for app container to be ready..."
for i in $(seq 1 15); do
    docker exec "$DEV_APP_CONTAINER" php artisan --version &>/dev/null && break
    sleep 2
done

log "Running any pending dev migrations..."
docker exec "$DEV_APP_CONTAINER" php artisan migrate --force

log "Clearing dev caches..."
docker exec "$DEV_APP_CONTAINER" php artisan cache:clear
docker exec "$DEV_APP_CONTAINER" php artisan config:clear
docker exec "$DEV_APP_CONTAINER" php artisan view:clear

log "Done! Dev is live at http://localhost:8040"
