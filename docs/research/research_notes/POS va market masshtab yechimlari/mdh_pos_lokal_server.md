# CIS / Central Asia restaurant POS: offline and local-server architecture (iiko/Syrve, R-Keeper, Quick Resto, Poster, Jowi, BILLZ, Saby Presto, Tillypad, Dooglys and others), as of Nov 2026

Method note: gap-filling pass on top of pos_offline_arxitekturalari.md (Toast, Square, Lightspeed, Loyverse, Poster EN KB, Petpooja are NOT repeated except Poster). About 36 tool calls. Official iiko help is mostly large PDFs (the iikoOffice 8.0 PDF exceeded the fetch limit, so it was not read; iiko claims below come from search-engine summaries of those PDFs plus integrator pages and are flagged). docs.rkeeper.ru pages were fetched directly. jowi.club, billz.io, ru.iiko.help HTML pages, help.quickresto.ru and Syrve docs could not be fetched or returned nothing (jowi.club failed to resolve in this sandbox). Vendor claims are labelled "vendor". Integrator/reseller/competitor/review claims are labelled "third-party". Years are given; pre-2022 items are marked possibly outdated. Nothing is guessed where a source is missing.

## 1. iiko / Syrve (iikoRMS, iikoChain, iikoCloud): where is the source of truth, what is installed, what happens offline

### Takeaway
Classic iiko is a server-centric system (iikoServer + iikoOffice + iikoFront, each till has a local "cash server" that buffers when the server is unreachable). iiko has now retired the local licence model: new local licence sales stopped 1 Jul 2023 and support ended 1 Jul 2025 (per one integrator), and iikoCloud (SaaS subscription) is the only sold form. Strong detail on iikoFront offline behaviour is thin in public HTML docs; the best sources are old PDF manuals and integrator descriptions. No source found describing Syrve (international brand) architecture.

