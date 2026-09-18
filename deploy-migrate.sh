#!/usr/bin/env bash
set -euo pipefail

# Brackets a database migration with Laravel maintenance mode, so any request
# in flight when you run this gets a clean 503 (with Retry-After) instead of
# hitting a half-migrated schema — then always brings the app back up
# afterward, success or failure. Run this on the prod host right after
# pulling/rebuilding the new image, before anything else that depends on the
# new schema.
#
# This intentionally does NOT try to detect "is anyone mid-task" — ForgeDesk
# commits state to the DB on every step (work order wizard, elevation edits,
# uploads), so there's no meaningful client-side unsaved state to protect.
# The only real risk is a request in flight at the exact moment you run this,
# which maintenance mode + a short --retry window covers.

APP_CONTAINER="forgedesk_app"

# Optional: set MAINTENANCE_SECRET to get a bypass URL (https://host/<secret>)
# so you can spot-check the app yourself while everyone else still sees the
# maintenance page. Leave unset for a plain all-or-nothing maintenance mode.
MAINTENANCE_SECRET="${MAINTENANCE_SECRET:-}"
RETRY_SECONDS="${RETRY_SECONDS:-60}"

DOWN_ARGS=(--render="errors::503" --retry="${RETRY_SECONDS}")
if [ -n "${MAINTENANCE_SECRET}" ]; then
  DOWN_ARGS+=(--secret="${MAINTENANCE_SECRET}")
fi

echo "$(date): entering maintenance mode..."
docker exec "${APP_CONTAINER}" php artisan down "${DOWN_ARGS[@]}"

# Always bring the app back up on exit, even if a step below fails partway —
# staying down indefinitely on a bad migration is worse than surfacing the
# failure with the app still reachable. If migrate genuinely left the schema
# broken, that needs a human anyway; a stuck maintenance page just hides it.
cleanup() {
  echo "$(date): exiting maintenance mode..."
  docker exec "${APP_CONTAINER}" php artisan up
}
trap cleanup EXIT

echo "$(date): running migrations..."
docker exec "${APP_CONTAINER}" php artisan migrate --force

echo "$(date): refreshing caches..."
docker exec "${APP_CONTAINER}" php artisan config:clear
docker exec "${APP_CONTAINER}" php artisan config:cache
docker exec "${APP_CONTAINER}" php artisan view:clear

echo "$(date): deploy migration step complete."
