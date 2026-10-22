import '../http/pos_http.dart';
import 'fiscal_receipt_models.dart';

/// Ro'yxatdan o'tgan chekni o'qish (docs/API.md, "Fiskal cheklar (P3-11)", P3-26): kassa sotuv, qaytarish, avans va
/// kredit chekining fiskal belgisi, QR va MXIK qatorlarini shu yerdan oladi — `GET /pos/fiscal-receipts/{uuid}`
/// (`pos.take_payment`, chek filiali). To'lov, qaytarish, avans va qarz to'lovi javobi chekni o'zi olib keladi
/// ([PosFiscalState.receipt]); bu chaqiruv nusxa chiqarish yoki keyinroq o'qish uchun.
///
/// ```dart
/// final repo = FiscalReceiptRepository(pos.http);
/// final receipt = await repo.get(bill.fiscal.receiptUuid!);
/// ```
class FiscalReceiptRepository {
  FiscalReceiptRepository(this._http);

  final PosHttp _http;

  Future<PosFiscalReceiptDetail> get(String uuid) => _http.get(
    '/v1/pos/fiscal-receipts/$uuid',
    (Object? data) => PosFiscalReceiptDetail.fromJson(dataObject(data)),
  );
}
