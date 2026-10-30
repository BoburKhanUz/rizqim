# Hostes: kutish ro'yxati, stol holatlari, bronlar

Hostes — zalning kirish joyida mehmonlarni kutib oladigan xodim. U **navbat** yuritadi (kimdir stol kutayotgan bo'lsa), bo'shagan
stolni **tozalanmoqda** deb belgilaydi va **bronlarni** yozadi. Hostesning o'z ilovasi yo'q: u **ofitsiant ilovasida** ishlaydi
(telefonda yoki planshetda), faqat ish rejimi boshqacha.

**Hostes pul olmaydi va buyurtma ochmaydi (standart bo'yicha).** To'lov, chegirma, schyot — kassirda. Hostes faqat mehmonlarni
joylashtiradi va ro'yxatni yuritadi.

Boshqa qo'llanmalar: ofitsiant ilovasi — [ofitsiant.md](ofitsiant.md), restoran oqimi — [restoran.md](restoran.md), kassadagi
bronlar va avans — [kassa.md](kassa.md#15-bron-avans-va-keldi), ega sozlamalari — [oxirgi bo'lim](#ega-uchun-sozlash).

## Mundarija

1. [Kirish](#1-kirish)
2. [Ekran](#2-ekran)
3. [Kutish ro'yxati](#3-kutish-royxati)
4. [Stol holatlari: tozalanmoqda va tayyor](#4-stol-holatlari-tozalanmoqda-va-tayyor)
5. [Bronlar va bo'nak](#5-bronlar-va-bonak)
6. [Muammolar va yechimlar](#6-muammolar-va-yechimlar)
7. [Ega uchun: sozlash](#ega-uchun-sozlash)

## 1. Kirish

1. Telefon yoki planshetda ofitsiant ilovasini oching (ulash va PIN — [ofitsiant.md](ofitsiant.md#1-telefonni-ulash-va-kirish)).
2. Ismingizni bosib **hostes PIN'ingizni** tering. Ro'yxatda ismingiz bo'lmasa, ega **Xodimlar** da sizga **Hostes** rolini va PIN
   berishi kerak.
3. Ekranning pastida **Xonalar** va **Hostes** yorliqlari chiqadi (faqat hostes huquqi borlarda va filialda **Hostes** moduli
   yoqilgan bo'lsa). **Hostes** ni bosing.

Hostesning standart huquqlari: buyurtmalarni ko'rish, stollarni boshqarish, menyuni ko'rish, bronlar, kutish ro'yxati. To'lov,
chegirma va buyurtma ochish yo'q. Ega kerak bo'lsa **Huquqlar** sahifasida o'zgartiradi.

<!-- TEKSHIRILMAGAN: pastki "Xonalar | Hostes" yorliqlari (waiter home_shell.dart, K1 ekrani hali tugamagan); hostes huquqlari ro'yxati backend POS_SPEC 19.3 dan -->

## 2. Ekran

**Hostes** ekrani uch qismdan iborat:

| Qism | Nima |
|---|---|
| **Kutish** (kutayotganlar soni bilan: **Kutish (3)**) | Navbatdagi mehmonlar, yozilgan tartibda. Pastda **Bugun**: allaqachon joylashgan, ketgan, kelmaganlar |
| **Stollar** | Zal xaritasi: stollar va holatlari. Tozalanayotgan stollar soni tepada |
| **Bronlar** | Bugungi bronlar, **Bron qo'shish** tugmasi |

Telefonda bu uch qism yorliq bo'lib almashadi. Keng ekranda (planshet) qismlar yonma-yon turadi.

Ro'yxatlar **o'zi yangilanadi**: boshqa hostes yoki menejer mehmon yozsa, stolni belgilasa, siz ekranni yangilamasdan ko'rasiz.
Aloqa uzilsa, tepada **Aloqa yo'q** lentasi chiqadi; o'zgartirish aloqa qaytgach mumkin.

<!-- TEKSHIRILMAGAN: planshetda yonma-yon joylashuv (hostess_page.dart wideFrom, _wide) va jonli yangilanish (waitlist.changed / table.state_changed) — K1 ekrani qo'lda ko'rilmagan; "Aloqa yo'q" lentasi ConnectionBanner orqali -->

## 3. Kutish ro'yxati

Hamma stol band, mehmonlar kutadi. Ularni ro'yxatga yozing, stol bo'shaganda chaqirasiz.

### Mehmonni yozish

1. **Kutish** qismida **Mehmon yozish** ni bosing.
2. To'ldiring:
   - **Ismi** — majburiy.
   - **Telefon (ixtiyoriy)** — `+998 90 123 45 67` ko'rinishida. Mehmon ketib qolsa, qo'ng'iroq qilasiz.
   - **Necha kishi** — mehmonlar soni (**+** va **−** bilan, 200 gacha).
   - **Aytilgan kutish** — mehmonga qancha kutishini aytdingiz: tez tugmalar (**10 daq**, **15 daq** ...).
   - **Izoh (ixtiyoriy)** — masalan, "deraza yonida, bolalar stuli".
3. **Ro'yxatga yozish**.

Mehmon ro'yxat oxiriga qo'shiladi. Ro'yxat **yozilgan vaqt** bo'yicha: kim avval kelgan, shu birinchi (qatorda navbat raqami turadi).
Har qatorda ismi, necha kishi, telefon, izoh va kutish ("12 / 15 daq": 12 daqiqa kutdi, 15 daqiqa deb aytilgan) ko'rinadi.
Kutish aytilganidan uzayib ketgan mehmonning kartochkasi **qizil chegara** bilan ajralib turadi: unga e'tibor bering.
Chaqirilgan mehmonda "Chaqirildi 19:40" yozuvi chiqadi.

### Chaqirish va joylashtirish

Stol bo'shaganda:

1. Mehmon qatoridagi **Chaqirish** ni bosing. **Bu faqat belgi qo'yadi**: mehmonga SMS yoki xabar **ketmaydi**. Mehmonni o'zingiz
   chaqirasiz yoki telefon qilasiz. Qayta chaqirish kerak bo'lsa tugma **Yana chaqirish** bo'ladi.
2. Mehmon kelganda **Joylashtirish** ni bosing.
3. Ochilgan oynada **bo'sh stollar** zallar bo'yicha: har stolda o'rinlar soni ("6 o'rin"). Mehmonlar sig'adigan va tayyor
   stollar yuqorida; sig'masa o'rinlar soni qizil, tozalanayotgan stol **Tozalanmoqda** yozuvi bilan pastroqda, bronli stolda
   **Bron: 19:30** turadi. Stolni tanlang.
4. **Buyurtmani ham ochish** — yoqsangiz, stolda buyurtma ochiladi va mehmonlar soni yoziladi. Bu tugma faqat buyurtma ochish
   huquqi bor xodimda ishlaydi. Hostesning standart huquqlarida u **yo'q**: mehmonni joylashtirasiz, buyurtmani ofitsiant yoki kassir
   ochadi.
5. Tasdiqlang. Mehmon ro'yxatdan chiqib **Bugun** qismida "Joylashdi: Stol 7" bo'lib qoladi.

Bo'sh stol yo'q bo'lsa, ilova "Bo'sh stol yo'q" deydi va stol bo'shaganda ro'yxat o'zi yangilanadi.

### Mehmon kutmasdan ketsa

Qator yonidagi menyudan:

- **Ketdi (bekor qilish)** — mehmon kutib o'tirmasdan ketdi.
- **Kelmadi** — chaqirdingiz, lekin mehmon kelmadi.

Ikkalasi ham yozuvni **Bugun** qismiga o'tkazadi; o'chirilmaydi. Egaga hisobotda kim qancha kutgani va nima bilan tugagani
ko'rinadi ([ega uchun](#ega-uchun-sozlash)).

Joylashgan, ketgan yoki kelmagan yozuv yopiq: uni qayta ochib bo'lmaydi. Xato yozgan bo'lsangiz, mehmonni qaytadan yozing.

<!-- TEKSHIRILMAGAN: tugmalar nomlari K1 kodidan o'qildi (hostess_page.dart / hostess_sheets.dart: "Mehmon yozish", "Ro'yxatga yozish", "Chaqirish", "Yana chaqirish", "Joylashtirish", "Buyurtmani ham ochish", "Ketdi (bekor qilish)", "Kelmadi"), ekran qo'lda ko'rilmagan; "yozuvni o'zgartirish" (ismi, necha kishi) ekranda yo'q bo'lishi mumkin, faqat API da bor (PATCH /pos/waitlist); aytilgan kutish tugmalari qiymatlari (10, 15 ...) aniq emas -->

**Mehmonning telefon raqami** hostesga ko'rinadi (qo'ng'iroq qilish uchun). Egaga va harakatlar jurnalida u yashirin: `+99890*****67`.

## 4. Stol holatlari: tozalanmoqda va tayyor

Mehmonlar turib ketgach stol tozalanadi. Shu paytda uni **tozalanmoqda** deb belgilang: ofitsiant va hostes stol hali tayyor emasligini ko'radi.

1. **Stollar** qismida stolni toping (zallar bo'yicha guruhlangan).
2. Stolda **Tozalash** ni bosing: stol "tozalanmoqda" bo'ladi.
3. Tozalab bo'lgach **Tayyor** ni bosing: belgi olib tashlanadi.

Eslab qoling:

- Belgi stolda **ochiq buyurtma yo'q** va undan keyin yangi buyurtma ochilmagan bo'lsagina ko'rinadi. Yangi buyurtma ochilsa, belgi
  o'zi o'chadi.
- Belgi **hech narsani to'smaydi**: tozalanayotgan stolga mehmon joylashtirish va buyurtma ochish mumkin. U faqat eslatma.
- Stolning asosiy holati (bo'sh, band) o'zgarmaydi: belgi alohida.
- Belgini hostes va **stollarni boshqarish** huquqi bor xodim (masalan, menejer) qo'yadi va oladi.

## 5. Bronlar va bo'nak

**Bronlar** qismida bugungi bronlar: kim, qaysi stol, necha kishi, qaysi vaqtda, holati. **Bron qo'shish** (bronlar huquqi bor
xodimda) — telefon orqali stol band qilish.

### Bron yozish

1. **Bron qo'shish** → **Mehmon ismi**, **Telefon (ixtiyoriy)**.
2. **Necha kishi** — mehmonlar soni (**+** va **−**).
3. **Stol** ni ro'yxatdan tanlang (o'rinlar soni yozilgan).
4. **Qachon** — **Hozir** yoki bugun keyinroq: vaqt tugmalari (masalan, "19:30 (60 daq)").
5. **Bron qilish**.

Boshqa kunga bron kassada yoki menejerda yoziladi ([kassa.md](kassa.md#yangi-bron)): hostes ekrani bugungi bronlar uchun.

Bron stolni filialning qoidasiga ko'ra **ma'lum muddatga** ushlaydi: tugash vaqtini yozmasangiz, stol uchun odatda **2 soat**
(ega o'zgartirishi mumkin: 1,5–2 soat). Kassa bilan bir xil bron daftari: kassirda ham, ofitsiantda ham shu bron ko'rinadi
([kassa.md](kassa.md#15-bron-avans-va-keldi)).

### "Oldindan bron qilish kerak" qoidasi

Ega bron boshlanishigacha **eng kamida** necha daqiqa qolishini belgilashi mumkin (masalan, choyxonada xona uchun 3 soat). Kerakli vaqtdan
yaqinroqqa yozsangiz, ilova bron mumkin emasligini va **eng erta mumkin vaqtni** aytadi. Shuningdek, ega "eng ko'pi bilan necha kun oldin"
cheklovini qo'yishi mumkin.

**"Yo'lda kelayotgan mehmon"** — mehmon hozir yo'lda va stol kerak: bu qoidadan **faqat ega yoki menejer** o'tkazishi mumkin. Bron
oynasida **Yo'lda kelayotgan mehmon** belgisi shu uchun. Hostesda bu huquq bo'lmasa, tugma ko'rinmaydi yoki ilova menejer kerakligini aytadi.

### Bo'nak (depozit)

Ega stol broniga **bo'nak** belgilashi mumkin: bron uchun bir xil summa yoki har mehmon uchun summa, ma'lum mehmonlar sonidan boshlab.
Agar ega "**Bo'nak olinmaguncha tasdiqlanmasin**" ni yoqqan bo'lsa:

- Bron **so'rov** holatida qoladi.
- Bo'nak kassada olinadi (avans bilan bir xil, bo'nak cheki chiqadi: [kassa.md](kassa.md#avans)). Hostes pul olmaydi.
- Bo'nak yetgach bron **o'zi tasdiqlanadi**. Qismlab olinsa, oxirgi qism yetgan paytda.
- Bo'nak yetmasa, bronni qo'lda tasdiqlab bo'lmaydi: ilova "bo'nak yetmaydi" va qancha qolganini aytadi.

Bron ekranida kerakli bo'nak va yetmagan qismi ko'rinadi. Mehmonga: "Bronni tasdiqlash uchun N so'm bo'nak kerak, uni kassada
to'laysiz" deb ayting.

<!-- TEKSHIRILMAGAN: bron oynasi maydonlari K1 ekranidan (hostess_sheets.dart "Bron qo'shish", "Mehmon ismi", "Telefon (ixtiyoriy)", "Stol", "Qachon", "Yo'lda kelayotgan mehmon", "Bron qilish"); "mehmonlar sonini yozing" maydoni, bo'nak yetmasligi/"deposit_required" xabarining ekranda ko'rinishi, bronlar ro'yxatida bo'nak belgisi — ekranda tekshirilmagan (API da deposit_required/deposit_missing bor) -->

## 6. Muammolar va yechimlar

| Muammo | Nima qilish kerak |
|---|---|
| **Hostes** yorlig'i yo'q | Filialda **Hostes** moduli o'chiq, yoki sizda hostes huquqi yo'q. Egaga ayting |
| Ismim PIN ekranida yo'q | Sizga PIN qo'yilmagan yoki rol boshqa. Ega yoki menejer **Xodimlar** da tuzatadi |
| **Mehmon yozish** ishlamayapti, "telefon noto'g'ri" | Telefon `+998` bilan, 9 raqam (`+998 90 123 45 67`). Bo'sh qoldirsangiz ham bo'ladi |
| **Joylashtirish** da "Bo'sh stol yo'q" | Hamma stol band. Stol bo'shaganda ro'yxat yangilanadi; yoki stolni **Stollar** da tekshiring |
| Buyurtmani ochib bo'lmayapti | Hostesda buyurtma ochish huquqi yo'q. Mehmonni joylashtiring, ofitsiant buyurtma ochadi |
| Bron: "juda yaqin vaqt" va eng erta vaqt ko'rsatildi | Ega bron uchun eng kam oldindan vaqt qo'ygan. Keyingi vaqtni tanlang yoki menejerdan **Yo'lda kelayotgan mehmon** sifatida yozishni so'rang |
| Bron so'rovda qolib ketdi, tasdiqlanmayapti | Bo'nak yetmaydi. Mehmon kassada bo'nakni to'lashi kerak; keyin bron o'zi tasdiqlanadi |
| Stolda "tozalanmoqda" turib qoldi | **Tayyor** ni bosing. Stolga yangi buyurtma ochilsa, belgi o'zi o'chadi |
| Ro'yxat eskirib qoldi | Pastga tortib yangilang yoki aloqani tekshiring. Boshqa hostes ham yozayotgan bo'lsa, o'zgarish o'zi keladi |

## Ega uchun: sozlash

1. **Kassa sozlamalari → Format** da **Hostes** va **Stol broni** modullarini yoqing (**Restoran** profilida ikkalasi o'zi yoqiladi;
   **Asosiy** tarifda, bron funksiyasi bilan). Choyxonada ular o'chiq: choyxona oqimi o'zgarmaydi.
2. **Xodimlar** da hostesni qo'shing: rol **Hostes**, PIN o'rnating ([ega-pos-sozlash.md](ega-pos-sozlash.md#10-xodimlar-va-pin)).
   Ruxsatlarni **Huquqlar** sahifasida ko'rasiz va o'zgartirasiz. Hostes buyurtma ochsin desangiz, unga buyurtmalarni boshqarish
   huquqini bering.
3. **Kassa sozlamalari → Stol broni** — stol broni qoidalari (faqat yangi bronlarga ta'sir qiladi; ochiq bronlar, to'lov va yopish
   o'zgarmaydi):
   - **Bron muddati**: stol uchun daqiqa (30–480, standart 120); xona va tapchan — bo'sh qoldirsangiz filialning bron muddati (5 soat).
   - **Qancha oldin bron qilinadi**: stol, xona va tapchan uchun eng kam oldindan vaqt (daqiqa, 0 — cheklov yo'q) va "eng ko'pi bilan necha
     kun oldin" (1–365).
   - **Bo'nak (ixtiyoriy)**: turi (olinmaydi / bron uchun bir xil summa / har mehmon uchun), summa, necha kishidan boshlab, va
     **Bo'nak olinmaguncha tasdiqlanmasin**. Bo'nak faqat **stol** broniga; xona va tapchan bo'nagi avvalgidek qo'lda olinadi.
4. **Kassa sozlamalari → Kutish ro'yxati** — faqat **ko'rish**: kunlar bo'yicha hostes yozgan mehmonlar, kim qancha kutdi (haqiqiy
   va aytilgan kutish), nima bilan tugadi (joylashdi, ketdi, kelmadi). Telefon raqamlari yashirilgan. Shu yerda hostesning ishini
   baholaysiz: aytilgan kutish haqiqiydan ancha qisqa bo'lsa, mehmonlar ketib qoladi.

Choyxona qoidasi o'zgarmaydi: xona broni 3 soatdan oldin qilinadi, 5 soat ushlanadi; stol qoidasi alohida.
