#!/bin/bash
# base.sh: fresh $DB_DATABASE (env.sh, default scale_big) = migrate + PosStackSeeder + one real POS order and one Mini App order (row templates),
# then a copy `${DB_DATABASE}_tmp` is kept as the reusable starting point (createdb -T) so profiles can be rebuilt quickly.
set -euo pipefail
source "$(dirname "$0")/env.sh"
$SC/server.sh stop >/dev/null 2>&1 || true
$PSQL -c "DROP DATABASE IF EXISTS \"$DB_DATABASE\"" -c "DROP DATABASE IF EXISTS \"${DB_DATABASE}_tmp\"" -c "CREATE DATABASE \"$DB_DATABASE\" OWNER rizqim"
$PSQL -d "$DB_DATABASE" -c "CREATE EXTENSION IF NOT EXISTS pg_stat_statements"
# the seeder's orders read the cache (CACHE_STORE=redis): the kit's own Redis must be up before seeding
pgrep -f "redis-server 127.0.0.1:$REDIS_PORT( |\$)" >/dev/null || (cd "$SCALE_WORK/logs" && redis-server --port "$REDIS_PORT" --bind 127.0.0.1 --save '' --appendonly no --daemonize yes --logfile "$SCALE_WORK/logs/redis.log")
cd $BACKEND && php artisan migrate --force -q && php artisan db:seed --class=PosStackSeeder --force -q
$SC/server.sh start 2
V=$($PSQL -d "$DB_DATABASE" -Atc "select string_agg(v.uuid::text, ',') from product_variants v join products p on p.id=v.product_id left join product_modifier_groups g on g.product_id=p.id where p.store_id=1 and not p.batch_tracked and g.id is null and coalesce(v.unit,'')<>'kg' and p.weight_mode is null")
node $SC/template_order.mjs $E2E_POS_SEED_FILE "$V" | tee $SCALE_WORK/logs/template_orders.json
POS_ID=$($PSQL -d "$DB_DATABASE" -Atc "select id from orders where channel='pos_takeaway' order by id desc limit 1")
$PSQL -d "$DB_DATABASE" -v pos_order=$POS_ID < $SC/sql/templates.sql
$SC/server.sh stop
$PSQL -c "CREATE DATABASE \"${DB_DATABASE}_tmp\" TEMPLATE \"$DB_DATABASE\" OWNER rizqim"
echo "base ready"
