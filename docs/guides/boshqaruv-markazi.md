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
