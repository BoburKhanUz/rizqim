# POS vendors' offline / local-mode architecture (as of Nov 2026)

Method note: research hit a tool-call budget. Vendor help-centre pages were fetched directly for Toast, Lightspeed K-Series, Square, Poster, Loyverse, Petpooja. Other items come from search snippets only and are labelled "(search snippet)". Nothing on iiko, R-Keeper, Quick Resto, Jowi, BILLZ, Syrve, Clover (first-party), Foodics, Moka, Revel (current docs), Shopify engineering, or any vendor engineering-blog/postmortem was obtained; see Gaps. No internal architecture is guessed.

## 1. Where is the source of truth per vendor (cloud / device / in-store server-hub), and what local DB/sync tech is named?

### Takeaway
Vendors split into three families. (1) Cloud-first with per-device offline cache and no peer sync: Square, Shopify, Loyverse, Poster, Odoo POS, Petpooja Android/Electron/Lite. (2) Cloud-first plus a designated in-store "hub" device that relays between tills/KDS: Toast (auto-elected hub), Lightspeed K-Series (multi-register sync over LAN). (3) In-store server as local authority: TouchBistro Pro Server, Petpooja POS Local (Bridge Server), legacy on-prem (Aloha/Micros). No vendor surveyed publicly names its sync DB technology (SQLite/Realm/CouchDB etc.) in the pages found, except Odoo (IndexedDB, via a forum answer).

