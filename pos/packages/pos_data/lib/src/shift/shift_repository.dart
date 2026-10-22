import 'package:pos_core/pos_core.dart';

import '../http/pos_http.dart';
import '../json.dart';
import 'shift_models.dart';

/// Smena, kassa harakatlari, X va Z (docs/API.md, "Smena, kassa, X va Z"). Yupqa qatlam: P3-22 kengaytiradi.
class ShiftRepository {
  ShiftRepository(this._http);

  final PosHttp _http;

  static PosShift _shift(Object? data) => PosShift.fromJson(dataObject(data));

  /// `GET /pos/shifts/current`: shu terminalning ochiq smenasi va X hisobot (yo'q — `shift: null`).
  Future<PosShiftWithReport> current() => _http.get(
    '/v1/pos/shifts/current',
    (Object? data) => PosShiftWithReport.fromJson(dataObject(data)),
  );

  /// `POST /pos/shifts`: shu terminalda smena ochish. Ochiq bo'lsa — [PosConflict] `shift_already_open`.
  Future<PosShift> open({
    required PosMoney openingCash,
    String? note,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/shifts',
    _shift,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'opening_cash': openingCash.toApiString(),
      'note': ?note,
    },
  );

  /// `GET /pos/shifts/{uuid}`: smena (yopilgan bo'lsa Z ma'lumotlari bilan).
  Future<PosShift> get(String uuid) =>
      _http.get('/v1/pos/shifts/$uuid', _shift);

  /// `GET /pos/shifts?branch_id&status&from&to&limit`.
  Future<List<PosShift>> list({
    int? branchId,
    String? status,
    String? from,
    String? to,
    int? limit,
  }) => _http.get(
    '/v1/pos/shifts',
    (Object? data) => <PosShift>[
      for (final Object? e in (data as List<Object?>?) ?? const <Object?>[])
        PosShift.fromJson(PosJson.of(e)),
    ],
    query: <String, Object?>{
      'branch_id': branchId,
      'status': status,
      'from': from,
      'to': to,
      'limit': limit,
    },
  );

  /// `GET /pos/shifts/{uuid}/x-report`.
  Future<PosShiftWithReport> xReport(String uuid) => _http.get(
    '/v1/pos/shifts/$uuid/x-report',
    (Object? data) => PosShiftWithReport.fromJson(dataObject(data)),
  );

  /// `POST /pos/shifts/{uuid}/cash-movements`: kirim, chiqim, ish haqi ([employeeId] majburiy), bozorlik. `other`
  /// kategoriyasi izohsiz bo'lmaydi; chiqim kassadagidan oshsa — [PosConflict] `not_enough_cash`.
  Future<PosCashMovement> cashMovement(
    String shiftUuid, {
    required PosCashMovementType type,
    required String category,
    required PosMoney amount,
    String? note,
    int? employeeId,
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/shifts/$shiftUuid/cash-movements',
    (Object? data) =>
        PosCashMovement.fromJson(dataObject(data).obj('movement')),
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'type': type.wire,
      'category': category,
      'amount': amount.toApiString(),
      'note': ?note,
      'employee_id': ?employeeId,
      'approval_token': ?approvalToken,
    },
  );

  /// `POST /pos/shifts/{uuid}/close` (Z): [countedBreakdown] — kupyura (so'm) → soni. Ochiq buyurtmalar bo'lsa
  /// [PosConflict] `shift_has_open_orders`; [force] + [reason] bilan majburan (menejer yoki tasdiq).
  Future<PosShift> close(
    String shiftUuid, {
    required Map<int, int> countedBreakdown,
    bool force = false,
    String? reason,
    String? approvalToken,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/pos/shifts/$shiftUuid/close',
    _shift,
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'counted_breakdown': <String, int>{
        for (final MapEntry<int, int> e in countedBreakdown.entries)
          '${e.key}': e.value,
      },
      if (force) 'force': true,
      'reason': ?reason,
      'approval_token': ?approvalToken,
    },
  );
}
