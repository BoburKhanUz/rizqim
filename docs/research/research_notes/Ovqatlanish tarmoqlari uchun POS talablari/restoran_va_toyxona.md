# Full-service restaurants and banquet/wedding halls (to'yxona) in Uzbekistan: operational workflows and the POS requirements they imply

Research date: 2026-10-19. Scope: Tashkent first, then the regions; data from 2025–2026 unless flagged. Fiscal receipt law is covered separately in `fiskal_background.md`, so it is not repeated here.

**Source-quality legend**
- **[PRIMARY]** marks an official, statistical or survey source, or a first-hand venue or job listing that was fetched and read.
- **[SNIPPET]** marks a claim seen only in a search-engine summary. The underlying page was not opened, so verify it before relying on it.
- **[RU-GENERIC]** marks a Russian industry source that is not specific to Uzbekistan. Tashkent restaurants mostly run the same iiko and R-Keeper software, so these sources are likely relevant, but they are not local evidence.

The single most valuable local dataset found is the UNDP and CERR (ITIM) report *"Umumiy ovqatlanish xizmatlari sohasida norasmiy iqtisodiyot shakllanishi sabablari"*. Its author is Azim Raimbaev, it was published in 2026, and it rests on surveys of catering-business owners and workers. The PDF is at https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf, and these notes cite it as **UNDP/ITIM 2026**. Its business sample was 45% cafes or canteens, 29% restaurants, 11% fast food, 10% choyxonas, 2% to'yxonas and 1% coffee shops.

---

## 1. Order flow in a full-service restaurant (seating, halls and tables, waiter ordering, course firing, kitchen and bar tickets, pre-check, bill, splits, merges and moves, comps and voids)

### Takeaway
A Tashkent full-service restaurant runs a classic Russian-school flow: hostess or administrator, waiter, kitchen and bar tickets, a pre-check ("schyot"), then the cashier or waiter closes the bill. Only about a third of venues use iiko, R-Keeper or similar software; the rest rely on a fiscal cash register plus a paper "hisob qog'ozi", a bill written by the waiter, which the law still explicitly allows. Uzbek-specific write-ups of the floor workflow itself (course firing, splits, table moves) were not found. The concrete requirements below therefore come from vendor practice (iiko and R-Keeper, as used in Uzbekistan) and from fraud patterns.

