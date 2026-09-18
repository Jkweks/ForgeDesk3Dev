#!/bin/sh
set -e

# In prod, a named volume is mounted directly onto /var/www/html/public for
# persistence (so it survives container recreation), which means the freshly
# built assets baked into THIS image at that path are invisible by now — the
# volume already shadowed them at container start. /var/www/public_src is a
# pristine copy of this image's public/ made at build time (see Dockerfile),
# unaffected by the mount, so we resync it into the volume on every boot to
# pick up this release's JS/CSS/build changes.
# Restricted to APP_ENV=production: in dev, /var/www/html is bind-mounted
# from the host wholesale, so public/ there is the developer's own live
# build output — this sync must never overwrite that.
if [ "$APP_ENV" = "production" ] && [ -d "/var/www/public_src" ]; then
    echo "Syncing public assets to shared volume..."
    # Never let a sync hiccup (e.g. a file left with mismatched ownership by a
    # manual hotfix) take the whole container down — this container always
    # runs as www-data, so it may not have permission to overwrite everything
    # in the volume, and that must not be fatal to boot.
    if ! cp -rf /var/www/public_src/. /var/www/html/public/; then
        echo "WARNING: public asset sync had errors (possible ownership mismatch in the volume) — continuing boot anyway." >&2
    fi
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