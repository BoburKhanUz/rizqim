# 6-bosqich rejasi: Rizqim POS barcha ovqatlanish formatlari uchun

Holat: **qoralama** (28.10.2026), egasi tasdig'ini kutadi. 4-bosqichdagi funksiya cheklovlariga (P4-21) va
3-bosqichdagi haqiqiy fiskal drayverga (P3-32) tayanadi. Agregator ulagichlari bu yerda emas, 5-bosqichda
([PHASE5_PLAN.md](PHASE5_PLAN.md)). Egasidan kerak bo'lganlar: 13-bo'lim.

Asos: [POS_SPEC.md](POS_SPEC.md), [PHASE3_PLAN.md](PHASE3_PLAN.md), tadqiqotlar
[Ovqatlanish formatlari](research/reports/Ovqatlanish%20formatlari%20POS%20talablari.md) (14 format, matritsa,
savolnoma), [Ovqatlanish tarmoqlari](research/reports/Ovqatlanish%20tarmoqlari%20uchun%20POS%20talablari.md) (BMTTD,
uskunalar), [Choyxona](research/reports/Choyxona%20ishlashi%20va%20POS%20talablari.md),
[Narxlar va fiskal terminallar](research/reports/POS%20bozori%20narxlar%20va%20fiskal%20terminallar.md) va egasining
24.10.2026 dagi javoblari.

## 1. Nima uchun

3-bosqichda Rizqim POS bitta format — **choyxona** — uchun qurildi: zal xaritasi, xona puli, choy-non, kg osh, bron va
bo'nak, nasiya, ofitsiant telefoni, KDS sexlari, smena va Z, fiskal chek. Lekin bozorning katta qismi choyxona emas.
BMTTD so'rovida (420 tadbirkor) kafe va oshxona 45%, restoran 29%, fast food 11%, choyxona 10%, to'yxona 2%, qahvaxona
1%. Ovqatlanish korxonalarining atigi 36% ida boshqaruv dasturi bor, 22.10.2026 dan esa soliq tizimi bilan
integratsiya qilinmagan buyurtma dasturi taqiqlangan (VM 393). Ya'ni dasturi yo'q taxminan 64% joy hozir majburan
tizim qidiradi.

Raqobatchilarning sahifalarida qozon yoki partiya qoldig'i, og'irlik bo'yicha sotish, xona narxi, kishi boshiga banket
narxi ko'rinmaydi; kombo va KDS ko'pincha faqat eng qimmat tarifda (Biznex Pro). Rizqim uchun imkoniyat: **bitta
kassa ilovasi, bitta sozlama bilan har qanday formatga moslanadi**, fiskal chek va RIZQIM Telegram buyurtmalari har
tarifda.

**Maqsad:** osh markazi, somsaxona, stolovaya, kafe, restoran, kabobxona, baliqxona, fast food, qahvaxona, to'yxona va
faqat yetkazadigan oshxona Rizqim POS'ni o'z ish tartibida, qo'shimcha "aylanma yo'l"siz ishlatadi; har biri
ro'yxatdan o'tganda formatni tanlaydi va kerakli ekranlar o'zi yoqiladi.

## 2. Muvaffaqiyat o'lchovlari

