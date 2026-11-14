import '../http/pos_http.dart';
import '../offline/pos_offline.dart';
import '../offline/sync_models.dart';
import 'banquet_models.dart';
import 'banquet_payment_models.dart';

/// P6-53: tadbir to'lov jadvali kassada (docs/API.md "Tadbirlar: to'lov jadvali, bekor qilish"): qatorlar va jami,
/// qatorni (yoki qismini) naqd/karta/statik QR bilan olish — pul smenaga, darhol BO'NAK cheki; bekor qilish hisobi va
/// bekor qilish (qaytarish cheklari, ushlab qolingan qism — "Bekor qilish haqi" sotuv cheki). Bank o'tkazmasi — faqat
/// panelda. O'qish va to'lash — `pos.take_payment` (yoki `banquets.manage`); bekor qilish — `banquets.manage`, naqd
/// qaytarishga `pos.refund` yoki menejer tasdig'i (403 `approval_required`). Faqat internet bilan.
///
/// Xatolar: 409 `shift_not_open`, `payment_not_due`, `event_not_payable`, `event_not_cancellable`,
/// `refund_needs_shift`; 422 `amount_exceeds_row`, `tender_invalid`, `card_type_required`, `transaction_id_required`,
/// `mxik_missing`, `override_reason_required`, `retained_invalid`; 404 `event_not_found`, `payment_not_found`.
class PosBanquetPaymentRepository {
  PosBanquetPaymentRepository(this._http, {this.offline});

  final PosHttp _http;
  final PosOffline? offline;

  static const String offlineMessage =
      "Internet yo'q: tadbir to'lovlari faqat server bilan ishlaydi.";

  void _online() {
    if (offline?.active ?? false) {
      throw const PosOfflineUnavailable(offlineMessage);
    }
  }

  static String _id(String uuid) => Uri.encodeComponent(uuid);

  /// `GET /pos/banquets/{uuid}/payments`.
  Future<PosBanquetSchedule> schedule(String uuid) async {
    _online();
    return _http.get(
      '/v1/pos/banquets/${_id(uuid)}/payments',
      (Object? data) => PosBanquetSchedule.fromJson(dataObject(data)),
    );
  }

  /// `POST /pos/banquets/{uuid}/payments/{row}/pay` (`Idempotency-Key` majburiy: berilmasa yangisi).
  Future<PosBanquetPaymentResult> pay(
    String uuid,
    String rowUuid,
    PosBanquetPayInput input, {
    String? shiftUuid,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/banquets/${_id(uuid)}/payments/${_id(rowUuid)}/pay',
      (Object? data) => PosBanquetPaymentResult.fromJson(dataObject(data)),
      idempotencyKey: idempotencyKey,
      body: input.toJson(shiftUuid: shiftUuid),
    );
  }

  /// `GET /pos/banquets/{uuid}/cancellation?retained=`: bekor qilish hisobi ([retained] — menejer summasini ko'rish).
  Future<PosBanquetCancellationQuote> cancellationQuote(
    String uuid, {
    String? retained,
  }) async {
    _online();
    return _http.get(
      '/v1/pos/banquets/${_id(uuid)}/cancellation',
      (Object? data) => PosBanquetCancellationQuote.fromJson(dataObject(data)),
      query: <String, Object?>{'retained': ?retained},
    );
  }

  /// `POST /pos/banquets/{uuid}/cancellation`: bekor qilish. [retained] boshqa bo'lsa [overrideReason] majburiy.
  Future<PosBanquetSchedule> cancel(
    String uuid, {
    required String reason,
    String? retained,
    String? overrideReason,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/banquets/${_id(uuid)}/cancellation',
      (Object? data) => PosBanquetSchedule.fromJson(dataObject(data)),
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{
        'reason': reason,
        'retained': ?retained,
        'override_reason': ?overrideReason,
        'approval_token': ?approvalToken,
      },
    );
  }

  /// `GET /pos/banquets/{uuid}/warnings`: ko'p mehmon, vaqt oynasidan tashqari (rad etish emas).
  Future<List<PosBanquetWarning>> warnings(String uuid) async {
    _online();
    return _http.get(
      '/v1/pos/banquets/${_id(uuid)}/warnings',
      (Object? data) =>
          dataObject(data).objects('warnings', PosBanquetWarning.fromJson),
    );
  }
}
