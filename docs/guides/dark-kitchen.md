# Dark kitchen: bir oshxonada bir nechta brend, ingredient bo'yicha stop-list, kanal tushumi

Bu qo'llanma bitta oshxonada bir nechta **brend** bilan ishlaydiganlar uchun (masalan, bitta oshxonadan "Bek Burger", "Osh Markazi" va "Lavash House" sotiladi; buyurtmalar Telegram, mobil ilova, agregator va kassadan keladi). Ega, menejer, kassir va oshpaz uchun.

Asosiy fikr: **oshxona bitta, navbat bitta, brend esa chiptada, yorliqda va hisobotda ko'rinadi.** "Tovuq tugadi" deb bir marta bossangiz, tovuq ishlatiladigan hamma taom (hamma brend va hamma kanalda) birdan yopiladi.

> **Muhim.** Brendsiz va ingredientsiz do'konda hech narsa o'zgarmaydi: menyu, narx, stop-list, oshxona ekrani va chek avvalgidek. Brend va ingredient faqat yangi imkoniyat qo'shadi. **Naqd va karta bilan to'lash, schyot, chek, smena, Z va qaytarish hech qachon to'xtamaydi.** Modul o'chsa ham brend qoidalari, filial o'chirgichi va to'xtatilgan ingredient amalda qoladi; ingredientni qayta ochish modulni so'ramaydi.

Boshqa qo'llanmalar:

