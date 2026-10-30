# Restoran va kafe: kurslar, mehmon bo'yicha schyot, choy puli

Bu qo'llanma restoran, kafe, kabobxona va baliqxonada ishlaydigan **ofitsiant** va **kassir** uchun. Ega uchun — oxirida alohida
bo'lim bor. Choyxona va osh markazi oqimi o'zgarmagan: bu yerdagi hamma narsa faqat ega **Kassa sozlamalari → Format** da
tegishli modulni yoqqan filialda ko'rinadi. Modul o'chiq bo'lsa, tugma yo'q va ish avvalgidek ketadi.

Boshqa qo'llanmalar: ofitsiant ilovasi — [ofitsiant.md](ofitsiant.md), kassa — [kassa.md](kassa.md), oshxona ekrani —
[oshxona-ekrani.md](oshxona-ekrani.md), hostes — [hostes.md](hostes.md), choy puli va ish haqi (ega) —
[choy-puli-va-ish-haqi.md](choy-puli-va-ish-haqi.md).

## Mundarija

1. [Qaysi tugma qaysi modulga bog'liq](#1-qaysi-tugma-qaysi-modulga-bogliq)
2. [Kurslar](#2-kurslar)
3. [Mehmon raqami va hisobni mehmon bo'yicha bo'lish](#3-mehmon-raqami-va-hisobni-mehmon-boyicha-bolish)
4. [Stolni boshqa ofitsiantga berish](#4-stolni-boshqa-ofitsiantga-berish)
5. [Choy puli kassada](#5-choy-puli-kassada)
6. [Schyotdagi xizmat haqi e'loni](#6-schyotdagi-xizmat-haqi-eloni)
7. [Six, og'irlik va mangal vaqti (kabobxona, baliqxona)](#7-six-ogirlik-va-mangal-vaqti-kabobxona-baliqxona)
8. [Kafe: stolga olib kelish](#8-kafe-stolga-olib-kelish)
9. [Internetsiz](#9-internetsiz)
10. [Muammolar va yechimlar](#10-muammolar-va-yechimlar)
11. [Ega uchun: nimani yoqish kerak](#11-ega-uchun-nimani-yoqish-kerak)

## 1. Qaysi tugma qaysi modulga bog'liq

| Nima | Modul (Format sahifasida) |
|---|---|
| Qatorga kurs tanlash, "N-kursni yuborish", "N-kurs tayyor" xabari | **Kurslar** |
| Qatorga mehmon raqami, schyotni "Mehmonlar bo'yicha" bo'lish | **Mehmon bo'yicha bo'lish** |
| "Boshqa ofitsiantga berish" | **Stolni boshqa ofitsiantga o'tkazish** |
| To'lovda choy puli, "Mening choy pulim" | **Choy puli** |
| Schyotdagi xizmat haqi e'loni, kuver, katta guruh foizi | **Xizmat haqi qoidalari** |
| ×2 … ×10 tugmalari | **Six tugmalari** |
| "Og'irligi keyin tortiladi" | **Og'irlikni keyin kiritish** |
| Kassada "Stol raqami" | **Stolga olib kelish** (kafe) |
| "Ishga keldim" / "Ishdan ketdim" | **Ofitsiant ish haqi** (ish vaqtini belgilash yoqilgan bo'lsa) |

Kurslar, mehmon bo'yicha bo'lish, choy puli, xizmat haqi qoidalari va ish haqi **Asosiy** tarifda. Tarifda bo'lmasa
tugma ko'rinmaydi yoki ilova "tarifda yo'q" deb yozadi.

**To'lov, schyot, chek, smena va qaytarish hech qachon to'xtamaydi** — modul o'chirilgan bo'lsa ham. Modul faqat yangi
tugmalarni yashiradi.

## 2. Kurslar

Kurs — taomlarning ketma-ketligi: 1-kurs salat va sho'rva, 2-kurs asosiy taom, 3-kurs shirinlik. Oshxona 2-kursni siz aytmaguncha
pishirmaydi: mehmonlar salatni yeb bo'lishini kutasiz va keyin yuborasiz. Kurslar ixtiyoriy: kursi **yo'q** qator
(**Kurssiz**) avvalgidek darhol oshxonaga ketadi.

### Ofitsiant: qatorga kurs berish

1. **Taom qo'shish** da taomni tanlang (kerak bo'lsa soni, qo'shimchasi bilan).
2. Qator sozlamasida **Kurs** qatori: **Kurssiz**, **1-kurs**, **2-kurs**, **3-kurs** va **Yana** (4–9-kurs). Boshida **Kurssiz** turadi.
3. Hamma qatorni shunday belgilab, **Yuborish** ni bosing.

Nima bo'ladi:

- **Kurssiz** qatorlar va oshxonaga allaqachon chiqqan kurslar (kamida 1-kurs) darhol ketadi.
- Oshxonaga chiqqan oxirgi kursdan **keyingi** kurs buyurtmada qoladi: **Yuborish** uni oshxonaga **yubormaydi**. Masalan, 1-, 2- va
  3-kurs qatorlari bor buyurtmada birinchi **Yuborish** faqat 1-kursni (va kurssiz qatorlarni) yuboradi, 2- va 3-kurs qoladi.

<!-- TEKSHIRILMAGAN: kurs tanlash qatori ofitsiant ilovasida (slot_pickers.dart: "Kurs: Kurssiz · 1-kurs · 2-kurs · 3-kurs · Yana") — K1 ekrani hali tugamagan, aniq joylashuvi (qo'shish oynasidami yoki qator ustida uzoq bosishdami) tekshirilmagan -->

### Kursni yuborish

Buyurtma ekranida **Kurslar** bloki bor: har kurs va uning holati.

| Holat | Ma'nosi |
|---|---|
| **Kutmoqda** | Hali oshxonaga yuborilmagan |
| **Yuborildi · tayyorlanmoqda** (yoki `2/3 tayyor`) | Oshxonada pishyapti |
| **Tayyor** | Kursning hamma taomi tayyor: olib borish kerak |
| **Berildi** | Mehmonga berilgan |

Mehmonlar oldingi kursni yeb bo'lgach, keyingi kurs tagidagi **N-kursni yuborish** ni bosing. Taomlar sexlarga bo'linib
oshxonaga ketadi va oshxona ekranida paydo bo'ladi. Kassir ham shu tugmani o'zining buyurtma ekranidan bosa oladi.

- Kurs yuborilgandan keyin unga qo'shilgan qator keyingi oddiy **Yuborish** bilan ketadi.
- Yuboriladigan narsa qolmagan bo'lsa, ilova "N-kursda oshxonaga yuboriladigan taom yo'q" deydi.

### "2-kurs tayyor" xabari

Oshpaz kursning oshxonaga chiqqan **oxirgi** taomini "tayyor" deganda, o'sha buyurtmaning ofitsiantining telefoniga xabar keladi:
**"2-kurs tayyor — 5-stol"** (ovoz va tebranish bilan, telefon sozlamasiga qarab). Bosib buyurtmani ochasiz. Boshqa ofitsiantga
xabar kelmaydi. Ofitsianti yo'q buyurtma (masalan, kassir ochgan) uchun xabar filialdagi hamma ofitsiantga boradi. Taomni olib
borgach — odatdagidek **Berildi** ([ofitsiant.md](ofitsiant.md#6-berildi)).

Oshxonada kurs belgisi ("1-kurs", "2-kurs") va har kursning **N-kurs tayyor** tugmasi qanday ko'rinishi —
[oshxona-ekrani.md](oshxona-ekrani.md#14-restoran-va-kafe-kurslar-ogirlik-mangal-vaqti-stol-raqami).

<!-- TEKSHIRILMAGAN: kassir ekranidagi "N-kursni yuborish" tugmasi joyi (cashier/lib/src/features/order/restaurant_widgets.dart bor, K2 ekrani tugallanmagan) -->

## 3. Mehmon raqami va hisobni mehmon bo'yicha bo'lish

Stolda har kim o'zi uchun to'lamoqchi bo'lsa, qatorga **mehmon raqamini** qo'yasiz, kassir esa schyotni mehmonlar bo'yicha bo'ladi.

### Ofitsiant: mehmon raqami

1. Taom qo'shayotganda qator sozlamasida **Mehmon** qatori: **Umumiy**, **1**, **2**, … (stoldagi mehmonlar soniga qadar).
2. Mehmonning o'z taomiga uning raqamini bosing. **Umumiy** — hech kimniki emas: non, choy, salat, umumiy taom. Boshida **Umumiy** turadi.
3. Keyin ham o'zgartirish mumkin: buyurtma ekranida qatorni bosib mehmon raqamini tanlang.

Mehmonlar soni buyurtmadagi **Mehmonlar soni** dan olinadi: avval uni to'g'ri qo'ying ([ofitsiant.md](ofitsiant.md#8-mehmonlar-soni-va-kochirish)).

<!-- TEKSHIRILMAGAN: mehmon qatori (slot_pickers.dart "Mehmon: Umumiy · 1 · 2") va "qatorni bosib o'zgartirish" (order_page.dart _seat) K1 ekranida, ishlashi qo'lda ko'rilmagan -->

### Kassir: "Mehmonlar bo'yicha"

1. Mehmon hisob so'raganda **Schyot** ni bosing, **Schyot tuzish** oynasida **Mehmonlar bo'yicha** ni tanlang.
2. Har mehmonga **bitta schyot** tuziladi, mehmon raqami kichigidan boshlab. Ekranda har mehmonning summasi ko'rinadi.
3. Har schyot **alohida to'lanadi va alohida sotuv cheki oladi**.

Hisob qoidalari (hamma summani server hisoblaydi):

- Mehmonning o'z qatorlari uning schyotiga to'liq tushadi.
- **Umumiy** qatorlar (mehmon raqamisiz) schyoti bor hamma mehmonga **teng bo'linadi**. Qoldiq birinchi mehmonga qo'shiladi:
  uchta mehmonga bitta non — 0,334 / 0,333 / 0,333 dona.
- Xizmat haqi, xona puli va chegirma har schyotning summasiga mutanosib bo'linadi. Schyotlar yig'indisi buyurtmaga **tiyingacha teng**.
- Bitta mehmon to'lab bo'lgan bo'lsa, uning schyoti o'z holicha qoladi, qolganlari qayta tuziladi: to'langan mehmonning umumiy
  qatordagi ulushi ham to'langan hisoblanadi.
- Hech bir qatorda mehmon raqami yo'q bo'lsa, ilova "mehmon raqamli qator yo'q" deydi. Avval qatorlarga raqam qo'ying.
- Schyotni bo'lish kassirda (to'lov olish huquqi bilan). Ofitsiant bo'lmaydi.
- **Mehmonlar bo'yicha** faqat internet bor paytda ishlaydi; internetsiz ilova "internet kerak" deydi, **Teng bo'lish** va
  **Taom bo'yicha** esa avvalgidek ([kassa.md](kassa.md#schyot-tuzish)).

<!-- TEKSHIRILMAGAN: split_builder.dart da "Mehmonlar bo'yicha" tanlovi bor (K2), oldindan ko'rish (har mehmon summasi) va internetsiz xabar matni tekshirilmagan; kassirda qatorning mehmon raqamini o'zgartirish ham K2 ekranida kutilmoqda -->

## 4. Stolni boshqa ofitsiantga berish

Ofitsiantning smenasi tugasa yoki stolni hamkasbi olsa, **ochiq stol** boshqa ofitsiantga o'tkaziladi. Bu **ko'chirish** emas: ko'chirishda
mehmonlar boshqa joyga o'tadi, bu yerda stol o'z joyida, ofitsiant o'zgaradi.

1. Buyurtma ekranida tepadagi menyu (⋮) → **Boshqa ofitsiantga berish**.
2. Ro'yxatdan ofitsiantni tanlang (faqat filialning faol ofitsiantlari), xohlasangiz **Sabab** yozing → **Berish**.
3. Ruxsat bo'lmasa (odatda ofitsiantda yo'q: bu **menejer va ega** huquqi), ilova menejer PIN'ini so'raydi. Menejer ismini tanlab
   o'z PIN'ini teradi; tasdiq bir marta va qisqa vaqt amal qiladi ([kassa.md](kassa.md#menejer-tasdigi)).

Nima bo'ladi:

- Eski ofitsiantga "Stol 5 boshqa ofitsiantga o'tkazildi", yangisiga "Stol 5 sizga berildi" xabari boradi.
- Bundan keyingi to'lovlar va choy puli **yangi** ofitsiantga yoziladi. O'tkazishdan **oldin** to'langan schyotlar nimaga yozilishi
  eganing sozlamasiga bog'liq ([choy-puli-va-ish-haqi.md](choy-puli-va-ish-haqi.md#stol-boshqa-ofitsiantga-otsa-sotuv-kimga)).
- Kim kimga o'tkazgani, sabab va tasdiqlagan menejer harakatlar jurnaliga yoziladi.
- Boshqa filialning ofitsiantini yoki o'sha ofitsiantning o'zini tanlab bo'lmaydi.

<!-- TEKSHIRILMAGAN: menyudagi "Boshqa ofitsiantga berish" (order_page.dart) va "Berish" oynasi K1 ekranida; kassada shu amal bormi — K2 ro'yxatida yo'q -->

## 5. Choy puli kassada

Choy puli — mehmonning ofitsiantga o'z xohishi bilan beradigan puli. U **tushum emas**: fiskal chekka kirmaydi, sotuv
hisobotiga qo'shilmaydi, qaytarishga ham tegmaydi. Choy pulini kassir to'lov paytida yozadi.

### Qanday olinadi

1. To'lov oynasida to'lov turini (naqd, karta, QR) va summani kiriting.
2. **Choy puli** maydoniga mehmon berayotgan summani yozing. U schyotdan **tashqari, ustiga** qo'shiladi.
3. Naqd to'lovda mehmon qaytimini qoldirsa: **Qaytimni choy puli qilish** ni belgilang.
   - Choy puli summasini yozmasangiz — **butun qaytim** choy pulida qoladi.
   - Summani yozsangiz — faqat shu summa qaytimdan olinadi, qolganini mehmonga qaytarasiz.
4. Shaxsiy rejimda (eganing sozlamasi) kimga ekani ko'rsatiladi: boshida buyurtmaning ofitsianti, kerak bo'lsa boshqa xodimni
   tanlang. Xodim tanlanmasa, choy puli hech kimga biriktirilmaydi (menejer keyin beradi). **Umumiy qozon** rejimida choy puli
   umumiy qozonga tushadi va kun yoki smena oxirida eganing qoidasi bo'yicha bo'linadi.
5. Pastda ikki alohida jami ko'rinadi: **Chek: …, choy puli: …**. Mehmon bergan jami = chek jami + choy puli.
6. **To'lash**.

### Misol: qaytim choy pulida

Schyot 200 000 so'm. Mehmon 250 000 so'm naqd beradi.

| Siz nima qilasiz | Choy puli | Mehmonga qaytim | Fiskal chek |
|---|---|---|---|
| Hech narsa belgilamaysiz | 0 | 50 000 | 200 000 |
| **Qaytimni choy puli qilish**, summa bo'sh | 50 000 | 0 | 200 000 |
| **Qaytimni choy puli qilish**, summa 20 000 | 20 000 | 30 000 | 200 000 |
| Choy puli 30 000 (qaytim belgisisiz), mehmon 250 000 beradi | 30 000 | 20 000 | 200 000 |

Berilgan pul to'lov va choy pulini qoplamasa, ilova buni aytadi ("Berilgan pul to'lov va choy pulini qoplamaydi").

### Karta va QR

Karta choy puli bank terminalida schyot bilan **birga** o'tadi: terminal to'lov + choy pulini bitta summa qilib olishi kerak.
Terminal summasi Rizqim'dagi to'lovdan choy puli qadar ko'p bo'ladi — bu farq emas. Z dan keyingi solishtirishda choy puli
hisobga olinadi ([tez-kassa.md](tez-kassa.md#12-z-va-bank-terminalining-kunlik-jami)).

### Smena oxirida

- X va Z hisobotda **Choy puli** alohida bo'lim: soni, jami, usul bo'yicha, shaxsiy va qozon, xodim bo'yicha. Savdo jamiga kirmaydi.
- **Naqd choy puli kassada turadi**: kassadagi kutilgan naqdga qo'shiladi. Uni ofitsiantlarga smena oxirida berasiz: **Choy pulini
  berish** (kassadan chiqim, kategoriya "choy puli"). Buning uchun menejer huquqi yoki menejer PIN'i kerak.
- Choy pulini noto'g'ri yozgan bo'lsangiz (boshqa xodimga, boshqa summa) — menejer sabab bilan **tuzatadi**; asl yozuv qoladi.
- Qaytarish (pulni qaytarish) choy pulini **hech qachon** o'zgartirmaydi. Choy pulini qaytarish kerak bo'lsa, menejer tuzatadi.

<!-- TEKSHIRILMAGAN: X/Z dagi choy puli bo'limi, "Choy pulini berish" tugmasi va X/Z chop etishdagi alohida bo'lim K2 ekranida kutilmoqda (shift_report_mapping.dart, smena ekrani); to'lov oynasidagi "Choy puli", "Qaytimni choy puli qilish", "Chek: …, choy puli: …" yozuvlari payment_page.dart da bor -->

### Ofitsiant: "Mening choy pulim"

Ofitsiant ilovasida tepadagi menyu (⋮) → **Mening choy pulim**: bugungi (kassa smenasi bo'lsa — shu smenaning) choy pullaringiz.
Faqat o'zingiznikini ko'rasiz. Filialda choy puli umumiy qozonga tushsa, sizning ulushingiz kun oxirida menejer qozonni bo'lgandan
keyin chiqadi. Choy pulini kassir to'lovda yozadi: bu sahifa faqat ko'rsatadi.

## 6. Schyotdagi xizmat haqi e'loni

Xizmat haqi qoidalari yoqilgan filialda (restoran, kafe) schyotda xizmat haqi qatori tagida **e'lon matni** chiqadi. Mehmon
to'lashdan oldin nima va qancha olinishini ko'radi. Masalan:

> Xizmat haqi: zalda 15%, stolga olib kelishda 12,5%. 8 va undan ko'p mehmonli stolga xizmat haqi 18%. Kuver: 5 000 so'm har bir
> mehmon uchun.

Matnni ega o'zi yozishi mumkin; yozmasa qoidalardan avtomatik tuziladi. Qoidalarning o'zi:

- **Buyurtma turi bo'yicha foiz**: zal (ofitsiant), stolga olib kelish (kassada to'lab stol raqami bilan), boshqa kassa buyurtmasi.
- **Katta guruh**: ma'lum mehmonlar sonidan boshlab butun buyurtmaga boshqa foiz (masalan, 8 va undan ko'p mehmon — 18%).
- **Kuver**: har mehmon uchun belgilangan summa (kamida 1 mehmon). Chekda kuver joy narxi qatori o'rnida turadi.
- **Stolovaya**: xizmat haqi qonun bo'yicha 0%.

Summani server qoida bo'yicha hisoblaydi: schyot va chekda xizmat haqi qatori o'zi chiqadi. Mehmon xizmat haqi haqida so'rasa,
schyotdagi e'lonni ko'rsating. E'tiroz bildirsa, xizmat qatorini olib tashlash — menejer tasdig'i bilan ([kassa.md](kassa.md#9-ochirish-va-menejer-tasdigi)).

<!-- TEKSHIRILMAGAN: e'lon matni schyot qog'ozida va kassa schyot ekranida K2 tomonidan chiqarilishi (bill print: service_charge_notice) — kod hali yozilmoqda; backendda `charges.service_charge_notice` va schyotda `service_charge_notice` bor -->

## 7. Six, og'irlik va mangal vaqti (kabobxona, baliqxona)

**Six tez tugmalari.** Six va dona taomlar yonida **×2 … ×10** tugmalari (qaysilari — ega belgilagan): bir bosishda shuncha dona qo'shiladi.
Variantli yoki qo'shimchali taomda avval tanlov oynasi ochiladi, keyin shuncha dona qo'shiladi.

**Og'irligi keyin tortiladigan taom** (baliq, qo'y qovurg'a). Taom ro'yxatida "og'irligi keyin tortiladi" yozuvi turadi. Taomni
qo'shsangiz, qator **Og'irlik kutilmoqda** belgisi bilan chiqadi, miqdori esa **taxminiy**.

1. Mehmonga taomni olib chiqing, tortgach qatorni oching va **Og'irlikni kiriting** ni bosing (ofitsiant ilovasida ham, kassada ham;
   buyurtma bilan ishlash huquqi kerak). Xom yoki pishgan og'irlik ekanini oyna yozadi.
2. Tarozidagi og'irlikni (kg, 3 kasrgacha) kiriting → **Saqlash**.
3. Qator **Tortildi** bo'ladi, narx qatorning kg narxi bo'yicha qayta hisoblanadi, schyot va chekda haqiqiy kg chiqadi
   ("Sazan 1.35 kg × 245 000").

Muhim:

- **Og'irlik kiritilmaguncha schyot tuzib ham, chop etib ham bo'lmaydi**: ilova "Og'irlik kutilmoqda (N): taom tortilgach schyot
  chiqadi" deydi. Qator o'chirilsa (mehmon voz kechsa), schyot yana tuzilishi mumkin.
- Oshpaz taomni og'irligi kiritilmagan bo'lsa ham pishiraveradi: oshxona ekranida qatorda **Og'irlik kutilmoqda** belgisi turadi, u
  taomni tayyor deb belgilashga to'sqinlik qilmaydi.
- Tortilgan og'irlikni **qayta** o'zgartirish menejer tasdig'i bilan (mehmonga aytilgan narx o'zgaradi).

**Mangal vaqti.** Ega taomga "mangal vaqti" (masalan, 15 daqiqa) belgilagan bo'lsa, oshxona ekranida yuborilgan paytdan sanab
turiladi ([oshxona-ekrani.md](oshxona-ekrani.md#14-restoran-va-kafe-kurslar-ogirlik-mangal-vaqti-stol-raqami)). Mehmonga "taxminan
15 daqiqa" deb aytishingiz mumkin.

<!-- TEKSHIRILMAGAN: ×N tugmalari (add_items_sheet.dart), "Og'irlikni kiritish" va "Saqlash" (order_widgets.dart, restaurant_sheets.dart) K1 ekranida; kassadagi og'irlik oynasi (restaurant_widgets.dart "Og'irlikni yozish") K2 ekranida — tugmalar nomi o'zgarishi mumkin; "Og'irlik kutilmoqda (N)" matni order_page.dart da bor -->

## 8. Kafe: stolga olib kelish

Kafeda mehmon peshtaxtada **oldindan to'laydi**, taom esa stolga olib boriladi. Buning uchun kassada buyurtmaga **stol raqami
tablichkasi** yoziladi:

1. Tez kassada buyurtmani yig'ing.
2. **Stol raqami** maydoniga stolning tablichkasi raqamini yozing (masalan, 12; 20 belgigacha, zal xaritasidagi stol shart emas).
3. To'lang. Talonda va oshxona chiptasida **Stol 12** chiqadi.

Oshxona ekranida chiptaning sarlavhasida **Stol 12** belgisi turadi: yuguruvchi taomni shu stolga olib boradi. Raqamni keyin ham
yozish yoki o'zgartirish mumkin (harakatlar jurnaliga yoziladi). Stolga olib kelishda xizmat haqi buyurtma turi bo'yicha qoidaga
ko'ra olinadi (6-bo'lim).

<!-- TEKSHIRILMAGAN: kassadagi "Stol raqami" maydoni, talonda chiqishi va keyin o'zgartirish (K2 ro'yxatida bor, kod hali yo'q); oshxona ekranidagi "Stol 12" belgisi tekshirilgan (kds testlari) -->

## 9. Internetsiz

- **Ofitsiant telefoni** internetsiz faqat ko'rish uchun ([ofitsiant.md](ofitsiant.md#10-aloqa-yoqolganda)): kursni yuborish,
  mehmon raqamini qo'yish, stolni boshqa ofitsiantga berish, og'irlikni kiritish va "Ishga keldim / Ishdan ketdim" aloqa
  qaytguncha kutadi.
- **Kassa** internetsiz ham ishlaydi ([kassa.md](kassa.md#20-internet-uzilsa)). Qatorning kursi va "og'irligi keyin tortiladi"
  belgisi navbat bilan serverga ketadi; **N-kursni yuborish** va og'irlikni kiritish ham navbatga yoziladi. Kassa o'z
  ko'rinishida keyingi kursni ushlab turadi va og'irligi kutilayotgan qator bilan schyot tuzmaydi. Agar shu orada ega **Kurslar**
  modulini o'chirgan bo'lsa, qurilmadagi qatorning kursi tashlab yuboriladi: sotuv hech qachon rad etilmaydi.
- **Mehmonlar bo'yicha** bo'lish internet talab qiladi: internetsiz **Teng bo'lish** va **Taom bo'yicha** ishlaydi.
- Choy puli internetsiz ham yoziladi: sinxronlashda har doim saqlanadi (pul olingan).

<!-- TEKSHIRILMAGAN: kassada internetsiz "N-kursni yuborish" va og'irlik (PosOffline.sendCourse / setWeight bor, cashier order_controller.dart:496 internetsiz deb izohlangan; tugma holati ekranda ko'rilmagan) -->

## 10. Muammolar va yechimlar

| Muammo | Nima qilish kerak |
|---|---|
| Kurs tanlash qatori yo'q | Filialda **Kurslar** moduli o'chiq (yoki tarifda yo'q). Egaga ayting |
| "N-kursni yuborish" tugmasi yo'q | Kursda yuboriladigan qator qolmagan yoki oldingi kurs hali yuborilmagan. **Kurslar** blokida holatni ko'ring |
| 2-kurs qatori ushlab turilmadi, keyingi **Yuborish** bilan ketdi | Oshxonaga 2-kursdan keyingi kurs (masalan, 3-kurs) allaqachon chiqqan edi: faqat undan **keyingi** kurslar ushlab turiladi |
| "2-kurs tayyor" xabari kelmadi | Buyurtma sizniki emas, ilova yopiq bo'lgan yoki aloqa uzilgan. Buyurtma ekranidagi **Kurslar** blokida holat yangilanadi. Oshpaz kursning hamma taomini belgilamagan bo'lishi mumkin |
| Schyotni "Mehmonlar bo'yicha" bo'lmadi | Qatorlarda mehmon raqami yo'q, yoki internet yo'q, yoki sizda to'lov olish huquqi yo'q |
| Mehmon boshqa mehmonning schyotida taom ko'rdi | Qatorning mehmon raqami noto'g'ri. Schyot to'lanmagan bo'lsa raqamni tuzating va qayta bo'ling |
| "Boshqa ofitsiantga berish" yo'q | **Stolni boshqa ofitsiantga o'tkazish** moduli o'chiq, yoki buyurtma ochiq stolniki emas (yopilgan, kassa buyurtmasi) |
| "Menejer tasdig'i kerak" | Sizda o'tkazish huquqi yo'q: menejer yoki ega o'z PIN'ini kiritadi |
| Choy puli maydoni yo'q | **Choy puli** moduli o'chiq. Pulni oldingiday faqat schyot bilan oling, egaga ayting |
| Choy puli noto'g'ri odamga yozildi | Menejerga ayting: u **Choy puli** sahifasida (panelda) yoki kassada sabab bilan tuzatadi |
| Schyot "Og'irlik kutilmoqda" deb chiqmayapti | Tortilmagan taom bor. Og'irlikni kiriting yoki qatorni o'chiring |
| Kassada "Stol raqami" yo'q | **Stolga olib kelish** moduli o'chiq (kafe formatida yoqiladi) |

## 11. Ega uchun: nimani yoqish kerak

1. **Kassa sozlamalari → Format** da **Restoran** (yoki **Kafe**, **Kabobxona**, **Baliqxona**) profilini tanlang: kerakli
   modullar o'zi yoqiladi. Keyin ularni bittalab o'chirish yoki qo'shish mumkin ([ega-pos-sozlash.md](ega-pos-sozlash.md#filial-formati-birinchi-qadam)).
2. **Choy puli va xizmat haqi** sahifasida (panelda **Choy puli**) choy puli rejimi, qozon qoidasi va xizmat haqi qoidalari
   ([choy-puli-va-ish-haqi.md](choy-puli-va-ish-haqi.md)).
3. **Menyu** da taom sahifasi → **Kassada sotish (six, og'irlik)**: ×N tugmalari, mangal vaqti (daqiqa) va "Og'irlikni keyin
   kiritish" (xom yoki pishgan). Faqat kabobxona va baliqxona uchun.
4. **Xodimlar** da ofitsiant, menejer, hostes va kassirlarga PIN va rol bering. Stolni o'tkazish huquqi (`orders.transfer`) standart
   bo'yicha faqat ega va menejerda; **Huquqlar** sahifasida o'zgartirish mumkin ([ega-pos-sozlash.md](ega-pos-sozlash.md#10-xodimlar-va-pin)).
5. Ofitsiantlarga ish haqi qoidalari — [choy-puli-va-ish-haqi.md](choy-puli-va-ish-haqi.md#3-ish-haqi-qoidalari).
