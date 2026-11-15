# RIZQIM wave M1: before/after load measurement

Date 2026-11-15, repo at 4fdcd66 (branch claude/zealous-maxwell-p1cfsk). Baseline = `RESULTS.md` (2026-11-14, 3f57c5b).
**[M]** = measured here, **[E]** = estimate. Raw text outputs are in `results_m1/` (`compare.md` = all tables side by side).
Same 4 vCPU box shared by the driver, PHP, PgBouncer, nginx and Postgres: every number is a relative cost on this box, not a
production capacity statement.

## 1. Method and what differs from the baseline

- **Data**: L profile generated fresh by the kit (`run.sh L base gen mint`) on a database migrated with ALL current
  migrations (the six M1 indexes present, the two duplicate indexes gone): 11 groups, 253 stores, 284 194 orders (baseline
  278 547; same generator, the 30-day window ends on a different day), 1 985 MB (baseline 2 157 MB), FK orphan check 0.
- **Scenarios and durations as the baseline**: probe (15 reps x 22 endpoints), L1 lunch+poll, L3 lunch+poll+250-customer
  spike at t=90-210 s, L4 250 customers for 300 s; 300 s of arrivals + drain; `pg_stat_statements` reset per run.
- **Four serving modes**: `serve-direct` = the baseline mode (`php artisan serve`, 4 workers, a new PG connection per
  request); `fpm-pgb` = what ships (the repo's nginx + critical/market/default pools at their defaults 4+4+4, market limits
  in-flight 16, 5 r/s + 20 burst per client; PgBouncer transaction mode, pool 20); for L3 only also `fpm-direct` and
  `serve-pgb` to split the gain.
- **Kit fixes** (`scripts/scale-test/`, no product code): (1) `base.sh` starts the kit's Redis before the seeder (the
  seeder's orders read the cache; it only worked before because Redis was already running). (2) `load.mjs`: every
  market customer sends its own `X-Forwarded-For` (100.64.x.y) from 127.0.0.1, a trusted proxy for nginx `real_ip` and
  Laravel. Without it all 250 customers share one address and nginx's per-address market limits (8 in flight, 50 r/s)
  would shed traffic that in production comes from 250 addresses; `XFF=0` turns it off (not measured). (3) `load.mjs` also
  records the `Retry-After` of 503/429 answers, the CPU of `pgbouncer` and `nginx` processes, and market status per spike
  phase. The new CPU columns are extra: "pg CPU" still counts postgres processes only, as before.
- Load average before every run: 0.14-1.8 (1-min, residual from the previous run; nothing else ran). One probe started at
  3.07 right after L4 and was repeated (`probe_fpm-pgb_rep`, same result within 10 %).
- L3 in `fpm-pgb` was run three times because the market shed rate turned out noisy; all three are reported.

## 2. Headline: the numbers the owner asked for [M]

| metric | baseline (RESULTS.md) | M1 code, old mode (serve-direct) | M1 as shipped (fpm-pgb) |
|---|---|---|---|
| till+poll p95 during the market spike (L3) | 9 031 ms (3 667 ms with indexes by hand) | 4 534 ms | **186 / 149 / 161 ms** (3 runs) |
| POST /pos/orders p95 in L3 | 9 162 ms (3 695) | 4 565 ms | **174 / 134 / 153 ms** |
| till+poll p95 before the spike (L3) | 156 ms | 150 ms | 122 / 103 / 114 ms |
| Postgres CPU s, L1 lunch (7.6 k requests) | 195 s | 151 s | **66 s** (+16 s PgBouncer) |
| PHP CPU s, L1 | 318 s | 269 s | 274 s |
| statements per request, L1 | 27.1 | 26.1 | 27.3 |
| `getColumnListing` statements, L1 | 3 183 | **0** | **0** |
| `UPDATE personal_access_tokens last_used_at`, L1 | 4 953 | 266 | 258 |
| L4 marketplace throughput | 33.7 req/s (50.0 with indexes) | 49.5 req/s, 0 shed | **53.3 req/s**, 1.2 % shed |
| L4 Mini App p50 / p95 | 2.3-3.3 s / 3.8-6.1 s (0.30-0.49 / 0.78-1.04 s) | 0.33-0.58 / 1.05-1.22 s | **0.05-0.11 / 0.18-0.25 s** |
| L4 Postgres CPU s | 672 s (289 s) | 283 s | **84 s** (+20 s PgBouncer) |
| peak PG connections L1 / L3 / L4 | 5 / 9 / 12 | 5 / 6 / 6 | 6 / 8 / 6 |
| till/POS/staff errors (5xx, 429, timeouts) | 0 | 0 | **0 in every run** |
| market requests shed in the L3 spike (default limits) | 0 (they waited 9 s instead) | 0 | **16.4 % / 4.2 % / 8.3 %** of 5.9 k |

