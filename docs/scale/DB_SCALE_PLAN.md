# RIZQIM: one big database. Facts, size projection, load-test design

Status: read-only audit at commit 65551cd (2026-11-14). Measured on a throw-away DB `scale_probe` (migrate + PosStackSeeder, 20 MB; dropped afterwards).
Labels: **[F]** = fact from code/DB, **[E]** = estimate or guess. Paths are relative to the repo root.

## 1. Schema inventory

- [F] 247 migrations, **165 tables**, 540 indexes, 501 FK constraints (information_schema / pg_indexes on scale_probe).
- [F] Migrations build from scratch in 2.4 s (no schema dump; `database/schema/` absent). 144 models in `backend/app/Models`.
- [F] Tenant columns:
  - **store_id + branch_id (57 tables)**: orders, order_bills, fiscal_receipts, audit_logs, cash_movements, pos_shifts, pos_sync_operations, pos_refunds, pos_tips, pos_qr_payments, devices, dining_tables, halls, printers, kitchen_stations, bookings, banquet_*, vouchers, customer_accounts, staff_work_sessions, stop_list_entries, users (staff carry store/branch, customers NULL) …
  - **store_id only (40)**: products, branches, brands, menu_price_history, channel_prices, promos, promo_codes, store_transactions, store_wallet_transactions, store_subscriptions, subscription_invoices, order_refund_requests, store_ratings, payout_requests, alerts …
  - **branch_id only (2)**: branch_product_rotation, branch_product_variants.
  - **Tenant-owned but NO tenant column (reachable only via parent)**: **order_items, payments, order_status_histories, order_status_logs, order_bill_items, order_assignments, order_sla_events, order_charge_waivers, order_packaging_removals, notification_logs**, product_variants, modifiers, categories, combo_slots(+options), product_modifier_groups, product_ingredients, booking_items, booking_tables, banquet_event_lines/halls/staff, banquet_package_items, daily_menu_items, daily_set_items, complaints, complaint_replies, pos_tip_distribution_lines, staff_pay_statement_lines, external_refs, integration_events, telegram_actions.
    Note: payments and order_items, the 2nd/3rd biggest growth tables, have no store_id: hash-partitioning or RLS by store would need a column added + backfill.
  - **Global**: users (one table for customers, staff, platform admins), addresses, regions, districts, settings, features, tariff_plans, payment_providers, platform_* (stats, invites, role perms), idempotency_keys (keyed by actor), personal_access_tokens, refresh_tokens, sessions, cache*, jobs*, failed_jobs, phone_verifications, alert_rules, support_canned_replies, store_broadcasts, monitoring_markers, stores itself.
- [F] Big-growth tables and their indexes (pg_indexes):
  - orders (**87 columns, 21 indexes**): (store_id,created_at,id), (store_id,status,created_at), (branch_id,created_at,id), (branch_id,status,created_at), (branch_id,business_date,pickup_status), UNIQUE(branch_id,business_date,queue_number), UNIQUE(store_id,local_number), UNIQUE(store_id,channel,external_id), (shift_id,status), (table_id,status), (user_id,created_at,id), (courier_id,created_at,id), (courier_id,status), (promo_code_id,user_id), (status,created_at), (created_at), (kitchen_fire_at,kitchen_fired_at), partial(settlement_failed_at), UNIQUE short_code/uuid/id. Hot staff/POS queries (`StaffOrderController.php:139-181`: branch_id + status + created_at keyset) lead with branch_id: OK. Write amplification is the concern: every UPDATE (status, version bump `OrderCloser::bumpVersion`) touches indexed columns, so no HOT updates → 21 index writes per update. [E]
  - order_items (7): order_id, (order_id,client_line_id), station_id, sent_at, brand_id, combo_parent_id. KDS ticket queries go via orders.branch_id then order_id: OK. (order_id) is redundant with (order_id,client_line_id). `sent_at`, `station_id` single-column indexes are global (no branch prefix) [E: low selectivity at scale].
  - order_bills (6): (branch_id,fiscal_status), (shift_id,status), UNIQUE(order_id,number), uuid. **store_id unindexed.**
  - payments (5): bill_id, (shift_id,provider), UNIQUE(provider,provider_transaction_id), uuid. **order_id unindexed** (FK).
  - fiscal_receipts (7): (store_id,created_at), UNIQUE(store_id,idempotency_key), (status,created_at), (status,next_attempt_at), (subject_type,subject_id), uuid. branch_id/device_id/original_receipt_id unindexed.
  - audit_logs (6): (store_id,created_at), (user_id,created_at), (action,created_at), **(model_type,model_id) twice** (`audit_logs_model_type_model_id_index` and `audit_logs_model_index`: exact duplicate). branch_id unindexed.
  - order_status_histories (2): (order_id,status). order_status_logs: **only PK, order_id unindexed**.
  - pos_sync_operations: UNIQUE(device_id,seq), UNIQUE(op_id), (device_id,client_ref), (store_id,status,resolved_at).
  - idempotency_keys: UNIQUE(scope,actor_type,actor_id,key), (expires_at). response_body is TEXT (full response).
  - notification_logs: **only PK**; `NotificationFeedService.php:15-21` filters `where user_id = ? [and is_read=false] order by id desc` and the Mini App polls it every 15 s → seq scan of a table that grows forever.
  - menu_price_history: (product_variant_id,channel,effective_from), (modifier_id,effective_from); no store_id index.
  - integration_events: 5 indexes incl. global (created_at), (type,id).
  - There is no kitchen_tickets table: KDS reads orders/order_items (`/v1/staff/kitchen/tickets`, `routes/api_staff_orders.php:21`).
