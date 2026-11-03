import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'fast_food_test.dart' show fastFoodTill, key, payExact;
import 'support/counter.dart';
import 'support/fast_food.dart';
import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

// P6 wave 3 stage B fast food suratlari: kombo oynasi, hajm tugmalari, kombo va qadoqli savat, stiker ko'rinishi — planshet
// (1280×800) va telefon (390×844). Oddiy ishga tushirishda — test shrifti bilan golden'lar (`test/goldens/fast_food_*.png`).
// `COUNTER_SHOTS=<papka>` va `--update-goldens` bilan — Roboto shrifti bilan o'qiladigan suratlar shu papkaga.
// O'zgarishdan keyin: flutter test --update-goldens test/fast_food_goldens_test.dart
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
            ? 'goldens/fast_food_$name.png'
            : Uri.file('$shots/fast_food_$name.png'),
      ),
    );
  }

  const Size phone = Size(390, 844);

  /// The one-column phone menu builds the tiles it shows: scroll the grid to [f] first.
  Future<void> reveal(WidgetTester tester, Finder f) async {
    await tester.scrollUntilVisible(
      f,
      200,
      scrollable: find.descendant(
        of: find.byType(GridView),
        matching: find.byType(Scrollable),
      ),
    );
    await tester.pumpAndSettle();
  }

  for (final (String tag, Size size) in <(String, Size)>[
    ('tablet', const Size(1280, 800)),
    ('phone', phone),
  ]) {
    testWidgets('$tag: the combo sheet with a changed drink and a sauce', (
      WidgetTester tester,
    ) async {
      final (P22 p, FakeQueueBackend _, FastFoodBackend _) =
          await fastFoodTill();
      await pumpHome(tester, p.h, size: size);
      await reveal(tester, menuTile(comboId));
      await tester.tap(menuTile(comboId));
      await tester.pumpAndSettle();
      await tester.tap(key('combo-option-${optionUuid(4)}'));
      await tester.tap(key('combo-option-${optionUuid(6)}'));
      await tester.pumpAndSettle();
      await shot(tester, '${tag}_combo_sheet');
    });

    testWidgets('$tag: the cart with a combo, a sized coffee and the bag', (
      WidgetTester tester,
    ) async {
      final (P22 p, FakeQueueBackend _, FastFoodBackend _) =
          await fastFoodTill();
      await pumpHome(tester, p.h, size: size);
      await reveal(tester, menuTile(comboId));
      await tester.tap(menuTile(comboId));
      await tester.pumpAndSettle();
      await tester.tap(key('combo-add'));
      await tester.pumpAndSettle();
      await reveal(tester, key('menu-$coffeeId-size-${sizeUuid(63)}'));
      await tester.tap(key('menu-$coffeeId-size-${sizeUuid(63)}'));
      await tester.pumpAndSettle();
      if (size == phone) {
        await shot(tester, '${tag}_size_selector');
        await tester.tap(find.byKey(const ValueKey<String>('cart-bar')));
        await tester.pumpAndSettle();
      }
      await shot(tester, '${tag}_cart_combo_packaging');
    });
  }

  testWidgets('tablet: the size selector on the tile and the line', (
    WidgetTester tester,
  ) async {
    final (P22 p, FakeQueueBackend _, FastFoodBackend _) = await fastFoodTill();
    await pumpHome(tester, p.h);
    await tester.tap(find.widgetWithText(ChoiceChip, 'Ichimliklar'));
    await tester.pumpAndSettle();
    await tester.tap(key('menu-$coffeeId-size-${sizeUuid(61)}'));
    await tester.pumpAndSettle();
    await tester.tap(key('cart-1-mod-${sizeUuid(65)}'));
    await tester.pumpAndSettle();
    await shot(tester, 'tablet_size_selector');
  });

  testWidgets('tablet: the sticker preview after a sale', (
    WidgetTester tester,
  ) async {
    final (P22 p, FakeQueueBackend _, FastFoodBackend _) = await fastFoodTill();
    await pumpHome(tester, p.h);
    await tester.tap(menuTile(comboId));
    await tester.pumpAndSettle();
    await tester.tap(key('combo-add'));
    await tester.pumpAndSettle();
    await tester.tap(key('menu-$coffeeId-size-${sizeUuid(62)}'));
    await tester.pumpAndSettle();
    await payExact(tester);
    await tester.tap(key('counter-labels'));
    await tester.pumpAndSettle();
    await shot(tester, 'tablet_sticker_preview');
  });
}
