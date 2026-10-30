import '../http/pos_http.dart';
import '../orders/order_models.dart';
import 'takeaway_models.dart';

/// P6-34 (docs/API.md "Qadoq, 24/7 va olib ketish vaqti"): kassada qadoq qatorini olib tashlash va qaytarish
/// (`packaging` moduli), olib ketish vaqti va taklif qilinadigan vaqtlar (`scheduled_pickup`). Faqat aloqa borida;
/// har o'zgartiruvchi so'rov `Idempotency-Key` va buyurtma `version` i bilan. Modul o'chiq — 403 `module_disabled`.
class PosTakeawayRepository {
  PosTakeawayRepository(this._http);

  final PosHttp _http;

  /// `POST /pos/orders/{uuid}/packaging/{rule}/remove`: mijoz o'z paketini olib keldi ([reason] majburiy).
  Future<PosOrder> removePackaging(
    String orderUuid,
    int ruleId, {
    required int version,
    required String reason,
    String? idempotencyKey,
  }) => _order(
    'POST',
    '/v1/pos/orders/$orderUuid/packaging/$ruleId/remove',
    <String, Object?>{'version': version, 'reason': reason},
    idempotencyKey,
  );

  /// `POST /pos/orders/{uuid}/packaging/{rule}/restore`: olib tashlangan qadoqni qaytarish.
  Future<PosOrder> restorePackaging(
    String orderUuid,
    int ruleId, {
    required int version,
    String? idempotencyKey,
  }) => _order(
    'POST',
    '/v1/pos/orders/$orderUuid/packaging/$ruleId/restore',
    <String, Object?>{'version': version},
    idempotencyKey,
  );

  /// `PUT /pos/orders/{uuid}/pickup-time`: olib ketish vaqti ([pickupAt] `null` — vaqt olib tashlanadi, oshxonaga
  /// hozir). 422 `pickup_too_soon` / `pickup_too_far` / `pickup_closed`, 409 `already_in_kitchen`.
  Future<PosOrder> setPickupTime(
    String orderUuid,
    DateTime? pickupAt, {
    required int version,
    String? idempotencyKey,
  }) =>
      _order('PUT', '/v1/pos/orders/$orderUuid/pickup-time', <String, Object?>{
        'version': version,
        'pickup_at': pickupAt?.toUtc().toIso8601String(),
      }, idempotencyKey);

  /// `GET /pos/pickup-slots`: filial taklif qiladigan olib ketish vaqtlari.
  Future<List<PosPickupSlot>> slots() => _http.get(
    '/v1/pos/pickup-slots',
    (Object? data) => dataObject(data).objects('slots', PosPickupSlot.fromJson),
  );

  Future<PosOrder> _order(
    String method,
    String path,
    Map<String, Object?> body,
    String? idempotencyKey,
  ) => _http.mutate(
    method,
    path,
    (Object? data) => PosOrder.fromJson(dataObject(data)),
    idempotencyKey: idempotencyKey,
    body: body,
  );
}
