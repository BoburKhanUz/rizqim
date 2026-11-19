# M4 "Tenant isolation": design (cross-store tests, roles, automatic scope, RLS)

Status: design at commit fd15d9a (2026-11-18); stages 1-3 are built (stage 3: section 4.3 "As built"). Postgres
behaviour below was checked on throw-away databases `m4_design` built by `php artisan migrate` from the real migrations on PG 16.15 (port 5432) and
PG 17.12 (port 5433); the PG 17 copy was filled with 1 000 stores, 1 100 branches, 1 000 000 orders (Zipf-like skew,
largest store 41 469 orders), 3 000 000 order_items, 1 000 000 order_status_histories and 1 000 000 payments. Test roles
`m4_*`, both databases and a throw-away PgBouncer 1.x (transaction mode) were removed afterwards.
Labels: **[F]** = fact from code/config (file:line), **[V]** = verified by SQL on `m4_design` (PG 16 and 17 unless one is
named), **[E]** = estimate. Paths are relative to the repo root; backend paths are under `backend/`. One recommendation per
decision; rejected alternatives in one line each.

## 0. Summary of decisions

| # | Decision |
|---|---|
| A | Of 126 tenant tables, 93 carry `store_id` directly, 2 `branch_id` only, 31 reach the store through one or two parents. Add `store_id` to **`payments` only** (money, addressed directly by uuid and provider id); the other 30 stay reached via their parent. Platform (33), user-owned (3), RIZQIM-private rows about a store (6) and the fiscal key registry get no tenant filter |
| B | Scoping today is explicit and consistent ("resolve, then `allows()` / `where store_id`"). 49 sampled endpoints: **1 real cross-tenant hole (medium)**, 2 existence oracles (low), 0 data leaks. The guarantee rests on ~100 hand-written isolation test files, not on a route-complete check |
| C | First line = a **generated cross-tenant route test** over every tenant route (stage 1), then a `BelongsToStore` global scope that is active only while a per-request `TenantContext` is bound by route middleware on tenant route groups (stage 2); jobs keep carrying `storeId` explicitly; no ambient tenant in console, queue, webhooks, admin, marketplace |
| D | RLS on ~20 high-value tables with a plain equality policy, enforced only for a NOLOGIN role `rizqim_tenant` that a tenant request switches to with `SET LOCAL ROLE` in the first message of each transaction (writes mode). Everything else runs as the login role with `BYPASSRLS`, so a missing setting never fails a till. Read statements outside transactions stay app-guarded unless a measured run shows wrapping them costs ≤ 10 % |
| E | Three roles: bootstrap superuser (postgres container, backup, restore drill only), `rizqim_owner` (LOGIN, owns every object; migrations and the two partition commands), `rizqim_app` (LOGIN, DML, `pg_monitor`, `BYPASSRLS` from stage 3; php-fpm via PgBouncer, queue, scheduler, Reverb). Created and kept in shape by an idempotent `db-roles` one-shot compose service |
| F | Stage 1: route suite + leak fixes + role split. Stage 2: `TenantContext` + global scope + `payments.store_id`. Stage 3: RLS writes mode. All three before the OPEN launch; stage 1 can ship during the pilot |
| G | Stage 1 = four agents (roles/infra, owner connection in PHP, test world + generated suite, fixes) with disjoint files; 6 owner decisions with defaults in section 7.4 |

## 1. Inventory (A)

[F] `information_schema` on `m4_design`: 169 tables (partitions not counted). Of them 59 have `store_id`+`branch_id`, 41
`store_id` only, 2 `branch_id` only, 67 neither. M2's `RevisionMap::TABLES` (`app/Domain/Realtime/RevisionMap.php:101-288`)
lists every table; its classes map to tenancy as follows:

| RevisionMap class | tenancy class | differences |
|---|---|---|
| BRANCH, STORE, STORE_BRANCHES (79, incl. `orders` and `store_ratings` with an extra USER rule) | tenant, direct `store_id` | equal |
| VIA (34, incl. `order_assignments`, `complaints`) | tenant, through the parent named in the rule | `order_bills` (`:106`) and `pos_qr_refund_attempts` are VIA but carry `store_id` themselves; `payments` (`:115`): the stage 2 column; `branch_product_variants`/`_rotation` carry `branch_id` (store via branches, depth 1) |
| USER (3: `addresses`, `notification_logs`, `notification_preferences`) | user-owned, platform | a customer orders from many stores: `notification_logs.order_id` points into a store but the row is the customer's |
| ALL_STORES, FEATURE_SCOPE (7) | platform reference | equal |
| NONE (45) | mixed: see below | RevisionMap NONE says "no screen polls it", not "not tenant data" |
| `users` STORE_BRANCHES + USER (`:230`) | **mixed**: staff rows tenant (`store_id` set), customers and RIZQIM team platform (`store_id` NULL) | no RLS, no scope on `users` (section 3.2) |

NONE tables that are tenant data with direct `store_id`: `audit_logs`, `store_transactions`, `store_wallet_transactions`,
`menu_price_history`, `device_pairing_codes`, `payout_requests`, `store_status_logs`, `subscription_dunning_events`,
`finance_adjustments`, `store_topup_requests`. Platform-private rows *about* a store (RIZQIM CRM, never shown to the store):
`store_notes`, `store_tasks`, `store_crm_profiles`, `store_health_scores`, `store_applications`, `alerts`. Registry:
`fiscal_receipt_keys` (`store_id`, but global uniqueness across stores is its purpose, M3 A2: never filtered).
Nullable `store_id` meaning "platform-wide": `promos`, `promo_codes`, `alerts`, `audit_logs`, `devices` (customer push
devices), `users` [V `attnotnull`].

Tenant tables without a direct `store_id` (31), parent and depth [V FKs]:
- via `orders` (depth 1): `order_items` (4 rows/order), `order_status_histories` (1-2), `payments` (1), `order_assignments`,
  `order_charge_waivers`, `order_packaging_removals`, `order_sla_events`, `order_status_logs` (legacy), `telegram_actions`,
  `complaints` (also `user_id`). Depth 2: `order_bill_items` (bills -> orders, 4/order), `complaint_replies`.
- menu: `product_variants`, `product_modifier_groups`, `product_ingredients` (products), `modifiers` (modifier_groups),
  `combo_slots` -> `combo_slot_options` (combos, depth 2), `daily_menu_items`, `daily_set_items`.
- POS: `booking_items`, `booking_tables` (bookings), `banquet_event_halls/_lines/_staff` (banquet_events),
  `banquet_package_items`, `banquet_payment_reminders`, `pos_tip_distribution_lines`, `staff_pay_statement_lines`,
  `external_refs`, `integration_events` (integration_connections).

Every one of them is read and written through its parent in code (sampled in section 2); none is addressed by an id from
the request without first resolving the parent, **except `payments`**: refunds take a payment uuid
(`PosBilling/PosRefundController.php:52`), webhooks find payments by `provider_transaction_id`
(`Payment/PaymeWebhookController.php:695-705`), four tables reference it (DB_SCALE_PLAN section 1). Hence:

