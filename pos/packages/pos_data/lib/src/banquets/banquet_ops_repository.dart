import '../http/pos_http.dart';
import '../offline/pos_offline.dart';
import '../offline/sync_models.dart';
import 'banquet_ops_models.dart';

/// P6-54: tadbirning ishlab chiqarish varag'i, osh kalkulyatori, bozorlik ro'yxati, shartnoma, tadbir kuni va yakuniy
/// hisob kassada (docs/API.md "Tadbirlar: ishlab chiqarish va yakuniy hisob"). Faqat internet bilan.
///
/// Yakuniy hisob: [settle] paket, zal va qo'shimchalarni tadbir buyurtmasiga qo'shib bitta schyot tuzadi va
/// zakladlarni oldindan to'lov sifatida ayiradi; qoldiq oddiy to'lov (`BillingRepository`) bilan olinadi — bitta sotuv
/// cheki. Xatolar: 409 `invalid_transition`, `order_closed`, `stale_version`; 422 `mxik_missing`; 403
/// `approval_required` (ortiqcha zakladni naqd qaytarish).
class PosBanquetOpsRepository {
  PosBanquetOpsRepository(this._http, {this.offline});

  final PosHttp _http;
  final PosOffline? offline;

  static const String offlineMessage =
      "Internet yo'q: tadbir varag'i va yakuniy hisob faqat server bilan ishlaydi.";

  void _online() {
    if (offline?.active ?? false) {
      throw const PosOfflineUnavailable(offlineMessage);
    }
  }

  static String _id(String uuid) => Uri.encodeComponent(uuid);

  /// `GET /pos/banquets/{uuid}/production-sheet`.
  Future<PosProductionSheet> productionSheet(String uuid) async {
    _online();
    return _http.get(
      '/v1/pos/banquets/${_id(uuid)}/production-sheet',
      (Object? data) => PosProductionSheet.fromJson(dataObject(data)),
    );
  }

  /// `GET /pos/banquets/shopping-list?from=&to=` (ko'pi bilan 62 kun).
  Future<PosShoppingList> shoppingList({String? from, String? to}) async {
    _online();
    return _http.get(
      '/v1/pos/banquets/shopping-list',
      (Object? data) => PosShoppingList.fromJson(dataObject(data)),
      query: <String, Object?>{'from': ?from, 'to': ?to},
    );
  }

  /// `GET /pos/banquets/osh-calculator?persons=`.
  Future<PosOshResult> osh(int persons) async {
    _online();
    return _http.get(
      '/v1/pos/banquets/osh-calculator',
      (Object? data) => PosOshResult.fromJson(dataObject(data)),
      query: <String, Object?>{'persons': persons},
    );
  }

  /// `GET /pos/banquets/{uuid}/contract` (`banquets.manage`).
  Future<PosBanquetContract> contract(String uuid) async {
    _online();
    return _http.get(
      '/v1/pos/banquets/${_id(uuid)}/contract',
      (Object? data) => PosBanquetContract.fromJson(dataObject(data)),
    );
  }

  /// `POST /pos/banquets/{uuid}/contract/number`: shartnoma raqami (bir marta).
  Future<PosBanquetContract> numberContract(
    String uuid, {
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/banquets/${_id(uuid)}/contract/number',
      (Object? data) => PosBanquetContract.fromJson(dataObject(data)),
      idempotencyKey: idempotencyKey,
    );
  }

  /// `POST /pos/banquets/{uuid}/start`: tadbir kuni — tadbirga bog'langan buyurtma ochiladi; javobdagi `order_uuid`.
  Future<String?> start(
    String uuid, {
    int? version,
    int? waiterId,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/banquets/${_id(uuid)}/start',
      (Object? data) => dataObject(data).obj('event').strOrNull('order_uuid'),
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{'version': ?version, 'waiter_id': ?waiterId},
    );
  }

  /// `GET /pos/banquets/{uuid}/settlement`.
  Future<PosBanquetSettlement> settlement(String uuid) async {
    _online();
    return _http.get(
      '/v1/pos/banquets/${_id(uuid)}/settlement',
      (Object? data) => PosBanquetSettlement.fromJson(dataObject(data)),
    );
  }

  /// `PATCH /pos/banquets/{uuid}/guests`: kelgan mehmonlar (`banquets.manage`).
  Future<PosBanquetSettlement> setGuestsActual(
    String uuid,
    int guests, {
    int? version,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'PATCH',
      '/v1/pos/banquets/${_id(uuid)}/guests',
      (Object? data) =>
          PosBanquetSettlement.fromJson(dataObject(data).obj('settlement')),
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{'guests_actual': guests, 'version': ?version},
    );
  }

  /// `POST /pos/banquets/{uuid}/settle`: yakuniy schyot (zakladlar ayirilgan); `approval_token` — ortiqcha zakladni
  /// naqd qaytarish uchun menejer tasdig'i (ixtiyoriy).
  Future<PosSettleResult> settle(
    String uuid, {
    String? shiftUuid,
    int? version,
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/banquets/${_id(uuid)}/settle',
      (Object? data) => PosSettleResult.fromJson(dataObject(data)),
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{
        'shift_uuid': ?shiftUuid,
        'version': ?version,
        'approval_token': ?approvalToken,
      },
    );
  }

  /// `POST /pos/banquets/{uuid}/settlement/refund-excess`: tadbir summasidan ortiq zakladni qaytarish.
  Future<PosBanquetSettlement> refundExcess(
    String uuid, {
    String? approvalToken,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/banquets/${_id(uuid)}/settlement/refund-excess',
      (Object? data) => PosBanquetSettlement.fromJson(dataObject(data)),
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{'approval_token': ?approvalToken},
    );
  }
}
