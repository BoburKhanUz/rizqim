# M5 "Offline mode and app updates": design (24 h stop, offline discount, signed queue, till backup, versions)

Status: design at commit 966119b3 (2026-11-19). Nothing is built. Read-only survey of `pos/apps/*`, `pos/packages/*`,
`packages/dart/rizqim_api`, `backend/app/Domain/Pos/Sync`, device pairing and tokens; it re-verifies
[LOCAL_MODE_AUDIT.md](LOCAL_MODE_AUDIT.md) (65551cd, 14.11.2026) against today's code.
Labels: **[F]** = fact from code (file:line, read at 966119b3), **[E]** = estimate or judgement. Nothing was executed
(the POS packages have no resolved `package_config` in this checkout, and no database was needed); "by reading" marks a
behaviour derived from code and not run. Paths are relative to the repo root.

**Owner decision (19.11.2026): outages of a few hours are enough, not a whole day.** The narrow scope is the plan.
Section 9 is a short note of what a "whole day" answer would change later; it has no stages.

## 0. Summary of decisions

| # | Decision |
|---|---|
| A | **The 24 h stop becomes "no NEW orders", never "no payment".** Today the till's single guard refuses every offline operation after 24 h, payment of an open table included. The limit stays 24 h (hours are enough), but it only stops opening new orders; rounds, bill, cash/card payment and closing of orders that are already open keep working with a red banner. The server stops refusing money for an old PIN session: it records it and flags it |
| B | **Three data-loss paths are closed before anything is added** (section 1.6): the journal is wiped on a 401, a corrupt line cuts off everything after it, and a till can be disconnected while it holds unsent sales |
| C | **Offline discount = one new queued operation `order.discount`**, applied by the server through the existing `PosOrderService::discount()`. Offline rights are the online rights minus manager approval: a holder of `orders.discount` any percent, a cashier up to the branch's `cashier_discount_max_percent`, only before the bill. A discount the server refuses never loses the sale: the payment is recorded and the order is flagged `underpaid` for the manager (the path that exists today) |
| D | **Queued operations are signed with HMAC-SHA256 and chained**, with a per-device key issued at pairing and kept in secure storage. No new dependency (`crypto` 3.0.7 is already in `pubspec.lock`; Ed25519 is not). A bad signature never drops money: observe mode first, then non-money operations are rejected and money is recorded as a conflict |
| E | **Till backup = a second copy of the journal on the device + a signed export file + a server import ("rescue") + a printed offline summary.** The export file is verified with the device key, so the queue of a dead, stolen or disconnected till can be loaded by a manager from another place. Honest limit: a single Android till that is destroyed before anyone exported still loses its unsent operations; the paper summary is what remains |
| F | **Versions: a release registry on the server, a manifest for pos.rizqim.uz, the app's version on every request, and a notice in the apps.** "Minimum version" refuses pairing and setup only. It never blocks payments, closing, receipts, shifts or refunds: below the minimum the till shows a permanent red notice and RIZQIM and the owner are told |
| G | Six stages, split into 1-2 hour briefs with disjoint files (section 7). S1-S4 are money/security (Fable + 2 reviews), S5 is mostly simple (Sonnet), S6 is the offline scenario suite that LAUNCH_PLAN names as M5's measure. 9 owner decisions with defaults in section 8 |

## 1. Inventory (what the code does today)

### 1.1 The 24-hour stop

- [F] It is a **client rule only**. `pos/packages/pos_sync/lib/src/limits.dart:136-145`: while the till is offline,
  `blockReason` is set when the last server contact is older than `contactMaxAge` (24 h, `:10`) or the menu copy is
  older than `menuMaxAge` (24 h, `:9`); `canSell` is `reason == null` (`:155`).
- [F] **Everything stops, payment included.** `pos/packages/pos_data/lib/src/offline/pos_offline.dart:884-888`
  (`_guard`) throws `PosOfflineUnavailable` when `!canSell`, and every offline operation calls it: `open` (`:371`),
  round (`:452`), send (`:475`), `createBill` (`:618`), **`pay` (`:743`)**, and through them `order.close`
  (`:787-791`). After 24 h without contact a table that is already eating cannot be billed or paid on the till.
  **This breaks the owner rule "never block taking payments".**
- [F] While the till is online with a queue that is still draining, nothing is blocked (`limits.dart:137`:
  `active && !online`).
- [F] Other limits in the same place: 500 queued new orders blocks only `open` (`pos_offline.dart:372`,
  `limits.dart:146`); fiscal warnings at 24/40/48 h are text only (`limits.dart:123-134`,
  `apps/cashier/lib/src/shell/limited_mode.dart:215-222`).
- [F] The stored bootstrap copy is ignored once it is older than `contactMaxAge`
  (`pos_offline.dart:279-286`), so an app restart after 24 h offline has no halls, tables or fees.
- [F] The limits come from the server in bootstrap: `backend/app/Domain/Pos/Sync/OfflineBootstrapSection.php:38-48`
  (`contact_max_age_hours` 24, `menu_max_age_hours` 24, `max_queued_orders` 500, `fiscal_limit_hours` 48). They are
  constants, not a store setting.
- [F] **Server side there is no 24 h refusal.** `PosSyncApplier.php:79` `STALE_MENU_HOURS = 24` only adds a
  `stale_menu` flag (`:964-966`). Two other server windows matter:
  - `:85` `DEVICE_TIME_MAX_AGE_HOURS = 48`: an operation time older than 48 h is moved to now − 48 h and flagged
    (`:233-249`).
  - `:82` `SESSION_WINDOW_HOURS = 36`: the staff member must have a `staff.session_started` audit row on that till
    within 36 h before the operation (`:1094-1104`). **When this fails the operation is rejected, money included**
    (`:139-152`, comment "never applied, money included"); the same for `permission_denied` and `staff_invalid`. This
    is the only place where the server drops a payment the till took, and it contradicts the class comment at
    `:55-56` ("money taken is never rejected").
