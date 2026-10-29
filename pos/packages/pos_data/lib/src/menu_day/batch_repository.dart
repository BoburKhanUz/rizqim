import 'package:pos_core/pos_core.dart';

import '../http/pos_http.dart';
import 'menu_day_models.dart';

/// P6-15: qozon/partiya hisobdan chiqarish (docs/API.md "Bugungi menyu, kompleks va partiya hisobi"). Yo'llar `owner`
/// guruhida: terminalning xodim PIN sessiyasi bilan ishlaydi, ruxsat `batches.manage` (menejer, oshpaz, ega; kassirda
/// odatda yo'q — P6-19: menejer shu kassada PIN'ini teradi, `PosPermissions.batchesManage` tasdig'i). Modul (`batch_writeoff`) o'chiq bo'lsa — 403
/// `module_disabled` ([PosModuleDisabled]).
class PosBatchRepository {
  PosBatchRepository(this._http);

  final PosHttp _http;

  /// `GET /owner/batches?branch_id`: filialning ochiq partiyalari (`batches.manage` yoki `menu.view` kerak).
  Future<List<PosBatchInfo>> open({required int branchId}) => _http.get(
    '/v1/owner/batches',
    (Object? data) =>
        dataObject(data).objects('batches', PosBatchInfo.fromJson),
    query: <String, Object?>{'branch_id': branchId},
  );

  /// `POST /owner/batches/{id}/write-off {quantity, reason, note?, close?}` `Idempotency-Key` bilan: partiya
  /// qoldig'idan sabab bilan ayiradi (qoldiqdan ko'p — 422; `other` izohsiz bo'lmaydi). [close] — qolganini ham
  /// chiqarib partiyani yopadi. Terminaldan qilinsa terminalning ochiq smenasiga bog'lanadi (X/Z da ko'rinadi).
  /// [approvalToken] — menejer tasdig'i (`batches.manage`; tokensiz kassirga server 403 `approval_required` deydi —
  /// `PosApprovalRequired`, `withApproval` / `runWithApproval` oynani ochadi). Tarmoq uzilganda aynan shu amalni qayta yuborishda o'sha [idempotencyKey].
  Future<PosBatchWriteOff> writeOff(
    int batchId, {
    required PosQuantity quantity,
    required PosWriteOffReason reason,
    String? note,
    bool close = false,
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/owner/batches/$batchId/write-off',
    (Object? data) => PosBatchWriteOff.fromJson(dataObject(data)),
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'quantity': quantity.toApiString(),
      'reason': reason.wire,
      if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      if (close) 'close': true,
      'approval_token': ?approvalToken,
    },
  );
}
