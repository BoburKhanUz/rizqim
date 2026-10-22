import 'quantity.dart';

/// Ajralmas bo'shliq (U+00A0): ekranda "25 000 so'm" qatorda bo'linmaydi. Ochiq emas: `rizqim_core` ham `nbsp`
/// eksport qiladi, ilova ikkalasini birga import qiladi.
const String _nbsp = '\u00A0';

/// So'mdagi pul, **tiyingacha aniq** (backend `App\Support\Money`, `decimal(12,2)`): ichida butun son (tiyin), suzuvchi
/// nuqta yo'q. Yaxlitlash hamma joyda "half-up" (noldan uzoqqa), backend bilan bir xil.
///
/// `rizqim_core` dagi `Money` butun so'mda (Mini App formati); POS esa chek, QQS va bo'lishda tiyinni yo'qotmasligi
/// kerak, shuning uchun alohida tur.
final class PosMoney implements Comparable<PosMoney> {
  const PosMoney(this.tiyin);

  /// Butun so'mdan.
  const PosMoney.som(int som) : tiyin = som * 100;

  /// Tiyin (so'm × 100). Manfiy bo'lishi mumkin (qaytarish, chegirma, avans ofseti).
  final int tiyin;

  static const PosMoney zero = PosMoney(0);

  static final RegExp _decimal = RegExp(r'^([+-]?)(\d+)(?:\.(\d*))?$');

  /// API qiymatini (so'mda) o'qiydi: `"15000.00"`, `"12500.5"`, `25000`, `12.34` (JSON soni). Ikkidan ortiq kasr
  /// raqam half-up yaxlitlanadi (backend `Money::of`). O'qib bo'lmasa `null`.
  static PosMoney? tryParse(Object? value) {
    if (value is PosMoney) return value;
    if (value is int) return PosMoney.som(value);
    if (value is double) {
      if (!value.isFinite) return null;
      // 15 muhim raqam: ikkilik shovqinni (0.1 + 0.2) tashlaydi, decimal(12,2) qiymatni o'zgartirmaydi
      return tryParse(_shortDecimal(value));
    }
    if (value is String) {
      final RegExpMatch? m = _decimal.firstMatch(value.trim());
      if (m == null) return null;
      final bool negative = m.group(1) == '-';
      final String whole = m.group(2)!;
      final String fraction = m.group(3) ?? '';
      var magnitude = int.parse(whole) * 100;
      final String cents = fraction.padRight(2, '0').substring(0, 2);
      magnitude += int.parse(cents);
      if (fraction.length > 2 && fraction.codeUnitAt(2) >= 0x35) {
        magnitude += 1; // half-up: uchinchi raqam 5 va undan katta
      }
      return PosMoney(negative ? -magnitude : magnitude);
    }
    return null;
  }

  /// [tryParse], o'qib bo'lmasa 0.
  factory PosMoney.parse(Object? value) => tryParse(value) ?? zero;

  static String _shortDecimal(double value) {
    final String s = value.toStringAsPrecision(15);
    if (!s.contains('e')) return s;
    return value.toStringAsFixed(4);
  }

  /// Butun so'm qismi (manfiyda ham noldan tomonga: -1.50 → -1).
  int get wholeSom => tiyin ~/ 100;

  /// Kasr qismi tiyinda (0–99, ishorasiz).
  int get tiyinPart => tiyin.abs() % 100;

  bool get isZero => tiyin == 0;

  bool get isNegative => tiyin < 0;

  bool get isPositive => tiyin > 0;

  PosMoney operator +(PosMoney other) => PosMoney(tiyin + other.tiyin);

  PosMoney operator -(PosMoney other) => PosMoney(tiyin - other.tiyin);

  PosMoney operator -() => PosMoney(-tiyin);

  /// Butun songa ko'paytirish (dona soni).
  PosMoney operator *(int count) => PosMoney(tiyin * count);

  bool operator <(PosMoney other) => tiyin < other.tiyin;

  bool operator <=(PosMoney other) => tiyin <= other.tiyin;

  bool operator >(PosMoney other) => tiyin > other.tiyin;

  bool operator >=(PosMoney other) => tiyin >= other.tiyin;

  PosMoney abs() => PosMoney(tiyin.abs());

  /// Birlik narxi × miqdor (1.5 kg × 160 000), tiyingacha half-up.
  PosMoney timesQuantity(PosQuantity quantity) =>
      PosMoney(divideHalfUp(tiyin * quantity.milli, PosQuantity.scaleFactor));

  /// [rate] foiz (masalan `10`, `"12.5"`): `amount × rate / 100`, bir marta half-up yaxlitlanadi.
  PosMoney percent(Object rate) =>
      PosMoney(divideHalfUp(tiyin * percentHundredths(rate), 10000));

  /// Narx ichidagi QQS (QQS narxga kiritilgan): `amount × rate / (100 + rate)`, tiyingacha half-up.
  PosMoney vatIncluded(Object rate) {
    final int r = percentHundredths(rate);
    if (r == 0) return zero;
    return PosMoney(divideHalfUp(tiyin * r, 10000 + r));
  }

