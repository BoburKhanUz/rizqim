# RIZQIM "one big database": load test results (step 1 of test -> analyse -> decide)

Date 2026-11-14, repo at 3f57c5b (no tracked file was edited). **[M]** = measured here,
**[E]** = estimate/projection built on measured numbers. Raw text outputs are in `results/` next to this file; the kit that produced them is `scripts/scale-test/`.

## 1. Method

- **Box**: one container, 4 vCPU (shared by driver + PHP + Postgres), 16 GB RAM, PG 16 (shared_buffers 128 MB, default
  tuning, `pg_stat_statements` + `auto_explain` preloaded, max_connections 200, max_wal_size 256 MB to protect the disk).
- **App**: `php artisan serve` with `PHP_CLI_SERVER_WORKERS=4` (= 4 concurrent requests, like the default fpm
  `pm.max_children=5`), opcache on, `config:cache`/`route:cache`/`event:cache` (as the prod entrypoint does; cache files in
  the kit's `cache/`, gitignored). Redis on :16379 for cache/rate limiters/locks (prod uses redis). `QUEUE_CONNECTION=null`
  (queued jobs dropped: API latency without worker load), `BROADCAST_CONNECTION=null` (Reverb off = default deployment).
- **No external calls**: Telegram/FCM/SMS credentials empty on the command line, generated stores have no
  `telegram_bot_token`, fiscal = `sandbox` driver (in-process), payments cash only, and `http(s)_proxy=127.0.0.1:9`
  black-holes any outbound HTTP from PHP. Server log: 0 errors/exceptions in all runs.
- **Data** (`sql/gen.sql`): group = 20 market-only + 1 POS-only + 2 hybrid stores (the assumed 20:3 mix), 1 500
  customers, 30 business days ending yesterday. Market-only 11–30 Mini App orders/day (skew 10+20/sqrt(rank));
  POS ~150/day (128–200), 2–4 tills + KDS + customer display, 60–120 products; hybrid = POS + 15–25 Mini App orders/day on
  one menu. Lunch/dinner-humped hours, 5 % cancelled, 8 % split bills, 55/35/10 % cash/card/QR, 5 % offline orders
  (+4 sync ops). Row shapes (audit payloads, fiscal JSON lines, idempotency bodies, status notes) are copied from ONE
  real POS order and ONE real Mini App order made through the API (`template_order.mjs`). Rows per order match the real
  path: POS 1 order, 4 items, 3 status rows, 6.8 audit rows, 1.02 bills, 3.8 bill items, 1.02 payments, 1.02 fiscal
  receipts; Mini App 1 order, 2.5 items, 3.9 status rows, 3 audit, 6.6 notification_logs (one per store staff + customer).
  FK constraints stayed ON during generation (the generator itself caught two FK bugs), orphan check = 0.
- **Profiles** (cumulative): S = 1 group, 23 stores, 24 454 orders, 190 MB; M = 3 groups, 69 stores, 76 032 orders,
  549 MB; **L = 11 groups, 253 stores (220 market-only, 11 POS-only, 22 hybrid; 99 tills), 278 547 orders, 2 157 MB.**
- **Auth** (`sql/mint.sql`): tokens inserted the way Sanctum stores them (`sha256(plain)`, client sends `id|plain`),
  device tokens (`App\Models\Device`, ability `terminal`), cashier/cook PIN sessions (`staff-session` ability,
  `device_id`, 12 h) plus the `staff.session_started` audit row PosSyncApplier checks; one token per till/KDS/display/
  owner and 2 000 customers, so no per-user limiter was hit (0 x 429 in every run).
- **Driver** (`load.mjs`, Node 22 fetch): closed-loop virtual users with think times; per-endpoint p50/p95/p99,
  status counts; CPU seconds of all `php` and `postgres` processes from /proc; pg_stat_database deltas; pg_stat_activity
  every 5 s; pg_stat_statements reset per run. Each scenario: 300 s of arrivals + drain (wall 315–438 s).

Reproduce: `cd scripts/scale-test && ./run.sh S && ./run.sh M gen mint probe && ./run.sh L gen mint probe load pgbench`, then
`psql -d scale_big < sql/indexes.sql` and re-run `probe`, `L3`, `L4`, `pgbench` (`TAG=_after`). Details in README.md.

## 2. Scenarios run (all at L unless stated) [M]

| id | what | load |
|---|---|---|
| probe S/M/L | one client, sequential, same tenants (group 1) at each size, 15 reps x 22 endpoints | 330 req |
| L1 | POS lunch peak (all 33 POS stores, 99 tills: open -> round -> send -> bill -> cash -> close, think 8–20 s, gap 40–80 s) + polling fleet Reverb off (till floor/order 15 s, KDS 15 s, display 5 s, owner LiveOrders 2 queries/15 s) | 17.7 req/s |
| L2 | L1 + 12 owners' reports (analytics month, sales by day 30 d, top products 30 d) every 30 s | 18.6 req/s |
| L3 | L1 + marketplace spike: 250 Mini App customers ramped in at t=90–120 s, gone at 210 s (stores geo, catalog geo, store menu, own orders, notifications, 2 % place an order) | 22.8 req/s |
| L4 | marketplace only, 250 customers for 300 s | 33.7 req/s (saturated) |
| L5 | offline burst: 40 tills each upload 32 queued ops (8 orders) within 60 s + full polling fleet + reload | 19.2 req/s |
| L6 | hub-first stand-in: no device polling, one `POST /pos/sync` per store every 30 s carrying that store's orders (33 stores, ~1.4 orders/store/min) | 0.7 req/s |
| after | `sql/indexes.sql` applied, then probe, L3, L4, pgbench re-run | |

Not run: waiters' phones, Reverb-live variant, KDS bump/recall, QR/Click/Payme, delivery/courier flow, shift close/Z,
platform-admin dashboards, soak (2 h), any run with php-fpm. Concurrent scenarios ran only at L (S/M have only 3/9 POS stores).

## 3. Size: measured bytes per order (replaces the 12 KB estimate) [M]

Fresh, densely loaded rows incl. all indexes and TOAST, at L (S gave 8.96 / 4.70 KB, M 8.9 / 4.6 KB; the orders table's
21 indexes make it grow slightly with size):

| table | POS order: rows | bytes | Mini App order: rows | bytes |
|---|---|---|---|---|
| audit_logs | 6.80 | 3 014 | 3.00 | 1 329 |
| fiscal_receipts | 1.02 | 2 006 | – | – |
| orders | 1 | 1 370 | 1 | 1 370 |
| order_items | 4.00 | 1 161 | 2.50 | 726 |
| order_bill_items | 3.80 | 714 | – | – |
| order_status_histories | 2.95 | 626 | 3.90 | 828 |
| order_bills | 1.02 | 428 | – | – |
| payments | 1.02 | 289 | 1 | 283 |
| pos_sync_operations | 0.20 | 151 | – | – |
| notification_logs | – | – | 6.64 | 791 |
| **total permanent** | **~28 rows** | **9.76 KB** | **~18 rows** | **5.33 KB** |
| idempotency_keys (24 h TTL) | 5 | 9.1 KB transient | 1 | 1.8 KB transient |

- Journals (audit + fiscal + histories + notifications) = **58 % of POS bytes, 55 % of Mini App bytes**; audit_logs alone
  is the largest table (591 MB, 1.4 M rows, 290 MB of it indexes) at L.
- idempotency_keys store the whole response (1.8 KB/key, 5 per POS order): a store with 150 orders/day keeps ~6.8 MB in it
  at any time and writes ~1.4 MB/day that is deleted next day.
- Top tables at L: audit_logs 591 MB, orders 364 MB (250 MB = 21 indexes), fiscal_receipts 283 MB, order_items 254 MB,
  order_status_histories 191 MB, order_bill_items 101 MB, notification_logs 98 MB, payments 76 MB, order_bills 60 MB,
  idempotency_keys 51 MB. Top indexes: audit_logs (action,created_at) 75 MB (24 scans), order_status_histories
  (order_id,status) 68 MB, audit_logs (user_id,created_at) 57 MB (0 scans), (store_id,created_at) 54 MB (0 scans),
  the duplicated (model_type,model_id) pair 2 x 37 MB.
- [E] real tables carry update bloat (orders get ~6 UPDATEs, none HOT because every update touches an indexed column):
  budget x1.3 on top of the measured numbers.
- pg_dump -Fc of the 2.29 GB DB: 24.7 s, 139 MB output (~93 MB/s of DB size, single job) [M]; restore not measured.

## 4. Latency vs. data size, one client (probe) [M]

p50 / p95 ms, same store, same customer, same requests; `after` = L with the index experiment.

| endpoint | S (24k) | M (76k) | L (279k) | L after | verdict |
|---|---|---|---|---|---|
| GET /notifications (customer) | 51 / 67 | 77 / 96 | 170 / 211 | **42 / 46** | grew 3.3x with TOTAL table size -> fixed by index |
| GET /notifications (owner) | 52 / 64 | 97 / 109 | 224 / 323 | **37 / 51** | same |
| GET /stores?geo | 44 / 53 | 48 / 96 | 84 / 110 | 66 / 89 | grows with number of stores (23 -> 253): cross-tenant scan |
| GET /catalog?geo | 93 / 115 | 90 / 130 | 120 / 148 | 91 / 120 | grows with stores; response 129 KB already with 8 stores |
| GET /catalog?store_id | 78 / 95 | 92 / 111 | 100 / 143 | 87 / 100 | product_variants seq scan -> index |
| GET /orders (customer) | 54 / 72 | 77 / 92 | 87 / 112 | 74 / 95 | mild |
| GET /pos/floor | 53 / 62 | 54 / 65 | 61 / 74 | 52 / 64 | flat |
| GET /pos/orders, /:id | 40 / 51, 47 / 55 | 40 / 50, 47 / 58 | 50 / 58, 57 / 79 | 45 / 50, 48 / 63 | flat |
| GET /pos/display/state | 35 / 42 | 34 / 43 | 38 / 54 | 33 / 45 | flat |
| GET /staff/orders (LiveOrders) | 124 / 142 | 114 / 144 | 122 / 144 | 103 / 121 | flat, but 3x a till poll |
| GET /staff/kitchen/tickets | 105 / 131 | 125 / 160 | 187 / 243 | 259 / 301 | grows with TODAY's un-bumped orders (driver never bumps, board keeps paid counter orders 24 h): 63 -> 192 orders |
| GET /owner/reports/top-products (30 d) | 88 / 120 | 133 / 172 | 79 / 109 | 66 / 75 | per-store history, flat |
| GET /owner/analytics?period=month | 33 / 148 | 32 / 215 | 36 / 156 | 32 / 151 | cached; the miss is 150–215 ms |
| POST /pos/orders | 119 / 135 | 128 / 151 | 143 / 161 | 117 / 223 | flat (<1.25x) |
| POST /pos/orders/:id/send | 89 / 111 | 102 / 115 | 102 / 127 | 100 / 137 | flat |
| POST /pos/orders/:id/bills | 84 / 93 | 88 / 107 | 91 / 127 | 86 / 103 | flat |
| POST /pos/bills/:id/payments (+fiscal) | 159 / 197 | 174 / 194 | 200 / 267 | 170 / 199 | flat (<1.3x) |
| POST /pos/orders/:id/close | 59 / 69 | 69 / 85 | 71 / 85 | 60 / 79 | flat |

Per-request floor: the cheapest authenticated GET (`/pos/sync/status`) costs 37–43 ms p50, i.e. PHP bootstrap + a new
PG connection dominate small requests. Statements per request: 26 (probe), 27 (lunch), 13 (Mini App).

## 5. Concurrent scenarios at L (4 PHP workers, 4 vCPU shared) [M]

| run | req | req/s | php CPU s | pg CPU s | stmts/req | max PG conns | lock-wait samples | 5xx/429 | POST /pos/orders p50/p95/p99 | payments p50/p95/p99 | floor p95 | KDS p95 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| L1 lunch+poll | 7 578 | 17.7 | 318 | 195 | 27.1 | 5 | 0 | 0 | 150 / 200 / 227 | 204 / 274 / 311 | 75 | 115 |
| L2 + reports | 7 985 | 18.6 | 342 | 236 | 27.4 | 5 | 0 | 0 | 159 / 212 | 212 / 283 | 82 | 144 |
| L3 + market spike | 9 958 | 22.8 | 425 | 374 | 23.4 | 9 | 0 | 0 | 168 / **9 162** | 229 / **8 651** | 8 424 | 8 813 |
| L3 after indexes | 11 508 | 26.4 | 510 | 255 | 23.5 | 6 | 0 | 0 | 169 / **3 695** | 223 / 3 505 | 3 547 | 3 600 |
| L4 market only | 10 665 | 33.7 | 482 | 672 | 12.9 | 12 | 0 | 0 | Mini App p50 2.3–3.3 s, p95 3.8–6.1 s | | | |
| L4 after indexes | 15 703 | **50.0** | 782 | 289 | 12.8 | 6 | 0 | 0 | Mini App p50 0.30–0.49 s, p95 0.78–1.04 s | | | |
| L5 offline burst | 6 058 | 19.2 | 262 | 154 | 29.0 | 5 | 1 | 0 | sync call p50 1 742 / p95 2 412 / max 3 064 | | 73 (p99 519) | 204 |
| L6 hub-first | 228 | 0.7 | 41 | 46 | 297 | 2 | 0 | 0 | sync call p50 306 / p95 371 / p99 392 | | – | – |

- Error rate 0 % in every run (no 5xx, no 429, no timeouts); 1 280 / 1 280 burst ops and 912 / 912 hub ops `applied`.
- Owner reports during the peak (L2): analytics p95 179 ms, sales 82 ms, top-products 94 ms; till write p95 +6 %
  (200 -> 212 ms), KDS p95 +25 %. Reports are not the problem at this size.
- **Marketplace spike (L3) phase view, all till+poll requests** (p50 / p95 / p99 ms):

| phase | total req/s | before indexes | after indexes |
|---|---|---|---|
| before spike (0–90 s) | 24 | 56 / 156 / 208 | 59 / 170 / 246 |
| during spike (90–210 s) | 42 -> 55 after | **6 868 / 9 031 / 9 487** | **2 957 / 3 667 / 3 789** |
| after spike (backlog drain) | 12 | 114 / 8 998 / 9 217 | 70 / 1 809 / 2 622 |

  The tills share the same 4 PHP workers and 4 cores with the Mini App: a Telegram-broadcast-style spike moves till p95
  from 0.16 s to 9 s (3.7 s after the indexes). Before the indexes Postgres was the bigger CPU consumer of Mini App
  traffic (63 ms pg CPU per Mini App request vs 45 ms PHP); after, PHP is (50 ms PHP vs 18 ms pg).
- Offline burst (L5): 40 tills x 8 orders drain in p50 1.9 s / p95 3.2 s / max 4.1 s per till incl. reload (target
  < 2 min: met by far); during the burst the fleet's floor poll p99 rose to 519 ms; 1 lock-wait sample; no deadlocks.
- Where the time goes (L1): pg_stat_statements total execution = 17.2 s of 428 s, while the postgres processes used 195 CPU
  s: **~90 % of Postgres CPU is per-connection overhead** (fork, SCRAM auth, catalog cache warm-up, parse/plan), because
  every PHP request opens a new connection. pgbench: `select 1` on a kept connection 0.05 ms / 159 000 tps vs
  reconnect-per-transaction (`-C`) 20.4 ms connect, **196 tps on 4 cores**.
- Hot statements under the lunch peak (L1, by total time): open-orders list of a branch (1 069 calls, 2.1 ms),
  **Eloquent `getColumnListing` introspection (3 183 calls, 0.65 ms, 88 k rows)**, audit_logs insert (2 001, 0.6 ms),
  products+brands channel check (546, 1.35 ms), `UPDATE orders SET version` (1 092), **`UPDATE personal_access_tokens SET
  last_used_at` (4 953 = one write per request, polls included)**, ShiftReport voided lines (9 calls, 52 ms).

## 6. Slow queries and plans (auto_explain at L, ANALYZE+BUFFERS; full text in `results/explain_L.txt`) [M]

1. `select * from notification_logs where user_id=$1 order by id desc limit 20` — 79 ms mean, 122 ms max: Index Scan
   Backward on the PK, **Rows Removed by Filter 833 228**, 14 409 buffers. Polled every 15 s by every open Mini App/manager.
2. `select count(*) from notification_logs where user_id=$1 and is_read=$2` — 37 ms: Parallel Seq Scan, 10 216 buffers.
3. `select count(*) from notification_logs where user_id=$1` — 29 ms: Parallel Seq Scan. (1–3 = one feed call: 145 ms of DB.)
   After `(user_id, id)` index: 0.33 / 0.31 ms (pgss, L4 after); pgbench Mini App poll **25.7 -> 9 709 tps**.
4. ShiftReport voided lines (`GET /pos/shifts/current`, X report): 109–119 ms, Nested Loop over the branch's whole
   history, 18 786 buffers read — grows with branch age. Partial index on `order_items(voided_at)`.
5. Top products 30 d: 32–51 ms, top-N sort over the store's 30-day items (per-store bound, flat with DB size).
6. Analytics prep/delivery time (orders x order_status_histories, 30 d): 83 ms, only on cache miss.
7. Marketplace catalog `select * from products where status=$1 and exists(stores open now ...)`: 5.3 ms mean in L4
   after, the #1 statement by total time (16.8 s of 300 s) — scans products of ALL open stores per request (12 940
   products at 253 stores), grows linearly with marketplace size.
