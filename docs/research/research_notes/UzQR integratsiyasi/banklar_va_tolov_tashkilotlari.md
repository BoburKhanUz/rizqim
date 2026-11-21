# Banklar va to'lov tashkilotlari: UzQR bo'yicha nima topildi

Tekshirilgan sana: 21.11.2026. Muhim: dinamik UzQR API kaliti bank emas, **uzqr.uz kabinetida** yaratiladi (qarang: dinamik_qr_texnik_dalillar.md). Shuning uchun bank faqat restoranni ro'yxatdan o'tkazadi, hisob ochadi va tarifni belgilaydi. Bankning o'z alohida UzQR API'si borligi hech bir bankda tasdiqlanmadi.

## Banklar

| Bank | UzQR ekvayring | Tarif | Pul tushishi | Onlayn ulanish | Bizga raqibmi | Manba |
|---|---|---|---|---|---|---|
| KDB Bank Uzbekistan | Ha, ommaviy oferta (statik va dinamik). Hujjatlar: ariza, ta'sis hujjatlari, vakil hujjati, shu bankdagi hisob rekvizitlari, KYC. QR 3 bank kunida | Ofertada "tarif jadvali bo'yicha", foiz yozilmagan [NOT FOUND] | Yakuniy klirengdan keyin 1 ish kuni | [NOT FOUND] | Yo'q | [PRIMARY] https://kdb.uz/public/storage/sample-forms/July2026/syPsK5NAoOXrxeuROqzL.pdf ; info@kdb.uz |
| Ipak Yo'li | Ha; sahifa dinamik QR va "API integratsiya bilan POS yoki biznes dasturga ulash"ni aytadi; "fiskal cheklar sotuvchi nomidan avtomatik yaratiladi va Telegram bot orqali yuboriladi" | "0,65% gacha" (sahifa, 21.11.2026); iyulda 0,25% e'lon qilingan [MEDIA] | 24 soat ichida | Ariza (mavjud mijoz), yangi mijozga ta'sis hujjatlari | Yo'q | [PRIMARY] https://en.ipakyulibank.uz/legal/payments/qr-pay |
| Ipoteka bank | Ha; kabinet orqali API integratsiya | 0,65%, ulash bepul | To'g'ridan-to'g'ri sotuvchi hisobiga | "minimal hujjatlar" | Yo'q | [PRIMARY] https://www.ipotekabank.uz/en/business/acquiring/qr-code/ (28.10.2026) |
| Kapitalbank | Ha; QR'ni Uzum Business Kapitalbank ilovasida filialga bormay rasmiylashtirish | [NOT FOUND] (topilgan PDF eski Tez QR 2%) | [NOT FOUND] | Ha, ilovada | Yo'q (Uzum guruhi; Uzum o'z QR mahsuloti bilan QR'da raqobatchi, POS'da emas) | [MEDIA] https://www.spot.uz/oz/2026/07/02/kapitalbank-online/ |
| TBC Bank | Ha; TBC Biznes ilovasida yagona QR buyurtma qilish; SoftPOS ham bor | [NOT FOUND] | [NOT FOUND] | Ha (ilova, 45 000+ tadbirkor) | Restoran POS'i topilmadi | [MEDIA, reklama] https://www.spot.uz/ru/2026/11/17/tbc/ |
| Biznesni rivojlantirish banki (BRB) | Ha; Biznes 24/7 ilovasida ariza → QR yaratish → yuklab olish | [NOT FOUND] | [NOT FOUND] | Ha | Yo'q | [MEDIA] https://uza.uz/ar/posts/uzqr-teper-mozhno-poluchit-onlayn_897253 |
| Hamkorbank | QR-xizmat shartlari umumiy shartnomada bor, UzQR tarifi ko'rinmadi | [NOT FOUND] | [NOT FOUND] | [NOT FOUND] | Yo'q | hamkorbank.uz shartnoma PDF (ko'rilmadi to'liq) |
| Anorbank, Asaka, Agrobank, Xalq banki, NBU, Infinbank, Davr, Orient Finans | Qonun bo'yicha barcha tijorat banklari ekvayer bo'lishi mumkin; aniq UzQR sahifasi topilmadi | [NOT FOUND] | [NOT FOUND] | [NOT FOUND] | Yo'q | — |

## To'lov tashkilotlari

| Tashkilot | UzQR'dagi roli | O'z QR / API | Raqibmi | Manba |
|---|---|---|---|---|
| Click | Xaridor tomoni (tashabbuskor ilova): UzQR'ni skanerlab to'laydi | O'z dinamik QR (SHOP API) — RIZQIM'da allaqachon bor | Smart kassa bilan hamkor; o'zi restoran POS sotishi topilmadi | [MEDIA] spot.uz 02.07.2026 |
| Payme | Tashabbuskor ilova; foydalanuvchilar uchun UzQR komissiyasiz | O'z Merchant API — RIZQIM'da bor | Yo'q | [MEDIA] kun.uz 03.07.2026 |
| Uzum Bank | Tashabbuskor ilova | **Dynamic QR API** (developer.uzumbank.uz/dynamicqr): faqat Uzum Bank ilovasi bilan to'lanadi; create, status-by-order, cancel-order, fiscal (chek havolasi), reversal (faqat to'liq: service_id + payment_id, summa yo'q); auth `merchant_service_user_id:sha1(timestamp+secret_key):timestamp`; prod mobile.apelsin.uz; credentials menejer orqali. UzQR emas | QR'da raqobatchi, POS emas | [PRIMARY] developer.uzumbank.uz bundle ichidagi OpenAPI |
| Multicard (Rahmat) | UzQR ishtirokchisi ekani [NOT FOUND] | O'z Rahmat QR: restoranlar uchun sotuvchi komissiyasi 1,5% | **Raqib** (Rahmat kassa / POS) | [MEDIA] spot.uz 02.07.2026; avvalgi hisobot |
| Paynet (Xolis) | YaTT va o'zini o'zi band qilganlar platformasi; ofertasida "yagona QR bo'yicha 0,65%" | Xolis statik QR | Qisman (chek platformasi, restoran POS emas) | [MEDIA] |
| Atmos, OCTO, Paylov | UzQR ishtirokchisi ekani [NOT FOUND] | Onlayn to'lov API'lari | Yo'q | avvalgi hisobotlar |
| Uzcard (Tez QR) | UzQR'dan oldingi milliy QR; terminal yaratadi | — | Yo'q | [MEDIA] spot.uz 02.07.2026 |

"Ekvayer agenti" (to'lov tashkiloti restoranni UzQR'ga ulab beradi) qoidalarda bor (21–26-bandlar), lekin shu rolda ishlayotgan biror to'lov tashkiloti topilmadi [NOT FOUND].
