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

# Refuse to start with production-unsafe settings (APP_ENV, APP_DEBUG, TELEGRAM_ALLOW_MOCK_AUTH,
# TELEGRAM_WEBHOOK_SECRET, default super admin password); runs after the DB wait because it checks accounts
if ! php artisan app:check-production; then
    echo "XATO: konteyner ishga tushirilmadi. backend/.env dagi yuqorida ko'rsatilgan sozlamalarni tuzating." >&2
    exit 1
fi

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
    # The webhook route requires secret_token, so (re)register it on every deploy
    php artisan telegram:set-webhook --if-configured \
        || echo "OGOHLANTIRISH: Telegram webhook o'rnatilmadi — 'php artisan telegram:set-webhook' ni qo'lda ishga tushiring." >&2
fi

exec "$@"
