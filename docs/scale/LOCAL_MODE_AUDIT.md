# RIZQIM POS "local mode": what works locally today, and the gap to local-first

Read-only audit, repo at 65551cd (2026-11-14). Builds on DB_SCALE_PLAN.md in the same folder. **[F]** = fact from code or docs,
**[E]** = estimate or guess. Paths are relative to the repo root. "Spec" = docs/POS_SPEC.md.

## 0. Summary

- [F] The design is **cloud-first with a short-outage fallback**. Spec §5 says it plainly: "this is not a full offline system".
  The goal is "a few minutes up to 1–2 h" (Spec:942-943), and long offline work is out of scope (Spec §5.6:1077-1087).
- [F] Online, every write goes straight to the API over HTTP with an `Idempotency-Key`. The outbox is used **only** while the till is offline.
  So there are two write paths: direct HTTP and the queue (`pos_data/lib/src/offline/pos_offline.dart`, `_guard`/`_enqueue` :878-924).
- [F] The server is the source of truth. It re-applies every offline op through the same PHP domain services
  (`backend/app/Domain/Pos/Sync/PosSyncApplier.php:50-75`).
- [F] There is a P6 LAN "hub" (`pos/packages/pos_lan`). It is a **relay plus a temporary local order book** on the hub till, used
  only when the cloud is unreachable. It is **not** an authoritative store, and it takes no money operations.

## 1. Local storage on a till

- [F] **Store.** `pos_sync` `FileSyncStorage` keeps a directory `<ApplicationSupport>/pos_sync/` (`apps/cashier/lib/main.dart:29-32`). It holds:
  - `journal.log`: append-only, one JSON line per op plus CRC32, fsync after every line (`pos_sync/lib/src/storage.dart:5-35`);
  - `doc_<key>.json`: snapshots, written to a temp file then renamed.
  - SQLite was rejected on purpose: no native library is needed, and the queue is small (Spec:968-969).
  - The journal is compacted after 800 lines and keeps 200 answered ops (`pos_sync/lib/src/store.dart:64,68`).
- [F] **Snapshot docs.** They are copied from online responses and are **not** a full replica (`pos_offline.dart:225-299`):
  - `bootstrap` (settings, `offline` section, fees, places, limits, device prefix, next_local_number). The `lan` keys are left out.
  - `catalog:<channel>` (menu with prices, carries `menu_version`);
  - `floor`, `order:<uuid>`, `billing:<uuid>`, `open_orders`;
  - offline sales still to print (`_salesKey`), `lan.bill_prints` (`pos_lan/lib/src/bill_prints.dart:188`), and the QR-offline-tender log (`apps/cashier/lib/src/features/billing/qr_offline_guard.dart:194`).
  - `_warm` pre-fetches up to 40 open orders from the floor whose `version` changed (`pos_offline.dart:300-346`).
  - Snapshots are **not refreshed while ops are pending** (`_put`, :290-299).
- [F] **Lifetime.** Snapshots stay until overwritten. They are treated as usable only for 24 h: the bootstrap copy is ignored after
  `contact_max_age` 24 h (:275-288), and selling stops if the menu copy is older than 24 h (`pos_sync/lib/src/limits.dart:136-145`).
- [F] **Price history** is not on the device. `menu_price_history` exists only on the server (`app/Services/Catalog/PriceHistory.php`).
  The device holds one catalogue copy.
- [F] **Other storage.**
  - Secure storage holds the terminal token and the LAN hub keys (`main.dart:20,41`).
  - SharedPreferences holds UI and printer settings (`cashier_prefs.dart`, `shell_settings.dart`, `kds_settings.dart`, `display_settings.dart`).
- [F] **In memory only.**
  - The PIN staff session (`pos_data/lib/src/session/pos_session.dart`; PIN is checked by the server, Spec §5.4:1012-1016).
  - The open shift, beyond what bootstrap carries.
  - Stop-list and kazan state.
  - Waiter and KDS state. The waiter and KDS apps have **no** SyncStore: their LAN keys live in memory, and the KDS keeps its last list (pos_lan README §9).
  - Staff, permissions and PIN hashes are **never** on the device ("bootstrap'da PIN xeshlari yo'q", README §9).

## 2. Offline behaviour today (till offline, shift already open)

