import '../http/pos_http.dart';
import 'queue_number_models.dart';

/// P6-13: buyurtma raqami va chaqiruv (docs/API.md "Buyurtma raqami va chaqiruv"): raqamlar taxtasi, kassa bloklari,
/// raqamni tayyor deb belgilash, qayta chaqirish va "berildi". Har o'zgartiruvchi so'rov `Idempotency-Key` bilan.
/// Modul o'chiq bo'lsa — 403 `module_disabled` ([PosModuleDisabled]).
class PosQueueRepository {
  PosQueueRepository(this._http);

  final PosHttp _http;

  /// `GET /pos/queue`: bugungi tayyorlanayotgan va tayyor raqamlar (`queue.*` hodisasida qayta so'raladi).
  Future<PosQueueBoard> board({int? branchId}) => _http.get(
    '/v1/pos/queue',
    (Object? data) => PosQueueBoard.fromJson(dataObject(data)),
    query: <String, Object?>{'branch_id': branchId},
  );

  /// `POST /pos/queue/blocks`: raqamlar kam qolsa (`low_water` dan kam) keyingi blok; yetarli bo'lsa o'zgarmaydi.
  Future<PosQueueState> reserveBlocks({String? idempotencyKey}) => _http.mutate(
    'POST',
    '/v1/pos/queue/blocks',
    (Object? data) => PosQueueState.fromJson(dataObject(data)),
    idempotencyKey: idempotencyKey,
    body: const <String, Object?>{},
  );

  /// `POST /pos/orders/{uuid}/ready`: raqam tayyor (KDS'siz kassa).
  Future<PosQueueEntry> ready(String orderUuid, {String? idempotencyKey}) =>
      _move(orderUuid, 'ready', idempotencyKey);

  /// `POST /pos/orders/{uuid}/call`: tayyor raqamni qayta chaqirish.
  Future<PosQueueEntry> call(String orderUuid, {String? idempotencyKey}) =>
      _move(orderUuid, 'call', idempotencyKey);

  /// `POST /pos/orders/{uuid}/handed`: mijoz oldi.
  Future<PosQueueEntry> handed(String orderUuid, {String? idempotencyKey}) =>
      _move(orderUuid, 'handed', idempotencyKey);

  Future<PosQueueEntry> _move(
    String orderUuid,
    String action,
    String? idempotencyKey,
  ) => _http.mutate(
    'POST',
    '/v1/pos/orders/$orderUuid/$action',
    (Object? data) => PosQueueEntry.fromJson(dataObject(data)),
    idempotencyKey: idempotencyKey,
    body: const <String, Object?>{},
  );
}
