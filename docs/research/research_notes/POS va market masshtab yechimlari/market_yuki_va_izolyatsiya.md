# Consumer browse traffic isolation and cheap catalogue/browse serving (as of Nov 2026)

Scope note: research budget was ~17 tool calls. Many platforms in the brief (Deliveroo, JET, Glovo, Delivery Hero, Grab/Gojek, Meituan/Ele.me, iFood, Rappi, Yandex, Instacart, Swiggy/Zomato internals) were NOT found with primary sources; they are listed under Gaps rather than guessed. Several search-engine summaries are secondary and flagged as such.

## 1. What mechanisms separate consumer traffic from order/merchant traffic; which fit a monolith

### Takeaway
Large platforms isolate by cells/pods, per-service concurrency limits, criticality-based load shedding and edge queues; none of that needs a platform team to copy in reduced form. The cheapest documented monolith-level equivalent is separate PHP-FPM pools routed by nginx URI, with a small fixed pool for the low-priority side and a pool reserved for critical traffic.

### Cited Findings
- Google SRE book defines four criticality classes (CRITICAL_PLUS, CRITICAL, SHEDDABLE_PLUS, SHEDDABLE); an overloaded task rejects lower criticality requests first; per-client quotas are set per customer and may sum to more than capacity (e.g. Gmail 4,000 CPU-s/s, others 500 CPU-s/s on a 10,000-CPU backend). Retry budgets: max 3 attempts per request, retry only while retries/requests ratio is below 10%; "overloaded; don't retry" error to stop retry amplification. (Book is older, ~2016; still the canonical reference) — [Google SRE: Handling Overload](https://sre.google/sre-book/handling-overload/)
- AWS Builders' Library: a server should "start rejecting excess requests so that it can focus on the requests it decides to let in"; rejections should be cheap; discard requests older than a bound / use per-request TTL from client deadlines; prefer spillover (fast-fail) over surge queues; LB health pings are the highest priority request; within-quota traffic is favoured over burst traffic; lower-layer controls include iptables connection caps and NGINX `max_conns` "as a last resort", WAF/API gateway rate limits. The article gives no benchmark numbers and does not discuss bulkheads/separate thread pools. — [AWS Builders' Library: Using load shedding to avoid overload](https://builder.aws.com/content/3Eun1EEyX6p2e3VYNyRLSJzLuMV/using-load-shedding-to-avoid-overload)
- DoorDash: instrumented each server with an adaptive concurrency limit from Netflix's concurrency-limits library; after a mid-2021 outage (>2 h) concluded L7 metrics-aware circuit breakers and load shedding must be built into infrastructure, not left to teams. Second outage 12 May 2022 lasted ~3.5 h; Envoy hit active connection/request limits and rejected many requests with 503. Retry storms from high payment-service latency are cited in a third-party summary of the 2021 incident. (Search-engine summary of DoorDash careers-blog posts; original pages not fetched, one returned 403) — [DoorDash blog index via search (careers.doordash.com/?p=9335)](https://careersatdoordash.com/?p=9335); [InfoQ on DoorDash service mesh, 2024](https://infoq.com/news/2024/01/doordash-service-mesh)
- DoorDash cell-based architecture: each cell has multiple Kubernetes clusters, each microservice deploys to exactly one cluster per cell; goal is smaller blast radius of a single-cell failure; non-migrated singleton services sit in one global cell. (Needs a platform team.) — [DoorDash service mesh / zone-aware routing post](https://careersatdoordash.com/blog/staying-in-the-zone-how-doordash-used-a-service-mesh-to-manage-data-transfer-reducing-hops-and-cloud-spend/)
- Netflix: service-level prioritized load shedding (QCon SF Nov 2025, re:Invent 2021 deck) — only titles/abstract seen, details not read. — [InfoQ news, Nov 2025](https://www.infoq.com/news/2025/11/netflix-prioritized-loadshedding); [re:Invent 2021 PDF](https://d1.awsstatic.com/events/reinvent/2021/Keeping_Netflix_reliable_using_prioritized_load_shedding_NFX302.pdf)
- Shopify (Feb 2017, Emil Stolarsky): flash sale writes overwhelmed the platform; edge tier = Nginx + OpenResty Lua; leaky-bucket throttle at the edge with 5 s period; rejected users get a cached queue page (JS polls /checkout; meta-refresh fallback) rather than an error; users who pass get a signed cookie to skip throttle; flaw: random polling made the queue a lottery. A 2016 Kylie Cosmetics sale "took down not just her store, but all others on the database shard". — [Shopify Engineering: scriptable load balancers part I](https://shopify.engineering/surviving-flashes-of-high-write-traffic-using-scriptable-load-balancers-part-i)
- Shopify pods as bulkheads and a per-shop checkout queue once a shop exceeds checkout concurrency: only from secondary newsletters, not primary — [Educative summary](https://www.educative.io/newsletter/system-design/inside-shopifys-multi-tenant-platform-during-bfcm). Semian appears only as a config snippet in a 2022 QCon slide deck (success_threshold 1, error_threshold 3, error_timeout 10); its role not explained there — [QCon London 2022 Shopify slides](https://archive.qconlondon.com/system/files/presentation-slides/qcon_london_2022_-_shopify_architecture.pdf)
- Monolith-level pool split (Tideways, 2016, Magento but framework-agnostic): two PHP-FPM pools; frontend `pm=static, max_children=50`; backend/admin `pm=ondemand, max_children=5, process_idle_timeout=5`; nginx sets the fastcgi socket by URI (`/admin/` -> backend socket). Claim: pools "will not compete and steal each other resources anymore". — [Tideways](https://tideways.com/profiler/blog/seperate-your-slow-backend-from-the-important-frontend-traffic-with-php-fpm)
- Other (non-authoritative guides, 2025-26) recommend per-workload pools (checkout vs API) with own `pm.max_children`, 30-80 MB per Laravel worker for sizing — [dchost](https://www.dchost.com/blog/en/?p=3710), [Deploynix](https://deploynix.io/blog/tuning-php-fpm-for-laravel-workers-memory-and-process-management)
- Wolt (Mar 2024): services tiered 0-3 by criticality to order creation/delivery; production load tests with in-house tool; feature flags, gradual rollouts, fallbacks and graceful degradation added after the 14 Feb 2021 incident (~1 h delivery disruption, ~4 h to full recovery). No explicit kill-switch documented. — [Wolt: prepare for scalability events](https://careers.wolt.com/en/blog/tech/prepare-for-scalability-events-at-wolt)

### Inferences
- For RIZQIM (inference): mapping to a monolith = (a) FPM pool "pos" (merchant/till/order write routes) sized so it always has free workers, (b) FPM pool "market" with a hard `max_children` cap so a Telegram broadcast can only saturate its own pool, (c) nginx `limit_req`/`limit_conn` per route group on the market side and fast 503 + Retry-After when the pool is full (spillover instead of queueing, per AWS). Same code base, same DB; CPU can still be shared, so use cgroup/container CPU limits per pool if tills must be protected from CPU, not only from worker exhaustion.
- DB connection pool and Postgres CPU stay shared unless browse reads are moved to cache/replica; pool split alone does not fix DB contention (Shopify's shared shard incident shows shared DB is the blast-radius edge).
- Cell/pod architectures and Netflix-style adaptive limits are platform-team scale; the edge queue/throttle (Shopify) and pool split are small-team scale.

### Gaps
- No primary source found for Uber Eats, Deliveroo, JET, Glovo, Delivery Hero, Grab/Gojek, Meituan, iFood, Rappi, Yandex, Instacart isolation. No measured numbers for pool-split gains in Laravel found.

## 2. How store lists, menus, search and availability are served; freshness; geo discovery; pagination

### Takeaway
Documented big-platform discovery is served from dedicated search/catalogue services with server-side result caching and pagination, not from the order DB; H3 is reported for Uber Eats geo modelling but only via secondary sources. Primary evidence on "open now"/stop-list freshness TTLs was not found.

### Cited Findings
- Uber Eats search pipeline post (10 Sep 2026): server-side cache returns a smaller first page and holds remaining results for scroll requests; pagination plus async rendering improved above-the-fold latency by >200 ms; overall search latency halved; product-level grouping cut data lookups by >100x (~50 ms); ~120 ms from removing low-yield lexical retrieval. H3 and store-availability freshness are NOT mentioned in this primary post. — [Uber: Uber Eats search pipeline](https://www.uber.com/blog/uber-eats-search-pipeline/)
- H3 used to map each merchant's delivery area (hexagons) so user location matches nearby stores; hex sharding of the world at resolution 2-3 with buffer zones at shard boundaries — secondary (ByteByteGo / QCon talk summaries), unverified against primary — [ByteByteGo](https://blog.bytebytego.com/p/how-uber-eats-handles-billions-of?open=false); [awesome-search summary](https://frutik.github.io/awesome-search/Articles/Optimizing-Search-at-Uber-Eats)
- Uber Eats image dedup: content-addressable cache keyed on image hash + processing spec; avoids re-sending full menu on every upload — [Uber: deduping and storing images](https://www.uber.com/blog/deduping-and-storing-images-at-uber-eats/)
- Wolt: discovery carousels show venues selected by ability to deliver to the customer; wolt.com first-load post (Sep 2024) on streaming personalized discovery content; embeddings framework (Feb 2026) for semantic search; no cache/TTL details found — [Wolt: how search works](https://explore.wolt.com/en/deu/how-search-works); [Wolt: streaming personalized content on first load](https://careers.wolt.com/blog/tech/streaming-personalized-content-on-first-load-at-wolt-com); [Wolt: embeddings framework](https://careers.wolt.com/blog/tech/wolt-embeddings-framework)
- Swiggy (older post) states demand is 4-6 h of peak and near zero 6-8 h/day, so they plan autoscaling to the demand curve — [Swiggy Bytes](https://bytes.swiggy.com/engineering-challenges-at-swiggy-430dea6c86a3)

### Inferences
- Pattern consistent with sources: precompute/cached result pages, paginate small first page (Uber), keep discovery off the transactional path. For RIZQIM (inference): cache the store list per geo cell (e.g. H3 res 7-8 or geohash prefix) for 30-60 s in Redis, compute distance only for the few stores in the user's cell plus neighbours, serve the 129 KB catalogue as versioned JSON (ETag + `Cache-Control` via nginx/CDN, version bumped on menu/stop-list/open-status change events) and paginate per store. Accepted staleness numbers here are my proposals, not documented ones.

### Gaps
- No primary source found on TTLs, event-driven invalidation, stop-list freshness or "open now" caching for DoorDash, Uber, Wolt, Glovo, Delivery Hero etc. DoorDash menu/catalogue platform, store feed, Postgres/CockroachDB moves not retrieved. PostGIS vs Elasticsearch geo comparisons not retrieved.

## 3. Incidents with marketing/peak load and fixes

### Takeaway
Documented incidents mostly involve flash-sale and retry storms; fixes are edge throttling/queues, platform-level load shedding, retry budgets and degradation. No primary food-delivery incident tied to a campaign push (Telegram-style broadcast) was found.

### Cited Findings
- Shopify 2016 flash-sale incident and edge-throttle/queue fix, see section 1 — [Shopify Engineering](https://shopify.engineering/surviving-flashes-of-high-write-traffic-using-scriptable-load-balancers-part-i)
- DoorDash 2021 (>2 h) and 12 May 2022 (~3.5 h) outages; fixes: platform-level circuit breakers, load shedding, adaptive concurrency — [DoorDash via search](https://careersatdoordash.com/?p=9335)
- Wolt Valentine's Day 2021 (~1 h disruption, ~4 h recovery); fixes: tiering, gradual rollouts, fallbacks — [Wolt](https://careers.wolt.com/en/blog/tech/prepare-for-scalability-events-at-wolt)
- Zomato NYE records (4,100 orders/min in 2020; Dec 2025 record deliveries) are news-reported; the Zomato engineering NYE write-up was seen only through a newsletter summary without detail — [Business Today 2021](https://www.businesstoday.in/amp/latest/trends/story/zomato-receives-record-4100-orders-per-minute-on-new-years-eve-283215-2021-01-01); [Biweekly Engineering ep. 28](https://biweekly-engineering.beehiiv.com/p/zomatos-nye-behind-scenes-biweekly-engineering-episode-28)

### Inferences
- A broadcast to a Telegram channel is a self-inflicted flash sale: rate-limiting the send (batches, e.g. spread over minutes) is cheaper than any server-side fix. Not documented by a source found here.

### Gaps
- Swiggy/Zomato IPL and NYE engineering details, waiting-room usage by food apps, campaign-send rate limiting: not found.

## 4. Order-status delivery: push, WebSocket, polling

### Takeaway
Wolt documents the move from polling to WebSockets with randomized exponential-backoff polling as fallback; Deliveroo's public docs show webhooks for partners. No documented polling intervals found for consumer apps.

### Cited Findings
- Wolt (9 Jun 2023): consumer apps polled order tracking in 2020; polling load became impractical at scale; moved to WebSocket (`ws` library, after trying Socket.IO), Redis Pub/Sub per-user channels (`user_<id>`), NLB ingress; clients poll REST until pipeline is acknowledged then once more for a consistent snapshot; on socket close resume polling after randomized exponentially increasing delay; burst test 10,000 connections, peak memory just over 700 MB; target "in the order of thousands" of connections per pod. No polling interval stated. — [Wolt: from polling to WebSockets](https://careers.wolt.com/en/blog/tech/from-polling-to-websockets-improving-order-tracking-user-experience)
- Deliveroo partner API: order.new and order.status_update events via webhook; rider status (location, ETA) pushed via webhook — [Deliveroo API docs](https://api-docs.deliveroo.com/docs/listen-to-new-order-events-via-a-webhook); [rider status](https://api-docs.deliveroo.com/docs/rider-status-api)

### Inferences
- RIZQIM already has Reverb (Laravel WebSocket) per the task list; Wolt's pattern (REST snapshot + socket + jittered backoff fallback) fits it. Polling every 10-15 s from N clients is N/12.5 req/s of PHP load; with 250 users that is about 20 req/s, so moving to push or cached lightweight status endpoint matters (arithmetic, inference).

### Gaps
- No documented intervals/fallbacks for Uber Eats, DoorDash, Swiggy, Glovo, Grab etc.

## 5. Numbers: read/write ratio, cache hit rates, gains

### Cited Findings
- Uber Eats search: latency cut in half; pagination >200 ms above-the-fold gain; product grouping >100x fewer lookups — [Uber](https://www.uber.com/blog/uber-eats-search-pipeline/)
- Wolt WebSocket: 10,000 connections ~700 MB — [Wolt](https://careers.wolt.com/en/blog/tech/from-polling-to-websockets-improving-order-tracking-user-experience)
- Tideways pool example: 50 static vs 5 on-demand workers — [Tideways](https://tideways.com/profiler/blog/seperate-your-slow-backend-from-the-important-frontend-traffic-with-php-fpm)
- Uber integrated cache "40 million reads/s" is mentioned in a newsletter title only (not verified) — [newsletter](https://hw.glich.co/p/how-uber-handles-40-million-reads-per-second-using-an-integrated-cache)

### Gaps
- No consumer read/write ratio or cache hit rate for any food platform found; no Laravel measurements for Octane, response caching, Redis catalogue caches or replica read/write splitting retrieved (Laravel docs cover read/write connections but I did not fetch them). Recommend a follow-up pass on: DoorDash menu/store-feed posts, Delivery Hero/Glovo/Grab/Meituan blogs, Laravel Octane benchmarks.
