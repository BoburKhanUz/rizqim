import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-22: choy puli — bootstrap `tips`, to'lovdagi `tip_amount` / `tip_from_change` / `tip_staff_id`, schyot va X/Z dagi
/// choy puli (tushumdan alohida), `/pos/tips` ro'yxati va menejer tuzatishi.
void main() {
  late Harness h;

  setUp(() async {
    h = Harness();
    FakeCashierBackend(h.state);
    await h.signIn();
  });

  test(
    'bootstrap tips: off on an old server, the mode from the section',
    () async {
      final PosTipSettings none = (await h.pos.session.loadBootstrap()).tips;
      expect(none.enabled, isFalse);
      expect(none.picksStaff, isFalse);

      h.state.bootstrapExtras['tips'] = <String, Object?>{
        'enabled': true,
        'mode': 'personal',
        'pool_rule': 'equal',
        'pool_period': 'day',
      };
      final PosTipSettings tips = (await h.pos.session.loadBootstrap()).tips;
      expect(tips.enabled, isTrue);
      expect(tips.mode, PosTipMode.personal);
      expect(tips.picksStaff, isTrue);
    },
  );

  test('a tender carries its tip; the change leaves the tip out', () {
    final PosTenderInput cash = PosTenderInput.cash(
      PosMoney.parse('143000'),
      tendered: PosMoney.parse('160000'),
      tip: PosMoney.parse('10000'),
    );
    expect(cash.toJson(), <String, Object?>{
      'tender': 'cash',
      'amount': '143000.00',
      'tendered_amount': '160000.00',
      'tip_amount': '10000.00',
    });
    expect(cash.change, PosMoney.parse('7000'));

    final PosTenderInput change = PosTenderInput.cash(
      PosMoney.parse('143000'),
      tendered: PosMoney.parse('150000'),
      tipFromChange: true,
    );
    expect(change.toJson()['tip_from_change'], isTrue);
    expect(change.toJson().containsKey('tip_amount'), isFalse);
    expect(change.change, PosMoney.zero);

    final PosTenderInput card = PosTenderInput.card(
      PosMoney.parse('50000'),
      cardType: 'humo',
      transactionId: 'RRN1',
      tip: PosMoney.parse('5000'),
    );
    expect(card.toJson()['tip_amount'], '5000.00');
    expect(
      PosTenderInput.qr(PosMoney.parse('1000'))
          .toJson()
          .containsKey('tip_amount'),
      isFalse,
    );
  });

  test('pay sends tip_staff_id; the bill reads its tips and notice', () async {
    h.adapter.respond = (RequestOptions o) =>
        const FakeResponse(200, <String, Object?>{
          'success': true,
          'message': "To'landi.",
          'data': <String, Object?>{
            'bill': <String, Object?>{
              'uuid': 'b-1',
              'order_uuid': 'o-1',
              'number': 1,
              'status': 'paid',
              'total': '143000.00',
              'paid_amount': '143000.00',
              'due_amount': '0.00',
              'tips_amount': '10000.00',
              'service_charge_notice': 'Xizmat haqi 10%.',
              'payments': <Object?>[
                <String, Object?>{
                  'uuid': 'p-1',
                  'tender': 'cash',
                  'amount': '143000.00',
                  'tip_amount': '10000.00',
                },
              ],
              'tips': <Object?>[
                <String, Object?>{
                  'uuid': 't-1',
                  'method': 'cash',
                  'source': 'tender',
                  'amount': '10000.00',
                  'original_amount': '10000.00',
                  'mode': 'personal',
                  'staff': <String, Object?>{'id': 7, 'name': 'Aziz'},
                  'holders': <Object?>[],
                  'closed': false,
                  'corrections': <Object?>[],
                },
              ],
            },
            'order': <String, Object?>{
              'uuid': 'o-1',
              'status': 'delivered',
              'version': 3,
            },
          },
        });

    final PosBillResult result = await h.pos.billing.pay(
      'b-1',
      tenders: <PosTenderInput>[
        PosTenderInput.cash(
          PosMoney.parse('143000'),
          tip: PosMoney.parse('10000'),
        ),
      ],
      tipStaffId: 7,
    );
    final Map<String, Object?> body = Harness.bodyOf(h.last);
    expect(body['tip_staff_id'], 7);
    expect(
      (body['tenders']! as List<Object?>).first,
      containsPair('tip_amount', '10000.00'),
    );

    final PosBill bill = result.bill;
    expect(bill.tipsAmount, PosMoney.parse('10000'));
    expect(bill.totalWithTips, PosMoney.parse('153000'));
    expect(bill.serviceChargeNotice, 'Xizmat haqi 10%.');
    expect(bill.tipOf(bill.payments.single), PosMoney.parse('10000'));
    expect(bill.tips.single.staff!.name, 'Aziz');
  });

  test('the X report reads its tips apart from the takings', () {
    final PosShiftReport report = PosShiftReport.fromJson(
      const PosJson(<String, Object?>{
        'sales': <String, Object?>{'received': '143000.00'},
        'tips': <String, Object?>{
          'count': 1,
          'amount': '10000.00',
          'by_method': <Object?>[
            <String, Object?>{
              'method': 'cash',
              'count': 1,
              'amount': '10000.00',
            },
          ],
          'personal': '10000.00',
          'pool': '0.00',
          'unassigned': '0.00',
          'by_staff': <Object?>[
            <String, Object?>{
              'staff_id': 7,
              'name': 'Aziz',
              'amount': '10000.00',
            },
          ],
          'corrections': <String, Object?>{'count': 0, 'amount': '0.00'},
        },
        'cash': <String, Object?>{
          'tips': '10000.00',
          'tip_payouts': '4000.00',
          'expected': '249000.00',
        },
      }),
    );
    expect(report.received, PosMoney.parse('143000'));
    expect(report.tips.amount, PosMoney.parse('10000'));
    expect(report.tips.cash, PosMoney.parse('10000'));
    expect(report.tips.byStaff.single.staffId, 7);
    expect(report.cashTips, PosMoney.parse('10000'));
    expect(report.tipPayouts, PosMoney.parse('4000'));
  });

  test('the shift tips and a correction with an approval', () async {
    h.adapter.respond = (RequestOptions o) =>
        const FakeResponse(200, <String, Object?>{
          'success': true,
          'message': 'ok',
          'data': <String, Object?>{
            'shift_uuid': 's-1',
            'business_date': '2026-11-16',
            'own_only': true,
            'summary': <String, Object?>{'count': 1, 'amount': '4000.00'},
            'tips': <Object?>[],
          },
        });
    final PosShiftTips list = await h.pos.tips.shiftTips(shiftUuid: 's-1');
    expect(h.last.uri.path, '/api/v1/pos/tips');
    expect(h.last.uri.queryParameters['shift_uuid'], 's-1');
    expect(list.ownOnly, isTrue);
    expect(list.summary.amount, PosMoney.parse('4000'));

    h.adapter.respond = (RequestOptions o) =>
        const FakeResponse(200, <String, Object?>{
          'success': true,
          'message': 'ok',
          'data': <String, Object?>{
            'uuid': 't-1',
            'method': 'cash',
            'amount': '6000.00',
            'original_amount': '10000.00',
            'mode': 'personal',
            'staff': <String, Object?>{'id': 8, 'name': 'Bobur'},
            'closed': false,
          },
        });
    final PosTip tip = await h.pos.tips.correct(
      't-1',
      mode: PosTipMode.personal,
      staffId: 8,
      amount: PosMoney.parse('6000'),
      reason: 'Xato',
      approvalToken: 'tok',
      idempotencyKey: 'k-1',
    );
    expect(h.last.method, 'POST');
    expect(h.last.uri.path, '/api/v1/pos/tips/t-1/corrections');
    expect(h.last.headers['Idempotency-Key'], 'k-1');
    expect(Harness.bodyOf(h.last), <String, Object?>{
      'amount': '6000.00',
      'mode': 'personal',
      'staff_id': 8,
      'reason': 'Xato',
      'approval_token': 'tok',
    });
    expect(tip.amount, PosMoney.parse('6000'));
    expect(tip.originalAmount, PosMoney.parse('10000'));
  });

  test('the order tells which rule set its service charge and the notice', () {
    final PosOrder order = PosOrder.fromJson(
      const PosJson(<String, Object?>{
        'uuid': 'o-1',
        'status': 'accepted',
        'channel': 'pos_hall',
        'version': 1,
        'charges': <String, Object?>{
          'service_charge_rule': 'large_group',
          'place_fee_kind': 'cover',
          'service_charge_notice':
              "8 va undan ko'p mehmonli stolga xizmat haqi 18%.",
        },
      }),
    );
    expect(order.serviceChargeRule, 'large_group');
    expect(order.placeFeeIsCover, isTrue);
    expect(order.serviceChargeNotice, contains('18%'));
  });
}
