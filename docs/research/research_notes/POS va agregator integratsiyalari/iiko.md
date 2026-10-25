# iiko (Syrve) integration for a third-party marketplace: RIZQIM orders into a venue's iiko

> Research date: 25.10.2026. Main primary source: the official iikoCloud API OpenAPI 3.0.4 spec, downloaded on 25.10.2026 from `https://api-ru.iiko.services/api-docs/docs`. It is the JSON behind the Redoc page [api-ru.iiko.services/docs](https://api-ru.iiko.services/docs): 349 endpoints, contract assembly version 9.9.1.3. Every claim marked "spec" below was read directly from that file. Abbreviations: **[SPEC]** = official OpenAPI file; **[OFFICIAL]** = iiko.ru / iiko partner page; **[VENDOR]** = dealer or third-party marketing; **[PRIOR NOTE]** = fact already sourced in an earlier Rizqim research note (URL given); **[INFERENCE]** = my reasoning, not stated by a source.

## 1. Which iiko API to use, and how authentication, organizations and terminal groups work

### Takeaway
An external marketplace should use the **iikoCloud API (also called iikoTransport API; Syrve Cloud API outside the CIS)** at `https://api-ru.iiko.services`. It is the only cloud API that pushes delivery orders to the venue's iikoFront, pulls the menu and stop lists, and sends status webhooks. iikoFront plugins (Front API SDK) are an on-premise .NET option for deeper POS hooks, not for order intake. **The auth scheme changed in 2026.** The old `apiLogin` → `/api/1/access_token` flow is deprecated. Integrators must now register on the iiko Developer Portal and call `/api/v2/access_token` with `apiKey + appId + clientSecret`. Per iiko, new API keys stopped working on the old method from 01.06.2026 and the old method shuts down fully on 29.08.2026.

### Cited Findings
**API choice**
- The OpenAPI spec is titled "iikoCloud API", and its schema namespaces are `iikoTransport.PublicApi.Contracts.*`. iikoCloud API and iikoTransport are the same product. Endpoint groups: Authorization, Organizations, Terminal groups, Menu, Dictionaries, Deliveries (create/update/retrieve), Drafts, Orders (table), Banquets/reserves, Webhooks, Operations (commands), Couriers, Delivery restrictions, Addresses, Loyalty, Licenses, Inventory, Nomenclature write-API, Employees. — [SPEC, api-ru.iiko.services/api-docs/docs](https://api-ru.iiko.services/api-docs/docs)
- The tag description says webhook handlers "can be registered by calling the `POST api/1/webhooks/update_settings` method or in the 'iikoTransport API' iikoWeb application." — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- iikoFront API SDK (plugins that run inside the POS, part of iikoRMS) is published by iiko on GitHub: `iiko/front.api.sdk`, 44 stars, updated 21.10.2026. Syrve mirrors it as `Syrve/front.api.sdk`. Docs are at `iiko/front.api.doc`. — [github.com/iiko/front.api.sdk](https://github.com/iiko/front.api.sdk); [github.com/Syrve/front.api.sdk](https://github.com/Syrve/front.api.sdk)
- Even Yandex's own iiko module (Yandex Delivery, express courier dispatch) needs a front plugin, `Plugin.Front.DeliveryExternalCourierService`, plus the iikoTransport service on the terminal. — [yandex.ru/dev/logistics … integration-iiko](https://yandex.ru/dev/logistics/api/integration/integration-iiko.html)

**Authentication (2026 change)**
- `POST /api/1/access_token` takes `{apiLogin}` ("API key. It is set in iikoWeb") and returns `{correlationId, token}`. Its description says "Deprecated: use `/api/v2/access_token` instead"; "The standard token lifetime is 1 hour." — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- `POST /api/v2/access_token` takes `{apiKey, appId, clientSecret}` (all required). Steps quoted from the spec:
  1. "Register at https://public-api.iikoweb.ru/portal and fill in your company details."
  2. "Create an application — you will receive an `appId` and a one-time `clientSecret`."
  3. "In iikoWeb → 'Integrations' → 'API Keys', generate an API key."
  4. "Call this method with all three credentials."
  5. "Use the returned token as a Bearer token in the Authorization header."

  The token is "valid for 1 hour (see the `exp` claim in the decoded JWT)"; "there is no refresh token flow". The `apiKey` "determines which restaurant organizations the token grants access to". The `clientSecret` "is shown only once"; regenerating it revokes the old one immediately. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- iiko news, 11.04.2026: "с 01.06.2026 не принимаются вновь созданные API-ключи" on the old method, and "с 29.08.2026 он будет полностью отключен". Integrations must be registered on the iiko developer portal. — [OFFICIAL, iiko.ru/news, 11.04.2026](https://iiko.ru/news/perehod-na-novuyu-shemu-avtorizaczii-v-api/)
- A dealer article (20.04.2026) gives the full shutdown date as "1 сентября", not 29.08. It also says registration needs the organisation's details plus e-mail confirmation, so tech support cannot do it for you. "Technology partners of iiko must create their own applications." — [VENDOR, resto-s.ru, 20.04.2026](https://resto-s.ru/articles/portal-razrabotchika-iiko); the date conflicts with [iiko.ru news](https://iiko.ru/news/perehod-na-novuyu-shemu-avtorizaczii-v-api/) (29.08.2026). The official date takes precedence.
- Older apiLogin creation flow, as one integrator documents it: in iikoWeb → iikoCloud settings, create an API login with a name, a notification e-mail, an optional "Order source" field and a validity of 2 years (default). Attach the departments, copy the key and save. — [VENDOR, docs.fudov.ru](https://docs.fudov.ru/en/integration/iiko/connect-fudov-smart/)
- Every data method also takes a `Timeout` header ("Timeout in seconds", default 15). — [SPEC](https://api-ru.iiko.services/api-docs/docs)

**Organizations and terminal groups**
- `POST /api/1/organizations` takes `{organizationIds?, returnAdditionalInfo, includeDisabled, returnExternalData?}`. By default it returns all organizations attached to the key. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- `POST /api/1/terminal_groups` takes `{organizationIds*, includeDisabled?}` and returns `terminalGroups` plus `terminalGroupsInSleep`. Sleeping groups "can be awakened by `/api/1/terminal_groups/awake`". `POST /api/1/terminal_groups/is_alive` with `{organizationIds, terminalGroupIds*}` returns `isAliveStatus[]`. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- `POST /api/1/organizations/settings` returns organization settings, including `addressFormatType` (Legacy or City), which decides the address shape on order create. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- `POST /api/licenses/v2/list` returns `organizationLicenses[] {organizationId, isChain, licenses[]}` for the API login. It can be used to check whether a venue has the right licenses. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Each method declares a "Restriction group". There are 36 in total, for example `Orders: creating`, `Order status: changing`, `Data: menu`, `Data: stoplists`, `Data: changing stoplists`, `WebHooks: settings`, `Commands`, `POS: availability`. — [SPEC](https://api-ru.iiko.services/api-docs/docs)

### Inferences
- Data model for Rizqim: one Rizqim venue maps to one iiko `organizationId` and, usually, one `terminalGroupId`. A chain can have many organizations under one apiKey. Store `organizationId`, `terminalGroupId`, `externalMenuId`, `priceCategoryId`, `paymentTypeId` (for "Rizqim prepaid"), `orderTypeId` (delivery and pickup) and the venue's `apiKey` per venue. Rizqim's `appId` and `clientSecret` are global secrets. [INFERENCE]
- The "Restriction group" labels suggest each API key carries per-group permissions that the venue grants in iikoWeb. Onboarding should verify access by calling each needed group once. `stop_lists/add|remove|clear` explicitly need "extra rights". [INFERENCE]
- Cache tokens for about 55 minutes per apiKey and re-auth on 401. [INFERENCE from the 1 h lifetime and no refresh flow]

### Gaps
- Whether the Developer Portal moderates or certifies applications, or charges for them: the iiko news and dealer article say nothing about fees or certification.
- The exact names of the permission checkboxes for an API key in iikoWeb. The ru.iiko.help article "polucheniyu-dostupa-k-api" is a JS-rendered SPA and returned no body to the fetcher.
- Whether `Syrve` (api-eu/api-eu.syrve.live) uses the same v2 auth. Not checked; irrelevant for Uzbekistan, which runs on the `api-ru` cluster [INFERENCE].

## 2. Menu, stop lists, order creation, statuses, cancellation, errors and limits (implementation details)

### Takeaway
- **Menu:** pull external menus with `/api/2/menu` plus `/api/menu/v3/by_id`. `/api/1/nomenclature` and `/api/2/menu/by_id` are now marked Deprecated.
- **Stop lists:** pull with `/api/1/stop_lists`, and also listen for the `StopListUpdate` webhook.
- **Order intake:** push orders with `/api/1/deliveries/create`. This is an asynchronous command: track it via `creationStatus`, `/api/1/commands/status` or webhooks.
- **Status sync:** receive `DeliveryOrderUpdate` and `DeliveryOrderError` webhooks, with `/api/1/deliveries/by_id` or `/by_revision` polling as the fallback.
- **Cancellation:** cancel with `/api/1/deliveries/cancel`.

### Cited Findings
**Menu (nomenclature, external menus, sizes, modifiers)**
- `POST /api/2/menu` returns `externalMenus[]` and `priceCategories[]` ("External menus with price categories"). — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- `POST /api/menu/v3/by_id` takes `{externalMenuId*, organizationId*, priceCategoryId?}` and returns a MenuV3 with `products[]`, `modifiers[]`, `taxCategories[]`, `productCategories[]`, `allergenGroups[]`, `schedules[]` and `itemsGroups[]`. Per the spec, "Menu v3 is denormalized: the same `productId` may appear in several `itemsGroups[].items[]` placements… To create an order, use `productId` (+ `sizePrices[].sizeId` if the product has a size scale)." — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Each item in an items group (`ItemsGroup.items[]`) has `productId`, `name`, `isHidden`, `description`, `sizePrices[] {sizeId, sizeName, isDefault, price, isHidden}`, `modifierGroups[] {id, name, restrictions, items[], splittable, childModifiersHaveMinMaxRestrictions}`, `menuItemId` and `sortOrder`. ModifierItem fields are overrides: when one is null, take the value from the reference `MenuV3.modifiers` entry with the same id. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- `POST /api/1/nomenclature` takes `{organizationId*, startRevision?}` and returns `groups[]`, `productCategories[]`, `products[]` ("Menu items and modifiers"), `sizes[]` and `revision`. Pass the last `revision` as `startRevision`; equal values mean the menu has not changed. The spec marks it "Deprecated, use `api/menu/v3/by_id`". `/api/2/menu/by_id` is also deprecated. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Combos: `POST /api/1/combo` and `/api/1/combo/calculate`. — [SPEC](https://api-ru.iiko.services/api-docs/docs)

**Stop lists**
- `POST /api/1/stop_lists` takes `{organizationIds*, terminalGroupsIds?, returnSize?}` and returns `terminalGroupStopLists[]`. Each `TerminalGroupStopList` is `{terminalGroupId, items[] {balance*, productId*, sizeId?, sku?, dateAdd}}`. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- `POST /api/1/stop_lists/check` checks given items. `/stop_lists/add`, `/remove` and `/clear` need "extra rights" (version 8.6.1 or later, restriction group `Data: changing stoplists`). — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- On create, `createOrderSettings.checkStopList: true` makes iiko check items against the stop list ("Unable if `terminalGroupId` is null"). — [SPEC](https://api-ru.iiko.services/api-docs/docs)

**Order creation: `POST /api/1/deliveries/create`**
- "This method is a command. Use `api/1/commands/status` method to get the progress status." Restriction group: `Orders: creating`. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Request body: `{organizationId*, terminalGroupId?, createOrderSettings? {transportToFrontTimeout, checkStopList}, order*}`. `transportToFrontTimeout` is "how much time is given for order to reach iikoFront. After this time, order is nullified if iikoFront doesn't take it. By default - 8 seconds." — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- `order` fields:

  | Field | Constraint / meaning (from the spec) |
  |---|---|
  | `id` | uuid, "Must be unique"; generated by iiko if null |
  | `externalNumber` | ≤50 chars, from version 8.0.6 |
  | `completeBefore` | `yyyy-MM-dd HH:mm:ss.fff`, local terminal time; null means urgent, shortest possible time; allowed range is now to +60 days |
  | `phone*` | 8–40 chars, must start with "+" |
  | `orderTypeId` / `orderServiceType` | exactly one of the two; `orderServiceType` is `DeliveryByCourier` or `DeliveryByClient` |
  | `deliveryPoint` | required unless pickup; see the address bullet below |
  | `comment` | free text |
  | `customer` | discriminator `type`: `regular` or `one-time` |
  | `guests` | guest details |
  | `marketingSourceId` | marketing source |
  | `operatorId` | operator |
  | `deliveryDuration`, `deliveryZone` | from version 8.8.6 |
  | `priceCategoryId` | from version 9.0.5 |
  | `items*` | order lines |
  | `combos` | combos |
  | `payments` | payment lines |
  | `tips` | tips |
  | `sourceKey` | "The string key (marker) of the source (partner - api user) that created the order. Needed to limit the visibility of orders for external integration." |
  | `discountsInfo`, `loyaltyInfo` | discounts and loyalty |
  | `chequeAdditionalInfo` | `{needReceipt*, email, phone, settlementPlace, retailAddress, isInternetPayment}`, described as "according to russian federal law #54" |
  | `externalData[]` | `{key, value, isPublic}`; set `isPublic` to TRUE to include the data in sales reports; from version 8.0.6 |

  `deliveryPoint` is `{coordinates?, address? (type legacy {street{id|classifierId|name+city}, house*, flat, entrance, floor, doorphone, regionId} or type city {line1* ≤250, flat, entrance, floor, doorphone, regionId}), externalCartographyId, comment ≤500}`. The `city` address type is allowed only when the organization's `addressFormatType == City`. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Customers: `regular` adds or updates the customer in the venue's CRM and loyalty base. `one-time` "should be used if a customer does not agree to take part in the store's loyalty programs or an aggregator (external system) does not provide customer details"; such details are used only for the current order. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Items: discriminator `type` = `Product` | `Compound`. A Product line has `{productId*, amount* (0–999.999), price*, productSizeId?, modifiers[] {productId*, amount*, productGroupId (required for group modifiers), price, positionId}, positionId, comment ≤255, comboInformation}`. The spec says `price` is "Price per item unit. Can be sent different from the price in the base menu." — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Payments: discriminator `paymentTypeKind` = `Cash` | `Card` | `LoyaltyCard` | `External`. Common fields: `{sum*, paymentTypeId*, isProcessedExternally, paymentAdditionalData, isFiscalizedExternally (version 7.6.3 or later), isPrepay (version 8.2.6 or later)}`. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Dictionaries:
  - `POST /api/1/payment_types` → `{id, code, name, combinable, isDeleted, printCheque ("If true, payment type is fiscal and bill will be printed"), paymentProcessingType, paymentTypeKind (Unknown|Cash|Card|Credit|Writeoff|Voucher|External|IikoCard), terminalGroups[]}`.
  - `POST /api/1/deliveries/order_types` → `{id, name, orderServiceType, isDeleted, isDefault}`.
  - Also `/api/1/cancel_causes`, `/api/1/discounts` and `/api/1/marketing_sources`.

  — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Response: `{correlationId, orderInfo {id, posId, externalNumber, organizationId, timestamp, creationStatus (Success|InProgress|Error|Cancelled), errorInfo?, order?}}`. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- `POST /api/1/commands/status {organizationId*, correlationId*}` returns `state` = InProgress | Success | Error (with `exception` and `errorReason`). "Response code `410` means that the correlationId value … is no longer supported." — [SPEC](https://api-ru.iiko.services/api-docs/docs)

**Order statuses (sync back to Rizqim)**
- `DeliveryStatus` enum: `Unconfirmed, WaitCooking, ReadyForCooking (deprecated from 9.0.6), CookingStarted, CookingCompleted, Waiting, OnWay, Delivered, Closed, Cancelled`. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- The response `Order` object includes `status`, `cancelInfo`, `courierInfo` and these timestamps: `whenCreated`, `whenConfirmed`, `whenPrinted`, `whenCookingCompleted`, `whenSended`, `whenDelivered`, `whenClosed`, `whenPacked`, `whenReceivedByApi` (UTC), `whenReceivedFromFront` (UTC). It also has `estimatedTime`, `sum`, `number`, `sourceKey`, `payments[]`, `processedPaymentsSum`, `terminalGroupId`, `trackingLink`, `externalData[]`, plus `movedTo*`/`movedFrom*` fields for orders moved to another terminal group. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Webhooks:
  - `POST /api/1/webhooks/update_settings {organizationId*, webHooksUri*, authToken?, webHooksFilter?}`.
  - Filters: `deliveryOrderFilter {orderStatuses[], itemStatuses[], errors, returnedExternalDataKeys[]}`, `tableOrderFilter`, `reserveFilter`, `stopListUpdateFilter {updates}`, `personalShiftFilter`, `nomenclatureUpdateFilter`, `businessHoursAndMappingUpdateFilter`.
  - `GET /api/1/webhooks/settings` returns the current `webHooksUri`, `authToken`, filter and `apiLoginName`. Settings are kept "for specified organization and authorized API login".
  - Event types (`WebHookEventType`): `DeliveryOrderUpdate, DeliveryOrderError, ReserveUpdate, ReserveError, TableOrderUpdate, TableOrderError, StopListUpdate, PersonalShift, KitchenOrderUpdate, NomenclatureUpdate, BusinessHoursAndMappingUpdate`.
  - Payload: `{eventType, eventTime (UTC), organizationId, correlationId, eventInfo}`. For delivery events, `eventInfo` is the same `OrderInfo` as in the API. For StopListUpdate it is `{terminalGroupsStopListsUpdates[]}`.

  — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Polling:
  - `POST /api/1/deliveries/by_id {organizationId*, orderIds[] (≤200) | posOrderIds[], sourceKeys?, returnExternalDataKeys?}`. "The guaranteed order availability period is the last 7 days."
  - `POST /api/1/deliveries/by_revision {startRevision*, organizationIds*, sourceKeys?}` → `maxRevision` plus `ordersByOrganizations`; "Maximum revision offset to request - 3 hours".

  — [SPEC](https://api-ru.iiko.services/api-docs/docs)

**Cancel and other mutations**
- `POST /api/1/deliveries/cancel {organizationId*, orderId*, movedOrderId?, cancelCauseId?, cancelComment?, removalTypeId?, removalComment?, userIdForWriteoff?}` is a command (version 7.5.4 or later, restriction group `Order status: changing`). — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Other mutations: `deliveries/update_order_delivery_status`, `confirm`, `cancel_confirmation`, `add_items`, `add_payments`, `change_payments`, `change_complete_before`, `change_delivery_point`, `change_comment`, `change_external_data`, `update_order_problem`, `update_tracking_link`, `change_driver_info`, `close`, `print_delivery_bill`. `deliveries/update_order_payments` is Deprecated. — [SPEC](https://api-ru.iiko.services/api-docs/docs)

**Errors and limits**
- HTTP errors: 400, 401, 408 and 500 return `ErrorResponse {correlationId, errorDescription, error}`. `ErrorCode` has 79 values relevant to order logic, including: `ProductNotFound, ProductSizeNotFound, ProductGroupNotFound, PaymentTypeNotFound, PaymentTypeCanNotBeUsedAsExternal, DuplicatedOrderId, TerminalGroupIdNotDetermined, TerminalGroupUnregistered, TerminalGroupDisabled, OrganizationUnregistered, OrganizationDisabled, InvalidPhone, ModifierDuplicated, OrderTypeNotFound, OrderServiceTypeDoesNotMatchSelfServiceMode, RequestProductPriceIsNotEqualToFrontPrice, TooSmallDeliveryDate, IikoFrontTooOldVersion, AnonymousCustomerDisabled, Line1Disabled, AddressNotFound, OrderStatusChangedInIikoFront, WrongDeliveryStatus, IllegalDeliveryStatus, InternalServerError`. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- 429 "Too many requests" appears 209 times in the spec, on the newer `api/nomenclature|inventory|employees|terminals` method families. The spec gives no numeric quota. The classic `/api/1/*` delivery methods list only 400/401/408/500. — [SPEC](https://api-ru.iiko.services/api-docs/docs); no numeric limits were found in public sources ([search result summary, renta.im docs](https://renta.im/docs/sources/iiko-cloud/)).

**Example payload** (assembled from the spec's field definitions; not copied from an official example):
```json
POST /api/1/deliveries/create
{ "organizationId": "<org>", "terminalGroupId": "<tg>",
  "createOrderSettings": { "transportToFrontTimeout": 30, "checkStopList": true },
  "order": {
    "id": "<uuid generated by Rizqim>", "externalNumber": "RZ-102345",
    "phone": "+998901234567", "orderServiceType": "DeliveryByCourier",
    "completeBefore": null, "comment": "Rizqim #102345",
    "customer": { "type": "one-time", "name": "Aziz" },
    "deliveryPoint": { "coordinates": {"latitude": 41.31, "longitude": 69.28},
      "address": { "type": "city", "line1": "Toshkent, Chilonzor 9-kv, 12-uy", "flat": "45", "entrance": "2", "floor": "3" },
      "comment": "domofon 45" },
    "items": [ { "type": "Product", "productId": "<productId>", "productSizeId": null,
      "amount": 2, "price": 32000,
      "modifiers": [ { "productId": "<modId>", "productGroupId": "<grpId>", "amount": 1, "price": 0 } ] } ],
    "payments": [ { "paymentTypeKind": "External", "paymentTypeId": "<'Rizqim online' payment type>",
      "sum": 64000, "isProcessedExternally": true, "isPrepay": true, "isFiscalizedExternally": false } ],
    "sourceKey": "rizqim",
    "externalData": [ { "key": "rizqim_order_id", "value": "102345", "isPublic": true } ] } }
```

### Inferences
- **Idempotency.** Generate `order.id` (uuid) on the Rizqim side and persist it before calling iiko. A retry then hits `DuplicatedOrderId` instead of creating a duplicate. [INFERENCE from the "Must be unique" text and the error code]
- **Synchronous accept/reject.** Treat `creationStatus=InProgress` as "sent". Wait for the `DeliveryOrderUpdate` or `DeliveryOrderError` webhook, or poll `commands/status` / `deliveries/by_id`. The default 8 s `transportToFrontTimeout` voids the order if the venue's iikoFront is offline. Rizqim should check `terminal_groups/is_alive` before accepting a basket and show "venue offline". [INFERENCE]
- **Price drift.** The spec lets the integrator send its own `price`, but `RequestProductPriceIsNotEqualToFrontPrice` exists. Rizqim should either sync prices from the external menu or price category, or confirm with each venue how this check is configured. [INFERENCE; the trigger condition is not documented in the spec]
- **Webhooks:**
  - Only one webhook URI is configured per organization and API login. This is another reason each aggregator needs its own API key.
  - The `authToken` is echoed to Rizqim's endpoint, and Rizqim should verify it.
  - Webhooks are not guaranteed delivery. Keep a `by_revision` poller (≤3 h window) as reconciliation.

  [INFERENCE; the header name used to send `authToken` is not stated in the spec]
- **Status mapping for Rizqim:**

  | iiko status | Rizqim status |
  |---|---|
  | Unconfirmed / WaitCooking | accepted-pending |
  | CookingStarted | cooking |
  | CookingCompleted / Waiting | ready |
  | OnWay | courier on way (only if the venue's own couriers deliver) |
  | Delivered / Closed | done |
  | Cancelled | cancelled (read `cancelInfo`) |

  If Rizqim provides the courier, Rizqim owns the post-"ready" states. Send `orderServiceType=DeliveryByClient` (pickup by Rizqim courier), or use an order type configured by the venue. [INFERENCE]
- **Stop-list webhook.** Treat `StopListUpdate` as a trigger to refetch `/api/1/stop_lists` for the affected terminal groups. Item `balance` > 0 can mean limited stock rather than fully stopped. [INFERENCE]

### Gaps
- Numeric rate limits per API key or per method: not published. The spec only lists 429 for the newer families.
- The exact HTTP header that carries the webhook `authToken`, and the retry policy for failed webhook deliveries: not in the spec.
- Whether a public test or demo organization exists for the v2 auth scheme. The old iikoBiz/iikoDelivery demo credentials (`demoDelivery`) in a 2019-era repo are for the legacy iikoBiz API, not iikoCloud — [github.com/fisher85/iiko-api](https://github.com/fisher85/iiko-api). No sandbox for iikoCloud was found. In practice a test organization comes from a dealer or partner [INFERENCE].

## 3. What the venue must do, what the integrator must do, costs, and how Uzbek dealers and aggregators (Uzum Tezkor, Yandex) do it

### Takeaway
- **The venue** generates an API key in iikoWeb (Integrations → API Keys), attaches its organizations and grants permissions. It also needs an iiko tariff that includes API and delivery: in Uzbekistan the "Pro" tier explicitly lists API, and per Yandex's docs, Life-time customers need the iikoDelivery module plus an "iikoAPI Cloud" license.
- **The integrator (Rizqim)** registers once on the iiko Developer Portal to get `appId` and `clientSecret`. No public fee or certification process was found.
- **Uzbek dealers** (ITDO, A-ONE, Soft24, Enter-co) sell iiko per terminal at about 54/81/108 USD per month. Enter-co prices in so'm: 484,000 / 991,500 / 1,332,000.
- **Uzum Tezkor** has had an iiko partnership since Aug 2023 (orders go straight to the kitchen, stop lists are pulled automatically). Its partner page lists iiko, R-Keeper and JOWi integrations.

### Cited Findings
- iiko Developer Portal registration: "Register at https://public-api.iikoweb.ru/portal and fill in your company details … Create an application … In iikoWeb → 'Integrations' → 'API Keys', generate an API key." — [SPEC, /api/v2/access_token](https://api-ru.iiko.services/api-docs/docs)
- iiko asks clients and partners to "проверьте все ваши интеграции" (check all integrations) and register them on the developer portal. The announcement mentions no fees or certification. — [OFFICIAL, iiko.ru news 11.04.2026](https://iiko.ru/news/perehod-na-novuyu-shemu-avtorizaczii-v-api/)
- Yandex Delivery's iiko module requires: "iikoCloud or Life-time tariff plus the iikoDelivery module. For Life-time, an iikoAPI Cloud license is also required", and iiko version 7.7.7 or later. — [yandex.ru/dev/logistics … integration-iiko](https://yandex.ru/dev/logistics/api/integration/integration-iiko.html)
- A legacy-era note says that to work with your own delivery venue "потребуется лицензия API iikoDelivery", and if a call-center is used, the license is added to the call-center. — [github.com/fisher85/iiko-api README](https://github.com/fisher85/iiko-api) (old, iikoBiz era)
- ITDO (Tashkent, itdo.uz) prices iiko at Start "54$/месяц" (table service, delivery orders at the till, POS/tablets, reservations), Pro "81$/месяц" (adds finance, stock, staff, loyalty and "Подключение сторонних приложений (API)") and Enterprise "108$/месяц" (adds call-center, Wallet, RFM, franchise). The page also shows "от 1295 ₽/месяц". — [VENDOR, itdo.uz](https://itdo.uz/) (read 25.10.2026)
- Enter-co prices iiko at 484,000 / 991,500 / 1,332,000 UZS per month per terminal. The cafe tier includes "Доставка на кассе и агрегаторы"; Pro includes API. "Price is for one order-taking terminal, without hardware." A-ONE CORP and Soft24 list 54/81/108 USD. — [PRIOR NOTE, enter-co.uz read 24.10.2026](https://enter-co.uz/); [rest.a-one.uz](https://rest.a-one.uz/)
- Uzum Tezkor × iiko (Spot.uz, 07.08.2023, advertorial): orders go straight to the kitchen, dish stop lists are pulled automatically from the restaurant, and the app shows a current menu. The partners claim a 5–7 minute cut in delivery time. About 150 Tashkent venues (every third Tezkor partner) used iiko; there were about 1,000 iiko restaurants in Uzbekistan. The article names no technical mechanism. — [spot.uz, 07.08.2023](https://www.spot.uz/ru/2023/08/07/uzum/)
- The Uzum Tezkor partner page lists integrations with iiko, R-Keeper and JOWi. — [PRIOR NOTE, promo.tezkor.com/partners/uz](https://promo.tezkor.com/partners/uz)
- Veda Vector, a self-described "iiko business partner in Uzbekistan" (~100 venues), lists aggregator integrations with "Yandex Eats · Uzum tezkor · Wolt". — [VENDOR, vedavector.uz](https://www.vedavector.uz/en). Wolt was leaving Uzbekistan in Feb 2026 — [spot.uz, 25.02.2026](https://www.spot.uz/ru/2026/02/25/uzum-tezkor/)
- Uzum Tezkor was recognised as a dominant digital platform in food ordering and delivery (Apr 2025); the same list includes Yandex Eats. — [gazeta.uz, 02.04.2025](https://www.gazeta.uz/ru/2025/04/02/uzum-tezkor/)
- Abroad, the standard iiko–Yandex Eda integration includes automatic stop-list sync. — [PRIOR NOTE, agregatory.pro](https://agregatory.pro/blog/yandex-eda-integratsiya-iiko)

### Inferences
- The likely pattern for Uzum Tezkor and Yandex Eats in Uzbekistan is the same as Rizqim's plan. The aggregator is an iikoCloud API client: the venue creates an API key for the aggregator, and the aggregator pushes `deliveries/create` with its own `sourceKey`, an "External" payment type and `orderServiceType` set to courier or pickup. This matches the features advertised (orders to kitchen, auto stop lists, current menu), which map one-to-one to `deliveries/create`, `stop_lists` and the menu endpoints. No source confirms the mechanism for Uzbekistan. [INFERENCE]
- Onboarding checklist for a Rizqim venue on iiko. [INFERENCE, assembled from the spec]
  1. The dealer confirms the tariff includes API (Pro or higher, or the iikoDelivery/API license on Life-time).
  2. The venue creates an API key named "Rizqim", attaches its organizations and grants the needed restriction groups.
  3. In iikoOffice/iikoWeb the venue creates a payment type "Rizqim online" (kind External, non-fiscal or fiscal; see Q4) and optionally an order type "Rizqim delivery".
  4. The venue creates an external menu and price category for Rizqim if Rizqim prices differ.
  5. Rizqim maps each venue's organization, terminal group, menu and payment type in its admin panel.
  6. Rizqim registers its webhook URI.
  7. Rizqim sends a test order and the venue cancels it.
- Dealers are the gatekeepers in Uzbekistan: they sell the license and do the on-site setup. A referral or partnership with 1–2 dealers (ITDO, A-ONE, Enter-co, Soft24) would speed up connector rollouts. [INFERENCE]

### Gaps
- Any official iiko partner or certification program for integrators, its fees, or a listing in an iiko marketplace: not found.
- The exact price in Uzbekistan of an "iikoAPI Cloud" or "iikoDelivery" add-on for Life-time customers: not found. Dealer pages show only bundled tariffs.
- Soft24 and A-ONE pages were not re-read in this pass; their prices come from the prior note.
- Who pays for the integration (aggregator vs venue) in Uzum's or Yandex's model in Uzbekistan: not found.
- No Uzbek-language technical write-up (habr/vc) on aggregator–iiko integration was found.

## 4. Fiscalization: who prints the fiscal receipt for an aggregator order in iiko (Uzbekistan), and how prepaid online payments are marked

### Takeaway
The API gives the integrator three payment-level flags: `isProcessedExternally`, `isPrepay` and `isFiscalizedExternally`. The payment type's own `printCheque` flag decides whether iikoFront prints a fiscal receipt. In Uzbekistan iiko fiscalizes through a virtual-cash or OFD integration (Rahmat/Multikassa, E-POS), so by default **iikoFront (via the venue's fiscal module) prints the receipt when the order is closed** unless the payment is marked as externally fiscalized. No Uzbek-specific official document on aggregator-order fiscalization was found.

### Cited Findings
- Payment fields on create:
  - `isProcessedExternally` ("Whether payment item is processed by external payment system (made from outside)")
  - `isFiscalizedExternally` ("Whether the payment item is externally fiscalized", version 7.6.3 or later)
  - `isPrepay` ("Whether the payment item is prepay", version 8.2.6 or later)

  — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Payment-type dictionary: `printCheque`: "If true, payment type is fiscal and bill will be printed". Error `PaymentTypeCanNotBeUsedAsExternal` exists, so not every payment type can be used as External. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- `chequeAdditionalInfo {needReceipt*, email, phone, settlementPlace, retailAddress, isInternetPayment}` is described as "according to russian federal law #54", which is Russia-specific. — [SPEC](https://api-ru.iiko.services/api-docs/docs)
- Rahmat advertises a ready integration of its virtual cash register (Rahmat POS) with iiko, MoySklad, Artix and others. Multikassa.uz now redirects to rhmt.uz/pos. — [VENDOR, rhmt.uz/pos/integration](https://rhmt.uz/pos/integration/); [PRIOR NOTE, multikassa.uz redirect checked 24.10.2026](https://multikassa.uz/)
- Uzbek dealer itdo.uz is cited in search results as integrating iiko with "1C, Epos, Rahmat va yetkazib berish agregatorlari". The fetched page did not show this text, so this is unverified. — [VENDOR, itdo.uz](https://itdo.uz/)
- The Tax Committee position (2023–2024): R-Keeper, JOWi, iiko and similar systems must integrate as virtual cash registers. — [PRIOR NOTE, buxgalter.uz 26.12.2023](https://buxgalter.uz/oz/publish/doc/text195345_avtomatlashtirish_dasturlarini)
- Poster in Uzbekistan supports three fiscalization options: E-POS, ARCA and Fiscal Me / Fiscal Box. This is evidence of the Uzbek fiscal-provider landscape, not of iiko. — [joinposter.com/fiscalization-in-uzbekistan](https://joinposter.com/fiscalization-in-uzbekistan)

### Inferences
- **Recommended Rizqim setup for online-prepaid orders** (Payme, Click or Uzum paid inside Rizqim): send a payment with `paymentTypeKind: External`, `isProcessedExternally: true` and `isPrepay: true`, using a venue-created "Rizqim online" payment type. [INFERENCE]
  - **If Rizqim does NOT fiscalize:** set `isFiscalizedExternally: false` and configure the payment type with `printCheque=true`. The venue's iikoFront plus its Rahmat or E-POS fiscal module issues the fiscal receipt with the venue's TIN. Under Uzbek rules the seller (the venue) must issue the receipt, so this is likely the default for a pure marketplace.
  - **If Rizqim fiscalizes itself:** for example, Rizqim as a commission agent issuing an OFD receipt through its own Rahmat or E-POS virtual cash. Set `isFiscalizedExternally: true` and a non-fiscal payment type so iiko does not print a second receipt.
- **Cash-on-delivery orders:** send no payment line, or a Cash line without `isProcessedExternally`. The cashier closes the order and fiscalizes it in iikoFront as usual. [INFERENCE]
- Payme, Click and Uzum Bank also have their own fiscal-data requirements (MXIK/IKPU codes and package codes per line), which iiko holds in `paymentSubjectCode` and other product fields. Rizqim's menu sync should pull these if Rizqim needs to fiscalize. [INFERENCE; the v3 menu Product has `paymentSubjectCode`, `outerEanCode` and `isMarked` per the spec]

### Gaps
- No official iiko or Uzbek document says who must issue the fiscal receipt for an aggregator-prepaid order in Uzbekistan, or how Uzum Tezkor and Yandex Eats handle it with iiko venues. The prior research flagged the same gap.
- Whether iiko's Uzbek fiscal plugins (Rahmat, E-POS) honour `isFiscalizedExternally` and `isPrepay` the same way as the Russian 54-FZ flow: not documented.

## 5. Open-source clients and examples on GitHub

### Takeaway
No official iiko SDK exists for the cloud API. Community clients are small: the most-starred is Python `pyiikocloudapi` with 26 stars. They are still useful as typed references for payloads and webhook models. iiko's official GitHub covers only the on-premise iikoFront plugin SDK. An Uzbek developer maintains a Python package, `iiko-pkg`.

### Cited Findings
All star counts and dates are as returned by the GitHub API on 25.10.2026. Each repo is cited at its GitHub URL.

| Repo | Language | Stars | Last update | What it shows |
|---|---|---|---|---|
| [iiko/front.api.sdk](https://github.com/iiko/front.api.sdk) | — | 44 (29 forks, 86 open issues) | 21.10.2026 | Official iikoFront plugin SDK (on-prem POS plugins, not the cloud API) |
| [iiko/front.api.doc](https://github.com/iiko/front.api.doc) | — | 13 | 13.10.2026 | Official iikoFront plugin docs |
| [Syrve/front.api.sdk](https://github.com/Syrve/front.api.sdk) | — | 3 | 21.10.2026 | Syrve mirror of the plugin SDK |
| [fisher85/iiko-api](https://github.com/fisher85/iiko-api) | PHP/Python | 31 | 31.07.2026 | Old iikoBiz/iikoDelivery examples by Semyon Fudov (fudov.ru; Habr "Как мы делаем доставку еды на Айко"); demo credentials for the legacy API; links to examples.iiko.ru |
| [kebrick/pyiikocloudapi](https://github.com/kebrick/pyiikocloudapi) | Python | 26 (12 forks) | 01.08.2026 | Method names mirror endpoint paths; pydantic models; includes a `WebHookDeliveryOrderEventInfoModel` webhook-validation example with `eventType: "DeliveryOrderUpdate"`. Sister Go library: [kebrick/goiikoapi](https://github.com/kebrick/goiikoapi) |
| [gadzhi/pyiiko](https://github.com/gadzhi/pyiiko) | Python | 21 | 18.09.2026 | Mainly the iikoServer/iikoBiz back-office API |
| [andriymiz/iiko-biz-api](https://github.com/andriymiz/iiko-biz-api) | PHP | 14 | — | Archived; old iikoBiz API |
| [Muhammadali-Akbarov/iiko-pkg](https://github.com/Muhammadali-Akbarov/iiko-pkg) | Python | 8 | 12.02.2026 | Uzbek author; "Python library for the iiko.services API": auth with auto token refresh, organizations, menu, order and delivery creation, terminal groups, customers, payment/order types, discounts, addresses, couriers; pydantic and tests. Uses a single `IIKO_API_KEY`, so it likely predates v2 auth [INFERENCE] |
| [biz87/iiko](https://github.com/biz87/iiko) | PHP | 7 | 06.12.2025 | — |
| [stanislavqq/iikoapi](https://github.com/stanislavqq/iikoapi) | PHP | 7 | — | iikoBiz |
| [iiko-go/iiko-go](https://github.com/iiko-go/iiko-go) | Go | 1 (6 forks) | 30.05.2023 | Client for api-ru.iiko.services |
| [Nap20192/iiko-go](https://github.com/Nap20192/iiko-go) | Go | 0 | 26.09.2026 | "Go clients for iiko: iikoServer back office (168 endpoints) and iikoCloud (305 methods). Stdlib-only." |
| [themgmd/iikocloud](https://github.com/themgmd/iikocloud) | Go | 0 | 29.12.2025 | Go SDK |
| [Tigranchick/iiko_client_rs](https://github.com/Tigranchick/iiko_client_rs) | Rust | 0 | 21.08.2026 | "Autogenerated and patched api client for iikoCloud API" (generated from the OpenAPI spec) |
| [UserVanya/Iikocloud-py-sdk](https://github.com/UserVanya/Iikocloud-py-sdk) | Python (async) | 0 | 13.10.2026 | Async SDK; same author has an MCP server, [UserVanya/Iikocloud-mcp](https://github.com/UserVanya/Iikocloud-mcp) |
| [yurisenik/iikocloudapi](https://github.com/yurisenik/iikocloudapi) | Python (async) | 0 | 07.10.2026 | Async client |
| [vectorserver/api-ru.iiko](https://github.com/vectorserver/api-ru.iiko) | PHP | 1 | 15.10.2026 | iikoCloud PHP client |
| [WoLand-Q/Syrve-Delivery-API](https://github.com/WoLand-Q/Syrve-Delivery-API) and [API-Commands-Status-Checker](https://github.com/WoLand-Q/API-Commands-Status-Checker) | PHP | 0 | 2025 | Demos of auth → organizations → couriers → create delivery, and of polling `commands/status` |
| [Gosha5426/fastapi-integration-gateway](https://github.com/Gosha5426/fastapi-integration-gateway) | Python | 0 | 18.06.2026 | "Async gateway on FastAPI for webhook integration (WooCommerce → iikoCloud API) with token caching and retry logic". Architecturally closest to a Rizqim connector |
| [common-repository/wc-iikocloud](https://github.com/common-repository/wc-iikocloud) | PHP | 0 | — | Mirror of the WordPress.org plugin "iikoCloud integration for WooCommerce", a production e-commerce → iikoCloud connector |

- A community TypeScript library ([context7: salesduck/iiko-cloud-api](https://context7.com/salesduck/iiko-cloud-api)) claims types auto-generated from the official OpenAPI spec, with base URL https://api-ru.iiko.services.
- [Tux0096/menu PR #20](https://github.com/Tux0096/menu/pull/20) adds an `IIKO_APP_ID` to the token request. This is an example of projects migrating to v2 auth in 2026.

### Inferences
- The best "client" for Rizqim is to generate one from the official OpenAPI JSON at `https://api-ru.iiko.services/api-docs/docs`, as the Rust client did. Rizqim's backend is Laravel/PHP, and no maintained PHP iikoCloud SDK exists. A thin hand-written Guzzle client for about 15 endpoints is realistic (auth v2, organizations, terminal_groups, is_alive, 2/menu, menu/v3/by_id, stop_lists, payment_types, order_types, cancel_causes, deliveries/create, deliveries/by_id, by_revision, deliveries/cancel, commands/status, webhooks/update_settings). [INFERENCE]
- Most community clients still use `apiLogin` → `/api/1/access_token` and will break after 29.08.2026 unless updated. Do not copy their auth code. [INFERENCE]

### Gaps
- READMEs of most repos were not read in depth: GitHub API access to third-party repos was restricted in this session, so only the raw READMEs of pyiikocloudapi, iiko-pkg and fisher85/iiko-api were read. Payload samples inside the other repos were not verified.
- No relevant habr.com or vc.ru article from 2024–2026 on building an aggregator → iikoCloud connector was found in this pass. The only Habr link found is an older Fudov post referenced from fisher85/iiko-api.
