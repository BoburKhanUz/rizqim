-- RIZQIM scale test: data generator (append mode).
--   psql -d scale_big -v g1=1 -v g2=1 -v days=30 -f gen.sql
-- Generates store groups g1..g2. One group = 20 market-only stores + 1 POS-only + 2 hybrid (the assumed 20:3 mix),
-- with `days` business days of history ending yesterday, FK-correct, using row shapes copied from ONE real POS order
-- and ONE real Mini App order made through the API on the seeded template (store 1, see templates.sql).
-- Requires: migrate + PosStackSeeder + templates.sql already run on this database.
\set ON_ERROR_STOP 1
BEGIN;
SET LOCAL synchronous_commit = off;
SET maintenance_work_mem = '256MB';
SET work_mem = '64MB';
SELECT setseed(0.42 + :g1 / 1000.0);
SELECT set_config('scale.g1', :'g1', false), set_config('scale.g2', :'g2', false);

CREATE TABLE IF NOT EXISTS scale_stores (
  store_id bigint PRIMARY KEY, branch_id bigint, kind text, grp int, rnk int,
  pos_per_day int, mkt_per_day int, lat numeric, lng numeric,
  owner_id bigint, manager_id bigint, cook_id bigint, cashier_ids bigint[], till_ids bigint[], kds_id bigint, display_id bigint,
  variant_ids bigint[], variant_uuids uuid[], hall_id bigint, table_ids bigint[], station_ids bigint[], staff_count int);
CREATE TABLE IF NOT EXISTS scale_customers (user_id bigint PRIMARY KEY, grp int);

-- Global categories shared by all generated menus (categories have no store_id in this schema)
INSERT INTO categories (uuid, name, slug, is_active, sort_order, created_at, updated_at)
SELECT gen_random_uuid(), 'Scale '||c, 'scale-cat-'||c, true, c, now(), now() FROM generate_series(1,12) c
ON CONFLICT DO NOTHING;

-- ---------------------------------------------------------------- tenants
DO $$
DECLARE
  g int; k int; kind text; sid bigint; bid bigint; uid bigint; n_prod int; n_till int; ppd int; mpd int;
  cats bigint[] := ARRAY(SELECT id FROM categories WHERE slug LIKE 'scale-cat-%' ORDER BY id);
  st bigint[]; tills bigint[]; cashiers bigint[]; hall bigint; tabs bigint[]; v_ids bigint[]; v_uuids uuid[];
  owner bigint; mgr bigint; cook bigint; kds bigint; disp bigint; lat numeric; lng numeric; nstaff int;
  g1 int := current_setting('scale.g1')::int; g2 int := current_setting('scale.g2')::int;
