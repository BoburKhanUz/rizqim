#!/bin/sh
set -e

APP_DIR=/var/www/html
APP_USER=www-data
cd "$APP_DIR"

# php-fpm runs as www-data, so everything artisan writes (storage/logs/laravel.log, compiled views, bootstrap/cache) has
# to belong to www-data too: a root-owned laravel.log makes php-fpm fail every request that logs. The container starts
# as root (the nginx and php-fpm masters need it), so artisan below goes through su-exec, and the queue and scheduler
# processes are handed to www-data at the end. Started as another user (docker run --user) everything runs as that user.
if [ "$(id -u)" = "0" ]; then
    as_app() { su-exec "$APP_USER" "$@"; }
    # Heals what an earlier `docker compose exec backend php artisan ...` left behind (exec is root unless -u www-data)
    chown -R "$APP_USER:$APP_USER" storage/logs storage/framework bootstrap/cache 2>/dev/null || true
else
    as_app() { "$@"; }
fi

if [ ! -f .env ]; then
    as_app cp .env.example .env
fi

echo "Waiting for database ($DB_HOST:$DB_PORT)..."
until nc -z "${DB_HOST:-pgsql}" "${DB_PORT:-5432}"; do
    sleep 1
done
echo "Database is up."

# Refuse to start with production-unsafe settings (APP_ENV, APP_DEBUG, APP_KEY, TELEGRAM_ALLOW_MOCK_AUTH,
# TELEGRAM_WEBHOOK_SECRET, TELEGRAM_WEBAPP_URL, default super admin password); runs after the DB wait because it
# checks accounts. APP_KEY is never generated here: web, queue and scheduler each run this script in their own
# container, so each would get a different key (and a new one on every recreate) and could not read the others'
# sessions or encrypted data. It must come from backend/.env; only a local setup runs key:generate itself.
if ! as_app php artisan app:check-production; then
    echo "XATO: konteyner ishga tushirilmadi. backend/.env dagi yuqorida ko'rsatilgan sozlamalarni tuzating." >&2
    exit 1
fi

# Only the web container (RUN_MIGRATIONS=true) deploys; queue and scheduler wait for its healthcheck (docker-compose.yml)
# and so start after the migrations. Same steps as scripts/deploy.sh (VPS). Nothing serves requests while this runs
# (nginx and php-fpm start after the entrypoint), so unlike the VPS there is no maintenance mode to switch on.
if [ "$RUN_MIGRATIONS" = "true" ]; then
    as_app php artisan migrate --force
    as_app php artisan storage:link || true
    # filament:upgrade runs config:clear/route:clear/view:clear, so it must come before the caches are built
    as_app php artisan filament:upgrade || true
    as_app php artisan config:cache
    as_app php artisan route:cache
    as_app php artisan view:cache
    # The webhook route requires secret_token, so (re)register it on every deploy
    as_app php artisan telegram:set-webhook --if-configured \
        || echo "OGOHLANTIRISH: Telegram webhook o'rnatilmadi — 'php artisan telegram:set-webhook' ni qo'lda ishga tushiring." >&2
    # Last, because the restart signal is stored in the cache (Redis): the queue workers (queue and queue-reports) that
    # still run old code finish their current job and exit, and Docker starts them again with the new code
    as_app php artisan queue:restart \
        || echo "OGOHLANTIRISH: queue:restart bajarilmadi — Redis/kesh ulanishini tekshiring, so'ng 'docker compose restart queue queue-reports' ni ishga tushiring." >&2
fi

# The web container's command (supervisord) stays root: its nginx and php-fpm masters bind port 80 and drop their workers
# to nginx/www-data themselves. Anything else (queue worker, scheduler, an ad-hoc `docker compose run backend ...`) runs
# as www-data.
if [ "$(id -u)" = "0" ] && [ "$(basename "$1")" != "supervisord" ]; then
    exec su-exec "$APP_USER" "$@"
fi
exec "$@"
