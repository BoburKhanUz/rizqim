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
  *As built (S6-a, 21.11.2026).* Kept: secure-storage key `rizqim.pos.staff_resume.v1` = `{v: 1, staff_id, name,
  permissions, signed_in_at}` (rizqim_api `StaffResumeRecord`, written at every successful PIN login on a till with the
  offline mode; no PIN, hash or token). Deleted on every sign-out (screen lock, shift end, 401 on the staff token, token
  expiry), unpair, forget, terminal 401, a new pairing, the end of the mode, and when unusable. Offered
  (`PosSession.resumeOffer`) only when: the kept bootstrap said `offline.staff_resume: true`, the last request failed
  (the PIN keypad reports its failed staff list), nobody is signed in, and `signed_in_at <= now <= signed_in_at + 12 h`
  by a device clock that is not behind the till's last server contact or newest queued operation (a PIN login in the
  future or a clock set back: the record is dropped). Resumed (`PosSession.resumeStaff`): no staff token (none is sent
  on this run), permissions cut to `orders.view`, `orders.manage`, `pos.take_payment`, `menu.view` (no discount, void,
  cancel, refund, shift, cash movement, reprint, unpairing), and finish-only: no new order, round or void, discount
  refused; the bill, the pre-check, the payment and the close go through. Every queued operation carries
  `staff_continued: true, staff_resumed: true`. It ends at the first server answer (`PosConnectivity` online: signed out,
  record deleted, PIN keypad with "Internet tiklandi"), at a PIN login, a sign-out, or when the 12 h are over (signed
  out, `resume_expired`). Server (`ContinuedStaff`): bootstrap `offline.staff_resume: true`; the order's informational
  `staff_continued` sync flag (once, plus one per `bill.pay` with its `payments`), the X/Z report's `continued:
  {count, amount}`, `staff_continued: bool` on the sync-error rows. The 36 h staff check is unchanged.
  *As built (S6 fixes F-till, 21.11.2026; supersedes the deletion and end rules above).* The record is deleted only at
  a PIN login (replaced by the new one), a sign-out the server hears (`DELETE` of the staff session answered, or 401),
  the 12 h end, the end of the shift (`PosSession.updateShift` from an open shift to none:
  `AuthSession.forgetResumableStaff`), unpair, forget, terminal 401, a new pairing, staff 401, a clock found set back,
  and when unusable. A lock, a sign-out or the idle lock while the server is out of reach keeps it, so the PIN keypad
  offers "Davom etish" again (also to a PIN-signed staff member who locked during the outage). The first server answer
  (any answer: a 502 or a captive portal counts) sends a resumed staff member to the PIN keypad (`resume_ended`) but
  keeps the record: when the link is found gone again before a PIN login, the offer is back. While resumed, every look
  at the staff member (`AuthSession.staff` / `staffResumed`, read before each action and each queued operation) ends
  the resume when the device clock is behind the highest time seen this run or the `notBefore` of the resume
  (`resume_clock`, record deleted), or when the 12 h are over by the device clock or by a monotonic stopwatch
  (`AuthSession.monotonic`) that counts from the resume what the device clock left of the 12 h then. The payment and
  the close that follows it are one action: who acts is read once at its start (`PosOffline._acting`), the end never
  falls between them. The open shift has its own small copy on the till (`PosOffline.rememberShift`, doc `shift`),
  written at every shift change and every server bootstrap, also while operations are queued (the bootstrap copy is
  not), and read back into the stored bootstrap after a restart: the shift opened after the PIN login is known. Safety
  net: a resumed staff member with no known shift still takes the payment; `bill.pay` is queued without `shift_uuid`
  and the server takes the till's current shift (`PosSyncApplier::openShiftOf`). The rule for the 12 h end is in ONE
  place, `AuthSession._resumeWindowOver` (open owner question Q1: today a hard end, signed out with `resume_expired`;
  the alternative "payment and close still allowed past 12 h, marked `staff_unverified`" is a change there plus the
  matching `PosOffline` guard and payload mark).

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

*What it does not buy (decision after the S3 reviews).* The signature protects **the journal on the till's disk**,
not against a stolen terminal token. Whoever holds the token can ask the server for a key
(`POST /terminal/sync-key`) and sign with it, and by rotating twice can push out the key the till's waiting operations
were signed with, turning them into `bad`. The server narrows this, it does not close it: a rotation is refused (409
`queue_not_empty`) while the queue the till last reported is not empty — but a till that is offline cannot report what
it queued since — and RIZQIM is alerted when a till's key is rotated more than once in a day. A stolen token is a
stolen terminal: the remedy is disconnecting the device (`revoked_at`), as for any other use of that token.

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

| mode | `missing` / `bad` on a concession (`order.discount`, `order.void`) | `missing` / `bad` on the sale itself (every other type) | on `bill.pay` | `chain_gap` |
|---|---|---|---|---|
| **observe** (first release, default) | applied, status recorded, RIZQIM alert on `bad` | applied, same | applied, same | applied, alert to RIZQIM and the owner ("kassa navbatidan amal yo'qolgan") |
| **enforce** (per store, after all its tills run a signing version) | **refused** `signature_invalid` (the discount as any refused offline discount, cause `signature_invalid`) | **applied** + conflict `signature_invalid` for the manager | **recorded** as a conflict `payment_unverified` (onto its bill, else unallocated), never dropped | applied + conflict for the manager |

The batch is never blocked by a signature (a 4xx for the whole batch would freeze a till's queue, 1.5).

*Enforce never drops a sale (decision after the S3 reviews; the first build rejected every unsigned or badly signed
operation that was not money).* Rejecting `order.open` threw the whole sale away: the bill had no order, the payment
that followed — even a perfectly signed one — was `order_not_found`, and the rejected rows kept the cashier from
closing the shift. So in enforce everything a sale is built of (`order.open`, `order.round`, `order.send`,
`order.served`, `order.course_send`, `order.weight`, `bill.create`, `bill.request`, `order.close`, the kitchen's
`kitchen.*`) is applied whatever its signature and shown to the manager as a conflict `signature_invalid`; a conflict
does not hold the shift. Only **concessions** are refused, the operations that make the guest pay less: the offline
discount and the void of a line. No other concession exists as a sync operation (an order is not cancelled, a charge
not waived and nothing refunded offline). The same reasoning for a journal line whose signed text no longer holds a
usable operation: it never refuses the batch (422 before); it is recorded rejected `invalid_payload` when it still
names itself, answered in `unreadable` without a row when it does not.

*As built (S3-d, `SignaturePolicy`):* the switch is `stores.pos_sync_enforce_signatures` (`PUT /owner/pos/sync-signing`,
audited; turning it on while a till does not sign yet asks for a second yes). `payment_unverified` goes the way of
S1's `staff_unverified`: onto its bill when the bill is open (so the order and the shift still close), unallocated
otherwise. The RIZQIM alert is the monitoring rule `pos_sync_signature` (per till, `bad` or `chain_gap` in the last
day; after the S3 reviews also a key rotated more than once in the window, and a till that signed under its
current key and then sent unsigned operations — audited `device.sync_signature_missing`, once a day per till); the
owner's `chain_gap` message is sent at most once per till in 60 minutes, counted from when the owner was last told.
Readiness (`GET /owner/pos/sync-signing`): a till counts as signing only once the server holds a verified operation
from it under its current key and its last operation was not unsigned — a key on the server proves nothing (every
till gets one at pairing, an old app too). Kill switch: `POS_SYNC_SIGNING=false` (`pos.sync_signing`) makes bootstrap
say `offline.signing: false` and treats every store as observe.

*Chain rules as built (`SyncChain`, after the S3 reviews).* The head (`devices.sync_chain`, `kid:mac`) is the last
operation whose own mac was right. (1) An operation under an older key than the head's is `chain_gap` whatever its
`prev`: the previous key cannot sign `ok` operations after the new key's first one. (2) `seq = last + 1`.
(3) Under the head's key `prev` must be the head's mac; a newer key starts from its own first prev. A `bad` operation
never moves the head; when its number follows, the mac it claims is accepted as the next `prev` beside the head (one
line edited in place is one `bad`); when its number does not follow it vouches for nothing and the removal before it
surfaces as `chain_gap`. A line replaced in place by other text under the same number, mac and prev cannot be told
from an edited line: one `bad`. A till rolled back to a build that does not sign and updated again starts from the
first prev of its current key; that is `ok` only straight after an unsigned operation with the number before it.

**Lifecycle.**
- Existing paired tills: `POST /v1/terminal/sync-key` (terminal token) issues a key when the device has none.
  Calling it again rotates: new key, `kid + 1`, the old one moves to `sync_key_prev` so operations queued under it
  still verify. The chain restarts at a rotation. The server refuses a rotation (409 `queue_not_empty`) while the
  queue the till last reported is not empty; a first key is always given.
- Rotation by the owner: "Kalitni yangilash" on the device row (audited `device.sync_key_rotated`, the key itself
  never logged or shown). Automatic at re-pairing (a new device row).
  *Not built in M5 (supervisor decision at S3-d):* the server keeps one previous key only, and a rotation the till
  did not ask for can turn its queued operations into `bad`. Only the till rotates (`POST /terminal/sync-key`, queue
  empty); the till learns the server's key number from bootstrap `offline.signing_kid`.
- Disconnected devices: the key is **kept** for 30 days after `revoked_at` [E] so an export file from that till can
  still be verified and imported (section 5), then erased by the nightly job. Operations dated after `revoked_at` are
  refused on import.
- Replay: unchanged, `op_id` and `(device, seq)` uniqueness. Clock skew: unchanged; the MAC has no time window.
- PII: `raw` is the payload already stored in `pos_sync_operations.payload`; nothing new is logged.

Rejected: Ed25519 (server would hold only a public key, but it needs a new Dart dependency such as `cryptography`
or `pointycastle`; PHP has `ext-sodium`; kept as owner decision 2, default no); signing the whole batch only (does not
protect the journal at rest or an export file); a signature with a timestamp window like the LAN's ±2 min (operations
are hours old by design); mutual TLS per device (operations, not transport, are what must survive on disk).

**As built on the till (S3 fixes, 20.11.2026).** A signed journal line keeps the decoded operation under `op` beside
`raw/mac/prev/kid/dev`, so an app rolled back to a pre-S3 build reads it as unsigned (this build always sends `raw`).
The acknowledged chain head is also kept in its own doc `_chain` (`{kid, mac, dev}`, never the key), so an old
build's compaction cannot drop it. Orphan files keep each operation's seal for the S4 import. A `bad` answer to the
till's own operation makes it fetch a new key (empty queue only, with backoff). Lines the server reports in
`data.unreadable` leave the queue and are kept as journal damage.

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
   where both copies are on one flash chip. (Corrected after the S4 review: the folder survives a clean-up of
   AppData, but the key and the pairing do not, so that copy can no longer be restored or sealed into a file; see
   "What the mirror does not cover" at the end of this section.)
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

**As built: the export file (S4-b, 21.11.2026; version 1).** The contract between the till's export (S4-a) and the
server's import (S4-b). Both are pinned to `backend/tests/Fixtures/sync_export_vectors.json` (written by an independent
script; no key inside, a test derives its keys from the seed), read by the PHP and the Dart tests.

- *File.* `rizqim-navbat-<device uuid>-<first seq>-<last seq>.rzq` (0 for a seq the header gives as null). UTF-8 text
  without a BOM. Every line is one JSON object written without spaces or line breaks and ends with LF (never CRLF):
  the header, then the body lines, then the trailer as the last line.
- *Header* (line 1), keys in this order:
  `{"format":"rizqim-sync-export","version":1,"device":"<uuid>","kid":<n>,"kids":[<n>,...],"first_seq":<n|null>,"last_seq":<n|null>,"count":<n>,"created_at":"<UTC ISO 8601>"}`.
  `device`: the till that writes the file (its current pairing), lower case. `kid`: the number of the key the file's
  MAC is made with. `kids`: the `kid` of every signed body line, each once, ascending. `first_seq` / `last_seq`: the
  lowest and highest `seq` of the header device's own lines (null when it has none). `count`: the number of body
  lines. A reader ignores header keys it does not know; the server acts on `format`, `version`, `device` and `kid`
  only and counts the body itself (the rest is for people and the file name).
- *Body.* One operation per line, in the shape `POST /pos/sync` takes it: a signed one
  `{"signed":"<raw>","mac":"<hex>","prev":"<hex>","kid":<n>}` with the journal's text, mac, prev and kid unchanged
  (never signed again for the file), an unsigned one (queued before the till had a key) as the operation itself
  `{"op_id","seq","type","occurred_at","staff_id","payload"[,"origin_device"]}`. An operation queued under **another
  pairing** (an orphan queue of S1 whose device uuid is not the header's) carries that uuid, lower case, as a last key
  `"dev"`: it stays signed under that device's key and the server applies it as that device. Order: the lines with
  `dev` first (by `dev` as text, then `seq`, then `op_id`), then the device's own (by `seq`, then `op_id`); one line
  per `op_id`. The server does not depend on the order (it sorts by device and `seq`) and answers a repeated `op_id`
  as a duplicate. Unreadable journal lines (damage) are not part of the file.
- *Trailer* (last line): `{"file_mac":"<64 lower-case hex>"}`,
  `file_mac = HMAC-SHA256(key, utf8("rizqim-sync-export/v1\n") || every byte of the file before the trailer line)`
  (the header and body lines, each with its LF), the key being the header device's key number `kid`. The label keeps
  a file MAC from ever being an operation's MAC. A LF after the trailer is written; a reader takes the file without
  it. Anything changed, removed or added before the trailer, a CRLF rewrite included, breaks the MAC.
- *JSON text*, so that both sides write the same bytes: as Dart's `jsonEncode` — keys in the order given, no spaces,
  only `"`, `\` and the characters below U+0020 escaped (`\n \r \t \b \f`, else `\u00XX`), `/` and non-ASCII
  left as they are. The server never re-encodes: it checks the bytes and reads each line with a JSON parser.
