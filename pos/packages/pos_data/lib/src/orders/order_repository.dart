import '../catalog/catalog_models.dart';
import '../http/pos_http.dart';
import '../json.dart';
import 'order_models.dart';

/// POS buyurtmalari (docs/API.md, "Zal va xona hisobi (P3-12)").
///
/// Har o'zgartiruvchi metod:
/// * `Idempotency-Key` yuboradi — [idempotencyKey] berilmasa yangisi. Tarmoq xatosidan ([PosNetworkException]) keyin
///   **aynan o'sha** amalni xatodagi `idempotencyKey` bilan qayta chaqiring: server ikki marta bajarmaydi.
/// * `version` — buyurtmaning oxirgi ko'rgan versiyasi ([PosOrder.version]); boshqa qurilma o'zgartirgan bo'lsa
///   [PosVersionConflict] (`currentVersion`): buyurtmani [get] bilan qayta yuklang.
/// * `approvalToken` — menejer tasdig'i ([PosApprovalRequired] dan keyin `ApprovalService.request`), **yangi** kalit
///   bilan (tana o'zgardi).
/// * To'liq [PosOrder] qaytaradi.
class PosOrderRepository {
  PosOrderRepository(this._http);

  final PosHttp _http;

  static PosOrder _order(Object? data) => PosOrder.fromJson(dataObject(data));

  /// `GET /pos/orders/{uuid}`.
  Future<PosOrder> get(String uuid) =>
      _http.get('/v1/pos/orders/$uuid', _order);

  /// `GET /pos/orders?scope=open`: filialning ochiq buyurtmalari navbati (POS va qabul qilingan onlayn), eskisi
  /// birinchi, ko'pi bilan 300.
  Future<List<PosOpenOrder>> openOrders({int? branchId}) => _http.get(
    '/v1/pos/orders',
    (Object? data) => <PosOpenOrder>[
      for (final Object? e in (data as List<Object?>?) ?? const <Object?>[])
        PosOpenOrder.fromJson(PosJson.of(e)),
    ],
    query: <String, Object?>{'scope': 'open', 'branch_id': branchId},
  );

  /// `POST /pos/orders`: buyurtma ochish. Zal — [tableId] (xona, stol, tapchan) yoki stolsiz; olib ketish —
  /// [PosChannel.takeaway]. [items] — birinchi bosqich, [send] — darhol oshxonaga. [externalId] — qurilmaning o'z
  /// raqami (shu raqam bilan qayta yuborilsa o'sha buyurtma qaytadi).
  Future<PosOrder> open({
    required PosChannel channel,
    int? tableId,
    int? guestsCount,
    int? waiterId,
    int? branchId,
    String? externalId,
    String? note,
    List<PosLineInput> items = const <PosLineInput>[],
    bool send = false,
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/orders',
    _order,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'channel': channel.wire,
      'branch_id': ?branchId,
      'table_id': ?tableId,
      'guests_count': ?guestsCount,
      'waiter_id': ?waiterId,
      'external_id': ?externalId,
      'note': ?note,
      if (items.isNotEmpty)
        'items': <Map<String, Object?>>[
          for (final PosLineInput i in items) i.toJson(),
        ],
      if (send) 'send': true,
      'approval_token': ?approvalToken,
    },
  );

  /// `POST /pos/orders/{uuid}/rounds`: yangi bosqich. [fireMode] — qatorlarning standart rejimi, [send] — `now`
  /// qatorlarni darhol oshxonaga.
  Future<PosOrder> addRound(
    String uuid, {
    required int version,
    required List<PosLineInput> items,
    PosFireMode? fireMode,
    bool send = false,
    String? approvalToken,
    String? idempotencyKey,
  }) => _post(uuid, 'rounds', idempotencyKey, <String, Object?>{
    'version': version,
    'items': <Map<String, Object?>>[
      for (final PosLineInput i in items) i.toJson(),
    ],
    'fire_mode': ?fireMode?.wire,
    if (send) 'send': true,
    'approval_token': ?approvalToken,
  });

  /// `POST /pos/orders/{uuid}/send`: [itemIds] siz — yuborilmagan hamma `now` qator; bilan — aynan shular (`hold`
  /// ham). Yangi qator yo'q — [PosRejected] `nothing_to_send`.
  Future<PosOrder> send(
    String uuid, {
    required int version,
    List<int>? itemIds,
    String? approvalToken,
    String? idempotencyKey,
  }) => _post(uuid, 'send', idempotencyKey, <String, Object?>{
    'version': version,
    'item_ids': ?itemIds,
    'approval_token': ?approvalToken,
  });

