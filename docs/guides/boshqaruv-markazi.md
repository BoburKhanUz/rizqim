# RIZQIM boshqaruv markazi: jamoa uchun qo'llanma

**Boshqaruv markazi** — RIZQIM xodimlari uchun veb-sahifa (`admin.<domen>`). Kompyuterda ham, telefonda ham ishlaydi.
Platformani boshqarishning hammasi shu yerda: buyurtmalar, ovqatxonalar, obunalar, moliya, mijozlar va shikoyatlar, marketing,
monitoring, tizim sozlamalari va jamoaning o'zi. Ovqatxona egalari uchun alohida hamkor paneli bor ([hamkor-panel.md](hamkor-panel.md)); bu qo'llanma faqat RIZQIM jamoasi uchun.

Qo'llanma oddiy tilda yozilgan. Nimadan boshlash kerak:

- **Yangi xodim**: [Kirish](#kirish), [2FA va tiklash kodlari](#2fa-va-tiklash-kodlari), [Menyu va qidiruv](#menyu-va-qidiruv),
  so'ng o'z rolingizning bo'limi ([Rollar](#rollar) jadvali qaysi rol nimani qilishini aytadi).
- **Birinchi marta ishga tushirayotgan superadmin**: [Birinchi ishga tushirish](#birinchi-ishga-tushirish).
- **Aniq ish kerak** (payoutni tasdiqlash, ovqatxonani to'xtatish, promo-kod...): [Kundalik ishlar](#kundalik-ishlar-qadamma-qadam).

## Mundarija

**Boshlash**

1. [Birinchi ishga tushirish](#birinchi-ishga-tushirish)
2. [Kirish](#kirish)
3. [2FA va tiklash kodlari](#2fa-va-tiklash-kodlari)
4. [Menyu va qidiruv](#menyu-va-qidiruv)

**Kundalik ish**

5. [Bugun (bosh sahifa)](#bugun-bosh-sahifa)
6. [Ko'rsatkichlar](#korsatkichlar)
7. [Buyurtmalar](#buyurtmalar)
8. [Ovqatxonalar](#ovqatxonalar)
9. [POS va obunalar](#pos-va-obunalar)
10. [Moliya](#moliya)
11. [Mijozlar va yordam](#mijozlar-va-yordam)
12. [Marketing](#marketing)
13. [Monitoring](#monitoring)
14. [Integratsiyalar](#integratsiyalar)

**Tizim**

15. [Tizim sozlamalari](#tizim-sozlamalari)
16. [Jamoa](#jamoa)
17. [Rollar](#rollar)
18. [Harakatlar jurnali](#harakatlar-jurnali)
19. [Sessiyalarim](#sessiyalarim)
20. [Excel eksport](#excel-eksport)

**Qo'llanma va ma'lumotnoma**

21. [Kundalik ishlar: qadamma-qadam](#kundalik-ishlar-qadamma-qadam)
22. [Qayta tasdiq (kod) va sabab qachon so'raladi](#qayta-tasdiq-kod-va-sabab-qachon-soraladi)
23. [Telefon raqamlarini kim ko'radi](#telefon-raqamlarini-kim-koradi)
24. [Xatolar va yechimlar](#xatolar-va-yechimlar)

## Birinchi ishga tushirish

Bu bo'lim **serverni yangi ko'targan texnik xodim va birinchi superadmin** uchun. Hozircha jonli ovqatxonalar yo'q, shuning uchun
eski ma'lumotlarni ko'chirish kerak emas: toza boshlaysiz. Server o'zi ([DEPLOY.md](../DEPLOY.md)) tayyor bo'lgach:

1. **Markaz ochiladimi tekshiring.** `docker compose ps` da `platform-admin` "healthy", `https://admin.<domen>/login` ochiladi.
   `backend/.env` da `ADMIN_APP_URL=https://admin.<domen>` yozilgan bo'lishi shart: taklif havolalari shu manzildan yasaladi.
2. **Birinchi superadminni yarating** (serverda, backend konteynerida):

   ```bash
   docker compose exec -u www-data backend php artisan rizqim:admin:create boss@rizqim.uz "Bosh admin"
   ```

   Buyruq `https://admin.<domen>/invite/...` ko'rinishidagi **bir martalik taklif havolasini** chiqaradi (72 soat amal qiladi).
   Uni nusxalab oling: yo'qotsangiz, `rizqim:admin:invite boss@rizqim.uz` yangisini beradi ([OPERATIONS.md](../OPERATIONS.md), 10-bo'lim).
3. **Havolani oching**, parol o'ylab toping (kamida 12 belgi), telefondagi ilovani ulang va **10 ta tiklash kodini** xavfsiz
   joyga saqlang ([2FA va tiklash kodlari](#2fa-va-tiklash-kodlari)). 2FA'ni 24 soat ichida yoqish kerak; o'tib ketsa, 2-bandning
   `invite` buyrug'i yangi havola beradi.
4. **Jamoani taklif qiling.** **Tizim → Jamoa → Xodim taklif qilish**: har bir odam uchun ism, email va rol. Markaz bir martalik
   havola ko'rsatadi — uni xodimga xavfsiz yo'l bilan yuboring ([Jamoa](#jamoa)). Kamida **ikkita** moliya vakolatli odam bo'lsin
   (masalan Moliya roli va superadmin): 1 000 000 so'mdan katta payout va tuzatishni ikki kishi tasdiqlaydi
   ([Moliya](#moliya)). Ikkinchi superadmin ham tavsiya etiladi: yagona superadmin telefonini yo'qotsa, uni faqat serverdagi
   `rizqim:admin:reset-2fa` buyrug'i tiklaydi.
5. **Rollarni ko'zdan kechiring.** **Tizim → Rollar**: standart ruxsatlar ko'pincha yetadi; o'zgartirsangiz, har katak jurnalga yoziladi.
6. **Hududlarni yoqing.** **Tizim → Tizim sozlamalari → Viloyatlar va tumanlar**: ishlaydigan viloyat va tumanlarni "Faol" qiling
   (usiz Mini App manzil formasi va ovqatxona arizasi bo'sh qoladi).
7. **Sozlamalar.** Xuddi shu sahifada: **Sozlamalar** (SMS usuli: haqiqiy SMS uchun "Eskiz" va uning email/paroli) va **To'lov
   tizimlari** (Payme/Click kalitlari, rejim). Kalitlar yozilgach hech qachon to'liq ko'rinmaydi ([Tizim sozlamalari](#tizim-sozlamalari)).
8. **Birinchi ovqatxona.** **Ovqatxonalar → Ulash jarayoni → Ariza qo'shish**, so'ng arizani "Ovqatxonani yaratish" bilan ovqatxonaga
   aylantiring ([Ovqatxonalar](#ovqatxonalar)). Kamida bitta faol ovqatxona bo'lmaguncha Mini App'ga hech kim kira olmaydi.
9. **Monitoring.** **Monitoring → Holat** "Hammasi joyida" ekanini tekshiring. Ogohlantirishlar Telegramda navbatchi guruhiga
   (`TELEGRAM_OPS_CHAT_ID`), u yo'q bo'lsa superadmin chatiga (`TELEGRAM_SUPERADMIN_CHAT_ID`) boradi; **Monitoring → Qoidalar**
   tepasida "Telegram guruhi sozlanmagan" yozuvi chiqmasligi kerak ([Monitoring](#monitoring)).
10. **Sinab ko'ring.** Chiqing va qayta kiring (parol + kod); "Sessiyalarim" sahifasida faqat o'zingizning qurilmangiz borligini tekshiring.

Bundan keyingi kundalik ishlar: [Kundalik ishlar](#kundalik-ishlar-qadamma-qadam).

## Kirish

1. Superadministrator sizni jamoaga qo'shadi va **taklif havolasini** yuboradi (u 72 soat amal qiladi va faqat bir marta ishlaydi).
2. Havolani oching, **parol** o'ylab toping: kamida 12 ta belgi, emailingizni parol qilmang. Parol menejerida saqlang.
3. Telefoningizdagi ilovani ulang ([2FA va tiklash kodlari](#2fa-va-tiklash-kodlari)). Tayyor: markazga kirasiz.

Keyingi safar: **email** va **parol** yozing, so'ng telefon ilovasidagi **6 xonali kod**ni kiriting.

- Parolni yoki kodni 5 marta xato yozsangiz, kirish 15 daqiqaga to'xtatiladi. Shoshilmang, 15 daqiqadan keyin qayta urining.
- Hisobingiz o'chirilgan bo'lsa, shu haqida yozuv chiqadi: superadministratorga murojaat qiling.
- Markaz sizni **60 daqiqa** hech narsa qilmasangiz chiqarib yuboradi. 55-daqiqada "Seans tugayapti" oynasi chiqadi:
  davom etish uchun **Men shu yerdaman** tugmasini bosing. Har holda bir kirish 12 soatdan ortiq davom etmaydi.

## 2FA va tiklash kodlari

2 bosqichli tasdiqlash (2FA) — parolingiz o'g'irlansa ham hisobingizni himoya qiladi. Kod telefondagi ilovada har 30 soniyada yangilanadi.

**Birinchi marta o'rnatish** (taklifdan keyin yoki 2FA tiklanganda):

1. Telefonga **Google Authenticator** (yoki Microsoft Authenticator, 2FAS) o'rnating.
2. Ilovada "QR kod skanerlash"ni tanlang va ekrandagi rasmni skanerlang. Skaner ishlamasa, rasm tagidagi **kalit**ni
   "Nusxa olish" bilan ko'chirib, ilovaga qo'lda kiriting.
3. Ilovada chiqqan 6 xonali kodni yozing va **Yoqish**ni bosing.
4. Ekranda **10 ta tiklash kodi** chiqadi. **Ular faqat shu yerda, bir marta ko'rsatiladi.** "Nusxa olish" yoki
   "Yuklab olish (.txt)" tugmasi bilan xavfsiz joyga saqlang, so'ng **"Saqlab qo'ydim"** belgisini qo'ying: usiz davom etib bo'lmaydi.

**Telefoningiz yo'q bo'lsa:** kod so'ralganda "Tiklash kodidan foydalanish"ni bosing va saqlab qo'ygan kodlardan birini yozing.
Har bir tiklash kodi **bir marta** ishlaydi. Kodlar kam qolsa, "Sessiyalarim" sahifasida yangilarini yarating (eskilari ishlamay qoladi).

**Telefon yo'qolgan va tiklash kodlari ham yo'q bo'lsa:** superadministrator "Jamoa" sahifasida sizning 2FA'ngizni tiklaydi
([Jamoa](#jamoa)); keyingi kirishda 2FA'ni qaytadan o'rnatasiz. Superadministratorning o'zi uchun: [OPERATIONS.md](../OPERATIONS.md).

**Muhim amallar oldidan qayta tasdiqlash.** Pulga, jamoaga, rollarga, sozlamalarga yoki mijoz hisobiga tegadigan amal oldidan markaz yana
**6 xonali kod** so'raydi ("Amalni tasdiqlang" oynasi). Kodni kiriting: tasdiq 10 daqiqa amal qiladi, shu vaqt ichida qayta so'ralmaydi.
"Bekor qilish"ni bossangiz, amal bajarilmaydi. Qaysi amal kod so'rashi: [jadval](#qayta-tasdiq-kod-va-sabab-qachon-soraladi). Kod bir marta ishlaydi: hozirgina ishlatilgan kodni qayta yozsangiz, markaz keyingisini kutishni so'raydi.

## Menyu va qidiruv

Chapdagi menyuda bo'limlar shu tartibda: **Bugun, Ko'rsatkichlar, Buyurtmalar, Ovqatxonalar, POS va obunalar, Moliya, Mijozlar va yordam,
Marketing, Monitoring, Integratsiyalar**, so'ng **Tizim** guruhi (**Tizim sozlamalari**, Jamoa, Rollar, Harakatlar jurnali, Sessiyalarim).
Sizga ruxsat berilmagan bo'limlar menyuda ko'rinmaydi: bu normal, ruxsatni rolingiz belgilaydi ([Rollar](#rollar)). "Sessiyalarim"
hammada bor. Kirgandan keyin sizni rolingiz ruxsat bergan birinchi bo'lim kutib oladi (odatda **Bugun**).

Telefonda menyu yuqoridagi tugma ("Menyuni ochish") ortida turadi. Hamma sahifa 360 px kenglikdagi telefonda ham ishlaydi; jadvallar
o'z ichida yon tomonga suriladi.

**Qidirish.** Klaviaturada **Ctrl+K** (Mac'da **Cmd+K**) yoki yuqoridagi "Qidirish" tugmasi. Kamida 2 ta belgi yozing:
ovqatxona (nomi, STIR, telefon), buyurtma (kodi), mijoz yoki xodim, jamoa a'zosi topiladi. Natijalar turi bo'yicha guruhlangan; yuqoriga/pastga
strelkalar bilan yuring, **Enter** bilan oching, **Esc** bilan yoping. Telefon raqamlari qisman yashiriladi
(`+998 90 *** ** 67`); to'liq ko'rish huquqi alohida beriladi va har bir ochilish jurnalga yoziladi ([Telefon raqamlarini kim ko'radi](#telefon-raqamlarini-kim-koradi)).

**Har sahifada umumiy narsalar:** yuqorida sahifa nomi, "Yangilash" tugmasi (ko'p sahifa o'zi ham yangilanadi), ro'yxatlarda filtrlar va
"Tozalash", yuklanmasa — "Qayta urinish". Holatlar rang bilan birga **so'z bilan** yoziladi.

## Bugun (bosh sahifa)

**Bugun** — markazga kirganda birinchi ochiladigan sahifa (Operator, Superadmin va "Faqat ko'rish" uchun). Sahifa har
**30 soniyada** o'zi yangilanadi; tepadagi "Yangilandi: 14:30" yozuvi oxirgi yangilanish vaqti, yonidagi tugma — darhol
yangilash. Hamma vaqtlar **Toshkent vaqti** bo'yicha, kun 00:00 da boshlanadi.

**To'rtta raqam:**

- **GMV (marketpleys)** — bugun RIZQIM boti, Mini App va ilova orqali kelgan buyurtmalar summasi (mijoz to'laydigan summa:
  taom, yetkazish va xizmat haqi, chegirmadan keyin). Bekor qilingan, rad etilgan va puli qaytarilgan buyurtmalar
  kirmaydi. Kassada (zalda, olib ketishda) sotilgani GMV ga kirmaydi — u "Kanallar bo'yicha"da alohida ko'rinadi.
- **Buyurtmalar** — shu buyurtmalar soni; ostida bugun nechtasi bekor qilingani.
- **Komissiya** — shu buyurtmalardan RIZQIM oladigan komissiya (buyurtma yetkazilganda ovqatxona hisobidan yechiladi).
- **Obuna daromadi** — bugun to'langan POS obuna hisob-fakturalari.

Har bir raqam ostida ikki solishtirma bor: **kechagi shu vaqtga** va **o'tgan haftaning shu kuniga** (xuddi shu soat va
daqiqagacha). Masalan "25% ko'p (9 960 000 so'm)" — kecha shu vaqtgacha 9 960 000 so'm edi, bugun 25% ko'p. Strelka va rang
faqat yordam beradi, asosiysi — so'z. "Kecha jami" — kechagi butun kun.

**Diqqat talab qiladi** — hozir kimdir qarashi kerak bo'lgan narsalar, eng shoshilinchi tepada ("Shoshilinch", "Diqqat",
"Ma'lumot"). Har bir qatorni bossangiz, o'sha narsaning kartasi ochiladi. Siz faqat rolingiz ko'radigan narsalarni
ko'rasiz (masalan Operator pul yechish so'rovlarini ko'rmaydi). Bu yerda nimalar chiqadi:

| Qator | Nima qilish kerak |
|---|---|
| Yiqilgan fiskal cheklar | Sotuv bo'lgan, lekin soliq cheki chiqmagan. Ovqatxona bilan bog'laning, xatoni (MXIK, soliq sozlamalari) tuzating |
| Hisob-kitob xatolari | Yetkazilgan buyurtmaning puli yoki komissiyasi hisobga yozilmadi. Tizim o'zi qayta urinadi; "Avtomatik urinishlar tugadi" bo'lsa — texnik jamoaga xabar bering |
| Ish vaqtida oflayn kassalar | Filial ochiq, lekin kassa yoki oshxona ekrani 10 daqiqadan beri aloqada emas. Ovqatxonaga qo'ng'iroq qiling: internet, quvvat |
| Muddati o'tgan hisob-fakturalar | POS obunasi to'lanmagan. Moliya eslatma yuboradi |
| Pul yechish so'rovlari | Ovqatxona pul yechishni so'radi, Moliya tasdig'ini kutmoqda |
| To'lanmay qolgan to'ldirishlar | Ovqatxona Click/Payme orqali balansni to'ldirishni boshlagan, lekin to'lov kelmagan. Kabinetni tekshiring |
| Qabul qilinmagan, kechikkan, kuryersiz buyurtmalar; tizim ogohlantirishlari | Buyurtmalar va Monitoring bo'limlari (ularning qo'llanmasiga qarang) |

Hech narsa bo'lmasa — **"Hammasi joyida"**.

**Kanallar bo'yicha** — bugungi buyurtmalar kanallarga bo'lib: Telegram, mobil ilova, kassa (GMV ga kirmaydi).
**Viloyatlar bo'yicha** — bugungi buyurtmalar va har viloyatdagi faol ovqatxonalar soni (filiali shu viloyatda bo'lgan
ishlayotgan ovqatxonalar). Viloyati ko'rsatilmagan filiallar "Viloyat ko'rsatilmagan" qatorida.

| Ko'ringan | Sabab va nima qilish kerak |
|---|---|
| "Bugungi raqamlar yuklanmadi" | Internet yoki server. "Qayta urinish"ni bosing; "Diqqat talab qiladi" ro'yxati baribir ko'rinadi |
| "Yig'ma jadvallar ... da yangilangan" | Raqamlarni tez beradigan fon vazifasi kechikmoqda. Raqamlar to'g'ri, faqat sahifa sekinroq. Davom etsa — Monitoring |
| Kassa yopiq bo'lsa ham "oflayn" deb chiqdi | Filialning ish vaqti kiritilmagan (unda kun bo'yi ochiq hisoblanadi). Ovqatxona filial ish vaqtini to'g'rilasin |

## Ko'rsatkichlar

Menyuda **Ko'rsatkichlar** ("Bugun"dan keyin). Moliya yoki ovqatxonalarni ko'rish ruxsati bor har bir xodim ochadi. Faqat o'qish:
bu yerda hech narsa o'zgartirilmaydi. Tepada **Davr** (6, 12 yoki 24 oy) va **Yangilash** tugmasi: raqamlar 5 daqiqaga saqlanadi,
tugma yangisini so'raydi. Oylar Toshkent vaqti bilan.

### Kartalar

- **MRR** — hozir pul to'layotgan ovqatxonalarning oylik obuna daromadi yig'indisi (yillik obuna 12 ga bo'linadi, tarifdan ortiq
  kassa va oshxona ekranlari qo'shiladi). **Sinov davridagi ovqatxonalar kirmaydi.** Pastida: oy boshiga nisbatan o'zgarish.
- **ARR** — MRR × 12. **Pullik ovqatxonalar** (sinovdagilar soni pastida). **ARPA** — bitta pullik ovqatxonadan o'rtacha.
- **Faol ovqatxonalar** — holati "Faol" bo'lganlar. **Ketish** — oxirgi **tugagan** oy uchun (qaysi oy ekani yozilgan).
- **GMV** va **Komissiya** — shu oyning, Telegram va mobil buyurtmalardan. Agar oyning ayrim kunlari hisoblanmagan bo'lsa,
  kartada "raqamlar to'liq emas" deyiladi.

### Grafiklar

Har bir grafikda **Grafik / Jadval** tugmasi bor: jadval aynan o'sha raqamlarni to'liq beradi. Ustunning ustiga olib boring
yoki unga Tab bilan o'ting — pastda o'qib beriladi. Och rangli ustun — tugamagan oy.

- **MRR oylar bo'yicha** — har oy oxiridagi MRR.
- **MRR harakati** — chiziqdan yuqorida **yangi** (to'lay boshlagan) va **o'sish** (tarif yoki qurilma ko'paygan), pastda
  **kamayish** va **ketgan**. Jadvalda: boshida + yangi + o'sish − kamayish − ketgan = oxirida.
- **Ketish (churn)** — oy boshida faol ovqatxonalardan nechtasi ketdi: faolligini yo'qotgan (to'xtatilgan, yopilgan) yoki obunasi
  tugagan. Jadvalda har ikki sabab alohida va daromaddan foiz ham bor.
- **Faol ovqatxonalar** — oy oxiridagi soni. **GMV** va **Komissiya** — oylar bo'yicha; hisoblanmagan kunlari bor oylar och rangda
  va grafik tagida yozib qo'yiladi. Eski oylar uchun texnik xodim `php artisan admin:stats-backfill` ishga tushiradi.

### Arizadan faol ovqatxonagacha

Oxirgi N oyda tushgan arizalar: har bosqichga (ariza, tekshiruv, menyu, qurilmalar, o'qitish, sinov, faol) nechtasi yetgani, ulushi va
arizalar o'sha bosqichda **o'rtacha (median) necha kun** turgani. Tepada **konversiya** (faol bo'lganlar ulushi) va "ariza → faol"
medianasi. "Ariza va ovqatxonalar" havolasi ulash jarayoniga olib boradi. Median bosqichdan chiqqan arizalar bo'yicha hisoblanadi:
"hali chiqqan yo'q" — hozircha hech kim o'tmagan.

## Buyurtmalar

**Buyurtmalar** — hamma ovqatxonalarning RIZQIM boti, Mini App va ilova orqali kelgan buyurtmalari (kassada sotilgani
bu yerda yo'q). Ro'yxat **jonli**: "Jonli: yangi buyurtmalar darhol chiqadi" yozuvi bo'lsa, yangi buyurtma va har bir
o'zgarish darhol ko'rinadi; "Har 15 soniyada yangilanadi" bo'lsa, jonli ulanish yo'q, sahifa o'zi tez-tez yangilanadi.
Ikkalasida ham ishlayverasiz.

**Kechikish (SLA)** har bir buyurtmada so'z bilan yoziladi:

- "Qabul qilish: 2 daqiqa qoldi" — vaqtida;
- "Tayyorlash: 4 daqiqa qoldi, kechikish xavfi" — chegaraning 80% o'tdi (sariq);
- "Qabul qilish: 12 daqiqa kechikdi" — chegaradan o'tdi (qizil).

Uch chegara bor: **qabul qilish** (buyurtma kelgandan ovqatxona qabul qilguncha, standart 5 daqiqa; onlayn to'lovda —
to'langandan keyin), **tayyorlash** (qabul qilingandan tayyor bo'lguncha, 30 daqiqa), **yetkazish** (kuryer yo'lga
chiqqandan topshirguncha, 45 daqiqa). Chegaralar sahifa tepasida yozilgan. Ularni faqat "Tizim sozlamalari" ruxsati bor
xodim (superadmin) **SLA chegaralari** tugmasi bilan o'zgartiradi — sabab yoziladi va 6 xonali kod so'raladi.

**Filtrlar:** yuqoridagi tugmalar — holat guruhi (Faol, Yangi, Oshxonada, Yo'lda, Yakunlangan, Bekor qilingan, qavs
ichida soni) va kechikish (Hammasi, Kechikkan, Kechikish xavfi). Qidiruv — buyurtma kodi (`#AB12CD`), uuid boshi yoki
mijoz telefonining oxirgi raqamlari. "Qo'shimcha filtrlar"da ovqatxona va viloyat (ID), sanalar. Sana tanlanmasa,
"Yakunlangan" va "Bekor qilingan" soni faqat bugungi.

**Buyurtma kartasi** (qatorni bosing): holatlar tarixi (kim qilgani: ovqatxona xodimi, "RIZQIM jamoasi" yoki "Tizim"),
SLA vaqt chizig'i (har bosqich necha daqiqa davom etgani va kechikdimi), taomlar va summalar, to'lov va qaytarishlar,
mijoz, kuryer, ovqatxona (telefonlar yarim yashirin — to'liq raqamni faqat ruxsati bor xodim ko'radi va bu jurnalga
yoziladi), fiskal chek, jurnal.

**Aralashish** (Operator, Superadmin). Har bir amalda **sabab** yoziladi va 6 xonali kod so'raladi (10 daqiqada bir marta):

- **Bekor qilish** — sababni tanlang (masalan "Do'kon yopiq"), mijoz uchun izoh yozish mumkin. Keshbek bilan to'langan
  bo'lsa, pul mijoz balansiga o'zi qaytadi. Click, Payme yoki naqd bo'lsa, kartada "Pul qo'lda qaytarilishi kerak"
  chiqadi — keyin **Pulni qaytarishni so'rash**. Ovqatxona chatiga "RIZQIM jamoasi bekor qildi" xabari boradi.
- **Holatni tuzatish** — faqat keyingi ruxsat etilgan holat (masalan oshxona "Tayyor"ni bosishni unutgan bo'lsa).
  Holatni orqaga qaytarib yoki bosqichdan sakrab bo'lmaydi.
- **Ovqatxonaga yozish** — xabar ovqatxonaning buyurtmalar chatiga va menejerlarga Telegram'da boradi.
  **Mijozga yozish** — RIZQIM boti orqali.

**Pulni qaytarish — ikki kishi bilan.** Operator yoki Yordam xodimi yakunlangan buyurtmada **Pulni qaytarishni
so'rash**ni bosadi: summa (qaytarish mumkin bo'lgandan ko'p emas) va sabab. So'rov "Tasdiq kutilmoqda" bo'lib turadi —
uni **boshqa** xodim (Moliya yoki Superadmin) tasdiqlaydi yoki rad etadi; o'zingiz yaratgan so'rovni tasdiqlay olmaysiz.
Tasdiq kutayotgan so'rovlar Moliya xodimiga Buyurtmalar sahifasining tepasida ko'rinadi.

Tasdiqlanganda: yetkazilgan buyurtma to'liq qaytarilsa, holati "Qaytarildi" bo'ladi, ovqatxona komissiyasi va unga
o'tkazilgan pul qaytariladi, keshbek mijozga o'zi qaytadi. **Click, Payme va naqd pulni tasdiqlagan xodim o'zi qaytaradi**
(to'lov tizimi kabinetida yoki naqd) — kartada "... mijozga to'lov tizimi kabinetida yoki naqd qaytariladi" deb yoziladi.
Markaz hozircha to'lov tizimiga o'zi murojaat qilmaydi.

**Ovqatxona sifati (30 kun)** — sahifa pastidagi tugma: har bir ovqatxona uchun buyurtmalar soni, qabul qilish ulushi,
o'rtacha qabul qilish va tayyorlash vaqti, bekor qilish ulushi va reyting. Eng zaifi birinchi.

| Ko'ringan | Sabab va nima qilish kerak |
|---|---|
| "Pulni qaytarishni faqat yakunlangan buyurtma uchun so'rash mumkin" | Avval buyurtmani bekor qiling yoki yetkazilishini kuting |
| "Bu buyurtma bo'yicha qaytarish so'rovi allaqachon tasdiq kutmoqda" | Birinchisi ko'rib chiqilguncha yangisini yaratib bo'lmaydi |
| "O'zingiz yaratgan so'rovni tasdiqlay ... olmaysiz" | Boshqa xodimdan (Moliya) tasdiqlashni so'rang |
| "Mijozning Telegram akkaunti yo'q" | Mijozga bot orqali yozib bo'lmaydi; ovqatxonaga yozing |
| "Buyurtmani «...» holatidan «...» holatiga o'tkazib bo'lmaydi" | Buyurtma shu orada o'zgargan: sahifani yangilang |

## Ovqatxonalar

**Ovqatxonalar** — RIZQIM'ning B2B mijozlari: ularning holati, tarifi, qarzi, arizadan faol ovqatxonagacha yo'li va aloqa tarixi.
Ko'rish uchun "Ovqatxonalarni ko'rish" ruxsati yetadi (Operator, Yordam, Moliya, Savdo); o'zgartirish "Ovqatxonalarni boshqarish"
ruxsatini talab qiladi (Savdo va Superadmin). **Egasi nomidan kirib bo'lmaydi**: ovqatxona sozlamalarini (menyu, filial, xodim)
ega o'zi hamkor panelida qiladi. To'rt yorliq: **Ro'yxat**, **Ulash jarayoni**, **Vazifalar**, **E'lonlar**.

### Ro'yxat va ovqatxona kartasi

Ro'yxatda har ovqatxonaning nomi, **sog'liq bahosi**, tarifi, qarzi va filiallari soni. Filtrlar: holat, viloyat, qarz (qarzi bor /
limitdan oshgan), tarif, sog'liq (Yaxshi, E'tibor kerak, Ketish xavfi) va tartib ("Xavfdagilar birinchi" ketish xavfi bor
ovqatxonalarni tepaga chiqaradi). Qidiruvda nom, STIR yoki **to'liq** telefon yoziladi (yarim yashirin raqam bilan topilmaydi).
Qatorni bosing — **ovqatxona kartasi** ochiladi (umumiy qidiruv — `Ctrl+K` — ham shu yerga olib keladi).

Kartada yorliqlar:

- **Umumiy** — profil (telefon, STIR, yuridik nom, manzil, reyting), aloqa shaxslari, Telegram holati (buyurtma chati va egasi botga ulanganmi),
  tarif va obuna holati, komissiya stavkalari kanallar bo'yicha, **sog'liq bahosi** va uning sabablari (nega "E'tibor kerak").
- **Filiallar** — filiallar, ish vaqti, yoqilgan/o'chirilgani.
- **Qurilmalar va fiskal** — kassalar va oshxona ekranlari (onlayn/oflayn, versiya) hamda fiskal holat: yoqilganmi, 7 kunda yiqilgan cheklar,
  MXIK'siz taomlar soni.
- **Moliya** — hamyon, qarz (obuna va komissiya qarzi alohida), kredit limiti, hisob-fakturalar, payoutlar, oxirgi yozuvlar.
- **Buyurtmalar** — oxirgi 30 kunlik sifat (qabul qilish ulushi, o'rtacha vaqtlar, bekor qilish) va oxirgi buyurtmalar.
- **Shikoyatlar**, **Tarix** (holat o'zgarishlari va jurnal), **CRM** (izohlar, vazifalar, keyingi aloqa sanasi).

**Amallar** (kartaning tepasida; hammasi **sabab** va **6 xonali kod** so'raydi, "Ovqatxonalarni boshqarish" ruxsati bilan):

| Amal | Nima qiladi |
|---|---|
| **To'xtatish** | Ovqatxona marketpleysdan yashirinadi va Mini App buyurtmalari to'xtaydi. **Kassa ishlashda davom etadi**: to'lov olish va chek chiqarish to'xtamaydi |
| **Faollashtirish** | Ovqatxona yana marketpleysda ko'rinadi va buyurtma qabul qiladi |
| **Kredit limiti** | Ovqatxona RIZQIM'ga ko'pi bilan qancha komissiya qarzi bo'la oladi. Qarz limitdan oshsa, yangi Mini App buyurtmalari to'xtaydi (kassa ishlayveradi) |
| **Ko'rsatish sozlamalari** | "Premium" va "Tavsiya etilgan" belgilari (marketpleysda ko'rinishi) |
| **Telegram havola** | Egasi uchun bir martalik yangi havola; eskisi ishlamay qoladi. Havola egasining tasdiqlangan telefoniga **SMS bilan** boradi va ekranda ko'rinmaydi |
| **Egasiga yozish** | Oddiy matn RIZQIM boti orqali egasining Telegramiga boradi |

Komissiya stavkasini o'zgartirish bu yerda emas — [POS va obunalar](#pos-va-obunalar) bo'limida. Karta tagidagi havolalar ovqatxonaning
buyurtmalariga, to'liq jurnaliga va obunasiga olib boradi.

### Ulash jarayoni (arizadan faol ovqatxonagacha)

Yangi ovqatxona shu yerda yo'l o'tadi. Ariza saytdan, botdan keladi yoki **Ariza qo'shish** bilan qo'lda kiritiladi (telefon bo'yicha;
qo'ng'iroq bilan kelgan mijoz uchun). Bosqichlar: **Ariza → Tekshiruv → Menyu → Qurilmalar → O'qitish → Sinov → Faol**
(yoki **Rad etildi**). Har bosqichda mas'ul xodim, muddat va ro'yxat (checklist) bor. Yuqoridagi filtrlar: Ochiq, Muddati o'tgan,
Meniki, Rad etilgan.

Arizani bosing — kartasi ochiladi:

1. **O'zimga olish** yoki boshqa mas'ulni tanlang; bosqich muddatini belgilang.
2. **Ro'yxat**ni to'ldiring: ba'zi bandlar majburiy, ba'zilari ovqatxonadan o'zi aniqlanadi ("ovqatxonadan aniqlandi", masalan menyu
   kiritilgani). Majburiy bandlar bajarilmaguncha keyingi bosqichga o'tib bo'lmaydi; bosqichdan faqat oldinga yuriladi (orqaga —
   "Orqaga" tugmasi bilan).
3. Tekshiruv tugagach **Ovqatxonani yaratish** (kod va sabab so'raydi; "Menyu" va undan keyingi bosqichlar uchun ovqatxona yaratilgan bo'lishi shart): ovqatxona, egasi (SMS bilan kirish telefoni) va asosiy filial
   yaratiladi; viloyat, tuman, manzil, koordinata va yetkazish radiusini yozing. Ovqatxona "Faol" bosqichigacha marketpleysda ko'rinmaydi.
   Chiqqan **Telegram havolani darhol egasiga yuboring** — u faqat shu oynada ko'rinadi.
4. Menyu, qurilmalar va o'qitish tugagach bosqichlarni ketma-ket ko'chiring (har ko'chirishda izoh yozish mumkin); **Sinov** — ovqatxona tarifning sinov davrida ishlaydi.
5. **Faol** bosqichi ("Ovqatxonani ishga tushirish", kod so'raydi): ovqatxona faollashadi va marketpleysda ko'rinadi.

**Rad etish** (sabab va kod bilan) arizani yopadi; ovqatxona yaratilgan bo'lsa, u ham "Rad etilgan" bo'ladi. **Qayta ochish** — rad etilgan
arizani qaytaradi.

### Vazifalar va CRM

Har ovqatxona va arizada **izohlar** (qo'ng'iroq natijasi, kelishuv), **vazifalar** (muddat bilan: "5-kuni qo'ng'iroq qilish") va
**keyingi aloqa sanasi** bor. **Vazifalar** yorlig'i hammasini bir joyga yig'adi: Meniki / Hammasi; "Muddati o'tgan", "Bugun",
"Kelgusi". Vazifani bajargach "Bajarildi" bosing.

### E'lonlar

Ovqatxona egalariga ommaviy Telegram xabar: texnik ishlar, yangiliklar. **Kimga**: barcha, faqat faol yoki tanlangan ovqatxonalar.
**Ko'rib chiqish** nechta egaga borishini va nechtasida Telegram yo'qligini aytadi. Yuborish **sabab** va **kod** so'raydi; yuborish tugmasi faqat ko'rib chiqishdan keyin ishlaydi; kuniga
cheklangan sondagina e'lon yuboriladi (sahifada "Bugun yuborilgan: N / limit" turadi). Qabul qiluvchilar soni ko'rib chiqishdan keyin
o'zgarsa, markaz qayta tekshirishni so'raydi.

| Ko'ringan | Sabab va nima qilish kerak |
|---|---|
| "«…» bosqichining ro'yxati tugallanmagan: …" | Xabarda nomi yozilgan majburiy bandlarni belgilang (yoki ovqatxonadan aniqlanishini kuting), so'ng qayta ko'chiring |
| "«Menyu» bosqichi uchun avval ovqatxonani yarating" | Arizada **Ovqatxonani yaratish**ni bosing |
| "Qabul qiluvchilar soni o'zgardi" | Yangi sonni tekshirib, qayta yuboring |
| Egasi botga ulanmagan | "Telegram havola" bilan yangi havola yuboring |

## POS va obunalar

**POS va obunalar** — ovqatxonalarning tarifi va obunasi, hisob-fakturalar, kassalar va oshxona ekranlari, smenalar, fiskal cheklar va
oflayn sinxron xatolari. Yetti yorliq: **Obunalar**, **Tariflar**, **Hisob-fakturalar**, **Qurilmalar**, **Smenalar**, **Fiskal cheklar**,
**Sinxron xatolari**. Ochish uchun "Obunalar", "Moliyani ko'rish" yoki "Qurilmalarni ko'rish" ruxsatlaridan biri yetadi; yorliq
qaysi ruxsat bor bo'lsa, o'shalar uchun ko'rinadi.

### Obunalar va tariflar

Obuna holatlari: **Sinov davri** (to'lov yo'q), **Faol**, **To'lov muddati o'tgan**, **Cheklangan (qarz)**, **Tugagan**. Ro'yxat sinovi tez
tugayotganlarni ko'rsatadi ("Sinovi 3 kunda tugaydi"). Qatorni bossangiz, ovqatxonaning **obunasi** ochiladi (ovqatxona kartasidan ham
shu yerga o'tiladi): tarif, davr, sinov va joriy davr tugashi, kutilayotgan o'zgarish, **tarif imkoniyatlari** (qaysi biri yoqilgan),
qurilmalar (tarifga kiritilgan va ortiqcha soni) hamda qarz va komissiya.

Amallar ("Obunalar, tariflar va hisob-fakturalar" ruxsati — Savdo, Moliya, Superadmin; hammasi **sabab** va **kod** so'raydi):

- **Tarif biriktirish.** Ovqatxonada obuna yo'q bo'lsa, shu bilan obuna boshlanadi (birinchisi tarifning sinov davri bilan). Yuqori tarifga
  hoziroq (oyning qolgan qismiga proporsional) yoki oy oxirida o'tiladi; **past tarifga doim oy oxirida**.
- **Davrni o'zgartirish** — oylik ↔ yillik. Sinovda darhol; oylikdan yillikka keyingi oydan, yillikdan oylikka yillik muddat tugaganda.
- **Sinovni uzaytirish** — 1 dan 90 kungacha; shu oylar uchun hisob-faktura yozilmaydi.
- **Obunani bekor qilish** — odatda davr oxirida tugaydi; "Hoziroq tugatish" tanlansa, davom etayotgan oy hisoblanmaydi. **Kassa to'lov olish va
  chek chiqarishda davom etadi.**
- **Komissiyani o'zgartirish** — kanal bo'yicha ovqatxonaning o'z stavkasi (RIZQIM kuryeri, o'z yetkazishi, olib ketish, zal, agregator).
  Bo'sh qoldirsangiz, kanal odatdagi stavkasini oladi. **Faqat yangi buyurtmalarga ta'sir qiladi**: buyurtma yaratilgandagi stavkani saqlaydi.

**Tariflar** yorlig'i — tariflar katalogi: oylik va yillik narx, tarifga kiritilgan kassa va oshxona ekrani soni, ortiqcha qurilma narxi,
sinov kunlari, imkoniyatlar. Tarifni tahrirlash faqat "Tizim sozlamalari" ruxsati bilan (Superadmin): yangi narx shu o'zgarishdan keyin
tugaydigan oylarning hisob-fakturalariga o'tadi, **yozilgan hisob-fakturalar o'zgarmaydi**. Eski tariflar faqat ko'rish uchun.

### Hisob-fakturalar

Har oy obunasi bor ovqatxonaga hisob-faktura yoziladi (sinov davrida yozilmaydi). Holat: **To'lanmagan**, **To'langan**, **Bekor qilingan**;
"Muddati o'tgan" filtri kechikkanlarni ko'rsatadi. Tepada to'lanmagan hisob-fakturalar jami turadi. Hisob-faktura kartasida qatorlar
(tarif, ortiqcha qurilmalar), to'langan va qolgan qism hamda **eslatmalar tarixi** (1, 3 va 7-kun eslatmasi, "Cheklandi") bor.
Eslatmalarni tizim o'zi yuboradi.

- **To'langan deb belgilash** ("Moliya: qo'lda tuzatish" ruxsati, kod va sabab): ovqatxona hisob raqamiga yoki naqd to'laganda. Pulning o'zi
  balansni to'ldirish sifatida yoziladi va eng eski hisob-fakturalarni yopadi; eslatmalar to'xtaydi.
- **Bekor qilish** (shu ruxsat, kod va sabab): hisob-faktura daromadga kirmaydi, to'lanmagan qismi qarzdan ayriladi. **Summa katta bo'lsa,
  bekor qilishni boshqa xodim tasdiqlaydi** ("Bekor qilish so'ralgan" holati) — [Moliya](#moliya)dagi ikki kishilik qoidaga qarang.

### Qurilmalar, smenalar, fiskal cheklar, sinxron xatolari

Bular ovqatxona kassalari bilan ishlovchi **Operator va Savdo** uchun ("Qurilmalarni ko'rish" ruxsati). O'zgartirish — "Ovqatxonalarni boshqarish"
ruxsati bilan (Savdo, Superadmin), hammasi sabab va kod so'raydi.

- **Qurilmalar.** Kassa va oshxona ekranlari: onlayn/oflayn, hech ulanmagan, o'chirilgan, eski versiya. Qurilma kartasida platforma, ilova
  versiyasi (eng yangisi bilan solishtirib), oxirgi aloqa, **oflayn navbat** (yuborilmagan amallar soni, eng eskisi) va soat farqi
  (soat noto'g'ri bo'lsa ogohlantiradi). **Qurilmani o'chirish** — u va undagi barcha sessiyalar darhol to'xtaydi; ega terminalni yangi kod
  bilan qayta ulaydi. Yo'qolgan yoki o'g'irlangan planshet uchun shuni qiling.
- **Smenalar.** Kassa smenalari: ochiq, majburan yopilgan, **kassa farqi** bor (kam yoki ortiq). Karta: kim ochdi/yopdi, boshlang'ich,
  hisobdagi va sanalgan naqd, farq, sanalgan pullar, Z-hisobot, kassaga kirim-chiqim. Faqat ko'rish.
- **Fiskal cheklar.** Soliq xizmatiga yuborilmagan ("Yiqilgan", "Kutilmoqda") cheklar va provayderlar bo'yicha xatolar soni. Chek kartasida
  sabab ("MXIK topilmadi", "STIR noto'g'ri"). Sababni (ko'pincha ovqatxonaning soliq sozlamalari) tuzatgach **Qayta yuborish**. Payme/Click
  o'zi ro'yxatdan o'tkazadigan cheklarni bu yerdan qayta yuborib bo'lmaydi.
- **Sinxron xatolari.** Internetsiz ishlagan kassa serverga yuborgan, lekin qabul qilinmagan amallar. Ochsangiz kassa yuborgan ma'lumot ko'rinadi
  (telefon, karta, PIN `***` bilan almashtirilgan). Kerakli ishni qo'lda bajargach **Hal qilindi** deb belgilang (qabul qilinmagan amal hal
  qilinsa, kassa smenasi yopilishi mumkin bo'ladi); hech narsa kerak bo'lmasa **Yopish**.

| Ko'ringan | Sabab va nima qilish kerak |
|---|---|
| "Komissiya qarzi kredit limitidan oshgan: Mini App buyurtmalari yopiq" | Ovqatxona qarzini to'lashi kerak ([Moliya](#moliya) → Balans va qarzlar) yoki Ovqatxonalar kartasida limitni oshiring. **Obuna qarzi buyurtmalarni to'xtatmaydi** |
| "Sozlamalar yopiq" (obunada) | Obuna to'lanmagan: yangi sozlamalar yopilgan, kassa ishlayveradi. Hisob-fakturani to'lashi kerak |
| "Past tarifga faqat oy oxirida o'tiladi" | Bu qoida; hoziroq o'tkazib bo'lmaydi |

## Moliya

**Moliya** — ovqatxonalarga pul o'tkazish (payout), ularning balans va qarzlari, balans to'ldirishlar, hisob-kitob xatolari, qo'lda
tuzatishlar va RIZQIM daromadi. Ochish uchun "Moliyani ko'rish" ruxsati kerak (Moliya, Superadmin, "Faqat ko'rish"). Payoutni
tasdiqlash — "Pul yechishni tasdiqlash" ruxsati, tuzatish va hisob-kitobni qayta yozish — "Moliya: qo'lda tuzatish va hisobotlar"
ruxsati (Moliya va Superadmin). **Har bir pul amali sabab va 6 xonali kod so'raydi** va jurnalga yoziladi.

Tepada qisqa raqamlar: tasdiq kutayotgan payoutlar, ikkinchi tasdiq kutayotganlar, tuzatishlar, hisob-kitob xatolari, kredit limitidan
oshganlar, to'lanmay qolgan to'ldirishlar. Pastda **"Ikkinchi tasdiq kutilmoqda"** navbati — boshqa xodim tasdiqlashi kerak bo'lgan
so'rovlar. Oltita yorliq: **Payoutlar**, **To'ldirishlar**, **Hisob-kitob xatolari**, **Balans va qarzlar**, **Qo'lda tuzatish**, **Oylik hisobot**.

### Ikki kishilik qoida (four-eyes)

Katta pulni bir kishi yolg'iz o'tkaza olmaydi. **1 000 000 so'm va undan katta** payout yoki qo'lda tuzatishni **ikki xodim** tasdiqlaydi
(chegara serverda sozlanadi, sahifada yozilgan turadi). Qoida:

- Birinchi tasdiqni bergan (yoki tuzatishni yaratgan) xodim ikkinchisini bera olmaydi. **Superadmin ham istisno emas.**
- Kichik bo'lib yuborib aylanib o'tib bo'lmaydi: oxirgi 24 soatda shu xodim yoki shu ovqatxona bo'yicha bir odamning so'zi bilan qo'llangan
  tuzatishlar yangisi bilan qo'shib hisoblanadi.
- Katta hisob-fakturani bekor qilish ([POS va obunalar](#pos-va-obunalar)) ham xuddi shunday ikkinchi tasdiq kutadi.
- Pul qaytarish ([Buyurtmalar](#buyurtmalar)) ham ikki kishilik: biri so'raydi, boshqasi tasdiqlaydi.

### Payoutlar

Ovqatxona egasi hamkor panelida pul yechishni so'raydi. Ro'yxatda holat: **Tasdiq kutilmoqda**, **Ikkinchi tasdiq**, **O'tkazilgan**,
**Rad etilgan**. Payout kartasida:

- **Hisob**: hisoblangan summa, **ushlangan qarz** va **o'tkaziladigan summa**. Ovqatxonaning komissiya qarzi payoutdan avval ushlab
  qolinadi, shuning uchun o'tkaziladigan summa so'ralganidan kam bo'lishi mumkin. "Hozir tasdiqlansa shunday bo'ladi" ko'rinishi qarz
  o'zgarsa, o'zgaradi.
- Ovqatxonaning hozirgi hamyoni, qarzi (obuna va komissiya alohida) va kredit limiti.
- Egasining izohi, karta (raqam yarim yashirin) va avvalgi tasdiqlar.

Qadamlar: [Kundalik ishlar](#payoutni-tasdiqlash-va-ikki-kishilik-qoida).

### To'ldirishlar

Ovqatxonalar Click/Payme orqali balansni to'ldirishi. Filtr: holat (Kutilmoqda, To'langan, Bekor qilingan), to'lov tizimi, "faqat
to'lanmay qolganlar". **To'lanmay qoldi** — 30 daqiqadan beri kutilmoqda: ega to'lov sahifasini yopgan yoki to'lov tizimi javobi kelmagan.
To'lov tizimi kabinetini tekshiring: pul tushgan bo'lsa, tizim javobi keladi va o'zi yopiladi; tushmagan bo'lsa, egasiga qayta urinishni ayting.
Bu ro'yxat faqat ko'rish uchun.

### Hisob-kitob xatolari

Yetkazilgan har buyurtmada komissiya va ovqatxonaga o'tkazma hisobga yoziladi. Yozilmasa, shu yerda qoladi: tizim **har 10 daqiqada o'zi
qayta urinadi** (cheklangan marta). "Avtomatik urinishlar to'xtadi" bo'lsa, sababini (qatordagi xato) bartaraf etib, **Qayta urinish**ni
bosing (sabab va kod bilan). Buyurtma shu orada yetkazilgan holatdan chiqqan bo'lsa, hisob-kitob kerak emas deb yopiladi.
Takrorlansa — texnik jamoaga xabar bering.

### Balans va qarzlar

Har ovqatxonaning hamyoni, qarzi (**obuna qarzi** va **komissiya qarzi** alohida), kredit limiti va kutilayotgan payouti; tepada jami
raqamlar. Filtr: Limitdan oshgan, Qarzdor, Obuna qarzi bor, Hamyonda puli bor; qidiruv — ovqatxona nomi. **Muhim:** kredit limitiga
**faqat komissiya qarzi** kiradi. Limitdan oshsa, yangi Mini App buyurtmalari yopiladi ("Buyurtmalar yopiq"), kassa ishlayveradi;
obuna qarzi buyurtmalarni to'xtatmaydi. **Daftar** tugmasi ovqatxonaning qarz va hamyon yozuvlarini ko'rsatadi: komissiya, to'lov,
o'tkazma, payout, qaytarish, tuzatish, obuna; yozuvdagi buyurtma yoki hisob-fakturaga o'tish mumkin.

### Qo'lda tuzatish

Hisobda xato bo'lsa yoki pul boshqa yo'l bilan kelganda (hisob raqamiga to'lov, naqd) ovqatxona qarzi yoki hamyoni qo'lda o'zgartiriladi.
Turlari: **Qarzga qo'shish**, **Komissiya qarzini kamaytirish**, **To'lov qabul qilindi**, **Hamyonga qo'shish**, **Hamyondan ayirish**.
Summa, sabab va ixtiyoriy **hujjat** (PDF yoki rasm, 10 MB gacha; yopiq saqlanadi) kerak. Chegaradan kichik tuzatish darhol qo'llanadi;
katta tuzatish "Tasdiq kutilmoqda" bo'ladi va balans **tasdiqlanguncha o'zgarmaydi**. Qadamlar: [Kundalik ishlar](#qolda-tuzatish-yaratish-va-tasdiqlash).

### Oylik hisobot

Tanlangan oy (Toshkent vaqti) uchun **RIZQIM daromadi**: komissiya (shu oyda yetkazilgan va qaytarilmagan buyurtmalardan; qisman
qaytarilganlari ayirilgan), kanallar bo'yicha komissiya, obuna hisob-fakturalari (hisoblangan, to'langan, qolgan) va ovqatxonalar kesimi.
**Excel yuklab olish** ("Eksport" ruxsati bilan) hisobotni faylga yozadi.

| Ko'ringan | Sabab va nima qilish kerak |
|---|---|
| "Siz birinchi tasdiqni bergansiz: ikkinchi tasdiqni boshqa xodim beradi" | Ikki kishilik qoida: boshqa xodimdan (Moliya yoki Superadmin) so'rang |
| "Siz yaratgansiz: tasdiqni boshqa xodim beradi" (tuzatishda) | Xuddi shunday |
| "Summani so'mda yozing: 0 dan katta, ko'pi bilan 2 xonali kasr" | Masalan `150000` yoki `150000,50` |
| "Fayl 10 MB dan katta" | Hujjatni kichraytiring yoki rasm sifatida saqlang |
| Payout summasi so'ralganidan kam | Qarz ushlangan: kartada "Ushlangan qarz" yozilgan |
| "Yana yozilmadi: …" (hisob-kitobda) | Qatordagi xatoni bartaraf eting yoki texnik jamoaga ayting |

## Mijozlar va yordam

**Mijozlar va yordam** — mijozlar, kuryerlar va ovqatxona xodimlari, ularning shikoyatlari va shubhali holatlar. Uch yorliq:
**Foydalanuvchilar** ("Foydalanuvchilarni ko'rish" ruxsati), **Shikoyatlar** va **Shubhali holatlar** ("Murojaatlarni
ko'rish"). O'zgartirish — "Murojaatlarni hal qilish" ruxsati bilan (standart: Yordam va Superadmin). "Faqat ko'rish" roli
hammasini ko'radi, hech narsani o'zgartira olmaydi. RIZQIM jamoasi a'zolari bu yerda yo'q — ular Tizim → Jamoa'da.

### Foydalanuvchilar va karta

Tepadagi tugmalar — rol (Hammasi, Mijozlar, Kuryerlar, Ovqatxona xodimlari), yonida holat (Faol, Bloklangan). Qidiruv —
ism yoki telefon. **Telefon bo'yicha faqat to'liq raqam topiladi** (`+998 90 123 45 67`, `90 123 45 67`): yarim yashirin
raqamni raqamma-raqam topib bo'lmasin. To'liq raqamlarni ko'rish ruxsati bor xodim istalgan 4 ta raqam bilan topadi va u
ko'rgan har bir raqam jurnalga yoziladi.

Qatorni bosing — **foydalanuvchi kartasi** ochiladi (umumiy qidiruvdagi foydalanuvchi ham shu yerga olib keladi):

- **Umumiy** — telefon, Telegram, kim taklif qilgan; buyurtmalar soni, yetkazilganlar summasi, o'rtacha chek, toifa
  (Doimiy mijoz (VIP), Qaytgan, Yangi); xodim bo'lsa — ovqatxonasi, filiali, roli, SMS bilan kirishi, kassa PIN; shu
  telefon yoki Telegramdagi boshqa hisoblar (bir odam mijoz ham, xodim ham bo'lishi mumkin).
- **Buyurtmalar** — oxirgi 20 ta (bosilsa buyurtma kartasi) va pul qaytarish so'rovlari.
- **Keshbek** — balans va harakatlar: buyurtma uchun to'lov, qaytarilgani, do'st taklifi bonusi.
- **Shikoyatlar**, **Sessiyalar** (faol tokenlar, oxirgi foydalanish, qurilmalar), **Jurnal** (shu hisob bo'yicha amallar).

**Bloklash** — firibgarlik yoki suiiste'molda. Sabab yoziladi va 6 xonali kod so'raladi. Bloklangan odam hech qayerga
kira olmaydi (Mini App, ilova, hamkor paneli, kassa PIN), buyurtma bera olmaydi, hozirgi barcha sessiyalari darhol
yopiladi; kartada kim, qachon, nima uchun bloklagani yoziladi. **Ovqatxona egasi bloklansa, ovqatxona ishlashda davom
etadi** — ovqatxonani to'xtatish Ovqatxonalar bo'limida. **Blokdan chiqarish** — sabab va kod bilan, odam qaytadan
kira oladi.

**Sessiyalarni yopish** — telefon yo'qolganda yoki o'g'irlanganda: Mini App, ilova va kassadagi PIN sessiyalari yopiladi
(sabab va kod bilan), odam qaytadan kirishi kerak bo'ladi. Kassaning o'zi ulanganicha qoladi.

### Shikoyatlar qutisi

Har shikoyatda ikki holat bor. **Yordam holati**: Yangi → Ko'rib chiqilmoqda → Javob berildi → Hal qilindi. Yonida
ovqatxona bilan yozishma holati ("Ovqatxona javobini kutmoqda", "Ovqatxona javob berdi", "Mijoz RIZQIMga yubordi").
**Ochiq** (Yangi va Ko'rib chiqilmoqda) birinchi chiqadi, eng uzoq kutgani tepada. Qizil **"Kutmoqda"** — 4 soatdan ko'p
javobsiz turgan (ovqatxona javob bergan va mijoz qayta yubormagan bo'lsa — yo'q); ular "Bugun → Diqqat talab qiladi"da
ham chiqadi. Filtrlar: holat tugmalari (qavsda soni), "Menga biriktirilgan" / "Hech kimga biriktirilmagan", "Faqat
kutayotganlar", buyurtma kodi yoki matndagi so'z.

Shikoyatni bosing:

- **Olish** — shikoyat sizga biriktiriladi va "Ko'rib chiqilmoqda" bo'ladi. **Mas'ul xodim** ro'yxatidan boshqa
  hamkasbga berish mumkin.
- **Javob yozish** — mijozga RIZQIM boti orqali Telegramda boradi. **Tayyor javob**ni tanlasangiz, matn o'zi yoziladi,
  uni o'zgartirib yuborish mumkin. Yuborilgach shikoyat "Javob berildi" bo'ladi va javob "RIZQIM javoblari"da qoladi.
  Mijozning Telegrami bo'lmasa, tugma yo'q — hal qilish izohini u Mini App'da ko'radi.
- **Hal qilish** — qanday hal qilinganini yozing. Izoh mijozga Mini App'dagi shikoyatlar ro'yxatida ko'rinadi.
- **Qayta ochish** — hal qilingan shikoyat bo'yicha ish davom etsa.

**Tayyor javoblar** tugmasi — tez-tez yoziladigan matnlar (masalan "Kechikish uchun uzr"). Qo'shish, tahrirlash va
o'chirish — "Murojaatlarni hal qilish" ruxsati bilan; o'zgartirish oldin yuborilgan javoblarga ta'sir qilmaydi.

### Shubhali holatlar

Faqat ko'rish uchun ro'yxat — hech narsa avtomatik qilinmaydi. Har qator nima uchun shu yerda ekanini oddiy so'z bilan
aytadi va foydalanuvchi kartasiga olib boradi:

- **Ko'p bekor qilish** — 7 kunda 3 va undan ko'p buyurtmasi mijoz tufayli bekor qilingan (o'zi bekor qilgan, olib
  ketmagan).
- **Promo-kodni ko'p hisobdan ishlatish** — bitta promo-kod bir xil telefon raqamli yoki bitta Telegramga bog'langan bir
  nechta hisobdan ishlatilgan (hamma hisoblar ko'rsatiladi).
- **O'rtachadan ko'p qaytarish** — 30 kunda qaytarish so'rovlari o'rtacha qaytarishdan 3 baravar ko'p (va 100 000
  so'mdan ortiq).

Kerak bo'lsa, kartada tekshirib, **Bloklash** (sabab bilan).

| Ko'ringan | Sabab va nima qilish kerak |
|---|---|
| "Mijozning Telegram akkaunti yo'q: javobni yuborib bo'lmaydi" | Mijoz botga ulanmagan. Hal qilish izohini yozing — u Mini App'da ko'radi |
| "Telegram bot sozlanmagan" | Texnik jamoaga ayting (bot tokeni) |
| "Shikoyat hal qilingan. Javob yozish uchun avval uni qayta oching" | **Qayta ochish**ni bosing |
| "Hisob allaqachon bloklangan" | Boshqa xodim sizdan oldin bloklagan: sahifani yangilang |
| "RIZQIM jamoasi a'zosi bu yerda boshqarilmaydi" | Jamoa a'zosini Tizim → Jamoa'da o'chiring |
| "Bu xodimga shikoyat biriktirib bo'lmaydi" | U xodimda shikoyatlarni ko'rish ruxsati yo'q yoki u o'chirilgan |
| Mijoz: "Hisobingiz bloklangan..." | Mijoz bloklangan. Blokdan chiqarish faqat sabab bilan |

## Marketing

**Marketing** — Mini App bosh sahifasidagi bannerlar, promo-kodlar va kategoriyalar. Uch yorliq: **Bannerlar va aksiyalar**,
**Promo-kodlar**, **Kategoriyalar**. Ko'rish uchun "Marketingni ko'rish" ruxsati yetadi; qo'shish, o'zgartirish va tartiblash
"Marketingni boshqarish" ruxsatini talab qiladi (standart: faqat Superadmin; boshqa rolga Rollar sahifasidan beriladi).
**Har o'zgarish sabab so'raydi** (3–500 belgi) va jurnalga yoziladi; **o'chirish** bundan tashqari 2FA kodini ham so'raydi.
Telefonda ham ishlaydi.

### Bannerlar va aksiyalar

Banner — Mini App bosh sahifasida chiqadigan rasm. Ro'yxatda har bannerning rasmi, sarlavhasi, holati va bosilganda nima
ochilishi (ovqatxona yoki taom) turadi. Holat so'z bilan yoziladi:

| Holat | Ma'nosi |
|---|---|
| **Ko'rinmoqda** | Yoqilgan va jadvali ichida: mijozlar hozir ko'radi |
| **Rejalashtirilgan** | Yoqilgan, lekin boshlanish vaqti hali kelmagan |
| **Tugagan** | Tugash vaqti o'tib ketgan |
| **O'chiq** | Qo'lda o'chirib qo'yilgan |

- **Yangi banner**: sarlavha, rasm (JPG, PNG yoki WebP, **4 MB gacha**), ixtiyoriy ravishda ovqatxona va taom (taom shu
  ovqatxonaniki bo'lishi kerak), jadval (Toshkent vaqti; bo'sh qoldirsangiz yoqilgan paytda doim ko'rinadi) va sabab. Banner
  ro'yxat oxiriga tushadi.
- **Yoqish/o'chirish** — tugma (sabab so'raydi). **Tahrirlash** — kalamcha tugmasi; faqat o'zgargan maydonlar yuboriladi.
- **Tartib**: strelkalar bannerni yuqoriga/pastga suradi. O'zgarish **darhol saqlanmaydi** — "Tartibni saqlash" bosilgach sabab
  so'raladi va butun qator birdan saqlanadi. Holat bo'yicha filtr yoqilgan paytda strelkalar yashirin (qator to'liq ko'rinmasa
  tartiblab bo'lmaydi). Tartibni saqlayotganda sahifa eskirgan bo'lsa ("Ro'yxat eskirgan...") yangilab qayta urining —
  hamkasbingizning tartibi bosilib ketmaydi.
- **Mini App'da ko'rinishi** — hozir bosh sahifada chiqadigan bannerlar, o'sha tartibda (mijoz ko'radigan so'rovning o'zi).
  Bo'sh bo'lsa "Hozir bosh sahifada banner ko'rinmayapti".
- **O'chirib tashlash** (chiqindi savati belgisi) — banner va rasmi butunlay o'chadi; jurnalda maydonlari qoladi.

### Promo-kodlar

Ro'yxatda kod, nima berishi (masalan "10 % (eng ko'pi 8 000 so'm)" yoki "5 000 so'm"), minimal buyurtma, ovqatxona, muddat va
**ishlatilish**: necha marta (limit bilan: "12 / 100"), nechta turli mijoz, jami berilgan chegirma va shu buyurtmalarning
jami summasi. Hisobga **bekor qilingan, rad etilgan va qaytarilgan buyurtmalar kirmaydi** (limit ham ularni sanamaydi).
Holat: **Faol**, **O'chirilgan**, **Muddati tugagan**, **Limit tugagan**. Yuqorida qidiruv va holat tugmalari bor.
**Batafsil** — kodning qoidalari va oxirgi 10 buyurtmasi (sana bosilsa buyurtma kartasi ochiladi).

- **Yangi promo-kod**: kod (lotin harf, raqam, `-` va `_`, 3–50 belgi; bosh harfda saqlanadi), turi (**Foiz** yoki **Belgilangan
  summa**), qiymat, foizda ixtiyoriy "Eng ko'p chegirma", minimal buyurtma, umumiy limit va bir mijoz uchun limit (bo'sh —
  cheklanmagan), ovqatxona (bo'sh — hammasi), muddat. **Qoidalar buyurtma berishdagi bilan bir xil**: foiz 100 dan oshmaydi,
  belgilangan summa butun so'm bo'ladi.
- **Chegirmasi qulflanadi.** Kodni birorta buyurtma ishlatgan bo'lsa (bekor qilingani ham), uning **kodi, turi, qiymati,
  "eng ko'p chegirma"si va ovqatxonasi o'zgarmaydi** — hisobotlar va buyurtmalar eski chegirmaga tayanadi. Oynada shu
  maydonlar o'chirib qo'yilgan va sababi yozilgan. Boshqa chegirma kerak bo'lsa: **yangi promo-kod yarating, eskisini
  o'chirib qo'ying**. Limitlar, minimal summa, muddat va yoqish/o'chirish esa har doim o'zgaradi.
- **Yoqish/o'chirib qo'yish** darhol ta'sir qiladi (har buyurtmada tekshiriladi); berilgan buyurtmalar o'z chegirmasini saqlaydi.
- **O'chirib tashlash** faqat hech bir buyurtmada ishlatilmagan kodda ko'rinadi (aks holda uni faqat o'chirib qo'yasiz).

### Kategoriyalar

Mini App'dagi kategoriyalar, ularning ketma-ketligi bilan. Har qatorda ikonka, nomi, qaysi kategoriya ichida turgani va
nechta taomi borligi turadi. Kategoriyalar **ikki darajali**: asosiy va uning ichki kategoriyalari (ichki kategoriyasi borini
boshqasining ichiga qo'yib bo'lmaydi).

- **Yashirish** — tugma (sabab so'raydi): kategoriya katalogdan yo'qoladi, **taomlari o'chmaydi**. Qaytarish ham shu tugma.
- **Tartib** — banner kabi: strelkalar, so'ng "Tartibni saqlash" (butun ro'yxat birdan saqlanadi, POS menyusi ham yangilanadi).
- **Yangi kategoriya / tahrirlash**: nomi, asosiy kategoriya, ikonka (JPG, PNG yoki WebP, 4 MB gacha; "Ikonkani olib
  tashlash" bilan olinadi).
- **O'chirish** faqat taomi ham, ichki kategoriyasi ham yo'q kategoriyada (2FA kodi bilan). Boshqa holda uni yashiring.

### Marketing xatolari

| Ko'ringan | Sabab va nima qilish kerak |
|---|---|
| "Rasm 4 MB dan katta bo'lmasligi kerak" / "Rasm JPG, PNG yoki WebP bo'lishi kerak" | Rasmni kichraytiring yoki boshqa formatda saqlang |
| "Tugash vaqti boshlanish vaqtidan keyin bo'lishi kerak" | Jadvalni to'g'rilang |
| "Mahsulot tanlangan ovqatxonaga tegishli emas" | Taomni shu ovqatxonaning menyusidan tanlang yoki ovqatxonani olib tashlang |
| "Bu promo-koddan buyurtma berilgan, shuning uchun uning chegirmasini o'zgartirib bo'lmaydi" | Yangi promo-kod yarating, eskisini o'chirib qo'ying |
| "Bunday promo-kod allaqachon bor" | Kodlar katta-kichik harfga qaramay takrorlanmaydi: boshqa kod tanlang |
| "Foiz 0 dan katta va 100 dan oshmasligi kerak" / "Belgilangan chegirma butun so'm bo'lishi kerak" | Qiymatni turiga mos yozing |
| "Ro'yxat eskirgan: sahifani yangilang va qayta urining" | Hamkasbingiz ro'yxatni o'zgartirgan. Sahifani yangilang, tartibni qaytadan qiling |
| "Kategoriyada mahsulotlar yoki ichki kategoriyalar bor" | O'chirib bo'lmaydi: kategoriyani yashiring |
| "Siz ko'rishingiz mumkin, o'zgartirishga ruxsat yo'q" | Rolingizda "Marketingni boshqarish" ruxsati yo'q. Superadmin Rollar sahifasidan beradi |

## Monitoring

**Monitoring** — tizim sog'lig'ini kuzatish joyi (Operator, Superadmin va "Faqat ko'rish" uchun; o'zgartirish faqat Operator va
Superadmin uchun). To'rtta bo'lim bor: **Holat**, **Ogohlantirishlar**, **Yiqilgan vazifalar**, **Qoidalar**. Hammasi har
**30 soniyada** o'zi yangilanadi; yangi ogohlantirish bo'lsa darhol ko'rinadi. Telefonda ham ishlaydi.

### Holat

Tepada umumiy hukm: **"Hammasi joyida"**, **"E'tibor kerak"** yoki **"Tizim ishlamayapti"**. Pastda har qism alohida katakda,
holati so'z bilan yozilgan (rang faqat yordam beradi):

| Katak | Nimani anglatadi |
|---|---|
| Ma'lumotlar bazasi, Redis | Ishlayaptimi va qancha tez javob beradi. Biri "Ishlamayapti" bo'lsa — ilova ishlay olmaydi, darhol texnik jamoaga xabar bering |
| Navbatlar | Fon vazifalari navbati: nechta vazifa kutmoqda va eng eskisi qancha vaqtdan beri. "To'planib qolgan" — ishchi (queue worker) ishlamayapti yoki ortiqcha yuk |
| Scheduler | Rejalashtirilgan ishlarni yurgizadigan jarayon. "Eskirgan" yoki "Ma'lumot yo'q" bo'lsa — buyurtmalarni avtomatik bekor qilish, hisob-fakturalar va ogohlantirishlar ishlamayapti |
| Real-time (Reverb) | Jonli yangilanish. Ishlamasa ham sahifalar ishlaydi, faqat jonli o'rniga har 30 soniyada yangilanadi. "O'chirilgan" — real-time sozlanmagan |
| Zaxira nusxa | Bazaning oxirgi nusxasi qachon olingan. "Eskirgan" (26 soatdan ko'p) yoki "Ma'lumot yo'q" — zaxira xizmatini tekshiring (`docker compose logs backup`) |
| Yiqilgan vazifalar | Nechta fon vazifasi xato bilan to'xtagan. "Ko'rish" tugmasi ro'yxatga olib boradi |
| Ogohlantirishlar | Ochiq va qabul qilingan ogohlantirishlar soni, kritiklari alohida |
| Ilova | Versiya, commit va muhit (xato haqida yozganda shuni ayting) |
| Sentry | Xatolar yuborilyaptimi; "Sentry'da ochish" — loyiha sahifasi |

### Ogohlantirishlar

Qoidalar muammo topsa, shu yerda ogohlantirish ochiladi va Telegramga xabar ketadi (navbatchi guruhiga, u bo'lmasa superadmin chatiga). Har ogohlantirishda: darajasi
(**Kritik**, **Diqqat**), holati (**Ochiq**, **Qabul qilingan**, **Hal bo'lgan**), qachon boshlangani, necha marta tekshiruvda
ko'ringani va "Tafsilot" (sonlar va nomlar).

1. Ochiq ogohlantirishni ko'rdingiz va shug'ullanyapsiz: **"Qabul qilish"** (ixtiyoriy izoh yozing). Sizning ismingiz
   yoziladi, ogohlantirish "Bugun" ro'yxatidan tushadi, lekin hal bo'lguncha ochiq turadi.
2. Muammo tugagach: **"Hal qilish"**. Muammo hali bo'lsa, keyingi daqiqada yangi ogohlantirish ochiladi.
3. Muammo o'zi tugasa (masalan navbat bo'shasa), ogohlantirish **o'zi yopiladi** va guruhga "Hal qilindi" yoziladi.

Tepadagi tugmalar: **Faol** (ochiq va qabul qilingan), **Ochiq**, **Qabul qilingan**, **Hal bo'lgan** (tarix: kim yoki o'zi
yopgani) va **Hammasi**; "Qoida turi" bilan bitta turni ajratish mumkin.

| Ogohlantirish | Nima qilish kerak |
|---|---|
| Yiqilgan vazifalar ko'payishi | "Yiqilgan vazifalar" bo'limida bir xil xatoni toping (ko'pincha Telegram yoki fiskal xizmat javob bermayapti). Sabab tuzalgach "Qayta urinish" |
| Navbat to'planishi | Ishchi to'xtagan yoki sekin. Texnik jamoaga: `docker compose ps`, `docker compose logs queue` |
| Ish vaqtida oflayn kassa | Ovqatxonaga qo'ng'iroq qiling: internet, quvvat, ilova ochiqmi. Ovqatxonaning ismi va kassa nomi ogohlantirishda yozilgan. Kun bo'yi ochiq ovqatxonada yarim tundan keyin ham ogohlantirish o'zi yopilmaydi. Juda ko'p kassa uzilsa (100 dan ortiq) har biriga alohida emas, "N ta kassa aloqada emas" degan bitta umumiy ogohlantirish chiqadi |
| Fiskal cheklar yiqilishi | Ovqatxona soliq sozlamalari (MXIK, STIR) yoki soliq xizmati. Ovqatxona kartasidan tekshiring |
| Hisob-kitob xatolari | Yetkazilgan buyurtma puli hisobga yozilmadi. Tizim o'zi qayta urinadi; to'xtab qolsa texnik jamoaga `php artisan orders:retry-settlements` kerak |
| Zaxira nusxa eskirgan | Zaxira xizmatini tekshiring (`docker compose logs backup`); tashqi nusxa (S3) xato bersa ham shu ogohlantirish chiqadi. Kerak bo'lsa darhol qo'lda oling (`docs/OPERATIONS.md`, 4-bo'lim) |
| Scheduler to'xtagan | Texnik jamoaga darhol: `docker compose up -d scheduler` |

### Yiqilgan vazifalar

Fon vazifalari (Telegram xabari, hisobot, fiskal yuborish...) xato bilan to'xtagan bo'lsa, shu yerda ko'rinadi: vazifa nomi,
navbat, qachon yiqilgani va xatoning birinchi qatori. Qatorni bossangiz **to'liq xato matni** va **vazifa ma'lumotlari** ochiladi.
Parollar, tokenlar, kodlar va telefon raqamlari yashirilgan (`[redacted]`, `[Filtered]`): ularni bu yerda hech kim ko'rmaydi.
Vazifa ma'lumotlari (masalan Telegram xabarining matni, mijoz ismi va manzili) va xatoning to'liq matni faqat **Monitoring:
fon vazifalari va ogohlantirishlar** ruxsati yoki **to'liq telefon raqamlarini ko'rish** ruxsati borlarga ochiladi. "Faqat
ko'rish" roli vazifa nomini, navbatini, vaqtini va xatoning birinchi qatorini ko'radi, qolgani yashirin deb yoziladi.

- **Qayta urinish** — sababi tuzalgan bo'lsa. Vazifa navbatga qaytadi va ro'yxatdan chiqadi; yana yiqilsa qaytib keladi.
- **O'chirish** — vazifa kerak emas (masalan, o'chirilgan chatga xabar). Bu amal 2FA kodini va **sababni** so'raydi,
  qaytarib bo'lmaydi va jurnalga yoziladi.
- Boshqa xodim shu vazifani allaqachon qayta urinsa yoki o'chirsa: "Bu vazifa topilmadi..." deb chiqadi.

### Qoidalar

Yetti qoida: har biri yoqiladi/o'chiriladi, Telegram'ga yuborish yoqiladi/o'chiriladi va chegarasi o'zgartiriladi (masalan navbat
500 emas 800 ta bo'lganda ogohlantirsin). Har maydon ostida standart qiymat yozilgan. O'zgartirgach **"Saqlash"** — 2FA
kodi va **sabab** so'raladi, o'zgarish jurnalga yoziladi. Qoida o'chirilsa, uning ochiq ogohlantirishlari yopiladi.
Tepada "Telegram guruhi sozlanmagan" deb yozilgan bo'lsa — texnik jamoa `TELEGRAM_OPS_CHAT_ID` (yoki `TELEGRAM_SUPERADMIN_CHAT_ID`) ni kiritsin.

| Ko'ringan | Sabab va nima qilish kerak |
|---|---|
| "Holat yuklanmadi" | Internet yoki server. "Qayta urinish"ni bosing; boshqa bo'limlar baribir ishlaydi |
| "Bu ogohlantirish allaqachon qabul qilingan yoki hal bo'lgan" | Boshqa xodim sizdan oldin bosdi. Ro'yxat yangilandi |
| "Sabab kamida 3 belgi bo'lsin" | O'chirish va qoidani o'zgartirish uchun sabab yozing |
| "Siz ko'rishingiz mumkin, o'zgartirishga ruxsat yo'q" | Rolingizda "Monitoring: fon vazifalari va ogohlantirishlar" ruxsati yo'q. Superadmin Rollar sahifasidan beradi |

## Integratsiyalar

**Integratsiyalar** — ovqatxonalarning tashqi tizimlarga ulanishi (iiko, Alipos, Yandex Eats, Uzum Tezkor, RIZQIM Partner
API). Ulagichlar **5-bosqichda** qo'shiladi; hozir sahifa tayyor, lekin ro'yxatlar bo'sh va buni aytib turadi. Operator va
«Faqat ko'rish» ko'radi; to'xtatish va qayta yoqish — «Integratsiyalarni boshqarish» ruxsati (standartda Superadmin).

- **Ulanishlar.** Har ovqatxona (yoki filial) ulanishi: tizim, yo'nalish (RIZQIM → joyning POS'i yoki Agregator → Rizqim
  POS), rejim (Sinov / Ishchi), holat va **sog'liq bahosi** (80 va yuqori — «Yaxshi», 50–79 — «E'tibor kerak», 50 dan past —
  «Uzilgan»), oxirgi muvaffaqiyatli so'rov, bugungi xatolar. Kartada kalitlar (faqat nomi va oxirgi 4 belgisi), sozlamalar,
  oxirgi hodisalar va menyu xaritasi soni. **To'xtatish** (sabab va kod bilan) — ulanish orqali hech narsa yuborilmaydi;
  buyurtma baribir RIZQIM xodim ekranida va Telegramda chiqadi. **Qayta yoqish** oldingi holatga qaytaradi.
- **Hodisalar jurnali.** Tashqi tizimga har so'rov va undan kelgan har webhook: turi, holati (Kutmoqda, Yuborildi,
  Tasdiqlandi, Xato, O'lik), HTTP kodi, urinishlar. Hodisani ochsangiz so'rov va javob tanasi ko'rinadi — maxfiy maydonlar
  `[redacted]`, telefon raqamlari qisman yashirilgan.
- **Menyu xaritasi.** Tashqi taom ↔ RIZQIM taomi bog'lanishlari 5-bosqichda shu yerda bo'ladi.
- **Qotib qolganlar.** Tashqi POS'ga yuborilgan, lekin 2 daqiqada tasdiqlanmagan ochiq buyurtmalar. Ular «Bugun → Diqqat
  talab qiladi»da ham chiqadi. Buyurtma kodini bossangiz buyurtma kartasi ochiladi: ovqatxonaga qo'ng'iroq qiling yoki
  buyurtmani u yerdan bekor qiling.

## Tizim sozlamalari

**Tizim → Tizim sozlamalari** (sahifa sarlavhasi «Tizim») — platformaning umumiy sozlamalari. Faqat «Tizim sozlamalari»
ruxsati bor xodim ochadi (standartda Superadmin). **Har o'zgarish sabab so'raydi va ilovadagi 6 xonali kodni** (oxirgi 10
daqiqada kiritilmagan bo'lsa); hammasi harakatlar jurnaliga yoziladi. To'rtta bo'lim bor:

- **Sozlamalar.** SMS: yuborish usuli («Log» — SMS ketmaydi, kod faqat server jurnaliga yoziladi; «Eskiz» — haqiqiy SMS),
  Eskiz email, jo'natuvchi nomi va Eskiz paroli. Faqat o'zgargan maydonlar saqlanadi; bo'sh qoldirilgan maydon serverdagi
  qiymatga yoki standartga qaytadi. Eskizga o'tish uchun email va parol kiritilgan bo'lishi kerak. Telegram va Umumiy
  qatorlari serverdagi `.env` faylidan — ular faqat ko'rsatiladi, o'zgartirish uchun texnik jamoaga murojaat qiling.
- **To'lov tizimlari.** Payme, Click, Uzum Bank, Paynet: «Faol» belgisi va rejim (Sinov / Ishchi), kalitlar. Har kalitning
  yonida qayerdan olingani yoziladi: «Shu yerda saqlangan» yoki «Server sozlamasidan (.env)». **«Faol» o'chirilsa**, to'lov tizimi Mini App'da
  mijozga taklif qilinmaydi va u orqali yangi to'lov boshlab bo'lmaydi; allaqachon boshlangan to'lovlar esa odatdagidek yakunlanadi
  (to'lov tizimining javobi qabul qilinaveradi). **Rejim** to'lov tizimi sinov yoki ishchi manzil va kalitlarini ishlatishini belgilaydi:
  haqiqiy to'lovlar uchun «Ishchi», sinov uchun «Sinov». Rejimni almashtirishdan oldin tegishli kalitlar kiritilganini tekshiring.
- **Viloyatlar va tumanlar.** Mini App manzil formasi, filiallar va arizalar shu ro'yxatdan tanlaydi. Qo'shish, nomini
  o'zgartirish, yoqish/o'chirish va tartib («Tartibni o'zgartirish» → yuqoriga/pastga → «Tartibni saqlash»). Filial, mijoz
  manzili yoki ariza ishlatayotgan viloyat/tuman **o'chirilmaydi** — uni «Faol emas» qiling. Tumanlari bor viloyat ham
  o'chirilmaydi: avval tumanlarini o'chiring.
- **Funksiyalar.** Kodda e'lon qilingan funksiyalar (feature flag): tavsifi, standart qiymati va nechta ovqatxonada
  yoqilgani. Pastda ovqatxonalar ro'yxati: har funksiyani bitta ovqatxona uchun yoqish yoki o'chirish, «Standartga
  qaytarish» — ovqatxonaning o'z qiymatlarini o'chirish.

Pastda boshqa tizim sahifalariga havolalar: SLA chegaralari (Buyurtmalar sahifasidagi «SLA sozlamalari»), tariflar
katalogi (POS va obunalar → Tariflar), Jamoa, Rollar, Harakatlar jurnali.

**Maxfiy kalitlar** (parol, merchant kaliti, bot tokeni) hech qachon to'liq ko'rinmaydi: faqat «Kiritilgan, oxiri ••••1234».
Kalitni almashtirish — alohida «Almashtirish» tugmasi: yangi qiymat bir marta yuboriladi, jurnalga qiymatning o'zi
yozilmaydi. «O'chirish» shu yerda saqlangan qiymatni o'chiradi va serverdagi qiymat ishlay boshlaydi.

| Xabar | Nima qilish kerak |
|---|---|
| "Eskiz uchun avval email va parolni kiriting" | Avval Eskiz paroli va emailini kiriting, keyin usulni «Eskiz»ga o'tkazing |
| "SMS Eskiz orqali yuborilmoqda: parolni o'chirishdan oldin…" | Avval SMS usulini «Log»ga o'tkazing |
| "Viloyat ishlatilmoqda…" / "Tuman ishlatilmoqda…" | O'chirib bo'lmaydi: «Faol emas» qiling |
| "Tartib to'liq ro'yxat bo'lishi kerak" | Hamkasbingiz ro'yxatni o'zgartirgan: sahifani yangilang va tartibni qaytadan qiling |

## Jamoa

**Tizim → Jamoa** (faqat jamoani boshqarish huquqi borlarga). Har bir xodim kartasida: roli, 2FA yoqilganmi, holati (Faol,
O'chirilgan, Taklif kutilmoqda), oxirgi kirishi va faol seanslari soni.

- **Xodim taklif qilish.** Ism, email va rolni yozing. Markaz **taklif havolasini** ko'rsatadi: "Havoladan nusxa olish"ni bosing va
  xodimga yuboring. **Havola faqat shu oynada, bir marta ko'rsatiladi**; oynani yopsangiz, u yo'qoladi. Yo'qotsangiz,
  "Taklifni qayta yuborish" yangisini yaratadi (eskisi ishlamaydi).
- **Rolni o'zgartirish.** Yangi rolni tanlang va "Saqlash"ni bosing. Ruxsatlar darhol o'zgaradi.
- **Hisobni o'chirish / yoqish.** O'chirilgan xodim kira olmaydi va uning barcha seanslari tugaydi. Hech narsa yo'qolmaydi: keyin
  qayta yoqish mumkin.
- **2FA ni tiklash.** Xodim telefonini yo'qotganda. Eski telefon va tiklash kodlari ishlamay qoladi, xodim keyingi kirishda 2FA'ni
  qaytadan o'rnatadi. Avval shaxsini tekshiring (qo'ng'iroq qiling).
- **Cheklovlar.** O'zingizni o'zgartira olmaysiz (o'zingizning kartangizda tugma yo'q). Oxirgi superadministratorni
  o'chirib yoki roldan tushirib bo'lmaydi: avval boshqa superadministrator tayinlang.

Har bir amal oldidan markaz oddiy savol beradi ("... hisobini o'chirasizmi?") va 6 xonali kod so'raydi ([2FA](#2fa-va-tiklash-kodlari)).

## Rollar

Oltita rol bor: **Superadmin, Operator, Yordam, Moliya, Savdo / ulash, Faqat ko'rish**. **Tizim → Rollar** jadvalida qatorlar —
ruxsatlar (bo'limlar bo'yicha guruhlangan), ustunlar — rollar. Katakni (uyaning kaliti) bosing: ruxsat berilgan yoki olingan bo'ladi, o'zgarish
**darhol saqlanadi va jurnalga yoziladi**. Telefonda jadval o'z ichida yon tomonga suriladi.

- **Superadmin** ustuni qulflangan: u hamma narsani qila oladi va buni o'zgartirib bo'lmaydi.
- Pul yoki keng huquq beradigan ruxsatni (masalan, payoutni tasdiqlash) berayotganda markaz yana bir marta so'raydi.
- Bir rolning ruxsatlarini o'zgartirsangiz, shu roldagi barcha xodimlarga ta'sir qiladi (ular sahifani yangilaganda yangi menyuni ko'radi).

**Standart ruxsatlar** (boshlang'ich holat; Rollar sahifasida o'zgartiriladi):

| Rol | Nimalarni qila oladi |
|---|---|
| **Superadmin** | Hammasi, shu jumladan tizim sozlamalari, jamoa, marketing, integratsiyalarni boshqarish, to'liq telefon va karta raqamlari |
| **Operator** | Bugun; buyurtmalar (bekor qilish, holatni tuzatish, xabar yozish, pul qaytarishni so'rash); ovqatxonalar, qurilmalar va integratsiyalarni ko'rish; Monitoring (ogohlantirish va yiqilgan vazifalar bilan ishlash) |
| **Yordam** | Foydalanuvchilar va shikoyatlar (javob, bloklash, sessiyalarni yopish); buyurtmalar va ovqatxonalarni ko'rish; pul qaytarishni so'rash |
| **Moliya** | Moliya (payout va qo'lda tuzatish, hisob-kitob); obunalar, tariflarni biriktirish va hisob-fakturalar; pul qaytarishni tasdiqlash; buyurtmalar va ovqatxonalarni ko'rish; Excel eksport |
| **Savdo / ulash** | Ovqatxonalar (ariza, kartaga amallar, CRM, e'lon); obunalar va tarif biriktirish; qurilmalarni ko'rish va boshqarish |
| **Faqat ko'rish** | Hamma bo'limni o'qiydi; hech narsani o'zgartirmaydi, to'liq telefonni ko'rmaydi, eksport qilmaydi |

Ba'zi bo'limlar faqat Superadminda: Marketing (o'zgartirish), Tizim sozlamalari, Jamoa va Rollar, Integratsiyalarni to'xtatish.

## Harakatlar jurnali

**Tizim → Harakatlar jurnali**: kim, qachon, nima qilgani. Yozuvlarni **o'zgartirib ham, o'chirib ham bo'lmaydi**. Yangisi tepada.

- **Filtrlar**: qidiruv (matn, ID, sabab), amal nomining boshi (masalan `admin.team`), sanalar; "Qo'shimcha filtrlar"da —
  xodim, ovqatxona (ID), obyekt turi va ID. Filtrlarni tanlab **Qo'llash**ni bosing; **Tozalash** hammasini olib tashlaydi.
- Ro'yxat 30 tadan yuklanadi: pastdagi **Yana yuklash** tugmasi eskilarini qo'shadi.
- Yozuvni bossangiz, **tafsilot** ochiladi: kim, qaysi qurilma va IP'dan, sababi va **nima o'zgargani** (maydon, oldin, keyin).
  Maxfiy qiymatlar (parol, kalit) serverda yashiriladi va hech qachon ko'rinmaydi.

## Sessiyalarim

**Tizim → Sessiyalarim**: hisobingiz qaysi qurilmalarda ochiq (brauzer, IP, kirgan va oxirgi faollik vaqti). Tanimagan qurilma
ko'rsangiz, "Sessiyani tugatish"ni bosing (6 xonali kod so'raladi), so'ng parolingizni almashtirishni superadministratordan so'rang.
Shu sahifada 2FA holati, qolgan tiklash kodlari soni va **"Yangi tiklash kodlarini yaratish"** tugmasi ham bor.

## Excel eksport

Buyurtmalar, Ovqatxonalar, Ulash jarayoni (arizalar), Moliya (payoutlar, to'ldirishlar, balans va qarzlar), POS (hisob-fakturalar,
qurilmalar) va Harakatlar jurnali ro'yxatlarida **Excel yuklab olish** tugmasi bor. Tugma faqat **Eksport** ruxsati borlarga
ko'rinadi (standart: Moliya va Superadmin; rollar sahifasida o'zgartiriladi).

- Fayl ekrandagi **filtrlar bo'yicha** tayyorlanadi: avval filtrni qo'ying, keyin yuklang. Fayl: birinchi varaq — ma'lumotlar,
  ikkinchi "Ma'lumot" varag'i — qaysi ro'yxat, qachon, kim yuklagani va qaysi filtrlar.
- **Eng ko'pi 50 000 qator.** Ro'yxat uzun bo'lsa, tugma tagida va faylda "kesildi" deyiladi: filtrni toraytiring (sana, ovqatxona,
  holat) va qayta yuklang.
- **Telefon raqamlari** ro'yxatdagidek yashirin; to'liq raqam faqat "to'liq telefon va karta" ruxsati borga (bu ham jurnalga yoziladi).
  **Karta raqamlari faylda hamisha yashirin.**
- Har bir eksport **harakatlar jurnaliga** yoziladi (kim, qaysi ro'yxat, filtrlar, nechta qator). Daqiqasiga 10 tadan ko'p eksport
  qilib bo'lmaydi.
- Matnlar katakda matn bo'lib qoladi (formula ishlamaydi); summalar son — Excel'da yig'ish mumkin.

## Kundalik ishlar: qadamma-qadam

Har bir amal oldidan markaz kerak bo'lsa **6 xonali kod** ("Amalni tasdiqlang" oynasi) va **sabab** so'raydi. Qaysi amal nima so'rashi:
[jadval](#qayta-tasdiq-kod-va-sabab-qachon-soraladi). Tugmani ko'rmasangiz, rolingizda ruxsat yo'q ([Rollar](#rollar)).

### Payoutni tasdiqlash va ikki kishilik qoida

*Kim: Moliya, Superadmin ("Pul yechishni tasdiqlash").*

1. **Moliya → Payoutlar**; "Tasdiq kutilmoqda" yoki "Ikkinchi tasdiq" holatidagi payoutni oching.
2. Kartani tekshiring: ovqatxona, karta (oxirgi raqamlar), **o'tkaziladigan summa** va **ushlangan qarz**. Hisob sizga mos kelmasa, avval
   ovqatxona kartasidagi [Moliya](#moliya) yozuvlariga qarang.
3. **Chegaradan kichik payout** (1 000 000 so'mdan kam): pulni ovqatxona kartasiga **o'zingiz o'tkazing**, keyin **Tasdiqlash** → sabab
   (masalan o'tkazma raqami) → kod. Payout "O'tkazilgan" bo'ladi, qarz ushlanadi.
4. **Katta payout** (1 000 000 va undan ko'p): kartada "Bu summani ikki xodim tasdiqlaydi" turadi. Birinchi xodim **Birinchi tasdiq**ni
   beradi — payout "Ikkinchi tasdiq" holatiga o'tadi, pul hali o'tkazilmaydi. **Boshqa** xodim ("Ikkinchi tasdiq kutilmoqda" navbatida
   ko'radi) tekshirib, pulni o'tkazadi va **Tasdiqlash**ni bosadi. O'zingiz berganingizdan keyin ikkinchisini bera olmaysiz — hatto superadmin ham.
5. **Rad etish**: sababni yozing — uni ovqatxona egasi ko'radi (masalan "Karta raqami noto'g'ri, yangisini kiriting").

### Pul qaytarishni tasdiqlash

*So'raydi: Operator, Yordam. Tasdiqlaydi: Moliya, Superadmin ("Pulni qaytarishni tasdiqlash"). O'zi so'ragan so'rovni tasdiqlab bo'lmaydi.*

1. So'rovchi: **Buyurtmalar** → yakunlangan buyurtma kartasi → **Pulni qaytarishni so'rash**: summa (qaytarish mumkin bo'lgandan ko'p emas) va sabab.
2. Tasdiqlovchi: **Buyurtmalar** sahifasi tepasidagi "Tasdiq kutilmoqda" ro'yxatidan so'rovni oching, summa va sababni tekshiring →
   **Tasdiqlash** (yoki rad etish; sabab bilan).
3. Click, Payme yoki naqd bo'lsa, pulni **tasdiqlagan xodim o'zi** to'lov tizimi kabinetida (yoki naqd) qaytaradi. Keshbek mijozga o'zi qaytadi.
   Batafsil: [Buyurtmalar](#buyurtmalar).

### Qo'lda tuzatish yaratish va tasdiqlash

*Kim: Moliya, Superadmin ("Moliya: qo'lda tuzatish va hisobotlar").*

1. **Moliya → Qo'lda tuzatish → Yangi tuzatish**: ovqatxona, nima o'zgarishi (turi), summa, sabab, ixtiyoriy hujjat (bank ko'chirmasi, dalolatnoma).
2. Kod kiriting. Summa 1 000 000 so'mdan kam bo'lsa, tuzatish darhol qo'llanadi. Katta bo'lsa, "Tasdiq kutilmoqda" bo'ladi: sahifada
   "Endi boshqa xodim tasdiqlashi kerak" yoziladi.
3. Boshqa xodim **Moliya → Qo'lda tuzatish**ni ochib so'rovni tekshiradi (hujjatni yuklab olish mumkin) va **Tasdiqlash** yoki **Rad etish** (sabab bilan).
   Tasdiqlansa, ledgerga darhol yoziladi.

### Ovqatxonani to'xtatish yoki faollashtirish

*Kim: Savdo, Superadmin ("Ovqatxonalarni boshqarish").*

1. **Ovqatxonalar** → ovqatxonani toping (qidiruv yoki `Ctrl+K`) → kartani oching.
2. **Amallar → To'xtatish** → sabab (masalan "Soliq qarzi, egasi bilan kelishildi") → kod. Ovqatxona marketpleysdan yashiriladi, Mini App
   buyurtmalari to'xtaydi; **kassa ishlayveradi**.
3. Qaytarish: shu yerdan **Faollashtirish**. Sabab jurnalga yoziladi (**Tarix** yorlig'ida ko'rinadi).

### Foydalanuvchini bloklash

*Kim: Yordam, Superadmin ("Murojaatlarni hal qilish").*

1. **Mijozlar va yordam → Foydalanuvchilar**; ism yoki to'liq telefon bilan toping → kartani oching. Gumon bo'lsa, avval **Buyurtmalar**,
   **Shikoyatlar**, **Sessiyalar** yorliqlarini ko'ring (yoki **Shubhali holatlar** ro'yxatidan keling).
2. **Bloklash** → sabab → kod. Odam hech qayerga kira olmaydi, hozirgi sessiyalari yopiladi.
3. Xato bo'lsa: **Blokdan chiqarish** (sabab va kod bilan). Ovqatxona egasi bloklansa, ovqatxona ishlashda davom etadi — uni to'xtatish alohida
   (oldingi retsept).

### Shikoyatga javob berish

*Kim: Yordam, Superadmin.*

1. **Mijozlar va yordam → Shikoyatlar**. "Kutmoqda" (qizil) va "Ochiq" birinchi turadi.
2. Shikoyatni oching → **Olish** (sizga biriktiriladi, "Ko'rib chiqilmoqda").
3. **Javob yozish** (kerak bo'lsa **Tayyor javob**ni tanlab tahrirlang) → **Yuborish**: javob mijozning Telegramiga boradi, shikoyat "Javob berildi" bo'ladi.
4. Masala hal bo'lgach **Hal qilish** va qanday hal qilingani izohini yozing — mijoz uni Mini App'da ko'radi. Ish davom etsa: **Qayta ochish**.

### Promo-kod yaratish va o'chirib qo'yish

*Kim: Superadmin ("Marketing: aksiyalar, promo-kodlar, bannerlar"); ko'rish — "Marketingni ko'rish".*

1. **Marketing → Promo-kodlar → Yangi promo-kod**: kod (lotin harf, raqam, `-`, `_`; 3–50 belgi), turi (foiz yoki belgilangan summa), qiymat,
   minimal buyurtma, limitlar, ovqatxona (bo'sh — hammasi), muddat.
2. **Sabab** yozing → saqlang. Kod darhol ishlay boshlaydi.
3. O'chirib qo'yish: qatordagi yoqish/o'chirish tugmasi (sabab bilan) — darhol ta'sir qiladi, berilgan buyurtmalar o'z chegirmasini saqlaydi.
4. Kodni birorta buyurtma ishlatgan bo'lsa, chegirmasini o'zgartirib bo'lmaydi: yangi kod yarating, eskisini o'chirib qo'ying.
   Ro'yxatda foydalanish statistikasi (necha marta, nechta mijoz, jami chegirma) turadi.

### Maxfiy kalitni almashtirish (sozlama)

*Kim: Superadmin ("Tizim sozlamalari").*

1. **Tizim → Tizim sozlamalari** → kerakli yorliq (**Sozlamalar** yoki **To'lov tizimlari**).
2. Kalit yonida faqat "Kiritilgan, oxiri ••••1234" turadi. **Almashtirish** → yangi qiymatni kiriting → sabab → kod.
3. Qiymat bir marta yuboriladi va qayta ko'rinmaydi; jurnalga qiymatning o'zi emas, faqat "almashtirildi" yoziladi.
4. Yangi kalit ishlayotganini tekshiring (masalan sinov to'lovi yoki SMS yuborish). Eski qiymatni olib tashlash kerak bo'lsa, **O'chirish** —
   shu yerda saqlangan qiymat o'chadi va server sozlamasi (`.env`) ishlay boshlaydi.

### Ogohlantirishni o'qish va yopish

*Kim: Operator, Superadmin (ko'rish — "Monitoring"ga ruxsat bor hamma).*

1. Telegramdagi xabar yoki **Bugun → Diqqat talab qiladi** qatori sizni **Monitoring → Ogohlantirishlar**ga olib keladi.
2. Darajasiga (**Kritik** / **Diqqat**) va "Tafsilot"iga qarang: sonlar va nomlar nima buzilganini aytadi.
3. Shug'ullanayotgan bo'lsangiz: **Qabul qilish** (ixtiyoriy izoh) — ismingiz yoziladi, boshqalar takror shug'ullanmaydi.
4. Sababni bartaraf etgach **Hal qilish**; muammo o'zi tugasa, ogohlantirish **o'zi yopiladi**. Har ogohlantirish turi uchun nima qilish:
   [Monitoring](#monitoring) jadvali.

### Ro'yxatni Excel'ga yuklash

*Kim: Moliya, Superadmin ("Eksport" ruxsati).*

1. Kerakli ro'yxatni oching (Buyurtmalar, Ovqatxonalar, Ulash jarayoni, Moliya, POS yoki Harakatlar jurnali).
2. **Filtrlarni qo'ying** (sana, ovqatxona, holat) — fayl ekrandagi filtrlar bo'yicha tayyorlanadi.
3. **Excel yuklab olish**. Fayl eng ko'pi 50 000 qator; kesilsa, filtrni toraytiring. Yuklash jurnalga yoziladi.
   Batafsil: [Excel eksport](#excel-eksport).

## Qayta tasdiq (kod) va sabab qachon so'raladi

**Qayta tasdiq** — shu seansda ilovadagi 6 xonali kodni yana kiritish. Tasdiq 10 daqiqa amal qiladi (shu vaqt ichida qayta so'ralmaydi).
Kod bir marta ishlaydi: hozirgina ishlatilgan kodni qayta kiritsangiz, "keyingi kodni kiriting" deydi — 30 soniya kuting.
**Sabab** — 3–500 belgi, harakatlar jurnaliga yoziladi. Faqat o'qish (ko'rish, qidirish, filtr) hech narsa so'ramaydi.

| Bo'lim | Amal | Kod | Sabab | Qo'shimcha |
|---|---|---|---|---|
| Buyurtmalar | bekor qilish, holatni tuzatish, ovqatxona/mijozga yozish | ha | ha | |
| Buyurtmalar | pulni qaytarishni so'rash / tasdiqlash / rad etish | ha | ha | tasdiqlovchi boshqa xodim |
| Buyurtmalar | SLA chegaralarini o'zgartirish | ha | ha | |
| Ovqatxonalar | to'xtatish, faollashtirish, kredit limiti, ko'rsatish, Telegram havola, egasiga yozish | ha | ha | |
| Ovqatxonalar | ovqatxona yaratish, arizani rad etish, "Faol" bosqichi, ommaviy e'lon | ha | ha | |
| Ovqatxonalar | izoh, vazifa, bosqichni ko'chirish, ro'yxat, ariza qo'shish | yo'q | yo'q | |
| POS va obunalar | tarif biriktirish, davr, sinov, bekor qilish, komissiya | ha | ha | |
| POS va obunalar | tarifni tahrirlash | ha | ha | faqat Superadmin |
| POS va obunalar | hisob-fakturani to'langan deb belgilash / bekor qilish | ha | ha | katta summa — ikkinchi xodim |
| POS va obunalar | qurilmani o'chirish, fiskal chekni qayta yuborish, sinxron xatoni hal qilish | ha | ha | |
| Moliya | payoutni tasdiqlash / rad etish | ha | ha | 1 000 000 so'mdan — ikki xodim |
| Moliya | qo'lda tuzatish, hisob-kitobni qayta yozish | ha | ha | 1 000 000 so'mdan — ikki xodim |
| Mijozlar va yordam | bloklash, blokdan chiqarish, sessiyalarni yopish | ha | ha | |
| Mijozlar va yordam | shikoyatga javob, hal qilish, biriktirish, tayyor javoblar | yo'q | hal qilishda izoh | |
| Marketing | banner, promo-kod, kategoriya: qo'shish, o'zgartirish, tartib, yoqish/o'chirish | yo'q | ha | |
| Marketing | o'chirib tashlash (banner, promo-kod, kategoriya) | ha | ha | |
| Monitoring | ogohlantirishni qabul qilish / hal qilish | yo'q | ixtiyoriy izoh | |
| Monitoring | yiqilgan vazifani qayta urinish | yo'q | yo'q | |
| Monitoring | vazifani o'chirish, qoidani o'zgartirish | ha | ha | |
| Integratsiyalar | ulanishni to'xtatish / qayta yoqish | ha | ha | |
| Tizim sozlamalari | sozlama, to'lov tizimi, kalit, viloyat/tuman, funksiya | ha | ha | |
| Jamoa va rollar | taklif, rol, hisobni o'chirish/yoqish, 2FA tiklash, ruxsat o'zgartirish | ha | — | |
| Sessiyalarim | boshqa sessiyani tugatish, yangi tiklash kodlari | ha | — | |

Jadvaldagi "yo'q" o'sha amal kod yoki sabab so'ramasligini bildiradi, lekin **hammasi baribir jurnalga yoziladi**. Ekran sizdan
nimadir so'rasa — shu qoida: markaz o'zi so'raydi, oldindan yodlash shart emas.

## Telefon raqamlarini kim ko'radi

Mijoz, kuryer, ovqatxona egasi va xodimlarning telefonlari markazda **hamma joyda qisman yashiriladi** (`+998 90 *** ** 67`): ro'yxatlarda,
kartalarda, qidiruvda, jurnalda, Excel fayllarda. To'liq raqamni faqat **"To'liq telefon va karta raqamlarini ko'rish"** ruxsati
bor xodim ko'radi — standartda **faqat Superadmin**. Ruxsat Rollar sahifasida boshqa rolga ham berilishi mumkin, lekin ehtiyot bo'ling:

- har bir ochilgan raqam jurnalga yoziladi (kim, kimning, qachon);
- qidiruvda telefon bo'yicha faqat **to'liq raqam** topiladi (yarim yashirin raqamni raqamma-raqam topib bo'lmasin);
- ruxsat yo'q rolda ("Faqat ko'rish" ham) to'liq raqam Excel faylda ham chiqmaydi; **karta raqamlari faylda hamisha yashirin**;
- ovqatxonaga yoki mijozga bog'lanish kerak bo'lsa, markazdagi amallardan foydalaning: "Egasiga yozish" (Telegram), shikoyatga javob —
  telefonni ko'rish shart emas. Qo'ng'iroq kerak bo'lsa, Superadminga murojaat qiling.

## Xatolar va yechimlar

| Ko'ringan | Sabab va nima qilish kerak |
|---|---|
| "Email yoki parol noto'g'ri" | Email yoki parolni tekshiring. Sabab aytilmaydi (xavfsizlik uchun). 5 xatodan keyin 15 daqiqa kuting |
| "Kod noto'g'ri yoki eskirgan" | Ilovadagi **yangi** kodni kiriting (30 soniyada almashadi); telefon vaqti avtomatik (tarmoqdan) o'rnatilganini tekshiring |
| "Kirish muddati tugadi (5 daqiqa)" | Parol kiritilgandan keyin kodni 5 daqiqa ichida yozish kerak. Boshidan boshlang |
| "Taklif havolasi eskirgan yoki ishlatilgan" | Havola 72 soat va bir marta ishlaydi. Superadministratordan yangisini so'rang |
| "Seans tugadi. Qaytadan kiring" | 60 daqiqa faolsizlik, 12 soat o'tdi yoki sessiya tugatildi. Qaytadan kiring |
| Menyuda kerakli bo'lim yo'q | Rolingizda unga ruxsat yo'q. Superadministratorga murojaat qiling |
| "Serverga ulanib bo'lmadi" | Internetni tekshiring, "Qayta urinish"ni bosing. Davom etsa, texnik jamoaga xabar bering |
| "Reauth required" / "Amalni tasdiqlang" oynasi chiqdi | Normal: amal oldidan kod so'raladi. Ilovadagi **yangi** kodni kiriting |
| "Bu kod hozirgina ishlatilgan — keyingi kodni kiriting" | Har kod bir marta ishlaydi: 30 soniya kutib, ilovadagi yangi kodni yozing |
| "Siz ko'rishingiz mumkin, o'zgartirishga ruxsat yo'q" | Rolingizda bu amal uchun ruxsat yo'q. Superadmin Rollar sahifasidan beradi |
| Kod doim "noto'g'ri" | Telefon vaqti avtomatik (tarmoqdan) o'rnatilganini tekshiring; bo'lmasa — tiklash kodi yoki superadmin ([2FA](#2fa-va-tiklash-kodlari)) |
| Amal "qayta urinib ko'ring" deydi | Boshqa xodim sizdan oldin o'zgartirgan bo'lishi mumkin: sahifani yangilang |

Har bir bo'limning o'z xatolar jadvali o'sha bo'lim oxirida.
