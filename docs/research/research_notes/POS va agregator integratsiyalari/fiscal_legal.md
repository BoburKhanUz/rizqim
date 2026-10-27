# Fiscal and legal rules for aggregator ↔ restaurant POS orders in Uzbekistan (as of 25.10.2026)

Scope note: this extends, and does not repeat, `POS bozori narxlar va fiskal terminallar/fiscal_terminals.md`, which covers:
- the VM 393 catering ban (VM 943 Annex 1 clause 4) and its entry into force on 22.10.2026;
- the TXKM clauses 16¹ and 25;
- the FM/FiscalDriveService receipt model;
- fiscal-terminal prices.

It also builds on `Ovqatlanish tarmoqlari uchun POS talablari/fiskal_background.md` §1.8 and `Alipos va agregator POS modeli/uz_aggregators.md`. Those already cover:
- the 2022 inspector explanation that a Payme/Click receipt with the restaurant's TIN as "komitent" can replace the restaurant's own receipt;
- Payme's `SetFiscalData` method;
- the marking relief for catering (PF-100/PF-175);
- Yandex/Uzum dominance and market shares.

What is new here:
1. The full text of **VM 943 Annex 1a (the "Marketplace" regulation)**, read from the current lex.uz consolidated text, including the 2026 VM 23 and VM 393 edits.
2. The 2026 receipt-field rules for the komitent TIN and the delivery address.
3. **Yandex Eats Uzbekistan's current user terms (21.08.2026).** They show an agent model for card payments and consent to cross-border transfer of personal data to partners.
4. The tax-authority "commissioner/committent" receipt model already used for taxi aggregators.
5. **The 26.03.2026 rewrite of the personal-data law (ORQ-1125).** It removes the general localization rule for ordinary personal data.
6. **The new "order aggregator operator" status in the E-commerce Law (ORQ-1173, 10.09.2026)**, in force about 12.12.2026.
7. The Competition Law (ZRU-850) art. 18 text that applies to dominant platforms.

Labels used:
- **[CONFIRMED]**: primary legal text read on lex.uz in this session.
- **[VENDOR]**: company terms or claims.
- **[MEDIA]**: news or secondary commentary.
- **[INFERENCE]**: my reasoning.
- **[LAWYER]**: a point that needs a lawyer or a Tax Committee written answer.

---

## Q1. VM 943 / VM 393 rules: who must issue the receipt for aggregator and marketplace orders, and how (agency/komitent model, online prepayment, marking)

### Takeaway
Uzbek law recognises exactly three kinds of "nazorat-kassa texnikasi" (control-cash equipment):
- an online-NKM;
- a virtual kassa;
- a **registered "Marketplace"**, meaning an e-payment organisation or e-commerce platform that fiscalises receipts through the OFD with a *virtual fiscal module*.

An aggregator's own app can therefore issue a legally valid fiscal receipt **only if the aggregator is itself entered in the State Register as a Marketplace** (or is a registered virtual kassa). In that case:
- the receipt must carry the **restaurant's TIN in the "Komitent (vositachi) STIRi" field**;
- for delivered orders, it must show the delivering entity's legal address.

For **on-site** purchases paid through e-payment systems, including bank single-QR, the **venue's own online-NKM/virtual-kassa receipt is mandatory**.

The law is silent on who must fiscalise when an aggregator takes the money remotely. That is decided by contract, so one party must issue the receipt and the other must not duplicate it. The venue remains responsible for the receipt being issued in full (Annex 1 cl. 4).

The VM 393 ban on non-integrated "order-accounting software" in catering means that aggregator orders entering a venue's iiko, R-Keeper, Poster or Rizqim POS are in scope. The POS that keeps those orders must be integrated with the tax systems, whoever prints the receipt.

