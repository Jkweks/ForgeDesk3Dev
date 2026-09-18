#!/usr/bin/env bash
set -euo pipefail

# Cron runs with a minimal environment — no inherited ssh-agent, and often a
# different (or unset) HOME/PATH than your interactive login shell, which is
# why remote-copy steps can fail under cron while working fine when run by
# hand. Harden both explicitly rather than relying on whatever cron happens
# to provide.
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"
export HOME="${HOME:-/root}"
echo "$(date): running as $(whoami), HOME=${HOME}, PATH=${PATH}, SSH_AUTH_SOCK=${SSH_AUTH_SOCK:-<unset>}"

# --- Config ---
BACKUP_DIR="/opt/forgedesk/backup/backup_storage"
COMPOSE_ENV_FILE="/opt/forgedesk/.env"     # path to the .env with DB_PASSWORD (also holds FAB_UTILS_SHIM_PATH)
DB_CONTAINER="forgedesk_postgres"
APP_CONTAINER="forgedesk_app"              # container that holds storage/app (and receives backup:record)
DB_NAME="forgedesk"
DB_USER="forgedesk"
STORAGE_PATH="/var/www/html/storage/app"   # Laravel uploaded files/documents/photos (inside APP_CONTAINER) —
                                            # tarred recursively, so app/public, app/private, app/ez_estimates,
                                            # app/templates etc. are all covered without listing them separately.
LOCAL_RETENTION_DAYS=4
REMOTE_RETENTION_DAYS=14
REMOTE_USER="deploy"
REMOTE_HOST="50.6.250.54"
REMOTE_DIR="~/forgedesk/backup/Vos"


# fab_utils (shimshop + configurator) — in prod these live as separate
# databases in the SAME postgres container/instance as forgedesk (DB_CONTAINER
# above), just different DB names. Dumped with the same DB_USER/DB_PASSWORD.
FAB_UTILS_DBS="shimshop configurator"
# shim_files (planpic/planpdf) are host-bind-mounted into the fab_utils web
# container — no docker exec needed, just archive the host directory. Uses
# the same FAB_UTILS_SHIM_PATH value docker-compose.prod.yml resolves, so this
# never drifts from what's actually mounted; defaults to "shim_files" next to
# the compose file, matching that file's own default.
FAB_UTILS_SHIM_PATH=$(grep -E '^FAB_UTILS_SHIM_PATH=' "${COMPOSE_ENV_FILE}" 2>/dev/null | cut -d '=' -f2-)
FAB_UTILS_SHIM_PATH="${FAB_UTILS_SHIM_PATH:-$(dirname "${COMPOSE_ENV_FILE}")/shim_files}"

RUN_DATE=$(date +"%Y-%m-%d")
STARTED_AT=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
DB_FILE="${BACKUP_DIR}/forgedesk_${TIMESTAMP}.sql.gz"
FILES_FILE="${BACKUP_DIR}/forgedesk_${TIMESTAMP}_storage.tar.gz"
mkdir -p "${BACKUP_DIR}"

# --- Status page reporting ---
# Every component's result is appended here as {"name":{"success":bool,"message":"..."}},
# then the whole run is reported to the app (so /status can show backup health)
# via `docker exec -i APP_CONTAINER php artisan backup:record` — the app never
# needs host/cron access, backup.sh just needs docker access to APP_CONTAINER,
# which it already has for the storage archive step below.
COMPONENTS_JSON="{"
FIRST_COMPONENT=1
LOCAL_FAILURE=0
REMOTE_FAILURE=0

add_component() {
  local name="$1" success="$2" message="$3"
  if [ "${FIRST_COMPONENT}" -eq 1 ]; then FIRST_COMPONENT=0; else COMPONENTS_JSON="${COMPONENTS_JSON},"; fi
  local esc="${message//\\/\\\\}"
  esc="${esc//\"/\\\"}"
  COMPONENTS_JSON="${COMPONENTS_JSON}\"${name}\":{\"success\":${success},\"message\":\"${esc}\"}"
}

