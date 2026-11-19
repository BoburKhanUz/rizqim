import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';

import 'offline_test.dart' show drive;
import 'support/harness.dart';
import 'support/p22.dart';

/// Wave M5 stage S1-e on the till's screen: the staff PIN session ends during an outage. The till stays on the work
/// screen (no PIN keypad it could not get past), says who goes on without a PIN, and asks for the PIN once the server
/// answers.
void main() {
  testWidgets("the PIN session ends during an outage: the banner says PIN'siz davom etilmoqda, the open room is paid; on "
      'first contact the PIN keypad is back', (WidgetTester tester) async {
    DateTime Function() clock = () => DateTime.utc(2026, 10, 22, 13);
    final SyncStore store = await SyncStore.open(
      MemorySyncStorage(),
      clock: () => clock(),
    );
    final P22 p = P22(offlineStore: store);
    clock = () => p.h.now;
    p.state.openShift();
    FakeSyncBackend(p.state);
    final String room = p.state.seedOrder(tableId: 1, guests: 2);
    await pumpHome(tester, p.h);
    await drive(tester, p.h.data.orders.get(room));
    final String name = p.h.data.session.staffName!;

    p.h.adapter.offline = true;
    await drive(tester, p.h.data.floor.load());
    await tester.pumpAndSettle();
    expect(find.textContaining("PIN'siz davom etilmoqda"), findsNothing);

    p.h.now = p.h.now.add(const Duration(hours: 13));
    // Any request with the staff token: the session used to be dropped here
    await drive(tester, p.h.data.session.refreshEntitlements());
    await tester.pump(const Duration(seconds: 31));

    expect(p.h.data.session.phase, PosSessionPhase.ready);
    expect(
      find.textContaining(
        "PIN'siz davom etilmoqda: $name. Faqat ochiq buyurtmalarni yakunlash va to'lov olish mumkin.",
      ),
      findsOneWidget,
    );

    final PosOrder order = await drive(tester, p.h.data.orders.get(room));
    final PosBilling billing = await drive(
      tester,
      p.h.data.billing.createBills(room, version: order.version),
    );
    final PosBill bill = billing.bills.single;
    final PosBillResult paid = await drive(
      tester,
      p.h.data.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: p.state.shift!['uuid']! as String,
      ),
    );
    expect(paid.order.isClosed, isTrue);

    p.h.adapter.offline = false;
    await drive(tester, p.h.data.offline!.syncNow());
    await tester.pumpAndSettle();
    expect(p.h.data.session.phase, PosSessionPhase.locked);
    expect(find.textContaining("PIN'siz davom etilmoqda"), findsNothing);
    expect(
      find.textContaining('Sessiya tugadi. PIN bilan qayta kiring.'),
      findsOneWidget,
    );
  });
}
