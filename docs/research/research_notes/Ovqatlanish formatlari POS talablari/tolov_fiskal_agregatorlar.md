# Payments, fiscal compliance and delivery aggregators for food businesses in Uzbekistan (input to POS design)

Research date: 21 October 2026. Primary legal texts were read directly from lex.uz. PKM 393 and PKM 943 were read in the official Uzbek (Cyrillic) text, the Tax Code in Uzbek, and the catering rules in the lex.uz unofficial Russian translation. Quotes from Uzbek are my own English translations. "PKM"/"VM" means a Cabinet of Ministers resolution, "UP"/"PF" a Presidential decree, and "BRV" the base calculation value.

---

## Q1. What must a catering business do today to be fiscally compliant, and what does VM/PKM 393 require of order-taking software? Effective dates?

### Takeaway
"VM 393" is **Cabinet of Ministers Resolution No. 393 of 20.07.2026**, published 21.07.2026. It is an omnibus act that amends PKM 943 (online cash registers and virtual cash registers), the trade and catering rules (PKM 75), the market rules (PKM 253) and others. For catering it adds a **ban on catering enterprises using automated accounting software or *order-management software* (буюртмалар ҳисобини юритиш дастурий маҳсулотлари) that is not integrated with the Tax Committee's information systems**. It also makes the cash-register service centres (TSCs) liable if they enable such software. Per lex.uz annotations and the act's own effective-date note, these provisions take effect **three months after official publication, i.e. 22 October 2026, the day after this research date**. Separately, the requirement to accept QR payments and to issue a fiscal receipt from an online cash register or virtual cash register for every payment (cash, card, QR) already applies. The unified bank QR (UzQR) has been mandatory for legal entities since 1 July 2026.

### Cited Findings

