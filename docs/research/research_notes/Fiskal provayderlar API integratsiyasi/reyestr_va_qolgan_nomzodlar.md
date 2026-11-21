# Virtual kassa reyestri (2026-noyabr) va oldingi raund qamrab olmagan nomzodlar

Research date: 2026-11-19. Tool budget ~15 calls; the official registry could NOT be read (soliq.uz and ofd.uz return 503, web.archive.org is blocked for the fetch tool). The registry table below is therefore PARTIAL and built from search-engine snapshots and media; every row is marked with its evidence level.

## 1. Registered virtual kassas (state register) - table first

### Takeaway
No complete late-2026 list of virtual kassas could be obtained. Only a handful of names are confirmed from (possibly outdated) snapshots of soliq.uz; the full current list must be read by a person from soliq.uz/page/virtual-kassa-reestri or ofd.uz when they respond.

### Cited Findings
| Name | Developer | Evidence level | Source |
|---|---|---|---|
| E-POS Cashdesk, E-POS Mobile | E-POS Systems | Registry snapshot (dated) - and now REMOVED 30.10.2026 (media) | [soliq.uz registry (search snapshot)](https://soliq.uz/page/virtual-kassa-reestri); [buxgalter.uz](https://buxgalter.uz/publish/doc/text218134_v_kakoy_srok_nujno_zamenit_kassu_esli_ee_isklyuchili_iz_gosreestra) |
| Smart one v1.0.84 (reg. 16.11.2021) | "Smart one club" MChJ | Registry snapshot (dated) | [soliq.uz registry (search snapshot)](https://soliq.uz/page/virtual-kassa-reestri) |
| Micros24 virtual kassa (2021) | Micros24 MChJ (the Multikassa company) | Registry snapshot (dated) | same |
| Multikassa | Micros24 MChJ | Third-party page says it is in the state register of virtual kassas (ofd.uz) | [Poster marketplace](https://joinposter.com/en/applications/multikassa) |
| Vipos, Xpos, I-cash (v0.2), Mobil kassa, I-cash (v4.08e) | developers not named | Media, 30.06.2021 (old; says list then had 16 products; current status unknown) | [norma.uz](https://www.norma.uz/uz/nashi_obzori/obnovlen_reestr_kontrolno-kassovoy_onlayn-tehniki) |
| Tax Committee's own virtual kassa software for e-commerce | State Tax Committee | Media 2021; product name not given | same norma.uz article |

- Several names often assumed to be virtual kassas are listed as ONLINE-NKM (hardware-type) models, not virtual: UzKassa (e.g. "UZKASSA N5", reg. 13.01.2020), Fiskalbox (reg. 27.07.2020), "Multikassa.Onlayn KKM" (Micros24, reg. 05.05.2023) - [soliq.uz registry snapshots via search](https://soliq.uz/page/onlayn-nkm). Snapshot-level evidence only.
- Registry change notice 28.04.2026: only removal of online-NKM "Uzkass-185"; no virtual kassa changes stated - [gov.uz/soliq](https://gov.uz/ru/soliq/news/view/159141).
- Tax Committee points to ofd.uz for registries of online-NKM, virtual kassas and service centres (CTO) - [buxgalter.uz](https://buxgalter.uz/publish/doc/text218134_v_kakoy_srok_nujno_zamenit_kassu_esli_ee_isklyuchili_iz_gosreestra).

### Inferences
- Names in the 2021 list (Vipos, Xpos, I-cash, Mobil kassa) may no longer be registered; do not treat as available without checking.

### Gaps
- Full registry (names + developers) as of Nov 2026: not found. Routes tried: soliq.uz (503), ofd.uz/ru/pages/view/55 (503), web.archive.org (blocked). Not tried for lack of budget: data.egov.uz, gazeta.uz, kun.uz Sept-Nov 2026 reprints, Google cache.
- Whether PosPoint, F-Kassa, iDokon, BILLZ, Alipos, Mison, Paynet, Atto, Arca, Newmax are in the registry: not found.

## 2. Per-candidate notes

### Takeaway
Only Multikassa, F-Kassa (via its own cash register for MoySklad), UzKassa (Paloma365 integration) and FiscalBox have any public trace; no candidate showed a public API spec plus test stand for third-party POS in what I could read.

### Cited Findings
- **Multikassa / Rahmat POS (Micros24)** - already covered in earlier rounds; extra facts: Poster integration price $28 connection, $8/month service, $5/month integration (vendor page); no hardware needed, runs on existing devices - [Poster](https://joinposter.com/en/applications/multikassa). Integrations with MoySklad described in an advertorial - [spot.uz](https://www.spot.uz/ru/2024/04/23/multikassa-moysklad/). Tel +998 78 113-77-08.
- **FiscalBox (dev.fbox.uz)** - public developer page exists with endpoints: billing login, order create/print/refuse, payment flows for Click, Payme, Uzum and Anor, Z-report open/close/print/info, cash drawer. States "use sandbox/test devices" but no sandbox URL given; the page does not name the company or registry status - [dev.fbox.uz](https://dev.fbox.uz/pages/development). Fiskalbox appears in the online-NKM (not virtual) registry per snapshot. Worth checking as the only candidate with a public integration page.
- **UzKassa** - Paloma POS 2 connects with Cloud URL + cashier phone + password + register serial; payment types mapped to Cash/Card/Not fiscalized - [Paloma docs](https://dashboard.palomapos.com/docs/en/pos-v2/fiscalization-uz). This proves a third-party integration exists but not that docs are public. Not in the virtual list per snapshot (online-NKM family).
- **F-Kassa (docs.f-kassa.uz)** - MoySklad cash-register app with fiscalization as a feature; fetched page documents no fiscal API, registry status, or sandbox. Support tel +998 78 113-78-90 - [docs.f-kassa.uz](https://docs.f-kassa.uz); [MoySklad listing](https://www.moysklad.ru/integratsii/apps/f-kassa-ferrosoft/) (search result says fiscal receipts go to OFD via an EPOS module - not verified).
- **Paloma365** lists UzKassa, EPosKassa, QPosKassa as Uzbek fiscal options per search summary - [Paloma docs](https://dashboard.paloma365.com/docs/en/pos-classic/fiscal-registers) (not opened by me).
- **E-POS (epos.uz)** advertised a REST API module and CRM/ERP connectors - [epos.uz](https://epos.uz/en) - but is now de-registered (see section 4).

### Inferences
- FiscalBox and UzKassa are the most realistic technical leads; Multikassa is the only one with a confirmed virtual-kassa registration, but its API is closed (earlier round).

### Gaps
- Not found publicly at all (no page opened or no result): PosPoint, Smart Kassa/Smart one club site, iDokon, BILLZ, Alipos fiscal, Mison, Paynet kassa, Atto, Arca (only a "Cash register without worries" campaign by ARCA GROUP surfaced: [uzcard](https://api.uzcard.uz/en/?p=23242), content unread), Newmax, Micros24 beyond Multikassa.
- Official free Soliq virtual kassa app and third-party sending: not found.
- Bank apps (Kapitalbank, Uzum Business, TBC, Hamkorbank, Ipak Yo'li): not found.
- Prices, test stands for all candidates except Poster/Multikassa: not found.

## 3. Payment systems as fiscalizers (Click, Payme, Uzum)

### Takeaway
No evidence found that Click, Payme or Uzum offer a registered virtual kassa or a fiscalization API for third-party in-venue cash/card sales. Their fiscal features found concern their own payment flows.

### Cited Findings
- Click virtual POS (2017 article) is a card-acceptance service via partner banks; the article says nothing about fiscal receipts - [uzdaily.uz, 03.03.2017](https://www.uzdaily.uz/en/click-offers-virtual-pos-terminals-for-business-entities).
- Payme Merchant API requires a "detail" object in CheckPerformTransaction for tax compliance - payment gateway integration, not a fiscal API (search summary of a third-party guide) - [skillsmp Payme guide](https://skillsmp.com/creators/ubranch/my-marketplace/plugins-payme-integration-skills-payme-integration) (secondary, unverified).
- Uzum "Remit Core" fiscalizes sales/refund receipts through an OFD (search summary only) - [apievangelist](https://providers.apievangelist.com/providers/uzum/) (secondary, unverified, likely a different product context).
- FiscalBox's page lists Click/Payme/Uzum/Anor as payment flows on its devices - [dev.fbox.uz](https://dev.fbox.uz/pages/development).
- Click is integrated into a P10 terminal that combines online cash register and POS - [spot.uz 2025-06-19](https://www.spot.uz/oz/2025/06/19/smart-business/) (advertorial, details unread).

### Inferences
- Payment systems appear as payment methods inside fiscal products, not as fiscalizers for other POS.

### Gaps
- Click Evolution/Click Kassa, Payme Business/Go, Uzum Business official docs: not found.

## 4. E-POS after 30.10.2026

### Takeaway
E-POS virtual kassa was removed from the register on 30.10.2026; no reinstatement or successor product found.

### Cited Findings
- E-POS virtual kassa removed 30.10.2026 for technical non-conformity after several warnings to E-POS SYSTEMS MChJ; the E-POS service centre (CTO) was also removed, announced 07.11.2026 - [buxgalter.uz](https://buxgalter.uz/publish/doc/text218134_v_kakoy_srok_nujno_zamenit_kassu_esli_ee_isklyuchili_iz_gosreestra).
- Replacement period 15 days, i.e. by 14.11.2026; fine 20 mln so'm under Tax Code art. 221 part 4 (same source).
- Developer told clients it hoped to clarify the situation by 10 November; no outcome in the source (same).
- epos.uz shows only a "site in alpha" banner, no notice - [epos.uz/en/about](https://epos.uz/en/about).

### Inferences
- The 14.11 deadline has passed as of 19.11.2026, so ex-E-POS venues are under urgent migration pressure, which is a sales window for any provider.

### Gaps
- Reinstatement, successor product, competitor migration offers: none found in two searches.

## 5. Partner/integrator programmes with test environments

### Takeaway
Only FiscalBox (developer page, sandbox mentioned without URL) and Poster's marketplace route for Multikassa were found; nothing formal.

### Cited Findings
- [dev.fbox.uz](https://dev.fbox.uz/pages/development) - integration guide mentioning sandbox/test devices.
- Multikassa partner application at open.rhmt.uz (earlier round, not re-verified).

### Gaps
- No other programme found.

## Suggested next steps
1. A human opens soliq.uz/page/virtual-kassa-reestri (or ofd.uz) when up and exports the table.
2. Contact FiscalBox, UzKassa and F-Kassa for API access/sandbox terms.
3. Check data.egov.uz, kun.uz and gazeta.uz Sept-Nov 2026 for registry reprints.
