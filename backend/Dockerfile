FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.3-fpm-alpine

# Cache, queue, sessions and rate limiting run on Redis (REDIS_CLIENT=phpredis), so the redis extension must be
# built for THIS image's PHP: Alpine's php83-pecl-redis package targets Alpine's own PHP and is never loaded here.
# ext-uv (libuv) is the event loop for Reverb (ReactPHP picks it automatically when loaded): without it Reverb falls back to
# stream_select(), which cannot hold more than ~1000 open WebSocket connections. `libuv` is the runtime library and must
# stay after `apk del $PHPIZE_DEPS`. The last command fails the build if either extension did not load.
# su-exec: entrypoint.sh runs artisan and the queue/scheduler processes as www-data (like php-fpm), not as root
RUN apk add --no-cache \
    nginx \
    supervisor \
    netcat-openbsd \
    su-exec \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    libuv-dev \
    libuv \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        bcmath \
        gd \
        intl \
        zip \
        pcntl \
        opcache \
    && pecl install redis-6.3.0 \
    && pecl install uv-0.3.0 \
    && docker-php-ext-enable redis uv \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear \
    && php -r 'exit(extension_loaded("redis") && extension_loaded("uv") ? 0 : 1);'

WORKDIR /var/www/html

COPY --from=vendor /app ./

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-rizqim.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
# Extra response headers hook included by nginx.conf; empty = nothing added (staging bind-mounts its own, docker-compose.staging.yml)
RUN touch /etc/nginx/rizqim-headers.conf

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/framework/testing storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
