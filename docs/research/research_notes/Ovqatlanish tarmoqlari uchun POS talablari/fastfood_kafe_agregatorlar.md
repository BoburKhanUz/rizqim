# QSR, coffee shops, dark kitchens and delivery aggregators in Uzbekistan: what a POS must support

Research date: 19.10.2026. Language of sources: RU/UZ/EN. Many primary figures are company self-reports or sponsored articles ("на правах рекламы"); this is flagged inline. Fiscal receipt law is deliberately out of scope (see `fiskal_background.md`).

Overall picture in one paragraph: the Uzbek delivery market is a two-and-a-half-player market. Yandex (Yandex Eats / Yandex Go "Taom", plus the Express24 assets it bought in Oct 2024) and Uzum Tezkor are both legally designated "dominant" platforms. Wolt left on 5 March 2026. Alongside them, restaurants' own Telegram bots and apps are a major channel, and some chains (Evos, Oqtepa Lavash, Max Way) run their own couriers. The big QSR chains (KFC, Evos, Oqtepa Lavash, Max Way, Les Ailes, Feed Up) are on both aggregators and on their own bots at the same time. POS integration with aggregators runs mainly through iiko and r_keeper connectors or through middleware (Delever, Kwaaka-type hubs). Public data on commissions and payout terms for Uzbekistan specifically is thin.

---

## 1. Delivery aggregator ecosystem (players, market share, commissions, integration, payouts)

### Takeaway
Since late 2024 the market has been a near-duopoly. Yandex Eats (with the Express24 assets) had **87.5%** of the food-aggregator market on end-2023 data, and Uzum Tezkor was designated dominant in April 2025. Wolt exited on 5 March 2026. There are no official published commission rates for Uzbekistan. Secondary sources give **20–35%** for aggregator delivery, and Delever puts the typical figure at 30–35%. POS integration exists via iiko and r_keeper connectors (Uzum Tezkor–iiko since Aug 2023; Yandex via iiko.biz, r_keeper Delivery, or open API).

### Cited Findings

