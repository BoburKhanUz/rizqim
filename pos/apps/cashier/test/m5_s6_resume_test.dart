import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';

import 'offline_test.dart' show drive;
import 'support/harness.dart';
import 'support/p22.dart';

/// Wave M5 stage S6-a on the till's screen (owner answer 3): the app is restarted while the internet is down. The PIN
/// keypad cannot reach the server, so it offers "Davom etish: (the last staff member)" (12 h after their PIN login at
/// most); pressed, the work screen opens with the "PIN'siz davom etilmoqda" banner, an open table is paid, and the
/// first server answer ends it: the PIN is asked again.
///
/// M5 S6 fixes (review 2): the shift is opened at the till AFTER the PIN login (the usual day), and the table is paid
/// through the real payment page, which takes the session's shift (`session.shift`, read back after the restart from
/// the till's own copy). The first server answer keeps the record: the offer comes back when the link drops again.
/// The run before: paired, Kamola enters her PIN at 13:00, opens the shift at the till, the till keeps its copies;
/// the network goes and the app is closed (plain pos_data, outside the widget clock). Opened again [after] later, the
/// server still out of reach: the same secure storage and journal. [windowOver]: the server records the
/// past-the-window mark (owner decision Q1, bootstrap `offline.staff_window_over`).
Future<({CashierHarness h, String room, MemoryKeyValueStore keys})> restarted(
  WidgetTester tester, {
  required Duration after,
  bool windowOver = false,
}) async {
  final MemoryKeyValueStore keys = MemoryKeyValueStore();
  final MemorySyncStorage disk = MemorySyncStorage();
  DateTime now = DateTime.utc(2026, 10, 22, 13);
  CashierHarness? later;
  // One clock for the fake server and the till of the second run (CashierHarness.now)
  final FakePosAdapter adapter = FakePosAdapter(clock: () => later?.now ?? now);
  FakeCashierBackend(adapter.state);
  final FakeSyncBackend sync = FakeSyncBackend(adapter.state);
  // No shift yet: it is opened at the till after the PIN login
  adapter.state
    ..offlineStaffResume = true
    ..offlineStaffWindowOver = windowOver;
  final String room = adapter.state.seedOrder(tableId: 1, guests: 2);

  await tester.runAsync(() async {
    final RizqimClient client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: keys,
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => now,
    );
    final PosData before = PosData(
      client,
      clock: () => now,
      offlineStore: await SyncStore.open(disk, clock: () => now),
    );
    await client.auth.pairTerminal('ABCD-EFGH');
    await client.auth.signInStaff(userId: 3, pin: adapter.state.pins[3]!);
    final PosBootstrap boot = await before.session.loadBootstrap();
    expect(boot.shift, isNull);
    await before.catalog.load(PosChannel.hall, menuVersion: boot.menuVersion);
    // As the shift page does (shift_page.dart): open, then updateShift
    final PosShift shift = await before.shifts.open(
      openingCash: const PosMoney.som(100000),
    );
    before.session.updateShift(shift.toSummary());
    await before.floor.load();
    await before.orders.openOrders();
    await client.auth.resumeWritten;
    await before.dispose();
  });
  sync.sessionStartedAt[3] = now;
  expect(keys.values.containsKey(AuthSession.staffResumeKey), isTrue);

  adapter.offline = true;
  now = now.add(after);
  final DateTime at = now;
  final SyncStore reopened = await SyncStore.open(
    disk,
    clock: () => later?.now ?? at,
  );
  final CashierHarness h = CashierHarness(
    offlineStore: reopened,
    store: keys,
    adapter: adapter,
  )..now = at;
  later = h;
  await drive(tester, h.client.auth.restore());
  await pumpCashier(tester, h);
  return (h: h, room: room, keys: keys);
}

