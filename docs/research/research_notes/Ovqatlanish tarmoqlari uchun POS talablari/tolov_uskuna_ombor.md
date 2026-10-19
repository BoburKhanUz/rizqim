# Payments, hardware, connectivity and back office for a restaurant POS in Uzbekistan (as of October 2026)

Prepared 19.10.2026. Scope: how Uzbek food-service businesses get paid, what hardware they use, how reliable power and internet are, how they handle inventory, food cost and accounting, staff features and language. Fiscal receipt law is in `fiskal_background.md` and is not repeated here; it is referenced only where it creates a hardware or payment requirement.

Confidence tags: **[OFFICIAL]** = Central Bank (CBU), government or law; **[VENDOR]** = a company's own site or advertising; **[MEDIA]** = news outlet; **[OLD]** = older than about 2 years (before Oct 2024); **[UNVERIFIED]** = snippet, secondary or not opened in full.

---

## 1. Payments: cash vs card vs QR/app, how terminals are provided, fees, merchant APIs

### Takeaway
Uzbekistan is moving fast to cashless payments. Cash was 16.9% of the economy on 1 April 2026, there are 75.7M cards and about 453k payment terminals, and since **1 July 2026 every legal entity in trade and services must accept the national UzQR**, alongside the cash and card-terminal acceptance that is still mandatory. For a restaurant this means four rails: **cash, a bank card terminal (Uzcard/Humo for about 0.2%, Visa/Mastercard for about 1% or more), UzQR (0.65% cap, only Uzcard/Humo cards behind it) and private QR aggregators** (Rahmat QR 1.5% for restaurants, Tez QR, plus Click/Payme/Uzum apps). I found no published cash/card/QR split for food service specifically.

### Cited Findings