- [F] **Other hard stops that are not the 24 h rule:**
  - No PIN login offline, and the staff session lives in memory only (`pos_data/lib/src/session/pos_session.dart:78-84`,
    `_staff` is never written to disk). After an app restart during an outage `_guard` answers "Xodim kirmagan"
    (`pos_offline.dart:889-891`): nobody can take payment until the internet is back, at any hour of the outage.
  - No shift open/close, cash in/out, refund, split, vouchers, QR offline (unchanged from the audit, section 2).
    The shift close needs the queue to be empty server-side (`backend/app/Domain/Pos/Shifts/ShiftService.php:358-364`).

### 1.2 Discounts

- [F] Blocked on the till: `pos_data/lib/src/orders/order_repository.dart:512-516` throws
  "Chegirma uchun internet kerak" whenever limited mode is active. There is no `order.discount` in the queue's types
  (`backend/app/Domain/Pos/Sync/PosSyncOperationType.php:16-31`).
- [F] The online rule is one method, `backend/app/Domain/Pos/Orders/PosOrderService.php:642-715`: kind
  `percent` or `amount`, a reason is mandatory, refused once a bill of the order is being paid
  (`paid_bill_affected`, `:663-669`), audited as `pos.order.discount` with old and new (`:703-708`), owner alert
  (`:710-712`).
- [F] Who may: `orders.discount` any discount; a cashier (`pos.take_payment`) up to the branch's
  `cashier_discount_max_percent` and only while no bill is printed; otherwise a manager approval token
  (`:755-777`, `:684-686`). An approval token is issued by the server, so it cannot exist offline.
- [F] The amount: percent -> items × value / 100 in **whole so'm, half-up**; amount -> whole so'm; never more than
  the dishes (`backend/app/Domain/Pos/Orders/PosChargesCalculator.php:166-183`). A percent is recomputed on every
  line change.
- [F] The till already has what it needs to mirror this: `PosMoney` is integer tiyin with half-up rounding
  (`pos/packages/pos_core/lib/src/money.dart:8-19,96-98`), bootstrap carries `cashier_discount_max_percent`
  (`pos_data/lib/src/bootstrap/bootstrap_models.dart:244,277-279`), and the offline order book already subtracts
  `order['discount']` before the service charge (`pos_data/lib/src/offline/offline_book.dart:615-617`), but only as
  a fixed amount that is always `0.00` for an order opened offline (`:282`).

### 1.3 The queue: integrity and identity

- [F] A queued operation is `{op_id, seq, type, occurred_at, staff_id, payload, origin_device?}`
  (`pos/packages/pos_sync/lib/src/operation.dart:139-147`). A journal line is `crc32 + space + JSON`
  (`pos_sync/lib/src/store.dart:366-369`). CRC32 detects a torn write; it is not a secret, so anyone who can edit the
  file can change an amount or delete a line and fix the checksum.
- [F] **Nothing is signed.** The batch is authenticated by the terminal's bearer token over TLS
  (`backend/routes/api_pos_sync.php:20-22`, `backend/app/Auth/DeviceTokenGuard.php:26-59`). The server trusts tender
  amounts and card `transaction_id` as sent (`PosSyncApplier.php:1223-1263`).
- [F] Replay protection exists and is enough: `op_id` unique across all stores, `(device, seq)` unique
  (`backend/app/Domain/Pos/Sync/PosSyncService.php:56-70`).
- [F] **Deleted lines are not detected.** The server stores `sync_last_seq` as a maximum (`PosSyncService.php:99`)
  and never compares the next `seq` with it, so a gap passes silently. The till never reads the `last_seq` the server
  returns (`PosSyncPresenter.php:85`; no use in `pos/`).
- [F] Clock: skew is computed from `sent_at` and applied above 300 s (`PosSyncService.php:49`,
  `PosSyncApplier.php:239-241`). Operations are hours old by design, so a signature cannot carry a freshness window.
- [F] Keys the till already holds: the terminal token and the LAN keys in `flutter_secure_storage`
  (`pos/apps/cashier/lib/main.dart:20,41`). The LAN layer already does HMAC-SHA256 in Dart with `package:crypto`
  and pins it to the PHP side with a shared test vector (`pos/packages/pos_lan/lib/src/crypto.dart:1-28`).
- [F] Dart crypto available without a new dependency: `crypto` 3.0.7 (`pubspec.lock:228-235`: SHA-256, HMAC). There is
  no `cryptography`, `pointycastle` or any Ed25519 implementation in the lock file. `pos_sync` itself has no
  dependency on `crypto` yet (only `pos_lan/pubspec.yaml:12`).
- [F] Pairing: an 8-character code creates the `Device` row and a Sanctum token with ability `terminal`, 365 days
  (`backend/app/Services/Auth/DevicePairingService.php:98-143`). Disconnecting sets `revoked_at` and deletes the
  tokens (`backend/app/Services/Auth/DeviceService.php:139-161`); the guard then answers 401 (`DeviceTokenGuard.php:43`).

### 1.4 Local storage and what is lost

- [F] One copy only: `<ApplicationSupport>/pos_sync/journal.log` plus `doc_*.json`
  (`pos/apps/cashier/lib/main.dart:29-32`, `pos_sync/lib/src/storage.dart:45-47`). Every line is flushed before the
  cashier sees "saved" (`storage.dart:96-107`). There is no export, no second copy and no restore path.
- [F] The server learns the size of a till's queue only when that till talks to it
  (`PosSyncService.php:97-111`). A till that goes offline and never returns leaves `sync_pending_ops` at its last
  online value, usually 0: the server does not know that anything is missing.
- [E] What is lost if the till's disk or app data dies with operations pending: every order, round and payment since
  the outage began, on that till and (if it is the LAN hub) from the waiters' phones and the KDS. The cash is in the
  drawer, the kitchen tickets and pre-checks are on paper, the fiscal receipts never exist. Nothing in the product
  helps the manager rebuild the shift.

