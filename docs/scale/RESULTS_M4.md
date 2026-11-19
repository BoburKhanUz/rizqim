# RIZQIM wave M4 stage 3: row-level security on / off under the lunch load

Date 2026-11-19, repo at f23a5dfd (branch claude/zealous-maxwell-p1cfsk: stage 3 with the review fixes). The question
of `M4_DESIGN.md` section 6 row 3: does `TENANT_RLS=1` keep POS p95 and Postgres CPU within +5 % of the same run
without it? **[M]** = measured here. Raw outputs: `results_m4/` (per run: `.txt` load driver, `.pgss.txt`, `.fpm.txt`,
`.pgbouncer.txt`, `.redis.txt`, `.rls.txt` = roles, transactions and top statements). Same 4 vCPU box shared by the
driver, PHP, PgBouncer, nginx, Redis, Reverb and Postgres, as in `RESULTS_M2.md`: relative costs, not capacity.

## 1. Method

- **Scenario**: `RT=1 ./m2.sh <label> m2b-L3` (the M2 "fix round 2" clients: lunch at the tills + polling fleet, 300 s,
  250 marketplace customers from t=90 s to 210 s; nginx + php-fpm pools 4+4+4, PgBouncer transaction mode pool 20,
  Reverb, queue worker, heartbeat). Unchanged from `RESULTS_M2.md` section 9.
- **Data**: the L profile built fresh with split roles (`./run.sh L base gen mint`): 261 stores (33 with POS),
  284 194 orders, 2 066 MB, FK orphans 0, all migrations including the 20 policies; `VACUUM (ANALYZE)` before the
  first run and one 60 s warm-up run.
- **Roles** (as production after `db-roles`): owner `m4s3_owner` (migrate), application login `m4s3_app` (DML,
  BYPASSRLS; PgBouncer and php-fpm), tenant role `m4s3_tenant`. OFF = `TENANT_RLS=0` with the same roles and policies
  in place (what the kill switch gives); ON = `TENANT_RLS=1`.
- **Cluster**: PostgreSQL 17.12 on port 5433 (the M2 runs were on 16), `shared_buffers` 128 MB, defaults otherwise,
  `pg_stat_statements` preloaded with `track_planning = on` for every run (the extra work of a policy is planning).
- **Same state for every run.** A first series without it (OFF, ON, OFF, ON, OFF, ON on one database) drifted: every
  run adds about 1 700 orders that never leave the kitchen lists, and Postgres CPU went 82.0, 84.1, 83.9, 87.4, 94.2,
  91.5 s whatever the switch said. That series is not reported. For the runs below the database is dropped and
  re-created before each run from a template copy of the fresh build (`CREATE DATABASE ... TEMPLATE ... STRATEGY
  FILE_COPY`, then `CHECKPOINT`), so each run starts from the identical rows, statistics and cold shared buffers.
- **Runs**: OFF, ON, OFF, ON, OFF, ON (three pairs: after two, the two ON runs differed by more than the effect), then
  ON and OFF once more with extended statistics (section 4). 1-minute load before each run 0.2-1.2; nothing else ran.

Kit changes for split roles (all optional, a plain run is as before): `env.sh` takes `DB_PORT` (default 5432) for the
app, PgBouncer and the kit's superuser `psql`; `pgbouncer.sh`, `server.sh` (real-time processes) and `load.mjs` use
it; `base.sh` runs `scripts/db-roles.sh` when `DB_OWNER_USERNAME` is exported; `server.sh` makes the real-time role
`scale_rt` a member of the exported `DB_USERNAME` and gives it BYPASSRLS when that role has it (the compose queue and
Reverb run as the app role itself). To reproduce:

```bash
export DB_PORT=5433 DB_DATABASE=scale_m4 DB_USERNAME=<app role> DB_PASSWORD=... DB_OWNER_USERNAME=<owner role> \
  DB_OWNER_PASSWORD=... DB_TENANT_ROLE=<tenant role>
rm -f "$SCALE_WORK"/cache/*.php     # a config cache left by m2.sh points artisan at PgBouncer, which is stopped
TENANT_RLS=0 ./run.sh L base gen mint
RT=1 TENANT_RLS=0 ./m2.sh L3_off m2b-L3;  RT=1 TENANT_RLS=1 ./m2.sh L3_on m2b-L3
```