void main() {
  testWidgets("after a restart with no server: Davom etish, the open table is paid, the link is back and the PIN is "
      'asked', (WidgetTester tester) async {
    final (:CashierHarness h, :String room, :MemoryKeyValueStore keys) =
        await restarted(tester, after: const Duration(hours: 3));

    expect(h.data.session.phase, PosSessionPhase.locked);
    expect(
      find.textContaining("Internet yo'q: PIN bilan kirib bo'lmaydi."),
      findsOneWidget,
    );
    expect(find.text('Davom etish: Kamola Karimova'), findsOneWidget);
    expect(
      find.textContaining(
        "faqat ochiq buyurtmalarni yakunlash va to'lov olish",
      ),
      findsOneWidget,
    );

    await tester.tap(find.text('Davom etish: Kamola Karimova'));
    await tester.pumpAndSettle();
    expect(h.data.session.phase, PosSessionPhase.ready);
    expect(h.data.session.staffResumed, isTrue);
    expect(
      find.textContaining(
        "PIN'siz davom etilmoqda: Kamola Karimova (ilova internetsiz qayta ishga tushdi)",
      ),
      findsOneWidget,
    );

    // The shift opened after the PIN login is known after the restart
    expect(h.data.session.shift?.uuid, h.state.shift!['uuid']);

    // Through the real payment page: the bill, "To'lash", cash for the whole amount
    await openBilling(tester, room);
    await tapButton(tester, 'Schyot chiqarish');
    await tapText(tester, "To'lash: ", contains: true);
    expect(find.byType(PaymentPage), findsOneWidget);
    await tapKey(tester, 'payment-submit');
    expect(find.byType(PaymentPage), findsNothing);
    final List<SyncOperation> queued = h.data.offline!.store.pending;
    expect(queued.map((SyncOperation o) => o.type), <String>[
      SyncOpType.billCreate,
      SyncOpType.billPay,
      SyncOpType.orderClose,
    ]);
    final SyncOperation pay = queued[1];
    expect(pay.payload['shift_uuid'], h.state.shift!['uuid']);
    expect(pay.payload[PosOffline.resumedKey], isTrue);
    expect(pay.staffId, 3);

    // The server answers: the PIN-less continuation is over at once
    h.adapter.offline = false;
    await drive(tester, h.data.offline!.syncNow());
    await tester.pumpAndSettle();
    expect(h.data.session.phase, PosSessionPhase.locked);
    expect(
      find.textContaining("Internet tiklandi: PIN'siz davom etish tugadi."),
      findsOneWidget,
    );
    expect(find.textContaining('Davom etish:'), findsNothing);
    expect(h.state.orders[room]!['status'], 'delivered');
    // Kept until a PIN login (M5 S6 review 2 MEDIUM): the link drops again, the offer is back
    expect(keys.values.containsKey(AuthSession.staffResumeKey), isTrue);
    h.adapter.offline = true;
    // The next request finds the link gone (the floor screen's own reload)
    try {
      await drive(tester, h.data.floor.load());
    } on PosException {
      // offline
    }
    await tester.pumpAndSettle();
    expect(h.data.connectivity.state.online, isFalse);
    expect(find.text('Davom etish: Kamola Karimova'), findsOneWidget);

    // Back for good: no offline timer is left
    h.adapter.offline = false;
    try {
      await drive(tester, h.data.session.loadBootstrap());
    } on PosException {
      // an answer (401: nobody is signed in)
    }
    await tester.pumpAndSettle();
    expect(find.textContaining('Davom etish:'), findsNothing);
  });

  testWidgets(
    'owner decision Q1: past the 12 h, still offline, the banner says only payment and closing are left; '
    'the table is paid through the payment page, marked',
    (WidgetTester tester) async {
      final (:CashierHarness h, :String room, keys: _) = await restarted(
        tester,
        after: const Duration(hours: 11, minutes: 50),
        windowOver: true,
      );
      await tester.tap(find.text('Davom etish: Kamola Karimova'));
      await tester.pumpAndSettle();
      expect(h.data.session.staffResumed, isTrue);

      // 12:05 after the PIN login
      h.now = h.now.add(const Duration(minutes: 15));
      // The banner is redrawn with the offline status (every 30 s while offline)
      await tester.pump(const Duration(seconds: 31));
      expect(h.data.session.staffResumeWindowOver, isTrue);
      expect(h.data.session.phase, PosSessionPhase.ready);
      expect(
        find.textContaining(
          "PIN bilan kirilganiga 12 soat o'tdi: endi faqat to'lov olish va ochiq buyurtmani yopish mumkin",
        ),
        findsOneWidget,
      );

      await openBilling(tester, room);
      await tapButton(tester, 'Schyot chiqarish');
      await tapText(tester, "To'lash: ", contains: true);
      expect(find.byType(PaymentPage), findsOneWidget);
      await tapKey(tester, 'payment-submit');
      expect(find.byType(PaymentPage), findsNothing);
      final List<SyncOperation> queued = h.data.offline!.store.pending;
      expect(queued.map((SyncOperation o) => o.type), <String>[
        SyncOpType.billCreate,
        SyncOpType.billPay,
        SyncOpType.orderClose,
      ]);
      for (final SyncOperation o in queued) {
        expect(o.payload[PosOffline.windowOverKey], isTrue, reason: o.type);
      }

      // The server answers: the PIN is asked; the payment waits for the manager (staff_unverified), the table is closed
      h.adapter.offline = false;
      await drive(tester, h.data.offline!.syncNow());
      await tester.pumpAndSettle();
      expect(h.data.session.phase, PosSessionPhase.locked);
      expect(h.state.orders[room]!['status'], 'delivered');
      try {
        await drive(tester, h.data.session.loadBootstrap());
      } on PosException {
        // an answer (401: nobody is signed in)
      }
      await tester.pumpAndSettle();
    },
  );
}
