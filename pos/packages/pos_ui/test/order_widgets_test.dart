import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/scenes.dart';

const PosModifierGroupSpec meat = PosModifierGroupSpec(
  id: 'g1',
  name: "Go'sht",
  required: true,
  max: 1,
  options: <PosModifierOptionSpec>[
    PosModifierOptionSpec(id: 'lamb', name: "Qo'y"),
    PosModifierOptionSpec(
      id: 'beef',
      name: 'Mol',
      priceDelta: PosMoney.som(3000),
    ),
  ],
);

const PosModifierGroupSpec extras = PosModifierGroupSpec(
  id: 'g2',
  name: "Qo'shimcha",
  max: 3,
  options: <PosModifierOptionSpec>[
    PosModifierOptionSpec(
      id: 'qazi',
      name: 'Qazi',
      priceDelta: PosMoney.som(15000),
      maxQuantity: 3,
    ),
    PosModifierOptionSpec(id: 'egg', name: 'Bedana tuxumi', available: false),
  ],
);

void main() {
  test('group rules: labels, validation and price', () {
    expect(meat.minimum, 1);
    expect(meat.isSingleChoice, isTrue);
    expect(meat.ruleLabel, 'Majburiy · 1 ta tanlang');
    expect(extras.ruleLabel, "Ixtiyoriy · ko'pi bilan 3 ta");
    expect(
      const PosModifierGroupSpec(
        id: 'x',
        name: 'x',
        min: 2,
        options: <PosModifierOptionSpec>[],
      ).ruleLabel,
      'Majburiy · kamida 2 ta',
    );

    final List<PosModifierGroupSpec> groups = <PosModifierGroupSpec>[
      meat,
      extras,
    ];
    expect(
      validateModifierSelection(groups, const <String, int>{}),
      <String, String>{'g1': "«Go'sht» dan kamida 1 ta tanlang"},
    );
    expect(
      validateModifierSelection(groups, const <String, int>{
        'beef': 1,
        'qazi': 4,
      }),
      <String, String>{'g2': "«Qo'shimcha» dan ko'pi bilan 3 ta"},
    );
    expect(
      modifierSelectionPrice(groups, const <String, int>{'beef': 1, 'qazi': 2}),
      const PosMoney.som(33000),
    );
  });

  testWidgets(
    'modifier dialog: required group blocks confirm, single choice swaps, stepper counts',
    (WidgetTester tester) async {
      setScreen(tester, const Size(900, 700));
      Map<String, int>? result;
      await tester.pumpWidget(
        host(
          Builder(
            builder: (BuildContext context) => TextButton(
              onPressed: () async => result = await showPosModifierDialog(
                context,
                title: 'Shashlik',
                groups: const <PosModifierGroupSpec>[meat, extras],
                basePrice: const PosMoney.som(18000),
              ),
              child: const Text('open'),
            ),
          ),
        ),
      );
      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();
      expect(find.text('Majburiy · 1 ta tanlang'), findsOneWidget);

      // Majburiy tanlanmagan: tasdiq xatoni ko'rsatadi
      await tester.tap(find.textContaining("Qo'shish ·"));
      await tester.pumpAndSettle();
      expect(find.text("«Go'sht» dan kamida 1 ta tanlang"), findsOneWidget);
      expect(result, isNull);

      await tester.tap(find.bySemanticsLabel("Qo'y"));
      await tester.pump();
      await tester.tap(find.bySemanticsLabel(RegExp('^Mol')));
      await tester.pump();
      await tester.tap(find.byTooltip("Qo'shish: Qazi"));
      await tester.pump();
      await tester.tap(find.byTooltip("Qo'shish: Qazi"));
      await tester.pump();
      // 18 000 + 3 000 + 2 × 15 000
      expect(
        find.text("Qo'shish · ${const PosMoney.som(51000).format()}"),
        findsOneWidget,
      );
      // Tugagan qo'shimchani tanlab bo'lmaydi
      expect(find.text('Tugadi'), findsOneWidget);
      await tester.tap(find.textContaining("Qo'shish ·"));
      await tester.pumpAndSettle();
      expect(result, <String, int>{'beef': 1, 'qazi': 2});
    },
  );

  testWidgets('line tile, tone chip and totals panel', (
    WidgetTester tester,
  ) async {
    setScreen(tester, const Size(600, 900));
    var waived = 0;
    await tester.pumpWidget(
      host(
        Column(
          children: <Widget>[
            const PosOrderLineTile(
              quantityLabel: '1.5 kg (guruch)',
              name: 'Osh',
              modifiers: <String>['Qazi'],
              note: 'kamroq yog',
              seat: 2,
              amount: PosMoney.som(180000),
              status: PosToneChip(label: 'Oshxonada', tone: PosChipTone.info),
            ),
            const PosOrderLineTile(
              quantityLabel: '2',
              name: 'Choy',
              voided: true,
              voidReason: 'xato kiritildi',
            ),
            PosTotalsPanel(
              total: const PosMoney.som(198000),
              lines: <PosTotalsLine>[
                const PosTotalsLine(
                  label: 'Taomlar',
                  amount: PosMoney.som(180000),
                ),
                PosTotalsLine(
                  label: 'Xizmat haqi 10%',
                  amount: const PosMoney.som(18000),
                  actionLabel: 'Olib tashlash',
                  onAction: () => waived++,
                ),
                const PosTotalsLine(
                  label: 'Xona puli',
                  amount: PosMoney.som(300000),
                  waived: true,
                  detail: "Olib tashlandi: mehmon e'tirozi",
                ),
              ],
            ),
          ],
        ),
      ),
    );
    expect(find.text("Osh · 2-o'rin"), findsOneWidget);
    expect(find.text('+ Qazi'), findsOneWidget);
    expect(find.text('! kamroq yog'), findsOneWidget);
    expect(find.text('Oshxonada'), findsOneWidget);
    expect(find.text('Sabab: xato kiritildi'), findsOneWidget);
    final Text voided = tester.widget<Text>(find.text('Choy'));
    expect(voided.style!.decoration, TextDecoration.lineThrough);
    expect(find.text("Olib tashlandi: mehmon e'tirozi"), findsOneWidget);
    await tester.tap(find.text('Olib tashlash'));
    expect(waived, 1);
    expect(find.text(const PosMoney.som(198000).format()), findsOneWidget);
  });

  testWidgets('floor tile shows the waiter only when asked', (
    WidgetTester tester,
  ) async {
    setScreen(tester, const Size(400, 300));
    const FloorMapItem item = FloorMapItem(
      id: 'r1',
      label: 'Xona 1',
      kind: FloorTableKind.room,
      rect: Rect.fromLTWH(0, 0, 10, 8),
      status: FloorTableStatus.occupied,
      guests: 4,
      waiter: 'Bekzod',
    );
    await tester.pumpWidget(
      host(
        SizedBox(
          width: 300,
          height: 160,
          child: FloorTile(item: item, now: now),
        ),
      ),
    );
    expect(find.text('Bekzod'), findsNothing);
    await tester.pumpWidget(
      host(
        SizedBox(
          width: 300,
          height: 160,
          child: FloorTile(item: item, now: now, showWaiter: true),
        ),
      ),
    );
    expect(find.text('Bekzod'), findsOneWidget);
    expect(RzTheme.light().extension<RzColors>(), isNotNull);
  });
}
