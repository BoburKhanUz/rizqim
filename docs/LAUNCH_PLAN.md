# Ishchi versiyaga chiqish rejasi

Sana: 2026-11-15. Egasi "Baza va masshtab" ishlarini boshlashga rozi bo'ldi (15.11.2026) va ilova ishchi versiyaga
chiqishi kerakligini aytdi. Bu hujjat ketma-ketlikni belgilaydi. Asoslar: [scale/TAHLIL.md](scale/TAHLIL.md) (sinov
natijasi), [research/reports/POS va market masshtab yechimlari.md](research/reports/POS%20va%20market%20masshtab%20yechimlari.md)
(boshqalar qanday yechgani), [ROADMAP.md](ROADMAP.md) (umumiy bosqichlar).

## Maqsad

Ikki marra bor:

1. **Pilot** — 1–3 ta haqiqiy do'kon, haqiqiy tushum bilan ishlaydi.
2. **Ochiq ishga tushirish** — istalgan do'kon ulanadi, market ham, POS ham.

Formatlar bo'yicha funksiyalar tayyor (1–4 va 6-bosqichlar tugagan). Qolgan ish ikki xil: tizimni yuk va nosozlikka
tayyorlash (kod, buni biz qilamiz) va tashqi narsalar (server, fiskal provayder, to'lov shartnomalari, qurilmalar;
bularni egasi beradi).

## Ketma-ketlik

Har to'lqin bitta PR: kod, testlar, ikki mustaqil tekshiruv, yashil CI. Merge faqat egasi aytganda.

| # | To'lqin | Nima qilinadi | Natija qanday o'lchanadi |
|---|---|---|---|
| M1 | **Tez yutuqlar** | Bazaga ulanishlar puli; sinovda tasdiqlangan indekslar; kassa va market uchun alohida PHP guruhlari va market so'rovlariga chegara; Telegram tarqatishni sekinlatish; har so'rovdagi ortiqcha yozuvni olib tashlash | Sinov qayta o'tkaziladi: market to'lqinida kassa javobi, baza protsessori |
| M2 | **Jonli aloqa va kesh** | WebSocket (Reverb) standart yoqiladi; takroriy so'rovlarga "o'zgarmagan bo'lsa bo'sh javob"; Reverb qotib qolsa o'zi sezadi; do'konlar ro'yxati va katalog keshdan beriladi; aloqasi uzilgan do'kon marketda buyurtma olishni o'zi to'xtatadi | Bir do'konning daqiqalik so'rovi (bugun 32), market so'rovlarining bazaga yuki |
| M3 | **Ishonchlilik** | Jurnallar (audit, fiskal, bildirishnoma) oylar bo'yicha bo'linadi; uzluksiz zaxira (istalgan daqiqaga qaytarish); zaxiradan tiklash soat bilan sinaladi; ogohlantirishlar (baza, navbat, Reverb, disk) | Tiklash vaqti; ma'lumot yo'qotish oynasi (maqsad: 5 daqiqadan kam) |
| M4 | **Do'konlar orasidagi himoya** | Har jadvalda do'kon belgisi; avtomatik filtr; baza darajasidagi himoya (RLS); ilova bazaga superfoydalanuvchi sifatida ulanmaydi | Maxsus testlar: bir do'kon boshqasining ma'lumotini ko'ra olmaydi |
| M5 | **Internetsiz rejim va yangilanish** | 24 soatlik qattiq to'xtashni qayta ko'rish; chegirmani internetsiz ruxsat qilish; navbatdagi amallarni imzolash; kassada mahalliy zaxira; ilovalar uchun minimal versiya va yangilanish xabari | Internetsiz ssenariy testlari |
| P | **Pilotga tayyorlash** | Staging serverga o'rnatish va tekshirish; ilova qismini bir nechta serverda ishlatish tartibi; haqiqiy fiskal drayver; Click/Payme haqiqiy kalitlari; qurilmalarda tekshiruv ro'yxati ([HARDWARE_TEST.md](HARDWARE_TEST.md)); staging'da yuk sinovi | Tekshiruv ro'yxati to'liq yopiladi |
| L | **Ochiq ishga tushirish** | **1 000 do'konlik yuk sinovi staging serverda** (sinov skriptlari bilan, tushlik va market to'lqini birga); pilot xulosalari bo'yicha tuzatishlar; navbatchilik tartibi; hamkorlar uchun qo'llanmalar yangilanadi | Pilot do'konlarda 2 hafta jiddiy nosozliksiz |

M1–M5 tashqi narsalarga bog'liq emas, ketma-ket qilinadi. P bosqichi egasidan keladigan narsalarga bog'liq va ular
tayyor bo'lishi bilan M to'lqinlari orasida boshlanishi mumkin.

## Marralar uchun shartlar

**Pilot uchun kerak:** M1, M2, M3 va P. M4 va M5 pilot davomida qilinishi mumkin, chunki pilot do'konlari kam va
tanish.