  /// `POST /pos/orders/{uuid}/items/{id}/void`. Yuborilgan qator yoki schyot chiqqan buyurtmada [reason] majburiy va
  /// `orders.void_item` yoki tasdiq kerak. [restock] — yuborilgan qatorning qoldig'ini qaytarish (pishmagan bo'lsa).
  Future<PosOrder> voidItem(
    String uuid,
    int itemId, {
    required int version,
    String? reason,
    bool? restock,
    String? approvalToken,
    String? idempotencyKey,
  }) => _post(uuid, 'items/$itemId/void', idempotencyKey, <String, Object?>{
    'version': version,
    'reason': ?reason,
    'restock': ?restock,
    'approval_token': ?approvalToken,
  });

  /// `POST /pos/orders/{uuid}/items/{id}/served`: taom mehmonga berildi.
  Future<PosOrder> markServed(
    String uuid,
    int itemId, {
    required int version,
    String? approvalToken,
    String? idempotencyKey,
  }) => _post(uuid, 'items/$itemId/served', idempotencyKey, <String, Object?>{
    'version': version,
    'approval_token': ?approvalToken,
  });

  /// `POST /pos/orders/{uuid}/guests`: mehmonlar soni (choy-non qatorlari server tomonidan qo'shiladi).
  Future<PosOrder> setGuests(
    String uuid, {
    required int version,
    required int guestsCount,
    String? approvalToken,
    String? idempotencyKey,
  }) => _post(uuid, 'guests', idempotencyKey, <String, Object?>{
    'version': version,
    'guests_count': guestsCount,
    'approval_token': ?approvalToken,
  });

  /// `POST /pos/orders/{uuid}/move`: boshqa stol yoki xonaga.
  Future<PosOrder> move(
    String uuid, {
    required int version,
    required int tableId,
    String? approvalToken,
    String? idempotencyKey,
  }) => _post(uuid, 'move', idempotencyKey, <String, Object?>{
    'version': version,
    'table_id': tableId,
    'approval_token': ?approvalToken,
  });

  /// `POST /pos/orders/{uuid}/merge`: [sourceUuid] buyurtmasini shu buyurtmaga qo'shish (manba bekor bo'ladi).
  Future<PosOrder> merge(
    String uuid, {
    required int version,
    required String sourceUuid,
    required int sourceVersion,
    String? approvalToken,
    String? idempotencyKey,
  }) => _post(uuid, 'merge', idempotencyKey, <String, Object?>{
    'version': version,
    'source_uuid': sourceUuid,
    'source_version': sourceVersion,
    'approval_token': ?approvalToken,
  });

  /// `POST /pos/orders/{uuid}/cancel`: butun buyurtmani bekor qilish ([reason] majburiy). To'lov bo'lsa —
  /// [PosConflict] `has_payments` (`data.paid_amount`).
  Future<PosOrder> cancel(
    String uuid, {
    required int version,
    required String reason,
    String? approvalToken,
    String? idempotencyKey,
  }) => _post(uuid, 'cancel', idempotencyKey, <String, Object?>{
    'version': version,
    'reason': reason,
    'approval_token': ?approvalToken,
  });

  /// `POST /pos/orders/{uuid}/charges/{charge}/waive`: xizmat qatorini sabab bilan olib tashlash.
  Future<PosOrder> waiveCharge(
    String uuid,
    PosChargeKind charge, {
    required int version,
    required String reason,
    String? approvalToken,
    String? idempotencyKey,
  }) => _post(
    uuid,
    'charges/${charge.wire}/waive',
    idempotencyKey,
    <String, Object?>{
      'version': version,
      'reason': reason,
      'approval_token': ?approvalToken,
    },
  );

  /// `POST /pos/orders/{uuid}/charges/{charge}/restore`: olib tashlangan qatorni qaytarish.
  Future<PosOrder> restoreCharge(
    String uuid,
    PosChargeKind charge, {
    required int version,
    String? approvalToken,
    String? idempotencyKey,
  }) => _post(
    uuid,
    'charges/${charge.wire}/restore',
    idempotencyKey,
    <String, Object?>{'version': version, 'approval_token': ?approvalToken},
  );

  Future<PosOrder> _post(
    String uuid,
    String action,
    String? idempotencyKey,
    Map<String, Object?> body,
  ) => _http.mutate(
    'POST',
    '/v1/pos/orders/$uuid/$action',
    _order,
    body: body,
    idempotencyKey: idempotencyKey,
  );
}