- [F] **321 of 501 FK columns have no index starting with them.** Hot-path ones: products.store_id, products.category_id, product_variants.product_id (menu load for every till and marketplace!), branches.store_id, payments.order_id, order_status_logs.order_id, notification_logs.user_id/order_id, store_transactions.store_id, store_wallet_transactions.store_id, cash_movements.store_id, order_bills.store_id, pos_refunds.order_id/bill_id, pos_tips.order_id, telegram_actions.order_id, order_assignments.order_id, store_ratings.store_id, addresses.user_id, devices.branch_id, modifiers.product_variant_id, complaints.order_id. Besides query cost, unindexed FK children make every parent DELETE/key UPDATE scan the child.
- [F] No pg_trgm / tsvector / PostGIS: marketplace distance is a haversine `whereRaw` over branches (`StoreController.php:56-80`, `CatalogController.php:78-116`), product search is LIKE.

## 2. Tenant scoping in code today

- [F] **No global tenant scope.** The only `addGlobalScope` is OrderItem's combo-parts scope (`app/Models/OrderItem.php:101`). No `BelongsToStore` trait, no `scopeForStore`.
- [F] Scoping = explicit `where('store_id'|'branch_id', $user->…)`: 611 occurrences in 210 files under `app/`. `StaffOrderScope` (`app/Services/Order/StaffOrderScope.php:23`) centralises staff order lists only.
- [F] Route params are strings (`{uuid}` ×169, `{id}` ×123); no implicit route-model binding of tenant models, no `Route::bind`/`scopeBindings`. Controllers resolve then check manually (33 `->store_id !==`/`abort_unless(...store_id)` sites).
- [F] Policies: 1 (`app/Policies/OrderPolicy.php`); 73 `authorize/Gate/can:` uses. Middleware: `EnsureStaffHasStore` (exists precisely because a NULL store_id would turn `where('store_id', null)` into "match all customers": its own comment), `EnsureUserHasPermission`, `EnsurePosModule`, `EnsurePlanFeature`.
- [F] Unscoped follow-ups exist by convention, e.g. `PosBillController.php:142,250,271` `Order::findOrFail($bill->order_id)` (safe because the bill was scoped first).
- [F] **No Row Level Security**: no `ENABLE ROW LEVEL SECURITY` / `CREATE POLICY` in migrations or app. The DB role used by the app is superuser in this container (`rolsuper = t`), which would bypass RLS anyway.
- [E] ~140 of 144 models have no automatic scope; isolation correctness rests on review and tests.

## 3. Request load per device (polls, websockets, sync)

