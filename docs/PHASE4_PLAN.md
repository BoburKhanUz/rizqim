# 4-bosqich rejasi: RIZQIM boshqaruv markazi va yangi billing

Holat: **tasdiqlangan** (27.10.2026). P4-01 tugadi (PR #14), **1-to'lqin tugadi** (PR #15); keyingisi — 2-to'lqin.
Egasining qarorlari: 12-bo'lim.

## 1. Nima uchun

RIZQIM ikki biznesdan iborat:

1. **Marketpleys** — bitta RIZQIM Telegram boti va Mini App orqali ovqat buyurtma qilish va yetkazib berish;
   daromad — buyurtmadan foiz (komissiya).
2. **B2B SaaS** — ovqatxonalarni tez ulash va qamrash uchun pullik POS (kassa, ofitsiant, KDS, bron, nasiya);
   daromad — oylik yoki yillik obuna.

Hozirgi superadmin paneli (Filament, `/superadmin`) loyiha boshida oddiy "restoranlar ro'yxati va balans" uchun
yozilgan va bu ikki biznesni boshqarishga mos emas: inglizcha-o'zbekcha aralash, "Laravel" nomi bilan, jonli
buyurtmalar, ovqatxonani ulash jarayoni, POS obunalari, qurilmalar, fiskal xatolar va tizim xatolarini ko'rsatmaydi.
Jamoa rollari va 2FA yo'q.

**Maqsad:** RIZQIM jamoasi butun platformani bitta joydan — **RIZQIM boshqaruv markazi**dan — boshqaradi:
muammoni birinchi bo'lib ko'radi, ovqatxonani bir kunda ulaydi, pulni xatosiz yuritadi, B2B mijozlar sog'lig'ini
kuzatadi.

## 2. Muvaffaqiyat o'lchovlari

| O'lchov | Maqsad |
|---|---|
| Muammoli buyurtmani (qabul qilinmagan, kechikkan) ko'rib, aralashish vaqti | 5 daqiqadan kam |
| Ovqatxonani arizadan birinchi buyurtmagacha ulash | 1–2 ish kuni |
| Ishlamay qolgan kassa yoki fiskal xato haqida bilish | 10 daqiqa ichida, ovqatxona aytmasidan oldin |
| Oy yopilishi (hisob-fakturalar, payoutlar, daromad hisoboti) | Bir kunda, qo'lda Excel'siz |
| Eski Filament panel kerak bo'lmay qolishi | 3-to'lqin oxirida |

## 3. Kimlar ishlatadi va kuni qanday o'tadi

| Xodim | Kundalik ishi markazda |
|---|---|
| **Operator** (navbatchi) | Ertalab "Bugun"ni ochadi: muammoli buyurtmalar, oflayn kassalar, fiskal xatolar. Kechikkan buyurtmada ovqatxonaga qo'ng'iroq qiladi yoki buyurtmani bekor qilib, pulni qaytarishni so'raydi |
| **Yordam** (support) | Shikoyatlar qutisi: mijoz va ovqatxona javobini ko'radi, hal qiladi; mijoz kartasidan buyurtmalar va qaytarishlar tarixini ko'radi |
| **Savdo / ulash** | Yangi arizalarni ko'rib chiqadi, hujjatlarni tekshiradi, menyu kiritishga yordam beradi, tarif biriktiradi, "ishga tushirish" ro'yxatini yopadi |
| **Moliya** | Payout so'rovlarini tasdiqlaydi, hisob-kitob xatolarini qayta urinadi, qarzdorlarni kuzatadi, oy oxirida hisob-fakturalar va daromad hisobotini chiqaradi |
| **Rahbar** (ega) | Bosh sahifadan: GMV, komissiya, MRR, yangi va ketgan ovqatxonalar, eng yaxshi va xavfdagi mijozlar |

## 4. Boshqaruv markazi bo'limlari

Hamma joyda: **umumiy qidiruv** (buyurtma kodi, telefon, ovqatxona nomi, STIR bo'yicha, `Ctrl+K`), har bir ro'yxatda
filtr va Excel'ga eksport, har bir amal sabab bilan jurnalga yoziladi.

### 4.1. Bugun
- Bugungi GMV, buyurtmalar soni, komissiya va obuna daromadi, kechagi kun va o'tgan hafta bilan solishtirma.
- **Diqqat talab qiladi** ro'yxati: qabul qilinmagan (5 daqiqadan ko'p), kechikkan, kuryersiz buyurtmalar; ish vaqtida
  oflayn bo'lgan kassalar; yiqilgan fiskal cheklar; hisob-kitob xatolari; yangi arizalar; muddati o'tgan
  hisob-fakturalar; tizim ogohlantirishlari. Har bir qator bir bosishda kerakli kartaga olib boradi.
- Viloyatlar bo'yicha qisqa ko'rinish (buyurtmalar, faol ovqatxonalar).

### 4.2. Buyurtmalar (marketpleys)
- Barcha ovqatxonalar bo'yicha jonli ro'yxat (real vaqtda yangilanadi), holat, kanal, viloyat, ovqatxona bo'yicha filtr.
- Kechikish chegaralari (SLA) serverda hisoblanadi va sozlanadi: qabul qilish, tayyorlash, yetkazish.
- Buyurtma kartasi: holatlar tarixi, taomlar, to'lov va qaytarishlar, kuryer, mijoz, fiskal chek.
- Aralashish: bekor qilish, pulni qaytarish (moliya tasdig'i bilan), holatni tuzatish, ovqatxonaga yoki mijozga
  xabar yuborish — har biri sabab bilan.
- Ovqatxona sifati ko'rsatkichlari: qabul qilish ulushi, o'rtacha tayyorlash vaqti, bekor qilish ulushi, reyting.

### 4.3. Ovqatxonalar — B2B mijozlar
- **Ulash jarayoni (pipeline):** ariza → tekshiruv (STIR, yuridik nom, bank hisobi, hujjatlar) → menyu (rasm va
  narxlar) → qurilmalarni ulash → o'qitish → sinov → **faol**. Har bir bosqichda mas'ul xodim va muddat; kechikkan
  arizalar ajratib ko'rsatiladi. Ovqatxona ariza qoldirishi uchun oddiy forma (Telegram bot yoki sayt orqali).
- **360° karta:** profil va filiallar, holat tarixi (`StoreStatusLog`), aloqa shaxslari, tarif va obuna, qurilmalar,
  fiskal holat, balans va qarz, payoutlar, buyurtmalar va sifat ko'rsatkichlari, shikoyatlar, jurnal.
- **Sog'liq bahosi:** buyurtmalar trendi, oflayn qurilmalar, fiskal xatolar, qarz, shikoyatlar, tizimga kirish
  chastotasi asosida "yaxshi / e'tibor kerak / ketish xavfi". Xavfdagi mijozlar ro'yxati.
- **CRM-lite:** izohlar, vazifalar va eslatmalar ("payshanba qo'ng'iroq qilish"), keyingi aloqa sanasi.
- Amallar: to'xtatish yoki faollashtirish (sabab bilan), komissiya va kredit limiti, ko'rsatish sozlamalari
  (premium, tavsiya etilgan), taklif havolasini yangilash, egasiga xabar yuborish. **Egasi nomidan kirish yo'q**
  (egasi qarori, 28.10.2026): yordam xodimi ovqatxona ma'lumotlarini 360° kartadan ko'radi, sozlashni egasi o'zi
  qiladi.
- Ommaviy e'lon: barcha yoki tanlangan ovqatxona egalariga Telegram xabar (texnik ishlar, yangilik).

### 4.4. POS va obunalar
- Obunalar: sinov, faol, qarzdor, bekor; sinov muddati 3 kunda tugaydiganlar; tarif almashtirish va bekor qilish.
- Hisob-fakturalar va to'lov holati; qarzni eslatish tartibi (dunning).
- **Qurilmalar:** onlayn yoki oflayn (oxirgi aloqa vaqti), ilova versiyasi, turi (kassa, KDS, ofitsiant), bekor qilish;
  eski versiyadagi qurilmalar ro'yxati.
- Smenalar va kassa farqlari, majburan yopilgan smenalar.
- Fiskal cheklar: yiqilgan va kutilayotganlar, sababi, qayta yuborish; provayder bo'yicha xatolar soni.
- Oflayn sinxron xatolari va ularni hal qilish.

### 4.5. Moliya
- GMV, komissiya (kanal bo'yicha), obuna daromadi, MRR va ARR grafiklari.
- Ovqatxonalar balansi va qarzi, kredit limitidan oshganlar.
- Payout so'rovlari navbati: tasdiqlash yoki rad etish.
- **Payoutdan ushlab qolish (netting, egasi qarori 28.10.2026):** RIZQIM yig'gan puldan (Payme, Click, keshbek)
  ovqatxonaga pul o'tkazishdan oldin uning qarzi (obuna hisob-fakturalari va naqd buyurtmalar komissiyasi) avval
  ushlab qolinadi, qolgani o'tkaziladi. Payout kartasida "Hisoblangan — Ushlab qolingan qarz — O'tkaziladi" qatorlari;
  har bir ushlash ledger'da alohida yozuv (qaysi hisob-faktura yoki komissiya yopildi). Hozir bu faqat keshbek
  to'lovlarida ishlaydi (`LedgerService::settleKeshbek`), Payme va Click pullari qarzni yopmaydi — 2-to'lqinda
  shu bo'shliq yopiladi.
- Balans to'ldirishlar (Click, Payme), hisob-kitob xatolari va qayta urinish.
- **Qo'lda tuzatish** (adjustment): sabab va hujjat bilan; belgilangan summadan katta tuzatishni ikkinchi xodim
  tasdiqlaydi.
- Oylik hisobot: Rizqim daromadi (komissiya + obuna), ovqatxonalar bo'yicha; Excel eksport.

### 4.6. Mijozlar va yordam
- Foydalanuvchilar: mijozlar, kuryerlar, ovqatxona xodimlari; mijoz kartasi (buyurtmalar, qaytarishlar, keshbek balansi,
  shikoyatlar); bloklash va sessiyalarni yopish.
- Shikoyatlar qutisi: holat, javob, hal qilish, tayyor javoblar.
- Shubhali holatlar: ko'p bekor qilish, promo-kodni suiiste'mol qilish.

### 4.7. Marketing
- Bannerlar va aksiyalar, promo-kodlar (ishlatilish statistikasi), kategoriyalar va ularning tartibi.

### 4.8. Monitoring
- Tizim holati: ma'lumotlar bazasi, Redis, navbatlar, scheduler, Reverb, oxirgi backup vaqti.
- Yiqilgan fon vazifalari (failed jobs): ko'rish, qayta urinish, o'chirish.
- Sentry'dagi oxirgi xatolar (havola bilan).
- **Ogohlantirish qoidalari:** xatolar ko'payishi, navbat to'planishi, ish vaqtida oflayn kassa, fiskal xatolar,
  hisob-kitob xatosi, backup eskirishi. Har biri markazda saqlanadi va Telegram guruhiga yuboriladi; kim ko'rgani va
  hal qilgani belgilanadi.

### 4.9. Tizim
- Sozlamalar (SMS, to'lov tizimlari — maxfiy kalitlar hech qachon to'liq ko'rsatilmaydi), viloyatlar va tumanlar,
  feature flag'lar, tariflar katalogi, SLA chegaralari.
- **Jamoa:** RIZQIM xodimlari, rollar, 2FA holati, oxirgi kirish.
- **Harakatlar jurnali:** kim, qachon, qaysi ovqatxona yoki buyurtmada nima qildi; o'zgartirib bo'lmaydi.

### 4.10. Integratsiyalar
- **Ulanishlar:** ovqatxona yoki filial bo'yicha tashqi tizim (iiko, Alipos, Yandex Eats, Uzum Tezkor, Partner API),
  yo'nalish, holat va sog'liq (oxirgi so'rov, oxirgi webhook, xatolar soni).
- **Hodisalar jurnali:** so'rov va javoblar, urinishlar, "qayta yuborish" tugmasi.
- **Menyu xaritasi:** tashqi taom ↔ RIZQIM taomi, bog'lanmagan taomlar, mos kelmay qolganlar haqida ogohlantirish.
- **Qotib qolgan buyurtmalar:** yuborilgan, lekin tashqi tizim qabul qilmagan — "Bugun → Diqqat talab qiladi"ga ham tushadi.
- **Hamkor ilovalar:** Partner API klientlari, sinov yoki ishchi kalitlar, tasdiqlash holati (kalitlar to'liq ko'rsatilmaydi).
- **Agregator solishtirishi:** Yandex va Uzum buyurtmalari va komissiyasini hisobotlar bilan solishtirish.

Bu bo'limning to'liq ishlashi 5-bosqichdagi ulagichlar bilan keladi; 4-bosqichda ekran joyi va ma'lumot modeli tayyorlanadi.

## 5. Jamoa rollari

| Rol | Ruxsat |
|---|---|
| Superadmin | Hammasi: jamoa, rollar, tizim sozlamalari |
| Operator | Bugun, buyurtmalar (aralashish bilan), ovqatxonalarni ko'rish, monitoring |
| Yordam | Shikoyatlar, foydalanuvchilar, buyurtmalarni ko'rish; pul qaytarishni so'raydi (tasdiqni moliya beradi) |
| Moliya | Moliya, obunalar va hisob-fakturalar, payout tasdiqlash, qo'lda tuzatish, hisobotlar |
| Savdo / ulash | Arizalar, ulash jarayoni, ovqatxona kartasi, tarif biriktirish |
| Faqat ko'rish | Hamma bo'limni ko'radi, o'zgartira olmaydi |

Telefon raqamlari va karta raqamlari ruxsatga qarab qisman yashiriladi; shaxsiy ma'lumotni kim ochib ko'rgani
jurnalga yoziladi.

## 6. Billing v2 (egasi tasdiqlagan narxlar, 24.10.2026)

| Tarif | Oylik | Yillik (12 oy = 10 oy) | Ichida |
|---|---|---|---|
| Start | 149 000 so'm | 1 490 000 | zal, xona puli, choy-non, smena, Z, RIZQIM buyurtmalari, 1 kassa |
| Asosiy | 249 000 so'm | 2 490 000 | + bron va bo'nak, nasiya, KDS, xizmat haqi; 2 terminal |
| Pro | 449 000 so'm | 4 490 000 | + filiallar hisoboti, keyin Yandex/Uzum buyurtmalari va banket; 3 terminal |

- Qo'shimcha kassa 59 000, qo'shimcha oshxona ekrani 29 000 so'm/oy; ofitsiant telefonlari cheklanmagan va bepul.
- Sinov 14 kun. Tarifda yo'q funksiya kassada va hamkor panelida "Asosiy tarifda bor" deb ko'rsatiladi.
- Komissiya kanal bo'yicha: RIZQIM kuryeri 15%, ovqatxona o'zi yetkazsa 10%, olib ketish 5%, zal (POS) 0%.
- O'tgan oyda 1 mln so'mdan ko'p komissiya to'lagan ovqatxonaga keyingi oy POS asosiy narxi 50% arzon.
- Obuna to'lovi: hisob-faktura ovqatxona qarziga qo'shiladi (hozirgidek) va RIZQIM yig'gan puldan avtomatik
  ushlab qolinadi (4.5, netting). Onlayn buyurtmasi yo'q, faqat POS ishlatadigan ovqatxona Click/Payme orqali
  balansini to'ldiradi yoki hisob raqamiga o'tkazadi (moliya qo'lda belgilaydi).
- To'lanmasa (taklif, egasi tasdiqlashi kerak — 12-bo'lim, 5): to'lov muddatidan keyin 1, 3 va 7-kunlari eslatma
  (Telegram va hamkor paneli), 7 kundan keyin yangi sozlamalar va tarifda yo'q funksiyalar yopiladi. **Kassa hech
  qachon to'lov olish va chek chiqarishdan to'xtatilmaydi.**
- Mavjud do'konlar yangi tariflarga xavfsiz o'tkaziladi (hech kimning ishi to'xtamaydi).

## 7. Texnik yechim

- **Alohida web ilova** `platform/admin` (Next.js, hamkor paneli bilan bir xil texnologiya), alohida manzilda
  (`admin.<domen>`). Umumiy UI komponentlari hamkor panelidan `packages/ts/ui` paketiga ajratiladi, ikkala ilova
  ham undan foydalanadi.
- **Admin API** `/api/v1/admin/*`: alohida token turi, jamoa ruxsatlari bo'yicha middleware, har bir o'zgartirish
  avtomatik jurnalga yoziladi, pul amallari idempotent. OpenAPI va TS klient avtomatik yaratiladi.
- **Kirish:** email va parol + 2FA (TOTP ilova), sessiya muddati, muhim amallar oldidan qayta tasdiq, ixtiyoriy IP
  cheklovi.
- **Tezkor ko'rsatkichlar:** bosh sahifa raqamlari xotirada hisoblanmaydi — kunlik va soatlik yig'ma jadvallar
  fon vazifasida yangilanadi.
- **Real vaqt:** `platform.ops` yopiq kanali (Reverb) orqali yangi buyurtma, muammo va ogohlantirishlar.
- **Ma'lumot joylashuvi:** 27.03.2026 dan (ORQ-1125) faqat biometrik, genetik va aloqa abonentlari ma'lumotlarini
  O'zbekistonda saqlash majburiy; ism, telefon, manzil va buyurtmalar chet elda ham saqlanishi mumkin, lekin buning
  uchun kerakli ro'yxatlar hali qabul qilinmagan va chetga uzatish rozilik talab qiladi. Rizqim soliq API'sidan
  foydalansa yoki "Marketpleys" sifatida ro'yxatdan o'tsa, server va IP O'zbekistonda bo'lishi shart. Shuning uchun
  production va staging'ni O'zbekistondagi serverda joylashtirish tavsiya qilinadi.
- **Integratsiyalar uchun asos hozirdan:** `integration_connections`, `integration_events`, `external_refs`
  jadvallari va buyurtmada `fiscalized_by` maydoni (chekni kim chiqargan: joy kassasi, to'lov tizimi yoki agregator)
  shu bosqichda loyihalanadi, keyingi bosqichdagi ulagichlar ularga quriladi
  (`docs/research/reports/POS va agregator integratsiyalari.md`).

## 8. Eski paneldan o'tish

Eski paneldagi har bir imkoniyat yangi markazda o'z joyiga ega bo'lmaguncha eski panel o'chirilmaydi:

| Eski panel | Yangi markazda |
|---|---|
| Restoranlar (ro'yxat, yaratish, tahrirlash, filiallar, balans to'ldirish, Telegram havola, egasi sifatida kirish) | Ovqatxonalar |
| Buyurtmalar (yashirin ro'yxat), do'kon panelidagi qaytarish | Buyurtmalar |
| Foydalanuvchilar, E'tirozlar | Mijozlar va yordam |
| Moliya dashboard, balans to'ldirish tarixi, yechib olish so'rovlari, Rizqim daromadi | Moliya |
| Tarif rejalari, do'kon obunalari, hisob-fakturalar | POS va obunalar |
| Aksiyalar/bannerlar, promo-kodlar, kategoriyalar | Marketing |
| Viloyatlar, to'lov tizimlari, tizim sozlamalari, feature flag'lar, harakatlar jurnali | Tizim |
| Qurilmalar | POS va obunalar |
| Dashboard vidjetlari | Bugun |

Ovqatxona egalari uchun eski Filament panel (`/admin`) ham bor — hamkor paneli uni to'liq almashtirgani tekshirilib,
egasi roziligi bilan u ham o'chiriladi.

## 9. To'lqinlar

Har to'lqin — **ishlatsa bo'ladigan tayyor qism** (backend va web birga), bitta PR; siz merge qilganingizdan keyin
keyingisi boshlanadi.

```
Oldindan   P4-01 Ma'lum xatolar                                                   — TUGADI (PR #14)

To'lqin 1  "Kundalik nazorat"                                                     — TUGADI (PR #15)
           P4-11 Admin kirish, 2FA, jamoa rollari va ruxsatlari, jurnal; admin API asosi
           P4-12 Admin ilova qobig'i (platform/admin), umumiy UI paketi, umumiy qidiruv
           P4-13 Bugun · P4-14 Buyurtmalar · P4-15 Monitoring

To'lqin 2  "B2B mijozlar va pul"
           P4-21 Billing v2          P4-22 Ovqatxonalar (ulash va 360° karta)
           P4-23 Moliya (netting)    P4-24 POS va obunalar

To'lqin 3  "To'liq almashtirish"
           P4-31 Mijozlar, yordam, marketing, tizim   P4-32 Moslik va e2e   P4-33 Qo'llanma, eski panellarni o'chirish
```

### 2-to'lqin: "B2B mijozlar va pul" — batafsil

Ish tartibi 1-to'lqindagidek: avval backend kelishuvi (endpointlar, ruxsatlar), keyin har bo'lim alohida agentda
(backend + web birga), oxirida ikki mustaqil review (xavfsizlik va pul to'g'riligi), so'ng bitta PR.

**P4-21 Billing v2** (backend asosi, qolganlar shunga tayanadi)
- Tarif katalogi 6-bo'limdagi narxlar bilan: Start, Asosiy, Pro; oylik va yillik davr (yillik = 10 oy narxi), 14 kun
  sinov; qo'shimcha kassa 59 000, qo'shimcha oshxona ekrani 29 000, ofitsiant telefonlari bepul.
- **Funksiya cheklovlari** (tarif → funksiyalar ro'yxati): backend har so'rovda tekshiradi, kassa, ofitsiant, KDS va
  hamkor paneli "Asosiy tarifda bor" deb ko'rsatadi. Kassa to'lov olish va chek chiqarish hech qachon yopilmaydi.
- Qurilmalar soni hisob-fakturaga kiradi (tarifdagi terminallardan ortig'i qo'shimcha narx bilan).
- Kanal komissiyasi: RIZQIM kuryeri 15%, o'z yetkazishi 10%, olib ketish 5%, zal 0% — buyurtma yaratilganda
  `commission_amount` shu qoidadan; ovqatxonaga alohida stavka berish mumkin (jurnal bilan).
- Chegirma qoidasi: o'tgan oyda 1 mln so'mdan ko'p komissiya → keyingi oy POS asosiy narxi 50% arzon (hisob-fakturada
  alohida qator).
- To'lanmagan hisob-faktura tartibi (dunning): 1, 3 va 7-kun eslatmalari (Telegram va hamkor paneli), keyin
  yangi sozlamalar yopiladi; to'langanda darhol ochiladi.
- Mavjud do'konlarni o'tkazish: hozirgi tarif → yangi tarif xaritasi, birinchi oy narxi o'zgarmaydi, buyruq
  (`--dry-run` bilan) va hisobot.

**P4-22 Ovqatxonalar**
- Ariza formasi (sayt va Telegram bot orqali): nom, format, shahar, egasi, telefon, STIR; ariza markazga tushadi.
- Ulash jarayoni (4.3): bosqichlar, mas'ul xodim, muddat, kechikkanlar ro'yxati; "ishga tushirish" ro'yxati
  (menyu, qurilmalar, fiskal, o'qitish, birinchi buyurtma).
- 360° karta: profil, filiallar, holat tarixi, aloqa shaxslari, tarif va obuna, qurilmalar, fiskal holat, balans va
  qarz, payoutlar, buyurtmalar va sifat, shikoyatlar, jurnal. Egasi nomidan kirish yo'q.
- Sog'liq bahosi va xavfdagi mijozlar ro'yxati; CRM-lite: izohlar, vazifalar, keyingi aloqa sanasi.
- Amallar (sabab, step-up va jurnal bilan): to'xtatish, faollashtirish, komissiya va kredit limiti, tarif
  biriktirish, egasiga xabar, ommaviy e'lon.
- Buyurtmalar filtrlari uchun ovqatxona va viloyat tanlash ro'yxati (1-to'lqinda ID bilan qolgan).

**P4-23 Moliya**
- Payout navbati: tasdiqlash yoki rad etish (`payouts.approve`, step-up, katta summada ikki kishi).
- **Netting** (4.5): payoutdan oldin qarz ushlanadi; Payme va Click pullari ham qarzni yopadi (hozir faqat keshbek).
  Har ushlash ledger'da alohida yozuv; payout kartasida "Hisoblangan — Ushlangan — O'tkaziladi".
- Balans to'ldirishlar (Click, Payme), hisob-kitob xatolari va qayta urinish.
- Qo'lda tuzatish: sabab va hujjat bilan; 12-bo'lim, 6-banddagi summadan kattasini ikkinchi xodim tasdiqlaydi.
- Oylik hisobot: RIZQIM daromadi (komissiya + obuna), ovqatxonalar bo'yicha; Excel eksport.
- 1-to'lqindan qolganlar: yetkazilgan buyurtma qisman qaytarilganda komissiya va hisob-kitobni ham qaytarish;
  Click qaytarish API'si (`payment/reversal`) bo'lsa — avtomatik qaytarish.

**P4-24 POS va obunalar**
- Obunalar ro'yxati (sinov, faol, qarzdor, bekor), sinovi tugayotganlar, tarif almashtirish va bekor qilish.
- Hisob-fakturalar va to'lov holati, eslatmalar tarixi.
- Qurilmalar: onlayn yoki oflayn, ilova versiyasi, turi; eski versiyadagilar ro'yxati; qurilmani bekor qilish.
- Smenalar: kassa farqlari, majburan yopilganlar.
- Fiskal cheklar: yiqilgan va kutilayotganlar, sababi, qayta yuborish; provayder bo'yicha xatolar.
- Oflayn sinxron xatolari va ularni hal qilish.

### 3-to'lqin: "To'liq almashtirish" — batafsil

**P4-31 Qolgan bo'limlar**
- Mijozlar va yordam (4.6): mijoz kartasi, bloklash va sessiyalarni yopish, shikoyatlar qutisi va tayyor javoblar,
  shubhali holatlar.
- Marketing (4.7): bannerlar va aksiyalar, promo-kodlar va ularning statistikasi, kategoriyalar tartibi.
- Tizim (4.9): sozlamalar (maxfiy kalitlar to'liq ko'rsatilmaydi), viloyatlar va tumanlar, feature flag'lar.
- Ko'rsatkichlar: MRR, churn, arizadan faol ovqatxonagacha konversiya; har ro'yxatda Excel eksport.
- Integratsiyalar bo'limining joyi va ma'lumot modeli (5-bosqichga tayyorgarlik).

**P4-32 Moslik va sinov**
- 8-bo'lim jadvali bo'yicha eski panelning har imkoniyati yangi markazda borligini tekshirish.
- Haqiqiy server bilan e2e (staging), skrinshotlar galereyasi, mustaqil review va tuzatishlar.

**P4-33 O'tish**
- Jamoa uchun qo'llanma (`docs/guides/boshqaruv-markazi.md` to'liq).
- Egasi roziligi bilan ikkala eski Filament panelni o'chirish; Mini App'dagi superadmin ekranlari va eski
  `/superadmin/*` JSON endpointlari olib tashlanadi (12-bo'lim, 1-band qaroriga ko'ra, ertaroq ham).

## 10. Tekshiruv

- Har bir admin API: har bir rol uchun ruxsat testlari, jurnalga yozilishi, pul amallarida idempotentlik va ikki
  kishilik tasdiq.
- Billing: oy oxiri va yillik hisob-fakturalar, chegirma qoidasi, tarif cheklovlari kassada, mavjud do'konlarni
  o'tkazish.
- Web: lint, typecheck, unit testlar, haqiqiy server bilan e2e, skrinshotlar galereyasi.
- Xavfsizlik: mustaqil review (2FA, sessiyalar, ruxsatlar, maxfiy kalitlar, shaxsiy ma'lumotlar).

## 11. Bu bosqichga kirmaydi (keyin)

- **5-bosqich: integratsiyalar** — boshqa POS'lar va agregatorlar bilan: [PHASE5_PLAN.md](PHASE5_PLAN.md).
- **6-bosqich: POS barcha formatlar uchun** (kafe, restoran, fast food, somsaxona, banket va boshqalar):
  [PHASE6_PLAN.md](PHASE6_PLAN.md).
- RIZQIM kuryerlari tarmog'i va dispetcherlik (15% stavka shundan keyin to'liq ishlaydi): [ROADMAP.md](ROADMAP.md).
- Ombor va taom tannarxi.
- Didox orqali elektron hisob-faktura (egasi kerak desa, 2-to'lqinga qo'shiladi).
- Rus va ingliz tarjimasi.

## 12. Egasining qarorlari va hali kerak bo'lganlar

**Qabul qilingan:**
- 2FA — telefon ilovasi (Google Authenticator) (27.10.2026). Bajarildi.
- Jamoa rollari — 5-bo'limdagi oltita, har rol ruxsatini superadmin o'zgartiradi (27.10.2026). Bajarildi.
- Egasi nomidan kirish — **kerak emas** (28.10.2026).
- Obuna to'lovi — payoutdan avtomatik ushlab qolinadi (netting) (28.10.2026). 2-to'lqinda (P4-23).

**Hali kerak:**
1. Superadminning Telegram / Mini App orqali kirishi (u yerda 2FA yo'q): yopish (tavsiya) yoki 3-to'lqingacha
   qoldirish.
2. Ogohlantirishlar uchun Telegram guruhi ("RIZQIM navbatchi") va uning ID'si (`TELEGRAM_OPS_CHAT_ID`).
3. Admin manzili (masalan `admin.rizqim.uz`) va server (O'zbekistondagi server tavsiya: soliq API va Marketpleys
   talabi).
4. Mavjud superadminlar uchun serverda `php artisan rizqim:admin:invite <email>` (birinchi kirish).
5. To'lanmagan obuna uchun muhlat (taklif: 7 kun, keyin yangi sozlamalar yopiladi) va qarz chegarasi: chegaradan
   oshgan qarz Mini App buyurtmalarini to'xtatadi — obuna qarzi ham shunga kirsinmi yoki faqat komissiya qarzi?
6. Qo'lda tuzatishda ikkinchi xodim tasdig'i qaysi summadan (taklif: 1 mln so'm).
7. Ovqatxonalar uchun Didox elektron hisob-faktura kerakmi (taklif: hozircha yo'q).
8. Yangi markaz tayyor bo'lgach, ikkala eski Filament panelni (`/superadmin` va `/admin`) o'chirishga rozilik.
9. Integratsiyalar bo'yicha muzokaralar: [PHASE5_PLAN.md](PHASE5_PLAN.md), "Egasidan kerak".
