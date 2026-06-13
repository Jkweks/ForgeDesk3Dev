#!/bin/sh
set -e

# Sync compiled public assets into the shared volume so nginx can serve them.
if [ -d "/var/www/public_shared" ]; then
    echo "Syncing public assets to shared volume..."
    cp -rf /var/www/html/public/. /var/www/public_shared/
fi

# Clear and rebuild caches so the running container reflects baked-in code
echo "Clearing application caches..."
php artisan route:clear
php artisan config:clear
php artisan view:clear

echo "Building application caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations only when explicitly enabled
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

exec "$@"