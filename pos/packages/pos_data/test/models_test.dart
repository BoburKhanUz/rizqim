import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// Haqiqiy backend javoblari (test/fixtures, mahalliy backend'dagi choyxona oqimidan) modellarga o'qiladi: pul
/// tiyinda, miqdor mingdan birda, holatlar tiplangan.
void main() {
  PosJson data(String name) => PosJson.of(fixtureData(name));

  test('bootstrap', () {
    final PosBootstrap b = PosBootstrap.fromJson(
      data('bootstrap'),
      receivedAt: DateTime.utc(2026, 10, 22, 7, 59, 10),
    );
    expect(b.menuVersion, '53df1dd96feb');
    expect(b.store.timezone, 'Asia/Tashkent');
    expect(b.branch.businessDate, '2026-10-22');
    expect(b.branch.businessDayStartsAt, '05:00');
    expect(b.branch.settings.mode, PosMode.hybrid);
    expect(b.branch.settings.screenLock, const Duration(minutes: 5));
    expect(b.branch.settings.alerts.voidAmount, const PosMoney.som(200000));
    expect(b.device!.isPrintingDevice, isFalse);
    expect(b.user.permissions, contains('pos.take_payment'));
    expect(b.stations.single.kindLabel, 'Osh va qozon');
    expect(b.stationRoutes.stationFor(productId: 3), 1);
    expect(b.stationRoutes.stationFor(productId: 4, categoryId: 1), isNull);
    expect(
      b.staff.map((PosStaffRef s) => s.role),
      containsAll(<String>['waiter', 'manager']),
    );
    expect(b.halls.single.name, 'Asosiy zal');
    expect(b.tables, hasLength(3));
    expect(b.tables.first.kind, FloorTableKind.room);
    expect(b.tables.first.layout, isNull);
    expect(b.tablesOf(1), hasLength(3));
    expect(b.shift, isNull);
    expect(b.clockSkew, const Duration(seconds: 8));
  });

  test('POS catalog: channel price, kg step and minimum, modifiers, kazan', () {
    final PosCatalog c = PosCatalog.fromJson(data('catalog'));
    expect(c.channel, PosChannel.hall);
    expect(c.branchId, 1);
    final PosProduct osh = c.products.firstWhere(
      (PosProduct p) => p.name == 'Osh',
    );
    final PosVariant plov = osh.singleVariant!;
    expect(plov.price, const PosMoney.som(120000));
    expect(plov.unit, PosUnit.kg);
    expect(plov.unitLabel, 'kg (guruch)');
    expect(plov.step, const PosQuantity.milli(500));
    expect(plov.minimum, const PosQuantity.milli(500));
    expect(plov.accepts(const PosQuantity.milli(1500)), isTrue);
    expect(plov.accepts(const PosQuantity.milli(300)), isFalse);
    expect(osh.stationId, 1);
    expect(osh.batchTracked, isTrue);
    expect(c.batchFor(plov)!.quantityLeft, const PosQuantity.whole(10));

    final PosProduct shashlik = c.products.firstWhere(
      (PosProduct p) => p.name == 'Shashlik',
    );
    expect(shashlik.singleVariant!.step, PosQuantity.one);
    expect(
      shashlik.singleVariant!.accepts(const PosQuantity.milli(1500)),
      isFalse,
    );
    final PosModifier onion = shashlik.modifierGroups.single.modifiers.single;
    expect(onion.priceDelta, const PosMoney.som(2000));
    expect(onion.uuid, isNotEmpty);
    expect(c.variantByUuid(plov.uuid), same(plov));
    expect(c.search('sha').single.name, 'Shashlik');
  });

  test('availability overrides what the catalog said', () {
    final PosCatalog c = PosCatalog.fromJson(data('catalog'));
    final PosVariant tea = c.products.first.singleVariant!;
    expect(c.isVariantAvailable(tea), isTrue);
    final PosCatalog stopped = c.withAvailability(
      PosAvailability(stoppedVariants: <int>{tea.id}),
    );
    expect(stopped.unavailableReasonOf(tea), PosUnavailableReason.stopped);
    expect(stopped.isProductAvailable(c.products.first), isFalse);
    final PosCatalog soldOut = c.withAvailability(
      PosAvailability(soldOutVariants: <int>{tea.id}),
    );
    expect(soldOut.unavailableReasonOf(tea), PosUnavailableReason.soldOut);
  });

  test('floor: statuses map to pos_core, orders summarised', () {
    final PosFloor f = PosFloor.fromJson(data('floor'));
    final FloorTable room = f.table(1)!;
    expect(room.status, FloorTableStatus.occupied);
    expect(room.orders.single.totalPrice, const PosMoney.som(522200));
    expect(room.orders.single.unsentCount, 0);
    expect(f.table(3)!.status, FloorTableStatus.free);
    expect(floorStatusFromWire('reserved_soon'), FloorTableStatus.booked);
    expect(floorStatusFromWire('bill_printed'), FloorTableStatus.billPrinted);
    expect(floorStatusFromWire('overdue'), FloorTableStatus.overdue);
    expect(f.orders, hasLength(1));
  });

  test('order: totals, charges, rounds, kg lines, modifiers, void', () {
    final PosOrder o = PosOrder.fromJson(data('order_show'));
    expect(o.posChannel, PosChannel.hall);
    expect(o.table!.kind, FloorTableKind.room);
    expect(o.hall!.name, 'Asosiy zal');
    expect(o.roomFeeAmount, const PosMoney.som(300000));
    expect(o.serviceChargeAmount, const PosMoney.som(20200));
    expect(o.totalPrice, const PosMoney.som(522200));
    expect(o.charges.serviceChargePercent, '10.00');
    expect(o.charges.dayType, 'weekday');
    final PosOrderItem plov = o.items.firstWhere(
      (PosOrderItem i) => i.productName == 'Osh',
    );
    expect(plov.quantity, const PosQuantity.milli(1500));
    expect(plov.unit, PosUnit.kg);
    expect(plov.kitchenStatus, PosKitchenStatus.sent);
    expect(plov.stationId, 1);
    final PosOrderItem shashlik = o.items.firstWhere(
      (PosOrderItem i) => i.productName == 'Shashlik',
    );
    expect(shashlik.isVoid, isTrue);
    expect(shashlik.voidReason, 'xato');
    expect(shashlik.modifiers.single.name, 'Piyoz');
    expect(shashlik.note, 'achchiq');
    expect(o.activeItems.any((PosOrderItem i) => i.isVoid), isFalse);
    expect(o.itemsByRound.keys, containsAll(<int>[1, 2]));
    expect(o.isOpen, isTrue);
  });

  test('open orders queue', () {
    final List<PosOpenOrder> rows = <PosOpenOrder>[
      for (final Object? e in fixtureData('orders_open')! as List<Object?>)
        PosOpenOrder.fromJson(PosJson.of(e)),
    ];
    expect(rows.single.isPos, isTrue);
    expect(rows.single.totalPrice, const PosMoney.som(522200));
    expect(rows.single.fiscalPending, isFalse);
  });

  test(
    'bills: equal split, items with kg shares, payments with change, refunds',
    () {
      final PosBilling b = PosBilling.fromJson(data('bills_create'));
      expect(b.bills, hasLength(2));
      expect(b.bills.first.splitMode, 'equal');
      expect(b.bills.first.total, const PosMoney.som(261100));
      expect(b.bills.first.items.last.quantity, const PosQuantity.milli(750));
      expect(b.order.billedAmount, const PosMoney.som(522200));

      final PosBillResult paid = PosBillResult.fromJson(data('bill_pay'));
      final PosPayment cash = paid.bill.payments.single;
      expect(cash.tenderKind, PosTender.cash);
      expect(cash.changeAmount, const PosMoney.som(738900));
      expect(paid.bill.isPaid, isTrue);

      final PosBillResult printed = PosBillResult.fromJson(data('bill_print'));
      expect(printed.bill.status, 'printed');

      final PosRefund refund = PosRefund.fromJson(data('refund'));
      expect(refund.amount, const PosMoney.som(1000));
      expect(refund.fiscal.status, 'issued');

      final PosBilling closed = PosBilling.fromJson(data('close'));
      expect(closed.order.isClosed, isTrue);
      expect(closed.refunds.single.reason, 'xato');
    },
  );

  test('shift and X report', () {
    final PosShift s = PosShift.fromJson(data('shift_open'));
    expect(s.isOpen, isTrue);
    expect(s.openingCash, const PosMoney.som(100000));
    expect(s.openedBy!.name, 'Kamola Kassir');
    expect(s.toSummary().deviceId, s.deviceUuid);

    final PosShiftWithReport current = PosShiftWithReport.fromJson(
      data('shift_current'),
    );
    expect(current.shift!.uuid, s.uuid);
    expect(current.report!.expectedCash, const PosMoney.som(361100));

    final PosShiftWithReport x = PosShiftWithReport.fromJson(data('xreport'));
    expect(x.report!.received, const PosMoney.som(261100));
    expect(x.report!.byTender['cash'], const PosMoney.som(261100));
    expect(x.report!.expectedCash, const PosMoney.som(361100));
    expect(x.report!.movements.map((PosCashMovement m) => m.type), <String>[
      'opening',
      'sale',
    ]);
    // P3-35: every part of the X/Z report is typed (no reading of raw)
    final PosShiftReport r = x.report!;
    expect(r.periodFrom, DateTime.utc(2026, 10, 22, 7, 59, 51));
    expect(r.periodTo, isNull);
    expect(r.sales.billsTotal, const PosMoney.som(261100));
    expect(r.sales.roomFee, const PosMoney.som(150000));
    expect(r.sales.serviceCharge, const PosMoney.som(10100));
    expect(r.sales.averageBill, const PosMoney.som(261100));
    expect(r.tenders.first.key, 'cash');
    expect(r.tenders.first.count, 1);
    expect(r.tenders, hasLength(5));
    expect(r.cards, isEmpty);
    expect(r.byChannel.single.key, 'pos_hall');
    expect(r.byChannel.single.count, 1);
    expect(r.byWaiter.single.waiterId, isNull);
    expect(r.byWaiter.single.bills, 1);
    expect(r.byWaiter.single.serviceCharge, const PosMoney.som(10100));
    expect(r.voids.count, 1);
    expect(r.voids.amount, const PosMoney.som(80000));
    expect(r.discounts.count, 0);
    expect(r.refunds.byMethod, isEmpty);
    expect(r.deposits.taken.cash, PosMoney.zero);
    expect(r.credit.repayments.amount, PosMoney.zero);
    expect(r.cash.opening, const PosMoney.som(100000));
    expect(r.cash.sales, const PosMoney.som(261100));
    expect(r.cash.expected, const PosMoney.som(361100));
    final PosShiftReport full = PosShiftReport.fromJson(
      const PosJson(<String, Object?>{
        'cards': <Object?>[
          <String, Object?>{
            'card_type': 'humo',
            'count': 2,
            'amount': '50000.00',
          },
        ],
        'refunds': <String, Object?>{
          'count': 1,
          'amount': '20000.00',
          'by_method': <Object?>[
            <String, Object?>{
              'method': 'cash',
              'count': 1,
              'amount': '20000.00',
            },
          ],
        },
        'deposits': <String, Object?>{
          'taken': <String, Object?>{
            'count': 1,
            'amount': '300000.00',
            'cash': '300000.00',
          },
          'applied': <String, Object?>{'count': 1, 'amount': '200000.00'},
        },
        'credit': <String, Object?>{
          'sales': <String, Object?>{'count': 1, 'amount': '90000.00'},
          'repayments': <String, Object?>{
            'count': 1,
            'amount': '40000.00',
            'cash': '40000.00',
          },
        },
      }),
    );
    expect(full.cards.single.key, 'humo');
    expect(full.cards.single.count, 2);
    expect(full.refunds.byMethod.single.amount, const PosMoney.som(20000));
    expect(full.deposits.taken.cash, const PosMoney.som(300000));
    expect(full.deposits.applied.amount, const PosMoney.som(200000));
    expect(full.deposits.refunded.count, 0);
    expect(full.credit.sales.amount, const PosMoney.som(90000));
    expect(full.credit.repayments.cash, const PosMoney.som(40000));

    expect(
      PosShiftWithReport.fromJson(
        const PosJson(<String, Object?>{'shift': null, 'report': null}),
      ).shift,
      isNull,
    );
  });

  test('bookings: calendar, booking with deposit', () {
    final PosBookingCalendar cal = PosBookingCalendar.fromJson(
      data('booking_cal'),
    );
    expect(cal.rooms.map((PosCalendarRoom r) => r.name), <String>[
      '1-xona',
      'Tapchan 1',
      'Stol 1',
    ]);
    expect(cal.timezone, 'Asia/Tashkent');

    final PosBooking b = PosBooking.fromJson(data('booking_show'));
    expect(b.status, 'confirmed');
    expect(b.contactPhone, '+998901234567');
    expect(b.depositAvailable, const PosMoney.som(200000));
    expect(b.payments.single.fiscalStatus, 'issued');
    expect(b.startsAt, DateTime.utc(2026, 10, 23, 13));

    final PosDepositResult d = PosDepositResult.fromJson(
      data('booking_deposit'),
    );
    expect(d.payment.remaining, const PosMoney.som(200000));
  });

  test('customer accounts: list, show, repayment, statement, debtors', () {
    final PosAccountPage page = PosAccountPage.fromJson(data('acc_list'));
    expect(page.accounts.single.balance, const PosMoney.som(111100));
    expect(page.accounts.single.creditLimit, const PosMoney.som(1000000));

    final PosAccountWithEntries show = PosAccountWithEntries.fromJson(
      data('acc_show'),
    );
    expect(show.entries.single.type, 'charge');
    expect(show.entries.single.approvedBy!.name, 'Malika Menejer');

    final PosAccountWithEntries repaid = PosAccountWithEntries.fromJson(
      data('acc_repay'),
    );
    expect(repaid.entries.single.amount, const PosMoney.som(-50000));
    expect(repaid.account.balance, const PosMoney.som(111100));

    final PosAccountStatement st = PosAccountStatement.fromJson(
      data('acc_statement'),
    );
    expect(st.closingBalance, const PosMoney.som(111100));
    expect(st.chargesTotal, const PosMoney.som(161100));

    final PosDebtors debtors = PosDebtors.fromJson(data('debtors2'));
    expect(debtors.totalDebt, const PosMoney.som(111100));
    expect(debtors.count, 1);
  });

  test('approval token', () {
    final ApprovalToken t = ApprovalToken.fromJson(data('approval'));
    expect(t.permission, 'orders.void_item');
    expect(t.approvedBy.name, 'Malika Menejer');
    expect(t.approverRole, 'manager');
    expect(t.isExpired(DateTime.utc(2026, 10, 22, 8, 1)), isTrue);
  });

  test('a ref may be an object or a bare id', () {
    expect(
      PosRef.maybe(
        const PosJson(<String, Object?>{'approved_by': 12}),
        'approved_by',
      )!.id,
      12,
    );
    expect(
      PosRef.maybe(
        const PosJson(<String, Object?>{'approved_by': null}),
        'approved_by',
      ),
      isNull,
    );
  });

  test('a cash tender tells what the guest handed over and the change', () {
    const PosTenderInput cash = PosTenderInput.cash(
      PosMoney.som(87000),
      tendered: PosMoney.som(100000),
    );
    expect(cash.tendered, const PosMoney.som(100000));
    expect(cash.change, const PosMoney.som(13000));
    expect(const PosTenderInput.cash(PosMoney.som(5000)).tendered, isNull);
    expect(const PosTenderInput.cash(PosMoney.som(5000)).change, PosMoney.zero);
    const PosTenderInput card = PosTenderInput.card(
      PosMoney.som(5000),
      cardType: 'humo',
      transactionId: '1',
    );
    expect(card.tendered, isNull);
    expect(card.toJson().containsKey('tendered_amount'), isFalse);
  });
}
