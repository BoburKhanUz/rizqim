# Telegram Mini App: ega, menejer va kuryer uchun qo'llanma

Mini App — Telegram ichida ochiladigan ilova. Telefondan ishlash uchun qulay: buyurtmani qabul qilish, taom tugaganini
belgilash, kuryer sifatida yetkazish. Katta ishlar (menyu, hisobotlar, zonalar) uchun kompyuterdagi
[hamkor paneli](hamkor-panel.md) qulayroq.

## 1. Kirish

Parol yoki SMS kod kerak emas: ilova sizni Telegram hisobingiz orqali o'zi taniydi.

1. Telegramda restoran botini oching va ilovani ishga tushiring.
2. Siz egasi taklif qilgan xodim bo'lsangiz, ilova ish ekraningizni o'zi ochadi:
   ega uchun **Analitika**, menejer uchun **Buyurtmalar**, kuryer uchun **Yetkazmalar**.

Xodim sifatida qo'shilish uchun egadan **taklif havolasi** oling va uni Telegramda bosing. Bir martalik havola sizni xodim qilib ulaydi.

Muammo bo'lsa:

- **Kirish amalga oshmadi** — "Ilovani Telegram ichida qaytadan oching." Ilovani yopib, botdan qayta oching.
- Mijoz ekrani (katalog) ochilsa — siz hali xodim sifatida ulanmagansiz. Egadan taklif havolasini so'rang.
- **Internet aloqasi mavjud emas** — internetni tekshiring.

Tilni o'zgartirish: **Profil** → **Ilova tili** (O'zbekcha, Русский, English).

## 2. Telefonda nima bor, panelda nima bor

| Ish | Telefon (Mini App) | Kompyuter (hamkor paneli) |
|---|:-:|:-:|
| Buyurtmalarni qabul qilish, rad etish, kuryer tayinlash | ha | ha |
| Stop-list va qozon | ha | ha |
| Xodim qo'shish, PIN o'rnatish | ha (ega) | ha |
| Filial ish vaqti | ha | ha |
| Taom qo'shish va narxini o'zgartirish | ha, oddiy shaklda (ega) | ha, to'liq |
| Kg bo'yicha sotiladigan taom, modifikatorlar, kanal narxlari | yo'q | ha |
| Yetkazish zonalarini xaritada chizish | yo'q | ha |
| Filial manzili, hududi va radiusi | yo'q (panelda o'zgartiriladi) | ha |
| Hisobotlar, CSV | faqat qisqa **Analitika** (egada) | to'liq hisobot, CSV |
| Moliya: balans, pul yechish | ha (ega) | ha |
| Oshxona planshetini ulash | yo'q | ha |
| Rolni o'zgartirish | yo'q | ha |
| Yangi buyurtma ovozi | yo'q | ha |

Ilova ochiq turganda yangi buyurtmalar o'zi chiqadi, lekin Mini App ovoz chalmaydi. Buyurtmalarni doim kuzatib turadigan
joyda (kassada, oshxonada) hamkor panelini ochiq qoldiring: u yangi buyurtmada signal beradi.

## 3. Pastki menyu: kim nimani ko'radi

Ekran pastida tugmalar bor. Rolga qarab:

- **Ega**: **Analitika**, **Katalog**, **Buyurtmalar**, **Xodimlar**, **Profil**.
- **Menejer**: **Buyurtmalar**, **Stop-list**, **Profil**.
- **Kuryer**: **Yetkazmalar**, **Profil**.

Qolgan bo'limlar (**Stop-list va qozon**, **Filiallar va ish vaqti**, **Balans va to'lovlar**, **Tizim va ish vaqti sozlamalari**,
**Shikoyatlar va e'tirozlar**) **Profil** ekranida ro'yxat bo'lib turadi (ega uchun **Do'kon sozlamalari**, menejer uchun **Filial**).

## 4. Buyurtmalar (ega va menejer)

**Buyurtmalar** ekrani. Tepada sizning ismingiz va "Menejer boshqaruvi" yozuvi.

- Egada bir nechta filial bo'lsa, **Filial bo'yicha** ro'yxati chiqadi: **Barcha filiallar** yoki bitta filial. Menejer faqat o'z filialini ko'radi.
- Ikkita yorliq: **Faol (N)** va **Yakunlanganlar**.
- Faol buyurtmalar tepasida to'rtta hisoblagich: **Yangi**, **Tayyorlash**, **Tayyor**, **Kuryer**. Birini bossangiz, faqat shu buyurtmalar
  ko'rinadi. Yana bossangiz — hammasi qaytadi (**Hammasini ko'rsatish**).

