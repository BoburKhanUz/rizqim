# Haqiqiy uskunada sinash ro'yxati

Phase 6 ("Uskunalar va lokal tarmoq") kodi emulyator va soxta printerlar bilan tekshirilgan, lekin **haqiqiy printer, skaner,
Sunmi va lokal tarmoq bilan hali sinalmagan**. Shu ro'yxat pilotdan oldin (yoki yangi uskuna sotib olinganda) bir marta
bajariladi. Har qadam: nima qilish, nimani ko'rish kerak, va `[ ]` — o'tdi/o'tmadi belgisi. O'tmagan qadamni yozib qo'ying
(pastdagi 14-bo'lim) va `docs/guides/uskunalar.md` dagi "TEKSHIRILMAGAN" belgisini faqat o'tgandan keyin olib tashlang.

Foydalanuvchi uchun qo'llanma — [guides/uskunalar.md](guides/uskunalar.md). Texnik asos — [POS_SPEC.md](POS_SPEC.md) 2.12
(qurilmalar), 5 (internetsiz ishlash, 5.5 lokal ko'prik) va [PHASE6_PLAN.md](PHASE6_PLAN.md) 8-bo'lim.

> **Eslatma.** Bu hujjatdagi ko'pchilik qadam hali yozilayotgan yoki sinalmagan imkoniyatga tegishli: Bluetooth va Android USB
> printer, printer qidirish va "Sinov cheki", skaner, D2 ikkinchi ekrani, lokal ko'prik (LAN bridge). Ekrandagi yozuvlar
> bir oz farq qilishi mumkin — farqni qayd qiling (14-bo'lim), qadamni o'tkazib yubormang.

## 0. Tayyorgarlik

- [ ] Sinov filiali va sinov egasi bor (haqiqiy do'konni ishlatmang). Panelda: bitta filial, bitta zal, 3–4 stol/xona, 5–6 taom
  (shu jumladan og'irlik bilan sotiladigan taom va bitta kombo), bitta sex ("Mangal"), 2 ta sex.
- [ ] Xodimlar: bitta kassir, bitta menejer (PIN'i bilan), ikkita ofitsiant, bitta oshpaz. Hammasiga PIN o'rnatilgan.
- [ ] Qurilmalar ro'yxati (to'ldiring):

| Qurilma | Model / OS | Ilova | Ulangan filial | Izoh |
|---|---|---|---|---|
| Sunmi (kassa) | | kassa | | |
| Android planshet (asosiy kassa — markaz) | | kassa | | |
| Windows monoblok | | kassa (markaz) + mijoz ekrani (2-monitor) | | |
| Ofitsiant telefon 1 | | ofitsiant | | |
| Ofitsiant telefon 2 | | ofitsiant | | |
| Oshxona planshet | | oshxona ekrani | | |
| Mijoz ekrani | | mijoz ekrani | | |

- [ ] Printerlar ro'yxati (to'ldiring):

| Printer | Model | Ulanish | Qog'oz | Vazifa | Manzil / MAC |
|---|---|---|---|---|---|
| Ichki (Sunmi) | | ichki | 58 | chek | |
| Bluetooth | | BT | 58 | chek | |
| USB (Android) | | USB OTG | 58/80 | chek | |
| LAN oshxona | | LAN | 80 | oshxona | |
| LAN yorliq | | LAN (TSPL) | 40×30 | yorliq | |
| Windows USB | | USB (Windows spooler) | 80 | chek | Windows'dagi nomi: |

- [ ] Hamma qurilma bitta Wi-Fi tarmog'ida (router: ulangan qurilmalar bir-birini ko'ra olishi kerak, "AP isolation" /
  "client isolation" **o'chiq**). Routerda LAN printerlarga doimiy IP berilgan.
- [ ] Ilovalar so'nggi versiyada; qurilma vaqti to'g'ri (avtomatik vaqt yoqiq — lokal ko'prik vaqt oynasi ±2 daqiqa).
- [ ] Har qadamni **telefon kamerasi** bilan suratga oling (chek, ekran). Qayd qilish ro'yxati — 14-bo'lim.

Uzbek harflarini tekshirish uchun sinov matni (har printerda chiqarib ko'ring): `O'zbekiston, g'isht, o'rik, Ch Sh Ng, ʻ ʼ`.

## 1. Sunmi (ichki printer, kassa qutisi, skaner, D2 ekrani)

Qurilma: Sunmi V2 / V2 Pro / D2 (qaysi biri bo'lsa).

### 1.1. Ichki printer

- [ ] Kassa ilovasini ulang (ulash kodi) va PIN bilan kiring. **Sozlamalar** → **Printerlar**: ichki printer (**Ichki printer (Sunmi)**)
  ro'yxatda.
- [ ] **Sinov chop etish** (yoki **Sinov cheki**): sahifa chiqadi — do'kon nomi, printer nomi, kod sahifasi qatori, QR, kesish.
  Tekshiring: matn o'qiladi; **QR telefon bilan o'qiladi**; qog'oz kesiladi (yoki yirtish chizig'i joyida).
- [ ] O'zbek harflari (`o'`, `g'`) to'g'ri chiqdi. Chiqmasa: **Matnni rasm sifatida** ni yoqing, qayta sinang — chiqdimi?
- [ ] 58 mm chek: matn qirqilmagan, ustunlar tekis (nom va narx bir qatorda).
- [ ] Haqiqiy sotuv: bitta buyurtma, to'lov → chek chiqdi. Chek fiskal emas yozuvi ("sinov rejimi") bor.
- [ ] Qog'ozni oling (qopqoqni oching) va chop eting: "Chop etilmadi" banneri chiqadi. Qog'oz qo'ying → **Qayta urinish** → chek chiqdi.

### 1.2. Kassa qutisi

- [ ] Qutini Sunmi/RJ11 orqali printerga ulang. Naqd to'lovda quti ochiladi.
- [ ] **Pul qutisini ochish** (sozlamalardagi sinov tugmasi) — quti ochiladi.
- [ ] Karta to'lovida quti ochilmaydi (qoida shunday bo'lsa).

### 1.3. Skaner (Sunmi ichki)

- [ ] Shtrixkod bor mahsulot (yoki istalgan shtrixkod) — kassada skanerlang. Natija ilovaga keladi: <!-- TEKSHIRILMAGAN: kassada mahsulotni shtrixkod bo'yicha topish katalogda shtrixkod bor-yo'qligiga bog'liq; hozir faqat skaner oqimi va ilgak -->
  mahsulot topilsa savatga tushadi, topilmasa "topilmadi" xabari.
- [ ] Tez ketma-ket 3 ta kod: hammasi o'qildi, takror yo'q.

### 1.4. D2 ikkinchi ekrani (mijoz ekrani)

- [ ] D2'da kassa ilovasi ochiq. Ikkinchi ekranda kassa savati chiqadi (taom, miqdor, jami).
- [ ] Taom qo'shsangiz/olib tashlasangiz ekran 1 soniyadan kam kechikish bilan yangilanadi.
- [ ] To'lov boshlanganda ekran to'lov holatini (va QR bo'lsa QR'ni) ko'rsatadi; to'langach "Rahmat".
- [ ] Kassa ilovasini yoping/qayta oching: ikkinchi ekran bo'shaydi va qaytadan ishlaydi.
- [ ] Ekran yorqinligi va shrift masofadan (1–2 m) o'qiladi.

## 2. Bluetooth 58 mm printer

- [ ] Printerni Android sozlamalarida **avval juftlang** (Bluetooth → yangi qurilma; PIN odatda `0000` yoki `1234`).
- [ ] Panelda printer qo'shing: **Ulanish: Bluetooth**, **Manzil**: MAC (`AA:BB:CC:DD:EE:FF`). <!-- TEKSHIRILMAGAN: kassa ilovasida Bluetooth printer hozir yozilmoqda (Android plugin); ilova ichida "Printerni qidirish" orqali juftlangan ro'yxatdan tanlash ham -->
- [ ] Android 12+: ilova **Yaqin atrofdagi qurilmalar** (Bluetooth) ruxsatini so'raydi. Ruxsat bering. Rad qilsangiz — aniq xato xabari
  chiqadi (sirli "xato" emas).
- [ ] **Sinov cheki** chiqdi. Matn va QR tekshirildi.
- [ ] Printerni o'chiring → chek yuboring → **"Chop etilmadi"** banneri; printerni yoqing → **Qayta urinish** → chiqdi (qayta ulanish ishlaydi).
- [ ] Printerdan 5–8 m uzoqlashing: chek chiqadi yoki aniq xato (qotib qolmaydi).
- [ ] 20 ta ketma-ket chek: hammasi chiqdi, tartib to'g'ri, orada uzilish yo'q. Vaqtini yozing.

## 3. Android USB printer (OTG)

- [ ] Printerni USB OTG kabel orqali ulang. Android "USB qurilmaga ruxsat" so'raydi — ruxsat bering ("Doim" tanlansa qulay).
- [ ] Printer sozlamalarida USB printer ro'yxatda. Panelda uni shu kassa terminaliga biriktiring (**Ulanish: USB**, **Kassa terminali**). <!-- TEKSHIRILMAGAN: Android USB OTG drayveri hozir yozilmoqda -->
- [ ] **Sinov cheki** chiqdi.
- [ ] Kabelni uzib ulang: ilova qayta ruxsat so'ramasdan (yoki bir marta so'rab) davom etadi; chek yo'qolmaydi (navbatda turadi).
- [ ] Planshetni quvvatga ulangan va ulanmagan holda sinang (OTG ba'zan quvvatni kamaytiradi).
- [ ] 20 ta ketma-ket chek.

## 4. LAN printerlar (oshxona, yorliq)

### 4.1. Oshxona printeri (ESC/POS, port 9100)

- [ ] Printer IP'si routerda belgilangan (`192.168.x.y`). Kompyuter/planshetdan `ping` yoki brauzer bilan javob beradi.
- [ ] **Printerni qidirish** (sozlamalarda): tarmoq skanerlanadi va printer ro'yxatda chiqadi (port 9100). Skanerlash 30 soniyadan kam. <!-- TEKSHIRILMAGAN: avtomatik qidirish hozir yozilmoqda; topilmasa IP'ni qo'lda kiriting -->
- [ ] Printerni tanlang → vazifa: **Oshxona** → **Sinov cheki** chiqdi.
- [ ] Oshxonaga yuborish: ofitsiant/kassir buyurtma yuboradi → sex markasi chiqdi: stol, vaqt, taom, miqdor katta shriftda. Ikki sexli buyurtma — har sexga o'z markasi.
- [ ] Mangal: six soni markada chiqdi (agar taomda six bo'lsa).
- [ ] Kod sahifasi: o'zbek harflari. Noto'g'ri bo'lsa `cp866`/`cp1251`'ni sinab ko'ring yoki **Matnni rasm sifatida**.
- [ ] Printer o'chiq: yuborish → bannerda "Chop etilmadi"; yoqing → **Qayta urinish** → chiqdi. Buyurtma o'zi to'xtamadi.
- [ ] Internetni uzing (routerdagi internet kabelini), Wi-Fi qolsin: sex markasi baribir chiqadi (chop etishni qurilmaning o'zi bajaradi).

### 4.2. Yorliq printeri (TSPL — Xprinter / Gprinter)

- [ ] Panelda: **Vazifasi: Yorliq**, LAN manzili. <!-- TEKSHIRILMAGAN: haqiqiy TSPL printerda sinab ko'rilmagan: kod emulyator bilan tekshirilgan -->
- [ ] Yorliq o'lchami (masalan 40×30 mm) printerga o'rnatilgan va kalibrlangan (bitta bo'sh yorliq chiqarib).
- [ ] **Sinov** yorlig'i: matn o'qiladi, chetidan qirqilmagan, QR/shtrixkod (bo'lsa) o'qiladi.
- [ ] Fast food to'lovidan keyin stakan stikeri: raqam, hajm, ichimlik nomi to'g'ri. Paket yorlig'i ham.
- [ ] Yorliq tugasa: to'lov to'xtamaydi, banner "Stiker chop etilmadi" + **Qayta urinish**.

## 5. Windows kassa (USB printer, ikkinchi monitor, skaner, lokal tarmoq markazi)

Qurilma: Windows 10/11 monoblok yoki kompyuter (64 bit), kassa ilovasi `rizqim_cashier.exe`; ikkinchi monitor (mijoz ekrani)
va USB chek printeri. Windows kodi faqat CI'da (`windows-build` job: ilovalar yig'iladi, spooler va LAN testlari Windows'da
ishlaydi) tekshirilgan, haqiqiy Windows kompyuterda **hali sinalmagan**. <!-- TEKSHIRILMAGAN: Windows bo'limi butunlay -->

### 5.1. USB printer (Windows spooler orqali)

- [ ] Printer drayveri Windows'da o'rnatilgan (**Sozlamalar → Bluetooth va qurilmalar → Printerlar va skanerlar**); Windows'dan
  "sinov sahifasi" chiqadi.
- [ ] Kassa ilovasi: **Sozlamalar** → **Printerni qidirish**. Birinchi kartada **Windows printerlari**: o'rnatilgan printerlar,
  chek printeri yuqorida, "Microsoft Print to PDF", "OneNote", faks — oxirida "printer emasdir" belgisi bilan. <!-- TEKSHIRILMAGAN -->
- [ ] Printer yo'q bo'lsa — "Windows'da printer o'rnatilmagan" matni; **Chop etish navbati** (Print Spooler) xizmati to'xtatilgan
  bo'lsa (`services.msc` → Print Spooler → To'xtatish) — "Print Spooler xizmati ishlayaptimi?" ogohlantirishi, ilova qotmaydi.
  Xizmatni qayta yoqing.
- [ ] Printerni tanlang → **Chek** → **Sinov cheki**: sahifa chiqdi (do'kon nomi, printer nomi, kod sahifasi qatori, QR, kesish).
  O'zbek harflari to'g'ri (aks holda **Matnni rasm sifatida**).
- [ ] **Pul qutisini ochish**: printerga ulangan quti ochildi.
- [ ] Sinov oynasidagi yozuvga qarab panelda printer qo'shing: **Ulanish: USB**, **Kassa terminali** = shu kompyuter, **Nomi** —
  Windows'dagi nomi bilan **aynan bir xil** (masalan `XP-80C`). Nom boshqacha bo'lsa chek chiqmaydi: "Windows printeri topilmadi".
- [ ] Haqiqiy sotuv va schyot chop etish. 20 ta ketma-ket chek: hammasi chiqdi, tartib to'g'ri.
- [ ] Printer kabelini uzing → chek yuboring: "Chop etilmadi" banneri (yoki chek Windows navbatida turadi — qaysi biri bo'lganini
  yozing); ulang → **Qayta urinish** → chiqdi. Ilova qayta ishga tushirilmadi.
- [ ] Windows'ni qayta yuklang: kassa ilovasi o'zi ochiladi (yorliq **Ishga tushirish** papkasida: `Win+R` → `shell:startup`) va
  printer ishlaydi.

### 5.2. Ikkinchi monitor: mijoz ekrani

Mijoz ekrani — alohida ilova (`rizqim_customer_display.exe`), xuddi shu kompyuterda ishlaydi va kassaga **lokal tarmoq orqali
`127.0.0.1` da** ulanadi (Windows xavfsizlik devori o'z kompyuteri ichidagi ulanishni to'smaydi). Ikkinchi monitor bo'lsa ilova o'zi
o'sha monitorda **to'liq ekran** (ramkasiz) ochiladi va kassa oynasidan fokusni olmaydi. <!-- TEKSHIRILMAGAN -->

- [ ] Ikkinchi monitorni ulang; Windows'da **Displey → Kengaytirish** ("Extend", "Takrorlash" emas).
- [ ] Mijoz ekrani ilovasini oching: u ikkinchi monitorda to'liq ekran ochildi, kassa ilovasi asosiy monitorda qoldi va
  klaviatura (skaner) hali kassada (kassada skanerlang — kod kassaga keldi).
- [ ] Mijoz ekranini birinchi marta ulang (panel → Qurilmalar → ulash kodi, turi **Mijoz ekrani**) — sichqoncha bilan ekranni bosib
  kodni kiriting.
- [ ] Monitorlar 3 ta bo'lsa yoki boshqa monitor kerak bo'lsa: yorliq xususiyatlarida **Obyekt** oxiriga `--monitor=2` (chapdan
  hisoblaganda 2-monitor) qo'shing. `--windowed` — oddiy oyna (sinov uchun), `--fullscreen` — bitta monitor bo'lsa ham to'liq ekran
  (faqat mijoz ekrani bo'lgan alohida kompyuter yoki televizor).
- [ ] Ikkinchi monitor kabelini sug'urib oling: mijoz ekrani asosiy monitorda oddiy oynaga qaytdi (yo'qolib qolmadi); qayta ulang —
  yana ikkinchi monitorda to'liq ekran.
- [ ] Kassada taom qo'shing: mijoz ekranida 1 soniyadan kam kechikish bilan chiqdi; to'lovdan keyin "Rahmat".
- [ ] Internetni uzing: mijoz ekrani kassadan (`127.0.0.1`) ma'lumot olishda davom etdi. <!-- TEKSHIRILMAGAN: mijoz ekranining lokal tarmoq mijozi stage B (B1) da yozilmoqda; markazni o'zi topmasa sozlamalarda `127.0.0.1` ni qo'lda kiriting -->
- [ ] Mijoz ekranini ham **Ishga tushirish** papkasiga qo'ying (kassadan keyin ochilsin) va qayta yuklashda tekshiring.

### 5.3. Skaner (Windows)

- [ ] USB skaner "klaviatura (HID)" rejimida (Windows uni klaviatura deb taniydi, drayver kerak emas), kod oxirida Enter.
- [ ] 6-bo'limdagi qadamlarni Windows kassada bajaring. Klaviatura tili (**EN**/**RU**/**UZ**) skaner kodini o'zgartirmasligini
  tekshiring: kirill tartibida (RU) skanerlang — raqamli kod to'g'ri, harfli kod buzilsa yozing. <!-- TEKSHIRILMAGAN -->

### 5.4. Windows kassa — lokal tarmoq markazi va xavfsizlik devori (firewall)

Markaz kassaga boshqa qurilmalar (ofitsiant telefoni, oshxona ekrani) **TCP 18480** porti orqali ulanadi, beacon **UDP 18480** ga
yuboriladi. Windows Defender Firewall buni standart holatda to'sadi. <!-- TEKSHIRILMAGAN -->

- [ ] Kassa ilovasi birinchi marta markaz bo'lganda Windows "Windows Security Alert / Brandmauer" oynasini ko'rsatsa: **Ruxsat berish**
  (Allow access) — administrator paroli bilan. **Bekor qilish** bosilsa Windows ilovani to'sib qo'yadi — keyingi qadam buni tuzatadi.
- [ ] Kassada **Lokal tarmoq: 0 qurilma** belgisini bosing: qurilma yo'q bo'lsa yordam matni chiqadi; qoida yo'q bo'lsa
  "Windows xavfsizlik devori 18480-portni to'sib qo'yishi mumkin" va **Ruxsat berish** tugmasi. Bosing → Windows UAC so'raydi → **Ha**
  → "Ruxsat berildi". (Tugma ilovaning to'suvchi qoidalarini o'chiradi va `Rizqim POS lokal tarmoq` nomli TCP, UDP va dastur
  qoidalarini qo'shadi.)
- [ ] Yoki qo'lda bir marta (administrator PowerShell yoki "Command Prompt (Admin)"):

  ```
  netsh advfirewall firewall add rule name="Rizqim POS lokal tarmoq" dir=in action=allow protocol=TCP localport=18480 profile=any
  netsh advfirewall firewall add rule name="Rizqim POS lokal tarmoq" dir=in action=allow protocol=UDP localport=18480 profile=any
  ```

  Port panelda boshqacha bo'lsa — o'sha raqam. **Windows Defender Firewall → Kengaytirilgan sozlamalar → Kirish qoidalari** da
  `rizqim_cashier` uchun **Bloklash** qoidasi bo'lsa — o'chiring (bloklash qoidasi ruxsatdan ustun).
- [ ] Ofitsiant telefonidan markazga ulaning (7-bo'lim): kassada qurilmalar soni oshdi. Telefonda brauzer bilan
  `http://<kassa IP>:18480/lan/v1/beacon` ochiladi (beacon JSON).
- [ ] Oshxona ekrani yoki mijoz ekrani **boshqa Windows kompyuterda** bo'lsa, o'sha kompyuterda ham UDP qoidasini qo'shing (beacon'ni
  eshitishi uchun); topmasa — sozlamalarda kassa IP'sini qo'lda kiriting.
- [ ] Windows'da kassa bir nechta tarmoqqa ulangan bo'lsa (Wi-Fi va kabel, VPN, VirtualBox adapteri): beacon'dagi IP qurilmalar
  tarmog'idagi IP'mi? Qurilma topmasa — `ipconfig` dagi to'g'ri IP'ni qo'lda kiriting va qaysi adapterlar borligini yozing.
- [ ] Kompyuter uyquga ketmaydi (**Quvvat va uyqu → Hech qachon**), Windows yangilanishi ish vaqtida qayta yuklamaydi (**Faol
  soatlar**) — markaz o'chsa zal lokal rejimda ishlay olmaydi.
- [ ] 8-bo'lim mashqini Windows kassa markaz bo'lganda bir marta bajaring.

## 6. 2D skaner (USB / Bluetooth klaviatura rejimi)

- [ ] Skaner "klaviatura (HID) rejimi"da va kod oxirida **Enter** yuboradi (skaner qo'llanmasidagi sozlash kodlari bilan).
- [ ] Kassa ekranida istalgan joyda skanerlang: kod matn maydonlariga tushmaydi, balki skaner oqimiga boradi. <!-- TEKSHIRILMAGAN: skaner tinglovchisi pos_ui'da yozilmoqda; kassada ulanishi katalogdagi shtrixkodga bog'liq -->
- [ ] Qo'lda tez yozilgan raqamlar skaner deb o'qilmaydi (odatiy yozish tezligi — skaner emas).
- [ ] Qalam bilan yozayotganda (maydon fokusda) skaner kodi maydonga tushmaydi yoki aniq belgilangan joyga tushadi.
- [ ] 1D va 2D (QR) kodlar, qisqa va uzun (13–20 belgi) kodlar o'qiladi. Xira/qiyshiq kod: o'qilmasa xato bermaydi.
- [ ] Ketma-ket 10 ta skan, 1 soniya oraliq: hammasi bitta-bitta.

## 7. Lokal ko'prik: asosiy tayyorgarlik

> Lokal ko'prik (LAN bridge) kodi yozilgan va avtomatik sinalgan (localhost), haqiqiy Wi-Fi va qurilmalarda hali sinalmagan. Bu bo'lim uni **birinchi marta** yoqqanda bajariladi. <!-- TEKSHIRILMAGAN: lokal ko'prik (markaz, beacon, ofitsiant/KDS/mijoz ekrani mijozlari) haqiqiy uskunada sinalmagan -->

- [ ] Asosiy kassa (planshet yoki monoblok) **markaz** deb belgilangan (sozlamalar → **Lokal tarmoq**). Boshqa kassalar markaz emas.
- [ ] Kassada **"Lokal tarmoq: N qurilma"** belgisi bor (internet bor paytda ham ko'rinadi, 0 yoki ulanganlar soni).
- [ ] Ofitsiant telefonlari, oshxona planshet va mijoz ekrani **bitta Wi-Fi'da**. Har biri bir marta internet orqali kirgan (ulanish kaliti va
  vakolat bootstrap bilan keladi).
- [ ] Bir necha soniyada kassada qurilmalar soni ko'paydi (3–5 qurilma). Ulanmasa: sozlamalarda IP yoki QR bilan qo'lda ulang.
- [ ] Filialning ulanish kaliti **yangilanganda** (panelda) qurilmalar yangi kalitni keyingi kirishda oladi; eskisi bilan ulanish rad etiladi va
  ilova buni tushunarli aytadi.
- [ ] Brauzer yoki boshqa qurilma markazga ulanishga urinadi (kalitsiz): rad etiladi.

**Mijoz ilovalari (ofitsiant, KDS, mijoz ekrani)** <!-- TEKSHIRILMAGAN: B1 mijozlari haqiqiy telefon/planshetda sinalmagan -->

- [ ] Internet bor paytda ofitsiant, oshxona va mijoz ekrani markazga **ulanmaydi** (kassada qurilmalar soni 0 bo'lishi to'g'ri: ko'prik
  faqat internet uzilganda ishga tushadi). Belgi yo'q yoki odatdagi ("Jonli").
- [ ] Android telefon (ayniqsa Xiaomi/Samsung, ekran yoqiq, quvvat tejash yoqiq) internet uzilgach markazni **beacon orqali o'zi topadi**
  (qo'lda manzilsiz) — 10 soniyadan kam. Topmasa: qaysi model, Android versiyasi; keyin qo'lda manzil bilan sinang (Wi-Fi multicast qulfi
  ishlamagan bo'lishi mumkin).
- [ ] Qo'lda manzil: ofitsiant — yuqoridagi aloqa belgisini bosing; oshxona — "Lokal tarmoq"/"Internet yo'q" belgisini bosing; mijoz ekrani —
  pastki chap burchakni 2 soniya bosib turing → **Lokal tarmoq**. `192.168.1.20` yoki kassadagi `rizqim-lan://...` matni → **Ulanish**. Ilova
  qayta ochilganda manzil saqlangan.
- [ ] Mijoz ekrani **internetsiz qayta ishga tushirilsa** ham kassaga ulanadi (kaliti qurilmada saqlangan) va kassadagi chekni ko'rsatadi.
- [ ] Oshxona planshetining soatini 5 daqiqa oldinga/orqaga suring: lokal rejimda chiptadagi kutish vaqti baribir to'g'ri (kassa soati bo'yicha).
- [ ] Ofitsiant lokal rejimda **"Schyotni qayta chiqarish"** bosadi: "Internet yo'q: schyotni qayta chiqarish faqat internet bilan (yoki kassaning
  o'zida)" deyiladi (to'g'ri: menejer PIN'i internetsiz tekshirilmaydi). Birinchi schyot ("Schyot so'rash") ishlaydi.
- [ ] Oshxonada lokal rejimda **"Buyurtma tayyor"** (butun buyurtma): oddiy xabar, taomlarni birma-bir "Tayyor" qilish ishlaydi.

## 8. Lokal ko'prik: internet uzildi mashqi (asosiy sinov)

Maqsad: internet uzilsa ham zal ishlashini isbotlash. Bu sinov **20–30 daqiqa**, 2 ofitsiant, 1 kassir, 1 oshpaz kerak. Soat va
daqiqalarni yozib boring.

Shu mashqning avtomatik varianti (haqiqiy server, kassadagi `LanHub`, LAN mijozlari localhost'da, printer emulyatori) —
`e2e/pos/test/internet_outage_lan_test.dart` (`scripts/pos-e2e.sh`). U Wi-Fi, haqiqiy telefonlar va printerni tekshirmaydi:
bu bo'lim shular uchun.

**Boshlanish holati:** hamma qurilma Wi-Fi'da, internet bor, smena ochiq, kassada "Lokal tarmoq: 4 qurilma" (yoki shuncha).

1. [ ] **Ofitsiant 1** bitta buyurtma ochadi va yuboradi (internet hali bor). Oshxona ekranida chipta chiqdi (oddiy rejim).
2. [ ] **Internetni uzing**: routerdan internet (WAN) kabelini oling. Wi-Fi ishlab tursin. Vaqtni yozing.
3. [ ] 30 soniya ichida hamma ilovada holat o'zgardi: ofitsiant va oshxona ilovasida **"Lokal tarmoq"** (yoki shunga o'xshash) belgisi, kassada
   "internetsiz rejim" bildirishnomasi. Ilovalar qotmadi.
4. [ ] **5 ta buyurtma** oling, **ikki ofitsiant telefonida** (taxminan 3 + 2):
   - [ ] har birida stol ochish, 2–4 taom qo'shish, **Yuborish**;
   - [ ] bittasida og'irlik bilan sotiladigan taom (miqdor keyin);
   - [ ] bittasida ikkinchi bosqich (qo'shimcha taom) va bittasida bitta yuborilgan taomni bekor qilish (qoidasiga ko'ra menejer tasdig'i kerak bo'lsa — internetsiz u **berilmaydi**, shuning uchun rad etilishi **to'g'ri**);
   - [ ] bittasida kursli yuborish (restoran formati bo'lsa).
5. [ ] Har buyurtma uchun: **oshxona ekranida chipta 1–2 soniyada chiqdi**; sex printeriga marka chiqdi (printer LAN'da).
6. [ ] Oshxona ekranida taomlarni **Tayyor** qiling (kamida 3 buyurtma). Ofitsiant telefonida taom "tayyor" bo'ldi (rang/belgi), 2 soniyadan kam.
7. [ ] Tez kassa/raqamli rejim bo'lsa: **mijoz ekrani** "Tayyorlanmoqda → Tayyor" raqamlarni ko'rsatdi.
8. [ ] Ofitsiant 1 da **"Schyot so'rash"**: kassada "Schyot so'raldi" belgisi chiqdi; (avtomatik chop etish yoqiq bo'lsa) schyot kassa printerida chiqdi.
9. [ ] **Kassada to'lov** (naqd) — 2 ta buyurtma: to'lov o'tdi, chek chiqdi (`fiscal: offline` — internet qaytgach fiskal chek yuboriladi).
10. [ ] To'lov **telefondan** qilishga urining: ofitsiant ilovasida to'lov **yo'q** (to'g'ri). Kassada yopishdan tashqari hech narsa masofadan bajarilmaydi.
11. [ ] Ofitsiant 2 telefonida Wi-Fi'ni 30 soniyaga o'chirib-yoqing: telefon qayta ulanadi, holat tiklanadi, buyurtmalar yo'qolmagan, takrorlanmagan.
12. [ ] **Kassa ilovasini qayta ishga tushiring** (markaz): ilovalar qayta ulanadi; avvalgi buyurtmalar saqlangan.
13. [ ] **Internetni qaytaring** (kabelni ulang). Vaqtni yozing.
14. [ ] 1–2 daqiqada: kassa "sinxronlanmoqda" → "sinxronlandi"; ofitsiant va oshxona oddiy rejimga qaytdi. Qurilma belgilari "bulut" holatiga qaytdi
    (markaz navbati tugamaguncha qaytmaydi).
15. [ ] **Serverni tekshiring** (panel / admin):
    - [ ] hamma 5 buyurtma serverda bor, **takrori yo'q**;
    - [ ] har buyurtmaning ofitsianti to'g'ri (telefon egasi), qurilmasi to'g'ri (ofitsiant telefoni, kassa emas);
    - [ ] taomlar, miqdorlar, og'irlik, narxlar to'g'ri; jami ofitsiant ko'rgan bilan teng (yoki farq ogohlantirish bilan aytilgan);
    - [ ] to'langan buyurtmalar yopilgan, to'lovlar smenada, fiskal chek holati (yuborildi/kutmoqda) mantiqiy;
    - [ ] **Sinxronlash xatolari** bo'limi bo'sh (yoki har xato tushunarli sabab bilan);
    - [ ] jurnalda (audit) amallar to'g'ri xodim nomi bilan.
16. [ ] Takrorlang, bu safar **kassa (markaz) o'chirilib**, internet ham yo'q ("yolg'iz" holat): ofitsiant telefonlari faqat ko'rishni ko'rsatadi, yangi buyurtma
    ochilmaydi va bu aniq aytiladi (yolg'on "yuborildi" yo'q).

Bu mashqda qayd qiling: ulanish vaqti (internet uzilgandan "lokal" belgisigacha), har xabar kechikishi (taxminiy), nechta xato, qaysi
qadam qiyin bo'ldi.

## 9. Avtomatik schyot chop etish

(Bu imkoniyat tayyor, lekin haqiqiy printerda sinalmagan.) <!-- TEKSHIRILMAGAN: avtomatik schyot chop etish emulyator va soxta printer bilan tekshirilgan -->

- [ ] Panelda **Kassa sozlamalari** → **Schyotni avtomatik chiqarish** yoqiq (standart), **Schyot printeri** = kassa chek printeri.
- [ ] Ofitsiant telefonida buyurtmada **Schyot so'rash** → **So'rash**: **3 soniya ichida** kassa printerida schyot chiqdi.
  Schyotda "Bu fiskal chek emas", taomlar, xizmat haqi, jami to'g'ri. "NUSXA" yozuvi **yo'q**.
- [ ] Faqat **bitta** schyot chiqdi (ikkita kassa bo'lsa — faqat tanlangani). Ilovani qayta ochish yoki Wi-Fi uzib-ulash schyotni qayta chiqarmaydi.
- [ ] Ofitsiantda **Schyotni qayta chiqarish**: menejer PIN so'raydi (ofitsiantda ruxsat yo'q). PIN'dan keyin **nusxa** chiqdi, "NUSXA" yozuvi bor.
  Kassir o'zida (ruxsati bor) PIN'siz.
- [ ] Printer o'chiq: schyot chiqmaydi, kassada "Chop etilmadi" banneri; yoqing → **Qayta urinish** → chiqdi. Ofitsiant ishi to'xtamadi.
- [ ] Sozlamani o'chiring (**Schyotni avtomatik chiqarish** o'chiq): "Schyot so'rash" faqat belgilaydi, schyot chiqmaydi; kassir qo'lda chiqaradi.
- [ ] **Schyot printeri** boshqa printerga (masalan, Bluetooth) o'zgartirilsa — shu printerdan chiqadi.
- [ ] Jurnalda `pos.bill_reprinted` yozuvi: kim so'ragan, kim tasdiqlagan.

## 10. Elektr o'chishi mashqi

Router, printer va kassa uchun UPS'ning to'g'ri ishlashini va ma'lumot yo'qolmasligini tekshiradi.

- [ ] **Kassa (markaz) qurilmasi** ishlayotgan buyurtma bilan: 3 ta ochiq buyurtma, bittasi yarim to'langan (aralash to'lovda) — yo'q bo'lsa 3 ta ochiq buyurtma.
  Quvvatni tortib o'chiring (batareyani/vilkani). **Ilovani to'g'ri yopmang.**
- [ ] Qaytaring: ilova ochiladi, PIN so'raydi, **hamma 3 buyurtma saqlangan**, smena ochiq, takrorlangan buyurtma yo'q.
- [ ] Internetsiz holatda (tarmoq yo'q) shu mashqni takrorlang: navbatdagi (yuborilmagan) amallar qurilmada saqlanib qolgan va internet
  qaytgach serverga ketadi (takror yo'q).
- [ ] **Router o'chdi** (3 daqiqa): ofitsiant/oshxona qurilmalari "aloqa yo'q" deydi, qotmaydi; router qaytgach o'zi ulanadi.
- [ ] **Printer o'chdi** to'lov paytida: to'lov o'tdi, chek navbatda, printer qaytgach **Qayta urinish** bilan chiqdi (yoki o'zi).
- [ ] **Oshxona planshet o'chdi** va qaytdi: chiptalar saqlangan (serverdan/markazdan qayta yuklandi), "tayyor" belgilari yo'qolmagan.
- [ ] **Hamma narsa** bir vaqtda o'chdi va ketma-ket yoqildi (router → printer → kassa → telefonlar): 5 daqiqada ish tiklandi, qo'lda hech narsa
  qilinmadi (faqat PIN).
- [ ] UPS: kassa va router elektr o'chganda necha daqiqa ishladi? (yozing)

## 11. Aralash: kunlik to'liq sinov

- [ ] Smenani oching. 10 ta buyurtma: 5 ta zal (ofitsiant), 3 ta tez kassa, 2 ta onlayn (bo'lsa).
- [ ] Hamma to'lov turi: naqd, karta (terminal bilan RRN), QR, aralash.
- [ ] Qaytarish (menejer PIN'i bilan), chegirma, bitta buyurtmani bekor qilish.
- [ ] X hisobot → Z hisobot: kassa farqi 0 (naqd to'g'ri sanalgan).
- [ ] Hamma cheklar, schyotlar, markalar chiqdi; qog'oz sarfi adekvat.

## 12. Unumdorlik va chidamlilik (qisqa)

- [ ] 30 ta buyurtmani 10 daqiqada oling (ikki ofitsiant): hech narsa qotmadi, oshxona ekrani kechikmadi.
- [ ] Kassa ilovasi 4 soat ochiq: xotira/batareya kutilmagan sarflanmadi, ilova javob beradi.
- [ ] Wi-Fi zaif joyda (devor orqasida) ofitsiant telefoni: ulanish uziladi/qayta ulanadi, buyurtma yo'qolmaydi.

## 13. Xavfsizlik tekshiruvi (lokal ko'prik)

- [ ] Boshqa Wi-Fi qurilma (oddiy telefon brauzerida) `http://<markaz IP>:18480/lan/v1/beacon` ni ochsa: faqat manzil ma'lumoti, buyurtma/xodim ma'lumoti yo'q.
- [ ] Kalitsiz yoki boshqa filial kaliti bilan ulanish rad etiladi.
- [ ] Ofitsiant telefoni ega/menejer web sessiyasida ko'rinmaydigan hech narsa saqlamagan (filial kaliti ega panelida ko'rsatilmaydi).
- [ ] Qurilma bekor qilingach (panelda **Qurilmani o'chirish**): u lokal tarmoqqa ulana olmaydi (keyingi bootstrap/kalit yangilashdan keyin).

## 14. Nimani qayd qilish kerak

Har o'tmagan qadam uchun:

1. **Qadam raqami** (masalan 8.5) va qurilma/model.
2. **Nima kutildi / nima bo'ldi** (bir-ikki jumla).
3. **Surat yoki video** (chek, ekran, xato oynasi).
4. **Jurnal (log) eksporti**: kassa ilovasida **Sozlamalar** → **Jurnalni yuborish / eksport** <!-- TEKSHIRILMAGAN: ilova ichidagi jurnal eksporti tugmasi hozir mavjud emas; kerak bo'lsa qurilmadan `adb logcat` yoki Windows'da ilova jurnali fayli -->
   (yoki Android: `adb logcat -d > log.txt`; Windows: ilova jurnali papkasi). Vaqtni ko'rsating (soat:daqiqa).
5. **Printer ma'lumoti**: model, ulanish turi, qog'oz eni, kod sahifasi, ishlab chiqaruvchi dasturiy ta'minot versiyasi (printer o'z-o'zini
   sinash sahifasidan: tugmani bosib ulang).
6. **Tarmoq**: router modeli, Wi-Fi diapazoni (2.4/5 GHz), qurilmalar soni, AP isolation holati.
7. **Server tomoni**: buyurtma uuid'lari (sinxronlash xatosi bo'lsa) va panelning "Sinxronlash xatolari" bo'limi surati.

Natijalar jadvali:

| Bo'lim | O'tdi | O'tmadi | Izoh / surat / jurnal fayli |
|---|---|---|---|
| 1. Sunmi | | | |
| 2. Bluetooth | | | |
| 3. Android USB | | | |
| 4. LAN printerlar | | | |
| 5. Windows kassa (USB, monitor, skaner, firewall) | | | |
| 6. 2D skaner | | | |
| 7. Lokal ko'prik: tayyorgarlik | | | |
| 8. Internet uzildi mashqi | | | |
| 9. Avtomatik schyot | | | |
| 10. Elektr o'chishi | | | |
| 11–13. Aralash, chidamlilik, xavfsizlik | | | |

Hammasi o'tgach: `docs/guides/uskunalar.md` va boshqa qo'llanmalardagi "TEKSHIRILMAGAN" belgilarini olib tashlang, o'tmagan
imkoniyatlarni qo'llanmada "hozircha qo'llab-quvvatlanmaydi" deb yozing.
