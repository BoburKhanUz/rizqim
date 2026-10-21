# Choyxona types in Uzbekistan and how each makes money (input for POS design), 2024–2026

## Scope and method

About 30 searches and fetches in October 2026, in Uzbek (Latin and Cyrillic), Russian and English.

**Main new primary data:** the public API of the choyxona directory **chaykhana.uz**. It returned 159 venues, 40 of them with a "placePrice" field (room/place fee). It also returned free-text price notes, menu items and user reviews. Raw data is saved at `/tmp/claude-0/-home-user-rizqim/840a5d2d-14df-532a-aa68-df7c99bf1fa1/scratchpad/chay/details.json`.

**Limits of the directory data:**
- Almost all of it was entered in 2019–2022 (review dates 2019-07 to 2024-12), so it is marked **[ESKI]**.
- It is still the only structured dataset of choyxona room fees and osh-per-kg prices found.

**Fresher sources:**
- The Choyxona Telegram channel (posts to 21 Oct 2026)
- Yandex Maps reviews (2025)
- 2GIS card (2026)
- Daryo (Nov 2025)
- Afisha (Jul 2025)
- Kamolon Telegram (2022–2025)
- OLX (Sep 2026)

**Labels used in these notes:**
- **[ESKI]** = source older than Oct 2024.
- **[ZAIF]** = weak source (single review, ad, unverifiable aggregator).
- "Inference" = my reasoning, not a sourced fact.

