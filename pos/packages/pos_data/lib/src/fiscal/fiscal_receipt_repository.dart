import '../http/pos_http.dart';
import 'fiscal_receipt_models.dart';

/// Ro'yxatdan o'tgan chekni o'qish (docs/API.md, "Fiskal cheklar (P3-11)"): kassa sotuv va qaytarish chekining fiskal
/// belgisi, QR va MXIK qatorlarini shu yerdan oladi.
///
/// Hozircha faqat `GET /owner/fiscal/receipts/{uuid}` bor (`finance.view` yoki `reports.view`): kassirda bu ruxsat
/// yo'q — [PosForbidden]. Shunda kassa chekni schyotdan tuzadi va fiskal belgi o'rniga "kutilmoqda" yozadi.
///
/// ```dart
/// final repo = FiscalReceiptRepository(pos.http);
/// final receipt = await repo.get(bill.fiscal.receiptUuid!);
/// ```
class FiscalReceiptRepository {
  FiscalReceiptRepository(this._http);

  final PosHttp _http;

  Future<PosFiscalReceiptDetail> get(String uuid) => _http.get(
    '/v1/owner/fiscal/receipts/$uuid',
    (Object? data) => PosFiscalReceiptDetail.fromJson(dataObject(data)),
  );
}
