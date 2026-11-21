# Rasmlar (egasi beradi)

Bu papkaga dizayner/egasi tayyorlagan rasmlar qo'yiladi. Rasm bo'lmaguncha sahifada hozirgi SVG chizma ko'rinadi.

Qanday qo'shiladi:

1. Har bir rasm uchun ikki fayl: `<nom>.webp` (2x, jadvaldagi o'lcham) va `<nom>-1x.webp` (ikki barobar kichik).
   Aynan shu nomlar bilan shu papkaga qo'ying.
2. `site/site.config.js` ichida `photos` bo'limida shu nomni `true` qiling (`false` = SVG chizma).
3. Deploy. Fayl topilmasa yoki ochilmasa, sahifa avtomatik ravishda SVG chizmaga qaytadi.

Hozirgi holat: egasi bergan 6 ta rasm (2026-11, `rizqim_assets`: 01_phone_app, 02_uzbek_plov, 03_cafe_storefront,
04_delivery_scooter, 05_pos_devices, 06_business_dashboard_laptop) oq fonidan tozalanib (shaffof), qirqilib, shu
o'lchamlarga keltirilgan va yoqilgan. 07_chef ishlatilmadi (joriy dizaynda oshpaz bloki yo'q).

Format: WebP, shaffof fon (transparent), sRGB. O'lchamlar retina uchun 2x (sahifadagi eng katta o'lchamdan ikki
barobar). Proporsiya aynan shunday bo'lsin: rasm qat'iy o'lchamli qutiga `object-fit: contain` bilan joylanadi,
proporsiya boshqacha bo'lsa chetlarida bo'sh joy qoladi. Har bir fayl iloji boricha 250 KB dan kichik bo'lsin
(PNG dan WebP ga: squoosh.app yoki `cwebp -q 82`).

| Fayl (2x) | Qayerda | Piksel o'lchami: 2x / 1x | Proporsiya |
| --- | --- | --- | --- |
| `hero-phone.webp` | Bosh sahifa, hero: Telegram Mini App ekrani ko'rsatilgan telefon (butun telefon, qiyshaytirmasdan; burchakni CSS beradi) | 600 x 1114 / 300 x 557 | 280:520 |
| `hero-food.webp` | Bosh sahifa, hero (telefon versiyasi): telefon yonidagi taom (osh likopchada) | 480 x 400 / 240 x 200 | 6:5 |
| `storefront.webp` | Hamkor sahifasi hero; "Restoranlar uchun" va "Nima olasiz" dagi Marketplace kartochkasi: "Rizqim" yozuvli do'kon/kafe old tomoni | 1200 x 920 / 600 x 460 | 300:230 |
| `pos-devices.webp` | Bosh sahifa "POS kassa" bo'limi; "Restoranlar uchun" va "Nima olasiz" dagi POS kartochkasi: kassa, ofitsiant telefoni, oshxona ekrani | 1520 x 680 / 760 x 340 | 760:340 |
| `partner-panel.webp` | Bosh sahifa "Hamkor paneli" bo'limi: hamkor paneli ochilgan noutbuk | 880 x 580 / 440 x 290 | 440:290 |
| `scooter.webp` | Bosh sahifa oxiridagi to'q sariq banner (chap tomonda, bannerdan biroz tepaga chiqib turadi): yetkazib berish skuteri (yoki skuterdagi kuryer) | 600 x 500 / 300 x 250 | 240:200 |

Eslatma: rasmlarda o'ylab topilgan raqam, sharh yoki App Store/Google Play belgisi bo'lmasin (sayt qoidalari).
