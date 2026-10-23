import 'package:pos_data/pos_data.dart';

/// "Tez-tez" paneli: shu kassada qaysi taom ko'p qo'shilgani (qurilma xotirasida, smena davomida). Backend'da
/// mashhur taomlar ro'yxati yo'q — kassirning o'z odatidan.
class FrequentItems {
  FrequentItems._();

  static final Expando<FrequentItems> _byData = Expando<FrequentItems>();

  /// Bitta [PosData] (bitta terminal) uchun bitta ro'yxat.
  static FrequentItems of(PosData data) => _byData[data] ??= FrequentItems._();

  final Map<int, int> _counts = <int, int>{};

  void record(int productId) =>
      _counts[productId] = (_counts[productId] ?? 0) + 1;

  /// Eng ko'p qo'shilgan [limit] ta taom (menyuda bor va sotiladigan). Tarix bo'lmasa [fallback] dagi taomlar
  /// (masalan, joriy buyurtmadagilar).
  List<PosProduct> top(
    PosCatalog catalog, {
    int limit = 6,
    Iterable<int> fallback = const <int>[],
  }) {
    final List<int> ids =
        (_counts.entries.toList()
              ..sort((MapEntry<int, int> a, MapEntry<int, int> b) {
                final int byCount = b.value.compareTo(a.value);
                return byCount != 0 ? byCount : a.key.compareTo(b.key);
              }))
            .map((MapEntry<int, int> e) => e.key)
            .toList();
    for (final int id in fallback) {
      if (!ids.contains(id)) ids.add(id);
    }
    final List<PosProduct> out = <PosProduct>[];
    for (final int id in ids) {
      final PosProduct? p = catalog.product(id);
      if (p != null) out.add(p);
      if (out.length >= limit) break;
    }
    return out;
  }
}
