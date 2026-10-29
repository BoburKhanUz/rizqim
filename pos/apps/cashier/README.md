# pos/apps/cashier

Kassa ilovasi (Android planshet, Sunmi, Windows; sinov uchun Linux): ulash, PIN, ekran qulfi, menejer tasdig'i, zal
xaritasi va buyurtma muharriri (P3-21), schyot, to'lov, smena, bronlar, nasiya, navbat va sozlamalar (P3-22).

## Ishga tushirish

```bash
cd pos/packages/pos_data && dart run tool/fake_pos_server.dart --port 8788        # yoki haqiqiy backend
cd pos/apps/cashier && flutter run -d linux \
  --dart-define=RIZQIM_API_URL=http://127.0.0.1:8788/api \
  --dart-define=RIZQIM_REVERB_KEY=pos-key --dart-define=RIZQIM_REVERB_URL=ws://127.0.0.1:8788
```

Soxta backend'da ulash kodi — istalgan 8 belgi, PIN: kassir 4827, menejer 5926, ofitsiant 3691.

## Qanday ishlaydi

1. **Ulash** — panelda Sozlamalar → Qurilmalar → «Ulash kodini yaratish» (turi: kassa), 8 belgili kod. Token qurilmaning
   maxfiy omborida.
2. **PIN** — xodim ismini tanlab PIN teradi (kassirlar birinchi). Ko'p xato — kutish vaqti ko'rsatiladi.
3. **Ish ekrani** — bootstrap yuklanadi (filial, printerlar, zallar, smena; menyu fonda keshga olinadi). Yuqorida:
   do'kon va filial, smena belgisi (bosilsa — Smena), aloqa ("Jonli", "Har 15 soniyada", "Aloqa yo'q"), sinxronlash
   nishoni, xodim menyusi (qulflash, mavzu, menejer va egaga — «Terminalni uzish») va qulf tugmasi.
4. **Ekran qulfi** — filialning `screen_lock_minutes` (standart 5 daqiqa, 0 — qulflanmaydi) davomida tegilmasa PIN
   ekraniga qaytadi, ochiq oyna va sahifalar yopiladi.
5. **Menejer tasdig'i** — `withApproval(context, (token) => ...)`: server tasdiq so'rasa menejer o'z PIN'ini teradi.

## Bo'limlar kelishuvi (P3-21, P3-22)

| Bo'lim | Fayl (vidjet) | Egasi |
|---|---|---|
| Zal | `lib/src/features/floor/floor_page.dart` (`FloorPage()`) | P3-21 |
| Buyurtma (ustidan ochiladi) | `lib/src/features/order/order_page.dart` (`OrderPage({orderUuid, tableId})`) | P3-21 |
| Navbat | `lib/src/features/queue/queue_page.dart` (`QueuePage()`) | P3-22 |
| Bronlar | `lib/src/features/bookings/bookings_page.dart` (`BookingsPage()`) | P3-22 |
| Nasiya | `lib/src/features/accounts/accounts_page.dart` (`AccountsPage()`) | P3-22 |
| Smena | `lib/src/features/shift/shift_page.dart` (`ShiftPage()`) | P3-22 |
| Sozlamalar | `lib/src/features/settings/settings_page.dart` (`SettingsPage()`) | P3-22 |
| Schyot (ustidan ochiladi) | `lib/src/features/billing/billing_page.dart` (`BillingPage({required orderUuid})`) | P3-22 |
| Tez kassa | `lib/src/counter/counter_page.dart` (`CounterPage()`) | P6-12 |

Feature faqat o'z papkasini o'zgartiradi; marshrutlar (`shell/destinations.dart`, `shell/routes.dart`) va qobiq —
P3-21 da. Bog'liqliklar `PosScope.of(context)` dan: `data` (`PosData`), `session`, `realtime`, `printing`
(`receiptPrinter`, `kitchenPrinterFor(stationId)`), `go(CashierDestination...)`. Ustidan sahifa —
`CashierRoutes.openOrder / openBilling / push`. Testlar: `pos_data/testing.dart` va `test/support/harness.dart`.

## Tez kassa va telefon rejimi (`counter`, P6-12)

