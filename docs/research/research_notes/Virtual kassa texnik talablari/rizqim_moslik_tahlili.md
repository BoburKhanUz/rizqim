# RIZQIM fiscal layer against FM 0400 / FiscalDriveService (code reading, 22.11.2026)

Branch `p-vkassa` at origin/main 737ada3c. Files read: `backend/app/Domain/Fiscal/**` (FiscalGateway, SmartKassaGateway, SandboxFiscalGateway, FiscalDocument, FiscalLine, FiscalPayment, FiscalReceiptType, FiscalPaymentForm, FiscalVat, FiscalProviderResponse, TaxRegime, MenuFiscalLineBuilder), `backend/config/fiscal.php`, migration `2026_10_25_100200_create_fiscal_receipts_table.php`, `backend/app/Support/{Money,Quantity}.php`, `docs/API.md` "Internetsiz rejim (P3-31)", `pos/packages/README.md`. Everything below marked [inference] is my reading, not a source.

## 1. What already fits

| RIZQIM | FM 0400 JSON (FiscalDriveService) | Fit |
|---|---|---|
| `FiscalReceiptType` sale / refund / advance / credit | `Type` 0/1/2 × `Operation` 0/1 | sale → Type 0 Op 0; refund → Type 0 Op 1; advance → Type 1 Op 0; credit → Type 2 Op 0. A refund of an advance (Type 1 Op 1) has no RIZQIM type yet |
| `FiscalPaymentForm` cash / card / qr / mixed | `PaymentType` 1 / 2 / 3 / 4 | one to one |
| `Money` scale 2 | uint64 tiyin | ×100, exact |
| `Quantity` scale 3 | `Amount` ×1000 | exact |
| `FiscalLine.unitPrice` (before discounts), `amount` (after) | `Price` = line total before discount, `Discount` | Price = unitPrice × quantity, Discount = Price − amount |
| `FiscalLine.vatRate` "12", "6", "0" | `VATPercent` uint8 | fits; `FiscalVat` also allows "7.5" which the FM cannot take |
| `FiscalLine.vatAmount` (line by line, half-up) | `VAT` per item | fits; FM sums `TOTAL_VAT` |
| `mxikCode`, `packageCode` | `SPIC` (≤ 18 digits), `PackageCode` (≤ 20 digits) | fits |
| `commissionerTin` | `CommissionInfo.TIN` | fits |
| `markingCode` | `Labels[]` (≤ 300) | fits, but RIZQIM has one code per line |
| `FiscalPayment.transactionId`, `cardType` | `ExtraInfo.PPTID`, `CardType` (1/2/3 corporate/personal/social) | RIZQIM card types are bank brands, not this list [inference] |
| `FiscalProviderResponse` fiscalSign, receiptNumber, qrUrl, terminalId, issuedAt | `RegisterTXID` → FiscalSign, ReceiptSeq, QRCodeURL, TerminalID, DateTime | one to one |
| `fiscal_receipts.provider_payload` | "JSON-ответ должен быть сохранен в БД ПО Кассы ... для возврата" | store the whole answer |
| `FiscalGateway::status()` before retry | `RegisterTXID` is idempotent, `Receipt/Info` | fits the idempotent model |
| Sandbox QR `?t=&r=&c=&s=` | `https://ofd.soliq.uz/check?t=&r=&c=&s=` | same shape already |
| advance and credit receipts without fiscal sign and QR | FM gives a number, no FiscalSign | matches VM 943 7-band |

## 2. Gaps found

