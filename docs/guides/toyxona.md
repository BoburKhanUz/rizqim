# To'yxona va banket: zalni band qilish, zaklad, ishlab chiqarish varag'i, yakuniy hisob

Bu qo'llanma to'yxona, banket zali va katta tadbirlar (to'y, osh, tug'ilgan kun, korporativ) bilan ishlaydiganlar uchun. Ega, menejer va kassir uchun.

Tadbir shunday yuradi: **zalni band qilish → shartnoma va narxlarni muzlatish → zaklad → oraliq to'lovlar → ishlab chiqarish varag'i → tadbir kuni → yakuniy hisob → qoldiqni to'lash.** Pul qismi (zaklad, oraliq to'lov, yakuniy chek, bekor qilish) kassada, sozlash va hisobotlar hamkor panelida.

> **Muhim.** Mavjud tadbirning pul qismi hech qachon to'xtamaydi: modul o'chsa ham zaklad olish, yakuniy hisob, bekor qilish va qaytarish ishlayveradi. Modul faqat **yangi** tadbir va yangi sozlashni yopadi. Naqd va karta bilan to'lash, schyot, chek, smena va Z avvalgidek.
>
> **Chek summasi = schyot summasi.** Zakladlar alohida "bo'nak" cheki bilan chiqadi, yakuniy sotuv cheki esa tadbirning **to'liq summasiga** BITTA chek bo'ladi va zakladlar unda oldindan to'lov sifatida ayiriladi ([9-bo'lim](#9-yakuniy-hisob)).

Boshqa qo'llanmalar:

- kassir: [kassa.md](kassa.md), to'lov turlari — [kassa.md](kassa.md#12-tolov-turlari);
- panel: [hamkor-panel.md](hamkor-panel.md); filial formati va modullar: [ega-pos-sozlash.md](ega-pos-sozlash.md#filial-formati-birinchi-qadam);
- bron va avans (xona bronlari): [ega-pos-sozlash.md](ega-pos-sozlash.md#12-bron-va-avans);
- printer: [uskunalar.md](uskunalar.md).

## Mundarija

1. [Qaysi modul nimani yoqadi](#1-qaysi-modul-nimani-yoqadi)
2. [Sozlash: zallar, vaqtlar, paketlar, qo'shimchalar, ijara](#2-sozlash-zallar-vaqtlar-paketlar-qoshimchalar-ijara)
3. [To'lov shabloni va bekor qilish qoidalari](#3-tolov-shabloni-va-bekor-qilish-qoidalari)
4. [Shartnoma shabloni va ishlab chiqarish sozlamalari](#4-shartnoma-shabloni-va-ishlab-chiqarish-sozlamalari)
5. [Taqvim va zalni band qilish](#5-taqvim-va-zalni-band-qilish)
6. [Narxlarni muzlatish va shartnoma](#6-narxlarni-muzlatish-va-shartnoma)
7. [Zaklad va oraliq to'lovlar, eslatmalar](#7-zaklad-va-oraliq-tolovlar-eslatmalar)
8. [Ishlab chiqarish varag'i, osh kalkulyatori, bozorlik ro'yxati](#8-ishlab-chiqarish-varagi-osh-kalkulyatori-bozorlik-royxati)
9. [Yakuniy hisob](#9-yakuniy-hisob)
10. [Tadbir kuni](#10-tadbir-kuni)
11. [Tadbirni bekor qilish](#11-tadbirni-bekor-qilish)
12. [Hisobot, X va Z](#12-hisobot-x-va-z)
13. [Muammolar va yechimlar](#13-muammolar-va-yechimlar)

## 1. Qaysi modul nimani yoqadi

Egasi filial formatini **Kassa sozlamalari** → **Format** da tanlaydi. **To'yxona, banket** formatida **Banket va tadbirlar** moduli o'zi yoqiladi; boshqa formatda uni alohida yoqish mumkin. Modul Pro tarifda; tarifda yo'q bo'lsa yonida "Asosiy tarifda bor" kabi yozuv turadi ([hamkor-panel.md](hamkor-panel.md#tarif-va-tolov-sahifasi)).

Kim nima qiladi (ruxsat `banquets.manage`: menejer va ega):

| Ish | Qayerda | Ruxsat |
|---|---|---|
| Sozlash (paket, narx, qoidalar) | panel | `banquets.manage`, faqat panel (kassada yo'q) |
| Taqvimni ko'rish, zalni band qilish, o'zgartirish, muzlatish | panel va kassa | `banquets.manage` |
| Zaklad va oraliq to'lovni olish | kassa | `pos.take_payment` (kassir ham) |
| Bank o'tkazmasini yozish | panel | `banquets.manage` |
| Yakuniy hisob va qoldiq to'lovi | kassa | `pos.take_payment` |
| Ishlab chiqarish varag'ini ko'rish | panel va kassa | menejer, kassir, oshxona (`kitchen.manage`) |
| Shartnoma (ism va telefon bor) | panel va kassa | faqat `banquets.manage` |

## 2. Sozlash: zallar, vaqtlar, paketlar, qo'shimchalar, ijara

**Qayerda:** chap menyuda **Tadbirlar** → yuqoridagi **Paketlar va narxlar**. Sahifada to'rtta yorliq: **Paketlar**, **Qo'shimchalar**, **Zal ijarasi**, **Sozlamalar**.

### Zallar

Zallar **Zal va stollar** bo'limida qo'shiladi ([ega-pos-sozlash.md](ega-pos-sozlash.md#2-zal-xaritasi)). Tadbir zalni band qiladi; bir nechta zalni bitta tadbirga **birlashtirish** mumkin (taqvimda tadbir har zal ostida ko'rinadi).

### Vaqtlar (slotlar)

**Sozlamalar** yorlig'ida:

- **Vaqtlar**: **Ertalab**, **Kunduzi**, **Kechqurun** ning boshlanishi va tugashi (soat:daqiqa). Tadbir shulardan birini yoki **Boshqa vaqt** ni (o'z boshlanishi va tugashi; tugash boshlanishdan oldin bo'lsa, tadbir **ertasi kuni** tugaydi) tanlaydi.
- **Zaklad kutish (soat)**: zaklad olinmasa band qilingan zal shuncha soatdan keyin o'zi bo'shaydi; 0 — o'zi bo'shamaydi.
- **Mehmonlar ogohlantirishi**: masalan 200, 250, 300. Mehmon soni chegaradan oshsa tadbir kartasida ogohlantirish chiqadi. **Bu rad etish emas**, faqat eslatma.
- **Vaqt oynasi**: tadbir shu soatlardan tashqarida bo'lsa ogohlantirish chiqadi (rad etilmaydi).
- **Bo'nak cheki MXIK** va **"Bekor qilish haqi" MXIK**: 17 raqam, buxgalter tasdiqlaydi.

### Paketlar

**Paketlar** → **Paket qo'shish**:

- **Paket nomi**, **Har mehmonga narx**, **Minimal mehmonlar**;
- **Tarkibi**: har qatorda bitta band (Osh, Salatlar, Non) — mijozga shartnomada ko'rinadi;
- **Menyu taomlari (ishlab chiqarish varag'i uchun)**: paketga kiradigan menyu taomlari va **har mehmonga miqdori** (porsiya, dona, kg yoki litr). Ishlab chiqarish varag'i shundan hisoblanadi ([8-bo'lim](#8-ishlab-chiqarish-varagi-osh-kalkulyatori-bozorlik-royxati)). Taomni "Osh" kabi nomi bilan yoki menyudagi taomni tanlab qo'shasiz.

Tadbirda ishlatilgan paket **o'chirilmaydi**, **arxivga** olinadi (eski tadbirlar buzilmasin).

### Qo'shimchalar

**Qo'shimchalar** → **Qo'shimcha qo'shish**: nomi (qo'shimcha soat, tort, dekor), narxi va **Hisoblash**: **Tadbirga** (bir marta) yoki **Har mehmonga**. Ishlatilgani ham arxivga olinadi. Tadbirga ro'yxatda yo'q qo'shimchani ham (nomi, narxi, miqdori bilan) qo'lda qo'shish mumkin.

### Zal ijarasi

**Zal ijarasi** yorlig'ida har zal uchun har vaqt bo'yicha ijara narxi (Ertalab, Kunduzi, Kechqurun, Boshqa vaqt). Bo'sh qoldirsangiz, o'sha vaqtda ijara yo'q.

Tadbirning summasi: **paket × mehmonlar + zal ijarasi + qo'shimchalar**.

## 3. To'lov shabloni va bekor qilish qoidalari

**Qayerda:** **Tadbirlar hisoboti** sahifasidagi **To'lov va bekor qilish qoidalari** kartasi.

### Jadval shabloni

Yangi tadbirning to'lov jadvali shu shablondan o'zi tuziladi. Shablon — 1 dan 8 gacha qator; har qatorda **turi** (zaklad, oraliq to'lov, yakuniy to'lov), **foiz** (yoki **qolgani**) va **necha kun oldin** (yoki **band qilishda**). Qoidalar: oxirgi qator — yakuniy to'lov (**qolgani**); zaklad ko'pi bilan bitta; foizlar jami 100 dan oshmaydi.

**Misol: 30 / 40 / qolgani** (standart shablon shunday: zaklad band qilganda, oraliq to'lov tadbirdan 14 kun oldin, qolgani tadbir kuni). Tadbir summasi 36 000 000 so'm bo'lsa:

| To'lov | Foiz | Summa | Sana |
|---|---|---|---|
| Zaklad | 30% | 10 800 000 | band qilganda |
| Oraliq to'lov | 40% | 14 400 000 | tadbirdan 14 kun oldin |
| Yakuniy to'lov | qolgani | 10 800 000 | tadbir kuni |

Summa o'zgarsa (mehmon qo'shildi), faqat **to'lanmagan** qatorlar qayta hisoblanadi; to'langan qatorlar o'zgarmaydi.

### Bekor qilish qoidalari

Tadbir bekor qilinsa, to'langan puldan qancha **ushlab qolinishi** tadbirgacha qolgan kunga bog'liq. Standart: **30 kun va undan ko'p — 0%**, **14–29 kun — 30%**, **14 kundan kam — 100%**. Qoida qatorlari: **Kamida kun** va **Ushlab qolinadi, %** (1–10 qator; 0 kunli qator shart). O'zingizga moslab o'zgartiring.

### Eslatma kunlari

**Eslatma kunlari (vergul bilan)**: to'lov sanasidan necha kun oldin eslatish (standart 3 va 1). **Muddati o'tganda eslatish** — kalit. Eslatmalar [7-bo'limda](#eslatmalar).

> Qoidalarni saqlash modulni so'raydi; ko'rish — yo'q. Xato yozilsa, panel sababini yozadi.

## 4. Shartnoma shabloni va ishlab chiqarish sozlamalari

**Qayerda:** tadbir sahifasidagi havolalar qatori → **Ishlab chiqarish sozlamalari** (tadbirni ochmasdan ham shu sahifaga o'tish mumkin).

- **Zaxira, %** (0–50): rejalangan mehmonlarga qo'shiladi (kutilmagan mehmonlar uchun).
- **Osh kalkulyatori**: **1 kg oshga kishi** (odatda 5–6), **1 kg oshga masalliqlar** (nomi, birligi, miqdori), **Qozonlar (kg)** (masalan 10, 20, 30, 50), **Osh taomlari** (paketdagi qaysi taomlar osh hisoblanadi).
- **Porsiya og'irligi (gramm)**: porsiyada sanalgan taomni kg ga aylantirish uchun.
- **Yakuniy chek MXIK kodlari**: **Banket paketi**, **Zal ijarasi**, **Qo'shimchalar** (har birining kodi va qadoq kodi). Buxgalter tasdiqlaydi. **Fiskal rejimda MXIK'siz yakuniy chek chiqmaydi.**
- **Shartnoma matni**: `{{...}}` ko'rinishidagi joylar tadbir ma'lumotlari bilan to'ldiriladi (mijoz, sana va vaqt, zal, mehmonlar, paket, jamilar, to'lov jadvali, bekor qilish qoidalari). Bo'sh qoldirsangiz, standart matn. **Standart matn** tugmasi qaytaradi. Kelishuv valyutasi (USD) shartnomada faqat ma'lumot uchun; hisob so'mda.

## 5. Taqvim va zalni band qilish

**Qayerda (panel):** **Tadbirlar**. **Qayerda (kassa):** chap menyuda **Tadbirlar** (modul yoqiq bo'lsa). Kassada **14 kunlik** ro'yxat kun bo'yicha chiqadi (**Oldingi 2 hafta** / **Keyingi 2 hafta**, **Bugun**), **Hamma zallar** filtri bilan; har tadbirda holat, muddati o'tgan nishoni va **Qoldiq**. **Telefon bo'yicha** qidirish (raqamni to'liq yozing), **Yangi tadbir** (`banquets.manage`) va **Bozorlik ro'yxati** (chek printeriga). Tadbirni bossangiz tadbir kartasi ochiladi. Kassa tadbirlarni faqat internet bilan ishlaydi.

Taqvimda **Oy** yoki **Hafta** ko'rinishi, **Bugun**, **Oldingi/Keyingi** va **Bekor qilinganlar ham** kalitlari bor. Qatorlar — zallar, ustunlar — kunlar, har katak — **Ertalab / Kunduzi / Kechqurun**. Tadbir holati rangi va yozuvi bilan ko'rinadi; to'lov muddati o'tgan tadbirda nishon bor.

### Zalni vaqtincha band qilish ("hold")

1. Bo'sh katakdagi **+** ni bosing (yoki **Yangi tadbir**).
2. **Mijoz** va **Telefon**, **Tadbir turi** (To'y, Osh, Tug'ilgan kun, Korporativ, Boshqa), ixtiyoriy nomi.
3. **Vaqt** (slot) yoki **Boshqa vaqt**; **Zallar** (bir nechtasi birlashtiriladi).
4. **Paket**, **Kafolatlangan mehmonlar**, **Minimal mehmonlar** (bo'sh — paketniki), **Qo'shimchalar**, **Izoh**. Kerak bo'lsa **Kelishuv valyutasi** va kurs (faqat ko'rsatish uchun).
5. **Band qilish**.

Natija: zal **zaklad kutish soati** davomida band; zaklad olinmasa o'zi bo'shaydi ([7-bo'lim](#7-zaklad-va-oraliq-tolovlar-eslatmalar)).

Eslatmalar:

- Bir vaqtda ikki kishi **bir zal va bir vaqtni** band qilsa, **bittasi yutadi**, ikkinchisiga "zal band" deyiladi (to'qnashuv ko'rsatiladi).
- Zalda **xona bron qilingan** bo'lsa, zalni band qilib bo'lmaydi (va aksincha) — [ega-pos-sozlash.md](ega-pos-sozlash.md#12-bron-va-avans).
- O'tgan sanaga band qilib bo'lmaydi.
- Mehmonlar chegaradan oshsa yoki vaqt oynadan chiqsa — ogohlantirish, rad etish emas.

### O'zgartirish va ko'chirish

Tadbir kartasida **O'zgartirish**: sana, vaqt, zallar, mehmonlar, qo'shimchalar. To'lovlar saqlanadi, to'qnashuv qayta tekshiriladi. Mehmon soni o'zgarsa, to'lov jadvalining **to'lanmagan** qatorlari qayta hisoblanadi.

## 6. Narxlarni muzlatish va shartnoma

Shartnoma kuni tadbir kartasida **Narxlarni muzlatish** ni bosing: paket va narxlar tadbirga **muhrlanadi**. Keyin paket yoki narxni o'zgartirsangiz ham, bu tadbir o'zgarmaydi. Muzlatilgan tadbirda paketni almashtirib bo'lmaydi: avval **Muzlatishni bekor qilish** (sababi yoziladi, jurnalga tushadi).

**Shartnoma**: sahifasida matn tadbir ma'lumotlari bilan to'ldirilgan. **Raqam berish** tugmasi shartnomaga raqam beradi: `2026-0001` (yil va tartib raqami, do'kon bo'yicha, bir marta). **Chop etish** matnni chop etadi. Kassada tadbir kartasidagi **Shartnoma** tugmasi shartnomani **chek printeriga** chiqaradi; A4 shartnomani boshqaruv panelida chop eting (kartaning tagidagi yozuv ham shuni aytadi).

## 7. Zaklad va oraliq to'lovlar, eslatmalar

**To'lov jadvali** tadbir sahifasida: har qatorda **turi**, **sana**, **summa**, **holat** (To'lanmagan, To'langan, Qaytarilgan, Ushlab qolingan), **to'lov turi** va **chek**. Tepada **Jami**, **To'langan**, **Qolgan**, **Muddati o'tgan** va **Keyingi to'lov**.

### Kassada (naqd, karta, QR)

1. Kassada **Tadbirlar** → tadbirni oching. Kartada **To'lov jadvali**: har qatorda to'langan, kutilayotgan yoki muddati o'tgan holati, tepada muddati o'tganlar soni.
2. Qator yonidagi **To'lash** ni bosing: oynada **To'lov summasi** (qatorning hammasi yoki **qismi**) va to'lov turi: **naqd**, **karta** yoki doimiy (statik) **QR**. Qism to'lansa, qolgani o'sha sanaga yangi qator bo'lib qoladi. Bank o'tkazmasi kassada yozilmaydi: "boshqaruv panelida yoziladi" deb ko'rsatiladi. Dinamik Click/Payme QR jadval qatori uchun hozircha yo'q.
3. **To'lovni olish**. Pul kassaning **ochiq smenasiga** tushadi (smena yopiq bo'lsa "Smena ochilmagan: to'lov olish uchun avval smenani oching" deydi, [kassa.md](kassa.md#4-smenani-ochish)).
4. Darhol **bo'nak (avans) cheki** chop etiladi ("To'lov qabul qilindi, bo'nak cheki chop etildi"); naqdda kassa qutisi ochiladi, qaytim ko'rsatiladi. Bu tushum emas, oldindan to'lov.
5. Chek chiqmay qolsa yoki aloqa uzilsa: xabar chiqadi, **xuddi shu to'lovni qayta yuborish xavfsiz**, ikki marta yozilmaydi. Chekni qayta chiqarish: qatorda **Bo'nak chekini qayta chop etish** (nusxa sifatida).

**Zaklad** qatori to'liq to'langanda tadbir **Tasdiqlangan** bo'ladi va zal muddatsiz band qoladi.

> Tadbir to'lanmagan qatori muddatidan o'tib ketsa ham to'lov olish mumkin. Mavjud tadbirda pul hech qachon to'xtamaydi.

### Bank o'tkazmasi (faqat panelda)

Mijoz bank orqali o'tkazgan pul:

1. Panelda tadbirning **To'lov jadvali** sahifasi → qator yonida **Bank o'tkazmasi**.
2. **To'lov topshirig'i raqami**, **O'tkazma sanasi** va (bo'sh qoldirsangiz qatorning hammasi) **Summa**.
3. **Saqlash**.

Bu to'lov **smenaga tushmaydi va chek chiqmaydi** (hisob-faktura tizimdan tashqarida). U to'langan summaga kiradi va yakuniy chekda zaklad kabi oldindan to'lov sifatida ayiriladi.

> **Buxgalter uchun.** Bank o'tkazmasi bo'yicha fiskal chek chiqmaydi: bu egasining qarori. Yakuniy chek to'liq summaga chiqadi va o'tkazmani oldindan to'lov sifatida ko'rsatadi. Bo'nak cheki va "Bekor qilish haqi" cheki MXIK kodlarini tasdiqlang.

### Jadvalni o'zgartirish

**Jadvalni tahrirlash**: faqat **to'lanmagan** qatorlar (qator qo'shish, o'chirish, sana va summa). Yig'indisi qolgan summaga teng bo'lishi shart (panel hozirgi yig'indini ko'rsatadi). To'langan qatorlar o'zgarmaydi.

### Eslatmalar

Har kuni **ertalab soat 09:05 da** (Toshkent vaqti) do'kon egasi va admin chatiga Telegramda eslatma boradi: to'lanmagan qator sanasidan **3 va 1 kun oldin** (3. bo'limdagi kunlar), muddati o'tgach **birinchi kuni**, keyin **har 7 kunda**. Bir qatorga bir kunda bittadan ortiq xabar bormaydi. Eslatma mijozga emas, do'konning egasi va admin chatiga boradi.

## 8. Ishlab chiqarish varag'i, osh kalkulyatori, bozorlik ro'yxati

**Qayerda:** tadbir sahifasidagi havolalar qatori → **Ishlab chiqarish varag'i**, **Bozorlik ro'yxati**. Kassada tadbir kartasidagi **Ishlab chiqarish varag'i** va tadbirlar ro'yxatidagi **Bozorlik ro'yxati** tugmalari varaqlarni **chek printeriga** chiqaradi; A4 chop etish panelda qoladi.

### Ishlab chiqarish varag'i

Tadbir uchun oshxona qog'ozi. Mijoz ma'lumotlari yo'q (ism va telefon oshxonaga kerak emas).

- **Mehmonlar (hisob)** = **kafolatlangan**, **minimal** va **kelgan** mehmonlarning eng kattasi.
- **Rejalangan** = hisob × (1 + zaxira %), yuqoriga yaxlitlanadi.
- Har taom = paketdagi **kishi boshiga miqdor × rejalangan mehmonlar**; sexlar bo'yicha guruhlanadi (taomning sexi bo'yicha; sexi yo'q taom **Umumiy** da). **Kg** ustuni kg/l'dagi taomlarda o'zi, porsiyada — porsiya og'irligi sozlangan bo'lsa.

**Misol.** 200 kafolatlangan mehmon, zaxira 5% → rejalangan 210. Paketda kishi boshiga 0,2 kg salat bo'lsa, varaqda 42 kg salat.

**Chop etish** va **Excel yuklab olish** tugmalari bor.

### Osh kalkulyatori

Osh tadbirida (nahor oshi): mehmonlar sonini yozing, kalkulyator **necha kg osh**, **masalliqlar** (sozlamadagi 1 kg oshga miqdor bo'yicha) va **qozonlar** (nechta, qaysi o'lchamda) ni beradi. Hisob: kishi soni ÷ 1 kg oshga kishi, keyingi 0,5 kg ga yuqoriga. Qozonlar eng kattasidan boshlab, qoldiqni sig'diradigan eng kichik qozon bilan.

**Misol.** 200 kishi, 1 kg oshga 5 kishi → 40 kg osh. Qozonlar 50 kg bo'lsa, bitta 50 kg qozon.

### Bozorlik ro'yxati

Tanlangan kunlar (62 kungacha) ichidagi tadbirlar (vaqtincha band, tasdiqlangan, o'tkazilmoqda) uchun jami: **Osh masalliqlari** va **Taomlar** (mahsulot va birlik bo'yicha), tagida **Tadbirlar** ro'yxati. Bozorga chiqishdan oldin shuni chop eting.

## 9. Yakuniy hisob

Tadbir tugagach yoki tadbir kuni oxirida **yakuniy hisob** tuziladi. Qoida:

> **Hisobdagi mehmonlar = kelgan, kafolatlangan va minimal mehmonlarning eng kattasi.** Kam kelsangiz ham kafolatlangan sondan kam to'lanmaydi; ko'p kelsa — haqiqiy son.

Hisob = **paket × hisobdagi mehmonlar + zal ijarasi + qo'shimchalar + tadbir kuni buyurtmalari** (xizmat haqi faqat tadbir kuni buyurtmalariga, filial qoidasi bilan; paket, zal va qo'shimchaga yo'q).

**Misol.** Paket 150 000 so'm, kafolat 200, kelgan 185:

| Qator | Hisob | Summa |
|---|---|---|
| Paket | 150 000 × **200** (185 emas) | 30 000 000 |
| Zal ijarasi | | 5 000 000 |
| Qo'shimchalar | | 1 000 000 |
| **Jami** | | **36 000 000** |
| Zakladlar (to'langan) | | −10 800 000 |
| **Kassada to'lanadi** | | **25 200 000** |

Agar 210 mehmon kelsa, paket 150 000 × 210 = 31 500 000 (haqiqiy son).

### Kassada

1. Tadbir kartasida **Kelgan mehmonlar** (menejer) sonini yozib saqlang.
2. **Yakuniy hisob** ni oching: mehmonlar ("hisob kafolatlangandan kam bo'lmaydi"), qatorlar, **Tadbir kuni buyurtmalari**, **Jami**, **To'langan zakladlar** va **Qoldiq (kassada)** (yoki ortiqcha zaklad).
3. **Qoldiqni to'lash**: tadbirning hisobi **bitta schyot** bo'ladi, to'lanmagan jadval qatorlari o'chadi, zakladlar (kassadagi ham, bank o'tkazmasi ham) schyotda **oldindan to'lov** sifatida ayiriladi.
4. Qoldiq summa odatdagi **to'lov sahifasida** olinadi: naqd, karta, QR, aralash, nasiya ([kassa.md](kassa.md#12-tolov-turlari)). Zakladlar schyotni to'liq yopsa, to'lov sahifasi emas, schyot sahifasi ochiladi ("Schyot zakladlardan to'liq yopildi").
5. Schyot yopilganda **BITTA sotuv cheki** chiqadi: **to'liq summa** (36 000 000), zakladlar to'lovning "oldindan to'lov" qismi sifatida. Buyurtma yopiladi, tadbir **Yakunlangan** bo'ladi, zal bo'shaydi.

**Tadbir hisobini yopishni faqat shu yo'l bilan qiling.** Agar tadbir kuni buyurtmaning schyotini oddiy yo'l bilan alohida to'lab yopsangiz, **Qoldiqni to'lash** "buyurtma yopilgan" deydi: tadbir hisobi buzilmasin deb, hamisha **Yakuniy hisob** orqali ishlang.

### Zaklad summadan ko'p bo'lsa

Agar zakladlar yakuniy summadan ko'p bo'lsa (kam mehmon kelgan, lekin zaklad katta), schyot zakladdan to'liq yopiladi, **ortig'i** eng yangi zakladdan **REFUND** chekida qaytariladi:

- **naqd** — kassaning ochiq smenasidan, **qaytarish huquqi** (`pos.refund`) yoki **menejer tasdig'i** bilan ([kassa.md](kassa.md#2-pin-bilan-kirish-va-menejer-tasdigi));
- smena ochiq bo'lmasa yoki tasdiq yo'q bo'lsa ortiqcha zaklad **kutilmoqda** holatida qoladi. Keyin **Ortiqcha zakladni qaytarish** tugmasi bilan (menejer PIN'i bilan) qaytariladi: "mijozga qaytariladi (naqd — shu kassadan)".

Panelda **Yakuniy hisob** sahifasi faqat ko'rsatadi: yakunlash va to'lov kassada.

## 10. Tadbir kuni

1. Tadbir kuni kassada tadbir kartasida **Tadbirni boshlash** ni bosing (tadbir **Tasdiqlangan** bo'lishi kerak: zaklad to'langan).
2. Kassa **tadbir kuni buyurtmasini** (**Tadbir buyurtmasi**) ochadi: oddiy buyurtma ekrani, sarlavhasi **Tadbir: <tadbir nomi>**; stolsiz zal buyurtmasi. Zal ijarasi bu buyurtmada hisoblanmaydi (u paketda).
3. Ichimlik va qo'shimcha taomlar shu buyurtmaga **oddiy bosqichlar** bilan qo'shiladi va **oshxonaga yuboriladi** (oshxona ekrani va printer avvalgidek). Xizmat haqi shu qatorlarga filial qoidasi bilan qo'shiladi.
4. Mehmonlar kelganda **Kelgan mehmonlar** sonini yozing (yakuniy hisobga ta'sir qiladi, [9-bo'lim](#9-yakuniy-hisob)). Bir necha marta o'zgartirish mumkin.
5. Tadbir tugagach — **Yakuniy hisob**.

Tadbirni qayta "boshlasangiz" o'sha buyurtmaning o'zi ochiladi (ikkinchi buyurtma yaratilmaydi).

## 11. Tadbirni bekor qilish

Bekor qilish tadbirni **Bekor qilingan** qiladi va zalni bo'shatadi.

### Hisob (nima ushlab qolinadi, nima qaytariladi)

Bekor qilish oynasi (panelda va kassada **Tadbirni bekor qilish**; kassada kirgan xodimda `banquets.manage` bo'lishi kerak) avval **hisobni** ko'rsatadi:

- tadbirgacha **necha kun** qolgan va qaysi **qoida** ([3-bo'lim](#3-tolov-shabloni-va-bekor-qilish-qoidalari)) qo'llanadi, necha foiz ushlab qolinadi;
- **Ushlab qolinadi** va **Qaytariladi** summalari; har to'lov uchun qaysi yo'l bilan qaytarilishi.

**Misol.** To'langan 10 800 000, tadbirgacha 20 kun (14–29 kun qoidasi — 30%): ushlab qolinadi 3 240 000, qaytariladi 7 560 000.

Ushlab qolinadigan qism **eng eski to'lovlardan** (zakladdan boshlab) olinadi, qolgani qaytariladi.

### Qaytarish yo'llari

| To'langan usul | Qanday qaytariladi |
|---|---|
| **Naqd** | shu kassaning **ochiq smenasidan** (smena ochiq bo'lishi shart; qaytarish huquqi yoki menejer tasdig'i). Shuning uchun naqd qaytarishni **kassada** bajaring. |
| **Karta** va **QR** | to'langan yo'li bilan, har biri asl bo'nak chekiga bog'langan **REFUND** cheki bilan |
| **Bank o'tkazmasi** | "o'tkazma bilan qaytariladi": pulni siz bank orqali qaytarasiz, tizimda belgi qoladi |

Ushlab qolingan qism uchun **"Bekor qilish haqi"** sotuv cheki chiqadi (to'lovi oldindan to'langan pul hisobidan). Hech narsa to'lanmagan bo'lsa, tadbir shunchaki bekor qilinadi.

### Menejer summani o'zgartirsa

Menejer ushlab qolinadigan summani qoidadan **farqli** qo'yishi mumkin (**Ushlab qolinadigan summani o'zgartirish**): bunda **sabab** yozish shart, menejer tasdig'i kerak bo'lishi mumkin. Egasiga Telegramga alohida qator bilan xabar boradi ("qoida bo'yicha ... edi, menejer o'zgartirdi").

**Bekor qilish sababi** har doim yoziladi (kamida 3 harf).

> **Buxgalter savoli.** Ushlab qolingan qism ("Bekor qilish haqi") qaysi MXIK va qaysi soliq rejimida chekka chiqishini tasdiqlang ([3-bo'lim](#3-tolov-shabloni-va-bekor-qilish-qoidalari), "Bekor qilish haqi" MXIK).

## 12. Hisobot, X va Z

### Tadbirlar hisoboti

**Qayerda:** panelda **Tadbirlar hisoboti**. Davr (standart joriy oy, 366 kungacha) tadbir sanasi bo'yicha: **Tadbirlar** soni va mehmonlar, **Jami**, **To'langan**, **Qolgan**, **Muddati o'tgan** (soni va summasi), **Ushlab qolingan / qaytarilgan**, o'tkazma bilan qaytariladiganlar, **Bekor qilinganlar**. Jadvalda har tadbir: sana, tadbir, mijoz, mehmon, summa, to'langan, holat. **Excel yuklab olish** — "Tadbirlar" va "Jami" varaqlari.

- Bekor qilinganlar **Jami** va **Qolgan** ga kirmaydi.
- Yakuniy hisobi tuzilgan tadbirda **To'langan** = zakladlar + yakuniy hisobga kassada to'langan; **Qolgan** = hisobda qolgani (yopilgach 0).
- `banquets.manage` siz telefon raqami yashiriladi.

### X va Z

Kassadagi X va Z hisobotda (ekranda ham, chekda ham) **Tadbirlar** bo'limi: shu smenada olingan tadbir to'lovlari (soni, summasi; naqd, karta, QR) va qaytarilganlari. **Naqd zakladlar kutilgan naqdga qo'shiladi**, naqd qaytarishlar ayiriladi. Z Telegram xulosasida **Tadbir avanslari** qatori bor.

## 13. Muammolar va yechimlar

| Muammo | Sabab va yechim |
|---|---|
| Panelda **Tadbirlar** yo'q | Modul yoqilmagan, tarifda yo'q yoki sizda `banquets.manage` yo'q. **Kassa sozlamalari** → **Format**ni tekshiring. |
| "Zal band" deydi | Shu zal va vaqtda boshqa tadbir bor (yoki ikki kishi bir vaqtda band qilgan: bittasi yutdi). Boshqa vaqt yoki zalni tanlang. |
| Zalni band qilib bo'lmadi: xona bron qilingan | Shu zalda xona broni bor. Avval bronni ko'ring ([ega-pos-sozlash.md](ega-pos-sozlash.md#12-bron-va-avans)). |
| Band qilingan zal o'zi bo'shab ketdi | Zaklad **zaklad kutish soati** ichida olinmagan. Qayta band qiling va zaklad oling. |
| Paketni almashtirib bo'lmaydi | Narxlar muzlatilgan. Avval **Muzlatishni bekor qilish** ([6-bo'lim](#6-narxlarni-muzlatish-va-shartnoma)). |
| To'lov olinmaydi: "smena ochiq emas" | Kassada smena yo'q. Smenani oching ([kassa.md](kassa.md#4-smenani-ochish)). |
| Jadvalni saqlab bo'lmaydi: yig'indi mos emas | To'lanmagan qatorlar yig'indisi qolgan summaga teng bo'lsin. Panel kerakli summani ko'rsatadi. |
| **Qoldiqni to'lash**: "buyurtma yopilgan" | Tadbir kuni buyurtma schyotini alohida to'lab yopilgan. Tadbir hisobi faqat **Yakuniy hisob** orqali yopiladi; menejerga ayting. |
| Yakuniy chek chiqmadi: MXIK yo'q | **Ishlab chiqarish sozlamalari**da yakuniy chek MXIK kodlarini kiriting (buxgalter tasdiqlaydi). |
| Ortiqcha zaklad qaytarilmadi | Smena ochiq emas yoki menejer tasdig'i yo'q. Smenani oching, **Ortiqcha zakladni qaytarish** ni menejer PIN'i bilan bajaring. |
| Naqd qaytarish panelda ishlamadi | Naqd qaytarish kassaning ochiq smenasidan: bekor qilishni kassada bajaring. |
| Eslatma kelmadi | Do'kon Telegram chati ulanmagan yoki **Eslatma kunlari** bo'sh ([3-bo'lim](#3-tolov-shabloni-va-bekor-qilish-qoidalari)). |
| Ishlab chiqarish varag'i bo'sh | Paketga menyu taomlari (kishi boshiga miqdor) qo'shilmagan ([2-bo'lim](#2-sozlash-zallar-vaqtlar-paketlar-qoshimchalar-ijara)). |
