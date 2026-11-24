# Statistika va analitika rejasi (P to'lqini)

Holat: **reja**, kod yozilmagan. Tuzildi: 24.11.2026, `p-analytics-plan` tarmog'ida. Egasining so'zi: "Mana kamchilik...
yaxshilab rejalashtirib qurish zarur". Hujjatning birinchi qismi ega uchun oddiy tilda, oxiridagi **A ilova** (inglizcha)
quruvchilar uchun: jadvallar, endpointlar, ishlar, testlar va har qadamning fayllari.

Mazmuni:

1. [Ega uchun bir sahifada](#1-ega-uchun-bir-sahifada)
2. [Hozir nima bor va so'rovnomadagi tuzatishlar](#2-hozir-nima-bor-va-sorovnomadagi-tuzatishlar)
3. [Hamma hisobotlar uchun bitta qoida](#3-hamma-hisobotlar-uchun-bitta-qoida)
4. [Sakkizta bo'shliq: nima, qanday hisoblanadi, qayerda ko'rinadi](#4-sakkizta-boshliq)
5. [Cheklovlar](#5-cheklovlar)
6. [Qurish tartibi](#6-qurish-tartibi)
7. [Ega hal qiladigan savollar](#7-ega-hal-qiladigan-savollar)
8. [Ataylab qilinmaydiganlar](#8-ataylab-qilinmaydiganlar)
9. [Maxfiylik siyosatidagi o'zgarishlar (matn loyihasi)](#9-maxfiylik-siyosatidagi-ozgarishlar)

A ilova (English, builders): [A. Technical appendix](#a-technical-appendix)

---

## 1. Ega uchun bir sahifada

Bugun restoran egasi savdoni, eng ko'p sotilgan taomlarni, kanal va filial bo'yicha tushumni, smena X/Z ni ko'radi; siz
(RIZQIM) — Bugun sahifasi, oylik daromad, MRR, churn va promo-kod sonlarini. **Yo'q narsalar:** mijoz Mini App'da qayerda
"tushib qolgani" (voronka), qaysi reklama qancha mijoz va pul olib kelgani, yangi va qaytgan mijozlar, soat va hafta kuni
bo'yicha savdo, restoran uchun davr bo'yicha "pul akti" (komissiya, qaytarish, payout), keshbek qancha berilgani va
ishlatilgani, hamkor paneli, boshqaruv markazi va kassa ilovalaridagi xatolar.

Har qadamdan keyin nima ko'rasiz (tartib bo'yicha; qadamlar raqami 6-bo'limdagi jadvalda):

| Bosqich | Qadamlar | Kim | Nimani ko'radi |
|---|---|---|---|
| **A. Ma'lumot yig'ish boshlanadi** (ishga tushishdan oldin) | 1–5 | hech kim (hali ekran yo'q) | Maxfiylik siyosati yangilanadi. Mini App ichidagi harakatlar (ochildi → restoran ko'rildi → savatga qo'shildi → buyurtmaga o'tdi → buyurtma berildi) o'z serverimizga yozila boshlaydi. Reklama havolalari (`c_kod`) va saytdagi `utm` belgilari sanala boshlaydi. Shunday qilib birinchi haqiqiy mijoz kelganda statistika bo'sh bo'lmaydi |
| **B. Xatolarni ko'rish** (ishga tushishdan oldin) | 6–8 | siz (Monitoring) | Hamkor paneli, boshqaruv markazi va kassa ilovalarida xato chiqsa, Monitoring → "Ilova xatolari" ro'yxatida ko'rinadi, ko'payib ketsa ogohlantirish keladi. Hech qanday chet el xizmati yo'q |
| **C. Restoran savdosi chuqurroq** (ishga tushishdan oldin) | 9–10 | restoran egasi, menejer | Soat va hafta kuni bo'yicha savdo ("qachon eng band"), o'tgan davr bilan solishtirish (+12% kabi), filiallarni yonma-yon solishtirish (o'rtacha chek, bekor qilish, tayyorlash vaqti) |
| **D. Pul akti** (birinchi payoutdan oldin) | 11–12 | restoran egasi; siz ham | Tanlangan davr uchun: RIZQIM orqali kelgan pul, komissiya, qaytarilgan komissiya, qaytarishlar, obuna, ushlangan qarz, kartaga o'tgan payout, davr boshi va oxiridagi qoldiq. CSV va Excel'ga yuklab olish, chop etish (brauzer orqali PDF). Raqamlar **faqat ledger'dan** — moliya sahifasidagi yozuvlar bilan tiyinigacha mos |
| **E. Mijozlar** (birinchi haqiqiy buyurtmalardan keyin) | 13–15 | restoran egasi; siz | Yangi va qaytgan mijozlar, necha marta buyurtma berishadi, oylik "qaytish" jadvali (kogorta), bir mijozdan o'rtacha tushum (LTV). Restoran faqat o'z mijozlarini, ismi qisqartirilgan holda ko'radi (7-bo'lim, 2-savol) |
| **F. Voronka va reklama** (birinchi haftalik ma'lumotdan keyin) | 16–17 | siz; restoran o'z voronkasini | Mini App voronkasi (har qadamda nechta odam qoldi), qaysi taom ko'p ko'riladi-yu kam olinadi, har reklama havolasi bo'yicha: tashriflar, ro'yxatdan o'tganlar, birinchi buyurtmalar, tushum, komissiya; do'st taklifi natijalari |
| **G. Keshbek va promo** | 18 | siz | Keshbek ballari qancha berildi, ishlatildi, qaytdi, hozir mijozlar qo'lida qancha (majburiyat); promo-kod yangi mijoz olib keldimi yoki eski mijozga chegirma bo'ldimi |
| **H. Restoran uchun qolganlari** | 19–21 | restoran egasi | Bekor qilish sabablari va yo'qotilgan tushum, tezlik va baho dinamikasi, restoran yopiq turgan vaqt, har kuni ertalab Telegram'ga kechagi qisqa hisobot |

Muhim: **ma'lumot yig'ishni (A) imkon qadar erta boshlash kerak** — o'tgan kunlarning Mini App harakatlarini keyin
tiklab bo'lmaydi. Savdo, mijoz, kogorta va pul hisobotlari esa mavjud buyurtma va ledger yozuvlaridan **orqaga qarab
to'ldiriladi**, shuning uchun ular birinchi kundan bo'sh bo'lmaydi.

Sizdan kerak bo'ladigan qarorlar 7-bo'limda (hammasi ha/yo'q yoki A/B, har biriga tavsiyam bor). Eng muhimlari: Mini App
harakatlari necha oy saqlanadi (tavsiya: 3 oy) va restoran "doimiy mijozlar" ro'yxatida ism va telefonni ko'radimi
(tavsiya: yo'q, faqat "Aziz R." va niqoblangan raqam).

---

## 2. Hozir nima bor va so'rovnomadagi tuzatishlar

Kod o'qib tekshirildi. So'rovnoma (P_ANALYTICS_RULES.md) asosan to'g'ri, lekin quyidagilar aniqlashtirildi yoki tuzatildi:

1. **Vaqt mintaqasi.** Restoran hisobotlari qat'iy `Asia/Tashkent` emas, **restoranning o'z mintaqasi** (`stores.timezone`,
   standart `Asia/Tashkent`, `App\Support\StoreTimezone`) bo'yicha. RIZQIM (platforma) hisobotlari — `Asia/Tashkent`
   (`PlatformStats::TIMEZONE`, `config('billing.timezone')`). Reja shuni saqlaydi: restoran ekranlari — restoran
   mintaqasi, boshqaruv markazi — Toshkent. Hozir hamma restoran Toshkentda, raqamlar bir xil chiqadi.
2. **Filiallarni solishtirish qisman bor.** `GET /owner/reports/sales` allaqachon `by_branch` (buyurtmalar, yetkazilgan,
   tushum) qaytaradi va hamkor panelida "Filial bo'yicha" bloki bor (`partner/web/src/components/reports/ReportsView.tsx`).
   Yo'g'i: o'rtacha chek, bekor qilish ulushi, tayyorlash vaqti va yonma-yon jadval.
3. **"Keshbek" foizli keshbek emas.** Kodda buyurtmadan foiz qaytaradigan keshbek yo'q. `users.balance` faqat ikki yo'l
   bilan to'ladi: do'st taklifi bonusi (taklif qilingan mijozning 50 000 so'm va undan katta birinchi yetkazilgan buyurtmasi
   uchun taklif qilganga 5 000 ball, `LedgerService::processOrderDelivered`) va qaytarishda balansga qaytgan pul. Sarflash —
   butun buyurtmani `keshbek` usuli bilan to'lash. **Bonusning narxini restoran to'laydi**: u restoran qarziga
   `commission` turidagi yozuv sifatida tushadi (izohi `Referral cashback reward cost`), keshbek bilan to'langan buyurtma
   puli esa restoranga RIZQIM tomonidan haqiqiy pul bilan (netting orqali) o'tkaziladi. Demak "keshbek xarajati" hisoboti
   aslida **ballar harakati va majburiyat** hisoboti (4.6). Bu ega bilmasligi mumkin bo'lgan muhim fakt — 7-bo'lim, 11-savol.
4. **Promo samaradorligi qisman bor.** `App\Services\Admin\Marketing\PromoCodes::stats` har kod uchun ishlatilish soni,
   noyob mijozlar, chegirma jami va GMV beradi. Yo'q: kod **yangi** mijoz olib keldimi, keyin mijoz qaytdimi. Chegirmani
   amalda restoran ko'taradi (komissiya chegirmadan keyingi summadan olinadi, `CreateOrderAction`).
5. **Telegram `start_param` uch xil:** `invite_<token>` (xodim taklifi), `ref_` / `referrer_` / `rizqim_<id>` (do'st
   taklifi) va `store_<uuid>`. `store_` mijozni restoran sahifasiga olib bormaydi: bot `/start` da u restoran chatini
   bog'lash uchun, Mini App kirishida esa faqat kontekst. Mijozning do'st taklifi havolasi bot `?start=` havolasi
   (`CustomerProfile.tsx`), Mini App `startapp` emas; ro'yxatdan o'tmagan odam uchun taklif qiluvchi **Redis keshida**
   30 kun saqlanadi (`ReferralService::storePendingReferral`). Reklama kampaniyasi uchun hech narsa yo'q (`utm` va
   `campaign` kodda umuman uchramaydi).
6. **Oylik komissiyaning ikki xil ta'rifi allaqachon bor:** `CommissionStats::inMonth` — ledger yozuvlari, yozilgan sanasi
   bo'yicha; `RevenueReportService` va `MonthlyRevenueReport` — buyurtmalarning `commission_amount`, birinchi `delivered`
   holat vaqti bo'yicha. Ular hisob-kitob qayta urinilgan (`settlement_failed_at`) buyurtmalarda farq qilishi mumkin.
   Restoran akti **ledger** ta'rifini oladi va testda ikkalasi bilan solishtiriladi (A.1).
7. **"Tushum" bilan "GMV" bir xil narsa emas.** Restoran tushumi — yaratilgan kuni bo'yicha **yetkazilgan** buyurtmalar
   `total_price` yig'indisi (qisman qaytarishlar ayirilmaydi). Bugun sahifasidagi GMV — yaratilgan kuni bo'yicha bekor
   qilinmagan (jarayondagilar ham) buyurtmalar. Oylik daromad hisobotidagi GMV — yetkazilgan vaqti bo'yicha. Reja bularni
   o'zgartirmaydi, har ekranda qaysi biri ekanini yozadi.
8. **Mobil ilovalar.** `partner/mobile` va `customer/mobile` hozircha faqat README. Flutter ilovalar — to'rtta POS ilova
   (`pos/apps/cashier`, `waiter`, `kds`, `customer_display`). Ularda xato yig'ish umuman yo'q (`FlutterError.onError` ham).
9. **So'raladigan (polling) ekranlar.** Hamkor panelining bosh sahifasi `GET /owner/analytics?period=today` ni har 60
   soniyada so'raydi (`partner/web/src/components/Dashboard.tsx`), Bugun sahifasi `GET /admin/today` ni. Reja ularga
   hech narsa qo'shmaydi.
10. **Grafiklar.** Grafik kutubxonasi yo'q va kerak emas: hamkor panelida `RevenueChart`, boshqaruv markazida `BarChart`
    va `MovementChart` — oddiy HTML/CSS ustunlar, yonida jadval ko'rinishi. Soat × hafta kuni "issiqlik xaritasi" ham
    CSS grid bilan chiziladi.
11. **Excel uchun yangi kutubxona kerak emas**: `openspout/openspout` backend'da bor (boshqaruv markazi eksportlari,
    banket va korporativ hisobotlar). **PDF uchun kutubxona yo'q** — u yangi dependency bo'lardi, shuning uchun PDF
    brauzerning "Chop etish → PDF sifatida saqlash" orqali (5-bo'lim).
12. **Maxfiylik siyosati** (`infra/landing/site/i18n/uz.js`, `ru.js`, 7-bo'lim) hozir "uchinchi tomon analitika
    tizimlari ishlatilmaydi" deydi va Mini App qurilmada saqlaydigan narsalarni sanaydi. O'z serverimizdagi statistika
    bunga zid emas, lekin 2, 4, 5, 6 va 7-bo'limlarga qo'shimcha kerak (9-bo'limda tayyor matn). Yandex Metrika qo'shilsa
    bu jumla yolg'on bo'lib qoladi.

---

## 3. Hamma hisobotlar uchun bitta qoida

- **Pul faqat ledger'dan.** Restoran qarzi (`store_transactions`) va hamyoni (`store_wallet_transactions`) — pulning
  yagona manbasi. Komissiya, qaytarilgan komissiya, hisob-kitob (RIZQIM yig'ib restoranga o'tkazgan pul), payout,
  obuna, do'st bonusi — hammasi shu yozuvlardan yig'iladi. Analitika jadvallari pul **saqlamaydi** (faqat
  savdo/GMV kabi buyurtmadan olingan ma'lumot raqamlarini, va ular har doim qayta hisoblanadi).
- **Savdo raqamlari mavjud hisobot bilan bir xil.** Yangi har bir savdo ko'rsatkichi `OwnerAnalyticsService` va
  `SalesReportService` dagi ta'rifni oladi: buyurtma — yaratilgan kunida; tushum — yetkazilganlar `total_price`;
  bekor = `canceled` + `rejected`; `refunded` tushum emas. Shunda yangi ekran va eski ekran bir xil raqam ko'rsatadi.
- **Kun** — restoran mintaqasidagi (restoran ekranlari) yoki Toshkentdagi (boshqaruv markazi) kalendar kuni.
- **Restoran faqat o'z ma'lumotini ko'radi** (do'kon filtri, RLS). Boshqa restorandagi buyurtmalar, boshqa restoranning
  mijozi bo'lgan-bo'lmagani hech qachon chiqmaydi.
- **Restoran mijoz haqida bugungidan ortiq bilmaydi.** Bugun: ega, menejer, kassir, ofitsiant buyurtma kartasida mijoz
  ismi, telefoni va manzilini ko'radi (`orders.manage`, buyurtmani bajarish uchun); oshpaz ko'rmaydi; CSV'ga telefon
  yozilmaydi; mijozlar ro'yxati umuman yo'q. Yangi mijoz hisobotlari shu chegarada qoladi (4.3).
- **Ma'lumot O'zbekistonda.** Hamma yangi jadval asosiy bazada (O'zbekistondagi server). Tashqi analitika xizmati yo'q.

---

## 4. Sakkizta bo'shliq

Har bo'shliq uchun: nima ko'rinadi, qanday hisoblanadi, qayerda, kim ko'radi. Jadval, endpoint, ish va testlarning aniq
nomlari A ilovada.

### 4.1. Mini App voronkasi (1-bo'shliq)

**Nima ko'rasiz.** Boshqaruv markazi → Ko'rsatkichlar → "Voronka": tanlangan kunlar uchun Mini App sessiyalari va har
qadamga yetganlar soni va ulushi; platforma (iOS, Android, Telegram Desktop, Web) va reklama havolasi bo'yicha filtr.
Restoran (hamkor paneli → Hisobotlar → "Voronka"): o'z sahifasini ko'rgan sessiyalar → savatga qo'shish → buyurtmaga
o'tish → buyurtma → tasdiqlangan → yetkazilgan; va "Menyu konversiyasi": har taom necha marta ochildi, necha marta
savatga qo'shildi, necha dona sotildi.

**Qadamlar va ta'riflar.**

- **Sessiya** — Mini App'ning bir ochilishi: ilova ochilganda tasodifiy raqam (UUID) yaratiladi, 30 daqiqa harakatsizlikdan
  keyin yangisi. Sessiya hisobga hech qachon bog'lanmagan bo'lsa ham (kirish xato bergan bo'lsa) sanaladi.
- Qadam "bajarilgan" — sessiyada shu qadamning kamida bitta hodisasi bo'lsa. Shuning uchun bir hodisa ikki marta
  kelsa ham natija o'zgarmaydi.
- Qadamlar: `ochildi` → `restoran ko'rildi` → `savatga qo'shildi` → `buyurtmaga o'tildi` (savatdan "Buyurtma berish") →
  `buyurtma berildi` (server buyurtmani yaratgan va u shu mijozniki ekanini tekshirgan) → `tasdiqlangan` (onlayn to'lov
  `paid`, naqd/keshbek buyurtmani restoran qabul qilgan) → `yetkazilgan` (hozirgi holati `delivered`).
- Konversiya = qadam / oldingi qadam, va qadam / sessiyalar. Foiz bir xona aniqlikda.
- Oxirgi uch qadam **buyurtmalar jadvalidan** olinadi, ilovadan kelgan "buyurtma berildi" hodisasi faqat qaysi
  sessiyaga tegishli ekanini bog'laydi. Hodisasi kelmagan buyurtma (ilova yopilib qolgan) jami buyurtmalarda bor, faqat
  voronkada "sessiyasiz" qatorida.
- Restoran, xodim va RIZQIM jamoasi hisoblari (roli `customer` emas) voronkaga kirmaydi — ega o'z menyusini ko'rib
  raqamni shishirmaydi.

**Qanday yig'iladi.** Mini App kichik paketlarda (10 soniyada bir yoki ilova yopilayotganda) `POST /api/v1/analytics/events`
ga yuboradi. Faqat kirgan mijoz tokeni bilan (Telegram imzosi tekshirilgan foydalanuvchi). Hodisa nomlari qat'iy ro'yxat,
qo'shimcha maydonlar ruxsat etilganlari bilan cheklangan (taom id, savat summasi, dona soni). **Yozilmaydi:** qidiruv
matni, IP manzil, brauzer satri, joylashuv, telefon. Kirishdan oldingi hodisalar (bir soniyacha) ilova xotirasida turadi
va kirgandan keyin yuboriladi.

**Saqlash.** Batafsil yozuvlar oylik bo'laklarga bo'lingan jadvalda (`analytics_events`), tavsiya: 3 oy (7-bo'lim,
1-savol), keyin bo'lak arxivsiz o'chiriladi. Kunlik yig'indilar (`analytics_funnel_daily`, `analytics_product_daily`)
shaxssiz va muddatsiz qoladi.

**Orqaga to'ldirish yo'q**: harakatlar faqat 3-qadam ishga tushgan kundan. Ekran "Ma'lumot DD.MM.YYYY dan" deb yozadi.

### 4.2. Reklama va do'st taklifi (2-bo'shliq)

**Nima ko'rasiz.** Boshqaruv markazi → Marketing → "Kampaniyalar": kampaniya yaratish (nomi, kanal: Telegram reklama,
Instagram, varaqa, bloger, restoran QR, sayt, boshqa; boshlanish va tugash; ixtiyoriy xarajat), uning tayyor havolalari
(Mini App: `https://t.me/<bot>/<app>?startapp=c_<kod>`, bot: `https://t.me/<bot>?start=c_<kod>`, sayt:
`https://rizqim.uz/?utm_source=...&utm_campaign=<kod>`) va hisoboti. "Do'st taklifi" va "Sayt" bo'limlari ham shu yerda.

**Kampaniya hisoboti (har kampaniya, tanlangan davr):**

- **Tashriflar** — shu kod bilan ochilgan Mini App sessiyalari + saytda shu `utm_campaign` bilan ochilgan sahifalar
  (alohida ustun).
- **Ro'yxatdan o'tganlar** — davrda yaratilgan va **birinchi** kelishi shu kampaniyaga yozilgan mijozlar (birinchi teginish:
  hisob yaratilganda serverda yoziladi, keyin hech qachon o'zgarmaydi).
- **Telefon tasdiqlaganlar** — ulardan `phone_verified_at` borlari.
- **Birinchi buyurtma** — ulardan platformadagi birinchi yetkazilgan buyurtmasi davrda bo'lganlar.
- **GMV (30 kun)** — shu mijozlarning ro'yxatdan o'tgandan keyingi 30 kun ichida yaratilgan yetkazilgan buyurtmalari
  `total_price` yig'indisi (oyna — 7-bo'lim, 5-savol). "Hammasi" ustuni — muddatsiz.
- **Komissiya (30 kun)** — o'sha buyurtmalar bo'yicha **ledger**dagi komissiya, qaytarilgani ayirilgan
  (`CommissionStats` qoidasi, buyurtma bo'yicha).
- **Bir mijoz narxi** — xarajat kiritilgan bo'lsa: xarajat / birinchi buyurtma bergan mijozlar.

**Do'st taklifi hisoboti:** taklif qilganlar soni, taklif bilan ro'yxatdan o'tganlar (`users.referred_by`), bonus olgan
taklif (ledgerdagi bonus yozuvlari soni), bonus jami (ledgerdagi summa, uni restoranlar to'lagan — 2-bo'lim, 3-band),
taklif qilinganlarning GMV'si; eng faol taklif qiluvchilar ro'yxati (ism, telefon `admin.pii.view` siz niqoblangan).

**Restoranning o'z havolasi (keyinroq, 7-bo'lim, 6-savol):** restoran hamkor panelida o'z QR va havolasini oladi
(`startapp=s_<restoran>` — Mini App to'g'ridan-to'g'ri restoran sahifasini ochadi; hozir bunday havola yo'q) va o'z
kampaniyasining natijasini ko'radi.

**Sayt:** `rizqim.uz` sahifasi ochilganda brauzer bir marta `POST /api/v1/landing/visits` yuboradi: sahifa, `utm_*`,
kelgan sayt nomi (faqat domen), til. Server faqat kunlik sonni oshiradi: cookie, identifikator, IP saqlanmaydi. Saytdagi
"Telegram'da ochish" tugmalari `utm_campaign` kampaniya kodi bo'lsa `?start=c_<kod>` bilan ochiladi, shunda saytdan
kelgan mijoz ham kampaniyaga yoziladi. "Hamkor bo'lish" arizasi `utm_*` ni ham yuboradi — Ko'rsatkichlardagi ariza
voronkasi kampaniya bo'yicha ham ko'rinadi.

### 4.3. Mijozlar: yangi, qaytgan, kogorta, LTV (3-bo'shliq)

**Ta'riflar (restoran uchun — shu restorandagi buyurtmalar; RIZQIM uchun — butun platforma):**

- **Mijoz** — buyurtmasida `user_id` bor foydalanuvchi. Kassadagi mijozsiz sotuvlar "Anonim (kassa)" qatorida alohida,
  shunda jami tushum savdo hisobotidagi bilan mos keladi.
- **Birinchi buyurtma** — mijozning shu restorandagi (platformada) eng erta **yetkazilgan** buyurtmasi. Qaytarilgan
  (`refunded`) buyurtma hisoblanmaydi.
- **Yangi mijoz (davrda)** — birinchi buyurtmasi davr ichida. **Qaytgan mijoz** — davrda yetkazilgan buyurtmasi bor va
  birinchisi davrdan oldin. **Faol mijozlar** = yangi + qaytgan.
- **Qaytish ulushi** = qaytgan / faol. **30 kunda qaytdi** — oy M ning yangi mijozlaridan birinchi buyurtmadan keyin 30
  kun ichida yana buyurtma berganlar ulushi.
- **Chastota** = davrdagi yetkazilgan buyurtmalar / faol mijozlar. **Taqsimot**: 1, 2, 3–4, 5–9, 10+ buyurtma.
- **Kogorta** — birinchi buyurtma oyi bo'yicha guruh. N-oy ushlab qolish = shu guruhdan N-oyda kamida bitta yetkazilgan
  buyurtma bergan mijozlar / guruh hajmi (0-oy = 100%).
- **LTV (o'tgan, bashorat emas)** — guruhning 0..N oylardagi yetkazilgan buyurtmalari tushumi / guruh hajmi. RIZQIM uchun
  GMV bo'yicha va (keyinroq) ledgerdagi komissiya bo'yicha.
- Hisoblar mavjud tushum ta'rifida (yetkazilgan `total_price`). Qisman qaytarishlar alohida ko'rsatkich sifatida
  "Qisman qaytarilgan" (tasdiqlangan qaytarish so'rovlari summasi) — ayirib yashirilmaydi.

**Restoran nimani ko'radi** (hamkor paneli → Hisobotlar → "Mijozlar"; Mini App → Hisobotlar ekranida qisqa blok):
yuqoridagi raqamlar, oylik yangi/qaytgan ustunlari, chastota taqsimoti, 12 oylik kogorta jadvali va "Doimiy mijozlar"
— eng ko'p buyurtma bergan 20 kishi: **"Aziz R."** ko'rinishidagi qisqa ism, niqoblangan telefon (`+998 90 *** ** 67`,
`PiiMask`), buyurtmalar soni, tushum, oxirgi buyurtma sanasi. To'liq ism va telefon — faqat ega "ha" desa (7-bo'lim, 2-savol),
alohida `customers.view` ruxsati bilan va har ochilish jurnalga yoziladi. Restoran mijozning boshqa restorandagi
buyurtmalarini hech qachon ko'rmaydi (hatto "boshqa joyda ham buyurtma beradi" belgisini ham). Eksportga telefon
yozilmaydi.

**RIZQIM nimani ko'radi** (Ko'rsatkichlar → "Mijozlar"): platforma bo'yicha yangi/qaytgan, kogorta, LTV, nechta
restorandan buyurtma berishadi, birinchi buyurtma qaysi kanaldan; kampaniya bo'yicha kogorta. Shaxsiy ma'lumot yo'q
(faqat raqamlar). Mijoz kartasi (Mijozlar va yordam) avvalgidek, `AdminPii` qoidasi bilan.

**Orqaga to'ldiriladi**: hamma mavjud buyurtmalardan, birinchi kundan to'liq.

### 4.4. Soat, hafta kuni, solishtirish, filiallar (4-bo'shliq)

Hamkor paneli → Hisobotlar → "Savdo" (mavjud sahifa) ga qo'shiladi; Mini App → Tahlil ekraniga soat bo'yicha ustunlar va
solishtirish foizi:

- **Soat bo'yicha** (0–23) va **hafta kuni bo'yicha** (Du–Ya): buyurtmalar, yetkazilganlar, tushum — mavjud ta'rif,
  restoran mintaqasidagi soat. **Issiqlik xaritasi** 7 × 24: har katakda buyurtmalar soni.
- **Solishtirish**: "oldingi shu uzunlikdagi davr" yoki "o'tgan yilning shu kunlari". Har asosiy raqam yonida o'zgarish
  (%, mutlaq), oldingi davrda 0 bo'lsa "—". Hafta kunlari mos kelishi uchun 7 kunlik davr 7 kun oldinga suriladi.
- **Filiallar**: alohida jadval — buyurtmalar, yetkazilgan, tushum, o'rtacha chek, bekor qilish ulushi, o'rtacha
  tayyorlash va yetkazish vaqti (mavjud `times()` qoidasi), oldingi davrga nisbatan o'zgarish.

Orqaga to'ldirish shart emas: hammasi buyurtmalardan jonli hisoblanadi (mavjud indekslar yetadi).

### 4.5. Restoran uchun pul akti (5-bo'shliq)

Hamkor paneli → Moliya → "Davr akti" (faqat `finance.view`; standart — faqat ega, ega menejerga berishi mumkin;
kassadagi PIN sessiyadan ochilmaydi). Boshqaruv markazida restoran kartasidan ham xuddi shu akt (`admin.finance.view`).

**Davr** — restoran mintaqasidagi `dan`–`gacha` kunlar, ko'pi bilan 366 kun. Asos — **ledger yozuvi yaratilgan vaqt**
(pul qachon harakatlangan). Akt qatorlari:

| Qator | Manba (ledger) |
|---|---|
| Davr boshidagi hamyon va qarz | davr boshigacha oxirgi yozuvning `balance_after` (hamyon va qarz alohida) |
| RIZQIM orqali kelgan pul (Click, Payme, Paylov, keshbek) | hamyondagi `settlement` + qarzdagi buyurtmaga bog'langan `topup` (netting) |
| Komissiya | `commission` (hamyondan va qarzga), do'st bonusi yozuvlari bundan tashqari |
| Qaytarilgan komissiya | `refund` yozuvlari, komissiya tomoni (`CommissionStats` qoidasi) |
| Qaytarishda restorandan qaytarib olingan pul | `refund` yozuvlari, hisob-kitob tomoni |
| Do'st taklifi bonusi (restoran to'lagan) | `commission`, izohi `Referral cashback reward cost` |
| Obuna hisob-fakturalari va bekor qilinganlari | qarzdagi `subscription` |
| Restoran o'zi to'lagan (to'ldirish) | qarzdagi buyurtmasiz, payoutsiz `topup` |
| Payoutdan ushlangan qarz | qarzdagi `payout_request_id` li `topup` |
| Kartaga o'tkazilgan payout | hamyondagi `payout` |
| Qo'lda tuzatishlar | `adjustment` |
| Davr oxiridagi hamyon va qarz; sof holat | oxirgi yozuvning `balance_after`; sof = hamyon − qarz |

Har ikki hisob uchun tekshiruv qatori: **boshlang'ich + harakatlar = oxirgi** (mos kelmasa akt chiqmaydi va RIZQIM
jamoasiga ogohlantirish). Qo'shimcha, pul **emas**, ma'lumot uchun: davrda yetkazilgan buyurtmalar soni va savdosi,
shundan restoran o'zi olgan pul (naqd, o'z terminali) — buyurtmalardan, alohida "ma'lumot uchun" bloki bilan.

**Eksport**: CSV va Excel (`openspout`, mavjud), har qator va yozuvlar ro'yxati ikkinchi varaqda. **PDF**: chop etish
uchun moslangan sahifa (`/finance/statement/print`), brauzerning "PDF sifatida saqlash" bilan; serverda PDF yaratish
yangi kutubxona talab qiladi — qilinmaydi (5-bo'lim).

**Ikki mustaqil review** (pul).

### 4.6. Keshbek va promo (6-bo'shliq)

Boshqaruv markazi → Moliya → "Keshbek" (`admin.finance.view`), tanlangan davr:

- **Berildi** — do'st bonuslari: ledgerdagi bonus yozuvlari soni va summasi (= mijozlarga yozilgan ballar). Shu summani
  restoranlar to'lagan (restoran bo'yicha ro'yxat).
- **Ishlatildi** — `keshbek` usuli bilan to'langan buyurtmalar (`payments.provider = keshbek`, `paid`), davrda yaratilgan.
- **Balansga qaytdi** — bekor qilish va qaytarishda qaytgan ballar (`KeshbekHistory` o'qiydigan to'lov yozuvi maydonlari).
- **Restoranlarga to'langan** — keshbek buyurtmalari uchun ledgerdagi hisob-kitob yozuvlari (RIZQIM haqiqiy pul bilan
  to'lagan).
- **Majburiyat** — mijozlar qo'lidagi ballar jami (`SUM(users.balance)`) va uning kunlik grafigi (har kecha surat;
  o'tgan kunlar harakatlardan orqaga hisoblab to'ldiriladi), balansi bor mijozlar soni.
- Tekshiruv: oldingi majburiyat + berildi + qaytdi − ishlatildi = keyingi majburiyat (farq bo'lsa qizil).

**Promo-kodlar** (mavjud Marketing → Promo-kodlar): har kodga qo'shiladi — **yangi mijozlar** (kod bilan berilgan buyurtma
mijozning platformadagi birinchi yetkazilgan buyurtmasi bo'lganlar), **30 kunda qaytdi** (ulardan keyin kodsiz buyurtma
berganlar), "chegirmani kim ko'tardi" izohi (restoran). Mavjud sonlar o'zgarmaydi.

**Ikki mustaqil review** (pul).

### 4.7. Ilova xatolari (7-bo'shliq)

Sentry SDK'lar yangi dependency bo'lardi. Yangi kutubxonasiz yo'l: **o'z endpointimiz**.

- `POST /api/v1/client-errors` (hamkor paneli, boshqaruv markazi; kirmagan foydalanuvchi ham — kirish sahifasi xatolari
  uchun, qattiqroq limit bilan) va `POST /api/v1/pos/client-errors` (kassa ilovalari, qurilma tokeni bilan).
- Brauzerda: `window.onerror`, `unhandledrejection`, Next.js `error.tsx` va `global-error.tsx` chegaralari. Flutter:
  `FlutterError.onError`, `PlatformDispatcher.instance.onError`, `runZonedGuarded`; internet bo'lmasa xatolar
  qurilmadagi faylda (ko'pi bilan 50 ta) turadi va keyin yuboriladi — mavjud `path_provider` bilan.
- Server har xabarni `SentryScrubber` qoidalari bilan tozalaydi (telefon, token, karta, initData), so'rov parametrlari va
  yo'ldagi raqamlarni olib tashlaydi, bir xil xatolarni "barmoq izi" bo'yicha guruhlaydi.
- Monitoring → "Ilova xatolari": guruhlar (ilova, versiya, birinchi va oxirgi marta, soni, oxirgi 24 soat), bitta
  guruhning namunalari, "hal qilindi" / "jim" tugmalari (`admin.monitoring.manage`). Ogohlantirish: bir ilovada 15
  daqiqada N dan ortiq yangi xato (mavjud ogohlantirish tizimi, `EvaluateAlertsJob`).
- Saqlash: namunalar 2 oy (oylik bo'laklar, arxivsiz o'chiriladi), guruhlar — hal qilingandan 90 kun keyin o'chiriladi.
- Mini App'dagi ixtiyoriy Sentry hozircha qoladi (7-bo'lim, 4-savol).

### 4.8. Restoran va RIZQIM'ga yana nima kerak (8-bo'shliq)

Yandex Eats va Uzum Tezkor hamkor kabinetlari (ochiq ma'lumotlarga ko'ra) odatda ko'rsatadi: buyurtma va tushum, o'rtacha
chek, bekor qilishlar va sabablari, kechikishlar va tayyorlash vaqti, baho va sharhlar, restoran "o'chiq" turgan vaqt,
menyu konversiyasi (ko'rildi → olindi), aksiyalar natijasi, yangi/qaytgan mijozlar ulushi, payout va solishtirish akti.
Bizda yo'g'i va rejaga kirganlari:

1. **Bekor qilishlar** (19-qadam): sabab bo'yicha (`orders.cancel_reason`: mijoz so'rovi, kelmadi, tugagan, yopiq,
   kuryer yo'q, to'lov vaqti o'tdi, boshqa), kim bekor qildi (mijoz, restoran, RIZQIM), yo'qotilgan tushum (bekor
   qilinganlar summasi), to'lanmay bekor bo'lgan onlayn buyurtmalar alohida. Ma'lumot bor, orqaga to'ldiriladi.
2. **Xizmat sifati** (19-qadam): qabul qilish vaqti, tayyorlash vaqti, qabul qilish va bekor qilish ulushi, baho va
   sharhlar dinamikasi — boshqaruv markazidagi `StoreQualityService` qoidalari restoranga o'z raqamlari sifatida.
3. **Restoran yopiq vaqti** (5-qadam, erta yig'iladi): ish vaqtida restoran qo'lda yopilgan yoki to'xtatilgan daqiqalar.
   Hozir `stores.is_open` o'zgarishi tarixi saqlanmaydi, shuning uchun yozish ishga tushishdan oldin boshlanishi kerak.
4. **Menyu konversiyasi** (16-qadam): voronka hodisalaridan, 4.1.
5. **Ertalabki Telegram hisoboti** (20-qadam): har kuni 08:41 da (Toshkent) egaga kecha: buyurtmalar, tushum, o'rtacha
   chek, bekor qilishlar, baho, o'tgan haftaning shu kuniga nisbatan. Xabar sozlamalaridan o'chiriladi (7-bo'lim, 8-savol).
6. **Yetkazish geografiyasi** — tuman va zona bo'yicha buyurtmalar soni: ataylab keyinga (8-bo'lim).

---

## 5. Cheklovlar

- **Yangi dependency yo'q.** Bu nimani cheklaydi:
  - *Grafiklar*: kutubxonasiz, mavjud `RevenueChart` / `BarChart` uslubida HTML/CSS (ustun, issiqlik xaritasi, kogorta
    jadvali rangli kataklar bilan). Chiziqli grafik kerak bo'lsa — inline SVG `polyline`, kutubxonasiz. Murakkab
    interaktiv grafiklar (zoom, ko'p o'q) yo'q.
  - *PDF*: serverda yo'q (dompdf, wkhtmltopdf, Chromium — hammasi yangi dependency). O'rniga chop etish sahifasi →
    brauzer PDF'i. Telegram'ga PDF hujjat yuborib bo'lmaydi (CSV/XLSX yuboriladi).
  - *Xato monitoringi*: Sentry'ning tayyor ekranlari, release/sourcemap bog'lash yo'q; stack trace kichraytirilgan
    (minified) bo'lib keladi. Kompensatsiya: xatoga ilova versiyasi va sahifa yo'li yoziladi; sourcemap'ni keyinroq
    serverda ochish mumkin (8-bo'lim).
  - *Excel*: `openspout` bor — cheklov yo'q.
  - *Bot himoyasi*: CAPTCHA yo'q; himoya — imzolangan Telegram kirishi, limitlar, qat'iy sxema (A.4).
- **Qat'iy tenancy.** Restoran o'qiydigan har yangi jadvalda `store_id NOT NULL` va u RLS ostida (`TenantRls::TABLES`);
  boshqalari `TenantRlsInventoryTest::NOT_UNDER_RLS` ga sababi bilan yoziladi. Har yangi restoran endpointi
  `tests/Feature/Tenancy/routes.php` va `CrossTenantRouteTest` ga qo'shiladi.
- **Restoranga PII sizmaydi.** Restoran javoblarida telefon raqami (niqobsiz), manzil, Telegram ID, boshqa restoran
  ma'lumoti bo'lmaydi; buni test barcha yangi restoran javoblarida raqam naqshi bo'yicha tekshiradi.
- **Tezlik chegaralari.** Hodisa qabul qilish p95 ≤ 25 ms (bitta INSERT); restoran hisobotlari 366 kunlik davrda p95 ≤
  400 ms (kuniga 150 buyurtmali restoran); kunlik yig'ish 1 000 restoranda ≤ 60 s; so'raladigan endpointlar
  (`/owner/analytics`, `/admin/today`, xodim buyurtma ro'yxatlari) **o'zgarmaydi**; hodisalar `ChangeRevision` ni
  qo'zg'atmaydi (hech bir ETag yangilanmaydi).
- **Katta jadvallarga xavfsiz migratsiya.** `orders`, `order_items`, `users`, `payments`, ledger jadvallariga **ALTER yo'q**
  (bog'lovchi ma'lumot alohida jadvallarda). Yangi bo'lingan jadvallar boshidanoq `PARTITION BY RANGE` bilan yaratiladi
  (konvertatsiya yo'q); mavjud katta jadvalga indeks kerak bo'lsa — faqat `ConcurrentIndex`. Migratsiyalar kesh/Redis'ga
  tegmaydi (`Cache::`, `Redis::` yo'q).
- **CI testlarni faqat DML huquqli rol bilan ishlatadi** (`rizqim_app`): ish vaqtida hech qanday DDL yo'q (bo'laklarni
  faqat migratsiya va `db:partitions:ensure` yaratadi, DEFAULT bo'lak bor, shuning uchun INSERT hech qachon yiqilmaydi);
  DDL qiladigan test `#[AsSchemaOwner]` bilan.

---

## 6. Qurish tartibi

Har qadam — bitta PR, bitta agent, taxminan 1 kun, o'zi alohida ishga tushadi va testlanadi. "2 review" — pul yoki
shaxsiy ma'lumotga tegadi, ikki mustaqil review kerak. "Qachon": **IT** — ishga tushishdan (pilotdan) oldin; **BP** —
birinchi payoutdan oldin; **HB** — birinchi haqiqiy buyurtmalardan keyin (oldin qurish mumkin, lekin haqiqiy ma'lumot
bilan tekshiriladi).

| # | Qadam | Bog'liq | Xavf | Review | Qachon |
|---|---|---|---|---|---|
| 1 | Maxfiylik siyosati (uz, ru) va `docs/legal/README.md` matni; hodisalar ro'yxati hujjati (`docs/API.md` bo'limi) | — | past | 1 + ega/yurist | IT |
| 2 | Backend: `analytics_events` (oylik bo'laklar), `POST /api/v1/analytics/events`, limitlar, RevisionMap, RLS inventari | 1 | o'rta | **2** (PII) | IT |
| 3 | Mini App: `lib/analytics.ts`, sessiya, hodisalar (ochildi, restoran, taom, savat, buyurtmaga o'tish, xato, buyurtma) | 2 | past | 1 | IT |
| 4 | Kampaniyalar: `campaigns`, `user_attributions`, `pending_attributions`, `StartParam` tahlilchisi, kirish va bot `/start` ga ulash, admin CRUD va havolalar | 2 | o'rta | **2** (kirish yo'li) | IT |
| 5 | Sayt: `POST /api/v1/landing/visits`, `landing_daily_visits`, nginx joyi, `utm` → `start=c_`, ariza `utm` ustunlari; restoran yopiq vaqti jurnali `store_availability_events` | 4 | past | 1 | IT |
| 6 | Xatolar backend: `client_error_reports` (bo'laklar), `client_error_groups`, ikki endpoint, tozalash, Monitoring API va ogohlantirish | — | o'rta | **2** (PII) | IT |
| 7 | Xatolar: hamkor paneli va boshqaruv markazi (`error.tsx`, `global-error.tsx`, oyna ushlagichlari) + Monitoring ekrani | 6 | past | 1 | IT |
| 8 | Xatolar: Flutter POS ilovalari (umumiy ushlagich, fayldagi navbat) | 6 | past | 1 | IT |
| 9 | Restoran savdosi backend: `by_hour`, `by_weekday`, `heatmap`, `compare`, `GET /owner/reports/branches`, CSV/XLSX | — | past | 1 | IT |
| 10 | Restoran savdosi ekranlari: hamkor paneli (issiqlik xaritasi, solishtirish, Filiallar) va Mini App Tahlil | 9 | past | 1 | IT |
| 11 | Pul akti backend: `StoreStatementService`, `GET /owner/finance/statement` (+ eksport), admin nusxasi, muvofiqlik testlari | — | **yuqori** | **2** (pul) | BP |
| 12 | Pul akti ekranlari: hamkor paneli "Davr akti", chop etish sahifasi, admin restoran kartasi | 11 | o'rta | **2** (pul ko'rinishi) | BP |
| 13 | Mijoz yig'indilari: `store_customer_stats`, `store_cohort_stats`, `platform_customer_stats`, `platform_cohort_stats`, ishlar va orqaga to'ldirish | — | o'rta | **2** (PII, RLS) | HB |
| 14 | Restoran mijoz hisobotlari: `GET /owner/reports/customers`, `/cohorts` + hamkor paneli "Mijozlar" + Mini App bloki | 13 | o'rta | **2** (PII) | HB |
| 15 | RIZQIM mijoz hisobotlari: `GET /admin/metrics/customers`, `/cohorts` + Ko'rsatkichlar → "Mijozlar" | 13 | past | 1 | HB |
| 16 | Voronka: yig'ish ishi, `analytics_funnel_daily`, `analytics_product_daily`, admin "Voronka", restoran "Voronka" va "Menyu konversiyasi" | 3, 13 | o'rta | 1 | HB |
| 17 | Kampaniya va do'st taklifi hisobotlari: `campaign_daily_stats`, Marketing ekranlari, eksport | 4, 5, 13 | o'rta | **2** (ledger pul) | HB |
| 18 | Keshbek hisoboti (`keshbek_daily_snapshots`) va promo samaradorligi (yangi mijozlar) | 13 | o'rta | **2** (pul) | HB |
| 19 | Bekor qilishlar va xizmat sifati: `GET /owner/reports/cancellations`, `/quality` + ekranlar | 9 | past | 1 | HB |
| 20 | Ertalabki Telegram hisoboti (xabar sozlamasi bilan) | 9 | past | 1 | HB |
| 21 | Restoranning o'z kampaniya havolasi va QR (`s_<restoran>` deep link, restoran kampaniyalari) — 6-savolga bog'liq | 4, 17 | o'rta | 1 | HB |

Bog'liqlik qoidasi: bir vaqtda bitta agent. 1→2→3→4→5 ketma-ket (ma'lumot yig'ish); 6–8, 9–10, 11–12 mustaqil, ular
oralig'ida istalgan tartibda; 13 dan keyin 14–18; 19–21 oxirida. Har qadam o'z hujjatini (`docs/API.md`, kerak bo'lsa
`docs/guides/hamkor-panel.md`, `docs/guides/boshqaruv-markazi.md`, `docs/OPERATIONS.md` saqlash muddatlari) o'zi
yangilaydi; alohida "hujjat" qadami yo'q. Oxirida `docs/ROADMAP.md` va `docs/LAUNCH_PLAN.md` holat qatorlari.

Nima uchun bu tartib: hodisalar va kampaniya havolalari (2–5) o'tmishdan tiklanmaydi — har kuni kechikish ma'lumot
yo'qotishi. Xatolar (6–8) pilotdagi muammolarni ko'rish uchun kerak. Savdo va pul akti (9–12) mavjud ma'lumotdan ishlaydi
va restoran birinchi oyda so'raydi. Mijoz, kogorta, voronka va kampaniya hisobotlari (13–18) haqiqiy buyurtmasiz
ma'nosiz, ularni orqaga to'ldirish istalgan payt ishlaydi.

---

## 7. Ega hal qiladigan savollar

| # | Savol | Variantlar | Tavsiya |
|---|---|---|---|
| 1 | Mini App harakatlarining batafsil yozuvlari qancha saqlansin? | A) 3 oy / B) 12 oy | **A.** Voronka va konversiya uchun yetadi, kunlik yig'indilar muddatsiz qoladi. 1 000 restoranda taxminan oyiga 6 GB; 12 oy ~70 GB — hozirgi 100 GB diskka sig'maydi. Muddat sozlamada (`ANALYTICS_EVENTS_MONTHS`) |
| 2 | "Doimiy mijozlar" ro'yxatida restoran ism va telefonni ko'rsinmi? | A) yo'q: "Aziz R." va niqoblangan raqam / B) ha: to'liq, faqat ega, har ochilish jurnalda | **A.** Hozir restoran ismi va telefonni faqat buyurtmani bajarish uchun oladi (siyosat, 4-bo'lim); ro'yxat — yangi maqsad (marketing), siyosatni o'zgartirish va yurist kerak. Keyin B ga o'tish oson |
| 3 | Saytga (faqat `rizqim.uz`) Yandex Metrika qo'shilsinmi? | ha / yo'q | **Yo'q.** Siyosat "uchinchi tomon analitika yo'q" deydi; Metrika ma'lumoti chet elga ketadi (transchegaraviy uzatish), CSP o'zgaradi. O'z hisoblagichimiz `utm` va tashriflarni beradi |
| 4 | Mini App xatolari ham faqat o'z endpointimizga o'tsinmi? | A) Sentry ixtiyoriy qolsin / B) Sentry o'chiriladi, hammasi o'zimizda | **A** hozircha; 7-qadamdan bir oy keyin o'z tizimimiz yetarli bo'lsa B |
| 5 | Kampaniya tushumi qaysi oynada hisoblansin? | A) 30 kun / B) 90 kun | **A** (ovqat — tez qaror), "hammasi" ustuni har doim yonida |
| 6 | Restoran o'z reklama havolasi va QR'ini olsinmi (Mini App to'g'ridan-to'g'ri restoran sahifasini ochadi)? | ha / yo'q | **Ha**, 21-qadamda (restoranlarni o'z mijozlarini olib kelishga undaydi) |
| 7 | Restoran akti qaysi sana bo'yicha? | A) pul harakatlangan sana (ledger) / B) buyurtma sanasi | **A** — balanslar bilan tiyinigacha mos keladigan yagona yo'l; buyurtma bo'yicha savdo alohida "ma'lumot uchun" blokida |
| 8 | Ertalabki Telegram hisoboti standart yoqilgan bo'lsinmi? | A) egaga yoqilgan, menejerga o'chiq / B) hammaga o'chiq | **A**, bir bosishda o'chiriladi |
| 9 | Restoran, xodim va RIZQIM jamoasi hisoblari voronkadan chiqarilsinmi? | ha / yo'q | **Ha** |
| 10 | Kampaniyaga xarajat kiritib "bir mijoz narxi" hisoblansinmi? | ha / yo'q | **Ha** (ixtiyoriy maydon; bu ledger puli emas, faqat ma'lumot) |
| 11 | Do'st taklifi bonusini (5 000) hozirgidek restoran to'laydimi? | A) aktda alohida qator qilib ko'rsatamiz, qoida o'zgarmaydi / B) RIZQIM o'z hisobidan to'laydigan qilib o'zgartirish (alohida pul ishi) | **A** hozir; B — analitikadan tashqari, alohida qaror va 2 review'li pul o'zgarishi |
| 12 | Qidiruv matni saqlansinmi ("nima qidirishdi, topilmadi")? | ha / yo'q | **Yo'q** — faqat "natija bor/yo'q" va so'z uzunligi; matnda shaxsiy ma'lumot bo'lishi mumkin |
| 13 | Yangi platforma ekranlarini (Kampaniyalar) Sotuv roli ham ko'rsinmi? | ha / yo'q | **Ha**: superadmin "Rollar" matritsasida Sotuv'ga `marketing.view` beradi (kod o'zgarmaydi) |

---

## 8. Ataylab qilinmaydiganlar

- **Python analitika ombori, ClickHouse, alohida servis** — ROADMAP 10-bosqich. 1 000 restorangacha Postgres yig'indi
  jadvallari yetadi; avval ma'lumot va savollar to'plansin.
- **Talab prognozi, ML, bashoratli LTV, tavsiyalar** — kamida 6–12 oylik toza ma'lumot kerak; hozir faqat o'tgan LTV.
- **Uchinchi tomon analitika va SDK** (GA, Yandex Metrika, PostHog, Amplitude, Sentry web/Flutter SDK) — siyosat,
  qonun va "yangi dependency yo'q" qoidasi.
- **Serverda PDF** — yangi kutubxona; brauzer orqali chop etish yetadi.
- **Qurilma barmoq izi, sessiyalarni qurilmalar orasida bog'lash, IP saqlash** — keraksiz shaxsiy ma'lumot.
- **Real vaqtda (soniyalik) analitika ekranlari** — Bugun sahifasi bor; yangi hisobotlar 15 daqiqalik yangilanish bilan.
- **A/B test tizimi, push kampaniyalar** — reklama xabarlari uchun alohida rozilik kerak (siyosat 3-bo'lim).
- **Yetkazish geografiyasi xaritasi** (manzil nuqtalari) — PII xavfi; tuman bo'yicha sonlar keyingi to'lqinda.
- **Kuryer analitikasi** — 7-bosqich (kuryer ilovasi) bilan.
- **Sourcemap orqali stack trace'ni ochish** — keyinroq, deploy jarayoniga sourcemap saqlash qo'shilganda.
- **Mavjud ta'riflarni o'zgartirish** (masalan, tushumdan qisman qaytarishlarni ayirish) — eski ekranlar bilan
  raqamlar ajralib ketadi; yangi ko'rsatkich alohida beriladi.

---

## 9. Maxfiylik siyosatidagi o'zgarishlar

1-qadamda `infra/landing/site/i18n/uz.js` (va xuddi shu ma'noda `ru.js`) ga; `docs/legal/README.md` ning "Sahifalar
mahsulotning haqiqiy ishiga tayanadi" bandiga shu faktlar va muddatlar qo'shiladi. Yurist ko'rigi tavsiya etiladi.

- **`privacy.s2.b`** (yig'iladigan ma'lumotlar) — ikki band qo'shiladi:
  - "**Ilovadan foydalanish statistikasi:** Mini App'da qaysi restoran, bo'lim va taom ochilgani, savatga nima
    qo'shilgani, buyurtma berishga o'tilgani va qaysi havola (reklama yoki do'st taklifi) orqali kelganingiz — ilova
    har ochilganda yaratiladigan tasodifiy sessiya raqami va hisobingiz bilan. Qidiruv matni, joylashuv, IP manzil va
    qurilma identifikatori bu yozuvlarga kirmaydi."
  - "**Sayt statistikasi:** rizqim.uz sahifasi ochilganda qaysi sahifa, qaysi reklama havolasi (utm belgilari) va
    qaysi saytdan kelingani faqat kunlik son sifatida sanaladi: cookie, identifikator va IP manzil saqlanmaydi."
- **`privacy.s4.b`** (kimga beriladi) — band: "**Restoranlar** o'z savdosi bo'yicha umumlashtirilgan statistikani ko'radi
  (masalan, yangi va qaytgan mijozlar soni); sizning boshqa restoranlardagi buyurtmalaringiz ularga ko'rsatilmaydi."
  (7-bo'lim 2-savolga B javob berilsa: "...doimiy mijozlar ro'yxatida ismingiz va telefon raqamingiz".)
- **`privacy.s5.b`** (xizmatlar) — Sentry jumlasidan keyin: "Hamkor paneli, boshqaruv markazi va kassa ilovalaridagi
  xatolar haqidagi texnik yozuvlar faqat O'zbekistondagi o'z serverlarimizga, shaxsiy ma'lumotlardan tozalangan holda
  yuboriladi."
- **`privacy.s6.b`** (muddatlar) — ikki band: "**Ilovadan foydalanish statistikasi:** batafsil yozuvlar 3 oygacha, keyin
  o'chiriladi; faqat shaxssiz umumlashtirilgan raqamlar qoladi." va "**Ilova xatolari haqidagi texnik yozuvlar:** 2
  oygacha." (Muddat 1-savol javobiga mos.)
- **`privacy.s7.b`** (cookie va brauzer xotirasi) — Mini App jumlasi: "Mini App qurilmangizda kirish holati, til, savat,
  tanlangan manzil va joriy ochilishning tasodifiy sessiya raqamini saqlaydi." Oxirgi jumla: "Reklama va kuzatuv cookie
  fayllari hamda uchinchi tomon analitika tizimlari ishlatilmaydi: statistika faqat O'zbekistondagi o'z serverlarimizda
  yig'iladi."

`privacy.s3.b` dagi "xizmatni umumlashtirilgan statistika asosida yaxshilash" maqsadi allaqachon bor — o'zgarmaydi.
Kampaniya va voronka 1-qadam matni e'lon qilinmaguncha yoqilmaydi (`config('analytics.enabled')` = false standart).

---

## A. Technical appendix

English, for the builders. Everything here is a contract for the steps of section 6; a step may refine details but not
the rules of A.1 (definitions), A.2 (tenancy/PII) and A.7 (constraints).

### A.1. Canonical definitions

Time:

- Store-facing: local days of `App\Support\StoreTimezone::of($store)`; ranges `[from 00:00, to+1 00:00)` converted to UTC
  exactly as `OwnerAnalyticsService::ordersBetween()`. Hour/weekday: `EXTRACT(HOUR|ISODOW FROM (orders.created_at AT TIME
  ZONE 'UTC') AT TIME ZONE :tz)`.
- Platform-facing: `PlatformStats::TIMEZONE` (Asia/Tashkent); months by `config('billing.timezone')`.

Orders and sales (unchanged, reused):

- `orders_count`, `delivered_count`, `canceled_count` (`canceled`+`rejected`), `refunded_count`, `revenue` = SUM(total_price)
  of `delivered`, `average_check` = revenue / delivered — by `orders.created_at`, `deleted_at IS NULL`
  (`OwnerAnalyticsService::groupTotals`). New per-hour/weekday/branch/customer figures call the same builder.
- `partially_refunded` (new, informational): SUM(`order_refund_requests.amount`) WHERE status = `approved` AND the order
  is in scope, by `decided_at` in range. Never subtracted from `revenue`.
- Platform daily GMV stays `PlatformStats` (counted orders by created_at). Customer/cohort/LTV/campaign GMV = "delivered
  GMV": SUM(total_price) of orders currently `delivered`, by `created_at` (store or platform tz).

Customers:

- Customer key: `orders.user_id` (NOT NULL). Orders with NULL user_id → bucket `anonymous` (so sums reconcile with sales).
- First order at store S: MIN(created_at) over S's orders with status `delivered` for that user. Platform first order:
  over all stores (computed as login role, never inside a tenant transaction — cf. `LedgerService::isFirstDelivery`
  using `TenantRls::asApp`; the aggregation job runs in the queue, unbound, so it needs no `asApp`).
- `new(P)`: first order ∈ P. `returning(P)`: ≥1 delivered order ∈ P and first order < start(P). `active` = new + returning.
- `repeat_rate(P)` = returning / active. `returned_30d(M)` = share of new(M) with another delivered order within
  `first_order_at + 30 days`.
- `frequency(P)` = delivered orders ∈ P (with user_id) / active. Histogram buckets `1`, `2`, `3-4`, `5-9`, `10+`.
- Cohort c = month of first order (local tz). `retention[c][k]` = |{u ∈ c : delivered order in month c+k}| / |c|.
  `ltv[c][k]` = Σ revenue of c's delivered orders in months c..c+k / |c|.
- Merged accounts (`AccountLinkingService`): orders already move to the kept user id; recompute picks it up nightly.

Funnel (session-based):

- `session_id`: UUID v4 created by the Mini App at launch, rotated after 30 min without events; kept in
  `sessionStorage` (per Telegram WebView launch).
- Steps (bool per session): `app_open`, `store_view` (per store), `add_to_cart`, `checkout_start`, `order_placed` (event
  `order_placed` whose `order_id` the server verified: order exists, `orders.user_id` = token user), `confirmed` (that
  order has `payment_status = paid`, or a non-online method and status beyond `new`), `delivered` (status `delivered`).
- Per-store funnel: sessions with `store_view` of S; later steps count only events/orders of S.
- Users with role ≠ `customer` excluded at ingestion (dropped, counted in a metric `analytics.dropped_staff`).

Campaigns and referrals:

- First touch: `user_attributions` row written once when the user row is created (`AuthenticateTelegramUserAction`),
  from `start_param` (`c_<code>`) or a parked `pending_attributions` row (bot `/start c_<code>`). Never overwritten.
- Last touch (sessions): campaign of the session's `app_open` (`start_param` kind `campaign`).
- `visits(c,P)` = sessions with campaign c whose `app_open` ∈ P; `landing_visits(c,P)` = SUM(landing_daily_visits.visits)
  WHERE utm_campaign = c.code.
- `signups(c,P)` = users with first-touch c created ∈ P; `phone_verified` = of them with `phone_verified_at`.
- `first_orders(c,P)` = users with first-touch c whose platform first order ∈ P.
- `gmv_30d(c,P)` = delivered GMV of first-touch-c users, orders created within `[user.created_at, +30 days)`, for users
  signed up ∈ P. `gmv_all` without the window.
- `commission_30d(c,P)` = for the same orders: Σ ledger COMMISSION rows (wallet: −amount; debt: amount; referral-note
  rows excluded) − REFUND reversals of those orders (exactly `CommissionStats` rules, keyed by order_id instead of month).
- `cost_per_first_order` = campaigns.cost / first_orders (null when cost null or 0 orders).
- Referrals: `referred_signups(P)` = users with `referred_by` NOT NULL created ∈ P; `rewards(P)` = count and SUM(amount) of
  `store_transactions` type `commission`, notes LIKE `'Referral cashback reward cost%'`, created ∈ P.

Keshbek:

- `issued(P)` = rewards(P) amount. `redeemed(P)` = SUM(payments.amount) WHERE provider = 'keshbek' AND status = paid
  AND payment created ∈ P. `returned(P)` = Σ of payload `refunded_to_balance_parts[].amount` (by `refunded_at`) and
  `refunded_to_balance` (cancellations; the `KeshbekHistory` rules for not double counting) ∈ P.
- `liability(d)` = SUM(users.balance) at end of day d (snapshot); backfill: liability(today) − Σ movements after d.
- Invariant (test and on-screen check): liability(start) + issued + returned − redeemed = liability(end).
- `paid_to_stores(P)` = SETTLEMENT (wallet) + order-linked TOPUP (debt) rows of keshbek-paid orders, created ∈ P.

Store statement (money; ledger only):

- Scope: one store (ledger is store-level; no branch filter). Range by ledger row `created_at`, store tz, ≤ 366 days.
- `opening_wallet` / `opening_debt` = `balance_after` of the last row (by id) before start in
  `store_wallet_transactions` / `store_transactions`; 0 if none. Closing likewise at end.
- Lines: `collected_by_platform` = wallet SETTLEMENT + debt TOPUP WHERE order_id NOT NULL; `commission` = wallet
  COMMISSION (abs) + debt COMMISSION non-referral; `commission_returned` = debt REFUND amount<0 + wallet REFUND amount>0
  of orders that had commission (CommissionStats rule, but over the range); `refund_clawback` = the other REFUND
  rows (settlement side); `referral_rewards` = debt COMMISSION referral-note; `subscriptions` = debt SUBSCRIPTION
  (positive and void negatives separately); `store_topups` = debt TOPUP WHERE order_id NULL AND payout_request_id NULL;
  `payout_netting` = debt TOPUP WHERE payout_request_id NOT NULL; `payouts_to_card` = wallet PAYOUT rows not netting;
  `adjustments` = ADJUSTMENT rows (both).
- Invariants: opening + Σ(range rows' amount) = closing, per table. Σ lines = Σ rows (no row in two lines, no row
  unclassified — an unknown type/combination fails the build in tests and shows "Boshqa" with an alert in prod).
- Cross-checks (tests): for a calendar month, `commission − commission_returned` (over orders booked in that month) ==
  `CommissionStats::inMonth`; with no settlement retries, monthly `commission` == `MonthlyRevenueReport` store commission
  + reversed; `collected_by_platform` == Σ `LedgerService::hasSettlement` amounts of the period's orders.
- Info block (not money): delivered orders and revenue by order date (existing definition), split `payment_owner`
  PLATFORM vs STORE.

### A.2. Tenancy, PII and RLS decisions per table

| Table | store_id | RLS | RevisionMap | Read by |
|---|---|---|---|---|
| `analytics_events` (partitioned) | nullable | NOT_UNDER_RLS: `MIXED` | NONE: "raw analytics events; no screen polls them" | jobs only |
| `analytics_funnel_daily` | NOT NULL (0 = platform row) | **TenantRls::TABLES** | NONE | owner (own rows), admin |
| `analytics_product_daily` | NOT NULL | **TenantRls::TABLES** | NONE | owner, admin |
| `campaigns` | nullable (store campaigns, step 21) | NOT_UNDER_RLS: `MIXED` | NONE | admin; owner via explicit `where store_id` (step 21) |
| `campaign_daily_stats` | none | — | NONE | admin |
| `user_attributions` | none | — | NONE | jobs, admin aggregates |
| `pending_attributions` | none | — | NONE | auth path |
| `landing_daily_visits` | none | — | NONE | admin |
| `store_customer_stats` | NOT NULL | **TenantRls::TABLES** | NONE | owner, jobs |
| `store_cohort_stats` | NOT NULL | **TenantRls::TABLES** | NONE | owner |
| `platform_customer_stats` | none (`first_store_id` only) | — | NONE | admin aggregates |
| `platform_cohort_stats` | none | — | NONE | admin |
| `keshbek_daily_snapshots` | none | — | NONE | admin |
| `client_error_groups` | none | — | NONE | admin |
| `client_error_reports` (partitioned) | nullable | NOT_UNDER_RLS: `MIXED` | NONE | admin |
| `store_availability_events` | NOT NULL | NOT_UNDER_RLS: `WORK` (no money) — or under RLS if step 19 reads it in tenant transactions (preferred: under) | NONE | owner (step 19) |
| `store_applications` (+utm columns) | existing | unchanged | unchanged | admin |

Rules: RLS tables are written only by queue/console (login role, `BYPASSRLS`) and read by owner endpoints with an
explicit `where store_id = tenant` as well (StoreScope on their models, `BelongsToStore`). Partitioned RLS is already
proven on `audit_logs` (policy on the parent). Owner responses: no `phone` unmasked, no `telegram_id`, no address, no
`user_id` (use an opaque per-store key `hash_hmac('sha256', store_id.':'.user_id, app.key)`, first 12 hex). Admin
responses: phones only through `AdminPii`.

### A.3. Data model

All money `decimal(16,2)`; timestamps `timestamp(0)` UTC (as the rest of the schema); `date` columns are local dates.

**`analytics_events`** — `PARTITION BY RANGE (received_at)`, monthly, DEFAULT partition; config
`partitions.tables.analytics_events = ['column' => 'received_at', 'online_months' => env('ANALYTICS_EVENTS_MONTHS', 3), 'archive' => false]`.

```
id bigint (sequence) NOT NULL, received_at timestamp NOT NULL default now(),
occurred_at timestamp NOT NULL           -- client time clamped to [received_at - 24h, received_at + 5m]
session_id uuid NOT NULL, event_uid uuid NOT NULL,   -- client ids; duplicates harmless (session-based metrics, COUNT DISTINCT event_uid)
user_id bigint NULL,                     -- from the token only, never from the body; no FK (partitioned, cf. M3 rule)
event smallint NOT NULL,                 -- App\Domain\Analytics\AnalyticsEvent (int-backed enum)
source smallint NOT NULL,                -- miniapp=1 (landing does not write here)
platform smallint NOT NULL,              -- Telegram.WebApp.platform: ios, android, tdesktop, macos, web, weba, unknown
app_version varchar(16) NULL,
store_id bigint NULL, product_id bigint NULL, order_id bigint NULL, campaign_id integer NULL,
props jsonb NULL                         -- allowlist per event, ≤ 512 bytes
PRIMARY KEY (id, received_at)
INDEX brin (received_at); INDEX (session_id)  -- per partition via ConcurrentIndex::createPartitioned
```

Events (`AnalyticsEvent`): `app_open {start_kind: campaign|referral|store|invite|none, campaign_code?}`, `store_view
{store_id}`, `category_view {store_id, category_id}`, `product_view {store_id, product_id}`, `add_to_cart {store_id,
product_id, qty}`, `cart_view {store_id, items, value}`, `checkout_start {store_id, items, value, payment_method,
order_type}`, `checkout_error {store_id, code: out_of_zone|min_order|store_closed|item_unavailable|validation|payment|network}`,
`order_placed {order_uuid → order_id}`, `payment_redirect {store_id}`, `search {results: int, length: int}`,
`promo_banner_click {promo_id}`. Size estimate: ~180 B/row; at 1 000 stores ~1 M events/day ≈ 6 GB/month.

**`campaigns`**: `id`, `uuid`, `code varchar(32) unique` (`^[a-z0-9_]{3,32}$`, lower), `name`, `channel varchar(24)`
(telegram_ads, instagram, flyer, influencer, store_qr, landing, other), `store_id bigint null` (FK stores), `starts_at`,
`ends_at` null, `is_active bool`, `cost decimal(16,2) null`, `notes text null`, `created_by` FK users, timestamps. Audit
via the admin audit log (`admin.marketing.campaign_*`).

**`user_attributions`**: `user_id bigint PK` (FK users `restrictOnDelete` — users are never hard-deleted, and the M4
lesson is to avoid cascades), `campaign_id int null` FK campaigns (`restrictOnDelete`; campaigns are deactivated, not deleted),
`referrer_user_id bigint null`, `start_kind smallint`, `source smallint` (miniapp, bot, landing_bot), `first_seen_at`,
`created_at`. Index `(campaign_id, created_at)`.

**`pending_attributions`**: `telegram_id varchar(32) PK`, `campaign_id int`, `created_at`, `expires_at` (30 days), prunable
(`MassPrunable`, picked up by the daily `model:prune`). DB instead of Redis so a Redis flush does not lose attribution;
the existing referral parking (`ReferralService`, Cache) is left as is.

**`landing_daily_visits`**: `date`, `path varchar(32)` (allowlist: `/`, `/hamkor`, `/maxfiylik`, `/shartlar`),
`utm_source varchar(32)`, `utm_medium varchar(32)`, `utm_campaign varchar(32)`, `referrer_host varchar(64)`, `lang
varchar(2)`, `visits int`; UNIQUE over all dimension columns (empty string, not NULL, for absent values); upsert
`visits = visits + 1`.

**`analytics_funnel_daily`**: `date`, `store_id bigint` (0 = platform-wide row), `platform smallint`, `campaign_id int`
(0 = none), `sessions`, `store_view_sessions`, `add_to_cart_sessions`, `checkout_sessions`, `order_sessions`,
`confirmed_sessions`, `delivered_sessions`, `orders_without_session` (store rows: orders of the day not linked to a
session), `computed_at`; UNIQUE (date, store_id, platform, campaign_id).

**`analytics_product_daily`**: `date`, `store_id`, `product_id`, `view_sessions`, `add_to_cart_sessions`,
`ordered_quantity decimal(12,3)` (delivered order_items, combo parts excluded as in `topProductRows`), `computed_at`;
UNIQUE (date, store_id, product_id).

**`campaign_daily_stats`**: `date`, `campaign_id`, `visits`, `signups`, `phone_verified`, `first_orders`, `orders`,
`gmv decimal`, `commission decimal`, `computed_at`; UNIQUE (date, campaign_id). (`gmv`/`commission` are recomputed
from orders/ledger nightly; they are a cache of A.1 formulas, never an input to money.)

**`store_customer_stats`**: PK (`store_id`, `user_id`); `first_order_at`, `last_order_at`, `delivered_orders int`,
`revenue decimal`, `canceled_orders int`, `first_channel varchar(16)`, `first_branch_id bigint null`, `computed_at`.
Index (`store_id`, `first_order_at`), (`store_id`, `revenue` DESC) for top customers.

**`store_cohort_stats`**: PK (`store_id`, `cohort_month date`, `month_offset smallint`); `customers`, `orders`,
`revenue`, `computed_at`.

**`platform_customer_stats`**: PK `user_id`; `first_order_at`, `first_store_id`, `first_channel`, `last_order_at`,
`delivered_orders`, `gmv`, `stores_count`, `campaign_id int null`, `referred bool`, `computed_at`. Index (`first_order_at`).

**`platform_cohort_stats`**: PK (`cohort_month`, `month_offset`, `segment varchar(24)`) — segment `all`, `campaign:<id>`,
`referral`, `organic`; `customers`, `orders`, `gmv`.

**`keshbek_daily_snapshots`**: PK `date`; `liability decimal`, `holders int`, `issued`, `redeemed`, `returned`,
`computed_at`.

**`client_error_groups`**: `id`, `fingerprint char(40)`, `app varchar(24)` (partner_web, platform_admin, miniapp,
pos_cashier, pos_waiter, pos_kds, pos_display), UNIQUE (app, fingerprint); `type varchar(120)`, `message varchar(500)`,
`first_seen_at`, `last_seen_at`, `last_release varchar(32)`, `total int`, `status varchar(12)` (open, resolved, muted),
`resolved_by`, `resolved_at`. Pruned 90 days after resolve.

**`client_error_reports`** — partitioned by `received_at`, `online_months` 2, archive false: `id`, `received_at`,
`group_id`, `release`, `platform`, `path varchar(200)` (no query string, digits → `:id`), `stack text` (≤ 8 KB, scrubbed),
`user_id null`, `store_id null`, `device_id null`, `context jsonb` (≤ 1 KB, scrubbed). PK (id, received_at). At most 20
samples per group per hour stored (the rest only bump `total`).

**`store_availability_events`**: `id`, `store_id`, `branch_id null`, `kind varchar(16)` (closed, opened, paused,
resumed), `source varchar(16)` (owner, admin, schedule, credit_lock), `at timestamp`, `actor_id null`. Written where
`stores.is_open` / the pause flag / `is_order_locked` change (owner settings, admin, `LedgerService` lock). Index
(`store_id`, `at`). Retention 13 months (plain table, monthly prune).

**`store_applications`**: add nullable `utm_source`, `utm_medium`, `utm_campaign` (varchar 32). Small table: plain
`ALTER TABLE ADD COLUMN` (metadata-only).

### A.4. Ingestion

`POST /api/v1/analytics/events` (route in `routes/api.php`, group `auth:sanctum`, **not** `tenant`):

- Body ≤ 32 KB (`Content-Length` check + nginx `client_max_body_size` stays default 1m — enforce in the controller):
  `{session_id, app_version, platform, start_param?, events: [{uid, name, at, store_id?, product_id?, category_id?, props?}]}`,
  1–50 events.
- 202 `{accepted, dropped}` always for well-formed bodies (never tells which event was dropped); 422 only on a malformed
  envelope; 429 on limits.
- Rate limits (`RateLimiter::for('analytics-events')`): 20 requests/min and 3 000 events/day per user; per IP 120/min.
- Drop silently: `config('analytics.enabled') = false`; user role ≠ customer; blocked user (`blocked_at`); unknown event
  name or props key; `store_id`/`product_id` not integers; events older than 24 h.
- `order_placed`: resolve `order_uuid` → `orders.id` WHERE `user_id` = token user (one indexed query per batch); unknown
  → dropped.
- `start_param`: parsed by `App\Domain\Analytics\StartParam` (`c_<code>`, `s_<store>`, referral, invite, unknown); raw
  param never stored; `campaign_id` resolved via a 60 s in-process+Cache map of active codes.
- One `INSERT` of all rows (`DB::table('analytics_events')->insert`), outside any transaction; RevisionMap NONE so no
  ChangeRevision touch. No queue, no Redis write except the rate limiter.
- Privacy: no IP, no User-Agent, no Telegram ID, no free text stored.

Mini App client `customer/miniapp/src/lib/analytics.ts` (no dependency, ≤ 1.5 KB gz): in-memory queue (max 200),
`track(name, data)`, flush every 10 s, on `visibilitychange: hidden` and on Telegram `viewportChanged`/close via
`fetch(url, {method:'POST', keepalive:true, headers:{Authorization}})`; waits for `useTelegramAuth` to finish before the
first flush; 3 tries with backoff then drop; `sessionStorage` key `rz_session` `{id, last}`; never throws. Hooks:
`App.tsx` (app_open, once per session), `StoreDetail.tsx` (store_view, category_view), `ProductSheet.tsx`
(product_view), `hooks/useAddToCart.ts` (add_to_cart), `Cart.tsx` (cart_view, checkout_start, checkout_error,
order_placed after the `/orders` 2xx, payment_redirect before `tg.openLink`), `Stores.tsx`/search (search counts only),
`HeroPromoSlider.tsx` (promo_banner_click).

`POST /api/v1/landing/visits` (no auth; `throttle:landing-visits` 30/min per IP; nginx `location = /api/v1/landing/visits`
in `infra/landing/nginx.conf`, `limit_except POST`, `client_max_body_size 2k`): `{path, utm_source, utm_medium,
utm_campaign, ref, lang}`; values lower-cased, cut to 32 chars, `[a-z0-9_.-]` only, `ref` reduced to host; requests
with a bot-like User-Agent (`bot|crawl|spider|headless|preview`) are counted nowhere; UA not stored. Landing JS
(`infra/landing/site/js/app.js`): one beacon per page load (`navigator.sendBeacon` with `Blob` JSON — same origin, CSP
`connect-src 'self'` unchanged); if `utm_campaign` matches `^[a-z0-9_]{3,32}$`, rewrite `a[href^="https://t.me/rizqimbot"]`
to add `?start=c_<code>`; `form.js` adds the utm fields to the application POST.

Bot `/start c_<code>` (`TelegramMessageHandler::start`): when no user with that telegram_id exists, upsert
`pending_attributions`; `AuthenticateTelegramUserAction` (new-user branch, next to the referral code at line ~147)
writes `user_attributions` from `start_param` or the pending row, inside the same DB transaction as the user insert;
never for staff (invite path) or platform accounts.

`POST /api/v1/client-errors` (optional `auth:sanctum` resolution; `throttle:client-errors` 30/min per user or IP, 300/day
per IP) and `POST /api/v1/pos/client-errors` (terminal device auth, 60/min per device): `{app, release, platform,
type, message, stack, path, context}` 1–10 reports; server scrubs with `SentryScrubber::scrubString/scrubArray`,
normalises path, computes `fingerprint = sha1(app|type|first 5 normalised frames)`, upserts the group, stores the sample
subject to the per-group cap. Clients: `partner/web/src/app/error.tsx` + `global-error.tsx`,
`platform/admin/src/app/error.tsx` + `global-error.tsx`, a shared `packages/ts/ui` (or `staff`) `reportClientError()`
helper with `window.onerror`/`unhandledrejection` registered in each app's `Providers.tsx`; Flutter: a
`ClientErrorReporter` in `packages/dart/rizqim_core` (pure Dart + dio already used by `pos_data`), wired in each
`pos/apps/*/lib/main.dart` with `runZonedGuarded`, `FlutterError.onError`, `PlatformDispatcher.instance.onError`, queue
file `client_errors.json` in `getApplicationSupportDirectory()` (max 50, oldest dropped).

### A.5. Aggregation jobs

All idempotent "delete day + insert" under `pg_advisory_xact_lock(hashtext('<table>:<date>'))`, the
`PlatformStatsRefresher` pattern; registered in a new `App\Providers\AnalyticsServiceProvider` (`callAfterResolving
(Schedule::class)`), dispatched to the `reports` queue (`queue-reports` worker, timeout 150 s — split per day / per store
chunk to stay under it). Times Asia/Tashkent, jittered off :00.

| Job | Schedule | Reads | Writes |
|---|---|---|---|
| `AggregateFunnelJob(date)` | today every 15 min (`*/15`, offset 7); 00:37 for D-1, D-2 | the day's `analytics_events` partition range + orders of linked order_ids | `analytics_funnel_daily`, `analytics_product_daily` |
| `RefreshCustomerStatsJob(store)` | 01:13 nightly for stores with orders created/changed in the last 2 days (by `orders(store_id, created_at, id)` over 35 days); Sunday 02:17 all stores | orders of the store (35 days incremental / all for weekly) | `store_customer_stats`, `store_cohort_stats` (last 24 months) |
| `RefreshPlatformCustomerStatsJob` | 01:43 nightly, chunks of 5 000 users with recent orders (`orders(user_id, created_at, id)`) | orders, `user_attributions`, `users.referred_by` | `platform_customer_stats`, `platform_cohort_stats` |
| `RefreshCampaignStatsJob(date)` | hourly at :23 for today; 00:53 for last 35 days | events, `user_attributions`, orders, ledger by order_id | `campaign_daily_stats` |
| `SnapshotKeshbekJob` | 00:07 | users.balance, payments, ledger | `keshbek_daily_snapshots` (D-1) |
| `SendOwnerDigestJob` | 08:41 | `SalesReportService` for D-1 per store (owners opted in) | Telegram (existing notification path, `NotificationPreference` type `daily_digest`) |

Backfill commands (console, owner of nothing — DML only): `analytics:customers-backfill {--store=} {--from=}`,
`analytics:platform-customers-backfill`, `analytics:campaigns-backfill {--from=}` (referral-based rows only: campaigns
did not exist before), `analytics:keshbek-backfill {--from=}`. Funnel: no backfill. Run once after deploy of steps 13,
17, 18 (documented in `docs/OPERATIONS.md`). Partition months come from `db:partitions:ensure` (already scheduled 00:40
UTC) once the table is in `config/partitions.php`.

Cost on polled endpoints: none. `GET /owner/analytics` (polled 60 s by the partner dashboard), `GET /admin/today`, the
staff order lists and catalogue feeds are untouched; new endpoints are user-triggered (`refetchOnWindowFocus` only, no
`refetchInterval`), cached `Cache::remember(120 s)` keyed by store, branch, params and local day.

### A.6. API endpoints

Owner (`routes/api_management.php`, group `auth:sanctum, staff.store, tenant, throttle:api`; controllers in
`app/Http/Controllers/Api/V1/Management/`; money as decimal strings like `/owner/reports/*`):

| Method, path | Permission | Notes |
|---|---|---|
| `GET /owner/reports/sales` + `include=hours,weekdays,heatmap` + `compare=previous_period|previous_year` | `reports.view` | extends `SalesReportService::sales`; `previous` = same shape; `delta` per summary key |
| `GET /owner/reports/branches?from&to&compare` | `reports.view` | branch-bound caller: own branch only (one row) |
| `GET /owner/reports/customers?from&to&branch_id` | `reports.view` | summary, monthly new/returning, histogram, `top` (20, masked) |
| `GET /owner/reports/cohorts?months=6..24` | `reports.view`, store-wide callers only (branch-bound: 403 `store_wide_report`) | from `store_cohort_stats` |
| `GET /owner/reports/funnel?from&to` | `reports.view`, store-wide | `analytics_funnel_daily` + `data_since` |
| `GET /owner/reports/menu-conversion?from&to&limit` | `reports.view`, store-wide | `analytics_product_daily` |
| `GET /owner/reports/cancellations?from&to&branch_id` | `reports.view` | by reason, by actor kind, lost revenue, unpaid-expired |
| `GET /owner/reports/quality?from&to&branch_id` | `reports.view` | acceptance/prep times, rates, ratings, closed minutes |
| `GET /owner/reports/export?report=...` new `hours`, `branches`, `customers`, `cohorts` + `format=csv|xlsx` | `reports.view` | `throttle:report-csv`; no phone in any file |
| `GET /owner/finance/statement?from&to` | `finance.view`, `not-staff-session` | A.1 statement |
| `GET /owner/finance/statement/export?from&to&format=csv|xlsx` | same + `throttle:report-csv` | sheet 1 lines, sheet 2 ledger rows |

Admin (`routes/api_admin_*.php`, existing `admin.can:` middleware; controllers `app/Http/Controllers/Api/V1/Admin/`):

| Method, path | Permission |
|---|---|
| `GET /admin/metrics/customers?months`, `GET /admin/metrics/cohorts?months&segment` | `finance.view,stores.view` (as `/admin/metrics`) |
| `GET /admin/metrics/mini-app-funnel?from&to&platform&campaign_id` | `finance.view,stores.view` |
| `GET/POST /admin/marketing/campaigns`, `GET/PATCH /admin/marketing/campaigns/{id}`, `POST .../{id}/deactivate` | view `marketing.view`; write `marketing.manage` |
| `GET /admin/marketing/campaigns/{id}/report?from&to` | `marketing.view` |
| `GET /admin/marketing/referrals?from&to` | `marketing.view`; phones via `AdminPii` |
| `GET /admin/marketing/landing?from&to` | `marketing.view` |
| `GET /admin/marketing/promo-codes` (existing) + `new_customers`, `returned_30d` in `stats` | `marketing.view` |
| `GET /admin/finance/keshbek?from&to` | `finance.view` |
| `GET /admin/finance/stores/{id}/statement?from&to` (+ `/export`) | `finance.view` (+ `export` for the file) |
| `GET /admin/monitoring/client-errors`, `GET .../{id}`, `POST .../{id}/resolve|mute` | view `monitoring.view`; write `monitoring.manage` |
| `GET /admin/exports/campaigns`, `/admin/exports/cohorts` | `export` + view permission, `throttle:admin-export` |

Public: `POST /analytics/events`, `POST /landing/visits`, `POST /client-errors`, `POST /pos/client-errors` (A.4). All
documented with Scramble attributes; `packages/api-spec/openapi.json` and the TS/Dart clients regenerated in the step.

### A.7. Exports

- CSV: the existing `SalesReportService` writer (BOM, `'` before formula-like cells, money two decimals, store tz).
- XLSX: `openspout` — reuse `App\Services\Admin\Export\XlsxExporter` for admin (PII through `ExportCells`, one audited
  reveal); for owners a small `App\Services\Order\Export\OwnerXlsx` on openspout, streamed, tenant re-bound inside the
  stream closure exactly like `ReportController::export` (M4: the binding is gone when the body is written).
- PDF: none on the server. Partner web `/finance/statement/print` (print CSS, `window.print()`).
- Caps: 366 days; customers export without phones/names beyond the masked form.

### A.8. Permissions

Store roles (`App\Services\Auth\RolePermissions`, overrides per store): `reports.view` (owner, manager default) for all
`/owner/reports/*`; `finance.view` (owner default, grantable) for the statement; if decision 2 = B, new
`Permission::CUSTOMERS_VIEW = 'customers.view'` (owner only by default, grantable, audit `customers.top_revealed`).
Branch-bound managers: branch-scoped reports for their branch (`PermissionService::actingBranchId`), store-wide-only
reports 403. Terminal PIN sessions: unchanged (finance never; reports as today).

Platform roles × new screens (`PlatformRolePermissions` defaults; union over a member's roles, Wave P):

| Screen | superadmin | operator | support | finance | sales | viewer |
|---|---|---|---|---|---|---|
| Metrics → Mijozlar / Kogorta / Voronka (`finance.view` or `stores.view`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Marketing → Kampaniyalar, Do'st taklifi, Sayt (view `marketing.view`) | ✓ | — | — | — | — (decision 13: grant) | ✓ |
| Kampaniya yaratish/o'zgartirish (`marketing.manage`) | ✓ | — | — | — | — | — |
| Moliya → Keshbek, restoran akti (`finance.view`) | ✓ | — | — | ✓ | — | ✓ |
| Monitoring → Ilova xatolari (`monitoring.view`) / hal qilish (`monitoring.manage`) | ✓/✓ | ✓/✓ | — | — | — | ✓/— |
| Eksportlar (`export`) | ✓ | — | — | ✓ | — | — |
| To'liq telefon (`pii.view`) | ✓ | — | — | — | — | — |

Tests: `tests/Feature/AdminMultiRoleTest.php` gains cases (support+finance sees Keshbek; viewer sees no PII and no
export; sales+support sees Metrics but not Campaign manage).

### A.9. Tests required (every step lists the ones it touches)

- Definitions: fixtures around the local midnight (order at 23:59 vs 00:01 Asia/Tashkent = 18:59/19:01 UTC), a store
  with `timezone` other than Tashkent, weekday on a Sunday/Monday boundary, compare periods of different month lengths.
- Reconciliation: Σ by_hour = Σ by_weekday = summary revenue; customers' revenue + anonymous = sales revenue; statement
  invariants (A.1) on a fixture with every ledger path (commission wallet+debt, platform settlement with netting,
  partial and full refund, referral reward, subscription and void, top-up, payout with netting, adjustment, retried
  settlement); statement month == `CommissionStats::inMonth`; keshbek invariant; campaign commission == ledger sums.
- Tenancy: each new owner route in `tests/Feature/Tenancy/routes.php` (CrossTenantRouteTest, TenantMiddlewareCoverageTest);
  `TenantRlsInventoryTest` updated; `TenantRlsPolicyTest`-style test that store B's rows of the new RLS tables are
  invisible inside store A's tenant transaction; `RevisionMapCoverageTest` NONE entries; streamed exports re-bind the
  tenant (`StreamedResponseTenantTest` pattern).
- PII: a test that walks every new owner endpoint's JSON and asserts no `\+?998\d{9}`, no unmasked 9-digit phone, no
  `telegram_id`/`address` keys; admin endpoints mask without `pii.view` and audit the reveal with it.
- Ingestion: limits (429), drops (staff, blocked, unknown names/props, old events), `order_placed` of another user
  dropped, 32 KB cap, no DB write when `analytics.enabled=false`, one INSERT per batch (query count), no ChangeRevision
  touch (spy), works as `rizqim_app` (no DDL; DEFAULT partition catches a month not yet attached).
- Partitions: `analytics_events` and `client_error_reports` in `PartitionManager::status()`, `db:partitions:ensure`
  attaches months, `PartitionsCheck` green; migration up/down as owner (`#[AsSchemaOwner]`).
- Jobs: idempotent (run twice = same rows), concurrent runs serialised by the advisory lock, late status change
  (refund on D+3) reflected after the nightly run, backfill commands produce the same rows as the incremental path.
- Attribution: `c_` via Mini App and via bot `/start` then sign-in; first touch never overwritten; staff invite and
  referral params unaffected (`AuthTelegramLoginTest`, `AuthReferralServiceTest`, `AuthTelegramWebhookBindingTest` stay
  green); landing beacon drops bots and normalises values.
- Polled endpoints: existing query-count / ETag tests for `/owner/analytics` and `/admin/today` unchanged.
- Frontend: vitest for `lib/analytics.ts` (queue, flush on hide, waits for auth, never throws), report components
  (table view equals chart numbers), statement print view; partner/admin `error.tsx` reporters; Flutter unit tests for
  the reporter queue (file cap, offline replay). `e2e` smoke for the landing beacon (`infra/landing/tests/smoke.mjs`).
- OpenAPI: `ApiDocsSpecTest` green, spec regenerated.

### A.10. Per-step file map (where to extend)

1. `infra/landing/site/i18n/uz.js`, `ru.js` (`privacy.s2.b`, `s4.b`, `s5.b`, `s6.b`, `s7.b`), `docs/legal/README.md`,
   `docs/API.md` (new section "Analitika hodisalari"), `config/analytics.php` (`enabled` false).
2. Migration `create_analytics_events_table` (partitioned via `PartitionManager`), `config/partitions.php`,
   `app/Domain/Analytics/{AnalyticsEvent,StartParam,EventIngestor}.php`, `Http/Controllers/Api/V1/Analytics/EventController.php`,
   `routes/api.php`, `AppServiceProvider` limiter, `RevisionMap`, `TenantRlsInventoryTest`.
3. `customer/miniapp/src/lib/analytics.ts` (+test), hooks in `App.tsx`, `pages/StoreDetail.tsx`,
   `components/ProductSheet.tsx`, `hooks/useAddToCart.ts`, `pages/Cart.tsx`, `pages/Stores.tsx`,
   `components/HeroPromoSlider.tsx`.
4. Migrations `campaigns`, `user_attributions`, `pending_attributions`; `app/Actions/Auth/AuthenticateTelegramUserAction.php`,
   `app/Services/Telegram/TelegramMessageHandler.php`, `app/Services/Admin/Marketing/Campaigns.php`,
   `AdminMarketingCampaignController`, `routes/api_admin_marketing.php`; admin `platform/admin/src/components/marketing/`.
5. `landing_daily_visits` migration, `LandingVisitController`, `infra/landing/nginx.conf`, `site/js/app.js`, `site/js/form.js`,
   `store_applications` utm columns + `ApplicationPipeline::submit`; `store_availability_events` + writes in
   `OwnerSettingsController` / admin store status / `LedgerService` lock-unlock.
6. Migrations `client_error_groups`, `client_error_reports`; `app/Domain/ClientErrors/*`, controllers, Monitoring API
   (`routes/api_admin_monitoring.php`), alert rule in `App\Services\Admin\Monitoring` + `EvaluateAlertsJob`.
7. `partner/web/src/app/error.tsx`, `global-error.tsx`, `components/Providers.tsx`; same in `platform/admin`; helper in
   `packages/ts/ui`; admin `components/monitoring/ClientErrors*.tsx`.
8. `packages/dart/rizqim_core/lib/src/client_error_reporter.dart`, `pos/apps/{cashier,waiter,kds,customer_display}/lib/main.dart`.
9. `app/Services/Order/SalesReportService.php`, `OwnerAnalyticsService.php` (new `byHour`, `byWeekday`, `heatmap`,
   extended `byBranch`), `ReportController.php`, `routes/api_management.php`.
10. `partner/web/src/components/reports/{ReportsView,Heatmap,CompareBadge,BranchesView}.tsx`, `app/(app)/reports/branches/`,
    `customer/miniapp/src/pages/AdminAnalytics.tsx`, i18n sections.
11. `app/Services/Finance/Reports/StoreStatementService.php`, `StatementController` (Management), admin controller,
    `routes/api_management.php`, `routes/api_admin_finance.php`.
12. `partner/web/src/components/reports/StatementView.tsx`, `app/(app)/finance/statement/{page,print/page}.tsx`,
    `platform/admin/src/components/stores/` (store card tab).
13. Migrations of the four stats tables; `app/Domain/Analytics/Customers/*`, jobs, commands, `AnalyticsServiceProvider`.
14. `CustomerReportController`, `partner/web/src/components/reports/CustomersView.tsx`, Mini App `AdminReports.tsx` block.
15. `app/Services/Admin/Metrics/{CustomerMetrics,CohortMetrics}.php`, `AdminMetricsController`, `components/metrics/`.
16. `AggregateFunnelJob`, funnel tables, `routes/api_admin_metrics.php`, owner funnel/menu-conversion endpoints and views.
17. `campaign_daily_stats`, `RefreshCampaignStatsJob`, `Campaigns` report, referrals/landing reports, admin views, exports.
18. `keshbek_daily_snapshots`, `App\Services\Admin\Finance\KeshbekReport`, `PromoCodes::stats` extension, admin views.
19. `CancellationReportService`, `OwnerQualityService` (reusing `StoreQualityService` SQL), views.
20. `SendOwnerDigestJob`, `NotificationPreferenceService` type `daily_digest`, `TelegramContent` template.
21. `s_<store>` deep link in Mini App routing (`App.tsx` reads `start_param` → `/store/:id`), partner web campaign/QR
    page (QR via existing `chillerlan/php-qrcode` on the backend), owner campaign report.
