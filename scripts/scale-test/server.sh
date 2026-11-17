#!/bin/bash
# server.sh start [workers] | stop  -- redis (cache, rate limiter, locks) + the app on $APP_PORT
#   EVENT_CACHE=0 skips `event:cache` (listener discovery on every request, as an image whose entrypoint does not cache events)
#   SERVER=serve (default): php artisan serve with PHP_CLI_SERVER_WORKERS=[workers] (one pool, as the first run)
#   SERVER=fpm: nginx + php-fpm with the repo's real config (backend/docker/nginx.conf, php-fpm.conf, pools.sh):
#     the critical / market / default pools, nginx's routing map and the market limits. [workers] is ignored: the
#     pool sizes and limits are the FPM_* / NGINX_* variables of backend/docker/pools.sh (its defaults when unset).
#     Needs nginx (with the realip module) and php8.3-fpm (`apt-get install nginx php8.3-fpm`); the generated
#     configs live in cache/fpm, the logs in logs/. FPM_PORT_BASE (19000) moves the three pool ports (+0 +1 +2).
# shellcheck source=scripts/scale-test/env.sh
source "$(dirname "$0")/env.sh"
SERVER=${SERVER:-serve}
FPM_DIR=$SCALE_WORK/cache/fpm
FPM_BIN=${FPM_BIN:-$(command -v php-fpm8.3 || command -v php-fpm || true)}
# The kit stops ONLY what it started (other stacks on the machine, e.g. the POS e2e's Reverb, run the same commands):
# every background process it starts writes its pid into $PIDS, and a stop kills that pid (and its children) only
# while its command line still holds the expected text (no stale pid of another process). nginx and php-fpm have
# their own pid files in cache/fpm, the kit's Redis is found by its own port, PgBouncer (pgbouncer.sh) by its own config.
PIDS=$SCALE_WORK/cache/pids
killtree() { local c; for c in $(pgrep -P "$1" 2>/dev/null); do killtree "$c"; done; kill "$1" 2>/dev/null; }
# stop_pid <pid file> <text its command line must contain> [master]: the pid and its children (master: the pid only,
# a daemon master such as nginx or php-fpm stops its own workers)
stop_pid() {
  local f=$1 want=$2 how=${3:-tree} pid
  [ -f "$f" ] || return 0
  pid=$(cat "$f" 2>/dev/null); rm -f "$f"
  [[ "$pid" =~ ^[0-9]+$ ]] || return 0
  tr '\0' ' ' < "/proc/$pid/cmdline" 2>/dev/null | grep -qF -- "$want" || return 0
  if [ "$how" = master ]; then kill "$pid" 2>/dev/null; else killtree "$pid"; fi
  return 0
}
# bg <name> <command...>: start in the background (logs/<name>.log), pid into $PIDS/<name>.pid
bg() { local n=$1; shift; mkdir -p "$PIDS"; (cd "$BACKEND" && exec "$@") > "$SCALE_WORK/logs/$n.log" 2>&1 < /dev/null & echo $! > "$PIDS/$n.pid"; }

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
error_log $SCALE_WORK/logs/nginx-error.log warn;
events { worker_connections 4096; }
http {
  include /etc/nginx/mime.types;
  access_log $SCALE_WORK/logs/nginx-access.log;
  client_body_temp_path $FPM_DIR/tmp/body; fastcgi_temp_path $FPM_DIR/tmp/fastcgi; proxy_temp_path $FPM_DIR/tmp/proxy;
  uwsgi_temp_path $FPM_DIR/tmp/uwsgi; scgi_temp_path $FPM_DIR/tmp/scgi;
  include $FPM_DIR/default.conf;
}
EOF
  # The repo's pools; this process's user instead of www-data, logs into logs/
  { echo "[global]"; echo "pid = $FPM_DIR/php-fpm.pid"
    sed -e "$ports" -e '/^user = /d' -e '/^group = /d' -e '/^\[global\]/d' -e '/^daemonize = /d' \
        -e "s#^error_log = .*#error_log = $SCALE_WORK/logs/php-fpm.log#" -e "s#^access.log = .*#access.log = $SCALE_WORK/logs/php-fpm-access.log#" \
        -e 's#^\(access.format = ".*\)"$#\1 cpu=%{total}C"#' \
        "$BACKEND/docker/php-fpm.conf"; } > "$FPM_DIR/php-fpm.conf"
  # backend/docker/php.ini too (upload limits, opcache), as the image's conf.d/zz-rizqim.ini; PROF=1|cpu adds the
  # profiler (php/prof/front.php: per-request CPU, phases, providers, SQL into $PROF_LOG; summary: php/prof/report.php)
  # FPM_KIT_INI=0: php-fpm without the kit's own opcache ini (php/zz-scale-opcache.ini), i.e. only the image's php.ini
  local kit=$PHP_INI_SCAN_DIR; [ "${FPM_KIT_INI:-1}" = 0 ] && kit=""
  # FPM_EXTRA_INI_DIR: one more scan dir after the image's php.ini (try a setting, e.g. JIT or preload, without editing it)
  # docker/php.ini preloads docker/preload.php from the image path as www-data: here from the repo, as this user
  export PHP_OPCACHE_PRELOAD=${PHP_OPCACHE_PRELOAD:-$BACKEND/docker/preload.php} PHP_OPCACHE_PRELOAD_USER=${PHP_OPCACHE_PRELOAD_USER:-$(id -un)}
  local scan="$kit:$BACKEND/docker${FPM_EXTRA_INI_DIR:+:$FPM_EXTRA_INI_DIR}${PROF:+:$SC/php/prof}"
  export PROF_LOG=${PROF_LOG:-$SCALE_WORK/logs/prof.jsonl}
  PHP_INI_SCAN_DIR="$scan" "$FPM_BIN" -t -y "$FPM_DIR/php-fpm.conf" -R 2> "$SCALE_WORK/logs/php-fpm-test.log" \
    || { cat "$SCALE_WORK/logs/php-fpm-test.log" >&2; exit 1; }
  nginx -t -q -p "$FPM_DIR" -c "$FPM_DIR/nginx.conf" || exit 1
  PHP_INI_SCAN_DIR="$scan" "$FPM_BIN" -D -y "$FPM_DIR/php-fpm.conf" -R
  nginx -p "$FPM_DIR" -c "$FPM_DIR/nginx.conf"
  echo "fpm pools: critical=$FPM_CRITICAL_CHILDREN market=$FPM_MARKET_CHILDREN default=$FPM_DEFAULT_CHILDREN;" \
    "market inflight=$NGINX_MARKET_MAX_INFLIGHT rate=${NGINX_MARKET_RATE}r/s burst=$NGINX_MARKET_BURST per client"
}

