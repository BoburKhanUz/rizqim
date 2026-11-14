import 'package:pos_core/pos_core.dart';
import 'package:test/test.dart';

import 'layout_test.dart' show expectFits;
import 'support/fixtures.dart';

/// P6-54: the banquet papers: the kitchen's production sheet (dishes by station, the osh kazans and ingredients, the
/// warnings), the shopping list and the contract text, on both paper widths.
void main() {
  final DateTime printedAt = DateTime(2027, 1, 19, 9, 30);

  for (final PaperWidth paper in PaperWidth.values) {
    group('$paper', () {
      test('the production sheet', () {
        final PrintLayout l = layoutBanquetProductionSheet(
          BanquetProductionSheetDoc(
            header: header,
            title: "Aziz va Malika to'yi",
            eventDate: '20.01.2027',
            localTime: '18:00–23:00',
            halls: const <String>['Oq zal'],
            guestsBase: 200,
            guestsPlanned: 220,
            reservePercent: '10',
            printedAt: printedAt,
            stations: <(String, List<BanquetDishLine>)>[
              (
                'Osh sexi',
                <BanquetDishLine>[
                  BanquetDishLine(
                    name: "To'y oshi",
                    quantity: PosQuantity.parse('220'),
                    unit: 'portion',
                    kg: PosQuantity.parse('77'),
                  ),
                ],
              ),
              (
                'Umumiy',
                <BanquetDishLine>[
                  BanquetDishLine(
                    name: 'Achchiq-chuchuk',
                    quantity: PosQuantity.parse('44'),
                    unit: 'kg',
                  ),
                ],
              ),
            ],
            oshPersons: 220,
            oshKg: PosQuantity.parse('44'),
            kazans: const <(int, int)>[(50, 1)],
            ingredients: <BanquetIngredientLine>[
              BanquetIngredientLine(
                name: 'Guruch',
                quantity: PosQuantity.parse('44'),
                unit: 'kg',
              ),
            ],
            warnings: const <String>["Mehmonlar soni 220: 200 dan ko'p."],
          ),
          paper,
        );
        expectFits(l);
        final List<String> texts = l.texts.map((String t) => t.trim()).toList();
        expect(
          texts,
          containsAll(<String>[
            "ISHLAB CHIQARISH VARAG'I",
            "Aziz va Malika to'yi",
            '20.01.2027 18:00-23:00',
            'OSH SEXI',
            'OSH: 220 kishi, 44 kg',
            'Qozonlar: 1 x 50 kg',
          ]),
        );
        expect(
          texts.any(
            (String t) =>
                t.startsWith("To'y oshi") && t.endsWith('220 porsiya'),
          ),
          isTrue,
        );
        expect(texts.any((String t) => t.endsWith('77 kg')), isTrue);
        expect(
          texts.any(
            (String t) => t.startsWith('Guruch') && t.endsWith('44 kg'),
          ),
          isTrue,
        );
        expect(
          texts.any((String t) => t.startsWith('! Mehmonlar soni')),
          isTrue,
        );
        expect(l.lines.last, isA<PrintCut>());
      });

      test('the shopping list', () {
        final PrintLayout l = layoutBanquetShoppingList(
          BanquetShoppingListDoc(
            header: header,
            from: '20.01.2027',
            to: '22.01.2027',
            printedAt: printedAt,
            events: const <(String, String, int)>[('20.01.2027', "To'y", 220)],
            ingredients: <BanquetIngredientLine>[
              BanquetIngredientLine(
                name: "Yog'",
                quantity: PosQuantity.parse('16.5'),
                unit: 'l',
              ),
            ],
            dishes: <BanquetDishLine>[
              BanquetDishLine(
                name: 'Non',
                quantity: PosQuantity.parse('330'),
                unit: 'piece',
              ),
            ],
          ),
          paper,
        );
        expectFits(l);
        final List<String> texts = l.texts.map((String t) => t.trim()).toList();
        expect(
          texts,
          containsAll(<String>[
            "BOZORLIK RO'YXATI",
            'OSH MASALLIQLARI',
            'TAOMLAR',
            'TADBIRLAR',
          ]),
        );
        expect(
          texts.any((String t) => t.startsWith("Yog'") && t.endsWith('16.5 l')),
          isTrue,
        );
        expect(
          texts.any(
            (String t) => t.startsWith('Non') && t.endsWith('330 dona'),
          ),
          isTrue,
        );
        expect(texts.any((String t) => t.endsWith('220 kishi')), isTrue);
      });

      test('the contract keeps its lines and blank lines', () {
        final PrintLayout l = layoutBanquetContract(
          BanquetContractDoc(
            header: header,
            contractNo: '2026-0001',
            lines: const <String>[
              "TADBIR O'TKAZISH SHARTNOMASI № 2026-0001",
              '',
              "1. Tadbir: To'y. Sana: 20.01.2027, vaqti: 18:00–23:00 (Kechqurun). Zal: Oq zal. Mehmonlar: kafolatlangan 200 kishi.",
            ],
            printedAt: printedAt,
          ),
          paper,
        );
        expectFits(l);
        expect(l.title, 'Shartnoma 2026-0001');
        expect(l.lines.whereType<PrintFeed>(), isNotEmpty);
        expect(l.texts.join(' '), contains('kafolatlangan 200'));
      });

      test('X/Z: the banquet advances have their own section', () {
        final ShiftReport base = shiftReport(ShiftReportKind.x);
        final ShiftReport r = ShiftReport(
          kind: base.kind,
          header: base.header,
          shiftNumber: base.shiftNumber,
          printedAt: base.printedAt,
          openedAt: base.openedAt,
          sales: base.sales,
          receiptsCount: base.receiptsCount,
          tenders: base.tenders,
          openingCash: base.openingCash,
          cashSales: base.cashSales,
          banquets: const <ReportAmount>[
            ReportAmount("Olindi (bo'nak)", PosMoney.som(12000000), count: 2),
            ReportAmount('  naqd', PosMoney.som(5000000)),
            ReportAmount('Qaytarildi', PosMoney.som(8400000), count: 1),
          ],
        );
        final PrintLayout l = layoutShiftReport(r, paper);
        expectFits(l);
        final String t = l.texts.join('\n');
        expect(t, contains('TADBIRLAR'));
        expect(t, contains("Olindi (bo'nak) (2):"));
        expect(t, contains('Qaytarildi (1):'));
        expect(
          layoutShiftReport(base, paper).texts.join('\n'),
          isNot(contains('TADBIRLAR')),
        );
      });
    });
  }
}