### Buyurtma kartochkasi

Raqam (`#E101`), holat belgisi, vaqt, mijoz ismi, summa, turi (**Yetkazish**, **Olib ketish**, **Zalda**), to'lov usuli (to'langan bo'lsa
"To'langan"), manzil, mijoz izohi (sariq qutida) va **Tarkibi** (taomlar). Ochilmagan bo'lsa, **Tarkibi** ni bosing.

Rangli belgilar:

- **Kutmoqda: 12 daq** — kulrang: normal; sariq — 15 daqiqadan; qizil — 30 daqiqadan.
- **Oshxona: 2/5 tayyor** — oshxonada taomlar qancha tayyor. Hammasi tayyor bo'lsa yashil.
- Holat belgisi: sariq — **Yangi**; ko'k — jarayonda; yashil — **Yetkazildi**; qizil — **Bekor qilindi** yoki **Rad etildi**.

### Tugmalar

Tugmalar buyurtma holatiga qarab chiqadi:

| Holat | Tugma |
|---|---|
| Yangi | **Qabul qilish**, **Rad etish** |
| Qabul qilindi | **Tayyorlashni boshlash**, **Bekor qilish** |
| Tayyorlanmoqda | **Tayyor bo'ldi**, **Bekor qilish** |
| Tayyor (yetkazish) | kuryerni tanlash va **Kuryerni tayinlash**, **Bekor qilish** |
| Tayyor (olib ketish, zal) | **Mijozga topshirish**, **Bekor qilish** |
| Kuryer kutilmoqda | **Yo'lga chiqdi** |
| Yo'lda | **Yetkazilganini tasdiqlash** |

### Rad etish yoki bekor qilish

1. **Rad etish** (yangi buyurtma) yoki **Bekor qilish** ni bosing.
2. Ochilgan oynada sababni tanlang (masalan, **Mahsulot qolmagan**, **Do'kon yopiq**, **Mijoz so'rovi**, **Boshqa sabab**).
3. Xohlasangiz izoh yozing va tasdiqlang.

Sababsiz tasdiqlab bo'lmaydi. Yakunlangan buyurtmada sabab kartochkada ko'rinadi.

### Kuryer tayinlash

1. Tayyor yetkazish buyurtmasida **Kuryerni biriktirish** ro'yxatini oching.
2. Kuryerni tanlang (ism va Telegram nomi ko'rinadi). Tanlamaguncha **Kuryerni tayinlash** tugmasi o'chiq turadi.
3. **Kuryerni tayinlash** ni bosing.

Ro'yxat bo'sh bo'lsa, kuryer qo'shilmagan: **Xodimlar** bo'limidan qo'shing.

### Buyurtma tafsiloti

Kartochkadagi **Batafsil** ni bosing. Ochiladi: mijoz (ism, telefon, **Qo'ng'iroq qilish**), yetkazish manzili (**Xaritada ochish**),
zona, kuryer, **Taomlar**, **Hisob** (taomlar summasi, chegirma, yetkazish, xizmat haqi, joy narxi, **Jami**), to'lov, kanal, filial,
**Holatlar tarixi** va **Har bir bosqich vaqti**. Ba'zi rollar uchun mijoz ma'lumoti yashirilgan.

Yakunlangan buyurtmalar **Yakunlanganlar** yorlig'ida; ro'yxat oxirida yana yuklash tugmasi bor.

## 5. Stop-list va qozon (ega va menejer)

Menejer: pastki menyuda **Stop-list**. Ega: **Profil** → **Stop-list va qozon** (yoki **Katalog** ichidagi **Stop-list va qozon** tugmasi).

Egada bir nechta filial bo'lsa, tepada **Filial** ni tanlaysiz. Ikkita yorliq: **Stop-list** va **Qozon**.

### Taom tugadi

1. **Stop-list** yorlig'ida qidiruvdan foydalaning (**Taom yoki qo'shimcha qidirish**) yoki **Ko'rinish** filtri bilan **To'xtatilgan (N)** ni tanlang.
2. Taom yonidagi **Sotuvda** tumblerini o'chiring. Taom darhol sotuvdan chiqadi, qatorda **Tugadi** belgisi paydo bo'ladi.
3. Qaytarish uchun tumblerni yoqing.

