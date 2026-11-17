#!/bin/bash
# m2.sh <label> '<cfg json>'  -- one M2 measurement run in the shipped serving mode (nginx + php-fpm pools, PgBouncer),
# each run from a fresh start: PgBouncer and the server restarted (Redis flushed: cold revision tokens and market read
# model), logs truncated. RT=1 also runs Reverb + queue worker + scheduler (env.sh). Writes into $SCALE_WORK/results:
#   <label>.json/.txt (load.mjs), .pgss.txt, .fpm.txt (logstats.mjs: pools, 304 share, PHP CPU and bytes per endpoint),
#   .pgbouncer.txt, .redis.txt. Waits (up to 3 min) for the 1-min load average to drop under 1.0 before starting.
# Presets (the M2 report): see the `case` below, e.g. ./m2.sh L1_m1mode m1-L1; m1-P / m2-P = the polling fleet only
# (no lunch writes: off-peak); DEBOUNCE_MS (300) = the screens' event debounce in the m2 presets
set -uo pipefail
cd "$(dirname "$0")" || exit 1
LABEL=${1:?label}; CFG=${2:?cfg json or preset}
LUNCH='"lunch":{"gap_s":[40,80],"think_s":[8,20]}'
SPIKE='"customers":250,"think_s":[3,8],"order_pct":2,"start_s":90,"end_s":210,"ramp_s":30'
OLDPOLL='"poll":{"web_s":15}'
LIVEPOLL='"poll":{"till_s":60,"kds_s":15,"display_s":30,"web_s":60}'
FALLPOLL='"poll":{"till_s":15,"kds_s":15,"display_s":5,"web_s":15}'
COND="\"client\":{\"cond\":true,\"unconditional_s\":110,\"debounce_ms\":${DEBOUNCE_MS:-300}"
case $CFG in
  m1-L1) CFG="{$LUNCH,$OLDPOLL}";;
  m1-L3) CFG="{$LUNCH,$OLDPOLL,\"market\":{$SPIKE}}";;
  m1-L4) CFG='{"market":{"customers":250,"think_s":[3,8],"order_pct":2}}';;
  m2-L1) CFG="{$LUNCH,$LIVEPOLL,$COND,\"refresh\":\"${REFRESH:-ws}\"}}";;
  m2-L3) CFG="{$LUNCH,$LIVEPOLL,$COND,\"refresh\":\"${REFRESH:-ws}\"},\"market\":{$SPIKE,\"mode\":\"m2\",\"orders_s\":60}}";;
  m2-L4) CFG='{"client":{"cond":true},"market":{"customers":250,"think_s":[3,8],"order_pct":2,"mode":"m2","orders_s":60}}';;
  m1-P) CFG="{$OLDPOLL}";;
  m2-P) CFG="{$LIVEPOLL,$COND,\"refresh\":\"${REFRESH:-ws}\"}}";;
  m2-L1-down) CFG="{$LUNCH,$FALLPOLL,$COND}}";;
  probe304) CFG='{"probe304":{"reps":40}}';;
esac
export SERVER=fpm DB_POOLER=pgbouncer DB_DATABASE=${DB_DATABASE:-scale_m2}
# shellcheck source=/dev/null
source ./env.sh
R=$SCALE_WORK/results; L=$SCALE_WORK/logs
for _ in $(seq 1 36); do awk '{exit !($1 < 1.0)}' /proc/loadavg && break; sleep 5; done
./server.sh stop >/dev/null 2>&1; ./pgbouncer.sh start >/dev/null && ./server.sh start | tail -2 || exit 1
sleep 3
: > "$L/php-fpm-access.log"; : > "$L/nginx-access.log"; : > "$L/nginx-error.log"
FPMLOG0=$(wc -l < "$L/php-fpm.log" 2>/dev/null || echo 0)
redis-cli -p "$REDIS_PORT" config resetstat >/dev/null
LOAD0=$(cut -d' ' -f1-3 /proc/loadavg)
{ echo "# $LABEL  $(date -u +%FT%TZ)  mode=fpm-pgb RT=${RT:-0} SERVER=fpm DB_POOLER=pgbouncer  load before: $LOAD0"; echo "# cfg $CFG"; echo; } > "$R/$LABEL.txt"
node load.mjs --tokens "$L/tokens.json" --pgss 1 --duration "${DUR:-300}" --label "$LABEL" --out "$R/$LABEL.json" --cfg "$CFG" >> "$R/$LABEL.txt"
echo "load after: $(cut -d' ' -f1-3 /proc/loadavg)" >> "$R/$LABEL.txt"
WALL=$(node -e "console.log(JSON.parse(require('fs').readFileSync('$R/$LABEL.json')).wall_s)")
{ node logstats.mjs "$L/php-fpm-access.log" "$L/nginx-access.log" "$WALL" 33
  echo; echo "== nginx error log: limit hits by zone"; grep -o 'zone "[a-z_]*"' "$L/nginx-error.log" | sort | uniq -c
  echo "== php-fpm log warnings (this run)"; tail -n +"$((FPMLOG0 + 1))" "$L/php-fpm.log" | grep -iE "warn|error|max_children" | sed 's/^\[[^]]*\] //' | sort | uniq -c | head -20
} > "$R/$LABEL.fpm.txt"
./pgbouncer.sh stats > "$R/$LABEL.pgbouncer.txt" 2>&1
{ redis-cli -p "$REDIS_PORT" info stats | grep -E "total_commands|instantaneous|total_net|keyspace"; redis-cli -p "$REDIS_PORT" info memory | grep -E "^used_memory_human|^used_memory_peak_human"
  redis-cli -p "$REDIS_PORT" info commandstats | sort -t= -k2 -rn | head -25; redis-cli -p "$REDIS_PORT" dbsize; } > "$R/$LABEL.redis.txt"
if [ "${RT:-0}" = 1 ]; then { echo "== reverb log (tail)"; tail -5 "$L/reverb.log"; echo "== queue log: jobs"; grep -cE "DONE" "$L/queue.log"; grep -E "FAIL" "$L/queue.log" | awk '{print $NF, $(NF-1), $(NF-2)}' | sort | uniq -c | head; } >> "$R/$LABEL.redis.txt"; fi
./server.sh stop >/dev/null 2>&1; ./pgbouncer.sh stop >/dev/null 2>&1
sed -n 4p "$R/$LABEL.txt" | cut -c1-400
