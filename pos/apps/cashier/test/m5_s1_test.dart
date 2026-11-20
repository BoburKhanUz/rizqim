import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/shell/limited_mode.dart';
import 'package:rizqim_cashier/src/shell/pairing_screen.dart';

import 'offline_test.dart' show drive;
import 'support/harness.dart';
import 'support/p22.dart';

/// Wave M5 stage S1 on the till's screen: the 24 h limit refuses only a new order (payment goes on), and a till the
/// server cut off says that its unsent operations were kept.
void main() {
  Future<P22> offlineTill() async {
    // The journal's clock is the till's (set once the harness exists)
    DateTime Function() clock = () => DateTime.utc(2026, 10, 22, 13);
    final SyncStore store = await SyncStore.open(
      MemorySyncStorage(),
      clock: () => clock(),
    );
    final P22 p = P22(offlineStore: store);
    clock = () => p.h.now;
    p.state.openShift();
    return p;
  }

  Future<void> goOffline(WidgetTester tester, P22 p) async {
    p.h.adapter.offline = true;
    await drive(tester, p.h.data.floor.load());
    await tester.pumpAndSettle();
  }

  PosLineInput tea() => PosLineInput(
    variantUuid: FakePosState.variantUuid(1),
    quantity: PosQuantity.parse('1'),
  );

  testWidgets(
    'after 25 h offline the banner refuses only NEW orders; the open room is billed and paid in cash',
    (WidgetTester tester) async {
      final P22 p = await offlineTill();
      final FakeSyncBackend sync = FakeSyncBackend(p.state);
      final String room = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      await drive(tester, p.h.data.orders.get(room));
      await goOffline(tester, p);
      p.h.now = p.h.now.add(const Duration(hours: 25));
      await tester.pump(const Duration(seconds: 31));

      expect(find.textContaining('yangi buyurtma ochilmaydi'), findsOneWidget);
      expect(
        find.textContaining("Ochiq buyurtmalar va to'lov ishlaydi"),
        findsOneWidget,
      );
      Object? refused;
      try {
        await drive(
          tester,
          p.h.data.orders.open(
            channel: PosChannel.takeaway,
            items: <PosLineInput>[tea()],
          ),
        );
      } on PosOfflineUnavailable catch (e) {
        refused = e;
      }
      expect(refused, isNotNull);

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
      expect(
        sync.results.values.map((Map<String, Object?> r) => r['status']),
        everyElement('applied'),
      );
      expect(p.state.orders[room]!['status'], 'delivered');
      expect(p.h.data.offline!.store.pendingCount, 0);
    },
  );

  testWidgets(
    'a till the server disconnected (401) keeps its unsent operations and says so on the pairing screen',
    (WidgetTester tester) async {
      final P22 p = await offlineTill();
      final FakeSyncBackend sync = FakeSyncBackend(p.state);
      final String room = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      await drive(tester, p.h.data.orders.get(room));
      await goOffline(tester, p);
      final PosOrder order = await drive(tester, p.h.data.orders.get(room));
      final PosBilling billing = await drive(
        tester,
        p.h.data.billing.createBills(room, version: order.version),
      );
      await drive(
        tester,
        p.h.data.billing.pay(
          billing.bills.single.uuid,
          tenders: <PosTenderInput>[
            PosTenderInput.cash(billing.bills.single.total),
          ],
          shiftUuid: p.state.shift!['uuid']! as String,
        ),
      );
      expect(p.h.data.offline!.store.pendingCount, 3);

      // The owner disconnects the till in the panel; the network comes back and the server answers 401
      p.state.paired = false;
      p.h.adapter.offline = false;
      await drive(tester, p.h.data.offline!.syncNow());
      for (var i = 0; i < 5; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      await tester.pumpAndSettle();

      expect(find.byType(PairingScreen), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('unsent-queue-notice')),
        findsOneWidget,
      );
      expect(
        find.textContaining("3 ta amal (shundan 1 ta to'lov) o'chirilmadi"),
        findsOneWidget,
      );
      expect(sync.received, isEmpty);
      expect(p.h.data.offline!.orphans.single.count, 3);
    },
  );

  test('the banner lines: unreadable journal lines and an orphan queue are named in plain words', () {
    expect(offlineWarningLines(const PosOfflineWarnings()), isEmpty);
    final List<String> lines = offlineWarningLines(
      PosOfflineWarnings(
        damagedLines: 2,
        orphans: <PosOrphanQueue>[
          PosOrphanQueue(
            key: 'orphan_1',
            setAsideAt: DateTime.utc(2026, 11, 19),
            operations: const <SyncOperation>[],
          ),
        ],
      ),
    );
    expect(lines.first, contains("2 ta qatori o'qilmadi"));
    expect(lines.first, contains('serverga yetmagan'));
    expect(lines.last, contains("o'chirilmadi"));
  });
}