What a customer sees when shed: **503**, `Retry-After: 2`, JSON `error: server_busy`, message "Hozir so'rovlar juda ko'p.
Bir necha soniyadan keyin qayta urinib ko'ring." All shed answers were the pool-wide in-flight limit (`rizqim_market_all`);
0 x 429 in every run (a customer with 3-8 s think time never reaches 5 r/s). The driver does not retry; the Mini App retries
reads (react-query, 3 times), so the share of screens that finally fail is lower [E]. Lock-wait samples 0 in every run.

## 3. L3 market spike: attribution of the two halves [M]

| L3 run | req/s | php / pg / pgbouncer CPU s | stmts/req | till+poll p95 before / during / after spike | market 2xx served | market shed | Mini App p95 (stores?geo) |
|---|---|---|---|---|---|---|---|
| serve-direct (old mode) | 26.8 | 514 / 244 / - | 21.7 | 150 / **4 534** / 3 792 | 3 909 | 0 | 4 504 ms |
| serve-pgb (pooler only) | 30.1 | 486 / 87 / 21 | 21.0 | 121 / **950** / 130 | 5 630 | 0 | 936 ms |
| fpm-direct (pools only) | 31.1 | 521 / 247 / - | 19.1 | 160 / **283** / 173 | 3 633 | **2 214 (37.9 %)** | 680 ms |
| fpm-pgb run 1 | 30.6 | 506 / 92 / 24 | 20.0 | 122 / **186** / 138 | 4 923 | 968 (16.4 %) | 444 ms |
| fpm-pgb run 2 | 31.3 | 460 / 81 / 20 | 20.9 | 103 / **149** / 110 | 5 751 | 254 (4.2 %) | 290 ms |
| fpm-pgb run 3 | 31.5 | 480 / 86 / 22 | 21.0 | 114 / **161** / 119 | 5 409 | 487 (8.3 %) | 335 ms |
| fpm-pgb, `NGINX_MARKET_MAX_INFLIGHT=32` | 31.3 | 471 / 83 / 21 | 21.3 | 104 / **161** / 116 | 5 676 | **99 (1.7 %)** | 629 ms |

- **PgBouncer** removes ~65 % of Postgres CPU in every scenario (L1 151 -> 66 s, L3 244 -> 87 s, L4 283 -> 84 s) and
  pg_stat_database transactions drop 2.8x (L1 430 k -> 153 k: connection start-up work disappears). Alone it gives the
  market ~45 % more served requests in the spike and cuts the till's spike p95 4.5 s -> 0.95 s, because the shared 4 CPUs
  are no longer burnt on connection set-up. It does not isolate the tills: they still queue behind market requests.
- **FPM pools** are what isolates the tills: with them the till spike p95 is 0.15-0.28 s whatever the DB path; the tills'
  `critical` pool is never touched by the market and its workers win the CPU (market workers at nice 10). The price is
  paid by the market: direct DB + pools sheds 38 % of spike requests, because each market request costs more CPU.
- **Together** (fpm-pgb) the till p95 during the spike is 150-190 ms, about what it is with no spike at all
  (100-120 ms before it), while 4.9-5.8 k market requests are served vs 3.9 k in the old mode.