8. Store list (`/stores?geo`, haversine over all active stores): 2.4 ms mean, #2 by total — same growth.
9. `select * from product_variants where product_id in (...)`: 2.3 ms seq scan before, 0.42 ms after index.
10. Branch open-orders list (`orders where branch_id and channel in and status not in ... order by created_at`): 2.1 ms
    under load (uses branch_id,status,created_at; `status NOT IN` forces a range over all statuses of the branch).

## 7. Index experiment (scale_big only, `sql/indexes.sql`) [M]

| statement | size | effect |
|---|---|---|
| `CREATE INDEX notification_logs_user_id_id_index ON notification_logs (user_id, id);` | 26 MB | feed 79 -> 0.3 ms; /notifications p50 170 -> 42 ms (probe), Mini App capacity 33.7 -> 50 req/s, pg CPU per Mini App req 63 -> 18 ms; spike till p95 9.0 -> 3.7 s |
| `CREATE INDEX product_variants_product_id_index ON product_variants (product_id);` | 0.3 MB | 2.3 -> 0.42 ms per menu load (2 per catalog request) |
| `CREATE INDEX products_store_id_status_index ON products (store_id, status);` | 0.1 MB | store menu `where store_id and status` 2.2 ms seq scan -> index; /catalog?store_id p50 100 -> 87 ms |
| `CREATE INDEX order_items_voided_at_partial_index ON order_items (voided_at) WHERE voided_at IS NOT NULL;` | 8 KB | ShiftReport 110 ms branch-history scan -> index (no voids in the data set) |
| `CREATE INDEX payments_order_id_index ON payments (order_id);` | 6.2 MB | FK hygiene (close/refund lookups, parent deletes) — no latency change measured |
| `CREATE INDEX notification_logs_order_id_index ON notification_logs (order_id);` | 9.1 MB | FK hygiene — no latency change measured |
| `DROP INDEX audit_logs_model_type_model_id_index;` (exact dup of audit_logs_model_index) | -37 MB | one index write less per audit row (7 per POS order) |
| `DROP INDEX order_items_order_id_index;` (prefix of (order_id, client_line_id)) | -20 MB | one index write less per line; plans switched to the composite, no regressions seen |

