import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/scenes.dart';

/// P6-12 tez kassa komponentlari: tez miqdor, telefon savat paneli, navbat raqami kartasi, "Tayyor" qatori — xatti-harakat
/// va ikkala mavzuda golden (test shrifti: joylashuv, ranglar, holatlar).
/// O'zgarishdan keyin: flutter test --update-goldens test/counter_test.dart
void main() {
  testWidgets(
    'quick quantity: a tap picks ×N, the same tap again goes back to ×1',
    (WidgetTester tester) async {
      var value = 1;
      await tester.pumpWidget(
        host(
          StatefulBuilder(
            builder: (BuildContext context, StateSetter set) =>
                PosQuickQuantityBar(
                  value: value,
                  onChanged: (int v) => set(() => value = v),
                ),
          ),
        ),
      );
      await tester.tap(find.text('×3'));
      await tester.pump();
      expect(value, 3);
      await tester.tap(find.text('×3'));
      await tester.pump();
      expect(value, 1);
      await tester.tap(find.text('×10'));
      await tester.pump();
      expect(value, 10);
      expect(find.bySemanticsLabel('Miqdor 10'), findsOneWidget);
    },
  );

  testWidgets('cart bar: the count, the total, open and pay', (
    WidgetTester tester,
  ) async {
    var opened = 0;
    var paid = 0;
    await tester.pumpWidget(
      host(
        Align(
          alignment: Alignment.bottomCenter,
          child: PosCartBar(
            count: 3,
            total: const PosMoney.som(51000),
            onOpen: () => opened++,
            onPay: () => paid++,
          ),
        ),
      ),
    );
    expect(find.text(const PosMoney.som(51000).format()), findsOneWidget);
    expect(find.text('3'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey<String>('cart-bar')));
    await tester.tap(find.text("To'lash"));
    expect((opened, paid), (1, 1));
  });

  testWidgets(
    'ready tile: «Berildi» and «Qayta chaqirish»; a preparing one offers «Tayyor»',
    (WidgetTester tester) async {
      final List<String> calls = <String>[];
      await tester.pumpWidget(
        host(
          Column(
            children: <Widget>[
              PosReadyNumberTile(
                number: '57',
                onHanded: () => calls.add('handed'),
                onCall: () => calls.add('call'),
              ),
              PosReadyNumberTile(
                number: '58',
                ready: false,
                onReady: () => calls.add('ready'),
              ),
            ],
          ),
        ),
      );
      await tester.tap(find.byTooltip('Qayta chaqirish'));
      await tester.tap(find.text('Berildi'));
      await tester.tap(find.text('Tayyor').last);
      expect(calls, <String>['call', 'handed', 'ready']);
    },
  );

  Widget scene() => SingleChildScrollView(
    padding: const EdgeInsets.all(16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        PosQuickQuantityBar(value: 3, onChanged: (_) {}),
        const SizedBox(height: 12),
        PosQuickQuantityBar(value: 1, compact: true, onChanged: (_) {}),
        const SizedBox(height: 12),
        const PosQueueNumberCard(
          number: '57',
          orderLabel: 'Olib ketish',
          message: "Buyurtmangiz tayyor bo'lganda raqamingiz ekranda chiqadi va chaqiriladi.",
        ),
        const SizedBox(height: 12),
        const PosQueueNumberCard(
          number: '58',
          tone: PosQueueTone.ready,
          dense: true,
        ),
        const SizedBox(height: 12),
        PosReadyNumberTile(
          number: '57',
          detail: 'Tayyor · 2 daq · 1 marta chaqirildi',
          onHanded: () {},
          onCall: () {},
        ),
        const SizedBox(height: 8),
        PosReadyNumberTile(number: '61', ready: false, onReady: () {}),
        const SizedBox(height: 12),
        PosCartBar(
          count: 3,
          total: const PosMoney.som(51000),
          number: '57',
          onOpen: () {},
          onPay: () {},
        ),
        const SizedBox(height: 8),
        PosCartBar(count: 0, total: PosMoney.zero, onOpen: () {}, onPay: null),
      ],
    ),
  );

  for (final (String mode, ThemeData theme) in <(String, ThemeData)>[
    ('light', RzTheme.light()),
    ('dark', RzTheme.dark()),
  ]) {
    testWidgets('counter components ($mode)', (WidgetTester tester) async {
      setScreen(tester, const Size(420, 860));
      await tester.pumpWidget(host(scene(), theme: theme));
      await tester.pumpAndSettle();
      await expectLater(
        find.byType(Scaffold),
        matchesGoldenFile('goldens/counter_$mode.png'),
      );
    });
  }
}
