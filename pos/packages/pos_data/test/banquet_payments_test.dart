import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-53 (to'lqin 5, B): to'lov jadvali modeli va ombori ([PosBanquetPaymentRepository]) soxta backend ustida —
/// jadval (30/40/qolgani), qatorni qisman to'lash (BO'NAK cheki, smena, zaklad → tasdiqlangan), X/Z `banquets`
/// qismi, bekor qilish hisobi va bekor qilish (naqd qaytarishga menejer tasdig'i), yakuniy hisob (bitta schyot,
/// zakladlar `prepayment`), internetsiz oddiy xabar.
void main() {
  late Harness h;
  late FakeCashierBackend cashier;
  late FakeBanquetBackend banquets;

  setUp(() async {
    h = Harness();
    cashier = FakeCashierBackend(h.state);
    banquets = cashier.useBanquets();
    await h.signIn();
  });

  Future<T> throws<T extends Object>(Future<Object?> Function() call) async {
    try {
      await call();
    } on T catch (e) {
      return e;
    }
    fail('expected $T');
  }

  // 200 guests × 150 000 + evening hall 10 000 000 = 40 000 000
  String seed({String date = '2026-12-20', String status = 'hold'}) =>
      banquets.seedEvent(date: date, status: status);

  test('the schedule: 30 % deposit today, 40 % 14 days before, the rest on the day', () async {
    final String uuid = seed();
    final PosBanquetSchedule s = await h.pos.banquetPayments.schedule(uuid);
    expect(s.eventStatus, PosBanquetStatus.hold);
    expect(s.total, const PosMoney.som(40000000));
    expect(
      <(PosBanquetPaymentKind, String?, PosMoney)>[
        for (final PosBanquetPaymentRow r in s.rows)
          (r.kind, r.dueDate, r.amount),
      ],
      <(PosBanquetPaymentKind, String?, PosMoney)>[
        (
          PosBanquetPaymentKind.deposit,
          '2026-10-22',
          const PosMoney.som(12000000),
        ),
        (
          PosBanquetPaymentKind.interim,
          '2026-12-06',
          const PosMoney.som(16000000),
        ),
        (
          PosBanquetPaymentKind.finalPayment,
          '2026-12-20',
          const PosMoney.som(12000000),
        ),
      ],
    );
    expect(s.summary.outstanding, const PosMoney.som(40000000));
    expect(s.summary.overdue, isFalse);
    expect(s.dueRows, hasLength(3));

    banquets.makeOverdue(uuid);
    final PosBanquetSchedule late = await h.pos.banquetPayments.schedule(uuid);
    expect(late.rows[1].overdue, isTrue);
    expect(late.summary.overdueCount, 1);
    final List<PosBanquetEvent> list = await h.pos.banquets.list();
    expect(list.single.overdue, isTrue);
  });

  test('a part of the deposit, then the rest: the body, ADVANCE receipts, confirmed, X/Z banquets', () async {
    final String uuid = seed();
    final PosBanquetSchedule s = await h.pos.banquetPayments.schedule(uuid);
    final String deposit = s.rows.first.uuid;
    final PosConflict noShift = await throws<PosConflict>(
      () => h.pos.banquetPayments.pay(
        uuid,
        deposit,
        const PosBanquetPayInput(tender: PosTender.cash),
      ),
    );
    expect(noShift.reason, 'shift_not_open');

    h.state.openShift();
    final PosBanquetPaymentResult part = await h.pos.banquetPayments.pay(
      uuid,
      deposit,
      const PosBanquetPayInput(
        tender: PosTender.cash,
        amount: PosMoney.som(5000000),
      ),
      shiftUuid: h.state.shift!['uuid']! as String,
    );
    expect(Harness.bodyOf(h.last), <String, Object?>{
      'tender': 'cash',
      'amount': '5000000.00',
      'shift_uuid': h.state.shift!['uuid'],
    });
    expect(h.last.headers['Idempotency-Key'], isNotNull);
    expect(part.fiscal.receipt?.type, 'advance');
    expect(part.entry.amount, const PosMoney.som(5000000));
    expect(part.schedule.eventStatus, PosBanquetStatus.hold);
    expect(part.schedule.rows, hasLength(4));
    expect(part.schedule.rows[1].amount, const PosMoney.som(7000000));
    expect(part.schedule.rows[1].kind, PosBanquetPaymentKind.deposit);

    final PosBanquetPaymentResult rest = await h.pos.banquetPayments.pay(
      uuid,
      part.schedule.rows[1].uuid,
      const PosBanquetPayInput(
        tender: PosTender.card,
        cardType: 'humo',
        transactionId: 'RRN-7',
      ),
    );
    expect(rest.schedule.eventStatus, PosBanquetStatus.confirmed);
    expect(rest.schedule.summary.paid, const PosMoney.som(12000000));

    final PosShiftWithReport x = await h.pos.shifts.current();
    final PosShiftBanquets b = x.report!.banquets;
    expect(b.takenCount, 2);
    expect(b.takenAmount, const PosMoney.som(12000000));
    expect(b.takenCash, const PosMoney.som(5000000));
    expect(b.takenCard, const PosMoney.som(7000000));
    expect(b.drawerCash, const PosMoney.som(5000000));
    // opening 100 000 + the cash advance
    expect(x.report!.expectedCash, const PosMoney.som(5100000));
  });

  test('cancellation 20 days before: not by the cashier; a manager gets 30 % retained, the cash back from the drawer', () async {
    h.state.openShift();
    final String uuid = seed(date: '2026-11-11', status: 'confirmed');
    // The cashier cannot cancel (banquets.manage): a manager signs in
    await throws<PosForbidden>(
      () => h.pos.banquetPayments.cancellationQuote(uuid),
    );
    await h.client.auth.signInStaff(userId: 2, pin: h.state.pins[2]!);
    final PosBanquetCancellationQuote q = await h.pos.banquetPayments
        .cancellationQuote(uuid);
    expect(q.daysBefore, 20);
    expect(q.rulePercent, '30');
    expect(q.paid, const PosMoney.som(12000000));
    expect(q.retained, const PosMoney.som(3600000));
    expect(q.refund, const PosMoney.som(8400000));
    expect(q.cashRefund, const PosMoney.som(8400000));
    expect(q.rows.single.refundPath, 'cash_drawer');

    final PosValidationException noReason =
        await throws<PosValidationException>(
          () => h.pos.banquetPayments.cancel(uuid, reason: ''),
        );
    expect(noReason.firstError, contains('Sabab'));
    final PosBanquetSchedule done = await h.pos.banquetPayments.cancel(
      uuid,
      reason: "To'y ko'chirildi",
    );
    expect(done.eventStatus, PosBanquetStatus.cancelled);
    expect(done.cancellation!.retained, const PosMoney.som(3600000));
    expect(done.rows.single.status, PosBanquetPaymentStatus.retained);
    expect(done.entries.map((PosBanquetPaymentEntry e) => e.type), <String>[
      'payment',
      'refund',
      'retain',
    ]);
    final PosShiftWithReport x = await h.pos.shifts.current();
    expect(x.report!.banquets.refundedCash, const PosMoney.som(8400000));
    expect(x.report!.banquets.drawerRefunds, const PosMoney.som(8400000));
  });

  test('final settlement: one bill, the advances as prepayment, the remainder paid normally', () async {
    h.state.openShift();
    final String uuid = seed(date: '2026-10-22', status: 'confirmed');
    final String? order = await h.pos.banquetOps.start(uuid);
    expect(order, isNotNull);
    final PosSettleResult r = await h.pos.banquetOps.settle(
      uuid,
      shiftUuid: h.state.shift!['uuid']! as String,
    );
    expect(r.settlement.total, const PosMoney.som(40000000));
    expect(r.settlement.advances, const PosMoney.som(12000000));
    expect(r.bill.total, const PosMoney.som(40000000));
    expect(r.bill.dueAmount, const PosMoney.som(28000000));
    final PosOrder o = await h.pos.orders.get(order!);
    await h.pos.billing.pay(
      r.bill.uuid,
      tenders: <PosTenderInput>[
        const PosTenderInput.cash(PosMoney.som(28000000)),
      ],
      version: o.version,
    );
    final PosBanquetSettlement after = await h.pos.banquetOps.settlement(uuid);
    expect(after.eventStatus, PosBanquetStatus.settled);
    final Map<String, Object?> receipt = cashier
        .receipts['${(cashier.billByUuid(r.bill.uuid)!['fiscal']! as Map<String, Object?>)['receipt_uuid']}']!;
    expect(receipt['total'], '40000000.00');
    expect(
      (receipt['payments']! as List<Object?>)
          .cast<Map<String, Object?>>()
          .where((Map<String, Object?> p) => p['prepayment'] == true)
          .single['amount'],
      '12000000.00',
    );
  });

  test('offline: a plain message, no request', () async {
    expect(
      PosBanquetPaymentRepository.offlineMessage,
      contains("Internet yo'q"),
    );
  });
}
