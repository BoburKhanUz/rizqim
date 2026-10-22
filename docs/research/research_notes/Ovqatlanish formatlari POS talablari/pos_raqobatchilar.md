# O‘zbekistondagi restoran POS va ovqatlanish dasturlari bozori: raqobatchilar (Rizqim uchun)

**Tayyorlangan sana:** 2026-yil 21-oktabr. Qamrov: faqat O‘zbekiston, 2023–2026.

**Belgilar:**
- **[YB]**: yetkazib beruvchining o‘z da’vosi (sayt, reklama maqolasi).
- **[REKLAMA]**: ommaviy axborot vositasida "На правах рекламы" deb belgilangan maqola.
- **[ESKI]**: 2024-yil oktabrdan oldingi manba.
- **[ANIQ EMAS]**: qidiruv parchasi, ikkilamchi manba yoki manbalar o‘rtasida ziddiyat bor.
- **[OLDINGI NOTA]**: fakt 19.10.2026 dagi `research_notes/Ovqatlanish tarmoqlari uchun POS talablari/raqobatchilar.md` faylidan olingan. Manba havolasi o‘sha yerda tekshirilgan, bu sessiyada qayta ochilmagan.

**Texnik eslatma:** 21.10.2026 kuni quyidagi saytlar ochilmadi: jowi.club (DNS ENOTFOUND, 19.10 da ham shunday edi), iiko.uz (503), alipos.uz (matn yo‘q), daryo.uz’dagi Alipos maqolasi (403), Kafesoft’ning OLX e’loni (404). joinposter.com geolokatsiya sababli Ukraina narxlarini ko‘rsatadi.

---

## 1. Qaysi POS tizimlari qaysi segmentda ustun va ular qancha turadi?

### Takeaway
Ochiq bozor ulushi statistikasi yo‘q. Bilvosita belgilar quyidagicha:
- **Toshkentdagi o‘rta va yuqori segment hamda tarmoqlar:** iiko va R-Keeper. Soliq organlari aynan R-Keeper, JOWi va iiko’ni nomlagan. Yandex Eats faqat iiko va R_Keeper bilan tayyor integratsiya beradi. BUSOFT "1000+ muassasa" da’vo qiladi.
- **Bulutli SaaS:** Poster, Clopos va Paloma365.
- **Mahalliy yangi o‘yinchilar:** Alipos (1 291 muassasa, Samarqand), Biznex, RAOS.
- **Billz** chakana savdo uchun, restoran POSi emas.

Narxlar:
- iiko (ITDO): **54 / 81 / 108 $/oy** (Start / Pro / Enterprise).
- Mahalliy SaaS: **~135–800 ming so‘m/oy**.
- R-Keeper narxi ochiq emas.

Bozorning katta qismi umuman avtomatlashtirilmagan: BMTTD bo‘yicha faqat 36% korxonada boshqaruv dasturi bor.

### Cited Findings