- **Shed rate at the defaults is noisy (4-16 %) and, for 250 customers, high**: in-flight 16 = 4 workers + 12 waiting,
  and a 30-s ramp of 250 customers on 4 shared vCPU overruns it in bursts (`server reached max_children` every run).
  One tuned setting measured: `NGINX_MARKET_MAX_INFLIGHT=32` (per-client 8, per-IP 16 derived; `pools.sh` allows up to
  workers + backlog = 36): shed 1.7 %, till p95 unchanged (161 ms), market latency up (stores?geo p95 290-444 -> 629 ms).
  A queue of 32 costs a waiting customer about 0.6 s instead of a "busy" message. Not changed in the repo: owner's call.

## 4. L1 lunch and L4 marketplace [M]

| run | req | req/s | php / pg / pgbouncer / nginx CPU s | stmts/req | POST /pos/orders p50/p95/p99 | payments p50/p95/p99 | floor p95 | KDS p95 |
|---|---|---|---|---|---|---|---|---|
| L1 baseline | 7 578 | 17.7 | 318 / 195 / - / - | 27.1 | 150 / 200 / 227 | 204 / 274 / 311 | 75 | 115 |
| L1 serve-direct | 7 584 | 17.2 | 269 / 151 / - / - | 26.1 | 111 / 150 / 202 | 151 / 198 / 225 | 64 | 78 |
| L1 fpm-pgb | 7 584 | 17.8 | 274 / 66 / 16 / 3 | 27.3 | 96 / 133 / 180 | 150 / 199 / 240 | 46 | 102 |
| L4 baseline / with indexes | 10 665 / 15 703 | 33.7 / 50.0 | 482 / 672 (782 / 289) | 12.9 / 12.8 | Mini App p50 2.3-3.3 s / 0.30-0.49 s | | | |
| L4 serve-direct | 15 453 | 49.5 | 811 / 283 / - / - | 11.9 | Mini App p50 326-578 ms, p95 1 052-1 217 ms | | | |
| L4 fpm-pgb | 16 737 | 53.3 | 743 / 84 / 20 / 6 | 11.8 | Mini App p50 51-109 ms, p95 183-248 ms; 201 shed (1.2 %) | | | |

- **Statements per request did not fall much** (27.1 -> 26.1-27.3): `$fillable` removed the 3 183 introspection queries
  and the throttle removed 4 687 token writes (together ~8 k of ~205 k statements, i.e. ~1 per request); the remaining ~26
  are real reads. `set search_path`, the token lookup and the user lookup still run on every request (1 each). Through
  PgBouncer L1 shows ~1 statement/request more than direct; not explained (L3/L4 show no such difference).
- **PHP CPU per request is now the bottleneck**: L1 ~36 ms PHP vs ~11 ms PG+PgBouncer per request; L4 44 ms PHP vs 6 ms
  per Mini App request. M1 cut PHP CPU 15 % (318 -> 269-274 s in L1) and Postgres CPU 66 %.
- L4 throughput is CPU-bound by PHP on this box: 53 req/s with 743 s PHP CPU in 314 s = 2.4 of the 4 cores.
- `devices.last_seen_at` updates: 165 per L1 run (once a minute per device, as documented).

## 5. Single client (probe), p50 ms [M]

| endpoint | L baseline | L baseline + indexes | serve-direct | fpm-pgb (repeat) |
|---|---|---|---|---|
| GET /notifications (customer / owner) | 170 / 224 | 42 / 37 | 32 / 30 | 19 / 20 |
| GET /pos/sync/status (cheapest authenticated GET) | 37-43 | - | 30 | 19 |
| GET /pos/floor | 61 | 52 | 44 | 26 |
| GET /stores?geo / catalog?geo / catalog?store_id | 84 / 120 / 100 | 66 / 91 / 87 | 60 / 82 / 68 | 47 / 71 / 52 |
| POST /pos/orders / payments | 143 / 200 | 117 / 170 | 89 / 129 | 72 / 105 |
| GET /staff/kitchen/tickets | 187 | 259 | 58 | 112-142 |