Legend: **Full** = works offline; **Lim** = works with limits; **Online** = online-only. Evidence: `pos_offline.dart` guards,
repository `offlineMessage`s, PosSyncApplier, Spec §5.

| Operation | Status | Detail |
|---|---|---|
| Order create (hall/takeaway), add round | Lim | From the cached catalogue only. Room must be free locally (:376-381). 500-order cap (:368-371). |
| Edit order (move, merge, guest count, cancel) | Online | Spec:976-977 |
| Send to kitchen, course send, weight, mark served | Full | Kitchen tickets print from this till to LAN printers. |
| Void a line | Lim | Unsent lines only (`line_sent` rejection). |
| Bill | Lim | One bill. Pre-check prints. `nothing_to_bill` and `weight_pending` guards (:607-630). |
| Split, discount, promo code, voucher | Online | `billing_repository.dart:15-17`, `voucher_repository.dart:24-30` |
| Cash, card on a separate terminal | Full | Queued `bill.pay`. Overpayment is blocked (:756-762). |
| Static QR | Online in code | `pay()` refuses any tender other than cash/card (:746-751); the server answers `tender_not_offline` (`PosSyncApplier.php:1218`). Spec:291 suggests a hand-confirmed static QR still works offline. **Mismatch.** |
| Dynamic QR, Click, Payme | Online | `qr_payment_repository.dart:22-30`. A pending QR plus a cash tender is logged in `qr_offline_guard.dart`. |
| Prepayment, corporate account (`account`), voucher tenders | Online | Only cash and card are accepted. |
| Fiscal receipt | Queued server-side | No fiscal device on the till. See below. |
| Refund, void a bill | Online | Spec:976-977 |
| Shift open, close, Z, cash in/out | Online | `shift_repository.dart:21-28`; Spec:1018-1022. Close needs an empty queue (409 `shift_has_unsynced_ops`, `shift_has_sync_errors`, manager `force`). |
| X report | Online [E] | Comes from `GET /pos/shifts/current`. |
| Stop-list, kazan | Online | Changes do not spread to other devices; `ingredient_stop_sheet.dart` shows an offline message. Overselling is accepted at sync (`oversold`). |
| Bookings, banquets | Online | `booking_confirmation.dart`, `banquet_ops_repository.dart:19-25`. Banquet payments are online too. |
| Staff PIN login | Online | Only staff signed in before the outage keep working. After an app restart offline nobody can sign in, but the queue survives (Spec:1012-1016). The screen does not auto-lock in limited mode. |
| Manager approvals | Online | "internetsiz menejer tasdig'i olinmaydi" (`pos_offline.dart:886-891`). Bill reprint is not possible over LAN (README §9). |
| Telegram, mobile, aggregator orders | Online | They never reach the store during an outage. |

- [F] **Fiscalisation offline.**
  - The till queues only the sale (`bill.pay`). At sync the server calls `BillFiscalizer` with `sold_at` = the offline payment time
    (API.md:1854-1859), and the till then auto-prints the sale receipt (`pos_offline.dart:184-222`).
  - The fiscal path is a cloud virtual-cash provider (Spec §4.8). The provider is **not yet chosen**: there is a sandbox driver only.
  - The 48 h virtual-register rule (Spec §4.6) drives the UI warnings at 24 h, 40 h and 48 h (`limits.dart:11-13,123-133`).
- [E] **Realistic outage endurance.** With the shift opened before the outage:
  - selling continues until **24 h after the last server contact**, or until the menu copy is 24 h old, whichever comes first (`limits.dart:136-145`);
  - there is a cap of 500 queued orders;
  - the shift cannot be closed, so in practice the limit is one shift.
- [E] **What breaks first, in order.**
  1. Immediately: marketplace and Telegram orders, QR/Click/Payme, vouchers, discounts and splits, new staff logins, and stop-list sync.
  2. Waiters, if there is no hub: their phones become view-only.
  3. Within minutes to hours: the KDS shows stale data, unless a hub is up and the kitchen works from paper tickets.
  4. A restart of the till's app loses all staff sessions.
  5. At 24 h: selling stops.

## 3. Sync protocol

