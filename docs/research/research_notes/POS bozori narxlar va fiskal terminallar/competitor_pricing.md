# O‘zbekistonda restoran/kafe POS dasturlarining narxlari va narx modellari (2026-yil oktabr)

> **Kurs.** Barcha qayta hisoblashlar O‘zbekiston Markaziy bankining rasmiy kursida qilingan: **1 USD = 11 903,15 so‘m** (23.10.2026), 1 RUB = 146,75 so‘m ([norma.uz, kurslar dinamikasi](http://www.norma.uz/rate_dinamic), 24.10.2026 da o‘qildi). Taqqoslash uchun 17.10.2026 da kurs 12 055,90 edi, ya’ni bir haftada so‘m ~1,3% mustahkamlangan.
> **Sana.** “24.10.2026 da o‘qildi” deb belgilangan narxlarni tadqiqotchi vendor sahifasidan shu kuni o‘qigan. “Oldingi nota (19.10.2026)” deganlari [raqobatchilar.md](../Ovqatlanish%20tarmoqlari%20uchun%20POS%20talablari/raqobatchilar.md) va [pos_raqobatchilar.md](../Ovqatlanish%20formatlari%20POS%20talablari/pos_raqobatchilar.md) dan olingan va bu safar qayta tekshirildi (agar shunday deyilgan bo‘lsa).
> **Belgilar** [Ovqatlanish tarmoqlari uchun POS talablari.md](../../reports/Ovqatlanish%20tarmoqlari%20uchun%20POS%20talablari.md) hisobotidagidek: [YETKAZIB BERUVCHI], [OAV], [ANIQ EMAS], [ESKI], [XORIJIY], [XULOSA].
> **Bu nota nimani kengaytiradi.** Hisobotning 6-bo‘limidagi narx jadvalida 10 ta tizim bor edi. Bu yerda: VKassa tariflari to‘liq ochildi (99k/299k/599k/1,199k), iiko’ning uchala tarifi ikki integratorda tasdiqlandi, Clopos (59/99 $), BROS (15 $), BILLZ (299k–999k, lekin faqat chakana), Dastur-xon (99k/399k), Mison ichki tarkibi, Poster’ning asosiy tariflar tuzilmasi, Multikassa→Rahmat qo‘shilishi, R-Keeper diler aksiyasi va OLX’dagi real e’lon narxlari qo‘shildi. Syrve, Quick Resto, Saby, Tillypad, Smartup, Datapos va Paylov bo‘yicha “O‘zbekistonda restoran mahsuloti topilmadi” natijasi qayd etildi.

---

## 1. O‘zbekistonda qaysi restoran POS/boshqaruv mahsulotlari sotiladi va kimning mahalliy dileri bor?

### Takeaway
Bozorda amalda uch guruh bor: (a) mahalliy integratorlar orqali dollarda sotiladigan **iiko** va **R-Keeper**; (b) dollarda narxlangan xorijiy bulutli SaaS (**Clopos**, **Poster**) va (c) so‘mda narxlangan o‘nga yaqin **mahalliy o‘zbek POS** (VKassa, Paloma365, RAOS, Biznex, Mison, Reca, Dastur-xon, BROS va boshqalar). Fiskal qatlam **Rahmat** qo‘lida birlashmoqda (multikassa.uz endi rhmt.uz/pos ga yo‘naltiriladi). So‘ralgan ro‘yxatdagi Syrve, Quick Resto, Saby, Tillypad, Smartup, BILLZ, Datapos va Paylov uchun O‘zbekistonda **restoran mahsuloti topilmadi**.

### Cited Findings

**iiko — kamida ikkita mahalliy integrator (A-ONE CORP, Soft24), ITDO ham (oldingi nota)**
- A-ONE CORP (Toshkent) iikoCloud’ni uchta tarifda, **har bir kassa uchun oyiga** sotadi: Start **54 $** (kichik kafe), Pro **81 $** (restoran va tarmoqlar; moliya, ombor, xodimlar), Enterprise **108 $** (yirik tarmoqlar; to‘liq yetkazib berish, call-markaz, kengaytirilgan sodiqlik, o‘z ilovalari). So‘mdagi narx, o‘rnatish, ofitsiant ilovasi, KDS va uskuna narxi saytda yo‘q. Aloqa: +998 77 071 44 55 — [rest.a-one.uz](https://rest.a-one.uz/), 24.10.2026 da o‘qildi [YETKAZIB BERUVCHI]
- Soft24.uz: Pro “**81 $ dan**”, Enterprise “**108 $ dan**” bir kassa uchun oyiga. “Kassa” = front-ofis buyurtma terminali yoki yetkazib berish operatori ish joyi. Kassalar ko‘paysa bitta kassa narxi tushadi (miqdor ko‘rsatilmagan). Joriy etishga menyu va texnologik kartalarni sozlash va o‘qitish kiradi, narxi ko‘rsatilmagan; ishga tushirilgandan keyin **3 oy bepul qo‘llab-quvvatlash**. Fiskal modul (Soliq qo‘mitasiga ulanish) bor, narxi yo‘q. Uskuna hamkorlar orqali — [soft24.uz/iiko](https://soft24.uz/iiko), 24.10.2026 da o‘qildi [YETKAZIB BERUVCHI]
- Qidiruv xulosasiga ko‘ra Soft24 “birlamchi joriy etish **100 $ dan**, bir martalik” deydi; sahifaning o‘zida bu raqamni ko‘rmadim — [qidiruv natijasi, soft24.uz](https://soft24.uz/iiko) [ANIQ EMAS]
- ITDO ham “54 $/oydan” deb yozadi (sahifada Rossiya shablonidan qolgan “1295 ₽/oydan” ham bor); uskunani ANORBANK orqali 0% muddatli to‘lov bilan beradi — [itdo.uz](https://itdo.uz/), oldingi nota (19.10.2026) [YETKAZIB BERUVCHI]
- Rossiyadagi iiko tariflari (taqqoslash uchun): “Kafe” **5 000 ₽**, Pro **8 600 ₽**, Enterprise **10 600 ₽** oyiga (≈ 734 / 1 262 / 1 556 ming so‘m, 146,75 so‘m/₽ da) — [qidiruv xulosasi, iiko.ru/open-s.info](https://open-s.info/prices/) [XORIJIY; ANIQ EMAS — sahifa ochilmadi, iiko.ru/tariffs 404]

**R-Keeper — diler BUSOFT**
- BUSOFT “O‘zbekistonda 1 000 dan ortiq avtomatlashtirilgan muassasa”, “16 yil bozorda” deydi. Narx sahifada yo‘q. R-Keeper’ni **sotib olish yoki oylik obuna** bilan ulash mumkin. R-Keeper va StoreHouse’ni loyiha tarkibida sotib olishda **10% dan chegirma**. **31.12.2026 gacha aksiya:** ulanishda (sotib olish yoki obuna) **1 ta termoprinter sovg‘a**. Xizmat ko‘rsatish tariflari alohida sahifada (o‘qilmadi). Fiskal: onlayn-kassa va fiskal registratorlarni ulash, ERI bo‘lsa soliq kabinetida ro‘yxatdan o‘tkazish. UZCARD (A35 bank terminali integratsiyasi), UZQR va UCS hamkori. Ofitsiant ilovasi, KDS, yetkazib berish moduli sahifada tilga olinmagan — [rkeeper.uz](https://rkeeper.uz/), 24.10.2026 da o‘qildi [YETKAZIB BERUVCHI]
- Prom.uz’dagi “Soft Atiservis” e’loni: R-keeper tizimi **13 312 629 so‘m** (≈ 1 118 $ joriy kursda), “nalichida yo‘q”, oxirgi yangilanish **14.03.2019** — [prom.uz](https://www.prom.uz/ads/sistema-avtomatizatsii-dlya-restoranov-i-kafe-r-keeper/) [ESKI]
- Rossiya katalogi: kassa stansiyasi litsenziyasi 24 000 ₽, menejer stansiyasi 30 000 ₽, ombor 28 000 ₽ (bir martalik), bulut 2 500 ₽/oy/foydalanuvchi; sana ko‘rsatilmagan — [crmindex.ru](https://crmindex.ru/products/r_keeper) [XORIJIY; ANIQ EMAS]

**Rahmat (Multikassa endi Rahmat ichida)**
- **multikassa.uz → 301 yo‘naltirish → rhmt.uz/pos/** (24.10.2026 da tekshirildi). Ya’ni Multikassa brendi Rahmat POS sahifasiga qo‘shilgan — [multikassa.uz](https://multikassa.uz/) [TASDIQLANGAN: yo‘naltirish]
- Rahmat POS tariflari: SUNMI P3 terminali **990 000 so‘m bir martalik**; Onlayn-KKM **100 000 so‘m/oy** (Humo, Visa, Mastercard, UnionPay karta; Click, Payme, Uzum va 10+ QR; tovar hisobi; fiskalizatsiya); to‘lov terminali **0 so‘m/oy** (faqat karta); virtual kassa **123 600 so‘m/oy** (noutbuk, PK yoki planshetda; QR, tovar hisobi, fiskalizatsiya). Bir martalik pullik xizmatlar (narxsiz): soatlik masofaviy maslahat va sozlash, OFD rekvizitlarini o‘zgartirish, POFM ro‘yxatdan o‘tkazish/faollashtirish/almashtirish, yo‘qolgan POFM uchun kompensatsiya, KPO uchun FGF, kassani soliq reyestrida ro‘yxatdan o‘tkazish. Ochiq API va “mashhur POS tizimlari” bilan integratsiya bor (nomlari yo‘q). Sinov muddati yo‘q — [rhmt.uz/pos/tariffs](https://rhmt.uz/pos/tariffs/), 24.10.2026 da o‘qildi [YETKAZIB BERUVCHI]
- Rahmat “Ресторанам” bo‘limi: “1 kunda ulanish”; schyot bo‘yicha to‘lov, onlayn menyu, bron, agregator, o‘z yetkazib berish — narxlari sahifada yo‘q — [rhmt.uz/rest](https://rhmt.uz/rest/), 24.10.2026 [YETKAZIB BERUVCHI]

**Poster (joinposter) — O‘zbekiston narxi ochiq emas**
- joinposter.com/uz va /uz/pricing **404**; /ru/pricing esa Ukraina narxlarini (₴) ko‘rsatadi, mamlakat tanlagich yo‘q — [joinposter.com/ru/pricing](https://joinposter.com/ru/pricing), 24.10.2026 [XORIJIY]
- Poster narx **tuzilmasi** (Ukraina, ₴/oy, yillik to‘lovda 10% chegirma): Starter 600 (yillik 540), Mini 1 200 (1 080), Business 1 700 (1 530), Pro 2 400 (2 160). Har tarifda 1 terminal; qo‘shimcha kassa 760 ₴; **mobil ofitsiant va KDS faqat Business’dan**; qo‘shimcha ofitsiant 360 ₴/oy; KDS (“Kitchen Kit”) 7 ₴/oy har ekran; menyu limiti 50/100/300/1 500 pozitsiya; qo‘shimcha filial — shu tarif narxida; **15 kun bepul sinov** (Pro darajasida). Bir martalik: menyuni to‘ldirish **30 ₴/pozitsiyadan**, tez start paketi 2 000 ₴, hamkor chiqishi 800 ₴/soatdan — [joinposter.com/ru/pricing](https://joinposter.com/ru/pricing) [XORIJIY]
- O‘zbekiston uchun fiskal qo‘shimcha: **28 $ ulanish + 8 $/oy Multikassa + 5 $/oy Poster integratsiyasi** (= 333 288 so‘m + 154 741 so‘m/oy) — [joinposter.com, Multikassa](https://joinposter.com/en/applications/multikassa), oldingi nota [YETKAZIB BERUVCHI]
- Uchinchi tomon agregator: Poster Starter **14 $**, Mini **26 $**/oy; O‘zbekistonga tegishliligi tasdiqlanmagan — [costbench.com](https://costbench.com/software/restaurant-pos/poster-pos/) [ANIQ EMAS]

**Clopos (Ozarbayjon) — o‘zbekcha sahifa, narx dollarda**
- Start **59 $/oy**, Standard **99 $/oy** (≈ 702 286 va 1 178 412 so‘m). **Yillik obuna 20% foydaliroq.** KDS va QR-menyu mahsulot sifatida bor, lekin narxsiz; tarif bo‘yicha funksiyalar taqsimoti sahifada ko‘rinmadi; bepul sinov shartlari yo‘q (“Sinab ko‘ring” formasi). Tel. 012 310 22 66 (Ozarbayjon raqami) — [clopos.com/uz/pricing](https://www.clopos.com/uz/pricing), 24.10.2026 [YETKAZIB BERUVCHI]

**JOWi — mahsulot holati noaniq, lekin e’lonlarda hamon o‘rnatiladi**
- jowi.club domeni ishlamaydi (DNS topilmadi), jowi.uz 404 — 24.10.2026 da tekshirildi [TASDIQLANGAN: saytlar ochilmaydi]
- OLX’da 2026-yil oktabrida JOWi hamon o‘rnatish xizmatlari ro‘yxatida: “магазин кафе учун EasyTrade/iiko/YesPos/Payme+Clik/Jowi” (Samarqand, 19.10.2026), “Dokon Restaran Kafe Sklad 1c jowi rkeper EasyTrade avtomatlashtirish” (Toshkent, Yashnobod, 09.10.2026), narxsiz — [OLX.uz, “iiko” xizmatlari](https://www.olx.uz/uslugi/q-iiko/); [OLX.uz, “kafe dastur”](https://www.olx.uz/oz/uslugi/q-kafe-dastur/), 24.10.2026 [ANIQ EMAS]
- Eski narxlar: 29,99 $/oydan (2019), “Standart” 69,99 $/oy (2023-yil oktabr, Poster ma’lumoti) — oldingi nota [ESKI]

**Ro‘yxatdagi, lekin O‘zbekistonda restoran mahsuloti topilmaganlar**
- **BILLZ** — faqat chakana savdo (kiyim, poyabzal, oziq-ovqat do‘koni, xo‘jalik mollari, kosmetika, elektronika va h.k.); bosh sahifada restoran yechimi yo‘q — [billz.io](https://billz.io/), 24.10.2026 [YETKAZIB BERUVCHI]. Tariflari taqqoslash uchun 2-bo‘limda.
- **Smartup** — smartup.uz → smartup24.com; distribyutor (FMCG, farma), ishlab chiqaruvchi, qurilish, telekom uchun; restoran mahsuloti va narx yo‘q — [smartup24.com](https://smartup24.com/), 24.10.2026 [YETKAZIB BERUVCHI]
- **Saby (SBIS)** — saby.uz → 302 → saby.ru; alohida O‘zbekiston sahifasi yo‘q — [saby.uz](https://saby.uz/), 24.10.2026 [TASDIQLANGAN: yo‘naltirish]
- **Quick Resto** — quickresto.uz domeni mavjud emas (DNS); O‘zbekiston bo‘yicha qidiruvda natija yo‘q — 24.10.2026 [ANIQ EMAS: yo‘qligi]
- **Syrve** (iiko’ning xalqaro brendi) va **Tillypad** — O‘zbekistondagi hamkor yoki narx topilmadi (qidiruv natijasiz) [ANIQ EMAS: yo‘qligi]
- **Datapos** (datapos.uz 503) va **Paylov** (paylov.uz sahifasida faqat sarlavha) — mahsulot va narx aniqlanmadi [ANIQ EMAS]

**Mahalliy o‘zbek POS’lar (narxlari 2-bo‘limda)** — VKassa, Paloma365, RAOS, Biznex, Mison, Reca (kafe.inware.uz), Dastur-xon, BROS; qidiruvda ular “restoran uchun dastur narxi” so‘roviga birinchi chiqadi — [qidiruv natijalari: biznex.uz, mison.uz, dastur-xon.uz, vkassa.uz, bros.uz, kafe.inware.uz](https://www.biznex.uz/) [YETKAZIB BERUVCHI]. OLX’da qo‘shimcha nomlar: ZPOS, Ovel POS, SmartPos (Namangan), Bito, “Neon Alisa”, VelionPOS (oldingi nota) — [OLX.uz](https://www.olx.uz/oz/uslugi/q-kafe-dastur/) [ANIQ EMAS]

### Inferences
- iiko’ning **O‘zbekistondagi narx ro‘yxati bir xil (54/81/108 $)** uch xil integratorda (A-ONE, Soft24, ITDO) — bu vendor tomonidan belgilangan narx, integratorlar faqat joriy etish va xizmatdan pul ishlaydi [XULOSA].
- Multikassa’ning Rahmat’ga qo‘shilishi fiskal qatlamni bitta kompaniyada jamlaydi: Poster’ning “Multikassa” fiskal ilovasi endi amalda Rahmat infratuzilmasi. Rizqim uchun fiskal hamkor sifatida Rahmat’dan muqobil yo‘q darajada muhim, bu esa bitta yetkazib beruvchiga bog‘lanish xavfini oshiradi [XULOSA].
- BILLZ (TBC Bank egaligida) restoranga chiqmagan; agar chiqsa, “bank + POS” to‘plami bilan arzon raqobatchi bo‘lishi mumkin — kuzatish kerak [XULOSA].

### Gaps
- Poster’ning O‘zbekiston uchun asosiy tarif narxi (so‘m yoki $) hamon topilmadi: sayt geolokatsiyaga qarab Ukraina narxini ko‘rsatadi.
- R-Keeper’ning O‘zbekistondagi joriy litsenziya/obuna narxi va BUSOFT xizmat tariflari ochiq emas (faqat so‘rov orqali).
- JOWi kompaniyasining 2026-yildagi rasmiy holati (yopilgan, sotilgan yoki faqat eski mijozlarni qo‘llab-quvvatlaydi) aniqlanmadi.
- Alipos (Samarqand) saytida matn yo‘q — narxi topilmadi ([alipos.uz](https://alipos.uz/), 24.10.2026).
- Datapos va Paylov’ning nima ekanligi aniqlanmadi (sayt ishlamadi/bo‘sh).

---

## 2. Har bir mahsulot qancha turadi va qanday hisoblanadi (tarif, birlik, yillik chegirma, o‘rnatish, fiskal, ofitsiant/KDS, sinov)

### Takeaway
Mahalliy so‘mdagi SaaS’ning kirish narxi **99–390 ming so‘m/oy** (8–33 $), “to‘liq restoran” tarifi **450–700 ming so‘m/oy** (38–58 $); dollardagi tizimlar (iiko, Clopos) **59–108 $/oy/kassa** (0,64–1,29 mln so‘m) dan boshlanadi va o‘rnatish alohida. Deyarli hamma joyda **narx birligi = 1 nuqta (filial)**, iiko’da esa **1 kassa (terminal)**. Fiskal faqat RAOS’da barcha tariflarga kiritilgan; Biznex’da faqat Pro’da; Poster, iiko va R-Keeper’da qo‘shimcha pulga. Yillik chegirma: Clopos va BILLZ 20%, Paloma365 va Poster 10%. Bepul sinov: RAOS 30 kun, Paloma365 va Poster 15 kun, VKassa 14 kun, BILLZ 7 kun, Dastur-xon 3 kun.

### Cited Findings

**Asosiy jadval** (1 USD = 11 903,15 so‘m; “ekv.” — tadqiqotchi hisoblagan qiymat)

| Mahsulot (diler) | Tarif: oylik narx | Ekv. | Narx birligi va limitlar | Yillik chegirma | O‘rnatish / menyu kiritish | Fiskal | Ofitsiant ilovasi / KDS | Bepul sinov | Manba, sana |
|---|---|---|---|---|---|---|---|---|---|
| **VKassa** (mahalliy) | MINI **99 000**; STANDART **299 000**; BUSINESS **599 000**; COMPLEX **1 199 000** so‘m | 8,32 / 25,12 / 50,32 / 100,73 $ | Har tarif = 1 filial. MINI: 2 foydalanuvchi, +20 000 so‘m har qo‘shimcha. STANDART/BUSINESS: 1 kassa, cheksiz foydalanuvchi; COMPLEX: 3 kassa. FAQ: narx “299 000 dan, qurilma soni va aylanmaga qarab oshadi” | Ko‘rsatilmagan | O‘rnatish, sozlash, o‘qitish kompaniya tomonidan (narxi yo‘q); Wi-Fi va montaj ishlari tarifga kirmaydi, alohida | Saytda tilga olinmagan | Ofitsiant (VKassa – Xodimlar) va ega ilovasi **bepul**; KDS STANDART’dan. BUSINESS: QR-menyu 120 mahsulot, CRM, **Telegram bot va yetkazib berish**, sodiqlik, AI | **14 kun**, karta shart emas | [vkassa.uz](https://vkassa.uz/), 24.10.2026 [YETKAZIB BERUVCHI] |
| **Dastur-xon** | Oddiy **99 000**; Biznes **399 000** so‘m | 8,32 / 33,52 $ | Cheksiz stol, xodim, buyurtma. Biznes: white-label, ofitsiant foizi, GPS smena nazorati, davomat, o‘z domeni | Yo‘q | “5–10 daqiqada” kompaniya o‘zi ochib beradi; to‘lov yo‘q deb yozilmagan | Tilga olinmagan | Oshpaz, ofitsiant, kassir panellari, oshxona printeri — ikkalasida | **3 kun** | [dastur-xon.uz](https://dastur-xon.uz/), 24.10.2026 [YETKAZIB BERUVCHI] |
| **Paloma365** | Boshlang‘ich **134 700**; Asosiy **224 500**; Universal **269 400** so‘m | 11,32 / 18,86 / 22,63 $ | 1 savdo nuqtasi; cheksiz foydalanuvchi; qo‘shimcha nuqta narxi yo‘q | **~10%** (yillik narx ko‘rsatilmagan) | **O‘qitish va sozlash bepul** | Tilga olinmagan | Tilga olinmagan | **15 kun** | [paloma365.uz/tariffs](https://paloma365.uz/tariffs), 24.10.2026 [YETKAZIB BERUVCHI] |
| **RAOS** | Starter **249 000**; Growth **449 000**; Pro **799 000** so‘m | 20,92 / 37,72 / 67,13 $ | Starter: 1 filial, 2 kassir; Growth: 3 filial, 10 kassir, mobil ilova, “AI tungi kassir”; Pro: cheksiz, API, menejer | Ko‘rsatilmagan | O‘rnatishda bepul yordam | **Soliq.uz barcha tariflarda bepul**, chek OFD’ga avtomatik | Stollar, ofitsiant hisobi bor; alohida ilova/KDS nomi yo‘q | **30 kun**, karta shart emas | [raos.uz/kassa/kafe](https://raos.uz/kassa/kafe), 24.10.2026 [YETKAZIB BERUVCHI] |
| **Reca** (kafe.inware.uz) | Mo‘tadil **330 000**; Kengroq **660 000**; Cheklovsiz **1 980 000** so‘m; yoki bir martalik **6,5–25,5 mln** | 27,72 / 55,45 / 166,34 $; bir martalik 546–2 142 $ | — | — | — | — | — | — | [kafe.inware.uz](https://kafe.inware.uz/), qidiruv xulosasi va oldingi nota [YETKAZIB BERUVCHI] |
| **Biznex** | Standard **349 000**; Pro **549 000** so‘m; Custom — kelishiladi | 29,32 / 46,12 $ | Qo‘shimcha ish joyi va filial alohida (narxsiz). Custom: bosh ofis paneli, kiosk | Ko‘rsatilmagan | **O‘rnatish, menyu kiritish va o‘qitish alohida pullik**, kelishuv bo‘yicha | **Faqat Pro va Custom’da** | KDS va mijoz ekrani, ko‘proq ofitsiant o‘rni — **Pro’da** | Ko‘rsatilmagan | [biznex.uz/tariflar](https://www.biznex.uz/tariflar), 24.10.2026 [YETKAZIB BERUVCHI]. Uskuna (kassa PK, printer, ekran) **ijaraga** — narxsiz |
| **Mison** | Start **390 000**; Polnyy **690 000** so‘m; “Pod klyuch” — loyiha bo‘yicha | 32,76 / 57,97 $ | 1 nuqta. Start: oflayn kassa, zallar, Z-hisobot, stop-list, QR-menyu. Polnyy: ombor, foodcost, mijoz bazasi, bonuslar, ish haqi, yetkazib berish | Ko‘rsatilmagan | “Kirish to‘lovi yo‘q”; Pod klyuch’da uskuna, tarmoq, Face ID, joyida o‘qitish alohida | Tilga olinmagan | Ofitsiant ilovasi **har tarifda**; **o‘z Telegram boti, kuryer ilovasi, Yandex Eda/Uzum Tezkor integratsiyasi — Polnyy’da** | Ko‘rsatilmagan | [mison.uz/ru](https://mison.uz/ru/), 24.10.2026 [YETKAZIB BERUVCHI] |
| **BROS** | **15 $/oy** (kuniga <50 buyurtma); ko‘prog‘i — foydalanishga qarab | ≈ 178 547 so‘m | **Buyurtma hajmiga bog‘liq** — bozorda yagona shunday model | — | Bepul o‘qitish, inventar kiritishda yordam, **professional fotosurat** | Tilga olinmagan | Ofitsiant ilovasi, stol QR-kodi bor | “Bepul akkaunt” | [bros.uz](https://bros.uz/), 24.10.2026 [YETKAZIB BERUVCHI] |
| **Rahmat POS** | Virtual kassa **123 600**; Onlayn-KKM **100 000**/oy + SUNMI P3 **990 000** bir martalik | 10,38 $; 8,40 $ + 83,17 $ | 1 kassa | — | Soatlik pullik xizmatlar (narxsiz) | **Fiskal o‘zi** | Restoran funksiyasi tarifda yo‘q | Yo‘q | [rhmt.uz/pos/tariffs](https://rhmt.uz/pos/tariffs/), 24.10.2026 [YETKAZIB BERUVCHI] |
| **iiko** (A-ONE, Soft24, ITDO) | Start **54 $**; Pro **81 $**; Enterprise **108 $** | 642 770 / 964 155 / 1 285 540 so‘m | **1 kassa (terminal) uchun**; kassa ko‘paysa chegirma | Ko‘rsatilmagan | Joriy etish alohida (Soft24: “100 $ dan”, ANIQ EMAS); 3 oy bepul qo‘llab-quvvatlash (Soft24) | Fiskal modul alohida / Rahmat virtual kassa +123 600 so‘m (oldingi nota) | Narxi ko‘rsatilmagan | Yo‘q | [rest.a-one.uz](https://rest.a-one.uz/); [soft24.uz/iiko](https://soft24.uz/iiko), 24.10.2026 [YETKAZIB BERUVCHI] |
| **Clopos** | Start **59 $**; Standard **99 $** | 702 286 / 1 178 412 so‘m | Ko‘rsatilmagan | **20%** | Ko‘rsatilmagan | Ko‘rsatilmagan | KDS mahsulot sifatida bor, narxsiz | So‘rov orqali | [clopos.com/uz/pricing](https://www.clopos.com/uz/pricing), 24.10.2026 [YETKAZIB BERUVCHI] |
| **R-Keeper** (BUSOFT) | Narx e’lon qilinmagan; sotib olish yoki obuna | — | Stansiya/modul bo‘yicha (RF: kassa stansiyasi 24 000 ₽ ≈ 3,52 mln so‘m) | Loyihada 10% dan chegirma | Ko‘rsatilmagan | Fiskal registrator / onlayn-kassa ulash | Ko‘rsatilmagan | — | [rkeeper.uz](https://rkeeper.uz/), 24.10.2026 [YETKAZIB BERUVCHI]; [crmindex.ru](https://crmindex.ru/products/r_keeper) [XORIJIY] |
| **Poster** (O‘zbekiston) | Asosiy tarif topilmadi; fiskal **+28 $ + 13 $/oy** | +333 288 + 154 741 so‘m/oy | 1 terminal/tarif (Ukraina modeli) | 10% (Ukraina) | Menyu to‘ldirish 30 ₴/pozitsiyadan (Ukraina) | Multikassa(Rahmat) orqali, pullik | **Faqat Business’dan** (Ukraina) | 15 kun | [joinposter.com](https://joinposter.com/ru/pricing) [XORIJIY] |
| **BILLZ** (taqqoslash; chakana) | Start **299 000**; Advanced **499 000**; Pro **999 000** so‘m | 25,12 / 41,92 / 83,93 $ | Start: 10 foydalanuvchi, 5 000 mijoz, 3 do‘kon; Advanced: 20 / 25 000 / 5; Pro: cheklovsiz. **+179 000 so‘m har qo‘shimcha do‘kon** | **20%** (1 yil va undan ortiq oldindan) | **Sozlash, uskuna ulash, o‘qitish, tovar yuklash bepul** | Tilga olinmagan | — | **7 kun** | [billz.io/prices](https://billz.io/prices), 24.10.2026 [YETKAZIB BERUVCHI] |

**Uskuna va boshqa bir martalik xarajatlar (yangi topilmalar)**
- R-Keeper: 31.12.2026 gacha ulanishda **termoprinter sovg‘a** — [rkeeper.uz](https://rkeeper.uz/) [YETKAZIB BERUVCHI]
- iiko integratorlari uskunani hamkorlar orqali beradi; ITDO — ANORBANK orqali 0% muddatli to‘lov (oldingi nota) — [soft24.uz/iiko](https://soft24.uz/iiko); [itdo.uz](https://itdo.uz/) [YETKAZIB BERUVCHI]
- VKassa: monoblok, printer, UPS tarifga kirmaydi, alohida kelishiladi; printer ESC/POS (Xprinter, Epson, HPRT), TCP yoki USB — [vkassa.uz](https://vkassa.uz/) [YETKAZIB BERUVCHI]
- Paloma365 va VKassa’da uskuna “alohida sotib olinadi”; Biznex’da ijaraga — yuqoridagi manbalar [YETKAZIB BERUVCHI]

### Inferences
- **Narx koridori yangilandi** [XULOSA]: hisobotdagi “135–550 ming” o‘rniga aniqrog‘i:
  - **Pastki pog‘ona (kichik kafe, choyxona, mobil POS):** 99–270 ming so‘m/oy (VKassa MINI, Dastur-xon Oddiy, Paloma365, BROS 15 $ ≈ 179 ming, RAOS Starter 249 ming).
  - **O‘rta pog‘ona (bitta to‘liq restoran):** 300–550 ming so‘m/oy (VKassa STANDART 299k, Reca 330k, Biznex 349k/549k, Mison Start 390k, Dastur-xon Biznes 399k, RAOS Growth 449k).
  - **Yuqori pog‘ona (Telegram bot, yetkazib berish, ombor, agregatorlar bilan):** 600 ming – 1,2 mln so‘m/oy (VKassa BUSINESS 599k, Mison Polnyy 690k, RAOS Pro 799k, VKassa COMPLEX 1,199k) va dollardagi tizimlar 0,64–1,29 mln so‘m/oy **har kassa uchun** (iiko, Clopos).
- **Telegram bot + yetkazib berish** mahalliy raqobatchilarda **yuqori tariflarga** qo‘yilgan: VKassa’da 599 000 (BUSINESS), Mison’da 690 000 (Polnyy). Rizqimning asosiy funksiyasi (Telegram buyurtma) raqobatchilarda ~600–700 ming so‘mlik paketda turadi — bu Rizqim uchun narx “soyabon”i [XULOSA].
- **KDS va ofitsiant ilovasi** odatda pullik farqlovchi: Poster’da Business’dan, Biznex’da Pro’dan; VKassa ofitsiant ilovasini bepul beradi, KDS’ni 299k’dan [XULOSA].
- **Fiskal** hamon pullik qo‘shimcha (Biznex Pro, Poster +13 $/oy, iiko + Rahmat 123 600). RAOS — fiskalni hamma tarifga kiritgan yagona tasdiqlangan raqobatchi [XULOSA].
- Bozor standarti: **oylik obuna, 1 nuqta uchun, o‘rnatish bepul yoki “kompaniya o‘zi sozlaydi”, 7–30 kun bepul sinov, yillik to‘lovda 10–20% chegirma** [XULOSA].
- Dollar narxlari so‘m mustahkamlanayotganda (bir haftada −1,3%) mahalliy mijoz uchun biroz arzonlashadi, lekin reklamada narxni faqat so‘mda ko‘rsatish talabi bor (Raqobat qo‘mitasi) — dollardagi narx marketingda kamchilik [XULOSA, quyidagi manbaga asoslanib: [anhor.uz](https://anhor.uz/news/ads/) [OAV]].

### Gaps
- Mahalliy SaaS’lardan hech biri qo‘shimcha filial/kassa narxini aniq yozmagan (BILLZ 179 000 so‘m/do‘kon bundan mustasno, lekin u chakana).
- iiko ofitsiant ilovasi (iikoWaiter), KDS, yetkazib berish modullari O‘zbekistondagi narxi topilmadi.
- Reca tarif tarkibi va VKassa, Mison, Dastur-xon fiskal qo‘llab-quvvatlashi saytlarda yozilmagan.
- Annual (yillik) narxlarni hech bir mahalliy vendor raqam bilan ko‘rsatmagan (faqat foiz).

---

## 3. Kichik ovqatlanish korxonalari amalda qancha to‘laydi (OLX, integratorlar, forumlar)?

### Takeaway
OLX.uz’da 2026-yil sentabr–oktabrda 20 ga yaqin “kafe/restoran dasturi” e’loni bor, ko‘pchiligi narxsiz. Narx ko‘rsatilganlari **0,6–1,2 mln so‘m** (dastur) va **~4,5 mln so‘m** (dastur + monoblok + printer), bir qismi aslida **dollarda** qo‘yilgan (OLX ularni ~11 958,8 so‘m/$ kursida so‘mga aylantirgan: 50 $, 81 $, 100 $). Choyxona uchun “**oylik to‘lov YO‘Q**” deb bir martalik sotiladigan dastur e’loni ham bor — ya’ni bir qism bozor obunaga emas, bir martalik xaridga o‘rgangan.

### Cited Findings
- “Kafe/Restoran egalari uchun! Tayyor iiko tizimi — qulay narxd”: **968 663 so‘m** (kelishiladi), Toshkent, Yakkasaroy, 10.10.2026 — [OLX.uz](https://www.olx.uz/oz/uslugi/q-kafe-dastur/), 24.10.2026 [ANIQ EMAS]
- “Kafe va restoranlar uchun SmartPos, QR menyu va POS tizimi”: **597 940 so‘m** (kelishiladi), Namangan, 23.10.2026 — [OLX.uz](https://www.olx.uz/oz/uslugi/q-kafe-dastur/) [ANIQ EMAS]
- “POS dastur | Magazin, Cafe, Dorixona… | ZPOS”: **1 195 880 so‘m**, Toshkent, Yunusobod, 25.09.2026; “Neon Alisa для кафе ресторанов и фаст фуда”: **1 195 880 so‘m**, 01.10.2026 — [OLX.uz](https://www.olx.uz/oz/uslugi/q-kafe-dastur/) [ANIQ EMAS]
- “R keper Jowi Poster R pos Manoblok Monoblok kassa Xprinter”: **4 450 000 so‘m** (kelishiladi; ≈ 374 $), Toshkent, Chilonzor, 16.10.2026 — [OLX.uz](https://www.olx.uz/oz/uslugi/q-kafe-dastur/) [ANIQ EMAS]
- “Choyxona, Kafe, Bar, FastFood Kassa, Zakaz Programma” — sarlavhada “**Oylik To‘lov YOQ**”, narx ko‘rsatilmagan, Toshkent, Chilonzor, 22.10.2026 — [OLX.uz, “choyxona programma”](https://www.olx.uz/uslugi/q-choyxona-programma/) [ANIQ EMAS]
- “Kassa dasturi: savdo, ombor, nasiya va hisobotlar | Ovel POS”: **300 000 so‘m** (kelishiladi; oylik yoki bir martalikligi noma’lum), 28.09.2026 — [OLX.uz](https://www.olx.uz/uslugi/q-choyxona-programma/) [ANIQ EMAS]
- Do‘kon dasturi “oylik to‘lovsiz”: 2 800 000 so‘m (Angren, 15.10.2026); “bepul o‘rnatish” bilan Core i5 monoblok 2 630 936 so‘m (02.10.2026) — [OLX.uz](https://www.olx.uz/oz/uslugi/q-kafe-dastur/) [ANIQ EMAS]
- OLX’da “Soliq kesh-bek QR-kodini iiko programma bilan bog‘lab beramiz” (24.09.2026 va 10.10.2026, Toshkent) — iiko foydalanuvchilariga **soliq integratsiyasi alohida xizmat** sifatida sotilmoqda, narxsiz — [OLX.uz, “iiko”](https://www.olx.uz/uslugi/q-iiko/) [ANIQ EMAS]
- Masofaviy buxgalteriya “VEREFIX, IIKO, 1C” (Buxoro, 08.10.2026) — iiko atrofida buxgalteriya xizmati bozori ham bor — [OLX.uz, “iiko”](https://www.olx.uz/uslugi/q-iiko/) [ANIQ EMAS]
- VelionPOS (do‘kon kassasi): **199 000 so‘m/oy + 499 000 so‘m o‘rnatish** — oldingi nota, [olx.uz](https://www.olx.uz/d/oz/obyavlenie/dokon-uchun-qulash-pos-dastur-ID4r2iS.html) [ANIQ EMAS]

### Inferences
- 968 663 / 81 = 11 958,8; 597 940 / 50 = 11 958,8; 1 195 880 / 100 = 11 958,8. Demak bu e’lonlar **dollarda** qo‘yilgan (81 $, 50 $, 100 $) va OLX ularni bir xil kursda so‘mga aylantirgan. “Tayyor iiko tizimi” 81 $ — bu aynan iiko Pro’ning oylik narxi; ya’ni e’lon bir martalik narx emas, ehtimol **oylik litsenziya qayta sotuvi** [XULOSA].
- Integratorlar daromadi asosan **o‘rnatish, soliq integratsiyasi va buxgalteriya xizmatidan** keladi; litsenziya narxi vendor tomonidan qat’iy [XULOSA].
- “Oylik to‘lovsiz” (bir martalik) dasturlar choyxona segmentida mavjud — Rizqim narx strategiyasida “bir martalik + arzon oylik xizmat” yoki “yillik oldindan to‘lov” varianti ham kerak bo‘lishi mumkin [XULOSA].
- Kichik kafe uchun “real” birinchi yil xarajati (taxmin): mahalliy SaaS 1,2–4,8 mln so‘m/yil + uskuna 3–6 mln so‘m (hisobotdagi baho) + fiskal (Rahmat 1,48 mln so‘m/yil yoki RAOS’da bepul) [XULOSA].

### Gaps
- Forum/Telegram kanallaridagi egalarning real to‘lov tajribasi (masalan, “iiko’ga oyiga qancha to‘layapsiz”) topilmadi.
- Davlat xaridlari (xarid.uz va h.k.) ichida restoran POS bo‘yicha lot qidirilmadi — vaqt yetmadi.
- OLX e’lonlarining aksariyati narxsiz; ko‘rsatilganlari ham “kelishiladi”.
- Integratorlarning yozma joriy etish narx ro‘yxati (menyu kiritish, o‘qitish, joyiga chiqish) ochiq manbada yo‘q.

---

## 4. Choyxona yoki milliy formatlarga maxsus yo‘naltirilgan sotuvchilar bormi?

### Takeaway
Bir nechta mahalliy vendor **“choyxona” so‘zini marketingda ishlatadi** (Dastur-xon: “kichik kafe va choyxonalar uchun” Oddiy tarifi; BROS: “restoran, choyxona va kafelar uchun”; OLX’da “Choyxona, Kafe, Bar, FastFood kassa” e’loni), lekin ularning sahifalarida choyxonaga xos funksiya (xona/joy narxi, qozon qoldig‘i, guruh hisobini bo‘lish, nasiya) **ko‘rsatilmagan**. Choyxona segmenti eng arzon tariflarga (99 000 so‘m yoki 15 $) qo‘yilgan.

### Cited Findings
- Dastur-xon: **Oddiy — 99 000 so‘m/oy, “kichik kafe va choyxonalar uchun”**; funksiyalar: QR-menyu va stollar, oshpaz/ofitsiant/kassir paneli, oshxona cheki, statistika. Yetkazib berish, ombor, sodiqlik, ko‘p filial — “tez kunda” — [dastur-xon.uz](https://dastur-xon.uz/), 24.10.2026 [YETKAZIB BERUVCHI]
- BROS: sarlavha “Restoran, Choyxona va Kafelar uchun Avtomatlashtirish Dasturi”; **15 $/oy (<50 buyurtma/kun)**; QR-menyu, ofitsiant ilovasi, onlayn va zal buyurtmasi; fiskal va Telegram buyurtma tilga olinmagan — [bros.uz](https://bros.uz/), 24.10.2026 [YETKAZIB BERUVCHI]
- OLX: “Choyxona, Kafe, Bar, FastFood Kassa, Zakaz Programma — Oylik to‘lov YO‘Q” (22.10.2026) — [OLX.uz](https://www.olx.uz/uslugi/q-choyxona-programma/) [ANIQ EMAS]
- Mison Start (390 000) “qahvaxona, oshxona (stolovaya), fast food, novvoyxona” uchun; choyxona yoki osh markazi alohida tilga olinmagan — [mison.uz/ru](https://mison.uz/ru/) [YETKAZIB BERUVCHI]
- Oldingi notalar: Reca, Biznex, Mison va VKassa sahifalarida tarozi, qozon qoldig‘i, xona narxi va xizmat haqi foizi ko‘rsatilmagan — [hisobot, 6-bo‘lim](../../reports/Ovqatlanish%20tarmoqlari%20uchun%20POS%20talablari.md) [TASDIQLANGAN: shu sahifalarda yo‘qligi]

### Inferences
- Choyxona uchun bozor narx “langari” — **~99 000–180 000 so‘m/oy** (Dastur-xon Oddiy, BROS 15 $, Paloma365 134 700). Rizqim choyxona tarifini shu oraliqdan uzoqlashtirsa, milliy funksiyalarni (xona narxi, qozon, nasiya, guruh hisobi) aniq ko‘rsatib asoslashi kerak [XULOSA].
- BROS’ning **buyurtma hajmiga bog‘langan** narxi (kuniga <50 buyurtma) kichik choyxona uchun qulay model: to‘lov biznes hajmiga mos o‘sadi va tushumdan foiz olinmaydi [XULOSA].
- Rizqim uchun narx taklifi (hisobotdagi 150–300 ming so‘m tavsiyasini yangilab) [XULOSA]:
  - **Choyxona/osh markazi “Start”:** 99–149 ming so‘m/oy — Dastur-xon va VKassa MINI bilan teng, BROS’dan arzon emas, lekin fiskal va Telegram bilan.
  - **“Telegram + POS” asosiy tarif:** 249–349 ming so‘m/oy — RAOS Starter va Biznex Standard oralig‘ida, lekin VKassa BUSINESS (599k) va Mison Polnyy (690k) dagi Telegram bot/yetkazib berishni o‘z ichiga oladi; bu eng kuchli qiymat argumenti.
  - **Banket/to‘yxona va tarmoq:** 449–699 ming so‘m/oy.
  - Fiskal: agar Rahmat virtual kassasi (123 600 so‘m/oy) orqali bo‘lsa, “Start” tarifida uni alohida qator sifatida ko‘rsatish yoki faqat yuqori tariflarga kiritish kerak; aks holda pastki tarif marjasi deyarli nolga tushadi.
  - Bozor standartiga mos: 14–30 kun bepul sinov, yillik oldindan to‘lovda 15–20% chegirma, o‘rnatish va menyu kiritish bepul (Biznex bundan pul oladi — farqlovchi argument).

### Gaps
- Choyxona egalarining POS uchun to‘lashga tayyorligi (willingness to pay) bo‘yicha so‘rov yoki intervyu ma’lumoti topilmadi.
- Dastur-xon va BROS mijozlari soni, choyxonalardagi real qo‘llanilishi noma’lum.
- OLX’dagi “oylik to‘lovsiz choyxona dasturi”ning narxi va muallifi aniqlanmadi.
