# M3 "Reliability": design (journals partitioned, PITR, restore drill, alerts)

Status: design only, at commit 9e94139 (2026-11-17). Nothing is implemented. Postgres behaviour below was checked on a
throw-away database `m3_design` (PG 16.x in the dev container, real schema from `php artisan migrate`; dropped afterwards).
Labels: **[F]** = fact from code/DB (file:line), **[V]** = verified by SQL on `m3_design`, **[E]** = estimate or guess.
Paths are relative to the repo root; backend paths are under `backend/`. One recommendation per decision; rejected
alternatives in one line each.

## 0. Summary of decisions

| # | Decision |
|---|---|
| A | Partition by month NOW: `audit_logs`, `fiscal_receipts`, `notification_logs`. Leave: `order_status_histories`, orders and their children, ledgers. Prune (no partitioning): `integration_events`, `telegram_actions`, `failed_jobs` |
| B | Own artisan command `db:partitions:ensure` (daily, 3 months ahead) + a DEFAULT partition per table + an alert. No pg_partman |
| C | Migration converts inline, one transaction per table (writes wait ~1 s per 140 000 rows, content compared before the swap); above the row threshold it refuses and tells the operator to run it in a quiet window with `PARTITIONS_INLINE_MAX_ROWS` raised. No online converter (built, reviewed three times, removed: section 4) |
| D | Online: audit 7 months, fiscal 13 months, notifications 6 months. After: detach, `pg_dump` the partition, upload with sha256 to S3, catalogue row, verify, drop. Fiscal and audit archives are never deleted. Notifications are dropped without archive |
| E | pgBackRest in a custom `postgres:17-alpine` image, archive-push from the postgres container, backups from the `backup` sidecar; repo1 local volume + repo2 S3 (encrypted); `archive_timeout=60`. Keep the nightly `pg_dump` |
| F | `scripts/restore-drill.sh`: PITR restore of a just-written marker into a scratch data dir inside the `backup` container, checks, one Uzbek report, a `restore_drills` row; monthly automatic |
| G | 13 new `AlertKind` checks in the existing evaluator; ops Telegram sent synchronously; free external monitors (UptimeRobot keyword check + healthchecks.io dead-man) for "server down" |

## 1. Inventory of append-mostly tables

Bytes per order are measured at the L profile (278 547 orders, 2 157 MB) in `docs/scale/RESULTS.md:60-73`; "GB/yr" is at
the target 1 000 stores x (150 POS + 20 Mini App orders/day), x1.3 bloat as in `RESULTS.md:215` [E].

| table | bytes/POS order, /Mini App order | GB/yr @1 000 | read by | rows updated/deleted? |
|---|---|---|---|---|
| audit_logs | 3 014 (6.8 rows), 1 329 (3 rows) | ~227 | store page `where store_id order by id desc paginate` (`Store/AuditLogController.php:60-75`); admin list cursor by id (`Admin/AdminAuditLogController.php:46-68`); subject trail `model_type,model_id order by id desc limit 30/50` (`UserCardPresenter.php:355`, `ComplaintInbox.php:402`, `AdminOrderPresenter.php:351`, `StorePresenter.php:263,555`); funnel `whereIn model_id` (`ApplicationFunnel.php:119`); **POS offline sync** `action, user_id, device_id, created_at >= now-36h` (`PosSyncApplier.php:1087-1093`); export (`AuditLogsExport.php:60`) | no: trigger refuses, except ON DELETE SET NULL of user/store/branch/device (`2026_11_01_100600_make_audit_logs_append_only.php:28-50`) |
| fiscal_receipts | 2 006 (1.02 rows), - | ~143 | by id (`FiscalService.php:128,143,418`), by `store_id+idempotency_key` (`:263`), by `store_id+uuid` (`:292`, `PosFiscalReceiptController.php:33`, `PosFiscalReceipt.php:73`), by uuid (admin `AdminPosFiscalController.php:73,90`), subject (`scopeForSubject`, `BillService.php:242`), refunds sum by `original_receipt_id` (`FiscalDocumentValidator.php:219`), retry sweep `status=pending` every minute (`FiscalService.php:215-228`), store list `store_id [+created_at] order by id desc paginate` (`FiscalReceiptController.php:82-94`) | YES: status, attempts, lease, provider answer (`FiscalService.php:128-140, 203`) |
| notification_logs | -, 791 (6.6 rows) | ~7.5 | feed `user_id order by id desc paginate(20)` + unread count + mark-all-read UPDATE, polled 15 s (`NotificationFeedService.php:15-46`); courier-delay dedupe with `sent_at >=` (`CheckUnacceptedAssignmentsJob.php:58-63`) | YES: `is_read`, `read_at` |
| order_status_histories | 626 (2.95), 828 (3.9) | ~52 | eager-loaded with every order (`StaffOrderPresenter.php:31`, `OrderQueryService.php:178`); correlated subqueries per order row in admin lists and reports (`AdminOrderQuery.php:38-42`, `StoreQualityService.php:49-51`, `PrepTimeReport.php:85`), joins (`OwnerAnalyticsService.php:416`), finance (`MonthlyRevenueReport.php:47`, `RevenueReportService.php:107`) | rare |
| pos_sync_operations | 151 (0.2) | ~11 | `UNIQUE(device_id,seq)`, `UNIQUE(op_id)` = offline idempotency | YES (resolution) |
| integration_events | not in profile (aggregators not live) | ? | retries `status,next_attempt_at`; partial UNIQUE idempotency | YES |
| order_status_logs | 0: legacy, no writer left (only `app/Models/OrderStatusLog.php` and one test) | 0 | - | - |