**What PKM 393 says (primary text)**
- Full title: "On amendments and additions to certain Government resolutions, and on recognising some of them as invalid", No. 393 of 20.07.2026, signed by PM A. Aripov. It was issued "to harmonise legislation" under Art. 18 of the Law on Normative Legal Acts. — [lex.uz, PKM 393 (official Uzbek text)](https://lex.uz/ru/docs/8343102)
- **New clause 4, first paragraph, of the Regulation on online cash registers and virtual cash registers (Annex 1 to PKM 943)**, in my translation: "After the deadlines in the schedule approved by PF-5813 of 6 Sept 2019, it is prohibited at a trade (service) outlet to use, alongside a state-registered online cash register and/or virtual cash register, any other (secondary) fiscal-data accounting systems, **and also for catering enterprises to use automated accounting measuring tools (information systems, software products) or order-accounting software products that are not integrated with the information systems of the tax authorities**. Cases provided for in para 2 of clause 4 of the Decree are excepted." — [lex.uz, PKM 393, Annex 1 item 14(a)](https://lex.uz/ru/docs/8343102); the lex.uz consolidated PKM 943 shows clause 4 with the note "see changes entering into force on 22 October 2026" — [lex.uz, PKM 943 (Uzbek)](https://lex.uz/ru/docs/4603329)
- The existing second paragraph of clause 4 (in force since the PKM 255 amendment of 12.05.2022) already said: "Automated accounting measuring tools (information systems, software products) used by **retail and catering** businesses must be mandatorily integrated with the tax authorities' information systems and entered into the State Register." When a business registers its own automated accounting complex as a *virtual cash register*, the deposit/reserve requirement does not apply. The third paragraph says: "Responsibility for forming the purchase receipt in full and giving it to the buyer lies with the business." — [lex.uz, PKM 943 Annex 1 cl. 4](https://lex.uz/ru/docs/4603329)
- **New clause 16-1 of Annex 3 to PKM 943 (TSC registry rules)**: "TSCs or businesses are prohibited from enabling the use of automated accounting tools … or order-accounting software products not integrated with the tax authorities' information systems." Clause 25 adds two new grounds for **removing a TSC from the State Register**. The first is where it is found that secondary systems, whether registered or not (automated accounting tools, software products, order accounting), have been integrated with registered online cash registers *for the purpose of printing QR-code receipts*. The second is where a registered online cash register has been enabled to print receipts without a QR code. — [lex.uz, PKM 393, item 14(г)](https://lex.uz/ru/docs/8343102)
- Annex 4, cl. 19 of PKM 943 gains a new ground for excluding a model from the register: "when the technical capabilities of online cash registers and virtual cash registers do not meet the technical requirements". — [lex.uz, PKM 393, item 14(д)](https://lex.uz/ru/docs/8343102)
- **Effective dates (note to Annex 1 of PKM 393)** cover item 14(a) paras 4–5, item 14(г) paras 2–6, item 14(д) paras 2–5 and others. These are the clause 4 software ban, the payment-form field, the TSC ban and the register exclusion. They "enter into force three months after official publication". Publication was 21.07.2026 (QMMB 09/26/393/0764), and lex.uz lists a 22.10.2026 edition. Other parts of the act have their own dates: 1 Aug, 1 Oct and 1 Nov 2026. Everything else took effect on 21.07.2026. — [lex.uz, PKM 393](https://lex.uz/ru/docs/8343102); lex.uz annotation on PKM 943 cl. 7 says "date of entry into force — 22 October 2026" — [lex.uz, PKM 943](https://lex.uz/ru/docs/4603329)
- PKM 393 also redefines the receipt field "payment form" as **"cash, bank cards, QR-code payment or mixed"** (Annex 1 cl. 7 of PKM 943, effective 22.10.2026). It adds a definition of "QR-code payment". — [lex.uz, PKM 943 cl. 2 and 7](https://lex.uz/ru/docs/4603329)
- PKM 393 rewrote Annex 1a cl. 2 of PKM 943 (in force since 21.07.2026): payment data for payments made "at outlets, including via the unified QR code generated by banks" are sent to the operator, and "**for payments via the unified QR code a receipt from the cash register equipment must also be issued mandatorily**". — [lex.uz, PKM 943 Annex 1a](https://lex.uz/ru/docs/4603329)
- The technical requirements for online cash registers (Annex 2 cl. 3) and virtual cash registers (cl. 6) were amended by PKM 393 (in force since 21.07.2026). Both must have "mandatory connection to the information system generating the unified bank QR code and to electronic payment systems", and "the ability to accept payments by all bank cards and QR codes directly through this device". — [lex.uz, PKM 943 Annex 2](https://lex.uz/ru/docs/4603329)
- Catering rules (PKM 75, Annex 2), as amended by PKM 393, add a new liability ground in cl. 25. It covers "individual entrepreneurs and self-employed not providing the possibility to use the special QR code generated on digital platforms, and legal entities the unified QR code generated in banks, and/or not accepting payments via these QR codes". — [lex.uz, PKM 75 (catering rules, ru)](https://lex.uz/ru/docs/243233)
- Commentary describes PKM 393 as aimed at "further digitalisation of settlements", expanding cashless options and strengthening seller liability. It also says the article covering it does not describe catering-specific changes; my reading of the primary text above contradicts that. — [buxgalter.uz, 2026](https://buxgalter.uz/publish/doc/text216324_obnovleny_pravila_torgovli_kak_ne_narushit_novyy_limit_nalichnyh_raschetov)

**Baseline catering obligations already in force**
- Catering rules cl. 13: cash settlements are made using cash registers, or online cash registers / virtual cash registers where legislation requires. Businesses "providing catering services" must use online cash registers or virtual cash registers equipped with 2D scanners or data-collection terminals to read barcodes and digital-marking codes (PKM 595 of 22.09.2021). — [lex.uz, PKM 75 Annex 2](https://lex.uz/ru/docs/243233)
- Catering rules cl. 14: in self-service catering a cash receipt is issued. Where waiters serve, payment is made "on the bill issued by the waiter or the receipt of the cash register, online cash register or virtual cash register" (PKM 943 wording). "All catering outlets must have a payment terminal for accepting bank card payments" (PKM 116 of 2010). — [lex.uz, PKM 75 Annex 2](https://lex.uz/ru/docs/243233)
- Catering rules cl. 8: catering businesses must display their full name, taxpayer ID (INN/STIR) and "a special QR code" that allows scanning this information. — [lex.uz, PKM 75 Annex 2](https://lex.uz/ru/docs/243233)
- PKM 943 Annex 1 cl. 6 (as amended by PKM 23 of 23.01.2026): a business that uses an online cash register or virtual cash register must give buyers a fiscal receipt from it for payments accepted via a payment terminal (together with the terminal slip) **and** for payments via electronic payment systems for goods and services bought on site. Registered online cash registers and virtual cash registers must be connected to bank-card acceptance systems and use a 2D scanner. — [lex.uz, PKM 943](https://lex.uz/ru/docs/4603329)
- Receipt contents (PKM 943 Annex 1 cl. 7) include bank card type (corporate/personal/social), receipt number, cash register serial number, name, STIR/PINFL, address, item name, marking code, IKPU/catalogue unit, barcode, quantity, price, VAT rate and amount, payment form, totals, date and time. They also include **payment type: sale, return, advance, instalment, credit**. "Receipts of advance, instalment and credit types do not carry a QR code and fiscal sign." Product and service codes must match the national catalogue (tasnif) or GS1. Receipt paper must be at least 55 mm wide. — [lex.uz, PKM 943](https://lex.uz/ru/docs/4603329)
- Who must use a cash register: businesses accepting cash and plastic cards must use an online cash register or virtual cash register. Self-employed persons may use one voluntarily. Only models in the State Register are allowed. — [buxgalter.uz, 14.05.2024](https://buxgalter.uz/publish/doc/text199847_kto_i_kakie_modeli_onlayn-kkt_obyazan_ispolzovat_v_uzbekistane)
- The Tax Committee's position (2020) is that a receipt with a QR code must be issued regardless of whether the customer pays cash or card. — [yuz.uz, 09.09.2020](https://yuz.uz/ru/news/chek-onlayn-kkm-doljen-vdavatsya-nezavisimo-ot-form-oplat)
- **From 1 April 2026 (UP-246 of 10.12.2025)** cash registers must support four functions. They must block cash sales for listed IKPU codes (about 28,800 codes, incl. about 22,500 alcohol and tobacco), block prescription drugs without an e-prescription QR, and block expired goods. They must also accept social cards (recording card type, number and transaction ID). Old firmware was blocked on 31.03.2026. — [buxgalter.uz, 03.04.2026](https://buxgalter.uz/publish/doc/text214409_kak_rabotat_s_onlayn-kkt_po_novym_trebovaniyam_s_1_aprelya_2026_goda)
- **Exception for catering:** under UP-100 of 26.05.2026, from 1 June 2026 hotels and catering may again accept cash for alcohol and tobacco. Marking codes of alcohol bought by them count as sold for final consumption. — [buxgalter.uz, 01.06.2026](https://buxgalter.uz/publish/doc/text215370_chto_menyaet_v_nalogovom_administrirovanii_malyh_predpriyatiy_ukaz_up-100)
- UP-100 also introduces a voluntary simplified VAT regime at 6% for catering, trade and services (1 June 2026 to 1 Jan 2030). The Tax Committee must launch separate tracking of 6% VAT in e-invoices and **online cash register receipts** within one month. — [buxgalter.uz, 01.06.2026](https://buxgalter.uz/publish/doc/text215370_chto_menyaet_v_nalogovom_administrirovanii_malyh_predpriyatiy_ukaz_up-100); PwC also notes the 6% optional regime from 1 June 2026 — [PwC Tax Summaries](https://taxsummaries.pwc.com/republic-of-uzbekistan/corporate/other-taxes)
- **QR acceptance:** sole proprietors and self-employed had to accept payment via a special QR from payment-organisation platforms from 1 Jan 2026. Legal entities must have the unified bank QR (UzQR) from 1 July 2026. — [buxgalter.uz, 2026](https://buxgalter.uz/publish/doc/text216324_obnovleny_pravila_torgovli_kak_ne_narushit_novyy_limit_nalichnyh_raschetov); "from 1 July 2026 all legal entities in retail and services must enable payment via the unified QR … non-use is a trade-rules violation" — [buxgalter.uz, 30.06.2026](https://buxgalter.uz/publish/doc/text215897_edinyy_qr-kod_obyazatelen_dlya_yurlic_s_1_iyulya_-_mojno_li_ispolzovat_drugie_qr-kody)
- **Catering VAT refund (cashback)** rules over time:
  - 2025: catering got back 40% of VAT paid if cashless revenue was 60% or more, and 20% otherwise. The refund was checked automatically against e-invoices, online cash register receipts and marketplace receipts. — [spot.uz, 06.05.2026](https://www.spot.uz/ru/2026/05/06/vat-refund); [buxgalter.uz (oz)](https://buxgalter.uz/oz/publish/doc/text216203_umumiy_ovqatlanish_korhonalari_qqs_buyicha_keshbekning_bir_qismini_qaytarish_tugrisida_hatlar_olmoqda_soliq_organlariga_qanday_javob_berish_kerak)
  - From 1 June 2026 (UP-100): 40% regardless of cashless share. — [buxgalter.uz](https://buxgalter.uz/publish/doc/text215370_chto_menyaet_v_nalogovom_administrirovanii_malyh_predpriyatiy_ukaz_up-100)
  - August 2026: the Tax Committee started demanding back part of earlier refunds from catering businesses. — [spot.uz (oz), 15.08.2026](https://www.spot.uz/oz/2026/08/15/vat-refund/)

**Penalties (Tax Code, primary text)**
- Tax Code Art. 221, part 1, as amended by ZRU-1108 of 25.12.2025 and in force from 1 Jan 2026, carries a fine of **5,000,000 UZS**. It covers:
  - trading or providing services without cash registers, payment terminals, electronic payment systems or the special QR codes where these are mandatory;
  - selling without issuing the mandatory receipt or equivalent document;
  - refusing payment via terminals, electronic payment systems or special QR codes.
  — [lex.uz, Tax Code Art. 221](https://lex.uz/ru/docs/4674902)
- Art. 221, other parts:
  - part 2: unregistered cash register, **7,000,000 UZS**;
  - part 3: using a terminal or special QR registered to another taxpayer, **20,000,000 UZS**;
  - part 4: non-compliant cash register or broken service program, **20,000,000 UZS**.
  — [lex.uz, Tax Code Art. 221](https://lex.uz/ru/docs/4674902)
- Tax Code Art. 227-1 covers breach of requirements on fiscal signs, on equipping with automated accounting tools, **or on integration with tax authorities' information systems**, and of digital-marking rules. The fine is **2% of net revenue of the last reporting quarter**, or **20%** for a repeat offence within a year (ZRU-927 of 29.05.2024). — [lex.uz, Tax Code Art. 227-1](https://lex.uz/ru/docs/4674902); background on the cut from 100% to 2% — [buxgalter.uz](https://buxgalter.uz/publish/doc/text200217_shtraf_po_state_227-1_nalogovogo_kodeksa_snijen_do_2_procentov)
- On first detection of an Art. 221 part 1 violation, the tax officer issues an official warning to the taxpayer's personal cabinet. If it is not fixed within 3 days or recurs, an on-site inspection follows. — [lex.uz, Tax Code](https://lex.uz/ru/docs/4674902)
- In Dec 2023 the Tax Committee told retail and catering users of R-Keeper, JOWi, iiko and similar that their software must be integrated as a virtual cash register, or they face Art. 227-1. The commentator argued the norm's scope (front-office tools vs 1C/1Uz) was unclear. — [buxgalter.uz, 25.12.2023](https://buxgalter.uz/publish/doc/text195312_kak_programmy_avtomatizacii_nujno_integrirovat_s_informacionnymi_sistemami_nalogovyh_organov_ruz); [buxgalter.uz](https://buxgalter.uz/publish/doc/text195004_o_trebovanii_nalogovyh_organov_k_predpriyatiyam_roznichnoy_torgovli_i_obshchepita_uzbekistana_ob_integracii_ih_programm_ucheta_s_nalogovymi_organami)

### Inferences
- From 22.10.2026, a catering POS or order-taking system (waiter app, QR-menu ordering, KDS-linked order ledger) that runs *alongside* a separate fiscal cash register without being integrated with the tax systems is plausibly an explicit violation. The wording covers "order-accounting software products", so it is not limited to cash software. The penalty route would likely be Tax Code Art. 227-1 (2% of quarterly net revenue). The PKM 393 text itself does not name the article.
- For a new POS, the safe design is one of two:
  - (a) the POS itself is registered as a **virtual cash register** in the State Register, via fiscal data operator integration plus a TSC, and every order and payment is fiscalised in it;
  - (b) the POS is integrated with a registered online cash register or virtual cash register in a way the Tax Committee recognises as "integration with tax information systems".
  The new TSC ban, which targets secondary systems integrated with online cash registers "for printing QR receipts", suggests that *loose* bridges (POS → cash register only to print a receipt) are exactly what is being targeted. Route (b) therefore carries regulatory uncertainty.
- Every payment method (cash, card, UzQR, Click/Payme/Uzum QR, mixed) needs a fiscal receipt that records the correct "payment form", including "mixed" for split bills. For credit/nasiya and advance (deposit/banquet prepayment) flows the cash register supports special receipt types without a fiscal sign.
- Order flows matter for banquets/toʻy and deposits: advance → credit → sale receipt types.

### Gaps
- The full text of the **excepted cases in PF-5813 clause 4, para 2**, referenced by the new clause 4, was not retrieved.
- No official Tax Committee or fiscal data operator explanation was found of what exactly counts as "integration" for order-management software after 22.10.2026, nor of any transition grace period. No buxgalter, norma or soliq.uz explainer on this specific catering clause was found; the change appears not yet widely covered.
- Which administrative or tax article would be applied to the new clause 4 ban (227-1 vs 221) is not stated in PKM 393.
- The Code of Administrative Responsibility amounts (BRV-based fines on officials) for these violations were not verified. Some secondary sources cite Art. 175, but this was unconfirmed.

---

## Q2. Which fiscal integration paths exist for third-party POS (fiscal data operator APIs, virtual cash register providers, TSCs)?

### Takeaway
Uzbekistan has a single state **fiscal data operator: SUE "Yangi texnologiyalar" Scientific-Information Centre under the Tax Committee**. Fiscal modules are issued by the fiscal data operator and activated through accredited **technical service centres** (TSCs, ЦТО/ЦОТУ in Russian). A third-party POS can do one of three things:
- (1) get itself registered as a **virtual cash register** (fiscal data operator integration + Cybersecurity Center conclusion + certificate + Tax Committee registration + TSC);
- (2) integrate with an already-registered virtual cash register or online cash register vendor's API. Several vendors advertise this, but it is now legally sensitive under PKM 393;
- (3) for marketplace-type flows, use the "Marketplace" route with a **virtual fiscal module** (an EDS certificate), sending receipts to the fiscal data operator via API.
The Tax Committee also provides a free base virtual cash register program.

### Cited Findings
- Fiscal data operator definition: "SUE Scientific-Information Centre 'Yangi texnologiyalar' under the Tax Committee, which independently and continuously receives, processes and stores fiscal data online" (as amended by PKM 548 of 18.10.2023). — [lex.uz, PKM 943 Annex 1 cl. 2](https://lex.uz/ru/docs/4603329)
- Definitions in PKM 943:
  - **virtual cash register**: "a software product (and complexes) able to connect to a fiscal module, generating fiscal documents and transmitting them to the fiscal data operator in real time, with the function of printing receipts on paper or sending them electronically";
  - **fiscal module**: a physical device developed by the operator providing cryptographic protection (fiscal memory);
  - **virtual fiscal module** (added by PKM 23 of 23.01.2026): "a unique number created by the operator with an attached EDS public key certificate".
  — [lex.uz, PKM 943](https://lex.uz/ru/docs/4603329)
- The fiscal data operator develops and publishes the electronic structure of fiscal data, the procedure for sending it, and the exchange formats with TSCs in its special information system (Annex 2 cl. 2). — [lex.uz, PKM 943 Annex 2](https://lex.uz/ru/docs/4603329)
- Virtual cash register technical requirements include:
  - automatic blocking after **48 hours offline** (PKM 23 of 2026);
  - fiscal data sent directly and only to the operator via the fiscal module;
  - **one fiscal module per virtual cash register at a time**;
  - support for a barcode scanner and 2D scanners for marking codes;
  - real-time loading of national catalogue IKPU codes;
  - acceptance of all bank cards and QR codes directly through the device;
  - mandatory connection to the unified bank QR system (PKM 393);
  - time sync to UTC+5 or the operator server;
  - a password of at least 6 characters;
  - a certificate of conformity and copyright documents;
  - receipts mainly in the state language.
  — [lex.uz, PKM 943 Annex 2 cl. 6](https://lex.uz/ru/docs/4603329)
- The fiscal data operator gives a conclusion on correct or incomplete integration of an online cash register or virtual cash register within **10 working days** (Annex 4 cl. 12). — [lex.uz, PKM 943](https://lex.uz/ru/docs/4603329)
- **Steps to register a program as a virtual cash register** (per buxgalter, Dec 2023):
  1. The developer applies to the fiscal data operator (NIC Yangi texnologiyalar) and signs an integration agreement.
  2. It obtains conclusions from the fiscal data operator and from SUE "Cybersecurity Center".
  3. It applies to the Tax Committee with: a reference sample, a certificate of conformity, an application, a sample receipt, a technical passport, operating docs (incl. tax inspector instructions), a spare-parts guarantee, the two conclusions and copyright/patent documents.
  4. A TSC must also be registered. It needs an agreement with the developer, premises, a call centre and certified specialists, and it signs with the fiscal data operator to receive fiscal modules.
  — [buxgalter.uz, 25.12.2023](https://buxgalter.uz/publish/doc/text195312_kak_programmy_avtomatizacii_nujno_integrirovat_s_informacionnymi_sistemami_nalogovyh_organov_ruz)
- The Tax Committee's base virtual cash register software is free. It covers goods intake, receipt generation, fiscal module integration, online sending to the operator, Z-report, user management, offline mode, nomenclature and reports (PKM 943 Annex 1 cl. 5). — [lex.uz, PKM 943](https://lex.uz/ru/docs/4603329)
- **Marketplace route (Annex 1a of PKM 943):**
  - Marketplaces integrate with the operator's special information system, send receipt data with sequence numbers, and get back a fiscal sign plus a QR code. Sending is confirmed with the marketplace's virtual fiscal module.
  - The marketplace sends the receipt link to the buyer's phone or e-mail.
  - Annex 1a also covers on-site purchases paid via marketplaces or payment organisations.
  - "API" is formally defined in this annex (PKM 847 of 18.12.2024).
  — [lex.uz, PKM 943 Annex 1a](https://lex.uz/ru/docs/4603329)
- TSC role: the TSC is the only place to buy an online cash register or virtual cash register licence. It signs the service contract, registers the cash register with the fiscal data operator and activates the fiscal module. Registration happens in the taxpayer's personal cabinet with an EDS, per outlet. — [moysklad.uz, 2026](https://www.moysklad.uz/poleznoe/shkola-torgovli/online-kassa-v-uzbekistane/)
- A cashier can switch to another TSC while keeping the same fiscal module. Non-compliant models are not forced out until removed from the registry. — [buxgalter.uz, 03.04.2026](https://buxgalter.uz/publish/doc/text214409_kak_rabotat_s_onlayn-kkt_po_novym_trebovaniyam_s_1_aprelya_2026_goda)
- Vendor pricing signals (marketing, unverified):
  - REGOS: fiscal module connection 1 BRV per virtual cash register; monthly service 0 UZS for REGOS users and 100,000 UZS for others. — [REGOS FAQ](https://regos.uz/ru/product/regos-vcr/faq)
  - Hippo POS: about 1 BRV one-time plus about 0.15 BRV per month per register.
  - Rahmat POS: virtual cash register from 123,600 UZS per month.
  - MobilKassa: from 80,000 UZS per month.
  — [moysklad.uz](https://www.moysklad.uz/poleznoe/shkola-torgovli/online-kassa-v-uzbekistane/)
- Cloud virtual cash register offerings (REGOS VCR, Hippo, Rahmat and others) say they fiscalise receipts automatically "via API or integrations with a website, CRM, marketplace or accounting system". — [moysklad.uz](https://www.moysklad.uz/poleznoe/shkola-torgovli/online-kassa-v-uzbekistane/); [Rahmat](https://rhmt.uz/pos/virtual-kassa/); [Hippo](https://hippo.uz/ru/pos/virtual-cash-box/); [REGOS](https://regos.uz/ru/blog/polezno-znat/onlajn-kassa-kkm-v-uzbekistane-virtualnaya-kassa-regos-vcr)
- **Dynamic UzQR + cash register:** a static UzQR only collects money and does *not* fiscalise automatically. For automatic fiscalisation, the outlet needs a **dynamic QR connected to the online cash register via an API key**: the cash register generates the QR with the amount and the customer scans it. Cash register makers and TSCs were still finishing software as of 30.06.2026. — [buxgalter.uz, 30.06.2026](https://buxgalter.uz/publish/doc/text215897_edinyy_qr-kod_obyazatelen_dlya_yurlic_s_1_iyulya_-_mojno_li_ispolzovat_drugie_qr-kody)
- Double counting warning: if a business issues receipts both from a POS registered as a virtual cash register and from an online cash register, turnover and VAT reports count sales twice. — [buxgalter.uz, 25.12.2023](https://buxgalter.uz/publish/doc/text195312_kak_programmy_avtomatizacii_nujno_integrirovat_s_informacionnymi_sistemami_nalogovyh_organov_ruz)
- State registries exist for online cash register models, virtual cash register software and TSCs. They are linked from buxgalter. — [buxgalter.uz, 14.05.2024](https://buxgalter.uz/publish/doc/text199847_kto_i_kakie_modeli_onlayn-kkt_obyazan_ispolzovat_v_uzbekistane)

### Inferences
- For a new POS vendor targeting catering after 22.10.2026, the robust path is to **become a registered virtual cash register**, alone or through a partner TSC. A cheaper but riskier path is to integrate with a registered virtual cash register vendor's API, provided that vendor and TSC accept responsibility under the new TSC ban. Expect several months of lead time: the fiscal data operator has a 10-working-day conclusion window, and certification, the Cybersecurity Center review and registry entry come on top.
- POS design must handle the following:
  - IKPU code and unit per menu item;
  - VAT rate per line (12% standard, or 6% under the simplified regime);
  - payment-form split (cash / card / QR / mixed);
  - receipt types (sale, return, advance, credit);
  - marking codes for beer and alcohol where applicable;
  - a 48-hour offline limit;
  - Z-reports;
  - generating dynamic UzQR from the order total.

### Gaps
- No public fiscal data operator (ofd.soliq.uz / Yangi texnologiyalar) API documentation or a public list of "certified integrators" was found. Formats are published in the operator's special information system, which needs access.
- No official current price for fiscal modules was found; only vendor claims.
- The current state-registry list of virtual cash registers was not retrieved, so it is not known which catering POS (iiko, R-Keeper, Jowi, Poster, Mison and others) are themselves registered.

---

## Q3. Delivery aggregators: market shares, commissions, public partner/POS APIs, payout terms

### Takeaway
The market is a duopoly. **Yandex Eats** acquired Express24's brand, site, apps and user base in late 2024; the Competition Committee estimated an 87.5% combined share of ready-food aggregator services as of end-2023. Express24 restaurants "moved to Yandex Eats". **Uzum Tezkor** was recognised as a dominant digital platform in April 2025. Wolt entered in 2024, and smaller players include Bringo, Girgitton, Broniboy and own Telegram bots. **Commissions are not published.** Secondary sources cite **20–30%** of order value in Uzbekistan. Yandex Eats has a documented Vendor API (OAuth2, menu, stop-list, orders). Uzum Tezkor advertises integrations with iiko, R-Keeper and JOWi, but no public API docs were found.

### Cited Findings
- In Oct 2024 the Competition Committee said Yandex Eats would control **87.5%** of the ready-food delivery aggregator market after buying Express24 assets (site, trademarks, apps, user database). The deal did not require prior approval. — [gazeta.uz, 28.10.2024](https://www.gazeta.uz/ru/2024/10/28/yandex/); [spot.uz, 28.10.2024](https://www.spot.uz/ru/2024/10/28/economic-concentration/). The 87.5% figure is based on end-2023 data. Competitors named at the time were Uzum Tezkor, Bringo, Girgitton, own services (Telegram bots, apps), Broniboy and Wolt (launched Sept 2024). — [search summary of gazeta/spot reports](https://www.gazeta.uz/ru/2024/10/28/yandex/)
- Express24's website now states "Restaurants of Express 24 moved to Yandex Eats". — [express24.uz](https://express24.uz/)
- The Competition Committee recognised Uzum Tezkor, Yandex Eats and Express24 as dominant operators in food and consumer-goods ordering and delivery. For Uzum Tezkor this was 02.04.2025, after its own notification. Dominance thresholds (from 1 Aug 2024): revenue above 100,000 BRV (37.5 bn UZS), **or** at least 50,000 monthly active end users, **or** at least 3,000 monthly active business users, plus network effects; or a share above 40%. Obligations: antimonopoly compliance, and **publishing price or terms changes at least 15 days in advance**. — [gazeta.uz, 02.04.2025](https://www.gazeta.uz/ru/2025/04/02/uzum-tezkor/); [kun.uz, 02.04.2025](https://kun.uz/en/news/2025/04/02/uzum-tezkor-named-dominant-digital-platform-in-delivery-market)
- Players listed in a Nov 2025 overview: Express24 (2017, partly bought by Yandex in 2024), Uzum Tezkor (2023), Yandex Eats (2023), Yandex Lavka (2024), Yandex Ultima Go (2024, premium), Wolt (2024, expanding Tashkent zones in 2025), Korzinka Go, Noor, Mars Express (Samarkand). The same overview cites restaurant costs of a **20–30% commission** plus 3,000–5,000 UZS packaging per order, per Busoft (a POS integrator). — [zira.uz, 17.11.2025](https://zira.uz/ru/2025/11/17/kak-segodnya-rabotaet-dostavka-v-uzbek/)
- Uzum Tezkor partner page:
  - integrates with **iiko, R-Keeper and JOWi**;
  - works on any device;
  - customers can pay by card, cash, Uzum Bank or **Uzum Nasiya**;
  - "regular payments and transparent reporting", with financial documents in **Didox** (e-document system);
  - sign-up via Telegram bot in about 5 minutes;
  - the commission is not stated.
  — [promo.tezkor.com/partners](https://promo.tezkor.com/partners/uz)
- Uzum Tezkor reported the partners' share of delivery orders rising to 20–30% and then up to 40% in some months, with more than 1,600 partner restaurants and shops (2024). — [gazeta.uz (oz), 06.09.2024](https://www.gazeta.uz/oz/2024/09/06/uzum-tezkor/) (as summarised by search; not fetched in full)
- **Yandex Eats Vendor API** (official docs):
  - OpenAPI spec with Authentication, Menu, Orders and Restaurants sections.
  - Pull model: Yandex calls endpoints the partner implements, e.g. `GET /menu/{restaurantId}/composition`, menu, stop-list and promo import operations. Push model: "Vendor Management Integration API", with restaurant block/unblock using reasons `technical_problem` and `too_busy`.
  - Other functions: feedback and replies; courier handover code validation.
  - Auth is OAuth2 client credentials (`/security/oauth/token`).
  - Country availability is not stated.
  — [yandex.ru/dev/eda-vendor](https://yandex.ru/dev/eda-vendor/doc/en/ref/)
- Yandex Eats connection process (third-party, Russia-oriented): request "POS integration" in the partner cabinet, get a Client ID and Secret, about 3–5 business days. Orders must be confirmed within about 3 minutes or they auto-cancel. — [cenaly.ru](https://cenaly.ru/en/docs/integration-yandex/) (secondary; Russia context)
- iiko ↔ Yandex Eats: connection is requested in the Yandex Eats cabinet with the iiko client_id, secret and host. It syncs menu, prices, modifiers and stop-lists, and sends status both ways. iikoCloud API has a `StopListUpdate` webhook and per-terminal stop-lists. — [alpha-soft](https://horeca.alpha-soft.ru/blog/integratsiya-yandeks-edy-v-iiko/); [apimenu.ru](https://apimenu.ru/yandex) (Russian integrators)
- R-Keeper Uzbekistan (Busoft) offers a hub integrating **Uzum Tezkor, Express24 and Yandex Eats**. Restaurants, menus and stop-lists go from the POS to the aggregators, and orders come straight into R-Keeper. Pricing is not given. — [rkeeper.uz, 11.06.2024](https://rkeeper.uz/stati/post/r_keeper-integraciya-agregatorov-dostavki-edy-uzum-tezkor-express-24-yandex-eda)
- Yandex Eats commission in Russia (not Uzbekistan) is cited at 20–35% (e.g. 35% with delivery, 20% pickup). It is **not** Uzbek data. — [vc.ru](https://vc.ru/retail/2180724-yandeks-eda-1100-zakazov-i-1-3-mln)
- Fiscal receipts for marketplace/aggregator-paid orders: PKM 943 Annex 1a obliges marketplaces to send electronic fiscal receipts via the operator. It also covers businesses giving online cash register or virtual cash register receipts for on-site purchases paid via marketplaces or payment organisations. Receipts for delivered goods show the supplier's legal address. — [lex.uz, PKM 943](https://lex.uz/ru/docs/4603329)

### Inferences
- A POS needs aggregator connectors for at least Yandex Eats and Uzum Tezkor: order intake, menu and price push, stop-list sync, and status updates. Wolt is a secondary candidate. Since Uzum Tezkor has no public API docs, integration likely requires a partnership agreement; the iiko, R-Keeper and JOWi precedents show it exists.
- The aggregator commission (about 20–30%) is a large cost line. POS reporting should show net revenue per channel after commission, packaging and promos.
- Who issues the fiscal receipt for aggregator orders is a design point (the aggregator as marketplace, or the restaurant's own cash register). The answer depends on the payment flow and should be confirmed per aggregator contract.

### Gaps
- No official commission rates, payout schedule (days), or minimum terms were found for Yandex Eats Uzbekistan, Uzum Tezkor or Wolt Uzbekistan. The 15-day notice duty means terms exist in public offers, but those were not retrieved.
- No 2025–2026 market share figures were found after the Express24 deal, and none for Wolt's position in 2026.
- No data was found on the share of orders through restaurants' own Telegram bots.
- Uzum Tezkor and Wolt POS API documentation was not found publicly.

---

## Q4. Payment methods in cafes and restaurants, their shares, and merchant fees

### Takeaway
No catering-specific payment-mix statistics were found. National data shows rapidly rising card and terminal use: about 460 trillion UZS through terminals in 2025, and terminals at about 48% of cash receipts in trade and services per a CBU cash-circulation review cited by media. The government targets 75% cashless in trade and services by 2030. Accepted methods: cash; Uzcard and Humo cards via bank terminals (mandatory in every catering outlet); Visa/Mastercard (limited, mainly at hotels and higher-end outlets, not verified); QR via Click, Payme, Uzum, Paynet and TezQR; and the **mandatory unified UzQR** (CBU/MUNIS) since 1 July 2026. UzQR's official merchant fee is **0.65%**. Click, Payme and Uzum acquiring is cited at about **0.8–2%** by secondary sources.

### Cited Findings
- On 01.01.2026 there were more than 68.27 million bank cards. Payment terminal turnover in 2025 was about 460.25 trillion UZS, up 133.5 trillion from 2024. Terminals rose from 430.8k to 453.5k, compared with turnover of 387.4 trillion UZS for Jan–Jul 2026. — [yuz.uz](https://yuz.uz/ru/news/v-uzbekistane-chislo-bankovskix-kart-prevsilo-68-millionov-shtuk); [yuz.uz](https://yuz.uz/ru/news/v-uzbekistane-kolichestvo-bankovskix-kart-prevsilo-75-millionov) (as summarised by search)
- A CBU cash-circulation review for 2025, as reported by Frank.uz: receipts via terminals rose from 40% to 43% of total cash receipts, and to **48% in trade and services**. The primary CBU document was not verified. — [frank.uz](https://frank.uz/news/dolya-nalichnyh-deneg-v-ekonomike-uzbekistana-snizilas-na-fone-rosta-beznalichnyh-platezhey/)
- UP-246 (10.12.2025) targets a 75% cashless share of household payments in trade and services by 2030, and singles out retail, **catering** and construction as high-cash sectors. — [spot.uz, 20.07.2026](https://www.spot.uz/ru/2026/07/20/shadow-economy); [economist.kg, 12.12.2025](https://economist.kg/ekonomika/2025/12/12/uzbiekistan-chastichno-pieriekhodit-na-bieznal-kak-eto-povliiaiet-na-kr/)
- Cash share of total turnover fell to 16.9% (April 2026) per upl.uz. Note that this measure differs from retail payment share. — [upl.uz, 06.05.2026](https://upl.uz/economy/62726-news.html)
- **UzQR** basics:
  - Operator: the CBU clearing system MUNIS. Launched June 2026. Works with Humo and Uzcard (and som Visa cards per spot).
  - Free to connect via a bank. Mandatory for trade and service legal entities from 1 July 2026.
  — [gazeta.uz, 22.06.2026](https://www.gazeta.uz/ru/2026/06/22/uzqr/); [spot.uz, 03.07.2026](https://www.spot.uz/ru/2026/07/03/mandatory-single-qr)
- UzQR fee split:
  - **Merchant fee is 0.65%**: acquiring bank 0.1%, then 0.55% to MUNIS, which shares it as MUNIS 0.2%, the QR-reading app 0.15%, Uzcard/Humo 0.1–0.15%, the issuer 0.05% and the payer's bank 0.05%.
  - Some banks offer lower rates, e.g. Ipak Yuli at 0.25%.
  - Some payment services may add about 1% for the buyer, making the total at least 1.65%. The CBU says the buyer fee issue is being resolved.
  - Settlement is within about 24 hours or 1 working day; Paynet's offer says 3 working days.
  — [gazeta.uz](https://www.gazeta.uz/ru/2026/06/22/uzqr/); [spot.uz](https://www.spot.uz/ru/2026/07/03/mandatory-single-qr); [kapital.uz](https://kapital.uz/komissiya-po-edinomu-qr/)
- The CBU says UzQR **does not cancel** other QR codes (Click, Payme, Uzum, TezQR) or cards and cash. Some businesses were pressured to remove other QR codes; Payme's CEO said UP-246 does not prohibit them. — [spot.uz, 03.07.2026](https://www.spot.uz/ru/2026/07/03/mandatory-single-qr); [spot.uz, 01.07.2026](https://www.spot.uz/ru/2026/07/01/uzqr)
- Other QR services charge "about 1% and above" per buxgalter. — [buxgalter.uz, 30.06.2026](https://buxgalter.uz/publish/doc/text215897_edinyy_qr-kod_obyazatelen_dlya_yurlic_s_1_iyulya_-_mojno_li_ispolzovat_drugie_qr-kody)
- Blog estimates of online acquiring fees: Payme about 1–1.5%, Click about 1–2%, Uzum about 0.8–1.5%; vc.ru gives about 0.8–2% for all three. The exact rate is set in the acquiring contract. — [uzneo.uz, Apr 2026](https://uzneo.uz/blog/payme-click-uzum-podklyuchit-oplatu); [vc.ru](https://vc.ru/dev/3069312-podklyuchenie-onlayn-oplaty-v-uzbekistane-payme-click-uzum)
- From 1 April 2026, cash is banned for about 28.8k IKPU codes (alcohol, tobacco, fuel and others) and for items over 25 million UZS. Catering got back cash for alcohol and tobacco from 1 June 2026 (UP-100). — [buxgalter.uz](https://buxgalter.uz/publish/doc/text214409_kak_rabotat_s_onlayn-kkt_po_novym_trebovaniyam_s_1_aprelya_2026_goda); [buxgalter.uz](https://buxgalter.uz/publish/doc/text215370_chto_menyaet_v_nalogovom_administrirovanii_malyh_predpriyatiy_ukaz_up-100)
- Receipts must record bank card type (corporate, personal or social), and for social cards the first and last 4 digits plus the transaction ID (PKM 610 of 29.09.2025). — [lex.uz, PKM 943 cl. 7](https://lex.uz/ru/docs/4603329)

### Inferences
- A POS must support cash, a bank card terminal (Uzcard and Humo, ideally integrated), dynamic UzQR generated from the bill (required for automatic fiscalisation), optional Click, Payme and Uzum QR, and split/mixed payments. Each payment must map to the "payment form" field on the fiscal receipt.
- Lower UzQR fees (0.65%) compared with about 1–2% for private QR or acquiring may shift restaurants toward UzQR. Settlement-time reconciliation (T+1) is needed.

### Gaps
- No CBU or official data on payment-method shares specifically in catering or cafes.
- No verified Visa/Mastercard acceptance share in restaurants.
- No official bank-terminal MDR for Uzcard and Humo POS acquiring was found; this needs bank tariff sheets.

---

## Q5. How common are P2P card transfers and nasiya (credit for regulars), and what are the risks?

### Takeaway
Paying the owner's or staff's personal card by P2P transfer is widespread enough that the Tax Committee started **targeted P2P-turnover analysis of business owners in 2026**. It sends letters citing 11-month 2025 P2P turnover and summons entrepreneurs with large card inflows (reportedly above 500 million UZS a year). A draft rule would have banks report large card inflows; the CBU objected on constitutional grounds. P2P revenue is treated as concealed tax base. It also breaks the receipt obligation, since every payment needs a fiscal receipt (5 million UZS fine), and using a terminal or QR registered to another person costs 20 million UZS. No data was found on how common informal nasiya (tab) is in cafes. Legally, cash registers support "credit", "advance" and "instalment" receipt types, and consumer BNPL (Uzum Nasiya) is offered through Uzum Tezkor.

### Cited Findings
- On 13.05.2026 the Tax Committee announced analysis of P2P (card-to-card) payments in trade. Inspections found outlet owners concealing revenue received on personal cards. Cards of **heads of legal entities** are being analysed. Entrepreneurs with many P2P transactions, reportedly with annual card turnover above **500 million UZS**, were summoned. The legal basis cited is a presidential decree of 30.10.2020 on reducing the shadow economy. — [spot.uz, 13.05.2026](https://www.spot.uz/ru/2026/05/13/p2p-analysis); [kun.uz, 13.05.2026](https://kun.uz/ru/news/2026/05/13/v-uzbekistane-nachali-p2p-monitoring-poka-mnogo-neyasnostey)
- Tax letters list the citizen receiving trade revenue via P2P, their PINFL, P2P turnover for 11 months of 2025 and the total. They state the entrepreneur "concealed the tax base". Reported consequences: back taxes and a **20% fine** on the hidden amount (spot.uz). — [gazeta.uz, 13.05.2026](https://www.gazeta.uz/ru/2026/05/13/p2p-transactions/); [spot.uz, 13.05.2026](https://www.spot.uz/ru/2026/05/13/p2p-raids)
- The Tax Committee says P2P transfers between individuals are not taxable as such, and denies mass monitoring of private transfers. — [gazeta.uz, 13.07.2026](https://www.gazeta.uz/ru/2026/07/13/p2p-transfers/)
- A draft resolution would make banks report accounts and large card inflows of individuals, incl. P2P, to the tax authorities (consultation until 8 July 2026). The CBU called it contrary to the Constitution. Final adoption was not confirmed. — [gazeta.uz, 24.06.2026](https://www.gazeta.uz/ru/2026/06/24/p2p/); [gazeta.uz, 13.07.2026](https://www.gazeta.uz/ru/2026/07/13/p2p-transfers/)
- P2P monitoring and marking were officially expected to bring an extra 30 trillion UZS to the budget in 2026. — [kun.uz, 13.05.2026](https://kun.uz/ru/news/2026/05/13/v-uzbekistane-nachali-p2p-monitoring-poka-mnogo-neyasnostey)
- Experts' views on P2P checks. — [kursiv.media, 25.05.2026](https://uz.kursiv.media/2026-05-25/proverka-p2p-perevody-v-uzbekistane/)
- Tax Code legal exposure:
  - Art. 223 (concealment of tax base).
  - Art. 221 part 1: selling without a receipt or refusing terminal/QR payment, 5 million UZS.
  - Art. 221 part 3: using a terminal or special QR registered to another taxpayer, 20 million UZS.
  — [lex.uz, Tax Code](https://lex.uz/ru/docs/4674902)
- Nasiya/credit in fiscal terms: receipt "payment type" includes **advance, instalment, credit**. Such receipts carry no QR code or fiscal sign (PKM 943 Annex 1 cl. 7). A Tax Committee explanation on accepting advance and credit payments exists. — [lex.uz, PKM 943](https://lex.uz/ru/docs/4603329); [buxgalter.uz](https://buxgalter.uz/publish/doc/text193843_kak_prinimat_plateji_avansom_i_po_kreditu_razyasneniya_nalogovogo_komiteta); [buxgalter.uz](https://buxgalter.uz/publish/doc/text175937_kak_v_cheke_onlayn-kkm_otrazit_prodaju_tovara_komitenta_avans_i_rassrochku)
- Canteens serving fixed groups (workers, students) may use individual or group subscriptions (abonements) for settlement (catering rules cl. 14). — [lex.uz, PKM 75 Annex 2](https://lex.uz/ru/docs/243233)
- Uzum Tezkor lets customers pay with Uzum Nasiya (BNPL). — [promo.tezkor.com](https://promo.tezkor.com/partners/uz)

### Inferences
- A POS should discourage P2P by making compliant QR (UzQR or Click/Payme dynamic QR tied to the receipt) as fast as a card transfer. It should not offer a "paid by transfer to card" payment type that bypasses fiscalisation. If it records such payments, it should flag them as compliance risk.
- An informal tab (nasiya) for regulars should be modelled as accounts receivable per customer, with a credit-type receipt at serving and a sale receipt or fiscalisation at settlement. The exact sequence should be confirmed with a tax adviser or TSC.
- P2P tips to waiters are a separate question (personal income); no source addressed it.

### Gaps
- No survey or statistics on the share of P2P payments in cafes and restaurants were found.
- No data on how common informal credit (nasiya / "daftar") is for regulars in Uzbek cafes; searches in Uzbek and Russian returned nothing relevant.
- The exact moment and receipt sequence for credit sales (credit receipt vs final sale receipt) is not specified in the sources found.
- Final status of the bank-reporting draft resolution (after July 2026) is not confirmed.
- No guidance was found on the tax and fiscal treatment of tips paid by P2P to staff.
