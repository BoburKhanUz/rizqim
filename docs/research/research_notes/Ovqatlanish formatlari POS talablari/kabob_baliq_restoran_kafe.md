# Kabobxona, baliqxona va to'liq xizmatli restoran/kafe: POS talablari uchun ish jarayonlari (Uzbekistan, 2023–2026)

> Research scope note: about 18 search/fetch calls. Web sources on *operational* details (who weighs fish, how tickets reach the mangal, waiter pay) are very thin online. Most of those details live in practice and in 2GIS/Yandex reviews, and 2GIS, TripAdvisor and gorodwiki returned 403 or only snippets. Everything below is labelled as either a sourced finding or an inference. Prices are in UZS (so'm) unless stated. Delivery-aggregator menus (eda7.ru, eda-dostavka.com) are third-party mirrors, so their prices may lag the venue's own menu.

## 1. How do baliqxonas price and record weighed fish (raw weight before cooking? per 100 g? who weighs?), and how is it written on the bill?

### Takeaway
Tashkent fish restaurants publish fish prices **per 1 kg**, with cooking method built into the price (fried vs on the mangal, whole vs "sheyka" neck cut, headless/tailless). The only first-hand description found (Chinaz fish cafes, 2013, old) shows **staff weighing the customer-chosen raw piece on the spot**. I found no 2023–2026 source that says explicitly whether the billed weight is raw or cooked, or how it appears on the receipt. This is the biggest open question for the POS design.

### Cited Findings
- **Per-kg pricing on the menu, by species, cut and cooking method** (Sazanchik El Gaucho, Tashkent, delivery-menu mirror): fried carp, no head or tail, 1 kg = 240,000; carp neck ("shейка") fried, 1 kg = 360,000; carp on the mangal, 1 kg = 245,000; carp neck on the mangal, 1 kg = 370,000; salmon on the mangal, 1 kg = 380,000; zander (sudak) fried or on the mangal, 1 kg = 350,000 — [eda7.ru, Sazanchik El Gaucho menu](https://eda7.ru/tashkent/rest/sazanchik-el-gaucho-28vmd/)
  - The same menu lists garnishes, bread and salads as **separate fixed-weight portions**: boiled rice 150 g = 23,000; baby potatoes 400 g = 50,000; fries 150 g = 30,000; grilled corn 300 g = 40,000; Khorezm flatbread 450 g = 25,000; thin flatbread 400 g = 25,000; "patyr" 450 g = 25,000; salads 350–550 g = 70,000–400,000. No tea is listed. — [eda7.ru](https://eda7.ru/tashkent/rest/sazanchik-el-gaucho-28vmd/)
- **Other Tashkent per-kg or per-portion fish prices** (from search-result snippets of aggregator menus): Rayhon milliy taomlari carp 1 kg = 170,000; Xorrot Restaurant carp on the mangal 1 kg = 228,000; Restoran Soy fried carp 900 g = 190,000; Tarnov carp 500 g = 96,000; Gijduvan carp 300 g = 70,000 — [eda-dostavka.com Rayhon](https://eda-dostavka.com/tashkent/restorany/rayhon-oyilaviy-restaran/); [dostavka-eda.com Soy](https://dostavka-eda.com/tashkent/place/restoran_soy/); [eda-dostavka.com Xorrot](https://eda-dostavka.com/tashkent/restorany/xorrot-restaurant/); [eda-dostavka.com Tarnov](https://eda-dostavka.com/tashkent/restorany/tarnov-/); [eda-dostavka.com Gijduvan](https://eda-dostavka.com/tashkent/restorany/gizhduvan-4wm2j/). I verified Rayhon's 170,000 per kg by fetching the page. The others are search snippets only.
  - So some venues sell fish **per kg (variable weight)**, while others sell **fixed-weight portions** (300 g, 500 g, 900 g).
- **A TripAdvisor review of Sazanchik (Shota Rustaveli 22a)** says "1 kg of fried carp cost 280 thousand so'm". The review is about 638 days old (roughly early 2025). The page itself returned 403, so I have the snippet only — [TripAdvisor Sazanchik](https://www.tripadvisor.com/Restaurant_Review-g293968-d19780059-Reviews-Sazanchik-Tashkent_Tashkent_Province.html)
- **The weighing ritual (old, 2013, Chinaz fish cafes about 50 km from Tashkent):** the cook takes a whole fish (a 5–6 kg catfish) from a large tub and cuts it with an axe into eight equal portions. The customer **picks the portion**, and the head is optional and separate. The chosen piece is **weighed on the spot on old Soviet scales**, then fried in a kazan and served with flatbread and hot tea. "You pay for exactly what you chose." — [iqmena LiveJournal, 24 Jan 2013](https://iqmena.livejournal.com/403341.html) **[OLD: 2013]**
- **Directory context:** Tashkent has dedicated fish restaurants such as Kaspiyka (Tashkent City Mall, average check 250,000), Sazanchik, RybaLove and Qanotchi Baliq. I found no weighing procedure documented for any of them — [2GIS "Рыбный ресторан"](https://2gis.uz/tashkent/search/%D0%A0%D1%8B%D0%B1%D0%BD%D1%8B%D0%B9%20%D1%80%D0%B5%D1%81%D1%82%D0%BE%D1%80%D0%B0%D0%BD); [magg.uz](https://magg.uz/ru/tashkent/fish-restaurant); [resto.uz RybaLove](https://resto.uz/catalog-zavedeniy/tashkent/rybalove-4740/)
- **Bill accuracy complaints:** in reviews of Qamish (Khorezm cuisine, Mukimi St.), a guest reports dishes worth 250,000 added to a 2.5M bill that "we did not eat" and advises others to check the bill. This is a search snippet; the page returned 403 — [gorodwiki Qamish reviews](https://uz.gorodwiki.ru/tashkent/qamish-xorazm-taomlari-13270-otzyvy)

### Inferences
- The "headless, tailless" wording and the "sheyka" cut both point to the price being tied to the **raw portion weighed after butchering**, before cooking. Chinaz describes the same flow: choose, cut, weigh raw, then cook. This is an inference, not confirmed for 2023–2026 Tashkent venues.
- **POS implication:** the item needs a `price_per_kg` and a `sold_by_weight` flag. The waiter or cook has to enter the actual grams, either from a scale or typed in by hand, so the order line shows "Sazan (mangal) 1.35 kg × 245,000 = 330,750". The system should also support fixed-weight portion items (the Soy, Tarnov and Gijduvan pattern) for the same species. Cut type (whole, headless, sheyka) and cooking method (fried, mangal) work best as separate SKUs, because venues price them differently.
- Weight disputes and padded bills are a plausible owner and guest pain, given the Qamish complaint. A printed weight, or a scale-photo or timestamp audit attached to the order line, would address it. This is design reasoning, not a sourced need.

### Gaps
- I found no 2023–2026 source that says who weighs the fish (cook, waiter or administrator), whether a **digital or label-printing scale** is used, or whether the weight is entered into a POS.
- I couldn't confirm raw versus cooked billed weight, or whether venues price per 100 g. Every published menu I found used per kg or a fixed portion.
- I found no example of a printed bill or schyot for a fish restaurant showing the weight line.
- I didn't find whether live fish tanks (aquarium selection) are common in Tashkent restaurants, versus the fish being pre-portioned. The search returned nothing specific.

## 2. How do kabobxonas take orders for mangal (per six, mixed sets) and how does the mangal station get the order?

### Takeaway
Shashlik in Uzbekistan is ordered and priced **per skewer ("six"/шампур, dona)**. Menus usually state the skewer's weight (110–150 g), and types are priced differently: qiyma/ground, lamb chunk, beef chunk, chicken, liver, tomato. Some sellers price per 100 g instead. I found **no source describing how the order physically reaches the mangalchi** (paper slip, shouting, or a printer at the mangal).

### Cited Findings
- **A 2026 price guide** says one skewer of shashlik costs about 15,000–30,000 so'm (about $1–2.5) in Tashkent — [Sputnik8, Prices in Tashkent 2026](https://www.sputnik8.com/ru/tashkent/pages/tseny-v-tashkente-v-2026-eda-taksi-zhilyo-razvlecheniya)
- **A Tashkent restaurant menu (Rayhon, delivery mirror)** lists shashlik **per 150 g unit**: lamb chunk 150 g = 23,000; ground (molotyy) 150 g = 18,000; beef chunk 150 g = 23,000; chicken 150 g = 17,000. Kazan kabob is 400 g = 84,000, and patyr is 100 g = 8,000. Salads come in 300–350 g portions — [eda-dostavka.com Rayhon](https://eda-dostavka.com/tashkent/restorany/rayhon-oyilaviy-restaran/)
- **A delivery shashlik seller (Shampurik)** prices per 100 g: ground 23,500; "Marvarid" 26,500; "Napoleon" 120 g 32,500; chicken 20,500; beef 29,000. Another delivery menu ("Шашлык & Сомса") prices per portion weight: chicken 110 g = 21,000; lamb 140 g = 37,000; tomato 110 g = 14,000 — [shampurik.uz](https://shampurik.uz/); [eda-dostavka.com Шашлык & Сомса](https://eda-dostavka.com/tashkent/restorany/shashlyk-samsa/) (search snippet)
- **Fish is also cooked on the mangal** in fish restaurants (for example "carp on the mangal" at Sazanchik El Gaucho and Xorrot). So the **mangal station serves both meat and fish** — [eda7.ru](https://eda7.ru/tashkent/rest/sazanchik-el-gaucho-28vmd/)
- **Wait-time complaints at the mangal:** reviews of Qamish say "we waited a long time for our shashlik… waited an hour and a half, cancelled the order, but the bill was still issued and demanded" (search snippet) — [gorodwiki Qamish](https://uz.gorodwiki.ru/tashkent/qamish-xorazm-taomlari-13270-otzyvy)
- A 2022 Kun.uz piece ("Oshqoshiq" comparison of three Tashkent kabob kitchens) gave no operational detail **[OLD: 2022]** — [Kun.uz](https://kun.uz/news/2022/11/14/oshqoshiqda-shashliklar-tanlovi)

### Inferences
- The POS needs **count-based items per skewer type**, with a nominal weight shown on the menu. Guests order "5 qiyma, 3 jigar, 2 kuskovoy", so quick-entry quantity buttons matter. Mixed sets ("assorti" platters) are likely sold as a fixed-composition combo item. I found no source for the composition rules.
- Fish and meat both go to the mangal, while salads, garnishes and kazan dishes go to the kitchen and tea or drinks go to the bar or tea station. Station routing should therefore be set per item, not per category, and one bill typically splits across 2–4 stations.
- "Bill demanded for a cancelled order" shows why the system needs explicit item states (ordered, sent to station, cooked, served, voided with a reason and who voided it).

### Gaps
- I found no source on how the mangalchi receives orders (paper copy of the waiter's pad, kitchen printer, KDS, or verbal), or whether shashlik prices are per skewer versus per kg of raw meat in sit-down kabobxonas outside delivery menus.
- I found no source on whether kabobxonas sell raw meat by the kg, where the guest buys meat by weight and the venue grills it. This is common in some CIS markets but was not documented here.
- Peak hours for kabobxonas weren't found in any source.

## 3. Full-service restaurants: typical service charge, how waiters are paid, pre-check vs final receipt, splitting, banquets

### Takeaway
A **service charge of 10–15% is standard on the bill in Uzbek restaurants and cafes, sometimes up to 20%**. One venue charges **25% for private cabins**. In November 2025 the Competition Committee said the fee must be **disclosed before ordering** and **cannot be imposed with no self-service alternative**, and deposits on reservations cannot be forced. Waiter pay seen in job ads is a daily rate plus a small percentage. I found no source on pre-check printing, bill splitting or banquet procedures.

### Cited Findings
- **Regulator, 21 Nov 2025:** many cafes and restaurants add a 10–15% service fee, in some cases up to 20% of the total, and some require **deposits for bookings**. The Competition Committee says restaurants serve through waiters, cafes and buffets may offer waiter service or self-service, and canteens (stolovaya) are self-service only. Establishments must inform guests **in advance** about dish prices, the service fee, and the terms of service. Imposing extra services violates the Consumer Protection Law, and the complaints hotline is 1159 — [Spot.uz, 21.11.2025](https://www.spot.uz/ru/2025/11/21/tips-catering); also [Vaib.uz](https://vaib.uz/2025/11/21/nelzya-navyazyvat-depozit-i-platu-za-obsluzhivanie-komitet-po-konkurenczii-predupredil-restorany-i-kafe/), [Podrobno.uz](https://podrobno.uz/cat/obchestvo/restorany-ne-vprave-navyazyvat-servisnyy-sbor-za-obsluzhivanie-bez-alternativy-komitet-po-zashchite-/), [Daryo.uz](https://daryo.uz/ru/10k5BLmvg/)
- **Venue practice from reviews:** Qamish (Tashkent) charges 15% for service, and 25% instead of 15% in the second-floor cabins. Guests complain the 15% was charged even though "no one served you" (search snippet) — [gorodwiki Qamish](https://uz.gorodwiki.ru/tashkent/qamish-xorazm-taomlari-13270-otzyvy)
- **An older article (2013)** says the service percentage depends on the type of venue **[OLD: 2013]** — [Afisha.uz, 2013](https://www.afisha.uz/ru/gorod/2013/11/28/servis)
- **Waiter pay:** a Marhabo restaurant job ad (Telegram channel "ISH TOSHKENT") lists "Ofitsiant 100 000 so'm + 3%". It's a daily rate plus 3%, and the base of the 3% isn't stated (search snippet) — [t.me/s/ish_toshkent](https://telegram.me/s/ish_toshkent?before=5694). An OLX ad for a waiter at a European-style restaurant in Yakkasaroy offers 170,000–250,000 so'm (apparently per shift/day, but the unit isn't stated in the snippet) — [OLX.uz](https://www.olx.uz/oz/rabota/tashkent/q-vakansiya/). An ish.uz ad (Qibray, about 3 years old) offers a fixed 3,500,000 so'm/month **[OLDER]** — [ish.uz](https://ish.uz/oz/jobs-tashkent-region/2342). A new cafe in Mirzo Ulug'bek lists waiter pay as "negotiable" — [t.me/s/ish_toshkent](https://t.me/s/ish_toshkent?before=3411)
- **Neighbouring context, not Uzbekistan:** Kyrgyzstan banned separate service charges from 1 Jan 2026 — [24.kg](https://24.kg/ekonomika/347532_otmena_chaevyih_vkafe_ofitsiantyi_poluchali_menshe_polovinyi_izprotsentov/). Kazakhstan has the same 10–15% debate — [lada.kz](https://www.lada.kz/kazakhstan-news/157562-uzhin-zakonchilsia-a-osadok-ostalsia-chto-ne-tak-so-schetami-v-restoranakh-kazakhstana.html). The 24.kg headline says that after tips/fees were abolished in cafes, waiters had received less than half of the percentage, which implies the service fee was not fully passed to waiters there.

### Inferences
- The POS must support a **configurable service-charge percentage per hall or zone** (for example 15% main hall, 25% cabin). It must also support turning it off or zeroing it per order (self-service or takeaway), and **printing it on the pre-check before payment** so the venue can show disclosure under the 2025 Committee position.
- Pay of "daily rate + X%" implies the owner needs **per-waiter sales reports** (sales attributed to the waiter who opened the table) to compute the percentage. Whether the X% comes from sales or from the collected service charge is unknown.
- **Reservation deposits** exist, and the regulator scrutinises them, so the system should record a deposit as a prepayment that is credited against the final bill, with a receipt.

### Gaps
- No source states what share of the xizmat haqi reaches waiters in Uzbekistan, or how cash and card tips are recorded.
- I found no source on whether venues print a pre-check ("schyot") before the fiscal receipt, how the bill is split between guests, or how tables are transferred between waiters.
- Banquet practice (set menu per person, deposit, toy/wedding halls) and peak hours weren't found online in this pass.

## 4. What software/POS do such places use today (iiko, R-Keeper, Poster, Jowi, local), and how common is paper?

### Takeaway
The systems visible in Uzbekistan are **iiko, R-Keeper, Poster and Jowi**, plus local players: Neon Alisa (an Uzbek POS with fiscalization built in) and AliPos. Every source I found is vendor marketing, and **I found no market-share or paper-vs-POS adoption data**.

### Cited Findings
- **R-Keeper in Uzbekistan (sponsored article, 18 Mar 2025):** R-Keeper has integrations for fiscalization via a **virtual cash register** and for the **product-marking system**. Add-ons include Pro Bonus (Telegram-bot loyalty with QR codes), Pro Alert (a Telegram bot that alerts on cash operations) and Store House Pulse (AI reports and reordering). The site is rkeeper.uz — [Spot.uz, 18.03.2025](https://www.spot.uz/ru/2025/03/18/r-keeper/)
- **Comparison by Zoomda (an online-ordering platform, ©2026, vendor content):** iiko is described as the "CIS market leader" with strong back-office and analytics, and pricier than the others. **Neon Alisa** is called an "Uzbek POS with the best localization" with "Uzbekistan fiscalization out of the box". **Poster** is a simple Ukrainian SaaS for small venues and is "weak on UZ fiscalization". Their suggestion: iiko for chains of 5+ sites, Neon Alisa for 1–2 sites, Poster for micro-cafes — [zoomda.uz](https://zoomda.uz/en/knowledge-base/iiko-vs-jowi-vs-poster)
- **SmartMenu (a QR-menu vendor)** says it integrates with iiko, Jowi, R-Keeper and Poster and claims 500+ restaurants (vendor claim) — [smartmenu.uz](https://smartmenu.uz/)
- **AliPos** (a local automation product) advertises per-employee access rights and full financial accounting — [alipos.uz](https://alipos.uz/ru/)
- **Jowi architecture (old, non-UZ-specific Habr article):** a local module runs in the restaurant and syncs to remote servers, so it keeps working when the internet drops **[OLD: ~2015, updated 2021]** — [Habr](https://habr.com/en/articles/258679)
- **Generic CIS anti-fraud practice (Russia/Poster blog, not UZ-specific):** common waiter schemes include deleting paid items in collusion with the kitchen, moving items between tables, cancelling a pre-check and printing a new bill, and abusing discounts. Countermeasures are personal logins, forbidding waiters to void, transfer or cancel pre-checks, event logs, and comparing void rates by waiter or shift — [Poster blog](https://joinposter.com/blog/management/how-to-prevent-theft-in-restaurant); [open-s.info](https://open-s.info/blog/sposoby-vorovstva-chast-vtoraya/); [lemma.ru](https://place.lemma.ru/article/poimai-menya-esli-smojesh-kak-oficianty-voruut-dengi-u-restoranov)

### Inferences
- Fiscalization (virtual kassa, product marking) is a selling point that vendors stress specifically for Uzbekistan. A new POS that lacks it would be at a disadvantage against Neon Alisa and R-Keeper.
- Telegram-bot owner alerts (as in R-Keeper Pro Alert) are a local expectation worth matching: real-time voids, discounts and cash events pushed to the owner's Telegram.
- The anti-fraud patterns (voids after a pre-check, moving items between tables, discount abuse) fit the Uzbek review complaints about added items and charges for cancelled orders. An audit log, void reasons, and manager PINs for voids and transfers should be in the MVP.

### Gaps
- I found no independent data on POS penetration or on the share of venues still using paper pads with a manual cash drawer, especially in regional kabobxonas and baliqxonas.
- Pricing for iiko, R-Keeper and Neon Alisa in Uzbekistan wasn't found.
- I couldn't confirm the equipment typically installed (printers per station, waiter handhelds, digital scales at fish counters).