[V] Schema facts that drive the decision (`\d` on `m3_design`):
- `audit_logs`: PK(id); indexes `(action,created_at)`, `audit_logs_model_index (model_type,model_id)`, `(store_id,created_at)`,
  `(user_id,created_at)`; 4 FKs OUT, all `ON DELETE SET NULL` (users, stores, branches, devices); **no FK points IN**;
  `created_at timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP`; trigger `audit_logs_append_only BEFORE UPDATE OR DELETE`.
- `fiscal_receipts`: UNIQUE `(store_id,idempotency_key)`, UNIQUE `(uuid)`; self-FK `original_receipt_id -> fiscal_receipts(id)`
  (the only FK pointing IN); `store_id ON DELETE CASCADE`; **`created_at` is NULLABLE**; bills/refunds/bookings store
  `fiscal_receipt_uuid` as plain columns, not FKs.
- `notification_logs`: PK, `(order_id)`, `(user_id,id)`; FKs OUT users/orders SET NULL; `sent_at NOT NULL DEFAULT now`.
- `order_status_histories`: `created_at` nullable, FK `order_id ON DELETE CASCADE`. `payments` is referenced by 4 tables
  (`cash_movements`, `pos_refunds`, `pos_tips`, `voucher_entries`): partitioning it would force `(id, paid_at)` FKs there.
- Models: `AuditLog` refuses update/delete in `booted()` and has `UPDATED_AT = null`; `FiscalReceipt`, `PosSyncOperation`,
  `OrderStatusHistory` have explicit `$fillable` (M1); `NotificationLog` `$timestamps = false`. All use `id` as key; no
  model needs a composite key (Eloquent `find($id)` simply probes every partition).
- M2 `RevisionMap`: `audit_logs` NONE (`RevisionMap.php:248`), `fiscal_receipts` BRANCH (`:116`), `notification_logs` USER
  (`:126`). The hook (`RevisionQueryBuilder`) keys on the builder's `from` table, i.e. the parent name: partitioning does
  not change it. **But** `RevisionMapCoverageTest.php:31` lists `Schema::getTables()`, and Laravel's
  `compileTables` (`vendor/.../PostgresGrammar.php:93-99`) returns `relkind in ('r','p')` = every leaf partition too:
  the test must skip `pg_class.relispartition` rows (and stay strict for real tables).

Postgres facts verified [V]:
- `PRIMARY KEY (id)` on a partitioned table fails ("must include all partitioning columns"); `(id, created_at)` works.
  Same for every UNIQUE. Global uniqueness of `id` still holds in practice: one shared sequence, ids never inserted by hand.
- `INSERT ... RETURNING id` (Laravel `insertGetId`) routes and returns normally. No `ON CONFLICT`/upsert targets these
  tables (grep: only `Complaint.php:76` `firstOrCreate`, a select-then-insert).
- A BEFORE ROW trigger created on the partitioned parent is cloned to every partition (also future ones): DELETE and
  UPDATE raise as today; `DELETE FROM users` still sets `user_id` NULL through the FK and the trigger lets it pass.
- `DETACH PARTITION` removes the cloned trigger from the detached table and keeps its FKs; `DROP TABLE` of a partition
  fires no row trigger (append-only is not violated by retention).
- `DETACH ... CONCURRENTLY` is refused while a DEFAULT partition exists. Plain DETACH takes AccessExclusive on the parent
  and the DEFAULT for milliseconds; run it with `lock_timeout`.
- `CREATE TABLE ... PARTITION OF` takes AccessExclusive on the parent; `CREATE TABLE (LIKE ...)` + `ATTACH PARTITION`
  takes only ShareUpdateExclusive on the parent (AccessExclusive on the new table and on the DEFAULT, which is scanned).
