# Click va Payme'ni jonli rejimga ulash

Bu hujjat egasi uchun: Click va Payme'ni haqiqiy pul bilan ishga tushirishdan oldin nima qilinadi, qaysi qiymat
qayerga yoziladi, qanday sinaladi va muammo bo'lsa qanday o'chiriladi.

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