### Cited Findings
- **Legal anchor for the paper bill.** Clause 14 of Cabinet of Ministers resolution No. 75 (13.02.2003), "Rules for production and sale of catering products", says payment in catering is made against the bill issued by the waiter (*"ofitsiant bergan hisob qog'ozi"*). It may also be made against a KKM receipt, or against an online KKM or virtual-cash-register receipt where the law requires one. Clause 21 requires devices that can read barcodes and digital-marking codes. — [UNDP/ITIM 2026, p.~29](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Software penetration.** Only 36% of catering businesses have "R-Keeper" or similar management software installed. 73% of those have integrated it with the tax database. 97% have an official cash register, and 94% use it constantly. — [UNDP/ITIM 2026, p.43](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Venue size, by seats.** Across the sample, 64% of venues have up to 100 seats, 21% have 100–200, 13% have 200–500 and 2% have more than 500. Average capacity is 384 for to'yxonas, 187 for restaurants, 94 for cafes and canteens, 89 for choyxonas, 61 for fast food and 63 for coffee shops. — [UNDP/ITIM 2026, p.40](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Traffic and check size.** Average daily visitors are 135 overall and 173 at restaurants. The average spend per diner is 75k UZS overall: 99k at restaurants, 94k at to'yxonas, 67k at cafes and choyxonas, and 48k at fast food. Average daily revenue is 17.9M UZS for restaurants, 21.9M for to'yxonas, 10M for cafes and 7.1M for choyxonas. — [UNDP/ITIM 2026, p.41](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Average check by segment (2026 guide).** National-cuisine places and choyxonas run 45k–90k UZS per person, mid-range restaurants 100k–180k, and premium steakhouses or European restaurants about 200k–350k. Shashlik-centred venues run 70k–180k (for example Chirchiq Shashlik Markazi at 70–130k and Sim-Sim Grill at 100–180k). — [qogozcha.uz/restoranlar (© 2026, undated)](https://qogozcha.uz/restoranlar)
- **Handheld ordering.** Local iiko integrators sell iikoWaiter and SmartOrder, apps that turn a phone or tablet into a waiter terminal that sends dishes straight to the kitchen. Enter Co. says it has automated catering in Tashkent, Samarkand and Namangan for 8 years. Price quoted by the integrator A-ONE: iikoCloud Start costs $54 a month per terminal and Pro costs $81 a month (date not given). — [kafesoft.uz](https://kafesoft.uz/store), [enter-co.uz](https://enter-co.uz/), [rest.a-one.uz](https://rest.a-one.uz/) [SNIPPET]
- **Common floor-operation fraud vectors** (all [RU-GENERIC]):
  - deleting an item after the guest has paid, with the cash split with the kitchen;
  - cancelling a pre-check and moving a paid item to another table;
  - leaving a cash-paid table open past the shift so the sale "doesn't exist";
  - cancelling a pre-check, applying a discount after the guest paid full price, and pocketing the difference.

  Recommended controls: only the administrator may delete items or change amounts, plus an audit log of pre-check cancellations, deletions and item transfers. — [open-s.info: 8 theft schemes](https://open-s.info/blog/vorovstvo-v-restorane-chast-i/), [gk-mact.ru: dangerous operations in iiko](https://gk-mact.ru/blog/avtomatizatsiya-obshchepita/opasnye-operatsii-v-iiko/)

### Inferences
POS requirements implied (derived; not stated by a single source):
- **Floor plan.** Multiple halls (main hall, terrace or summer area, VIP *kabina* or *xona* rooms) with tables that have a seat count and a status (free, ordered, pre-check printed, paid). Per-hall settings are likely needed, for example a different service-charge rate or minimum spend for a VIP kabina. This last point is an assumption; no source confirms it.
- **Order entry.** Orders can be entered on a waiter handheld (Android phone) or a fixed terminal. Because 64% of venues are paper- or KKM-only, a low-friction migration path matters: printed kitchen and bar tickets ("marka") routed by printer group, and an option to enter the paper "hisob qog'ozi" later.
- **Course firing.** Send-now versus hold ("fire" later) per item or course, with a "serve together" flag. Kitchen tickets must also print deletions ("УДАЛЕНО" / "o'chirildi") so the kitchen sees voids. The RU sources show that cooks staying silent about such tickets is a known collusion path.
- **Pre-check (schyot).** The pre-check is non-fiscal. Each reprint and each cancellation of a printed pre-check is logged and needs administrator rights. After a pre-check, edits to the order are locked.
- **Splitting.** By item, by seat or guest, or into equal shares. Mixed tender on one bill (cash plus card plus Click or Payme) is required, because 59% of diners pay by card and 45% in cash, so mixed payment is common.
- **Merge and move.** Merging tables, moving a whole order between tables, and moving single items. Every move is logged with who, when, from and to.
- **Voids, comps and discounts.** Voids before kitchen send are free. After send they need a manager PIN and a reason code (guest refused, kitchen error, comp). Comps and discounts need a reason and a manager. All of these appear in a daily exceptions report.
- **Shift close.** A shift cannot close while tables are open; any table still open requires an administrator override.

### Gaps
- No Uzbek-language source was found that describes hostess seating rules, course timing norms, or how often bills are split in Tashkent. Interviewing 3–5 Tashkent restaurant administrators is recommended.
- The share of Tashkent waiters using handhelds versus paper pads is unknown. The only proxy is UNDP's figure that 36% of venues have R-Keeper or similar software.
- No source quantified how common VIP kabinas or terraces are, or whether they carry a separate fee.

---

## 2. Service charge ("obsluzhivanie" / xizmat haqi), its legality, how it appears on the receipt, tipping, and waiter pay

### Takeaway
A service charge of 10–15% on top of menu prices, sometimes 20%, is widespread in Uzbek cafes and restaurants. In November 2025 the Competition Committee (Raqobat qo'mitasi) publicly warned that charging it *compulsorily* or *without prior disclosure* breaches the Consumer Rights Law, Articles 4, 19 and 21. Consumer-rights commentary from August 2026 says the charge is not illegal *per se* if it is disclosed in advance on the menu and itemised separately on the bill. Waiters are paid a monthly salary or a per-shift rate. Tips are kept by the waiter, and some venues add a small percentage of sales.

### Cited Findings
- **The warning.** Complaints to the Competition Committee said some restaurants and cafes add *"taomlar narxidan tashqari 10–15 foiz, ba'zan esa 20 foizgacha xizmat haqi"* ("a service charge of 10–15%, sometimes up to 20%, on top of dish prices"). The Committee *"ofitsiant xizmati uchun majburiy ravishda haq undiruvchi restoran va kafelarni ogohlantirdi"* ("warned restaurants and cafes that compulsorily charge for waiter service"). It cited Articles 4, 19 and 21 of the Law "On Protection of Consumer Rights". — [kun.uz, 21.11.2025](https://kun.uz/news/2025/11/21/kafe-va-restoranlarda-qoshimcha-undirilayotgan-15-20-foiz-xizmat-haqi-qonunga-xilof-raqobat-qomitasi) [PRIMARY]
- **What the Committee asked for.**
  - Disclose dish prices, service procedures and the size of the waiter-service fee in advance.
  - Do not force additional paid services, and do not charge for services not rendered.
  - Depending on the venue type, offer a self-service option.

  The same warning covers venues that demand **advance deposits** for tables. — [spot.uz, 21.11.2025](https://www.spot.uz/oz/2025/11/21/restaurants-warned); [gazeta.uz, 21.11.2025](https://www.gazeta.uz/oz/2025/11/21/restoran-kafe/) [PRIMARY]
- **Consumer-rights reading.** The charge is not unlawful in itself; what matters is advance disclosure. The menu should state the service charge and its percentage (examples given: 5, 10, 15%). The bill should list dishes, prices, the service charge and other fees separately. Article 21 bans forcing extra paid services. Conditions such as "set menu only" must respect freedom of choice. — [istemolchi-info.uz, 25.08.2026](https://istemolchi-info.uz/archives/12853)
- **Tips.** A review of Café 1991 in Tashkent says service is already included in its prices, so tipping is optional. This is a single-venue anecdote. — [Tripadvisor via search](https://www.tripadvisor.ru/Restaurants-g293968-zfp6-Tashkent_Tashkent_Province.html) [SNIPPET]
- **Waiter pay on hh.uz** (32 Tashkent listings, fetched October 2026, undated cards).
  - Monthly offers:
    - Afsona / Irish Pub at 3.5–7M UZS;
    - Ramada Encore from 4.5M;
    - TomYumBar at 6–12M;
    - Ribambelle at 7–12M;
    - Roni Pizza at 8–15M;
    - Sezam Garden at 9–15M;
    - TGI Fridays at 11–13M;
    - Sun Group at 12–25M.
  - Per-shift offers:
    - Giotto at 200–250k;
    - Gosht Group at 200–300k;
    - Assorti at 120–500k;
    - Lambic at 400k–1M;
    - runners at 120–150k.
  - Payout frequency varies: daily, weekly, or twice a month.

  — [tashkent.hh.uz](https://tashkent.hh.uz/vacancies/oficiant_v_restoran) [PRIMARY]
- **Telegram job channel.** Giotto offers waiters 180k per shift, rising to 200k plus a KPI bonus, on 6/1 or 3/1 shifts from 10:00 to 23:00, with pay three times a month. A hostess at Dönerci Hamdi Usta earns 150–170k per day. — [t.me/s/Ish_Toshkent](https://t.me/s/Ish_Toshkent?before=3913) [PRIMARY, post dates not shown]
- **Percentage-of-sales pay (unverified).** Search summaries mentioned three schemes: ZarKebab at 3–6M plus a percentage of each order with tips kept by the waiter; ShoshArt at up to 7% of each closed order plus tips; and a listing with salary plus 2%. A Telegram listing offered a hostess 2.5–5M "+ foiz". — [search over tashkent.hh.uz / olx.uz / t.me](https://tashkent.hh.uz/vacancies/oficiant_v_restoran) [SNIPPET — not verified on the original pages]
- **Banquet staff pay.** A Tashkent wedding restaurant (Turkiston) pays staff $20 on ordinary days and $40 on wedding days, about $600–1,200 a month. — [Kursiv Uzbekistan, 29.04.2025](https://uz.kursiv.media/2025-04-29/ekonomika-svadeb-v-tashkente-skolko-stoit-torzhestvo-i-kto-na-etom-zarabatyvaet-v-2025-godu/)
- **Informal pay.** Waiters are the main informal staff: 73% of employers who have informal staff name waiters, and 52% of informally employed respondents are waiters. 47% of workers are paid fully in cash. 19% of firms do not report wages fully; of those, 55% declare 50–75% of the wage and pay the rest "in an envelope". The average informal share of staff is 23.5% overall, 26% at to'yxonas and 16.2% at restaurants. — [UNDP/ITIM 2026, pp.46–52](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Shift lengths.** Workers average 10 hours a day: administrators 11, waiters 10, cooks 9. 14% work without a day off. — [UNDP/ITIM 2026, p.51](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Regional context.** Kyrgyzstan is moving to ban the separate 15% service charge, prompted partly by complaints that it was not shown on receipts and that waiters got less than half of it. — [24.kg](https://24.kg/ekonomika/347532_otmena_chaevyih_vkafe_ofitsiantyi_poluchali_menshe_polovinyi_izprotsentov/), [kaktus.media](https://kaktus.media/doc/528762_v_kyrgyzstane_hotiat_zapretit_15_platy_za_obslyjivanie_v_kafe_i_restoranah.html) (not Uzbekistan; shows the regulatory direction in the region)

### Inferences
POS requirements implied:
- **Configurable service charge.** It should be configurable as 0, 5, 10, 12, 15 or 20%, and settable per venue, per hall (for example, VIP or terrace) and per order type (dine-in on; takeaway and delivery off). It must appear as a **separate line** on the pre-check and the final bill. It must be **removable by a manager**, with a logged reason, when the guest disputes it; this follows from the Article 21 "no forced services" logic.
- **Disclosure.** Printed menus, QR menus and pre-check footers should carry a "Xizmat haqi X%" note to support the advance-disclosure requirement.
- **Fiscal treatment.** The service charge most likely needs its own MXIK/IKPU service code line on the fiscal check. See `fiskal_background.md` and confirm with an accountant.
- **Tips.** Tips are a separate, non-revenue field: a card tip or "choy puli". Payroll needs reports that attribute tips and service charge to each waiter.
- **Payroll basis.** Support a fixed shift rate plus a percentage of each waiter's closed sales (examples: 2%, up to 7%) plus a KPI bonus. Payroll periods should be daily, weekly, 10-day and twice-monthly, because job ads show all of these.
- **Regulatory risk.** Uzbekistan may follow Kyrgyzstan and restrict service charges. Do not hard-code the charge into dish prices; keep it a toggleable rule.

### Gaps
- No lex.uz norm was found that explicitly permits or bans a service charge in Uzbekistan. The current position rests on the Committee's November 2025 warning and the general consumer-rights law. No fines data was found.
- No verified data on how much of the service charge reaches waiters in Uzbekistan.
- No data on typical tip size in Tashkent.

---

## 3. Roles and permissions, shift handover, cash discipline, and fraud patterns

### Takeaway
Typical staff are the owner, administrator ("zal mudiri"), cashier or accountant, waiter, runner, hostess, bartender, cook and cook's assistant. Owners' main loss vectors are unrecorded cash sales, voided or deleted items, after-payment discounts, and staff pocketing cash. These sit inside a sector where 87% of activity is classed as unobserved economy and cash still accounts for roughly 45% of payments.

### Cited Findings
- **Worker roles in the survey.** Respondents were 32% waiters, 25% administrators or "zal mudiri", 12% cooks, 12% accountants, 9% cook's assistants and 4% helpers (dishwashing, cleaning). — [UNDP/ITIM 2026, p.50](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Payment mix and steering.** Businesses report that on average 59% of customers pay by card and 45% in cash. 30% of businesses steer card or cash payers toward a P2P online transfer; this is 45% at choyxonas and 38% at to'yxonas. The report calls online transfers and cash the most common way to hide turnover. — [UNDP/ITIM 2026, p.43](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Receipts on cash payments.** 89% of businesses say they always issue receipts, yet 32% admit they do not always issue one for cash payments. The reasons given: poor internet 26%, faulty equipment 20%, "customer doesn't need it" 14%, bank link failure 11%. — [UNDP/ITIM 2026, pp.43–44](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Customer behaviour.** 37% of consumers never ask for a fiscal receipt, and 80% don't care if they are not given one. Only 37% always demand one. — [UNDP/ITIM 2026, pp.8, 38](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Scale of the shadow economy.** The unobserved economy is 87% of the catering sector, of which 83% is in the informal sector. — [UNDP/ITIM 2026, executive summary](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Staff fraud patterns** (all [RU-GENERIC]):
  - deleting paid items;
  - moving items between tables after a pre-check is cancelled;
  - carrying open tables to the next day;
  - discounting after payment;
  - collusion between waiter and cook through silent "deleted" tickets.

  Controls: rights restricted to the administrator, event logs, and comparison with inventory counts. One vendor claims staff dishonesty cuts revenue by 10–20%; this is a marketing figure and unverified. — [open-s.info](https://open-s.info/blog/vorovstvo-v-restorane-chast-i/), [open-s.info part 2](https://open-s.info/blog/sposoby-vorovstva-chast-vtoraya/), [restospace.com: event journal](https://restospace.com/blog/articles/article/107)

### Inferences
POS requirements implied:
- **Role matrix (RBAC).**
  - **Owner:** everything, remote view on mobile.
  - **Administrator:** voids after kitchen send, comps, discounts, pre-check cancellation, table transfer between waiters, forced shift close, cash-in and cash-out.
  - **Cashier:** close bills, take payments, X and Z reports, cash drawer.
  - **Waiter:** open and order on own tables only, print pre-check once.
  - **Bartender:** bar orders and own bar tab.
  - **Hostess:** reservations, seating, table status only.
  - **Cook:** KDS bump only.
- **Approval flow.** Every sensitive action needs a manager PIN or card on the same device, or a remote approve via Telegram push to the owner.
- **Owner alerts.** Telegram alerts for voids above a threshold, discounts above X%, pre-check cancellations, a table open longer than N hours, and the shift Z-report.
- **Shift handover.** Opening float, cash count by denomination at close, recorded variance, mandatory closure of every table before the Z-report, and waiter-to-waiter table handover logged at shift change. Waiter shifts run 10–13 hours and 6/1 or 3/1 rotas are common.
- **Payment type.** "P2P transfer to a card" (Click, Payme, Uzum transfers) should be recorded as its own tender type. Owners want to see it. Note the fiscal implications (see `fiskal_background.md`).
- **Offline operation.** Offline-tolerant operation reduces the "no internet, no receipt" excuse, the most cited reason (26%) for skipping receipts.

### Gaps
- No Uzbek-language case study or interview was found in which a Tashkent owner describes specific theft cases.
- No quantified loss rate for Uzbekistan.

---

## 4. Menu specifics: portion versus weight, modifiers, half-portions, bread, tea and table charges, alcohol

### Takeaway
Little documentary evidence was found. Shashlik is priced per skewer ("six") and grilled venues list several kinds. Menus emphasise national dishes and grills. Alcohol needs a licence, and 47% of businesses cite licensing difficulty (alcohol, sanitary, 24-hour) as a push toward informality. Bread or "non", tea and table charges were not documented in any source found.

### Cited Findings
- **Per-skewer pricing.** A 24-hour Tashkent venue, G'ijduvon Premium, listed shashlik "from 16,000 so'm" in August 2024 (2024 data). — [afisha.uz, 07.08.2024](https://www.afisha.uz/uz/gorod/2024/08/07/restiklar) [SNIPPET]
- **Shashlik varieties.** Menus distinguish G'ijduvon, qiyma, lula, and liver with fat ("jigar va jiz"). — [qogozcha.uz](https://qogozcha.uz/restoranlar)
- **Licensing friction.** 47% of businesses say difficulty and cost of licences (alcohol sales, sanitary, 24-hour operation) can push them into informality; 17% call it a main cause. — [UNDP/ITIM 2026, p.43](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Weight disclosure.** Consumer-rights guidance says diners should get enough information to verify the quantity and weight of the food served. — [istemolchi-info.uz, 25.08.2026](https://istemolchi-info.uz/archives/12853)
- **Cake by weight.** Banquet add-on cakes are sold by the kilogram at about 180–200k UZS per kg in 2026. — [afisha.uz, 24.08.2026](https://www.afisha.uz/ru/weddings/2026/08/24/wedding-budget)
- **Purchasing.** 64% of businesses buy their main ingredients at bazaars and 57% buy meat from bazaars or butchers. Only 54% of purchases are documented, so food-cost data in a POS will often lack supplier invoices. — [UNDP/ITIM 2026, p.42](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]

### Inferences
POS requirements implied:
- **Unit types.** Items need a unit type of piece or portion, skewer, or weight (kg or 100 g). Fish and meat sold "by kg" need a price per kg with the weighed quantity entered on the handheld, or taken from scales. Grams and portion weight should print on the bill, for the consumer-rights point above.
- **Modifiers.** Half or double portions, doneness, "without onion" and similar, both priced and free.
- **Mandatory or auto items.** Bread, tea or a per-guest table charge, if a venue uses them, should be auto-added or per-guest items that are clearly disclosed. The same disclosure logic as the service charge applies.
- **Alcohol.** A product category that can be flagged (licence, marking, cash restrictions) and hidden from venues without a licence.
- **Stock purchases.** Stock receipts must allow entering bazaar purchases without a supplier invoice, as a cash expense, or food-cost reports will be useless.

### Gaps
- No current (2025–2026) Tashkent menu with per-skewer, per-kg fish or bread and tea prices was retrieved.
- Whether bread, tea or a "cover charge" is commonly billed separately in Tashkent is undocumented. Field verification is needed.
- Alcohol rules for restaurants (licence, sale hours, marking) were not researched here; they overlap with the fiscal and marking notes.

---

## 5. Banquets and weddings (to'y, nahor oshi, birthdays, corporate events)

### Takeaway
To'yxonas are the biggest and highest-revenue catering format. Average capacity is 384 seats and average daily revenue is 21.9M UZS. Weddings are priced **per guest** with set menus, typically about 390k–500k UZS per guest in Tashkent in 2026, rising to 800k+ for premium venues. Some venues add a separate hall-rental fee from 25M UZS and impose minimum guest counts (for example, 100). Bookings are made months in advance with a deposit; one source puts it at 5–15M UZS and 6–12 months ahead. To'yxonas are also among the venues most likely to steer payment to P2P transfers.

### Cited Findings
- **Seoul Banquet Hall, Tashkent (2026 site).**
  - Pricing: menus at 390k or 450k UZS per guest, hall rental from 25M UZS.
  - Capacity: 100–320 guests, with a 100-guest minimum. Main hall 724 m², lobby 111 m².
  - Facilities: parking for 110 cars, and more than 70 European and Korean dishes.
  - Booking channels: a web form ("leave a request", after which a manager calls back), phone, Telegram, WhatsApp and Instagram.

  — [seoulhall.uz](https://seoulhall.uz/) [PRIMARY]
- **Afisha 2026 wedding budget.** It assumes 500k UZS per guest, including hall and basic service, so a banquet costs 25M for 50 guests, 50M for 100 and 100M for 200. Total wedding budgets come to about 94M, 129M and 198M UZS respectively. It advises getting the *full* price before paying a deposit, because hall rental, service, textiles, equipment and **extra hours** may be billed separately. The usual payment sequence starts with the venue deposit. — [afisha.uz, 24.08.2026](https://www.afisha.uz/ru/weddings/2026/08/24/wedding-budget) [PRIMARY; partly sponsored]
- **Kursiv 2025.**
  - Average check of $30–50 per person, so 200 guests cost $6–10k. A typical Tashkent wedding of 150–200 guests costs $5–10k.
  - The Turkiston restaurant hosted about 230–240 weddings in the previous year. Calendars were booked until June at the time of writing (April 2025). Winter is the "dead" season.
  - 22,352 weddings were registered in Tashkent in 2023 and 22,755 in 2024.
  - Example: one family held a morning plov (nahor oshi) for 200 guests, a bride's party for 50 and an evening banquet for 100, for about $8k in total in November 2023.

  — [Kursiv Uzbekistan, 29.04.2025](https://uz.kursiv.media/2025-04-29/ekonomika-svadeb-v-tashkente-skolko-stoit-torzhestvo-i-kto-na-etom-zarabatyvaet-v-2025-godu/) [PRIMARY]
- **Premium hall.** Versal is listed "from 800,000 UZS per person". — [anons.uz](https://anons.uz/ru/digest/gde-provesti-svadbu-v-tashkente) [SNIPPET]
- **Booking practice (vendor blog).**
  - Halls are booked 6–12 months ahead.
  - A booking records the date, package, contacts and a prepayment deposit of 5–15M UZS.
  - Halls are rented per day, half-day or evening.
  - Capacities run 100, 200, 500 and 800 guests.
  - Turnkey packages cost about 50–100M (economy), 100–200M (mid) and 200–500M UZS (premium).
  - Many halls hide their availability calendar and make clients phone.

  — [sayt.uz blog (date shown as 20.07.2029, apparently a typo)](https://sayt.uz/ru/blog/nikoh-toyxona-sayt) [vendor marketing; treat as indicative]
- **Large halls on 2GIS.** Some list capacities up to 400 (Sarbon) or 900 (Yakka Saroy). — [2gis.uz search](https://2gis.uz/tashkent/search/%D0%91%D0%B0%D0%BD%D0%BA%D0%B5%D1%82%D0%BD%D1%8B%D0%B9%20%D0%B7%D0%B0%D0%BB%20%D0%B4%D0%BB%D1%8F%20%D1%81%D0%B2%D0%B0%D0%B4%D1%8C%D0%B1%D1%8B%20%D0%B2%20%D0%A2%D0%B0%D1%88%D0%BA%D0%B5%D0%BD%D1%82%D0%B5) [SNIPPET]
- **Individual menus.** Manana restaurant builds banquet menus individually on request. — [manana.uz/banquet](https://manana.uz/banquet) [SNIPPET]
- **To'yxona survey figures.**
  - Average capacity 384, spend per guest 94k UZS, daily revenue 21.9M.
  - 38% steer customers to online P2P transfers, and 26% of staff are informal.
  - Tax burden is 16.1%.
  - They claim to pay their formal staff fully officially.

  — [UNDP/ITIM 2026, pp.40–47](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY] *(Note: the 94k per guest is far below the 390–500k banquet prices. The survey figure probably averages non-wedding days, small events or regions; treat as not comparable.)*
- **Nahor oshi.** The morning plov is a ritual held at weddings and funerals, usually preceded the evening before by a "sabzi to'g'rash" (carrot-cutting) gathering. — [uz.wikipedia: Nahor oshi](https://uz.wikipedia.org/wiki/Nahor_oshi)
- **Deposits under scrutiny.** The Competition Committee's November 2025 warning also targets restaurants demanding advance deposits. — [spot.uz, 21.11.2025](https://www.spot.uz/oz/2025/11/21/restaurants-warned)

### Inferences
POS and back-office requirements implied (a banquet module separate from the à-la-carte flow):
- **Event record.** Type (to'y, nahor oshi, kelin salom or bride's party, birthday, corporate, funeral or "ehson"), date, time slot (morning plov, day, evening), hall or halls, guaranteed guest count and minimum guests, and the client with phone and passport details for the contract.
- **Calendar.** Hall-by-date availability that can be shared, for example a read-only Telegram or web link. Double-booking prevention matters because dates are sold 6–12 months ahead.
- **Pricing.** Per-guest set-menu packages (for example 390k or 450k tiers) times guest count, plus hall rental, plus add-ons (extra hours, textiles, equipment, decor, cake per kg, corkage for the client's own alcohol). Service charge where applicable.
- **Payments.** Deposit or zakalat, then interim payments, then final settlement after the actual guest count is known. Each payment can be cash, card or transfer, with a balance-due tracker. A refund or forfeit policy should be configurable, given regulatory attention to deposits.
- **Fiscal timing.** Each prepayment probably needs a fiscal "advance" receipt and the final receipt a settlement. Coordinate with `fiskal_background.md`.
- **Production.** A banquet order turns into kitchen production sheets (dishes times quantity), purchasing lists and stock write-off per event. Extra à-la-carte orders during the event go onto the same event bill.
- **Staffing.** Waiters assigned per event with per-event pay (for example $40 on wedding days versus $20 normally), which links to payroll.
- **Contract.** A printable contract or quote in Uzbek (Latin and Cyrillic) and Russian.

### Gaps
- No verified figure for deposit as a percentage of the total. The 5–15M UZS figure comes from a vendor blog. Venue pages (Seoul) do not state deposit terms.
- No regional (Samarkand, Fergana valley and so on) per-guest prices were found.
- No source on the typical guest count of regional weddings (often said to be 300–1000+, but not sourced here).
- No source on nahor oshi pricing (per "qozon" or per guest) or on how to'yxonas bill it.
- No source on cancellation and refund practices or on post-event guest-count reconciliation.

---

## 6. Reservations (channels, deposits, no-shows)

### Takeaway
Reservations come by phone and messengers (Telegram, WhatsApp), Instagram, and web forms. Popular Tashkent venues need advance booking. Table deposits exist but drew a regulatory warning in November 2025. No data was found on no-show rates.

### Cited Findings
- **Banquet hall channels.** Seoul Hall lists phone, Telegram, WhatsApp, Instagram, Facebook Messenger and a web form with manager callback. — [seoulhall.uz](https://seoulhall.uz/) [PRIMARY]
- **Table deposits.** The Competition Committee notes complaints about venues requiring advance deposits and says all terms must be disclosed in advance. — [kun.uz, 21.11.2025](https://kun.uz/news/2025/11/21/kafe-va-restoranlarda-qoshimcha-undirilayotgan-15-20-foiz-xizmat-haqi-qonunga-xilof-raqobat-qomitasi)
- **Popular venues.** Reviews say some Tashkent restaurants (for example Jumanji, and Ramiz in Tashkent City) cannot be entered without a reservation. — [Tripadvisor via search](https://www.tripadvisor.ru/Restaurants-g293968-zfp6-Tashkent_Tashkent_Province.html) [SNIPPET]

### Inferences
- **Reservation book.** A booking records name, phone, party size, time, hall or table, deposit and a note. A Telegram bot or link for booking, with SMS or Telegram reminders to reduce no-shows. The hostess role sees the table map with upcoming reservations.
- **Deposit handling.** A deposit becomes a prepayment credited to the final bill, with its disclosure text stored on the booking.

### Gaps
- No-show rates, typical deposit amounts for tables, and the share of bookings by channel were not found.

---

## 7. Delivery and takeaway from full-service restaurants

### Takeaway
No full-service-specific data was found in this pass. Online-marketplace payments are relevant to fiscal treatment: the UNDP report notes that payments received via marketplaces are treated as cashless for VAT-refund incentives.

### Cited Findings
- **Cashless incentive.** Catering businesses whose monthly turnover is more than 60% cashless (terminals or mobile payments) get 40% of paid VAT refunded; those below 60% get 20%. Payments via marketplaces count as cashless. — [UNDP/ITIM 2026, p.~31](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]

### Inferences
- **Order types.** Order types are dine-in, takeaway (o'zi olib ketish), own delivery, and aggregator delivery (Yandex Eats, Uzum Tezkor, Wolt and others; not researched here). Each needs its own price list, service-charge rule (normally off for takeaway) and packaging items.
- **Cashless share report.** A report of the cashless percentage of turnover is directly valuable to owners, because crossing the 60% threshold doubles the VAT refund from 20% to 40%.

### Gaps
- Aggregator commission rates and the share of delivery in full-service restaurant revenue were not researched; another researcher may cover this.

---

## 8. Reporting owners care about

### Takeaway
Based on the data owners struggle with, priority reports are:
- daily revenue by tender (cash, card, P2P transfer);
- the cashless share against the 60% VAT-refund threshold;
- waiter performance (sales, average check, voids, discounts);
- void and discount exception logs;
- Z-report and cash variance;
- food cost against purchases, many of them undocumented bazaar buys.

### Cited Findings
- **Who prepares tax reports.** In 62% of businesses an in-house accountant prepares tax reports, in 25% an external accountant, and in 13% the owner. 45% find tax reporting somewhat or very difficult. — [UNDP/ITIM 2026, p.44](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Tax burden.** The self-reported average is 15.9% (restaurants 15.6%, to'yxonas 16.1%). The Tax Committee's figure is 7.2% of turnover. — [UNDP/ITIM 2026, pp.44–45](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **Cashless threshold.** The VAT-refund incentive works at the 60% cashless threshold. — [UNDP/ITIM 2026](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) [PRIMARY]
- **iiko positioning.** Local iiko integrators sell it as cash register, warehouse, staff, kitchen, finance and reporting in a single system. — [rest.a-one.uz](https://rest.a-one.uz/) [SNIPPET]

### Inferences
Minimum owner report set:
- **Daily summary (pushed to Telegram):** revenue, number of checks, guests, average check, and the split of cash, card, Click/Payme/Uzum and P2P.
- **Per-waiter report:** sales, checks, average check, service charge earned, tips, voids, discounts, pre-check cancellations.
- **Exceptions log.**
- **Hourly and hall heat-map.**
- **ABC menu analysis.**
- **Food cost:** recipe-card theoretical cost against actual purchases.
- **Banquet pipeline:** booked dates, deposits held, balances due.
- **Shift reports:** X and Z with cash variance.
- **Accountant exports:** formats an in-house or external accountant can use, since 87% of firms rely on an accountant.

### Gaps
- No Uzbek owner survey or interview that ranks which reports owners actually use.
