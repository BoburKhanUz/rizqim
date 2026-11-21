# Paylov API: what the official docs say (notes)

Official docs: **https://developer.paylov.uz** (VitePress site "Paylov API", EN/UZ/RU; server header last-modified 11.11.2026). Checked 21.11.2026. Everything below is [PRIMARY] from that site unless marked.

The mirror https://paylov-docs.pages.dev is an older build of the same docs (same title). It lacks the sub-merchant, split and QR pages (404). Treat it as outdated; use developer.paylov.uz.

## Closed sections

These pages exist in the menu but return "Access denied - You do not have permission to view this section": `/qr-payments`, `/didox`, `/subscribe/p2p-transfer`. `/merchant-configuration` is empty. So **QR payments are documented only for logged-in partners**. [PRIMARY]

## Environments

- Checkout page host: `https://my.paylov.uz/checkout/create/{base64(query)}`.
- API host: docs say `BASE_URL`; one example shows `https://gw2.paylov.uz`.
- **No sandbox / test URL is documented.** The home page only has a decorative "Sandbox" tag. [NOT FOUND]
- Onboarding: Paylov gives a one-time onboarding token -> merchant sets username/password -> gets consumer_key/consumer_secret (secret shown once) -> account works only after Paylov admins activate it. Token: OAuth2 password grant, access 1 h, refresh 30 days (rotated). IP whitelist exists (`IP address not in whitelist`, `ip_not_allowed`). Rate limit example: 100 req / 60 s on sub-merchant create. Body limit 64 KB.

## Products found in docs

| Product | Endpoint(s) | Notes |
|---|---|---|
| Checkout (hosted payment page) | link `my.paylov.uz/checkout/create/...` with merchant_id, amount, currency_id (860 UZS / 840 USD), return_url, amount_in_tiyin, account.order_id; server callbacks JSON-RPC `transaction.check` and `transaction.perform` | Callback auth: **Basic Auth** login/password set with Paylov. Status codes 0 OK, 3 maintenance, 5 invalid amount, 303 order not found, +1 custom. Link is only base64, **not signed**: the merchant must check the amount in `transaction.check`. Example "Query params for Visa/Master USD payments" -> Visa/MC accepted in USD via checkout. `convert_to` converts at CBU rate. |
| Card binding (tokenisation) | `POST /merchant/userCard/createUserCard/` + `confirmUserCardCreate/` (merchant handles PAN + OTP) **or** `POST /merchant/userCard/createBindLink/` -> webview `my.paylov.uz/card-bind/?token=...` (PAN never touches us; link ~20 min, one card) + bind webhook + `GET /merchant/userCard/bindSession/{token}/status/` | OTP SMS comes from Uzcard/Humo processing; merchant SMS name can be added by Paylov. Bind webhook: Basic Auth, **no retries**. Card vendors in examples: Uzcard, Humo. |
| Pay with saved card (recurring, merchant-initiated) | `POST /merchant/receipts/create/` then `POST /merchant/receipts/pay/` (cardId) | Amount in sum. |
| One-time pay without saving card | `POST /merchant/paymentWithoutRegistration/` + `POST /merchant/confirmPayment/` (OTP) | Merchant handles card number -> PCI exposure; avoid, use checkout instead. |
| Hold (pre-authorisation) | `payment/hold/create/` (time up to 40 320 min = 28 days), `hold/charge/` (amount), `hold/dismiss/`, `hold/status/` | Charge takes an amount -> may allow partial capture (not stated explicitly). |
| Status | `GET /merchant/getTransactions/?id=` | statuses created, transfer, pending, waiting, payed, failed, cancel, refunded; response contains `ofd[]` receipts, `is_split`, `via_link`. |
| Refund | `POST /merchant/payment/cancel/` {transactionId} | **Full only** - no amount field. Cancel webhook `transaction.cancel` (also when cancelled from Paylov business panel or by Paylov staff), Basic Auth, retried 3x every 60 s, may duplicate. **Partial refund: [NOT FOUND]**. |
| Sub-merchants | `POST /merchant/subMerchant/merchant-create/` (name, tin/pinfl, external_id, optional new organization with director PINFL, bank account, MFO, OKED, OFD VAT %, SPIC/IKPU, package code) | Needs permission; **sub-merchant activated by Paylov staff**. Duplicate check by name/tin/pinfl/external_id. |
| Split payment | `POST /merchant/splitPayment/` {transactionId, recipients[{recipientId, amount, items[]}], resendOFD}; `GET /merchant/splitPayment/status/` | Needs permission; recipientId issued by Paylov; sum must equal transaction; once per transaction; with items it sends receipts to OFD asynchronously. **When/how recipients get money: [NOT FOUND]**. |
| Account2Card (payout to card) | `POST /merchant/a2c/performTransaction/`, balance, status, deposit webhook | Payout from merchant deposit to a card; optional MyID/PINFL checks. Could pay couriers; not needed for restaurants. |
| Card monitoring | `GET /merchant/getCardHistory/` | Reads a customer's card history. We should not use it (privacy). |
| Service payments | Paynet-style utility payments | Not relevant. |
| Fiscalisation | see below | |

