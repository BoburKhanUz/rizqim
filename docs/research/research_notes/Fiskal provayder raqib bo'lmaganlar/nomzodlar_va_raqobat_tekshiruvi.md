# Fiscal candidates: competitor check and fit for RIZQIM (as of 21 Nov 2026)

Research date: 2026-11-21. Labels: [PRIMARY] = provider's own page, official Tax Committee channel, lex.uz, or an official developer repo/page read directly; [MEDIA] = press, advertorial, accounting site, search-summary only; [NOT FOUND] = searched, not seen. soliq.uz, ofd.uz, uzkassa.uz and bct.uz returned HTTP 503 on every try today (21.11.2026); kapitalbank.uz returned 403. The registry itself was therefore NOT read; registry statements below come from vendor pages, the Tax Committee Telegram channel, and older snapshots.

"Competitor" test used: does the company sell its own POS / cashier / sales-management software to shops or restaurants that RIZQIM targets? "Yes" = sells restaurant or general cashier software; "Partial" = sells cashier/retail software but not a restaurant POS; "No" = only fiscal infrastructure / hardware / payments.

## 1. Summary table

| Candidate | What it sells | Competitor? | Register status (evidence) | API / SDK / test | Receipt types seen | Cloud vs device | Price | Contact |
|---|---|---|---|---|---|---|---|---|
| Rahmat / Multikassa (AO "Multicard Payment") | Rahmat POS: online-KKM on SUNMI P3, virtual kassa (Windows), restaurant products (online menu, booking, QR bill) | **Yes** | Vendor says virtual kassa in register; multikassa.uz now 301-redirects to rhmt.uz/pos [PRIMARY, 21.11.2026] | "Open API" mentioned, no docs [PRIMARY] | sale, refund [MEDIA via Poster] | Windows app + device | virtual kassa 123 600 so'm/month; online-KKM 100 000/month; terminal 990 000 one-time [PRIMARY rhmt.uz/pos] | rhmt.uz, 1865 |
| Smartpos / Smart Business / Smart Cafe (CDTI; advertorial says "formerly UZKASSA") | Smart Business (trade), **Smart Cafe for cafes and restaurants**, online-KKM on PAX A930, virtual kassa | **Yes** | Not read; TXKM with 90+ offices [MEDIA, spot.uz 10.09.2026] | No public API [NOT FOUND] | not stated | device + "virtual kassa without device" [PRIMARY smart-ecosystem.uz] | 0.35/0.45/0.65 BHM per month, fiscal included [PRIMARY] | +998 71 200-30-50, smart-ecosystem.uz |
| UzKassa (brand) | cloud kassa used by Paloma365 | **Yes (via Smartpos)** if the "formerly UZKASSA" claim holds [MEDIA, spot.uz 16.07.2025] | snapshot: online-NKM family [MEDIA] | Paloma integration only [PRIMARY Paloma docs]; Paloma: no mixed payment, refunds on the terminal itself [PRIMARY paloma365 classic docs] | sale | cloud login | not found | uzkassa.uz (503) |
| YesPOS / QPosKassa ("QPOS SYS TECH" MChJ) | Sales automation platform: kassa, warehouse, staff (shops, markets) | **Partial (retail POS)** | not stated [NOT FOUND] | "individual API" in Enterprise tier [PRIMARY yespos.uz] | refunds; fiscal QR | app | 100 000 / 200 000 so'm/month [PRIMARY] | +998 33 283-33-33, t.me/yespos |
| MobilKassa (mkassa.uz; Golden Pages names NEWTECH SOLUTIONS SRL [MEDIA]) | Own cash-register app (Nexgo terminal, Android phone, PC) | **Partial (retail cashier app)** | not stated on site | API not mentioned; partner programme via Telegram [PRIMARY] | sale, refund, installment, credit; marking via AslBelgisi [PRIMARY] | device/app; offline up to **24 h** [PRIMARY] | from 100 000 so'm/month [PRIMARY]; moysklad.uz says from 80 000 [MEDIA] | +998 78 555 01 08, t.me/mobilkassa |
| Hippo POS (hippo.uz) | Virtual kassa + POS system for retail/wholesale | **Yes/Partial** | not checked | no API mentioned [MEDIA] | - | - | FM ~1 BHM/yr, connection ~1 BHM, service ~0.15 BHM/month per kassa [MEDIA moysklad.uz 09.07.2026] | hippo.uz |
| KASSO (kasso.uz) | Virtual kassa + POS system | **Yes** | not checked | not mentioned [MEDIA] | - | - | on request | kasso.uz |
| ARCA GROUP (arca.uz) | Online-KKM solution on PAX A930/A930 RTX/A910s (cashier app), 80 000+ clients claimed | **Partial (cashier app on terminal)** | PAX A930 in first online-KKM register 2020 [MEDIA spot.uz] | "OCR integration" item on uzcard.uz nav, no spec [NOT FOUND] | - | device | free connection + 3 months service promo 01.06–31.07.2026 [MEDIA uzcard.uz] | +998 78 120-23-23 |
| E-POS (E-POS SYSTEMS MChJ) | API-first virtual kassa | No (was pure infrastructure) | **Removed from register; its TXKM also removed; 11 000 taxpayers warned** [PRIMARY, t.me/soliqnews/21589; post shows no date; media date 30.10.2026] | was public REST | - | - | - | **excluded** |
| **FiscalBox (fbox.uz, "Made by BCT"; BCT = "Bar Code Technologies" MChJ per directories [MEDIA])** | FiscalBOX online kassa device + subscription; BCT sells POS hardware (monoblocks, scanners, scales) to HoReCa/retail since 2005/2006 | **Probably No** (no own POS software found; site's "Foodservice" section mentions quick ordering for cafes without a product name -> must ask) | snapshot: Fiskalbox online-NKM, reg. 27.07.2020 [MEDIA]; current status not read | **Public API docs** dev.fbox.uz; "use sandbox/test devices" (no URL); example host fbox.ngrok.io; no auth in examples [PRIMARY] | `receipt_type`: order / **prepaid** / **credit**; cash + card in one receipt (mixed); `class_code` (IKPU), `package_code`, VAT, DataMatrix `labels`, `comission_info` [PRIMARY]; refund = "refuse" (unclear) | **device** (terminal_id, printer errors) | bundle 4 590 000 so'm one-time; 220 000 so'm/month (0.5 BHM) or 2 200 000/year (5 BHM) [PRIMARY fbox.uz] | +998 71 205-80-89 (FiscalBox), sales +998 91 162-88-90, info@bct.uz |
| **REGOS: VCR (Regos Software)** | Virtual kassa for Windows + REGOS retail ERP/POS | **Partial** (retail/wholesale POS, no restaurant module found) | "in state register since 2020", no number [PRIMARY vendor]; **AliPos lists Regos among its fiscal services** [PRIMARY alipos.uz/ru/fiscalization] | docs.regos.uz; "documents and test access credentials" on request [PRIMARY]; POS/DocCheque API methods (Create/Pay/Close/SetRefundInfo) [PRIMARY] | sale, refund; IKPU; DataMatrix; UzQR [PRIMARY]; advance/credit/mixed not mentioned | Windows PC + **physical FM via "fiskal modul adapteri"** [PRIMARY] | FM connection 1 BHM per kassa one-time + 1 BHM/yr operator; 0 so'm/month for REGOS users, **100 000 so'm/month for others** [PRIMARY] | +998 55 501 00 30, @regos_manager_bot |
| **SmartOne ("SMART ONE CLUB" MChJ, SmartOne Fintech, London/Dubai HQ claimed)** | Terminals (SmartOne Bank/Pro), cashier and bank software, ERP/CRM, **SmartOne Click (order intake/delivery for food service)**, Government ECR/FDO | **Partial/Yes** (SmartOne Click targets food service) [MEDIA spot.uz 12.06.2025] | 2021 snapshot: "Smart one" virtual kassa v1.0.84; Sept 2024: its service of "Shtrix-M Kassir 5" stopped [PRIMARY t.me/soliqnews/14305] | **Public "Cashbox Fiscal API"** (HTTP 127.0.0.1:8008 or serial): /sale, /refund, /open_shift, /close_shift, /x_report, /check_status, idempotent `documentExtID` [PRIMARY docs.smartoneclub.com]; Uzbekistan coverage not stated | sale, refund (linked to parent), **prepaymentAmount, creditAmount, mixed** (up to 5 cards), per-item VAT; IKPU not named [PRIMARY] | device (local service) | not found | +998 78 113 80 08, info@smartone.uz |
| Multibank (helpdesk.multibank.uz) | Distributes Multikassa (Micros24) free desktop app | n/a (it is Multikassa = Rahmat) | "registered" [PRIMARY helpdesk] | none | sale, refund, advance, credit [PRIMARY] | Windows | free app; FM + TXKM contract needed | +998 55 506 01 01 |
| F-Kassa (Ferrosoft) | MoySklad cashier app | Partial | not found | none public | depends on EPOS module (now excluded) | - | - | +998 78 113-78-90 |

## 2. Payment companies (not competitors; only for their own payments)

| Company | Fiscal function | Scope | Evidence |
|---|---|---|---|
| Payme | Receipt with IKPU/package_code/VAT in `detail`; SetFiscalData callback returns fiscal sign, QR URL, virtual FM number | Payme payments only | [PRIMARY developer.help.paycom.uz, read 19.11.2026] |
| Click | `ofd_data/submit_items`, `submit_qrcode` | Click payments | [MEDIA snippet docs.click.uz] |
| Paylov | `POST /merchant/fiscalization/register/` (api-key); card payments by `transactionId`, **cash payments by externalId + phone**; returns receiptUrl, terminalId, fiscalSign | mirror site, not official | [MEDIA paylov-docs.pages.dev] |
| OCTO | `POST https://secure.octo.uz/fiscal-data`; basket with spic, package_code, nds | OCTO payments | [PRIMARY help.octo.uz] |
| Atmos | payment responses include `ofd_url` | Atmos payments | [PRIMARY docs.atmos.uz, prior round] |
| Multicard | = Rahmat owner | competitor | [PRIMARY rhmt.uz/pos footer] |
| Uzum, Kapitalbank, Anor, Paynet | no in-venue fiscalisation offer for third-party POS found | - | [NOT FOUND] |

Context: from 01.11.2022 receipts are mandatory for e-payment settlements; at that time 25 of 48 payment organisations had completed OFD integration [MEDIA kun.uz 02.11.2022].

## 3. Who the non-fiscal POS vendors use (their partners)

| POS | Fiscal partners seen | Evidence |
|---|---|---|
| Poster | Multikassa only (Poster's "Uzbekistan" fiscal list) | [PRIMARY joinposter.com/en/applications/fiscal-tj, multikassa] |
| Paloma365 | UzKassa, EPosKassa, QPosKassa (= YesPOS / QPOS SYS TECH) | [PRIMARY paloma docs; paloma365.uz blog 10.07.2026] |
| AliPos | Regos, E-POS, YesPOS, SmartOne | [PRIMARY alipos.uz/ru/fiscalization, 21.11.2026] |
| MoySklad UZ | E-POS (page still lists it on 21.11.2026), F-Kassa app, Rahmat | [PRIMARY moysklad.uz/programmy-dlja-biznesa/pos] |
| R-Keeper (rkeeper.uz reseller) | "integration solution via virtual kassa", provider not named; Rahmat lists R_keeper | [MEDIA spot.uz 18.03.2025; PRIMARY rhmt.uz/pos] |
| iiko | Rahmat lists iiko | [PRIMARY rhmt.uz/pos] |
| Jowi | Rahmat lists JOWY; Jowi article on "fiscal printer in Uzbekistan" now 404 | [PRIMARY rhmt.uz/partners; NOT FOUND] |
| Artix | Own virtual kassa build for Uzbekistan via FiscalDriveService + USB FM | [PRIMARY docs.artix.su] |
| Mison | Own "Kassir" app; fiscal partner not named | [PRIMARY mison.uz] |
| Billz | E-POS (2025 case study); current partner not found | [NOT FOUND for 2026] |

Takeaway: every infrastructure partner the other POS vendors use is either a competitor (Rahmat, Smartpos/UzKassa, YesPOS), excluded (E-POS), or a retail POS (REGOS). The only "pure" option visible is a TXKM/FM layer under the POS's own registered virtual kassa (the Artix model).

## Gaps
- Current register list (virtual kassa, online-NKM, TXKM): not read, soliq.uz/ofd.uz 503.
- BCT's software products and whether FiscalBox is in the 2026 register.
- SmartOne Cashbox API coverage for Uzbekistan and registry status.
- Whether "UzKassa" brand really belongs to Smartpos now (only advertorial).
- No pure TXKM (service-only, no POS) identified by name.
