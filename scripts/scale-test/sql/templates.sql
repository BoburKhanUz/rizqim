-- Row templates copied from ONE real POS order (store 1, made through the API by probe) and the real fiscal receipt.
-- Run once after the probe order exists:  psql -d scale_big -v pos_order=<id> -f templates.sql
\set ON_ERROR_STOP 1
DROP TABLE IF EXISTS scale_tpl, scale_tpl_audit;
CREATE TABLE scale_tpl AS
SELECT (f.lines -> 0) AS line, f.payments, f.provider_payload,
       (SELECT response_body FROM idempotency_keys WHERE scope = 'pos.orders.open' ORDER BY id DESC LIMIT 1) AS idem_body
FROM fiscal_receipts f ORDER BY f.id LIMIT 1;
CREATE TABLE scale_tpl_audit AS
SELECT 'pos'::text AS kind, (row_number() OVER (ORDER BY id))::int - 1 AS ord, action, payload_json, before, after
FROM audit_logs WHERE model_type = 'App\Models\Order' AND model_id = :pos_order;
INSERT INTO scale_tpl_audit SELECT 'mkt', n - 1, action, payload_json, before, after
FROM (SELECT * FROM scale_tpl_audit WHERE action = 'order.status_changed' LIMIT 1) a CROSS JOIN generate_series(1,3) n;
SELECT kind, count(*) FROM scale_tpl_audit GROUP BY 1;
