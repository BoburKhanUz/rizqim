# 3-bosqich: POS (kassa) — ish tartibi

Holati: **tasdiqlangan (21.10.2026).** Birinchi format — **choyxona** (egasi qarori).
Ishlar shu hujjat bo'yicha boradi; o'zgarish bo'lsa, avval shu yerda yangilanadi.

Har bir ishning kodi bor (masalan, `P3-21`). Hisobotlar, commit xabarlari va PR'larda shu kod ishlatiladi.

Asos: [POS_SPEC.md](POS_SPEC.md) (umumiy yadro), tadqiqotlar
[Choyxona ishlashi va POS talablari](research/reports/Choyxona%20ishlashi%20va%20POS%20talablari.md) (8-bo'limdagi
R1–R32 talablari) va
[Ovqatlanish formatlari POS talablari](research/reports/Ovqatlanish%20formatlari%20POS%20talablari.md) (fiskal).

## Maqsad

1. **Kassa ilovasi** (`pos/apps/cashier`, Flutter: Android planshet, Sunmi turidagi terminal, Windows) — choyxona uchun
   to'liq ish joyi: zal xaritasi, xona hisobi, bron, to'lov, **fiskal chek**, smena va X/Z.
2. **Ofitsiant rejimi** (`pos/apps/waiter`, Android telefon) — o'z xonalari, buyurtmani bosqichma-bosqich qo'shish,
   oshxonaga yuborish.
3. **Fiskal qatlam boshidan.** POS soliq tizimiga ulangan virtual kassa talablariga mos quriladi (VM 943, 2-ilova;
   VM 393 22.10.2026 dan kuchda): sotuv, qaytarish, **bo'nak** (avans) va **kredit** (nasiya) cheklari. Fiskalsiz
   rejim faqat demo va sinov uchun.
4. **Mini App buyurtmalari ham fiskal chek oladi:** Payme va Click orqali to'langanda chekni to'lov tizimi restoran
   STIRi bilan chiqaradi.
5. Toshkentdagi bitta xonali choyxonada (8–20 xona) **pilotga tayyor** holat.

## Choyxona qanday ishlaydi (qisqacha, tadqiqotdan)

Bitta choyxonada uchta buyurtma rejimi birga yashaydi. POS hammasini bitta ekranda ko'taradi; har biri oxirida oddiy
`orders` qatori bo'ladi, shuning uchun oshxona, stop-list, hisobot va fiskal hamma rejim uchun bir xil.

| | **1. Bronsiz stol yoki tapchan** | **2. Xona (kabinka) sessiyasi** | **3. Oldindan buyurtma** |
|---|---|---|---|
| Nima | Porsiya osh qozondan, choy, non, salat, six | Xona puli + kg taomlar + bosqichma-bosqich buyurtma, 4–6 soat | N kg osh, qozon kabob, N kishilik set, lagan |
| Hisob | Qisqa; odatda xona pulisiz | Uzoq ochiq hisob, xona puli avtomatik, minimal summa, avans ayiriladi, ko'pincha teng bo'linadi yoki bitta odam to'laydi | Muddat bilan; avans bilan; kelganda xona hisobiga yoki olib ketishga o'tadi |
| Fiskal | Yopishda sotuv cheki | Bronda bo'nak cheki → yopishda to'liq sotuv cheki | Avansga bo'nak cheki → topshirishda sotuv cheki |

Osh uch xil sotiladi: choyxona oshi (kg guruch, lagan yoki porsiya), mijoz o'z masallig'ini olib kelsa — pishirish
xizmati, guruh o'zi pishirsa — qozon yoki oshxona ijarasi. Nasiyada: taom berilganda to'liq sotuv cheki, har qarz
to'lovida kredit cheki.

## Asosiy qaror: fiskal qatlam qanday quriladi

Fiskal yo'l (provayder orqali — B, yoki Rizqimning o'zi virtual kassa — A) hali tanlanmagan, lekin bu ishni
to'xtatmaydi. Kod ikkala yo'lga ham bir xil tayyorlanadi:

