import 'package:rizqim_api/rizqim_api.dart';

import '../http/pos_exceptions.dart';
import '../http/pos_http.dart';
import '../realtime/pos_events.dart';

/// P6 lokal ko'prik (docs/POS_SPEC.md 5.5, `pos/packages/pos_lan`): internet yo'q, lekin filialning markaz kassasi
/// Wi-Fi orqali ulangan (`lan` rejimi) bo'lsa — ofitsiant ilovasining zal xaritasi, buyurtmalari va ular ustidagi
/// amallar HTTP o'rniga markazga boradi. `pos_lan` dagi `LanPosRoute` amalga oshiradi; kassada yo'q (kassaning o'zi
/// markaz).
///
/// Shakllar serverniki (`GET /pos/floor`, `GET /pos/orders?scope=open`, `GET /pos/orders/{uuid}`): markaz ularni
/// o'z `OfflineOrderBook` idan beradi. Xatolar — `PosException` (rad etilsa `PosOfflineUnavailable` sababi bilan,
/// markaz javob bermasa `PosNetworkException` amalning `idempotencyKey`i = `op_id` bilan: o'sha kalit bilan qayta
/// yuborish bir marta qo'llanadi).
abstract interface class PosLanRoute {
  /// `lan` rejimi: repozitoriylar markazga murojaat qiladi.
  bool get active;

  Future<Map<String, Object?>> floor();

  Future<List<Map<String, Object?>>> openOrders();

  Future<Map<String, Object?>> order(String ref);

  /// Markazga amal (`pos_lan` README 6: `order.open`, `order.round`, ...); javob — buyurtmaning markazdagi ko'rinishi.
  /// [opId] — `Idempotency-Key` o'rnida (berilmasa yangisi).
  Future<Map<String, Object?>> op(
    String type,
    Map<String, Object?> payload, {
    String? opId,
  });

  /// LAN'da ochilgan buyurtmaning server uuid'i (markaz uni serverga yetkazgach), bo'lmasa `null`.
  String? resolve(String ref);

  /// Markaz hodisalari server hodisalari shaklida ([PosRefreshScheduler.localEvents] uchun).
  Stream<PosEvent> get events;
}

/// P6 lokal ko'prik: server javob beryaptimi (`pos_lan` `LanLink.probeCloud`) — `GET /health` tokensiz (hech qanday
/// sessiyaga ta'sir qilmaydi). Har qanday javob (xato ham) — `true`, javob kelmasa — `false`.
Future<bool> lanProbeServer(PosHttp http) async {
  try {
    await http.raw('GET', '/v1/health', scope: AuthScope.none);
    return true;
  } on PosNetworkException {
    return false;
  } on PosException {
    return true;
  }
}
