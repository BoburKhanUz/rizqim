import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_cashier/src/features/billing/billing_page.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';
import 'package:rizqim_cashier/src/shell/routes.dart';

import 'support/counter.dart';
import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';
import 'support/restaurant.dart';

// P6-21 … P6-25 kassa suratlari (restoran): to'lovda choy puli, mehmonlar bo'yicha bo'lish, og'irlik kutilayotgan
// buyurtma va kurslar, X hisobotdagi choy puli, tez kassada stol raqami — planshet (1280×800) va telefon (390×844).
// Oddiy ishga tushirishda — test shrifti bilan golden'lar (`test/goldens/restaurant_*.png`). `RESTAURANT_SHOTS=<papka>`
// va `--update-goldens` bilan — haqiqiy Roboto shrifti bilan o'qiladigan suratlar shu papkaga.
// O'zgarishdan keyin: flutter test --update-goldens test/restaurant_goldens_test.dart
void main() {
  final String? shots = Platform.environment['RESTAURANT_SHOTS'];
  const Size phone = Size(390, 844);

  setUpAll(() async {
    if (shots == null) return;
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
            ? 'goldens/restaurant_$name.png'
            : Uri.file('$shots/restaurant_$name.png'),
      ),
    );
  }

  /// Bo'lim ustidan sahifa (planshetda yon panel, telefonda pastki panel ostidagi kontekstdan).
  Future<void> push(WidgetTester tester, Widget Function() page) async {
    final Finder shell = find.byType(NavigationRail).evaluate().isNotEmpty
        ? find.byType(NavigationRail)
        : find.byType(NavigationBar);
    final BuildContext context = tester.element(shell.first);
    CashierRoutes.push<void>(context, page()).ignore();
    await tester.pumpAndSettle();
  }

  Future<void> paymentWithTip(WidgetTester tester, Size size) async {
    final Restaurant r = Restaurant();
    final String uuid = r.order(waiterId: 4);
    await pumpHome(tester, r.p.h, size: size);
    final PosOrder order = await settle(tester, r.p.h.data.orders.get(uuid));
    final PosBilling billing = await settle(
      tester,
      r.p.h.data.billing.createBills(uuid, version: order.version),
    );
    final PosOrder fresh = await settle(tester, r.p.h.data.orders.get(uuid));
    await push(
      tester,
      () => PaymentPage(bill: billing.bills.single, order: fresh),
    );
    await enter(tester, 'tender-given', '220000');
    await tapKey(tester, 'tip-from-change');
    await tester.drag(find.byType(Scrollable).last, const Offset(0, -200));
    await tester.pumpAndSettle();
  }

  testWidgets('tablet: payment with the change left as a tip', (
    WidgetTester tester,
  ) async {
    await paymentWithTip(tester, const Size(1280, 800));
    await shot(tester, 'tablet_payment_tip');
  });

  testWidgets('phone: payment with the change left as a tip', (
    WidgetTester tester,
  ) async {
    await paymentWithTip(tester, phone);
    await shot(tester, 'phone_payment_tip');
  });

  Future<void> guestSplit(WidgetTester tester, Size size) async {
    final Restaurant r = Restaurant();
    final String uuid = r.order(
      guests: 3,
      lines: const <(int, String)>[(3, '1.500'), (2, '1'), (1, '2')],
      seats: const <int?>[1, 2, null],
    );
    await pumpHome(tester, r.p.h, size: size);
    await push(tester, () => BillingPage(orderUuid: uuid));
    await tapText(tester, "Mehmonlar bo'yicha");
  }

  testWidgets('tablet: split by guest, the preview per guest', (
    WidgetTester tester,
  ) async {
    await guestSplit(tester, const Size(1280, 800));
    await shot(tester, 'tablet_split_guest');
  });

  testWidgets('phone: split by guest, the preview per guest', (
    WidgetTester tester,
  ) async {
    await guestSplit(tester, phone);
    await shot(tester, 'phone_split_guest');
  });

  Future<int> weightAndCourses(WidgetTester tester, Size size) async {
    final Restaurant r = Restaurant();
    final String uuid = r.order(
      lines: const <(int, String)>[(3, '1.500'), (1, '2'), (2, '2')],
      courses: const <int?>[1, 1, 2],
      awaitingWeight: true,
    );
    await pumpHome(tester, r.p.h, size: size);
    await push(tester, () => OrderPage(orderUuid: uuid));
    if (size.width < 900) {
      await tester.tap(find.textContaining('Buyurtma ('));
      await tester.pumpAndSettle();
    }
    return items(r.p.state.orders[uuid]!).first['id']! as int;
  }

  testWidgets('tablet: a dish waits for its weight, the next course is held', (
    WidgetTester tester,
  ) async {
    final int id = await weightAndCourses(tester, const Size(1280, 800));
    await shot(tester, 'tablet_order_weight_courses');
    await tapKey(tester, 'item-$id');
    await tapButton(tester, "Og'irlikni kiritish");
    await shot(tester, 'tablet_weight_dialog');
  });

  testWidgets('tablet: the X report with the tips section', (
    WidgetTester tester,
  ) async {
    final Restaurant r = Restaurant();
    final String uuid = r.order(waiterId: 4);
    await pumpHome(tester, r.p.h);
    final PosData data = r.p.h.data;
    final PosOrder order = await settle(tester, data.orders.get(uuid));
    final PosBilling billing = await settle(
      tester,
      data.billing.createBills(uuid, version: order.version),
    );
    final PosBill bill = billing.bills.single;
    await settle(
      tester,
      data.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[
          PosTenderInput.cash(
            bill.dueAmount,
            tendered: bill.dueAmount + const PosMoney.som(11000),
            tip: const PosMoney.som(11000),
            tipFromChange: true,
          ),
        ],
        shiftUuid: r.p.state.shift!['uuid'] as String,
        tipStaffId: 4,
      ),
    );
    await openSection(tester, 'Smena');
    await shot(tester, 'tablet_shift_tips');
    await tapKey(tester, 'x-report');
    await tester.drag(find.byType(Scrollable).last, const Offset(0, -500));
    await tester.pumpAndSettle();
    await shot(tester, 'tablet_x_tips');
  });

  testWidgets('phone: the quick counter with the table plate (kafe)', (
    WidgetTester tester,
  ) async {
    final P22 p = P22(offlineStore: await SyncStore.open(MemorySyncStorage()));
    Restaurant(
      p: p,
      clock: true,
      modules: const <String>[
        'quick_counter',
        'phone_mode',
        'queue_numbers',
        'table_service',
        'tips',
        'staff_pay',
      ],
    );
    p.state.format = formatBlock(
      profile: 'kafe',
      label: 'Kafe',
      modules: const <String>[
        'quick_counter',
        'phone_mode',
        'queue_numbers',
        'table_service',
        'tips',
        'staff_pay',
      ],
    );
    p.state.printers = <Map<String, Object?>>[];
    seedCounterMenu(p.state);
    FakeQueueBackend(p);
    await pumpHome(tester, p.h, size: phone);
    await tester.tap(find.widgetWithText(ChoiceChip, 'Tez kassa'));
    await tester.pumpAndSettle();
    await tester.tap(menuTile(oshId));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey<String>('cart-bar')));
    await tester.pumpAndSettle();
    await enter(tester, 'counter-table-label', '12');
    await shot(tester, 'phone_counter_plate');
  });
}