**Decision A: add `payments.store_id` (stage 2) and nothing else.** Migration [V on 1 000 000 payments, PG 17]: `ADD COLUMN
store_id bigint` 0.9 ms; backfill `UPDATE payments p SET store_id = o.store_id FROM orders o WHERE o.id = p.order_id` in
batches of 50 000 ids, 0.68 s per batch (13.5 µs/row, row locks only on the batch); `ADD CONSTRAINT ... CHECK (store_id IS
NOT NULL) NOT VALID` 5.7 ms, `VALIDATE` 171 ms (SHARE UPDATE EXCLUSIVE, writes continue), then `SET NOT NULL` 0.5 ms (uses
the validated check). **Built (stage 2): no index on `store_id`**: EXPLAIN on 1 M payments (an order's payments, a shift's report, the finance-history list for the largest and the smallest store, by uuid) shows the store predicate only as a filter on the existing order/shift/uuid/pkey index paths, never slower; the index (`CREATE INDEX CONCURRENTLY`, 0.44 s) waits for a query that needs it, and stage 3 re-measures with the RLS policy on. Writes: 8 creation sites [F grep `Payment::create`:
CreateOrderAction, PaymentService, PosSyncApplier, BookingDepositService, BanquetSchedule ×2, Click and Payme webhooks];
a `BEFORE INSERT` trigger `payments_fill_store` sets `NEW.store_id` from `orders` (or from `pos_shifts` when
`order_id` is NULL: `payments.order_id` is nullable [V]; the implementer counts such rows on the pilot first) and raises
when it cannot. No composite FK `(order_id, store_id)`: it needs a new UNIQUE `(id, store_id)` on orders (21 indexes
already) for a consistency the trigger gives at insert.
Rejected: `store_id` on all 31 children (≈31 migrations + triggers + index writes on the two biggest growth tables, for
rows that are never addressed without their parent); a VIA policy `EXISTS (select 1 from orders ...)` instead of the
column [V: order_items read +49 %, insert +25 %, section 4.4].

M3 partitioned tables: `audit_logs` and `fiscal_receipts` already carry `store_id` (`audit_logs.store_id` nullable for
platform actions); `notification_logs` is user-owned; `fiscal_receipt_keys` (non-partitioned) stays unfiltered. Nothing to
add there.

## 2. How the application scopes today (B)

[F] Actors and how the store is derived:
- Staff (owner, manager, cashier, waiter, cook, courier) = one `users` row with one `store_id` and optional `branch_id`;
  there is no multi-store owner (no `owner_id` on `stores`, no pivot) [F `information_schema`, `app/Models/User.php:287`].
  `EnsureStaffHasStore` refuses storeless staff (`app/Http/Middleware/EnsureStaffHasStore.php:17-27`).
- Acting branch: `PermissionService::actingBranchId()` (`app/Services/Auth/PermissionService.php:132-150`) = the staff
  member's branch, or the paired terminal's branch for a PIN session, or `NO_BRANCH` (fails closed). `allows()`
  (`:79-107`) checks `subject.store_id == user.store_id` and the acting branch. `StaffOrderScope`
  (`app/Services/Order/StaffOrderScope.php`) wraps it for orders.
- POS PIN session = a Sanctum token with `device_id` (`StaffPinService.php:179-193`); the terminal must be of the user's
  store (`PermissionService.php:165-185`). Device token (`auth:device`) = `devices.store_id/branch_id`; routes compare the
  path uuid with the token's device (`Device/TerminalController.php:145`, `:189`).
