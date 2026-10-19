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
| Superadmin paneli | Filament qoladi (ichki boshqaruv) |
| POS qurilmalari | Hammasi: Windows, Android planshet, Android POS terminal (Sunmi, PAX, iMin), iPad |
| Kuryerlar | Aralash: restoranning o'z kuryerlari va Rizqim kuryer parki, bitta ilovada |
| Biznes model | Ikkalasi ham: POS obunasi (terminal/oy) va onlayn buyurtmadan komissiya; tarif har hamkor uchun tanlanadi |
| Deploy | Faqat Docker |
| Fiskal chek | **Shoshilinch, ochiq.** VM 393-son qarori (20.07.2026) umumiy ovqatlanishda soliq tizimlari bilan integratsiya qilinmagan hisob va buyurtma dasturlarini taqiqlaydi; kuchga kirish sanasi buxgalter/yurist bilan aniqlanmoqda. Yo'llar: Rizqim o'zi virtual kassa sifatida ro'yxatdan o'tadi yoki reyestrdagi provayderga yozma tasdiq bilan ulanadi; onlayn to'lovlarda chekni Payme/Click chiqaradi |

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
│       ├── pos_fiscal/     fiskal chek integratsiyasi
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
| 1 | Poydevor | Yangi papkalar tuzilmasi; backend modullari; OpenAPI va avtomatik TS/Dart klientlar; auth (refresh token, qurilmalar, POS uchun PIN); ruxsatlar (ofitsiant, kassir, oshpaz); hisoblarni telefon bo'yicha birlashtirish; push (FCM/APNs); menyu modifikatorlari va stop-list; `Order.channel`; idempotency; PostGIS zonalari; staging va feature flag'lar |
| 2 | Hamkor web va oshxona ekrani | Xodim ekranlarini mini-app'dan Next.js'ga ko'chirish; jonli buyurtmalar doskasi; menyu, stop-list, xodimlar, hisobotlar; `pos/apps/kds` |
| 3 | POS | Kassa va ofitsiant: stollar, chek, modifikatorlar, oshxonaga yuborish, to'lov (naqd, karta, QR), chekni bo'lish, chegirma, smena va X/Z hisobot, internetsiz ishlash, printerlar; fiskal chek; 1–2 restoranda pilot |
| 4 | Kuryer ilovasi | Smena, push, fon GPS, navigatsiya, topshirishni tasdiqlash, naqd pul, daromad; o'z va Rizqim kuryerlari uchun taqsimot va hisob-kitob |
| 5 | Hamkor mobil | Ega va menejer: buyurtmalar, xabarlar, tezkor hisobot |
| 6 | Mijoz mobil ilovasi | Mini-app imkoniyatlari, push, kuryerni xaritada kuzatish, saqlangan kartalar, sodiqlik |
| 7 | Python servislari | Analitika ombori, talab prognozi, kuryer taqsimoti (OR-Tools), tavsiyalar |

## Ish tartibi

- Har to'lqinda avval backend API kelishuvi (OpenAPI) birlashtiriladi, keyin ilovalar parallel quriladi.
- Har ilova o'z CI tekshiruvi va o'z PR'iga ega; ulkan PR'lar qilinmaydi.
- Har to'lqin oxirida ikkita mustaqil review va yashil CI.

## Egasidan kerak bo'ladiganlar

- Fiskal provayder tanlovi, hujjatlar va kalitlar; Uzum va Paynet hujjatlari va sinov kalitlari.
- Apple Developer va Google Play akkauntlari; iOS yig'ish uchun Mac yoki bulutli xizmat (masalan, Codemagic).
- Pilot restoran va real POS uskunalari (printer, kassa qutisi, terminal) sinov uchun.
- Ishlash muhitida Flutter SDK va pub.dev'ga tarmoq ruxsati.

## Texnik eslatmalar

- Reverb backend image'ida `ext-uv` (yoki `ext-event`) yo'q, shuning uchun bitta Reverb jarayoni taxminan 1000 ta bir
  vaqtdagi ulanishgacha ishlaydi; undan oshgan mijozlar so'rov bilan yangilanadi. 1-bosqichda image'ga `ext-uv` qo'shiladi.
