# Oshxona ekrani: oshpaz va planshet sozlovchi uchun qo'llanma

Oshxona ekrani — planshet yoki kompyuter ekrani. Unda restoranga kelgan barcha buyurtmalar (Telegram, ilova, kassa, zal)
bitta doskada ko'rinadi. Oshpaz taom tayyor bo'lganda uni bosib belgilaydi, belgi darhol menejerning panelida ko'rinadi.

Bu qo'llanma ikki kishi uchun:

- **Planshetni sozlovchi** (ega yoki menejer) — [1. Planshetni ulash](#1-planshetni-ulash-bir-marta) bo'limi.
- **Oshpaz** — qolgan bo'limlar.

## 1. Planshetni ulash (bir marta)

Bu ishni faqat bir marta qilasiz. Keyin planshet o'zi ulangan holda qoladi.

**Panelda (kompyuterda):**

1. [Hamkor paneli](hamkor-panel.md)ga kiring.
2. **Sozlamalar** → **Qurilmalar** → **Ulash kodini yaratish**.
3. **Turi**: **Oshxona ekrani**. **Filial**: shu oshxona qaysi filialniki bo'lsa, shuni tanlang. **Nomi**: masalan, "Oshxona 1".
4. **Kod yaratish** ni bosing.
5. Ekranda `ABCD-EFGH` ko'rinishida kod chiqadi. Kod **10 daqiqa** amal qiladi va **bir marta** ishlaydi.

**Planshetda:**

1. Oshxona ilovasini oching. **Terminalni ulash** ekrani chiqadi.
2. **Ulash kodi** maydoniga kodni yozing. Chiziqcha shart emas, kichik harf ham bo'ladi: ilova o'zi `ABCD-EFGH` qiladi.
3. **Ulash** tugmasini bosing.
4. Ulandi. Endi PIN ekrani chiqadi (keyingi bo'lim).

Kod xato bo'lsa yoki muddati o'tgan bo'lsa, panelda yangi kod yarating (**Yangi kod**).

Muhim:

- Planshet ulangach, panelning **Qurilmalar** ro'yxatida "Oshxona 1" paydo bo'ladi.
- Panelda qurilma **O'chirish** bilan o'chirilsa, planshet o'zi ulash ekraniga qaytadi.
- Ilovani o'chirib-yoqsangiz, qayta ulash shart emas.

## 2. Oshpaz PIN bilan kiradi

Oshpaz uchun PIN kodni ega yoki menejer panelda o'rnatadi: **Xodimlar** → oshpaz qatori → kalit belgisi → **PIN o'rnatish**
([panel qo'llanmasi](hamkor-panel.md#pin-kod-oshxona-ekrani-va-kassa-uchun)). PIN — 4 dan 6 gacha raqam.

Kirish:

1. **Kim ishlaydi?** ekranida ismingizni bosing.
2. PIN kodingizni tering. 4 raqam bo'lsa, ✓ tugmasini bosing; 6 raqam bo'lsa, o'zi yuboriladi.
   Kompyuterda klaviatura ham ishlaydi (raqamlar, Backspace, Enter).
3. Doska ochiladi.

Boshqa odam kirmoqchi bo'lsa, **Boshqa xodim** tugmasi bilan ismlar ro'yxatiga qayting.

Muammolar:

- **PIN noto'g'ri. Qayta urinib ko'ring.** — PIN xato terildi. Qaytadan tering.
- Ko'p marta xato qilsangiz, klaviatura vaqtincha o'chadi va "... dan keyin qayta urinib ko'ring" deb sanoq chiqadi.
  Kuting. Har keyingi xatoda kutish uzayadi. To'g'ri PIN terilgach, hisob nolga tushadi.
- **PIN o'rnatilgan xodim yo'q** — ro'yxat bo'sh. Menejerga ayting: panelda **Xodimlar** bo'limidan oshpazga PIN o'rnatsin.
  Keyin planshetda **Yangilash** ni bosing.
- Ismingiz ro'yxatda yo'q — sizga PIN qo'yilmagan yoki siz boshqa filial xodimisiz.

Agar hech kim kirmagan bo'lsa ham, yangi buyurtma kelsa ovoz chalinadi va "1 ta yangi buyurtma oshxonaga keldi" yozuvi chiqadi.

## 3. Doskani o'qish

Doskada qabul qilingan va tayyorlanayotgan buyurtmalar (chiptalar) turadi.

- **Eng eski chipta eng boshida**: chap yuqorida. Keyin o'ngga, keyin pastga.
- Ustunlar soni ekran kengligiga qarab o'zgaradi (planshetda 3, katta televizorda 5).
- Ekran tepasida: filial nomi, soat, ismingiz, ovoz tugmasi, sozlamalar va **Chiqish**.
- Chipta yo'q bo'lsa: "Hozircha buyurtma yo'q". Yangi buyurtma shu yerda o'zi chiqadi.

### Chiptada nima yozilgan

| Qism | Ma'nosi |
|---|---|
| Raqam (`#E101`) | Buyurtma raqami. Mijoz va kassir ham shu raqamni biladi |
| **Yangi** belgisi | Chipta hozirgina keldi (bir daqiqa turadi) |
| Soat va vaqt (masalan, `4:32`) | Buyurtma qabul qilinganiga necha daqiqa bo'ldi |
| **Zal**, **Olib ketish**, **Yetkazish** | Buyurtma turi. Zalda stol raqami ham yoziladi (**Stol 5**) |
| Kanal | Qayerdan kelgani: Telegram, ilova, kassa |
| `2/5 tayyor` | 5 ta taomdan 2 tasi tayyor |
| Izoh (sariq qutida) | Mijozning izohi, masalan "achchiq qilmang". Doim o'qing |
| Taom qatori | Miqdor (`2×`) yoki vazn (`1.5 kg`), taom nomi, qo'shimchalar (`+ Achchiq`) |

Narx va mijoz ma'lumoti oshxona ekranida ko'rsatilmaydi.

### Ranglar va taymer

Chipta sarlavhasining rangi buyurtma qancha kutganini bildiradi. Rang bilan birga belgi va yozuv ham bor:

| Ko'rinish | Ma'nosi | Standart chegara |
|---|---|---|
| Kulrang, soat belgisi | Hammasi joyida | 10 daqiqagacha |
| Sariq, qum soati, **Shoshiling** | Kutish uzaydi | 10 daqiqadan |
| Qizil, ogohlantirish belgisi, **Kechikdi** | Buyurtma kechikdi | 20 daqiqadan |

Chegaralarni sozlamalarda o'zgartirsa bo'ladi ([5. Sozlamalar](#5-sozlamalar)).

## 4. Taomni tayyor deb belgilash

### Bitta taom

1. Tayyor bo'lgan taom qatorini bosing.
2. Qator darhol **✓** bilan belgilanadi va ustidan chiziladi. Chiptada `3/5 tayyor` ga o'zgaradi.

Belgi serverga yuboriladi va menejerning panelida ko'rinadi.

### Xato bosildi: qaytarish

1. Tayyor deb belgilangan qatorni yana bosing: belgi qaytadi.
2. Yoki pastdagi **Yaqinda tayyor** lentasida (oxirgi 10 daqiqada belgilangan taomlar) kerakli taomning **Qaytarish** tugmasini bosing.

Buyurtma allaqachon "tayyor" bo'lib doskadan ketgan bo'lsa, qaytarib bo'lmaydi: ilova buni yozib chiqaradi.
Bunday holda menejerga ayting.

### Hammasi tayyor

Chiptadagi qolgan hamma taomni birdan belgilash uchun **Hammasi tayyor** tugmasini bosing.

- **Yetkazish** va **Olib ketish** buyurtmasi oxirgi taom tayyor bo'lganda o'zi tayyor bo'ladi va doskadan ketadi.
- **Zal** buyurtmasi stol yopilguncha doskada qoladi. Hamma taom tayyor bo'lsa, **Buyurtma tayyor** tugmasi chiqadi.

### Belgilash ishlamasa

Taom qatori ostida sabab yoziladi. Odatda server rad etgan yoki aloqa yo'q. Belgi o'zi qaytadi. Aloqa tiklangach, qayta bosing.
Ekran faqat ko'rish uchun bo'lsa (taom qatorlari bosilmasa va **Hammasi tayyor** tugmasi yo'q), sizning rolingizda oshxona ruxsati yo'q.
Menejerga ayting.

## 5. Sozlamalar

PIN bilan kirgandan keyin tepadagi tishli g'ildirak belgisi (**Sozlamalar**). PIN ekranida sozlamalar yo'q: PIN'siz
hech kim ovozni yoki avtomatik chiqishni o'chira olmaydi. Sozlamalar shu planshetning o'zida saqlanadi.

| Sozlama | Nima qiladi |
|---|---|
| **Yangi buyurtma ovozi** | Yangi chipta kelganda signal. Yoqish yoki o'chirish |
| **Qorong'i mavzu** | Qorong'i yoki yorug' ekran. Standart: qorong'i |
| **Shrift** | **100%**, **120%**, **140%**. Televizorda kattaroq tanlang |
| **Shoshiling (sariq)** | Necha daqiqadan keyin chipta sariq bo'lsin |
| **Kechikdi (qizil)** | Necha daqiqadan keyin chipta qizil bo'lsin |
| **Avtomatik chiqish** | Ekranga shuncha vaqt tegilmasa, PIN qayta so'raladi (5, 10, 15, 30, 60 daqiqa yoki o'chiq). Standart: 15 daqiqa |

**Yopish** tugmasi oynani yopadi.

Ovozni ekran tepasidagi karnay belgisi bilan ham tezda o'chirib-yoqish mumkin (**Ovozni o'chirish** / **Ovozni yoqish**).

Ekran o'chib qolmasligi uchun Android planshetda bu o'zi ta'minlanadi. Windows kompyuterda ekran saqlovchi va uyqu rejimini
Windows sozlamalarida o'chirib qo'ying.

## 6. Aloqa belgisi

Tepada kichik belgi aloqa holatini ko'rsatadi:

- **Jonli** — yangi buyurtma darhol chiqadi.
- **Ulanmoqda** yoki **Qayta ulanmoqda** — ilova ulanishga harakat qilmoqda. Ko'p holda o'zi tiklanadi.
- **Har 15 soniyada** — jonli ulanish yo'q (yoki server oshxona kanaliga hali ruxsat bermadi), ro'yxat har 15 soniyada
  yangilanadi. Ish davom etaveradi. Uzoq tursa, menejerga ayting.

### "Aloqa yo'q" lentasi

Tepada qizil **Aloqa yo'q** lentasi chiqsa, planshet serverga ulana olmayapti. Lentada "Oxirgi yangilanish 14:05" yoziladi.

Nima qilish kerak:

1. Ekrandagi chiptalar eski bo'lishi mumkin: yangi buyurtmalar kelmayapti, tayyor belgisi ham yetib bormasligi mumkin.
2. Planshetda Wi-Fi yoqilganini va internet borligini tekshiring.
3. **Qayta urinish** tugmasini bosing.
4. Aloqa tiklansa, lenta o'zi yo'qoladi va ro'yxat yangilanadi.
5. Uzoq vaqt tiklanmasa, menejerga ayting va muhim buyurtmalarni og'zaki tekshiring.

## 7. Chiqish

- Ish tugasa yoki boshqa oshpaz almashsa: tepadagi **Chiqish** (qulf belgisi) tugmasini bosing.
- Ekranga 15 daqiqa (sozlanadi) tegilmasa, ilova o'zi chiqaradi: "Ekranga uzoq tegilmagani uchun chiqildi. Davom etish uchun PIN tering."
- Chiqqandan keyin ham planshet ulangan qoladi: yangi buyurtma kelsa ovoz chalinadi.
- Sessiya tugasa: "Sessiya tugadi. PIN bilan qayta kiring." — PIN'ni qayta tering. (Sessiya 12 soat amal qiladi.)

**Planshetni butunlay uzish** (boshqa oshxonaga o'tkazish yoki sotish oldidan) — buni faqat ega yoki menejer qiladi:

- **Panelda (eng oson):** **Sozlamalar** → **Qurilmalar** → planshet qatoridagi **O'chirish**. Planshet o'zi ulash ekraniga qaytadi.
- **Planshetda:** menejer o'z PIN kodi bilan kiradi → **Sozlamalar** → **Terminalni uzish** → **Uzish**. Planshet serverda
  ham o'chiriladi. Faqat internet bo'lmasa, planshet buni aytadi — unda panelda ham **O'chirish** bilan o'chiring.

Oshpazda bu tugma yo'q, PIN ekranida ham yo'q. Qayta ulash uchun yangi kod kerak bo'ladi.

## 8. Muammolar va yechimlar

| Muammo | Nima qilish kerak |
|---|---|
| Ulash kodi qabul qilinmayapti | Kod 10 daqiqada eskiradi va bir marta ishlaydi. Panelda **Yangi kod** yarating. Kodni to'g'ri yozganingizni tekshiring |
| PIN ekranida ism yo'q | Panelda oshpazga PIN o'rnatilmagan. **Xodimlar** bo'limida o'rnating, planshetda **Yangilash** ni bosing |
| **PIN noto'g'ri** | Qayta tering. 5 marta xato qilsangiz, bir daqiqa kutish kerak |
| Planshet o'zi ulash ekraniga qaytdi | Panelda bu qurilma o'chirilgan. Yangi kod yarating va qayta ulang |
| Yangi chipta kelmayapti | Tepadagi aloqa belgisini va qizil **Aloqa yo'q** lentasini tekshiring. Wi-Fi'ni tekshiring |
| "Server javobini o'qib bo'lmadi" yoki "N ta chiptani o'qib bo'lmadi" | Aloqa bor, lekin ilova eski bo'lishi mumkin. Menejerga ayting: ilovani yangilash kerak. Qolgan chiptalar bilan ishlashda davom eting |
| Ovoz yo'q | Karnay belgisi yoqilganini tekshiring (**Ovozni yoqish**), **Sozlamalar** da **Yangi buyurtma ovozi** yoqilganini ko'ring, planshet ovozini balandlating |
| Taom qatorini bosganda belgi qaytyapti | Server rad etdi yoki aloqa uzildi. Taom ostidagi sababni o'qing, aloqa tiklangach qayta bosing |
| Hamma taomni bosdim, buyurtma doskada qoldi | Zal buyurtmasi stol yopilguncha qoladi. **Buyurtma tayyor** tugmasini bosing |
| Yaqinda tayyor taomni qaytarib bo'lmadi | Buyurtma allaqachon tayyor bo'lib ketgan. Menejerga ayting |
| Chiptalar juda kichik yoki juda katta | **Sozlamalar** → **Shrift** |
| Ekran o'chib qolyapti | Planshet yoki kompyuter sozlamalarida uyqu rejimini o'chiring |
