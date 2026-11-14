#!/bin/bash
# server.sh start [workers] | stop  -- redis (cache, rate limiter, locks) + php artisan serve on $APP_PORT
source "$(dirname "$0")/env.sh"
case "$1" in
start)
  W=${2:-4}
  mkdir -p $SC/cache && chmod 700 $SC/cache && (cd $BACKEND && php artisan config:cache -q && php artisan route:cache -q && php artisan event:cache -q)
  pgrep -f "redis-server 127.0.0.1:16379" >/dev/null || (cd $SC/logs && redis-server --port 16379 --bind 127.0.0.1 --save '' --appendonly no --daemonize yes --logfile $SC/logs/redis.log)
  redis-cli -p 16379 flushall >/dev/null
  (cd $BACKEND && PHP_CLI_SERVER_WORKERS=$W exec php artisan serve --host=127.0.0.1 --port=$APP_PORT --no-reload) > $SC/logs/server.log 2>&1 < /dev/null &
  for i in $(seq 1 40); do curl -s -o /dev/null http://127.0.0.1:$APP_PORT/up && break; sleep 0.25; done
  echo "server up on $APP_PORT with $W workers";;
stop)
  pkill -f "artisan serve --host=127.0.0.1 --port=$APP_PORT"; pkill -f "127.0.0.1:$APP_PORT"; sleep 1; echo stopped;;
esac
