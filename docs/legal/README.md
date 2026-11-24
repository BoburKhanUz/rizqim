# Maxfiylik siyosati va foydalanish shartlari: huquqiy asoslar

Sahifalar: `https://rizqim.uz/maxfiylik` va `https://rizqim.uz/shartlar` (versiya 1.0, kuchga kirgan sana 23.11.2026).
Matnlarning yagona manbasi: `infra/landing/site/i18n/uz.js` va `ru.js` (`privacy.*`, `terms.*`, `legal.*` kalitlari);
HTML ga `node infra/landing/tests/check.mjs --sync` bilan yoziladi. Kompaniya rekvizitlari: `infra/landing/site/site.config.js`.

Qonunlar 23.11.2026 da lex.uz dan tekshirildi. Bu eslatma yuridik xulosa emas: karta to'lovlarini kengaytirishdan
oldin yurist ko'rib chiqishi tavsiya etiladi (pastda).

## Qaysi bo'lim qaysi qonunga tayanadi

| Sahifa, bo'lim | Qonun va modda | Havola |
|---|---|---|
| Maxfiylik 1, 3 (rozilik, asoslar) | "Shaxsga doir ma'lumotlar to'g'risida" O'RQ-547 (02.07.2019): 18-modda (shartnomani bajarish uchun roziliksiz qayta ishlash), 21-modda (rozilik istalgan tasdiqlanadigan shaklda; voyaga yetmaganlar uchun ota-ona yozma roziligi) | https://lex.uz/docs/4396428 (rus: https://lex.uz/ru/docs/4396428) |
| Maxfiylik 2, 4 (tarkib, uchinchi shaxslar) | O'RQ-547: 31-modda (mulkdor va operator majburiyatlari: zarur va yetarli tarkib, uchinchi shaxsga berish haqida xabardor qilish, mas'ul shaxs) | o'sha |
| Maxfiylik 5 (Telegram, xorijiy xizmatlar) | O'RQ-547: 15-modda (transchegaraviy uzatish: adekvat himoyasiz davlatga faqat subyekt roziligi bilan), 27-1-modda | o'sha |
| Maxfiylik 6 (saqlash joyi va muddati) | O'RQ-547: 10, 17-moddalar (muddat maqsadga erishilguncha, so'ng yo'q qilish), 27-1-modda | o'sha |
| Maxfiylik 9 (huquqlar) | O'RQ-547: 30-modda (bilish, ma'lumot olish, rozilikni qaytarib olish, to'xtatish talabi, organ yoki sudga shikoyat) | o'sha |
| Maxfiylik 10 (restoran mehmonlari) | O'RQ-547: mulkdor va operator tushunchalari (restoran mulkdor, RIZQIM uning topshirig'i bilan ishlaydi) | o'sha |
| Shartlar 1 (oferta, aksept) | Fuqarolik kodeksi (1-qism): 367 (oferta), 369 (ommaviy oferta), 370 (aksept) | https://lex.uz/docs/111181 |
| Shartlar 1, 3, 16 (savdo maydonchasi, til, o'zgartirish) | "Elektron tijorat to'g'risida" O'RQ-792 (29.09.2022): 13-modda (maydoncha operatori qoidalarni ko'rsatadi, o'zgartirishni kamida 30 kun oldin e'lon qiladi), 14-15, 19-moddalar (elektron shartnoma, aksept harakat bilan), 21-modda (davlat tilida) | https://lex.uz/docs/6213428 (rus: https://lex.uz/ru/docs/6213428) |
| Shartlar 3, 5 (sotuvchi haqida ma'lumot, narx) | "Iste'molchilarning huquqlarini himoya qilish to'g'risida" (26.04.1996, 221-I): 5-modda (sotuvchi nomi, manzili), 8-modda (ko'rsatilgan tovar oferta), 28-1-modda (axborot tizimlari orqali savdo: sotuvchi nomi, manzili, shikoyat tartibi, narx va yetkazish narxi shartnomadan oldin); O'RQ-792: 16-modda | https://lex.uz/docs/14643 (rus: https://lex.uz/ru/docs/14643) |
| Shartlar 7 (to'lov, chek) | "To'lovlar va to'lov tizimlari to'g'risida" O'RQ-578 (01.11.2019): 13-15-moddalar (to'lov xizmatlari faqat Markaziy bank litsenziyasi bilan); iste'molchilar qonuni 10-modda (har bir sotuvga chek); O'RQ-792: 23-modda (elektron chek xaridorga yuboriladi); VM qarori №943 (23.11.2019, onlayn-NKM va virtual kassa) | https://lex.uz/docs/4575788, https://lex.uz/docs/4603329 |
| Shartlar 9 (bekor qilish, qaytarish) | Iste'molchilar qonuni: 13-14-moddalar (sifatsiz tovar), 18-modda (sifatli tovarni almashtirish huquqi oziq-ovqatga tatbiq etilmaydi), 28-1-modda (qaytarish xarajati sotuvchida, kechiktirilsa kuniga 1% penya); O'RQ-792: 27-modda (pul to'langan usulga qaytariladi) | yuqoridagi havolalar |
| Shartlar 13-14 (javobgarlik, nizolar) | Iste'molchilar qonuni: 21-modda (iste'molchi huquqini cheklovchi shart haqiqiy emas), 27, 29-moddalar (da'vo iste'molchi yashash joyida ham beriladi) | https://lex.uz/docs/14643 |

### Muhim: 2026-yildagi o'zgarishlar

- **27-1-modda (ma'lumotlarni lokalizatsiya qilish) 26.03.2026 dagi O'RQ-1125 bilan o'zgargan** (27.03.2026 dan).
  Endi O'zbekistonda saqlash **majburiy** faqat biometrik, genetik ma'lumotlar va O'zbekistondagi telekom operatorlari
  foydalanuvchilari ma'lumotlari uchun. Boshqa ma'lumotlarni chet elda saqlash shartlar bilan ruxsat (adekvat davlat,
  standart shartnoma shartlari yoki tasdiqlangan xalqaro standart). RIZQIM serverlari O'zbekistonda, shuning uchun bu
  talabdan qat'i nazar mos keladi; sahifada "ma'lumotlar O'zbekistondagi serverlarda saqlanadi" deb yozilgan.
- **20-modda (davlat reyestriga ro'yxatdan o'tkazish) ham O'RQ-1125 bilan o'zgargan**: reyestrga endi majburiy
  O'zbekistonda saqlanadigan bazalar (27-1-modda 2-qismi) kiritiladi, xabardor qilish tartibida (vakolatli organ:
  Davlat personallashtirish markazi). RIZQIM biometrik yoki genetik ma'lumot yig'maydi va telekom operatori emas,
  shuning uchun ro'yxatdan o'tish **majburiy emasga o'xshaydi**, lekin buni yurist yoki Personallashtirish markazi bilan
  tasdiqlang.
- O'RQ-792 (elektron tijorat) da 12.12.2026 dan kuchga kiradigan o'zgarishlar bor (2, 3, 8, 9, 11-13, 16, 23, 24,
  27-moddalar). Kuchga kirgach sahifalarni qayta solishtiring.

## Sahifalar mahsulotning haqiqiy ishiga tayanadi

Kod va hujjatlardan tekshirilgan: Telegram orqali kirish (ID, ism, username, rasm havolasi), telefon (Telegram kontakti
yoki Eskiz SMS, kod 5 daqiqa, faqat xesh), manzillar va xaritadagi nuqta (faqat tugma bosilganda bir marta), buyurtmalar,
Click/Payme/Paylov (karta ma'lumoti RIZQIM'ga kelmaydi), kassada UzQR, fiskal chek (restoran STIR bilan), keshbek, promo,
do'st taklif qilish, baholar, shikoyatlar, xabar sozlamalari, hamkor arizasi (IP bilan), xodimlar (PIN xeshi,
qurilmalar), harakatlar jurnali (IP bilan, o'zgarmaydi). Restoran xodimlari va kuryerlar Telegram'da mijoz ismi,
telefoni va manzilini oladi. Xarita plitkalari OpenStreetMap'dan, geokodlash Nominatim'dan, Sentry ixtiyoriy va
tozalangan. Saqlash muddatlari `docs/OPERATIONS.md` dan: zaxira 14 kun (PITR S3 da ~28 kun), `audit_logs` bazada 7 oy
keyin arxiv, `notification_logs` 6 oy, `fiscal_receipts` bazada 13 oy keyin arxiv, Mini App tokeni 30 kun. Mijoz
o'zi faqat "Yangi" va to'lanmagan buyurtmani bekor qila oladi; to'lanmagan onlayn buyurtma 30 daqiqada bekor bo'ladi;
olib ketilmagan buyurtmadan keyin naqd to'lov o'chadi (`config/orders.php`). Alkogol va tamaki sotish imkoniyati yo'q.
Kuryer ilovasi va mijoz mobil ilovasi hali yo'q, shuning uchun kuryer GPS kuzatuvi va push xabarlar sahifalarda yo'q:
ular ishga tushsa matnga qo'shing.

## Egasi nima qilishi kerak

1. **Rekvizitlarni to'ldiring** (`infra/landing/site/site.config.js`, so'ng `docker compose up -d --build landing`):
   `legal.name` (yuridik nomi), `legal.stir`, `legal.address.uz` va `legal.address.ru`, `legal.email`,
   `phone` va `phoneHref` (masalan `tel:+998...`), `telegramHandle` va `telegramUrl`. To'ldirilmagan maydon sahifada
   "[egasi kiritadi]" bo'lib turadi: sahifalarni rasman e'lon qilishdan oldin hammasini to'ldiring. Telefon va Telegram
   saytning pastki qismida ham chiqadi.
2. **Shaxsga doir ma'lumotlar bo'yicha mas'ul shaxsni** tayinlang (O'RQ-547, 31-modda) va `legal.email` ni u o'qiydigan
   manzil qiling. Murojaatlarga javob berish tartibini (kim, necha kunda) ichki hujjatda belgilang.
3. **Davlat reyestri**: ro'yxatdan o'tish shart emasligini Personallashtirish markazi yoki yurist bilan tasdiqlang
   (yuqoridagi 20-modda izohi).
4. **To'lov tizimlari bilan shartnomalar** (Click, Payme, Paylov; restoranlar uchun UzQR) imzolangan bo'lsin;
   ularning ofertalaridagi talablar (masalan, saytda oferta va rekvizitlar bo'lishi) shu sahifalar bilan bajariladi.
5. **Hamkorlik shartnomasi** (restoranlar bilan, komissiya va POS obunasi) yozma shaklda tayyor bo'lsin: shartlarning
   15-bo'limi unga havola qiladi. Unda RIZQIM restoran mehmonlari ma'lumotlarini restoran topshirig'i bilan qayta
   ishlashi (operator sifatida) yozilsin.
6. **Zaxira nusxalar uchun S3 provayderi**: hujjatlarda misol sifatida AWS, Cloudflare R2, Backblaze tilga olingan.
   Sahifada "zaxira nusxalar O'zbekistondagi serverlarda" deb yozilgan: O'zbekistondagi S3-mos xotirani tanlang yoki
   chet el provayderi tanlansa, 27-1-modda 3-qismi shartlarini bajaring va siyosatning 5-6-bo'limlarini yangilang.
7. **Yurist ko'rigi** tavsiya etiladi, ayniqsa karta to'lovlari hajmi oshishidan oldin (pastdagi noaniq joylar).

## Noaniq joylar (yurist bilan hal qiling)

- **Pulni RIZQIM hisobiga qabul qilish.** "Platforma hisobi" rejimida mijoz puli RIZQIM'ning Click/Payme kassasiga
  tushadi va keyin restoranga o'tkaziladi. O'RQ-578 ning 14-moddasi uchinchi shaxslar foydasiga to'lov qabul qilishni
  to'lov xizmati sifatida sanaydi; RIZQIM litsenziyasiz bo'lgani uchun bu sxema komissioner (agent) shartnomasi bilan
  rasmiylashtirilishi va soliq oqibatlari (QQS, kim sotuvchi) tekshirilishi kerak. Sahifada "RIZQIM to'lov tashkiloti
  emas, pul shartnoma asosida restoranga o'tkaziladi" deb yozilgan.
- **Fiskal chek mijozga yetkazilishi.** O'RQ-792 23-moddasi elektron chekni xaridorning telefoniga, e-pochtasiga yoki
  shaxsiy kabinetiga yuborishni talab qiladi. **Wave P dan qoplangan:** Mini App'dagi buyurtma sahifasi va tarixida
  "Fiskal chek" va "Qaytarish cheki" tugmalari ofd.soliq.uz havolasini ochadi (shaxsiy kabinet), chek ro'yxatga
  olinganda Telegram bot havolani tugma bilan yuboradi (`docs/API.md`, "Mijozga chek va sotuvchi ma'lumoti"). Qolgani:
  Click qaytarish cheki hali so'ralmaydi; restoran boshqa kassada urgan chek RIZQIM'da ko'rinmaydi; e-pochta va
  PDF keyingi bosqichda. Sahifadagi "to'lov tizimi ilovasida yoki so'rov bo'yicha" matnini yangilash kerak.
- **Restoran haqida ma'lumot** (iste'molchilar qonuni 28-1-modda). **Wave P dan qoplangan:** restoran sahifasida va
  to'lovdan oldin "Sotuvchi" bloki: yuridik nomi, STIR, yuridik manzil, telefon, shikoyat tartibi (ilovadagi
  "Shikoyatlar" bo'limi va restoranning shikoyat aloqasi); buyurtmada o'sha paytdagi sotuvchi nusxasi. Yuridik manzil
  va shikoyat aloqasini ega hamkor panelida to'ldiradi; bo'sh maydon ko'rsatilmaydi.
- **Harakatlar jurnali arxivi muddatsiz saqlanadi** (`docs/OPERATIONS.md` 20-bo'lim). O'RQ-547 10-moddasi muddatni
  maqsad bilan cheklaydi: arxiv uchun aniq muddat (masalan, da'vo muddati) belgilash tavsiya etiladi.
- **Telegram orqali transchegaraviy uzatish** subyekt roziligiga asoslangan (15-modda). Vazirlar Mahkamasi adekvat
  davlatlar ro'yxatini tasdiqlasa, asosni qayta ko'ring.
- **Hisobni o'chirish** hozir faqat so'rov orqali qo'lda bajariladi (ilovada tugma yo'q). Qonun elektron so'rov
  imkonini talab qiladi (31-modda): e-pochta yoki Telegram orqali so'rov shu talabni qoplaydi, lekin ichki tartib kerak.
- **Javob muddatlari** sahifada "qonunchilikda belgilangan muddatlarda" deb yozilgan; aniq kun soni yurist bilan
  belgilansa, matnga qo'shilsin.
