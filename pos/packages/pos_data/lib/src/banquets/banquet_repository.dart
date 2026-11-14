import '../http/pos_http.dart';
import '../offline/pos_offline.dart';
import '../offline/sync_models.dart';
import 'banquet_models.dart';

/// P6-51/P6-52: tadbirlar taqvimi kassada (docs/API.md "Tadbirlar"): zal × sana × vaqt, tadbir kartasi, zalni
/// vaqtincha band qilish (hold), o'zgartirish va ko'chirish, narxlarni muzlatish. O'qish — `banquets.manage` yoki
/// `pos.take_payment`; o'zgartirish — `banquets.manage`. Faqat internet bilan.
///
/// Xatolar: 409 `slot_taken` (zal shu vaqtda band), 409 `hall_has_bookings` (zaldagi xona bron qilingan), 409
/// `stale_version`, 409 `event_frozen` (muzlatilgan narxda paketni almashtirish), 409 `event_not_editable`, 422
/// `event_in_past`, 403 `module_disabled`.
class PosBanquetRepository {
  PosBanquetRepository(this._http, {this.offline});

  final PosHttp _http;
  final PosOffline? offline;

  static const String offlineMessage =
      "Internet yo'q: tadbirlar taqvimi faqat server bilan ishlaydi.";

  void _online() {
    if (offline?.active ?? false) {
      throw const PosOfflineUnavailable(offlineMessage);
    }
  }

  static String _id(String uuid) => Uri.encodeComponent(uuid);

  /// `GET /pos/banquets/calendar?from=&to=`: filial zallari va kunlardagi tadbirlar (ko'pi bilan 62 kun).
  Future<PosBanquetCalendar> calendar({
    String? from,
    String? to,
    bool includeCancelled = false,
  }) async {
    _online();
    return _http.get(
      '/v1/pos/banquets/calendar',
      (Object? data) => PosBanquetCalendar.fromJson(dataObject(data)),
      query: <String, Object?>{
        'from': ?from,
        'to': ?to,
        if (includeCancelled) 'include_cancelled': 1,
      },
    );
  }

  /// `GET /pos/banquets?from=&to=&status=&phone=`: ro'yxat yoki telefon bo'yicha qidirish.
  Future<List<PosBanquetEvent>> list({
    String? from,
    String? to,
    PosBanquetStatus? status,
    String? phone,
  }) async {
    _online();
    return _http.get(
      '/v1/pos/banquets',
      (Object? data) =>
          dataObject(data).objects('events', PosBanquetEvent.fromJson),
      query: <String, Object?>{
        'from': ?from,
        'to': ?to,
        'status': ?status?.wire,
        'phone': ?phone,
      },
    );
  }

  /// `GET /pos/banquets/catalog`: paketlar, qo'shimchalar, zal narxlari, vaqtlar.
  Future<PosBanquetCatalog> catalog() async {
    _online();
    return _http.get(
      '/v1/pos/banquets/catalog',
      (Object? data) => PosBanquetCatalog.fromJson(dataObject(data)),
    );
  }

  /// `GET /pos/banquets/{uuid}`: tadbir kartasi (qatorlar, jadval, ogohlantirishlar).
  Future<PosBanquetEvent> show(String uuid) async {
    _online();
    return _http.get(
      '/v1/pos/banquets/${_id(uuid)}',
      (Object? data) => PosBanquetEvent.fromJson(dataObject(data)),
    );
  }

  /// `POST /pos/banquets`: zalni vaqtincha band qilish ([draft] da sana, slot, zallar, mijoz, mehmonlar majburiy).
  Future<PosBanquetEvent> createHold(
    PosBanquetDraft draft, {
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/banquets',
      (Object? data) => PosBanquetEvent.fromJson(dataObject(data)),
      idempotencyKey: idempotencyKey,
      body: draft.toJson(),
    );
  }

  /// `PATCH /pos/banquets/{uuid}`: o'zgartirish yoki ko'chirish (to'lovlar saqlanadi).
  Future<PosBanquetEvent> update(
    String uuid,
    PosBanquetDraft draft, {
    required int version,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'PATCH',
      '/v1/pos/banquets/${_id(uuid)}',
      (Object? data) => PosBanquetEvent.fromJson(dataObject(data)),
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{...draft.toJson(), 'version': version},
    );
  }

  /// `POST /pos/banquets/{uuid}/freeze`: shartnoma kuni narxlarni muzlatish.
  Future<PosBanquetEvent> freeze(
    String uuid, {
    required int version,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/banquets/${_id(uuid)}/freeze',
      (Object? data) => PosBanquetEvent.fromJson(dataObject(data)),
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{'version': version},
    );
  }

  /// `POST /pos/banquets/{uuid}/unfreeze`: muzlatishni sabab bilan bekor qilish (`banquets.manage`).
  Future<PosBanquetEvent> unfreeze(
    String uuid, {
    required int version,
    required String reason,
    String? idempotencyKey,
  }) async {
    _online();
    return _http.mutate(
      'POST',
      '/v1/pos/banquets/${_id(uuid)}/unfreeze',
      (Object? data) => PosBanquetEvent.fromJson(dataObject(data)),
      idempotencyKey: idempotencyKey,
      body: <String, Object?>{'version': version, 'reason': reason},
    );
  }
}
