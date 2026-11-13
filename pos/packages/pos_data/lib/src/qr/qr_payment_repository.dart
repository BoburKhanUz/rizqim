import '../http/pos_http.dart';
import '../offline/pos_offline.dart';
import '../offline/sync_models.dart';
import 'qr_payment_models.dart';

/// P6-41/P6-42: schyot summasiga dinamik QR (docs/API.md, "QR to'lov kassada"). Kassir QR so'raydi, uni schyotga chop
/// etadi va mijoz ekraniga yuboradi ([PosQrPayment.display]); mijoz to'lagach provayder o'zi tasdiqlaydi — kassa
/// `qr.paid` hodisasini eshitadi yoki [poll] qiladi va schyotni qayta oladi. Fiskal chekni Rizqim chiqaradi.
///
/// Internet yo'q (cheklangan rejim): QR so'rab bo'lmaydi — [PosOfflineUnavailable] (oddiy matn): kassa statik QR'ni
/// qo'lda tasdiqlashni taklif qiladi (`PosTenderInput.qr`).
///
/// Xatolar: modul o'chiq — 403 `module_disabled`; provayder yoqilmagan — 422 `qr_provider_disabled`; do'konning o'z
/// merchant hisobi ulanmagan — 409 `provider_not_configured`; ochiq smena yo'q — 409 `shift_not_open`; summa qarzdan
/// ko'p — 422 `overpayment`; to'langan QR'ni bekor qilish — 409 `qr_already_paid`.
class PosQrPaymentRepository {
  PosQrPaymentRepository(this._http, {this.offline});

  final PosHttp _http;
  final PosOffline? offline;

  static const String offlineMessage =
      "Internet yo'q: Click/Payme QR'ni server yaratadi. Statik QR orqali to'lovni qo'lda tasdiqlang yoki naqd/karta oling.";

  void _online() {
    if (offline?.active ?? false) {
      throw const PosOfflineUnavailable(offlineMessage);
    }
  }

  static PosQrPayment _one(Object? data) =>
      PosQrPayment.fromJson(dataObject(data).obj('qr'));

  /// `POST /pos/bills/{uuid}/qr`: [provider] (sukut — filialning asosiysi) bilan schyot qarzi (yoki [amount] — aralash
  /// to'lovning QR qismi) uchun QR. Yangisi eskisini bekor qiladi.
  Future<PosQrPayment> create(
    String billUuid, {
    PosQrProvider? provider,
    String? amount,
    String? shiftUuid,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/bills/${Uri.encodeComponent(billUuid)}/qr',
      _one,
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{
        'provider': ?provider?.wire,
        'amount': ?amount,
        'shift_uuid': ?shiftUuid,
      },
    );
  }

  /// `GET /pos/qr/{uuid}`: hozirgi holat (realtime bo'lmasa so'rab turish uchun).
  Future<PosQrPayment> poll(String uuid) async {
    _online();
    return _http.get('/v1/pos/qr/${Uri.encodeComponent(uuid)}', _one);
  }

  /// `GET /pos/bills/{uuid}/qr`: schyotning QR so'rovlari (yangisi birinchi; `pending` — ko'rsatiladigani).
  Future<List<PosQrPayment>> forBill(String billUuid) async {
    _online();
    return _http.get(
      '/v1/pos/bills/${Uri.encodeComponent(billUuid)}/qr',
      (Object? data) => dataObject(data).objects('qrs', PosQrPayment.fromJson),
    );
  }

  /// `DELETE /pos/qr/{uuid}`: kassir QR'ni qaytarib oladi ([reason] — jurnalga).
  Future<PosQrPayment> cancel(
    String uuid, {
    String? reason,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'DELETE',
      '/v1/pos/qr/${Uri.encodeComponent(uuid)}',
      _one,
      idempotencyKey: idempotencyKey,
      query: <String, Object?>{'reason': ?reason},
    );
  }

  /// `POST /pos/qr/{uuid}/simulate-paid`: faqat sinov (sandbox) QR — demo va e2e'da provayder tasdig'ini o'ynaydi.
  Future<PosQrPayment> simulatePaid(
    String uuid, {
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/qr/${Uri.encodeComponent(uuid)}/simulate-paid',
      _one,
      idempotencyKey: idempotencyKey,
    );
  }
}
