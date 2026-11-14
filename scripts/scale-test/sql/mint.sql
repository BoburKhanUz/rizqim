-- Bulk auth for the driver, minted by SQL exactly as the app stores them (Sanctum: token = sha256(plain) hex, the client
-- sends "<id>|<plain>"; tokenable_type is the model class, no morph map):
--   tills   : device token (App\Models\Device, name 'terminal', abilities ["terminal"]) + a cashier PIN session token
--             (App\Models\User, name 'staff-session', abilities ["staff-session"], device_id = till, 12 h) + the
--             'staff.session_started' audit row that StaffPinService writes (PosSyncApplier needs it for offline ops)
--   KDS     : device token + cook PIN session;  display: device token;  owner: panel token ["*"];
--   customers: Mini App token ["*"] for :ncust customers.
--   psql -d scale_big -v ncust=2000 -f mint.sql   (re-runnable: previous scale tokens are deleted)
\set ON_ERROR_STOP 1
BEGIN;
CREATE TABLE IF NOT EXISTS scale_tokens (pat_id bigint, kind text, store_id bigint, user_id bigint, device_id bigint, plain text);
DELETE FROM personal_access_tokens WHERE name LIKE 'scale-%' OR id IN (SELECT pat_id FROM scale_tokens);
TRUNCATE scale_tokens;
CREATE TEMP TABLE want AS
SELECT 'till_dev' kind, s.store_id, NULL::bigint user_id, t.d device_id, t.i FROM scale_stores s, unnest(s.till_ids) WITH ORDINALITY t(d,i)
UNION ALL SELECT 'till_staff', s.store_id, s.cashier_ids[t.i], t.d, t.i FROM scale_stores s, unnest(s.till_ids) WITH ORDINALITY t(d,i)
UNION ALL SELECT 'kds_dev', store_id, NULL, kds_id, 1 FROM scale_stores WHERE kds_id IS NOT NULL
UNION ALL SELECT 'kds_staff', store_id, cook_id, kds_id, 1 FROM scale_stores WHERE kds_id IS NOT NULL
UNION ALL SELECT 'display_dev', store_id, NULL, display_id, 1 FROM scale_stores WHERE display_id IS NOT NULL
UNION ALL SELECT 'owner', store_id, owner_id, NULL, 1 FROM scale_stores
UNION ALL SELECT 'customer', NULL, user_id, NULL, 1 FROM (SELECT user_id FROM scale_customers ORDER BY user_id LIMIT :ncust) c;
INSERT INTO scale_tokens SELECT nextval('personal_access_tokens_id_seq'), kind, store_id, user_id, device_id,
       md5(random()::text || clock_timestamp()::text) || substr(md5(random()::text), 1, 8) FROM want;
INSERT INTO personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, expires_at, created_at, updated_at, device_id)
SELECT pat_id,
       CASE WHEN kind LIKE '%_dev' THEN 'App\Models\Device' ELSE 'App\Models\User' END,
       coalesce(user_id, device_id),
       CASE WHEN kind LIKE '%_dev' THEN 'terminal' WHEN kind LIKE '%_staff' THEN 'staff-session' ELSE 'scale-' || kind END,
       encode(sha256(plain::bytea), 'hex'),
       CASE WHEN kind LIKE '%_dev' THEN '["terminal"]' WHEN kind LIKE '%_staff' THEN '["staff-session"]' ELSE '["*"]' END,
       CASE WHEN kind LIKE '%_dev' THEN now() + interval '365 days' WHEN kind LIKE '%_staff' THEN now() + interval '12 hours' END,
       now(), now(),
       CASE WHEN kind LIKE '%_dev' OR kind LIKE '%_staff' THEN device_id END
FROM scale_tokens;
INSERT INTO audit_logs (user_id, action, model_type, model_id, payload_json, created_at, store_id, branch_id, device_id)
SELECT t.user_id, 'staff.session_started', 'App\Models\User', t.user_id, '{"staff_name":"Kassir","role":"cashier"}'::json, now(), t.store_id, s.branch_id, t.device_id
FROM scale_tokens t JOIN scale_stores s USING (store_id) WHERE t.kind LIKE '%_staff';
COMMIT;
\pset tuples_only on
\pset format unaligned
SELECT json_build_object(
  'stores', (SELECT json_agg(json_build_object(
      'store_id', s.store_id, 'store_uuid', st.uuid, 'branch_id', s.branch_id, 'kind', s.kind, 'grp', s.grp, 'lat', s.lat, 'lng', s.lng,
      'pos_per_day', s.pos_per_day, 'mkt_per_day', s.mkt_per_day, 'variants', s.variant_uuids[1:40], 'prices', (SELECT array_agg(v.price ORDER BY x.i) FROM unnest(s.variant_ids[1:40]) WITH ORDINALITY x(id, i) JOIN product_variants v ON v.id = x.id), 'table_ids', s.table_ids,
      'owner', (SELECT pat_id || '|' || plain FROM scale_tokens WHERE kind = 'owner' AND store_id = s.store_id),
      'tills', (SELECT json_agg(json_build_object('device_uuid', d.uuid, 'device_id', d.id, 'staff_id', ts.user_id,
                      'device_token', td.pat_id || '|' || td.plain, 'staff_token', ts.pat_id || '|' || ts.plain) ORDER BY d.id)
                FROM scale_tokens td JOIN scale_tokens ts ON ts.device_id = td.device_id AND ts.kind = 'till_staff' JOIN devices d ON d.id = td.device_id
                WHERE td.kind = 'till_dev' AND td.store_id = s.store_id),
      'kds', (SELECT pat_id || '|' || plain FROM scale_tokens WHERE kind = 'kds_staff' AND store_id = s.store_id),
      'display', (SELECT pat_id || '|' || plain FROM scale_tokens WHERE kind = 'display_dev' AND store_id = s.store_id)
    ) ORDER BY s.store_id) FROM scale_stores s JOIN stores st ON st.id = s.store_id),
  'customers', (SELECT json_agg(pat_id || '|' || plain) FROM scale_tokens WHERE kind = 'customer'));