- **Bitta interfeys, almashtiriladigan drayver.** Backend'da `FiscalGateway`. Drayverlar: `SandboxFiscalGateway` —
  provayderga o'xshab ishlaydi (fiskal belgi, QR, xato va kechikishlar ham), pilotgacha shu; `NullFiscalGateway` —
  faqat demo; keyin **haqiqiy provayder drayveri** (P3-32) yoki Rizqimning o'z virtual kassasi.
- **To'rt chek turi to'liq oqim sifatida:** sotuv, qaytarish, bo'nak, kredit. To'lov shakli: naqd, karta, QR, aralash.
- **Fiskal ma'lumot boshidan yig'iladi:** taomda va xizmatda (xona puli, xizmat haqi, pishirish, ijara) MXIK kodi,
  birlik, QQS stavkasi; do'konda STIR va soliq rejimi (QQS 12%, 6% yoki aylanma soliq — QQSsiz).
- **Chek chiqmaguncha sotuv yopilmaydi.** Provayder javob bermasa buyurtma "fiskal kutmoqda" holatida qoladi, chek
  navbatda qayta yuboriladi, kutayotgan chek bor bo'lsa smena yopilmaydi.
- **Fiskal qism alohida va kam o'zgaradi** (virtual kassada fiskal qismdagi har o'zgarish qayta ekspertiza talab
  qiladi).
- **Ikki marta hisoblashdan himoya:** Rizqim chek chiqaradigan joyda eski kassa o'sha sotuvga chek chiqarmaydi; bu
  o'rnatish yo'riqnomasiga yoziladi.

## v1 doirasi

**v1 ga kiradi** (tadqiqotdagi R-raqamlar):

