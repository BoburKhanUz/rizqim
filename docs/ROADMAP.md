# Rizqim yo'l xaritasi

Rizqim — bitta brend ostidagi to'liq tizim: mijoz kanallari (Telegram mini-app, mobil ilova), hamkor boshqaruvi,
restoran POS'i, oshxona ekrani va kuryer ilovasi. Hammasi bitta backend API'ga ulanadi.

Bu hujjat qabul qilingan qarorlar, papkalar tuzilmasi va bosqichlarni saqlaydi. Qaror o'zgarsa, shu yerda yangilanadi.

## Qabul qilingan qarorlar

| Mavzu | Qaror |
|---|---|
| Backend | Laravel monoliti qoladi, modullarga bo'linadi. Barcha ilovalar uchun bitta API |
| Boshqa tillar | Python faqat analitika, prognoz va kuryer taqsimoti uchun, alohida servis sifatida, ma'lumot yig'ilgandan keyin. Buyurtma va pul yo'lida emas |
| Mobil va POS ilovalar | Flutter (bitta Dart monoreposi, umumiy paketlar) |
| Hamkor web | Next.js. Biznes mantiq faqat backend'da |
| Telegram mini-app | React (hozirgi), `customer/miniapp`ga ko'chadi |
| Superadmin paneli | **RIZQIM boshqaruv markazi** — alohida Next.js ilova `platform/admin`, 2FA bilan (egasi qarori, 25–27.10.2026); eski Filament panellari 4-bosqichda (P4-33) o'chirildi ([PHASE4_PLAN.md](PHASE4_PLAN.md)) |
| POS qurilmalari | Hammasi: Windows, Android planshet, Android POS terminal (Sunmi, PAX, iMin), iPad |
| Kuryerlar | Aralash: restoranning o'z kuryerlari va Rizqim kuryer parki, bitta ilovada |
| Biznes model | Marketpleys buyurtmadan komissiya oladi (RIZQIM kuryeri 15%, o'z yetkazishi 10%, olib ketish 5%, zal 0%); POS pullik obuna: Start 149 000, Asosiy 249 000, Pro 449 000 so'm/oy, yillik = 10 oy, 14 kun sinov (egasi qarori, 24.10.2026). Obuna qarzi payoutdan ushlab qolinadi (28.10.2026) |
| Deploy | Faqat Docker |
| Fiskal chek | POS **v1 dan fiskal chek chiqaradi**: sotuv, qaytarish, bo'nak (avans) va kredit (nasiya) cheklari; provayderga bog'liq bo'lmagan fiskal qatlam (`App\Domain\Fiscal`), provayder tanlanguncha sandbox drayver, fiskalsiz drayver faqat demo uchun. Yo'l — provayder orqali (B) yoki Rizqimning o'z virtual kassasi (A) — keyin tanlanadi. Sabab: VM 393-son qarori **22.10.2026 dan** umumiy ovqatlanishda soliq tizimi bilan integratsiya qilinmagan hisob va buyurtma dasturlarini taqiqlaydi. Egasi yozma so'rov yubormaslikka qaror qildi; real tushum bilan pilot faqat haqiqiy fiskal drayver bilan. Rizqim chek chiqaradigan joyda eski kassa o'sha sotuvga chek chiqarmaydi (ikki marta hisoblashdan himoya). Mini App'da Payme/Click cheki restoran STIRi bilan. Batafsil: [POS_SPEC.md](POS_SPEC.md), 0 va 4-bo'limlar |
| POS birinchi formati | **Choyxona** (egasi qarori, 21.10.2026): bronsiz stol yoki tapchan, xona sessiyasi, oldindan buyurtma; osh markazi uning ichki rejimi. Keyingi formatlar tartibi: [PHASE3_PLAN.md](PHASE3_PLAN.md) |

## Asosiy arxitektura tamoyili: bitta buyurtma, barcha kanallar

Telegram, mobil ilova, POS (zal, olib ketish) va keyinchalik agregatorlardan kelgan buyurtma bitta `Order` bo'ladi,
kanal `channel` maydonida. Oshxona hammasini bitta ekranda va printerda ko'radi; stop-list va menyu hamma kanalga bir
vaqtda tarqaladi.

## Papkalar tuzilmasi

```
rizqim/
├── backend/                Laravel API
├── customer/
│   ├── mobile/             Flutter: mijoz ilovasi (Android, iOS)
│   └── miniapp/            React: Telegram mini-app
├── partner/
│   ├── web/                Next.js: hamkor boshqaruv paneli
│   └── mobile/             Flutter: ega va menejer
├── courier/                Flutter: kuryer ilovasi (o'z va Rizqim kuryerlari)
├── pos/
│   ├── apps/
│   │   ├── cashier/        Kassa: Windows, Android planshet, terminal, iPad
│   │   ├── waiter/         Ofitsiant: telefon yoki qo'l terminali
│   │   ├── kds/            Oshxona ekrani
│   │   └── customer_display/  Mijozga qaragan ikkinchi ekran
│   └── packages/
│       ├── pos_core/       chek, stol, smena, chegirma mantiqi
│       ├── pos_sync/       internetsiz ishlash: lokal baza va sinxronlash
│       ├── pos_hardware/   printer, kassa qutisi, skaner (escpos, sunmi, windows)
│       └── pos_ui/         sensor ekran komponentlari
├── packages/
│   ├── dart/               rizqim_api, rizqim_core, rizqim_ui
│   └── ts/                 api-client, ui
├── services/               Python servislari (keyinchalik)
├── infra/                  docker, nginx, deploy
└── docs/
```

Platforma papkalari (`android/`, `windows/`, `ios/`) har bir Flutter ilovasi ichida bo'ladi; kod platformalar uchun
takrorlanmaydi, faqat uskuna bilan ishlaydigan qism (`pos_hardware`) farqlanadi.

## Bosqichlar

| # | Bosqich | Tarkibi |
|---|---|---|
| 0 | Hozirgi ishni yakunlash | Review, tuzatishlar, VPS yo'lini olib tashlash, CI yashil, PR #1 merge |
| 1 | Poydevor | Yangi papkalar tuzilmasi; backend modullari; OpenAPI va avtomatik TS/Dart klientlar; auth (refresh token, qurilmalar, POS uchun PIN); ruxsatlar (ofitsiant, kassir, oshpaz); hisoblarni telefon bo'yicha birlashtirish; push (FCM/APNs); menyu modifikatorlari va stop-list; `Order.channel`; idempotency; yetkazish zonalari (GeoJSON, PostGIS'siz); staging va feature flag'lar. **Tugadi (PR #2–#5), faqat staging'dagi tekshiruv (P1-35) server kutmoqda** |
| 2 | Hamkor web va oshxona ekrani | Xodim ekranlari Next.js web panelda ham quriladi (Mini App'da ham qoladi, umumiy kod `packages/ts` da); batafsil: [PHASE2_PLAN.md](PHASE2_PLAN.md); jonli buyurtmalar doskasi; menyu, stop-list, xodimlar, hisobotlar; `pos/apps/kds`. **Tugadi (PR #6–#8), faqat staging'dagi tekshiruv (P2-34) server kutmoqda** |
| 3 | POS (choyxona birinchi) | Kassa va ofitsiant (telefon): zal xaritasi va xona hisobi, xona puli va minimal summa, bron, oldindan buyurtma va avans, nasiya, oshxonaga yuborish (sexlar, KDS), schyot va bo'lish, to'lov (naqd, karta, QR, aralash), smena va X/Z, printerlar, qisqa uzilishlarga chidamli ishlash; **fiskal chek v1 dan** (sotuv, qaytarish, bo'nak, kredit; sandbox drayver, keyin haqiqiy provayder); Mini App to'lovlarida fiskal chek; Toshkentdagi bitta xonali choyxonada pilotga tayyor. Ish tartibi: [PHASE3_PLAN.md](PHASE3_PLAN.md), spetsifikatsiya: [POS_SPEC.md](POS_SPEC.md). **Tugadi (PR #9–#13); real tushumli pilot fiskal provayder, server va qurilmalarni kutmoqda** |
| 4 | Boshqaruv markazi va billing v2 | RIZQIM jamoasi uchun 2FA'li boshqaruv markazi: Bugun, buyurtmalar, monitoring (1-to'lqin **tugadi**, PR #15); ovqatxonalarni ulash, billing v2, moliya va netting, POS obunalari (2-to'lqin); qolgan bo'limlar va eski panellarni o'chirish (3-to'lqin). [PHASE4_PLAN.md](PHASE4_PLAN.md) |
| 5 | Integratsiyalar | Boshqa POS'lar (Alipos, iiko, R-Keeper, Poster...) va agregatorlar (Yandex Eats, Uzum Tezkor) bilan ikki tomonlama ulanish, ochiq RIZQIM Partner API. [PHASE5_PLAN.md](PHASE5_PLAN.md) |
| 6 | POS barcha formatlar uchun | Kafe, restoran, fast food, somsaxona, kabobxona, osh markazi, qahvaxona, stolovaya, banket, faqat yetkazadigan oshxona: format profillari va yetishmayotgan imkoniyatlar. [PHASE6_PLAN.md](PHASE6_PLAN.md) |
| 7 | Kuryer ilovasi va RIZQIM kuryer tarmog'i | Smena, push, fon GPS, navigatsiya, topshirishni tasdiqlash, naqd pul, daromad; o'z va RIZQIM kuryerlari uchun taqsimot, dispetcherlik va hisob-kitob |
| 8 | Hamkor mobil | Ega va menejer: buyurtmalar, xabarlar, tezkor hisobot |
| 9 | Mijoz mobil ilovasi | Mini-app imkoniyatlari, push, kuryerni xaritada kuzatish, saqlangan kartalar, sodiqlik |
| 10 | Python servislari | Analitika ombori, talab prognozi, kuryer taqsimoti (OR-Tools), tavsiyalar |

Tartib o'zgarishi mumkin: 5, 6 va 7-bosqichlarning qismlari bir-biriga xalaqit bermaydi va bozor talabiga qarab
parallel boshlanishi mumkin (masalan, integratsiya muzokaralari hozirdan).

## Ish tartibi

- Har to'lqinda avval backend API kelishuvi (OpenAPI) birlashtiriladi, keyin ilovalar parallel quriladi.
- Har ilova o'z CI tekshiruvi va o'z PR'iga ega; ulkan PR'lar qilinmaydi.
- Har to'lqin oxirida ikkita mustaqil review va yashil CI.

## Egasidan kerak bo'ladiganlar

- Fiskal provayder tanlovi — **bo'nak va kredit cheklarini API orqali qo'llaydigani**; API hujjati va sinov kalitlari
  (3-bosqich, to'lqin 3). Kelmasa POS sandbox drayverda qoladi va real tushum bilan pilot bo'lmaydi.
- A yo'li (Rizqimning o'z virtual kassasi) bo'yicha qaror — keyinroq; ungacha B yo'li.
- Buxgalter bilan tekshirish: xizmatlar uchun MXIK kodlari, ushlab qolingan avans cheki, aylanma soliq chegarasi
  ([POS_SPEC.md](POS_SPEC.md), 4.9, T1–T9).
- Pilot choyxona (Toshkent, 8–20 xona), uning soliq rejimi va hozirgi kassasi; 2–3 choyxona egasi bilan suhbat va
  bitta payshanba oqshomi kuzatuvi.
- Uzum va Paynet hujjatlari va sinov kalitlari.
- Apple Developer va Google Play akkauntlari; iOS yig'ish uchun Mac yoki bulutli xizmat (masalan, Codemagic).
- Real POS uskunalari sinov uchun: Android planshet yoki Sunmi, 1–2 Android telefon (ofitsiant), LAN printer, kassa
  qutisi.
- Ishlash muhitida Flutter SDK va pub.dev'ga tarmoq ruxsati.

## Texnik eslatmalar

- Reverb backend image'ida `ext-uv` (yoki `ext-event`) yo'q, shuning uchun bitta Reverb jarayoni taxminan 1000 ta bir
  vaqtdagi ulanishgacha ishlardi. 1-bosqichda (P1-13) image'ga `ext-uv` qo'shildi, cheklov olib tashlandi.
