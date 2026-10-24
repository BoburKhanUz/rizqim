import '../http/pos_exceptions.dart';
import '../http/pos_http.dart';
import '../offline/pos_offline.dart';
import 'bootstrap_models.dart';

/// `GET /pos/bootstrap` (xodim PIN sessiyasi bilan, terminal filiali). Oxirgi natija [current] da.
class PosBootstrapRepository {
  PosBootstrapRepository(this._http, {DateTime Function()? clock})
    : _clock = clock ?? DateTime.now;

  final PosHttp _http;
  final DateTime Function() _clock;

  /// P3-31: onlayn javob nusxa sifatida saqlanadi; aloqa yo'q bo'lsa — oxirgi nusxa (24 soatdan yangi bo'lsa).
  PosOffline? offline;

  PosBootstrap? _current;

  /// Oxirgi yuklangan bootstrap (hali yuklanmagan — `null`).
  PosBootstrap? get current => _current;

  /// [branchId] faqat filialga biriktirilmagan chaqiruvchi uchun (ega, do'konga ulangan terminal).
  Future<PosBootstrap> load({int? branchId}) async {
    final PosOffline? o = offline;
    final PosBootstrap b;
    try {
      b = await _http.get(
        '/v1/pos/bootstrap',
        (Object? data) =>
            PosBootstrap.fromJson(dataObject(data), receivedAt: _clock()),
        query: <String, Object?>{'branch_id': branchId},
      );
    } on PosNetworkException {
      final PosBootstrap? stored = branchId == null
          ? o?.storedBootstrap()
          : null;
      if (stored == null) rethrow;
      _current = stored;
      return stored;
    }
    if (o != null && branchId == null) await o.captureBootstrap(b.raw);
    _current = b;
    return b;
  }

  /// Xodim chiqqanda yoki terminal uzilganda.
  void clear() => _current = null;
}
