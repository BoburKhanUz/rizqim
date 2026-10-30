# Choy puli va ofitsiant ish haqi: ega uchun qo'llanma

Bu qo'llanma restoran, kafe, kabobxona va baliqxona egasi (va uning buxgalteri) uchun. Unda: choy puli kimga va qanday
bo'linishini tanlash, ofitsiantlar ish haqi qoidalarini yozish, davr oxirida hisob-kitob tuzish va kassadan to'lash.

Bu **kadrlar hisobi emas**: tabel, soliq va ijtimoiy to'lov yo'q. Faqat ofitsiant va xodimlarga qancha to'lash kerakligini
hisoblaydi va to'langanini yozadi. Rasmiy hujjatlar uchun buxgalteringizga qarang.

Kassirning choy pulini qanday olishi — [restoran.md](restoran.md#5-choy-puli-kassada). Format va modullarni yoqish —
[ega-pos-sozlash.md](ega-pos-sozlash.md#filial-formati-birinchi-qadam). Xizmat haqining oddiy sozlamasi —
[ega-pos-sozlash.md](ega-pos-sozlash.md#6-xizmat-haqi-va-oldindan-elon-qilish).

## Mundarija

1. [Avval shuni bilib oling](#1-avval-shuni-bilib-oling)
2. [Choy puli: sozlash](#2-choy-puli-sozlash)
3. [Ish haqi qoidalari](#3-ish-haqi-qoidalari)
4. [Hisob-kitob (davr oxirida)](#4-hisob-kitob-davr-oxirida)
5. [Kassadan to'lash va choy pulini berish](#5-kassadan-tolash-va-choy-pulini-berish)
6. [Ish vaqti](#6-ish-vaqti)
7. [Xizmat haqi qoidalari](#7-xizmat-haqi-qoidalari)
8. [Kim nimani ko'radi va qila oladi](#8-kim-nimani-koradi-va-qila-oladi)
9. [Muammolar va yechimlar](#9-muammolar-va-yechimlar)

## 1. Avval shuni bilib oling

| Tushuncha | Ma'nosi |
|---|---|
| **Choy puli** | Mehmon o'z xohishi bilan beradigan pul. **Tushum emas**: fiskal chekka kirmaydi, savdo hisobotiga qo'shilmaydi. X va Z da alohida bo'lim |
| **Xizmat haqi** | Schyotga kiritilgan foiz (yoki kuver). Bu tushum: chekda chiqadi. Choy pulidan farqli |
| **Shaxsiy** choy puli | Buyurtmaning ofitsiantiga yoki kassir tanlagan xodimga |
| **Umumiy qozon** | Hamma choy puli bir qozonga tushadi va kun yoki smena oxirida qoida bo'yicha bo'linadi |
| **Ish haqi qoidasi** | Xodim yoki rol uchun bir yoki bir nechta **qism**dan iborat formula; qismlar qo'shiladi |
| **Hisob-kitob** | Davr (kun, hafta, yarim oy, oy) uchun hamma xodimning qancha olishi hisoblangan hujjat |

Hamma summani **server** hisoblaydi: telefon yoki kassa o'zi hisoblamaydi, panel faqat ko'rsatadi.

**Choy puli va ish haqi — alohida narsalar.** Choy puli standart bo'yicha ish haqiga **kirmaydi** (xodim uni baribir oladi);
faqat siz ish haqi qoidasiga "Choy pulini qo'shish" qismini qo'shsangiz kiradi (3-bo'lim).

**Choy puliga qaytarish tegmaydi.** Mehmonga pul qaytarilsa, choy puli o'zgarmaydi. Xato yozilgan bo'lsa, menejer sabab bilan
**tuzatadi**.

**Tarif va modullar.** Choy puli, xizmat haqi qoidalari va ish haqi — **Asosiy** tarifda. **Kassa sozlamalari → Format** da
**Choy puli**, **Xizmat haqi qoidalari** va **Ofitsiant ish haqi** modullarini yoqing (**Restoran** va **Kafe** profillarida
tegishlilari o'zi yoqiladi). Modul o'chiq bo'lsa, panel sozlamani o'zgartirishga ruxsat bermaydi va "moduli o'chiq" deydi, lekin
to'lov, chek, smena va qaytarish baribir ishlayveradi.

## 2. Choy puli: sozlash

Panelda chap menyuda **Choy puli**. Sahifaning to'rt bo'limi bor: **Hisobot**, **Qozonni bo'lish**, **Choy puli sozlamasi**,
**Xizmat haqi qoidalari**.

### 2.1. Sozlama

**Choy puli sozlamasi** bo'limi (huquq: sozlamalarni boshqarish):

1. **Choy puli kimga** ni tanlang:
   - **Shaxsiy (ofitsiantga)** — buyurtmaning ofitsiantiga yoki kassir to'lovda tanlagan xodimga.
   - **Umumiy qozon** — hamma choy puli bir qozonga. Qoida bo'yicha bo'linadi (pastda).
2. **Umumiy qozon** da yana:
   - **Bo'lish qoidasi**: **Teng**, **Ishlagan soatiga qarab**, **Savdosiga qarab**, **Rol ulushiga qarab**.
   - **Davr**: **Biznes kun** (har kun alohida qozon) yoki **Smena** (har kassa smenasi alohida).
   - **Qatnashadigan rollar**: qaysi rollar qozondan ulush oladi (ofitsiant, oshpaz, kassir, hostes ...). Standart — faqat ofitsiantlar.
     Ega qozonda qatnashmaydi.
   - **Rol ulushiga qarab** da har rolga ulush (0,01 dan 100 gacha, standart 1), masalan ofitsiant 1, oshpaz 0,5.
3. **Saqlash**.

Sozlama o'zgarsa, **yangi** olingan choy pullari yangi rejimda yoziladi: har choy puli olingan paytdagi rejimini saqlaydi. Eski
yozuvlar o'zgarmaydi. Har o'zgarish harakatlar jurnaliga yoziladi.

### 2.2. Shaxsiy rejim

Kassir to'lovda choy pulini yozadi; kimga ekani ko'rsatiladi (boshida — buyurtmaning ofitsianti). Qo'shimcha hisob-kitob kerak
emas: har ofitsiantning choy puli **Hisobot** da o'z qatorida. Xodim tanlanmasa, choy puli **biriktirilmagan** bo'lib qoladi
(**Hech kimga berilmagan** deb ko'rinadi): menejer **Tuzatish** bilan xodimga beradi.

**Kimga yozish mumkin.** Faqat filialning choy puli rollaridagi xodimga (**Qatnashadigan rollar**, standart — ofitsiantlar). Choy pulini
buyurtmaning ofitsiantidan **boshqa** xodimga yozish uchun menejer PIN'i (yoki **Choy pulini tuzatish va umumiy qozonni bo'lish**
huquqi) kerak: kassa PIN so'raydi. Ruxsatsiz yoki noto'g'ri xodim bo'lsa, to'lov **olinishidan oldin** aniq xabar bilan rad etiladi
(pul olinmaydi, choy puli ham yozilmaydi). Internetsiz olingan to'lov esa hech qachon rad etilmaydi: ruxsatsiz yoki noto'g'ri xodim
tanlangan bo'lsa, choy puli buyurtmaning ofitsiantiga yoziladi.

### 2.3. Umumiy qozon: bo'lish qoidalari

Qozonda **kimlar** ulush oladi: tanlangan rollardan davrda **ishlaganlar**. Ishlaganni shunday aniqlaydi:

1. Xodimning ish vaqti (**Ishga keldim / Ishdan ketdim**, 6-bo'lim), ochiq bo'lsa hozirgacha.
2. Aks holda u ochgan kassa smenalari.
3. Ikkalasi ham bo'lmasa — xodim xizmat qilgan buyurtmalari yoki olgan to'lovlarining birinchi va oxirgi vaqti oralig'i.

Shuning uchun ofitsiantlar **Ishga keldim** ni bossa, **Ishlagan soatiga qarab** qoidasi aniq ishlaydi.

**Misollar.** Hammasida kun oxiridagi qozon bo'linadi; summa so'mgacha aniq.

**1. Teng.** Qozon 1 000 000 so'm, uch ofitsiant ishlagan.

| Ofitsiant | Ulush |
|---|---|
| Aziz | 333 334 so'm |
| Botir | 333 333 so'm |
| Dilnoza | 333 333 so'm |

1 000 000 ni 3 ga bo'lsa, har biriga 333 333 so'm, **1 so'm ortadi**. Ortgan so'm (odamlar sonidan kam bo'ladi) bittadan
**eng katta qoldiqqa** beriladi; teng bo'lsa kattaroq asosli, undan keyin ro'yxatdagi birinchi (kichik id) xodimga. Yig'indi **doim
qozonga teng**: 333 334 + 333 333 + 333 333 = 1 000 000.

**2. Ishlagan soatiga qarab.** Qozon 900 000 so'm.

| Ofitsiant | Ish vaqti | Ulush |
|---|---|---|
| Aziz | 8 soat (480 daq) | 900 000 × 480 / 1 080 = **400 000** |
| Botir | 6 soat (360 daq) | 900 000 × 360 / 1 080 = **300 000** |
| Dilnoza | 4 soat (240 daq) | 900 000 × 240 / 1 080 = **200 000** |

**3. Savdosiga qarab.** Qozon 500 000 so'm. Asos — xodim xizmat qilgan buyurtmalarining to'langan schyotlari (stol o'tkazilsa — pastda,
[sotuv kimga yoziladi](#stol-boshqa-ofitsiantga-otsa-sotuv-kimga)).

| Ofitsiant | Savdo | Ulush |
|---|---|---|
| Aziz | 6 000 000 | 500 000 × 6/10 = **300 000** |
| Botir | 3 000 000 | **150 000** |
| Dilnoza | 1 000 000 | **50 000** |

**4. Rol ulushiga qarab.** Qozon 1 000 000 so'm. Ulushlar: ofitsiant 1, oshpaz 0,5, hostes 0,5. Ishlaganlar: uch ofitsiant, ikki oshpaz,
bir hostes. Jami ulush: 3 × 1 + 2 × 0,5 + 0,5 = 4,5.

| Kim | Hisob | Ulush |
|---|---|---|
| Birinchi ofitsiant | 1 000 000 × 1 / 4,5 = 222 222,2 | **222 223** (ortgan 1 so'm shunga) |
| Ikkinchi va uchinchi ofitsiant | 222 222,2 | **222 222** dan |
| Har oshpaz va hostes | 1 000 000 × 0,5 / 4,5 = 111 111,1 | **111 111** dan |

Yig'indi: 222 223 + 2 × 222 222 + 3 × 111 111 = 1 000 000.

**Hamma asos nolga teng bo'lsa** (hech kim ish vaqtini belgilamagan va savdo yo'q) qozon **teng** bo'linadi; hisobda shunday yoziladi.

### 2.4. Qozonni bo'lish va yopish

Qozon o'zi bo'linmaydi: siz yoki menejer bo'lasiz.

1. **Qozonni bo'lish** bo'limi → **Biznes kun** ni tanlang. **Davr** (2.1) **Smena** bo'lsa, yoniga **Smena** tanlovi chiqadi
   ("Smenani tanlang"): kunning **yopilgan** smenalaridan birini tanlaysiz (yo'q bo'lsa "Bu kunda yopilgan smena yo'q") va har smena
   alohida bo'linadi; smena tanlanmaguncha **Qozonni bo'lish** tugmasi o'chiq turadi.
2. **Qozonni bo'lish** ni bosing. Jadvalda har xodimning **Asos** (soat, savdo yoki ulush) va ulushi chiqadi. Yig'indi qozonga teng.
3. Xato bo'lsa (ish vaqti unutilgan, yangi choy puli tuzatilgan), **Qayta hisoblash** — yopilguncha istalgancha.
4. To'g'ri bo'lsa **Yopish**. "Yopilgandan keyin qayta hisoblanmaydi va shu kunning choy pulini tuzatib bo'lmaydi."

Qozon hisoblangandan keyin o'zgargan bo'lsa (masalan, choy puli tuzatilgan), yopish rad etiladi: avval **Qayta hisoblash**. Bu kun uchun
ishlagan odam topilmasa, "bu davrda ishlagan xodim topilmadi" deydi va qozon hech kimga bo'linmaydi: ish vaqtini tuzating (6-bo'lim).
Qozonda pul bor-u, ulushlar yig'indisi qozonga teng bo'lmasa (masalan, hech kim qatnashmagan), yopish rad etiladi (409): xodimlarni
tuzatib, qayta hisoblang.

Eslab qoling:

- **Davr qoidasi qat'iy.** **Biznes kun** davrida qozon butun kun uchun bitta bo'linadi (smena tanlanmaydi). **Smena** davrida har
  smena alohida bo'linadi, butun kunni bitta qilib bo'lib bo'lmaydi. Kunning qozoni bilan o'sha kunning smena qozoni birga
  bo'lmaydi (aks holda choy puli ikki marta sanalardi): ilova ikkinchisini rad etadi.
- **Ishtirokchilar** faqat shu filialning **Qatnashadigan rollar** dagi xodimlari bo'ladi (qo'lda ro'yxat berilganda ham; ega bo'lmagan bo'luvchi bunday ro'yxatga
  o'zini qo'sha olmaydi). Ro'yxat va ulushlar harakatlar jurnaliga yoziladi.
- **Ish haqiga qozon ulushi yopilgandan keyin kiradi.** Ish haqi hisob-kitobidagi **Choy pulini qo'shish** qismi qozon ulushini faqat
  qozon **yopilgan** bo'lsa hisoblaydi; qoralama bo'linish hisobga kirmaydi. Hisob-kitob tuzishdan oldin davrdagi qozonlarni yoping.
- Ikki menejer bir vaqtda birinchi marta hisoblasa, ikkinchisi xato (500) emas, "band" (409) javobini oladi: sahifani yangilab,
  **Qayta hisoblash** ni bosing.

### 2.5. Hisobot va tuzatish

**Hisobot** bo'limida kunlar bo'yicha: jami, usul bo'yicha (**Naqd**, **Karta**, **QR**), **Shaxsiy** va **Umumiy qozon**, hech
kimga berilmaganlari, xodimlar bo'yicha (qozondan ulush: yopilgan summa alohida), va har choy puli (vaqti, usuli, kimga, "qaytimdan"
belgisi). Choy puli tushum emasligini sahifaning o'zi eslatadi.

**Tuzatish** (huquqi bor menejer yoki siz; kassada menejer PIN'i bilan):

1. Qatordagi **Tuzatish** → yangi summa, kimga, **Sabab** (majburiy) → **Saqlash**.
2. Asl yozuv **qoladi**; tuzatish ishorali alohida qator bo'lib yoziladi ("Tuzatilgan (1): sabab", "avval 50 000"). Shu sababli xodim
   bo'yicha yig'indilar doim aniq.
3. Qozoni **yopilgan** kunning choy pulini tuzatib bo'lmaydi.

**Tuzatish qoidalari.** Tuzatilgan jami summa **0 dan asl summagacha** bo'ladi (oshirib bo'lmaydi: choy puli mehmon bergan puldan ko'p
bo'lmaydi; kamaytirsangiz yoki boshqa xodimga bersangiz bo'ladi). Tuzatish **kassadagi naqdga tegmaydi**: kassadagi kutilgan naqdga
faqat to'lovda olingan naqd choy puli kiradi.

## 3. Ish haqi qoidalari

Panelda chap menyuda **Ish haqi**. Uch bo'lim: **Ish haqi qoidalari**, **Ish vaqti**, **Hisob-kitob**.

### 3.1. Hisob davri

**Ish haqi qoidalari** bo'limining tepasida **Hisob davri va sotuv** bloki:

- **Davr**: **Kunlik**, **Haftalik (dushanba–yakshanba)**, **Yarim oylik (1–15, 16–oxiri)**, **Oylik**. Davrlar **biznes kunlar** bo'yicha,
  Toshkent vaqti bilan (biznes kun ertalab 05:00 da boshlanadi).
- **Stol boshqa ofitsiantga o'tsa, sotuv** — pastda [alohida](#stol-boshqa-ofitsiantga-otsa-sotuv-kimga).

Davrni o'zgartirish **keyingi** hisob-kitoblarga ta'sir qiladi; yopilganlar o'zgarmaydi.

### 3.2. Qoida va uning qismlari

**Qoida qo'shish**:

1. **Qoida kim uchun**: **Rol uchun** (hamma ofitsiant, hamma oshpaz ...) yoki **Bitta xodim uchun**. Xodimning **o'z** qoidasi roldagidan
   **ustun**.
2. **Kuchga kirish sanasi** (biznes kun). Shu kundan boshlab yangi qoida hisoblanadi.
3. **Qism qo'shish** — kerakli qismlarni tanlang. Qismlar **qo'shiladi**.
4. **Saqlash**.

Qoidani o'zgartirsangiz, **yangi versiya** yoziladi: eski kunlar eski qoida bilan, yangilari yangisi bilan hisoblanadi. Qismsiz
(bo'sh) versiya — shu sanadan ish haqi yo'q ("Ish haqi yo'q (shu sanadan)").

| Qism | Nima hisoblaydi |
|---|---|
| **Smena stavkasi** | Har ishlagan smena (biznes kun) uchun belgilangan summa |
| **Soatbay stavka** | Ish vaqti (ishga keldim–ketdim va kassa smenasi), daqiqasigacha |
| **Sotuvdan foiz** | Xodimning o'z sotuvidan foiz: to'langan schyotlar, taomlar summasi chegirma ayrilgan holda; xizmat haqi, xona puli, joy puli va choy puli kirmaydi; qaytarilgani ayriladi |
| **Xizmat haqidan foiz** | Xodimning schyotlarida olingan xizmat haqidan foiz |
| **Garant + foiz** | Kafolatlangan summa: **Har smena uchun** yoki **Butun davr uchun**; foiz bilan **Garant yoki foiz (qaysi katta)** yoki **Garant + foiz**; foiz **Sotuvdan** yoki **Xizmat haqidan** |
| **Tadbir stavkasi** | Xodim xizmat qilgan har tadbir (yopilgan bron) uchun. Tur tanlanmasa, "Boshqa" dan tashqari hammasi |
| **Choy pulini qo'shish** | Xodimning davrdagi choy puli (shaxsiy va qozondan ulushi) |

Har qism summasi **bir marta so'mgacha** yaxlitlanadi (0,5 yuqoriga); jami qismlar yig'indisi.

### 3.3. Misollar

**Ofitsiant: smena + sotuvdan % + xizmat haqidan %.** Qoida: smena stavkasi 100 000 so'm, sotuvdan 3%, xizmat haqidan 10%. Haftalik
davr. Aziz 5 smena ishladi, sotuvi 12 000 000, xizmat haqi (15%) 1 800 000.

| Qism | Hisob | Summa |
|---|---|---|
| Smena stavkasi | 5 × 100 000 | 500 000 |
| Sotuvdan foiz | 3% × 12 000 000 | 360 000 |
| Xizmat haqidan foiz | 10% × 1 800 000 | 180 000 |
| **Hisoblangan** | | **1 040 000** |
| Avans (kassadan berilgan) | | − 200 000 |
| **To'lanadi** | | **840 000** |

**Soatbay + tadbir.** Hostes: 20 000 so'm/soat, bir kun 7 soat 30 daqiqa ishladi → 20 000 × 7,5 = **150 000**. Ofitsiant: tadbir stavkasi
200 000, ikkita to'yda xizmat qildi → 2 × 200 000 = **400 000** (bron yopilgan va buyurtma shu ofitsiantniki bo'lishi kerak).

**Garant, har smena uchun.** Garant 150 000 so'm, sotuvdan 5%. Uch kun ishladi: sotuv 2 000 000, 4 000 000, 1 000 000.

| Kun | 5% sotuvdan | Natija |
|---|---|---|
| 1 | 100 000 | **Garant yoki foiz (qaysi katta)**: max(150 000; 100 000) = 150 000 |
| 2 | 200 000 | max(150 000; 200 000) = 200 000 |
| 3 | 50 000 | max(150 000; 50 000) = 150 000 |
| **Jami** | | **500 000** |

Xuddi shu qoida **Garant + foiz** rejimida: 3 × 150 000 + 5% × 7 000 000 = 450 000 + 350 000 = **800 000**. Har kun alohida
taqqoslanadi: yomon kun garantiga tushadi, yaxshi kun foizga qarab oshadi.

**Garant, butun davr uchun.** Oylik davr (30 kun), garant 3 000 000, 4% sotuvdan, bitta qoida butun oy kuchda. Sotuv 60 000 000 →
foiz 2 400 000. **Garant yoki foiz**: max(3 000 000; 2 400 000) = **3 000 000**; **Garant + foiz**: 3 000 000 + 2 400 000 = **5 400 000**. Garant
**ishlagan smenalar soniga bog'liq emas**. Davr o'rtasida qoida o'zgarsa, garant qoida kuchda bo'lgan kunlar ulushida olinadi
(masalan, yangi qoida 20 kun ishlasa: 3 000 000 × 20 / 30 = 2 000 000).

**Choy pulini qo'shish.** Odatda choy puli xodimniki — kassadan alohida beriladi, ish haqiga kirmaydi. Agar siz choy pulini **ish haqi
ichida** hisoblasangiz (ofitsiantga kassadan choy pulini alohida bermaysiz, hammasini davr oxirida to'laysiz), qoidaga **Choy pulini qo'shish**
qismini qo'shing. Kassadan (smena ekranidagi **Choy pulini berish** bilan) berilgan choy puli bu holda ish haqidan **ayriladi**:
hisob-kitob qatorida alohida summa (**Choy puli kassadan berilgan**, ma'lumotda `tips_paid`) bo'lib saqlanadi va **To'lanadi** dan
ayriladi, shuning uchun xodim ikki marta olmaydi (panel jadvalida hozircha alohida ustun yo'q: **To'lanadi** "Hisoblangan − Avans + Tuzatish"
dan kam ko'rinsa, farq shu). Qoidada
**Choy pulini qo'shish** qismi **yo'q** bo'lsa, kassadan berilgan choy puli ish haqi emas (u mehmonning xodimga bergani) va
hisob-kitobdan **ayrilmaydi**.

### Stol boshqa ofitsiantga o'tsa, sotuv kimga

Ofitsiant stolni boshqasiga berishi mumkin ([restoran.md](restoran.md#4-stolni-boshqa-ofitsiantga-berish)). **Ish haqidagi sotuv** kimniki
bo'lishini siz tanlaysiz — **Hisob davri va sotuv** blokida **Stol boshqa ofitsiantga o'tsa, sotuv**:

- **To'lov paytidagi ofitsiant** (standart; choy puli kabi). Schyot to'langan paytda buyurtmaning ofitsianti schyotga yoziladi.
  O'tkazishdan **oldin** to'langan schyotlar birinchi ofitsiantda qoladi, keyingilari yangisida.
- **Stolning oxirgi ofitsianti** — buyurtmaning **hamma** schyoti, o'tkazishdan oldin to'langanlari ham, stolni oxirgi olgan ofitsiantga.

Bu sozlama X va Z hisobotdagi **Ofitsiantlar** bo'limiga ham amal qiladi: ofitsiantning smenadagi sotuvi ish haqidagi sotuv bilan bir xil
qoida bo'yicha yoziladi, shuning uchun kassadagi hisobot va ish haqi hisob-kitobi bir-biriga mos keladi.

**Misol.** Aziz stolni ochdi. Birinchi schyot (400 000 so'm) soat 20:00 da to'landi. 20:30 da stol Botirga berildi. Ikkinchi schyot
(600 000 so'm) 21:30 da to'landi.

| Sozlama | Aziz sotuvi | Botir sotuvi |
|---|---|---|
| **To'lov paytidagi ofitsiant** | 400 000 | 600 000 |
| **Stolning oxirgi ofitsianti** | 0 | 1 000 000 |

Qaytarish **qaytarilgan schyot kimga yozilgan bo'lsa, o'shandan** ayriladi. Sozlamani o'zgartirsangiz, **qoralama** hisob-kitob keyingi
hisoblashda yangisini oladi; **yopilgan** hisob-kitob o'zgarmaydi. Choy puli qozonining "savdosiga qarab" qoidasi ham shu sozlamaga amal qiladi.
Shaxsiy choy puli esa to'lov paytida kassir tanlagan (yoki buyurtmaning o'sha paytdagi) ofitsiantga yoziladi. Internetsiz olingan
to'lovda schyot va standart choy puli kassa to'lov paytida ko'rgan ofitsiantga yoziladi (serverga yetgan paytdagi emas).

## 4. Hisob-kitob (davr oxirida)

**Ish haqi → Hisob-kitob**.

### Tuzish

1. **Yangi hisob-kitob** → **Davr ichidagi sana** (davr shu sana tushgan hafta, yarim oy yoki oy) → **Hisoblash**.
2. **Qoralama** tuziladi: qoidalar, ish vaqti, sotuv, tadbirlar va kassadan berilgan **avanslar** bo'yicha. Shu davr uchun hisob-kitob
   bor bo'lsa, **Mavjudini ochish**.
3. Har xodim qatorida: **Smena**, **Ish vaqti**, **Sotuv**, **Hisoblangan**, **Avans**, **Tuzatish**, **To'lanadi**. Qatorni ochsangiz qismlar
   va qanday hisoblangani ("5 smena × 100 000 so'm", "3% × 12 000 000 so'm") ko'rinadi. Pastda "Schyotlar: … · xizmat haqi: … · tadbirlar:
   … · choy puli: …".
4. Qoidasi bo'lmagan yoki ish vaqti yopilmagan xodim uchun tepada **E'tibor bering** ogohlantirishi chiqadi:
   - "**… ishlagan, lekin qoidasi yo'q: hisoblanmadi**" — qoida qo'shing va **Qayta hisoblash**.
   - "**…: N ta ish vaqti yopilmagan, hisobga kirmadi**" — **Ish vaqti** bo'limida tuzating.
   Hech ishlamagan va sotmagan xodim (garant bo'lsa ham) qatorga chiqmaydi.

### Tuzatish: jarima va mukofot

Qatorda **Tuzatish**: jarima uchun manfiy (−50 000), mukofot uchun musbat summa va sabab. U **Qayta hisoblash** da ham saqlanadi.
To'lanadi = hisoblangan − avans − kassadan berilgan choy puli + tuzatish. **Avans** — shu davrda kassadan xodimga berilgan "Avans" va
"Kunlik ish haqi" chiqimlari (to'lanadigan summadan ayriladi). **Mukofot** chiqimi ayrilmaydi. **Choy puli kassadan berilgan** faqat
qoidada **Choy pulini qo'shish** qismi bo'lsa ayriladi (3.3 va 5-bo'lim).

Hisob-kitob to'lovi (kategoriya **payroll**, "Ish haqi (hisob-kitob)") avans emas: uni kassada qo'lda yozib bo'lmaydi (ilova rad etadi),
faqat **To'lash** (5-bo'lim) yozadi. Hisob-kitob qatoriga bog'lanmagan eski **payroll** chiqim bo'lsa, u **avans** sifatida ayriladi.

### Holatlar

| Holat | Nima |
|---|---|
| **Qoralama** | Istalgancha **Qayta hisoblash** mumkin; tuzatishlar saqlanadi. Keraksiz bo'lsa o'chiriladi |
| **Yopilgan** | **Yopish** oxirgi marta hisoblab muzlatadi: qoida keyin o'zgarsa ham yopilgan hisob o'zgarmaydi |
| **To'langan** | Pulli hamma qator to'langanda |

Yopilgan hisob-kitobni, hech bir qatori to'lanmagan bo'lsa, **Qayta ochish** mumkin (sabab jurnalga yoziladi); qoralamaga qaytadi.

## 5. Kassadan to'lash va choy pulini berish

### Ish haqini kassadan to'lash

Yopilgan hisob-kitobda qator yonidagi **To'lash**:

1. **Qanday to'lanadi**: **Kassadan naqd (smenada "ish haqi" chiqimi)** yoki **Boshqa yo'l bilan (karta, o'tkazma)**.
2. Kassadan bo'lsa, **Smena** ni tanlang: filialning ochiq smenasi ("… · kassada 3 400 000"). Ochiq smena yo'q bo'lsa, "avval smenani oching" deydi.
3. **To'landi deb belgilash**.

Kassadan to'langanda shu smenada **ish haqi chiqimi** yoziladi (kategoriya **payroll**, kim, izoh "Ish haqi 01.11–30.11.2026"). U **Ish
haqi berildi** qatorida X va Z da chiqadi. Kassadagi naqddan **ko'p bo'lmaydi**. Katta chiqim egaga xabar yuboradi (odatdagidek).
Qator **bir marta** to'lanadi.

Boshqa yo'l bilan to'langan bo'lsa, faqat **To'landi** deb belgilanadi, kassaga tegilmaydi. Hammasi to'langach hisob-kitob **To'langan** bo'ladi.

Bu amal **faqat panelda** (egada yoki **Ish haqi qoidalari va hisob-kitob** huquqi bor xodimda): kassadagi PIN sessiyasidan ish haqi sahifasi ochilmaydi.

### Choy pulini ofitsiantga berish

Naqd choy puli **kassada turadi** (kutilgan naqdga qo'shiladi). Uni ofitsiantga:

- **shaxsiy** rejimda — smena oxirida, har ofitsiantning **Hisobot** dagi o'z summasi bo'yicha;
- **umumiy qozon** rejimida — qozon bo'lingach, har xodimning ulushi bo'yicha.

Kassir buni kassa ilovasida **Smena** ekranidagi **Choy pulini berish** tugmasi bilan qiladi (tugma **Choy puli** moduli yoqilgan va
kassada naqd choy puli bor bo'lsa ko'rinadi, berilmagan qoldiq bo'lmasa o'chiq turadi; internetsiz rejimda ishlamaydi, kirim-chiqim kabi):

1. Tugmani bosing. **Choy pulini boshqarish** huquqi (**Choy pulini tuzatish va umumiy qozonni bo'lish**, menejer standart) bo'lmasa,
   ilova **menejer PIN'ini** so'raydi: menejer ismini tanlab o'z PIN'ini teradi. Tasdiq bir marta ishlaydi.
2. Oynada tepada: **Kassadagi naqd choy puli**, **Oldin berilgan** va **Berilmagan** (hali berilmagan qoldiq).
3. **Kimga** — xodimni tanlang (ro'yxatda choy puli bo'lganlar summasi bilan birinchi turadi). **Summa** o'zi to'ldiriladi, **Izoh** ixtiyoriy.
4. **Berish**. Kassa qutisi ochiladi, "Choy puli berildi" xabari chiqadi.

Qoidalar (server tekshiradi):

- Berilgan summa smenadagi **naqd choy pulidan, undan oldin berilganini ayirib**, ortiq bo'lmaydi (aks holda "Bu smenada buncha naqd
  choy puli yo'q" deydi va berish mumkin bo'lgan summani aytadi).
- Xodimga shu biznes kun uchun **unga tegishli** naqd choy pulidan ortiq berib bo'lmaydi. Tegishli summa = uning shaxsiy naqd choy puli
  (tuzatishlar bilan) + shu kunning **yopilgan** qozon bo'linishidagi ulushi − shu kuni kassalardan unga allaqachon berilgani. Qozon hali
  yopilmagan bo'lsa, ulush hali tegishli emas. Kassadan berilmagan qozon ulushi ish haqi hisob-kitobi bilan to'lanadi.
- Huquq yoki menejer PIN'ini server ham tekshiradi (tasdiq bir martalik): faqat ilova so'ragani yetmaydi.

Bu chiqim yozuvda **Ish haqi** turi, kategoriya **Choy puli**. X va Z da u **Ish haqi** qatoriga **kirmaydi**: **Kassa (naqd)** bo'limida alohida
**Choy puli berildi** qatorida (minus bilan) turadi, **Choy puli (naqd)** qatori esa kirim tomonida. Tepada alohida **Choy puli (tushum
emas)** bo'limi: **Jami**, to'lov usullari, **Shaxsiy**, **Umumiy qozon**, xodimlar, **Tuzatishlar** va **Xodimlarga berildi**. Karta va QR
choy puli kassaga naqd tushmaydi: uni siz ofitsiantga boshqa yo'l bilan (o'tkazma) berasiz.

**Ish haqiga ta'siri.** Kassadan berilgan choy puli, qoidada **Choy pulini qo'shish** qismi bo'lsa, hisob-kitobda xodimning
to'lanadigan summasidan **ayriladi** (alohida summa **Choy puli kassadan berilgan**); qismi bo'lmasa, ayrilmaydi (3.3).


## 6. Ish vaqti

Soatbay stavka, **Ishlagan soatiga qarab** qozon va ish vaqtiga bog'liq hamma narsa ish vaqtidan olinadi.

- Xodim o'z PIN sessiyasidan (kassa yoki ofitsiant ilovasi) tepadagi menyuda **Ishga keldim** va ketishda **Ishdan ketdim** ni bosadi. Bu
  faqat **Ofitsiant ish haqi** moduli yoqilgan filialda ko'rinadi. Internet kerak (internetsiz belgilash hozircha yo'q).
- Ish vaqti **boshlangan biznes kunniki**. Xodimda bir vaqtda bitta ochiq ish vaqti bo'ladi.
- Xodim ketishni unutsa, ish vaqti **ochiq qoladi**. Keyingi biznes kunda xodim yana kelsa, eskisi **Unutilgan** deb chetga olinadi va
  siz yoki menejer ketgan vaqtni kiritguncha hisobga kirmaydi. 24 soatdan uzun ish vaqti ham shunday.
- Kassa smenasining vaqti ham kassirning ish vaqtiga qo'shiladi (ustma-ust qismi **bir marta** sanaladi, har biri ko'pi bilan 24 soat).

**Ish vaqtini tuzatish.** **Ish haqi → Ish vaqti**: ro'yxatda **Xodim**, **Biznes kun**, **Keldi**, **Ketdi**, **Davomiyligi**, **Manba**
(**Terminal (PIN)** yoki **Qo'lda**). **Ish vaqtini qo'shish** yoki qatorda **Tuzatish**: **Keldi** va **Ketdi** (ketdi bo'sh qoldirilsa, xodim hali
ishda), **Sabab** (majburiy). Qoida: ko'pi bilan 24 soat, boshqa ish vaqti bilan ustma-ust tushmaydi, kelajakda bo'lmaydi. Har o'zgarish
jurnalga yoziladi.

## 7. Xizmat haqi qoidalari

**Choy puli → Xizmat haqi qoidalari** bo'limi (modul **Xizmat haqi qoidalari**, **Asosiy** tarifda). Bu — mehmonga schyotda ko'rinadigan
xizmat haqining **variantlari**. Oddiy foiz va joy narxi — **Sozlamalar → Xizmat haqi** ([ega-pos-sozlash.md](ega-pos-sozlash.md#6-xizmat-haqi-va-oldindan-elon-qilish)).

1. **Qaysi sozlama**: butun filial (hamma zallar) yoki bitta zal.
2. **Buyurtma turi bo'yicha foiz**:
   - **Zal** — ofitsiantli zal buyurtmasi;
   - **Stolga olib kelish** — kassada to'lab, stol raqami tablichkasi bilan (kafe);
   - **Olib ketish** — boshqa kassa buyurtmasi.
   Bo'sh qoldirilgan tur asosiy foizni oladi. **0** — shu turda xizmat haqi olinmaydi.
3. **Katta guruh**: **necha mehmondan** va **foiz** ikkalasi birga. Shuncha va undan ko'p mehmonli zal yoki stolga olib kelish buyurtmasi
   butun buyurtmaga shu foizni oladi (boshqa foizga qo'shilmaydi). Olib ketishga tegmaydi.
4. **Kuver (har mehmon uchun)**: zal va stolga olib kelishda har mehmon (kamida 1) uchun belgilangan summa; joy narxi qatori o'rniga.
5. **Chekdagi e'lon matni**: o'zingiz yozasiz (300 belgigacha). Bo'sh qoldirsangiz, qoidalardan avtomatik tuziladi ("Xizmat haqi: zalda 15%,
   stolga olib kelishda 12,5%. 8 va undan ko'p mehmonli stolga xizmat haqi 18%. Kuver: 5 000 so'm har bir mehmon uchun."). **Chekda ko'rinadi**
   blokida matnni oldindan ko'rasiz. U schyotda xizmat haqi qatori tagida chiqadi ([restoran.md](restoran.md#6-schyotdagi-xizmat-haqi-eloni)).

**Stolovaya**: xizmat haqi qonun bo'yicha 0%; foizlar 0 bo'lishi kerak, aks holda saqlanmaydi. Foiz 0 dan 30 gacha, mehmonlar soni 2 dan 500 gacha.
Modul o'chirilsa, qoidalar qo'llanmaydi (qatorning o'z foizi ishlaydi), saqlangani qoladi. **Oldindan e'lon qilish** majburiyati
[ega-pos-sozlash.md](ega-pos-sozlash.md#6-xizmat-haqi-va-oldindan-elon-qilish) da yozilgan: xizmat haqini menyuda va zalda ham yozing.

## 8. Kim nimani ko'radi va qila oladi

| Amal | Kim |
|---|---|
| Choy puli hisoboti | **Hisobotlar** huquqi (ega, menejer) |
| Choy puli sozlamasi, xizmat haqi qoidalari | **Do'kon sozlamalari** huquqi (ega) |
| Choy pulini tuzatish, qozonni bo'lish va yopish | **Choy pulini tuzatish va umumiy qozonni bo'lish** huquqi (menejer standart, ega); kassada menejer PIN'i bilan |
| Kassadan choy pulini berish; choy pulini buyurtma ofitsiantidan boshqa xodimga yozish | Xuddi shu huquq, yoki kassada **menejer PIN'i** (bir martalik tasdiq) |
| Ish haqi qoidalari, ish vaqti va hisob-kitobni **ko'rish** | **Ish haqi hisob-kitobini ko'rish** (ega, menejer) |
| Qoidalar, davr, ish vaqtini tuzatish, hisob-kitob va to'lash | **Ish haqi qoidalari va hisob-kitob** (ega; boshqa rolga berish mumkin) |
| O'z choy pulini ko'rish | Ofitsiant ilovasida **Mening choy pulim** (faqat o'ziniki) |

Huquqlarni **Huquqlar** sahifasida berasiz ([ega-pos-sozlash.md](ega-pos-sozlash.md#huquqlar-sahifasi-xodim-nimani-ozi-qiladi)). Ish haqi va choy
puli bo'yicha har o'zgarish harakatlar jurnaliga yoziladi.

## 9. Muammolar va yechimlar

| Muammo | Nima qilish kerak |
|---|---|
| Choy puli sozlamasini saqlab bo'lmayapti: "moduli o'chiq" | **Kassa sozlamalari → Format** da **Choy puli** modulini yoqing (tarifda yo'q bo'lsa, **Asosiy** tarif kerak) |
| Kassada choy puli maydoni yo'q | Xuddi shu: **Choy puli** moduli o'chiq (yoki tarifda yo'q) |
| **Hech kimga berilmagan** choy puli bor | Shaxsiy rejimda kassir xodim tanlamagan. **Tuzatish** bilan xodimga bering |
| Qozon "Bu davrda ishlagan xodim topilmadi" | Ish vaqti belgilanmagan va smena ochilmagan. **Ish haqi → Ish vaqti** da qo'shing, keyin **Qayta hisoblash** |
| Qozonni yopib bo'lmayapti | Hisoblangandan keyin choy puli o'zgargan. **Qayta hisoblash**, keyin **Yopish** |
| Choy pulini tuzatib bo'lmayapti | Shu kunning qozoni yopilgan (yopilgan kunning choy pulini tuzatib bo'lmaydi) yoki yangi jami 0 dan asl summagacha oraliqdan chiqyapti |
| **Choy pulini berish** tugmasi o'chiq yoki "berib bo'lmaydi" deydi | Kassada berilmagan naqd choy puli qolmagan, xodimga shu kun uchun shuncha naqd choy puli tegishli emas (qozon ulushi qozon yopilgach tegishli bo'ladi) yoki internet yo'q. Karta va QR choy pulini kassadan berib bo'lmaydi |
| Kassir choy pulini boshqa xodimga yozolmayapti | Buyurtmaning ofitsiantidan boshqasiga yozish uchun menejer PIN'i kerak, xodim esa choy puli rollaridan bo'lishi kerak (2.2) |
| Qozon ulushi ish haqida ko'rinmayapti | Qozon hali yopilmagan: yopilgan bo'linishgina ish haqiga kiradi. Qozonni yoping, hisob-kitobni **Qayta hisoblash** |
| Hisob-kitobdan "choy puli kassadan berilgan" ayrildi, kerak emas edi | Qoidada **Choy pulini qo'shish** qismi bor. Qismi bo'lmasa, kassadan berilgan choy puli ayrilmaydi |
| Hisob-kitobda "qoidasi yo'q" ogohlantirishi | **Ish haqi qoidalari** da shu xodim yoki roli uchun qoida qo'shing; kuchga kirish sanasi davr boshidan oldin bo'lsin; **Qayta hisoblash** |
| Hisob-kitobda "ish vaqti yopilmagan" | **Ish vaqti** bo'limida ketgan vaqtni kiriting (sabab bilan) |
| To'lanadigan summa kutilgandan kam | **Avans** ayrilgan bo'lishi mumkin (kassadan berilgan avans va kunlik ish haqi), yoki qoidada **Choy pulini qo'shish** qismi bo'lib, kassadan choy puli berilgan. Qatorni ochib qismlarni ko'ring |
| Ofitsiant "sotuvim kam" deydi | Stol o'tkazilgan bo'lishi mumkin: **Stol boshqa ofitsiantga o'tsa, sotuv** sozlamasini tekshiring (3.3) |
| "Kassadan to'lash" ishlamayapti: "ochiq smena yo'q" | Filialda smenani oching. Kassadagi naqddan ko'p to'lab bo'lmaydi |
| Yopilgan hisob-kitobni o'zgartirmoqchiman | Hech bir qatori to'lanmagan bo'lsa **Qayta ochish** (sabab bilan), tuzating, qayta **Yopish**. To'langan qatorli hisob-kitob qayta ochilmaydi |
| Qoida o'zgargan, lekin eski davr eski hisoblangan | To'g'ri: har kun o'sha kunda kuchdagi qoida bilan hisoblanadi |
