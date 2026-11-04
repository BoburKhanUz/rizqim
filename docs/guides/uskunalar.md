# Uskunalar va lokal tarmoq: printer, skaner, kassa qutisi, internetsiz zal

Bu qo'llanma kassaga ulanadigan uskunalar (chek, oshxona va yorliq printerlari, kassa qutisi, skaner, ikkinchi ekran) va
**lokal tarmoq** (internet uzilsa ham zalning ishlashi) haqida. Texnik bilim kerak emas. Uskunani sotib olgandan keyin
ro'yxat bo'yicha tekshirish — [HARDWARE_TEST.md](../HARDWARE_TEST.md).

> **Muhim.** Bu qo'llanmaning bir qismi hali haqiqiy uskunada sinalmagan. Shunday joylar `<!-- TEKSHIRILMAGAN -->` belgisi bilan
> ajratilgan (matn ichida ko'rinmaydi, lekin fayl manbasida bor) va matnda "**Hozircha sinalmagan**" deb yozilgan. Pilotdan oldin
> [HARDWARE_TEST.md](../HARDWARE_TEST.md) ni bajaring.

## Mundarija

1. [Qaysi uskuna nima bilan ishlaydi](#1-qaysi-uskuna-nima-bilan-ishlaydi)
2. [Printer qo'shish (ega)](#2-printer-qoshish-ega)
3. [Printerni topish va sinash (kassa)](#3-printerni-topish-va-sinash-kassa)
4. [Printer turlari bo'yicha](#4-printer-turlari-boyicha)
5. [Kassa qutisi](#5-kassa-qutisi)
6. [Skaner](#6-skaner)
7. [Mijoz ekrani va Sunmi D2](#7-mijoz-ekrani-va-sunmi-d2)
8. [Schyotni avtomatik chiqarish](#8-schyotni-avtomatik-chiqarish)
9. [Lokal tarmoq: internet uzilsa ham zal ishlaydi](#9-lokal-tarmoq-internet-uzilsa-ham-zal-ishlaydi)
10. [Muammolar va yechimlar](#10-muammolar-va-yechimlar)

## 1. Qaysi uskuna nima bilan ishlaydi

| Uskuna | Qanday ulanadi | Holati |
|---|---|---|
| Windows monoblok va kassa | USB printer (Windows drayveri orqali), LAN printer, printer orqali kassa qutisi | ishlaydi |
| Sunmi (V2, V2 Pro, D2) | ichki printer; D2'da ikkinchi ekran | ichki printer ishlaydi; D2 ikkinchi ekrani hozircha sinalmagan |
| Android planshet | LAN printer; Bluetooth va USB (OTG) printer | LAN ishlaydi; Bluetooth va USB hozircha sinalmagan |
| Oshxona printeri | LAN (port 9100) | ishlaydi |
| Yorliq printeri (stiker) | LAN (TSPL tili: Xprinter, Gprinter) | hozircha haqiqiy printerda sinalmagan |
| Skaner | USB/Bluetooth skaner "klaviatura" rejimida; Sunmi ichki skaneri | hozircha sinalmagan |
| iPad | faqat LAN printer | v1 ga kirmaydi |

Chop etishni **kassaning o'zi** bajaradi (LAN yoki USB orqali), server emas. Shuning uchun internet uzilsa ham sex markasi va
schyot chiqaveradi, faqat qurilma va printer bir tarmoqda bo'lsin.

## 2. Printer qo'shish (ega)

**Hamkor paneli** → **Kassa sozlamalari** → **Printerlar** → **Printer qo'shish**. Batafsil maydonlar:
[ega-pos-sozlash.md](ega-pos-sozlash.md#7-sexlar-va-printerlar).

Qisqacha:

- **Vazifasi**: **Chek** (schyot, to'lov cheki), **Oshxona** (sex markasi), **Yorliq** (stiker).
- **Ulanish**: **LAN** (IP va port 9100), **Bluetooth** (MAC manzil), **USB** yoki **Ichki (Sunmi)** (kassa terminalini tanlang: printer shu
  terminalga ulangan va faqat u chop etadi).
- **Qog'oz kengligi**: 58 yoki 80 mm. Chek qog'ozi kamida 55 mm.
- **Kodlash** va **Matnni rasm sifatida chop etish** — o'zbek harflari noto'g'ri chiqsa.

Maslahat: LAN printerga routerda **doimiy IP** bering; printer, router va kassaga UPS qo'ying.

## 3. Printerni topish va sinash (kassa)

**Sozlamalar** → **Printerlar** (kassada). <!-- TEKSHIRILMAGAN: "Printerni qidirish", "Sinov cheki" va "Pul qutisini ochish" tugmalari hozir yozilmoqda (stage A, H1); amaldagi ekran farq qilishi mumkin -->

1. **Printerni qidirish** — kassa tarmoqni (LAN) tekshiradi va 9100 portida javob beradigan printerlarni ko'rsatadi; Bluetooth uchun
   avval telefon sozlamalarida **juftlangan** printerlar, USB uchun ulangan printerlar ro'yxatda chiqadi.
2. Ro'yxatdan printerni tanlang va **vazifasini** belgilang: chek, oshxona yoki yorliq.
3. **Sinov cheki** — sinov sahifasi chiqadi: do'kon nomi, printer nomi, kod sahifasi qatori, QR va qog'oz kesilishi. Matn o'qilsa,
   o'zbek harflari (`o'`, `g'`) to'g'ri bo'lsa va QR telefon bilan o'qilsa — printer tayyor.
4. **Pul qutisini ochish** — kassa qutisi ochilishini tekshiradi.

Topilmasa: printer yoqiq va kassa bilan bir Wi-Fi'dami? IP'ni qo'lda yozing (printerning o'zi o'z IP'sini sinov sahifasida
chiqaradi: o'chirib, "FEED" tugmasini bosib yoqing).

Chek yoki marka chiqmasa, ish ekranlarida **"Chop etilmadi"** banneri chiqadi; sababni tuzating va **Qayta urinish** ni bosing.
Buyurtma va to'lov printerga bog'liq emas: chek chiqmasa ham to'lov o'tadi, ish navbatda turadi.

## 4. Printer turlari bo'yicha

### Sunmi ichki printer

Alohida sozlash kerak emas: panelda **Ulanish: Ichki (Sunmi)** va shu Sunmi terminalini tanlang. Qog'oz — 58 mm. Qog'oz tugasa banner
chiqadi; qog'oz qo'yib **Qayta urinish** ni bosing.

### Bluetooth printer (58 mm)

<!-- TEKSHIRILMAGAN: Bluetooth printer drayveri hozir yozilmoqda va haqiqiy printerda sinalmagan -->

**Hozircha sinalmagan.** Tartib: printerni avval Android **Sozlamalar → Bluetooth** orqali juftlang → panelda **Ulanish: Bluetooth** va
printer **MAC** manzilini kiriting (yoki kassada **Printerni qidirish** orqali juftlanganlar ro'yxatidan tanlang) → **Sinov cheki**.
Android 12 va yangisida ilova **Yaqin atrofdagi qurilmalar** ruxsatini so'raydi — ruxsat bering. Printer o'chib yonsa, ilova qayta ulanadi.

### USB printer

- **Windows kassa**: printer Windows'da o'rnatilgan bo'lsin (drayver). Panelda **Ulanish: USB** va **Kassa terminali**. Printer nomi serverdagi
  nom bilan bir xil bo'lishi kerak (boshqacha bo'lsa, kassa sozlamalarida almashtiring).
- **Android (OTG kabel)**: <!-- TEKSHIRILMAGAN: Android USB OTG printer hozir yozilmoqda --> **hozircha sinalmagan.** Printerni ulaganda Android
  "USB qurilmaga ruxsat" so'raydi — ruxsat bering.

### LAN printer (oshxona)

IP va port (9100) bilan ulanadi, hamma kassa va telefon shu manzil bo'yicha ko'radi. Eng ishonchli usul. Oshxonaning har sexi uchun
alohida printer qo'shish mumkin; sexsiz taom umumiy oshxona printeriga chiqadi.

### Yorliq printeri (stiker)

<!-- TEKSHIRILMAGAN: haqiqiy TSPL printerda (Xprinter, Gprinter) chop etish sinab ko'rilmagan: kod emulyator bilan tekshirilgan -->

Fast food va qahvaxona uchun: **Vazifasi: Yorliq**, LAN. Stikerlar haqida — [fast-food.md](fast-food.md#6-stiker-va-yorliq-printeri).
**Hozircha haqiqiy printerda sinalmagan.**

## 5. Kassa qutisi

Kassa qutisi chek printeriga ulangan bo'ladi (printer orqali). Naqd to'lovda quti o'zi ochiladi. Sinash: **Pul qutisini ochish**
(3-bo'lim). Quti ochilmasa — kabel (RJ11) va printerning "quti" kirishini tekshiring.

## 6. Skaner

<!-- TEKSHIRILMAGAN: skaner tinglovchisi hozir yozilmoqda; kassada mahsulotni shtrixkod bo'yicha topish katalogda shtrixkod bor-yo'qligiga bog'liq -->

**Hozircha sinalmagan.** Ikki xil:

- **USB/Bluetooth skaner (klaviatura rejimi)** — skaner kodni tez yozilgan raqamlar sifatida yuboradi, oxirida Enter. Kassa buni
  odatiy yozishdan ajratadi. Skaner "klaviatura (HID)" rejimida va Enter yuboradigan qilib sozlangan bo'lsin (skaner qo'llanmasida
  sozlash kodlari bor).
- **Sunmi ichki skaneri** — Sunmi o'zi kodni ilovaga uzatadi.

Skanerlangan kod bilan mahsulotni topish — mahsulotda shtrixkod yoki artikul kiritilgan bo'lsa.

## 7. Mijoz ekrani va Sunmi D2

Alohida mijoz ekrani (planshet yoki monitor) — [mijoz-ekrani.md](mijoz-ekrani.md). **Sunmi D2** ning ikkinchi ekrani (mijoz tomonidagi)
kassa ilovasining o'zi ichida chek va jamini ko'rsatadi, alohida qurilma kerak emas. <!-- TEKSHIRILMAGAN: D2 ikkinchi ekrani hozir yozilmoqda -->
**Hozircha sinalmagan.**

## 8. Schyotni avtomatik chiqarish

<!-- TEKSHIRILMAGAN: avtomatik schyot chop etish emulyator va soxta printer bilan tekshirilgan, haqiqiy printerda sinalmagan -->

Ofitsiant mehmon hisob so'raganda **Schyot so'rash** ni bosadi. Filialda **Schyotni avtomatik chiqarish** yoqiq bo'lsa (standart),
server schyotni o'zi tuzadi va kassa uni **o'zi chop etadi** — kassir hech narsani bosmaydi. Schyot **fiskal chek emas** ("Bu fiskal chek emas"):
bu faqat mehmonga ko'rsatiladigan hisob. To'lovni kassir oladi, fiskal chekni to'lovdan keyin kassa chiqaradi.

### Ega: sozlash

**Hamkor paneli** → **Kassa sozlamalari** → **Kassa sozlamalari** yorlig'i:

- **Schyotni avtomatik chiqarish** — yoqish/o'chirish. O'chiq bo'lsa, "Schyot so'rash" faqat kassada belgi qo'yadi, schyotni kassir o'zi chiqaradi.
- **Schyot printeri** — schyot qaysi printerdan chiqadi (filialning faol **Chek** printeri). Bo'sh qoldirsangiz, **chop etuvchi kassa**ning
  chek printeridan chiqadi. Chop etuvchi kassa ham tanlanmagan bo'lsa va bir nechta kassa bo'lsa, schyot hamma kassada chiqib ketishi
  mumkin: **bitta chop etuvchi kassani yoki bitta schyot printerini tanlang.**

### Ofitsiant

1. Buyurtmada **Schyot so'rash** → **So'rash**.
2. "Schyot so'raldi: kassada chiqadi." yozuvi chiqadi. Schyot kassa printerida bir necha soniyada paydo bo'ladi. Uni mehmonga olib boring.
3. Schyot yana kerak bo'lsa (yo'qolgan, mehmon ikkinchisini so'ragan): **Schyotni qayta chiqarish** → **Chiqarish**. Ofitsiantning bu
   huquqi yo'q, shuning uchun **menejer PIN'i** so'raladi (menejer o'z PIN'ini kiritadi). Kassada **NUSXA** yozuvi bilan chiqadi.
   Qayta chiqarish jurnalga yoziladi (kim so'radi, kim tasdiqladi).

Agar schyot hali tuzilmagan bo'lsa (buyurtma bo'sh, yoki og'irlik kutilmoqda), "Schyot hali tuzilmagan" deyiladi — avval taomni tuzating.

### Kassir

Schyot o'zi chiqadi. Chiqmasa — ekranda **"Chop etilmadi"** banneri: qog'oz/printerni tekshirib **Qayta urinish**. Aloqa bo'lmaganda
"Schyot chiqmadi" xabari chiqadi: schyotni buyurtmadan qo'lda chiqaring ([kassa.md](kassa.md#11-schyot-chegirma-va-hisobni-bolish)).
Avtomatik chiqqan schyot buyurtmani **qulflamaydi** (taom qo'shish/o'chirish oldingidek); kassir "Schyotni chiqarish" bosganda schyot rasman
mehmonga topshirilgan hisoblanadi va buyurtma qulflanadi.

## 9. Lokal tarmoq: internet uzilsa ham zal ishlaydi

<!-- TEKSHIRILMAGAN: lokal ko'prik (markaz, ofitsiant/oshxona/mijoz ekrani mijozlari) hozir yozilmoqda va haqiqiy uskunada sinalmagan (stage A/B) -->

**Hozircha sinalmagan; hozir qurilmoqda.** Muammo: internet uzildi, lekin Wi-Fi ishlab turibdi. Oldin ofitsiantlar buyurtma ocha olmasdi.
Endi:

- **Asosiy kassa — markaz.** Ofitsiant telefonlari, oshxona ekrani va mijoz ekrani unga bitta Wi-Fi orqali ulanadi.
- Internet uzilsa: ofitsiantlar buyurtma oladi va yuboradi, **oshxona ekrani chiptalarni oladi**, oshpaz **Tayyor** bosadi va
  ofitsiant telefonida ko'rinadi, mijoz ekrani raqamlarni ko'rsatadi, schyot so'rash va avtomatik chop etish ishlaydi.
- **To'lov, chegirma, qaytarish va hisobni yopish faqat kassaning o'zida** (masofadan to'lov yo'q).
- Internet qaytgach hammasi **tartib bilan serverga o'zi yuboriladi**; takror buyurtma bo'lmaydi.
- Xavfsizlik: lokal xabarlar filial kaliti bilan imzolanadi; begona qurilma ulana olmaydi. Kalitni ega panelda yangilaydi (jurnalga yoziladi).

### Belgilar

- Kassada **"Lokal tarmoq: 3 qurilma"** — markazga ulangan qurilmalar soni.
- Ofitsiant va oshxona ilovasida holat belgisi: **bulut** (internet bor, oddiy ish), **lokal** (internet yo'q, markaz bor — ish davom etadi),
  **yolg'iz** (na internet, na markaz — faqat ko'rish, yangi buyurtma ochilmaydi).
- Lokal rejimdan oddiy rejimga qaytish faqat markazdagi navbat serverga yetgandan keyin bo'ladi.

### Sozlash

1. Asosiy kassani markaz deb belgilang (**Sozlamalar** → **Lokal tarmoq**). Filialda bitta markaz bo'ladi.
2. Hamma qurilma bitta Wi-Fi'da bo'lsin (routerda "klientlar bir-birini ko'rmasin" / AP isolation **o'chiq**).
3. Har qurilma bir marta internet bilan kirsin (kalit shu paytda keladi). Keyin ular markazni o'zi topadi; topmasa, sozlamalarda IP yoki QR
   kiriting.
4. Qurilmalar vaqti to'g'ri bo'lsin (avtomatik vaqt yoqiq).

### Nima qilmaslik

- Markaz kassani yopib, boshqa kassada ishlayverish: markaz o'chiq bo'lsa lokal rejim ishlamaydi.
- Wi-Fi'ni o'chirib qo'yish: lokal rejim Wi-Fi'ga tayanadi. Internet kabeli uzilsa ham Wi-Fi router yoqiq tursin (UPS).

Texnik tavsif: [POS_SPEC.md](../POS_SPEC.md) 5.5 va [pos_lan README](../../pos/packages/pos_lan/README.md).

## 10. Muammolar va yechimlar

| Muammo | Sabab | Yechim |
|---|---|---|
| Printer ro'yxatda yo'q | tarmoq/ulanish | printer yoqiqmi, bir Wi-Fi'dami; IP'ni qo'lda kiriting |
| Chek chiqmaydi, "Chop etilmadi" | qog'oz, uzilish | tuzatib **Qayta urinish** |
| O'zbek harflari `?` yoki kvadrat | kod sahifasi | **Kodlash**ni o'zgartiring yoki **Matnni rasm sifatida** |
| Chek tor/keng chiqadi | qog'oz eni | sozlamada 58 yoki 80 mm |
| Schyot hamma kassada chiqdi | chop etuvchi kassa tanlanmagan | panelda bitta chop etuvchi kassa yoki schyot printerini tanlang |
| Schyot chiqmadi, ofitsiantga "so'raldi" dedi | printer/aloqa | kassadagi banner/xabarni o'qing; schyotni qo'lda chiqaring |
| "Schyotni qayta chiqarish" menejer PIN'ini so'raydi | ofitsiantda huquq yo'q | menejer o'z PIN'ini kiritadi (to'g'ri ishlayapti) |
| Lokal rejimda ofitsiant buyurtma ocha olmaydi ("yolg'iz") | markaz o'chiq yoki Wi-Fi yo'q | asosiy kassani yoqing, Wi-Fi'ni tekshiring |
| Oshxona ekrani internet uzilganda chipta olmayapti | lokal tarmoq sozlanmagan | 9-bo'limdagi sozlashni bajaring (hozircha sinalmagan) |
