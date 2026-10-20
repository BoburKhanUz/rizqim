# O‘zbekistondagi restoran POS tizimlari: raqobatchilar tahlili (Rizqim uchun)

**Tayyorlangan sana:** 2026-yil 19-oktabr. Saytlar shu kuni o‘qildi, agar boshqa sana ko‘rsatilmagan bo‘lsa.
**Ishonch belgilari** (fiskal_background.md bilan bir xil):
- **[YETKAZIB BERUVCHI]**: kompaniyaning o‘z sayti, ya’ni kompaniyaning o‘z da’vosi.
- **[ANIQ EMAS]**: ikkilamchi manba, sharh-agregator yoki o‘zim tekshira olmagan ma’lumot.
- **[ESKI]**: 2024-yil oktabrdan oldingi manba.
- **[ROSSIYA/UKRAINA BOZORI]**: O‘zbekistonga tegishli bo‘lmasligi mumkin.

**Texnik eslatma:** iiko.uz, syrve.uz, quickresto.uz, jowi.club, erapos.uz va foodtech.uz saytlari tadqiqot paytida ochilmadi (ulanish uzildi yoki 502). jowi.club uchun WebFetch "ENOTFOUND" qaytardi, ya’ni domen DNS’da topilmadi. www.jowi.uz 403/404 qaytardi. joinposter.com O‘zbekiston narxlari o‘rniga Ukraina narxlarini ko‘rsatdi (geolokatsiya). Clopos narxlari JavaScript orqali yuklanadi va ularni o‘qib bo‘lmadi. Fiskalizatsiya qonunchiligi bu yerda takrorlanmaydi (fiskal_background.md ga qarang). Har bir POS faqat fiskalni **qanday** hal qilishi bo‘yicha qisqacha yozildi.

---

## 1. O‘zbekistonda qaysi POS tizimlari ishlatiladi, ularni kim sotadi va qanchalik keng tarqalgan

### Takeaway
Bozor uch qatlamdan iborat:
- **(a) Rossiya ildizli "og‘ir" tizimlar:** iiko va R-Keeper. Ular yirik restoran va tarmoqlarda ishlaydi va mahalliy integratorlar (ITDO, BUSOFT va boshqalar) orqali sotiladi.
- **(b) Bulutli SaaS:** Poster, Clopos (Ozarbayjon) va Paloma365 (mahalliy, universal).
- **(c) Yangi mahalliy o‘zbekcha POSlar:** Biznex, RAOS, Alipos va boshqalar, so‘m narxlari bilan.

Eng muhim raqam BMTTD so‘rovidan (2026-yil may): ovqatlanish korxonalarining atigi **36%** ida "R-Keeper yoki shunga o‘xshash" dastur bor. Demak, bozorning taxminan **2/3 qismi hali avtomatlashtirilmagan**.

### Cited Findings

**Bozor hajmi va qamrov**
- Soliq qo‘mitasi ma’lumotiga ko‘ra, respublikada **25 mingdan ortiq** yuridik shaxs umumiy ovqatlanish xizmatlarini ko‘rsatadi. — [BMTTD, "Umumiy ovqatlanish xizmatlari sohasida norasmiy iqtisodiyot", 2026, ~38-bet](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf)
- So‘rov bo‘yicha korxonalarning faqat **36%** ida "R-Keeper" yoki shunga o‘xshash boshqaruv dasturi o‘rnatilgan. Ulardan **73%** soliq bazasiga integratsiya qilingan. **97%** korxonada rasmiy kassa apparati bor. So‘rovda 14 viloyat markazidan 420 tadbirkor, 420 ishchi va 420 mijoz qatnashgan (jami 1260). — [BMTTD hisoboti, 2026, ~38 va 44-betlar](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf)
- Mijozlarning o‘rtacha **59%** i karta bilan, **45%** i naqd to‘laydi. Korxonalarning 30% i mijozga "onlayn o‘tkazma" (kartadan kartaga) bilan to‘lashni taklif qiladi. Bu holat choyxonalarda **45%**, to‘yxonalarda **38%**. — [BMTTD, 2026, 44-bet](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf)

