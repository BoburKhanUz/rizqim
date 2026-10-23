import 'dart:async';
import 'dart:convert';

import '../http/pos_exceptions.dart';
import '../http/pos_http.dart';
import '../json.dart';
import '../offline/pos_offline.dart';
import 'catalog_models.dart';

/// Menyuni qurilmada saqlash (ilova qayta ochilganda, aloqa yo'q paytda ham menyu bo'lsin). Kalit — kanal; qiymat —
/// `GET /catalog` javobining `data` qismi (JSON satr). Standart — xotirada ([MemoryPosCatalogCache]); ilova faylga
/// yoki `shared_preferences` ga yozadigan o'z amalga oshirishini beradi.
abstract interface class PosCatalogCache {
  Future<String?> read(PosChannel channel);

  Future<void> write(PosChannel channel, String json);

  Future<void> clear();
}

class MemoryPosCatalogCache implements PosCatalogCache {
  final Map<PosChannel, String> _items = <PosChannel, String>{};

  @override
  Future<String?> read(PosChannel channel) async => _items[channel];

  @override
  Future<void> write(PosChannel channel, String json) async =>
      _items[channel] = json;

  @override
  Future<void> clear() async => _items.clear();
}

/// `GET /catalog?channel=pos_hall|pos_takeaway`, `menu_version` bo'yicha keshlanadi.
///
/// * [load] bootstrap'dagi `menu_version` bilan chaqiriladi: keshdagi menyu shu versiyada bo'lsa server so'ralmaydi.
///   Versiya o'zgarsa (narx, nom, modifikator ...) — qayta olinadi.
/// * Qoldiq, stop-list va qozon versiyani o'zgartirmaydi: `menu.availability_changed` hodisasida
///   [refreshAvailability] (`GET /catalog/availability`) faqat shu qismni yangilaydi.
/// * Har yangilanish [changes] ga chiqadi.
class PosCatalogRepository {
  PosCatalogRepository(
    this._http, {
    PosCatalogCache? cache,
    DateTime Function()? clock,
  }) : cache = cache ?? MemoryPosCatalogCache(),
       _clock = clock ?? DateTime.now;

  final PosHttp _http;
  final PosCatalogCache cache;
  final DateTime Function() _clock;

  /// P3-31: menyu nusxasi va uning yoshi (24 soatdan eski menyu bilan oflayn sotilmaydi); aloqa yo'q paytda — nusxa.
  PosOffline? offline;

  final Map<PosChannel, PosCatalog> _memory = <PosChannel, PosCatalog>{};
  final StreamController<PosCatalog> _changes =
      StreamController<PosCatalog>.broadcast();

  /// Serverdan necha marta olindi (testlar va diagnostika).
  int fetches = 0;

  Stream<PosCatalog> get changes => _changes.stream;

  /// Xotiradagi menyu (yuklanmagan — `null`).
  PosCatalog? cached(PosChannel channel) => _memory[channel];

  /// Menyu: [menuVersion] berilsa va keshdagisi shu versiyada bo'lsa — keshdan, aks holda serverdan. [force] —
  /// har doim serverdan.
  Future<PosCatalog> load(
    PosChannel channel, {
    String? menuVersion,
    bool force = false,
    int? branchId,
  }) async {
    if (!force) {
      final PosCatalog? mem = _memory[channel];
      if (mem != null &&
          (menuVersion == null || mem.menuVersion == menuVersion)) {
        if (menuVersion != null) {
          await offline?.confirmCatalog(channel, menuVersion);
        }
        return mem;
      }
      if (menuVersion != null) {
        final PosCatalog? stored = await _readStored(channel);
        if (stored != null && stored.menuVersion == menuVersion) {
          _put(stored);
          await offline?.confirmCatalog(channel, menuVersion);
          return stored;
        }
      }
    }
    try {
      return await _fetch(channel, branchId: branchId);
    } on PosNetworkException {
      // No server: the last menu kept on the device (the limited mode checks its age)
      final PosCatalog? stored =
          _memory[channel] ??
          await _readStored(channel) ??
          offline?.storedCatalog(channel);
      if (stored == null) rethrow;
      _put(stored);
      return stored;
    }
  }

  /// Qurilmada saqlangan menyu (aloqa yo'q paytda ishga tushganda). Yo'q yoki o'qib bo'lmasa `null`.
  Future<PosCatalog?> loadStored(PosChannel channel) async {
    final PosCatalog? stored = await _readStored(channel);
    if (stored != null) _put(stored);
    return stored;
  }

  /// `GET /catalog/availability?store_id&branch_id`: faqat stop-list, tugaganlar va qozonlar (ikkala kanalga).
  /// Hali menyu yuklanmagan bo'lsa hech narsa qilmaydi.
  Future<void> refreshAvailability({
    required int storeId,
    int? branchId,
  }) async {
    if (_memory.isEmpty) return;
    final int branch = branchId ?? _memory.values.first.branchId;
    final PosAvailability? next = await _http.get('/v1/catalog/availability', (
      Object? data,
    ) {
      final PosJson j = dataObject(data);
      for (final PosJson b in j.objects('branches', (PosJson x) => x)) {
        if (b.intOrNull('branch_id') == branch) {
          return PosAvailability.fromJson(b);
        }
      }
      return null;
    }, query: <String, Object?>{'store_id': storeId, 'branch_id': branch});
    if (next == null) return;
    for (final PosChannel c in _memory.keys.toList()) {
      _put(_memory[c]!.withAvailability(next));
    }
  }

  /// Xodim chiqqanda emas, terminal uzilganda (boshqa filial menyusi qolmasin).
  Future<void> clear() async {
    _memory.clear();
    await cache.clear();
  }

  Future<void> dispose() => _changes.close();

  Future<PosCatalog> _fetch(PosChannel channel, {int? branchId}) async {
    fetches++;
    late Object? rawData;
    final PosCatalog catalog = await _http.get(
      '/v1/catalog',
      (Object? data) {
        rawData = data;
        return PosCatalog.fromJson(dataObject(data), fetchedAt: _clock());
      },
      query: <String, Object?>{'channel': channel.wire, 'branch_id': branchId},
    );
    _put(catalog);
    try {
      await cache.write(channel, jsonEncode(rawData));
    } on Object {
      // Saqlab bo'lmadi: menyu xotirada baribir bor
    }
    if (branchId == null) await offline?.captureCatalog(channel, rawData);
    return catalog;
  }

  Future<PosCatalog?> _readStored(PosChannel channel) async {
    try {
      final String? text = await cache.read(channel);
      if (text == null) return null;
      return PosCatalog.fromJson(PosJson.of(jsonDecode(text)));
    } on Object {
      return null;
    }
  }

  void _put(PosCatalog catalog) {
    _memory[catalog.channel] = catalog;
    if (!_changes.isClosed) _changes.add(catalog);
  }
}
