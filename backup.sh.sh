#!/usr/bin/env bash
set -euo pipefail
# --- Config ---
BACKUP_DIR="/opt/forgedesk/backup"
COMPOSE_ENV_FILE="/opt/forgedesk/.env"     # path to the .env with DB_PASSWORD
DB_CONTAINER="forgedesk_postgres"
APP_CONTAINER="forgedesk_app"              # container that holds storage/app
DB_NAME="forgedesk"
DB_USER="forgedesk"
STORAGE_PATH="/var/www/html/storage/app"   # Laravel uploaded files/documents/photos (inside APP_CONTAINER)
RETENTION_DAYS=90
REMOTE_USER="deploy"
REMOTE_HOST="50.6.250.54"
REMOTE_DIR="~/forgedesk/backup"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
DB_FILE="${BACKUP_DIR}/forgedesk_${TIMESTAMP}.sql.gz"
FILES_FILE="${BACKUP_DIR}/forgedesk_${TIMESTAMP}_storage.zip"
mkdir -p "${BACKUP_DIR}"

# --- Read DB password from .env ---
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' "${COMPOSE_ENV_FILE}" | cut -d '=' -f2-)
if [ -z "${DB_PASSWORD}" ]; then
  echo "$(date): ERROR - could not read DB_PASSWORD from ${COMPOSE_ENV_FILE}" >&2
  exit 1
fi

# --- Database dump (fatal on failure) ---
docker exec -e PGPASSWORD="${DB_PASSWORD}" "${DB_CONTAINER}" \
  pg_dump -U "${DB_USER}" -d "${DB_NAME}" | gzip > "${DB_FILE}"
if [ $? -eq 0 ] && [ -s "${DB_FILE}" ]; then
  echo "$(date): DB backup succeeded -> ${DB_FILE} ($(du -h "${DB_FILE}" | cut -f1))"
else
  echo "$(date): ERROR - DB backup failed or produced an empty file" >&2
  rm -f "${DB_FILE}"
  exit 1
fi

# --- Storage archive: uploaded files / documents / photos (non-fatal) ---
# Zipped from inside the app container (no host bind-mount in prod). Paths inside
# the archive are relative to storage/ ("app/public/...", "app/..."), so restore
# with:
#   docker cp forgedesk_<ts>_storage.zip forgedesk_app:/tmp/s.zip
#   docker exec forgedesk_app sh -c 'cd /var/www/html/storage && unzip -o /tmp/s.zip && rm /tmp/s.zip'
STORAGE_PARENT="$(dirname "${STORAGE_PATH}")"
STORAGE_LEAF="$(basename "${STORAGE_PATH}")"
set +e
docker exec "${APP_CONTAINER}" sh -c \
  "cd '${STORAGE_PARENT}' && zip -r -q -y - '${STORAGE_LEAF}'" > "${FILES_FILE}"
ZIP_RC=$?
set -e
# zip rc 0 == ok; 12 == nothing to archive; 18 == a file couldn't be read but the
# rest was archived. Keep the archive as long as it isn't empty.
if [ "${ZIP_RC}" -eq 0 ] && [ -s "${FILES_FILE}" ]; then
  echo "$(date): Storage archive succeeded -> ${FILES_FILE} ($(du -h "${FILES_FILE}" | cut -f1))"
elif [ "${ZIP_RC}" -ne 0 ] && [ -s "${FILES_FILE}" ]; then
  echo "$(date): WARNING - storage zip returned rc=${ZIP_RC} but produced an archive; keeping ${FILES_FILE} ($(du -h "${FILES_FILE}" | cut -f1))" >&2
else
  echo "$(date): WARNING - storage archive failed (rc=${ZIP_RC}); DB backup still OK" >&2
  rm -f "${FILES_FILE}"
  FILES_FILE=""
fi

# --- Copy backups to remote server (non-fatal per file) ---
for f in "${DB_FILE}" ${FILES_FILE:+"${FILES_FILE}"}; do
  if scp -o BatchMode=yes -o ConnectTimeout=15 \
      "${f}" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}/" >/dev/null 2>&1; then
    echo "$(date): Remote copy succeeded -> ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}/$(basename "${f}")"
  else
    echo "$(date): WARNING - remote copy of $(basename "${f}") failed (local backup still OK)" >&2
  fi
done

# --- Retention: drop DB dumps and storage archives older than RETENTION_DAYS ---
find "${BACKUP_DIR}" -name "forgedesk_*.sql.gz"        -type f -mtime +${RETENTION_DAYS} -delete
find "${BACKUP_DIR}" -name "forgedesk_*_storage.zip"   -type f -mtime +${RETENTION_DAYS} -delete
echo "$(date): Cleanup complete (retention: ${RETENTION_DAYS} days)"
