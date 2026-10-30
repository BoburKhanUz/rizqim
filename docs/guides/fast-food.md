# Fast food va qahvaxona: kombo, stiker, qadoq, oldindan buyurtma

Bu qo'llanma fast food, qahvaxona va dark kitchen (faqat yetkazish va olib ketish) uchun: **kombo** (lavash + ichimlik + sous
bitta narxda), kassada **tez tanlov** (hajm bir bosishda), stakanga **stiker** va paketga **yorliq**, olib ketishda **qadoq**,
**oldindan buyurtma** (mijoz olib ketish vaqtini aytadi), oshxonada **sex vaqtlari** va **yig'ish ekrani**.

Kassa, tez kassa va oshxona ekranining umumiy ishi alohida qo'llanmalarda:

- kassir: [kassa.md](kassa.md) va [tez-kassa.md](tez-kassa.md);
- oshpaz: [oshxona-ekrani.md](oshxona-ekrani.md);
- devordagi raqamlar taxtasi: [mijoz-ekrani.md](mijoz-ekrani.md);
- eganing birinchi sozlashi (format, fiskal sozlamalar, printerlar): [ega-pos-sozlash.md](ega-pos-sozlash.md).

> **Muhim.** Bu imkoniyatlar faqat yangi narsalarni sozlashni va yangi buyurtma turlarini ochadi. **To'lov olish, schyot,
> chek, smena, Z va qaytarish hech qachon to'xtamaydi**: modul o'chirilsa ham, sotilgan kombo va chek ishlayveradi.

## Mundarija

