# Uzbekistan-specific food-service formats (choyxona, osh markazi, oshxona/stolovaya, samsa/tandir, national fast-casual): how they operate and what a POS must support

Research date: 2026-10-19. Language of sources: Uzbek, Russian, English. Fiscal-receipt law is deliberately excluded (see `fiskal_background.md`).
Confidence markers: **[VERIFIED]** = read on the page itself; **[VENDOR]** = vendor's own marketing claim; **[TRAVEL/BLOG]** = tourist guide, blog or review, so lower reliability; **[OLD]** = source older than Oct 2024.

Overall caveat: open, quantitative, operator-side data on these formats is scarce. Most numbers come from (a) the official "Palov indeksi" of the Statistics Agency/National Statistics Committee, (b) Afisha.uz restaurant reviews with menus, (c) tourist guides about the Tashkent Central Asian Plov Centre / Besh Qozon, and (d) local POS vendors' websites. I found **no** interviews in which owners of choyxonas or plov centres describe their cash control, credit (nasiya) or POS use. Those points are listed as gaps.

---

## 1. Choyxona (teahouse): seating, groups, tea, whole-dish ordering, cooking to order, bill-splitting, nasiya, payment

### Takeaway
A choyxona is a legally recognised catering type in Uzbekistan. It is mainly a venue for male groups that meet regularly ("gap", weekly or monthly) in **private rooms** or on tapchans. They order tea first and then a **group dish** (plov cooked in a kazan for 15–50 people). Some choyxonas also let the group **rent the kitchen spot and cook themselves**. For a POS this means the unit of sale is a **room/group** with a long-open bill, not a seat. That bill mixes per-kg or per-kazan dishes with per-unit items and possibly a room fee. I found no published data on nasiya or on bill-splitting habits.

