# Restaurant integration hubs and marketplace open APIs: design and monetization (as of Oct 2026), with guidance for the RIZQIM Partner API and internal connectors

Research date: 2026-10-25. Most sources are live developer documentation with no publication date, fetched on that day. Undated docs pages are marked "(live docs, accessed 2026-10-25)". Facts are kept in "Cited Findings". Recommendations for Rizqim are kept in "Inferences".

Access notes: `developers.hubrise.com` did not resolve (DNS failure). The HubRise docs are now served at `www.hubrise.com/developers/...` and were read there. Several Deliverect docs URLs returned 404 on direct fetch, so the Deliverect facts below come from search-result extracts of developers.deliverect.com and help.deliverect.com pages and should be re-checked before anyone builds against them. GitHub repository search through the API was blocked in this session, so open-source coverage is limited to the HubRise GitHub org page and API Evangelist profiles.

---

## 1. Canonical data models and protocol mechanics (menu, order, status, timing, IDs, webhooks/polling, retries, signing, sandbox, versioning)

### Takeaway
The hubs agree on the overall shape. The menu is a tree: category → product → SKU/variant → option lists with min/max → options. Deals/combos have lines, and availability and price overrides are expressed as rules keyed by channel and time. Orders carry their own copies of item, option, discount, charge and payment lines, each referencing the catalog by a stable merchant `ref`. Statuses form a linear "happy path" plus terminal anomaly states, and the POS must explicitly accept within a hard deadline. Webhooks are signed with HMAC-SHA256 over the raw body, retried with exponential backoff, and paired with a polling fallback.

### Cited Findings