- *Never in the file:* a key. *Limits:* 8 MiB and 10 000 body lines (the till writes several files past that, each
  whole by itself, imported in `seq` order).
- *Refused whole* (422, nothing applied): not this format (`not_export_file`), a version other than 1
  (`version_unsupported`), too large (`file_too_large`), a device that is not a POS till (`not_a_till`), no key
  number `kid` held for the device — erased 30 days after it was disconnected, or pushed out by two rotations
  (`key_unavailable`) —, a wrong file MAC (`file_mac_invalid`). A device outside the caller's store: 404.

**As built: the import (S4-b, `PosSyncImport`, `SyncExportFile`).** `POST /v1/owner/pos/sync/import` (the partner
panel: `devices.manage`, never a PIN session; a branch-bound manager only their branch's tills) and
`POST /v1/admin/pos/sync-import` (the control centre: `admin.stores.manage`, step-up, a `reason`; any store), both a
multipart upload `file`. (The owner path is under `/owner/pos/` like the panel's other POS settings, not
`/v1/pos/sync/import` as first written above.)

- Once the file MAC is right nothing refuses the file: each line is answered by itself. The operations go through
  `PosSyncService::sync()` as their device, under the till's own lock, in batches of 50 in `seq` order, bound to the
  device's store: the same idempotency (`op_id`), signature and chain check (`sig_status`), store mode (observe /
  enforce: a payment is recorded, `payment_unverified`, never dropped) and owner messages as a live sync. A file
  imported twice, or after the till sent a part of it, changes nothing (`duplicate: true`).