| O'lchov | Maqsad |
|---|---|
| Haqiqiy pilotdan o'tgan format profillari | Bosqich oxirida kamida 6 ta: osh markazi, somsaxona yoki tamaddixona, stolovaya, kafe yoki restoran, fast food, to'yxona |
| Tez kassada bitta buyurtma (boshidan to'lov tugaguncha) | Mediana 30 soniyadan kam; cho'qqida bitta kassada daqiqasiga 1 buyurtma |
| Format tanlashdan birinchi fiskal chekkacha | 1 ish kuni (profil, menyu shabloni, qurilmalar ulanishi bilan) |
| Fast food KDS: buyurtmadan "tayyor"gacha | 90% buyurtma 9 daqiqada (Oqtepa maqsadi), vaqt KDS'da o'lchanadi |
| Banket: ikki marta band qilish | 0; har bir zaklad bo'nak cheki bilan |
| Fiskal | 100% sotuv chek bilan; ikki marta hisoblangan sotuv 0 (Z va bank terminali hisoboti solishtiriladi) |
| Choyxona buzilmaydi | 3-bosqichdagi "payshanba oqshomi" e2e sinovi har to'lqinda o'tadi |
| Bozor | Bosqich tugagandan 3 oy ichida 30 ta pullik POS joyi, kamida yarmi choyxona emas; sinovdan pullikka o'tish 30% dan ko'p |

## 3. Formatlar matritsasi

Tadqiqotdagi 14 format bo'yicha qisqacha. "Xizmat modeli": **zal** — ofitsiant stolga xizmat qiladi; **kassa** — mijoz
kassada buyurtma beradi va to'laydi; **raqamli tez xizmat** — kassa + buyurtma raqami + "tayyor" ekrani; **liniya** —
o'z-o'ziga xizmat, patnis, liniya oxirida kassir; **banket** — tadbir oldindan sotiladi.

| Format | Xizmat modeli | Savdo birligi | Oshxona oqimi | To'lov | Cho'qqi | Qurilmalar |
|---|---|---|---|---|---|---|
| Osh markazi | Kassa yoki gibrid (ofitsiant schyot olib keladi, markaziy kassada to'lanadi) | Porsiya ulushi 1 / 0,7 / 2, kg olib ketish, qozon | Qozondan tarqatish; qozon qoldig'i hisoblagichi | Naqd, karta, QR; ba'zan 12% xizmat haqi | Tong va tushlik, 13–14 da tugaydi | Kassa, ixtiyoriy tarqatish markasi |
| Lag'monxona | Zal yoki kassa | Porsiya | Buyurtma bo'yicha, oshxona markasi majburiy | Naqd, karta, QR | Tushlik | Kassa, oshxona printeri |
| Choyxona (tayyor) | Zal (xona, tapchan) | Xona + kg taom + choy-non | Sexlar: osh, mangal, salat va bar, choy | Aralash, bo'nak, nasiya | Kechqurun, payshanba–shanba | Kassa, ofitsiant telefonlari, KDS yoki printer |
| Kafe | Zal yoki kassa ("stolga olib kelish") | Porsiya, ichimlik | Oshxona va bar | Aralash; 10–15% xizmat haqi | Tushlik va kechqurun | Kassa, sex printerlari, ofitsiant telefoni |
| Restoran | Zal (hostes, ofitsiant, kurslar) | Stol, zal, VIP kabina | Kurslar bo'yicha sexlar | Aralash; xizmat haqi zal bo'yicha (15–25%); bron depoziti | Tushlik va kechqurun | Kassa, sex printerlari yoki KDS, ofitsiant telefonlari |
| Kabobxona, shashlikxona | Zal | Six (dona), 100 g, assorti | Ofitsiant → mangal, oshxona, choy | Aralash | Kechqurun | Kassa, mangal markasi yoki KDS |
| Baliqxona | Zal | kg narx × haqiqiy og'irlik, qat'iy porsiya | Tanlash → tortish → pishirish (mangal) | Aralash; chek katta | Kechqurun | Kassa, tarozi (keyin) |
| Fast food | Raqamli tez xizmat | Buyurtma raqami, kombo, quti | Kassa → sexlar (grill, fritur, ichimlik) → yig'ish → "tayyor" | Aralash, onlayn | Tushlik va kechki; ba'zan 24/7 | Kassa, KDS, mijoz ekrani |
| Tamaddixona | Kassa, ko'pincha ega o'zi | Dona, porsiya | Tayyor mahsulot | Naqd, QR | Tong va tushlik | Telefon yoki planshet |
| Somsaxona | Kassa, oldindan buyurtma | Dona, kg, tandir partiyasi | Partiya; "keyingi partiya 20 daqiqada" | Naqd, QR | Tong va tushlik | Telefon yoki planshet |
| Stolovaya | Liniya (qonun bo'yicha faqat o'z-o'ziga xizmat) | Patnis: porsiya, yarim porsiya, kompleks, kg | Oshxona markasi yo'q | Naqd, karta, korxona talonlari | Tushlik | Kassa liniya oxirida, tarozi (keyin) |
| Qahvaxona | Kassa → barista | Ichimlik + modifikatorlar (hajm, sut, sirop, shot) | Barista navbati, stakan stikeri | Karta, QR | Tong | Kassa, stiker printeri |
| To'yxona, banket | Banket | Tadbir, kishi boshiga paket, zal ijarasi | Ishlab chiqarish varag'i | Zaklad, oraliq va yakuniy to'lov; bank o'tkazmasi, naqd | Bahor–kuz, ertalabki osh va kechki tadbir | Kassa yoki web panel, taqvim |
| Dark kitchen | Faqat yetkazish | Kanal buyurtmasi, bir nechta brend | Bot va agregatorlar → KDS → kuryer | Onlayn, agregator | Kechqurun | Planshetlar, printer |

**Bugun nima ishlaydi va nima yetishmaydi** (bo'shliq kodlari 5-bo'limda):

| Format | Bugun Rizqim POS'da ishlaydi | Yetishmaydi | Profil | To'lqin |
|---|---|---|---|---|
| Osh markazi | Tez kassa (stolsiz buyurtma), qozon (`menu_batches`), ulushlar, stop-list Telegram'ga ham, erta biznes kun | A1–A4, A7 | `osh_markazi` | 1 |
| Lag'monxona | Tez kassa, oshxona markasi | A1–A3 | `lagmonxona` | 1 |
| Somsaxona, tamaddixona | Tez kassa, partiya, dona va kg | A1, A2, A5, A7 | `somsaxona`, `tamaddixona` | 1 |
| Stolovaya | Tez kassa, kg, xizmat haqisiz rejim | A1, A6, D4 | `stolovaya` | 1, 4 |
| Kafe | Zal, ofitsiant, schyot, bo'lish, xizmat haqi zal bo'yicha | A1–A3, C1, C2, C3, C7, D1 | `kafe` | 1, 2, 4 |
| Restoran | Zal, ofitsiant, sexlar, schyot qulfi, "O'CHIRILDI", PIN, bron | B5, C1–C6 | `restoran` | 2 |
| Kabobxona | Mangal sexi, six soni KDS'da | B4, C1, C3 | `kabobxona` | 2 |
| Baliqxona | kg birligi va kasr miqdor (1,35 kg × narx) | B3, C1 | `baliqxona` | 2 |
| Fast food | Tez kassa, navbat raqami (ustun), Telegram buyurtmalari navbatda | A1–A4, B1, B6, B8 | `fast_food` | 1, 3 |
| Qahvaxona | Variant (hajm) va modifikatorlar, `label` turidagi printer | A1, A2, B2, B7, D1 | `qahvaxona` | 1, 3 |
| To'yxona | Bron, xonalarni birlashtirish, bo'nak cheki, xizmat qatori | E1–E5 | `toyxona` | 5 |
| Dark kitchen | Kanal narxi, barcha kanallar bitta navbatda | B9, E6 (+ 5-bosqich ulagichlari) | `dark_kitchen` | 5 |

## 4. Kodda bugun nima bor: umumiy va choyxonaga xos

Kodni tekshirib chiqildi (`backend/app/Domain/Pos/**`, `Domain/Fiscal`, menyu modellari, `pos/apps/*`,
`pos/packages/*`). Ko'p narsa allaqachon umumiy, faqat ilova uni ishlatmaydi.

**Umumiy (hamma formatga yaraydi):**
- Buyurtma va bosqichlar (`round_no`), "hozir / ushlab turish" (`fire_mode`), sexga yo'naltirish taom yoki kategoriya
  bo'yicha, KDS holatlari (`sent`, `ready`, `served`, `void`), `sent_at` va `ready_at` (tayyorlash vaqti o'lchanadi).
- Schyot va bo'lish (bitta, teng N, taom bo'yicha), aralash to'lov (`cash`, `card`, `qr`, `prepayment`, `account`), har
  schyotga alohida chek, qaytarish, chegirma, menejer PIN; fiskal qatlam (to'rt chek turi, MXIK, QQS, soliq rejimi;
  sandbox va null drayverlar, haqiqiysi P3-32 da); smena, X/Z, biznes kun, internetsiz cheklangan rejim.
- Menyu: variantlar, modifikatorlar narx bilan, kg va litr kasr miqdor bilan, `menu_batches` (qozon, tandir), stop-list
  (taom, variant, modifikator), kanal narxi, `quick_add`. Printerlar: chek, oshxona va `label` turlari.
- `pos_settings.mode` (`counter`, `table`, `hybrid`) va `queue_numbers`; `orders.queue_number` biznes kun bo'yicha;
  `pos_core` da navbat talonchasi shabloni. Bron va bo'nak, nasiya, `by_waiter` hisoboti, funksiya bayroqlari (Pennant).

**Choyxonaga xos (boshqa formatda yashiriladi yoki almashtiriladi):** sex turlari `osh`, `mangal`, `salad_bar`, `tea`
va KDS'dagi qozon paneli, six guruhlari; `room` / `tapchan` joylar, xona tariflari, minimal summa farqi; choy-non
avtomatik qatori va "choy-nonsiz yopilgan xona" ogohlantirishi; 4–6 soatlik bron sloti. Kassa ilovasi doim zal
xaritasidan boshlanadi ("Tez kassa" — ikkinchi tugma) va `mode`, `queue_numbers` sozlamalarini o'qimaydi.

**Umuman yo'q:** kombo, kurslar raqami, choy puli (tips), ish haqi qoidalari, mijoz ekrani (`pos/apps/customer_display`
bo'sh), stiker shabloni, ingredient darajasidagi stop-list, retsept va ombor, sertifikat va vaucher, korxona talonlari,
banket paketi va to'lov jadvali, bir oshxonada bir nechta brend, Click/Payme QR'ni kassada avtomatik tasdiqlash.

## 5. Bo'shliqlar ro'yxati (A–E)

Har bandda: bugun nima bor → nima kerak. Qaysi tarifda — 7-bo'lim.

### A. Tez kassa va buyurtma raqami

- **A1. Tez kassa birinchi ekran.** `mode = counter` bor, ilova o'qimaydi. Kerak: menyu panjarasidan boshlash, 1–3
  bosishda buyurtma, "To'lash" va "Keyingi mijoz", tez miqdor; maqsad 30 soniya. `hybrid` da zal va tez kassa yonma-yon.
- **A2. Buyurtma raqami va chaqiruv.** `queue_number` va talon bor. Kerak: kassa bo'yicha raqam oralig'i (internetsiz
  ham to'qnashmasin), "tayyorlanmoqda → tayyor → berildi", ovoz va ekranda chaqirish, Telegram olib ketishga ham raqam.
- **A3. Mijoz ekrani.** Ilova bo'sh. Kerak: kassaning ikkinchi ekrani yoki planshet (joriy chek, jami, to'lov QR'i) va
  TV yoki planshetda "Tayyorlanmoqda / Tayyor" raqamlari; `pos-branch` kanalidan real vaqtda.
- **A4. "Avval to'lov, keyin oshxona".** Kassa rejimida to'lov olingach buyurtma o'zi sexlarga ketadi; osh markazining
  gibrid oqimi (ofitsiant schyot olib keladi, kassada to'lanadi) saqlanadi.
- **A5. Telefon rejimi.** Tamaddixona va somsaxona: ega o'zi kassir, Android telefon joylashuvi, smena bir bosishda,
  printer ixtiyoriy (Bluetooth yoki chek havolasi).
- **A6. Patnis va kunlik menyu (stolovaya).** "Bugungi menyu" (20–40 taomni yoqish, kechagisini nusxalash), yarim
  porsiya, kompleks, markasiz; xizmat haqi qonun bo'yicha **qulf** (0%).
- **A7. Partiya va hisobdan chiqarish.** `menu_batches` bor. Kerak: "keyingi partiya HH:MM" (kassada va Telegram'da),
  sotilmagan qoldiqni sabab bilan hisobdan chiqarish, qozon yoki tandir hosili bilan sotuvni solishtirish hisoboti.

### B. Menyu va oshxona

- **B1. Kombo, quti, kompleks.** Yo'q. Slotlar (asosiy, garnir, ichimlik), tanlov va narx farqi, almashtirish; tarkib
  sexlarga bo'linadi; tarkibdagi taom tugasa kombo ham yopiladi; chek qatorlari — buxgalter javobi bo'yicha.
- **B2. Tez modifikatorlar.** Modifikatorlar oyna orqali. Kerak: menyu tugmasi yonida bir ekranda, standart tanlov,
  hajm bir bosishda (qahvaxona, fast food).
- **B3. Og'irlikni keyin kiritish.** kg × narx bor. Baliqxona: taom tanlanadi, og'irlik tortilgach kiritiladi (kim
  tortdi), schyotda "Sazan (mangal) 1,35 kg × 245 000"; xom yoki pishgan — sozlama. Tarozi — keyin.
- **B4. Six va dona tez miqdor.** "5 qiyma, 3 jigar": ×2…×10 tugmalari, assorti komplekti, mangal kutish vaqti.
- **B5. Kurslar.** `fire_mode = hold` bor. Kerak: kurs raqami, "2-kursni yuborish" (kassa va ofitsiant), KDS'da kurs
  belgisi, "kurs tayyor" xabari ofitsiantga.
- **B6. KDS: universal sexlar va vaqt.** Sex turlari choyxonaniki. Kerak: `grill`, `fryer`, `drinks`, `barista`, `expo`
  (yig'ish), vaqt maqsadi, yig'ish ekrani (hamma sex tayyor → raqam mijoz ekraniga), tayyorlash vaqti hisoboti.
- **B7. Stakan stikeri va yorliq.** `label` printer turi bor, shablon yo'q: ichimlikka stiker, olib ketish paketiga yorliq.
- **B8. Qadoq avtomatik.** Olib ketish va yetkazishda qadoq qatori qoida bo'yicha (MXIK 10202002, bir martalik idish).
- **B9. Ingredient stop-list.** "Tovuq tugadi" — bog'langan hamma taom (barcha brendlarda) yopiladi; retsept emas.
- **B10. Menyu shablonlari.** Profil uchun namunaviy kategoriyalar, birliklar, sexlar, MXIK guruhlari.

### C. Zal va xizmat

- **C1. Xizmat haqi variantlari.** Bor: foiz zal va kanal bo'yicha, sabab bilan olib tashlash, alohida qator. Kerak:
  buyurtma turi bo'yicha, katta guruh qoidasi (N mehmondan ko'p), restoran "cover", e'lon matni avtomatik, stolovayada qulf.
- **C2. Choy puli (tips).** Yo'q. To'lovda naqd, karta yoki QR bilan; tushum emas, ofitsiantga yoziladi, X/Z da alohida,
  fiskal chekdan tashqari (buxgalter); shaxsiy yoki umumiy qozon.
- **C3. Ofitsiant ish haqi qoidalari.** Bugun faqat sotuv va xizmat haqi bo'yicha hisobot. Egasi: "har joyda har xil".
  Kerak: smena stavkasi, sotuvdan %, xizmat haqidan %, garant + %, tadbir stavkasi; xodim yoki rol bo'yicha; davr
  hisobi; kassadagi "ish haqi" chiqimiga bog'lash. Kadrlar hisobi emas.
- **C4. Mehmon bo'yicha bo'lish.** `seat_no` bor, bo'lishda ishlatilmaydi. Kerak: "mehmon bo'yicha" turi, ofitsiant
  telefonida mehmon raqami.
- **C5. Ofitsiantni almashtirish va hostes.** Ochiq stolni boshqa ofitsiantga o'tkazish yo'q; hostes roli (bron, stol
  holati, kutish ro'yxati) yo'q.
- **C6. Restoran broni.** Bron xonaga qurilgan (4–6 soat). Kerak: stol turi bo'yicha slot (1,5–2 soat), muddat qoidasi
  sozlanadi (choyxonada kamida 3 soat oldin), ixtiyoriy depozit bo'nak cheki bilan, kutish ro'yxati.
- **C7. Kafe "stolga olib kelish".** Kassada to'lab, stol raqami tablichkasi bilan; zona "ofitsiantli" yoki "o'z-o'ziga
  xizmat" (xizmat haqi shunga qarab).

### D. To'lov va chek

- **D1. Click va Payme QR kassada.** QR hozir qo'lda tasdiqlanadi. Kerak: schyot summasiga QR (mijoz ekranida yoki
  schyotda), avtomatik tasdiq, qaytarish; chekni Rizqim chiqaradi, to'lov tizimi chiqarmasligi kelishiladi.
- **D2. Dinamik UzQR.** Fiskal provayder yoki bank API'si orqali (P3-32 provayderiga bog'liq; UzQR majburiy, 0,65%).
- **D3. Sovg'a sertifikati va vaucher.** Sotishda bo'nak cheki, ishlatishda sotuv cheki; kod yoki QR, qoldiq, muddat;
  chegirma vaucheri — mavjud promo-kod POS'ga.
- **D4. Korxona hisobi va talon.** Nasiya hisobi bor. Kerak: "korxona" turi, xodimlar ro'yxati, kunlik limit, talon
  yoki abonement, ish beruvchiga oylik hisobot.
- **D5. Fiskal terminal oqimlari.** Egasi: joylarda karta va naqdga chek chiqaradigan fiskal terminal bor. 22.10.2026
  dan summani unga qayta terish ham, Rizqim'ni unga chek uchun ulash ham mumkin emas (VM 393, 25-band). Kerak: (a) bank
  terminali "faqat terminal" rejimida, karta to'lovi Rizqim chekiga tranzaksiya raqami bilan; (b) Rizqim Android
  smart-kassada (Sunmi P2/P3), chek provayder virtual kassasi orqali; (c) o'rnatish ro'yxati (eski kassada sotuv yo'q)
  va kun oxirida bank terminali hisobotini Z bilan solishtirish, farqda ogohlantirish.

### E. Banket, to'yxona va dark kitchen

- **E1. Tadbir taqvimi.** Bron bor (xona × vaqt, birlashtirish). Kerak: zal × sana × vaqt oralig'i (ertalabki osh,
  kunduz, kechqurun), tadbir turi, kafolatlangan va minimal mehmon, "zakladsiz ushlab turilgan" va "zaklad bilan
  tasdiqlangan" holatlari, oylar oldin band qilish.
- **E2. Kishi boshiga paket.** Paket × max(mehmon, minimal) + zal ijarasi + qo'shimchalar (qo'shimcha soat, tort,
  dekor); narx shartnoma kuni muzlatiladi; dollarda aytilsa ham hisob so'mda.
- **E3. To'lov jadvali.** `booking_payments` bitta avansga mo'ljallangan. Kerak: zaklad, oraliq va yakuniy to'lovlar
  sanasi bilan, har biriga bo'nak cheki, Telegram eslatmasi, bekor qilish qoidasi.
- **E4. Ishlab chiqarish varag'i.** Paket × mehmon → porsiya va kg; osh kalkulyatori (1 kg osh = 1 kg guruch + 1 kg
  go'sht, 5–6 kishiga) va bozorlik ro'yxati.
- **E5. Yakuniy hisob va shartnoma.** Haqiqiy mehmon bo'yicha sotuv cheki (zakladlar ayiriladi), tadbir paytidagi
  qo'shimchalar, shartnoma chop etish, mehmon soni (200/250/300) va vaqt (06:00–23:00) ogohlantirishi, ofitsiantlar haqi (C3).
- **E6. Dark kitchen.** Bitta oshxonada bir nechta brend (KDS va markada brend), brend × kanal narxi, ingredient
  stop-list (B9), kanal bo'yicha sof tushum; yagona buyurtma qutisi — 5-bosqich ulagichlari bilan.

## 6. Format profili

**Bitta sozlama** — filialning formati — to'g'ri ekranlarni yoqadi. Profil — tayyor to'plam, qulf emas: ega har bir
modulni alohida yoqib-o'chira oladi (qonun talab qilgan joylardan tashqari).

- **Saqlanishi:** `branches.pos_settings.profile` (14 ta qiymat, 3-bo'lim) va `pos_settings.modules` (profildan farq
  qiladigan yoqish va o'chirishlar). `GET /pos/bootstrap` **samarali modullar** ro'yxatini qaytaradi: profil + ega
  o'zgarishi, kesishma tarif funksiyalari (7-bo'lim). Kassa, ofitsiant, KDS va mijoz ekrani faqat shu ro'yxatga qarab
  ekran va tugmalarni ko'rsatadi; backend ham har so'rovda shu ro'yxatni tekshiradi.
- **Profil nimalarni belgilaydi:** kassa rejimi va birinchi ekran, buyurtma raqami, mijoz ekrani, modullar (xonalar,
  choy-non, bron, nasiya, kurslar, kombo, og'irlik, partiya, kunlik menyu, banket), sex va menyu shabloni (B10),
  standart xizmat haqi, egaga ogohlantirishlar to'plami ("choy-nonsiz yopilgan xona" faqat choyxonada).
- **Qonun qulflari:** `stolovaya` da xizmat haqi 0% va o'zgarmaydi (VM 75); fiskal, MXIK va PIN qoidalari hamma joyda bir.
- **Aralash joylar** (resto-choyxona, osh markazi + zal): asosiy profil + qo'shimcha modullar, `hybrid` rejim.
- **Ulash:** ariza formasidagi "format" (P4-22) profilni tanlaydi; mavjud filiallar `choyxona` profilini oladi.

| Profil | Rejim va birinchi ekran | Yoqiladigan modullar | Sex shabloni | Xizmat haqi | Tavsiya tarif |
|---|---|---|---|---|---|
| `choyxona` | `hybrid`, zal xaritasi | xonalar, xona puli, choy-non, bron, nasiya, qozon | osh, mangal, salat va bar, choy | zal bo'yicha | Asosiy |
| `osh_markazi` | `counter` (gibrid ruxsat), tez kassa | qozon hisoblagichi, ulushlar, raqam, hosil solishtiruvi | osh tarqatish | ixtiyoriy | Start |
| `lagmonxona` | `hybrid`, tez kassa | raqam, oshxona markasi | oshxona | ixtiyoriy | Start |
| `somsaxona`, `tamaddixona` | `counter`, telefon rejimi | partiya, "keyingi partiya", hisobdan chiqarish, oldindan buyurtma | — | yo'q | Start |
| `stolovaya` | `counter`, patnis | kunlik menyu, kompleks, korxona hisobi | — | **0%, qulf** | Start |
| `kafe` | `hybrid`, tez kassa | "stolga olib kelish", zal, ofitsiant, raqam | oshxona, bar | zona bo'yicha | Asosiy |
| `restoran` | `table`, zal xaritasi | kurslar, hostes, bron, choy puli, ish haqi qoidalari | oshxona, bar | zal bo'yicha | Asosiy |
| `kabobxona` | `table`, zal xaritasi | six tugmalari, assorti, mangal kutish vaqti | mangal, oshxona, choy | zal bo'yicha | Asosiy |
| `baliqxona` | `table`, zal xaritasi | og'irlikni keyin kiritish | mangal, oshxona | zal bo'yicha | Asosiy |
| `fast_food` | `counter`, tez kassa | kombo, raqam, mijoz ekrani, qadoq, KDS v2, 24/7 smena | grill, fritur, ichimlik, yig'ish | yo'q | Asosiy |
| `qahvaxona` | `counter`, tez kassa | tez modifikatorlar, stiker, raqam | barista | yo'q | Start |
| `toyxona` | `table`, tadbir taqvimi | banket moduli, to'lov jadvali, ishlab chiqarish varag'i | oshxona | paketga kiradi | Pro |
| `dark_kitchen` | `counter`, kanallar navbati | ko'p brend, ingredient stop-list, qadoq | sexlar | yo'q | Pro |

## 7. Tariflar bo'yicha cheklovlar

Narxlar o'zgarmaydi (4-bosqich, 6-bo'lim): Start 149 000, Asosiy 249 000, Pro 449 000 so'm/oy; qo'shimcha kassa
59 000, qo'shimcha KDS 29 000; ofitsiant telefonlari bepul; sinov 14 kun (Pro imkoniyatlari bilan). Cheklov P4-21
mexanizmi bilan: tarifda yo'q funksiya ekranda "Asosiy tarifda bor" deb ko'rinadi. **Kassa hech qachon to'lov olish va
chek chiqarishdan to'xtamaydi** — tarif tushsa ham ochiq buyurtmalar va kombo narxlari ishlayveradi, faqat yangi sozlash
yopiladi.

| Funksiya | Start | Asosiy | Pro |
|---|:-:|:-:|:-:|
| Format profili, tez kassa, telefon rejimi, buyurtma raqami va talon | ✓ | ✓ | ✓ |
| Mijoz ekrani (kassaning ikkinchi ekrani) | ✓ | ✓ | ✓ |
| Kunlik menyu, kompleks, partiya va hisobdan chiqarish, qozon hosili hisoboti | ✓ | ✓ | ✓ |
| Kombo va quti, tez modifikatorlar, six tugmalari, og'irlikni keyin kiritish, qadoq | ✓ | ✓ | ✓ |
| Click va Payme QR kassada, dinamik UzQR | ✓ | ✓ | ✓ |
| KDS v2 (universal sexlar, yig'ish ekrani, vaqt hisoboti), "tayyor" raqamlar ekrani | — | ✓ | ✓ |
| Kurslar, hostes, restoran broni, mehmon bo'yicha bo'lish | — | ✓ | ✓ |
| Xizmat haqi variantlari, choy puli, ofitsiant ish haqi qoidalari | — | ✓ | ✓ |
| Stakan stikeri va yorliqlar | — | ✓ | ✓ |
| Sovg'a sertifikati va vaucher, korxona hisobi va talonlar | — | ✓ | ✓ |
| Banket moduli (taqvim, paket, to'lov jadvali, ishlab chiqarish varag'i) | — | — | ✓ |
| Ko'p brend, ingredient stop-list | — | — | ✓ |

Izoh: kombo va tez modifikatorlar ataylab Start'da — raqobatchida ular eng qimmat tarifda, bu Rizqim uchun sotuv
dalili. Fast food baribir KDS uchun Asosiy oladi. Alohida "tayyor" ekrani yoki mijoz ekrani plansheti qanday
hisoblanishi — egasi qarori (13-bo'lim, 7).

## 8. Uskunalar

Narxlar tadqiqotdan (e'lonlar va sotuvchi saytlari, taxminiy). Qonun bo'yicha har kassada **2D skaner** (taxminan
300 ming so'm) va kamida 55 mm chek qog'ozi; karta terminali bankdan bepul yoki arzon ijara.

| Format | Kassa | Oshxona | Mijozga | Qo'shimcha | Taxminiy byudjet |
|---|---|---|---|---|---|
| Osh markazi | Android planshet yoki Sunmi (P3 990 ming) | 1 LAN printer tarqatishda (480–700 ming) | Ixtiyoriy: raqam ekrani (planshet/TV) | 2D skaner, UPS | 2–4 mln |
| Lag'monxona, kafe | Planshet yoki Windows monoblok (1–4 mln) | Oshxona va bar printeri yoki KDS planshet | Kafe: ikkinchi ekran | Ofitsiant telefonlari (o'zlariniki), 2D skaner | 3–6 mln |
| Somsaxona, tamaddixona | Android telefon yoki Sunmi | — | — | Bluetooth 58 mm printer (ixtiyoriy) | 1–2 mln |
| Stolovaya | Monoblok yoki planshet liniya oxirida | — | Ikkinchi ekran | Tarozi keyin (1,9–3,7 mln) | 2–4 mln |
| Restoran, kabobxona, baliqxona | Windows monoblok yoki planshet | Har sexga LAN printer yoki KDS | — | Ofitsiant telefonlari, 2D skaner, UPS, LTE router; baliqxona — tarozi keyin | 4–8 mln |
| Fast food | Monoblok yoki planshet, kassa ikkinchi ekrani | KDS planshetlar (sexlar + yig'ish) | "Tayyor" raqamlar TV yoki planshet | Talon printeri, 2D skaner | 6–10 mln (2 kassa) |
| Qahvaxona | Planshet yoki Sunmi | Barista KDS yoki printer | Ikkinchi ekran | Stiker printeri (narx topilmadi) | 3–5 mln |
| To'yxona | Web panel (ofis) + kassa | Oshxona printeri (ishlab chiqarish varag'i) | — | A4 printer (shartnoma) | 2–4 mln |
| Dark kitchen | Planshet (buyurtmalar navbati) | KDS planshetlar | — | Yorliq printeri paketga | 3–5 mln |

Kassa ilovasi qurilmalari o'zgarmaydi: Windows, Android planshet, Android telefon (yangi joylashuv, A5), Sunmi turidagi
terminal. iPad — 11-bo'lim. Yangi drayverlar faqat `pos_hardware` da: stiker (TSPL yoki ESC/POS label), ikkinchi ekran
(Sunmi D2 turi va Windows ikkinchi monitor).

## 9. To'lqinlar

Har to'lqin — **ishlatsa bo'ladigan tayyor qism** (backend, kassa, ofitsiant, KDS va hamkor paneli birga), bitta PR;
siz merge qilganingizdan keyin keyingisi boshlanadi. Tartib: tadqiqotdagi bozor tartibi (tez kassa oilasi → restoran
va kafe → fast food → banket → dark kitchen), kod tayyorligiga qarab tuzatilgan — tez kassa uchun ko'p narsa allaqachon
bor, shuning uchun u birinchi va eng arzon.

```
To'lqin 1  "Tez kassa"            osh markazi, lag'monxona, somsaxona, tamaddixona, stolovaya; kafe va fast food asosi
           P6-11 Format profili    P6-12 Tez kassa va telefon rejimi    P6-13 Buyurtma raqami va chaqiruv
           P6-14 Mijoz ekrani      P6-15 Kunlik menyu, partiya, hosil   P6-16 Fiskal terminal oqimlari
           P6-19 Review, e2e, qo'llanma, pilot

To'lqin 2  "Restoran va kafe"     restoran, kafe, kabobxona, baliqxona
           P6-21 Kurslar           P6-22 Xizmat haqi va choy puli       P6-23 Ish haqi qoidalari
           P6-24 Mehmon bo'yicha bo'lish, ofitsiantni almashtirish, hostes
           P6-25 Six, og'irlik, "stolga olib kelish"                    P6-26 Stol broni
           P6-29 Review, e2e, qo'llanma, pilot

To'lqin 3  "Fast food va qahvaxona"
           P6-31 Kombo             P6-32 Tez modifikatorlar va stiker  P6-33 KDS v2
           P6-34 Qadoq, 24/7, olib ketish                               P6-39 Review, e2e, pilot

To'lqin 4  "To'lov va chek"       hamma formatlar
           P6-41 Click/Payme QR    P6-42 Dinamik UzQR                   P6-43 Sertifikat va vaucher
           P6-44 Korxona hisobi va talon                                 P6-49 Review, e2e, pilot

To'lqin 5  "Banket va dark kitchen"
           P6-51 Tadbir taqvimi    P6-52 Paket va narx                  P6-53 To'lov jadvali
           P6-54 Ishlab chiqarish varag'i va yakuniy hisob              P6-55 Ko'p brend, ingredient stop-list
           P6-59 Review, e2e, qo'llanma, pilot
```

Ish tartibi 3–4-bosqichdagidek: avval backend kelishuvi (jadvallar, endpointlar, ruxsatlar, bootstrap maydonlari),
keyin parallel agentlar, mening tekshiruvim, mustaqil review va bitta PR; POS_SPEC va API.md har to'lqinda yangilanadi.

### To'lqin 1 — "Tez kassa"

| Kod | Ish | Asosiy joylar | Tekshiruv |
|---|---|---|---|
| P6-11 | **Format profili:** `pos_settings.profile` va `modules`, profil to'plamlari (6-bo'lim jadvali), samarali modullar bootstrap'da, backend tekshiruvi, tarif kesishmasi (P4-21), stolovaya qulfi, menyu va sex shablonlari (B10), hamkor panelida "Format" sahifasi, mavjud filiallar `choyxona` ga | `Domain/Pos/Setup`, `partner/web`, `pos_data` | Har profil uchun bootstrap testi; yopiq modul endpointi 403 `module_disabled`; choyxona e2e o'zgarmaydi |
| P6-12 | **Tez kassa birinchi ekran** (A1, A4, A5): rejimga qarab birinchi ekran, panjara + tez miqdor + "To'lash", "avval to'lov, keyin oshxona", telefon joylashuvi va bitta foydalanuvchi rejimi | `pos/apps/cashier`, `pos_ui` | Widget testlari; 20 ta buyurtmada mediana < 30 s (Linux suratlar va vaqt o'lchovi) |
| P6-13 | **Buyurtma raqami va chaqiruv** (A2): kassa bo'yicha oraliq, internetsiz ham to'qnashmaydi, "tayyor → berildi", ovozli chaqiruv, Telegram olib ketishga raqam, talon | `Domain/Pos/Shifts`, `Orders`, `pos_sync`, kassa | Ikki kassa + internetsiz: dublikat raqam yo'q; biznes kunda 1 dan |
| P6-14 | **Mijoz ekrani** (A3): `pos/apps/customer_display` — kassa ikkinchi ekrani va alohida planshet; joriy chek, jami, QR; "Tayyorlanmoqda / Tayyor" ekrani; qurilma turi va ulash | `customer_display`, `pos_hardware`, `devices` | Suratlar; real vaqtda 2 soniyadan kam kechikish |
| P6-15 | **Kunlik menyu, partiya, hosil** (A6, A7): bugungi menyu va nusxalash, kompleks, "keyingi partiya", hisobdan chiqarish, hosil va sotuv solishtiruvi hisoboti | `Menu`, `menu_batches`, panel, kassa | Hisobdan chiqarish Z va hisobotda; Telegram menyusida "keyingi partiya" |
| P6-16 | **Fiskal terminal oqimlari** (D5): bank terminali "faqat terminal" rejimida tranzaksiya raqami bilan, Sunmi smart-kassada Rizqim, o'rnatish ro'yxati, kun oxirida bank hisoboti va Z solishtiruvi, farq ogohlantirishi | `Domain/Fiscal`, `Billing`, `Shifts`, qo'llanma | Sandbox drayverda to'liq sikl; farq ogohlantirishi testi |
| P6-19 | **Review, e2e va pilot:** "osh markazi tushligi" e2e (3 soatda 300 buyurtma, 2 qozon, qozon tugaydi); kassir qo'llanmasi (tez kassa); osh markazi va somsaxona pilotlari | hammasi | 10-bo'lim |

### To'lqin 2 — "Restoran va kafe"

| Kod | Ish | Asosiy joylar | Tekshiruv |
|---|---|---|---|
| P6-21 | **Kurslar** (B5): qatorda kurs raqami, "N-kursni yuborish", KDS'da kurs belgisi, kurs tayyor xabari ofitsiantga | `Pos/Orders`, kassa, ofitsiant, KDS | 3 kursli buyurtma e2e; ushlab turilgan kurs oshxonaga erta chiqmaydi |
| P6-22 | **Xizmat haqi variantlari va choy puli** (C1, C2): buyurtma turi va guruh qoidasi, "cover", e'lon matni; choy puli to'lovda, ofitsiantga, X/Z da alohida, fiskal chekdan tashqari | `PosChargesCalculator`, `Billing`, `Shifts` | Chek jami = schyot jami − choy puli; stolovaya profilida xizmat haqi rad etiladi |
| P6-23 | **Ofitsiant ish haqi qoidalari** (C3): qoidalar (smena stavkasi, sotuvdan %, xizmat haqidan %, garant + %, tadbir stavkasi), hisob davri, panel sahifasi, ish haqi chiqimiga bog'lash | yangi `Pos/Staff`, panel | Har qoida turi uchun hisob testi; davr chegaralari biznes kun bo'yicha |
| P6-24 | **Mehmon bo'yicha bo'lish, ofitsiantni almashtirish, hostes** (C4, C5): `seat_no` bo'yicha bo'lish turi, stolni boshqa ofitsiantga o'tkazish (menejer), `hostess` roli va ruxsatlari, kutish ro'yxati | `BillSplitter`, `Pos/Orders`, ruxsatlar | Ruxsat testlari; bo'lishda qoldiq qoidasi |
| P6-25 | **Six, og'irlik, "stolga olib kelish"** (B3, B4, C7): ×N tugmalari, assorti, mangal kutish vaqti; "og'irlik kutilmoqda" va keyin kiritish, schyotda og'irlik; kassada to'lab stol raqami bilan buyurtma | menyu, kassa, ofitsiant, KDS | Og'irlik kiritilmagan qator bilan schyot chiqmaydi; chekda haqiqiy kg |
| P6-26 | **Stol broni** (C6): stol turi bo'yicha slot, bron muddati qoidasi sozlanadi, depozit ixtiyoriy, kutish ro'yxati | `Pos/Booking` | Choyxona broni (kamida 3 soat) o'zgarmaydi |
| P6-29 | **Review, e2e, pilot:** "juma kechasi restoran" e2e (20 stol, 4 ofitsiant, 3 kurs, bo'lingan schyot, choy puli); qo'llanmalar; kafe va restoran, kabobxona yoki baliqxona pilotlari | hammasi | 10-bo'lim |

### To'lqin 3 — "Fast food va qahvaxona"

| Kod | Ish | Asosiy joylar | Tekshiruv |
|---|---|---|---|
| P6-31 | **Kombo** (B1): kombo mahsulot turi, slotlar va tanlovlar, narx farqi, sexlarga bo'linish, stop-list ta'siri, fiskal qatorlar (buxgalter javobi bo'yicha), panelda konstruktor, Telegram Mini App ham ko'rsatadi | `Menu`, `CreateOrderAction`, `Fiscal`, panel, Mini App | Kombo chek qatorlari yig'indisi = kombo narxi; tarkibdagi taom tugasa kombo yopiladi |
| P6-32 | **Tez modifikatorlar va stiker** (B2, B7): bir ekranda modifikatorlar, standart tanlov; stiker shabloni va label drayveri | kassa, `pos_core`, `pos_hardware` | "Oltin" suratlar; label emulyatori |
| P6-33 | **KDS v2** (B6): `grill`, `fryer`, `drinks`, `barista`, `expo` turlari, vaqt maqsadi, yig'ish ekrani → mijoz ekraniga "tayyor", tayyorlash vaqti hisoboti | `KitchenStationKind`, `pos/apps/kds`, hisobotlar | Cho'qqi simulyatsiyasi: 60 buyurtma/soat, 4 sex; hisobotdagi vaqtlar `sent_at`/`ready_at` bilan mos |
| P6-34 | **Qadoq, 24/7, olib ketish** (B8): qadoq qoidasi, yarim tundan o'tadigan smena tekshiruvi, olib ketish vaqti (oldindan buyurtma), yorliq | `PosCharges`, `Shifts`, kassa | Yarim tunda smena va raqam to'g'ri; qadoq chekda o'z MXIK'i bilan |
| P6-39 | **Review, e2e, pilot:** "tushlik cho'qqisi" e2e (2 kassa, 4 KDS, Telegram buyurtmalari, kombo); fast food va qahvaxona pilotlari | hammasi | 10-bo'lim |

### To'lqin 4 — "To'lov va chek"

| Kod | Ish | Asosiy joylar | Tekshiruv |
|---|---|---|---|
| P6-41 | **Click va Payme QR kassada** (D1): schyot summasiga QR, avtomatik tasdiq, qaytarish, mijoz ekranida ko'rsatish; joyning o'z merchant hisobi; ikki marta fiskal bo'lmasligi | `Payment`, `Billing`, kassa, mijoz ekrani | Click va Payme hujjati namunalari bilan testlar; tasdiqsiz to'lov "to'landi" bo'lmaydi |
| P6-42 | **Dinamik UzQR** (D2): fiskal provayder yoki bank API'si orqali (P3-32 provayderiga bog'liq); bo'lmasa statik QR qo'lda qoladi | `Fiscal`, `Payment` | Provayder sinov muhitida |
| P6-43 | **Sertifikat va vaucher** (D3): sotish (bo'nak cheki), ishlatish (sotuv cheki), qoldiq va muddat; promo-kod POS'da | yangi `Pos/Vouchers`, `Fiscal` | Bo'nak → sotuv ketma-ketligi; qoldiq ikki marta ishlatilmaydi |
| P6-44 | **Korxona hisobi va talon** (D4): hisob turi "korxona", xodimlar, kunlik limit, talon, oylik hisobot (Excel) | `Pos/Accounts`, panel | Limit va oylik hisobot testlari |
| P6-49 | **Review, e2e, pilot:** to'lovlar xavfsizlik review'i (webhook imzolari, idempotentlik); stolovaya (korxona talonlari bilan) va Click/Payme QR'ni hamma pilot joylarda yoqish | hammasi | 10-bo'lim |

### To'lqin 5 — "Banket va dark kitchen"

| Kod | Ish | Asosiy joylar | Tekshiruv |
|---|---|---|---|
| P6-51 | **Tadbir taqvimi** (E1): zal × sana × vaqt oralig'i, tadbir turlari, holatlar, to'qnashuv; web panel va kassada | `Pos/Booking` (kengaytma) yoki yangi `Pos/Banquet`, panel | Ikki marta band qilish yo'q (parallel so'rovlar testi) |
| P6-52 | **Paket va narx** (E2): paketlar, formula, qo'shimchalar, narxni muzlatish | `Pos/Banquet`, panel | Formula testlari, minimal mehmon |
| P6-53 | **To'lov jadvali** (E3): zaklad, oraliq, yakuniy; bo'nak cheklari; eslatmalar; bekor qilish, qaytarish, ushlab qolish | `Pos/Banquet`, `Fiscal` | Har to'lovga bo'nak cheki; yakuniy sotuv chekida zakladlar ofseti |
| P6-54 | **Ishlab chiqarish varag'i va yakuniy hisob** (E4, E5): oshxona varag'i, osh kalkulyatori, bozorlik ro'yxati, haqiqiy mehmon bo'yicha hisob, qo'shimcha buyurtmalar, shartnoma chop etish, chegara ogohlantirishlari | `Pos/Banquet`, `pos_core`, panel | 200 mehmonli to'y e2e: zaklad → 2 oraliq → tadbir → yakuniy chek |
| P6-55 | **Ko'p brend va ingredient stop-list** (E6, B9): bitta filialda brendlar, brend × kanal narxi, KDS'da brend, ingredient bog'lash, kanal bo'yicha sof tushum | `Menu`, `StopList`, KDS, hisobotlar | "Tovuq tugadi" hamma brendda bir vaqtda; 5-bosqich ulagichlari bilan e2e (tayyor bo'lsa) |
| P6-59 | **Review, e2e, pilot:** to'yxona pilotida bitta tadbirni boshidan oxirigacha kuzatish; dark kitchen — 5-bosqich Yandex/Uzum ulagichi tayyor bo'lsa | hammasi | 10-bo'lim |

## 10. Tekshiruv

- **Har ish:** ruxsat, filial izolyatsiyasi, modul yopiqligi (`module_disabled`) va tarif cheklovi testlari;
  idempotentlik va `version`; PHPStan, lint, Flutter testlari, Linux suratlari.
- **Pul va fiskal:** kombo, choy puli, sertifikat, zaklad, korxona hisobi uchun "chek summasi = schyot summasi"; Z'dagi
  yangi qatorlar; sandbox drayverda to'rt chek turining to'liq sikli.
- **Regressiya va yuklama:** har to'lqinda "payshanba oqshomi" (choyxona) va internetsiz rejim e2e; tez kassa 3 soatda
  300 buyurtma; fast food soatiga 60 buyurtma, 4 KDS; restoran 20 stol, 4 ofitsiant telefoni bir vaqtda.
- **Pilot har format uchun.** Real tushum faqat haqiqiy fiskal drayver bilan (P3-32), ungacha "o'quv rejimi". Har
  pilotda: savolnoma bo'yicha suhbat (E, K, F), cho'qqida 1–2 soat kuzatuv (O1–O7), 2 hafta ishlash, tuzatishlar.

| Format | To'lqin | Nima sinaladi | O'tish mezoni |
|---|---|---|---|
| Osh markazi | 1 | Qozon hisoblagichi, ulushlar, tushlik cho'qqisi, gibrid to'lov | Qozon tugaganda kassada, ofitsiantda va Telegram'da bir vaqtda "tugadi"; mediana < 30 s |
| Somsaxona yoki tamaddixona | 1 | Telefon rejimi, partiya, hisobdan chiqarish | Ega o'zi bir kun telefonda ishlaydi, Z to'g'ri |
| Stolovaya | 1, 4 | Kunlik menyu, patnis tezligi, korxona talonlari | Liniyada navbat uzaymaydi; oylik hisobot ish beruvchiga |
| Kafe va restoran | 2 | Kurslar, bo'lish, xizmat haqi, choy puli, ish haqi | Juma kechasi: hamma schyot va chek to'g'ri, ish haqi hisobi ega hisobiga mos |
| Kabobxona yoki baliqxona | 2 | Six tugmalari, mangal vaqti, og'irlik | Schyotdagi og'irlik bo'yicha nizo yo'q |
| Fast food | 3 | Kombo, KDS v2, raqam ekrani, Telegram buyurtmalari | 90% buyurtma 9 daqiqada; raqam ekrani to'g'ri |
| Qahvaxona | 3 | Modifikatorlar, stiker, Click/Payme QR | Ertalabki cho'qqida stiker har ichimlikka |
| To'yxona | 5 | Taqvim, paket, to'lov jadvali, yakuniy hisob | Bitta tadbir zakladdan yakuniy chekkacha Rizqim'da |
| Dark kitchen | 5 | Ko'p brend, ingredient stop-list | Faqat 5-bosqich ulagichlari bilan; bo'lmasa keyinga |

## 11. Bu bosqichga kirmaydi (keyin)

- **Yandex Eats, Uzum Tezkor va boshqa POS'lar bilan ulanish** — 5-bosqich ([PHASE5_PLAN.md](PHASE5_PLAN.md)). Dark
  kitchen'ning yagona buyurtma qutisi shunga tayanadi.
- **Ombor, retsept (tex-karta), tannarx, inventarizatsiya, e-faktura importi, xarid dalolatnomasi, 1C eksporti** —
  alohida bosqich. Bu yerda faqat ingredient stop-list (B9) va osh kalkulyatori (E4).
- Tarozi ulash (baliqxona, stolovaya) — og'irlik qo'lda kiritiladi; tarozi pilot so'rasa keyin, qiyoslangan tarozi bilan.
- O'z-o'ziga xizmat kioski, stolda QR orqali buyurtma, drive-through.
- iPad (lokal fiskal modul yo'q; bulutli fiskal yo'l kerak).
- Rizqim'ning o'z virtual kassasi (A yo'li) — alohida qaror.
- To'liq kadrlar va ish haqi hisobi (tabel, soliq, ijtimoiy to'lov) — faqat ofitsiant pul hisobi (C3).
- Sodiqlik dasturi (shtamp, bonuslar) va restoranning o'z boti (Pro qo'shimchasi) — keyin.
- Rus va ingliz tarjimasi.

## 12. Xavflar

- **Fiskal drayver hali yo'q.** P3-32 bo'lmasa hech bir format real tushum bilan pilotga chiqmaydi; dinamik UzQR ham
  provayderga bog'liq. Yechim: provayder tanlovini bosqich boshlanishidan oldin yopish (13-bo'lim, 3).
- **Yangi fiskal savollar** (kombo, choy puli, sertifikat, korxona talonlari, banket zakladi): noto'g'ri MXIK — chekdagi
  sotuvning 1% jarima. Yechim: har to'lqindan oldin buxgalter, javobgacha sandbox.
- **Ilova murakkablashadi:** 14 format bitta ilovada, ortiqcha tugmalar kassirni sekinlashtiradi. Yechim: hamma ekran
  samarali modullar orqali (6-bo'lim), har profil uchun suratlar va e2e.
- **Choyxona buzilishi:** umumiy kod (bo'lish, sexlar, raqam) o'zgaradi. Yechim: har to'lqinda regressiya e2e, mavjud
  filiallar `choyxona` profilida.
- **Dalil kam formatlar** (to'yxona — BMTTD'da ~8 ta, dark kitchen, lag'monxona, baliqxona). Yechim: har to'lqindan oldin
  2–3 suhbat; dark kitchen oxirida va 5-bosqichdan keyin.
- **Cho'qqi va uzilish:** osh markazi va fast food'da uzilish katta navbat beradi; raqam oralig'i va lokal raqam
  (P6-13) yordam beradi, lekin internetsiz rejim hali "qisqa uzilish" darajasida.
- **Qonun:** to'y qoidalari holati noma'lum — ogohlantirish yumshoq va sozlanadi; xizmat haqi oldindan e'lon va alohida
  qator bilan (Raqobat qo'mitasi).
- **Uskunalar xilma-xilligi** (stiker, ikkinchi ekran, Bluetooth printer): pilotdagi aniq modellardan boshlash, matnni
  rasm sifatida chop etish zaxirasi.
- **Bog'liqliklar:** P4-21 kechiksa P6-11 vaqtincha funksiya bayroqlari bilan; "format" maydoni P4-22 bilan kelishiladi.

## 13. Egasidan kerak

1. Rejani va to'lqinlar tartibini tasdiqlash (yoki Click/Payme QR'ni, P6-41, 1-to'lqinga ko'chirish).
2. **Pilot joylar har format uchun** (Toshkent): osh markazi; somsaxona yoki tamaddixona; stolovaya (korxona bilan
   ishlaydigani); kafe; restoran; kabobxona yoki baliqxona; fast food (1–3 filial, Telegram buyurtmasi bor); qahvaxona;
   to'yxona — har biri suhbat va kuzatuvga rozi, hozirgi kassasi, soliq rejimi va uskunasi ma'lum.
3. **Fiskal provayder** (P3-32) tanlovi, API hujjati va sinov kalitlari; dinamik UzQR'ni qo'llaydimi. Busiz pilotlar
   faqat o'quv rejimida.
4. **Sinov uskunalari:** ikkinchi ekranli kassa (Sunmi D2 turi) yoki Windows ikkinchi monitor, raqam ekrani uchun
   Android planshet, stiker printeri, qo'shimcha LAN printer, 2 ta KDS planshet, Android telefon, 2D skaner.
5. **Buxgalter bilan tekshirish:** kombo chekda (bitta MXIK yoki tarkib bo'yicha), choy puli chekka kiradimi, xizmat haqi
   va qadoq MXIK'i, sovg'a sertifikati (sotishda bo'nak, ishlatishda sotuv), korxona talonlari va JShDS, banket zakladi
   va oraliq to'lovlar, sotilmagan mahsulotni hisobdan chiqarish.
6. **Click va Payme kassada:** har joy o'z merchant hisobini ulaydimi (tavsiya) yoki RIZQIM hisobi orqali; to'lov
   tizimi zal to'loviga chek chiqarmasligi bo'yicha ular bilan kelishuv.
7. **Tariflar:** 7-bo'lim jadvali to'g'rimi; alohida mijoz yoki "tayyor" ekrani plansheti bepulmi (tavsiya: bepul,
   ofitsiant telefoni kabi) yoki qo'shimcha KDS kabi 29 000 so'm.
8. **Format ro'yxati:** 6-bo'limdagi 14 profil va ularning nomlari ariza formasida (P4-22) — tasdiqlash.
9. **Ofitsiant ish haqi:** standart qoidalar ro'yxati (smena stavkasi, sotuvdan %, xizmat haqidan %, garant + %, tadbir
   stavkasi) yetarlimi; choy puli shaxsiymi yoki umumiy qozonga. **Qaror (29.10.2026):** moslashuvchan — choy puli rejimi (shaxsiy yoki umumiy qozon va bo'lish qoidasi) va ish haqi qoidalari (bir nechta komponent birga) filial sozlamasidan tanlanadi.
10. **To'yxona:** mehmon soni va vaqt chegarasi ogohlantirishi yoqilsinmi; shartnoma matni namunasi (yurist bilan); zaklad
    va bekor qilish qoidasi bo'yicha 2–3 to'yxona menejeri bilan suhbat.
11. **Dark kitchen:** shu bosqichda (tavsiya: oxirgi to'lqinda, 5-bosqich ulagichlari tayyor bo'lsa) yoki keyin alohida.
12. **Stolovaya va baliqxona uchun tarozi:** pilot so'rasa keyingi bosqichga qo'shamizmi.
13. PR'larni ko'rib chiqish va merge — har to'lqin oxirida.
