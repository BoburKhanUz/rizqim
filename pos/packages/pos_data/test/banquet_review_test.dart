import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6 wave 5 review fixes mirrored by the fake banquet backend (H4): the event-day order is billed and paid only
/// through its settlement (C1, 409 `banquet_settle_first`), the schedule is closed once the event is billed (C2, 409
/// `event_billed`, `event.billed`), the event figures are net (C4), and the plain messages of the new codes.
void main() {
  late Harness h;
  late FakeCashierBackend cashier;
  late FakeBanquetBackend banquets;

  setUp(() async {
    h = Harness();
    cashier = FakeCashierBackend(h.state);
    banquets = cashier.useBanquets();
    await h.signIn();
    h.state.openShift();
  });

  Future<T> throws<T extends Object>(Future<Object?> Function() call) async {
    try {
      await call();
    } on T catch (e) {
      return e;
    }
    fail('expected $T');
  }

  String shift() => h.state.shift!['uuid']! as String;

  test('C1: no bill on the event-day order outside the settlement; the settlement bill is paid normally', () async {
    final String uuid = banquets.seedEvent(
      date: '2026-10-22',
      status: 'confirmed',
    );
    final String order = (await h.pos.banquetOps.start(uuid))!;
    final PosOrder o = await h.pos.orders.get(order);
    final PosConflict refused = await throws<PosConflict>(
      () => h.pos.billing.createBills(order, version: o.version),
    );
    expect(refused.reason, PosReasons.banquetSettleFirst);
    expect(refused.message, PosReasons.describe(PosReasons.banquetSettleFirst));
    expect(refused.message, contains('Yakuniy hisob'));

    final PosSettleResult r = await h.pos.banquetOps.settle(
      uuid,
      shiftUuid: shift(),
    );
    final PosOrder after = await h.pos.orders.get(order);
    await h.pos.billing.pay(
      r.bill.uuid,
      tenders: <PosTenderInput>[PosTenderInput.cash(r.bill.dueAmount)],
      version: after.version,
    );
    final PosBanquetEvent e = await h.pos.banquets.show(uuid);
    expect(e.status, PosBanquetStatus.settled);
  });

  test('event-day order note: type, halls and date only; no customer name, no title', () async {
    final String uuid = banquets.seedEvent(
      date: '2026-10-22',
      status: 'confirmed',
      customer: 'Karimovlar',
      title: 'Aziz va Malika toyi',
    );
    final String order = (await h.pos.banquetOps.start(uuid))!;
    final String note = (await h.pos.orders.get(order)).notes ?? '';
    expect(note, startsWith('Tadbir: '));
    expect(note, endsWith('22.10.2026'));
    expect(note, isNot(contains('Karimovlar')));
    expect(note, isNot(contains('Aziz')));
    expect(note, isNot(contains('Malika')));
  });

  test('C2: billed → the schedule says so and refuses a row payment (event_billed, plain words)', () async {
    final String uuid = banquets.seedEvent(
      date: '2026-10-22',
      status: 'confirmed',
    );
    expect((await h.pos.banquetPayments.schedule(uuid)).billed, isFalse);
    await h.pos.banquetOps.start(uuid);
    await h.pos.banquetOps.settle(uuid, shiftUuid: shift());
    banquets.addDueRow(uuid, 1000000);
    final PosBanquetSchedule s = await h.pos.banquetPayments.schedule(uuid);
    expect(s.billed, isTrue);
    final PosConflict refused = await throws<PosConflict>(
      () => h.pos.banquetPayments.pay(
        uuid,
        s.rows.last.uuid,
        const PosBanquetPayInput(tender: PosTender.cash),
      ),
    );
    expect(refused.reason, PosReasons.eventBilled);
    expect(refused.message, contains("Qoldiq yakuniy hisobda to'lanadi"));
  });

  test('C1: a stuck event (its order closed by a direct payment) is settled: the bill carries the banquet charge, '
      'the advances offset', () async {
    final String uuid = banquets.seedEvent(
      date: '2026-10-22',
      status: 'confirmed',
    );
    final String order = (await h.pos.banquetOps.start(uuid))!;
    banquets.closeOrderDirectly(uuid);
    final PosSettleResult r = await h.pos.banquetOps.settle(
      uuid,
      shiftUuid: shift(),
    );
    expect(r.bill.total, const PosMoney.som(40000000));
    expect(r.bill.dueAmount, const PosMoney.som(28000000));
    final PosOrder o = await h.pos.orders.get(order);
    await h.pos.billing.pay(
      r.bill.uuid,
      tenders: <PosTenderInput>[PosTenderInput.cash(r.bill.dueAmount)],
      version: o.version,
    );
    final PosBanquetEvent e = await h.pos.banquets.show(uuid);
    expect(e.status, PosBanquetStatus.settled);
    // C4: the net figures: the deposit + the remainder on the bill, nothing outstanding
    expect(e.paidNet, const PosMoney.som(40000000));
    expect(e.paidTotal, const PosMoney.som(12000000));
    expect(e.outstanding, PosMoney.zero);
    expect(e.remaining, PosMoney.zero);
    final PosBanquetSettlement s = await h.pos.banquetOps.settlement(uuid);
    expect(s.extraBills, isEmpty);
    expect(s.extraBillsNotice, isNull);
  });

  test('C4: a cancelled event: paid net of the refund, the refund, nothing outstanding', () async {
    final String uuid = banquets.seedEvent(
      date: '2026-11-11',
      status: 'confirmed',
    );
    await h.client.auth.signInStaff(userId: 2, pin: h.state.pins[2]!);
    await h.pos.banquetPayments.cancel(uuid, reason: "To'y ko'chirildi");
    final PosBanquetEvent e = await h.pos.banquets.show(uuid);
    expect(e.paidTotal, const PosMoney.som(12000000));
    expect(e.paidNet, const PosMoney.som(3600000));
    expect(e.refunded, const PosMoney.som(8400000));
    expect(e.outstanding, PosMoney.zero);
  });

  test('the new codes have plain Uzbek words', () {
    for (final String code in <String>[
      PosReasons.banquetSettleFirst,
      PosReasons.eventBilled,
      PosReasons.transferDuplicate,
      PosReasons.panelOnly,
    ]) {
      expect(PosReasons.describe(code), isNotNull, reason: code);
    }
    expect(PosReasons.describe(PosReasons.panelOnly), contains('panel'));
  });

  test('an older server without the net figures: the figures fall back (settled = paid in full, no remainder)', () {
    final PosBanquetEvent e = PosBanquetEvent.fromJson(
      PosJson.of(<String, Object?>{
        'uuid': 'e-1',
        'branch_id': 1,
        'event_date': '2026-10-22',
        'slot': 'evening',
        'start_time': '18:00',
        'end_time': '23:00',
        'starts_at': '2026-10-22T13:00:00Z',
        'ends_at': '2026-10-22T18:00:00Z',
        'halls': <Object?>[],
        'event_type': 'toy',
        'status': 'settled',
        'customer_name': 'Karimovlar',
        'total': '40000000.00',
        'paid_total': '12000000.00',
        'outstanding': '28000000.00',
        'frozen': false,
        'version': 3,
      }, 'data'),
    );
    expect(e.paidNet, isNull);
    expect(e.paid, const PosMoney.som(40000000));
    expect(e.remaining, PosMoney.zero);
  });
}
