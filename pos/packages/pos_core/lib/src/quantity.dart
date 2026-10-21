/// Variant qanday sotiladi (backend `App\Enums\MenuUnit`): dona yoki vazn/hajm (kg, l). Dona doim butun.
enum PosUnit {
  piece('piece', 'dona'),
  kg('kg', 'kg'),
  litre('l', 'l');

  const PosUnit(this.wire, this.label);

  /// API'dagi qiymat (`product_variants.unit`).
  final String wire;

  /// Qisqa yorliq: "dona", "kg", "l".
  final String label;

  /// Kasr miqdor bo'la oladimi (0.5 kg). Dona — yo'q.
  bool get isMeasured => this != PosUnit.piece;

  /// Noma'lum yoki bo'sh qiymat — dona.
  static PosUnit fromWire(String? value) {
    for (final PosUnit unit in values) {
      if (unit.wire == value) return unit;
    }
    return PosUnit.piece;
  }
}

/// Miqdor, uch kasr raqamgacha aniq (backend `App\Support\Quantity`, `decimal(12,3)`): ichida butun son (mingdan bir).
/// Dona (2), vazn (1.5 kg) yoki ulush (0.75 porsiya) — hammasi shu tur.
final class PosQuantity implements Comparable<PosQuantity> {
  const PosQuantity.milli(this.milli);

  /// Butun miqdor (2 dona).
  const PosQuantity.whole(int count) : milli = count * scaleFactor;

  /// Mingdan bir birlik (1.5 → 1500).
  final int milli;

  static const int scale = 3;
  static const int scaleFactor = 1000;

  static const PosQuantity zero = PosQuantity.milli(0);
  static const PosQuantity one = PosQuantity.whole(1);

  /// Kg va ulush klaviaturasining tez tugmalari: 0.5, 0.7, 0.75, 1, 2 (qozondan ulush, `batch_consumption`).
  static const List<PosQuantity> presets = <PosQuantity>[
    PosQuantity.milli(500),
    PosQuantity.milli(700),
    PosQuantity.milli(750),
    PosQuantity.whole(1),
    PosQuantity.whole(2),
  ];

  static final RegExp _decimal = RegExp(r'^(-?)(\d+)(?:[.,](\d*))?$');

  /// `"1.500"`, `"0,75"`, `2`, `1.5`. Uchdan ortiq kasr raqam — `null` (miqdor yaxlitlanmaydi: bu mijoz so'ragan
  /// narsa, backend ham rad etadi). Bo'sh yoki noto'g'ri — `null`.
  static PosQuantity? tryParse(Object? value) {
    if (value is PosQuantity) return value;
    if (value is int) return PosQuantity.whole(value);
    if (value is double) {
      if (!value.isFinite) return null;
      return tryParse(value.toString());
    }
    if (value is! String) return null;
    final RegExpMatch? m = _decimal.firstMatch(value.trim());
    if (m == null) return null;
    final String fraction = (m.group(3) ?? '').replaceFirst(RegExp(r'0+$'), '');
    if (fraction.length > scale) return null;
    final int milli =
        int.parse(m.group(2)!) * scaleFactor +
        int.parse(fraction.padRight(scale, '0'));
    return PosQuantity.milli(m.group(1) == '-' ? -milli : milli);
  }

  /// [tryParse], o'qib bo'lmasa 0.
  factory PosQuantity.parse(Object? value) => tryParse(value) ?? zero;

  bool get isZero => milli == 0;

  bool get isPositive => milli > 0;

  bool get isWhole => milli % scaleFactor == 0;

  /// Butun qismi (dona soni).
  int get wholePart => milli ~/ scaleFactor;

  PosQuantity operator +(PosQuantity other) =>
      PosQuantity.milli(milli + other.milli);

  PosQuantity operator -(PosQuantity other) =>
      PosQuantity.milli(milli - other.milli);

  bool operator <(PosQuantity other) => milli < other.milli;

  bool operator <=(PosQuantity other) => milli <= other.milli;

  bool operator >(PosQuantity other) => milli > other.milli;

  bool operator >=(PosQuantity other) => milli >= other.milli;

  /// [step] ning butun karralimi (0.3 kg, qadam 0.1: ha; 0.25: yo'q). Nol qadam hammasini qabul qiladi.
  bool isMultipleOf(PosQuantity step) =>
      step.milli <= 0 || milli % step.milli == 0;

  /// "2", "1.5", "0.75" (keraksiz nollarsiz, backend `Quantity::format`).
  String format() {
    final String sign = milli < 0 ? '-' : '';
    final int abs = milli.abs();
    final int whole = abs ~/ scaleFactor;
    final int frac = abs % scaleFactor;
    if (frac == 0) return '$sign$whole';
    final String f = frac
        .toString()
        .padLeft(scale, '0')
        .replaceFirst(RegExp(r'0+$'), '');
    return '$sign$whole.$f';
  }

  /// Backend'ga: `"1.500"`.
  String toApiString() {
    final String sign = milli < 0 ? '-' : '';
    final int abs = milli.abs();
    return '$sign${abs ~/ scaleFactor}.${(abs % scaleFactor).toString().padLeft(scale, '0')}';
  }

  /// Ekran, marka va chek uchun (backend `OrderItem::quantityLabel` bilan bir xil): vazn — "1.5 kg" (yoki
  /// [unitLabel], masalan "kg (guruch)"), dona — "2".
  String label(PosUnit unit, {String? unitLabel}) {
    if (!unit.isMeasured) return format();
    final String u = (unitLabel == null || unitLabel.trim().isEmpty)
        ? unit.label
        : unitLabel.trim();
    return '${format()} $u';
  }

  @override
  int compareTo(PosQuantity other) => milli.compareTo(other.milli);

  @override
  bool operator ==(Object other) =>
      other is PosQuantity && other.milli == milli;

  @override
  int get hashCode => milli.hashCode;

  @override
  String toString() => 'PosQuantity(${format()})';
}
