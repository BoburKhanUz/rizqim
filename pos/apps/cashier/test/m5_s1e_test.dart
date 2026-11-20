import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';

import 'offline_test.dart' show drive;
import 'support/harness.dart';
import 'support/p22.dart';

/// Wave M5 stage S1-e on the till's screen: the staff PIN session ends during an outage. The till stays on the work
/// screen (no PIN keypad it could not get past), says who goes on without a PIN, and asks for the PIN once the server
/// refuses the dead token. M5 S1 review: when the link goes again before a PIN login succeeded, the staff member goes
/// on (a flapping link never leaves the till at a keypad nobody can pass).
void main() {
  testWidgets("the PIN session ends during an outage: the banner says PIN'siz davom etilmoqda, the open room is paid; "
      'when the server refuses the token the PIN keypad is back; the link goes again and the staff member goes '
      'on', (WidgetTester tester) async {
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
    final String second = p.state.seedOrder(tableId: 2, guests: 2);
    await pumpHome(tester, p.h);
    await drive(tester, p.h.data.orders.get(room));
    await drive(tester, p.h.data.orders.get(second));
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
        "PIN'siz davom etilmoqda: $name. PIN bilan kirilganiga 12 soatdan oshdi: faqat schyot, to'lov va "
        "buyurtmani yopish mumkin (yangi taom qo'shilmaydi, taom o'chirilmaydi).",
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

    // The link goes again before anybody could enter a PIN: the till finds out and the staff member goes on
    p.h.adapter.offline = true;
    await drive(
      tester,
      p.h.data.floor.load().then<void>((_) {}, onError: (Object _) {}),
    );
    await tester.pumpAndSettle();
    expect(p.h.data.session.phase, PosSessionPhase.ready);
    expect(p.h.data.session.staffContinued, isTrue);
    expect(
      find.textContaining("PIN'siz davom etilmoqda: $name"),
      findsOneWidget,
    );
    final PosOrder next = await drive(tester, p.h.data.orders.get(second));
    final PosBilling nextBilling = await drive(
      tester,
      p.h.data.billing.createBills(second, version: next.version),
    );
    final PosBillResult nextPaid = await drive(
      tester,
      p.h.data.billing.pay(
        nextBilling.bills.single.uuid,
        tenders: <PosTenderInput>[
          PosTenderInput.cash(nextBilling.bills.single.total),
        ],
        shiftUuid: p.state.shift!['uuid']! as String,
      ),
    );
    expect(nextPaid.order.isClosed, isTrue);

    // The link is back for good: the sale reaches the server and the PIN is asked again
    p.h.adapter.offline = false;
    await drive(tester, p.h.data.offline!.syncNow());
    await tester.pumpAndSettle();
    expect(p.state.orders[second]!['closed_at'], isNotNull);
    expect(p.h.data.session.phase, PosSessionPhase.locked);
  });
}