- If the DEFAULT already holds rows of a month, creating that month fails ("updated partition constraint for default
  partition would be violated"); fix in one transaction: create standalone, move the rows out of DEFAULT, ATTACH.
- `CREATE TABLE new (LIKE old INCLUDING DEFAULTS)` keeps `nextval('old_id_seq')`; `DROP TABLE old` then fails (the sequence
  is OWNED BY old.id) until `ALTER SEQUENCE old_id_seq OWNED BY new.id`.
- Pruning of the real queries on a 25-partition copy of audit_logs with 1.9 M rows: `store_id = ? ORDER BY id DESC LIMIT 50`
  = Merge Append over one index probe per partition, 0.5 ms execution, 0.9 ms planning; subject trail likewise; the sync
  check with `created_at >=` prunes to 1 partition + DEFAULT. `count(*) where store_id` (the `paginate()` total) reads every
  partition (10 ms here; it already grows with the table today). `ORDER BY created_at DESC` with no filter has no index
  (seq scan, 100 ms): keep `ORDER BY id DESC` (served by the `(id, created_at)` PK of each partition). Planning cost
  grows linearly with partitions [E: ~2 ms at 60], one reason to cap months online.

## 2. Decision A: which tables

| table | decision | why |
|---|---|---|
| audit_logs | **partition by `created_at`, monthly, now** | largest table (591 MB at L, `RESULTS.md:76`), no FK in, never updated, every hot read is either id-ordered LIMIT (cheap per partition) or date-bounded |
| fiscal_receipts | **partition by `created_at`, monthly, now** (own agent, stricter tests) | second largest; legal archive needs a detachable unit; no live data yet, so now is the cheapest moment. Requires: `created_at` backfill + NOT NULL; drop the self-FK; partition-local uniques + an advisory lock for idempotency (section 4) |
| notification_logs | **partition by `sent_at`, monthly, now** | polled every 15 s per open Mini App; old rows are worthless; drop by month |
| order_status_histories | leave (redesign later) | read by `order_id` inside correlated subqueries over order lists: 50 orders x 3 subqueries x N partitions probes. Needs `accepted_at/ready_at` denormalised onto `orders` first. Retention follows orders (out of M3) |
| orders, order_items, order_bills(+items), payments | leave | core records, FKs IN (payments x4), mutable; retention of orders is the next wave's question (see D, growth) |
| pos_sync_operations | leave + prune | idempotency `UNIQUE(op_id)`, `UNIQUE(device_id,seq)` must stay global; prune `applied` rows older than 12 months via `MassPrunable` (owner may say keep) |
| integration_events, telegram_actions | leave + prune | mutable, global idempotency; add `MassPrunable`: integration_events `status in (done, failed)` older than 90 days; telegram_actions `expires_at` older than 30 days. Picked up by the existing daily `model:prune` (`routes/console.php:52`) |
| failed_jobs | prune | schedule `queue:prune-failed --hours=720` daily (not scheduled today) |
| idempotency_keys | leave | already pruned daily; note it is a big WAL producer (9.1 KB written and deleted per POS order, `RESULTS.md:72`) |
| order_status_logs | leave (zero growth); drop in a later cleanup with its test |

Rejected: partition everything big (orders/payments: FKs in, mutable, every query has no date); hash-by-store (does not
help retention); a separate journal database (two connections in one transaction, no FK, double PITR).

## 3. Decision B: partition management

- **Config** `config/partitions.php` (new):
  `tables.audit_logs = [column => created_at, online_months => env ARCHIVE_AUDIT_MONTHS 7, archive => true]`,
  `tables.fiscal_receipts = [created_at, ARCHIVE_FISCAL_MONTHS 13, archive => true]`,
  `tables.notification_logs = [sent_at, ARCHIVE_NOTIFICATION_MONTHS 6, archive => false]`, `months_ahead => 3`,
  `first_month => '2026-01'`. Boundaries are UTC months (`config/app.php:68` timezone UTC; columns are `timestamp(0)`).
- **Names**: `audit_logs_2026_11` (FROM '2026-11-01' TO '2026-12-01'), `audit_logs_default`.
- **`php artisan db:partitions:ensure`** (`app/Console/Commands/PartitionsEnsureCommand.php`, logic in
  `app/Support/Partitions/PartitionManager.php`): for each table, for each month from `first_month` (or the oldest
  attached) to now + `months_ahead`: if missing, `SET LOCAL lock_timeout = '2s'`, `CREATE TABLE x (LIKE parent INCLUDING
  DEFAULTS INCLUDING CONSTRAINTS)`, move any matching rows out of DEFAULT, `ATTACH PARTITION`; retry 3 times on lock
  timeout. Idempotent. Scheduled `dailyAt('00:40')` UTC (05:40 Tashkent, before trade), `withoutOverlapping(30)`, registered
  in a new `app/Providers/PartitionsServiceProvider.php` (pattern of `AdminMonitoringServiceProvider.php:25`).
- **DEFAULT partition**: kept on every table as the safety net (a missing month must never fail an audit insert inside a
  till transaction). Its rows trigger the `partitions` alert; `ensure` empties it.
- **Alert**: `partitions` check (section 7): fewer than 2 future months attached, or `count(*) > 0` in any `*_default`.
- **pg_partman rejected**: not in `postgres:17-alpine`, needs a custom build and `shared_preload_libraries` for its
  background worker; our needs are 3 tables and two statements. Native `pg_cron` rejected for the same reason.
- **Tests and `migrate:fresh`**: the M3 migration creates `first_month .. now+3` plus DEFAULT (~14 months x 3 tables today
  [E: +0.5-1 s once per run, not per test]). RefreshDatabase wraps each test in a transaction; CREATE/ATTACH/DETACH are
  transactional in Postgres, so partition tests roll back cleanly. 81 test files time-travel (`travelTo/setTestNow`),
  almost all to 2026-10/11 [F: grep]; rows outside attached months land in DEFAULT and work, so the suite is not affected
  except the coverage test above. `dropAllTables` (migrate:fresh) drops partitions with their parent.

## 4. Decision C: the conversion migration and future migrations

Migration `database/migrations/2026_11_25_100000_partition_journals.php` (`$withinTransaction = false`, pgsql only,
like `ConcurrentIndex` users). Per table, in order notification_logs, audit_logs, fiscal_receipts:

1. Skip if already partitioned (`pg_class.relkind = 'p'`).
2. If the exact row count is above `config('partitions.inline_max_rows', 200000)`: throw with the Uzbek message giving the
   row count, the lock time and `PARTITIONS_INLINE_MAX_ROWS=<N> php artisan migrate` for a quiet window (section 4 note).
3. Inline path, one transaction, `SET LOCAL lock_timeout = '5s'`, `LOCK TABLE t IN EXCLUSIVE MODE` (reads continue):
   `CREATE TABLE t_p (LIKE t INCLUDING DEFAULTS INCLUDING STORAGE INCLUDING COMMENTS) PARTITION BY RANGE (col)`;
   fiscal only: `UPDATE fiscal_receipts SET created_at = coalesce(sold_at, issued_at, updated_at, now()) WHERE created_at IS
   NULL` and `ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP, SET NOT NULL` on `t_p`; create months + DEFAULT;
   `INSERT INTO t_p SELECT * FROM t`; then constraints and indexes on the parent (built per partition):
   - audit_logs: PK `(id, created_at)`; `(store_id, created_at)`, `(model_type, model_id)`, `(action, created_at)`,
     `(user_id, created_at)` (same set as today, keeps FK SET NULL lookups indexed); the 4 FKs; trigger
     `audit_logs_append_only` on the parent (function unchanged).
   - fiscal_receipts: PK `(id, created_at)`; UNIQUE `(store_id, idempotency_key, created_at)`, UNIQUE `(uuid, created_at)`;
     indexes `(status, created_at)`, `(status, next_attempt_at)`, `(store_id, created_at)`, `(subject_type, subject_id)`,
     `(original_receipt_id)` (new: the self-FK is dropped, its lookup needs it); FKs store/branch/device.
   - notification_logs: PK `(id, sent_at)`; `(user_id, id)`; partial `(user_id) WHERE is_read = false` (unread count);
     `(order_id)`; FKs users/orders.
   `ALTER SEQUENCE t_id_seq OWNED BY t_p.id`; `ALTER TABLE t RENAME TO t_m3_old`; `ALTER TABLE t_p RENAME TO t`; rename
   the parent constraints/indexes to the old names where Laravel names them (`audit_logs_model_index`,
   `fiscal_receipts_uuid_unique`... so later `dropIndex` calls keep working); `DROP TABLE t_m3_old`. At L profile size the
   copy + index build of audit_logs took ~8 s for 619 MB on 4 vCPU [V], fiscal ~4 s, notifications ~1 s [E].
4. `down()`: the reverse (plain table with the old constraints, `INSERT SELECT`, swap), inline only.

**Online converter: removed (2026-11-17).** An online, resumable `db:partitions:convert` (change-capture trigger, batch
copy, `--finish`/`--rollback`/`--drop-old`, old table kept 7 days) was built in M3 and reviewed three times; each review
proved a data-loss or till-blocking bug (a committed UPDATE lost by the capture's `ON CONFLICT DO NOTHING` against the
catch-up; `--finish` livelocking against a steady writer; the archive holding the parent's ACCESS EXCLUSIVE through
several FK lock waits (9.5 s till insert); a rollback leaving the plain table with NULL `created_at`; `--finish`
draining an uncapped remainder and re-checking hashes under the lock (44.6 s); an interrupted `--rollback` leaving no
capture trigger and a re-run losing an update; a column added during the 7-day window making `--drop-old` refuse
forever). Nobody needs it in M3 (no live stores; the three tables are far below the threshold when the migration
runs), so it was removed. The inline path stays: one transaction, bounded lock waits, a content comparison (md5 per
row as jsonb, by column name) before the swap, every user trigger carried and checked; above
`partitions.inline_max_rows` it refuses with the table's size, the lock time (~1 s per 140 000 rows measured; 5 M rows
~36 s) and the one command for a quiet window (`PARTITIONS_INLINE_MAX_ROWS=<N> php artisan migrate`; tills sell offline
meanwhile). A future online converter must get right, as its checklist: every committed change replayed whatever the
interleaving (append-only log, delete only what was applied); copy and catch-up bounded against a steady writer;
everything done under a lock capped in rows and time, `statement_timeout` kept for the whole locked section; content
verified (by column name, schema changes during the window handled); resumable from every interrupted state, rollback
included (capture trigger present whenever a copy exists); the old table's data equal to the new one's (backfills
written to both); triggers carried; strong locks taken up front in a fixed order, bounded.

