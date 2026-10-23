/// Oshxona ekrani uchun sexlar va qozon (partiya) qoldig'i: qo'lda yozilgan, xom JSON bilan ishlaydigan ko'rinish.
///
/// Sexlar `GET /pos/bootstrap` dan (docs/API.md, P3-13), qozon `/owner/batches*` dan (docs/MENU.md §4). Yaratilgan klient
/// bu javoblarni qattiq modellar bilan o'qiydi (zallar, smena, printerlar ...): oshxona ekraniga ularning hammasi kerak
/// emas va bitta mos kelmagan maydon butun javobni yiqitmasligi kerak, shuning uchun bu yerda faqat kerakli maydonlar
/// yumshoq o'qiladi.
library;

import 'package:dio/dio.dart';

import '../auth/api_exception.dart';
import 'kitchen_models.dart';

/// Sex turlari (`kitchen_stations.kind`, docs/API.md P3-13).
abstract final class StationKind {
  static const String osh = 'osh';
  static const String mangal = 'mangal';
  static const String saladBar = 'salad_bar';
  static const String tea = 'tea';
  static const String kitchen = 'kitchen';
  static const String other = 'other';
}

/// Filial sexi (oshxona ekrani tanlaydigan).
class KitchenStation {
  const KitchenStation({
    required this.id,
    required this.name,
    required this.kind,
    this.kindLabel = '',
    this.sortOrder = 0,
    this.showOnKds = true,
    this.isActive = true,
  });

  factory KitchenStation.fromJson(Map<String, Object?> json) => KitchenStation(
    id: (json['id']! as num).toInt(),
    name: '${json['name'] ?? ''}',
    kind: (json['kind'] as String?) ?? StationKind.other,
    kindLabel: (json['kind_label'] as String?) ?? '',
    sortOrder: (json['sort_order'] as num?)?.toInt() ?? 0,
    showOnKds: json['show_on_kds'] is bool
        ? json['show_on_kds']! as bool
        : true,
    isActive: json['is_active'] is bool ? json['is_active']! as bool : true,
  );

  final int id;
  final String name;

  /// [StationKind] qiymatlaridan biri.
  final String kind;
  final String kindLabel;
  final int sortOrder;
  final bool showOnKds;
  final bool isActive;

  bool get isMangal => kind == StationKind.mangal;
}

/// `GET /pos/bootstrap` dan oshxona ekraniga kerak qismi.
class KitchenSetup {
  const KitchenSetup({required this.stations, this.serverTime});

  /// Faol va KDS'da ko'rsatiladigan sexlar (`sort_order` bo'yicha).
  final List<KitchenStation> stations;
  final DateTime? serverTime;
}

/// Qozon (partiya): filialda bir taom (yoki variant) uchun pishirilgan va qolgan porsiyalar.
class KitchenBatch {
  const KitchenBatch({
    required this.id,
    required this.productId,
    required this.productName,
    required this.isOpen,
    this.productVariantId,
    this.variantName,
    this.unit,
    this.label,
    this.quantityProduced,
    this.quantityLeft,
    this.startedAt,
    this.closedAt,
    this.soldOutAt,
  });

  factory KitchenBatch.fromJson(Map<String, Object?> json) => KitchenBatch(
    id: (json['id']! as num).toInt(),
    productId: (json['product_id'] as num?)?.toInt() ?? 0,
    productName: '${json['product_name'] ?? ''}',
    productVariantId: (json['product_variant_id'] as num?)?.toInt(),
    variantName: json['variant_name'] as String?,
    unit: json['unit'] as String?,
    label: json['label'] as String?,
    quantityProduced: json['quantity_produced'] as num?,
    quantityLeft: json['quantity_left'] as num?,
    isOpen: json['is_open'] == true,
    startedAt: _date(json['started_at']),
    closedAt: _date(json['closed_at']),
    soldOutAt: _date(json['sold_out_at']),
  );

  final int id;
  final int productId;
  final String productName;
  final int? productVariantId;
  final String? variantName;
  final String? unit;
  final String? label;
  final num? quantityProduced;
  final num? quantityLeft;
  final bool isOpen;
  final DateTime? startedAt;
  final DateTime? closedAt;
  final DateTime? soldOutAt;