| Qism | Tarkibi |
|---|---|
| Zal | Zallar va zonalar (ichki, hovli, yozgi — mavsumda yopiladi); xona, tapchan, stol; xona holatlari (bo'sh, band, bron kutilmoqda, schyot chiqdi, N soatdan uzoq) (R1) |
| Xona hisobi | Uzoq ochiq hisob, har bosqich vaqti va ofitsianti bilan (R2); xonani ko'chirish va birlashtirish (R26) |
| Xona puli | Xona yoki xona turi bo'yicha, hafta ichi / dam olish / bayram tarifi, nol, kishi boshiga ixtiyoriy (R3); minimal summa va farq qatori (R4); xizmat haqi zal yoki zona bo'yicha, oldindan e'lon qilingan (R6) |
| Menyu | Kg taomlar va birlik yorlig'i ("kg (guruch)" / "kg (tayyor)"), sifat darajalari (R7); N kishilik set, lagan, kasr porsiya (R8); qo'shimchalar (R9); "xizmat" turidagi qatorlar: pishirish, qozon ijarasi (R10); choy va non tez tugmalari yoki kishi boshiga avtomatik qator (R11); qozondan porsiya osh va "tugadi" (R12) |
| Oshxona | Sexlar: osh va qozon, mangal, salat va bar, choy; marka va KDS (R13) |
| Bron | Bron daftari: xona × sana × vaqt, to'qnashuvni taqiqlash, telefon bo'yicha qidirish, tadbir turi, 4–6 soatlik slot, xonalarni banketga birlashtirish (R14); oldindan buyurtma muddat bilan, oshxonada vaqt navbati (R15) |
| Avans | Bo'nak cheki bilan olish, yopishda avtomatik ayirish, qaytarish (hujjat bilan) va ushlab qolish (menejer) (R16) |
| To'lov | Hisobni teng N yoki taom bo'yicha bo'lish, schyotda kishi boshiga summa, aralash to'lov (R17); naqd, karta (karta turi va tranzaksiya raqami), QR; P2P oddiy tur emas (R24) |
| Nasiya | Mijoz yoki guruh hisobi (telefon bo'yicha), limit, menejer tasdig'i, sotuv cheki → kredit cheklari, qarzdorlar ro'yxati (R18) |
| Ofitsiant | Telefonda: o'z xonalari, bosqich qo'shish, yuborish, schyot so'rash (R19) |
| Nazorat | PIN, menejer tasdig'i, harakatlar jurnali, egaga Telegram ogohlantirishlari: avans ushlab qolish, nasiya, xona puli yoki xizmat haqini olib tashlash, choy va nonsiz yopilgan xona, uzoq ochiq xona (R20) |
| Smena | Ochish, kassadan chiqim kategoriyalari (bozorlik, ish haqi), xodimga berilgan pul, X, Z (R21); ofitsiant bo'yicha sotuv, "kafolat + %", choy puli alohida (R22) |
| Fiskal | Sotuv, qaytarish, bo'nak, kredit; MXIK, birlik, QQS; soliq rejimi (R23) |
| Kanallar | Telegram va mobil buyurtmalar bitta navbatda: qabul qilish, rad etish, oshxona markasi |
| Uskunalar | 80/58 mm ESC/POS printer (LAN, USB Windows), Sunmi ichki printeri, printer orqali kassa qutisi |
| Internetsiz | Qisqa uzilishga chidaydigan cheklangan rejim, ochiq xona hisoblari qurilmada saqlanadi, 48 soatlik chek chegarasi ogohlantirishi (R25) |

**v1 ga kirmaydi** (keyingi kengaytmalar, pastda): doimiy guruh (R27), Mini App'da xona broni va avans to'lash (R28),
soatbay xona taymeri (R5), xarid ro'yxati va retsept me'yori (R29), alkogol toifasi (R30), iftorlik seti (R31), aylanma
soliq chegarasi hisoblagichi (R32), kombo, agregator API, mijoz ekrani, tarozi, iPad. Dinamik UzQR — provayderga bog'liq
(P3-32 da imkon bo'lsa).

## Umumiy ko'rinish

```
To'lqin 1    P3-11 Fiskal yadro: 4 chek turi, MXIK, QQS          ┐
(API va      P3-12 Zal, xona hisobi, xona puli, ko'chirish        │
 poydevor)   P3-13 Sozlamalar: sexlar, printerlar, tariflar       │
             P3-14 Mini App to'lovlarida fiskal chek              ├── parallel ─┐
             P3-15 Flutter POS paketlari va printerlar            │             │
             P3-16 Hujjatlar: POS_SPEC, ROADMAP                   │             │
             P3-17 Bron, oldindan buyurtma, avans, nasiya API     │             │
             P3-18 Schyot, bo'lish, to'lov, smena, X/Z            ┘             ▼
To'lqin 2    P3-21 Kassa: zal xaritasi va xona hisobi             ┐
(ilovalar)   P3-22 Kassa: to'lov, smena, bron, nasiya             │
             P3-25 Ofitsiant rejimi (telefon)                     ├── parallel ─┐
             P3-23 Web panel: MXIK, tariflar, bron taqvimi,       │             │
             │     nasiya, smenalar, cheklar                       │             │
             P3-24 KDS: mangal sexi, oldindan buyurtma navbati    ┘             ▼
To'lqin 3    P3-31 Internetsiz cheklangan rejim                   ┐
             P3-32 Haqiqiy fiskal drayver (kalitlar kelsa)        │
             P3-33 E2E testlar, "payshanba oqshomi" sinovi        ├ parallel ─┐
             P3-34 Qo'llanmalar                                   │            │
             P3-35 Mustaqil review va tuzatishlar ────────────────┘            │
                                                                                ▼
             P3-36 Staging va pilotga tayyorgarlik (haqiqiy uskunada)   (bosqich tugadi)
```

Har to'lqin bitta PR: to'lqin 1 — "P3 to'lqin 1: fiskal yadro va POS API", to'lqin 2 — "P3 to'lqin 2: kassa,
ofitsiant, web, KDS", to'lqin 3 — "P3 to'lqin 3: internetsiz rejim, fiskal drayver, review". Siz merge qilganingizdan
keyin keyingi to'lqin boshlanadi.

## To'lqin 1 — API va poydevor (8 ta parallel ish)

| Kod | Ish | Bajaruvchi | Qaysi fayllar | Tekshiruv |
|---|---|---|---|---|
| P3-11 | **Fiskal yadro.** Mahsulotga `kind` (taom yoki xizmat), MXIK, birlik, QQS stavkasi; variantga birlik yorlig'i; do'konga STIR va soliq rejimi. `fiscal_receipts` va `FiscalGateway` (sandbox va null drayverlar). Chek turlari: sotuv, qaytarish, bo'nak, kredit; to'lov shakli, karta turi, tranzaksiya raqami, komitent STIRi. Qayta yuborish navbati, "fiskal kutmoqda". Fiskal rejimda MXIK'siz taom yoki xizmat sotilmaydi | Opus | `App\Domain\Fiscal` (yangi), menyu va do'kon migratsiyalari | Chek summasi = buyurtma summasi (yaxlitlash, QQS, avans ofseti); qayta yuborish idempotent; drayver xatosida sotuv yo'qolmaydi; sandbox'da to'rt turning to'liq sikli |
| P3-12 | **Zal va xona hisobi.** Zallar va zonalar (mavsumiy), xona, tapchan, stol; xona holatlari. POS buyurtmasi: ochish (stol, xona yoki stolsiz), bosqichlar (`round_no`, kim qo'shdi), oshxonaga yuborish va "ushlab turish", o'chirish (sabab, "O'CHIRILDI" markasi), ko'chirish va birlashtirish. Xona puli (xona tarifi × kun turi) va minimal summa qatori avtomatik; xizmat haqi zal bo'yicha; choy-non kishi boshiga qoidasi. Qozondan ulush bo'yicha kamayish. Real-time: `order.items_changed`. Hammasi `Idempotency-Key` va `version` bilan | Opus | `App\Domain\Pos\Hall`, `Pos\Orders`, `Ordering` | Xona puli va minimal summa hisobi, ikki qurilma bir vaqtda (409), ruxsat va izolyatsiya testlari |
| P3-13 | **POS sozlamalari.** Sexlar (osh, mangal, salat va bar, choy) va yo'naltirish; printerlar; xona tariflari (`room_tariffs`); filial POS sozlamalari (rejim, biznes kun boshlanishi, "chop etuvchi" kassa, ekran qulfi, ogohlantirish chegaralari); `GET /pos/bootstrap`, `GET /catalog?channel=pos_*`. Boshqaruv API'si (web panel uchun) | Sonnet | `App\Domain\Pos\Setup`, `Management` | Har endpoint uchun ruxsat va izolyatsiya testlari |
| P3-14 | **Mini App to'lovlarida fiskal chek.** Payme (`CheckPerformTransaction` javobida `detail`: MXIK, qadoq kodi, QQS; `SetFiscalData` natijasi) va Click (fiskal ma'lumot). Restoran STIRi komitent sifatida. Natija `fiscal_receipts` ga | Opus | `Payment` webhooklari | Payme va Click hujjatidagi namunalar bilan testlar; eski to'lov oqimi buzilmaydi |
| P3-15 | **Flutter POS paketlari.** `pos_core` (zal, buyurtma, bron, smena va to'lov modellari), `pos_hardware` (ESC/POS: LAN, USB Windows, Sunmi; kassa qutisi; matnni rasm sifatida chop etish; sinov chop etish), `pos_ui` (sensor ekran komponentlari, zal xaritasi, raqam va kg klaviaturasi). Chek (fiskal QR bilan), schyot ("Bu fiskal chek emas"), marka, X/Z shablonlari | Opus | `pos/packages/*`, `packages/dart` | Unit testlar; shablonlar uchun "oltin" suratlar; LAN printer emulyatori bilan sinov |
| P3-16 | **Hujjatlar.** POS_SPEC: 0-bo'lim (fiskal qaror qayta ochiladi), 2, 4, 6 (choyxona birinchi format, uch rejim), 9, 10, 14-bo'limlar tadqiqot tavsiyalari bo'yicha; ROADMAP va ARCHITECTURE | Men | `docs/` | — |
| P3-17 | **Bron, oldindan buyurtma, avans, nasiya.** `bookings` (xona, vaqt, mehmonlar, tadbir turi, aloqa, manba, holat, minimal summa), `booking_items` (muddat bilan), `booking_payments` (bo'nak cheki bilan; olingan, ayirilgan, qaytarilgan, ushlab qolingan); to'qnashuv tekshiruvi; "Keldi" → buyurtma ochiladi, avans bog'lanadi. `customer_accounts` va `customer_account_entries` (nasiya: limit, qarz, to'lov — kredit cheki bilan) | Opus | `App\Domain\Pos\Booking`, `Pos\Accounts` | Bron to'qnashuvi, avans ofseti, qaytarish va ushlab qolish, nasiya limiti va kredit cheki testlari |
| P3-18 | **Schyot, to'lov va smena.** Schyot (qulflash, qayta chiqarish "NUSXA"), bo'lish (teng N, taom bo'yicha; kishi boshiga summa), to'lov (aralash, qaytim; avans ofseti; "nasiyaga yopish"), yopish fiskal chek orqali, bekor qilish, smena ichida qaytarish (qaytarish cheki). Smena: ochish, chiqim kategoriyalari va xodimga berilgan pul, X, Z (kupyuralar, farq), biznes kun. Menejer PIN tasdig'i. Real-time: `shift.changed` | Opus | `App\Domain\Pos\Billing`, `Pos\Shifts`, ruxsatlar (`pos.refund`) | Pul summalari, bo'lishdagi qoldiq, aralash to'lov, qaytarish va Z testlari; ochiq yoki fiskal kutayotgan buyurtma bilan smena yopilmaydi |
| P3-19 | **Integratsiya tuzatishlari** (to'lqin oxirida qo'shildi): nasiya chekda `credit` belgisi bilan; fiskal rejim o'chiq do'konda MXIK'siz sotuv; butun buyurtmani bekor qilish; ochiq buyurtmalar ro'yxati va "berildi"; ofitsiant faqat o'z buyurtmalari bilan; egaga ogohlantirishlar (o'chirish, olib tashlash, bekor qilish, uzoq ochiq xona); naqd avans qaytarilishi X/Z da; haqiqiy kod bilan "choyxona oqshomi" e2e testi | Opus | `Domain/Fiscal`, `Domain/Pos/*` | To'liq suite, e2e fiskal yoqiq va o'chiq |

**To'qnashuv xavfi va yechim:**
- Backend'da oltita ish bor (P3-11, 12, 13, 14, 17, 18). Har biri o'z papkasida. Umumiy fayllar (`routes/api.php`,
  ruxsatlar ro'yxati, `Order` modeli, migratsiyalar tartibi) — har biri o'z bloki bilan.
- **Birinchi kuni** kelishiladigan narsalar: `orders` va `order_items` dagi yangi ustunlar (P3-12 egasi), `FiscalGateway`
  interfeysi (P3-11 egasi), avans va nasiya to'lov turlari (P3-17 va P3-18 kelishadi). Qolganlar sinovda soxta
  amalga oshirish bilan ishlaydi.
- P3-15 API'ga kelishilgan OpenAPI orqali bog'lanadi; Dart klient to'lqin oxirida qayta yaratiladi.

## To'lqin 2 — ilovalar (5 ta parallel ish)

| Kod | Ish | Bajaruvchi | Qaysi fayllar | Tekshiruv |
|---|---|---|---|---|
| P3-20 | **Umumiy ma'lumot qatlami va ilova qobiqlari** (to'lqin boshida qo'shildi): `pos/packages/pos_data` (bootstrap, menyu, zal, buyurtma, schyot, smena, bron, nasiya repozitoriylari; `pos-branch` real-time), kassa va ofitsiant ilovalarining qobig'i (ulash, PIN, ekran qulfi, menejer tasdig'i, navigatsiya). P3-21, P3-22 va P3-25 shu ustiga quriladi; P3-23 va P3-24 u bilan parallel | Opus | `pos/packages/pos_data`, `pos/apps/cashier`, `pos/apps/waiter` | Repozitoriy testlari, qobiq widget testlari, Linux suratlari |
| P3-21 | **Kassa: zal xaritasi va xona hisobi.** Ulash va PIN, ekran qulfi, menejer tasdig'i oynasi. Asosiy ekran — zal xaritasi (xona holatlari, bron belgisi, ochiq soatlar). Buyurtma muharriri: kategoriyalar, katta tugmalar, kg va kasr klaviaturasi, qozon qoldig'i, qo'shimchalar, choy-non tez tugmalari, izoh, "hozir / ushlab turish". Bosqichlar ro'yxati, ko'chirish va birlashtirish. Tez kassa (bronsiz stol) ikkinchi rejim. Ilovaning umumiy qobig'i shu ishda | Opus | `pos/apps/cashier` | Widget testlari, Linux'da ishga tushirib suratlar, bosqich qo'shish vaqti o'lchanadi |
| P3-22 | **Kassa: to'lov, smena, bron, nasiya.** Schyot va bo'lish, to'lov (aralash, qaytim, avans ofseti, nasiyaga yopish), fiskal chek chop etish, qaytarish. Bron taqvimi (xona × vaqt), bron yaratish, avans olish, "Keldi". Nasiya hisoblari va qarz to'lovi. Smena, chiqimlar, X va Z. Barcha kanallar navbati, "fiskal kutmoqda" ro'yxati, printer sozlamalari | Opus | `pos/apps/cashier` (o'z ekranlari) | Widget testlari, suratlar |
| P3-25 | **Ofitsiant rejimi.** Telefonda PIN, o'z xonalari, bosqich qo'shish, yuborish, schyot so'rash; offlaynda faqat ko'rish | Sonnet | `pos/apps/waiter` | Widget testlari, suratlar |
| P3-23 | **Web panel: POS sozlamalari.** MXIK, birlik, QQS va "xizmat" turi (ommaviy tahrir bilan), STIR va soliq rejimi, zallar va xonalar, xona tariflari, xizmat haqi zal bo'yicha, sexlar, printerlar, bron taqvimi, nasiya hisoblari va qarzdorlar, smenalar va Z, fiskal cheklar va xatolar. Mini App'da: Z xulosasi va qarzdorlar | Sonnet | `partner/web`, `customer/miniapp` (xodim) | Komponent testlari, e2e |
| P3-24 | **KDS: sexlar.** Sex tanlash, mangal sexi (six soni va turi), oldindan buyurtmalar vaqt navbati, "O'CHIRILDI" belgisi, qozon qoldig'i paneli | Sonnet | `pos/apps/kds` | Flutter testlari, suratlar |
| P3-26 | **To'lqin 2 da topilgan backend kamchiliklari** (to'lqin oxirida qo'shildi): kassir chekida haqiqiy fiskal belgi va QR, buyurtma avansi, onlayn buyurtmalarni kassada qabul qilish yoki rad etish, bugun yopilganlar ro'yxati, ofitsiantning "schyot so'rash"i, zalda tayyor taomlar soni, oldindan pishirilgan taomni "tayyor" deyish, choy-non tez tugmalari, bron egasining ismi, bitta bayram ro'yxati, faqat fiskal maydonlarni yangilash, API spetsifikatsiyasining aniqligi; so'ng ilovalardagi aylanma yechimlar olib tashlanadi | Opus | `backend`, ilovalar | Har tuzatish uchun test, barcha suite'lar yashil |

**To'qnashuv xavfi va yechim:**
- P3-21 va P3-22 bitta ilovada. Qobiq, navigatsiya va umumiy holat P3-21 ga tegishli va birinchi tayyorlanadi; P3-22
  faqat o'z ekran papkalarida ishlaydi.
- P3-25 alohida ilova, `pos_core` va `pos_ui` ni ishlatadi, ularni o'zgartirmaydi (kerak bo'lsa P3-21 orqali).
- P3-23 yagona TypeScript ishi, P3-24 yagona KDS ishi.

## To'lqin 3 — internetsiz rejim, fiskal drayver, sinov, review

| Kod | Ish | Bajaruvchi | Tekshiruv |
|---|---|---|---|
| P3-31 | **Internetsiz cheklangan rejim** (POS_SPEC 5-bo'lim): `pos_sync` (SQLite navbat, `op_id`, lokal raqam), `POST /pos/sync`, to'qnashuvlar va "Sinxronlash xatolari", ochiq xona hisoblari qurilmada, 24 soatlik menyu nusxasi. Fiskal qismi: offlayn cheklar navbatda, 48 soatlik chegaradan ancha oldin ogohlantirish | Opus | Uzilish va qayta ulanish sinovlari; elektr o'chishini simulyatsiya; dublikat yo'q; pul yo'qolmaydi |
| P3-32 | **Haqiqiy fiskal drayver.** Siz tanlagan provayder API'si bo'yicha, **bo'nak va kredit cheklari bilan**; imkon bo'lsa dinamik UzQR. Hujjat va sinov kalitlari kelmasa — sandbox drayverda qoladi va pilot real tushum bilan boshlanmaydi | Opus | Provayderning sinov muhitida to'rt chek turi |
| P3-33 | **E2E va "payshanba oqshomi" sinovi:** bron → avans (bo'nak) → keldi → 3+ bosqich → bo'lingan schyot → sotuv cheki avans ofseti bilan → Z; nasiya → kredit cheki; 10 ta xona bir vaqtda 5 soat ochiq, 2 ta ofitsiant telefonda; Telegram buyurtmasi kassada | Sonnet | CI'da o'tadi, 3 marta ketma-ket barqaror; chek summalari va Z to'g'ri |
| P3-34 | **Qo'llanmalar** (o'zbekcha, oddiy tilda): kassir (xona, bron, avans, to'lov, nasiya, Z), ofitsiant, ega (MXIK va tariflar, sexlar va printerlar, fiskal provayder, ikki marta hisoblamaslik) | Sonnet | Siz o'qib tasdiqlaysiz |
| P3-35 | **Mustaqil review** (backend va fiskal, kassa va ofitsiant, web va KDS) va topilmalarni tuzatish | 3 × Opus + tuzatuvchilar | Topilmalar yopilgan, CI yashil |
| P3-36 | **Staging va pilotga tayyorgarlik:** staging'da to'liq sikl, haqiqiy planshet va telefonlarda, LAN printerda sinov; pilot choyxonada o'rnatish va birinchi payshanba oqshomi kuzatuvi | Men + siz | Tekshiruv ro'yxati to'liq |

## Keyingi kengaytmalar (3-bosqichdan keyin)

Pilot natijasiga qarab, har biri alohida reja bilan:

1. **Choyxona v1.1:** doimiy guruhlar (jo'ra boshi, odatiy kun, xona va menyu, takrorlanuvchi bron); Mini App'da xona
   broni, menyuni oldindan tanlash va avansni Payme yoki Click bilan to'lash; nasiya eslatmalari; soatbay xona taymeri;
   ertangi xarid ro'yxati (bronlardagi osh → guruch, go'sht).
2. **Osh markazi, somsaxona, tamaddixona:** tez kassa yadrosi tayyor bo'ladi, navbat raqami va mijoz ekrani qo'shiladi.
3. **Restoran va kafe** (kabobxona va baliqxona bilan): kurslar, tarozi, mijoz ekrani.
4. **Fast food:** kombo, Yandex Eats va Uzum Tezkor API.
5. **Stolovaya va qahvaxona,** keyin **to'yxona va banket**.
6. **Rizqim o'z virtual kassasi (A yo'li),** agar shuni tanlasangiz.

## Har bir ishning bajarilish tartibi

1–2-bosqichlardagidek: agentga aniq topshiriq → agent ishlaydi → men o'zim tekshiraman (testlar, PHPStan, lint, build,
e2e, suratlar) → commit (ish kodi bilan) → to'lqin oxirida PR, yashil CI, sizga qisqa hisobot → siz merge qilasiz.

## Sizdan kerak bo'ladiganlar

| Nima | Qachongacha | Kerak bo'lmasa nima bo'ladi |
|---|---|---|
| Ushbu rejani tasdiqlash yoki o'zgartirish | To'lqin 1 dan oldin | Boshlanmaydi |
| 2–3 choyxona egasi bilan suhbat (tadqiqotdagi savolnoma bo'yicha) va bitta payshanba oqshomi kuzatuvi | To'lqin 2 dan oldin | Ekranlar taxminlar bo'yicha quriladi, pilotda ko'p tuzatish chiqadi |
| Pilot choyxona (Toshkent, 8–20 xona), uning soliq rejimi va hozirgi kassasi | To'lqin 3 | Pilot keyinga suriladi |
| Buxgalter bilan tekshirish: xizmat uchun MXIK kodlari, avansni ushlab qolish cheki, aylanma soliq chegarasi (tadqiqotdagi T1–T9) | To'lqin 2 | Sandbox'da ishlaydi, real pilotda xato chek xavfi |
| Fiskal provayder tanlovi — **bo'nak va kredit cheklarini qo'llaydigani**; API hujjati va sinov kalitlari | To'lqin 3 | Sandbox drayverda qoladi, real tushum bilan pilot bo'lmaydi |
| A yo'li (Rizqim o'zi virtual kassa) bo'yicha qaror | Keyinroq | B yo'li bilan ishlanadi |
| Sinov uchun Android planshet yoki Sunmi, 1–2 ta Android telefon (ofitsiant) va bitta LAN printer | To'lqin 2 oxiri | Faqat emulyator va Linux'da sinaladi |
| Staging server va domen (P1-35, P2-34 bilan birga) | To'lqin 3 | Staging tekshiruvlari keyinga qoladi |
| PR'larni ko'rib chiqish va merge | Har to'lqin oxirida | Keyingi to'lqin boshlanmaydi |

## Bosqich tugaganini qanday bilamiz

- **Payshanba oqshomi sinovi o'tadi:** 10 ta xona bir vaqtda 5 soat ochiq, har birida 4+ bosqich, 2 ta ofitsiant
  telefonda ishlaydi, kamida bitta bron avans bilan va bitta nasiya bor; hamma cheklar va Z-hisobot to'g'ri.
- Bron va avans: bo'nak cheki chiqadi, yopishda avans avtomatik ayiriladi, sotuv cheki to'liq summaga chiqadi.
- Nasiya: sotuv cheki chiqadi, qarz to'langanda kredit cheki chiqadi, qarzdorlar ro'yxati to'g'ri.
- Qozon tugaganda kassada, KDS'da va Telegram menyusida bir vaqtda "tugadi" bo'ladi.
- Payme yoki Click bilan to'langan Mini App buyurtmasi fiskal chek oladi.
- Internet qisqa uzilganda sotuv to'xtamaydi, qaytgach hech narsa yo'qolmaydi va takrorlanmaydi.
- Barcha testlar va e2e o'tadi, CI yashil, PR'lar merge qilingan.
- Staging'da va haqiqiy uskunada ishlaydi; pilot choyxona tanlangan.
