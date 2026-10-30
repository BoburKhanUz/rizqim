import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'restaurant_test.dart' show holdAsCourse, openRestaurantOrder, tapChip;
import 'support/harness.dart';
import 'support/restaurant.dart';

// P6 2-to'lqin ofitsiant suratlari: telefon (412×860) va hostes plansheti (1280×800). Oddiy ishga tushirishda — test
// shrifti bilan golden'lar (`test/goldens/restaurant_*.png`). `WAITER_SHOTS=<papka>` va `--update-goldens` bilan —
// haqiqiy Roboto shrifti bilan o'qiladigan suratlar shu papkaga (hujjat va ko'rib chiqish uchun).
// O'zgarishdan keyin: flutter test --update-goldens test/restaurant_goldens_test.dart
void main() {
  final String? shots = Platform.environment['WAITER_SHOTS'];

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
      // (twice: a label rebuilt by the first frame gets it again)
      for (var pass = 0; pass < 2; pass++) {
        for (final RenderParagraph r
            in tester.renderObjectList<RenderParagraph>(
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
    }
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile(
        shots == null
            ? 'goldens/restaurant_$name.png'
            : Uri.file('$shots/restaurant_$name.png'),
      ),
    );
  }

  testWidgets('phone: an order with courses, guests and a dish to weigh', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    RestaurantFake(h).seedMenu();
    await openRestaurantOrder(
      tester,
      h,
      lines: const <(int, String)>[(4, '4'), (7, '1.200'), (1, '2')],
      before: (String _, List<Map<String, Object?>> items) {
        items[0]
          ..['course_no'] = 1
          ..['seat_no'] = 1
          ..['kitchen_status'] = 'ready';
        items[1]
          ..['seat_no'] = 2
          ..['weight_status'] = 'awaiting'
          ..['weight_basis'] = 'raw';
        holdAsCourse(items[1], 2);
        items[2]['seat_no'] = null;
      },
    );
    await shot(tester, 'phone_order_courses');
  });

  testWidgets('phone: adding with course and guest chips, skewer buttons', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    RestaurantFake(h).seedMenu();
    await openRestaurantOrder(tester, h);
    await tapButton(tester, "Taom qo'shish");
    await tapChip(tester, '2-kurs');
    await tapChip(tester, '2');
    await tapChip(tester, 'Taomlar');
    await tester.pumpAndSettle();
    await tester.tap(find.bySemanticsLabel("Jigar: 10 dona qo'shish"));
    await tester.pumpAndSettle();
    await shot(tester, 'phone_add_items');
  });

  testWidgets('phone: course ready toast and the clock-in strip', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final RestaurantFake fake = RestaurantFake(h);
    final String uuid = seedWaiterOrder(h, tableId: 1);
    await pumpHome(tester, h);
    fake.courseReady(uuid, 2);
    await tester.pumpAndSettle();
    await shot(tester, 'phone_course_ready');
  });

  testWidgets('phone: my tips', (WidgetTester tester) async {
    final WaiterHarness h = WaiterHarness();
    RestaurantFake(h)
      ..addTip('20000.00')
      ..addTip('15000.00', method: 'card')
      ..addTip('5000.00', source: 'change');
    await pumpHome(tester, h);
    await tester.tap(find.byTooltip('Menyu').first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Mening choy pulim'));
    await tester.pumpAndSettle();
    await shot(tester, 'phone_my_tips');
  });

  Future<RestaurantFake> hostess(
    WidgetTester tester,
    WaiterHarness h,
    Size size,
  ) async {
    final RestaurantFake fake = RestaurantFake(h)..addHostess();
    fake
      ..addWaiting('Jasur', party: 4, waited: 22, quoted: 15)
      ..addWaiting('Madina', party: 2, waited: 3, phone: null)
      ..addWaiting('Oila Karimovlar', party: 7, waited: 9, quoted: 30);
    fake.waitlist[1]['notified_at'] = h.now.toIso8601String();
    fake.serviceStates[3] = 'cleaning';
    fake.addBooking(
      name: 'Anvar',
      startsAt: h.now.add(const Duration(hours: 2)),
      guests: 6,
    );
    seedWaiterOrder(h, tableId: 2);
    await settle(tester, h.client.auth.pairTerminal('ABCD-EFGH'));
    await pumpWaiter(tester, h, size: size);
    await tester.tap(find.text(hostessName));
    await tester.pumpAndSettle();
    await typePin(tester, hostessPin);
    return fake;
  }

  testWidgets('phone: the hostess waitlist', (WidgetTester tester) async {
    final WaiterHarness h = WaiterHarness();
    await hostess(tester, h, const Size(412, 860));
    await shot(tester, 'phone_hostess_waitlist');
    await tester.tap(find.text('Stollar'));
    await tester.pumpAndSettle();
    await shot(tester, 'phone_hostess_tables');
  });

  testWidgets('tablet: the hostess desk', (WidgetTester tester) async {
    final WaiterHarness h = WaiterHarness();
    await hostess(tester, h, const Size(1280, 800));
    await shot(tester, 'tablet_hostess');
  });
}
