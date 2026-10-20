/// `App\Enums\OrderType`.
enum OrderType {
  delivery('delivery', 'Yetkazib berish'),
  pickup('pickup', 'Olib ketish'),
  dineIn('dine_in', 'Zalda');

  const OrderType(this.wire, this.label);

  /// API'dagi qiymat.
  final String wire;

  /// O'zbekcha nom (xodim ekranlari uchun).
  final String label;

  /// API qiymatidan; noma'lum bo'lsa `null`.
  static OrderType? fromWire(String? value) {
    for (final OrderType t in OrderType.values) {
      if (t.wire == value) return t;
    }
    return null;
  }
}
