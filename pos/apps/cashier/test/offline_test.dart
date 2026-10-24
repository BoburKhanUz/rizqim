import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:rizqim_cashier/src/features/billing/billing_page.dart';
import 'package:rizqim_cashier/src/features/floor/floor_page.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

/// P3-31: the cashier in the limited mode (docs/POS_SPEC.md §5): it goes offline, keeps selling (a walk-in and a round
/// on a room opened before the outage), takes cash, reconnects and everything reaches the server once; sync errors for
/// the manager; no Z while the queue is not empty.
/// Runs [future] to the end, moving the fake clock (Dio and the sync sender wait on timers).
Future<T> drive<T>(WidgetTester tester, Future<T> future) async {
  var done = false;
  late T value;
  Object? error;
  unawaited(
    future.then(
      (T v) {
        value = v;
        done = true;
      },
      onError: (Object e) {
        error = e;
        done = true;
      },
    ),
  );
  for (var i = 0; i < 300 && !done; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
  if (!done) throw StateError('did not finish');
  if (error != null) throw error!;
  await tester.pumpAndSettle();
  return value;
}

void main() {
  Future<P22> offlineTill(WidgetTester tester) async {
    final SyncStore store = await SyncStore.open(MemorySyncStorage());
    final P22 p = P22(offlineStore: store);
    p.state.openShift();
    return p;
  }

  /// The network goes; the next request (the floor) finds out and the till switches to the limited mode.
  Future<void> goOffline(WidgetTester tester, P22 p) async {
    p.h.adapter.offline = true;
    await drive(tester, p.h.data.floor.load());
    await tester.pumpAndSettle();
  }

  Future<void> reconnect(WidgetTester tester, P22 p) async {
    p.h.adapter.offline = false;
    await drive(tester, p.h.data.offline!.syncNow());
    await tester.pumpAndSettle();
  }

  /// Back online with an empty queue: no offline timer is left (probe, age tick and retry stop by themselves).
  void noTimersLeft(P22 p) {
    expect(p.h.data.offline!.active, isFalse);
    expect(p.h.data.offline!.store.pendingCount, 0);
  }

  testWidgets(
    'P3-35: a round synced at the server price shows a notice until the cashier dismisses it',
    (WidgetTester tester) async {
      final P22 p = await offlineTill(tester);
      final FakeSyncBackend sync = FakeSyncBackend(p.state);
      p.state.seedOrder(tableId: 1, guests: 3);
      await pumpHome(tester, p.h);
      await goOffline(tester, p);
      await openPlace(tester, '1-xona');
      await tester.tap(menuTile(1)); // Choy
      await tester.pumpAndSettle();
      await tester.tap(sendButton);
      await tester.pumpAndSettle();
      Navigator.of(tester.element(find.byType(OrderPage))).pop();
      await tester.pumpAndSettle();
      sync.flagNext[SyncOpType.orderRound] = <Map<String, Object?>>[
        <String, Object?>{
          'flag': 'offline_price_mismatch',
          'line_id': 'l1',
          'name': 'Choy',
          'device_price': '4000.00',
          'server_price': '5000.00',
        },
      ];

      await reconnect(tester, p);
      expect(
        find.byKey(const ValueKey<String>('sync-price-banner')),
        findsOneWidget,
      );
      expect(
        find.textContaining("'Choy' offlayn 4000 so'mdan"),
        findsOneWidget,
      );
      await tester.tap(find.text('Tushunarli'));
      await tester.pumpAndSettle();
      expect(
        find.byKey(const ValueKey<String>('sync-price-banner')),
        findsNothing,
      );
      noTimersLeft(p);
    },
  );

  testWidgets(
    'offline: a walk-in and a room round are sold, cash is taken, and on reconnect everything is synced once',
    (WidgetTester tester) async {
      final P22 p = await offlineTill(tester);
      final FakeSyncBackend sync = FakeSyncBackend(p.state);
      final String room = p.state.seedOrder(tableId: 1, guests: 3);
      await pumpHome(tester, p.h);
      await goOffline(tester, p);

      expect(find.text('Internetsiz rejim: sotuv davom etadi'), findsOneWidget);
      expect(find.textContaining("Navbat bo'sh"), findsOneWidget);

      // A round on the room opened before the outage
      await openPlace(tester, '1-xona');
      expect(find.byType(OrderPage), findsOneWidget);
      await tester.tap(menuTile(1)); // Choy
      await tester.pumpAndSettle();
      await tester.tap(sendButton);
      await tester.pumpAndSettle();
      expect(find.text('2-bosqich'), findsOneWidget);
      expect(p.h.data.offline!.store.pendingCount, 1);
      Navigator.of(tester.element(find.byType(OrderPage))).pop();
      await tester.pumpAndSettle();
      expect(find.textContaining('Navbatda 1 ta amal'), findsOneWidget);

      // A walk-in from "Tez kassa": opened offline with the till's local number, one bill, cash
      await tester.tap(find.text('Tez kassa').first);
      await tester.pumpAndSettle();
      await tester.tap(menuTile(1));
      await tester.pumpAndSettle();
      await tester.tap(sendButton);
      await tester.pumpAndSettle();
      expect(find.textContaining('#K1-001'), findsOneWidget);
      // The snackbar ("Buyurtma ochildi va yuborildi") goes away first
      await tester.pump(const Duration(seconds: 5));
      await tester.pumpAndSettle();
      await tapButton(tester, 'Schyot');
      expect(find.byType(BillingPage), findsOneWidget);
      expect(
        find.textContaining("Hisobni bo'lish internet tiklangach"),
        findsOneWidget,
      );
      await tapButton(tester, 'Schyot chiqarish');
      await tapText(tester, "To'lash: ", contains: true);
      expect(find.textContaining('faqat naqd va karta'), findsOneWidget);
      expect(find.widgetWithText(ChoiceChip, 'Nasiya'), findsNothing);
      await tapKey(tester, 'payment-submit');
      expect(find.byType(FloorPage), findsOneWidget);
      expect(p.drawerKicks, 1);
      // No payment slip offline: the sale receipt comes after the sync
      expect(
        p.receipts.where((String r) => r.contains('SOTUV CHEKI')),
        isEmpty,
      );
      expect(p.h.data.offline!.store.pendingCount, 5);

      // Back online: the queue reaches the server in order, once
      await reconnect(tester, p);
      expect(sync.received.map((Map<String, Object?> o) => o['type']), <String>[
        'order.round',
        'order.open',
        'bill.create',
        'bill.pay',
        'order.close',
      ]);
      expect(
        sync.results.values.every(
          (Map<String, Object?> r) => r['status'] == 'applied',
        ),
        isTrue,
      );
      expect(p.h.data.offline!.store.pendingCount, 0);
      expect(find.textContaining('Internetsiz rejim'), findsNothing);
      expect(
        items(p.state.orders[room]!)
            .where((Map<String, Object?> i) => i['round_no'] == 2),
        hasLength(1),
      );
      final String clientId =
          (sync.received[1]['payload']! as Map<String, Object?>)['order']!
              as String;
      final String walkIn = sync.uuids[clientId]!;
      expect(p.h.data.offline!.store.resolve(clientId), walkIn);
      expect(p.state.orders[walkIn]!['status'], 'delivered');
      // The sale receipt of the offline payment is printed once it is registered
      expect(
        p.receipts.where((String r) => r.contains('SOTUV CHEKI')),
        hasLength(1),
      );

      noTimersLeft(p);
    },
  );

  testWidgets(
    'a rejected offline operation shows in "Sinxronlash xatolari" and the manager resolves it',
    (WidgetTester tester) async {
      final P22 p = await offlineTill(tester);
      final FakeSyncBackend sync = FakeSyncBackend(p.state);
      final String room = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h, name: 'Malika Rahimova', pin: '5926');
      await drive(tester, p.h.data.orders.get(room));
      await goOffline(tester, p);
      final PosOrder order = await drive(tester, p.h.data.orders.get(room));
      await drive(
        tester,
        p.h.data.orders.addRound(
          room,
          version: order.version,
          items: <PosLineInput>[
            PosLineInput(
              variantUuid: FakePosState.variantUuid(1),
              quantity: PosQuantity.parse('1'),
            ),
          ],
        ),
      );
      sync.rejectNext['order.round'] = 'order_closed';
      await reconnect(tester, p);

      expect(find.textContaining('Sinxronlash xatolari: 1'), findsOneWidget);
      await tester.tap(find.textContaining('Xatolar (1)'));
      await tester.pumpAndSettle();
      expect(find.text('Sinxronlash xatolari'), findsOneWidget);
      expect(find.text('Rad etildi'), findsOneWidget);
      expect(find.textContaining("Bosqich qo'shildi"), findsOneWidget);

      await tester.tap(find.text('Hal qilindi'));
      await tester.pumpAndSettle();
      expect(sync.resolved, hasLength(1));
      expect(find.textContaining("Xato yo'q"), findsOneWidget);

      noTimersLeft(p);
    },
  );

  testWidgets('Z is blocked while the offline queue is not empty', (
    WidgetTester tester,
  ) async {
    final P22 p = await offlineTill(tester);
    FakeSyncBackend(p.state);
    await pumpHome(tester, p.h);
    await openSection(tester, 'Smena');
    await goOffline(tester, p);
    await drive(
      tester,
      p.h.data.orders.open(
        channel: PosChannel.takeaway,
        items: <PosLineInput>[
          PosLineInput(
            variantUuid: FakePosState.variantUuid(1),
            quantity: PosQuantity.parse('1'),
          ),
        ],
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const ValueKey<String>('z-blocked')), findsOneWidget);
    expect(
      find.textContaining("navbatda 1 ta amal serverga yetmagan"),
      findsOneWidget,
    );
    RzButton z() =>
        tester.widget<RzButton>(find.byKey(const ValueKey<String>('z-open')));
    expect(z().onPressed, isNull);

    await reconnect(tester, p);
    expect(find.byKey(const ValueKey<String>('z-blocked')), findsNothing);
    expect(z().onPressed, isNotNull);

    noTimersLeft(p);
  });

  testWidgets(
    'an offline sale receipt prints once: paper out at sync is retried from the banner, a new sign-in does not reprint',
    (WidgetTester tester) async {
      final P22 p = await offlineTill(tester);
      FakeSyncBackend(p.state);
      await pumpHome(tester, p.h);
      await goOffline(tester, p);
      final PosOrder o = await drive(
        tester,
        p.h.data.orders.open(
          channel: PosChannel.takeaway,
          items: <PosLineInput>[
            PosLineInput(
              variantUuid: FakePosState.variantUuid(1),
              quantity: PosQuantity.parse('1'),
            ),
          ],
        ),
      );
      final PosBill bill = (await drive(
        tester,
        p.h.data.billing.createBills(o.uuid, version: o.version),
      )).bills.single;
      await drive(
        tester,
        p.h.data.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
          shiftUuid: p.h.data.session.shift?.uuid,
        ),
      );
      int sales() =>
          p.receipts.where((String r) => r.contains('SOTUV CHEKI')).length;

      // The receipt printer is out of paper when the queue reaches the server
      p.h
          .printerFor('server-1')
          .failNext(
            1,
            const PrinterException(PrinterErrorKind.paperOut, "Qog'oz tugadi"),
          );
      await reconnect(tester, p);
      expect(sales(), 0);
      expect(find.text('Chek chop etilmadi (1)'), findsOneWidget);
      expect(p.h.data.offline!.salesToPrint, hasLength(1));

      await tapButton(tester, 'Qayta urinish');
      expect(sales(), 1);
      expect(find.text('Chek chop etilmadi (1)'), findsNothing);
      expect(p.h.data.offline!.salesToPrint, isEmpty);

      // Locked and signed in again: the old receipt does not come out again
      await drive(tester, p.h.client.auth.signOutStaff());
      await tester.tap(find.text('Kamola Karimova'));
      await tester.pumpAndSettle();
      await typePin(tester, p.state.pins[3]!);
      await tester.pumpAndSettle();
      expect(find.byType(FloorPage), findsOneWidget);
      expect(sales(), 1);

      noTimersLeft(p);
    },
  );

  testWidgets(
    'the till is not unpaired while offline sales are queued; unpairing clears the journal',
    (WidgetTester tester) async {
      final P22 p = await offlineTill(tester);
      FakeSyncBackend(p.state);
      await pumpHome(tester, p.h, name: 'Malika Rahimova', pin: '5926');
      await goOffline(tester, p);
      await drive(
        tester,
        p.h.data.orders.open(
          channel: PosChannel.takeaway,
          items: <PosLineInput>[
            PosLineInput(
              variantUuid: FakePosState.variantUuid(1),
              quantity: PosQuantity.parse('1'),
            ),
          ],
        ),
      );
      Future<void> unpair() async {
        await tester.tap(find.byTooltip('Xodim menyusi'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Terminalni uzish'));
        await tester.pumpAndSettle();
      }

      await unpair();
      expect(find.text("Terminalni uzib bo'lmaydi"), findsOneWidget);
      expect(find.textContaining('Navbatda 1 ta amal'), findsWidgets);
      await tapButton(tester, 'Tushunarli');
      expect(p.h.client.auth.terminal, isNotNull);
      expect(p.h.data.offline!.store.pendingCount, 1);

      await reconnect(tester, p);
      await unpair();
      await tapButton(tester, 'Uzish');
      expect(p.h.client.auth.terminal, isNull);
      expect(p.h.data.offline!.store.pendingCount, 0);
      expect(p.h.data.offline!.store.docKeys(), isEmpty);
    },
  );
}