## Fiscalisation (the "cash by externalId + phone" claim)

`POST BASE_URL/merchant/fiscalization/register/` - official description: "sends a transaction to the fiscal system (OFD) to generate a fiscal receipt. It registers a cash payment in the OFD system."
- `transactionId` - required for card payment (a Paylov transaction).
- `externalId` + `phoneNumber` (+998...) - required for **cash payment**.
- items: title, price (tiyin), discount, count, code (IKPU), vat_percent, package_code, tin or pinfl (one of them; commitent). Example also shows receiptType, advanceContractId, voucher, labels (marking codes) without description.
- Returns receiptUrl, terminalId, receiptId, fiscalSign.
- Errors include `ofd_commitent_or_subcommission_not_active` (the item's TIN/PINFL has no active contract in the OFD catalogue) and `invalid_amount` (items total != transaction amount).
- Related: `set-receipt` (attach a receipt we made ourselves; requires "client-side fiscalisation" mode; qrCodeUrl must be on ofd.soliq.uz), `GET fiscalization/status/` (by transactionId or externalId), `POST fiscalization/refund/` (by receiptId; for card payments cancel the transaction instead), `activity-types` (checks a PINFL: YATT / self-employed / income > 100 mln).
- Example terminal IDs look like `EP000000000378`. My guess only: "EP" may mean the receipts go through an E-POS-type virtual cash register; E-POS was removed from the register (Soliq, ~30.10.2026). Unverified - ask Paylov which virtual cash register (TXKM) and terminal they use.

So: the cash method **is on the official docs** [PRIMARY]. What the docs do **not** say [NOT FOUND]: the legal basis for a payment organisation to register a cash sale it did not process; whose virtual cash register the receipt belongs to; whether a restaurant can use it as its main kassa; how it fits VM 943 as amended by VM 393 (from 22.10.2026, removal from register if an order-accounting system is integrated with a registered kassa to print QR receipts - see reports/Fiskal provayder - raqib bo'lmaganlar.md).

## Webhooks and signing

- All callbacks/webhooks use HTTP **Basic Auth** (login/password agreed with Paylov). **No HMAC/signature** documented. JSON-RPC 2.0. [PRIMARY]
- Retries: cancel webhook and deposit webhook 3x/60 s; card-bind webhook no retries; checkout check/perform retry policy [NOT FOUND].
- Mitigation for us: TLS only, strong random password, IP allowlist of Paylov senders (ask for IP list), always re-check status with `getTransactions`.

## Telegram Mini App

- No mention of Telegram. Checkout is a hosted web page with return_url; card bind is a webview link. Both can be opened from a Mini App (like Payme/Click). [NOT FOUND for an explicit statement]

## Mapping to RIZQIM code (for later)

- backend/app/Services/Admin/System/PaymentProviders.php: KNOWN_KEYS has payme, click, uzum, paynet; WIRED = payme, click. Paylov would be a new slug with keys like merchant_id, consumer_key, consumer_secret, username, password, callback_login, callback_password.
- Checkout check/perform is close to Click Prepare/Complete and Payme CheckPerform/Perform, so the webhook controller pattern fits.
- backend/app/Domain/Pos/QrPayments/Providers has Click, Payme, UzQr, Sandbox. A Paylov QR provider cannot be written until the closed QR docs are shared.
- backend/app/Services/Payment/Fiscal: OnlineFiscalReceiptBuilder could feed Paylov `fiscalization/register` items (IKPU, package code, VAT, commitent TIN).
