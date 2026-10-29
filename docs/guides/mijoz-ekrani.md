# Mijoz ekrani: egasi va kassir uchun qo'llanma

Mijoz ekrani — mijozga qaragan ekran. U ikki ish qiladi, ikkalasini ham xodimsiz, o'zi bajaradi:

1. **Kassaning ikkinchi ekrani.** Kassir taom qo'shganda mijoz buyurtmasini, jami summani va to'lov usulini ko'radi. QR bilan
   to'lansa, ekranda QR chiqadi (telefon kamerasi bilan skanerlanadi). To'langandan keyin **"Rahmat!"** va, agar raqam berilgan
   bo'lsa, **"Raqamingiz: 17"** chiqadi.
2. **"Tayyorlanmoqda / Tayyor" taxtasi.** Devordagi televizor yoki alohida planshet: chapda pishayotgan raqamlar, o'ngda tayyorlari,
   yirik raqamlar. Raqam tayyor bo'lganda ovoz chalinadi va "17 raqam tayyor!" yozuvi chiqadi.

**Mijoz ekrani bepul.** U tarifdagi kassa va oshxona ekrani sonlariga kirmaydi, qo'shimcha to'lov yo'q, to'lanmagan hisob-faktura uni
ulashga to'sqinlik qilmaydi (ofitsiant telefonlari kabi).

> Ekran xodim emas: unda PIN yo'q, hech kim kirmaydi. Shuning uchun u hech narsani o'zgartira olmaydi, faqat ko'rsatadi.

## Mundarija

