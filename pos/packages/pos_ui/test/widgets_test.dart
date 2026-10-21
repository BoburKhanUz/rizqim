import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/scenes.dart';

String som(int value) => PosMoney.som(value).format();

Future<void> tapLabel(WidgetTester tester, String label) async {
  await tester.tap(find.bySemanticsLabel(label).first);
  await tester.pump();
}

void main() {
  group('PosKeypadController', () {
    test('integer input: leading zero, max digits, backspace, clear', () {
      final PosKeypadController c = PosKeypadController(maxIntegerDigits: 6);
      c.input('0');
      expect(c.text, '0');
      c.input('5');
      expect(c.text, '5');
      c.input('000');
      expect(c.text, '5000');
      c.input('.');
      expect(c.text, '5000'); // no decimals allowed
      c.input('999');
      expect(c.text, '500099'); // capped at 6 digits
      expect(c.amount, const PosMoney.som(500099));
      c.backspace();
      expect(c.text, '50009');
      c.clear();
      expect(c.isEmpty, isTrue);
      expect(c.amount, PosMoney.zero);
      c.dispose();
    });

    test('decimal input: one dot, at most three decimals', () {
      final PosKeypadController c = PosKeypadController(maxDecimals: 3);
      c.input('.');
      expect(c.text, '0.');
      expect(c.quantity, PosQuantity.zero);
      c.input('7');
      c.input('5');
      c.input('.');
      c.input('1');
      c.input('9');
      expect(c.text, '0.751');
      expect(c.quantity, PosQuantity.parse('0.751'));
      c.dispose();
    });
  });

  group('PosAmountEntry', () {
    testWidgets('types thousands quickly, quick amounts, clear on long press', (
      WidgetTester tester,
    ) async {
      setScreen(tester, const Size(400, 700));
      final List<PosMoney> values = <PosMoney>[];
      await tester.pumpWidget(
        host(
          PosAmountEntry(
            quickAmounts: const <PosMoney>[PosMoney.som(100000)],
            onChanged: values.add,
          ),
        ),
      );
      await tapLabel(tester, '1');
      await tapLabel(tester, '2');
      await tapLabel(tester, '000');
      expect(values.last, const PosMoney.som(12000));
      expect(
        find.bySemanticsLabel("${groupDigits(12000)} so'm"),
        findsOneWidget,
      );
      await tapLabel(tester, som(100000));
      expect(values.last, const PosMoney.som(100000));
      await tester.longPress(find.bySemanticsLabel("O'chirish"));
      await tester.pump();
      expect(values.last, PosMoney.zero);
      // keys are big enough for a finger
      expect(
        tester.getSize(find.bySemanticsLabel('5')).height,
        greaterThanOrEqualTo(64),
      );
    });

    testWidgets('physical keyboard digits and backspace', (
      WidgetTester tester,
    ) async {
      final List<PosMoney> values = <PosMoney>[];
      await tester.pumpWidget(
        host(PosAmountEntry(autofocus: true, onChanged: values.add)),
      );
      await tester.pump();
      await tester.sendKeyEvent(LogicalKeyboardKey.digit7);
      await tester.sendKeyEvent(LogicalKeyboardKey.digit5);
      await tester.sendKeyEvent(LogicalKeyboardKey.backspace);
      await tester.pump();
      expect(values.map((PosMoney m) => m.wholeSom), <int>[7, 75, 7]);
    });
  });

  group('PosQuantityKeypad', () {
    testWidgets('a preset fills the quantity; submit names it with the unit', (
      WidgetTester tester,
    ) async {
      setScreen(tester, const Size(420, 800));
      final List<PosQuantity> submitted = <PosQuantity>[];
      await tester.pumpWidget(
        host(
          SingleChildScrollView(
            child: PosQuantityKeypad(
              unitLabel: 'kg (guruch)',
              onSubmit: submitted.add,
            ),
          ),
        ),
      );
      expect(find.text('Miqdorni tanlang yoki tering'), findsOneWidget);
      for (final String p in <String>['0.5', '0.7', '0.75']) {
        expect(find.bySemanticsLabel(p), findsOneWidget);
      }
      // presets 1 and 2 next to the keypad digits 1 and 2
      expect(find.bySemanticsLabel('2'), findsNWidgets(2));
      await tapLabel(tester, '0.75');
      await tapLabel(tester, "Qo'shish: 0.75 kg (guruch)");
      expect(submitted, <PosQuantity>[PosQuantity.parse('0.75')]);
    });

    testWidgets('typed fraction is checked against step, min and max', (
      WidgetTester tester,
    ) async {
      setScreen(tester, const Size(420, 800));
      final List<PosQuantity> submitted = <PosQuantity>[];
      await tester.pumpWidget(
        host(
          SingleChildScrollView(
            child: PosQuantityKeypad(
              presets: const <PosQuantity>[],
              step: PosQuantity.parse('0.1'),
              max: PosQuantity.parse('10'),
              onSubmit: submitted.add,
            ),
          ),
        ),
      );
      await tapLabel(tester, 'Nuqta');
      await tapLabel(tester, '2');
      await tapLabel(tester, '5');
      expect(find.text('Qadam: 0.1 kg'), findsOneWidget);
      final RzButton submit = tester.widget<RzButton>(find.byType(RzButton));
      expect(submit.onPressed, isNull);
      await tester.tap(find.bySemanticsLabel("O'chirish"));
      await tester.pump();
      expect(find.text('Qadam: 0.1 kg'), findsNothing);
      await tapLabel(tester, "Qo'shish: 0.2 kg");
      expect(submitted.single, PosQuantity.parse('0.2'));
      await tester.longPress(find.bySemanticsLabel("O'chirish"));
      await tester.pump();
      await tapLabel(tester, '1');
      await tapLabel(tester, '5');
      expect(find.text("Ko'pi bilan 10 kg"), findsOneWidget);
    });
  });

  group('PosQuantityStepper', () {
    testWidgets('steps within limits; at the minimum minus removes the line', (
      WidgetTester tester,
    ) async {
      PosQuantity value = const PosQuantity.whole(1);
      var removed = 0;
      await tester.pumpWidget(
        host(
          StatefulBuilder(
            builder: (BuildContext context, StateSetter setState) =>
                PosQuantityStepper(
                  value: value,
                  max: const PosQuantity.whole(3),
                  onChanged: (PosQuantity v) => setState(() => value = v),
                  onRemove: () => removed++,
                ),
          ),
        ),
      );
      expect(find.bySemanticsLabel('Olib tashlash'), findsOneWidget);
      await tapLabel(tester, 'Olib tashlash');
      expect(removed, 1);
      await tapLabel(tester, "Ko'paytirish");
      await tapLabel(tester, "Ko'paytirish");
      expect(value, const PosQuantity.whole(3));
      expect(find.bySemanticsLabel('Miqdor: 3'), findsOneWidget);
      await tapLabel(tester, "Ko'paytirish"); // disabled at max
      expect(value, const PosQuantity.whole(3));
      await tapLabel(tester, 'Kamaytirish');
      expect(value, const PosQuantity.whole(2));
      expect(
        tester.getSize(find.bySemanticsLabel('Kamaytirish')).width,
        greaterThanOrEqualTo(44),
      );
    });
  });

  group('PosTileButton', () {
    testWidgets('is big, announces price and badge; sold out is not tappable', (
      WidgetTester tester,
    ) async {
      var taps = 0;
      await tester.pumpWidget(
        host(
          Row(
            children: <Widget>[
              PosTileButton(
                label: 'Osh',
                subtitle: "30 000 so'm",
                badge: 'Qoldi: 4',
                onTap: () => taps++,
              ),
              PosTileButton(
                label: 'Kabob',
                unavailable: true,
                onTap: () => taps++,
              ),
            ],
          ),
        ),
      );
      final Finder osh = find.bySemanticsLabel("Osh, 30 000 so'm, Qoldi: 4");
      expect(osh, findsOneWidget);
      final Size size = tester.getSize(osh);
      expect(size.width, greaterThanOrEqualTo(96));
      expect(size.height, greaterThanOrEqualTo(88));
      await tester.tap(osh);
      await tester.tap(find.bySemanticsLabel('Kabob, Tugadi'));
      expect(taps, 1);
    });
  });

  group('FloorMap', () {
    testWidgets(
      'places items by layout, shows status and badges, reports taps',
      (WidgetTester tester) async {
        setScreen(tester, const Size(1000, 600));
        final List<String> tapped = <String>[];
        await tester.pumpWidget(
          host(
            FloorMap(
              items: hall,
              now: now,
              onTap: (FloorMapItem i) => tapped.add(i.id),
            ),
          ),
        );
        // Xona 1: open 2:35, 12 of 15 guests, the bill so far
        expect(
          find.bySemanticsLabel(
            'Xona 1, Band, ochiq 2:35, mehmon 12/15, ${som(2450000)}',
          ),
          findsOneWidget,
        );
        expect(
          find.bySemanticsLabel('Xona 2, Bron, mehmon 10, bron 21:00'),
          findsOneWidget,
        );
        expect(find.text('Uzoq ochiq'), findsOneWidget);
        expect(find.text('6:30'), findsOneWidget); // Xona 3 open since 14:10

        // proportions are kept: Xona 3 (40 wide) is wider than Xona 1 (30 wide)
        final Size r1 = tester.getSize(
          find.bySemanticsLabel(RegExp('^Xona 1,')),
        );
        final Size r3 = tester.getSize(
          find.bySemanticsLabel(RegExp('^Xona 3')),
        );
        expect(r3.width / r1.width, closeTo(40 / 30, 0.05));

        await tester.tap(find.bySemanticsLabel(RegExp('^Tapchan 2,')));
        expect(tapped, <String>['t2']);
        expect(tester.takeException(), isNull);
      },
    );

    testWidgets('tiny map (phone) still renders without overflow', (
      WidgetTester tester,
    ) async {
      setScreen(tester, const Size(320, 220));
      await tester.pumpWidget(host(FloorMap(items: hall, now: now)));
      expect(tester.takeException(), isNull);
      expect(find.byType(FloorTile), findsNWidgets(hall.length));
    });
  });

  group('RoomSummaryCard', () {
    testWidgets('shows how much is left to the minimum spend', (
      WidgetTester tester,
    ) async {
      setScreen(tester, const Size(600, 600));
      await tester.pumpWidget(host(summaryScene()));
      expect(find.text('Minimal summagacha: ${som(550000)}'), findsOneWidget);
      expect(find.text(som(-1000000)), findsOneWidget);
      expect(find.text('18:05 dan, 2:35'), findsOneWidget);
      expect(find.text('12/15 mehmon'), findsOneWidget);
      expect(find.text('3 bosqich'), findsOneWidget);

      await tester.pumpWidget(
        host(
          const RoomSummaryCard(
            title: 'Xona 2',
            status: FloorTableStatus.billPrinted,
            total: PosMoney.som(3200000),
            minSpend: PosMoney.som(3000000),
          ),
        ),
      );
      expect(
        find.text('Minimal summa (${som(3000000)}) bajarildi'),
        findsOneWidget,
      );
      expect(find.text('Schyot chiqdi'), findsOneWidget);
    });
  });
}