- A line that holds no usable operation is answered in `skipped` (`invalid_payload`) or, when it names itself,
  recorded rejected, exactly as the sync endpoint does. A line whose `dev` is no POS till of the store is skipped
  (`device_unknown`); an `op_id` that belongs to another device is skipped (`op_id_conflict`).
- A number already taken by another operation of the device (the till lost its journal, went on from the server's
  `last_seq`, and an older file is imported): live, the till would be told `seq_reused` and renumber. There is no
  till here, so the import gives that operation and every later one of that device in the file the next free numbers
  (`renumbered_from` in its result; its mac is still checked over its own text, its place in the chain is not the
  till's: `chain_gap`). Money in such a file is not lost.
- A disconnected till: the file is taken while the key is kept (30 days after `revoked_at`). An operation dated after
  `revoked_at` (the till's clock; also one without a readable time) is not applied: recorded rejected
  `device_revoked` for the manager. A **payment** dated after it is still recorded, as the owner rule demands (the
  way of S1's `staff_unverified`: onto its bill when open, else unallocated; the flag's reason `device_revoked`).
- The request works for about 20 seconds; what is left is answered as `totals.pending` with `complete: false` and
  the same file is uploaded again (what was applied is answered as duplicates). The same when the till itself is
  syncing at that moment (`stopped_reason: sync_in_progress`).

**As built: the fixes after the S4 reviews (F1 backend, 21.11.2026).**
- Disconnected till (review R1 HIGH-1): the paragraph above is superseded. An operation dated after `revoked_at` (or
  without a readable time) is treated as enforce mode treats an unverified one (`suspect` on the operation,
  `PosSyncApplier::suspected()`): what the sale is built of is applied and marked a conflict `device_revoked`; a
  concession (`SignaturePolicy::CONCESSIONS`: `order.discount`, `order.void`) is refused (the discount as
  `discount_not_allowed`, cause `device_revoked`); a payment goes S1's `staff_unverified` way (recorded, conflict).
  Refusing the sale itself had dropped it and left its payment `order_not_found`. The origin terminal of a relayed
  operation is resolved first, exactly as in a live sync (a relayed payment is `relay_not_allowed` there and here; it
  is never written as the hub's).
- Old operations: one dated more than `PosSyncImport::HISTORY_MONTHS` (12, the prune of applied `pos_sync_operations`)
  ago may have lost its row, so its `op_id` no longer protects it: the same treatment, reason `operation_too_old`.
- `dev` lines (review S1): taken only for a **disconnected** POS till of the **header till's branch** (an earlier
  pairing), and only when the line's mac verifies under that device's key (`SyncChain::check()` is `ok` or
  `chain_gap`, checked before anything is applied); otherwise skipped (`device_unknown`, `signature_invalid`). The file
  MAC proves only that the header till sealed the file, never that another device wrote a line. Only the header
  till's reported queue is lowered.
- Lock (review S2): `Lock::block()` throws `LockTimeoutException`; `PosSyncService::sync()` turns it into the
  documented 409 `sync_in_progress` (a live sync too: it was a 500 after 10 s), the import into
  `stopped_reason: sync_in_progress`. The import's audit is written whatever stops it; an unexpected failure is
  audited with what was done (`stopped_reason: error`) and then rethrown.
- `PosSyncService::report()` reads `sync_last_seq` from the row (under the lock) so it never goes back when the caller
  held a stale device; an import (`sync(..., imported: true)`) leaves `sync_reported_at` alone.
- `warnings[]` in the answer: `earlier_numbers_missing` when the file's first own number is above 1 and the server
  holds nothing at `first_seq − 1` (part files uploaded out of order). Not a refusal. A re-paired till that went on
  counting gets it too (the server cannot tell), the message says what to check.
- Refused attempts after the header was read are audited `pos.sync_import_refused` (reason, header device, kid, count)
  in the journal of the store that tried.
- Known, documented only: after an import that renumbered operations, a live till that comes back renumbers its own
  queue (`adoptServerSeq`) and its first re-signed line is a `chain_gap` (one false owner alert). The runbook says to
  import only for a till that will not sync again. The mirror folder's privacy (Android ≤ 9 external storage,
  Windows Documents under OneDrive) is in docs/OPERATIONS.md §25.
- After the import the queue the till last reported (`devices.sync_pending_ops`) is lowered by what the import
  recorded, so a rescued till's shift can close. Audited `pos.sync_imported` on the device (who, counts, first and
  last seq, `complete`; never a key, a mac or a payload), plus the control centre's own `admin.pos.sync_imported`.
