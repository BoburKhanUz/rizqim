import '../http/pos_http.dart';
import '../offline/pos_offline.dart';
import '../offline/sync_models.dart';
import '../orders/order_models.dart';
import 'voucher_models.dart';

/// P6-43: sovg'a sertifikati va promo-kod kassada (docs/API.md "Sertifikat va vaucher"). Sotish — menyudan tashqari
/// sotuv: pul smenaga tushadi, bo'nak (ADVANCE) cheki chiqadi, javobda kod va chop etiladigan [PosVoucher.print].
/// Ishlatish — schyot to'lovi (`PosTenderInput.voucher`, [BillingRepository.pay]); qidirish — kod bo'yicha.
/// Promo-kod — do'konning chegirma vaucheri, buyurtma chegirmasi bo'ladi (kassir limiti ishlamaydi).
///
/// Hammasi faqat internet bilan: qoldiq serverda. Cheklangan rejimda [PosOfflineUnavailable] (oddiy matn), naqd/karta
/// qoladi.
///
/// Xatolar: modul o'chiq — 403 `module_disabled`; ochiq smena yo'q — 409 `shift_not_open`; kod topilmadi — 404
/// `voucher_not_found`; promo — 404 `promo_not_found`, 409 `promo_already_applied`, 422 `promo_inactive`,
/// `promo_min_order`, `promo_limit_reached`, `promo_no_discount`.
class PosVoucherRepository {
  PosVoucherRepository(this._http, {this.offline});

  final PosHttp _http;
  final PosOffline? offline;

  static const String offlineMessage =
      "Internet yo'q: sertifikat va promo-kod faqat server bilan ishlaydi. Naqd yoki karta bilan oling.";

  void _online() {
    if (offline?.active ?? false) {
      throw const PosOfflineUnavailable(offlineMessage);
    }
  }

  static String _code(String code) => Uri.encodeComponent(code.trim());

  /// `POST /pos/vouchers/sell`: sertifikat sotish ([shiftUuid] — sukut terminalning ochiq smenasi).
  Future<PosVoucher> sell(
    PosVoucherSale sale, {
    String? shiftUuid,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/vouchers/sell',
      (Object? data) => PosVoucher.fromJson(dataObject(data)),
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{...sale.toJson(), 'shift_uuid': ?shiftUuid},
    );
  }

  /// `GET /pos/vouchers/{code}`: qoldiq, muddat, holat va oxirgi harakatlar (kod — chop etilgandek yoki skanerdan).
  Future<PosVoucherLookup> lookup(String code) async {
    _online();
    return _http.get(
      '/v1/pos/vouchers/${_code(code)}',
      (Object? data) => PosVoucherLookup.fromJson(dataObject(data)),
    );
  }

  /// `POST /pos/orders/{uuid}/promo`: promo-kodni buyurtmaga qo'llash.
  Future<PosOrder> applyPromo(
    String orderUuid, {
    required int version,
    required String code,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/orders/${Uri.encodeComponent(orderUuid)}/promo',
      (Object? data) => PosOrder.fromJson(dataObject(data)),
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{'version': version, 'code': code.trim()},
    );
  }

  /// `DELETE /pos/orders/{uuid}/promo?version=`: promo-kod va chegirmani olib tashlash.
  Future<PosOrder> removePromo(
    String orderUuid, {
    required int version,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'DELETE',
      '/v1/pos/orders/${Uri.encodeComponent(orderUuid)}/promo',
      (Object? data) => PosOrder.fromJson(dataObject(data)),
      idempotencyKey: idempotencyKey,
      query: <String, Object?>{'version': version},
    );
  }
}
