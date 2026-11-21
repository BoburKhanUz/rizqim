# Own virtual kassa: technical path found in open sources (21 Nov 2026)

## 1. FiscalDriveService (local service for the physical fiscal module)

Source: [github.com/qo0p/fiscal-drive-service](https://github.com/qo0p/fiscal-drive-service), README read 2026-11-21; repo last updated 2026-10-16 per GitHub search. Publisher not named in README (owner account "qo0p"); examples use ofd.soliq.uz QR URLs, so it targets Uzbekistan. [PRIMARY as a developer document; authority of the publisher unverified]

- Local REST service on 127.0.0.1:3449 with Swagger; replaces older FiscalDriveAPI which does not support FM version 0400.
- Platforms: Windows 11 x64 (smart-card service), Linux x64 and arm (pcscd, libccid). Android: separate project.
- Fiscal module: physical, issued by the OFD as USB key or smart card. Developer **FM 0400 emulator** over TCP (port 4387), **test OFD server** (13447, state API 13448), test FM serial ZZ000000000000; test receipts accepted only by the test server.
- Flow: `GetTXID` (store JSON receipt) -> `RegisterTXID` (register in FM) -> store fiscal sign + QR URL; retry `RegisterTXID` with same TXID on failure (idempotent); periodic sync of receipts and Z-reports to OFD.
- Receipt fields: Type 0 = normal, **1 = advance**, **2 = credit**; Operation 0 = sale, 1 = refund (refund references the original receipt); PaymentType 1 cash, 2 card, 3 QR, **4 mixed**; items with labels (marking) and VAT; extra info (phone, QR payment).
- Offline: receipts encrypted/signed by FM and queued locally; **FM refuses new operations when the oldest unacknowledged receipt is 2+ days behind OFD time** (this is the 48 h rule in practice). Clock must be synced.
- Security: FM can be locked to one POS with a 32-byte secret; each call needs `X-POS-Auth`.

## 2. Android: acrsim-android

Source: [github.com/qo0p/acrsim-android](https://github.com/qo0p/acrsim-android), README read 2026-11-21; updated 2026-10-28. [PRIMARY developer doc, publisher unverified]

- Android cash-register simulator and Russian guide for FM 0400: FM is a JavaCard smart card / USB token with a fiscal applet; the till talks to it with APDU commands through a **SAM slot**; the developer must write the slot driver for the hardware; PC emulator over Wi-Fi for development.
- FM 0400 news: no Z-report count limit, up to 29 999 operations per Z-report, new Advance and Credit types, idempotent operations, optional binding to one POS; no backward compatibility.
- Feitian (China) is the main FM supplier to the Tax Committee's SIC "Yangi texnologiyalar" (USB token + SIM-size card, Java Card) [PRIMARY ftsafe.com reference page].

## 3. Proof that a third-party POS does this: Artix (Russian retail POS)

Source: [docs.artix.su "ПО виртуальной кассы Artix (Узбекистан)"](https://docs.artix.su/pages/viewpage.action?pageId=93946891), read 2026-11-21 [PRIMARY].
- Artix runs its own Uzbek virtual kassa build: Feitian USB token found with lsusb, FiscalDriveService installed as a Linux package (from Artix 5.317 / 4.6.317), FM version 04xx, OFD address configurable, HTTPS with certificate.
- Advance (type 3) and credit (type 4) receipts printed without fiscal data; PaymentType mandatory (cash, card, QR-only, mixed); since 4.6.204 refund needs original fiscal data (FM serial, fiscal sign, receipt number, date or QR scan).
- Artix configures blocking after 24 h offline (stricter than the 48 h law).
- QR/mixed payments via Payme, Click, AnorGo, Uzum; fiscal data transfer for PaymeGO and ClickPass.

## 4. What this means for RIZQIM architecture [inference]

- Windows till: FiscalDriveService + USB FM on the till, RIZQIM app calls localhost.
- Android till: either a SAM-slot device (Sunmi/Nexgo-type with SAM) and own applet driver, or a small Linux/arm box (or the Windows till) in the restaurant running FiscalDriveService with the USB FM, Android tills call it over LAN. The box option keeps cheap Android tablets.
- Cloud backend cannot hold the FM unless the OFD allows FM hosting off-site [NOT FOUND, ask OFD].
- RIZQIM still needs a registered TXKM to activate FMs and register each restaurant's kassa (see legal notes), unless RIZQIM becomes a TXKM.

## 5. Other open technical material
- `@exode-team/ofd-uz` npm (test.ofd.uz) - unverified [MEDIA, prior round].
- SmartOne Cashbox Fiscal API (local HTTP 127.0.0.1:8008) - device route, Uzbekistan not stated [PRIMARY docs.smartoneclub.com].
- FiscalBox API (order create with prepaid/credit) - device route [PRIMARY dev.fbox.uz].