  /// "Osh (katta)" yoki "Osh".
  String get title {
    final String? variant = variantName?.trim();
    return variant == null || variant.isEmpty
        ? productName
        : '$productName ($variant)';
  }

  /// Ochiq, lekin hech narsa qolmagan: "tugadi".
  bool get isSoldOut => isOpen && (quantityLeft ?? 0) <= 0;

  /// Qolgan ulush 0..1 (ishlab chiqarilgan miqdor noma'lum bo'lsa `null`).
  double? get fractionLeft {
    final num? produced = quantityProduced;
    final num? left = quantityLeft;
    if (produced == null || left == null || produced <= 0) return null;
    return (left / produced).clamp(0, 1).toDouble();
  }

  /// Shu taom/variantga yangi qozon ochish uchun kalit.
  String get productKey => '$productId/${productVariantId ?? 0}';
}

/// Qozon bo'yicha sotiladigan taom (`products.batch_tracked`): yangi qozon ochish uchun tanlanadi.
class BatchProduct {
  const BatchProduct({
    required this.id,
    required this.name,
    this.variants = const <BatchProductVariant>[],
  });

  final int id;
  final String name;
  final List<BatchProductVariant> variants;
}

class BatchProductVariant {
  const BatchProductVariant({required this.id, required this.name});

  final int id;
  final String name;
}

/// Sexlar va qozon so'rovlari (xodim PIN sessiyasi tokeni bilan).
class KitchenSetupService {
  KitchenSetupService(this._dio, {DateTime Function()? clock})
    : _clock = clock ?? DateTime.now;

  final Dio _dio;
  final DateTime Function() _clock;

  static const String _bootstrapPath = '/v1/pos/bootstrap';
  static const String _batchesPath = '/v1/owner/batches';
  static const String _catalogPath = '/v1/catalog';

  /// `GET /pos/bootstrap`: faol sexlar. Oshxona ekrani faqat `show_on_kds` bo'lganlarini ko'rsatadi.
  Future<KitchenSetup> setup({int? branchId}) async {
    final Map<String, Object?> data = await _get(
      _bootstrapPath,
      <String, Object?>{'branch_id': ?branchId},
    );
    final List<KitchenStation> stations =
        _list(
            data['stations'],
            KitchenStation.fromJson,
          ).where((KitchenStation s) => s.isActive && s.showOnKds).toList()
          ..sort((KitchenStation a, KitchenStation b) {
            final int byOrder = a.sortOrder.compareTo(b.sortOrder);
            return byOrder != 0 ? byOrder : a.id.compareTo(b.id);
          });
    return KitchenSetup(
      stations: stations,
      serverTime: _date(data['server_time']),
    );
  }

  /// `GET /staff/orders/{uuid}`: chiptalar ro'yxatidan ketgan buyurtmaning oxirgi holati (masalan, bekor qilingan:
  /// oshxona "O'CHIRILDI" ni qisqa vaqt ko'rsatadi). Buyurtma tafsiloti chiptadagi maydonlarni ham beradi.
  Future<KitchenTicket?> orderAsTicket(String uuid) async {
    final Map<String, Object?> data = await _get(
      '/v1/staff/orders/$uuid',
      const <String, Object?>{},
    );
    try {
      return KitchenTicket.fromJson(data, receivedAt: _clock());
    } on Object {
      return null;
    }
  }

  /// `GET /owner/batches`: ochiq qozonlar, [includeClosed] bilan oxirgi yopilganlari ham.
  Future<List<KitchenBatch>> batches({
    int? branchId,
    bool includeClosed = false,
  }) async {
    final Map<String, Object?> data = await _get(
      _batchesPath,
      <String, Object?>{
        'branch_id': ?branchId,
        if (includeClosed) 'include_closed': true,
      },
    );
    return _list(data['batches'], KitchenBatch.fromJson);
  }