**Application changes required by the new keys** (stage A2):
- `FiscalService::issue/create` (`FiscalService.php:263,313`): inside the insert transaction take
  `pg_advisory_xact_lock(hashtextextended('fiscal:' || store_id || ':' || idempotency_key, 0))`, then `existing()`, then
  insert. The partition-local UNIQUE stays as the backstop; without the lock two concurrent retries straddling a month
  boundary could create two receipts. Transaction-scoped locks are PgBouncer-safe (`DB_SCALE_PLAN.md:110`).
- `FiscalDocumentValidator.php:219`: add `created_at >= $original->created_at` (prunes older months).
- `FiscalService::sweep` (`:215,223`): add `created_at >= now()->subDays(config('fiscal.retry.max_age_days', 30))`.
- `originalReceipt()` relation keeps working without the FK; the presence of the original is already checked in code
  (`FiscalService.php:288-298`).
- Nothing else must change for correctness; the store audit `paginate()` total gets slower per partition, as today with
  table size (optional later: `simplePaginate`, an API change for partner web; not in M3).

**Rule for FUTURE migrations on partitioned tables** (to `docs/scale/M3_DESIGN.md` and a comment in the migration):
add columns on the parent only (propagates); never `CREATE INDEX CONCURRENTLY` on the parent (not supported): use
`CREATE INDEX ... ON ONLY parent`, then `CONCURRENTLY` per partition, then `ALTER INDEX ... ATTACH PARTITION` (add a
`ConcurrentIndex::createPartitioned()` helper in `app/Support/ConcurrentIndex.php`); every UNIQUE includes the partition
column; no FK may reference these tables; never UPDATE the partition column; raw SQL on a partition name is allowed only
in `app/Support/Partitions/*`.

Rejected: pg_partman `partition_data_proc` (extension); `pg_repack`-style trigger swap (extension); converting by
`ATTACH` of the old table as one big first partition (fast, but the old table has no `(id, created_at)` PK and a NULL
`created_at` in fiscal; and its single "partition" can never be archived by month).

## 5. Decision D: retention and archive

Catalogue table `partition_archives` (migration in A1; RevisionMap NONE): `id, table_name, partition_name, range_from,
range_to, status (detached|uploaded|verified|dropped|failed), rows, bytes, sha256, storage_uri, detached_at,
uploaded_at, verified_at, dropped_at, error`. UNIQUE `(partition_name)`.

Flow (two phases, resumable, each step idempotent):
1. `php artisan db:partitions:archive` (Laravel scheduler, daily 22:40 UTC = 03:40 Tashkent): for each table with
   `archive => true`, months older than `online_months` (counting the current month): `lock_timeout 3s`, `ALTER TABLE
   parent DETACH PARTITION p` (not CONCURRENTLY: DEFAULT exists), `ALTER TABLE p DROP CONSTRAINT` its FKs (a detached
   table must not block deleting a user/store), catalogue row `detached` with `rows` and `min/max(id)`. For `archive =>
   false` (notifications): detach + `DROP TABLE` directly.
