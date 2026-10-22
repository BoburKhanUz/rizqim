# Choyxonas, osh markazlari, lag'monxonas and other national-cuisine eateries in Uzbekistan: how they operate (input for POS design)

Scope note: about 20 searches and fetches in Uzbek, Russian and English (October 2026). Public web sources on how these places run day to day are thin. There are almost no owner interviews about cash control, and the job ads I found are not choyxona-specific. Facts with a source are marked as such. Everything under "Inferences" is reasoning or general domain knowledge, not verified, and should be checked in owner interviews. Sources about Osh (Kyrgyzstan) and Kazakhstan came up repeatedly in search. They are left out except where marked as comparison.

## 1. How a typical choyxona takes and closes an order (seating to payment); room/per-person charge; service charge (xizmat haqi)

### Takeaway
The verified pattern for a sit-down national-cuisine venue in Tashkent: a waiter tells guests what is available today (often without a printed menu), brings the bill, and guests either pay a separate cashier or settle at the table. A percentage service charge (12% at the Tashkent Plov Center) is added to the bill. Legally it is allowed only if the exact rate is disclosed in advance and service is actually provided. New-format choyxonas run on bookable kabinkas for 8–22 people. I found no published per-room fee (xona narxi) or deposit figures.

### Cited Findings
- At the Tashkent Plov Center (Furkat ko'chasi 26, per the article): there is no printed menu, and the waiter lists what is available that day. The waiter brings the check, but payment is made at the cashier, not at the table. "A 12% service charge is added to the bill." Article undated, images dated February 2026. — [Eurasia.travel, Inside Tashkent's Plov Center](https://eurasia.travel/inside-tashkents-plov-center/)
- Service charge legality (Uzbekistan, November 2025): the Committee for Competition Development and Consumer Rights Protection warned catering venues against imposing services. Buxgalter.uz's editorial reading is that this is not a ban on service charges. A charge is allowed only if (a) the service is actually provided and (b) the customer is told in advance that service is paid and at what exact rate (on the menu, at the counter or in the public offer). Prohibited: a mandatory waiter fee in self-service venues, a hidden percentage, and revealing the fee only at payment. Venues should define their format and, where possible, offer a choice between self-service and waiter service. Complaints go to hotline 1159. — [Buxgalter.uz editorial, 21 Nov 2025](https://buxgalter.uz/publish/doc/text211989_mnenie_redakcii_o_chem_predupredil_zavedeniya_obshchepita_komitet_po_konkurencii) (editorial interpretation, not an official ruling)
- Uzbek Consumer Rights Committee inspections in 2025 checked whether Tashkent restaurant and cafe menus are in the state language (Uzbek). Price information must be available in Uzbek, and other languages are optional. — [Afisha.uz, 22 Apr 2025](https://www.afisha.uz/ru/restaurants/2025/04/22/the-official-language)
- "The Choyxona" (Tashkent, new-format resto-choyxona, opened 2022/23): 13 kabinkas for groups of 8, 10, 12, 14 or 16 people, plus one 22-person kabinka with its own courtyard and summer kitchen. Guests can rent that one and cook their own lunch or dinner, with staff provided. There is a 12-seat summer veranda, and two 22-person kabinkas can be joined for banquets. Advance booking is required for kabinkas. Open 12:00–01:00, no days off. Cabin extras: PlayStation, hookah, streaming TV. Plov is served on Thursdays. — [Afisha.uz (uz), 2 Feb 2023](https://www.afisha.uz/uz/restaurants/2023/02/02/the-choyxona/)
- The same venue advertises event formats ranging from banquets and closed corporate or state events to children's birthdays and presentations. — [Afisha.uz, 11 Jun 2022](https://www.afisha.uz/ru/gorod/2022/06/11/the-choyxona) (older than 2023)
- Comparison only, outside Uzbekistan: Kyrgyzstan moved the 10–20% service fee into menu prices after the government ruled that a mandatory 15% line on the bill was unlawful (Feb 2026). Kazakhstan venues commonly add 10–15%. — [Economist.kg, 7 Feb 2026](https://economist.kg/biznes/2026/02/07/pochiemu-15-za-obsluzhivaniie-pierieiekhali-v-tsienu-bliud-pozitsiia-khorieka-klub/); [24.kg](https://24.kg/vlast/360704_kabmin_kafe_neimeli_prava_vklyuchat_15protsentov_zaobslujivanie_vschet/); [Inform.kz](https://www.inform.kz/ru/obyazan-li-klient-v-restorane-platit-10-15-za-obsluzhivanie-bce8f9de). This is a regulatory trend that Uzbekistan could follow.

### Inferences
- Likely flow at a classic choyxona (domain knowledge, unverified):
  1. Guests sit at a table, topchan/so'ri or kabinka.
  2. The waiter (ofitsiant), or the choyxonachi in small village or mahalla choyxonas, takes the order verbally or on a paper notepad.
  3. Tea (choynak) and non are brought immediately.
  4. Hot dishes are ordered from the oshpaz, and the waiter writes the bill by hand or the cashier rings it up.
  5. At the end, the waiter brings the bill with the service charge added. Guests pay cash at the table (the waiter carries it to the cashier) or by card or QR at the cashier.
- The 12% at the Plov Center suggests that 10–15% is common in Tashkent. That range is consistent with the brief's assumption, but I have only one verified Uzbek data point.
- POS implications:
  - The service charge needs to be a configurable % per venue, and possibly per hall or zone (a self-service zone gets 0%), so venues stay compliant with the Committee guidance.
  - The charge must be printed as a separate line, with its rate shown on the menu and receipt.
  - Kabinkas need their own entity: capacity, bookable, joinable for banquets, and possibly carrying a fixed rental fee.
- Possible room-charge models to ask owners about (not verified):
  - a fixed xona narxi per visit or per hour
  - a minimum spend (minimal buyurtma) per kabinka
  - a per-guest charge, sometimes called "joy puli"
  - no fee, with a higher service %
- The "self-cook kabinka with summer kitchen, staff provided" model at The Choyxona implies a billable line that is a rental and service fee, not a menu dish.

### Gaps
- No published xona narxi, minimum spend or deposit figures for any Uzbek choyxona. Searches in Russian and Uzbek ("кабинка депозит минимальный заказ", "xona band qilish narxi") returned nothing relevant.
- No source on whether a mahalla or village choyxona charges a service % at all. Likely many do not, but this is unverified.
- No owner-described step-by-step order flow. The Plov Center description comes from a travel site.

## 2. How osh markazlari sell (portion, kg, takeaway, morning-only) and how they handle "osh tugadi"

### Takeaway
Osh markazlari cook in very large kazans early in the day. The Tashkent Plov Center runs five kazans of up to 360 kg each. Only a few osh varieties are made per day, and they sell out after noon ("after 1 pm only empty kazans"). Osh is sold by portion: full, reduced 0.7 and double portions are verified, plus add-ons (quail egg, qazi), salad (achchiq-chuchuk), non and tea. Bring-your-own-container takeaway exists informally. Average portion price was about 35,700 UZS in Tashkent (Dec 2024 / Jan 2025 statistics). 2025 menu prices at well-known centres were 38,000–46,000 UZS.

### Cited Findings
- Plov Center (Tashkent):
  - Five kazans, each holding up to 360 kg, cooked simultaneously every day.
  - Osh is eaten fresh, mainly at lunch, and only a few varieties are made per day. On one visit only "Wedding" and "Special" plov were left.
  - Arrive before 12:00 for the widest choice.
  - Portions come full, reduced (0.7) or double. Extras: quail eggs, qazi (horse sausage). Sides: vegetable salad or pickles, lepyoshka (non).
  - Drinks: tea, fermented dairy, cola in 0.5 L or 1 L bottles only. No alcohol.
  - Approximate prices: osh $3.50–3.70, achichuk $1.20, non $0.60, choynak $0.30.
  - Some locals bring their own containers for takeaway; this is not an official service.

  — [Eurasia.travel](https://eurasia.travel/inside-tashkents-plov-center/)
- Besh Qozon (also called "Central Asian Plov Center", near the Tashkent TV tower): very large two-storey hall serving "thousands of plates" daily. Plov starts cooking at night, and "after one o'clock you will see only empty kazans". Tables are hard to find after noon. — [Atlas Obscura](https://WWW.Atlasobscura.com/places/central-asian-plov-center); [Kupibilet guide](https://www.kupibilet.ru/gid/uzbekistan/tashkent/besh-kozon) (traveller and guide sources; times are anecdotal)
- Note on a source conflict: Eurasia.travel says the Plov Center (Furkat 26) has no branches and warns of similarly named cafes. Some guides conflate it with Besh Qozon. Treat them as possibly different venues.
- Prices in April 2025 (Tashkent):
  - Besh Qozon: to'y oshi (wedding plov) 42,000 UZS, special plov 46,000 UZS.
  - Plov City: plov 38,000–40,000, mastava 30,000, shashlik 17,000–20,000.
  - Chorsu bazaar: plov 45,000 per portion, shurpa 40,000, samsa 10,000–15,000.
  - Ko'yliq bazaar: tea 5,000, samsa 15,000, flatbread with dumba 30,000.
  - Diyar: lag'mon 45,000–49,000, soups 42,000–48,000.
  - TANDIRIY: manti 40,000–50,000.
  - Oloy bazaar: manti 6,000–8,000 per piece. This shows that a per-piece unit exists.

  — [WeProject, 28 Apr 2025](https://www.weproject.media/articles/detail/chto-mozhno-pokushat-v-tashkente-za-50-000-sum-luchshie-mesta-i-blyuda/?amp=y)
- Official "Palov indeksi" (Statistics Agency), average price of one portion in selected osh markazlari; restaurants and choyxonas excluded:
  - December 2024: Tashkent city 35,700 (highest), Bukhara 33,700, Andijan 31,200, Khorezm 30,900, Kashkadarya 30,700, Samarkand 30,500, Jizzakh 28,800, Surkhandarya 28,600, Tashkent region 27,300, Navoi 26,800, Fergana 26,600, Karakalpakstan 25,700, Syrdarya 25,700, Namangan 22,200 (lowest).
  - The average plov price rose 15.6% in 2024, and Tashkent city rose 20.2%.

  — [Afisha.uz, 3 Jan 2025](https://www.afisha.uz/ru/restaurants/2025/01/03/plov)
- January 2025 index: Tashkent city still 35,700, Namangan 23,000. — [Daryo.uz](https://daryo.uz/rrl6Fbl7) (seen via search summary only, not fetched directly)
- July 2024: Tashkent 30,400 per portion, Namangan 20,000. — [Afisha.uz, 1 Aug 2024](https://www.afisha.uz/ru/gorod/2024/08/01/plov)
- 2023: osh prices rose about 30–32% in Bukhara, Kashkadarya and Namangan, roughly three times food inflation. — [Kun.uz, 5 Jan 2024](https://kun.uz/news/2024/01/05/palov-indeksi-ozbekistonda-osh-narxi-bir-yilda-qanchaga-oshdi)
- The stat.uz methodology covers selected public catering outlets specialising in plov, and the sample is updated each January. — [stat.uz releases](https://stat.uz/images/uploads/reliz-2023/palovindeks20231031ru.pdf)

### Inferences
- Kazan/batch model and stock:
  - Each kazan is a production batch with a known yield (kg of raw rice or meat leads to N portions).
  - Stock is "portions remaining per osh type". It decrements per portion, with 0.7 = 0.7 units and double = 2 units, and also per kg for takeaway.
  - When a batch hits zero, the item must be blocked automatically on waiter and cashier screens ("osh tugadi" / stop-list), so waiters stop promising it.
  - Today this is almost certainly done verbally, with the oshpaz or kazan server shouting or the waiter checking the kazan. Unverified.
- Units to support:
  - porsiya, yarim/0.7 porsiya, ikki porsiya (double)
  - kg (takeaway osh for home or events, commonly ordered "1 kg osh"; unverified)
  - dona (samsa, manti per piece, quail egg, non)
  - choynak (tea pot)
  - bottle sizes
  - add-on modifiers on osh: qazi, bedana tuxum, extra meat (go'sht ko'proq)
- Morning osh (ertalabki osh, eaten roughly 6–10 am at weddings and nahor oshi) and lunch osh mean the peak is early morning to early afternoon, after which the centre may close or switch to other dishes. Exact hours were not verified.

### Gaps
- No verified portion weights (grams per porsiya) or per-kg takeaway prices.
- No published opening hours or kazan start times for specific osh markazlari. "Cooks at night" and "empty after 13:00" are traveller anecdotes.
- No owner source on how they forecast kazan size or what they do with leftovers.
- 2025–2026 Palov indeksi monthly figures were not retrieved. stat.uz should be checked directly.

## 3. How groups (gap, gashtak, ziyofat) book and pay (advance, fixed menu per person, bill splitting)

### Takeaway
Groups book kabinkas in advance (verified for The Choyxona, where booking is mandatory for cabins, and joinable cabins exist for banquets). I found no web source documenting how gap or gashtak groups pay: advance amount, fixed per-person menu, rotating host or splitting.

### Cited Findings
- Kabinkas for 8–22 people require advance booking, and two 22-person cabins can be joined for banquets. — [Afisha.uz, 2023](https://www.afisha.uz/uz/restaurants/2023/02/02/the-choyxona/)
- The choyxona serves as an informal meeting place, traditionally male, with topchans and long conversations. — [Eurasia.travel, Chayhana Culture](https://eurasia.travel/?p=9833); [Orexca](https://www.orexca.com/rus/uzbekistan/traditions.htm) (general cultural sources, not operational)

### Inferences (domain knowledge, unverified; validate in interviews)
- Gap/gashtak: a recurring (weekly or monthly) gathering of a fixed circle (friends, classmates, colleagues, neighbours) in which members take turns hosting and paying, or everyone contributes a fixed amount. Choyxonas often host the same group on the same weekday. This points to:
  - a "regular group" entity (name, contact person, usual day, usual kabinka, usual menu)
  - recurring reservations
  - a host-of-the-day who pays
- Ziyofat, birthdays, mavlud, nahor oshi and similar events: usually booked by phone or Telegram, with a fixed set menu per person or per table (dasturxon + osh + shashlik + drinks) and an advance payment (avans or zaklad). The balance is paid at the end, often in cash.
- Splitting the bill per person is less common than one person paying. A POS still needs equal split and split by items.
- Large events order osh by kg or by kazan, sometimes cooked on-site by a hired oshpaz. A choyxona with a "rent kabinka and cook yourself" option (as at The Choyxona) bills a rental and staff fee instead of dishes.
- Nasiya (tab or credit for regulars and mahalla people) is a widely reported practice in Uzbek small retail and food service generally, but I found no source for choyxonas specifically. It needs a customer ledger (qarz daftari) with balance, limit and payment history.

### Gaps
- No source on typical avans size (% or fixed amount), cancellation rules or per-person set-menu prices for ziyofat in Uzbek choyxonas.
- No source on nasiya practice in choyxonas.
- Uzbek-language search for "gap gashtak choyxona xona band qilish" returned only irrelevant results (Gap, France). Telegram channels of specific choyxonas would be the place to look, but they are not indexable via this search tool.

## 4. Paper or software processes used today; owner pains (theft, lost orders, kitchen slips, cash discrepancies); equipment

### Takeaway
Little public evidence exists for traditional choyxonas. Larger chains use r-keeper or iiko, with fiscalisation through a "virtual kassa". Vendors market their products on cash-operation alerts to Telegram, which points to owner demand for theft and cash control. Cash is still significant: a cashless mandate for goods or services over 25 million UZS took effect on 1 April 2026, and a possible cash exemption for alcohol and tobacco in restaurants is being discussed. I found no Uzbek owner interviews quantifying theft.

### Cited Findings
- r-keeper in Uzbekistan offers integration for fiscalisation via a virtual cash register (virtual kassa) and with the goods-marking system. It also offers add-ons: "Pro Alert" (real-time monitoring of every cash operation via Telegram), "Pro Bonus" (loyalty via Telegram bot and QR code) and "Store House Pulse" (stock and purchasing analytics). — [Spot.uz advertorial, 18 Mar 2025](https://www.spot.uz/ru/2025/03/18/r-keeper/) (paid content, so these are vendor claims)
- From 1 April 2026 in Uzbekistan, cashless payment is required for alcohol and tobacco, fuel, government services, utilities, and any goods or services above 25 million UZS. An exception letting restaurants, cafes and hotels sell alcohol and tobacco for cash is reportedly planned but not confirmed. — [Anhor.uz, 2026](https://anhor.uz/economy/cashless-payments-debate-uzbekistan-2026/)
- Plov Center model: the waiter brings the check, and payment is at a central cashier, which splits order-taking from cash handling. — [Eurasia.travel](https://eurasia.travel/inside-tashkents-plov-center/)
- Generic waiter duties in Tashkent hospitality job ads: greet, take orders, communicate special requests to the kitchen, process orders and payments through a POS (MICROS / Oracle Symphony at hotel venues). Shifts are 8 hours, including evening and night. These are hotel restaurants, not choyxonas. — [hh.uz vacancy](https://hh.uz/vacancy/136400519)
- OLX.uz Tashkent waiter or runner ads quote salaries of about 3.5–4.5 million UZS per month for full-time work. One data point; venue type unclear. — [OLX.uz waiter listings](https://www.olx.uz/rabota/bary-restorany-razvlecheniya/oficiant/q-ташкент-сити-работа/)
- Diaspora comparison (Moscow, not Uzbekistan): a review of the chaikhana "Ya Tashkent" says the waitress insisted on cash or bank transfer instead of card. This is the kind of behaviour that hides revenue or diverts cash. — [Zoon.ru](https://zoon.ru/msk/restaurants/chajhana_ya_tashkent/) (outside geography; illustrative only)

### Inferences (unverified; validate in interviews)
- Typical equipment in small choyxonas and osh markazlari:
  - paper notepads for waiters
  - a hand-written or calculator bill
  - a fiscal cash register or virtual kassa at the cashier (required for fiscal receipts)
  - one card or QR terminal (Uzcard/Humo, Click/Payme QR)
  - rarely a kitchen printer
- Mid-size and chain venues use r-keeper, iiko, Poster or local systems on a cashier PC, with a kitchen or bar printer and sometimes waiter tablets or phones.
- Likely owner pains in these formats (typical of cash-heavy catering; not documented for Uzbekistan in what I found):
  - **Unrecorded orders:** the waiter takes cash at the table for items never rung up.
  - **Missing items on the bill:** dishes cut from the bill or reprinted bills, with pocketing of the difference. This is common with tea, non and salads, which are served without a kitchen ticket.
  - **Kitchen and kazan leakage:** osh portions served "off the books", or extra portions sold from the kazan. A kazan yield vs portions sold reconciliation detects this.
  - **Service-charge manipulation:** the % is applied or removed manually.
  - **Nasiya abuse:** fake "debts".
  - **End-of-day gaps:** cash discrepancies at shift close between the cashier's drawer and the waiter's notes.
- POS controls implied:
  - every item, including tea and non, must be entered before it is served
  - void and discount only with a manager PIN, with a Telegram alert to the owner
  - kazan batch yield vs portions sold reconciliation
  - per-waiter shift report
  - a cash drawer count at shift close
  - service charge computed automatically, not typed

### Gaps
- No Uzbek owner interview or survey on theft, lost orders or cash discrepancies was found (spot.uz, kun.uz and gazeta.uz searches returned nothing on this).
- No data on POS penetration among choyxonas or osh markazlari, or on how many use paper only.
- No choyxona-specific job ads describing ofitsiant, kassir or choyxonachi duties and shift patterns. hh.uz and OLX should be searched manually with "choyxona" or "чайхана" filters.

## 5. Staff structure, shifts, peak hours; typical prices and average check (UZS)

### Takeaway
Verified price anchors as of late 2024 to 2025:
- osh, average in osh markazlari: about 35,700 UZS in Tashkent (lowest region about 22,000–23,000)
- osh at well-known Tashkent centres: 38,000–46,000 UZS
- lag'mon and soups: 42,000–49,000
- samsa: 10,000–15,000
- tea: about 5,000
- shashlik: 17,000–20,000 per skewer

Peak for osh is morning to lunch, with sell-out after about 13:00. Staff structure and shifts were not found in any source.

### Cited Findings
- Price anchors: see section 2 ([WeProject 2025](https://www.weproject.media/articles/detail/chto-mozhno-pokushat-v-tashkente-za-50-000-sum-luchshie-mesta-i-blyuda/?amp=y); [Afisha.uz Palov indeksi](https://www.afisha.uz/ru/restaurants/2025/01/03/plov)).
- Plov Center prices (approx., 2026 images): osh $3.50–3.70, choynak about $0.30, non about $0.60, achichuk about $1.20, plus 12% service. — [Eurasia.travel](https://eurasia.travel/inside-tashkents-plov-center/)
- Osh is best before 12:00. Centres are packed after noon, and kazans are empty after about 13:00 at Besh Qozon. — [Eurasia.travel](https://eurasia.travel/inside-tashkents-plov-center/); [Atlas Obscura](https://WWW.Atlasobscura.com/places/central-asian-plov-center)
- The new-format choyxona runs 12:00–01:00, an evening-gathering profile. — [Afisha.uz 2023](https://www.afisha.uz/uz/restaurants/2023/02/02/the-choyxona/)
- Waiter pay is about 3.5–4.5 million UZS per month (one Tashkent ad). — [OLX.uz](https://www.olx.uz/rabota/bary-restorany-razvlecheniya/oficiant/q-ташкент-сити-работа/)

### Inferences
- Illustrative average checks, built from the verified unit prices; not observed data:
  - Solo osh-markazi lunch: osh + non + tea + salad ≈ 35,000–46,000 + 5,000–10,000 + 5,000 + 10,000–15,000, so roughly 55,000–75,000 UZS before a 10–15% service charge.
  - Choyxona kabinka gathering of 10 people: dasturxon, osh by kg or portions, shashlik, drinks. Likely 1–3 million+ UZS per table, but this is not verified and must not be quoted as fact.
- Peak profile:
  - osh markazi: morning (about 7–11) and lunch (11–14), then sold out
  - lag'monxona or national cafe: lunch peak
  - choyxona with kabinkas: evening (18:00–24:00) gatherings, Thursday/Friday osh days, weekend ziyofats
- Likely roles:
  - choyxona: ofitsiant, kassir (often owner or relative), oshpaz (osh master, separate from the shashlikchi and other cooks), choyxonachi (tea, non, dasturxon), idish yuvuvchi, farrosh, and an administrator who takes bookings
  - osh markazi: kazan oshpaz plus an osh server at the kazan who portions onto plates
- Both role lists are inferred and should be confirmed in interviews.

### Gaps
- No source for staff counts, shift schedules, or waiter pay model (fixed vs % of service charge) in choyxonas.
- No verified average check figures for choyxonas or banquets.
- Regional price data beyond the Palov indeksi (e.g., Samarkand or Fergana choyxona menus) was not retrieved.