- panel: [hamkor-panel.md](hamkor-panel.md) (menyu — [5-bo'lim](hamkor-panel.md#5-menyu), stop-list — [6-bo'lim](hamkor-panel.md#6-stop-list-va-qozon));
- oshxona ekrani: [oshxona-ekrani.md](oshxona-ekrani.md);
- kassa: [kassa.md](kassa.md);
- formatni tanlash va modullar: [ega-pos-sozlash.md](ega-pos-sozlash.md#filial-formati-birinchi-qadam);
- stiker va yorliq printeri: [fast-food.md](fast-food.md), [uskunalar.md](uskunalar.md).

## Mundarija

1. [Qaysi modul nimani yoqadi](#1-qaysi-modul-nimani-yoqadi)
2. [Brendlar: panelda sozlash](#2-brendlar-panelda-sozlash)
3. [Kanal narxi va ko'rinishi](#3-kanal-narxi-va-korinishi)
4. [Brend kassada](#4-brend-kassada)
5. [Brend oshxona ekranida](#5-brend-oshxona-ekranida)
6. [Brend stikerda, paket yorlig'ida va oshxona chiptasida](#6-brend-stikerda-paket-yorligida-va-oshxona-chiptasida)
7. [Ingredientlar va "Tovuq tugadi"](#7-ingredientlar-va-tovuq-tugadi)
8. [Kanal tushumi hisoboti va komissiyalar](#8-kanal-tushumi-hisoboti-va-komissiyalar)
9. [Muammolar va yechimlar](#9-muammolar-va-yechimlar)

## 1. Qaysi modul nimani yoqadi

Egasi filial formatini **Kassa sozlamalari** → **Format** da tanlaydi ([ega-pos-sozlash.md](ega-pos-sozlash.md#filial-formati-birinchi-qadam)). "Dark kitchen" formatida ikkala modul o'zi yoqiladi; boshqa formatda ularni alohida yoqish mumkin.

| Modul (panelda nomi) | Nima qiladi | Tarif |
|---|---|---|
| **Ko'p brend** | brendlar, brend × kanal narxi va ko'rinishi, brend bo'yicha filial o'chirgichi, kanal tushumi hisoboti | Pro |
| **Ingredient bo'yicha stop-list** | ingredient va "Tovuq tugadi" | Pro |

Tarifda yo'q modul yonida **"Asosiy tarifda bor"** kabi yozuv turadi ([hamkor-panel.md](hamkor-panel.md#tarif-va-tolov-sahifasi)).

## 2. Brendlar: panelda sozlash

**Qayerda:** chap menyuda **Brendlar**. Ruxsat: menyuni ko'rish (ko'rish), menyuni boshqarish (o'zgartirish).

### Brend qo'shish

1. **Brend qo'shish** ni bosing.
2. **Nomi** — mijoz ko'radigan nom ("Bek Burger").
3. **Qisqa kod** — 1 dan 8 gacha harf yoki raqam ("BB"). Oshxona chiptasida, stikerda va yorliqda shu ko'rinadi. Kod do'konda takrorlanmaydi; katta harfga aylanadi.
4. **Rangi** — chipta va kassadagi brend belgisi shu rangda bo'ladi. Yozuv rangi (oq yoki qora) o'zi tanlanadi, o'qilishi qiyin bo'lmaydi.
5. **Logotip havolasi** — ixtiyoriy, `https://` bilan.
6. **Saqlash**.

### Taomlarni brendga o'tkazish

Brendlar sahifasidagi **Taomlar va brendlar** kartasida (qidiruv bilan) taomlarni belgilang, **Qaysi brendga** ni tanlang va **O'tkazish** ni bosing (bir martada 500 tagacha). Taom **bitta** brendga tegishli. Brendi yo'q taom **Asosiy brend**da qoladi: u oddiy taom, chiptada brend belgisi chiqmaydi.

### Brendni o'chirish yoki o'chirib qo'yish

- **Sotuvda** kalitini o'chirsangiz, brend taomlari **hamma kanalda** yashirin bo'ladi.
- **O'chirish**: taomlari Asosiy brendga o'tadi. Allaqachon sotilgan buyurtmalardagi brend o'zgarmaydi (qator brendning sotilgan paytdagi nusxasini saqlaydi).

## 3. Kanal narxi va ko'rinishi

Brend kartasidagi **Kanallar va narx** tugmasi (kartada shuningdek **Tahrirlash**, **Filiallar** va **O'chirish**). Jadvalda har kanal uchun ikki narsa:

| Maydon | Ma'nosi |
|---|---|
| **Sotiladi** | O'chirsangiz, brend shu kanalda sotilmaydi (menyuda ko'rinmaydi, buyurtma qabul qilinmaydi). |
| **Narx farqi, %** | Brend taomlari shu kanalda shuncha foiz qimmat yoki arzon (−90 dan +300 gacha). Masalan, agregatorda **+15**: 40 000 so'mlik taom 46 000 bo'ladi. Narx butun so'mga yaxlitlanadi. |

Kanallar: **Telegram**, **Mobil ilova**, **Zal (POS)**, **Olib ketish (POS)**, **Agregator**.

> Taomning **o'z kanal narxi** bo'lsa ([hamkor-panel.md](hamkor-panel.md#kanal-narxlari)), o'sha narx ustun: foiz undan keyin qo'llanmaydi.

**Filiallar** tugmasi: brendni ayrim filialda o'chirib qo'yish. O'chirilgan filialda brend taomlari stop-listdagidek yopiq bo'ladi (hamma kanalda), sababi "<brend> bu filialda o'chirilgan".

Narx va ko'rinish o'zgarsa, menyu versiyasi yangilanadi: kassa va Telegram yangi narxni o'zi oladi.

## 4. Brend kassada

Katalogda brend bo'lsa, kassaning menyu panelida:

- **brend chiplari**: **Hamma brend** va har brend (o'z rangi bilan). Brendni bossangiz faqat shu brend taomlari qoladi; kategoriya filtri bilan birga ishlaydi. Kategoriya chiplari shu qatorning pastida.
- **"Tugadi" belgisi**: ingredient tugagani uchun yopilgan taomning tugmasida sababi chiqadi (masalan, **Tovuq tugadi**), oddiy "Tugadi" o'rniga. Bunday taomni sotib bo'lmaydi.
- **Cheklangan taom belgisi**: taom sotuvda, lekin muhim bo'lmagan ingredienti tugagan bo'lsa tugmada **Piyozsiz** kabi belgi turadi. Taomni sotish mumkin, kassir mijozga ayta oladi.
- **"Ingredient tugadi" tugmasi** (qidiruv yonida, faqat huquqi bor kassirda) — [7-bo'lim](#7-ingredientlar-va-tovuq-tugadi).

Brendsiz do'konda bu chiplar va tugma ko'rinmaydi.

## 5. Brend oshxona ekranida

Chiptada brendli qator bo'lsa, oshxona ekrani ([oshxona-ekrani.md](oshxona-ekrani.md)):

- **chipta sarlavhasida** har brendning qisqa kodi, brend rangida ("BB", "OM");
- **har taom qatorida**, nomdan oldin shu taomning brend kodi;
- bir navbatda hamma brend chiptasi birga turadi: oshpaz brend nomi bilan emas, kod va rangga qarab ajratadi.

Brendsiz chiptada hech narsa o'zgarmaydi.

### "Ingredient tugadi" (oshpaz)

Oshpazda **stop-list huquqi** bo'lsa (`stoplist.manage`: menejer, ega yoki shu huquq berilgan oshpaz) va modul yoqiq bo'lsa, doska tepasida (sarlavha tagida) ingredient lentasi chiqadi:

- chapda: **Hamma ingredient bor** yoki **Tugadi: Tovuq, Piyoz**;
- o'ngda: **Ingredient tugadi** tugmasi.

Tugmani bossangiz ingredientlar ro'yxati ochiladi (har birining tagida nechta taom yopilishi yozilgan):

1. Kerakli ingredient yonida **Tugadi** ni bosing.
2. Ixtiyoriy **sabab** yozing va muddatni tanlang: **Qo'lda ochilguncha**, **1 soat**, **3 soat** yoki **Bugun oxirigacha**. Muddat tugagach ingredient o'zi qayta ochiladi.
3. **Tugadi deb belgilash**.

Qayta ochish: ro'yxatda shu ingredient yonida **Qayta ochish**.

Huquq yoki modul yo'q bo'lsa lenta va tugma umuman ko'rinmaydi. Internet uzilsa ingredientni o'zgartirib bo'lmaydi (oddiy xabar chiqadi); doska esa ishlayveradi.

## 6. Brend stikerda, paket yorlig'ida va oshxona chiptasida

Kassa chop etganda qatorning brend kodi chiqadi (faqat brendli qatorda; brendsiz qator avvalgidek):

| Hujjat | Brend qayerda |
|---|---|
| **Stakan stikeri** | pastki qatorning o'ng chetida, teskari rangda: ` BB `. Chapda mijoz ismi yoki "Olib ketish". |
| **Paket yorlig'i** | "Olib ketish" qatorining o'ngida paketdagi brendlar: `BB OM`. |
| **Oshxona qog'oz chiptasi** (sexga chiqadigan marka) | sarlavha tagida **Brend: BB, OM**, har brendli taom oldida `[BB]`. |

Stiker va yorliq printeri sozlamasi o'zgarmaydi ([fast-food.md](fast-food.md)). 40×30 va 58×40 mm yorliqda kod har doim sig'adi.

## 7. Ingredientlar va "Tovuq tugadi"

### Ingredientni sozlash (panelda)

**Qayerda:** **Stop-list** → **Ingredientlar** yorlig'i.

1. **Ingredient qo'shish**: nomi ("Tovuq") va ixtiyoriy o'lchov birligi (faqat ko'rsatiladi). Nom do'konda takrorlanmaydi.
2. **Taomlar** tugmasi: shu ingredientni ishlatadigan taomlarni belgilang. Har taom yonida **Muhim** belgisi:
   - **Muhim** — ingredient tugasa taom **yopiladi**;
   - muhim emas — taom sotuvda qoladi, kassada faqat **"Tovuqsiz"** kabi belgi chiqadi.
3. Butun kategoriyani bog'lash ham mumkin (**Kategoriyani butunlay bog'lash**): kategoriya va pastki kategoriyalardagi hamma taom qo'shiladi.

Bu **retsept emas**: miqdor, ombor yoki tannarx yo'q. Faqat "bu taomga shu ingredient kerak".

### Ingredientni to'xtatish

Panelda ingredient qatoridagi **To'xtatish** yoki kassa va oshxona ekranidagi **Ingredient tugadi** ([4](#4-brend-kassada) va [5](#5-brend-oshxona-ekranida)-bo'limlar). Natija:

- ingredientga **muhim** bog'langan hamma taom shu **filialda** bir vaqtda yopiladi: **hamma brend va hamma kanalda** (Telegram, mobil ilova, agregator, kassa);
- yopilgan taom yo'q: buyurtmaga qo'shib bo'lmaydi, kassada tugmasi "Tovuq tugadi" deydi, onlayn buyurtma qabul qilinmaydi;
- **sabab** bo'sh qolsa — "Tovuq tugadi" deb yoziladi;
- **qachongacha** berilsa, shu vaqtda o'zi ochiladi.

Ingredient oddiy stop-list yozuvi bo'lib saqlanadi, shuning uchun **Stop-list** sahifasida ham ko'rinadi.

### Qayta ochish

**Qayta ochish**: bog'langan taomlar sotuvga qaytadi. Ammo **taomning o'z stop-list yozuvi** bo'lsa (masalan, taom alohida to'xtatilgan), u yopiq qoladi: ingredient faqat o'zi yopgan taomlarni ochadi.

### Kim to'xtata oladi

`stoplist.manage` huquqi borlar (menejer, ega), faqat **o'z filialida**. Panelda ro'yxatni menyuni ko'ruvchi ham ko'radi, to'xtatish uchun esa stop-list huquqi kerak.

## 8. Kanal tushumi hisoboti va komissiyalar

**Qayerda:** chap menyuda **Kanal tushumi**. Ruxsat: hisobotlarni ko'rish.

Davrni tanlang (bugun, 366 kungacha) va, kerak bo'lsa, filialni. Jadval **Kanal va brend** bo'yicha ustunlar:

| Ustun | Ma'nosi |
|---|---|
| **Buyurtmalar** | shu kanal va brenddagi buyurtmalar soni (bir buyurtma har brendda bir marta sanaladi) |
| **Yalpi tushum** | buyurtma summasi + chegirma (yetkazish, xizmat va qadoq qatorlari bilan) |
| **Chegirmalar** | berilgan chegirma |
| **Qaytarishlar** | kassadan qaytarilgan pul |
| **Komissiya** | kanal komissiyasi (pastda) |
| **Sof tushum** | yalpi − chegirma − qaytarish − komissiya |

Jadval tagida **Kanallar bo'yicha**, **Brendlar bo'yicha** va **Jami** qatorlari bor. **Excel yuklab olish** uchta varaq beradi: "Kanal va brend", "Kanallar", "Brendlar".

Qoidalar:

- hisobga **yetkazilgan** va keyin **qaytarilgan** buyurtmalar kiradi, joylashtirilgan kuni bo'yicha (do'kon vaqti);
- bir nechta brendli buyurtma brendlar qator summasi ulushida bo'linadi, brend qatorlari buyurtmaga aynan teng bo'ladi.

### Komissiyalar

**Kanal tushumi** sahifasi tepasidagi **Komissiyalar** tugmasi: har kanal uchun foiz (0 dan 100 gacha) va izoh. Agregator (Yandex, Uzum) o'z komissiyasini o'zi oladi: shu foizni kiriting. Kanal bo'sh qolsa, buyurtmaning RIZQIM komissiyasi hisoblanadi ([hamkor-panel.md](hamkor-panel.md#11-hisobotlar-va-csv)). Komissiya = foiz × buyurtmaning to'lanadigan summasi.

**Misol.** Agregatorda bitta buyurtma: yalpi 1 000 000, chegirma 50 000, to'lanadigan summa 950 000. Qaytarish yo'q. Komissiya 15%: 950 000 × 0,15 = 142 500. Sof tushum = 1 000 000 − 50 000 − 0 − 142 500 = **807 500** so'm.

> **Buxgalter uchun.** Bu boshqaruv hisoboti: soliq hisobi emas. Fiskal chek va soliq hisobi avvalgidek schyot summasi bo'yicha. Agregator bilan hisob-kitobni (komissiya foizi, to'lov muddati) kanal shartnomasi bo'yicha tekshiring.

## 9. Muammolar va yechimlar

| Muammo | Sabab va yechim |
|---|---|
| Panelda **Brendlar** yo'q | Modul yoqilmagan yoki tarifda yo'q. **Kassa sozlamalari** → **Format**da "Ko'p brend" ni tekshiring. |
| Kassada brend chiplari yo'q | Menyuda brend yo'q (taomlar Asosiy brendda) yoki brend shu kanalda o'chirilgan. |
| Brend taomlari Telegramda ko'rinmaydi | Brend kartasida **Kanallar va narx** da Telegram "Sotiladi" o'chiq yoki brend shu filialda o'chirilgan. |
| Oshxona ekranida **Ingredient tugadi** tugmasi yo'q | Oshpazda stop-list huquqi yo'q, modul o'chiq yoki eski server. Menejerdan huquq so'rang. |
| "Tovuq tugadi" bosildi, lekin bitta taom yopilmadi | Ingredientga bu taom **muhim** deb bog'lanmagan (faqat belgilangan). **Taomlar** tugmasida **Muhim** ni yoqing. |
| Ingredientni qayta ochdim, taom hamon yopiq | Taomning o'z stop-list yozuvi bor: **Stop-list** sahifasida uni alohida oching. |
| Stikerda brend kodi yo'q | Qator brendsiz (Asosiy brend) yoki eski kassa versiyasi. |
| Kanal tushumida komissiya 0 | Kanal uchun foiz kiritilmagan va buyurtmada RIZQIM komissiyasi yo'q. **Komissiyalar** da foizni kiriting. |
