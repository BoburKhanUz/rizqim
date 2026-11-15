# Baza va masshtab: sinov natijasi va tahlil

Sana: 2026-11-14. Bu hujjat egasi qaror qilishi uchun yozilgan. To'liq raqamlar: [RESULTS.md](RESULTS.md) (sinov),
[DB_SCALE_PLAN.md](DB_SCALE_PLAN.md) (kod tekshiruvi), [LOCAL_MODE_AUDIT.md](LOCAL_MODE_AUDIT.md) (lokal rejim).
Sinov skriptlari: `scripts/scale-test/`.

Belgilar: **o'lchangan** = shu sinovda o'lchab olingan; **taxmin** = o'lchangan raqamdan hisoblab chiqarilgan.

## 1. Qisqa javob

**Bazani hozir bo'lish shart emas.** Baza 11 baravar kattalashganda (24 ming → 279 ming buyurtma, 23 → 253 do'kon)
kassa amallari deyarli sekinlashmadi: 1,0–1,3 baravar (o'lchangan). Tizimni sekinlashtiradigan narsa ma'lumot hajmi
emas, to'rtta boshqa narsa, va ularning hammasi bitta baza ichida tuzatiladi:

1. Har bir so'rov bazaga **yangi ulanish** ochadi. Postgres protsessor vaqtining taxminan 90 foizi shunga ketyapti.
2. **Bildirishnomalar** jadvalida kerakli indeks yo'q. Butun tizimda faqat shu so'rov umumiy hajm bilan birga sekinlashadi.
3. **Market va kassa bitta navbatda.** Marketda mijozlar ko'payganda kassalar kutib qoladi.
4. **Polling.** So'rovlarning 78 foizi "yangilik bormi?" degan takroriy so'rov, har safar to'liq javob qaytadi.

Bazani do'konlarga bo'lish bu to'rttasidan birortasini ham tuzatmaydi.

## 2. Nima sinaldi

Bitta katta baza: 253 do'kon (220 tasi faqat market, 11 tasi faqat POS, 22 tasi ikkalasi), 99 kassa, 278 547 buyurtma,
2,16 GB. Har bir buyurtma haqiqiy buyurtma yozadigan barcha qatorlar bilan (audit, fiskal chek, to'lov, tarix).
Sinovlarda xato yo'q: 5xx ham, 429 ham bo'lmadi.

| Sinov | Natija (o'lchangan) |
|---|---|
| Tushlik payti, 33 POS do'kon, 99 kassa | Buyurtma ochish p95 200 ms, to'lov p95 274 ms, qulflanish kutishi 0 |
| Tushlik + egalar hisobot ochadi | Kassa atigi 6 % sekinlashdi (200 → 212 ms) |
| Tushlik + marketda 250 mijoz birdan kiradi | **Kassa p95 0,16 s → 9,0 s**; indekslardan keyin 3,7 s |
| Faqat market, 250 mijoz | Sekundiga 33,7 so'rov; indekslardan keyin 50 |
| 40 kassa internet qaytgach navbatni yuboradi | Har kassa 3,2 s da tugatdi, 1 280 amalning hammasi qabul qilindi |
| Zaxira nusxa (pg_dump), 2,29 GB | 24,7 s |

## 3. Market va POS birga bo'lganda nima bo'ladi

Bu sizning asosiy savolingiz edi va sinovdagi eng muhim topilma shu yerda.

- POS do'konning o'zi og'ir yuk emas: tushlik payti bir do'kon daqiqasiga 32 so'rov yuboradi, shundan 25 tasi polling.
- Market do'kon kam buyurtma beradi, lekin mijozlar ilovasi ochiq turganda tinmay so'rov yuboradi. Yil 1 taxminida
  (2 000 market + 300 POS) eng band paytda so'rovlarning 70 foizi Mini App'dan keladi.
- Ikkalasi bitta PHP navbatidan va bitta protsessordan foydalanadi. Shuning uchun Telegram'da reklama tarqatilganda
  yoki tushlik payti market gavjumlashganda **kassa 9 soniya kutib qoladi**. Kassir uchun bu "tizim qotdi" degani.

Bu yerda bir narsani aniq aytish kerak: 9 soniya degan raqam sinov mashinasiga tegishli (4 yadro, 4 ta PHP ishchi).
Haqiqiy serverda raqam boshqa bo'ladi, lekin sabab o'sha: umumiy navbat. Tuzatish yo'li baza bo'lish emas, kassa va
market so'rovlarini **alohida PHP guruhlariga** ajratish, katalogni keshlash va ulanishlar pulini qo'yish.

## 4. Hajm: baza qanchalik tez o'sadi

Bitta buyurtma bazada egallaydigan joy (o'lchangan, indekslar bilan): POS buyurtma **9,76 KB**, Mini App buyurtma
**5,33 KB**. Oldingi taxminim 12 KB edi, haqiqiysi kamroq chiqdi.

| Holat | Yillik buyurtma | Yillik o'sish (taxmin) | Eng band paytda so'rov/s (taxmin) |
|---|---|---|---|
| 500 market + 50 POS | 6,6 mln | 62 GB | ~270 |
| 2 000 market + 300 POS | 32,5 mln | 320 GB | ~1 190 |
| 5 000 market + 1 500 POS | 126 mln | 1,35 TB | ~3 700 |

Hajmning 58 foizi **jurnallar**: audit, fiskal cheklar, holat tarixi, bildirishnomalar. Ular yozilgandan keyin deyarli
o'qilmaydi. Demak, hajm muammosi kelganda birinchi bo'lib do'konlarni emas, jurnallarni ajratish kerak.

## 5. Lokal rejim savoli

Bugungi usul (bulut asosiy) va lokal usul (kassalar do'kon ichida ishlaydi, serverga faqat natija yuboradi) bir xil
buyurtma soni bilan solishtirildi (o'lchangan):

| Bir do'kon uchun | Bugun | Lokal usul |
|---|---|---|
| Daqiqasiga so'rov | 32 | 1,3 |
| Bir buyurtmaga protsessor vaqti | 1 877 ms | 381 ms |
| Bir buyurtmaga bazaga yoziladigan qator | 31 | 25 |

Xulosa: lokal usul serverni 5 baravar yengillatadi, lekin bu tejamning asosiy qismi pollingni olib tashlashdan keladi.
Bazaga yoziladigan qatorlar soni deyarli bir xil. Pollingni lokal rejimsiz ham olib tashlash mumkin (6-bo'lim, A
to'lqini), bu 0,5–1 to'lqin ish; lokal rejim esa 3–5 to'lqin va PHP'dagi pul qoidalarini kassaga ko'chirishni talab
qiladi.

Shuning uchun lokal rejim haqidagi qarorni **server yuki** bilan emas, **boshqa savol** bilan qabul qilish kerak:
do'kon internetsiz butun kun ishlashi shartmi? Bugun kassa internetsiz buyurtma ochadi, naqd va karta qabul qiladi,
lekin schyotni bo'lish, chegirma, qaytarish, smena ochish-yopish ishlamaydi, va 24 soatdan keyin savdo to'xtaydi.

## 6. Qaror uchun variantlar

| | Nima qilinadi | Ish hajmi | Qachon |
|---|---|---|---|
| **A. Bitta baza, tartibga keltirilgan** | Indekslar; ulanishlar puli (PgBouncer); kassa va market uchun alohida PHP guruhlari; pollingda "o'zgarmagan bo'lsa bo'sh javob" va Reverb'ni yoqish; katalog keshi; har so'rovdagi ortiqcha yozuvni olib tashlash | 1 to'lqin | Hozir |
| **B. Jurnallarni ajratish** | Audit, fiskal va bildirishnomalarni oylar bo'yicha bo'lish; eskisini arxivlash; uzluksiz zaxira (PITR) | 1 to'lqin | A dan keyin |
| **C. Do'konlar orasidagi himoya** | Har jadvalga do'kon belgisi, avtomatik filtr, baza darajasidagi himoya (RLS) | 1–2 to'lqin | B bilan birga yoki keyin |
| **D. Do'konlarni bir nechta bazaga bo'lish** | Do'konlar guruhlari alohida bazalarda | 3+ to'lqin | Faqat belgilar paydo bo'lsa |
| **E. Lokal rejim** | Kassalar do'kon ichidagi markaz orqali ishlaydi | 3–5 to'lqin | Internetsiz butun kun ishlash talab bo'lsa |

D ga o'tish belgilari (A–C qilingandan keyin o'lchanadi): eng band paytda baza protsessori 60 foizdan oshsa; baza
500 GB – 1 TB ga yetsa; zaxiradan tiklash 1–2 soatdan oshsa. C varianti D ga tayyorgarlik ham bo'ladi: har jadvalda
do'kon belgisi bo'lsa, keyin bo'lish osonlashadi.

Har do'konga alohida baza varianti tavsiya etilmaydi: market ro'yxati, qidiruv, mijozning turli do'konlardagi
buyurtmalari, kuryerlar, billing va boshqaruv paneli buzilib qoladi.

## 7. Tavsiyam

1. **A ni hozir boshlash.** Eng arzon va eng katta foyda. Tugagach sinovni qayta o'tkazamiz va haqiqiy yutuqni o'lchaymiz.
2. Keyin **B va C**. C xavfsizlik uchun ham kerak: bugun do'konlar orasidagi ajratish faqat qo'lda yozilgan 611 ta shartga
   tayanadi, bittasi unutilsa bir do'kon boshqasining ma'lumotini ko'radi.
3. **D ni kechiktirish**, yuqoridagi belgilar bo'yicha qaror qilish.
4. **E** bo'yicha sizdan javob kerak: internetsiz butun kun ishlash talabmi?

## 8. Bu sinov nimani isbotlamadi

- Mashina kichik: 4 yadro, PHP, Postgres va yuk beruvchi dastur bitta joyda. Raqamlar nisbiy xarajatni ko'rsatadi,
  haqiqiy server sig'imini emas.
- Ulanishlar puli (PgBouncer) o'zi sinalmadi. Undan kutilayotgan yutuq taxmin, A to'lqinidan keyin o'lchanadi.
- Baza xotiradan kichik edi (2,2 GB baza, 16 GB xotira). Xotiradan katta bazada o'qish sekinroq bo'lishi mumkin.
- Har do'konda 30 kunlik tarix bor edi, bir yillik emas.
- Sinalmagan: ofitsiant telefonlari, QR va karta to'lovlari, yetkazib berish, smena yopish, boshqaruv paneli,
  ko'p soatlik uzluksiz yuk.
- Do'konlar orasidagi himoya (C) sinovda emas, kod tekshiruvida aniqlangan.

Haqiqiy sig'imni bilish uchun staging server kerak: shu skriptlar o'sha yerda o'zgarishsiz ishlaydi.