Net index size -12 MB (454 -> 442 MB on the touched tables). pgbench DB-only before -> after: Mini App poll 25.7 ->
9 709 tps; till poll 9 313 -> 8 649 tps (noise, unchanged); top-products report 749 -> 716 tps; one POS order's writes
in one transaction 181 -> 173 tps (noise; the dropped duplicates did not show up at 8 clients). **Pays off for the
migration: the first four CREATE INDEX lines and the two DROPs.** Not tried (needs code): ETag/`since` cursors,
catalog/store-list read model.

## 8. Cloud-first vs hub-first (the "local mode" question) [M, same data, same order rate]

| per store (33 POS stores, 3 tills + KDS + display + 1 owner tab) | cloud-first today (L1) | hub-first stand-in (L6) | ratio |
|---|---|---|---|
| orders/store/min (achieved) | 1.16 (273 orders / 428 s wall) | 1.27 (228 / 327 s) | |
| requests/store/min | **32.2** (78 % of them polls) | **1.3** | 25x fewer |
| requests per order | 27.8 | 1.0 (4 ops/order, ~1.4 orders per batch) | |
| SQL statements per order (pgss) | 752 | 297 | 2.5x fewer |
| DB transactions per order (pg_stat_database) | 1 631 | 203 | 8x fewer |
| CPU per order: PHP / Postgres | 1 163 / 714 ms | 181 / 200 ms | 4.9x less |
| rows inserted / updated per order | 31 / 56 | 25 / 25 | inserts ~same, updates 2.2x fewer |
| p95 of the till's own calls | writes 166–274 ms, polls 55–115 ms | 371 ms per batch call (staff see it 30 s later) | |
| peak PG connections | 5 | 2 | |

