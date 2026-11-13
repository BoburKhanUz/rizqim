import 'package:pos_core/pos_core.dart';
import 'package:test/test.dart';

import 'layout_test.dart' show expectFits;
import 'support/fixtures.dart';

/// P6-44: a corporate member's talon: the store, the company, the member, the code as a QR and in writing, the limits.
void main() {
  for (final PaperWidth paper in PaperWidth.values) {
    group('$paper', () {
      test('a talon with a daily limit and meals per day', () {
        final PrintLayout l = layoutCorporateTalon(
          CorporateTalon(
            header: header,
            company: 'Navoiy zavodi',
            memberName: 'Aziz Rahimov',
            code: '5550001111',
            printedAt: DateTime(2026, 11, 20, 9, 30),
            dailyLimit: const PosMoney.som(60000),
            mealsPerDay: 1,
            validNote: 'Noyabr oyi uchun',
          ),
          paper,
        );
        expectFits(l);
        final List<String> texts = l.texts.map((String t) => t.trim()).toList();
        expect(
          texts,
          containsAll(<String>[
            'KORXONA TALONI',
            'Navoiy zavodi',
            'Aziz Rahimov',
            '5550001111',
            'Noyabr oyi uchun',
          ]),
        );
        expect(l.lines.whereType<PrintQr>().single.data, '5550001111');
        expect(
          texts.any(
            (String t) => t.startsWith('Kunlik limit:') && t.endsWith('60 000'),
          ),
          isTrue,
        );
        expect(
          texts.any(
            (String t) =>
                t.startsWith('Kuniga ovqat:') && t.endsWith('1 marta'),
          ),
          isTrue,
        );
        expect(l.lines.last, isA<PrintCut>());
        expect(l.title, 'Talon 5550001111');
      });

      test('without limits', () {
        final PrintLayout l = layoutCorporateTalon(
          CorporateTalon(
            header: header,
            company: 'Zavod',
            memberName: 'Bobur',
            code: 'NAV-0002',
            printedAt: DateTime(2026, 11, 20),
          ),
          paper,
        );
        final List<String> texts = l.texts.map((String t) => t.trim()).toList();
        expect(
          texts.any(
            (String t) =>
                t.startsWith('Kunlik limit:') && t.endsWith('cheksiz'),
          ),
          isTrue,
        );
        expect(texts.any((String t) => t.startsWith('Kuniga ovqat:')), isFalse);
      });
    });
  }
}
