#!/bin/bash
# server.sh start [workers] | stop  -- redis (cache, rate limiter, locks) + the app on $APP_PORT
#   SERVER=serve (default): php artisan serve with PHP_CLI_SERVER_WORKERS=[workers] (one pool, as the first run)
#   SERVER=fpm: nginx + php-fpm with the repo's real config (backend/docker/nginx.conf, php-fpm.conf, pools.sh):
#     the critical / market / default pools, nginx's routing map and the market limits. [workers] is ignored: the
#     pool sizes and limits are the FPM_* / NGINX_* variables of backend/docker/pools.sh (its defaults when unset).
#     Needs nginx (with the realip module) and php8.3-fpm (`apt-get install nginx php8.3-fpm`); the generated
#     configs live in cache/fpm, the logs in logs/. FPM_PORT_BASE (19000) moves the three pool ports (+0 +1 +2).
source "$(dirname "$0")/env.sh"
SERVER=${SERVER:-serve}
FPM_DIR=$SC/cache/fpm
FPM_BIN=${FPM_BIN:-$(command -v php-fpm8.3 || command -v php-fpm || true)}

start_fpm() {
  [ -n "$FPM_BIN" ] || { echo "php-fpm not found: apt-get install php8.3-fpm" >&2; exit 1; }
  command -v nginx >/dev/null || { echo "nginx not found: apt-get install nginx" >&2; exit 1; }
  local base=${FPM_PORT_BASE:-19000}
  mkdir -p "$FPM_DIR/tmp" && : > "$FPM_DIR/rizqim-headers.conf"
  eval "$(sh "$BACKEND/docker/pools.sh" env)"           # the same defaults/validation as the image
  sh "$BACKEND/docker/pools.sh" render "$FPM_DIR"
  local ports="s#127\.0\.0\.1:9000#127.0.0.1:$base#; s#127\.0\.0\.1:9001#127.0.0.1:$((base + 1))#; s#127\.0\.0\.1:9002#127.0.0.1:$((base + 2))#"
  # The repo's server block; only paths, ports and the listen address are rewritten
  sed -e "$ports" -e "s#/etc/nginx/rizqim-#$FPM_DIR/rizqim-#g" -e "s#root /var/www/html/public;#root $BACKEND/public;#" \
      -e "s#listen 80;#listen 127.0.0.1:$APP_PORT;#" -e "s#include fastcgi_params;#include /etc/nginx/fastcgi_params;#" \
      "$BACKEND/docker/nginx.conf" > "$FPM_DIR/default.conf"
  cat > "$FPM_DIR/nginx.conf" <<EOF
daemon on;
worker_processes 2;
pid $FPM_DIR/nginx.pid;
error_log $SC/logs/nginx-error.log warn;
events { worker_connections 4096; }
http {
  include /etc/nginx/mime.types;
  access_log $SC/logs/nginx-access.log;
  client_body_temp_path $FPM_DIR/tmp/body; fastcgi_temp_path $FPM_DIR/tmp/fastcgi; proxy_temp_path $FPM_DIR/tmp/proxy;
  uwsgi_temp_path $FPM_DIR/tmp/uwsgi; scgi_temp_path $FPM_DIR/tmp/scgi;
  include $FPM_DIR/default.conf;
}
EOF
  # The repo's pools; this process's user instead of www-data, logs into logs/
  { echo "[global]"; echo "pid = $FPM_DIR/php-fpm.pid"
    sed -e "$ports" -e '/^user = /d' -e '/^group = /d' -e '/^\[global\]/d' -e '/^daemonize = /d' \
        -e "s#^error_log = .*#error_log = $SC/logs/php-fpm.log#" -e "s#^access.log = .*#access.log = $SC/logs/php-fpm-access.log#" \
        "$BACKEND/docker/php-fpm.conf"; } > "$FPM_DIR/php-fpm.conf"
  # backend/docker/php.ini too (upload limits), as the image's conf.d/zz-rizqim.ini
  PHP_INI_SCAN_DIR="$PHP_INI_SCAN_DIR:$BACKEND/docker" "$FPM_BIN" -t -y "$FPM_DIR/php-fpm.conf" -R 2> "$SC/logs/php-fpm-test.log" \
    || { cat "$SC/logs/php-fpm-test.log" >&2; exit 1; }
  nginx -t -q -p "$FPM_DIR" -c "$FPM_DIR/nginx.conf" || exit 1
  PHP_INI_SCAN_DIR="$PHP_INI_SCAN_DIR:$BACKEND/docker" "$FPM_BIN" -D -y "$FPM_DIR/php-fpm.conf" -R
  nginx -p "$FPM_DIR" -c "$FPM_DIR/nginx.conf"
  echo "fpm pools: critical=$FPM_CRITICAL_CHILDREN market=$FPM_MARKET_CHILDREN default=$FPM_DEFAULT_CHILDREN;" \
    "market inflight=$NGINX_MARKET_MAX_INFLIGHT rate=${NGINX_MARKET_RATE}r/s burst=$NGINX_MARKET_BURST per client"
}

stop_fpm() {
  [ -f "$FPM_DIR/nginx.pid" ] && kill "$(cat "$FPM_DIR/nginx.pid")" 2>/dev/null
  [ -f "$FPM_DIR/php-fpm.pid" ] && kill "$(cat "$FPM_DIR/php-fpm.pid")" 2>/dev/null
  rm -f "$FPM_DIR/nginx.pid" "$FPM_DIR/php-fpm.pid"
}

case "$1" in
start)
  W=${2:-4}
  mkdir -p $SC/cache && chmod 700 $SC/cache && (cd $BACKEND && php artisan config:cache -q && php artisan route:cache -q && php artisan event:cache -q)
  pgrep -f "redis-server 127.0.0.1:16379" >/dev/null || (cd $SC/logs && redis-server --port 16379 --bind 127.0.0.1 --save '' --appendonly no --daemonize yes --logfile $SC/logs/redis.log)
  redis-cli -p 16379 flushall >/dev/null
  if [ "$SERVER" = fpm ]; then
    stop_fpm; start_fpm
  else
    (cd $BACKEND && PHP_CLI_SERVER_WORKERS=$W exec php artisan serve --host=127.0.0.1 --port=$APP_PORT --no-reload) > $SC/logs/server.log 2>&1 < /dev/null &
  fi
  for i in $(seq 1 40); do curl -s -o /dev/null http://127.0.0.1:$APP_PORT/up && break; sleep 0.25; done
  if [ "$SERVER" = fpm ]; then echo "server (nginx + php-fpm) up on $APP_PORT"; else echo "server up on $APP_PORT with $W workers"; fi;;
stop)
  stop_fpm
  pkill -f "artisan serve --host=127.0.0.1 --port=$APP_PORT"; pkill -f "127.0.0.1:$APP_PORT"; sleep 1; echo stopped;;
esac