Reading: the central DB writes about the same rows either way; what hub-first removes is the polling (78 % of requests)
and the per-request overhead (connection + bootstrap). [E] Design A (cloud-first + ETag/Reverb + pooling) should capture
most of the same CPU saving without the L–XL hub work: polling is 78 % of requests, and ~90 % of Postgres CPU is
connection overhead that a pooler removes. Caveat: L6 used the existing sync endpoint, whose per-op work includes
offline-specific checks (price history, staff 36 h session audit); a real hub API could be cheaper or dearer.

## 9. Projections from measured numbers [E]

Assumptions: market-only store 20 Mini App orders/day; POS store 150 POS orders/day; hybrid = both; 2/3 of POS stores
hybrid; 365 days; measured 9.76 KB / 5.33 KB per order x1.3 bloat; peak hour = 15 % of the day's Mini App orders, each
Mini App order = ~45 min of an open app at 0.17 req/s (measured loop: 6 requests per ~35 s); POS store at lunch =
0.54 req/s measured (L1) + 2 waiter phones ~0.13 req/s; market-store staff app open at peak for 50 % of stores at
0.13 req/s; Reverb off. CPU/request measured: lunch mix 68 ms (42 PHP + 26 PG), Mini App 68 ms after indexes (50 + 18).

| | (i) 500 market + 50 POS | (ii) 2 000 market + 300 POS (200 hybrid) | (iii) 5 000 market + 1 500 POS (1 000 hybrid) |
|---|---|---|---|
| orders/day (Mini App / POS) | 10 660 / 7 500 | 44 000 / 45 000 | 120 000 / 225 000 |
| orders/year | 6.6 M | 32.5 M | 126 M |
| DB growth/year (x1.3) | **62 GB** | **320 GB** | **1.35 TB** |
| of which journals (audit, fiscal, histories, notifications) | ~35 GB | ~180 GB | ~760 GB |
| peak req/s: POS / staff apps / Mini App | 34 / 35 / 204 = **~270** | 201 / 143 / 841 = **~1 190** | 1 005 / 390 / 2 295 = **~3 700** |
| CPU cores at peak, today's code (68 ms/req, 26–63 ms of it PG) | ~18 (PG 7–17) | ~80 (PG 31–75) | ~250 (PG 100–230) |
| [E] same request count with pooler + indexes + $fillable (~30 ms/req, ~6 ms PG); ETag/Reverb would cut POS requests a further ~4x | ~8 (PG ~1.6) | ~36 (PG ~7) | ~110 (PG ~22) |
| pg_dump single job at 93 MB/s of DB size, end of year 1 | ~11 min | ~1 h | ~4 h (restore [E] 2–4x longer) |

