# Fiskal provayder - raqib bo'lmaganlarni qidirish

**Xulosa.** 2026-yil 21-noyabr holatiga ochiq manbalarda RIZQIM uchun "faqat fiskal infratuzilma sotadigan, o'zi POS sotmaydigan, API'si ochiq, reyestrda turgan" provayder topilmadi. Boshqa POS kompaniyalari foydalanayotgan hamkorlarning deyarli hammasi o'zi ham kassa yoki POS dasturi sotadi: Rahmat (Multikassa endi Rahmat'ga yo'naltiriladi), Smartpos (o'zini "avvalgi UZKASSA" deydi, restoranlar uchun Smart Cafe sotadi), YesPOS (Paloma'dagi QPos), MobilKassa, Hippo, KASSO. E-POS esa reyestrdan chiqarilgan, buni endi Soliq qo'mitasining rasmiy kanali ham tasdiqlaydi. Eng muhim yangi topilma qonundan chiqdi. 22.10.2026 dan kuchga kirgan qoida bo'yicha, ro'yxatdagi kassaga QR-kodli chek chiqarish uchun boshqa (ikkilamchi) hisob yoki buyurtma dasturi ulangani aniqlansa, shu kassaga xizmat ko'rsatadigan markaz (TXKM) reyestrdan chiqariladi. Bu qoida "RIZQIM buyurtmani boshqa kompaniya kassasiga yuboradi, o'sha kassa chekni chiqaradi" degan sxemaning o'zini xavf ostiga qo'yadi. Shuning uchun tavsiya o'zgaradi. **Birinchi yo'l: RIZQIM POS'ni o'zini virtual kassa sifatida ro'yxatdan o'tkazish.** Bunda fiskal ma'lumotlar operatori ("Yangi texnologiyalar" markazi) va raqib bo'lmagan TXKM hamkor kerak bo'ladi. Ikkinchi va uchinchi o'rinda vaqtinchalik ko'prik turadi: FiscalBox (BCT) va REGOS VCR. Ikkalasi ham ishga tushirish uchun yaraydi, lekin yuqoridagi qoida ularga ham tegishli bo'lishi mumkin.

## Belgilar va cheklovlar

Har bir fakt belgi bilan keladi. **[BIRLAMCHI]** degani manbaning o'zini o'qiganman: lex.uz, Soliq qo'mitasining rasmiy Telegram kanali yoki provayderning o'z sahifasi. Provayder sahifasi uning o'zi nima deyishini ko'rsatadi, gapi to'g'riligini kafolatlamaydi. **[MEDIA]** degani faqat gazeta, reklama maqolasi, buxgalteriya sayti yoki qidiruv xulosasi aytgan. **[TOPILMADI]** degani men ko'ra olmadim, "yo'q" degani emas. Hammasi 21.11.2026 da tekshirilgan.

Bugun soliq.uz, ofd.uz, uzkassa.uz va bct.uz har urinishda 503 xato berdi, kapitalbank.uz esa 403 berdi. Demak **reyestrning o'zini hali ham o'qiy olmadim**. Reyestr bo'yicha ma'lumot provayder sahifalaridan, Soliq qo'mitasi Telegram kanalidan va eski nusxalardan olingan. lex.uz dagi qaror matni juda uzun, uni bo'laklab, qisqartiruvchi vosita orqali o'qidim. Muhim iboralarni asl o'zbekcha holida keltirdim, lekin ularni yurist qayta tekshirishi kerak.

## Eng muhim yangilik: qonun "boshqa kassa orqali chek chiqarish"ni xavfli qildi

VM 393 (20.07.2026) VM 943 ga ikki qoida qo'shdi, ikkalasi 22.10.2026 dan kuchda ([lex.uz VM 943](https://lex.uz/ru/docs/-4603329)) [BIRLAMCHI].

**Birinchi qoida, 161-band.** TXKM yoki tadbirkorga soliq tizimlari bilan "integratsiya qilinmagan" hisob yoki buyurtma dasturidan foydalanishga imkon berish taqiqlanadi.

**Ikkinchi qoida, 25-band 9-xatboshi.** Bu TXKM ni reyestrdan chiqarish asoslaridan biri. Asl matnda shunday deyilgan: "Davlat reyestriga kiritilgan onlayn-NKTlarga boshqa turdagi (ikkilamchi) Davlat reyestriga kiritilmagan yoki kiritilgan axborot tizimlari (... buyurtmalar hisobini yuritish) ... QR-kodli chek chop etish maqsadida integratsiya qilinganligi aniqlanganda". Oddiy tilda: ro'yxatdagi kassaga QR-chek chiqarish uchun boshqa dastur ulangan bo'lsa, bu dastur ro'yxatda bo'lsa ham, bo'lmasa ham, kassaga xizmat ko'rsatadigan markaz reyestrdan chiqadi.

Bundan tashqari, 1-ilova 4-bandi umumiy ovqatlanish korxonalariga integratsiya qilinmagan buyurtma dasturidan foydalanishni taqiqlaydi [BIRLAMCHI].

Mening o'qishim (rasmiy tushuntirish topilmadi): bu qoida aynan Poster+Multikassa va Paloma+UzKassa kabi modelga qaratilgan. Bunday modelda restoran POS'i hisobni boshqa kompaniya kassasiga yuboradi va QR-chekni o'sha kassa chiqaradi. Agar qoida shunday qo'llansa, RIZQIM'ni o'z kassasi orqali chek chiqarishga qo'ygan har qanday provayder o'z TXKM ini xavf ostiga qo'yadi. U bilan birga bizning restoranlarimiz ham xavf ostida qoladi. Qonun matnida eng aniq xavfsiz holat bitta: buyurtma dasturining o'zi ro'yxatdagi virtual kassa bo'lishi. 2023-yilda Soliq qo'mitasi ham R-Keeper, JOWi va iiko "virtual kassa sifatida" integratsiya qilinishi kerakligini aytgan edi ([buxgalter.uz, 25.12.2023](https://buxgalter.uz/publish/doc/text195312_kak_programmy_avtomatizacii_nujno_integrirovat_s_informacionnymi_sistemami_nalogovyh_organov_ruz)) [MEDIA]. 22.10.2026 dan keyin Soliq qo'mitasining rasmiy tushuntirishi topilmadi [TOPILMADI]. Shuning uchun bu savol yozma ravishda so'raladi (pastda).

E-POS haqida ham yangi tasdiq bor. Soliq qo'mitasining rasmiy kanalida "E-POS" virtual kassasi va E-POS Systems MChJ xizmat markazi qonun talablariga javob bermagani uchun reyestrdan chiqarilgani e'lon qilingan. 11 000 soliq to'lovchiga ogohlantirish xati yuborilgan, almashtirish muddati 15 kun ([t.me/soliqnews/21589](https://t.me/soliqnews/21589)) [BIRLAMCHI; postda sana ko'rinmaydi, matbuot sanasi 30.10.2026]. Bitta sabab sifatida tasdiqlanmagan bo'lsa ham, E-POS aynan "boshqa dasturlar uchun API" ga qurilgan kassa edi.

