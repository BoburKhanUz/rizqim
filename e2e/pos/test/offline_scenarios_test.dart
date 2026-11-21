/// M5 stage S6-b, against the real stack: scenarios 1, 3, 6 and 7 of docs/scale/M5_DESIGN.md 7.1 (the offline scenario
/// suite). Each scenario pairs a till of its own (a code the owner makes), so revoking or editing one never touches the
/// seeded tills of the other files.
///
/// The orders are not sent to the kitchen (`send: false`), because the Thursday evening's cook bumps every ticket of the
/// branch and counts the lines; what an order leaves open is settled or cancelled at the end of its test (the Thursday
/// evening and the Telegram order read the branch's open orders). The orders it closes stay: the Thursday evening only
/// requires its own parties among the closed ones.
///
/// Every check of what went over the wire reads the `signed` text of a line, not the fields beside it: the server
/// applies only that text.
@Timeout(Duration(minutes: 4))
library;

import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

import 'support/menu.dart';
import 'support/offline_till.dart';
import 'support/stack.dart';

void main() {
  late StackConfig config;
  late Dio owner;
  final List<OfflineTill> tills = <OfflineTill>[];

  setUpAll(() {
    config = StackConfig.fromEnvironment();
    owner = ownerDio(config);
  });

  tearDown(() async {
    for (final OfflineTill t in tills) {
      try {
        t.cable.cut = false;
        if (t.client.auth.terminal != null) {
          await t.cancelLeftovers(config.seed.staff('manager'));
        }
        await t.close();
      } on Object {
        // a till the scenario revoked or broke: nothing to close
      }
    }
    tills.clear();
  });

  /// A fresh till with the cashier signed in and a shift open.
  Future<(OfflineTill, Menu, PosShift)> openTill(
    String name, {
    String staff = 'cashier',
  }) async {
    final OfflineTill till = await OfflineTill.pair(config, owner, name);
    tills.add(till);
    await till.signIn(staff);
    final PosShift shift = await till.pos.shifts.open(
      openingCash: const PosMoney.som(100000),
      note: name,
    );
    till.shiftUuid = shift.uuid;
    till.pos.session.updateShift(shift.toSummary());
    final Menu menu = Menu(
      await till.pos.catalog.load(
        PosChannel.takeaway,
        menuVersion: till.terminal.bootstrap.menuVersion,
      ),
    );
    return (till, menu, shift);
  }

  Future<List<Map<String, Object?>>> auditOf(
    String action,
    OfflineTill till,
  ) async {
    final Response<Object?> r = await owner.get<Object?>(
      '/v1/owner/audit-logs',
      queryParameters: <String, Object?>{
        'action': action,
        'device_uuid': till.deviceUuid,
        'per_page': 100,
      },
    );
    return asList(asMap(asMap(r.data)['data'])['data']);
  }

  PosLineInput line(Menu menu, String dish, Object qty) =>
      menu.input(Dish.of(dish, qty));

  group('scenario 1: the outage, a discount, one sale', () {
    test('open, a round, 10 % discount, bill, cash, close offline; after the sync the server\'s bill is the till\'s to '
        'the tiyin, one fiscal receipt, both audit rows', () async {
      final (OfflineTill till, Menu menu, PosShift shift) = await openTill(
        'S6 kassa 1',
      );
      final PosData pos = till.pos;
      till.goOffline();
      expect(pos.offline!.active, isTrue);

      final PosOrder opened = await till.openTakeaway(<PosLineInput>[
        line(menu, 'Osh', '1.5'),
      ]);
      final PosOrder round = await pos.orders.addRound(
        opened.uuid,
        version: opened.version,
        items: <PosLineInput>[line(menu, 'Choy', 2)],
        send: false,
      );
      final PosMoney subtotal = menu.total(<Dish>[
        Dish.of('Osh', '1.5'),
        Dish.of('Choy', 2),
      ]);
      expect(round.subtotal, subtotal);
      final PosOrder given = await pos.orders.discount(
        opened.uuid,
        version: round.version,
        kind: PosDiscountKind.percent,
        value: '10',
        reason: 'Doimiy mijoz',
      );
      final PosMoney discount = subtotal.percent(10).roundToSom();
      expect(given.discount, discount);
      final PosBilling billing = await pos.billing.createBills(
        opened.uuid,
        version: given.version,
      );
      final PosBill tillBill = billing.bills.single;
      final PosBillResult paid = await pos.billing.pay(
        tillBill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(tillBill.total)],
        shiftUuid: shift.uuid,
      );
      expect(paid.order.isClosed, isTrue);
      expect(
        <String>[for (final SyncOperation o in till.store.pending) o.type],
        <String>[
          SyncOpType.orderOpen,
          SyncOpType.orderRound,
          SyncOpType.orderDiscount,
          SyncOpType.billCreate,
          SyncOpType.billPay,
          SyncOpType.orderClose,
        ],
      );

      final SyncDrainResult sync = await till.comeBack();
      expect(sync.error, isNull);
      expect(sync.left, 0);
      for (final SyncOperation op in till.store.answered) {
        expect(
          op.status,
          SyncOpStatus.applied,
          reason: '${op.type} ${op.reason} ${op.message}',
        );
      }
      // The wire: signed lines; the discount operation as the server read it, with the amount the till expected
      final List<({Map<String, Object?> op, bool signed})> sent =
          till.wire.operations;
      expect(sent.every((s) => s.signed), isTrue, reason: 'signing is on');
      final Map<String, Object?> discountOp = sent
          .map((s) => s.op)
          .firstWhere(
            (Map<String, Object?> o) => o['type'] == 'order.discount',
          );
      expect(
        asMap(discountOp['payload'])['expected_discount'],
        '${discount.tiyin ~/ 100}.00',
      );

      // The server's order and bill are the till's, to the tiyin
      final String serverOrder = till.store.resolve(opened.uuid);
      expect(serverOrder, isNot(opened.uuid));
      final PosOrder synced = await pos.orders.get(serverOrder);
      expect(synced.discount, discount);
      expect(synced.subtotal, subtotal);
      expect(synced.totalPrice, paid.order.totalPrice);
      expect(synced.status, 'delivered');
      final PosBill serverBill = (await pos.billing.bills(serverOrder))
          .bills
          .single;
      expect(serverBill.total, tillBill.total);
      expect(serverBill.isPaid, isTrue);
      expect(serverBill.payments, hasLength(1));
      expect(serverBill.payments.single.amount, tillBill.total);
      // One fiscal receipt, for the bill's total
      final PosFiscalReceiptDetail receipt = await FiscalReceiptRepository(
        pos.http,
      ).get(serverBill.fiscal.receiptUuid!);
      expect(receipt.total, serverBill.total);
      expect(receipt.type, 'sale');
      // ...and exactly one for the order: one bill, one receipt uuid (the server's receipts of an order are its bills'),
      // and the replay of the sync (the same operations again) does not issue another
      final Set<String> receipts = <String>{
        for (final PosBill b in (await pos.billing.bills(serverOrder)).bills)
          ?b.fiscal.receiptUuid,
      };
      expect(receipts, <String>{serverBill.fiscal.receiptUuid!});
      expect(
        (await pos.billing.bills(serverOrder)).bills,
        hasLength(1),
        reason: 'one bill: one fiscal receipt',
      );

      // The audit: the discount (twice-audited: the decision and the offline mark)
      expect(await auditOf('pos.order.discount', till), isNotEmpty);
      expect(await auditOf('pos.offline_discount', till), isNotEmpty);
    });
  });

  group(
    'scenario 3: a payment whose staff member has no PIN login on the till',
    () {
      test('the payment is recorded and flagged staff_unverified, not rejected; the close that follows is taken', () async {
        // (The 37 h itself needs the server's clock: PosSyncStaffUnverifiedTest moves it. Here the real stack answers
        // the same check for a cashier who has no PIN session on this till inside the 36 h window.)
        // The manager works the till and opens the order and its bill online
        final (OfflineTill till, Menu menu, PosShift shift) = await openTill(
          'S6 kassa 3',
          staff: 'manager',
        );
        final PosOrder order = await till.openTakeaway(<PosLineInput>[
          line(menu, 'Osh', 1),
        ]);
        final PosBill bill = (await till.pos.billing.createBills(
          order.uuid,
          version: order.version,
        )).bills.single;

        // The cashier typed the PIN on another till (the seeded one), never on this one. Offline, this till's journal
        // holds the payment and the close under the cashier's name
        final Staff cashier = config.seed.staff('cashier');
        till.goOffline();
        await till.store.enqueue(SyncOpType.billPay, <String, Object?>{
          'order': order.uuid,
          'bill': bill.uuid,
          'shift_uuid': shift.uuid,
          'tenders': <Object?>[PosTenderInput.cash(bill.total).toJson()],
        }, staffId: cashier.id);
        await till.store.enqueue(SyncOpType.orderClose, <String, Object?>{
          'order': order.uuid,
        }, staffId: cashier.id);

        final SyncDrainResult sync = await till.comeBack();
        expect(sync.error, isNull);
        final SyncOperation pay = till.store.answered.firstWhere(
          (SyncOperation o) => o.type == SyncOpType.billPay,
        );
        expect(pay.status, SyncOpStatus.conflict, reason: '${pay.message}');
        expect(pay.reason, 'staff_unverified');
        expect(
          till.store.answered.every(
            (SyncOperation o) => o.status != SyncOpStatus.rejected,
          ),
          isTrue,
          reason: 'nothing is rejected: the money is on the bill',
        );
        final PosBill after = (await till.pos.billing.bills(order.uuid))
            .bills
            .single;
        expect(after.isPaid, isTrue);
        expect(after.payments.single.amount, bill.total);
        expect((await till.pos.orders.get(order.uuid)).status, 'delivered');
        expect(
          await auditOf('pos.offline_payment_staff_unverified', till),
          isNotEmpty,
        );
      });
    },
  );

  group(
    'scenario 6: the terminal token is refused with operations pending',
    () {
      test('nothing is deleted; the rescue file is imported by the owner: all 5 applied; a second import changes nothing', () async {
        final (OfflineTill till, Menu menu, PosShift shift) = await openTill(
          'S6 kassa 6',
        );
        final PosOrder table = await till.openTakeaway(<PosLineInput>[
          line(menu, 'Osh', 1),
        ]);
        till.goOffline();
        final PosOrder more = await till.pos.orders.addRound(
          table.uuid,
          version: table.version,
          items: <PosLineInput>[line(menu, 'Choy', 1)],
          send: false,
        );
        final PosOrder last = await till.pos.orders.addRound(
          table.uuid,
          version: more.version,
          items: <PosLineInput>[line(menu, 'Choy', 2)],
          send: false,
        );
        final PosBill bill = (await till.pos.billing.createBills(
          table.uuid,
          version: last.version,
        )).bills.single;
        await till.pos.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
          shiftUuid: shift.uuid,
        );
        final List<SyncOperation> queued = till.store.pending;
        expect(queued, hasLength(5));
        final List<String> ids = <String>[
          for (final SyncOperation o in queued) o.opId,
        ];

        // The owner disconnects the till; the next sync meets the 401
        // (a second passes: the server stamps the disconnection to the second, the till its operations to the
        // millisecond)
        await Future<void>.delayed(const Duration(milliseconds: 1500));
        final String oldDevice = till.deviceUuid;
        await ownerCall(
          owner,
          'POST',
          '/v1/owner/devices/$oldDevice/revoke',
          <String, Object?>{'reason': 'S6-b sinov'},
        );
        await till.comeBack();
        await Future<void>.delayed(const Duration(milliseconds: 200));
        expect(till.client.auth.terminal, isNull);
        final String disk = <String>[
          ...till.disk.lines,
          ...till.disk.docs.values,
        ].join('\n');
        for (final String id in ids) {
          expect(disk, contains(id), reason: 'operation $id was deleted');
        }
        expect(
          till.pos.offline!.orphans.single.operations.map((o) => o.opId),
          ids,
        );

        // The till is paired again (a new key) and writes the file: "Navbatni faylga saqlash"
        await till.client.auth.pairTerminal(
          await OfflineTill.pairingCode(config, owner, 'S6 kassa 6 (yangi)'),
          name: 'S6 kassa 6 (yangi)',
          platform: 'e2e',
          appVersion: '0.1.0',
        );
        await till.terminal.signIn('cashier');
        expect(till.pos.offline!.settings.export, isTrue);
        final Directory dir = await Directory.systemTemp.createTemp('rzq_e2e_');
        addTearDown(() => dir.delete(recursive: true));
        final SyncExporter exporter = SyncExporter(
          store: till.store,
          keyProvider: till.pos.syncKeys!.current,
          enabled: () => till.pos.offline!.settings.export,
          setAside: () => <SyncExportQueue>[
            for (final PosOrphanQueue o in till.pos.offline!.orphans)
              SyncExportQueue(device: o.deviceUuid!, operations: o.operations),
          ],
          directory: () => dir,
        );
        final SyncExportResult file = await exporter.export();
        expect(file.operations, 5);
        expect(file.payments, 1);
        final List<int> bytes = File(file.paths.single).readAsBytesSync();

        Future<Map<String, Object?>> importFile() async {
          final Response<Object?> r = await owner.post<Object?>(
            '/v1/owner/pos/sync/import',
            data: FormData.fromMap(<String, Object?>{
              'file': MultipartFile.fromBytes(
                bytes,
                filename: file.files.single.name,
              ),
            }),
          );
          return asMap(asMap(r.data)['data']);
        }

        final Map<String, Object?> first = await importFile();
        final Map<String, Object?> totals = asMap(first['totals']);
        expect(totals['operations'], 5, reason: '$first');
        expect(totals['applied'], 5, reason: '$first');
        expect(totals['rejected'], 0);
        expect(first['complete'], isTrue);

        // The sale is on the server once: the table's order paid and closed
        final PosBill paidOnServer = (await till.pos.billing.bills(table.uuid))
            .bills
            .single;
        expect(paidOnServer.isPaid, isTrue);
        expect(paidOnServer.payments, hasLength(1));
        expect(paidOnServer.total, bill.total);
        expect((await till.pos.orders.get(table.uuid)).status, 'delivered');

        final Map<String, Object?> again = await importFile();
        final Map<String, Object?> totalsAgain = asMap(again['totals']);
        expect(totalsAgain['applied'], 0, reason: '$again');
        expect(totalsAgain['duplicate'], 5, reason: '$again');
        final PosBill still = (await till.pos.billing.bills(table.uuid))
            .bills
            .single;
        expect(still.payments, hasLength(1), reason: 'the money is taken once');
      });
    },
  );

  group('scenario 7: a journal line changed by hand', () {
    /// A sale paid offline (open, bill online; the payment and the close queued), the app stopped; the payment's
    /// amount on the disk is changed and the line's checksum made right again. The app starts again.
    Future<(OfflineTill, PosBill, PosOrder)> editedPayment(
      OfflineTill till,
      Menu menu,
      PosShift shift,
    ) async {
      final PosOrder order = await till.openTakeaway(<PosLineInput>[
        line(menu, 'Osh', 1),
      ]);
      final PosBill bill = (await till.pos.billing.createBills(
        order.uuid,
        version: order.version,
      )).bills.single;
      till.goOffline();
      await till.pos.offline!.sender.stop();
      await till.pos.billing.pay(
        bill.uuid,
        tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        shiftUuid: shift.uuid,
      );
      final SyncOperation pay = till.store.pending.firstWhere(
        (SyncOperation o) => o.type == SyncOpType.billPay,
      );
      await till.stopApp();
      final int at = till.disk.lines.indexWhere(
        (String l) => _record(l)['raw'] == pay.seal!.raw,
      );
      expect(at, isNonNegative);
      final Map<String, Object?> edited = _record(till.disk.lines[at]);
      String lower(String s) =>
          s.replaceAll(RegExp(r'"amount":"\d+\.\d+"'), '"amount":"1000.00"');
      expect(lower(edited['raw']! as String), isNot(edited['raw']));
      edited['raw'] = lower(edited['raw']! as String);
      edited['op'] = jsonDecode(lower(jsonEncode(edited['op'])));
      till.disk.lines[at] = _line(edited);
      // The app starts on the edited disk
      await till.startApp();
      expect(
        till.store.pending.map((SyncOperation o) => o.seal!.raw),
        isNot(contains(pay.seal!.raw)),
        reason: 'the edited line is what the app starts with',
      );
      return (till, bill, order);
    }

    Future<void> enforce(bool on) => ownerCall(
      owner,
      'PUT',
      '/v1/owner/pos/sync-signing',
      <String, Object?>{'enforce': on, 'confirm_unsigned': true},
    );

    test('observe (the default): the edited payment is recorded as its signed text says, sig_status bad, on the manager\'s '
        'list', () async {
      final (OfflineTill till0, Menu menu, PosShift shift) = await openTill(
        'S6 kassa 7a',
      );
      final (OfflineTill till, PosBill bill, PosOrder order) =
          await editedPayment(till0, menu, shift);
      final SyncDrainResult sync = await till.comeBack();
      expect(sync.error, isNull);
      final SyncOperation pay = till.store.answered.firstWhere(
        (SyncOperation o) => o.type == SyncOpType.billPay,
      );
      expect(till.wire.resultOf(pay.opId)!['sig_status'], 'bad');
      expect(pay.status, isNot(SyncOpStatus.rejected));
      expect(
        pay.reason,
        'underpaid',
        reason: 'the changed amount is what was recorded',
      );
      final PosBill server = (await till.pos.billing.bills(order.uuid))
          .bills
          .single;
      expect(server.payments, hasLength(1));
      expect(server.payments.single.amount, const PosMoney.som(1000));
      expect(server.total, bill.total);

      // On the manager's list ("Sinxronlash xatolari"): the payment, a conflict for its short amount, with the
      // manager's PIN on this till
      await till.signIn('manager');
      final PosSyncErrorsPage list = await till.pos.offline!.serverErrors();
      final PosSyncError row = list.errors.singleWhere(
        (PosSyncError e) => e.opId == pay.opId,
        orElse: () => fail(
          'the edited payment is not on the manager\'s list: '
          '${list.errors.map((e) => '${e.type}/${e.status}/${e.reason}')}',
        ),
      );
      expect(row.status, 'conflict');
      expect(row.reason, 'underpaid');
      expect(row.amount, '1000');
      // The same row as the server sends it: the signature verdict and the mark are on it. (The platform admin's
      // monitoring alert `pos_sync_signature` is the platform team's: not readable with an owner's token.)
      final Object? raw = await till.pos.http.get<Object?>(
        '/v1/pos/sync/errors',
        (Object? data) => data,
      );
      final Map<String, Object?> sent = asList(asMap(raw)['errors'])
          .map(asMap)
          .singleWhere((Map<String, Object?> e) => e['op_id'] == pay.opId);
      expect(sent['sig_status'], 'bad');
      expect(sent['staff_continued'], isFalse);
    });

    test('enforce: the same edit is recorded and flagged payment_unverified, never dropped', () async {
      final (OfflineTill till0, Menu menu, PosShift shift) = await openTill(
        'S6 kassa 7b',
      );
      await enforce(true);
      try {
        final (OfflineTill till, PosBill bill, PosOrder order) =
            await editedPayment(till0, menu, shift);
        final SyncDrainResult sync = await till.comeBack();
        expect(sync.error, isNull);
        final SyncOperation pay = till.store.answered.firstWhere(
          (SyncOperation o) => o.type == SyncOpType.billPay,
        );
        expect(till.wire.resultOf(pay.opId)!['sig_status'], 'bad');
        expect(pay.status, SyncOpStatus.conflict);
        expect(pay.reason, 'payment_unverified');
        final PosBill server = (await till.pos.billing.bills(order.uuid))
            .bills
            .single;
        expect(server.payments, hasLength(1));
        expect(server.total, bill.total);
      } finally {
        await enforce(false);
      }
    });

    test(
      'a line removed: the next one is chain_gap and applied all the same',
      () async {
        final (OfflineTill till, Menu menu, PosShift shift) = await openTill(
          'S6 kassa 7c',
        );
        final PosOrder order = await till.openTakeaway(<PosLineInput>[
          line(menu, 'Osh', 1),
        ]);
        till.goOffline();
        await till.pos.offline!.sender.stop();
        final PosOrder more = await till.pos.orders.addRound(
          order.uuid,
          version: order.version,
          items: <PosLineInput>[line(menu, 'Choy', 1)],
          send: false,
        );
        final PosBill bill = (await till.pos.billing.createBills(
          order.uuid,
          version: more.version,
        )).bills.single;
        await till.pos.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
          shiftUuid: shift.uuid,
        );
        final List<SyncOperation> queued = till.store.pending;
        final SyncOperation gone = queued.firstWhere(
          (SyncOperation o) => o.type == SyncOpType.billCreate,
        );
        await till.restart();
        till.disk.lines.removeWhere(
          (String l) => _record(l)['raw'] == gone.seal!.raw,
        );
        await till.restart();
        final SyncDrainResult sync = await till.comeBack();
        expect(sync.error, isNull);
        final SyncOperation next = queued[queued.indexOf(gone) + 1];
        expect(till.wire.resultOf(next.opId)!['sig_status'], 'chain_gap');
        expect(till.wire.resultOf(queued.first.opId)!['sig_status'], 'ok');
        expect(
          till.store.answered.any(
            (SyncOperation o) => o.status == SyncOpStatus.rejected,
          ),
          isFalse,
          reason: 'nothing is dropped',
        );
        // Where the money landed: the bill the till made was among the lines taken away, so the server does not know
        // it; the payment is recorded all the same, once
        final SyncOperation paid = queued.firstWhere(
          (SyncOperation o) => o.type == SyncOpType.billPay,
        );
        final Map<String, Object?> answer = till.wire.resultOf(paid.opId)!;
        expect(answer['status'], 'conflict');
        expect(answer['reason'], 'payment_unallocated');
        final Map<String, Object?> refs = asMap(answer['refs']);
        expect(
          refs['payments'],
          hasLength(1),
          reason: 'recorded once: $answer',
        );
        final Map<String, Object?> flag = asMap(
          asList(refs['flags']).singleWhere(
            (Object? f) => asMap(f)['flag'] == 'payment_unallocated',
          ),
        );
        expect(
          PosMoney.parse('${flag['amount']}'),
          bill.total,
          reason: 'the whole sale landed unallocated, on the order',
        );
        // not on a bill: the server has no bill for the order
        expect((await till.pos.billing.bills(order.uuid)).bills, isEmpty);
      },
    );
  });
}

Map<String, Object?> _record(String line) =>
    (jsonDecode(line.substring(9)) as Map).cast<String, Object?>();

String _line(Map<String, Object?> record) {
  final String json = jsonEncode(record);
  // (the journal's checksum: CRC-32 of the JSON text, 8 hex characters)
  var crc = 0xffffffff;
  for (final int b in utf8.encode(json)) {
    crc ^= b;
    for (var k = 0; k < 8; k++) {
      crc = (crc & 1) != 0 ? (crc >> 1) ^ 0xedb88320 : crc >> 1;
    }
  }
  final String sum = ((crc ^ 0xffffffff) & 0xffffffff)
      .toRadixString(16)
      .padLeft(8, '0');
  return '$sum $json';
}