- [F] **Outbox record.** `{op_id UUID, seq (per device, monotonic), type, occurred_at (device clock), staff_id, payload, origin_device?}`
  (`pos_sync/lib/src/operation.dart:89-156`). Op types (:3-26): order.open, order.round, order.send, order.void, order.served,
  bill.create, bill.pay, order.close, order.course_send, order.weight, kitchen.bump, kitchen.recall, kitchen.assembled, bill.request.
- [F] **Transport.**
  - `POST /v1/pos/sync`: 1-50 ops per batch, sent with the device token (`auth:device`), throttled at 120/min per device
    (`PosSyncServiceProvider.php:50`).
  - Back-off runs from 2 s up to 1 min (`sender.dart:95-96`).
  - While offline, the till probes `GET /pos/sync/status` every 15-60 s.
- [F] **Ordering.**
  - Per-device Cache lock, 120 s (409 `sync_in_progress`).
  - Within a batch, ops are sorted by `seq` and applied one by one, each in its own DB transaction (`PosSyncService.php:43-80`).
  - A transient DB error stops the batch at that seq (`stopped_at`); later ops stay queued.
  - Payload errors do not stop the queue: the op is `rejected` (`invalid_payload`).
  - There is **no ordering across devices**.
- [F] **Idempotency.**
  - `op_id` is unique: a resend returns the stored result with `duplicate: true`.
  - `(device_id, seq)` is unique (409 `seq_reused`); another device's op_id gives 409 `op_id_conflict` (`PosSyncService.php:56-69`).
  - The order's device UUID becomes `external_id`.
  - The optional `idempotency_key` links an op to an online request that was lost before the outage. A relay op's op_id doubles as that key.
- [F] **Conflict handling** (API.md:1836-1852, `PosSyncApplier.php:56-70`).
  - Results are `applied`, `conflict` (applied but flagged for the manager) or `rejected`.
  - Rejection reasons: `order_closed`, `line_sent`, `bill_paid`, `tender_not_offline`, staff not signed in within 36 h, `permission_denied`, relay reasons.
  - Rejected ops are **not retried**. They land in "Sinxronlash xatolari" (`GET /pos/sync/errors`, resolve or dismiss, audited); a human fixes them.
  - **Money is never rejected**: on a cancelled order the payment is flagged `refund_needed`, on a paid bill `payment_unallocated`; also `underpaid` / `overpaid`.
  - An occupied table opens the order without a table (`conflict`).
  - Order versions are deliberately not compared.
- [F] **Clock.**
  - The server computes skew from `sent_at` (warning above 300 s) and corrects `occurred_at` by it.
  - Times are clamped to [now − 48 h, now]. A payment is never dated before the shift opened. Clamps add the `device_time_clamped` flag (`PosSyncApplier.php:223-249,735`).
  - LAN ops are stamped with the hub's clock.
- [F] **Numbering offline.**
  - `local_number` = device prefix + counter (`K1-0041`); the prefix and next number come from bootstrap. The DB enforces UNIQUE(store_id, local_number); a clash gives `local_number_taken` and the order is opened unnumbered.
  - Queue numbers come from per-device blocks issued by the server (`pos_sync/lib/src/queue_numbers.dart`). Tills cannot collide by construction. If blocks run out, the order is opened without a number.
- [F] **What the server recomputes and what it trusts.**
  - Line prices are recomputed. The device price is kept only if it was effective in `menu_price_history` at some point between `menu_taken_at` and now. Otherwise the server price is used and the op is flagged `offline_price_mismatch`; the owner is alerted if the device price was lower.
  - A menu copy older than 24 h gives `stale_menu`.
  - Charges are recomputed by `PosChargesCalculator`. The till mirrors it to avoid `amount_mismatch` (Spec:972-975).
  - Bill totals come from the server; the device's `expected_total` is informational.
  - Tender amounts and card `transaction_id` are **trusted as claimed**. No promo exists offline.
- [F] **Data flowing down.**
  - Polling endpoints (15 s, or 60 s while Reverb is live), Reverb `pos-branch.{id}` events, and bootstrap/catalogue refetches when `menu_version` changes (`Domain/Pos/Setup/MenuVersion.php`).
  - LAN carries nothing down from the cloud; it is only the outage path.
  - Reverb is off by default (`BROADCAST_CONNECTION` null, see DB_SCALE_PLAN §3).

## 4. LAN layer (pos_lan, P6 PR #21: 139 files, +10.4k lines in 3cc65bd, then a large review fix fb0dc1b)

