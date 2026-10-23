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
- **Xatolar:** `PosVersionConflict` (`currentVersion`), `PosApprovalRequired` (`permission`), `PosApprovalRejected`,
  `PosConflict`, `PosRejected`, `PosValidationException`, `PosForbidden`, `PosNotFound`, `PosNetworkException` —
  hammasi `ApiException`, matn o'zbekcha, `data` va `idempotencyKey` bilan.
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
- **Holat:** `offline.status` (`OfflineStatus`), `changes`, `synced` (yuborilgan amallar natijasi — sotuv chekini chop
  etish uchun). Oflaynda `GET /pos/sync/status` 15–60 soniyada; aloqa qaytsa navbat `SyncSender` bilan ketadi, keyin
  zal va buyurtmalar serverdan qayta olinadi.
- **Xatolar:** `serverErrors()` (`PosSyncErrorsPage`), `resolveError(opId, resolution, note)`, `syncNow()`.

Soxta backend: `FakeSyncBackend` (`pos_data/testing.dart`) — `FakePosState` ga `/pos/sync` yo'llarini qo'shadi
(`received`, `rejectNext`, `resolved`). Test: `test/offline_test.dart`.

## Sinov

```bash
cd pos/packages/pos_data && dart test
dart run tool/fake_pos_server.dart --port 8788   # soxta backend + Reverb (kassa va ofitsiantni Linux'da ochish uchun)
```

`package:pos_data/testing.dart`: `FakePosState` (soxta backend; qo'shimcha yo'llar — `extraRoutes`),
`FakeCashierBackend` (schyot, to'lov, fiskal "kutmoqda", qaytarish, smena va Z, bron, avans, nasiya, onlayn navbat), `FakePosAdapter` (Dio), `FakePosRealtimeHub`,
`FakePosServer`. `test/fixtures/*.json` — mahalliy backend'dagi choyxona oqimidan olingan haqiqiy javoblar.