2. `scripts/archive-partitions.sh` (in the `backup` sidecar daemon, after the nightly dump): for each `detached` row:
   `pg_dump -Fc --no-owner -t public.<p>` to `/backups/archive/<table>/<YYYY-MM>.dump`, `sha256sum`, rclone upload to
   `${BACKUP_S3_PREFIX}/archive/<table>/`, then **download it back**, verify sha256 and `pg_restore --list`, set
   `verified` with `storage_uri`, `bytes`, `sha256`. If S3 is not configured: status stays `uploaded` (local only) and
   nothing is dropped unless `ARCHIVE_ALLOW_LOCAL_ONLY=1`.
3. Next `db:partitions:archive` run: `DROP TABLE p` for `verified` rows older than 3 days -> `dropped`.
- **Never dropped**: fiscal and audit archive files (S3 prefix `archive/` gets no lifecycle expiry; enable Object Lock /
  bucket versioning where the provider offers it; key without delete permission as `docs/OPERATIONS.md` section 4 already
  recommends). Not partitioned tables (orders, payments, ledgers) are never touched by M3.
- **Reading an archived month**: `scripts/archive-restore.sh <table> <YYYY-MM>` (one command): fetch from S3, check
  sha256, `pg_restore` into a side database `rizqim_archive` on the same server (created if missing), print the row count
  and a ready `psql` line. `--attach` (expert only) re-attaches it to the live parent and marks the catalogue row `held`
  so the archive job skips it. A partition restored into `rizqim_archive` keeps its name (`audit_logs_2026_03`).
- **Size at 1 000 stores** [E] (sections 1 and `RESULTS.md:60-73`, x1.3): without M3 ~745 GB/yr (journals ~430).
  With the defaults: audit steady ~132 GB (7 months), fiscal ~155 GB (13 months), notifications ~4 GB: journals capped at
  ~290 GB. Not capped: orders + children + payments ~315 GB/yr and order_status_histories ~52 GB/yr. End of year 1
  ~650 GB, year 2 ~1.0 TB, then +~367 GB/yr. Archive files (pg_dump -Fc, [E] 4-6x smaller) ~60-80 GB/yr in S3.
  **So M3 alone does not keep the database under the 1 TB trigger of `LAUNCH_PLAN.md:45` past year 2**: the next lever
  is order retention (archive closed orders after N years) or the cells split. Say this to the owner.

Rejected: keep everything online (≥ 715 GB of fiscal alone after 5 years); DELETE-based retention (fights the trigger,
bloat, huge WAL); archive as CSV (loses types/JSON fidelity; `pg_dump -Fc` restores with one command).

## 6. Decision E: PITR