1. **Where the receipt is registered.** Today: "Fiskal chek ... qurilmada emas, backend'da" (`pos/packages/README.md`); FiscalService calls the driver from the server. FM 0400: the FM and FiscalDriveService sit on the kassa computer and register the receipt locally, offline too; VM 943 2-ilova 6-band: data to the operator "directly through the fiscal module", one FM per virtual kassa. So registration has to move to the restaurant: the main kassa (`pos_lan` hub, Windows or a Linux box) calls FiscalDriveService on localhost; the backend keeps the record [inference]. A server-side driver can only work if the backend can reach the hub (online) — not when the internet is down.
2. **Offline sales receipted later (P3-31 `soldAt`).** FM refuses any time not later than its last operation (`DATETIME_IS_IN_THE_PAST`, ≥ 1 s later). Receipts must be registered in time order, at the moment of sale, on the hub. "Queue the sale, receipt it at sync with its original time" breaks as soon as a newer receipt was registered [inference from SW 0x9030].
3. **48 h rule is in the FM.** Counted from the oldest unsent receipt (`FirstUnacknowledgedReceiptTime` vs `LastOperationTime` ≥ 2 days → `SEND_ALL_RECEIPTS_FIRST`). RIZQIM's P3-31 warnings must read this field from the FM, not only the server queue.
4. **Unit code.** `FiscalLine.unit` is a text label ("dona", "kg"); FM `Units` is a numeric code (`TAG_UNITS` "Код ед. изм."). The code list is in the tasnif units reference (`/integration-mxik/references/get/units/all`, unofficial client) [MEDIA]. Needs a unit code per menu unit and per charge.
5. **Name charset and length.** FM `NAME` is 1–63 bytes in its own charset; Uzbek ʻ/ʼ (U+02BB/U+02BC) are not in it. Need a transliteration step (ʻ, ʼ, ‘, ’ → ', unknown → ?) and truncation to 63 [PRIMARY spec, inference for the rule].
6. **Advance offset.** When a booking advance is offset in the final sale (`FiscalPayment.prepayment`), how the FM sale receipt shows it is not documented. The FM equation only bounds received money from above (received − Σ(Price − Discount − Other) ≤ 100 so'm), so received < total may be accepted; whether the offset goes into `Other`, `Discount` or simply less `ReceivedCash/Card` is [NOT FOUND]. Same for the unpaid (nasiya) part of a sale.
7. **QR money.** FM has cash and card accumulators only; `ReceivedCard` likely carries QR money with `PaymentType` 3 and `ExtraInfo.QRPaymentID/QRPaymentProvider` [inference]. Provider code list [NOT FOUND].
8. **Empty Z.** `CANNOT_CLOSE_EMPTY_ZREPORT`: a shift with no sale or refund cannot close its FM Z. Shift close must handle this (keep the Z open, or do not open the FM Z until the first receipt) [inference]. Also 29 999 operations per Z.
9. **Refund data.** Needs the original's `TerminalID`, `ReceiptSeq`, `DateTime` (YYYYMMDDHHMISS), `FiscalSign`. RIZQIM has them in the original receipt row; refunds of receipts from another kassa (Artix asks for manual entry or QR scan) need a UI [inference].
10. **POS lock key.** 32-byte secret per TXKM, `X-POS-Auth` on every Z/receipt call. Where RIZQIM keeps it (per hub, encrypted) and whether the TXKM or RIZQIM generates it [NOT FOUND].
11. **Cash ban (PF-246).** Receipt with a cash-banned MXIK must be cashless as a whole; list published by the Tax Committee, changes. Not in RIZQIM now (only the earlier note about beer and alcohol in the previous report) [inference].
12. **`OwnerType`** per item: reference not published [NOT FOUND]; Payme/Click docs also do not explain it.
13. **Location** (`Latitude`, `Longitude`) per receipt: RIZQIM stores branch coordinates? Not checked; easy to add from branch data [inference].
14. **One FM per virtual kassa.** If one restaurant has several cash desks, each kassa needs its own FM, or one hub kassa registers everything. Whether waiter tablets that take payment count as separate kassas is [NOT FOUND] — ask OFD.

## 3. Shape of the step-2 code (proposal) [inference]

- Backend stays the record keeper (`fiscal_receipts`, idempotency, reports). New driver `rizqim_vkassa` in `config('fiscal.drivers')`.
- Dart package (e.g. `pos/packages/pos_fiscal`): FiscalDriveService REST client, FM JSON mapper and validators, FM state machine (lock, Z, 2-day rule), sync loop, POS lock auth; a fake FiscalDriveService + FM + OFD in `testing.dart` with the published error codes.
- Hub flow: bill paid → hub builds the FM JSON from the backend's FiscalDocument snapshot (or builds it locally when offline) → GetTXID → RegisterTXID → prints QR → reports `{TerminalID, ReceiptSeq, DateTime, FiscalSign, QRCodeURL}` to the backend (online at once, offline through `pos_sync`).
- Backend side: an endpoint for the hub to report a registered receipt; FiscalService marks the receipt issued from that report (instead of calling a provider). Keep FiscalGateway for providers (sandbox, smart kassa, future bridge).
- A PHP mirror of the mapper/validators lets the backend refuse a document the FM would refuse before it reaches the hub.
