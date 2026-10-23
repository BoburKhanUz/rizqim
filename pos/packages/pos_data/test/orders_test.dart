import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart' show StaffMember;
import 'package:test/test.dart';

import 'support/harness.dart';

void main() {
  late Harness h;

  setUp(() async {
    h = Harness();
    await h.signIn();
  });

  PosLineInput line(
    int variantId,
    String quantity, {
    List<PosModifierChoice> mods = const <PosModifierChoice>[],
  }) => PosLineInput(
    variantUuid: FakePosState.variantUuid(variantId),
    quantity: PosQuantity.parse(quantity),
    modifiers: mods,
  );

  test('open a room, add a round, send: every request has the right body and version', () async {
    final PosOrder opened = await h.pos.orders.open(
      channel: PosChannel.hall,
      tableId: 1,
      guestsCount: 4,
    );
    expect(Harness.bodyOf(h.last), <String, Object?>{
      'channel': 'pos_hall',
      'table_id': 1,
      'guests_count': 4,
    });
    expect(opened.version, 1);
    expect(opened.table!.name, '1-xona');

    final PosOrder round = await h.pos.orders.addRound(
      opened.uuid,
      version: opened.version,
      items: <PosLineInput>[
        line(3, '1.5'),
        line(
          4,
          '4',
          mods: const <PosModifierChoice>[
            PosModifierChoice('00000000-0000-4000-8000-0000000000a1'),
          ],
        ),
      ],
      fireMode: PosFireMode.now,
    );
    final Map<String, Object?> body = Harness.bodyOf(h.last);
    expect(body['version'], 1);
    expect(body['fire_mode'], 'now');
    expect(body['items'], <Object?>[
      <String, Object?>{
        'variant_id': FakePosState.variantUuid(3),
        'quantity': '1.500',
      },
      <String, Object?>{
        'variant_id': FakePosState.variantUuid(4),
        'quantity': '4.000',
        'modifiers': <Object?>[
          <String, Object?>{
            'id': '00000000-0000-4000-8000-0000000000a1',
            'quantity': 1,
          },
        ],
      },
    ]);
    expect(round.version, 2);
    expect(round.items, hasLength(2));
    expect(round.unsentItems, hasLength(2));
    // 1.5 kg × 120 000 + 4 × (18 000 + 2 000) = 260 000; xizmat haqi 10%; xona puli 300 000
    expect(round.subtotal, const PosMoney.som(260000));
    expect(round.serviceChargeAmount, const PosMoney.som(26000));
    expect(round.totalPrice, const PosMoney.som(586000));

    final PosOrder sent = await h.pos.orders.send(
      round.uuid,
      version: round.version,
    );
    expect(sent.status, 'preparing');
    expect(sent.unsentItems, isEmpty);
    expect(
      sent.items.every(
        (PosOrderItem i) => i.kitchenStatus == PosKitchenStatus.sent,
      ),
      isTrue,
    );

    final PosRejected nothing = await _throws<PosRejected>(
      () => h.pos.orders.send(sent.uuid, version: sent.version),
    );
    expect(nothing.reason, 'nothing_to_send');
  });

  test(
    'a stale version is a PosVersionConflict; reloading gives the current one',
    () async {
      final PosOrder o = await h.pos.orders.open(
        channel: PosChannel.hall,
        tableId: 2,
        guestsCount: 2,
      );
      await h.pos.orders.setGuests(o.uuid, version: o.version, guestsCount: 3);
      final PosVersionConflict e = await _throws<PosVersionConflict>(
        () =>
            h.pos.orders.setGuests(o.uuid, version: o.version, guestsCount: 5),
      );
      expect(e.currentVersion, 2);
      final PosOrder fresh = await h.pos.orders.get(o.uuid);
      expect(fresh.version, 2);
      expect(fresh.guestsCount, 3);
    },
  );

  test('voiding a sent line needs a manager: approval token flow', () async {
    final PosOrder o = await h.pos.orders.open(
      channel: PosChannel.hall,
      tableId: 2,
      guestsCount: 2,
      items: <PosLineInput>[line(4, '2')],
      send: true,
    );
    final int itemId = o.items.single.id;

    final PosApprovalRequired required = await _throws<PosApprovalRequired>(
      () => h.pos.orders.voidItem(
        o.uuid,
        itemId,
        version: o.version,
        reason: 'xato',
      ),
    );
    expect(required.permission, PosPermissions.ordersVoidItem);

    final List<StaffMember> approvers = await h.pos.approvals.approvers();
    expect(approvers.map((StaffMember m) => m.role), <String>['manager']);
    expect(h.last.uri.queryParameters['role'], 'manager,owner');
    expect(
      h.last.headers['Authorization'],
      'Bearer ${FakePosState.terminalToken}',
    );

    final ApprovalToken token = await h.pos.approvals.request(
      permission: required.permission!,
      userId: approvers.single.id,
      pin: '5926',
    );
    expect(token.approvedBy.id, 2);

    final PosOrder voided = await h.pos.orders.voidItem(
      o.uuid,
      itemId,
      version: o.version,
      reason: 'xato',
      approvalToken: token.token,
    );
    expect(voided.items.single.isVoid, isTrue);
    expect(Harness.bodyOf(h.last)['approval_token'], token.token);

    // Bir martalik
    final PosApprovalRejected used = await _throws<PosApprovalRejected>(
      () => h.pos.orders.cancel(
        o.uuid,
        version: voided.version,
        reason: 'x',
        approvalToken: token.token,
      ),
    );
    expect(used.reason, 'approval_used');
  });

  test(
    'runWithApproval asks once, retries with a new key, and maps cancel',
    () async {
      final PosOrder o = await h.pos.orders.open(channel: PosChannel.takeaway);
      final List<String> asked = <String>[];
      final PosOrder canceled = await runWithApproval<PosOrder>(
        (String? token) => h.pos.orders.cancel(
          o.uuid,
          version: o.version,
          reason: 'mijoz ketdi',
          approvalToken: token,
        ),
        approve: (String permission, PosException cause) async {
          asked.add(permission);
          return h.pos.approvals.request(
            permission: permission,
            userId: 2,
            pin: '5926',
          );
        },
      );
      expect(asked, <String>[PosPermissions.ordersCancel]);
      expect(canceled.status, 'canceled');
      final List<(String, String?)> cancels = h.state.idempotencyKeys
          .where(((String, String?) k) => k.$1.endsWith('/cancel'))
          .toList();
      expect(cancels, hasLength(2));
      expect(cancels.first.$2, isNot(cancels.last.$2));

      final PosOrder other = await h.pos.orders.open(
        channel: PosChannel.takeaway,
      );
      await expectLater(
        runWithApproval<PosOrder>(
          (String? token) => h.pos.orders.cancel(
            other.uuid,
            version: other.version,
            reason: 'x',
            approvalToken: token,
          ),
          approve: (_, _) async => null,
        ),
        throwsA(isA<PosApprovalCancelled>()),
      );
    },
  );

  test('a wrong manager PIN and a manager without the permission', () async {
    final PosRejected wrong = await _throws<PosRejected>(
      () => h.pos.approvals.request(
        permission: 'orders.cancel',
        userId: 2,
        pin: '0000',
      ),
    );
    expect(
      approvalFailure(wrong).message,
      "PIN noto'g'ri. Qayta urinib ko'ring.",
    );

    final PosApprovalRejected notAllowed = await _throws<PosApprovalRejected>(
      () => h.pos.approvals.request(
        permission: 'orders.cancel',
        userId: 3,
        pin: '4827',
      ),
    );
    expect(notAllowed.reason, PosReasons.approverNotAllowed);
    expect(
      approvalFailure(notAllowed).message,
      'Bu xodim bu amalni tasdiqlay olmaydi.',
    );

    for (var i = 0; i < 2; i++) {
      await _throws<PosRejected>(
        () => h.pos.approvals.request(
          permission: 'orders.cancel',
          userId: 2,
          pin: '0000',
        ),
      );
    }
    final PosException locked = await _throws<PosException>(
      () => h.pos.approvals.request(
        permission: 'orders.cancel',
        userId: 2,
        pin: '5926',
      ),
    );
    expect(locked.statusCode, 429);
    expect(approvalFailure(locked).isLocked, isTrue);
    expect(approvalFailure(locked).lockedFor, const Duration(seconds: 60));
  });

  test(
    'move, waive and restore a charge, mark served, open orders queue',
    () async {
      final PosOrder o = await h.pos.orders.open(
        channel: PosChannel.hall,
        tableId: 11,
        guestsCount: 2,
        items: <PosLineInput>[line(1, '2')],
        send: true,
      );
      final PosOrder moved = await h.pos.orders.move(
        o.uuid,
        version: o.version,
        tableId: 12,
      );
      expect(moved.table!.id, 12);

      final PosApprovalRequired waiveNeeds = await _throws<PosApprovalRequired>(
        () => h.pos.orders.waiveCharge(
          moved.uuid,
          PosChargeKind.serviceCharge,
          version: moved.version,
          reason: 'x',
        ),
      );
      expect(waiveNeeds.permission, 'orders.discount');
      expect(h.last.uri.path, endsWith('/charges/service_charge/waive'));

      final PosOrder served = await h.pos.orders.markServed(
        moved.uuid,
        moved.items.single.id,
        version: moved.version,
      );
      expect(served.items.single.kitchenStatus, PosKitchenStatus.served);

      final List<PosOpenOrder> queue = await h.pos.orders.openOrders();
      expect(h.last.uri.queryParameters['scope'], 'open');
      expect(queue.map((PosOpenOrder q) => q.uuid), contains(o.uuid));
    },
  );

  test('floor reflects open orders', () async {
    await h.pos.orders.open(
      channel: PosChannel.hall,
      tableId: 1,
      guestsCount: 6,
    );
    final PosFloor floor = await h.pos.floor.load();
    expect(floor.table(1)!.status, FloorTableStatus.occupied);
    expect(floor.table(2)!.status, FloorTableStatus.free);
    expect(floor.halls.map((FloorHall x) => x.name), <String>[
      'Asosiy zal',
      'Hovli',
    ]);
  });
}

Future<T> _throws<T extends Object>(Future<Object?> Function() call) async {
  try {
    await call();
  } on T catch (e) {
    return e;
  }
  fail('Expected $T');
}
