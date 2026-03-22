FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    git curl libpng-dev libzip-dev zip unzip \
    postgresql-dev oniguruma-dev nodejs npm

RUN docker-php-ext-install \
    pdo pdo_pgsql pgsql mbstring zip exif pcntl bcmath gd

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Bake in PHP config
COPY php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Copy dependency manifests first for better layer caching
COPY laravel/composer.json laravel/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY laravel/package*.json ./
RUN npm ci

# Copy full application
COPY laravel/ .

# Build frontend assets and clean up dev dependencies
RUN npm run build && rm -rf node_modules

# Regenerate optimized autoloader now that all app files are present
RUN composer dump-autoload --optimize --no-dev

# Setup storage/cache directories and permissions
RUN mkdir -p storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

COPY scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
