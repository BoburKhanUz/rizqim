-- Index experiment on scale_big only (plain SQL; CONCURRENTLY so it can run under load).  psql -d scale_big < indexes.sql
-- Missing indexes the plan lists (DB_SCALE_PLAN 1) that the L profile showed on hot paths, plus the ones found here.
\set ON_ERROR_STOP 1
\timing on
-- 1 Mini App / partner notification feed + badge (NotificationFeedService): was Index Scan Backward on the PK filtering 833k rows
CREATE INDEX CONCURRENTLY IF NOT EXISTS notification_logs_user_id_id_index ON notification_logs (user_id, id);
-- 2 menu load for every till and the marketplace: product_variants by product_id, products by store (seq scans today)
CREATE INDEX CONCURRENTLY IF NOT EXISTS product_variants_product_id_index ON product_variants (product_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS products_store_id_status_index ON products (store_id, status);
-- 3 ShiftReport (GET /pos/shifts/current, X report): voided lines of the branch -> read the branch's whole item history
CREATE INDEX CONCURRENTLY IF NOT EXISTS order_items_voided_at_partial_index ON order_items (voided_at) WHERE voided_at IS NOT NULL;
-- 4 FK hygiene on hot children (payments by order on close/refund; notification rows by order)
CREATE INDEX CONCURRENTLY IF NOT EXISTS payments_order_id_index ON payments (order_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS notification_logs_order_id_index ON notification_logs (order_id);
-- 5 redundant: exact duplicate of audit_logs_model_index; prefix of order_items_order_id_client_line_id_index
DROP INDEX CONCURRENTLY IF EXISTS audit_logs_model_type_model_id_index;
DROP INDEX CONCURRENTLY IF EXISTS order_items_order_id_index;
ANALYZE notification_logs; ANALYZE product_variants; ANALYZE products; ANALYZE order_items; ANALYZE payments; ANALYZE audit_logs;
SELECT indexrelname, pg_size_pretty(pg_relation_size(indexrelid)) FROM pg_stat_user_indexes
WHERE indexrelname IN ('notification_logs_user_id_id_index','product_variants_product_id_index','products_store_id_status_index',
  'order_items_voided_at_partial_index','payments_order_id_index','notification_logs_order_id_index');