## 2. Result [M]

Three runs each; "spread" = (max - min) / mean of the three equal runs.

| metric | OFF 1 / 2 / 3 | ON 1 / 2 / 3 | OFF mean | ON mean | ON vs OFF | spread OFF / ON |
|---|---|---|---|---|---|---|
| requests | 13 072 / 13 015 / 13 109 | 12 919 / 13 036 / 13 001 | 13 065 | 12 985 | -0.6 % | 0.7 % / 0.9 % |
| 5xx on `/pos` and `/staff` | 0 / 0 / 0 | 0 / 0 / 0 | 0 | 0 | | |
| 5xx anywhere (all are the marketplace limit's 503) | 5 / 18 / 25 | 4 / 8 / 0 | 16 | 4 | | |
| **Postgres CPU (s)** | 78.06 / 76.45 / 76.23 | 80.74 / 77.40 / 80.05 | 76.91 | 79.40 | **+3.2 %** | 2.4 % / 4.2 % |
| pg_stat_statements: plan time (s) | 23.49 / 22.75 / 22.85 | 24.21 / 23.28 / 24.01 | 23.03 | 23.83 | +3.5 % | 3.2 % / 3.9 % |
| pg_stat_statements: exec time (s) | 10.12 / 9.93 / 10.07 | 10.63 / 10.04 / 10.30 | 10.04 | 10.32 | +2.8 % | 2.0 % / 5.7 % |
| PHP CPU (s) | 287.5 / 282.2 / 286.1 | 300.4 / 282.7 / 293.7 | 285.3 | 292.3 | +2.5 % | 1.9 % / 6.1 % |
| PgBouncer CPU (s) | 23.09 / 22.42 / 22.37 | 24.05 / 22.54 / 23.93 | 22.63 | 23.51 | +3.9 % | 3.2 % / 6.4 % |
| till+poll p50 / p95 / p99 before the spike (ms) | 31 / 113 / 186, 32 / 136 / 200, 31 / 113 / 178 | 33 / 117 / 185, 31 / 110 / 169, 32 / 119 / 195 | 31.3 / 121.0 / 187.6 | 31.9 / 115.5 / 182.9 | +2.0 / -4.5 / -2.5 % | p95: 18.9 % / 7.7 % |
| till+poll p50 / **p95** / p99 during the spike (ms) | 37 / 143 / 217, 35 / 135 / 211, 36 / 137 / 211 | 39 / 150 / 225, 35 / 131 / 214, 38 / 154 / 227 | 35.8 / 138.6 / 212.9 | 37.1 / 144.8 / 221.9 | +3.7 / **+4.5** / +4.2 % | p95: 5.6 % / 15.6 % |
| till+poll p50 / p95 / p99 after the spike (ms) | 36 / 156 / 192, 34 / 159 / 195, 35 / 160 / 194 | 38 / 172 / 208, 35 / 164 / 202, 36 / 162 / 200 | 34.9 / 158.3 / 193.3 | 36.6 / 166.1 / 203.2 | +5.0 / +4.9 / +5.1 % | p95: 2.3 % / 5.7 % |
| `POST /pos/orders` p50 (ms) | 110.5 / 108.5 / 110.1 | 118.5 / 109.4 / 112.3 | 109.7 | 113.4 | +3.4 % | 1.8 % / 8.0 % |
| `POST /pos/orders` **p95** (ms) | 149.3 / 145.0 / 160.6 | 171.9 / 142.0 / 160.6 | 151.6 | 158.2 | +4.3 % | 10.3 % / 18.9 % |
| `POST /pos/orders/:id/rounds` p50 / p95 (ms) | 92.8, 91.8, 93.3 / 133.8, 134.8, 128.6 | 101.0, 92.8, 95.8 / 132.6, 120.8, 155.9 | 92.6 / 132.4 | 96.5 / 136.4 | +4.2 / +3.0 % | p95: 4.7 % / 25.7 % |
| `POST /pos/orders/:id/send` p50 / p95 (ms) | 83.9, 81.3, 80.6 / 112.7, 121.6, 114.5 | 86.9, 82.6, 86.4 / 121.7, 116.2, 141.8 | 81.9 / 116.3 | 85.3 / 126.6 | +4.1 / +8.9 % | p95: 7.7 % / 20.2 % |
| `POST /pos/orders/:id/bills` p50 / p95 (ms) | 65.4, 63.7, 65.6 / 99.0, 89.7, 91.1 | 72.0, 66.1, 69.4 / 102.5, 92.0, 108.1 | 64.9 / 93.3 | 69.2 / 100.9 | +6.6 / +8.1 % | p95: 10.0 % / 16.0 % |
| `POST /pos/bills/:id/payments` p50 / **p95** (ms) | 180.3, 177.1, 180.8 / 243.4, 239.1, 235.6 | 195.1, 185.4, 186.8 / 248.8, 233.4, 257.4 | 179.4 / 239.4 | 189.1 / 246.5 | +5.4 / +3.0 % | p95: 3.3 % / 9.7 % |
| `POST /pos/orders/:id/close` p50 / **p95** (ms) | 43.0, 41.2, 43.5 / 69.6, 68.7, 67.4 | 44.6, 42.8, 44.1 / 69.8, 56.6, 84.3 | 42.6 / 68.6 | 43.8 / 70.2 | +3.0 / +2.4 % | p95: 3.2 % / 39.4 % |
| `GET /pos/floor` p50 / p95 (ms; no transaction, never switched) | 29.1, 28.4, 29.2 / 46.4, 43.4, 45.5 | 31.4, 28.7, 29.4 / 50.7, 43.8, 50.6 | 28.9 / 45.1 | 29.8 / 48.4 | +3.2 / +7.2 % | p95: 6.7 % / 14.3 % |
| `GET /staff/kitchen/tickets` p50 / p95 (ms; never switched) | 35.5, 35.2, 36.5 / 64.0, 69.6, 64.4 | 38.3, 35.6, 37.4 / 72.1, 62.1, 72.2 | 35.7 / 66.0 | 37.1 / 68.8 | +3.8 / +4.2 % | p95: 8.5 % / 14.7 % |
| statements (pg_stat_statements calls) | 241 231 / 240 470 / 238 350 | 240 615 / 245 917 / 247 657 | 240 017 | 244 730 | +2.0 % | 1.2 % / 2.9 % |
| transactions (PgBouncer `total_xact_count`) | 174 053 / 173 076 / 172 722 | 169 903 / 172 624 / 173 552 | 173 284 | 172 026 | -0.7 % | 0.8 % / 2.1 % |
| `BEGIN` (explicit transactions) | 2 665 / 2 663 / 2 604 | 2 848 / 2 940 / 2 974 | 2 644 | 2 921 | +277 | |
| transactions that took the tenant role (`SET LOCAL ROLE`) | 0 / 0 / 0 | 2 829 / 2 919 / 2 959 | 0 | 2 902 | | |
| PgBouncer avg transaction / query time (µs) | 961, 929, 916 / 522, 494, 491 | 914, 978, 1 002 / 472, 504, 516 | 935 / 502 | 965 / 497 | +3.1 / -1.0 % | 4.8 % / 9.1 % |
| `tenant_rls` log lines, lock-wait samples | 0 | 0 | | | | |

php-fpm warnings are the same two lines in every run ("pool default" and "pool market" reached max_children once, as
in the M2 runs); none for the critical (till) pool.

## 3. Verdict against the criterion (+5 % on POS p95 and Postgres CPU)

- **Postgres CPU: within.** +3.2 % (76.9 -> 79.4 s over 300 s of load), in the same direction in planning (+3.5 %) and
  execution time (+2.8 %). The spread between equal runs is 2.4 % (OFF) and 4.2 % (ON), so the effect is about the
  size of the noise, and under the limit.
- **POS p95: not resolvable to ±5 % on this box, and no sign of a breach.** The aggregate till+poll p95 moved +4.5 %
  during the spike and +4.9 % after it, -4.5 % before it; the write endpoints' p95 means moved +2.4 % (close), +3.0 %
  (pay), +4.3 % (open) to +8.9 % (send). But two EQUAL runs differ by 10-39 % on a single endpoint's p95 (about 280
  calls per endpoint per run) and by 6-19 % on the aggregate: the noise is larger than the criterion, and every ON
  value lies inside or next to the range of the OFF values plus that spread (ON run 2 is as fast as the OFF runs
  everywhere). Said plainly: this run cannot prove "+5 % at p95" either way; it shows no shift larger than the
  run-to-run spread.
