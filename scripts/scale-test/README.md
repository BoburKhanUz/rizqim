# RIZQIM "one big database" load test kit

The kit writes only into its own gitignored `logs/`, `results/` and `cache/` folders; configuration is environment
variables only (`env.sh`), `backend/.env` is never edited. `cache/` holds Laravel's config cache with the secrets of
`backend/.env`: delete it after a run. Run it on a dev box or staging, never against production data. The results of
the first run (2026-11-14) and their analysis are in `docs/scale/`.
Needs: Postgres 16 with `pg_stat_statements` (+ `auto_explain`) in `shared_preload_libraries`, PHP 8.3 (pdo_pgsql, redis,
opcache), `redis-server`, Node 22, `pgbench`, `sudo -n -u postgres psql`.

| file | what |
|---|---|
| `env.sh` | all env for artisan/server: `DB_DATABASE` (default `scale_big`), `SCALE_WORK` (where `logs/`, `results/`, `cache/` go; default this folder), `QUEUE_CONNECTION=null`, `BROADCAST_CONNECTION=null` (Reverb off), `CACHE_STORE=redis` on the kit's own redis `SCALE_REDIS_PORT` (16379; `server.sh start` flushes it, so two runs on one machine need different ports) (rate limiters/locks as in prod), mail array, Telegram/FCM/SMS creds empty, outbound HTTP black-holed (`http(s)_proxy=127.0.0.1:9`), logs to stderr, opcache + config/route/event cache in `cache/` |
| `server.sh start [workers] / stop` | redis + `php artisan serve` with `PHP_CLI_SERVER_WORKERS` on `$APP_PORT` (18500) |
| `SERVER=fpm ./server.sh start` (or `SERVER=fpm ./run.sh ...`) | fpm mode: nginx + php-fpm (`apt-get install nginx php8.3-fpm`) with the repo's real `backend/docker/nginx.conf`, `php-fpm.conf` and `pools.sh` (pools critical / market / default, the routing map, the market limits); only paths and ports are rewritten, into `cache/fpm/`. Sizes and limits: the `FPM_*` / `NGINX_*` env of `pools.sh` (its defaults when unset; `[workers]` is ignored); pool ports `FPM_PORT_BASE` (19000) +0/+1/+2. Logs: `logs/php-fpm-access.log` (each request with its pool name), `logs/nginx-error.log` (limit hits), `logs/nginx-access.log`. Re-measuring L3 against it: `SERVER=fpm ./run.sh L load` |
| `PROF=cpu\|1 SERVER=fpm ./server.sh start` | profiler (`php/prof/`, fpm mode only, as php-fpm's `auto_prepend_file`): `cpu` logs each request's CPU time (getrusage) and wall time into `$PROF_LOG` (`logs/prof.jsonl`); `1` serves it through an instrumented `public/index.php` and also logs phases, each service provider's register+boot, every SQL statement and autoloaded classes; `PROF_TL=1` adds the container-resolution timeline, `PROF_OC=1` OPcache status, `PROF_CLASSES=<file>` the declared classes (input of `php/prof/preload-list.php`, which writes `backend/docker/preload-classes.txt`). Summary: `php php/prof/report.php logs/prof.jsonl [--providers] [--sql]`. Other fpm knobs: `FPM_KIT_INI=0` (only the image's `php.ini`, not `php/zz-scale-opcache.ini`), `FPM_EXTRA_INI_DIR=<dir>` (try a setting), `EVENT_CACHE=0`. Results: `docs/scale/PHP_COST_M2.md` |
| `pgbouncer.sh start / stop / stats` | PgBouncer (transaction mode, `apt-get install pgbouncer`) on `$DB_POOL_PORT` (16432), configured by `infra/pgbouncer/entrypoint.sh` like the compose service; `DB_POOLER=pgbouncer ./server.sh start` (or `./run.sh`) then sends the app through it, as php-fpm in production (`env.sh`: `DB_POOLER`, default `direct` = the first run) |
| `base.sh` | fresh `$DB_DATABASE`: migrate, `PosStackSeeder`, ONE real POS order and ONE Mini App order through the API (`template_order.mjs`), `sql/templates.sql` copies their row shapes; keeps `${DB_DATABASE}_tmp` as a copy |
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
Clean up: `./server.sh stop; sudo -u postgres psql -c 'drop database scale_big' -c 'drop database scale_tmp'; rm -rf cache` (with your own
`DB_DATABASE` / `SCALE_WORK`: those names). Two runs side by side: give each its own `SCALE_WORK`, `SCALE_REDIS_PORT`, `APP_PORT`,
`FPM_PORT_BASE`, `DB_POOL_PORT` + `PGB_DIR` and `DB_DATABASE`.
