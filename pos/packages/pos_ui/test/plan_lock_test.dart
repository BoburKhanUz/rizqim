import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/scenes.dart';

void main() {
  group('PosPlanButton', () {
    testWidgets('is the plain button while the plan has the feature', (
      WidgetTester tester,
    ) async {
      int taps = 0;
      await tester.pumpWidget(
        host(
          PosPlanButton(
            label: 'Yangi bron',
            locked: false,
            lockLabel: 'Asosiy tarifda bor',
            onPressed: () => taps++,
          ),
        ),
      );
      expect(find.text('Asosiy tarifda bor'), findsNothing);
      await tester.tap(find.text('Yangi bron'));
      expect(taps, 1);
    });

    testWidgets('is disabled and says which plan has the feature when locked', (
      WidgetTester tester,
    ) async {
      int taps = 0;
      await tester.pumpWidget(
        host(
          PosPlanButton(
            label: 'Yangi bron',
            locked: true,
            lockLabel: 'Asosiy tarifda bor',
            onPressed: () => taps++,
          ),
        ),
      );
      expect(find.text('Asosiy tarifda bor'), findsOneWidget);
      await tester.tap(find.text('Yangi bron'), warnIfMissed: false);
      expect(taps, 0);
      final RzButton button = tester.widget<RzButton>(find.byType(RzButton));
      expect(button.onPressed, isNull);
    });
  });

  testWidgets(
    'the notice bar shows the title and the text, in danger when locked',
    (WidgetTester tester) async {
      await tester.pumpWidget(
        host(
          const Column(
            children: <Widget>[
              PosPlanNoticeBar(
                title:
                    "To'lov muddati o'tdi — 4 kundan keyin sozlamalar yopiladi",
                text: 'Kassa ishlashda davom etadi.',
                locked: false,
              ),
              PosPlanNoticeBar(
                title: "Sozlamalar yopiq — to'lov qiling",
                text: "Kassa to'lov qabul qilishda davom etadi.",
                locked: true,
              ),
            ],
          ),
        ),
      );
      expect(find.textContaining('4 kundan keyin'), findsOneWidget);
      expect(
        find.textContaining('Kassa ishlashda davom etadi'),
        findsOneWidget,
      );
      expect(find.textContaining("Sozlamalar yopiq"), findsOneWidget);
      expect(find.byIcon(Icons.lock_outline_rounded), findsOneWidget);
      expect(find.byIcon(Icons.warning_amber_rounded), findsOneWidget);
    },
  );
}
