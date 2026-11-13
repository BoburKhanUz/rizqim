# To'lov va chek: QR to'lov, sovg'a sertifikati, promo-kod, korxona hisobi va talon

Bu qo'llanma kassadagi yangi to'lov usullari haqida: **QR to'lov** (Click, Payme, UzQR — mijoz skanerlaydi, to'lov o'zi tasdiqlanadi),
**sovg'a sertifikati**, **promo-kod** va **korxona hisobi** (stolovaya: xodimlar talon bilan ovqatlanadi, oy oxirida korxonaga hisob).
Ega, menejer va kassir uchun.

Kassaning umumiy ishi alohida qo'llanmalarda:

- kassir: [kassa.md](kassa.md) (to'lov turlari — [12-bo'lim](kassa.md#12-tolov-turlari)) va [tez-kassa.md](tez-kassa.md);
- ofitsiant: [ofitsiant.md](ofitsiant.md);
- mijoz ekrani: [mijoz-ekrani.md](mijoz-ekrani.md);
- panel: [hamkor-panel.md](hamkor-panel.md); eganing birinchi sozlashi: [ega-pos-sozlash.md](ega-pos-sozlash.md).

> **Muhim.** Bu imkoniyatlar faqat yangi to'lov usullarini ochadi. **Naqd va karta bilan to'lash, schyot, chek, smena, Z va
> qaytarish hech qachon to'xtamaydi.** Modul o'chirilsa yoki internet uzilsa ham kassa oddiy ishlayveradi.
>
> **Chek summasi = schyot summasi.** Qaysi usulda to'lanmasin, sotuv cheki schyotning **to'liq summasiga** chiqadi va uni
> **Rizqim** chiqaradi. Click yoki Payme o'zlari alohida chek chiqarmaydi (ikki marta hisoblanmasligi uchun) —
> [ega-pos-sozlash.md](ega-pos-sozlash.md) boshidagi "Ikki marta hisoblamaslik" qutisiga qarang.

## Mundarija

1. [Qaysi modul nimani yoqadi](#1-qaysi-modul-nimani-yoqadi)
2. [QR to'lov: panelda sozlash](#2-qr-tolov-panelda-sozlash)
3. [QR to'lov kassada](#3-qr-tolov-kassada)
4. [QR to'lovni qaytarish](#4-qr-tolovni-qaytarish)
5. [Sovg'a sertifikati](#5-sovga-sertifikati)
6. [Promo-kod](#6-promo-kod)
7. [Korxona hisobi va talon: panelda sozlash](#7-korxona-hisobi-va-talon-panelda-sozlash)
8. [Korxona hisobi kassada](#8-korxona-hisobi-kassada)
9. [Oylik hisobot va Excel](#9-oylik-hisobot-va-excel)
10. [Internet uzilsa](#10-internet-uzilsa)
11. [X va Z hisobotda](#11-x-va-z-hisobotda)
12. [Muammolar va yechimlar](#12-muammolar-va-yechimlar)

## 1. Qaysi modul nimani yoqadi

Egasi filial formatini **Kassa sozlamalari** → **Format** da tanlaydi ([ega-pos-sozlash.md](ega-pos-sozlash.md#filial-formati-birinchi-qadam)).
Modullarni keyin ham alohida yoqish yoki o'chirish mumkin.

| Modul (panelda nomi) | Nima qiladi | Qaysi formatda o'zi yoqiladi | Tarif |
|---|---|---|---|
| **Click, Payme va UzQR kassada** | dinamik QR to'lov | kafe, restoran, qahvaxona, fast food | har tarifda |
| **Sovg'a sertifikati va vaucher** | sertifikat sotish va promo-kod | kafe, restoran, qahvaxona | Asosiy va undan yuqori |
| **Korxona hisobi va talon** | korxona hisobi, xodimlar, talon | stolovaya | Asosiy va undan yuqori |

Tarifda yo'q modul yonida **"Asosiy tarifda bor"** yozuvi turadi ([hamkor-panel.md](hamkor-panel.md#tarif-va-tolov-sahifasi)).
Modul faqat **yangi narsani yaratishni** to'sadi: sertifikat bilan to'lash, mavjud QR'ni bekor qilish, to'langan QR'ni qaytarish
va korxona tushligini yozish modul o'chiq bo'lsa ham to'xtamaydi.

## 2. QR to'lov: panelda sozlash

Pul **do'konning o'z** Click yoki Payme hisobiga tushadi (platforma hisobi orqali emas). Shuning uchun avval o'z merchant hisobingiz
bo'lishi kerak.

### O'z merchant hisobini ulash

1. Click yoki Payme bilan do'kon uchun merchant shartnomasi tuzing; kabinetdan kalitlarni oling.
2. Panelda **Sozlamalar** → **Do'kon** → **To'lov usullari**. To'lovlar **do'konning o'z hisobiga** tushadigan rejim yoqilgan
   bo'lishi kerak ("To'lovlar do'konning o'z hisobiga tushadi").
3. Click uchun **service_id**, **merchant_id**, **merchant_user_id** va **maxfiy kalit**; Payme uchun **Merchant ID** va **maxfiy
   kalit** kiriting. Maxfiy kalit saqlangandan keyin ko'rinmaydi: bo'sh qoldirsangiz saqlangani qoladi, yangisini yozsangiz almashadi.
4. **Saqlash**.

### Webhook manzillari (merchant kabinetida)

Click va Payme kabinetida to'lov xabarlari yuboriladigan manzilni yozing (domen — sizning Rizqim serveringiz):

- Click: `https://<domen>/api/v1/payments/click/webhook`
- Payme: `https://<domen>/api/v1/payments/payme/webhook`

Manzillar Mini App buyurtmalari bilan **bir xil**; server kassa QR'ini hisob nomidan (`pos_qr_…`) ajratadi va do'konning **o'z
kaliti** bilan tekshiradi. Domenni va kabinetdagi sozlashni RIZQIM jamoasi bilan birga tekshiring.
<!-- TEKSHIRILMAGAN: haqiqiy Click/Payme merchant kabinetida webhook manzilini kiritish va sinov to'lovi; Payme `receipts.cancel` id si birinchi do'kon ulanganda Payme bilan tasdiqlanadi -->

### Kassa uchun sozlash

**Kassa sozlamalari** → **QR to'lov** tabi (filialni yuqorida tanlang):

| Maydon | Nima |
|---|---|
| **Provayderlar** | kassada taklif qilinadiganlar: Click, Payme, UzQR. Yonida **Ulangan** yoki **Ulanmagan** turadi: merchant hisobi kiritilmagan provayder ishlamaydi |
| **Birinchi taklif qilinadigan** | kassa avval shuni tanlaydi |
| **QR amal qilish vaqti** | 1 dan 30 daqiqagacha (standart 5). Mijoz to'lashni boshlagan bo'lsa, QR yana 10 daqiqa kutadi |

**UzQR** hozircha faqat **sinov rejimida** ishlaydi (haqiqiy bank ulanishi keyin). Sinov QR'i bilan haqiqiy pul harakatlanmaydi.

Kimdir sozlamani o'zgartirsa, jurnalda qoladi.

## 3. QR to'lov kassada

1. Schyotni oching → **To'lash** → **QR** yorlig'i. Modul yoqiq, provayder ulangan va internet bor bo'lsa, provayderni tanlaysiz
   (birinchisi avvaldan tanlangan: Click, Payme yoki UzQR). **QR summasi** schyot qarziga teng turadi; kamroq yozsangiz, qolgani
   boshqa usulda to'lanadi (aralash to'lov, masalan sertifikat + QR).
2. **QR yaratish**. Kassa QR kodni **katta** ko'rsatadi, amal qilish vaqti sanaladi va holat o'zi yangilanadi.
3. QR **mijoz ekrani**da ham chiqadi: summa va provayder nomi bilan ([mijoz-ekrani.md](mijoz-ekrani.md#3-ikkinchi-ekran-rejimi-mijoz-nimani-koradi)).
4. Mijoz telefoni bilan skanerlab to'laydi. To'lov kelishi bilan kassa **o'zi** tasdiqlaydi: schyot to'langan bo'ladi, chek chiqadi,
   mijoz ekranida **"To'landi"**. **Kassir hech narsa bosmaydi.**

Kutish paytidagi tugmalar:

- **Schyotga chop etish** — QR schyot qog'ozida ham chiqadi (mijoz qog'ozdan skanerlaydi).
- **Bekor qilish** — QR'ni qaytarib oladi; keyin kelgan to'lov **rad etiladi** (pul olinmaydi).
- **Yangi QR** — eskisi tugagan yoki boshqa provayder kerak bo'lsa. Schyotga bir vaqtda **bitta** kutilayotgan QR bo'ladi: yangisi
  eskisini bekor qiladi. Mijoz to'lashni allaqachon boshlagan bo'lsa (provayder pulni olayotgan), **Yangi QR** va **Bekor qilish**
  o'chiq turadi: bir oz kuting.
- **Statik QR (qo'lda tasdiqlash)** — do'konning doimiy QR kodi: mijoz unga to'laydi, **pul kelganini kassir o'zi tekshirib**
  qo'lda tasdiqlaydi (avvalgi usul). Dinamik QR kutilayotganda bu tugma o'chiq.

Dinamik QR o'chiq yoki ulanmagan bo'lsa, **QR** yorlig'i oldingi statik qo'lda tasdiqlash shaklida ishlaydi.

Muhim qoidalar:

- **Summa tiyiniga qadar mos** kelishi shart; boshqa summa rad etiladi.
- **Schyot o'zgarsa** (qayta tuzish, bo'lish, chegirma, boshqa to'lov), kutilayotgan QR **bekor bo'ladi**: yangisini chiqaring.
- QR amal qilish vaqti o'tgach u **ekrandan ham yo'qoladi** va kelgan to'lov rad etiladi.
- Bir to'lov ikki marta yozilmaydi: provayder xabarni takrorlasa, hech narsa o'zgarmaydi.
- Pul QR **yaratilgan smenaga** tushadi; smena yopilgan bo'lsa — kassaning hozirgi ochiq smenasiga. Smena yo'q bo'lsa, QR bekor
  bo'ladi va pul olinmaydi.

## 4. QR to'lovni qaytarish

Qaytarish odatdagidek ([kassa.md](kassa.md#14-pulni-qaytarish), smena ichida, sabab bilan), lekin QR bilan to'langan pul
**provayder orqali** qaytadi: Click to'lovni bekor qiladi, Payme chekni bekor qiladi, UzQR — shlyuz.

- Click va Payme **faqat to'liq** qaytaradi. Qisman qaytarish so'ralsa: "QR to'lovni qisman qaytarib bo'lmaydi" xabari chiqadi.
- Provayder rad etsa yoki javob bermasa, qaytarish **qilinmaydi** va oddiy xabar chiqadi. QR to'lovni **hech qachon jimgina naqd
  bilan qaytarmaydi**. Biroz kutib qayta urining yoki menejerga ayting.
- Qo'lda tasdiqlangan **statik QR** (internetsiz yoki modul yo'q) avvalgidek qaytariladi.


## 5. Sovg'a sertifikati

Sertifikat — oldindan sotib olingan summa: egasi keyin shu summa bilan to'laydi. Kod `XXXX-XXXX-XXXX` ko'rinishida, qog'ozda QR bilan.

### Sotish

1. Kassada **Smena** sahifasida **Sertifikat sotish** (sotish faqat shu yerda; pul shu smenaga tushadi). Qiymatni yozing (tez summalar ham bor) (1 000 dan 100 000 000 so'mgacha, butun so'm).
2. To'lov turi: **naqd**, **karta** (karta turi va tranzaksiya raqami majburiy) yoki **QR**.
3. Xohlasangiz xaridor ismi va telefonini yozing. Amal qilish muddati — standart **1 yil**.
4. Kassa **sertifikatni chop etadi** (do'kon, kod, QR, qiymat, muddat) va **avans cheki**ni chiqaradi. **Qayta chop etish** sertifikatning **NUSXA** yozuvli nusxasini beradi.

Sertifikat sotuv emas, **avans** (bo'nak): pul smenaga tushadi, lekin savdo tushumiga kirmaydi. Sotuv tushumi sertifikat bilan
**to'langanda** hisoblanadi. Naqd sotilgan sertifikat pulli Z dagi **kutilgan naqd**ga qo'shiladi.

### To'lovda ishlatish

1. To'lov oynasida **Sertifikat** yorlig'ini tanlang; **Sertifikat kodi**ni skaner bilan o'qing yoki yozing (`XXXX-XXXX-XXXX`) va **Tekshirish**.
2. Kassa holat, **Qoldiq**, **Qiymati** va muddatni ko'rsatadi; **Sertifikatdan** maydoniga summani yozing. Summa sertifikat qoldig'idan ham, schyot qarzidan ham ko'p bo'lmaydi.
3. Qoldiq yetmasa, qolganini naqd, karta yoki QR bilan to'lang (aralash to'lov).

Qoidalar: bitta sertifikat bitta schyotda **bir marta**; qoldiq saqlanadi va keyingi schyotda ishlatiladi; **bloklangan**, to'liq
ishlatilgan yoki **muddati o'tgan** sertifikat to'lamaydi. Ikki kassa bir paytda ishlatsa, faqat biri o'tadi, ikkinchisiga "qoldiq
yetmaydi" chiqadi. Sotuv cheki baribir **to'liq summaga**, sertifikat qismi "oldindan to'langan" deb belgilanadi (bron avansidek).

### Qaytarish

Sertifikat bilan to'langan schyotni qaytarsangiz, pul **sertifikatga qaytadi** (naqd emas): qoldiq yana ko'payadi.

### Panelda: ro'yxat, hisobot, bloklash

Panelda **Sertifikatlar** sahifasi:

- **Davr** bo'yicha: **Sotildi (avans)**, **To'lovda ishlatildi**, **Qolgan majburiyat** (hali to'lay oladigan qoldiq),
  **Bloklangan / muddati o'tgan**.
- Ro'yxat: kod, holat (**Faol**, **Ishlatilgan**, **Bloklangan**, **Muddati o'tgan**), qiymat, qoldiq, kim sotgan; kod, xaridor
  yoki telefon bo'yicha qidirish. Xaridor telefoni niqoblangan ko'rinadi.
- **Tarix** — sertifikatning har bir harakati (sotildi, to'landi, qaytarildi, bloklandi).
- **Bloklash** — yo'qolgan yoki o'g'irlangan sertifikatni to'xtatadi (sabab yoziladi; qoldiq saqlanadi). **Blokdan chiqarish** uni
  qaytaradi. Faqat sertifikatlarni boshqarish huquqi bor xodim (menejer, ega) qila oladi.
- **Excel yuklab olish** — sertifikatlar va davr hisoboti.

## 6. Promo-kod

Promo-kod — do'konning o'z chegirma kodi (Mini App buyurtmalarida ishlaydigan shu kod). Kassada u buyurtma chegirmasi bo'ladi.
Kodni hozircha RIZQIM jamoasi (marketing) do'kon uchun yaratadi: panelda kod yaratish sahifasi yo'q.
<!-- TEKSHIRILMAGAN: egasi promo-kodni o'zi yarata oladimi — panelda hozircha yo'q, faqat boshqaruv markazida -->

- **Kassada:** buyurtma ekranida **Promo-kod** tugmasi → kodni yozing yoki skanerlang. Chegirma qatori kod bilan ko'rinadi
  ("Promo-kod BAHOR10"). Kod qo'llangach shu qatordan uni olib tashlash mumkin ("Promo-kod olib tashlansinmi?").
- **Ofitsiant telefonida:** buyurtma menyusida **Promo-kod** ([ofitsiant.md](ofitsiant.md)). Taom qo'shilgan bo'lishi kerak.
- Bitta buyurtmaga **bitta** kod. Qo'lda berilgan chegirma kod bilan almashadi.
- Kassirning chegirma limiti promo-kodga tegmaydi (kodni ega o'zi belgilagan). Ofitsiantga ofitsiant qoidasi ishlaydi.
- Rad etilganda sababi aytiladi: kod topilmadi, faol emas, minimal buyurtma summasiga yetmadi, limit tugadi, chegirma bermaydi.
- Faqat **onlayn**: internetsiz "Internet yo'q: sertifikat va promo-kod faqat server bilan ishlaydi. Naqd yoki karta bilan oling."
  xabari chiqadi.


## 7. Korxona hisobi va talon: panelda sozlash

Stolovaya yaqin korxonaga tushlik yetkazadi: xodim **talon** (o'z kodi) bilan ovqatlanadi, summa korxona hisobiga yoziladi, oy oxirida
korxonaga hisob-faktura beriladi.

### Korxonani qo'shish

1. Panelda **Nasiya** → **Hisob ochish**. **Hisob turi**: **Korxona**.
2. To'ldiring: **Korxonaning rasmiy nomi**, **STIR** (9 xonali; yoki 14 xonali JShShIR), **Mas'ul shaxs** va uning telefoni,
   **Hisob-faktura uchun e-pochta**.
3. **Oylik limit** (bo'sh bo'lsa — yo'q) va **Talon tartibi**:
   - **Talonsiz: korxona bo'yicha** — xodim ixtiyoriy, hammasi korxona hisobiga;
   - **Kunlik limit (summa)** — har to'lovda xodim ko'rsatiladi, kuniga cheklangan summa;
   - **Abonement (kuniga ovqat soni)** — kuniga necha marta ovqatlanishi cheklanadi.
4. **Kassada menejer tasdig'i kerak** — standart **o'chiq**: kassir tushlikni o'zi yozadi (limitlar baribir tekshiriladi). Yoqsangiz,
   nasiyadek menejer PIN'i so'raladi.

Korxona hisobi — oddiy nasiya hisobi ham ([kassa.md](kassa.md#16-nasiya-va-qarz-tolovi)): **Hozirgi qarz** shu yerda. Xodimi bor
korxonani oddiy nasiyaga qaytarib bo'lmaydi.

### Xodimlar

Korxona qatorida **Xodimlar va hisobot**. **Xodimlar** tabi:

- **Xodim qo'shish**: **Ismi va familiyasi**, telefon (ixtiyoriy; do'kon xodimlari orasida takrorlanmasin), **Talon kodi** (bo'sh
  qoldirsangiz 10 xonali kod beriladi; o'zingiz yozsangiz 4–32 ta lotin harfi, raqam yoki "-"; do'konda yagona), **Kunlik limit**
  (bo'sh — cheksiz), **Kuniga ovqat soni** (1–20).
- **Ro'yxatdan qo'shish** (ko'plab xodim): har qatorga bitta — `Ism Familiya;90 123 45 67`. Telefon ixtiyoriy; uchinchi ustun —
  o'z talon kodingiz. Limit va ovqat soni hammaga bir xil beriladi (500 qatorgacha). **Hammasi yoki hech biri**: bitta qator xato
  bo'lsa, hech kim qo'shilmaydi va xato qatorlar sababi bilan ko'rsatiladi.
- **O'chirish** — xodim talonini to'xtatadi (kassada "xodim faol emas"). **Yoqish** qaytaradi.
- **Yangi talon kodi berish** — talon yo'qolsa yoki nusxalansa: eski talon darhol ishlamaydi.

### Talonni chop etish

Talon — xodimning kodi (QR kartochka yoki beydj). Kassada **Nasiya** bo'limida korxona hisobini tanlab **Talon chop etish**: xodimlar ro'yxati chiqadi, talonni bittalab yoki
**Hammasini chop etish** bilan chop etasiz. Hisoblarni boshqarish huquqi bo'lmagan kassir ro'yxatni ko'rmaydi: **Talon kodi** (yoki telefon)
ni yozib bitta talon chop etadi. Xodim talonni skaner qilganda yoki kodni yozganda topiladi. Talon "ishlatilib tugamaydi": limitlar
serverda sanaladi.

## 8. Korxona hisobi kassada

Kassada **To'lov** oynasida **Nasiya** yorlig'i (faqat korxona hisobi yoqilgan filialda — **Korxona**), ichida **Mijoz nasiyasi / Korxona** tanlovi.

1. **Korxona**ni tanlang; **Talon kodi yoki telefon**ni skaner qiling yoki yozing → **Topish**.
2. Kassa korxona va xodimni, **Bugun qoldi** (summa va ovqat soni) va **Korxona: shu oy qoldi**ni ko'rsatadi; summa sig'sa **Yozish mumkin**, aks holda **Limit yetmaydi**.
3. **Korxona hisobiga** → tasdiqlang. Menejer PIN'i faqat korxonada **Kassada menejer tasdig'i kerak** yoqilgan bo'lsagina
   so'raladi.

Hisob-kitob **biznes kun** bo'yicha (Toshkent vaqti; kun odatda 05:00 da boshlanadi: 04:59 dagi tushlik kechagi kunga yoziladi).
Rad etish sabablari oddiy so'z bilan aytiladi va naqd yoki kartaga o'tishni taklif qiladi:

| Xabar ma'nosi | Sabab |
|---|---|
| Kunlik limit tugadi | xodim bugun limitidan ko'p yeb bo'lgan (qancha qolgani ko'rsatiladi) |
| Bugungi ovqat ishlatilgan | abonementda kuniga ovqat soni tugagan |
| Oylik limit tugadi | korxonaning oylik limiti yetmaydi |
| Kredit limiti tugadi | korxonaning umumiy qarz limiti yetmaydi |
| Xodim faol emas | talon to'xtatilgan |
| Xodim ko'rsating | talon tartibida xodimsiz yozib bo'lmaydi |
| Hisob bloklangan | korxona hisobi bloklangan |

Xodim limitdan oshib ketsa, ortig'ini **naqd yoki karta** bilan to'laydi (aralash to'lov).

Chek: sotuv cheki schyotning **to'liq summasiga**, korxonaga yozilgan qismi **kredit** deb belgilanadi (nasiyadek); korxona qarzini
to'lagach kredit cheki chiqadi. Tushlikni qaytarsangiz, xodimning o'sha kuni limiti **tiklanadi**. Korxona tushligi uchun egaga
alohida xabar yuborilmaydi: hamma narsa oylik hisobotda.

## 9. Oylik hisobot va Excel

Panelda korxona hisobi → **Oylik hisobot** (huquq: **Hisobotlar**). **Oy**ni tanlang:

- jadval: **xodim × kun** (biznes kun bo'yicha summa), har xodimning jami va ovqatlari soni, oxirida **Jami**; xodimsiz yozuvlar
  **Xodimsiz (korxona)** qatorida;
- **Oy davomida yozilgan**, **Ovqatlar soni**, **Oy davomida to'langan**, **Hozirgi qarz**, **Oylik limit**;
- qaytarilgan tushliklar ayirilgan.

**Excel yuklab olish** ikki varaq beradi: **Hisobot** (Xodim, Talon kodi, 1..31-kun, Jami, Ovqatlar) va **Hisob-faktura** (korxona,
STIR, mas'ul, e-pochta, oy, yozilgan summa, ovqatlar, to'langan, oylik limit, hozirgi qarz). Faylni korxona buxgalteriyasiga
yuboring. **Hisob-fakturani chop etish** tugmasi shu ma'lumotning qisqa varag'ini beradi.

Korxona qarzini to'lashi — oddiy qarz to'lovi ([kassa.md](kassa.md#qarz-tolovi)): kredit cheki chiqadi.

## 10. Internet uzilsa

Naqd va karta ishlayveradi. Yangi imkoniyatlar serverga bog'liq (qoldiq va limitlar serverda), shuning uchun:

| Nima | Internetsiz |
|---|---|
| Dinamik QR (Click, Payme, UzQR) | chiqmaydi; **QR** yorlig'i ham ko'rinmaydi. Naqd yoki karta bilan oling. Internet bor paytda statik QR (**Statik QR (qo'lda tasdiqlash)**) qo'lda tasdiqlanadi |
| Sertifikatni sotish va ishlatish | mumkin emas; naqd yoki karta |
| Promo-kod | qo'llanmaydi; naqd yoki karta |
| Korxona hisobiga yozish | mumkin emas; xodim naqd yoki karta bilan to'laydi |
| Kassa, ofitsiant telefoni | oddiy ishlaydi ([kassa.md](kassa.md#20-internet-uzilsa)) |

Kutilayotgan QR bo'lib internet uzilsa, to'lov kelganini internet qaytgach kassa o'zi ko'radi; shubha bo'lsa mijozga avval to'lov
kelganini tekshiring, so'ng yana urinmang.

## 11. X va Z hisobotda

- **QR to'lovlar** bo'limi: provayderlar bo'yicha son va summa — **Click**, **Payme**, **UzQR** va **Statik QR (qo'lda)**. Komissiya
  hisoblanmaydi.
- **Sertifikatlar** bo'limi: sotildi (soni, summasi, naqd/karta/QR) va ishlatildi; **Sertifikat** to'lov turi sifatida ham qator. Sotilgan
  sertifikat savdoga kirmaydi.
- Naqd sotilgan sertifikat **kutilgan naqd**ga qo'shiladi.
- Korxonaga yozilgan tushlik nasiyadek "pul emas" qatorida.

## 12. Muammolar va yechimlar

| Muammo | Nima qilish kerak |
|---|---|
| QR chiqmayapti, "ulanmagan" yoki provayder tanlanmaydi | Panelda **Sozlamalar** → **Do'kon** da o'z Click/Payme kalitlari kiritilganini va **Kassa sozlamalari** → **QR to'lov** da provayder yoqilganini tekshiring |
| Mijoz to'ladi, lekin kassada "to'landi" chiqmadi | Biroz kuting (kassa har 3 soniyada so'raydi). QR muddati o'tgan yoki schyot o'zgargan bo'lsa to'lov rad etilgan, pul mijozga qaytadi. Muddat o'tgan bo'lsa yangi QR chiqaring |
| "Summa mos emas" | QR schyot summasiga yaratiladi. Schyot o'zgargan bo'lsa yangi QR chiqaring |
| Yangi QR so'rab bo'lmayapti | Mijoz to'lashni boshlagan: bir oz kuting yoki avval kelgan to'lovni kuting |
| QR to'lovni qaytarib bo'lmadi | Provayder rad etdi yoki javob bermadi: qaytarish yozilmadi. Keyinroq qayta urining yoki menejerga ayting; qisman qaytarish yo'q |
| Sertifikat "topilmadi" | Kodni qayta o'qing (bo'shliq, chiziqcha, katta-kichik harf farq qilmaydi). Boshqa do'konning sertifikati topilmaydi |
| Sertifikat to'lamayapti | Holatini ko'ring: bloklangan, muddati o'tgan yoki ishlatilib bo'lgan |
| Promo-kod rad etildi | Kassa sababini aytadi: faol emas, minimal summa, limit |
| Xodim "limit tugadi" | Ortig'ini naqd yoki karta bilan oling; egasi limitni panelda o'zgartira oladi |
| Talon yo'qoldi | Panelda xodim → **Yangi talon kodi berish**; eskisi ishlamaydi |
| Korxona hisobini oddiy nasiyaga o'zgartirib bo'lmaydi | Xodimi bor hisobning turi o'zgarmaydi: oddiy nasiya uchun yangi hisob oching |
| Bulk ro'yxat qabul qilinmadi | Xato qatorlar sababi bilan ko'rsatiladi (ism yo'q, telefon noto'g'ri, takror, kod band); to'g'rilab qayta yuboring |
