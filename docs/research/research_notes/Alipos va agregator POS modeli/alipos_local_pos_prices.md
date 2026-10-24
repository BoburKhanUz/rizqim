# Alipos va O‘zbekistondagi qolgan restoran POS mahsulotlarining narxlari (2026-yil oktabr)

> **Kurs.** Qayta hisoblashlar O‘zbekiston Markaziy banki kursida: **1 USD = 11 903 so‘m** (23.10.2026). Alipos saytining o‘z kalkulyatori 11 913,35 so‘m/$ dan foydalanadi, shuning uchun saytdagi “≈ so‘m” raqamlari biznikidan bir oz farq qiladi.
> **Sana.** “24.10.2026 da o‘qildi” deganlari shu kuni vendor sahifasidan to‘g‘ridan-to‘g‘ri olingan (HTML yuklab olinib o‘qildi). “Qidiruv xulosasi” deganlari faqat qidiruv tizimi parchasidan olingan, sahifaning o‘zida tasdiqlanmagan.
> **Belgilar.** [YETKAZIB BERUVCHI] — vendor da’vosi, sahifadan o‘qilgan; [OAV] — ommaviy axborot vositasi; [TASDIQLANGAN] — tadqiqotchi o‘zi tekshirgan fakt (masalan, sayt ochilmaydi); [ANIQ EMAS] — bitta ikkilamchi manba yoki qidiruv parchasi; [ESKI] — 2025-yildan oldingi; [XULOSA] — tadqiqotchining xulosasi.
> **Bu nota nimani kengaytiradi.** [competitor_pricing.md](../POS%20bozori%20narxlar%20va%20fiskal%20terminallar/competitor_pricing.md) (24.10.2026) da Alipos “saytda matn yo‘q” deb qolgan edi. Endi alipos.uz/ru to‘liq ochildi: 4 tarif, qurilma bo‘yicha qo‘shimchalar, o‘rnatish, fiskal, uskuna va agregator narxlari bilan to‘liq narx ro‘yxati bor. Qo‘shimcha: iiko’ning so‘mdagi narxi (Enter-co), R-Keeper UZQR va agregator integratsiyasi, Reca’ning 24 oylik narxlari, Mison va VKassa qayta tekshiruvi, Poster’ning O‘zbekistondagi fiskal variantlari, JOWi/Datapos/Paylov holati va agregator integratsiyalari jadvali. Oldingi notadagi narxlar bu yerda takrorlanmaydi, faqat o‘zgargan yoki tasdiqlanganlari ko‘rsatiladi.

---

## 1. Alipos nima, qaysi formatlarga mo‘ljallangan, tariflari, nimalar kiradi, qancha mijozi bor?

### Takeaway
Alipos — Samarqanddan chiqqan (2023), hozir Toshkentda ham ofisi bor restoran POS/ERP. U narxni **dollarda, har bir nuqta uchun oyiga** qo‘yadi: Start **25 $**, Standard **39 $**, Pro **59 $**, Enterprise **89 $ dan** (≈ 0,30 / 0,46 / 0,70 / 1,06 mln so‘m). Qo‘shimcha kassa, ofitsiant ilovasi, KDS va agregatorlar **har qurilma uchun alohida oylik to‘lov**. Bir martalik **o‘rnatish 1,5 mln so‘m**, fiskal (EPOS) 1,14–1,5 mln so‘m. Yillik to‘lovda **12 oy narxiga 16 oy** (amalda 25% chegirma). Bepul sinov yo‘q, faqat demo. Sayt **1 751 muassasa** deydi (vendor da’vosi), bu bozordagi eng katta e’lon qilingan mahalliy mijozlar bazasi. Agregator integratsiyasi **faqat Pro’dan**, har agregator **10 $/oy + 100 $ ulash**.

### Cited Findings

