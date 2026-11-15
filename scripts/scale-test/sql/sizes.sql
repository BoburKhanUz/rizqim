-- Size snapshot + MEASURED bytes per order (per store channel).  psql -d scale_big -v label=S < sizes.sql
-- bytes/order(type) = sum over order tables of (table total size incl. indexes & TOAST) x (share of that table's rows
-- that belong to orders of the type) / orders of the type. Row shares are counted, sizes are pg_total_relation_size.
\set ON_ERROR_STOP 1
CREATE TABLE IF NOT EXISTS scale_size_snap(label text, relname text, total bigint, heap bigint, idx bigint, rows bigint, at timestamptz default now());
VACUUM ANALYZE;
DELETE FROM scale_size_snap WHERE label = :'label';
INSERT INTO scale_size_snap(label, relname, total, heap, idx, rows)
SELECT :'label', relname, pg_total_relation_size(relid), pg_relation_size(relid), pg_indexes_size(relid), n_live_tup FROM pg_stat_user_tables;
\echo == database size, top 15 tables
SELECT pg_size_pretty(pg_database_size(current_database())) AS db_size, (SELECT count(*) FROM orders) AS orders;
SELECT relname, pg_size_pretty(total) total, pg_size_pretty(heap) heap, pg_size_pretty(idx) idx, rows
FROM scale_size_snap WHERE label = :'label' ORDER BY scale_size_snap.total DESC LIMIT 15;
\echo == top 15 indexes
SELECT indexrelname, relname, pg_size_pretty(pg_relation_size(indexrelid)) sz, idx_scan FROM pg_stat_user_indexes ORDER BY pg_relation_size(indexrelid) DESC LIMIT 15;
\echo == measured bytes per order by channel (pos = POS order of a POS/hybrid store; mkt = Mini App order)
WITH k AS (SELECT order_id, ch FROM scale_orders_kind),
cnt AS (
  SELECT 'orders' t, k.ch, count(*) n FROM orders o JOIN k ON k.order_id = o.id GROUP BY 2
  UNION ALL SELECT 'order_items', k.ch, count(*) FROM order_items x JOIN k ON k.order_id = x.order_id GROUP BY 2
  UNION ALL SELECT 'order_status_histories', k.ch, count(*) FROM order_status_histories x JOIN k ON k.order_id = x.order_id GROUP BY 2
  UNION ALL SELECT 'order_bills', k.ch, count(*) FROM order_bills x JOIN k ON k.order_id = x.order_id GROUP BY 2
  UNION ALL SELECT 'order_bill_items', k.ch, count(*) FROM order_bill_items x JOIN order_bills b ON b.id = x.bill_id JOIN k ON k.order_id = b.order_id GROUP BY 2
  UNION ALL SELECT 'payments', k.ch, count(*) FROM payments x JOIN k ON k.order_id = x.order_id GROUP BY 2
  UNION ALL SELECT 'fiscal_receipts', k.ch, count(*) FROM fiscal_receipts x JOIN order_bills b ON b.id = x.subject_id AND x.subject_type = 'order_bill' JOIN k ON k.order_id = b.order_id GROUP BY 2
  UNION ALL SELECT 'audit_logs', k.ch, count(*) FROM audit_logs x JOIN k ON k.order_id = x.model_id AND x.model_type = 'App\Models\Order' GROUP BY 2
  UNION ALL SELECT 'notification_logs', k.ch, count(*) FROM notification_logs x JOIN k ON k.order_id = x.order_id GROUP BY 2
  UNION ALL SELECT 'pos_sync_operations', k.ch, count(*) FROM pos_sync_operations x JOIN k ON k.order_id = x.order_id GROUP BY 2
),
tot AS (SELECT t, sum(n) n FROM cnt GROUP BY 1),
sz AS (SELECT relname t, total, rows FROM scale_size_snap WHERE label = :'label'),
per AS (SELECT c.t, c.ch, c.n, sz.total * c.n / NULLIF(tot.n, 0) AS bytes FROM cnt c JOIN tot USING (t) JOIN sz USING (t)),
ords AS (SELECT ch, count(*) n FROM scale_orders_kind GROUP BY 1)
SELECT per.t AS "table", per.ch, round(per.n::numeric / ords.n, 2) AS rows_per_order, round(per.bytes / ords.n) AS bytes_per_order
FROM per JOIN ords USING (ch) ORDER BY ch, bytes_per_order DESC;
WITH k AS (SELECT ch, count(*) n FROM scale_orders_kind GROUP BY 1)
SELECT 'idempotency_keys (24 h window only)' AS note, pg_size_pretty(pg_total_relation_size('idempotency_keys')) sz, (SELECT count(*) FROM idempotency_keys) n,
       round(pg_total_relation_size('idempotency_keys')::numeric / NULLIF((SELECT count(*) FROM idempotency_keys), 0)) bytes_per_key;
