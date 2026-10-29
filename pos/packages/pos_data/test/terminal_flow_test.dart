import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-16: bank terminali oqimi — bootstrap `terminal_flow`, kartada RRN va oxirgi 4 raqam, smenani yopishda bank
/// terminali jami (`bank_terminal`) va `PUT /pos/shifts/{uuid}/bank-report`.
void main() {
  Map<String, Object?> flow({
    String? cardMode = 'terminal_only',
    bool smartKassa = false,
    bool askBankTotal = true,
    String threshold = '5000.00',
  }) => <String, Object?>{
    'card_mode': cardMode,
    'smart_kassa': smartKassa,
    'fiscal_kassa_id': smartKassa ? 'KASSA-7' : null,
    'ask_bank_total': askBankTotal,
    'difference_threshold': threshold,
    'checklist_complete': false,
  };

  group('bootstrap terminal_flow', () {
    late Harness h;

    setUp(() async {
      h = Harness();
      FakeCashierBackend(h.state);
      await h.signIn();
    });

    test('is read when the module is on; null while it is off or the server is old', () async {
      expect((await h.pos.session.loadBootstrap()).terminalFlow, isNull);
      expect((await h.pos.session.loadBootstrap()).hasTerminalFlow, isFalse);

      h.state.bootstrapExtras['terminal_flow'] = flow();
      PosTerminalFlow f = (await h.pos.session.loadBootstrap()).terminalFlow!;
      expect(f.cardMode, PosCardMode.terminalOnly);
      expect(f.asksCardReference, isTrue);
      expect(f.providerTotals, isFalse);
      expect(f.askBankTotal, isTrue);
      expect(f.differenceThreshold, const PosMoney.som(5000));
      expect(f.checklistComplete, isFalse);

      h.state.bootstrapExtras['terminal_flow'] = flow(
        cardMode: 'smart_kassa',
        smartKassa: true,
      );
      f = (await h.pos.session.loadBootstrap()).terminalFlow!;
      expect(f.asksCardReference, isFalse);
      expect(f.providerTotals, isTrue);
      expect(f.fiscalKassaId, 'KASSA-7');

      h.state.bootstrapExtras['terminal_flow'] = flow(cardMode: null);
      f = (await h.pos.session.loadBootstrap()).terminalFlow!;
      expect(f.cardMode, isNull);
      expect(f.asksCardReference, isFalse);

      h.state.bootstrapExtras['terminal_flow'] = 'garbage';
      expect((await h.pos.session.loadBootstrap()).terminalFlow, isNull);
    });
  });

  group('card payment', () {
    late Harness h;

    setUp(() async {
      h = Harness();
      FakeCashierBackend(h.state);
      await h.signIn();
      h.state.openShift();
    });

    Future<(PosBill, PosMoney)> bill() async {
      final String uuid = h.state.seedOrder(tableId: 1, guests: 2);
      final PosOrder order = await h.pos.orders.get(uuid);
      final PosBill b = (await h.pos.billing.createBills(
        uuid,
        version: order.version,
      )).bills.single;
      return (b, b.total);
    }

    test('the RRN and the last four digits go to the server and come back on the payment', () async {
      final (PosBill b, PosMoney total) = await bill();
      final PosBillResult paid = await h.pos.billing.pay(
        b.uuid,
        tenders: <PosTenderInput>[
          PosTenderInput.card(
            total,
            cardType: 'humo',
            transactionId: '123456789012',
            cardLast4: '4321',
          ),
        ],
      );
      final Map<String, Object?> sent =
          (Harness.bodyOf(h.last)['tenders']! as List<Object?>).single!
              as Map<String, Object?>;
      expect(sent['transaction_id'], '123456789012');
      expect(sent['card_last4'], '4321');
      final PosPayment p = paid.bill.payments.single;
      expect(p.transactionId, '123456789012');
      expect(p.cardLast4, '4321');
    });

    test('the last four digits are optional: not sent when empty; not four digits is a 422', () async {
      final (PosBill b, PosMoney total) = await bill();
      await expectLater(
        h.pos.billing.pay(
          b.uuid,
          tenders: <PosTenderInput>[
            PosTenderInput.card(
              total,
              cardType: 'humo',
              transactionId: 'T1',
              cardLast4: '12a4',
            ),
          ],
        ),
        throwsA(
          isA<PosRejected>().having(
            (PosRejected e) => e.reason,
            'reason',
            'card_last4_invalid',
          ),
        ),
      );
      final PosBillResult paid = await h.pos.billing.pay(
        b.uuid,
        tenders: <PosTenderInput>[
          PosTenderInput.card(
            total,
            cardType: 'humo',
            transactionId: 'T1',
            cardLast4: '',
          ),
        ],
      );
      final Map<String, Object?> sent =
          (Harness.bodyOf(h.last)['tenders']! as List<Object?>).single!
              as Map<String, Object?>;
      expect(sent.containsKey('card_last4'), isFalse);
      expect(paid.bill.payments.single.cardLast4, isNull);
    });
  });

  group('end of day', () {
    late Harness h;
    late FakeCashierBackend backend;

    setUp(() async {
      h = Harness();
      backend = FakeCashierBackend(h.state);
      await h.signIn();
      h.state.openShift();
    });

    Future<void> payCard() async {
      final String uuid = h.state.seedOrder(tableId: 1, guests: 2);
      final PosOrder order = await h.pos.orders.get(uuid);
      final PosBill b = (await h.pos.billing.createBills(
        uuid,
        version: order.version,
      )).bills.single;
      await h.pos.billing.pay(
        b.uuid,
        tenders: <PosTenderInput>[
          PosTenderInput.card(
            b.total,
            cardType: 'uzcard',
            transactionId: 'RRN1',
          ),
        ],
      );
    }

    test('close sends bank_terminal and reads the difference back; it never blocks closing', () async {
      h.state.bootstrapExtras['terminal_flow'] = flow();
      await payCard();
      final String shiftUuid = h.state.shift!['uuid']! as String;
      final PosShiftReport x = (await h.pos.shifts.current()).report!;
      final PosMoney card = x.byTender['card']!;

      final PosShift closed = await h.pos.shifts.close(
        shiftUuid,
        countedBreakdown: const <int, int>{},
        bankTerminal: PosBankTerminalInput(
          total: card + const PosMoney.som(20000),
          count: 1,
          terminalRef: ' T-1 ',
          note: ' ',
        ),
      );
      expect(Harness.bodyOf(h.last)['bank_terminal'], <String, Object?>{
        'total': (card + const PosMoney.som(20000)).toApiString(),
        'count': 1,
        'terminal_ref': 'T-1',
      });
      expect(closed.isOpen, isFalse);
      final PosBankTerminalReport bank = closed.bankTerminal!;
      expect(bank.bankTotal, card + const PosMoney.som(20000));
      expect(bank.rizqimTotal, card);
      expect(bank.difference, const PosMoney.som(20000));
      expect(bank.hasDifference, isTrue);
      expect(bank.overThreshold, isTrue);
      expect(bank.source, PosBankReportSource.manual);
    });

    test(
      'without the total (or with the module off) the close is the old one',
      () async {
        await payCard();
        final String shiftUuid = h.state.shift!['uuid']! as String;
        final PosShift closed = await h.pos.shifts.close(
          shiftUuid,
          countedBreakdown: const <int, int>{},
        );
        expect(Harness.bodyOf(h.last).containsKey('bank_terminal'), isFalse);
        expect(closed.bankTerminal, isNull);
        expect(backend.closedShifts, hasLength(1));
      },
    );

    test('bank-report: manual total, the provider total of a smart kassa, and the refusal of a plain till', () async {
      h.state.bootstrapExtras['terminal_flow'] = flow();
      await payCard();
      final String shiftUuid = h.state.shift!['uuid']! as String;
      final PosMoney card =
          (await h.pos.shifts.current()).report!.byTender['card']!;

      await expectLater(
        h.pos.shifts.bankReport(shiftUuid),
        throwsA(
          isA<PosRejected>().having(
            (PosRejected e) => e.reason,
            'reason',
            'bank_total_required',
          ),
        ),
      );
      final PosBankTerminalReport manual = await h.pos.shifts.bankReport(
        shiftUuid,
        bankTotal: card,
        bankCount: 1,
      );
      expect(h.last.method, 'PUT');
      expect(h.last.uri.path, '/api/v1/pos/shifts/$shiftUuid/bank-report');
      expect(h.last.headers['Idempotency-Key'], matches(uuidV4));
      expect(manual.hasDifference, isFalse);
      expect(manual.overThreshold, isFalse);

      await expectLater(
        h.pos.shifts.bankReport(
          shiftUuid,
          source: PosBankReportSource.provider,
        ),
        throwsA(
          isA<PosConflict>().having(
            (PosConflict e) => e.reason,
            'reason',
            'provider_report_unavailable',
          ),
        ),
      );

      h.state.bootstrapExtras['terminal_flow'] = flow(
        cardMode: 'smart_kassa',
        smartKassa: true,
      );
      backend.providerCardTotal =
          (card - const PosMoney.som(3000)).wholeSom * 100;
      final PosBankTerminalReport provider = await h.pos.shifts.bankReport(
        shiftUuid,
        source: PosBankReportSource.provider,
      );
      expect(provider.source, PosBankReportSource.provider);
      expect(Harness.bodyOf(h.last)['source'], 'provider');
      expect(provider.difference.isNegative, isTrue);
    });
  });
}
