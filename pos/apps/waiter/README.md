# pos/apps/waiter

Ofitsiant ilovasi (telefon, bir qo'l bilan): ulash, PIN (avval xodimlar ro'yxati, keyin klaviatura), ekran qulfi,
"Mening xonalarim", buyurtma olish, bosqich qo'shish, oshxonaga yuborish, "Berildi", schyot so'rash va boshqa
ofitsiantning buyurtmasida menejer tasdig'i. Ofitsiantda to'lov va chek chiqarish yo'q.

## Ekranlar

- **Mening xonalarim** (`lib/src/features/rooms`). Uch yorliq: *Mening* (`waiter_id` = men), *Hammasi* (zallar bo'yicha,
  boshqa ofitsiantning buyurtmasida uning ismi), *Bo'sh*. Kartochka: xona yoki stol, mehmonlar, qancha vaqt ochiq, jami,
  holat. Oshxona "tayyor" degan taomlar yashil ramka va yozuv bilan ajraladi (zal xaritasida qator holati yo'q, shuning
  uchun o'z buyurtmalarim to'liq olinadi). Yangilanish: `PosRealtime` hodisalari + `PosRefreshScheduler` (jonli bo'lmasa
  polling). Bo'sh joyni bosish: mehmonlar soni → buyurtma ochiladi → buyurtma ekrani.
- **Buyurtma** (`lib/src/features/order`). Bosqichlar (qatorlar holat belgisi bilan: yuborilmagan, ushlab turilgan,
  oshxonada, tayyor, berildi, o'chirildi), hisob (hamma summa serverdan). Amallar:
  - *Taom qo'shish*: choy-non tez tugmalari, toifalar, qidiruv, qozon qoldig'i va stop-list, dona uchun stepper, kg va
    kasr uchun klaviatura (qadam va eng kam miqdor tekshiriladi), taom oynasi (variant, qo'shimchalar: majburiy / eng kam /
    eng ko'p, izoh), savat, "Hozir yuborish" yoki "Ushlab turish". *Yuborish* bosqichni oshxonaga jo'natadi.
  - *Berildi* (bitta qator yoki "Hammasini berdim"), yuborilmagan qatorni oshxonaga yuborish yoki olib tashlash.
  - Menyu (⋮): mehmonlar soni, boshqa joyga ko'chirish.
  - *Schyot so'rash*: pastda.
- **Boshqa ofitsiantning buyurtmasi** faqat ko'rish uchun. "Tahrirlash (menejer tasdig'i)" bosilgach har bir amal server
  403 `approval_required` qaytarsa menejer (ega) PIN oynasini ochadi (`withApproval`), token bilan qayta yuboriladi.
  `approver_not_allowed`, muddati o'tgan yoki ishlatilgan tasdiq aniq o'zbekcha matn bilan aytiladi.
- P3-35: o'zgarish to'lovi olingan schyotga tegsa (409 `paid_bill_affected`) — "kassirga ayting: u avval to'lovni
  qaytaradi" matni, buyurtma va schyot holati qayta yuklanadi. `order.items_changed` ning ikki xabari (`round_added`,
  `sent`) bitta qayta yuklashga birlashadi.
- **Aloqa yo'q**: qizil lenta, ilova faqat ko'rish rejimida (yozuvlar navbatga qo'yilmaydi: internetsiz rejim faqat
  kassada, P3-31); aloqa o'zi qaytadi. Amal paytida tarmoq uzilsa (javob kelmadi), xuddi shu amal qayta bosilganda
  **aynan o'sha so'rov** — o'sha `Idempotency-Key` va o'sha `version` bilan — yuboriladi: oraliqda buyurtma yangilangan
  bo'lsa ham server uni bir marta bajaradi (bosqich ikki marta tushmaydi). Server javob bersa (rad etdi, versiya
  to'qnashuvi) keyingisi yangi so'rov.

## Restoran va kafe (P6, 2-to'lqin)

Har tugma faqat filialning samarali moduli yoqilganda ko'rinadi (`bootstrap.format.modules`, `lib/src/common/modules.dart`);
modul o'chiq bo'lsa choyxona oqimi o'zgarmaydi, server 403 `module_disabled` bersa oddiy matn chiqadi.

