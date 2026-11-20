import 'dart:convert';
import 'dart:io';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'offline_test.dart' show OfflineHarness;

/// Wave M5 stage S2 (docs/scale/M5_DESIGN.md section 3): a discount given at the till without internet.
///
/// The first groups read `backend/tests/Fixtures/offline_discount_vectors.json`, the file the backend test
/// (tests/Feature/PosSync/OfflineDiscountVectorsTest.php) reads too: the till and the server agree to the tiyin.
/// The rest runs the till against the fake backend: who may give a discount offline, the `order.discount` operation,
/// the percent that follows the lines, and what happens when the server refuses it.
void main() {
  final Map<String, Object?> vectors = _vectors();

  group('shared vectors', () {
    test('the file is the one the backend reads', () {
      expect(vectors['version'], 1);
      expect((vectors['amounts']! as List<Object?>).length, greaterThan(10));
      expect((vectors['limits']! as List<Object?>).length, greaterThan(5));
      expect(
        ((vectors['operation']! as Map<String, Object?>)['type']),
        SyncOpType.orderDiscount,
      );
    });

    for (final Map<String, Object?> v in _rows(vectors['amounts'])) {
      test('amount: ${v['name']}', () {
        final PosMoney items = PosMoney.parse(v['items']);
        final PosMoney discount = OfflineDiscount.amount(
          v['kind'] as String?,
          v['value'],
          items,
        );
        expect(discount.toApiString(), v['discount']);
        expect((items - discount).toApiString(), v['after_discount']);
      });
    }

    for (final Map<String, Object?> v in _rows(vectors['limits'])) {
      test('cashier limit: ${v['name']}', () {
        expect(
          OfflineDiscount.withinLimit(
            v['kind']! as String,
            '${v['value']}',
            PosMoney.parse(v['items']),
            v['limit'] as String?,
          ),
          v['allowed'],
        );
      });
    }

    for (final Map<String, Object?> s in _rows(vectors['sequences'])) {
      test('recompute: ${s['name']}', () {
        for (final Map<String, Object?> step in _rows(s['steps'])) {
          expect(
            OfflineDiscount.amount(
              s['kind'] as String?,
              s['value'],
              PosMoney.parse(step['items']),
            ).toApiString(),
            step['discount'],
          );
        }
      });
    }

    test(
      'no kind or no value: nothing is computed (the stored amount stands)',
      () {
        expect(
          OfflineDiscount.amount(null, '10', const PosMoney.som(41000)),
          PosMoney.zero,
        );
        expect(
          OfflineDiscount.amount('percent', null, const PosMoney.som(41000)),
          PosMoney.zero,
        );
      },
    );
  });

  test('the operation type is known to the queue and has a name for the error list', () {
    expect(SyncOpType.orderDiscount, 'order.discount');
    expect(SyncOpType.all, contains(SyncOpType.orderDiscount));
    expect(SyncOpType.label(SyncOpType.orderDiscount), 'Chegirma berildi');
  });

  group('the till offline', () {
    late OfflineHarness h;
    late FakeSyncBackend sync;

    Future<void> start({int userId = 3, bool advertised = true}) async {
      h = await OfflineHarness.create();
      FakeCashierBackend(h.state);
      sync = FakeSyncBackend(h.state);
      h.state.offlineDiscount = advertised;
      h.state.openShift();
      await h.signIn(userId: userId);
    }

    tearDown(() => h.pos.dispose());

    PosLineInput line(int variantId, String qty) => PosLineInput(
      variantUuid: FakePosState.variantUuid(variantId),
      quantity: PosQuantity.parse(qty),
    );

    String shift() => h.state.shift!['uuid']! as String;

    /// Room 1: 1.5 kg osh (180 000) + 2 tea (10 000) = 190 000; the room fee 300 000; service 10 % after the
    /// discount. Loaded online, then the network goes.
    Future<PosOrder> roomOffline() async {
      final String room = h.state.seedOrder(tableId: 1, guests: 4);
      await h.warmUp();
      final PosOrder order = await h.pos.orders.get(room);
      await h.goOffline();
      return order;
    }

    Future<PosOfflineUnavailable> refused(
      Future<Object?> Function() call,
    ) async {
      final int queued = h.offline.store.pendingCount;
      try {
        await call();
      } on PosOfflineUnavailable catch (e) {
        expect(
          h.offline.store.pendingCount,
          queued,
          reason: 'a refused discount queues nothing',
        );
        return e;
      }
      fail('expected PosOfflineUnavailable');
    }

    test(
      'scenario 1: a cashier gives 10 % offline; the percent follows a new round; bill, cash, close; after the '
      'sync the server has the same discount and the same bill to the tiyin',
      () async {
        await start();
        final PosOrder before = await roomOffline();
        expect(before.subtotal, const PosMoney.som(190000));
        expect(h.offline.discountEnabled, isTrue);
        expect(h.offline.discountBlock(before.uuid), isNull);

        final PosOrder given = await h.pos.orders.discount(
          before.uuid,
          version: before.version,
          kind: PosDiscountKind.percent,
          value: '10',
          reason: '  Doimiy mijoz ',
        );
        expect(given.discount, const PosMoney.som(19000));
        expect(given.discountKind, PosDiscountKind.percent);
        expect(given.discountValue, '10.00');
        expect(given.discountReason, 'Doimiy mijoz');
        expect(given.discountBy, 3);
        // 190 000 - 19 000 = 171 000; service 10 % = 17 100; room 300 000
        expect(given.serviceChargeAmount, const PosMoney.som(17100));
        expect(given.totalPrice, const PosMoney.som(488100));
        expect(given.version, greaterThan(before.version));

        final SyncOperation op = h.offline.store.pending.single;
        expect(op.type, SyncOpType.orderDiscount);
        expect(op.staffId, 3);
        expect(op.payload['order'], before.uuid);
        expect(op.payload['kind'], 'percent');
        expect(op.payload['value'], '10.00');
        expect(op.payload['reason'], 'Doimiy mijoz');
        expect(op.payload['expected_discount'], '19000.00');

        // A new round: the percent is taken again from the lines (as PosChargesCalculator::discountOf)
        final PosOrder more = await h.pos.orders.addRound(
          before.uuid,
          version: given.version,
          items: <PosLineInput>[line(1, '1')],
          send: true,
        );
        expect(more.subtotal, const PosMoney.som(195000));
        expect(more.discount, const PosMoney.som(19500));
        expect(more.totalPrice, const PosMoney.som(493050));

        final PosBilling billing = await h.pos.billing.createBills(
          before.uuid,
          version: more.version,
        );
        final PosBill bill = billing.bills.single;
        expect(bill.total, more.totalPrice);
        expect(bill.discount, const PosMoney.som(19500));
        final PosBillResult paid = await h.pos.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
          shiftUuid: shift(),
        );
        expect(paid.order.isClosed, isTrue);

        h.adapter.offline = false;
        expect((await h.offline.syncNow()).drained, isTrue);
        expect(
          sync.received.map((Map<String, Object?> o) => o['type']),
          <String>[
            SyncOpType.orderDiscount,
            SyncOpType.orderRound,
            SyncOpType.billCreate,
            SyncOpType.billPay,
            SyncOpType.orderClose,
          ],
        );
        expect(
          sync.results.values.map((Map<String, Object?> r) => r['status']),
          everyElement('applied'),
        );
        final Map<String, Object?> refs =
            sync.results[op.opId]!['refs']! as Map<String, Object?>;
        expect(refs['discount'], '19000.00');
        expect(refs['discount_kind'], 'percent');
        expect(refs['discount_value'], '10.00');
        expect(refs['flags'], <Object?>[
          <String, Object?>{
            'flag': 'offline_discount',
            'amount': '19000.00',
            'kind': 'percent',
            'value': '10.00',
            'staff_id': 3,
          },
        ]);
        // No amount_mismatch on the bill: the till's total is the server's
        final Map<String, Object?> billed = sync.results.values.firstWhere(
          (Map<String, Object?> r) => r['type'] == SyncOpType.billCreate,
        );
        expect((billed['refs']! as Map<String, Object?>)['flags'], isNull);
        expect(
          (billed['refs']! as Map<String, Object?>)['bill_total'],
          '493050.00',
        );
        final Map<String, Object?> server = h.state.orders[before.uuid]!;
        expect(server['discount'], '19500.00');
        expect(server['total_price'], '493050.00');
        expect(server['status'], 'delivered');
        expect(sync.errors, isEmpty);
      },
    );

    test('scenario 4a: a cashier above the branch limit is refused on the till, in Uzbek, and nothing is queued; '
        'an amount is measured as typed', () async {
      await start();
      final PosOrder order = await roomOffline();

      final PosOfflineUnavailable percent = await refused(
        () => h.pos.orders.discount(
          order.uuid,
          version: order.version,
          kind: PosDiscountKind.percent,
          value: '10.01',
          reason: 'Doimiy mijoz',
        ),
      );
      expect(percent.reason, PosOffline.discountLimitReason);
      expect(percent.message, contains('10%'));
      expect(percent.message, contains('Internetsiz'));
      expect(percent.message, contains('filial chegarasi'));

      // 190 000 x 10 % = 19 000: one tiyin above is refused though it rounds down to the limit
      final PosOfflineUnavailable amount = await refused(
        () => h.pos.orders.discount(
          order.uuid,
          version: order.version,
          kind: PosDiscountKind.amount,
          value: '19000.01',
          reason: 'Doimiy mijoz',
        ),
      );
      expect(amount.reason, PosOffline.discountLimitReason);
      expect(
        h.offline.discountRefusal(
          order.uuid,
          kind: PosDiscountKind.amount,
          value: '19000.01',
          reason: 'Doimiy mijoz',
        ),
        amount.message,
      );

      // The approval token of a manager cannot be checked offline: it changes nothing
      await refused(
        () => h.pos.orders.discount(
          order.uuid,
          version: order.version,
          kind: PosDiscountKind.percent,
          value: '20',
          reason: 'Doimiy mijoz',
          approvalToken: 'tok',
        ),
      );

      final PosOrder atLimit = await h.pos.orders.discount(
        order.uuid,
        version: order.version,
        kind: PosDiscountKind.amount,
        value: '19000',
        reason: 'Doimiy mijoz',
      );
      expect(atLimit.discount, const PosMoney.som(19000));
      expect(atLimit.discountKind, PosDiscountKind.amount);
      expect(h.offline.store.pending.single.payload['value'], '19000.00');
    });

    test(
      'the branch limit switched off: a cashier gives no discount offline',
      () async {
        await start();
        h.state.cashierDiscountMaxPercent = null;
        final PosOrder order = await roomOffline();
        final PosOfflineUnavailable e = await refused(
          () => h.pos.orders.discount(
            order.uuid,
            version: order.version,
            kind: PosDiscountKind.percent,
            value: '1',
            reason: 'Doimiy mijoz',
          ),
        );
        expect(e.reason, PosOffline.discountLimitReason);
        expect(e.message, contains('chegarasi belgilanmagan'));
      },
    );

    // (owner decision of 20.11.2026: a holder of orders.discount has the cashier limit offline too:
    // m5_s2_fix_test.dart)

    test('risk 7.2-1: a server that does not advertise offline.discount gets no order.discount; the till says '
        'internet is needed', () async {
      await start(advertised: false);
      final PosOrder order = await roomOffline();
      expect(h.offline.discountEnabled, isFalse);
      expect(
        h.offline.discountBlock(order.uuid),
        contains('Chegirma uchun internet kerak'),
      );
      final PosOfflineUnavailable e = await refused(
        () => h.pos.orders.discount(
          order.uuid,
          version: order.version,
          kind: PosDiscountKind.percent,
          value: '10',
          reason: 'Doimiy mijoz',
        ),
      );
      expect(e.reason, PosOffline.discountUnsupportedReason);
      expect(e.message, contains('Chegirma uchun internet kerak'));
    });

    test('a reason is mandatory; the value must be a discount', () async {
      await start();
      final PosOrder order = await roomOffline();
      Future<PosOfflineUnavailable> bad(
        PosDiscountKind kind,
        String value, {
        String? reason = 'Doimiy mijoz',
      }) => refused(
        () => h.pos.orders.discount(
          order.uuid,
          version: order.version,
          kind: kind,
          value: value,
          reason: reason,
        ),
      );

      expect(
        (await bad(PosDiscountKind.percent, '5', reason: '  ')).message,
        contains('sabab'),
      );
      expect(
        (await bad(PosDiscountKind.percent, '5', reason: null)).message,
        contains('sabab'),
      );
      expect(
        (await bad(PosDiscountKind.percent, 'abc')).reason,
        'value_invalid',
      );
      expect(
        (await bad(PosDiscountKind.percent, '-1')).reason,
        'value_invalid',
      );
      expect(
        (await bad(PosDiscountKind.percent, '1.005')).reason,
        'value_invalid',
      );
    });

    test('a manager: a percent above 100 and an amount above the dishes are refused', () async {
      await start(userId: 2);
      final PosOrder order = await roomOffline();
      final PosOfflineUnavailable percent = await refused(
        () => h.pos.orders.discount(
          order.uuid,
          version: order.version,
          kind: PosDiscountKind.percent,
          value: '100.01',
          reason: 'Bayram',
        ),
      );
      expect(percent.message, contains('100'));
      final PosOfflineUnavailable amount = await refused(
        () => h.pos.orders.discount(
          order.uuid,
          version: order.version,
          kind: PosDiscountKind.amount,
          value: '190000.01',
          reason: 'Bayram',
        ),
      );
      expect(amount.reason, 'value_invalid');
      expect(amount.message, contains('taomlar summasidan'));
    });

    test('only before the bill: once a bill exists the discount is refused for everybody', () async {
      await start(userId: 2);
      final PosOrder order = await roomOffline();
      await h.pos.billing.createBills(order.uuid, version: order.version);
      final int queued = h.offline.store.pendingCount;
      expect(h.offline.discountBlock(order.uuid), contains('schyot'));
      await expectLater(
        h.pos.orders.discount(
          order.uuid,
          version: order.version,
          kind: PosDiscountKind.percent,
          value: '5',
          reason: 'Bayram',
        ),
        throwsA(
          isA<PosOfflineUnavailable>().having(
            (PosOfflineUnavailable e) => e.reason,
            'reason',
            PosOffline.discountBilledReason,
          ),
        ),
      );
      expect(h.offline.store.pendingCount, queued);
    });

    test('taking a discount off needs no reason and no limit (orders.manage); the bill is whole again', () async {
      await start();
      final PosOrder order = await roomOffline();
      final PosOrder given = await h.pos.orders.discount(
        order.uuid,
        version: order.version,
        kind: PosDiscountKind.percent,
        value: '10',
        reason: 'Doimiy mijoz',
      );
      final PosOrder removed = await h.pos.orders.discount(
        order.uuid,
        version: given.version,
        kind: PosDiscountKind.percent,
        value: '0',
      );
      expect(removed.discount, PosMoney.zero);
      expect(removed.discountKind, isNull);
      expect(removed.discountValue, isNull);
      expect(removed.discountReason, isNull);
      expect(removed.hasDiscount, isFalse);
      expect(removed.totalPrice, order.totalPrice);
      final SyncOperation op = h.offline.store.pending.last;
      expect(op.payload['value'], '0.00');
      expect(op.payload['expected_discount'], '0.00');
      expect(op.payload.containsKey('reason'), isFalse);

      h.adapter.offline = false;
      expect((await h.offline.syncNow()).drained, isTrue);
      expect(
        sync.results.values.map((Map<String, Object?> r) => r['status']),
        everyElement('applied'),
      );
      // Taken off: the order carries no offline-discount mark
      expect(
        (sync.results[op.opId]!['refs']! as Map<String, Object?>)['flags'],
        isNull,
      );
      expect(h.state.orders[order.uuid]!['discount'], '0.00');
      expect(h.state.orders[order.uuid]!['discount_kind'], isNull);
    });

    test(
      'a waiter (no pos.take_payment, no orders.discount) gives none offline',
      () async {
        await start(userId: 4);
        final PosOrder order = await roomOffline();
        final PosOfflineUnavailable e = await refused(
          () => h.pos.orders.discount(
            order.uuid,
            version: order.version,
            kind: PosDiscountKind.percent,
            value: '5',
            reason: 'Doimiy mijoz',
          ),
        );
        expect(e.reason, PosOffline.discountLimitReason);
      },
    );

    test('not after the 24 h limit: the open table is still billed, but no new discount', () async {
      await start();
      final PosOrder order = await roomOffline();
      h.clock.now = h.clock.now.add(const Duration(hours: 25));
      expect(h.offline.status.canOpenOrders, isFalse);
      final PosOfflineUnavailable e = await refused(
        () => h.pos.orders.discount(
          order.uuid,
          version: order.version,
          kind: PosDiscountKind.percent,
          value: '5',
          reason: 'Doimiy mijoz',
        ),
      );
      expect(e.reason, PosOffline.limitReason);
      expect(e.message, contains('24 soat'));
      expect(h.offline.discountBlock(order.uuid), e.message);
      // (the bill is never stopped)
      final PosBilling billing = await h.pos.billing.createBills(
        order.uuid,
        version: order.version,
      );
      expect(billing.bills.single.total, order.totalPrice);
    });

    test('not by a staff member who goes on without a PIN', () async {
      await start();
      final String room = h.state.seedOrder(tableId: 1, guests: 2);
      sync.sessionStartedAt[3] = h.clock.now;
      await h.warmUp();
      await h.goOffline();
      h.clock.now = h.clock.now.add(const Duration(hours: 13));
      await h.pos.session.refreshEntitlements();
      expect(h.pos.session.staffContinued, isTrue);
      final PosOrder order = await h.pos.orders.get(room);
      final PosOfflineUnavailable e = await refused(
        () => h.pos.orders.discount(
          room,
          version: order.version,
          kind: PosDiscountKind.percent,
          value: '5',
          reason: 'Doimiy mijoz',
        ),
      );
      expect(e.reason, PosOffline.continuedReason);
      expect(e.message, contains("PIN'siz"));
      expect(h.offline.discountBlock(room), e.message);
    });

    test('scenario 4b: the limit was lowered on the server during the outage: the discount is rejected '
        '(discount_not_allowed), the bill is made at the full price, the payment is taken and the order is '
        'underpaid by the discount', () async {
      await start();
      final PosOrder order = await roomOffline();
      final PosOrder given = await h.pos.orders.discount(
        order.uuid,
        version: order.version,
        kind: PosDiscountKind.percent,
        value: '10',
        reason: 'Doimiy mijoz',
      );
      final String discountOp = h.offline.store.pending.single.opId;
      final PosBill bill = (await h.pos.billing.createBills(
        order.uuid,
        version: given.version,
      )).bills.single;
      expect(bill.total, const PosMoney.som(488100));
      await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift(),
      );

      // Meanwhile the owner lowers the cashier's limit
      h.state.cashierDiscountMaxPercent = '5.00';
      h.adapter.offline = false;
      expect((await h.offline.syncNow()).drained, isTrue);

      final Map<String, Object?> refusedOp = sync.results[discountOp]!;
      expect(refusedOp['status'], 'rejected');
      expect(refusedOp['reason'], 'discount_not_allowed');
      expect(
        refusedOp['message'],
        contains('Chegirma serverda qabul qilinmadi'),
      );
      expect(refusedOp['refs'], <String, Object?>{
        // (owner decision of 20.11.2026: through sync the cashier limit is everybody's ceiling)
        'cause': 'offline_limit',
        'kind': 'percent',
        'value': '10.00',
        'expected_discount': '19000.00',
      });

      Map<String, Object?> of(String type) => sync.results.values.firstWhere(
        (Map<String, Object?> r) => r['type'] == type,
      );
      // The bill at the full price: 190 000 + 19 000 service + 300 000 room
      final Map<String, Object?> billed = of(SyncOpType.billCreate);
      expect(billed['status'], 'applied');
      expect((billed['refs']! as Map<String, Object?>)['flags'], <Object?>[
        <String, Object?>{
          'flag': 'amount_mismatch',
          'device_total': '488100.00',
          'server_total': '509000.00',
        },
      ]);
      // The money is never refused: taken, and the order is short by the refused discount (and its service charge)
      final Map<String, Object?> payment = of(SyncOpType.billPay);
      expect(payment['status'], 'conflict');
      expect(payment['reason'], 'underpaid');
      expect((payment['refs']! as Map<String, Object?>)['flags'], <Object?>[
        <String, Object?>{
          'flag': 'underpaid',
          'amount': '20900.00',
          'server_total': '509000.00',
        },
      ]);
      final Map<String, Object?> server = h.state.orders[order.uuid]!;
      expect(server['discount'], '0.00');
      expect(server['status'], isNot('delivered'));

      // The till's error list names the operation; the till's own view is the server's again
      final SyncOperation shown = h.offline.store.errors.firstWhere(
        (SyncOperation o) => o.opId == discountOp,
      );
      expect(shown.status, SyncOpStatus.rejected);
      expect(SyncOpType.label(shown.type), 'Chegirma berildi');
      final PosOrder after = await h.pos.orders.get(order.uuid);
      expect(after.discount, PosMoney.zero);
      expect(after.totalPrice, const PosMoney.som(509000));
    });

    test(
      'a percent given online before the outage follows a round added offline',
      () async {
        await start();
        final String room = h.state.seedOrder(tableId: 1, guests: 4);
        await h.warmUp();
        final PosOrder plain = await h.pos.orders.get(room);
        final PosOrder online = await h.pos.orders.discount(
          room,
          version: plain.version,
          kind: PosDiscountKind.percent,
          value: '10',
          reason: 'Doimiy mijoz',
        );
        expect(online.discount, const PosMoney.som(19000));
        await h.goOffline();

        final PosOrder more = await h.pos.orders.addRound(
          room,
          version: online.version,
          items: <PosLineInput>[line(1, '1')],
          send: true,
        );
        expect(more.discount, const PosMoney.som(19500));
        expect(more.totalPrice, const PosMoney.som(493050));

        h.adapter.offline = false;
        expect((await h.offline.syncNow()).drained, isTrue);
        expect(h.state.orders[room]!['total_price'], '493050.00');
      },
    );

    test('a promo code (the server\'s discount_promo) is the server\'s to compute: its amount stays as stored', () {
      final Map<String, Object?> order = <String, Object?>{
        'uuid': 'u',
        'status': 'preparing',
        'channel': 'pos_takeaway',
        'version': 3,
        'discount': '7000.00',
        'discount_kind': 'percent',
        'discount_value': '10.00',
        'discount_reason': 'promo:YOZ',
        'discount_promo': true,
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
      final OfflineOrderState state = OfflineOrderState.existing(
        'u',
        order,
        null,
        const PosOfflineSettings(),
      )..recalculate();
      expect(state.order['discount'], '7000.00');
      expect(state.order['total_price'], '93000.00');
    });

    test(
      'online, the request still goes to the server (approval token and all)',
      () async {
        await start();
        final String room = h.state.seedOrder(tableId: 1, guests: 4);
        await h.warmUp();
        final PosOrder order = await h.pos.orders.get(room);
        await expectLater(
          h.pos.orders.discount(
            room,
            version: order.version,
            kind: PosDiscountKind.percent,
            value: '20',
            reason: 'Doimiy mijoz',
          ),
          throwsA(isA<PosApprovalRequired>()),
        );
        expect(h.offline.store.pendingCount, 0);
      },
    );
  });
}

/// `backend/tests/Fixtures/offline_discount_vectors.json`, found from the repo root (the test runs from the package
/// directory, or from wherever `flutter test` was started).
Map<String, Object?> _vectors() {
  const String path = 'backend/tests/Fixtures/offline_discount_vectors.json';
  Directory dir = Directory.current.absolute;
  while (true) {
    final File file = File('${dir.path}/$path');
    if (file.existsSync()) {
      return (jsonDecode(file.readAsStringSync()) as Map)
          .cast<String, Object?>();
    }
    final Directory parent = dir.parent;
    if (parent.path == dir.path) {
      throw StateError('$path was not found above ${Directory.current.path}');
    }
    dir = parent;
  }
}

List<Map<String, Object?>> _rows(Object? v) => <Map<String, Object?>>[
  for (final Object? e in (v as List<Object?>?) ?? const <Object?>[])
    if (e is Map) e.cast<String, Object?>(),
];
