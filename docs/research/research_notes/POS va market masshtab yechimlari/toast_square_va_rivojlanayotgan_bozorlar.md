# Toast, Square, Shopify and emerging-market POS + marketplace platforms: structure, tenancy, order delivery, peaks (gap-filling pass, as of Nov 2026)

Scope note: ~33 tool calls. This pass adds to `pos_va_market_platformalari.md`; items already there (Toast rate limits, Wolt/Yandex/Swiggy APIs, Foodics SingleStore summary, Square Feb 2025 and Oct 2025 incidents, Meituan BestHub mirrors) are not repeated unless corrected or upgraded to a primary source. Public first-party material on how a POS and a consumer marketplace are split internally is scarce. Many searches (Toast tech blog, Meituan RMS, Zomato partner side, Uzum Tezkor, Express24, Petpooja/UrbanPiper internals, Moka-GoFood internals) returned nothing architectural; these are listed under Gaps rather than guessed.

## 1. Did anyone run POS and consumer marketplace in one app/DB at the start; when and why were they separated; what was separated first?

### Takeaway
No source found documents a POS and a consumer marketplace sharing one application and database. The best primary evidence of an early-monolith-then-split is Meituan's delivery order system (shared jar in every app, then an independent order service in April 2014 at 100k orders/day, split by priority into trade, query and async systems). Delivery Club describes a monolith-to-about-162-services migration (2020). Toast, Square and Shopify do not publish a "we split POS from online" story.

