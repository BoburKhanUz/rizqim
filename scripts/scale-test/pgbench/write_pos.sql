-- The row writes of one POS cash order (1 order, 4 lines, 3 status rows, 7 audit rows, bill, 4 bill lines, payment,
-- fiscal receipt, 5 idempotency keys, ~6 order UPDATEs) in ONE transaction, rolled back so the DB does not grow.
-- Lower bound of DB work per order (the app spreads it over ~6 requests / ~25 transactions).
\set sid random(:store_min, :store_max)
BEGIN;
insert into orders (uuid, short_code, store_id, user_id, status, payment_status, order_type, subtotal, total_price, channel, branch_id, version, business_date, created_at, updated_at)
  values (gen_random_uuid(), 'PB'||txid_current(), :sid, 1, 'accepted', 'pending', 'pickup', 50000, 50000, 'pos_takeaway', :sid, 1, current_date, now(), now()) returning id \gset o_
insert into order_items (order_id, product_name, quantity, unit_price, total_price, kitchen_status, round_no, fire_mode, created_at, updated_at)
  select :o_id, 'Taom '||g, 1, 12500, 12500, 'sent', 1, 'now', now(), now() from generate_series(1,4) g;
insert into order_status_histories (order_id, status, created_at, updated_at) select :o_id, s, now(), now() from unnest(array['accepted','preparing','delivered']) s;
insert into audit_logs (user_id, action, model_type, model_id, payload_json, store_id, branch_id, created_at)
  select 1, 'pos.bench', 'App\Models\Order', :o_id, '{"items":4,"total":"50000.00","staff":"Kassir"}', :sid, :sid, now() from generate_series(1,7);
update orders set version = version + 1, updated_at = now() where id = :o_id;
update orders set status = 'preparing', version = version + 1, updated_at = now() where id = :o_id;
insert into order_bills (uuid, store_id, branch_id, order_id, number, status, subtotal, total, basis, created_at, updated_at)
  values (gen_random_uuid(), :sid, :sid, :o_id, 1, 'open', 50000, 50000, md5(random()::text), now(), now()) returning id \gset b_
insert into order_bill_items (bill_id, order_item_id, quantity, amount) select :b_id, id, 1, 12500 from order_items where order_id = :o_id;
insert into payments (uuid, order_id, bill_id, provider, amount, status, paid_at, created_at, updated_at) values (gen_random_uuid(), :o_id, :b_id, 'cash', 50000, 'paid', now(), now(), now());
insert into fiscal_receipts (uuid, store_id, branch_id, type, payment_form, status, driver, idempotency_key, subject_type, subject_id, total, lines, payments, created_at, updated_at)
  values (gen_random_uuid(), :sid, :sid, 'sale', 'cash', 'issued', 'sandbox', 'bench:'||:b_id, 'order_bill', :b_id, 50000, (select jsonb_agg(line) from scale_tpl, generate_series(1,4)), (select payments from scale_tpl), now(), now());
insert into idempotency_keys (scope, actor_type, actor_id, key, request_hash, response_status, response_body, expires_at, created_at, updated_at)
  select 'bench', 'user', 1, gen_random_uuid()::text, md5(random()::text)||md5(random()::text), 200, (select idem_body from scale_tpl), now() + interval '1 day', now(), now() from generate_series(1,5);
update order_bills set status = 'paid', paid_at = now() where id = :b_id;
update orders set status = 'delivered', payment_status = 'paid', closed_at = now(), version = version + 1, updated_at = now() where id = :o_id;
update orders set version = version + 1, updated_at = now() where id = :o_id;
ROLLBACK;