**Bozordagi umumiy manzara**
- Buxgalter.uz (13.12.2023) bo‘yicha, umumiy ovqatlanishda hisobni avtomatlashtirish uchun asosan R-Keeper, Jowi va iiko ishlatiladi. Mehmonxonalar uchun "Feliks" tilga olinadi. — [buxgalter.uz, 13.12.2023](https://buxgalter.uz/oz/publish/doc/text195022_soliq_organlarining_uzbekistonning_chakana_savdo_va_umumiy_ovqatlanish_korhonalariga_ularning_hisobga_olish_dasturlarini_soliq_organlari_bilan_integraciya_qilish_tugrisida_talabi_tugrisida) **[ESKI]**
- Korxonalarning faqat **36%** ida "R-Keeper yoki shunga o‘xshash" dastur bor. So‘rovda 14 viloyat markazidan 420 tadbirkor qatnashgan. — [BMTTD, 2026-yil may](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) **[OLDINGI NOTA]**
- Yellowpages.uz’ning "Restoranlar uchun dasturlar" rubrikasida 15–17 ta kompaniya bor. Ular orasida ELASTIX.UZ, E-POS, ADKHAMOV GROUP, AISolution va MICROS TM bor. — [yellowpages.uz](https://www.yellowpages.uz/rubrika/programmy-dlya-restoranov-avtomatizaciya-restoranov-razrabotka-vnedrenie); [yellowpages.uz/uz](https://www.yellowpages.uz/uz/rubrika/restoranlar-uchun-dasturlar-restoranlarni-avtomatlashtirish-ishlab-chiqish-amalga-oshirish)

**iiko**
- **Narxi (ITDO, iiko integratori, Toshkent):** Start **54 $/oy**, Pro **81 $/oy**, Enterprise **108 $/oy**. Pro’da API orqali uchinchi tomon ilovalarini ulash mumkin.
  - Shu sahifada Rossiya shablonidan qolgan "от 1295 ₽/месяц" yozuvi ham bor, bu dollar narxlariga zid.
  - Joriy etish narxi va mijozlar ro‘yxati sahifada yo‘q.
  - Uskuna ANORBANK orqali bo‘lib to‘lanadi: "до 50 млн сум на 1 год без процентов". Qo‘llab-quvvatlash 24/7, sayt rus tilida.
  - — [itdo.uz, 21.10.2026 o‘qildi](https://itdo.uz/) **[YB]**
- **Uzum Tezkor bilan hamkorlik (2023):** iiko Uzum Tezkor hamkorlarining "har uchinchisi"da ishlatiladi, bu Toshkentda taxminan **150 muassasa**. Integratsiya buyurtmani to‘g‘ridan-to‘g‘ri oshxonaga yuboradi, stop-listni avtomatik oladi va yetkazishni 5–7 daqiqaga tezlashtiradi. Nomi aytilgan zanjir yo‘q. — [the-tech.kz, 08.08.2023](https://the-tech.kz/uzum-tezkor-i-iiko-obyavili-o-strategicheskom-partnerstve-v-uzbekistane/) **[ESKI]**
- **Kafesoft (Samarqand):** OLX e’lonida o‘zini "iiko Gold Partner" deb atagan. 24/7 rus va o‘zbek tilida yordam, Uzcard, Uzum, Humo, Payme va Click bilan sodiqlik va integratsiyalarni va’da qiladi. — [olx.uz e’loni (qidiruv parchasi; 21.10.2026 da 404)](https://www.olx.uz/d/obyavlenie/samarqand-iiko-dasturi-ombor-va-kassa-ID4qhDQ.html) **[ANIQ EMAS]**
- **Funksiyalar (ITDO):** tex-karta bo‘yicha avtomatik hisobdan chiqarish, ombor, moliya, iikoWeb bulut paneli, mehmonlar bazasi, bonus va aksiyalar. — [itdo.uz](https://itdo.uz/bazaznaniy/iiko-ili-r-keeper/) **[OLDINGI NOTA] [YB]**

**R-Keeper (UCS)**
- **BUSOFT (rkeeper.uz):** "16 yil bozorda, O‘zbekistonda 1000 dan ortiq avtomatlashtirilgan muassasa" deydi. UCS’ning kumush maqomli hamkori. Mijozlari: The Doner, WeDrink, Osteria Mario/Shvili (Tigrus), Bla Bla Bar, City Grill, "Papin dom" (Olmaliq). Litsenziyalarga 10% dan chegirma beriladi, narx e’lon qilinmagan. — [rkeeper.uz](https://rkeeper.uz/) **[OLDINGI NOTA] [YB]**
- **Spot.uz reklama maqolasi (18.03.2025)** quyidagi funksiyalarni sanaydi:
  - virtual kassa orqali fiskal integratsiya;
  - tovarlarni markirovkalash tizimi bilan integratsiya;
  - **Pro Bonus**: plastik karta o‘rniga Telegram-bot va QR orqali bonus;
  - **Pro Alert**: kassa operatsiyalari bo‘yicha Telegram bildirishnomalari va hisobotlar;
  - **StoreHouse Pulse**: barcha omborlar zaxirasini tahlil qiladigan, haftalik daromad, tannarx va foyda hisobotini beradigan va xarid tavsiya qiladigan AI moduli.

  Agregatorlar, KDS, til va narx haqida ma’lumot yo‘q. Kontakt sifatida rkeeper.uz va "Kahramon" brendi ko‘rsatilgan. — [spot.uz, 18.03.2025](https://www.spot.uz/ru/2025/03/18/r-keeper/) **[REKLAMA]**
- **Soft Business Group (SBG) + UCS** 02.11.2024 da Hilton Tashkent City’da HoReCa nonushtasini o‘tkazgan.
  - Mavzulardan biri "Oqtepa Lavash tarmog‘ini masshtablash" bo‘lgan, Oqtepa Lavash IT direktori Aleksey Orlovskiy ma’ruza qilgan.
  - Boshqa mavzular: Uzum Tezkor istiqbollari (tijorat direktori Lev Muravyov), alkogolni markirovkalash (Soliq qo‘mitasi va CRPT Turon), Yangi Bank ekvayringi.
  - UCS savdo direktori Yelena Kondratova ham ishtirok etgan.
  - — [spot.uz, 02.11.2024](https://www.spot.uz/ru/2024/11/02/sbg-rkeeper/) **[REKLAMA]**
- 2018-yilda R-Keeper Uzbekistan bosh direktori Qahramon Shukurjanov bo‘lgan. — [spot.uz, 24.02.2018 (qidiruv parchasi)](https://www.spot.uz/ru/2018/02/24/restaurants/) **[ESKI]**
- R-Keeper Akademiyasi O‘zbekistonda "buxgalter-kalkulyator" kasbiga o‘qitadi. — [spot.uz, 23.05.2024 (teg sahifasi)](https://www.spot.uz/ru/tag/r-keeper/) **[ESKI]**

**Poster (joinposter)**
- **Narx sahifasi** 21.10.2026 da Ukraina narxlarini ko‘rsatdi: Starter 540 ₴, Mini 1 080 ₴, Business 1 530 ₴, Pro 2 160 ₴ /oy (yillik to‘lovda).
  - Har bir tarifga 1 terminal kiradi. Qo‘shimcha kassa 760 ₴, mobil ofitsiant 360 ₴/oy.
  - Modifikatorlar, yetkazish, mobil ofitsiant va oshxona ekrani faqat **Business** va undan yuqori tariflarda.
  - Zal sxemasi va sodiqlik **Mini**’dan boshlanadi.
  - O‘zbekiston narxi ko‘rinmadi.
  - — [joinposter.com/pricing](https://joinposter.com/pricing) **[YB]** (O‘zbekiston bozoriga mos kelmasligi mumkin)
- **O‘zbekistonda fiskalizatsiya** Multikassa ilovasi orqali: **28 $ ulanish + 8 $/oy Multikassa + 5 $/oy Poster integratsiyasi**. IKPU va qadoq kodlari tasnif.soliq.uz’dan tanlanadi. — [joinposter.com, Multikassa](https://joinposter.com/en/applications/multikassa) **[OLDINGI NOTA] [YB]**

**JOWi (Toshkent)**
- Yuridik shaxs: "SMART NEW TECHNOLOGIES SISTEMS" MChJ. Golden Pages kartochkasi 06.05.2026 da yangilangan. — [goldenpages.uz](https://www.goldenpages.uz/company/?Id=86605)
- Gibrid arxitektura: restoranda lokal modul ishlaydi, ma’lumotlar AQSh data-markazlaridagi serverlar bilan sinxronlanadi, internet uzilganda ham ishlaydi. — [spark.ru](https://spark.ru/startup/jowi-club/blog/22669/o-proekte-jowi-club) **[ESKI]**
- Funksiyalari: buyurtma, hisobni yopish, kalkulyatsiya, inventarizatsiya, analitika, marketing. Kompyuter, planshet yoki telefonda ishlaydi. Narxi 2019-yilda 29,99 $/oydan boshlangan. — [weproject.media, 2019](https://www.weproject.media/articles/detail/kak-uskorit-i-uprostit-rabotu-restorana-5-effektivnykh-sistem-avtomatizatsii/) **[ESKI]**
- 2023-yil mayda Yandex Eats bilan integratsiya qilingan. — [spot.uz, 10.05.2023](https://www.spot.uz/oz/2023/05/10/yandex-eats-jowi/) **[OLDINGI NOTA] [ESKI]**
- 2025–2026-yillarda JOWi haqida yangilik topilmadi. jowi.club domeni DNS’da topilmayapti (19 va 21-oktabr). — o‘z tekshiruvim; [qidiruv natijasi bo‘sh](https://www.spot.uz/ru/2026/08/18/joja/)

**Clopos (Ozarbayjon, O‘zbekistonda ofisi bor)**
- Mahsulotlari: bulutli POS, KDS, restoranni boshqarish, QR-menyu, Clopos Manager ilovasi. Bosh ofis Bakuda, Markaziy Osiyoga kengayish uchun O‘zbekistonda ofis ochilgan. Faol bozorlari: Ozarbayjon, O‘zbekiston, Turkiya, Qozog‘iston. IT Park Ventures portfelida. — [tech.az](https://tech.az/en/posts/clopos-has-achieved-43-growth-in-the-international-market-5490); [dealroom](https://app.dealroom.co/companies/clopos)
- Mijozlar soni bo‘yicha manbalar bir-biriga zid:
  - "12 mamlakatda 4 500+ restoran" (kompaniya posti, qidiruv parchasi);
  - "~2 000" ([idda.az](https://awards.idda.az/en/nominees/405));
  - "3 000+" ([tech.az](https://tech.az/en/posts/clopos-has-achieved-43-growth-in-the-international-market-5490)).

  **[ANIQ EMAS]**
- Integratsiyalar ro‘yxatida **Uzum Tezkor**, Wolt, Yango va boshqalar bor. Wolt integratsiyasi menyu va qoldiqni sinxronlaydi va buyurtmani POS’ga tushiradi. — [developer.wolt.com](https://developer.wolt.com/integration-partners/clopos); [clopos.com/uz](https://www.clopos.com/uz) **[YB]**

**Alipos (Samarqand, mahalliy startap)**
- HoReCa SaaS: kassa, ombor nazorati, moliyaviy hisobot va mijozlar analitikasi.
  - **1 291 restoran va kafe** foydalanadi; 23 xodim va 29 hududiy hamkor bor.
  - Asoschilari: Muhammad Ali Akbarov, Jonibek Miralimov, Komil Xamidov.
  - The Pitch Uzbekistan’da **200 000 $** jalb qilgan (2026-yil aprel).
  - — [daryo.uz, 01.04.2026 (qidiruv parchasi; sahifa 403)](https://daryo.uz/en/2026/04/01/samarkand-startup-alipos-secures-200000-investment-on-the-pitch-uzbekistan/); [the-tech.kz](https://the-tech.kz/startap-alipos-iz-uzbekistana-privlek-200-000-na-shou-the-pitch-uzbekistan/)
- Investorlar bo‘yicha manbalar zid:
  - Daryo va the-tech.kz: Central Asian University (AKFA) va AloqaVentures, bitim due diligence bosqichida.
  - Munich Startup: Imkon Ventures va Nexus Capital, 3,5 mln $ baholashda.

  — [munich-startup.de](https://insights.munich-startup.de/news/feed/uzbek-horeca-saas-startup-alipos-raises-200k-at-3-5m-valuation-from-imkon-ventures-and-nexus-capital) **[ANIQ EMAS]**
- Narxi va funksiyalar matritsasi topilmadi (alipos.uz da matn yo‘q).

**Biznex, RAOS, Paloma365 (mahalliy SaaS, so‘mda narx)**
- **Biznex** (21.10.2026 da o‘qildi):
  - **Standard 349 000 so‘m/oy**: kassa, smena, ombor va tannarx.
  - **Pro 549 000 so‘m/oy**: + modifikator va kombo, KDS, mijoz ekrani, soliq integratsiyasi.
  - **Custom**: + ko‘p filial, bosh ofis paneli, kiosk.
  - O‘rnatish, menyu kiritish va o‘qitish alohida kelishiladi. Uskunani ijaraga olish mumkin.
  - Vazn bo‘yicha sotish, qozon yoki partiya, agregatorlar, sodiqlik va ofitsiant ilovasi tarif sahifasida **ko‘rsatilmagan**.
  - — [biznex.uz/tariflar](https://www.biznex.uz/tariflar) **[YB]**
- **RAOS:** Starter 249 000, Growth 449 000, Pro 799 000 so‘m/oy. Soliq.uz barcha tariflarda. "8+ kafe va restoran" ishlatadi. — [raos.uz/kassa/kafe](https://raos.uz/kassa/kafe) **[OLDINGI NOTA] [YB]**
- **Paloma365:** 134 700 / 224 500 / 269 400 so‘m/oy. "20 000+ obyekt" (universal: chakana va HoReCa birga). — [paloma365.uz/tariffs](https://paloma365.uz/tariffs) **[OLDINGI NOTA] [YB]**

**Billz va boshqa "restoran emas" tizimlar**
- **Billz:** chakana savdo uchun bulutli POS (kiyim, poyabzal, elektronika), O‘zbekistonda savdoni avtomatlashtirish standartlaridan biri deb ataladi. Restoran moduli haqida ma’lumot yo‘q. — [the-tech.kz, 23.05.2025](https://the-tech.kz/it-produkty-iz-uzbekistana-za-kotorymi-stoit-sledit-v-2025-godu/)
- TBC Bank Group Billz’ning ko‘pchilik ulushini sotib olgan (2025-yil iyun). Summa bo‘yicha manbalar zid: "9 mln $" (qidiruv parchasi) va "12 mln $" (sarlavha). — [digitalbusiness.kz, 06.10.2025](https://digitalbusiness.kz/2025-10-06/vlozhili-200-tisyach-lichnih-sredstv-kak-startap-iz-uzbekistana-poluchil-12-mln-ot-krupnogo-banka/); [cbinsights](https://www.cbinsights.com/company/billz) **[ANIQ EMAS]**
- 2026-yilgi bir sharhda Billz "retail, HoReCa va e-commerce uchun SaaS" deb, Clopos, Urest va AliPos bilan bir qatorda tilga olingan. Manba aniq emas. **[ANIQ EMAS]**
- **Smartup:** distribyutsiya va savdo vakillari platformasi, restoran POSi emas. — [smartup.uz](https://smartup.uz) **[OLDINGI NOTA]**
- **ZPOS:** chakana savdo uchun Windows va POS-terminal kassasi, o‘zbek va rus tilida. — [glotr.uz](https://glotr.uz/uz/zpos-pos-dastur-p-1094448/)
- **REGOS:** chakana savdo va virtual kassa. — [regos.uz](https://regos.uz/ru) **[OLDINGI NOTA]**
- **Datagram, Mobi POS, Qulay:** O‘zbekiston restoran POSi sifatida birorta manba topilmadi.

**Uskunalar (O‘zbekistondagi narxlar)**
- **Chek printeri Xprinter 58 mm (OLX, 2026-yil iyul–avgust e’lonlari):**
  - XP-58IIX: 250 000 so‘m (yangi);
  - EASYPOS Xprinter 58: 301 245 so‘m;
  - XP-58IIL: 300 000 so‘m (b/u).

  80 mm oshxona printerlari narxi topilmadi. — [olx.uz qidiruvi](https://www.olx.uz/elektronika/q-xprinter-58/) **[ANIQ EMAS]**
- **Rahmat:** SUNMI P3 smart-kassa **990 000 so‘m** + 100 000 so‘m/oy; virtual kassa 123 600 so‘m/oy. — [rhmt.uz/pos/tariffs](https://rhmt.uz/pos/tariffs/) **[OLDINGI NOTA] [YB]**
- **REGOS:** POS va monoblok to‘plamlari 770 000 – 6 527 000 so‘m. — [regos.uz](https://regos.uz/ru) **[OLDINGI NOTA] [YB]**
- **Sunmi T2s (statsionar POS):** Yevropa narxi 933 €, O‘zbekiston narxi topilmadi. — [dateks.lv (eski kesh)](https://www.dateks.lv/cenas/gaisa-attiritaji/800181-sunmi-t2s-desktop-pos) **[ANIQ EMAS]**
- BUSOFT R-Keeper buyurtmasiga termoprinterni sovg‘a qiladi (2026 aksiyasi). — [rkeeper.uz](https://rkeeper.uz/) **[OLDINGI NOTA]**

**Tarmoqlar va ularning tizimlari**
- **Tasdiqlangan (diler sayti bo‘yicha):** The Doner, WeDrink, Osteria Mario/Shvili, Bla Bla Bar, City Grill va Papin dom R-Keeper ishlatadi. — [rkeeper.uz](https://rkeeper.uz/) **[OLDINGI NOTA] [YB]**
- **Oqtepa Lavash** (13 shaharda 99 filial, eng ko‘p soliq to‘lovchi 3 ta ovqatlanish korxonasidan biri). IT direktori r_keeper/SBG tadbirida "tarmoqni masshtablash" mavzusida chiqqan. Bu Oqtepa r_keeper ishlatishini **taxmin qilishga** asos beradi, lekin tasdiq emas. — [spot.uz, 02.11.2024](https://www.spot.uz/ru/2024/11/02/sbg-rkeeper/); [spot.uz, 29.10.2025 (qidiruv parchasi)](https://www.spot.uz/ru/2025/10/29/oqtepa-lavash)
- **EVOS, KFC (International Foodchain), Les Ailes (Havoqand People, 30 ta muassasa), Burger King, Domino’s** uchun POS tizimini ko‘rsatadigan manba topilmadi. KFC 2025-yilda daromad bo‘yicha EVOS’dan o‘zib ketgan. — [anhor.uz](https://anhor.uz/news/na-rynke/); [spot.uz, Les Ailes](https://www.spot.uz/ru/2019/10/07/les-ailes/) **[ESKI]**

### Inferences
- **Segmentlar bo‘yicha:**

  | Segment | Asosiy tizimlar |
  |---|---|
  | (a) Yirik tarmoqlar, fine dining, Toshkent | R-Keeper (BUSOFT/SBG) va iiko (ITDO, Kafesoft) |
  | (b) O‘rta kafe va fast food | iiko, Poster, Clopos |
  | (c) Viloyat va kichik kafelar | Alipos, Biznex, RAOS, Paloma365 yoki umuman dastursiz |
  | Choyxona va osh markazlari | Bu formatga ixtisoslashgan tizim **topilmadi** |

- Alipos’ning 1 291 muassasasi va 29 hududiy hamkori uni viloyatlardagi eng katta mahalliy restoran POSi qilishi mumkin. Lekin bu raqam kompaniyaning o‘z da’vosi.
- **Narx oralig‘i (1 nuqta, oyiga, uskunasiz):**
  - mahalliy SaaS: ~135–550 ming so‘m;
  - iiko: 54 $ (~0,65–0,7 mln so‘m) dan 108 $ gacha, bunga joriy etish qo‘shiladi;
  - Poster: fiskal uchun +13 $/oy.

  Kurs tekshirilmadi.
- Chakana savdo POSlari (Billz, REGOS, ZPOS) ham bozorda kuchli. Billz’ning bank (TBC) bilan bog‘lanishi uni kelajakda HoReCa’ga kengaytirishi mumkin, bu potensial xavf. **[ANIQ EMAS]**

### Gaps
- iiko va R-Keeper’ning O‘zbekistondagi joriy etish narxi, doimiy litsenziya narxi va StoreHouse narxi topilmadi.
- Alipos va Clopos’ning O‘zbekiston narxlari topilmadi.
- Poster’ning O‘zbekiston narxi topilmadi (geolokatsiya muammosi).
- EVOS, KFC, Les Ailes, Oqtepa, Beshqozon va Milliy Taomlar kabi tarmoqlar qaysi POSda ishlashini tasdiqlovchi manba yo‘q. hh.uz vakansiyalarida "знание iiko/R-Keeper" topilmadi: qidiruv faqat qozog‘iston vakansiyasini qaytardi ([kaspi.kz](https://obyavleniya.kaspi.kz/a/kassir-123008268)).
- 80 mm oshxona printerlari, KDS ekranlari va Windows touch-monobloklarning so‘mdagi narxlari tizimli to‘planmadi.

---

## 2. Qaysi tizimlarda O‘zbekiston fiskal (OFD / onlayn-NKM) integratsiyasi va agregator (Yandex Eats, Uzum Tezkor, Express24, Wolt) integratsiyasi bor?

### Takeaway
"Katta" tizimlar fiskalni o‘zi qilmaydi. Ular reyestrdagi virtual kassa (Rahmat/Multikassa, E-POS) yoki fiskal registrator orqali ishlaydi va buning uchun qo‘shimcha oylik to‘lov olinadi. Mahalliy SaaS’lar (RAOS, Biznex Pro) fiskalni o‘rnatilgan holda beradi.

Agregatorlar:
- **Yandex Eats/Express24** tayyor integratsiyani faqat **iiko va R_Keeper Delivery** uchun beradi, qolganlar API orqali ulanadi. Integratsiyaga faqat reytingi **≥4,6** bo‘lgan va (8+ filiali bor **yoki** kuniga 7+ buyurtma oladigan) restoranlar kiritiladi.
- **Uzum Tezkor:** iiko (2023) va Clopos.
- **Wolt:** Clopos.

### Cited Findings

**Fiskal**
- **Huquqiy asos:** naqd hisob-kitob faqat onlayn-NKM yoki virtual kassa orqali. Asosiy hujjat: Vazirlar Mahkamasining 23.11.2019 dagi 943-son qarori.
- **2023-yil dekabr:** soliq organlari umumiy ovqatlanish va chakana savdo korxonalariga avtomatlashtirish dasturlarini (R-Keeper, Jowi, iiko) soliq tizimlari bilan integratsiya qilishni talab qilib xat yuborgan va Soliq kodeksining 227-1-moddasi bo‘yicha jarima bilan ogohlantirgan.
- Ekspert V. Gibel’ning fikricha, majburiyat dasturga emas, reyestrdagi onlayn-NKM yoki virtual kassaga tegishli.
- — [buxgalter.uz, 13.12.2023](https://buxgalter.uz/oz/publish/doc/text195022_soliq_organlarining_uzbekistonning_chakana_savdo_va_umumiy_ovqatlanish_korhonalariga_ularning_hisobga_olish_dasturlarini_soliq_organlari_bilan_integraciya_qilish_tugrisida_talabi_tugrisida) **[ESKI]**
- R-Keeper ishlatib integratsiya qilmagan korxonaga 100 mln so‘m jarima solingan, sud tadbirkor foydasiga hal qilgan. — [kun.uz, 22.12.2023](https://kun.uz/news/2023/12/22/bu-tadbirkorlar-uchun-olim-jazosi-soliq-maslahatchisi-227-1-modda-haqida) **[OLDINGI NOTA] [ESKI]**

**Tizimlar bo‘yicha fiskal yechimlar**

| Tizim | Fiskal yechim | Manba |
|---|---|---|
| R-Keeper | Virtual kassa integratsiyasi va markirovka | [spot.uz, 2025](https://www.spot.uz/ru/2025/03/18/r-keeper/) **[REKLAMA]** |
| R-Keeper (BUSOFT) | Fiskal registrator va onlayn-kassani ulaydi | [rkeeper.uz](https://rkeeper.uz/) **[OLDINGI NOTA]** |
| iiko | Rahmat/Multikassa plagini; ITDO fiskal modulni sozlaydi | [rhmt.uz/pos/integration](https://rhmt.uz/pos/integration/); [itdo.uz](https://itdo.uz/bazaznaniy/iiko-ili-r-keeper/) **[OLDINGI NOTA]** |
| Poster | Multikassa (28 $ + 13 $/oy) | [joinposter.com](https://joinposter.com/en/applications/multikassa) **[OLDINGI NOTA]** |
| RAOS | Soliq.uz/OFD barcha tariflarda bepul | [raos.uz](https://raos.uz/kassa/kafe) **[OLDINGI NOTA]** |
| Biznex | Faqat Pro (549 000 so‘m) va Custom tariflarda | [biznex.uz/tariflar](https://www.biznex.uz/tariflar) |
| JOWi | 2023-yilda asoschisi integratsiyani "texnik jihatdan imkonsiz" degan | fiskal_background.md **[OLDINGI NOTA] [ESKI]** |
| Clopos, Alipos, Paloma365 | O‘zbekistonda fiskalni qanday hal qilishi topilmadi | — |

- **Kafolatli markirovka (Asl Belgisi/CRPT Turon)** alkogol va boshqa tovarlar uchun HoReCa’ga ham tegishli. U r_keeper tadbirida Soliq qo‘mitasi tomonidan taqdim etilgan. — [spot.uz, 02.11.2024](https://www.spot.uz/ru/2024/11/02/sbg-rkeeper/) **[REKLAMA]**

**Agregatorlar**
- **Yandex Eats (O‘zbekiston) integratsiya sahifasi:**
  - Tayyor tizimlar: **iiko** (iiko.biz orqali, sertifikatlangan iiko hamkori yordamida) va **R_Keeper Delivery**. Qolganlar, jumladan o‘z tizimi bo‘lsa, Yandex API orqali ulanadi. API menyu, buyurtma yaratish, status va restoranlar ro‘yxatini qabul qilishi kerak.
  - Shartlar: reyting **≥4,6** (majburiy) + **8+ filial** yoki tarmoqdagi birorta filialda **kuniga o‘rtacha 7+ buyurtma** (oylik). Menyu O‘zbekiston qoidalariga ko‘ra rasmiylashtirilishi kerak.
  - Aloqa: vendor-uz@eats.yandex.com. Narx ko‘rsatilmagan.
  - — [yandex.com/promo/eats/uz/rest_faq_integration](https://yandex.com/promo/eats/uz/rest_faq_integration) **[YB]**
- Yandex Eats Toshkentda 2023-yil martda 200 muassasa bilan ishga tushgan. — [qidiruv parchasi, spot.uz/retail.ru](https://www.spot.uz/ru/2023/03/07/yandex-eats/) **[ESKI]**
- **Yandex va Express24:** 2024-yil oktabrda Yandex Uzbekistan Express24 aktivlarining bir qismini sotib olib, yagona platforma qurishni e’lon qilgan. Raqobat qo‘mitasi ikkalasini ustun mavqedagi subyekt deb topgan. — [kun.uz, 25.10.2024](https://m.kun.uz/en/news/2024/10/25/yandex-uzbekistan-expands-with-acquisition-of-express24-assets-plans-to-build-unified-food-delivery-platform); [kun.uz, 26.10.2024](https://jch.kun.uz/en/news/2024/10/26/uzbekistans-food-delivery-market-deemed-monopolized-by-yandex-eats-and-express24)
- **Uzum Tezkor + iiko (2023):** buyurtma oshxonaga tushadi, stop-list avtomatik sinxronlanadi. — [the-tech.kz, 2023](https://the-tech.kz/uzum-tezkor-i-iiko-obyavili-o-strategicheskom-partnerstve-v-uzbekistane/) **[ESKI]**
- **JOWi + Yandex Eats (2023-yil may).** — [spot.uz](https://www.spot.uz/oz/2023/05/10/yandex-eats-jowi/) **[ESKI]**
- **Wolt** 2024-yil oktabrda O‘zbekistonga kirgan va Toshkentda 500 muassasa bilan hamkorlik qilgan. Toshkentda "POS Integration Associate" vakansiyasi bor: POS kompaniyalari bilan integratsiya qurish. — [kun.uz, 07.10.2024](https://kun.uz/en/news/2024/10/07/finnish-tech-giant-wolt-launches-delivery-service-in-uzbekistan); [careers.wolt.com](https://careers.wolt.com/en/jobs/pos-integration-associate/e0068c8)
- **Clopos** integratsiyalari: Uzum Tezkor, Wolt, Yango va boshqalar. — [developer.wolt.com](https://developer.wolt.com/integration-partners/clopos); [clopos.com/uz](https://www.clopos.com/uz)
- **Rahmat** mavjud POSlarga (R-Keeper, JOWi, iiko, Poster) Yandex Eats, Express24 va Uzum Tezkor uchun agregator modulini qo‘shimcha sifatida sotadi. — [rhmt.uz/rest/aggregator](https://rhmt.uz/rest/aggregator/) **[OLDINGI NOTA] [YB]**
- **RAOS:** Uzum va Yandex Go integratsiyalari "ishlab chiqilmoqda". **Biznex** tarif sahifasida agregatorlar yo‘q. — [raos.uz](https://raos.uz/) **[OLDINGI NOTA]**; [biznex.uz/tariflar](https://www.biznex.uz/tariflar)

### Inferences
- **Yandex shartlari** (≥4,6 reyting + tarmoq yoki 7+ buyurtma/kun) kichik choyxona va kafelarni to‘g‘ridan-to‘g‘ri POS integratsiyasidan chetda qoldiradi. Ular buyurtmani planshetdan qo‘lda qayta kiritadi. Rizqim uchun: agregator integratsiyasi asosan tarmoq mijozlari uchun qiymatli. Kichik nuqtalar uchun "agregator planshetidan tez qo‘lda kiritish" yoki Rahmat kabi shlyuz yetarli bo‘lishi mumkin.
- **Yandex API orqali "boshqa tizim" sifatida integratsiya qilish mumkin.** Shuning uchun yangi POS uchun to‘siq texnik emas, balki restoranning reytingi va hajmi.
- **Fiskal sertifikatsiya:** "Sertifikatlangan" POS degan rasmiy ro‘yxat topilmadi. Amalda reyestrdagi virtual kassa yoki fiskal modul (Rahmat/Multikassa, E-POS, REGOS VCR) bilan ulanish ishlatiladi. Fiskal bo‘yicha batafsil: fiskal_background.md.

### Gaps
- Uzum Tezkor va Wolt’ning uchinchi tomon POSlari uchun rasmiy integratsiya talablari va ro‘yxati topilmadi.
- Express24 Yandex platformasiga to‘liq o‘tganmi (2025–2026), tasdiqlanmadi.
- Ofitsial OFD sahifasida POS provayderlari ro‘yxati qidirilmadi.

---

## 3. Restoran egalari nimadan shikoyat qiladi (narx, murakkablik, faqat rus tili, yomon qo‘llab-quvvatlash, o‘zbek tili yo‘qligi, qozon/vazn hisobi yo‘qligi)?

### Takeaway
O‘zbekistonga xos ochiq sharhlar juda kam. Telegram guruhlarini qidirib bo‘lmadi. Hujjatlashtirilgan og‘riqlar:
1. **Fiskal integratsiya talabining noaniqligi va jarima xavfi** (2023).
2. **Kassa va hisobot tizimlari xarajati** (rasmiylashuvga to‘siq sifatida 24%).
3. **Internet va uskuna nosozligi** (chek bermaslik sabablari: 26% va 20%).
4. **Xodimlarning texnik salohiyati pastligi.**
5. **Modullar alohida sotilishi va yashirin to‘lovlar:** fiskal, KDS va modifikatorlar faqat qimmat tariflarda.

O‘zbek tili yo‘qligidan to‘g‘ridan-to‘g‘ri shikoyat topilmadi, lekin hamma mahalliy POSlar o‘zbek tilini asosiy ustunlik sifatida sotadi. Qozon yoki partiya hisobi va osh uchun vazn bo‘yicha sotishni birorta vendor ochiq e’lon qilmagan.

### Cited Findings
- **Chek bermaslik sabablari:** 26% yomon internet, 20% kassa uskunasi nosozligi, 11% bank aloqasi uzilishi, 8% ko‘p vaqt oladi yoki buziladi. — [BMTTD, 2026](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) **[OLDINGI NOTA]**
- **Rasmiylashuvga to‘siqlar:** 24% kassa uskunasi va hisobot tizimlari xarajati, 10% bank xizmatlari to‘lovlari. BMTTD yozadi: kichik korxonalar "xodimlarning texnik salohiyati va vaqti yetarli emasligi" sababli yangi tizimlarni yordamsiz o‘zlashtira olmaydi. — [BMTTD, 2026](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) **[OLDINGI NOTA]**
- **Fiskal integratsiya nizolari:** 2023-yilda soliq organlarining R-Keeper, Jowi va iiko’ni integratsiya qilish talabi tadbirkorlar bilan nizolarga sabab bo‘lgan. Ekspert normani "soliq organlari talqin qilgandek aniq emas" deydi va nizoli holatlar soliq to‘lovchi foydasiga talqin qilinishi kerakligini eslatadi. — [buxgalter.uz, 13.12.2023](https://buxgalter.uz/oz/publish/doc/text195022_soliq_organlarining_uzbekistonning_chakana_savdo_va_umumiy_ovqatlanish_korhonalariga_ularning_hisobga_olish_dasturlarini_soliq_organlari_bilan_integraciya_qilish_tugrisida_talabi_tugrisida) **[ESKI]**
- **Modullar alohida sotiladi:**
  - Biznex’da soliq integratsiyasi, KDS va modifikatorlar faqat Pro’da; o‘rnatish va menyu kiritish alohida pullik. — [biznex.uz/tariflar](https://www.biznex.uz/tariflar)
  - Poster’da modifikatorlar, KDS va mobil ofitsiant faqat Business tarifida; qo‘shimcha kassa va ofitsiant pullik. — [joinposter.com/pricing](https://joinposter.com/pricing)
  - R-Keeper’da StoreHouse alohida. — [itdo.uz](https://itdo.uz/bazaznaniy/iiko-ili-r-keeper/) **[OLDINGI NOTA]**
- **Mahalliy POS marketingi og‘riqlarni nomlaydi:** "Internet o‘chsa, savdo to‘xtaydi", "Buyurtma oshxonaga yetmaydi", "Foyda taxminiy". RAOS raqobatchini "Tizim A’da Soliq.uz integratsiyasi yo‘q, internet talab qiladi" deb tanqid qiladi. — [biznex.uz](https://www.biznex.uz/); [raos.uz](https://raos.uz/) **[OLDINGI NOTA] [YB]**
- **Til:** iiko va R-Keeper dilerlarining saytlari (ITDO, BUSOFT, rkeeper.uz) va reklama maqolalari rus tilida. Faqat Kafesoft (iiko hamkori, Samarqand) e’loni rus **va o‘zbek** tilida 24/7 yordam va’da qiladi. — [itdo.uz](https://itdo.uz/); [spot.uz, 2025](https://www.spot.uz/ru/2025/03/18/r-keeper/); [olx.uz (parcha)](https://www.olx.uz/d/obyavlenie/samarqand-iiko-dasturi-ombor-va-kassa-ID4qhDQ.html) **[ANIQ EMAS]**
- **MDH sharhlari (O‘zbekistonga bog‘lanmagan):**
  - iiko qo‘llab-quvvatlashi "sifatli, lekin sekin";
  - R-Keeper interfeysi eskirgan, tizim yopiq, modullar alohida sotiladi;
  - Poster tarif rejalarini o‘zgartirgani va oldindan to‘lov olgani uchun shikoyat qilingan;
  - JOWi a2is’da 3,7/5, qo‘llab-quvvatlashga ulanish qiyinligidan shikoyat bor.

  — [a2is.ru](https://a2is.ru/catalog/CRM-sistemy-kafe-restoran/iiko/reviews-iiko); [kirov.denvic.ru](https://kirov.denvic.ru/blog/obshchepit/iiko-i-r-keeper-sravnenie-dvukh-samykh-populyarnykh-sistem-avtomatizatsii-obshchepita/); [vidhuk.ua](https://www.vidhuk.ua/poster-pos/review-1482387) **[OLDINGI NOTA] [ANIQ EMAS]**
- **JOWi xavfi:** mahalliy vendorning sayti ishlamay qolishi (jowi.club DNS’da yo‘q, 2026-yil oktabr) mijozlar uchun qo‘llab-quvvatlash va ma’lumot xavfini ko‘rsatadi. Bulut serverlari AQShda bo‘lgan. — [spark.ru](https://spark.ru/startup/jowi-club/blog/22669/o-proekte-jowi-club) **[ESKI]**; o‘z tekshiruvim
- **Vazn va qozon:**
  - Biznex tarif sahifasida vazn bo‘yicha sotish va partiya yoki qozon ishlab chiqarish **eslatilmagan**. — [biznex.uz/tariflar](https://www.biznex.uz/tariflar)
  - Paloma365’da "porsiya hisobi" faqat Universal tarifda. — [paloma365.uz/tariffs](https://paloma365.uz/tariffs) **[OLDINGI NOTA]**
  - O‘zbekcha va ruscha qidiruvda "osh markazi, qozon, tarozi, kassa dasturi" bo‘yicha birorta POS taklifi topilmadi.
- **Tarozi muvofiqligi:** iste’molchilar huquqlari agentligi bozorlarda ~6 000 tarozini tekshirgan, ulardan 1 474 tasida o‘z vaqtida tekshiruvdan o‘tganlik belgisi yo‘q bo‘lgan. Vazn bo‘yicha sotishda qiyoslangan tarozi talab qilinishi muhim. — [yuz.uz](https://yuz.uz/uz/news/bozorlarda-nosoz-va-korikdan-otmagan-tarozilar-aniqlandi) (sana aniqlanmadi) **[ANIQ EMAS]**

### Inferences
- Asosiy shikoyatlar tartibi: **narx va yashirin qo‘shimchalar > internet va uskuna barqarorligi > fiskal noaniqlik va jarima qo‘rquvi > qo‘llab-quvvatlash sifati va tili**. Rus tili muammosi asosan viloyatlarda va oddiy xodimlarda (ofitsiant, kassir) seziladi, deb taxmin qilinadi. Bu intervyu bilan tekshirilishi kerak.
- **Qozon va osh logikasi** ochiq bo‘shliq. Unga quyidagilar kiradi:
  - bir qozondan N porsiya yoki kg chiqishi;
  - "osh tugadi" stop-listi;
  - porsiya yoki kg bo‘yicha sotish;
  - choyxonada kishi boshiga hisob.

  iiko va R-Keeper umumiy ishlab chiqarish akti va vazn bo‘yicha taomlarni qo‘llab-quvvatlaydi, deb taxmin qilinadi (umumiy bilim, bu tadqiqotda tasdiqlanmadi). Lekin buni osh markazi uchun sozlash integratorni talab qiladi.
- **Agregator integratsiyasi** kichik nuqtalar uchun ko‘pincha mavjud emas (Yandex shartlari, 2-bo‘lim). Bu kichik egalar uchun "qo‘lda qayta kiritish" og‘rig‘ini saqlab qoladi.

### Gaps
- Telegram restoranchilar guruhlaridagi (masalan, SBG’ning "клуб HoReCa"si) va YouTube’dagi o‘zbekcha sharhlar to‘planmadi, chunki Telegram ichida qidirish imkoni yo‘q.
- iiko yoki R-Keeper’ning o‘zbek tilidagi kassir interfeysi bor-yo‘qligi birlamchi manba bilan tasdiqlanmadi.
- Osh markazlari (Beshqozon, Markaziy Osh Markazi va boshqalar) qanday dastur ishlatishi topilmadi.

---

## 4. O‘zbekistondagi ovqatlanish korxonalari soni va bozor hajmi bo‘yicha ochiq ma’lumotlar

### Takeaway
Rasmiy statistika faqat **"yashash va ovqatlanish xizmatlari"** (OKED I bo‘limi) bo‘yicha bor, ya’ni mehmonxonalar ham shunga kiradi. Raqamlar ta’rifga qarab farq qiladi:
- **ro‘yxatdan o‘tgan:** 47,4 ming (12.2024) → **43,8 ming** (12.2025);
- **faoliyat yuritayotgan:** ~30,4 ming (01.01.2025) → **~29,7 ming** (01.11.2025);
- **Soliq qo‘mitasi:** 25 mingdan ortiq yuridik shaxs umumiy ovqatlanish xizmatini ko‘rsatadi.

Yakka tartibdagi tadbirkorlar va norasmiy choyxonalar bu sonlarga to‘liq kirmaydi.

### Cited Findings
- **Ro‘yxatdan o‘tgan korxonalar** (yashash va ovqatlanish): 47,4 ming (2024-yil dekabr) → 43,8 ming (2025-yil dekabr), ya’ni ~7,6% kamaygan. Manba: Statistika qo‘mitasi (siat.stat.uz, 2075-jadval). — [kursiv.media, 22.01.2026](https://uz.kursiv.media/uz/2026-01-22/restoran-biznesida-2025-yildagi-ozgarishlar-tahlil-va-prognozlar/)
- **Faoliyat yuritayotgan birliklar** (yashash va ovqatlanish): 29 717 (01.11.2025), 30 374 (01.01.2025). Jami faoliyat yuritayotgan korxonalar shu davrda 465,9 ming bo‘lgan. — stat.uz press-relizi (qidiruv parchasi, aniq sahifa ochilmadi): [stat.uz yangiliklari](https://stat.uz/ru/press-tsentr/novosti-goskomstata/67324-zbekistonda-576-8-mingta-korkhona-faoliyat-yuritmo-da-4) **[ANIQ EMAS]**
- **Jami korxonalar:** 01.04.2026 holatiga 577,2 ming korxona va tashkilot faoliyat yuritadi (+7,7% yillik). — [stat.uz](https://stat.uz/ru/press-tsentr/novosti-goskomstata/67324-zbekistonda-576-8-mingta-korkhona-faoliyat-yuritmo-da-4)
- **Soliq qo‘mitasi ma’lumoti:** 25 mingdan ortiq yuridik shaxs umumiy ovqatlanish xizmatini ko‘rsatadi. — [BMTTD, 2026](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf) **[OLDINGI NOTA]**
- **Bozor hajmi:** 2023-yilda oziq-ovqat va ichimliklar bilan ta’minlash bo‘yicha bozor xizmatlari hajmi **14,8 trln so‘m** bo‘lgan. — stat.uz (qidiruv parchasi) [stat.uz](https://stat.uz/ru/press-tsentr/novosti-goskomstata/51043-oziq-ovqat-va-ichi-mliklar-bil-an-ta-minlash-bo-yicha-xizmatlar-hajmi-5) **[ESKI] [ANIQ EMAS]**
- **Soliq tushumi:** 2025-yilda umumiy ovqatlanish korxonalari **1,7 trln so‘mdan** ortiq soliq to‘lagan. Eng yirik soliq to‘lovchi: International Foodchain (KFC franshizasi). — [kursiv.media, 12.03.2026](https://uz.kursiv.media/uz/2026-03-12/otgan-yilda-umumiy-ovqatlanish-sohasida-eng-kop-soliq-tolagan-kompaniyalar-malum-boldi/amp/)
- **Toshkent shahri** (2025-yil avgust, ro‘yxat): "yashash va ovqatlanish" sohasida xorijiy kapitalli korxonalar 545 ta (98 ta qo‘shma, 447 ta xorijiy). — [toshstat.uz](https://www.toshstat.uz/2025/Registr/august.pdf)
- **Yetkazib berish platformalari:**
  - **Yandex Go Eats:** 3 000+ faol restoran (bir yilda +1 000), 14 shahar. Hamkorlar 2025-yil 1-yarmida **340 mln $+** ishlagan (+50% yillik). — [kursiv.media, 22.01.2026](https://uz.kursiv.media/uz/2026-01-22/restoran-biznesida-2025-yildagi-ozgarishlar-tahlil-va-prognozlar/) (Yandex’ning LinkedIn statistikasiga tayanib) **[YB]**
  - **Uzum Tezkor:** 25 shahar; 2025-yil aprelda 2 600 hamkor. — [uzum.com](https://uzum.com/en/press-center/news-and-press-releases/25cities-2600partners-7000couriers-uzum-tezkor-continues-regional-expansion/); [kursiv.media](https://uz.kursiv.media/uz/2026-01-22/restoran-biznesida-2025-yildagi-ozgarishlar-tahlil-va-prognozlar/)
  - **Wolt:** Toshkentda 500 muassasa (ishga tushganda, 2024-yil oktabr). — [kun.uz](https://kun.uz/en/news/2024/10/07/finnish-tech-giant-wolt-launches-delivery-service-in-uzbekistan)
- **Tarmoqlar:**
  - KFC 2025-yilda daromadda EVOS’dan o‘zib ketgan (daromadi 1 trln so‘mdan oshgan, +36%; EVOS −3,1%). — [anhor.uz](https://anhor.uz/news/na-rynke/) (iHamkor.uz ma’lumoti)
  - Burger King (2025) va Domino’s (2024) bozorga kirgan. — [kursiv.media](https://uz.kursiv.media/uz/2026-01-22/restoran-biznesida-2025-yildagi-ozgarishlar-tahlil-va-prognozlar/)

### Inferences
- **Rizqim uchun manzil bozor (TAM):** ~25–30 ming faol yuridik shaxs, bunga YaTT va norasmiy choyxonalar qo‘shiladi.
  - BMTTD’ning 36% ko‘rsatkichi qo‘llansa, ~16–19 ming korxonada hali dastur yo‘q.
  - Hisob: 29,7k × 0,64 ≈ 19k; 25k × 0,64 ≈ 16k. Mehmonxonalar ulushi chiqarilmagan, shuning uchun bu yuqori chegara.
- Yandex’ning 3 000+ va Uzum’ning 2 600 hamkori, ya’ni ular kesishgan holda, faol yetkazib beradigan restoranlar ~3–5 ming ekanini bildiradi. Bu agregator integratsiyasi kerak bo‘ladigan segmentning taxminiy hajmi.
- Korxonalar sonining kamayishi (−7,6%) bilan tarmoqlarning o‘sishi birga kechmoqda. Bu konsolidatsiyani bildiradi, demak ko‘p filialli funksiyalarga talab ortadi.

### Gaps
- Faqat OKED 56 ("oziq-ovqat va ichimliklar bilan ta’minlash") bo‘yicha korxonalar soni va formatlar bo‘yicha taqsimot (choyxona, kafe, restoran, fast food) ochiq jadvalda topilmadi. siat.stat.uz’ning 2075-jadvalini to‘g‘ridan-to‘g‘ri yuklab tekshirish kerak.
- 2024–2025-yillar bo‘yicha umumiy ovqatlanish bozor hajmi (so‘mda) topilmadi, faqat 2023-yilgi 14,8 trln so‘m bor.
- POS bozorining hajmi (vendorlar daromadi, o‘rnatilgan terminallar soni) bo‘yicha birorta manba yo‘q.