### 1.5 Versions and updates

- [F] The apps carry a hard-coded constant, separate from `pubspec.yaml`: `AppConfig.version`
  (`pos/apps/cashier/lib/src/app_config.dart:22` `0.1.0`, `kds/...:24` `0.2.0`, `waiter/...:34`,
  `customer_display/...:23`); the pubspecs say `0.1.0+1` and `0.2.0+2`.
- [F] **The version reaches the server once, at pairing** (`apps/*/lib/src/shell|screens/pairing_screen.dart:47|63`
  -> `DevicePairingService.php:118`). Nothing updates `devices.app_version` afterwards: after the first app update
  every terminal shows its pairing-day version forever. `X-Rizqim-App` (which app) is sent on every request
  (`backend/app/Enums/DeviceApp.php:16`); a version header does not exist.
- [F] "Outdated" in the control centre is a guess: there is no release registry, the newest version is the highest one
  seen on any active device **of the same device type** (`backend/app/Services/Admin/Pos/PosDevices.php:15,101-131,
  182-195`). The cashier and waiter apps are both type `pos` (`DeviceApp.php:29`), so once their versions differ one
  of them is always "outdated".
- [F] No minimum version, no update notice, no updater, no download page. CI builds debug APKs for arm64 only and a
  debug Windows build (`.github/workflows/ci.yml:1405,1457`); there is no release job and no installer.
