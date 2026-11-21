# rizqim.uz landing va "Hamkor bo'lish" sahifasi: nimalar chiziladi (21.11.2026)

Egasi chizishi uchun ro'yxat. Faqat hozir haqiqatan bor narsalar; yo'q narsa (App Store / Google Play, ochiq API,
o'ylab topilgan raqamlar va sharhlar) chizilmaydi.

Har bir sahifa ikki o'lchamda: kompyuter (1440 px) va telefon (375 px). Ko'p odam telefondan kiradi.
Matnlar o'zbekcha va ruscha bo'ladi: ruscha matn uzunroq, joy qoldiring.

## A. Landing (rizqim.uz)

1. **Yuqori qator**: logo; menyu (Qanday ishlaydi, Restoranlar uchun, POS kassa, Savollar); til (O'zbek / Русский);
   ikki tugma: "Buyurtma berish" (Telegram botni ochadi) va "Hamkor bo'lish". Telefonda menyu "≡" ichida.
2. **Bosh blok (xaridor uchun)**: sarlavha, bir qator izoh, asosiy tugma "Telegram'da ochish", yonida telefon
   ichida Mini App ekrani (bosh sahifa yoki restoran menyusi).
3. **Qanday buyurtma beriladi**: 4 qadam: Telegram'da oching -> restoran va taom tanlang -> to'lang (Click, Payme
   yoki naqd) -> yetkazib berish yoki olib ketish.
4. **Xaridorga foydasi**: ilova o'rnatish shart emas (Telegram ichida), buyurtmani real vaqtda kuzatish, onlayn
   to'lov, aksiya va bonuslar.
5. **Restoranlar uchun (ikki mahsulot)**:
   - Marketplace: Rizqim orqali yangi mijozlar, buyurtmalar hamkor panelida.
   - POS kassa (pullik): kassa, ofitsiant telefoni, oshxona ekrani, mijoz ekrani.
   Tugma "Hamkor bo'lish".
6. **POS kassa bloki**: kassa ekrani, ofitsiant telefoni va oshxona ekranining rasmlari; 4-6 ta asosiy imkoniyat:
   internetsiz ham ishlaydi, stol va zal, oshxonaga avtomatik yuborish, smena va hisobotlar, fiskal chek,
   Android va Windows qurilmalarda.
7. **Hamkor paneli**: business.rizqim.uz ekranining bitta rasmi (buyurtmalar, menyu, hisobot).
8. **Savollar (FAQ)**: 5-6 ta savol, javoblarni egasi beradi (narx, komissiya, ulanish muddati, qurilmalar,
   to'lov usullari).
9. **Oxirgi chaqiriq**: ikki tugma yana: "Telegram'da ochish" va "Hamkor bo'lish".
10. **Pastki qism**: logo va shior "Rizq har kuni."; telefon, Telegram, manzil; "Maxfiylik siyosati" va
    "Foydalanish shartlari" havolalari (onlayn to'lov uchun shart); ijtimoiy tarmoqlar (faqat haqiqatan borlari).

Chizilmaydi: App Store / Google Play tugmalari, "1 200+ restoran" kabi raqamlar, xaridor sharhlari,
"Dasturchilar uchun / API". Haqiqiy raqam va sharhlar pilotdan keyin qo'shiladi.

## B. "Hamkor bo'lish" sahifasi (rizqim.uz/hamkor)

1. **Yuqori qator**: landingdagi bilan bir xil.
2. **Qisqa bosh blok**: sarlavha "Rizqim bilan hamkor bo'ling", bir qator izoh, 3 ta qisqa afzallik.
3. **Ariza formasi** (server tayyor; maydonlar shu): ikki qadamli bo'lishi mumkin.
   - 1-qadam "Siz haqingizda": Ism* , Telefon* (+998 __ ___ __ __).
   - 2-qadam "Joyingiz haqida": Joy nomi*, Joy turi* (ro'yxat: restoran, kafe, fast food, oshxona, kofeyxona,
     novvoyxona, choyxona, ketering, boshqa), Viloyat yoki shahar*, STIR (ixtiyoriy, 9 raqam), Izoh (ixtiyoriy).
   - Belgi: "Shaxsiy ma'lumotlarimni qayta ishlashga roziman" (havola bilan).
   - Tugma "Ariza yuborish".
   - Elektron pochta va "Do'kon / Texnologik hamkor / Yetkazib berish" yo'nalishlari hozircha yo'q: kerak bo'lsa
     egasi aytadi, server qismi qo'shiladi.
4. **Formaning holatlari (har biri alohida chiziladi)**:
   - maydon xatosi (masalan noto'g'ri telefon, bo'sh majburiy maydon) - qizil izoh maydon ostida;
   - yuborilmoqda (tugma ichida aylanuvchi belgi, bosib bo'lmaydi);
   - muvaffaqiyat: "Arizangiz qabul qilindi. Tez orada siz bilan bog'lanamiz." (forma o'rnida);
   - xato: internet yo'q / server javob bermadi, "Qayta urinish" tugmasi;
   - juda ko'p urinish: "Keyinroq qayta urinib ko'ring."
5. **Nima olasiz**: marketplace va POS kassa qisqacha (landingdagi 5-blokning ixcham varianti).
6. **Hamkorlik qanday boshlanadi**: 4 qadam: ariza -> ko'rib chiqish -> qo'ng'iroq va shartlar -> ishga tushirish.
7. **Tariflar** (ixtiyoriy, egasining haqiqiy narxlari bilan): marketplace komissiyasi, POS oylik narxi.
8. **Savollar (FAQ)**: bepulmi, komissiya, ulanish muddati, POS va qurilmalar.
9. **Pastki qism**: landingdagi bilan bir xil.

## C. Umumiy
- Ranglar: asosiy #C2410C, urg'u #F59E0B, fon #F4F5F7, matn #0F172A (ilovalardagi bilan bir xil).
- Logo: SVG fayl kerak.
- Tugma, kartochka va maydonlarning bir xil uslubi ikkala sahifada.
- Rasmlarda haqiqiy ilova ekranlari (namuna restoranlar bilan) bo'lgani yaxshi.
