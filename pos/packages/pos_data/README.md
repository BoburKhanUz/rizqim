# pos_data

Kassa va ofitsiant ilovalarining backend bilan ishlash qatlami (Flutter'siz, `dart test`). Hammasi bitta
`RizqimClient` ustida: token, 401 va xodim PIN sessiyasini `rizqim_api` boshqaradi.

```dart
final pos = PosData(client);                       // client: RizqimClient
final boot = await pos.session.loadBootstrap();    // PIN bilan kirilgandan keyin
final menu = await pos.catalog.load(PosChannel.hall, menuVersion: boot.menuVersion);
final order = await pos.orders.open(channel: PosChannel.hall, tableId: 3, guestsCount: 4);
final next = await pos.orders.addRound(order.uuid, version: order.version, items: [
  PosLineInput(variantUuid: osh.uuid, quantity: PosQuantity.parse('1.5')),
]);
```

## Nima bor

- **`PosSession`** — terminal + xodim: `phase` (ulanmagan, qulf, kirdi, tayyor), `staffName`, `can(...)`,
  `needsApproval(...)`, `canUnpair`, `branchId`, `bootstrap`, `shift`, `idleTimeout()` (filialning ekran qulfi),
  `lock()`, `unpair()`, `changes`.
- **`ApprovalService`** — menejer tasdig'i: `approvers()` (PIN o'rnatilgan menejer va ega), `request(...)` →
  `ApprovalToken`. `runWithApproval(action, approve: ...)` — 403 `approval_required` bo'lsa token so'rab, amalni yangi
  kalit bilan qayta yuboradi.
- **Repozitoriylar:** `bootstrap`, `catalog` (`menu_version` bo'yicha kesh, `refreshAvailability`), `floor`, `orders`
  (ochish, bosqich, yuborish, o'chirish, berildi, mehmonlar, ko'chirish, birlashtirish, bekor qilish, xizmat qatori),
  `billing`, `shifts`, `bookings`, `accounts`. P3-22 qo'shgani (`PosData.http` ustida yaratiladi):
  `FiscalReceiptRepository` (chek: MXIK qatorlari, fiskal belgi, QR) va `OnlineOrderRepository` (qabul kutayotgan
  onlayn buyurtmalar, qabul qilish, rad etish, bugun yopilganlar).
- **Modellar:** X/Z hisobot `PosShiftReport` to'liq tiplangan (`sales`, `tenders`, `cards`, `byChannel`, `byWaiter`,
  `discounts`, `voids`, `refunds`, `deposits`, `credit`, `cash`; yopilgan smenada `PosShift.summary`), `raw` — serverning
  o'z javobi. Schyot: serverda `paying` yo'q — `PosBill.isTaken` (to'lov olingan), `awaitingReceipt` (to'liq to'langan,
  chek chiqmagan). `PosTenderInput.tendered` / `change` — naqdda berilgan pul va qaytim.
- **Xatolar:** `PosVersionConflict` (`currentVersion`), `PosApprovalRequired` (`permission`), `PosApprovalRejected`,
  `PosConflict`, `PosRejected`, `PosValidationException`, `PosForbidden`, `PosNotFound`, `PosNetworkException` —
  hammasi `ApiException`, matn o'zbekcha, `data` va `idempotencyKey` bilan. `PosReasons` — sabab kodlari va matni;
  P3-35: `paid_bill_affected`, `merge_bookings_conflict`, `shift_closed`, `account_unknown` da ilovaning o'z matni
  server matnidan ustun (`PosReasons.clientWorded`), summa aytadigan `reverse_exceeds_applied`, `reverse_exceeds_charge`,
  `more_than_debt` da server matni qoladi. `needsOrderReload` — ekrandagi buyurtma eskirgan (`version_conflict`,
  `order_closed`, `order_changed`, `paid_bill_affected`).