- **POS p50 is the steadier signal: +3 to +7 %** (open +3.4 %, add a round +4.2 %, send +4.1 %, bill +6.6 %, pay
  +5.4 %, close +3.0 %: +1 to +10 ms on requests of 43-180 ms). The same +3 to +4 % shows on reads that never take
  the role (`GET /pos/floor`, the kitchen list): with RLS on, the whole box runs about 3 % busier (PHP +2.5 %,
  Postgres +3.2 %, PgBouncer +3.9 % CPU on 4 shared vCPUs), and all requests pay for it, not only the tenant
  transactions.
- **Where the cost is**: 2 902 tenant transactions per run (1.7 per POS write request), 277 single writes wrapped in a
  transaction of their own (BEGIN, the two SETs in one message, COMMIT: three more round trips each), +2.0 %
  statements in all. No statement moved in the top list; the one that is new in the top 12 by time is `select
  exists(select * from "fiscal_receipts" where "subject_type" = ...)` as the tenant role (about 1.05 ms per call, 550
  calls): planning 15 partitions with the policy on each.
- 0 errors on POS and staff endpoints, 0 refusals, 0 `tenant_rls` log lines, 0 lock waits in every run.

## 4. Extended statistics on (store_id, branch_id): not added

`CREATE STATISTICS (dependencies) ON store_id, branch_id` on `orders` and `order_bills` (+ `ANALYZE`), put into the
template, one more run each [M]:

