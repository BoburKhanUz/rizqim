#!/bin/bash
# run.sh <profile> [phase...]  -- one profile end to end.
#   profiles (cumulative groups of 20 market-only + 1 POS-only + 2 hybrid stores, 30 business days):
#     S = group 1 (~25k orders), M = groups 1-3 (~76k), L = groups 1-11 (~270k)
#   phases (default: all, in this order):
#     base     fresh $DB_DATABASE (default scale_big): migrate + PosStackSeeder + one real POS and one Mini App order (row templates)
#     gen      generate the profile's groups (append), then sizes (bytes/order measured)
#     mint     bulk tokens -> "$SCALE_WORK/logs/tokens.json"
#     probe    single-client latency of every endpoint (S -> M -> L comparison)
#     load     the concurrent scenarios (lunch, +reports, +market spike, sync burst, hub-first) -- meant for L
#     pgbench  DB-only capacity with the captured hot SQL
# Env overrides: APP_PORT, DB_DATABASE, SCALE_WORK, SCALE_REDIS_PORT (env.sh), WORKERS (php -S workers, default 4), DUR (seconds per scenario, default 300).
set -euo pipefail
cd "$(dirname "$0")"; source ./env.sh
P=${1:?profile S|M|L}; shift || true
PHASES=${*:-base gen mint probe load pgbench}
WORKERS=${WORKERS:-4}; DUR=${DUR:-300}
case $P in S) RANGES="1:1";; M) RANGES="1:1 2:3";; L) RANGES="1:1 2:3 4:7 8:11";; *) echo "bad profile"; exit 1;; esac
L() { node load.mjs --tokens "$SCALE_WORK/logs/tokens.json" --pgss 1 --duration "$DUR" --label "$1" --out "$SCALE_WORK/results/$1.json" --cfg "$2" | tee "$SCALE_WORK/results/$1.txt" | head -1; }
for ph in $PHASES; do case $ph in
 base) ./base.sh ;;
 gen)
   have=$($PSQL -d "$DB_DATABASE" -Atc "select coalesce(max(grp),0) from scale_stores" 2>/dev/null || echo 0)
   for r in $RANGES; do g1=${r%:*}; g2=${r#*:}; [ "$g2" -le "$have" ] && continue
     $PSQL -d "$DB_DATABASE" -v g1=$g1 -v g2=$g2 -v days=30 < sql/gen.sql | tail -2; df -h / | tail -1; done
   $PSQL -d "$DB_DATABASE" -v label=$P < sql/sizes.sql > "$SCALE_WORK/results/sizes_$P.txt"
   $PSQL -d "$DB_DATABASE" -Atc "select 'fk-check orphans', (select count(*) from order_items i where not exists (select 1 from orders o where o.id=i.order_id)) + (select count(*) from payments p where p.order_id is not null and not exists (select 1 from orders o where o.id=p.order_id))";;
 mint) $PSQL -d "$DB_DATABASE" -v ncust=2000 < sql/mint.sql > "$SCALE_WORK/logs/tokens.json" ;;
 probe) ./server.sh start "$WORKERS"; L "probe_$P" '{"probe":{"reps":15}}'; ;;
 load)
   ./server.sh start "$WORKERS"
   LUNCH='"lunch":{"gap_s":[40,80],"think_s":[8,20]},"poll":{"web_s":15}'
   L "L1_lunch_poll_$P"        "{$LUNCH}"
   L "L2_lunch_reports_$P"     "{$LUNCH,\"reports\":{\"owners\":12,\"every_s\":30}}"
   L "L3_market_spike_$P"      "{$LUNCH,\"market\":{\"customers\":250,\"think_s\":[3,8],\"order_pct\":2,\"start_s\":90,\"end_s\":210,\"ramp_s\":30}}"
   L "L4_market_only_$P"       '{"market":{"customers":250,"think_s":[3,8],"order_pct":2}}'
   L "L5_sync_burst_$P"        '{"poll":{"web_s":15},"sync":{"tills":40,"ops":32,"at_s":20,"spread_s":60}}'
   L "L6_hub_first_$P"         '{"hub":{"batch_s":30,"orders_per_min":1.5}}'
   ./server.sh stop ;;
 pgbench)
   R=$($PSQL -d "$DB_DATABASE" -Atc "select min(user_id)||' '||max(user_id) from scale_customers"); set -- $R
   B=$($PSQL -d "$DB_DATABASE" -Atc "select min(branch_id)||' '||max(branch_id)||' '||min(store_id)||' '||max(store_id) from scale_stores where kind<>'market'")
   read bmin bmax smin smax <<< "$B"
   for f in read_miniapp read_pos read_report write_pos; do
     PGPASSWORD=$(grep "^DB_PASSWORD=" $BACKEND/.env | cut -d= -f2- | tr -d "\"") pgbench -h 127.0.0.1 -U $(grep "^DB_USERNAME=" $BACKEND/.env | cut -d= -f2-) -n -M prepared -c 8 -j 4 -T 30 -D cust_min=$1 -D cust_max=$2 -D branch_min=$bmin -D branch_max=$bmax \
       -D store_min=$smin -D store_max=$smax -f pgbench/$f.sql "$DB_DATABASE" 2>&1 | grep -E "tps|latency average|failed" | sed "s/^/$f: /"
   done | tee "$SCALE_WORK/results/pgbench_$P${TAG:-}.txt"
   # connection cost: a kept connection vs a new backend per transaction (-C), which is what PHP does per request today
   for C in "" "-C"; do PGPASSWORD=$(grep "^DB_PASSWORD=" $BACKEND/.env | cut -d= -f2- | tr -d '"') pgbench -h 127.0.0.1 -U $(grep "^DB_USERNAME=" $BACKEND/.env | cut -d= -f2-) \
     -n $C -c 8 -j 4 -T 20 -f pgbench/select1.sql "$DB_DATABASE" 2>&1 | grep -E "^tps|latency average|average connection" | sed "s/^/select1 $C: /"; done | tee -a "$SCALE_WORK/results/pgbench_$P${TAG:-}.txt" ;;
esac; done