- [F] **What the hub is.**
  - The hub is a `dart:io` HTTP plus WebSocket server (port 18480) with a UDP beacon every 2 s, running **inside the cashier app** (`apps/cashier/lib/src/lan/cashier_lan_hub.dart:100-200`).
  - It applies each client op through its own `PosOffline` rules (`pos_lan/lib/src/pos_offline_applier.dart`) into **its own outbox**, tagged `origin`.
  - It serves `state` / `order.changed` from its `OfflineOrderBook`: the last server snapshots plus its queue. That makes it a temporary local book, not a replica.
  - It accepts ops even while it is online and relays them at once (README §1).
- [F] **Which device can be the hub.**
  - Only a cashier-app till: Android or Windows. The server picks it: the owner's choice (`PUT /owner/pos/lan`), else the printing till, else the current hub, else a cashier-app terminal. A waiter phone never becomes the hub (Spec:1052-1058).
  - There is no dedicated box. On Windows there is firewall help (`windows_firewall.dart`).
- [F] **Messages.**
  - Client to hub: hello, grant, op (`order.open`, `round`, `send`, `course_send`, `weight`, `items.void`, `served`, `kitchen.bump`, `kitchen.recall`, `kitchen.assembled`, `bill.request`), state.get, order.get, ping.
  - Hub to clients: welcome, state, order.changed, kitchen.ticket, kitchen.item_status, queue.*, cart.display, lan.state.
  - Money, bills, closing, refunds and discounts are answered `not_allowed_on_lan` (README §6).
- [F] **Security.**
  - Per-branch 32-byte secret on the server. Per-device keys are derived with HMAC; the full secret is held only by the hub.
  - Frames are encrypted with HMAC-SHA256 in counter mode plus a fresh 12-byte nonce, then signed with HMAC (encrypt-then-MAC, `crypto.dart:30-62`).
  - Replay protection: a ±2 min window and `(device, seq)`; the `hello` carries the hub's challenge.
  - Staff identity is a **server-signed grant** valid 36 h after the PIN login (`grant.dart`; README §3).
  - Transport is plain `ws://` with home-made but sound-looking crypto [E]; there is no TLS.
  - The server re-validates every relayed op: the sender must be the branch hub, the origin an active terminal, and the staff member signed in on the origin device within 36 h of the op time (`PosSyncApplier.php:297,1067-1086`).
- [F] **Which devices work over the hub with the internet down.**
  - The waiter app can open orders, add rounds and send to the kitchen. Bill reprint is not possible.
  - The KDS can bump, recall and assemble.
  - The customer display shows the cart and queue numbers.
  - Clients use `LanLink`: they probe `/health` every 15 s, fall back to `lan` or `alone`, and return to `cloud` only when the hub reports `pending: 0`.
- [F] **When the hub dies.**
  - There is no election or standby; the hub "does not move silently". Clients go `alone` (view-only) and retry at 2/5/10/30 s (`link.dart:46-54`).
  - Ops the hub accepted are on its disk only. A broken hub means lost waiter and KDS ops [E].
- [F] **Other cashiers are not LAN clients.** Only waiter, KDS and display use `LanLink` (`apps/{waiter,kds,customer_display}/lib/src/lan/*`).
  A second till is an island: its offline orders are invisible to the hub and the KDS until the cloud returns (Spec:1024-1025).
- [F] **What still needs the cloud with the LAN up:** payments other than at the hub till, every Online row in §2, marketplace orders, PIN login, approvals, fiscal receipts, and catalogue or stop-list changes.

## 5. Marketplace to kitchen

- [F] **Path today.**
  1. A Telegram or Mini App order is created on the central API (`POST /orders`).
  2. The store sees it by polling or a Reverb event (`order.created` / `order.status_changed`, `pos_data/lib/src/realtime/pos_events.dart:19-20`) in the cashier queue page (`features/queue/queue_page.dart:46,147-160`).
  3. A cashier accepts it (Click/Payme-paid orders are auto-accepted). Acceptance prints the bag label (:153).
  4. Spec:164 says the kitchen ticket comes out on the branch's "printing" till and the order shows up on the KDS. The KDS does see it via `/v1/staff/kitchen/tickets` polling.