BEGIN
 FOR g IN g1..g2 LOOP
  FOR k IN 1..23 LOOP
   kind := CASE WHEN k <= 20 THEN 'market' WHEN k = 21 THEN 'pos' ELSE 'hybrid' END;
   ppd := CASE k WHEN 21 THEN 150 WHEN 22 THEN 180 WHEN 23 THEN 120 ELSE 0 END;
   ppd := round(ppd * (0.85 + random()*0.3));
   mpd := CASE WHEN k <= 20 THEN 10 + round(20 / sqrt(k)) WHEN k = 22 THEN 25 WHEN k = 23 THEN 15 ELSE 0 END;
   lat := 41.22 + random()*0.18; lng := 69.13 + random()*0.27;
   INSERT INTO stores (uuid, name, slug, is_active, timezone, currency, language, theme_color, payment_settings, commission_rate,
       is_open, open_time, close_time, min_order_amount, status, payment_account_type, settlement_type, accept_cash,
       credit_limit, min_payout_reserve, tin, tax_regime, fiscal_settings, created_at, updated_at)
   SELECT gen_random_uuid(), 'Scale '||kind||' g'||g||'-'||k, 'scale-'||g||'-'||k, true, timezone, currency, language, theme_color,
       payment_settings, commission_rate, true, '00:00:00', '00:00:00', 0, 'active', payment_account_type, settlement_type, true,
       credit_limit, min_payout_reserve, (400000000 + g*100 + k)::text, 'vat',
       CASE WHEN kind = 'market' THEN NULL ELSE jsonb_build_object('enabled', true, 'driver', 'sandbox') END, now() - interval '120 days', now()
   FROM stores WHERE id = 1 RETURNING id INTO sid;
   INSERT INTO branches (store_id, name, latitude, longitude, radius_km, is_active, status, business_day_starts_at, created_at, updated_at)
   VALUES (sid, 'Filial '||g||'-'||k, lat, lng, 8, true, 'active', '05:00:00', now(), now()) RETURNING id INTO bid;
   -- staff (password/pin hashes copied from the seeded cashier; tokens are minted later, PINs are not used)
   INSERT INTO users (uuid, store_id, branch_id, name, password, phone, role, created_at, updated_at)
   VALUES (gen_random_uuid(), sid, NULL, 'Owner '||sid, (SELECT password FROM users WHERE id=3), '+99870'||lpad(sid::text,7,'0'), 'owner', now(), now()) RETURNING id INTO owner;
   INSERT INTO users (uuid, store_id, branch_id, name, password, phone, role, created_at, updated_at)
   VALUES (gen_random_uuid(), sid, bid, 'Manager '||sid, (SELECT password FROM users WHERE id=3), '+99871'||lpad(sid::text,7,'0'), 'manager', now(), now()) RETURNING id INTO mgr;
   n_prod := CASE WHEN kind = 'market' THEN 30 + floor(random()*30) ELSE 60 + floor(random()*60) END;
   INSERT INTO products (uuid, store_id, category_id, name, slug, status, type, kind, mxik_code, batch_tracked, quick_add, created_at, updated_at)
   SELECT gen_random_uuid(), sid, cats[1 + (p % 12)], 'Taom '||p, 'scale-'||sid||'-'||p, 'active', 'physical', 'dish',
          '1020200100'||lpad((p%9999)::text,7,'0'), false, false, now(), now()
   FROM generate_series(1, n_prod) p;
   INSERT INTO product_variants (uuid, product_id, name, price, stock, is_active, unit, created_at, updated_at)
   SELECT gen_random_uuid(), p.id, 'Porsiya', (5 + floor(random()*60)) * 1000, 100000, true, 'piece', now(), now()
   FROM products p WHERE p.store_id = sid;
   SELECT array_agg(v.id ORDER BY v.id), array_agg(v.uuid ORDER BY v.id) INTO v_ids, v_uuids
   FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.store_id = sid;
   cashiers := NULL; tills := NULL; cook := NULL; kds := NULL; disp := NULL; hall := NULL; tabs := NULL; st := NULL; nstaff := 2;
   IF kind <> 'market' THEN
     n_till := CASE WHEN ppd > 165 THEN 4 WHEN ppd > 135 THEN 3 ELSE 2 END;
     INSERT INTO users (uuid, store_id, branch_id, name, password, phone, role, staff_code, created_at, updated_at)
     SELECT gen_random_uuid(), sid, bid, 'Kassir '||sid||'-'||t, (SELECT password FROM users WHERE id=3), '+9987'||(2+t)||lpad(sid::text,7,'0'), 'cashier', (1000+t)::text, now(), now()
     FROM generate_series(1, n_till) t;
     SELECT array_agg(id ORDER BY id) INTO cashiers FROM users WHERE store_id = sid AND role = 'cashier';
     INSERT INTO users (uuid, store_id, branch_id, name, password, phone, role, staff_code, created_at, updated_at)
     VALUES (gen_random_uuid(), sid, bid, 'Oshpaz '||sid, (SELECT password FROM users WHERE id=3), '+99879'||lpad(sid::text,7,'0'), 'cook', '2000', now(), now()) RETURNING id INTO cook;
     INSERT INTO users (uuid, store_id, branch_id, name, password, phone, role, staff_code, created_at, updated_at)
     SELECT gen_random_uuid(), sid, bid, 'Ofitsiant '||sid||'-'||w, (SELECT password FROM users WHERE id=3), '+9988'||w||lpad(sid::text,7,'0'), 'waiter', (3000+w)::text, now(), now()
     FROM generate_series(1, 2) w;
     nstaff := 2 + n_till + 3;
     INSERT INTO devices (uuid, store_id, branch_id, type, name, platform, paired_by, sync_prefix, sync_last_seq, sync_pending_ops, smart_kassa, created_at, updated_at)
     SELECT gen_random_uuid(), sid, bid, 'pos', 'Kassa '||t, 'android', owner, 'K'||t, 0, 0, false, now(), now() FROM generate_series(1, n_till) t;
     SELECT array_agg(id ORDER BY id) INTO tills FROM devices WHERE store_id = sid AND type = 'pos';
     INSERT INTO devices (uuid, store_id, branch_id, type, name, platform, paired_by, sync_last_seq, sync_pending_ops, smart_kassa, created_at, updated_at)
     VALUES (gen_random_uuid(), sid, bid, 'kds', 'Oshxona', 'android', owner, 0, 0, false, now(), now()) RETURNING id INTO kds;
     INSERT INTO devices (uuid, store_id, branch_id, type, name, platform, paired_by, sync_last_seq, sync_pending_ops, smart_kassa, display_for_device_id, created_at, updated_at)
     VALUES (gen_random_uuid(), sid, bid, 'display', 'Ekran', 'android', owner, 0, 0, false, tills[1], now(), now()) RETURNING id INTO disp;
     INSERT INTO kitchen_stations (store_id, branch_id, name, kind, sort_order, is_active, show_on_kds, created_at, updated_at)
     VALUES (sid, bid, 'Issiq', 'kitchen', 1, true, true, now(), now()), (sid, bid, 'Sovuq', 'salad_bar', 2, true, true, now(), now());
     SELECT array_agg(id ORDER BY id) INTO st FROM kitchen_stations WHERE branch_id = bid;
     INSERT INTO kitchen_station_routes (store_id, branch_id, station_id, category_id, created_at, updated_at)
     SELECT sid, bid, st[1 + (c % 2)], cats[c], now(), now() FROM generate_series(1, 12) c;
     INSERT INTO halls (store_id, branch_id, name, sort_order, is_active, created_at, updated_at) VALUES (sid, bid, 'Zal', 1, true, now(), now()) RETURNING id INTO hall;
     INSERT INTO dining_tables (store_id, branch_id, hall_id, name, kind, seats, sort_order, is_active, created_at, updated_at)
     SELECT sid, bid, hall, 'Stol '||t, 'table', 4, t, true, now(), now() FROM generate_series(1, 15) t;
     SELECT array_agg(id ORDER BY id) INTO tabs FROM dining_tables WHERE branch_id = bid;
   END IF;
   INSERT INTO scale_stores VALUES (sid, bid, kind, g, k, ppd, mpd, lat, lng, owner, mgr, cook, cashiers, tills, kds, disp, v_ids, v_uuids, hall, tabs, st, nstaff);
  END LOOP;
  -- customers of the marketplace: 1 500 per group
  WITH c AS (
    INSERT INTO users (uuid, name, password, phone, role, telegram_id, created_at, updated_at)
    SELECT gen_random_uuid(), 'Mijoz '||g||'-'||i, (SELECT password FROM users WHERE id=7), '+99890'||lpad((g*10000+i)::text,7,'0'), 'customer', (800000000 + g*10000 + i)::text, now() - interval '100 days', now()
    FROM generate_series(1, 1500) i RETURNING id)
  INSERT INTO scale_customers SELECT id, g FROM c;
 END LOOP;
