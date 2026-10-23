import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P3-22: onlayn navbat va chek repozitoriylari hamda soxta backend'ning kassa qismi (`FakeCashierBackend`) — kassa
/// widget testlari va Linux suratlari shu oqimlarga tayanadi.
void main() {
  late Harness h;
  late FakeCashierBackend backend;

  setUp(() async {
    h = Harness();
    backend = FakeCashierBackend(h.state);
    await h.signIn();
  });

  Future<PosOrder> roomOrder({int guests = 4}) async {
    final String uuid = h.state.seedOrder(tableId: 1, guests: guests);
    return h.pos.orders.get(uuid);
  }

  Future<ApprovalToken> managerToken(String permission) =>
      h.pos.approvals.request(permission: permission, userId: 2, pin: '5926');

  group('OnlineOrderRepository', () {
    test('incoming online orders (oldest first), accept, reject needs orders.cancel or a manager', () async {
      final String older = backend.seedOnlineOrder(minutesAgo: 9);
      final String newer = backend.seedOnlineOrder(
        channel: 'mobile',
        minutesAgo: 1,
      );
      final List<PosOnlineOrder> pending = await h.pos.onlineOrders().pending(
        branchId: 1,
      );
      expect(h.last.uri.path, '/api/v1/pos/orders');
      expect(h.last.uri.queryParameters, <String, String>{
        'scope': 'incoming',
        'branch_id': '1',
      });
      expect(pending.map((PosOnlineOrder o) => o.uuid), <String>[older, newer]);
      expect(pending.first.items.first.displayName, 'Osh (Choyxona oshi)');
      expect(pending.first.items.first.quantityLabel, '1 kg (guruch)');
      expect(pending.first.customerName, 'Aziz');
      expect(pending.first.totalPrice, const PosMoney.som(128000));

      final PosOnlineOrder accepted = await h.pos.onlineOrders().accept(older);
      expect(accepted.status, 'accepted');
      expect(h.last.uri.path, '/api/v1/pos/orders/$older/accept');
      expect(h.last.headers['Idempotency-Key'], matches(uuidV4));
      final List<PosOpenOrder> queue = await h.pos.orders.openOrders();
      expect(queue.single.channel, 'telegram');
      expect(queue.single.online?.customerName, 'Aziz');

      await expectLater(
        h.pos.onlineOrders().reject(newer, reason: PosRejectReason.outOfStock),
        throwsA(isA<PosApprovalRequired>()),
      );
      expect(Harness.bodyOf(h.last), <String, Object?>{
        'reason': 'Mahsulot qolmagan',
        'cancel_reason': 'out_of_stock',
      });

      final ApprovalToken token = await managerToken('orders.cancel');
      final PosOnlineOrder rejected = await h.pos.onlineOrders().reject(
        newer,
        reason: PosRejectReason.outOfStock,
        note: 'Osh tugadi',
        approvalToken: token.token,
      );
      expect(rejected.status, 'rejected');
      expect(Harness.bodyOf(h.last)['reason'], 'Osh tugadi');
      expect(await h.pos.onlineOrders().pending(), isEmpty);
    });
  });

  group('billing', () {
    test('equal split: remainder tiyin on the first bill; mixed cash + card closes the order', () async {
      h.state.openShift();
      final PosOrder order = await roomOrder();
      PosBilling billing = await h.pos.billing.createBills(
        order.uuid,
        version: order.version,
        split: const PosSplit.equal(3),
      );
      expect(billing.bills, hasLength(3));
      final PosMoney total = billing.order.totalPrice;
      expect(PosMoney.sum(billing.bills.map((PosBill b) => b.total)), total);
      expect(billing.bills.first.total >= billing.bills.last.total, isTrue);

      for (final PosBill bill in billing.bills) {
        await expectLater(
          h.pos.billing.pay(
            bill.uuid,
            tenders: <PosTenderInput>[
              PosTenderInput.card(
                bill.total,
                cardType: 'uzcard',
                transactionId: '',
              ),
            ],
          ),
          throwsA(
            isA<PosRejected>().having(
              (PosRejected e) => e.reason,
              'reason',
              'transaction_id_required',
            ),
          ),
        );
        final PosBillResult paid = await h.pos.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[
            const PosTenderInput.cash(
              PosMoney.som(50000),
              tendered: PosMoney.som(100000),
            ),
            PosTenderInput.card(
              bill.total - const PosMoney.som(50000),
              cardType: 'humo',
              transactionId: 'T1',
            ),
          ],
        );
        expect(paid.bill.isPaid, isTrue);
        expect(
          paid.bill.payments.first.changeAmount,
          const PosMoney.som(50000),
        );
        expect(paid.bill.fiscal.status, 'issued');
      }
      billing = await h.pos.billing.bills(order.uuid);
      expect(billing.order.isClosed, isTrue);
      expect(h.state.orders[order.uuid]!['status'], 'delivered');
    });

    test(
      'items split with a fractional kg share; uncovered lines are refused',
      () async {
        final PosOrder order = await roomOrder();
        final PosOrderItem osh = order.items.firstWhere(
          (PosOrderItem i) => i.unit == PosUnit.kg,
        );
        final PosOrderItem tea = order.items.firstWhere(
          (PosOrderItem i) => i.unit == PosUnit.piece,
        );
        await expectLater(
          h.pos.billing.createBills(
            order.uuid,
            version: order.version,
            split: PosSplit.items(<PosSplitBill>[
              PosSplitBill(
                items: <int, PosQuantity>{osh.id: PosQuantity.parse('0.5')},
              ),
            ]),
          ),
          throwsA(
            isA<PosRejected>().having(
              (PosRejected e) => e.reason,
              'reason',
              'items_not_covered',
            ),
          ),
        );
        final PosBilling billing = await h.pos.billing.createBills(
          order.uuid,
          version: order.version,
          split: PosSplit.items(<PosSplitBill>[
            PosSplitBill(
              items: <int, PosQuantity>{
                osh.id: PosQuantity.parse('0.5'),
                tea.id: tea.quantity,
              },
            ),
            PosSplitBill(
              items: <int, PosQuantity>{osh.id: PosQuantity.parse('1')},
            ),
          ]),
        );
        expect(billing.bills.map((PosBill b) => b.items.length), <int>[2, 1]);
        expect(billing.bills.last.items.single.quantity, PosQuantity.one);
        expect(
          PosMoney.sum(billing.bills.map((PosBill b) => b.total)),
          billing.order.totalPrice,
        );
      },
    );

    test('fiscal pending → retry issues the receipt and closes; the receipt comes along', () async {
      h.state.openShift();
      backend
        ..fiscalMode = FakeFiscalMode.pending
        ..retryFailures = 1;
      final PosOrder order = await roomOrder();
      final PosBill bill = (await h.pos.billing.createBills(
        order.uuid,
        version: order.version,
      )).bills.single;
      PosBillResult r = await h.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
      );
      expect(r.bill.fiscal.isPending, isTrue);
      expect(r.order.fiscalPending, isTrue);
      expect((await h.pos.orders.openOrders()).single.fiscalPending, isTrue);

      r = await h.pos.billing.fiscalRetry(bill.uuid);
      expect(r.bill.fiscal.status, 'pending');
      r = await h.pos.billing.fiscalRetry(bill.uuid);
      expect(r.bill.fiscal.status, 'issued');
      expect(r.order.isClosed, isTrue);

      // P3-26: the payment's answer carries the registered receipt; the till reads it again with pos.take_payment
      final PosFiscalReceiptDetail? inline = r.bill.fiscal.receipt;
      expect(inline, isNotNull);
      expect(inline!.fiscalSign, isNotNull);
      expect(inline.receiptNumber, isNotNull);
      expect(inline.terminalId, 'EP000000000123');
      final FiscalReceiptRepository receipts = FiscalReceiptRepository(
        h.pos.http,
      );
      final PosFiscalReceiptDetail detail = await receipts.get(
        r.bill.fiscal.receiptUuid!,
      );
      expect(
        h.last.uri.path,
        '/api/v1/pos/fiscal-receipts/${r.bill.fiscal.receiptUuid}',
      );
      expect(detail.receiptType, FiscalReceiptType.sale);
      expect(detail.fiscalSign, inline.fiscalSign);
      expect(detail.qrUrl, startsWith('https://'));
      expect(detail.lines.first.mxikCode, hasLength(17));
      expect(detail.nonFiscal, isTrue);
      h.state.rolePermissions['cashier']!.remove('pos.take_payment');
      await expectLater(
        receipts.get(r.bill.fiscal.receiptUuid!),
        throwsA(isA<PosForbidden>()),
      );
    });

    test('nasiya needs a manager approval and the limit; prepayment comes from the booking deposit', () async {
      h.state.openShift();
      final String account = backend.seedAccount(
        name: 'Jamshid',
        limit: '1000000.00',
      );
      final String booking = backend.seedBooking(
        tableId: 2,
        start: h.now.add(const Duration(minutes: 10)),
        deposit: '50000.00',
      );
      final PosArrival arrival = await h.pos.bookings.arrive(booking);
      final PosOrder order = await h.pos.orders.get(arrival.orderUuid);
      expect(order.table!.id, 2);
      await h.pos.orders.addRound(
        order.uuid,
        version: order.version,
        items: <PosLineInput>[
          PosLineInput(
            variantUuid: FakePosState.variantUuid(1),
            quantity: const PosQuantity.whole(4),
          ),
        ],
      );
      final PosOrder fresh = await h.pos.orders.get(order.uuid);
      final PosBill bill = (await h.pos.billing.createBills(
        fresh.uuid,
        version: fresh.version,
      )).bills.single;

      await expectLater(
        h.pos.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[
            const PosTenderInput.prepayment(PosMoney.som(60000)),
          ],
        ),
        throwsA(
          isA<PosRejected>().having(
            (PosRejected e) => e.data['available'],
            'available',
            '50000.00',
          ),
        ),
      );
      await expectLater(
        h.pos.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[
            PosTenderInput.account(
              const PosMoney.som(10000),
              accountUuid: account,
            ),
          ],
        ),
        throwsA(isA<PosApprovalRequired>()),
      );
      final ApprovalToken token = await managerToken(
        PosPermissions.customerAccountsManage,
      );
      final PosBillResult r = await h.pos.billing.pay(
        bill.uuid,
        approvalToken: token.token,
        tenders: <PosTenderInput>[
          const PosTenderInput.prepayment(PosMoney.som(50000)),
          PosTenderInput.account(
            bill.total - const PosMoney.som(50000),
            accountUuid: account,
          ),
        ],
      );
      expect(r.bill.isPaid, isTrue);
      expect(
        (await h.pos.bookings.get(booking)).depositAvailable,
        PosMoney.zero,
      );
      expect(
        (await h.pos.accounts.get(account)).account.balance,
        bill.total - const PosMoney.som(50000),
      );
    });

    test('refund within the shift needs pos.refund (approval)', () async {
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
      final PosPayment payment = paid.bill.payments.single;
      await expectLater(
        h.pos.billing.refund(
          paymentUuid: payment.uuid,
          amount: const PosMoney.som(1000),
          reason: 'xato',
        ),
        throwsA(isA<PosApprovalRequired>()),
      );
      final ApprovalToken token = await managerToken(PosPermissions.refund);
      final PosRefund refund = await h.pos.billing.refund(
        paymentUuid: payment.uuid,
        amount: const PosMoney.som(1000),
        reason: 'xato',
        approvalToken: token.token,
      );
      expect(refund.fiscal.status, 'issued');
      expect(
        (await h.pos.billing.bills(order.uuid)).refunds.single.amount,
        const PosMoney.som(1000),
      );
    });
  });

  group('shift', () {
    test(
      'cash movements, X report, Z refusals and a forced close with approval',
      () async {
        final PosShift opened = await h.pos.shifts.open(
          openingCash: const PosMoney.som(100000),
        );
        await expectLater(
          h.pos.shifts.cashMovement(
            opened.uuid,
            type: PosCashMovementType.purchase,
            category: 'bazaar',
            amount: const PosMoney.som(30000),
          ),
          throwsA(isA<PosApprovalRequired>()),
        );
        final PosCashMovement purchase = await h.pos.shifts.cashMovement(
          opened.uuid,
          type: PosCashMovementType.purchase,
          category: 'bazaar',
          amount: const PosMoney.som(30000),
          approvalToken: (await managerToken(PosPermissions.cashInOut)).token,
        );
        expect(purchase.type, 'purchase');
        await expectLater(
          h.pos.shifts.cashMovement(
            opened.uuid,
            type: PosCashMovementType.cashOut,
            category: 'collection',
            amount: const PosMoney.som(500000),
            approvalToken: (await managerToken(PosPermissions.cashInOut)).token,
          ),
          throwsA(
            isA<PosConflict>().having(
              (PosConflict e) => e.reason,
              'reason',
              'not_enough_cash',
            ),
          ),
        );
        final PosShiftWithReport x = await h.pos.shifts.xReport(opened.uuid);
        expect(x.report!.expectedCash, const PosMoney.som(70000));
        expect(x.report!.movements.map((PosCashMovement m) => m.type), <String>[
          'opening',
          'purchase',
        ]);

        h.state.seedOrder(tableId: 3);
        await expectLater(
          h.pos.shifts.close(
            opened.uuid,
            countedBreakdown: const <int, int>{50000: 1, 10000: 2},
          ),
          throwsA(
            isA<PosConflict>().having(
              (PosConflict e) => e.reason,
              'reason',
              'shift_has_open_orders',
            ),
          ),
        );
        await expectLater(
          h.pos.shifts.close(
            opened.uuid,
            countedBreakdown: const <int, int>{50000: 1, 10000: 2},
            force: true,
            reason: 'Xona ertaga yopiladi',
          ),
          throwsA(isA<PosApprovalRequired>()),
        );
        final PosShift closed = await h.pos.shifts.close(
          opened.uuid,
          countedBreakdown: const <int, int>{50000: 1, 10000: 1},
          force: true,
          reason: 'Xona ertaga yopiladi',
          approvalToken: (await managerToken(PosPermissions.ordersCancel))
              .token,
        );
        expect(closed.isOpen, isFalse);
        expect(closed.countedCash, const PosMoney.som(60000));
        expect(closed.difference, const PosMoney.som(-10000));
        expect(closed.zNumber, 1);
        expect(closed.forceClosed, isTrue);
        expect(closed.raw['summary'], isA<Map<String, Object?>>());
        expect((await h.pos.shifts.current()).shift, isNull);
      },
    );
  });

  group('bookings and accounts', () {
    test(
      'conflict, deposit (idempotent), arrive with held pre-order lines',
      () async {
        final DateTime start = DateTime.utc(2026, 10, 22, 14);
        final PosBooking booking = await h.pos.bookings.create(
          PosBookingInput(
            diningTableId: 1,
            startsAt: start,
            contactName: 'Jamshid',
            contactPhone: '90 123 45 67',
            guestsPlanned: 8,
          ),
        );
        expect(booking.contactPhone, '+998901234567');
        expect(booking.endsAt, start.add(const Duration(hours: 5)));
        await expectLater(
          h.pos.bookings.create(
            PosBookingInput(
              diningTableId: 1,
              startsAt: start.add(const Duration(hours: 2)),
            ),
          ),
          throwsA(
            isA<PosConflict>()
                .having(
                  (PosConflict e) => e.reason,
                  'reason',
                  'booking_conflict',
                )
                .having(
                  (PosConflict e) => e.message,
                  'message',
                  contains('1-xona'),
                ),
          ),
        );
        // 18:00 da tugagan va 18:00 da boshlangan bronlar to'qnashmaydi
        await h.pos.bookings.create(
          PosBookingInput(
            diningTableId: 1,
            startsAt: start.add(const Duration(hours: 5)),
          ),
        );

        final PosBooking withItem = await h.pos.bookings.addItem(
          booking.uuid,
          productVariantId: 3,
          quantity: PosQuantity.parse('2.5'),
        );
        expect(withItem.items.single.quantity, PosQuantity.parse('2.5'));

        final PosDepositResult d1 = await h.pos.bookings.takeDeposit(
          booking.uuid,
          amount: const PosMoney.som(200000),
          tender: 'cash',
          idempotencyKey: 'k1',
        );
        final PosDepositResult d2 = await h.pos.bookings.takeDeposit(
          booking.uuid,
          amount: const PosMoney.som(200000),
          tender: 'cash',
          idempotencyKey: 'k1',
        );
        expect(d2.payment.uuid, d1.payment.uuid);
        expect(d2.booking.depositAvailable, const PosMoney.som(200000));
        expect(d1.payment.fiscalStatus, 'issued');

        final PosArrival arrival = await h.pos.bookings.arrive(booking.uuid);
        final PosOrder order = await h.pos.orders.get(arrival.orderUuid);
        expect(order.items.single.fireMode, PosFireMode.hold);
        await expectLater(
          h.pos.bookings.arrive(booking.uuid),
          throwsA(
            isA<PosConflict>().having(
              (PosConflict e) => e.data['order_uuid'],
              'order',
              arrival.orderUuid,
            ),
          ),
        );
        final PosBookingCalendar calendar = await h.pos.bookings.calendar(
          from: '2026-10-22',
          to: '2026-10-22',
        );
        expect(calendar.rooms.first.bookings.first.status, 'arrived');
      },
    );

    test('debtors, repayment with its credit receipt and a create with a manager approval', () async {
      final String a = backend.seedAccount(
        name: 'Jamshid aka',
        phone: '+998901112233',
        limit: '1000000.00',
        debt: '161100.00',
      );
      backend.seedAccount(name: 'Sardor', debt: '20000.00');
      final PosDebtors debtors = await h.pos.accounts.debtors();
      expect(debtors.totalDebt, const PosMoney.som(181100));
      expect(debtors.accounts.first.uuid, a);
      expect(
        (await h.pos.accounts.list(query: '1112')).accounts.single.uuid,
        a,
      );
      await expectLater(
        h.pos.accounts.repay(
          a,
          amount: const PosMoney.som(200000),
          tender: 'cash',
        ),
        throwsA(isA<PosRejected>()),
      );
      final PosAccountWithEntries paid = await h.pos.accounts.repay(
        a,
        amount: const PosMoney.som(50000),
        tender: 'cash',
      );
      expect(paid.account.balance, const PosMoney.som(111100));
      expect(paid.entries.single.fiscalStatus, 'issued');
      expect(paid.account.availableCredit, const PosMoney.som(888900));
      // P3-26: the CREDIT receipt to print comes with the repayment
      expect(paid.fiscal?.receipt?.receiptType, FiscalReceiptType.credit);
      expect(paid.fiscal?.receipt?.total, const PosMoney.som(50000));
      expect(paid.fiscal?.receipt?.fiscalSign, isNull);
      // A cashier opens an account with a manager's approval
      await expectLater(
        h.pos.accounts.create(const PosAccountInput(name: 'Yangi')),
        throwsA(isA<PosApprovalRequired>()),
      );
      final ApprovalToken token = await managerToken(
        'customer_accounts.manage',
      );
      final PosCustomerAccount created = await h.pos.accounts.create(
        const PosAccountInput(name: 'Yangi'),
        approvalToken: token.token,
      );
      expect(created.name, 'Yangi');
      expect(Harness.bodyOf(h.last)['approval_token'], token.token);
    });
  });
}

extension on PosData {
  OnlineOrderRepository onlineOrders() => OnlineOrderRepository(http);
}