## Raqobatchi tekshiruvi: kim nima sotadi

| Nomzod | O'zi POS yoki kassa dasturi sotadimi | Reyestr | API va sinov | Holat |
|---|---|---|---|---|
| Rahmat / Multikassa (AO "Multicard Payment") | **Ha.** Kassa, terminal, restoranlar uchun onlayn menyu va QR-hisob sotadi. multikassa.uz endi rhmt.uz/pos ga yo'naltiriladi ([rhmt.uz/pos](https://rhmt.uz/pos/)) [BIRLAMCHI] | Sotuvchi "reyestrda" deydi | "Ochiq API" deyilgan, hujjat yo'q | **Raqib** |
| Smartpos / Smart Business (CDTI) | **Ha.** Restoranlar uchun Smart Cafe ([smart-ecosystem.uz](https://smart-ecosystem.uz/)) [BIRLAMCHI]; reklama maqolasi uni "avvalgi UZKASSA" deydi ([spot.uz, 16.07.2025](https://www.spot.uz/ru/2025/07/16/smartpos/)) [MEDIA] | O'qilmadi; 90+ ofisli TXKM [MEDIA] | Ochiq API topilmadi | **Raqib** (UzKassa ham shunga qo'shiladi) |
| YesPOS / QPos ("QPOS SYS TECH" MChJ) | **Ha**, do'konlar uchun savdo platformasi ([yespos.uz](https://yespos.uz)) [BIRLAMCHI] | Topilmadi | Faqat Enterprise tarifida "individual API" | Raqib (chakana) |
| MobilKassa (mkassa.uz) | **Ha**, o'z kassa ilovasi: sotuv, qaytarish, bo'lib to'lash, kredit; oflayn 24 soat ([mkassa.uz](https://mkassa.uz)) [BIRLAMCHI] | Saytda yo'q | API yo'q, hamkorlik Telegram orqali | Qisman raqib |
| Hippo POS, KASSO | **Ha**, virtual kassa va POS ([moysklad.uz, 09.07.2026](https://www.moysklad.uz/poleznoe/shkola-torgovli/online-kassa-v-uzbekistane/)) [MEDIA] | Tekshirilmadi | API tilga olinmagan | Raqib |
| ARCA GROUP | Qisman: PAX terminalidagi kassa ilovasi, 80 000+ mijoz ([uzcard.uz](https://api.uzcard.uz/en/?p=23577)) [MEDIA] | PAX A930 2020-yilgi ro'yxatda [MEDIA] | Spetsifikatsiya topilmadi | Qisman raqib |
| SmartOne ("SMART ONE CLUB" MChJ) | Qisman: terminallar va ovqatlanish uchun buyurtma tizimi SmartOne Click ([spot.uz, 12.06.2025](https://www.spot.uz/oz/2025/06/12/smartone/)) [MEDIA] | 2021 nusxada "Smart one" virtual kassasi; 2024-yilda uning "Shtrix-M Kassir 5" xizmati to'xtatilgan ([t.me/soliqnews/14305](https://t.me/soliqnews/14305)) [BIRLAMCHI] | **Ochiq "Cashbox Fiscal API"**: bo'nak, kredit, aralash to'lov ([docs.smartoneclub.com](https://docs.smartoneclub.com/files/cashbox-fiscal-http-serial-api-v1.md)) [BIRLAMCHI]; O'zbekistonga tegishliligi yozilmagan | Zaxira, qisman raqib |
| **FiscalBox (BCT)** | **Ehtimol yo'q.** BCT 2005/2006 dan HoReCa va chakanaga uskuna (monoblok, skaner, tarozi) sotadi; o'z POS dasturi topilmadi. Saytdagi "Foodservice" bo'limi kafelar uchun buyurtma tizimini nomsiz tilga oladi, buni so'rash kerak ([fbox.uz](https://fbox.uz)) [BIRLAMCHI] | 2020 nusxada onlayn-NKM [MEDIA]; hozirgisi o'qilmadi | **Ochiq API** (dev.fbox.uz), "sinov qurilmalari" tilga olingan [BIRLAMCHI] | **Shortlist** |
| **REGOS: VCR** | Qisman: chakana va ulgurji savdo uchun ERP/POS; restoran moduli topilmadi | "2020 dan reyestrda" deydi, raqamsiz [BIRLAMCHI, sotuvchi] | docs.regos.uz; so'rov bo'yicha test kirish ma'lumotlari [BIRLAMCHI] | **Shortlist** |
| E-POS | Yo'q edi (sof infratuzilma) | **Chiqarilgan** [BIRLAMCHI] | Eski | Chiqarib tashlandi |
| "Yangi texnologiyalar" markazi (OFD) | **Yo'q**, davlat operatori | Operatorning o'zi | Ochiq dasturchi materiali GitHub'da (pastda) | **Shortlist, 1-o'rin** |

To'lov kompaniyalari (Payme, Click, Paylov, OCTO, Atmos) raqib emas, lekin ular faqat o'z to'lovlari uchun chek chiqaradi. Payme chekka IKPU, qadoq kodi va QQS ni qabul qiladi va fiskal belgini qaytaradi ([Payme docs](https://developer.help.paycom.uz/metody-merchant-api/setfiscaldata)) [BIRLAMCHI]. OCTO ham shunday ([help.octo.uz](https://help.octo.uz/en/fiscal-data.html)) [BIRLAMCHI]. Paylov hujjatining nusxasida naqd to'lovni ham OFD da ro'yxatdan o'tkazadigan usul bor ([paylov-docs](https://paylov-docs.pages.dev/subscribe/ofd/register)) [MEDIA, rasmiy sayt emas]. Multicard esa Rahmat'ning egasi, demak raqib. Uzum, Kapitalbank, Anor, Paynet uchun boshqa POS'larga zal savdosini fiskallash xizmati topilmadi [TOPILMADI]. Xulosa: to'lov kompaniyalari Telegram marketpleysdagi onlayn to'langan buyurtmalar uchun yaxshi, lekin zaldagi naqd va karta savdosi uchun kassa o'rnini bosmaydi.

## Boshqa POS'lar fiskal uchun kimdan foydalanadi

| POS | Fiskal hamkor | Manba |
|---|---|---|
| Poster | Faqat Multikassa (= Rahmat) | [joinposter.com](https://joinposter.com/en/applications/fiscal-tj) [BIRLAMCHI] |
| Paloma365 | UzKassa, EPosKassa, QPosKassa. UzKassa'da aralash to'lov yo'q, qaytarish terminalning o'zida qilinadi | [Paloma docs](https://dashboard.paloma365.com/docs/en/pos-classic/fiscal-registers) [BIRLAMCHI] |
| AliPos | Regos, E-POS, YesPOS, SmartOne | [alipos.uz](https://alipos.uz/ru/fiscalization) [BIRLAMCHI] |
| MoySklad | E-POS (sahifada 21.11.2026 da ham turibdi), F-Kassa, Rahmat | [moysklad.uz](https://www.moysklad.uz/programmy-dlja-biznesa/pos/) [BIRLAMCHI] |
| iiko, R-Keeper, Jowi | Rahmat ularni o'z integratsiyasi deb ko'rsatadi; R-Keeper dileri provayder nomini aytmaydi | [rhmt.uz/pos](https://rhmt.uz/pos/) [BIRLAMCHI]; [spot.uz, 18.03.2025](https://www.spot.uz/ru/2025/03/18/r-keeper/) [MEDIA] |
| Artix (Rossiya chakana POS'i) | **O'z virtual kassasi**: USB fiskal modul va FiscalDriveService | [docs.artix.su](https://docs.artix.su/pages/viewpage.action?pageId=93946891) [BIRLAMCHI] |
| Mison, Billz | Hamkor nomi topilmadi | [TOPILMADI] |

Bu jadvaldan chiqadigan xulosa: boshqa POS'larning "infratuzilma hamkorlari" aslida ham raqib (Rahmat, Smartpos, YesPOS), ham reyestrdan chiqarilgan (E-POS), ham chakana POS (REGOS). Raqobatchiga bog'lanmagan yagona namuna Artix: u o'z kassasini o'zi ro'yxatdan o'tkazgan va fiskal modulga to'g'ridan-to'g'ri ulanadi.

## Qisqa ro'yxat: 3 ta tavsiya

| O'rin | Kim | Nega | Asosiy xavf |
|---|---|---|---|
| **1** | **"Yangi texnologiyalar" markazi (OFD) va raqib bo'lmagan TXKM bilan o'z virtual kassamiz** | Raqib emas (davlat operatori). 25-band xavfini butunlay yopadi. Chek turlarining hammasi texnik jihatdan bor: bo'nak, kredit, qaytarish, aralash to'lov. Sinov emulyatori ochiq. Bitta provayder tushib ketsa butun bazamiz yo'qolishi xavfi ham yo'qoladi | 2,5 oydan 6 oygacha vaqt, sertifikat va ekspertiza narxi noma'lum, TXKM hamkor topish kerak |
| **2** | **FiscalBox (BCT, fbox.uz)** | O'z POS dasturi topilmadi. Ochiq API'da `prepaid` (bo'nak), `credit` (nasiya), naqd va karta bir chekda, IKPU, qadoq kodi, markirovka bor ([dev.fbox.uz](https://dev.fbox.uz/pages/api-reference/endpoint/order/create/order-create)) [BIRLAMCHI]. BCT HoReCa uskunasini biladi, keyinchalik uskuna yoki TXKM hamkori bo'lishi mumkin | Qurilma kerak: to'plam 4 590 000 so'm bir martalik, 220 000 so'm/oy [BIRLAMCHI]. Hozirgi reyestr holati o'qilmadi. 25-band unga ham tegishli bo'lishi mumkin. API sahifasida kompaniya nomi yo'q |
| **3** | **REGOS: VCR** | Restoran POS'i sotmaydi. AliPos uni fiskal xizmat sifatida ko'rsatadi. Test kirish beriladi. Begona foydalanuvchiga 100 000 so'm/oy, fiskal modul ulash har kassaga 1 BHM ([regos.uz](https://regos.uz/uz/product/regos-vcr/info)) [BIRLAMCHI] | Faqat Windows va jismoniy fiskal modul. Bo'nak, kredit va aralash to'lov tilga olinmagan. REGOS chakanada raqib, restoran mijozlarimiz ma'lumoti uning tizimidan o'tadi. 25-band xavfi bor |

Zaxira: **SmartOne**. Uning API'si texnik jihatdan eng to'liq: sotuv, asl chekka bog'langan qaytarish, bo'nak, kredit, 5 tagacha karta bilan aralash to'lov, qayta yuborishda dublikat chiqmaydi. Lekin SmartOne ovqatlanish uchun buyurtma tizimini ham sotadi, O'zbekistonda reyestr holati noma'lum va 2024-yilda uning bitta kassa xizmati reyestrdan chiqarilgan.

Nega 2- va 3-o'rinlar faqat "ko'prik": ular birinchi restoranlarni hozir qonuniy ishga tushirish uchun kerak. Lekin RIZQIM ularga tayanib qolmasligi kerak, chunki 25-band ularning TXKM iga tegadi. Ular bilan shartnomada "reyestrdan chiqarilsa" bandi va yozma pozitsiya shart (savollar pastda).

## O'z virtual kassamiz: real reja

**Qonun nima talab qiladi** (VM 943, reyestr tartibining 11–22-bandlari ([lex.uz](https://lex.uz/ru/docs/-4603329)) [BIRLAMCHI]). Ariza beruvchi yuridik shaxs, ya'ni RIZQIM kompaniyasining o'zi. Hujjatlar:

- OFD xulosasi (texnik va funksional muvofiqlik), 10 ish kuni;
- Kiberxavfsizlik markazi ekspertizasi, 15 ish kuni;
- texnik jihatdan tartibga solish agentligi va "O'zeltexsanoat" javobi, 5 ish kuni;
- muvofiqlik sertifikati;
- chek namunalari, dasturning namunaviy nusxasi, texnik pasport, foydalanuvchi hujjatlari, ehtiyot qismlar kafolat xati, mualliflik huquqi hujjati.

Qaror 20 ish kuni ichida chiqadi. 200 BHM zaxira talabi virtual kassaga qo'llanmaydi. Bosqichlar ketma-ket o'tsa, faqat kutishning o'zi taxminan 50 ish kuni, ya'ni ~2,5 oy bo'ladi. Ekspertiza, sertifikat va OFD xulosasi narxi topilmadi [TOPILMADI].

**Texnik tomon** ([fiscal-drive-service](https://github.com/qo0p/fiscal-drive-service), [acrsim-android](https://github.com/qo0p/acrsim-android); ikkalasi 2026-yil oktyabrida yangilangan) [BIRLAMCHI, nashr etuvchisi tasdiqlanmagan]. Fiskal modul jismoniy: OFD beradigan USB kalit yoki smart-karta, asosiy yetkazib beruvchisi Feitian ([ftsafe.com](https://www.ftsafe.com/Reference/State_Tax_Committee_of_Uzbekistan/)) [BIRLAMCHI]. Kompyuterda "FiscalDriveService" degan lokal xizmat ishlaydi (Windows, Linux x64/arm). RIZQIM ilovasi chekni unga yuboradi, u modulda imzolaydi va OFD ga jo'natadi. Chek turlari: oddiy, bo'nak, kredit; sotuv yoki qaytarish; to'lov naqd, karta, QR yoki aralash. Internet bo'lmasa cheklar navbatda turadi. Eng eski yuborilmagan chek 2 kundan oshsa, modul yangi chek chiqarmaydi. Bu qonundagi 48 soat qoidasining amaldagi ko'rinishi. Ishlab chiqish uchun modul emulyatori, test OFD server va test modul raqami bor, ya'ni dasturchi haqiqiy modulsiz boshlashi mumkin. Android'da modul SAM-slot orqali ulanadi va drayverni dasturchi yozadi. Arzon planshetlar uchun oddiyroq variant ham bor: restoranda bitta kichik Linux qurilma (yoki Windows kassa) modul bilan turadi, Android kassalar unga lokal tarmoq orqali ulanadi [XULOSA].

**Virtual fiskal modul faqat marketpleyslar uchun.** 2026-yildan ma'lum bo'lgan "virtual fiskal modul" (operator beradigan raqam va elektron imzo sertifikati) qaror matnida marketpleys qoidalarida ta'riflangan. Marketpleys arizasi 15 ish kunida ko'riladi [BIRLAMCHI]. Restoran kassasi uchun jismoniy modul kerak bo'lib qolmoqda. RIZQIM Telegram marketpleysini alohida marketpleys reyestriga kiritish ham mumkin. 10.10.2025 holatiga reyestrda 100 ta platforma bor edi, ular orasida "Yandex Samokatlar" ham bor ([spot.uz](https://www.spot.uz/ru/2025/10/10/marketplace-registry/)) [MEDIA]. Ovqat buyurtma platformasi shu reyestrga mos kelishi esa tasdiqlanmagan.

**Kim xizmat ko'rsatadi (TXKM).** Har bir restorandagi kassani ro'yxatdagi TXKM ro'yxatga oladi va modulni faollashtiradi. Bu jarayon bepul: 4 soat TXKM, 8 soat soliq xodimi [BIRLAMCHI]. Bu yerda ikki yo'l bor:

- **Hamkor TXKM.** Raqib bo'lmagan TXKM ni nomi bilan topa olmadim [TOPILMADI], chunki TXKM reyestri ochilmadi. Shuning uchun birinchi ish reyestrni qo'lda ochib, POS sotmaydigan TXKM larni ajratish.
- **RIZQIM o'zi TXKM bo'ladi.** 1000 tagacha kassa uchun 100 BHM zaxira (BHM 440 000 so'm bo'lsa, ~44 mln so'm) kerak, bank kafolati bilan ham bo'ladi, 6 oyda yig'ish mumkin. Bundan tashqari call-markaz, sertifikatli mutaxassislar va kafolatdagi qurilmalarning kamida 5% i hajmida zaxira kerak [BIRLAMCHI; BHM qiymati fbox.uz dagi "0,5 BHM = 220 000" dan chiqarilgan].

**Taklif qilingan reja va muddat** [XULOSA]:

| Bosqich | Nima | Kim | Taxminiy vaqt |
|---|---|---|---|
| 0 | Ko'prik: 1–2 pilot restoran FiscalBox yoki REGOS VCR bilan, shartnomada "reyestrdan chiqarilsa" bandi bilan | Egasi | 2–4 hafta |
| 1 | OFD ga yozma murojaat: integratsiya shartnomasi, test modul, restoran kassasi uchun talablar | Egasi | 1–3 hafta javob |
| 2 | Emulyator va test server bilan FiscalDriveService integratsiyasi (Windows va Linux qurilma), barcha chek turlari, oflayn navbat, beer/alkogol uchun naqdni bloklash | Dasturchi | 6–8 hafta |
| 3 | OFD xulosasi, Kiberxavfsizlik ekspertizasi, sertifikat, hujjatlar to'plami | Egasi va dasturchi | 10–15 ish kuni har biri, qisman parallel |
| 4 | Soliq qo'mitasiga ariza, qaror | Egasi | 20 ish kuni |
| 5 | Hamkor TXKM bilan shartnoma (yoki o'z TXKM), birinchi restoranlarni ko'prikdan o'z kassamizga ko'chirish | Egasi | 2–4 hafta |

Jami: **taxminan 4–6 oy**. Bu avvalgi hisobotdagi 3–6 oy taxminiga mos keladi, endi qonundagi aniq muddatlar bilan. Restoran uchun xarajat: har kassaga operator haqi yiliga 1 BHM, va REGOS bo'yicha modulni ulash ham 1 BHM bo'lishi mumkin. RIZQIM uchun: ekspertiza va sertifikat narxi noma'lum, hamkor TXKM narxi noma'lum. O'z TXKM imiz bo'lsa ~44 mln so'm zaxira yoki bank kafolati kerak.

## Kimga birinchi yozish kerak

1. **"Yangi texnologiyalar" markazi (OFD)**: info@yt.uz, tel. 71 202-32-32 ([buxgalter.uz](https://buxgalter.uz/ru/publish/doc/text165671_ofd_otvetil_na_5_goryachih_voprosov_po_fiskalnym_modulyam)) [MEDIA, aloqa ma'lumotlari tekshirilmagan]. Bu bitta xat ham qonuniy savolni, ham o'z kassa yo'lini ochadi.
2. **Soliq qo'mitasi**: 25-band 9-xatboshi bo'yicha yozma tushuntirish (my3.soliq.uz murojaati yoki rasmiy xat).
3. **FiscalBox / BCT**: +998 71 205-80-89, sotuv +998 91 162-88-90, info@bct.uz. Pilot uchun ko'prik va TXKM hamkor sifatida.
4. **REGOS**: +998 55 501 00 30, @regos_manager_bot. Ikkinchi ko'prik varianti.
5. Shu bilan birga odam soliq.uz/page/txkm-reestri va virtual-kassa-reestri sahifalarini ochib, POS sotmaydigan TXKM lar ro'yxatini tuzishi kerak.

## Yuborishga tayyor savollar

**A. OFD ("Yangi texnologiyalar") uchun**
1. RIZQIM MChJ restoran POS dasturini o'zi virtual kassa sifatida reyestrga kiritmoqchi. Integratsiya shartnomasi uchun qanday ariza va hujjatlar kerak, muddati qancha?
2. Ishlab chiqish uchun test fiskal modul, test server va texnik spetsifikatsiya (FM 0400, FiscalDriveService) qanday beriladi? GitHub'dagi qo0p/fiscal-drive-service rasmiy hujjatmi?
3. OFD xulosasining narxi va muddati qancha? Muvofiqlik sertifikatini kim beradi, narxi qancha?
4. Virtual fiskal modul (2026) restoran virtual kassasiga beriladimi, yoki faqat marketpleyslargami? Bitta restoranda bir nechta Android kassa bitta jismoniy modul bilan, lokal server orqali ishlashi mumkinmi? Modulni bulutda (data-markazda) saqlash mumkinmi?
5. Telegram marketpleysimiz (restoranlar nomidan buyurtma qabul qilish) marketpleys reyestriga kira oladimi? Kirsa, chekni kim chiqaradi: restoran kassasimi yoki marketpleys?

**B. Soliq qo'mitasi uchun**
6. 3-ilova 25-band 9-xatboshi (22.10.2026 dan): restoran POS'i API orqali ro'yxatdagi virtual kassaga buyurtma yuborsa va QR-chekni o'sha kassa chiqarsa, bu "ikkilamchi tizim integratsiyasi" hisoblanadimi? Qaysi sxema qonuniy?
7. POS dasturi o'zi virtual kassa sifatida reyestrga kirsa, bu 1-ilova 4-band va 161-band talablarini to'liq bajaradimi?

**C. TXKM hamkor uchun (BCT va reyestrdan topilganlar)**
8. Siz reyestrdagi TXKM misiz? Uchinchi tomon ishlab chiqqan virtual kassaga (RIZQIM) xizmat ko'rsatish shartnomasini tuzasizmi? Bir kassa uchun narx, modulni faollashtirish va restoranni ro'yxatga olish narxi qancha?
9. O'zingiz restoran yoki savdo POS dasturi sotasizmi? Sotsangiz, mijozlarimiz ma'lumotidan foydalanmaslik bo'yicha shartnomada band qo'ya olasizmi?

**D. Ko'prik provayderlar uchun (FiscalBox, REGOS, SmartOne)**
10. Mahsulotingiz hozir reyestrning qaysi toifasida, yozuv raqami va sanasi qanday? 2026-yilda ogohlantirish olganmisiz?
11. 25-band 9-xatboshi sizning TXKM ingizga tegmasligini qanday asoslaysiz? RIZQIM ulanganda sizning pozitsiyangiz yozma bo'ladimi?
12. API orqali qaysi cheklar ishlaydi: sotuv, qaytarish (asl chekka bog'langan, qisman), bo'nak, nasiya (kredit), aralash to'lov (naqd, karta va QR bir chekda), IKPU, qadoq kodi, QQS, markirovka? 01.04.2026 dan alkogol va pivoni naqdga bloklash qanday ishlaydi?
13. Qurilma kerakmi? Android va Windows kassalarimizdan lokal tarmoq orqali ishlaydimi? Internet uzilsa navbat qanday ishlaydi va 48 soat qanday hisoblanadi?
14. Sinov muhiti va test qurilma qachon beriladi? Hamkor (dasturchi) uchun narx, chegirma va pilot uchun bepul kassa bormi?
15. Reyestrdan chiqarilsangiz bizni qancha oldin ogohlantirasiz va mijozlarni ko'chirishga qanday yordam berasiz? Buni shartnomaga yozasizmi?
16. (Faqat BCT) fbox.uz dagi "Foodservice" bo'limidagi kafelar uchun buyurtma tizimi sizning o'z dasturingizmi?

**E. Payme va Click uchun**
17. Marketpleysdagi onlayn to'lovda chekni komissioner sifatida siz chiqarasiz. Restoran IKPU va QQS ini qanday berish kerak? Chek restoranning zal kassasi bilan ikki marta fiskallanmasligi uchun nima qilish kerak?

## Hali noma'lum narsalar

- **Reyestr:** virtual kassa, onlayn-NKM va TXKM reyestrlarining bugungi ro'yxati o'qilmadi (503). FiscalBox, REGOS VCR va SmartOne ning hozirgi holati tasdiqlanmagan.
- **25-band talqini:** rasmiy tushuntirish yo'q. Bu butun "provayder orqali" yo'lning qonuniyligini hal qiladi.
- **Pul:** OFD xulosasi, Kiberxavfsizlik ekspertizasi, muvofiqlik sertifikati narxi va beruvchisi; hamkor TXKM narxi; BHM ning 2026 qiymati (fbox.uz dan chiqadigan 440 000 so'm, REGOS esa 412 000 deydi).
- **Texnik:** FiscalDriveService rasmiy OFD hujjatimi; bitta modulni bir nechta kassa bilan ishlatish yoki bulutda saqlash mumkinmi; Android SAM-slot qurilmalari.
- **Raqib emas TXKM:** nomi bilan topilmadi.
- **Kompaniya ma'lumotlari:** BCT o'z POS dasturini sotadimi; UzKassa brendi haqiqatan Smartpos'gami; SmartOne API O'zbekistonga tegishlimi.
- **Marketpleys:** ovqat buyurtma platformasi marketpleys reyestriga kira oladimi va chekni kim chiqaradi.

Tafsilotlar va manbalar: `docs/research/research_notes/Fiskal provayder raqib bo'lmaganlar/` papkasida (nomzodlar va raqobat, qonun topilmalari, o'z kassa texnik yo'li).