END $$;

-- ---------------------------------------------------------------- shifts (one closed shift per till per day)
CREATE TEMP TABLE sh AS
SELECT nextval('pos_shifts_id_seq') AS id, s.store_id, s.branch_id, t.till, t.i AS till_no, d, (current_date - d) AS bdate
FROM scale_stores s CROSS JOIN LATERAL unnest(s.till_ids) WITH ORDINALITY t(till, i) CROSS JOIN generate_series(1, :days) d
WHERE s.grp BETWEEN :g1 AND :g2 AND s.kind <> 'market';
INSERT INTO pos_shifts (id, uuid, store_id, branch_id, device_id, business_date, status, opened_by, opened_at, opening_cash, closed_by, closed_at,
                        expected_cash, counted_cash, difference, z_number, force_closed, summary, created_at, updated_at)
SELECT sh.id, gen_random_uuid(), sh.store_id, sh.branch_id, sh.till, sh.bdate, 'closed', s.cashier_ids[sh.till_no], sh.bdate + time '03:00', 100000,
       s.cashier_ids[sh.till_no], sh.bdate + time '18:00', 1000000, 1000000, 0, sh.id, false,
       '{"orders":120,"cash":"1000000.00","card":"500000.00"}'::json, sh.bdate + time '03:00', sh.bdate + time '18:00'
