# RIZQIM wave M2-4: PHP cost per request

Date 2026-11-15, branch claude/zealous-maxwell-p1cfsk (stage A, with the other three M2 agents' work in progress in the
same tree). **[M]** = measured here, **[E]** = estimate. Same shared 4 vCPU box as `RESULTS_M1.md`; three other agents ran
tests and builds at the same time (1-min load 0.3-5.4 during the runs), so only paired, CPU-time numbers are used.

## 1. Method

- **Data**: the kit's S profile (`run.sh S base gen mint`, 23 stores, ~25 k orders) in its own database, PgBouncer
  (transaction mode), php-fpm with the repo's pools (`SERVER=fpm`), `APP_ENV=production`, the kit's opcache ini off
  (`FPM_KIT_INI=0`, so php-fpm runs on the image's `backend/docker/php.ini` only).
- **Profiler** (new, kit only, `scripts/scale-test/php/prof/`): `PROF=cpu ./server.sh start` adds an `auto_prepend_file`
  that logs each request's CPU time (`getrusage` user+sys of the worker, the only number robust to a busy machine) and wall
  time; `PROF=1` serves the request through an instrumented copy of `public/index.php` that also logs the phases (each
  bootstrapper, each service provider's register+boot, global middleware, route middleware + controller, send,
  terminate), every SQL statement and the autoloaded classes; `PROF_TL=1` adds a timeline of every object the container
  resolves. `php/prof/report.php` summarises. Never loaded by the image or the tests.
- **Paired runs**: two php-fpm stacks side by side (same DB and PgBouncer, own Redis and ports), one sequential probe client
  each (the kit's `probe` scenario: 22 requests per rep incl. a full POS order cycle), started together so machine noise
  hits both alike. An A/A pair (identical configs) gave per-route medians within ±3-7 % and totals within ±0.2 %; every
  change below was run at least twice, with the sides swapped.
- Requests per route: 20-30 per side per run (medians). `GET /staff/kitchen/tickets` grows with every rep (the probe never
  bumps tickets) and is left out of totals.

## 2. Where a cheap authenticated GET spends its CPU [M] (`GET /pos/sync/status`, before, ~11-12 ms CPU)

| phase | CPU ms | note |
|---|---|---|
| composer autoload, `bootstrap/app.php`, kernel | 1.2 | |
| register + boot 65 service providers | 2.9 | Sentry 0.68, Scramble 0.46, Carbon 0.23, app providers 0.2 each or less |
| global middleware | 0.8 | locale, request id, CORS, trusted proxies |
| auth: PDO connect + token, device, `exists(devices revoked)` | 1.8 | the 3rd statement repeated what the guard had just read |
| throttle (Redis) | 0.35 | |
| controller construction | 1.7 | `PosSyncController` resolves ~280 objects (the whole sync/billing graph) for a status read; other polled controllers 0.1-0.6 |
| validation, 3 queries, JSON | 1.2 | |
| response factory (view/session/redirector), terminate | 0.65 | |
| autoload inside the above | 1.65 ms wall, ~870 classes | ~10 lookups per request miss the classmap (`env`, `int`...): classmap-authoritative would save < 0.1 ms, not done |

Other findings: the image ran PHP's opcache defaults (8 MB interned strings were 93 % full; 10 000 files; stat every 2 s);
the entrypoint did not run `event:cache` (listener discovery on every request: +0.55 ms, +19 files); `GET /pos/floor` asked
`pg_class` twice per request whether the bookings tables exist. Statements: cheap polls run 5-13 (the "27 per request" of
M1 is the lunch mix with its writes: a POS order 72, a payment 121).

## 3. Changes and their effect (paired, CPU per request) [M]

| change | effect | evidence |
|---|---|---|
| OPcache production settings in `docker/php.ini` (192 MB, 32 MB interned, no timestamp checks) | -1 % (within noise) | 2 pairs; kept for headroom: interned strings were at 7.5 of 8 MB |
| OPcache **preload** (`docker/preload.php`, 1 266 classes from real requests) | **-8 to -9 %** | 3 pairs (-9.4, -8.6 and -8.4 % total) |
| `event:cache` in the entrypoint | -0.55 ms | instrumented, 30 requests each way |
| Scramble only for `/docs/api*` and the console | -0.4 ms (-5 % in a noisy pair) | instrumented + 1 pair |
| terminal token: reuse the device row the guard read | -1 statement on every terminal call (sync/status, display) | query log, test |
| `/pos/floor`: no `Schema::hasTable` probes | -2 statements (12 -> 10) | query log, test |
| JIT tracing 64 MB (measured, **not enabled**) | -4 % (-15 % on the KDS board) | 1 pair |

## 4. Before / after per request type [M] (mean of two paired runs with the sides swapped; both runs agree within 3 points)

"Before" = image as of M1 (opcache defaults, no preload, no event cache, Scramble eager); "after" = this wave. The two code
changes (device row, floor probes) are on both sides here: their gain is the statement column, not in the CPU ratio.

| request | PHP CPU ms before | after | change | statements before | after |
|---|---|---|---|---|---|
| GET /pos/sync/status | 12.8 | 9.4 | -27 % | 6 | 5 |
| GET /pos/display/state | 13.4 | 9.4 | -30 % | 6 | 5 |
| GET /pos/floor | 17.7 | 14.4 | -19 % | 12 | 10 |
| GET /pos/orders | 15.8 | 12.9 | -19 % | 6 | 6 |
| GET /pos/orders/:id | 19.0 | 15.8 | -17 % | 13 | 14 |
| GET /staff/orders?status=new | 13.8 | 10.0 | -27 % | - | 4 |
| GET /notifications | 13.6 | 10.6 | -22 % | 6 | 6 |
| GET /orders (customer) | 27.7 | 24.3 | -12 % | 6 | 6 |
| GET /stores?geo | 17.1 | 13.3 | -23 % | 5 | 2 * |
| GET /catalog?geo / ?store_id | 17.1 / 13.8 | 13.6 / 9.7 | -20 / -30 % | 12 | 2-3 * |
| GET /owner/reports/sales | 13.5 | 10.4 | -23 % | 6 | 6 |
| POST /pos/orders | 45.8 | 41.2 | -10 % | 72 | 80 * |
| POST /pos/orders/:id/send | 35.8 | 31.0 | -14 % | 46 | 47 |
| POST /pos/orders/:id/bills | 30.1 | 25.6 | -15 % | 42 | 44 |
| POST /pos/bills/:id/payments | 64.4 | 58.8 | -9 % | 121 | 124 |
| POST /pos/orders/:id/close | 22.9 | 18.9 | -18 % | 25 | 25 |
| all probe requests except KDS | 100 % | **83-84 %** | -16 to -17 % | | |

\* statement changes from the other M2 agents' work in the same tree (marketplace read model; revision hooks), not this
change. Single-client numbers: M1's 36-44 ms per request were whole-run averages under 4-core contention with the lunch
mix; the ratio is what carries over, about 3-4 ms less CPU per request whatever the route.

## 5. Not done, with the reason

- Persistent PDO connections (saves the ~0.7 ms connect per request): an aborted request could leave a transaction open on
  a connection PgBouncer then hands to another client; needs its own test plan.
- Caching token/user/device lookups in Redis: they gate auth; revocation paths include bulk deletes that fire no model
  event, so no invalidation could be proven.
- `PosSyncController` builds ~280 objects (1.7 ms) for `GET /pos/sync/status`: the fix is lazy resolution in that
  controller (M2-2's read path; with its 304 path the constructor no longer runs for unchanged polls).
- Sentry's provider (0.68 ms) stays: production is expected to set a DSN.

## 6. A long-running PHP runtime (Octane with FrankenPHP or Swoole): what it would take and save [E]

**Saves.** Everything in section 2 that is the same for every request disappears: autoload, `bootstrap/app.php`, kernel,
the providers' register+boot (~2.5-3 ms CPU after this wave), plus the PDO and Redis connects (~0.9 ms) and most container
construction. On today's profile that is about 3.5-4.5 ms of a cheap poll's ~9.4 ms (**-40 to -50 %** on polls and Mini
App reads) and of a POS write's 20-60 ms (**-8 to -15 %**). After M2-2's 304 path the remaining unchanged polls are mostly
framework cost, so the relative gain on them would be larger still. Memory: one booted app per worker (~40-60 MB) instead
of a fresh one per request; no change to Postgres load.

**Takes.** (1) A new dependency (`laravel/octane` + FrankenPHP binary or `ext-swoole`): a separate owner decision. (2) A
state audit: 11 `singleton()` bindings and 6 static properties in `app/`, every package singleton (Sanctum guards, the
`Auth::resolved` callbacks of AccessServiceProvider, Scramble/Sentry statics, Pennant's resolved features, M2-1's
revision map and connection hooks), anything memoised per instance that assumed "one request = one container"; Octane
flushes only what it knows. (3) The nginx pool split (critical / market / default with nice levels and the market
in-flight limit) must be rebuilt as three Octane servers (worker counts and priorities per server), or the till isolation
of M1 is lost. (4) Workers recycle (`--max-requests`) to bound leaks; deploy = graceful reload. (5) The whole suite has
no Octane mode: a soak test (L1 + L3 for an hour, memory per worker flat, no cross-request state) before production.
Recommendation: measure first under the kit (the profiler here gives the per-phase baseline); worth it if PHP stays the
first limit after M2's 304s, not before.
