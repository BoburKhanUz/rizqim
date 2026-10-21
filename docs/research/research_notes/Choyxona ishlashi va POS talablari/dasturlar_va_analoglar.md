# Choyxonalar qaysi dasturlardan foydalanadi va analog bizneslardagi POS funksiyalari (xona, vaqt, guruh hisobi)

**Sana:** 2026-yil 21-oktabr. **Qamrov:** foydalanish bo‘yicha O‘zbekiston (va Chaihona №1 Rossiyada); analoglar global. Bu fayl `Ovqatlanish formatlari POS talablari/pos_raqobatchilar.md` ni chuqurlashtiradi. U yerdagi narx va umumiy bozor ma’lumotlari bu yerda takrorlanmaydi.

**Belgilar:**
- **[YB]**: yetkazib beruvchining o‘z da’vosi.
- **[ESKI]**: 2024-yildan oldingi manba.
- **[XULOSA]**: mening xulosam, manbada to‘g‘ridan-to‘g‘ri aytilmagan.
- **[ANIQ EMAS]**: faqat qidiruv parchasi yoki manbalar zid.

**Texnik eslatma:** 2026-yil 21-oktabrda jowi.club hamon ochilmadi (oldingi nota). Alipos funksiyalar sahifasi bu sessiyada ham topilmadi. Telegram ichida qidirish imkoni yo‘q edi, shuning uchun faqat ochiq `t.me/s/` sahifalari o‘qildi.

---

## 1. O‘zbekiston choyxonalari (va Chaihona №1) qaysi dasturlardan foydalanadi? "Choyxona dasturi" deb sotiladigan mahsulot bormi?

### Takeaway
Birorta O‘zbekiston choyxonasining qaysi POSda ishlashini tasdiqlovchi ochiq manba (keys, mijozlar ro‘yxati, sharh) topilmadi.

Faqat choyxona uchun mo‘ljallangan dastur ham yo‘q. Choyxonani maqsadli segment sifatida tilga oladigan uchta mahalliy mahsulot bor: **Biznex**, **BROS** va **VelionPOS**. Hech birida choyxonaga xos funksiya (xona, joy haqi, vaqt) e’lon qilinmagan.

Rossiyadagi eng yirik chaikhana tarmog‘i **Chaihona №1 R-Keeper 7 + StoreHouse** ishlatadi (2025-yilgi vakansiya). Topilgan choyxonalarda bron faqat **telefon** orqali qilinadi.

### Cited Findings

