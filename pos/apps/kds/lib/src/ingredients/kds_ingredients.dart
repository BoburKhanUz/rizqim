import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// Oshxona ingredienti ("Tovuq"): nomi, nechta taom unga bog'liq va shu filialda to'xtatilganmi.
class KdsIngredient {
  const KdsIngredient({
    required this.id,
    required this.name,
    this.dishes = 0,
    this.stopped = false,
    this.reason,
    this.until,
  });

  factory KdsIngredient.fromJson(Map<String, Object?> j) {
    final Object? links = j['links'];
    final Object? stopList = j['stopped'];
    final bool here = j['stopped_here'] == true;
    Map<Object?, Object?>? entry;
    if (here && stopList is List) {
      entry = stopList.whereType<Map<Object?, Object?>>().firstOrNull;
    }
    return KdsIngredient(
      id: (j['id'] as num).toInt(),
      name: '${j['name'] ?? ''}',
      dishes: links is List
          ? links
                .whereType<Map<Object?, Object?>>()
                .where((Map<Object?, Object?> l) => l['is_critical'] != false)
                .length
          : 0,
      stopped: here,
      reason: entry?['reason'] is String ? entry!['reason']! as String : null,
      until: entry == null
          ? null
          : DateTime.tryParse('${entry['until'] ?? ''}')?.toLocal(),
    );
  }

  final int id;
  final String name;

  /// To'xtatilsa yopiladigan taomlar soni.
  final int dishes;

  /// Shu filialda to'xtatilgan.
  final bool stopped;
  final String? reason;
  final DateTime? until;
}

/// Oshxonaning "Ingredient tugadi" tez to'xtatishi (P6-55 / B9): `GET /owner/ingredients` (xodim PIN sessiyasi bilan),
/// to'xtatish `POST …/{id}/stop`, qayta ochish `DELETE …/{id}/stop`. Ruxsat (`stoplist.manage`) yo'q yoki modul
/// (`ingredient_stoplist`) o'chiq bo'lsa [available] `false` va tugma ko'rinmaydi. Aloqa uzilsa oxirgi holat qoladi.
/// Server so'rovi (xodim PIN sessiyasi bilan): javobning to'liq tanasi. Xatoda tashlaydi (Dio yoki [ApiException]).
typedef KdsIngredientRequest = Future<Object?> Function(
  String method,
  String path, {
  Map<String, Object?>? query,
  Object? body,
});

class KdsIngredientsController extends ChangeNotifier {
  KdsIngredientsController(
    this._request, {
    this.branchId,
    this.every = const Duration(seconds: 60),
  });

  final KdsIngredientRequest _request;
  final int? branchId;
  final Duration every;

  List<KdsIngredient> _all = const <KdsIngredient>[];
  bool _available = false;
  bool _busy = false;
  Timer? _timer;
  bool _disposed = false;

  /// Ruxsat va modul bor: tugma va yuqori lenta ko'rinadi.
  bool get available => _available;
  bool get busy => _busy;
  List<KdsIngredient> get ingredients => _all;

  /// Hozir to'xtatilganlar.
  List<KdsIngredient> get stopped =>
      _all.where((KdsIngredient i) => i.stopped).toList();

  void start() {
    unawaited(refresh());
    _timer ??= Timer.periodic(every, (_) => unawaited(refresh()));
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
  }

  Future<void> refresh() async {
    try {
      final Object? body = await _request(
        'GET',
        '/v1/owner/ingredients',
        query: <String, Object?>{'branch_id': ?branchId},
      );
      if (_disposed) return;
      final Object? data = body is Map ? body['data'] : null;
      final Object? list = data is Map ? data['ingredients'] : null;
      _all = <KdsIngredient>[
        if (list is List)
          for (final Object? e in list)
            if (e is Map) KdsIngredient.fromJson(e.cast<String, Object?>()),
      ];
      _available = true;
    } on Object catch (e) {
      if (_disposed) return;
      final int? code = ApiException.from(e).statusCode;
      // 403 (ruxsat yoki modul yo'q) va 404 (eski server): tugma yashirin; aloqa yo'q bo'lsa oxirgi holat qoladi
      if (code == 403 || code == 404) {
        _available = false;
        _all = const <KdsIngredient>[];
      } else {
        return;
      }
    }
    notifyListeners();
  }

  /// "X tugadi": filialdagi bog'langan hamma taom (hamma brend va kanalda) yopiladi. Xatoda [ApiException].
  Future<void> stopIngredient(
    KdsIngredient i, {
    String? reason,
    DateTime? until,
  }) => _mutate(
    () => _request(
      'POST',
      '/v1/owner/ingredients/${i.id}/stop',
      body: <String, Object?>{
        'branch_id': ?branchId,
        if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
        if (until != null) 'until': until.toUtc().toIso8601String(),
      },
    ),
  );

  /// Qayta ochish (o'z yozuvi bilan to'xtatilgan taom yopiq qoladi).
  Future<void> reopen(KdsIngredient i) => _mutate(
    () => _request(
      'DELETE',
      '/v1/owner/ingredients/${i.id}/stop',
      query: <String, Object?>{'branch_id': ?branchId},
    ),
  );

  Future<void> _mutate(Future<Object?> Function() call) async {
    _busy = true;
    notifyListeners();
    try {
      await call();
    } on Object catch (e) {
      throw ApiException.from(e);
    } finally {
      _busy = false;
      if (!_disposed) notifyListeners();
    }
    await refresh();
  }

  @override
  void dispose() {
    _disposed = true;
    _timer?.cancel();
    _timer = null;
    super.dispose();
  }
}
