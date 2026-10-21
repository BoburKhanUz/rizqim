/// `App\Enums\OrderType`.
enum OrderType {
  delivery('delivery', 'Yetkazib berish', 'Yetkazish'),
  pickup('pickup', 'Olib ketish', 'Olib ketish'),
  dineIn('dine_in', 'Zalda', 'Zal');

  const OrderType(this.wire, this.label, this.shortLabel);

  /// API'dagi qiymat.
  final String wire;

  /// O'zbekcha nom (xodim ekranlari uchun).
  final String label;

  /// Qisqa nom: oshxona chiptasi va doskadagi belgi ("Zal", "Olib ketish", "Yetkazish").
  final String shortLabel;

  /// API qiymatidan; noma'lum bo'lsa `null`.
  static OrderType? fromWire(String? value) {
    for (final OrderType t in OrderType.values) {
      if (t.wire == value) return t;
    }
    return null;
  }
}
