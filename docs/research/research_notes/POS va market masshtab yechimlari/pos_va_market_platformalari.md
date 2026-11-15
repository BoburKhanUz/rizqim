# Combined POS + online ordering / marketplace platforms: architecture and isolation (as of Nov 2026)

Scope note: research was budget-limited (~25 tool calls). Public, first-party engineering material on how POS and consumer ordering are split is scarce. Most findings below are about the integration model exposed in developer docs (which reveals the boundary), not internal architecture. Coverage is strong for Toast, Square, Wolt, Swiggy, Foodics, Meituan (secondhand), Yandex Eda (partial). Not researched or no usable result: ChowNow, Owner.com, Flipdish, Lightspeed, Petpooja internals, UrbanPiper internals, Posist, DotPe, Zomato, Grab, Ele.me, Rappi, iFood, Delivery Club, Uzum Tezkor, Express24, Poster, iiko, R-Keeper, Glovo. These are listed under Gaps.

## 1. Is the POS database the same as the marketplace database, or separate services? Did acquirers merge or keep apart?

### Takeaway
No company in this sample publicly documents a single shared database for tills and consumer traffic. Every public integration surface (Toast, Wolt, Yandex, Swiggy) treats the POS/merchant system and the consumer marketplace as separate systems linked by APIs, webhooks and sync jobs. Gojek kept Moka as an independent entity after acquiring it. Internal DB topology of Toast and Square is not public.

