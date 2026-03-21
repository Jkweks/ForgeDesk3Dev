#!/bin/bash
set -e

DUMP_FILE="/tmp/forgedesk_prod_dump.dump"

echo "=== [$(date)] Starting prod -> dev sync ==="

# 1. Dump from prod postgres container
echo ">>> Dumping prod database..."
docker exec forgedesk_postgres pg_dump \
  -U forgedesk \
  -d forgedesk \
  --no-owner \
  --no-privileges \
  -Fc \
  > "$DUMP_FILE"

echo ">>> Dump complete: $(du -sh $DUMP_FILE | cut -f1)"

# 2. Stop app containers to release DB connections
echo ">>> Pausing app containers..."
docker stop forgedesk_dev_app forgedesk_dev_queue

# 3. Drop and recreate dev DB for a clean slate
echo ">>> Resetting dev database..."
docker exec forgedesk_dev_postgres psql -U forgedesk -d postgres -c "DROP DATABASE IF EXISTS forgedesk;"
docker exec forgedesk_dev_postgres psql -U forgedesk -d postgres -c "CREATE DATABASE forgedesk;"

# 4. Restore into dev
echo ">>> Restoring dump to dev..."
docker exec -i forgedesk_dev_postgres pg_restore \
  -U forgedesk \
  -d forgedesk \
  --no-owner \
  --no-privileges \
  < "$DUMP_FILE"

# 5. Clean up dump file
rm "$DUMP_FILE"

# 6. Restart app containers
echo ">>> Restarting app containers..."
docker start forgedesk_dev_app forgedesk_dev_queue

# 7. Wait for app to be ready
echo ">>> Waiting for app container to be ready..."
sleep 5

# 8. Run any pending dev migrations
echo ">>> Running dev migrations..."
docker exec forgedesk_dev_app php artisan migrate --force

# 9. Sanitize PII — anonymize real user data
# echo ">>> Sanitizing user data..."
# docker exec forgedesk_dev_app php artisan db:seed --class=DevSanitizeSeeder

# 10. Clear caches so dev starts fresh
echo ">>> Clearing dev caches..."
docker exec forgedesk_dev_app php artisan cache:clear
docker exec forgedesk_dev_app php artisan config:clear

echo "=== [$(date)] Sync complete. Dev is live at http://localhost:8041 ==="