**Kompaniya, domenlar, kanallar**
- alipos.uz til tanlash sahifasi; mazmun /uz, /ru, /en da. Sarlavha: “AliPos — программа для ресторанов и кафе: касса, склад”. Ofislar: Toshkent, Yashnobod tumani, Sultonali Mashhadiy ko‘chasi 79; Samarqand, Gagarin ko‘chasi 36. Tel. +998 55 704-99-99. Platformalar: Android, iOS, Windows, Web — [alipos.uz/ru](https://alipos.uz/ru), 24.10.2026 da o‘qildi [YETKAZIB BERUVCHI]
- Mahsulotlar: AliPos POS (kassa), AliPos Waiter (ofitsiant), AliPos Boss (ega ilovasi), AliPos Web (bek-ofis), AliPos KDS (oshxona ekrani), AliPos Kiosk. App Store/Google Play’da “AliPos Waiter” va “AliPos Manager” ilovalari bor — [alipos.uz/ru](https://alipos.uz/ru); [Google Play, AliPos Waiter](https://play.google.com/store/apps/details?id=com.aliposwaiterapp&hl=en_US) [YETKAZIB BERUVCHI]
- Direktor: Jonibek Miralimov (saytdagi “direktorga yozish” formasi) — [alipos.uz/ru](https://alipos.uz/ru) [YETKAZIB BERUVCHI]
- Ish shakli: kassa Windows monoblok/PK’da; muassasaga **lokal server** qo‘yiladi, internet uzilsa kassa, ofitsiant va oshxona lokal tarmoqda ishlaydi, keyin bulut bilan sinxronlanadi. Interfeys o‘zbek va rus tillarida. “Ishga tushirish 1 kunda, menyuni o‘zimiz ko‘chiramiz” — [alipos.uz/ru](https://alipos.uz/ru) [YETKAZIB BERUVCHI]
- Telegram kanal **@alipos_uz** (“AliPos - Автоматизация ресторанного бизнеса”): 24.10.2026 da **6 obunachi**, postlar 19.05.2026 sanasida (yangilanishlar haqida). Unda narx posti yo‘q. Telegram-bot bo‘yicha yangilik: “Telegram-botdagi bildirishnomalar endi barcha tariflarda”, “ABC/XYZ tahlil — Pro va Enterprise’da” — [t.me/s/alipos_uz](https://t.me/s/alipos_uz), 24.10.2026 [YETKAZIB BERUVCHI]. @alipos nomli Telegram akkaunt kompaniyaga tegishli emas (begona shaxsiy profil) [TASDIQLANGAN]
- Instagram: @aliposuz bor — [instagram.com/aliposuz](https://www.instagram.com/aliposuz/) (kontent o‘qilmadi, login talab qiladi)

**Formatlar**
- Saytdagi “Yechimlar” ro‘yxati: restoran, kafe va qahvaxona, **choyxona**, milliy oshxona va oshxona (stolovaya), fastfud, bistro, bar, qandolatchilik va novvoyxona, fudkort, dark-kitchen, yetkazib berish, kichik muassasalar, tarmoqlar — [alipos.uz/ru](https://alipos.uz/ru), 24.10.2026 [YETKAZIB BERUVCHI]
- Choyxona sahifasi: “topchan va stollar sxemasi”, “palov va shashlik tannarxi”, “kompaniya hisobi bitta chekda”, qozon/mangal/salatlar uchun alohida printer yoki ekran, guruch/go‘sht/yog‘ hisobi, to‘y va tadbirlarga oldindan buyurtma. Tavsiya: **“Choyxonaga Standard mos keladi”** (39 $). Keys: Qashqadaryo choyxonasi, murakkab hisobni yopish 6 → 3 daqiqa — [alipos.uz/ru/solutions/dlya-chajhanyi](https://alipos.uz/ru/solutions/dlya-chajhanyi), 24.10.2026 [YETKAZIB BERUVCHI]

**Tariflar (har bir nuqta uchun oyiga; “Склад во всех тарифах”)** — [alipos.uz/ru/tariffs](https://alipos.uz/ru/tariffs), 24.10.2026 da o‘qildi [YETKAZIB BERUVCHI]

| Tarif | Oylik | Ekv. so‘m | Yillik (12 oy to‘lov = 16 oy) | Amaldagi oylik (16 oyga) | Tarkibi |
|---|---|---|---|---|---|
| **Start** — “bitta kassali kichik nuqta” | **25 $** | 297 575 | 300 $ (3 570 900) | 18,75 $ (223 181) | Kassa (POS), mahsulot katalogi, hisobotlar, **Telegram-bot: hisobot va bildirishnomalar**. Texkarta va KDS yo‘q |
| **Standard** — “zal va oshxonali kafe” | **39 $** | 464 217 | 468 $ (5 570 604) | 29,25 $ (348 163) | Start + ombor (kirim/chiqim), inventarizatsiya, texkarta va retseptlar, **CRM: mijozlar bazasi va qarzlar (nasiya)**, **KDS** |
| **Pro** (“Хит”) — “yetkazib berish va bir necha nuqta” | **59 $** | 702 277 | 708 $ (8 427 324) | 44,25 $ (526 708) | Standard + yetkazib berish moduli, **agregatorlar bilan integratsiya**, qo‘shimcha nuqta va filiallar, kol-markaz, **ofitsiant ilovasi (5 qurilma)** |
| **Enterprise** — “yirik restoran va tarmoqlar” | **89 $ dan** | 1 059 367 | 1 068 $ dan (12 712 404) | 66,75 $ (794 525) | Pro + shaxsiy menejer, maxsus ega paneli, **2 kassa terminali** |

- 10 dan ortiq nuqta yoki franshiza uchun — individual narx [YETKAZIB BERUVCHI]
- “Narxga nima kiradi? Tarifdagi barcha funksiyalar, yangilanishlar va **24/7 qo‘llab-quvvatlash**.” Tarifni istalgan vaqt almashtirish mumkin. To‘lov: **Payme, Click, Paynet yoki bank o‘tkazmasi**; yuridik shaxsga shartnoma va hisob-faktura — [alipos.uz/ru/tariffs](https://alipos.uz/ru/tariffs) [YETKAZIB BERUVCHI]
- Bosh sahifa FAQ: “**$25 dan oyiga (≈ 300 000 so‘m)**. Narx tarif va kassalar soniga bog‘liq, yashirin to‘lov yo‘q” — [alipos.uz/ru](https://alipos.uz/ru) [YETKAZIB BERUVCHI]

**Qo‘shimcha qurilmalar va litsenziyalar (oyiga, har bir dona uchun)** — [alipos.uz/ru/tariffs](https://alipos.uz/ru/tariffs), 24.10.2026 [YETKAZIB BERUVCHI]
- Kassa terminali (monoblok/noutbuk): **+9 $** (107 127 so‘m)
- Ofitsiant mobil ilovasi: **+5 $** (59 515 so‘m)
- Oshxona ekrani (KDS), Standard’dan: **+3 $** (35 709 so‘m)
- Kol-markaz qurilmasi, Pro’dan: **+9 $** (107 127 so‘m)
- O‘z-o‘ziga xizmat kioski: **+15 $** (178 545 so‘m)

**Bir martalik: o‘rnatish va sozlash (so‘mda)** — o‘sha manba [YETKAZIB BERUVCHI]
- Server o‘rnatish: 1 monoblok, 1 printer, o‘qitish (1 soat) — **1 500 000 so‘m** (126,02 $). FAQ: “Bir martalik — o‘rnatish va sozlash, o‘qitish bilan (1 soat) 1 500 000 so‘m va uskuna prays bo‘yicha”
- Qo‘shimcha terminal **250 000 so‘m/dona**; qo‘shimcha printer **250 000 so‘m/dona**; qo‘shimcha o‘qitish **150 000 so‘m/soat**; kabel va montaj **7 000 so‘m/metr**

**Yetkazib berish va qo‘shimcha yechimlar** — o‘sha manba [YETKAZIB BERUVCHI]
- Agregator integratsiyasini ulash va sozlash (Yandex Eats, Uzum Tezkor, Yemak): **100 $ bir martalik** (1 190 300 so‘m)
- Agregator integratsiyasi (Pro’dan): **10 $/oy** (119 030 so‘m), kalkulyatorda har agregator alohida belgilanadi
- Noor kuryer xizmati bilan integratsiya (Pro’dan): **50 $/oy** (595 150 so‘m)
- Mijozlar uchun mobil ilova (yetkazib berish, onlayn buyurtma; Pro’dan): **1 000 $ bir martalik** (11 903 000 so‘m)

**Uskuna (prays 14.10.2026, “so‘mda MB kursi bo‘yicha to‘lanadi”)** — [alipos.uz/ru/equipment](https://alipos.uz/ru/equipment), 24.10.2026 [YETKAZIB BERUVCHI]
- Monoblok 15″ **300 $**; monoblok 19″ **400 $**; monoblok 2×2 (mijoz ekrani bilan) **400 $**; printer 80 mm **50 $**; pul qutisi **70 $**; 2D-skaner **50 $**
- Tayyor to‘plamlar: “Start” (qahvaxona/kichik kafe: 15″ + printer + pul qutisi) **420 $** (≈ 5 004 000 so‘m saytda); “Restoran” (19″ + 2 printer, biri oshxonaga + pul qutisi) **570 $** (≈ 6 791 000); “Fastfud” (2×2 + printer + skaner + pul qutisi) **570 $**. “To‘plam narxi — faqat uskuna; o‘rnatish, fiskal va tarif alohida”
- O‘z uskunasi ham mumkin: Windows 10+, Intel Core i5 (6-avlod+), 4 GB RAM, SSD — [alipos.uz/ru/equipment](https://alipos.uz/ru/equipment) [YETKAZIB BERUVCHI]

**Fiskal**
- Prays-varaqda: “**Фискализация EPOS (USB-ключ и SIM-карта)**”: modulsiz **1 140 000 so‘m** (95,77 $), modul bilan **1 500 000 so‘m** (126,02 $), bir martalik — [alipos.uz/ru/tariffs](https://alipos.uz/ru/tariffs) [YETKAZIB BERUVCHI]
- Integratsiyalar katalogidagi fiskal hamkorlar: **Regos, E-POS, YesPOS, SmartOne** (“cheklarni avtomatik fiskalizatsiya qilish”). Markirovkalangan mahsulot bilan ishlaydi — [alipos.uz/ru/fiscalization](https://alipos.uz/ru/fiscalization); [alipos.uz/ru/integrations](https://alipos.uz/ru/integrations), 24.10.2026 [YETKAZIB BERUVCHI]
- FAQ: “AliPos cheklarni O‘zbekiston talablariga ko‘ra fiskalizatsiya qiladi va markirovkalangan mahsulot bilan ishlaydi” — [alipos.uz/ru](https://alipos.uz/ru) [YETKAZIB BERUVCHI]

**Boshqa integratsiyalar (katalog, “20+”)** — [alipos.uz/ru/integrations](https://alipos.uz/ru/integrations), 24.10.2026 [YETKAZIB BERUVCHI]
- To‘lov: Click, Payme, Rahmat (onlayn to‘lov); bosh sahifada Uzum, Paynet ham
- Yetkazib berish/onlayn buyurtma: **Yandex Eats, Uzum Tezkor, Yemak, Robosell, Delever**
- Hujjatlar va xabarlar: **Didox** (yuk xatlarini qabul qilish), **Telegram** (buyurtmalar, bekor qilishlar va smenalar haqida), **Play Mobile** (SMS)
- Qo‘shimcha modullar: KDS, sodiqlik kartasi, yetkazib berish (kuryerlar va zonalar), QR-menyu, Telegram-bot (bildirishnomalar). **Mijoz buyurtma beradigan Telegram-bot alohida mahsulot sifatida ko‘rsatilmagan** — Telegram faqat hisobot va bildirishnoma uchun

**Sinov, ulanish**
- Tarif va narx sahifalarida **bepul sinov muddati yo‘q**; o‘rniga “3 bosishda demo” (onlayn demo ish vaqtida 15 daqiqa ichida beriladi) va “15 daqiqada qayta qo‘ng‘iroq”. Ulanish bosqichlari: ariza → demo va hisob-kitob (30 daqiqa) → shartnoma va to‘lov (1 kun) → o‘rnatish va o‘qitish (1 kun) — [alipos.uz/ru/tariffs](https://alipos.uz/ru/tariffs), 24.10.2026 [YETKAZIB BERUVCHI]

**Dilerlar va hamkorlik dasturi** — [alipos.uz/ru/partners](https://alipos.uz/ru/partners), 24.10.2026 [YETKAZIB BERUVCHI]
- “Toshkent va Samarqanddagi ofislar va **viloyatlarda 140+ hamkor**” o‘rnatadi va o‘qitadi
- Autsors-o‘rnatuvchi: **har bir o‘rnatish uchun 450 000 so‘mdan**; yangi mijoz olib kelsa +1 oylik tarif bonus
- Diler: **mijoz to‘lovlarining 30–50%** (1–10 faol mijoz 30%, 11–24 — 35%, 25–35 — 40%, 36–49 — 45%, 50+ — 50%); o‘rnatish narxini diler o‘zi belgilaydi; shartnoma avval 2 yilga; 100+ mijozda hududiy eksklyuzivlik ko‘rib chiqiladi
- Restorator/texnolog: **mijoz oylik to‘lovining 30% — 2 yil davomida + 3 oylik to‘lov bonus**
- Referal: **1 oylik tarif** bonus

**Mijozlar soni va moliyalashtirish**
- Sayt: “**1 751 muassasa** AliPos’da ishlaydi” — [alipos.uz/ru](https://alipos.uz/ru), 24.10.2026 [YETKAZIB BERUVCHI]
- 25.11.2025: “**1 291 faol restoran**, har oy **107 ta yangi**”; “Taqdimot” teleko‘rsatuvida uch investordan **200 000 $** taklif — [Pivot.uz](https://pivot.uz/alipos-startup-received-a-200000-investment-offer-on-the-taqdimot-tv-show/) [OAV]
- 04.04.2025: AloqaVentures **80 000 $** ga 6,67% ulush oldi (baholash 1,2 mln $) — [Pivot.uz](https://pivot.uz/alipos-raises-80000-investment/) [OAV]
- 01.04.2025: 2023-yilda tashkil topgan; “**bir martalik joriy etish to‘lovi — har mijozga 200 $**, keyin obuna” — [Pivot.uz](https://pivot.uz/a-fully-self-funded-and-rebuilt-startup-alipos/) [OAV; ESKI narx modeli — hozir o‘rnatish 1,5 mln so‘m ≈ 126 $]
- Qidiruv parchasiga ko‘ra Alipos Imkon Ventures va Nexus Capital’dan 3,5 mln $ baholashda 200 000 $ olgan; sahifa 404 qaytardi — [insights.munich-startup.de](https://insights.munich-startup.de/news/feed/uzbek-horeca-saas-startup-alipos-raises-200k-at-3-5m-valuation-from-imkon-ventures-and-nexus-capital) [ANIQ EMAS]
- Taqqoslash uchun: 2024-yil boshidagi maqolada “830 dan ortiq biznes” — [qidiruv xulosasi](https://stanbase.tech/organization/alipos) [ANIQ EMAS]

### Inferences
- **Alipos’ning real narxi “25 $” emas.** Choyxona uchun o‘zi tavsiya qilgan konfiguratsiya: Standard 39 $ + 4 ta ofitsiant ilovasi (4 × 5 $) + 1 KDS (3 $) = **62 $/oy ≈ 737 986 so‘m/oy**. Birinchi oy naqd: 62 $ + o‘rnatish 1,5 mln + EPOS fiskal 1,14 mln + “Restoran” to‘plami 570 $ (6,78 mln) ≈ **10,2 mln so‘m**. Uskunasi bor choyxona uchun ≈ 3,4 mln so‘m [XULOSA, yuqoridagi narxlardan hisoblandi]. (Standard’da birinchi KDS ekrani bepulmi yoki har biri +3 $ mi — sahifada aniq emas.)
- **Yetkazib berishli kafe (Pro):** 59 $ + 2 agregator (2 × 10 $) = 79 $/oy ≈ **940 337 so‘m/oy**, ulash 200 $ (2,38 mln so‘m) bir marta. Ya’ni Alipos agregator uchun ham oylik, ham bir martalik pul oladi [XULOSA].
- Alipos’ning yillik taklifi (“+4 oy sovg‘a”) 25% chegirmaga teng — bu bozordagi eng saxiy (Clopos/BILLZ 20%, Paloma365/Poster 10%) [XULOSA].
- Dilerga 30–50% ulush — bu Alipos’ning tez o‘sishini (oyiga ~100 yangi muassasa) tushuntiradi: 140+ hududiy hamkor bor. Rizqim bunday diler tarmog‘isiz viloyatlarga kirishda kuchsiz bo‘ladi [XULOSA].
- Alipos’da **mijoz buyurtma beradigan Telegram-bot yo‘q** (Telegram faqat hisobot/bildirishnoma), mijoz ilovasi esa 1 000 $. Rizqim’ning Telegram buyurtma funksiyasi Alipos’ga nisbatan aniq farqlovchi [XULOSA].
- Dollarda narxlash (so‘mda “MB kursi bo‘yicha”) Alipos’ni Clopos va iiko bilan bir guruhga qo‘yadi, lekin saytda so‘m ekvivalentini ham ko‘rsatadi [XULOSA].

### Gaps
- Instagram (@aliposuz) postlari o‘qilmadi (login kerak); OLX’da Alipos dilerlarining e’lonlari qidirilmadi.
- Standard’da birinchi KDS ekrani va Start/Standard’da ofitsiant ilovasi umuman bormi (faqat +5 $ qo‘shimcha sifatida) — sahifadan aniq emas.
- EPOS fiskalidan keyin oylik to‘lov (OFD/SIM) bormi — prays-varaqda yozilmagan.
- “1 751 muassasa” mustaqil tasdiqlanmagan; 1 291 (11.2025) dan 1 751 (10.2026) ga o‘sish oyiga ~42 ta, bu “oyiga 107 yangi” da’vosidan kam — churn yoki da’volar mos kelmasligi mumkin.

---

## 2. Qolgan raqobatchilarning narxlari: qayta tekshiruv va yangi topilmalar

### Takeaway
Oldingi notadagi so‘mdagi narxlar 24.10.2026 da **o‘zgarmagan** (Mison 390k/690k, VKassa 99k–1 199k, Dastur-xon 99k, BROS 15 $, Clopos 59/99 $ va 20% yillik). Yangi: **iiko’ning so‘mdagi narxi** integrator Enter-co’da **484 000 / 991 500 / 1 332 000 so‘m/oy har terminal** (A-ONE’ning 54/81/108 $ dan farq qiladi); **Reca** 24 oylik shartnoma bilan sotadi (7,92 / 15,84 / 47,52 mln so‘m 24 oyga); **R-Keeper UZQR** ulash 200 000 so‘m + 50 000 so‘m/oy/kassa + 0,65%; Poster O‘zbekistonda uchta fiskal variantni (E-POS, ARCA, Fiscal Me/Box) qo‘llaydi, lekin asosiy tarif narxi hamon ochiq emas. **JOWi, Datapos, Paylov** — amalda restoran POS sifatida faol emas yoki aniqlanmadi.

### Cited Findings

**iiko — yangi integrator Enter-co, narx so‘mda**
- Enter-co.uz “Тарифы на подключение iiko”: **iiko for kafe 484 000 UZS/oy** (kichik kafe: kassa va mehmonga xizmat, bulutda hisob, **“Доставка на кассе и агрегаторы”**, tarmoqqa birlashtirish); **Pro 991 500 UZS/oy** (moliya, ombor, xodimlar, sodiqlik, API); **Enterprise 1 332 000 UZS/oy** (yangilik nazorati, kol-markaz va yetkazib berish bosqichlari, Wallet va RFM, franshiza). “Narx **bitta buyurtma qabul qilish terminali** uchun, uskunasiz.” Ofitsiant mobil ilovasi tavsifda bor, narxi alohida yo‘q. To‘lovlar: CLICK, Payme, Uzum, Alif Mobi, JOYDA, Tenge 24, Zoomrad. “O‘rnatish 3 soatdan” — [enter-co.uz](https://enter-co.uz/), 24.10.2026 da o‘qildi [YETKAZIB BERUVCHI]. Ekv.: 40,66 / 83,30 / 111,90 $
- Qidiruv parchasida Enter-co narxlari **688 500 va 1 032 750 UZS** sifatida ko‘rinadi (eski kesh bo‘lishi mumkin) — [qidiruv xulosasi, enter-co.uz](https://enter-co.uz/) [ANIQ EMAS; sahifada 24.10.2026 da 484 000 / 991 500 / 1 332 000]
- Taqqoslash: A-ONE CORP va Soft24 54 / 81 / 108 $ (642 762 / 964 143 / 1 285 524 so‘m) — oldingi nota, [rest.a-one.uz](https://rest.a-one.uz/) [YETKAZIB BERUVCHI]
- iikoWaiter App Store’da bepul ilova sifatida; iiko litsenziyasi uchun ofitsiant terminali/KDS narxi O‘zbekistonda topilmadi. Uchinchi tomon “Экран повара для iiko” (iiko 7.9+, Start–Enterprise tariflarida) — narxi so‘rov bo‘yicha — [qidiruv xulosasi, apps.apple.com](https://apps.apple.com/app/id641016373) [ANIQ EMAS]

**R-Keeper (BUSOFT) — UZQR va agregatorlar**
- UZQR ulash: **200 000 so‘m** bir martalik, kuzatib borish **50 000 so‘m/oy har kassa**, to‘lov komissiyasi **0,65%** (masalan, oyiga 10 mln so‘m QR tushumda 65 ming so‘m). Maqola sanasi **19.08.2026** — [rkeeper.uz, UZQR](https://rkeeper.uz/stati/post/uzqr-i-r-keeper-kogda-oplata-stanovitsya-chastyu-avtomatizacii-restorana), 24.10.2026 [YETKAZIB BERUVCHI]
- R-Keeper kassasi **Uzum Tezkor, Express 24 va Yandex Eda** bilan integratsiyalangan (maqola 11.06.2024); narxi ko‘rsatilmagan — [rkeeper.uz](https://rkeeper.uz/stati/post/r_keeper-integraciya-agregatorov-dostavki-edy-uzum-tezkor-express-24-yandex-eda) [YETKAZIB BERUVCHI]
- BUSOFT maqolasi qidiruv parchasiga ko‘ra kichik kafe uchun avtomatlashtirish “oyiga 1,5 mln so‘mdan”, o‘rta restoran “3–5 mln so‘mdan” — [qidiruv xulosasi, rkeeper.uz](https://rkeeper.uz/stati/post/avtomatizaciya-restoranov-v-tashkente-kak-uvelichit-pribyl-i-snizit-rashody) [ANIQ EMAS; sahifa o‘qilmadi]

**Mison — qayta tekshirildi**
- Start **390 000**, Polnyy **690 000 so‘m/oy/nuqta**; “kirish to‘lovi yo‘q”; “Pod klyuch — hisob bo‘yicha (uskuna va montaj)”. Polnyy’da: “o‘z boti, sayti va kol-markazi — 0% komissiya”, “**Yandex Eda, Uzum Tezkor, Tez Chakana — pod klyuch**”, “3 kunda”, kuryer ilovasi — [mison.uz/ru](https://mison.uz/ru/), 24.10.2026 [YETKAZIB BERUVCHI]
- Mison marketingi: “agregator orqali buyurtma — **har chekdan ~30% maydon komissiyasi**, o‘z kanalingizda 0%”; “yetkazib berish ulushi 30–50% — O‘zbekiston bozori ma’lumotlari, 2026-yil may” — o‘sha manba [YETKAZIB BERUVCHI; manbasi ko‘rsatilmagan]

**VKassa — qayta tekshirildi**
- MINI 99 000; STANDART 299 000 (1 filial, 1 kassa, KDS, mobil ilova, moliya, HR); BUSINESS 599 000 (“149 000 so‘m tejaysiz”, alohida olinsa 748 000; QR-menyu, CRM Pro 1 500 kontakt, AI 5M token, Telegram bot + dostavka); COMPLEX 1 199 000 (3 kassa + Upage 500 + CRM Business 3 000 + 10M token, “299 000 so‘m tejaysiz”); 14 kun bepul — [vkassa.uz](https://vkassa.uz/), 24.10.2026 [YETKAZIB BERUVCHI]. Yandex/Uzum agregatorlari nomi sahifada yo‘q.

**Dastur-xon — qayta tekshirildi**
- 99 000 so‘m/oy, “apparat shart emas, 5 daqiqada”, to‘lov **PayX** orqali; menyu/narx/xodimni **Telegram botdan** boshqarish. O‘zini raqobatchilar bilan solishtiradi: “**E-cafe’ning yarmi**”, boshqalar “qimmat (oyiga $50+)” — [dastur-xon.uz](https://dastur-xon.uz/), 24.10.2026 [YETKAZIB BERUVCHI]

**Reca (kafe.inware.uz) — shartnoma muddati aniqlandi**
- Narxlar **24 oyga**: **7 920 000**, **15 840 000** va **47 520 000 so‘m / 24 oy** (= oyiga 330 000 / 660 000 / 1 980 000 so‘m, ya’ni 665 / 1 331 / 3 992 $ 24 oyga). Telegram-bot orqali smena va kun hisobotlari — [kafe.inware.uz](https://kafe.inware.uz/), 24.10.2026 [YETKAZIB BERUVCHI]

**Paloma365**
- Bazaviy paket (kassa + ish joyi) yillik to‘lovda **114 500 so‘m/oydan** (oylik 134 700 dan); ichki onlayn-kassa fiskalizatsiya bilan — [qidiruv xulosasi, paloma365.uz/ru/solutions/public-catering](https://paloma365.uz/ru/solutions/public-catering) [ANIQ EMAS: sahifa 24.10.2026 da ochildi, “15 kun bepul” va “Mobil ofitsiant” ko‘rindi, lekin narx JS’da yuklanadi]. Ekv. 9,62 $

**Clopos, BROS — tasdiqlandi**
- Clopos: “Yillik obuna 20% foydaliroq” — [clopos.com/uz/pricing](https://www.clopos.com/uz/pricing), 24.10.2026 [YETKAZIB BERUVCHI]; tarif raqamlari JS’da, oldingi notadagi 59/99 $
- BROS: **15 $/oy** — [bros.uz](https://bros.uz/), 24.10.2026 [YETKAZIB BERUVCHI]

**Biznex, RAOS** — sahifalar 24.10.2026 da ochildi (200), lekin narxlar JS’da; oldingi notadagi raqamlar (Biznex 349k/549k; RAOS 249k/449k/799k) shu safar matnda qayta tasdiqlanmadi. RAOS’ning 15.07.2026 dagi blogi “249 000 so‘m/oydan, 30 kun bepul”ni takrorlaydi — [raos.uz/blog](https://raos.uz/blog/ozbekistonda-pos-tizimlari-royxati) [YETKAZIB BERUVCHI]

**Poster — O‘zbekiston**
- O‘zbekiston uchun alohida sahifa: uchta fiskal varianti — **E-POS** (rekvizitlar admin-panelda, alohida qurilmasiz), **ARCA** (sensorli alohida fiskal registrator), **Fiscal Me / Fiscal Box** (diler yechimi). Ichimliklar markirovkasi. Narx ko‘rsatilmagan; pastdagi blokda “**19 ₴ dan oyiga**” (Ukraina valyutasi) — [joinposter.com/fiscalization-in-uzbekistan](https://joinposter.com/fiscalization-in-uzbekistan), 24.10.2026 [YETKAZIB BERUVCHI]
- Poster-Uzbekistan dileri Prom.uz’da: “**kuniga 4 000 so‘mdan**” (≈ 120 000 so‘m/oy), obuna va sinov davri; e’lon ~2018-yildan — [prom.uz](https://www.prom.uz/ads/18141/poster-avtomatizatsiya-kafe-restorana-i-magazina/) [ESKI]

**JOWi — 2026 holati**
- Golden Pages: JOWI, Toshkent (Yakkasaroy), “restoranlarni avtomatlashtirish dasturi”, sayt www.jowi.club, kartochka **06.05.2026** da yangilangan — [goldenpages.uz](https://www.goldenpages.uz/uz/company/?Id=86605) [ANIQ EMAS: katalog yozuvi]
- 24.10.2026: jowi.club DNS javob bermaydi, jowi.uz **403** — [TASDIQLANGAN]. 2025–2026 yillarda JOWi haqida yangilik topilmadi.

**Datapos, Paylov**
- datapos.uz 24.10.2026 da javob bermadi (ulanish yo‘q); qidiruvda “Datapos” bo‘yicha natija yo‘q [TASDIQLANGAN: sayt ochilmaydi]
- Paylov — “OCTAGRAM” AJ, 12.2020 da tashkil topgan **mobil to‘lov xizmati**, biznes uchun onlayn to‘lov API; POS/restoran mahsuloti emas. Dealroom bo‘yicha ~13 xodim — [dealroom.co](https://dealroom.co/companies/paylov/) [ANIQ EMAS: agregator]; paylov.uz sahifasida faqat sarlavha (24.10.2026)

**Yangi topilgan nomlar (narx ochiq emas)**
- RAOS blogi (15.07.2026) ro‘yxati: iDo‘kon (bepul tarif bor), AlfaPOS, MillyPOS, Rahmat Kassa, Didox Kassa, MobilKassa — asosan chakana, narx so‘rov bo‘yicha; **Hippo POS** — “restoran va savdo nuqtalari uchun”, narx so‘rov bo‘yicha — [raos.uz/blog](https://raos.uz/blog/ozbekistonda-pos-tizimlari-royxati) [YETKAZIB BERUVCHI: raqobatchi tomonidan yozilgan]. hippopos.uz 24.10.2026 da javob bermadi.
- **E-cafe** — Dastur-xon o‘zini “E-cafe’ning yarmi” deb taqqoslaydi; E-cafe sayti yoki narxi topilmadi — [dastur-xon.uz](https://dastur-xon.uz/) [ANIQ EMAS]
- **Regos** — chakana ERP, Uzum Tezkor/Yandex Eats integratsiya hujjati bor; Alipos uchun fiskal hamkor sifatida ham ko‘rsatilgan — [docs.regos.uz](https://docs.regos.uz/ru/regos-sm/operations/settings-and-security/integrations/uzum-tezkor) [YETKAZIB BERUVCHI]

**Yangilangan narx jadvali (faqat bu notada o‘zgargan/qo‘shilgan qatorlar; 1 USD = 11 903 so‘m)**

| Mahsulot | Kirish tarifi | O‘rta tarif | Yuqori tarif | Birlik | Yillik chegirma | Bir martalik | Sinov | Manba, sana |
|---|---|---|---|---|---|---|---|---|
| **Alipos** | 25 $ (297 575) | 39 $ (464 217) / 59 $ (702 277) | 89 $ dan (1 059 367) | nuqta; +9 $ kassa, +5 $ ofitsiant, +3 $ KDS | **12 oy narxiga 16 oy (25%)** | o‘rnatish 1,5 mln; EPOS 1,14–1,5 mln; uskuna 420–570 $ | Yo‘q (demo) | [alipos.uz/ru/tariffs](https://alipos.uz/ru/tariffs), 24.10.2026 |
| **iiko (Enter-co)** | 484 000 (40,66 $) | 991 500 (83,30 $) | 1 332 000 (111,90 $) | terminal | — | ko‘rsatilmagan | — | [enter-co.uz](https://enter-co.uz/), 24.10.2026 |
| **Reca** | 330 000 | 660 000 | 1 980 000 | — | — | 24 oylik shartnoma (7,92 / 15,84 / 47,52 mln) | — | [kafe.inware.uz](https://kafe.inware.uz/), 24.10.2026 |
| **R-Keeper UZQR** (qo‘shimcha) | 50 000 so‘m/oy/kassa + 0,65% | | | kassa | — | 200 000 so‘m | — | [rkeeper.uz](https://rkeeper.uz/stati/post/uzqr-i-r-keeper-kogda-oplata-stanovitsya-chastyu-avtomatizacii-restorana), 19.08.2026 |
| **Paloma365** | 114 500 (yillikda) / 134 700 | | | nuqta | ~10–15% | o‘qitish bepul | 15 kun | qidiruv xulosasi [ANIQ EMAS] |

### Inferences
- **iiko so‘mda arzonlashgan ko‘rinadi**: Enter-co’da Start 484 000 so‘m (≈ 41 $) — boshqa integratorlarning 54 $ (≈ 643 ming) dan ~25% past. Bu yo integratorlar o‘rtasidagi narx raqobati, yo yangi “iiko for kafe” tarifi. Rizqim uchun ma’nosi: “brendli” iiko ham kichik kafe uchun oyiga ~0,5 mln so‘mga tushmoqda [XULOSA].
- Bozorda **uchta narxlash modeli** aniq ajraldi: (1) nuqta uchun yagona so‘m narxi (VKassa, Mison, Dastur-xon, Paloma365, RAOS, Biznex); (2) dollarda nuqta + har qurilmaga qo‘shimcha (Alipos, Poster); (3) dollarda/so‘mda har terminal uchun (iiko). Reca uzoq muddatli (24 oy) shartnoma bilan ajralib turadi [XULOSA].
- Kichik kafe uchun hech bir raqobatchida “to‘liq paket” (ofitsiant + KDS + agregator + fiskal) **oyiga 0,6 mln so‘mdan arzon emas**: Alipos Pro+2 agregator ≈ 0,94 mln, Mison Polnyy 0,69 mln, VKassa BUSINESS 0,6 mln, iiko Pro 0,99 mln [XULOSA].
- JOWi amalda bozordan chiqqan (sayt ishlamaydi, yangilik yo‘q); OLX’da eski mijozlarga xizmat ko‘rsatuvchi o‘rnatuvchilar qolgan. Datapos va Paylov raqobatchi emas [XULOSA].

### Gaps
- Poster’ning O‘zbekistondagi asosiy obuna narxi hamon topilmadi (sahifa Ukraina narxini ko‘rsatadi; joriy dilerlar ro‘yxati topilmadi).
- R-Keeper litsenziya/obuna narxi va BUSOFT agregator integratsiyasi narxi ochiq emas.
- iiko’ning ofitsiant terminali, KDS va agregator moduli O‘zbekistondagi alohida narxi topilmadi (Enter-co agregatorni kafe tarifiga kiritgan deb yozadi, A-ONE/Soft24 yozmaydi).
- Biznex, RAOS, Clopos va Paloma365 narxlari JS orqali yuklanadi — bu safar matnda qayta tasdiqlanmadi (oldingi nota 24.10.2026).
- Hippo POS, E-cafe, AlfaPOS, MillyPOS narxlari topilmadi.

---

## 3. Qaysi POS’lar Yandex Eats, Uzum Tezkor, Wolt bilan integratsiyalangan va buning uchun pul oladimi?

### Takeaway
O‘zbekistonda agregator integratsiyasi **yuqori tarifga** qo‘yilgan va ko‘pincha pullik. **Faqat Alipos aniq narx e’lon qilgan: Pro’dan (59 $), har agregator 10 $/oy + 100 $ ulash.** Mison uni Polnyy’ga (690 000 so‘m) kiritgan, iiko (Enter-co) kafe tarifiga (484 000 so‘m) kiritgan, R-Keeper bor deydi, lekin narxsiz. Mahalliy integratsiyalar asosan **Yandex Eats, Uzum Tezkor, Yemak, Tez Chakana** bilan; **Wolt** (Toshkentda 2024-yil oktabrdan) bilan O‘zbekistonda POS integratsiyasini e’lon qilgan mahalliy vendor topilmadi.

### Cited Findings

**Integratsiyalar jadvali (24.10.2026)**

| POS | Agregatorlar | Qaysi tarifda | Narxi | Manba |
|---|---|---|---|---|
| **Alipos** | Yandex Eats, Uzum Tezkor, Yemak; Robosell, Delever (yetkazib berish platformalari); Noor kuryer | **Pro’dan** (59 $) | **10 $/oy har agregator + 100 $ bir martalik ulash**; Noor 50 $/oy | [alipos.uz/ru/tariffs](https://alipos.uz/ru/tariffs); [/integrations](https://alipos.uz/ru/integrations) [YETKAZIB BERUVCHI] |
| **Mison** | Yandex Eda, Uzum Tezkor, Tez Chakana (“pod klyuch, 3 kunda”) | **Polnyy** (690 000 so‘m) | Alohida narx yozilmagan | [mison.uz/ru](https://mison.uz/ru/) [YETKAZIB BERUVCHI] |
| **iiko (Enter-co)** | “Доставка на кассе и агрегаторы” | iiko for kafe (484 000 so‘m)dan | Alohida narx yo‘q | [enter-co.uz](https://enter-co.uz/) [YETKAZIB BERUVCHI] |
| **R-Keeper (BUSOFT)** | Uzum Tezkor, Express 24, Yandex Eda | — | Ko‘rsatilmagan | [rkeeper.uz](https://rkeeper.uz/stati/post/r_keeper-integraciya-agregatorov-dostavki-edy-uzum-tezkor-express-24-yandex-eda) (11.06.2024) [YETKAZIB BERUVCHI] |
| **Clopos** | Uzum Tezkor (O‘zbekiston); boshqa bozorlarda Wolt, Bolt Food, Yango va b. | — | Ko‘rsatilmagan | [qidiruv xulosasi: Clopos vakilining LinkedIn posti](https://az.linkedin.com/in/rahidakhundzada) [ANIQ EMAS] |
| **Regos** (chakana ERP) | Uzum Tezkor, Yandex Eats | — | Ko‘rsatilmagan | [docs.regos.uz](https://docs.regos.uz/ru/regos-sm/operations/settings-and-security/integrations/uzum-tezkor) [YETKAZIB BERUVCHI] |
| **VKassa** | Yetkazib berish + o‘z Telegram boti; agregator nomlari yo‘q | BUSINESS (599 000) | — | [vkassa.uz](https://vkassa.uz/) [YETKAZIB BERUVCHI] |
| Paloma365, Biznex, RAOS, Dastur-xon, BROS, Reca | Saytlarda agregator integratsiyasi topilmadi | — | — | 24.10.2026 da tekshirildi [TASDIQLANGAN: sahifa matnida yo‘q] |

**Agregator bozori konteksti**
- Wolt Toshkentda **04.10.2024** da ishga tushgan (28-davlat), boshida 500 muassasa va 200 kuryer — [Kun.uz](https://kun.uz/en/news/2024/10/07/finnish-tech-giant-wolt-launches-delivery-service-in-uzbekistan); [Daryo](https://daryo.uz/en/2024/10/07/e-commerce-giant-wolt-enters-uzbekistan-1199288/) [OAV]
- Yandex O‘zbekiston Express24 aktivlarining bir qismini sotib oldi (25.10.2024), Yandex Eats va Express24 bitta platformaga birlashtirilishi rejalashtirilgan; Raqobat qo‘mitasi ularni oziq-ovqat yetkazib berish bozorida ustun deb tan olgan — [Kun.uz](https://m.kun.uz/en/news/2024/10/25/yandex-uzbekistan-expands-with-acquisition-of-express24-assets-plans-to-build-unified-food-delivery-platform); [Kun.uz](https://beta.kun.uz/en/news/2024/10/26/uzbekistans-food-delivery-market-deemed-monopolized-by-yandex-eats-and-express24) [OAV]
- **Uzum Tezkor 14.09.2026** dan restoranlarning **o‘z kanallari** (telefon, Telegram, ilova, ijtimoiy tarmoqlar) buyurtmalarini o‘z kuryerlari bilan yetkazib berish xizmatini ishga tushirdi; 13 shaharda; “shaffof tariflash”, kompaniyalarning ish jarayoniga integratsiya qilinadi; narx e’lon qilinmagan — [Spot.uz](https://www.spot.uz/oz/2026/09/14/uzum-tezkor/) [OAV]
- Mison’ning marketing da’vosi: agregator komissiyasi ≈ **30% har chekdan** — [mison.uz/ru](https://mison.uz/ru/) [YETKAZIB BERUVCHI; mustaqil tasdiqlanmagan]

### Inferences
- Bozor standarti: agregator integratsiyasi **o‘rta-yuqori tarifning (0,48–0,94 mln so‘m/oy) bir qismi** yoki alohida pullik (Alipos: ~119 ming so‘m/oy har agregator + ~1,19 mln so‘m ulash). Rizqim uni pastki tarifga kiritsa yoki arzon qo‘shimcha (masalan, 50–100 ming so‘m/oy) qilib bersa, bu kuchli narx argumenti bo‘ladi [XULOSA].
- Uzum Tezkor’ning yangi B2B kuryer xizmati (09.2026) Rizqim uchun alohida imkoniyat: Telegram orqali kelgan o‘z buyurtmalarini Uzum kuryeriga uzatish integratsiyasi — 30% agregator komissiyasisiz yetkazib berish. Alipos’da bunga o‘xshash Noor kuryer integratsiyasi 50 $/oy turadi — narx mo‘ljal [XULOSA].
- Wolt bilan O‘zbekistonda hech bir mahalliy POS integratsiyasini e’lon qilmagan — Rizqim uchun ham ustuvor emas; birinchi navbatda Yandex Eats va Uzum Tezkor [XULOSA].

### Gaps
- Yandex Eats va Uzum Tezkor’ning restoranlar uchun komissiya foizi (rasmiy) bu tadqiqotda tekshirilmadi; 30% — faqat Mison da’vosi.
- Uzum Tezkor B2B kuryer xizmatining narxi va API/POS integratsiya shartlari e’lon qilinmagan.
- Mison, iiko va R-Keeper agregator integratsiyasi uchun alohida ulash to‘lovi olinadimi — saytlarda yo‘q.
- Clopos’ning O‘zbekistondagi Uzum Tezkor integratsiyasi faqat LinkedIn postiga asoslangan, rasmiy sahifada tasdiqlanmadi.
