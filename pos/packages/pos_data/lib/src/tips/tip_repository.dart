import 'package:pos_core/pos_core.dart';

import '../http/pos_http.dart';
import 'tip_models.dart';

/// Choy puli (docs/API.md, "Choy puli", P6-22): smenaning choy pullari va menejer tuzatishi. Choy pulining o'zi
/// to'lov bilan olinadi ([PosTenderInput] `tip`, `BillingRepository.pay` `tipStaffId`).
///
/// Xatolar: tuzatishga ruxsat yo'q — [PosApprovalRequired] (`tips.manage`); yopilgan kun — [PosConflict]
/// `tips_period_closed`; o'zgarish yo'q — [PosRejected] `nothing_to_correct`.
class PosTipsRepository {
  PosTipsRepository(this._http);

  final PosHttp _http;

  static PosShiftTips _list(Object? data) =>
      PosShiftTips.fromJson(dataObject(data));

  static PosTip _tip(Object? data) => PosTip.fromJson(dataObject(data));

  /// `GET /pos/tips`: [shiftUuid] (sukut — terminalning ochiq smenasi) choy pullari.
  Future<PosShiftTips> shiftTips({String? shiftUuid}) => _http.get(
    '/v1/pos/tips',
    _list,
    query: <String, Object?>{'shift_uuid': ?shiftUuid},
  );

  /// `POST /pos/tips/{uuid}/corrections`: choy puli endi [amount] (`null` — o'zgarmaydi) bo'lib [mode] ga
  /// ([staffId]; `null` — hech kimga) boradi. Sabab majburiy; `tips.manage` yo'q bo'lsa — menejer [approvalToken].
  Future<PosTip> correct(
    String uuid, {
    required PosTipMode mode,
    required String reason,
    PosMoney? amount,
    int? staffId,
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/tips/${Uri.encodeComponent(uuid)}/corrections',
    _tip,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'amount': ?amount?.toApiString(),
      'mode': mode.wire,
      'staff_id': mode == PosTipMode.personal ? staffId : null,
      'reason': reason,
      'approval_token': ?approvalToken,
    },
  );
}