stop_fpm() {
  stop_pid "$FPM_DIR/nginx.pid" "$FPM_DIR/nginx.conf" master
  stop_pid "$FPM_DIR/php-fpm.pid" "$FPM_DIR/php-fpm.conf" master
}

# RT=1 (env.sh): Reverb + one queue worker + the scheduler, logs in logs/; stopped by `stop` whatever RT says
start_rt() {
  # Their own config cache: straight to Postgres (DB_POOLER=direct, as the compose queue/scheduler/reverb) as role
  # scale_rt (a member of the app role, new password per start), so pg_stat_statements and the backend CPU of the
  # broadcast jobs are told apart from the web requests'. Dropped by `server.sh drop-rt-role` (cleanup).
  local app_user rtpw; app_user=$(grep "^DB_USERNAME=" "$BACKEND/.env" | cut -d= -f2- | tr -d '"'); rtpw=$(od -An -tx1 -N16 /dev/urandom | tr -d ' \n')
  $PSQL -c "DO \$\$ BEGIN IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'scale_rt') THEN CREATE ROLE scale_rt LOGIN IN ROLE $app_user; END IF; END \$\$" \
        -c "ALTER ROLE scale_rt PASSWORD '$rtpw'" >/dev/null
  export APP_CONFIG_CACHE=$SCALE_WORK/cache/config-rt.php
  (cd "$BACKEND" && DB_POOLER=direct DB_HOST=127.0.0.1 DB_PORT=5432 DB_USERNAME=scale_rt DB_PASSWORD=$rtpw php artisan config:cache -q)
  bg reverb php artisan reverb:start --host=127.0.0.1 --port="$REVERB_PORT"
  bg queue php artisan queue:work redis --queue=default,broadcasts --tries=3 --sleep=1
  if [ "${RT_SCHEDULER:-heartbeat}" = full ]; then bg scheduler php artisan schedule:work
  else bg scheduler php "$SC/php/rt/heartbeat.php" schedule:heartbeat; fi
  for _ in $(seq 1 40); do (echo > "/dev/tcp/127.0.0.1/$REVERB_PORT") 2>/dev/null && break; sleep 0.25; done
  echo "real-time on: reverb :$REVERB_PORT, queue worker, scheduler"
}
# the scheduler's schedule:run children go with it (killtree)
stop_rt() { stop_pid "$PIDS/reverb.pid" "reverb:start"; stop_pid "$PIDS/queue.pid" "queue:work"; stop_pid "$PIDS/scheduler.pid" "schedule:"; }

case "$1" in
start)
  W=${2:-4}
  mkdir -p "$SCALE_WORK/cache" && chmod 700 "$SCALE_WORK/cache" && (cd "$BACKEND" && php artisan config:cache -q && php artisan route:cache -q && if [ "${EVENT_CACHE:-1}" = 1 ]; then php artisan event:cache -q; else rm -f "$APP_EVENTS_CACHE"; fi)
  # The kit's own Redis on $REDIS_PORT (env.sh: SCALE_REDIS_PORT): flushed for a clean run, so never point it at a
  # Redis another run or service uses
  pgrep -f "redis-server 127.0.0.1:$REDIS_PORT( |\$)" >/dev/null || (cd "$SCALE_WORK/logs" && redis-server --port "$REDIS_PORT" --bind 127.0.0.1 --save '' --appendonly no --daemonize yes --logfile "$SCALE_WORK/logs/redis.log")
  redis-cli -p "$REDIS_PORT" flushall >/dev/null
  if [ "$SERVER" = fpm ]; then
    stop_fpm; start_fpm
  else
    stop_pid "$PIDS/server.pid" "artisan serve"
    PHP_CLI_SERVER_WORKERS=$W bg server php artisan serve --host=127.0.0.1 --port="$APP_PORT" --no-reload
  fi
  for _ in $(seq 1 40); do curl -s -o /dev/null "http://127.0.0.1:$APP_PORT/up" && break; sleep 0.25; done
  if [ "$SERVER" = fpm ]; then echo "server (nginx + php-fpm) up on $APP_PORT"; else echo "server up on $APP_PORT with $W workers"; fi
  stop_rt; if [ "${RT:-0}" = 1 ]; then start_rt; fi;;
drop-rt-role)
  $PSQL -c "DROP ROLE IF EXISTS scale_rt" && echo "role scale_rt dropped";;
stop)
  stop_rt; stop_fpm; stop_pid "$PIDS/server.pid" "artisan serve"
  # the kit's own Redis, by its own port (it is flushed at every start anyway)
  if pgrep -f "redis-server 127.0.0.1:$REDIS_PORT( |\$)" >/dev/null; then redis-cli -p "$REDIS_PORT" shutdown nosave >/dev/null 2>&1; fi
  sleep 1; echo stopped;;
esac