- [F] No ETag/If-None-Match, no `since`/version cursor on any polled endpoint (grep of pos/, partner/web, customer/miniapp, backend/app). Every poll returns the full current state. Orders carry a `version` (optimistic lock) that is not used for delta reads.
- [F] Flutter `PosRefreshScheduler` (`pos/packages/pos_data/lib/src/realtime/pos_realtime.dart:178-251`): poll every `pollInterval` (15 s) while Reverb is down, `livePollInterval` (60 s) "safety poll" while live, plus a debounced refresh on every matching Reverb event and on resubscribe. Only the visible screen's scheduler runs.
  - Cashier (`apps/cashier/lib/src/app_config.dart:28,31`): floor (`GET /v1/pos/floor`), order page (`GET /v1/pos/orders/{uuid}`), billing (`cashier_ui.dart:649`), counter queue (`counter_ready.dart:93`, `GET /v1/pos/queue`); bill-print inbox catch-up every 60 s (`services.dart:40`, `app.dart:188`); LAN hub refresh every 5 min (`app.dart:141`); plan refresh 10 min (`shell/plan_gate.dart:143`); QR payment status polled ~1 s while a QR panel is open (`qr_payment_panel.dart:162,220`).
  - KDS: kitchen board 15 s / 60 s (`apps/kds/lib/src/app_config.dart:27,30`, `GET /v1/staff/kitchen/tickets`), kazan panel 15 s (`kds/lib/src/widgets/kazan_panel.dart:21`), ingredients 60 s (`kds_ingredients.dart:69`), plan 10 min.
  - Waiter: order/hostess/rooms 15 s / 60 s (`apps/waiter/lib/src/app_config.dart:40,43`), plan 10 min.
  - Customer display: 5 s / 30 s (`apps/customer_display/lib/src/app_config.dart:26,29`; `GET /v1/pos/display/state|cart|queue`).
  - Offline: while offline, `GET /v1/pos/sync/status` probe with 15 s heartbeat and back-off ×2/×4 (`pos_offline.dart:70,1147-1155`); on reconnect the outbox is flushed via `POST /v1/pos/sync` (`routes/api_pos_sync.php`), then floor/orders are re-read.