**Source to avoid:** qogozcha.uz "Milliy taomlar restoranlari va choyxonalar 2026" looks auto-generated. It uses placeholder-style phones such as "+998 71 234-56-78" and generic addresses. Do not use it as evidence. — [qogozcha.uz](https://qogozcha.uz/milliy-taomlar-restoranlari) [ZAIF]

---

## 1. What types and formats of choyxona exist now, and how they differ

### Takeaway
There are at least five working formats:
1. **Traditional mahalla/guzar choyxona:** cheap or free gathering place for older men, increasingly disappearing.
2. **Room-based ("xonali") choyxona:** the dominant paid format. Groups rent a xona/kabinka for a fee and order food (osh by kg) mostly in advance.
3. **New-format "resto-choyxona":** The Choyxona and similar. Designed kabinkas with PlayStation/karaoke, a European plus Uzbek menu, a high check and a family/mixed clientele.
4. **Osh markazi / national-food centre with a "choyxona osh" product:** open hall, walk-in, osh by portion and by kg, long hours.
5. **Roadside (trassa) choyxona:** larger, tapchan-based, serving drivers.

The public meaning of "choyxona" has shifted: an architect said in Nov 2025 that it is now understood as a place "where food is ordered in advance for a set sum."

### Cited Findings

**Concept shift (2025)**
- Architect Shuhrat Abdullayev (Daryo, 17 Nov 2025):
  - "Choyxona deyilganda ... kunda-kunora yig'iladigan joy tushunilardi. Hozir esa oldindan falon summali ovqat buyurtma qilib qo'yiladigan joy tushuniladi. Bugungi kunda choyxona tushunchasi butunlay o'zgarib ketdi."
  - He calls for re-creating free ("tekin") guzars where retired men can meet and play chess.
  - This implies the free or cheap mahalla choyxona has largely been replaced by paid, pre-order venues.
  - — [Daryo.uz, 17 Nov 2025](https://daryo.uz/2025/11/17/choyxona-tushunchasi-butunlay-ozgarib-ketdi-arxitektor-shuhrat-abdullayev-otaxonlar-uchun-tekin-guzarlar-kerakligi-haqida/)

**Wikipedia (RU), general**
- Besides mahalla choyxonas there are larger choyxonas in other parts of towns and along intercity roads.
- Traditionally, tapchans stood outside by an aryk in summer, and those without a tapchan sat inside.
- — [Wikipedia RU "Чайхана"](https://ru.wikipedia.org/wiki/%D0%A7%D0%B0%D0%B9%D1%85%D0%B0%D0%BD%D0%B0) (general, undated)

**Roadside choyxonas**
- A traveller report describes a choyxona at a junction in Jaslyk (Karakalpakstan) where trucks gather.
- The same report describes "countless roadside choyxonas" with very pushy touts who run onto the road.
- — [Forum Vinsky (awd.ru), 2023 trip report](https://forum.awd.ru/viewtopic.php?t=421873) [ESKI][ZAIF: single traveller]

**Room-based choyxona (dominant listed format)** — chaykhana.uz directory, 159 venues across Tashkent, Fergana valley, Samarkand, Bukhara, Jizzakh, Navoi, Surxondaryo, Qashqadaryo, Khorezm:
- 40 venues list a "placePrice" (place/room fee).
- 77 of 159 list their food simply as "Buyurtma bo'yicha" (made to order).
- Reviews talk about "xonalari qulay ... konditsioner, televizor" (Zoda, 2019), "uyutnye kabinki" (Chust choyxonasi, 2020), "retro usulda bezatilgan xonalari" (Qorasuv, 2019) and "ichki va tashqi stollar" (Asr, 2019).
- Venues advertise "Banket zali (musiqa bilan)" and "24/7" (Hammabop, Fergana).
- "Oshpaz xizmati xonaning narxi ichida. Bozorlik mijozlardan ham qabul qilinadi" (Nodirbek, Fergana): the customer can bring their own groceries, and the cook's labour is included in the room fee.
- — [chaykhana.uz API, venue list](https://api.chaykhana.uz/v1/restaurants?page=0) / [venue pages, e.g. Nodirbek id 3157](https://api.chaykhana.uz/v1/restaurants/3157) [ESKI: data entered about 2019–2022]

**Directory coverage**
- A user wrote in Dec 2019 that only about 30% of Andijan choyxonas are listed in the app ("Андижонда чойхоналар жуда кўп. 30%га яқин ... жойлаштирилган").
- The format is very widespread in the regions.
- — [chaykhana.uz review on "Chaykhana" id 7037](https://api.chaykhana.uz/v1/restaurants/7037) [ESKI][ZAIF]

**"Is it a choyxona or a restaurant?" blurring**
- "Bu choyxonamas restoran bo'p ketibti, narxla qimmat" (Shofayz, Tashkent, Feb 2020).
- "Bu joy choyhonami yoki restaranmi??? ... farqiga borolmadim" (Baxtiyor, Andijan, 2019).
- — [chaykhana.uz reviews](https://api.chaykhana.uz/v1/restaurants/2402) [ESKI][ZAIF]

**New-format resto-choyxona: The Choyxona (Tashkent, Bog'ishamol 1-proezd 131)**

*Kabinkas, capacity and amenities*
- Opened 2021/22.
- 13 kabinkas (8–16 people) plus a 22-person kabinka with its own yard and summer kitchen.
- Kabinka No. 1 is a VIP kabinka for up to 16 people with lounge, PS5 and hookah.
- 2GIS (2026): up to 130 seats, banquet hall up to 40, VIP hall, outdoor seating, prayer room.
- 2GIS payments: cash, card, bank transfer, card-to-card transfer, QR.
- Each cabin has its own music, so no shared space except restrooms. Spot.uz (Mar 2023) says up to 190 guests at once, which conflicts with 2GIS's 130 seats.

*Clientele*
- Guests are mixed: young men's groups, "gap" groups, families with teenagers (birthdays), women's groups (8 March campaigns).

*Sources*
- [Afisha.uz 2023](https://www.afisha.uz/uz/restaurants/2023/02/02/the-choyxona/) [ESKI]
- [Spot.uz, 3 Mar 2023](https://www.spot.uz/oz/2023/03/03/the-choyxona/) [ESKI]
- [2GIS card](https://2gis.uz/tashkent/firm/70000001061770279/tab/info) (fetched Oct 2026)
- [Telegram @thechoyxona, 2 Mar 2024](https://t.me/s/thechoyxona?before=201)

**Weproject list (7 Jul 2025) of Tashkent choyxonas "to feel Uzbek colour"**
- TheChoyxona
- «Halva» (@chaihana.halva): oriental style, large terrace with microclimate system
- «Vostok» (@chayxanavostok)
- «Чайхана No1» (@001_choyxona): "traditional Tashkent choyxona"
- «Ташкентский дворик» (@tashkentskiy.dvorik): large summer terrace
- Boboy (@boboycafe_uz)
- Sultan Said (@sultansaid_restoran): green territory, summer gazebos (besedka), banquet halls

The article gives no prices. — [Weproject, 7 Jul 2025](https://weproject.media/articles/detail/chaykhana-v-tashkente-7-mest-gde-vy-smozhete-prochuvstvovat-uzbekskiy-kolorit/)

**Osh centre with choyxona-style osh (open hall, walk-in)**
- Kamolon milliy taomlar markazi / "Kamolon Oilaviy Kafe":
  - from Jul 2022: "har kuni ertalab 09:00 dan 23:00 ga qadar To'y oshi va Choyxona palov"
  - breakfast 05:00–11:00
  - night promotion 00:00–08:00, with all promo-menu dishes at 20,000 so'm "faqat ... oshxonasiga tashrif buyurib tanovvul qilsangiz" (dine-in only)
  - — [Telegram @aslkamolon](https://t.me/s/aslkamolon?before=597) [ESKI for 2022–23 posts]
  - Location is not stated in the text. The menu includes shilpildoq, a Khorezm dish, so it is possibly in Khorezm (inference).
- Kamolon Osh (Tashkent, possibly a different business), delivery menu: "Choyxona Osh (kechki)" 53,000, To'y oshi 53,000, Zig'ir osh 53,000, achchiq-chuchuk 17,500; open 09:00–06:00. — [kamolonosh.uz](https://kamolonosh.uz/en/menu/product/choikhona-osh-1-kechki-d) (undated, live 2026)
- Nomdor (Tashkent chain): "Osh Choyxona" 48,000, Osh To'y 46,000, Samarqand zig'ir osh 48,000, go'shtli somsa 14,000, shashlik qiyma 18,000, cola 1.5 L 16,000. — [nomdor.uz](https://nomdor.uz/uz/menu/product/osh-choikhona-) (undated, live 2026)

**Large rural choyxona-complex for sale (Sep 2026)**
- Guliston (Sirdaryo), 2,000 m², plot about 20 sotix, asking US$150,000.
- Rooms with 10 tables, each with chairs and tapchans.
- Kitchen, washing area, storage and a separate fish room.
- Two saunas.
- Shaded garden with tapchans for summer.
- Yard for about 200 people for events.
- — [OLX.uz listing, 27 Sep 2026](https://www.olx.uz/d/oz/obyavlenie/ishlab-turgan-chojxona-sotiladi-ID43fZD.html) [ZAIF: single ad]

**Sharq choyxona: halal, no-alcohol rule**
- "ichkiliklar qat'iyan man qilinadi, ichkilik iste'mol qilinayotgan bo'lsa choyxonadan chiqarib yuboriladi."
- — [Telegram @choyxonachiuz, 6 Oct 2021](https://t.me/s/choyxonachiuz?before=776) [ESKI]

### Inferences
Working typology for POS:

| Format | Seating | Food model | Main revenue lines | Hours |
|---|---|---|---|---|
| A. Mahalla/guzar choyxona (traditional) | Hall + tapchans, few or no rooms | Tea, non, simple dishes; osh on set days | Tea/non, cheap dishes, occasional osh | Day, early |
| B. Xonali choyxona (mainstream, Tashkent + regions) | 5–20 xona/kabinka + some tapchans/tables | Mostly pre-ordered ("buyurtma bo'yicha"); osh/kabob by kg | Xona/joy narxi + food by kg + tea/non/salads; sometimes self-provision (bozorlik) | Afternoon–late night, sometimes 24/7 |
| C. New-format resto-choyxona | Designed kabinkas, VIP, terrace, banquet hall | Full à la carte menu (Uzbek + European) + pre-order hot dishes; weekly osh set | Food & drink, high check, deposits, events, delivery | 12:00–00:00/01:00 |
| D. Osh markazi / national-food centre ("choyxona osh" product) | Large open hall | Walk-in, osh by portion and by kg, takeaway/delivery | Volume sales per portion/kg | Early morning to night (sometimes 24h) |
| E. Roadside (trassa) choyxona | Tapchans, open hall, sometimes rooms/overnight | Walk-in à la carte, fast | Food, tea; possibly parking/overnight | Long hours |

- Men-only versus family: type A is traditionally male. Types B and C increasingly host families, women's groups and teenagers (The Choyxona evidence). The halal/no-alcohol rule is a selling point in type B (Sharq). Type C (The Choyxona) has a wine list (2GIS).
- A POS must therefore support a "venue profile" switch: room-rental-heavy (B), restaurant-like (C), quick-service (D, E).

### Gaps
- No census or share of each format, and no data on how many mahalla choyxonas still operate.
- Roadside choyxonas: no Uzbek-language business source, only traveller anecdotes. No prices, and nothing on overnight or parking fees.
- No 2024–2026 source on "Choyxona №1" in Tashkent (@001_choyxona). Instagram is not fetchable. "Choyxona nomer 1" in Fergana (Rishton) and Andijan exist in the directory with no details beyond osh per kg (see section 2).

---

## 2. Revenue streams and pricing (joy puli, service %, self-cooking, osh per kg / N kishilik, tea, bread, shashlik, banquets)

### Takeaway
The core business model of the mainstream (type B) choyxona is **room fee plus food by weight**:
- a fixed **xona/joy narxi** per room per visit, varying with room size and weekday vs weekend;
- **osh and kabob sold per kg**, mostly ordered in advance;
- tea, non and salads on top.

Some venues accept the customer's own groceries ("bozorlik") with the cook's work included in the room price.

The new-format venue (type C) works like a restaurant: per-seat deposit expectation, high à la carte check, events, delivery.

Osh centres (type D) sell by portion, by kg and by "N kishilik" sets.

Concrete room fees found:
- Tashkent: 30,000–300,000 so'm per room [ESKI]
- regions: 0–80,000
- one weekday/weekend pair: 75,000 vs 150,000

I found no 2024–2026 published room fees.

### Cited Findings

**Room/place fee (xona narxi / joy narxi)** — chaykhana.uz placePrice field, so'm per room per visit [ESKI: about 2019–2022]

*Tashkent*
- Rohat (Buyuk Ipak Yo'li 173): 30,000
- Qorasuv (Mirzo Ulug'bek): 50,000
- Shukrona: 70,000–100,000
- Yagona (Hasanboy): 80,000
- Fayz (Yunusobod 19-kv): 80,000–100,000
- Osiyo: 80,000–150,000
- Choyxona Qorasuv (Temur Malik): 80,000–150,000
- Anor: 100,000
- Turon: 100,000
- Yakkasaroy: 100,000
- 7 Mo'jiza: 100,000–150,000
- Sinfdosh: 100,000–150,000
- Chust choyxonasi: 100,000–150,000
- Archazor: 100,000–200,000
- Sarbon: 100,000–250,000
- Anorzor: 120,000–300,000
- Qadrdon: 120,000
- Karvon Saroy: 120,000
- Abror: 120,000
- Zamondosh: 120,000–150,000
- Diydor: 130,000–170,000
- Nur (Chilonzor): 130,000–150,000
- Garimdori: 150,000
- Po'latxo'ja ota: 150,000
- Olchazor: 150,000
- Uzumzor: 150,000
- Shuxrat: 150,000

*Regions*
- Hammabop (Fergana): 25,000
- Sohil (Namangan): 10,000–20,000
- Bog'u Bo'ston (Andijan): 20,000–40,000
- Zamin (Qo'qon): 35,000–75,000
- Yangi Davr (Marg'ilon): 50,000–80,000
- Caravan (Marg'ilon): 60,000
- Nodirbek (Fergana): 60,000–80,000
- Choyxona nomer 1 (Rishton): 0
- Bexizor (Andijan): 0

— [chaykhana.uz API](https://api.chaykhana.uz/v1/restaurants?page=0) (per-venue `https://api.chaykhana.uz/v1/restaurants/{id}`)

**Room fee differs by room type and size**
- Zamin (Qo'qon) lists six room prices: 35,000 / 45,000 / 50,000 / 55,000 / 65,000 / 75,000 so'm. — [chaykhana.uz id 1084](https://api.chaykhana.uz/v1/restaurants/1084) [ESKI]
- Qadrdon (Tashkent, Shayxontohur), user-posted price list (18 Oct 2020): "oddiy xona 140.000, billiard xona 300.000, katta zal 35 kishiga 300.000; osh 140.000 1kg, to'y osh 150.000 1kg, qozon kabob 100.000 1kg." — [chaykhana.uz id 1059](https://api.chaykhana.uz/v1/restaurants/1059) [ESKI][ZAIF: user review]

**Weekday vs weekend**
- Sharq choyxona: "Joy narxi hafta kunlari 75000, dam olish kunlari 150000" (weekend is 2x). — [Telegram @choyxonachiuz, 6 Oct 2021](https://t.me/s/choyxonachiuz?before=776) [ESKI][ZAIF]

**Minimum order ("obyazalovka")**
- Diydor (Tashkent), review 12 Sep 2020: "жой нархини жуда оширишди и обязаловка бор!" (they raised the place fee a lot, and there is a mandatory minimum). — [chaykhana.uz id 1067](https://api.chaykhana.uz/v1/restaurants/1067) [ESKI][ZAIF]
- Complaint about tea charged separately: Sherxon (Tashkent), Nov 2020: "choygayam alohida pul oladi". This implies some customers expect tea to be included with the room. — [chaykhana.uz id 1073](https://api.chaykhana.uz/v1/restaurants/1073) [ESKI][ZAIF]

**Self-provision / self-cooking**
- Nodirbek (Fergana): "Bozorlik mijozlardan ham qabul qilinadi. Oshpaz xizmati xonaning narxi ichida." — [chaykhana.uz id 3157](https://api.chaykhana.uz/v1/restaurants/3157) [ESKI]
- The Choyxona (2023): some guests "book the kitchen" to cook themselves, plus the 22-person kabinka with its own yard and summer kitchen for self-cooking. — [Spot.uz 2023](https://www.spot.uz/oz/2023/03/03/the-choyxona/); [Afisha 2023](https://www.afisha.uz/uz/restaurants/2023/02/02/the-choyxona/) [ESKI]
- An old (2015) Uzbek blog on opening a choyxona says the guest chooses dishes and pays for groceries and for the kazan rental. — [oybekhaydaraliev.blogspot.com, 2015](http://oybekhaydaraliev.blogspot.com/2015/08/blog-post_91.html) (seen via search snippet only) [ESKI][ZAIF]

**Osh by kg (pre-order)** [ESKI] — chaykhana.uz

*Tashkent, "lazer osh" per kg*
- Garimdori: 100,000
- Yagona: 100,000
- Zamondosh: 100,000–110,000
- Shukrona: 106,000
- Qadrdon: 110,000
- Diydor: 110,000
- Nur: 110,000
- 7 Mo'jiza: 110,000
- Po'latxo'ja ota: 110,000
- Fayz: 110,000
- Karvon Saroy: 110,000
- Sinfdosh: 110,000–120,000
- Anor: 120,000
- Anorzor: 120,000
- Osiyo: 120,000
- Sarbon: 120,000
- Rohat: 125,000
- Chust choyxonasi: 140,000

*Tashkent, devzira osh per kg*
- Choyxona Qorasuv: 130,000
- Rohat: 135,000
- Chust choyxonasi: 150,000

*Other Tashkent items*
- Sarbon: "Achchiq osh 250,000".
- Fayz: "Qozon kabob 90,000, Bo'yin kabob 90,000".

*Fergana valley*
- Zamin (Qo'qon) devzira osh, three tiers: Oddiy 128,000 / Birga bir 164,000 / Sergo'sht 248,000 per kg. These are meat-ratio tiers.
- Hammabop (Fergana):
  - Lazer osh 110,000–120,000
  - "Hammabop osh" 130,000
  - "Boy oshi" 180,000
  - "Kuchli osh" 170,000
  - Qozonkabob 85,000
  - Jiz 80,000
  - Qovurma 100,000
  - soups per portion: lag'mon/mastava/chuchvara/manpar 9,000, ko'za sho'rva 16,000
- Nodirbek: 140,000 (Rishton dasta sariq guruch).
- Yangi Davr (Marg'ilon): osh 95,000/kg; "go'shtli ovqatlar 75,000/kg".
- Caravan (Marg'ilon): lazer 105,000, devzira 135,000.
- Bexizor / Choyxona nomer 1 (Rishton): devzira 135,000.
- Bog'u Bo'ston (Andijan): devzira 140,000.

*Other regions*
- Sohil (Namangan): lazer 120,000.
- Osiyo Grand (Navoi): devzira 115,000.
- Fish lakeside choyxonas sell fish by kg: Arg'in Baliq (Qibray) sazan 55,000/kg; Charxpalak (Bag'dod) tolstolob 30,000, sazan 35,000/kg.

— [chaykhana.uz API](https://api.chaykhana.uz/v1/restaurants?page=0)

**Cost benchmark for "1 kg osh" (Statistics committee, June 2025)**
- Ingredient cost of 1 kg osh, defined as 1 kg rice + 0.7 kg beef + 1 kg carrot + 0.3 oil + 300 g onion + 100 g chickpeas + spices:
  - Tashkent city: 116,267 so'm
  - Fergana: 114,390
  - Tashkent region: 112,192
  - Samarkand: 111,681
  - Karakalpakstan (lowest): 97,684
- — [Afisha.uz, 1 Jul 2025](https://www.afisha.uz/uz/gorod/2025/07/01/oshoshosh)

**Osh-centre pricing by portion, kg and "N kishilik"** — Kamolon milliy taomlar markazi, Telegram:

*Osh (2022–2023)*
- Jul 2022: choyxona palov 1 portion 24,000, 1 kg 45,000.
- Aug 2022: 1 portion 26,000, 1 kg 65,000.
- Feb 2023: 1 portion osh 25,000 (another post: 28,000); 1 kg to'y oshi 40,000; 1 kg choyxona palov 50,000.

*Other dishes*
- Halim 1 kg 90,000, with 20% off from 4 kg (72,000/kg) (Aug 2024).
- Shilpildoq by "N kishilik" (Aug 2024): 1 kishilik 65,000 / 2 kishilik 110,000 / 3 kishilik 165,000 / 6 kishilik 300,000. Feb 2023: 60,000 / 80,000 / 110,000 / 5–6 kishilik 200,000.
- Fish (Aug–Sep 2024): moyva 1 portion 50,000, 1 kg 150,000; sazan 1 portion 82,000, 1 kg 166,000.
- Somsa per piece (2022–23): kartoshkali 4,000, tovuqli 5,000, ovoshnoy 7,000, go'shtli 8,000.
- Whole somsa "tog'ora" (tray) 500,000–600,000.

— [Telegram @aslkamolon](https://t.me/s/aslkamolon) and [archive](https://t.me/s/aslkamolon?before=597) (2024 posts current; 2022–23 posts [ESKI])

Note the two "kg" meanings:
- Kamolon's 40–65k "per kg" sits next to a 24–28k portion, so it is cooked-weight kg.
- The chaykhana.uz 100–150k/kg figures sit in the same range as the stat committee's ingredient cost of osh made from 1 kg rice (97–116k in 2025).
- So "1 kg osh" in a choyxona pre-order most likely means **osh cooked from 1 kg of rice** (this is an inference; see Inferences).

**New-format pricing (The Choyxona)**

*Deposits and checks (Spot.uz, Mar 2023)*
- Deposit at booking is set on the expectation of at least 250,000 so'm per seat.
- One group of 10 in a 16-seat cabin paid a 10 million so'm bill.
- The largest check was 45 million so'm, for two cabins.
- — [Spot.uz, 3 Mar 2023](https://www.spot.uz/oz/2023/03/03/the-choyxona/) [ESKI]

*Current average check*
- 2GIS shows "Чек 400000 сум" (it does not say whether this is per person). — [2GIS](https://2gis.uz/tashkent/firm/70000001061770279/tab/info)

*Yandex menu (flagged "not updated for a while")*
- solyanka 75,000 (300 g)
- lentil soup 55,000
- "The Choyxona" salad 159,000
- olivier 79,000
- achichuk 39,000 (300 g)
- — [Yandex Maps](https://yandex.com/maps/org/restoran_the_choyxona/193687795731/)

*Weekly plov set*
- Thursday "PLOV set" 12:30–14:30: a portion of osh, lepyoshka, spring salad, achichuk, pakhlava, chak-chak and tea for 95,000 so'm (Apr 2024). Extended to choyxona osh as well as to'y oshi.
- Still advertised every Thursday 12:30–14:30 in Oct 2026 (no price in the post).
- — [Telegram @thechoyxona, 17 Apr 2024](https://t.me/s/thechoyxona?before=201); [21 Oct 2026](https://t.me/s/thechoyxona)

*Delivery and service charge*
- Festive osh delivery by phone order (Arafa, Apr 2024). — [Telegram @thechoyxona](https://t.me/s/thechoyxona?before=201)
- Service %: no 2024–2026 source states a xizmat haqi % for any choyxona. The only Uzbek data point remains 12% at Tashkent Plov Center (prior notes). The legal requirement to disclose the rate in advance (Nov 2025) also comes from prior notes.

**Investment and payback (new format)**
- The Choyxona's founder said a ready building lets you open for about $50–100k.
- Payback was expected at 2 years 8 months, ahead of plan (the theoretical norm is about 3 years).
- Baku copy estimated at about $1 mln.
- — [Spot.uz 2023](https://www.spot.uz/oz/2023/03/03/the-choyxona/) [ESKI]

**Banquets / events**
- Hammabop: "Banket zali (musiqa bilan)". — [chaykhana.uz id 3158](https://api.chaykhana.uz/v1/restaurants/3158) [ESKI]
- Zoda (Tashkent), review 2019: "Man kuyov oshimni Zodada o'tkazaman!" This is a wedding morning osh hosted at a choyxona. — [chaykhana.uz id 1075](https://api.chaykhana.uz/v1/restaurants/1075) [ESKI][ZAIF]
- The Choyxona banquet hall holds up to 40. — [2GIS](https://2gis.uz/tashkent/firm/70000001061770279/tab/info)
- Discount vouchers existed: "скидку по ваучеру на сумму 100.000 сумов" (Zoda, 2019). — [chaykhana.uz id 1075](https://api.chaykhana.uz/v1/restaurants/1075) [ESKI][ZAIF]

### Inferences

**Pricing objects a POS must support**
1. **Room charge**, a fixed fee per room per visit with a price list per room/size. Possible variants:
   - weekday/weekend (and probably holiday) tariffs
   - "0" for venues with no fee
   - a minimum-spend rule in place of or alongside the fee
   - an optional per-person mode, not verified (no source shows per-person joy puli)
2. **Weight-based items**:
   - osh, qozon kabob, jiz, qovurma, fish, halim per kg, with fractional quantities (1.5 kg, 2.5 kg)
   - quality/meat tiers per item (lazer vs devzira; oddiy / birga-bir / sergo'sht; to'y oshi vs choyxona oshi)
   - volume discount (Kamolon: −20% from 4 kg)
3. **"N kishilik" fixed sets** (1/2/3/6-person) as distinct SKUs.
4. **Portion and fractional-portion items** (osh portion, soups, somsa per piece, tray of somsa).
5. **Rental/service lines with no stock consumption**:
   - "oshpaz xizmati"
   - kazan rental
   - kitchen/yard rental for self-cooking
   - "bozorlik" accepted from customer (no food revenue; room plus cook fee only)
6. **Deposits** (zaklad/avans) recorded at booking and offset against the final bill.
7. **Time-window promos** (Thursday 12:30–14:30 osh set; night 00:00–08:00 dine-in-only promo prices) and dine-in-only versus delivery prices.
8. Separately chargeable tea and non. Some venues include tea in the room fee, so this must be configurable.

**Unit ambiguity**
- "1 kg osh" in choyxona pre-orders most likely means osh from 1 kg rice (feeds roughly 4–5 people; domain knowledge, unverified).
- In osh centres "kg" means cooked weight.
- A POS must let each venue define its unit explicitly (e.g. "kg (guruch)" vs "kg (tayyor)"), or staff and customers will be confused.

**Price levels**
- The [ESKI] 100–150k/kg osh prices are at or below the June 2025 ingredient cost (97–116k/kg).
- So current 2025–26 choyxona per-kg osh prices must be materially higher, plausibly 150–250k/kg in Tashkent. This is an inference only and must be verified in interviews.

### Gaps
- No 2024–2026 published xona/joy narxi for any named Tashkent choyxona. Instagram/Telegram price posts of specific venues were not indexable, and the chaykhana.uz data is old.
- No source for per-person joy puli, hourly room rental, or deposit size in percent at mainstream choyxonas.
- No source for a xizmat haqi % in any choyxona in 2024–2026.
- No current tea (choynak) or non price at a choyxona specifically. Prior notes give about 5,000 for tea at Ko'yliq bazaar (2025).
- No shashlik per-skewer price at a choyxona in 2024–2026. Nomdor shashlik qiyma is 18,000 (undated).

---

## 3. Group size, average check, visit duration, peak days/hours, seasonality

### Takeaway
New-format kabinkas host groups of 8–22. Visits are long (average 5.5 hours from opening to closing a bill at The Choyxona), with about 2 table turns per day. Thursday to Saturday are full, and weekday evenings around 19:00 are the busy hour. Spend is about 250k+ so'm per seat in the new format (2023). Ramadan (iftar), Navruz, 8 March and weddings (kuyov oshi, morning osh) are the seasonal peaks. Osh centres peak at breakfast and lunch.

### Cited Findings

**Group size**
- The Choyxona kabinkas take 8, 10, 12, 14, 16 or 22 people. — [Afisha 2023](https://www.afisha.uz/uz/restaurants/2023/02/02/the-choyxona/) [ESKI]
- Qadrdon "katta zal 35 kishiga". — [chaykhana.uz](https://api.chaykhana.uz/v1/restaurants/1059) [ESKI]
- "8 kishiga beshbarmoq zakaz qildik" (7 Mo'jiza, Apr 2022). — [chaykhana.uz id 1051](https://api.chaykhana.uz/v1/restaurants/1051) [ESKI][ZAIF]
- "7 kishi kirib bittadan shakarob bilan osh..." (Sultan, Qo'qon, Aug 2022). — [chaykhana.uz id 1088](https://api.chaykhana.uz/v1/restaurants/1088) [ESKI][ZAIF]

**Duration and turnover (The Choyxona, Spot.uz 2023)** [ESKI]
- Average time from opening to closing a bill is 5.5 hours.
- Tables turn 2 times a day, against an industry norm of 3–4.
- Weekdays bring 130–140 guests; Thursday, Friday and Saturday are almost always full.
- Source: [Spot.uz 2023](https://www.spot.uz/oz/2023/03/03/the-choyxona/)

**Busy hour**
- Yandex marks 19:00 as "usually busy"; open until 01:00. — [Yandex Maps](https://yandex.com/maps/org/restoran_the_choyxona/193687795731/)

**Check size**
- New format: deposit set on at least 250k so'm per seat; 10 guests paid 10 mln (1 mln per person) (2023). — [Spot.uz 2023](https://www.spot.uz/oz/2023/03/03/the-choyxona/) [ESKI]
- 2GIS "Чек 400000 сум" (2026). — [2GIS](https://2gis.uz/tashkent/firm/70000001061770279/tab/info)

**Regulars ("ulfatlar", gap groups)**
- "Deyarli 3 yildan buyon ulfatlar bilan shu yerda yig'ilishamiz ... Masjidga juda yaqin" (Muhtasham, Tashkent, 2019). — [chaykhana.uz id 1077](https://api.chaykhana.uz/v1/restaurants/1077) [ESKI][ZAIF]
- "Har doim shu yerda o'tiramiz ishxona bilan" (Karvon Saroy, 2019). — [chaykhana.uz id 4702](https://api.chaykhana.uz/v1/restaurants/4702) [ESKI][ZAIF]
- Proximity to a mosque is a stated advantage.

**Thursday osh**
- The Choyxona runs its weekly osh set on Thursdays (payshanba), 12:30–14:30 (2024–2026). — [Telegram @thechoyxona](https://t.me/s/thechoyxona)

**Ramadan**
- The Choyxona campaigns for kabinka iftars every year (2023, 2024).
- It gives a complimentary iftar set per kabinka (Zam-zam water, dates, dried fruit, nuts, nisholda).
- — [Telegram @thechoyxona, Mar 2023 / Mar 2024](https://t.me/s/thechoyxona?before=201)
- Arafa: osh delivery campaign. — [same](https://t.me/s/thechoyxona?before=201)

**Navruz and 8 March**
- Sumalak event on 20 March.
- 8 March "women's day in kabinkas" with welcome drink and flowers.
- — [Telegram @thechoyxona, Mar 2024](https://t.me/s/thechoyxona?before=201)

**Seasonal menus and summer seating**
- Autumn: a pumpkin menu (Oct 2026). — [Telegram @thechoyxona](https://t.me/s/thechoyxona)
- Summer: terraces and tapchans.
  - Halva: terrace with microclimate. — [Weproject 2025](https://weproject.media/articles/detail/chaykhana-v-tashkente-7-mest-gde-vy-smozhete-prochuvstvovat-uzbekskiy-kolorit/)
  - Guliston: garden with tapchans, yard for 200. — [OLX 2026](https://www.olx.uz/d/oz/obyavlenie/ishlab-turgan-chojxona-sotiladi-ID43fZD.html)
  - 9 Qozon (Namangan): summer pool. — [Telegram @choyxonachiuz 2022](https://t.me/s/choyxonachiuz) [ESKI]

**Osh-centre daily profile**
- Kamolon: breakfast 05:00–11:00, osh 09:00–23:00, night promo 00:00–08:00. — [Telegram @aslkamolon](https://t.me/s/aslkamolon?before=597) [ESKI]

### Inferences

**Revenue concentration**
- For type B/C venues, revenue is concentrated in about 3 evenings a week (Thursday–Saturday) and around holidays.
- The POS must handle many simultaneous open, long-lived checks (5+ hours) per room, with items added over the evening.
- It needs a reservation calendar per room with weekday/weekend tariffs.

**Peak calendar to model**
- Ramadan iftar: a 30-day evening peak.
- Navruz.
- Hayit days.
- Wedding season (spring/autumn), with morning osh and kuyov oshi groups.
- Summer: outdoor tapchan/terrace seating becomes a separate zone, possibly with a different fee.

**Average check (illustrative, unverified)**
- Mainstream room-based choyxona in Tashkent, group of 8–10:
  - room 100–300k
  - osh 2–3 kg
  - kabob
  - salads, tea and non
- Likely 1.5–4 mln so'm per group, about 150–400k per person.
- Must be verified.

### Gaps
- No 2024–2026 data on visit length, group size distribution or checks for mainstream (non-premium) choyxonas.
- No source on Ramadan or wedding-season revenue share.

---

## 4. Typical menu structure of a choyxona

### Takeaway
The menu is built around **osh (several varieties and quality tiers) and heavy meat dishes sold by kg or per group**, plus soups, salads, tea and non.

From chaykhana.uz food tags across 159 venues, the most common dishes were:

| Dish (tag) | Venues |
|---|---|
| Buyurtma bo'yicha | 77 |
| Qozonkabob | 19 |
| Osh | 15 |
| Sho'rva | 14 |
| Dimlama | 10 |
| Mastava | 9 |
| Kabob | 8 |
| Moshkichiri | 6 |
| Baliq | 5 |
| Qaynatma sho'rva | 5 |
| Achchiq osh | 5 |
| Jiz, siltama, devzira osh, cho'poncha | 4 each |
| Beshbarmoq, shashlik assorti, bo'yin go'shti | 3 each |
| Manti, manpar, shilpildoq, do'lma, to'y oshi, choyxona osh, lazer osh | 2 each |

The new format adds European dishes, desserts and seasonal menus.

### Cited Findings

**Food tag frequencies**
- See the Takeaway table. — [chaykhana.uz API](https://api.chaykhana.uz/v1/restaurants?page=0) [ESKI]

**Osh varieties and tiers**
- lazer osh, devzira osh, achchiq osh, to'y oshi, choyxona oshi, "boy oshi", "kuchli osh", zig'ir osh (Samarqand)
- meat tiers: oddiy / birga-bir / sergo'sht
- — [chaykhana.uz Zamin, Hammabop](https://api.chaykhana.uz/v1/restaurants/1084); [kamolonosh.uz](https://kamolonosh.uz/en/menu/product/choikhona-osh-1-kechki-d); [nomdor.uz](https://nomdor.uz/uz/menu/product/osh-choikhona-)

**Hammabop (Fergana) menu items**
- jiz, qozonkabob, lazer osh, tovuqli lapsha, moshxo'rda, mastava, manpar, qaynatma sho'rva, lag'mon, ko'za sho'rva, chuchvara, "Hammabop osh", qovurma, "O'zbegim", "Boy oshi", "Kuchli osh"
- — [chaykhana.uz id 3158](https://api.chaykhana.uz/v1/restaurants/3158) [ESKI]

**Osh-centre menu categories**
- Kamolon Osh: Shashliklar, SET, Plov, Qo'shimchalar, Ikkinchi taomlar, Sho'rvalar, Salatlar, Non. — [kamolonosh.uz](https://kamolonosh.uz/en/menu/product/choikhona-osh-1-kechki-d)
- Nomdor: Osh, Somsa, Shashlik (9 items), Shurva (5), Salat (3), Non (6), Napitki (4). — [nomdor.uz](https://nomdor.uz/uz/menu/product/osh-choikhona-)

**The Choyxona (new format)**
- osh (Thursday), qozon kabob, beshbarmoq, dumg'aza, sazan, manti, shavla
- asado ribs, wagyu, solyanka, salads 39–159k, desserts
- pumpkin seasonal menu, wine list, kids' menu
- — [Telegram @thechoyxona](https://t.me/s/thechoyxona); [2GIS](https://2gis.uz/tashkent/firm/70000001061770279/tab/info); [Yandex](https://yandex.com/maps/org/restoran_the_choyxona/193687795731/)
- Only kebab, kurtoba and European dishes can be ordered without advance booking (2023). — [Spot.uz 2023](https://www.spot.uz/oz/2023/03/03/the-choyxona/) [ESKI]

**Drinks**
- The Plov Center sells no alcohol (prior notes).
- Sharq choyxona bans alcohol. — [Telegram @choyxonachiuz](https://t.me/s/choyxonachiuz?before=776) [ESKI]
- Shakarob is sold per glass: a review complains about "shakarob miqdori va narxi" (Sultan, 2022). — [chaykhana.uz id 1088](https://api.chaykhana.uz/v1/restaurants/1088) [ESKI][ZAIF]

### Inferences
Suggested POS menu template for choyxona:
- Choy (choynak; ko'k/qora; possibly "choy bepul / xonaga kiradi" flag)
- Non (dona; patir/obi non)
- Salatlar (achchiq-chuchuk, bahor, assorti)
- Osh (variety x tier x unit: porsiya / 0.7 / kg / N kishilik)
- Go'shtli taomlar by kg (qozon kabob, jiz, qovurma, dimlama, tandir)
- Shashlik (dona / six)
- Sho'rva (porsiya)
- Xamir taomlar (manti, somsa dona, chuchvara, lag'mon)
- Baliq (kg / porsiya)
- Ichimliklar
- Xizmatlar (xona narxi, oshpaz xizmati, qozon ijarasi, banket zali)

### Gaps
- No 2024–2026 full price list from a mainstream mahalla or xonali choyxona (not premium, not osh centre).

---

## 5. Named examples (chains and well-known venues), rooms, capacity, rules, prices

### Takeaway
The best-documented current example is **The Choyxona** (Tashkent):
- 13 or 14 kabinkas (8–22 people), banquet hall for 40, up to 130–190 guests
- booking by phone and advance pre-order of hot dishes
- per-seat deposit logic, check about 400k
- Thursday osh set 95k (2024)

Mainstream choyxonas with room fees and osh-per-kg prices are documented only in [ESKI] directory data (see section 2). Osh centres with choyxona osh (Kamolon, Nomdor, Kamolon Osh) publish portion prices of 48–53k in Tashkent (2025–26).

### Cited Findings

**The Choyxona**
- Address and phone: Bog'ishamol 1-proezd 131; +998 99 844 88 88.
- Opening hours conflict between sources: 12:00–00:00 (site) and to 01:00 (Yandex/Afisha).
- Booking: "Kabinkani oldindan band qiling" in every post 2024–2026. Booking is also possible "online" (Mar 2024).
- Ratings: Yandex 5.0, 1,739 ratings, 529 reviews. 2GIS 4.9, 117 ratings, check 400,000.
- Founder: Roman Sayfulin (The Management). Same founder runs The WKND Bar and a Russian banya complex.
- — [Telegram](https://t.me/s/thechoyxona); [Yandex](https://yandex.com/maps/org/restoran_the_choyxona/193687795731/); [2GIS](https://2gis.uz/tashkent/firm/70000001061770279/tab/info); [thechoyxona.uz](https://thechoyxona.uz/) (via search snippet: 13 kabinkas, Kabinka No. 1 VIP for 16 with PS5 and hookah)

**Other Tashkent choyxonas named in 2025 media** (no prices published):
- Halva, Vostok, Чайхана No1 (@001_choyxona), Tashkentskiy dvorik, Boboy, Sultan Said
- — [Weproject, Jul 2025](https://weproject.media/articles/detail/chaykhana-v-tashkente-7-mest-gde-vy-smozhete-prochuvstvovat-uzbekskiy-kolorit/)

**Choyxona-style osh sellers, Tashkent (2025–26 live menus)**
- Kamolon Osh: Choyxona osh (kechki) 53,000; 09:00–06:00. — [kamolonosh.uz](https://kamolonosh.uz/en/menu/product/choikhona-osh-1-kechki-d)
- Nomdor: Osh Choyxona 48,000. — [nomdor.uz](https://nomdor.uz/uz/menu/product/osh-choikhona-)

**Regional, from [ESKI] directory data**
- Hammabop O'zbegim choyxonasi (Fergana): 24/7, banquet hall, room 25k, osh 110–180k/kg.
- Zamin (Qo'qon): 6 room prices 35–75k; devzira osh 128–248k/kg.
- Nodirbek (Fergana): room 60–80k including cook; bozorlik accepted.
- Choyxona nomer 1 (Rishton): room 0; devzira 135k/kg.
- — [chaykhana.uz API](https://api.chaykhana.uz/v1/restaurants?page=0)

**Market liquidity**
- The choyxonachi.uz channel posted "Al humo choyxonasi ishlamayapti, sotuvga qo'yilgan" (Feb 2022). — [Telegram @choyxonachiuz](https://t.me/s/choyxonachiuz) [ESKI]
- Guliston choyxona-complex asking $150k (Sep 2026). — [OLX](https://www.olx.uz/d/oz/obyavlenie/ishlab-turgan-chojxona-sotiladi-ID43fZD.html)
- Qarshi choyxona building on e-auksion: 308 m², start price 5.1 bln so'm (undated). — [Telegram @onlineauksionuz](https://t.me/s/onlineauksionuz?before=14289) (via search snippet) [ZAIF]

**Discovery platforms**
- chaykhana.uz (app plus API, 159 venues, Uzbek reviews).
- choyxonachi.uz (Telegram plus site, choyxonas plus dachas; "site in testing" 2026).
- — [chaykhana.uz](https://m.chaykhana.uz/); [t.me/s/choyxonachiuz](https://t.me/s/choyxonachiuz)

### Inferences
- I found no evidence of a true multi-branch choyxona chain inside Uzbekistan in 2024–2026. "Choyxona nomer 1" in Fergana/Andijan appears to be unrelated local venues; the Moscow "Chaihona №1" announced Tashkent franchising plans, but this was not verified.
- The Choyxona expanded abroad (Baku) rather than domestically. So "chain" features (multi-branch, central menu) matter more for osh-centre and national-food chains (Nomdor, Kamolon, Rayhon-type) than for choyxonas.

### Gaps
- Choyxona №1 Tashkent, Halva, Vostok, Tashkentskiy dvorik: no room counts, rules or prices found (Instagram-only).
- No current prices from the Samarkand, Bukhara or Khorezm choyxona scene.

---

## 6. (Product-owner add-on) Walk-in hall vs kabinka with booking vs group pre-order: how the three modes coexist

### Takeaway
All three modes exist, but their weight differs by format:

| Format | Dominant mode(s) | Notes |
|---|---|---|
| Osh centres / national-food centres (D) and roadside (E) | Walk-in open hall with à la carte portions | Osh cooked in the morning and sold by portion until it runs out; kg and N-kishilik for takeaway |
| Mainstream xonali choyxonas (B) | Room booking + pre-ordered food by kg | "Buyurtma bo'yicha" listed by 77 of 159 directory venues; walk-in tables/tapchans exist but are secondary |
| New-format resto-choyxonas (C) | Booked kabinkas with pre-ordered hot dishes, plus a limited à la carte menu for walk-ins | A 2025 reviewer could not get hot dishes when arriving unannounced in the evening |

### Cited Findings

**Walk-in à la carte with limited hot dishes (The Choyxona)**
- Yandex review, 8 Jul 2025: most hot dishes must be ordered in advance, and the group could not get hot meals when they arrived unannounced in the evening. — [Yandex Maps](https://yandex.com/maps/org/restoran_the_choyxona/193687795731/) [ZAIF: single review, but consistent with the owner statement below]
- Owner statement (2023): food is cooked to order per cabin and "not stored, reheated or sold the next day". Only kebab, kurtoba (about 30 min) and European dishes can be ordered without advance booking. — [Spot.uz 2023](https://www.spot.uz/oz/2023/03/03/the-choyxona/) [ESKI]
- The same venue also has outdoor seating and a 12-seat summer veranda, plus a Thursday 12:30–14:30 walk-in osh set. This is a fixed-price set for walk-in lunch guests alongside booked kabinkas. — [2GIS](https://2gis.uz/tashkent/firm/70000001061770279/tab/info); [Afisha 2023](https://www.afisha.uz/uz/restaurants/2023/02/02/the-choyxona/); [Telegram](https://t.me/s/thechoyxona)

**Pre-order as the norm**
- Choyxona is now "oldindan falon summali ovqat buyurtma qilib qo'yiladigan joy". — [Daryo, Nov 2025](https://daryo.uz/2025/11/17/choyxona-tushunchasi-butunlay-ozgarib-ketdi-arxitektor-shuhrat-abdullayev-otaxonlar-uchun-tekin-guzarlar-kerakligi-haqida/)
- 77 of 159 venues tag their food "Buyurtma bo'yicha". — [chaykhana.uz](https://api.chaykhana.uz/v1/restaurants?page=0) [ESKI]
- 9 Qozon (Namangan): "Osh, qotirma, dimlama, sho'rva, jiz, shavla ... buyurtma asosida tayyorlab beriladi". — [Telegram @choyxonachiuz, Jan 2022](https://t.me/s/choyxonachiuz) [ESKI]
- A user asking "Qani joy narxi, Menu??? Osh kilosi nech pul? telefon qilsa ko'tarmayapti" (Mulla Do'st, Dec 2019) shows the booking-by-phone flow: room price plus osh price per kg are the two things a customer checks before booking. — [chaykhana.uz id 7034](https://api.chaykhana.uz/v1/restaurants/7034) [ESKI][ZAIF]

**Walk-in tables/tapchans in mainstream choyxonas**
- "Ichki va tashqi stollar mavjud" (Asr, Tashkent, 2019). — [chaykhana.uz id 1066](https://api.chaykhana.uz/v1/restaurants/1066) [ESKI][ZAIF]
- "Biroz kechiktirilgan xizmat (ayniqsa, ko'chada o'tirsangiz)" (Qo'qon, 2019): a street/outdoor seating zone. — [chaykhana.uz id 1091](https://api.chaykhana.uz/v1/restaurants/1091) [ESKI][ZAIF]
- Guliston complex: "rooms with 10 tables" plus a garden with tapchans plus an event yard for 200. — [OLX 2026](https://www.olx.uz/d/oz/obyavlenie/ishlab-turgan-chojxona-sotiladi-ID43fZD.html)

**Walk-in à la carte in osh centres**
- Kamolon (osh 09:00–23:00 by portion, dine-in night promo).
- Tashkent Plov Center and Besh Qozon (walk-in, portions, sold out after about 13:00; prior notes).
- — [Telegram @aslkamolon](https://t.me/s/aslkamolon?before=597)

**Group pre-order in "N kishilik" sizes**
- Kamolon shilpildoq 1/2/3/6-kishilik (2024). — [Telegram @aslkamolon](https://t.me/s/aslkamolon)
- The Choyxona festive osh by phone order and delivery (2024). — [Telegram @thechoyxona](https://t.me/s/thechoyxona?before=201)

**Deposit at booking (new format)**
- Deposit based on at least 250k per seat. — [Spot.uz 2023](https://www.spot.uz/oz/2023/03/03/the-choyxona/) [ESKI]

### Inferences

**Share of seating** (rough, unverified, to be confirmed in interviews):
- Mainstream xonali choyxona: majority of covers in rooms (perhaps 60–90%), the rest at hall tables and tapchans (seasonal outdoors).
- Osh centres: nearly 100% open hall.
- New format: about 80%+ kabinkas, plus terrace/veranda.

**Billing difference between modes** (inference):
- Walk-in table/tapchan: usually no room fee. Pay per item (portion osh, tea, non, salad, shashlik per skewer), possibly a service %.
- Booked room: room fee (or a minimum) plus pre-ordered kg dishes plus extras added during the stay; deposit offset; often one payer.
- Pre-order for groups or takeaway (osh "N kishilik" or N kg ordered a day ahead): an order with a due time, possibly prepaid. Feeds the kitchen/kazan plan.

**POS consequence**
One venue needs three order types on a single floor plan and kitchen queue:
1. Table/tapchan order (open check, à la carte, quick)
2. Room session: reservation, then check-in, a long-running check, room charge auto-added per tariff, then close
3. Pre-order: created days ahead with menu, kg quantities, deposit and due time; converted into a room session or takeaway at arrival

The kitchen must see pre-orders by due time, separately from à la carte tickets. Stock and stop-list logic is needed for walk-in osh portions (morning kazan sell-out).

### Gaps
- No source quantifying the share of walk-in versus booked revenue, or whether walk-in tapchans carry a joy puli.
- No source on how far in advance mainstream choyxonas require osh pre-orders (same day vs previous day), or on cancellation rules.
- 2GIS and Yandex review mining for mainstream Tashkent choyxonas (not The Choyxona) was blocked by tooling. 2GIS connections failed, and Instagram is not fetchable. This is the best next step, together with 5–10 owner phone interviews.
