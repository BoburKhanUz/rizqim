# Fiscal providers in Uzbekistan with an API a third-party POS can integrate (as of 19 Nov 2026)

Scope note: research was capped at about 15 tool rounds. Several vendor pages are JS-rendered or login-gated (docs.epos.uz, soliq.uz registry returned 503/empty), so "not found" below means "not visible in public pages I could fetch", not "does not exist". Page summaries came through a fetch-summariser; items marked (summary) were not read raw. Seen-on date for every item: 2026-11-19 unless stated.


## 0. UPDATE 19 Nov 2026: E-POS status (owner says "tugatilgan") and verification of coordinator leads

### Takeaway
Open sources agree with the owner: the E-POS virtual kassa was removed from the State Register on 30 Oct 2026; treat E-POS as NOT available and do not integrate its API. Exact corporate status (liquidated vs only delisted) is not confirmed by any source I found.

### Cited Findings
- Tax committee removed virtual kassa "E-POS" from the State Register on 30 Oct 2026 for failing technical requirements; OOO "E-POS SYSTEMS" had several warnings and did not fix the defects; businesses had 15 days (to 14 Nov 2026) to replace it; using a non-compliant register carries a 20 million UZS fine; the developer said it hoped to clarify the situation by 10 Nov; I found no later report of reinstatement. Same outlet: the developer's service centre was excluded 7 Nov 2026 — [buxgalter.uz, published 4 Nov 2026](https://buxgalter.uz/publish/doc/text218134_v_kakoy_srok_nujno_zamenit_kassu_esli_ee_isklyuchili_iz_gosreestra) (media; only source; not cross-checked on soliq.uz which returned 503)
- Related mechanism: a service centre excluded from the registry returns reserve fiscal modules to the OFD and is disconnected, so users usually must change service operator or model — [buxgalter.uz](https://buxgalter.uz/publish/doc/text203529_chto_delat_s_kassoy_esli_vash_cotu_isklyuchili_iz_reestra)
- epos.uz pages were still live and advertising on my fetches (blog dated 22 Apr 2026) — [epos.uz](https://epos.uz/en/blog/zapustit-kassu-za-den); no shutdown or merger notice seen there.
- Paloma365 + UzKassa cloud register: confirmed, setup uses cloud URL, cashier phone, cashier password, register serial; per-payment-type mapping to cash/card; failed receipts resendable; shift cannot close with unfiscalized paid receipts. "No extra hardware" is not stated on the page — [Paloma docs](https://dashboard.palomapos.com/docs/en/pos-v2/fiscalization-uz)
- Poster + Multikassa prices confirmed as USD 28 one-time, USD 8/month, USD 5/month — [Poster](https://joinposter.com/en/applications/multikassa)
- `@exode-team/ofd-uz` / test.ofd.uz: npm page returned 403, so NOT verified; no other source found for test.ofd.uz — [npm](https://www.npmjs.com/package/@exode-team/ofd-uz)
- UzKassa/MyUzkassa public API docs: searched again, nothing found (not documented publicly). Related but not fiscal-register APIs: KDB bank PDF on a cash-register link via dynamic QR with per-register API keys — [kdb.uz PDF](https://www.kdb.uz/storage/sample-forms/July2026/9FsQjQVcAbce8wQ9loEX.pdf); ATMOS payments REST API — [docs.atmos.uz](https://docs.atmos.uz/en/)

### Inferences
- Sections 1-7 below that describe E-POS are historical; ignore its API, pricing and onboarding for planning.
- Practical shortlist: Rahmat/Multikassa (partner application, no public docs) and UzKassa (cloud register proven by Paloma, docs unknown). Next step: contact both for API, test stand, advance/credit/mixed support and partner terms.

### Gaps
- Whether E-POS was liquidated, merged or may be reinstated; test.ofd.uz and the npm package contents; UzKassa/MyUzkassa API; bank and payment-system registers (not found).

## 1. Who are the providers, and do they integrate outside POS software?

### Takeaway
Only two of the candidates show a public sign of outside-POS integration: E-POS (public REST API page, but see the registry-exclusion warning) and Rahmat/Multikassa (integrations with iiko, R-Keeper, 1C, MoySklad, Poster, Jowi; API/SDK only "on application"). REGOS publishes a general API but no fiscal methods were found. For every other candidate on the list (Uzkassa, Smartpos, Billz fiscal, Click, Payme, Uzum, Atto, bank apps, Newmax, Arca) I found no public integration offer. Confirmed from provider pages unless marked.

### Cited Findings
- **CRITICAL, E-POS: virtual cash register "E-POS" was excluded from the State Register on 30 Oct 2026** for non-compliance with technical requirements (developer OOO "E-POS SYSTEMS" had received several warnings). Replacement deadline is 15 days, i.e. 14 Nov 2026 (already passed). Article published 4 Nov 2026; an update says the same developer's service centre (TXKM) was excluded on 7 Nov 2026. Source is a single media/accounting outlet (media, not tax committee); I could not open the soliq.uz registry to cross-check — [buxgalter.uz](https://buxgalter.uz/publish/doc/text218134_v_kakoy_srok_nujno_zamenit_kassu_esli_ee_isklyuchili_iz_gosreestra)
- E-POS: company site, REST API page, partners page, Telegram @epossystems, phone +998 71 200-01-73 — [epos.uz API](https://epos.uz/en/products/api). Products: E-POS Cashdesk, E-POS Mobile, E-POS API — same page. Partner programme is open to "integrators, resellers, banks, payment services"; listed partners include Click, Payme, Uzum, Humo, Uzcard, Evos, Texnomart, Billz, MoySklad; a case study "Billz connected E-POS API to its CRM, integration in 2 days" — [epos.uz partners](https://epos.uz/en/partners)
- Rahmat (rhmt.uz): virtual cash register on Windows PC/laptop/tablet; Smart Kassa is an online-KKM on SUNMI P3; docs/offers hosted on corp.multicard.uz — [rhmt.uz](https://rhmt.uz), [tariffs](https://rhmt.uz/pos/tariffs/). Multikassa is "a virtual cash register in the state register of virtual cash desks (ofd.uz)" per the Poster app page — [Poster](https://joinposter.com/en/applications/multikassa). The background report says Multikassa was folded into Rahmat; I did not find a page confirming that merger in this round (not re-verified).
- Rahmat declares integrations with iiko, R-Keeper, 1C, MoySklad; Rahmat Rest also mentions Poster and JOWY — [rhmt.uz/partners](https://rhmt.uz/partners/). Rahmat's FAQ says an "open API" exists for other systems (the wording sits in the smart-kassa answer, unclear whether it covers the virtual kassa) — [rhmt.uz](https://rhmt.uz)
- Rahmat's partner page lists a "technology partner" model: "access to API and SDK, integration with Rahmat POS and Rahmat QR, sales via Marketplace"; application at open.rhmt.uz; partner contacts: Ekaterina Lapshina +998 95 036-99-36, Telegram @lapshina_e_a — [rhmt.uz/partners](https://rhmt.uz/partners/)
- REGOS (docs.regos.uz): a general ERP/POS platform with public REST/JSON API (POST, batch up to 50 calls, webhooks, OAuth flows); has "REGOS: CashServer" and "Cash/CashServer" API sections, but the pages I read show no fiscal/OFD/virtual-kassa methods — [docs.regos.uz/ru/api/intro/quick-start](https://docs.regos.uz/ru/api/intro/quick-start), [docs.regos.uz/ru/api](https://docs.regos.uz/ru/api)
- UzKassa (MyAirGroup, Yashnabad technopark): Android-based online-KKM hardware, 2018 article; no public API mentioned — [spot.uz 2018](https://www.spot.uz/ru/2018/02/20/uzkassa). Paloma365 connects to "UzKassa" as a cloud cash register via cloud URL + cashier phone/password + serial number, and also names EPosKassa and QPosKassa as Uzbek registers — [Paloma POS2 docs](https://dashboard.palomapos.com/docs/en/pos-v2/fiscalization-uz), [Paloma classic](https://dashboard.paloma365.com/docs/en/pos-classic/fiscal-registers)
- F-Kassa (cash app on MoySklad, support +998 78 113-78-90): sends fiscal receipts to the OFD "through an ePOS or PosPoint fiscal module" — [MoySklad catalogue](https://www.moysklad.ru/integratsii/apps/f-kassa-ferrosoft/). PosPoint is a new name: no PosPoint page found.
- Smartpos.uz: only an advertorial on spot.uz (no technical content), site smartpos.uz, t.me/smartpos_uz, +998 71 200-30-50 — [spot.uz 2025](https://www.spot.uz/ru/2025/07/16/smartpos/)
- Jowi: help-centre article "Fiscal printer setup in Uzbekistan" exists (not opened) — [Jowi](https://jowiclub.userecho.ru/knowledge-bases/2/articles/121-nastrojka-fiskalnogo-printera-v-uzbekistane)
- Paynet Xolis (not a cash register): tax committee says it does not remove the cash-register duty; sole proprietors need a QR code from 1 Jan 2026 (decree PP-247) — [kun.uz 24 Mar 2026](https://kun.uz/ru/news/2026/03/24/paynet-xolis-zamenyayet-kassovyy-apparat-ili-net-biznes-putayetsya-v-trebovaniyax-nalogovikov). Not relevant as a restaurant fiscal API.
- Legal base: Cabinet resolution No. 943 of 23.11.2019 (online KKM + virtual kassa, state registries of models) — [lex.uz](https://lex.uz/docs/-4603329). Registry page for virtual kassas is soliq.uz/page/virtual-kassa-reestri (could not load: HTTP 503) — [buxgalter.uz summary of registries](https://buxgalter.uz/publish/doc/text199847_kto_i_kakie_modeli_onlayn-kkt_obyazan_ispolzovat_v_uzbekistane)

### Inferences
- A POS vendor should not build on E-POS until the registry status is checked directly at soliq.uz; the exclusion would invalidate every restaurant fiscalised through it (replacement deadline passed 14 Nov).
- That leaves Rahmat/Multikassa as the only well-evidenced alternative, which strengthens the background report's own-virtual-kassa (A path) argument.

### Gaps
- Not opened/found: Click, Payme/Paycom, Uzum kassa, Atto, Paynet kassa, bank cash-register apps, Newmax, Arca, Sunmi-based fiscal terminals other than Rahmat SUNMI P3, Soliq's own virtual-kassa mobile app, iDokon, Alipos, Mison, Billz fiscal. No evidence either way; each needs its own check.
- Full registry list of virtual kassas (soliq.uz) not retrievable.

## 2. API documentation: public? type, sandbox, keys

### Takeaway
Only E-POS and REGOS expose public API pages; Rahmat gates API/SDK behind a partner application. No sandbox is documented publicly anywhere I could read.

### Cited Findings
- E-POS: cloud REST, `POST https://api.epos.uz/v1/receipts`, Bearer token; page also mentions OAuth2 tokens, IP allowlist, request signing; samples in cURL/JS/PHP; docs at docs.epos.uz (login page "Вход для интеграторов", content not visible without login); portal.epos.uz. Sandbox: not on the page — [epos.uz API](https://epos.uz/en/products/api), [docs.epos.uz](https://docs.epos.uz)
- E-POS capabilities listed on the page: issue receipt (items, payment, cashier_id), refund by original receipt id (partial or full), receipt status/fiscal mark/verification link, catalogue sync, bundled payments (Click, Payme, Uzum, Humo, Uzcard) — same page.
- Rahmat: no developer docs page found; user guide "Rahmat Виртуальная касса" v1.0.0 PDF exists (end-user, no API data per search summary; PDF itself returned 404 when fetched directly) — [search result](https://rhmt.uz/upload/Руководство_пользователя_Rahmat_Виртуальная_касса.pdf). API/SDK access via partner application only — [rhmt.uz/partners](https://rhmt.uz/partners/)
- REGOS: public docs in ru/uz/en, JSON over POST, endpoint pattern `https://integration.regos.uz/gateway/out/{integrationKey}/v1/{method}`, integration created in REGOS.Online; sandbox not mentioned — [docs.regos.uz quick start](https://docs.regos.uz/ru/api/intro/quick-start) (via search summary)
- Poster's Multikassa app: closed integration inside Poster, no public API — [Poster](https://joinposter.com/en/applications/multikassa)
- Uzkassa/Paloma: cloud login per cash register (URL, phone, password, serial) — implies a cloud API but no public docs found — [Paloma](https://dashboard.palomapos.com/docs/en/pos-v2/fiscalization-uz)

### Inferences
- Local-HTTP/Android-SDK/Windows-driver variants are not documented publicly for any provider; the background report's "FiscalDriveService / USB FM" path is a different, regulator-level route, not a provider API.

### Gaps
- Contents of docs.epos.uz (auth flow, sandbox, keys), Rahmat API/SDK, REGOS fiscal methods: not publicly readable; request from vendors.

## 3. Receipt types supported (sale, refund, advance, credit, mixed, MXIK, VAT, marking, Z-report)

### Takeaway
Publicly confirmed: sale and refund (E-POS API; Multikassa in Poster), shift open/close with Z-report and IKPU/MXIK selection (Multikassa in Poster). Advance, credit (nasiya), mixed payment, VAT and marking: not documented publicly for any provider.

### Cited Findings
- Multikassa via Poster: sales and returns only; open/close shift with Z-report; IKPU codes and packaging codes picked from tasnif.soliq.uz inside Poster — [Poster](https://joinposter.com/en/applications/multikassa)
- E-POS API page lists receipts and refunds (partial/full); nothing on advance/credit/MXIK — [epos.uz API](https://epos.uz/en/products/api)
- F-Kassa (MoySklad app, on ePOS/PosPoint): split payments across several types in one receipt, remainder as debt, overpayment kept as advance on customer balance — but this is F-Kassa's accounting logic; whether the fiscal receipt carries advance/credit types is not stated — [MoySklad](https://www.moysklad.ru/integratsii/apps/f-kassa-ferrosoft/)
- VAT regime changed 1 Jun 2026 (6% simplified turnover rate per one summary) — [Yandex Eda UZ support](https://yandex.com/support/eda-vendor-uz/ru/vat); not verified against a primary law text.

### Inferences
- The background report's requirement (advance, credit, mixed payment, MXIK) cannot be satisfied from public evidence; written answers from the vendor remain necessary.

### Gaps
- Advance, credit, mixed payment, marking codes, VAT lines per provider: not documented publicly.

## 4. Hardware, offline

### Takeaway
Rahmat and E-POS virtual kassas run on ordinary PC/tablet/phone; Rahmat's Smart Kassa needs a SUNMI P3. Offline behaviour documented only for F-Kassa (its own app).

### Cited Findings
- Rahmat virtual kassa: Windows PC, laptop or tablet; card/NFC acceptance needs a separate terminal — [rhmt.uz tariffs](https://rhmt.uz/pos/tariffs/). Smart Kassa: SUNMI P3, one-time 990,000 UZS — same page.
- E-POS: PC, laptop or phone almost always enough; printer optional — [epos.uz blog, 22 Apr 2026](https://epos.uz/en/blog/zapustit-kassu-za-den)
- Multikassa: runs on monoblock/PC/tablet, "all devices except iOS" — [Poster](https://joinposter.com/en/applications/multikassa)
- F-Kassa: sales continue offline, uploaded when the connection returns — [MoySklad](https://www.moysklad.ru/integratsii/apps/f-kassa-ferrosoft/)
- UzKassa device (2018): SMS fallback without internet — [spot.uz 2018](https://www.spot.uz/ru/2018/02/20/uzkassa)

### Gaps
- Offline queue-and-send behaviour of Rahmat and E-POS APIs: not found.

## 5. Price and partner terms

### Takeaway
End-customer prices are published for Rahmat and Poster+Multikassa; no per-receipt or revenue-share terms found for any provider.

### Cited Findings
- Rahmat virtual kassa monthly fee: tariff card/intro says 200,000 UZS, while FAQ and navigation say 123,600 UZS (page is internally inconsistent, seen 2026-11-19); Smart Kassa 100,000 UZS/month; SUNMI P3 990,000 UZS one-time; payment terminal 0 UZS/month; one-time services (fiscal-module activation, tax registry registration, OFD credential change) priced on request — [rhmt.uz tariffs](https://rhmt.uz/pos/tariffs/), [rhmt.uz](https://rhmt.uz)
- Poster + Multikassa: USD 28 one-time, USD 8/month Multikassa fee, USD 5/month Poster integration fee — [Poster](https://joinposter.com/en/applications/multikassa)
- Rahmat partner programme: dealers (equipment income, acquiring retro-bonuses), agents (fixed lead payment), technology partners (API/SDK, marketplace); rates and thresholds not published — [rhmt.uz/partners](https://rhmt.uz/partners/)
- E-POS pricing and partner terms: not published; contact form / +998 71 200-01-73 — [epos.uz partners](https://epos.uz/en/partners)
- F-Kassa price: not listed ("paid") — [MoySklad](https://www.moysklad.ru/integratsii/apps/f-kassa-ferrosoft/)

### Inferences
- The 123,600 vs 200,000 UZS discrepancy affects the background report's "~100-125k/month" estimate; the higher figure may reflect a newer tariff. Confirm with Rahmat.

### Gaps
- Revenue share, white label, per-receipt fees: not found for any provider.

## 6. Onboarding

### Takeaway
E-POS publishes a one-day engineer-led flow; for others only the OFD/registry chain is known.

### Cited Findings
- E-POS: engineer contacts within an hour, installs/activates, registers the merchant with the fiscal system by tax ID, 30-40 minute training, usually done in one day; CRM/ERP integration on day two — [epos.uz blog](https://epos.uz/en/blog/zapustit-kassu-za-den). Given the exclusion above, treat as historical until status confirmed.
- Rahmat: one-time services include tax registry registration, fiscal module (POFM) activation and OFD credential change (price on request) — [rhmt.uz tariffs](https://rhmt.uz/pos/tariffs/). Support 1865, t.me/rhmt_support_bot — [rhmt.uz](https://rhmt.uz)
- Marketplaces and payment systems must integrate with the OFD and enter the marketplace registry — [buxgalter.uz](https://buxgalter.uz/publish/doc/text183408_kak_proyti_integraciyu_s_ofd_i_popast_v_reestr_marketpleysov)

### Gaps
- What the POS vendor itself must do (certification, test cases): not published by any provider.

## 7. Known integrations

### Cited Findings
- Multikassa: Poster (since app page), MoySklad ([spot.uz 2024](https://www.spot.uz/ru/2024/04/23/multikassa-moysklad/), title only seen), Rahmat lists iiko, R-Keeper, 1C, MoySklad, Poster, JOWY — [rhmt.uz/partners](https://rhmt.uz/partners/)
- E-POS: Billz (case study), MoySklad via F-Kassa — [epos.uz partners](https://epos.uz/en/partners), [MoySklad](https://www.moysklad.ru/integratsii/apps/f-kassa-ferrosoft/)
- UzKassa: Paloma365 — [Paloma](https://dashboard.palomapos.com/docs/en/pos-v2/fiscalization-uz)
- Billz fiscal: no page found; Billz was acquired in majority by TBC Bank Group (media) — [Paypers](https://thepaypers.com/mergers-aquisitions-and-investments/news/tbc-bank-group-acquires-majority-stake-in-billz)

### Inferences
- E-POS clients (Billz, F-Kassa users) face migration risk if the exclusion is confirmed; this could be a sales opening for a vendor with a working alternative.

### Gaps
- Poster's other Uzbek fiscal partners, iDokon, Alipos, Mison, Jowi native fiscal: not found.

## Recommended next steps (for the owner)
1. Check E-POS status directly at soliq.uz virtual-kassa registry and with E-POS today.
2. Apply at open.rhmt.uz as technology partner; ask in writing for API/SDK, sandbox, advance/credit/mixed/MXIK, offline queue, resale terms, and which monthly price (123,600 or 200,000 UZS) applies.
3. Ask UzKassa operator and REGOS support whether a cloud API with those receipt types exists.