| | OFF mean (3) | OFF + statistics | ON mean (3) | ON + statistics |
|---|---|---|---|---|
| Postgres CPU (s) | 76.91 | 78.07 | 79.40 | 79.30 |
| pg_stat_statements plan / exec (s) | 23.03 / 10.04 | 23.41 / 10.32 | 23.83 / 10.32 | 23.65 / 10.44 |
| till+poll p95 during the spike (ms) | 138.6 | 144.4 | 144.8 | 131.1 |
| `POST /pos/orders` p50 / p95 (ms) | 109.7 / 151.6 | 113.0 / 155.8 | 113.4 / 158.2 | 113.8 / 149.2 |
| `POST /pos/bills/:id/payments` p50 / p95 (ms) | 179.4 / 239.4 | 183.6 / 250.9 | 189.1 / 246.5 | 184.7 / 237.8 |
| `GET /pos/floor` p50 / p95 (ms) | 28.9 / 45.1 | 29.8 / 45.7 | 29.8 / 48.4 | 29.4 / 46.4 |

Every value with statistics lies inside the range of the three runs without them: no measurable change in this load,
in either mode. What the statistics do help is measured in `M4_DESIGN.md` 4.3 (1 M orders): statements with
`branch_id` and no `store_id` inside a tenant transaction (the staff-pay, tips and X-report queries: 39 of 18 447
statements of the POS e2e) get their row estimate back (302 -> 5 999 for 8 782 rows) and one plan becomes 9 times
faster for the largest store (25.6 -> 2.9 ms). None of them is in the lunch load. So: not clear enough to add a
migration now; it stays a proposal for the moment reads mode is considered or those reports show up in production
(`CREATE STATISTICS` takes a brief SHARE UPDATE EXCLUSIVE lock, then `ANALYZE` of the two tables).

## 5. Limits

- One box for everything, 4 vCPUs, three runs per mode: p95 of a single endpoint has about 280 samples per run. A
  claim finer than about ±10 % at p95 needs staging hardware and longer runs.
- PostgreSQL 17 here, 16 in the M2 runs; `track_planning` on (off in M2): absolute numbers are not comparable with
  `RESULTS_M2.md` (Postgres CPU 70.5 s there), only OFF with ON here.
- The lunch load holds no report request inside a tenant transaction and no offline sync batch; their cost under
  the tenant role is in `M4_DESIGN.md` 4.3 (DB only).