- [F] The Android release build is signed with the **debug key**
  (`pos/apps/cashier/android/app/build.gradle.kts:33-37`, the Flutter template's TODO). Android refuses to install an
  update signed with a different key: a till installed from such a build can only be moved to a properly signed one
  by uninstalling, which deletes the pairing and the journal.
- [F] The journal and the snapshots carry no format version (`store.dart:515-535`); compatibility across app versions
  rests on tolerant JSON parsing.
- [F] A new operation type is refused for the whole batch by request validation on a server that does not know it
  (`backend/app/Http/Controllers/Api/V1/Pos/PosSyncController.php:96`, HTTP 422), and the sender then retries the
  same batch forever (`pos_sync/lib/src/sender.dart:369-380`). So the server must always be deployed before the apps.

### 1.6 Bugs found in the current offline code (not fixed here; stage S1 fixes 1-4)

1. **Payment is blocked after 24 h offline** (owner rule). `limits.dart:136-145` + `pos_offline.dart:884-888,743`.
2. **A 401 on the terminal token deletes unsent operations.** `packages/dart/rizqim_api/lib/src/auth/auth_session.dart:287-292`
   emits `terminalRevoked` on any 401 for the terminal token (disconnected by the owner, or the 365-day token
   expired); `pos_data/lib/src/pos_data.dart:109-114` answers with `forget()`, and `pos_offline.dart:841-852` ->
   `store.dart:324-338` clears the queue. The comment says the queue "must be empty before disconnecting"; nothing
   checks it, on the till or in `DeviceService::revoke` (`DeviceService.php:139-161`).
3. **One bad journal line discards every line after it** (by reading). `store.dart:388-390` stops at the first line
   whose checksum fails and `:440-443` rewrites the file without the rest. That is right for a torn last line after a
   power cut. But when an append fails half-way and the app keeps running (disk full, I/O error), the next append is
   written straight after the partial bytes (`storage.dart:96-107`; the test double models exactly this,
   `storage.dart:197-201`), the merged line fails its checksum, and at the next start it and **all later confirmed
   operations** are dropped. The existing test restarts the app right after the tear (`test/store_test.dart:58-97`),
   so it does not cover this.
4. **The server rejects a payment when the staff check fails** (`PosSyncApplier.php:139-152`): PIN session older than
   36 h before the payment, or the permission was taken away meanwhile. The money is in the drawer and not in the
   books; it appears only as a rejected line in "Sinxronlash xatolari".
5. **A journal lost while the pairing survives blocks the queue for good** (by reading; [E] likely on Windows, where
   uninstalling leaves the secure-storage token). `seq` restarts at 1, the server answers 409 `seq_reused`
   (`PosSyncService.php:68-70`) for every batch, and the till never adopts the server's `last_seq`.
6. `devices.app_version` is never updated after pairing (1.5). Not a money bug; it makes every version report wrong.
7. "Outdated" is computed per device type, not per app (1.5).
8. Audit mismatch that is still open: the spec lets a hand-confirmed static QR work offline, the code refuses any
   tender but cash and card (`pos_offline.dart:750-756`, `PosSyncApplier.php:1231-1233`). Out of M5; left as is.

## 2. Proposal A: the 24-hour stop

**Rule.** The limit separates *starting* from *finishing*.

| after the limit (24 h without contact, or menu copy older than 24 h) | today | proposed |
|---|---|---|
| open a new order | blocked | blocked ("Internet kerak: yangi buyurtma ochilmaydi") |
| add a round to an order that is already open, send to kitchen, void an unsent line, mark served, weight | blocked | allowed, red banner; the server prices it as today (`offline_price`, `stale_menu` flags) |
| create the bill, print the pre-check | blocked | **allowed, always** |
| cash / card payment, close the order | blocked | **allowed, always** |
| offline discount (section 3) | n/a | blocked after the limit (a new concession on stale data; the sale can still be finished) |

- `OfflineStatus` gets `canOpenOrders` (today's `canSell` logic) and the guard takes the kind of operation;
  `createBill`, `pay` and the close that follows never consult the age limits. `canSell` is removed so no caller
  keeps the old meaning.
- The stored bootstrap copy stays usable after 24 h for an order that is already open (halls, fees, the open
  shift); only "is the copy fresh enough to *open* an order" uses the limit.
- **The numbers do not change** (24 h contact, 24 h menu, 500 orders, 48 h fiscal). The owner asked for hours; a longer
  window would only widen the price-drift and fiscal exposure. They stay server constants in bootstrap.
- **Server.** A `bill.pay` whose staff check fails for the session window or the permission is no longer rejected: it
  is recorded through the existing unallocated path (`PosSyncApplier.php:793-866`), attributed to the named staff
  member when that user exists in the store (else `received_by` null, the column is nullable), with a new conflict
  flag `staff_unverified`, an audit row and the manager's list. `staff_invalid` for a non-money operation and every
  relay rule stay as they are. Non-money operations keep being rejected.
- **App restart during an outage (owner decision 3).** Offered as a small addition, default yes: the till keeps the
  last signed-in staff member's id, name, permissions and PIN-login time in secure storage; after a restart with no
  server it offers "Davom etish: <name>" for at most 12 h after that PIN login, restricted to finishing open orders
  and taking payment (no new orders, no discount). No PIN hash is stored. The server's own check is unchanged (it
  already attributes by `staff_id` and the sign-in audit). The exposure equals an unlocked till, which limited mode
  already is (POS_SPEC 5.4: no auto-lock offline).

Rejected: raising the limits to 48-72 h (not asked for; section 9); making the limits a per-store setting (one more
owner setting to audit, no pilot need); offline PIN verification with hashes on the device (deliberately absent today,
`pos_lan` README section 9).

## 3. Proposal B: discounts offline

**Operation.** `order.discount`, payload `{order, kind: percent|amount, value: "10" | "50000.00", reason,
expected_discount: "12000.00"}`. Value `"0"` removes the discount. Decimal strings only; the till computes with
`PosMoney` (integer tiyin), the server with `App\Support\Money` (brick/math). Never relayable over the LAN (added to
the list in `PosSyncOperationType::relayable()`), not a money operation (`isMoney()` stays `bill.pay` only).

**Who and how much, offline.**

| actor | online today | offline |
|---|---|---|
| holds `orders.discount` | any | any, up to the optional offline cap (decision 1) |
| cashier (`pos.take_payment`) | up to `cashier_discount_max_percent`, before the bill | the same limit, from the bootstrap copy |
| anyone above their limit | manager PIN approval | **not available offline**; the button says so |
| remove a discount | `orders.manage` | the same |
| promo code, voucher, waive service charge / room fee, split | online | stay online |

- Only before the bill is created on the till (one rule for everybody offline; online a manager may still change it
  until a payment is taken). Not after the 24 h limit (section 2).
- A reason is mandatory, as online.

**Till.** `OfflineOrderState` keeps `discount_kind` / `discount_value` and recomputes the amount on every line change
exactly as `PosChargesCalculator::discountOf`: percent -> `items × value / 100` rounded to whole so'm half-up, amount
-> whole so'm, capped at the dishes, packaging never discounted. The bill's `expected_total` then matches the
server's, so no `amount_mismatch`. A shared vector file (owned by the backend agent, read by the Dart test) pins both
sides to the same results, as the LAN keys do today.

**Server.** `PosSyncApplier::discount()` calls `PosOrderService::discount($order, $order->version, $staff, $kind,
$value, $reason, null)`: every existing rule runs (limit from the branch's *current* settings, bill lock,
`paid_bill_affected`, the waiter rule), the existing audit row `pos.order.discount` and the owner alert fire. The
applier adds a second audit row `pos.offline_discount` (op id, device, device time, expected vs applied amount) so the
log shows it was given without a server at hand. `expected_discount` ≠ applied -> informational flag.

**When the server refuses** (limit lowered meanwhile, permission taken away, order changed): the operation is
rejected into "Sinxronlash xatolari" with `discount_not_allowed`. The guest already paid the discounted bill, and that
payment is never refused: `bill.create` is applied at the full price (informational `amount_mismatch`), `bill.pay` is
applied and the order is flagged `underpaid` by exactly the refused discount (`PosSyncApplier.php:739-744`). The
manager either gives the discount online with approval or settles it with the cashier. No new money path.

**Abuse surface [E].** A cashier with a till that is "always offline" can give their limit on every bill; so can they
online. The difference is that the owner alert arrives at sync time. Mitigations in the design: the alert text says
"internetsiz", the shift report counts offline discounts separately (one line; simple), and signing (section 4) ties
the operation to the device.

## 4. Proposal C: signing queued operations

**What it buys, honestly.** The till's journal is a plain file. Signing makes a change or removal of a queued line
*detectable on the server* unless the attacker also has the key from the device's secure storage (Android Keystore /
Windows DPAPI). It does not prove that the cash amount typed by the cashier is true, and it does not stop a modified
app that holds the key. It also gives the export file of section 5 its authenticity, which is the practical reason to
do it now.

**Scheme (no new dependency).**
- Key: 32 random bytes per device, generated by the server, returned **once** in the pairing response next to the
  token and kept in `flutter_secure_storage`. Server copy: `devices.sync_key` (Laravel encrypted cast), with
  `sync_key_id` (small integer), `sync_key_prev` (one earlier key) and `sync_chain` (the last verified MAC).
- Signed bytes: at enqueue the till serialises the operation **once** (`raw` = the JSON text it writes to the
  journal) and computes `mac = HMAC-SHA256(key, prev_mac || raw)`; `prev_mac` of the first operation is
  `HMAC(key, "rizqim-sync/v1/" + device uuid)`. The journal line stores `raw`, `mac`, `prev`, `kid`.
- Wire: each operation is sent as `{signed: raw, mac, prev, kid}`. The server verifies the MAC over the received
  bytes and then decodes `raw` and uses **that** as the operation. No canonical JSON is needed on either side (PHP and
  Dart never have to agree on key order or number formatting). An unsigned operation in today's shape is still
  accepted (old apps).
