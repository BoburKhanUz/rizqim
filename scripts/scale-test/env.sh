# shellcheck shell=bash
# Environment for every artisan / server process of the scale test. No tracked file is edited (logs/, results/ and
# cache/ next to this file are gitignored; cache/ holds the config cache WITH secrets, delete it after a run):
# all configuration is passed as environment variables (they override backend/.env).
export SC=${SC:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}
export BACKEND=${BACKEND:-$(cd "$SC/../../backend" && pwd)}
mkdir -p "$SC/logs" "$SC/results"
export APP_PORT=${APP_PORT:-18500}
export DB_DATABASE=${DB_DATABASE:-scale_big}
export APP_ENV=local APP_DEBUG=false
export LOG_CHANNEL=stderr LOG_LEVEL=warning          # never write storage/logs in the repo
export E2E_POS_SEED_FILE=$SC/logs/seed.json         # PosStackSeeder writes here, not into storage/app
export QUEUE_CONNECTION=null                         # queued jobs (broadcasts, notifications, fiscal retry) are dropped
export BROADCAST_CONNECTION=null                     # Reverb off = the default deployment (DB_SCALE_PLAN 3)
export MAIL_MAILER=array SESSION_DRIVER=array
export CACHE_STORE=redis REDIS_CLIENT=phpredis REDIS_HOST=127.0.0.1 REDIS_PORT=16379 REDIS_PASSWORD=null  # like prod (redis), own instance
export TELEGRAM_BOT_TOKEN= TELEGRAM_OPS_CHAT_ID= TELEGRAM_SUPERADMIN_CHAT_ID= FCM_PROJECT_ID= ESKIZ_EMAIL= ESKIZ_PASSWORD=
# Black-hole proxy: any outbound HTTP from PHP (curl/Guzzle honour these) fails fast instead of reaching Telegram/Click/Payme/fiscal
export http_proxy=http://127.0.0.1:9 https_proxy=http://127.0.0.1:9 HTTP_PROXY=http://127.0.0.1:9 HTTPS_PROXY=http://127.0.0.1:9 no_proxy= NO_PROXY=
export PSQL="sudo -n -u postgres psql -X -q -v ON_ERROR_STOP=1"
# Like production (Dockerfile: opcache; entrypoint: config:cache + route:cache), but the caches live in cache/ next to this file
export PHP_INI_SCAN_DIR=":$SC/php"
export APP_CONFIG_CACHE=$SC/cache/config.php APP_ROUTES_CACHE=$SC/cache/routes.php APP_EVENTS_CACHE=$SC/cache/events.php
