import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

void main() {
  const Map<String, Object?> wire = <String, Object?>{
    'order': <String, Object?>{
      'uuid': 'o1',
      'status': 'preparing',
      'store_id': 1,
      'branch_id': 1,
      'courier_id': null,
      'updated_at': null,
    },
    'bill': <String, Object?>{'uuid': 'b1'},
    'request_id': 'r1',
    'reprint': true,
    'printer_id': 7,
    'target_device': 'till-1',
    'requested_by': 4,
  };

  group('BillPrintRequest', () {
    test('reads the server event and round trips', () {
      final BillPrintRequest r = BillPrintRequest.fromJson(wire);
      expect(
        (r.billUuid, r.orderUuid, r.requestId, r.reprint, r.printerId),
        ('b1', 'o1', 'r1', true, 7),
      );
      expect((r.targetDevice, r.requestedBy), ('till-1', 4));
      expect(BillPrintRequest.fromJson(r.toJson()), r);
      expect(BillPrintRequest.fromJson(r.toJson()).toJson(), r.toJson());
    });

    test('the event is typed and carries the order', () {
      final PosEvent e = PosEvent.fromWire('bill.print_requested', wire);
      expect(e, isA<PosBillPrintRequested>());
      expect(e.orderUuid, 'o1');
      expect((e as PosBillPrintRequested).request.requestId, 'r1');
      // A broken body never throws: it is an unknown event
      expect(
        PosEvent.fromWire('bill.print_requested', <String, Object?>{}),
        isA<PosUnknownEvent>(),
      );
    });

    test('a till named in the request prints it, no name: every till', () {
      final BillPrintRequest named = BillPrintRequest.fromJson(wire);
      expect(named.isFor('till-1'), isTrue);
      expect(named.isFor('till-2'), isFalse);
      final BillPrintRequest open = BillPrintRequest.fromJson(<String, Object?>{
        ...wire,
        'target_device': null,
        'printer_id': null,
      });
      expect(open.isFor('till-2'), isTrue);
      expect(open.printerId, isNull);
    });

    test('the same request is accepted once, the log is bounded', () {
      final BillPrintRequests log = BillPrintRequests(capacity: 2);
      final BillPrintRequest a = BillPrintRequest.fromJson(wire);
      expect(log.accept(a), isTrue);
      expect(log.accept(a), isFalse);
      log.forget(a);
      expect(log.accept(a), isTrue);

      BillPrintRequest other(String id) => BillPrintRequest.fromJson(
        <String, Object?>{...wire, 'request_id': id},
      );
      expect(log.accept(other('r2')), isTrue);
      expect(log.accept(other('r3')), isTrue); // r1 is forgotten now
      expect(log.accept(a), isTrue);
    });
  });

  group('settings and the print-request call', () {
    test('auto_print_bill defaults to on, bill_printer to none', () {
      final PosSettings s = PosSettings.fromJson(
        const PosJson(<String, Object?>{'mode': 'hybrid'}),
      );
      expect((s.autoPrintBill, s.billPrinter), (true, null));
      final PosSettings off = PosSettings.fromJson(
        const PosJson(<String, Object?>{
          'auto_print_bill': false,
          'bill_printer': 3,
        }),
      );
      expect((off.autoPrintBill, off.billPrinter), (false, 3));
    });

    test('a reprint needs the permission or a manager approval', () async {
      final Harness h = Harness();
      final FakeCashierBackend backend = FakeCashierBackend(h.state);
      backend.printRequestPrinterId = 7;
      backend.printRequestTargetDevice = 'till-1';
      await h.signIn(userId: 4); // a waiter
      final String uuid = h.state.seedOrder(tableId: 1);
      final PosOrder order = await h.pos.orders.get(uuid);
      final PosBilling billing = await h.pos.billing.createBills(
        uuid,
        version: order.version,
      );
      final String bill = billing.bills.single.uuid;

      await expectLater(
        h.pos.billing.requestPrint(bill),
        throwsA(isA<PosApprovalRequired>()),
      );
      expect(backend.printRequests, isEmpty);

      final ApprovalToken token = await h.pos.approvals.request(
        permission: PosPermissions.reprintBill,
        userId: 2,
        pin: '5926',
      );
      final List<PosEvent> heard = <PosEvent>[];
      h.state.onEvent = (String name, Map<String, Object?> data) =>
          heard.add(PosEvent.fromWire(name, data));
      final PosBillPrintAck ack = await h.pos.billing.requestPrint(
        bill,
        approvalToken: token.token,
      );
      expect(h.last.uri.path, '/api/v1/pos/bills/$bill/print-request');
      expect(h.last.headers['Idempotency-Key'], matches(uuidV4));
      expect(Harness.bodyOf(h.last), <String, Object?>{
        'approval_token': token.token,
      });
      expect((ack.printerId, ack.targetDevice), (7, 'till-1'));
      expect(backend.printRequests.single['bill'], bill);

      final PosBillPrintRequested event = heard
          .whereType<PosBillPrintRequested>()
          .single;
      expect(event.request.reprint, isTrue);
      expect(event.request.requestId, ack.requestId);
      expect(event.request.billUuid, bill);
      expect(event.request.isFor('till-1'), isTrue);

      // The token is single use
      await expectLater(
        h.pos.billing.requestPrint(bill, approvalToken: token.token),
        throwsA(isA<PosException>()),
      );
    });

    test(
      'the cashier holds pos.reprint_bill and asks without a token',
      () async {
        final Harness h = Harness();
        final FakeCashierBackend backend = FakeCashierBackend(h.state);
        await h.signIn();
        final String uuid = h.state.seedOrder(tableId: 1);
        final PosOrder order = await h.pos.orders.get(uuid);
        final String bill = (await h.pos.billing.createBills(
          uuid,
          version: order.version,
        )).bills.single.uuid;

        await h.pos.billing.requestPrint(bill);
        expect(Harness.bodyOf(h.last), isEmpty);
        expect(backend.printRequests, hasLength(1));
      },
    );
  });
}
