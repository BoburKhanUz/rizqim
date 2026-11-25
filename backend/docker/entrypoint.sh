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

# The web container (supervisord): check the pool sizes, the market limits and the pooler settings, and that nginx and
# php-fpm accept the rendered config, before anything starts. A bad FPM_* / NGINX_* / PGBOUNCER_POOL_SIZE / DB_SSLMODE
# value stops the container here with the reason, instead of supervisord running with nginx or php-fpm FATAL.
if [ "$(basename "${1:-}")" = "supervisord" ]; then
    if ! rizqim-pools check; then
        echo "XATO: konteyner ishga tushirilmadi: yuqoridagi FPM_*/NGINX_*/PGBOUNCER_POOL_SIZE/DB_SSLMODE sozlamasini tuzating (docs/DEPLOY.md)." >&2
        exit 1
    fi
fi

# Bounded waits (STARTUP_WAIT_SECONDS, default 120): a database that never comes up stops the container with an
# error (Docker's restart policy tries again) instead of a container that hangs here for ever.
echo "Waiting for database (${DB_HOST:-pgsql}:${DB_PORT:-5432})..."
rizqim-pools wait-for Postgres "${DB_HOST:-pgsql}" "${DB_PORT:-5432}"
echo "Database is up."

# DB_POOLER=pgbouncer (docker-compose.yml, web container only): php-fpm reaches Postgres through PgBouncer in
# transaction mode (config/database.php). Wait for it too, so the first requests do not fail while it starts.
# PgBouncer has no TLS towards php-fpm: DB_SSLMODE=require/verify-* with the pooler is refused (rizqim-pools).
if [ "${DB_POOLER:-direct}" = "pgbouncer" ]; then
    echo "Waiting for the connection pooler (${DB_POOL_HOST:-127.0.0.1}:${DB_POOL_PORT:-6432})..."
    rizqim-pools wait-for PgBouncer "${DB_POOL_HOST:-127.0.0.1}" "${DB_POOL_PORT:-6432}"
fi

# M2: real-time is on by default (docker-compose.yml: BROADCAST_CONNECTION=reverb). With no REVERB_APP_ID/KEY/SECRET
# set anywhere, the web container (the first to start; queue, scheduler and reverb wait for its healthcheck) generates
# them once into the shared storage volume, readable by root only, and every container exports them from there. The
# values are never printed. Keys set in the root .env always win and are never overwritten. A container that finds no
# keys runs with BROADCAST_CONNECTION=null (clients poll) instead of signing channels with empty keys.
# Rollback: BROADCAST_CONNECTION=null in the root .env. docs/DEPLOY.md, 9-bo'lim
REVERB_KEYS_FILE="${REVERB_KEYS_FILE:-$APP_DIR/storage/app/.realtime/reverb.env}"
if [ "${BROADCAST_CONNECTION:-}" = "reverb" ] && [ -z "${REVERB_APP_ID:-}${REVERB_APP_KEY:-}${REVERB_APP_SECRET:-}" ]; then
    if [ ! -s "$REVERB_KEYS_FILE" ] && [ "$RUN_MIGRATIONS" = "true" ]; then
        (
            umask 077
            mkdir -p "$(dirname "$REVERB_KEYS_FILE")"
            php -r 'printf("REVERB_APP_ID=%d\nREVERB_APP_KEY=%s\nREVERB_APP_SECRET=%s\n", random_int(100000, 999999), bin2hex(random_bytes(16)), bin2hex(random_bytes(32)));' > "$REVERB_KEYS_FILE.tmp"
            mv "$REVERB_KEYS_FILE.tmp" "$REVERB_KEYS_FILE"
        )
        echo "Reverb kalitlari yaratildi va saqlandi (qiymatlari chiqarilmaydi): $REVERB_KEYS_FILE"
    fi
    if [ -s "$REVERB_KEYS_FILE" ]; then
        set -a
        # shellcheck source=/dev/null
        . "$REVERB_KEYS_FILE"
        set +a
    else
        echo "OGOHLANTIRISH: Reverb kalitlari topilmadi ($REVERB_KEYS_FILE): bu konteynerda real-time o'chiq (BROADCAST_CONNECTION=null), ilovalar so'rov bilan yangilanadi." >&2
        export BROADCAST_CONNECTION=null
    fi
