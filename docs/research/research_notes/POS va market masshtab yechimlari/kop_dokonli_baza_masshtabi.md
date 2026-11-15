# Multi-tenant relational DB scaling path: what comparable companies did (as of Nov 2026)

Scope note for the report writer: this file is built from about 20 tool calls. Several requested cases (Square/Block, Toast, Lightspeed, Mercado Libre, Shopee, BigCommerce, Heap, Linear, Robinhood, Salesforce, DoorDash, Citus users, Supabase, stancl/spatie, Laravel Octane) were NOT researched and are listed under Gaps. Items marked [secondary] come from search-result summaries, not from a page I fetched myself. Source years are given where known.

## 1. At what size/load did companies leave a single primary, and what numbers do they give?

### Takeaway
Documented sharding moves happened at "terabytes + a saturated single primary" scale, not at hundreds of GB. Notion sharded a monolith that had grown four orders of magnitude and had stalling VACUUM; Figma was at 65% peak CPU on the largest RDS instance with traffic growing 3x/year; Shopify moved to sharding in 2014 when one MySQL could no longer hold the data. None of the sources gives a tenant-count trigger.

### Cited Findings
- Notion (2021 switchover): 5-year-old single Postgres monolith, grown "four orders of magnitude"; symptoms were DB CPU spikes on-call, VACUUM stalling (could not reclaim dead tuples) and transaction-ID wraparound risk. No total data size is stated in the article. — [Notion: Sharding Postgres at Notion](https://www.notion.com/blog/sharding-postgres-at-notion)
- Notion design: 480 logical shards (each a Postgres schema) on 32 physical DBs (15 per DB); shard key = workspace ID; caps of 500 GB per table and 10 TB per physical DB; at least 60K total IOPS needed; 480 chosen because it has many divisors (32 -> 40 -> 48 hosts without moving to 64). — [Notion](https://www.notion.com/blog/sharding-postgres-at-notion)
- Notion migration: double-write via audit log plus catch-up script (logical replication rejected because it could not keep up with `block` write volume); ~3-day backfill on an m5.24xlarge (96 CPU); 5 minutes scheduled downtime. — [Notion](https://www.notion.com/blog/sharding-postgres-at-notion)
- Notion "would do differently": shard earlier (backfilling a new partition key onto the strained monolith was costly); aim for zero downtime; use a composite primary key instead of separate `id` and `space_id`. — [Notion](https://www.notion.com/blog/sharding-postgres-at-notion)
- Figma (2022-2023): DB traffic grows about 3x per year; one large RDS DB reached upwards of 65% CPU at peak; moved r5.12xlarge to r5.24xlarge (largest available); thousands of connections; terabytes of data. — [Figma: How Figma's databases team lived to tell the scale](https://www.figma.com/blog/how-figma-scaled-to-multiple-databases/)
- Shopify: sharded by `shop_id` attached to all shop-owned tables; each pod is an isolated Shopify instance with one MySQL shard plus Redis and Memcached; "millions of merchants"; pod assignments stored in a routing table in a separate non-sharded control-plane DB; least- and most-used shards differed almost 4x before balancing (about 2x after simulated moves). Article gives no shard sizes or pod counts. — [Shopify Engineering: shard balancing](https://shopify.engineering/mysql-database-shard-balancing-terabyte-scale)
- Shopify history [secondary, quoting Shopify]: "In 2014, we could no longer store all our data in a single MySQL instance - even by buying better hardware"; later moved from shards to pods; one secondary source says more than a hundred pods; large merchants can get dedicated pods. — [Search summary listing Shopify/Kovyrin/ByteByteGo posts](https://blog.bytebytego.com/p/how-shopify-manages-its-petabyte); [Kovyrin interview](https://kovyrin.net/2024/06/16/interview-inside-shopify-monolith/)
- Instagram (2012): "several thousand logical shards" implemented as Postgres schemas, mapped in code to far fewer physical servers, so growth meant moving logical shards between DBs without re-bucketing. Secondary sources disagree on exact shard counts (64 / 2,000 / 4,096), so use only the "several thousand" wording. — [Instagram Engineering: Sharding & IDs](https://instagram-engineering.com/1cf5a71e5a5c); [Instagram SFPUG deck](https://media.postgresql.org/sfpug/instagram_sfpug.pdf)
- Stripe DocDB (MongoDB-based, not relational): reported as >5M queries/s over thousands of shards [secondary]; can split any shard n ways and merge them. — [Stripe: DocDB zero-downtime migrations](https://stripe.dev/blog/how-stripes-document-databases-supported-99.999-uptime-with-zero-downtime-data-migrations)
- Instacart (2020, per a PgDog author who worked there): 16 TB on a single Postgres instance; incident at >90% CPU from a 300 GB table seq-scanned on stale stats; fixed by ANALYZE (CPU to ~30%); table later split 12 ways (largest ~25 GB). Vendor-authored opinion piece. — [PgDog: You should shard your database](https://pgdog.dev/blog/shard-your-database)
- GitLab: main/CI split planned July 2022 because "a large portion of all writes" came from CI; reads scale with replicas, writes cannot; expected roughly 2x write capacity; up to 120 min downtime warned. Article (pre-split) gives no size, connection or CPU numbers and no results. — [GitLab blog: splitting database into main and CI](https://about.gitlab.com/blog/2022/06/02/splitting-database-into-main-and-ci/)

### Inferences
- Every Postgres case that sharded horizontally (Notion, Figma path, Instagram, Instacart) had a single write-saturated primary and/or multi-TB tables, with growth rates of 3x/year or more. RIZQIM's projection (1.35 TB/year at 6,500 stores) is below Notion's own per-table (500 GB) and per-DB (10 TB) caps, but Notion's caps are its own operating choice, not a general limit.
- Notion's regret ("shard earlier, because backfilling a shard key onto a strained monolith was costly") is a reason to keep `store_id` on every table and in composite keys now, even if sharding is years away.

### Gaps
- Square/Block, Toast, Lightspeed, Mercado Libre, Shopee, BigCommerce, Heap, Linear, Robinhood: not researched in this pass.
- Notion's resharding post (the "hidden costs" article) returned 404 at the URL I tried; no resharding numbers were retrieved.
- No source gave a tenant-count or write-rate (TPS) trigger for splitting.

## 2. What was done BEFORE sharding, and how long did each step buy?

### Takeaway
Documented order: bigger instance, read replicas, PgBouncer, then vertical partitioning by table group, then horizontal sharding. Figma's vertical split moved the largest partition's CPU to about 10%, but Figma itself calls it runway, not a final answer.

### Cited Findings
- Figma sequence: instance upgrade to the largest RDS size, multiple read replicas, PgBouncer for thousands of connections, then vertical partitioning (first 2 high-traffic tables, 50 tables by Oct 2022); about 30 s partial impact per operation (~2% requests dropped); largest partition CPU ~10% afterward; horizontal sharding deferred for high-write tables. Figma rejected NoSQL, Vitess and NewSQL options due to migration complexity. — [Figma blog](https://www.figma.com/blog/how-figma-scaled-to-multiple-databases/)
- Figma lessons: partition PgBouncers first, then data; use logical replication (smaller destination, upgrades, reverse replication for rollback); check LSNs before rerouting; static analysis of ActiveRecord was not enough, runtime validators were needed. — [Figma blog](https://www.figma.com/blog/how-figma-scaled-to-multiple-databases/)
- GitLab: replicas for reads, functional split (CI) for writes. — [GitLab blog](https://about.gitlab.com/blog/2022/06/02/splitting-database-into-main-and-ci/)
- PlanetScale docs: shard only if the primary is still a bottleneck "even after you tune Postgres configuration, query performance, indexes, and storage"; signs are persistent write/IOPS limit, working set not fitting RAM, wish to limit blast radius, and a stable routing key such as `customer_id`. No numeric thresholds given. Vendor of a sharding product. — [PlanetScale: when to shard](https://planetscale.com/docs/neki/when-to-shard)
- Instacart example: the first fix was a statistics change plus ANALYZE (CPU 90% to ~30%), not sharding. — [PgDog](https://pgdog.dev/blog/shard-your-database)

### Inferences
- RIZQIM's own load test (connection per request, missing index, shared workers) matches the "fix tuning first" pattern; no source recommends splitting before those are fixed.
- Time bought per step is mostly NOT quantified by the sources (only Figma's ~10% CPU on largest partition after vertical split and GitLab's expected ~2x write capacity).

### Gaps
- No source gave months/years bought by replicas, caching or partitioning.

## 3. Tenancy models: shared tables vs schema-per-tenant vs DB-per-tenant; regrets

### Takeaway
Crunchy Data's guidance: shared tables with tenant_id scale to millions of tenants, schema-per-tenant to hundreds (Citus schema sharding up to "several thousand"), DB-per-tenant to tens. Large products still use per-tenant DBs (Atlassian) or schema-level logical shards (Notion, Instagram), so the model depends on tenant size distribution.

### Cited Findings
- Crunchy Data comparison: database-per-tenant fine for "5 ... 10" databases, avoid beyond a few dozen customers; schema-per-tenant for hundreds (Citus schema-based sharding "generally not to exceed several thousand"), migrations must run across every schema; shared tables with tenant_id for millions; recommends tenant ID on every table; mentions acts_as_tenant / activerecord-multi-tenant / django-multitenant; does not discuss RLS. — [Crunchy Data: Designing your Postgres database for multi-tenancy](https://www.crunchydata.com/blog/designing-your-postgres-database-for-multi-tenancy)
- Atlassian Jira: one Postgres database per tenant (millions of DBs; up to ~4,000 DBs per cluster; ~4 million DBs, ~3,000 instances, 13 regions per a ByteByteGo summary [secondary]); moving to Aurora hit file-count limits (~20 million files per cluster), solved by "drain-convert-refill"; ~1,000 DBs migrated per day on average. Peak figures conflict between secondary sources (38,000 vs 90,000/day). — [Atlassian Engineering](https://www.atlassian.com/blog/atlassian-engineering/migrating-jira-database-platform-to-aws-aurora); [InfoQ](https://infoq.com/news/2025/07/atlassian-jira-postgresql-aurora/)
- Instagram and Notion both used Postgres schemas as logical shards (many schemas per physical DB), not one schema per customer. — [Instagram](https://instagram-engineering.com/1cf5a71e5a5c); [Notion](https://www.notion.com/blog/sharding-postgres-at-notion)
- GitLab reportedly moved from schema-per-tenant to shared schema with RLS for its multi-tenancy work; I could not fetch the issue (HTTP 429). [secondary] — [GitLab issue 321134](https://gitlab.com/gitlab-org/gitlab/-/issues/321134)
- Anecdotes of schema-per-tenant pain (migrations across all schemas, ORM trouble with dynamic schema names, extension relocation) from personal blogs; unverified figures. — [Arkency: What surprised us in Postgres-schema multitenancy](https://blog.arkency.com/what-surprised-us-in-postgres-schema-multitenancy/); [Citus: Lessons learned from Postgres schema sharding](https://www.citusdata.com/blog/2016/12/18/schema-sharding-lessons/)
- A claim that Shopify hit connection limits at 800k tenants on schema-per-tenant came from a single personal blog and conflicts with Shopify's own account (shop_id shared tables); do not use it. — search summary only, no reliable source

### Inferences
- For RIZQIM (thousands of small tenants, 165 tables) schema-per-tenant would multiply every migration by tenant count (165 tables x 2,300+ schemas = 380k+ tables, my arithmetic); consistent with Crunchy's "hundreds" ceiling. Shared tables + store_id remains the documented default.
- Atlassian shows DB-per-tenant works at huge scale only with heavy automation and platform investment.

### Gaps
- No first-hand "moved back from schema-per-tenant" case with numbers retrieved; GitLab issue not read.

## 4. Row-level security and alternatives

### Takeaway
RLS works in production but only if the tenant variable is set with transaction-local scope, the app role is not the table owner/BYPASSRLS, and tenant_id is indexed. Evidence retrieved here is mostly blog-level; no performance numbers from a primary source.

### Cited Findings
- Common pitfalls: plain `SET` leaks tenant context across pooled sessions; use `SET LOCAL` / `set_config(..., true)` inside the transaction; table owners bypass RLS unless `FORCE ROW LEVEL SECURITY`; superusers/BYPASSRLS roles bypass even FORCE; use separate migration and app roles; missing tenant_id index is a frequent mistake. [secondary blog aggregation] — [Permit.io RLS guide](https://www.permit.io/blog/postgres-rls-implementation-guide); [DEV: Postgres RLS for multi-tenant SaaS](https://dev.to/software_mvp-factory/postgresql-row-level-security-for-multi-tenant-saas-1lgp)
- PgBouncer official: in transaction pooling SET/RESET are "Never" supported, `server_reset_query` is not used; `search_path` changes via SET are not tracked before Postgres 18 and can leak between clients. This is why session-level `SET app.store_id` is unsafe behind transaction pooling. — [PgBouncer features](https://www.pgbouncer.org/features.html); [PgBouncer config](https://www.pgbouncer.org/config.html)
- A post titled "Why we stopped using row-level security after 1M rows" exists but I could not verify its content. — [DEV](https://dev.to/canu/why-we-stopped-using-row-level-security-after-1m-rows-4dpb)
- Crunchy recommends application-level enforcement via tenant-aware ORM libraries plus tenant ID on every table. — [Crunchy Data](https://www.crunchydata.com/blog/designing-your-postgres-database-for-multi-tenancy)

### Inferences
- Laravel equivalent of the alternatives: global scope / trait on every tenant model, composite FKs `(store_id, id)`, and a CI test that fails on tenant-less queries. RLS as a second layer would need `set_config('app.store_id', ?, true)` inside each transaction (works with PgBouncer transaction pooling because it is transaction-local; my inference from the PgBouncer docs plus the blog sources).
- The 95% CPU efficiency / RLS overhead numbers seen in search results were unsourced; not reported as fact.

### Gaps
- No named company with RLS performance numbers retrieved (Supabase docs not fetched; GitLab issue not read).

## 5. Cross-tenant features (marketplace search, global users, admin analytics, billing)

### Takeaway
The documented pattern is a separate non-sharded control-plane/global DB for routing and global entities (Shopify, Slack metadata cluster); cross-tenant analytics are moved outside the OLTP primary. Little first-hand detail was retrieved for search/warehouse.

### Cited Findings
- Shopify keeps pod assignments in a routing table in a separate non-sharded control-plane database. — [Shopify Engineering](https://shopify.engineering/mysql-database-shard-balancing-terabyte-scale)
- Slack legacy: a metadata cluster mapped workspace id to shard id; sharding by workspace stopped fitting once product features crossed teams, which drove the move to Vitess. [secondary summary of Slack slides] — [Slack: Scaling datastores with Vitess](https://slack.engineering/scaling-datastores-at-slack-with-vitess/)
- Slack cells (2021+): each AZ is a cell, services siloed per AZ, drain in ~5 min goal; edge LBs and control plane stay non-siloed; this is a fault-isolation cell design, not a data-sharding design. — [Slack: Slack's migration to a cellular architecture](https://slack.engineering/slacks-migration-to-a-cellular-architecture/)

### Inferences
- A marketplace listing across stores would, per these patterns, live in a global table or search index fed from tenant data, not a fan-out query; this is inference, not a documented case.

### Gaps
- No documented source on marketplace search, global users, billing or data-warehouse fan-out; AWS cell-based architecture page returned only a title (no body).

## 6. Append-only/log tables: partitioning and retention

### Takeaway
Native time partitioning plus drop-partition retention is the standard pattern; I found no hard benchmark.

### Cited Findings
- pg_partman default retention only detaches; set `retention_keep_table = false` to drop; dropping partitions avoids bulk DELETE cost. [secondary] — [Crunchy: Five great features of pg_partman](https://www.crunchydata.com/blog/five-great-features-of-postgres-partition-manager); [pg_partman docs](https://access.crunchydata.com/documentation/pg-partman/5.0.0/pg_partman)
- Aha! had an audits table with billions of rows using terabytes, "nearly half of our database"; archived and partitioned with Rails. — [Aha! engineering](https://www.aha.io/engineering/articles/partitioning-a-large-table-in-postgresql-with-rails)
- Instacart: splitting a 300 GB table into ~25 GB pieces cut ALTER TABLE time from >5 min to ~20 s and reduced autovacuum burden. — [PgDog](https://pgdog.dev/blog/shard-your-database)

### Inferences
- RIZQIM's journals (58% of bytes) match the Aha! shape; partitioning them by month and dropping/archiving old partitions is the documented first step, before any tenant split.

### Gaps
- No documented ClickHouse/object-storage move of audit logs with measured results.

## 7. Backup and recovery at 100 GB - 1 TB

### Takeaway
No reliable published restore-time figures were found; sources agree only qualitatively.

### Cited Findings
- pgBackRest documents delta restore (checksum manifest skips matching files) and parallel restore as large reductions in restore time; PITR time scales with WAL to replay. — [pgBackRest README](https://cdn.jsdelivr.net/gh/pgbackrest/pgbackrest@main/README.md)
- Sharding triggers cited include VACUUM, backup and recovery taking unacceptably long. — [PlanetScale](https://planetscale.com/docs/neki/when-to-shard)
- Blog benchmarks of pgBackRest vs WAL-G at 1 TB (e.g. 100 GB WAL in 8m22s vs 19m14s) are unreproducible and were rejected. A claim that pgBackRest lost its sponsor in April 2026 is unverified. — [DEV benchmark post](https://dev.to/johalputt/comparison-2026-pgbackrest-vs-wal-g-postgresql-backup-speed-for-1tb-databases-5n6)

### Inferences
- RIZQIM should run its own timed restore drill at current size and at projected size.

### Gaps
- No pg_dump limits or vendor-reported restore times at 100 GB-1 TB.

## 8. PHP/Laravel specifics

### Takeaway
Behind PgBouncer transaction pooling, either use `PDO::ATTR_EMULATE_PREPARES => true`, or PgBouncer >=1.21 `max_prepared_statements` with PHP 8.4+ and libpq 17.

### Cited Findings
- PgBouncer 1.21.0 added protocol-level prepared-statement tracking in transaction pooling; enable with non-zero `max_prepared_statements` (default 200). SQL-level PREPARE/EXECUTE/DEALLOCATE are not tracked. Per the FAQ, "PHP/PDO is only compatible when PHP 8.4+ and libpq 17" are used. — [PgBouncer FAQ](https://www.pgbouncer.org/faq.html); [PgBouncer config](https://www.pgbouncer.org/config.html)
- With pdo_pgsql built on libpq 16 or older (e.g. Debian 12), PHP frees statements with DEALLOCATE, which fails behind PgBouncer and aborts the transaction. [secondary] — [ClickHouse Managed Postgres Laravel guide](https://clickhouse.com/docs/products/managed-postgres/guides/laravel.md)
- Laravel + PgBouncer failure modes: boolean binding mismatch under pooling, no server-side prepares in transaction mode, undetected lost-connection messages, replica recovery conflicts, stale reads after writes; recommends `PDO::ATTR_EMULATE_PREPARES => true`. Does not cover persistent connections or Octane. — [Layers: Hidden failure modes of Laravel + PgBouncer + PlanetScale](https://www.uselayers.com/articles/the-hidden-failure-modes-of-laravel-pgbouncer-planetscale)
- PgBouncer unsupported in transaction mode: SET/RESET, LISTEN, WITH HOLD cursors, session advisory locks; NOTIFY and ON COMMIT DROP temp tables work. (Relevant to Laravel queue/Reverb code using advisory locks or LISTEN.) — [PgBouncer features](https://www.pgbouncer.org/features.html)

### Inferences
- RIZQIM's "new DB connection per PHP request" cost is the textbook PgBouncer use case (Figma added PgBouncer at thousands of connections). Check that no code uses session advisory locks, LISTEN or session `SET` before enabling transaction pooling.

### Gaps
- Laravel Octane/FrankenPHP/Swoole connection effects, persistent PDO connections, stancl/tenancy, spatie/laravel-multitenancy and large Laravel SaaS reports: not researched.

## 9. Rule-of-thumb thresholds for "one Postgres is enough"

### Takeaway
No authoritative numeric threshold exists; sources give qualitative triggers and anecdotes.

### Cited Findings
- Practitioner guide: multi-TB on one well-provisioned server is normal; working set in RAM, disk not near full (a "reasonably" rule of ~10% of RAM, unattributed); anecdotes of 8 TB constant-write single server and Instacart 16 TB single instance. — [search summary of community sources](https://planetscale.com/blog/how-to-scale-your-database-and-when-to-shard); [PgDog](https://pgdog.dev/blog/shard-your-database)
- Triggers: persistent write/IOPS limit after tuning, working set > RAM, VACUUM/backup/restore too slow. — [PlanetScale](https://planetscale.com/docs/neki/when-to-shard)
- Notion's self-imposed caps: 500 GB/table, 10 TB/DB. — [Notion](https://www.notion.com/blog/sharding-postgres-at-notion)

### Inferences
- RIZQIM's projection (62 GB to 1.35 TB/year, i.e. low single-digit TB within 2-3 years at the high scenario) falls inside the range the cited sources treat as single-primary territory; the decision point would be write/IOPS saturation and VACUUM/restore time, not raw size. This is inference, not a documented threshold.

### Gaps
- No TPS or connection-count rules of thumb from a primary source.