- Bootstrap: `offline.export` (true only while `pos.sync_signing` is on) tells the till the server takes such files.
- Known limit, unchanged from the live sync: the staff check measures the PIN sign-in against the operation's time
  kept within the last 48 h (`PosSyncApplier::deviceTime`, `SESSION_WINDOW_HOURS` 36). A file imported more than
  about 3.5 days after the cashier last signed in on that till has its sales rejected `staff_not_signed_in` and its
  payments recorded `staff_unverified`; the runbook tells support to import within three days.
- Open: a till that no longer starts cannot write the file (its key is in that device's secure storage, and the
  mirror holds journal lines, not a sealed file). Rescuing such a disk needs a server-side path that trusts the
  per-operation MACs alone; not built (docs/OPERATIONS.md §25 sends it to the developers).

**As built: the fixes after the S4 reviews (F2 till, 21.11.2026).**
- Restore from the mirror (review R1 MEDIUM-2): no longer gated on `offline.export`. The flag was read from the
  bootstrap copy in the journal's own folder, which is gone in exactly the case the mirror is for, so the restore was
  off and the mirror-only payments were set aside where nothing read them. Now every line whose MAC verifies under the
  till's key is put back (it is the till's own line; the server takes an `op_id` once). A line that cannot be verified
  (unsigned, another key, changed on the disk) is never put into the journal and never removed from the mirror's
  folder (`mirror_kept_<time>` document); a later start puts it back once it verifies, and only then takes it out of
  that document. `MirroredSyncStorage.setAside` (the kept lines plus the mirror's own copies of the queues set aside,
  `orphan_*`) goes into every export file through `SyncExporter`, after the journal's queue and the queues the app
  gives (one line per `op_id`), so those payments still reach the server by an import.
