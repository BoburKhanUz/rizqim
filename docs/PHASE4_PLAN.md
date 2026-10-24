# 4-bosqich rejasi: RIZQIM boshqaruv markazi va yangi billing

Holat: **taklif, egasining tasdig'ini kutmoqda** (24.10.2026).

## Maqsad

RIZQIM — ovqat buyurtma qilish va yetkazib berish marketpleysi (buyurtmadan foiz oladi) va ovqatxonalarni tez ulash
uchun pullik POS (B2B SaaS). Hozirgi superadmin paneli (Filament, `/superadmin`) loyiha boshida oddiy "restoranlar
ro'yxati va balans" uchun yozilgan: inglizcha-o'zbekcha aralash, "Laravel" nomi bilan, jonli buyurtmalar, ovqatxonani
ulash jarayoni, POS obunalari, qurilmalar, fiskal xatolar va tizim xatolarini ko'rsatmaydi.

Bu bosqichda:
1. **Boshqaruv markazi** — hamkor paneli uslubida yangi web ilova (alohida manzil, masalan `admin.<domen>`), RIZQIM
   jamoasi butun platformani bitta joydan boshqaradi: marketpleys, B2B mijozlar (ovqatxonalar), POS, moliya,
   yordam, xatolar monitoringi.
2. **Billing v2** — egasi tasdiqlagan narxlar (24.10.2026): POS tariflari Start / Asosiy / Pro, kanal bo'yicha
   komissiya, yillik to'lov, qurilmalar alohida narxda.
3. Eski Filament superadmin paneli yangi markaz to'liq ishlagach o'chiriladi (shungacha zaxira).

## Joriy holat (o'rganish natijasi)

