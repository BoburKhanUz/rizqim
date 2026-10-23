import '../http/pos_http.dart';
import 'bootstrap_models.dart';

/// `GET /pos/bootstrap` (xodim PIN sessiyasi bilan, terminal filiali). Oxirgi natija [current] da.
class PosBootstrapRepository {
  PosBootstrapRepository(this._http, {DateTime Function()? clock})
    : _clock = clock ?? DateTime.now;

  final PosHttp _http;
  final DateTime Function() _clock;

  PosBootstrap? _current;

  /// Oxirgi yuklangan bootstrap (hali yuklanmagan — `null`).
  PosBootstrap? get current => _current;

  /// [branchId] faqat filialga biriktirilmagan chaqiruvchi uchun (ega, do'konga ulangan terminal).
  Future<PosBootstrap> load({int? branchId}) async {
    final PosBootstrap b = await _http.get(
      '/v1/pos/bootstrap',
      (Object? data) =>
          PosBootstrap.fromJson(dataObject(data), receivedAt: _clock()),
      query: <String, Object?>{'branch_id': branchId},
    );
    _current = b;
    return b;
  }

  /// Xodim chiqqanda yoki terminal uzilganda.
  void clear() => _current = null;
}
