import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// Schyot, smena, bron va nasiya repozitoriylari: to'g'ri yo'l, tana, `Idempotency-Key` va haqiqiy javobni o'qish.
void main() {
  late Harness h;

  setUp(() async {
    h = Harness();
    await h.signIn();
  });

  /// Keyingi so'rovga [fixtureName] javobi; so'rov qaytariladi.
  Future<RequestOptions> call(
    String fixtureName,
    Future<Object?> Function() action, {
    int status = 200,
  }) async {
    h.adapter.respond = (RequestOptions o) =>
        FakeResponse(status, fixture(fixtureName));
    await action();
    return h.last;
  }

  void expectChange(RequestOptions r, String method, String path) {
    expect(r.method, method);
    expect(r.uri.path, '/api$path');
    expect(r.headers['Idempotency-Key'], matches(uuidV4));
  }

  group('BillingRepository', () {
    test(
      'bills, split equal, print, mixed payment, fiscal retry, close, refund',
      () async {
        RequestOptions r = await call(
          'bills_create',
          () => h.pos.billing.bills('o1', all: true),
        );
        expect(r.uri.path, '/api/v1/pos/orders/o1/bills');
        expect(r.uri.queryParameters, <String, String>{'all': '1'});

        r = await call(
          'bills_create',
          () => h.pos.billing.createBills(
            'o1',
            version: 4,
            split: const PosSplit.equal(2),
          ),
        );
        expectChange(r, 'POST', '/v1/pos/orders/o1/bills');
        expect(Harness.bodyOf(r), <String, Object?>{
          'version': 4,
          'mode': 'equal',
          'parts': 2,
        });

        r = await call(
          'bills_create',
          () => h.pos.billing.createBills(
            'o1',
            version: 4,
            split: const PosSplit.items(<PosSplitBill>[
              PosSplitBill(
                items: <int, PosQuantity>{3: PosQuantity.milli(500)},
                guests: 2,
              ),
            ]),
          ),
        );
        expect(Harness.bodyOf(r), <String, Object?>{
          'version': 4,
          'mode': 'items',
          'bills': <Object?>[
            <String, Object?>{
              'items': <Object?>[
                <String, Object?>{'order_item_id': 3, 'quantity': '0.500'},
              ],
              'guests': 2,
            },
          ],
        });

        r = await call(
          'bill_print',
          () => h.pos.billing.printBill('b1', approvalToken: 't'),
        );
        expectChange(r, 'POST', '/v1/pos/bills/b1/print');
        expect(Harness.bodyOf(r), <String, Object?>{'approval_token': 't'});

        late PosBillResult paid;
        r = await call('bill_pay', () async {
          paid = await h.pos.billing.pay(
            'b1',
            tenders: const <PosTenderInput>[
              PosTenderInput.cash(
                PosMoney.som(100000),
                tendered: PosMoney.som(200000),
              ),
              PosTenderInput.card(
                PosMoney.som(61100),
                cardType: 'uzcard',
                transactionId: 'T1',
              ),
              PosTenderInput.qr(PosMoney.som(1), reference: 'Q'),
              PosTenderInput.prepayment(PosMoney.som(2)),
              PosTenderInput.account(PosMoney.som(3), accountUuid: 'acc'),
            ],
          );
          return null;
        });
        expectChange(r, 'POST', '/v1/pos/bills/b1/payments');
        expect(Harness.bodyOf(r)['tenders'], <Object?>[
          <String, Object?>{
            'tender': 'cash',
            'amount': '100000.00',
            'tendered_amount': '200000.00',
          },
          <String, Object?>{
            'tender': 'card',
            'amount': '61100.00',
            'card_type': 'uzcard',
            'transaction_id': 'T1',
          },
          <String, Object?>{'tender': 'qr', 'amount': '1.00', 'reference': 'Q'},
          <String, Object?>{'tender': 'prepayment', 'amount': '2.00'},
          <String, Object?>{
            'tender': 'account',
            'amount': '3.00',
            'account_uuid': 'acc',
          },
        ]);
        expect(
          paid.bill.payments.single.changeAmount,
          const PosMoney.som(738900),
        );

        r = await call('bill_pay', () => h.pos.billing.fiscalRetry('b1'));
        expectChange(r, 'POST', '/v1/pos/bills/b1/fiscal-retry');

        r = await call('close', () => h.pos.billing.closeOrder('o1'));
        expectChange(r, 'POST', '/v1/pos/orders/o1/close');

        late PosRefund refund;
        r = await call('refund', () async {
          refund = await h.pos.billing.refund(
            paymentUuid: 'p1',
            amount: const PosMoney.som(1000),
            reason: 'xato',
          );
          return null;
        });
        expectChange(r, 'POST', '/v1/pos/refunds');
        expect(Harness.bodyOf(r), <String, Object?>{
          'payment_uuid': 'p1',
          'amount': '1000.00',
          'reason': 'xato',
        });
        expect(refund.fiscal.status, 'issued');
      },
    );
  });

  group('ShiftRepository', () {
    test('current, open, X, cash movement, close', () async {
      RequestOptions r = await call(
        'shift_current',
        () => h.pos.shifts.current(),
      );
      expect(r.uri.path, '/api/v1/pos/shifts/current');

      r = await call(
        'shift_open',
        () => h.pos.shifts.open(openingCash: const PosMoney.som(100000)),
        status: 201,
      );
      expectChange(r, 'POST', '/v1/pos/shifts');
      expect(Harness.bodyOf(r), <String, Object?>{'opening_cash': '100000.00'});

      r = await call('xreport', () => h.pos.shifts.xReport('s1'));
      expect(r.uri.path, '/api/v1/pos/shifts/s1/x-report');

      h.adapter.respond = (RequestOptions o) =>
          FakeResponse(201, <String, Object?>{
            'success': true,
            'message': '',
            'data': <String, Object?>{
              'movement': <String, Object?>{
                'uuid': 'm1',
                'type': 'cash_out',
                'category': 'supplies',
                'amount': '5000.00',
                'note': 'salfetka',
                'employee_id': null,
                'approved_by': 2,
              },
              'shift': fixtureData('shift_open'),
            },
          });
      final PosCashMovement m = await h.pos.shifts.cashMovement(
        's1',
        type: PosCashMovementType.cashOut,
        category: 'supplies',
        amount: const PosMoney.som(5000),
        note: 'salfetka',
        approvalToken: 't',
      );
      expectChange(h.last, 'POST', '/v1/pos/shifts/s1/cash-movements');
      expect(Harness.bodyOf(h.last), <String, Object?>{
        'type': 'cash_out',
        'category': 'supplies',
        'amount': '5000.00',
        'note': 'salfetka',
        'approval_token': 't',
      });
      expect(m.approvedBy!.id, 2);

      r = await call(
        'shift_open',
        () => h.pos.shifts.close(
          's1',
          countedBreakdown: <int, int>{100000: 2, 50000: 1},
          force: true,
          reason: 'kech',
        ),
      );
      expectChange(r, 'POST', '/v1/pos/shifts/s1/close');
      expect(Harness.bodyOf(r), <String, Object?>{
        'counted_breakdown': <String, Object?>{'100000': 2, '50000': 1},
        'force': true,
        'reason': 'kech',
      });
    });
  });

  group('BookingRepository', () {
    test('calendar, create, deposit, arrive, refund deposit', () async {
      await call(
        'booking_cal',
        () => h.pos.bookings.calendar(from: '2026-10-22', to: '2026-10-23'),
      );

      RequestOptions r = await call(
        'booking_show',
        () => h.pos.bookings.create(
          PosBookingInput(
            diningTableId: 1,
            startsAt: DateTime.utc(2026, 10, 23, 13),
            guestsPlanned: 8,
            eventType: 'gap',
            contactName: 'Jamshid',
            contactPhone: '901234567',
            source: 'phone',
            minSpend: const PosMoney.som(1000000),
          ),
        ),
        status: 201,
      );
      expectChange(r, 'POST', '/v1/pos/bookings');
      expect(Harness.bodyOf(r), <String, Object?>{
        'dining_table_id': 1,
        'starts_at': '2026-10-23T13:00:00.000Z',
        'guests_planned': 8,
        'event_type': 'gap',
        'contact_name': 'Jamshid',
        'contact_phone': '901234567',
        'source': 'phone',
        'min_spend': '1000000.00',
      });

      late PosDepositResult d;
      r = await call('booking_deposit', () async {
        d = await h.pos.bookings.takeDeposit(
          'bk',
          amount: const PosMoney.som(200000),
          tender: 'cash',
        );
        return null;
      });
      expectChange(r, 'POST', '/v1/pos/bookings/bk/deposits');
      expect(d.booking.depositTotal, const PosMoney.som(200000));

      r = await call(
        'booking_deposit',
        () => h.pos.bookings.refundDeposit(
          'bk',
          'p1',
          reason: 'kelmadi',
          approvalToken: 't',
        ),
      );
      expectChange(r, 'POST', '/v1/pos/bookings/bk/deposits/p1/refund');
      expect(Harness.bodyOf(r), <String, Object?>{
        'reason': 'kelmadi',
        'approval_token': 't',
      });

      h.adapter.respond = (RequestOptions o) =>
          FakeResponse(200, <String, Object?>{
            'success': true,
            'message': '',
            'data': <String, Object?>{
              'booking': fixtureData('booking_show'),
              'order': <String, Object?>{
                'uuid': 'new-order',
                'status': 'accepted',
              },
            },
          });
      final PosArrival a = await h.pos.bookings.arrive(
        'bk',
        guests: 9,
        version: 2,
      );
      expectChange(h.last, 'POST', '/v1/pos/bookings/bk/arrive');
      expect(Harness.bodyOf(h.last), <String, Object?>{
        'guests': 9,
        'version': 2,
      });
      expect(a.orderUuid, 'new-order');

      r = await call(
        'booking_show',
        () => h.pos.bookings.cancel('bk', reason: 'ob-havo', version: 3),
      );
      expectChange(r, 'POST', '/v1/pos/bookings/bk/cancel');
    });
  });

  group('CustomerAccountRepository', () {
    test('list, show, statement, repay, create', () async {
      RequestOptions r = await call(
        'acc_list',
        () => h.pos.accounts.list(query: 'Jam', debtorsOnly: true),
      );
      expect(r.uri.queryParameters, <String, String>{
        'q': 'Jam',
        'debtors': '1',
      });
      await call('debtors2', () => h.pos.accounts.debtors());
      await call('acc_show', () => h.pos.accounts.get('a1'));
      r = await call(
        'acc_statement',
        () => h.pos.accounts.statement('a1', from: '2026-10-01'),
      );
      expect(r.uri.queryParameters, <String, String>{'from': '2026-10-01'});

      r = await call(
        'acc_repay',
        () => h.pos.accounts.repay(
          'a1',
          amount: const PosMoney.som(50000),
          tender: 'cash',
        ),
      );
      expectChange(r, 'POST', '/v1/pos/customer-accounts/a1/repayments');
      expect(Harness.bodyOf(r), <String, Object?>{
        'amount': '50000.00',
        'tender': 'cash',
      });

      h.adapter.respond = (RequestOptions o) => FakeResponse(
        201,
        <String, Object?>{
          'success': true,
          'message': '',
          'data': (fixtureData('acc_show')! as Map<String, Object?>)['account'],
        },
      );
      await h.pos.accounts.create(
        const PosAccountInput(
          name: 'Jamshid aka',
          phone: '901112233',
          creditLimit: PosMoney.som(1000000),
        ),
      );
      expectChange(h.last, 'POST', '/v1/pos/customer-accounts');
      expect(Harness.bodyOf(h.last), <String, Object?>{
        'name': 'Jamshid aka',
        'phone': '901112233',
        'credit_limit': '1000000.00',
      });
      await h.pos.accounts.update(
        'a1',
        const PosAccountInput(clearCreditLimit: true, isBlocked: true),
      );
      expectChange(h.last, 'PATCH', '/v1/pos/customer-accounts/a1');
      expect(Harness.bodyOf(h.last), <String, Object?>{
        'credit_limit': null,
        'is_blocked': true,
      });
    });
  });
}
