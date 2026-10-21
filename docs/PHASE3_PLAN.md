# 3-bosqich: POS (kassa) — ish tartibi

Holati: **qoralama, egasi ko'rib chiqishi uchun (21.10.2026).** Tasdiqlangach "tasdiqlangan" deb belgilanadi va ishlar shu
hujjat bo'yicha boradi; o'zgarish bo'lsa, avval shu yerda yangilanadi.

Har bir ishning kodi bor (masalan, `P3-21`). Hisobotlar, commit xabarlari va PR'larda shu kod ishlatiladi.

Asos: [POS_SPEC.md](POS_SPEC.md) (umumiy yadro va format profillari) va tadqiqot
[Ovqatlanish formatlari POS talablari](research/reports/Ovqatlanish%20formatlari%20POS%20talablari.md).

## Maqsad

1. **Kassa ilovasi** (`pos/apps/cashier`, Flutter: Android planshet, Sunmi turidagi terminal, Windows) — birinchi
   format uchun to'liq ish joyi: smena, buyurtma, to'lov, **fiskal chek**, oshxonaga marka, X/Z hisobot.
2. **Fiskal qatlam boshidan.** POS soliq tizimiga ulangan virtual kassa talablariga mos quriladi (VM 943, 2-ilova;
   VM 393 22.10.2026 dan kuchda). Fiskalsiz rejim faqat demo va sinov uchun qoladi.
3. **Mini App buyurtmalari ham fiskal chek oladi:** Payme va Click orqali to'langanda chekni to'lov tizimi restoran
   STIRi bilan chiqaradi.
4. Bitta osh markazida **pilotga tayyor** holat.

## Asosiy qaror: fiskal qatlam qanday quriladi

Fiskal yo'l (provayder orqali — B, yoki Rizqimning o'zi virtual kassa — A) hali tanlanmagan, lekin bu ishni
to'xtatmaydi. Kod ikkala yo'lga ham bir xil tayyorlanadi:

- **Bitta interfeys, almashtiriladigan drayver.** Backend'da `FiscalGateway` (chekni ro'yxatdan o'tkazish, qaytarish,
  holatni so'rash). Drayverlar: `SandboxFiscalGateway` — provayderga o'xshab ishlaydi (fiskal belgi, QR havola, xato va
  kechikishlarni ham simulyatsiya qiladi), sinov va pilotgacha shu; `NullFiscalGateway` — faqat demo; keyin
  **haqiqiy provayder drayveri** (P3-32) yoki Rizqimning o'z virtual kassasi. Drayver do'kon sozlamasida tanlanadi.
- **Fiskal ma'lumot boshidan yig'iladi:** taomda MXIK (IKPU) kodi, o'lchov birligi yoki qadoq kodi, QQS stavkasi;
  do'konda STIR va soliq rejimi; har bir chek `fiscal_receipts` jadvalida (turi, to'lov shakli, fiskal belgi, QR,
  holat, xato, qayta urinishlar).
- **Chek chiqmaguncha sotuv yopilmaydi.** Provayder javob bermasa buyurtma "fiskal kutmoqda" holatida qoladi, chek
  navbatda qayta yuboriladi, kutayotgan chek bor bo'lsa smena yopilmaydi.
- **Fiskal qism alohida va kam o'zgaradi.** Virtual kassada fiskal qismdagi har o'zgarish qayta ekspertiza talab
  qiladi, shuning uchun menyu, ekran va hisobot o'zgarishlari unga tegmaydi.
- **Ikki marta hisoblashdan himoya:** Rizqim chek chiqaradigan joyda eski kassa o'sha sotuvga chek chiqarmaydi; bu
  o'rnatish yo'riqnomasiga yoziladi.

## Birinchi format va v1 doirasi

