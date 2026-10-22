import 'package:pos_core/pos_core.dart';

import '../http/pos_http.dart';
import 'billing_models.dart';

/// Schyot, to'lov, yopish va qaytarish (docs/API.md, "Schyot, to'lov va smena (P3-18)"). Yupqa qatlam: P3-22 kerakli
/// joyini kengaytiradi. Har o'zgartiruvchi so'rovda `Idempotency-Key` (`PosOrderRepository` dagi qoida).
class BillingRepository {
  BillingRepository(this._http);

  final PosHttp _http;

  static PosBilling _billing(Object? data) =>
      PosBilling.fromJson(dataObject(data));
  static PosBillResult _result(Object? data) =>
      PosBillResult.fromJson(dataObject(data));

  /// `GET /pos/orders/{uuid}/bills` ([all] — bekor qilingan schyotlar ham).
  Future<PosBilling> bills(String orderUuid, {bool all = false}) => _http.get(
    '/v1/pos/orders/$orderUuid/bills',
    _billing,
    query: <String, Object?>{if (all) 'all': true},
  );

  /// `POST /pos/orders/{uuid}/bills`: schyot tuzish yoki bo'lish (buyurtmaning hali schyotga tushmagan qismidan).
  Future<PosBilling> createBills(
    String orderUuid, {
    required int version,
    PosSplit split = const PosSplit.single(),
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/orders/$orderUuid/bills',
    _billing,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'version': version,
      ...split.toJson(),
      'approval_token': ?approvalToken,
    },
  );

  /// `POST /pos/bills/{uuid}/print`: schyotni chiqarish (birinchisi buyurtmani qulflaydi; qayta — "NUSXA").
  Future<PosBillResult> printBill(
    String billUuid, {
    int? version,
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/bills/$billUuid/print',
    _result,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'version': ?version,
      'approval_token': ?approvalToken,
    },
  );

  /// `POST /pos/bills/{uuid}/payments`: bir yoki bir nechta to'lov turi. Jami qoldiqdan oshsa [PosRejected]
  /// `overpayment` (`data.due`); ochiq smena bo'lmasa [PosConflict] `shift_not_open`.
  Future<PosBillResult> pay(
    String billUuid, {
    required List<PosTenderInput> tenders,
    String? shiftUuid,
    int? version,
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/bills/$billUuid/payments',
    _result,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'tenders': <Map<String, Object?>>[
        for (final PosTenderInput t in tenders) t.toJson(),
      ],
      'shift_uuid': ?shiftUuid,
      'version': ?version,
      'approval_token': ?approvalToken,
    },
  );

  /// `POST /pos/bills/{uuid}/fiscal-retry`: kutilayotgan sotuv chekini qayta so'rash (yangi chek chiqmaydi).
  Future<PosBillResult> fiscalRetry(
    String billUuid, {
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/bills/$billUuid/fiscal-retry',
    _result,
    idempotencyKey: idempotencyKey,
    body: const <String, Object?>{},
  );

  /// `POST /pos/orders/{uuid}/close`: uzilib qolgan yopishni yakunlash. To'lanmagan — [PosConflict]
  /// `order_not_settled`.
  Future<PosBilling> closeOrder(String orderUuid, {String? idempotencyKey}) =>
      _http.mutate(
        'POST',
        '/v1/pos/orders/$orderUuid/close',
        _billing,
        idempotencyKey: idempotencyKey,
        body: const <String, Object?>{},
      );

  /// `POST /pos/refunds`: shu smenada olingan naqd, karta yoki QR to'lovni qaytarish (`pos.refund` yoki tasdiq).
  Future<PosRefund> refund({
    required String paymentUuid,
    required PosMoney amount,
    required String reason,
    String? shiftUuid,
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/refunds',
    (Object? data) => PosRefund.fromJson(dataObject(data)),
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'payment_uuid': paymentUuid,
      'amount': amount.toApiString(),
      'reason': reason,
      'shift_uuid': ?shiftUuid,
      'approval_token': ?approvalToken,
    },
  );
}
