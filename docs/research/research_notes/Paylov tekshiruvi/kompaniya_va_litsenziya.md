# Paylov: company, licence, owners, incident (notes)

All checked 21.11.2026. Labels: [PRIMARY] = read the source itself (CBU file, Paylov's own pages/docs, App Store). [MEDIA] = press, aggregators, search summaries. [NOT FOUND] = I could not see it; it does not mean "does not exist".

## Legal entity

| Fact | Value | Source | Label |
|---|---|---|---|
| Legal entity behind the Paylov brand | АО «OCTAGRAM» (joint-stock company) | CBU register of payment organisations, xlsx, row 25 (file modified 03.11.2026): https://cbu.uz/upload/medialibrary/e28/ofmipzc9199944bp01q47ntpt7xwfoeb/Reestr_platezhnyh_organizaciy_ru.xlsx (linked from https://cbu.uz/ru/payment-systems/registers/payment-organizations/) | [PRIMARY] |
| Licence | Payment organisation (not a payment system operator), licence No 40, issued 10.03.2022 | same CBU file | [PRIMARY] |
| Legal address in CBU register | 100093, Tashkent, Yunusabad district, Malaya koltsevaya doroga, 44/17 | same CBU file | [PRIMARY] |
| TIN (INN) | 307993034 | Paylov public offer (app.paylov.uz page "public-offer", requisites block); also orginfo.uz | [PRIMARY] offer / [MEDIA] orginfo |
| Requisites in the public offer | Address Tashkent, Mirzo Ulugbek district, Gulbadanbegim 26; bank "Tenge bank" ATB, MFO 01176; OKED 62010 (software); director Kurbonov K.U. | Paylov public offer, https://paylov.uz/pages/public-offer (content served by the site's API) | [PRIMARY] (offer is undated) |
| Address mismatch | Offer says Mirzo Ulugbek / Gulbadanbegim 26; CBU register says Yunusabad / 44/17 | both above | [PRIMARY] - ask which is current |
| Registration date | 14.12.2020; charter capital 28 709 200 000 UZS; director QURBONOV QILICHBEK ULUG'BEK O'G'LI; founders shown as "AKSIYADORLAR 100%"; email ceo@paylov.uz, phone +998 90 137 44 21 | https://orginfo.uz/ru/organization/7f846290fa45/ (data as of 03.01.2026 per page) | [MEDIA] |
| Share issue | 27 847 924 common shares x 1 000 UZS, closed subscription | UzDaily 04.09.2024 https://www.uzdaily.uz/en/octagram-to-issue-shares-worth-2785-billion-soums | [MEDIA] |
| Owners (named shareholders) | Not disclosed | orginfo shows only "shareholders 100%"; openinfo search found nothing | [NOT FOUND] |
| Founders | Otabek Khakberdiev (ex-bank manager) and Alisher Tadjiev (developer / security); idea 2019, company 2020, development 2021; 450+ business clients, 1.1M end users | digitalbusiness.kz 29.12.2025 (page 403 to me, read only via search summary) | [MEDIA] |
| Consumer app | "Paylov" on App Store, seller "Octagram LLC" / "OCTAGRAM, MAS'ULIYATI CHEKLANGAN JAMIYATI" (stale form; register says AO); v1.0.0 06.04.2025, v1.3.36 28 Oct; 4.4 from 7 ratings; P2P, utilities, card management. Android id uz.octagram.paylov | https://apps.apple.com/uz/app/paylov/id6743964462 ; Play id seen in paylov.uz JS bundle | [PRIMARY] |
| Contacts on site | Support: https://t.me/paylov_support_bot ; instagram.com/paylov.uz ; facebook.com/paylov.uz ; linkedin.com/company/paylov ; merchant cabinet https://my.paylov.uz/ | paylov.uz JS bundle (site is a JS app, no text without JS) | [PRIMARY] |

For comparison in the same CBU file: АО «Click» licence No 1 (28.03.2020), АО «PAYME» licence No 3 (04.04.2020), АО «MULTICARD PAYMENT» No 26, АО «Maroqand» (UPAY, Humans Pay) No 6. [PRIMARY]

## Competitor check

- No POS, cash-register or restaurant software from Paylov found. Site sections: home, business, application, blog, FAQ, feedbacks. The business offer is the payment API. [PRIMARY for site routes; NOT FOUND for any POS product]
- The consumer app competes with Payme/Click wallets, not with RIZQIM POS. [PRIMARY App Store]
- Earlier RIZQIM report already concluded "Paylov is a payment service, not a POS" (reports/Alipos va agregator POS modeli.md).
- Verdict: not a competitor. Watch item: docs list "QR Payments" and "Didox" (e-documents) as closed sections; if QR is a merchant QR product it overlaps with our POS QR feature but not with POS itself.

## December 2025 incident (Humans)

- Humans Companies was Paylov's agent (contract No 32, 10.04.2025) for payments and P2P. [MEDIA gazeta.uz 09.12.2025, uznews.uz 09.12.2025 quoting CBU press service]
- Mass complaints about unauthorised card debits via the Humans app. CBU instructed Octagram to suspend P2P; from 08.12.2025 all Octagram payment services in Humans were suspended. [MEDIA, quoting CBU]
- Humans: attackers reached Paylov's API without OTP; Paylov did not block attacking IPs. Octagram: no vulnerability; fraud used encrypted keys Octagram gave Humans, which Humans had to keep safe; no incidents with other partners. [MEDIA kun.uz 09.01.2026; gazeta.uz 09.12.2025]
- 825 complaints to Humans' contact centre (06.12.2025-08.01.2026); another report says 2 000+ users, ~7 bn UZS. [MEDIA kun.uz 09.01.2026]
- CBU working group inspected "Octagram" JSC; report sent to Tashkent city police (IIBB) on 07.01.2026. Findings not published. [MEDIA kun.uz 07.01.2026]
- Licence still listed in CBU register file modified 03.11.2026. [PRIMARY]
- Meaning for RIZQIM: the dispute is about who kept API keys safe. Our own API keys would sit on our server; a leak on our side would let someone charge saved cards. Same risk exists with any provider, but here it already happened once with a partner.

Sources:
- https://www.gazeta.uz/ru/2025/12/09/octagram/
- https://www.uznews.uz/ru/news/105534
- https://kun.uz/en/news/2026/01/07/central-bank-urges-victims-of-humans-app-fraud-to-contact-police
- https://kun.uz/en/news/2026/01/09/humans-reports-825-complaints-over-fraudulent-withdrawals-linked-to-paylov
- https://www.spot.uz/ru/2025/12/09/paylov

## Fees, settlement

- Merchant fees: not published anywhere I could find. Site FAQ (consumer): "most operations without commission". [PRIMARY FAQ; NOT FOUND merchant tariff]
- Settlement time to merchant / sub-merchant: [NOT FOUND]
- UzQR participation of Paylov: not named in kun.uz UzQR explainer (03.07.2026) or elsewhere. [NOT FOUND]