report_run() {
  local status="$1" error_message="$2"
  local finished_at
  finished_at=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
  local esc_error="${error_message//\\/\\\\}"
  esc_error="${esc_error//\"/\\\"}"
  local error_json="null"
  if [ -n "${error_message}" ]; then
    error_json="\"${esc_error}\""
  fi
  # COMPONENTS_JSON opens with "{" but never closes it (add_component only
  # ever appends entries), so the "}" right after it below is what closes the
  # components object — not a typo.
  local payload="{\"run_date\":\"${RUN_DATE}\",\"started_at\":\"${STARTED_AT}\",\"finished_at\":\"${finished_at}\",\"status\":\"${status}\",\"components\":${COMPONENTS_JSON}},\"error_message\":${error_json}}"
  if ! printf '%s' "${payload}" | docker exec -i "${APP_CONTAINER}" php artisan backup:record >/dev/null 2>&1; then
    echo "$(date): WARNING - could not record backup run status on the status page (backups themselves are unaffected)" >&2
  fi
}

# --- Read DB password from .env ---
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' "${COMPOSE_ENV_FILE}" | cut -d '=' -f2-)
if [ -z "${DB_PASSWORD}" ]; then
  echo "$(date): ERROR - could not read DB_PASSWORD from ${COMPOSE_ENV_FILE}" >&2
  add_component "config" 0 "Could not read DB_PASSWORD from ${COMPOSE_ENV_FILE}"
  report_run "failed" "Could not read DB_PASSWORD from ${COMPOSE_ENV_FILE}"
  exit 1
fi

# --- Database dump (fatal on failure) ---
docker exec -e PGPASSWORD="${DB_PASSWORD}" "${DB_CONTAINER}" \
  pg_dump -U "${DB_USER}" -d "${DB_NAME}" | gzip > "${DB_FILE}"
if [ $? -eq 0 ] && [ -s "${DB_FILE}" ]; then
  echo "$(date): DB backup succeeded -> ${DB_FILE} ($(du -h "${DB_FILE}" | cut -f1))"
  add_component "forgedesk_db" 1 "OK -> $(basename "${DB_FILE}")"
else
  echo "$(date): ERROR - DB backup failed or produced an empty file" >&2
  add_component "forgedesk_db" 0 "pg_dump failed or produced an empty file"
  rm -f "${DB_FILE}"
  report_run "failed" "forgedesk DB backup failed or produced an empty file"
  exit 1
fi

# --- Storage archive: uploaded files / documents / photos (non-fatal) ---
# Tarred from inside the app container (no host bind-mount in prod). Paths inside
# the archive are relative to storage/ ("app/public/...", "app/..."), so restore
# with:
#   docker cp forgedesk_<ts>_storage.tar.gz forgedesk_app:/tmp/s.tar.gz
#   docker exec forgedesk_app sh -c 'cd /var/www/html/storage && tar xzf /tmp/s.tar.gz && rm /tmp/s.tar.gz'
STORAGE_PARENT="$(dirname "${STORAGE_PATH}")"
STORAGE_LEAF="$(basename "${STORAGE_PATH}")"
set +e
docker exec "${APP_CONTAINER}" sh -c \
  "cd '${STORAGE_PARENT}' && tar czf - '${STORAGE_LEAF}'" > "${FILES_FILE}"
TAR_RC=$?
set -e
# tar rc 0 == ok; 1 == some files changed/were skipped while reading (e.g. a
# file vanished or was unreadable) but the rest was archived; 2 == fatal, no
# usable archive. Keep the archive for rc 0 or 1 as long as it isn't empty.
if [ "${TAR_RC}" -eq 0 ] && [ -s "${FILES_FILE}" ]; then
  echo "$(date): Storage archive succeeded -> ${FILES_FILE} ($(du -h "${FILES_FILE}" | cut -f1))"
  add_component "app_storage" 1 "OK -> $(basename "${FILES_FILE}")"
elif [ "${TAR_RC}" -eq 1 ] && [ -s "${FILES_FILE}" ]; then
  echo "$(date): WARNING - storage tar returned rc=1 (some files skipped) but produced an archive; keeping ${FILES_FILE} ($(du -h "${FILES_FILE}" | cut -f1))" >&2
  add_component "app_storage" 1 "tar rc=1 but archive produced -> $(basename "${FILES_FILE}")"
else
  echo "$(date): WARNING - storage archive failed (rc=${TAR_RC}); DB backup still OK" >&2
  add_component "app_storage" 0 "tar failed (rc=${TAR_RC})"
  LOCAL_FAILURE=1
  rm -f "${FILES_FILE}"
  FILES_FILE=""
fi