**Menu / catalog model (HubRise, the most completely published model)**
- A HubRise catalog is identified by a `name` that is unique per account or location. Its `data` holds `variants`, `categories`, `products`, `option_lists`, `deals`, `discounts` and `charges`. — [HubRise Catalog API](https://www.hubrise.com/developers/api/catalog-management) (live docs, accessed 2026-10-25)
- Categories form a tree through `parent_ref`. A product has `ref`, `category_ref`, `name`, `description`, `tags`, `nutrition`, `image_ids`, `skus`, and a per-service `tax_rate` with `delivery`/`collection`/`eat_in` values. — [HubRise Catalog API](https://www.hubrise.com/developers/api/catalog-management)
- A SKU (the sellable variant, e.g. a size) has `ref`, `name`, `price`, `price_overrides`, `restrictions`, `option_list_refs`, `barcodes` (8/12/13 digits), `custom_fields`, `weight`, `volume` and `nb_servings`. — [HubRise Catalog API](https://www.hubrise.com/developers/api/catalog-management)
- An option list (modifier group) has `min_selections` (default 0), `max_selections` (default null, meaning unlimited) and `multiple_selection` (whether the same option can be picked more than once). Validation rules: min ≤ max and min ≤ the number of options, and `multiple_selection` is not allowed when max is 0 or 1. The old `type` field (single/multiple) is deprecated. Options have `ref`, `name`, `price`, `price_overrides`, `restrictions` and `default`. — [HubRise Catalog API](https://www.hubrise.com/developers/api/catalog-management)
- A deal (combo) has `lines`. Each line has a `label`, eligible `skus` (each with an optional `extra_charge`), `pricing_effect` (`unchanged` | `fixed_price` | `price_off` | `percentage_off`) and `pricing_value`. One documentation example uses `"free"`, which is not in the documented list, so the docs are internally inconsistent. — [HubRise Catalog API](https://www.hubrise.com/developers/api/catalog-management)
- Charges are typed: `delivery`, `payment_fee`, `tip`, `tax`, `other`. A `null` price means the amount is variable. — [HubRise Catalog API](https://www.hubrise.com/developers/api/catalog-management)
- **Channel/time availability:** `restrictions` can appear on a SKU, option, deal, discount or charge, with the fields `enabled`, `variant_refs` (channel), `dow`, `start_time`/`end_time` (local HH:MM), `start_date`/`end_date`, `min_order_amount`, `max_per_order` and `max_per_customer`. All conditions must match, and `enabled:false` disables the item outright. — [HubRise Catalog API](https://www.hubrise.com/developers/api/catalog-management)
- **Channel prices:** `price_overrides` on a SKU or option are matched by `variant_refs`, `dow`, time and date. At least one condition is required, and when several rules match, the last matching rule wins. A "variant" is HubRise's name for a channel or location price/availability profile. — [HubRise Catalog API](https://www.hubrise.com/developers/api/catalog-management)
- `ref` must be unique per type within a catalog, is carried into orders, and "should be stable". HubRise also has a separate system `id`. — [HubRise Catalog API](https://www.hubrise.com/developers/api/catalog-management)
- Stock and inventory are a separate HubRise resource ("Inventories") with their own `inventory.read`/`inventory.write` scopes. Using `catalog.*` scopes for inventory is deprecated. — [HubRise Authentication](https://www.hubrise.com/developers/api/authentication)
- UrbanPiper's menu push contains categories, items, option groups, options, taxes and charges. The POS must create the menu and assign it to each store. — [UrbanPiper docs via API Evangelist profile](https://github.com/api-evangelist/urbanpiper/blob/main/README.md); [UrbanPiper downstream overview](https://api-docs.urbanpiper.com/downstream/getting-started/overview)
- UrbanPiper also models "Category Timing Groups", which are time-based availability for categories, and item and option on/off actions that are processed asynchronously by each aggregator. — [UrbanPiper Webhooks-Callbacks](https://api-docs.urbanpiper.com/downstream/getting-started/webhooks-callbacks) (live docs, accessed 2026-10-25)

**Order model (HubRise)**
- Order header fields:
  - references: `ref` (creator's reference, "Uniqueness is recommended but not enforced") and `private_ref` ("Unique private order reference used for lookups")
  - `channel`, `status`, `collection_code`
  - `service_type` (`delivery`/`collection`/`eat_in`) and `service_type_ref` (encodes channel, brand and delivery method)
  - notes and extras: `customer_notes`, `seller_notes`, `coupon_codes`, `custom_fields`
  - computed by HubRise: `total` — [HubRise Orders API](https://www.hubrise.com/developers/api/order-management)
- Items require `product_name`, `price` (unit price excluding options) and `quantity`. They can also carry `sku_ref`, `sku_name`, `tax_rate`, `customer_notes`, loyalty points and `deleted`. Options carry `option_list_name`, `name`, `ref`, `price`, `quantity` and `removed` (for "no onions" style removals). — [HubRise Orders API](https://www.hubrise.com/developers/api/order-management)
- Deal items carry a `deal_line` with `deal_key`, `label`, `pricing_effect` and `pricing_value`. Every `deal_key` must match an entry in the order's `deals` map, or the request is rejected. — [HubRise Orders API](https://www.hubrise.com/developers/api/order-management)
- Discounts, charges and payments are separate arrays, each with `private_ref`, `name`, `ref`, an amount and `deleted`. If payments are omitted, the order is treated as unpaid, and any mismatch with `total` is stored in `payment_discrepancy`. — [HubRise Orders API](https://www.hubrise.com/developers/api/order-management)
- A customer is either embedded as a guest `customer` object or referenced through `customer_id`/`customer_list_id` + `customer_private_ref`. The two forms are mutually exclusive. — [HubRise Orders API](https://www.hubrise.com/developers/api/order-management)

**Status lifecycle and timing rules**
- HubRise:
  - happy path: `new → received → accepted → in_preparation → awaiting_collection → in_delivery → completed`, and steps may be skipped
  - anomaly states: `rejected`, `cancelled`, `delivery_failed`
  - `awaiting_shipment` is deprecated — [HubRise Orders API](https://www.hubrise.com/developers/api/order-management)
- HubRise timing fields:
  - `expected_time`: ready or delivery time
  - `asap`: true when `expected_time` is unset
  - `expected_time_pickup`
  - `confirmed_time`: a merchant-confirmed time that overrides the customer's requested time — [HubRise Orders API](https://www.hubrise.com/developers/api/order-management)
- Deliverect numeric status codes:
  - happy path: 10 NEW, 20 ACCEPTED, 40 PRINTED, 50 PREPARING, 60 PREPARED, 70 PICKUP_READY, 90 FINALIZED, with 95 AUTO_FINALIZED as a fallback only
  - failure states: 110 CANCELED, 120 FAILED
  - courier sub-statuses: 80–89 (81 delivery created, 83 en route to pickup, 85 arrived at pickup, 87 en route to dropoff, 89 arrived at dropoff) — [Deliverect Order Statuses list](https://developers.deliverect.com/page/order-status); [Update Order Status](https://developers.deliverect.com/v1.1-restaurants/reference/update-order-status); [Update Delivery Job](https://developers.deliverect.com/v3.0-ordering-experience/reference/update-delivery-job) (from search extracts, not fetched directly)
- Deliverect POS rules:
  - every processed order should reach at least 20 ACCEPTED, because channels need confirmation
  - 70 drives driver alerts on some platforms
  - the POS should send 90
  - a POS that cannot process an order should set 120 FAILED so the customer is informed — [Update Order Status](https://developers.deliverect.com/v1.1-restaurants/reference/update-order-status)
- Deliverect's help-center glossary lists operational states that are not in the API table: Denied, Duplicate, Parsed, Received by POS, Cancel failed, Failed but handled. An order "stuck in Parsed" means the POS never received it or denied it. — [Deliverect help: Order statuses](https://help.deliverect.com/en/articles/7979014-order-statuses)
- Uber Eats:
  - acknowledge `orders.notification` with HTTP 200 and an empty body
  - then explicitly call accept or deny within 11.5 minutes, or the order auto-cancels
  - if the store has robocalls enabled, a robocall fires after 90 seconds with no accept or deny — [Uber Eats Webhooks guide](https://developer.uber.com/docs/eats/guides/webhooks) (live docs, accessed 2026-10-25)
- The Uber webhook page names `/accept_pos_order` and `/deny_pos_order`, but the newer Order API reference lists "Order Acceptance/Denial" endpoints with different naming. — [Uber Eats Order Integration](https://developer.uber.com/docs/eats/guides/order-integration) (naming conflict noted in search extracts)
- DoorDash Marketplace confirmation modes:
  - synchronous: a 2xx response to the order webhook confirms the order, and any non-2xx counts as a failure. The HTTP timeout is over one minute, but DoorDash prefers async if calls regularly exceed about 20 seconds
  - asynchronous: reply 202, then call `PATCH /api/v1/orders/{id}` with `merchant_order_id`, `order_status: success|fail` and an optional `failure_reason`
  - an unconfirmed order triggers DoorDash action after a window described as roughly 3–8 minutes. The exact text was truncated in the extract, so verify it. — [DoorDash: Receive orders](https://developer.doordash.com/en-US/docs/marketplace/how_to/order_integration/)

**Webhooks vs polling, retries, acknowledgement**
- HubRise callbacks:
  - each connection gets exactly one callback
  - an *active* callback sends an HTTP POST per event; a *passive* callback only stores events, which the client polls with `GET /callback/events` and acknowledges with `DELETE /callback/events/:id`
  - the recommended polling interval is about 1 minute, and no less than 30 seconds because of the daily rate limit — [HubRise Callbacks](https://www.hubrise.com/developers/api/callbacks) (live docs, accessed 2026-10-25)
- HubRise acknowledgement and retries:
  - the receiver must return a status in the 200–499 range within 20 seconds; a 5xx or a timeout triggers a resend
  - up to 6 retries, starting at 1 minute and doubling up to a 32-minute cap, after which the event is deleted
  - unacknowledged events can still be read through the events endpoint in the meantime — [HubRise Callbacks](https://www.hubrise.com/developers/api/callbacks)
- HubRise event payload: event id, `resource_type`, `event_type` (`create`/`update`/`patch`/`delete`), timestamp, resource IDs, and `previous_state`/`new_state`, so the receiver gets a diff without refetching. Catalog events carry no state. Subscribable resources: `order`/`own_order`, `catalog`, `customer`, `customer_list`, `delivery`/`own_order_delivery`, `inventory`, `location`. A client never receives events it generated itself, which prevents echo loops. — [HubRise Callbacks](https://www.hubrise.com/developers/api/callbacks)
- Uber Eats retries on 500/502/503/504, on timeout or on network error: the first retry comes after 10 seconds, then exponential backoff at 30s, 60s, 120s and so on, stopping after 7 total sends. — [Uber Eats Webhooks](https://developer.uber.com/docs/eats/guides/webhooks)
- Uber Eats webhook event types:
  - order events: `orders.notification`; `orders.cancel` (or `orders.failure` on API v1.0.0 stores); `orders.release`; `orders.scheduled.notification`; `order.fulfillment_issues.resolved`
  - store events: `store.provisioned`/`store.deprovisioned`; `store.status.changed` (the last one needs a whitelisted scope) — [Uber Eats Webhooks](https://developer.uber.com/docs/eats/guides/webhooks)
- UrbanPiper:
  - re-pushes an order only when the POS response is not HTTP 200, on an interval of "2^n seconds/minutes for the next 3 attempts"
  - retry is opt-in per webhook in the Atlas dashboard
  - callbacks cover async results (store add/update, store on/off, menu push, item/option action) and events (order relay, order status, rider status) — [UrbanPiper Order Relay FAQ](https://urbanpiper.helpjuice.com/55452-faqs/order-relay-webhook); [UrbanPiper Webhooks-Callbacks](https://api-docs.urbanpiper.com/downstream/getting-started/webhooks-callbacks)
- DoorDash menu creation is asynchronous: a 200 on the menu POST only means the request was received, and the result arrives later through a Menu Status webhook (average under 1 minute). Updates go through `PATCH /api/v1/menus/{id}`. Required webhook endpoints: Orders, Menu Status, Menu Request, Order Canceled. — [DoorDash Menu integration](https://developer.doordash.com/en-US/docs/marketplace/how_to/menu_integration/); [DoorDash Menu status callback](https://developer.doordash.com/en-US/docs/marketplace/how_to/menu_status_callback/)

**Signing / authentication**
- HubRise sends `X-HubRise-Hmac-SHA256`, a hex HMAC-SHA256 of the raw body keyed with the client secret. On a mismatch, the receiver should reject and ignore the event. — [HubRise Callbacks](https://www.hubrise.com/developers/api/callbacks)
- Uber Eats sends `X-Uber-Signature`, a lowercase hex HMAC-SHA256 of the raw body keyed with the client secret. — [Uber Eats Webhooks](https://developer.uber.com/docs/eats/guides/webhooks)
- Deliverect adds an HMAC (SHA256) header to every order webhook call and requires it to be computed over the raw, unparsed body. In production, a per-partner secret is issued when the partner moves to production. In staging, the `channelLinkId` is the secret. A secure HMAC secret must be set before certification. — [Deliverect: Validating orders using HMAC](https://developers.deliverect.com/v3.0/docs/validating-orders-in-pos-using-hmac); [Deliverect: Validate Requests](https://developers.deliverect.com/docs/how-do-i-validate-requests-from-deliverect)
- UrbanPiper supports only a static token header per webhook endpoint, with no request signing documented. — [UrbanPiper Webhooks-Callbacks](https://api-docs.urbanpiper.com/downstream/getting-started/webhooks-callbacks)
- Otter (TryOtter) uses OAuth 2.0 client credentials (`POST /v1/auth/token`), with per-store scoping through an `X-Store-Id` header. Webhooks are HMAC-signed, and the authentication type is chosen per endpoint. Apps and webhooks are registered manually through an Account Representative. — [API Evangelist Otter profile](https://github.com/api-evangelist/otter); [Otter Connect Quickstart](https://connect.tryotter.com/docs/guides-quickstart/) (secondary source for some details)

**External IDs / idempotency**
- HubRise has two references: `ref`, the external ID (uniqueness not enforced), and `private_ref`, unique and used for lookups. The docs do not state what happens when an order is created with a duplicate `private_ref`. — [HubRise Orders API](https://www.hubrise.com/developers/api/order-management)
- The Uber webhook carries `resource_id` (order_id), `resource_href` and `user_id` (store_id). The webhook guide gives no explicit idempotency-key guidance. — [Uber Eats Webhooks](https://developer.uber.com/docs/eats/guides/webhooks)
- Deliverect matches items to the POS by PLU, for example through its "mark products out of stock by PLU" endpoint. — [Deliverect channel endpoints](https://developers.deliverect.com/reference/channel_endpoints) (search extract)

**Sandbox and versioning**
- Deliverect has a staging host (`https://api.staging.deliverect.com`) and a production host (`https://api.deliverect.com`). Its docs are versioned by path (v1.1, v2.0, v3.0, v3.0-ordering-experience). — [Deliverect Update Order Status](https://developers.deliverect.com/v1.1-restaurants/reference/update-order-status)
- UrbanPiper has a sandbox, `pos-int` Atlas, with "Developer Tools" for placing test orders, available only in the sandbox. — [UrbanPiper Developer Tools](https://api-docs.urbanpiper.com/downstream/getting-started/developer-tools); [UrbanPiper Testing and Validation](https://api-docs.urbanpiper.com/downstream/integration-certification/testing-and-validation)
- Uber versions behaviour per store ("API version 1.0.0" stores get different event names), and access requires written approval. — [Uber Eats Webhooks](https://developer.uber.com/docs/eats/guides/webhooks); [Uber Eats Marketplace APIs intro](https://developer.uber.com/docs/eats/introduction)
- DoorDash Marketplace APIs are "not yet generally available": partners apply through the Developer Portal and must validate a test order. — [DoorDash Validate Test Order](https://developer.doordash.com/en-US/docs/marketplace/overview/getting_started/validate_test_order/)

### Inferences
- **RIZQIM catalog model recommendation.** Copy HubRise's structure almost one-to-one:
  - `categories` tree (`ref`, `parent_ref`)
  - `products`, each with 1..n `skus`
  - `option_lists` with `min/max_selections` (avoid the old single/multiple enum)
  - `options`
  - `deals` with `lines` and `pricing_effect`
  - typed `charges`
  - `restrictions` and `price_overrides` keyed by a `channel_ref` (HubRise's `variant_refs`) plus dow/time/date

  This covers channel prices (Rizqim marketplace vs Yandex Eats vs Uzum Tezkor vs dine-in) without duplicating menus. Keep the stock/stop-list as a separate, high-frequency `inventory` resource, not inside the catalog, because availability changes far more often than the menu.
- **Order model.** Store lines as snapshots (name, unit price, options, `sku_ref`) plus `ref`s pointing at the catalog, so orders stay readable after menu changes. Keep `discounts`, `charges` (delivery fee, service fee, Rizqim commission shown separately), `payments` (with method: cash, Payme, Click, Uzum, card) and the computed `total` + `payment_discrepancy` as separate arrays. This matters in Uzbekistan, where cash on delivery and prepaid wallets coexist.
- **Status model.** Use HubRise-style string statuses (`new, received, accepted, in_preparation, awaiting_collection, in_delivery, completed` + `rejected, cancelled, delivery_failed`), and optionally publish Deliverect-style numeric codes for ordering. Enforce an accept deadline, as Uber's 11.5 minutes and DoorDash's few-minute window do. For a fast-food market, a shorter RIZQIM deadline (e.g. 3–5 min), with an escalation (Telegram/robocall) at about 60–90 seconds, mirrors Uber's 90-second robocall.
- **Delivery and signing.** Support both active webhooks and passive polling, as HubRise does. Many small POS vendors in Uzbekistan (and on-prem iiko installs behind NAT) cannot expose public HTTPS endpoints, so polling is required, not optional. Sign with `X-Rizqim-Signature` set to HMAC-SHA256 over the raw body. Add a timestamp header and a 5-minute tolerance to block replays (an addition beyond what HubRise and Uber document). Retry with exponential backoff, for example the HubRise schedule of 6 tries over about 1 hour. Include an `event_id` so receivers can dedupe.
- **Idempotency.** Require partners to send an `Idempotency-Key` (or a unique `external_id` per partner) on every write. Unlike HubRise, document and enforce the behaviour on a duplicate: return the existing resource with 200/409, never create a second order.

### Gaps
- I could not fetch Deliverect's full order and menu JSON schema (direct fetches returned 404), so Deliverect's field names for modifiers, bundles and taxes are not captured here.
- Otter's public order and menu schema could not be read; registration is gated through an account representative.
- No official HubRise statement on rate limits was found. The callback page only mentions a "daily API rate limit".
- Square and Toast order/menu schemas were not researched in depth.
- The DoorDash exact unconfirmed-order window ("3 to 8 minutes") came from a truncated extract and needs verification.

---

## 2. HubRise in depth: data model, connection flow, pricing, open-source references

### Takeaway
HubRise is the clearest public reference for an "open hub". It has one canonical catalog/order/customer model and OAuth2 connections scoped per location or account. Signed webhooks with a polling fallback share one event store. The price is a flat €35/location/month for the restaurant, with no per-order fee for standard locations. Its open-source footprint is small: Ruby client and app plus a sample "TinyTablet" order printer.

### Cited Findings
- **Connection flow:**
  - the app redirects to `https://manager.hubrise.com/oauth2/v1/authorize` with `client_id`, `redirect_uri`, `scope` and an optional `state`/`country`/`account_name`/`location_name` for prefilled signup
  - HubRise redirects back with a `code`, which is single-use and valid for 10 minutes
  - the code is exchanged server-side (HTTP Basic auth) for an access token that does not expire but can become invalid on non-payment, revocation or blocking (the API then returns 401)
  - tokens can be revoked through `/oauth2/v1/revoke` — [HubRise Authentication](https://www.hubrise.com/developers/api/authentication) (live docs, accessed 2026-10-25)
- **Scopes:**
  - level: `location[...]` or `account[...]`, plus `profile`/`profile_with_email`
  - permissions are `resource.read|write` over `orders`, `customer_list`, `catalog`, `inventory`, `all_customer_lists`, `all_catalogs`, e.g. `location[orders.write,customer_list.write,catalog.read]`
  - a location-level token is bound to one location, so API calls need no location ID
  - the token is sent in an `X-Access-Token` header to `https://api.hubrise.com/v1/...` — [HubRise Authentication](https://www.hubrise.com/developers/api/authentication)
- **Data model:**
  - catalog: categories, products, skus, option_lists, options, deals, discounts, charges, variants, restrictions and price_overrides
  - orders: items, options, deals, discounts, charges, payments, customer and status/timing
  - details are in section 1 — [HubRise Catalog API](https://www.hubrise.com/developers/api/catalog-management); [HubRise Orders API](https://www.hubrise.com/developers/api/order-management)
- **Callbacks:** one per connection, active or passive, signed with `X-HubRise-Hmac-SHA256`, a 20-second ack window, 6 retries (1 to 32 minutes) and no echo of the client's own events. — [HubRise Callbacks](https://www.hubrise.com/developers/api/callbacks)
- **Pricing**, in EUR excluding VAT, paid by the restaurant:
  - Standard: €35/location/month, no commitment, no setup fee, unlimited connections, no order limit for standard locations
  - Chains: €35 for locations 1–5, €30 for 6–30, €27 for 31+
  - Dark kitchens (multiple virtual brands): €35 up to 1,500 orders/month, €55 to 3,000, €75 to 4,500, then +€20 per additional 1,500, plus a €25 one-time setup per brand per platform
  - HubRise offers a partnership programme with "grey or white-label options" — [HubRise Pricing](https://www.hubrise.com/pricing) (accessed 2026-10-25)
- The pricing page says nothing about developer fees or revenue share with connector developers. — [HubRise Pricing](https://www.hubrise.com/pricing)
- **Open source:** the HubRise GitHub org has the following public repos:
  - `ruby-client`, updated Feb 2026
  - `ruby-app`, updated May 2026
  - `rails-initializer`
  - `ruby-money`
  - `tiny-tablet`, "a static HTML/JS app that prints orders received from HubRise", updated Jan 2023
  - a fork of `act-fluent-logger-rails`

  Most are MIT licensed. — [github.com/hubrise](https://github.com/hubrise) (accessed 2026-10-25)
- API Evangelist maintains third-party, unofficial profiles of the UrbanPiper and Otter API surfaces on GitHub. — [api-evangelist/urbanpiper](https://github.com/api-evangelist/urbanpiper/blob/main/README.md); [api-evangelist/otter](https://github.com/api-evangelist/otter)
- A third-party PHP package for the Uber Eats API, `omnifood/ubereats`, exists. Its README says it was built from public docs and recorded responses, not tested against the live API. — [packagist omnifood/ubereats](https://packagist.org/packages/omnifood/ubereats)

### Inferences
- HubRise's model is good evidence that a small team can run an "open hub". It sells a cheap flat per-location subscription to the merchant and gives connector developers a free, documented API, so the connectors become its distribution. RIZQIM can do the same: the Partner API costs nothing for POS vendors to integrate, and Rizqim earns from marketplace commission rather than per-location hub fees.
- Copy the HubRise OAuth pattern so a restaurant can link its POS from inside the POS UI: the POS vendor is the OAuth client, the restaurant owner approves location-scoped access, and the token is bound to one location. This is better than sharing static API keys with each restaurant.
- `tiny-tablet` is a useful pattern for Rizqim's fallback: a static web/Telegram "order printer" for restaurants without any POS integration.

### Gaps
- GitHub search for "restaurant menu schema", "open menu spec", "OpenAPI food ordering" and "deliverect api client" could not be run (the GitHub search API was blocked in this session), so no ranked list of open-source reference implementations is provided. A manual follow-up search on github.com is recommended. Known candidate to check: the OpenMenu format (not verified here).
- HubRise developer and partner commercial terms (revenue share, listing review, certification requirements) are not published on the pages read.
- HubRise rate-limit numbers and sandbox/test-account process were not found on the pages read.

---

## 3. Partner program mechanics and monetization (certification, marketplace listing, revenue share, per-location pricing)

### Takeaway
There are two monetization patterns:
- **Hubs** (HubRise, Deliverect, Otter, UrbanPiper, Chowly, Itsacheckmate) charge the restaurant per location per month. Only HubRise publishes a list price (€35). Otter's entry price is reported at about $19/month. Deliverect quotes privately, reportedly about €49–99.
- **POS and marketplace platforms** (Toast, Square, Uber, DoorDash) gate API access behind application, approval and certification. They monetize partners through revenue share; Toast is reported to take a large share of partner revenue on joint customers.

### Cited Findings
- **Deliverect** does not publish a fixed per-location price. Its pricing page is an estimator (order volume, order value, locations), with "contact us" for 50+ locations. — [Deliverect pricing](https://website-prod-assets.deliverect.com/en/pricing); [Toolradar Deliverect pricing](https://toolradar.com/tools/deliverect/pricing)
- A competitor blog (Fooderise, biased) estimates Deliverect at about €49–99/location/month with an annual commitment. — [Fooderise vs Deliverect](https://www.fooderise.com/en/compare/fooderise-vs-deliverect) (2026; competitor source, unverified)
- **Otter:** a Starter plan at about $19/month and a "Main" plan at about $49–59/month. Add-ons are reported at about $29 per KDS screen and about $30/month for loyalty, plus per-order fees on direct orders. A reported 24-month standard commitment comes from user feedback. — [Fooderise Otter pricing 2026](https://www.fooderise.com/en/blog/otter-prix-tarifs-comparaison-restaurateurs-2026) (competitor source, unverified); [Capterra Otter](https://www.capterra.com/p/275512/Hubster/)
- **HubRise:** €35/€30/€27 per location by tier, and dark-kitchen pricing by order volume (see section 2). — [HubRise Pricing](https://www.hubrise.com/pricing)
- **Chowly / Itsacheckmate / Cuboh:**
  - no published per-location pricing found
  - one Capterra reviewer ranked Chowly the most expensive and Itsacheckmate the cheapest, with Cuboh in between (anecdotal) — [Capterra Cuboh reviews](https://www.capterra.com/p/199007/Cuboh/reviews/)
  - Cuboh is listed by PitchBook as acquired by ChowNow on 2024-03-28 (single source) — [PitchBook Cuboh](https://pitchbook.com/profiles/company/268521-85)
- **Ordermark/Nextbite:** Nextbite sold the U.S. Ordermark business to India's UrbanPiper after Nextbite layoffs. The deal was reported in 2023; one database lists the date as 2023-01-01. UrbanPiper said the deal adds "extensive point of sale (POS) integrations". — [Restaurant Dive](https://www.restaurantdive.com/news/Nextbite-sells-Ordermark-US-to-Urbanpiper/652643/); [NRN](https://www.nrn.com/restaurant-automation/ordermark-u-s-business-sold-to-indian-software-company-in-wake-of-nextbite-layoffs); [Restaurant Business](https://www.restaurantbusinessonline.com/technology/nextbite-sells-ordermark-business-india-based-urbanpiper)
- **Toast:**
  - partner API access goes through a multi-stage partner process that ends in certification, and the fees are not published — [Katalyst blog](https://www.katalystos.com/blog/pos-api-access-and-what-integrations-cost) (secondary)
  - 2019 reporting said Toast pays $500 per lead a partner sends and takes "30% of ongoing revenue in perpetuity" from partners on joint customers, replacing an earlier $20/location/month integration fee — [Reforming Retail, 2019-07-16](https://reformingretail.com/index.php/2019/07/16/as-we-predicted-toast-has-greatly-increased-their-pos-integration-fees/)
  - this is older than the 2023–2026 window and is included only as the most recent public figure found; current terms are unverified
- **Square:**
  - publishing in the Square App Marketplace automatically makes a developer an "app partner", and any developer may list after completing the required steps and a technical review (Square recommends submitting marketing content with the app)
  - listed partners "may be eligible for revenue share for referrals", and some can apply for a partner manager and profit share — [Square App Partner](https://squareup.com/gb/en/partnerships/app-partner)
  - Square's separate Solutions Partner program pays a percentage of adjusted payment-processing revenue, capped at 24 months — [Square Solutions Partner](https://squareup.com/us/en/partnerships/solutions-partner-program)
- **Deliverect:** a production HMAC secret is issued "when you move to our production environment" and a secure HMAC secret must be set before certification. This implies a staged staging → certification → production flow. — [Deliverect HMAC docs](https://developers.deliverect.com/v3.0/docs/validating-orders-in-pos-using-hmac)
- **UrbanPiper** publishes a dedicated "Integration Certification → Testing and Validation" section for POS partners. — [UrbanPiper Testing and Validation](https://api-docs.urbanpiper.com/downstream/integration-certification/testing-and-validation)
- **Uber Eats:**
  - API access may require written approval
  - Uber publishes "Quality & Performance Standards" for integrators — [Uber Eats intro](https://developer.uber.com/docs/eats/introduction); [Uber Quality & Performance](https://developer.uber.com/docs/eats/quality-and-performance)
- **DoorDash:** Marketplace APIs are not generally available, and partners apply through the Developer Portal and validate a test order. — [DoorDash Validate Test Order](https://developer.doordash.com/en-US/docs/marketplace/overview/getting_started/validate_test_order/)
- **Otter:** application registration and webhook registration both go through an Account Representative. — [API Evangelist Otter profile](https://github.com/api-evangelist/otter)

### Inferences
- **For RIZQIM (a marketplace, not a hub):**
  - do not charge POS vendors to integrate; charge restaurants marketplace commission as today
  - consider a small optional monthly "integration" add-on only once integrations deliver measurable labour savings
  - do not copy Toast's 30% take; in a young market it would deter integrators
- **Partner program structure:**
  1. self-serve sandbox with test stores and a "place test order" tool (UrbanPiper Developer Tools pattern)
  2. a certification checklist run in the sandbox, e.g. accept within the SLA, all statuses up to `completed`, rejection with reason, menu push with modifiers/combos, stop-list toggle, duplicate-delivery handling, HMAC verification
  3. a production secret issued only after certification (Deliverect pattern)
  4. a listing in a "RIZQIM integrations" directory in the partner panel
- **Incentives for local POS vendors** (iiko dealers, Poster, local fiscal-POS makers): a referral bonus per restaurant activated on the marketplace, or a revenue share of Rizqim commission for N months (cf. Square's referral rev-share and 24-month cap). This is cheaper than building every connector in-house.

### Gaps
- No primary 2023–2026 source for Deliverect, Otter, Chowly or Itsacheckmate list prices; the figures are from competitor blogs and reviews.
- Current Toast partner fee terms (post-2019) are not public.
- Square's exact referral revenue-share percentage was not found.
- Deliverect's certification checklist contents were not retrieved.

---

## 4. Common failure modes and how hubs handle them (mapping drift, availability lag, duplicates, timeouts) and admin tooling

### Takeaway
The recurring failure classes are:
- **Unacknowledged or timed-out orders**, handled with hard accept deadlines, robocalls and auto-cancel.
- **Duplicate deliveries from retries**, handled with stable IDs and dedupe.
- **Menu mapping drift**, when refs/PLUs change between the POS and the channel.
- **Availability lag**, because snooze and menu push are asynchronous and take minutes.
- **Payment/total mismatches.**

Hubs mitigate these with acknowledgement windows, signed retries, async status callbacks for menu operations, explicit "failed/duplicate/parsed" statuses, and webhook logs in the dashboard.

### Cited Findings
- **Timeouts and unacknowledged orders:**
  - Uber auto-cancels orders not accepted or denied within 11.5 minutes and triggers a robocall at 90 seconds — [Uber Eats Webhooks](https://developer.uber.com/docs/eats/guides/webhooks)
  - DoorDash recommends async confirmation if processing regularly exceeds about 20 seconds; its test-order guide advises raising the webhook timeout, removing long DB calls, or returning 200 and processing asynchronously when 5xx/timeouts occur — [DoorDash Receive orders](https://developer.doordash.com/en-US/docs/marketplace/how_to/order_integration/); [DoorDash Validate Test Order](https://developer.doordash.com/en-US/docs/marketplace/overview/getting_started/validate_test_order/)
  - HubRise requires an acknowledgement within 20 seconds, retries 6 times, then deletes the event — [HubRise Callbacks](https://www.hubrise.com/developers/api/callbacks)
- **Stuck/undelivered orders:** Deliverect surfaces "Parsed" (received by Deliverect but not by the POS), "Received by POS", "Failed", "Failed but handled", "Duplicate" and "Cancel failed" as distinct operator-visible states. — [Deliverect help: Order statuses](https://help.deliverect.com/en/articles/7979014-order-statuses)
- **Explicit failure path:** a Deliverect POS that cannot process an order must set 120 FAILED so the channel informs the customer, and 95 AUTO_FINALIZED is a fallback when no updates arrive. — [Deliverect Update Order Status](https://developers.deliverect.com/v1.1-restaurants/reference/update-order-status); [Deliverect Order Statuses list](https://developers.deliverect.com/page/order-status)
- **Duplicates:**
  - UrbanPiper re-pushes the same order whenever the POS responds with anything other than HTTP 200, so a POS that processes the order and then returns e.g. 201 or times out will receive it again — [UrbanPiper Order Relay FAQ](https://urbanpiper.helpjuice.com/55452-faqs/order-relay-webhook)
  - HubRise `ref` uniqueness is "recommended but not enforced", while `private_ref` is the unique lookup key — [HubRise Orders API](https://www.hubrise.com/developers/api/order-management)
- **Echo loops:** a HubRise client does not receive callbacks for events it created, and `own_order` subscriptions limit events to orders the client created. — [HubRise Callbacks](https://www.hubrise.com/developers/api/callbacks)
- **Menu mapping drift:**
  - HubRise requires `ref` values to be unique per type and "stable", because they are carried into orders — [HubRise Catalog API](https://www.hubrise.com/developers/api/catalog-management)
  - HubRise rejects orders whose deal lines reference a non-existent `deal_key` — [HubRise Orders API](https://www.hubrise.com/developers/api/order-management)
  - Deliverect matches POS products by PLU — [Deliverect channel endpoints](https://developers.deliverect.com/reference/channel_endpoints)
- **Availability lag:**
  - Deliverect sends snooze/unsnooze and busy-mode webhooks to channels, but only for items on a menu that was successfully published; snoozes auto-expire when the timer lapses — [Deliverect channel webhooks](https://developers.deliverect.com/v3.0/reference/channel-webhooks); [Deliverect Snooze/Unsnooze](https://developers.deliverect.com/v3.0-ordering-experience/reference/channel_snooze)
  - on Deliveroo, a menu push is needed after any availability change and changes can take about 5 minutes to appear — [Deliverect Deliveroo integration overview](https://help.deliverect.com/en/articles/7979326-deliveroo-integration-overview)
  - DoorDash menu jobs are async, with results through a Menu Status webhook (average under 1 minute) — [DoorDash Menu status callback](https://developer.doordash.com/en-US/docs/marketplace/how_to/menu_status_callback/)
  - UrbanPiper item/option on/off actions are processed per aggregator and confirmed asynchronously by callback — [UrbanPiper Webhooks-Callbacks](https://api-docs.urbanpiper.com/downstream/getting-started/webhooks-callbacks)
- **Payment mismatches:** HubRise stores any difference between payments and the order total in `payment_discrepancy`, and omitted payments mean "unpaid". — [HubRise Orders API](https://www.hubrise.com/developers/api/order-management)
- **Admin tooling:**
  - UrbanPiper's Atlas dashboard manages webhooks (Settings → Webhooks, per-event headers, retry toggle) and sandbox Developer Tools for test orders — [UrbanPiper Webhooks-Callbacks](https://api-docs.urbanpiper.com/downstream/getting-started/webhooks-callbacks); [UrbanPiper Developer Tools](https://api-docs.urbanpiper.com/downstream/getting-started/developer-tools)
  - HubRise exposes pending (unacknowledged) events through `GET /callback/events` for inspection and replay — [HubRise Callbacks](https://www.hubrise.com/developers/api/callbacks)
  - Deliverect provides a "Verify orders received by a webhook" guide for partners — [Deliverect: Verify orders received](https://developers.deliverect.com/docs/how-to-i-verify-orders-received-to-webhook)

### Inferences
- **Recommended RIZQIM safeguards:**
  1. Return 2xx fast and process asynchronously. Accept any 2xx as acknowledgement, not only 200, to avoid UrbanPiper's 201-duplicate trap.
  2. Every event and order carries an immutable `id` + `event_id`. Partners dedupe on these, and Rizqim dedupes partner writes on `Idempotency-Key`.
  3. Run an accept-deadline watchdog: if the POS has not acknowledged within N seconds, fall back to the Rizqim staff panel/Telegram, then auto-cancel with a customer refund.
  4. Use a separate fast `PATCH /inventory` (stop-list) endpoint, so a full menu push is not needed for out-of-stock changes.
  5. Return async job status (`menu_job.status`, per-item errors) for menu pushes.
  6. Show stable `ref` validation and an "unmapped items" report.
  7. Keep a `payment_discrepancy`-style field.
- **Admin "Integration health" screen (superadmin + restaurant panel):**
  - per-connection last-seen/heartbeat
  - an event log with request/response bodies, signature result, attempts and next retry, with a "replay" button
  - an orders-stuck-in-`new`/`received` queue with timers
  - a menu-push job history with per-item validation errors
  - a mapping table of POS ref ↔ Rizqim ref with drift warnings (refs in orders that are missing from the catalog)
  - a stop-list audit trail
- **Internal connectors:** treat iiko, Poster, Yandex Eats and Uzum Tezkor as "adapters" that translate to and from the same canonical RIZQIM model, as Deliverect, Otter and HubRise do for every channel. Each external system then needs only one mapping, and the admin tooling is shared.

### Gaps
- No published quantitative data (rates of duplicate orders, mapping errors, availability lag) from hubs was found.
- No engineering blog post from Deliverect, Otter or UrbanPiper on these failure modes was retrieved within the tool budget.
- The internal connectors' target APIs (iiko Cloud API, Poster API, Yandex Eats vendor API, Uzum Tezkor partner API) were not researched in this pass; their webhook/signing/rate-limit specifics remain open.

---

## 5. Synthesis: recommended shape of the RIZQIM Partner API (v1)

### Takeaway
Build a HubRise-like open API: canonical catalog + inventory + order + location resources, OAuth2 location-scoped connections, HMAC-signed webhooks with a polling fallback, and a sandbox with test orders and a certification gate. Internal connectors (iiko, Poster, Yandex Eats, Uzum Tezkor) map into the same model.

### Cited Findings
- These are the precedents each recommendation is based on (sources in sections 1–4):
  - HubRise: OAuth2 location/account scopes, catalog/order model, active and passive callbacks, `X-HubRise-Hmac-SHA256`, 20s ack, 6 retries — [HubRise Authentication](https://www.hubrise.com/developers/api/authentication); [HubRise Callbacks](https://www.hubrise.com/developers/api/callbacks)
  - Uber: `X-Uber-Signature`, 7 sends, 11.5-minute accept deadline, 90-second robocall — [Uber Eats Webhooks](https://developer.uber.com/docs/eats/guides/webhooks)
  - DoorDash: sync 2xx or async 202 + PATCH confirmation, async menu jobs with status webhook — [DoorDash Receive orders](https://developer.doordash.com/en-US/docs/marketplace/how_to/order_integration/); [DoorDash Menu integration](https://developer.doordash.com/en-US/docs/marketplace/how_to/menu_integration/)
  - Deliverect: numeric statuses, FAILED/AUTO_FINALIZED, staging/production secrets, snooze/busy webhooks — [Deliverect Order Statuses](https://developers.deliverect.com/page/order-status); [Deliverect channel webhooks](https://developers.deliverect.com/v3.0/reference/channel-webhooks)
  - UrbanPiper: sandbox Developer Tools and certification section — [UrbanPiper Testing and Validation](https://api-docs.urbanpiper.com/downstream/integration-certification/testing-and-validation)

### Inferences (recommendations, not facts)
- **Resources (v1):**
  - `GET /v1/location`, `PATCH /v1/location` (open/busy/closed, prep-time offset)
  - `PUT /v1/catalog` (full push, async → `catalog_job`), `GET /v1/catalog_jobs/{id}`
  - `PATCH /v1/inventory` (stop-list by `sku_ref`/`option_ref`, with optional `until`)
  - `GET /v1/orders?status=…&updated_after=…`, `GET /v1/orders/{id}`
  - `POST /v1/orders/{id}/accept` (with `confirmed_time`/prep minutes), `POST /v1/orders/{id}/reject` (with `reason` enum), `PATCH /v1/orders/{id}` (status)
  - `GET /v1/events` + `DELETE /v1/events/{id}` (polling), `PUT /v1/webhook`
- **Events:** `order.created`, `order.updated` (customer cancel, courier status), `order.cancelled`, `catalog_job.completed`, `location.updated`. Each carries `event_id`, `occurred_at`, `resource_id`, and `previous_state`/`new_state` diffs (HubRise pattern).
- **Security:**
  - OAuth2 authorization-code flow for POS vendors (the restaurant approves in the Rizqim partner panel), producing location-bound tokens
  - webhooks signed with `X-Rizqim-Signature: t=<unix>,v1=<hex HMAC-SHA256(secret, t + "." + raw_body)>` (timestamp added for replay protection)
  - a separate sandbox secret
  - IP allowlisting is optional
- **Reliability:** ack any 2xx within 10–20s; retries at 1, 2, 4, 8, 16, 32 minutes; a dead-letter list visible in the admin panel; `Idempotency-Key` on all POSTs; dedupe on `event_id`.
- **Timing rules (proposal):**
  - the POS must accept or reject within 3 minutes for ASAP orders; if there is no response, escalate at 60 seconds (Telegram to the restaurant, an in-app alarm in the Rizqim staff app); auto-reject with refund at the deadline
  - scheduled orders become actionable N minutes before `expected_time`
- **Versioning:** date- or major-versioned path (`/v1`), additive changes only within a version, a `deprecated` marker in the schema (as HubRise does for `type` and `service_types`), and a changelog page. Publish an OpenAPI 3.1 spec. Rizqim already has P1-11 OpenAPI work in the repo history, which can host it.
- **Sandbox and certification:**
  - a sandbox tenant with test stores and a "simulate order / customer cancel / courier statuses" tool
  - an automated certification test run, after which the production client secret is issued
  - a listing in a "RIZQIM integrations" directory
- **Internal connectors:**
  - iiko/Poster (POS side): Rizqim acts as the "channel" that pushes orders into the POS and pulls menu/stop-list from it
  - Yandex Eats/Uzum Tezkor (marketplace side): Rizqim acts as the "hub" that pushes the menu and receives orders
  - both go through the same canonical model and the same event log/admin tooling

### Gaps
- None of the recommended timing values (3 minutes, 60-second escalation) are derived from Uzbekistan-specific data. They are extrapolated from Uber and DoorDash and need validation with local restaurants.
- Compatibility with the specific iiko, Poster, Yandex Eats and Uzum Tezkor APIs was not verified in this pass.