1. [Ekran qachon kerak va qaysi modul yoqiladi](#1-ekran-qachon-kerak-va-qaysi-modul-yoqiladi)
2. [Ekranni ulash (bir marta)](#2-ekranni-ulash-bir-marta)
3. [Ikkinchi ekran rejimi: mijoz nimani ko'radi](#3-ikkinchi-ekran-rejimi-mijoz-nimani-koradi)
4. [Raqamlar taxtasi rejimi](#4-raqamlar-taxtasi-rejimi)
5. [Ekran sozlamalari](#5-ekran-sozlamalari)
6. [Aloqa uzilsa](#6-aloqa-uzilsa)
7. [Ekranni o'chirish yoki almashtirish](#7-ekranni-ochirish-yoki-almashtirish)
8. [Muammolar va yechimlar](#8-muammolar-va-yechimlar)

## 1. Ekran qachon kerak va qaysi modul yoqiladi

Ekran filial **formati**ga qarab ishlaydi ([ega qo'llanmasi](ega-pos-sozlash.md#filial-formati-birinchi-qadam)):

| Format | Ikkinchi ekran (`Mijoz ekrani` moduli) | Raqamlar taxtasi (`Raqamni chaqirish` moduli) |
|---|---|---|
| Fast food, kafe, qahvaxona | yoqilgan | yoqilgan |
| Stolovaya | yoqilgan | yo'q (raqam berilmaydi) |
| Osh markazi, lag'monxona | ixtiyoriy (egasi yoqadi) | yoqilgan |
| Choyxona, restoran | yo'q | yo'q |

Modulni egasi panelda **Sozlamalar** → **Format** sahifasida yoqadi yoki o'chiradi. Ekran ulanayotganda filialda kamida bitta
modul yoqilgan bo'lishi kerak. Modul yoqilmagan bo'lsa, ekran sababini yozadi: "Egasi hamkor panelida Sozlamalar → Format
sahifasidan yoqadi".

## 2. Ekranni ulash (bir marta)

Ekran uchun planshet, Windows kompyuter yoki televizorga ulangan kompyuter kerak. Android planshetda ilova yoki Windows/Linux
kompyuterda `customer_display` ilovasi ishlaydi.

**Panelda (kompyuterda):**

1. [Hamkor paneli](hamkor-panel.md)ga kiring.
2. **Sozlamalar** → **Qurilmalar** → **Ulash kodini yaratish**.
3. **Turi**: **Mijoz ekrani (bepul)**.
4. **Filial**: ekran qaysi filialniki bo'lsa, shuni tanlang. Mijoz ekrani uchun filial **majburiy**: ekran shu filialning
   raqamlarini ko'rsatadi.
5. **Qaysi kassaning ekrani**: bir nechta kassa bo'lsa, kerakli kassani tanlang (bitta kassa bo'lsa, tanlash shart emas).
6. **Nomi**: masalan, "Kassa 1 ekrani" yoki "Zal televizori".
7. **Kod yaratish** ni bosing. `ABCD-EFGH` ko'rinishidagi kod chiqadi. U **10 daqiqa** amal qiladi va **bir marta** ishlaydi.

**Ekranda:**

1. Mijoz ekrani ilovasini oching. **Mijoz ekranini ulash** oynasi chiqadi.
2. **Ulash kodi** maydoniga kodni yozing (chiziqcha shart emas, kichik harf ham bo'ladi).
3. **Ulash** ni bosing.
4. Ulandi. Filialda chek bo'lmasa, ekranda do'kon nomi va "Xush kelibsiz!" yoki raqamlar taxtasi chiqadi.

Bir filialda bir nechta kassa bo'lsa, **Qaysi kassaning ekrani** maydonidan shu ekran qaysi kassaning chekini ko'rsatishini
tanlang (filialda kassalar ro'yxati chiqadi). Tanlamasangiz, ekran filialning oxirgi cheki bilan ishlaydi: bitta kassa
bo'lsa, bu to'g'ri. Ikki kassali joyda (masalan, fast food) har kassaga o'z ekranini ulang va shu maydonda kassani tanlang.

Planshetni ulagach, ilovani o'chirib-yoqsangiz ham qayta ulash shart emas.

## 3. Ikkinchi ekran rejimi: mijoz nimani ko'radi

Kassir buyurtma olayotganda mijoz ekranda:

- **Buyurtma ro'yxati**: har bir taom, miqdori (yoki og'irligi, masalan "0.75 kg"), modifikatorlari ("+ Achchiq") va qator summasi;
  yangi taom qo'shilganda ro'yxat pastga suriladi.
- **Jami summa** juda katta shriftda; chegirma bo'lsa "Jami", "Chegirma" va "To'lash kerak".
- **Navbat raqami** ("Raqam 17"), agar format raqam beradigan bo'lsa.

Kassir **To'lash**ni bosganda ekran to'lov rejimiga o'tadi:

- **To'lash kerak** summasi va to'lov usuli: *Naqd pul*, *Karta*, *QR orqali*.
- Naqdda: *Berildi* va *Qaytim* summalari.
- QR bilan to'lashda kassir QR ko'rsatsa, ekranda QR va "Click orqali to'lash uchun skanerlang" yoziladi.

To'lov tugagach: **"Rahmat!"**, to'langan summa, qaytim va **"Raqamingiz: 17"** (ekranda raqami chiqqach oladi). "Rahmat" 8 soniya
turadi yoki kassir **"Keyingi mijoz"** ni bosganda o'zi tozalanadi. Kassir unutib qo'ysa ham, "Rahmat" o'zi ketadi; tugatilmagan chek
30 daqiqadan keyin ekranda qolmaydi.

Ekranda **narx tarkibi, mijozning ismi yoki telefoni** ko'rinmaydi: faqat u tanlagan taomlar va summa.

## 4. Raqamlar taxtasi rejimi

Devordagi televizor yoki planshet:

- **Tayyorlanmoqda** (chapda): oshxonaga tushgan, hali tayyor bo'lmagan raqamlar, kichikdan kattaga.
- **Tayyor** (o'ngda): tayyor bo'lgan raqamlar, eng yangisi birinchi. Mijoz o'z raqamini ko'rib, kassadan oladi.
- Raqam tayyor bo'lganda: **ovoz** ("ding-dong"), tepada **"17 raqam tayyor!"** yozuvi va raqam yorqin ajralib turadi (8 soniya).
- Kassir raqamni **"Qayta chaqirish"** bilan yana chaqirsa: "17 raqam, buyurtmangizni oling!" va ovoz yana chalinadi.
- Mijoz buyurtmasini olgach ("Berildi"), raqam taxtadan ketadi.
- Raqamlar ko'p bo'lib sig'masa, ro'yxat o'zi sekin pastga suriladi va oxirida boshiga qaytadi.

Taxta oshxona ekranidagi (KDS) "tayyor" tugmasi bosilishi bilan, odatda **1–2 soniyada** yangilanadi.

## 5. Ekran sozlamalari

Ekranning **pastki chap burchagini 2 soniya bosib turing** (tasodifan teginish oynani ochmasin). **Ekran sozlamalari** oynasi
ochiladi:

| Sozlama | Nima |
|---|---|
| **Nima ko'rsatiladi** | **Avto**: chek bor paytda chek, qolgan paytda tayyor raqamlar. **Ikkinchi ekran**: faqat chek va to'lov. **Raqamlar taxtasi**: faqat raqamlar (televizor) |
| **Tayyor raqam ovozi** | ovozni o'chirish (yozuv va raqam baribir chiqadi) |
| **Qorong'i mavzu** | standart qorong'i (zalda ko'zni qamashtirmaydi); yorug' ham bor |
| **Shrift** | 100%, 125%, 150% (uzoqdan o'qiladigan televizor uchun kattaroq) |

Sozlama ekranning o'zida saqlanadi. Ikkala modul yoqilgan fast food'da **Avto** tavsiya etiladi: kassir ishlayotganda mijoz
chekni, bo'sh paytda raqamlarni ko'radi. Devordagi alohida televizorga **Raqamlar taxtasi** qo'ying.

## 6. Aloqa uzilsa

- Ekran oxirgi bilgan chek va raqamlarni ko'rsatishda davom etadi, burchakda qizil **"Aloqa yo'q"** belgisi chiqadi.
- Aloqa tiklanishi bilan ekran o'zi yangilanadi, hech narsa qilish shart emas.
- Kassa internet uzilsa ham ishlayveradi (internetsiz rejim): mijoz ekranining yangilanishi to'xtashi kassadagi sotuvga
  ta'sir qilmaydi. Real-time (jonli) ulanish bo'lmasa ham ekran har 5 soniyada o'zi so'raydi.

## 7. Ekranni o'chirish yoki almashtirish

Ekranda xodim yo'q, shuning uchun uni **panelda** o'chirasiz: **Sozlamalar** → **Qurilmalar** → ekran qatoridagi **O'chirish**.
Ekran o'zi ulash oynasiga qaytadi. Yangi ekranni ulash uchun yangi kod yarating. O'chirilgan ekran hech narsa ko'rsatmaydi va
haqiqatan o'chiq: tokeni yaroqsiz bo'ladi.

## 8. Muammolar va yechimlar

| Ko'rinish | Sabab | Nima qilish kerak |
|---|---|---|
| Kodni kiritganda "Kod noto'g'ri yoki muddati o'tgan" | kod 10 daqiqadan eski yoki ishlatilgan | panelda yangi kod yarating |
| Panelda kod yaratilmaydi, "Mijoz ekrani uchun filialni tanlang" | filial tanlanmagan | **Filial**ni tanlang |
| Panelda "…moduli bu filialda o'chirilgan" | filial formatida mijoz ekrani ham, raqamni chaqirish ham yoqilmagan | **Sozlamalar** → **Format**: **Mijoz ekrani** (yoki **Raqamni chaqirish**) ni yoqing |
| Ekranda "Bu ekran hozircha o'chirilgan" | tanlangan rejim uchun modul yoqilmagan | modulni yoqing yoki sozlamada rejimni o'zgartiring |
| Ekranda "Ekran sozlanmagan: Bu qurilma filialga ulanmagan" | ekran filial tanlanmasdan ulangan | panelda o'chirib, filialni tanlab qayta ulang |
| Kassir taom qo'shyapti, ekran bo'sh | ekran boshqa kassaga bog'langan yoki modul o'chiq | ekran sozlamalarida rejim **Avto**/**Ikkinchi ekran**; kassa qaysi ekran bilan ishlashini egasi tekshirsin |
| Raqam tayyor, lekin ovoz yo'q | ovoz o'chirilgan yoki qurilma ovozi pastda | ekran sozlamalarida **Tayyor raqam ovozi**ni yoqing, qurilma ovozini oshiring |
| Ekran uxlab qolyapti (Windows/Linux) | tizim uyqu rejimi | uyqu va ekran saqlovchini o'chiring (Android'da ilova ekranni o'zi yoqiq tutadi) |
| Burchakda "Aloqa yo'q" | internet yoki server | ekran o'zi tiklanadi; uzoq davom etsa qurilmaning internetini tekshiring |
