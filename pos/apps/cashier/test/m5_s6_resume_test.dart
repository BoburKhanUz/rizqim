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
void main() {
  testWidgets("after a restart with no server: Davom etish, the open table is paid, the link is back and the PIN is "
      'asked', (WidgetTester tester) async {
    final MemoryKeyValueStore keys = MemoryKeyValueStore();
    final MemorySyncStorage disk = MemorySyncStorage();
    DateTime now = DateTime.utc(2026, 10, 22, 13);
    final FakePosAdapter adapter = FakePosAdapter(clock: () => now);
    FakeCashierBackend(adapter.state);
    final FakeSyncBackend sync = FakeSyncBackend(adapter.state);
    // No shift yet: it is opened at the till after the PIN login
    adapter.state.offlineStaffResume = true;
    final String room = adapter.state.seedOrder(tableId: 1, guests: 2);

    // The run before: paired, Kamola enters her PIN at 13:00, the till keeps its copies; the network goes and the app
    // is closed (plain pos_data, outside the widget clock)
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

    // Opened again 3 h later, the server still out of reach: the same secure storage and journal
    adapter.offline = true;
    now = now.add(const Duration(hours: 3));
    final DateTime later = now;
    final SyncStore reopened = await SyncStore.open(disk, clock: () => later);
    final CashierHarness h = CashierHarness(
      offlineStore: reopened,
      store: keys,
      adapter: adapter,
    )..now = later;
    await drive(tester, h.client.auth.restore());
    await pumpCashier(tester, h);

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
}