The per-request floor fell from ~40 ms to ~20 ms (no 20 ms connection). KDS tickets in the probe vary with how many of
today's orders earlier runs left on the probe store's board (the driver never bumps): not a mode effect.

## 6. pgbench: connection cost and DB-only capacity [M] (`results_m1/pgbench_L_m1.txt`)

| test (8 clients, 4 threads) | baseline | M1 |
|---|---|---|
| `select 1`, kept connection, direct | 0.050 ms, 159 k tps | 0.052 ms, 154 k tps |
| `select 1`, new connection per transaction (`-C`), direct | 20.4 ms connect, 196 tps | 19.4 ms connect, 206 tps |
| same through PgBouncer (md5 client auth) | - | **0.32 ms connect, 12 028 tps** |
| `select 1`, kept connection through PgBouncer | - | 0.21 ms, 37.6 k tps (+0.16 ms per round trip) |
| Mini App poll / till poll | 25.7 (9 709 after) / 9 313 tps | 11 002 / 9 031 tps |
| top-products report | 749 (716 after) tps | **523 tps (worse, -27 %)** |
| one POS order's writes | 181 (173 after) tps | 1 587-1 619 tps |

The report regression and the 9x write gain are DB-only microbenchmarks with no schema difference against the baseline's
"after" run on those tables (same index counts, same trigger): neither is explained or claimed for M1. The app-level
top-products probe did not regress (58 ms p50 vs 66-79 ms).

## 7. What did not improve, or got worse

- **Market shed**: 4-16 % of spike requests at the default limits (was 0 %, because customers waited 3-9 s instead). By design,
  but at the high end for 250 customers; in-flight 32 brings it to 1.7 %.
- **Statements per request** unchanged in practice (26-27 at lunch, 12 in the marketplace).
- **PHP CPU per request** only -15 %: it is now 3-7x the Postgres+PgBouncer cost and the first limit on this box.
- **KDS poll p95 in L1** 78 ms (serve) vs 102 ms (fpm-pgb); probe 58 vs 112-142 ms. Partly board growth between runs;
  the fpm mode was not measured first, so no clean comparison.
- **Kept-connection round trip** through PgBouncer +0.16 ms (12-27 SQL per request: +2-4 ms), as OPERATIONS.md says.
- **Peak PG connections** in L3 8 (fpm) vs 6 (serve): more PHP workers in flight (12 vs 4), far under the pool of 20.

## 8. Limits

4 vCPU shared by everything, one box, no network latency, nginx and PgBouncer on the same cores as PHP and Postgres;
Reverb and queue workers off; data dense and 30 days old; customers do not retry after 503; no waiters, KDS bumps, card/QR,
delivery or admin flows; L2/L5/L6 were not re-run (not in the M1 brief). Shed rates moved 4x between identical runs:
treat any single figure as a range.

## 9. What M2 should target (from the new hot statements)

1. **Marketplace catalog query**: `products where status and exists(stores open now ...)` = 5.0 ms x 3 324 calls =
   16.8 s of 35 s total SQL in L4 (48 %), returning 50 products per call from all open stores; and the store list
   (haversine over all stores, 84 rows per call) 2.3 ms = 7.9 s (23 %). Read model / cache per area + pagination.
2. **PHP cost per request** (36-44 ms CPU): per-request bootstrap + the three per-request lookups (`set search_path`, token,
   user) and Eloquent hydration of full catalog rows; profile the Mini App catalog/store-list controllers first.
3. **Polling**: 78 % of lunch requests are still polls (ETag/`since` + Reverb, RESULTS.md finding 4); branch open-orders list
   (`orders where branch_id and channel in and status not in`) is the #1 lunch statement (2.3 ms x 1 073 = 25 % of L1 SQL).
4. **Market limit sizing**: decide `NGINX_MARKET_MAX_INFLIGHT` (16 vs 32) and `FPM_MARKET_CHILDREN` against real core counts;
   re-measure with the Mini App's retry behaviour in the driver.
5. Report query regression in pgbench (top-products 749 -> 523 tps): check its plan on a fresh L database before M2 work.
