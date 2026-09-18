#!/usr/bin/env bash
set -euo pipefail

# One-time migration: moves postgres_data / app_public / app_storage /
# app_private off Docker's internal volume store and onto host bind mounts
# under ./_data/<name>, matching docker-compose.prod.yml's driver_opts.
# Run this BEFORE applying the compose file that adds driver_opts, from the
# directory containing docker-compose.prod.yml.
#
# Safe by construction: nothing is deleted until its data has been copied
# out and verified non-empty. Stops the stack for the duration (a brief
# outage), since Postgres and the app must not be writing while their
# volumes are copied.

COMPOSE_FILE="docker-compose.prod.yml"
mkdir -p _data/postgres_data _data/app_public _data/app_storage _data/app_private

# Discover the ACTUAL existing volume names from the running containers' own
# mounts, rather than guessing the compose project's name-prefixing — this
# works regardless of what COMPOSE_PROJECT_NAME resolved to when the stack
# was first brought up.
declare -A VOL
find_volume() {
  local container="$1" dest="$2"
  docker inspect "${container}" \
    --format '{{range .Mounts}}{{if eq .Destination "'"${dest}"'"}}{{.Name}}{{end}}{{end}}' 2>/dev/null
}

VOL[postgres_data]=$(find_volume forgedesk_postgres /var/lib/postgresql/data)
VOL[app_public]=$(find_volume forgedesk_app /var/www/html/public)
VOL[app_storage]=$(find_volume forgedesk_app /var/www/html/storage/app/public)
VOL[app_private]=$(find_volume forgedesk_app /var/www/html/storage/app/private)

for key in "${!VOL[@]}"; do
  if [ -z "${VOL[${key}]}" ]; then
    echo "ERROR: could not find an existing volume backing ${key} — is the stack running? Aborting, nothing changed." >&2
    exit 1
  fi
  echo "Found ${key} -> docker volume '${VOL[${key}]}'"
done

echo "Stopping stack (brief outage while volumes are copied)..."
docker compose -f "${COMPOSE_FILE}" down

# Owning UID:GID each container actually runs as, so the bind-mounted data
# is readable/writable once it's outside the volume (the copy step below
# runs as root inside the alpine helper, which would otherwise leave
# everything root-owned on the host — postgres and www-data are not root).
declare -A OWNER=(
  [postgres_data]="70:70"   # postgres:16-alpine's `postgres` user
  [app_public]="82:82"      # this image's `www-data` user
  [app_storage]="82:82"
  [app_private]="82:82"
)

for key in "${!VOL[@]}"; do
  vol="${VOL[${key}]}"
  echo "Copying volume '${vol}' -> ./_data/${key} ..."
  docker run --rm -v "${vol}:/from:ro" -v "$(pwd)/_data/${key}:/to" alpine \
    sh -c 'cp -a /from/. /to/'
  docker run --rm -v "$(pwd)/_data/${key}:/to" alpine \
    chown -R "${OWNER[${key}]}" /to
  count=$(find "_data/${key}" -mindepth 1 | wc -l)
  echo "  -> ${count} item(s) copied into ./_data/${key}, chowned to ${OWNER[${key}]}"
done

echo
echo "Data copied. Verify counts above look right, then:"
echo "  1. Confirm docker-compose.prod.yml has driver_opts pointing at ./_data/<name> for all four volumes."
echo "  2. Remove the old (now-orphaned) volumes so Compose creates fresh bind-backed ones in their place:"
for key in "${!VOL[@]}"; do
  echo "       docker volume rm ${VOL[${key}]}"
done
echo "  3. Bring the stack back up: docker compose -f ${COMPOSE_FILE} up -d"
echo "  4. Spot-check: log in, open a job document, confirm Postgres data is intact."
