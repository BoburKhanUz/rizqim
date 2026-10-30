import '../http/pos_http.dart';
import 'staff_pay_models.dart';

/// Ish vaqtini belgilash (docs/API.md, "Ish haqi qoidalari", P6-23): kirgan xodim o'z PIN sessiyasidan keladi va ketadi.
/// Yupqa qatlam: o'zgartiruvchi so'rov `Idempotency-Key` bilan; modul o'chiq — 403 `module_disabled`.
///
/// Xatolar: allaqachon ishda — [PosConflict] `already_clocked_in`; ishda emas — [PosConflict] `not_clocked_in`; terminal
/// sessiyasi emas — 403 `terminal_required`.
class StaffClockRepository {
  StaffClockRepository(this._http);

  final PosHttp _http;

  static PosClockState _state(Object? data) =>
      PosClockState.fromJson(dataObject(data));

  /// `GET /pos/clock`.
  Future<PosClockState> state() => _http.get('/v1/pos/clock', _state);

  /// `POST /pos/clock/in`: "Ishga keldim".
  Future<PosClockState> clockIn({String? idempotencyKey}) => _http.mutate(
    'POST',
    '/v1/pos/clock/in',
    _state,
    idempotencyKey: idempotencyKey,
  );

  /// `POST /pos/clock/out`: "Ishdan ketdim".
  Future<PosClockState> clockOut({String? idempotencyKey}) => _http.mutate(
    'POST',
    '/v1/pos/clock/out',
    _state,
    idempotencyKey: idempotencyKey,
  );
}