1. [Qaysi format nimani yoqadi](#1-qaysi-format-nimani-yoqadi)
2. [Kombo: panelda sozlash](#2-kombo-panelda-sozlash)
3. [Chekda kombo qanday ko'rinadi](#3-chekda-kombo-qanday-korinadi)
4. [Kassada kombo](#4-kassada-kombo)
5. [Tez modifikatorlar: hajm bir bosishda](#5-tez-modifikatorlar-hajm-bir-bosishda)
6. [Stiker va yorliq printeri](#6-stiker-va-yorliq-printeri)
7. [Qadoq qoidalari](#7-qadoq-qoidalari)
8. [Oldindan buyurtma: olib ketish vaqti](#8-oldindan-buyurtma-olib-ketish-vaqti)
9. [24/7 ishlaydigan filial](#9-247-ishlaydigan-filial)
10. [Oshxona: sexlar, vaqt maqsadi va yig'ish ekrani](#10-oshxona-sexlar-vaqt-maqsadi-va-yigish-ekrani)
11. [Tayyorlash vaqti hisoboti](#11-tayyorlash-vaqti-hisoboti)
12. [Muammolar va yechimlar](#12-muammolar-va-yechimlar)

## 1. Qaysi format nimani yoqadi

Egasi filial formatini **Kassa sozlamalari** → **Format** da tanlaydi ([ega-pos-sozlash.md](ega-pos-sozlash.md#filial-formati-birinchi-qadam)).
Format kerakli modullarni o'zi yoqadi; xohlasangiz har birini kalit bilan o'chirasiz yoki yoqasiz.

| Modul (panelda nomi) | Nima qiladi | Fast food | Qahvaxona | Dark kitchen |
|---|---|---|---|---|
| **Kombo** | slotli kombo, narx farqi, chekda bitta qator | bor | yo'q | bor |
| **Tez modifikatorlar** | hajm va tanlovlar menyu tugmasi yonida | bor | bor | yo'q |
| **Stakan stikeri va yorliq** | stakanga stiker, paketga yorliq | bor | bor | yo'q |
| **Yig'ish ekrani va sex vaqtlari** | sexlarga vaqt maqsadi, yig'ish ekrani | bor | yo'q | bor |
| **Qadoq** | olib ketishda qadoq qatori o'zi qo'shiladi | bor | bor | bor |
| **Olib ketish vaqti** | mijoz vaqtni tanlaydi | bor | bor | yo'q |
| **24/7 ish** | kechayu kunduz ishlaydigan filial | o'zi yoqilmaydi | o'zi yoqilmaydi | o'zi yoqilmaydi |

Qolgan formatlar (lag'monxona, somsaxona, tamaddixona) faqat **Qadoq** ni oladi. Modullar Start tarifida; **Yig'ish ekrani** va
**Stikerlar** oshxona ekrani (KDS) bilan birga **Asosiy** tarifda. Tarifda yo'q modul yonida **"Asosiy tarifda bor"** yozuvi turadi
([hamkor-panel.md](hamkor-panel.md#tarif-va-tolov-sahifasi)).

Fast food formatida tayyor sexlar ham bor: **Grill**, **Fritur**, **Ichimliklar**, **Yig'ish**; qahvaxonada **Barista**.
**Format** sahifasidagi **Namunani qo'shish** tugmasi ularni qo'shadi.

## 2. Kombo: panelda sozlash

Kombo — menyudagi oddiy mahsulot (o'z narxi, MXIK kodi), lekin mijoz uning tarkibini **slotlarda** tanlaydi: "Asosiy",
"Ichimlik", "Sous". Har tanlovning narx farqi bor: 0 yoki qo'shimcha.

**Kompleksdan farqi.** Kompleks (stolovaya) — o'zgarmas to'plam, tanlov yo'q ([tez-kassa.md](tez-kassa.md#7-kompleks-stolovaya)).
Kombo — tanlovli. Ikkalasi bir-biriga xalaqit bermaydi.

### Komboni yaratish

1. **Menyu** bo'limida kombo nomi bilan oddiy taom qo'shing: masalan **Lavash kombo**, narxi **45 000** (bu — komboning
   asosiy narxi), MXIK kodi bilan ([hamkor-panel.md](hamkor-panel.md#taom-qoshish)). Bitta variant (**Standart**) yetarli.
2. **Menyu** → **Kombo** → **Kombo qo'shish**.
3. **Kombo mahsuloti** — 1-qadamdagi taomni tanlang.
4. **Slot qo'shish**. Har slot uchun:
   - **Slot nomi** ("Asosiy", "Ichimlik", "Sous");
   - **Eng kami** — 0 bo'lsa slot ixtiyoriy, 1 va undan ko'p bo'lsa majburiy;
   - **Eng ko'pi** — mijoz shu slotdan nechta tanlay oladi (sous uchun 2, ichimlik uchun 1);
   - **Xaridor tarkib modifikatorlarini o'zgartira oladi** — yoqilsa, mijoz tanlangan taomning o'z qo'shimchalarini
     (masalan, "piyozsiz") ham tanlaydi; qo'shilgan qo'shimcha narxi komboga qo'shiladi.
5. **Tanlov qo'shish**. Har tanlov uchun:
   - **Taom** — menyudagi taom (va varianti: "Katta", "0,4 l");
   - **Dona** — bitta komboda nechta (odatda 1);
   - **Narx farqi** — 0 yoki qo'shimcha so'm (masalan, tovuqli lavash **+5 000**, Ayran **+2 000**);
   - **Standart** — slotda **bitta** tanlov standart bo'ladi: kassada va Mini App'da u oldindan belgilanib turadi;
   - **Chekdagi matn** — chekda kombo tagida shu yoziladi (masalan, "Ichimlik — 0,4 l Pepsi"); bo'sh qoldirsangiz, Rizqim
     o'zi yozadi;
   - **Oldindan qo'yilgan modifikatorlar** — masalan, lavashga "mol go'shtli".
6. **Saqlash**.

Chegara: kombo ko'pi bilan 8 slot, slotda ko'pi bilan 20 tanlov. Tarkibda boshqa kombo yoki kompleks bo'lmaydi; tarkib — dona bilan
sotiladigan taom. Slot nomi va kamida bitta taomi bo'lmasa, panel saqlamaydi va sababini aytadi.

### Tekshirish: chek ko'rinishi

Kombo qatoridagi **Chek ko'rinishi** tugmasi oyna ochadi: tanlovni o'zgartirib ko'ring — **Mijoz cheki** va **Fiskal chek**
qatorlari server hisobi bilan chiqadi. Saqlashdan oldin buxgalter bilan shu oynani ko'rib chiqing.

### Narx qanday hisoblanadi

**Kombo narxi + tanlangan farqlar yig'indisi.** Lavash kombo (45 000): tovuqli lavash (+5 000) va Ayran (+2 000) tanlansa — **52 000**.
Standart tanlov bilan — 45 000. Tanlov farqi 0 bo'lsa, mijoz kombo narxida oladi.

### Tanlov tugab qolsa

Mijoz yoki kassir **sotilmaydigan tanlovni ko'rmaydi** (stop-listdagi, tugagan yoki filialda o'chirilgan taom). Majburiy slotda
sotiladigan birorta tanlov qolmasa, **kombo butunlay yopiladi** va stop-listda sababi bilan ko'rinadi ("Kombodagi "Ichimlik"
tanlovida hozir mavjud taom yo'q"). Taom qaytgach kombo o'zi ochiladi. Mijoz savatda eskirgan tanlov bilan qolgan bo'lsa, buyurtma
berishda aniq xabar chiqadi va savatni yangilash so'raladi.

## 3. Chekda kombo qanday ko'rinadi

**Mijoz cheki va schyotda** kombo **bitta qator**: nomi, soni va umumiy narxi. Tagida tarkibi **narxsiz**:

```
Lavash kombo
  1 ta lavash — katta, mol go'shtli
  Ichimlik — 0,4 l Pepsi
  Sous — achchiq
  1 x 45 000                    45 000
```

**Fiskal chekda** ham komboning **bitta qatori** — komboning o'z MXIK kodi bilan. Bu standart rejim (**Bitta qator**).

Buxgalter tarkib bo'yicha qator so'rasa, **Menyu** → **Kombo** sahifasining tepasidagi **Kombo fiskal chekda** ro'yxatidan
**Tarkib bo'yicha** ni tanlang (faqat ega; o'zgarishi jurnalga yoziladi). Shunda fiskal chekda har tarkib o'z MXIK kodi bilan
alohida qator bo'ladi, narxlar esa tarkibning menyu narxlari nisbatida bo'linadi. Yig'indi **har doim komboning narxiga tiyingacha
teng**; bo'linmay qolgan tiyinlar eng katta ulushli qatorlarga bittadan beriladi. Mijoz cheki esa avvalgidek bitta qator bo'lib qoladi.

Qaytarishda kombo **bir butun** qaytariladi (tarkibning bir qismini alohida qaytarib bo'lmaydi). Oshxona ekranida har tarkib o'z
sexida kombo nomi bilan chiqadi ([10-bo'lim](#10-oshxona-sexlar-vaqt-maqsadi-va-yigish-ekrani)).

## 4. Kassada kombo

<!-- TEKSHIRILMAGAN: kassa ilovasidagi kombo oynasi hozir tayyorlanmoqda (stage B, M1). Pastdagi qadamlar spetsifikatsiya va hozirgi
oyna matnlariga qarab yozilgan; tugmalar nomi va bosishlar soni ilova tayyor bo'lgach tekshirilsin. -->

1. Menyuda **kombo tugmasini** bosing (u oddiy taom kabi ko'rinadi, narxi — komboning asosiy narxi).
2. **Slotlar oynasi** ochiladi. Har slotda tanlovlar, ularning yonida narx farqi (**+5 000**) ko'rinadi; sotilmaydigan
   tanlov ko'rinmaydi. **Standart tanlovlar oldindan belgilangan.**
3. Mijoz standart kombo aytsa — darhol **Qo'shish** ni bosing (jami summa tugmada yozilgan): ikki bosishda kombo savatda.
   Boshqa tanlov kerak bo'lsa, slotda uni bosing (bitta tanlovli slotda yangi tanlov eskisini almashtiradi). Majburiy slot
   tanlovsiz qolsa, tugma uni aytadi.
4. Savatda kombo **bitta qator**: tagida tarkibi turadi. Narxda tanlov farqlari ham hisoblangan.
5. Qatorni bosib tanlovni **o'zgartirish** mumkin; oshxonaga yuborilgan kombo bir butun olib tashlanadi (tarkibni alohida
   o'chirib bo'lmaydi).

**Kombo bir xil bo'lsa ham birlashmaydi:** ikki marta bir xil kombo — ikki qator.

**Internetsiz.** Kassa komboni o'zi hisoblaydi (server bilan bir xil qoida) va buyurtmani navbatga qo'yadi; internet qaytgach server
tanlovni saqlaydi va narxni qayta tekshiradi. Farq chiqsa, buyurtma belgilanadi va ega ko'radi ([kassa.md](kassa.md#20-internet-uzilsa)).

## 5. Tez modifikatorlar: hajm bir bosishda

Qahvaxona va fast foodda "Hajm" (0,3 / 0,5 / 0,7 l), "Sut", "Sous" kabi tanlovni har safar oyna ochib tanlash vaqt oladi.
**Tez modifikatorlar** bu tanlovni menyu tugmasi yonida ko'rsatadi.

### Egasi: sozlash

**Menyu** → **Modifikatorlar** → guruhni oching → **Kassada tez tanlash** bo'limi (modul yoqilgan filiallarda ishlaydi):

- **Variantlar qayerda ko'rinadi:**
  - **Oyna orqali (avvalgidek)** — hech narsa o'zgarmaydi;
  - **Menyu tugmasi yonida** — variantlar kichik tugmalar bo'lib tugma yonida turadi;
  - **Hajm tanlash (bir bosish)** — bir marta bosib hajm tanlanadi (faqat bitta variant olinadigan, ya'ni **Eng ko'p tanlov = 1**
    guruhlarda).
- Variantda **Standart (oldindan belgilanadi)** — kassada shu variant oldindan tanlangan turadi.

Bu faqat qulaylik: buyurtmaga **tanlangan variantlar baribir aniq yoziladi** (standart ham), shuning uchun chek, oshxona markasi va
hisobotlar avvalgidek ishlaydi.

### Kassir: ishlash

<!-- TEKSHIRILMAGAN: kassadagi tez tanlov ko'rinishi (tugma yonidagi chiplar, hajm tanlagich) hozir tayyorlanmoqda (stage B, M1). -->

Hajm tanlagichi bor taomda standart hajm tanlangan turadi. Mijoz boshqa hajm aytsa — uning tugmasini bosing, narx o'zi o'zgaradi.
Oyna orqali tanlanadigan guruhlar avvalgidek oyna ochadi.

## 6. Stiker va yorliq printeri

**Stiker** — ichimlik stakaniga yopishtiriladigan kichik yorliq: buyurtma raqami, ichimlik nomi, hajmi, modifikatorlari va vaqt.
Kombo ichidagi ichimlikka ham stiker chiqadi (u yerda kombo nomi ham yoziladi). **Paket yorlig'i** — olib ketish paketiga:
buyurtma raqami, mahsulotlar soni, mijoz ismi va olib ketish vaqti.

### Printer (bir marta)

Stiker va yorliq **yorliq printerida** chop etiladi. Bu odatdagi chek printeri emas: yorliq rulonli printer (Xprinter, Gprinter va
shu kabilar), u **TSPL** tilida ishlaydi. Kassa yorliq vazifasidagi printerga stikerni o'zi shu tilda yuboradi — alohida til
sozlamasi kerak emas.

1. **Kassa sozlamalari** → **Printerlar** → printer qo'shing ([ega-pos-sozlash.md](ega-pos-sozlash.md#printerlar)).
2. **Vazifasi**: **Yorliq**. **Ulanish**: odatda **LAN** (IP manzil va port 9100) yoki USB.
3. Rulon o'lchami printerga qo'yilgan rulonga mos bo'lishi kerak (keyingi qadam).
4. Kassada **Sozlamalar** → **Printerlar** da **Sinov chop etish** bilan tekshiring ([kassa.md](kassa.md#19-printer-sozlamalari)).

<!-- TEKSHIRILMAGAN: haqiqiy TSPL printerda (Xprinter, Gprinter) chop etish sinab ko'rilmagan: kod emulyator bilan tekshirilgan.
Birinchi ulashda sinov stikerini chop etib, o'lcham va markazlashtirishni ko'ring. -->

### Stiker sozlamalari

**Kassa sozlamalari** → **Stiker va yorliq** (modul yoqilgan bo'lsa):

| Sozlama | Nima |
|---|---|
| **Rulon o'lchami** | **40 × 30 mm** yoki **58 × 40 mm** — printerdagi rulonga mos tanlang |
| **Stiker chiqadigan taomlar** | **Hamma taomlarga** yoki **Faqat tanlangan kategoriyalarga** (odatda ichimliklar kategoriyasi; kamida bittasini belgilang) |
| **Har dona uchun alohida stiker** | Qatorda 3 ta stakan bo'lsa 3 ta stiker (2/3 kabi raqam bilan). O'chiq bo'lsa — qatorga bitta |
| **Olib ketish paketiga yorliq** | Olib ketish va Telegram'dan olib ketish buyurtmasiga paket yorlig'i chiqadi |

**Stiker ko'rinishi** tugmasi sozlamaga qarab namunani ko'rsatadi. Sozlama har filial uchun alohida.

### Kassada

<!-- TEKSHIRILMAGAN: stikerlarni kassa ilovasidan chop etish oqimi (to'lovdan keyin avtomatik, qayta chop etish tugmasi, xato
banneri matnlari) hozir tayyorlanmoqda (stage B, M1). -->

- Avval-to'lov (tez kassa) rejimida stikerlar **to'lovdan keyin**, boshqa rejimda **oshxonaga yuborilganda** chiqadi.
- Stiker chiqmasa, **to'lov baribir o'tadi**: ish chop etish navbatiga tushadi, qayta uriniladi va kassada oddiy ogohlantirish
  chiqadi (**Qayta yuborish** — [kassa.md](kassa.md#19-printer-sozlamalari)). Yorliq printeri yo'q yoki sozlama o'chiq bo'lsa, stiker
  chiqmaydi va hech narsa so'ramaydi.
- Buyurtmadan stikerni **qayta chop etish** mumkin.

## 7. Qadoq qoidalari

Olib ketish va yetkazishda qadoq (paket, idish, stakan qopqog'i) alohida qator bo'lib **o'zi qo'shiladi**: schyot va chekda o'z
nomi, narxi va MXIK kodi bilan. Zal buyurtmasiga hech qachon qo'shilmaydi.

### Egasi: qoida qo'shish

**Kassa sozlamalari** → **Qadoq qoidalari** → **Qoida qo'shish** (har filial uchun alohida):

- **Qadoq nomi** ("Paket", "Stakan qopqog'i");
- **Qanday hisoblanadi**:
  - **Har buyurtmaga** — buyurtmaga **Soni** dona (har olib ketishga bitta paket);
  - **Har porsiyaga** — tanlangan **kategoriya** yoki **taom**ning har porsiyasiga **Soni** dona (har ichimlikka bitta qopqoq);
- **Soni** (1 dan 20 gacha), **Narxi (bir dona)**;
- **MXIK** (17 raqam). Bo'sh qoldirsangiz, fiskal sozlamalardagi **Qadoq** kodi olinadi; **Qadoq kodi** va **O'lchov birligi**;
- **Qaysi buyurtmalarda**: **Kassada olib ketish**, **Telegram: olib ketish**, **Yetkazib berish**.

Misol: "Paket" — har buyurtmaga, 1 000 so'm, kassada olib ketish va Telegram olib ketish. "Qopqoq" — har porsiyaga, ichimliklar
kategoriyasi, 300 so'm, hamma turlarda.

Qadoq **chegirmadan keyin** qo'shiladi va chegirma olmaydi. Bo'lingan schyotda qadoq ulushi ham bo'linadi; chek yig'indisi har doim
schyotga teng. Qadoq **Z hisobotda alohida satr** bo'lib ham chiqadi, shuning uchun tushumni qadoqsiz ham ko'rish mumkin.

### Kassir: mijoz o'z paketini olib kelsa

Qadoq qatori buyurtmada ko'rinadi. Mijoz o'z paketini olib kelsa, qatorni **Olib tashlash** bilan **sabab yozib** olib tashlang
(menejer PIN'i shart emas; harakat jurnalga yoziladi). Xato bo'lsa **Qaytarish** bilan qatorni qaytaring. Schyoti allaqachon
to'lanayotgan buyurtmada qadoqni o'zgartirib bo'lmaydi.

<!-- TEKSHIRILMAGAN: kassa ilovasidagi qadoq qatorlari, "Olib tashlash" / "Qaytarish" tugmalari va sabab oynasi hozir
tayyorlanmoqda (stage B, M1). -->

**Internetsiz** qadoq qatorlari ko'rinadi va narxga kiradi, lekin olib tashlash o'chiq turadi (oddiy xabar bilan): buni faqat
internet bor paytda qilish mumkin.

Telegram buyurtmasida qadoq buyurtma kartasida va onlayn to'lov chekida alohida qator bo'lib chiqadi.

## 8. Oldindan buyurtma: olib ketish vaqti

Mijoz olib ketish buyurtmasiga **vaqt** aytishi mumkin: "18:30 da olaman". Buyurtma shu vaqtdan **tayyorlash vaqti oldin**
oshxonaga o'zi chiqadi, shuning uchun taom issiq tayyor bo'ladi.

### Egasi: sozlash

**Kassa sozlamalari** → **Oldindan buyurtma**:

| Sozlama | Nima |
|---|---|
| **Eng kamida (daqiqa oldin)** | Mijoz hozirdan kamida shuncha keyingi vaqtni tanlaydi (standart 20) |
| **Vaqt qadami (daqiqa)** | Vaqtlar shu qadam bilan taklif qilinadi (standart 15) |
| **Necha kun oldin** | 0 — faqat bugun (biznes kun), 7 gacha |
| **Tayyorlash vaqti (daqiqa)** | Oshxona buyurtmani olib ketish vaqtidan shuncha oldin oladi; taomning o'z kutish vaqti kattaroq bo'lsa, o'sha olinadi (standart 15) |

Vaqtlar filialning **ish vaqti** ichida taklif qilinadi; 24/7 filialda — kechayu kunduz.

### Mijoz: Mini App'da

1. Savatda **Olib ketish** ni tanlang (filial ko'rinadi).
2. **Olib ketish vaqti** ro'yxatidan vaqtni tanlang: **Hozir (tayyor bo'lishi bilan)** yoki "Bugun, 18:30", "Ertaga, 09:00".
   Ro'yxat filial oldindan buyurtma qabul qilsa ko'rinadi; ostida "Eng erta: 20 daqiqadan keyin" yozuvi turadi.
3. Tanlangan vaqt pastdagi jami summada **Olib ketish vaqti: Bugun, 18:30** bo'lib turadi: buyurtma berishdan oldin tekshiring.
4. **Buyurtmalarim** da buyurtma kartasida **Olib ketish vaqti: Bugun, 18:30** yirik yoziladi.

Vaqtni server yana tekshiradi: juda yaqin, juda uzoq yoki ish vaqtidan tashqari vaqt rad etiladi va sababi yoziladi. Vaqt kelguncha
buyurtma oshxona ekranida va raqamlar taxtasida ko'rinmaydi.

### Kassir: kassada

<!-- TEKSHIRILMAGAN: kassa ilovasidagi "Vaqtga" tanlagichi, rejalashtirilgan buyurtmalar ro'yxati va talondagi vaqt hozir
tayyorlanmoqda (stage B, M1). -->

Olib ketish buyurtmasini ochishda yoki keyinroq **vaqtni tanlash** mumkin (vaqtlar ro'yxati serverdan keladi, shuning uchun faqat
internet bor paytda). Vaqtli buyurtma kassada vaqti bilan ro'yxatda turadi, talonda **Olib ketish: 13:30** yoziladi. Vaqtni oshxonaga
chiqquncha o'zgartirish yoki olib tashlash mumkin (olib tashlansa — buyurtma darhol oshxonaga ketadi); oshxonaga chiqqach — yo'q.
Internetsiz vaqt tanlanmaydi: buyurtma "hozir" bo'ladi.

Avval to'langan (yopilgan) vaqtli buyurtma ham o'z vaqtida oshxonaga chiqadi.

## 9. 24/7 ishlaydigan filial

**24/7 ish** modulini ega **Format** sahifasida o'zi yoqadi (hech bir formatda o'zi yoqilmaydi). Shunda:

- **Biznes kun** filialning kun boshlanishidan (standart 05:00) keyingisigacha. 23:59, 00:00 va 04:59 dagi savdo **oldingi kunniki**,
  05:00 dan yangi kun. Kun boshini **Kassa sozlamalari** → **Kassa sozlamalari** dagi **Biznes kun boshlanishi** da o'zgartirasiz.
- **Smena va Z** smena ochilgan biznes kunga tegishli: 20:00 da ochilib ertalab 08:00 da yopilgan smena — kechagi kunniki.
- **Buyurtma raqami har biznes kun boshida 1 dan** boshlanadi — smena o'rtasida ham. Bir kunda raqam takrorlanmaydi. Kassa
  yangi kun boshlanganda raqam bloklarini o'zi yangilaydi.
- Hisobot biznes kun bo'yicha: **Kassa sozlamalari** → **24/7** yorlig'ida kunlar, buyurtmalar, tushum, qadoq, yarim tundan keyingi
  buyurtmalar va Z raqamlari.
- Oldindan buyurtmada vaqtlar kechayu kunduz taklif qilinadi.

## 10. Oshxona: sexlar, vaqt maqsadi va yig'ish ekrani

### Sexlar

**Kassa sozlamalari** → **Sexlar** da yangi sex turlari bor ([ega-pos-sozlash.md](ega-pos-sozlash.md#sexlar)):
**Grill** (burger, kabob), **Fritur** (fri, naggets), **Ichimliklar**, **Barista** (qahvaxonada kofe), **Yig'ish**.
Taomlarni sexlarga **Yo'naltirish** yorlig'ida biriktirasiz. **Yig'ish** sexi taom pishirmaydi: unga taom biriktirilmaydi, u
buyurtmani yig'adi.

Kombo tarkibi **har biri o'z sexiga** boradi: lavash grillga, fri fritga, ichimlik ichimliklarga.

### Vaqt maqsadi

Modul yoqilgan bo'lsa, sexda **Vaqt maqsadi** maydoni bor (soniyada: 300 — 5 daqiqa; bo'sh — maqsad yo'q). **Sexlar** yorlig'i tagidagi **Taomning o'z vaqti**
bo'limida alohida taomga o'z maqsadini berasiz (u sexnikidan ustun: katta burger grillda uzoqroq pishadi).

Oshxona ekranida maqsadli qatorda taymer: maqsadning taxminan **80 foizi** o'tganda **sariq**, maqsad o'tganda **qizil** va
**Kechikdi +1:15** yozuvi chiqadi. Rang bilan birga ikonka va yozuv ham bor. Maqsadsiz qator kechikmaydi. Vaqt qator oshxonaga
kelgan paytdan sanaladi (Telegram buyurtmasida — qabul qilingandan).

### Oshxona ekrani

[oshxona-ekrani.md](oshxona-ekrani.md#15-fast-food-vaqt-maqsadi-kombo-va-yigish-ekrani) da oshpaz uchun: taymerlar, kombo tarkibi
va yig'ish ekrani.

**Yig'ish ekrani.** Faqat bitta sexda tayyorlanadigan buyurtma avvalgidek: oshpaz "tayyor" bossa, raqam tayyor bo'ladi. Qatorlari
**ikki va undan ko'p sexda** pishadigan buyurtma (lavash grillda, fri fritda, ichimlik ichimliklarda) oxirgi sex tayyor bo'lgach
**yig'ish ekranida** kutadi. Yig'uvchi sumkaga hammasini solgach **Yig'ildi** ni bosadi — shundan keyingina raqam tayyor bo'ladi
(raqamlar taxtasida va mijoz ekranida chiqadi, onlayn mijozga xabar boradi). Ekran buyurtmaning qaysi sexlari tayyorligini ko'rsatadi.

<!-- TEKSHIRILMAGAN: yig'ish ekrani (KDS) hozir tayyorlanmoqda (stage B, M2): "Yig'ildi" tugmasi va sex holati ko'rinishi. -->

Kassirning **Tayyor** tugmasi doim ishlaydi: to'lov, schyot va yopish hech qachon yig'ishni kutmaydi. Yig'ilgandan keyin qator qaytarilsa
yoki yangi qator tayyor bo'lsa, yig'ish bekor bo'ladi.

## 11. Tayyorlash vaqti hisoboti

**Tayyorlash vaqti** bo'limi (hisobot huquqi bor xodimga ko'rinadi): oshxona qanchada tayyorlayapti.

1. Yuqorida **filialni** va **davrni** tanlang (**Birinchi kun** — **Oxirgi kun**, ko'pi bilan 31 kun). Kerak bo'lsa **Sex** ni tanlang.
2. Sahifada: **Taomlar** (qatorlar), **Buyurtmalar** (birinchi taom kelgandan buyurtma tayyor bo'lguncha) va **Yig'ish**
   (oxirgi sex tayyor bo'lgandan yig'ilguncha) uchun **Mediana** va **90%**; **Maqsaddan oshgan** — nechtasi va ulushi.
3. **Sexlar bo'yicha**, **Taomlar bo'yicha** va **Soatlar bo'yicha** jadvallari: tushlik soatida qaysi sex ortda qolayotganini ko'rasiz.

**Mediana** — yarmi shundan tez tayyor bo'lgan vaqt. **90%** — o'ntadan to'qqiztasi shundan tez ("90% buyurtma 9 daqiqada").
Vaqtlar haqiqiy belgilardan olinadi: oshxonaga kelgan vaqt (Telegram'da qabul qilingan vaqt), "tayyor" bosilgan vaqt, "yig'ildi"
bosilgan vaqt. Kunlar Toshkent vaqti bilan, filialning kun boshlanishidan hisoblanadi.

## 12. Muammolar va yechimlar

| Muammo | Nima qilish kerak |
|---|---|
| Panelda **Kombo** bo'limi yo'q | Filialda **Kombo** moduli yoqilmagan (**Format**) yoki sizda menyu huquqi yo'q |
| Kombo mijozga ko'rinmayapti | Majburiy slotda sotiladigan tanlov qolmagan (stop-listda sababi yozilgan) yoki kombo mahsuloti o'chirilgan |
| Kombo saqlanmayapti | Har slotga nom va kamida bitta taom bering; **Eng kami** **Eng ko'pi** dan oshmasin; slotda standart tanlov bittadan ko'p bo'lmasin |
| Savat "tanlov eskirgan" deb rad etdi | Tanlangan taom shu orada tugagan. Savatni yangilab, boshqa tanlov bering |
| Hajm tanlash faqat bitta variantli guruhda chiqadi | **Eng ko'p tanlov** ni 1 qiling |
| Stiker chiqmayapti | Filialda **Yorliq** vazifali printer bormi, **Stiker va yorliq** moduli yoqilganmi, stiker chiqadigan kategoriya belgilanganmi tekshiring; kassada **Printerlar** da **Holatini tekshirish** |
| Stiker kesilib yoki siljib chiqyapti | **Rulon o'lchami** printerdagi rulonga mos emas: 40 × 30 yoki 58 × 40 ni almashtiring |
| Stiker chiqmadi, lekin to'lov o'tdi | Shunday bo'lishi kerak: chop etish to'lovni to'xtatmaydi. Printerni tuzatib **Qayta yuborish** ni bosing |
| Qadoq qo'shilmayapti | **Qadoq** moduli va qoida filialda yoqilganmi; buyurtma turi qoidada belgilanganmi; zal buyurtmasiga qadoq qo'shilmaydi |
| Qadoqni olib tashlab bo'lmayapti | Internetsiz olib tashlanmaydi; yoki schyot to'lanayotgan buyurtma |
| **Olib ketish vaqti** ro'yxati Mini App'da yo'q | Filialda **Olib ketish vaqti** moduli o'chiq yoki bugun taklif qilinadigan vaqt qolmagan (ish vaqti tugagan) |
| "Vaqt juda yaqin" yoki "ish vaqtidan tashqari" | **Eng kamida** daqiqa va filial ish vaqtiga qarang; mijozga boshqa vaqt tanlashni ayting |
| Vaqtli buyurtma oshxonada ko'rinmayapti | Hali vaqti kelmagan: u olib ketish vaqtidan tayyorlash vaqti oldin chiqadi |
| Taymer ko'rinmayapti | Sexda yoki taomda **Vaqt maqsadi** yozilmagan, yoki modul o'chiq |
| Yig'ish ekrani yo'q | **Yig'ish** sexi qo'shilmagan yoki **Yig'ish ekrani va sex vaqtlari** moduli o'chiq |
| Raqam tayyor bo'lmayapti, buyurtma yig'ishda turibdi | Yig'uvchi **Yig'ildi** ni bosmagan. Shoshilinch bo'lsa kassir **Tayyor** ni bosadi |
| Raqam bir kunda ikki marta chiqdi | 24/7 filialda kun boshi (05:00) o'zgargan bo'lishi mumkin; **Biznes kun boshlanishi** ni tekshiring |
