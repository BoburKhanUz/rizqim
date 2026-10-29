import 'package:rizqim_api/rizqim_api.dart';

import '../http/pos_http.dart';
import '../pickup/queue_number_models.dart';
import 'display_models.dart';

/// P6-14: mijoz ekrani endpointlari (docs/API.md "Mijoz ekrani"). Hammasi **terminalning o'z tokeni** bilan ketadi
/// ([AuthScope.terminal]): mijoz ekranida xodim yo'q, kassa esa chekni PIN sessiyasidan mustaqil yuboradi. Modul
/// o'chiq bo'lsa — 403 `module_disabled` ([PosModuleDisabled]).
class PosDisplayRepository {
  PosDisplayRepository(this._http);

  final PosHttp _http;

  /// `GET /pos/display/state`: filial, kuzatadigan kassa, modullar va joriy chek.
  Future<PosDisplayState> state() => _http.get(
    '/v1/pos/display/state',
    (Object? data) => PosDisplayState.fromJson(dataObject(data)),
    scope: AuthScope.terminal,
  );

  /// `GET /pos/display/queue`: bugungi tayyorlanayotgan va tayyor raqamlar.
  Future<PosQueueBoard> queue() => _http.get(
    '/v1/pos/display/queue',
    (Object? data) => PosQueueBoard.fromJson(dataObject(data)),
    scope: AuthScope.terminal,
  );

  /// `POST /pos/display/cart`: kassa chekni ikkinchi ekranga yuboradi. `true` — saqlandi, `false` — server eskirgan
  /// yuborish deb tashladi. `Idempotency-Key` yo'q: har yuborish oldingisini almashtiradi.
  Future<bool> publish(PosDisplayCart cart) async {
    final PosResponse r = await _http.raw(
      'POST',
      '/v1/pos/display/cart',
      body: cart.toJson(),
      scope: AuthScope.terminal,
      idempotent: false,
    );
    final Object? data = r.data;
    return data is Map && data['stored'] != false;
  }
}