**"Choyxona"ni nomlagan mahalliy POSlar**
- **Biznex** meta-tavsifi: "Restoran, kafe va choyxonalar uchun kassa va boshqaruv tizimi". Demo so‘rov formasida "Tez ovqat yoki choyxona" biznes turi bor. Choyxonaga alohida bo‘lim yo‘q. — [biznex.uz](https://www.biznex.uz/) **[YB]**
- **BROS** o‘zini "Restoran, choyxona, kafe va oshxonalar uchun innovatsion avtomatlashtirish dasturi" deb ataydi.
  - Funksiyalari: QR-menyu, stol buyurtmasi, ofitsiant ilovasi, BROS POS, oshxonaga yo‘naltirish, admin panel.
  - Narx: kuniga 50 tadan kam buyurtma bo‘lsa **15 $/oy**, undan ko‘pi kelishiladi.
  - Ishlab chiqaruvchi kompaniya nomi ko‘rsatilmagan ("© 2025 BROS", "BATO Help" Telegram chati). Mijozlar ro‘yxati yo‘q.
  - — [bros.uz](https://bros.uz/) **[YB]**
- **VelionPOS** OLX’da asosan do‘kon kassasi sifatida e’lon qilingan: **199 000 so‘m/oy + 499 000 so‘m o‘rnatish**. Choyxona yoki xona broni funksiyasi ko‘rsatilmagan. Bu ma’lumot "choyxona dasturi" qidiruvida chiqqan qidiruv xulosasidan olingan. — [olx.uz e’loni](https://www.olx.uz/d/oz/obyavlenie/dokon-uchun-qulash-pos-dastur-ID4r2iS.html) **[ANIQ EMAS]**
- **Dastur-xon** (O‘zbekistonda ishlab chiqilgan) restoran va kafe uchun mo‘ljallangan, choyxona so‘zi ishlatilmagan. — [dastur-xon.uz](https://dastur-xon.uz/) **[YB]**
- **Mison:** App Store’da ishlab chiquvchi "AUTOMATION SOURCE, MChJ" deb ko‘rsatilgan. MChJ o‘zbek huquqiy shakli, ya’ni mahalliy vendor bo‘lishi ehtimoli bor. Ilova restoran va oshxona hisobotlarini ko‘rsatadi, "Mison Waiter" esa ofitsiant ilovasi. Choyxona funksiyalari haqida ma’lumot yo‘q. — [apps.apple.com/id6737459053](https://apps.apple.com/us/app/-/id6737459053); [Mison Waiter](https://apps.apple.com/us/app/-/id6471659282) **[ANIQ EMAS]**
- **Reca** va **Joyda** nomli O‘zbekiston POSlari bo‘yicha birorta natija topilmadi.
- Ruscha "программа для чайханы автоматизация" qidiruvi chaikhanaga ixtisoslashgan birorta mahsulot bermadi, faqat umumiy kafe dasturlari chiqdi. — [qidiruv natijalari: saby.ru](https://saby.ru/articles/presto/avtomatizaciya_restorana_i_kafe), [olx.uz umumiy e’lon](https://www.olx.uz/d/obyavlenie/kafe-va-restoranlarni-avtomatlashtirish-avtomatizatsiya-restaranov-ID4pal0.html)

**Havaskor va buyurtma asosidagi yechimlar (GitHub)**
- "Xayitboy ota CHOYXONA uchun Telegram ovqat buyurtma boti": ovqat buyurtmasi uchun, xona broni uchun emas. — [github.com/miraxmedovxoshimjon69-arch/choyxona-bot](https://github.com/miraxmedovxoshimjon69-arch/choyxona-bot)
- "Tuyxona": to‘yxona broni uchun grammY asosidagi Telegram bot, telefon raqamini tasdiqlab ro‘yxatdan o‘tkazadi. Choyxona emas, lekin "zal/xona sanasi bo‘yicha bron" mantig‘i o‘xshash. — [github.com/CoderQudratov/Tuyxona](https://github.com/CoderQudratov/Tuyxona)

**Toshkent choyxonalari amalda**
- **"The Choyxona"** (Bog‘ishamol): 8–16 kishilik 13 ta kabinka va hovli hamda yozgi oshxonasi bor 22 kishilik kabina ijaraga beriladi. — [afisha.uz, 02.02.2023](https://www.afisha.uz/uz/restaurants/2023/02/02/the-choyxona/) **[ESKI]**
- "The Choyxona" Telegram kanalida bron faqat telefon orqali: "Бронируйте кабинку заранее: 99 844 88 88". Kanal sahifasida xona narxi, minimal buyurtma, depozit yoki xizmat foizi ko‘rsatilmagan. — [t.me/s/thechoyxona](https://t.me/s/thechoyxona)
- **Shosh choyxona** (Yunusobod) Telegram kanalida faqat telefon raqamlari bor, bron boti yo‘q. — [t.me/shosh_choyxona](https://t.me/shosh_choyxona)

**Chaihona №1 (Moskva)**
- Moskvadagi "Специалист по работе R-keeper" vakansiyasi (01.07.2025, maosh 150 000 ₽ dan). Vazifalari:
  - **R-Keeper 7** texnik yordami;
  - **R-Keeper va StoreHouse**’ni o‘rnatish va xizmat ko‘rsatish;
  - chegirma kartalari tizimi;
  - KKT, EGAIS va "Честный знак".

  Yetkazish yoki bron modullari tilga olinmagan. — [dreamjob.ru, 01.07.2025](https://dreamjob.ru/employers/289735/vakansii/122277006)
- Chaihona №1 Easy (TC "Mega") ofitsiant vakansiyasi majburiyatlarida "R-Keeper bilan ishlash" bor. — [restojob.ru (qidiruv parchasi)](https://restojob.ru/employer/vacancy/vacancy-view/100779/) **[ANIQ EMAS]**
- hh.ru’dagi 2026-yilgi ofitsiant vakansiyalari (Yangi Arbat, Mayakovskaya) ham topildi. Qidiruv xulosasi ularning "ключевые навыки"da R-Keeper borligini aytdi, lekin men ochgan sahifada ko‘nikmalar bo‘limi ko‘rinmadi (13.04.2026 dan arxivda). — [hh.ru/vacancy/131623993](https://hh.ru/vacancy/131623993) **[ANIQ EMAS]**
- Chaihona №1’ning O‘zbekistondagi filiallari va ularning POSi haqida manba topilmadi.

### Inferences
- **[XULOSA]** "Choyxona dasturi" bozori bo‘sh. Vendorlar choyxonani faqat SEO yoki segment so‘zi sifatida ishlatadi, xona, joy haqi yoki vaqt hisobi funksiyalarini e’lon qilmaydi. Choyxonalarning katta qismi daftar yoki telefon bron (va ehtimol umumiy kafe POSi) bilan ishlaydi, deb taxmin qilinadi. Daftar va Excel ulushi bo‘yicha ochiq ma’lumot yo‘q, buni intervyu bilan tekshirish kerak.
- **[XULOSA]** Chaihona №1 (Rossiyadagi eng katta chaikhana tarmog‘i) maxsus dastur emas, umumiy restoran POSi (R-Keeper) ishlatadi. Bu katta chaikhana tarmoqlari kabinka va guruh hisobini umumiy "stol/zal + bron + depozit" modeli orqali hal qilishini bildiradi.

### Gaps
- O‘zbekistondagi birorta choyxona yoki osh markazining POS nomi tasdiqlanmadi. Bunga Telegram guruhlari, YouTube va dala intervyusi kerak.
- iiko, R-Keeper, Poster, Jowi, Alipos va Clopos’ning O‘zbekistondagi mijozlar ro‘yxatlarida choyxona bor-yo‘qligi topilmadi.
- Reca, Joyda va Mison haqida batafsil ma’lumot topilmadi.

---

## 2. Mahalliy POSlarda choyxonaga kerakli funksiyalar bormi (xona, joy haqi, xizmat %, depozit, uzoq ochiq hisob, chekni bo‘lish, nasiya, bron kalendari)?

### Takeaway
Ochiq e’lon qilingan funksiyalar bo‘yicha eng mosi **Biznex**. Unda bron, chekni bo‘lish, qarzga sotuv (nasiya), offlayn ishlash va bitta stolda bir nechta hisob bor.

**Dastur-xon**da xizmat foizi bor, lekin u faqat bulutda ishlaydi. Hech bir mahalliy POS **xona uchun soatlik to‘lov, kishi boshiga joy haqi, minimal buyurtma yoki depozitni hisobdan ayirish** funksiyalarini ochiq e’lon qilmagan. iiko (va Paloma365 KZ) vaqtga asoslangan hisobni qo‘llab-quvvatlaydi (3-bo‘limga qarang).

### Cited Findings

**Funksiyalar matritsasi (vendor saytlari bo‘yicha) [YB]**

| Funksiya | Biznex | Dastur-xon | BROS | iiko (Uz’da ITDO/Kafesoft orqali) |
|---|---|---|---|---|
| Zal/stol sxemasi | Ha ("Zal va stollar chizmasi, stol bandligi") | Ko‘rsatilmagan | Stol buyurtmasi | Ha |
| Xona/kabinka obyekti | Ko‘rsatilmagan | Ko‘rsatilmagan | Ko‘rsatilmagan | Stol sifatida modellashtiriladi |
| Bron | Ha ("Bron") | Yo‘q | "Stol bron yoki buyurtma" (sayt/ilova) | Ha (Restoplace va boshqalar bilan integratsiya) |
| Xizmat % | Ko‘rsatilmagan | Ha ("xizmat foizi", avtomatik) | Ko‘rsatilmagan | Ha [XULOSA, umumiy bilim] |
| Kishi boshiga joy haqi | Ko‘rsatilmagan | Ko‘rsatilmagan | Ko‘rsatilmagan | Ko‘rsatilmagan |
| Vaqt bo‘yicha to‘lov | Ko‘rsatilmagan | Ko‘rsatilmagan | Ko‘rsatilmagan | **Ha** (tarifikatsiya) |
| Depozit va oldindan to‘lov | Ko‘rsatilmagan | Ko‘rsatilmagan | Ko‘rsatilmagan | Ha (kalyanxona yechimi: "предоплата") |
| Chekni bo‘lish | Ha ("Chekni bo‘lish, chegirma, qaytarish") | Ko‘rsatilmagan | Ko‘rsatilmagan | Ha |
| Nasiya/qarz | Ha ("qarzga sotuv") | Ko‘rsatilmagan | Ko‘rsatilmagan | — |
| Offlayn | Ha ("Internet o‘chsa ham savdo to‘xtamaydi") | Yo‘q (bulut) | Ko‘rsatilmagan | Ha (lokal server) |
| Narx | 349k / 549k so‘m/oy (oldingi nota) | 99k / 399k so‘m/oy | 15 $/oy (<50 buyurtma/kun) | 54–108 $/oy (ITDO, oldingi nota) |

Manbalar: [biznex.uz](https://www.biznex.uz/); [dastur-xon.uz](https://dastur-xon.uz/); [bros.uz](https://bros.uz/); [iiko vaqt tarifikatsiyasi](https://iiko.github.io/front.api.doc/v6/ru/TimeRatedServices.html); [kafesoft.kz, iiko kalyanxona (qidiruv parchasi)](https://kafesoft.kz/typical/avtomatizacziya-kalyannyh-na-iiko/)

**Qo‘shimcha tafsilotlar**
- **Biznex:**
  - Bitta stolda bir nechta hisob ochish mumkin.
  - To‘lov turlari: "Naqd, karta, o‘tkazma, Payme, Click, qarzga sotuv".
  - — [biznex.uz](https://www.biznex.uz/) **[YB]**
- **Dastur-xon:**
  - Oddiy telefonlarda ishlaydi, 58 mm Bluetooth printer ixtiyoriy, Telegram-bot orqali boshqariladi.
  - Ofitsiant xizmat foizi Biznes tarifida avtomatik hisoblanadi.
  - O‘zini "E-cafe’ning yarmi" narxida deb taqqoslaydi, bu E-cafe degan yana bir raqobatchi borligini ko‘rsatadi.
  - 3 kun bepul.
  - — [dastur-xon.uz](https://dastur-xon.uz/) **[YB]**
- **Clopos:** ofitsiant ilovasi, KDS, QR-menyu va E-Kassa integratsiyasi (Ozarbayjon). Choyxona funksiyalari ko‘rsatilmagan. — [clopos.com](https://www.clopos.com/en/restaurant-pos-system) **[YB]**
- **Alipos:** vakansiya e’loniga ko‘ra kassa, buyurtma, ombor va hisobot tizimi. Rasmiy funksiyalar sahifasi topilmadi. — qidiruv parchasi **[ANIQ EMAS]**

### Inferences
- **[XULOSA]** Choyxonaning asosiy "pul formulasi" (joy yoki xona haqi + taomlar + xizmat % − oldindan to‘lov; ko‘pincha kechqurun 4–6 soat ochiq turadigan guruh hisobi) mahalliy POSlarda **tayyor holda yo‘q**. Uni "maxsus taom pozitsiyasi" yoki qo‘lda chegirma bilan aylanib o‘tish kerak bo‘ladi.

  Rizqim uchun ajralib turadigan funksiyalar:
  - xona obyekti (sig‘im, minimal summa, soatlik yoki qat’iy narx);
  - kishi boshiga joy haqi;
  - depozitni avtomatik hisobdan ayirish;
  - nasiya (Biznex’da bor, demak gigiyena darajasidagi talab).
- **[XULOSA]** Nasiya va offlayn ishlash O‘zbekiston mahalliy POSlarida standartga aylanmoqda (Biznex). Choyxona POSida bular majburiy.

### Gaps
- Jowi, Alipos, Mison, Clopos (Uz) va Poster (Uz) funksiyalarini choyxona nuqtai nazaridan tekshirib bo‘lmadi: saytlar ochilmadi yoki ma’lumot yo‘q.
- Mahalliy POSlarning hech birida "joy haqi" (kishi boshiga) sozlamasi borligi tasdiqlanmadi. Buni demo so‘rab tekshirish kerak.

---

## 3. O‘zbekistonda bron vositalari (Telegram botlar, Yandex/2GIS, bron servislari) — choyxonalar ulardan foydalanadimi?

### Takeaway
2026-yilda Toshkentda onlayn bron rivojlanmoqda:
- **Yandex Go "Куда сходить" pilot**, 2026-yil fevral, 10+ restoran;
- **2GIS** dagi "Забронировать столик" tugmasi;
- **resto.uz**;
- Joyme/resttab.

Lekin topilgan hech bir manbada choyxona bu kanallarga ulangani ko‘rsatilmagan. Choyxonalar bronni **telefon** orqali qabul qiladi, Telegram kanallar faqat raqamni e’lon qiladi.

### Cited Findings
- **Yandex Go** "Куда сходить" bo‘limida "Бронь" tugmasi: 10+ muassasa (Zira, Benedict, Lali, "Южанин", Steak House "Мясной", Pro.Хинкали), ro‘yxat kengaytirilishi aytilgan. Keyinchalik bron Yandex Kartalar orqali ham mumkin bo‘ladi. Choyxona va POS integratsiyasi haqida gap yo‘q. — [repost.uz, 13.02.2026](https://repost.uz/ray-dlya-introvertov)
- **2GIS** Toshkent restoran kartochkalarida "Забронировать столик" tugmasi bor. — [2gis.uz](https://2gis.uz/tashkent/search/%D0%A2%D0%B0%D1%88%D0%BA%D0%B5%D0%BD%D1%82%20%D1%80%D0%B5%D1%81%D1%82%D0%BE%D1%80%D0%B0%D0%BD) **[ANIQ EMAS: qidiruv parchasi]**
- **resto.uz:** Toshkent va O‘zbekiston bo‘yicha ovqatlanish joylarini qidirish platformasi. — [resto.uz](https://resto.uz/)
- **Joyme (resttab.com):** onlayn bron servisi, o‘rtacha chek so‘mda ko‘rsatiladi ("Ср. чек: 200,000 UZS"). — [resttab.com](https://resttab.com/) **[ANIQ EMAS]**
- **Restoplace** (Rossiya) narx sahifasi rublda, O‘zbekistonga moslashtirilgani ko‘rinmadi. — [restoplace.cc/pricing](https://restoplace.cc/pricing)
- Choyxonalarda bron kanali sifatida faqat telefon topildi ("The Choyxona", "Shosh"). — [t.me/s/thechoyxona](https://t.me/s/thechoyxona); [t.me/shosh_choyxona](https://t.me/shosh_choyxona)
- "Restolink" nomli O‘zbekiston bron servisi topilmadi.

### Inferences
- **[XULOSA]** Choyxona broni hozircha "telefon + administrator daftari" darajasida. Yandex va 2GIS bronlari restoranlar bilan boshlanmoqda. Rizqim uchun imkoniyat: **Telegram-bot yoki Mini App orqali xona broni** + POS’dagi xona kalendari, depozitni Payme yoki Click orqali olish. Kelajakda Yandex yoki 2GIS bron kanallari bilan integratsiya qilish mumkin.

### Gaps
- Yandex va 2GIS bron API’lari uchinchi tomon POSlari uchun ochiqmi, topilmadi.
- Choyxona egalarining bron kanallari bo‘yicha so‘rov yoki statistik ma’lumot yo‘q.

---

## 4. Analog bizneslarning dasturlari xona/kabinka vaqti, soatlik haq, minimal summa, depozit, guruh hisobini qanday modellashtiradi?

### Takeaway
Eng yetuk model **iiko "повременные услуги"**. Unda:
- xizmat stolga bog‘lanadi;
- start/stop qilinadi va pauza vaqtida haq olinmaydi;
- haq qadam bo‘yicha yuqoriga yaxlitlanadi;
- minimal davomiylik va oldindan bron qilingan limit bor;
- kun vaqti yoki davomiylikka qarab tarif avtomatik almashadi;
- prechek yoki to‘lovda xizmat avtomatik to‘xtaydi.

Bron tomonda Restoplace depozitlarni **kishi boshiga, soat bo‘yicha va hafta kuni yoki vaqtga qarab** olishni beradi. Square × SevenRooms bron depozitini yakuniy hisobga **avtomatik ayiradi**. **Minimal summa (minimum spend)** modelini birorta POS hujjatida aniq topmadim, u asosan joy qoidasi sifatida yashaydi.

### Cited Findings

**iiko: vaqtga asoslangan xizmatlar (iikoFront API, v6)**
- **Start/stop:** xizmat start va stop qilinadi; "Когда услуга остановлена, отсчёт времени приостанавливается, эти периоды не оплачиваются".
- **Yaxlitlash:** vaqt `TimingStep` ga karrali qilib yuqoriga yaxlitlanadi; masalan, soatlik tarifda 70 daqiqa 2 soat deb hisoblanadi. "Оплачивается округлённое время, но запоминается фактическое".
- **Minimal va maksimal vaqt:** `MinimumDuration` qisqa seansni minimal haq bilan hisoblaydi. Oldindan bron qilingan `TimeLimit` to‘liq to‘lanadi, limitga yetganda xizmat avtomatik to‘xtaydi. Limit bo‘lmasa maksimum 12 soat. `RemainingLimitCost` qolgan limit qiymatini ko‘rsatadi.
- **Tariflar:** `TimingMode` kun vaqti yoki davomiylikka bog‘lanadi. Tarif avtomatik almashadi va har bir tarif davri alohida narxlanadi.
- **Stolga bog‘lanish:** bir stolda bir vaqtda faqat bitta xizmat ishlaydi. Start/stop tashqi qurilmani (chiroqni) boshqarishi mumkin. "При пречеке или оплате заказа услуга остановится автоматически".
- — [iiko.github.io, TimeRatedServices](https://iiko.github.io/front.api.doc/v6/ru/TimeRatedServices.html)
- iiko qo‘llanmasi bo‘yicha har bir xizmat joyi zal sxemasida alohida stol bo‘lishi va kontrollerga bog‘lanishi kerak; tariflar kun vaqti va hafta kuniga qarab o‘zgaradi; vaqt "по факту" yoki "по лимиту" hisoblanadi. iiko Relay qurilmasi bilyard chirog‘i yoki karaokeni yoqadi. — [rapid.iiko.ru, UG_iiko_tarification.pdf](https://rapid.iiko.ru/userguide/Special_solutions/UG_iiko_tarification.pdf) **[ESKI, 2015]**
- Tarifikatsiya funksiyasi iiko’ning hamma tariflarida bo‘lmasligi mumkin. — [iresto.ru/iiko](https://iresto.ru/iiko/) **[ANIQ EMAS]**

**Paloma365 (Qozog‘iston): vaqt bo‘yicha to‘lov**
- Narx kun vaqti, hafta kuni va davomiylikka qarab farqlanadi ("Разные цены по времени суток, дням недели, длительности").
- Hisob turlari: qat’iy vaqt, taymer yoki ma’lum summagacha. Antikafe uchun daqiqa, soat yoki summa bo‘yicha.
- Karaoke kabinkalari soatlik ijaraga beriladi. Bronda depozit va oldindan to‘lov olinadi.
- Kabinkalar, xonalar va zonalar band/bo‘sh statusi bilan vizual xaritada ko‘rinadi, bunga banya va sauna kabinkalari ham kiradi.
- Bar va oshxona buyurtmalari seans vaqti bilan bitta chekka tushadi.
- Narx: yillik to‘lovda **4 590 ₸/oy dan**, 15 kun bepul.
- Yaxlitlash, minimal vaqt va pauza haqida sahifada gap yo‘q.
- — [paloma365.kz/for-time-pricing](https://paloma365.kz/for-time-pricing) **[YB]**

**Restoplace (kalyanxonalar uchun bron)**
- Soatlik bron (qadamini sozlash mumkin).
- Depozitlar "за человека, за часы, разные депозиты на разные дни недели и время дня".
- Oldindan to‘lov restoranning hisob raqamiga tushadi.
- Saytda real vaqtda bo‘sh stolni ko‘rsatadigan vidjet, SMS va messenjer eslatmalari.
- Integratsiyalar: iiko, r_keeper, CRM, IP-telefoniya, API.
- Minimal summa tilga olinmagan. Bepul ("Фри") tarif va PRO+ uchun 14 kunlik sinov bor.
- — [restoplace.cc/servis-dlya-kalyannoy](https://restoplace.cc/servis-dlya-kalyannoy) **[YB]**

**Boshqa kalyanxona yechimlari (Rossiya va Qozog‘iston)**
- **Chimitdorzhi:** depozitli stol broni, real vaqtdagi o‘tirish statusi, kalyan kartasi va sodiqlik dasturi. Soatlik to‘lov ko‘rinmaydi. — [chimitdorzhi.tech](https://chimitdorzhi.tech/predlozheniya/kalyannaya-launzh/) **[YB] [ANIQ EMAS: qidiruv parchasi]**
- **FusionPOS:** kalyan va sarf materiallari hisobi. — [fusionpos.ru/solution/kalyannaya](https://fusionpos.ru/solution/kalyannaya/) **[YB] [ANIQ EMAS]**
- **Kafesoft.kz:** iiko asosida kalyanxonani avtomatlashtirish; oldindan to‘lov, aralash to‘lov usullari va hisob chiqarish. — [kafesoft.kz](https://kafesoft.kz/typical/avtomatizacziya-kalyannyh-na-iiko/) **[YB] [ANIQ EMAS: qidiruv parchasi]**
- **Poster:** ro‘yxatdan o‘tishda "Hookah bar" biznes turi bor, lekin vaqt taymeri yoki soatlik xizmat hujjatlarda topilmadi. — [help.joinposter.com](https://help.joinposter.com/en/articles/5326173-how-to-create-a-poster-pos-account) **[ANIQ EMAS]**

**Odoo POS modullari (global, ochiq bozor)**
- "eg_pos_time_based_products": taymer to‘g‘ridan-to‘g‘ri POS chek qatorida; kassir start/stop qiladi, narx seans tugagach hisoblanadi. — [apps.odoo.com](https://apps.odoo.com/apps/modules/17.0/eg_pos_time_based_products)
- "pos_gaming_zone_management": stansiya bo‘yicha taymer, har bir buyurtma 1 soatga karrali hisoblanadi; buyurtmani stollar orasida ko‘chirish; qat’iy seans tugashi haqida bildirishnoma. — [apps.odoo.com](https://apps.odoo.com/apps/modules/15.0/pos_gaming_zone_management) **[ANIQ EMAS: qidiruv parchasi]**

**Sauna va banya**
- **1C "Управление кафетерием и сауной":** mijoz, sana va vaqt bo‘yicha sauna broni, grafik ko‘rinishi; kesishadigan vaqtga bron qilib bo‘lmaydi. Kafe savdosi bilan birga ishlaydi. — [infostart.ru](https://infostart.ru/public/236008/) **[ESKI]**
- **USU (Qozog‘iston):** banya va saunani buyurtma asosida avtomatlashtirish. — [usu.kz](https://www.usu.kz/langs/en/sauna/sauna_automation.php) **[YB]**

**Depozitni hisobdan ayirish va minimal summa**
- **Square × SevenRooms:** bron depozitini yakuniy hisobga avtomatik qo‘llash mumkin. Buning uchun "Seated Status" yoqilgan bo‘lishi kerak, aks holda POS’da buyurtma qo‘lda yaratiladi va depozit qo‘lda qo‘llanadi. Qaytarish SevenRooms orqali; choychaqa depozit ayirilmagan to‘liq summadan hisoblanadi. — [community.squareup.com](https://community.squareup.com/t5/Product-Updates/Improvement-SevenRooms-x-Square-for-Restaurants-reservation/ba-p/810372)
- **Private dining namunasi (Sidney):** xonani band qilish uchun 50% qaytarilmaydigan depozit, 14 kundan kam qolgan bronlar uchun to‘liq oldindan to‘lov. — [sevenrooms.com experiences](https://www.sevenrooms.com/experiences/sakerestaurantandbarmanlywharf/sora-private-dining-room--7200881079)
- **Rossiya fiskal amaliyoti (analog):**
  - oldindan to‘lov olinganda "Предоплата" belgili chek beriladi ("Предоплата за банкет");
  - yakuniy "Полный расчёт" chekida avval to‘langan summa va qo‘shimcha to‘lov ko‘rsatiladi.

  — [nalog.gov.ru (qidiruv parchasi)](https://www.nalog.gov.ru/rn75/news/activities_fts/16592343/) **[ANIQ EMAS]**
- **Buxgalteriya:** depozit kechiktirilgan daromad sifatida yuritiladi va xizmat ko‘rsatilganda, odatda haqiqiy hisobdan ayirilib, tushum deb tan olinadi. — [beancount.io, 27.07.2026](https://beancount.io/ru/blog/2026/07/27/restaurant-reservation-deposits-no-show-fees-deferred-revenue-guide)
- **R-Keeper amaliyoti:** Qozog‘istondagi kassir vakansiyasida "контроль правильного закрытия счетов с учетом ранее внесенной предоплаты или депозита" vazifasi bor. — [obyavleniya.kaspi.kz](https://obyavleniya.kaspi.kz/a/kassir-123008268)
- **Saby Presto:** kassada "Расчёт → Предоплата" (mehmon ismi va summa) jarayoni, keyin u hisobdan ayiriladi. — [saby.ru/help/presto/pay_oplata/partial](https://saby.ru/help/presto/pay_oplata/partial) **[ANIQ EMAS]**
- R-Keeper’da banket uchun "minimal summa" sozlamasini tasdiqlovchi hujjat topilmadi.

### Inferences
**[XULOSA] Choyxona POSi uchun analoglardan olinadigan naqshlar:**
1. **Xona = stol + xizmat.** iiko modelidagi kabi xona zal sxemasidagi obyekt bo‘ladi va unga ixtiyoriy "vaqt xizmati" bog‘lanadi. Sozlanadigan parametrlar:
   - start/stop/pauza;
   - yaxlitlash qadami (masalan, 30 yoki 60 daqiqa);
   - minimal davomiylik;
   - kun vaqti yoki hafta kuni tariflari (kechki narx, juma va dam olish kunlari).

   Prechek yoki to‘lovda taymer avtomatik to‘xtaydi.
2. **Uchta narx modeli bir mahsulotda bo‘lishi kerak:**
   - (a) xona uchun qat’iy haq (kechaga);
   - (b) soatlik haq;
   - (c) kishi boshiga joy haqi (choy, non, salat bilan).

   Hammasi bitta chekda taomlar bilan birga turadi (Paloma365 kabi).
3. **Depozit:** bronda olinadi (Payme, Click). Kishi boshiga, soat bo‘yicha yoki vaqtga qarab hisoblanadi (Restoplace kabi). Mehmon o‘tirganda ochiq hisobga **avtomatik** ayiriladi (Square × SevenRooms kabi). Fiskal tomonda "oldindan to‘lov" va "to‘liq hisob" cheklari ajratiladi; O‘zbekiston fiskal talablarini alohida tekshirish kerak.
4. **Minimal summa (minimum spend):** analoglarda hujjatlashtirilmagan. Choyxona uchun oddiy qoida yetarli: "xona minimal summasi; yetmasa farq 'xona haqi' qatori sifatida qo‘shiladi". Bu raqobat ustunligi bo‘lishi mumkin.
5. **Kalendarda kesishuvni taqiqlash** (1C sauna kabi) va bron vidjeti yoki boti orqali bo‘sh xonani real vaqtda ko‘rsatish.
6. **Guruh hisobi:** bitta xonada bir nechta hisob (Biznex), chekni bo‘lish, aralash to‘lov. Odoo’dagi kabi buyurtmani xonalar orasida ko‘chirish.

### Gaps
- Karaoke va banya dasturlarida (masalan, Rossiyadagi "Банный комплекс" CRMlari) minimal summa va guruh depozitining aniq modeli topilmadi.
- Poster’ning vaqt xizmati yoki ilovalar marketidagi taymer plaginlari tasdiqlanmadi.
- Split bill’ning "teng bo‘lish / kishi boshiga / pozitsiya bo‘yicha" variantlari qaysi tizimda qanday ishlashi chuqur o‘rganilmadi.

---

## 5. Qozog‘iston, Qirg‘iziston, Tojikiston va Rossiyadagi chaikhana POS amaliyotlari

### Takeaway
Rossiya va Qozog‘istondagi chaikhanalar uchun alohida dastur topilmadi. Yirik tarmoq (Chaihona №1) umumiy **R-Keeper + StoreHouse** ishlatadi.

Qozog‘istonda vaqt bo‘yicha to‘lov (Paloma365) va kalyanxona yechimlari (iiko asosida Kafesoft.kz) mavjud, bu choyxona kabinkalariga yaqin analog. Qirg‘iziston va Tojikiston bo‘yicha ma’lumot topilmadi.

### Cited Findings
- Chaihona №1: R-Keeper 7, StoreHouse, chegirma kartalari, EGAIS va "Честный знак". — [dreamjob.ru, 01.07.2025](https://dreamjob.ru/employers/289735/vakansii/122277006)
- Qozog‘istondagi iiko integratori (Taraz, OLX) iiko’ni WebKassa, Kaspi, Halyk va 1C bilan integratsiya qilishni taklif qiladi; "2007-yildan bozorda". Chaikhanaga xos funksiya ko‘rsatilmagan. — [olx.kz](https://www.olx.kz/d/obyavlenie/avtomatizatsiya-iiko-pod-klyuch-programma-ayko-v-taraze-IDqSv5V.html) **[YB]**
- Paloma365.kz vaqt bo‘yicha to‘lov tarifikatori (4-bo‘limga qarang). — [paloma365.kz](https://paloma365.kz/for-time-pricing)
- "автоматизация чайханы Казахстан iiko кабинки" qidiruvi chaikhanaga xos birorta keys bermadi. — qidiruv natijasi (manbalar: [olx.kz](https://www.olx.kz/d/obyavlenie/avtomatizatsiya-iiko-pod-klyuch-programma-ayko-v-taraze-IDqSv5V.html), [satu.kz](https://satu.kz/Iiko.html))

### Inferences
- **[XULOSA]** MDH bo‘ylab chaikhana kabinkasi alohida dasturiy kategoriya emas. U "restoran POS + bron servisi (+ ixtiyoriy tarifikator)" kombinatsiyasi bilan yopiladi. Rizqim shu uchta qismni choyxona uchun bitta mahsulotda birlashtirsa, bu farqlovchi ustunlik bo‘ladi.

### Gaps
- Chaihona №1 kabinka depoziti, minimal summa va xizmat % qoidalari ochiq manbada topilmadi.
- Qirg‘iziston va Tojikiston chaikhanalari bo‘yicha ma’lumot yig‘ilmadi.
- Qozog‘istondagi yirik chaikhana tarmoqlarining (masalan, "Navat"ning) POSi tekshirilmadi.
