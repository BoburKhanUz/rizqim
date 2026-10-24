# Fiscal terminals in Uzbekistan for small food businesses, and how a third-party cloud POS can fiscalize (as of 24.10.2026)

Scope note: this extends, and does not repeat, `reports/Choyxona ishlashi va POS talablari.md` §5 and `reports/Ovqatlanish tarmoqlari uchun POS talablari.md` §3 and §5. It also builds on the notes `Ovqatlanish tarmoqlari uchun POS talablari/fiskal_background.md` and `Ovqatlanish formatlari POS talablari/tolov_fiskal_agregatorlar.md`. Those already cover the following, and this file does not repeat them:
- the OFD ("Yangi texnologiyalar") chain;
- the virtual-kassa registration steps;
- receipt fields;
- the bo'nak→sotuv and sotuv→kredit sequences;
- the fines table;
- the vendor price table (Rahmat 123 600/oy, REGOS, Poster+Multikassa, E-POS).

What is new here:
1. The exact text and effective date of the VM 393 catering ban, read directly from lex.uz. It resolves the "[ANIQ EMAS] 22.10 or 21.07" question in the earlier report.
2. Which "fiscal terminals" (2-in-1 card terminal plus online-NKM) are actually on the market, and their service centres.
3. Prices of those devices.
4. The OFD's own developer documentation, which shows the fiscal-module receipt types (sale, advance, credit, refund).
5. The legal status of "separate POS + cashier re-types the total".

Labels used: **[CONFIRMED]** = primary legal text or OFD developer doc; **[VENDOR]** = vendor or advertising claim; **[MEDIA]** = news or expert article; **[INFERENCE]** = my reasoning.

---

## Q1. What is the "ordinary fiscal terminal" choyxonas and cafes use, and who supplies and approves them?

### Takeaway
The owner's "ordinary fiscal terminal that prints a fiscal receipt for card and cash" is almost certainly a **2-in-1 Android "smart kassa"**. It is a state-registry **online-NKM** (onlayn nazorat-kassa mashinasi) that has a fiscal module (FM) inside the body and a built-in bank card and NFC acquirer.

Typical models:
- Sunmi P2 Pro or P3;
- A-Pay / ex-UZKASSA P10;
- UZKASSA N5;
- SmartOne P1000/P2000L;
- Aisino A90 (Paymob24);
- NEXGO N82 (MobilKassa);
- CASHBOX.

Each is sold and serviced by a registered technical service centre (TXKM/ЦТО). Since 2023 the law no longer allows a cash register that cannot take cards. Since the VM 393 amendments (2026), the register must accept **all bank cards and QR codes directly through the device**. This is why the terminal and the kassa have merged into one box.

There is one state fiscal data operator, the Tax Committee's "Yangi texnologiyalar" centre (soliq.uz / ofd.uz). There are no competing private OFDs. Names like "UZKASSA", "e-POS" and "QPos" are kassa or TXKM vendors, even when blogs call them "OFD operators".

