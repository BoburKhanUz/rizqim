import 'locale.dart';

/// So'mdagi pul miqdori: **butun son**, suzuvchi nuqtasiz (docs/DESIGN.md, "Formatlash").
///
/// Backend decimal qatorlarni yuboradi (`"15000.00"`); [Money.parse] ularni to'g'ri o'qiydi va butun so'mga
/// yaxlitlaydi (Mini App'dagi `Math.round` bilan bir xil: 0.5 yuqoriga). Hisob-kitob faqat butun sonlarda.
final class Money implements Comparable<Money> {
  const Money(this.amount);

  /// Butun so'm. Manfiy bo'lishi mumkin (qaytarish, chegirma).
  final int amount;

  static const Money zero = Money(0);

  static final RegExp _decimal = RegExp(r'^([+-]?)(\d+)(?:\.(\d*))?$');

  /// [value] ni o'qiydi: `int`, `double` (yoki `num`), `"25000"`, `"15000.00"`, `" 12000.5 "`. O'qib bo'lmasa
  /// `null` (bo'sh qator, `NaN`, cheksizlik, harflar, `null`).
  static Money? tryParse(Object? value) {
    if (value is int) return Money(value);
    if (value is num) {
      if (!value.isFinite) return null;
      return Money((value + 0.5).floor());
    }
    if (value is String) {
      final RegExpMatch? m = _decimal.firstMatch(value.trim());
      if (m == null) return null;
      final bool negative = m.group(1) == '-';
      final int whole = int.parse(m.group(2)!);
      final String fraction = m.group(3) ?? '';
      var magnitude = whole;
      if (fraction.isNotEmpty) {
        final int first = fraction.codeUnitAt(0) - 0x30;
        final bool moreDigits = fraction.substring(1).contains(RegExp('[1-9]'));
        // Math.round: 0.5 yuqoriga (musbatda kattalik oshadi, manfiyda -2.5 -> -2)
        final bool roundUp = negative
            ? (first > 5 || (first == 5 && moreDigits))
            : first >= 5;
        if (roundUp) magnitude += 1;
      }
      return Money(negative ? -magnitude : magnitude);
    }
    return null;
  }

  /// [tryParse], o'qib bo'lmasa 0 (ekranda "NaN so'm" chiqmasligi uchun, Mini App'dagi `toAmount` kabi).
  factory Money.parse(Object? value) => tryParse(value) ?? zero;

  Money operator +(Money other) => Money(amount + other.amount);

  Money operator -(Money other) => Money(amount - other.amount);

  Money operator -() => Money(-amount);

  /// Miqdorga ko'paytirish (dona soni).
  Money operator *(int quantity) => Money(amount * quantity);

  bool operator <(Money other) => amount < other.amount;

  bool operator <=(Money other) => amount <= other.amount;

  bool operator >(Money other) => amount > other.amount;

  bool operator >=(Money other) => amount >= other.amount;

  bool get isZero => amount == 0;

  bool get isNegative => amount < 0;

  /// Barcha miqdorlar yig'indisi.
  static Money sum(Iterable<Money> values) =>
      values.fold(zero, (Money a, Money b) => a + b);

  /// "25 000 so'm" (uz), "25 000 сум" (ru), "25,000 UZS" (en). Bo'shliqlar ajralmas.
  String format([RzLocale locale = RzLocale.uz]) =>
      '${formatNumber(amount, locale)}$nbsp${_currency[locale]}';

  /// Backend'ga yuboriladigan qiymat: butun so'm, kasr qismisiz.
  String toApiString() => amount.toString();

  @override
  int compareTo(Money other) => amount.compareTo(other.amount);

  @override
  bool operator ==(Object other) => other is Money && other.amount == amount;

  @override
  int get hashCode => amount.hashCode;

  @override
  String toString() => 'Money($amount)';

  static const Map<RzLocale, String> _currency = <RzLocale, String>{
    RzLocale.uz: "so'm",
    RzLocale.ru: 'сум',
    RzLocale.en: 'UZS',
  };
}

/// Butun son minglik ajratgichlar bilan: "1 234 567" (uz, ru) yoki "1,234,567" (en). Bo'shliq ajralmas.
String formatNumber(Object? value, [RzLocale locale = RzLocale.uz]) {
  final int rounded = value is Money ? value.amount : Money.parse(value).amount;
  final String separator = locale == RzLocale.en ? ',' : nbsp;
  final String digits = rounded.abs().toString();
  final StringBuffer out = StringBuffer(rounded < 0 ? '-' : '');
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) out.write(separator);
    out.write(digits[i]);
  }
  return out.toString();
}