FROM sh JOIN scale_stores s USING (store_id);

-- ---------------------------------------------------------------- orders plan
-- times are stored in UTC (Tashkent = UTC+5); local hour picked from a lunch/dinner-humped distribution
CREATE TEMP TABLE o AS
SELECT nextval('orders_id_seq') AS id, s.store_id, s.branch_id, s.kind, ch, d, (current_date - d) AS bdate,
       ((current_date - d) + make_interval(hours => (CASE WHEN ch = 'pos'
            THEN (ARRAY[9,10,11,12,12,12,12,13,13,13,13,14,14,15,16,17,18,19,19,19,20,20,21,22])[1 + floor(random()*24)::int]
            ELSE (ARRAY[10,11,12,12,13,13,14,15,16,17,18,19,19,20,20,20,21,21,22,23])[1 + floor(random()*20)::int] END) - 5,
            mins => floor(random()*60)::int, secs => floor(random()*60)::int)) AS created_at,
       CASE WHEN ch = 'pos' THEN 1 + floor(random()*4)::int + floor(random()*4)::int ELSE 1 + floor(random()*3)::int + floor(random()*2)::int END AS n_items,
       random() AS r1, random() AS r2, random() AS r3,
       1 + floor(random() * coalesce(array_length(s.till_ids,1),1))::int AS till_no
FROM scale_stores s
CROSS JOIN LATERAL (VALUES ('pos'), ('mkt')) c(ch)
CROSS JOIN generate_series(1, :days) d
CROSS JOIN LATERAL generate_series(1, round((CASE WHEN ch = 'pos' THEN s.pos_per_day ELSE s.mkt_per_day END) * (0.75 + random()*0.5))::int) k
WHERE s.grp BETWEEN :g1 AND :g2 AND (CASE WHEN ch = 'pos' THEN s.pos_per_day ELSE s.mkt_per_day END) > 0;
ALTER TABLE o ADD PRIMARY KEY (id);
ANALYZE o;

-- items plan
CREATE TEMP TABLE oi AS
SELECT nextval('order_items_id_seq') AS id, o.id AS order_id, o.ch, i AS line_no,
       s.variant_ids[1 + floor(random()*array_length(s.variant_ids,1))::int] AS variant_id,
       CASE WHEN random() < 0.2 THEN 2 ELSE 1 END AS qty, o.created_at, s.station_ids
FROM o JOIN scale_stores s USING (store_id) CROSS JOIN LATERAL generate_series(1, o.n_items) i;
CREATE TEMP TABLE oi2 AS
SELECT oi.*, v.price AS unit_price, p.name AS pname FROM oi JOIN product_variants v ON v.id = oi.variant_id JOIN products p ON p.id = v.product_id;
DROP TABLE oi;
CREATE INDEX ON oi2 (order_id); ANALYZE oi2;
CREATE TEMP TABLE ot AS SELECT order_id, sum(unit_price*qty) AS total FROM oi2 GROUP BY 1;

CREATE TEMP TABLE cust AS SELECT array_agg(user_id ORDER BY user_id) AS a FROM scale_customers;
INSERT INTO orders (id, uuid, short_code, store_id, user_id, status, payment_status, order_type, subtotal, delivery_price, total_price, currency,
    shipping_address, created_at, updated_at, payment_method, commission_amount, branch_id, payment_owner, discount, settlement_attempts, channel,
    service_charge_amount, place_fee_amount, guests_count, hall_id, table_id, device_id, version, room_fee_amount, min_spend_topup_amount,
    shift_id, business_date, queue_number, closed_at, cancel_reason, fiscalized_by, packaging_amount, banquet_amount, synced_at, offline_created_at, external_id)