### Cited Findings
- Local (LifeTime / iikoClassic) model: system ran on a physical server in the venue, owner maintained it and installed updates manually; iikoCloud is a cloud till on SaaS subscription with data on the developer's servers and automatic updates (third-party: integrator POSBazar, 2025/2026, commercial interest in migrations). — [POSBazar: iiko LifeTime disabled](https://posbazar.ru/blog/iiko-lifetime-otklyuchena-chem-grozit-rabota-na-staroj-liczenzii-i-kak-perejti-na-oblachnuyu-kassu/)
- Timeline of the local version per the same integrator: 1 Jul 2023 sales of local licences to new venues stopped; 1 Jul 2024 no extra licences for existing customers; 1 Jul 2025 end of support/updates; 1 Jan 2026 cloud services for local licences shut down (iikoTransport, iiko.biz API, iikoCard APIs), so a local-licence till "no longer accepts orders" from Yandex Eda, SberMarket, own sites/apps. These dates are the integrator's statement; not checked against an iiko primary notice. — [POSBazar](https://posbazar.ru/blog/iiko-lifetime-otklyuchena-chem-grozit-rabota-na-staroj-liczenzii-i-kak-perejti-na-oblachnuyu-kassu/); corroborated loosely by "с 01.01.2026 отключена поддержка доставок в старых версиях" in a search summary of the same site (same vendor, not independent).
- iikoCloud tariffs: Nano (coffee points, food trucks), Start (small cafes), Pro (restaurants, chains), Enterprise (large chains); rental "from 1490 RUB/month", iikoClassic licence "about 65,000 RUB one-off" (third-party reseller Denvic, undated; prices vary). It says you rent a virtual server and can export the DB from the cloud to your computer and continue locally. — [Denvic: iikoCloud](https://denvic.ru/blog/obshchepit/arenda-iiko-v-chem-preimushchestva-iikocloud/)
- Competitor view (Poster/PickTech-style comparison, 2026, third-party with interest): iiko described as "Единая облачная база данных", "Облако (iikoCloud). Основной фокус — SaaS", internet dependence "Средняя/Высокая. Для управления и отчетов нужен стабильный интернет"; plans "Корнер 2,500 / Кафе 5,000 RUB per venue; Профессиональный 8,600 / Корпоративный 10,600 RUB per till". — [PickTech: iiko vs r-keeper 2026](https://picktech.ru/blog/a-vs-b/iiko-vs-r-keeper-sistema-avtomatizatsii-dlya-restorana-2026/)
- Cash server / autonomy (vendor manual, via search summary of iikoOffice PDF, version 7.x/8.0, years about 2019-2023): the cash server stores local data, talks to the server and "provides autonomous operation of the terminal when there is no connection with the server". Sync modes: with no main till (главная касса) assigned there is no sync and each terminal has its own copy of data; if the main till is unavailable all other terminals are blocked; "for fast food, 'no sync' mode is recommended". Over internet rather than LAN a timeout of 20-30 s is recommended. NOT verified by reading the PDF (too large). — [iikoOffice 8.0 PDF](https://ru.iiko.help/resources/Storage/archive/PDF/RU_iikoOffice_8.0.pdf); [iikoFront 7.8 PDF](https://ru.iiko.help/resources/Storage/archive/PDF/RU_iikoFront_7.8.pdf)
- Peer sync (third-party unofficial wiki, old): with a main till all subordinate terminals open a TCP connection to it and sync order data; unclosed orders go to the server as events. Possibly outdated. — [razgovorov wiki: iiko](https://wiki.razgovorov.ru/index.php/Iiko)
- Integrator description: when the connection with the server is lost the main terminal goes autonomous and keeps ringing sales, discounts and price lists keep working, accumulated data is uploaded when the link returns (third-party, argorest.ru bar case, page returned 503 on my fetch, so taken from search summary). — [argorest: Bar Koba & iiko](http://argorest.ru/posts/bar-koba--iiko-vo-vladimire)
- Sales accumulate in a "send queue" folder on the till; if iikoOffice shows no past shifts the tills are offline; licences are held by the server for a while after a client closes (FAQ, undated, third-party). — [lostguide FAQ](https://lostguide.ucoz.ru/faq)
- Forum fixes for "Front does not see server": wrong server address/port in `%appdata%\iiko\CashServer\config.xml` (port changed 8081 to 8083 after licence reinstall); typical LAN problems (third-party forum, undated). — [Carbis forum thread](https://carbis.ru/forum/showthread.php/10209-Front-(%D1%80%D0%B0%D0%B1%D0%BE%D1%87%D0%B8%D0%B5-%D1%81%D1%82%D0%B0%D0%BD%D1%86%D0%B8%D0%B8)-%D0%BD%D0%B5-%D0%B2%D0%B8%D0%B4%D1%8F%D1%82-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80%D0%B0-Iiko)
- iikoCard has its own "POS server" and an overdraft limit usable offline for 24 hours while the till has no link to the main server (vendor manual, iikoCard 5, older). — [iikoCard 5 PDF](https://rapid.iiko.ru/userguide/iikoCard5/UG_iikoCard.pdf)
- Cloud front offline (third-party integrator): the cloud till "keeps a local copy of the information", staff keep taking orders and printing receipts without internet, and data uploads automatically on reconnect. No duration or disabled-feature list was found. — [POSBazar](https://posbazar.ru/blog/iiko-lifetime-otklyuchena-chem-grozit-rabota-na-staroj-liczenzii-i-kak-perejti-na-oblachnuyu-kassu/)
- Chain scale: marketing claim that "hundreds of remote points" can be managed centrally; iikoEnterprise for large franchises (third-party). — [POSBazar: iikoServer](https://posbazar.ru/blog/iikoserver-avtomatizacziya-restoranov-i-server-iiko-v-restorane/)

### Delivery / online orders reach the till
- iikoTransport / iiko Cloud API is the delivery channel; for new integrations the iiko Cloud API (compatible with iikoTransport) replaced iiko.biz (FoodSoul notice, 2022). — [FoodSoul notice 2022](https://foodsoul.pro/news/164-discontinuation-of-integrations-with-iikobiz-transition-to-iikotransport)
- Yandex Delivery integration requires iiko 7.7.7+, tariff iikoCloud or Life-time with the iikoDelivery module; the terminal where couriers are assigned must run the iikoTransport service and the plugin `Plugin.Front.DeliveryExternalCourierService`; setup in iikoWeb (vendor-adjacent: Yandex docs). — [Yandex Logistics: integration with iiko](https://yandex.ru/dev/logistics/api/integration/integration-iiko.html)
- iikoFront API V3 exposes `IDelivery` (plugin model), i.e. delivery orders can be driven through plugins on the front (vendor API docs, 2015-era page, possibly outdated). — [iikoFront API V3 IDelivery](https://rapid.iiko.ru/api/4.0.2015.0/Resto.Front.Api.V3.Documentation/html/5b5fa83c-e6f7-1bf7-791c-06482d2bbde1.htm)

### Inferences
- Even for the "server" product, the till is designed to survive server loss (cash server buffering, send queue). The weak spot is multi-till consistency: with a main till, loss of the main till blocks other tills; with no sync each till is an island. That is the same trade-off as RIZQIM's options B and C.
- iiko's own retirement of the local licence (on integrator evidence) is the strongest signal that a leading server-based vendor chose cloud for new sales; the stated reason from iiko itself was not found.

### Gaps
- iiko primary documentation on iikoFront offline limits (which operations are disabled, time limits, behaviour when the cash server dies): not retrieved. The 8.0 PDF is too large for the fetch tool; read it directly.
- Database of iikoServer (reported as MS SQL in some sources, not verified here): not found in sources fetched. Hardware requirements: not found.
- iikoChain replication schedule and central-versus-local menu conflict rules: not found.
- Syrve (international brand), "Syrve Go", Syrve cloud/on-prem split: search returned nothing relevant; not found.
- iiko's own explanation of why it moved to cloud and its stated trade-offs: not found.

## 2. R-Keeper (UCS / now docs under restera): classic r_keeper 7, backup server, cloud and Lite editions, White Server

### Takeaway
Classic r_keeper 7 is a local server product: a cash server with a group of cash stations, reference server for master data, manager station. Since 7.6.0 a backup cash server running on a till softens main-server loss, with stated limits. UCS has sold cloud variants since 2016 (r_k CLOUD) but even there the cash server stays local; r_keeper Lite is a separate, small-business cloud till with offline mode.

### Cited Findings
- System composition: a group of cash stations connects to a cash server; cash server connects to a reference server that the manager station can edit; transaction data (checks, orders) in `work.udb`, reference data in `rk7.udb` and `check.udb` (vendor docs). If the reference server is off or sync disabled, the cash server uses data from the last sync session. The page does not name the DBMS and does not describe a head-office exchange. — [r_keeper 7: Состав системы](https://docs.rkeeper.ru/rk7/latest/ru/sostav-sistemy-4037443.html)
- Backup cash server (vendor docs, current "latest"): introduced in 7.6.0; runs on the same computer as the till; used when the main server is unreachable (network, hardware, software). If a till cannot reach the main server within `ReserveServerTimeout` and the backup is available, the till asks whether to switch. Reference data comes only from the main server; sales go only back to the main server. Setup: copy of the main server marked Active, assigned in the till's "Резервный кассовый сервер" property; user needs the right "Переключение на резервный сервер"; backup server is not licensed. An order being created at the moment of loss is moved into the backup DB. — [Настройка резервных кассовых серверов](https://docs.rkeeper.ru/rk7/latest/ru/nastrojka-rezervnyh-kassovyh-serverov-4056797.html)
- Same page limits: card payments, loyalty/discount cards, KDS and online sync "may not be supported" (functions requiring a network); a till cannot be connected to main and backup at once; shift files created on the backup cannot be processed until the main server passes them; switching back needs closing the general shift, then till reboot. — same URL.
- Practitioner reports (third-party, older forums, undated/possibly outdated): tills with three stations work in "autonomous" mode but show "no link"; on server failure shift closing and reports do not work and new dishes do not reach stations; for fully independent tills one forum recommends a cash server on each till. — [Carbis forum](https://carbis.ru/forum/showthread.php/7344-%D0%9A%D0%B0%D1%81%D1%81%D0%B0-r-keeper-7-%D0%BD%D0%B5-%D0%B2%D0%B8%D0%B4%D0%B8%D1%82-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80); [F-Trade forum: cash server variants](https://forum.f-trade.ru/index.php?topic=14.0%3Bwap2) (503 on my fetch; title only from search snippet)
- r_k CLOUD (vendor news, 19 Jul 2016, possibly outdated): budget cloud version; cashier r_keeper 7, manager edition, StoreHouse 4 and a CRM module via the cloud, servers in Rambler data centre; "кассовый сервер – локальный, он не зависит от подключения"; if the link to the licence server breaks, the system can run autonomously for 72 hours; rental 3,500 RUB/month at that time. — [UCS: Приветствуем r_k CLOUD](https://ucs-soft.ru/news/privetstvuem-rk-cloud-byudzhetnuyu-versiyu-r-keeper-dlya-uchastnikov-segmenta-horeca/)
- r_keeper Lite: "облачная касса" Android app (vendor store listing); analytics from anywhere, till can work offline, data saved to cloud after reconnect; targeted at coffee shops, burger points, food trucks; functions listed: menus, floor plans, 1C and EGAIS integration, warehouse module. — [Google Play: r_keeper Lite](https://play.google.com/store/apps/details?id=ru.ucs.rk_lite_pos&hl=en_US)
- Current editions per comparison articles (third-party 2026): Cloud Start/Pro/Max from about 4,085 / 6,170 / 8,500 RUB per month, LifeTime perpetual from about 107,000 RUB with annual support of about 15-25% (PickTech); "can be deployed in the cloud or locally" (Evotor/Habr 2026). — [PickTech 2026](https://picktech.ru/blog/a-vs-b/iiko-vs-r-keeper-sistema-avtomatizatsii-dlya-restorana-2026/); [Habr (Evotor blog) top-15, Oct 2026](https://habr.com/ru/companies/habr_evotor/articles/1097160/)
- Competitor framing (Poster comparison page): r_keeper "Гибрид. Сильны локальные серверы" and "Если упал интернет — ресторан работает автономно" (competitor marketing). — [PickTech 2026](https://picktech.ru/blog/a-vs-b/iiko-vs-r-keeper-sistema-avtomatizatsii-dlya-restorana-2026/)
- White Server (vendor docs): cloud layer; "White Server is the entry point to the restaurant via a public API". A White Server Agent (WSA) installed in the restaurant receives tasks and forwards them to r_keeper 7 through its XML Interface (or StoreHouse via OLE); response goes back through White Server to the caller. Requires r_keeper 7.6.0+. The page does not say which side opens the connection and does not say what happens when the restaurant is offline. — [White Server: product description](https://docs.restera.com/ws/product-description-23660771.html)
- White Server API v2 is JSON-RPC over HTTP POST with token header; events to aggregators via HTTPS callbacks; integration needs several paid licences (3 to 4 types), external order numbers (Yandex Eda, SberMarket) need r_keeper 7.7+ and WSA 2.8.4.2+ (search-summary of vendor docs). — [White Server API v2](https://docs.rkeeper.com/ws/white-server-api-v2-23660945.html); [Yandex Logistics: integration with r_keeper](https://yandex.ru/dev/logistics/api/integration/integration-rkeeper.html)

### Inferences
- R-Keeper's published offline design is "keep the local cash server alive" plus a cold standby on a till. Notable cost: backup mode is degraded (no cards/KDS/loyalty as per docs) and recovery needs a shift close and reboot; this is the real price of a store server with a standby.
- Delivery to a local r_keeper needs an agent in the store (WSA) because the store is the authority; the cloud cannot write to it directly. For a cloud-first RIZQIM the equivalent problem does not exist.

### Gaps
- DBMS of r_keeper 7 cash server (the file names suggest a proprietary/embedded store; not stated), and hardware requirements: not stated in fetched pages.
- Reference-server to head office replication schedule/conflict handling: not found.
- Official r_keeper statement of why cloud/Lite were added, and r_keeper Cloud (current) offline limits: not found (only the 2016 launch note).
- Behaviour of White Server when the store is offline (are orders queued?): not stated.

## 3. Cloud-native vendors: Quick Resto, Poster, Saby Presto

### Takeaway
All three are cloud with a local cache on the till; offline is marketed as "keeps working and syncs later". Detailed limits are published only by Poster (clearly) and Saby (partly). Multi-till behaviour offline is the weak point and is contradicted between vendor and user claims (Quick Resto, Poster).

### Cited Findings
- Quick Resto (vendor marketing pages): "Да, Quick Resto продолжает работать офлайн. Все данные сохраняются на устройстве и отправляются в облако при восстановлении связи"; food-truck page: till records sales and prints receipts, data goes to the OFD and FNS after internet returns. No dedicated help article listing offline limits found. — [Quick Resto: автоматизация кафе](https://quickresto.ru/automation/cafe); [Quick Resto: фудтрак](https://quickresto.ru/automation/avtomatizaciya-fudtraka/)
- Quick Resto marking limits (vendor help, current): without internet on the till, sale of marked goods is blocked in permissive mode; offline check possible only when the marking system is technically unavailable, seller decides. A Quick Resto blog (about 2 years old) says goods may be added without code check but connection must be restored within 72 hours; sources conflict, rules changed 1 Mar 2025. Russian regulation, not Uzbek. — [Quick Resto: Разрешительный режим](https://quickresto.ru/support/markirovka_i_podaktsiza/razreshitelnyy_rezhim/); [Quick Resto blog](https://blog.quickresto.ru/razreshitelnyy-rezhim-v-obshchepite)
- Quick Resto reviews, hf.ru, 2026 (third-party): five reviews (May to Jul 2026) say terminals go offline and sync later ("система сама переходит в оффлайн-режим", "чек всё равно пробивается"); a 1 May 2026 review says "Без интернета система превращается в кирпич" with no sync between terminals; 11 Aug 2026 review: cloud slow/freezes on weak internet; 29 Apr 2026 review: since 18 Apr orders from Delivery Club stop arriving in the system. Only the first of 132 reviews shown. — [hf.ru: Quick Resto reviews](https://hf.ru/services/quick_resto/reviews)
- Poster (vendor KB, English, same as prior notes): offline sales kept in app cache; sync indicator green/yellow/grey; online orders and sync between tills, management console and Poster Boss pause when offline; printing offline only if printer and till share the same Wi-Fi; if not logged in when servers fail you cannot log in; shift can be closed offline or left open to next day; do not clear cache or close app offline. — [Poster KB: servers down](https://knowledge-base.joinposter.com/en/what-to-do-if-poster-pos-servers-are-down); [Poster KB: offline](https://knowledge-base.joinposter.com/en/does-poster-work-offline)
- Poster marketing claim (Russian page): "Несколько касс в зале синхронизируются между собой и работают даже без интернета". This is stronger than the English KB, which says grey status means orders are not uploaded to other registers. Conflict between marketing and KB; no technical detail found. — [Poster: alternative to iiko](https://joinposter.com/alternative/iiko)
- Saby Presto Front Offline (vendor help, undated, current): desktop app that works with unstable internet; orders, payment and delivery work without internet; editing menu/sales points/loyalty needs connection; sales reports unavailable offline except the discounts report; Front and Front Offline do not sync order data with each other; with several fiscal registers "network mode" is recommended where one computer acts as a server for several workstations and data go to all workstations regardless of internet. — [Saby: Presto Front Offline](https://saby.ru/help/presto/sistema/sbis_presto/)
- Saby sync: unsynced data warning at shift close when no internet; data stay in the app and sync automatically on reconnect; no deadline stated. — [Saby: unsynced data](https://saby.ru/help/presto/sistema/saby_presto/synchroniz_error/)
- Saby Presto Waiter (Android/iOS), Prod Monitor and Client Monitor can be switched to offline once Presto offline runs on the terminal; after replacing the till PC, data exchange with Waiter and monitors must be reconfigured (search summary of vendor help). Summer 2026 news: delivery added to the offline app. — [Saby: Presto Waiter](https://saby.ru/help/presto/sistema/mobile); [Saby: Presto offline apps](https://saby.ru/help/presto/app)

### Inferences
- Saby's "till PC acts as server for other workstations" mode is a documented vendor example of option B (one till as local hub) in the CIS market, and it ships beside a cloud product.
- User reviews of Quick Resto and Poster show that "works offline" for a single till is credible but multi-till consistency offline is where complaints and vendor-versus-KB contradictions cluster.

### Gaps
- Why Poster and Quick Resto chose cloud, and their own answer to the "internet is down" objection beyond one-line marketing: not found in sources retrieved (no founder interview or blog fetched).
- Quick Resto: local DB, multi-terminal LAN sync behaviour, duration limit: not public in what I found.
- Poster Russian-language help centre pages not fetched.

## 4. Jowi (Uzbek-origin hybrid)

### Takeaway
Jowi is described as hybrid: a local module installed in the restaurant syncing to cloud servers; the local server role is confirmed by its docs (terminals connect to a DB-server IP). Offline claims come from third-party reviews and are conditional.

### Cited Findings
- Habr 2015 overview (possibly outdated; updated 29 Jun 2021 note on page): "модуль Jowi устанавливается в ресторане, а затем данные синхронизируются на удаленные серверы", preserving operation when the internet falls (third-party). — [Habr: Автоматизация ресторана: 24 инструмента](https://habr.com/ru/articles/258679/)
- Jowi documentation: Waiter local part must be installed; the installer asks for the IP of the database server, "necessary when installing additional terminals which will connect to the server at this address"; minimal channel not lower than 512 kb/s (vendor knowledge base, undated). — [Jowi KB: Установка локальной части Waiter](https://jowiclub.userecho.ru/knowledge-bases/2/articles/6-ustanovka-lokalnoj-chasti-waiter)
- Vendor site claim relayed by search result: local module runs on any POS terminal, Windows XP or newer, screen 1024x768 minimum (jowi.club/devices; fetch failed, taken from search summary). — [Jowi: devices](https://www.jowi.club/devices)
- Habr (Evotor blog) Oct 2026: Jowi offline "depends on the installed configuration"; basic set free, financial accounting 0.2% of revenue, staff app "$3 per device" (competing vendor's list). — [Habr top-15 2026](https://habr.com/ru/companies/habr_evotor/articles/1097160/)

### Inferences
- Jowi's architecture (local DB server on a store PC + cloud sync, with extra terminals pointing at it by IP) is the CIS "option B/C hybrid" that is closest to what RIZQIM is considering, and it is positioned as low cost (software free).

### Gaps
- DBMS, sync interval, conflict rules, kitchen module offline: not found. jowi.club could not be fetched in this sandbox.

## 5. BILLZ, Tillypad, Dooglys, others

### Takeaway
BILLZ and Dooglys have no retrievable architecture documentation; Tillypad is described only as locally installed.

### Cited Findings
- BILLZ: co-founder describes it as a "cloud-based platform for retail stores" accessed from PC, tablet or phone (retail, not restaurant); no offline documentation found. — [Astana Hub interview (2025)](https://astanahub.com/en/article/vlozhili-200-tysiach-lichnykh-sredstv-kak-startap-iz-uzbekistana-poluchil-12-mln-ot-krupnogo-banka1761657047)
- Tillypad: third-party description as a server system, installed locally, independent of internet; DBMS and offline behaviour not documented in results. — [toolfox: Tillypad](https://toolfox.ru/services/s/tillypad); [Tillypad help 10.4](https://my.tillypad.ru/help103/english/Main/10.4/tpad.html?index.html)
- Dooglys: only third-party claims "works without internet" (Habr Evotor blog 2026 and a review site); no detail. — [Habr top-15 2026](https://habr.com/ru/companies/habr_evotor/articles/1097160/); [marketing-tech: Dooglys](https://marketing-tech.ru/online-services/dooglys/)
- Uzbek fiscal context: online-KKM "Восстановить чек" lets users correct receipts sent to the fiscal data operator without full detail (Uzbek accounting site); relevant to offline fiscal queueing but not vendor-specific. — [buxgalter.uz](https://buxgalter.uz/nalichnye_raschety/chto_mojet_novyy_funkcional_sistemy_onlayn-kkm_i_virtualnyh_kass_vosstanovit_chek)

### Gaps
- Not found at all: BILLZ offline mode; Smartup, Presto (non-Saby), Restik, YUMA POS, MoySklad Kassa, Kontur.Market, Tillypad offline and DB; Dooglys details. Not public in results does not mean absent.

## 6. Fiscal registers, and how data reaches cloud/chain, across vendors

### Takeaway
Little fiscal-attachment detail was retrievable. Where found, the fiscal unit sits on the till or LAN next to the till, not in the vendor cloud.

### Cited Findings
- Quick Resto (via third-party integrator, from the prior notes): iPad reaches the Aqsi fiscal register by local IP on the same router. — [vc.ru Aqsi](https://vc.ru/id1103752/2002837-kassa-aqsi-za-0-rubley)
- Quick Resto food-truck page: offline sales are sent to OFD and FNS once internet returns (store-and-forward of fiscal data; Russian regime). — [Quick Resto: фудтрак](https://quickresto.ru/automation/avtomatizaciya-fudtraka/)
- Saby: with several fiscal registers use network mode where one PC serves the workstations. — [Saby: Presto Front Offline](https://saby.ru/help/presto/sistema/sbis_presto/)

### Gaps
- iiko / r_keeper / Jowi fiscal attachment topology and Uzbek-specific (OFD, virtual KKM) behaviour: not found.
- Replication schedule to head office for all vendors: not found as a number anywhere; only qualitative "when the link returns".

## 7. What restaurateurs and integrators report (all third-party)

### Takeaway
Server-based setups are reported to cost hardware and maintenance and to fail on LAN/port/licence problems; cloud setups are reported fine for a single till offline but slow or "brick" on weak internet and with no multi-till sync. Much of the pro-cloud material comes from integrators selling migration.

### Cited Findings
- Local iiko owner "maintained the server and installed updates manually" (POSBazar, integrator with migration interest). — [POSBazar](https://posbazar.ru/blog/iiko-lifetime-otklyuchena-chem-grozit-rabota-na-staroj-liczenzii-i-kak-perejti-na-oblachnuyu-kassu/)
- r_keeper perpetual licence needs annual support payment of about 15-25% (competitor-compiled). — [PickTech 2026](https://picktech.ru/blog/a-vs-b/iiko-vs-r-keeper-sistema-avtomatizatsii-dlya-restorana-2026/)
- Quick Resto user reviews, 2026, mixed as listed in section 3. — [hf.ru](https://hf.ru/services/quick_resto/reviews)
- iiko "Front does not see server" and licence-seat issues on forums (undated). — [Carbis forum](https://carbis.ru/forum/showthread.php/10209-Front-(%D1%80%D0%B0%D0%B1%D0%BE%D1%87%D0%B8%D0%B5-%D1%81%D1%82%D0%B0%D0%BD%D1%86%D0%B8%D0%B8)-%D0%BD%D0%B5-%D0%B2%D0%B8%D0%B4%D1%8F%D1%82-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80%D0%B0-Iiko); [lostguide FAQ](https://lostguide.ucoz.ru/faq)

### Gaps
- No habr/vc.ru/restoranoff engineering postmortems on data loss from server death or sync failure were retrieved (habr search returned no relevant article); no quantified maintenance cost of a store server in Uzbekistan.
- No vendor explanation of "why we went cloud" for iiko, R-Keeper, Poster or Quick Resto.

## Overall inferences for RIZQIM (labelled as inference, not findings)
- Every vendor that has a local server also ships a degraded standby or buffer (iiko cash server queue and main till; r_keeper backup cash server; Jowi local module; Saby network-mode PC). None offers full-function multi-till operation when the hub dies; r_keeper explicitly drops cards/KDS/loyalty in backup mode and requires shift close plus reboot to return.
- The market's two most visible movements are in the cloud direction: iiko ended local licence sales (per integrator) and r_keeper offers Cloud/Lite beside LifeTime; yet r_keeper's 2016 cloud still kept a local cash server, and Saby's offline PC-as-server mode is a documented small hub. That pattern supports "cloud-first plus one till as local hub", not a separate server box.
- The most credible hub precedent in CIS is Saby Presto Front Offline network mode; the most credible server-with-standby precedent is r_keeper 7.6.0 backup cash server.
