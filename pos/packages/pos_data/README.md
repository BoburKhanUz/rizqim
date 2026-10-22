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
  `billing`, `shifts`, `bookings`, `accounts` (oxirgi to'rttasi yupqa — P3-22 kengaytiradi).
- **Xatolar:** `PosVersionConflict` (`currentVersion`), `PosApprovalRequired` (`permission`), `PosApprovalRejected`,
  `PosConflict`, `PosRejected`, `PosValidationException`, `PosForbidden`, `PosNotFound`, `PosNetworkException` —
  hammasi `ApiException`, matn o'zbekcha, `data` va `idempotencyKey` bilan.
- **Qoidalar:** har o'zgartiruvchi so'rovda `Idempotency-Key` (yangi amal — yangi kalit; tarmoq xatosidan keyin aynan
  o'sha amal — xatodagi kalit), buyurtmada `version`. Pul — `PosMoney`, miqdor — `PosQuantity` (`pos_core`).
- **Real-time:** `PosRealtime.create(...)` — `private-pos-branch.{id}` terminal tokeni bilan; `events` (tiplangan
  `PosEvent`), `states` (`PosLiveState`), `resubscribed`. `PosRefreshScheduler` — polling zaxirasi.
- **`PosConnectivity`** — server javob beryaptimi va oflayn navbatdagi amallar soni (P3-31).

## Sinov

```bash
cd pos/packages/pos_data && dart test
dart run tool/fake_pos_server.dart --port 8788   # soxta backend + Reverb (kassa va ofitsiantni Linux'da ochish uchun)
```

`package:pos_data/testing.dart`: `FakePosState` (soxta backend), `FakePosAdapter` (Dio), `FakePosRealtimeHub`,
`FakePosServer`. `test/fixtures/*.json` — mahalliy backend'dagi choyxona oqimidan olingan haqiqiy javoblar.