**Ochiq ishga tushirish uchun kerak:** hammasi, shu jumladan M4. Notanish do'konlar ulanadigan bo'lsa, do'konlar
orasidagi himoya shart.

**Keyinga qoldirilgan:** bazani do'konlar bo'yicha bo'lish va to'liq lokal server. Bo'lishga qaytish belgilari:
M1–M3 dan keyin ham baza protsessori eng band paytda 60 foizdan oshsa, baza 500 GB – 1 TB ga yetsa yoki zaxiradan
tiklash 1–2 soatdan uzoq cho'zilsa. To'liq lokal rejim faqat do'konlar butun kun internetsiz ishlashni talab qilsa.
5-bosqich (integratsiyalar) va 7–10-bosqichlar (kuryer, mobil ilovalar, analitika) pilotdan keyin, bozor talabiga
qarab.

## Egasidan kerak bo'ladiganlar

| Nima | Qaysi marra uchun | Kelmasa nima bo'ladi |
|---|---|---|
| Server (staging va ishchi) va domen | Pilot | Haqiqiy sig'im o'lchanmaydi, hech kim ulanolmaydi |
| Fiskal provayder: API hujjati va sinov kalitlari | Pilot | Haqiqiy tushum bilan ishlab bo'lmaydi (VM 393) |
| Click va Payme merchant kalitlari | Pilot | Onlayn to'lov ishlamaydi, faqat naqd va alohida terminal |
| Sinov qurilmalari: Android planshet yoki Sunmi, telefon, printer | Pilot | Qurilma kodi faqat CI'da yig'ilgan, haqiqiy qurilmada sinalmagan |
| Pilot do'kon(lar) | Pilot | — |
| Server qayerda joylashishi (shaxsiy ma'lumotlar qonuni bo'yicha yurist fikri) | Ochiq ishga tushirish | Huquqiy xavf |
| Do'konlar internetsiz butun kun ishlashi shartmi | M5 hajmi | M5 tor doirada qilinadi |
| Har do'konning o'rtacha kunlik buyurtmasi (POS va market alohida) | M3 sig'im hisobi | Taxmin bilan hisoblanadi: 150 POS + 20 market buyurtma |

## Maqsadli hajm

Egasi qarori (15.11.2026): POS taxminan **1 000 ta oshxonaga** o'rnatiladi va ularning hammasi marketda ham ishlaydi.
Bu ixtiyoriy maqsad emas, biznes sharti: 1 000 ga yaqin oshxona ulanmasa loyiha o'zini oqlamaydi. Shuning uchun ochiq
ishga tushirishdan oldin tizim shu hajmda sinab ko'rilgan bo'lishi shart.
Sig'im shu hajmga hisoblanadi. Taxmin (har do'kon kuniga 150 POS va 20 market buyurtma, M1 dan keyingi o'lchangan
xarajatlar asosida; haqiqiy serverda o'lchanmagan):

| Do'konlar | Eng band paytda so'rov/s | PHP yadrolari | Baza yadrolari | Baza o'sishi, yiliga |
|---|---|---|---|---|
| 100 | ~105 | 4-5 | 1-2 | ~75 GB |
| 300 | ~315 | 12-13 | 3-4 | ~225 GB |
| 1 000 | ~1 050 | ~40 | ~11 | ~750 GB |

M2 dan keyin qayta o'lchandi ([scale/RESULTS_M2.md](scale/RESULTS_M2.md), 9-bo'lim; o'sha test mashinasidan taxmin):

| Do'konlar | PHP yadrolari | Baza yadrolari |
|---|---|---|
| 100 | ~2 | ~0,7 |
| 300 | ~6 | ~2 |
| 1 000 | ~19-20 | ~6,5-7 |

Bundan chiqadigan xulosalar: (1) 1 000 do'kon bitta serverga sig'maydi, ilova qismi bir nechta serverda ishlashi kerak
(P bosqichiga qo'shiladi); (2) M2 so'rovlar sonini kamaytiradi, shuning uchun yadrolar soni shu jadvaldagidan kam
bo'lishi kutiladi; (3) M3 (jurnallarni bo'lish va arxivlash) majburiy: jurnallarsiz o'sish yiliga taxminan 315 GB;
(4) bazani bo'lish savoli 1 000 do'konda ikkinchi yilda qayta ko'riladi.

## Holat

| To'lqin | Holat |
|---|---|
| M1 Tez yutuqlar | **Tugadi** (PR #24, 15.11.2026). Natija: [scale/RESULTS_M1.md](scale/RESULTS_M1.md) |
| M2 Jonli aloqa va kesh | **Tugadi** (PR #25, 17.11.2026). Natija: [scale/RESULTS_M2.md](scale/RESULTS_M2.md): market yuki 2-3 baravar kam, sokin vaqtda so'rovlar 73 % kam, tushlikda kassa tezligi M1 darajasida va ekranlar 2-5 soniyada yangilanadi |
| M3 Ishonchlilik | Boshlandi (17.11.2026) |
| M4, M5, P, L | Navbatda |