**Tool: pgBackRest** (Alpine package `pgbackrest` in the community repo [E: verify version in CI, the dev container has
Ubuntu's 2.50]). Why: file checksums on every backup and archived WAL, `pgbackrest verify`/`check`, two repos at once
(repo1 posix volume for fast local restore, repo2 S3 for disaster), AES-256 repo encryption, zstd, async archiving with a
spool and `archive-push-queue-max` (the disk cap), retention by full-backup count with WAL expiry, restore by
`--type=time --target=...` into any `--pg1-path`, `info --output=json` for monitoring. Configuration fully by env
(`PGBACKREST_REPO2_S3_*`), like the existing rclone setup (`scripts/backup.sh:93-104`).
Rejected: WAL-G (one storage at a time, release binaries are glibc builds: Alpine needs a source build [E]); Barman (needs
its own server, rsync/ssh or streaming slot); plain `archive_command` + `pg_basebackup` + script (we would rewrite
checksums, retention, encryption and gap detection ourselves).

Topology (stage B1):
- `infra/postgres/Dockerfile` (new): `FROM postgres:17-alpine`, `apk add --no-cache pgbackrest`, `COPY archive.sh`
  (`/usr/local/bin/pitr-archive.sh %p`: if `PITR_ENABLED != 1` exit 0, else `exec pgbackrest --stanza=rizqim
  archive-push "$1"`). `infra/backup/Dockerfile` builds `FROM` the same base stage (same pgbackrest and Postgres binaries
  in both images; it keeps rclone and pg_dump).
- `docker-compose.yml` `postgres`: `build: infra/postgres`, `command: postgres -c wal_level=replica -c archive_mode=on
  -c archive_command='/usr/local/bin/pitr-archive.sh %p' -c archive_timeout=60 -c wal_compression=lz4 -c max_wal_size=2GB`,
  env `PITR_ENABLED`, `PGBACKREST_*`; volumes add `pgbackrest_repo:/var/lib/pgbackrest`, `pgbackrest_spool:/var/spool/pgbackrest`,
  `pg_run:/var/run/postgresql`. `docker-compose.staging.yml:33` overrides `command`: it must repeat these flags.
  First deploy restarts Postgres once (archive_mode) [E: seconds].
- `backup` sidecar: mounts `postgres_data:/var/lib/postgresql/data:ro`, `pgbackrest_repo`, `pg_run` (pgBackRest talks to
  Postgres over the shared unix socket, `pg1-path` identical to the server's `data_directory`). Its daemon
  (`scripts/backup.sh --daemon`, extended): on start `stanza-create` (idempotent) + `check`; full backup Sunday 21:30 UTC,
  diff the other nights 21:30 UTC (02:30 Tashkent), `expire`, then `pg_dump`, then `archive-partitions.sh`; every 5
  minutes `pgbackrest info --output=json` + `df` of the data volume + `du pg_wal` -> `monitoring_markers` keys `pitr`
  and `disk` (same psql path as `record_marker`, `scripts/backup.sh:151-162`). [E] The sidecar-with-shared-PGDATA layout
  is what the CI job must prove; fallback if pgBackRest refuses it: run `pgbackrest backup` via `docker compose exec
  postgres` from a host cron line documented in Uzbek.
- Settings: `repo1-retention-full=2` (~14 days of PITR locally), `repo2-retention-full=4` (~28 days), `compress-type=zst`,
  `process-max=2`, `archive-async=y`, `archive-push-queue-max=4GiB` [E: ~1 day of WAL at 300 stores], `repo2-cipher-type=
  aes-256-cbc` with `PITR_CIPHER_PASS` from the root `.env` (the owner must keep a copy outside the server: lost = backups
  unreadable). repo1 unencrypted (same disk).
- **Loss window**: `archive_timeout=60` forces a segment at most every 60 s when anything was written; async push adds
  seconds. RPO [E] ≤ 1-2 min to both repos; measured by the drill (section 8) and CI.
- **Archive target down**: archive-push to S3 fails -> WAL stays in the spool / `pg_wal`; at 4 GiB pgBackRest drops WAL
  and reports success, so Postgres never fills the disk; the PITR chain then has a gap: alert `wal_archive` (critical:
  "PITR uzildi, to'liq zaxira kerak"); the sidecar runs a full backup automatically once `check` passes again.
- **Restore to a time** (`scripts/pitr-restore.sh "2026-12-01 14:05" [--into <name>]`): always into a NEW scratch dir or
  a NEW named volume, started as a second Postgres on port 5499 inside the `backup` container; it refuses a target path
  equal to the live `data_directory`. Making it live is a separate, typed-confirmation runbook (stop app, point compose
  at the new volume via `POSTGRES_DATA_VOLUME`, start) [E: implementation agent verifies the volume naming default keeps
  existing installs on their current volume].
- **Storage per day** [E]: WAL is not measured yet. Estimate 30-50 KB of WAL per order (31 inserts/56 updates per order,
  `RESULTS.md:202`, 21 orders indexes, full-page writes, idempotency insert+delete) -> at 1 000 stores (170 k orders/day)
  5-8 GB/day raw, ~2-3 GB/day zstd; 28 days in S3 ~70-90 GB + 4 fulls (~0.3 x DB each). Pilot (10 stores): <100 MB/day.
  A2/B3 must replace this with a number: run the scale test L1 with `pg_current_wal_lsn()` before/after.
- **Nightly `pg_dump` stays** (logical second line, survives physical corruption and version changes); add
  `--load-via-partition-root` so a dump restores into any partition layout. When the DB passes ~200 GB [E: >1 h dump]
  make it weekly (`BACKUP_DUMP_WEEKDAY`).
- **Provable in CI** (new job `pitr-drill`, stage B3): build `infra/postgres` and `infra/backup`, `docker compose -f
  docker-compose.yml -f infra/ci/docker-compose.pitr.yml up -d postgres backup minio` (MinIO pinned, bucket created by
  `mc`), `php artisan migrate` from the runner against the exposed port, then `scripts/pitr-ci.sh`: rows batch A, full
  backup, batch B, note T, batch C, wait until archived, `scripts/restore-drill.sh --target-time T --ci` -> assert A+B
  present, C absent, marker present; print `RESTORE_SECONDS`, `RECOVERY_POINT`, `DATA_LOSS_SECONDS` to the step
  summary; second case: stop MinIO, write, assert `wal_archive` failure is visible in `pg_stat_archiver` and that it
  catches up after MinIO returns. [E] 4-6 min job.
- **Provable in this dev container** (PG 16, no Docker): the mechanism only: `apt-get install pgbackrest` (2.50
  available), two `initdb` clusters on 5433/5434 under the scratchpad, posix repo, the same `pitr-ci.sh` steps with
  `--local`. Not provable here: the compose topology, Alpine packages, S3.

## 7. Decision G: alerts

What exists [F]: `alert_rules`/`alerts` tables (dedupe: UNIQUE `alerts_one_unresolved_per_subject (alert_rule_id,
subject_key) WHERE state <> 'resolved'`), `AlertEvaluator` run every minute by `EvaluateAlertsJob` in the scheduler
process, deliberately not queued (`app/Jobs/EvaluateAlertsJob.php:8-15`, `AdminMonitoringServiceProvider.php:25`);
7 kinds (`AlertKind.php:20-27`: failed_jobs_spike, queue_backlog, pos_device_offline, fiscal_failures,
settlement_failures, backup_stale 26 h from `monitoring_markers.backup`, scheduler_stale 180 s); `AlertNotifier` with
cooldown/hourly limit and resolve notices to `TELEGRAM_OPS_CHAT_ID`; control-center Monitoring page
(`platform/admin/src/components/monitoring/MonitoringView.tsx`, `GET /admin/monitoring/health` -> `MonitoringStatus`
with database, redis, queue, scheduler, reverb, backup, sentry). **Gap**: `AlertNotifier` sends through
`TelegramMessenger::queue` (`TelegramMessenger.php:141-151`, `Bus::dispatch`): when the queue worker is down, the
alert about it is queued behind it and never sent. Fix: ops messages via `dispatchSync` (5 s HTTP timeout).

New kinds (one `AlertCheck` each in `app/Services/Admin/Monitoring/Checks/`, defaults editable in Monitoring -> Qoidalar;
every SQL check under `SET LOCAL statement_timeout = '2s'`; needs `pg_monitor` once M4 removes superuser):

| kind | measured | default threshold | severity |
|---|---|---|---|
| db_connections | `count(*) pg_stat_activity` vs `max_connections`; per client vs `PGBOUNCER_MAX_DB_CONNECTIONS` | 80 % | critical |
| db_long_transaction | `now() - xact_start` of active / `idle in transaction` sessions (excluding the `backup` user's pg_dump) | 300 s / 60 s | warning |
| db_lock_waits | sessions with `wait_event_type = 'Lock'` longer than 30 s | ≥ 3 | warning |
| wal_archive | `pg_stat_archiver`: `last_failed_time > last_archived_time`, `.ready` files (`pg_ls_archive_statusdir()`) | > 10 ready or failing 5 min | critical |
| pitr_backup_stale | marker `pitr`: last full / any backup age; marker age | full > 8 d, any > 26 h, marker > 15 min | critical |
| restore_drill_stale | `restore_drills` last `status = ok` age; last run failed | > 35 days; any failure | warning / critical |
| disk_usage | marker `disk` (`df` of the data volume, `pg_wal` bytes, spool bytes) | 80 % warn, 90 % critical; marker > 15 min | critical |
| redis_memory | `INFO memory` used_memory / maxmemory (noeviction: at the cap revision INCRs and queue pushes fail, `OPERATIONS.md:166`) | 80 % / 90 % | critical |
| queue_worker_stale | new `QueueHeartbeatJob` dispatched every minute to `default` writes a cache key | > 180 s | critical |
| realtime_down | `RealtimeHealth::status()` `live = false` (heartbeat via broadcasts queue to Reverb, `RealtimeHealth.php:19`) | 3 min | warning |
| table_bloat | `pg_stat_user_tables` n_dead_tup / n_live_tup on orders, order_items, order_bills, payments, idempotency_keys, personal_access_tokens, product_variants, stores | > 20 % and > 100 k, or no autovacuum for 24 h | warning |
| partitions | future months attached per table; rows in `*_default`; `partition_archives` rows `failed` or `detached` > 3 days | < 2 months ahead; > 0 rows | critical / warning |
| tls_expiry | scheduler opens TLS to `APP_URL` host (PHP `capture_peer_cert`) | 14 d warn, 3 d critical | warning |

Existing `backup_stale` stays for the pg_dump. Control center: `MonitoringStatus::snapshot()` gains `postgres`
(connections, longest transaction, archiver), `pitr` (last full/diff, last WAL archived, lag), `drill` (last result,
restore seconds, loss window), `partitions`, `redis.memory`, `disk`; `MonitoringView.tsx` shows them; OpenAPI regenerated.

**When the app or the server is down** nothing inside can alert. Recommendation (no paid service, owner creates two free
accounts, ~10 min): (1) UptimeRobot free, keyword `"status":"ok"` on `https://<domain>/api/v1/health` every 5 min, alert
to the owner's Telegram/email (already advised in `OPERATIONS.md:135`); (2) healthchecks.io free dead-man switch: the
scheduler pings `HEALTHCHECKS_PING_URL` every minute after `EvaluateAlertsJob` succeeds, the sidecar pings
`PITR_PING_URL` after each backup; silence > 5 min / > 26 h alerts. Without these the owner learns of a dead server from
the stores.

## 8. Decision F: restore drill

One command on the host: `scripts/restore-drill.sh` (monthly by the sidecar daemon, first Sunday 23:00 UTC; on demand any
time). It runs `docker compose exec -T backup sh /usr/local/bin/pitr-drill.sh` and then the app smoke check.
1. Preflight: free space on the data filesystem ≥ 1.2 x `pg_database_size`, else stop with a clear message (and the
   `--from-s3` option for a separate machine). Stanza `check` passes.
2. Marker: upsert `monitoring_markers ('drill_probe', now(), {"token": <uuid>})` in live; wait until its WAL segment is
   archived (`pg_stat_archiver.last_archived_wal` ≥ `pg_walfile_name(lsn)`, timeout 3 min); T = marker time + 1 s; then
   upsert a second token (must be ABSENT after restore). Manifest from live: `count(*)` of orders, order_items, payments,
   fiscal_receipts, audit_logs, notification_logs, users, stores `WHERE created_at <= T - 5 min`, and `max(migration)`,
   `count(*)` of `migrations`.
3. Restore: `pgbackrest restore --stanza=rizqim --repo=1 (or 2) --type=time --target=T --target-action=promote
   --pg1-path=/restore/data`, start `postgres -D /restore/data -p 5499 -c archive_mode=off` inside the sidecar, wait for
   `pg_is_in_recovery() = false`. Timer covers restore + replay + promotion = **RTO**.
4. Checks on the restored copy: first token present, second absent; manifest counts equal; same `migrations` count and
   last name; `pg_amcheck --heapallindexed` on the 10 hottest tables' indexes [E: bounded minutes]; every M3 partition
   and the `audit_logs_append_only` trigger present.
5. App smoke: `docker compose run --rm --no-deps -e DB_HOST=backup -e DB_PORT=5499 -e DB_POOLER=direct -e
   RUN_MIGRATIONS=false backend php artisan db:restore-smoke` (new, A3): `migrate:status` has no pending migration, loads
   the newest order with items, bill, payment and fiscal receipt through the models, reads one audit row.
6. Print (Uzbek): `Tiklash vaqti: 4 daq 12 s`, `Qaytarilgan nuqta: 2026-12-06 23:00:41`, `Ma'lumot yo'qotish oynasi: 38 s`
   (now at step 2 minus the last archived commit time), base backup label, WAL segments replayed, check results.
7. Record: insert into live `restore_drills` (A1 migration, RevisionMap NONE): `started_at, finished_at, status
   (ok|failed), repo, target_time, reached_time, restore_seconds, data_loss_seconds, backup_label, checks json, error`.
   Stop the scratch server, `rm -rf /restore/data`. Alert `restore_drill_stale` reads this table.

Rejected: drill into a second compose service with its own volume (more moving parts, same result); drill from
`pg_dump` only (measures the wrong RTO).

## 9. Decision H: work breakdown

Stage A (backend), agents with disjoint files:
- **A1 Partitions core** (first): `database/migrations/2026_11_25_100000_partition_journals.php`,
  `..._100100_create_partition_archives_table.php`, `..._100200_create_restore_drills_table.php`, `config/partitions.php`,
  `app/Support/Partitions/{PartitionManager,PartitionConverter,PartitionArchiver}.php`,
  `app/Console/Commands/Partitions{Ensure,Convert,Archive,Status}Command.php`, `app/Providers/PartitionsServiceProvider.php`
  + one line in `bootstrap/providers.php`, `app/Support/ConcurrentIndex.php` (`createPartitioned`),
  `app/Domain/Realtime/RevisionMap.php` (2 new tables NONE), `tests/Feature/Realtime/RevisionMapCoverageTest.php`
  (skip `relispartition`), `tests/Feature/Partitions/*`. Prune: `MassPrunable` on `IntegrationEvent`, `TelegramAction`,
  `PosSyncOperation` models + `queue:prune-failed` in `routes/console.php`.
- **A2 Queries** (after A1's schema is merged or frozen as here): `app/Domain/Fiscal/FiscalService.php`,
  `app/Domain/Fiscal/FiscalDocumentValidator.php`, `tests/Feature/Fiscal/*` (concurrent same-key issue across a month
  boundary with two connections, refund of a previous-month receipt, sweep).
- **A3 Monitoring** (parallel with A2): `app/Services/Admin/Monitoring/{AlertKind,AlertNotifier,MonitoringStatus}.php`,
  `Checks/*` (13 new), `app/Jobs/QueueHeartbeatJob.php`, `app/Providers/AdminMonitoringServiceProvider.php`,
  `app/Console/Commands/RestoreSmokeCommand.php`, admin health controller/presenter, `packages/api-spec/openapi.json`,
  `tests/Feature/Admin/Monitoring/*`. Owns the only OpenAPI regeneration of M3.
Stage B (infrastructure):
- **B1 Images + compose**: `infra/postgres/{Dockerfile,archive.sh}`, `infra/backup/Dockerfile`, `docker-compose.yml`,
  `docker-compose.staging.yml`, `.env.example`, `.env.staging.example`, `infra/README.md`.
- **B2 Scripts** (after B1): `scripts/backup.sh` (daemon schedule, markers, `--load-via-partition-root`),
  `scripts/{pitr,pitr-drill,restore-drill,pitr-restore,archive-partitions,archive-restore}.sh` (POSIX sh, shellcheck).
- **B3 CI** (after B2): `.github/workflows/ci.yml` (job `pitr-drill`; infra job checks `pgbackrest version` in both
  images; shellcheck list), `infra/ci/docker-compose.pitr.yml`, `scripts/pitr-ci.sh`.
- **B4 Control center UI** (after A3): `platform/admin/src/components/monitoring/MonitoringView.tsx` (+ test),
  `platform/admin/src/lib/api/monitoring.ts`, `platform/admin/src/i18n/sections/monitoring.ts`, regenerated TS client.
- **B5 Docs in Uzbek** (last): `docs/OPERATIONS.md` (sections 4, 5, 11 + new "Uzluksiz zaxira (PITR)", "Tiklash mashqi",
  "Jurnallar arxivi"), `docs/DEPLOY.md` (sections 6-7: `PITR_ENABLED`, cipher pass custody, first restart).
Order: A1 -> {A2, A3}; B1 -> B2 -> B3; A1 + A3 + B2 before the drill works end to end; B4 after A3; B5 last.

Test plan:
- Rollover with time travel: `travelTo('2027-02-27')`, `db:partitions:ensure` -> `audit_logs_2027_02..2027_05` exist
  (`pg_inherits`); an `AuditLog::create` lands in `2027_02` (`tableoid::regclass`); travel past the horizon -> row in
  `audit_logs_default`, `partitions` finding appears; `ensure` moves it into the new month and the finding resolves.
- Append-only on partitions: delete/update raise; user delete sets NULL; detach + drop succeeds; `PartitionArchiver`
  status machine (detach, never drop unless `verified`, keeps fiscal when S3 off).
- Conversion: migration up/down on a seeded DB, content check, trigger carrying, refusal above the threshold and the
  quiet-window override (the online converter and its tests were removed, section 4).
- Fiscal idempotency across a month boundary (two connections, same key, `travelTo` 23:59:59 / 00:00:01).
- Full suite timing before/after (target: < +2 % [E]).
- PITR and drill: only in CI job `pitr-drill` (and locally with `--local` and pgbackrest 2.50).

Risks, ranked:
1. pgBackRest in the compose topology (sidecar with read-only PGDATA over a shared socket, Alpine package version equal
   in both images, S3 creds inside the postgres container): unproven until the CI job passes; fallback host cron.
2. Fiscal idempotency weakened by partition-local UNIQUE: a duplicate legal receipt if the advisory lock is missed in any
   insert path (`FiscalService::create` is the only `FiscalReceipt::create` [F: grep], keep it so).
3. Archive deletes the only copy: mitigated by download-back verification, 3-day delay, no drop without S3, no delete key.
4. Disk: drill needs ~1.2x DB free; WAL backlog when S3 is down (capped at 4 GiB, then PITR gap).
5. Locks during conversion/detach on a live pilot (audit insert sits inside till transactions): `lock_timeout` + retry,
   run at night; never inline above the row threshold.
6. Planning overhead with many partitions on hot paths (fiscal sweep every minute, notification feed every 15 s): capped
   by months online; measure p95 in the next load test.

Decisions only the owner can make (defaults so work is not blocked):
1. Months online: audit 7, fiscal 13, notifications 6 (dropped, not archived).
2. Archive keep time: fiscal and audit archives forever (≥ 5 years legal) with Object Lock if the provider has it.
3. S3-compatible bucket: needed for PITR repo2 and for any partition drop. Default until it exists: local repo only,
   nothing dropped, a standing "no off-site copy" warning.
4. Who gets alerts: `TELEGRAM_OPS_CHAT_ID` group (owner + one technical person); default = the superadmin chat.
5. External monitors (UptimeRobot + healthchecks.io free accounts): default yes, owner creates them.
6. PITR window: 14 days local, 28 days S3. Drill: monthly, on the same server.
7. Custody of `PITR_CIPHER_PASS` (password manager outside the server). Default: generated at first start, shown once.
8. A night window for the first deploy (one Postgres restart for `archive_mode`).
9. Order retention after M3 (the uncapped ~367 GB/yr): not decided in M3; raise at the pilot review.
