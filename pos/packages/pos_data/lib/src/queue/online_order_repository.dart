import '../http/pos_http.dart';
import '../json.dart';
import 'online_order_models.dart';

/// Kassa navbatidagi onlayn buyurtmalar: qabul qilinmagan (`new`) Telegram va mobil buyurtmalar, qabul qilish va rad
/// etish (docs/API.md 9.1, POS_SPEC 2.5). Qabul qilingan buyurtma `GET /pos/orders?scope=open` ga o'tadi.
///
/// Qabul qilish — `orders.manage` (kassir ham), rad etish — `orders.cancel` (menejer): ruxsat yo'q bo'lsa
/// [PosForbidden] (bu endpoint menejer tasdig'i tokenini qabul qilmaydi).
class OnlineOrderRepository {
  OnlineOrderRepository(this._http);

  final PosHttp _http;

  static PosOnlineOrder _order(Object? data) =>
      PosOnlineOrder.fromJson(dataObject(data));

  /// `GET /staff/orders?scope=active&status=new`: qabul kutayotgan buyurtmalar (eskisi birinchi).
  Future<List<PosOnlineOrder>> pending({int? branchId, int perPage = 50}) =>
      _http.get(
        '/v1/staff/orders',
        (Object? data) {
          final List<PosOnlineOrder> list = <PosOnlineOrder>[
            for (final Object? e
                in (data as List<Object?>?) ?? const <Object?>[])
              PosOnlineOrder.fromJson(PosJson.of(e)),
          ];
          // Server yangisini birinchi beradi: navbatda eng uzoq kutgani tepada
          return list.reversed.toList();
        },
        query: <String, Object?>{
          'scope': 'active',
          'status': 'new',
          'branch_id': branchId,
          'per_page': perPage,
        },
      );

  /// `GET /staff/orders?scope=history&date=` — shu kunda yakunlangan buyurtmalar (yangisi birinchi): kassa yopilgan
  /// buyurtmaning schyotini ochib, chek nusxasi yoki qaytarish qiladi. [channel] — masalan `pos_hall`.
  Future<List<PosOnlineOrder>> finished({
    required String date,
    int? branchId,
    String? channel,
    int perPage = 100,
  }) => _http.get(
    '/v1/staff/orders',
    (Object? data) => <PosOnlineOrder>[
      for (final Object? e in (data as List<Object?>?) ?? const <Object?>[])
        PosOnlineOrder.fromJson(PosJson.of(e)),
    ],
    query: <String, Object?>{
      'scope': 'history',
      'date': date,
      'branch_id': branchId,
      'channel': channel,
      'per_page': perPage,
    },
  );

  /// `POST /staff/orders/{uuid}/status {status: accepted}`.
  Future<PosOnlineOrder> accept(String uuid, {String? idempotencyKey}) =>
      _http.mutate(
        'POST',
        '/v1/staff/orders/$uuid/status',
        _order,
        idempotencyKey: idempotencyKey,
        body: const <String, Object?>{'status': 'accepted'},
      );

  /// `POST /staff/orders/{uuid}/status {status: rejected, reason, note}`.
  Future<PosOnlineOrder> reject(
    String uuid, {
    required PosRejectReason reason,
    String? note,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/staff/orders/$uuid/status',
    _order,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'status': 'rejected',
      'reason': reason.wire,
      if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
    },
  );
}