SELECT o.id, gen_random_uuid(), 'G'||o.id, o.store_id,
       CASE WHEN o.ch = 'pos' THEN s.cashier_ids[o.till_no]
            ELSE (SELECT a[1 + floor(o.r2 * array_length(a, 1))::int] FROM cust) END,
       CASE WHEN o.r1 < 0.05 THEN 'canceled' ELSE 'delivered' END,
       CASE WHEN o.r1 < 0.05 THEN 'pending' ELSE 'paid' END,
       CASE WHEN o.ch = 'pos' THEN (CASE WHEN o.r3 < 0.6 THEN 'dine_in' ELSE 'pickup' END) ELSE (CASE WHEN o.r3 < 0.7 THEN 'delivery' ELSE 'pickup' END) END,
       ot.total, CASE WHEN o.ch = 'mkt' AND o.r3 < 0.7 THEN 10000 ELSE 0 END, ot.total + CASE WHEN o.ch = 'mkt' AND o.r3 < 0.7 THEN 10000 ELSE 0 END, 'UZS',
       CASE WHEN o.ch = 'mkt' THEN jsonb_build_object('address', 'Chilonzor 9-kvartal, 12-uy', 'latitude', s.lat + 0.01, 'longitude', s.lng - 0.01) END,
       o.created_at, o.created_at + interval '35 minutes',
       CASE WHEN o.ch = 'pos' THEN 'cash' WHEN o.r2 < 0.6 THEN 'cash' ELSE 'click' END,
       CASE WHEN o.ch = 'mkt' THEN round(ot.total * 0.10, 2) ELSE 0 END, o.branch_id, 'STORE', 0, 0,
       CASE WHEN o.ch = 'mkt' THEN 'telegram' WHEN o.r3 < 0.6 THEN 'pos_hall' ELSE 'pos_takeaway' END,
       0, 0, CASE WHEN o.ch = 'pos' AND o.r3 < 0.6 THEN 2 END,
       CASE WHEN o.ch = 'pos' AND o.r3 < 0.6 THEN s.hall_id END,
       CASE WHEN o.ch = 'pos' AND o.r3 < 0.6 THEN s.table_ids[1 + (o.id % 15)::int] END,
       CASE WHEN o.ch = 'pos' THEN s.till_ids[o.till_no] END,
       CASE WHEN o.ch = 'pos' THEN 6 ELSE 4 END, 0, 0,
       sh.id, CASE WHEN o.ch = 'pos' THEN o.bdate END,
       CASE WHEN o.ch = 'pos' THEN row_number() OVER (PARTITION BY o.branch_id, o.bdate, o.ch ORDER BY o.created_at) END,
       CASE WHEN o.r1 >= 0.05 THEN o.created_at + interval '35 minutes' END,
       CASE WHEN o.r1 < 0.05 THEN 'customer_request' END,
       CASE WHEN o.ch = 'pos' AND o.r1 >= 0.05 THEN 'pos' END, 0, 0,
       CASE WHEN o.ch = 'pos' AND o.r2 < 0.05 THEN o.created_at + interval '40 minutes' END,
       CASE WHEN o.ch = 'pos' AND o.r2 < 0.05 THEN o.created_at END,
       CASE WHEN o.ch = 'pos' AND o.r2 < 0.05 THEN gen_random_uuid()::text END
FROM o JOIN scale_stores s USING (store_id) JOIN ot ON ot.order_id = o.id
LEFT JOIN sh ON sh.till = s.till_ids[o.till_no] AND sh.d = o.d AND o.ch = 'pos';

INSERT INTO order_items (id, order_id, product_variant_id, product_name, variant_name, quantity, unit_price, total_price, created_at, updated_at,
    unit, modifiers_total, kitchen_status, ready_at, round_no, fire_mode, station_id, sent_at, added_by)
SELECT oi2.id, oi2.order_id, oi2.variant_id, oi2.pname, 'Porsiya', oi2.qty, oi2.unit_price, oi2.unit_price*oi2.qty, oi2.created_at, oi2.created_at + interval '20 minutes',
       'piece', 0, 'served', oi2.created_at + interval '15 minutes', CASE WHEN oi2.line_no > 3 THEN 2 ELSE 1 END, 'now',
       CASE WHEN oi2.ch = 'pos' THEN oi2.station_ids[1 + (oi2.line_no % 2)] END, oi2.created_at + interval '1 minute',
       CASE WHEN oi2.ch = 'pos' THEN (SELECT user_id FROM orders WHERE id = oi2.order_id) END
FROM oi2;

