import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P3-37: a discount on the order (`POST /pos/orders/{uuid}/discount`) over the fake backend — the cashier alone up to
/// the branch limit, a manager above it, a percent that follows the lines, removal, paid bills; the cashier's new
/// default permissions; cancelling a closed order whose payments are all refunded.
void main() {
  late Harness h;
  late FakeCashierBackend backend;

  setUp(() async {
    h = Harness();
    backend = FakeCashierBackend(h.state);
    await h.signIn();
  });

  /// 1-xona: 1.5 kg osh (180 000) + 2 choy (10 000) = 190 000; room fee 300 000; service 10% after the discount.
  Future<PosOrder> roomOrder() async =>
      h.pos.orders.get(h.state.seedOrder(tableId: 1, guests: 4));

  Future<ApprovalToken> managerToken(String permission) =>
      h.pos.approvals.request(permission: permission, userId: 2, pin: '5926');

  Future<T> throws<T extends Object>(Future<Object?> Function() call) async {
    try {
      await call();
    } on T catch (e) {
      return e;
    }
    fail('expected $T');
  }

  test('order JSON: the discount fields are read; no discount — null', () {
    final PosOrder plain = PosOrder.fromJson(
      PosJson.of(<String, Object?>{
        'uuid': 'u',
        'status': 'accepted',
        'channel': 'pos_hall',
        'version': 1,
        'items': <Object?>[],
      }),
    );
    expect(plain.discountKind, isNull);
    expect(plain.discountValue, isNull);
    expect(plain.hasDiscount, isFalse);
    final PosOrder given = PosOrder.fromJson(
      PosJson.of(<String, Object?>{
        'uuid': 'u',
        'status': 'accepted',
        'channel': 'pos_hall',
        'version': 3,
        'discount': '19000.00',
        'discount_kind': 'percent',
        'discount_value': '10.00',
        'discount_reason': 'Doimiy mijoz',
        'discount_by': 3,
        'items': <Object?>[],
      }),
    );
    expect(given.discount, const PosMoney.som(19000));
    expect(given.discountKind, PosDiscountKind.percent);
    expect(given.discountPercent, '10.00');
    expect(given.discountReason, 'Doimiy mijoz');
    expect(given.discountBy, 3);
    expect(given.hasDiscount, isTrue);
  });

  test(
    'bootstrap: the cashier limit and the cash-out alert are read',
    () async {
      final PosBootstrap boot = await h.pos.session.loadBootstrap();
      expect(boot.branch.settings.cashierDiscountMaxPercent, '10.00');
      expect(boot.branch.settings.cashierDiscountMaxHundredths, 1000);
      expect(
        boot.branch.settings.alerts.cashOutAmount,
        const PosMoney.som(500000),
      );
      expect(boot.user.permissions, contains(PosPermissions.reprintBill));
      expect(boot.user.permissions, contains(PosPermissions.cashInOut));
      expect(
        boot.user.permissions,
        isNot(contains(PosPermissions.ordersDiscount)),
      );

      h.state.cashierDiscountMaxPercent = null;
      final PosBootstrap none = await h.pos.session.loadBootstrap();
      expect(none.branch.settings.cashierDiscountMaxPercent, isNull);
      expect(none.branch.settings.cashierDiscountMaxHundredths, 0);
    },
  );

  test('within the limit the cashier gives it alone: the body, the key, the totals', () async {
    final PosOrder order = await roomOrder();
    final PosOrder after = await h.pos.orders.discount(
      order.uuid,
      version: order.version,
      kind: PosDiscountKind.percent,
      value: '10',
      reason: '  Doimiy mijoz ',
    );
    expect(Harness.bodyOf(h.last), <String, Object?>{
      'version': order.version,
      'kind': 'percent',
      'value': '10',
      'reason': 'Doimiy mijoz',
    });
    expect(h.last.headers['Idempotency-Key'], matches(uuidV4));
    expect(after.version, order.version + 1);
    expect(after.discountKind, PosDiscountKind.percent);
    expect(after.discountValue, '10.00');
    expect(after.discountReason, 'Doimiy mijoz');
    expect(after.discountBy, 3);
    expect(after.discount, const PosMoney.som(19000));
    expect(after.charges.discount, const PosMoney.som(19000));
    // Service 10% of what is left (171 000), room fee as before
    expect(after.serviceChargeAmount, const PosMoney.som(17100));
    expect(after.totalPrice, const PosMoney.som(171000 + 17100 + 300000));
  });

  test('above the limit: approval_required for orders.discount, then with the manager token', () async {
    final PosOrder order = await roomOrder();
    final PosApprovalRequired e = await throws<PosApprovalRequired>(
      () => h.pos.orders.discount(
        order.uuid,
        version: order.version,
        kind: PosDiscountKind.percent,
        value: '15',
        reason: 'Ega ruxsati',
      ),
    );
    expect(e.permission, PosPermissions.ordersDiscount);
    final PosOrder after = await h.pos.orders.discount(
      order.uuid,
      version: order.version,
      kind: PosDiscountKind.percent,
      value: '15',
      reason: 'Ega ruxsati',
      approvalToken: (await managerToken(PosPermissions.ordersDiscount)).token,
    );
    expect(after.discount, const PosMoney.som(28500));
  });

  test('an amount counts by its percent of the lines; no limit set — every discount needs a manager', () async {
    final PosOrder order = await roomOrder();
    // 19 000 of 190 000 = 10% — within
    final PosOrder within = await h.pos.orders.discount(
      order.uuid,
      version: order.version,
      kind: PosDiscountKind.amount,
      value: '19000.00',
      reason: 'Kechikish uchun uzr',
    );
    expect(within.discount, const PosMoney.som(19000));
    expect(within.discountKind, PosDiscountKind.amount);
    // 19 001 — just above
    await throws<PosApprovalRequired>(
      () => h.pos.orders.discount(
        order.uuid,
        version: within.version,
        kind: PosDiscountKind.amount,
        value: '19001.00',
        reason: 'Kechikish uchun uzr',
      ),
    );
    h.state.cashierDiscountMaxPercent = null;
    await throws<PosApprovalRequired>(
      () => h.pos.orders.discount(
        order.uuid,
        version: within.version,
        kind: PosDiscountKind.percent,
        value: '1',
        reason: 'Doimiy mijoz',
      ),
    );
  });

  test(
    'a manager (orders.discount) gives any discount without a token',
    () async {
      await h.signIn(userId: 2);
      final PosOrder order = await roomOrder();
      final PosOrder after = await h.pos.orders.discount(
        order.uuid,
        version: order.version,
        kind: PosDiscountKind.percent,
        value: '50',
        reason: 'Ega ruxsati',
      );
      expect(after.discount, const PosMoney.som(95000));
    },
  );

  test('refused: no reason, more than the lines, over 100% (422); an old version (409)', () async {
    final PosOrder order = await roomOrder();
    final PosValidationException noReason =
        await throws<PosValidationException>(
          () => h.pos.orders.discount(
            order.uuid,
            version: order.version,
            kind: PosDiscountKind.percent,
            value: '5',
          ),
        );
    expect(noReason.errors.keys, contains('reason'));
    await throws<PosValidationException>(
      () => h.pos.orders.discount(
        order.uuid,
        version: order.version,
        kind: PosDiscountKind.amount,
        value: '190001.00',
        reason: 'x',
      ),
    );
    await throws<PosValidationException>(
      () => h.pos.orders.discount(
        order.uuid,
        version: order.version,
        kind: PosDiscountKind.percent,
        value: '101',
        reason: 'x',
      ),
    );
    final PosVersionConflict stale = await throws<PosVersionConflict>(
      () => h.pos.orders.discount(
        order.uuid,
        version: order.version - 1,
        kind: PosDiscountKind.percent,
        value: '5',
        reason: 'Doimiy mijoz',
      ),
    );
    expect(stale.currentVersion, order.version);
  });

  test('a percent follows the lines (a round added, a line voided); value 0 removes it without a reason', () async {
    final PosOrder order = await roomOrder();
    final PosOrder given = await h.pos.orders.discount(
      order.uuid,
      version: order.version,
      kind: PosDiscountKind.percent,
      value: '10',
      reason: 'Doimiy mijoz',
    );
    final PosOrder more = await h.pos.orders.addRound(
      order.uuid,
      version: given.version,
      items: <PosLineInput>[
        PosLineInput(
          variantUuid: FakePosState.variantUuid(2),
          quantity: const PosQuantity.whole(5),
        ),
      ],
    );
    // 190 000 + 5 × 4 000 = 210 000 → 21 000
    expect(more.discount, const PosMoney.som(21000));
    expect(more.discountValue, '10.00');
    final PosOrderItem bread = more.items.last;
    final PosOrder less = await h.pos.orders.voidItem(
      order.uuid,
      bread.id,
      version: more.version,
    );
    expect(less.discount, const PosMoney.som(19000));

    final PosOrder removed = await h.pos.orders.discount(
      order.uuid,
      version: less.version,
      kind: PosDiscountKind.percent,
      value: '0',
    );
    expect(Harness.bodyOf(h.last).containsKey('reason'), isFalse);
    expect(removed.discount, PosMoney.zero);
    expect(removed.discountKind, isNull);
    expect(removed.hasDiscount, isFalse);
    expect(removed.serviceChargeAmount, const PosMoney.som(19000));
  });

  test('a bill with money on it: 409 paid_bill_affected; a closed order: 422 order_closed', () async {
    h.state.openShift();
    final PosOrder order = await roomOrder();
    final PosBill bill = (await h.pos.billing.createBills(
      order.uuid,
      version: order.version,
    )).bills.single;
    await h.pos.billing.pay(
      bill.uuid,
      tenders: <PosTenderInput>[
        PosTenderInput.cash(bill.total - const PosMoney.som(1000)),
      ],
    );
    final PosOrder now = await h.pos.orders.get(order.uuid);
    final PosConflict paid = await throws<PosConflict>(
      () => h.pos.orders.discount(
        order.uuid,
        version: now.version,
        kind: PosDiscountKind.percent,
        value: '5',
        reason: 'Doimiy mijoz',
      ),
    );
    expect(paid.reason, PosReasons.paidBillAffected);
    expect(paid.needsOrderReload, isTrue);
    // Taking it off would change the paid bill too
    final PosConflict off = await throws<PosConflict>(
      () => h.pos.orders.discount(
        order.uuid,
        version: now.version,
        kind: PosDiscountKind.percent,
        value: '0',
      ),
    );
    expect(off.reason, PosReasons.paidBillAffected);

    await h.pos.billing.pay(
      bill.uuid,
      tenders: <PosTenderInput>[const PosTenderInput.cash(PosMoney.som(1000))],
    );
    final PosOrder closed = await h.pos.orders.get(order.uuid);
    expect(closed.isOpen, isFalse);
    final PosRejected e = await throws<PosRejected>(
      () => h.pos.orders.discount(
        order.uuid,
        version: closed.version,
        kind: PosDiscountKind.percent,
        value: '5',
        reason: 'Doimiy mijoz',
      ),
    );
    expect(e.reason, 'order_closed');
  });

  test('a printed bill locks the order: the own limit no longer applies; removal needs no manager', () async {
    h.state.openShift();
    final PosOrder order = await roomOrder();
    final PosOrder given = await h.pos.orders.discount(
      order.uuid,
      version: order.version,
      kind: PosDiscountKind.percent,
      value: '5',
      reason: 'Doimiy mijoz',
    );
    final PosBill bill = (await h.pos.billing.createBills(
      order.uuid,
      version: given.version,
    )).bills.single;
    await h.pos.billing.printBill(bill.uuid, version: given.version);
    final PosOrder locked = await h.pos.orders.get(order.uuid);
    expect(locked.billLocked, isTrue);
    final PosApprovalRequired e = await throws<PosApprovalRequired>(
      () => h.pos.orders.discount(
        order.uuid,
        version: locked.version,
        kind: PosDiscountKind.percent,
        value: '5',
        reason: 'Doimiy mijoz',
      ),
    );
    expect(e.permission, PosPermissions.ordersDiscount);
    final List<String> changes = <String>[];
    h.state.onEvent = (String event, Map<String, Object?> data) {
      if (event == 'order.items_changed') changes.add('${data['change']}');
    };
    final PosOrder removed = await h.pos.orders.discount(
      order.uuid,
      version: locked.version,
      kind: PosDiscountKind.percent,
      value: '0',
    );
    expect(removed.hasDiscount, isFalse);
    expect(changes, <String>['charges_changed']);
  });

  test('the discount reaches the bill lines, the receipt lines (after it) and the Z', () async {
    h.state.openShift();
    final PosOrder order = await roomOrder();
    final PosOrder given = await h.pos.orders.discount(
      order.uuid,
      version: order.version,
      kind: PosDiscountKind.percent,
      value: '10',
      reason: 'Doimiy mijoz',
    );
    final PosBill bill = (await h.pos.billing.createBills(
      order.uuid,
      version: given.version,
    )).bills.single;
    expect(bill.discount, const PosMoney.som(19000));
    expect(
      PosMoney.sum(bill.items.map((PosBillItem i) => i.discountAmount)),
      const PosMoney.som(19000),
    );
    expect(bill.total, given.totalPrice);
    final PosBillResult paid = await h.pos.billing.pay(
      bill.uuid,
      tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
    );
    final PosFiscalReceiptDetail receipt = paid.bill.fiscal.receipt!;
    expect(
      PosMoney.sum(receipt.lines.map((PosFiscalLine l) => l.amount)),
      bill.total,
    );
    final PosShiftWithReport x = await h.pos.shifts.xReport(
      (await h.pos.shifts.current()).shift!.uuid,
    );
    expect(x.report!.discounts.count, 1);
    expect(x.report!.discounts.amount, const PosMoney.som(19000));
  });

  test('the same key and body after a lost answer is answered once', () async {
    final PosOrder order = await roomOrder();
    Future<PosOrder> send() => h.pos.orders.discount(
      order.uuid,
      version: order.version,
      kind: PosDiscountKind.percent,
      value: '10',
      reason: 'Doimiy mijoz',
      idempotencyKey: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    );
    final PosOrder first = await send();
    final PosOrder again = await send();
    expect(again.version, first.version);
    expect(h.state.replayed, 1);
    expect(h.state.orders[order.uuid]!['version'], first.version);
  });

  test('an owner may take pos.cash_in_out away again: then the cash movement needs a manager', () async {
    h.state.rolePermissions['cashier']!.remove(PosPermissions.cashInOut);
    final PosShift shift = await h.pos.shifts.open(
      openingCash: const PosMoney.som(100000),
    );
    await throws<PosApprovalRequired>(
      () => h.pos.shifts.cashMovement(
        shift.uuid,
        type: PosCashMovementType.cashIn,
        category: 'change',
        amount: const PosMoney.som(10000),
      ),
    );
  });

  test('cancel after a full refund: a CLOSED order whose payments are all refunded; others stay order_closed', () async {
    h.state.openShift();
    final PosOrder order = await roomOrder();
    final PosBill bill = (await h.pos.billing.createBills(
      order.uuid,
      version: order.version,
    )).bills.single;
    final PosBillResult paid = await h.pos.billing.pay(
      bill.uuid,
      tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
    );
    expect(paid.order.isClosed, isTrue);
    Future<PosOrder> cancel({String? token}) async {
      final PosOrder now = await h.pos.orders.get(order.uuid);
      return h.pos.orders.cancel(
        order.uuid,
        version: now.version,
        reason: "Xato to'lov",
        approvalToken: token,
      );
    }

    expect((await throws<PosRejected>(cancel)).reason, 'order_closed');
    await h.pos.billing.refund(
      paymentUuid: paid.bill.payments.single.uuid,
      amount: bill.total,
      reason: "Xato to'lov",
      approvalToken: (await managerToken(PosPermissions.refund)).token,
    );
    final List<PosOpenOrder> closed = await h.pos.orders.closedOrders();
    expect(closed.single.paymentStatus, 'refunded');
    await throws<PosApprovalRequired>(cancel);
    final PosOrder cancelled = await cancel(
      token: (await managerToken(PosPermissions.ordersCancel)).token,
    );
    expect(cancelled.status, 'canceled');
    final PosBilling after = await h.pos.billing.bills(order.uuid);
    expect(after.order.isClosed, isTrue, reason: 'closed_at is kept');
    expect(after.order.status, 'canceled');
    expect(
      backend.receipts.values.where(
        (Map<String, Object?> r) => r['type'] == 'sale',
      ),
      hasLength(1),
    );
  });
}
