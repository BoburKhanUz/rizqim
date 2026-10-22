import '../http/pos_http.dart';
import 'floor_models.dart';

/// `GET /pos/floor`: zal xaritasi. `order.*` hodisalarida (va polling'da) qayta so'raladi.
class FloorRepository {
  FloorRepository(this._http);

  final PosHttp _http;

  /// [branchId] standart — xodimning (terminalning) filiali.
  Future<PosFloor> load({int? branchId}) => _http.get(
    '/v1/pos/floor',
    (Object? data) => PosFloor.fromJson(dataObject(data)),
    query: <String, Object?>{'branch_id': branchId},
  );
}
