# How food delivery aggregators use POS and restaurant software to onboard merchants, and how they price it (as of Oct 2026)

Research date: 2026-10-24. Scope: evidence for Rizqim (one marketplace Telegram bot / Mini App plus a POS that helps restaurants connect faster). Every figure is dated where the source allows. Many sources are vendor blogs or search-summary snippets; reliability is flagged inline.

---

## 1. Cases: how comparable aggregators bundle POS or merchant software

### Takeaway
No major Western or Indian food aggregator found sells its own full restaurant POS. The dominant pattern (DoorDash, Uber Eats, Delivery Hero/Talabat, Wolt, Glovo, Grab, Yandex Eda, Uzum Tezkor) is free integration with third-party POS systems such as Toast, Square, iiko, r_keeper and JOWi, plus a cheap or free tablet or merchant app for small venues. The two clear exceptions bundle POS from the payments side, not the delivery side. Kaspi (Kazakhstan) gives merchants free POS terminals and POS Register software. iFood (Brazil) sells a card machine through its own bank, iFood Pago. DoorDash is the aggregator moving closest to a "restaurant OS" (Storefront, SevenRooms, DashOS) but, per available reports, still has no first-party POS device.

### Cited Findings

**DoorDash (US)**
- POS integrations are free. A DoorDash tablet is the alternative order method, at $6/week in the US, $3/week in Canada and $0/week in Australia and New Zealand (DoorDash merchant FAQ, accessed Oct 2026). — [DoorDash Merchant FAQ](https://merchants.doordash.com/en-us/faq)
- DoorDash "Online Ordering" (own-channel ordering, formerly marketed as Storefront) is "commission-free, and there are no activation fees or monthly software fees". The merchant pays only standard payment processing. — [DoorDash Merchant FAQ](https://merchants.doordash.com/en-us/faq)
- Restaurantology (15 Apr 2025) calls Storefront a "commission-free direct ordering tool". A March 2025 scan of about 15,000 multi-unit restaurant websites found 399 brands using it: 201 with 2–5 units, 146 with 6–20, 46 with 21–100 and 6 with 101+. The article says rumors of a DoorDash POS exist but "details remain scarce". It flags the risk that order, guest and transaction data consolidate inside DoorDash's ecosystem, making DoorDash the restaurant's "first party" and pressuring POS and middleware vendors. — [Restaurantology, 2025-04-15](https://www.restaurantology.io/2025/04/15/the-storefront-strategy-is-doordash-quietly-becoming-a-restaurant-operating-system/)
- DoorDash agreed to acquire SevenRooms (reservations and guest CRM) for about $1.2B in May 2025. — [Restaurant Business](https://www.restaurantbusinessonline.com/technology/sevenrooms-deal-doordash-enters-dining-room)
- DashOS was announced around 30 Sep 2026. It links customer records across the Marketplace, Online Ordering, SevenRooms, loyalty and marketing. Beginning in 2027, DoorDash plans to extend these tools to more than 80,000 restaurants on third-party POS and middleware, including Clover (Fiserv), Qu, Checkmate, Chowly, Deliverect, Stream and UrbanPiper. Jefferies' view is that DashOS is unlikely to challenge Toast's core POS but could compete with Toast's add-on software. These facts come from news search summaries, not DoorDash's own release text. — [StockTitan / DoorDash release](https://www.stocktitan.net/news/DASH/door-dash-introduces-dash-os-one-platform-to-help-restaurants-g0keffhizdeo.html); [PPC Land](https://ppc.land/doordash-plans-loyalty-tools-for-80-000-restaurants-on-third-party-pos/); [Stocktwits on Jefferies](https://stocktwits.com/news-articles/markets/equity/tost-stock-gets-a-door-dash-ai-test-jefferies-breaks-down-where-dash-os-could-pressure-toast/cZDiuaZRBLz)
- PYMNTS reported that DoorDash has not launched a POS device, and that a Rothschild Redburn analyst (May 2026) saw DoorDash moving toward Toast-like in-store systems. — [PYMNTS 2026](https://www.pymnts.com/restaurant-technology/2026/doordash-projected-to-reach-20percent-of-us-restaurants-by-2035/); [PaymentsJournal](https://www.paymentsjournal.com/doordash-could-expand-into-payments-with-restaurant-pos-launch/)

**Square + DoorDash / Uber Eats (POS adding marketplace connectivity)**
- Square's direct integration lets restaurants manage DoorDash orders, items and menus from the Square Dashboard, with automatic sync. — [Square App Marketplace](https://squareup.com/us/en/app-marketplace/app/doordash)
- DoorDash lists Square as a "Preferred Integration" partner covering Marketplace, Online Ordering and Caviar orders. — [DoorDash Help](https://help.doordash.com/en-us/merchants/article/square-pos-integration-vs-other-order-protocols)
- Square's May 2025 food-and-beverage release announced streamlined Uber Eats and DoorDash integrations. — [Square press release](https://squareup.com/us/en/press/releases-food-and-beverage)

**Toast (reverse case: POS adding delivery)**
- Toast says major delivery integrations can be added at no cost, but marketplace fees still apply (vendor claim). — [Toast integrations guide](https://pos.toasttab.com/blog/on-the-line/toast-pos-integrations); [Toast 3PD integrations](https://pos.toasttab.com/third-party-delivery-integrations)
- Toast Delivery Services (own-channel delivery fulfilled by gig fleets) charges flat per-delivery fees, not a commission:
  - Uber Direct: $6.99 under 6 miles, $8.74 for 6–8 miles.
  - DoorDash Drive: $7.49 within 5 miles, plus $0.50 per additional mile up to 10 miles.
  - Toast adds a $3.99 delivery service charge to the guest bill to offset the fee.
  - Toast support pages show "last updated" dates of Jul 2025 and Mar 2026, and they disagree slightly on the fee table. — [Toast Support](https://support.toasttab.com/en/article/Toast-Delivery-Services); [Restaurant Business](https://restaurantbusinessonline.com/technology/toast-gives-restaurants-cheaper-delivery-option-uber)

**Uber Eats**
- Supports POS integrations including Toast, Square, NCR and Olo. With Toast, the menu, prices, availability and orders sync with the existing Uber Eats account. — [Uber Help: Toast integration](https://help.uber.com/en/merchants-and-restaurants/article/set-up-your-toast-integration-with-uber-eats?nodeId=98ca8bb8-f63b-40de-86d1-06efed3a63fd)
- No first-party Uber POS was found.

**Delivery Hero / Talabat / Foodpanda**
- Delivery Hero runs an integration middleware: a POS vendor builds a plugin, and accepted orders are forwarded to the restaurant POS. Orders can also be handled in Delivery Hero's own vendor application, with auto-accept available on request. Talabat markets (UAE, Kuwait, Bahrain, Oman, Qatar, Jordan) are supported. — [Delivery Hero POS API docs](https://developers.deliveryhero.com/documentation/pos.html)
- Talabat's integration site says its POS solutions help partners "operate more efficiently and accurately" and make it easier to onboard new stores. — [Talabat POS Integration](https://integration.talabat.com/en/about/)
- No first-party Delivery Hero or Talabat POS product was found.

**Wolt / Glovo**
- Wolt connects restaurant POS systems directly or via middleware. Orders auto-appear in the POS, and menu, hours and availability sync. — [Wolt Merchant: POS integration](https://merchant.wolt.com/en/aut/learning-center/pos-integration)
- A Kazakh bank article says small venues use the Wolt Merchant Lite app, while chains connect a POS via API. — [Alatau City Bank (kk)](https://alataucitybank.kz/business/osim/kk/articles/kogamdyk-tamaktanu-ornyna-ontayly-zhetkizu-servisin-tandau)
- Glovo integrates through middleware such as Deliverect, which requires subscriptions to both Glovo and Deliverect. A French blog puts Glovo commission at about 25–30% excluding VAT (secondary source). — [Deliverect Glovo](https://website-prod-assets.deliverect.com/sv-se/integrations/glovo); [Fooderise](https://www.fooderise.com/en/blog/integrer-glovo-restaurant-fonctionnement-commission-2026/_payload.json)

**Grab / GrabFood**
- Grab's merchant app includes a "Point of Sale (PoS) solution" for managing stores and taking QR payments (older Grab-sponsored feature article, about 2 years old). — [DealStreetAsia partner content](https://www.dealstreetasia.com/partner-content/how-grabfood-is-overcoming-the-challenge-of-food-delivery-in-southeast-asia)
- Grab signed an MoU with Cambodian POS maker T.O Group to inject GrabFood orders into its POS, so partners no longer juggle devices or re-key orders. — [Phnom Penh Post](https://phnompenhpost.com/socialite/grab-t-o-group-partner-to-integrate-grabfood-orders-with-pos-platform/)
- Grab offers a GrabFood API for POS and SaaS partners, and a separate Grab POS API for GrabPay in-store payments. — [apis.io SEA food delivery](https://apis.io/regions/southeast-asia/food-delivery/)

**Zomato / Swiggy (India)**
- Integration is done by third-party POS vendors (Petpooja, Restroworks, Sapaad and others), which pull Swiggy and Zomato orders onto one screen and sync menus. Petpooja's paid plans are roughly Rs 3,000–8,000/month. — [Petpooja](https://blog.petpooja.com/industry-business-guides/sync-menu-across-swiggy-zomato-and-pos/); [aidukan.in roundup 2026](https://aidukan.in/best-billing-software-restaurant-india/)
- No 2023–2026 evidence was found of Zomato or Swiggy offering their own POS.

**iFood (Brazil)**
- iFood Pago, iFood's digital bank, sells "MaquinONA", a credit/debit card machine. Restaurants can apply app offers (cashback, promotions) to in-person sales. A 2026 comparison site puts rates from 1.89% (debit) to 2.99% (credit) with no monthly rent. Saipos (a POS vendor) says to contact the acquirer for MaquinONA rates. — [Selectra 2026](https://selectra.net.br/financas/maquininha/ifood); [Saipos](https://saipos.com/integracoes/ifood/ifood-maquininha)
- iFood marketplace pricing per Saipos (vendor blog, about 2025):
  - 12% commission on the Básico plan (restaurant delivers) and 23% on the Entrega plan (iFood delivers).
  - Plus a 3.2% online payment fee.
  - Monthly fee R$110 (Básico) or R$150 (Entrega), reportedly waived under R$1,800/month in sales.
  - Verify these figures against iFood's Portal do Parceiro. — [Saipos: Taxa iFood](https://saipos.com/integracoes/ifood/taxa-ifood)

**Kaspi.kz (Kazakhstan): the strongest "free POS" precedent, but on the payments side**
- Kaspi Pay POS terminals are provided free of charge, with an installed base of about 465k devices by end-2022 (investor newsletter, secondary). Kaspi's tariff pages say delivery and servicing of terminals are free. Listed rates:
  - Connection: 0 ₸
  - QR payments: 0.95%
  - Kaspi Gold via Smart POS: 1.29%
  - Other banks' cards: 2.3%
  - Monthly fee: 0 ₸ in the first month, then 1,950 ₸ (third-party tariff aggregator; date unclear). — [Fractal Value substack](https://fractalvalue.substack.com/p/the-super-app-in-kazakhstan-kaspikz); [centeraktiv.kz](https://centeraktiv.kz/poleznoe/informatsiya-dlya-biznesa/kaspi-pay-usloviya-podklyuchenie-tarify.html); [bank.kz](https://bank.kz/banks/kaspi-bank/ekvajring/terminaly/)
- Kaspi FY2024 results (24 Feb 2025):
  - "Kaspi POS Register, launched in 2023, is now used by 35% of Kaspi Pay merchants." It lets merchants create a product catalogue, accept payments, record sales data and issue tax invoices, and adds sales reports, customer trends and inventory management.
  - Kaspi frames it as "another reason for merchants to transact and keep more of their funds with us", alongside Business Deposit (77K accounts by end-2024), Buy-Inventory-Now-Pay-Later and advertising (51,000 advertisers in 2024).
  - Payments Platform: 737,000 merchants in FY2024.
  - Payments take rate: 1.18% (FY2024) and 1.16% (4Q24).
  - Marketplace take rate: 9.7% (FY2024).
  - e-Commerce take rate: 11.3% (FY2024). — [Kaspi 4Q & FY2024 Results PDF](https://ir.kaspi.kz/media/4Q__FY_2024_Results.pdf)
- In 2023, Kaspi, the Ministry of Finance and the State Revenue Committee launched "Kaspi Kassa", giving entrepreneurs a combined cash register and POS terminal free of charge (Wikipedia, secondary). — [Wikipedia: Kaspi.kz](https://en.wikipedia.org/wiki/Kaspi.kz)

**Yandex Eda (Russia / CIS)**
- Integrates with iiko and r_keeper (r_keeper Delivery documentation). The API is pull-based: Yandex requests data from the restaurant's accounting system. — [r_keeper docs](https://docs.rkeeper.ru/delivery/yandeks-eda-87557916.html); [agregatory.pro](https://agregatory.pro/blog/yandex-eda-integratsiya-iiko)
- Commission figures vary by source; all are blogs or partners, none official:
  - Up to 35% including couriers, ads and VAT (Yupito).
  - 16–35% (iikoService, 2026).
  - 16.67–29.17% (Monster Context). — [Yupito](https://yupito.ru/pages/blog/integraciya-s-yandex-edoj.html); [iikoService](https://iikoservice.ru/blog/kak-zapustit-dostavku-edy-v-2026-godu-polnoe-rukovodstvo-ot-analiza-rynka-do-pervyh-zakazov/); [Monster Context](https://m-context.ru/blog-monster-context/yandeks-eda-dlya-restorana/)

**Uzbekistan**
- Uzum Tezkor partner page:
  - Integrates with iiko, R-Keeper and JOWi.
  - Partners manage orders from a computer, phone or tablet.
  - Registration claimed at no more than 5 minutes.
  - Financial documents go through Didox.
  - Average delivery time 30–35 minutes.
  - Support replies within 1–2 minutes.
  - No commission is stated. — [Uzum Tezkor partners](https://promo.tezkor.com/partners/uz)
- Uzum Tezkor offers delivery from more than 3,000 cafes, shops and restaurants in 26 cities. Uzum and iiko announced a strategic partnership in Aug 2023. The Competition Committee recognized Uzum Tezkor as a dominant digital platform in delivery (Apr 2025). — [Kun.uz 2025-04-02](https://kun.uz/en/news/2025/04/02/uzum-tezkor-named-dominant-digital-platform-in-delivery-market); [Wikipedia: Uzum](https://en.wikipedia.org/wiki/Uzum)
- Wolt announced its exit from Uzbekistan, effective 5 Mar 2026. — [Kun.uz](https://kun.uz/en/01543038); [Gazeta.uz 2026-02-25](https://www.gazeta.uz/oz/2026/02/25/uzum-tezkor/)
- Yandex acquired part of Express24's assets. Yandex Eats and Express24 were previously named dominant players. — [Daryo](https://daryo.uz/en/category/uzbekistan/tii28q81y9yr/)
- Delever, a vendor blog published 6 Feb 2026, gives the following figures. Its numbers are unverified and the page is internally inconsistent on dates.
  - Online delivery market: about $120M (2024), $160M (2025) and an estimated $180–220M (2026).
  - 300–400k online orders per day.
  - Tashkent accounts for 70% of orders.
  - 30–40% of orders are paid in cash.
  - Telegram is called "channel No. 1 for orders".
  - The own-channel share is forecast to rise from 20% to 35–40%.
  - Business platforms named: Delever, iiko Delivery and Jowi (a local POS with a delivery module). — [Delever blog](https://www.delever.uz/blog/delivery-market-uzbekistan-2026)

### Inferences
- An aggregator that bundles its own POS is rare. Most large aggregators chose free integration with incumbent POS vendors, not competition with them. That suggests strong POS-vendor relationships and restaurant neutrality concerns matter. Rizqim offering its own POS is closer to the Kaspi/iFood "ecosystem" playbook than to the DoorDash/Uber playbook.
- In Uzbekistan, the incumbent leader (Uzum Tezkor) onboards through iiko, R-Keeper and JOWi integration. A Rizqim POS will compete with those POS vendors for the restaurant's back office, which makes iiko/JOWi integration (or coexistence) strategically important.
- With Wolt gone (Mar 2026), the market is concentrated in Uzum Tezkor and Yandex/Express24. A neutral, low-commission Telegram-native marketplace may find a gap. This is not verified by data.

### Gaps
- DoorDash Bbot (acquired 2022): no 2023–2026 source on its current status, pricing or whether it was folded into DoorDash Online Ordering.
- "Talabat Pro": no source found describing it as a merchant POS product. It appears to be a consumer subscription, but this was not verified.
- Swiggy, Foodpanda, Rappi: no first-party POS evidence found. No current official commission data found for Swiggy.
- Yandex: no evidence found of a Yandex-owned restaurant POS or of tablet pricing for Yandex Eda partners.
- Kyrgyzstan: no aggregator found that bundles POS.
- Express24 and Uzum Tezkor commission rates: not published on the pages found.

---

## 2. Pricing: free POS with commission, thresholds, reduced commission for POS users, hardware subsidies; commission by channel

### Takeaway
The market standard is a tiered marketplace commission:
- 15–30% for aggregator-delivered orders.
- About 6–10% for pickup.
- 0% commission for the restaurant's own channel (paying only payment processing or a flat delivery fee).
- Free POS integration on top.

No aggregator found explicitly cuts its commission for restaurants that use its POS. Free hardware is subsidized by payment take rate (Kaspi) or sold at low fees (iFood MaquinONA), not by delivery commission.

### Cited Findings
- **DoorDash (US, accessed Oct 2026):** Basic 15%, Plus 25%, Premier 30% delivery commission; pickup 6% for partners matching in-store prices. Online Ordering is commission-free; POS integrations are free; the tablet costs $6/week. — [DoorDash Merchant FAQ](https://merchants.doordash.com/en-us/faq)
- **Uber Eats (US):**
  - 2021 tiers: Lite 15%, Plus 25%, Premium 30%; pickup 6%. — [NRN](https://www.nrn.com/restaurant-segments/uber-eats-and-postmates-introduce-tiered-commission-pricing-for-operators-starting-at-15-)
  - Changes reported effective 11 Mar 2026: Lite rises 15% to 20%. Plus stays 25%, but Uber One member orders are 30%. Premium stays 30%. Pickup rises 6% to 7% with validated in-store pricing, otherwise 10%. Custom rates rise 3 points, capped at 30%. — [Restaurant Dive](https://www.restaurantdive.com/news/uber-eats-increases-marketplace-fees/814294/); [Food On Demand 2026-04-01](https://foodondemand.com/04012026/how-the-uber-eats-fees-stack-up-against-3pd-competitors/)
- **Zomato (India, 2025):**
  - Long-distance fee: Rs 20 on orders over Rs 150 delivered 4–6 km away, Rs 40 beyond 6 km. Total payable to Zomato is capped at 30% of order value. — [Outlook Business](https://www.outlookbusiness.com/corporate/zomato-faces-continued-flak-over-long-distance-fee-nrai-plans-talks-with-deepinder-goyal)
  - One restaurant owner said their effective commission rose from 16% to 25% (single anecdote). — [MediaNama 2025-07](https://www.medianama.com/2025/07/223-zomato-long-distance-delivery-fee-restaurants/)
  - Eternal (Zomato's parent) is reportedly exploring changes to the commission structure. — [Yahoo Finance / Verdict](https://finance.yahoo.com/news/eternal-explores-changes-zomato-commission-114442407.html)
- **iFood (Brazil, about 2025):** 12% (Básico) or 23% (Entrega) plus a 3.2% payment fee plus a R$110–150/month fee, reportedly waived under R$1,800/month in sales. This is the only GMV-threshold mechanism found, and it applies to the marketplace monthly fee, not to a POS. Card machine fees run 1.89% (debit) to 2.99% (credit) with no rent. — [Saipos](https://saipos.com/integracoes/ifood/taxa-ifood); [Selectra 2026](https://selectra.net.br/financas/maquininha/ifood)
- **Kaspi (Kazakhstan):** Free terminal, delivery and servicing. Revenue comes from acquiring fees (0.95% QR to 2.3% other cards) and a 1,950 ₸/month fee after the first month. Kaspi's FY2024 Payments take rate was 1.18%. — [centeraktiv.kz](https://centeraktiv.kz/poleznoe/informatsiya-dlya-biznesa/kaspi-pay-usloviya-podklyuchenie-tarify.html); [Kaspi FY2024](https://ir.kaspi.kz/media/4Q__FY_2024_Results.pdf)
- **Toast (POS-owned delivery channel):** A flat $6.99–8.74 (Uber Direct) or $7.49+ (DoorDash Drive) per delivery instead of a commission, with a $3.99 guest-side service charge. — [Toast Support](https://support.toasttab.com/en/article/Toast-Delivery-Services)
- **Yandex Eda:** 16–35% depending on source and model (blogs, 2025–2026). **Glovo:** about 25–30% excluding VAT (blog). — [iikoService](https://iikoservice.ru/blog/kak-zapustit-dostavku-edy-v-2026-godu-polnoe-rukovodstvo-ot-analiza-rynka-do-pervyh-zakazov/); [Fooderise](https://www.fooderise.com/en/blog/integrer-glovo-restaurant-fonctionnement-commission-2026/_payload.json)
- **India POS pricing (standalone):** Petpooja about Rs 3,000–8,000/month; "free lifetime" POS plans exist from smaller vendors such as PosEase (vendor claims). — [aidukan.in](https://aidukan.in/best-billing-software-restaurant-india/); [PosEase](https://www.posease.com/free-restaurant-pos-billing-software-for-lifetime/)

### Inferences
- Channel pricing benchmark Rizqim can cite:

  | Channel | Typical pricing |
  |---|---|
  | Marketplace delivery (platform couriers) | 15–30% (US); 12–23% plus fees (Brazil); 16–35% (Russia/CIS) |
  | Pickup | 6–10% |
  | Restaurant's own channel | 0% commission, payment processing or a flat delivery fee only |

- "Free POS, paid by commission or payment take rate" is a proven model only where the platform owns a high-frequency revenue stream; for Kaspi, that is payments and lending. For Rizqim, the POS cost could be recovered from marketplace commission, payment acquiring (Payme/Click/Uzum margins, if any) or a small subscription after a free period, as Kaspi does with its 0 ₸ first month.
- No precedent was found for "reduced commission if you use our POS". It is a possible differentiator but untested.

### Gaps
- No public data found on any aggregator offering POS free below a GMV threshold. iFood's fee waiver below R$1,800/month applies to the marketplace monthly fee, not to a POS.
- Official, current Swiggy, Wolt, Glovo, Talabat, Uzum Tezkor and Express24 commission schedules were not found.
- Kaspi's current (2026) POS terminal tariff was not confirmed from a primary Kaspi page.

---

## 3. Outcomes: did bundling increase acquisition or retention; risks

### Takeaway
Direct outcome data is scarce. The best primary evidence is Kaspi: 35% of about 737k Kaspi Pay merchants adopted POS Register within roughly 1–2 years, and Kaspi explicitly frames the software as a retention and "keep funds with us" tool. Talabat says POS integration eases onboarding of new stores. The main risks documented are merchant distrust over data ownership and fee transparency, and friction with POS and middleware vendors.

### Cited Findings
- Kaspi says POS Register (launched 2023) was used by 35% of Kaspi Pay merchants by end-2024, giving "merchants more reasons to be with us". — [Kaspi FY2024 Results](https://ir.kaspi.kz/media/4Q__FY_2024_Results.pdf)
- Talabat says its POS integration solutions make it easier to onboard new stores. — [Talabat POS Integration](https://integration.talabat.com/en/about/)
- Uzum Tezkor markets POS integration with iiko, R-Keeper and JOWi alongside 5-minute registration as an onboarding pitch. — [Uzum Tezkor partners](https://promo.tezkor.com/partners/uz)
- Grab's T.O Group POS deal was justified by removing device juggling and manual re-entry of orders. — [Phnom Penh Post](https://phnompenhpost.com/socialite/grab-t-o-group-partner-to-integrate-grabfood-orders-with-pos-platform/)
- DoorDash Storefront had limited penetration of multi-unit brands: 399 of about 15,000 scanned websites (about 2.7%) as of Mar 2025. — [Restaurantology](https://www.restaurantology.io/2025/04/15/the-storefront-strategy-is-doordash-quietly-becoming-a-restaurant-operating-system/)
- **Risk: data and platform power.** Restaurantology warns that DoorDash could become the restaurant's "first party", with guest and transaction data consolidating in its ecosystem and middleware losing its differentiator. — [Restaurantology](https://www.restaurantology.io/2025/04/15/the-storefront-strategy-is-doordash-quietly-becoming-a-restaurant-operating-system/)
- **Risk: competitive pressure on POS vendors.** Analysts see DashOS pressuring Toast's add-on software, though not its core POS. — [Stocktwits on Jefferies](https://stocktwits.com/news-articles/markets/equity/tost-stock-gets-a-door-dash-ai-test-jefferies-breaks-down-where-dash-os-could-pressure-toast/cZDiuaZRBLz)
- **Risk: fee distrust.** NRAI (India) objected to Zomato's 2025 long-distance fee as raising costs "without transparency or prior consultation". The NRAI has also previously filed against Zomato and Swiggy with the CCI. — [Outlook Business](https://www.outlookbusiness.com/corporate/zomato-faces-continued-flak-over-long-distance-fee-nrai-plans-talks-with-deepinder-goyal); [Madhyamam](https://english.madhyamam.com/india/nrai-files-evidence-with-cci-against-zomato-swiggy-822896)
- **Risk: settlement reconciliation.** Indian POS vendors highlight mismatches between aggregator sales and bank deposits as a pain point that POS can solve (vendor content). — [Petpooja blog](https://blog.petpooja.com/operations-workflows/why-swiggy-zomato-sales-bank-deposits-never-match/)
- **Risk: regulatory dominance.** Uzbekistan's Competition Committee has designated Uzum Tezkor and Yandex Eats as dominant. — [Kun.uz](https://kun.uz/en/news/2025/04/02/uzum-tezkor-named-dominant-digital-platform-in-delivery-market)
- **Advice reflecting merchant hedging behavior:** Delever's blog recommends keeping any single platform below one-third of revenue. — [Delever](https://www.delever.uz/blog/delivery-market-uzbekistan-2026)

### Inferences
- If Rizqim's POS is seen as a way for the marketplace to see a restaurant's offline sales, owners may distrust it, as Indian restaurants distrust aggregators' opaque fees and data. Clear data ownership terms will matter: the restaurant owns POS data, and offline sales are not used for ranking or pricing.
- Competing head-on with iiko and JOWi may slow adoption among chains already on them. A dual path, with a free Rizqim POS for small and new venues and integration with iiko/JOWi for chains, mirrors Wolt's two tiers (Merchant Lite app for small venues, POS API for chains).

### Gaps
- No quantitative before/after data on merchant acquisition or churn attributable to POS bundling was found for any aggregator (DoorDash, Grab, iFood, Kaspi Food).
- No data found on Kaspi's food delivery (Kaspi Food) merchant terms or whether it uses POS Register for onboarding.

---

## 4. Lessons for a new marketplace in Uzbekistan (Rizqim)

### Takeaway
The evidence supports a hybrid design:
- A free or low-cost POS bundled with a single consumer-facing marketplace bot, priced through tiered commission, with no fixed POS fee for small venues at first.
- Integration with incumbent POS systems (iiko, R-Keeper, JOWi) for chains.
- 0% (or flat-fee) commission on the restaurant's own-channel orders.

The model closest to Rizqim is Kaspi (free tools subsidized by an ecosystem take rate). Aggregators like DoorDash and Uber monetize marketplace commission and keep POS neutral.

### Cited Findings
- Telegram is described as the No. 1 ordering channel in Uzbekistan, with 30–40% of orders paid in cash and 70% of orders in Tashkent (vendor blog, 2026). — [Delever](https://www.delever.uz/blog/delivery-market-uzbekistan-2026)
- The leading incumbent, Uzum Tezkor, already offers iiko, R-Keeper and JOWi integration and a claimed 5-minute registration. — [Uzum Tezkor partners](https://promo.tezkor.com/partners/uz)
- Wolt exited Uzbekistan in Mar 2026, reducing competition. — [Kun.uz](https://kun.uz/en/01543038)
- DoorDash's own-channel ordering is commission-free, with revenue only from payments and delivery fees; its marketplace tiers run 15/25/30%. — [DoorDash Merchant FAQ](https://merchants.doordash.com/en-us/faq)
- Kaspi gives free terminals and POS software. 35% of merchants adopted POS Register, and Kaspi monetizes via about 1.2% payments take rate, lending, deposits and ads. — [Kaspi FY2024](https://ir.kaspi.kz/media/4Q__FY_2024_Results.pdf)
- iFood waives its monthly fee for very small merchants (under R$1,800/month in sales, per vendor blog). — [Saipos](https://saipos.com/integracoes/ifood/taxa-ifood)

### Inferences
- **Pricing structure to test:**
  - Free POS software for venues that list on the Rizqim marketplace.
  - A tiered marketplace commission, with lower rates if the restaurant delivers itself and higher rates if Rizqim couriers deliver.
  - Low or zero commission on pickup and the restaurant's own-channel orders.
  - A paid POS tier only for advanced features or multi-branch use.
  - Optionally, a reduced marketplace commission for POS users. This is untested in the market and could be a differentiator.
- **Hardware:** avoid owning a hardware subsidy unless a payments margin covers it, as at Kaspi and iFood. Prefer a BYOD (bring your own device) Android or tablet setup, or a fiscal terminal partnership.
- **Neutrality and trust:** publish data-ownership terms and transparent fee breakdowns, given the NRAI-type backlash seen in India.
- **Coexistence:** build iiko and JOWi integrations so chains can join the marketplace without switching POS. This is the path the incumbents (Uzum, Yandex) already use.
- **Regulation:** the Competition Committee is active (dominance rulings). Any exclusivity requirement ("use our POS to list") may draw scrutiny, so keep the POS optional.

### Gaps
- No Uzbek-specific data on restaurant willingness to adopt an aggregator-provided POS, or on churn from aggregators.
- No public commission data for Uzum Tezkor, Express24 or Yandex Eats Uzbekistan; this needs merchant interviews.
- Delever's market-size figures are from a vendor blog and could not be cross-checked against an official or independent source.
