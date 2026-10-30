import 'package:pos_core/pos_core.dart';
import 'package:test/test.dart';

import 'support/golden_text.dart';

/// Every text of a label lies inside the label grid and rows do not overlap.
void expectInside(LabelLayout layout) {
  final Set<String> used = <String>{};
  for (final LabelText t in layout.texts) {
    expect(
      t.col + t.cells,
      lessThanOrEqualTo(layout.size.cols),
      reason: '"${t.text}" is wider than ${layout.size.wire}',
    );
    expect(
      t.row + t.rowSpan,
      lessThanOrEqualTo(layout.size.rows),
      reason: '"${t.text}" is lower than ${layout.size.wire}',
    );
    for (var r = t.row; r < t.row + t.rowSpan; r++) {
      for (var c = t.col; c < t.col + t.cells; c++) {
        expect(used.add('$r:$c'), isTrue, reason: 'overlap at row $r col $c');
      }
    }
  }
}

final DateTime printedAt = DateTime(2026, 11, 20, 12, 15);

CupSticker latte({
  String number = '57',
  List<String> modifiers = const <String>["Sut 0%", 'Vanil sirop'],
  String? customer,
  int index = 1,
  int total = 1,
  String name = 'Latte',
  String? variant = '0,4 l',
}) => CupSticker(
  number: number,
  itemName: name,
  variantName: variant,
  modifiers: modifiers,
  customerName: customer,
  printedAt: printedAt,
  index: index,
  total: total,
);

void main() {
  for (final LabelSize size in LabelSize.values) {
    final String tag = size.wire;

    group('$tag mm', () {
      void golden(String name, LabelLayout layout) {
        expectInside(layout);
        expectGoldenText(renderLabelPlainText(layout), '${name}_$tag');
      }

      test('cup sticker: number, item, size, modifiers, take-away, time', () {
        final LabelLayout l = layoutCupSticker(latte(), size);
        golden('cup_sticker', l);
        final String text = l.allText.join('\n');
        expect(text, contains('#57'));
        expect(text, contains('Latte'));
        expect(text, contains('0,4 l'));
        expect(text, contains('+ Sut 0%, Vanil sirop'));
        expect(text, contains('Olib ketish'));
        expect(text, contains('12:15'));
        final LabelText number = l.texts.firstWhere(
          (LabelText t) => t.text == '#57',
        );
        expect(number.scale, 2);
        expect(number.bold, isTrue);
      });

      test('cup sticker: customer name instead of take-away, cup 2 of 3', () {
        final LabelLayout l = layoutCupSticker(
          latte(customer: 'Dilnoza', index: 2, total: 3),
          size,
        );
        golden('cup_sticker_named', l);
        final String text = l.allText.join('\n');
        expect(text, contains('Dilnoza'));
        expect(text, isNot(contains('Olib ketish')));
        expect(text, contains('2/3'));
      });

      test('cup sticker: a long name and many modifiers are cut, not lost', () {
        final LabelLayout l = layoutCupSticker(
          latte(
            name: 'Karamel makiyato ikki karra qaymoqli',
            modifiers: const <String>[
              'Sut 0%',
              'Vanil sirop',
              'Karamel sirop',
              "Qo'shimcha espresso",
              'Doljin',
              'Muz',
              'Shokolad qirindisi',
              'Bodom siropi',
              'Qaymoq',
              'Zefir',
            ],
          ),
          size,
        );
        golden('cup_sticker_long', l);
        // the small label cuts the modifiers with ".."; the big one still has room for them
        if (size == LabelSize.mm40x30) {
          expect(l.allText.join('\n'), contains('..'));
        }
        // the take-away line is always there
        expect(l.texts.last.text, 'Olib ketish');
        expect(l.texts.last.row, size.rows - 1);
      });

      test('cup sticker without a queue number shows the time big', () {
        final LabelLayout l = layoutCupSticker(
          latte(number: '', modifiers: const <String>[]),
          size,
        );
        golden('cup_sticker_no_number', l);
        expect(l.texts.first.text, '12:15');
        expect(l.texts.first.scale, 2);
      });

      test('bag label: order number, items, customer, pickup time', () {
        final LabelLayout l = layoutBagLabel(
          BagLabel(
            number: '57',
            itemCount: 3,
            printedAt: printedAt,
            customerName: 'Dilnoza',
            pickupAt: DateTime(2026, 11, 20, 12, 40),
          ),
          size,
        );
        golden('bag_label', l);
        final String text = l.allText.join('\n');
        expect(text, contains('#57'));
        expect(text, contains('Mahsulot: 3 ta'));
        expect(text, contains('Dilnoza'));
        expect(text, contains('Olib ketish: 12:40'));
        expect(
          l.texts
              .firstWhere((LabelText t) => t.text.contains('12:40'))
              .inverted,
          isTrue,
        );
        expect(l.bars, hasLength(1));
      });

      test('bag label without a customer or pickup time', () {
        final LabelLayout l = layoutBagLabel(
          BagLabel(
            number: '#T057',
            itemCount: 1,
            printedAt: printedAt,
            orderLabel: 'Yetkazish',
          ),
          size,
        );
        golden('bag_label_plain', l);
        final String text = l.allText.join('\n');
        expect(text, contains('#T057'));
        expect(text, isNot(contains('#T057 ')));
        expect(text, contains('Yetkazish'));
        expect(text, isNot(contains('Olib ketish:')));
      });
    });
  }

  test('label sizes: dots and the grid', () {
    expect(LabelSize.mm40x30.dotsWidth, 320);
    expect(LabelSize.mm40x30.dotsHeight, 240);
    expect(LabelSize.mm40x30.cols, 25);
    expect(LabelSize.mm40x30.rows, 8);
    expect(LabelSize.mm58x40.dotsWidth, 464);
    expect(LabelSize.mm58x40.cols, 37);
    expect(LabelSize.mm58x40.rows, 10);
    expect(LabelSize.fromWire('58x40'), LabelSize.mm58x40);
    expect(LabelSize.fromWire('nope'), LabelSize.mm40x30);
    expect(LabelSize.fromWire(null), LabelSize.mm40x30);
  });
}
