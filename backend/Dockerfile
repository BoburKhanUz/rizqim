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

RUN apk add --no-cache \
    nginx \
    supervisor \
    netcat-openbsd \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    php83-pecl-redis \
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
    && apk del $PHPIZE_DEPS

WORKDIR /var/www/html

COPY --from=vendor /app ./

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/framework/testing storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