- Chain: `prev` must equal the device's `sync_chain`; then `sync_chain = mac`. A valid MAC with a different `prev`
  means lines are missing in between -> `chain_gap`. Independently the server starts checking `seq = last + 1`
  (a missing check today, 1.3).
- Hub relays: the hub signs what it writes into its own journal, relayed operations included (`origin_device` is
  inside `raw`). The origin device's own signature is not carried over the LAN in M5.

**What the server does with the result.** `pos_sync_operations` gets `sig_status` (`ok`, `missing`, `bad`,
`chain_gap`) and `mac`.

| mode | `missing` / `bad` on a non-money operation | on `bill.pay` | `chain_gap` |
|---|---|---|---|
| **observe** (first release, default) | applied, status recorded, RIZQIM alert on `bad` | applied, same | applied, alert to RIZQIM and the owner ("kassa navbatidan amal yo'qolgan") |
| **enforce** (per store, after all its tills run a signing version) | rejected `signature_invalid` | **recorded** as a conflict `payment_unverified` (unallocated path), never dropped | applied + conflict for the manager |

The batch is never blocked by a signature (a 4xx for the whole batch would freeze a till's queue, 1.5).

**Lifecycle.**
- Existing paired tills: `POST /v1/terminal/sync-key` (terminal token) issues a key when the device has none.
  Calling it again rotates: new key, `kid + 1`, the old one moves to `sync_key_prev` so operations queued under it
  still verify. The chain restarts at a rotation.
- Rotation by the owner: "Kalitni yangilash" on the device row (audited `device.sync_key_rotated`, the key itself
  never logged or shown). Automatic at re-pairing (a new device row).
- Disconnected devices: the key is **kept** for 30 days after `revoked_at` [E] so an export file from that till can
  still be verified and imported (section 5), then erased by the nightly job. Operations dated after `revoked_at` are
  refused on import.
- Replay: unchanged, `op_id` and `(device, seq)` uniqueness. Clock skew: unchanged; the MAC has no time window.
- PII: `raw` is the payload already stored in `pos_sync_operations.payload`; nothing new is logged.

Rejected: Ed25519 (server would hold only a public key, but it needs a new Dart dependency such as `cryptography`
or `pointycastle`; PHP has `ext-sodium`; kept as owner decision 2, default no); signing the whole batch only (does not
protect the journal at rest or an export file); a signature with a timestamp window like the LAN's ±2 min (operations
are hours old by design); mutual TLS per device (operations, not transport, are what must survive on disk).

## 5. Proposal D: a local backup at the till

**Layers.**

1. **Stop losing what is already on disk** (stage S1): bugs 2, 3 and 5 of section 1.6.
   - A 401 with operations pending never clears them: the journal is moved aside (`journal.<time>.orphan`) and the
     till offers "Navbatni faylga saqlash".
   - `DeviceService::revoke` refuses a terminal whose last report shows pending operations or an open shift unless
     the caller confirms (`force`), and says in plain words what will be lost (owner-facing; audited as today).
   - Loading skips a bad line in the middle and keeps the valid lines after it (the chain of section 4 then shows the
     gap); a failed append is repaired (file cut back to its last good length) before the next one.
   - The journal's meta line carries a format version and the till adopts the server's `last_seq` when it is higher
     than its own.
