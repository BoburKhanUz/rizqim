#!/bin/sh
set -e

if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

echo "Waiting for database ($DB_HOST:$DB_PORT)..."
until nc -z "${DB_HOST:-pgsql}" "${DB_PORT:-5432}"; do
    sleep 1
done
echo "Database is up."

if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
    php artisan storage:link || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan filament:upgrade || true
fi

exec "$@"