# --- fab_utils DB dumps: shimshop + configurator (non-fatal) ---
# Same postgres container/user/password as the main DB — just different
# database names within that one instance.
FAB_UTILS_DB_FILES=""
for db in ${FAB_UTILS_DBS}; do
  fab_db_file="${BACKUP_DIR}/fab_utils_${db}_${TIMESTAMP}.sql.gz"
  set +e
  docker exec -e PGPASSWORD="${DB_PASSWORD}" "${DB_CONTAINER}" \
    pg_dump -U "${DB_USER}" -d "${db}" | gzip > "${fab_db_file}"
  DUMP_RC=$?
  set -e
  if [ "${DUMP_RC}" -eq 0 ] && [ -s "${fab_db_file}" ]; then
    echo "$(date): fab_utils '${db}' DB backup succeeded -> ${fab_db_file} ($(du -h "${fab_db_file}" | cut -f1))"
    add_component "fab_utils_${db}_db" 1 "OK -> $(basename "${fab_db_file}")"
    FAB_UTILS_DB_FILES="${FAB_UTILS_DB_FILES} ${fab_db_file}"
  else
    echo "$(date): WARNING - fab_utils '${db}' DB backup failed; other backups still OK" >&2
    add_component "fab_utils_${db}_db" 0 "pg_dump failed"
    LOCAL_FAILURE=1
    rm -f "${fab_db_file}"
  fi
done

# --- fab_utils storage: shim_files (planpic/planpdf plan images + PDFs), non-fatal ---
# Archived directly on the host (bind-mounted into the fab_utils container, not
# forgedesk_app), so this needs `tar` on the host itself, not inside a container.
FAB_UTILS_SHIM_FILE=""
if [ -d "${FAB_UTILS_SHIM_PATH}" ]; then
  FAB_UTILS_SHIM_FILE="${BACKUP_DIR}/fab_utils_shimfiles_${TIMESTAMP}.tar.gz"
  set +e
  tar czf "${FAB_UTILS_SHIM_FILE}" -C "$(dirname "${FAB_UTILS_SHIM_PATH}")" "$(basename "${FAB_UTILS_SHIM_PATH}")"
  SHIM_TAR_RC=$?
  set -e
  if [ "${SHIM_TAR_RC}" -eq 0 ] && [ -s "${FAB_UTILS_SHIM_FILE}" ]; then
    echo "$(date): fab_utils shim_files archive succeeded -> ${FAB_UTILS_SHIM_FILE} ($(du -h "${FAB_UTILS_SHIM_FILE}" | cut -f1))"
    add_component "fab_utils_shimfiles" 1 "OK -> $(basename "${FAB_UTILS_SHIM_FILE}")"
  elif [ "${SHIM_TAR_RC}" -eq 1 ] && [ -s "${FAB_UTILS_SHIM_FILE}" ]; then
    echo "$(date): WARNING - shim_files tar returned rc=1 (some files skipped) but produced an archive; keeping ${FAB_UTILS_SHIM_FILE}" >&2
    add_component "fab_utils_shimfiles" 1 "tar rc=1 but archive produced -> $(basename "${FAB_UTILS_SHIM_FILE}")"
  else
    echo "$(date): WARNING - fab_utils shim_files archive failed (rc=${SHIM_TAR_RC}); other backups still OK" >&2
    add_component "fab_utils_shimfiles" 0 "tar failed (rc=${SHIM_TAR_RC})"
    LOCAL_FAILURE=1
    rm -f "${FAB_UTILS_SHIM_FILE}"
    FAB_UTILS_SHIM_FILE=""
  fi
else
  echo "$(date): WARNING - fab_utils shim_files path not found (${FAB_UTILS_SHIM_PATH}); skipping" >&2
  add_component "fab_utils_shimfiles" 0 "path not found: ${FAB_UTILS_SHIM_PATH}"
  LOCAL_FAILURE=1
fi

# --- Copy backups to remote server (non-fatal per file) ---
SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=accept-new)

# One upfront connectivity check so an auth/host-key/network problem is logged
# once with its real reason, instead of the same silent failure repeated for
# every file in the loop below.
PROBE_ERR=$(mktemp)
if ssh "${SSH_OPTS[@]}" "${REMOTE_USER}@${REMOTE_HOST}" true 2>"${PROBE_ERR}"; then
  echo "$(date): Remote SSH connectivity check OK (${REMOTE_USER}@${REMOTE_HOST})"