### Cited Findings
- The Competition Committee (21 Nov 2025) lists the official catering enterprise types under **Cabinet of Ministers Resolution No. 75**: restaurant, wagon-restaurant, bar, café, seasonal café, cafeteria, **oshxona**, **choyxona**, buffet, and **kichik oshxona** (small canteen). For choyxona it gives no specific service model. **[VERIFIED]** — [raqobat.gov.uz, 21.11.2025](https://raqobat.gov.uz/uz/raqobat-qomitasi-respublikamizdagi-barcha-turdagi-umumiy-ovqatlanish-korxonalarini-ogohlantiradi/)
- Close friends or relatives meet in a choyxona "at least once a week or once a month". They order plov and drink tea while it is being cooked or brought. The choyxona is described as a traditionally male venue (news, conversation, nardy/backgammon). **[TRAVEL/BLOG]** — [Centrum Air blog (RU)](https://centrum-air.com/ru/blogs/lyubaya-istoriya-nachinaetsya-s-chashki-chaya); [uzbekistan.travel – Чайхана](https://uzbekistan.travel/ru/o/chajhana/)
- "Gap/gashtak" groups (classmates, neighbours, colleagues) pay a fixed sum weekly or monthly, and the pooled money goes to one member in rotation. This comes from a project spec (hamdavra) describing the practice, so it is only a secondary source. **[UNVERIFIED secondary]** — [GitHub hamdavra](https://github.com/baxriddin0317/hamdavra)
- Choyxona near Tashkent (Ulugbek settlement): a group may either **order plov from the choyxona's cooks** or **rent a table plus a place in the kitchen and cook it themselves** (here, led by a guest cook). The kitchen had **11 gas kazans**. Side kazans feed **~15–20 people** and the large end kazan **~40–50 people**. Groups sit in **private rooms off an inner courtyard**, some with low tables for sitting cross-legged. Kazan-kabob and shurpa are also cooked in kazans. **[OLD: July 2011, blog]** — [protchenkov LiveJournal](https://protchenkov.livejournal.com/26258.html)
- A choyxona listing channel (choyxonachi.uz, Telegram) shows choyxonas advertising "tasty oshes and cozy **rooms**". Some listings are dacha-style venues with **tapchans** and a price "for up to 10 people". The channel mentions a weekday "**joy narxi**" (place/room price) of **75,000 so'm** for one venue. Only a snippet was available; the post date and terms are unclear. **[UNVERIFIED]** — [t.me/s/choyxonachiuz](https://t.me/s/choyxonachiuz); directory: [choyxonachi.uz – TOP 77 choyxonas](https://choyxonachi.uz/places/category/choyxona-1/)
- **Service charges and deposits:** consumers complained that cafés and restaurants add **10–15% (sometimes 20%)** service charges and demand **deposits for bookings**. The Competition Committee says imposing waiter-service fees is unlawful unless it is disclosed in advance, and a seller may not charge for services not provided. Choyxonas are among the covered types. **[VERIFIED]** — [spot.uz RU, 21.11.2025](https://www.spot.uz/ru/2025/11/21/tips-catering); [kun.uz, 21.11.2025](https://kun.uz/news/2025/11/21/kafe-va-restoranlarda-qoshimcha-undirilayotgan-15-20-foiz-xizmat-haqi-qonunga-xilof-raqobat-qomitasi)
- Typical spend at "milliy oshxona va choyxonalar" (national restaurants and choyxonas) is **45,000–90,000 so'm per person** (2026 guide). **[TRAVEL/BLOG]** — [qogozcha.uz – restoranlar 2026](https://qogozcha.uz/restoranlar)
- Choyxona-style plov in Tashkent fast-casual/plov cafés in 2025–2026: **"choyxona oshi" costs the same as to'y oshi**, 55,000 so'm per portion at Minor Osh (Apr 2026) and 40,000 so'm per 0.7 portion at Abdujalil osh markazi (Jan 2025). **[VERIFIED]** — [Afisha.uz, Minor osh, 23.04.2026](https://www.afisha.uz/uz/restaurants/2026/04/23/minor-osh); [Afisha.uz, Abdujalil, 09.01.2025](https://www.afisha.uz/uz/gorod/2025/01/09/abdujalil-osh-markazi)
- Paper credit ledgers ("qarz daftari") are widespread in small Uzbek businesses. A nasiya-app vendor claims **>1 million small businesses keep nasiya on paper** and says paper books get torn or lost. This refers to shops, not choyxonas specifically. **[VENDOR]** — [daftaron.uz](https://daftaron.uz/); [birliy.uz blog](https://birliy.uz/blog/mini-market-dasturi-kassa-ombor-nasiya)

### Inferences
- POS model for a choyxona: **rooms/tapchans as "tables"** with a capacity and an optional **room/place fee (joy narxi)**, either flat or per weekday. The bill stays **open for hours**, with items added in rounds (tea first, then salads and non, then plov, then more tea).
- Need **"group dish" items** sold per kazan, per kg of rice/meat or per number of persons (e.g. "osh 5 kg guruch", "lagan for 10 kishi"). Free-price or weight-based lines are needed, plus a **"cooking service only" line** for when the group brings its own meat or rice or cooks itself (kazan rent and kitchen fee).
- Need **pre-booking with a deposit/prepayment** that is deducted from the final bill, given that deposits are common and now scrutinised. Any **service-charge %** must be configurable, shown on the bill and clearly disclosed, or switched off.
- **Equal split by N persons** and **split by items** are both needed. Gap groups typically settle as one payer or split equally. This is my inference; I found no source on how they actually split.
- **Customer account with debt (nasiya)** for regulars, with a balance and a payment history replacing the paper "qarz daftari". This is inferred from the paper-ledger prevalence in small businesses; no choyxona-specific source was found.

### Gaps
- No source found with actual choyxona menus giving **per-kg/per-lagan plov prices** or the **servings per kg** they use.
- No source on **how gap groups pay** (equal split vs. rotating payer vs. host pays), or on how common **nasiya** is in choyxonas.
- No data on choyxona **seat counts, room counts or revenue**. The Kukcha-area two-storey choyxona building sold for debts (valued at 6.25 bn so'm, July 2024) was only seen in a search snippet: [spot.uz tag "чайхана"](https://www.spot.uz/ru/tag/чайхана/), unverified.
- No source confirms a standard **tea charge per table/per person** (in Kazakhstan, tea is priced like a full dish; that is not evidence for Uzbekistan).

---

## 2. Plov centres (osh markazi) and kazan-based batch cooking

### Takeaway
Plov centres cook in **a few very large kazans** (reported as 5–10, up to ~1 t or "3 tonnes" each) in the morning. They serve from about **11:00**, and the main plov is typically **sold out by about 14:00–15:00**. They sell **fractional portions** (1 / 0.75 / 0.7 / 0.5) at 35,000–65,000 so'm in Tashkent, plus a fixed set of add-ons (qazi, quail eggs, extra meat, salads, non, tea), and allow takeaway in containers. Throughput is thousands of portions in a ~3–4 hour window. A POS must therefore support **batch/kazan stock with live "sold out"**, portion-fraction modifiers and a very fast counter flow.

### Cited Findings
- **Statistics Agency "Palov indeksi" (Dec 2024)**: data is collected at large catering enterprises, **including "Osh markazi" in every regional centre**. The average price of **one portion** rose from 24,978 to **28,885 so'm** nationally in 2024 (+15.6%). Tashkent city had the highest at **35,744 so'm** (+20.5%) and Namangan the lowest at **22,201 so'm**. Other regions: Bukhara 33,717; Andijan 31,184; Khorezm 30,939; Kashkadarya 30,688; Samarkand 30,480; Jizzakh 28,762; Surkhandarya 28,575; Tashkent region 27,287; Navoiy 26,759; Fergana 26,602; Syrdarya 25,741; Karakalpakstan 25,718. **[VERIFIED]** — [Gazeta.uz, 03.01.2025](https://www.gazeta.uz/oz/2025/01/03/plov-index/)
- Since 2025 the index is reported mainly as the **ingredient cost of 1 kg of plov** (not the sale price): Tashkent city **125,126 so'm/kg** (May 2026) vs. Karakalpakstan 103,557. July 2025: Tashkent 117,694/kg. The committee stresses this is *not* the price of ready plov. **[secondary news, figures consistent across outlets]** — [Sputnik O'zbekiston, 07.06.2026](https://oz.sputniknews.uz/20260607/uzbekistan-palov-qimmat-arzon-hududlar-58176497.html); [Sputnik, 07.08.2026](https://oz.sputniknews.uz/20260807/palov-indeksi-eng-arzon-va-eng-qimmat-osh-qaerda-59556169.html); [spot.uz, 01.08.2025](https://www.spot.uz/oz/2025/08/01/palov)
- **Central Asian Plov Centre / Besh Qozon (Tashkent, near TV tower):**
  - Hours **11:00–15:00**; portions "usually sell out by ~14:00"; **>7,000 portions/day from 10 kazans**, the largest weighing almost a ton. Portion price **45,000–65,000 so'm**. Order **at the counter** and pay; the left serving window is described as much faster than the canteen line on the right. **[TRAVEL/BLOG, 27.04.2026; unverified figures]** — [uz-safar.com, Plov festival 2026 guide](https://www.uz-safar.com/ru/blog/plov-festival-tashkent-2026-guide)
  - Other claims: "up to **10 tons** of plov daily" (travel listing, unverified) — [kupi.com](https://kupi.com/en-ae/explore/uzbekistan/tashkent/besh-qozon); nearly **3,000 lunch guests per day**, with one batch using **100 kg carrots + 100 kg rice** and ~6 hours of cooking (video transcript summary, unverified) — [VoiceTube video page](https://tw.voicetube.com/videos/169844).
  - Daryo.uz (14.09.2025) headline: "**Uch tonna osh damlaydigan oshpaz** – Beshqozon osh markaziga safar" (a cook who cooks 3 tonnes of osh). Only the headline was accessible (HTTP 403). — [daryo.uz](https://daryo.uz/2025/09/14/uch-tonna-osh-damlaydigan-oshpaz-beshqozon-osh-markaziga-safar-qilamiz-video/)
  - Afisha.uz review (21.11.2024): **no printed or QR menu** (the waiter said "no"; the menu is on beshqozon.uz). Prices: linseed-oil plov **42,000 / 40,000** (full / **0.7 portion**); wedding plov and teahouse plov **38,000 / 36,000**. Serving times were 5–14 min. Two of three salads arrived and the cola never did, which suggests order-loss problems. Branches: TV tower, Yangi Sergeli, Aklan, Glinka. **[VERIFIED]** — [Afisha.uz RU, 21.11.2024](https://www.afisha.uz/ru/gorod/2024/11/21/plove)
  - Older description: **6 kazans** outside, all ready by **11:00**, plov **gone after 15:00**, not on the evening menu. Guests either **queue at a kazan** or sit and wait for a waitress. Add-ons: raisins, pumpkin, chicken/quail egg, sousma, achuchuk salad, tea; **takeaway in containers**; no alcohol. **[OLD: Oct 2018, blog]** — [mytashkent.uz](https://mytashkent.uz/2018/10/04/tsentr-plova-ili-palchiki-oblizhesh-po-uzbekski/)
  - Aggregated guide claims: 5 large kazans of up to 3 t each, 5 head oshpaz plus helpers; "at least a thousand portions" sold within a few hours; plov finished by lunch; advice to come 10:00–13:00 or after 15:00 for a fresh batch; two plov types (Tashkent wedding, devzira); takeaway in containers. Figures conflict between sources. **[TRAVEL/BLOG, dates unclear]** — [turi-uzbekistana.ru](https://turi-uzbekistana.ru/stati/center-plova); [touristpro.com](https://touristpro.com/guides/topic/%D1%81%D1%80%D0%B5%D0%B4%D0%BD%D0%B5%D0%B0%D0%B7%D0%B8%D0%B0%D1%82%D1%81%D0%BA%D0%B8%D0%B9-%D1%86%D0%B5%D0%BD%D1%82%D1%80-%D0%BF%D0%BB%D0%BE%D0%B2%D0%B0)
- **Minor Osh (Tashkent, Apr 2026):** kazans visible to guests. **1 portion 55,000 / 0.75 portion 53,000 so'm** for to'y oshi or choyxona oshi; qaynatma sho'rva 45,000. Add-ons: **extra meat 30,000; qazi 11,000; quail egg 2,000 each**; salads 15,000–20,000+; **non 8,000**; tea/juice 5,000–15,000. Fast service. **[VERIFIED]** — [Afisha.uz, 23.04.2026](https://www.afisha.uz/uz/restaurants/2026/04/23/minor-osh)
- **Abdujalil osh markazi (Tashkent, Jan 2025):** open **10:30–23:00**, so this centre has an evening osh (olive-oil osh in the evening, to'y oshi and choyxona palov during the day). **QR menus on every table**. Prices given per **0.7 portion**: osh 40,000 (35,000) and **"komplekt"** (osh + sides set) 57,000 (52,000). The bracketed second price is unexplained, possibly a discount or a different size. Guests can request **raisins or other customisations**. The reviewer's order total was 74,800 so'm. **[VERIFIED]** — [Afisha.uz, 09.01.2025](https://www.afisha.uz/uz/gorod/2025/01/09/abdujalil-osh-markazi)
- **Nahor oshi / event plov:** Tashkent's 2019 regulation on family celebrations limits guests (about 100 for bride send-off events, about 250 for weddings). No published per-person or per-kazan catering prices were found. **[secondary]** — [Azattyq Asia](https://www.azattyqasia.org/a/33303686.html)

### Inferences
- **Batch/kazan inventory:** the POS needs a "batch" object (kazan #, type of osh, planned portions or kg, start time). It should decrement on each sale weighted by portion fraction (1 / 0.75 / 0.7 / 0.5) and **auto-stop-list at zero**. A manual "kazan tugadi" (sold out) button should push to all channels (counter, waiters, Telegram/delivery menu). Kazans finish around 14:00–15:00, so the "remaining portions" counter is the main operational screen at lunch.
- **Portion-fraction modifiers and sets ("komplekt")** with set pricing; per-kg sale for takeaway and events; **container (takeaway) surcharge** item.
- **Throughput:** thousands of portions in about 3–4 hours (≈ 7,000/day claimed at the largest centre) means **one-tap items and preset combos**, a queue/order number, possibly **pre-pay at counter, then show a ticket at the kazan window**, and no table assignment for counter guests. The same site also runs a **waiter-served hall**, so a **hybrid counter + table** flow is required.
- **Pre-orders for events** (nahor oshi, to'y, ma'raka): deposit, date/time, persons count → kg of rice/meat, delivery/kazan rental lines. Needed even if I found no pricing source.
- **Yield/cost tracking per kazan** (rice, meat, carrot, oil, chickpeas, zira per kg, as in the Palov indeksi basket) would let owners check portions per kazan against sales, which is the main leakage control in a batch business.

### Gaps
- No verified figure for **kg per kazan** or **portions per kg** at commercial osh markazi (e.g., 1 kg of rice ≈ N portions).
- No source on **how plov centres pay staff, count cash, or which POS (if any) they use**. Besh Qozon's lack of a menu (2024) and the lost items suggest manual ordering, but this is not proof.
- No published **nahor oshi** catering price per person or per kazan for 2025–2026.

---

## 3. Canteens (oshxona / stolovaya, incl. workplace/self-service)

### Takeaway
By law an **oshxona (stolovaya) is self-service only**: tray line, items priced at the cashier. Average checks in Tashkent canteens are about **32,000–50,000 so'm**. Workplace feeding is organised either via the company's own canteen with **talons (vouchers) or ledgers (vedomost')** or via contracted caterers. The meal value is taxable employee income. No primary data was found on daily menus, kompleks-obed prices at stolovayas or weighed items.

### Cited Findings
- Competition Committee (21 Nov 2025), citing CoM Resolution 75: **oshxona = self-service only** ("o'z-o'ziga xizmat ko'rsatish usuli qo'llaniladi"). Cafés and buffets may use self-service or waiters. **Kichik oshxona** = quick service of simple dishes and may specialise (e.g., **kebab or samsa**). **[VERIFIED]** — [raqobat.gov.uz](https://raqobat.gov.uz/uz/raqobat-qomitasi-respublikamizdagi-barcha-turdagi-umumiy-ovqatlanish-korxonalarini-ogohlantiradi/); [spot.uz RU](https://www.spot.uz/ru/2025/11/21/tips-catering)
- 2GIS Tashkent "Столовые" listings show average checks of about **32,000–50,000 so'm per person**. This is user-reported data and may be stale. **[aggregator]** — [2GIS Tashkent canteens](https://2gis.uz/tashkent/search/%D0%A1%D1%82%D0%BE%D0%BB%D0%BE%D0%B2%D1%8B%D0%B5/rubricId/166)
- "Kompleks obed" offers in Tashkent: restaurant business lunches "from 79,000 and 99,000 so'm" (2GIS); delivered office lunch sets at **31,000–40,000 so'm** (OLX) and 35,000 so'm (BirBir). These are undated listings. **[aggregator, unverified]** — [2GIS "Комплексные обеды"](https://2gis.uz/tashkent/search/%D0%9A%D0%BE%D0%BC%D0%BF%D0%BB%D0%B5%D0%BA%D1%81%D0%BD%D1%8B%D0%B5%20%D0%BE%D0%B1%D0%B5%D0%B4%D1%8B); [OLX.uz](https://www.olx.uz/uslugi/q-%D0%BA%D0%BE%D0%BC%D0%BF%D0%BB%D0%B5%D0%BA%D1%81%D0%BD%D1%8B%D0%B5-%D0%BE%D0%B1%D0%B5%D0%B4%D1%8B/); [BirBir](https://birbir.uz/ru/tashkent/cat/uslugi/dostavka-yedy-i-produktov/o/kompleksnyy-obed-bystroye-pitaniye-dostavka-tashkent-42358223)
- **Workplace feeding (Uzbekistan):**
  - An employer can (a) pay a meal compensation within salary, or (b) feed staff via **its own canteen or a contract with an outside caterer**. The procedure should be fixed in the collective agreement or a local act. **[VERIFIED summary of accounting media]** — [buxgalter.uz – Как организовать питание работников](https://buxgalter.uz/publish/doc/text191085_kak_organizovat_pitanie_rabotnikov?utmtag=other_plan); [bss.uz](https://www.bss.uz/article/735-pitanie-sotrudnikov-za-schet-predpriyatiya-kakimi-nalogami-oblagaetsya-i-kak-uchityvaetsya)
  - With an own canteen, meals should be documented by **issuing special talons or keeping proper ledgers (vedomosti)**. Meal value counts as employee **income (PIT + social tax)**. **[secondary]** — [bss.uz](https://www.bss.uz/article/735-pitanie-sotrudnikov-za-schet-predpriyatiya-kakimi-nalogami-oblagaetsya-i-kak-uchityvaetsya)
  - Whether **deducting meal cost from salary** is lawful is disputed. One 2014 reader comment called it illegal payment in kind, while norma.uz Q&As mention "accrual and deduction" for meals as a practice. **[UNCLEAR, partly OLD]** — [gazeta.norma.uz, Обеды на рабочем месте](https://gazeta.norma.uz/publish/doc/text103927_obedy_na_rabochem_meste?paper=ntv)

### Inferences
- POS for a stolovaya: a **cafeteria-line mode** where the cashier taps items on a tray with no table and no kitchen ticket. It needs a **daily menu** (enable or disable items per day), **weight items (per 100 g / per kg)**, and **set "kompleks" items** with fixed prices.
- Workplace canteens need **employee accounts**: talon/voucher redemption, per-employee daily limits, company-paid vs. employee-paid split, and a **monthly statement per employer/department** for payroll or accounting. A balance per employee is effectively a **nasiya/credit account settled by the employer**.
- Since oshxona is self-service by law, **no waiter role and no service charge** in this mode.

### Gaps
- No primary source (2025–2026) on **typical stolovaya menus, number of items, daily covers, or use of scales/weighed salads** in Uzbekistan.
- No source on **corporate meal-card or voucher systems** actually used in Uzbekistan (e.g., bank-issued meal cards); I found none.
- No data on **kompleks obed prices at true stolovayas** (only restaurants and delivery sets).

---

## 4. Samsa, tandir non bakeries and national fast-casual (lag'mon, shashlik, somsa points)

### Takeaway
These are counter or quick-service formats. Samsa sells **per piece (about 8,000–10,000 so'm in a Tashkent chain)** or **per kg (mini-samsa)**, and tandir bread at **3,000–25,000 so'm** depending on size and place. Shashlik/samsa cafés show average checks of about **30,000–50,000 so'm** (budget) to **60,000–100,000** (mid). Production is batch-based (tandir loads). I found no reliable data on output per day, wholesale to shops, or pre-orders.

### Cited Findings
- Safia Bakery (Tashkent chain) menu: samsa with potato or chicken **8,000 so'm**, meat **8,500**, pumpkin **10,000** per piece; **mini-samsa sold per kg** (e.g., 17,000 so'm per kg for the chicken one as displayed). The date of the prices is unclear. **[VENDOR menu]** — [safiabakery.uz](https://safiabakery.uz/ru/menu/myasnaya-vipechka)
- Bread prices in Tashkent (June 2025 market data): **3,000 to 25,000 so'm** per loaf, depending on size and seller. **[secondary]** — [anhor.uz](https://anhor.uz/uzl/ichkaridan-nazar/toshkent-va-astana-bozorlaridagi-narx-qancha)
- Most bread is produced industrially at bread combines, while small bakeries bake in tandirs (novvoy). This is a general encyclopaedic statement. — [haqida.su/non](https://haqida.su/non/)
- Parkent "green samsa" bakery: headline claims **10,000 samsa/day** with **10–15 staff**. **[OLD (~2022), blog, unverified]** — [pikabu.ru](https://pikabu.ru/story/10_000_shtuk_samsa_v_den_sukok_mador_samsa_9197208)
- Average checks on 2GIS cards (Tashkent): "Shashlik&somsa" **35,000 so'm**; lag'mon/samsa cafés **35,000–50,000**; mid-range samsa places about **60,000**; Lagman24 shows 45,000 on one card and 100,000 on another, which is inconsistent. **[aggregator]** — [2GIS "Шашлык самса кафе"](https://2gis.uz/tashkent/search/%D0%A8%D0%B0%D1%88%D0%BB%D1%8B%D0%BA%20%D1%81%D0%B0%D0%BC%D1%81%D0%B0%20%D0%BA%D0%B0%D1%84%D0%B5); [2GIS "Lagman"](https://2gis.uz/tashkent/search/Lagman)
- Tandir ovens for samsa and non are sold on OLX from about **650,000 so'm** (electric about 908,000), and bakery tandirs at about **5 million so'm**. This indicates low entry cost and many micro-operators. **[listings]** — [2GIS/OLX search summary](https://2gis.uz/tashkent/search/%D0%A2%D0%B0%D0%BD%D0%B4%D1%8B%D1%80%D0%BD%D0%B0%D1%8F%20%D1%81%D0%B0%D0%BC%D1%81%D0%B0); [smart-market.uz tandir](https://smart-market.uz/product/977017)
- "Samsa na zakaz" (samsa to order) is a common OLX service category in Tashkent, which shows **pre-orders for events** exist. — [OLX.uz "самса на заказ"](https://www.olx.uz/uslugi/tashkent/q-%D1%81%D0%B0%D0%BC%D1%81%D0%B0-%D0%BD%D0%B0-%D0%B7%D0%B0%D0%BA%D0%B0%D0%B7/)

### Inferences
- **Mixed units on one receipt:** pieces, kg (mini-samsa, shashlik per skewer or per kg, salads by weight) and portions. The POS needs a quantity keypad with decimals and optionally a scale.
- **Batch production log** (each tandir load: samsa type × count). Stock counts down with sales, then the item is automatically sold out. End-of-day **unsold or waste write-off** shows leakage.
- **B2B/wholesale customers** (shops, offices) with a different price list, invoice/nakladnaya and **credit (nasiya) balances**: likely for tandir non producers. This is inferred because no source was found.
- **Pre-orders** (samsa for events: date, count, deposit), and a **fast counter flow** where cash or QR is taken immediately, the order is printed or shown and the customer is called by number.

### Gaps
- No reliable 2025–2026 data on **daily output, staff, or revenue** of samsa/tandir points or lag'mon/shashlik cafés.
- No source on **wholesale bread distribution** practices (credit terms, returns of unsold bread).
- No source on whether these micro-operators currently use any POS.

---

## 5. Staff roles, owner control problems, and what owners want from a simple POS

### Takeaway
Typical roles are oshpaz (head cook) with helpers, kassir, ofitsiant, and in choyxonas a choyxonachi (teahouse keeper). Monthly pay in Tashkent is about **2–5 million so'm**. The documented control problems are generic: unrecorded orders, deleted items, cash taken by waiters and cashiers. Uzbek vendors respond with offline operation, Android phone waiter apps, Uzbek (Latin + Cyrillic) UI, Telegram-bot reports to the owner, and low monthly prices (**99,000–690,000 so'm/month**).

### Cited Findings
- Pay benchmarks (Tashkent job ads, mostly undated, 2025): cook **4.5–5.0 mln so'm/month**; cook assistant **3.9–5.1 mln**; waiter about **4.0 mln**; MaxWay (fast food) cashier or cook **from 2.0 mln + KPI**. **[listings, unverified]** — [OLX.uz oshpaz](https://www.olx.uz/oz/rabota/tashkent/q-oshpaz/); [Telegram Ish Toshkent](https://t.me/s/Ish_Toshkent?before=3913)
- Plov centre staffing (guide): 5 head oshpaz plus several helpers at a large centre. **[TRAVEL/BLOG, unverified]** — [turi-uzbekistana.ru](https://turi-uzbekistana.ru/stati/center-plova)
- Typical theft schemes in catering: cashiers, waiters and managers with access to cash. Controls include push alerts on **cancellations and manual discounts** and blocking item deletion or refunds without admin confirmation. Waiters sometimes **do not ring up part of an order and pocket the cash**. These are Russian/Kazakh vendor articles, not Uzbekistan-specific. **[VENDOR]** — [kontur.ru](https://kontur.ru/market/spravka/56921-kassiry_v_obshchepite_voruyut); [joinposter.kz](https://joinposter.kz/blog/management/how-to-prevent-theft-in-restaurant)
- In Tashkent, owners adopt automation for **cost control and transparency** (R-Keeper Uzbekistan dealer article). **[VENDOR]** — [rkeeper.uz](https://rkeeper.uz/stati/post/avtomatizaciya-restoranov-v-tashkente-kak-uvelichit-pribyl-i-snizit-rashody)
- What local POS vendors emphasise (as a proxy for owner demand):
  - **Reca (inware.uz):** works **without internet** (needs a connection about once a week for licence checks) and stores data on the owner's own device. Waiter app on **Android smartphones**. **Shift and day reports via a Telegram bot**. Prep-station printers. Tariffs **330,000 so'm/month** (small café) to 1.98 mln, or one-time 6.5–25.5 mln. Case: **Nizom ota, Andijan** (large national-cuisine restaurant, 10 prep stations, **30 waiters**). Claims 1,000+ entrepreneurs in 12 regions since 2018. **[VENDOR]** — [kafe.inware.uz](https://kafe.inware.uz/)
  - **Biznex:** offline register with sync; UI in **Uzbek Latin, Uzbek Cyrillic, Russian, English**; **"qarzga sotuv" (credit sale)** as a payment type; **split checks / multiple open checks per table**; halls and tables with reservations; recipe/portion stock deduction; **349,000 / 549,000 so'm/month**. "Choyxona" appears as a business type in its demo form. **[VENDOR]** — [biznex.uz](https://www.biznex.uz/)
  - **Mison:** cashier works **offline**; apps for **Android and iOS** (cashier, waiter, courier, owner); languages **Uzbek, Russian, Karakalpak**; bill splitting; halls and tables; a stop-list shared across all channels; **390,000 / 690,000 so'm per venue per month**. The application form lists "choyxona", "oshxonasi bor restoran" and "fastfud". **[VENDOR]** — [mison.uz](https://mison.uz/)
  - **VKassa:** offline mode; staff and owner apps on Google Play; **split payment**; debt tracking (qarzdorlik); stop-list; hall/VIP/outdoor tables; tariffs **99,000–1,199,000 so'm/month**. **[VENDOR]** — [vkassa.uz](https://vkassa.uz/)
  - **Dastur-xon:** QR menu, cashier, cook and waiter panels; **99,000 so'm/month**; "no hardware required". **[VENDOR, search snippet only]** — [dastur-xon.uz](https://dastur-xon.uz/)
- None of the vendor pages I checked explicitly mention **weight-based sales**, **kazan/batch stock**, **room/place fees**, or **service-charge %** features (Reca, Biznex, Mison and VKassa pages checked). **[VERIFIED absence on those pages]**

### Inferences
- Owner "must-haves" for these formats: (1) **every order goes through the device** (waiter phone or counter) before the kitchen or kazan serves it; (2) **void/discount/delete needs owner PIN, with a Telegram alert**; (3) **shift close** with an expected cash total vs. counted cash; (4) **kazan/batch reconciliation** (portions produced vs. sold); (5) a **daily Telegram summary**; (6) **offline-first** on a cheap Android phone or tablet; (7) **Uzbek Latin + Cyrillic + Russian** UI; (8) ≤2–3 taps per common item (osh 1 / 0.7, non, choy).
- The gaps in local vendors (no batch/kazan stock, no weight lines, no room fee, no gap/group split, weak customer nasiya) are a plausible **differentiation space** for a POS targeting choyxona, osh markazi and stolovaya. This is an inference from the feature pages only.

### Gaps
- No Uzbek interviews with choyxona or plov-centre owners about **cash leakage** or **staff theft**.
- No official or survey data on **POS penetration among small national-cuisine outlets**.

---

## 6. Prices, volumes, average check, seats (Tashkent vs. regions)

### Takeaway
One plov portion costs about **22–36k so'm** in the regions vs. about **36k+** in Tashkent (official Dec 2024). Fashionable Tashkent plov centres charge **36–65k** in 2024–2026. Per-person spend in national restaurants and choyxonas is **45–90k**, canteens **32–50k**, and budget shashlik/samsa about **35–50k**. The largest plov centre claims **>7,000 portions/day**. Seat counts are not published.

### Cited Findings
- Official portion averages by region, Dec 2024 (table in §2): national 28,885; Tashkent city 35,744; Namangan 22,201. Plov-centre portion in January 2025: Tashkent 35,700 (most expensive), Namangan 23,000 (cheapest). **[VERIFIED / secondary]** — [Gazeta.uz](https://www.gazeta.uz/oz/2025/01/03/plov-index/); [kun.uz, 06.02.2025](https://kun.uz/news/2025/02/06/yanvar-oyi-uchun-palov-indeksi-elon-qilindi)
- Tashkent plov centre menus: Besh Qozon 36–42k (Nov 2024); Abdujalil 40k per 0.7 portion, 57k for a set (Jan 2025); Minor Osh 53–55k (Apr 2026); Besh Qozon 45–65k (Apr 2026 travel guide). — sources in §2.
- Per-person spend: choyxona/national 45,000–90,000 so'm (2026) — [qogozcha.uz](https://qogozcha.uz/restoranlar); stolovaya 32,000–50,000 — [2GIS](https://2gis.uz/tashkent/search/%D0%A1%D1%82%D0%BE%D0%BB%D0%BE%D0%B2%D1%8B%D0%B5/rubricId/166); shashlik/samsa 35,000–50,000 — [2GIS](https://2gis.uz/tashkent/search/%D0%A8%D0%B0%D1%88%D0%BB%D1%8B%D0%BA%20%D1%81%D0%B0%D0%BC%D1%81%D0%B0%20%D0%BA%D0%B0%D1%84%D0%B5).
- Volumes: Besh Qozon >7,000 portions/day from 10 kazans (2026 travel guide); ~3,000 lunch guests/day (video); "10 tons/day" (travel listing). These are mutually inconsistent and unverified. — sources in §2.
- Example (not choyxona): Tashkent café "Ugolok", **up to 120 seats**, check about **500,000 so'm** (per table/group, presumably). **[search snippet, unverified]** — [weproject.media](https://weproject.media/articles/detail/kafe-i-restorany-tashkenta-kotorye-pomnyat-sovetskiy-tashkent/)

### Inferences
- With checks of 30–90k so'm and very high volume at lunch, POS **per-transaction cost and speed** matter more than features. Percent-of-turnover pricing would be unattractive to plov centres.

### Gaps
- No published **seat counts**, **daily covers** or **revenues** for choyxonas, stolovayas or regional plov centres.
- No 2025–2026 regional (non-Tashkent) menu reviews for plov centres.

---

## 7. Payment habits

### Takeaway
Cash is still significant but falling: cash is **16.9% of the economy's money (1 Apr 2026)**, and card operations are **81% cashless** by 2025. Since **1 July 2026 all legal entities in trade and services must accept the state "UzQR"** (merchant fee 0.65%, or lower by bank), alongside Click/Payme/Uzum QR, cards and cash. A POS should record **multiple tenders per bill** (cash + card + QR).

### Cited Findings
- UzQR (Central Bank): mandatory acceptance for all legal entities in trade and services **from 1 July 2026**. It does **not** replace other methods (cards, cash, Click/Payme/Uzum QRs remain lawful). Merchant fee **0.65%** (some acquirers 0.25%). Settlement within 1 business day. Works with UZCARD, HUMO and sum Visa. Some merchants removed Click/Payme QR codes after the change. **[VERIFIED]** — [spot.uz, 04.07.2026](https://www.spot.uz/ru/2026/07/04/uzqr-payment)
- Decree **UP-246 (10.12.2025)**: cash ban from 1 Apr 2026 for alcohol, tobacco, real estate, cars and goods over 25 mln so'm. Target of **75% cashless** retail and service payments by 2030. **[secondary legal media]** — [buxgalter.uz](https://buxgalter.uz/publish/doc/text212358_nalichnye_raschety_v_uzbekistane_zapreshcheny_pri_realizacii_ryada_tovarov_i_uslug_polnyy_obzor_up-2461); [norma.uz](https://www.norma.uz/novoe_v_zakonodatelstve/nalichnye_raschety_v_uzbekistane_zapreshcheny_pri_realizacii_ryada_tovarov_i_uslug_polnyy_obzor_up-246nalichnye_raschety_v_uzbekistane_zapreshcheny_pri_realizacii_ryada_tovarov_i_uslug_polnyy_obzor_up-246)
- Cash share of the economy is **16.9% at 1 Apr 2026** (−1.7 pp y/y). **[secondary, CBU data]** — [Sputnik Uzbekistan, 05.05.2026](https://uz.sputniknews.ru/20260505/dinamika-ispolzovaniya-nalichnoy-i-beznalichnoy-oplaty-v-uzbekistane-57338764.html). Cashless share of card operations rose **68% → 81% (2021–2025)** (KPMG). — [UzDaily](https://www.uzdaily.uz/ru/kpmg-beznalichnye-raschioty-v-uzbekistane-vyrosli-do-72-vvp/)
- Tourists are told to **pay cash at the plov centre counter** (2026 guide). **[TRAVEL/BLOG]** — [uz-safar.com](https://www.uz-safar.com/ru/blog/plov-festival-tashkent-2026-guide)

### Inferences
- Must support: split tender; **static QR display** (UzQR + Click/Payme) with manual confirmation or, ideally, a payment-status callback; cash change calculation; tips are not common (not verified).

### Gaps
- No HoReCa-specific cash/QR share in Uzbekistan; no data specific to choyxona or osh markazi payment mix.

---

## 8. Current POS usage in these formats

### Takeaway
Full-service restaurants and chains use iiko (≈1,000 venues in Uzbekistan, 2023 vendor claim), R-Keeper, Poster (with the Multikassa fiscal module) and a crowd of local Uzbek POS products (Reca, Biznex, Mison, VKassa, Dastur-xon, Jowi). I found **no evidence** that typical choyxonas, plov centres, stolovayas or samsa points use any POS. Indirect evidence (no menu at Besh Qozon in 2024, paper nasiya ledgers in small business) suggests that **paper, memory and cash drawers** dominate. This is not verified.

### Cited Findings
- About **1,000 restaurants in Uzbekistan use iiko**; iiko is used by about one-third of Uzum Tezkor's Tashkent partners (~150 of 400+). Stop-lists sync automatically to the delivery menu. **[VENDOR/sponsored, 07.08.2023 — OLD]** — [spot.uz](https://www.spot.uz/ru/2023/08/07/uzum/)
- iiko is resold in Uzbekistan by partners (e.g., Kafesoft, "launch from 1 day, 24/7 support in Russian and Uzbek"). **[VENDOR ad]** — [OLX.uz](https://www.olx.uz/d/oz/obyavlenie/avtomatizaciya-iiko-pod-klyuch-programma-ajko-v-samarkande-ID4qfZz.html)
- Poster integrates with **Multikassa** (a registered virtual cash register in Uzbekistan): IKPU (MXIK) code selection and Z-reports inside Poster. **[VENDOR]** — [joinposter.com/applications/multikassa](https://joinposter.com/en/applications/multikassa)
- Jowi (Tashkent): local module plus cloud sync that works when the internet drops; waiter, manager, accountant and cook modules; cost and stock accounting. **[VENDOR/directory]** — [startpack.ru](https://startpack.ru/application/jowi); [top.uz](https://top.uz/company/jowi)
- R-Keeper Uzbekistan case: Ostaria Maria / Shvili (Tashkent) with 1C integration and mobile waiters. This is a full-service restaurant, not a national format. **[VENDOR]** — [rkeeper.uz](https://rkeeper.uz/stati/post/avtomatizaciya-ostaria-maria-i-shvili-v-tashkente-kak-my-postroili-upravlyaemyj-restoran-ot-seti-do-ucheta-i-servisa)
- Local Uzbek POS products and prices (2026): Reca 330k–1.98 mln so'm/month; Biznex 349k–549k; Mison 390k–690k; VKassa 99k–1.199 mln; Dastur-xon 99k. Details in §5. **[VENDOR]**
- Besh Qozon had **no printed or QR menu** in Nov 2024, and items were lost from an order. Abdujalil osh markazi had **QR menus on every table** in Jan 2025, so practice varies among plov centres. **[VERIFIED reviews]** — [Afisha.uz 2024](https://www.afisha.uz/ru/gorod/2024/11/21/plove); [Afisha.uz 2025](https://www.afisha.uz/uz/gorod/2025/01/09/abdujalil-osh-markazi)

### Inferences
- Why these formats don't use POS (hypotheses, unverified): iiko and R-Keeper are priced and designed for full-service restaurants; licence plus hardware cost is high relative to a 30–50k so'm check; a cash-heavy, informal model; and no fit for **kazan batches, per-kg group dishes, room fees and nasiya**. The new regulatory push (fiscal integration and UzQR; see `fiskal_background.md`) is likely to force adoption of *some* compliant system, which creates an opening for a cheap, Android, offline-first POS tuned to these formats.

### Gaps
- No survey or media data on **share of choyxonas, plov centres, stolovayas or samsa points that use POS**, nor on use of **Excel**.
- Could not verify whether major plov centres (Besh Qozon, Milliy Taomlar, Rayhon, Caravan) use iiko, R-Keeper or local systems. No public case studies were found.
