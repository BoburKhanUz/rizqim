# shellcheck shell=bash
# Environment for every artisan / server process of the scale test. No tracked file is edited (logs/, results/ and
# cache/ under $SCALE_WORK, by default next to this file and gitignored; cache/ holds the config cache WITH secrets, delete it after a run):
# all configuration is passed as environment variables (they override backend/.env).
export SC=${SC:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}
export BACKEND=${BACKEND:-$(cd "$SC/../../backend" && pwd)}
# Where a run writes (logs/, results/, cache/): next to the scripts by default; give two runs on one machine their own
# SCALE_WORK, SCALE_REDIS_PORT, APP_PORT, FPM_PORT_BASE, DB_POOL_PORT (+ PGB_DIR) and DB_DATABASE, so neither clears the
# other's Redis, caches or database.
export SCALE_WORK=${SCALE_WORK:-$SC}
mkdir -p "$SCALE_WORK/logs" "$SCALE_WORK/results"
export APP_PORT=${APP_PORT:-18500}
export DB_DATABASE=${DB_DATABASE:-scale_big}
export APP_ENV=local APP_DEBUG=false
export LOG_CHANNEL=stderr LOG_LEVEL=warning          # never write storage/logs in the repo
export E2E_POS_SEED_FILE=$SCALE_WORK/logs/seed.json         # PosStackSeeder writes here, not into storage/app
export QUEUE_CONNECTION=null                         # queued jobs (broadcasts, notifications, fiscal retry) are dropped
export BROADCAST_CONNECTION=null                     # Reverb off (the M1 runs; the M2 "real-time down" run)
# M2 real-time on (RT=1, server.sh): BROADCAST_CONNECTION=reverb, the queue on the kit's Redis, and server.sh also starts
# Reverb on REVERB_PORT (18580), one queue worker (default,broadcasts, as the compose `queue` service) and the
# heartbeat (php/rt/heartbeat.php: the scheduler's 30 s heartbeat job only; RT_SCHEDULER=full = schedule:work). Local throwaway app key/secret (the secret is new per start).
export REALTIME_REVISION_STORE=redis
if [ "${RT:-0}" = 1 ]; then
  export BROADCAST_CONNECTION=reverb QUEUE_CONNECTION=redis REDIS_QUEUE_CONNECTION=default
  export REVERB_APP_ID=${REVERB_APP_ID:-scale} REVERB_APP_KEY=${REVERB_APP_KEY:-scalekey} REVERB_APP_SECRET=${REVERB_APP_SECRET:-$(od -An -tx1 -N16 /dev/urandom | tr -d ' \n')}
  export REVERB_PORT=${REVERB_PORT:-18580}
  export REVERB_HOST=127.0.0.1 REVERB_SCHEME=http REVERB_SERVER_HOST=127.0.0.1 REVERB_SERVER_PORT=$REVERB_PORT
fi
export MAIL_MAILER=array SESSION_DRIVER=array
export CACHE_STORE=redis REDIS_CLIENT=phpredis REDIS_HOST=127.0.0.1 REDIS_PORT=${SCALE_REDIS_PORT:-16379} REDIS_PASSWORD=null  # like prod (redis), the kit's own instance (server.sh starts it)
export TELEGRAM_BOT_TOKEN='' TELEGRAM_OPS_CHAT_ID='' TELEGRAM_SUPERADMIN_CHAT_ID='' FCM_PROJECT_ID='' ESKIZ_EMAIL='' ESKIZ_PASSWORD=''
# Black-hole proxy: any outbound HTTP from PHP (curl/Guzzle honour these) fails fast instead of reaching Telegram/Click/Payme/fiscal
export http_proxy=http://127.0.0.1:9 https_proxy=http://127.0.0.1:9 HTTP_PROXY=http://127.0.0.1:9 HTTPS_PROXY=http://127.0.0.1:9 no_proxy='' NO_PROXY=''
# RT=1: the app and the queue worker reach Reverb's HTTP API on 127.0.0.1 (everything else stays black-holed)
if [ "${RT:-0}" = 1 ]; then export no_proxy=127.0.0.1,localhost NO_PROXY=127.0.0.1,localhost; fi
export PSQL="sudo -n -u postgres psql -X -q -v ON_ERROR_STOP=1"
# Connection mode of the app (config/database.php): direct (default, as the first run) or pgbouncer = through
# ./pgbouncer.sh on DB_POOL_PORT, transaction pooling, as php-fpm in docker-compose.yml
export DB_POOLER=${DB_POOLER:-direct} DB_POOL_HOST=${DB_POOL_HOST:-127.0.0.1} DB_POOL_PORT=${DB_POOL_PORT:-16432}
# Like production (Dockerfile: opcache; entrypoint: config:cache + route:cache), but the caches live in $SCALE_WORK/cache
export PHP_INI_SCAN_DIR=":$SC/php"
export APP_CONFIG_CACHE=$SCALE_WORK/cache/config.php APP_ROUTES_CACHE=$SCALE_WORK/cache/routes.php APP_EVENTS_CACHE=$SCALE_WORK/cache/events.php