- A mirror write that does not answer holds a journal append (a sale) up for at most 2 s
  (`MirroredSyncStorage.writeTimeout`). The journal is written first and decides; while a mirror write is out, later
  lines are not queued behind it, and once it answers the mirror is rewritten whole from the journal (no line lost, no
  order changed). The start-up restore waits at most 15 s a step for the mirror (`restoreTimeout`), then the till
  starts from the journal alone and the mirror is not written in that run.
- Export file names: a file name already in the folder is never overwritten (an empty queue, or a queue of earlier
  pairings alone, is always `-0-0`): the new file gets `-2`, `-3`, ... before `.rzq`. The name inside the format
  (vectors, `SyncExportFormat.fileName`) is unchanged; the server reads the header, not the name.
- Printed summary: tips (`tip_amount`; with `tip_from_change` and no `tip_amount`, the whole change) are their own
  lines ("Choy puli", by tender) beside the bill totals, with "Kassadagi naqd" (cash + cash tips) and "Terminal
  (karta)" (card + card tips), so the drawer count and the bank terminal's total can be matched.
- **What the mirror does not cover [E], Windows.** The till's secure storage (`flutter_secure_storage` on Windows: a
  DPAPI file `flutter_secure_storage.dat` in the app support folder under `%APPDATA%`) holds the terminal token, the
  pairing and the sync key. Wiping AppData (a profile clean-up, "reset the app", a new Windows profile) therefore loses
  the key and the pairing together with the journal: the till starts unpaired, the mirror is not consulted (there is
  no key to verify it with, and a till paired again gets a new device uuid, so a new mirror folder), and no export
  file can be sealed for the old device (the file MAC needs that device's key, and a DPAPI file copied to another
  profile or PC cannot be decrypted either). The mirror folder `Documents\RIZQIM\<old uuid>\` stays on the disk with
  the signed lines (the server still holds the old key for 30 days after the disconnect), but nothing imports it
  today: that needs the server-side path above that trusts the per-operation MACs alone (not built). Until then a
  wiped AppData is rescued from the printed summary and the drawer count, like a dead till. The mirror covers what
  leaves the secure storage in place: a damaged, cut or deleted journal (`pos_sync` folder), bug 3, a failed update.
  On Android "clear data" and an uninstall remove the external files directory, the journal and the Keystore entry
  alike; the mirror there covers journal damage only.

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

**Where each scenario lives (as built, S6-b).** `pos_data` files are in `pos/packages/pos_data/test`, `pos_sync` in
`pos/packages/pos_sync/test`, PHP in `backend/tests`, the real stack in `e2e/pos/test`. "Fully" means the scenario as
worded is proved at that level; nothing is duplicated, a row names the test that proves each part.

| # | Fake backend (Dart) | Server (PHP) | Real stack (`scripts/pos-e2e.sh`) |
|---|---|---|---|
| 1 | `m5_s2_discount_test.dart`: "scenario 1: a cashier gives 10 % offline ..." (open, round, discount follows the round, bill, cash, close, sync; the till's total is the server's to the tiyin, `offline_discount` flag in refs) | `Feature/PosSync/PosSyncOfflineDiscountTest::test_an_offline_discount_is_applied_audited_and_the_bill_matches_the_tills` (both audit rows) | `offline_scenarios_test.dart`: "scenario 1 ..." (sync request read from the `signed` text, order, bill and payment equal the till's, one fiscal receipt, `pos.order.discount` and `pos.offline_discount` rows in the owner's audit log) |
| 2 | `m5_s1_test.dart`: "bug 1: ... after 25 h without contact a new order is refused; a round, the bill, a cash payment and the close ... work"; `pos_sync` `limits_test.dart`: "M5 bug 1: the 24-hour limits stop only OPENING a new order" | - | - |
| 3 | `m5_s1_test.dart`: "bug 4 on the till: a payment taken 37 h after the PIN login ..."; `m5_s1_fix_test.dart` (12 h, no PIN) | `PosSyncStaffUnverifiedTest::test_a_payment_37_hours_after_the_pin_login_is_recorded_as_staff_unverified` (moves the server clock 37 h), `PosSyncStaffUnverifiedSettleTest` | `offline_scenarios_test.dart`: "scenario 3 ..." (the server clock cannot be moved on the stack, so the stack proves the same check for a cashier with no PIN session on the till inside the 36 h window: payment conflict `staff_unverified`, bill paid, order closed, audit `pos.offline_payment_staff_unverified`) |
| 4 | `m5_s2_discount_test.dart`: "scenario 4a ..." and "scenario 4b ..." | `PosSyncOfflineDiscountTest::test_a_refused_discount_is_rejected_and_the_bill_and_the_payment_are_still_taken` | - |
| 5 | `pos_sync` `journal_damage_test.dart`: "two good operations written after a failed append survive a restart", "a flipped byte in a middle line: the later lines survive" | - | - |
| 6 | `m5_s6_scenarios_test.dart`: "scenario 6 ..." (5 queued, 401, every op still on the disk, the file holds all 5 and verifies under the key); `m5_s1_test.dart` "bug 2: a 401 on the terminal token"; `pos_sync` `export_test.dart` | `PosSyncImportTest::test_a_file_is_applied_as_its_till_and_audited`, `test_the_same_file_twice_changes_nothing`; `SyncExportVectorsTest` | `offline_scenarios_test.dart`: "scenario 6 ..." (5 ops queued, owner disconnects the till, 401, nothing deleted, the till is paired again and writes the file, the owner imports it, second import changes nothing) |
| 7 | `m5_s6_scenarios_test.dart`: "scenario 7 ..." (amount changed, CRC fixed: `bad`, observe applies, enforce `payment_unverified`; a removed line: `chain_gap`); `m5_s3_fix_test.dart` chain tests | `PosSyncSignatureTest::test_a_line_edited_by_hand_is_bad_and_in_observe_mode_still_applied`, `test_a_removed_line_is_a_chain_gap_...`; `PosSyncSignatureEnforceTest` | `offline_scenarios_test.dart`: "scenario 7 ..." (three tests: observe, enforce, removed line; the owner switches enforce on and back) |
| 8 | `pos_sync` `mirror_storage_test.dart`: "restoring on start: a lost journal gets back every verified operation from the copy" (queue and chain intact), `mirror_review_fix_test.dart` | - | - |
| 9 | - | `PosReleases/PosReleaseRouteInventoryTest` (no 426 on any till route; a whole sale below the minimum: shift, bill, payment, refund, close, sync), `PosReleaseTerminalTest` (pairing 426, `devices.app_version` follows the header) | - |
| 10 | `m5_s2_discount_test.dart` "shared vectors", `pos_sync` `signature_test.dart` "the shared vectors", `export_test.dart` "vector file" | `OfflineDiscountVectorsTest`, `Unit/PosSync/SyncSignatureVectorsTest`, `SyncExportVectorsTest` | - |

Notes. Scenario 1 does not wait three hours: the length of the outage changes nothing the till computes (the 24 h
limits have their own scenario 2). The real-stack file pairs a till of its own per scenario (the owner's pairing-code
route lets 5 through a minute, so the file waits for the limit once) and never touches the seeded tills.

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

Owner decision (20.11.2026, after the S2 security review showed that a till token alone could give an
unlimited discount in a manager's name): without internet EVERYONE, managers included, gives at most the branch's
cashier discount limit; larger discounts wait for the connection. Answer 1 above is narrowed accordingly.

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