2. **A second copy on the device** (stage S4): every journal line is also appended to a mirror file outside the app's
   private folder. Windows: `Documents\RIZQIM\<device uuid>\` (survives uninstall and a profile clean-up of
   AppData). Android: the app's external files directory (`path_provider`, already a dependency). On start, if the
   journal is missing or shorter than the mirror, the till restores from the mirror (MACs verify it). [E] This covers
   corruption, an accidental "clear data" on Windows and bug 3; it does **not** cover a dead or stolen Android till,
   where both copies are on one flash chip.
3. **A signed export file** (stage S4): "Navbatni faylga saqlash" in the till's settings writes
   `rizqim-navbat-<device>-<first seq>-<last seq>.rzq` (header + the signed lines + a MAC over the whole file) to the
   mirror folder or, on Windows, to a chosen path such as a USB stick. Offered automatically when the outage passes
   2 h (`alert_after_minutes`) and before any "disconnect this till" action.
4. **Server import, the restore path** (stage S4): a manager or the owner uploads the file in the partner panel
   (business.rizqim.uz) or RIZQIM does it in the control centre: `POST /v1/pos/sync/import`. The server finds the
   device by the uuid in the header (it may be revoked or its token expired), verifies every MAC with the stored key,
   and applies the operations through `PosSyncService` **as that device**: the same idempotency (`op_id`), so a file
   imported twice, or after the till synced half of it, changes nothing. Audited `pos.sync_imported` (who, which
   device, how many operations, first and last seq). Needs signing (section 4): without it an uploaded file would be
   unauthenticated money.
5. **A printed offline summary** (stage S4, simple): on demand and at the 2 h alert the till prints one slip: outage
   start, number of orders and payments queued, cash total, card total, and the list of local numbers with amounts.
   It needs no server and no storage; when everything else is gone, the manager re-enters the shift from it and the
   drawer count.

**What is still lost [E].** A single Android till destroyed or stolen during an outage, with no export made: every
operation since the last contact. The printed summary (if printed) and the paper tickets remain. Closing this gap
needs a copy on a second device (section 9), which the narrow scope does not justify for outages of a few hours.

Rejected: encrypting the journal at rest (it holds no secrets; the keys are in secure storage); SQLite or another
store (the queue is small, POS_SPEC 5.2); mirroring to a waiter's phone or the KDS over the LAN (section 9).

## 6. Proposal E: versions and updates

**Reporting.** `RizqimClient` sends `X-Rizqim-App-Version: <version>+<build>` on every request, built from the same
constant the pairing screen uses (a CI check keeps `AppConfig.version` equal to `pubspec.yaml`).
`DeviceTokenGuard` writes it to `devices.app_version` inside the heartbeat save it already does at most once a minute
(`DeviceTokenGuard.php:51-54`), only when it changed: no extra write.

**Registry.** Table `pos_app_releases`: `app` (cashier, waiter, kds, customer_display), `platform` (android,
windows), `version`, `build`, `file_url`, `sha256`, `size_bytes`, `notes` (Uzbek, shown in the app), `released_at`,
`is_minimum` (versions below this one are unsupported), `minimum_from` (date the notice turns red), `withdrawn_at`.
Managed by RIZQIM staff in the control centre (admin.rizqim.uz), every change audited. "Latest" = highest
non-withdrawn version per app and platform; "minimum" = highest row with `is_minimum`. `PosDevices` reads "latest"
from here, per app, instead of guessing from the fleet (bugs 6 and 7).

**Manifest for pos.rizqim.uz.** `GET /v1/pos/releases` on the API: public, no auth, cached 5 minutes, throttled;
the list above without internal ids. pos.rizqim.uz is a static site (one more nginx service like the Mini App's
frontend): a download page in Uzbek that reads this manifest, and the files under `/files/<app>/<platform>/`. One
source of truth (the API); the site holds only files and one page.

**What a terminal is told.** `GET /v1/terminal` (terminal token, exists) gains
`app_release: {current, latest, minimum, state: ok | update_available | update_required, url, notes, minimum_from}`.
Apps read it at start and every 6 h. All four apps use it (the KDS and the display have no bootstrap).

**What the apps do. Nothing here can stop a sale.**

| state | cashier, waiter | KDS, customer display |
|---|---|---|
| `update_available` | a quiet line in settings and a dismissible banner on the home screen, once a day; never over an open order, a payment or a dialog | a small corner mark; the notice is on the cashier till |
| `update_required` (below minimum) | a permanent red banner; a full notice at app start, after shift close and on the shift-open screen, each with **"Davom etish"** that always works | the same red mark |
| any state | shift open/close, orders, bill, payment, receipts, refunds, the offline queue and its sync are **never** gated by version | |

- The "Yangilash" button is disabled with a reason while the till has queued operations or an order mid-payment
  ("avval navbat serverga yetsin"), so an update cannot interrupt money; it opens the download link.
- Android: the link is opened in the system browser, which downloads the APK and offers to install it over the
  existing app (data, pairing and journal are kept). Opening a link needs one small native method in the repo's own
  `pos_hardware` plugin (no new package; `url_launcher` is not in the lock file). Windows: `explorer <url>` through
  `dart:io`, then the installer, which must leave `%APPDATA%` and `Documents\RIZQIM` alone.
- The server side of "minimum": `POST /devices/pair` answers 426 `app_outdated` with the download link for a version
  below the minimum (pairing is setup). Owner-settings endpoints may do the same later. **Money and shift routes
  never check the version**; a test asserts that the version middleware is not on any `pos/*` money route.
- Below the minimum for more than 7 days [E]: the owner gets a Telegram notice naming the tills, and RIZQIM sees them
  in the control centre (the existing "outdated" filter, now correct).

**Releases (needs the owner, see decisions 6 and 7).** A tag `pos-v<version>` runs a release job: release APKs
(arm64 and, for Sunmi-class tills, 32-bit arm [E: to confirm on the pilot hardware]) and a Windows installer (Inno
Setup on the Windows runner [E]), with checksums, attached to a GitHub release. Publishing = copying the files to
pos.rizqim.uz and adding the registry rows. **The Android signing key must be created and used before the first
pilot install** (1.5): a till installed from a debug-signed build cannot be updated in place.

Rejected: a store (the brief: no store; Sunmi tills often have no Play services [E]); self-download and silent
install inside the app (needs `REQUEST_INSTALL_PACKAGES`, a FileProvider and per-vendor testing; a later step if the
pilot asks); blocking the till below the minimum (owner rule); a static `releases.json` on pos.rizqim.uz as the
source of truth (two places to edit, and the server needs the same data for the pairing check).

## 7. Stages and work breakdown

Order: S1 -> S2 -> S3 -> S4; S5 may run beside S1 and S2 (its files are disjoint from theirs) but not beside S3
(both touch pairing); S6 last. Every stage: server first, apps second, because a till must never send what the
server does not know (1.5). Each line below is one 1-2 hour brief; no two briefs of a stage share a file.

| stage | brief | owns (only these files) | kind |
|---|---|---|---|
| **S1 never block payment, stop losing the queue** | S1-a journal | `pos/packages/pos_sync/lib/src/{limits,store,storage}.dart`, `pos_sync/test/*` | money: Fable + 2 reviews |
| | S1-b till rules | `pos/packages/pos_data/lib/src/offline/pos_offline.dart`, `pos_data/lib/src/pos_data.dart`, `pos_data/lib/src/bootstrap/bootstrap_repository.dart`, their tests; `apps/cashier/lib/src/shell/limited_mode.dart` | money: Fable + 2 reviews |
| | S1-c server | `backend/app/Domain/Pos/Sync/PosSyncApplier.php` (staff check for money), `PosSyncService.php` (seq gap noted, `last_seq` in status), `app/Services/Auth/DeviceService.php` + the two device controllers (revoke with pending operations), tests, `docs/API.md` | money: Fable + 2 reviews |
| **S2 offline discount** | S2-a server | `PosSyncOperationType.php`, `PosSyncApplier.php` (`discount`), `backend/tests/Fixtures/offline_discount_vectors.json` (written first), tests, `docs/API.md`, `docs/POS_SPEC.md` 5.2 | money: Fable + 2 reviews |
| | S2-b till | `pos_sync/lib/src/operation.dart` (type), `pos_data/lib/src/offline/{offline_book,pos_offline}.dart`, `pos_data/lib/src/orders/order_repository.dart`, tests reading the vectors | money: Fable + 2 reviews |
| | S2-c UI | `apps/cashier/lib/src/features/order/discount_dialog.dart`, `.../order/order_controller.dart`, `.../billing/billing_page.dart`, widget tests | simple: Sonnet |
| **S3 signed queue** | S3-a server | migration (devices: `sync_key`, `sync_key_id`, `sync_key_prev`, `sync_chain`; pos_sync_operations: `sig_status`, `mac`), new `app/Domain/Pos/Sync/SyncSignature.php`, `PosSyncController.php`, `PosSyncService.php`, `DevicePairingService.php`, `TerminalController.php` (`sync-key`), a shared vector file, tests | security: Fable + 2 reviews |
| | S3-b journal | `pos_sync/pubspec.yaml` (`crypto`, already in the lock), new `pos_sync/lib/src/signature.dart`, `operation.dart`, `store.dart`, `sender.dart`, tests | security: Fable + 2 reviews |
| | S3-c key on the till | `packages/dart/rizqim_api` auth store (keeps the key), `pos_data` sync transport, the four `pairing_screen.dart` | security: Fable + 1 review |
| | S3-d enforce switch and alerts | new `app/Domain/Pos/Sync/SignaturePolicy.php`, alert wiring, control-centre device row (`platform/admin`), tests | security: Fable + 1 review |
| **S4 backup and rescue** | S4-a mirror and export | new `pos_sync/lib/src/{mirror_storage,export}.dart`, `apps/cashier/lib/main.dart`, tests | money: Fable + 2 reviews |
| | S4-b import | new `app/Domain/Pos/Sync/PosSyncImport.php`, route in `routes/api_pos_sync.php`, controller, tests, `docs/API.md`, `docs/OPERATIONS.md` (rescue runbook) | money: Fable + 2 reviews |
| | S4-c screens | cashier settings card "Zaxira" (new file), partner web upload page (`partner/web`), control-centre action | simple: Sonnet |
| | S4-d printed summary | new cashier printing file + its test | simple: Sonnet |
| **S5 versions** | S5-a server | migration `pos_app_releases`, model, admin endpoints, `GET /v1/pos/releases`, `TerminalController::show`, `DeviceTokenGuard.php`, pairing 426, `PosDevices.php`, tests | simple: Sonnet, 1 review by Fable for the guard and the "never on a money route" test |
| | S5-b client core | `packages/dart/rizqim_api` (header), new `pos_data/lib/src/release/*`, a shared banner widget in `pos_ui`, `pos_hardware` (open link) | simple: Sonnet |
| | S5-c apps | the four apps' shells (one brief per two apps), `AppConfig` version check in CI | simple: Sonnet |
| | S5-d site and release job | new `infra/pos-site/*`, compose service, `.github/workflows` release job, control-centre "Versiyalar" page, `docs/OPERATIONS.md` | simple: Sonnet |
| **S6 offline scenarios** | S6-a restart resume (if decision 3 = yes) | `pos_data/lib/src/session/pos_session.dart`, cashier login screen, tests | security: Fable + 2 reviews |
| | S6-b scenario suite | `e2e/pos/*` additions and `pos_data` scenario tests (section 7.1) | simple: Sonnet, reviewed by Fable |

Cost [E]: S1 3 briefs, S2 3, S3 4, S4 4, S5 5, S6 2 = 21 briefs; one PR per stage or one for S1-S2, one for S3-S4,
one for S5-S6. S1 alone is worth shipping during the pilot: it removes the rule violation and the three loss paths.

Rollback per stage: S1 is logic only (revert). S2: the till offers the offline discount only when bootstrap says
`offline.discount: true` (a server switch). S3: observe mode is the default and unsigned operations stay valid. S4:
the mirror is additive; import is a new route. S5: empty registry = no notices.

### 7.1 Test plan (the "offline scenario tests" of LAUNCH_PLAN)

Scenarios, each as a `pos_data` test against the fake backend and, where marked *, in the real-stack POS e2e job:
1. * Outage of 3 h: open, rounds, discount 10 %, bill, cash, close; sync; the server's bill equals the till's to the
   tiyin; one fiscal receipt; audit rows `pos.order.discount` and `pos.offline_discount`.
2. Clock moved 25 h forward with a table open: a new order is refused, the open table is billed and paid.
3. * Payment by a staff member whose PIN login is 37 h before the payment: recorded, flag `staff_unverified`, not
   rejected.
4. Discount above the cashier's limit offline: refused on the till. Limit lowered on the server during the outage:
   op rejected `discount_not_allowed`, payment applied, order `underpaid` by the discount.
5. Journal: a failed append followed by two good ones, restart: both good ones are there. A flipped byte in the
   middle line: the lines after it survive.
6. * 401 on the terminal token with 5 operations pending: nothing is deleted; export; import as owner; all 5 applied;
   a second import changes nothing.
7. * A journal line edited by hand (amount changed, CRC fixed): `sig_status = bad`; observe -> applied and alerted;
   enforce -> payment recorded as `payment_unverified`. A line removed: `chain_gap`.
8. Mirror: journal file deleted, restart: restored from the mirror, queue intact.
9. Versions: a till below the minimum opens a shift, sells, takes payment, refunds and closes the shift (asserts no
   426 anywhere on those routes); pairing with the same version answers 426; `devices.app_version` changes after a
   request with a new header.
10. Vector tests shared by PHP and Dart: discount rounding, HMAC chain.

### 7.2 Risks, ranked

1. **A till updates before the server** and sends `order.discount` or a signed envelope the server does not know:
   the whole batch is 422 and the queue stalls. Mitigation: server first in every stage; the till enables each new
   capability only when bootstrap advertises it (`offline.discount`, `offline.signing`).
2. **Till and server compute a discount differently by one so'm**: bills come back `underpaid`/`overpaid` and block
   shift close. Mitigation: shared vectors, scenario 1 in the real-stack job.
3. **Key and journal get out of step** (key rotated or lost while operations are queued): everything arrives `bad`.
   Mitigation: `sync_key_prev`, rotation refused by the till while its queue is not empty, observe mode first,
   money never dropped in any mode.
4. **The import endpoint is a new way to write money.** Mitigation: MAC over every line and the file, device must
   belong to the caller's store, operations after `revoked_at` refused, audited, rate limited, owner/manager
   permission only.
5. **Android signing key**: pilots installed from a debug-signed APK cannot be updated in place. Mitigation: owner
   decision 6 before the first pilot install.
6. Windows mirror folder is readable by any user of the PC (order contents, no secrets). Accepted; the MAC detects
   edits.
7. Fiscal: none yet (sandbox driver). When a real provider arrives its 48 h rule and its view of an offline-discounted
   receipt must be re-checked (section 10).

## 8. Owner decisions (plain words, with the recommended default)

1. **A ceiling for discounts given without internet?** Default: none beyond today's rules (a manager may give what
   they may give online; a cashier up to the branch's limit). Alternative: a separate "without internet" ceiling per
   branch, for example 20 %.
2. **How operations are signed.** Default: a shared secret key per till (no new software component). Alternative:
   a public/private key pair, slightly stronger if the server database leaks, needs one new library in the apps.
3. **If the till app restarts while the internet is down, may the same employee continue without a PIN** (only to
   finish open tables and take payment, for at most 12 hours after their last PIN)? Default: yes. Without it a
   restart during an outage leaves open tables that nobody can charge until the internet returns.
4. **When a signature is wrong.** Default: for the first weeks only record and warn; later, per store, refuse such
   operations except payments, which are always recorded and shown to the manager.
5. **Disconnecting a till that still holds unsent sales.** Default: the panel warns and asks for a second
   confirmation, and the till keeps its queue for export. Alternative: forbid it outright (a stolen till could then
   not be disconnected, so not recommended).
6. **Android signing key and who keeps it.** Default: RIZQIM creates one key now, stores it in GitHub Secrets with
   an offline copy held by the owner; no pilot till is installed from a build signed with any other key.
7. **Windows installer signing.** Default: start unsigned (Windows shows a warning once at install) and buy a
   code-signing certificate before the open launch.
8. **How long an old version is tolerated.** Default: 14 days from the day a version is declared the minimum until
   the notice turns red and the owner is messaged; the till is never stopped.
9. **Who publishes a new version.** Default: RIZQIM only, from the control centre, after the build passed on the
   pilot tills; stores cannot pin or skip versions.

### 8.1 Owner answers (19.11.2026)

All nine as recommended, with these additions:
1. No separate ceiling. Every discount given without internet is marked separately in reports, and once the connection
   is back the owner gets ONE summary message (how many, total amount, who gave them).
2. Shared secret per till (HMAC-SHA256), no new library.
3. Yes: continue as the last staff member, at most 12 h, only closing open tables and taking payment. Such operations
   are marked "PIN'siz davom etilgan" in reports.
4. Record and warn first; later refuse per store setting. Payments are never dropped.
5. Warn + second confirmation; the data stays on the till and can be exported to a file.
6. Create the real Android signing key NOW (no live store or installed till yet, so switching costs nothing today),
   together with the owner: one copy in GitHub Secrets, one offline with the owner. Secret values never go in chat.
7. Pilot unsigned on Windows (we explain the warning at install); certificate before open launch.
8. 14 days, then the red notice and an owner message; the till is never stopped.
9. RIZQIM team only, from the control centre.

Order: S1 first, as its own small PR that is quick to review. Every bug gets a failing test first; the journal bug
(found only by reading) is confirmed by a test before anything else. Remaining stages follow in plan order.

## 9. If the answer had been "a whole day without internet" (note only, not planned)

Decided against on 19.11.2026. For the record, it would add, on top of everything above [E]:
- Limits: contact and menu windows of 36-48 h as a store setting; the 500-order cap raised with journal compaction
  measured at several thousand lines; the 36 h staff window widened to match.
- Staff: PIN login offline (hashes or pre-issued grants on the device), because a day spans staff changes and
  restarts. This reverses a deliberate security choice.
- Shifts: open, close, X/Z and cash in/out offline, with the Z recomputed at sync. A second money engine in Dart.
- Catalogue and prices: stop-list and kazan shared between devices over the LAN; a rule for prices changed by the
  owner during the day.
- Backup: a copy on a second device (hub mirror on another till or phone) becomes necessary, not optional; the
  second till must become a LAN client instead of an island.
- Fiscal: a full day of sales with no receipt until sync, against the 48 h rule; depends on the provider allowing
  delayed receipts at all.
- Cost: this is option B of LOCAL_MODE_AUDIT section 8 (hub-first), roughly 3-5 waves, not stages of M5.

## 10. Not built, and why

- No longer offline window, no per-store limits, no offline PIN, no offline shift open/close, refund, split, voucher
  or QR: the owner asked for hours, and each of these is a second copy of a server money rule (section 9).
- No manager approval offline: an approval is a server-issued token; a local one would need PIN hashes on the till.
- No signature on LAN-relayed operations by their origin device, no end-to-end proof of cash amounts: the hub signs
  its journal; cash is counted at shift close as today.
- No encryption of the journal, no SQLite, no LAN mirror of the queue.
- No in-app silent installer, no store listing, no forced update, no version check on any money or shift route.
- No fiscal work: there is no provider (E-POS left the State Register on 30.10.2026; sandbox and null drivers only).
  Open for the P stage: whether the provider accepts a receipt for a sale made hours earlier with an offline
  discount, how its 48 h block interacts with a rescued (imported) queue, and what the printed offline slip may
  legally be called. Nothing in M5 depends on the answers; the discount and the import both end in the existing
  `BillFiscalizer` call.
- Bug 8 (static QR offline: spec and code disagree) is left for the owner to settle with the payment contracts.
