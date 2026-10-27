# Oshxona ekrani: oshpaz va planshet sozlovchi uchun qo'llanma

Oshxona ekrani — planshet yoki kompyuter ekrani. Unda restoranga kelgan barcha buyurtmalar (Telegram, ilova, kassa, zal)
bitta doskada ko'rinadi. Oshpaz taom tayyor bo'lganda uni bosib belgilaydi, belgi darhol menejerning panelida ko'rinadi.

Choyxona va katta oshxonada ekran **sexlar** bo'yicha ishlaydi: osh, mangal, salat va bar, choy. Har sexning oshpazi faqat o'z
taomlarini ko'radi. Mangal sexida taomlar six sanog'i bilan chiqadi. Oldindan buyurtmalar (nahor oshi, N kg osh) o'z vaqt
navbatida turadi. Osh qoldig'i uchun **Qozon** paneli bor.

Bu qo'llanma ikki kishi uchun:

- **Planshetni sozlovchi** (ega yoki menejer) — [1. Planshetni ulash](#1-planshetni-ulash-bir-marta) bo'limi. Sexlarni
  [ega qo'llanmasida](ega-pos-sozlash.md#7-sexlar-va-printerlar) sozlaysiz.
- **Oshpaz** — qolgan bo'limlar. Choyxona oshpazi uchun: [8. Sexlar](#8-sexlar), [9. Mangal](#9-mangal-korinishi-six-sanogi),
  [10. Oldindan buyurtmalar](#10-oldindan-buyurtmalar), [11. O'chirilgan taom](#11-ochirilgan-taom-ochirildi-va-berildi),
  [12. Qozon](#12-qozon-paneli).

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

Filialda sexlar sozlangan bo'lsa, PIN'dan keyin **qaysi sexda ishlashingizni** tanlaysiz: [8. Sexlar](#8-sexlar).

Agar hech kim kirmagan bo'lsa ham, yangi buyurtma kelsa ovoz chalinadi va "1 ta yangi buyurtma oshxonaga keldi" yozuvi chiqadi.

## 3. Doskani o'qish

Doskada qabul qilingan va tayyorlanayotgan buyurtmalar (chiptalar) turadi.

- **Eng eski chipta eng boshida**: chap yuqorida. Keyin o'ngga, keyin pastga.
- Ustunlar soni ekran kengligiga qarab o'zgaradi (planshetda 3, katta televizorda 5).
- Ekran tepasida: filial nomi, soat, ismingiz, ovoz tugmasi, sozlamalar va **Chiqish**.
- Chipta yo'q bo'lsa: "Hozircha buyurtma yo'q". Yangi buyurtma shu yerda o'zi chiqadi.
- Pastroqda bo'limlar yorlig'i: **Buyurtmalar**, **Oldindan buyurtmalar** va (ruxsati borlarga) **Qozon**.

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
| **Osh: oldindan chiqish** | Oldindan buyurtma muddatidan necha daqiqa oldin osh doskaga chiqadi (standart 120) |
| **Mangal: oldindan chiqish** | Xuddi shunday mangal uchun (standart 30) |
| **Boshqa sexlar: oldindan chiqish** | Qolgan sexlar uchun (standart 30) |

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

## 8. Sexlar

Sex — taom tayyorlanadigan joy: **Osh**, **Mangal**, **Salat bar**, **Choy**, **Oshxona**. Har taom o'z sexiga yo'naltiriladi (buni ega
panelda sozlaydi). Kassir yoki ofitsiant taomni yuborganda u o'sha sexning printeriga va/yoki ekraniga tushadi.

**Sexni tanlash.** PIN kiritgandan keyin (filialda ekranda ko'rsatiladigan sexlar bo'lsa) sizdan so'raladi:

1. Ishlayotgan sexingizni bosing. Bir nechtasini ham belgilash mumkin (masalan, **Osh** va **Choy**).
2. **Umumiy** — sexsiz qatorlar: onlayn buyurtmalar (Telegram, ilova) va qaysi sexga tegishli ekani belgilanmagan taomlar. Odatda bitta ekranda
   **Umumiy** ham belgilanadi, shunda hech narsa e'tibordan chetda qolmaydi.
3. **Hammasi** — hamma sexni belgilaydi (**Hammasini olib tashlash** — tanlovni bekor qiladi).
4. **Davom etish**.

Tanlov planshetda **saqlanadi**: ekran qayta yoqilganda qayta so'ralmaydi. O'zgartirish uchun tepadagi **Sex: Mangal, Osh**
tugmasini bosing (hech narsa tanlanmagan bo'lsa u **Sexlar** deb turadi).

Doskada faqat tanlangan sexlarning qatorlari ko'rinadi. Chiptada boshqa sex qatorlari yo'q; **Hammasi tayyor** tugmasi ham faqat
sizga ko'rinadigan qatorlarni belgilaydi.

Filialda sex sozlanmagan bo'lsa, tanlash ekrani chiqmaydi va hamma chipta ko'rinadi.

**Zal buyurtmasining xususiyatlari** (kassa yoki ofitsiant ochgan):

- Choyxonada xona hisobi 4–6 soat ochiq turadi. Shuning uchun kutish vaqti butun seansdan emas, **eng eski pishayotgan qatordan** sanaladi.
- **Bosqichlar** alohida sarlavhalar bilan chiqadi ("1-bosqich", "2-bosqich"): mehmon avval choy, keyin salat, keyin osh buyursa, har biri o'z bosqichida.
- Zal buyurtmasida **Buyurtma tayyor** tugmasi yo'q: hammasi qator bo'yicha belgilanadi. Stolni ofitsiant va kassir yopadi.

## 9. Mangal ko'rinishi (six sanog'i)

Faqat **mangal** sexi tanlangan bo'lsa, doska o'zi **Mangal** ko'rinishiga o'tadi. Yuqoridagi **Oddiy / Mangal** tugmasi bilan
qo'lda ham almashtirish mumkin (tanlov saqlanadi).

Mangal chiptasida:

- **Xona yoki stol** (katta harflar bilan: "Xona 3", "Stol 5") — six qayerga borishini darhol ko'rasiz.
- Bosqich raqami va kutish vaqti. Ranglar oddiy doskadagidek: kulrang, sariq (**Shoshiling**), qizil (**Kechikdi**).
- Taomlar **six soni bilan**: **Qo'y shashlik — 6 six**, **Tovuq shashlik — 4 six**. Bir bosqichdagi bir xil taom bitta qatorga qo'shiladi.
  Sarlavhada **Jami: 10 six**.

Kg bilan sotiladigan mangal taomi (masalan, lyulya kabob 1.5 kg) six bilan sanalmaydi: u alohida qatorda o'z miqdori bilan turadi.

Qatorni bossangiz, shu taomning **hamma sixi** tayyor bo'ladi. Xato bosilgan bo'lsa, yana bosing — qaytadi. Pastda **Hammasi tayyor**
tugmasi bor. Yangi chiptada **Yangi** belgisi bir daqiqa turadi.

Mangal chiptasida ham **O'CHIRILDI** va **Berildi** belgilari oddiy chiptadagidek ishlaydi.

## 10. Oldindan buyurtmalar

Bron bilan oldindan buyurilgan taomlar (nahor oshi, N kg osh, qozon kabob, set) va ofitsiant "ushlab turish" bilan saqlagan qatorlar
darhol doskaga tushmaydi. Ular alohida **Oldindan buyurtmalar** yorlig'ida **muddat bo'yicha** turadi: eng yaqini birinchi.

Har qatorda: muddat soati (masalan, 14:30), qolgan vaqt (yoki "… o'tdi", kechiksa), xona yoki stol, bosqich, sex, taom va miqdor.
Qolgan vaqt **server soati** bo'yicha hisoblanadi: planshet soati noto'g'ri bo'lsa ham to'g'ri chiqadi.

**Qachon oddiy doskaga o'tadi.** Muddatdan oldin, taom turiga qarab (standart bo'yicha **osh — 120 daqiqa**, **mangal — 30 daqiqa**,
**boshqa sexlar — 30 daqiqa**; bu **Sozlamalar** da o'zgaradi: **Osh: oldindan chiqish**, **Mangal: oldindan chiqish**, **Boshqa
sexlar: oldindan chiqish**). Masalan, osh 14:30 ga buyurilgan bo'lsa, u 12:30 da doskaga chiqadi: qozonni shu vaqtda qo'yish kerak.
Ofitsiant oldinroq yuborsa, qator ham oldinroq chiqadi.

Doskada hali yuborilmagan qator "**Muddat 14:30 · hali yuborilmagan**" yoki "Oldindan buyurtma · hali yuborilmagan" belgisi bilan turadi.

**Oldindan tayyor qilish.** Qator yonida **Tayyor** tugmasi bor (agar oshxona taomni muddatdan oldin pishirib bo'lgan bo'lsa).
Bosilsa, qator tayyor bo'ladi. Mehmon kelganda ofitsiant uni "Berildi" qiladi. Yuborilmagan qatorni doskada "tayyor" deb bo'lmaydi:
server rad etadi.

## 11. O'chirilgan taom (O'CHIRILDI) va "Berildi"

**O'CHIRILDI.** Kassir yoki menejer yuborilgan taomni o'chirsa (mehmon voz kechdi, xato kiritildi), chiptada:

- qator ustidan chiziladi va qizil **O'CHIRILDI** belgisi chiqadi;
- sababi yoziladi: **Sabab: Mehmon xohlamadi**;
- ish paytida **alohida ovoz** chalinadi (yangi chiptanikidan boshqacha);
- belgi **2 daqiqa** turadi, keyin yo'qoladi.

Bu — "**bu taomni pishirmang**" degani. Agar taom allaqachon pishgan bo'lsa, menejer bilan gaplashing: o'chirilgan taom hisobdan ketadi.
Belgi rangdan tashqari yozuv va belgi bilan ham ko'rsatiladi.

**Butun buyurtma bekor qilinsa**, u chiptalar ro'yxatidan ketadi, lekin ekranda qizil **O'CHIRILDI** chiptasi 2 daqiqa turadi: hamma
taomi chizilgan. Oshpazning o'z "tayyor" bosishi bekor qilish deb hisoblanmaydi.

Sex printeriga ham **O'CHIRILDI** markasi chiqadi.

**Berildi.** Ofitsiant taomni mehmonga olib borib "Berildi" bossa, qator doskada tugatilgan ko'rinishda turadi (**Berildi**, ✓✓).
Bu ofitsiantning ishi: oshxona ekrani uni faqat ko'rsatadi, bosib qaytarib bo'lmaydi. Hamma qatori berilgan chipta doskadan ketadi.

## 12. Qozon paneli

**Qozon** yorlig'i (ruxsati borlarga: oshpaz, menejer, ega) — osh va boshqa qozonda pishadigan taomlar qoldig'i. Qozonda pishirilib,
porsiyalab sotiladigan taom shu yerda sanaladi. Kassa va ofitsiant buyurtma olganda qoldiq o'zi kamayadi; qoldiq tugasa taom
**Tugadi** bo'ladi va Telegram menyusida ham darhol yopiladi.

Panelda filialning ochiq qozonlari: taom nomi, qolgan porsiya (katta raqam), pishirilgan soni (`12 / 60`), qachon ochilgani va
rangli chiziq: **yashil** — yetarli, **sariq** — 20% dan kam qoldi, **qizil** — **TUGADI**. Panel o'zi yangilanadi (va **Yangilash**
tugmasi bor). Aloqa bo'lmasa: "Server bilan aloqa yo'q. Qoldiq eskirgan bo'lishi mumkin."

**Amallar:**

| Amal | Qachon | Qanday |
|---|---|---|
| **Yangi qozon** | Yangi osh qo'ydingiz | **Yangi qozon** → **Taom**ni tanlang → **Nechta porsiya pishirildi** → (ixtiyoriy) **Qozon nomi** → **Ochish** |
| **Qo'shish** | Yana pishirdingiz | Qozon kartochkasida `+5`, `+10`, `+20` tugmalari yoki o'zingiz son yozasiz ("**yana pishirildi**") |
| **Sanash** | Qoldiq mos kelmayapti | **Sanash** → **Hozir qolgan miqdor**ni yozing → **Saqlash**. Qoldiq shu songa tuzatiladi (sabab: "Oshxonada sanaldi") |
| **Yopish** | Qozon tugadi yoki tashlandi | **Yopish** → "qozonni yopamizmi?" → **Yopish**. Yopilgach yangi qozon ochilguncha taom menyuda **tugadi** turadi |
| **Qayta ochish** | Yopishni adashib bosdingiz | **Yaqinda yopilgan** ro'yxatida **Qayta ochish** |

Qozon bo'lmasa: "Qozon bo'yicha sotiladigan taomlar qozon ochilmaguncha menyuda 'tugadi' turadi. 'Yangi qozon' tugmasi bilan oching."
**Shuning uchun har kuni birinchi osh qo'yilganda qozonni ochishni unutmang**, aks holda osh sotilmaydi.

Qozon amallari uchun alohida ruxsat kerak (`batches.manage`: oshpaz, menejer va egada bor). Ruxsat bo'lmasa, **Qozon** yorlig'i ko'rinmaydi.
Qozonni panelda yoki Mini App'da ham boshqarish mumkin ([panel qo'llanmasi](hamkor-panel.md#6-stop-list-va-qozon)).

## 13. Muammolar va yechimlar

| Muammo | Nima qilish kerak |
|---|---|
| Ulash kodi qabul qilinmayapti | Kod 10 daqiqada eskiradi va bir marta ishlaydi. Panelda **Yangi kod** yarating. Kodni to'g'ri yozganingizni tekshiring |
| PIN ekranida ism yo'q | Panelda oshpazga PIN o'rnatilmagan. **Xodimlar** bo'limida o'rnating, planshetda **Yangilash** ni bosing |
| **PIN noto'g'ri** | Qayta tering. 5 marta xato qilsangiz, bir daqiqa kutish kerak |
| Planshet o'zi ulash ekraniga qaytdi | Panelda bu qurilma o'chirilgan. Yangi kod yarating va qayta ulang |
| Linux kompyuterda ekran har ochilganda ulash kodini so'raydi va «Saqlangan ulanishni o'qib bo'lmadi» deb yozadi | Kompyuterda parollar ombori (`gnome-keyring` yoki KWallet) ishlamayapti. Uni o'rnating, kompyuterga oddiy foydalanuvchi sifatida kiring va yangi kod bilan qayta ulang. Android va Windows'da bu muammo yo'q |
| Yangi chipta kelmayapti | Tepadagi aloqa belgisini va qizil **Aloqa yo'q** lentasini tekshiring. Wi-Fi'ni tekshiring |
| "Server javobini o'qib bo'lmadi" yoki "N ta chiptani o'qib bo'lmadi" | Aloqa bor, lekin ilova eski bo'lishi mumkin. Menejerga ayting: ilovani yangilash kerak. Qolgan chiptalar bilan ishlashda davom eting |
| Ovoz yo'q | Karnay belgisi yoqilganini tekshiring (**Ovozni yoqish**), **Sozlamalar** da **Yangi buyurtma ovozi** yoqilganini ko'ring, planshet ovozini balandlating |
| Taom qatorini bosganda belgi qaytyapti | Server rad etdi yoki aloqa uzildi. Taom ostidagi sababni o'qing, aloqa tiklangach qayta bosing |
| Hamma taomni bosdim, buyurtma doskada qoldi | Zal buyurtmasi stol yopilguncha qoladi. **Buyurtma tayyor** tugmasini bosing |
| Yaqinda tayyor taomni qaytarib bo'lmadi | Buyurtma allaqachon tayyor bo'lib ketgan. Menejerga ayting |
| Chiptalar juda kichik yoki juda katta | **Sozlamalar** → **Shrift** |
| Ekran o'chib qolyapti | Planshet yoki kompyuter sozlamalarida uyqu rejimini o'chiring |
| Sex tanlash ekrani chiqmayapti | Filialda ekranda ko'rsatiladigan sex yo'q. Ega panelda **Kassa sozlamalari** → **Sexlar** da sex qo'shib, **Ekranda** belgisini yoqadi |
| Doskada kerakli taomlar yo'q | Boshqa sex tanlangan bo'lishi mumkin: **Sex: …** tugmasi. Onlayn buyurtmalar uchun **Umumiy** ham belgilang |
| Taom noto'g'ri sexda chiqyapti | Ega panelda **Kassa sozlamalari** → **Yo'naltirish** da taomni to'g'ri sexga biriktiradi |
| Oldindan buyurtma kerak vaqtda doskaga chiqmadi | **Sozlamalar** da **Osh/Mangal: oldindan chiqish** vaqtini oshiring. Qatorda "hali yuborilmagan" yozuvi bo'lsa, ofitsiant yubormagan |
| Oldindan buyurtmani "tayyor" deb bo'lmayapti | Qator hali yuborilmagan yoki doskaga o'tmagan. Muddati kelganda bosing |
| Mangalda six soni noto'g'ri | Six soni buyurtmadagi miqdordan olinadi. Ofitsiant yoki kassir buyurtmadagi sonni tekshirsin |
| **Qozon** yorlig'i yo'q | Sizda qozon ruxsati yo'q. Menejerga ayting |
| Qozon qoldig'i noto'g'ri | **Sanash** bilan haqiqiy qoldiqni kiriting |
| Osh menyuda **tugadi** turibdi, lekin bor | Qozon yopilgan yoki ochilmagan. **Yangi qozon** yoki **Qayta ochish** |
| Qizil **O'CHIRILDI** chipta chiqdi | Buyurtma yoki taom o'chirildi. Pishirmang; pishgan bo'lsa menejerga ayting |
