# pos_sync

Kassaning internetsiz cheklangan rejimi uchun lokal navbat va sinxronlash (docs/POS_SPEC.md 5-bo'lim), Flutter'siz
(`dart test`). Tashqi bog'liqligi yo'q. Serverga ulanish va buyurtmalarning lokal ko'rinishi — `pos_data`
(`PosOffline`) da.

```dart
final store = await SyncStore.open(FileSyncStorage(Directory('$support/pos_sync')));
final op = await store.enqueue(SyncOpType.orderOpen, {'order': uuid, 'local_number': 'K1-0041', ...}, staffId: 7);
final sender = SyncSender(store: store, transport: transport)..start();   // aloqa qaytsa navbatni yuboradi
```

## Nima bor

- **`SyncStore`** — diskdagi amallar navbati: `enqueue` (yangi `op_id` UUID va ketma-ket `seq`, har yozuvdan keyin
  diskka `flush`; bir vaqtda kelgan yozuvlar — navbat, javob, nusxa, siqish — bittadan, chaqirilgan tartibda bajariladi,
  `seq` yozuv paytida beriladi), `pending`, `applyResults` (server javobi: holat, sabab, server uuid'lari — `aliases`), `errors`
  (`conflict`/`rejected`), `resolve(id)` (qurilma id'si → server uuid'i), `nextLocalNumber(prefix, serverNext)` (`K1-0041`), serverdan olingan nusxalar
  (`putDoc`/`doc`, yoshi bilan), `markContact`, `markReloaded`. Navbat bo'shagan sari jurnal siqiladi.
- **`FileSyncStorage`** — `journal.log` (har qator JSON + CRC32; yarim yozilgan oxirgi qator ochilishda kesib
  tashlanadi; bitta ochiq fayl, yozuvlar ketma-ket) va `doc_<kalit>.json` (vaqtinchalik fayl + nomini almashtirish). `MemorySyncStorage` — testlar uchun
  (`tearNextAppend`, `failNextAppend` — elektr o'chishini taqlid qiladi).
- **`SyncSender`** — navbatni tartib bilan, paket-paket (`OfflineLimits.batchSize`, 50) `SyncTransport` ga yuboradi:
  bir vaqtda bitta, tarmoq xatosida kutib qayta urinadi (`SyncBackoff`), server rad etsa (`SyncRefused`) to'xtaydi;
  `drain` (`SyncDrainResult`), `start`, `nudge`, `stop`, `onDrained`. Javobni diskka yozib bo'lmasa ham to'xtamaydi:
  amal navbatda qoladi va qayta yuboriladi.
- **`OfflineLimits`**, **`OfflineStatus.evaluate`** — bootstrap'ning `offline.limits` qismidan: 15 daqiqa sariq, 2 soat
  qizil, 500 buyurtma chegarasi, menyu va aloqa 24 soatdan eski bo'lsa sotuv yo'q (`blockReason`), serverga yetmagan
  eng eski to'lov 24 / 40 / 48 soatda (`FiscalQueueLevel`), `canCloseShift`.
- **`SyncOpType`** — amal turlari (`order.open`, `order.round`, `order.send`, `order.void`, `order.served`,
  `bill.create`, `bill.pay`, `order.close`) va o'zbekcha nomlari.

## Sinov

```bash
cd pos/packages/pos_sync && dart test
```

`test/store_test.dart` (qayta ochish, yarim yozilgan qator, siqish, lokal raqam, bir vaqtdagi yozuvlar, terminal
uzilishi — `clear` navbat va nusxalarni o'chiradi, `seq` davom etadi), `sender_test.dart` (tartib, qayta
urinish, javob yo'qolishi), `limits_test.dart`.
