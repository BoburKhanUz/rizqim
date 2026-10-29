import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_cashier/src/counter/counter_page.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/floor/floor_page.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';
import 'package:rizqim_cashier/src/features/shift/z_close_page.dart';

import 'support/counter.dart';
import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

/// P6-12: tez kassa (A1, A4), navbat raqami (P6-13), telefon rejimi (A5) va modullar bo'yicha yashirish (P6-11).
void main() {
  /// Osh markazi: `counter` rejimi, raqam va chaqiruv; qurilma jurnali (navbat raqamlari uchun) xotirada.
  Future<(P22, FakeQueueBackend)> oshMarkazi({
    Map<String, Object?>? format,
    bool shift = true,
  }) async {
    final SyncStore store = await SyncStore.open(MemorySyncStorage());
    final P22 p = P22(offlineStore: store);
    p.state.format = format ?? formatBlock();
    seedCounterMenu(p.state);
    if (shift) p.state.openShift();
    return (p, FakeQueueBackend(p));
  }

  Finder rail(String label) => find.descendant(
    of: find.byType(NavigationRail),
    matching: find.text(label),
  );

  Map<String, Object?> lastOrder(P22 p) => p.state.orders.values.last;

  testWidgets(
    'counter format: the till opens on «Tez kassa»; one dish is an order in 3 taps; talon printed; next customer in one tap',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend q) = await oshMarkazi();
      await pumpHome(tester, p.h);

      expect(find.byType(CounterPage), findsOneWidget);
      expect(rail('Tez kassa'), findsOneWidget);
      final TapCounter taps = TapCounter(tester);
      await taps.tap(menuTile(oshId));
      await taps.tap(find.byKey(const ValueKey<String>('counter-pay')));
      expect(find.byType(PaymentPage), findsOneWidget);
      await taps.tap(find.byKey(const ValueKey<String>('payment-submit')));
      expect(taps.count, 3);

      // A4: the lines went to the kitchen with the order, the talon's number came from this till's block
      final Map<String, Object?> order = lastOrder(p);
      expect(order['channel'], 'pos_takeaway');
      expect(items(order).single['kitchen_status'], isNot('pending'));
      expect(q.sentNumbers, <int?>[1]);
      expect(find.byType(CounterPage), findsOneWidget);
      expect(find.text("To'landi: ${som(35000)}"), findsOneWidget);
      expect(
        find.descendant(
          of: find.byType(PosQueueNumberCard),
          matching: find.text('1'),
        ),
        findsOneWidget,
      );
      expect(
        p.receipts.where((String r) => r.contains('Navbat raqamingiz')),
        hasLength(1),
      );
      expect(
        p.receipts.firstWhere((String r) => r.contains('Navbat raqamingiz')),
        contains('Olib ketish'),
      );

      await tester.tap(find.byKey(const ValueKey<String>('counter-next')));
      await tester.pumpAndSettle();
      expect(find.text("Savat bo'sh"), findsOneWidget);

      // The next customer gets the next number
      await tester.tap(menuTile(somsaId));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('payment-submit')));
      await tester.pumpAndSettle();
      expect(q.sentNumbers, <int?>[1, 2]);
      expect(
        p.receipts.where((String r) => r.contains('Navbat raqamingiz')),
        hasLength(2),
      );
    },
  );

  testWidgets(
    'quick quantity ×3, a required modifier, + and −, the next dish after a paid sale starts the next customer',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend _) = await oshMarkazi();
      await pumpHome(tester, p.h);

      await tester.tap(find.byKey(const ValueKey<String>('qty-x3')));
      await tester.pumpAndSettle();
      await tester.tap(menuTile(somsaId));
      await tester.pumpAndSettle();
      expect(find.text(som(24000)), findsWidgets);
      // ×3 is used once: the next tap is +1
      await tester.tap(menuTile(somsaId));
      await tester.pumpAndSettle();
      expect(find.text(som(32000)), findsWidgets);

      // Lag'mon needs "Achchiqligi"
      await tester.tap(menuTile(lagmonId));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Achchiqmas'));
      await tester.pumpAndSettle();
      await tester.tap(find.textContaining("Qo'shish").last);
      await tester.pumpAndSettle();
      expect(find.text('+ Achchiqmas'), findsOneWidget);

      final Finder plus = find.byWidgetPredicate(
        (Widget w) =>
            w.key is ValueKey<String> &&
            (w.key! as ValueKey<String>).value.startsWith('cart-plus-'),
      );
      await tester.tap(plus.first);
      await tester.pumpAndSettle();
      await tester.tap(
        find
            .byWidgetPredicate(
              (Widget w) =>
                  w.key is ValueKey<String> &&
                  (w.key! as ValueKey<String>).value.startsWith('cart-minus-'),
            )
            .first,
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('payment-submit')));
      await tester.pumpAndSettle();
      final Map<String, Object?> order = lastOrder(p);
      final List<Map<String, Object?>> lines = items(order);
      expect(lines, hasLength(2));
      // Somsa: ×3, +1, then + and − on the cart line
      expect(num.parse('${lines.first['quantity']}'), 4);
      expect(lines.last['product_name'], "Lag'mon");
      expect(
        (lines.last['modifiers']! as List<Object?>).single.toString(),
        contains('Achchiqmas'),
      );

      // Paid: tapping a dish starts the next customer at once
      await tester.tap(menuTile(colaId));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey<String>('counter-next')), findsNothing);
      expect(find.text(som(10000)), findsWidgets);
    },
  );

  testWidgets(
    'pay first: leaving the payment keeps the sent order «To\'lov kutilmoqda»; the manager cancels it',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend _) = await oshMarkazi();
      await pumpHome(tester, p.h, name: 'Malika Rahimova');

      await tester.tap(menuTile(oshId));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      await tester.pageBack();
      await tester.pumpAndSettle();

      expect(find.text("To'lov kutilmoqda"), findsOneWidget);
      // The menu is locked until the sale is paid or cancelled
      await tester.tap(menuTile(somsaId));
      await tester.pumpAndSettle();
      expect(items(lastOrder(p)), hasLength(1));

      // Paying again goes to the same order and bill
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      expect(find.byType(PaymentPage), findsOneWidget);
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(p.state.orders, hasLength(1));

      await tester.tap(find.byKey(const ValueKey<String>('counter-cancel')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Mijoz voz kechdi'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Bekor qilish').last);
      await tester.pumpAndSettle();
      expect(lastOrder(p)['status'], 'canceled');
      expect(find.text("Savat bo'sh"), findsOneWidget);
    },
  );

  testWidgets(
    'queue.ready: a sound and an on-screen call; «Qayta chaqirish» calls again, «Berildi» takes it off the list',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend q) = await oshMarkazi();
      final List<MethodCall> sounds = recordSystemSounds(tester);
      await pumpHome(tester, p.h);
      await tester.tap(menuTile(oshId));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('payment-submit')));
      await tester.pumpAndSettle();

      // The kitchen bumps the last dish
      q.markReady(q.uuidOf(1));
      await tester.pumpAndSettle();
      expect(
        find.byKey(const ValueKey<String>('ready-announcement')),
        findsOneWidget,
      );
      expect(find.text('Tayyor — mijozni chaqiring'), findsOneWidget);
      expect(sounds, hasLength(1));
      expect(find.byKey(const ValueKey<String>('ready-1')), findsOneWidget);

      await tester.tap(find.byTooltip('Qayta chaqirish'));
      await tester.pumpAndSettle();
      expect(q.entries.values.single['call_count'], 1);
      expect(find.text('Qayta chaqirildi'), findsOneWidget);
      expect(sounds, hasLength(2));

      await tester.tap(find.text('Berildi'));
      await tester.pumpAndSettle();
      expect(q.entries.values.single['status'], 'handed');
      expect(find.byKey(const ValueKey<String>('ready-1')), findsNothing);

      // The call goes away by itself
      await tester.pump(const Duration(seconds: 11));
      await tester.pumpAndSettle();
      expect(
        find.byKey(const ValueKey<String>('ready-announcement')),
        findsNothing,
      );
    },
  );

  testWidgets('no KDS: the cashier marks a preparing number «Tayyor»', (
    WidgetTester tester,
  ) async {
    final (P22 p, FakeQueueBackend q) = await oshMarkazi();
    await pumpHome(tester, p.h);
    await tester.tap(menuTile(oshId));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey<String>('payment-submit')));
    await tester.pumpAndSettle();
    // The poll (or an event) brings the preparing number
    await tester.pump(const Duration(seconds: 16));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey<String>('preparing-1')), findsOneWidget);
    await tester.tap(
      find.descendant(
        of: find.byKey(const ValueKey<String>('preparing-1')),
        matching: find.text('Tayyor'),
      ),
    );
    await tester.pumpAndSettle();
    expect(q.entries.values.single['status'], 'ready');
    expect(find.byKey(const ValueKey<String>('ready-1')), findsOneWidget);
  });

  testWidgets(
    'offline: the talon number comes from the block and rides on order.open',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend q) = await oshMarkazi();
      FakeSyncBackend(p.state);
      await pumpHome(tester, p.h);
      // One sale online (number 1), then the network goes
      await tester.tap(menuTile(oshId));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('payment-submit')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('counter-next')));
      await tester.pumpAndSettle();

      p.h.adapter.offline = true;
      await tester.tap(menuTile(somsaId));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      final SyncStore store = p.h.data.offline!.store;
      final List<SyncOperation> ops = store.pending;
      final SyncOperation open = ops.firstWhere(
        (SyncOperation o) => o.type == SyncOpType.orderOpen,
      );
      expect(open.payload['queue_number'], 2);
      expect(q.sentNumbers, <int?>[1]);
      expect(store.lastQueueNumber(q.businessDate), 2);
      expect(find.byType(PaymentPage), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey<String>('payment-submit')));
      await tester.pumpAndSettle();
      expect(
        find.descendant(
          of: find.byType(PosQueueNumberCard),
          matching: find.text('2'),
        ),
        findsOneWidget,
      );

      // Back online: the queue reaches the server, no timer is left
      p.h.adapter.offline = false;
      await drive(tester, p.h.data.offline!.syncNow());
      await drive(tester, p.h.data.shifts.current());
      expect(store.pendingCount, 0);
      expect(p.h.data.offline!.active, isFalse);
    },
  );

  testWidgets(
    'hybrid (kafe): «Tez kassa» and «Zal» side by side, the format picks the first',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend _) = await oshMarkazi(
        format: formatBlock(
          profile: 'kafe',
          label: 'Kafe',
          mode: 'hybrid',
          modules: const <String>[
            'quick_counter',
            'queue_numbers',
            'queue_calling',
            'customer_display',
            'service_charge',
            'kds',
          ],
        ),
      );
      await pumpHome(tester, p.h);
      expect(rail('Tez kassa'), findsOneWidget);
      expect(rail('Zal'), findsOneWidget);
      expect(find.byType(CounterPage), findsOneWidget);
      // Not in the format: no bookings or nasiya
      expect(rail('Bronlar'), findsNothing);
      expect(rail('Nasiya'), findsNothing);
      await openSection(tester, 'Zal');
      expect(find.text('1-xona'), findsOneWidget);
      // The hall map's «Tez kassa» button goes to the counter section
      await tester.tap(
        find.descendant(
          of: find.byType(FloorPage),
          matching: find.text('Tez kassa'),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.byType(CounterPage), findsOneWidget);
      expect(find.byType(OrderPage), findsNothing);
    },
  );

  testWidgets(
    'customer display (P6-14): the cart, the payment with its number, "Rahmat" and the idle screen',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend q) = await oshMarkazi(
        format: formatBlock(
          modules: const <String>[
            'quick_counter',
            'queue_numbers',
            'queue_calling',
            'customer_display',
          ],
        ),
      );
      await pumpHome(tester, p.h);
      List<String> states() => <String>[
        for (final Map<String, Object?> c in q.displayed) '${c['state']}',
      ];
      await tester.tap(menuTile(oshId));
      await tester.pumpAndSettle();
      await tester.pump(const Duration(milliseconds: 200));
      await tester.pumpAndSettle();
      expect(states().last, 'cart');
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      expect(states().last, 'payment');
      final Map<String, Object?> paying = q.displayed.last;
      expect(paying['queue_number'], 1);
      expect(paying['total'], '35000.00');
      await tester.tap(find.byKey(const ValueKey<String>('payment-submit')));
      await tester.pumpAndSettle();
      expect(states().last, 'paid');
      await tester.tap(find.byKey(const ValueKey<String>('counter-next')));
      await tester.pumpAndSettle();
      expect(states().last, 'idle');
    },
  );

  testWidgets(
    'table (restoran): as today — no «Tez kassa», starts at the hall map',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend _) = await oshMarkazi(
        format: formatBlock(
          profile: 'restoran',
          label: 'Restoran',
          mode: 'table',
          firstScreen: 'hall_map',
          modules: const <String>[
            'bookings',
            'service_charge',
            'kds',
            'customer_accounts',
          ],
        ),
      );
      await pumpHome(tester, p.h);
      expect(rail('Tez kassa'), findsNothing);
      expect(rail('Bronlar'), findsOneWidget);
      expect(find.byType(CounterPage), findsNothing);
      expect(find.text('1-xona'), findsOneWidget);
    },
  );

  testWidgets(
    'choyxona (no format block from an old server): unchanged — hall map first, no «Tez kassa» tab',
    (WidgetTester tester) async {
      final P22 p = P22();
      await pumpHome(tester, p.h);
      expect(rail('Tez kassa'), findsNothing);
      expect(rail('Zal'), findsOneWidget);
      expect(rail('Bronlar'), findsOneWidget);
      expect(rail('Nasiya'), findsOneWidget);
      expect(find.byType(CounterPage), findsNothing);
    },
  );

  testWidgets(
    'counter mode without tables: no hall map tab; nasiya is not offered at payment without the module',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend _) = await oshMarkazi(
        format: tamaddixonaFormat(),
      );
      p.state
        ..tables = <Map<String, Object?>>[]
        ..halls = <Map<String, Object?>>[];
      await pumpHome(tester, p.h);
      expect(rail('Zal'), findsNothing);
      expect(rail('Tez kassa'), findsOneWidget);
      await tester.tap(menuTile(oshId));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      expect(find.byType(PaymentPage), findsOneWidget);
      expect(find.text('Naqd'), findsOneWidget);
      expect(find.text('Nasiya'), findsNothing);
    },
  );

  group('phone (portrait Android, 390×844)', () {
    const Size phone = Size(390, 844);

    testWidgets(
      'phone mode: the shift opens in one tap; one-column grid, cart bar, pay; no printer — number and receipt on screen',
      (WidgetTester tester) async {
        final (P22 p, FakeQueueBackend _) = await oshMarkazi(
          format: tamaddixonaFormat(),
          shift: false,
        );
        p.state.printers = <Map<String, Object?>>[];
        await pumpHome(tester, p.h, size: phone);
        expect(find.byType(NavigationBar), findsOneWidget);
        expect(find.byType(CounterPage), findsOneWidget);

        await tester.tap(
          find.byKey(const ValueKey<String>('counter-open-shift')),
        );
        await tester.pumpAndSettle();
        expect(p.state.shift, isNotNull);
        expect(
          find.byKey(const ValueKey<String>('counter-open-shift')),
          findsNothing,
        );

        // One column: the counter's own category first
        await tester.tap(find.widgetWithText(ChoiceChip, 'Tez kassa'));
        await tester.pumpAndSettle();
        await tester.tap(menuTile(somsaId));
        await tester.pumpAndSettle();
        await tester.tap(menuTile(somsaId));
        await tester.pumpAndSettle();
        expect(find.text(som(16000)), findsWidgets);

        // The cart sheet
        await tester.tap(find.byKey(const ValueKey<String>('cart-bar')));
        await tester.pumpAndSettle();
        expect(find.text('Somsa'), findsWidgets);
        await tester.tap(
          find.byKey(const ValueKey<String>('counter-pay-sheet')),
        );
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const ValueKey<String>('payment-submit')));
        await tester.pumpAndSettle();

        expect(find.text("To'landi: ${som(16000)}"), findsOneWidget);
        // No queue numbers in this format: no number, no talon
        expect(find.byType(PosQueueNumberCard), findsNothing);
        await tester.tap(find.byKey(const ValueKey<String>('counter-receipt')));
        await tester.pumpAndSettle();
        expect(find.textContaining('Somsa'), findsWidgets);
        await tester.tapAt(const Offset(195, 40));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const ValueKey<String>('counter-next')));
        await tester.pumpAndSettle();
        expect(find.byKey(const ValueKey<String>('cart-bar')), findsOneWidget);

        // Close the shift: one tap to the Z page
        await tester.tap(
          find.byKey(const ValueKey<String>('counter-close-shift')),
        );
        await tester.pumpAndSettle();
        expect(find.byType(ZClosePage), findsOneWidget);
      },
    );
  });
}
