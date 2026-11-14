# RIZQIM "one big database" load test kit

The kit writes only into its own gitignored `logs/`, `results/` and `cache/` folders; configuration is environment
variables only (`env.sh`), `backend/.env` is never edited. `cache/` holds Laravel's config cache with the secrets of
`backend/.env`: delete it after a run. Run it on a dev box or staging, never against production data. The results of
the first run (2026-11-14) and their analysis are in `docs/scale/`.
Needs: Postgres 16 with `pg_stat_statements` (+ `auto_explain`) in `shared_preload_libraries`, PHP 8.3 (pdo_pgsql, redis,
opcache), `redis-server`, Node 22, `pgbench`, `sudo -n -u postgres psql`.

| file | what |
|---|---|
| `env.sh` | all env for artisan/server: `DB_DATABASE=scale_big`, `QUEUE_CONNECTION=null`, `BROADCAST_CONNECTION=null` (Reverb off), `CACHE_STORE=redis` on a private redis :16379 (rate limiters/locks as in prod), mail array, Telegram/FCM/SMS creds empty, outbound HTTP black-holed (`http(s)_proxy=127.0.0.1:9`), logs to stderr, opcache + config/route/event cache in `cache/` |
| `server.sh start [workers] / stop` | redis + `php artisan serve` with `PHP_CLI_SERVER_WORKERS` on `$APP_PORT` (18500) |
| `base.sh` | fresh `scale_big`: migrate, `PosStackSeeder`, ONE real POS order and ONE Mini App order through the API (`template_order.mjs`), `sql/templates.sql` copies their row shapes; keeps `scale_tmp` as a copy |
| `sql/gen.sql` | generator, append mode: `-v g1 -v g2 -v days`; a group = 20 market-only + 1 POS-only + 2 hybrid stores with menus, staff, tills/KDS/display, stations, hall+15 tables, 1 500 customers, closed shifts, 30 days of orders with every row a real order writes. FKs stay enforced (no replica mode), so the load itself proves FK integrity |
| `sql/mint.sql` | tokens by SQL, hashed like Sanctum (`sha256(plain)`, client sends `id|plain`): till device + cashier PIN-session (+ the `staff.session_started` audit row), KDS cook session, display device, owner, customers -> JSON on stdout |
| `sql/sizes.sql` | VACUUM ANALYZE, top tables/indexes, MEASURED bytes/order per channel |
| `sql/indexes.sql` | the index experiment (create / drop statements) |
| `load.mjs` | the driver: scenarios `lunch`, `poll`, `reports`, `market` (with spike window), `sync`, `hub`, `probe`; per-endpoint p50/p95/p99, errors, CPU seconds of php/postgres (from /proc), pg_stat_database deltas, pg_stat_activity samples, pg_stat_statements top lists (`--pgss 1`) |
| `pgbench/*.sql` | DB-only: Mini App poll, till poll, top-products report, one POS order's writes (rolled back) |
| `run.sh <S|M|L> [phases]` | end to end: `base gen mint probe load pgbench` |

Reproduce: `./run.sh S` (≈5 min), `./run.sh M gen mint probe`, `./run.sh L gen mint probe load pgbench` (≈45 min).
On staging: run the same with the real compose stack instead of `server.sh` (set `APP_PORT` to nginx), php-fpm
`pm.max_children` tuned, and a bigger `days`/group count (Server-90d profile = ~60 groups x 90 days).
Clean up: `./server.sh stop; sudo -u postgres psql -c 'drop database scale_big' -c 'drop database scale_tmp'; rm -rf cache`.
