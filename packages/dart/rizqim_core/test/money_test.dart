import 'package:rizqim_core/rizqim_core.dart';
import 'package:test/test.dart';

// Mirrors customer/miniapp/src/lib/format.test.ts: the same inputs must give the same text in the Mini App and here.
String plain(String text) => text.replaceAll(nbsp, ' ');

void main() {
  group('Money.format', () {
    test('groups thousands and adds the currency of each language', () {
      expect(plain(const Money(25000).format()), "25 000 so'm");
      expect(plain(const Money(25000).format(RzLocale.ru)), '25 000 сум');
      expect(plain(const Money(25000).format(RzLocale.en)), '25,000 UZS');
      expect(plain(const Money(1234567).format()), "1 234 567 so'm");
      expect(plain(Money.zero.format()), "0 so'm");
      expect(plain(const Money(999).format()), "999 so'm");
    });

    test('never breaks a value across lines', () {
      expect(const Money(25000).format(), "25 000 so'm");
      expect(const Money(25000).format(RzLocale.en), '25,000 UZS');
      expect(const Money(25000).format().contains(' '), isFalse);
    });

    test('keeps the sign of a negative amount', () {
      expect(plain(const Money(-100000).format()), "-100 000 so'm");
    });

    test('formats the examples of docs/DESIGN.md', () {
      expect(plain(const Money(25000).format()), "25 000 so'm");
    });
  });

  group('Money.parse', () {
    test("reads the API's decimal strings and rounds to whole so'm", () {
      expect(plain(Money.parse('25000.00').format()), "25 000 so'm");
      expect(plain(Money.parse('12000.5').format()), "12 001 so'm");
      expect(plain(Money.parse(99.4).format()), "99 so'm");
      expect(Money.parse(' 15000 ').amount, 15000);
      expect(Money.parse('0.49').amount, 0);
      expect(Money.parse('0.50').amount, 1);
    });

    test('rounds half up like Math.round', () {
      expect(Money.parse('-2.5').amount, -2);
      expect(Money.parse('-2.51').amount, -3);
      expect(Money.parse('-2.4').amount, -2);
      expect(Money.parse(-2.5).amount, -2);
      expect(Money.parse(2.5).amount, 3);
    });

    test('shows a missing or non-numeric amount as zero, never NaN', () {
      for (final Object? v in <Object?>[
        null,
        '',
        '  ',
        'abc',
        double.nan,
        double.infinity,
        '1e5',
        '1,5',
      ]) {
        expect(Money.parse(v), Money.zero, reason: '$v');
        expect(Money.tryParse(v), isNull, reason: '$v');
        expect(plain(Money.parse(v).format()), "0 so'm");
      }
    });

    test('big amounts are exact (no floating point)', () {
      expect(Money.parse('9007199254740993').amount, 9007199254740993);
      expect(plain(const Money(1000000000).format()), "1 000 000 000 so'm");
    });
  });

  group('Money arithmetic', () {
    test('is integer math', () {
      expect(const Money(12500) + const Money(500), const Money(13000));
      expect(const Money(12500) - const Money(20000), const Money(-7500));
      expect(const Money(12500) * 3, const Money(37500));
      expect(-const Money(100), const Money(-100));
      expect(
        Money.sum(const <Money>[Money(1), Money(2), Money(3)]),
        const Money(6),
      );
      expect(Money.sum(const <Money>[]), Money.zero);
    });

    test('compares', () {
      expect(const Money(1) < const Money(2), isTrue);
      expect(const Money(2) >= const Money(2), isTrue);
      expect(<Money>[const Money(3), const Money(1)]..sort(), const <Money>[
        Money(1),
        Money(3),
      ]);
      expect(const Money(5).hashCode, const Money(5).hashCode);
    });

    test('toApiString is a whole number', () {
      expect(const Money(25000).toApiString(), '25000');
    });
  });

  group('formatNumber', () {
    test('groups digits like the money it goes with', () {
      expect(plain(formatNumber(5000)), '5 000');
      expect(formatNumber(5000, RzLocale.en), '5,000');
      expect(formatNumber(null), '0');
      expect(formatNumber('1234567.4'), '1 234 567');
    });
  });
}