### Cited Findings
- **Humo's merchant page lists the online-NKMs integrated with HUMO**, with the TXKM for each (page last updated 23.10.2026). [CONFIRMED as Humo's list; device capabilities VENDOR] — [humocard.uz, Onlayn NKM](https://humocard.uz/uz/business/onlayn-nkm/)

  | Device | Type | Service centre (TXKM) | Notes from the page |
  |---|---|---|---|
  | **A-Pay P10** | Online cash register | Not named | Humo and Visa; Uzcard via the single QR; Tasnif integration; scanner |
  | **UZKASSA N5** | Android online cash register + POS | SPECIAL TEXNOSOFT MChJ | Made in Uzbekistan; NFC/EMV |
  | **SmartOne P1000 / P2000L / BANK PRO** | Android "3-in-1 / 4-in-1" | Smart One Club MChJ | — |
  | **CASHBOX** | Android cash register + bank terminal | TSS CENTER MChJ | — |
  | **Sunmi P2 Pro** | Smart cash register + terminal | MICROS24 MChJ | Humo, Visa, MC, QR; "integrates with MoySklad and Poster, open API" |
  | **Paymob24 Aisino A90** | Online cash register + terminal | Linkserv Group | Humo, Visa, MC, UnionPay, Amex, MIR; Click/Payme/Uzum QR; "integration with external software" |
  | **NEXGO N82** | Online cash register + terminal | NEWTECH SOLUTIONS (MobilKassa) | Tasnif, OFD and 1C; "open API" |
  | **MERKURIY-115F-UZ, ORION-105F-UZ** | Classic (non-Android) cash registers | Onlinekassa MChJ | Take Humo only with an external Ingenico pin-pad (Lane3000, IPP320, Desk 3500, Move 2500) |
  | **SIMURG 001** | Classic cash register | Garov taraqqiyot NKM | Takes Humo only with an external Ingenico pin-pad |

- **Smart Business (formerly UZKASSA) P10 terminal**, launched 08.05.2025:
  - "a universal P10 terminal" combining a POS terminal and an online cash register;
  - usable as a cash register only, a terminal only, or "two in one";
  - accepts UZCARD and HUMO contactless, Visa/MC/UnionPay, SBP and QR (A-Pay, Payme, Oson, Paynet);
  - works on 2G/3G/4G with a 24-hour battery;
  - sold on instalments; "more than 90 offices".
  - The piece is labelled advertising. [VENDOR] — [kapital.uz, 08.05.2025](https://kapital.uz/uz/yangi-p10-terminali/)
- **Smart Ecosystem (smartpos.uz, now smart-ecosystem.uz)** claims "more than 80 000 companies" in its ecosystem. [VENDOR] — [smart-ecosystem.uz](https://smart-ecosystem.uz/uz/)
  - It sells fiscal cash registers and payment terminals, POS monoblocks and a virtual kassa.
  - It has a "Smart Cafe" product for cafes and restaurants.
  - Smart Business tariffs are quoted as "0.35 / 0.45 / 0.65 BHM" (Start / O'sish / Tarmoqlar), "fiscalization included". The period is not stated.
  - It took part in a "Qulay terminal" programme with UZCARD (24.03.2026).
- **Rahmat** sells:
  - a "Платёжный терминал на базе SUNMI P3" for **990 000 UZS**;
  - "Online-KKM (Smart Kassa)" at **100 000 UZS/month**, including the card terminal (Humo, Visa, Mastercard, UnionPay), QR (Click, Payme, Uzum "+10"), stock accounting and receipt fiscalization;
  - a payment terminal only (no kassa) at **0 UZS/month**;
  - a virtual kassa at **123 600 UZS/month** for a laptop, PC or tablet.

  Rahmat's legal offers are hosted on corp.multicard.uz. [VENDOR] — [rhmt.uz/pos/tariffs](https://rhmt.uz/pos/tariffs/); [rhmt.uz/pos/integration](https://rhmt.uz/pos/integration/)
- **Card acceptance by the kassa is legally mandatory:**
  - From **01.04.2022**, terminal payments must appear on the online-NKM or virtual-kassa receipt.
  - A TXKM connects a separate bank terminal to the kassa by cable and reconfigures it, "after which the two devices work only together".
  - From **01.01.2023**, online-NKMs that cannot accept cards are banned (PQ-5252, 04.10.2021).
  - [MEDIA, citing PQ-5252] — [buxgalter.uz, 19.04.2022](https://buxgalter.uz/oz/publish/doc/text180631_onlayn-nkm_qanday_qilib_terminal_chekini_chop_etadi); [buxgalter.uz, 20.10.2021](https://buxgalter.uz/oz/publish/doc/text174048_plastik_kartadan_hisob-kitob_qilinganda_onlayn-nkt_chekini_berish_tartibi_uzgartiriladi_bunga_qanday_tayergarlik_kurish_lozim)
  - Under the same rule, once payments run through the kassa "terminal cheki chiqarilmaydi" (no separate terminal slip is issued), and the card payment "is recorded automatically in the online-NKT system".
- **VM 393 (20.07.2026) rewrote Annex 2 of VM 943**: technical requirements for the cash register device. [CONFIRMED] — [lex.uz, VM 393, 1-ilova 14(в)](https://lex.uz/ru/docs/8343102)
  - It must have "барча банк карталари ва QR-кодлар орқали тўловларни тўғридан-тўғри шу қурилма орқали қабул қилиш имконияти" (the ability to accept all bank cards and QR payments directly through this device).
  - It must have "банкларда генерация қилинган ягона QR-кодни шакллантириш ахборот тизими ҳамда электрон тўлов тизимларига мажбурий уланиши" (mandatory connection to the bank single-QR system and e-payment systems).
- **Single fiscal data operator.** VM 943 defines the operator as the Tax Committee's SUE "Yangi texnologiyalar" Scientific-Information Centre. VM 23 (23.01.2026) added the **virtual fiscal module**: a unique number plus an EDS public-key certificate. The yearly operator fee of **1 BHM** applies to "фискал модул ёки виртуал фискал модул". [CONFIRMED] — [lex.uz, VM 943, 2 and 55-band](https://lex.uz/uz/docs/-4603329)
- **Fiscal module fee in 2026:**
  - buxgalter.uz's 2026 payment-details page gives 1 BHM = **412 000 so'm** per year, payable via my.soliq.uz, the Soliq app or a bank. [MEDIA] — [buxgalter.uz, rekvizity 2026](https://buxgalter.uz/uz/publish/doc/text173416_oplata_za_ecp_i_fiskalnyy_modul_rekvizity)
  - REGOS likewise quotes "1 BHM 412 000 so'm". [VENDOR] — [regos.uz](https://regos.uz/uz/product/regos-vcr/info)
- **Paloma365 (10.07.2026)** names "UZKASSA, e-POS, QPos" as "OFD operators". This is a loose use of the term: under VM 943 there is only one operator, and these are kassa/TXKM vendors. [VENDOR, terminology wrong] — [paloma365.uz](https://paloma365.uz/ru/blog/guides/onlajn-kassa-uzbekistan)
- **Registry churn:**
  - In April 2026 the online-NKM "Uzkass-185" and the TXKM "Bestpos" were removed from the state registry for non-compliance. [MEDIA] — [buxgalter.uz, April 2026](https://buxgalter.uz/publish/doc/text214795_kakie_onlayn-kkm_i_cto_isklyucheny_iz_gosreestra_za_nesoblyudenie_trebovaniy)
  - From **01.04.2026** (decree UP-246, 10.12.2025) cash registers must block cash sales for about 28 800 restricted IKPU codes (alcohol, tobacco, fuel and others) and handle social cards. TXKMs had to push firmware by 1 April. The Tax Committee planned to finish conformity checks of all cash registers and virtual kassas by 10.04.2026. [MEDIA] — [buxgalter.uz, 03.04.2026](https://buxgalter.uz/publish/doc/text214409_kak_rabotat_s_onlayn-kkt_po_novym_trebovaniyam_s_1_aprelya_2026_goda)
- **Free state virtual kassa.** The base virtual-kassa software built by the Tax Committee / "Yangi texnologiyalar" is free, with technical service by "Soliq Servis" DUK. In 2020 there were 5 online-NKM types, 5 virtual kassas and 9 TXKMs in the registry. [MEDIA, old, 11.06.2020] — [yuz.uz](https://yuz.uz/uz/news/smart-kassalar-kop-yumushlarni-tarixga-aylantiryapti)
  - A "Virtual Kassa" site exists at vk.soliqservis.uz. Its content could not be read (only the title loaded).

### Inferences
- [INFERENCE] When a choyxona owner says "an ordinary fiscal terminal", it means one of the Android 2-in-1 online-NKMs above. The cashier keys the amount (or picks items) in the vendor's own kassa app on the device. The same device takes the card, and it prints one receipt with QR code and fiscal sign for cash, card or QR.
- [INFERENCE] Classic cash registers (MERKURIY, ORION, SIMURG) with an external Ingenico pin-pad still exist but are legacy. New sales in HoReCa are the Android devices.
- [INFERENCE] The "approved providers" question reduces to two things: (a) is the **device model / virtual-kassa software** in the Tax Committee's online-NKM or virtual-kassa registry, and (b) is its **TXKM** in the TXKM registry. Payment brands (Click, Payme) are a separate "Marketplace" fiscalization route that covers only their own online payments (see the earlier notes).

### Gaps
- The official registry pages (soliq.uz/page/onlayn-nkm and soliq.uz/page/virtual-kassa-reestri) returned **HTTP 503** during research. The current full list of registered models, virtual kassas and TXKMs, and their count, was not obtained.
- "Multikassa", "Kassa.uz", "Paylov", "Uzkassa" and "SmartPOS" were checked:
  - Multikassa: registry status is only vendor-stated.
  - Uzkassa / SmartPOS: this is the same company (Smart Business, ex-UZKASSA).
  - Kassa.uz and Paylov: no fiscal-kassa product was found.
  - The relationship between Rahmat and Multikassa (both appear tied to Multicard) is inferred, not confirmed.
- I could not verify whether banks (Kapitalbank, Hamkorbank and others) hand out **fiscal** 2-in-1 devices themselves, or only plain acquiring terminals. Humo's page lists the TXKM, not the bank, as supplier.

---

## Q2. Market shares and prices (device, fiscal module, monthly fees)

### Takeaway
**No market-share data** for fiscal-kassa vendors in HoReCa was found. The visible scale signals are:
- Smart Ecosystem claims more than 80k companies;
- Uzcard has about 260k POS terminals and Humo about 223k (Central Bank figures quoted in search results, June 2026).

An indicative cost of a 2-in-1 fiscal terminal for a small choyxona in 2026:
- **device**: about 1–3 mln so'm (Rahmat Sunmi P3 990k new; used A-Pay P10, NEXGO and PAX 1.45–1.7 mln; ERA from 3 mln);
- **kassa or service subscription**: about 80–125k so'm per month;
- **operator fee**: 1 BHM (412k so'm) per year for the fiscal module;
- **acquiring**: Uzcard/Humo commission on top (earlier notes say about 0.2%).

### Cited Findings
- Rahmat: SUNMI P3 terminal **990 000 UZS** (promo). Smart Kassa online-NKM **100 000 UZS/month**. Virtual kassa **123 600 UZS/month**. The list of one-time services has no prices: POFM (fiscal-memory) issue, replacement or activation; compensation for a lost POFM; FGF; registration in the tax registry. [VENDOR] — [rhmt.uz/pos/tariffs](https://rhmt.uz/pos/tariffs/)
- MoySklad.uz guide (09.07.2026), vendor list. The guide does not mention Uzkassa or REGOS. [MEDIA/VENDOR] — [moysklad.uz](https://www.moysklad.uz/poleznoe/shkola-torgovli/online-kassa-v-uzbekistane/)

  | Vendor | Price as listed | Note |
  |---|---|---|
  | E-POS | Fiscalization "from about 100 000 sum" | — |
  | Hippo POS | About 1 BRV/year (FM) + about 1 BRV connection + about 0.15 BRV/month per register | — |
  | MobilKassa | From 80 000 sum/month; equipment kit about 890 000 sum if paper receipts are needed | — |
  | Rahmat | Terminal 990 000; online-KKM from 100 000/month; virtual kassa 123 600/month | — |
  | Smart POS, ERA POS, KASSO | On request | — |

- Prom.uz / OLX asking prices. These are listings, not dealer quotes. [MEDIA, unverified] — [prom.uz](https://www.prom.uz/tags/onlayn-kassa/); [olx.uz](https://www.olx.uz/oz/elektronika/tashkent/q-onlayn-kassa/)
  - ERA online cash register from **3 000 000**; Simurg 001 from **1 750 000**.
  - Used A-Pay P10 **1 500 000**; SmartOne **2 500 000**; NEXGO **1 700 000**; PAX A930 **1 450 000**.
  - UZKASSA N5 and similar POS terminals from about **900 000**.
- One service price list, undated and unattributed in the search snippet: "connecting a fiscal cash register 288 400 so'm", "registering one cash register with the tax committee 412 000 so'm". [UNVERIFIED] — search result only, source page not fetched
- Smart Business tariffs: 0.35 / 0.45 / 0.65 BHM, fiscalization included. Period not stated. At 412k per BHM this is about 144k / 185k / 268k so'm. [VENDOR; my arithmetic] — [smart-ecosystem.uz](https://smart-ecosystem.uz/uz/)
- Paloma365 cafe POS from **134 700 so'm/month** (fiscal not included in the claim). [VENDOR] — [paloma365.uz](https://paloma365.uz/ru/blog/guides/onlajn-kassa-uzbekistan)
- Network scale: Central Bank data for June 2026 as quoted in search results give UZCARD about 260 000 POS terminals and HUMO 222 784, with cross-acceptance still being tested. [MEDIA, secondary] — search summary citing CBU; not fetched
- Historical scale: about 34 000 online-NKM and virtual kassas in 2020, of which more than 40% were not being used. [MEDIA, old] — [kun.uz, 05.08.2020](https://kun.uz/news/2020/08/05/onlayn-kassa-mashinalarning-40-foizdan-kopi-ishlatilmayapti-davlat-soliq-qomitasi)
- UNDP (May 2026): only **36%** of catering firms use software such as R-Keeper, and of those **73%** say it is integrated with the tax base. (Already in earlier notes; repeated only as the one HoReCa adoption figure.) — [UNDP](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf)

### Inferences
- [INFERENCE] The total cost of ownership of the 2-in-1 route for a choyxona is about **1–3 mln so'm upfront + 1.2–1.5 mln so'm/year subscription + 412k/year FM**. Any Rizqim fiscal path must beat or bundle this.
- [INFERENCE] With 64% of catering outside any software (UNDP), the dominant "competitor" in choyxonas is the vendor kassa app on the 2-in-1 device plus a paper or Excel bill. iiko or R-Keeper are not the main alternative there.

### Gaps
- No vendor market shares, and no count of devices in catering, were found for 2025–2026.
- No official dealer price was found for UZKASSA N5, A-Pay P10, Sunmi P2 Pro or SmartOne.
- No current BHM figure was found newer than 412 000 (an earlier report said "~412–440k").

---

## Q3. How can third-party software issue fiscal receipts? APIs and SDKs, receipt-type support, and the registry requirement

### Takeaway
There are four technical paths:

**(a) Talk to a physical fiscal module yourself, i.e. be the kassa.**
- The OFD-associated developer docs show a local REST service, **FiscalDriveService**, at `127.0.0.1:3449` for Windows 11 and Linux, with a USB or smart-card FM version 0400.
- A separate **Android** developer guide shows how a KKM app on Android talks to FM 0400 over ISO7816.
- The FM receipt JSON natively supports **sale, advance (avans), credit**, and **refund** operations, with a payment form of cash, card, QR or mixed. It also carries SPIC (MXIK), PackageCode, marking codes and commission (komitent) info.
- The software doing this must itself be a **registered virtual kassa** (VM 943 Annex 4).

**(b) Call a registered virtual-kassa provider's API.**
- E-POS cloud REST `POST /v1/receipts`, documented as sale and refund only.
- Multikassa: sale, refund, advance and credit receipts plus Z-report, per a 2024 sponsored article.
- Rahmat: "open API", no public spec.
- REGOS VCR: API, from earlier notes.

**(c) App-to-app on the 2-in-1 Android device.** Humo's page credits Sunmi P2 Pro and NEXGO N82 with an "open API". No public SDK or intent spec was found.

**(d) Payme/Click Marketplace fiscalization.** For online payments only.

Path (a) is the only one with a **public, detailed spec that covers bo'nak and kredit**. For (b) and (c) the docs sit behind a login or do not exist publicly.

### Cited Findings
- **FiscalDriveService ("РУКОВОДСТВО ПРОГРАММИСТА ВИРТУАЛЬНОЙ КАССЫ ДЛЯ ИНТЕГРАЦИИ С FiscalDriveService-v10")**. [CONFIRMED as published developer doc; publisher identity not verified] — [GitHub qo0p/fiscal-drive-service](https://github.com/qo0p/fiscal-drive-service)
  - It replaces the older FiscalDriveAPI, which cannot work with FM 0400.
  - It runs as a Windows service or on Linux (x64 and arm, via pcscd). It exposes REST plus Swagger on port 3449, with optional mutual TLS using a client PFX.
  - It stores signed or encrypted receipts in a local DB and sends them to OFD servers listed in `config.ini`.
  - It has a "test receipts server" mode for development with a test FM or FM emulator.
  - Receipt JSON fields (verbatim):

    | Field | Values |
    |---|---|
    | `Type` | "0 - Обычный (покупка), 1 - Аванс, 2 - Кредит" |
    | `Operation` | "0 - Продажа, 1 - Возврат (отзыв)" |
    | `PaymentType` | "1 - Наличными, 2 - Безналичными, 3 - По QR-коду, 4 - Смешанная" |
    | `ReceivedCash` / `ReceivedCard` | Amounts in tiyin |
    | `Items[]` | `SPIC` (IKPU), `PackageCode`, `Labels` (up to 300 marking codes), `OwnerType`, `CommissionInfo`, VAT |
    | `ExtraInfo` | `CardType` (1 corporate / 2 personal / 3 social), `CardNumber`, `CashedOutFromCard` |
    | `RefundInfo` | `TerminalID`, `ReceiptSeq`, `DateTime`, `FiscalSign` of the original receipt |
    | `Location` | — |

  - Validation rule: ReceivedCard + ReceivedCash − Σ(Price − Discount − Other) ≤ 10000 tiyin.
  - Idempotent `GetTXID` / `RegisterTXID` flow for when the FM transmission fails.
  - Z-report open and close; totals per sale and refund for cash, card and VAT.
- **Android FM integration guide ("ИНСТРУКЦИЯ РАЗРАБОТЧИКА КОНТРОЛЬНО-КАССОВОЙ МАШИНЫ НА БАЗЕ ОС ANDROID ДЛЯ ИНТЕГРАЦИИ ФИСКАЛЬНОГО МОДУЛЯ ВЕРСИИ 0400")**. [CONFIRMED as published doc] — [GitHub qo0p/acrsim-android](https://github.com/qo0p/acrsim-android)
  - FM 0400 is a JavaCard smart card or USB token. Communication uses ISO7816 APDU and TLV.
  - The guide covers ZReport, Receipt, FullReceipt, Ack and Sync files, plus a sample Android app.
  - FM 0400 refuses future dates more than 2 days ahead and requires server sync.
  - This is how Android online-NKM makers build their kassa apps.
- **E-POS API** ("alpha" site). [VENDOR] — [epos.uz/en/products/api](https://epos.uz/en/products/api)
  - `POST https://api.epos.uz/v1/receipts` with a Bearer token, OAuth2, IP allowlist and request signing.
  - The body holds items, a payment object (e.g. `"method":"qr","provider":"payme"`) and `cashier_id`.
  - The response returns status, fiscal sign and a verification link. Refund is "by the original receipt id, partial or full". Catalog push is supported.
  - Full reference is at docs.epos.uz (login).
  - **Advance and credit receipts and MXIK are not mentioned on the public page.**
  - Integrations listed elsewhere: MoySklad, 1C, Bitrix24, **Billz**, Click, Payme, Uzum. [VENDOR] — [moysklad.uz](https://www.moysklad.uz/poleznoe/shkola-torgovli/online-kassa-v-uzbekistane/)
  - The F-Kassa app for MoySklad sends receipts "to the Uzbek OFD via the EPOS module". [VENDOR] — search result on moysklad.ru F-Kassa app
- **Multikassa** (sponsored article, 23.04.2024): "зарегистрированные в государственных реестрах виртуальных касс и онлайн-ККМ"; supports "продажа товаров", "возврат товаров", "**авансовый чек**", "**кредитный чек**", "открытие и закрытие смены (Z-отчет)". It integrates with MoySklad. No API spec is public. [VENDOR, advertising] — [spot.uz, 23.04.2024](https://www.spot.uz/ru/2024/04/23/multikassa-moysklad/)
  - The Poster integration page lists only sale, refund and shift open/close. [VENDOR] — [joinposter.com](https://joinposter.com/en/applications/multikassa)
- **Rahmat**:
  - Its FAQ says "Благодаря открытому API мы можем провести интеграцию с популярными POS системами" (thanks to an open API we can integrate with popular POS systems).
  - Its integration page lists SetRetail, **iiko**, **R_keeper**, MoySklad, 1C and Artix, with YouTube videos and no API docs.
  - [VENDOR] — [rhmt.uz/pos/tariffs](https://rhmt.uz/pos/tariffs/); [rhmt.uz/pos/integration](https://rhmt.uz/pos/integration/)
- **Package registries**: no public SDK for Multikassa, Uzkassa or the OFD was found on npm or Packagist. The only Multikassa npm packages are UI widgets. An npm package "FiscalIntegrationUz" was mentioned in a search result but not verified. — [npm search, 24.10.2026](https://registry.npmjs.org/-/v1/search?text=multikassa)
- **Registry requirement (legal).** VM 943 Annex 4 (process detailed in earlier notes):
  - an OFD integration conclusion within 10 working days;
  - a Cybersecurity Centre conclusion within 15 working days;
  - a Tax Committee decision.

  In addition, the TXKM registry exclusion grounds now include unauthorised changes to kassa functionality. [CONFIRMED] — [lex.uz, VM 943](https://lex.uz/uz/docs/-4603329)
- **No separate "integrated software registry"**:
  - I found no separate registry called "integratsiya qilingan dasturlar reyestri".
  - The Tax Committee's position (21.06.2024) is that catering and retail automation software "должны быть обязательно интегрированы с информационными системами налоговых органов" and entered in the State Register. The same notice says "запрещается одновременное использование наряду с онлайн-ККТ других видов систем учета фискальных данных" (alongside an online cash register, using other kinds of fiscal-data accounting systems at the same time is prohibited).
  - In practice "integrated" = registered as a virtual kassa (earlier notes: [gov.uz/14530](https://gov.uz/ru/soliq/news/view/14530)). [CONFIRMED position, older] — [gov.uz, 21.06.2024](https://gov.uz/ru/soliq/news/view/14525)

### Inferences
- [INFERENCE] For Rizqim's **Windows** cashier app, the most controllable path to full bo'nak/kredit/refund support is **FiscalDriveService + a USB FM**, with Rizqim registered as a virtual kassa (or partnered with a TXKM). The receipt model maps 1:1 to the P3-11 `FiscalGateway` design: Type 0/1/2, Operation 0/1, PaymentType 1–4, SPIC, PackageCode, Labels, CommissionInfo.
- [INFERENCE] For the **Android** cashier app, the same is possible in principle: FM 0400 over USB-OTG/ISO7816, following acrsim-android. But it turns the Android app into a KKM needing its own registry entry and Cybersecurity Centre review. It is heavier than Windows.
- [INFERENCE] For the **Laravel cloud**, the clean options are a cloud virtual-kassa API (E-POS-style) or the new **virtual fiscal module** (an EDS certificate, VM 23 of 23.01.2026). The OFD has not published whether a restaurant virtual kassa (as opposed to a marketplace) may use a virtual FM.
- [INFERENCE] Bo'nak and kredit support from providers is only proven publicly for the FM protocol itself and claimed by Multikassa. E-POS and Rahmat must be asked in writing (earlier checklist item T7).

### Gaps
- No public API docs from Multikassa, Rahmat, Smart Business/UZKASSA, SmartOne, Paymob24 or NEXGO; no Android intent or AIDL spec for third-party apps on Sunmi or NEXGO fiscal devices.
- No public OFD documentation for the virtual-fiscal-module (cloud) protocol for non-marketplace kassas.
- docs.epos.uz requires a login, so its advance and credit support is unknown.
- FiscalDriveService's publisher is not stated on GitHub. It is consistent with OFD terminology and versions, but authorship is unverified.

---

## Q4. Is "separate POS + fiscal terminal, cashier re-types the total" legal for catering in late 2026? How do competitors fiscalize?

### Takeaway
**Not for catering after 22.10.2026.** VM 393 (20.07.2026, published 21.07.2026) rewrote VM 943 Annex 1 clause 4. It is now prohibited:
- to use any "secondary" fiscal-data accounting system alongside a registered online-NKM or virtual kassa; and
- for **catering enterprises**, to use order-accounting software (buyurtmalar hisobini yuritish dasturiy mahsulotlari) **not integrated with tax-authority information systems**.

The effective-date clause of VM 393 puts exactly these paragraphs (14-band "а" paragraphs 4–5, and the TXKM rules in "г" paragraphs 2–6) into force **three months after official publication, i.e. about 21–22.10.2026**. So the rule is **in force now** (today 24.10.2026). This resolves the earlier "[ANIQ EMAS] 22.10 or 21.07" question.

A Rizqim POS used only for orders and bills, with the total re-typed into a separate 2-in-1 fiscal terminal, is the exact pattern banned. The receipt itself is legal, but the order software is not. Exposure: Tax Code art. 227¹, 2% of last-quarter net revenue, 20% on repeat.

The obvious "fix" of plugging the POS into the vendor's registered device to print the QR receipt is also attacked from the TXKM side. New VM 943 Annex 3 clause 25 makes it a ground for **striking the TXKM from the registry** if a secondary system, registered or not, is "integrated into a registered online-NKT for the purpose of printing QR-code receipts". Clause 16¹ forbids TXKMs to "enable" use of non-integrated order software.

The only unambiguous path is for the software that keeps the orders to **be (or be part of) a registered virtual kassa / online-NKM integrated with the OFD**.

### Cited Findings
- **VM 393, Annex 1, item 14(а)**: new first paragraph of VM 943 Annex 1 clause 4, verbatim. [CONFIRMED] — [lex.uz, VM 393](https://lex.uz/ru/docs/8343102)
  > «4. Ўзбекистон Республикаси Президентининг 2019 йил 6 сентябрдаги ПФ-5813-сон Фармони билан тасдиқланган графикда кўрсатилган муддатлар тугагандан кейин савдо (хизмат кўрсатиш) шохобчасида давлат реестрига киритилган онлайн-НКМ ва (ёки) виртуал касса билан бир вақтда бошқа турдаги (иккиламчи) фискал маълумотлар ҳисобини юритиш тизимларидан, шунингдек, умумий овқатланиш корхоналари томонидан солиқ органларининг ахборот тизимлари билан интеграция қилинмаган автоматлаштирилган ҳисобга олиш ўлчов воситалари (ахборот тизимлари, дастурий маҳсулотлари) ёки буюртмалар ҳисобини юритиш дастурий маҳсулотларидан фойдаланиш тақиқланади. Фармоннинг 4-банди иккинчи хатбошисида назарда тутилган ҳоллар бундан мустасно»

  In English: after the deadlines in the PF-5813 schedule, a trade or service outlet may not use other (secondary) fiscal-data accounting systems at the same time as a registered online-NKM and/or virtual kassa. Catering enterprises may also not use automated accounting tools (information systems, software) or order-accounting software that is not integrated with the tax authorities' information systems. The cases in PF-5813 clause 4, paragraph 2 are excepted.
- **VM 393, item 14(г)**: VM 943 Annex 3, the TXKM regulation. [CONFIRMED] — [lex.uz, VM 393](https://lex.uz/ru/docs/8343102)
  - New clause 16¹:
    > «ТХКМ ёки тадбиркорлик субъектлари томонидан солиқ органларининг ахборот тизимлари билан интеграция қилинмаган автоматлаштирилган ҳисобга олиш ўлчов воситалари … ёки буюртмалар ҳисобини юритиш дастурий маҳсулотларидан фойдаланишга имконият яратиш тақиқланади»

    (TXKMs or business entities may not make it possible to use automated accounting tools … or order-accounting software that is not integrated with the tax authorities' information systems.)
  - New TXKM-exclusion grounds in clause 25:
    > «Давлат реестрига киритилган онлайн-НКТларга бошқа турдаги (иккиламчи) Давлат реестрига киритилмаган ёки киритилган ахборот тизимлари (автоматлаштирилган ҳисобга олиш ўлчов воситалари, дастурий маҳсулотлари, буюртмалар ҳисобини юритиш) QR-кодли чек чоп этиш мақсадида интеграция қилинганлиги аниқланганда; Давлат реестрига киритилган онлайн-НКТлардан QR-кодсиз чек чоп этиш имконияти яратилганлиги аниқланганда»

    (A TXKM is removed when it is found that a secondary information system, whether or not it is itself in the State Register (accounting tools, software, order-accounting software), has been integrated into a registered online-NKT for the purpose of printing QR-code receipts; or when a registered online-NKT has been made able to print receipts without a QR code.)
- **VM 393 effective-date clause**, verbatim, from the lex.uz text. [CONFIRMED] — [lex.uz, VM 393](https://lex.uz/ru/docs/8343102)
  > «…14-бандининг «а» кичик банди тўртинчи ва бешинчи хатбошилари, «г» кичик банди иккинчи — олтинчи хатбошилари, «д» кичик банди иккинчи — бешинчи хатбошилари … қарор расмий эълон қилинган кундан эътиборан уч ой ўтгач кучга киради.»

  (Paragraphs 4–5 of sub-item "а" of item 14, paragraphs 2–6 of sub-item "г", paragraphs 2–5 of sub-item "д" … enter into force three months after the day the resolution is officially published.)

  Counting paragraphs in 14(а):
  1. "а) 1-иловада:"
  2. instruction to add the QR definition
  3. the QR definition
  4. "4-банднинг биринчи хатбошиси … баён этилсин:"
  5. the new clause 4 text

  So paragraphs 4–5 are exactly the catering ban. In 14(г), paragraphs 2–6 are clause 16¹ and the two new clause-25 paragraphs. Publication: "Қонунчилик маълумотлари миллий базаси, 21.07.2026 й., 09/26/393/0764-сон". Delayed entry into force is therefore ≈ **21/22.10.2026**.
  - The device card/QR requirement (14(в)) is **not** in the delayed list, so it has applied since **21.07.2026**. [CONFIRMED reading; date arithmetic INFERENCE]
- The PF-5813 (06.09.2019) schedule deadlines for moving businesses to online kassas ran by turnover band: from 01.08.2020 (over 5 bn), 01.01.2021 (over 100 mln), 01.01.2022 (all others). So the "after the schedule deadlines" condition is already met for every choyxona. [MEDIA, old] — [yuz.uz, 11.06.2020](https://yuz.uz/uz/news/smart-kassalar-kop-yumushlarni-tarixga-aylantiryapti)
- Fines (from earlier reports, Tax Code): 227¹ is 2% of last-quarter net revenue (20% on repeat) for breaking the fiscal-sign, tax-integration or marking rules. 221 is 5 mln so'm for not issuing a receipt. — [lex.uz, Soliq kodeksi](https://lex.uz/docs/-4674902)
- Enforcement history: in 2023–2024 restaurants were fined under 227¹ on the ground that "R-Keeper, iiko, Jowi are not integrated". buxgalter.uz argued the legal basis was weak. VM 393 now writes the rule into VM 943 explicitly. [MEDIA] — [buxgalter.uz, 06.12.2023](https://buxgalter.uz/publish/doc/text194910_pismo_nalogovyh_organov_o_shtrafah_za_to_chto_net_integracii_programm_roznichnoy_torgovli_i_obshchepita_s_sistemami_nalogovyh_organov_v_uzbekistane)
- **No news coverage** of the 22.10.2026 entry into force was found on spot.uz, kun.uz, gazeta.uz or buxgalter.uz in this session's searches (Uzbek and Russian queries). [Absence noted]
- A side risk on re-typed totals: an August 2026 report noted that on some electronic receipts sent to the OFD, the item details disappear and only the payment and VAT amounts remain. It warned this distorts turnover and VAT reports if not fixed. [MEDIA, search summary only, not fetched] — buxgalter.uz (via search)
- Tax Committee, 11.08.2026: customers can now view electronic receipts in a simplified form (name, amount, total). Full data (MXIK, unit, VAT, discounts) is still stored. Display only; no change to what kassas must record. [MEDIA] — [spot.uz, 11.08.2026](https://www.spot.uz/oz/2026/08/11/fiscal-receipt)
- **How competitors fiscalize.** Mostly vendor claims; details for Jowi and BILLZ were not found in 2025–2026 sources.
  - **iiko, R-Keeper**: through the Rahmat (Multicard) virtual kassa, an "integration" listed on rhmt.uz with setup videos. [VENDOR] — [rhmt.uz/pos/integration](https://rhmt.uz/pos/integration/)
  - **Poster**: the Multikassa app ($28 setup + $8/month + $5/month integration); sale, refund and shift open/close. Humo also lists Sunmi P2 Pro (MICROS24) as integrating with Poster. [VENDOR] — [joinposter.com](https://joinposter.com/en/applications/multikassa); [humocard.uz](https://humocard.uz/uz/business/onlayn-nkm/)
  - **MoySklad**: Multikassa (sale, refund, advance, credit, Z) and F-Kassa through E-POS. [VENDOR] — [spot.uz, 2024](https://www.spot.uz/ru/2024/04/23/multikassa-moysklad/)
  - **BILLZ**: listed among E-POS integrations. [VENDOR] — [moysklad.uz](https://www.moysklad.uz/poleznoe/shkola-torgovli/online-kassa-v-uzbekistane/)
  - **Jowi**: no current source. In 2023 its founder said integration was "technically not yet possible" (earlier notes).
  - **Local POS (Biznex and others)**: FM + FiscalDriveService/QPOS (earlier notes, docs.biznex.uz).

### Inferences
- [INFERENCE] **Legal status of "re-type into a separate terminal" (as of 24.10.2026):**
  - The 2-in-1 terminal itself is compliant: a registered online-NKM that takes cards and prints QR receipts.
  - A catering business that also runs Rizqim (or any order-taking software) that is not "integrated with tax-authority information systems" breaches the new clause 4. Every choyxona using Rizqim this way is exposed to the 227¹ fine of 2% of quarterly revenue. **Rizqim cannot be marketed to catering as "keep your fiscal terminal, re-type the total".**
- [INFERENCE] **Ambiguity remains on what "integrated" means for the B-path** (Rizqim → provider API → provider's registered virtual kassa):
  - Clause 25 targets integration into a registered **online-NKT** "for printing QR receipts". Read literally, that is exactly what iiko+Rahmat or Poster+Multikassa do. Whether "NKT" there covers virtual kassas, and whether a provider-API integration counts as "integrated with tax systems", is not defined.
  - The Tax Committee's 2023–2024 line was "register as a virtual kassa".
  - The safest reading is: **Rizqim (the fiscal part of it) must be a registered virtual kassa, or a registered component of one.**
  - The B-path should be used only with a written confirmation from the provider and the Tax Committee or OFD. This repeats the earlier recommendation, now with the effective date confirmed.
- [INFERENCE] **Integration path ranking for P3-32:**

  | Rank | Path | When to use | Why |
  |---|---|---|---|
  | 1 | Apply to register Rizqim's **fiscal gateway as a virtual kassa**, with a TXKM partner | Long-term, compliant | Windows: FiscalDriveService + USB FM. Android: FM 0400 per acrsim-android. Cloud: virtual FM if the OFD allows it. Full bo'nak, kredit, refund, MXIK and marking per the FM JSON. |
  | 2 | Meanwhile, a **registered cloud virtual-kassa API** (E-POS, Multikassa or Rahmat), chosen on a written answer to two questions | Interim | (i) Is bo'nak/kredit/refund/MXIK/marking supported in the API? (ii) Does "Rizqim via your API" count as "integrated" under VM 943 Annex 1 clause 4 and Annex 3 clauses 16¹ and 25, as of 22.10.2026? |
  | 3 | Do **not** ship "re-type into the 2-in-1 terminal" as a catering mode | Never as a product mode | At most a temporary, owner-acknowledged "non-fiscal bill" mode with a warning, since the owner carries the 227¹ risk. |

- [INFERENCE] The 2-in-1 terminal can stay as the **card acquirer**. Its kassa app cannot be the fiscal printer for Rizqim's orders without hitting clause 25. A Rizqim virtual kassa must itself accept cards and QR "directly" (VM 943 Annex 2 as amended), so the card terminal has to be linked to the Rizqim kassa as well. Options: bank acquiring SDK, a cable-linked terminal set up by the TXKM, or dynamic UzQR.

### Gaps
- No official Tax Committee or OFD explanation yet of how VM 393's catering ban will be enforced, or of what counts as "integration" (provider API vs own registry entry). There was also no press coverage of the 22.10.2026 start.
- Whether "онлайн-НКТ" in Annex 3 clause 25 includes virtual kassas was not verified against the VM 943 definitions; the term is not defined in the extracted text.
- The exception in "Фармоннинг 4-банди иккинчи хатбошиси" (PF-5813 clause 4, paragraph 2) was not read. It may exempt remote settlements or small taxpayers and could matter for rural choyxonas.
- Jowi and BILLZ fiscalization mechanics for 2025–2026 were not found. iiko and R-Keeper rely on vendor pages only.
