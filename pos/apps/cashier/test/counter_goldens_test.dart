import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_cashier/src/counter/counter_page.dart';

import 'support/counter.dart';
import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

// P6-12 tez kassa suratlari: planshet (1280×800) va telefon (390×844). Oddiy ishga tushirishda — test shrifti bilan
// golden'lar (`test/goldens/counter_*.png`: joylashuv, ranglar, holatlar). `COUNTER_SHOTS=<papka>` va `--update-goldens`
// bilan — haqiqiy Roboto shrifti bilan o'qiladigan suratlar shu papkaga (hujjat va ko'rib chiqish uchun).
// O'zgarishdan keyin: flutter test --update-goldens test/counter_goldens_test.dart
void main() {
  final String? shots = Platform.environment['COUNTER_SHOTS'];

  setUpAll(() async {
    if (shots == null) return;
    // Readable screenshots: the SDK's Roboto and Material icons instead of the test font
    final String fonts =
        '${Platform.environment['FLUTTER_ROOT'] ?? '/opt/flutter'}/bin/cache/artifacts/material_fonts';
    final FontLoader roboto = FontLoader('Roboto');
    for (final String f in <String>[
      'Roboto-Regular.ttf',
      'Roboto-Medium.ttf',
      'Roboto-Bold.ttf',
      'Roboto-Black.ttf',
    ]) {
      roboto.addFont(
        Future<ByteData>.value(
          ByteData.sublistView(File('$fonts/$f').readAsBytesSync()),
        ),
      );
    }
    await roboto.load();
    final FontLoader icons = FontLoader('MaterialIcons')
      ..addFont(
        Future<ByteData>.value(
          ByteData.sublistView(
            File('$fonts/MaterialIcons-Regular.otf').readAsBytesSync(),
          ),
        ),
      );
    await icons.load();
  });

  Future<void> shot(WidgetTester tester, String name) async {
    if (shots != null) {
      // Labels whose style has no family (component themes, RzButton) would draw in the test font: Roboto for them
      for (final RenderParagraph r in tester.renderObjectList<RenderParagraph>(
        find.byType(RichText),
      )) {
        if (r.text.style?.fontFamily != null) continue;
        r.text = TextSpan(
          style: const TextStyle(fontFamily: 'Roboto'),
          children: <InlineSpan>[r.text],
        );
      }
      await tester.pump();
    }
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile(
        shots == null
            ? 'goldens/counter_$name.png'
            : Uri.file('$shots/counter_$name.png'),
      ),
    );
  }

  Future<(P22, FakeQueueBackend)> till({
    Map<String, Object?>? format,
    bool shift = true,
    bool printer = true,
  }) async {
    final P22 p = P22(offlineStore: await SyncStore.open(MemorySyncStorage()));
    p.state.format = format ?? formatBlock();
    seedCounterMenu(p.state);
    if (!printer) p.state.printers = <Map<String, Object?>>[];
    if (shift) p.state.openShift();
    return (p, FakeQueueBackend(p));
  }

  Future<void> sell(WidgetTester tester, List<int> ids) async {
    for (final int id in ids) {
      await tester.tap(menuTile(id));
      await tester.pumpAndSettle();
    }
    await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey<String>('payment-submit')));
    await tester.pumpAndSettle();
  }

  testWidgets('tablet: menu grid, quick quantity and the cart', (
    WidgetTester tester,
  ) async {
    final (P22 p, FakeQueueBackend _) = await till();
    await pumpHome(tester, p.h);
    await tester.tap(find.byKey(const ValueKey<String>('qty-x2')));
    await tester.pumpAndSettle();
    await tester.tap(menuTile(oshId));
    await tester.pumpAndSettle();
    await tester.tap(menuTile(1));
    await tester.pumpAndSettle();
    await tester.tap(menuTile(somsaId));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey<String>('qty-x3')));
    await tester.pumpAndSettle();
    expect(find.byType(CounterPage), findsOneWidget);
    await shot(tester, 'tablet_cart');
  });

  testWidgets(
    'tablet: paid — the number, «Keyingi mijoz», the ready list and the call',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend q) = await till();
      await pumpHome(tester, p.h);
      await sell(tester, <int>[oshId, 1]);
      await tester.tap(find.byKey(const ValueKey<String>('counter-next')));
      await tester.pumpAndSettle();
      await sell(tester, <int>[somsaId]);
      await tester.tap(find.byKey(const ValueKey<String>('counter-next')));
      await tester.pumpAndSettle();
      await sell(tester, <int>[oshId, oshId, colaId]);
      q.markReady(q.uuidOf(1));
      await tester.pumpAndSettle();
      expect(
        find.byKey(const ValueKey<String>('ready-announcement')),
        findsOneWidget,
      );
      await shot(tester, 'tablet_paid_ready');
      // The call hides itself
      await tester.pump(const Duration(seconds: 11));
      await tester.pumpAndSettle();
    },
  );

  testWidgets(
    'tablet (dark): waiting for payment after the payment page was left',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend _) = await till();
      p.h.settings.value = ThemeMode.dark;
      await pumpHome(tester, p.h);
      await tester.tap(menuTile(lagmonId));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Achchiq'));
      await tester.pumpAndSettle();
      await tester.tap(find.textContaining("Qo'shish").last);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text("To'lov kutilmoqda"), findsOneWidget);
      await shot(tester, 'tablet_waiting_dark');
    },
  );

  group('phone', () {
    const Size phone = Size(390, 844);

    testWidgets('phone mode: no shift yet — one big button', (
      WidgetTester tester,
    ) async {
      final (P22 p, FakeQueueBackend _) = await till(
        format: tamaddixonaFormat(),
        shift: false,
        printer: false,
      );
      await pumpHome(tester, p.h, size: phone);
      expect(
        find.byKey(const ValueKey<String>('counter-open-shift')),
        findsOneWidget,
      );
      await shot(tester, 'phone_shift');
    });

    testWidgets('phone: one-column menu and the cart bar', (
      WidgetTester tester,
    ) async {
      final (P22 p, FakeQueueBackend _) = await till(
        format: tamaddixonaFormat(),
        printer: false,
      );
      await pumpHome(tester, p.h, size: phone);
      await tester.tap(find.widgetWithText(ChoiceChip, 'Tez kassa'));
      await tester.pumpAndSettle();
      await tester.tap(menuTile(somsaId));
      await tester.pumpAndSettle();
      await tester.tap(menuTile(somsaId));
      await tester.pumpAndSettle();
      await tester.tap(menuTile(oshId));
      await tester.pumpAndSettle();
      await shot(tester, 'phone_menu');
      await tester.tap(find.byKey(const ValueKey<String>('cart-bar')));
      await tester.pumpAndSettle();
      await shot(tester, 'phone_cart_sheet');
    });

    testWidgets('phone: paid, with a number (osh markazi on a phone)', (
      WidgetTester tester,
    ) async {
      final (P22 p, FakeQueueBackend _) = await till(
        format: formatBlock(
          modules: const <String>[
            'quick_counter',
            'phone_mode',
            'queue_numbers',
            'queue_calling',
          ],
        ),
        printer: false,
      );
      await pumpHome(tester, p.h, size: phone);
      await tester.tap(find.widgetWithText(ChoiceChip, 'Tez kassa'));
      await tester.pumpAndSettle();
      await tester.tap(menuTile(oshId));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('payment-submit')));
      await tester.pumpAndSettle();
      expect(
        find.byKey(const ValueKey<String>('counter-next')),
        findsOneWidget,
      );
      await shot(tester, 'phone_paid');
      await tester.tap(find.byKey(const ValueKey<String>('counter-receipt')));
      await tester.pumpAndSettle();
      await shot(tester, 'phone_receipt');
    });
  });
}
