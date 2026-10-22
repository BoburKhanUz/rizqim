# Choyxona: payment, fiscal-receipt and legal rules (Uzbekistan, status as of 21.10.2026)

Research date: 21 October 2026. This is not legal advice.

**Conventions**
- Language: English, with Uzbek terms kept as they are.
- **[ESKI]**: the source is dated before October 2024. Its content may have been overtaken by later changes.
- **[XULOSA]**: my own inference, not stated by any source.
- Abbreviations: "VM"/"PKM" is a Cabinet of Ministers resolution; "PF"/"UP" is a Presidential decree; "BHM"/"BRV" is the base calculation value; "MXIK"/"IKPU" is the product/service identification code in the national catalogue (tasnif.soliq.uz).

**How the primary texts were read**
- The following were read directly from lex.uz downloads. Most are current consolidated editions retrieved 19–21.10.2026:
  - the Tax Code (Uzbek Latin);
  - PKM 943 (edition with the 22.10.2026 changes);
  - PF-100 (with the PF-175 amendment);
  - catering rules PKM 75 Annex 2 (unofficial Russian translation);
  - ZRU-844.
- The general findings on VM 393 (POS integration ban from 22.10.2026, UzQR, Art. 221 / 227-1 fines) are in the earlier note and are **not repeated here**: `research_notes/Ovqatlanish formatlari POS talablari/tolov_fiskal_agregatorlar.md`.

---

## 1. Fiscal receipts for choyxona-specific items: MXIK codes, VAT, goods vs services

