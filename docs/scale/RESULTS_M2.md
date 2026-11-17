# RIZQIM wave M2: before/after load measurement

Date 2026-11-17, repo at 6f37376 (branch claude/zealous-maxwell-p1cfsk, M2 incl. the review fixes: a sale moves only
the selling branch's token, signed ETags). Baseline = `RESULTS_M1.md` "M1 as shipped" (fpm-pgb). **[M]** = measured
here, **[E]** = estimate. Raw outputs: `results_m2/` (`compare.md` = every run side by side). Same 4 vCPU box shared by
the driver, PHP, PgBouncer, nginx, Redis, Reverb and Postgres: relative costs on this box, not production capacity.

## 1. Method

- **Data**: L profile generated fresh (`DB_DATABASE=scale_m2 ./run.sh L base gen mint`, all migrations): 11 groups, 253
  stores (33 with POS), 284 194 orders, 1 986 MB, FK orphans 0. `pg_stat_statements` checked preloaded before and after.
- **Serving mode**: the shipped one for every run (nginx + critical/market/default pools 4+4+4, market limits at their
  defaults, PgBouncer transaction mode pool 20). PgBouncer and the server restarted per run, Redis flushed (cold revision
  tokens and cold market read model at each start). `scripts/scale-test/m2.sh` waits for a 1-min load < 1.0 and records
  it: 0.06-0.73 before every run, nothing else ran. Each headline scenario run twice, interleaved (M1 mode, M2, M1, M2).
- **Real-time on** (`RT=1`): Reverb (`reverb:start`, no ext-uv), one `queue:work --queue=default,broadcasts`, and the
  30 s heartbeat job (kit stand-in `php/rt/heartbeat.php`, not the whole scheduler, which M1 did not run either). They
  connect straight to Postgres as their own role (as the compose `queue`/`reverb` do), so their SQL is split out: the
  broadcast jobs ran **0 statements**.
- **Scenarios**: (1) **M1 client on M2 code** (`m1mode`: unconditional 15 s polls, old marketplace calls, real-time off):
  L1 lunch+poll, L3 lunch+250-customer spike t=90-210 s, L4 250 customers 300 s. (2) **M2 as shipped** (`m2`, RT on):
  L1, L3, L4. (3) **Real-time down** (`m2down`, RT off, M2 client at the apps' fallback intervals: till/KDS/owner 15 s,
  display 5 s, conditional, no events): L1. Extra: `L1_m2_debounce2s` (event debounce 2 s instead of 300 ms),
  `L1_m2sim` (events simulated instead of sockets), `P_*` (polling fleet only, no orders: off-peak), `probe304`.
- **Driver client model** (checked against `pos_data` `PosRefreshScheduler`, the cashier/KDS/display controllers,
  `rizqim_api` `ConditionalGet`/`ChannelRevision`, `customer/miniapp` hooks, `partner/web` LiveOrders):
  - every screen keeps the last ETag per URL and sends `If-None-Match`; Flutter screens send a poll without it when the
    poll time since the last full read reaches 110 s (60 s polls: every 2nd, 15 s: every 8th, 30 s: every 4th, as the
    apps count it); the owner board is a browser tab and never drops it.
  - live intervals: till 60 s, KDS 15 s, customer display 30 s, owner board 60 s; Mini App notifications 15 s,
    customer orders 60 s.
  - **real sockets**: one Pusher-protocol socket per screen (Node 22 `WebSocket`, `POST /api/broadcasting/auth`), 198 per
    L1 run, all subscribed. A screen re-reads once per relevant event (300 ms debounce, one read at a time), skips an
    event whose `rev` equals the token it read, re-reads on a heartbeat whose `rev` differs. Relevant = the apps'
    filters: floor = order/items/bill/booking events, open order page = events of that order, KDS = every event,
    display = `display.cart` and `queue.*` only, owner board = `order.*`.
  - Mini App M2: fixed address per customer; home = `catalog?{loc}&view=cards&limit=50` + `stores?{loc}&page=1&per_page=10`
    only when older than react-query's staleTime (30 / 60 s), page 2 for 30 % (page 3 for a third of those); store menu
    `catalog?store_id={uuid}` on every open (StoreDetail's effect) + `catalog/availability` (20 s, while on the store).
  - Differences: no waiters, hostess, bumps, queue calls or `display.cart` events (the driver's flow emits none); no
    Mini App sockets (customers poll at the no-socket rates, which only matters for availability: 20 vs 60 s); customers
    do not retry 503s; 3 tills + KDS + display + owner per POS store. `L1_m2_1` used a broader filter (every screen
    re-read on every event); it is kept in `results_m2/` but not in the tables (`L1_m2_3` replaces it).
- **KDS board growth** (driver artefact, as in M1): the driver never bumps, so today's board grows run after run: a KDS
  200 cost 26 ms PHP and 10 KB in the first run, 98 ms and 107 KB in the last. Runs were interleaved, but M2 reads the
  board ~1 230 times per L1 run vs 660, so it is hit twice as hard. "adj" = PHP CPU with each KDS 200 recharged at the
  first run's 26 ms (`compare.md`, from the php-fpm access log with `%C`). Postgres is not adjusted (pessimistic for late runs).

## 2. Headline [M]

| metric (L1 lunch unless noted) | M1 as shipped | M2 code, M1 client | **M2 as shipped** | M2, real-time down |
|---|---|---|---|---|
| requests per POS store per minute | 32.4 | 31.9 / 32.1 | **43.1 / 41.6** (debounce 2 s: 38.5) | 32.6 / 31.9 |
| PHP CPU s (adj) | 274 | 212 / 211 (212 / 203) | **308 / 363 (279 / 274)** | 222 / 237 (203 / 213) |
| Postgres + PgBouncer CPU s | 66 + 16 | 56 + 14 / 55 + 14 | **73 + 19 / 77 + 20** | 57 + 14 / 59 + 15 |
| (PHP + Postgres) CPU per POS order, adj | 1.24 s | 0.97 / 0.90 s | **1.24 / 1.24 s** | 0.93 / 0.99 s |
| statements per request / per order | 27.3 / 755 | 27.1 / 745, 28.1 / 751 | 26.5 / 939, 28.1 / 984 | 27.5 / 747, 27.2 / 750 |
| share of polls answered 304 (all GETs) | 0 % | 0 % | **15.0 / 15.9 %** | 21.3 / 24.0 % |
| POST /pos/orders p95; floor p95; KDS p95 (ms) | 133; 46; 102 | 112 / 104; 32 / 31; 64 / 80 | **277 / 398; 263 / 353; 320 / 458** | 109 / 112; 33 / 35; 116 / 127 |
| till+poll p95 during the L3 spike (before) | 149-186 (103-122) | 137 / 145 (112 / 120) | **485 / 469 (350 / 468)** | - |
| L3 market shed during spike (default limits) | 4.2-16.4 % | 0.1 / 0.0 % | **0.5 / 1.0 %** | - |
| L4: req/s, shed, PHP / PG+PgB CPU s | 53.3, 1.2 %, 743 / 104 | 54.2 / 54.7, 0.0 %, 400 / 47 | **43.9 / 44.0, 2.5 / 1.9 %, 241 / 33, 219 / 30** | - |
| L4 Mini App GET p50 / p95 (ms) | 51-109 / 183-248 | 18-26 / 33-69 (orders 60 / 92-100) | 13-64 / 48-286 | - |
| Redis commands/s L1 (L4); Redis CPU | - | 207 (940); 3.5 s | 452-472 (804); 5.3-5.6 s | 216-221; 3.7-3.9 s |
| Reverb / queue worker CPU, sockets | - | - | 2.6-2.9 s / 4.7-5.1 s per 430 s, 198 sockets | - |
| bytes per POS store per minute (excl. KDS) | - | 336-397 kB (309-325) | 766-1 129 kB (536-558) | 423-428 kB (290-302) |
| off-peak (no orders): req/store/min, PHP CPU | - | 34.3, 176 s / 315 s | **9.2, 41 s / 360 s (304: 62 %)** | - |
| till/POS/staff errors (5xx, 429, timeouts) | 0 | 0 | **0 in every run** | 0 |

What a shed customer sees: unchanged (503, `Retry-After: 2`, `server_busy`, "Hozir so'rovlar juda ko'p..."), 0 x 429.
In L4 M2 the 295-388 shed answers fell in the first minute (287 of 295 in run 2): 250 customers arriving within 5 s on
a flushed Redis, each firing feed + store list at once. Lock waits 0 in every run.

## 3. 304s at lunch: per endpoint, and what still forces a 200 [M]

| GET (L1 304 share) | M2 as shipped (2 runs) | real-time down (2 runs) | off-peak M2 | L3 M2 | L4 M2 |
|---|---|---|---|---|---|
| /pos/floor (till) | 8.0 / 10.4 % | 13.9 / 17.1 % | 40 % | 8.2 / 9.9 % | - |
| /pos/orders/:id (open order) | 6.5 / 7.3 % | 0.6 / 0.9 % | - | 7.4 / 7.3 % | - |
| /staff/kitchen/tickets (KDS) | 31.0 / 31.6 % | 7.4 / 11.4 % | 85 % | 31.7 / 32.3 % | - |
| /pos/display/state | 7.1 / 7.8 % | 46.5 / 49.9 % | 70 % | 9.5 / 12.1 % | - |
| /staff/orders, ?status=new (owner board) | 15.4 / 15.2 %, 14.6 / 14.9 % | 11-13 % | 41-44 % | 15-16 % | - |
| /notifications; /orders?scope=active | - | - | - | 84 %; 39-41 % | 93 %; 75-76 % |
| /catalog?store_id; cards feed; /stores pages; /catalog/availability | - | - | - | 5-6 %; 0; 0; 0 | 7 %; 0; 0; 0 |

- **A write in the branch forces a 200, and at lunch the branch writes every ~9 s.** 274-286 orders x 6 writes in 430 s
  over 33 stores = one write per branch per 8.7 s; an order emits ~9 events (`created`, `items_changed` x2,
  `status_changed` x2, `bill_changed` x3, `fiscal.receipt_updated`), each to ~6 sockets: ~15.8 k deliveries per run. Reads by cause (L1_m2_3): 1 650
  periodic polls, 4 721 event reads, 518 heartbeat reads. The event reads come right after a change, so they are 200s;
  the 1 311 304s are almost exactly the 1 320 conditional periodic polls. Only 809 of 15.8 k events were skipped by `rev`.
- **The periodic unconditional poll** forces 330 of 1 650 periodic polls per run to 200 (20 %; every 2nd till/owner poll).
- **ETag max-age**: 120 s on the POS screens never binds at lunch (a write comes first). In the Mini App it binds always:
  cards feed (max-age 30 s) and store pages (60 s) are refetched only after react-query's staleTime of 30 / 60 s, and
  availability (15 s) is polled every 20 s, so the tag has always expired: 0 % 304 on all three. Notifications
  (300 s) and orders (300 s) are the only market reads that 304 well.
- **Display**: it ignores order events, so its known token goes stale and every 30 s heartbeat with a moved branch
  `rev` makes it re-read: 7-12 % 304 with real-time, 47-50 % at 5 s polls without it (its ETag is the whole branch token).
- **What a 304 costs vs a 200** (`probe304_rt.txt`, one client, 40 reps): PHP CPU 8.5-10.5 ms vs 11-67 ms, 3-4 statements
  vs 4-31 (search_path, token, user, device stay), p50 10-13 ms vs 14-84 ms; floor 9.5 vs 16.3 ms / 4 vs 11 statements;
  owner board 10.5 vs 67 ms / 3 vs 31; KDS 10.5 vs 12 ms / 4 vs 6. Market reads (read model): 304 11.3-12.8 ms vs
  12.8-17.3 ms and 3 vs 3-4.5 statements, so a 304 there saves little CPU, mainly bytes (58 KB menu, 43 KB feed).

## 4. What each change contributed [M]

- **Server side (M1 client on M2 code)**: L1 PHP -23 % (274 -> 211 s), Postgres+PgB -15 % (82 -> 69 s), CPU per order
  1.24 -> 0.90-0.97 s, POS write p50 -10 % under load. L4: PHP -46 % (743 -> 400 s), Postgres+PgB -55 % (104 -> 47 s),
  total SQL time 35 s -> 7 s, Mini App p50 18-26 ms, shed 1.2 % -> 0. L3 spike: shed 4-16 % -> 0-0.1 % at the same
  default limits, till p95 137-145 ms. This is the read model (market) and the PHP cost work (OPcache, preload).
- **M2 Mini App client** (cards, pages, staleTime, ETag): ~19 % fewer market requests per customer (0.176 vs 0.217 req/s),
  PHP per market request 23.5 -> 14-15 ms; L4 PHP 400 -> 219-241 s. Cost: the store menu is fetched on every store
  open (55-64 ms p50, 7 % 304) and the cold start sheds 2-2.5 % in the first minute. `NGINX_MARKET_MAX_INFLIGHT`:
  keep 16. The defaults now shed 0-1 % in the L3 spike (M1: 4-16 %, which was the case for 32); only the cold start sheds.
- **Real-time + event refresh at lunch: worse, not better.** +30 % requests per store, +30 % PHP (adj), +35 % Postgres,
  +26 % statements per order, 1.7x bytes (excl. KDS), and till latency p95 x3-4 (floor 31 -> 263-353 ms, POST /pos/orders 104-112 ->
  277-398 ms, L3 spike 145 -> 469-485 ms). Each write makes ~6 screens of the branch re-read within ~300 ms; the 4
  static `critical` workers queue behind the burst (owner board reads cost 67 ms PHP each and are in `critical`). A 2 s
  debounce removes 8 % of reads but not the burst (p95 unchanged). The real-time processes themselves are cheap
  (Reverb 0.6 % of a core for 198 sockets, queue worker 1.2 %, 0 SQL).
- **Real-time down** costs the same as the M1 client (PHP adj 203-213 s, writes 109-112 ms p95): conditional polls at 15 s
  give 21-24 % 304s, which roughly pays for the write hook. **Off-peak** (no writes) M2 is a big win: requests -73 %,
  PHP -80 % per minute, 62 % 304s, 110 vs 864 kB per store per minute.
- **Write hook** (revision tokens on every write): probe p50 (one client) POST /pos/orders 72 -> 78 ms, payments
  105 -> 119 ms, send 56 -> 58, bills/close unchanged; under load it is hidden by the PHP savings (orders p50 96 ->
  81-87 ms in L1 M1 mode). Redis: the Lua INCR is in the 18-44 k `eval` calls per L1 run; not separable per write.

## 5. What did not improve, or got worse

- Till/POS latency at lunch with real-time on (above): the M1 isolation of the tills is undone by the branch's own screens.
- Requests per POS store at lunch: 32 -> 42-43 (target was fewer). 304 share at lunch only 15-16 % (21-24 % when down).
- Statements per request unchanged (26.5-28.1); per order +26 % with real-time (more reads).
- Market: cards feed, store pages and availability never 304 (max-age <= client refetch interval); `/catalog?store_id`
  p95 283-286 ms in L4 M2 (58 KB menu per open); cold-start shed 2-2.5 %.
- Per-request floor: a 304 still runs 3-4 statements and 8.5-12.8 ms PHP (auth + middleware), ~70 % of a cheap 200.
- `cashPickupBanApplies` (a `count(*)` on the customer's orders) runs on every `/stores` and `/catalog` read: 4.5 k calls
  in L4, 3rd by total SQL time behind the token and user lookups.

## 6. Limits

One box, 4 vCPU shared; no network latency; Mini App without sockets, no waiters/bumps/queue/display-cart flows; KDS
board growth (adjusted for PHP only); Reverb without ext-uv (fine at 198 sockets; the 7-10 k socket case was not
measured); no reconnect wave measured; customers do not retry; pgbench and L2/L5/L6 not re-run (not in the brief).
Shed figures moved 2x between identical runs, as in M1.

## 7. Capacity estimate for the target [E] (from this box; LAUNCH_PLAN assumptions: 150 POS + 20 market orders per
store per day, peak = the L1 lunch intensity, ~2.4 browsing customers per store at peak)

Method (reproduces M1's table within 10-20 %): cores per POS store = L1 CPU / (33 stores x wall); market cores per store =
2.43 customers x L4 CPU per customer-second (2.43 = M1's 0.52 market req/s per store / 0.213 req/s per M1 customer).

| stores | M1 estimate (LAUNCH_PLAN) PHP / PG | **M2 as shipped** PHP / PG (+ Reverb/queue, Redis) | M2, real-time down | M2 code, M1 clients |
|---|---|---|---|---|
| 100 | 4-5 / 1-2 | **2.6 / 0.8** (+0.1, 0.1) | 2.1 / 0.6 | 2.7 / 0.6 |
| 300 | 12-13 / 3-4 | **7.7 / 2.2** (+0.2, 0.2) | 6.3 / 1.8 | 8.1 / 1.9 |
| 1 000 | ~40 / ~11 | **~26 / ~7.5** (+0.6, 0.6) | ~21 / ~6 | ~27 / ~6.4 |

M2 as shipped: POS side 19.5 PHP + 6.6 PG cores per 1 000 stores (M1: 19.5 + 5.8: no gain), market side 6.2 + 0.85 (M1:
23 + 3.2). Peak load ~1 130 req/s per 1 000 stores (M1: ~1 050). The whole M2 gain at peak comes from the market; at the
POS lunch peak the shipped real-time setting costs ~30 % more PHP than the same code with real-time down. Postgres at
1 000 stores also has a 4x larger database than this box's L profile: not modelled.

## 8. What should be targeted next

1. **[Addressed by the client fix (section 9), partly: the till still reads more than with polling]** **Event-driven re-read storm**: one event per committed write (not 5-6), per-screen scopes (KDS = kitchen changes,
   display = queue/cart, floor = table/order state) or apply the event payload instead of re-reading; at least stagger
   the screens' re-reads (jitter 0-2 s) and keep the owner board (67 ms, 96 KB per read) out of `critical`.
2. **Branch open-orders list** (`orders where branch_id and channel in and status not in`): still #1 at lunch, 1.8 ms x
   1 570-1 700 calls = 23-26 % of L1 SQL; `/staff/orders` 31 statements per 200.
3. **ETag max-age vs client refetch** in the Mini App: give feed/store pages/availability a max-age longer than the
   client's staleTime/poll (or a stale-while-revalidate client), so repeat reads 304.
4. **Per-request auth floor** (3-4 statements, ~9 ms PHP even for a 304): cache token+user per request burst.
5. **`cashPickupBanApplies` per market read** (cache per customer) and the menu payload on every store open.
6. Market cold start (2-2.5 % shed in the first minute after a Redis flush/deploy): warm the read model on deploy.

## 9. After the client fix (re-measured)

HEAD ae781ff (client fix e1bf367, bdb8736, 81d11f8, 4cf4979, 40187fd; server da1341b stock lock order, 589506a ETag clock
expiry). Raw: `results_m2b/` (`compare.md`). Same box and method as section 1 (relative costs, not production capacity).
**Differences**: replaced container, fresh `scale_m2` (same size), every reference re-run here (once each), new runs twice,
interleaved, load before 0.09-0.29. Driver mode `m2b` (`load.mjs` block `b3)`) imitates the fixed clients: per-screen
R/S/doubt fed by every event, relevant events only, paced reads (KDS 2 s / 0-0.5 s, floor 5 s / 0-1.5 s, order page 1 s /
0-0.3 s, display 0.3 s / 0-0.25 s), heartbeat reads on `rev != S` (doubt: not on the display), one retry of a failed read;
owner board: `GET /staff/orders/{uuid}` per relevant event (3 s / 0-1 s), payload for kitchen events, open list on a gap or
heartbeat (30 s) and as 60 s safety poll, no `?status=new`; KDS polls 15 s after its last read (as its controller). Till
`alt` = as m1/m2 (the floor does not read while an order page is open); `stack` = the app's navigation (the floor stays
mounted under the order page and re-reads when it closes). Not modelled: billing page, waiters, pickup board, cart/queue
events, Mini App sockets. PHP adjusted for KDS board growth as in section 1 (base 26.9 ms, the first run).

| L1 lunch unless noted | m1mode | m2 storm | **m2b run 1 / run 2** | m2b `stack` | m2down |
|---|---|---|---|---|---|
| requests per POS store per minute | 32.2 | 41.6 | **30.1 / 30.0** | 37.5 | 31.5 |
| PHP CPU s (adj) | 189 (189) | 257 (248) | **193 / 222 (177 / 185)** | 246 (204) | 214 (198) |
| Postgres + PgBouncer CPU s | 53 + 13 | 68 + 18 | **60 + 14 / 66 + 16** | 70 + 16 | 59 + 15 |
| (PHP + Postgres) CPU per POS order, adj | 0.86 s | 1.14 s | **0.86 / 0.91 s** (unadj 0.92 / 1.05) | 0.99 s | 0.93 s |
| statements per request / per order | 27.9 / 758 | 26.2 / 942 | 31.5 / 809, 32.7 / 843 | 28.4 / 920 | 27.7 / 761 |
| 304 share, all GETs | 0 % | 15.3 % | 3.7 / 3.6 % | 5.3 % | 23.0 % |
| POST /pos/orders p95; floor p95; KDS p95 (ms) | 109; 30; 62 | 306; 217; 256 | **116 / 135; 36 / 42; 93 / 138** | 120; 49; 154 | 118; 34; 108 |
| till+poll p95 during the L3 spike (before) | - | - | **122 / 152 (98 / 123)** | - | - |
| L3 market shed during spike | - | - | 0.3 / 0.1 % | - | - |
| bytes per POS store per minute | 352 kB | 645 kB | 240 / 353 kB | 462 kB | 402 kB |
| off-peak (P, no orders): req/store/min, 304 | - | - | **8.4, 64 %** (section 2: 9.2, 62 %) | - | - |
| till/POS/staff answers not 2xx/304 | 0 | 0 | **0** (L3: 0; market 503 sheds only) | 0 | 0 |

**Reads by cause** (L1, poll / event / heartbeat): storm 1 649 / 4 761 / 498; **m2b 1 062 / 4 287 / 109** (runs within 3);
stack 1 319 / 5 723 / 80 (+207 return reads). Of 15.5 k deliveries m2b skipped 995 (rev = R or already asked; storm 857),
fed 13.3 k irrelevant ones into S only, asked 5.5 k (merged into 4.3 k reads), dropped 4-8 covered reads; 620-640 heartbeats
asked (mostly doubt: an ignored event's token jumped, the "rev at send time" limit), ~110 became heartbeat reads. By screen
(run 1): floor 1 297 event + 265 poll; order page 1 029 event + 62 poll + 78 heartbeat; KDS 943 + 240; owner 1 018
single-order + 165 list polls (0 gap reads); display 330 polls + 22 heartbeat.
**304 per endpoint** (m2b, both runs): floor 2.2 / 2.5 %, order 1.6 / 1.9 %, KDS 10.2 / 10.1 %, display 5.4 / 4.3 %,
`/staff/orders/{uuid}` 0.7 / 0.5 %, `?scope=active` 0 %: event reads now come only after a real change, so they are 200s.
**Single-order read** (`probe304_m2b`, one client, 40 reps): `GET /staff/orders/{uuid}` 200 = 16.5 ms PHP, 11 statements,
2.4 KB; 304 = 12.3 ms, 3 statements, 0 B (list: 67 ms, 26 statements, 96 KB; `?scope=active` 19.8 ms, 11, 9.6 KB).
**KDS artefact**: the board is never bumped, so its lines go late and stay; with 589506a a KDS ETag expires at the next
line turning late, which matters while lines are still fresh. On this ever-growing board most lines are already late, so
the expiry rarely binds (off-peak KDS 304 85 %, as before; m2down 10.6 %, section 2: 7-11 %); KDS 200 cost grew 26.9 ->
63-67 ms over the session, which is why run 2 (later, bigger board) is slower and costlier than run 1.

**Acceptance** (both m2b runs; same-session m1mode in brackets):
- POST /pos/orders p95 back to m1mode (109 ms): run 1 **pass** (116, the m2down level 118), run 2 **fail** (135, +24 %).
  `/pos/floor` p95 (30 ms): **fail** both (36, 42; m2down 34), but far from the storm (217). Till p95 in the L3 spike
  <= ~190 ms: **pass** both (122, 152; section 2 storm 469-485).
- Requests per POS store per minute <= ~34: **pass** both (30.1, 30.0). (PHP + Postgres) CPU per order <= m2down (0.93 s):
  **pass** both adjusted (0.86, 0.91); unadjusted run 2 fails (1.05 vs 0.99). The `stack` till fails both (37.5; 0.99 s).
- Off-peak unchanged: **pass** (8.4 req/store/min, 64 % 304, PHP 29 s / 358 s).
- No measurable cost of the stock lock order: POST /pos/orders p50 81-92 ms (m1mode 81; section 2 81-90), lock waits 0.

What still costs most: on the 4 `critical` workers the KDS reads 1.8x m1mode (1 190 vs 660; a 200 costs 42-67 ms PHP and
grows with the board: 40-46 % of read PHP) and the floor 1.5x (1 564 vs 1 062). The order page's 1 029 event reads all
follow the cashier's own writes (a write response proves no R). With the app's real navigation the floor under the order
page doubles floor reads (stack: 2 835 event + 207 return reads, 37.5 req/store/min).
Next cheapest levers: (1) let a write response carry `X-Revision` and set R from it, so a screen skips the event of its
own write (order page ~1 000 reads/run, and the floor's under `stack`); (2) KDS: apply `kitchen.item_status_changed` and
`order.items_changed` from the payload like the owner board, or a 5 s KDS window; (3) pause a screen covered by another
route (floor under the order page) and read once on return.

**Capacity [E]** (section 7 method; POS side from m2b L1 adjusted, market side unchanged from section 7, not re-measured):

| stores | section 7 M2 as shipped PHP / PG | **m2b (runs 1-2)** PHP / PG (+ Reverb/queue, Redis) | m2b stack | same session: m1mode / m2down / m2 storm |
|---|---|---|---|---|
| 100 | 2.6 / 0.8 | **1.9 / 0.6-0.7** (+0.1, 0.1) | 2.0 / 0.7 | 2.0 / 0.6, 2.0 / 0.6, 2.4 / 0.7 |
| 300 | 7.7 / 2.2 | **5.6-5.8 / 1.8-2.0** (+0.2, 0.2) | 6.1 / 2.0 | 5.9 / 1.7, 5.9 / 1.8, 7.0 / 2.1 |
| 1 000 | ~26 / ~7.5 | **~19 / ~6.4** (+0.6, 0.6) | ~20 / ~6.8 | ~19.5 / ~5.6, ~20 / ~5.9, ~23.5 / ~6.9 |

POS side per 1 000 stores: 12.5-13.0 PHP + 5.3-5.8 PG cores (storm today 17.3 + 6.0, m1mode 13.3 + 4.7). The fix removes
the storm's PHP; Postgres stays 10-20 % above m1mode/m2down (unadjusted). Today's m1mode PHP is 189 s (section 2: 211-212 s).
