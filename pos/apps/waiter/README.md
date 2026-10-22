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
- **Aloqa yo'q**: qizil lenta, ilova faqat ko'rish rejimida (yozuvlar navbatga qo'yilmaydi: 3-to'lqin, P3-31); aloqa
  o'zi qaytadi. Amal paytida tarmoq uzilsa, xuddi shu amal qayta bosilganda o'sha `Idempotency-Key` yuboriladi.

## Schyot so'rash

Backend'da alohida "schyot so'raldi" endpointi yo'q. Ofitsiant o'z buyurtmasi uchun schyotni tuzishi mumkin
(`POST /pos/orders/{uuid}/bills`, `mode: single`, `orders.manage`): ilova shuni yuboradi, kassa `order.bill_changed`
hodisasi va `GET /pos/orders/{uuid}/bills` orqali ochiq schyotni ko'radi, chiqaradi va to'lovni oladi. Ofitsiant
schyotni *chiqarmaydi* (`/print` chaqirilmaydi): chiqarish buyurtmani qulflaydi va telefonda printer yo'q. Buyurtma
schyotdan keyin o'zgarsa "Schyot eskirdi: qayta so'rang" ko'rsatiladi.

## Ishga tushirish

```bash
cd pos/apps/waiter && dart run tool/demo_server.dart --port 8789   # soxta backend: Botir, Aziz, tayyor taom, ofitsiant qoidasi
flutter run -d linux \
  --dart-define=RIZQIM_API_URL=http://127.0.0.1:8789/api \
  --dart-define=RIZQIM_REVERB_KEY=pos-key --dart-define=RIZQIM_REVERB_URL=ws://127.0.0.1:8789
```

Telefon panelda kassa turidagi kod bilan ulanadi (soxta backend'da istalgan 8 belgi). Ofitsiant Botir — PIN 1357,
Aziz — 1111, menejer Malika — 5926. Aloqa uzilishini sinash: `curl -X POST "http://127.0.0.1:8789/__control/offline?on=1"`.

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