Where one primary bites first, in order [E]: (1) **connections/CPU per request** — at (ii) without a pooler PG needs
30+ cores just for connection churn, and fpm `max_children` x containers must stay under max_connections (100 in prod
compose); (2) **Mini App polling CPU** (notifications seq scan grows with the table: at (ii) year 1 notification_logs
~100 M rows — without the index each poll would read the whole table); (3) **restore time / RTO** at (ii)-(iii)
(nightly pg_dump becomes hours: switch to basebackup + WAL/PITR); (4) **disk** at (iii) (TB/yr, 56–58 % journals:
partition or move audit/fiscal/notifications); data size itself is not the first limit at (i)–(ii).

## 10. Limits of this environment (cannot be proven here)

- Real concurrency: 4 php -S workers on 4 shared vCPU, driver on the same box; no php-fpm, nginx, multi-container,
  network latency. Throughput numbers are per-box relative costs, not a capacity statement for production hardware.
- Cache behaviour with data >> RAM (2.2 GB DB in 16 GB RAM, ~99 % buffer hit), WAL/checkpoint pressure, replication,
  read replicas, PgBouncer itself, restore time, Reverb fan-out, queue workers (jobs were dropped).
- Data is dense (no months of update bloat/autovacuum history); 30 days per store, not a year (per-store history queries
  like top-products/ShiftReport will be ~12x heavier for year-old stores).
