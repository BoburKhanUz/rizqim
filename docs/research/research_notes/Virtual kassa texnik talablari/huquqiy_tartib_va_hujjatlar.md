# Legal basis, procedure and dossier for an own virtual kassa (evidence, 22.11.2026)

Main source: VM 943 (23.11.2019) with annexes, current text on [lex.uz/docs/-4603329](https://lex.uz/docs/-4603329), read on 22.11.2026 through a summarising fetch tool in three chunks (the page is 280 972 characters). Quotes are short fragments the tool returned; a lawyer should check them against the page. Earlier findings: `../Fiskal provayder raqib bo'lmaganlar/huquqiy_yangi_topilmalar.md`, `../Fiskal provayderlar API integratsiyasi/huquqiy_asos_va_oz_kassa.md`.

## 1. Technical requirements for a virtual kassa (2-ilova, 3-bob, 6–8-bandlar) [PRIMARY]

6-band, list as returned:
- software version number and serial or licence number;
- use limited without a fiscal module;
- automatic blocking on unauthorised use or data change by external programs;
- automatic blocking when the FM fails or its memory is full;
- automatic blocking after 48 hours offline;
- barcode reader support;
- real-time loading of product codes from the national electronic catalogue (MXIK) into device memory;
- acceptance of all bank cards and QR payments directly on the device;
- ISO/IEC 15415 marking code reading, marking code shown, receipt limited if code missing; no duplicate marking codes;
- prescription drugs only with an e-prescription id on the receipt;
- correct product name, unit and quantity;
- software password of at least 6 characters or stronger protection;
- compliance certificates from certification bodies;
- copyright document (source named if obtained abroad);
- user manual in the state language and other languages;
- clear receipt characters;
- time zone UTC+05:00 automatically, or time from the operator's server;
- mandatory connection to the banks' unified QR system;
- **fiscal data go only to the operator, directly through the fiscal module**;
- **one fiscal module used by only one virtual kassa at a time**;
- receipt data mainly in the state language.
- Any change of the virtual kassa or of how it sends data requires a new conclusion from the operator and the Cybersecurity Center.

7-band: applies to all kinds of virtual kassa. 8-band: a virtual kassa may not be used if its version is not in the register, a receipt lacks a mandatory requisite, there is no TXKM service contract, or the business's details changed.

## 2. Inclusion in the State register (4-ilova, Nizom) [PRIMARY]

- 11-band (application on the 2-ilova form, by a legal entity). Attachments, with what applies to a virtual kassa:
  1. reserve of 200 BHM or guarantee — "virtual kassa bundan mustasno" (not for virtual kassa);
  2. operator's conclusion "onlayn-NKM yoki virtual kassa dasturiy mahsulotining texnik va funksional tavsiflariga muvofiqligi";
  3. sample receipts;
  4. reference (etalon) sample of the software;
  5. technical passport with technical and functional description and warranty obligations;
  6. user documentation: user guide, installation guide, tax inspector instructions;
  7. spare parts guarantee letter;
  8. copyright certificate (patent), source if from abroad;
  9. "virtual kassa yoki onlayn-NKM dasturiy mahsulotiga “Kiberxavfsizlik markazi” DUK tomonidan berilgan ekspertiza xulosasi" — "ariza kelib tushgan kundan boshlab 15 ish kunidan kechiktirmasdan";
  10. "Onlayn-NKMga sertifikatlashtirish organi tomonidan berilgan muvofiqlik sertifikati" — wording names only online-NKM.
- 12-band: operator's conclusion in 10 working days ("xulosani oʻn ish kunida ariza beruvchiga taqdim etadi"): properly integrated or not.
- 12-1-band: technical regulation agency and "O'zeltexsanoat" association answer in 5 working days.
- 13–15: the Tax Committee checks documents; refusal if incomplete or discrepancies confirmed.
- 16-band: decision or reasoned refusal "yigirma ish kunida".
- 19-band 6-xatboshi, 20-1, 20-2: if used out of line with the technical requirements, a 10-day warning to the manufacturer, then exclusion if not fixed. E-POS was excluded this way on 30.10.2026 (buxgalter.uz, previous round) [MEDIA].
- Contradiction to clarify: 2-ilova 6-band lists "compliance certificates from certification bodies" for virtual kassa, while 4-ilova 11-band asks a certificate only for online-NKM [inference: ask the Tax Committee].
- Fees: no state fee for inclusion found [NOT FOUND for fees of the operator conclusion, the Cybersecurity Center expertise and any certificate].

## 3. TXKM (3-ilova) [PRIMARY]

- Reserve by number of devices served: up to 1 000 → 100 BHM; 1 000–5 000 → 200 BHM; 5 000–10 000 → 500 BHM; more → 1 000 BHM (the tool returned two slightly different scales from two chunks; the earlier round recorded 100/200/500/1000). Bank or insurance guarantee allowed, can be formed within 6 months; top-up within 10 working days.
- Call centre with phone numbers on its website; 5 % ready spare devices; specialists with a manufacturer's certificate; annual inspection of devices, results in the system within 3 working days; no fee for TXKM registration.
- 25-band (exclusion): 9-xatboshi "Davlat reyestriga kiritilgan onlayn-NKTlarga boshqa turdagi (ikkilamchi)… integratsiya qilinganligi aniqlanganda"; 10-xatboshi "QR-kodsiz chek chop etish imkoniyati yaratilganligi aniqlanganda". 26-band: exclusion by the authorised body's decision.
- 1-ilova 9-band: service under a TXKM contract; non-payment may not stop the service.
- 1-ilova 16–23-bandlar: registering a restaurant's kassa at the tax authority goes through the TXKM, 4 working hours TXKM + 8 working hours tax officer, free.
- 1-ilova 48-band: operator gives the conclusion on software conformity and registers TXKM specialists who hold a qualified specialist certificate; 52-band: operator keeps the register of FMs, TXKMs, specialists, online-NKM and virtual kassas.
- FM operator fee: 1 BHM per year (previous round; buxgalter.uz 05.10.2026) [PRIMARY+MEDIA].
- POS lock (technical, see the FM notes): the secret key is generated "каждое ЦТО", i.e. the FM is bound to the kassa software of a TXKM. Who holds the key when the developer and the TXKM differ is not stated [NOT FOUND].

## 4. Rules from 1.04.2026 that the kassa must enforce (PF-246, 10.12.2025)

From [buxgalter.uz, "2026 yil 1 apreldan ..."](https://buxgalter.uz/oz/publish/doc/text214454_2026_yil_1_apreldan_boshlab_yangi_talablar_buyicha_onlayn-nkt_bilan_qanday_ishlash_kerak) [MEDIA]:
- "1 apreldan boshlab kassa apparatlari toʻrtta funksiyani toʻlaqonli qoʻllab-quvvatlashi shart": block cash for MXIKs on the state cash-ban list; block prescription drugs without the prescription QR; block expired goods; accept social cards with card type, number and transaction number (these map to `ExtraInfo.CardType`, `CardNumber`, `PPTID`, `Item.RecipeID`).
- Cash ban: alcohol including beer, tobacco, petroleum products, state fees, a single good or service above 25 mln so'm per MXIK. One banned item → the whole receipt cashless. The Tax Committee published the MXIK list on 3.04.2026 and changed it on 15.04.2026; it must be monitored.
- Devices checked by "IAM" received conclusions through their TXKM; the Tax Committee planned to finish checking all online-NKT and virtual kassas by 10.04.2026 and update the registers. "IAM" is not expanded in the source [NOT FOUND].

## 5. Cybersecurity Center ("Kiberxavfsizlik markazi" DUK, csec.uz)

- csec.uz pages could not be opened (404 through the fetch tool for every document link). From search snippets only [MEDIA]:
  - technical assignments (texnik topshiriq) for IT products and software must be agreed with the Center (2018 presidential decree), and the Center carries out their expertise;
  - applications in writing or through the online form on csec.uz; the service is paid, price set in a contract after the volume of work is assessed;
  - required material includes a description of the software and an installation guide;
  - contacts: info@csec.uz, (71) 203-12-24, software certification itsoft@csec.uz, Taras Shevchenko ko'chasi 20, Toshkent;
  - the Center has five national cybersecurity standards, among them O'zMSt 841:2026 for application software;
  - cryptographic products are certified by the State Security Service, not the Center.
- VM 943 fixes only the 15 working days for the Center's conclusion. Price and exact checklist [NOT FOUND].

## 6. Register status (who is registered now)

- soliq.uz/page/virtual-kassa-reestri, soliq.uz/page/txkm-reestri and ofd.uz/ru/pages/view/55 and /48 returned 503 (fetch tool) or connection reset (curl) on 22.11.2026, as on 21.11 [NOT FOUND].
- A search snippet of the virtual kassa register page showed old entries from 2020, e.g. "SOFT TECH SOLUTIONS MChJ, Virtual kassa POS2K V5.0, 30.04.2020" [MEDIA: snippet only].
- Changes known: Uzkass-185 (online-KKM) and Bestpos (TXKM) excluded 28.04.2026 ([gov.uz](https://gov.uz/ru/soliq/news/view/159141)) [PRIMARY]; E-POS excluded 30.10.2026 [MEDIA].
- Third-party POS with its own Uzbek virtual kassa on FiscalDriveService: Artix ([docs.artix.su](https://docs.artix.su/pages/viewpage.action?pageId=93946891)) [PRIMARY].
- A non-competing TXKM by name: [NOT FOUND].

## 7. Other 2026 facts that touch the receipt

- VAT: mandatory VAT threshold raised to 12 000 BHM (~5 bn so'm) from 1.06.2026; a 6 % simplified VAT rate by tax authority decision (applied through my.soliq.uz) ([Yandex Eda partner help](https://yandex.com/support/eda-vendor-uz/ru/vat)) [MEDIA]. The FM takes `VATPercent` as a whole number (uint8 / 1-byte BCD), so 12, 6, 0 fit; a fractional rate would not [inference].
- FM operator fee and e-signature: 1 BHM/year per FM; ERI 10 % BHM legal entity, 2 years; payee "Yangi texnologiyalar" ([buxgalter.uz](https://buxgalter.uz/publish/doc/text173416_oplata_za_ecp_i_fiskalnyy_modul_rekvizity)) [MEDIA].