### Cited Findings
- Toast: "offline mode with local sync" uses a local hub device which "connects all devices on the restaurant's local network" and "relays updates between devices and to the Toast cloud". Toast auto-selects the hub; only one hub per restaurant; it only shares with devices on its own subnet. (vendor doc) — [Toast: Offline mode with local sync](https://doc.toasttab.com/doc/platformguide/platformOfflineModeLocalSync.html)
- Toast hub eligibility: not a handheld or Elo V1, hardwired Ethernet, on the restaurant LAN, active (Toast ping received within last 3 minutes). Toast support can reassign the hub. — [Toast: Offline mode with local sync](https://doc.toasttab.com/doc/platformguide/platformOfflineModeLocalSync.html)
- Toast's older "offline mode" (no hub) means devices do not sync with each other; each employee should use one device. A search-summary of Toast docs says Toast plans to deprecate the older method in favour of local sync (not independently verified by me; confirm in Toast docs). — [Toast: Offline payments overview](https://doc.toasttab.com/doc/platformguide/platformOfflinePaymentsOverview.html); [Toast: Offline mode](https://doc.toasttab.com/doc/platformguide/platformOfflineMode.html)
- Toast: multiple subnets "greatly reduces the likelihood" that offline KDS works (search-summary of Toast KDS offline doc). — [Toast: KDS devices offline](https://doc.toasttab.com/doc/platformguide/platformOfflineKDSDevices.html)
- Toast: if the local network itself is down, devices cannot pass on-premise orders and off-premise orders are not received (search-summary). — [Toast support: offline during local network outage](https://support.toasttab.com/en/article/Offline-Mode-Using-Toast-During-a-Local-Network-Outage)
- Lightspeed K-Series: cloud product; active POS keeps a local cache of menu, modifiers, discounts, prices; "multi-register sync between POS devices, with data stored locally on the active device(s)" over LAN when internet is down; the POS logs activity and uploads when internet returns. — [Lightspeed K-Series: Offline POS functionality](https://k-series-support.lightspeedhq.com/hc/en-us/articles/53028167251867-Offline-POS-functionality)
- Lightspeed K-Series: if both LAN and internet are down, only the active POS works; no device-to-device communication; printers and KDS stop. — same URL.
- TouchBistro: a third-party blog (Katalyst, a competing POS vendor) says multi-iPad installs run on a local Mac server so most operations need only local Wi-Fi, trade-off is maintaining an on-site server. — [Katalyst blog](https://www.katalystos.com/blog/what-happens-to-your-pos-when-the-internet-goes-down). TouchBistro's own help pages (search snippet) say restaurant data lives on iPads and the Pro Server Mac, Mac is the device that syncs iPad data, Pro Server app version must match iPad app, backups of the Mac recommended. — [TouchBistro: Backing up](https://cdn.touchbistro.com/help/articles/backing-touchbistro); [TouchBistro: Updating Pro Server](https://cdn.touchbistro.com/help/articles/updating-touchbistro-pro-server). Note: TouchBistro's marketing blog describes a cloud POS with device-memory offline mode and does not mention the server, so the product line's current state is ambiguous — [TouchBistro blog](https://www.touchbistro.com/?p=5489).
- Revel: describes a "hybrid" iPad POS running locally and syncing to cloud; "Always On Mode" is built in at no extra cost; printers and customer displays keep working. (vendor marketing, 2017 and 2013-era) — [Revel blog 2017](https://blog.revelsystems.com/blog/2017/10/24/get-started-always-mode); [Revel offline datasheet (2013)](https://cdn.revelsystems.com/wp-content/uploads/2013/07/13173613/offline-mode-datasheet.pdf)
- Square: cloud-first; offline is per-device storage of payments, no peer sync described in the pages found. — [Square: offline payments time limits](https://squareup.com/help/us/en/article/8106-offline-payments-time-limits)
- Loyverse: per-device offline; open tickets "don't sync with other devices" until internet returns. — [Loyverse: Offline use](https://help.loyverse.com/help/offline-work-of-pos)
- Poster: cloud; offline sales kept in app cache; same-Wi-Fi printer works; no multi-till sync described. — [Poster KB: does Poster work offline](https://knowledge-base.joinposter.com/en/does-poster-work-offline)
- Petpooja (India): four products. POS Lite = cloud only, no offline. Android POS and Electron POS = offline up to 5 days, data on Petpooja cloud, then sync. POS Local = data on the customer's own computer, runs via a "Bridge Server"; only menus, settings and online orders are synced down. — [Petpooja blog](https://blog.petpooja.com/industry-business-guides/restaurant-pos-without-internet/); [Petpooja Offline POS](https://www.petpooja.com/offline-pos)
- Odoo POS: session data loaded online is stored locally in IndexedDB (forum answer for Odoo 18, community not official); existing sessions continue offline, a new session cannot be opened offline. — [Odoo forum](https://www.odoo.com/forum/help-1/whats-the-mechanism-of-pos-offline-283217). Odoo's general offline mode doc: "not intended to offer full or long-term offline capabilities", 2 GB cap, oldest data evicted, hard refresh wipes local data (this page is the generic web-client offline page, not POS-specific). — [Odoo docs offline mode](https://www.odoo.com/documentation/saas-19.1/applications/general/offline_mode.html)
- Legacy on-prem (Aloha, Oracle Micros): database on site so core operations continue on WAN loss (third-party blog). — [Katalyst blog](https://www.katalystos.com/blog/what-happens-to-your-pos-when-the-internet-goes-down)
- Quick Resto: described by a third-party review site as cloud system on iPad needing no server hardware; its offline behaviour not found. — [toolfox Quick Resto](https://toolfox.ru/services/s/quick-resto)
- R-Keeper: docs describe a "Common Server" microservice base for modules (not an offline description). — [docs.rkeeper.com](https://docs.rkeeper.com/x/AYAmAw)

### Inferences
- Only Toast and Lightspeed (and TouchBistro/Petpooja Local) give multi-device consistency without internet; everyone else accepts "each till is an island" offline.
- Toast's hub is the closest published precedent for option B (hub-first): automatic election, hardwired only, one per site, same subnet only.
- Petpooja's split into Lite/Android/Electron/Local shows one small vendor shipping several tiers of local authority at once, which implies maintenance cost across tiers.

### Gaps
- No vendor named its local DB or sync protocol in a fetched primary source (SQLite/Realm/CouchDB etc. unknown). Not public as far as I found.
- Toast's hub-failure behaviour (failover, split brain) is not documented in the page fetched; only "support can reassign".
- Revel current (2026) offline docs not retrieved; sources are 2013-2017.
- iiko, R-Keeper, Quick Resto, Jowi, BILLZ, Syrve, Clover, Foodics, Moka: no primary source retrieved (see section 6).

## 2. What exactly is disabled offline, and what time/transaction limits are stated?

### Takeaway
Everything that needs a global view or a third party is disabled: gift cards, loyalty, online orders, closeout/reports, new users/menu changes, refunds (Loyverse), and login/clock-in changes. Time limits are mostly about card authorisation windows, not the POS: Square 72 h (24 h for older hardware), Petpooja 5 days, Clover up to 7 days (third-party), Toast and Lightspeed state none.

### Cited Findings
- Toast offline (local sync) disabled list: Resync orders/all data; logging in/out of Toast POS app; clock in/out on separate devices; Kiosk order send/receive; sharing orders between non-KDS devices; printing kitchen tickets from KDS for outage orders; guest data and online reports; employee shift reviews; EMV mode (cards must be swiped); gift cards, rewards points, house accounts, customer credits; guest lookup for takeout/delivery. No offline duration limit stated on the page. — [Toast: Offline mode with local sync](https://doc.toasttab.com/doc/platformguide/platformOfflineModeLocalSync.html)
- Toast: device enters offline mode 40 s after losing connectivity; Tender API payments, text-to-pay, comp cards also disabled. — [Toast: Offline payments overview](https://doc.toasttab.com/doc/platformguide/platformOfflinePaymentsOverview.html)
- Toast offline: cannot close checks, declare cash tips, reconcile cash and tips, or clock out (search summary of Toast docs); Online Ordering and Mobile Order & Pay unavailable. — [Toast: Offline mode](https://doc.toasttab.com/doc/platformguide/platformOfflineMode.html)
- Toast: uninstalling the app or clearing device memory permanently removes stored offline data and payments; keep signed merchant receipt copies. — [Toast: Offline payments overview](https://doc.toasttab.com/doc/platformguide/platformOfflinePaymentsOverview.html)
- Lightspeed K-Series works offline: taking orders/tables/holds, local print, manual payments (cash/check/custom), full menu/modifiers/discounts/prices from local cache, staff clock-in/out. Does not: integrated card payments (terminal must run standalone over LTE/4G), live reports/inventory, new users/major settings/menu changes from Back Office, item pictures/descriptions/allergens, gift cards and K-Series loyalty, third-party integrations (accounting/delivery/online ordering). No time limit stated. — [Lightspeed K-Series: Offline POS functionality](https://k-series-support.lightspeedhq.com/hc/en-us/articles/53028167251867-Offline-POS-functionality)
- Square: offline card payments must upload within 72 h of session start (24 h recommended) and expire after 72 h, unrecoverable; Reader (1st/2nd gen) and Stand allow accepting offline for only 24 h per session; per-transaction cap settable $1 to $50,000; supported in Square POS full-service/quick-service/bar modes and Square for Restaurants app; close-of-day reports unavailable; online/delivery orders may not appear on POS; loyalty points linked manually after upload; Afterpay, Cash App Pay, Square Gift Cards, Tap to Pay, manually keyed cards not supported. — [Square: offline payments time limits](https://squareup.com/help/us/en/article/8106-offline-payments-time-limits)
- Square: declined offline payments only visible after reconnect; offline enabled by default for all devices from April 2026 unless opted out (search snippet; rollout not verified). — [Square: process card payments with offline mode](https://squareup.com/help/us/en/article/7777-process-card-payments-with-offline-mode)
- Clover: Mini/Flex offline up to 7 days by default; merchant-settable per-transaction cap, total stored cap, approval threshold (third-party; Clover first-party doc not retrieved). — [Katalyst blog](https://www.katalystos.com/blog/what-happens-to-your-pos-when-the-internet-goes-down)
- Loyverse: refund button disabled; cannot register/edit customers; integrated card terminals do not work offline; stock levels and negative-stock alerts not displayed; cannot sign out while unsynced receipts exist; open tickets don't sync to other devices; no time limit stated. — [Loyverse: Offline use](https://help.loyverse.com/help/offline-work-of-pos)
- Poster: orders and printing work offline; online orders need internet; do not clear cache or shut down the app offline or accumulated data is lost; power cut kills LAN printing; no duration or feature-restriction list stated. — [Poster KB](https://knowledge-base.joinposter.com/en/does-poster-work-offline)
- Petpooja: billing, KOT printing, table management, cash settlement, day-end work offline up to 5 days; Swiggy/Zomato orders arrive late; card/UPI on EDC need their own connection; cloud reports, menu edits stop; B2B bills needing IRN need government portal. Offline bills keep same GST serial series. — [Petpooja blog](https://blog.petpooja.com/industry-business-guides/restaurant-pos-without-internet/)
- Shopify POS: offline card payments must be enabled in admin and per-staff permission "Accept offline credit and debit payments"; orders not synced to admin until reconnect. Claims of 24 h limit and refunds needing connection are third-party and unverified. — [Shopify help: Offline payments](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/selling-offline/offline-payments?r_done=1); [community thread](https://community.shopify.com/t/how-do-you-handle-offline-pos-orders-during-internet-outages/203388)
- Revel: 2013 datasheet says offline cards may not be fully authorised until reconnect, Revel not liable for declined offline transactions, and it did not recommend accepting cards over $10 (2013, may be outdated). — [Revel datasheet](https://cdn.revelsystems.com/wp-content/uploads/2013/07/13173613/offline-mode-datasheet.pdf)
- Toast terms (via Katalyst): "You are responsible for any declined, expired, or disputed offline payment transactions"; authorisations may expire in as little as 24 h on some networks. — [Katalyst blog](https://www.katalystos.com/blog/what-happens-to-your-pos-when-the-internet-goes-down)

### Inferences
- RIZQIM's current pain list (split bills, discounts, refunds, shift open/close need server) is stricter than Toast/Lightspeed, which keep discounts and local order ops working; Toast also blocks closeout and refunds-like back-office actions, Loyverse blocks refunds. So refunds and shift close being server-only is common industry behaviour; discounts and split bills offline are normal in Lightspeed K-Series (discounts cached locally).
- A hard 24 h cutoff on selling (RIZQIM today) is stricter than any vendor that publishes a limit for POS operation; published limits are card-risk windows (24 h/72 h/5 d/7 d).

### Gaps
- Split bills offline: no vendor page fetched explicitly states split-bill behaviour. Not found.
- Refund behaviour offline for Toast, Square, Lightspeed: not explicit in fetched pages.
- Toast offline card payment article (adminOfflineCCPayments) limits not retrieved.

## 3. How do multiple tills/KDS/handhelds in one store stay consistent without internet; hub failure?

### Takeaway
Two working models: elected/designated hub (Toast) and LAN multi-register sync on active devices (Lightspeed K-Series), or a dedicated in-store server (TouchBistro, Petpooja Local). Others do not sync tills offline at all. Failure of the hub is poorly documented.

### Cited Findings
- Toast with hub: KDS receives POS and Kiosk orders on the same network; orders from one POS are not visible on another POS; Toast recommends one device per employee for placing/updating orders. So even with a hub, till-to-till order sharing is not provided. — [Toast: Offline mode with local sync](https://doc.toasttab.com/doc/platformguide/platformOfflineModeLocalSync.html)
- Toast: hub cannot send orders to cloud while offline; hub only talks to its subnet. — [Toast: Offline mode](https://doc.toasttab.com/doc/platformguide/platformOfflineMode.html) (search summary)
- Lightspeed K-Series: multi-register sync over LAN offline; KDS and printers work over LAN; if LAN also down, only active POS works. — [Lightspeed K-Series](https://k-series-support.lightspeedhq.com/hc/en-us/articles/53028167251867-Offline-POS-functionality)
- Lightspeed: payment terminals with 4G keep working with no internet or local connections. — same URL.
- Poster: tablets and printers must stay on the same Wi-Fi; a 4G/5G Wi-Fi router is recommended to preserve the LAN during power or ISP loss; Bluetooth printers add resilience. — [Poster KB](https://knowledge-base.joinposter.com/en/does-poster-work-offline)
- Petpooja: suggests mobile hotspot at the till because mobile lines often stay up when fibre fails. — [Petpooja blog](https://blog.petpooja.com/industry-business-guides/restaurant-pos-without-internet/)
- Toast hub failure: page "does not describe what happens if the hub fails", only that support can reassign. — [Toast: Offline mode with local sync](https://doc.toasttab.com/doc/platformguide/platformOfflineModeLocalSync.html)

### Inferences
- Even the biggest restaurant-specific cloud vendor stops short of full multi-till consistency offline; it protects the KDS flow (the kitchen) and tells staff to stick to one device. That is a scoped, cheaper version of option B.
- LAN survival (router power, 4G fallback) is itself a stated dependency by Toast, Poster, Lightspeed.

### Gaps
- Conflict resolution rules (two tills editing same order) not documented by any vendor found.
- Hub failover timing and behaviour: not public in sources found.

## 4. Did any vendor move local server to cloud, cloud to hybrid, or add a local hub later, and why?

### Takeaway
Direction found: cloud-first vendors adding local pieces over time (Toast added hub-based local sync and plans to retire the older mode; Lightspeed lists an optional local server on some lines per a third party; Square made offline payments default). No vendor's own explanation ("why") was found in engineering blogs; only product docs.

### Cited Findings
- Toast: newer "offline mode with local sync" with a hub exists beside the older offline mode; search summary says Toast plans to deprecate the older one. — [Toast: Offline mode with local sync](https://doc.toasttab.com/doc/platformguide/platformOfflineModeLocalSync.html); [Toast: Offline mode](https://doc.toasttab.com/doc/platformguide/platformOfflineMode.html)
- Lightspeed: older L-Series used LiteServer (user-provided request; not verified here); Katalyst says Lightspeed offers "on some product lines, an optional local server appliance". — [Katalyst blog](https://www.katalystos.com/blog/what-happens-to-your-pos-when-the-internet-goes-down)
- Square: offline payments auto-enabled on all devices in April 2026 (search snippet; vendor page statement quoted by the search tool, rollout unverified). — [Square: process card payments with offline mode](https://squareup.com/help/us/en/article/7777-process-card-payments-with-offline-mode)
- Shopify changelog entry "Poor connection mode for POS checkout" (2023) exists; text not retrieved. — [Shopify changelog](https://changelog.shopify.com/posts/poor-connection-mode-for-pos-checkout)

### Inferences
- The trend in cloud vendors is to add a narrow local hub for kitchen/KDS and card store-and-forward, not to move authority fully local.

### Gaps
- Vendor "own words" about why they moved: not found. iiko/R-Keeper/Syrve cloud transitions (iikoCloud, r_keeper cloud) not documented in retrieved sources.
- Nothing found on L-Series LiteServer in primary docs.

## 5. Business-rule duplication and data loss when a device dies before sync

### Takeaway
No vendor discusses rule duplication publicly in the sources found. Data loss on device death is acknowledged only as a warning: offline data lives on the device and is lost if cleared or the device fails.

### Cited Findings
- Toast: uninstalling or clearing device memory permanently removes stored data and payments; keep signed merchant receipts as backup. — [Toast: Offline payments overview](https://doc.toasttab.com/doc/platformguide/platformOfflinePaymentsOverview.html)
- Poster: clearing cache or shutting down offline loses accumulated offline sales. — [Poster KB](https://knowledge-base.joinposter.com/en/does-poster-work-offline)
- Loyverse: cannot sign out while unsynced receipts remain on device (guard against loss). — [Loyverse](https://help.loyverse.com/help/offline-work-of-pos)
- Square: expired (72 h) pending payments cannot be retrieved or reprocessed. — [Square](https://squareup.com/help/us/en/article/8106-offline-payments-time-limits)
- Odoo: a hard refresh removes locally stored data (generic offline mode page). — [Odoo docs](https://www.odoo.com/documentation/saas-19.1/applications/general/offline_mode.html)
- Petpooja: offline bills keep the same GST serial number series, i.e. numbering is resolved locally. — [Petpooja blog](https://blog.petpooja.com/industry-business-guides/restaurant-pos-without-internet/)

### Inferences
- Common pattern: single-device ownership of offline orders (Toast "one device per employee") sidesteps conflict resolution rather than solving it.
- Disabling discounts/refunds/closeout offline (Toast, Loyverse) is effectively how vendors avoid duplicating rule engines on the device.

### Gaps
- No vendor statement on duplicated business logic, pricing/discount engines on device, or CRDT/merge strategies found.

## 6. Fiscal, card payments offline; CIS vendors; small-vendor choices and restaurant complaints

### Takeaway
Card payments offline are store-and-forward with merchant bearing the decline risk, or delegated to a separate terminal. For CIS vendors (iiko, R-Keeper, Quick Resto, Jowi, BILLZ, Syrve) I found no usable primary documentation. Small cloud vendors (Loyverse, Poster, Petpooja Lite/Android) choose device-cache offline with no peer sync; Petpooja shows the hybrid with an optional local bridge server.

### Cited Findings
- Toast: merchant responsible for declined/expired/disputed offline payments; EMV disabled in local sync offline (swipe only). — [Katalyst blog](https://www.katalystos.com/blog/what-happens-to-your-pos-when-the-internet-goes-down); [Toast: Offline mode with local sync](https://doc.toasttab.com/doc/platformguide/platformOfflineModeLocalSync.html)
- Lightspeed K-Series, Loyverse, Petpooja: card terminals work independently (standalone mode/own connection), the POS records the sale separately; reconciliation is manual. — [Lightspeed](https://k-series-support.lightspeedhq.com/hc/en-us/articles/53028167251867-Offline-POS-functionality); [Loyverse](https://help.loyverse.com/help/offline-work-of-pos); [Petpooja](https://blog.petpooja.com/industry-business-guides/restaurant-pos-without-internet/)
- Fiscal: Petpooja states e-invoice (IRN) needs the government portal and cannot be done offline. — [Petpooja blog](https://blog.petpooja.com/industry-business-guides/restaurant-pos-without-internet/)
- Quick Resto: third-party integrator instruction connects iPad to Aqsi fiscal register by local IP on the same router (local network between iPad and cash register, not a Quick Resto server). — [vc.ru Aqsi](https://vc.ru/id1103752/2002837-kassa-aqsi-za-0-rubley)
- iiko: search found only third-party hints; no official statement on iikoFront offline behaviour; one third-party dashboard FAQ implies cash terminals talk to the iiko server and loss of that link means no sales data in iikoOffice. — [iikoDashboard FAQ](https://support.iikodashboard.com)
- Revel customer-complaint claim "unreliable offline mode" comes from a competitor (Restroworks) page; unverified. — [Restroworks](https://www.restroworks.com/?p=24281)
- Complaints surfaced in sources: Toast "multiple subnets" breaks KDS offline; Poster power cut kills LAN printing; Katalyst notes the TouchBistro trade-off of maintaining an on-site server. — [Toast KDS offline](https://doc.toasttab.com/doc/platformguide/platformOfflineKDSDevices.html); [Poster KB](https://knowledge-base.joinposter.com/en/does-poster-work-offline); [Katalyst](https://www.katalystos.com/blog/what-happens-to-your-pos-when-the-internet-goes-down)

### Inferences
- Small vendors with limited engineering resources choose per-device cache + store-and-forward and push the rest to operational advice (4G router, hotspot, standalone terminal). A hub (Toast-style) is the next step; a full local server (iiko/R-Keeper/TouchBistro) is the heaviest and is also the one with ongoing support burden (versions must match, backups).
- For RIZQIM: Toast's scoped hub (KDS + order relay on LAN, no till-to-till order sharing, closeout disabled) is the published floor for option B; its disabled list (closeout, gift cards, loyalty, login changes) maps to what RIZQIM already restricts.

### Gaps
- iiko, R-Keeper (classic RK7 vs cloud), Syrve, Jowi, BILLZ, Quick Resto offline behaviour and architecture: not found in retrieved sources; need ru.iiko.help, docs.rkeeper.com, habr.com, vc.ru targeted pages (not retrieved here). Not public in what I saw is NOT the same as not existing.
- Foodics, Moka, Posist/Restroworks (technical), Clover first-party docs: not retrieved.
- Engineering blogs/postmortems (Toast tech blog, Square Corner, Shopify Engineering, AWS-outage impact on Toast): not retrieved; a Toast community thread titled "AWS outage impacting Toast systems, offline mode checklist" exists — [Toast community](https://community.toasttab.com/t5/toast-community-hub/aws-outage-impacting-toast-systems-offline-mode-checklist/m-p/17094) — not read.
- Reddit/forum complaints not systematically collected.