- Driver gaps: no KDS bumps (so the KDS board accumulated today's paid counter orders), no waiters, no QR/card flows,
  no delivery/courier, no Reverb-live variant, no admin dashboards.
- Side effects on the repo's ignored files before env isolation was in place (first migrate+seed, 16:49): PosStackSeeder
  overwrote `backend/storage/app/pos-e2e-seed.json` and appended to `backend/storage/logs/laravel.log` (both gitignored;
  git status clean; the e2e script regenerates the seed file). Everything after that ran with `LOG_CHANNEL=stderr`,
  `E2E_POS_SEED_FILE` and `APP_*_CACHE` pointing into the kit's own folders.

## 11. Findings, ranked

1. **New PG connection per request** — evidence: pgbench connect 20.4 ms, 196 conn/s on 4 cores; L1 PG CPU 195 s vs 17 s
   of statement time. Impact: dominates PG CPU at any scale; caps request rate. Fix: PgBouncer (transaction mode;
   `max_prepared_statements` or `PDO::ATTR_EMULATE_PREPARES`, see DB_SCALE_PLAN 6) or persistent connections. Effort: S.
2. **notification_logs has no user index** — evidence: 833 k rows filtered per feed call, 145 ms DB per poll at 279 k orders,
   Mini App DB capacity 25.7 tps; after index 9 709 tps, Mini App capacity +48 %, spike till p95 -59 %. Impact: grows with
   the total table (not per tenant) — the one query that does not scale. Fix: index #1 (+ retention). Effort: XS.
3. **Marketplace spike starves the tills** (same PHP pool, same DB) — evidence: till p95 0.16 s -> 9.0 s (3.7 s after
   indexes). Impact: noisy neighbour across product lines. Fix: separate PHP pools/containers for `/v1/pos/*` and Mini
   App, catalog/store-list cache, per-route concurrency limits; later a read replica for marketplace reads. Effort: S–M.
4. **Polling is 78 % of requests** (32 req/store/min at lunch, every response full state) — evidence: L1 vs L6 (25x fewer
   requests, 4.9x less CPU per order). Fix: ETag/`since` on the ~8 polled endpoints + Reverb on by default (design A). Effort: M.
5. **Cross-tenant marketplace queries grow with store count** — `/stores` (all stores, haversine) and `/catalog` (products
   of all open stores, 129 KB response with 8 stores): #1/#2 statements by total time in L4 after. Fix: cached/
   precomputed store directory + catalog read model per area, pagination. Effort: M.
6. **Eloquent `$guarded` triggers schema introspection** — `GuardsAttributes::isGuardableColumn` runs `getColumnListing`
   once per model class per request (statics reset per request): 3 183 calls in L1 (0.4 per request, 0.65 ms each, 88 k
   catalog rows) and PHP time. Fix: `$fillable` (or `$guarded = []`) on hot models (Order, OrderItem, OrderBill,
   Payment, FiscalReceipt, IdempotencyKey, PosShift...). Effort: S.
7. **A DB write on every authenticated request** (`personal_access_tokens.last_used_at`, 4 953 updates in L1, 14 049 in L4) —
   fix: throttle the update (e.g. once per 5 min per token) or drop it for device/staff tokens. Effort: XS.
8. **Journals dominate size** (58 % of POS bytes; audit_logs 6.8 rows/POS order, append-only trigger) and idempotency
   keys store whole responses (9 KB per POS order, transient). Fix: monthly partitioning of audit_logs/fiscal_receipts/
   notification_logs (drop-partition retention works with the append-only trigger), drop the 0-scan audit indexes
   ((user_id,created_at), (store_id,created_at) unless the UI needs them), store a hash + status instead of the full
   idempotent body where the client can re-read. Effort: M.
9. **Redundant indexes** on write-hot tables (audit_logs model pair 2x37 MB, order_items(order_id)) — drop (in the
   migration). Effort: XS.
10. **Per-store history scans**: ShiftReport voided-lines 110 ms over the branch's whole history (index #4), top-products/
    analytics 30–80 ms per store-month (will be ~12x for a year at 30-day windows only if windows grow). Effort: XS–S.
11. **KDS board keeps paid counter orders for 24 h until bumped** — KDS poll cost rose with the day's un-bumped orders
    (115 -> 259 ms p95 at 63 -> 192 orders). Worth a cap/limit on the board query. Effort: XS.
12. Offline burst, reports during peak, locking: **fine** — 40 tills drain in < 5 s, 0 deadlocks, 1 lock-wait sample;
    reports moved till p95 by 6 %. No limiter value blocked lunch traffic (api 300/min/user, pos-sync 120/min/device;
    PIN sign-in is 60/min per IP, which a food court behind one NAT could hit at shift start — not exercised). 0 errors.

Bottom line for step 2 [E]: at 279 k orders/253 stores, per-tenant POS latency is flat (S -> L within 1.0–1.3x); the
things that grow are global scans (notifications, store/catalog lists) and per-request overhead, all fixable inside one
database. One Postgres is not the limit for (i) and, with the fixes in 1–7, plausibly not for (ii); (iii) needs
journal partitioning/separation and PITR, and the request-rate numbers above, before any tenant split is justified.
