# Aggregator APIs for POS integration (Yandex Eats, Uzum Tezkor, reference aggregators): implementation notes for Rizqim POS, as of Oct 2026

Labels: **[DOC]** = official developer documentation or spec read directly. **[VENDOR]** = a POS vendor's or integrator's own docs or marketing. **[PRESS]** = news. **[3P]** = unofficial third-party listing. All official docs were fetched 25 Oct 2026. Related market context (commissions, market shares, Wolt's exit, Uzum's onboarding bot) is already in `../Alipos va agregator POS modeli/uz_aggregators.md` and is not repeated here.

---

## 1. Yandex Eats (Yandex Eda): integration API, auth, order model, menu and stop-lists, certification, Uzbekistan

### Takeaway
Yandex Eats has a public OpenAPI spec, "API для интеграции сервиса Яндекс Еда" (v1.0-oas3, changelog entry dated 01.09.2026). It mostly uses a **pull model**: the POS hosts an OAuth2 client-credentials server, and Yandex calls it. Yandex calls `POST /order`, `GET /menu/{restaurantId}/composition` and `/availability`, `GET/PUT /order/{id}/status`, `DELETE /order/{id}` and others. A smaller push API (Vendor Management Integration API) lets the POS call Yandex to force a menu or stop-list reload, block or unblock a restaurant, validate courier codes, and pull order history for reconciliation. The confirmed endpoint paths match the ones in the brief exactly. A restaurant connects the integration from the Yandex Vendor cabinet by entering the POS's host, client_id and client_secret. A local Uzbek POS (REGOS) already ships exactly this pattern for "Yandex Eats".