- [F] Web: partner LiveOrders 15 s / 60 s with up to 4 queries (new orders, active, today's finished page, selected detail) (`partner/web/src/components/shell/LiveOrders.tsx:24-25,129`, `orders/OrdersBoard.tsx:94-100`), dashboard 60 s, stop-list 30 s. Mini App (`customer/miniapp/src/lib/polling.ts:13-25`): customer orders 10 s, notifications 15 s, manager orders 8 s, owner finance 10 s, analytics 60 s; 60 s when realtime connected; no polling while hidden.
- [F] Reverb channels (`routes/channels.php`): `store.{id}`, `branch.{id}`, `pos-branch.{id}`, `courier.{id}`, `user.{id}`, platform-ops. Channel auth goes through PHP (`/api/broadcasting/auth`, `bootstrap/app.php:26-29`), i.e. a DB hit per subscribe/reconnect.
- [F] **docker-compose.yml:41 defaults `BROADCAST_CONNECTION` to `null`.** Unless the operator sets reverb, every client stays in 15 s (display 5 s) polling mode.
- [E] Requests/minute (reads; writes added for busy):
  | device | Reverb live | Reverb off |
  |---|---|---|
  | idle till | 2–3 (screen 1 + prints 1 + small) | 5–6 |
  | busy till (30 orders/h) | 15–25 (≈6–8 writes/order + event refreshes) | 20–30 |
  | KDS | 3–6 idle, 10–20 busy (event refresh per order change) | 9–10 + events n/a |
  | waiter phone | 1–2 + 3–6 writes/order | 4–5 |
  | customer display | 2 + events | 12 |
  | partner web tab | 4–5 | 16–17 |
  | Mini App customer with active order | 2 | 10 |
  Typical store (2 tills, 1 KDS, 3 waiters, 1 display, 1 owner tab), lunch: ≈ 40 rpm live / ≈ 90 rpm without Reverb ⇒ 1 000 stores ≈ 700–1 500 req/s at peak, mostly full-state reads. **Request rate, not data size, is the first scaling pressure.**

## 4. Rows and bytes per POS cash order with fiscal receipt

Code path (dine-in, 4 lines, 1 bill, cash): `PosOrderService::open` (`app/Domain/Pos/Orders/PosOrderService.php:155,190,211`) → `send` (:325-333) → bill request (:958) → `PaymentService` (`app/Domain/Pos/Billing/PaymentService.php:201,226,235,243,265`) → `BillFiscalizer::issue` → `FiscalService::create` (`app/Domain/Fiscal/FiscalService.php:313`) → `OrderCloser` (:41,50). Auditable trait (auto-audit on create/update) is only on Store, Branch, Product, Promo, PayoutRequest.
- [F/E] Rows inserted (permanent): orders 1, order_items 4, order_status_histories 1–2, audit_logs 4–5 (order_opened, items_sent, bill_requested, payment, order_closed), order_bills 1, order_bill_items 4, payments 1, fiscal_receipts 1 ⇒ **~18 rows**. Transient: idempotency_keys 3–5 (24 h TTL, `config/menu.php:28`), jobs rows for broadcasts if QUEUE_CONNECTION=database (prod: redis). Offline orders add 3–6 pos_sync_operations. Updates: orders ~5–7, order_bills 2–3, order_items 1–2 each, fiscal_receipts 1–2.
- [F] Measured avg heap tuple size on seeded rows: orders 344 B (87 cols, seeded rows sparse), order_items 207 B, audit_logs 223 B (menu actions, smaller meta than pos.payment), payments 103 B, order_status_histories 103 B. fiscal_receipts has `lines`, `payments`, `provider_payload` JSONB.
- [E] Bytes per order incl. indexes and normal bloat: audit_logs 3.7 KB, fiscal_receipts 3.0 KB, order_items 2.0 KB, orders 1.2 KB, order_bills 0.6, pos_sync_operations 0.5 (avg), payments 0.35, order_status_histories 0.3, order_bill_items 0.3, misc 0.2 ⇒ **≈ 12 KB/order (range 8–15 KB)**. Rough: seeded data has only 2 orders, so this is tuple size × index count, not a measured delta; the S-profile test below replaces it with a real number.
- [E] Projection at 150 orders/day/store = 54 750 orders/store/year ≈ 0.66 GB/store/year:
  | table (KB/order) | 100 stores GB/yr | 1 000 | 5 000 |
  |---|---|---|---|
  | audit_logs (3.7) | 20 | 203 | 1 013 |
  | fiscal_receipts (3.0) | 16 | 164 | 821 |
  | order_items (2.0) | 11 | 110 | 548 |
  | orders (1.2) | 6.6 | 66 | 329 |
  | order_bills (0.6) | 3.3 | 33 | 164 |
  | pos_sync_operations (0.5) | 2.7 | 27 | 137 |
  | payments (0.35) | 1.9 | 19 | 96 |
  | order_status_histories (0.3) | 1.6 | 16 | 82 |
  | order_bill_items (0.3) | 1.6 | 16 | 82 |
  | idempotency_keys (steady, 1 day) | 0.2 | 1.8 | 9 |
  | **total** | **≈ 66** | **≈ 655** | **≈ 3 300** |
  Row counts per year: orders 5.5 M / 55 M / 274 M; audit_logs ~25 M / 250 M / 1.2 B. Catalog, users etc. are negligible (<1 %).
  Interpretation [E]: 100 stores fits one modest server for years; 1 000 stores is ~0.65 TB/yr, still one Postgres but needs partitioning/retention for audit + fiscal and a better backup than nightly pg_dump; 5 000 stores is multi-TB/yr.

## 5. Cross-tenant queries a per-store database would break

- [F] Marketplace store list `GET /v1/stores` (`StoreController.php:48-89`): all active stores, whereHas branches with haversine distance or region/district, ordered by distance.
- [F] Marketplace catalog `GET /v1/catalog` (`CatalogController.php:73-131`): products across all open stores (opening hours, distance), limit 50, then categories; product search by LIKE.
- [F] Customer's own orders and notifications across stores: `GET /v1/orders` (orders.user_id index), `GET /v1/notifications` (notification_logs by user_id). Addresses, promo-code usage per user (`orders(promo_code_id,user_id)`), store_ratings by user, complaints.
- [F] One `users` table for customers, staff and admins; Telegram identity (`users.telegram_id`) is global; tokens (`personal_access_tokens`, `refresh_tokens`) are global.
- [F] Couriers: `order_assignments`, `orders(courier_id,…)`: a courier can serve several stores.
- [F] Platform admin: 17 admin services query orders directly (`app/Services/Admin/{Today,Metrics,Orders,Customers,Stores,Monitoring,Attention,Integrations}`), e.g. `PlatformStatsRefresher.php:41` platform-wide daily stats, `SuspiciousCases.php` (customer fraud across stores), stuck integration orders, store health scores, attention queue.
- [F] Billing/finance: store_subscriptions, subscription_invoices, store_transactions, store_wallet_transactions, payout_requests, dunning (`BillSubscriptionsJob` daily 00:15 over all stores, `routes/console.php:37`), commission settlement retries (`RetryFailedSettlementsJob`, :19).
- [F] Scheduler jobs that sweep all tenants every minute: CancelUnpaidOnlineOrdersJob, menu:expire-stop-list, CheckUnacceptedAssignmentsJob; every 10 min pos:alert-long-open, settlements.
- [F] Aggregator channels: integration_connections/events/external_refs, UNIQUE(store_id,channel,external_id): inbound webhooks must find the tenant from an external id first (`integration_events` unique by connection).
- [E] With DB-per-store all of the above become fan-out queries or need a central "platform" DB plus replication of store/product summaries (search index) and of orders (customer history, admin, billing). That is a second system to build and keep consistent.

## 6. Connection model

- [F] Image `php:8.3-fpm-alpine` (`backend/Dockerfile:14`) + nginx under supervisord (`backend/docker/supervisord.conf`). No `pm.*` override anywhere in the repo ⇒ the image default `www.conf`: `pm = dynamic`, `pm.max_children = 5` [F for the default, E that nothing overrides it at deploy]. **That caps the whole API at 5 concurrent requests per backend container.** No Octane/Swoole/FrankenPHP (`composer.json`: only laravel/reverb).
- [F] PDO not persistent (no `PDO::ATTR_PERSISTENT` in `config/database.php`): each FPM request opens/closes one PG connection (≤5 per container). Queue workers: `queue` (default,broadcasts) and `queue-reports` (`docker-compose.yml:132-140`), 1 long-lived connection each. Scheduler `schedule:work` (:155) spawns `schedule:run` each minute (1 conn + 1 per in-process job/command). Reverb (:181) itself does not query PG; channel auth goes through FPM. Backup container: pg_dump connection nightly.
- [F] Postgres: prod compose `postgres:17-alpine` with default `max_connections=100`; staging `-c max_connections=50 -c shared_buffers=64MB` (`docker-compose.staging.yml:33`). This container: PG 16, max_connections 100, `shared_preload_libraries` empty. Typical total today ≈ 5 + 2 + 2 + 1 ≈ 10 connections.
- [F] PgBouncer transaction pooling compatibility:
  - OK: all advisory locks are transaction-scoped `pg_advisory_xact_lock` (`ShiftService.php:407`, `QueueNumbers.php:128`, `TipPoolDistributor.php:119`, `PlatformStatsRefresher.php:41`, `StoreBroadcastService.php:70`); `lockForUpdate` inside transactions; no LISTEN/NOTIFY; no `SET`/`set_config` in app code; Cache locks / `withoutOverlapping` use the cache store (redis in prod).
  - Session-scoped items: Laravel's connector issues `set names 'utf8'` and `set search_path to "public"` (config/database.php:95,98) at connect: identical for every client, harmless. **PDO pgsql uses named server-side prepared statements** (no `ATTR_EMULATE_PREPARES` set): needs PgBouncer ≥ 1.21 with `max_prepared_statements` > 0, or `PDO::ATTR_EMULATE_PREPARES => true`. Migration `DB::unprepared` for the audit trigger runs fine through a direct connection (run migrations without the pooler).

## 7. Retention

- [F] Pruned: idempotency_keys (expires_at, TTL 24 h; `model:prune` daily, `routes/console.php:52`, `app/Models/IdempotencyKey.php:35`), pos_approvals (7 days after expiry), alerts (resolved, KEEP_RESOLVED_DAYS), personal_access_tokens / refresh_tokens / pairing codes (`auth:prune-tokens --hours=24`, `bootstrap/app.php:33`, `PruneAuthTokensCommand.php`). Backups: nightly pg_dump -Fc, 14-day retention (`scripts/backup.sh:2,63`).
- [F] Grows forever: orders, order_items, order_status_histories, order_status_logs (legacy, copied into histories by `2026_10_18_130100_…`), order_bills(+items), payments, fiscal_receipts, audit_logs, notification_logs, pos_sync_operations, integration_events, telegram_actions, cash_movements, pos_shifts, store_transactions/wallet, menu_price_history, staff_work_sessions, platform_*_stats, failed_jobs.
- [F] **audit_logs is append-only by trigger** (`2026_11_01_100600_make_audit_logs_append_only.php:28-50`): DELETE raises. A retention policy therefore requires either partitioning (dropping a partition does not fire row triggers) or an explicit maintenance bypass. Fiscal receipts and payments likely have legal retention (Uzbek tax rules, [E] ≥5 years): archive, not delete.

## 8. Load test design (step 1)

### 8.1 Environment
- [F] Here: 4 vCPU, 16 GB RAM, ~2.6 GB free disk, PG 16 (shared with other agents' test DBs), nginx present, **no php-fpm, no docker**, no k6/wrk/hey/ab/vegeta/oha; **pgbench** present; Node 22 (built-in fetch/undici), Python 3 (stdlib only), PHP 8.3 with curl + pcntl. pg_stat_statements and auto_explain `.so` are installed but not preloaded.
- Implication: in-container HTTP tests run on `php artisan serve` (single process: measures per-request cost, not concurrency) or `PHP_CLI_SERVER_WORKERS=4 php artisan serve` (Laravel 11+ supports it; 4 workers ≈ realistic small box). Concurrency-limited results must be labelled as such. Staging must use the real compose stack.

### 8.2 Data generator (SQL, not factories)
- Create DB `scale_big`; `php artisan migrate --force` with `DB_DATABASE=scale_big` on the command line; run `PosStackSeeder` once for reference rows (plans, settings, catalog template).
- One scratch SQL file (outside repo), `psql -v profile=S -f gen.sql`:
  1. `SET session_replication_role = replica` (skip FK triggers during bulk load; superuser only), `synchronous_commit=off`, `maintenance_work_mem=512MB`.
  2. stores/branches/users/devices/halls/tables/categories/products/variants via `generate_series`, copying NOT NULL defaults from the seeded store (generator author: build the column lists from `\d+` after migrate; orders has 87 columns).
  3. Skew: store weight w = 1/rank^1.1 (Zipf); assign branches 1–10 by tier (top 1 % = 5–10 branches, 10 % = 2–3, rest 1); daily orders per store = round(base × w × N/Σw), clamp 5..1 500.
  4. orders via `INSERT … SELECT` over `generate_series(1, n)` joined to a weighted store/branch table; created_at spread over the period with a lunch (12–14) and dinner (19–21) hump; status 92 % closed, 5 % cancelled, 3 % open/today; business_date, queue_number (row_number per branch/day), local_number unique per store.
  5. children from orders: items (1–8, mean 4), status histories (2), bills (1; 8 % split into 2), bill items, payments (cash 55 %, card 35 %, QR 10 %), fiscal receipts (1 per bill, ~1.5 KB JSONB lines), audit_logs (5, realistic meta JSON), 5 % offline → 4 pos_sync_operations, idempotency_keys only for the last 24 h.
  6. `setval` all sequences, re-enable triggers, `VACUUM ANALYZE`, then a quick FK sanity check (`NOT EXISTS` joins on the 10 biggest FKs).
- Profiles (≈12 KB/order realistic; `lean=1` shrinks JSON/audit to ≈5 KB to pack more rows):
  | profile | stores | branches | days | orders | items | audit_logs | est. size |
  |---|---|---|---|---|---|---|---|
  | S | 50 | 80 | 30 | 25 k | 100 k | 125 k | ~0.3 GB |
  | M | 500 | 700 | 30 | 60 k | 240 k | 300 k | ~0.75 GB |
  | L (lean) | 2 000 | 2 600 | 60 | 250 k | 1 M | 1.25 M | ~1.3 GB |
  | Server-1 | 1 000 | 1 400 | 365 | 55 M | 220 M | 275 M | ~650 GB |
  | Server-90d | 1 000 | 1 400 | 90 | 13.5 M | 54 M | 68 M | ~160 GB |
  Run S first and compute **measured bytes/order** = Δ`pg_total_relation_size` per table / orders; replace the §4 estimate.

### 8.3 Auth tokens in bulk
- Device tokens: insert into personal_access_tokens with `tokenable_type` = Device morph class, `token = encode(sha256(plain::bytea),'hex')`; client sends `Authorization: Bearer {id}|{plain}`. Staff session tokens: call the PIN sign-in endpoint once per (device, staff) at warm-up (exercises the real path) or insert the same way after reading how the staff-session token's abilities/`device_id` are set. Customer tokens: same insert for `User` (customers). Write all tokens to a CSV outside the tracked tree.

### 8.4 Driver (no new project dependency)
- **Node 22 script** (`load.mjs`): undici `Agent({connections})`, open-model arrival rate per scenario, per-endpoint HDR-like histogram (sorted array percentiles is fine), CSV/JSON output. Python asyncio is not usable without aiohttp; PHP curl_multi is a fallback.
- **pgbench custom scripts** (`-f hot_floor.sql -f kds.sql …`, `-c/-j`, `-M prepared`) to load the DB alone with the captured hot SQL: separates Postgres limits from PHP limits and is the cheapest re-run on a real server.

### 8.5 Scenarios (each 10 min steady state, 2 min warm-up)
1. **Lunch peak**: N stores × T tills (here: 50×2; staging: 1 000×2). Per order: `POST /v1/pos/orders` (open) → add round → send to kitchen → KDS bump (`/v1/staff/kitchen/tickets` refresh + status) → `POST …/bills` → pay cash → fiscal receipt (fake provider) → close. Think time 20–60 s between steps, order inter-arrival per till ~2 min (30/h). Idempotency-Key on every POST.
2. **Idle fleet polling**: all devices of all stores polling per §3 table, two variants: Reverb off (15 s/5 s) and Reverb live (60 s/30 s + event-triggered refresh). Reverb-live variant here only simulates the reduced poll rate.
3. **Owner reports during peak**: 5 % of stores open `GET /v1/owner/analytics`, finance, shift X/Z report, `queue-reports` job every 60 s; one platform admin hitting Today/Metrics every 30 s.
4. **Marketplace browsing**: 200 → 2 000 virtual customers: `/v1/stores` with lat/lng, `/v1/catalog`, store menu, `/v1/orders`, `/v1/notifications` (10–15 s while an order is active), 2 % place an online order.
5. **Offline-sync burst**: 20 % of tills offline 30 min, each queues ~30 ops, reconnect within 60 s: `POST /v1/pos/sync` batches + full floor/orders reload.
6. **Soak**: scenario 1+2 at 50 % for 2 h on staging (bloat, autovacuum, connection churn).

### 8.6 Measurements
- Client: p50/p95/p99 per endpoint, throughput, error rate (by status), timeouts.
- Postgres: after the merge (other agents idle) `ALTER SYSTEM SET shared_preload_libraries='pg_stat_statements,auto_explain'`, `auto_explain.log_min_duration=200ms`, `auto_explain.log_analyze=on`, `track_io_timing=on`, `log_lock_waits=on`, `log_min_duration_statement=100ms`, restart; `pg_stat_statements_reset()` before each run. Collect: top 20 by total_exec_time and by mean, EXPLAIN (ANALYZE, BUFFERS) for each, `pg_stat_user_tables` (seq_scan vs idx_scan, n_dead_tup), `pg_statio_user_tables` hit ratio, table/index sizes, `pg_stat_activity` sampled every 5 s (connections, wait_event), `pg_locks` waits, checkpoints/WAL volume (`pg_stat_wal`).
- Host: CPU split php vs postgres (`pidstat`/`top -b`), RAM, disk.

### 8.7 Pass/fail targets (proposal for the owner)
| target | value | failure implies |
|---|---|---|
| POS write endpoints (open/send/pay) p95 | < 300 ms, p99 < 800 ms | lock contention or index write cost on orders → slim indexes / fix hot query, not split |
| polled reads (floor, tickets, display) p95 | < 150 ms | add ETag/`since` cursors, Reverb on, cache → app fix |
| error rate | < 0.1 % (0 lost payments) | correctness bug, fix first |
| any single statement mean | < 20 ms; no seq scan > 10 k rows on a hot path | missing index (§1 list) |
| per-store query latency flat between S and L | < 1.5× growth | if it grows with total DB size (not store size) → partitioning/index order problem |
| DB CPU at peak | < 60 % | read replica / caching before splitting |
| connections | < 70 % of max_connections | PgBouncer |
| offline burst drains | < 2 min, no deadlocks | sync batching |
| bytes/order measured | ≤ 15 KB | retention/partitioning for audit_logs, fiscal JSON |

### 8.8 What this container can and cannot prove
- Can: query plans and index usage at 250 k orders/2 000 stores with skew; measured bytes/order; per-request DB cost; relative cost of scenarios; locking/deadlocks under moderate concurrency; pgbench SQL capacity on 4 vCPU.
- Cannot: realistic concurrency (no php-fpm, shared CPU between driver, PHP and PG), buffer-cache behaviour once data ≫ RAM (needs ≥ 100 GB data), WAL/backup/restore times, Reverb fan-out, network latency, multi-container connection counts. Those need a staging server (e.g. 8 vCPU/32 GB, NVMe, compose stack with `pm.max_children` tuned, Server-90d profile).

## 9. Options the analysis will choose between

| option | migrations | marketplace / cross-store | reports | backups | cost & effort (small team) | evidence that favours it |
|---|---|---|---|---|---|---|
| (a) one DB, store_id + indexes | one run | unchanged | unchanged (heavy ones on primary) | one dump (switch to basebackup+WAL past ~100 GB) | lowest | per-store latency flat S→L, DB CPU < 60 %, fixes are missing indexes/polling |
| (b) + partitioning (time for orders/items/audit/fiscal; hash(store_id) optional) + read replica | partitioned tables need PK incl. partition key; Laravel migrations manual SQL; payments/order_items need store_id or created_at | unchanged | replica isolates reports | drop/detach old partitions, faster vacuum | medium, one-off | audit/fiscal dominate size; vacuum/bloat or retention pain; reports hurt POS p95 |
| (c) RLS second wall | add policies per tenant table (~100), non-superuser app role, `SET LOCAL app.store_id` per transaction (PgBouncer-safe if LOCAL) | needs a bypass role for marketplace/admin | admin bypass role | none | medium; perf cost small but test it | orthogonal to size: chosen for isolation risk (611 manual where clauses, no global scope) |
| (d) schema-per-store | N× migrations (5 000 schemas × 165 tables = 825 k relations, catalog bloat) | needs UNION/fan-out or central copy | fan-out | per-schema restore possible | high | almost never; only if per-tenant restore is a hard requirement |
| (e) DB-per-store | N× migrations, orchestration | breaks everything in §5; needs central platform DB + sync | ETL needed | N backups | highest; connection count × N | only if contracts demand physical isolation |
| (f) few shards + store→shard map / dedicated DB for very large clients | per shard (2–4 runs) | central marketplace DB or fan-out to few shards | per shard + ETL | per shard | high, but only when needed | single primary saturates (CPU/IO/WAL) even after (a)+(b)+(g), or one client is >10–20 % of load |
| (g) split by workload (audit, notifications, integration events, analytics out) | 2 DBs, cross-DB FKs dropped | unchanged | analytics DB/warehouse | smaller main dump | low–medium | audit_logs + logs ≥ 30–40 % of size/writes; reports compete with POS |

[E] Expected outcome if the test confirms §3/§4: (a) + fix indexes/polling now; plan (b)/(g) for audit/fiscal before ~500 stores; (c) as a separate isolation decision; (d)/(e) not justified by size.

## Addendum (orchestrator, after the owner's push-back: "500 stores comes fast in marketplace mode; we are market AND POS")
- Unit of load is NOT "stores". Model three store types: market-only (~10–30 delivery orders/day, no tills), POS-only
  (~150 orders/day, 2–4 devices polling), hybrid (both; ONE menu, ONE kitchen, one `orders` table → a split by product
  line cuts through a store; natural cuts are by data nature (journals) or by store group (cells)).
- Test must use a mix (assumption until the owner gives numbers: year 1 = 2 000 market-only, 300 POS of which 200
  hybrid) and include a cross-workload scenario: marketplace spike (Telegram broadcast → Mini App browsing/search) DURING
  the POS lunch peak — measure whether till p95 moves (noisy neighbour), with and without reads on a replica/cache.
- Decision triggers to measure, not store counts: hot data size, primary CPU at peak, restore time (RTO), till p95 under
  marketplace spike, blast radius of one bad migration/lock.
- Path proposed to the owner: step 0 no-regret + shard-ready (store_id on every tenant table, tenant context + auto
  scope + RLS, indexes, ETag polling, pooling, PITR); step 1 journals (audit, fiscal, notification logs, histories)
  partitioned monthly / separate DB; step 2 by trigger: cells (several full DBs + small control DB with users, store
  directory/search read model, billing). DB-per-store and schema-per-store rejected (165 tables × N).
- Owner also asked to put "POS in local mode" on the table. Audit of what is local today → LOCAL_MODE_AUDIT.md
  (designs A cloud-first quieter / B hub-first / C full local server). Load test must add a comparison scenario: same
  order volume arriving as (1) today's per-device polling + interactive writes vs (2) hub-first: no device polling, one
  batched sync per store every N seconds — to give the owner the central-load difference as a number.
