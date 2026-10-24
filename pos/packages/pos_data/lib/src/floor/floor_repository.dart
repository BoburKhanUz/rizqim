import '../http/pos_exceptions.dart';
import '../http/pos_http.dart';
import '../offline/pos_offline.dart';
import 'floor_models.dart';

/// `GET /pos/floor`: zal xaritasi. `order.*` hodisalarida (va polling'da) qayta so'raladi. P3-31: [offline] bilan —
/// onlayn javob nusxa sifatida saqlanadi, cheklangan rejimda xarita nusxa + oflayn buyurtmalardan.
class FloorRepository {
  FloorRepository(this._http, {this.offline});

  final PosHttp _http;
  final PosOffline? offline;

  /// [branchId] standart — xodimning (terminalning) filiali.
  Future<PosFloor> load({int? branchId}) async {
    final PosOffline? o = offline;
    if (o != null && o.active && branchId == null) return o.floor();
    Object? raw;
    try {
      final PosFloor floor = await _http.get('/v1/pos/floor', (Object? data) {
        raw = data;
        return PosFloor.fromJson(dataObject(data));
      }, query: <String, Object?>{'branch_id': branchId});
      if (o != null && branchId == null && raw is Map) {
        await o.captureFloor((raw! as Map).cast<String, Object?>());
      }
      return floor;
    } on PosNetworkException {
      if (o != null && branchId == null) return o.floor();
      rethrow;
    }
  }
}