- Birinchi ekran filial formatidan (`bootstrap.format`, docs/POS_SPEC.md 2.15): `counter` rejimi yoki
  `first_screen: quick_counter` — «Tez kassa»; `hybrid` (lag'monxona, kafe) — «Tez kassa» va «Zal» yonma-yon bo'limlar;
  `table` — avvalgidek zal xaritasi. Choyxona (va `format` bermaydigan eski server) o'zgarmaydi: «Tez kassa» bo'limi
  yo'q, zal xaritasidagi «Tez kassa» tugmasi ishlaydi. `counter` rejimida zal (stol) bo'lmasa «Zal» bo'limi yo'q.
- Menyu panjarasi (qidiruv, kategoriyalar, «Tez-tez»), bosish = +1, tez miqdor ×2…×10 (bir bosishga), majburiy
  qo'shimcha — oyna, uzoq bosish — ixtiyoriy qo'shimchalar; savat yonda (planshet) yoki pastki panel va varaq (telefon).
  «To'lash»: navbat raqami olinadi (`queue_numbers`, `SyncStore.takeQueueNumber`, internetsiz ham), buyurtma taomlar
  bilan **darhol oshxonaga** yuborib ochiladi (A4), bitta schyot va to'lov sahifasi; to'langach talon (`QueueSlip`)
  chop etiladi yoki raqam ekranda, «Keyingi mijoz» (yoki keyingi taom) — yangi savat. Bitta taom — 3 bosish; 20 ta
  odatiy buyurtma o'lchovi — `test/counter_speed_test.dart`.
- To'lov sahifasidan to'lovsiz qaytilsa — «To'lov kutilmoqda»: «To'lash» (o'sha schyot) yoki «Bekor qilish» (sabab,
  «O'CHIRILDI» markasi).
- `queue_calling`: «Tayyor» ro'yxati (`GET /pos/queue`) — «Berildi», «Qayta chaqirish»; KDS'siz kassada tayyorlanayotgan
  raqamga «Tayyor». `queue.ready` (va qayta chaqiruv) — ovoz va ekranda e'lon.
- `phone_mode` (somsaxona, tamaddixona): portret telefon — bir ustunli panjara, katta tugmalar, smena bir bosishda
  ochiladi (boshlang'ich naqd 0) va yopiladi (Z sahifasi), printer ixtiyoriy: chek va raqam ekranda, fiskal chek
  havolasi nusxalanadi.
- `customer_display`: savat, to'lov (summa, raqam), «Rahmat» va bo'sh ekran mijoz ekraniga (`PosData.displayFeed`).
- Modullar bo'yicha yashiriladi: «Bronlar» (`bookings`), «Nasiya» bo'limi va to'lov turi (`customer_accounts`), choy-non
  tez tugmalari (`guest_items`), olib tashlangan xizmat haqini qaytarish (`service_charge`, stolovaya qulfi).

## Zal xaritasi (`features/floor`)

- Zallar — yuqorida yorliqlar (`Asosiy zal · 3/4` — band/hammasi). Xona, tapchan va stol holati rang, ikonka va yozuv
  bilan: bo'sh, band, bron (bir soat ichida), schyot chiqdi, uzoq ochiq. Belgilar: necha soat ochiq, mehmonlar/o'rinlar,
  ofitsiant, summa, bron vaqti. Panelda xarita chizilmagan bo'lsa joylar to'rga teriladi.
- O'ngda — stolsiz va olib ketish buyurtmalari; «Tez kassa» — stolsiz buyurtma (olib ketish yoki zalda): birinchi
  «Yuborish» yoki «Saqlash» uni ochadi (bo'sh buyurtma qolmaydi).
- Bo'sh joy → mehmonlar soni va ofitsiant (ixtiyoriy) → buyurtma ochiladi → muharrir. Bron kutilayotgan joyda —
  ogohlantirish va «Bronlar» (bron bo'yicha kelganlar «Keldi» bilan ochiladi). Band joy → buyurtmasi; tapchan yoki
  stolda bir nechta bo'lsa — tanlash yoki yangi buyurtma (uzoq bosish ham).
- `order.*` va `booking.changed` hodisalarida va polling bilan yangilanadi (`PosRefreshScheduler`).

## Buyurtma muharriri (`features/order`)

- Chapda menyu: qidiruv, «Tez-tez» (shu kassada ko'p qo'shilganlar), kategoriyalar, katta tugmalar (narx, qozon
  qoldig'i, «Tugadi»/«Stop-listda»). O'ngda: bosqichlar (raqami, vaqti, kim qo'shgani; qator holati — yuborilmagan,
  ushlab turilgan, oshxonada, tayyor, berildi, o'chirildi), yangi bosqich, choy-non tez tugmalari, jami (xona puli,
  minimal summaga qo'shimcha, xizmat haqi, joy narxi; olib tashlanganlari sababi bilan).
- Taom bosilsa — yangi bosqichga (bir xili bo'lsa soni oshadi); kg taom — klaviatura (0.5, 0.7, 0.75, 1, 2, qadam va eng
  kam miqdor); majburiy qo'shimchali taom — qo'shimchalar oynasi; uzoq bosish — izoh, mehmon (o'rin), qo'shimchalar,
  «hozir / ushlab turish». «Yuborish» — bosqich va oshxonaga bitta so'rovda; «Saqlash» — yuborilmaydi.
  Tezlik: ochiq buyurtmaga 3 ta taom qo'shib yuborish — 4 bosish (kg taom bilan — 6).
- Qator bosilsa: «Hozir oshxonaga yuborish», «Berildi», «O'chirish» (yuborilgan bo'lsa sabab va menejer tasdig'i).
  Yuqorida: mehmonlar, ko'chirish, birlashtirish, bekor qilish. «Schyot» — `CashierRoutes.openBilling`.
- Boshqa qurilma buyurtmani o'zgartirgan bo'lsa (409): buyurtma qayta yuklanadi; bosqich, yuborish, «berildi» va
  mehmonlar o'zi takrorlanadi, qolganlari uchun kassirga aytiladi. Tarmoq xatosidan keyin bosqich o'sha
  `Idempotency-Key` bilan qayta yuboriladi.
- Sex markalari: yuborilgan qatorlar sex va bosqich bo'yicha `kitchenPrinterFor(stationId)` ga, o'chirilgan qator —
  «O'CHIRILDI». Chop etilmasa — «Marka chop etilmadi» banneri («Qayta urinish», «Yopish»); sotuv to'xtamaydi.

## Schyot va to'lov (`features/billing`)

- Schyot (pre-check): qatorlar, xona puli, minimal summaga qo'shimcha, xizmat haqi, joy puli, chegirma, jami, avans,
  kishi boshiga, «Bu fiskal chek emas». Bo'lish: bitta, teng N (qoldiq tiyin 1-schyotga), taom bo'yicha (kg ulushi —
  «Ulush»). Chop etish — `layoutPreCheck`; qayta — «NUSXA» (`pos.reprint_bill` yoki menejer tasdig'i).
- To'lov (`PaymentPage`): naqd (berilgan pul, qaytim, tez summalar), karta (turi va tranzaksiya raqami majburiy), QR,
  bron avansidan (birinchi taklif qilinadi), nasiyaga (hisob qidirish yoki ochish, limit, menejer tasdig'i); aralash
  («Qo'shish»). To'langach sotuv cheki (`FiscalReceipt` sale) chop etiladi, naqdda kassa qutisi ochiladi; chek kutilsa
  — «Fiskal kutmoqda» va «Qayta urinish». Hamma schyot to'langach buyurtma yopiladi va kassa zalga qaytadi.
- Qaytarish: yopilgan buyurtma — «Navbat → Bugun yopilgan» → schyot → «Pulni qaytarish» (sabab, `pos.refund` yoki
  tasdiq), qaytarish cheki. P3-35: naqd, karta, QR, **bron avansi va nasiya** qismlari (`refund_dialog.dart`: har to'lovdan
  qolgani — schyotlardagi `refunds` bo'yicha; tanlangan to'lov uchun pul qayerga borishi yoziladi — avans bronga
  qaytadi, nasiya qarzdan ayiriladi, kassadan naqd chiqmaydi, kassa qutisi faqat naqdda). Xato (`more_than_debt`,
  `reverse_exceeds_*`, `account_unknown`) dialogda qoladi. Buyurtmaning hamma to'lovi qaytarilgan va u ochiq
  (`payment_status = refunded`) — schyot ekranida «Buyurtmani bekor qilish» (sabab, tasdiq, «O'CHIRILDI» markalari).
- P3-35 javoblari: `paid_bill_affected` (o'chirish, mehmonlar, ko'chirish, birlashtirish, xizmat qatori to'langan
  schyotga tegadi) — o'zbekcha matn va buyurtma qayta yuklanadi (`PosException.needsOrderReload`); `shift_closed`
  (to'lov yoki qaytarish paytida smena Z bilan yopildi) — to'lov ekranida «Smenani ochish» bloki, smena serverdan
  qayta o'qiladi (`reloadShift`).
- Chek fiskal belgisi, raqami, kassa va QR — to'lov javobidagi `fiscal.receipt` dan, bo'lmasa
  `GET /pos/fiscal-receipts/{uuid}` dan (P3-26: kassir sessiyasi bilan). Ikkalasi ham o'qilmasa chek schyotdan tuziladi
  va fiskal belgi o'rniga «kutilmoqda» yoziladi.
- Chek printeriga ketgan hujjat chiqmasa (qog'oz tugadi, printer javob bermadi) ish ekranlari tepasida «Chek chop
  etilmadi» banneri («Qayta urinish», «Yopish»). Kassa qutisi qog'oz tugagan printerda ham ochiladi.
- Pul amallari (to'lov, qaytarish, kirim-chiqim, nasiya hisobi) javobsiz qolsa (tarmoq), qayta bosish **aynan o'sha
  so'rovni** — o'sha kalit, tana va menejer tasdig'i bilan — yuboradi (`LostRequest`, `shell/approval_dialog.dart`):
  server bir marta bajaradi, PIN qayta so'ralmaydi.
- Schyotlar: serverda «to'lanmoqda» holati yo'q — to'lov olingan schyot chek chiqquncha `open`/`printed` bo'lib qoladi
  (`PosBill.isTaken`, `awaitingReceipt`). Bo'lishda va «Qisman to'langan» / «Fiskal kutmoqda» belgilarida shu
  hisobga olinadi. Bo'lingan schyotlar pre-chekida avans har schyotga ulush bo'lib tushadi (`depositShare`).

## Smena, bronlar, nasiya, navbat, sozlamalar

- **Smena:** ochish (boshlang'ich naqd), xulosa (tushum, to'lov turlari — avans va nasiya alohida, kassadagi naqd),
  kirim va chiqim (bozorlik, ish haqi — kimga, boshqa; `pos.cash_in_out` yoki tasdiq; kassa qutisi), X hisobot
  (ko'rish, chop etish), Z — kupyuralar (200 000 … 100), farq, rad etish sababi (ochiq buyurtma, fiskal kutayotgan chek)
  va majburan yopish (sabab + tasdiq), Z-hisobot chop etiladi.
- **Bronlar:** kun bo'yicha xona × soat taqvimi (bugun va keyingi 6 kun), bo'sh katak — yangi bron (o'sha xona va
  soat; davomiylik — filial sloti), telefon bo'yicha qidiruv, to'qnashuv xabari; bron sahifasi: tasdiqlash, «Keldi»
  (nechta mehmon keldi va joy — bron qilingan xona yoki «Boshqa joy»; `guests`, `dining_table_id`; buyurtma ochiladi —
  `CashierRoutes.openOrder`), kelmadi, bekor qilish, oldindan buyurtma (menyu, kg, tayyor bo'lish
  vaqti), avans (bo'nak cheki), avansni qaytarish yoki ushlab qolish (tasdiq bilan).
- **Nasiya:** qarzdorlar va jami qarz, telefon yoki ism bo'yicha qidiruv, ko'chirma (30/90 kun), qarz to'lovi (kredit
  cheki), hisob ochish va tahrirlash (limit, to'xtatish — `customer_accounts.manage`; kassir — menejer PIN'i bilan,
  to'lov sahifasidagi «Yangi hisob ochish» ham).
- **Navbat:** qabul kutayotgan Telegram/ilova buyurtmalari (ovoz, N daqiqadan oshsa ogohlantirish, qabul qilish, rad
  etish — `orders.cancel`), «Fiskal kutmoqda» ro'yxati (qayta urinish), ochiq buyurtmalar (kanal bo'yicha), bugun
  yopilganlar.
- **Sozlamalar:** printerlar (holati, sinov chop etish, xato ishlarni qayta yuborish; shu qurilmada qog'oz eni va
  «matnni rasm sifatida»), ovoz, qurilma ma'lumoti va terminalni uzish.

## Internetsiz rejim (P3-31)

- Navbat qurilmada: `getApplicationSupportDirectory()/pos_sync` (`main.dart`), `PosData(..., offlineStore:)`.
- Aloqa yo'q yoki navbat hali bo'shamagan bo'lsa ish ekrani tepasida **cheklangan rejim banneri** (`shell/limited_mode.dart`):
  qachondan beri, navbatdagi amallar, 15 daqiqada sariq, 2 soatda qizil, fiskal navbat yoshi (24 / 40 / 48 soat), soat
  farqi, «Yuborish».
- Ishlaydi: zal, yangi buyurtma (lokal raqam `K1-0041`), bosqich, oshxonaga yuborish (marka LAN printerga), o'chirish,
  «Berildi», bitta schyot (pre-chek chop etiladi), naqd va karta to'lovi. O'chirilgan (sababi yozilgan): mehmonlar,
  ko'chirish, birlashtirish, bekor qilish, schyotni bo'lish, qaytarish, QR/avans/nasiya, kassa harakati, Z-hisobot.
- Oflayn to'lovda fiskal bo'lmagan chek chiqmaydi: sotuv cheki sinxronlashda ro'yxatdan o'tadi va kassa uni o'zi chop
  etadi (`PosOffline.salesToPrint` — qurilmada saqlanadi): chek haqiqatan chiqqanda belgilanadi; chek hali tayyor
  bo'lmasa `fiscal.receipt_updated`, keyingi sinxronlash yoki kirishda yana uriniladi; qayta kirganda eski cheklar
  qayta chiqmaydi. Ekran qulfi cheklangan rejimda to'xtaydi (PIN tekshiruvi serverda).
- Onlayn tuzilgan va chiqarilgan schyot internet uzilgach ham chiqariladi va to'lanadi (schyot nusxasidan).
- Navbatda serverga yetmagan amal bo'lsa terminalni uzib bo'lmaydi (oyna sababini aytadi); uzilganda yoki server
  terminalni bekor qilganda navbat va nusxalar o'chiriladi (`PosOffline.forget`), `seq` esa davom etadi.
- «Sinxronlash xatolari» — Sozlamalar (`orders.cancel`): rad etilgan va farq bilan qo'llangan amallar, «Hal qilindi»
  yoki «Yopish». Server matni bo'lmasa sabab matni `PosSyncReasons` dan (`invalid_payload`, `modifier_not_found`, ...).
- P3-35: sinxronlashda qator server narxida saqlansa (`offline_price_mismatch`) tepada «Narx serverda boshqacha» lentasi
  («Tushunarli» bosilguncha; `SyncPriceBanner`), ochiq buyurtma ekrani xabar beradi va buyurtmani, schyot ekrani
  schyotni serverdan qayta yuklaydi (`OrderController.onSynced`, `RefreshingState` — `offline.synced`).
  `device_time_clamped` — faqat ma'lumot, ko'rsatilmaydi.
- `send: true` bosqichidan keyin `order.items_changed` ikki marta keladi (`round_added`, `sent`): ekranlar hodisani
  faqat qayta yuklash uchun ishlatadi (`PosRefreshScheduler` 300 ms ichida birlashtiradi), sex markasi faqat o'z
  amalidan keyin (`printSent(before, after)`) chiqadi — ikki marta chop etilmaydi.
- Test: `test/offline_test.dart` (oflayn sotuv va qayta ulanish, xatoni hal qilish, Z yopiq, sotuv cheki bir marta,
  uzish), `test/order_retry_test.dart` (javobsiz bosqich, 409 dan keyin marka, oflayn qatorlar markasi),
  `test/lost_request_test.dart` (javobsiz to'lov, qaytarish, kirim), `test/refund_conflicts_test.dart` (P3-35: avans va
  nasiya qaytarish, qaytarilgandan keyin bekor qilish, `more_than_debt`, `shift_closed`, `paid_bill_affected`, narx
  farqi xabari).

Soxta backend'ning kassa qismi — `FakeCashierBackend` (`pos_data/testing.dart`); `tool/fake_pos_server.dart` uni demo
ma'lumotlar bilan ulaydi. Testlar: `test/{billing,shift,bookings,accounts,queue,settings}_test.dart`
(`test/support/p22.dart`).