**Taklif: osh markazi (va lag'monxona), shu yadroning o'zida tezkor kassa formatlari (somsaxona, tamaddixona).**
Sabablari (POS_SPEC 9-bo'lim va tadqiqot): qozon va porsiya ulushi backend'da tayyor; oqim oddiy (kassada oldindan
to'lov, bitta buyurtma — bitta sotuv cheki); fiskallashtirish eng oson; pilot arzon (1 kassa + 1 printer).

**v1 ga kiradi:**

| Qism | Tarkibi |
|---|---|
| Kassa rejimi | Tez kassa (stolsiz yoki oddiy stol raqami), oldindan to'lov, navbat raqami (biznes kun bo'yicha) |
| Sotuv birliklari | Porsiya ulushi (1 / 0,75 / 0,7 / 0,5 / 2), qozondan avtomatik kamayish va "tugadi", kg bo'yicha olib ketish, dona (non, choynak, somsa), qo'shimchalar modifikator sifatida (qazi, bedana tuxumi) |
| To'lov | Naqd (qaytim bilan), karta (alohida bank terminali, karta turi va tranzaksiya raqami yoziladi), QR (vaqtincha qo'lda tasdiq), aralash to'lov |
| Fiskal | Sotuv va qaytarish cheklari, to'lov shakli (naqd, karta, QR, aralash), chekda fiskal belgi va QR |
| Smena | Ochish, kirim/chiqim, X-hisobot, Z (kupyura bo'yicha sanash, farq), biznes kun (erta boshlanishi mumkin, masalan 04:00) |
| Kanallar | Telegram va mobil buyurtmalar bitta navbatda: qabul qilish, rad etish, oshxona markasi |
| Oshxona | Sexlar (osh tarqatish, salat, ichimlik), taom yoki kategoriya bo'yicha yo'naltirish, printer markasi va KDS, "O'CHIRILDI" markasi |
| Nazorat | Menejer PIN'i bilan tasdiq, harakatlar jurnali, egaga Telegram ogohlantirishlari va Z xulosasi |
| Uskunalar | 80/58 mm ESC/POS printer (LAN, USB Windows), Sunmi ichki printeri, printer orqali kassa qutisi |
| Internetsiz | Qisqa uzilishga chidaydigan cheklangan rejim (POS_SPEC 5-bo'lim) + fiskal chek navbati va 48 soatlik chegara ogohlantirishi |

**v1 ga kirmaydi (keyingi kengaytmalar, pastda):** zal xaritasi va xonalar, ofitsiant ilovasi, hisobni bo'lish,
xizmat haqi va joy narxi bilan uzoq ochiq hisob, bo'nak va kredit (nasiya) cheklari, bron, kombo konstruktori,
agregator API, mijoz ekrani, tarozi, dinamik UzQR (provayderga bog'liq — P3-32 da imkon bo'lsa), iPad.

## Umumiy ko'rinish

```
To'lqin 1    P3-11 Fiskal yadro (backend)                ┐
(API va      P3-12 POS buyurtma, to'lov, smena API       │
 poydevor)   P3-13 POS sozlamalari: sexlar, printerlar,  ├── parallel ─┐
             │     filial sozlamalari, bootstrap          │             │
             P3-14 Mini App to'lovlarida fiskal chek     │             │
             P3-15 Flutter POS paketlari va printerlar   │             │
             P3-16 Hujjatlar: POS_SPEC, ROADMAP          ┘             ▼
To'lqin 2    P3-21 Kassa: sotuv oqimi                    ┐
(ilovalar)   P3-22 Kassa: smena, navbat, hisobotlar      ├── parallel ─┐
             P3-23 Web panel: MXIK, sexlar, printerlar,  │             │
             │     smenalar, cheklar                      │             │
             P3-24 KDS: sexlar, qozon paneli             ┘             ▼
To'lqin 3    P3-31 Internetsiz cheklangan rejim          ┐
             P3-32 Haqiqiy fiskal drayver (kalitlar      │
             │     kelsa)                                 ├ parallel ─┐
             P3-33 E2E testlar                           │            │
             P3-34 Qo'llanmalar                          │            │
             P3-35 Mustaqil review va tuzatishlar ───────┘            │
                                                                       ▼
             P3-36 Staging va pilotga tayyorgarlik (haqiqiy uskunada)   (bosqich tugadi)
```

Har to'lqin bitta PR: to'lqin 1 — "P3 to'lqin 1: fiskal yadro va POS API", to'lqin 2 — "P3 to'lqin 2: kassa
ilovasi", to'lqin 3 — "P3 to'lqin 3: internetsiz rejim, fiskal drayver, review". Siz merge qilganingizdan keyin
keyingi to'lqin boshlanadi.

## To'lqin 1 — API va poydevor (6 ta parallel ish)

| Kod | Ish | Bajaruvchi | Qaysi fayllar | Tekshiruv |
|---|---|---|---|---|
| P3-11 | **Fiskal yadro.** Taomga (va variantga) MXIK kodi, o'lchov birligi yoki qadoq kodi, QQS stavkasi (12%, 6%, QQSsiz); do'konga STIR va soliq rejimi. `fiscal_receipts` jadvali va `FiscalGateway` (sandbox va null drayverlar). Chek turlari (sotuv, qaytarish; bo'nak va kredit uchun joy), to'lov shakli (naqd, karta, QR, aralash), karta turi, tranzaksiya raqami, komitent STIRi. Qayta yuborish navbati, "fiskal kutmoqda" holati. Fiskal rejimda MXIK'siz taomni sotib bo'lmaydi | Opus | `App\Domain\Fiscal` (yangi), menyu va do'kon migratsiyalari | Chek summasi = buyurtma summasi (yaxlitlash, QQS), qayta yuborish idempotent, drayver xatosida sotuv yo'qolmaydi, sandbox'da to'liq sikl |
| P3-12 | **POS buyurtma, to'lov va smena API.** `/pos/*`: buyurtma ochish (`pos_takeaway`, zalda stolsiz), qator qo'shish va o'chirish, oshxonaga yuborish, to'lov (aralash, qaytim), yopish (fiskal chek orqali), bekor qilish, smena ichida qaytarish (qaytarish cheki bilan). Smena: ochish, kirim/chiqim, X, Z (kupyuralar, farq), biznes kun, navbat raqami. Menejer PIN tasdig'i (bir martalik, 60 soniya). Hammasi `Idempotency-Key` va buyurtma `version` bilan, filial va terminal bo'yicha ajratilgan. Real-time: `order.items_changed`, `shift.changed`. Qozondan ulush bo'yicha kamayish va "tugadi" Telegram menyusiga ham | Opus | `App\Domain\Pos` (yangi), `Ordering`, ruxsatlar (`pos.refund` qo'shiladi) | Pul summalari, qaytarish va smena hisobi uchun testlar; ikki terminal bir vaqtda (409); ruxsat va izolyatsiya; ochiq yoki fiskal kutayotgan buyurtma bilan smena yopilmaydi |
| P3-13 | **POS sozlamalari.** Sexlar va yo'naltirish (kategoriya yoki taom bo'yicha), printerlar (maqsadi, ulanish, manzil, qog'oz eni, qaysi terminal), filial POS sozlamalari (rejim, biznes kun boshlanishi, navbat raqami, "chop etuvchi" kassa, ekran qulfi, ogohlantirish chegaralari), `GET /pos/bootstrap`, `GET /catalog?channel=pos_*`. Boshqaruv API'si (web panel uchun) | Sonnet | `App\Domain\Pos\Setup`, `Management` | Har endpoint uchun ruxsat va izolyatsiya testlari |
| P3-14 | **Mini App to'lovlarida fiskal chek.** Payme (`CheckPerformTransaction` javobida `detail`: MXIK, qadoq kodi, QQS; `SetFiscalData` natijasini saqlash) va Click (fiskal ma'lumot yuborish). Chekda restoran STIRi komitent sifatida. Natija `fiscal_receipts` ga yoziladi. MXIK'i yo'q taom bo'lsa ogohlantirish | Opus | `Payment` webhooklari, P3-11 ning jadvali | Webhook testlari Payme va Click hujjatidagi namunalar bilan; eski to'lov oqimi buzilmaydi |
| P3-15 | **Flutter POS paketlari.** `pos_core` (buyurtma, smena va to'lov modellari, P3-12 API ustida), `pos_hardware` (ESC/POS: LAN, USB Windows, Sunmi ichki printeri; kassa qutisi; matnni rasm sifatida chop etish; sinov chop etish), `pos_ui` (sensor ekran komponentlari: katta tugmalar, raqam va kg klaviaturasi). Chek, marka va X/Z shablonlari (fiskal QR bilan) | Opus | `pos/packages/*`, `packages/dart` | Unit testlar; shablonlar uchun "oltin" suratlar (golden); LAN printer emulyatori bilan sinov |
| P3-16 | **Hujjatlar.** POS_SPEC: 0-bo'lim (fiskal qaror qayta ochiladi), 2.9, 4, 6, 9, 10, 14-bo'limlar tadqiqot tavsiyalari bo'yicha; ROADMAP va ARCHITECTURE | Men | `docs/` | — |

**To'qnashuv xavfi va yechim:**
- P3-11, P3-12, P3-13, P3-14 backend'da. Har biri o'z papkasida (`Fiscal`, `Pos`, `Pos\Setup`, `Payment`). Umumiy
  fayllar (`routes/api.php`, ruxsatlar ro'yxati, `Order` modeli) — har biri o'z bloki bilan. P3-12 yopish oqimida
  P3-11 ning interfeysini chaqiradi: interfeys birinchi kuni kelishiladi, P3-12 sinovda soxta drayver ishlatadi.
- P3-15 API'ga kelishilgan OpenAPI orqali bog'lanadi; Dart klient to'lqin oxirida qayta yaratiladi.

## To'lqin 2 — ilovalar (4 ta parallel ish)

| Kod | Ish | Bajaruvchi | Qaysi fayllar | Tekshiruv |
|---|---|---|---|---|
| P3-21 | **Kassa: sotuv oqimi.** Ulash va PIN (KDS'dagi kabi), ekran qulfi, menejer tasdig'i oynasi. Asosiy ekran: tepada qozon hisoblagichlari, kategoriyalar, katta tugmalar, porsiya ulushi va kg klaviaturasi, qo'shimchalar, izoh. To'lov: naqd va qaytim, karta (tranzaksiya raqami), QR, aralash. Fiskal chek va marka chop etish, navbat raqami talonchasi. Qaytarish. Ilovaning umumiy qobig'i (navigatsiya, mavzu) shu ishda | Opus | `pos/apps/cashier` | Widget testlari, Linux'da ishga tushirib suratlar, bitta buyurtma vaqti o'lchanadi (maqsad: 30 soniyagacha) |
| P3-22 | **Kassa: smena, navbat, hisobotlar.** Smenani ochish, kirim/chiqim, X va Z (kupyuralar, farq, chop etish). Barcha kanallar navbati: Telegram buyurtmasini qabul qilish yoki rad etish, ovoz. "Fiskal kutmoqda" cheklar ro'yxati va qayta yuborish. Printer sozlamalari va sinov chop etish | Sonnet | `pos/apps/cashier` (o'z ekranlari) | Widget testlari, suratlar |
| P3-23 | **Web panel: POS sozlamalari.** Taomlarga MXIK, qadoq kodi va QQS kiritish (ommaviy tahrir bilan), do'kon STIRi va soliq rejimi, sexlar va yo'naltirish, printerlar, filial POS sozlamalari, smenalar va Z-hisobotlar ro'yxati, fiskal cheklar va xatolar. Mini App'da: Z xulosasini ko'rish | Sonnet | `partner/web`, `customer/miniapp` (xodim) | Komponent testlari, e2e |
| P3-24 | **KDS: sexlar va qozon.** Sex tanlash (filter), "O'CHIRILDI" belgisi, qozon qoldig'i paneli (`batches.manage`), Telegram va POS buyurtmalari bitta oqimda | Sonnet | `pos/apps/kds` | Flutter testlari, suratlar |

**To'qnashuv xavfi va yechim:**
- P3-21 va P3-22 bitta ilovada. Qobiq, navigatsiya va umumiy holat P3-21 ga tegishli va birinchi bo'lib tayyorlanadi;
  P3-22 faqat o'z ekran papkalarida ishlaydi.
- P3-23 yagona TypeScript ishi, P3-24 yagona KDS ishi.

## To'lqin 3 — internetsiz rejim, fiskal drayver, sinov, review

| Kod | Ish | Bajaruvchi | Tekshiruv |
|---|---|---|---|
| P3-31 | **Internetsiz cheklangan rejim** (POS_SPEC 5-bo'lim): `pos_sync` (SQLite navbat, `op_id`, lokal raqam `K1-017`), `POST /pos/sync`, to'qnashuvlar va "Sinxronlash xatolari", 24 soatlik menyu nusxasi. Fiskal qismi: offlayn sotuv cheklari navbatda saqlanadi, 48 soatlik chegaradan ancha oldin ogohlantirish | Opus | Uzilish va qayta ulanish sinovlari; elektr o'chishini simulyatsiya; dublikat yo'q; pul yo'qolmaydi |
| P3-32 | **Haqiqiy fiskal drayver.** Siz tanlagan provayder (Rahmat/Multikassa, REGOS, E-POS yoki boshqasi) API'si bo'yicha; imkon bo'lsa dinamik UzQR. Provayder hujjati va sinov kalitlari kelmasa — sandbox drayverda qoladi va pilot real tushum bilan boshlanmaydi | Opus | Provayderning sinov muhitida sotuv va qaytarish cheklari |
| P3-33 | **E2E testlar:** kassada buyurtma → oshxona markasi va KDS → to'lov → fiskal chek → Z; Telegram buyurtmasi kassada qabul qilinadi; qozon tugaganda Telegram menyusida ham yopiladi | Sonnet | CI'da o'tadi, 3 marta ketma-ket barqaror |
| P3-34 | **Qo'llanmalar** (o'zbekcha, oddiy tilda): kassir uchun (smena, sotuv, qaytarish, Z), ega uchun (MXIK kiritish, printer va sexlar, fiskal provayder, eski kassa bilan ikki marta hisoblamaslik) | Sonnet | Siz o'qib tasdiqlaysiz |
| P3-35 | **Mustaqil review** (backend va fiskal, kassa ilovasi, web va KDS) va topilmalarni tuzatish | 3 × Opus + tuzatuvchilar | Topilmalar yopilgan, CI yashil |
| P3-36 | **Staging va pilotga tayyorgarlik:** staging'da to'liq sikl, haqiqiy planshet yoki Sunmi va LAN printerda sinov, pilot joyda tadqiqot savolnomasi bo'yicha suhbat va cho'qqida kuzatuv | Men + siz | Tekshiruv ro'yxati to'liq |

## Keyingi kengaytmalar (3-bosqichdan keyin, tartib bilan)

Har biri alohida reja bilan, pilot natijasiga qarab:

1. **Choyxona:** zallar, xonalar va tapchanlar, xona narxi va kishi boshiga narx, xizmat haqi zal bo'yicha, uzoq ochiq
   hisob, hisobni bo'lish, bo'nak (bron, zaklad) va kredit (nasiya) cheklari, doimiy guruhlar, ofitsiant ilovasi.
2. **Restoran va kafe** (kabobxona va baliqxona kichik profillari bilan): stol xaritasi, kurslar, ko'chirish va
   birlashtirish, six va gramm, sex printerlari, mijoz ekrani.
3. **Fast food:** kombo va qutilar, Yandex Eats va Uzum Tezkor API, tasdiqlash vaqti hisoblagichi, mijoz ekranida
   navbat.
4. **Stolovaya va qahvaxona:** patnis rejimi, kunlik menyu, stiker printeri.
5. **To'yxona va banket:** taqvim, kishi boshiga paket, to'lov jadvali.
6. **Rizqim o'z virtual kassasi (A yo'li),** agar siz shuni tanlasangiz: OFD shartnomasi, xulosalar, reyestr.

## Har bir ishning bajarilish tartibi

1–2-bosqichlardagidek: agentga aniq topshiriq → agent ishlaydi → men o'zim tekshiraman (testlar, PHPStan, lint, build,
e2e, suratlar) → commit (ish kodi bilan) → to'lqin oxirida PR, yashil CI, sizga qisqa hisobot → siz merge qilasiz.

## Sizdan kerak bo'ladiganlar

| Nima | Qachongacha | Kerak bo'lmasa nima bo'ladi |
|---|---|---|
| Ushbu rejani tasdiqlash yoki o'zgartirish | To'lqin 1 dan oldin | Boshlanmaydi |
| Birinchi format: osh markazi + tezkor kassa (taklif) yoki boshqasi | To'lqin 1 dan oldin | Taklif bo'yicha qilinadi |
| Fiskal provayder tanlovi (B yo'li) va undan API hujjati hamda sinov kalitlari | To'lqin 3 | Sandbox drayverda qoladi, real tushum bilan pilot bo'lmaydi |
| A yo'li (Rizqim o'zi virtual kassa) bo'yicha qaror | Keyinroq | B yo'li bilan ishlanadi |
| Pilot osh markazi, undagi uskunalar va soliq rejimi (QQS 12%, 6% yoki aylanma soliq) | To'lqin 3 | Pilot keyinga suriladi |
| Sinov uchun Android planshet yoki Sunmi va bitta LAN printer | To'lqin 2 oxiri | Faqat emulyator va Linux'da sinaladi |
| Staging server va domen (P1-35, P2-34 bilan birga) | To'lqin 3 | Staging tekshiruvlari keyinga qoladi |
| POS_SPEC 14-bo'limidagi savollarga javob (navbat raqami, biznes kun, ogohlantirish chegaralari, P2P va boshqalar) | To'lqin 1 | Spetsifikatsiyadagi takliflar bo'yicha qilinadi |
| PR'larni ko'rib chiqish va merge | Har to'lqin oxirida | Keyingi to'lqin boshlanmaydi |

## Bosqich tugaganini qanday bilamiz

- Kassir planshetda smena ochadi, osh markazi buyurtmasini 30 soniya ichida oladi, to'lov qabul qiladi, fiskal chek
  va marka chiqadi, smenani Z bilan yopadi.
- Qozon tugaganda kassada, KDS'da va Telegram menyusida bir vaqtda "tugadi" bo'ladi.
- Telegram buyurtmasi kassada qabul qilinadi; Payme yoki Click bilan to'langan Mini App buyurtmasi fiskal chek oladi.
- Internet qisqa uzilganda sotuv to'xtamaydi, qaytgach hech narsa yo'qolmaydi va takrorlanmaydi.
- Barcha testlar va e2e o'tadi, CI yashil, PR'lar merge qilingan.
- Staging'da va haqiqiy uskunada ishlaydi; pilot joy tanlangan va suhbat o'tkazilgan.