- **Kurslar** (`courses`): taom qo'shishda "Kurs: Kurssiz · 1 · 2 · 3 · Yana (4–9)" — qatorning kursi; kurssiz qator darhol
  oshxonaga. Buyurtma ekranida "Kurslar" bloki: har kurs holati (kutmoqda, yuborildi · tayyorlanmoqda, tayyor, berildi) va
  "N-kursni yuborish". Oshxona kursni tugatsa (`order.course_ready`, faqat o'z buyurtmam) — "2-kurs tayyor — 5-stol" xabari,
  tovush va tebranish bilan, "Ochish" (`lib/src/features/alerts`).
- **Mehmon raqami** (`guest_split`): qo'shishda "Mehmon: Umumiy · 1 … N" (N — stol mehmonlari), savatda va yuborilgan qatorda
  belgini bosib o'zgartiriladi (`POST …/items/{id}/seat`).
- **Six ×N** (`skewer_buttons`): taom ostida ×2 … ×10 tugmalari, mangal vaqti ("~15 daq").
- **Og'irlikni keyin kiritish** (`weight_later`): qatorda "Tortiladi", buyurtmada "Og'irlik kutilmoqda (N)"; `orders.manage`
  bo'lsa "Og'irlikni kiritish" (birinchi tortish; qayta tortishni kassir yoki menejer qiladi).
- **Boshqa ofitsiantga berish** (`waiter_transfer`): buyurtma menyusida; `orders.transfer` bo'lmasa menejer PIN'i so'raladi.
  Eski ofitsiantga "… boshqa ofitsiantga o'tkazildi", yangisiga "… sizga berildi" (`order.transferred`).
- **Mening choy pulim** (`tips`): bosh menyuda; bugungi (yoki shu smenadagi) o'z choy pullari (`GET /pos/tips`).
- **Ishga keldim / Ishdan ketdim** (`bootstrap.staff_pay.clock`): kelmagan bo'lsa tepada lenta, ketish — menyuda. Internetsiz
  belgilanmaydi ("internet kerak").
- **Hostes stoli** (`hostess` moduli, hostes roli yoki `hostess.manage`; `lib/src/features/hostess`): hostesning bosh
  sahifasi, menejerga pastda "Hostes" yorlig'i. Kutish ro'yxati (yozish, chaqirish, joylashtirish — stol tanlab, `orders.manage`
  bo'lsa buyurtmani ham ochib, ketdi, kelmadi; `waitlist.changed` bilan jonli), stollar ("Tozalash" → "Tayyor"), bugungi
  bronlar (bo'nak yetmasa belgi) va "Bron qo'shish" (ega/menejer "yo'lda kelayotgan mehmon" — `skip_lead_time`). Telefonda uch
  yorliq, planshetda ikki ustun.

## Schyot so'rash

"Schyot so'rash" — `POST /pos/orders/{uuid}/request-bill` (P3-26, `OrderController.requestBill`): kassaga
`order.bill_requested` hodisasi boradi, zal xaritasida "Schyot so'raldi" ko'rinadi. Schyotni kassir tuzadi, chiqaradi
va to'lovni oladi; ofitsiant schyot tuzmaydi va chiqarmaydi (telefonda printer yo'q). Takroriy so'rov o'zgarishsiz
qaytadi. Kassir schyotni chiqargandan keyin buyurtma o'zgarsa "Schyot eskirdi" ko'rsatiladi.

## Ishga tushirish

```bash
cd pos/apps/waiter && dart run tool/demo_server.dart --port 8789   # soxta backend: Botir, Aziz, tayyor taom, ofitsiant qoidasi
flutter run -d linux \
  --dart-define=RIZQIM_API_URL=http://127.0.0.1:8789/api \
  --dart-define=RIZQIM_REVERB_KEY=pos-key --dart-define=RIZQIM_REVERB_URL=ws://127.0.0.1:8789
```

Telefon panelda kassa turidagi kod bilan ulanadi (soxta backend'da istalgan 8 belgi). Ofitsiant Botir — PIN 3691,
Aziz — 7305, menejer Malika — 5926. Aloqa uzilishini sinash: `curl -X POST "http://127.0.0.1:8789/__control/offline?on=1"`.

Umumiy soxta backend (`pos_data`): `cd pos/packages/pos_data && dart run tool/fake_pos_server.dart --port 8788`; unda
ofitsiant qoidasi yo'q.

## Kelishuv

- Bog'liqliklar `WaiterScope.of(context)`: `data` (`PosData`: buyurtmalar, zal, menyu, schyot), `session`, `realtime`.
  Ustidan sahifa — `WaiterScope.push(context, page)`, pastdan oyna — `showWaiterSheet` (scope saqlanadi).
- Menejer tasdig'i — `withApproval(context, (token) => ...)`.
- Ekran qulfi, ulash va PIN — `lib/src/shell`, `lib/src/app.dart`.

## Sinov

```bash
cd pos/apps/waiter && flutter test
```

Widget testlari soxta backend ustida butun ilovani ishga tushiradi (`test/support/harness.dart`): yorliqlar va filtrlar,
joyni ochish, bosqich (dona, kg, qo'shimchalar), yuborish, berildi, schyot so'rash, boshqa ofitsiantning buyurtmasi va
tasdiq, aloqa yo'q lentasi.

Restoran qismi: `test/restaurant_test.dart` (soxta backend'ning restoran yo'llari — `test/support/restaurant.dart`) va
suratlar `test/restaurant_goldens_test.dart` (`test/goldens/restaurant_*.png`; o'qiladigan suratlar uchun
`WAITER_SHOTS=<papka> flutter test --update-goldens test/restaurant_goldens_test.dart`).
