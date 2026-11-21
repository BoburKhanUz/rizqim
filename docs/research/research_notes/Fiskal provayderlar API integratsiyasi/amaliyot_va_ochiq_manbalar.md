# Uzbekistan fiscalization in practice: POS products, payment-system fiscal data, open developer material (as of 2026-11-19)

Scope note: this research ran ~20 tool calls and is partial. Items marked NOT FOUND were searched but not confirmed. Source dates are given where visible. Labels: [PRIMARY] = vendor/official docs read directly; [SECOND-HAND] = third-party, press, or search-snippet only.

## 1. How POS products in Uzbekistan issue fiscal receipts

### Takeaway
Confirmed practice is the plugin/partner model: POS products connect to a third-party virtual cash register (Multikassa, UzKassa, E-POS, Rahmat) running on the existing Android/Windows device, with no dedicated fiscal hardware. Per-product pages were found only for Poster and Paloma365; for iiko, r_keeper, Jowi, Alipos, Mison, Smartup, Quick Resto nothing new was confirmed in this pass.

### Cited Findings
- Poster has a catalogue app "Multikassa". Multikassa is described as a virtual cash register listed in the state register of virtual cash registers (ofd.uz). It fiscalizes sales and returns, opens/closes shifts (Z-report), and lets the cashier pick IKPU codes inside Poster. [SECOND-HAND: via search summary of the page; page itself not opened] — [joinposter.com/en/applications/multikassa](https://joinposter.com/en/applications/multikassa)
- Same page, per the search summary: tariff 28 USD connection, 8 USD/month Multikassa service fee, 5 USD/month Poster integration fee; installs on an existing monoblock/PC/tablet, all devices except iOS; support phone +998 78 113 77 08. Page cites PKM 943 with date 23.11.2019 whereas the Rahmat manual says 23.11.2020 (discrepancy, check lex.uz). Prices may be stale, verify. — [joinposter.com](https://joinposter.com/en/applications/multikassa)
- Paloma365 POS 2 fiscalizes through the cloud register UzKassa. Setup: Settings > Hardware > Fiscal Devices > Add > UzKassa; enter Cloud URL, cashier phone, password, register serial; map each payment type to Cash/Card/"Don't" (unmapped types are not fiscalized). No separate hardware; cost not stated. [PRIMARY] — [Paloma docs](https://dashboard.palomapos.com/docs/en/pos-v2/fiscalization-uz)
- Paloma offline/failure behaviour (useful pattern to copy): receipt saved and sent when connection returns; failed receipts marked "Not fiscalized" with per-order and bulk resend ("Fiscalize checks"); a shift cannot be closed while unfiscalized receipts exist; if no default fiscal device, payment proceeds unfiscalized with a warning banner. [PRIMARY] — [Paloma docs](https://dashboard.palomapos.com/docs/en/pos-v2/fiscalization-uz)
- Paloma classic also has a "fiscal registers" page (not read). — [Paloma classic](https://dashboard.paloma365.com/docs/en/pos-classic/fiscal-registers)
- Rahmat "Virtual Kassa" is standalone Windows cash-register software for retail and catering, based on a fiscal module, manual dated 2026, publisher RAHMAT TECH. Not a Poster plugin. [SECOND-HAND: search summary] — [Rahmat manual PDF](https://rhmt.uz/upload/Руководство_пользователя_Rahmat_Виртуальная_касса.pdf)
- F-Kassa (MoySklad integration) sends receipts to the Uzbek OFD via an EPOS module; docs at docs.f-kassa.uz (not opened). [SECOND-HAND] — [moysklad.ru](https://www.moysklad.ru/integratsii/apps/f-kassa-ferrosoft/)
- A fiscal module (physical/electronic key) is paid by legal entities at 10% BRV, individuals 7% BRV, via bank, my.soliq.uz or payment apps; payer INN/PINFL must be given. [SECOND-HAND, 2026 article] — [buxgalter.uz](https://buxgalter.uz/publish/doc/text173416_oplata_za_ecp_i_fiskalnyy_modul_rekvizity)
- BILLZ: retail SaaS, ~4,000 clients, TBC Bank majority stake (2025). No fiscal mechanism found in search. [SECOND-HAND] — [thepaypers](https://thepaypers.com/mergers-aquisitions-and-investments/news/tbc-bank-group-acquires-majority-stake-in-billz)

### Inferences
- The market norm is "POS + partner virtual cash register on the same device, per-payment-type fiscal mapping, resend queue, shift blocked on unfiscalized receipts". RIZQIM can copy the Paloma pattern as the reference UX.
- Fiscalization cost to the venue is split into register fee + POS integration fee (Poster example about 13 USD/month plus 28 USD one-off).

### Gaps
- iiko, r_keeper (BUSOFT), Jowi, Alipos, Mison, Smartup, Quick Resto: no fiscal help page, plugin name, or tariff retrieved. A search for iiko + Soliq plugin returned nothing relevant. Needs direct fetch of each vendor's help centre or a call to dealers.
- Poster page not opened directly; figures come from search snippet.
- Existing RIZQIM report mentions "Rahmat, E-POS" plugins for iiko (docs/research/reports/POS va agregator integratsiyalari.md); not re-verified here.

## 2. Payment-system fiscalization (Payme, Click, Uzum, Paynet)

### Takeaway
Payme and Click do the fiscalization themselves when the merchant sends item details (IKPU, package code, VAT) and the payment system acts as commission agent with the merchant's INN as principal. Payme's fiscal return channel is the optional SetFiscalData callback. Click's endpoints are known only from a search snippet and a third-party wrapper. Uzum and Paynet were not documented in this pass.

### Cited Findings
- Payme Subscribe API receipts.create `detail` object: `receipt_type` (Number, required; sale/return = 0 per page), `shipping` {`title`, `price` in tiyin}, `items[]` with `title`, `price` (tiyin), `count`, `code` (IKPU), `units` (optional), `package_code` (required), `vat_percent` (required), `discount` (optional, tiyin, includes quantity). All amounts in tiyin. The page is internally inconsistent on whether `items` is required, and does not say who issues the receipt; `commission_info` is not on the page. [PRIMARY] — [developer.help.paycom.uz receipts.create](https://developer.help.paycom.uz/metody-subscribe-api/receipts.create)
- Payme Merchant API method SetFiscalData (optional to implement): Payme sends fiscal data to the merchant's billing after the receipt reaches a success status. Params: `id` (Payme receipt id), `type` "PERFORM" or "CANCEL", `fiscal_data` {`receipt_id` (serial number for the Virtual Fiscal Module), `status_code`, `message` (error if OFD registration failed), `terminal_id` (virtual fiscal module number, e.g. "EP000000000025"), `fiscal_sign`, `qr_code_url`, `date` (e.g. "20220706221021")}. Response `{"result":{"success":true}}`; errors -32001 (receipt not found), -32700, -32602. The tax authority creates two separate fiscal receipts (payment and cancellation); store CANCEL data separately. [PRIMARY] — [developer.help.paycom.uz SetFiscalData](https://developer.help.paycom.uz/metody-merchant-api/setfiscaldata)
- Note: the task brief names `receipts.set_fiscal_data`; the primary page found is Merchant API `SetFiscalData` (a callback Payme calls on the merchant), not a Subscribe API method. A search for `receipts.set_fiscal_data` found no such method. [PRIMARY + search] — [search results]
- The "EP..." terminal-id format in the Payme example suggests the E-POS virtual fiscal module, but the page does not name the operator. Inference only.
- Click Merchant API: fiscalization page exists at `docs.click.uz/merchant-api/fiscalization` (Russian title "Фискализация данных"); page body could not be fetched (JS-rendered). From search snippet of docs.click.uz/en/?p=554: POST to v2 merchant `ofd_data/submit_items` with `service_id` (int), `payment_id` (long), `items` (list of goods/services); sibling `ofd_data/submit_qrcode` registers an already-fiscalized receipt using the ofd.soliq.uz QR URL; `Auth` header in format ID:hash:timestamp. Item sub-fields not retrieved. [SECOND-HAND: snippet only] — [docs.click.uz](https://docs.click.uz/en/?p=554)
- fbox.uz (third-party wrapper) documents attaching a Soliq receipt to a Click payment: POST `/payment/click_confirm` with `payment_id`, `qr_code` (ofd.soliq.uz/check URL); has analogous Payme and Uzum fiscalization pages. Unclear status codes (spec lists 400 while text says errors are 200). [SECOND-HAND, third-party] — [dev.fbox.uz click](https://dev.fbox.uz/pages/api-reference/endpoint/payment/click/fiscalization); [uzum](https://dev.fbox.uz/pages/api-reference/endpoint/payment/uzum/fiscalization); [payme](https://dev.fbox.uz/pages/api-reference/endpoint/payment/payme/fiscalization)
- Fiscal data operator named in Aug 2022 article: NIC "Yangi texnologiyalar" (ofd.uz). Payment systems and marketplaces must integrate with the OFD (application, integration contract, CSR file, virtual fiscal module certificate) then apply to the State Tax Committee for the Marketplace register (receipts samples, APK/URL, user docs, copyright proof); decision within 15 working days. The article does not confirm Click/Payme/Uzum are in the registry. [SECOND-HAND, 2022-08-02, may be outdated] — [buxgalter.uz](https://buxgalter.uz/publish/doc/text183408_kak_proyti_integraciyu_s_ofd_i_popast_v_reestr_marketpleysov)
- Practice problems with payment-system receipts (2023-05-01, generic, does not name Payme/Click): payment missing from tax report after commission contract renewal (check "list of commission agents" service on my.soliq.uz); VAT 12% charged on exempt IKPU because VAT rate not 0; wrong principal INN (only the payment org can fix it); refunds of remote payments must be requested via payment-system support in the same month, and the system issues the return receipt; double VAT or missing cashback when the venue's own online cash register and the payment system are not coordinated. [SECOND-HAND] — [buxgalter.uz](https://buxgalter.uz/ru/publish/doc/text189895_esli_voznikayut_problemy_s_fiskalnymi_chekami_platejnyh_sistem_chast_1)
- E-POS API advertises "payments bundled with fiscalisation for Click, Payme, Uzum, Humo, Uzcard". [SECOND-HAND: vendor marketing] — [epos.uz/en/products/api](https://epos.uz/en/products/api)

### Inferences
- When Payme/Click issue the receipt as commission agent, the POS vendor needs a register only for in-venue and courier-cash sales. This matches the existing RIZQIM reports; legal coverage of delivery orders was not newly confirmed here.
- Implement SetFiscalData on RIZQIM's Payme endpoint: it gives fiscal sign, QR URL and terminal for storing as an "external" receipt, and a CANCEL variant for refunds.
- Always send `vat_percent` (0 where exempt) and valid IKPU + package_code, otherwise the receipt may carry wrong VAT or land on the wrong taxpayer.

### Gaps
- Click fiscalization page text and Item fields: not retrieved (JS page); verify at docs.click.uz/merchant-api/fiscalization.
- Uzum checkout fiscalization and Paynet: no primary doc found.
- Whether Payme/Click are in the Marketplace register in 2026: not confirmed.
- Who the operator is for Click/Payme receipts: only inferred.
- Payme `commission_info` / principal-INN passing is not on the receipts.create page.

## 3. Open technical material

### Takeaway
Open material is thin: one MIT npm client for the OFD, vendor REST docs (E-POS, REGOS), and third-party wrappers (fbox). No GitHub repository, Postman collection, or composer package for Uzbek fiscal modules was confirmed.

### Cited Findings
- npm `@exode-team/ofd-uz` v1.0.4: Node/TypeScript client for the OFD, MIT licence. `OFDClient(baseURL, privateKeyPEM, certPEM)` with `sendReceipt`, `sendQRPayment`; PKCS#7 signing; example base `https://test.ofd.uz`. Fields: ReceiptSeq, IsRefund, Items{Name, SPIC, PackageCode, OwnerType, GoodPrice, Price, VAT, VATPercent, Amount}, ReceivedCash, ReceivedCard, TotalVAT, Time, ReceiptType; QR payment has PaymentId, PaidSum, TIN, TerminalID. README is thin: no endpoint paths, unclear units (tiyin inconsistent), no response schema. Repo and maintenance status unverified. [PRIMARY README] — [unpkg](https://unpkg.com/@exode-team/ofd-uz@1.0.4/README.md)
- E-POS REST API: `POST https://api.epos.uz/v1/receipts`; covers receipts from line items, full/partial refunds, status/fiscal mark/verification link, payments + fiscalization, catalog sync. Bearer token, OAuth2, IP allowlist, request signing. Docs https://docs.epos.uz (integrator login wall), portal https://portal.epos.uz, phone +998 71 200-01-73. No pricing, sandbox, or partner terms on the page. [PRIMARY marketing page] — [epos.uz/en/products/api](https://epos.uz/en/products/api)
- REGOS API has `DocOrderDelivery/SetFiscalInfo` method (Retail), meaning external fiscal data can be written back to a delivery order; page content was not read in full. [SECOND-HAND] — [docs.regos.uz](https://docs.regos.uz/uz/api/retail/docorderdelivery/setfiscalinfo)
- Community Click OpenAPI client lists ofd_data endpoints (repo iota-uz/click). [SECOND-HAND] — [awesome.ecosyste.ms](https://awesome.ecosyste.ms/projects/github.com%2Fiota-uz%2Fclick)
- `texhub/virtual-pos-andoz` (Packagist) targets Tajikistan, not usable for Uzbekistan. — [packagist](https://packagist.org/packages/texhub/virtual-pos-andoz)

### Inferences
- OFD protocol requires a certificate-based signed channel (CSR, PKCS#7); a direct OFD integration is a certification project, while E-POS/Multikassa/UzKassa REST are far cheaper to adopt.

### Gaps
- Multikassa API, UzKassa API, REGOS fiscal docs, Rahmat API: no public developer docs found (Multikassa search returned nothing). No Postman collections, no pub.dev packages, no Telegram-thread mirrors found.
- Not run: GitHub code search for "epos"/"uzkassa"; consider `gh search` next.

## 4. Developer experience reports

### Takeaway
No developer-written post-mortems (habr, Telegram mirrors) were found. Practical pitfalls are known only from accountant-oriented articles and the Paloma docs.

### Cited Findings
- Pitfalls on the payment-system side: wrong VAT on exempt IKPU, wrong principal INN, commission-agent confirmation lapses on contract renewal, refunds must be requested within the same month — see section 2 ([buxgalter.uz, 2023-05-01](https://buxgalter.uz/ru/publish/doc/text189895_esli_voznikayut_problemy_s_fiskalnymi_chekami_platejnyh_sistem_chast_1)).
- Offline queue and shift-close blocking pattern — [Paloma docs](https://dashboard.palomapos.com/docs/en/pos-v2/fiscalization-uz).

### Gaps
- MXIK validation, time sync, receipt numbering, tax-office blocking: no developer sources found.

## 5. Partner/integrator programmes

### Cited Findings
- E-POS: docs behind an "integrators login" and a /partners page exist; terms, form, sandbox not visible publicly. — [epos.uz](https://epos.uz/en/products/api); [docs.epos.uz](https://docs.epos.uz)
- OFD (NIC Yangi texnologiyalar) integration: written application, integration contract, CSR, certificate, then register application (2022 description). — [buxgalter.uz](https://buxgalter.uz/publish/doc/text183408_kak_proyti_integraciyu_s_ofd_i_popast_v_reestr_marketpleysov)
- Poster catalogue shows Multikassa as an integrator app; the integration fee (5 USD/month) shows it as a commercial partnership. — [joinposter.com](https://joinposter.com/en/applications/multikassa)

### Gaps
- Application forms, requirements and test stands for Multikassa, UzKassa, Rahmat, REGOS not found publicly. Recommend direct written requests.