### Cited Findings
- Meituan Waimai (original post, 2016): first order Sept 2013; order logic was a shared jar bundled into each application (fast, tightly coupled). From April 2014, at "10万单/日" (100k orders/day), the order system became independent, exposed via RPC, split by priority into trade, query and async-processing systems, with order status changes published over MQ. Reason given: multiple businesses sharing one project interfered with each other. — [tech.meituan.com 2016](https://tech.meituan.com/2016/09/09/mt-waimai-order-evolution.html)
- Same post: from about 2015 volume passed 1M orders; Sept 2016 daily peak above 6M, company average near 5M; from May 2015 the order DB neared single-machine write limits, so orders were sharded, each order stored three times keyed by order ID, user ID and store ID, 100 tables per dimension spread over 4 databases, with Elasticsearch (synced by "databus") for other queries; later "千万单/日" (tens of millions/day). — [tech.meituan.com 2016](https://tech.meituan.com/2016/09/09/mt-waimai-order-evolution.html)
- Delivery Club (Habr, 18 Sept 2020): "In the beginning there was a monolith" migration; about 162 microservices; PostgreSQL standard for new services; Kafka; single Kubernetes cluster, about half of production moved by Sept 2020; load: restaurant back-office services tens to hundreds of RPS, client-facing services thousands of RPS. The page fetched showed the comment thread only, not the article body, so figures are the author's comment statements. — [Habr, Delivery Club 2020](https://habr.com/en/companies/deliveryclub/articles/519354/comments)
- Gojek/Moka (2020): Moka remained an independent POS entity integrated with the Gojek merchant ecosystem; today Moka's dashboard receives GoFood and GrabFood orders; a third-party article says GoFood integration needs an active internet connection. No public statement on whether orders flow via GoBiz, a shared order service or direct API. — [Bisnis.com 2020](https://teknologi.bisnis.com/read/20200430/266/1234785/perkuat-sistem-kasir-digital-gojek-akuisisi-moka); [Moka App Store listing](https://apps.apple.com/us/app/id955811575)
- Toast's public engineering blog front page (fetched Nov 2026, posts undated) has no post about monolith decomposition, sharding or online-ordering architecture. Visible architecture-related titles: GraphQL BFFs to Federation, Deferred ACK in Apache Camel with Pulsar, How Toast Safely Deploys Background Workers, Simplified Payments Integration. — [technology.toasttab.com](https://technology.toasttab.com/)

### Inferences
- Meituan's sequence (shared code, then order service carved out at about 100k orders/day, then sharding at about 1M) is the closest documented analogue of "separate the order/transaction path first." Nothing sourced says the catalogue, search or payments were separated first.
- No evidence either way on whether a POS-first vendor (Toast, Square) once ran consumer ordering inside the POS monolith; do not assert it.

### Gaps
- Toast and Square monolith history: not found; Toast tech blog has no such posts (checked first page of 13 only). Conference talks (InfoQ, YouTube) not reached.
- Zomato/Swiggy/Grab/GoTo/iFood original "separation" stories for POS vs marketplace: not found.
- Meituan RMS (cashier) to waimai connection posts: Chinese searches returned only App Store pages and unrelated results; original not found.

## 2. Tenancy model and database of POS-centric companies, with numbers

### Takeaway
Documented tenancy: Shopify (shop_id on every shop-owned table, pods = isolated shard with own MySQL, Redis, Memcached), Square (Vitess-sharded MySQL, about 1 TB shards, documented for Cash App/Square infrastructure), Toast (RDS Postgres, Aurora Postgres, DynamoDB per AWS, 2019, no tenancy detail), Foodics (one multi-tenant SingleStore, no tenancy detail), Grab (DynamoDB for orders, partition key order_id).

### Cited Findings
- Shopify pod = isolated Shopify instance with its own MySQL shard plus Redis and Memcached; each pod holds a subset of shops; a load balancer consults a routing table. `shop_id` on all shop-owned tables is the sharding key; shops are moved between shards online with Ghostferry (open source). Number of pods not stated; an example showed least vs most used shard about 4x apart, reduced to about 2x after rebalancing (Sept 2021). — [Shopify Engineering, Sept 2021](https://shopify.engineering/mysql-database-shard-balancing-terabyte-scale)
- Square: uses Vitess to shard MySQL; shards about 1 TB when it is time to split; post (Dec 2018) does not name merchant vs Cash product. Related post "Sharding Cash" covers Cash App scaling. — [Square Corner Dec 2018](https://developer.squareup.com/blog/shard-splits-with-consistent-snapshots/); [Sharding Cash](https://developer.squareup.com/blog/sharding-cash/) (title from search, not fetched)
- Toast (AWS Startups blog, 20 Nov 2019): relies on RDS Postgres, Aurora Postgres, DynamoDB, EC2, ECS, Route53; "over 2,500 requests per second across tens of thousands of restaurants." Says nothing on monolith vs services or sharding. — [AWS Startups Blog 2019](https://aws.amazon.com/blogs/startups/restaurant-point-of-sale-platform-toast)
- Toast job listings mention Pulsar/Camel async services and RDS/Aurora Postgres, MySQL, DynamoDB, MongoDB (weak evidence, job ads). — search summary only; the Toast tech blog posts on Camel/Pulsar are at [technology.toasttab.com](https://technology.toasttab.com/)
- Foodics (SingleStore case study, undated): earliest stack CitusDB plus MySQL (200 concurrent users, 5,000 customers, Citus outages about 3 hours up to 4 times a month); then commercial managed PostgreSQL (hard to rebalance when adding nodes, vendor dropped AWS support); then SingleStore as one multi-tenant database for all customers, target 100 concurrent queries/s (about 1% of customers), claimed 22,000+ customers, 35 markets, 5B+ orders/year, 60% query improvement. The article gives no dates, latencies or tenancy column design; this is a vendor case study. — [SingleStore: Foodics](https://www.singlestore.com/made-on/foodics/)
- Grab orders (15 Aug 2022): DynamoDB is the OLTP store for online orders (partition key `order_id`, sparse GSI on passenger id plus `created_at` for ongoing orders), MySQL RDS for OLAP partitioned monthly; DynamoDB retention 3 months, RDS partitions older than 6 months dropped. Passenger side only; says "millions of orders every day" in one place and "up to a million per month" in background (internal conflict). — [Grab Engineering 2022](https://engineering.grab.com/how-we-store-millions-orders)
- Meituan order tables: see section 1 (3 copies by order/user/store, 100 tables x 4 DBs per dimension). — [tech.meituan.com 2016](https://tech.meituan.com/2016/09/09/mt-waimai-order-evolution.html)
- iFood: its restaurant-facing order event polling was originally on Postgres, which became expensive at about 50,000 stores; replaced by an Apache Ignite based service (old one kept as fallback). — [GridGain Summit talk, Nov 2022](https://www.gridgain.com/resources/summit/what-we-learned-supporting-1m-rpm-10-ms-latency-using-apache-ignite-ifood)

### Inferences
- The shared-table-with-tenant-id model plus shard routing table (Shopify) is the best-documented pattern for "many small merchants"; nothing found for database-per-restaurant at any named company.
- Per-merchant tenant column is not sufficient on its own at scale; both Shopify and Meituan eventually moved to sharding with a router.

### Gaps
- Toast: no public per-restaurant sharding or tenancy detail; 2019 AWS post is the only primary statement.
- Square Restaurants / merchant data layout (Vitess keyspace per merchant? by merchant token?): not found.
- Petpooja, UrbanPiper, Posist/Restroworks, DotPe, Limetray, Zomato, Swiggy DB layout: not found.

## 3. How online orders reach in-store devices; documented fallback

### Takeaway
Toast documents cloud-push to devices with a hub-based local sync for outages; iFood documents that restaurants (via public API) poll an event stream with ack per event, with a fallback system; Square pushes online orders to POS/KDS and states orders may not appear in the POS during disruption and show up after recovery. Petpooja's cloud pushes online-aggregator orders down to a local Bridge Server.

### Cited Findings
- Toast: orders flow from the guest ordering page through the Toast cloud to devices; a restaurant that cannot reach the cloud receives no online orders. Ordering page stops accepting orders up to 5 minutes after Offline Mode is detected; if the auto-fire device is disconnected for about 5 minutes (or nobody logged in), online ordering pauses; after reconnect orders accepted in the window are auto-fired or sit in Pending Orders; suggested snooze 20 or 40 minutes after reconnecting; widespread outage over 1 hour shows "online ordering is unavailable" page; unapproved order after 5 minutes may trigger an email to the guest. — [Toast Support, outages](https://support.toasttab.com/en/article/Outages-and-Disruptions-Toast-Online-Ordering)
- Toast offline mode: normally devices send orders through the cloud, which pushes to all devices; offline, orders stored locally per device; optional local sync uses one hub device (wired Ethernet, pinged within last 3 minutes, one hub per restaurant, same subnet only) relaying updates; KDS on the LAN can receive POS and kiosk orders while offline; protocol (ports, messages) not documented; kiosks cannot send orders; EMV, gift cards, guest lookup unavailable; Resync buttons disabled. — [Toast platform guide, local sync](https://doc.toasttab.com/doc/platformguide/platformOfflineModeLocalSync.html); [Toast offline mode overview](https://doc.toasttab.com/doc/platformguide/platformOfflineMode.html)
- Toast Oct 20, 2025 incident: status updates attributed it to a third-party partner outage affecting multiple Toast systems; devices were said to enter Offline Mode automatically; reported login problems at shift changes. Mirror site, no formal postmortem. — [IsDown mirror](https://isdown.app/status/toast/incidents/461338-toast-status-update)
- Square: Square says new online orders from the website or ordering platforms may not appear in the POS during some disruptions and appear on devices after resolution; offline card payments are queued on the device, need reconnection within about 24 hours, per-transaction limit configurable; Square Restaurants POS is listed as supporting offline payments; Square notes it will email sellers to review offline settings from March 2026. — [Square offline payments guide](https://squareup.com/gb/en/payments/offline-payments-guide) (as summarised by search); [Square POS API offline cookbook](https://developer.squareup.com/docs/pos-api/cookbook/offline-mode)
- Square: online and mobile orders pushed to POS and KDS; developer forum says KDS cannot be configured to show only online-source orders; Orders API order.created webhook exists. — [Square food delivery software page](https://www.squareup.com/us/en/point-of-sale/restaurants/food-delivery-software); [Square dev forum](https://developer.squareup.com/forums/t/display-online-orders-to-kds-without-routing-by-category/22757)
- iFood (Nov 2022 talk): order events reach stores through a public API "via polling"; each restaurant session must get each event at least once, with ack per event per session; SNS topics feed SQS; polling interval not stated; "average throughput of 1M rpm with p99 under 10 ms", over 65M orders/month, over 500M events delivered to external agents; old Postgres-based polling system kept as fallback to the Ignite one. — [GridGain Summit 2022](https://www.gridgain.com/resources/summit/what-we-learned-supporting-1m-rpm-10-ms-latency-using-apache-ignite-ifood)
- Petpooja: POS Local uses a Bridge Server on a PC at the outlet holding bills, KOTs and sales data locally; menu, prices, settings and Zomato/Swiggy online orders sync down from Petpooja's cloud; vendor claims billing continues up to 5 days offline then syncs automatically; page does not say how aggregator orders behave offline. One POS type (Lite) has no offline mode. — [Petpooja offline POS](https://www.petpooja.com/offline-pos); [Petpooja blog](https://blog.petpooja.com/industry-business-guides/restaurant-pos-without-internet/)
- Yandex Eda vendor API: PULL model (Yandex is client, partner implements server) and a separate PUSH Vendor Management Integration API; polling intervals, timeouts, retries not on the page fetched. — [Yandex Eda vendor API](https://yandex.ru/dev/eda-vendor/doc/ru/ref/)
- Swiggy partner app: new-order ringtone and in-app view per FAQ; no public detail on transport (FCM, MQTT, WebSocket). — [Swiggy partner FAQ](https://partner.swiggy.com/PartnerFAQ)

### Inferences
- No source documents WebSocket/MQTT usage by any of these; do not claim it. Documented models are: cloud push to managed devices (Toast), polling with ack (iFood public API), webhook injection into POS (Square, Wolt from the earlier notes).
- Toast's design makes the consumer side stop accepting orders when the store is unreachable (about 5 minute detection); the consumer channel is gated by store connectivity, not the reverse.

### Gaps
- Toast and Square device transport protocol: not documented publicly.
- Polling intervals for iFood and Yandex: not found.
- Moka/GoFood, Grab merchant app, Zomato Base delivery path: not found.

## 4. Peak-load events and credited protections

### Takeaway
Shopify and Zomato publish peak numbers and prep programs; Toast gives merchants throttling controls but publishes no engineering peak story; Meituan's service split and degradation (previous notes) remain the clearest isolation example. No source shows a marketplace spike degrading in-store tills.

### Cited Findings
- Shopify BFCM 2024 peak: 284M requests/min at the edge, 80M on app servers, 12 TB/min; 10.5 trillion DB queries and 1.17 trillion DB writes over the weekend. 2025 prep: five scale tests April to October, Test 4 at 146M RPM and over 80,000 checkouts/min, final test 200M RPM at p99; tests ran on production from three GCP regions with Toxiproxy fault injection; checkout, payment, order creation and fulfilment designated "critical journeys." Post does not mention pods or POS or load shedding by name. — [Shopify Engineering, 20 Nov 2025](https://shopify.engineering/bfcm-readiness-2025)
- Shopify 2020 capacity planning: each resource area in its own GCP project to manage quotas; load tests simulate browse, cart, checkout with a mock payment gateway; no pods, POS or load shedding mentioned in that post. — [Shopify Engineering, Dec 2020](https://shopify.engineering/capacity-planning-shopify)
- Shopify tells merchants to disable collection filtering, recommendations and carrier-calculated shipping during flash sales; bot protection can cover up to 50 products. Merchant-side, not infrastructure. — [Shopify Help, flash sales](https://help.shopify.com/manual/sell-online/frenzy); [bot protection](https://help.shopify.com/manual/checkout-settings/bot-protection)
- I found no Shopify source describing how POS traffic is protected during flash sales. The 2021 load-test post reportedly covered online and POS sales together (per search summary only). — [Shopify Engineering](https://shopify.engineering/performance-testing-shopify)
- Toast merchant controls: add delay minutes to online orders, throttle by order or item count per window, catering caps per 15/30/60 min. — [Toast Support, order volume](https://support.toasttab.com/en/article/Managing-Online-Order-Volume-Throttling-Orders-1492627940253); [Toast catering](https://support.toasttab.com/en/article/Optimize-Toast-Catering-Online-Ordering)
- Zomato: NYE peaks 4,100 orders/min (2020), about 7,100 orders/min (2021, press); Zomato Engineering post "A Tale of Scale: Behind the Scenes at Zomato Tech for NYE 2023" exists (Feb 2024); the summary credits autoscaling cloud infrastructure, benchmarking across hundreds of microservices, forecast-based capacity planning, chaos testing and an in-house benchmarking tool. Original returned HTTP 503 so queues, DBs and load shedding details were not read. — [Biweekly Engineering summary](https://biweekly-engineering.beehiiv.com/p/zomatos-nye-behind-scenes-biweekly-engineering-episode-28); [original post](https://www.zomato.com/blog/a-tale-of-scale-behind-the-scenes-at-zomato-tech-for-nye-2023) (not read); [The Week 2021](https://www.theweek.in/news/biz-tech/2021/01/01/4254-orders-per-minute-zomato-at-its-peak-on-new-years-eve.html)
- Meituan order system (2016): per-dependency thread pools, timeouts and fallbacks (Hystrix), stateless service clusters across multiple data centres, tp99 150 ms trade / 40 ms query, claimed six-nines availability; 2PC for resource reservation; retries with idempotency. — [tech.meituan.com 2016](https://tech.meituan.com/2016/09/09/mt-waimai-order-evolution.html)

### Inferences
- The only sourced mechanism that maps directly onto RIZQIM's problem is bulkhead isolation per dependency (Meituan) plus per-merchant throttling controlled by the store (Toast). Whether Toast separates in-store and online traffic at worker/DB level is not public.

### Gaps
- Toast Mother's Day / Friday peak engineering numbers: not found.
- Zomato NYE post body: not read.
- Shopify POS protection under flash sale: not found.

## 5. Connectivity shaping the POS architecture (India, Indonesia, MENA, Brazil, CIS)

### Takeaway
Vendors in connectivity-poor markets sell local-first products: Petpooja's Bridge Server with up to 5 days offline (vendor claim), Toast's hub-based LAN sync in the US. Documentation of why design choices were made is missing; mostly product pages.

### Cited Findings
- Petpooja has multiple POS types: Electron, Android, POS Local each billing up to 5 days offline then syncing; POS Lite is pure cloud with no offline; one G2 comparison notes aggregator sync can lag at peak. — [Petpooja offline POS](https://www.petpooja.com/offline-pos); [Petpooja blog](https://blog.petpooja.com/industry-business-guides/restaurant-pos-without-internet/); [G2 discussion](https://www.g2.com/discussions/restroworks-cloud-kitchen-management-vs-petpooja-poss-which-is-better-for-consolidating-delivery-app-orders-in-a-cloud-kitchen)
- Foodics: Citus outages about 3 hours up to 4 times a month cited as reason to move databases (reliability of the central DB, not the till link). — [SingleStore: Foodics](https://www.singlestore.com/made-on/foodics/)
- Moka: some features including GoFood integration require internet. — [Bisnis.com 2020](https://teknologi.bisnis.com/read/20200430/266/1234785/perkuat-sistem-kasir-digital-gojek-akuisisi-moka) (via search summary)
- Yandex Eda Kyrgyzstan: orders arrive on the restaurant's till; iiko via iiko.biz, R-Keeper Delivery, custom via Yandex API contract. — [Yandex Eda KG](https://yandex.ru/project/eda/kg/rest_faq_integration)

### Inferences
- In poorly connected markets the till keeps a local database and the cloud pushes menu and orders down; online orders are inherently unavailable while offline (Toast says so explicitly), so the marketplace should stop accepting for that store after a short detection window.

### Gaps
- No engineering post found on connectivity design for Posist/Restroworks, DotPe, Limetray, UrbanPiper, Zomato Base, GoBiz, Grab Merchant, iFood tools, Delivery Club.

## 6. Scale numbers with dates

### Takeaway
Numbers exist but rarely tie to architecture. Most useful: Toast 2,500 req/s (2019), iFood 1M rpm polling and 65M orders/month (2022), Meituan stages 100k to 1M to 10M orders/day, Shopify 284M edge RPM (2024).

### Cited Findings
- Toast: over 2,500 requests/s across tens of thousands of restaurants (Nov 2019). — [AWS Startups Blog](https://aws.amazon.com/blogs/startups/restaurant-point-of-sale-platform-toast)
- Meituan waimai: 100k orders/day (Apr 2014), over 1M (about 2015), over 6M peak day (Sept 2016), tens of millions/day (later claim in same post). — [tech.meituan.com 2016](https://tech.meituan.com/2016/09/09/mt-waimai-order-evolution.html)
- iFood: 1M rpm, p99 under 10 ms, 65M+ orders/month (Nov 2022). Later: 180M orders in Nov 2025, average 6M/day, 250k/hour at best (press, my arithmetic about 69 orders/s average is not from source). — [GridGain 2022](https://www.gridgain.com/resources/summit/what-we-learned-supporting-1m-rpm-10-ms-latency-using-apache-ignite-ifood); [Forbes Brasil BrandVoice, Dec 2025](https://forbes.com.br/forbeslife/2025/12/brandvoice-ifood-com-gourmet-e-turbo-ifood-consolida-a-era-da-personalizacao-em-escala-e-bate-recorde-de-pedidos/)
- Delivery Club: restaurant back-office tens to hundreds of RPS, client-facing thousands of RPS (Sept 2020). — [Habr 2020](https://habr.com/en/companies/deliveryclub/articles/519354/comments)
- Shopify: 284M edge RPM, 80M app RPM (BFCM 2024). — [Shopify Engineering 2025](https://shopify.engineering/bfcm-readiness-2025)
- Zomato: 4,100 orders/min (NYE 2020), 7,100 orders/min (NYE 2021, press). — [The Week](https://www.theweek.in/news/biz-tech/2021/01/01/4254-orders-per-minute-zomato-at-its-peak-on-new-years-eve.html); [Deccan Herald](https://www.deccanherald.com/amp/story/business%2Fzomato-swiggy-clock-over-7000-orders-per-minute-on-new-years-eve-log-personal-bests-1066866.html)
- UrbanPiper: founder interview says about half a million orders/day and up to 10M API calls/day; its own site says 2,500+ brands, 11M orders/month (roughly 370k/day), conflicting; a partner case study says 45,000+ locations in 30+ countries. — [Masai School interview](https://www.masaischool.com/blog/scaling-tech-with-anirban-founder-of-urban-piper/); [UrbanPiper about](https://urbanpiper.com/about-us); [Parallel HQ](https://parallelhq.com/work/urbanpiper-parallel)
- Uzum Tezkor: about 25 cities and 2,600+ partners per one secondary source (year unclear); Uzum job ads list PostgreSQL, Redis, Kafka, RabbitMQ, Go. No architecture published. — [Habr/Uzum search results](https://habr.com/ru/companies/uzum/articles/page2); [spot.uz](https://www.spot.uz/ru/2025/05/16/uzum-tezkor/)

### Inferences
- Order rate in orders/s is small for most (Zomato peak about 120 orders/s, iFood average well under 100/s); the RPS the API sees is dominated by polling, browsing and menu reads, not order creation. This is arithmetic on cited figures.

### Gaps
- Toast and Square current RPS, orders/day: not found. Meituan RMS numbers: not found.

## 7. Outages crossing consumer and in-store sides

### Takeaway
Documented cross-side effects are connectivity or shared-dependency failures, not consumer load taking down tills. No postmortem found where marketplace load degraded in-store POS.

### Cited Findings
- Toast Oct 20, 2025: third-party partner outage affected multiple Toast systems; devices told to run Offline Mode; users reported login problems at shift change. No root-cause write-up. — [IsDown mirror](https://isdown.app/status/toast/incidents/461338-toast-status-update)
- Toast community report: checkout error after payment for Toast Online Ordering and Toast Local for about a week; poster says engineering resolved root cause, no details (user account, undated in summary). — [Toast Community](https://community.toasttab.com/t5/guest-experience/online-ordering-quot-error-placing-order-quot/m-p/13894)
- Square Feb 2025 and Oct 2025 incidents are in the earlier notes. — [Square 2025](https://developer.squareup.com/blog/an-analysis-of-the-square-and-cash-app-outage/)

### Inferences
- Toast designs online ordering to auto-pause when the store connection or auto-fire device is lost, so an in-store failure is contained on the consumer side by a switch, not the reverse.

### Gaps
- Toast engineering postmortems: none public found. Meituan, Zomato, Grab, Yandex, iFood cross-side incident reports: not found.

## Not covered (no usable result in budget)
Square Orders API internals and Square for Restaurants architecture beyond help pages, Block merchant sharding, Toast conference talks, Shopify POS-specific protection, Posist/Restroworks, DotPe, Limetray, Ele.me/Koubei, Rappi, Glovo, Wolt merchant tooling (beyond earlier notes), Lightspeed Order Anywhere, ChowNow, Flipdish, Owner.com, Express24, Delivery Club vendor API, Meituan RMS.
