import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'offline_test.dart' show OfflineHarness;

/// Wave M5 stage S2, review fixes on the till (owner decision of 20.11.2026, docs/scale/M5_DESIGN.md 3 and 8.1):
/// offline EVERYONE gives at most the branch's cashier limit; a promo discount is known by the server's
/// `discount_promo`, never by the reason's text; an online request whose answer was lost and that the till would
/// refuse stays a network error; the fake sync backend refuses what the server refuses.
void main() {
  late OfflineHarness h;
  late FakeSyncBackend sync;
  late FakeCashierBackend cashier;

  Future<void> start({int userId = 3}) async {
    h = await OfflineHarness.create();
    cashier = FakeCashierBackend(h.state);
    sync = FakeSyncBackend(h.state);
    h.state.offlineDiscount = true;
    h.state.openShift();
    await h.signIn(userId: userId);
  }

  tearDown(() => h.pos.dispose());

  /// Room 1: dishes 190 000, service 10 % after the discount, the room fee 300 000.
  Future<PosOrder> room({bool offline = true}) async {
    final String uuid = h.state.seedOrder(tableId: 1, guests: 4);
    await h.warmUp();
    final PosOrder order = await h.pos.orders.get(uuid);
    if (offline) await h.goOffline();
    return order;
  }

  Future<PosOrder> give(
    PosOrder order,
    String value, {
    PosDiscountKind kind = PosDiscountKind.percent,
    String? reason = 'Doimiy mijoz',
  }) => h.pos.orders.discount(
    order.uuid,
    version: order.version,
    kind: kind,
    value: value,
    reason: reason,
  );

  Future<PosOfflineUnavailable> refused(Future<Object?> Function() call) async {
    final int queued = h.offline.store.pendingCount;
    try {
      await call();
    } on PosOfflineUnavailable catch (e) {
      expect(h.offline.store.pendingCount, queued);
      return e;
    }
    fail('expected PosOfflineUnavailable');
  }

  Map<String, Object?> staff(int id) =>
      h.state.staff.firstWhere((Map<String, Object?> s) => s['id'] == id);

  Map<String, Object?> result(String type) => sync.results.values.firstWhere(
    (Map<String, Object?> r) => r['type'] == type,
  );

  group('F2-1: offline everyone has the cashier limit', () {
    test(
      'a holder of orders.discount gives the branch limit offline and no more',
      () async {
        await start(userId: 2);
        final PosOrder order = await room();
        expect(h.offline.discountUnlimited, isFalse);
        expect(h.offline.discountLimitPercent, '10');

        final PosOfflineUnavailable e = await refused(() => give(order, '50'));
        expect(e.reason, PosOffline.discountLimitReason);
        expect(
          e.message,
          "Internetsiz chegirma: filial chegarasi 10% gacha. Kattarog'i internet qaytgach beriladi.",
        );
        // An amount is measured as typed: 190 000 x 10 % = 19 000
        expect(
          (await refused(
            () => give(order, '19000.01', kind: PosDiscountKind.amount),
          )).reason,
          PosOffline.discountLimitReason,
        );
        expect(
          h.offline.discountRefusal(
            order.uuid,
            kind: PosDiscountKind.percent,
            value: '10.01',
            reason: 'Bayram',
          ),
          e.message,
        );

        final PosOrder given = await give(order, '10');
        expect(given.discount, const PosMoney.som(19000));
        h.adapter.offline = false;
        expect((await h.offline.syncNow()).drained, isTrue);
        expect(sync.results.values.single['status'], 'applied');
      },
    );

    test('a cashier above the limit reads the same sentence', () async {
      await start();
      final PosOrder order = await room();
      expect(h.offline.discountLimitPercent, '10');
      final PosOfflineUnavailable e = await refused(() => give(order, '10.01'));
      expect(e.reason, PosOffline.discountLimitReason);
      expect(
        e.message,
        "Internetsiz chegirma: filial chegarasi 10% gacha. Kattarog'i internet qaytgach beriladi.",
      );
    });

    for (final int user in <int>[2, 3]) {
      test(
        'no limit configured: no offline discount for staff $user, with the reason; taking one off still '
        'works',
        () async {
          await start(userId: user);
          final PosOrder plain = await room(offline: false);
          final PosOrder online = await give(plain, '5');
          h.state.cashierDiscountMaxPercent = null;
          await h.pos.session.loadBootstrap();
          await h.goOffline();
          expect(h.offline.discountLimitPercent, isNull);
          expect(h.offline.discountUnlimited, isFalse);
          final PosOfflineUnavailable e = await refused(
            () => give(online, '1'),
          );
          expect(e.reason, PosOffline.discountLimitReason);
          expect(e.message, contains('chegarasi belgilanmagan'));
          expect(e.message, contains('internetsiz chegirma berilmaydi'));

          final PosOrder removed = await give(online, '0', reason: null);
          expect(removed.discount, PosMoney.zero);
          h.adapter.offline = false;
          expect((await h.offline.syncNow()).drained, isTrue);
          expect(sync.results.values.single['status'], 'applied');
        },
      );
    }

    test(
      'a waiter (no orders.discount, no pos.take_payment) still gives none',
      () async {
        await start(userId: 4);
        final PosOrder order = await room();
        expect(h.offline.discountLimitPercent, isNull);
        final PosOfflineUnavailable e = await refused(() => give(order, '5'));
        expect(e.reason, PosOffline.discountLimitReason);
        expect(e.message, contains('menejer'));
      },
    );
  });

  group('F2-2: promo is the server\'s word (discount_promo), never the reason\'s text', () {
    Map<String, Object?> cached({required Object? promo, String? reason}) =>
        <String, Object?>{
          'uuid': 'u',
          'status': 'preparing',
          'channel': 'pos_takeaway',
          'version': 3,
          'discount': '7000.00',
          'discount_kind': 'percent',
          'discount_value': '10.00',
          'discount_reason': reason,
          'discount_promo': ?promo,
          'charges': <String, Object?>{},
          'items': <Object?>[
            <String, Object?>{
              'id': 1,
              'kitchen_status': 'sent',
              'total_price': '100000.00',
              'quantity': '1',
              'unit': 'piece',
            },
          ],
        };

    OfflineOrderState recalculated(Map<String, Object?> order) =>
        OfflineOrderState.existing('u', order, null, const PosOfflineSettings())
          ..recalculate();

    test('a hand discount whose typed reason starts with "promo:" is worked out on the till and follows the '
        'rounds', () async {
      await start();
      final PosOrder order = await room();
      final PosOrder given = await give(
        order,
        '10',
        reason: 'promo: bayram aksiyasi',
      );
      expect(given.discount, const PosMoney.som(19000));
      expect(given.totalPrice, const PosMoney.som(488100));
      expect(
        h.offline.store.pending.single.payload['expected_discount'],
        '19000.00',
      );
      final PosOrder more = await h.pos.orders.addRound(
        order.uuid,
        version: given.version,
        items: <PosLineInput>[
          PosLineInput(
            variantUuid: FakePosState.variantUuid(1),
            quantity: PosQuantity.parse('1'),
          ),
        ],
        send: true,
      );
      expect(more.discount, const PosMoney.som(19500));
      h.adapter.offline = false;
      expect((await h.offline.syncNow()).drained, isTrue);
      expect(
        sync.results.values.map((Map<String, Object?> r) => r['status']),
        everyElement('applied'),
      );
      expect(
        (result(SyncOpType.orderDiscount)['refs']! as Map)['flags'],
        hasLength(1),
        reason: 'no discount_mismatch',
      );
    });

    test('the cached order says discount_promo: the stored amount stands whatever the reason reads', () {
      final OfflineOrderState s = recalculated(
        cached(promo: true, reason: 'Yozgi aksiya'),
      );
      expect(s.order['discount'], '7000.00');
      expect(s.order['total_price'], '93000.00');
    });

    test('discount_promo false: a reason "promo:YOZ" is just a text, the percent is taken from the lines', () {
      final OfflineOrderState s = recalculated(
        cached(promo: false, reason: 'promo:YOZ'),
      );
      expect(s.order['discount'], '10000.00');
      expect(s.order['total_price'], '90000.00');
    });

    test('no discount_promo key (cached from an older server): the stored amount stands, no recompute', () {
      final OfflineOrderState s = recalculated(
        cached(promo: null, reason: 'Doimiy mijoz'),
      );
      expect(s.order['discount'], '7000.00');
      expect(s.order['total_price'], '93000.00');
    });

    test('a promo code set online stays the server\'s amount offline; a hand discount given offline clears the '
        'mark', () async {
      await start(userId: 2);
      final PosOrder plain = await room(offline: false);
      cashier.seedPromo('YOZ', percent: '10.00');
      final PosOrder promo = await h.pos.vouchers.applyPromo(
        plain.uuid,
        version: plain.version,
        code: 'yoz',
      );
      expect(promo.raw['discount_promo'], isTrue);
      // The server's own amount (a code has rules the till does not know): pretend it capped it
      h.state.orders[plain.uuid]!['discount'] = '15000.00';
      await h.pos.orders.get(plain.uuid);
      await h.goOffline();
      final PosOrder kept = await h.pos.orders.addRound(
        plain.uuid,
        version: promo.version,
        items: <PosLineInput>[
          PosLineInput(
            variantUuid: FakePosState.variantUuid(1),
            quantity: PosQuantity.parse('1'),
          ),
        ],
        send: true,
      );
      expect(kept.discount, const PosMoney.som(15000));

      final PosOrder hand = await give(kept, '5', reason: 'Doimiy mijoz');
      expect(hand.raw['discount_promo'], isFalse);
      // 195 000 x 5 % = 9 750
      expect(hand.discount, const PosMoney.som(9750));
    });

    test('the fake server: a hand discount clears discount_promo', () async {
      await start(userId: 2);
      final PosOrder plain = await room(offline: false);
      expect(plain.raw['discount_promo'], isFalse);
      cashier.seedPromo('YOZ', percent: '10.00');
      final PosOrder promo = await h.pos.vouchers.applyPromo(
        plain.uuid,
        version: plain.version,
        code: 'YOZ',
      );
      expect(promo.raw['discount_promo'], isTrue);
      final PosOrder hand = await give(promo, '5');
      expect(hand.raw['discount_promo'], isFalse);
    });
  });

  group('F2-3: an online discount whose answer was lost', () {
    test('what the till would refuse is not refused and not queued: the network error stands', () async {
      await start();
      final PosOrder order = await room(offline: false);
      // The request fails; the till is not in the offline mode yet
      h.adapter.offline = true;
      await expectLater(give(order, '20'), throwsA(isA<PosNetworkException>()));
      expect(h.offline.store.pendingCount, 0);
    });

    test(
      'what the till may give goes on through the queue, as before',
      () async {
        await start();
        final PosOrder order = await room(offline: false);
        h.adapter.offline = true;
        final PosOrder given = await give(order, '10');
        expect(given.discount, const PosMoney.som(19000));
        expect(h.offline.store.pending.single.type, SyncOpType.orderDiscount);
      },
    );

    test('in the offline mode a refusal is still said in words', () async {
      await start();
      final PosOrder order = await room();
      expect(
        (await refused(() => give(order, '20'))).reason,
        PosOffline.discountLimitReason,
      );
    });
  });

  group('F2-4: the fake sync backend mirrors the server', () {
    Map<String, Object?> op(
      String id,
      int seq,
      String order, {
      int staffId = 2,
      Map<String, Object?> more = const <String, Object?>{},
    }) => <String, Object?>{
      'op_id': id,
      'seq': seq,
      'type': 'order.discount',
      'staff_id': staffId,
      'occurred_at': h.clock.now.toUtc().toIso8601String(),
      'payload': <String, Object?>{
        'order': order,
        'kind': 'percent',
        'value': '10.00',
        'reason': 'Bayram',
        'expected_discount': '19000.00',
        ...more,
      },
    };

    Map<String, Object?> post(Map<String, Object?> one) {
      h.state.terminalRoutes!(
        'POST',
        '/v1/pos/sync',
        const <String, String>{},
        <String, Object?>{
          'operations': <Object?>[one],
        },
      );
      return sync.results[one['op_id']]!;
    }

    test('the cap is the cashier limit for a manager too (cause offline_limit); none configured: none', () async {
      await start(userId: 2);
      final String order = h.state.seedOrder(tableId: 1, guests: 4);
      final Map<String, Object?> above = post(
        op('a', 1, order, more: <String, Object?>{'value': '10.01'}),
      );
      expect(above['status'], 'rejected');
      expect(above['reason'], 'discount_not_allowed');
      expect((above['refs']! as Map)['cause'], 'offline_limit');
      expect(above['message'], contains('filial chegarasidan (10%)'));
      expect(h.state.orders[order]!['discount'], '0.00');

      final Map<String, Object?> amount = post(
        op(
          'b',
          2,
          order,
          more: <String, Object?>{'kind': 'amount', 'value': '19000.01'},
        ),
      );
      expect((amount['refs']! as Map)['cause'], 'offline_limit');

      expect(post(op('c', 3, order))['status'], 'applied');
      expect(h.state.orders[order]!['discount'], '19000.00');

      h.state.cashierDiscountMaxPercent = null;
      final Map<String, Object?> none = post(
        op('d', 4, order, more: <String, Object?>{'value': '1.00'}),
      );
      expect((none['refs']! as Map)['cause'], 'offline_limit');
      expect(none['message'], contains('chegarasi belgilanmagan'));
      // Taking it off is not capped
      expect(
        post(
          op('e', 5, order, more: <String, Object?>{'value': '0.00'}),
        )['status'],
        'applied',
      );
    });

    test('a cashier above a limit lowered during the outage: offline_limit, not approval_required', () async {
      await start();
      final PosOrder order = await room();
      await give(order, '10');
      h.state.cashierDiscountMaxPercent = '5.00';
      h.adapter.offline = false;
      expect((await h.offline.syncNow()).drained, isTrue);
      final Map<String, Object?> r = sync.results.values.single;
      expect(r['reason'], 'discount_not_allowed');
      expect((r['refs']! as Map)['cause'], 'offline_limit');
    });

    test('staff_continued: true is refused (cause staff_continued)', () async {
      await start(userId: 2);
      final String order = h.state.seedOrder(tableId: 1, guests: 4);
      final Map<String, Object?> r = post(
        op('a', 1, order, more: <String, Object?>{'staff_continued': true}),
      );
      expect(r['status'], 'rejected');
      expect(r['reason'], 'discount_not_allowed');
      expect(r['refs'], <String, Object?>{
        'cause': 'staff_continued',
        'kind': 'percent',
        'value': '10.00',
        'expected_discount': '19000.00',
      });
      expect(h.state.orders[order]!['discount'], '0.00');
      expect(
        post(
          op('b', 2, order, more: <String, Object?>{'staff_continued': false}),
        )['status'],
        'applied',
      );
    });

    test('a blocked user does nothing through sync but money: the discount is discount_not_allowed '
        '(staff_invalid), the round staff_invalid, the payment is taken (staff_unverified)', () async {
      await start();
      final PosOrder order = await room();
      final PosOrder given = await give(order, '10');
      final PosOrder more = await h.pos.orders.addRound(
        order.uuid,
        version: given.version,
        items: <PosLineInput>[
          PosLineInput(
            variantUuid: FakePosState.variantUuid(1),
            quantity: PosQuantity.parse('1'),
          ),
        ],
        send: true,
      );
      final PosBill bill = (await h.pos.billing.createBills(
        order.uuid,
        version: more.version,
      )).bills.single;
      await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: h.state.shift!['uuid']! as String,
      );
      staff(3)['blocked_at'] = h.clock.now.toUtc().toIso8601String();
      h.adapter.offline = false;
      await h.offline.syncNow();

      final Map<String, Object?> discount = result(SyncOpType.orderDiscount);
      expect(discount['status'], 'rejected');
      expect(discount['reason'], 'discount_not_allowed');
      expect((discount['refs']! as Map)['cause'], 'staff_invalid');
      final Map<String, Object?> round = result(SyncOpType.orderRound);
      expect(round['status'], 'rejected');
      expect(round['reason'], 'staff_invalid');
      expect(
        sync.results.values.any(
          (Map<String, Object?> r) =>
              r['type'] == SyncOpType.billPay && r['status'] == 'rejected',
        ),
        isFalse,
        reason: 'money is never rejected',
      );
      expect(
        result(SyncOpType.billPay)['reason'],
        anyOf(PosSyncReasons.staffUnverified, 'payment_unallocated'),
      );
    });

    test('the PIN sign-in of a discount is measured at the server\'s receipt, not at the till\'s time', () async {
      await start(userId: 2);
      final String order = h.state.seedOrder(tableId: 1, guests: 4);
      sync.sessionStartedAt[2] = h.clock.now;
      h.clock.now = h.clock.now.add(const Duration(hours: 37));
      // The till says it happened an hour after the sign-in
      final Map<String, Object?> late = op('a', 1, order)
        ..['occurred_at'] = h.clock.now
            .subtract(const Duration(hours: 36))
            .toUtc()
            .toIso8601String();
      final Map<String, Object?> r = post(late);
      expect(r['status'], 'rejected');
      expect(r['reason'], 'discount_not_allowed');
      expect((r['refs']! as Map)['cause'], 'staff_not_signed_in');
    });
  });
}