-- status history: POS accepted/preparing/delivered; Mini App new/accepted/preparing/delivered; cancelled: new/canceled
INSERT INTO order_status_histories (order_id, user_id, status, from_status, notes, created_at, updated_at)
SELECT o.id, NULL::bigint, h.status, h.prev, h.note, o.created_at + h.off, o.created_at + h.off
FROM o CROSS JOIN LATERAL (VALUES
   ('accepted', NULL, 'POS: buyurtma ochildi', interval '0'), ('preparing', 'accepted', 'POS: oshxonaga yuborildi', interval '1 minute'),
   ('delivered', 'preparing', 'POS: to''landi va yopildi', interval '35 minutes')) h(status, prev, note, off)
WHERE o.ch = 'pos' AND o.r1 >= 0.05
UNION ALL
SELECT o.id, NULL::bigint, h.status, h.prev, h.note, o.created_at + h.off, o.created_at + h.off
FROM o CROSS JOIN LATERAL (VALUES
   ('new', NULL, 'Order created via Mini App', interval '0'), ('accepted', 'new', NULL, interval '2 minutes'),
   ('preparing', 'accepted', NULL, interval '5 minutes'), ('delivered', 'preparing', NULL, interval '35 minutes')) h(status, prev, note, off)
WHERE o.ch = 'mkt' AND o.r1 >= 0.05
UNION ALL
SELECT o.id, NULL::bigint, h.status, h.prev, NULL, o.created_at + h.off, o.created_at + h.off
FROM o CROSS JOIN LATERAL (VALUES ('new', NULL, interval '0'), ('canceled', 'new', interval '10 minutes')) h(status, prev, off)
WHERE o.r1 < 0.05;

-- bills: one per paid POS order, 8 % split by items into two
CREATE TEMP TABLE b AS
SELECT nextval('order_bills_id_seq') AS id, o.id AS order_id, o.store_id, o.branch_id, o.created_at, n AS number, (o.r2 > 0.92 AND o.n_items > 1) AS split, o.till_no
FROM o CROSS JOIN LATERAL generate_series(1, CASE WHEN o.r2 > 0.92 AND o.n_items > 1 THEN 2 ELSE 1 END) n
WHERE o.ch = 'pos' AND o.r1 >= 0.05;
CREATE TEMP TABLE bi AS
SELECT b.id AS bill_id, oi2.id AS item_id, oi2.unit_price*oi2.qty AS amount, oi2.qty, oi2.pname, oi2.unit_price
FROM b JOIN oi2 ON oi2.order_id = b.order_id AND (NOT b.split OR (oi2.line_no % 2) + 1 = b.number);
CREATE INDEX ON bi (bill_id); ANALYZE bi;
CREATE TEMP TABLE bt AS SELECT bill_id, sum(amount) AS total, count(*) AS n FROM bi GROUP BY 1;
ALTER TABLE bt ADD PRIMARY KEY (bill_id); ANALYZE bt;
DELETE FROM b WHERE id NOT IN (SELECT bill_id FROM bt);
ALTER TABLE b ADD COLUMN fr_uuid uuid DEFAULT gen_random_uuid(), ADD COLUMN uuid uuid DEFAULT gen_random_uuid(), ADD COLUMN tender text;
ALTER TABLE b ADD PRIMARY KEY (id);
ANALYZE b;
UPDATE b SET tender = CASE WHEN random() < 0.55 THEN 'cash' WHEN random() < 0.78 THEN 'card' ELSE 'qr' END;

INSERT INTO order_bills (id, uuid, store_id, branch_id, order_id, number, status, split_mode, subtotal, discount, room_fee_amount, min_spend_topup_amount,
    service_charge_amount, place_fee_amount, total, basis, printed_count, paid_at, shift_id, fiscal_receipt_uuid, fiscal_status, created_by, created_at, updated_at,
    packaging_amount, banquet_amount)
SELECT b.id, b.uuid, b.store_id, b.branch_id, b.order_id, b.number, 'paid', CASE WHEN b.split THEN 'items' ELSE 'single' END, bt.total, 0, 0, 0, 0, 0, bt.total,
       encode(sha256(b.uuid::text::bytea), 'hex'), 1, b.created_at + interval '34 minutes', o.shift_id, b.fr_uuid, 'issued', o.user_id,
       b.created_at + interval '30 minutes', b.created_at + interval '34 minutes', 0, 0
FROM b JOIN bt ON bt.bill_id = b.id JOIN orders o ON o.id = b.order_id;

