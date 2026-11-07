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
11. [Windows kassa](#11-windows-kassa)

## 1. Qaysi uskuna nima bilan ishlaydi

| Uskuna | Qanday ulanadi | Holati |
|---|---|---|
| Windows monoblok va kassa | USB printer (Windows drayveri orqali), LAN printer, printer orqali kassa qutisi; ikkinchi monitorda mijoz ekrani; lokal tarmoq markazi ([11-bo'lim](#11-windows-kassa)) | USB va LAN printer ishlaydi; printer qidirish, ikkinchi monitor va markaz hozircha sinalmagan |
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
  nom bilan bir xil bo'lishi kerak (boshqacha bo'lsa, kassa sozlamalarida almashtiring). Aniq nomni kassada **Printerni qidirish** →
  **Windows printerlari** ro'yxatida ko'rasiz ([11-bo'lim](#11-windows-kassa)).
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
- Ofitsiant va oshxona ilovasida holat belgisi: odatdagi (internet bor), **"Lokal tarmoq"** (internet yo'q, markaz bor — ish davom etadi),
  **"Aloqa yo'q"** / **"Internet yo'q"** (na internet, na markaz — faqat ko'rish, yangi buyurtma ochilmaydi). Mijoz ekranining burchagida kichik
  "Lokal tarmoq" yoki "Aloqa yo'q".
- Lokal rejimda ofitsiant **schyotni qayta chiqara olmaydi** (menejer PIN'i internetsiz tekshirilmaydi) — kassada chiqaring. Oshxonada
  "Buyurtma tayyor" o'rniga taomlarni birma-bir **Tayyor** qiling.
- Lokal rejimdan oddiy rejimga qaytish faqat markazdagi navbat serverga yetgandan keyin bo'ladi.

### Sozlash

1. Ega boshqaruv panelida markaz kassani tanlaydi: **Kassa sozlamalari** → **Lokal tarmoq** → **Markaz**. Tanlanmasa — chop etuvchi
   kassa, u ham bo'lmasa birinchi ulangan kassa (ofitsiant telefoni hech qachon markaz bo'lmaydi). Filialda bitta markaz bo'ladi. Kassaning
   o'zida **Sozlamalar** → **Lokal tarmoq** bo'limi holatni, IP, portni, ulangan qurilmalarni va qo'lda ulanish uchun QR'ni ko'rsatadi.
2. Hamma qurilma bitta Wi-Fi'da bo'lsin (routerda "klientlar bir-birini ko'rmasin" / AP isolation **o'chiq**).
3. Har qurilma bir marta internet bilan kirsin (kalit shu paytda keladi). Keyin ular markazni o'zi topadi; topmasa, markaz manzilini
   (kassadagi IP yoki `rizqim-lan://...`) qo'lda kiriting: ofitsiant — yuqoridagi aloqa belgisini bosing; oshxona — "Lokal tarmoq" /
   "Internet yo'q" belgisini bosing; mijoz ekrani — pastki chap burchakni 2 soniya bosib turing → **Lokal tarmoq**.
4. Qurilmalar vaqti to'g'ri bo'lsin (avtomatik vaqt yoqiq).

### Nima qilmaslik

- Markaz kassani yopib, boshqa kassada ishlayverish: markaz o'chiq bo'lsa lokal rejim ishlamaydi.
- Wi-Fi'ni o'chirib qo'yish: lokal rejim Wi-Fi'ga tayanadi. Internet kabeli uzilsa ham Wi-Fi router yoqiq tursin (UPS).

### Telefon yo'qolsa: kalitni almashtirish

Boshqaruv panelida **Kassa sozlamalari** → **Lokal tarmoq** → **Kalitni almashtirish** (tasdiqlang). Har kassa, telefon va ekran yangi kalitni
keyingi kirishda (yoki qayta ishga tushganda) oladi; ungacha lokal tarmoqqa ulanmaydi — buni internet bor paytda qiling. Yo'qolgan telefonni
**Sozlamalar** → **Qurilmalar** da o'chirib qo'ying.

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
| Windows kassada qurilmalar markazga ulanmaydi | Windows xavfsizlik devori (firewall) | "Lokal tarmoq" belgisini bosing → **Ruxsat berish** yoki [11-bo'lim](#11-windows-kassa)dagi buyruq |
| Windows kassada "Windows printeri topilmadi" | paneldagi nom Windows'dagidan farq qiladi | **Printerni qidirish** dagi nomni panelga aynan yozing |

## 11. Windows kassa

<!-- TEKSHIRILMAGAN: Windows bo'limi CI'da yig'ilgan va testlangan (windows-build), haqiqiy Windows kompyuterda sinalmagan -->

**Hozircha sinalmagan.** Kassa ilovasi Windows 10/11 (64 bit) da ishlaydi. Tekshirish ro'yxati — [HARDWARE_TEST.md](../HARDWARE_TEST.md) 5-bo'lim.

### USB printer

1. Printerni USB bilan ulang va **ishlab chiqaruvchining drayverini** o'rnating (Xprinter, Rongta, Epson va h.k. saytidan). Windows'ning
   o'zidan "sinov sahifasi" chiqsin.
2. Kassada **Sozlamalar** → **Printerni qidirish**: **Windows printerlari** ro'yxatida printer ko'rinadi (PDF, OneNote, faks kabi "virtual"
   printerlar oxirida, "printer emasdir" deb). Tanlang → **Chek** → **Sinov cheki**, **Pul qutisini ochish**.
3. Panelda printer qo'shing: **Ulanish: USB**, **Kassa terminali** = shu kompyuter, **Nomi** — ro'yxatdagi nomi bilan **aynan bir xil**.

Kassa baytlarni drayverga "RAW" holida beradi (drayver ularni o'zgartirmaydi), shuning uchun kesish, QR va kassa qutisi printerning o'z
buyruqlari bilan ishlaydi. Ro'yxat bo'sh va "Print Spooler" haqida yozilsa — Windows'ning **Chop etish navbati** xizmati to'xtagan:
kompyuterni qayta yuklang yoki `services.msc` da **Print Spooler** ni yoqing.

### Ikkinchi monitor — mijoz ekrani

Mijozga qaragan monitorni kompyuterga ulang (Windows **Displey** sozlamasida **Kengaytirish**) va shu kompyuterda **Mijoz ekrani**
ilovasini o'rnating. U ikkinchi monitorda o'zi **to'liq ekran** ochiladi, kassa oynasi va klaviatura (skaner) asosiy monitorda qoladi.
Mijoz ekrani kassaga shu kompyuter ichida (`127.0.0.1`) ulanadi — internet va firewall sozlamasi shart emas. Ulash — oddiy mijoz ekrani
kabi ([mijoz-ekrani.md](mijoz-ekrani.md)).

Qo'shimcha (yorliq xususiyatlarida **Obyekt** oxiriga yoziladi): `--monitor=2` — qaysi monitor (chapdan sanaladi), `--fullscreen` —
bitta monitorli kompyuterda ham to'liq ekran (faqat mijoz ekrani uchun alohida kompyuter yoki televizor), `--windowed` — oddiy oyna.
Monitor uzilsa, ekran asosiy monitorda oddiy oynaga qaytadi; qayta ulansa — yana to'liq ekran. Chiqish: **Alt+F4**.

Ikkala ilovani **Ishga tushirish** papkasiga qo'ying (`Win+R` → `shell:startup`, avval kassa, keyin mijoz ekrani yorlig'i), shunda
kompyuter yoqilganda o'zi ochiladi.

### Skaner

USB skaner Windows'da klaviatura sifatida ishlaydi (drayver kerak emas). Skanerni "klaviatura (HID)" rejimiga va kod oxirida **Enter**
yuboradigan qilib sozlang. Skaner kodi doim **fokusdagi oynaga** yoziladi — shuning uchun mijoz ekrani fokusni olmaydigan qilib ochiladi;
boshqa oyna ochilgan bo'lsa, kassa oynasini bir marta bosing.

### Kassa — lokal tarmoq markazi

Windows kassa filialning markazi bo'lishi mumkin ([9-bo'lim](#9-lokal-tarmoq-internet-uzilsa-ham-zal-ishlaydi)). Buning uchun Windows
xavfsizlik devori (firewall) boshqa qurilmalarga **18480** portni ochishi kerak — **bir marta**:

- Kassa birinchi marta markaz bo'lganda Windows "ruxsat berasizmi?" deb so'rasa — **Ruxsat berish** (administrator paroli bilan).
- Yoki kassada yuqoridagi **Lokal tarmoq: 0 qurilma** belgisini bosing → **Ruxsat berish** → Windows so'raganda **Ha**. Kassa kerakli
  qoidalarni (`Rizqim POS lokal tarmoq`) o'zi qo'shadi va oldin bosilgan "Bekor qilish" ning to'sig'ini olib tashlaydi.
- Yoki kompyuter ustasi administrator buyruq oynasida bajaradi:

  ```
  netsh advfirewall firewall add rule name="Rizqim POS lokal tarmoq" dir=in action=allow protocol=TCP localport=18480 profile=any
  netsh advfirewall firewall add rule name="Rizqim POS lokal tarmoq" dir=in action=allow protocol=UDP localport=18480 profile=any
  ```

Oshxona ekrani yoki mijoz ekrani **boshqa** Windows kompyuterda bo'lsa, o'sha kompyuterda ham shu buyruqlar bajariladi (u markaz
beacon'ini eshitishi uchun); topmasa, sozlamalarda kassa IP'sini qo'lda kiriting. Markaz kompyuter **uyquga ketmasin** (Quvvat va uyqu →
Hech qachon) va Windows yangilanishi ish vaqtida qayta yuklamasin (**Faol soatlar**).
