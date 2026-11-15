# How far one well-run PostgreSQL primary goes for multi-tenant RIZQIM (as of Nov 2026)

Scope note: gap-filling pass (~34 tool calls) on top of `kop_dokonli_baza_masshtabi.md` (Notion, Figma, Instagram, Shopify, Atlassian, Crunchy tenancy, PgBouncer prepared statements, basic RLS pitfalls are NOT repeated). Items marked [secondary] come from search-result summaries, not from a page I fetched. Many requested companies (Stack Overflow, Basecamp/HEY, Robinhood, Heap, Zalando, Gojek, Braintree, Toast, Square, Linear, Cloudflare, Retool) were NOT retrieved: the Retool URL returned 404 and the others were not reached. Source years are given.

## 1. What documented numbers show the range a single primary handles, and what fails first?

### Takeaway
The best 2026 data point is OpenAI: one write primary plus about 50 read replicas serving ChatGPT (about 800M users) after moving shardable write-heavy workloads to Cosmos DB; it is a read-heavy case and the post gives no exact QPS. GitLab ran about 22 TB on one cluster before splitting by function, and keeps tables under 100 GB. First failures reported across sources: connection count/setup, vacuum/bloat on huge tables, write saturation, restore time, schema-change locks.

### Cited Findings
- OpenAI (Jan 2026, via InfoQ Feb 2026): ChatGPT, 800M users, PostgreSQL load grew more than 10x in a year; one primary, nearly 50 geo-distributed read replicas, p99 in the low double-digit ms; "millions of queries per second" in the title, but no exact QPS, connection counts or replica lag in the article. Shardable write-heavy workloads moved to sharded systems (Azure Cosmos DB); Postgres kept unsharded for read-heavy relational data. — [InfoQ 2026](https://www.infoq.com/news/2026/02/openai-runs-chatgpt-postgres); original [openai.com/index/scaling-postgresql](https://openai.com/index/scaling-postgresql/) returned HTTP 403 to me, so I did not read it directly.
- OpenAI practices (same source): PgBouncer in transaction-pooling mode, multiple pods on Kubernetes; stricter timeouts on idle and long-running transactions; rate-limit backfills and high-volume updates to limit MVCC churn; separate critical and non-critical workloads; tune queries and reduce redundant writes; testing cascading replication to relieve WAL fan-out from the primary. Failure modes named: cache-miss storms, ORM-generated multi-table joins, service-wide retry loops. — [InfoQ 2026](https://www.infoq.com/news/2026/02/openai-runs-chatgpt-postgres)
- OpenAI numbers from secondary blogs only (unverified): Azure max 5,000 connections; connection setup 50 ms to 5 ms with PgBouncer; blogs disagree on statement vs transaction mode. [secondary] — [search summary, e.g. DEV/techlogstack/gend](https://dev.to/techlogstack/openai-runs-chatgpt-for-800-million-users-on-one-postgresql-instance-and-it-works-5o1)
- GitLab.com (PGConf.EU 2022 slides, via search summary): before the main/ci split, one cluster was about 22 TB; after, Main 13 TB and CI 11 TB (each cluster is a full copy then trimmed; figures as reported). CI tables were about 36-40% of database size and about 50% of writes; expected about 2x capacity from the functional split. — [PGConf.EU 2022 GitLab slides](https://www.postgresql.eu/events/pgconfeu2022/sessions/session/3832/slides/320/PGConf.eu%20-%20PostgreSQL%20at%20GitLab%202022.pdf); [GitLab handbook pipeline partitioning](https://handbook.gitlab.com/handbook/engineering/architecture/design-documents/ci_data_decay/pipeline_partitioning) [secondary summary]
- GitLab rule: keep on-disk table size (with indexes) under 100 GB on GitLab.com; in mid-2021 `ci_builds` was 1.5 TB with 31 indexes summing to 1 TB (rationale text of the doc was beyond what I could fetch; figure from search summary). — [GitLab handbook: limit table size to < 100 GB](https://handbook.gitlab.com/handbook/engineering/architecture/design-documents/database_size_limits)
- Retool, Adyen: Postgres FM episode 100 (June 2024) had Notion, Figma and Adyen guests; the page lists Adyen's article "Updating a 50 terabyte PostgreSQL database" (2018) but no numbers or thresholds were retrievable. — [Postgres FM: To 100TB and beyond](https://postgres.fm/episodes/to-100tb-and-beyond)
- Single-node TPS ceiling: Postgres FM "Four million TPS" (July 2024) describes Nikolay's experiment on a 360-vCPU, 1.5-2 TB RAM AMD EPYC machine; transcript and title disagree (4M vs 6M TPS), contention on lightweight locks was the bottleneck; this is a synthetic benchmark. — [Postgres FM](https://postgres.fm/episodes/four-million-tps)
- Rule-of-thumb check: a low-authority blog says "under about 10 TB and 50k writes/s vertical scaling is cheaper"; Citus itself gave no numeric single-node limit, only workload-shape advice. Treat as unverified. — [oneuptime 2026 (low authority)](https://oneuptime.com/blog/post/2026-01-24-postgresql-citus-sharding/view); [Citus multi-tenant page (vendor)](https://www.citusdata.com/use-cases/multi-tenant-apps)

### Inferences
- RIZQIM's projected 270-3,700 req/s and 1.35 TB/year at 6,500 stores are far below OpenAI's (millions QPS) and GitLab's (22 TB) single-cluster levels; the data-size axis would reach about 5 TB only after about 4 years at the top projection (my arithmetic, ignoring retention).
- Every documented stress point in these sources is write saturation, table-size/vacuum or connection handling, not "tenant count".

### Gaps
- No retrievable numbers for Stack Overflow, Basecamp/HEY, Robinhood, Heap, Zalando, Gojek, Braintree, Toast, Square, Linear, Cloudflare, Retool, Instacart beyond the prior file; Shopify pod sizes not found in primary form.
- OpenAI primary post not readable (403); replica lag and exact QPS unknown.
- No expert statement with explicit TB/TPS thresholds from pganalyze/EDB/Crunchy/Neon/Supabase was retrieved.

## 2. Which measures come before sharding, and what does each buy?

### Takeaway
Sources agree on the order: fix queries and connections, add pooling, add read replicas, partition big tables, move logs/shardable writes out, then split by function, and only then shard. Connection pooling is the item with the strongest evidence for RIZQIM's measured 90% connection-setup CPU.

### Cited Findings
- Connection cost, estimates only: Ilia Alshanetsky (PHP core contributor) puts connection overhead at 20-100 ms in cloud setups, with TLS adding 2-3 round trips; his example is a 2 ms query on a 50 ms connection (96% wasted); a persistent PHP connection lookup is microseconds. No benchmark, estimates. Matches RIZQIM's measured 20 ms. — [ilia.ws persistent myth](https://ilia.ws/blog/the-persistent-myth-why-your-php-app-needs-persistent-connection)
- Postgres memory per connection: sources vary, 1.3-2 MB typical, up to 50 MB in some reports; PgBouncer about 2 kB per connection. In one test Postgres used 2,113 MB vs 1,253 MB (PgBouncer) at 500 connections and 7,702 MB vs 1,511 MB at 4,000. — [Percona: Scaling PostgreSQL with PgBouncer](https://www.percona.com/blog/scaling-postgresql-with-pgbouncer/); [Linux Polska test](https://linuxpolska.com/en/knowledge-base/blog/pgbouncer-light-connection-pooler-for-postgresql/)
- Pooler throughput is not always a win: Percona's sysbench-tpcc found direct connections gave 2.5x the TPS of PgBouncer at 56 clients (pooling pays when connections greatly exceed cores or connect/disconnect often); Stack Overflow blog reported about 60% throughput gain; PgCat beat PgBouncer only above about 50 clients in one test. — [Percona](https://www.percona.com/blog/scaling-postgresql-with-pgbouncer/); [Stack Overflow blog 2020](https://stackoverflow.blog/2020/10/14/improve-database-performance-with-connection-pooling/)
- OpenAI: pooler plus replicas plus moving shardable writes out kept one primary (see section 1). — [InfoQ 2026](https://www.infoq.com/news/2026/02/openai-runs-chatgpt-postgres)
- PostgreSQL docs on partitioning: benefit is "normally worthwhile only when a table would otherwise be very large", rule of thumb that the table should exceed server physical memory; DROP/DETACH of a partition is far faster than bulk DELETE and avoids VACUUM overhead. — [PostgreSQL 16 docs: partitioning](https://www.postgresql.org/docs/16/ddl-partitioning.html)
- Heuristic thresholds (single, less established source, a PlanetScale skills repo): partition general tables above about 100 GB (or above RAM) or 20M rows; time-series/log tables above about 50 GB or 10M rows. Tiger Data (Timescale, vendor) says partitioning need is not strictly size-based but gains grow when table or index size exceeds cache. — [PlanetScale database-skills partitioning.md (2026)](https://raw.githubusercontent.com/planetscale/database-skills/main/skills/postgres/references/partitioning.md); [Tiger Data: when to consider partitioning](https://www.tigerdata.com/learn/when-to-consider-postgres-partitioning)
- GitLab: table-size cap 100 GB and functional split (see section 1). — [GitLab handbook](https://handbook.gitlab.com/handbook/engineering/architecture/design-documents/database_size_limits)

### Inferences
- For RIZQIM, thresholds named by sources map to: partition journals once a table passes about 50 GB (log heuristic) or the RAM size; sharding thresholds were not given numerically by any expert source I could open. The only quantitative "sharding" signals are cases (Notion, Figma, GitLab's 22 TB) in the earlier file and above.
- 90% CPU on connection setup means pooling comes first; it needs no schema change.

### Gaps
- No source quantified how many months/years replicas, caching or partitioning buy.
- PgCat/Supavisor/Odyssey comparisons with credible methodology not found.

## 3. Safe recipe for RLS with PgBouncer transaction pooling in PHP

### Takeaway
Set the tenant with `set_config('app.store_id', ?, true)` (transaction-local) as the first statement of every transaction, use a non-owner, non-BYPASSRLS application role plus FORCE RLS, wrap the setting in `(select current_setting(...))` so it becomes an InitPlan, and index `store_id`. Keep Laravel global scopes as the first layer. No source gave measured RLS overhead from a production run.

### Cited Findings
- PostgreSQL 16 docs: superusers and BYPASSRLS roles always bypass RLS; table owners bypass unless `FORCE ROW LEVEL SECURITY`; RI checks (unique/PK/FK) bypass RLS (covert-channel risk); policy expressions run before user conditions except `leakproof` functions; policies that look at other rows/tables can race in READ COMMITTED; policies that only look at the current row are simplest and fastest; `SET row_security = off` raises an error instead of silently filtering (useful for pg_dump so backups are never silently partial). — [PostgreSQL 16 docs: row security](https://www.postgresql.org/docs/16/ddl-rowsecurity.html)
- PlanetScale (2025-26, vendor with a sharding product, critical of RLS): policies run per scanned row; uncached policy plan cost 34,828 vs 10,096 with a `select`-wrapped function (about 3.4x cost, real latency gap "much larger", chart only); STABLE function results are not cached in policies unless wrapped in `SELECT` (InitPlan); through PgBouncer the per-user role identity is lost so you must `SET LOCAL` in every transaction; `statement` pool mode breaks `SET LOCAL`; omitting it leaves `current_setting()` empty or erroring; policies live in `pg_policies` and migration tools often do not track them; SECURITY DEFINER bypasses RLS; a user running queries that return nothing still burns CPU. — [PlanetScale: RLS sounds great until it isn't](https://planetscale.com/blog/rls-sounds-great-until-it-isnt)
- Supabase docs (production RLS user): wrap helper calls as `(select auth.uid()) = user_id` so Postgres caches per statement; index every column used in policies (leading column of a btree only); name the role with `to`; security-definer helper functions with `search_path = ''`; views need `security_invoker = true` (PG15+) or they bypass RLS. The performance page shows only 171 ms seq scan with 100k rows removed by the filter and 0.046 ms indexed lookup, no before/after pairs. — [Supabase RLS](https://supabase.com/docs/guides/database/postgres/row-level-security); [Supabase RLS performance](https://supabase.com/docs/guides/database/postgres/row-level-security-performance)
- PgBouncer: SET/RESET not supported in transaction mode (see earlier file); transaction-local `set_config(..., true)` is cleared at COMMIT/ROLLBACK and so is safe. Search results confirm: set at start of each transaction, requires an active transaction, FORCE RLS, superuser bypass. — [PgBouncer features](https://www.pgbouncer.org/features.html); [django-rls-tenants pooling guide](https://dvoraj75.github.io/django-rls-tenants/guides/connection-pooling) [secondary]
- PHP-specific: no source found that documents RLS+PDO+PgBouncer for Laravel end to end. PHP persistent connections can leave transactions, locks, temp tables and settings on the reused connection; manual advises fixing root cause or using a pooling proxy first. — [PHP manual: persistent connections](https://www.php.net/manual/en/features.persistent-connections.php)

### Inferences (my synthesis, not a documented recipe)
- Per request: `DB::transaction(fn)` begins, first statement `select set_config('app.store_id', ?, true)`, then queries. Every code path that touches tenant tables must be inside a transaction, or the setting is absent; use `current_setting('app.store_id', true)` (missing_ok) and cast with `nullif(..., '')::bigint` so absence returns no rows rather than error.
- Policy form: `USING (store_id = (select nullif(current_setting('app.store_id', true), '')::bigint))`, role-specific `TO app_rw`; migrations run as a separate owner role; `ALTER TABLE ... FORCE ROW LEVEL SECURITY`; app role without BYPASSRLS; composite index starting with `store_id`.
- Platform/admin and marketplace cross-store reads need a separate role or policy branch (or security-definer functions), because the single `app.store_id` variable denies them.
- Test: a CI test that runs as the app role, sets store A, and asserts zero rows for store B on every tenant table, plus a check that every table with `store_id` has RLS enabled and forced.
- Laravel Octane/persistent connections: transaction-local setting does not leak; session `SET` and `search_path` would.

### Gaps
- No measured RLS overhead percentage from a named production user; GitLab's RLS plans and Notion's usage not retrieved (GitLab issue returned 429 earlier).
- No documented Laravel package using RLS + PgBouncer found.

## 4. PHP/Laravel connection handling (pooling, persistent, Octane)

### Takeaway
Options in order of evidence: PgBouncer transaction pooling with emulated prepares (or PHP 8.4 + libpq 17 + `max_prepared_statements`), or PDO persistent connections as a cheaper stopgap; Octane keeps connections per worker by default.

### Cited Findings
- PgBouncer FAQ (current): "PHP/PDO is only compatible when PHP 8.4+ and libpq 17 are used" with prepared-statement tracking (PgBouncer 1.21+, non-zero `max_prepared_statements`); otherwise set `PDO::ATTR_EMULATE_PREPARES` to true; in session pooling use `server_reset_query` with `DISCARD ALL` or at least `DEALLOCATE ALL`. — [PgBouncer FAQ](https://www.pgbouncer.org/faq.html)
- PHP persistent connections: only under Apache 2 handler or FPM; each worker holds its own connection (connection count = worker count, so `max_connections` must exceed max workers plus crons); leftover state risks: open transactions, locks, temp tables, session settings; CLI/cron gain nothing. — [PHP manual](https://www.php.net/manual/en/features.persistent-connections.php)
- Alshanetsky: persistent connections cap connections at FPM worker count and smooth spikes; mitigations: `pm.max_requests`, roll back in shutdown/terminate middleware; a Unix socket to a local pooler avoids most TCP/TLS cost. — [ilia.ws](https://ilia.ws/blog/the-persistent-myth-why-your-php-app-needs-persistent-connection)
- Octane: connections persist across requests by default per best-practices guides; open transactions in long-lived workers leak slowly; singleton/static tenant state leaks between requests (Octane docs warn); SET statements persist on reused connections. Blog-level sources, uneven reliability. — [Octane best practices (michael-rubel)](https://github.com/michael-rubel/laravel-octane-best-practices); [DEV: multi-tenancy bugs](https://dev.to/hafiz619/multi-tenancy-queues-the-three-bugs-every-laravel-saas-hits-in-its-first-year-al1) [secondary]
- A vendor blog (Goldlapel) claims persistent PDO reuse leaves state such as Laravel's `SET search_path`; vendor, verify. — [Goldlapel](https://goldlapel-foyer.pages.dev/grounds/connection-pooling/php-fpm-postgres-connection-exhaustion)

### Inferences
- With 196 conn/s on 4 cores, either persistent connections or a local PgBouncer removes the setup cost; PgBouncer also caps server connections independent of worker count. Since RLS uses transaction-local settings, both are compatible if each request always starts with `set_config(..., true)` inside a transaction.

### Gaps
- No measured Laravel/PDO benchmark for persistent vs PgBouncer; stancl/tenancy and spatie/laravel-multitenancy single-database mode not researched; no large Laravel SaaS report on Postgres scale found.

## 5. Append-only tables (audit, fiscal receipts, history, notifications)

### Takeaway
Monthly declarative partitions with drop/detach retention are documented in the PostgreSQL manual itself; BRIN suits time-ordered insert-only data. Main constraint: unique/PK must include the partition key.

### Cited Findings
- Partitioning limits: PK/unique constraints must include all partition-key columns; no cross-partition exclusion constraints; planner handles up to "a few thousand" partitions if queries prune; each session caches metadata for touched partitions; `DETACH PARTITION CONCURRENTLY` takes only SHARE UPDATE EXCLUSIVE on the parent while plain DETACH/DROP takes ACCESS EXCLUSIVE; a detached partition is an ordinary table that can be dumped before dropping. — [PostgreSQL 16 docs: partitioning](https://www.postgresql.org/docs/16/ddl-partitioning.html)
- BRIN: for very large tables where values correlate with physical position (the docs' example: a store's sales orders, earlier orders earlier in the table); index is very small, lossy (recheck). Default `pages_per_range` not stated on the page I fetched. — [PostgreSQL 16 docs: BRIN](https://www.postgresql.org/docs/16/brin-intro.html)
- pg_partman: retention drops or detaches partitions older than the interval; it never drops the last child; default detaches only. — [pg_partman docs](https://pgxn.org/dist/pg_partman/5.2.3/doc/pg_partman.html); [Crunchy: five features](https://www.crunchydata.com/blog/five-great-features-of-postgres-partition-manager)
- Partitioning benefits maintenance and retention more than query speed; align partition size with retention; pre-create future partitions. — [PlanetScale partitioning skill](https://raw.githubusercontent.com/planetscale/database-skills/main/skills/postgres/references/partitioning.md); [Tiger Data](https://www.tigerdata.com/learn/determining-optimal-postgres-partition-size)
- Archive/ClickHouse: no source with measured compression for Postgres to Parquet/ClickHouse audit pipelines; "4:1 Parquet+ZSTD" appears only as a planning assumption; ClickHouse ClickPipes Postgres CDC was in public beta in 2025 (vendor). — [ClickHouse 2025-03 newsletter](https://clickhouse.com/blog/202503-newsletter) [secondary]

### Inferences
- Audit/receipt tables keyed `(id)` need `(id, created_at)` as PK when partitioned; FKs to partitioned tables need care (not verified in docs fetched). Fiscal receipts may have legal retention, so detach+dump before drop, not just drop.

### Gaps
- No measured index-size/vacuum effect from a production partitioning migration; no documented per-partition `pg_dump` to object storage results; Postgres FM "Append-only tables" episode surfaced but not read.

## 6. Backup and restore, 100 GB to 2 TB

### Takeaway
No credible published restore time exists for 320 GB or 1.35 TB. Available measurements: backup speed 1.02 TiB in 1.7-15.8 min on a 128-vCPU local-NVMe box (backup, not restore), and an 11 TB restore taking 13+ hours on old hardware with compression and likely I/O contention. Restore time is dominated by storage throughput plus WAL replay; the only safe answer is a timed drill.

### Cited Findings
- Postgres.ai (Nov 2025): i4i.32xlarge, 8x NVMe RAID0, 75 Gbps, PG 18, 1.023 TiB: pg_basebackup 950 s (1.08 GiB/s); pgBackRest process-max 4/8/16/32/64/128 = 575/298/164/101/107/133 s; best at 32 (10.1 GiB/s logical, link saturated); more processes added overhead. This measures replica creation (backup transfer) on an exceptionally large machine, not a restore. — [postgres.ai marathon 2-012](https://postgres.ai/blog/20251105-postgres-marathon-2-012-ultra-fast-replica-creation-pgbackrest)
- pgBackRest issue #2066 (2023): about 11-12 TB database (2.6 TB in repo, zst level 3, AES-256), 96 CPU, 764 GB RAM, RAID5-6 SSD, 50 Gb NIC: full backup 2.5-3 h but standby restore about 13 h, second attempt with process-max 25 still halfway at 14 h; restore decompresses after network transfer; processes were in D state (I/O starvation) and maintainer warned more processes would worsen it; PG 9.6, pgBackRest 2.43; closed. — [pgBackRest issue 2066](https://github.com/pgbackrest/pgbackrest/issues/2066)
- pgBackRest 50 TB thread: backup about 26 h at process-max 30 and about 37 h at 10; a reply says upwards of 3 TB/h has been seen. — [pgsql-general thread](https://www.postgresql.org/message-id/CAEDsCzj6PFh=1JXHwBSZkjrsWzo4qjUWnHwE-V_SeU0pPQYQSQ@mail.gmail.com) [secondary summary]
- pgBackRest user guide: `process-max` default 1 ("almost never appropriate"); restores can use more processes than backups because Postgres is stopped; delta restore compares SHA-1 per file and rewrites only mismatches, efficient with process-max; block incremental (v2.46+, recommend 2.52.1+) needs `repo-bundle`; bundling recommended for object stores because file creation is slow on S3/Azure/GCS. — [pgBackRest user guide](https://pgbackrest.org/user-guide.html)
- PostgreSQL 17 incremental backup: needs `summarize_wal=on`; `pg_combinebackup` merges chain in order; restore speed gain comes mostly from less WAL to replay (pganalyze: PITR previously over an hour of replay); no `pg_combinebackup` timing benchmark found. — [pganalyze: Postgres 17 incremental backups](https://pganalyze.com/blog/5mins-postgres-17-incremental-backups); [PGConf.EU 2024 slides](https://postgresql.eu/events/pgconfeu2024/sessions/session/5718/slides/612/Incremental%20Backup%20-%20PGCONF.EU%202024.pdf)
- Unreliable: FlashBlade vendor claim of 22.83 TB/h (vendor marketing); blog estimates of 1 TB restore in under 10 minutes counted transfer only. — [Everpure/FlashBlade blog](https://blog.everpuredata.com/purely-technical/rapid-postgresql-backup-and-recovery-with-pgbackrest-and-flashblade/)
- pg_dump/restore size limits and Barman/WAL-G numbers: not retrieved.

### Inferences (arithmetic, not measured)
- If restore were I/O bound at a sustained 1 GB/s, 320 GB takes about 5-6 min and 1.35 TB about 23 min for file copy; at 300 MB/s (typical modest NVMe/network) about 18 min and 75 min. Add WAL replay (RTO grows with WAL since the last backup; frequent differential/incremental backups bound it) and index/stats warmup. Treat as lower bounds; the 11 TB case ran about 14 times slower than raw copy would predict, likely due to I/O contention, so schedule a restore drill on the real hardware at 320 GB and a synthetic 1.35 TB.

### Gaps
- No ordinary-NVMe restore measurement at 100 GB-2 TB; no WAL-G or Barman benchmarks from credible sources (the DEV 1 TB benchmark was rejected earlier); managed-service restore times (RDS/Cloud SQL) and RPO/RTO guidance not retrieved.

## 7. Cross-tenant reads and cell/pod designs

### Takeaway
Citus documents row-sharded tenant tables where non-tenant-filtered queries fan out to all shards in parallel (fine for dashboards, weak for joins); I found no first-hand Shopify/Notion description of search or analytics fan-out. Cell guidance says to start with few large cells and invest in routing and automation first.

### Cited Findings
- Citus row-based tenant sharding: dense tenant packing and cross-tenant queries supported; shared schema required; queries without the distribution-column filter hit every shard (task count equals shard count); do not shard on timestamps, use native partitioning for time. — [Citus multi-tenant](https://www.citusdata.com/use-cases/multi-tenant-apps/); [Citus choosing distribution column](https://docs.citusdata.com/en/stable/sharding/data_modeling.html)
- Cell architecture (AWS Well-Architected): smaller cells mean more operational burden unless automated; many adopters start with a small number of large cells and shrink later; a routing layer between clients and cells is required; moving to cells may require re-partitioning data across databases; applications without cells can still use canary deployment. — [AWS: why use a cell-based architecture](https://docs.aws.amazon.com/wellarchitected/latest/reducing-scope-of-impact-with-cell-based-architecture/why-to-use-a-cell-based-architecture.html); [InfoQ: cell-based adoption guidelines](https://www.infoq.com/articles/cell-based-architecture-adoption-guidelines) (search summary: more complex and costly, cell should be team-sized)
- Shopify routing table and Slack metadata cluster: see earlier file.

### Inferences
- Minimum control plane (from the cited routing requirement plus the earlier Shopify pattern): a non-sharded table store_id -> cell, per-cell migration runner, per-cell backups. The sources describe the need but give no minimal-team sizing; "when NOT to do it" is only supported by the AWS/InfoQ cost statements above.
- Marketplace listing/search across stores in a single DB can use an indexed table or materialized read model; external search (Elasticsearch/Typesense/Meilisearch) and warehouse (ClickHouse) were not researched.

### Gaps
- Search engines, materialized views, denormalised read models and ClickHouse admin analytics: no sources retrieved.
- Shopify, Notion handling of cross-tenant queries: not found.
- Replica lag handling, Toast/Square data stores: not researched.