### Cited Findings
- Gojek acquired Moka (POS) in 2020; the pre-deal problem was that merchants used separate apps (Moka POS for offline orders, GoBiz for GoFood orders). Gojek said the services would be combined for merchants, but Moka kept operating as an independent entity integrated with the Gojek merchant ecosystem. Reported price about $120m, unverified. (2020; secondary press) — [KrASIA](https://www.kr-asia.com/gojek-confirms-acquisition-of-mpos-startup-moka); [AgFunderNews](https://agfundernews.com/gojek-pays-a-reported-120m-for-mpos-startup-moka-to-boost-its-food-merchant-platform); title of [DailySocial "Moka Remains an Open Platform Post Gojek Acquisition"](https://www.dailysocial.id/post/moka-remains-an-open-platform-post-gojek-acquisition) (page returned 404 on fetch, only the headline was seen in search).
- Foodics (MENA POS): per a SingleStore case study (undated), transactions (order submission, inventory, employee ops) ran on MySQL then commercial PostgreSQL, while analytics ran on CitusDB then PostgreSQL; they later unified transactions and analytics on SingleStore, one multi-tenant database serving all customers. Cited scale: 22,000+ customers, 35 markets, 5+ billion orders/year. Citus had outages of about 3 hours up to 4 times a month. Note this is POS vs analytics split, not POS vs consumer ordering. — [SingleStore: Foodics](https://www.singlestore.com/made-on/foodics/)
- Foodics site claims 33,500+ restaurants across MENA (more recent than the 22K in the case study; figures conflict by date, not reconciled). — [Foodics](https://www.foodics.com/cloud-based-pos-system-cut-costs-45-scale-instantly/)
- An unofficial page (f7-platform.pages.dev) claims Foodics runs POS (RMS) and an online ordering app as separate products with separate DBs, the latter originally a separate acquired company. Unverified, appears to be a third-party planning page; do not rely on it. — [f7-platform.pages.dev](https://f7-platform.pages.dev/)
- Toast exposes online ordering to third parties as separate Menus, Orders and Payments APIs, with the POS as the system of record. Toast marketing says one menu and one source of order data across channels. Internal service/DB split not stated. — [Toast developer community](https://developers.toasttab.com/hc/en-us/community/posts/51416793392276-Need-Guidance-on-Building-a-Custom-Online-Ordering-Website-Integrated-with-Toast-POS-Payments); [Toast online ordering](https://pos.toasttab.com/blog/on-the-line/online-ordering-for-restaurants)
- Swiggy (2021, ex-employee Substack, not official): built a layer on top of its existing order management APIs, with one generic API for third-party POS integrations; chose build over acquiring an aggregator. Share of Swiggy orders via POS integration: about 2% at launch, over 10% in a year, near 35% after about four years. — [Asad Abrar, Sep 2021](https://asadabrar.substack.com/p/2-building-the-3rd-party-order-management)
- Meituan (2018, secondhand summary of InfoQ interview): the order system was split from shared code into a standalone service in 2014 (one mirror says at about 100k daily orders) on principles of business isolation, priority-aligned services, and including both logic and data. — [BestHub mirror](https://besthub.dev/articles/how-meituan-scaled-its-food-delivery-order-system-to-millions-of-daily-orders-4858963c5831); [BestHub, 2018](https://www.besthub.dev/articles/how-meituan-waimai-supports-ten-million-daily-orders-evolution-of-its-backend-architecture-ee6ee99c9dc0). Originals on tech.meituan.com were not reached.
- Olo: Rails API (third-party marketplace order delivery) and Ordering API (order injection) are separate API products; Olo acquired Omnivore (2019), a POS abstraction API. Directory-site summary, unverified. — [apis.io Olo](https://apis.io/providers/olo/); [Grubhub/Olo Rails announcement](https://salestechstar.com/partner-management-channel-enablement/olo-teams-up-with-grubhub-to-streamline-delivery-and-digital-orders-for-restaurants/)

### Inferences
- The consistent pattern is: POS = system of record for the store; consumer channel = separate service that talks to POS through a narrow API (menu in, order out). Acquirers (Gojek-Moka) preserved the POS as a separate product, most plausibly to keep multi-marketplace neutrality and avoid coupling, but no source states the reason.
- No evidence found that any major player runs tills and marketplace customers on the same app workers and same DB as RIZQIM currently does.

### Gaps
- Internal DB topology of Toast, Square, Zomato, Grab, Meituan RMS, iFood, Rappi, Yandex, iiko, Poster: not found in public sources.
- Grab/GoBiz merge details beyond press reports: not found.

## 2. How is the menu/catalogue published from POS to the consumer side; how fast do stop-lists propagate?

### Takeaway
Dominant model: POS/integrator pushes menu and availability to the marketplace via API (with per-venue rate limits), marketplace stores its own copy. Stop-lists are often a separate lightweight "availability" call, rate-limited rather than real-time-unbounded.

### Cited Findings
- Wolt Menu API: partner can send full menu, update item details (price), and push real-time availability updates without sending the full menu. A separate inventory endpoint accepts store-specific stock levels from ERP/POS. Rate limits at venue level: menu get/item updates 1 call per 15 minutes, inventory update 1 call per 5 minutes (as summarised by search; the underlying page 404'd on direct fetch, verify). Webhook-based orders. (current docs, 2026) — [Wolt developer docs](https://developer.wolt.com/docs/marketplace-integrations/restaurant-advanced); [Integration API basics](https://developer.wolt.com/docs/partner-integrations)
- Toast Menus API: `GET /menus` capped at 1 request/second per location per client; `GET /metadata` uses default limits. Toast tells integrators to cache data needed in real time. — [Toast rate limiting](https://doc.toasttab.com/doc/devguide/apiRateLimiting.html); [Toast downtime](https://doc.toasttab.com/doc/devguide/apiHandlingDowntimes.html)
- Yandex Eda vendor API has sections for menu, orders, restaurants, delivery zones, working schedule; stop-list ("работа со стоп-листом") and VAT settings are documented in the menu section; two models: PULL (Yandex Eda as client calls partner-implemented methods) and PUSH (Vendor Management Integration API, Yandex as server). Timeouts and rate limits not retrieved. — [Yandex Eda vendor API](https://yandex.ru/dev/eda-vendor/doc/ru/ref/); [Yandex Eda menu help](https://yandex.com/support/eda-vendor-ru/account/menu.html) (listed in search, not fetched)
- Swiggy "Cloud menu": menu sync API pulls the POS menu into Swiggy, cutting update cycle from about 2 days to about 2 seconds, after one-time item-ID mapping (2021, ex-employee account). Swiggy FAQ: POS-integrated restaurants send menu change requests via their POS partner; Swiggy does not process direct requests. — [Substack 2021](https://asadabrar.substack.com/p/2-building-the-3rd-party-order-management); [Swiggy partner FAQ](https://partner.swiggy.com/PartnerFAQ)
- Olo Rails lets brands publish menus, prices, locations to third-party marketplaces; orders are injected into POS. — [Grubhub/Olo](https://salestechstar.com/partner-management-channel-enablement/olo-teams-up-with-grubhub-to-streamline-delivery-and-digital-orders-for-restaurants/)

### Inferences
- A separate read-optimised copy of the catalogue on the consumer side (rather than reading POS tables live) is implied by push-based menu APIs with rate limits, though no source describes the marketplace's internal catalogue store, CDN or search index.
- Stop-list freshness in practice is bounded by integration rate limits (minutes, e.g. Wolt 5 min for inventory) not sub-second.

### Gaps
- No public detail on CDN/search-index/cache layers for menus at Toast, Square, Wolt, Yandex, Meituan.
- Square, Lightspeed, Flipdish, Poster, iiko menu-push specifics not researched.

## 3. How do online orders reach the kitchen; what if the store is offline?

### Takeaway
Pattern is order-in via webhook or polling to POS integration, with idempotency keys and explicit retry rules; POS stays authoritative and consumer side must tolerate POS being unreachable.

### Cited Findings
- Wolt: order status webhooks do not carry full order details; the partner fetches them from the order's `resource_url`. Order API supports accept, reject, mark ready, and a "reached POS" confirmation. Webhooks are HMAC-SHA256 signed. QA demo mandatory before rollout. — [Wolt docs](https://developer.wolt.com/docs/marketplace-integrations/restaurant-advanced) (search summary; direct fetch 404)
- Toast downtime guidance for order submission: 5XX means platform offline; after a 5XX fetch the order to check if it went through; retry after a few seconds; then ask guest to resubmit or cancel; include a unique external identifier to avoid duplicates; back off polling exponentially; pause polling during planned maintenance; planned maintenance is usually on US Thanksgiving and Christmas, announced at least 4 weeks ahead. — [Toast downtime](https://doc.toasttab.com/doc/devguide/apiHandlingDowntimes.html)
- Deliverect (competing middleware, not a POS) documents POS order failover with exponential backoff, max 5 retries. — [Deliverect](https://developers.deliverect.com/v1.1-restaurants/docs/pos-orders)
- Swiggy: orders are pushed into the POS where the restaurant accepts, sends to kitchen and bills; faster acceptance linked by the author to 3-4% shorter delivery time. — [Substack 2021](https://asadabrar.substack.com/p/2-building-the-3rd-party-order-management)
- Square: store can accept card payments offline during outages (offline payments), used in Feb 2025 incident. — [Square postmortem 2025](https://developer.squareup.com/blog/an-analysis-of-the-square-and-cash-app-outage/); [Square AWS resilience](https://squareup.com/us/en/the-bottom-line/inside-square/aws-outage-square-reliability)

### Inferences
- Order-injection durability relies on idempotency key + status check + bounded retries, not on transactional coupling with POS DB.

### Gaps
- WebSocket vs long-poll vs printer-cloud delivery inside first-party systems (Toast Go, Square KDS, Yandex): not found.
- Offline-mode architecture of tills (local store, sync) at Toast/Lightspeed/Poster: not found.

## 4. Isolation mechanisms (separate pools, queues, rate limits, load shedding, tenant throttles, in-store priority)

### Takeaway
The only documented isolation is (a) per-tenant/per-location API rate limits on integrator traffic (Toast, Wolt), and (b) Meituan's service split, peak/normal instance separation, and degradation switches. No source states that in-store tills get a dedicated worker pool or DB; that is not publicly documented.

### Cited Findings
- Toast: global limit 20 req/s and 10,000 req/15 min across all APIs; default per-API same; `GET /ordersBulk` 5 req/s per client per location; when `Toast-Restaurant-External-ID` header is present limits are per restaurant location, otherwise per IP; account-level limits (across locations) for select high-volume integrations; excess gets HTTP 429. — [Toast rate limiting](https://doc.toasttab.com/doc/devguide/apiRateLimiting.html)
- Toast engineering blog lists posts on event-driven async messaging to decouple services (Apache Camel + Pulsar deferred ACK; safe deployment of background workers); undated, unrelated to online ordering specifically. — [Toast tech blog](https://technology.toasttab.com/tag/architecture)
- Wolt rate limits are per venue (see section 2). — [Wolt docs](https://developer.wolt.com/docs/marketplace-integrations/restaurant-advanced)
- Meituan (2018, secondhand): self-protection via rate limiting, degradation, circuit breaking; core order chain (browse, display, order, pay, deliver) protected first and non-core degraded; over 100 degradation switches via incident system; 1.5x peak redundant capacity; sharded order/store/user tables; full-link stress tests; fault-injection drills. — [BestHub 2018](https://www.besthub.dev/articles/how-meituan-waimai-supports-ten-million-daily-orders-evolution-of-its-backend-architecture-ee6ee99c9dc0)
- Meituan group-buy order system: split into order placement, order query, coupon issuance; separate instances for normal vs promotional spike traffic with distinct URIs for physical isolation; thread-pool isolation, circuit breaking, fallbacks on delivery side (secondhand mirrors, years not verified). — [BestHub](https://besthub.dev/articles/meituan-group-order-system-optimization-sharding-and-service-oriented-refactoring-d2dea054121b); [BestHub delivery](https://besthub.dev/articles/meituan-instant-logistics-evolution-of-distributed-system-architecture-and-technical-challenges-aa8760e72d66)

### Inferences
- Directly applicable to RIZQIM (not stated by sources): per-tenant/location throttles, separate instance pools addressed by distinct URI/host for marketplace vs till traffic, and ranked degradation switches are the documented techniques; Meituan's "separate instances for spikes with distinct URIs" is the closest published analogue to splitting tills from marketplace workers.
- Toast's per-location limiting means one integrator's burst for one store does not starve other stores; it is protection of the API, not proven protection of in-store tills.

### Gaps
- No found statement from Toast/Square/Lightspeed/Olo on dedicated pools or DB for in-store transactions.
- Original Meituan tech.meituan.com posts (Chinese) not reached; only third-party mirrors.

## 5. Outage stories (consumer/online failure hurting POS or reverse) and changes after

### Takeaway
Found incidents where shared infrastructure failures hit POS and online together, but none explicitly caused by consumer traffic spikes taking down tills.

### Cited Findings
- Square, 26 Feb 2025: routine security certificate bundle update (19:40 UTC) broke certificate validation between services and databases; payments (and Cash App) failed from about 20:20; rolled back 20:42; card-present payments switched to offline processing 20:53; full recovery 22:13. Remediation: eliminate manual certificate process, audit deployment surfaces, reassess offline playbook, improve degraded modes. Offline payments did not work for all sellers. — [Square 2025](https://developer.squareup.com/blog/an-analysis-of-the-square-and-cash-app-outage/)
- Square, AWS us-east-1 outage (Oct 2025): Payments, POS, Login, Auth run multi-region; traffic redirected in about 30 min; Europe sellers routed mainly through us-east-1 saw up to 30% transaction failures at peak; refunds delayed (must run in original region); changes: review cross-region background-job dependencies, continue gamedays. — [Block Engineering, Oct 2025](https://engineering.block.xyz/blog/building-for-resilience)
- Square status-page incidents (titles only, not fetched): Mar 2025 Orders not loading/syncing, later affecting KDS and Floor Plans; Oct 2024 Square Online checkout and order creation/update; Apr 2023 Online/third-party order webhooks stuck pending while orders remained visible in app. — [Square status](https://ie.issquareup.com/history); [isdown](https://isdown.app/status/square/incidents/326509-point-of-sale-disruption)
- Foodics earlier stack: Citus outages about 3 hours up to 4 times a month, driving DB migration. — [SingleStore](https://www.singlestore.com/made-on/foodics/)

### Inferences
- The Square Apr 2023 case (webhooks pending, in-app orders still accurate) suggests the integration/webhook layer is decoupled from the order store, so a failure there did not stop in-store views. Inference from a status-page snippet only.

### Gaps
- No postmortem found where marketplace/consumer load degraded in-store POS (Toast, Olo, Lightspeed, Meituan, Zomato, Grab). Toast and Olo status histories not searched.

## 6. Scale numbers: Meituan, Toast, Square

### Takeaway
Toast and Meituan figures exist but are not tied to architecture detail; Square has no scale figures obtained.

### Cited Findings
- Toast FY2025: about 164,000 locations at year end (+22%), record 30,000 net adds in 2025; ARR over $2.0B (+26%); GPV $195.1B (+23%). No online-ordering-specific figures, no requests/s. — [Toast Q4/FY2025 release, Feb 2026](https://www.businesswire.com/news/home/20260212058106/en/Toast-Announces-Fourth-Quarter-and-Full-Year-2025-Financial-Results)
- Meituan Waimai: about ten million orders/day (2018 interview, secondhand); no QPS given on that page. — [BestHub 2018](https://www.besthub.dev/articles/how-meituan-waimai-supports-ten-million-daily-orders-evolution-of-its-backend-architecture-ee6ee99c9dc0)
- Olo: $23B+ GMV, 2M+ orders/day per one financial article (year not confirmed). — via search summary; [Olo providers page](https://apis.io/providers/olo/) (not directly confirming)
- Meituan RMS (restaurant management system) architecture: not found.

### Gaps
- Square restaurants/orders per day/requests per second: not found. Meituan current (2025-26) order volumes and RMS numbers: not found. Toast requests/s: not found.

## What changed as they grew
- Meituan: extracted the order system into an independent service (2014), later sharded and split by function and by spike vs normal instances (secondhand). Sources above.
- Foodics: MySQL, Citus, PostgreSQL, then one multi-tenant SingleStore for transactions plus analytics (undated). Source above.
- Swiggy: from manual menu mapping to generic POS API plus cloud menu sync (2021). Source above.
- Square: moved critical POS/payments paths to multi-region and now improving offline/degraded modes (2025). Sources above.

## Overall Gaps (not covered, for follow-up)
ChowNow, Owner.com, Flipdish, Lightspeed Order Anywhere, Petpooja, UrbanPiper, Posist/Restroworks, DotPe, Zomato Base, Grab/GoBiz, Ele.me, Rappi, iFood, Delivery Club, Uzum Tezkor, Express24, Poster, iiko, R-Keeper, Glovo: no usable findings obtained. Chinese original Meituan posts and Yandex tech talks should be fetched directly.
