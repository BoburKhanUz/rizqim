# Click, Payme va Paylov'ni jonli rejimga ulash

Bu hujjat egasi uchun: Click va Payme'ni haqiqiy pul bilan ishga tushirishdan oldin nima qilinadi, qaysi qiymat
qayerga yoziladi, qanday sinaladi va muammo bo'lsa qanday o'chiriladi. Paylov (uchinchi onlayn to'lov tizimi) —
alohida, [11-bo'lim](#11-paylov). Kassadagi dinamik UzQR — [12-bo'lim](#12-uzqr-kassada).

> **Kalitlar haqida qoida.** Maxfiy kalit (Click `secret_key`, Payme kassa kaliti) hech qachon chatga, Telegram'ga,
> kodga, skrinshotga yoki Git'ga yozilmaydi. Ular faqat serverdagi `backend/.env` ga yoki boshqaruv markazidagi
> **Tizim → To'lov tizimlari** sahifasiga (bazada shifrlangan holda saqlanadi) kiritiladi. Kimdir kalitni
> "tekshirib beraman" deb so'rasa, bermang.

Tekshirilgan rasmiy hujjatlar (2026-yil 21-noyabr): Payme Merchant API (developer.help.paycom.uz) va Click SHOP API,
Merchant API (docs.click.uz).

---

## 1. Pul hozir qayerga boradi

Har bir onlayn buyurtma qaysi hisobga to'lanishi **buyurtma berilgan paytda** belgilanadi (`payment_owner`) va
keyin o'zgarmaydi. Ovqatxona keyinroq sozlamani almashtirsa ham, yo'ldagi to'lov va uning qaytarilishi eski hisob
bo'yicha ishlaydi.

| Holat | Pul kimning hisobiga tushadi | Keyin nima bo'ladi |
|---|---|---|
| Ovqatxona **«Platforma hisobi»** (`platform_account`) — standart | **RIZQIM'ning** Click servisi / Payme kassasi | Buyurtma yetkazilgach summa ovqatxonaga hisoblanadi: avval uning RIZQIM oldidagi qarzi yopiladi, qolgani hamyoniga tushadi; ovqatxona hamyondan pul yechib olishni so'raydi (to'lov so'rovi) |
| Ovqatxona **«O'z hisobi»** (`own_account`) va shu tizim uchun barcha kalitlari to'liq | **Ovqatxonaning o'z** Click servisi / Payme kassasi | Pul to'g'ridan-to'g'ri ovqatxonaga tushadi. RIZQIM komissiyasi ovqatxona qarziga yoziladi, ovqatxona uni balansni to'ldirish orqali to'laydi |
| «O'z hisobi», lekin kalitlari to'liq emas | RIZQIM'ning hisobi (xavfsiz zaxira) | Platforma hisobidagidek |
| Balansni to'ldirish (ovqatxona RIZQIM'ga to'laydi) | Har doim **RIZQIM'ning** hisobi | Ovqatxona qarzi kamayadi |
| Kassadagi QR (POS, Click/Payme) | Har doim **ovqatxonaning o'z** hisobi | Chekni RIZQIM kassasi chiqaradi, Click/Payme ikkinchi chek chiqarmaydi |
| Naqd | Ovqatxonaga | Komissiya qarzga yoziladi |

Xulosa: **hozir ikkala model ham ishlaydi.** Ovqatxonalarning ko'pchiligi standart bo'yicha RIZQIM hisobi orqali
ishlaydi; faqat o'z hisobini ulagan ovqatxonaning puli o'ziga tushadi. Kassadagi QR uchun esa ovqatxonaning o'z
Click/Payme hisobi **majburiy**.

### Ovqatxona o'z hisobini ulasa nima berishi kerak

Ovqatxona o'zi Click va/yoki Payme bilan shartnoma tuzadi (o'z STIR'i bilan) va hamkor panelidagi to'lov
sozlamalariga o'zi kiritadi (RIZQIM xodimi kalitni qo'lida ushlamaydi):

| Tizim | Qiymatlar | Ixtiyoriy |
|---|---|---|
| Click | `service_id`, `merchant_id`, `secret_key` | `merchant_user_id` — Click chek (fiskal) ma'lumotini yuborish uchun |
| Payme | `merchant_id` (kassa ID), `secret_key` (kassa kaliti) | — |

Ovqatxona o'z kabinetida ham webhook manzillarini **xuddi RIZQIM'nikidek** yozadi (3-bo'lim): hamma kassalar bitta
manzilga keladi, server qaysi kassa ekanini buyurtma va kalit bo'yicha o'zi aniqlaydi. Bir nechta filial (do'kon) bitta
Payme kassasini ishlatsa, kassa ko'chirmasi (`GetStatement`) hammasining tranzaksiyalarini beradi.

Ovqatxona kiritgan kalitlar (`stores.payment_settings`) bazada `APP_KEY` bilan **shifrlangan** holda saqlanadi
(platformaning kalitlari kabi); hamkor paneli kalitni hech qachon qaytarmaydi, faqat «kalit kiritilgan» belgisini
ko'rsatadi. Shuning uchun `APP_KEY` ni almashtirmang: almashtirilsa eski kalitni `APP_PREVIOUS_KEYS` ga yozing, aks
holda ovqatxonalar sozlamalari o'qilmay qoladi.

---

## 2. Server qiymatlari (env nomlari)

Quyidagilar serverdagi `backend/.env` ga yoziladi (yoki kalitlar boshqaruv markazidagi **Tizim → To'lov
tizimlari**ga; u yerdagi qiymat `.env` dagidan ustun). Standart qiymat yo'q: `merchant_id` (Click uchun `service_id`
ham) bo'sh qolsa platforma hisobiga buyurtma va balans to'ldirish **rad etiladi** (422 `payment_provider_unconfigured`,
«... orqali to'lov hozircha sozlanmagan», hech narsa yaratilmaydi, logda xato), `secret_key` bo'sh bo'lsa webhook rad
etadi — pul boshqa hisobga ketib qolmaydi. `php artisan app:check-production` faol Click/Payme uchun yetishmagan har bir
qiymatni nomi bilan ogohlantiradi (bazadagi qiymat ham hisoblanadi, qiymatning o'zi chiqmaydi).

| Env nomi | Qayerdan olinadi | Maxfiymi |
|---|---|---|
| `CLICK_SERVICE_ID` | Click kabineti → Servis sozlamalari | yo'q |
| `CLICK_MERCHANT_ID` | Click kabineti | yo'q |
| `CLICK_SECRET_KEY` | Click kabineti (servisning maxfiy kaliti) | **ha** |
| `CLICK_MERCHANT_USER_ID` | Click kabineti → Merchant API foydalanuvchisi (chek uchun) | yo'q (lekin kalit bilan ishlaydi) |
| `PAYME_MERCHANT_ID` | Payme Business → Kassa → Dasturchi parametrlari | yo'q |
| `PAYME_SECRET_KEY` | Payme Business → Kassa → Kalit (ishchi kalit, TEST_KEY emas) | **ha** |
| `PAYLOV_*` | Paylov — [11-bo'lim](#11-paylov) | ko'pi **ha** |
| `PAYMENTS_ALLOW_TEST_MODE` | `false` (productionda doim) | — |
| `QR_PAYMENTS_SANDBOX`, `QR_PAYMENTS_SIMULATE`, `QR_PAYMENTS_SIMULATE_DRIVERS` | productionda bo'sh yoki `false` | — |
| `LOG_LEVEL` | `warning` (webhook so'rovlari `info` darajasida to'liq yoziladi) | — |

Himoya: `APP_ENV=production` va `PAYMENTS_ALLOW_TEST_MODE=false` bo'lsa, bazada «Sinov» bo'lib qolgan to'lov tizimi
ham **ishchi** rejimda ishlaydi (mijoz Payme sinov sahifasiga tushmaydi), kassadagi QR sinov rejimi ham ishlamaydi.

`.env` ni o'zgartirgandan keyin: `php artisan config:cache` (yoki odatdagi deploy).

---

## 3. Webhook manzillari (api.rizqim.uz)

| Tizim | Kabinetdagi maydon | Manzil |
|---|---|---|
| Click | Prepare URL | `https://api.rizqim.uz/api/v1/payments/click/webhook` |
| Click | Complete URL | `https://api.rizqim.uz/api/v1/payments/click/webhook` (o'sha manzil) |
| Payme | Endpoint URL (Merchant API) | `https://api.rizqim.uz/api/v1/payments/payme/webhook` |
| Paylov | Callback URL va cancel webhook URL | `https://api.rizqim.uz/api/v1/payments/paylov/webhook` (ikkalasi bitta manzil, [11-bo'lim](#11-paylov)) |

Payme kassasida **hisob (account) maydoni**: nomi aynan `order_id`, turi — matn (satr), uzunligi kamida 50 belgi
(buyurtma raqami UUID, balans uchun `topup_<uuid>`, kassa QR uchun `pos_qr_<uuid>`).

Tekshirish: `curl -s -X POST https://api.rizqim.uz/api/v1/payments/payme/webhook -H 'Content-Type: application/json' -d '{"id":1,"method":"CheckTransaction","params":{"id":"x"}}'`
javobi `-32504` (ruxsat yo'q) bo'lishi kerak — demak manzil ochiq va kalitsiz so'rov rad etiladi.

---

## 4. IP ro'yxati (whitelist)

Click ham, Payme ham rasmiy hujjatida so'rov yuboradigan IP manzillarini e'lon qilmagan (2026-11-21 holatiga).
Shuning uchun:

1. **Asosiy himoya — kalit va imzo.** Payme har so'rovda `Paycom:<kalit>` yuboradi, Click har so'rovni MD5 imzo bilan
   imzolaydi; noto'g'ri kalit yoki imzo bo'lsa server rad etadi (Payme `-32504`, Click `-1`). Bu testlar bilan
   tekshirilgan.
2. IP cheklovi **qo'shimcha** chora. Click va Payme qo'llab-quvvatlash xizmatidan IP ro'yxatini **yozma** so'rang.
   Ro'yxat olinmaguncha nginx'da IP cheklov qo'ymang: noto'g'ri ro'yxat to'lovlarni to'xtatib qo'yadi.
3. Qo'ysangiz — faqat ikki webhook manziliga, va proksi (Cloudflare va h.k.) orqasida haqiqiy IP to'g'ri
   o'qilayotganini tekshiring.

---

## 5. Click kabinetida nima qilinadi

1. Click bilan shartnoma (RIZQIM nomidan), servis ochiladi.
2. Servis sozlamalarida Prepare va Complete URL — 3-bo'limdagi manzil.
3. `service_id`, `merchant_id`, `secret_key` ni oling va **serverga** yozing (2-bo'lim). Chatga yozmang.
4. Chek (fiskal) uchun: Merchant API foydalanuvchisini oching (`merchant_user_id`), servisning STIR/MXIK ma'lumotlari
   to'g'ri ekanini tekshiring.
5. Click'dan test rejimi (test servis) so'rang — 7-bo'limdagi sinov shu servisda qilinadi.

## 6. Payme Business kabinetida nima qilinadi

1. business.payme.uz → yangi **veb-kassa** qo'shing. Payme ikki kalit beradi: ishchi kalit va `TEST_KEY` (sinov).
2. Kassa → Dasturchi parametrlari: Endpoint URL (3-bo'lim), hisob maydoni `order_id`.
3. `merchant_id` (kassa ID) va kalitni **serverga** yozing. Sinovda — `TEST_KEY`, jonli rejimda — ishchi kalit.
4. Payme kassani jonli rejimga sandbox sinovidan (7-bo'lim) keyin o'tkazadi — sinov natijasini Payme menejeriga
   yuboring.

---

## 7. Sinov (sandbox) — qadamma-qadam

Sinovni **staging** serverda qiling (`APP_ENV=staging`, alohida domen, alohida baza). Productionda sinash kerak
bo'lsa, `PAYMENTS_ALLOW_TEST_MODE=true` ni faqat sinov vaqtiga qo'ying va darhol qaytaring.

### Payme (test.paycom.uz)

1. Staging `.env`: `PAYME_MERCHANT_ID` = kassa ID, `PAYME_SECRET_KEY` = **TEST_KEY**.
2. Boshqaruv markazi → Tizim → To'lov tizimlari → Payme: **Faol**, rejim **Sinov** (to'lov sahifasi
   `checkout.test.paycom.uz` ochiladi).
3. Staging Mini App'da Payme bilan buyurtma bering (masalan 1 000 so'mlik taom). Buyurtma raqamini (UUID) va
   summani tiyinda (so'm × 100) yozib oling. Har ssenariy uchun **yangi** buyurtma bering.
4. test.paycom.uz da: kassa ID, TEST_KEY va Endpoint URL (staging manzili) ni kiriting.
5. **1-ssenariy** (tasdiqlanmagan to'lovni bekor qilish):
   - noto'g'ri kalit → `-32504` kutiladi;
   - noto'g'ri summa → `CheckPerformTransaction` va `CreateTransaction` da `-31001`;
   - mavjud bo'lmagan `order_id` → `-31050`;
   - to'g'ri ma'lumot: `CheckPerformTransaction` → `CreateTransaction` → `CheckTransaction` (holat `1`);
   - `CancelTransaction` → holat `-1`.
6. **2-ssenariy** (yangi buyurtma): `CheckPerformTransaction` → `CreateTransaction` → `PerformTransaction` (ikki marta:
   ikkinchisi ham holat `2`, vaqti o'zgarmaydi) → `CheckTransaction` → `CancelTransaction` (holat `-2`).
7. Boshqaruv markazida tekshiring: 2-ssenariy buyurtmasi avval «to'langan», bekor qilingach yana «to'lanmagan».
8. Hammasi yashil bo'lsa — rejimni **Ishchi** ga qaytaring.

### Click (test servis)

Click'ning sinov vositasi (docs.click.uz → Testing: Playground / Postman) yoki Click bergan test servis bilan:

1. Staging `.env`: test servisning `CLICK_SERVICE_ID`, `CLICK_MERCHANT_ID`, `CLICK_SECRET_KEY`.
2. Click bilan buyurtma bering, raqamini (UUID) va summasini yozib oling.
3. Kutiladigan javoblar:
   - noto'g'ri imzo → `-1`; noto'g'ri summa → `-2`; mavjud bo'lmagan buyurtma → `-5`; majburiy maydon yo'q → `-8`;
   - to'g'ri Prepare → `0` va `merchant_prepare_id`;
   - Complete → `0`; xuddi shu Complete takrori → `0` (`Already confirmed`);
   - to'langan buyurtmaga boshqa `click_trans_id` bilan Complete → `-4`;
   - `error` manfiy bo'lgan Complete (Click to'lovni bekor qildi) → `-9`, buyurtma to'lanmagan bo'lib qoladi.
4. Chek yoqilgan ovqatxonada: Complete'dan keyin chek holati `pending` → `recorded` bo'lishini kuting
   (hamkor paneli → To'lovlar).

---

## 8. Birinchi haqiqiy kichik to'lov va qaytarish

Productionda, jonli kalitlar bilan, **o'zingizning kartangiz** bilan:

1. Tekshiring: `PAYMENTS_ALLOW_TEST_MODE=false`, Tizim → To'lov tizimlari'da Click va Payme **Faol** va **Ishchi**.
   `php artisan app:check-production` ogohlantirish bermasin.
2. Sinov ovqatxonasida 1 000 so'mlik taom bilan Payme orqali buyurtma bering, to'lang.
3. Tekshiring: buyurtma «to'langan»; Payme kabinetida tranzaksiya bor; summa bir xil (so'm va tiyin to'g'ri).
4. **Qaytarish (Payme)**: buyurtma hali yetkazilmagan bo'lsa — Payme kabinetida to'lovni bekor qiling; Payme serverga
   `CancelTransaction` yuboradi, buyurtma «to'lanmagan»ga qaytadi. Yetkazilgan va ovqatxonaga hisoblangan buyurtma
   uchun avval boshqaruv markazida qaytarish so'rovini tasdiqlang (hisob-kitob qaytariladi), keyin Payme kabinetida
   bekor qiling — aks holda Payme rad javobi (`-31007`) oladi va pul ikki marta qaytmaydi.
5. Xuddi shuni Click bilan qiling. **Click qaytarishi** serverga xabar bermaydi: avval boshqaruv markazida qaytarishni
   tasdiqlang («qo'lda qaytarish» deb belgilanadi), keyin Click kabinetida to'lovni qaytaring (Click: faqat joriy oy
   to'lovlari, faqat onlayn karta).
6. Kassadagi QR uchun: sinov ovqatxonasining o'z Click/Payme hisobi bilan bitta QR to'lov va kassadan qaytarish.

---

## 9. Orqaga qaytish (rollback)

Muammo bo'lsa:

1. Boshqaruv markazi → **Tizim → To'lov tizimlari** → muammoli tizimni **o'chiring** (Faol emas). Darhol: Mini App uni
   ko'rsatmaydi, yangi buyurtma va balans to'ldirish qabul qilinmaydi. Boshlangan to'lovlar odatdagidek yakunlanadi
   yoki bekor bo'ladi — pul yo'qolmaydi.
2. Kalitlarni **o'chirmang**: kalit bo'lmasa yo'ldagi to'lovlarning webhook'i rad etiladi.
3. Ovqatxonalar naqd va boshqa tizim bilan ishlashda davom etadi.
4. Sabab topilgach tizimni qayta yoqing; ovqatxonalar sozlamasi saqlanib qolgan.

---

## 10. Tekshiruv ro'yxati

| # | Ish | Kim | Tayyor |
|---|---|---|---|
| 1 | Click shartnomasi, servis ochildi | Ega | ☐ |
| 2 | Payme veb-kassa ochildi, `order_id` hisob maydoni | Ega | ☐ |
| 3 | Kabinetlarda webhook manzillari (3-bo'lim) | Ega | ☐ |
| 4 | Staging'da Payme sandbox 1 va 2-ssenariy yashil | Jamoa | ☐ |
| 5 | Staging'da Click test servis ssenariylari yashil | Jamoa | ☐ |
| 6 | Production `.env`: `CLICK_*`, `PAYME_*` (jonli), `PAYMENTS_ALLOW_TEST_MODE=false`, QR sandbox o'chiq, `LOG_LEVEL=warning` | Jamoa (serverda) | ☐ |
| 7 | Tizim → To'lov tizimlari: Faol, Ishchi; `app:check-production` toza | Jamoa | ☐ |
| 8 | IP ro'yxati Click/Payme'dan yozma so'raldi (qo'yish ixtiyoriy) | Ega | ☐ |
| 9 | Birinchi 1 000 so'mlik Payme to'lovi va qaytarish | Ega + jamoa | ☐ |
| 10 | Birinchi 1 000 so'mlik Click to'lovi va qaytarish | Ega + jamoa | ☐ |
| 11 | Chek (fiskal) bitta buyurtmada `recorded` | Jamoa | ☐ |
| 12 | O'z hisobli birinchi ovqatxona: kalitlarni o'zi kiritdi, bitta to'lov o'z hisobiga tushdi | Ovqatxona + jamoa | ☐ |
| 13 | Orqaga qaytish (tizimni o'chirish) bir marta sinab ko'rildi | Jamoa | ☐ |
| 14 | Paylov: 11-bo'limdagi ro'yxat | Ega + jamoa | ☐ |

---

## 11. Paylov

Paylov (AJ «OCTAGRAM», Markaziy bank litsenziyasi №40) — Mini App'dagi **onlayn** to'lov uchun uchinchi tizim, Click va
Payme yonida. Faqat onlayn buyurtma: kassadagi QR, kartani saqlash, split (sub-merchant), kartaga pul chiqarish (A2C)
va P2P ulanmagan. **Zaldagi naqd savdoga Paylov orqali chek chiqarilmaydi** (egasi qarori; "Paylov tekshiruvi"
hisobotiga qarang). Rasmiy hujjat: https://developer.paylov.uz (2026-yil 22-noyabrda tekshirildi).

### 11.1. Qanday ishlaydi

1. Mijoz Paylov'ni tanlaydi → server havola yasaydi: `https://my.paylov.uz/checkout/create/<base64>`, ichida
   `merchant_id`, summa **tiyinda** (`amount_in_tiyin=True`), `currency_id=860`, `return_url` (Mini App) va
   `account.order_id` = buyurtma UUID. Havola imzolanmagan, shuning uchun summani server o'zi tekshiradi.
2. Paylov serverga `transaction.check` yuboradi: buyurtma, summa (aniq tiyin, kasr rad etiladi) va holat tekshiriladi.
3. Mijoz to'laydi → Paylov `transaction.perform` yuboradi. Server to'lovni **«tasdiqlanmagan»** (`unconfirmed`) deb
   yozadi va darhol javob beradi. Paylov webhook'lari imzolanmagan (faqat login/parol), shuning uchun keyin server
   Paylov API'sidan (`getTransactions`) tranzaksiya holatini o'qiydi: `payed`, summa bir xil va `account.order_id`
   aynan shu buyurtma bo'lsa — buyurtma **bir marta** «to'langan» bo'ladi, aks holda to'lov tegilmaydi. Paylov
   tranzaksiyani **boshqa buyurtmaniki** deb ko'rsatsa, to'lov yopiladi (`rejected`, `transaction_of_another_order`,
   logda `critical`) — bu pul boshqa buyurtmaniki, shuning uchun bu yerdan qaytarilmaydi. `account` umuman bo'lmasa,
   to'lov hisoblanmaydi va «tasdiqlanmagan» bo'lib qayta tekshiriladi.
4. API javob bermasa yoki holat hali `pending` bo'lsa, to'lov «tasdiqlanmagan» bo'lib qoladi va har 5 daqiqada
   (`php artisan payments:paylov-reconcile`, scheduler) qayta tekshiriladi. Bunday buyurtma 30 daqiqalik «to'lanmagan»
   muddat bilan **bekor qilinmaydi**. Bir soatdan oshsa logda `critical` ogohlantirish chiqadi — Paylov biznes panelida
   tekshiring (ogohlantirish belgisi to'lov qatori qulflanib yoziladi: shu payt kelgan boshqa yozuv o'chmaydi). Paylov
   boshqa summani ko'rsatsa — `mismatch`: to'lov buyurtmaga **hech qachon** hisoblanmaydi va pul ichida qolib
   «kutilmoqda» bo'lib turmaydi: server `payment/cancel` bilan uni **to'liq qaytaradi** (to'lovda
   `refused: amount_mismatch`), logda `critical`. Paylov qaytarishni rad etsa — «qo'lda qaytarish» belgisi (7-band).
5. Paylov pulni to'langan yoki bekor qilingan buyurtma uchun olgan bo'lsa (javobimiz `+1` bo'lsa ham), to'lov yoziladi
   va `payment/cancel` orqali **to'liq qaytariladi** — buyurtmaga hisoblanmaydi.
6. Chek: ovqatxonada fiskal rejim yoqilgan bo'lsa, to'lov **tasdiqlangandan keyin** `fiscalization/register`
   yuboriladi (taomlar, MXIK, qadoq kodi, QQS, ovqatxona STIR/JShShIR; yetkazish alohida qator). Natija hamkor paneli →
   To'lovlar'da (`recorded`, `blocked`, `failed`).
7. Qaytarish: **faqat to'liq** (Paylov `payment/cancel` summa olmaydi). To'langan buyurtma bekor qilinsa yoki
   boshqaruv markazida to'liq qaytarish tasdiqlansa, server o'zi `payment/cancel` chaqiradi. «Qo'lda qaytarish» belgisi
   qo'yilishidan **oldin** server har doim Paylov'dan tranzaksiya holatini o'qiydi (oldingi `payment/cancel` o'tgan,
   lekin javobi yo'qolgan bo'lishi mumkin, API orqali bekor qilishda webhook kelmaydi): `cancel`/`refunded` bo'lsa —
   qaytarilgan deb yoziladi; holatni o'qib bo'lmasa — belgi qo'yilmaydi, keyinroq yana so'raladi (12 urinishdan keyin
   logda bir marta `critical`); faqat Paylov pul hali o'zida (`payed`) desa — «qo'lda qaytarish» (Paylov biznes
   panelidan qaytariladi). Shu tufayli pul ikki marta qaytarilmaydi. **Qisman qaytarish so'rovi rad etiladi**
   («Paylov faqat to'liq qaytarishni qo'llaydi...»).
8. `transaction.cancel` webhook'i (Paylov panelidan yoki xodimidan bekor qilinganda, 3 martagacha takrorlanadi) faqat
   login/parol bilan keladi, imzosi yo'q — o'z Paylov hisobli ovqatxona egasi o'z callback parolini biladi. Shuning
   uchun server uni **Paylov API'sidan tekshiradi** (buyurtma to'langan hisob bilan `getTransactions`): Paylov
   `cancel`/`refunded` desagina bir marta yoziladi (yetkazilgan buyurtma «qaytarilgan», komissiya va hisob-kitob
   qaytariladi). Paylov `payed` desa — e'tiborsiz qoldiriladi (logda ogohlantirish). Paylov javob bermasa — to'lovda
   `cancel_reported_at` qoladi va har 5 daqiqalik qayta tekshiruv uni yana so'raydi (bir soatdan oshsa logda bir marta
   `critical`). Webhook'ga har doim 2xx javob beriladi.
9. Qaytarish cheki Paylov'da avtomatik bekor qilinadi; bo'lmasa server `fiscalization/refund` bilan so'raydi. Pul chek
   ro'yxatga olinayotgan paytda qaytarilsa ham qaytarish cheki albatta so'raladi (chek holati to'lov qatori qulflanib
   yoziladi, shu payt yozilgan qaytarish so'rovi o'chmaydi).

### 11.2. Paylov'dan nima olish kerak (egasi)

Paylov ochiq ro'yxatdan o'tish bermaydi: shartnoma va hisobni Paylov xodimi ochadi.

| # | Nima | Izoh |
|---|---|---|
| 1 | Shartnoma (RIZQIM nomidan) va **bir martalik onboarding token** | token bilan `username`/`password` o'rnatiladi, javobda `consumer_key` va `consumer_secret` keladi (**secret faqat bir marta ko'rsatiladi**) |
| 2 | Hisobni **faollashtirish** | faollashtirilmaguncha token so'rovi `this account not activated` qaytaradi |
| 3 | `merchant_id` (UUID) | to'lov havolasi uchun |
| 4 | **BASE_URL** (API manzili) | hujjatda faqat misolda `gw2.paylov.uz`; Paylov aniq manzilni yozma aytsin |
| 5 | Bizning server IP'sini Paylov'ning IP oq ro'yxatiga qo'shish | aks holda `IP address not in whitelist` |
| 6 | Fiskal chek ruxsati va har bir ovqatxona STIR'ining OFD katalogida komitent sifatida faol shartnomasi | aks holda `ofd_commitent_or_subcommission_not_active`, chek `failed` |
| 7 | Paylov webhook yuboradigan IP manzillar (yozma) | ixtiyoriy nginx cheklovi uchun |
| 8 | Narx (komissiya), pul tushish muddati | e'lon qilinmagan |

### 11.3. Paylov'ga nima berish kerak

| Nima | Qiymat |
|---|---|
| Callback URL (Checkout: `transaction.check`, `transaction.perform`) | `https://api.rizqim.uz/api/v1/payments/paylov/webhook` |
| Cancel webhook URL (`transaction.cancel`) | `https://api.rizqim.uz/api/v1/payments/paylov/webhook` (o'sha manzil) |
| Callback login va parol (Basic Auth) | o'zingiz yarating: uzun tasodifiy qiymat (masalan `openssl rand -base64 32`; o'z hisobli ovqatxonaning callback paroli hamkor panelida **kamida 16 belgi**, qisqasi 422 bilan rad etiladi); serverga `PAYLOV_CALLBACK_LOGIN` / `PAYLOV_CALLBACK_PASSWORD` sifatida yozing va Paylov'ga **xavfsiz kanal** orqali bering. Hujjatda bu "Merchant's API login/password" deb atalgan — Paylov API login/paroli bilan bir xil bo'lishi kerakmi, so'rang; bir xil bo'lsa, ikkala juftga bir xil qiymat yozing |
| Account maydoni | `order_id` (matn, UUID, 36 belgi) |
| `return_url` | Mini App manzili (`TELEGRAM_WEBAPP_URL`), server o'zi qo'yadi |

### 11.4. Server qiymatlari

| Env nomi | Qayerdan | Maxfiymi |
|---|---|---|
| `PAYLOV_MERCHANT_ID` | Paylov (merchant UUID) | yo'q |
| `PAYLOV_CONSUMER_KEY` | onboarding javobi | **ha** |
| `PAYLOV_CONSUMER_SECRET` | onboarding javobi (bir marta ko'rsatiladi) | **ha** |
| `PAYLOV_USERNAME` | onboarding'da o'zingiz o'rnatgan login | **ha** |
| `PAYLOV_PASSWORD` | onboarding'da o'rnatgan parol (8+ belgi, katta-kichik harf, raqam, belgi) | **ha** |
| `PAYLOV_CALLBACK_LOGIN` | o'zingiz yaratasiz (11.3) | **ha** |
| `PAYLOV_CALLBACK_PASSWORD` | o'zingiz yaratasiz (11.3) | **ha** |
| `PAYLOV_BASE_URL` | Paylov aytgan API manzili (standart `https://gw2.paylov.uz`) | yo'q |
| `PAYLOV_CHECKOUT_URL` | standart `https://my.paylov.uz/checkout/create/` | yo'q |

Kalitlarni Tizim → To'lov tizimlari → Paylov'ga ham yozish mumkin (bazada shifrlangan). **Yetti kalitning hammasi**
bo'lmasa, Mini App Paylov'ni ko'rsatmaydi va buyurtma 422 `payment_provider_unconfigured` bilan rad etiladi (Click/Payme
dan farqi: kalitsiz to'lovni tasdiqlab bo'lmaydi). `php artisan app:check-production` har bir yetishmagan qiymatni
nomi bilan aytadi. Mavjud serverda Paylov qatori bo'lmasa: `php artisan db:seed --class=PaymentProviderSeeder` (mavjud
kalitlar va sozlamalar o'zgarmaydi).

O'z hisobli ovqatxona (`own_account`) hamkor panelida `payment_settings.paylov` ga yetti qiymatni o'zi kiritadi; hech
biri qaytarib ko'rsatilmaydi (`has_consumer_secret` kabi belgilar). Bittasi yetishmasa, buyurtma RIZQIM hisobiga
to'lanadi (Click/Payme kabi). Ovqatxona o'z Paylov hisobida ham callback URL'ni 11.3'dagidek yozadi.

### 11.5. Sinov (sandbox yo'q)

Paylov'da ochiq sinov muhiti **yo'q** (hujjatda faqat production manzillar). Shuning uchun:

1. **Staging** (`APP_ENV` production emas): Tizim → To'lov tizimlari → Paylov → rejim **Sinov**. Bu holatda server
   Paylov API'siga umuman so'rov yubormaydi: soxta drayver yozilgan to'lovni o'z summasi bilan `payed` deb javob beradi,
   qaytarish va chek ham soxta. Webhook'larni curl bilan yuborib sinang (Basic Auth bilan):
   `transaction.check` → `{"status":"0"}`, noto'g'ri summa → `5`, noma'lum buyurtma → `303`, noto'g'ri parol → HTTP 401;
   `transaction.perform` → buyurtma «to'langan»; ikkinchi marta → o'sha javob, ikki marta hisoblanmaydi;
   `transaction.cancel` → soxta drayver to'lovni doim `payed` deb ko'rsatadi, shuning uchun webhook orqali bekor
   qilish Sinov rejimida **qo'llanmaydi** (8-band: tekshiruvdan o'tmaydi); bekor qilish/qaytarishni boshqaruv markazidan
   sinang (soxta `payment/cancel` darhol `cancelled` beradi).
   Eslatma: Sinov rejimi platforma qatoriga qo'yiladi, lekin production bo'lmagan serverda **o'z Paylov hisobli
   ovqatxonalar** to'lovlari ham soxta drayver bilan ishlaydi (ataylab: staging'da hech qanday haqiqiy Paylov hisobiga
   so'rov ketmaydi). Productionda bu holat yo'q.
2. **Productionda Sinov rejimi yoqilmaydi** (`PAYMENTS_ALLOW_TEST_MODE=true` bo'lsa ham): server rad etadi, bazada
   qolgan «Sinov» ham productionda haqiqiy Paylov bilan ishlaydi.
3. Birinchi haqiqiy to'lov — productionda, o'z kartangiz bilan, **1 000 so'm**: to'lang → buyurtma «to'langan»
   (bir necha soniyada; «tasdiqlanmagan» qolsa logni ko'ring) → Paylov biznes panelida tranzaksiya va summa → boshqaruv
   markazida buyurtmani bekor qiling → pul kartaga qaytdi, to'lov «qaytarilgan». Keyin Paylov panelidan bekor qilishni
   ham bir marta sinang (webhook keladi).

### 11.6. Orqaga qaytish

9-bo'limdagidek: Tizim → To'lov tizimlari → Paylov → **o'chiring**. Yangi buyurtmalar qabul qilinmaydi, boshlangan
to'lovlar tasdiqlanadi yoki qaytariladi. Kalitlarni o'chirmang: «tasdiqlanmagan» to'lovlarni tekshirish va
qaytarish uchun kerak.

### 11.7. Tekshiruv ro'yxati

| # | Ish | Kim | Tayyor |
|---|---|---|---|
| 1 | Shartnoma, onboarding token, hisob faollashtirildi | Ega | ☐ |
| 2 | `merchant_id`, `consumer_key`, `consumer_secret`, `username`, `password` serverda | Jamoa | ☐ |
| 3 | Callback login/parol yaratildi, serverda va Paylov'da | Ega + jamoa | ☐ |
| 4 | Callback va cancel webhook URL Paylov'ga berildi (11.3) | Ega | ☐ |
| 5 | Server IP Paylov oq ro'yxatida; `PAYLOV_BASE_URL` yozma tasdiqlangan | Ega | ☐ |
| 6 | Staging'da Sinov rejimida webhook ssenariylari yashil | Jamoa | ☐ |
| 7 | `app:check-production` Paylov bo'yicha toza; scheduler ishlayapti (`payments:paylov-reconcile`) | Jamoa | ☐ |
| 8 | Birinchi 1 000 so'mlik to'lov, bekor qilish va qaytarish | Ega + jamoa | ☐ |
| 9 | Fiskal ruxsat va ovqatxona komitent shartnomasi; bitta chek `recorded` | Ega + jamoa | ☐ |

---

## 12. UzQR kassada

UzQR — Markaziy bankning yagona QR'i (01.07.2026 dan yuridik shaxslar uchun majburiy). Dinamik UzQR'ni **kassa**
yaratadi: Rizqim serveri UzQR operatorining API'siga **shu kassaning o'z API kaliti** bilan so'rov yuboradi. Pul
restoranning **o'z hisob raqamiga** tushadi (bizning hisobga emas). Fiskal chekni Rizqim chiqaradi (UzQR va bank chek
chiqarmaydi): chekda UzQR to'lovi **bank kartasi** to'lovi sifatida, RRN bilan yoziladi; chek fiskallashgach uning
havolasi UzQR'ga yuboriladi. Tadqiqot: `docs/research/reports/UzQR integratsiyasi.md`.

> **Holat (22.11.2026).** Drayver (`uzqr_operator`) yozilgan va sinovlarda ishlaydi, lekin operatorning API hujjati hali
> yo'q: so'rov va javob shakllari SetRetail10 va operator qo'llanmasidan olingan, qolgani **taxmin** va bitta klassda
> (`App\Domain\Pos\QrPayments\UzQr\UzQrOperatorProtocol`). Hujjat kelgach shu klass tuzatiladi va sinov konturida
> tekshiriladi. Ungacha haqiqiy restoranda yoqilmaydi.

### 12.1. Restoran uzqr.uz da nima qiladi (direktor, E-IMZO bilan)

1. Bankida hisob ochadi va bank orqali UzQR'ga ro'yxatdan o'tadi (UzQR bilan alohida shartnoma kerak emas).
2. **uzqr.uz** ga E-IMZO bilan kiradi → **QR boshqaruvi → Yangi QR** → hisobni, turini **Dinamik QR** tanlaydi, nomini
   kassaga qarab yozadi (`KASSA_1`, `KASSA_2`...). **Har kassaga alohida dinamik QR.**
3. **API boshqaruvi → Yangi API** → shu dinamik QR'ni tanlaydi → API kalit chiqadi. **Kalit faqat bir marta
   ko'rsatiladi**: oyna yopilishidan oldin nusxalaydi. Har kassaga alohida kalit (bitta kalit bitta QR bilan ishlaydi).
4. Kalitni **o'zi** Rizqim panelida kiritadi (12.2). Kalitni **chatga, Telegram'ga, xatga, skrinshotga yozmaydi va
   bizga yubormaydi** — jamoa kalitni hech qachon so'ramaydi.
5. Kalit yo'qolsa yoki kimdir ko'rgan bo'lsa: uzqr.uz da eski API'ni **o'chiradi**, yangisini yaratadi va Rizqim'da
   almashtiradi.
6. Dinamik QR bloklansa yangi to'lov QR'lari yaratilmaydi (kassa boshqa usullarni taklif qiladi).

### 12.2. Rizqim'da nima kiritiladi (egasi, panel)

1. **Fiskal sozlamalar**: do'kon **STIR**i (9 raqam) — UzQR'ga ro'yxatdan o'tgan yuridik shaxsniki; **UzQR drayveri** —
   `uzqr_operator` (`PUT /owner/fiscal/settings {fiscal_settings: {uzqr: {driver: "uzqr_operator"}}}`).
2. **POS sozlamalari → QR to'lov → UzQR kassalari**: har kassa (POS qurilma) qatoriga o'z API kalitini kiritadi →
   «Kalitni saqlash». Kalit shifrlangan holda saqlanadi va **qayta ko'rsatilmaydi** (faqat oxirgi 4 belgisi). Kalitsiz
   kassa UzQR'ni taklif qilmaydi, naqd/karta/Click/Payme ishlayveradi.
3. Faqat `settings.manage` ruxsati bor (egasi) kiritadi; har o'zgarish auditda (`devices.uzqr_key_changed`, kalitsiz).

### 12.3. Operatordan nima so'raladi (jamoa; tadqiqot 7A savollari)

- API texnik hujjati, xato kodlari ("UzQR Error Reference"), **sinov konturi manzili** va sinov kaliti,
  **production manzili**. Manzillar `https://` bo'lishi shart.
- Webhook bormi (hozir faqat so'rab turish), summa birligi (tiyin yoki so'm), QR muddatini o'zimiz belgilay olamizmi,
  qisman va bir necha marta qaytarish, qaytarish holatini bizning id bilan so'rash, chek havolasi formati.

### 12.4. Server qiymatlari

| Env | Qiymat |
|---|---|
| `UZQR_OPERATOR_URL` | operator bergan **production** manzili (`https://...`) |
| `UZQR_OPERATOR_TEST_URL` | sinov konturi manzili |
| `UZQR_OPERATOR_TEST` | `true` — sinov konturi. **Production**'da (`APP_ENV=production`, `PAYMENTS_ALLOW_TEST_MODE` yo'q) drayver bunda ishlamaydi; `UZQR_OPERATOR_URL` sinov manziliga teng bo'lsa ham ishlamaydi |

Kalitlar env'ga yozilmaydi: ular har kassada, bazada shifrlangan. Ish: `pos:expire-qr-payments` (har daqiqa) — muddati
o'tgan va yaqinda tugagan UzQR'larni operatordan qayta so'raydi, javobsiz qaytarishlarni tekshiradi.

### 12.5. Sinov va birinchi to'lov

1. Staging: `UZQR_OPERATOR_TEST=true`, sinov manzili, sinov kaliti bitta kassaga. QR yaratish, holat, bekor qilish,
   qaytarish va chek havolasini sinang (sinov konturida "QR topilmadi" bo'lishi mumkin — SetRetail10 ogohlantiradi).
2. Production, pilot restoran, **1 000 so'm**: kassada UzQR → bank ilovasida to'lang → schyot to'landi, chek chiqdi
   (to'lov shakli — karta, RRN bilan) → QR'da `receipt_link: sent` → uzqr.uz **Operatsiyalar tarixi**da to'lov →
   kassada 500 so'm qisman qaytarish → qolganini qaytarish → uzqr.uz **Qaytarishlar**ida ikkalasi.
3. Bankdan **ikki marta chek chiqmasligini** yozma tasdiqlang (tadqiqot, 3-bo'lim).

### 12.6. Orqaga qaytish

Fiskal sozlamalarda UzQR drayverini o'chiring (`driver: null`): kassalar UzQR'ni taklif qilmaydi. Kalitlarni
o'chirmang: to'langan QR'larni so'rash va qaytarish uchun kerak.

### 12.7. Tekshiruv ro'yxati

| # | Ish | Kim | Tayyor |
|---|---|---|---|
| 1 | Operatordan API hujjati, sinov va production manzillari | Ega | ☐ |
| 2 | `UzQrOperatorProtocol` hujjat bo'yicha tuzatildi, sinovlar yashil | Jamoa | ☐ |
| 3 | Pilot restoran: har kassaga dinamik QR + API kalit, kalitlarni o'zi kiritdi | Ovqatxona | ☐ |
| 4 | STIR va `uzqr_operator` drayveri fiskal sozlamalarda | Ega | ☐ |
| 5 | Birinchi 1 000 so'mlik to'lov, qisman va to'liq qaytarish, chek havolasi `sent` | Ega + jamoa | ☐ |
| 6 | Bankdan "ikki marta chek yo'q" yozma tasdig'i | Ega | ☐ |