### Cited Findings
**Model and spec [DOC]**
- Spec title "API для интеграции сервиса Яндекс Еда", version `1.0-oas3`. It covers the "Yandex" model (Yandex couriers) and the "Marketplace" model (restaurant's own couriers). All methods "должны быть реализованы на стороне партнера". "Общение происходит на основании pull-модели: сервис Яндекс Еда как клиент запрашивает информацию о ресурсе либо может создавать или обновлять ресурсы". The "Vendor Management Integration API" block is a push model, where Yandex acts as the server. — [API reference](https://yandex.ru/dev/eda-vendor/doc/ru/ref/); [OpenAPI JSON](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json); [llms.txt index](https://yandex.ru/dev/eda-vendor/doc/ru/llms.txt)
- The doc is for restaurants. Shops (retail) use a separate API with `/nomenclature/{placeId}/...` paths. — [О сервисе](https://yandex.ru/dev/eda-vendor/doc/ru/index.md); [retail API](https://yandex.ru/support/retail/ru/ref/)

**Endpoints the POS must host (Yandex calls them), from the OpenAPI spec [DOC]**
- Auth: `POST /security/oauth/token`. Security scheme: OAuth2 `clientCredentials`, tokenUrl `/security/oauth/token`, scopes `read` and `write`. "Партнер у себя должен реализовать OAuth2 авторизацию… метод для получения токена с client_id, client_secret, grant_type и scopes". The request is `application/x-www-form-urlencoded`, and the token is sent as `Authorization: Bearer`. There is no refresh token. The partner server must be given as a domain; IP addresses are not supported. — [OpenAPI JSON](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json); [retail auth page](https://yandex.ru/support/retail/ru/ref/authenticationPull)
- Menu:
  - `GET /menu/{restaurantId}/composition` returns the full menu (`application/vnd.eats.menu.composition.v2+json`). Items with a price of 0.00 or invalid data may be ignored.
  - `GET /menu/{restaurantId}/availability` returns the stop-list or stock (`application/vnd.eats.menu.availability.v2+json`): `items[{itemId, stock}]`, `modifiers[{modifierId, stock}]`, `combos[{comboId, stock}]`. "При 0 блюдо пропадает из выдачи". A missing stock value counts as 0.
  - `GET /menu/{restaurantId}/promos` returns promo dishes.
  - — [OpenAPI JSON](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json)
- `lastChange` (RFC3339 with fractional seconds) on the menu: if it has not changed, Yandex "может… принять решение о том, что обновлять меню не требуется". A changed date forces a reload. — [llms-full.txt](https://yandex.ru/dev/eda-vendor/doc/ru/llms-full.txt)
- Restaurants: `GET /restaurants` (the partner's list of venues) and `GET /restaurants/availability` (`places[{id, enabled}]`). Also `GET /places/{restaurantId}/zones`, `/zone/meta` and `/schedule`. — [OpenAPI JSON](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json)
- Orders:
  - `POST /order` creates the order in the POS. There are three schemas: `YandexOrder` (Yandex delivery), `MarketplaceOrder` (partner delivery) and `PickupOrder`. Content type is `application/vnd.eats.order.v2+json`. The response is `200 {result:"OK", orderId}`, where `orderId` is the POS's own ID ("Рекомендуется UUID4").
  - `GET /order/{orderId}` returns the order.
  - `PUT /order/{orderId}` updates the order. Yandex sends the whole order, and this "требуется согласование с сотрудниками Яндекса".
  - `DELETE /order/{orderId}` cancels the order. The body is `OrderCancellation{eatsId, comment}`.
  - `GET /order/{orderId}/status` returns the status the POS holds.
  - `PUT /order/{orderId}/status` is a status change pushed by Yandex: `TAKEN_BY_COURIER`, `DELIVERED` or `CANCELLED`, with `reason` (e.g. `place.unable_to_call`), `attributes` (e.g. `paid`), `comment` and `updatedAt`.
  - `PUT /order/{orderId}/courier` sends courier info. The `batches` field was added 30.06.2026.
  - — [OpenAPI JSON](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json); [changelog](https://yandex.ru/dev/eda-vendor/doc/ru/ref/changelog.md)
- Status machine (the `OrderStatus` enum): `NEW → ACCEPTED_BY_RESTAURANT → COOKING → READY → TAKEN_BY_COURIER → DELIVERED`. `CANCELLED` is allowed from any status, and transitions go only forward. Yandex **pulls** this status from the POS through `GET /order/{id}/status`. — [OpenAPI JSON](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json)
- Error semantics for `POST /order`:
  - `400` must return an `ErrorList` array.
  - `401` means an expired or missing token, and Yandex retries.
  - `406` (`UnavailableGoodsResponse` `{type:"unavailable_goods", message, goods:{id:name}}`) puts those dishes on stop. The customer is offered a changed order, which is re-sent if they agree. The dishes come back after the next stop-list poll if they are no longer listed.
  - `500` is an internal error.
  - — [OpenAPI JSON](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json)
- Order payload fields:
  - `paymentInfo.paymentType` is `CARD` (paid) or `CASH` (unpaid).
  - `itemsCost` is net of cart discounts. Marketplace orders add `deliveryFee`, `total` and `change`.
  - Items carry `id, name, quantity, price, original_price, weight, measure_unit, modifications[{id, group_id, name, quantity, price}]`.
  - Discount splits are given as the share paid by the partner and the share paid by Yandex.
  - Money uses ISO 4217 currency codes.
  - — [OpenAPI JSON](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json); [llms-full.txt](https://yandex.ru/dev/eda-vendor/doc/ru/llms-full.txt)
- Menu items carry `vat` (percent included in the price; defaults to 0) and an `excise` flag. The Russia-only `legalInfo` with GTIN marking was added 01.09.2026 ("Платформенная экономика" law). — [changelog](https://yandex.ru/dev/eda-vendor/doc/ru/ref/changelog.md)

**Push API, POS → Yandex (Vendor Management Integration API) [DOC]**
- `POST /oauth2/token` (client_id and client_secret, form-encoded).
- `POST /menu/import/initiation` `{restaurantId, operationType: menu | menu_stop_list | promo}` forces a reload of the menu or stop-list ("Принудительный запуск процесса обновления меню/стоп-листов").
- `POST /block` `{vendorInfo, from, to?, message, tag: technical_problem | too_busy}`, plus `/unblock` and `/v2/status`.
- `POST /platform/orders/{orderId}/codes/validate` validates the courier code.
- `POST /v1/orders-history` and `POST /v1/get-orders-details` fetch order history and details: at most 100 orders per call, 180 days of history.
- `POST /feedback-answer`, plus `POST /partner-integration/v1/place-info/v1/busy-mode-status`.
- The spec gives no server host for the push API (`servers: [{url:""}]`).
- — [OpenAPI JSON](https://yandex.ru/dev/eda-vendor/doc/ru/ref/external-api.openapi.json)

**Timeouts (retail spec; restaurant spec gives none) [DOC]**
- Retail-API timeouts:
  - token: 3 s
  - composition: 300 s / 100 s / 60 s, for up to 50 / up to 100 / more than 100 points
  - availability: 120 s
  - `POST /order`: **210 s**
  - `GET /order/{id}`: 5 s
  - `GET /order/{id}/status`: 5 s
  - `PUT /order/{id}/status`: 10 s
- — [Время ожидания ответов (retail)](https://yandex.ru/support/retail/ru/ref/overview/timeout.md)

**Testing and certification [DOC / 3P]**
- Yandex publishes a Postman "Коллекции для партнеров". It runs requests against the partner's host, covering the token, menu or stocks, and `POST/GET/PUT/DELETE /order`, `GET /order/{id}/status` and `GET /restaurants`. Built-in tests check:
  - Content-Type and the JSON schema
  - `lastChange` format and price > 0
  - "`mxikCodeUz` is exactly 17 symbols long… коды ИКПУ (актуально только для Узбекистана)"
  - measureUnit is one of г, мл, g or ml
  - unique IDs and that every categoryId exists
  - image links and response timeout
  - The menu or stock requests also check that a 401 comes back when no token is sent and a 404 for an invalid place_id.
  - — [llms-full.txt, Коллекции](https://yandex.ru/dev/eda-vendor/doc/ru/llms-full.txt); [collections page](https://yandex.ru/dev/eda-vendor/doc/ru/collections.md)
- Connection process (RU market): the restaurant applies in the Yandex Eats cabinet ("Поддержка → Интеграция → Подключение интеграции") and enters the POS's **host, client_id and client_secret** (and the outlet code). The integration is switched on **after a test order**. The menu must meet Yandex's rules, and there may be rating or volume requirements. Ready integrations: iiko (iikoWeb "внешнее меню" plus the order source "Яндекс.Еда"), r_keeper Delivery (separate licences: Delivery_Base, Delivery_Filial), Saby Presto, Контур.Маркет and 1С:Ресторан Фронт-офис. Anyone else can build their own implementation. — [agregatory.pro (integrator blog, 3P)](https://agregatory.pro/blog/yandex-eda-integratsiya-iiko)

**Uzbekistan**
- Local POS REGOS (Store Management) has a "Yandex Eats" integration. REGOS generates a **Client ID and Secret** ("Создать новый Client ID") and shows an **EndPoint** ("Адрес, на который поступают заказы от Yandex Eats"). The merchant signs its own contract with Yandex Eats and gives the Yandex manager the Client ID, Secret, Endpoint and warehouse code. Items with zero or no price are not sent, and missing barcodes are generated. [VENDOR] — [docs.regos.uz: Yandex Eats](https://docs.regos.uz/ru/regos-sm/operations/settings-and-security/integrations/yandex-eats)
- REGOS also had a separate, older **Express24** integration with a different design: a login and password for the Express24 API, an Excel file for the nomenclature (external_id = REGOS code), periodic sync, and a REGOS EndPoint for incoming orders. Orders arrive as "Утвержден". Items without an **ИКПУ** are not synced. Express24 manager phone: +998(71)205-57-35. [VENDOR] — [docs.regos.uz: Express24](https://docs.regos.uz/ru/regos-sm/operations/settings-and-security/integrations/e24)
- Yandex Uzbekistan bought part of Express24's assets on 25 Oct 2024 to build "единую технологическую платформу". The Express24 app kept running at the time. [PRESS] — [Kun.uz EN 25.10.2024](https://m.kun.uz/en/news/2024/10/25/yandex-uzbekistan-expands-with-acquisition-of-express24-assets-plans-to-build-unified-food-delivery-platform); [spot.uz 25.10.2024](https://www.spot.uz/ru/2024/10/25/express24/)
- AliPos (UZ POS) lists "Yandex Eats — Заказы напрямую в систему", along with Uzum Tezkor, Yemak, Robosell and Delever. [VENDOR; integrations page saved locally, URL presumed alipos.uz/integrations]

### Inferences
- Rizqim implements Yandex as a **server**. That means an OAuth2 token endpoint per tenant, plus `/menu/*`, `/restaurants`, `/order*` and `/order/{id}/status` on a public HTTPS **domain**. Yandex's `restaurantId` maps to a Rizqim branch's external ID.
- Statuses are **pulled**, so Rizqim's KDS and cashier status must be readable at all times through `GET /order/{id}/status`. "Accept" means setting `ACCEPTED_BY_RESTAURANT` locally.
- `POST /order` must answer quickly and idempotently. Yandex retries on 401 and timeouts, so dedupe on `eatsId`.
- The `mxikCodeUz` (ИКПУ, 17 digits) check in Yandex's test collection shows the same API serves Uzbekistan, and that each dish needs an ИКПУ. Rizqim already stores ИКПУ for fiscal receipts.
- Express24's old login/password plus Excel flow is legacy. After the 2024 merger, new UZ integrations most likely go through the Yandex Eats API. REGOS's current page reflects this.

### Gaps
- No official list of Yandex-certified POS vendors for **Uzbekistan**, and no published UZ thresholds. The Kyrgyz page cited elsewhere says rating ≥4.6 and ≥8 outlets or >7 orders/day; this is not verified for UZ.
- How often Yandex polls `GET /order/{id}/status` and `/menu/{id}/availability` is not published.
- Push-API production host: not in the spec. It is given at onboarding.
- No official restaurant-API timeout page was found. The 210 s for `POST /order` comes from the retail doc.
- Whether a third-party POS needs formal Yandex "certification" beyond the test order and Postman checks: no official statement found.

---

## 2. Uzum Tezkor: partner/integrator API, iiko/R-Keeper/JOWi, onboarding, payouts

### Takeaway
Uzum Tezkor publishes **no public API docs**. Uzbek POS vendors already integrate with it, though. REGOS documents a setup that is a copy of its Yandex Eats setup: the POS generates a Client ID and Secret, exposes an EndPoint, and Uzum's manager registers them. This strongly suggests Uzum Tezkor uses a **Yandex-compatible pull protocol** (the POS acts as server, with Client ID and Secret). The official partner page lists iiko, R-Keeper and JOWi. Access goes through a signed contract and a manager. Onboarding is through a Telegram bot, and settlement documents go through Didox.

### Cited Findings
- REGOS "Uzum Tezkor" integration ("Интеграция позволит получать заказы от Uzum Tezkor"):
  - Settings: **Client ID**, **Secret**, a "Создать новый Client ID" button, price type, order source, delivery method, and an **EndPoint** ("Адрес, на который поступают заказы от Uzum Tezkor").
  - "Для работы интеграции клиент должен самостоятельно заключить договор с Uzum Tezkor". The merchant gives Uzum's manager the Client ID, Secret, Endpoint and REGOS warehouse code.
  - Only "Товар" items are sent, and zero-price items are skipped. The page text even says "не передаётся в Yandex Eats" inside the Uzum section, which looks like a copy-paste from the Yandex page.
  - [VENDOR] — [docs.regos.uz: Uzum Tezkor](https://docs.regos.uz/ru/regos-sm/operations/settings-and-security/integrations/uzum-tezkor)
- Uzum Tezkor + iiko strategic partnership (Aug 2023). Orders go straight to the kitchen, and stop-lists and the current menu are pulled from the restaurant automatically ("автоматически забирать стоп-листы блюд из ресторана и транслировать пользователю самое актуальное меню"). It is said to cut delivery time by 5–7 minutes. At the time, about 1 in 3 partners (~150 Tashkent venues) used iiko. [PRESS] — [repost.uz](https://repost.uz/uzum-and-iiko); [the-tech.kz](https://the-tech.kz/uzum-tezkor-i-iiko-obyavili-o-strategicheskom-partnerstve-v-uzbekistane/)
- The partner page lists integrations **iiko, R-Keeper, JOWi**. It also offers onboarding via the Telegram bot @UzumTezkorbot_bot ("регистрация не более 5 минут"), regular payouts and transparent reports, and financial documents in **Didox**. Customers pay by card, cash, Uzum Bank or Uzum Nasiya. [VENDOR] — [promo.tezkor.com/partners](https://promo.tezkor.com/partners/uz) (via the earlier note `uz_aggregators.md`)
- Sept 2026: a new courier-only B2B service. Restaurants send orders taken on their own channels (phone, Telegram, app) to Uzum couriers; "данные заказа автоматически передаются в систему", and a courier task and tracking link are created. It runs in 13 cities. Neither the technical integration method nor the price is described. [PRESS, advertorial] — [spot.uz 14.09.2026](https://www.spot.uz/ru/2026/09/14/uzum-tezkor/)
- Other POS vendors that claim Uzum Tezkor order intake: AliPos ("Uzum Tezkor — заказы напрямую в систему") [VENDOR, local copy of alipos integrations page]; Mison (Yandex Eda, Uzum Tezkor, "Tez Chakana") [VENDOR] — [mison.uz](https://mison.uz/ru/).
- GitHub and web searches for "uzum tezkor api" and "uzum tezkor" repos found **nothing**. [search 25.10.2026]

### Inferences
- Uzum Tezkor appears to implement the **same OAuth2 client-credentials pull pattern as Yandex Eats**. The evidence: identical REGOS configuration fields, and the copy-pasted "Yandex Eats" wording. Uzum Tezkor's tech team has ex-Express24/Yandex-style RU-market heritage, but that is unconfirmed. It is likely that Rizqim can build one "Yandex-style vendor server" and parameterise it per aggregator. **This must be confirmed with Uzum's integration manager, who should be asked for the spec.**
- Getting access means the restaurant signs with Uzum. Then Rizqim, as the integrator, hands over Client ID, Secret, Endpoint and branch code. Ask Uzum whether it keeps a list of approved integrators.
- Payouts and reports are in Didox (e-invoices and acts), so reconciliation in Rizqim will be a manual or imported report. No API for settlements was found.

### Gaps
- Uzum Tezkor's actual endpoint paths, status set, menu format, timeouts, test environment and integrator approval process: not public. They need a direct request to the integration manager or a partner's REGOS or AliPos contact.
- Whether Uzum has a push API (block or unblock, forced menu reload) like Yandex's: unknown.
- The integration route for the Sept 2026 courier-only service (API or cabinet): unknown.

---

## 3. Reference aggregators (Wolt, Glovo, Delivery Hero/Talabat, Uber Eats, DoorDash): common patterns for a generic adapter

### Takeaway
Western aggregators **push** orders by webhook. The POS then **calls back** to accept or reject, mark ready and so on, and **pushes** menus, availability and store status. Yandex (and probably Uzum) work the other way round: the aggregator **pulls** from the POS. A generic adapter therefore needs both an inbound-server side and an outbound-client side, normalised to one internal order state machine. Common to all of them: an HMAC or token on the webhook, fast 2xx acknowledgment then asynchronous processing, idempotency, hard accept deadlines (DoorDash 3–8 min, Uber 11.5 min), auto-accept defaults, stop-list as item-level availability, temporary store closing, and a gated partner programme with a staging store before go-live.

### Cited Findings
**Wolt (Order, Menu and Venue APIs) [DOC]**
- Ping-and-pull: webhooks carry only the order ID, venue ID and status, plus a `resource_url`. The POS fetches the full order with `GET /orders/{orderId}` or `GET /v2/orders/{orderId}`. The webhook URL is given to the Wolt account manager. — [Wolt webhook](https://developer.wolt.com/docs/webhook); [Integration basics](https://developer.wolt.com/docs/partner-integrations)
- Signature: HMAC-SHA256 over the raw body using the client secret (≥128-bit), hex, in the `WOLT-SIGNATURE` header. Wolt requires a `200`. Retries come at 5 s, then two more at 5 s intervals. Webhook events include CREATED, PRODUCTION, READY, DELIVERED, CANCELED, courier-nearby (sent prep-time minutes before arrival), pick-up completed, VENUE_OFFLINE_ALERT, OPENING_HOURS_UPDATED and reviews. — [Wolt webhook](https://developer.wolt.com/docs/webhook)
- Order endpoints: `PUT /orders/{id}/accept`, `/reject`, `/ready`, `/delivered`, `/confirm-preorder`, `/sent-to-pos`, `/replace-items`, `/self-delivery/accept`, `/delivery/eta`, `/pickup-completed`; `POST /orders/{id}/refund-items`. The order payload has `order_handling.acceptance_status` (e.g. `auto_accepted_by_wolt`) and `preparation_time_seconds`. — [Wolt Order API](https://developer.wolt.com/docs/api/order)
- Menu: `POST /v1/restaurants/{venueId}/menu` (full push), `GET /v2/venues/{venueId}/menu`, `PATCH /venues/{venueId}/items` (availability or price), `PATCH /venues/{venueId}/items/inventory`, `PATCH /venues/{venueId}/options/values`. Venue: `PATCH /venues/{venueId}/online`, `GET /venues/{venueId}/status`, `PATCH /venues/{venueId}/opening-times`, `PUT /venues/{venueId}/special-opening-times`. — [Wolt Menu API](https://developer.wolt.com/docs/api/menu); [Wolt Venue API](https://developer.wolt.com/docs/api/venue)
- Order types: instant, takeaway, self-delivery, and scheduled (up to 7 days ahead; must be confirmed via `confirm-preorder`). — [Restaurant Order Guide](https://developer.wolt.com/docs/orderrestaurantguide)
- Wolt left Uzbekistan on 5 Mar 2026, so it is reference only. — see `uz_aggregators.md` ([Kun.uz](https://kun.uz/en/01543038))

**Glovo Partners API [DOC: OpenAPI `definition.yaml`]**
- Servers: `https://stageapi.glovoapp.com` (test) and `https://api.glovoapp.com` (production). — [Glovo Partners API](https://api-docs.glovoapp.com/partners/index.html) ([definition.yaml](https://api-docs.glovoapp.com/partners/definition.yaml))
- Process:
  1. Email partner.integrationseu@glovoapp.com with the stage URLs: order dispatched (mandatory), picked up and cancelled (optional), plus the countries.
  2. Get the stage webhook and Jira access.
  3. Build and test on a staging store.
  4. Develop the plugin for production.
  5. Get the production webhook.
  6. Roll out together with the local teams, mapping store and external IDs.
  - Webhook URLs must be reachable from any IP. — same source
- Auth: one shared token per integration, sent as `Authorization: <token>`. Glovo sends the same header on its webhooks, so the POS can verify them. Webhooks: `orderDispatched`, `orderPickedUp`, `orderCancelled` (the last only if the order was already dispatched). The dispatch is timed to the courier's ETA minus prep time. — same source
- Endpoints:
  - `PUT /webhook/stores/{storeId}/orders/{orderId}/status`; or `PUT /api/v0/integrations/orders/{orderId}/accept`, `/ready_for_pickup`, `/out_for_delivery`, `/customer_picked_up`; and `PUT /api/v1/.../delivered`
  - `POST .../replace_products`, `.../change_price`
  - `POST /webhook/stores/{storeId}/menu` (async, returns a transactionId; check with `GET .../menu/{transactionId}`)
  - `PATCH .../products/{productId}`, `PATCH .../attributes/{attributeId}`, `POST .../menu/updates` (bulk)
  - `PUT/GET/DELETE .../closing` (temporary close)
  - `POST /paris/menu/validate`
  - — same source
- Status rule: "`ACCEPTED`… if you don't accept the order we will still move forward with the order, as we don't require an acceptance to proceed." In other words, the model is auto-accept. — same source
- Third-party hubs (Deliverect, Sinqro) say Glovo access needs Glovo approval and takes 3–5 business days to set up, with orders auto-accepted by default. [VENDOR] — [Deliverect help](https://help.deliverect.com/en/articles/7979360-glovo-integration-overview); [Sinqro](https://www.sinqro.com/store/glovo)

**Delivery Hero (Talabat, foodpanda, PedidosYa) POS integration [DOC + 3P]**
- Two APIs:
  - The **Plugin API**, implemented by the POS partner, receives orders and cancellations from the Integration Middleware.
  - The **Middleware API**, called by the POS, sends accept or reject, order prepared, vendor availability (open, closed, busy, unreachable) and the catalog.
  - Credentials (username, password, secret) are encrypted with the vendor's **PGP public key**. The plugin URL must be HTTPS with a valid certificate.
  - — [DH POS docs](https://developers.deliveryhero.com/documentation/pos.html)
- Flows: in the **Indirect** flow, orders are accepted on the DH Vendor App first ("Orders rejected or not accepted on the Delivery Hero Vendor App, will never be forwarded to the Vendor POS System"). In the **Direct** flow, the POS accepts or rejects; this is allowed only "case by case". Accepting requires a delivery time and the POS's own order ID. Rejecting requires a reason from the list. The POS must send "unreachable" when it is down. The Vendor App is the mandatory fallback. — same source
- Menu: the Catalog Import API. The legacy Menu Import is deprecated ("new implementations are not allowed"). The Order Report Service lists the last 24 hours of orders, for information only. — same source
- Go-live: request PGP credentials, develop, give the plugin URL to the local rep, test with a test vendor, roll out. Afterwards: give ≥24 h maintenance notice (mandatory in the Direct flow) and an escalation contact. DH may disable the integration if that contact is unresponsive. An NDA is needed before access. — same source; [talabat integration portal](https://integration.talabat.com/en/documentation/)
- Plugin API paths (unofficial listing): `POST /order/{remoteId}` (dispatch), `PUT /remoteId/{remoteId}/remoteOrder/{remoteOrderId}/posOrderStatus`, `GET /menuimport/{remoteId}`, `POST /{catalogImportCallback}`. Bearer token issued by DH. [3P] — [Jentic](https://jentic.com/apis/deliveryhero.com/deliveryhero-pos-plugin-api.md)

**Uber Eats Marketplace APIs [DOC]**
- Webhooks:
  - `orders.notification` (new order), `orders.cancel`, `orders.failure` (v1.0.0 stores), `orders.scheduled.notification`, `orders.release`
  - `store.provisioned` / `store.deprovisioned`
  - `store.status.changed` (needs the `eats.store.status.notification` scope)
  - `order.fulfillment_issues.resolved`
  - — [Uber webhooks guide](https://developer.uber.com/docs/eats/guides/webhooks)
- Signature: `X-Uber-Signature`, a lowercase hex HMAC-SHA256 of the raw body keyed with the client secret. Reply `200` with an empty body. Uber retries on 5xx, timeouts or network errors: first after 10 s, then 30, 60, 120 s…, up to 7 attempts. — same source
- Deadline: call `POST /eats/orders/{order_id}/accept_pos_order` or `/deny_pos_order` "within 11.5 minutes. Otherwise the order will time out and auto-cancel". An optional robocall fires after 90 s. — same source; [Order integration guide](https://developer.uber.com/docs/eats/guides/order-integration)
- Other endpoints: `POST /eats/orders/{order_id}/cancel`, `PUT /eats/stores/{store_id}/menus` (upsert menu), `POST /eats/stores/{store_id}/menus/items/{item_id}` (update item), `GET/POST /eats/store/{store_id}/status`. OAuth Bearer with the `eats.order` scope, and the caller must be the store's nominated order-manager app. Some APIs "may require written approval from Uber". — [Order integration guide](https://developer.uber.com/docs/eats/guides/order-integration); [accept_pos_order ref](https://developer.uber.com/docs/eats/references/api/v1/post-eats-order-orderid-acceptposorder)
- Integrator note: Doshii says that if the POS doesn't respond within 90 s, the order falls back to the Uber tablet, which then has 10 minutes. [VENDOR] — [Doshii support](https://support.doshii.com/orders-and-docket-printing/troubleshooting/why-did-order-go-to-uber-eats-tablet)

**DoorDash Marketplace [DOC]**
- New orders arrive as an `OrderCreate` webhook (status `NEW`) at a URL configured by DoorDash. Confirmation:
  - synchronous: `200`, with an HTTP timeout ">1 minute"; or
  - asynchronous: `202`, then `PATCH /api/v1/orders/{id}` (host `openapi.doordash.com/marketplace`) with `{merchant_supplied_id, order_status: success | fail, failure_reason}`.
  - "If you do not confirm the order in 3-8 minutes, DoorDash will treat the order as a failure." DoorDash prefers async if calls take more than ~20 s.
  - — [DoorDash: Receive orders](https://developer.doordash.com/docs/marketplace/how_to/order_integration)
- There is also an order-ready signal, merchant cancellation and menu push. The integration checklist requires menu creation and updates. Marketplace APIs are "not yet generally available"; the reference says "not accepting new partners at the moment". — [DoorDash order ready](https://developer.doordash.com/docs/marketplace/how_to/order_ready_signal); [Marketplace API spec](https://developer.doordash.com/de-DE/api/marketplace)

### Inferences (generic adapter design for Rizqim)
- **Two transport modes behind one interface.**
  - (a) *Inbound-pull server* for Yandex and probably Uzum. Rizqim hosts the OAuth2 token endpoint and the menu, availability, order and status resources. Per tenant and per aggregator credentials. A public domain.
  - (b) *Webhook plus callback client* for Wolt, Glovo, DH, Uber and DoorDash. Rizqim receives a signed webhook, ACKs it fast, queues it, then calls accept, ready and so on.
  - Both feed the same `AggregatorOrder` and state machine: `NEW → ACCEPTED → COOKING → READY → PICKED_UP → DELIVERED` / `CANCELLED` (from any status) / `REJECTED`.
- **Order intake rules:**
  - Persist the raw payload.
  - Dedupe on the aggregator order ID (e.g. `eatsId`).
  - Answer fast: Yandex allows ~210 s, while webhooks expect seconds.
  - Map items by **external ID = Rizqim item/modifier UUID**.
  - Return `406 unavailable_goods` for Yandex when an item is on stop.
  - Show an accept timer on the cashier and KDS. The deadlines differ by platform: Glovo and Wolt can auto-accept, DoorDash allows 3–8 min, Uber 11.5 min.
- **Menu and stop-lists:**
  - For Yandex, serve `composition` with a `lastChange` that moves on every edit, plus `availability` stocks, and call `POST /menu/import/initiation` (`menu_stop_list`) on every stop-list change to cut the lag.
  - For push platforms, send a PATCH per item.
  - Keep **per-aggregator price lists**. REGOS uses "Вид цены"; prices may be marked up on aggregators.
  - Skip zero-price items.
  - Require ИКПУ on every dish.
- **Store status:** map "Pause/Busy" in Rizqim to Yandex `/block` (`too_busy` / `technical_problem`), Glovo `/closing`, Wolt `/online`, and DH availability `unreachable`.
- **Money:**
  - Store `paymentType` (CARD = paid by the aggregator, CASH = collect on delivery or pickup).
  - Store the discount split between partner and platform.
  - Store the aggregator commission (from reports) for reconciliation against Didox acts (Uzum) and Yandex order history (`/v1/get-orders-details`, 180 days).
- **Fallback:** every platform assumes a vendor app or tablet fallback (DH requires it). Rizqim should keep the aggregator cabinet usable and show an integration health indicator: last poll and last webhook.

### Gaps
- Exact request and response JSON for Wolt, Uber and DoorDash payloads was not extracted. Those references are behind JS-rendered pages or partner access.
- The DH Plugin API paths come only from an unofficial listing. The official spec is gated.
- Rate limits: not stated for Glovo ("check rate limit" only), Wolt, Yandex, Uber or DoorDash in the pages read.

---

## 4. GitHub: open-source clients and examples

### Takeaway
GitHub has almost nothing useful for the Yandex Eats or Uzum Tezkor **vendor** APIs. Searches for "yandex eda integration" returned 0 results, and nothing turned up for "uzum tezkor". The Wolt and Uber Eats results are small or abandoned clients, or consumer-API scrapers. The best implementation references are the **official OpenAPI specs** themselves (Yandex `external-api.openapi.json`, Glovo `definition.yaml`), which can be used to generate server stubs and DTOs.

### Cited Findings
- `food-ticket/wolt-client`: "A Laravel client to integrate with the Wolt API". PHP, 0 stars, created Apr 2026 and updated Jun 2026. Also on Packagist as `foodticket/wolt`, which wraps the webhook and OAuth callback endpoints. Relevant to Rizqim's Laravel backend as a pattern for webhook signature checks and OAuth. — [GitHub](https://github.com/food-ticket/wolt-client); [Packagist](https://root.packagist.org/packages/foodticket/wolt)
- `OzTamir/wolt-api` (TypeScript client for the Wolt **consumer** API) and `Amperstrand/wolt` (read-only storefront scraper of consumer-api.wolt.com) are not merchant APIs. — [GitHub](https://github.com/OzTamir/wolt-api); [GitHub](https://github.com/Amperstrand/wolt)
- `dan-v/golang-ubereats` (archived, unofficial) and `jzarca01/node-ubereats` ("work in progress") are old consumer-side wrappers. The other results were Uber Eats clones. — [GitHub](https://github.com/dan-v/golang-ubereats); [GitHub](https://github.com/jzarca01/node-ubereats)
- The `glovo.client.csharp` NuGet package mirrors the Glovo partner docs. — [NuGet](https://www.nuget.org/packages/glovo.client.csharp)
- GitHub search for "yandex eda integration" returned 0 repositories (GitHub MCP search, 25.10.2026). The `gh` CLI search was blocked in this environment, so coverage is limited to the MCP search.

### Inferences
- There is no reusable open-source Yandex or Uzum vendor server. Rizqim should generate DTOs from Yandex's OpenAPI JSON (e.g. openapi-generator for PHP/Laravel) and treat Yandex's Postman collection as the acceptance test suite.

### Gaps
- Private or enterprise integrator code (iiko, r_keeper, REGOS, AliPos plugins) is not public. GitHub code search for exact path strings (e.g. `"menu/{restaurantId}/composition"`) was not possible with the tools available.