else
  echo "$(date): WARNING - remote SSH connectivity check FAILED: $(tr -d '\n' < "${PROBE_ERR}")" >&2
fi
rm -f "${PROBE_ERR}"

REMOTE_OK_COUNT=0
REMOTE_FAIL_COUNT=0
for f in "${DB_FILE}" ${FILES_FILE:+"${FILES_FILE}"} ${FAB_UTILS_DB_FILES} ${FAB_UTILS_SHIM_FILE:+"${FAB_UTILS_SHIM_FILE}"}; do
  SCP_ERR=$(mktemp)
  if scp "${SSH_OPTS[@]}" \
      "${f}" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}/" >/dev/null 2>"${SCP_ERR}"; then
    echo "$(date): Remote copy succeeded -> ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}/$(basename "${f}")"
    REMOTE_OK_COUNT=$((REMOTE_OK_COUNT + 1))
  else
    echo "$(date): WARNING - remote copy of $(basename "${f}") failed (local backup still OK): $(tr -d '\n' < "${SCP_ERR}")" >&2
    REMOTE_FAIL_COUNT=$((REMOTE_FAIL_COUNT + 1))
    REMOTE_FAILURE=1
  fi
  rm -f "${SCP_ERR}"
done
add_component "remote_copy" "$([ "${REMOTE_FAIL_COUNT}" -eq 0 ] && echo 1 || echo 0)" "${REMOTE_OK_COUNT} succeeded, ${REMOTE_FAIL_COUNT} failed"

# --- Local retention: drop DB dumps and storage archives older than LOCAL_RETENTION_DAYS ---
find "${BACKUP_DIR}" -name "forgedesk_*.sql.gz"          -type f -mtime +${LOCAL_RETENTION_DAYS} -delete
find "${BACKUP_DIR}" -name "forgedesk_*_storage.tar.gz"  -type f -mtime +${LOCAL_RETENTION_DAYS} -delete
find "${BACKUP_DIR}" -name "fab_utils_*.sql.gz"          -type f -mtime +${LOCAL_RETENTION_DAYS} -delete
find "${BACKUP_DIR}" -name "fab_utils_shimfiles_*.tar.gz" -type f -mtime +${LOCAL_RETENTION_DAYS} -delete
echo "$(date): Local cleanup complete (retention: ${LOCAL_RETENTION_DAYS} days)"

# --- Remote retention: drop backups on the remote host older than REMOTE_RETENTION_DAYS (non-fatal) ---
# Uses the same four filename patterns as the local cleanup above, just run via
# ssh against REMOTE_DIR instead of BACKUP_DIR.
set +e
CLEANUP_ERR=$(mktemp)
ssh "${SSH_OPTS[@]}" "${REMOTE_USER}@${REMOTE_HOST}" \
  "find ${REMOTE_DIR} -type f \\( \
      -name 'forgedesk_*.sql.gz' -o \
      -name 'forgedesk_*_storage.tar.gz' -o \
      -name 'fab_utils_*.sql.gz' -o \
      -name 'fab_utils_shimfiles_*.tar.gz' \
    \\) -mtime +${REMOTE_RETENTION_DAYS} -delete" >/dev/null 2>"${CLEANUP_ERR}"
REMOTE_CLEANUP_RC=$?
set -e
if [ "${REMOTE_CLEANUP_RC}" -eq 0 ]; then
  echo "$(date): Remote cleanup complete (retention: ${REMOTE_RETENTION_DAYS} days)"
  add_component "remote_retention" 1 "OK (retention: ${REMOTE_RETENTION_DAYS} days)"
else
  echo "$(date): WARNING - remote cleanup failed (rc=${REMOTE_CLEANUP_RC}): $(tr -d '\n' < "${CLEANUP_ERR}"); local backups still OK" >&2
  add_component "remote_retention" 0 "ssh cleanup failed (rc=${REMOTE_CLEANUP_RC})"
  REMOTE_FAILURE=1
fi
rm -f "${CLEANUP_ERR}"

# --- Report overall run status to the app (red > orange > yellow > green) ---
if [ "${REMOTE_FAILURE}" -eq 1 ]; then
  OVERALL_STATUS="remote_failed"
elif [ "${LOCAL_FAILURE}" -eq 1 ]; then
  OVERALL_STATUS="local_failed"
else
  OVERALL_STATUS="success"
fi
report_run "${OVERALL_STATUS}" ""