- [F] **Not verified:** I did not find in a quick grep where the printing till auto-prints the kitchen ticket for an accepted online order.
- [F] Aggregators are hand-entered today (Spec:167); the `Integrations` domain handles webhooks.
- [E] **What local-first would need.**
  - One persistent cloud-to-hub push channel (WS) for marketplace orders, with ack and redelivery.
  - Accept/reject decided on the hub and pushed up.
  - Status changes pushed up quickly, because the customer's Mini App and the SLA jobs (`CancelUnpaidOnlineOrdersJob`, unaccepted alerts) run on the cloud.
  - The cloud stays authoritative for marketplace order state and payment, so these orders keep a cloud-first lifecycle even in design B.

## 6. Trust and tamper-resistance today

- [F] **What protects sales today.**
  - The server recomputes prices and totals against `menu_price_history`; promos and discounts are impossible offline.
  - Per-device `seq` and `op_id` uniqueness. Each op is checked against a 36 h PIN session audit and the staff member's permissions.
  - audit_logs is append-only by trigger. Fiscal receipts are issued server-side only.
  - The `printed_on_lan` dedup stops double bill paper. Reprints need a manager or a permission and are logged on the server.
  - The device token and the LAN grants are signed by the server.
- [F] **Gaps.**
  - Ops are **not signed or hash-chained** (no hmac/signature in `pos_sync/lib`).
  - A till could drop journal lines before sync. Only the `queue.size` / `oldest_at` counters (`devices.sync_pending_ops`) and `seq` gaps hint at it. I did not check whether the server alerts on seq gaps.
  - Cash amounts are trusted as claimed.
- [E] **If the device became authoritative**, these would be lost or need replacing:
  - server recomputation as a gate (it would become an after-the-fact audit);
  - real-time server-side discount, void and refund approvals;
  - the server-held reprint log;
  - the PIN check.
- [E] **Replacements needed:**
  - signed, hash-chained op logs per device;
  - local PIN verification (hashes or pre-issued grants);
  - local approval tokens;
  - and the fiscal receipt as the external anchor. It is still issued by the cloud provider within 48 h, so unsynced sales are untaxed until sync.

## 7. Device fleet operations

- [F] **Versioning.**
  - No min-version or upgrade-required enforcement and no OTA updater in any POS pubspec. Apps are at `version: 0.1.0+1`.
  - `devices.app_version` is recorded at registration (`Services/Auth/DeviceService.php:88`) but not enforced.
  - The API is `/v1` only. `X-Rizqim-App` identifies the app. The catalogue is versioned by `menu_version`; the LAN contract by `v` (README).
- [F] **Local schema.** There is no migration framework. Journal lines and docs carry no format-version field (`store.dart`).
  Compatibility relies on tolerant JSON parsing: a `FormatException` drops the snapshot (`pos_offline.dart:247-257`).
- [F] **Backup.** There is none for local data.
- [F] **A lost or broken till** loses its unsynced ops, plus any LAN ops if it was the hub. The server knows only the counts (`devices.sync_pending_ops`, `sync_oldest_op_at`).
  Shift close is then blocked until a manager forces it. The fiscal receipt for those sales never exists.

## 8. Gap analysis (one wave ≈ one P6 wave: backend + apps + e2e + review, one PR, about 10-20k changed lines)

Load baseline (DB_SCALE_PLAN §3) for a typical store (2 tills, 1 KDS, 3 waiters, 1 display, 1 owner tab) at lunch:
about 40 requests per minute with Reverb live, about 90 without, mostly full-state reads. Writes are about 6-8 requests and about 18 rows per order.

### A. Cloud-first, quieter. **Size S-M (≈0.5-1 wave). Recommended first in any case.**
- **To build.**
  - ETag/If-None-Match, or a `since`/version cursor, on the ~8 polled endpoints: floor, order, billing, queue, kitchen/tickets, display state/cart/queue, staff orders, partner LiveOrders, Mini App notifications.
    Orders already carry `version`, and `_warm` already does version diffing.
  - Reverb on by default (`docker-compose.yml:41`). Polls become a 2-5 min safety net. Cache channel auth.
  - Run Reverb horizontally (Redis scaling) with an OS file-descriptor budget of about 7 WebSockets per store.
