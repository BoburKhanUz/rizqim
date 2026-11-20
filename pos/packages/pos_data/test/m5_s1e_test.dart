import 'dart:async';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'offline_test.dart' show OfflineHarness;

/// Wave M5 stage S1-e: the staff PIN session (12 h) ends while the till has no server contact. There is no PIN login
/// without the server, so the till goes on as that staff member ("PIN'siz davom etilmoqda"): open orders are finished
/// and paid, nothing new is started, and every operation queued this way is marked for the server.
void main() {
  late OfflineHarness h;
  late FakeSyncBackend sync;

  setUp(() async {
    h = await OfflineHarness.create();
    FakeCashierBackend(h.state);
    sync = FakeSyncBackend(h.state);
    h.state.openShift();
    await h.signIn();
  });

  tearDown(() => h.pos.dispose());

  PosLineInput line(int variantId, String qty) => PosLineInput(
    variantUuid: FakePosState.variantUuid(variantId),
    quantity: PosQuantity.parse(qty),
  );

  String shift() => h.state.shift!['uuid']! as String;

  Future<void> settle() => Future<void>.delayed(Duration.zero);

  /// Signed in at 13:00, the network goes, 13 h pass (the token lived 12 h), and the till asks the server for
  /// something (any request with the staff token: this is where the session used to be dropped).
  Future<String> outageOutlivesTheSession() async {
    final String room = h.state.seedOrder(tableId: 1, guests: 2);
    sync.sessionStartedAt[3] = h.clock.now;
    await h.warmUp();
    await h.goOffline();
    h.clock.now = h.clock.now.add(const Duration(hours: 13));
    expect(h.client.auth.staff!.isExpired(h.clock.now), isTrue);
    await h.pos.session.refreshEntitlements();
    return room;
  }

  // (M5 S1 review: 12 h after the PIN login, which is when the server's 12 h token ends, a round is no longer taken:
  // m5_s1_fix_test.dart)
  test('the staff token expires during an outage: an open order still takes the bill, a cash payment and the close; '
      'the operations are marked staff_continued', () async {
    final String room = await outageOutlivesTheSession();

    expect(h.pos.session.isSignedIn, isTrue);
    expect(h.pos.session.staffContinued, isTrue);
    expect(h.offline.continuedStaffName, isNotNull);

    final PosOrder before = await h.pos.orders.get(room);
    final PosBilling billing = await h.pos.billing.createBills(
      room,
      version: before.version,
    );
    final PosBill bill = billing.bills.single;
    final PosBillResult paid = await h.pos.billing.pay(
      bill.uuid,
      tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
      shiftUuid: shift(),
    );
    expect(paid.order.isClosed, isTrue);

    final List<SyncOperation> queued = h.offline.store.pending;
    expect(queued.map((SyncOperation o) => o.type), <String>[
      SyncOpType.billCreate,
      SyncOpType.billPay,
      SyncOpType.orderClose,
    ]);
    for (final SyncOperation o in queued) {
      expect(o.staffId, 3);
      expect(o.payload['staff_continued'], isTrue, reason: o.type);
    }
  });

  test('a continued staff member cannot open a new order', () async {
    await outageOutlivesTheSession();
    await expectLater(
      h.pos.orders.open(
        channel: PosChannel.takeaway,
        items: <PosLineInput>[line(1, '1')],
      ),
      throwsA(
        isA<PosOfflineUnavailable>()
            .having(
              (PosOfflineUnavailable e) => e.reason,
              'reason',
              PosOffline.continuedReason,
            )
            .having(
              (PosOfflineUnavailable e) => e.message,
              'message',
              contains("PIN'siz"),
            ),
      ),
    );
    expect(h.offline.store.pendingCount, 0);
  });

  test('operations queued before the token expired carry no marker', () async {
    final String room = h.state.seedOrder(tableId: 1, guests: 2);
    await h.warmUp();
    await h.goOffline();
    h.clock.now = h.clock.now.add(const Duration(hours: 2));
    final PosOrder before = await h.pos.orders.get(room);
    await h.pos.orders.addRound(
      room,
      version: before.version,
      items: <PosLineInput>[line(4, '1')],
    );
    expect(h.pos.session.staffContinued, isFalse);
    expect(h.offline.continuedStaffName, isNull);
    expect(
      h.offline.store.pending.single.payload.containsKey('staff_continued'),
      isFalse,
    );
  });

  test('when the link is back the queue is sent and the server applies the payment and the close (inside its 36 h staff '
      'window); the dead staff token is refused on its first request and the PIN is required', () async {
    final String room = await outageOutlivesTheSession();
    final PosOrder order = await h.pos.orders.get(room);
    final PosBilling billing = await h.pos.billing.createBills(
      room,
      version: order.version,
    );
    final PosBill bill = billing.bills.single;
    await h.pos.billing.pay(
      bill.uuid,
      tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
      shiftUuid: shift(),
    );
    final List<AuthEvent> events = <AuthEvent>[];
    final StreamSubscription<AuthEvent> sub = h.client.auth.events.listen(
      events.add,
    );
    addTearDown(sub.cancel);

    h.adapter.offline = false;
    await h.offline.syncNow();
    await settle();

    expect(h.offline.store.pendingCount, 0);
    expect(h.offline.store.errors, isEmpty);
    for (final Map<String, Object?> r in sync.results.values) {
      expect(r['status'], 'applied', reason: '${r['type']}: ${r['reason']}');
    }
    expect(h.client.auth.staff, isNull);
    expect(h.pos.session.phase, PosSessionPhase.locked);
    expect(
      events.where((AuthEvent e) => e.kind == AuthEventKind.staffSignedOut),
      hasLength(1),
    );
    expect(events.last.reason, 'expired');
  });

  test(
    'online, an expired staff token signs the staff member out (unchanged)',
    () async {
      await h.warmUp();
      h.clock.now = h.clock.now.add(const Duration(hours: 13));
      await h.pos.session.refreshEntitlements();
      await settle();
      expect(h.client.auth.staff, isNull);
      expect(h.pos.session.phase, PosSessionPhase.locked);
    },
  );
}
