# Hamkor paneli: ega va menejer uchun qo'llanma

Hamkor paneli (**Rizqim Hamkor**) — restoran egasi va menejeri uchun veb-sahifa. Kompyuterda yoki planshetda ishlaydi.
Buyurtmalarni qabul qilasiz, menyuni yuritasiz, xodimlarni qo'shasiz, hisobotlarni ko'rasiz.

Telefondan ishlash kerak bo'lsa, [Telegram Mini App qo'llanmasiga](mini-app-xodim.md) qarang.
Oshxona planshetini ulash — shu qo'llanmaning 8-bo'limida; oshpaz ishi — [oshxona ekrani qo'llanmasida](oshxona-ekrani.md).
Kassa tizimini (zal xaritasi, xona narxi, fiskal chek, printerlar) sozlash — [ega-pos-sozlash.md](ega-pos-sozlash.md); kassir ishi — [kassa.md](kassa.md).

## Mundarija

1. [Kim nimani ko'radi](#1-kim-nimani-koradi)
2. [Panelga kirish](#2-panelga-kirish)
3. [Yuqori panel va filial tanlash](#3-yuqori-panel-va-filial-tanlash)
4. [Buyurtmalar doskasi](#4-buyurtmalar-doskasi)
5. [Menyu](#5-menyu)
6. [Stop-list va qozon](#6-stop-list-va-qozon)
7. [Xodimlar](#7-xodimlar)
8. [Sozlamalar va qurilmalar](#8-sozlamalar-va-qurilmalar)
9. [Filiallar va ish vaqti](#9-filiallar-va-ish-vaqti)
10. [Yetkazish zonalari](#10-yetkazish-zonalari)
11. [Hisobotlar va CSV](#11-hisobotlar-va-csv)
12. [Moliya](#12-moliya)
13. [Xatolar va yechimlar](#13-xatolar-va-yechimlar)

## 1. Kim nimani ko'radi

Chap tomondagi menyuda faqat sizga ruxsat berilgan bo'limlar ko'rinadi. Bo'lim ko'rinmasa — sizda unga ruxsat yo'q.
Ruxsatni ega beradi.

| Bo'lim | Ega | Menejer |
|---|:-:|:-:|
| Boshqaruv paneli | ha | ha |
| Buyurtmalar | ha | ha |
| Menyu | ko'radi va o'zgartiradi | faqat ko'radi |
| Stop-list | ha | ha |
| Qozon | ha | ha |
| Xodimlar | ha | ha, o'zidan past rollar bilan |
| Filiallar | ha, hammasi | faqat o'z filiali |
| Yetkazish zonalari | ha | yo'q |
| Sozlamalar: Do'kon, Xizmat haqi | ha | yo'q |
| Sozlamalar: Qurilmalar | ha | ha |
| Kassa sozlamalari | ha | yo'q |
| Huquqlar | ha | yo'q |
| Hisobotlar | ha | ha |
| Moliya | ha | yo'q |

Muhim:

- Menejer filialga biriktirilgan bo'lsa, u faqat o'z filialidagi buyurtmalar, xodimlar va qurilmalarni ko'radi.
- Panel ega va menejer uchun mo'ljallangan. Kassir, ofitsiant, oshpaz va kuryer uchun alohida ilovalar bor.
- Ruxsatlar rolga qarab beriladi. Ega ularni **Huquqlar** bo'limida o'zgartirishi mumkin (masalan, menejerga Moliyani ko'rishni berish).
  Shunda menyuda yangi bo'lim paydo bo'ladi. [Huquqlar](#huquqlar-xodim-nimani-ozi-qiladi) haqida pastda.
- Menyuni menejer hozircha faqat ko'radi (keyinchalik o'zgarishi mumkin).

## 2. Panelga kirish

Parol yo'q. Telefon raqamingizga SMS kod keladi.

1. Panel manzilini oching va **Hamkor paneliga kirish** sahifasida **Telefon raqami** maydoniga raqamingizni yozing.
   Faqat +998 dan keyingi 9 ta raqamni yozing: `90 123 45 67`.
2. **Kod yuborish** tugmasini bosing.
3. SMS orqali kelgan 6 xonali kodni **SMS kod** maydoniga yozing.
4. **Kirish** tugmasini bosing.

Kod kelmasa, 60 soniya kuting va **Kodni qayta yuborish** tugmasini bosing. Raqam xato yozilgan bo'lsa, **Raqamni o'zgartirish** ni bosing.
Juda ko'p urinsangiz, "Juda ko'p urinish" xabari chiqadi. Biroz kutib, qayta urining.

**Qaysi raqam bilan kirasiz?** Faqat "kirish raqami" sifatida yozilgan raqam bilan. Ega uchun bu — do'kon ochilganda yozilgan raqam.
Boshqa xodimlar uchun — [Xodimlar](#7-xodimlar) bo'limida egasi yozgan raqam. Raqamingiz xodim sifatida topilmasa,
panel sababini yozadi. Ega bilan bog'laning.

**Ikki do'konda ishlasangiz.** Bitta raqam bir nechta do'konda bo'lsa, kodni kiritgandan keyin **Qaysi hisobga kirasiz?**
ro'yxati chiqadi. Do'kon nomi, rol va filialga qarab kerakli qatorni bosing. Notanish do'konni tanlamang.

**Chiqish.** Yon menyuning pastidagi **Chiqish** tugmasi sizni tizimdan chiqaradi. Umumiy kompyuterda ishlagan bo'lsangiz, albatta chiqing.
Panel bir nechta brauzer varag'ida ochiq bo'lsa, ular bitta sessiyada ishlaydi: bittasida chiqsangiz, hammasidan chiqasiz (uzoq
turib qolgan varaqdan chiqqanda ham sessiya serverda yopiladi).

**Til.** Menyudagi **Til** orqali o'zbekcha, ruscha yoki inglizcha tanlanadi.

## 3. Yuqori panel va filial tanlash

Sahifaning yuqorisida:

- **Filial** — qaysi filial bilan ishlayotganingiz. Ega va filialsiz menejer **Barcha filiallar** yoki bitta filialni tanlaydi.
  Tanlov shu brauzerda eslab qolinadi. Filialga biriktirilgan menejerda **Filialingiz: ...** deb yoziladi, tanlab bo'lmaydi.
  Buyurtmalar, hisobotlar, stop-list, qozon va taomning **Filiallar** bo'limi tanlangan filialga qarab ishlaydi. **Barcha filiallar**
  tanlangan bo'lsa, stop-list, qozon va **Filiallar** bo'limi sahifaning o'zida filialni so'raydi.
- **Jonli** — yangi buyurtmalar sahifani yangilamasdan o'zi chiqadi. **Ulanmoqda...** yoki **Har 15 soniyada yangilanadi**
  yozuvi bo'lsa, ulanish vaqtincha yo'q (yoki hali o'rnatilmoqda): ro'yxat har 15 soniyada o'zi yangilanadi. Hech narsa qilish shart
  emas. Ulanib bo'lmasa, **Ulanmoqda...** o'rniga **Har 15 soniyada yangilanadi** yoziladi.
- Karnay belgisi — yangi buyurtma ovozini o'chiradi va yoqadi (**Yangi buyurtma ovozini o'chirish** / **yoqish**).
- Menyudagi **Buyurtmalar** yonida va brauzer sarlavhasida yangi buyurtmalar soni ko'rinadi, masalan `(3)`.

## 4. Buyurtmalar doskasi

**Boshqaruv paneli** — bugungi qisqa xulosa: buyurtmalar soni, tushum, o'rtacha chek, faol, yetkazilgan va bekor qilingan
buyurtmalar, eng ko'p sotilgan taomlar. Yuqorida **Hozir** bloki bor: doskadagi holat va **Doskani ochish** tugmasi.

**Buyurtmalar** bo'limi — asosiy ish joyi. Sahifani ochiq qoldirsangiz, yangi buyurtmalar o'zi paydo bo'ladi.

### Ovoz

Yangi buyurtma kelsa, signal chalinadi. Brauzer ovozga faqat siz sahifaning istalgan joyini bir marta bosganingizdan keyin ruxsat beradi.
Sahifa ochilganda **Ovoz chiqishi uchun sahifaning istalgan joyini bir marta bosing** yozuvi chiqsa, shuni bajaring.
Ovozni karnay belgisi bilan o'chirasiz. Tanlov brauzerda eslab qolinadi.

### Ustunlar

Buyurtmalar ustunlarga bo'lingan. Har bir ustunda nechta buyurtma borligi yozilgan.

| Ustun | Ma'nosi |
|---|---|
| **Yangi** | Hali qabul qilinmagan. Birinchi navbatda shuni ko'ring |
| **Tayyorlanmoqda** | Qabul qilingan, oshxonada |
| **Tayyor** | Pishgan, mijoz yoki kuryer kutmoqda |
| **Yo'lda** | Kuryer biriktirilgan yoki buyurtma bilan yo'lda |
| **Bugun yakunlangan** | Bugun yetkazilgan, bekor qilingan yoki rad etilgan. Ustun nomini bossangiz, yig'iladi |

Ustun ichida eng eski buyurtma tepada turadi. Yakunlangan ustunda — eng yangisi.

### Buyurtma kartochkasi

Kartochkada: raqam (`#E101`), turi (**Yetkazish**, **Olib ketish**, **Zalda**), kanal (**Telegram**, **Mobil ilova**, **Zal (POS)**,
**Olib ketish (POS)**, **Agregator**), mijoz ismi, birinchi 3 ta taom, summa va **kutish vaqti**.
Oshxonada bo'lgan buyurtmada **Oshxona: 2/5** yozuvi bor — 5 ta taomdan 2 tasi tayyor.
Kartochka tagidagi tugma — keyingi qadam. Kartochkani bossangiz, yon tomondan to'liq ma'lumot ochiladi.

### Ranglar nimani bildiradi

- **Kutish vaqti** (soat belgisi bilan): kulrang — hammasi joyida. **Sariq** — shoshiling. **Qizil** — kechikdi.
  Yangi buyurtma uchun sariq 5 daqiqadan, qizil 10 daqiqadan boshlanadi. Qolganlari uchun — 20 va 40 daqiqadan.
- **Yangi** buyurtmaning chap cheti sariq chiziq bilan ajratilgan.
- **Boshqaruv paneli**da "10 daqiqadan ortiq qabul qilinmagan" buyurtmalar soni yozilgan, **Doskani ochish** tugmasi doskaga olib boradi.
- Holat belgisi: sariq — qabul kutmoqda; ko'k — jarayonda; yashil — yetkazildi; qizil — bekor qilindi yoki rad etildi;
  kulrang — qaytarildi. Rang bilan birga yozuv ham bor.

### Qidirish va filtrlar

Doska tepasida: **Buyurtma raqami** (masalan, `A105`), **Turi** va **Kanal**. Hammasini qaytarish uchun **Filtrlarni tozalash**.
Doskada eng so'nggi 500 ta faol buyurtma ko'rsatiladi.

### Buyurtmani qabul qilish va keyingi qadamlar

Kartochkadagi tugma buyurtma holatiga qarab o'zgaradi:

| Buyurtma holati | Tugma | Natija |
|---|---|---|
| Yangi | **Qabul qilish** | Buyurtma qabul qilinadi |
| Qabul qilindi | **Tayyorlashni boshlash** | Oshxonaga ketadi |
| Tayyorlanmoqda | **Tayyor bo'ldi** | Tayyor ustuniga o'tadi |
| Tayyor, yetkazish | **Kuryerni biriktirish** | Kuryer tanlanadi |
| Tayyor, olib ketish yoki zal | **Mijozga topshirish** | Buyurtma yakunlanadi |
| Kuryer kutilmoqda | **Yo'lga chiqdi** | Kuryer yo'lga chiqdi |
| Yo'lda | **Yetkazib berildi** | Buyurtma yakunlanadi |

Oshxonada KDS ekrani bo'lsa, oshpaz o'zi taomlarni "tayyor" deb belgilaydi. Buyurtma ham shunga qarab o'zgaradi.

### Buyurtmani rad etish yoki bekor qilish

Bu tugmalar kartochkada emas, kartochkani ochganda **Amallar** bo'limida turadi (qizil tugma).

1. Buyurtma kartochkasini bosib oching.
2. **Amallar** bo'limida **Rad etish** (yangi buyurtma uchun) yoki **Bekor qilish** (qabul qilingan buyurtma uchun) tugmasini bosing.
3. **Sababni tanlang** ro'yxatidan sababni belgilang. Sabablar: **Mijoz so'rovi**, **Mijoz kelmadi** (faqat olib ketish uchun),
   **Mahsulot qolmagan**, **Do'kon yopiq**, **Kuryer yo'q** (faqat yetkazish uchun), **To'lov muddati o'tdi**, **Boshqa sabab**.
4. Xohlasangiz **Izoh (ixtiyoriy)** yozing.
5. **Rad etish** yoki **Bekor qilish** tugmasi bilan tasdiqlang. Fikringiz o'zgarsa — **Orqaga**.

Sababsiz rad etib bo'lmaydi: "Sababni tanlang." xabari chiqadi. Sabab buyurtma tarixida saqlanadi.

### Kuryer biriktirish

1. Yetkazish buyurtmasi **Tayyor** ustuniga o'tgach, **Kuryerni biriktirish** tugmasini bosing.
2. **Kuryerni biriktirish** oynasida ro'yxatdan kuryerni tanlang.
3. **Tayinlash** ni bosing.

"Bu filialda bo'sh kuryer yo'q" chiqsa, kuryer hali qo'shilmagan yoki boshqa filialda. [Xodimlar](#7-xodimlar) bo'limida kuryerni qo'shing.
Buyurtma tafsilotida kuryer ismi ko'rinadi.

### Buyurtma ma'lumoti (yon oyna)

Kartochkani bossangiz: taomlar (har birining oshxonadagi holati: **Kutmoqda** / **Tayyor**), mijoz izohi, mijoz ismi va
telefoni (bosib qo'ng'iroq qilasiz), yetkazish manzili va zona, to'lov usuli va holati, hisob (**Taomlar**, **Chegirma**, **Yetkazish**,
**Xizmat haqi**, **Joy narxi**, **Jami**) va **Holatlar tarixi**. **Yopish** oynani yopadi.

### Amal bajarilmasa

Qizil xabar sababini yozadi. Eng ko'p uchraydigani: **Buyurtma holati allaqachon o'zgargan** — boshqa qurilma yoki oshxona
buyurtmani o'zgartirgan. Ro'yxat o'zi yangilanadi, qayta urining.

## 5. Menyu

**Menyu** bo'limida ikkita ro'yxat bor: **Taomlar** va **Modifikatorlar**. Menyuni faqat ega o'zgartiradi.
O'zgartirish huquqi bo'lmasa, tepada "Sizda menyuni o'zgartirish huquqi yo'q: faqat ko'rish mumkin." yozuvi chiqadi.

### Taom qo'shish

1. **Menyu** → **Taomlar** → **Taom qo'shish**.
2. **Asosiy ma'lumotlar**: **Nomi**, **Kategoriya**, **Tavsif**, **Tartib raqami** (kichik raqam oldinroq turadi), **Rasm tanlash**
   (JPG, PNG yoki WebP, 5 MB gacha).
3. **Faol (mijozlarga ko'rinadi)** belgisi yoqilgan bo'lsin. O'chirsangiz, taom mijozlarga ko'rinmaydi.
4. **Variantlar va narxlar** bo'limida kamida bitta variant bo'lsin: **Variant nomi** (masalan, "Mini", "Katta", "1 litr") va **Narxi (so'm)**.
   Yana variant kerak bo'lsa — **Variant qo'shish**. Ortiqchasini **Variantni o'chirish** bilan olib tashlaysiz.
5. **Saqlash**.

Taom saqlangandan keyin pastda yana bo'limlar ochiladi: **Modifikator guruhlari**, **Kanal narxlari va ko'rinishi**, **Filiallar**.

### Variantlar

Variant — bir taomning o'lchami yoki turi: kichik va katta porsiya, 0,5 litr va 1 litr. Har birining o'z narxi bor.
Bitta narxli taomda faqat bitta (**Standart**) variant bo'ladi.

### Kilogrammda sotiladigan taom

1. Variantda **O'lchov birligi** ni **kg** (yoki **litr**) qiling. Birlik **Dona** bo'lsa — odatiy taom.
2. **1 kg narxi (so'm)** maydoniga bir kilogramm narxini yozing.
3. **Qadam (kg)**: mijoz miqdorni shu qadam bilan o'zgartiradi. Bo'sh qoldirsangiz, 0,1.
4. **Eng kam miqdor (kg)**: qadamga karrali bo'lsin. Bo'sh qoldirsangiz, bitta qadam.

Masalan: narx 80 000, qadam 0,1, eng kam 0,5 — mijoz kamida 0,5 kg (40 000 so'm) buyurtma qiladi.
Qoldiq bu yerda o'zgartirilmaydi: **qozon** yoki **stop-list**dan foydalaning.

### Modifikatorlar (sous, qo'shimcha)

Modifikator — taomga qo'shiladigan tanlov: "Sous tanlang", "Qo'shimchalar".

1. **Menyu** → **Modifikatorlar** → **Guruh qo'shish**.
2. **Guruh nomi** (masalan, "Sous tanlang"), kerak bo'lsa ruscha nomi.
3. Qoidalar: **Eng kam tanlov**, **Eng ko'p tanlov** (bo'sh — cheklanmagan), **Majburiy tanlov** (mijoz kamida bittasini tanlashi shart).
4. **Variantlar** bo'limida har bir tanlovga **Nomi** va **Narx qo'shimchasi (so'm)** yozing (bo'sh — bepul).
   Donali taomda qo'shimcha har bir donaga, kilogrammli taomda butun qatorga bir marta qo'shiladi.
5. **Saqlash**. Guruh saqlangach, **Qaysi taomlarda taklif qilinadi** bo'limida taomlarni belgilab, **Taomlarni saqlash** ni bosing.

Yoki taom oynasidagi **Modifikator guruhlari** bo'limidan **Taomda taklif qilinadi** ni belgilab, **Guruhlarni saqlash** ni bosing
(saqlangach **Saqlandi.** yozuvi chiqadi).
Guruh yo'q bo'lsa, avval **Modifikatorlar** bo'limida yarating.

### Kanal narxlari

Bitta taom Telegramda va mobil ilovada har xil narxda sotilishi mumkin.

1. Taomni oching → **Kanal narxlari va ko'rinishi**.
2. **Telegram** va **Mobil ilova** uchun alohida **Kanal narxi** yozing. Bo'sh qoldirsangiz, asosiy narx ishlaydi.
3. Taomni (yoki bitta variantni) kanalda ko'rsatmaslik uchun **Taomni butunlay yashirish** yoki **Yashirilgan** belgisini qo'ying.
   Yashirilgan taom o'sha kanalda ko'rinmaydi va buyurtma qilinmaydi.
4. **Kanal narxlarini saqlash**.

### Filialda sotilmaydigan variant

Taom oynasining **Filiallar** bo'limida (yuqori panelda tanlangan filial; **Barcha filiallar** bo'lsa, shu yerda filialni tanlang)
u yerda sotilmaydigan variantni o'chiring. Bu stop-listda ham ko'rinadi.

### "Qozon bo'yicha sotiladi"

Osh kabi qozonda pishadigan taomlar uchun **Qozon bo'yicha sotiladi** ni yoqing. Filialda ochiq qozon bo'lmasa, taom **tugadi** deb ko'rsatiladi.
**Qozon bo'yicha** yorlig'i ro'yxatda ko'rinadi. [Stop-list va qozon](#6-stop-list-va-qozon) bo'limiga qarang.

### Taomni o'chirish

Taom qatoridan **O'chirish** → tasdiqlang. Oldingi buyurtmalar o'zgarmaydi. Vaqtincha sotmaslik kerak bo'lsa — o'chirmang,
**Faol** belgisini olib tashlang yoki stop-listga qo'ying.

## 6. Stop-list va qozon

Ikkalasi ham filial bo'yicha ishlaydi. Sahifa tepasidagi ikkita yorliq (**Stop-list** va **Qozon**) orqali o'tasiz.
Filial — yuqori paneldagi tanlov (sahifada **Filial: ...** deb yoziladi). Yuqorida **Barcha filiallar** tanlangan bo'lsa,
sahifaning o'zida **Filial** ro'yxati chiqadi: avval to'g'ri filialni tanlang. Menejerda doim o'z filiali.

### Stop-list: taom tugadi

1. **Stop-list** bo'limini oching.
2. **Menyu** ro'yxatidan tugagan taomni toping (qidiruv yordam beradi).
3. **Tugadi** tugmasini bosing. Taom shu zahoti mijozlarga sotilmaydi.
4. Qaytarish uchun: **Sotuvga qaytarish**.

Faqat bitta variant yoki qo'shimcha tugagan bo'lsa ham shu yerdan belgilanadi. Ro'yxat turlari: **Taom**, **Variant**, **Qo'shimcha**.

Vaqtincha to'xtatish: taom qatoridagi soat belgisini bosing (**Sabab va vaqt bilan to'xtatish**), **Sabab (ixtiyoriy)** va
**Qachongacha (ixtiyoriy)** ni yozing. Vaqt o'tgach taom o'zi sotuvga qaytadi.

Tepada **Hozir stop-listda: 3** deb nechta taom to'xtatilgani yoziladi.

### Qozon

Qozon — bir marta pishirilgan va porsiyalab sotiladigan taom (osh, sho'rva). Qoldiq har buyurtmada o'zi kamayadi.
Sahifa har 5 soniyada yangilanadi.

1. **Qozon** bo'limida **Qozon ochish** ni bosing.
2. **Taom** va kerak bo'lsa **Variant** ni tanlang (variant tanlanmasa, taomning hamma variantlari shu qozondan oladi).
3. **Qancha tayyorlandi** — porsiya yoki kg hisobida.
4. **Izoh (ixtiyoriy)**: masalan, "Tushlik qozoni". Qozonni ochish bilan tasdiqlang.

Qozon ochilgach, kartochkada **Qoldiq** va holati (**Ochiq**, **Tugadi**, **Yopilgan**) ko'rinadi. Tugmalar:

- **To'ldirish** — yana pishirilganda **Qancha qo'shildi** ni yozing.
- **Qoldiqni to'g'rilash** — haqiqiy qoldiq boshqacha bo'lsa, **Haqiqiy qoldiq** ni yozing.
- **Yopish** — qozon tugadi. Qolgan miqdor sotilmaydi.

Qozon tugasa, taom o'zi "tugadi" bo'ladi. **Yopilganlarni ko'rsatish** eski qozonlarni ko'rsatadi.
Oshpaz ham qozonni ocha va yopa oladi.

## 7. Xodimlar

**Xodimlar** bo'limida har bir xodimning ismi, roli, filiali, kirish usuli va PIN holati ko'rinadi.
Qidiruv: ism, telefon yoki xodim kodi. **Barcha rollar** ro'yxati rol bo'yicha saralaydi.

### Rollar

| Rol | Nima qiladi |
|---|---|
| **Ega** | Hamma narsa. Egani o'zgartirib bo'lmaydi |
| **Menejer** | Buyurtmalar, stop-list, qozon, xodimlar PIN'i, qurilmalar, hisobotlar |
| **Kassir** | Kassa: buyurtma va to'lov qabul qilish |
| **Ofitsiant** | Zal buyurtmalari |
| **Oshpaz** | Oshxona ekrani, qozon |
| **Kuryer** | Yetkazish |

Rolni faqat o'zingizdan past darajada bera olasiz. Ega menejer qo'sha oladi, menejer esa kassir, ofitsiant, oshpaz va kuryer qo'shadi.
Bo'lmasa server rad etadi.

### Xodim qo'shish

1. **Xodimlar** → **Xodim qo'shish**.
2. **Ismi**, **Roli**, **Filial** (yoki **Butun do'kon**) ni tanlang.
3. **Kirish telefoni** — ixtiyoriy. Raqam yozsangiz, xodim shu raqamga kelgan SMS kod bilan panelga va ilovalarga kiradi.
   Bo'sh qoldirsangiz, xodim faqat taklif havolasi orqali ulanadi.
4. Saqlang. **Taklif havolasi** oynasi chiqadi. **Nusxalash** ni bosib, havolani xodimga yuboring (Telegram'da bosganda ulanadi).

Havola faqat hozir ko'rsatiladi, keyin xodimlar ro'yxatida chiqmaydi: nusxalab oling. Havola yo'qolsa, qalam belgisi (tahrirlash)
orqali xodimga **Kirish telefoni** yozing: u SMS kod bilan kiradi.

Ro'yxatdagi belgilar: **SMS bilan kiradi** yoki **Kirish raqami yo'q**; **PIN bor** yoki **PIN yo'q**; **Telegram ulangan**;
**Taklif kutilmoqda** (xodim havolani hali bosmagan); **Faqat ko'rish** (bu xodim bilan ishlash huquqingiz yo'q).

### Rolni o'zgartirish

Faqat ega qila oladi: qator ichidagi rol tugmasini (qalqon belgisi) bosing, yangi rol va filialni tanlang. Ega o'z rolini o'zgartira olmaydi.

### PIN kod (oshxona ekrani va kassa uchun)

PIN — 4 dan 6 gacha raqam. Xodim kassa yoki oshxona planshetiga shu PIN bilan kiradi. Panelga PIN bilan kirib bo'lmaydi.

1. Xodim qatoridagi kalit belgisini bosing (**PIN kod**).
2. **PIN kod** maydoniga yangi PIN yozing. Oddiy kodlar (0000, 1234) qabul qilinmaydi.
3. **PIN o'rnatish** (yoki **PINni almashtirish**).
4. **PIN saqlandi** oynasida **Xodim kodi** chiqadi. PIN'ni xodimning o'ziga ayting. Boshqalarga bildirmang.

PIN'ni olib tashlash: **PINni o'chirish** → **Ha, o'chirish**. Xodim yangi PIN qo'yilguncha terminalga kira olmaydi.
PIN almashsa yoki o'chirilsa, xodimning terminaldagi sessiyasi darhol yopiladi.

PIN faqat ega, menejer, kassir, ofitsiant va oshpaz uchun. Menejer PIN'ni o'z filialidagi o'zidan past xodimlarga va o'ziga qo'ya oladi.

### Kirish telefoni va nega uni o'zgartirib bo'lmaydi

Kirish telefoni — xodim SMS orqali kiradigan raqam. Uni ikki yo'l bilan qo'yish mumkin:

- **Siz yozasiz** (xodim formasida). Bu "siz kafolatlagan" raqam.
- **Xodim o'zi tasdiqlaydi**: ilovada SMS kod kiritib raqamni isbotlaydi. Bu "SMS bilan tasdiqlangan" raqam.

Xodim o'zi SMS bilan tasdiqlagan raqamni siz o'zgartira olmaysiz. Raqamni o'zgartirmoqchi bo'lsangiz, panel
"Bu raqamni xodimning o'zi SMS kod bilan tasdiqlagan, uni bu yerda o'zgartirib bo'lmaydi." deb yozadi va maydonni qulflaydi.
**Eski raqamni qaytarish** tugmasi avvalgi raqamni tiklaydi.

Sababi: xavfsizlik. Aks holda kimdir raqamni almashtirib, boshqa odamning hisobiga kirishi mumkin edi.
Boshqa maydonlarni (ism, filial) saqlash raqamni o'zgartirmaydi.

Raqam almashishi kerak bo'lsa:

1. Xodim yangi raqamini ilovada SMS kod bilan tasdiqlaydi; yoki
2. Ishdan ketgan xodimni o'chirib, yangisini qo'shing.

Siz yozgan raqamni o'zgartirsangiz yoki o'chirsangiz, xodimning ilovalardagi barcha sessiyalari yopiladi va u qayta kirishi kerak bo'ladi.
Har bir o'zgarish harakatlar jurnaliga yoziladi.

### Xodimni o'chirish

Qatordagi chiqindi savat belgisi → **O'chirish**. Xodim do'kondan olib tashlanadi va ilovalarga kira olmaydi. Buni faqat ega qiladi.

### Huquqlar: xodim nimani o'zi qiladi

Faqat ega ko'radi. **Huquqlar** bo'limida har bir lavozim (Menejer, Kassir, Ofitsiant, Oshpaz, Kuryer) uchun oddiy tilda yozilgan ro'yxat turadi, masalan
"Qaytim qilish — mijozga pulni qaytaradi". Tugmani yoqsangiz, xodim bu ishni o'zi qiladi; o'chirsangiz, kassa menejer PIN'ini so'raydi.
O'zgarish darhol saqlanadi. Pul bilan bog'liq ishlar (qaytim, bekor qilish, chegirma, kassadan pul chiqarish) alohida belgilangan va ruxsat berishdan
oldin tizim so'raydi. O'zgargan qator **O'zgartirilgan** deb belgilanadi, **Standartga qaytarish** tugmasi uni Rizqim'ning standart holatiga qaytaradi.
To'liq tushuntirish va kassirning standart qoidalari — [Kassa sozlash qo'llanmasi](ega-pos-sozlash.md#huquqlar-sahifasi-xodim-nimani-ozi-qiladi).

## 8. Sozlamalar va qurilmalar

**Sozlamalar** da uchta ichki bo'lim: **Do'kon**, **Xizmat haqi**, **Qurilmalar**. Menejerga faqat **Qurilmalar** ochiq.

### Do'kon

- **Do'kon ma'lumotlari**: **Do'kon nomi**, **Vaqt mintaqasi** (masalan, `Asia/Tashkent`; hisobotlar va ish vaqti shu bo'yicha),
  **Do'kon telefoni**, **Tavsif**, **Manzil**, **Xarita havolasi**.
- **Buyurtma qabul qilish**: **Do'kon ochiq** (o'chirilsa, buyurtma qabul qilinmaydi), **Yetkazib berish**, **Olib ketish**,
  **Yetkazish narxi**, **Eng kam buyurtma**, **Ochilish vaqti**, **Yopilish vaqti**.
  Yetkazish zonasi chizilgan bo'lsa, narx va eng kam buyurtma zonadan olinadi.
- **To'lov usullari**: **Naqd pul**, **Click**, **Payme**. **Tez orada** deb yozilganlari hali ulanmagan.

O'zgartirgach **Saqlash** ni bosing.

### Xizmat haqi

Zal buyurtmalari uchun: **Xizmat haqi** (hisobdan foiz, 0 dan 30 gacha) va **Joy narxi** (**Har bir mehmon uchun** yoki **Har bir stol uchun**).
Telegram orqali kelgan buyurtmalarga qo'llanmaydi. Filialga alohida sozlash uchun **Qaysi uchun** ro'yxatidan filialni tanlang.

### Qurilmalar: oshxona planshetini ulash

Bu yerda do'konga ulangan kassa (**Kassa (POS)**) va **Oshxona ekrani** planshetlari ko'rinadi.
Har bir qurilmada nomi, holati (**Faol** / **O'chirilgan**) va **Oxirgi faollik** yoziladi.

Yangi planshetni ulash:

1. **Sozlamalar** → **Qurilmalar** → **Ulash kodini yaratish**.
2. **Qurilmani ulash** oynasida **Turi** ni tanlang: **Oshxona ekrani**.
3. **Filial** ni tanlang (oshxona ekrani uchun filial shart).
4. **Nomi** ni yozing, masalan "Oshxona 1" (bo'sh qoldirsangiz, o'zi nomlanadi).
5. **Kod yaratish** ni bosing.
6. **Ulash kodi** chiqadi: `ABCD-EFGH` ko'rinishida 8 belgi. Kod **10 daqiqa** amal qiladi va faqat **bir marta** ishlaydi.
   Kodni **Nusxalash** mumkin.
7. Planshetda oshxona ilovasini oching, kodni kiriting va **Ulash** ni bosing (batafsil: [oshxona ekrani qo'llanmasi](oshxona-ekrani.md)).
8. Planshet ulangach, u **Qurilmalar** ro'yxatida paydo bo'ladi.

Kod muddati tugasa, **Yangi kod** ni bosing. Bir do'konda bir vaqtda ko'pi bilan 5 ta faol kod bo'ladi.

Qurilma bilan boshqa amallar (qalam va to'siq belgilari):

- **Nomini o'zgartirish**.
- **O'chirish**: qurilma darhol tizimdan chiqariladi, qayta ulash uchun yangi kod kerak. Planshet yo'qolsa yoki almashtirilsa, shunday qiling.
- **O'chirilganlarni ham ko'rsatish** — eski qurilmalarni ko'rsatadi.

Eslatma: kassa va oshxona ekrani terminallari soni oylik to'lovga ta'sir qilishi mumkin (tarifga qarang, [Moliya](#12-moliya)).

## 9. Filiallar va ish vaqti

**Filiallar** bo'limida har bir filial kartochkasi ko'rinadi: nomi, **Hozir ochiq** / **Hozir yopiq**, ish vaqti, manzil, telefon va **Yetkazish radiusi**.

### Ish vaqtini o'zgartirish

1. Filial kartochkasidagi qalam belgisini bosing.
2. **Ish vaqti** bo'limida **Ochilish** va **Yopilish** vaqtini yozing.
3. Kechasi ishlaydigan filial uchun yopilish vaqtini ochilishdan oldingi vaqt qiling (masalan, 18:00 – 02:00). Panel **tungi** deb belgilaydi.
4. Sutkasiga ochiq filial uchun **24 soat** ni belgilang.
5. **Holati**: **Faol**, **Faol emas**, **Vaqtincha yopiq**. Vaqtincha yopiq yoki faol bo'lmagan filialga buyurtma tushmaydi.
6. **Saqlash**.

### Boshqa maydonlar (ega)

**Telefon**, **Manzil**, **Viloyat**, **Tuman**, **Kenglik** / **Uzunlik** (xaritadagi nuqta; Yandex yoki Google xaritasida nuqtani bosib olinadi),
**Yetkazish radiusi**.

### Filial qo'shish va o'chirish (faqat ega)

- **Filial qo'shish** — yangi filial formasi.
- Chiqindi savat belgisi — filialni o'chiradi. Buyurtmalari bor filial yoki do'konning yagona filialini o'chirib bo'lmaydi.
  Bunday holda **Faol emas qilish** tugmasi chiqadi: filial o'chmaydi, lekin buyurtma qabul qilmaydi.

Menejer faqat o'z filialining ma'lumotlarini va ish vaqtini o'zgartiradi.

## 10. Yetkazish zonalari

Faqat ega uchun. Zona — xaritada chizilgan hudud, uning o'z yetkazish narxi va shartlari bor.
Zona chizilmaguncha yetkazish filial radiusi va do'kon narxi bilan ishlaydi. Birinchi zona chizilgan zahoti zonalar rejimiga o'tadi.

### Zona chizish

1. **Yetkazish zonalari** → **Yangi hudud**.
2. Formada: **Nomi** (masalan, "Markaz"), kerak bo'lsa **Filial** (tanlansa, bu hududdagi buyurtmani faqat shu filial qabul qiladi;
   bo'sh bo'lsa — **Eng yaqin ochiq filial**).
3. Narx va shartlar: **Yetkazish narxi**, **Eng kam buyurtma**, **Bepul yetkazish chegarasi**, **Taxminiy vaqt**.
   Bepul chegara bo'sh bo'lsa — hech qachon bepul emas; 0 bo'lsa — doim bepul.
4. **Ustuvorlik**: zonalar ustma-ust tushsa, kattasi tanlanadi.
5. Xaritada **Yangi shakl** ni bosing, nuqtalarni birma-bir bosib chizing, so'ng birinchi nuqtani bosing yoki **Shaklni yopish**.
   Xato nuqtani **Oxirgi nuqtani olib tashlash** bilan qaytaring, **Hammasini tozalash** — qaytadan boshlash.
6. **Hududni saqlash**.

Xaritada qizil nuqtalar — filiallar; yashil hudud — faol zona; kulrang — faol emas; to'q sariq — tanlangan zona.

Zona shaklini o'zgartirish: ro'yxatdan zonani oching, nuqtani sudrab joyini o'zgartiring, shakl chetini bosib nuqta qo'shing,
nuqtani o'ng tugma bilan bosib (telefonda uzoq bosib) o'chiring. **Hudud faol** belgisini olib tashlasangiz, zonaga yetkazilmaydi.
O'chirish: **Hududni o'chirish**. Eski buyurtmalarda zona nusxasi qoladi.

Xarita yuklanmasa, **GeoJSON** maydoniga tayyor shaklni qo'yib **Xaritaga qo'llash** ni bosish mumkin.

## 11. Hisobotlar va CSV

**Hisobotlar** — savdo ko'rsatkichlari tanlangan davr bo'yicha.

1. **Davr** ni tanlang: **Bugun**, **Kecha**, **Oxirgi 7 kun**, **Oxirgi 30 kun**, **Shu oy** yoki **Boshqa davr**
   (shunda **Boshlanish sanasi** va **Tugash sanasi** yoziladi).
2. **Guruhlash**: **Kun**, **Hafta**, **Oy**.
3. Filial — yuqori paneldagi tanlovdan olinadi. Hisobot sarlavhasida qaysi filial va qaysi vaqt mintaqasi ekani yoziladi.

Sahifada: **Tushum**, **Buyurtmalar**, **O'rtacha chek**, **Bekor qilingan**; **Tushum dinamikasi** grafigi (**Grafik** / **Jadval**);
**Kanal bo'yicha**, **Filial bo'yicha**, **To'lov usuli bo'yicha**, **Buyurtma turi bo'yicha**; **Eng ko'p sotilgan taomlar**
(**Soni bo'yicha** / **Tushum bo'yicha**). Tushum faqat yetkazilgan buyurtmalardan hisoblanadi.

### CSV yuklab olish

CSV — Excel yoki Google Sheets'da ochiladigan fayl.

1. **Fayl turi** ni tanlang: **Buyurtmalar**, **Savdo** yoki **Taomlar**.
2. **CSV yuklab olish** ni bosing. Fayl kompyuteringizga tushadi: "Fayl yuklab olindi: ...".

Chegaralar: davrda buyurtmalar juda ko'p bo'lsa, "Bu davrda buyurtmalar juda ko'p" chiqadi: qisqaroq davr tanlang.
Juda tez-tez yuklasangiz (daqiqada 6 ta, kuniga 100 ta), biroz kutish kerak.

## 12. Moliya

Moliya bo'limi egaga ochiq (menejerga ega ruxsat bersa). Balans, pul yechish va to'ldirish tugmalari faqat egaga ko'rinadi.

- **Hamyon** — Rizqim sizga to'laydigan mablag'. Hamyonda doim **minimal qoldiq** qolishi kerak (sahifada yozilgan).
- **Qarz hisobi** — Rizqim oldidagi qarz (komissiya, obuna). Manfiy bo'lsa, oldindan to'langan. **Kredit limiti** va **Komissiya** foizi ham shu yerda.
- Qarz kredit limitidan oshsa, qizil xabar chiqadi: "Buyurtma qabul qilish hozir bloklangan". Balansni to'ldiring.

### Pul yechish

1. **Pul yechish** tugmasi.
2. **Summa (so'm)** va **Karta raqami** (16 ta raqam), kerak bo'lsa **Izoh (ixtiyoriy)**.
3. **So'rov yuborish**. Holatini **Pul yechish so'rovlari** ro'yxatida ko'rasiz (**Kutilmoqda**, **To'langan**, **Rad etilgan**).

### Balansni to'ldirish

1. **Balansni to'ldirish** tugmasi.
2. **Summa** va **To'lov tizimi** ni tanlang.
3. **To'lov sahifasini yaratish**, so'ng **To'lov sahifasini ochish**. To'lov tugagach balans yangilanadi.

### Tarixlar

Pastdagi bo'limlar: **Qarz hisobi**, **Hamyon tarixi**, **Onlayn to'lovlar**, **Pul yechish so'rovlari**, **Tarif va hisob-fakturalar**.
Hammasida **Turi**, **Holati** bo'yicha filtr va sahifalash bor.

### Tarif va hisob-fakturalar

- **Joriy tarif**, davr, sinov muddati tugashi, amaldagi komissiya, terminallar soni, asosiy oylik to'lov va terminal narxi.
- **Joriy davr uchun taxminiy hisob-faktura** — hozirgi hisob.
- **Hisob-fakturalar**: **Raqam**, **Davr**, **Summa**, **To'lov muddati** va holati (**To'lanmagan**, **To'langan**, **Bekor qilingan**).
  Hisob-faktura oy tugagach, keyingi oyning 1-sanasida chiqadi. Summa qarz hisobiga yoziladi va balansni to'ldirib to'lanadi.
  Sinov davrida hisob-faktura chiqmaydi.
- "Tarif biriktirilmagan" yozuvi bo'lsa, oylik to'lov yo'q, komissiya do'konning o'z foizida olinadi.
  Tarifni Rizqim jamoasi biriktiradi.

## 13. Xatolar va yechimlar

| Xabar yoki holat | Nima qilish kerak |
|---|---|
| **Ma'lumot yuklanmadi** | Internetni tekshiring, **Qayta urinish** ni bosing |
| **Serverga ulanib bo'lmadi** | Internetni tekshiring. Biroz kutib qayta urining |
| **Bu amal uchun ruxsatingiz yo'q** | Bu amalni sizning rolingiz qila olmaydi. Ega bilan gaplashing |
| **Bu hisob uchun panelga kirish yo'q** | Panel faqat ega va menejer uchun. Boshqa raqam bilan kiring |
| **Juda ko'p urinish** | 1–2 daqiqa kuting |
| SMS kelmayapti | 60 soniya kuting, **Kodni qayta yuborish**. Raqam kirish raqami sifatida yozilganini egadan so'rang |
| Yangi buyurtma ovozi yo'q | Sahifani bir marta bosing. Karnay belgisi yoqilganini tekshiring. Brauzer ovozni bloklamaganini ko'ring |
| Buyurtma o'zi chiqmayapti | **Jonli** o'rniga "Har 15 soniyada yangilanadi" yozilgan bo'lsa, kuting — ro'yxat baribir yangilanadi |
| Menyu bo'limi yo'q | Sizda menyuni ko'rish ruxsati yo'q |
| Planshet kodni qabul qilmayapti | Kod 10 daqiqada eskiradi va bir marta ishlaydi: **Yangi kod** yarating |