- Admin = separate `auth:admin` guard (`routes/api_admin.php:38`); store, customer and terminal tokens never pass it.
- Customer = `user_id` only; marketplace routes are public-by-store.
- No global scopes (only OrderItem's combo scope), no route-model binding of tenant models (DB_SCALE_PLAN section 2).
  Pattern: load by uuid, then `allows()`/`store_id` check; or `Model::where('store_id', $store)->find($id)`.

**Sample (49 endpoints and paths, every id from the request traced to its check):**

| area | endpoint / path | id from request | check (file:line) | result |
|---|---|---|---|---|
| POS | `/pos/orders/{uuid}/*` (hall) | order uuid | `PosHall/PosHallController.php:85-88` allows | OK |
| POS | `/pos/bills/{uuid}/payments`, `/print`, `/qr`, `/fiscal-retry` | bill uuid | `PosBilling/PosBillingController.php:68-77` bill -> order -> allows | OK |
| POS | `/pos/shifts/{uuid}/x-report`, `/bank-report` | shift uuid | `PosBillingController.php:79-88` `store_id` + branch | OK |
| POS | `/pos/fiscal-receipts/{uuid}` | receipt uuid | `PosBilling/PosFiscalReceiptController.php:33-35` | OK |
| POS | `/pos/refunds` | payment uuid | `PosRefundController.php:52-53` payment -> order -> allows | OK |
| POS | `/pos/orders/{uuid}/move` | table id (body) | `PosOrderService.php:447` same branch | OK |
| POS | `/pos/orders` open | table id, variant ids, modifier ids | `PosOrderService.php:104` (branch), `PosLineBuilder.php:122,482` (store) | OK |
| POS | held lines | modifier ids | `PosOrderService.php:1522` unscoped id->uuid map, then `OrderLineBuilder.php:270-293` product rules | OK (re-checked downstream) |
| POS | `/pos/queue/*` | order uuid | `Pos/PosQueueController.php:212-214` | OK |
| POS | `/pos/hostess/waitlist/{uuid}` | entry uuid | `PosHall/PosWaitlistController.php:245-247` | OK |
| POS | `/pos/bill-print-requests/{id}/printed` | request id | `BillPrintInbox` claim: `store_id`+`branch_id` of the device | OK |
| POS | `/pos/bookings/{uuid}/deposits/{payment}/*` | booking, payment | `Pos/BookingController.php:528-529` | OK |
| POS | `/pos/bookings/{uuid}/items/{item}` | modifier ids | `Booking/BookingItemService.php:145` map, then rules | OK |
| POS | `/pos/banquets/{uuid}/payments/{row}`, `/transfers/{entry}` | row, entry | `Pos/BanquetPaymentController.php:120,165,197` via event | OK |
| POS | `/pos/customer-accounts/{uuid}/members/{member}` | member uuid | `Pos/CorporateAccountController.php:154` | OK |
| POS | voucher redeem | code | `PosBilling/PosVoucherController.php:89` `store_id` | OK |
| POS | `/pos/tips` | shift/branch | `PosBilling/PosTipsController.php:69-77` branch | OK |
| POS | `POST /pos/sync` (offline) | staff id, table, order/bill/shift uuid, origin device | `Sync/PosSyncApplier.php:300-305, 382, 931, 1079-1081, 1123-1128, 1185, 1202` | OK |
| staff | `/staff/orders/{uuid}`, `/items/{item}/ready` | order, item | `Order/StaffOrderController.php:547, 623-626` | OK |
| device | `/devices/{uuid}/staff-session` | device uuid, user id | `TerminalController.php:145`, `StaffPinService.php:208-210` (eligible staff of the device's store) | OK |
| device | `/devices/{uuid}/push-token` | device uuid | `Device/DeviceController.php:82,97` `user_id` | OK |
| owner | `/owner/employees/invite`, `/{id}/transfer` | branch id | `Store/OwnerEmployeeController.php:64-66, 125-129` | OK |
| owner | `/owner/branch-variants` | branch, product, variant | `Catalog/BranchProductVariantController.php:115-131` | OK |
| owner | `/owner/products/{id}` variants | variant ids | `Store/OwnerCatalogController.php:257-258, 322` | OK |
| owner | `/owner/staff/{id}`, `/pin`, `/role` | user id | `Management/StaffController.php:111`, `Store/StaffAccessController.php:98,216` | OK |
| owner | `/owner/pos/tips/{uuid}/corrections`, distributions | uuid | `Management/TipsController.php:276,287` | OK |
| owner | `/owner/fiscal/receipts/{uuid}` (+retry) | uuid | `Fiscal/FiscalReceiptController.php:190` | OK |
| owner | `/owner/fiscal/devices/{uuid}` | device uuid | `Fiscal/TerminalFlowController.php:196` | OK |
| owner | `/owner/staff-pay/statements/{uuid}/lines/{line}/pay` | line, shift uuid | `StaffPay/StaffPayStatementController.php:243,256` | OK |
| owner | `/owner/branches/{branch}/packaging-rules` | product id | `Management/BranchPackagingRulesController.php:160` | OK |
| owner | brand products | product ids | `Pos/Brands/BrandService.php:174-176` | OK |
| owner | combos, meal sets | variant ids | `Pos/Menu/Combos.php:520-521`, `MealSets.php:150-152` | OK |
| owner | every report/export with `branch_id` (sales, channel revenue, vouchers, banquets, export) | branch id | `Management/ManagementController.php:36-63` branchFilter | OK |
| owner | `/owner/reports/kitchen-times` | station id | `Management/PrepTimeReportController.php:56` | OK |
| owner | `/owner/devices` pairing | branch id | `Device/StoreDeviceController.php:119-121` | OK |
| owner | `POST /reports/export` (queued) | none: job gets `storeId`, `branchId` | `Jobs/SendOrderReport.php:21-23` | OK |
| customer | `POST /orders` | store, branch, variant uuids | `Actions/Order/CreateOrderAction.php:150-151, 228-232` | OK |
| customer | `/orders/{uuid}/cancel` | order uuid | `Order/OrderController.php:388-394` `user_id` | OK |
| staff | `/orders/{uuid}/assign-courier` | courier id | `Services/Order/OrderStatusService.php:395` assignable staff of the store | OK |
| customer | `/promo/validate` | code, store id | `Services/Promo/PromoCodeService.php:43` `isValidForStore` | OK |
| customer | **`POST /stores/{id}/rate`** | store id, order id | `StoreRatingController.php:24-60`: **none** | **HOLE H1** |
| customer | `POST /complaints` | order id | `Order/ComplaintController.php:64-73` user check after `exists:orders,id` | oracle H2 |
| customer | `/addresses/{id}`, pickup slots | id, branch | `User/AddressController.php:59`, `Order/PickupSlotsController.php:47-50` | OK |
| webhook | Click | order uuid / short code | `Payment/ClickWebhookController.php:75, 323`: the order's own merchant key | OK |
| webhook | Payme | account, transaction id | `PaymeWebhookController.php:141-160`; `GetStatement` (`:455-485`) platform key only, store cashboxes left out | OK |
| webhook | Telegram button | order in callback | `Services/Telegram/TelegramCallbackHandler.php:230-238` staff of the order's store, then OrderPolicy | OK |
| realtime | `/broadcasting/auth` | store/branch id | `routes/channels.php:31-51, 73-92` | OK |
| ETag | 304 / X-Revision | scope ids | `Http/Middleware/ConditionalGet.php:313-332` admissible | OK |
| files | `GET/PUT storage/{path}` | path | `config/filesystems.php:33-38` local disk `serve: true`: Laravel `ServeFile` requires a valid signature | OK [F vendor `Filesystem/ServeFile.php:28`] |

Holes found:
- **H1 (medium, integrity of another store's public data).** `POST /v1/stores/{id}/rate` (`StoreRatingController.php:24-60`):
  any signed-in user (a customer, or the staff of a competing store, their token passes `auth:sanctum` + `staff.store`)
  may post unlimited ratings for any store; `order_id` is only `exists:orders,id` and is never checked against the caller
  or the store; each call rewrites `stores.rating` and `review_count`, which the marketplace shows and sorts by. Fix:
  require `order_id`, an order of the caller (`user_id`) in that store in a finished status, one rating per order; staff
  accounts refused.
- **H2 (low, oracle).** `exists:orders,id` on `ComplaintController.php:64` and `StoreRatingController.php:29` answers 422
  for a missing id and 403/200 for an existing one: anyone can count the platform's orders (sequential ids). Fix: no
  `exists` rule; one 404 for "missing or not yours".
- H3 (none found, residual): id->uuid maps without store filter (`PosOrderService.php:1522`, `BookingItemService.php:145`)
  are re-checked by the product's modifier rules; the generated suite (stage 1) is what proves it.

Existing tests [F grep]: 101 test files mention another/foreign store (`foreignStore` 121×, `otherStore` 78×); about 96
test methods are named for cross-store or cross-branch cases, out of ~2 760 test methods. Coverage is per feature and
written by hand; nothing enumerates routes, so a new route without its isolation test is not noticed.

## 3. Application-level guard (C)

### 3.1 Stage 1: the generated cross-tenant route suite (the first line, and the acceptance test for M4)
- **Enumerate:** `Route::getRoutes()`; tenant routes = URI prefix `api/v1/pos`, `api/v1/owner`, `api/v1/staff`,
  `api/v1/devices`, `api/v1/terminal`, plus `orders/{uuid}/status|complete|assign-courier`, `couriers`, `reports/export`
  [F route list: 146 + 228 + 7 + 8 + 3 + 5 ≈ 397 routes, 211 with path parameters, 247 writes]. Admin (193), marketplace,
  customer and webhook routes are listed in an explicit `NOT_TENANT` array with a reason, like RevisionMapCoverageTest.
- **World:** two complete stores A and B (`tests/Feature/Tenancy/TenancyWorld.php`, built from the existing fixtures,
  `tests/Feature/ManagementFixtures.php`): branch, hall, table, each staff role, POS/KDS/display terminals with PIN
  sessions, open shift, menu (product, variant, modifier group, combo, daily set, batch, stop-list), an order with items,
  bill, payment, fiscal receipt, refund, tip, booking with deposit, banquet with schedule, customer account + member,
  voucher, staff-pay statement + line, work session, printer, station + route, zone, packaging rule, brand, ingredient,
  waitlist entry, bank report, bill print request, sync operation. Every name/label of B carries the marker `ZZB-`.
- **Manifest:** `tests/Feature/Tenancy/routes.php` maps each tenant route (method + URI) to closures `params(World $w,
  Store $s)` and optional `body(...)` that name the store's ids (body ids for routes that take them, e.g. `table_id`,
  `product_variant_id`, `branch_id`). A tenant route missing from the manifest fails the suite ("classify this route"):
  the suite stays honest for new routes.
- **Calls:** each route as A's owner, A's branch-bound manager, A's cashier PIN session on A's till, and A's device
  token where the route takes `auth:device` (≈ 1 000 calls [E] ~30-60 s), with **B's** ids.
- **Assertions:** status in {401, 403, 404, 409, 422}; B unchanged (per tenant table, `md5(string_agg(row::text ...))` of
  B's rows before/after, plus row counts referencing B); the response body contains no `ZZB-` and no B uuid. Positive
  control: the same call with A's own ids must not give the same not-found answer (otherwise the mapping is wrong and the
  negative result means nothing). Parameter-free GET tenant routes are called as A and checked for `ZZB-` (catches a
  report or list that forgets the scope).
- Stage 2 adds one assertion: every tenant route binds `TenantContext` to A's store.

### 3.2 Stage 2: automatic scope bound to a per-request tenant
- `App\Support\Tenancy\TenantContext` (request-scoped singleton, `storeId(): ?int`), bound by route middleware `tenant`
  on the tenant route groups only, after auth: staff user -> `users.store_id`; device -> `devices.store_id`; PIN session ->
  user's store (equal to the terminal's, `PermissionService.php:178`). **Per route group, not per actor:** an owner's
  token also calls `/v1/stores` and `/v1/catalog` (`routes/api.php:72-74`), which must keep showing every store.
- Trait `BelongsToStore` adds a global scope `where <table>.store_id = ?` **only while bound**, a no-op otherwise. Applied
  to models with a NOT NULL direct `store_id` ([E] ≈ 85 models); not to `User`, `Store`, `Promo`, `PromoCode`, `Alert`,
  `AuditLog` writes (nullable = platform rows), the CRM `store_*` models, `FiscalReceiptKey`, nor to child models (reached
  via a scoped parent).
- Code without a tenant (scope inactive, nothing changes): console and scheduler (17 jobs, 19 commands), queue workers,
  Click/Payme/Telegram webhooks, admin (admin services/controllers, console and jobs: 33 files name tenant models), marketplace (`StoreController`,
  `CatalogController`, M2 read model builders), customer and courier order lists, broadcasting auth (`channels.php`),
  `CachedChannelAuth`. Jobs keep carrying their tenant as constructor ints (`SendOrderReport.php:21-23`); no ambient
  tenant is serialized into jobs.
- Breaks to look for (inside tenant routes only): queries that legitimately read another store's or a platform row
  through a scoped model. Found none in the sample; the implementer greps the ≈ 85 models' uses under the tenant route
  controllers and runs the full suite plus the stage 1 suite; each legitimate exception is `withoutGlobalScope`
  with a comment.
- Raw `DB::table()` (159 calls in 63 files) and `DB::select/statement` (85) are not covered by the scope; they are covered
  by the route suite (behaviour) and, for writes to RLS tables, by RLS (stage 3).
- M2's `RevisionQueryBuilder` (`app/Domain/Realtime/RevisionQueryBuilder.php`) sees every write and could assert
  "written rows belong to the bound store". Not done: it resolves scope columns only for tracked tables and does an
  extra read for updates; stage 3 gives the same check in Postgres for free on the tables that matter.
- [F, stage 2 as built] The binding lasts while the middleware's `runAs` runs: a streamed body (`streamDownload`,
  `response()->stream`) is written after it, so a tenant route that streams binds the store again inside its callback
  (`ReportController::export`); work after the response (`terminating`, `dispatchAfterResponse`) runs unbound. Queued
  jobs and queued listeners run unbound also on the sync queue (`QueuedJobsRunUnbound`: forgotten from `JobProcessing`,
  put back on `JobAttempted`, after the job's `failed()` method), so tests see what the worker sees; a job that is not ShouldQueue run by
  `dispatchSync`, inline listeners and `DB::afterCommit` callbacks are part of the request and stay bound.
- Known limits: `BindTenant` binds a courier to `users.store_id`; couriers serve only their own store's orders today
  (`OrderStatusService.php:262-264`). A platform fleet courier (`CourierType` FLEET) acting across stores would get 404
  on other stores' orders under the scope and needs its own binding decision before that phase. `exists:` rules go
  through the base query builder and still see every store: the scoped load after them must handle null (the stage 1
  suite sends B's id for every reference key and refuses any 5xx).
Rejected: explicit scoping + guard test only (no automatic filter: LAUNCH_PLAN asks for it and it costs nothing at the DB);
a scope active for every staff actor (breaks the marketplace for owners); route-model binding (169 `{uuid}` + 123 `{id}`
routes rewritten for no extra guarantee over the suite).

## 4. Row-level security (D)

### 4.1 Postgres facts [V, PG 16.15 and 17.12 unless noted]
1. `current_setting('app.store_id', true)` is NULL in a fresh session, but **`''` after any earlier transaction of the same
   session ran `set_config(..., true)`**; `''::bigint` raises `invalid input syntax for type bigint`. Under PgBouncer every
   pooled connection reaches that state. Policies must use `nullif(current_setting('app.store_id', true), '')::bigint`.
2. Through PgBouncer in transaction mode a session `SET app.store_id = '1'` by client 1 was **seen by client 2** (same
   server connection; transaction mode runs no reset query). `set_config(..., true)` and `SET LOCAL ROLE` ended at COMMIT:
   the next client was back to the login role with no setting [V PG 17 + local PgBouncer, pool size 1].
3. `SET LOCAL ROLE m4_tenant` from a `BYPASSRLS` login role that is a member of a `NOBYPASSRLS` role: inside the
   transaction the policy applied (8 313 of 1 000 000 orders visible, an insert of another store's row refused); outside
   it the login role saw everything [V PG 17, direct and through PgBouncer].
4. Missing setting with a fail-closed policy: `SELECT` returns 0 rows, `UPDATE`/`DELETE` of another store's row affect
   **0 rows silently** (no error), `INSERT` of another store's `store_id` fails `new row violates row-level security
   policy`.
5. **FK checks bypass RLS**: a child row pointing at an invisible parent (another store's order) was inserted. A policy on
   `orders` does not protect `order_items`/`order_status_histories`.
6. Triggers run as the invoking role (`fiscal_receipt_keys_insert`, `audit_logs_append_only` are not SECURITY DEFINER
   [V `pg_proc.prosecdef = f`]); a fiscal receipt insert under the policy wrote its `fiscal_receipt_keys` row normally. The
   app role therefore needs DML on `fiscal_receipt_keys`, which must stay without RLS (global uniqueness).
7. Partitioned tables: the parent's policy applies to queries through the parent; **a leaf queried by name bypasses it**
   (parent 1 row, `audit_logs_2026_11` 2 rows); new partitions are created with `relrowsecurity = f`. Insert routing through
   the parent checks the parent's policy. Revoking the app's grants on a leaf blocks direct access and keeps access
   through the parent.
8. `COPY ... TO STDOUT` applies the policy; `COPY FROM` into an RLS table fails ("not supported with row-level security").
   The app has no COPY FROM [F grep].
9. `pg_dump` as a role subject to RLS fails ("query would be affected by row-level security policy"); as the table owner
   without FORCE, or as superuser, it dumps everything. `FORCE ROW LEVEL SECURITY` makes the owner's dump fail too (and the
   owner sees 0 rows) [V PG 17, pg_dump 16.15].
10. `current_setting` is STABLE, not leakproof; `int8eq`, `texteq`, `uuid_eq`, `timestamp_ge` are leakproof; `textlike`,
    `texticlike` are not [V `pg_proc`].

### 4.2 Measurements (PG 17, 1 M orders, pgbench 4 clients × 8 s, extended protocol, each script `BEGIN; set_config;
query; COMMIT` as a non-owner role; latency ms; run-to-run noise ≈ ±5 %)

| query (from `results_m2b/L1_down.pgss.txt`) | no RLS | A: `store_id = nullif(setting,'')::bigint` | B: same in `(select ...)` | C: fail-open `setting IS NULL OR store_id = setting` | D: plpgsql `tenant_ok(store_id)` STABLE |
|---|---|---|---|---|---|
| floor: `orders` branch+channel+status, order by created_at | 2.84-2.86 | 2.89-2.94 | 2.94 | 3.03 | 3.21 |
| KDS: `orders` store+branch+status | 1.91 | 1.90 | 2.01 | 2.12 | 2.21 |
| `orders` by id | 0.18 | 0.19 | 0.20 | 0.20 | 0.18 |
| owner list: `orders` store order by created_at desc limit 20 | 0.26-0.27 | 0.37-0.38 | 0.41 | **3.14** | 0.29 |
| `order_items` in (31 latest of the store) | 0.69 | 0.75 | 0.76 | 0.95 | **1.22** |
| `order_status_histories` in (31 latest) | 0.39 | 0.43 | 0.46 | 0.59 | **0.83** |
| `update orders set version` (latest of the store) | 1.08 | 1.10 | 1.14 | 1.21 | 1.27 |

- Policy A lets the planner merge `store_id = 500` with `store_id = setting` into a **One-Time Filter** over the normal
  `orders_store_id_created_at_id_index` backward scan; on the branch query it adds a BitmapAnd with a store index and
  the row estimate collapses to 1 (branch and store are correlated); `CREATE STATISTICS (dependencies) ON store_id,
  branch_id` lifted it to 20, actual 18. Watch join plans in stage 3 for this.
- The subselect wrapper (B) brings nothing on PG 17: the expression is already evaluated once per statement.
- Any fail-open form inside the policy is worse: the OR (C) kills the selectivity estimate and the owner list falls from an
  ordered index scan to bitmap + sort (12×); an opaque function (D) keeps that plan but is called per row (+77 % / +112 %
  on the `IN (subselect)` reads). Hence fail-open is done with roles (4.3), never inside the policy.
- Wrapper cost: a statement wrapped as `BEGIN; SET LOCAL ROLE ...; SELECT set_config(...)` (one simple-protocol message) +
  statement + `COMMIT` vs bare: by-id 0.095 -> 0.146 ms, owner list 0.163 -> 0.262 ms (local TCP). With 27 statements per
  request (`docs/scale/RESULTS_M2.md:57`) wrapping every level-0 statement costs ≈ 1.5-3 ms per request locally [V] and
  more through Docker + PgBouncer [E 3-5 ms, against a floor read of 9.5 ms in `RESULTS_M2.md:99`]. Per transaction (writes mode)
  it is one extra round trip: ≈ 0.05 ms [V].

### 4.3 Design
- **Roles:** `rizqim_app` (LOGIN, `BYPASSRLS`, `NOINHERIT` member of `rizqim_tenant`); `rizqim_tenant` (NOLOGIN,
  NOBYPASSRLS, same DML grants). No FORCE: the app is not the owner; the owner bypasses as owner (migrations, partition
  commands), the bootstrap superuser bypasses (backup, restore drill, `pg_dump`). `BYPASSRLS` needs a superuser to grant:
  the `db-roles` service (section 5) does it.
- **Policy** (one per table, all commands):
  `CREATE POLICY tenant ON <t> USING (store_id = nullif(current_setting('app.store_id', true), '')::bigint);`
  Under `rizqim_tenant` it is fail-closed, but the role switch and the setting travel in the same message, so "tenant role
  without a store" cannot occur; a path that escapes the switch runs as `rizqim_app` and works unprotected (the till never
  fails for a missing setting).
- **Where the switch happens:** `RevisionPostgresConnection` (`app/Domain/Realtime/RevisionPostgresConnection.php:11-15`, the
  app's own connection class) after `beginTransaction` at level 0, when `TenantContext` is bound: one `exec("SET LOCAL ROLE
  rizqim_tenant; SELECT set_config('app.store_id', '<int>', true)")`. Same on every retry attempt of `DB::transaction`.
  Level-0 `insert/update/delete` outside a transaction (Eloquent single saves) are wrapped in a transaction by the same
  class. Never inside a savepoint (a rolled-back savepoint would undo the setting).
- **Writes mode (decision):** reads outside transactions run as `rizqim_app` (bypass) and rely on the app guard; reads
  inside transactions (every POS money path: open, bill, pay, refund, close, shift, sync) are under the policy. Reads
  mode (wrap every level-0 select of a tenant request) only if the stage 3 load run shows ≤ 10 % on POS p95 and Postgres
  CPU (owner decision 4).
- **Tables (20):** `orders`, `order_bills`, `payments` (after stage 2), `fiscal_receipts` (partitioned), `pos_shifts`,
  `cash_movements`, `pos_refunds`, `pos_tips`, `pos_qr_payments`, `customer_accounts`, `customer_account_entries`,
  `vouchers`, `voucher_entries`, `booking_payments`, `banquet_payments`, `banquet_payment_entries`,
  `staff_pay_statements`, `store_transactions`, `store_wallet_transactions`, `audit_logs` (partitioned; the same one
  policy as the others, see "As built": the separate `FOR INSERT WITH CHECK (store_id IS NULL OR ...)` planned here does
  not work with `INSERT ... RETURNING`).
- **Not under RLS, and why acceptable:** marketplace-public rows read across stores by customers (`stores`, `branches`,
  `products`, `product_variants`, `modifier*`, `combos`, `daily_*`, `delivery_zones`, `categories`): public by nature, and a
  policy cannot hide columns (`Store::INTERNAL_ATTRIBUTES`, `app/Models/Store.php:53,232`, keeps balances out of bodies).
  `users` (customers have NULL `store_id`; POS screens read the order's customer). Children without `store_id` (FK checks
  bypass RLS anyway, fact 5; a VIA policy costs +49 %). Platform, auth, queue and cache tables. `fiscal_receipt_keys`
  (fact 6). Partition leaves keep the default grants (fact 7): app code never names a leaf [F grep `_20[0-9][0-9]_` in
  `app/` outside `Support/Partitions`]; revoking per leaf would add a step to `PartitionManager::ensure` for no gain.
- **Branch-level isolation stays app-level** (`actingBranchId`): one setting per store keeps one policy shape.
**As built (stage 3; where this differs from the bullets above, this is what the code does):**
- Roles [V 16.15, 17.12]: `GRANT rizqim_tenant TO rizqim_app WITH INHERIT FALSE, SET TRUE` (the per-membership form of
  PG 16+; same statement on both, idempotent); the tenant role's name comes from `DB_TENANT_ROLE` (default
  `rizqim_tenant`) in `scripts/db-roles.sh` and `config('database.tenant_rls.role')`. The policy is `TO PUBLIC`, so the
  migration needs no role and runs on a one-role install, where it is inert (owner, no FORCE: tested).
- The setting is written with `SET LOCAL app.store_id = '<int>'` (the same thing as `set_config(..., true)`; the
  suite's pooling guard, `TransactionPoolingSafetyTest`, allows only the `SET LOCAL` form in `app/`).
- Where the switch happens: on the first transaction opened while a store is bound and the role is not yet taken. In
  production that is level 1 (right after BEGIN, every retry attempt again; COMMIT/ROLLBACK end it). Under the test
  suite's wrapping transaction (`RefreshDatabase`), and wherever bound code starts inside a transaction opened by
  unbound code, it is a savepoint: a rollback to it undoes role and setting, and when it is committed (Laravel never
  releases a savepoint) both are put back by hand (`SET LOCAL ROLE NONE; SET LOCAL app.store_id = ''`) before
  after-commit callbacks run. So the same code path runs in tests and in production, and the whole suite exercises the
  policies. Transactions nested inside a tenant transaction change nothing.
- Writes mode wraps only a single `insert/update/delete` whose target is one of the 20 tables (first keyword and table
  name of the statement); other level-0 writes of a tenant request run as before (no extra round trips, no change).
- `audit_logs`: with a policy `USING (store_id = ...)`, `INSERT ... RETURNING` (what Eloquent sends) applies the USING
  expression to the new row too, so a NULL-store row is refused whatever a separate INSERT policy says [V 16, 17].
  Hence one policy like every other table, and `AuditLog::performInsert` writes a NULL-store line of a tenant request
  as the login role (`TenantRls::asApp`); a line for another store is refused. Callers read: `AuditLogger::record`
  takes the store from the subject, the terminal or the actor, which in a bound request is never NULL; the one
  NULL-store writer reachable from a tenant route is `OwnerEmployeeController::updateBranch` (`AuditLog::create`
  without `store_id`).
- Deliberate cross-store statements inside a tenant transaction go through `TenantRls::asApp()` (`SET LOCAL ROLE NONE`
  and back, no savepoint). Uses: `LedgerService::isFirstDelivery` (the referral's "first delivery on the platform") and
  the NULL-store audit line. Queued jobs the sync queue runs inline get the login role for their duration
  (`QueuedJobsRunUnbound`), as a worker would give them.
- Fail-open only for the role: when `SET LOCAL ROLE` itself fails (role dropped, membership revoked while running) the
  transaction is started again as the login role with a critical log line; the monitoring check `partitions` asks the
  database every minute (`TenantRls::setupProblems()`) and keeps a critical alert open while that lasts, and
  `app:check-production` refuses to start in that state (also for a privilege the tenant role lacks). A refused write
  (SQLSTATE 42501) is a server error for the client, a critical log line (table, route, store) and a critical alert.
- The migration refuses, before changing anything, when the application's role would itself be bound by the policies
  (roles split, `db-roles` of this stage not run): it would see no row of the 20 tables anywhere.
- Fact 7 as built: a month created by `db:partitions:ensure` or attached by `scripts/archive-restore.sh` needs nothing
  (tested through the parent). A journal REBUILT as a new table (`PartitionConverter`, the M3 migration's up/down) lost
  its policy: the converter now carries row-level security over the swap like the triggers.

- What the policy does not cover (security review of stage 3; PostgreSQL's design, not a gap of the migration):
  **foreign-key actions** (ON DELETE CASCADE / SET NULL run as the table's owner; deleting a parent that is not under
  the policy deletes or changes rows of the 20 tables in whichever store they are. Parents that cascade into them:
  `users` (through `orders.user_id`), `branches`, `bookings`, `banquet_events`, `customer_accounts`, `vouchers`,
  `orders`, `order_bills`, `payments`, `pos_shifts`, `stores`. Found exploitable and fixed: `DELETE
  /owner/employees/{id}` hard-deleted an employee who had bought at another store, and that store's orders and
  payments with them; such a person is now taken out of the store instead. A DELETE-only policy on the parents was
  proposed in review and not built); **foreign-key checks** (fact 5: a row of the tenant's own store may point at
  another store's parent: `order_bills.order_id`, `pos_refunds.payment_id`, `cash_movements.shift_id`; accepted, the
  row gives no read of the parent. `payments` is closed: `payments_parent_store()` is SECURITY DEFINER with a fixed
  search_path since the stage 3 migration, so its trigger sees the order's real store and the policy refuses "my
  store, your order"); **children without `store_id`** (`order_items`, `order_bill_items`,
  `staff_pay_statement_lines`, `pos_tip_distribution_lines`, ...: reached only through their parents, application
  guard, decision A).
- The catalog is part of the check: `setupProblems()` also asks that each of the 20 tables has row-level security on
  and exactly the agreed policy (a second permissive policy, a rewritten one or a disabled table opens it without any
  error); a check that cannot run is an error at start-up and a warning in monitoring while TENANT_RLS is on.
- The migration retries a lock timeout (55P03) for up to a minute in all before failing (the nightly `pg_dump` holds
  ACCESS SHARE on every table for its whole run; a failed migration restarts the web container, the tables done stay
  done).
- Transaction pooling (PgBouncer, transaction mode) cannot run in CI; it was verified by hand in the correctness review
  (pool size 1: a second client on the same server process saw the login role and an empty setting after a commit, a
  rollback, a wrapped single write, three retries, an asApp section and a refused write). The repository keeps
  `TenantRlsProductionShapeTest`: a tenant request on real transactions (no wrapping transaction), with retries after
  a server-raised 40P01.
- Known framework behaviour, not changed (open question for a later stage): a serialization failure (40001) raised
  inside a NESTED `DB::transaction` reaches the outer `DB::transaction(..., 3)` as Laravel's DeadlockException with
  code 0, whose message is not in Laravel's list, so the outer one does not retry it; 40P01 is retried.
- Measured (stage 3, PG 17.12, 1 000 stores, 1 000 000 orders with 3 items, a bill and a payment each, largest store
  52 416 orders, median 498; DB only, pgbench 2 clients, simple protocol, the statements a till sends, rolled back; run
  to run ±10 %): the switch alone (`BEGIN; SET LOCAL ROLE ...; SET LOCAL app.store_id; ROLLBACK` against `BEGIN;
  ROLLBACK`) +0.05 ms; every statement on a table under the policy plans about 0.03-0.05 ms longer (the extra
  `store_id` qual; execution unchanged: the same index scan under a One-Time Filter); so per transaction: open order
  1.83 -> 2.01 ms, add item 1.84 -> 2.17 ms, pay 3.09 -> 3.49 ms, close 1.80 -> 2.18 ms (+0.2 to +0.4 ms, +10 to
  +21 % of the transaction's database time). A single write to a table under the policy outside a transaction costs
  three more round trips than before (BEGIN, the two SETs in one message, COMMIT); the load run measures what that
  is through Docker and PgBouncer. The POS e2e (114 scenarios, split roles, RLS on): 10 982 tenant
  transactions, 6.4 statements each, 1.7 of them on a table under the policy, 326 single writes wrapped.
- Plans: none of the write-path statements changed (all by primary key or with `store_id`). Statements with
  `branch_id` and no `store_id` on a table under the policy do: the estimate collapses (store and branch are
  correlated: 302 estimated for 8 782 rows) and the plan becomes BitmapAnd + sort: the floor shape for the largest
  store 2.6 -> 25.6 ms, a pay-statement join for a median store 1.4 -> 3.9 ms. `CREATE STATISTICS (dependencies) ON
  store_id, branch_id` on `orders` and `order_bills` brings both back (2.9 ms, 1.5 ms). Inside tenant transactions
  such statements are the report queries of the staff-pay, tips and X-report code only (39 of 18 447 statements in
  the e2e); the floor read itself runs outside transactions. Not added in stage 3 (no write path is affected); needed
  before reads mode, and worth adding with the load run if those reports show up.

Rejected: wrap every request in one transaction (holds a pooled server connection and row locks through slow PHP and
outbound HTTP, turns `DB::transaction(..., 3)` deadlock retries into savepoints that never retry, delays every
`afterCommit` revision touch and broadcast to the end of the request); a second PgBouncer pool in session mode (doubles
server connections, still needs per-request reset); `options=-c app.store_id` per connection (impossible with shared
pools); per-tenant roles (1 000 roles, a pool per role); SECURITY BARRIER views or functions per statement (rewrites
every query); fail-open inside the policy (plans, 4.2); FORCE RLS (breaks owner dumps and partition maintenance).

## 5. Roles (E)

[F] Today: compose creates the database with `POSTGRES_USER: ${DB_USERNAME:-rizqimbot}` (`docker-compose.yml:130-132`), the
image's **bootstrap superuser**, and every container uses the same credentials: backend/queue/scheduler/Reverb
(`docker-compose.yml:28-29`), PgBouncer userlist (`infra/pgbouncer/entrypoint.sh:43`), backup sidecar
(`docker-compose.yml:467-468`). CI's Postgres service user `rizqim` is also the superuser (`.github/workflows/ci.yml:195-197`).
Locally `rizqim` is a non-superuser owner [V `rolsuper = f`]. The pooled connection uses
`PGSQL_ATTR_DISABLE_PREPARES` (unnamed statements), not emulated prepares (`config/database.php:126-128`).

What needs ownership or privileges [V as a plain DML role unless noted]:
- Migrations, `ConcurrentIndex` (`app/Support/ConcurrentIndex.php:25-106`): "must be owner of table". -> owner.
- `db:partitions:ensure` (00:40 UTC) and `db:partitions:archive` (22:40 UTC) from the scheduler
  (`app/Providers/PartitionsServiceProvider.php:30-31`; `PartitionManager`/`PartitionArchiver` do CREATE ... PARTITION OF,
  ATTACH/DETACH, DROP, trigger repair): "permission denied for schema public" as DML role. -> owner.
- `pg_ls_archive_statusdir()` (`app/Services/Admin/Monitoring/PostgresStats.php:146`): permission denied; works with
  `pg_monitor`. `pg_stat_archiver` readable by anyone; other roles' `pg_stat_activity.query` needs `pg_read_all_stats`
  (in `pg_monitor`). -> grant `pg_monitor` to `rizqim_app`.
- Transaction advisory locks, `lockForUpdate`, `SET LOCAL lock_timeout` (`FiscalReceiptLock`): any role. No `LOCK TABLE`,
  `CREATE EXTENSION`, `COPY FROM`, `TRUNCATE` or `session_replication_role` in `app/` [F grep].
- `pg_dump` nightly, archive dumps (`scripts/archive-partitions.sh:119`), restore drill, pgBackRest: bootstrap superuser,
  unchanged (it must read every row under RLS, fact 9).

Role set (decision E):

| role | attributes | used by | grants |
|---|---|---|---|
| bootstrap (`POSTGRES_USER`, unchanged name) | SUPERUSER | postgres container, `backup` sidecar, restore drill, `db-roles` | - |
| `rizqim_owner` | LOGIN, NOSUPERUSER | `migrate` (backend entrypoint, `DB_POOLER=direct`), `db:partitions:*` | owns schema objects; `USAGE, CREATE ON SCHEMA public` |
| `rizqim_app` | LOGIN, NOSUPERUSER, `pg_monitor`; `BYPASSRLS` from stage 3 | php-fpm via PgBouncer, queue, queue-reports, scheduler, Reverb | DML on all tables, `USAGE, SELECT` on sequences, `EXECUTE` on functions; `ALTER DEFAULT PRIVILEGES FOR ROLE rizqim_owner` the same |
| `rizqim_tenant` (stage 3) | NOLOGIN | `SET LOCAL ROLE` inside tenant transactions | same DML grants |

No read-only reporting role (nothing would use it yet) and no separate monitoring login (the checks run in the app's own
scheduler).

Facts for the move [V PG 16]: a role created later needs `CREATE` on `public` before `ALTER TABLE ... OWNER TO` works
(PG 15+ schema rules); `ALTER TABLE parent OWNER` does **not** recurse to partitions (each leaf needs its own); owned
sequences and indexes follow their table; a DO loop over 214 relations + functions took 52 ms on an empty schema (each
takes ACCESS EXCLUSIVE briefly); default privileges for the owner gave the app DML on a table and a new partition created
later by the owner.

Mechanics:
- `scripts/db-roles.sh` (new), run by a one-shot compose service `db-roles` (postgres image, bootstrap credentials,
  `depends_on: postgres healthy`; `backend`, `queue*`, `scheduler`, `reverb`, `pgbouncer` depend on its successful
  completion). Idempotent: create missing roles, `ALTER ROLE ... PASSWORD` from env (= rotation: change the env, `docker
  compose up -d db-roles pgbouncer backend ...`), grants, default privileges, then ownership transfer of every object not
  owned by `rizqim_owner` with `SET lock_timeout = '2s'` and a retry per object (pilot database: run once in a quiet hour;
  the script prints what it moved).
- Env: `DB_USERNAME/DB_PASSWORD` = app (backend environment block, PgBouncer); new `DB_OWNER_USERNAME/DB_OWNER_PASSWORD`;
  `POSTGRES_USER/POSTGRES_PASSWORD` = bootstrap (postgres, backup, db-roles only). The owner password must not end up in
  `bootstrap/cache/config.php`: the entrypoint runs `migrate` with the owner credentials in the command's environment
  (`backend/docker/entrypoint.sh:95`) before `config:cache` (`:97`) runs with the app's; the partition commands read the
  owner credentials from the process environment when they start (scheduler container only). The implementer verifies
  that a scheduled command's subprocess inherits the container env with a cached config.
- PgBouncer userlist: unchanged code, it now carries `rizqim_app` only (only php-fpm uses the pool).
- Local dev keeps one owner role (`rizqim`); CI gets a second job (section 7) that migrates as owner and runs the suite as a
  DML-only role; the scale-test kit keeps connecting as owner (it measures load, not isolation) and gains an optional
  `DB_APP_ROLE` for the stage 3 load run.

## 6. Stages (F)

| stage | contents | can a till write fail? how it is proven not to | acceptance | cost [E] |
|---|---|---|---|---|
| 1 | generated route suite + fixes for every leak it finds (H1, H2 known) + role split (`rizqim_owner`, `rizqim_app`, `db-roles`, owner connection for migrate and partitions, `pg_monitor`) | only through a runtime path that needed ownership: the known three are moved (5); CI runs the whole suite as a DML-only role, and the scheduler's partition commands as owner; staging soak 48 h covering 00:40 and 22:40 | all ≈ 397 tenant routes in the manifest; every route × 3-4 actors refused with B's ids, B unchanged, no `ZZB-` in any body; positive controls pass; `SELECT rolsuper FROM pg_roles WHERE rolname = current_user` = f for app connections (new `app:check-production` line) | 4 agents, 2 review rounds; the manifest is the big part (≈ 400 entries) |
| 2 | `TenantContext` + `tenant` middleware on tenant groups + `BelongsToStore` on ≈ 85 models + `payments.store_id` (column, trigger, batched backfill, NOT NULL via check) | a scoped model read in a tenant route that needed another store's row returns null -> 404: full suite + stage 1 suite + POS e2e job (`ci.yml:913`) with the scope on | stage 1 suite also asserts the bound tenant; 0 `withoutGlobalScope` without a comment; payments backfill bounded (50 000 rows/batch, measured 0.7 s) | 3 agents, 2 review rounds |
| 3 | `rizqim_tenant`, `BYPASSRLS` on `rizqim_app`, switch in `RevisionPostgresConnection`, 20 policies (one migration, metadata-only, `ENABLE ROW LEVEL SECURITY` takes ACCESS EXCLUSIVE briefly per table) | yes, the riskiest: a tenant transaction that writes another store's row on purpose, or a write whose `store_id` is not the bound store, now fails or silently does nothing. Proven by running the full suite, the stage 1 suite and the POS e2e job under the app role with RLS on; plus the M2 lunch load (`scripts/scale-test/m2.sh m2b-L3`) as `rizqim_app` | 0 new 5xx; POS p95 and Postgres CPU within +5 % of the same run without policies; a test per table proves a forged cross-store write is refused by Postgres even with the app guard bypassed | 2-3 agents, 1 load run, 2 review rounds |

Cut line: all three before the OPEN launch (LAUNCH_PLAN "Marralar uchun shartlar"). Stage 1 is safe to ship during the
pilot and gives most protection per unit of risk (tests catch the bug class, the role split is mechanical). Stage 3 waits
for stage 2 (needs `TenantContext` and `payments.store_id`). Rollback per stage: 1 = env back to one role; 2 = middleware
off; 3 = `ALTER TABLE ... DISABLE ROW LEVEL SECURITY` (policies stay) or `TENANT_RLS=0` skips the role switch.

## 7. Work breakdown for stage 1 (G)

### 7.1 Agents and file ownership (disjoint)
1. **R, roles and infra:** `scripts/db-roles.sh` (new), `docker-compose.yml`, `docker-compose.staging.yml`,
   `backend/docker/entrypoint.sh`, `.env.example` (root) and `backend/.env.example`, `infra/pgbouncer/entrypoint.sh` (only if
   the userlist variable is renamed), `.github/workflows/ci.yml` (new job `tests-app-role`: bootstrap superuser
   `postgres`, `db-roles.sh`, migrate as owner, `php artisan test` with `DB_USERNAME=rizqim_app` and the migrations of
   `RefreshDatabase` on an `owner` connection), `docs/OPERATIONS.md` (rotation, first run on the pilot database).
2. **P, owner connection in PHP:** `backend/config/database.php` (connection `pgsql_owner`, built from process env, never
   cached with a password), `app/Support/Partitions/*`, `app/Console/Commands/Partitions*.php`, `tests/TestCase.php` (or a
   trait) so `RefreshDatabase` migrates on `pgsql_owner` when set, `app/Console/Commands/CheckProductionConfigCommand.php`
   (refuse a superuser or owner app role in production), their tests.
3. **T, test world + generated suite:** `tests/Feature/Tenancy/` (new: `TenancyWorld.php`, `routes.php` manifest,
   `CrossTenantRouteTest.php`, `ListLeakTest.php`). Reports each failing route to F; changes no app code.
4. **F, fixes:** `app/Http/Controllers/Api/V1/StoreRatingController.php`, `Order/ComplaintController.php` + tests (H1, H2)
   first; then one fix per route T reports, each in the controller/service that owns it, with its own regression test.
Order: R and P in parallel (agree first on env names `DB_OWNER_USERNAME`/`DB_OWNER_PASSWORD` and connection name
`pgsql_owner`); T and F (H1, H2) in parallel with them; F's second round after T's first full run; R's CI job last (needs
P's `RefreshDatabase` change).

### 7.2 Test plan
- T's suite in the normal CI job (as owner) and in `tests-app-role`.
- P: partition ensure/archive tests run against `pgsql_owner` while the default connection is a DML-only role; a test
  that `db:partitions:ensure` fails loudly (not silently) without owner credentials.
- R: `db-roles.sh` run twice on a database with data (idempotent, prints "nothing to move" the second time); a compose
  smoke in CI's existing stack job: backend healthy, `select current_user, rolsuper` through PgBouncer = `rizqim_app, f`.
- F: H1 tests: stranger, staff of another store, a customer without an order of that store, a second rating of one
  order all refused; H2: missing and foreign order give the same 404.

### 7.3 Risks, ranked
1. A runtime path needs ownership and is found only in production (partition ensure at 00:40 would leave new rows in
   DEFAULT; M3's `PartitionsCheck` alerts). Mitigation: `tests-app-role` CI job, staging soak over both schedule times.
2. Ownership transfer on the pilot database waits behind a long transaction (ACCESS EXCLUSIVE per table). Mitigation:
   `lock_timeout 2s` + retry per object, quiet hour, the script is re-runnable.
3. The manifest maps a parameter wrongly and the suite passes vacuously. Mitigation: the positive control per route.
4. The owner password leaks into the config cache read by php-fpm. Mitigation: env only on the command line of `migrate`
   and in the scheduler container; `app:check-production` refuses a cached `pgsql_owner` password.
5. PgBouncer restart for the new userlist drops in-flight requests (seconds). Mitigation: deploy in a quiet minute; tills
   queue offline (`pos_sync`).

### 7.4 Owner decisions (with recommended defaults)
1. "Every table carries the store": **default `payments` only**; alternative all 31 children (+≈ 2 agent-waves, bigger
   order_items/bill_items writes).
2. Rating rule (H1): **default** one rating per finished order of the caller in that store, staff refused.
3. Roles on the pilot: **default** split during the pilot (stage 1), not at open launch.
4. RLS reads mode: **default** writes mode only; reads mode only if the stage 3 load run shows ≤ 10 %.
5. Backups and restore drill keep the bootstrap superuser: **default yes** (they must bypass RLS).
6. Branch-level isolation stays in the app (`actingBranchId`), not in RLS: **default yes**.

## 8. Not built, and why
- No route-model binding rewrite, no per-tenant roles or schemas, no second PgBouncer pool, no read-only reporting role,
  no separate monitoring login: nothing needs them (sections 3-5).
- No tenant check inside `RevisionQueryBuilder`: RLS gives it on the tables that matter at no extra read.
- No VIA policies on child tables and no `store_id` on them (decision A; FK checks bypass RLS anyway).
- No revoke on partition leaves, no FORCE RLS, no RLS on marketplace-public tables or `users` (section 4.3).
- No tooling to "shadow" RLS: the CI suite under the app role with policies on is the proof.