INSERT INTO order_bill_items (bill_id, order_item_id, quantity, amount, discount_amount, created_at, updated_at)
SELECT bi.bill_id, bi.item_id, bi.qty, bi.amount, 0, now(), now() FROM bi;

INSERT INTO payments (uuid, order_id, provider, amount, status, paid_at, created_at, updated_at, bill_id, shift_id, device_id, received_by, tendered_amount, change_amount,
                      card_type, transaction_id)
SELECT gen_random_uuid(), b.order_id, CASE b.tender WHEN 'cash' THEN 'cash' WHEN 'card' THEN 'card' ELSE 'uzqr' END, bt.total, 'paid', b.created_at + interval '34 minutes',
       b.created_at + interval '34 minutes', b.created_at + interval '34 minutes', b.id, o.shift_id, o.device_id, o.user_id,
       CASE WHEN b.tender = 'cash' THEN ceil(bt.total / 50000) * 50000 END, CASE WHEN b.tender = 'cash' THEN ceil(bt.total / 50000) * 50000 - bt.total END,
       CASE WHEN b.tender = 'card' THEN 'uzcard' END, CASE WHEN b.tender <> 'cash' THEN md5(b.uuid::text) END
FROM b JOIN bt ON bt.bill_id = b.id JOIN orders o ON o.id = b.order_id;
INSERT INTO payments (uuid, order_id, provider, amount, status, paid_at, created_at, updated_at, transaction_id)
SELECT gen_random_uuid(), x.id, x.payment_method, x.total_price, CASE WHEN x.status = 'canceled' THEN 'cancelled' ELSE 'paid' END,
       x.created_at + interval '1 minute', x.created_at, x.created_at + interval '1 minute', CASE WHEN x.payment_method = 'click' THEN md5(x.uuid::text) END
FROM orders x JOIN o ON o.id = x.id WHERE o.ch = 'mkt';

-- fiscal receipts: one per POS bill, lines/payments/provider_payload shaped like the real sandbox receipt (templates.sql)
INSERT INTO fiscal_receipts (uuid, store_id, branch_id, device_id, type, payment_form, status, driver, idempotency_key, subject_type, subject_id, tax_regime,
    total, vat_total, lines, payments, non_fiscal, fiscal_sign, receipt_number, qr_url, terminal_id, provider_payload, attempts, issued_at, created_at, updated_at, sold_at)
SELECT b.fr_uuid, b.store_id, b.branch_id, o.device_id, 'sale', CASE WHEN b.tender = 'cash' THEN 'cash' ELSE 'card' END, 'issued', 'sandbox',
       'pos-bill:'||b.uuid||':sale', 'order_bill', b.id, 'vat', bt.total, round(bt.total * 12 / 112, 2),
       (SELECT jsonb_agg(t.line || jsonb_build_object('name', bi.pname||' (Porsiya)', 'amount', bi.amount::text, 'quantity', bi.qty::text||'.000', 'unit_price', bi.unit_price::text))
          FROM bi CROSS JOIN scale_tpl t WHERE bi.bill_id = b.id),
       (SELECT t.payments FROM scale_tpl t), true, (100000000000 + b.id)::text, (10000000 + b.id)::text,
       'https://fiscal-sandbox.invalid/check?t=SANDBOX-000001&r='||(10000000 + b.id), 'SANDBOX-000001', (SELECT t.provider_payload FROM scale_tpl t), 1,
       b.created_at + interval '34 minutes', b.created_at + interval '34 minutes', b.created_at + interval '34 minutes', b.created_at + interval '34 minutes'
FROM b JOIN bt ON bt.bill_id = b.id JOIN orders o ON o.id = b.order_id;

-- audit_logs: the 7 rows a real POS order writes (opened, round, status_changed, items_sent, bills_created, payment, closed);
-- Mini App orders: 3 order.status_changed (accept, prepare, deliver) as written when staff move the order
INSERT INTO audit_logs (user_id, action, model_type, model_id, payload_json, created_at, store_id, branch_id, device_id, before, after)
SELECT o2.user_id, a.action, 'App\Models\Order', o.id, a.payload_json, o.created_at + (a.ord * interval '5 minutes'), o.store_id, o.branch_id, o2.device_id, a.before, a.after
FROM o JOIN orders o2 ON o2.id = o.id JOIN scale_tpl_audit a ON a.kind = 'pos'
WHERE o.ch = 'pos' AND (o.r1 >= 0.05 OR a.ord < 3)
UNION ALL
SELECT s.manager_id, a.action, 'App\Models\Order', o.id, a.payload_json, o.created_at + (a.ord * interval '5 minutes'), o.store_id, o.branch_id, NULL, a.before, a.after
FROM o JOIN scale_stores s USING (store_id) JOIN scale_tpl_audit a ON a.kind = 'mkt'
WHERE o.ch = 'mkt';