fi

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
# and so start after the migrations. Nothing serves requests while this runs (nginx and php-fpm start after the
# entrypoint), so there is no maintenance mode to switch on.
if [ "$RUN_MIGRATIONS" = "true" ]; then
    # Migrations always use a direct connection (DB_POOLER=direct), never the pooler: a migration may take session
    # state (SET lock_timeout, CREATE INDEX CONCURRENTLY outside a transaction) that must not leak into a pooled
    # server connection. config:clear first: a restarted container still has the previous start's config cache, which
    # would ignore the override. The cache is rebuilt (with the pooled settings) right after.
    # M4: with DB_OWNER_USERNAME/DB_OWNER_PASSWORD set (docker-compose.yml, backend and scheduler only) `migrate` runs
    # as the schema owner (rizqim_owner) and everything else as DB_USERNAME (rizqim_app). The owner's credentials stay
    # in the environment of this command only: config/database.php never reads them, so config:cache below cannot
    # write them into bootstrap/cache/config.php (php-fpm reads it), and they are removed before supervisord starts.
    as_app php artisan config:clear
    as_app env DB_POOLER=direct php artisan migrate --force
    # Third review M1: stores.payme_key_digest is an index hint that a release without its hook (a code rollback) can
    # leave stale; recomputed on every deploy (idempotent, one row update per changed store, no table lock). A failure
    # never stops the start: Payme calls still find the store, the slow way (docs/OPERATIONS.md, 29). Fourth review: at
    # most 300 s (`timeout` of the image's busybox, php:*-alpine), so a hung database connection never blocks the start
    as_app timeout 300 env DB_POOLER=direct php artisan payments:payme-digests \
        || echo "OGOHLANTIRISH: payments:payme-digests hammasini yoza olmadi yoki 300 s da tugamadi — logni ko'ring va qo'lda qayta ishga tushiring (OPERATIONS.md, 29-bo'lim)." >&2
    as_app php artisan storage:link || true
    as_app php artisan config:cache
    as_app php artisan route:cache
    # M2-4: without it every request discovers the listeners in app/Listeners again (~0.6 ms CPU, 19 files)
    as_app php artisan event:cache
    as_app php artisan view:cache
    # The webhook route requires secret_token, so (re)register it on every deploy
    as_app php artisan telegram:set-webhook --if-configured \
        || echo "OGOHLANTIRISH: Telegram webhook o'rnatilmadi — 'php artisan telegram:set-webhook' ni qo'lda ishga tushiring." >&2
    # Last, because the restart signal is stored in the cache (Redis): the queue workers (queue and queue-reports) that
    # still run old code finish their current job and exit, and Docker starts them again with the new code
    as_app php artisan queue:restart \
        || echo "OGOHLANTIRISH: queue:restart bajarilmadi — Redis/kesh ulanishini tekshiring, so'ng 'docker compose restart queue queue-reports' ni ishga tushiring." >&2
fi

# M4: php-fpm keeps the container's environment (clear_env = no): the owner's credentials, needed above for the
# migrations only, must not reach a request. The scheduler keeps them (its partition commands run as the owner).
if [ "$(basename "${1:-}")" = "supervisord" ]; then
    unset DB_OWNER_USERNAME DB_OWNER_PASSWORD
fi

# The web container's command (supervisord) stays root: its nginx and php-fpm masters bind port 80 and drop their workers
# to nginx/www-data themselves. Anything else (queue worker, scheduler, an ad-hoc `docker compose run backend ...`) runs
# as www-data.
if [ "$(id -u)" = "0" ] && [ "$(basename "$1")" != "supervisord" ]; then
    exec su-exec "$APP_USER" "$@"
fi
exec "$@"