- **Qoidalar:** har o'zgartiruvchi so'rovda `Idempotency-Key` (yangi amal — yangi kalit; tarmoq xatosidan keyin aynan
  o'sha amal — xatodagi kalit), buyurtmada `version`. Pul — `PosMoney`, miqdor — `PosQuantity` (`pos_core`).
- **Real-time:** `PosRealtime.create(...)` — `private-pos-branch.{id}` terminal tokeni bilan; `events` (tiplangan
  `PosEvent`), `states` (`PosLiveState`), `resubscribed`. `PosRefreshScheduler` — polling zaxirasi.
- **`PosConnectivity`** — server javob beryaptimi va oflayn navbatdagi amallar soni (P3-31).

## Internetsiz rejim (P3-31)

`PosData(client, offlineStore: await SyncStore.open(FileSyncStorage(dir)))` — `pos.offline` (`PosOffline`) yoqiladi;
`pos_sync` ham shu paketdan eksport qilinadi.

- **Nusxalar:** onlayn paytda bootstrap (`offline` qismi bilan), menyu, zal, ochiq buyurtmalar va schyotlar diskka
  olinadi (`PosOfflineSettings`: prefiks, keyingi lokal raqam, xizmat haqi, joy narxi, xona puli, minimal summa,
  choy-non, chegaralar).
- **Repozitoriylar o'zi tanlaydi:** aloqa yo'q yoki navbat bo'sh emas — `orders` (ochish, bosqich, yuborish,
  o'chirish, berildi), `billing` (bitta schyot, chop etish, naqd/karta to'lov) va `floor` lokal ishlaydi
  (`OfflineOrderBook`, narx — `OfflinePricing`, `pos_core` pulida), amal navbatga yoziladi; qolganlari
  (`PosOfflineUnavailable`, 503 `offline_unavailable`) — mehmonlar, ko'chirish, birlashtirish, bekor qilish,
  qaytarish, smena ochish/yopish, kassa harakati.
- **Holat:** `offline.status` (`OfflineStatus`), `changes`, `synced` (shu yuborishda javob olgan amallar),
  `salesToPrint` / `salePrinted(opId)` (oflayn to'langan, serverga yetgan, sotuv cheki hali chop etilmagan schyotlar —
  diskda, qayta kirishda yo'qolmaydi va qayta chiqmaydi). Oflaynda `GET /pos/sync/status` 15–60 soniyada; aloqa qaytsa navbat `SyncSender` bilan ketadi, keyin
  zal va buyurtmalar serverdan qayta olinadi.
- **Xatolar:** `serverErrors()` (`PosSyncErrorsPage`), `resolveError(opId, resolution, note)`, `syncNow()`.
  P3-35: `PosSyncReasons` — sabab va belgilar matni (`invalid_payload`, `modifier_not_found`, `offline_price_mismatch`,
  `device_time_clamped` va boshqalar; server matni bo'lmasa `PosSyncError.message`), `PosSyncPriceNotice.of(ops)` —
  server narxida saqlangan qatorlar (kassirga xabar), `syncTouchesOrder(ops, ref, resolve)` — ekran o'z buyurtmasini
  qayta yuklashi kerakmi.
- **Onlayn schyot oflaynda:** onlayn tuzilgan schyot (nusxasi `createBills` / `bills` javobidan) uzilishdan keyin ham
  chop etiladi va to'lanadi. Qisman to'langan schyot saqlanadi (to'lov olingan — `paid` yoki unda to'lov bor), yangi
  bitta schyotga faqat qolgani tushadi; buyurtma har schyot to'liq to'langanda yopiladi.
- **Terminal uzilsa** (`terminalForgotten`, `terminalRevoked`): `forget()` — navbat, nusxalar va chek ro'yxati
  o'chiriladi, `seq` davom etadi (server bir `seq` ni ikki marta olmaydi). Uzishdan oldin navbat bo'sh bo'lsin.

Soxta backend: `FakeSyncBackend` (`pos_data/testing.dart`) — `FakePosState` ga `/pos/sync` yo'llarini qo'shadi
(`received`, `rejectNext`, `flagNext` — P3-35 belgilari, `resolved`). `FakePosState` serverdagidek `Idempotency-Key` ni eslaydi: o'sha kalit va
o'sha tana — birinchi javob qaytadi (`replayed`), boshqa tana — 409. Soxta PIN'lar serverdagi oddiy-PIN qoidasidan
o'tadi (kassir 4827, ofitsiant 3691, menejer 5926; `test/fake_pins_test.dart`). Test: `test/offline_test.dart`,
`test/offline_book_test.dart`.

## Sinov

```bash
cd pos/packages/pos_data && dart test
dart run tool/fake_pos_server.dart --port 8788   # soxta backend + Reverb (kassa va ofitsiantni Linux'da ochish uchun)
```

`package:pos_data/testing.dart`: `FakePosState` (soxta backend; qo'shimcha yo'llar — `extraRoutes`),
`FakeCashierBackend` (schyot, to'lov, fiskal "kutmoqda", qaytarish — P3-35 dan avans va nasiya qismlari ham, smena va Z, bron, avans, nasiya, onlayn navbat; P3-35 qoidalari: `has_payments`, `paid_bill_affected`, `merge_bookings_conflict`, `payment_status = refunded`), `FakePosAdapter` (Dio), `FakePosRealtimeHub`,
`FakePosServer`. `test/fixtures/*.json` — mahalliy backend'dagi choyxona oqimidan olingan haqiqiy javoblar.