-- notification_logs: a real Mini App order writes one new_order row per staff member of the store (+1); plus 3 status
-- notifications to the customer [E: status pushes measured as rows only, Telegram is off in this test]
INSERT INTO notification_logs (user_id, order_id, type, status, sent_at, is_read)
SELECT u.id, o.id, 'new_order', 'sent', o.created_at, random() < 0.7
FROM o JOIN users u ON u.store_id = o.store_id AND u.role IN ('owner','manager','cashier','cook','waiter') WHERE o.ch = 'mkt'
UNION ALL
SELECT x.user_id, o.id, t, 'sent', o.created_at + interval '10 minutes', random() < 0.5
FROM o JOIN orders x ON x.id = o.id CROSS JOIN unnest(ARRAY['new_order','order_status','order_status','order_status']) t WHERE o.ch = 'mkt';

-- pos_sync_operations: the 5 % POS orders that were taken offline (external_id set): 4 ops each
INSERT INTO pos_sync_operations (store_id, branch_id, device_id, op_id, seq, type, staff_id, occurred_at, payload, status, result, client_ref, order_id, clock_skew_seconds,
                                 received_at, applied_at, created_at, updated_at)
SELECT x.store_id, x.branch_id, x.device_id, gen_random_uuid(), 100000 * x.id + op.n, op.t, x.user_id, x.created_at + op.n * interval '5 minutes',
       jsonb_build_object('order', x.external_id, 'channel', x.channel, 'items', jsonb_build_array(jsonb_build_object('variant_id', gen_random_uuid(), 'quantity', 1, 'unit_price', '25000.00')),
                          'menu_taken_at', x.created_at - interval '2 hours', 'send', true),
       'applied', jsonb_build_object('order_uuid', x.uuid, 'status', 'applied'), x.external_id, x.id, 2,
       x.synced_at, x.synced_at, x.synced_at, x.synced_at
FROM orders x JOIN o ON o.id = x.id CROSS JOIN (VALUES (1,'order.open'),(2,'bill.create'),(3,'bill.pay'),(4,'order.close')) op(n,t)
WHERE o.ch = 'pos' AND x.external_id IS NOT NULL;

-- idempotency_keys: only the last 24 h exist (TTL); ~5 per POS order with the stored response (shape of the real rows)
INSERT INTO idempotency_keys (scope, actor_type, actor_id, key, request_hash, response_status, response_body, response_content_type, expires_at, created_at, updated_at)
SELECT sc, 'user', x.user_id, gen_random_uuid()::text, encode(sha256((x.id||sc)::bytea),'hex'), 200, t.idem_body, 'application/json',
       x.created_at + interval '1 day', x.created_at, x.created_at
FROM orders x JOIN o ON o.id = x.id CROSS JOIN scale_tpl t
CROSS JOIN unnest(CASE WHEN o.ch = 'pos' THEN ARRAY['pos.orders.open','pos.orders.rounds','pos.orders.send','pos.bills.create','pos.bills.pay'] ELSE ARRAY['orders.store'] END) sc
WHERE o.d = 1;

-- bookkeeping for the bytes/order measurement
CREATE TABLE IF NOT EXISTS scale_orders_kind (order_id bigint PRIMARY KEY, kind text, ch text);
INSERT INTO scale_orders_kind SELECT id, kind, ch FROM o;
UPDATE devices d SET last_seen_at = now() FROM scale_stores s WHERE d.store_id = s.store_id AND s.grp BETWEEN :g1 AND :g2;
SELECT :g1 AS g1, :g2 AS g2, (SELECT count(*) FROM o) AS orders, (SELECT count(*) FROM oi2) AS items, (SELECT count(*) FROM b) AS bills;
COMMIT;
