# RIZQIM boshqaruv markazi: jamoa uchun qo'llanma

**Boshqaruv markazi** — RIZQIM xodimlari uchun veb-sahifa (`admin.<domen>`). Kompyuterda ham, telefonda ham ishlaydi.
Bu qo'llanma birinchi versiya: kirish, 2FA, jamoa, rollar va harakatlar jurnali. Buyurtmalar, ovqatxonalar va moliya bo'limlari
keyingi to'lqinlarda qo'shiladi (menyuda ular bor, lekin hozircha "Bu bo'lim keyingi to'lqinda qo'shiladi" deb turibdi).

## Mundarija

1. [Kirish](#1-kirish)
2. [2 bosqichli tasdiqlash (2FA) va tiklash kodlari](#2-2-bosqichli-tasdiqlash-2fa-va-tiklash-kodlari)
3. [Menyu va qidiruv](#3-menyu-va-qidiruv)
4. [Jamoa](#4-jamoa)
5. [Rollar](#5-rollar)
6. [Harakatlar jurnali](#6-harakatlar-jurnali)
7. [Sessiyalarim](#7-sessiyalarim)
8. [Xatolar va yechimlar](#8-xatolar-va-yechimlar)
9. [Bugun (bosh sahifa)](#bugun-bosh-sahifa)
10. [Buyurtmalar](#buyurtmalar)
11. [Monitoring](#monitoring)

## 1. Kirish

1. Superadministrator sizni jamoaga qo'shadi va **taklif havolasini** yuboradi (u 72 soat amal qiladi va faqat bir marta ishlaydi).
2. Havolani oching, **parol** o'ylab toping: kamida 12 ta belgi, emailingizni parol qilmang. Parol menejerida saqlang.
3. Telefoningizdagi ilovani ulang (2-bo'lim). Tayyor: markazga kirasiz.

Keyingi safar: **email** va **parol** yozing, so'ng telefon ilovasidagi **6 xonali kod**ni kiriting.

- Parolni yoki kodni 5 marta xato yozsangiz, kirish 15 daqiqaga to'xtatiladi. Shoshilmang, 15 daqiqadan keyin qayta urining.
- Hisobingiz o'chirilgan bo'lsa, shu haqida yozuv chiqadi: superadministratorga murojaat qiling.
- Markaz sizni **60 daqiqa** hech narsa qilmasangiz chiqarib yuboradi. 55-daqiqada "Seans tugayapti" oynasi chiqadi:
  davom etish uchun **Men shu yerdaman** tugmasini bosing. Har holda bir kirish 12 soatdan ortiq davom etmaydi.

## 2. 2 bosqichli tasdiqlash (2FA) va tiklash kodlari

2FA — parolingiz o'g'irlansa ham hisobingizni himoya qiladi. Kod telefondagi ilovada har 30 soniyada yangilanadi.

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
(4-bo'lim); keyingi kirishda 2FA'ni qaytadan o'rnatasiz. Superadministratorning o'zi uchun: [OPERATIONS.md](../OPERATIONS.md).

**Muhim amallar oldidan qayta tasdiqlash.** Jamoa, rollar yoki boshqa sessiyani o'zgartirganda markaz yana
**6 xonali kod** so'raydi ("Amalni tasdiqlang" oynasi). Kodni kiriting: tasdiq 10 daqiqa amal qiladi, shu vaqt ichida qayta so'ralmaydi.
"Bekor qilish"ni bossangiz, amal bajarilmaydi.

## 3. Menyu va qidiruv

Chapdagi menyuda bo'limlar shu tartibda: **Bugun, Buyurtmalar, Ovqatxonalar, POS va obunalar, Moliya, Mijozlar va yordam,
Marketing, Monitoring, Integratsiyalar**, so'ng **Tizim** (Jamoa, Rollar, Harakatlar jurnali, Sessiyalarim). Sizga ruxsat
berilmagan bo'limlar menyuda ko'rinmaydi: bu normal, ruxsatni rolingiz belgilaydi. "Sessiyalarim" hammada bor.

Telefonda menyu yuqoridagi tugma ("Menyuni ochish") ortida turadi.

**Qidirish.** Klaviaturada **Ctrl+K** (Mac'da **Cmd+K**) yoki yuqoridagi "Qidirish" tugmasi. Kamida 2 ta belgi yozing:
ovqatxona (nomi, STIR, telefon), buyurtma (kodi), mijoz yoki xodim topiladi. Natijalar turi bo'yicha guruhlangan; yuqoriga/pastga
strelkalar bilan yuring, **Enter** bilan oching, **Esc** bilan yoping. Telefon raqamlari qisman yashiriladi
(`+998 90 *** ** 67`); to'liq ko'rish huquqi alohida beriladi va har bir ochilish jurnalga yoziladi.

## 4. Jamoa

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

Har bir amal oldidan markaz oddiy savol beradi ("... hisobini o'chirasizmi?") va 6 xonali kod so'raydi (2-bo'lim).

## 5. Rollar

Oltita rol bor: **Superadmin, Operator, Yordam, Moliya, Savdo / ulash, Faqat ko'rish**. **Tizim → Rollar** jadvalida qatorlar —
ruxsatlar (guruhlangan), ustunlar — rollar. Katakni (uyaning kaliti) bosing: ruxsat berilgan yoki olingan bo'ladi, o'zgarish
**darhol saqlanadi va jurnalga yoziladi**. Telefonda jadval o'z ichida yon tomonga suriladi.

- **Superadmin** ustuni qulflangan: u hamma narsani qila oladi va buni o'zgartirib bo'lmaydi.
- Pul yoki keng huquq beradigan ruxsatni (masalan, payoutni tasdiqlash) berayotganda markaz yana bir marta so'raydi.
- Bir rolning ruxsatlarini o'zgartirsangiz, shu roldagi barcha xodimlarga ta'sir qiladi (ular sahifani yangilaganda yangi menyuni ko'radi).

Qaysi rol nimani qiladi (boshlang'ich holat): **Operator** — Bugun, buyurtmalar (aralashish bilan), ovqatxonalarni ko'rish, monitoring;
**Yordam** — shikoyatlar, foydalanuvchilar, buyurtmalarni ko'rish; **Moliya** — moliya, obunalar, payout tasdiqlash;
**Savdo / ulash** — arizalar, ovqatxona kartasi, tarif; **Faqat ko'rish** — hamma bo'limni ko'radi, o'zgartira olmaydi.

## 6. Harakatlar jurnali

**Tizim → Harakatlar jurnali**: kim, qachon, nima qilgani. Yozuvlarni **o'zgartirib ham, o'chirib ham bo'lmaydi**. Yangisi tepada.

- **Filtrlar**: qidiruv (matn, ID, sabab), amal nomining boshi (masalan `admin.team`), sanalar; "Qo'shimcha filtrlar"da —
  xodim, ovqatxona (ID), obyekt turi va ID. Filtrlarni tanlab **Qo'llash**ni bosing; **Tozalash** hammasini olib tashlaydi.
- Ro'yxat 30 tadan yuklanadi: pastdagi **Yana yuklash** tugmasi eskilarini qo'shadi.
- Yozuvni bossangiz, **tafsilot** ochiladi: kim, qaysi qurilma va IP'dan, sababi va **nima o'zgargani** (maydon, oldin, keyin).
  Maxfiy qiymatlar (parol, kalit) serverda yashiriladi va hech qachon ko'rinmaydi.

## 7. Sessiyalarim

**Tizim → Sessiyalarim**: hisobingiz qaysi qurilmalarda ochiq (brauzer, IP, kirgan va oxirgi faollik vaqti). Tanimagan qurilma
ko'rsangiz, "Sessiyani tugatish"ni bosing (6 xonali kod so'raladi), so'ng parolingizni almashtirishni superadministratordan so'rang.
Shu sahifada 2FA holati, qolgan tiklash kodlari soni va **"Yangi tiklash kodlarini yaratish"** tugmasi ham bor.

## 8. Xatolar va yechimlar

| Ko'ringan | Sabab va nima qilish kerak |
|---|---|
| "Email yoki parol noto'g'ri" | Email yoki parolni tekshiring. Sabab aytilmaydi (xavfsizlik uchun). 5 xatodan keyin 15 daqiqa kuting |
| "Kod noto'g'ri yoki eskirgan" | Ilovadagi **yangi** kodni kiriting (30 soniyada almashadi); telefon vaqti avtomatik (tarmoqdan) o'rnatilganini tekshiring |
| "Kirish muddati tugadi (5 daqiqa)" | Parol kiritilgandan keyin kodni 5 daqiqa ichida yozish kerak. Boshidan boshlang |
| "Taklif havolasi eskirgan yoki ishlatilgan" | Havola 72 soat va bir marta ishlaydi. Superadministratordan yangisini so'rang |
| "Seans tugadi. Qaytadan kiring" | 60 daqiqa faolsizlik, 12 soat o'tdi yoki sessiya tugatildi. Qaytadan kiring |
| Menyuda kerakli bo'lim yo'q | Rolingizda unga ruxsat yo'q. Superadministratorga murojaat qiling |
| "Serverga ulanib bo'lmadi" | Internetni tekshiring, "Qayta urinish"ni bosing. Davom etsa, texnik jamoaga xabar bering |

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

Qoidalar muammo topsa, shu yerda ogohlantirish ochiladi va Telegram ops guruhiga xabar ketadi. Har ogohlantirishda: darajasi
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
Tepada "Telegram guruhi sozlanmagan" deb yozilgan bo'lsa — texnik jamoa `TELEGRAM_OPS_CHAT_ID` ni kiritsin.

| Ko'ringan | Sabab va nima qilish kerak |
|---|---|
| "Holat yuklanmadi" | Internet yoki server. "Qayta urinish"ni bosing; boshqa bo'limlar baribir ishlaydi |
| "Bu ogohlantirish allaqachon qabul qilingan yoki hal bo'lgan" | Boshqa xodim sizdan oldin bosdi. Ro'yxat yangilandi |
| "Sabab kamida 3 belgi bo'lsin" | O'chirish va qoidani o'zgartirish uchun sabab yozing |
| "Siz ko'rishingiz mumkin, o'zgartirishga ruxsat yo'q" | Rolingizda "Monitoring: fon vazifalari va ogohlantirishlar" ruxsati yo'q. Superadmin Rollar sahifasidan beradi |
