# Legal findings that change the provider choice (lex.uz VM 943, read 21 Nov 2026)

Source for everything here unless stated: [lex.uz VM 943, consolidated ru/uz text](https://lex.uz/ru/docs/-4603329), read in three parts (offsets 0, 100000, 200000) on 2026-11-21 through a fetch summariser. Quotes are short fragments of the original Uzbek text. [PRIMARY], but read through a summariser: exact wording should be re-checked by a lawyer before decisions.

## 1. New TXKM exclusion ground (clause 25, paragraphs 9-10 of the TXKM procedure), from 22.10.2026

- Added by VM 393 of 20.07.2026 (Milliy baza 21.07.2026, 09/26/393/0764), in force 22.10.2026.
- Paragraph 9: a TXKM is excluded from the register when it is found that "Davlat reyestriga kiritilgan onlayn-NKTlarga boshqa turdagi (ikkilamchi) Davlat reyestriga kiritilmagan yoki kiritilgan axborot tizimlari (avtomatlashtirilgan hisobga olish o'lchov vositalari, dasturiy mahsulotlari, buyurtmalar hisobini yuritish) ... QR-kodli chek chop etish maqsadida integratsiya qilinganligi aniqlanganda".
- Paragraph 10: also when a registered online-NKT is found able to print receipts without a QR code.
- Plain reading: if another (secondary) system, registered or not, including order-accounting software, is integrated into a registered cash-register product in order to print QR receipts, the TXKM that services that cash register loses its registration.
- Inference (not confirmed by any official explanation found): this is aimed exactly at the "restaurant POS sends the bill to someone else's kassa, kassa prints the QR receipt" model (Poster+Multikassa, Paloma+UzKassa). If it is enforced that way, any provider that lets RIZQIM print through its kassa puts its own TXKM status at risk, and therefore RIZQIM's customers too. The term "onlayn-NKT" is not separately defined in the visible text; clause 3 groups online-NKM and virtual kassa as "nazorat-kassa texnikasi", so it probably covers virtual kassas too [inference].
- No official Tax Committee clarification after 22.10.2026 was found [NOT FOUND].

## 2. Clause 161 (TXKM procedure), from 22.10.2026

- "TXKM yoki tadbirkorlik subyektlari tomonidan soliq organlarining axborot tizimlari bilan integratsiya qilinmagan avtomatlashtirilgan hisobga olish o'lchov vositalari ... yoki buyurtmalar hisobini yuritish dasturiy mahsulotlaridan foydalanishga imkoniyat yaratish taqiqlanadi."
- Read together with clause 25: an order system must itself be "integrated with tax information systems"; being bolted onto someone else's kassa is the case clause 25 punishes. The only clearly safe status is for the order system to be a registered virtual kassa itself [inference].

## 3. Annex 1, clause 4 (amended by VM 393, in force 22.10.2026)

- Bans using "boshqa turdagi (ikkilamchi) fiskal ma'lumotlar hisobini yuritish tizimlaridan" together with a registered online-NKM or virtual kassa; for "umumiy ovqatlanish korxonalari tomonidan" non-integrated accounting/order software "... buyurtmalar hisobini yuritish dasturiy mahsulotlaridan foydalanish taqiqlanadi".
- 2nd paragraph (added by VM 255, 12.05.2022): retail and catering businesses must integrate their automated accounting tools with tax information systems.
- Also clause 4: when a business's own automated accounting system is registered as a virtual kassa, the reserve-deposit requirement does not apply.
- Tax Committee position (2023): R-Keeper, JOWi, iiko etc. must be integrated "as a virtual kassa" [MEDIA, buxgalter.uz 25.12.2023].

## 4. Registering a virtual kassa in the State Register (Nizom, clauses 11-22)

- Applicant: a legal entity (manufacturer may initiate) applies to the Tax Committee with Annex 2 form (clause 11).
- Documents (clause 11): reserve/guarantee of 200 BHM (**not required for a virtual kassa**), operator (OFD) conclusion on technical and functional compliance, receipt samples, reference copy, technical passport, user documents, spare-parts guarantee, patent/copyright, Cybersecurity Center expertise conclusion, certificate of conformity.
- Deadlines: OFD conclusion 10 working days (clause 12); technical-regulation agency and "O'zeltexsanoat" 5 working days (clause 12-1); Cybersecurity Center 15 working days (clause 11); decision 20 working days (clause 16). Total pure waiting if done in sequence: about 50 working days (~2.5 months); some steps may run in parallel [inference].
- Exclusion (clauses 19-22): grounds incl. technical non-compliance; warning gives 10 days to fix (20-1), then exclusion (20-2), notice within 3 days (21); users must replace within 15 days (22).
- Cost of OFD conclusion, Cybersecurity expertise and certificate of conformity: [NOT FOUND].
- Earlier media description (2023) says the developer applies and lists 10 documents; matches the above [MEDIA buxgalter.uz text195312]. One 2026 search summary claims the TXKM files the application [MEDIA, contradicted by clause 11].

## 5. TXKM requirements (TXKM procedure)

- Deposit (clause 7), by number of registered devices/virtual kassas serviced: up to 1 000 -> 100 BHM; 1 000-5 000 -> 200 BHM; 5 000-10 000 -> 500 BHM; over 10 000 -> 1 000 BHM. A bank/insurer guarantee letter is allowed; can be built up over six months. At BHM = 440 000 so'm (implied by fbox.uz "0.5 BHM = 220 000") 100 BHM = 44 mln so'm [inference on BHM value].
- Clause 13: before registering a device/virtual kassa for a user, TXKM checks its condition and software.
- Clause 14: call-centre numbers on the official site; clause 15: material base and spare devices at least 5% of devices under warranty contracts.
- User registration of a kassa via TXKM: TXKM records within 4 working hours, tax officer reviews in 8 working hours, free of charge (Annex 1 clauses 16-20).
- Only TXKMs in the register may sign service contracts [MEDIA legalise.uz].

## 6. Virtual fiscal module = marketplace instrument

- In the visible text, the "virtual fiscal module" (unique number from the operator + e-signature public-key certificate) is defined in the **marketplace rules** (clause 3), and marketplace fiscal data is confirmed through it (clause 4). Marketplace application decided within 15 working days (clause 14), with OFD conclusion attached.
- For a restaurant virtual kassa, a fiscal module ("bitta fiskal modul bir vaqtda faqat bitta virtual kassa") issued by the operator on a TXKM's request is used; FM is a physical USB token / smart card [MEDIA buxgalter 18.12.2020; PRIMARY qo0p/fiscal-drive-service README].
- Marketplace registry: 100 platforms as of 10.10.2025 incl. "Yandex Samokaty", 1Fit; documents: OFD conclusion, receipt samples, APK + URL, technical passport [MEDIA spot.uz 10.10.2025]. Not found whether a food-ordering Telegram platform qualifies [NOT FOUND].

## 7. Offline and other functional rules

- 48 h offline -> automatic blocking (online-NKM requirements clause 3; virtual kassa clause 6) [PRIMARY].
- From 01.04.2026 (UP-246, 10.12.2025): kassas must block cash sales for designated IKPU codes (alcohol incl. **beer**, tobacco, fuel, items over 25 mln so'm per IKPU), block expired goods, prescription QR, social cards; if one restricted item is on a receipt the whole receipt must be cashless, expert advice is to split receipts [MEDIA buxgalter.uz 03.04.2026, updated 15.04.2026]. Directly relevant to restaurants selling beer.
- E-POS removal confirmed on the official channel: virtual kassa "E-POS" and TXKM E-POS Systems MChJ removed for not meeting legal requirements, warning letters sent to 11 000 taxpayers; 15-day replacement (Russian text; the Uzbek text reportedly says 5 days) [PRIMARY t.me/soliqnews/21589, post date not visible; media date 30.10.2026].
- Earlier removals: 11.09.2024 TXKMs of virtual kassas "Pos2k" (SOFT TECH SOLUTIONS), "YPOS" (YOUR SOFT), "Shtrix-M Kassir 5" (Smart One Club) [PRIMARY t.me/soliqnews/14305]; 28.04.2026 "Uzkass-185" and TXKM "Bestpos" [PRIMARY gov.uz/soliq/news/159141].

## Gaps
- Official explanation of clause 25 para 9 (does a POS feeding a registered virtual kassa through API count as "ikkilamchi tizim ... integratsiya"?).
- Fees for OFD conclusion, Cybersecurity Center, certificate of conformity; who issues the certificate.
- Whether a cloud-hosted FM (data centre) is allowed for a restaurant virtual kassa.
