# Dinamik UzQR: kassa dasturi qanday ulanadi (texnik dalillar)

Tekshirilgan sana: 21.11.2026.

## 1. UzQR shaxsiy kabineti qo'llanmasi (v1.1, KDB Bank saytida, iyul 2026)

Manba: https://www.kdb.uz/storage/sample-forms/July2026/9FsQjQVcAbce8wQ9loEX.pdf [PRIMARY — operator qo'llanmasining nusxasi; MB saytidagi ruscha PDF faqat rasm, mazmuni bir xil ko'rinadi: https://cbu.uz/upload/medialibrary/900/vk02r2kr9ol8t6mi0o0w6t098gbxuspa/ru_uzqr_.pdf]

- "Connect through the servicing bank without concluding a separate agreement with UzQR". Pul QR'ga bog'langan hisob raqamga tushadi.
- POS ulash: (1) dinamik QR yaratish, (2) API kalit yaratish, (3) kalitni QR'ga bog'lash, (4) **kalitni kassa dasturi yetkazib beruvchisiga berish**, (5) birga sozlash, (6) sinov to'lovi.
- Har API kalit faqat o'z QR'i bilan ishlaydi. Har kassaga alohida dinamik QR va alohida API kalit tavsiya qilinadi.
- API kalit bir marta ko'rsatiladi; yo'qolsa yangisini yaratib, kassa dasturiga qayta beriladi.
- Kirish: E-IMZO (elektron imzo).
- Dinamik QR bloklansa: yangi to'lov QR'lari yaratilmaydi, to'lanmaganlari o'chiriladi, API kalitlar saqlanadi.
- Telegram bot xabarnomasi faqat statik QR uchun.
- "Operatsiyalar tarixi": to'lovlar va **qaytarishlar**. Qaytarish summasini kiritish mumkin, asl summadan oshmaydi (demak qisman qaytarish bor). Qaytarish "bir necha soniyadan bir necha soatgacha" bajariladi. Qidiruv: to'lov raqami, invoice raqami; eng uzun davr 35 kun; CSV eksport.
- Xato kodlari "UzQR Error Reference" hujjatida [NOT FOUND — hujjat topilmadi].

## 2. SetRetail10 (CSI / Crystals) — UzQR plagini hujjati

Manba: https://crystals.atlassian.net/wiki/spaces/SR10SUPPORT/pages/6492127238 (yaratilgan 13.07.2026, yangilangan 14.07.2026; versiya 10.5.1.0). [PRIMARY — bu boshqa kassa dasturi sotuvchisining o'z hujjati; UzQR'ning o'z API hujjati emas]

- Kassa UzQR'ga **o'zi** so'rov yuboradi: dinamik QR yaratish, to'lov holatini so'rash (polling), to'lanmagan invoice'ni bekor qilish, qaytarish yaratish va qaytarish holatini so'rash.
- **Fiskal chekdan keyin kassa UzQR'ga fiskal chek havolasini yuboradi** (sotuv va qaytarish uchun).
- Ulanish sozlamalari: "Адрес подключения" (prod uchun UzQR bergan manzil), **INN (9 xonali STIR)**, **API_key** (yuridik shaxs yoki sinov konturi uchun berilgan). Standart: ulanish 3 s, javob 20 s, 5 marta qayta urinish; holat so'rash oralig'i 3–10 s.
- "Тестовая среда UzQR предназначена для технической проверки методов API" — **sinov muhiti bor**, lekin unda haqiqiy to'lov o'tmasligi mumkin ("QR не найден").
- Qaytarish: butun chek yoki pozitsiyalar bo'yicha (demak qisman); faqat sotuv bo'lgan kassada; asos chekisiz qaytarish yo'q.
- Aralash to'lov: chekning bir qismini UzQR bilan to'lash mumkin.
- Chek eksportidagi maydonlar: bank.id, ref.number (RRN), pay_id (UzQR tomonidagi operatsiya id), invoice_id (kassa tomonidagi id).
- Fiskal modulga: **UzQR to'lovi bank kartasi to'loviga tenglashtiriladi**; CardType, CardNumber, PPTID maydonlari processing javobidan to'ldiriladi; QRPaymentID va QRPaymentProvider yuborish shart emas.
- Webhook yoki callback haqida hech narsa yo'q — faqat polling [NOT FOUND webhook].
- Bog'liq sahifa (yangilangan 17.11.2026): statik "yagona QR qo'lda tasdiqlash bilan" rejimi ham bor (xaridor summani o'zi kiritadi, kassir tasdiqlaydi).

## 3. Boshqa kassa sotuvchilari (UzQR ularda ishlaydi)

- R-Keeper (diler BUSOFT): UZQR integratsiyasi; ulash 200 000 so'm, har kassa oyiga 50 000 so'm, 0,65%; pul restoranning hisobiga; to'lovdan keyin R-Keeper chekni o'zi fiskallashtiradi. 19.08.2026. [PRIMARY vendor sahifasi] https://rkeeper.uz/stati/post/uzqr-i-r-keeper-kogda-oplata-stanovitsya-chastyu-avtomatizacii-restorana
- REGOS VCR: dinamik UzQR ekranga chiqaradi, chekdan oldin UzQR va fiskal ma'lumotni tekshiradi. [PRIMARY vendor] https://regos.uz/ru/product/regos-vcr/info
- buxgalter.uz (30.06.2026): uzqr.uz ga E-IMZO bilan kirish, dinamik QR, API kalit, kalitni kassaga kiritish, ЦОТУ (TXKM) yoki kassa ishlab chiqaruvchisi yordamida sozlash, sinov to'lovi. Statik QR chekni fiskallashtirmaydi; marketlangan tovar uchun dinamik kerak. [MEDIA] https://buxgalter.uz/publish/doc/text215897_edinyy_qr-kod_obyazatelen_dlya_yurlic_s_1_iyulya_-_mojno_li_ispolzovat_drugie_qr-kody

## 4. UzQR API'ning ochiq hujjati

- [NOT FOUND]. Qidirildi: uzqr.uz (ochilmadi), cbu.uz, GitHub (kod qidiruvida faqat bitta Flutter loyihasida "UzQR" so'zi), Google/Bing (ru, uz, en). Qoidalar 10-bandiga ko'ra Operator texnik hujjatni o'z saytida e'lon qilishi kerak — ehtimol uzqr.uz ichida yoki ishtirokchilar uchun yopiq.
- QR satri (EMVCo payload)ni kim yasaydi — UzQR qaytaradimi yoki kassa o'zi yasaydimi [NOT FOUND]. CSI matni "kassa UzQR'ga QR yaratish so'rovini yuboradi" deydi, demak QR UzQR tomonidan qaytariladi (xulosa).
