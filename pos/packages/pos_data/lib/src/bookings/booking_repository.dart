import 'package:pos_core/pos_core.dart';

import '../http/pos_http.dart';
import 'booking_models.dart';

/// Bron, oldindan buyurtma va avans (docs/API.md, "Bron, avans va nasiya (P3-17)"). Yupqa qatlam: P3-22 kengaytiradi.
///
/// To'qnashuv — [PosConflict] `booking_conflict` (`data['conflicts']`), noto'g'ri o'tish — [PosRejected]
/// `invalid_transition`, eskirgan versiya — [PosConflict] `stale_version`.
class BookingRepository {
  BookingRepository(this._http);

  final PosHttp _http;

  static PosBooking _booking(Object? data) =>
      PosBooking.fromJson(dataObject(data));

  /// `data: {bookings: [...]}`.
  static List<PosBooking> _list(Object? data) =>
      dataObject(data).objects('bookings', PosBooking.fromJson);

  /// `GET /pos/bookings/calendar?from&to` (`YYYY-MM-DD`, ko'pi bilan 31 kun).
  Future<PosBookingCalendar> calendar({
    required String from,
    required String to,
    int? branchId,
    bool includeCanceled = false,
  }) => _http.get(
    '/v1/pos/bookings/calendar',
    (Object? data) => PosBookingCalendar.fromJson(dataObject(data)),
    query: <String, Object?>{
      'from': from,
      'to': to,
      'branch_id': branchId,
      if (includeCanceled) 'include_canceled': true,
    },
  );

  /// `GET /pos/bookings?from&to&status` yoki `?phone=` (kamida 4 raqam).
  Future<List<PosBooking>> list({
    String? from,
    String? to,
    String? status,
    String? phone,
    int? branchId,
  }) => _http.get(
    '/v1/pos/bookings',
    _list,
    query: <String, Object?>{
      'from': from,
      'to': to,
      'status': status,
      'phone': phone,
      'branch_id': branchId,
    },
  );

  Future<PosBooking> get(String uuid) =>
      _http.get('/v1/pos/bookings/$uuid', _booking);

  Future<PosBooking> create(PosBookingInput input, {String? idempotencyKey}) =>
      _http.mutate(
        'POST',
        '/v1/pos/bookings',
        _booking,
        idempotencyKey: idempotencyKey,
        body: input.toJson(),
      );

  Future<PosBooking> update(
    String uuid,
    PosBookingInput changes, {
    int? version,
    String? idempotencyKey,
  }) => _http.mutate(
    'PATCH',
    '/v1/pos/bookings/$uuid',
    _booking,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{...changes.toJson(), 'version': ?version},
  );

  Future<PosBooking> confirm(
    String uuid, {
    int? version,
    String? idempotencyKey,
  }) => _action(uuid, 'confirm', <String, Object?>{
    'version': ?version,
  }, idempotencyKey);

  Future<PosBooking> cancel(
    String uuid, {
    required String reason,
    int? version,
    String? idempotencyKey,
  }) => _action(uuid, 'cancel', <String, Object?>{
    'reason': reason,
    'version': ?version,
  }, idempotencyKey);

  Future<PosBooking> noShow(
    String uuid, {
    int? version,
    String? idempotencyKey,
  }) => _action(uuid, 'no-show', <String, Object?>{
    'version': ?version,
  }, idempotencyKey);

  /// `POST /pos/bookings/{uuid}/arrive` ("Keldi"): buyurtma ochiladi. Ikkinchi marta — [PosConflict]
  /// `booking_already_arrived` (`data['order_uuid']`).
  Future<PosArrival> arrive(
    String uuid, {
    int? guests,
    int? diningTableId,
    int? version,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/bookings/$uuid/arrive',
    (Object? data) => PosArrival.fromJson(dataObject(data)),
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'guests': ?guests,
      'dining_table_id': ?diningTableId,
      'version': ?version,
    },
  );

  /// Oldindan buyurtma qatori ([productVariantId] — variantning raqamli id'si, [PosVariant.id]).
  Future<PosBooking> addItem(
    String uuid, {
    required int productVariantId,
    required PosQuantity quantity,
    List<Map<String, Object?>>? modifiers,
    String? note,
    DateTime? dueAt,
    int? version,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/bookings/$uuid/items',
    _booking,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'product_variant_id': productVariantId,
      'quantity': quantity.toApiString(),
      'modifiers': ?modifiers,
      'note': ?note,
      'due_at': ?dueAt?.toUtc().toIso8601String(),
      'version': ?version,
    },
  );

  Future<PosBooking> updateItem(
    String uuid,
    int itemId, {
    PosQuantity? quantity,
    String? note,
    DateTime? dueAt,
    int? version,
    String? idempotencyKey,
  }) => _http.mutate(
    'PATCH',
    '/v1/pos/bookings/$uuid/items/$itemId',
    _booking,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'quantity': ?quantity?.toApiString(),
      'note': ?note,
      'due_at': ?dueAt?.toUtc().toIso8601String(),
      'version': ?version,
    },
  );

  Future<PosBooking> removeItem(
    String uuid,
    int itemId, {
    String? idempotencyKey,
  }) => _http.mutate(
    'DELETE',
    '/v1/pos/bookings/$uuid/items/$itemId',
    _booking,
    idempotencyKey: idempotencyKey,
  );

  /// `POST /pos/bookings/{uuid}/deposits`: avans (bo'nak cheki chiqadi). `Idempotency-Key` majburiy — takroriy
  /// so'rov o'sha avansni qaytaradi.
  Future<PosDepositResult> takeDeposit(
    String uuid, {
    required PosMoney amount,
    required String tender,
    String? cardType,
    String? transactionId,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/bookings/$uuid/deposits',
    (Object? data) => PosDepositResult.fromJson(dataObject(data)),
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'amount': amount.toApiString(),
      'tender': tender,
      'card_type': ?cardType,
      'transaction_id': ?transactionId,
    },
  );

  /// Avansni qaytarish (`pos.refund` yoki tasdiq).
  Future<PosDepositResult> refundDeposit(
    String uuid,
    String paymentUuid, {
    required String reason,
    String? approvalToken,
    String? idempotencyKey,
  }) => _settle(
    uuid,
    paymentUuid,
    'refund',
    reason,
    approvalToken,
    idempotencyKey,
  );

  /// Avansni ushlab qolish (`orders.cancel` yoki tasdiq).
  Future<PosDepositResult> retainDeposit(
    String uuid,
    String paymentUuid, {
    required String reason,
    String? approvalToken,
    String? idempotencyKey,
  }) => _settle(
    uuid,
    paymentUuid,
    'retain',
    reason,
    approvalToken,
    idempotencyKey,
  );

  Future<PosDepositResult> _settle(
    String uuid,
    String paymentUuid,
    String action,
    String reason,
    String? approvalToken,
    String? idempotencyKey,
  ) => _http.mutate(
    'POST',
    '/v1/pos/bookings/$uuid/deposits/$paymentUuid/$action',
    (Object? data) => PosDepositResult.fromJson(dataObject(data)),
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{'reason': reason, 'approval_token': ?approvalToken},
  );

  Future<PosBooking> _action(
    String uuid,
    String action,
    Map<String, Object?> body,
    String? idempotencyKey,
  ) => _http.mutate(
    'POST',
    '/v1/pos/bookings/$uuid/$action',
    _booking,
    idempotencyKey: idempotencyKey,
    body: body,
  );
}
