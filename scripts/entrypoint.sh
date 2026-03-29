#!/bin/sh
set -e

# Sync compiled public assets into the shared volume so nginx can serve them.
# This overwrites stale files from previous image versions on every container start.
if [ -d "/var/www/public_shared" ]; then
    echo "Syncing public assets to shared volume..."
    cp -rf /var/www/html/public/. /var/www/public_shared/
fi

# Clear cached routes/config/views so the running container always reflects
# the code baked into this image — prevents stale route-cache 404s.
echo "Clearing application caches..."
php artisan route:clear
php artisan config:clear
php artisan view:clear

# Run migrations only when explicitly enabled (default true for app, false for queue)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

exec "$@"
