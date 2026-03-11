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

RUN chown -R www-data:www-data /var/www/html
# ... existing content ...

WORKDIR /var/www/html

# Add these lines:
RUN mkdir -p storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

USER www-data
# ... rest of file
USER www-data

EXPOSE 9000

CMD ["php-fpm"]