Faqat qo'shimcha (masalan, sous) tugagan bo'lsa, **Qo'shimchalar** bo'limida o'sha qatorni o'chiring. Taom to'xtatilgan bo'lsa,
uning qo'shimchalari ustida "Taom to'xtatilgan" yoziladi.

### Qozon

1. **Qozon** yorlig'ida **Yangi qozon ochish** ni bosing.
2. **Taom**, kerak bo'lsa **Variant** (yoki **Hamma variantlar**), **Necha porsiya (miqdor)**, **Izoh (ixtiyoriy)** ni yozing.
3. **Qozonni ochish**.

Ochiq qozon kartochkasida **Qoldi: 12 / 40** ko'rinadi. Tugmalar: **To'ldirish** (yana pishirilganda **Qancha qo'shiladi** yoki **Hozirgi qoldiq**),
**Yopish** (qolgan miqdor hisobdan chiqadi). Tugasa, **Tugadi** belgisi chiqadi.

## 6. Xodimlar va PIN (ega)

Pastki menyuda **Xodimlar**. Yuqorida "Jami N ta xodim".

### Xodim qo'shish

1. **Yangi xodim qo'shish** shaklida **Lavozim** (**Menejer**, **Kassir**, **Ofitsiant**, **Oshpaz**, **Kuryer**), **Ism-familiya**
   va **Filialga biriktirish** ni to'ldiring.
2. **Kirish telefoni (ixtiyoriy)**: `+998XXXXXXXXX`. Yozsangiz, xodim shu raqamga kelgan SMS kod bilan hamkor paneliga kiradi.
3. **Xodim qo'shish** ni bosing.
4. Chiqqan **taklif havolasini** **Nusxa olish** yoki **Ulashish** bilan xodimga yuboring. U Telegramda bosganda ulanadi.
5. Yana qo'shish uchun **Yangi xodim qo'shish** (yoki **Barcha xodimlar**).

### Xodim bilan ishlash

Ro'yxatdagi xodim kartochkasida belgilar: **Taklif kutilmoqda**, **Telegram ulangan**, **PIN o'rnatilgan** / **PIN yo'q**, kirish raqami.
Pastga qaragan strelkani (**Boshqarish**) bossangiz, amallar ochiladi:

- **Kirish raqami**: **Raqam qo'shish** yoki **Raqamni o'zgartirish**. Xodim raqamni o'zi SMS bilan tasdiqlagan bo'lsa,
  "SMS bilan kirish yoqilgan" yozuvi chiqadi va raqamni o'zgartirib bo'lmaydi (xavfsizlik uchun).
- **Terminal PIN kodi**: **PIN o'rnatish** / **PIN almashtirish** — 4 dan 6 gacha raqam; oddiy kodlar (0000, 1234) qabul qilinmaydi.
  **Kod: 1234** (raqam har xodimda boshqa) — xodimning terminal kodi. **PIN ni o'chirish** — xodim yangi PIN qo'yilguncha terminalga kira olmaydi.
- **Filialni o'zgartirish** — ro'yxatdan yangi filial.
- **Xodimni o'chirish** — tasdiqlashdan keyin xodim ilovalarga kira olmaydi.

"Bu xodimni faqat ko'rishingiz mumkin." yozuvi bo'lsa, bu xodim bilan ishlash huquqingiz yo'q.

PIN nima uchun kerak: kassa yoki oshxona planshetiga kirish ([oshxona ekrani qo'llanmasi](oshxona-ekrani.md)).
Rolni o'zgartirish yoki planshetni ulash — kompyuterdagi [panelda](hamkor-panel.md).

## 7. Katalog (ega)

**Katalog** ekrani — menyudagi taomlar ro'yxati. Mini Appda mahsulotni qo'shish va tahrirlash soddalashtirilgan.

1. **Qo'shish** tugmasi. Yoki mavjud mahsulotni bosing.
2. **Rasm yuklash**. **Asosiy ma'lumotlar**: **Sotuvda** tumbleri, **Kategoriya**, **Nomi**, **Ta'rifi (ixtiyoriy)**.
3. **Narx va variantlar**: har bir variantga **Nomi** (masalan, Mini, Katta, 1 L) va **Narxi (so'm)**.
4. Saqlang.

Ro'yxatda har bir mahsulotda **Sotuvda** tumbleri bor: o'chirsangiz mahsulot mijozlarga ko'rinmaydi. Qalam — tahrirlash; chiqindi savat — o'chirish.
Kg bo'yicha sotiladigan taom (**Vazn bo'yicha** yorlig'i bor), modifikatorlar va kanal narxlari faqat panelda tahrirlanadi.

## 8. Filial ish vaqti (ega va menejer)

**Profil** → **Filial ish vaqti** (menejer) yoki **Filiallar va ish vaqti** (ega).

1. Filial kartochkasida **Tahrirlash** ni bosing.
2. **Ochilish vaqti** va **Yopilish vaqti** ni yozing. Kechasi ishlasa, yopilish vaqti ochilishdan oldin bo'ladi (**Tungi smena**).
3. Sutkasiga ochiq bo'lsa, **Sutka bo'yi ochiq** ni yoqing.
4. Kerak bo'lsa **Vaqtincha yopiq** ni yoqing: yopiq filialga yetkazish buyurtmasi tushmaydi.
5. **Saqlash**.

Kartochkada **Hozir ochiq** yoki **Hozir yopiq** ko'rinadi. Manzil, hudud va radiusni o'zgartirish uchun hamkor panelidan foydalaning.

## 9. Analitika, balans va sozlamalar (ega)

- **Analitika** (ega ochiladigan birinchi ekran): **Bugun**, **Hafta**, **Oy**, **Yil**. Umumiy savdo, to'lov turlari bo'yicha taqsimot,
  **Eng ko'p sotilganlar**, **Kuryerlar reytingi**.
- **Profil** → **Balans va to'lovlar**: hamyon va qarz hisobi, **Balansni to'ldirish**, pul yechish so'rovi (karta raqami bilan). Batafsil — panelning
  [Moliya bo'limida](hamkor-panel.md#12-moliya).
- **Profil** → **Tizim va ish vaqti sozlamalari**: **Do'kon sozlamalari** (logo, banner, **Do'kon ochiq**, **Ish vaqti**, **Yetkazib berish**,
  **Olib ketish**, **To'lov tizimlari**).
- **Profil** → **Shikoyatlar va e'tirozlar**: mijoz shikoyatlariga javob yozish.

## 10. Kuryer: yetkazish qadamlari

Pastki menyuda **Yetkazmalar**. Ekran tepasida ismingiz, **Kuryer kabineti**, **Bugungi daromad** va **Yetkazilganlar** soni.
Ikkita yorliq: **Faol (N)** va **Topshirilganlar (N)**.

Sizga menejer buyurtma biriktirsa, u **Faol** yorlig'ida chiqadi. Hozircha buyurtma bo'lmasa: "Sizga hali buyurtma biriktirilmagan".

Har bir yetkazish kartochkasida: raqam, holat, mijoz ismi va telefoni (yashil telefon tugmasi — **Qo'ng'iroq qilish**),
manzil (**Xaritada ochish** — xaritada yo'nalish ochiladi), **Mahsulotlar**, to'lov: **To'langan** yoki **Naqd olinadi** (naqd pulni mijozdan olasiz).

Qadamlar:

1. Buyurtmani restorandan oling. Keyin **Olib ketdim (Yo'lga chiqdim)** tugmasini bosing. Holat "Yo'lda" bo'ladi.
2. Manzilga boring. Kerak bo'lsa mijozga qo'ng'iroq qiling.
3. Buyurtmani topshiring, naqd bo'lsa pulni oling.
4. **Yetkazib berdim (Yakunlash)** tugmasini bosing. Buyurtma **Topshirilganlar** yorlig'iga o'tadi, **Yetkazilganlar** soni oshadi.

Bir nechta buyurtma bo'lsa, ilova joriy buyurtmani **Hozirgi** belgisi bilan ajratadi. Asosiy tugma ham Telegramning pastki katta tugmasi
sifatida chiqadi — shuni bosish ham mumkin. Yo'ldagi buyurtma birinchi o'rinda turadi.

Xato chiqsa ("Holatni yangilashda xatolik yuz berdi!" yoki "Buyurtmani topshirishda xatolik yuz berdi!"), internetni tekshirib qayta bosing.
