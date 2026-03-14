#!/bin/bash
set -e

echo "=== Syncing prod DB to dev ==="

# 1. Dump from prod postgres container
echo "Dumping prod database..."
docker exec forgedesk_postgres pg_dump \
  -U forgedesk \
  -d forgedesk \
  --no-owner \
  --no-privileges \
  -Fc \
  > /tmp/forgedesk_prod_dump.dump

# 2. Drop and recreate dev DB to ensure clean slate
echo "Restoring to dev database..."
docker exec -i forgedesk_dev_postgres psql -U forgedesk -d postgres -c "DROP DATABASE IF EXISTS forgedesk;"
docker exec -i forgedesk_dev_postgres psql -U forgedesk -d postgres -c "CREATE DATABASE forgedesk;"

# 3. Restore into dev
docker exec -i forgedesk_dev_postgres pg_restore \
  -U forgedesk \
  -d forgedesk \
  --no-owner \
  --no-privileges \
  < /tmp/forgedesk_prod_dump.dump

# 4. Run any pending dev migrations
echo "Running dev migrations..."
docker exec forgedesk_dev_app php artisan migrate --force

# 5. Clean up dump
rm /tmp/forgedesk_prod_dump.dump

echo "=== Done. Dev DB is now a fresh copy of prod + dev migrations ==="