**Market structure and regulation**
- **Express24 deal (25 Oct 2024):** Yandex Uzbekistan bought part of Express24's assets (Newmax Technologies, founder Akmal Paiziev). The assets were software (customer iOS/Android apps, courier app, ops software), databases including user data, the domains express24.uz, exp24.uz, ekspress24.uz and exp.uz, and the trademarks "Express24: Служба доставки", "Express24: Монстры доставки" and "Express dookon". It was an asset deal, not a share deal, so no prior antitrust approval was needed. The parties planned to move to a "single technology platform". — [Spot.uz, 28.10.2024](https://www.spot.uz/ru/2024/10/28/economic-concentration/)
- After the deal Yandex Eats controls **87.5%** of the ready-food delivery aggregator market, on Competition Committee analysis of end-2023 data. — [Gazeta.uz, 28.10.2024](https://www.gazeta.uz/ru/2024/10/28/yandex/); [Spot.uz, 28.10.2024](https://www.spot.uz/ru/2024/10/28/economic-concentration/)
- The Express24 app was to keep operating during the transition period. — [Kun.uz, 25.10.2024](https://m.kun.uz/en/news/2024/10/25/yandex-uzbekistan-expands-with-acquisition-of-express24-assets-plans-to-build-unified-food-delivery-platform)
- **Express24's status in 2025–2026 is not confirmed** (still separate vs fully merged); see Gaps.
- **Dominance rulings, Oct 2024:** The Competition Committee designated Yandex Eats LLC JV and Express24 APP LLC as dominant in food and consumer-goods delivery aggregation. The order is dated 22 Oct 2024. It brings a mandatory antitrust compliance system and ongoing monitoring. — [Kun.uz, 26.10.2024](https://kun.uz/en/news/2024/10/26/uzbekistans-food-delivery-market-deemed-monopolized-by-yandex-eats-and-express24)
- **Dominance thresholds:** a platform qualifies with revenue above 100,000 BRV (37.5 bn so'm) or at least 50,000 monthly active end users or at least 3,000 monthly active business users, plus network effects. A share above 40% qualifies on its own. Designated platforms must announce price or term changes **at least 15 days in advance** in the media and on their website. The penalty for violations is 5% of turnover. — [Gazeta.uz, 02.04.2025](https://www.gazeta.uz/ru/2025/04/02/uzum-tezkor/); [Spot.uz, 28.10.2024](https://www.spot.uz/ru/2024/10/28/economic-concentration/)
- **Uzum Tezkor, April 2025:** designated dominant in ordering and delivery of ready food and goods, on its own notification. At that time it was in 19 cities, had more than 2,400 partners (cafés, restaurants, shops) and about 7,000 courier partners. The company claims the market could grow **5–8x in 5 years**. — [Gazeta.uz, 02.04.2025](https://www.gazeta.uz/ru/2025/04/02/uzum-tezkor/)
- **Uzum Tezkor, end-2025 / May 2026:** in 26 cities. Turnover nearly doubled year over year, retail more than 3x, and regional turnover 2.5x in 2025; regions are now one third of volume. It has more than 7,000 couriers and an average delivery time of 30–40 minutes in large cities. Partners estimate that delivery is **30–50% of restaurant orders**. In its survey (n=450), 40% order delivery several times a week and 30% weekly. These figures are sponsored and self-reported. — [Gazeta.uz (на правах рекламы), 21.05.2026](https://www.gazeta.uz/ru/2026/05/21/uzum-tezkor/); [Kapital.uz, Uzum 2025 results](https://kapital.uz/uzum-podvel-itogi-2025-goda/)
- **Yandex Eats:** launched in Tashkent on 7 March 2023 with about 200–250 venues. — [Interfax](https://interfax.com/newsroom/top-stories/88501/); [UzDaily](https://www.uzdaily.uz/en/post/79666). A Yandex Uzbekistan manager's LinkedIn post (undated, around 2025) claims about **3,000 restaurants (+1,000 YoY)**, 14 cities, and 160→320 retail partners. This is unverified and company-affiliated. — [LinkedIn (S. Aksenov)](https://www.linkedin.com/in/semyonaksenov/)
- **Yandex brands in Uzbekistan:** Yandex Eats / "Yandex Taom" (restaurants, about 30-minute average delivery), Yandex Lavka (dark-store grocery, 2024), and Yandex Ultima Go (premium restaurants, 2024). Others in the market: Korzinka Go, Noor, and Mars Express (Samarkand only). The article notes there is no 2025 market-share data. — [Zira.uz, 17.11.2025](https://zira.uz/ru/2025/11/17/kak-segodnya-rabotaet-dostavka-v-uzbek/)
- **Other players named in 2024:** Bringo, Girgitton, Broniboy (Russian), Wolt, and businesses' own Telegram bots and apps. — [Spot.uz, 28.10.2024](https://www.spot.uz/ru/2024/10/28/economic-concentration/)
- **Wolt:** launched 4 Oct 2024 with about 500 venues in Tashkent. — [Kun.uz, 07.10.2024](https://wap.kun.uz/en/news/2024/10/07/finnish-tech-giant-wolt-launches-delivery-service-in-uzbekistan). It **exited on 5 March 2026** (announced 25 Feb 2026) as part of a DoorDash portfolio review. — [Kun.uz, 25.02.2026](https://kun.uz/en/01543038)

**Commissions** (no official Uzbek tariff found)
- Busoft, an r_keeper integrator, says restaurants pay aggregator commissions of **20–30%**. Packaging costs 3,000–5,000 so'm per order. — [Zira.uz, 17.11.2025](https://zira.uz/ru/2025/11/17/kak-segodnya-rabotaet-dostavka-v-uzbek/)
- Delever puts typical aggregator commissions at **30–35%** of order value. It says restaurants that move volume to their own channels average 17–18% effective cost, and around 13% in some cases (MaxWay is given as an example). This is vendor-interested. — [The Tech, 14.10.2024, upd. 04.03.2026](https://the-tech.kz/delever-uz-startap-kotoryj-zanimaetsya-dostavkoj-edy-v-uzbekistane-i-kazahstane/)
- A third-party integration guide gives Yandex Eats at "15–35% depending on plan and region". It is undated and not Uzbekistan-specific. — [cenaly.com](https://cenaly.com/docs/integration-yandex)
- **Russian reference only, not Uzbekistan:** Yandex Eats' RU base rate from 1 Dec 2025 is 29.17% with Yandex couriers and 16.67% for own delivery or pickup (excluding VAT). — [Monster Context](https://m-context.ru/blog-monster-context/yandeks-eda-dlya-restorana/); [Yandex partner page RU](https://yandex.ru/project/eda/partners/registration). The two-tier "marketplace courier vs own delivery/pickup" structure is likely similar in Uzbekistan, but this is unverified.
- Express24 earned its money from commissions on orders (percentage not given). — [Kapital.uz, 31.01.2023](https://kapital.uz/dark-kitchen-v-uzbekistane/)

**POS integration and how orders appear**
- **Uzum Tezkor × iiko (7 Aug 2023, sponsored):** orders go straight into iiko and the kitchen with no manual acceptance. Stop lists are pulled automatically and the in-app menu stays current; the article claims this cuts 5–7 minutes of delivery time. About 150 Tashkent restaurants on iiko were then "every third" Tezkor partner (400+ partners in Tashkent, including KFC, Bon! and Oqtepa Lavash). About 1,000 restaurants in Uzbekistan use iiko. — [Repost.uz, 07.08.2023](https://repost.uz/uzum-and-iiko)
- **Yandex Eats integration (official Yandex page for Kyrgyzstan, "Yandex Go Eda"; the Uzbek terms are assumed similar but unverified):** supported routes are iiko (via iiko.biz), r_keeper Delivery, an open API for in-house or other POS, and CRMs already connected to the API. Over the API, orders go into the POS, menu and stop-list sync automatically, and order statuses go back to Yandex. Eligibility: rating at least 4.6, plus either a chain of at least 8 outlets or at least one outlet averaging 7 or more orders a day. Applications go through the vendor cabinet (Поддержка → Интеграция). Without integration, staff re-type orders from the Yandex app into the till. — [Yandex Eda KG integration FAQ](https://yandex.ru/project/eda/kg/rest_faq_integration)
- Without integration, orders are handled in the "Orders" section of the Yandex Vendor cabinet, where the menu and stop-list are also managed. r_keeper Delivery needs separate licences. — [agregatory.pro (RU)](https://agregatory.pro/blog/yandex-eda-integratsiya-iiko)
- A Kyrgyz iiko case notes that aggregator sales need **their own menu and price list** because of the aggregator mark-up, and that without integration a staff member re-enters orders by hand. — [iweb.kg](https://iweb.kg/en/cases/iiko-eden-taste)
- Middleware such as Kwaaka (Kazakhstan) routes Yandex Eats orders into iiko, r_keeper, Poster and Paloma. — [kwaaka.com](https://kwaaka.com/). Whether Kwaaka is active in Uzbekistan was not verified.
- Delever has an integrator API (marked "obsolete") for branch and menu data and for order push. — [Delever GitBook](https://delever.gitbook.io/delever/for-developers/soon)

**Who delivers**
- Aggregators use their own courier partners (Tezkor: about 7,000). — [Gazeta.uz, 02.04.2025](https://www.gazeta.uz/ru/2025/04/02/uzum-tezkor/)
- Big chains also run their own fleets: Evos says it has "its own fastest delivery service" via app and Telegram bot, and Oqtepa Lavash advertises 30-minute delivery. — [evos.uz](https://evos.uz/ru/about); [Afisha.uz, 29.10.2024](https://www.afisha.uz/ru/gorod/2024/10/29/oqtepa-lavash)

### Inferences
- A POS for Uzbekistan must treat **Yandex (Eats/Taom, ex-Express24) and Uzum Tezkor as the two mandatory aggregator connectors**. Wolt is no longer needed (exited March 2026), and Express24 should be treated as merged into Yandex unless shown otherwise.
- **Order intake from aggregators**: the POS must:
  - receive orders automatically by webhook or API into the kitchen queue;
  - push back status changes (accepted / cooking / ready / handed to courier / cancelled);
  - sync the menu and the stop-list per branch;
  - support a **separate price list per channel**, because aggregator prices are often marked up;
  - map aggregator item IDs to POS items and modifiers;
  - record the aggregator's order number, courier ETA, and payment type (prepaid online vs cash to courier);
  - handle cancellation and refund reasons.
- Because aggregator integration eligibility is gated (e.g., Yandex requires 8 or more outlets or 7 or more orders a day per outlet), **small cafés will remain on aggregator tablets or vendor web cabinets**. A POS can win them with a "manual aggregator order" entry flow: a quick-entry screen with channel = Yandex/Uzum and the external order number.
- With commissions of about 20–35%, **per-channel net-margin reporting** (gross sales, commission, packaging, promo co-funding) is a core need, not a nice-to-have.
- Because dominant platforms must give 15 days' notice of term changes, commission rates in the POS should be **configurable per channel and date-effective**.

### Gaps
- No official Uzbek commission tariff was found for Yandex Eats/Taom or Uzum Tezkor (eda.yandex.uz and Tezkor partner pages were not retrievable or not indexed). The figures above are secondary.
- **Payout schedules** (weekly, bi-weekly, T+N) for either aggregator in Uzbekistan were not found.
- Whether Uzum Tezkor offers a public open API (beyond iiko) or r_keeper and Poster connectors was not found.
- Express24's current (2025–2026) operating status and whether partners were migrated to the Yandex vendor platform were not found.
- No independent 2025–2026 market-share split between Yandex and Uzum Tezkor was found; Zira.uz explicitly says none exists.

---

## 2. QSR order flow and multi-branch chains (Evos, Oqtepa Lavash, Max Way, KFC, Les Ailes, Bon!, Safia)

### Takeaway
The largest chains run 80–100 branches with 24/7 outlets, their own delivery fleets, Telegram bots and apps, and listings on both aggregators. KFC overtook Evos as the revenue leader in 2025 (more than 1 trillion so'm). Public sources on which POS each chain uses are scarce. There is a vendor claim that Oqtepa Lavash uses r_keeper and that Delever's software was originally built for Oqtepa Lavash. No sources were found on kiosks or drive-through in Uzbekistan.

### Cited Findings
- **KFC Uzbekistan:** 2025 revenue grew **36%** to over **1 trillion so'm**, making it #1 in the country's catering market. **Evos** revenue fell 3.1% (per iHamkor.uz) after about 5 years as QSR leader. — [Anhor.uz, 8 Mar (likely 2026)](https://anhor.uz/ru/news/na-rynke)
- KFC, Evos and Oqtepa Lavash are the largest taxpayers in catering. In Jan–Jul 2025 the top 20 taxpayers paid 19.1% of the sector's 963.3 bn so'm in taxes. — [Frank.uz](https://frank.uz/news/kfc-evos-i-oqtepa-glavnye-nalogoplatelshhiki-obshhepita/)
- **Evos:** founded 2006, with **84+ branches** (20th anniversary, 2026). Its own site shows 62, 75 or 82 in different versions. It runs its own delivery via app and Telegram bot (@evosdeliverybot) and markets combos ("Звёздные комбо"). The average check in 2GIS is about 35,000 so'm. Its hh.ru page says 70+ branches with new ones almost monthly. — [UzNews](https://www.uznews.uz/ru/news/111523); [Repost.uz](https://repost.uz/novie-lineyki-ot-evosa); [evos.uz/about](https://evos.uz/ru/about); [2GIS](https://2gis.uz/tashkent/firm/70000001037378447); [hh.ru](https://hh.ru/employer/3432635)
- An academic case (WIUT) lists Evos's strategic recommendations as operational efficiency, **automation**, brand refresh and **loyalty programmes**. — [WIUT](https://science.wiut.uz/en/graduate-activities/graduate-cafe-discussion-series/180-graduate-cafe-featuring-shakhnoza-pardaeva)
- **Oqtepa Lavash:** **94 branches**, **21 open 24/7**, mostly in Tashkent; 30-minute delivery. — [Afisha.uz, 29.10.2024](https://www.afisha.uz/ru/gorod/2024/10/29/oqtepa-lavash)
- **Oqtepa Lavash POS:**
  - A search snippet from r_keeper's site lists Oqtepa Lavash as a case ("скорость открытия новых точек +100%, время обслуживания –50%"). — [rkeeper.ru](https://rkeeper.ru/). The specific case page was not located; this is **unverified vendor marketing**.
  - Separately, Delever says its original software was built for Oqtepa Lavash and integrated cash registers and payments. — [The Tech](https://the-tech.kz/delever-uz-startap-kotoryj-zanimaetsya-dostavkoj-edy-v-uzbekistane-i-kazahstane/)
  - The two claims are compatible: POS plus a separate delivery/ordering layer.
- **Max Way, Evos, Oqtepa Lavash, KFC, Feed Up and Les Ailes** were all orderable through Uzum Tezkor's Telegram bot (Dec 2023). — [Kursiv, 19.12.2023](https://uz.kursiv.media/2023-12-19/uzum-tezkor-zapustil-telegram-bota-dlya-zakaza-edy/)
- **KFC, Bon! and Oqtepa Lavash** were Uzum Tezkor partners in 2023. — [Repost.uz, 07.08.2023](https://repost.uz/uzum-and-iiko)
- Iiko's own claims for fast food: auto-forming combo sets and auto-adding "to-go" consumables (packaging). This is a generic vendor feature, not tied to a named Uzbek chain. — [multi-bit.com](https://multi-bit.com/avt_ff)
- New entrants: Burger King planned 3 Tashkent restaurants for Nov–Dec 2025. — [Spot.uz, 24.09.2025](https://www.spot.uz/ru/2025/09/24/burger-king/). Mom's Touch (Korea) is entering the QSR market. — [WeProject](https://weproject.media/articles/detail/mom-s-touch-vykhodit-na-rynok-fastfuda-uzbekistana/)
- Iiko's partner in Uzbekistan is Zetta Group LLC (2017 data). — [iiko.ru, 22.11.2017](https://iiko.ru/news/novinki-iiko-dlya-restoratorov-uzbekistana/). Local iiko integrators include enter-co.uz and Soft24. — [enter-co.uz](https://enter-co.uz/); [soft24.uz](https://soft24.uz/en/restoran/)

### Inferences (domain knowledge, not Uzbek-sourced; label as such in the report)
- **Combos and upsell:** chains of the Evos and KFC type sell combos (a main item plus fries and a drink with a choice of drink and size). The POS needs:
  - a combo builder with choice groups and price deltas;
  - forced and optional modifiers (sauce, size, "без лука", extra cheese);
  - packaging auto-add for takeaway and delivery.
- **High-volume counter service with 24/7 outlets** (21 of 94 Oqtepa branches) requires:
  - **shift handling across midnight** and business-day cut-off separate from the calendar day;
  - order numbers that reset per shift or day;
  - a customer pickup display;
  - a KDS split by station (grill/lavash line, fryer, drinks), with bump/recall and preparation timers.
- **Multi-branch (80–100 outlets)** requires:
  - a central menu with per-branch price and availability overrides;
  - per-branch stop-lists that propagate to aggregators and the own bot;
  - central reporting and franchise/branch permissions;
  - fast new-branch cloning (r_keeper's claimed "+100% speed of opening new outlets" points to this need).
- Delivery runs on three courier models at once: own fleet, aggregator couriers, and pickup. The POS must support dispatch to own couriers, plus status and ETA for aggregator couriers.

### Gaps
- No confirmed public source says which POS Evos, Max Way, KFC Uzbekistan, Les Ailes, Bon! or Safia use. Job postings did not surface. Primary confirmation would need hh.uz vacancy texts or vendor case pages.
- No Uzbekistan-specific data was found on **self-service kiosks**, **drive-through**, speed-of-service targets, or peak-hour throughput. KFC Uzbekistan likely follows Yum! global standards, but this is unverified.
- No data was found on food-court operations in Tashkent malls (Magic City, Next, Samarqand Darvoza): shared seating, pager/buzzer systems, or mall-level POS.

---

## 3. Restaurants' own Telegram bots and apps

### Takeaway
Telegram is the default direct-ordering channel in Uzbekistan. Most restaurants with their own delivery take orders through Telegram bots or Mini Apps (e.g., @evosdeliverybot, @maxwayuz_bot), and even the aggregator Uzum Tezkor launched a Telegram ordering bot. Bots are built by white-label platforms (Delever, Zoomda and others) or by agencies. They are tied to the kitchen either by POS integration or by manual re-entry, and paid via Click/Payme.

### Cited Findings
- "In Uzbekistan there is a habit of doing everything via Telegram. Most restaurants that offer their own delivery let customers order it through Telegram chatbots." — [UzNews, 13.07.2022](https://uznews.uz/ru/posts/57080). Reasons given (2022): unstable internet, and bots being cheaper and faster to build than apps. Downsides: weaker analytics and support. *(Dated 2022.)*
- Uzum Tezkor research (Dec 2023): over a third of Tashkent residents had ordered food via Telegram directly from restaurants or cafés. Uzum then launched its own Telegram ordering bot in Tashkent and Andijan, with ordering, payment and status tracking. — [Kursiv, 19.12.2023](https://uz.kursiv.media/2023-12-19/uzum-tezkor-zapustil-telegram-bota-dlya-zakaza-edy/); [Kapital.uz](https://kapital.uz/telegram-bot-v-uzum-tezkor/)
- Chain bots: @evosdeliverybot (Evos) and @maxwayuz_bot (Max Way) appear in a list of 18 Tashkent restaurant bots. — [myday.uz](https://myday.uz/eda/zakazivaem-edu-cherez-boti-bistro-i-udobno); [telegram.im](https://telegram.im/info/evosdeliverybot)
- **Zoomda (2026, vendor claims):**
  - Uzbekistan has 24+ million active Telegram users.
  - A custom Mini App from a developer costs $3,000–5,000; Zoomda launches in 24 hours, priced per order.
  - Click and Payme payments work inside the Mini App.
  - A "repeat last order" button gives +30% repeat sales.
  - Tashkent fast-food case: **22% of all orders** via messenger after a Mini App plus a channel with 4,800 subscribers; peak (14–16h) order intake is 60% faster.
  - Samarkand coffee shop case: one post to 3,200 subscribers produced 280–350 orders in 4 hours.
  - — [Zoomda](https://zoomda.uz/en/knowledge-base/telegram-bot-prodazhi)
- **Delever (Uzbek startup):**
  - White-label own website, mobile app, loyalty and analytics.
  - Automates intake → packing → handover to own or third-party courier.
  - 400+ restaurants (16 in Kazakhstan), about **10,000 orders a day**, 2M+ customers served.
  - Clients include Mahmood Kebab, China Shashlik and MaxWay; the original software was built for Oqtepa Lavash.
  - A Telegram-bot creation step is planned for onboarding.
  - Valued at $4M (Aloqa Ventures).
  - — [The Tech, upd. 04.03.2026](https://the-tech.kz/delever-uz-startap-kotoryj-zanimaetsya-dostavkoj-edy-v-uzbekistane-i-kazahstane/)
- Local bot agencies include Open Engine (Tashkent). — [oe.uz](https://oe.uz/razrabotka-botov-facebook-telegram/). MoySklad Uzbekistan publishes a guide to building a Telegram shop (Mini App plus bot). — [moysklad.uz](https://www.moysklad.uz/poleznoe/shkola-torgovli/kak-sozdat-magazin-v-telegram/)

### Inferences
- For a POS in Uzbekistan, a **native Telegram bot / Mini App ordering channel** is close to table stakes for delivery-oriented QSR, and it is the main lever for reducing a 20–35% aggregator commission. The bot must:
  - share the POS menu, modifiers and per-branch stop-list;
  - pick the branch by geolocation or delivery zone;
  - take Click/Payme/Uzum online payment or cash on delivery;
  - push orders straight to the KDS;
  - send status messages back to the customer in Telegram.
- Bot customers are identified by Telegram ID and phone number, which makes the bot the natural CRM and loyalty front-end ("repeat last order", cashback balance).

### Gaps
- No independent measurement was found of the Telegram channel's share of orders for named chains.
- The pricing of Uzbek bot-builder SaaS (Delever, Zoomda and others) was not found.

---

## 4. Dark kitchens / cloud kitchens and virtual brands; multi-channel intake ("tablet hell")

### Takeaway
The only documented dark-kitchen programme in Uzbekistan is Express24's (Newmax). It started in 2019 with a single kitchen of four in-house brands, and in 2023 announced 24 rented-kitchen sites for partner brands. No evidence after the Yandex takeover was found. The "tablet hell" problem is implied by restaurants being on 2–3 aggregators plus their own bot, and is addressed by POS connectors or hubs (iiko connectors, Delever, Kwaaka-type middleware).

### Cited Findings
- Express24's first dark kitchen opened in **2019** at Alay Bazaar (Tashkent) with **4 in-house brands** (burgers, pizza, bistro, fast lunches). By 2023 only the fast-lunch concept remained, because low-volume formats in one kitchen could not cut costs. — [Kapital.uz, 31.01.2023](https://kapital.uz/dark-kitchen-v-uzbekistane/)
- **2023 plan:** 5 kitchens that year and **24 by end of next year** (22 in Tashkent, 2 in regions). The first site was to be 350 m². Six known brands were invited (pizza, two fast-food brands, pan-Asian); partners bring their own equipment and serve delivery only. Each kitchen needs 30–80 kW of power, and investment is $200–250k per mid-size kitchen. Dishes taking more than 30 minutes to cook are removed. Express24 earns from order commissions, not rent. — [Kapital.uz, 31.01.2023](https://kapital.uz/dark-kitchen-v-uzbekistane/)
- Rationale given: central restaurants have small kitchens and cannot cope at lunch (40–50 minutes cooking time), and order density lowers delivery cost. — [Kapital.uz, 31.01.2023](https://kapital.uz/dark-kitchen-v-uzbekistane/)
- Restaurants are on several channels at once; for example, the same chains are on Uzum Tezkor, the Yandex/Express24 apps and their own bots. — [Kursiv, 19.12.2023](https://uz.kursiv.media/2023-12-19/uzum-tezkor-zapustil-telegram-bota-dlya-zakaza-edy/); [Repost.uz, 07.08.2023](https://repost.uz/uzum-and-iiko)
- Integration motive: without integration, orders are manually transferred from the aggregator app to the till. — [Yandex Eda KG](https://yandex.ru/project/eda/kg/rest_faq_integration); [iweb.kg](https://iweb.kg/en/cases/iiko-eden-taste)

### Inferences
- POS requirements for dark kitchens and multi-brand venues:
  - **multiple brands (virtual menus) per kitchen and legal entity**, each with its own menu and price list per channel;
  - a single unified order inbox across Yandex, Uzum Tezkor, own bot and phone, with KDS routing by station rather than by brand;
  - per-brand and per-channel P&L;
  - packaging and labelling per brand;
  - one stop-list action (e.g., "out of chicken") that propagates to all brands and channels using that ingredient (ingredient-level stop-list derived from recipes).
- "Tablet hell" in Uzbekistan today means 2 aggregators plus the own bot (3 before Wolt's exit), so it is milder than in Western markets. The main pains are manual re-entry and stop-list desync, which is exactly what the Uzum–iiko integration advertised fixing.

### Gaps
- No 2024–2026 data was found on how many dark kitchens operate in Tashkent, whether Yandex kept the Express24 dark-kitchen sites, or on independent virtual brands.
- No Uzbek-sourced quantification of order-intake time lost to tablets was found.

---

## 5. Coffee shops / cafés: barista flow, labels, loyalty, pre-ordering

### Takeaway
Little Uzbekistan-specific operational documentation is public. Safia is a large pastry/coffee chain (airport branch 2023, ISO 22000). Coffee shops use Telegram channels and bots for promos and pre-orders. Bank and payment-app cashback (Humo Bonus, bank cards, Uzum ecosystem) is the dominant consumer "loyalty" layer rather than café-run programmes.

### Cited Findings
- **Safia** (pastry house and coffee chain): multiple Tashkent branches, the largest two-storey shop/café opened in 2018, and an airport branch opened in Oct 2023 (hot and cold drinks, pastry, sandwiches, salads, desserts). It holds ISO 22000 certification. — [Afisha.uz, 2018](https://www.afisha.uz/restaurants/2018/04/07/safia); [Afisha.uz, 12.10.2023](https://www.afisha.uz/ru/gorod/2023/10/12/safia); [WeProject](https://weproject.media/articles/detail/modnye-kofeyni-tashkenta-kotorye-stoit-posetit/)
- Samarkand coffee shop case (vendor claim): a "1+1 latte" Telegram post to 3,200 subscribers produced 280–350 orders in 4 hours. — [Zoomda](https://zoomda.uz/en/knowledge-base/telegram-bot-prodazhi)
- Card and bank cashback schemes are widespread: HUMO Bonus (2023), TBC Bank Salom Card loyalty (2025–2026 terms), and NBU/Mastercard cashback. — [Spot.uz, 27.11.2023](https://www.spot.uz/ru/2023/11/27/humo-bonus/); [TBC Bank UZ loyalty terms, 2026](https://minio.tbcbank.uz/prod-tbcbankuz-new/uploads/Loyalty_program_01052026_RU_2985c94bfa.pdf)
- Uzum's own fintech payments exceed 50% of payments in its e-commerce services, and Uzum To'lov is a single QR code for card or installment payments. — [Kapital.uz](https://kapital.uz/uzum-podvel-itogi-2025-goda/)
- Delever and Zoomda offer white-label loyalty and "repeat order" features to restaurants. — [The Tech](https://the-tech.kz/delever-uz-startap-kotoryj-zanimaetsya-dostavkoj-edy-v-uzbekistane-i-kazahstane/); [Zoomda](https://zoomda.uz/en/knowledge-base/telegram-bot-prodazhi)

### Inferences (domain knowledge)
- **Coffee-shop POS needs:**
  - a fast modifier grid (size, milk type, syrup, extra shot, temperature) with price deltas;
  - printing a **cup sticker or label** per drink (name, modifiers, order number) on a label printer;
  - a barista KDS or ticket queue separate from the pastry and food counter;
  - **pre-order from a Telegram bot or Mini App** with a pickup time slot;
  - a stamp card ("6th coffee free") or percentage cashback tied to phone number or Telegram ID;
  - promo mechanics such as 1+1 and happy hours.
- Because consumers already get cashback from cards and banks, café loyalty in Uzbekistan more plausibly works as a Telegram-linked balance or stamp programme than as a plastic card.

### Gaps
- No public information was found on Safia, Bon! or other coffee chains' apps, stamp cards, subscriptions or POS vendors. Their Telegram and Instagram channels (e.g., @safia_uz) would be the primary source.
- No Uzbekistan data was found on coffee subscriptions.

---

## 6. Loyalty / cashback in QSR and cafés

### Takeaway
Documented loyalty in Uzbek food service is mostly platform-driven: aggregator promos and free-delivery thresholds, bank-card cashback, and white-label bot or app loyalty from Delever and similar vendors. No public detail was found on chain-run programmes at Evos, KFC or Max Way.

### Cited Findings
- Consumer incentive expectations (2022): free delivery on the first order or above a minimum basket. — [UzNews, 13.07.2022](https://uznews.uz/ru/posts/57080)
- The Uzum Tezkor bot offered discounts and offers, with free delivery through the end of December 2023. — [Kursiv, 19.12.2023](https://uz.kursiv.media/2023-12-19/uzum-tezkor-zapustil-telegram-bota-dlya-zakaza-edy/)
- Loyalty programmes were recommended for Evos in an academic case. — [WIUT](https://science.wiut.uz/en/graduate-activities/graduate-cafe-discussion-series/180-graduate-cafe-featuring-shakhnoza-pardaeva)
- Delever bundles loyalty with its own channels. — [The Tech](https://the-tech.kz/delever-uz-startap-kotoryj-zanimaetsya-dostavkoj-edy-v-uzbekistane-i-kazahstane/)

### Inferences
- The POS should keep a customer profile keyed by phone and Telegram ID, with:
  - cashback or points that can be earned and spent across counter, bot and app (not aggregator orders, where the customer belongs to the platform);
  - promo codes;
  - combo/1+1 promotions;
  - per-channel promo-cost tracking.

### Gaps
- No specifics (rates, mechanics) were found for any named chain's loyalty programme.

---

## 7. Reports chains need

### Takeaway
No Uzbek source enumerates chain reporting needs. They follow from the facts above: 80–100 branches, 24/7 outlets, 2–3 delivery channels at 20–35% commission, and delivery making up 30–50% of orders.

### Cited Findings
- Delivery is about 30–50% of restaurant orders (partner estimate). — [Gazeta.uz, 21.05.2026](https://www.gazeta.uz/ru/2026/05/21/uzum-tezkor/)
- Hidden delivery costs: packaging at 3,000–5,000 so'm per order, own couriers and vehicles, discounts and promos. Busoft advises analysing dish sales. — [Zira.uz, 17.11.2025](https://zira.uz/ru/2025/11/17/kak-segodnya-rabotaet-dostavka-v-uzbek/)
- Peak lunch hours are the pressure point; iiko cites help with "peak lunch hours", and Zoomda cites a 14–16h delivery peak. — [Repost.uz](https://repost.uz/uzum-and-iiko); [Zoomda](https://zoomda.uz/en/knowledge-base/telegram-bot-prodazhi)

### Inferences
- Minimum report set:
  - sales by **branch × channel** (dine-in, takeaway, own delivery, Telegram bot, Yandex, Uzum Tezkor) × **hour**;
  - **net revenue after aggregator commission and packaging**;
  - average check and order count per channel;
  - combo attach rate;
  - KDS speed (order-to-ready time by station, by hour);
  - stop-list duration (lost sales);
  - cancellations and refunds by channel and reason;
  - courier performance (own fleet);
  - business-day reports for 24/7 branches;
  - aggregator **payout reconciliation** (expected vs received).

### Gaps
- No Uzbek chain has published KPI or reporting requirements. Interviews or job descriptions (e.g., analyst roles at Evos or Oqtepa) would be needed.