### Takeaway
Choyxona revenue lines map onto three catering MXIK groups introduced on 28.11.2022:
- **10202001**: catering menu (dishes, bread, tea and other non-alcoholic drinks);
- **10202002**: other services of a catering outlet (booking/reservation, deposit, delivery, disposable tableware, kids' room, parking and similar);
- **10202003**: catering for events and banquets, including event-related costs.

Two full 17-digit service codes were found in a buxgalter expert piece [ESKI]:
- **10202002003000002**, "other booking/reserving-a-place services";
- **10202002004000001**, "other payments, prepayment for a mandatory deposit".

No official code was found for "xizmat haqi" (service charge), "joy puli" (seat fee), kabinka rent, "own-products cooking service" or kazan rental. These must be confirmed in tasnif.soliq.uz, which was unreachable from this environment (connection reset / HTTP 503).

VAT on the receipt depends on the regime:
- **12%** standard;
- **6%** under the optional simplified regime (PF-100, 1.06.2026–1.01.2030);
- **no VAT line** for turnover-tax payers.

### Cited Findings
**MXIK groups and codes**
- In Nov 2022 the Tax Committee cut the recommended catering codes from 306 to 47, of which 26 are for dishes. The groups are:
  - 10202001, "Umumiy ovqatlanish taomnomasi" (catering menu);
  - 10202002, "other services at catering outlets";
  - 10202003, "catering (keytering) and other events in restaurants and banquet halls".
  The same source says delivery, disposable tableware, kids' play areas, **service charges ("xizmat haqi")** and room decoration are each placed in separate categories. It does not give their codes. Receipt item names should match menu names. — [spot.uz (oz), 28.11.2022] [ESKI](https://www.spot.uz/oz/2022/11/28/mxik/)
- The Russian version of the same news describes the groups as follows:
  - 10202001: fast food, starters, salads, soups, bread, main dishes, sauces/sides, desserts, combo meals and non-alcoholic drinks (tea, coffee);
  - 10202002: table booking, food delivery, disposable tableware, kids' room, paid parking and similar;
  - 10202003: food ordered for events plus related costs such as host and musicians.
  The full list was in an Excel attachment that was not retrieved. — [spot.uz (ru), 28.11.2022] [ESKI](https://www.spot.uz/ru/2022/11/28/catering-codes/)
- Dish codes are generic by category. For osh/palov, use "main dishes prepared by a catering enterprise" under "Joylashtirish va umumiy ovqatlanish xizmatlari → Umumiy ovqatlanish menyusi". Requests for a separate code per dish are rejected. — [buxgalter.uz (oz), 08.06.2023] [ESKI](https://buxgalter.uz/oz/publish/doc/text191011_tasnif_soliq_uz_saytida_tugri_mhik_qanday_topiladi)
- **10202001002000000**, "Fast food prepared in a catering outlet", has the unit "pcs (set)" and is recommended for sets. The same piece says dish IKPUs are shared and the specific dish goes in the item name. — [buxgalter.uz] (search excerpt; the article was not fetched in full)(https://buxgalter.uz/publish/doc/text199516_kakoy_ikpu_predpriyatiyu_obshchepita_primenit_k_setu)
- Codes for restaurant deposit and booking prepayment:
  - **10202002004000001**, "Другие виды оплат, предоплата за обязательный депозит" (other payment types, prepayment for a mandatory deposit), unit "sum";
  - **10202002003000002**, "Прочие услуги бронирования/резервирования места" (other booking/reserving-a-place services), units pcs, service (time) or service (sum).
  — [buxgalter.uz, 23.02.2023] [ESKI](https://buxgalter.uz/publish/doc/text188318_kak_oformit_cheki_predoplaty_i_prodaji_restoranu_3_prakticheskih_soveta)
- A vendor doc (Biznex) lists "codes" for osh (56.10.11.110.000.00.000), non (10.71.11.100.000.00.000), tea (10.83.13.000.000.00.000) and Coca-Cola. These are in an OKPD/CPA-style dotted format, **not** the 17-digit MXIK structure used in the official 10202xxx codes. They should **not** be relied on. — [docs.biznex.uz](https://docs.biznex.uz/tax-modul/mxik/)

**Receipt fields and correction receipts**
- Receipt codes must match the national catalogue. Each receipt line carries the MXIK, unit, quantity, price, VAT rate and VAT amount. — [lex.uz, PKM 943 Annex 1 cl. 7](https://lex.uz/ru/docs/4603329)
- **Correction receipts (cl. 38-1, added by PKM 23 of 23.01.2026)**:
  - Errors in MXIK, unit, quantity, sum or VAT can be corrected with a correction receipt, up to the deadline of the tax return for the month in which the discrepancy was found.
  - A correction receipt is also formed when the user earlier settled **without** the cash register, to fulfil the cash-register obligation.
  — [lex.uz, PKM 943 (22.10.2026 edition)](https://lex.uz/ru/docs/4603329)

**VAT rates**
- The standard VAT rate is **12%** (Tax Code Art. 258, in force since 1.1.2023). — [lex.uz, Tax Code Art. 258](https://lex.uz/docs/4674902)
- **Simplified VAT (PF-100 of 26.05.2026)**, from 1.06.2026 to 1.01.2030:
  - who may opt in: entities whose main activity is catering, trade or services. Large taxpayers and entities with 50% or more state share are excluded;
  - VAT rate: 6% on **all** turnovers;
  - profit tax: 0%, with no profit tax return;
  - no input-VAT credit, and no VAT benefits or partial-refund (cashback) scheme;
  - buyers may still credit the VAT;
  - switching in or out takes effect from the 1st of the month after notice;
  - the Tax Committee was to implement 6% VAT calculation and separate tracking in e-invoices and **purchase receipts** within one month.
  — [lex.uz, PF-100 cl. 5–8](https://lex.uz/docs/8236831); [spot.uz (oz), 27.05.2026](https://www.spot.uz/oz/2026/05/27/simplified-vat)
- Partial VAT refund for catering on the general regime: 40% of VAT paid regardless of cashless share, from 1.06.2026. — [PF-100 cl. 3(b)(ii)](https://lex.uz/docs/8236831)

**Choyxona definition**
- Catering rules cl. 3 define the types: restaurant, dining car, bar, café, seasonal café, cafeteria, canteen, **choyxona**, buffet, snack bar. The type is determined by the owner.
- A "**choyxona** is an enterprise where the main type of service is serving tea in teapots. In addition, choyxonas may have flatbread (lepeshka), national sweets, confectionery, bakery products and other buffet products in their range."
- A *zakusochnaya* (snack bar) may specialise as "plovnaya, shashlychnaya, samsa, …".
— [lex.uz, PKM 75 Annex 2 cl. 3 (ru, unofficial)](https://lex.uz/ru/docs/243233)

### Inferences
- [XULOSA] **Suggested MXIK mapping for a choyxona POS, to be verified in tasnif before go-live:**

  | Revenue line | Suggested group/code | Receipt treatment |
  |---|---|---|
  | Tea (choynak) and drinks | 10202001 class "coffee, tea and other non-marked non-alcoholic drinks" | sale |
  | Non / lepeshka | 10202001 class "bread and bakery" | sale |
  | Osh, shashlik and other dishes | 10202001 "main dishes" class | sale |
  | Booking or table reservation fee | 10202002003000002 | service, sale receipt |
  | Mandatory deposit taken in advance | 10202002004000001, or advance receipt (see Section 2) | — |
  | Service charge (xizmat haqi) and "joy puli" | 10202002 group, exact code unknown; spot.uz says service charge has its own category in the 10202002/10202003 structure | separate service line |
  | Kabinka/room rent within a banquet; cooking from the group's own products ("o'z mahsuloti bilan osh damlash"); kazan rental | 10202002 or 10202003 (events), exact codes unknown | service lines |

- [XULOSA] On VAT for these lines:
  - Service charge, joy puli, room rent and cooking services are **services**. When the outlet is a VAT payer they take the same VAT rate as the food (12%, or 6% simplified). No source suggests a reduced rate or exemption for these lines.
  - For "cooking from customer's products", only the fee for the service should be on the receipt. The customer's own raw products are not the outlet's sale.
  - If kabinka or premises rent is offered stand-alone (no food), it may be treated as a lease of property rather than a catering service. That would change the code and possibly the tax treatment. Ask a tax adviser.
- [XULOSA] POS design implications:
  - Every POS item, including non-food "service" items, needs a configurable MXIK, unit (pcs / service / sum / person / hour) and VAT rate.
  - Service charge should be generated as a **separate receipt line** with its own MXIK, not hidden inside dish prices. This also supports the Competition Committee transparency rule (Section 3).
  - The 6% regime means the VAT rate must be a per-business setting that switches from the 1st of a month.
- [XULOSA] Choyxona classification is a business decision. By the legal definition (tea plus bread and buffet items), a modern choyxona serving osh, kazan-kabob and banquets operates closer to a café or restaurant. The POS should not assume rule differences by "type" except where a rule names the type, such as self-service availability (Section 3).

### Gaps
- tasnif.soliq.uz could not be queried (connection reset / HTTP 503). The full 17-digit codes for the following are **not confirmed**: tea, bread, main dishes, service charge, room decoration, delivery, cooking service, room/kabinka rent and kazan rental.
- No official Tax Committee explanation was found on the receipt treatment of "joy puli", kabinka rent or cooking from the customer's products.
- No source was found on whether these services can be sold under 10202003 (events) versus 10202002 for small family gatherings (gap, ziyofat).

---

## 2. Advance (bo'nak) and credit (kredit / nasiya) receipts under VM 943, including from 22.10.2026

### Takeaway
VM 943 (Annex 1 cl. 7) still lists the receipt payment types: **sotuv, qaytarish, bo'nak, bo'lib to'lash, kredit** (sale, return, advance, instalment, credit). Advance, instalment and credit receipts carry **no QR code and no fiscal sign** and are not counted in tax reports. The 22.10.2026 edition changed the **payment-form** field (cash / bank card / QR / mixed), not these types.

Tax Committee explanations (via buxgalter) set the sequences:

| Flow | Step 1 | Step 2 |
|---|---|---|
| Booking deposit (advance) | **Bo'nak** receipt for the amount received | **Sotuv** receipt for the **full** order value, including the advance, at the event or delivery |
| Nasiya (credit) | **Sotuv** receipt for the full value at handover (fiscal, with QR, counted in VAT) | One **Kredit** (or bo'lib to'lash) receipt for each later repayment (non-fiscal) |

### Cited Findings
- Current text of PKM 943 Annex 1 cl. 7: "to'lov turi (sotuv, qaytarish, bo'nak, bo'lib to'lash, kredit). Bunda to'lovning bo'nak, bo'lib to'lash, kredit turlari bo'yicha shakllantirilgan cheklarda QR-kod va fiskal belgi mavjud bo'lmaydi". In English: payment type (sale, return, advance, instalment, credit); receipts of the advance, instalment and credit types have no QR code or fiscal sign. — [lex.uz, PKM 943, edition incl. 22.10.2026 changes](https://lex.uz/ru/docs/4603329)
- **Tax Committee explanation, advances:**
  1. On receiving an advance, issue a receipt with type "Avans/Predoplata" for the amount received. It has no QR or fiscal sign and is not reflected in tax reporting.
  2. On delivering the goods or service and receiving the final payment, issue a "Sale" receipt **for the full value**. It has QR and fiscal sign and is counted in the VAT report. The timing follows Tax Code Art. 242.
  — [buxgalter.uz, 18.10.2023] [ESKI](https://buxgalter.uz/publish/doc/text193843_kak_prinimat_plateji_avansom_i_po_kreditu_razyasneniya_nalogovogo_komiteta)
- **Tax Committee explanation, instalment/credit:**
  1. At handover, issue a "Sale" receipt for the full value, with QR, auto-included in the VAT report.
  2. With each payment toward the debt, issue a "Rassrochka/Kredit" receipt for the amount received. It has no QR or fiscal sign and is not in tax reporting.
  — [buxgalter.uz, 18.10.2023] [ESKI](https://buxgalter.uz/publish/doc/text193843_kak_prinimat_plateji_avansom_i_po_kreditu_razyasneniya_nalogovogo_komiteta)
- Worked example: a 10 mln contract with a 5 mln prepayment gives an advance receipt of 5 mln, then a sale receipt of **10 mln, not 5 mln**. Advance receipts are not in tax reports, so there is no double count. — [buxgalter.uz, 07.07.2023] [ESKI](https://buxgalter.uz/publish/doc/text191727_kakoy_chek_kkt_vydat_pri_poluchenii_avansovyh_platejey_ili_predoplaty_v_uzbekistane)
- **Refund of an advance** (e.g. a cancelled booking): the law is silent and there is no official explanation. The author recommends **not** issuing a "Return" receipt for a refunded advance, because advance receipts are outside the tax base. The money is returned against supporting documents:
  - the customer's application;
  - a copy of the customer's ID;
  - a return act;
  - a cash outgoing order (RKO) if refunded in cash;
  - the original advance receipt.
  For returns on *sale* receipts there are separate constraints (written request; cashback cases). — [buxgalter.uz, 07.07.2023] [ESKI](https://buxgalter.uz/publish/doc/text191727_kakoy_chek_kkt_vydat_pri_poluchenii_avansovyh_platejey_ili_predoplaty_v_uzbekistane)
- **Restaurant banquet deposits**, from three experts:
  - At prepayment, issue an "Avans" receipt (quantity 1, price equal to the prepayment).
  - After the event, issue a "Sale" receipt listing all dishes and services at actual quantities and prices. Its total includes the advance.
  - Advance amounts are excluded from turnover tax and VAT under Art. 242.
  - **Conflicting view (Tyan):** issue a "Sale" receipt for the deposit item at prepayment, and later show the deposit as a discount on the final receipt.
  - Suggested codes: 10202002004000001 (mandatory deposit) or 10202002003000002 (booking).
  — [buxgalter.uz, 23.02.2023] [ESKI](https://buxgalter.uz/publish/doc/text188318_kak_oformit_cheki_predoplaty_i_prodaji_restoranu_3_prakticheskih_soveta)
- buxgalter also notes that advance and credit receipts are barely regulated in cl. 7: only the "no QR / no fiscal sign" rule. Some Tax Committee explanations were published only on its Telegram channel. — [buxgalter.uz (search summary)](https://buxgalter.uz/publish/doc/text198112_chto_doljno_byt_otrajeno_v_cheke_onlayn-kkt)
- The Tax Code Art. 242 reference (moment of sale) is in the current Tax Code. — [lex.uz, Tax Code](https://lex.uz/docs/4674902)
- From **22.10.2026**, the payment-form field reads "naqd pul, bank kartalari, QR-kod orqali to'lov yoki aralash" (cash, bank cards, QR-code payment or mixed). This comes from VM 393 (see the earlier note); there is no change to the payment *types*. — [lex.uz, PKM 943 cl. 7](https://lex.uz/ru/docs/4603329)
- The offline limit is 48 hours: if real-time sending is impossible, receipts may be issued offline, and the first offline receipt must reach the operator within 48 h (cl. 31). — [lex.uz, PKM 943](https://lex.uz/ru/docs/4603329)

### Inferences
- [XULOSA] **Choyxona booking deposit (bron / zakalat), recommended sequence:**
  1. Advance (bo'nak) receipt for the deposit, with payment form cash, card or QR.
  2. On the day: one **Sale** receipt for all dishes, services, xizmat haqi and joy puli at full value. The payment portion is split into "already paid by advance" and today's cash, card or QR, the latter marked "mixed" if more than one method.
  3. If the booking is cancelled and the deposit is kept as a cancellation fee (forfeit), [XULOSA] the kept amount has become a realised service (booking service, 10202002003000002). It probably needs a Sale receipt for that amount. No source covers this; it needs confirmation.
  4. If the deposit is refunded, there is no receipt per buxgalter's view [ESKI]. The POS should log the refund with documents.
- [XULOSA] **Nasiya for regulars** (e.g. "daftarga yozib qo'y"):
  1. At the time of serving, a **Sale** receipt (fiscal) for the full bill. The receipt must not be delayed until payment.
  2. Each repayment is a **Kredit** receipt.
  The POS needs per-customer receivables (debtor ledger) linked to the original sale receipt ID, partial repayments, and reminders. A sale-type receipt has to show a payment form; how a fully unpaid sale is represented in the payment-form field is not documented (see Gaps).
- [XULOSA] Because advance and credit receipts are non-fiscal, the POS's internal ledger is the only place where deposit balances live. It must reconcile advance-in against sale offset per booking. The new VM 393 ban on non-integrated "order accounting software" makes it safer for this ledger to sit inside the registered virtual cash register flow.

### Gaps
- No official text explains how the final Sale receipt technically records an advance offset: whether there is a separate "advance offset" payment field, or whether the advance portion is shown as cash/card. The Russian 54-FZ concept of "zachet avansa" has no confirmed Uzbek equivalent in the sources found.
- No 2024–2026 soliq.uz / my.soliq.uz FAQ was found that updates the 2023 explanations. All detailed explanations found are **[ESKI] (2023)**. They remain consistent with the 22.10.2026 text of cl. 7, but this is unconfirmed by a newer official source.
- No guidance was found on a forfeited (non-refunded) deposit, or on the payment form for a fully deferred (credit) sale receipt.

---

## 3. Service charge (xizmat haqi), "joy puli", deposits: Competition Committee rules and cases

### Takeaway
The Competition Committee (Raqobatni rivojlantirish va iste'molchilar huquqlarini himoya qilish qo'mitasi) warned **all catering types, explicitly including choyxona,** on 21.11.2025. The warning covers:
- forced 10–20% service charges and forced deposits;
- the requirement that prices, service order and the waiter-service fee be disclosed **in advance**;
- the requirement that self-service be possible where the outlet type allows it;
- the ban on charging for services not provided.

A service charge is not banned as such. Per buxgalter's editorial reading, it is lawful when actually provided and announced in advance with the exact rate.

In May 2026 the Committee ordered a Karakalpakstan café to refund more than 5.37 mln UZS of undisclosed 10% service charge. No fine amounts were reported. No source mentions "joy puli" specifically.

### Cited Findings
- **Committee warning, 21.11.2025:**
  - Complaints say restaurants and cafés add a 10–15% (up to 20%) xizmat haqi on top of menu prices and demand deposits in advance.
  - The Committee warns all catering businesses not to force consumers into additional services. They must inform customers in time about menu prices, service procedures and the waiter-service fee.
  - Legal bases cited: catering rules (PKM 75), which list the types including **choyxona**; the Law on Consumer Rights Protection, Arts. 4, 19 and 21 (information and free choice; ban on imposing extra goods or services and on charging for services not provided).
  - Hotline 1159.
  — [raqobat.gov.uz, 21.11.2025](https://raqobat.gov.uz/uz/raqobat-qomitasi-respublikamizdagi-barcha-turdagi-umumiy-ovqatlanish-korxonalarini-ogohlantiradi/); [spot.uz (oz), 21.11.2025](https://www.spot.uz/oz/2025/11/21/restaurants-warned)
- The Committee urged outlets not to insist on waiter service. Cafés and canteens must make self-service possible. In restaurants waiters serve; in cafés and buffets both waiter service and self-service are allowed; canteens are self-service. The quoted principle: "Не допускается навязывание дополнительных товаров и услуг" (imposing additional goods and services is not allowed). — [spot.uz (ru), 21.11.2025](https://www.spot.uz/ru/2025/11/21/tips-catering)
- **buxgalter editorial view (21.11.2025):**
  - The warning is **not** a ban on service charges. A charge may be included in the bill if (a) the service is actually provided and (b) the guest was told in advance that service is paid, with the exact rate.
  - A charge hidden from the menu, counter or public offer, or revealed only at payment, is unlawful.
  - Forcing a waiter fee where self-service is the format is a violation.
  - No fines are stated, and the tax or receipt treatment is not discussed.
  — [buxgalter.uz, 21.11.2025](https://buxgalter.uz/publish/doc/text211989_mnenie_redakcii_o_chem_predupredil_zavedeniya_obshchepita_komitet_po_konkurencii)
- **Case, published 18.05.2026 (violations Jan–Feb 2026):** a catering outlet (café) in the Republic of Karakalpakstan:
  - did not clearly show full prices on the menu;
  - offered no self-service option;
  - charged a 10% xizmat haqi without prior notice.
  More than **5,370,000 UZS** was collected without justification. The Committee ordered the violations fixed, the money refunded to customers and no repetition. No fine was reported. — [raqobat.gov.uz, 18.05.2026](https://raqobat.gov.uz/uz/raqobat-qomitasiga-savdo-qurilish-umumiy-ovqatlanish-korxonalari-va-reklama-xizmatlari-sohasida-tushgan-murojaatlari-ijobiy-hal-etilib-istemolchilik-huquqlari-tiklanmoqda/)
- **Catering rules cl. 8** require the outlet to display, visibly and accessibly:
  - its full name and STIR;
  - a special QR code;
  - working hours;
  - the list of products;
  - portion weight and volume;
  - **the list of services and conditions of their provision**;
  - **prices and payment terms for services**.
  — [lex.uz, PKM 75 Annex 2 cl. 8](https://lex.uz/ru/docs/243233)
- **Catering rules cl. 14:**
  - Payment methods may be prepayment, pay-after-selection, pay-after-eating or self-settlement.
  - With waiters, payment is made on the waiter's bill or the cash-register receipt.
  - Every catering outlet must have a bank-card payment terminal.
  — [lex.uz, PKM 75 Annex 2 cl. 14](https://lex.uz/ru/docs/243233)
- Search coverage of the warning also said that "imposing service charges and mandatory deposits contradicts consumer law" and that the fee is unlawful if hidden. A new Law "On Competition" provides fines on officials of 10–20 BRV, but this is a general competition-law norm, not specific to catering service charges. — [daryo.uz (ru)](https://daryo.uz/ru/10k5BLmvg/); [podrobno.uz](https://podrobno.uz/cat/obchestvo/restorany-ne-vprave-navyazyvat-servisnyy-sbor-za-obsluzhivanie-bez-alternativy-komitet-po-zashchite-/); [aktualno.uz](https://aktualno.uz/ru/a/9764-v-uzbekistane-zakon-o-konkurencii-prinyat-v-novoi-redakcii) (search summaries; not fetched in full)

### Inferences
- [XULOSA] **"Joy puli"** (per-person or per-table seat fee, common in choyxonas) and **kabinka/xona puli** fall under the same consumer-law logic as xizmat haqi:
  - lawful only if announced in advance (menu, signage, booking confirmation) with the exact amount;
  - tied to a real service (seat, room, setting, cleaning);
  - not imposed where the customer had no choice.
  No source addresses joy puli directly.
- [XULOSA] POS requirements derived from this:
  - (a) service charge and joy puli are configurable, displayed rules (percent or fixed per person/room/hour). They are printed on the pre-bill and the fiscal receipt as separate lines, and shown in the digital/QR menu;
  - (b) the waiter can switch them off per table, with a reason logged;
  - (c) a "self-service / no service charge" mode exists;
  - (d) booking confirmations (Telegram or SMS) state the deposit, joy puli and service charge terms in advance;
  - (e) a refund flow exists for disputed charges.
- [XULOSA] A **deposit** that is a prepayment toward the final bill seems acceptable if disclosed. A *non-refundable minimum spend* deposit is the risk area the Committee flagged.

### Gaps
- No Committee decision or court case specifically against a **choyxona**, or about **joy puli**, was found for 2023–2026.
- Fine amounts were not identified: no Code of Administrative Responsibility (MJtK) article or BHM amount for consumer-rights violations in catering. The cases found report only refunds and orders.
- No source was found on whether the Committee treats a fixed "joy puli" differently from a percentage service charge.

---

## 4. Tax regimes typical for choyxonas: YaTT vs MChJ, turnover tax, VAT 12% vs 6%, thresholds, exemptions

### Takeaway
From 1.01.2026 (ZRU-1108), the current Tax Code gives:

| Payer | Turnover tax rate | Condition |
|---|---|---|
| YaTT (sole proprietor) and self-employed | **1%** | income up to 1 bn UZS |
| Legal entities (MChJ) in catering | **4%** | general rate; catering has no special rate |

Above the threshold, or by choice, the business pays VAT at 12% plus profit tax, or opts into the PF-100 simplified VAT (6%, 0% profit tax) from 1.06.2026 to 1.01.2030.

The thresholds conflict:
- the Tax Code text on lex.uz still says **1 bn UZS**;
- PF-100 sets the threshold for moving to the general regime at **12,000 BHM** from 1.06.2026 (about 4.9 bn UZS at BHM 412,000).

No exemption from the online cash register, terminal or QR requirements was found for small or mahalla choyxonas. The only carve-outs are the PF-5813 list of remote and hard-to-reach settlements and vending machines.

### Cited Findings
**Tax Code**
- **Art. 461 (as amended by ZRU-1108, in force 1.01.2026).** Turnover-tax payers are:
  1. legal entities whose total income in the tax period does not exceed **1 bn UZS**;
  2. YaTT and self-employed persons whose sales income does not exceed **1 bn UZS**.
  Turnover tax does not apply to, among others:
  - importers;
  - excise producers;
  - **stationary outlets retailing alcohol including beer** (item 9);
  - markets;
  - **non-commercial organisations including budget organisations** (item 13);
  - jewellery businesses.
  — [lex.uz, Tax Code Art. 461](https://lex.uz/docs/4674902)
- **Art. 467 rates (ZRU-1108, from 1.01.2026):**
  - all sectors: 4%;
  - retail: 4% in cities with 100k+ population, 2% in other settlements, 1% in hard-to-reach or mountain districts; tobacco 4%;
  - procurement organisations: 4% of turnover or 25% of gross income;
  - organisations of disabled persons and similar: 0%;
  - **YaTT and self-employed: 1%**.
  **No separate catering rate.** Parts 2–4 of Art. 467 were repealed from 1.01.2026 (ZRU-1014). — [lex.uz, Tax Code Art. 467](https://lex.uz/docs/4674902)
- **Art. 462.** Legal entities under the 1 bn threshold may choose to pay turnover tax instead of VAT and profit tax. — [lex.uz, Tax Code Art. 462](https://lex.uz/docs/4674902)
- The 1% rate for YaTT and self-employed up to 1 bn UZS was confirmed, along with the cancellation of the fixed personal income tax for YaTT. Above 1 bn UZS the business must move to VAT and profit tax, or may do so voluntarily below it. — [buxgalter.uz (oz), 29.12.2025](https://buxgalter.uz/oz/publish/doc/text212722_sk-2026_yakka_tartibdagi_tadbirkorlar_va_uzini_uzi_band_qilgan_shahslar_uchun_aylanmadan_olinadigan_soliq_buyicha_uzgarishlar)
- Standard VAT rate is 12% (Art. 258). — [lex.uz, Tax Code](https://lex.uz/docs/4674902)

**PF-100 (26.05.2026)**
- **cl. 2:** from 1.06.2026, "the threshold for transition to the general tax regime is set at **twelve thousand BHM**".
- **cl. 3(a), hotels and catering:**
  - (i) cash payments are allowed for alcohol and tobacco;
  - (ii) as amended by **PF-175 of 27.08.2026**: digital marking codes of alcohol, beer, tobacco, **water and soft drinks** purchased by these entities are deemed sold for final consumption.
- **cl. 3(b), catering:**
  - simplified same-day hiring of certain staff through the labour authority's mobile app;
  - 40% VAT cashback;
  - rent for land adjacent to catering, trade or service buildings set at 1× the land tax.
- **cl. 5–8:** 6% simplified VAT (details in Section 1).
— [lex.uz, PF-100](https://lex.uz/docs/8236831); [spot.uz (oz), 27.05.2026](https://www.spot.uz/oz/2026/05/27/simplified-vat)

**Cash register, terminal and QR obligations**
- **PKM 943 cl. 3:** businesses with cash revenue had to move to online cash registers or virtual cash registers after the schedule deadlines. The only exceptions are the cases in **PF-5813 cl. 4 para 2** and sales through vending machines. — [lex.uz, PKM 943 Annex 1 cl. 3](https://lex.uz/ru/docs/4603329)
- PF-5813 cl. 4 provides an annually approved list of **remote and hard-to-reach settlements** where the transition schedule does not apply. buxgalter could not find such a list publicly and notes that PKM 943 has **no list of entity types** allowed to work without an online cash register. — [buxgalter.uz] [ESKI](https://buxgalter.uz/publish/doc/text163978_pochemu_net_isklyucheniy_pri_perehode_na_onlayn-kkm)
- **All** catering outlets must have a card terminal (catering rules cl. 14) and must use online cash registers or virtual cash registers with 2D scanners (cl. 13). — [lex.uz, PKM 75 Annex 2](https://lex.uz/ru/docs/243233)
- Payment QR requirements: YaTT and self-employed must accept the special platform QR from 1.01.2026; legal entities must accept UzQR from 1.07.2026 (catering rules cl. 25 liability). — earlier note; [lex.uz, PKM 75](https://lex.uz/ru/docs/243233)
- A press article says every registered business accepting cash from the public must have an online cash register, and that mahalla tax inspectors check it. The text is partly garbled. — [press.natlib.uz (Yangi O'zbekiston)](https://press.natlib.uz/download.php?id=118369) (search summary only)

### Inferences
- [XULOSA] The typical small mahalla choyxona is a **YaTT on 1% turnover tax** (no VAT on receipts) while under the threshold. Larger choyxona-restaurants (MChJ) are on **4% turnover tax**, on **12% VAT + profit tax**, or on **6% simplified VAT**. The POS must support the following:

  | Regime | Receipt VAT |
  |---|---|
  | Turnover tax | no VAT line ("QQSsiz") |
  | General regime | 12% |
  | Simplified regime | 6% |

  It must also allow switching regime from the 1st of a month, and keep revenue counters so the owner sees the approach to the 1 bn / 12,000 BHM threshold.
- [XULOSA] The **1 bn (Tax Code) vs 12,000 BHM (PF-100) threshold conflict** is unresolved in the sources. The decree may be implemented through a Tax Code amendment that was not yet reflected, or the lex.uz Art. 461 text may refer to a different threshold concept. The POS should make the threshold configurable.
- [XULOSA] There is **no special regime for "milliy taomlar" or mahalla choyxonas** in any text found. Small size gives no exemption from the cash register, terminal or QR requirements, apart from the remote-settlement list, which appears unpublished.

### Gaps
- No source was found reconciling the Art. 461 "1 bn" text with the PF-100 "12,000 BHM" threshold. The BHM value after 1.09.2026 was not verified: one search summary said 440,000 UZS, which is unconfirmed.
- The current remote/hard-to-reach settlements list under PF-5813 was not found.
- No statistics were found on the share of choyxonas registered as YaTT vs MChJ.

---

## 5. P2P transfers to the owner's personal card: legal status and enforcement 2025–2026

### Takeaway
No norm was found that explicitly permits taking sales revenue to an owner's personal card. Every sale must be receipted through a registered cash register, and revenue missing from accounting records counts as **concealment of the tax base**. That means back taxes plus a fine of **20% of the hidden base** (Tax Code Art. 223), alongside the 5 mln UZS fine for selling without a receipt (Art. 221 part 1).

Since May 2026 the Tax Committee has been sending letters to entrepreneurs with large P2P inflows. A draft rule obliging banks to report large P2P inflows was opposed by the CBU (July 2026). Its adoption is unconfirmed as of October 2026.

### Cited Findings
- **Tax Code Art. 223:** concealment or understatement of the tax base carries a fine of **20%** of the concealed amount, and taxes are assessed on that amount. Concealment includes "not reflecting sales revenue in accounting registers" and "unlawful modification of the cash-register fiscal memory program". — [lex.uz, Tax Code Art. 223](https://lex.uz/docs/4674902)
- From 13.05.2026 the Tax Committee has been analysing P2P receipts of outlet owners, including cards of heads of legal entities. People with many P2P transactions, reportedly above 500 mln UZS a year of card turnover, are summoned. Letters cite 11-month 2025 P2P turnover and state the tax base was concealed; the consequences are back taxes plus a 20% fine. — [gazeta.uz (oz), 13.05.2026](https://www.gazeta.uz/oz/2026/05/13/p2p/); [spot.uz (oz), 13.05.2026](https://www.spot.uz/oz/2026/05/13/p2p-raids)
- **Draft bank-reporting rule (June 2026):** banks would report large P2P inflows to tax authorities. Thresholds per the search summary: more than 1,000 BHM per operating day for legal entities, and more than 500 BHM for YaTT and self-employed. Transfers from close relatives and between one's own cards are excluded. — [gazeta.uz (oz), 24.06.2026](https://www.gazeta.uz/oz/2026/06/24/p2p/)
- The CBU said the draft contradicts the Constitution (Art. 41). — [gazeta.uz (oz), 09.07.2026](https://www.gazeta.uz/oz/2026/07/09/p2p/). The Tax Committee replied that the draft gives no new powers and does not lift bank secrecy. — [spot.uz (oz), 11.07.2026](https://www.spot.uz/oz/2026/07/11/p2p-transfers); [spot.uz (oz), 24.06.2026](https://www.spot.uz/oz/2026/06/24/tax-note)
- Some fintech services began asking users for the purpose of P2P transfers (March 2026). — [spot.uz (oz), 03.03.2026](https://www.spot.uz/oz/2026/03/03/p2p-reason)
- **Tax Code Art. 221 fines:**
  - part 1: **5 mln UZS** for selling without the mandatory receipt or refusing terminal/QR payment;
  - part 3: **20 mln UZS** for using a terminal or special QR registered to another taxpayer.
  — [lex.uz, Tax Code Art. 221](https://lex.uz/ru/docs/4674902) (detail in the earlier note)
- PKM 943 Annex 1 cl. 6: a fiscal receipt is required for payments via terminal **and via electronic payment systems** for goods and services bought on site. — [lex.uz, PKM 943](https://lex.uz/ru/docs/4603329)

### Inferences
- [XULOSA] For a **YaTT** the personal card and the business are the same person. A P2P payment received *with* a fiscal receipt issued is arguably compliant on revenue recognition, but it is still risky:
  - the receipt's payment form ("bank card" / "QR") would not match an acquiring record;
  - it bypasses the mandatory terminal/QR acceptance;
  - P2P inflows not matched by receipts are exactly what the Tax Committee is looking for.
- [XULOSA] For an **MChJ**, revenue paid to the director's or owner's personal card is not company revenue in the bank account. It is the scenario named in the Tax Committee's letters, with Art. 223 exposure.
- [XULOSA] POS design:
  - Do **not** offer a "transfer to card" payment method as a normal option.
  - If it exists for a transition period, force a fiscal receipt and flag the payment in reports.
  - Provide dynamic UzQR or Click/Payme QR, which is as fast as P2P and fiscalised.
  - The POS can include an "approximate P2P exposure" reminder in owner education, but no automated P2P tracking is possible from the POS side.

### Gaps
- No explicit statutory provision was found, for or against, on a YaTT accepting customer payments to a personal (non-business) card. Whether a YaTT may designate a personal card as a business card is not clarified in the sources.
- The final status of the bank-reporting resolution after July 2026 is unknown.
- No P2P enforcement case specific to choyxonas or catering was found.

---

## 6. Alcohol, hookah, smoking and other licensing relevant to choyxonas

### Takeaway
**Alcohol**
- A catering outlet may sell alcohol only if it is a **legal entity**, after sending a **notification** to the tax authority (ZRU-844 Art. 32 as amended 20.02.2025). A YaTT choyxona therefore cannot legally sell alcohol.
- Outlets selling on tap must have verified measuring equipment.
- Since 1.06.2026 catering may take cash for alcohol and tobacco.
- From 1.01.2027 the fees for the right to sell alcohol (retail and catering) are merged at 10% of BHM (PF-100).

**Hookah and smoking**
- Hookah is effectively **not allowed in a catering outlet**. Tobacco and nicotine-device use is banned in catering premises.
- Designated hookah places may exist only in **isolated buildings providing exclusively hookah services**.
- Outdoor smoking spots outside buildings are allowed.
- Staff under 21 may not sell alcohol, tobacco or nicotine devices.

### Cited Findings
**ZRU-844 (Law on restricting the distribution and consumption of alcohol and tobacco products, 24.05.2023)**
- The law replaced the 2018 hookah law ZRU-478, which lost force 26.08.2023. — [lex.uz, ZRU-478 note](https://lex.uz/uz/docs/3803255); [lex.uz, ZRU-844](https://lex.uz/ru/docs/6472104)
- **Alcohol consumption:** drinking alcohol is banned in public places. The ban "does not apply to trade and service facilities, including catering enterprises, **in which on-tap sale of alcohol is permitted**, and to places of weddings, anniversaries and other celebrations and rites". — [lex.uz, ZRU-844 Art. 24](https://lex.uz/ru/docs/6472104)
- **Tobacco:** use of tobacco products and nicotine devices is banned in public places, "including in trade and service facilities, including catering enterprises". It is also banned to provide premises, or tobacco products and devices, for use there.
- **Hookah (Art. 25):** "Specially designated places for hookah smoking may be organised in isolated buildings, structures and facilities where **exclusively** hookah services are provided, meeting technical-regulation documents and equipped with ventilation." The definition of a hookah covers tobacco and tobacco-free mixtures.
— [lex.uz, ZRU-844 Arts. 3, 24, 25](https://lex.uz/ru/docs/6472104)
- **Art. 32 (as amended by ZRU-1034 of 20.02.2025):**
  - "Retail trade in alcohol and the sale of alcohol by catering enterprises is carried out **by legal entities** by sending a **notification** to the tax authorities about the start of this activity."
  - Catering enterprises selling alcohol on tap must have trade equipment and state-verified measuring beakers and mugs.
  - Retail of alcohol requires online cash registers or virtual cash registers and card terminals.
  — [lex.uz, ZRU-844 Art. 32](https://lex.uz/ru/docs/6472104)
- **Catering rules (PKM 507 of 15.08.2024 amendments):**
  - cl. 5: the outlet must not allow alcohol, tobacco or nicotine devices to be sold by staff **under 21**;
  - cl. 8-1: the outlet "must prohibit the consumption of tobacco and alcohol products and nicotine devices in places of production and sale of catering products", must post warning signs, and must warn smokers and then report them to the police;
  - cl. 8-2: special smoking places may be set up **outdoors, outside buildings**, with non-combustible bins and signs.
  — [lex.uz, PKM 75 Annex 2 (ru, unofficial)](https://lex.uz/ru/docs/243233)
  - Note: the unofficial Russian wording of cl. 8-1 mentioning *alcohol* conflicts with ZRU-844 Art. 24, which allows drinking in catering outlets licensed for on-tap sale. The official Uzbek text was not checked.
- **PF-100:**
  - 3(a)(i): from 1.06.2026 hotels and catering may accept **cash** for alcohol and tobacco;
  - 3(a)(ii), as amended by PF-175 of 27.08.2026: marking codes of alcohol, beer, tobacco, water and soft drinks purchased by them count as sold for final consumption;
  - 4(a): from **1.01.2027** the fee for the right to retail alcohol and the fee for catering enterprises' right to sell alcohol are merged, at **10% of BHM**. The period basis, such as per year or per outlet, was not stated in the excerpt read.
  — [lex.uz, PF-100](https://lex.uz/docs/8236831)
- Turnover tax does not apply to stationary outlets retailing alcohol including beer (Tax Code Art. 461 part 2 item 9). Whether a catering outlet selling alcohol on tap counts as such an outlet is not stated. — [lex.uz, Tax Code](https://lex.uz/docs/4674902)
- A 2018 draft on hookah fines (1/3 of minimum wage) was found, but its adoption is unknown. — [norma.uz] [ESKI](https://www.norma.uz/proekty_npa/za_kalyany_i_veyping_-_shtraf_ih_reklama_zapreshchena)

### Inferences
- [XULOSA] For a **YaTT choyxona**:
  - **no alcohol sales**, since the notification route is for legal entities only. Guests bringing their own alcohol is a separate question; ZRU-844 Art. 24 allows drinking at "places of weddings, anniversaries and other celebrations", which may cover banquet use;
  - **no hookah** inside the catering outlet.
- [XULOSA] POS design:
  - an alcohol category that can be enabled only for legal-entity tenants with an "alcohol notification sent" flag;
  - measuring units (ml / 50 g / 100 g) for on-tap alcohol;
  - an age-check prompt;
  - no hookah menu items by default (configurable for separate hookah-only venues).
  - Since PF-175 deems marked drinks (water, soft drinks, beer, alcohol, tobacco) bought by catering businesses as already sold for final consumption, a catering POS likely does **not** need to scan marking codes at the table for these. This should be confirmed against the current cash-register requirements.

### Gaps
- The sanitary licensing/permits for opening a choyxona (SES conclusion vs notification regime, fire safety) were not researched in depth. No choyxona-specific permit was found.
- Fine amounts for hookah or smoking violations in catering under the current MJtK were not retrieved.
- The official Uzbek wording of catering rules cl. 8-1 (alcohol mention) was not checked.
- The PF-100 cl. 4(a) fee basis (annual or quarterly; per outlet) was not confirmed.

---

## 7. Mahalla choyxonas owned by the mahalla or community: legal form and commercial status

### Takeaway
No legal source was found on the ownership or legal form of "mahalla choyxonasi" buildings. It is also unknown whether a mahalla (fuqarolar yig'ini) itself may run a choyxona as a commercial catering business, or how such premises are leased to entrepreneurs. Whoever *operates* a choyxona that sells food or tea to the public is a catering business under PKM 75, which applies "regardless of form of ownership". The cash-register, terminal and QR, consumer and tax rules above apply to that operator.

### Cited Findings
- Catering rules cl. 1: "These Rules regulate the basic requirements for the work of **all business entities** in the field of public catering … **regardless of form of ownership and departmental affiliation**." — [lex.uz, PKM 75 Annex 2 cl. 1](https://lex.uz/ru/docs/243233)
- Tax Code Art. 461 part 2 item 13: turnover tax does not apply to **non-commercial organisations** (incl. budget organisations). — [lex.uz, Tax Code](https://lex.uz/docs/4674902)
- Traditionally each mahalla had its own choyxona on its guzar, serving as the mahalla's social-cultural centre. — [zenodo (academic paper, 2026)](https://zenodo.org/records/18845077) (search summary only)
- An architect argues "the concept of the choyxona has completely changed": it is now a place to order food and book a table in advance. He calls for free guzars for elders. — [daryo.uz, 17.11.2025](https://daryo.uz/2025/11/17/choyxona-tushunchasi-butunlay-ozgarib-ketdi-arxitektor-shuhrat-abdullayev-otaxonlar-uchun-tekin-guzarlar-kerakligi-haqida/) (search summary; not fetched in full)
- PF-100 cl. 3(b)(v): rent for land adjacent to catering, trade and service buildings is set at 1× the land tax. This is relevant if a choyxona uses an outdoor area (supa or tapchan zone). — [lex.uz, PF-100](https://lex.uz/docs/8236831)

### Inferences
- [XULOSA] There are three likely set-ups, none confirmed by a source:
  - (a) a mahalla-owned building leased to a YaTT, who is the taxpayer and needs their own cash register;
  - (b) a community or waqf-type free choyxona with no sales, which is outside fiscal rules if nothing is sold;
  - (c) a non-commercial organisation running paid catering, which would fall outside turnover tax under Art. 461 item 13 and so into the general VAT/profit regime for entrepreneurial income.
  For the POS, the operator (STIR/PINFL) on the receipt is what matters, not the building owner.
- [XULOSA] PF-100's cheap rent for adjacent land is a cost lever for choyxonas with outdoor supa or tapchan seating, but it is not a POS feature.

### Gaps
- No lex.uz act was found on the ownership, transfer or lease of mahalla choyxona buildings (for example within the Law on citizens' self-government bodies or the PQ-402 of 21.12.2023 mahalla reforms). This needs a targeted legal search or a local hokimiyat/mahalla inquiry.
- No data was found on how many mahalla choyxonas are commercial vs community-run.
