import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/order/frequent_items.dart';
import 'package:rizqim_cashier/src/features/order/kitchen_tickets.dart';
import 'package:rizqim_cashier/src/features/order/order_controller.dart';

import 'support/harness.dart';
import 'support/p22.dart';

/// P3-35 follow-up: deposit and nasiya refunds (no cash leaves the drawer), cancelling an order whose payments are
/// all refunded, `paid_bill_affected` / `shift_closed` answers and the offline price notice after a sync.
void main() {
  const String manager = 'Malika Rahimova';

  Future<String> paidWithDepositAndNasiya(
    WidgetTester tester,
    P22 p, {
    required String account,
    required String booking,
  }) async {
    final PosArrival arrival = await settle(
      tester,
      p.h.data.bookings.arrive(booking),
    );
    final PosOrder order = await settle(
      tester,
      p.h.data.orders.get(arrival.orderUuid),
    );
    await settle(
      tester,
      p.h.data.orders.addRound(
        order.uuid,
        version: order.version,
        items: <PosLineInput>[
          PosLineInput(
            variantUuid: FakePosState.variantUuid(1),
            quantity: const PosQuantity.whole(4),
          ),
        ],
      ),
    );
    final PosOrder fresh = await settle(
      tester,
      p.h.data.orders.get(order.uuid),
    );
    final PosBill bill = (await settle(
      tester,
      p.h.data.billing.createBills(fresh.uuid, version: fresh.version),
    )).bills.single;
    await settle(
      tester,
      p.h.data.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[
          const PosTenderInput.prepayment(PosMoney.som(50000)),
          PosTenderInput.account(
            bill.total - const PosMoney.som(50000),
            accountUuid: account,
          ),
        ],
      ),
    );
    return bill.uuid;
  }

  testWidgets(
    'deposit and nasiya parts are offered for refund: no cash leaves the drawer, the deposit and the debt are told',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String account = p.backend.seedAccount(
        name: 'Jamshid',
        limit: '1000000.00',
      );
      final String booking = p.backend.seedBooking(
        tableId: 2,
        start: p.h.now.add(const Duration(minutes: 10)),
        deposit: '50000.00',
      );
      await pumpHome(tester, p.h, name: manager);
      final String billUuid = await paidWithDepositAndNasiya(
        tester,
        p,
        account: account,
        booking: booking,
      );
      final String orderUuid = p.backend.bills.keys.single;
      await openBilling(tester, orderUuid);
      expect(find.text('Buyurtma yopildi'), findsOneWidget);

      // The deposit part first
      await tapKey(tester, 'refund-$billUuid');
      expect(find.textContaining('Bron avansi · '), findsOneWidget);
      expect(find.textContaining('Nasiya · '), findsOneWidget);
      expect(
        find.textContaining('summa bron avansiga qaytadi'),
        findsOneWidget,
      );
      expect(find.textContaining('Kassadan pul chiqmaydi'), findsOneWidget);
      await tapText(tester, "Xato to'lov");
      await tapButton(tester, 'Qaytarish');
      expect(p.backend.refunds.single['method'], 'prepayment');
      expect(p.backend.refunds.single['amount'], '50000.00');
      expect(
        find.textContaining('bron avansiga qaytdi: kassadan pul chiqmadi'),
        findsOneWidget,
      );
      expect(p.drawerKicks, 0, reason: 'no cash moved');
      expect(p.receipts.last, contains('QAYTARISH CHEKI'));
      final PosBooking after = await settle(
        tester,
        p.h.data.bookings.get(booking),
      );
      expect(after.depositAvailable, const PosMoney.som(50000));

      // Then the nasiya part: the only one left
      await tapKey(tester, 'refund-$billUuid');
      expect(find.textContaining('Bron avansi · '), findsNothing);
      await tapText(tester, 'Nasiya · ', contains: true);
      expect(
        find.textContaining("mijozning nasiya qarzidan ayiriladi"),
        findsOneWidget,
      );
      await tapText(tester, "Xato to'lov");
      await tapButton(tester, 'Qaytarish');
      expect(p.backend.refunds.last['method'], 'account');
      expect(find.textContaining('Mijoz qarzi '), findsOneWidget);
      final PosAccountWithEntries debt = await settle(
        tester,
        p.h.data.accounts.get(account),
      );
      expect(debt.account.balance, PosMoney.zero);
      expect(p.drawerKicks, 0);
      expect(
        find.byKey(ValueKey<String>('refund-$billUuid')),
        findsNothing,
        reason: 'nothing left to refund',
      );
    },
  );

  testWidgets(
    'the guest repaid part of the debt: more_than_debt stays in the dialog with the server text',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String account = p.backend.seedAccount(
        name: 'Jamshid',
        limit: '1000000.00',
      );
      final String booking = p.backend.seedBooking(
        tableId: 2,
        start: p.h.now.add(const Duration(minutes: 10)),
        deposit: '50000.00',
      );
      await pumpHome(tester, p.h, name: manager);
      final String billUuid = await paidWithDepositAndNasiya(
        tester,
        p,
        account: account,
        booking: booking,
      );
      final PosAccountWithEntries before = await settle(
        tester,
        p.h.data.accounts.get(account),
      );
      await settle(
        tester,
        p.h.data.accounts.repay(
          account,
          amount: before.account.balance - const PosMoney.som(1000),
          tender: 'cash',
        ),
      );
      await openBilling(tester, p.backend.bills.keys.single);
      await tapKey(tester, 'refund-$billUuid');
      await tapText(tester, 'Nasiya · ', contains: true);
      await tapText(tester, "Xato to'lov");
      await tapButton(tester, 'Qaytarish');
      expect(p.backend.refunds, isEmpty);
      expect(
        find.textContaining("Mijoz qarzining bir qismini to'lagan"),
        findsOneWidget,
      );
      expect(find.textContaining('1000.00'), findsOneWidget);
    },
  );

  testWidgets(
    'every payment refunded: the open order may be cancelled from the bill screen',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h, name: manager);
      final PosOrder order = await settle(tester, p.h.data.orders.get(uuid));
      final PosBilling billing = await settle(
        tester,
        p.h.data.billing.createBills(
          uuid,
          version: order.version,
          split: const PosSplit.equal(2),
        ),
      );
      final PosBill first = billing.bills.first;
      await settle(
        tester,
        p.h.data.billing.pay(
          first.uuid,
          tenders: <PosTenderInput>[PosTenderInput.cash(first.total)],
        ),
      );
      await openBilling(tester, uuid);
      expect(
        find.byKey(const ValueKey<String>('cancel-after-refund')),
        findsNothing,
      );
      await tapText(tester, '1-schyot');
      await tapButton(tester, 'Pulni qaytarish');
      expect(find.textContaining('Naqd pul kassadan chiqadi'), findsOneWidget);
      await tapText(tester, 'Mijoz voz kechdi');
      await tapButton(tester, 'Qaytarish');
      expect(p.backend.refunds.single['amount'], first.total.toApiString());
      expect(find.textContaining("hamma to'lovi qaytarildi"), findsOneWidget);

      await tapKey(tester, 'cancel-after-refund');
      await tapText(tester, 'Mehmonlar ketdi');
      // The dialog's confirm (the page's own button is under it)
      await tester.tap(buttonLabeled('Buyurtmani bekor qilish').last);
      await tester.pumpAndSettle();
      expect(p.state.orders[uuid]!['status'], 'canceled');
      expect(find.textContaining('Buyurtma bekor qilindi'), findsOneWidget);
    },
  );

  testWidgets(
    'a Z closed the shift meanwhile: the payment is refused with the Uzbek reason and the shift is read again',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      await openBilling(tester, uuid);
      await tapButton(tester, 'Schyot tuzish');
      await tapText(tester, "To'lash: ", contains: true);
      var currentReads = 0;
      p.h.adapter.respond = (RequestOptions o) {
        if (o.method == 'POST' && o.uri.path.endsWith('/payments')) {
          return const FakeResponse(409, <String, Object?>{
            'success': false,
            'message': 'Smena yopilgan.',
            'data': <String, Object?>{'reason': 'shift_closed'},
          });
        }
        if (o.method == 'GET' && o.uri.path.endsWith('/shifts/current')) {
          currentReads++;
        }
        return null;
      };
      await tapKey(tester, 'payment-submit');
      expect(find.textContaining('Smena shu orada yopildi'), findsOneWidget);
      expect(buttonLabeled('Smenani ochish'), findsOneWidget);
      expect(currentReads, greaterThan(0));
      expect(p.backend.bills[uuid]!.single['payments'], isEmpty);
    },
  );

  group('order editor', () {
    Future<T> direct<T>(Future<T> Function(String? token) action) =>
        action(null);

    Future<OrderController> editor(
      WidgetTester tester,
      CashierHarness h,
      String uuid,
    ) async {
      await pumpHome(tester, h);
      final OrderController ctl = OrderController(
        data: h.data,
        tickets: KitchenTickets(
          printing: h.services.printing,
          bootstrap: () => h.data.session.bootstrap,
          now: () => h.now,
        ),
        frequent: FrequentItems.of(h.data),
        orderUuid: uuid,
      );
      addTearDown(ctl.dispose);
      await settle(tester, ctl.load());
      return ctl;
    }

    testWidgets(
      'paid_bill_affected: the Uzbek reason is shown and the order is read again from the server',
      (WidgetTester tester) async {
        final CashierHarness h = CashierHarness();
        final String uuid = h.state.seedOrder(tableId: 1, guests: 2);
        final OrderController ctl = await editor(tester, h, uuid);
        final List<OrderNotice> notices = <OrderNotice>[];
        ctl.notices.listen(notices.add);
        var reads = 0;
        h.adapter.respond = (RequestOptions o) {
          if (o.method == 'POST' && o.uri.path.endsWith('/guests')) {
            return const FakeResponse(409, <String, Object?>{
              'success': false,
              'message': "Bu o'zgarish to'lovi olingan schyotga tegadi.",
              'data': <String, Object?>{
                'reason': 'paid_bill_affected',
                'bills_reason': 'bills_out_of_date',
              },
            });
          }
          if (o.method == 'GET' && o.uri.path.endsWith('/orders/$uuid')) {
            reads++;
          }
          return null;
        };
        expect(
          await settle(tester, ctl.setGuests(1, approve: direct)),
          isFalse,
        );
        await tester.pumpAndSettle();
        expect(notices.single.error, isTrue);
        expect(
          notices.single.message,
          startsWith("Schyotning bir qismi to'langan"),
        );
        expect(reads, 1, reason: 'reloaded once');
      },
    );

    testWidgets(
      'a sync that kept a line at the server price tells the cashier and reloads the order',
      (WidgetTester tester) async {
        final SyncStore store = await SyncStore.open(MemorySyncStorage());
        final CashierHarness h = CashierHarness(offlineStore: store);
        final String uuid = h.state.seedOrder(tableId: 1, guests: 2);
        final OrderController ctl = await editor(tester, h, uuid);
        final List<OrderNotice> notices = <OrderNotice>[];
        ctl.notices.listen(notices.add);
        var reads = 0;
        h.adapter.respond = (RequestOptions o) {
          if (o.method == 'GET' && o.uri.path.endsWith('/orders/$uuid')) {
            reads++;
          }
          return null;
        };
        SyncOperation op(Map<String, Object?> refs, {String? order}) =>
            SyncOperation(
              opId: 'op-${refs.length}',
              seq: 1,
              type: SyncOpType.orderRound,
              occurredAt: h.now,
              staffId: 3,
              payload: <String, Object?>{'order': ?order},
            ).answered(
              SyncResult(
                opId: 'op',
                seq: 1,
                status: SyncOpStatus.conflict,
                reason: 'offline_price_mismatch',
                refs: refs,
              ),
              h.now,
            );

        // Another order's answer: nothing here
        await settle(
          tester,
          ctl.onSynced(<SyncOperation>[
            op(<String, Object?>{'order_uuid': 'other'}),
          ]),
        );
        expect(notices, isEmpty);
        expect(reads, 0);

        await settle(
          tester,
          ctl.onSynced(<SyncOperation>[
            op(<String, Object?>{
              'order_uuid': uuid,
              'local_number': 'K1-0005',
              'flags': <Object?>[
                <String, Object?>{
                  'flag': 'offline_price_mismatch',
                  'name': 'Osh',
                  'device_price': '40000.00',
                  'server_price': '45000.00',
                  'line_id': 'l1',
                },
                <String, Object?>{
                  'flag': 'device_time_clamped',
                  'device_time': '2026-10-23T10:00:00Z',
                  'used_time': '2026-10-23T10:20:00Z',
                },
              ],
            }),
          ]),
        );
        expect(notices.single.error, isTrue);
        expect(notices.single.message, contains("K1-0005: 'Osh'"));
        expect(notices.single.message, contains("40000 so'mdan"));
        expect(notices.single.message, contains("45000 so'm qo'yildi"));
        expect(reads, 1);
      },
    );
  });
}
