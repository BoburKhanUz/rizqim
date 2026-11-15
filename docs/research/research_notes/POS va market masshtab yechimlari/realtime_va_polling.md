# Real-time delivery and incremental sync for POS / KDS / order tracking (state as of Nov 2026)

Method note: about 17 tool calls. centrifugo.io was unreachable (host not in session allowlist), so Centrifugo/Soketi/Ably/Mercure/Pusher limits, Linear/Figma/Shopify/Discord-blog, Electric/Zero/Replicache primary docs were NOT read. Many web-search summaries were synthesized by the search tool; I flag where a claim is a secondary summary. Community/vendor-authored numbers are labelled.

## 1. Real-time transport choices and operational cost (WebSocket vs SSE vs long poll vs push; Laravel Reverb; mobile limits)

### Takeaway
Production systems pair a persistent socket for latency with a REST snapshot for correctness. Reverb is documented as one process per server with Redis pub/sub scaling, and only community reports give a per-node ceiling (about 10-12k connections on 4 vCPU), which is far above one-store-per-device-fleet needs. Mobile OSes suspend background sockets, so a socket is only trustworthy in the foreground and FCM/APNs is the wake-up path.

### Cited Findings
- Reverb official: every connection is held in memory and consumes a file descriptor; raise `ulimit -n` (docs example 10,000), Nginx `worker_rlimit_nofile`/`worker_connections`, Supervisor `minfds=10000`. — [Laravel 12 Reverb docs (2026)](https://laravel.com/docs/12.x/reverb)
- Reverb official: default ReactPHP loop uses `stream_select`, "typically limited to 1,024 open files"; for more than ~1,000 concurrent connections install `ext-uv` (auto-selected when present). — [Laravel 12 Reverb docs](https://laravel.com/docs/12.x/reverb)
- Reverb official: ephemeral port range 32768-60999 gives ~28,231 connections per IP pair; docs recommend horizontal scaling over widening ports. — [Laravel 12 Reverb docs](https://laravel.com/docs/12.x/reverb)
- Reverb official: horizontal scaling = `REVERB_SCALING_ENABLED=true`, one central Redis, Reverb servers behind a load balancer; a message received by one server is published via Redis to all others. Docs do not mention sticky sessions as a requirement. — [Laravel 12 Reverb docs](https://laravel.com/docs/12.x/reverb)
- Reverb official: `reverb:restart` "gracefully terminates" all connections and Supervisor restarts it, i.e. every client reconnects on each deploy. Pulse integration records connections and messages (run `pulse:check` on one server only). Laravel Cloud sells managed Reverb clusters. — [Laravel 12 Reverb docs](https://laravel.com/docs/12.x/reverb)
- Community (second-hand, "a team I talked to", year not shown, May post): one Reverb 1.3 node hit the wall at 11,400 connections on 4-core c7g.xlarge; planning number about 12,000 per node; 4-node cluster ran 48k connections, 55-65 % CPU during heavy broadcasts; at 48k idle connections about 12 % CPU; 8 ms broadcast grew to ~80 ms at ~12k subscribers. — [DEV.to, Gabriel Anhaia](https://dev.to/gabrielanhaia/laravel-reverb-in-production-lessons-from-50k-concurrent-websocket-connections-2h6f) (not first-hand; internal arithmetic inconsistencies noted for the pong rate, treat as indicative)
- Same community source: shutdown SIGTERM then 30 s then SIGKILL; ~12,000 clients reconnected within 200 ms after a node died; surviving nodes jumped 12,000 to 16,000 connections in 2 s; a connection storm of 50k clients subscribing to 2-3 channels creates 100-150k channel-auth requests; suggested 30 s cache for auth results; moving a presence channel used only for an online counter to a Redis counter cut cluster CPU ~40 %; cost claim Pusher $340/mo vs Reverb cluster+Redis $190/mo. — [same DEV.to post](https://dev.to/gabrielanhaia/laravel-reverb-in-production-lessons-from-50k-concurrent-websocket-connections-2h6f)
- Vendor-authored comparisons (competitors of Reverb, so biased): AnyCable test ~10k virtual users, Reverb 95 MB peak vs 80 MB AnyCable, p95 broadcast latency 923 vs 792 ms; Relay (Go) at 1,000 connections: Reverb ~63 MB, ~95 % CPU peak vs ~38 MB / 18 %; third-party Rust reimplementation reports 21.8 KB per idle Reverb connection. — summarized from search results listing [Evil Martians AnyCable for Laravel](https://evilmartians.com/chronicles/anycable-for-laravel), [DEV.to Relay vs Reverb](https://dev.to/darknautica/relay-vs-laravel-reverb-a-real-performance-benchmark-3md4), [reverb-rs](https://packagist.org/packages/baggins800/reverb-rs) (search-tool summary; pages not fetched)
- Wolt (delivery, engineering blog): moved order tracking from polling to Socket.IO (long-poll then WebSocket transport) and then to a plain `ws` server; Redis pub/sub relays messages and each pod subscribes only to channels of its connected users; target "in the order of thousands" of connections per pod; burst test of 10,000 connections peaked at just over 700 MB; scaling is manual on a connection-count alert; short-lived ES256 JWTs must be renewed before expiry or the server closes the socket. — [Wolt Careers, From polling to WebSockets](https://careers.wolt.com/blog/engineering/from-polling-to-websockets-improving-order-tracking-user-experience) (year not shown on page)
- Wolt failure mode: after a Redis failover the server stopped receiving pub/sub messages without closing client connections (silent staleness); fix was ioredis and a liveness probe that PINGs Redis so pods without Redis get replaced. — [Wolt Careers](https://careers.wolt.com/blog/engineering/from-polling-to-websockets-improving-order-tracking-user-experience)
- Uber RAMEN: older design was SSE with sequence IDs, acks sent by ordinary RPC every 30 s (delivery state unknown up to 30 s); the gRPC rewrite gave real-time acks; server heartbeat every 4 s, client reconnects after 7 s without a heartbeat or message; messages are persisted and retried until TTL (few seconds to 30 min). — [Uber Engineering, Next gen push platform on gRPC](https://www.uber.com/blog/ubers-next-gen-push-platform-on-grpc/) and [Real-time push platform](https://www.uber.com/blog/real-time-push-platform/) (summary via search tool; year not confirmed, roughly 2020-2022)
- Slack: clients fall back to `wss-backup` host with backoff/jitter "and lots of client-side goodies"; deliberately lets some backup traffic land on the failed region to avoid a thundering herd; Koi Pond load-test tool lists "thundering herds, massive message fan-out, unbounded datasets" as scale behaviors to simulate. — [Slack Engineering, Traffic 101](https://slack.engineering/traffic-101-packets-mostly-flow/), [Koi Pond](https://slack.engineering/load-testing-with-koi-pond/)
- Android: under Doze only high-priority FCM messages get through; Google says use high priority only for messages that produce a user-visible notification, otherwise FCM may deprioritize; put content in the payload instead of opening a socket to fetch it. — [Firebase message priority](https://firebase.google.com/docs/cloud-messaging/android/message-priority), [Android Doze](https://developer.android.com/training/monitoring-device-state/doze-standby)
- iOS: Apple forum answer: while app is running the system does not tear down connections, but iOS usually suspends the app shortly after backgrounding; WebKit bug shows WebSocket closing with 1006 after Safari backgrounded 10+ s. — [Apple Developer Forums 716118](https://developer.apple.com/forums/thread/716118), [WebKit bug 308073](https://bugs.webkit.org/show_bug.cgi?id=308073)
- Silent stall: community report that Android WebSockets can look open but be dead after backgrounding; Supabase docs have a dedicated page on silent disconnections in backgrounded apps. — [DEV.to](https://dev.to/jitchakraborty/websockets-can-stall-without-disconnecting-and-its-worse-on-android-3n6e), [Supabase Realtime troubleshooting](https://supabase.com/docs/guides/troubleshooting/realtime-handling-silent-disconnections-in-backgrounded-applications-592794)
- Telegram Mini Apps: Bot API 8.0+ exposes `activated`/`deactivated` events and `Telegram.WebApp.isActive` (false when minimized/inactive tab). Docs say nothing about network/timers while inactive. — [Telegram Mini Apps docs](https://core.telegram.org/bots/webapps)
- Telegram Mini App community reports (forum, unverified) say pages fail when app is minimized or phone locked. — surfaced in search; see [Telegram docs](https://core.telegram.org/api/bots/webapps) for the official API only

### Inferences
- Tills, KDS and customer display are foreground, powered, always-on devices, so a socket is reliable there; waiter phones and the Telegram Mini App are the suspension-prone clients and need resync on `activated`/foreground plus FCM for "new order" alerts.
- With the community ceiling (~11-12k connections per 4 vCPU node) a single Reverb node covers thousands of stores at ~4-6 devices each; the real constraints are file limits, deploy reconnect storms and Redis failover staleness, not raw capacity. (Inference from the figures above, not measured.)
- Wolt's silent Redis pub/sub stall is the same failure class as a lost event, which argues for the safety poll below.

### Gaps
- No primary source for Reverb capacity from Laravel; all per-node numbers are community or competitor-authored.
- Not researched: Soketi, Pusher, Ably, Centrifugo, Mercure official limits (centrifugo.io blocked by session network; others not searched). Pusher protocol docs not read.
- No source found on what transport Toast/Square KDS, Shopify, Trello, Linear, Figma, DoorDash use internally.
- No documented method for caching/batching private-channel auth beyond the community 30 s cache suggestion; Laravel broadcasting auth docs not read. Note that Pusher-protocol auth is per subscribe, so one `/broadcasting/auth` call per private channel per connect (inference from protocol knowledge, not fetched).
- No authoritative iOS/APNs background doc fetched.

## 2. Cheap polling: ETag, since-cursors, change feeds, per-tenant counters

### Takeaway
CouchDB `_changes` (since + last_seq + longpoll/heartbeat) is the canonical cursor design; ETag/304 saves bytes and serialization but not requests, so the biggest measured wins come from removing polls (GitLab -92.56 % calls). A per-store monotonic counter in Redis is an inferred design; I found no public source describing exactly that for POS.

### Cited Findings
- CouchDB: `since` returns changes after a given sequence (`now` supported); response `last_seq` is passed back as `since`; `feed=longpoll` waits for at least one change then closes; `heartbeat` default 60,000 ms; `timeout` default 60,000 ms "to prevent undetected dead connections". — [CouchDB _changes docs](https://docs.couchdb.org/en/stable/api/database/changes.html)
- CouchDB: only the latest change per document is guaranteed; ordering is only partial; duplicates can reappear after shard failover so consumers must be idempotent; clustered `seq` is an opaque string (treat as opaque). — [CouchDB _changes docs](https://docs.couchdb.org/en/stable/api/database/changes.html)
- Matrix `/sync`: `GET /_matrix/client/v3/sync` is the long-poll incremental endpoint with `since`/`next_batch` tokens; I could not read the details (page truncated). — [Matrix Client-Server API](https://spec.matrix.org/latest/client-server-api/)
- GitLab (published Sep 2025): CI job header polled every 30 s; after moving to GraphQL subscriptions over ActionCable API calls fell from 45,729,530/day to 3,403,395/day (-92.56 %), 547,145 to 39,670 per 15 min; no significant CPU increase on services; many UI parts still poll. Article does not mention fallback, ETag or missed events. — [GitLab blog](https://about.gitlab.com/blog/how-we-supercharged-gitlab-ci-statuses-with-websockets/)
- Community: a 400-client 5-second polling loop replaced by push cut load ~90 % while keeping a slower poll for clients behind WebSocket-hostile proxies (anecdotal). — [DEV.to](https://dev.to/krishnamm/we-killed-400-clients-5-second-polling-loop-and-cut-server-load-by-90-1fdf)
- ETag/304 evidence in search was qualitative only (Upstox saw more 304s and less transfer, Picnic "ETags: saving bandwidth 1 Kb at a time"); no percentages found. — [Upstox Engineering](https://medium.com/upstox-engineering/etags-demystified-80b72f3970da), [Picnic](https://jobs.picnic.app/en/blogs/etags-saving-bandwidth-1kb-at-a-time)
- Conditional requests semantics (If-None-Match -> 304 with no body; strong vs weak validators) are defined in RFC 9110 section 13; I did not fetch it this session. — [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110.html#name-conditional-requests)

### Inferences
- Computation sketch (design inference, not from a source): keep `store:{id}:rev` in Redis, `INCR` it inside the same DB transaction's after-commit hook whenever any order/menu/table row for that store changes; the poll endpoint returns `ETag: "<rev>"` (or `204`/`304`) after one Redis GET and never touches Postgres when `If-None-Match` equals the current value. Persist the counter in a Postgres `store_revisions` row (updated in-transaction) as the recovery source if Redis is flushed, and include an epoch so a reset forces a full snapshot.
- Because ETag still costs one request, at 15 s x ~5 devices a store still produces 20 req/min of near-empty responses; with WebSocket on, raise safety poll to 30-60 s so the 304 path carries the load (inferred from GitLab: removing polls beats cheaper polls).
- Use `since=<rev>` returning changed orders (CouchDB-style) so catch-up after a gap is a delta, with a full snapshot fallback when the gap exceeds retention.

### Gaps
- Matrix `/sync` details (`limited`, gap handling), Slack/Trello/GitHub conventions (GitHub uses ETag and `X-Poll-Interval`, from memory only, not verified here), Redis change-counter patterns: not found/fetched.
- No measured CPU/bandwidth percentages for ETag adoption found.

## 3. Missed events, reconnects, gap detection, thundering herds

### Takeaway
Everyone who has published details combines sequence numbers (Discord, Uber, CouchDB) with a snapshot-then-delta resync and jittered backoff (Wolt, Slack). The simplest never-stale design for a kitchen screen is: socket for speed, plus a periodic cheap revision check that forces a snapshot on mismatch.

### Cited Findings
- Discord gateway: every dispatch has sequence `s`; on disconnect the client opens the resume URL and sends Resume (opcode 6) with last seq; close codes other than 1000/1001 should trigger reconnect+resume; failed heartbeat closes the connection; Invalid Session means the resume failed and a full new session is needed. — [Discord Gateway docs](https://docs.discord.com/developers/events/gateway), [Gateway events](https://docs.discord.com/developers/events/gateway-events)
- Discord engineering (3/25/26 voice outage post): gateway monitors sessions, tells clients to reconnect, and "optimistically" resumes on a gateway instance in the same zone; I did not read the overload section. — [Discord blog](https://discord.com/blog/behind-the-scenes-of-the-3-25-26-voice-outage)
- Wolt: client polls REST until the server confirms the pipeline is ready, polls once more, then applies streamed partial updates (snapshot then delta); on socket loss resumes polling with randomized exponential backoff; they accepted a rare race where a stream update is older than the snapshot because partial updates overwrite fields. — [Wolt Careers](https://careers.wolt.com/blog/engineering/from-polling-to-websockets-improving-order-tracking-user-experience)
- Uber: sequence IDs plus heartbeat (4 s server, 7 s client timeout) and TTL-bounded persisted retry. — [Uber Engineering](https://www.uber.com/blog/ubers-next-gen-push-platform-on-grpc/)
- Slack Jan 4 2021 outage: 7 am traffic peak plus network problems saturated the web tier; cold client caches after holidays meant "clients pull down more data than usual on their first connection"; autoscaling tried adding 1,200 servers in 14 min and the provisioning service hit two resource bottlenecks; load balancer panic mode, retries and circuit breaking helped recovery. Slack's post does not describe a reconnect storm; that framing is commenters'. — [Slack Engineering, outage Jan 4 2021](https://slack.engineering/slacks-outage-on-january-4th-2021/)
- Slack client uses backoff/jitter across primary/backup websocket hosts. — [Slack Traffic 101](https://slack.engineering/traffic-101-packets-mostly-flow/)
- Community deploy data: after a node death ~12,000 clients reconnected in a 200 ms window; a 25 s drain handler spreads reconnects (~3,000 extra per surviving node). — [DEV.to](https://dev.to/gabrielanhaia/laravel-reverb-in-production-lessons-from-50k-concurrent-websocket-connections-2h6f)
- Generic guidance (non-authoritative): backoff from 500 ms-1 s capped ~30 s with 10-100 % jitter. — surfaced by search summarizing system-design write-ups; no primary source.

### Inferences
- Simplest guarantee (design, not sourced): every event carries `store_rev`; client keeps `last_rev`; if incoming `rev == last_rev+1` apply, `== last_rev` ignore (idempotent), `> last_rev+1` or any reconnect/foreground/`activated` event triggers `GET /orders?since=last_rev` (or snapshot). Safety poll every 20-30 s with `If-None-Match: <rev>` bounds staleness to that interval even if Reverb or Redis silently stalls (Wolt's failure). Order `version` already present gives per-order idempotent apply (ignore lower version).
- Add 0-100 % jitter to poll start and a random 0-5 s delay on reconnect; on server restart do rolling restarts so only a fraction reconnects at a time.
- Customer display (5 s now) can move to socket plus 30 s check; kitchen is the one place where a 10-15 s safety poll may be worth keeping.

### Gaps
- No PagerDuty incident or Discord reconnect-storm postmortem with numbers found; Slack's own post does not call it a thundering herd.
- No published "typical safety poll interval alongside WebSockets"; figures above (GitLab 30 s before) are pre-migration. Not found.

## 4. Local-first sync engines and POS fit

### Takeaway
Local-first engines are viable for offline reads and queued writes but money/stock rules need a server authority, and the vendor-managed option (Atlas Device Sync) was shut down, which is a concrete platform-risk data point. Toast's offline design is a local hub, not a CRDT.

### Cited Findings
- MongoDB Atlas Device Sync, Device SDKs (Realm sync), Data API deprecated Sept 2024 and reached end-of-life 30 Sept 2025; Realm as a local open-source DB remains but 20.x+ has no cloud sync. — [MongoDB deprecation docs](https://www.mongodb.com/docs/atlas/device-sdks/deprecation.md), [MongoDB forum](https://mongodb.com/community/forums/t/device-sync-and-edge-server-are-deprecated/296035)
- Toast KDS: with an eligible local hub, orders on the same local network still reach KDS during internet/cloud outage ("offline mode with local sync"); without a hub devices fall to plain offline mode; Toast KDS overview says front-of-house changes appear on KDS "in real time". — [Toast KDS offline](https://doc.toasttab.com/doc/platformguide/platformOfflineKDSDevices.html), [Toast KDS overview](https://doc.toasttab.com/doc/platformguide/platformKDSOverview.html)
- Square KDS: community threads indicate KDS offline mode was not shipped; a staffer said it was in development (2026 mentioned, no firm date); KDS appears to be fed by Square servers rather than directly from POS (seller's understanding). — [Square Seller Community thread](https://community.squareup.com/t5/Feedback-Discussions/KDS-Offline-Mode/m-p/709200/highlight/true)
- PowerSync positions itself with an authoritative backend (writes go through your API), whereas legacy ElectricSQL was a direct-to-Postgres CRDT design; current Electric is described as a read-path sync engine with writes left to the app (vendor-written PowerSync comparison; 2026 guides agree). — [PowerSync: ElectricSQL vs PowerSync](https://powersync.com/blog/electricsql-vs-powersync), [PowerSync blog index](https://powersync.com/blog)
- A 2026 taxonomy cited in search lists conflict resolution as LWW / server-authoritative / CRDT. — [pistack.xyz, Oct 2026](https://www.pistack.xyz/posts/2026-10-21-local-first-sync-engines-electricsql-powersync-triplit/) (low-authority blog)

### Inferences
- For payments and stock, last-write-wins or CRDT merges can oversell or double-apply; the safe pattern (PowerSync-style) is local queue of intents, server validates and returns the authoritative result. This matches Toast's choice of a local hub for offline rather than peer merge. Inference, no source states the "why money stays server-authoritative" directly.
- For RIZQIM, the existing `version` plus server-side order transitions already gives an optimistic-concurrency model; a sync engine would add a second state store and, for a small team, operational burden. The offline mode already built (P3-31) plus WebSocket/since-cursor covers the need; adopt a sync engine only if offline-first reads at scale become a requirement. Needs a platform team: Couchbase Sync Gateway, self-hosted Electric/PowerSync clusters.

### Gaps
- Not researched with primary sources: Replicache/Zero, Couchbase Lite, CouchDB/PouchDB for POS, Firestore offline, WatermelonDB, Automerge/Yjs, Ink & Switch essays.
- No found list of which POS or field-service products use PowerSync/Electric/Couchbase; no running-cost figures found.
- Square/Shopify/DoorDash/Linear/Figma/Trello transport choices unresearched.