  /// `GET /catalog?channel=pos_hall`: qozon bo'yicha sotiladigan taomlar (`batch_tracked`).
  Future<List<BatchProduct>> batchProducts({int? branchId}) async {
    final Map<String, Object?> data = await _get(
      _catalogPath,
      <String, Object?>{'channel': 'pos_hall', 'branch_id': ?branchId},
    );
    final Object? raw = data['products'];
    if (raw is! List) return const <BatchProduct>[];
    final List<BatchProduct> out = <BatchProduct>[];
    for (final Object? p in raw) {
      if (p is! Map || p['batch_tracked'] != true) continue;
      final Object? id = p['id'];
      if (id is! num) continue;
      final Object? variants = p['variants'];
      out.add(
        BatchProduct(
          id: id.toInt(),
          name: '${p['name'] ?? ''}',
          variants: <BatchProductVariant>[
            if (variants is List)
              for (final Object? v in variants)
                if (v is Map && v['id'] is num)
                  BatchProductVariant(
                    id: (v['id']! as num).toInt(),
                    name: '${v['name'] ?? ''}',
                  ),
          ],
        ),
      );
    }
    return out;
  }

  /// `POST /owner/batches`: yangi qozon (filialda taom/variant uchun bitta ochiq qozon bo'ladi, aks holda 422).
  Future<KitchenBatch?> openBatch({
    int? branchId,
    required int productId,
    int? productVariantId,
    required num quantity,
    String? label,
  }) => _post(_batchesPath, <String, Object?>{
    'branch_id': ?branchId,
    'product_id': productId,
    'product_variant_id': ?productVariantId,
    'quantity': quantity,
    if (label != null && label.trim().isNotEmpty) 'label': label.trim(),
  });

  /// `POST /owner/batches/{id}/adjust {add}`: yana pishirildi (ishlab chiqarilgan ham, qolgan ham oshadi).
  Future<KitchenBatch?> refill(int id, num add, {String? reason}) =>
      _post('$_batchesPath/$id/adjust', <String, Object?>{
        'add': add,
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      });

  /// `POST /owner/batches/{id}/adjust {quantity_left}`: qayta sanash, qolgan miqdorni o'rnatish.
  Future<KitchenBatch?> setLeft(int id, num left, {String? reason}) =>
      _post('$_batchesPath/$id/adjust', <String, Object?>{
        'quantity_left': left,
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      });

  /// `POST /owner/batches/{id}/close`: qozonni yopish (taom keyingi qozon ochilguncha "tugadi").
  Future<KitchenBatch?> closeBatch(int id) =>
      _post('$_batchesPath/$id/close', const <String, Object?>{});

  /// Javobning `data` qismi (xarita).
  Future<Map<String, Object?>> _get(
    String path,
    Map<String, Object?> query,
  ) async {
    final Response<Object?> response;
    try {
      response = await _dio.get<Object?>(path, queryParameters: query);
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
    final Object? body = response.data;
    final Object? data = body is Map ? body['data'] : null;
    if (data is! Map) throw _unexpected(response.statusCode, body);
    return data.cast<String, Object?>();
  }

  /// O'zgartirish: HTTP xatosi [ApiException]; 2xx javobni o'qib bo'lmasa — amal bajarilgan, natija `null`.
  Future<KitchenBatch?> _post(String path, Map<String, Object?> body) async {
    final Response<Object?> response;
    try {
      response = await _dio.post<Object?>(path, data: body);
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
    final Object? raw = response.data;
    final Object? data = raw is Map ? raw['data'] : null;
    if (data is! Map || data['id'] is! num) return null;
    try {
      return KitchenBatch.fromJson(data.cast<String, Object?>());
    } on Object {
      return null;
    }
  }

  static ApiException _unexpected(int? statusCode, Object? cause) =>
      ApiException(
        message: "Server javobini o'qib bo'lmadi. Ilovani yangilang yoki administratorga ayting.",
        statusCode: statusCode,
        reason: ApiException.unexpectedResponse,
        cause: cause,
      );
}

List<T> _list<T>(Object? raw, T Function(Map<String, Object?>) parse) {
  if (raw is! List) return <T>[];
  final List<T> out = <T>[];
  for (final Object? e in raw) {
    if (e is! Map) continue;
    try {
      out.add(parse(e.cast<String, Object?>()));
    } on Object {
      // O'qib bo'lmagan yozuv tushiriladi, qolganlari ko'rsatiladi
    }
  }
  return out;
}

DateTime? _date(Object? raw) => raw is String ? DateTime.tryParse(raw) : null;