**Macro payment statistics**
- Cash share of the economy was **16.9% on 1 April 2026**, down 1.7 pp year on year. Cash withdrawals were 17% of card-account receipts in Q1 2026 (−2 pp). Bank receipts were 316 trn UZS in Q1 2026 (+35%), against 233 trn in Q1 2025 and 184 trn in Q1 2024 — [Sputnik Uzbekistan, 05.05.2026, citing CBU](https://uz.sputniknews.ru/20260505/dinamika-ispolzovaniya-nalichnoy-i-beznalichnoy-oplaty-v-uzbekistane-57338764.html) [MEDIA]
- Card payment volume was about **72% of GDP in 2025**, against about 25% in 2021 (KPMG with CBU support) — [UzDaily](https://www.uzdaily.uz/ru/kpmg-beznalichnye-raschioty-v-uzbekistane-vyrosli-do-72-vvp/) [MEDIA, UNVERIFIED: search snippet only]
- **75.7M bank cards** were in circulation on 1 Aug 2026 (68.3M at the start of 2026). Payment terminals grew from **430.8k to 453.5k** between 1 Jan and 1 Aug 2026 — [yuz.uz](https://yuz.uz/ru/news/v-uzbekistane-kolichestvo-bankovskix-kart-prevsilo-75-millionov), [podrobno.uz](https://podrobno.uz/cat/obchestvo/kolichestvo-bankovskikh-kart-v-uzbekistane-priblizilos-k-74-millionam-tsb-opublikoval-novuyu-statist/) [MEDIA citing CBU, UNVERIFIED: search summary]
- Humo says it has **184,000+ terminals and 35M+ cards** (undated) — [humocard.uz](https://humocard.uz/ru/) [VENDOR]
- QR payments in 2024: volume +31% to **441.79 bn UZS**, transactions ×2.3 (CBU). This volume is small compared with card flows, so QR was still a niche in 2024 — [gazeta.uz, 10.12.2025](https://www.gazeta.uz/ru/2025/12/10/qr-code/) [MEDIA citing CBU]
- Online payments market share in 2024 (Humo report): **Click 33%, Paynet 15%, Payme 12%** — [pulbek.uz](https://pulbek.uz/ru/article/rynok-onlain-platezei-i-perevodov-v-uzbekistane-kto-lider-2024-goda-click-paynet-ili-payme) [MEDIA, UNVERIFIED]. Net profit for 9 months of 2025: Paynet 330.5 bn, Payme 287.3 bn, Click 235.7 bn UZS — [frank.uz](https://frank.uz/news/paynet-payme-i-click-kto-lidiruet-po-pribyli/) [MEDIA, UNVERIFIED: snippet]
- A 2026 tourist guide calls **Payme, Click and Uzum Bank** the three wallets "you see everywhere, roughly in that order" — [uz-safar.com](https://www.uz-safar.com/blog/uzbekistan-cashless-payme-click-uzum-qr-guide-2026) [UNVERIFIED, low authority]
- Government target: cashless share in trade and services of **75% or more**. Since 1 April 2026, alcohol, tobacco, purchases over 25M UZS and fuel cannot be paid in cash, and "the cash button on the register becomes inactive" — [gazeta.uz, 23.04.2026](https://www.gazeta.uz/ru/2026/04/23/alcohol/); [vc.ru/Octobank](https://vc.ru/top_obzor/2855804-uzbekistan-ogranichivaet-nalichnye-raschety) [MEDIA]. HoReCa was exempted for alcohol and tobacco from 1 June 2026 (PF-100, see fiskal_background.md §1.9).
- Tax incentives tied to cashless share (2025 regime): catering got a **40% VAT refund if the cashless share was above 60%, otherwise 20%**; profit tax was cut from 15% to 7.5% for 2025–2028; condition: catering income must be at least 90% of total income — [search summary of gazeta.uz/kapital.uz, 2026](https://www.gazeta.uz/ru/2026/06/01/small-business/) [UNVERIFIED]. PF-100 (May 2026) makes the 40% refund independent of cashless share (fiskal_background.md §2.3).
- Consumer cashback paid on fiscal receipts was about **1.51 trn UZS in 2025** (+50% y/y). Faster cashback for catering receipts was discussed in May 2026 — [gazeta.uz, 05.05.2026](https://www.gazeta.uz/ru/2026/05/05/cashback/) [MEDIA, UNVERIFIED: snippet]
- **No source gave the share of cash vs card vs QR in restaurants or cafes.** UNDP (May 2026) says only **36% of catering businesses use software such as R-Keeper**, and 73% of those are integrated with the tax base (fiskal_background.md §3.4) [UNVERIFIED].

**UzQR (national single QR), mandatory from 1 July 2026**
- PF-246 decree (10.12.2025): banks issue UzQR from 1 Jan 2026. It is **mandatory for legal entities in trade and services from 1 July 2026**, and not having it counts as a breach of trade rules — [gazeta.uz, 10.12.2025](https://www.gazeta.uz/ru/2025/12/10/qr-code/) [MEDIA]
- Fee: **0.65% of the payment, all paid by the merchant**, and it is a ceiling. CBU split: QR operator 0.20% (covers support, call centre, merchant cabinet and **online-KKM integration**), payment initiator app 0.15%, acquiring bank 0.10%, Uzcard/Humo share 0.15%, payer's bank 0.05%. Market QR fees were 1–3% before this. The payer pays nothing under the official tariff — [CBU, 23.06.2026](https://cbu.uz/uz/press_center/news/4038001/) [OFFICIAL]. Media give slightly different splits; the regulation is registered as MoJ No. 3817 — [kun.uz, 03.07.2026](https://www.kun.uz/news/2026/07/03/ozbekistonda-uzqr-tizimi-ishga-tushdi-asosiy-savollarga-javoblar-3dab63), [spot.uz, 29.07.2026](https://www.spot.uz/ru/2026/07/29/cb-uzqr/) [MEDIA]
- Operator: **Munis (MB clearing)**. Only **Uzcard and Humo cards** work behind UzQR, not Visa or Mastercard. Funds settle **within 1 business day**. Connection through a bank is free. **Ipak Yo'li Bank offers 0.25%**. Some bank apps charged payers an extra 1% at first; Payme announced fee-free UzQR payments — [kun.uz, 03.07.2026](https://www.kun.uz/news/2026/07/03/ozbekistonda-uzqr-tizimi-ishga-tushdi-asosiy-savollarga-javoblar-3dab63) [MEDIA]
- **265,000+ businesses connected by 29.07.2026.** The Tax Committee says only UzQR is mandatory, and merchants **may keep Click, Payme and Uzum QRs**; CBU says banks will not force their removal — [spot.uz, 29.07.2026](https://www.spot.uz/ru/2026/07/29/cb-uzqr/) [MEDIA]
- Cash and card-terminal acceptance **remain mandatory** alongside UzQR — [kun.uz, 03.07.2026](https://www.kun.uz/news/2026/07/03/ozbekistonda-uzqr-tizimi-ishga-tushdi-asosiy-savollarga-javoblar-3dab63) [MEDIA]. A fiscal receipt is also required for UzQR payments (fiskal_background.md §1.2).

**Other QR schemes in restaurants**
- **Dynamic QR** (created per purchase with the amount filled in) is usually generated by cash registers or terminals. **Static QR** (the payer types the amount) is used by small sellers — [spot.uz, 29.06.2026](https://www.spot.uz/ru/2026/06/29/qr/) [MEDIA]
- **Rahmat QR (Multicard)**: one code accepts 15+ methods (cards, Click, Payme, Uzum, Paynet). It is "the most common option in cafes and restaurants", printed on table tents or on the bill. Merchant fee: **restaurants 1.5%**, static QR 1%, fuel 1.2%, retail and vending 2%. The payer pays nothing — [spot.uz, 29.06.2026](https://www.spot.uz/ru/2026/06/29/qr/) [MEDIA]
- **Tez QR (Uzcard)**: found where Uzcard POS terminals are installed, including cafes; payable from **24 apps**. Merchant fee was not stated — [spot.uz, 29.06.2026](https://www.spot.uz/ru/2026/06/29/qr/) [MEDIA]. Uzum Bank app pays Tez QR — [uzcard.uz](https://api.uzcard.uz/en/?p=23589) [VENDOR]
- **Xolis QR (Paynet)**: for sole proprietors and the self-employed only. Withdrawal fee 0.9–1%, 1% tax withheld on each payment, annual cap 1 bn UZS, **no refunds** — [spot.uz, 29.06.2026](https://www.spot.uz/ru/2026/06/29/qr/) [MEDIA]
- Click, Payme and Uzum are **payer apps**; on the merchant side they route through the QR schemes above or through their own merchant QR — [spot.uz, 29.06.2026](https://www.spot.uz/ru/2026/06/29/qr/) [MEDIA]

**Card acquiring: how terminals are provided and fees**
- Typical POS fee for **Uzcard/Humo is 0.2%** at most banks with public tariffs (AnorBank, Apexbank, Hamkorbank, DavrBank, Kapitalbank, NBU). Garant Bank charges 1% — [frank.uz](https://frank.uz/razbory/ekvajring-dlya-biznesa-kak-banki-uzbekistana-tarificziruyut-priyom-beznalichnyh-platezhej/) [MEDIA, UNVERIFIED: page returned 403, search snippet only]
- **Hamkorbank**: Humo terminal rental 0 UZS, 0.2% fee. Uzcard terminal rental free. The fee is deducted at settlement — [hamkorbank.uz](https://hamkorbank.uz/business/acquiring/rental-trading-terminals/) [VENDOR, UNVERIFIED: snippet]. **NBU**: 0.2% on Humo/Uzcard, terminal installation and rental free — [nbu.uz](https://nbu.uz/en/small-business/acquiring/trade-acquiring) [VENDOR, UNVERIFIED: snippet]
- **Tenge Bank (from 01.07.2025)**: POS Humo/Uzcard 0.2%; e-commerce via E-PAY from 0.8%; QR from 0.6% — [tengebank.uz tariff PDF](https://www.tengebank.uz/storage/app/media/tarify-po-iurlicam-ot-01072025g.pdf) [VENDOR, UNVERIFIED: snippet]
- Apexbank: from 0.2% plus **30,000 UZS/month per terminal**. Kapitalbank: 0% on Uzcard for 3 months, then 0.2%. Internet acquiring: Octobank 0.8%, Anorbank 1% — [azma.uz](https://azma.uz/tpost/xrdof8gh81-kak-podklyuchit-internet-ekvairing-dlya), [kreditlar.uz](https://kreditlar.uz/ekvairing) [UNVERIFIED]
- **Visa/Mastercard**: an NBU notice dated 09.01.2026 reportedly sets a 1% merchant fee on international cards processed via UzCard, with a further 2% charged "from the card account" (payer not clear) — [nbu.uz notice](https://nbu.uz/ru/novosti/vnimaniiu-predstavitelei-malogo-i-srednego-biznesa_09_01_26) [UNVERIFIED: page returned 503, search snippet only]. In 2020 CBU fixed Uzcard/Humo merchant fees at 0.2% — [spot.uz, 2020](https://www.spot.uz/ru/2020/02/26/merchant-fee/) [OLD]
- Humo terminals accept Visa cards — [fergana.agency](https://en.fergana.agency/news/113564/) [MEDIA, date not checked]
- Model: **banks give out terminals free or for a small monthly rent** as part of acquiring. Terminals belong to the acquiring bank. Using someone else's terminal or QR carries a 20M UZS fine (fiskal_background.md §1.7).
- Uzcard/Humo terminal interoperability (one terminal for both networks) was delayed by different software stacks. In Jan 2024 it was being tested at Trastbank, InfinBank and Apex Bank, with completion targeted for end of Q1 2024 — [spot.uz, 25.01.2024](https://www.spot.uz/ru/2024/01/25/final-integration/) [OLD]. "Smart Business P10" (ex-UZKASSA) is advertised as a 2-in-1 online cash register and card terminal for **both UZCARD and Humo**, with Click QR built in — [spot.uz, 19.06.2025, advertorial](https://www.spot.uz/ru/2025/06/19/smart-business/) [VENDOR]

**Integrating bank terminals with a POS (amount pushed from POS)**
- Humo's terminal list includes the **Ingenico Lane/3600 Smart PIN pad**, "designed for integration with cash register and POS systems" over **Ethernet, USB or RS232**. **SUNMI P3** "can be used with banking software and cash register systems" — [humocard.uz/business/terminals](https://humocard.uz/ru/business/terminals/) [VENDOR]
- Rahmat (Multicard) offers a SUNMI P3 payment terminal for 990,000 UZS one-off and 0 UZS/month. It accepts Humo, Visa, Mastercard, UnionPay, NFC and a unified QR (Click, Payme, Uzum and others), with an "open API for integration with popular POS systems" — [rhmt.uz/pos](https://rhmt.uz/pos/) [VENDOR]. That page did not list Uzcard among the cards.
- **I found no public ECR protocol (POS→terminal amount push) for Uzcard or Humo terminals.** Integration is arranged bilaterally with the acquiring bank, the processing centre or a vendor such as Rahmat — [search result summary](https://humocard.uz/ru/business/terminals/) [gap]
- Local restaurant POS Mison lists integrations with **Payme, Click, Uzum and Rahmat** plus "cards, cash, QR, bonuses; bill splitting, part payments, mixed payment". It names **no** bank-terminal integration — [mison.uz](https://mison.uz/ru/) [VENDOR]. Paloma POS maps each payment type only to Cash, Card or "don't fiscalize" for UzKassa; its docs describe no terminal integration — [Paloma docs](https://dashboard.palomapos.com/docs/en/pos-v2/fiscalization-uz) [VENDOR]

**Merchant APIs relevant to a POS**
- **Click Pass** (merchant scans the customer's QR shown in the Click app): `POST https://api.click.uz/v2/merchant/click_pass/payment` with `service_id`, `otp_data` (QR contents), `amount` and optional `cashbox_code` (for example "KASSA-1") and `transaction_id`. Auth header = merchant_user_id : sha1(timestamp+secret_key) : timestamp. Status 0 = created, 1 = processing, 2 = success — [docs.click.uz CLICK Pass](https://docs.click.uz/en/?p=426) [VENDOR, via search summary]
- The Click Merchant API also has a **"Fiscalization"** section; web payments include a **payment link** (click-button), card payment, **Split Shop**, Telegram payments and mobile integration. The provider-side Shop API is **Prepare/Complete** — [docs.click.uz](https://docs.click.uz/) [VENDOR]
- Payme: the **Payme Business** app lets a cashier scan the customer's Payme QR with any smartphone — [spot.uz, 13.05.2022](https://www.spot.uz/ru/2022/05/13/payme/) [OLD]. The Payme Merchant API returns fiscal data through `SetFiscalData` (fiskal_background.md §1.8).
- Uzum Bank: **no public merchant tariff or POS API found.** Uzum Bank appears as a payer app (Tez QR, UzQR) — [uzcard.uz](https://api.uzcard.uz/en/?p=23589) [gap]
- **Tips by QR**: no Uzbek tipping service and no Click/Payme/Uzum tips product found. Foreign analogues (Netmonet, Yandex Check, VTB Tips) offer "pay the bill and tip by QR on the pre-check" — [netmonet.co](https://netmonet.co/ru/qr-pay), [yandex.ru/project/eda/check](https://yandex.ru/project/eda/check) [not Uzbekistan]
- Kazakhstan precedent: Kaspi restaurant QR is integrated into iiko through a plugin (iiko 8.7 or later) — [guide.kaspi.kz](https://guide.kaspi.kz/partner/ru/restaurants/conditions/q3872) [not Uzbekistan]

### Inferences
- **POS requirement: multi-tender with split and mixed payments** across at least cash, Uzcard/Humo card, Visa/MC card, UzQR, Rahmat/Tez QR, Click, Payme and Uzum. Each tender must map to a fiscal payment type (cash / card / QR / mixed), because 943 now requires "mixed" on receipts (fiskal_background.md §1.4).
- **Dynamic UzQR on the pre-check (or a customer display) is the strategic integration.** It costs at most 0.65% (as low as 0.25%), settles in T+1, is mandatory anyway, and the operator's share explicitly funds online-KKM integration. That makes it cheaper than Rahmat QR (1.5%), and it can be bound to the order ID so payment is auto-confirmed with no manual reconciliation. Visa/MC (tourists) still need a card terminal.
- Bank terminals are mostly **standalone today**. The cashier types the amount again, which risks mismatches and makes reconciliation manual. An MVP should support "external terminal, manual confirm" with the last 4 digits and the RRN/transaction ID stored (the receipt needs a unique transaction number since Sep 2025). Push-to-terminal integration means vendor-by-vendor deals (Rahmat/Multicard API, Sunmi P3 with bank app, Ingenico Lane over RS232/Ethernet).
- POS should **calculate the cashless share per period**. It drove the 40% vs 20% VAT refund in 2025, and owners track it even though PF-100 removed the condition.
- POS must be able to **block cash for configured product categories** (alcohol and tobacco outside HoReCa, purchases over 25M UZS), because rules change often; gazeta.uz reported the cash button being disabled at registers.
- Payment fees vary roughly sevenfold between rails (0.2% vs 1.5%), so a **"fees by tender" report** is a selling point.

### Gaps
- Cash vs card vs QR split specifically in restaurants or cafes (2025–2026): not found. The CBU annual report 2025 PDF and UNDP report were not read in full.
- Exact Visa/Mastercard merchant discount rate per bank: NBU notice could not be opened.
- Public ECR protocol for Uzcard/Humo terminals: not found.
- Uzum Bank merchant tariffs and API; Payme merchant QR fee for restaurants; Click merchant QR fee: not found.
- Whether UzQR supports per-transaction dynamic codes generated by third-party POS through the Munis API (developer docs): not found.
- Tips-by-QR products in Uzbekistan: none found.

---

## 2. Hardware: terminals, printers, peripherals, prices, dealers

### Takeaway
The typical Uzbek restaurant setup is a **Windows touch monoblock (often second-hand, 1–4M UZS) or an Android all-in-one, an 80 mm Xprinter-class ESC/POS receipt printer (about 350–700k UZS), network kitchen printers routed by workshop, a 2D scanner, and the bank's separate card terminal**. Android payment and cash devices (SUNMI P3/P2, Smart Business P10, A-Pay P10, UZKASSA N5) are the main devices sold with a built-in fiscal module or card reader. Hard price data for Sunmi, iMin or PAX all-in-ones in Uzbekistan was **not found**; only foreign prices are available.

### Cited Findings
- **Humo terminal catalogue**: Ingenico Lane/3600 (PIN pad, ECR-integrable); **SUNMI P3** (Android 11 Go, 58 mm printer, 4G/Wi-Fi/BT); SUNMI P3H and P3H Dual (customer-facing second screen, GPS, eSIM); Sunmi P2 and P2 PRO (Android, 58 mm, dual SIM; "cash register software can be installed"); SUNMI P2 Smart PAD (Ethernet; standalone or attached to a POS). Most models support EMV chip, magstripe, NFC and QR. No prices listed — [humocard.uz](https://humocard.uz/ru/business/terminals/) [VENDOR]
- **Rahmat**: SUNMI P3 payment terminal **990,000 UZS**; "Smart kassa 3-in-1" online-KKM (terminal + fiscal + inventory) **100,000 UZS/month**; virtual kassa for Windows PC, laptop or tablet **123,600 UZS/month**. P3: up to 8 h active battery, eSIM + SIM + Wi-Fi, **offline mode stores data up to 24 h** — [rhmt.uz/pos](https://rhmt.uz/pos/) [VENDOR]
- Fiscal-capable Android online-KKMs in local catalogues: A-Pay P10, UZKASSA N5 (smartpos.uz) and Smart Business P10 (2-in-1 kassa + Uzcard/Humo terminal) — fiskal_background.md §3.3; [spot.uz, 19.06.2025](https://www.spot.uz/ru/2025/06/19/smart-business/) [VENDOR]
- **Receipt printers (Tashkent retail)**: Xprinter XP-Q80A (80 mm, USB+LAN, 300 mm/s) **480,000 UZS** and XP-80T **580,000 UZS** at Rofeev.uz; XP-Q80A **575,000–700,000 UZS** on Prom.uz; Xprinter XP-Q890K **850,000 UZS** and XP-Q200II (USB+COM) 1,589,348 UZS at Glotr.uz — [search summary of rofeev.uz/prom.uz/glotr.uz](https://glotr.uz/pos-printeri-v-tashkente/) [VENDOR, UNVERIFIED: rofeev.uz returned 502]
- **Comp Lab (Prom.uz)**: 80 mm receipt printer **350,969 UZS**; YesPOS omnidirectional 2D scanner **302,559 UZS**; monoblock with printer and scanner **4,400,000 UZS**; a "POS kit" (printer + monoblock + scale + scanner) listed at 1,500,000 UZS — [prom.uz/company/comp-lab](https://www.prom.uz/company/comp-lab/) [VENDOR, UNVERIFIED: the kit price looks implausibly low]
- **Shop4Shop (Prom.uz)** lists a cash drawer, programmable keyboard, Zebra DS9208 scanner and ШТРИХ МП 200 floor scales, all "out of stock" with prices on request — [prom.uz/company/shop4shop](https://www.prom.uz/company/shop4shop/) [VENDOR]
- **OLX.uz**: POS touch monoblocks from about **1,000,000 UZS used** up to **3,850,000 UZS** (Core i5 8/128 15.6", advertised for "r-keeper, jowi, iiko, billz"). Sellers bundle "2 monoblocks + 2 printers + safe" kits for cafes. One ad: monoblock **$170**, Xprinter 80 mm, scanner **$30** (about 8 months old) — [olx.uz pos monoblock](https://www.olx.uz/list/q-pos-%D0%BC%D0%BE%D0%BD%D0%BE%D0%B1%D0%BB%D0%BE%D0%BA/), [olx.uz ad](https://www.olx.uz/d/obyavlenie/pos-monblok-pos-170-monoblok-jowi-iiko-idokon-ID3Jg4p.html) [UNVERIFIED: listing prices]
- **Sunmi D3 Mini** (10.1", built-in 80 mm printer, 1D/2D scanner, NFC, Android 13) in neighbouring markets: Kyrgyzstan **59,000 KGS**, EU **from €407.81 ex-VAT**, Russia about 38,557 RUB; iMin Swan 2 from €529 ex-VAT — [login.kg](https://login.kg/torgovoe-oborudovanie/kassovye-apparaty/sunmi-d3-mini-pos-system), [satomar.shop](https://www.satomar.shop/en/) [VENDOR, not Uzbekistan]
- **Mison's restaurant hardware stack** (a local POS): monoblocks, tablets or ordinary PCs; **thermal receipt and kitchen printers, network ESC/POS, print routing by workshop (sex)**; routers and access points; **a local server on site**; a **Face ID terminal** for staff login. The POS also runs on phones — [mison.uz](https://mison.uz/ru/) [VENDOR]
- Regulatory hardware needs (from fiskal_background.md): a **2D scanner** (ISO/IEC 15415) is mandatory for kassa; paper receipts must be **at least 55 mm wide** with characters at least 2 mm high; the kassa must accept all bank cards and QR through the same device.

### Inferences
- **Supported printer profile**: ESC/POS over **LAN (primary, for kitchen and bar), USB and Bluetooth** (handheld waiter devices). Support **58 mm and 80 mm**, Cyrillic and Uzbek Latin code pages, including the ʻ/ʼ characters (oʻ, gʻ) — render as raster/bitmap if the printer lacks the glyphs. Print routing by workshop is expected.
- **Device targets in priority order**: Windows monoblock (installed base; iiko, Jowi and R-Keeper legacy), Android all-in-one (Sunmi, iMin) and Android handheld (Sunmi P2/P3 class, which the banks and Rahmat already distribute), Android phones for waiters, then iPad (rare; tied to Poster, and it cannot host a USB fiscal module).
- Budget for a small cafe (one cash point plus a kitchen printer), using the listed prices: monoblock 1.5–4M + 2 × 80 mm printers about 1–1.4M + 2D scanner about 0.3M + cash drawer. Roughly **3–6M UZS (about $250–500)** excluding fiscal kassa fees and the bank terminal (free from the bank). This is my estimate from the listings above, not a quoted price.
- Scales matter for weighed items (shashlik by weight, bread, desserts, salads by weight). The protocol is not documented locally; plan for common RS232/USB serial protocols (CAS, Shtrih-M are sold in Tashkent) as a later feature.

### Gaps
- Uzbek prices and dealers for Sunmi T2/T3/D3, iMin Swan/Falcon, PAX A920/A35, Telpo and Urovo: none found (OLX and dealer searches returned no matches). Ask the Rahmat, Smart Business and Mison sales teams.
- Customer display and KDS screen usage and prices in Uzbek restaurants: no data.
- Scale models and protocols used in Uzbek catering: no data.
- Which bank terminals expose an ECR/integration API: no public docs.

---

## 3. Connectivity and power: how often restaurants go offline and for how long

### Takeaway
**Power cuts, not internet speed, are the main operational risk.** CBU's 2024 business survey named gas and electricity interruptions the **#1 obstacle for Tashkent businesses**. Restaurateurs report several unannounced cuts per day in 2025–2026 (Mirabad: 8 in one day), low voltage (170–180 V), and UPS backup of only 30–60 minutes. Fixed broadband averages about 94 Mbps nationally, but regional quality, mobile coverage outside cities and occasional backbone incidents vary. An offline-first POS with a local server or peer sync and long battery life is required. The fiscal layer allows at most 48 h offline.

### Cited Findings
- CBU (2024 data): interruptions in gas and electricity became **the main obstacle to doing business in Tashkent**, and the share of entrepreneurs working at partial capacity rose to **55%** — [spot.uz, 04.07.2025](https://www.spot.uz/ru/2025/07/04/tunnel-light/) [MEDIA citing CBU]
- Same article (Tashkent restaurateurs, summer 2025):
  - ChayKof/Moloko cafes report outages in Mirzo-Ulugbek and Yakkasaray "**several times a day, without warning**" since April; one cut came 10 minutes after 150+ cakes went into the ovens.
  - **Mirabad district had 8 outages in one day.**
  - A hookah-lounge owner reports daily cuts at **peak afternoon and evening hours**, with refrigerators and motors burning out weekly. When systems go down, "**bills have to be calculated by hand while guests wait**", and some guests leave.
  - A food producer has about **2 hours before freezer temperatures rise**.
  - Office **UPS units last 30–60 minutes**.
  - Voltage of **170–180 V** instead of 220 V for 2–3 years.
  - Generators are often impossible because of lack of space or neighbours' refusal; one producer installed batteries, an inverter and solar panels.

  — [spot.uz, 04.07.2025](https://www.spot.uz/ru/2025/07/04/tunnel-light/) [MEDIA]
- Chilanzar residents: after a relatively stable 2024, "**regular and long outages returned in 2025 and 2026**" — [gazeta.uz, 19.07.2026](https://www.gazeta.uz/ru/2026/07/19/chilanzar-heat/) [MEDIA]
- June 2026: a fire at the "Vystavochnaya" substation (Almazar) cut power to **6,200 households, 800+ organisations and 18 social facilities** — [gazeta.uz, 01.06.2026](https://www.gazeta.uz/ru/2026/06/01/yongin/) [MEDIA]. June 2025: a blackout left three Tashkent districts without power for **1.5 h** — [spot.uz, 18.06.2025](https://www.spot.uz/ru/2025/06/18/blackout-tashkent) [MEDIA]. July 2025: planned cuts explained as "preparation for winter" — [gazeta.uz, 04.07.2025](https://www.gazeta.uz/ru/2025/07/04/electricity/) [MEDIA]
- September 2026: a **1,600 MW trip at the new EDF thermal plant in Syrdarya** caused outages in several regions and in Kazakhstan — [vz.ru, 25.09.2026](https://vz.ru/news/2026/9/25/1455185.html) [MEDIA]
- Winter: kun.uz ran "Winter is testing the energy system: where did the power go out today?" on 20.12.2025 (article body not retrievable). Planned cuts in Fergana region (Oltiariq, Rishton, Qo'shtepa) on 20.12.2025, 05:00–07:00 and 17:00–17:30 — [kun.uz, 20.12.2025](https://kun.uz/news/2025/12/20/qish-energotizimni-sinamoqda-bugun-qayerlarda-elektr-uzilishlari-boldi) [MEDIA, partially verified]. July 2026: special HQs set up in 15 districts for gas and power problems; about 40% of citizens' energy complaints concern electricity — [search summary, kun.uz](https://kun.uz/97632457) [UNVERIFIED]
- Historical: the **25 January 2022 Central Asia blackout** and winter gas cuts to businesses, including catering, ordered in some winters — [eurasianet](https://russian.eurasianet.org/%D1%83%D0%B7%D0%B1%D0%B5%D0%BA%D0%B8%D1%81%D1%82%D0%B0%D0%BD-%D0%BE%D1%82%D0%BA%D0%BB%D1%8E%D1%87%D0%B5%D0%BD%D0%B8%D0%B5-%D1%8D%D0%BB%D0%B5%D0%BA%D1%82%D1%80%D0%BE%D1%8D%D0%BD%D0%B5%D1%80%D0%B3%D0%B8%D0%B8-%D0%B2%D1%8B%D0%B7%D0%B2%D0%B0%D0%BB%D0%BE-%D0%BE%D0%B1%D0%B2%D0%B0%D0%BB-%D0%B2%D1%81%D0%B5%D0%B9-%D1%81%D1%82%D1%80%D0%B0%D0%BD%D1%8B), [azattyqasia](https://www.azattyqasia.org/a/%D1%83%D0%B7%D0%B1%D0%B5%D0%BA%D0%B8%D1%81%D1%82%D0%B0%D0%BD-%D0%BE%D0%B3%D1%80%D0%B0%D0%BD%D0%B8%D1%87%D0%B8%D0%BB-%D0%B7%D0%B8%D0%BC%D0%BE%D0%B9-%D0%B8%D1%81%D0%BF%D0%BE%D0%BB%D1%8C%D0%B7%D0%BE%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5-%D0%BF%D1%80%D0%B5%D0%B4%D0%BF%D1%80%D0%B8%D0%BD%D0%B8%D0%BC%D0%B0%D1%82%D0%B5%D0%BB%D1%8F%D0%BC%D0%B8-%D0%BF%D1%80%D0%B8%D1%80%D0%BE%D0%B4%D0%BD%D0%BE%D0%B3%D0%BE-%D0%B3%D0%B0%D0%B7%D0%B0/30927121.html) [OLD]
- Internet: Ookla fixed broadband average **94.50 Mbps (Aug 2026), rank 73**; Uzbekistan claims 1st place in the CIS — [gov.uz, Sept 2026](https://gov.uz/en/digital/news/view/220126) [OFFICIAL]. Dec 2025: **90.94 Mbps**, fixed rank fell from 77 to 83, and mobile fell 5 places — [kun.uz, 17.01.2026](https://kun.uz/en/news/2026/01/17/uzbekistan-slips-five-spots-in-global-mobile-internet-speed-ranking) [MEDIA]. In Sept 2025, **Samarkand and Kokand had faster internet than Tashkent** — [search summary](https://zamin.uz/en/uzbekistan/221541-uzbekistans-rank-in-the-internet-speed-ranking-revealed.html) [UNVERIFIED]
- Quality incidents: users across regions reported unstable connections, speed drops and periodic disconnects (causes cited include backbone wear, construction cable cuts and **power cuts at network nodes**) — [podrobno.uz, undated](https://podrobno.uz/cat/tehnp/pochemu-internet-v-uzbekistane-likhoradit-i-kak-izbezhat-utechek-dannykh/) [MEDIA, UNVERIFIED date]. Uztelecom publishes planned maintenance windows (for example 00:00–06:00) and warns of short mobile interruptions in Navoi — [uztelecom.uz](https://uztelecom.uz/ru/novosti/opoveshheniya/uvazhaemye-abonenty-2835/) [VENDOR]. A Sept 2024 Uztelecom routing error caused a global BGP incident of about 40 minutes — [inbusiness.kz](https://inbusiness.kz/ru/news/oshibka-v-uzbekistane-privela-k-sboyu-interneta-vo-vsem-mire) [OLD]
- How local vendors respond:
  - Mison: "the kassa works without internet and syncs when connection returns; **a local server is installed on site**" — [mison.uz](https://mison.uz/ru/) [VENDOR]
  - Rahmat SUNMI P3: offline storage for up to 24 h — [rhmt.uz/pos](https://rhmt.uz/pos/) [VENDOR]
  - Paloma: unsent receipts are marked "Not fiscalized" and resent; a shift cannot close until they are fiscalized — [Paloma docs](https://dashboard.palomapos.com/docs/en/pos-v2/fiscalization-uz) [VENDOR]
  - Legal limit: the first offline receipt must reach the OFD within **48 h**, after which the kassa blocks (fiskal_background.md §1.5).

### Inferences
- **POS requirements**:
  - **Offline-first**: local DB on each device and/or an on-site LAN hub, so orders, kitchen tickets, bills and cash/card payments continue with no internet.
  - **Store-and-forward** for fiscal receipts, alerting well before the 48 h kassa block.
  - **Fast restart and state recovery** after power loss: open tables and orders must survive a hard power-off.
  - **LAN printing** that does not depend on cloud.
  - **Battery-friendly devices** (Android handhelds or tablets beat monoblocks during cuts).
  - **LTE failover** on the router or device eSIM.
- QR and app payments (UzQR, Click, Payme) and online fiscalization **do not work offline**. During an internet cut the realistic tenders are cash and the bank terminal (which has its own SIM). The POS must queue these and reconcile later.
- Typical offline episodes, from the anecdotes above: **minutes to about 2 hours**, several times a day in bad periods. Multi-hour regional or winter events also happen. Design for at least a full shift (8–12 h) of local operation, within the 48 h fiscal cap.

### Gaps
- No systematic outage statistics (SAIDI/SAIFI) by city or region for 2025–2026 were found. Evidence is anecdotal or from news events.
- No survey of restaurant internet connection types (fibre vs LTE) or uptime.
- Winter 2025–26 outage details: the kun.uz article body could not be retrieved.

---

## 4. Inventory, food cost and accounting (tech cards, bazaar purchases, 1C, e-invoices)

### Takeaway
Restaurants buy a large share of produce and meat **for cash at bazaars from individuals without invoices**. Since a Cabinet of Ministers resolution of **7 Nov 2024 (No. 745, Annex 2)** this is documented with an **electronic purchase act** sent through the tax system, available only if catering income is at least 90%. Accounting is mostly kept in **1C (Uzbek localisation of 1C:Accounting)**, which exchanges e-invoices via **Didox or the Soliq operator**. Incoming supplier invoices arrive as e-faktura through about 15 licensed operators (Didox, Faktura.uz and others). Restaurant POS products sold locally (Mison, iiko) handle tech-card based write-off, food cost, stock counts and waste. **No Uzbek source gave typical food-cost percentages.**

### Cited Findings
- **Electronic purchase act**: the "Regulation on electronic formalization of receipt documents for purchases of agricultural products for cash from individuals by catering enterprises" (Annex 2 to **CoM Resolution No. 745 of 07.11.2024**) applies to catering businesses whose catering income is **at least 90%** of income. Problem case: a restaurant that also sold factory soft drinks, alcohol and tobacco under other IKPU codes was computed below 90% and **lost access to sending purchase acts**. The Tax Committee had given no guidance on paper alternatives — [buxgalter.uz, 20.12.2024](https://buxgalter.uz/publish/doc/text204945_kak_oformit_zakup_selhozprodukcii_obshchepitu_esli_v_obshchem_obeme_realizacii_-_menee_90_procentov_sobstvennoy_produkcii) [MEDIA/expert]
- Catering may buy food **without a contract**; on request the supplier must issue the invoice by the end of the month. Tax officials say paying for farm produce by transferring to individuals' cards is not allowed (it may be treated as the individual's income). Purchases of farm produce from individuals benefit from a personal income tax (NDFL) exemption — [buxgalter.uz](https://buxgalter.uz/publish/doc/text182757_kak_pravilno_rasschitatsya_za_tovary_kuplennye_na_rynke), [buxgalter.uz](https://buxgalter.uz/publish/doc/text181302_kak_oformit_pokupku_selhozprodukcii_za_nalichnyy_raschet) [MEDIA/expert, UNVERIFIED: snippets, dates not checked]
- **E-invoices (ESF/e-faktura)**: created on my.soliq.uz or through one of about **15 licensed operators (Didox, Faktura.uz and others)** connected by a roaming hub. Invoices are signed JSON documents (e-signature). B2C restaurant sales fall under the cash-register rules, not e-faktura — [vatupdate country booklet](https://www.vatupdate.com/?p=355197), [gov.uz list of operators](https://gov.uz/ru/soliq/pages/elektron-hisobvaraq-fakturalar) [OFFICIAL/secondary]
- 1C ↔ e-invoice: 1C's EDO module can send invoices through **Didox or Soliq Service** using the operator account and e-signature — [softteampro.uz](https://softteampro.uz/blog/kak-nastroit-1s-edo-otpravka-schetov-faktur-cherez-didox-ili-soliq/) [VENDOR]. There is "1C:Accounting for Uzbekistan" with bank exchange — [search summary](https://softteampro.uz/blog/kak-nastroit-1s-edo-otpravka-schetov-faktur-cherez-didox-ili-soliq/) [UNVERIFIED]
- 1C-Rarus lists among its **flagship Uzbek projects an ERP plus catering module and analytics for a restaurant chain** — [bizon.ru](https://bizon.ru/news/print/id/713218/format/smoothbox) [VENDOR, UNVERIFIED]. 1C:Obshchepit 3.0 is a Russian product supported by 1C-Rarus; **no Uzbek edition confirmed** — [solutions.1c.ru](https://solutions.1c.ru/upload/reestr/ec0/u76si7pwwfarzf2aj6v5i18npfqasx99/Funktsionalnye-kharakteristiki_1S_Obshchepit.pdf) [VENDOR]
- iiko → 1C: iiko exports goods receipts and issues, **production (preparation) acts, write-off acts and inventory counts** to 1C:Accounting 2.0/3.0 through an external processor (Russian integrator) — [iiko.restoran-service.ru](https://iiko.restoran-service.ru/servicers/integratsiya-s-1s/) [VENDOR, Russian market]. iiko–1C integration services are offered in Tashkent by Denvik — [tashkent.denvic.ru](https://tashkent.denvic.ru/service/avtomatizatsiya-obshchepita/integratsiya-iiko-s-1s/) [VENDOR]
- Tech cards and costing (Russian/Soviet practice, also used in Uzbekistan): a tech card holds **gross and net quantity per portion** (yield/loss on cold and hot processing). The calculation card (form OP-1) sets the dish price — [sberbusiness.live](https://sberbusiness.live/publications/tekhnologicheskaya-karta), [secrets.tbank.ru](https://secrets.tbank.ru/documents/kalkulyacionnaya-karta/) [Russian, not Uzbekistan]
- **Mison (local POS) inventory scope**: tech-card based ingredient write-off at sale, costing, **food cost**, stock counts, write-offs, **stop-list**, stock alerts, **purchase-price history**, P&L, cash flow, supplier debts. Pricing: **390,000 UZS/month per outlet** ("Start", no stock or finance) and **690,000 UZS/month** ("Full") — [mison.uz](https://mison.uz/ru/) [VENDOR]
- Marked goods (alcohol, beer, water, soft drinks) are received by restaurants via **ESF with marking codes**. From 1 June 2026 their codes count as "sold" at purchase for HoReCa (fiskal_background.md §2.4).

### Inferences
- **POS requirements (back office)**:
  - **Recipes / tech cards** with gross and net weight, processing losses (cold/hot), semi-finished products, modifiers and yield; automatic write-off on sale.
  - **Two purchase flows**:
    - (a) **E-faktura import** from Didox, Faktura.uz or my.soliq, to create goods receipts with supplier STIR, IKPU and VAT. Integration with roaming operators by API is likely needed.
    - (b) **Bazaar cash purchases**: quick mobile entry by the purchaser (item, kg, price, seller name/phone/passport if needed, photo), producing the data needed for the **electronic purchase act** and a cash-out ("rasxod") from the till or the safe.
  - **Petty-cash / safe accounting**: cash taken from the register to buy at the bazaar is very common, so cash drawer payouts with categories are needed.
  - **Stock counts** (full and partial, by storage location), write-offs (spoilage, staff meals, comps) with reasons, and a **theoretical vs actual food-cost report**.
  - **Export to 1C:Accounting (Uzbekistan)**: daily sales by IKPU and VAT rate, payment-type totals, goods receipts and purchase acts, write-offs. CommerceML/XML, a 1C external processor or Excel/CSV are the realistic formats. iiko's practice of exporting production and write-off acts is the benchmark. I found no regulation that **requires** a POS to export to 1C or Didox; it is a market expectation.
- The **90% catering-income test** means the POS should report revenue split by IKPU group (catering 10202xxx vs resold drinks, alcohol and tobacco), so owners can watch their eligibility for purchase acts, the VAT refund and the profit-tax rate.

### Gaps
- Typical food-cost % in Uzbek restaurants (plov centres, choyxona, fast food, fine dining): **no source found**.
- How many restaurants use 1C, and which edition; whether a 1C:Obshchepit Uzbek localisation exists.
- Didox and Faktura.uz public APIs for third-party import of incoming invoices: not checked.
- Current text and practice of CoM 745 purchase acts in 2026 (format, portal, API): not read in the original.

---

## 5. Staff and HR features (waiter PINs/cards, shifts, payroll % of sales, service charge)

### Takeaway
Local POS competitors already offer **payroll as % of check, KPI, shifts, timesheets and biometric (Face ID) login**. The common **10–20% "obsluga" service charge** is under regulatory pressure: in Nov 2025 the Competition Committee warned that it must be disclosed in advance and cannot be charged for services not rendered. No data was found on the typical waiter pay % in Uzbekistan.

### Cited Findings
- Mison staff module: **"salary from check" (% of sales), KPI, shifts, timesheet, Face ID biometric login**; control reports on **deleted checks and discounts**, Z-report, safe control — [mison.uz](https://mison.uz/ru/) [VENDOR]
- **Competition Committee (21.11.2025)**: after complaints that restaurants add **10–15%, up to 20%** service charges on top of menu prices and demand advance deposits, it warned that this may break consumer-protection law. Businesses must **disclose menu prices, service terms and the service-charge amount in advance** — [afisha.uz, 21.11.2025](https://www.afisha.uz/uz/gorod/2025/11/21/restoran), [spot.uz, 21.11.2025](https://www.spot.uz/oz/2025/11/21/restaurants-warned/) [MEDIA]
- Regional comparison (not Uzbekistan): in Kazakhstan, 5–7% (sometimes all 10%) of the 10% service charge goes to the waiter — [informburo.kz](https://informburo.kz/mneniya/zulfiya-yusupova/esli-otmenit-10-za-obsluzivanie-to-ceny-v-restoranax-seryozno-vyrastut). Kyrgyz HoReCa says the 15% service charge funded wages, and labour costs are 30–40% (up to 50%) of revenue, compared with 25% previously — [economist.kg, 07.02.2026](https://economist.kg/biznes/2026/02/07/pochiemu-15-za-obsluzhivaniie-pierieiekhali-v-tsienu-bliud-pozitsiia-khorieka-klub/) [MEDIA, not Uzbekistan]
- Fiscal rule: a kassa password of at least 6 characters is required (fiskal_background.md §3.2).

### Inferences
- **POS requirements**:
  - Per-employee **PIN** (6+ characters for fiscal functions) plus optional card/NFC or Face ID login.
  - Roles: waiter, cashier, manager, cook, courier, owner.
  - **Configurable service charge** (on/off, % per hall or table, shown on the pre-check and menu, and treated correctly on the fiscal receipt with its own IKPU/service line).
  - **Waiter % of sales or of service charge** payroll calculation; shift open/close with cash count; audit of voids, deletes and discounts (owners are very sensitive to waiter fraud, as the "deleted checks" report in Mison shows).
- With the Competition Committee pressure, some venues may move the service charge into menu prices (as Kyrgyzstan did), so the POS should support both models.

### Gaps
- Typical waiter pay structure in Uzbekistan (fixed + % of sales, share of the service charge): no source found.
- Tax treatment of the service charge and tips (whether tips are wages, whether they go on the fiscal receipt): not researched; check with an accountant.
- Shift scheduling practices: no data.

---

## 6. Language: Uzbek Latin vs Cyrillic vs Russian on receipts and staff screens

### Takeaway
Fiscal receipts must be **mainly in the state language** (Uzbek), and the **Latin script is official**. In practice **Cyrillic Uzbek and Russian remain widespread** (bazaar signage is "overwhelmingly Cyrillic"). A new Latin-alphabet law (28 letters plus an apostrophe) passed parliament in mid-2026. Local POS vendors ship **Uzbek and Russian interfaces, and some add Karakalpak**.

### Cited Findings
- Fiscal receipt data is mainly in the state language (943, Annex 2) — fiskal_background.md §1.4/§3.2 [OFFICIAL]
- New Latin-based Uzbek alphabet: the Legislative Chamber approved it (July 2026) and the Senate reportedly approved it too, pending presidential signature. It moves from 26 letters plus 3 digraphs to **28 letters and one apostrophe**. Existing documents, **signs and plates keep legal force** and need no immediate replacement. The authorities intend to move press, state bodies and official document flow fully to Latin and end parallel Cyrillic use. Experts say both scripts are used in practice; **bazaar signs are mostly Cyrillic** — [afisha.uz, 07.07.2026](https://www.afisha.uz/ru/gorod/2026/07/07/alphabet), [forbes.kz](https://forbes.kz/articles/uzbekskiy-alfavit-na-latinitse-senatory-odobrili-izmeneniya-61055f), [bes.media](https://bes.media/news/senatory-uzbekistana-odobrili-izmeneniya-v-alfavite-na-latinitse/) [MEDIA, final status UNVERIFIED]
- Mison: kassa interface in **Uzbek, Russian and Karakalpak**; support in Uzbek and Russian — [mison.uz](https://mison.uz/ru/) [VENDOR]

### Inferences
- **POS requirements**:
  - UI in **Uzbek Latin, Uzbek Cyrillic and Russian** (Karakalpak and English optional; English helps tourists on the guest-facing QR menu and bill).
  - Per-user language choice: cooks and waiters often prefer Russian or Cyrillic, while owners and accountants may want Latin.
  - **Menu items need multilingual names**: the fiscal name in Uzbek Latin, the kitchen ticket in the staff language, and the guest menu in uz/ru/en.
  - Store text as Unicode and avoid hard-coding the current Latin orthography (the oʻ/gʻ characters may change under the new alphabet law).
  - Thermal printers need code-page or raster support for Cyrillic and for the ʻ character.

### Gaps
- Final status and implementation dates of the 2026 alphabet law, and whether it changes the oʻ/gʻ/sh/ch spelling used in fiscal and IKPU catalogue names: not confirmed.
- Survey data on which language restaurant staff actually use in POS screens: not found.
