import '../http/pos_http.dart';
import '../json.dart';
import 'online_order_models.dart';

/// Kassa navbatidagi onlayn buyurtmalar: qabul qilinmagan (`new`) Telegram va mobil buyurtmalar, qabul qilish va rad
/// etish (docs/API.md "Zal va xona hisobi", P3-26). Qabul qilingan buyurtma `GET /pos/orders?scope=open` ga o'tadi.
///
/// Qabul qilish — `orders.manage` (kassir ham); rad etish — `orders.cancel` yoki menejer tasdig'i
/// ([PosApprovalRequired] dan keyin `approvalToken` bilan).
class OnlineOrderRepository {
  OnlineOrderRepository(this._http);

  final PosHttp _http;

  /// Navbat qatoridagi onlayn karta (`online`); bo'lmasa qatorning o'zi.
  static PosOnlineOrder _card(PosJson row) {
    final PosJson? card = row.objOrNull('online');
    if (card == null) return PosOnlineOrder.fromJson(row);
    // P6-34: the row's queue number and pickup time (the card has neither) ride along in the card's raw
    return PosOnlineOrder.fromJson(
      PosJson.of(<String, Object?>{
        if (row['queue'] != null) 'queue': row['queue'],
        if (row['pickup'] != null) 'pickup': row['pickup'],
        ...card.map,
      }),
    );
  }

  static PosOnlineOrder _order(Object? data) => _card(dataObject(data));

  /// `GET /pos/orders?scope=incoming`: qabul kutayotgan onlayn buyurtmalar (eskisi birinchi).
  Future<List<PosOnlineOrder>> pending({int? branchId}) => _http.get(
    '/v1/pos/orders',
    (Object? data) => <PosOnlineOrder>[
      for (final Object? e in (data as List<Object?>?) ?? const <Object?>[])
        _card(PosJson.of(e)),
    ],
    query: <String, Object?>{'scope': 'incoming', 'branch_id': branchId},
  );

  /// `POST /pos/orders/{uuid}/accept`: yangi onlayn buyurtmani qabul qilish.
  Future<PosOnlineOrder> accept(
    String uuid, {
    int? version,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/orders/$uuid/accept',
    _order,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{'version': ?version},
  );

  /// `POST /pos/orders/{uuid}/reject`: rad etish — mijoz o'qiydigan [reason] sababi, [note] izoh (bo'lmasa sababning
  /// nomi); `orders.cancel` yo'q kassir menejer tasdig'i [approvalToken] bilan.
  Future<PosOnlineOrder> reject(
    String uuid, {
    required PosRejectReason reason,
    String? note,
    String? approvalToken,
    int? version,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/orders/$uuid/reject',
    _order,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'reason': note != null && note.trim().isNotEmpty
          ? note.trim()
          : reason.label,
      'cancel_reason': reason.wire,
      'approval_token': ?approvalToken,
      'version': ?version,
    },
  );
}