- Superadmin hamma ishni Filament panelda qiladi (sessiya cookie). Superadmin uchun API bor-yo'g'i 4 ta
  (`/v1/superadmin/dashboard`, `stores`, `locations`, do'kon yaratish) — yangi markaz uchun backend API'ning ~90%
  yangidan yoziladi.
- Ovqatxonani ulash jarayoni yo'q: `pending`, `under_review`, `rejected` holatlari bor, lekin ishlatilmaydi;
  `StoreStatusLog` jadvali hech qachon yozilmaydi.
- Barcha do'konlar bo'yicha buyurtmalar ro'yxati yo'q (Filament'dagi yashirin ro'yxatning tablari ishlamaydi).
- To'lovlar: payout tasdiqlash, balans to'ldirish, hisob-kitob xatolari (settlement) faqat Filament yoki
  artisan/Telegram orqali; qo'lda tuzatish (adjustment) amali umuman yo'q.
- POS: qurilmalar, smenalar, fiskal xatolar, oflayn sinxron xatolari faqat har bir do'kon ichida ko'rinadi.
- Monitoring: Sentry, `/health`, JSON loglar, backup bor; lekin ilova ichida xatolar, yiqilgan navbat ishlari
  (failed jobs), scheduler holati va ogohlantirishlar tarixi ko'rinmaydi. Superadmin ogohlantirishlari faqat
  Telegram'ga ketadi va saqlanmaydi.
- Kirish: superadmin uchun API'da login yo'q (faqat Telegram Mini App), ikki bosqichli tasdiq (2FA) yo'q.
  Jamoa rollari yo'q — har bir xodim to'liq superadmin bo'lishi kerak.

## Boshqaruv markazi bo'limlari

| Bo'lim | Nima bo'ladi |
|---|---|
| **Bugun** | Bugungi buyurtmalar va savdo (GMV), komissiya va obuna daromadi; muammoli buyurtmalar (qabul qilinmagan, kechikkan, kuryersiz); yangi ovqatxona arizalari; oflayn kassalar va fiskal xatolar; tizim holati (xatolar, navbat, scheduler) |
| **Buyurtmalar** | Barcha ovqatxonalar bo'yicha jonli ro'yxat va qidiruv, holat bo'yicha filtr, kechikish chegaralari (SLA); buyurtma kartasi (tarix, to'lov, kuryer); aralashish: bekor qilish, pulni qaytarish, holatni tuzatish — sabab bilan va jurnalga yoziladi |
| **Ovqatxonalar (B2B mijozlar)** | Ulanish jarayoni: ariza → tekshiruv (hujjatlar, STIR, bank) → menyu → sinov → faol; har bir ovqatxonaning **360° kartasi**: profil va filiallar, holat tarixi, tarif va obuna, qurilmalar, fiskal holat, balans va qarz, buyurtmalar, shikoyatlar, jurnal; amallar: to'xtatish/faollashtirish sabab bilan, komissiya va kredit limiti, taklif havolasi, egasi nomidan kirish (cheklangan, jurnalga yoziladi) |
| **POS** | Obunalar (sinov, faol, qarzdor, bekor), sinov muddati tugayotganlar, hisob-fakturalar; qurilmalar (onlayn/oflayn, oxirgi aloqa, versiya); smenalar va kassa farqlari; fiskal cheklar (yiqilgan, kutilayotgan, qayta yuborish); oflayn sinxron xatolari |
| **Moliya** | Kanal bo'yicha komissiya, GMV va daromad grafiklari; ovqatxonalar balansi va qarzi; to'lov so'rovlari (payout) navbati — tasdiqlash/rad etish; balans to'ldirishlar; hisob-kitob xatolari va qayta urinish; qo'lda tuzatish (sabab bilan, ikki kishi tasdig'i); Rizqim daromadi hisoboti (komissiya + obuna) |
| **Mijozlar va yordam** | Foydalanuvchilar (mijoz, kuryer, xodim) qidiruvi, bloklash, sessiyalarni yopish; shikoyatlar qutisi (javob, hal qilish); pul qaytarishlar |
| **Marketing** | Bannerlar va aksiyalar, promo-kodlar (ishlatilish statistikasi bilan), kategoriyalar tartibi |
| **Monitoring** | Xatolar (Sentry'dan oxirgi xatolar), yiqilgan navbat ishlari (qayta urinish/o'chirish), scheduler va navbat holati, Reverb, backup yangiligi, ogohlantirishlar tarixi (Telegram'ga ketganlari ham saqlanadi) |
| **Tizim** | Sozlamalar (SMS, to'lov tizimlari — maxfiy kalitlar yashirin), viloyatlar va tumanlar, feature flag'lar, tariflar katalogi; **Jamoa**: RIZQIM xodimlari va rollari; harakatlar jurnali (kim, qachon, nima qildi) |

Dizayn: hamkor paneli bilan bir xil dizayn tizimi (Rizqim ranglari, yorug' va qorong'i mavzu), to'liq o'zbekcha
(rus va ingliz tarjimasi keyin), telefonda ham ishlaydi.

## Jamoa rollari (taklif)

| Rol | Nima qila oladi |
|---|---|
| Superadmin | Hammasi, jamoa va rollar, tizim sozlamalari |
| Operator | Bugun, buyurtmalar (aralashish bilan), ovqatxonalar (ko'rish, ulash jarayoni), yordam |
| Yordam (support) | Shikoyatlar, foydalanuvchilar, buyurtmalarni ko'rish, pul qaytarishni so'rash (tasdiqni moliya beradi) |
| Moliya | Moliya, billing, payout tasdiqlash, qo'lda tuzatish, hisobotlar |
| Savdo (onboarding) | Ovqatxona arizalari, ulash jarayoni, tarif biriktirish |
| Faqat ko'rish | Hamma bo'limni ko'radi, o'zgartira olmaydi |

Har bir pul amali va holat o'zgarishi sabab bilan yoziladi; katta qo'lda tuzatishlar ikkinchi xodim tasdig'ini talab
qiladi.

## Billing v2 (egasi tasdiqlagan narxlar)

| Tarif | Oylik | Yillik (12 oy = 10 oy) | Ichida |
|---|---|---|---|
| Start | 149 000 so'm | 1 490 000 | zal, xona puli, choy-non, smena, Z, RIZQIM buyurtmalari, 1 kassa |
| Asosiy | 249 000 so'm | 2 490 000 | + bron va bo'nak, nasiya, KDS, xizmat haqi; 2 terminal |
| Pro | 449 000 so'm | 4 490 000 | + Yandex/Uzum buyurtmalari (keyin), banket (keyin), filiallar hisoboti; 3 terminal |

- Qo'shimcha kassa 59 000, qo'shimcha oshxona ekrani 29 000 so'm/oy; ofitsiant telefonlari cheklanmagan va bepul.
- Sinov 14 kun. Tarifda yo'q funksiya kassada "Asosiy tarifda bor" deb ko'rsatiladi.
- Komissiya kanal bo'yicha: RIZQIM kuryeri 15%, ovqatxona o'zi yetkazsa 10%, olib ketish 5%, zal (POS) 0%.
- O'tgan oyda 1 mln so'mdan ko'p komissiya to'lagan ovqatxonaga keyingi oy POS asosiy narxi 50% arzon.
- Mavjud do'konlar xavfsiz o'tkaziladi (hech kimning ishi to'xtamaydi).

## To'lqinlar

```
Oldindan  P4-01 Ma'lum xatolarni tuzatish (Bronlar va Qurilmalar sahifasi, bron oynasi, kassa zal xaritasi)

To'lqin 1 (backend asos, parallel)
          P4-11 Admin kirish: email+parol, 2FA (TOTP ilova), jamoa rollari va ruxsatlari, admin jurnali
          P4-12 Billing v2: tariflar, funksiya cheklovlari, qurilma narxlari, yillik davr, kanal komissiyasi, chegirma qoidasi
          P4-13 Admin API: ovqatxonalar, ulash jarayoni (holat mashinasi + StoreStatusLog), filiallar, foydalanuvchilar, egasi nomidan kirish
          P4-14 Admin API: barcha buyurtmalar, SLA va muammoli buyurtmalar, aralashish amallari, admin real-time kanali
          P4-15 Admin API: moliya (ledger, payout navbati, to'ldirish, hisob-kitob xatolari, qo'lda tuzatish, daromad qatorlari) va billing boshqaruvi
          P4-16 Admin API: POS (qurilmalar, smenalar, fiskal va sinxron xatolari), monitoring (health, failed jobs, ogohlantirishlar tarixi), ma'lumotnomalar (kategoriya, viloyat, flag, sozlama, to'lov tizimi, promo, shikoyat)

To'lqin 2 (web ilova, parallel)
          P4-20 Umumiy UI paketini ajratish (hamkor panelidan) va admin ilova qobig'i: kirish, 2FA, menyu, rollar
          P4-21 Bugun va Buyurtmalar
          P4-22 Ovqatxonalar: ulash jarayoni va 360° karta
          P4-23 Moliya va POS/Billing
          P4-24 Monitoring, Mijozlar va yordam, Marketing, Tizim va Jamoa

To'lqin 3
          P4-31 E2E (haqiqiy server bilan Playwright), P4-32 mustaqil review va tuzatishlar,
          P4-33 jamoa uchun qo'llanma, P4-34 eski Filament panelni o'chirish (egasi tasdig'idan keyin)
```

Har to'lqin bitta PR; siz merge qilganingizdan keyin keyingisi boshlanadi.

## Tekshiruv

- Har bir admin API: ruxsat testlari (har bir rol uchun nima mumkin, nima yo'q), jurnalga yozilishi, pul amallari
  uchun idempotentlik.
- Billing: oy oxiri hisob-faktura testlari, yillik davr, chegirma qoidasi, tarif cheklovlari kassada.
- Web: lint, typecheck, unit testlar, haqiqiy server bilan e2e; skrinshotlar galereyasi.
- Xavfsizlik: 2FA majburiy, sessiya muddati, IP cheklovi ixtiyoriy, maxfiy kalitlar hech qachon to'liq ko'rsatilmaydi.

## Egasidan kerak

1. Admin manzili (masalan `admin.rizqim.uz`) — staging bilan birga.
2. 2FA usuli: telefon ilovasi (Google Authenticator) — tavsiya; yoki Telegram kodi.
3. Jamoa rollari ro'yxatini tasdiqlash (yuqoridagi jadval).
4. Egasi nomidan kirish: faqat ko'rish (tavsiya) yoki to'liq (jurnal bilan).
5. RIZQIM kuryerlari (15% stavka): hozir qilinadimi yoki keyin — hozircha 10% va 5% ishlaydi.
6. Eski Filament panelni yangi markaz tayyor bo'lgach o'chirishga rozilik.
