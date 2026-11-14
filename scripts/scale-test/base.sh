#!/bin/bash
# base.sh: fresh scale_big = migrate + PosStackSeeder + one real POS order and one Mini App order (row templates),
# then a copy `scale_tmp` is kept as the reusable starting point (createdb -T) so profiles can be rebuilt quickly.
set -euo pipefail
source "$(dirname "$0")/env.sh"
$SC/server.sh stop >/dev/null 2>&1 || true
$PSQL -c "DROP DATABASE IF EXISTS scale_big" -c "DROP DATABASE IF EXISTS scale_tmp" -c "CREATE DATABASE scale_big OWNER rizqim"
$PSQL -d scale_big -c "CREATE EXTENSION IF NOT EXISTS pg_stat_statements"
cd $BACKEND && php artisan migrate --force -q && php artisan db:seed --class=PosStackSeeder --force -q
$SC/server.sh start 2
V=$($PSQL -d scale_big -Atc "select string_agg(v.uuid::text, ',') from product_variants v join products p on p.id=v.product_id left join product_modifier_groups g on g.product_id=p.id where p.store_id=1 and not p.batch_tracked and g.id is null and coalesce(v.unit,'')<>'kg' and p.weight_mode is null")
node $SC/template_order.mjs $E2E_POS_SEED_FILE "$V" | tee $SC/logs/template_orders.json
POS_ID=$($PSQL -d scale_big -Atc "select id from orders where channel='pos_takeaway' order by id desc limit 1")
$PSQL -d scale_big -v pos_order=$POS_ID < $SC/sql/templates.sql
$SC/server.sh stop
$PSQL -c "CREATE DATABASE scale_tmp TEMPLATE scale_big OWNER rizqim"
echo "base ready"
