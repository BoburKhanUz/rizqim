import 'package:pos_core/pos_core.dart';
import 'package:test/test.dart';

String plain(String text) => text.replaceAll('\u00A0', ' ');

void main() {
  group('PosMoney', () {
    test('reads API decimals exactly, to the tiyin, rounding half-up', () {
      expect(PosMoney.parse('15000.00').tiyin, 1500000);
      expect(PosMoney.parse('12500.5').tiyin, 1250050);
      expect(PosMoney.parse(' 0.07 ').tiyin, 7);
      expect(PosMoney.parse('10.005').tiyin, 1001); // half-up, like Money::of
      expect(PosMoney.parse('10.004').tiyin, 1000);
      expect(PosMoney.parse('-10.005').tiyin, -1001);
      expect(PosMoney.parse(25000).tiyin, 2500000);
      expect(PosMoney.parse(0.1 + 0.2).tiyin, 30); // no binary noise
      expect(PosMoney.tryParse('abc'), isNull);
      expect(PosMoney.tryParse(''), isNull);
      expect(PosMoney.tryParse(double.nan), isNull);
      expect(PosMoney.parse(null), PosMoney.zero);
    });

    test(
      'formats in Uzbek with non-breaking spaces; tiyin only if present',
      () {
        expect(plain(const PosMoney.som(25000).format()), "25 000 so'm");
        expect(
          plain(PosMoney.parse('1234567.5').format()),
          "1 234 567.50 so'm",
        );
        expect(plain(const PosMoney.som(-1000).format()), "-1 000 so'm");
        expect(const PosMoney.som(25000).format().contains(' '), isFalse);
        expect(
          const PosMoney.som(450000).formatAmount(separator: ' '),
          '450 000',
        );
        expect(const PosMoney.som(5).formatAmount(alwaysTiyin: true), '5.00');
        expect(PosMoney.parse('-0.5').toApiString(), '-0.50');
        expect(const PosMoney.som(15000).toApiString(), '15000.00');
      },
    );

    test('price × quantity rounds half-up to the tiyin', () {
      const PosMoney perKg = PosMoney.som(160000);
      expect(
        perKg.timesQuantity(PosQuantity.parse('2.5')),
        const PosMoney.som(400000),
      );
      expect(
        const PosMoney.som(9999)
            .timesQuantity(PosQuantity.parse('0.333'))
            .tiyin,
        332967, // 3329.667 so'm
      );
      expect(
        const PosMoney(1).timesQuantity(PosQuantity.parse('0.5')).tiyin,
        1,
      );
    });

    test('VAT included in the price: amount × rate / (100 + rate)', () {
      // research example: 4 480 000 with 12% VAT inside → 480 000
      expect(
        const PosMoney.som(4480000).vatIncluded('12'),
        const PosMoney.som(480000),
      );
      expect(
        const PosMoney.som(10000).vatIncluded(12).tiyin,
        107143,
      ); // 1071.428…
      expect(
        const PosMoney.som(10600).vatIncluded('6.00'),
        const PosMoney.som(600),
      );
      expect(const PosMoney.som(10000).vatIncluded('0'), PosMoney.zero);
    });

    test('percent rounds once; service charge 10%', () {
      expect(
        const PosMoney.som(3800000).percent(10),
        const PosMoney.som(380000),
      );
      expect(const PosMoney(333).percent('12.5').tiyin, 42); // 41.625
      expect(() => const PosMoney(1).percent('1.234'), throwsFormatException);
    });

    test('splits equally in whole so\'m and keeps the exact sum', () {
      final List<PosMoney> shares = const PosMoney.som(3480000).splitEqually(7);
      expect(PosMoney.sum(shares), const PosMoney.som(3480000));
      expect(shares.first, const PosMoney.som(497143));
      expect(shares.last, const PosMoney.som(497142));
      final List<PosMoney> withTiyin = PosMoney.parse('100.50').splitEqually(3);
      expect(withTiyin.map((PosMoney m) => m.tiyin), <int>[3450, 3300, 3300]);
      expect(() => PosMoney.zero.splitEqually(0), throwsArgumentError);
    });

    test('rate formatting', () {
      expect(formatPercent('12.00'), '12');
      expect(formatPercent('12.5'), '12.5');
      expect(formatPercent(6), '6');
      expect(formatPercent('7.25'), '7.25');
    });
  });

  group('PosQuantity', () {
    test('parses up to three decimals, refuses more (never rounds)', () {
      expect(PosQuantity.parse('1.500').milli, 1500);
      expect(PosQuantity.parse('0,75').milli, 750);
      expect(PosQuantity.parse(2).milli, 2000);
      expect(PosQuantity.parse(0.7).milli, 700);
      expect(PosQuantity.tryParse('0.1234'), isNull);
      expect(PosQuantity.tryParse('0.1230')?.milli, 123);
      expect(PosQuantity.tryParse('x'), isNull);
    });

    test('formats like the backend: no trailing zeros, unit for weights', () {
      expect(PosQuantity.parse('1.500').format(), '1.5');
      expect(PosQuantity.parse('0.750').format(), '0.75');
      expect(const PosQuantity.whole(2).format(), '2');
      expect(PosQuantity.parse('1.5').toApiString(), '1.500');
      expect(PosQuantity.parse('2.5').label(PosUnit.kg), '2.5 kg');
      expect(
        PosQuantity.parse('3').label(PosUnit.kg, unitLabel: 'kg (guruch)'),
        '3 kg (guruch)',
      );
      expect(const PosQuantity.whole(4).label(PosUnit.piece), '4');
      expect(PosUnit.fromWire('l'), PosUnit.litre);
      expect(PosUnit.fromWire(null), PosUnit.piece);
    });

    test('presets are the batch shares of the kazan', () {
      expect(PosQuantity.presets.map((PosQuantity q) => q.format()), <String>[
        '0.5',
        '0.7',
        '0.75',
        '1',
        '2',
      ]);
    });

    test('step check', () {
      expect(
        PosQuantity.parse('0.3').isMultipleOf(PosQuantity.parse('0.1')),
        isTrue,
      );
      expect(
        PosQuantity.parse('0.25').isMultipleOf(PosQuantity.parse('0.1')),
        isFalse,
      );
      expect(PosQuantity.parse('0.25').isMultipleOf(PosQuantity.zero), isTrue);
    });
  });

  group('formatting helpers', () {
    test('receipt dates and open duration', () {
      final DateTime d = DateTime(2026, 10, 21, 9, 5);
      expect(formatReceiptDateTime(d), '21.10.2026 09:05');
      expect(formatOpenDuration(const Duration(hours: 5, minutes: 30)), '5:30');
      expect(formatOpenDuration(const Duration(minutes: 45)), '0:45');
      expect(
        formatOpenDuration(const Duration(hours: 26, minutes: 5)),
        '26:05',
      );
      expect(formatOpenDuration(const Duration(minutes: -3)), '0:00');
    });

    test('payment form from tenders', () {
      expect(
        FiscalPaymentForm.of(<ReceiptPayment>[
          const ReceiptPayment(PosTender.prepayment, PosMoney.som(10)),
          const ReceiptPayment(PosTender.cash, PosMoney.som(10)),
        ]),
        FiscalPaymentForm.cash,
      );
      expect(
        FiscalPaymentForm.of(<ReceiptPayment>[
          const ReceiptPayment(PosTender.card, PosMoney.som(10)),
          const ReceiptPayment(PosTender.cash, PosMoney.som(10)),
        ]),
        FiscalPaymentForm.mixed,
      );
      expect(
        FiscalPaymentForm.of(<ReceiptPayment>[
          const ReceiptPayment(PosTender.prepayment, PosMoney.som(10)),
        ]),
        isNull,
      );
    });
  });

  group('wrapText', () {
    test('wraps on words and hard-splits long words', () {
      expect(wrapText('Osh devzira sergo\'sht', 10), <String>[
        'Osh',
        'devzira',
        "sergo'sht",
      ]);
      expect(wrapText('abcdefghijkl', 5), <String>['abcde', 'fghij', 'kl']);
      expect(wrapText('', 5), <String>['']);
      // Uzbek modifier letters count as one character each
      expect(textWidth('oʻgʻil'), 6);
    });
  });
}