**iiko (Syrve)**
- iiko Uzum Tezkor hamkorlarining "har uchinchisi"da ishlatiladi, bu Toshkentda taxminan **150 ta muassasa**. Uzum Tezkor bilan integratsiya buyurtmani to‘g‘ridan-to‘g‘ri oshxonaga yuboradi va stop-listni avtomatik oladi. — [the-tech.kz, spot.uz’ga tayanib, 08.08.2023](https://the-tech.kz/?p=10320) **[ESKI]**
- **ITDO** (Toshkent, Bog‘ibo‘ston ko‘ch., 254) iiko’ni "kalit topshirish" asosida joriy qiladi, uskuna sotadi va ta’mirlaydi, 24/7 yordam beradi. Agentlik dasturi ham bor. — [itdo.uz](https://itdo.uz/) **[YETKAZIB BERUVCHI]**
- Syrve (iiko’ning xalqaro brendi) o‘zini "50+ mamlakatda 10 000+ restoran" deb ko‘rsatadi. O‘zbekistonda esa iiko brendi ishlatiladi (ITDO saytida faqat "iiko"). — [syrve.com](https://www.syrve.com/en-gb/) **[YETKAZIB BERUVCHI]**. iiko va Syrve bir kompaniya ekanligi mening umumiy bilimimga asoslangan va bu tadqiqotda manba bilan tasdiqlanmadi. **[ANIQ EMAS]**

**R-Keeper (UCS)**
- **BUSOFT** (rkeeper.uz domeni) "16 yil bozorda, O‘zbekistonda **1000 dan ortiq** avtomatlashtirilgan muassasa" deydi. U UCS’ning rasmiy hamkori (kumush maqom). R-Keeper 7 va StoreHouse 4/5 ni joriy qiladi, onlayn-kassa va fiskal registratorlarni ulaydi, UZQR va ekvayring (UzCard protsessing markazi bilan) bo‘yicha ham hamkor. Mijozlari: The Doner, WeDrink, Osteria Mario/Shvili (Tigrus), Bla Bla Bar, City Grill, "Papin dom" (Olmaliq). — [rkeeper.uz](https://rkeeper.uz/) **[YETKAZIB BERUVCHI]**
- Ma’lumotnomalarda yana "R-KEEPER UZBEKISTAN" (SOFT IT SERVICE LLC, rkeeper.uz@gmail.com) ham ko‘rsatilgan. Domen hozir BUSOFT nomida, shuning uchun dilerlar o‘rtasidagi bog‘liqlik noaniq. — [yellowpages.uz](https://www.yellowpages.uz/en/rubric/programs-for-restaurants-restaurant-automation-development-implementation/tashkent); [goldenpages.uz](https://www.goldenpages.uz/company/?Id=73011) **[ANIQ EMAS]**

**Poster (joinposter)**
- Poster O‘zbekistonda fiskalizatsiyani **Multikassa** ilovasi orqali hal qiladi. Ilova sahifasida qo‘llab-quvvatlash manzili sifatida `@micros.uz` ko‘rsatilgan. — [joinposter.com, Multikassa](https://joinposter.com/en/applications/multikassa) **[YETKAZIB BERUVCHI]**
- MICROS TM (UCD MICROS JV LLC) Toshkent restoran avtomatlashtirish provayderlari ro‘yxatida bor. — [yellowpages.uz](https://www.yellowpages.uz/en/rubric/programs-for-restaurants-restaurant-automation-development-implementation/tashkent)
- O‘zbekistondagi Poster mijozlari soni bo‘yicha manba topilmadi.

**JOWi**
- JOWi (Toshkentda ishlab chiqilgan, gibrid: lokal modul va bulut) 2023-yil mayda Yandex Eats bilan to‘liq integratsiya qilingan. — [spot.uz, 10.05.2023](https://www.spot.uz/oz/2023/05/10/yandex-eats-jowi/) **[ESKI]**
- Soliq qo‘mitasi 2023–2024-yillarda "R-Keeper, JOWi, iiko" ni nomma-nom aytib, ularni virtual kassa sifatida integratsiya qilishni talab qilgan. Bu uchala tizim bozorda eng ko‘p ishlatilishini ko‘rsatadi. — [buxgalter.uz, 26.12.2023](https://buxgalter.uz/oz/publish/doc/text195345_avtomatlashtirish_dasturlarini_soliq_organlarining_ahborot_tizimlari_bilan_integratsiya_qilish_talablari) **[ESKI]**
- Rahmat QR-to‘lov xizmati 2026-yilda ham "R-Keeper, JOWI, iiko, Poster bilan ishlaydi" deb yozadi. Demak, JOWi hali ham integratsiya ro‘yxatida turibdi. — [rhmt.uz/rest/aggregator](https://rhmt.uz/rest/aggregator/) **[YETKAZIB BERUVCHI]**

**Clopos (Ozarbayjon)**
- Clopos Bakuda asoslangan. O‘zbekistonda ofisi bor va o‘zbek tilida sayt yuritadi. U Yandex Go, Uzum va Wolt buyurtmalarini tizimga qabul qiladi. — [clopos.com/uz](https://www.clopos.com/uz). Mijozlari soni bo‘yicha manbalar bir-biriga mos kelmaydi: "taxminan 2000 restoran" ([idda.az](https://awards.idda.az/en/nominees/405)) va "4 mamlakatda 3000+ restoran" ([tech.az](https://tech.az/en/posts/clopos-has-achieved-43-growth-in-the-international-market-5490)). Ikkalasi ham qidiruv parchasidan olingan. **[ANIQ EMAS]**

**Paloma365**
- Paloma365 "2013-yildan beri, **20 000+ obyekt**" deydi. Bu restoranlar, do‘konlar, qahvaxonalar va salonlarni birga hisoblagan son, ya’ni universal bulutli POS. — [paloma365.uz](https://paloma365.uz/) **[YETKAZIB BERUVCHI]**

**Mahalliy yangi POSlar**
- **Biznex** (Deep Space MCHJ, Toshkent) o‘zini "restoran, kafe va choyxonalar uchun kassa va boshqaruv tizimi" deb ataydi va "butun O‘zbekiston bo‘ylab" o‘rnatishni va’da qiladi. — [biznex.uz](https://www.biznex.uz/tariflar) **[YETKAZIB BERUVCHI]**
- **RAOS** (TezCode) asosan do‘konlar uchun. Kafe sahifasida "8+ kafe va restoran RAOS ishlatmoqda" deyilgan, ya’ni restoran segmentida juda kichik. — [raos.uz/kassa/kafe](https://raos.uz/kassa/kafe) **[YETKAZIB BERUVCHI]**
- **Alipos**: Samarqandda 2023-yilda asos solingan HoReCa SaaS. Sarlavhaga ko‘ra Imkon Ventures va Nexus Capital’dan 3,5 mln $ baholashda 200 ming $ jalb qilgan. Maqolaning o‘zi ochilmadi (403), alipos.uz sahifasida esa matn yo‘q. — [munich-startup.de (faqat sarlavha)](https://insights.munich-startup.de/news/feed/uzbek-horeca-saas-startup-alipos-raises-200k-at-3-5m-valuation-from-imkon-ventures-and-nexus-capital); [dealroom](https://app.dealroom.co/companies/alipos) **[ANIQ EMAS]**
- Boshqa mahalliy restoran POSlar sifatida ERA POS va Paloma365 tilga olinadi. Ularning ko‘pchiligi FM + FiscalDriveService yoki Rahmat lokal API orqali ishlaydi. — fiskal_background.md, 3.4 ([docs.biznex.uz](https://docs.biznex.uz/tax-modul/overview/))

**Restoran POSi emas, lekin yondosh mahalliy tizimlar**
- **Billz**: do‘konlar uchun ("7000 do‘kon", kiyim va oziq-ovqat chakana savdosi). Saytda restoran moduli ko‘rinmadi. — [billz.io/ru](https://billz.io/ru) **[YETKAZIB BERUVCHI]**
- **Smartup** (smartup24.com): distribyutsiya va savdo vakillari uchun bulutli platforma, restoran POSi emas. — [smartup.uz → smartup24.com](https://smartup.uz)
- **REGOS**: chakana savdo va reyestrdagi virtual kassa (REGOS VCR). Saytda restoran funksiyalari topilmadi. Monoblok va POS uskunalarini 770 000–6 527 000 so‘m oralig‘ida sotadi. — [regos.uz](https://regos.uz/ru) **[YETKAZIB BERUVCHI]**
- **Rahmat/Multikassa** va **E-POS** to‘liq restoran POSi emas, ular fiskal va to‘lov qatlami (pastga qarang). Rahmat restoranlar uchun QR-to‘lov, choy puli, QR-menyu, stol bron qilish va agregator moduli ham sotadi, "500+ hamkor" bor deydi. — [rhmt.uz/rest](https://rhmt.uz/rest/) **[YETKAZIB BERUVCHI]**
- **Quick Resto**: O‘zbekistonda faoliyati, dileri yoki narxi bo‘yicha birorta manba topilmadi (quickresto.uz ochilmadi).

**Yetkazib berish bozori (integratsiya uchun muhim)**
- 2024-yil oktabrda Yandex Uzbekistan Express24 aktivlarining bir qismini sotib oldi va ikki xizmatni bitta texnologik platformaga o‘tkazishni rejalashtirdi. Raqobat qo‘mitasi Yandex Eats va Express24 ni ustun mavqedagi subyekt deb topdi. — [kun.uz, 25.10.2024](https://m.kun.uz/en/news/2024/10/25/yandex-uzbekistan-expands-with-acquisition-of-express24-assets-plans-to-build-unified-food-delivery-platform); [kun.uz, 26.10.2024](https://ad.kun.uz/en/news/2024/10/26/uzbekistans-food-delivery-market-deemed-monopolized-by-yandex-eats-and-express24)
- Uzum Tezkor 2025-yil aprelida 25 shaharda ishlagan, 2600 hamkori bo‘lgan. — [uzum.com](https://uzum.com/en/press-center/news-and-press-releases/25cities-2600partners-7000couriers-uzum-tezkor-continues-regional-expansion/) (sarlavha va URL’dan) **[ANIQ EMAS]**

### Inferences
- Bozor ulushini aniq hisoblovchi manba yo‘q. Bilvosita belgilar bo‘yicha Toshkentning o‘rta va yuqori segmentida **iiko va R-Keeper** yetakchi. Bunga soliq organlari aynan ularni nomlagani, iiko Uzum Tezkor hamkorlarining 1/3 qismida turgani va BUSOFT’ning "1000+" da’vosi asos.
- **JOWi** 2019–2023-yillarda mahalliy "uchinchi o‘yinchi" bo‘lgan. 2026-yilda uning saytlari ochilmayapti, bu pasayish belgisi bo‘lishi mumkin, lekin tasdiqlanmagan. Rizqim uchun JOWi mijozlari yangi tizimga o‘tishi mumkin bo‘lgan auditoriya bo‘lishi mumkin. **[ANIQ EMAS]**
- 25 mingdan ortiq korxonaning taxminan 64% ida (BMTTD) dastur yo‘q. Asosiy raqobat **"dastur yo‘q" holati, Excel va oddiy kassa** bilan, iiko bilan emas. Bu qatlamga mahalliy arzon SaaS (Biznex, Paloma365, RAOS) ham endi chiqmoqda.
- Restoran POS bozorida fiskal va to‘lov provayderlari (Rahmat, Multikassa, E-POS, REGOS) alohida "infratuzilma" qatlamini tashkil qiladi. Rahmat bu qatlamdan restoran funksiyalari (bron, QR-menyu, agregator) tomon kengaymoqda, ya’ni u ham hamkor, ham potensial raqobatchi.

### Gaps
- Har bir POSning O‘zbekistondagi aniq mijozlar soni yoki bozor ulushi. Mustaqil manba topilmadi, faqat yetkazib beruvchilar da’volari bor.
- iiko’ning O‘zbekistondagi boshqa dilerlari (ITDO’dan tashqari) va R-Keeper dilerlari to‘liq ro‘yxati. iiko.uz ochilmadi.
- JOWi’ning 2025–2026-yillardagi holati (faolmi, sotilganmi, yopilganmi). Saytlari ochilmadi, yangi xabar topilmadi.
- Clopos va Alipos narxlari va O‘zbekistondagi mijozlari soni.
- Telegram restoran guruhlaridagi ichki muhokamalar (Telegram ichini qidirish imkoni bo‘lmadi).

---

## 2. Har bir asosiy tizimning funksiyalari, platformasi, offlayn rejimi, fiskalizatsiyasi, tili va to‘lov/agregator integratsiyalari

### Takeaway
iiko va R-Keeper funksional jihatdan eng to‘liq (tex-karta, ombor, tarmoq, yetkazish, KDS), lekin Windows va server asosida ishlaydi. Fiskalni ular tashqi virtual kassa (Rahmat/Multikassa) orqali hal qiladi va interfeysi rus tilida. Bulutli tizimlar (Poster, Clopos, Paloma365) Android, iPad va veb’da ishlaydi. Mahalliy yangi POSlar (Biznex, RAOS) o‘zbek tili, offlayn rejim va o‘rnatilgan Soliq/OFD integratsiyasini asosiy ustunlik qilib ko‘rsatadi.

### Cited Findings

**Qisqa jadval (manbalar quyida, har bir qator tagida)**

| Tizim | Platforma | Offlayn | Fiskal (O‘zbekistonda) | O‘zbek tili | To‘lov / agregatorlar |
|---|---|---|---|---|---|
| iiko | Windows (iikoFront) + iikoWeb bulut paneli | Lokal server bilan ishlaydi (tasdiqlanmadi) | Rahmat/Multikassa virtual kassa plagini; ITDO sozlaydi | Topilmadi | Uzum Tezkor (2023), Rahmat QR |
| R-Keeper 7 | Windows, lokal server | Lokal (klassik arxitektura) | Onlayn-KKM / fiskal registrator + Rahmat virtual kassa | Topilmadi | UZQR (BUSOFT), Rahmat QR, UzCard ekvayring |
| Poster | Android, iPad, Windows, macOS (bulut) | Topilmadi | Multikassa ilovasi (IKPU, qadoq kodi, Z-hisobot) | Topilmadi | Rahmat QR; boshqalar topilmadi |
| JOWi | Kompyuter, planshet, telefon (Android/iOS ofitsiant ilovasi), veb panel | Gibrid: lokal modul internetsiz ishlaydi | 2023-yilda "texnik imkonsiz" deyilgan [ESKI] | Topilmadi | Yandex Eats (2023), Rahmat QR, API |
| Clopos | Bulut POS, KDS, Manager ilovasi | Topilmadi | Topilmadi | Ha (sayt) | Yandex Go, Uzum, Wolt; QR-menyu |
| Paloma365 | Windows, Android, iOS, veb | "2 haftagacha oflayn" | Saytda yo‘q | Ha | Yetkazib berish moduli; agregatorlar topilmadi |
| Biznex | Kassa, oshxona ekrani, kiosk, egasi telefoni | Ha, "chek soliqqa avtomatik ketadi" | O‘rnatilgan (faqat Pro va undan yuqori tarifda) | O‘zbek (lotin, kirill), rus, ingliz | Payme, Click, o‘tkazma, qarzga sotuv |
| RAOS | Android kassir ilovasi, iOS/Android egasi ilovasi | Ha | Soliq.uz/OFD barcha tariflarda bepul | UZ/RU/EN | Uzum, Yandex Go "ishlab chiqilmoqda" |

**iiko**
- Kassa, ombor, moliya va analitika bitta platformada. iikoWeb orqali telefondan bulutli kirish mumkin. Tex-karta bo‘yicha avtomatik hisobdan chiqarish, mehmonlar bazasi, bonus va aksiyalar, xodimlar ish vaqti hisobi bor. — [itdo.uz](https://itdo.uz/); [ITDO, iiko yoki R-Keeper](https://itdo.uz/bazaznaniy/iiko-ili-r-keeper/) **[YETKAZIB BERUVCHI]**
- ITDO yozishicha, iiko uchun fiskal modulni ITDO o‘zi sozlaydi. Tizim "mahalliy fiskalizatsiya va onlayn-KKM bilan ishlashi shart". — [itdo.uz, iiko yoki R-Keeper](https://itdo.uz/bazaznaniy/iiko-ili-r-keeper/) **[YETKAZIB BERUVCHI]**
- Rahmat (Multikassa) "iiko’ning asosiy modullari bilan integratsiya"ni tayyor yechim sifatida beradi. — [rhmt.uz/pos/integration](https://rhmt.uz/pos/integration/) **[YETKAZIB BERUVCHI]**
- Uzum Tezkor integratsiyasi buyurtmani oshxonaga avtomatik yuboradi va stop-listni sinxronlaydi. — [the-tech.kz, 08.08.2023](https://the-tech.kz/?p=10320) **[ESKI]**

**R-Keeper**
- Kuchli kassa qismi bor. Ombor (StoreHouse) va kengaytirilgan analitika ko‘pincha alohida sotib olinadi. Tizim yirik tarmoqlar va to‘liq servisli restoranlar uchun mos. — [ITDO](https://itdo.uz/bazaznaniy/iiko-ili-r-keeper/) (raqobatchi integratorning fikri) **[YETKAZIB BERUVCHI]**; [kirov.denvic.ru](https://kirov.denvic.ru/blog/obshchepit/iiko-i-r-keeper-sravnenie-dvukh-samykh-populyarnykh-sistem-avtomatizatsii-obshchepita/) **[ROSSIYA/UKRAINA BOZORI]**
- BUSOFT onlayn-kassa va fiskal registratorlarni ulaydi, ularni soliq to‘lovchining shaxsiy kabinetida ro‘yxatdan o‘tkazadi (ERI bo‘lsa). UZQR mehmon xizmati R-Keeper bilan integratsiya qilingan. — [rkeeper.uz](https://rkeeper.uz/) **[YETKAZIB BERUVCHI]**
- Rahmat R-Keeper modullari bilan tayyor integratsiyaga ega. — [rhmt.uz/pos/integration](https://rhmt.uz/pos/integration/) **[YETKAZIB BERUVCHI]**

**Poster**
- Bulutli tizim: Android planshetlar, iPad, Windows va macOS’da ishlaydi. Ma’lumotlar Germaniyadagi TIER 3 serverlarda, nusxasi AQShda saqlanadi. Ochiq API va ilovalar katalogi bor. Funksiyalar ro‘yxatida egasi ilovasi, sodiqlik, yetkazib berish moduli, ombor, aksiya konstruktori va 24/7 yordam bor. — [joinposter.com, JOWi bilan taqqoslash](https://joinposter.com/alternative/jowi-uz) **[YETKAZIB BERUVCHI]**
- Har bir tarifga bitta terminal kiradi, qo‘shimcha kassalar pullik. Ofitsiant uchun smartfondagi kassa alohida pullik qo‘shimcha. Fiskal registrator va printer alohida kerak. — [joinposter.com/pricing](https://joinposter.com/pricing) **[ROSSIYA/UKRAINA BOZORI]**
- **Multikassa** (ofd.uz reyestridagi virtual kassa) orqali har bir taom tafsilotlari bilan avtomatik fiskal chek chiqariladi, qaytarishlar va smena (Z-hisobot) yuritiladi. IKPU va qadoq kodlari tasnif.soliq.uz’dan Poster interfeysida tanlanadi. Alohida kassa apparati shart emas, Multikassa monoblok, PC yoki planshetga o‘rnatiladi. — [joinposter.com, Multikassa](https://joinposter.com/en/applications/multikassa) **[YETKAZIB BERUVCHI]**

**JOWi**
- Gibrid arxitektura: restoranda lokal modul o‘rnatiladi va bulut bilan sinxronlanadi, shuning uchun internet uzilganda ham ishlaydi. Zal menejeri va ofitsiantlar uchun alohida ilovalar bor. — [habr.com](https://habr.com/en/articles/258679) **[ESKI]**
- Oddiy kompyuter, planshet yoki telefon yetadi. Ofitsiant ilovasi Android va iOS’da ishlaydi, veb panel app.jowi.club manzilida. Saytlar, ilovalar, menyu, buyurtma va bron uchun dasturchi API bor. — [jowiclub.userecho.ru](https://jowiclub.userecho.ru/knowledge-bases/2/articles/4-kak-rabotat-s-jowi); [kabinet razrabotchika](https://jowiclub.userecho.ru/knowledge-bases/2/articles/178-kabinet-razrabotchika) **[YETKAZIB BERUVCHI]** (sanasi yo‘q)
- Funksiyalari: buyurtmalar, tannarx (kalkulyatsiya), ombor, analitika, marketing, sotuv paytida hisobdan chiqarish. — [weproject.media, 22.08.2019](https://www.weproject.media/articles/detail/kak-uskorit-i-uprostit-rabotu-restorana-5-effektivnykh-sistem-avtomatizatsii/) **[ESKI]**
- Yandex Eats integratsiyasi va stop-list (masalan, 5 ta burger bo‘lsa, 6-buyurtma avtomatik rad etiladi). — [spot.uz, 10.05.2023](https://www.spot.uz/oz/2023/05/10/yandex-eats-jowi/) **[ESKI]**
- JOWi asoschisi 2023-yilda fiskal integratsiya "texnik jihatdan hali imkonsiz" degan. — fiskal_background.md, 3.4 **[ESKI]**

**Clopos**
- Mahsulotlari: POS-kassa, oshxona ekrani tizimi (KDS), bosh ofis boshqaruvi, QR-menyu (mijoz o‘zi buyurtma beradi va telefondan to‘laydi), Clopos Manager mobil ilovasi, filiallarni markazlashgan boshqarish. Yandex Go, Uzum va Wolt buyurtmalari tizimga to‘g‘ridan-to‘g‘ri tushadi. Yillik obuna 20% arzonroq. — [clopos.com/uz](https://www.clopos.com/uz); [clopos.com/uz/pricing](https://www.clopos.com/uz/pricing) **[YETKAZIB BERUVCHI]**
- Fiskalizatsiyani O‘zbekistonda qanday hal qilishi saytda ko‘rinmadi.

**Paloma365**
- Windows, Android (Google Play va APK), iOS (iPhone/iPad) ilovalari va veb panel bor. "2 haftagacha oflayn rejimda ishlash" mumkin. Yetkazib berish, sodiqlik va CRM, AI yordamchi, cheksiz foydalanuvchi bor. Telefon, planshet, noutbuk, POS-terminal va bank terminalida ishlaydi. — [paloma365.uz](https://paloma365.uz/) **[YETKAZIB BERUVCHI]**
- Restoran funksiyalari (retseptlar, porsiya hisobi) faqat eng yuqori "Universal" tarifda. Quyi tariflarda ham tex-kartalar va ombor bor. — [paloma365.uz/tariffs](https://paloma365.uz/tariffs) **[YETKAZIB BERUVCHI]**
- Fiskal yechimi saytda yozilmagan. Boshqa manbaga ko‘ra, FDS/QPOS yoki Rahmat lokal API ishlatiladi. — fiskal_background.md 3.4 **[ANIQ EMAS]**

**Biznex**
- Zal sxemasi, ochiq hisob, bitta stolga bir nechta hisob, bron, oshxona ekrani, retsept va tannarx (yarim tayyor mahsulot va modifikatorlar bilan), smena nazorati, mijoz ekrani va o‘z-o‘ziga xizmat kioski bor. Sohalar: restoran, fast food, kofeynya, yetkazib berish va cloud kitchen, tarmoq. — [biznex.uz/restoran](https://www.biznex.uz/restoran); [biznex.uz/tariflar](https://www.biznex.uz/tariflar) **[YETKAZIB BERUVCHI]**
- "Internetsiz ham ishlaydi, chek soliqqa avtomatik ketadi. Egasi telefonidan ham kirib ko‘radi." To‘lov usullari: naqd, karta, o‘tkazma, Payme, Click, qarzga sotuv. Tillar: o‘zbek (lotin va kirill), rus, ingliz. — [biznex.uz](https://www.biznex.uz/) **[YETKAZIB BERUVCHI]**

**RAOS (TezCode)**
- Stollar, buyurtmani birlashtirish va bo‘lish, ofitsiant bo‘yicha hisob, oshxona printeri yoki ekrani, kalkulyatsiya (retsept), offlayn rejim, "AI tungi hisobot Telegram’da" bor. Soliq.uz fiskal cheklari bepul va har bir sotuv OFD’ga avtomatik yuboriladi. — [raos.uz/kassa/kafe](https://raos.uz/kassa/kafe) **[YETKAZIB BERUVCHI]**
- Kassir ilovasi Android uchun, egasi ilovasi iOS va Android uchun. MaxSavdo orqali Telegram do‘kon bilan bitta ombordan ishlaydi. Uzum Market va Yandex Go integratsiyalari "ishlab chiqilmoqda". — [raos.uz](https://raos.uz/) **[YETKAZIB BERUVCHI]**

**Fiskal va to‘lov qatlami (POSlar ulanadigan provayderlar)**
- **Rahmat**: QR-to‘lov (bitta QR’da 15+ usul: Uzcard, Humo, Payme, Click, Uzum, Oson, Visa, Mastercard, Xazna) R-Keeper, JOWI, iiko va Poster bilan integratsiya qilingan. Chekka dinamik QR chiqariladi. "Har beshinchi mehmon choy puli qoldiradi, o‘rtacha 6,5%." Shuningdek, Yandex Eats, Express24 va Uzum Tezkor uchun yetkazish agregatori, stol bron qilish (depozit, SMS/Telegram eslatmalar) va kassa bilan integratsiyalangan QR-menyu bor. — [rhmt.uz/rest](https://rhmt.uz/rest/); [rhmt.uz/rest/aggregator](https://rhmt.uz/rest/aggregator/); [rhmt.uz/rest/booking](https://rhmt.uz/rest/booking/); [rhmt.uz/rest/menu](https://rhmt.uz/rest/menu/) **[YETKAZIB BERUVCHI]**
- **E-POS**: bulutli virtual kassa, Cashdesk (PC), Mobile (kuryer) va REST API (Communicator). Click, Payme, Uzum, Humo va Uzcard qo‘llab-quvvatlanadi. Sayt "alfa rejimida". — [epos.uz](https://epos.uz/) **[YETKAZIB BERUVCHI]**

### Inferences
- **Til**: iiko, R-Keeper, Poster va JOWi uchun o‘zbek tilidagi interfeys haqida birorta manba topilmadi. Ularning O‘zbekistondagi saytlari va materiallari rus tilida (ITDO, BUSOFT). Mahalliy yangi POSlar esa o‘zbek tilini (lotin va kirill) birinchi o‘ringa qo‘ymoqda. Bu ular uchun asosiy ajralib turish nuqtasi.
- **Fiskal modeli**: "katta" tizimlar fiskalni o‘zi qilmaydi, uchinchi tomon virtual kassasi (Rahmat/Multikassa) orqali qiladi va buning uchun alohida oylik to‘lov olinadi. fiskal_background.md da yozilgan 22.10.2026 dagi "ikkilamchi tizim" xavfi aynan shu modelga tegadi. Mahalliy POSlar (RAOS, Biznex Pro) esa fiskalni "o‘rnatilgan, bepul" deb sotmoqda.
- **Offlayn rejim** mahalliy POSlarning marketingida birinchi qatorda turadi (Biznex: "'Internet o‘chsa, savdo to‘xtaydi' — har kuni eshitiladigan gap"; RAOS: "Tizim A internet talab qiladi"). Bu bozor og‘rig‘i ekanini ko‘rsatadi. BMTTD ma’lumoti ham buni tasdiqlaydi (4-bo‘limga qarang).
- **Telegram** hozircha faqat bildirishnoma va hisobot kanali (RAOS) yoki alohida "Telegram do‘kon" sifatida bor. Restoran POSiga o‘rnatilgan Telegram buyurtma yoki mini-ilova bilan to‘liq integratsiyalangan raqobatchi topilmadi.

### Gaps
- iiko, R-Keeper va Poster’ning o‘zbek tilidagi interfeysi bormi yoki yo‘qmi, aniq tasdiqlanmadi (iiko.uz ochilmadi). Taxmin qilinishicha yo‘q yoki qisman. **[ANIQ EMAS]**
- Poster va Clopos’ning offlayn rejimi va O‘zbekistondagi Click/Payme/Uzum integratsiyalari bo‘yicha birlamchi manba topilmadi.
- Clopos va Paloma365 fiskalni qanday hal qilishi (qaysi virtual kassa) topilmadi.
- KDS, kiosk va mobil ofitsiant funksiyalarining har bir tizim bo‘yicha to‘liq matritsasini yetkazib beruvchilar saytidan to‘liq olib bo‘lmadi. Jadvalda faqat tasdiqlangan qismlar bor.

---

## 3. O‘zbekistondagi narxlar (litsenziya/obuna, joriy etish, uskuna)

### Takeaway
Ochiq narx e’lon qiladiganlar asosan mahalliy SaaS:
- Paloma365: **134 700–269 400 so‘m/oy**.
- RAOS: **249 000–799 000 so‘m/oy**.
- Biznex: **349 000–549 000 so‘m/oy**.

iiko O‘zbekistonda "**54 $/oydan**" (ITDO), Poster + Multikassa fiskal qo‘shimchasi esa **28 $ ulanish + 13 $/oy**. R-Keeper narxi ochiq emas, faqat dilerdan so‘raladi. Uskuna va o‘rnatish deyarli hamma joyda alohida to‘lanadi.

### Cited Findings

| Tizim | Narx (O‘zbekiston) | Nima kiradi / izoh | Manba, sana |
|---|---|---|---|
| iiko (ITDO orqali) | **"от 54$/месяц"** (o‘sha sahifada Rossiya shablonidan qolgan "от 1295 ₽/месяц" ham bor) | Tarif tafsilotlari ochilmadi; uskuna 0% muddatli to‘lov bilan | [itdo.uz](https://itdo.uz/), 19.10.2026 o‘qildi; [ITDO maqolasi](https://itdo.uz/bazaznaniy/iiko-ili-r-keeper/) **[YETKAZIB BERUVCHI]** |
| iiko (Rossiya, eski) | "от 2990 руб./мес" | Rossiya narxi | [weproject.media, 22.08.2019](https://www.weproject.media/articles/detail/kak-uskorit-i-uprostit-rabotu-restorana-5-effektivnykh-sistem-avtomatizatsii/) **[ESKI]** |
| R-Keeper (BUSOFT) | Narx e’lon qilinmagan; loyiha tarkibida litsenziyalarga **10% dan chegirma**; "R_keeper buyurtma qiling, termoprinter sovg‘a" (31.12.2026 gacha); xodimlarni bepul o‘qitish (~3 soat); xizmat ko‘rsatish tarif rejalari alohida | Litsenziya, ombor (StoreHouse) alohida | [rkeeper.uz](https://rkeeper.uz/) **[YETKAZIB BERUVCHI]** |
| R-Keeper (Rossiya) | "от 750 руб./мес" | Rossiya bozori | [a2is.ru](https://a2is.ru/catalog/programmy-dlya-kafe-i-restoranov/rkeeper) **[ROSSIYA/UKRAINA BOZORI]** |
| Poster (asosiy tarif) | O‘zbekiston narxi olinmadi. Sayt Ukraina narxini ko‘rsatdi: Starter 540 ₴, Mini 1080 ₴, Business 1530 ₴, Pro 2160 ₴ / oy (yillik to‘lovda; oylik to‘lovda 600/1200/1700/2400 ₴). 15 kun bepul | Tarifga 1 terminal kiradi, qo‘shimcha kassa va ofitsiant smartfoni pullik | [joinposter.com/pricing](https://joinposter.com/pricing) **[ROSSIYA/UKRAINA BOZORI]** |
| Poster + Multikassa (fiskal) | **28 $** ulanish + **8 $/oy** Multikassa + **5 $/oy** Poster integratsiyasi | Fiskal chek, qaytarish, Z-hisobot, IKPU | [joinposter.com, Multikassa](https://joinposter.com/en/applications/multikassa) (sana yo‘q) **[YETKAZIB BERUVCHI]** |
| JOWi | **29,99 $/oydan** (2019); "Standart" **69,99 $/oy** (Poster ma’lumoti, 2023-yil oktabr); boshqa katalogda "2000 rubldan" | Joriy narx topilmadi | [weproject.media, 2019](https://www.weproject.media/articles/detail/kak-uskorit-i-uprostit-rabotu-restorana-5-effektivnykh-sistem-avtomatizatsii/) **[ESKI]**; [joinposter.com/alternative/jowi-uz](https://joinposter.com/alternative/jowi-uz) **[ESKI]**; a2is (qidiruv parchasi) **[ANIQ EMAS]** |
| Paloma365 | Boshlang‘ich **134 700**, Asosiy **224 500**, Universal **269 400** so‘m/oy, 1 savdo nuqtasi uchun; yillik to‘lovda ~10% tejaladi | 15 kun bepul; o‘qitish va sozlash bepul; cheksiz foydalanuvchi; uskuna alohida | [paloma365.uz/tariffs](https://paloma365.uz/tariffs) **[YETKAZIB BERUVCHI]** |
| Biznex | Standard **349 000**, Pro **549 000** so‘m/oy, Custom kelishiladi | Soliq integratsiyasi, oshxona ekrani, modifikatorlar va kombo **faqat Pro’da**; o‘rnatish, menyu kiritish va o‘qitish alohida to‘lanadi; qo‘shimcha kassa va filial alohida; uskunani ijaraga olish mumkin | [biznex.uz/tariflar](https://www.biznex.uz/tariflar) **[YETKAZIB BERUVCHI]** |
| RAOS | Starter **249 000** (1 filial, 2 kassir), Growth **449 000** (3 filial, 10 kassir), Pro **799 000** so‘m/oy (cheksiz) | 30 kun bepul, karta talab qilinmaydi; Soliq.uz barcha tariflarda; o‘rnatishda bepul yordam | [raos.uz/kassa/kafe](https://raos.uz/kassa/kafe) **[YETKAZIB BERUVCHI]** |
| Clopos | Narx sahifada JS orqali yuklanadi, o‘qib bo‘lmadi; tariflar Start va Standard; yillik obuna 20% arzon | — | [clopos.com/uz/pricing](https://www.clopos.com/uz/pricing) |
| Rahmat (fiskal + kassa) | Virtual kassa **123 600 so‘m/oy**; SUNMI P3 smart-kassa **100 000 so‘m/oy** + qurilma **990 000 so‘m**; ikkalasida QR to‘lov va tovar hisobi bor | — | [rhmt.uz/pos/tariffs](https://rhmt.uz/pos/tariffs/) **[YETKAZIB BERUVCHI]** |
| REGOS uskunalari | POS/monoblok to‘plamlari **770 000 – 6 527 000 so‘m** (masalan, 1 565 000, 2 116 000, 2 879 000) | Chakana savdo uchun | [regos.uz](https://regos.uz/ru) **[YETKAZIB BERUVCHI]** |

- RAOS o‘z narxini noma’lum "Tizim A" bilan solishtiradi: "249K vs 299K, Tizim A’da Soliq.uz integratsiyasi yo‘q, internet talab qiladi". — [raos.uz](https://raos.uz/) **[YETKAZIB BERUVCHI]**
- Hisoblash (taxminiy, 19.10.2026 kursiga bog‘liq): 54 $ ≈ 650–700 ming so‘m/oy, ya’ni iiko’ning boshlang‘ich narxi mahalliy SaaS’dan taxminan **2–5 baravar** qimmat, bunga joriy etish ham qo‘shilmagan. Kurs bu tadqiqotda tekshirilmadi. **[ANIQ EMAS]**

### Inferences
- Mahalliy narx "koridori" taxminan **135 ming – 550 ming so‘m/oy** (1 nuqta). Rizqim shu oraliqqa, ayniqsa kichik kafe va choyxona uchun quyi chegaraga mos kelishi kerak.
- Fiskalizatsiya ko‘p tizimlarda **qo‘shimcha pul**: Poster (+13 $/oy +28 $), Biznex (faqat Pro), iiko/R-Keeper (+Rahmat virtual kassa 123 600 so‘m/oy). Majburiy funksiya uchun alohida pul olinmoqda. "Fiskal bepul va barcha tariflarda" degan taklif (RAOS kabi) kuchli marketing dalili.
- Uskuna deyarli hech qayerda tarifga kirmaydi. Biznex ijara taklif qiladi, Rahmat SUNMI P3 ni 990 ming so‘mga sotadi. Arzon Android terminal va obunani birga sotish bozorda kam.

### Gaps
- iiko va R-Keeper’ning O‘zbekistondagi **joriy etish narxi** (bir martalik) va litsenziya turlari (doimiy yoki obuna). Dilerlar narxni ochiq ko‘rsatmaydi.
- Poster’ning so‘m yoki dollardagi rasmiy O‘zbekiston narxlari (sayt geolokatsiya tufayli Ukraina narxini ko‘rsatdi).
- Clopos va Alipos narxlari.
- JOWi’ning 2025–2026-yillardagi narxi.

---

## 4. Restoranchilar nimadan shikoyat qiladi va nimani maqtaydi

### Takeaway
O‘zbekistonga xos ochiq sharhlar juda kam. Eng ishonchli ma’lumot BMTTD so‘rovidan (2026): chek bermaslik sabablari **yomon internet (26%)** va **kassa uskunasining nosozligi (20%)**, rasmiylashuvga to‘siq esa **kassa va hisobot tizimlari xarajatlari (24%)**. Yetkazib beruvchilar marketingi ham shu og‘riqlarni takrorlaydi: offlayn, oshxonaga buyurtma yetmasligi, mahsulot yo‘qolishi, taxminiy foyda. Rossiya/MDH sharhlariga ko‘ra, R-Keeper’da eskirgan interfeys va modullarni alohida sotib olish, iiko’da sekin qo‘llab-quvvatlash, Poster’da qo‘llab-quvvatlash va tarif o‘zgarishlari shikoyat sababi bo‘ladi.

### Cited Findings

**O‘zbekistonga xos ma’lumotlar**
- Rasmiy chek bermaslik sabablari: **26%** internet sifati yomon, **20%** kassa uskunasi yaxshi ishlamaydi, **14%** mijozga chek kerak emas, **11%** bank bilan aloqa uziladi, **8%** ko‘p vaqt oladi yoki tez-tez buziladi. 50% hech qanday muammo yo‘q deydi. Naqd to‘lovda korxonalarning **32%** i har doim ham chek bermaydi. — [BMTTD, 2026, 44-bet](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf)
- Rasmiylashuvdagi moliyaviy to‘siqlar: 62% soliq yuki, **24% kassa uskunalari va hisobot tizimlari xarajati**, 15% litsenziya, **10% bank xizmatlari (POS, QR, hisob raqam) to‘lovlari**. — [BMTTD, 2026, "V. Norasmiy faoliyatni rasmiylashtirish", 15-band](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf)
- BMTTD tavsiyasi: soliq bazasi bilan integratsiyalangan boshqaruv dasturlarini keng rag‘batlantirish. "Tegishli yordam berilmasa, ko‘plab kichik ovqatlanish korxonalari yangi tizimlarni o‘zlashtira olmaydi, chunki xodimlarining texnik salohiyati va vaqti yetarli emas." — [BMTTD, 2026](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf)
- 2023-yilda tadbirkorlar soliq organlarining R-Keeper, JOWi va iiko integratsiyasi talabini haddan tashqari keng talqin qilinayotganidan shikoyat qilgan. Ko‘p elektron tizim ishlatadigan kafe va restoranlarda integratsiya ayniqsa qiyin bo‘lgan. R-Keeper ishlatib, integratsiya qilmagan korxonaga 100 mln so‘m jarima solingan, sud tadbirkor foydasiga hal qilgan. — [kun.uz, 22.12.2023](https://kun.uz/news/2023/12/22/bu-tadbirkorlar-uchun-olim-jazosi-soliq-maslahatchisi-227-1-modda-haqida); [kun.uz, 21.12.2023](https://www.kun.uz/news/2023/12/21/soliq-kodeksining-221-1-moddasi-boyicha-400-mlrd-somlik-jarimalar-hisobdan-chiqarilishi-mumkin) **[ESKI]**
- Mahalliy POS marketingi bozor og‘riqlarini to‘g‘ridan-to‘g‘ri nomlaydi: "Buyurtma oshxonaga yetmaydi", "Stollar chalkashadi", "Mahsulot qayerga ketgani noma’lum", "Foyda taxminiy", "Internet o‘chsa, savdo to‘xtaydi". — [biznex.uz/restoran](https://www.biznex.uz/restoran); [biznex.uz](https://www.biznex.uz/) **[YETKAZIB BERUVCHI]**
- R-Keeper mijozlarining maqtovi (dilerning saytida): "15 yildan ortiq ishlaymiz… R_keeper juda kam ishdan chiqadi" (City Grill asoschisi); "5 yildan ortiq… juda mamnunmiz" ("Papin dom", Olmaliq). — [rkeeper.uz](https://rkeeper.uz/) **[YETKAZIB BERUVCHI]** (tanlab olingan ijobiy sharhlar)
- RAOS mijoz sharhi: "Stollar va oshxonaga zakaz to‘g‘ridan boradi, ofitsiantlar yugurmaydi… Kalkulyatsiya orqali real foydani ko‘ryapman" (kafe, Samarqand). — [raos.uz/kassa/kafe](https://raos.uz/kassa/kafe) **[YETKAZIB BERUVCHI]**
- ITDO sharhlar sahifasida haqiqiy sharh matnlari yo‘q, faqat "mamnun / mamnun emas" tugmalari bor. — [itdo.uz/otziv](https://itdo.uz/otziv/)

**MDH bo‘yicha umumiy sharhlar (O‘zbekistonga bog‘lanmagan)**
- R-Keeper: plyuslar — bozorda uzoq yillardan beri bor va tez-tez yangilanadi. Minuslar — ombor moduli alohida sotib olinadi, interfeys eskirgan, tizim yopiq. — [kirov.denvic.ru](https://kirov.denvic.ru/blog/obshchepit/iiko-i-r-keeper-sravnenie-dvukh-samykh-populyarnykh-sistem-avtomatizatsii-obshchepita/) **[ROSSIYA/UKRAINA BOZORI]**
- R-Keeper "og‘ir" yechim: murakkab konfiguratsiyani ishga tushirish uzoq davom etadi, modullar alohida sotib olinadi, qo‘llab-quvvatlash mahalliy hamkorga bog‘liq. — [ITDO](https://itdo.uz/bazaznaniy/iiko-ili-r-keeper/) (iiko integratori yozgan, xolis emas) **[YETKAZIB BERUVCHI]**
- iiko va R-Keeper sharhlari: iiko qo‘llab-quvvatlashi "sifatli, lekin juda sekin", R-Keeper qo‘llab-quvvatlashi "sifati past" degan fikrlar bor. Narx bo‘yicha qarama-qarshi fikrlar uchraydi. Sharhlarning ko‘pi 2019–2020-yillarga tegishli. — [a2is.ru, iiko sharhlari](https://a2is.ru/catalog/CRM-sistemy-kafe-restoran/iiko/reviews-iiko); [crmindex.ru, R-Keeper](https://crmindex.ru/products/r_keeper/reviews) (qidiruv xulosasi orqali, sahifalar to‘liq o‘qilmadi) **[ANIQ EMAS] [ESKI]**
- Poster: bir sharhda qo‘llab-quvvatlash va nosozliklar yomon baholangan, boshqasida interfeys va admin panel "juda qulay" deyilgan. Ukrainadagi sharhda tarif rejalarini o‘zboshimchalik bilan o‘zgartirish va oldindan to‘lov olishdan shikoyat qilingan. — [crmindex.ru, Poster](https://crmindex.ru/products/poster/reviews); [vidhuk.ua](https://www.vidhuk.ua/poster-pos/review-1482387) **[ROSSIYA/UKRAINA BOZORI] [ANIQ EMAS]**
- JOWi: a2is.ru’da 3,7/5. Bitta sharh foydalanish qulayligi va qo‘llab-quvvatlashni maqtaydi, boshqasi qo‘llab-quvvatlashga ulanish qiyinligidan shikoyat qiladi. — [a2is.ru, JOWi](https://a2is.ru/catalog/programmy-dlya-kafe-i-restoranov/jowi) (qidiruv xulosasi orqali, sahifa ochilganda bo‘sh edi) **[ANIQ EMAS]**

### Inferences
- Eng ko‘p takrorlanadigan og‘riqlar: **(1) internet va uskuna barqarorligi**, **(2) narx va yashirin qo‘shimcha to‘lovlar** (modullar, fiskal, o‘rnatish), **(3) qo‘llab-quvvatlash sifati va tezligi**, **(4) fiskal integratsiya bo‘yicha noaniqlik va jarima xavfi**. Rus tilidagi interfeys haqida to‘g‘ridan-to‘g‘ri shikoyat topilmadi. Lekin mahalliy POSlarning hammasi o‘zbek tilini asosiy afzallik sifatida ko‘rsatadi, bu esa talab borligini bildiradi. **[ANIQ EMAS]**
- Mahalliy SaaS’ning va’dalari (30 kun bepul sinov, karta talab qilinmaydi, 24/7 Telegram yordam, "yashirin to‘lovlar yo‘q") katta tizimlarning qabul qilingan kamchiliklariga javob sifatida tuzilgan.

### Gaps
- Telegram restoranchilar guruhlaridagi va YouTube’dagi o‘zbekcha sharhlar to‘planmadi (Telegram ichini qidirib bo‘lmadi). Bu eng katta bo‘shliq: haqiqiy foydalanuvchi ovozi uchun 5–10 restoranchi bilan intervyu tavsiya etiladi.
- Capterra yoki G2 uslubidagi, O‘zbekistonga xos sharhlar topilmadi.
- Fiskal integratsiya ishlamay qolishi bo‘yicha POS nomi ko‘rsatilgan konkret holatlar (2025–2026) topilmadi.

---

## 5. Yangi POS (Rizqim) uchun bo‘shliqlar va imkoniyatlar

### Takeaway
Eng katta imkoniyat — **dasturi yo‘q ~64% korxona** (BMTTD), ayniqsa kichik kafe, choyxona, to‘yxona va osh markazlari. 22.10.2026 dan "integratsiya qilinmagan buyurtma dasturlari"ga taqiq (fiskal_background.md) ular uchun majburiy talab tug‘diradi. Ularga quyidagilar kerak:
- arzon (≤ ~250–350 ming so‘m/oy), o‘zbek tilidagi tizim;
- offlaynga chidamli, Android asosida;
- fiskal **barcha tariflarga kiritilgan**;
- Telegram va QR orqali buyurtma hamda to‘lov o‘rnatilgan.

Choyxona va to‘yxona formatlariga (banket, depozit, kishi boshiga narx, "onlayn o‘tkazma" to‘lovlarini rasmiylashtirish) maxsus moslashgan raqobatchi topilmadi.

### Cited Findings
- Korxonalarning faqat 36% ida boshqaruv dasturi bor. — [BMTTD, 2026](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf)
- Choyxonalarning 45% i va to‘yxonalarning 38% i mijozga karta yoki naqd o‘rniga "onlayn o‘tkazma" (P2P) bilan to‘lashni taklif qiladi. BMTTD buni tushumni yashirishning asosiy usuli deb ko‘rsatadi. — [BMTTD, 2026, 44-bet](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf)
- Internet sifati (26%) va uskuna nosozligi (20%) chek bermaslikning asosiy sabablari. — [BMTTD, 2026](https://www.undp.org/sites/g/files/zskgke326/files/2026-05/ga_report_uzb.pdf)
- Mahalliy POSlardan faqat Biznex o‘zini "choyxonalar uchun" deb aniq ataydi, lekin choyxonaga xos funksiya (banket, kishi boshiga narx, xona yoki supa bandligi) saytda ko‘rinmadi. — [biznex.uz](https://www.biznex.uz/tariflar) **[YETKAZIB BERUVCHI]**
- Restoranlarda Telegram orqali buyurtma bor, lekin u agregatorlar (masalan, Uzum Tezkor Telegram boti, 2023) yoki buyurtma asosida yozilgan alohida botlar orqali ishlaydi. POS bilan birlashtirilgan Telegram buyurtma tizimi topilmadi. — [kursiv.media, 19.12.2023](https://uz.kursiv.media/uz/2023-12-19/uzum-tezkor-restoranlardan-taomlarni-buyurtma-qilish-uchun-telegram-bot-ishga-tushirdi/amp/) **[ESKI]**; [amaliyhayot.uz](https://amaliyhayot.uz/blog/telegram-mini-app) **[ANIQ EMAS]**
- Rahmat QR-to‘lov, choy puli, QR-menyu, bron va agregatorni (Yandex Eats, Express24, Uzum Tezkor) **mavjud POSlarga qo‘shimcha** sifatida sotadi. Bu funksiyalarga talab borligini, lekin ular POS ichida emasligini ko‘rsatadi. — [rhmt.uz/rest](https://rhmt.uz/rest/) **[YETKAZIB BERUVCHI]**
- Agregatorlar: Yandex Eats va Express24 birlashmoqda va bozorda ustun mavqega ega. Uzum Tezkor va Wolt ham bor. Clopos uchtasini (Yandex Go, Uzum, Wolt) qo‘llab-quvvatlaydi, RAOS’da bu integratsiyalar hali "ishlab chiqilmoqda". — [kun.uz, 26.10.2024](https://ad.kun.uz/en/news/2024/10/26/uzbekistans-food-delivery-market-deemed-monopolized-by-yandex-eats-and-express24); [clopos.com/uz](https://www.clopos.com/uz); [raos.uz](https://raos.uz/)
- Fiskal qo‘shimcha pul talab qiladi: Poster’da +28 $ va +13 $/oy, Biznex’da faqat Pro tarifida, iiko va R-Keeper’da Rahmat virtual kassasi uchun 123 600 so‘m/oy. — 3-bo‘limdagi manbalar.

### Inferences (Rizqim uchun tavsiya qilinadigan pozitsiya)
1. **"Choyxona va osh markazi rejimi"**: banket va to‘y buyurtmasi (oldindan to‘lov yoki depozit, kishi boshiga narx, menyu to‘plami), xona, supa yoki kabina bo‘yicha band qilish, "osh tugadi" stop-listi (qozon hajmi bo‘yicha porsiya qoldig‘i), kilogrammlab sotish (osh va go‘sht), xizmat haqi (10–15%) avtomatik qo‘shilishi. Bunday funksiyani sotadigan raqobatchi topilmadi.
2. **Telegram-native**: restoranning o‘z Telegram boti yoki mini-ilovasi orqali buyurtma, olib ketish va yetkazish to‘g‘ridan-to‘g‘ri POS va oshxona ekraniga tushadi. Click, Payme va Uzum orqali to‘lanadi va fiskal chek Telegram’da yuboriladi. Egasiga kunlik hisobot ham Telegram’da keladi. Rizqimda Telegram mini-ilova allaqachon bor, bu esa raqobat ustunligi.
3. **Fiskal "bepul va hamma tarifda"**, rasmiy yozma tasdig‘i bilan. Restoranchi jarimadan qo‘rqadi (2023-yilgi nizolar), shuning uchun "22.10.2026 talabiga mos" degan aniq va hujjatlangan va’da sotuvda hal qiluvchi bo‘ladi (fiskal_background.md, B variant ogohlantirishiga qarang).
4. **Offlayn-birinchi Android**: arzon Android planshet yoki SUNMI turidagi terminal, mahalliy navbat va keyin sinxronlash. Bu internet (26%) va uskuna (20%) muammolariga to‘g‘ridan-to‘g‘ri javob.
5. **Narx**: 1 nuqta uchun ~150–300 ming so‘m/oy oralig‘ida (Paloma365 va RAOS bilan raqobat), o‘rnatish va menyu kiritish bepul. Ixtiyoriy ravishda Android terminal ijarasi yoki muddatli to‘lovi bilan.
6. **O‘zbek tili** (lotin va kirill) va rus tili. Ofitsiant va oshpaz interfeysi juda sodda bo‘lishi kerak, chunki BMTTD xodimlarning texnik salohiyati pastligini aytadi.
7. **O‘rnatilgan agregator shlyuzi** (Yandex Eats/Express24, Uzum Tezkor, Wolt) va QR-to‘lov hamda choy puli. Bu Rahmat va Clopos ustunliklarini bitta mahsulotga yig‘adi.
8. **P2P "onlayn o‘tkazma"ni rasmiylashtirish**: dinamik QR (Click, Payme, Uzum) va avtomatik fiskal chek choyxona va to‘yxonalardagi kulrang amaliyotni qonuniy va qulay qiladi. Davlat ham buni rag‘batlantirmoqchi (BMTTD tavsiyasi), demak bu marketingda ham ishlatilishi mumkin.
9. **Raqobat xavfi**: Rahmat (to‘lov va fiskal) restoran funksiyalari tomon kengaymoqda. Biznex, RAOS va Alipos ham xuddi shu "o‘zbekcha arzon offlayn" segmentiga kirmoqda. Demak bo‘shliq tez yopilishi mumkin, shuning uchun farqni choyxona va to‘y formati hamda Telegram orqali ko‘rsatish kerak. **[ANIQ EMAS]**

### Gaps
- Choyxona, to‘yxona va osh markazlarining haqiqiy ish jarayonlari (banket, depozit, xizmat haqi, qarzga sotish) bo‘yicha birlamchi tadqiqot yo‘q. Joyida intervyu kerak.
- Restoranchilar POS uchun qancha to‘lashga tayyorligi bo‘yicha so‘rov ma’lumoti topilmadi.
- Agregatorlarning (Yandex Eats/Express24, Uzum Tezkor, Wolt) uchinchi tomon POSlari uchun API shartlari va hamkorlik talablari o‘rganilmadi.
- Alipos, Clopos va Biznex mahsulotlarini amalda sinab ko‘rish (demo) qilinmadi. Funksiya taqqoslash faqat ularning marketing saytlariga tayanadi.
