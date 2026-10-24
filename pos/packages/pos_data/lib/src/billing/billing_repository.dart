import 'package:pos_core/pos_core.dart';

import '../http/pos_exceptions.dart';
import '../http/pos_http.dart';
import '../offline/pos_offline.dart';
import '../offline/sync_models.dart';
import 'billing_models.dart';

/// Schyot, to'lov, yopish va qaytarish (docs/API.md, "Schyot, to'lov va smena (P3-18)"). Yupqa qatlam: P3-22 kerakli
/// joyini kengaytiradi. Har o'zgartiruvchi so'rovda `Idempotency-Key` (`PosOrderRepository` dagi qoida).
///
/// P3-31: [offline] berilgan bo'lsa — cheklangan rejimda bitta schyot, schyotni chop etish (pre-check), naqd va karta
/// to'lovi va yopish navbat orqali; sotuv cheki internet tiklangach chiqadi (`fiscal.status: offline`). Bo'lish,
/// qaytarish, avans va nasiya — internet bilan.
class BillingRepository {
  BillingRepository(this._http, {this.offline});

  final PosHttp _http;
  final PosOffline? offline;

  bool get _limited => offline?.active ?? false;

  /// Oflayn ochilgan buyurtma yoki schyotning server uuid'i (sinxronlangandan keyin), bo'lmasa o'zi.
  String _id(String uuid) => offline?.store.resolve(uuid) ?? uuid;

  void _onlineOnly(String what) {
    if (_limited) {
      throw PosOfflineUnavailable(
        "Internet yo'q: $what faqat internet tiklangach.",
      );
    }
  }

  static PosBilling _billing(Object? data) =>
      PosBilling.fromJson(dataObject(data));
  static PosBillResult _result(Object? data) =>
      PosBillResult.fromJson(dataObject(data));

  /// `GET /pos/orders/{uuid}/bills` ([all] — bekor qilingan schyotlar ham).
  Future<PosBilling> bills(String orderUuid, {bool all = false}) async {
    final PosOffline? o = offline;
    if (o != null && o.active) return o.billing(orderUuid);
    Object? raw;
    try {
      final PosBilling b = await _http.get(
        '/v1/pos/orders/${_id(orderUuid)}/bills',
        (Object? data) {
          raw = data;
          return _billing(data);
        },
        query: <String, Object?>{if (all) 'all': true},
      );
      if (o != null && !all) await o.captureBilling(_id(orderUuid), raw);
      return b;
    } on PosNetworkException {
      if (o != null) return o.billing(orderUuid);
      rethrow;
    }
  }

  /// `POST /pos/orders/{uuid}/bills`: schyot tuzish yoki bo'lish (buyurtmaning hali schyotga tushmagan qismidan).
  Future<PosBilling> createBills(
    String orderUuid, {
    required int version,
    PosSplit split = const PosSplit.single(),
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    final PosOffline? o = offline;
    if (o != null && o.active) {
      return _localBill(o, orderUuid, split, idempotencyKey);
    }
    try {
      Object? raw;
      final PosBilling made = await _http.mutate(
        'POST',
        '/v1/pos/orders/${_id(orderUuid)}/bills',
        (Object? data) {
          raw = data;
          return _billing(data);
        },
        idempotencyKey: idempotencyKey,
        body: <String, Object?>{
          'version': version,
          ...split.toJson(),
          'approval_token': ?approvalToken,
        },
      );
      // Kept: the bill can be paid and printed again if the network goes before the screen reloads it
      if (o != null) await o.captureBilling(_id(orderUuid), raw);
      return made;
    } on PosNetworkException catch (e) {
      if (o != null && o.status.canSell) {
        return _localBill(
          o,
          orderUuid,
          split,
          e.idempotencyKey ?? idempotencyKey,
        );
      }
      rethrow;
    }
  }

  static Future<PosBilling> _localBill(
    PosOffline o,
    String orderUuid,
    PosSplit split,
    String? key,
  ) {
    if (split.toJson()['mode'] != 'single') {
      throw const PosOfflineUnavailable(
        "Internet yo'q: hisobni bo'lish faqat internet tiklangach. Bitta schyot chiqaring.",
      );
    }
    return o.createBill(orderUuid, idempotencyKey: key);
  }

  /// `POST /pos/bills/{uuid}/print`: schyotni chiqarish (birinchisi buyurtmani qulflaydi; qayta — "NUSXA").
  Future<PosBillResult> printBill(
    String billUuid, {
    int? version,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    final PosOffline? o = offline;
    // Offline the pre-check is printed from the till's own bill (the server never learns it was printed)
    if (o != null && o.active) return o.printBill(billUuid);
    return _http.mutate(
      'POST',
      '/v1/pos/bills/${_id(billUuid)}/print',
      _result,
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{
        'version': ?version,
        'approval_token': ?approvalToken,
      },
    );
  }

  /// `POST /pos/bills/{uuid}/payments`: bir yoki bir nechta to'lov turi. Jami qoldiqdan oshsa [PosRejected]
  /// `overpayment` (`data.due`); ochiq smena bo'lmasa [PosConflict] `shift_not_open`.
  Future<PosBillResult> pay(
    String billUuid, {
    required List<PosTenderInput> tenders,
    String? shiftUuid,
    int? version,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    final PosOffline? o = offline;
    if (o != null && o.active) {
      return o.pay(
        billUuid,
        tenders: tenders,
        shiftUuid: shiftUuid,
        idempotencyKey: idempotencyKey,
      );
    }
    return _http.mutate(
      'POST',
      '/v1/pos/bills/${_id(billUuid)}/payments',
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
  }

  /// `POST /pos/bills/{uuid}/fiscal-retry`: kutilayotgan sotuv chekini qayta so'rash (yangi chek chiqmaydi).
  Future<PosBillResult> fiscalRetry(
    String billUuid, {
    String? idempotencyKey,
  }) async {
    _onlineOnly('fiskal chekni qayta so\'rash');
    return _fiscalRetry(billUuid, idempotencyKey: idempotencyKey);
  }

  Future<PosBillResult> _fiscalRetry(
    String billUuid, {
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/bills/${_id(billUuid)}/fiscal-retry',
    _result,
    idempotencyKey: idempotencyKey,
    body: const <String, Object?>{},
  );

  /// `POST /pos/orders/{uuid}/close`: uzilib qolgan yopishni yakunlash. To'lanmagan — [PosConflict]
  /// `order_not_settled`.
  Future<PosBilling> closeOrder(String orderUuid, {String? idempotencyKey}) {
    final PosOffline? o = offline;
    if (o != null && o.active) {
      return Future<PosBilling>.value(o.billing(orderUuid));
    }
    return _closeOrder(orderUuid, idempotencyKey: idempotencyKey);
  }

  Future<PosBilling> _closeOrder(String orderUuid, {String? idempotencyKey}) =>
      _http.mutate(
        'POST',
        '/v1/pos/orders/${_id(orderUuid)}/close',
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
  }) async {
    _onlineOnly('pulni qaytarish');
    return _refund(
      paymentUuid: paymentUuid,
      amount: amount,
      reason: reason,
      shiftUuid: shiftUuid,
      approvalToken: approvalToken,
      idempotencyKey: idempotencyKey,
    );
  }

  Future<PosRefund> _refund({
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