### Cited Findings
- **What counts as fiscal equipment.** VM 943 Annex 1 cl. 3 (255-son edition):
  - "Ўзбекистон Республикаси ҳудудида фақат белгиланган тартибда Давлат реестрига киритилган онлайн-НКМ ва виртуал кассалар ёки Маркетплейсдан фойдаланишга рухсат этилади. Бунда онлайн-НКМ, виртуал касса, Маркетплейс тушунчалари солиқ тўғрисидаги қонунчилик ҳужжатларида «назорат-касса техникаси» номи билан қўлланилади."
  - In English: only registered online-NKMs, virtual kassas or a Marketplace may be used, and all three count as "control-cash equipment" under tax law.
  - The same clause bars any of them that do not show IKPU (MXIK) codes or cannot connect barcode or marking scanners.
  - [CONFIRMED] — [lex.uz, VM 943 (consolidated, uz)](https://lex.uz/uz/docs/-4603329)
- **Marketplace definition.** Annex 1a cl. 3:
  - "Маркетплейс — оператор тизимига боғланиш имкониятига эга бўлган, операторга тақдим қилган чек маълумотлари асосида онлайн режимда фискал белгиларни қабул қилувчи, чекларни электрон шаклда харидорларга юбориш функциясига эга бўлган … электрон тўлов ҳужжатлари воситасида нақд ёки нақд пулсиз ҳисоб-китобларни амалга ошириш тизими".
  - In English: a system that connects to the operator, receives fiscal signs online for the receipt data it submits, sends e-receipts to buyers, and settles cash or non-cash payments via electronic payment documents.
  - "Маркетплейс фойдаланувчиси" (Marketplace user) is the business whose fiscal data is sent to the operator through the Marketplace server.
  - [CONFIRMED] — [lex.uz, VM 943 Annex 1a](https://lex.uz/uz/docs/-4603329)
- **Scope of Annex 1a.** Cl. 2 covers:
  - (i) e-receipts for settlements made via a Marketplace, "шу жумладан тўлов ташкилотлар орқали" (including through payment organisations);
  - (ii) businesses issuing **online-NKM or virtual-kassa receipts** for **on-site** purchases paid via a Marketplace or payment organisation;
  - (iii) sending data on on-site payments via the bank single QR to the operator. The VM 393 edition, in force since 21.07.2026, adds: "ягона QR-код орқали амалга оширилган тўловларга ҳам назорат-касса техникаларидан мажбурий равишда чек берилиши шарт" (a receipt from control-cash equipment is mandatory for single-QR payments too).
  - [CONFIRMED] — [lex.uz, VM 943 Annex 1a cl. 2](https://lex.uz/uz/docs/-4603329)
- **Annex 1 cl. 6 (VM 23 edition, 23.01.2026).** Businesses on online-NKM or virtual kassa must give buyers a fiscal receipt from the online-NKM or virtual kassa "жойларда харид қилинган маҳсулот ва хизматларга электрон тўлов тизимлари орқали амалга оширилган тўловлар учун" (for e-payment-system payments for goods and services bought on site). [CONFIRMED] — [lex.uz, VM 943](https://lex.uz/uz/docs/-4603329)
- **How a Marketplace fiscalises.** Annex 1a, by clause:

  | Clause | Rule |
  |---|---|
  | 4 (VM 23 edition) | Each receipt is confirmed by the **Marketplace's virtual fiscal module** |
  | 5 | The Marketplace is liable for the accuracy of the data |
  | 6–7 | Receipts are sent with sequence numbers; the operator returns a fiscal sign and QR code |
  | 8 | The receipt link goes to the buyer's phone number, e-mail or personal cabinet |
  | 10–11 | The Marketplace must integrate with the operator's special IS, have an IS for forming, sending and storing receipts, and have a **call-centre** |
  | 12 | Receipt data must be stored **at least 5 years**, and users' personal data kept confidential |
  | 13–14 | Registry entry by application; the Tax Committee decides within 15 working days |
  | 19–20 | Each business selling through the Marketplace **signs a contract with it**, and the Marketplace records that business in the operator's special IS |

  [CONFIRMED] — [lex.uz, VM 943 Annex 1a](https://lex.uz/uz/docs/-4603329)
- **Receipt fields for online-NKM, virtual-kassa and Marketplace receipts** (Annex 1 cl. 7, as edited by VM 610 of 29.09.2025 and VM 23 of 23.01.2026):
  - The receipt must contain "**Комитент (воситачи) СТИРи/(ЖШШИРи)**" (TIN or PINFL of the committent / intermediary). This is the field that puts the restaurant's TIN on an aggregator's or payment system's receipt.
  - The address field is "пулли ҳисоб-китоблар амалга ошириладиган манзил (**харидорга етказиб бериш хизмати амалга оширилган тақдирда етказиб берувчи тадбиркорлик субъектининг юридик манзили**)". In English: the settlement address, or, where delivery is provided, the legal address of the delivering business.
  - Other required fields include:
    - card type (corporate, personal or social);
    - a unique transaction number;
    - the marking code for marked goods;
    - the unit of measure from the national catalogue;
    - the barcode.
  - [CONFIRMED] — [lex.uz, VM 943 Annex 1 cl. 7](https://lex.uz/uz/docs/-4603329)
- **Responsibility stays with the seller.** Annex 1 cl. 4, current paragraph: "Харид чекини тўлиқ миқдорда шакллантирилиши ҳамда белгиланган тартибда харидорга тақдим этилиши учун масъулият тадбиркорлик субъектлари зиммасига юклатилади" (the business is responsible for the receipt being formed in full and given to the buyer). The same clause, pre-22.10.2026 edition, says catering and retail accounting software "мажбурий равишда солиқ органларининг ахборот тизимлари билан интеграция қилиниши ва Давлат реестрига киритиши лозим" (must be integrated with the tax authorities' information systems and entered in the State Register). [CONFIRMED] — [lex.uz, VM 943 Annex 1 cl. 4](https://lex.uz/uz/docs/-4603329)
- **Tax API for e-commerce platforms** (Annex 1a ch. 3¹, added by VM 847 of 18.12.2024 and edited by VM 23 of 2026):
  - Registered e-commerce platforms may use tax-system data via API (cl. 12¹), after applying through their personal cabinet (12²).
  - The Tax Committee grants access within 10 working days if the platform:
    - holds a **certificate that its personal-data database is registered** in the State Register of PD databases (Personalisation Agency at the Ministry of Justice) (12³ "б");
    - has the **IP address and the data-processing hardware physically located in Uzbekistan** (12³ "в");
    - requests only data that matches e-receipt fields (12³ "д").
  - The rate limit is 1 request per second (12⁴).
  - Personal data and tax-secret data received may not be disclosed to third parties (12⁶–12⁷).
  - [CONFIRMED] — [lex.uz, VM 943 Annex 1a ch. 3¹](https://lex.uz/uz/docs/-4603329)
- **Commissioner/committent model already in use (taxi aggregators):**
  - The Tax Committee integrated taxi aggregators' systems. Receipts are formed in the aggregator's app and are electronic.
  - "В системе онлайн-ККТ агрегатор – комиссионер, а таксопарки и прочие субъекты, оказывающие услуги такси, – комитенты" (in the online-cash-register system the aggregator is the commissioner, and taxi parks and other taxi service providers are the committents).
  - Taxi parks find their turnover under "Ежедневный отчет комиссионера" (the commissioner's daily report) by the aggregator's fiscal-module number. Turnover tax and VAT reports are then formed from online-NKM data automatically.
  - Dated 23.02.2024. Food delivery is not mentioned.
  - [MEDIA, citing Tax Committee] — [buxgalter.uz, 23.02.2024](https://buxgalter.uz/publish/doc/text197075_agregatory_taksi_i_subekty_predprinimatelstva_okazyvayushchie_uslugi_taksi_nalajivayut_vydachu_chekov_onlayn-kkt_svoim_klientam)
  - A 2026 search summary says 242 taxi aggregators were integrated with tax IS in January–August 2026, and that the Tax Committee is "developing a fiscal receipt process for other aggregators as well". [MEDIA, search summary only, not fetched] — [yuz.uz](https://yuz.uz/uz/news/news-MDKKLP)
- **Cash on delivery, the "pause" before fiscalisation:**
  - On 13.02.2024 the Tax Committee chairman Sh. Kudbiev responded to 227¹ cases against delivery businesses. Cashiers had issued receipts at dispatch, before the courier collected the cash.
  - He promised "функцию паузы до фискализации платежа" (a pause function until the payment is fiscalised) and suspension of the court cases.
  - No implementing act or technical description was found. [MEDIA] — [spot.uz, 14.02.2024](https://www.spot.uz/ru/2024/02/14/delivery-receipt/)
- **Marking for drinks:**
  - A presidential decree in force from **28.08.2026** treats marking codes on water, soft drinks, beer and tobacco bought by hotels and catering as "yakuniy isteʼmolga realizatsiya qilingan" (sold for final consumption).
  - A first marking violation will get a warning, and fixed fines will replace the 2%/20% revenue fines. That bill is due by November 2026.
  - [MEDIA] — [kun.uz, 29.08.2026](https://kun.uz/news/2026/08/29/markirovkalash-qoidalarini-buzganlik-uchun-javobgarlik-yengillashtiriladi)
  - The earlier note already records PF-100/PF-175 with the same effect for alcohol from 01.06.2026. See `fiskal_background.md` §2.4.

### Inferences
- [INFERENCE] **Does an aggregator's own app count as "integrated with tax systems"?** Only if the aggregator is a registered Marketplace or virtual kassa. In that case its receipts are full fiscal receipts. For the **restaurant**, however, VM 393's ban concerns the restaurant's own order-accounting software. An aggregator tablet or web cabinet used only to accept orders is arguably the aggregator's system, not the restaurant's. Once the order is pushed into iiko, R-Keeper, Poster or Rizqim POS, that POS must be integrated. **[LAWYER]**
- [INFERENCE] **Scenario matrix for Rizqim.** "Rizqim-M" means Rizqim registered as a Marketplace under Annex 1a.

  | Scenario | Who should fiscalise | Venue POS must… |
  |---|---|---|
  | (a1) Rizqim marketplace order, **prepaid online** (Click/Payme/Uzum) | Either the **payment organisation's** Marketplace receipt with the venue TIN as komitent, built from item data Rizqim sends, **or** Rizqim-M | Record the order with the external receipt's fiscal sign and terminal ID, and **not** print a second sale receipt for the same money. A refund needs a refund receipt from the same Marketplace. |
  | (a2) Rizqim order, **cash or card on delivery by the venue's courier** | **Venue**, from its own online-NKM or virtual kassa, at the moment of payment (mobile kassa or the "pause" approach) | Fiscalise at hand-over, not at dispatch |
  | (a3) Rizqim order, **paid on site** (pickup, QR at the counter) | **Venue**, from its own kassa (Annex 1 cl. 6; Annex 1a cl. 2 para. 3) | Normal sale receipt; for QR, mandatory even if the payment system also sends data |
  | (b1) Yandex or Uzum order, **prepaid in the aggregator app** | The **aggregator or its payment operator** under its contract with the venue (see Q2). The venue gets the receipt reference only if the aggregator shares it. | Rizqim POS should accept the order as "fiscalised externally" and keep the reference for reconciliation and VAT-refund matching **[LAWYER]** |
  | (b2) Yandex order, **cash to the partner, partner's courier** | **Venue** (Yandex terms cl. 5.1.3 put this cash outside Yandex's agreement) | Rizqim POS must fiscalise at payment |
  | (c) Any online prepayment for an order **delivered later** | Strictly, the receipt is due when the money is received. Whether a Marketplace receipt for a prepaid, not-yet-delivered meal should be typed as "advance" (bo'nak) and later closed by a sale receipt is **not addressed** in Annex 1a. Payment systems in practice issue a single sale receipt at payment. | **[LAWYER]** |

- [INFERENCE] **Is a venue that also prints its own receipt for an aggregator-prepaid order "doubling" turnover?** The 2022 inspector explanation (in `fiskal_background.md`) said either receipt suffices. A duplicate receipt would double turnover in the venue's VAT and turnover-tax data. Rizqim POS therefore needs an explicit "paid and fiscalised by: venue / payment system / aggregator" flag per order, and must block a second sale receipt when the flag is "external".
- [INFERENCE] **Rizqim as a Marketplace (Rizqim-M).** It is legally possible and gives full control over (a1). The requirements are:
  - State Register entry (15 working days);
  - a virtual FM;
  - an OFD integration;
  - a call-centre;
  - 5-year storage;
  - a contract with each venue;
  - registering each venue in the operator IS.

  If Rizqim also wants tax-API access (for example, checking a venue's TIN or VAT status), its servers and IP must be **in Uzbekistan** and its PD database registered (cl. 12³ "б", "в"). This is stricter than the general PD law after March 2026 (see Q3).
- [INFERENCE] **Marking.** After PF-175 and the 28.08.2026 decree, beer, water, soft drinks and tobacco bought by a catering business are deemed sold on purchase. So codes **should not be required on the restaurant's or aggregator's receipt** for these drinks. This holds for catering, not for shops that sell bottled drinks via Yandex Lavka or Uzum Tezkor "shops". **[LAWYER]** (the decree text, lex.uz 8442717, was not read).

### Gaps
- No Tax Committee clarification specifically on **food-delivery aggregators** (Yandex Eats, Uzum Tezkor) was found. Open questions: who fiscalises, whether they are registered Marketplaces, and how the venue reflects aggregator receipts.
- The **current Marketplace registry** (whether Yandex Eats / "Y EATS", Uzum Tezkor, Click, Payme, Uzum Bank are entered) was not obtained; soliq.uz registry pages were unavailable in earlier sessions.
- Annex 1a does not say whether a Marketplace may issue **advance (bo'nak) / credit** receipt types, or how a later venue receipt should reference a Marketplace receipt.
- The "pause until fiscalisation" function (2024) was never documented officially in sources found.
- The Payme/Click fiscal item schema (the commission-info TIN field) could not be confirmed from official docs in this session; the Payme page fetched was an intro page only.

---

## Q2. How Yandex Eats and Uzum Tezkor handle receipts and payments today

### Takeaway
**Yandex Eats Uzbekistan** works on an agency model:
- the restaurant ("Partner") is the seller;
- Yandex "acts on the instruction of the Partner and/or courier" when accepting card payments, through an authorised payment operator;
- receipts "provided by law" appear in the app's order history;
- cash paid to the partner for partner-delivered orders is outside Yandex's agreement.

This is consistent with Yandex (or its payment operator) issuing the electronic receipt for prepaid orders with the restaurant as komitent. The terms do **not** say who issues it.

**Uzum Tezkor's** terms were not found publicly. Uzum's sister marketplace uses a *vositachilik* (intermediary/commission) offer, and Tezkor partners get financial documents via Didox.

There is **no public Tax Committee clarification** for food aggregators. The only official precedent is the 2023–2024 taxi model: the aggregator is the commissioner, issues receipts in its app, and the service providers are committents.

### Cited Findings
- **Yandex Eats user agreement (UZ, Russian version, published 21.08.2026; earlier versions 07.08.2025 and 12.05.2025):** [VENDOR] — [yandex.uz/legal/termsofuse_eda](https://yandex.uz/legal/termsofuse_eda/ru/)
  - The operator is "ИП ООО «Y EATS»", a foreign-enterprise LLC registered 16.01.2023, INN 310150022 (cl. 6.10).
  - The Partner sells the goods (cl. 1.1). Yandex "не является стороной" (is not a party) to User–Partner contracts (cl. 2.9). The user contracts directly with the Partner and/or courier (cl. 3.2).
  - Card payments: Yandex "действует по поручению Партнера и/или Доставщика" (acts on the instruction of the Partner and/or courier). Payment is taken by an authorised payment operator (cl. 5.1.1, 5.2, 5.4).
  - Cash paid directly to the Partner for Partner-delivered orders is outside the agreement (cl. 5.1.3).
  - Receipts are provided "в случаях, предусмотренных действующим законодательством" (in cases provided by law) in the "История заказов" (order history) section (cl. 5.3).
  - Split payment via a third-party "Поверенный" (attorney/agent) is possible (cl. 4.4).
- Yandex Eats' restaurant page (search summary) says payment to the restaurant is non-cash, paid weekly minus service fees. The page itself returned a captcha. [VENDOR, search summary] — [eda.yandex registration](https://eda.yandex/pages/registration/ru)
- Yandex Go started generating fiscal receipts inside its app in December 2023, for taxi. [MEDIA] — [spot.uz, 14.02.2024](https://www.spot.uz/ru/2024/02/14/delivery-receipt/)
- **Uzum.** [VENDOR] — [seller.uzum.uz](https://seller.uzum.uz/seller/agreement/uz/); [promo.tezkor.com/partners](https://promo.tezkor.com/partners/uz)
  - The Uzum Market seller offer is a "VOSITACHILIK SHARTNOMASI (OFERTA)" (intermediary agreement). The platform acts for the seller in consumer sales, and its commission is withheld from payouts. The listed version is dated 14.08.2026.
  - This document is **not** confirmed to govern Tezkor restaurants.
  - The Tezkor partner page says financial documents are kept in Didox. It lists card, cash, Uzum Bank and Uzum Nasiya as payment options.
- The R-Keeper (Busoft) and iiko integrations push aggregator orders into the venue POS (earlier notes). How those POSs mark aggregator-prepaid orders for fiscal purposes is not documented publicly. — see `Alipos va agregator POS modeli/uz_aggregators.md`

### Inferences
- [INFERENCE] For **prepaid Yandex Eats orders**, the most likely current practice is an electronic receipt from Yandex's payment operator or Marketplace, with the restaurant's TIN as komitent, visible in the app. The restaurant does not print its own receipt. For **cash orders delivered by the restaurant**, the restaurant must fiscalise. Rizqim POS must support both paths per order. **Confirm with a Yandex partner manager in writing.** **[LAWYER]**
- [INFERENCE] Uzum Tezkor likely follows the Uzum Market intermediary (commission-agent) model, with Uzum Bank or Click as payment operator. This is unverified.
- [INFERENCE] Because Annex 1a cl. 19–20 requires a contract between each Marketplace and each "user" business, a restaurant connected to Yandex should have this in its Yandex offer. Asking a pilot restaurant for its Yandex/Uzum offer PDF is the fastest way to confirm who fiscalises.

### Gaps
- The Yandex Eats **restaurant offer** for Uzbekistan and the Uzum Tezkor partner offer were not retrieved (Yandex captcha; no public Tezkor offer).
- It is not confirmed whether Yandex Eats or Uzum Tezkor are in the Marketplace registry. The komitent TIN on their receipts was not verified with a real receipt.
- No information was found on how aggregators fiscalise **their own fees** (delivery fee charged to the customer, service fee) versus the food amount.

---

## Q3. Personal data: localization, consent, transfer to POS vendors and aggregators, database registry, open partner API

### Takeaway
Since **27.03.2026** (ORQ-1125 of 26.03.2026), the Personal Data Law ZRU-547 **no longer requires all citizens' personal data to be stored in Uzbekistan**. Mandatory in-country storage now covers only:
- biometric data;
- genetic data;
- telecom operators' subscriber data.

Other data (name, phone, address, order history) may be stored abroad under one of these conditions:
- adequacy of the destination country;
- standard contractual clauses or binding corporate rules approved by the regulator;
- compliance with listed international standards.

The implementing lists have not been adopted yet, so commentators call this a "grey zone". The **database-registry duty (art. 20) now applies only to databases that must be localized**.

Two layers remain strict:
- (i) handing personal data or its processing to a third party (a POS vendor or aggregator) needs **written/electronic consent**, a contract with the subject, or a legal basis, plus written notice to the subject (art. 31);
- (ii) the **tax-API rules in VM 943 Annex 1a cl. 12³** still require a registered PD database and **servers and IP physically in Uzbekistan** for e-commerce platforms that use the tax API.

### Cited Findings
- **Art. 27¹ (ORQ-1125 edition, 27.03.2026)**, verbatim:
  - "Quyidagi shaxsga doir maʼlumotlar Oʻzbekiston Respublikasi hududida saqlanishi shart: jismoniy shaxslarning biometrik maʼlumotlari; jismoniy shaxslarning genetik maʼlumotlari; Oʻzbekiston Respublikasi hududida oʻz faoliyatini amalga oshirayotgan telekommunikatsiyalar operatorlaridagi ularning xizmatlaridan foydalanuvchi jismoniy shaxslarning maʼlumotlari."
  - In English: the following personal data must be stored in Uzbekistan: biometric data; genetic data; and data on users of telecom operators working in Uzbekistan.
  - Other data "Oʻzbekiston Respublikasi hududidan tashqarida saqlanishi va ularga ishlov berilishi mumkin" (may be stored and processed outside Uzbekistan) if one of the following holds:
    - the foreign state is recognised as giving equal protection (list set by the Cabinet of Ministers);
    - "standart shartnomaviy shartlar yoki majburiy korporativ qoidalar" (standard contractual terms or binding corporate rules) meeting the authorised body's requirements are adopted;
    - international standards from a list approved by the authorised body are complied with.
  - [CONFIRMED] — [lex.uz, ZRU-547 consolidated](https://lex.uz/uz/docs/-4396419); amending law [lex.uz, ORQ-1125](https://lex.uz/docs/8104580)
- The ORQ-1125 commentary says it took effect on publication (27.03.2026), whereas the 2021 version of 27¹ had a 3-month lead. **None of the implementing acts** (adequacy list, SCC/BCR requirements, standards list) had been adopted, so cross-border processing is a "серая зона" (grey zone). It also notes a possible conflict with the unchanged art. 15. [MEDIA, law-firm/IT-security blog] — [itts.uz, part 1](https://itts.uz/news/pdn_local_review1/)
- **Before March 2026.** Secondary sources describe the rule as requiring Uzbek citizens' personal data to be stored on servers in Uzbekistan and the database to be registered. [MEDIA] — [servercore.com](https://servercore.com/ru/blog/articles/uz-zakon-o-personalnyh-dannyh/); [serverspace.uz](https://serverspace.uz/about/blog/lokalizacziya-personalnyh-dannyh-v-uzbekistane-chto-vazhno-znat-biznesu-v-2026-godu/amp/)
- **Art. 20 part 1 (ORQ-1125 edition).** The State Register applies to databases "27¹-moddasining ikkinchi qismiga muvofiq Oʻzbekiston Respublikasi hududida saqlanishi shart boʻlgan" (that must be stored in Uzbekistan under art. 27¹ part 2).
  - Registration is by notification to the authorised body.
  - Changes must be notified within **10 calendar days**.
  - Exempt: databases holding only full names, data made public by the subject, one-time entry data, non-automated processing, and HR data.
  - [CONFIRMED] — [lex.uz, ZRU-547 art. 20](https://lex.uz/uz/docs/-4396419)
  - **Inconsistency:** art. 31 part 2 still lists the owner or operator's general duty "oʻz mulki boʻlgan va (yoki) ishlov berilayotgan shaxsga doir maʼlumotlar bazalarini roʻyxatdan oʻtkazishi" (to register the databases it owns or processes). [CONFIRMED; reconciliation is a LAWYER point]
- **Legal bases (art. 18).** These include consent, and "subyekt taraf boʻlgan shartnomani bajarish uchun" (performance of a contract to which the subject is party). [CONFIRMED]
- **Consent form (art. 21).** Consent may be in "har qanday shaklda" (any form) that lets the fact be confirmed. Special categories need written or electronic consent. [CONFIRMED]
- **Purpose limitation (art. 12).** Data may be used only for the purposes declared at collection. [CONFIRMED]
- **Transfer to third parties (art. 31 part 3).** The owner or operator may entrust processing to a third party:
  - with "subyektning yozma shakldagi, shu jumladan elektron hujjat tarzidagi roziligi" (the subject's written consent, including as an electronic document);
  - to perform a contract between the owner and the subject;
  - where the law provides.
  - Art. 31 part 2 also requires **notifying the subject in writing** when data is given to a third party, and appointing a responsible unit or officer.
  - [CONFIRMED] — [lex.uz, ZRU-547 art. 31](https://lex.uz/uz/docs/-4396419)
- **Cross-border transfer (art. 15, unchanged).** Allowed to countries with equal protection. Otherwise only with the subject's consent, or on constitutional, public-order or treaty grounds. [CONFIRMED]
- **Liability.** Art. 33 refers to Administrative Code art. 46² and Criminal Code art. 141². [CONFIRMED reference; fine amounts not read]
- **Sector rules:**
  - VM 943 Annex 1a cl. 12: a Marketplace keeps receipt data at least 5 years and protects users' personal data.
  - Cl. 12³ "б" and "в": a registered PD database plus in-country IP and processing hardware, for tax-API access.
  - Cl. 12⁷: no onward disclosure of personal or tax-secret data received from tax systems.
  - [CONFIRMED] — [lex.uz, VM 943](https://lex.uz/uz/docs/-4603329)
- **E-commerce Law, new art. 13¹ (ORQ-1173).** An order-aggregator operator must "буюртмалар агрегаторидан фойдаланувчиларнинг шахсга доир маълумотлари конфиденциаллигини таъминлаши, шахсга доир маълумотлар тўғрисидаги қонунчилик талабларига риоя этиши" (ensure the confidentiality of users' personal data and comply with personal-data law). In force about 12.12.2026. [CONFIRMED] — [lex.uz, ORQ-1173](https://lex.uz/ru/docs/8468724)
- **Market practice: Yandex Eats terms.** Cl. 3.6 and 4.2 take user consent to pass data to Partners and couriers "включая ее передачу (в т.ч. трансграничную)" (including its transfer, cross-border included). Data is kept "не менее трех лет" (at least three years) after the order. [VENDOR] — [yandex.uz/legal/termsofuse_eda](https://yandex.uz/legal/termsofuse_eda/ru/)

### Inferences
- [INFERENCE] **For Rizqim's open partner API** (passing orders to iiko, R-Keeper or Poster, or receiving Yandex/Uzum orders):
  - Customer name, phone and address passed to the venue to fulfil the order can rest on **contract performance (art. 18) plus art. 31 part 3 para. 3**.
  - To be safe, the customer offer should also contain explicit **electronic consent** to transfer to the venue, its POS vendor and couriers, including cross-border. Rizqim should **log it** (art. 31 part 2, proof of consent).
  - Send only what fulfilment needs (art. 12 purpose limitation). For example: first name, delivery address, and a masked or proxied phone where possible.
- [INFERENCE] **POS vendors' clouds abroad.** iiko and R-Keeper clouds are commonly hosted outside Uzbekistan, which is my assumption, not verified. Ordinary order data can legally be processed there after March 2026 only with:
  - consent under art. 15; or
  - one of the 27¹ part 3 bases (not yet operational).

  Consent in the customer offer is the practical route. **[LAWYER]**
- [INFERENCE] **Hosting.** If Rizqim becomes a Marketplace and/or uses the tax API, keep servers and egress IP **in Uzbekistan** and register the PD database anyway (VM 943 cl. 12³), even though general PD law no longer forces this. Registering the database is low-cost and also covers the art. 31 inconsistency.
- [INFERENCE] **Inbound data from aggregators.** Data from aggregators (Yandex/Uzum customer data in orders) arrives under their terms. Rizqim POS should use it only to fulfil the order, not for Rizqim marketing. That would exceed the original purpose (art. 12) and likely breach the aggregator's API terms.

### Gaps
- The text of the Cabinet adequacy list, the SCC/BCR requirements and the standards list: none had been adopted per itts.uz; not re-checked for September–October 2026.
- The fine amounts under Administrative Code 46² were not read.
- The current procedure and site for database registration (pd.gov.uz or the Personalisation Agency) was not checked, nor whether registration is still accepted voluntarily for non-localized databases.
- itts.uz part 2 (art. 3 and 20 changes) was not read.

---

## Q4. Competition rules for dominant aggregators (Yandex, Uzum) relevant to integrations and exclusivity

### Takeaway
Yandex Eats, Express24 and Uzum Tezkor are on the dominant digital-platform register (with Payme, Click, Uzum Market, OLX, Allplay and Yandex Go). Under **Competition Law ZRU-850 art. 18**, a dominant entity may not:
- impose unrelated or discriminatory terms;
- make a contract conditional on the counterparty **not dealing with other entities** (exclusivity);
- refuse to contract without reason;
- create barriers to market access.

A dominant **digital-platform operator** is additionally barred from restricting competition "by setting requirements to use particular data, technologies and digital products". This is the most direct hook against an aggregator forcing restaurants onto a specific POS or integration, or blocking a third-party POS such as Rizqim.

From about 12.12.2026, the E-commerce Law also obliges every order-aggregator operator to:
- keep its ranking **algorithms transparent**;
- publish its offer;
- give **30 days' notice** of rule changes.

### Cited Findings
- **ZRU-850 art. 18 part 1.** Prohibited abuses of a dominant position or superior bargaining power include:
  - "навязывание условий, не относящихся к предмету договора" (imposing terms unrelated to the subject of the contract) — as amended by ZRU-1044, in force 08.06.2025;
  - "установление дискриминационных условий" (setting discriminatory terms);
  - "согласие заключить договор лишь при условии … воздержания контрагента от приобретения товара у иных хозяйствующих субъектов или реализации товара другим хозяйствующим субъектам" (agreeing to contract only if the counterparty refrains from buying from, or selling to, other businesses);
  - "необоснованный отказ от заключения договора" (unjustified refusal to contract);
  - "создание препятствий для доступа на товарный или финансовый рынок" (creating barriers to market access).
- **ZRU-850 art. 18 part 2:** "Оператору цифровой платформы, признанному имеющим доминирующее положение, запрещается совершать действия, ограничивающие конкуренцию посредством установления требований об использовании сведений, технологий и цифровых продуктов" (a dominant digital-platform operator may not restrict competition by setting requirements to use particular data, technologies and digital products). The procedure for identifying such actions is set by the Cabinet of Ministers. [CONFIRMED] — [lex.uz, ZRU-850](https://lex.uz/uz/docs/6518383)
- **Dominance criteria.** VM 256 of 01.05.2024: network effects plus, for example, revenue above 100 000 BRV. Nine platforms are on the register, including Uzum Tezkor, Yandex Eats and Express 24. In May 2026 the Committee said thresholds were outdated and would be raised, and that a regime for **non-resident** transnational platforms was due by 01.10.2026. [MEDIA, search summary] — [spot.uz, 25.05.2026](https://www.spot.uz/ru/2026/05/25/digital-platforms); [gazeta.uz, 26.05.2026](https://www.gazeta.uz/ru/2026/05/26/platforms/)
- Earlier notes (`uz_aggregators.md`) record:
  - the 26.10.2024 dominance finding for YandexGo/Yandex Eats/Express24 and the 02.04.2025 finding for Uzum Tezkor;
  - the antitrust-compliance duty;
  - the 15-day public notice for price or term changes.
- **E-commerce Law art. 13¹ (ORQ-1173, signed 10.09.2026, published 11.09.2026, in force 3 months after publication).** It creates the "**buyurtmalar agregatori**" (order aggregator). The aggregator operator:
  - may set the final price of services for the buyer itself;
  - must check sellers' licences or permits;
  - must keep algorithms "таклиф қилиш ва тартиблаштириш алгоритмлари шаффофлигини" (transparent in how services are offered and ranked);
  - must keep reviews unaltered;
  - must publish its rules and offer;
  - must announce suspension or rule changes **at least 30 days** in advance.
- **Other ORQ-1173 changes:**
  - New art. 12 parts 5–6: the **seller is primarily liable** to the buyer, and the e-commerce operator is **subsidiarily liable**.
  - New art. 23: e-commerce operators and sellers must settle through **separate bank accounts**. Self-employed couriers may accept payment for third parties and keep cash against pre-placed funds.
  - [CONFIRMED] — [lex.uz, ORQ-1173](https://lex.uz/ru/docs/8468724); [spot.uz, 14.09.2026](https://www.spot.uz/ru/2026/09/14/marketplace-accounts)

### Inferences
- [INFERENCE] If Yandex or Uzum refused to connect a venue whose POS is Rizqim, while connecting iiko or R-Keeper venues, or required exclusivity from Rizqim marketplace venues, this would be arguable under art. 18 part 1 (refusal, discrimination, exclusivity) and part 2 (technology requirements). Complaints go to the Competition Committee (raqobat.gov.uz). Practical leverage, not a guaranteed remedy. **[LAWYER]**
- [INFERENCE] **Rizqim itself** becomes an "order aggregator operator" or "e-trading platform operator" under ORQ-1173 from about 12.12.2026, with algorithm-transparency, offer-publication, 30-day-notice and separate-account duties. If Rizqim grows past the dominance thresholds, the art. 18 restrictions would apply to its own partner-API terms. For example, it could not tie the marketplace to mandatory use of Rizqim POS.
- [INFERENCE] **Classification is unclear.** A food-delivery marketplace sells *goods*, which suggests an "e-trading platform" (art. 13). Its definition of "order aggregator" speaks of *services (works)*, which suggests art. 13¹. The duties are nearly identical, but art. 13 lets a platform also sell directly, and art. 13¹ lets the aggregator set the final price. **[LAWYER]**

### Gaps
- The Cabinet procedure for identifying a dominant platform's "technology requirement" abuses, and any 2026 Competition Committee cases on food aggregators (exclusivity, parity, integration refusal), were not found.
- I did not find the full text of the 25.05.2026 announcement or whether the non-resident platform regime was adopted by 01.10.2026.
- I did not check whether ORQ-1173's separate-bank-account rule requires a sub-law act (Central Bank) before 12.12.2026.