- **Reuse.** `PosRefreshScheduler`, the Reverb events, `pos_events.dart`, menu_version. **Nothing contradicts it.**
- **Risks.** Missed-event bugs, mobile WebSockets suspended in the background, Reverb as a new single point of failure (polling stays as the fallback).
- **Load [E].** About 8-15 rpm per store at lunch, mostly writes plus 304s. Idle about 1-2 rpm. DB reads fall by about 80-90%; DB writes are unchanged.

### B. Hub-first. **Size L-XL (≈3-5 waves).**
- **To build.**
  1. Make the hub authoritative for the whole shift. Every operation, including bills, split, discount, refunds, void, cash moves, shift open/close and X/Z, needs a local engine.
     That means porting PHP rules (PosOrderService, PosLineBuilder, BillService, PaymentService, charges, kazan/stop-list) to Dart. `OfflinePricing` / `offline_book` cover only a subset.
  2. Other cashiers become LAN clients; today they are islands.
  3. A durable hub store, probably SQLite [E], since the journal plus snapshots will not scale to a full shift state.
  4. Offline PIN verification and approvals.
  5. Hub failover: warm standby replication to a second till, or a promote-with-handover protocol.
  6. A cloud-to-hub WS for the catalogue, stop-list and marketplace orders (§5).
  7. Invert the server rule: the server accepts hub facts and only flags them. That replaces most `rejected` outcomes.
  8. Batched upload at fixed intervals, and reports that tolerate the lag.
  9. Signed op chains.
  10. Fiscal: still the cloud provider, in near real time when online.
- **Reuse.** pos_lan (frames, keys, grants, beacon, LanLink modes, relay validation `relay_not_hub`), pos_sync (outbox, idempotency, seq), PosSyncApplier as the cloud ingest, queue-number blocks, local_number prefixes, hub-accepts-while-online.
- **Contradicts.**
  - "The server is the source of truth" and the conflict table (Spec §5.3).
  - Rejection-based conflict handling.
  - Every online-only feature (QR/Click/Payme, vouchers, accounts, bookings and banquets need cloud round-trips or cached ledgers).
  - Two code bases for business rules (PHP and Dart), with a drift risk.
  - The Android background lifecycle: the hub dies when its app is closed, which the spec accepts today.
  - Spec §5.6 explicitly excludes "full transfer of server state between devices".
- **Risks.** The hub as a single point of failure, with data loss on hub death; owner, Mini App and SLA views lag by the batch interval; marketplace order latency; a security surface on Wi-Fi; a long tail of edge cases.
- **Load [E].** 1 connection per store, about 2-6 rpm (a batch every 10-30 s plus a heartbeat), plus the partner web and Mini App as before.
  The central DB writes the same ~18 rows per order but in fewer, larger transactions. Write rows do not drop.

### C. Fully local server (iiko / R-Keeper style). **Size XL (≈5-8+ waves, plus a hardware and ops product).**
- **To build.**
  - A per-store server and DB with an installer, updates and backups.
  - The POS domain as a local service: either the PHP stack in a box or a rewrite.
  - Bidirectional replication with conflict rules for the catalogue (cloud-owned), orders and money (store-owned), and marketplace orders (cloud-owned).
  - Monitoring of thousands of on-prem boxes.
- **Reuse.** The same as B, and possibly the Laravel domain code if it is shipped on-prem.
- **Contradicts.** The Android-till, zero-hardware SaaS model (Spec §5.6: no separate local server); the central fiscal path; the paid-POS onboarding target of "1 working day" (PHASE6_PLAN:38).
- **Load [E].** The same as B for the central server, plus replication streams.

## 9. Single-device stores (one Android till, no LAN)

- [F] Today's offline mode **is** local mode for one device, but limited. Missing offline:
  - shift open/close, X/Z, cash moves;
  - split, discount, refunds, moving tables;
  - QR, vouchers, accounts;
  - PIN login after a restart;
  - stop-list changes.
  Selling also stops after 24 h without contact.
  The customer display works with no server, as the device's second screen (`main.dart:47-52`).
- [E] "Local-first" here means routing every operation through the local engine and the outbox, and syncing in the background.
  This is B with N=1 and no LAN, minus failover: still the Dart port of the business rules and offline PIN, so **size L (≈2-3 waves)**.
  The biggest risk is losing the device with unsynced sales. That needs a backup: cloud sync whenever online, or an optional export.