  /// Butun so'mga half-up yaxlitlangan.
  PosMoney roundToSom() => PosMoney(divideHalfUp(tiyin, 100) * 100);

  /// [parts] ta teng ulush, yig'indisi aniq shu summa: qoldiq birinchilariga bittadan taqsimlanadi. [wholeSom] bo'lsa
  /// ulushlar butun so'mda (naqd uchun qulay), tiyin qoldig'i birinchi ulushda.
  List<PosMoney> splitEqually(int parts, {bool wholeSom = true}) {
    if (parts <= 0) throw ArgumentError.value(parts, 'parts', 'must be > 0');
    final int unit = wholeSom ? 100 : 1;
    final int units = tiyin ~/ unit;
    final int rest = tiyin - units * unit;
    final int base = units ~/ parts;
    final int extra = units - base * parts;
    return List<PosMoney>.generate(parts, (int i) {
      var share = (base + (i < extra ? 1 : 0)) * unit;
      if (i == 0) share += rest;
      return PosMoney(share);
    });
  }

  static PosMoney sum(Iterable<PosMoney> values) =>
      values.fold(zero, (PosMoney a, PosMoney b) => a + b);

  /// "25 000 so'm", "25 000.50 so'm", "-1 000 so'm". Tiyin faqat bo'lsa ko'rsatiladi (yoki [alwaysTiyin]).
  /// [separator] — minglik ajratgich (ekran uchun ajralmas bo'shliq, printer uchun oddiy bo'shliq).
  String format({
    bool currency = true,
    bool alwaysTiyin = false,
    String separator = _nbsp,
  }) {
    final String number = formatAmount(
      alwaysTiyin: alwaysTiyin,
      separator: separator,
    );
    return currency ? "$number$separator$currencyLabel" : number;
  }

  /// Valyutasiz son: "25 000", "25 000.50".
  String formatAmount({bool alwaysTiyin = false, String separator = _nbsp}) {
    final String sign = tiyin < 0 ? '-' : '';
    final String whole = groupDigits(tiyin.abs() ~/ 100, separator);
    final int part = tiyinPart;
    if (part == 0 && !alwaysTiyin) return '$sign$whole';
    return '$sign$whole.${part.toString().padLeft(2, '0')}';
  }

  /// Backend'ga: `"25000.50"` (doim ikki kasr raqam).
  String toApiString() {
    final String sign = tiyin < 0 ? '-' : '';
    return '$sign${tiyin.abs() ~/ 100}.${tiyinPart.toString().padLeft(2, '0')}';
  }

  static const String currencyLabel = "so'm";

  @override
  int compareTo(PosMoney other) => tiyin.compareTo(other.tiyin);

  @override
  bool operator ==(Object other) => other is PosMoney && other.tiyin == tiyin;

  @override
  int get hashCode => tiyin.hashCode;

  @override
  String toString() => 'PosMoney(${toApiString()})';
}

/// Musbat butun sonni minglik guruhlarga ajratadi: "1 234 567".
String groupDigits(int value, [String separator = _nbsp]) {
  final String digits = value.abs().toString();
  final StringBuffer out = StringBuffer(value < 0 ? '-' : '');
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) out.write(separator);
    out.write(digits[i]);
  }
  return out.toString();
}

/// `n / d`, half-up (noldan uzoqqa) yaxlitlangan; `d > 0`.
int divideHalfUp(int n, int d) {
  if (d <= 0) throw ArgumentError.value(d, 'd', 'must be > 0');
  final int q = n.abs() ~/ d;
  final int r = n.abs() - q * d;
  final int rounded = r * 2 >= d ? q + 1 : q;
  return n < 0 ? -rounded : rounded;
}

/// Foiz stavkasini yuzdan birlarda: `12` → 1200, `"12.5"` → 1250, `"6.00"` → 600. Ikkidan ortiq kasr raqam rad
/// etiladi (stavka `decimal(5,2)`).
int percentHundredths(Object rate) {
  if (rate is int) return rate * 100;
  final String text = rate is double ? rate.toString() : '$rate'.trim();
  final RegExpMatch? m = RegExp(r'^(\d+)(?:\.(\d{0,2})0*)?$').firstMatch(text);
  if (m == null) throw FormatException('Invalid percent rate', '$rate');
  final String fraction = (m.group(2) ?? '').padRight(2, '0');
  return int.parse(m.group(1)!) * 100 + int.parse(fraction);
}

/// Stavkani chekda ko'rsatish: 1200 → "12", 1250 → "12.5".
String formatPercent(Object rate) {
  final int h = percentHundredths(rate);
  final int whole = h ~/ 100;
  final int frac = h % 100;
  if (frac == 0) return '$whole';
  final String f = frac.toString().padLeft(2, '0');
  return '$whole.${f.endsWith('0') ? f.substring(0, 1) : f}';
}
