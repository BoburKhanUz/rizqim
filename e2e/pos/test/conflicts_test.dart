/// Two devices change one order at the same time (the waiter's phone and the till): the server takes one change, answers
/// the other `409 version_conflict` with the current version, and the loser reloads the order and sends its change again.
/// Nothing is lost and nothing is applied twice.
@Timeout(Duration(minutes: 3))
library;

import 'dart:async';

import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

import 'support/menu.dart';
import 'support/stack.dart';

void main() {
  late StackConfig config;
  late Terminal till;
  late Terminal waiter;
  late Menu menu;
  late Staff manager;
  late PosFloor floor;

  setUpAll(() async {
    config = StackConfig.fromEnvironment();
    till = await Terminal.open(config, 'cashier');
    waiter = await Terminal.open(config, 'waiter1');
    manager = config.seed.staff('manager');
    await till.signIn('cashier');
    await waiter.signIn('waiter1');
    menu = Menu(
      await till.pos.catalog.load(
        PosChannel.hall,
        menuVersion: till.bootstrap.menuVersion,
      ),
    );
    floor = await till.pos.floor.load();
  });

  tearDownAll(() async {
    await till.close();
    await waiter.close();
  });

  /// The outcome of one device's attempt: the order it got, or the conflict.
  Future<Object> attempt(Future<PosOrder> call) async {
    try {
      return await call;
    } on PosVersionConflict catch (e) {
      return e;
    }
  }

  test('two devices add a round at once: one wins, the other gets a conflict and recovers', () async {
    final FloorTable table = floor.tables.firstWhere(
      (t) => t.name == 'Tapchan 2',
    );
    PosOrder order = await waiter.pos.orders.open(
      channel: PosChannel.hall,
      tableId: table.id,
      guestsCount: 4,
      waiterId: config.seed.staff('waiter1').id,
      items: menu.inputs(<Dish>[Dish.of('Shashlik', 4)]),
    );
    final String uuid = order.uuid;

    // Three races in a row; the winner may be either device
    for (var race = 1; race <= 3; race++) {
      final PosOrder seenByTill = await till.pos.orders.get(uuid);
      expect(
        seenByTill.version,
        order.version,
        reason: 'both devices hold the same version',
      );
      final int version = order.version;

      final List<Object> outcomes = await Future.wait(<Future<Object>>[
        attempt(
          waiter.pos.orders.addRound(
            uuid,
            version: version,
            items: menu.inputs(<Dish>[Dish.of('Achichuk', race)]),
          ),
        ),
        attempt(
          till.pos.orders.addRound(
            uuid,
            version: version,
            items: menu.inputs(<Dish>[Dish.of('Svejiy salat', race)]),
          ),
        ),
      ]);
      final List<PosOrder> winners = outcomes.whereType<PosOrder>().toList();
      final List<PosVersionConflict> losers = outcomes
          .whereType<PosVersionConflict>()
          .toList();
      expect(
        winners,
        hasLength(1),
        reason: 'race $race: exactly one change is taken',
      );
      expect(
        losers,
        hasLength(1),
        reason: 'race $race: exactly one device is told it was late',
      );
      expect(losers.single.statusCode, 409);
      expect(losers.single.reason, 'version_conflict');
      expect(
        losers.single.currentVersion,
        winners.single.version,
        reason: 'the conflict carries the version to reload',
      );
      expect(winners.single.version, greaterThan(version));

      // The loser reloads the order, sees the winner's round and sends its own again on the new version
      final bool tillLost = outcomes[1] is PosVersionConflict;
      final Terminal loser = tillLost ? till : waiter;
      final Dish own = tillLost
          ? Dish.of('Svejiy salat', race)
          : Dish.of('Achichuk', race);
      final PosOrder reloaded = await loser.pos.orders.get(uuid);
      expect(reloaded.version, losers.single.currentVersion);
      expect(
        reloaded.rounds,
        2 * race,
        reason: 'only the winner\'s round is on the order',
      );
      order = await loser.pos.orders.addRound(
        uuid,
        version: reloaded.version,
        items: menu.inputs(<Dish>[own]),
      );
      expect(order.rounds, 2 * race + 1);
    }

    // Both devices' changes are on the order, each exactly once: 3 races x 2 devices
    order = await till.pos.orders.get(uuid);
    expect(order.rounds, 1 + 6);
    final Map<String, int> lines = <String, int>{};
    for (final PosOrderItem i in order.activeItems) {
      lines[i.productName] = (lines[i.productName] ?? 0) + i.quantity.wholePart;
    }
    expect(lines['Shashlik'], 4);
    expect(lines['Achichuk'], 1 + 2 + 3);
    expect(lines['Svejiy salat'], 1 + 2 + 3);
    expect(lines['Choy'], 4, reason: 'the courtyard: one choy per guest');
    expect(lines['Non'], 4);

    // A stale send is refused the same way, and the same request with the same key twice is taken once
    await expectLater(
      waiter.pos.orders.send(uuid, version: order.version - 1),
      throwsA(
        isA<PosVersionConflict>().having(
          (e) => e.currentVersion,
          'currentVersion',
          order.version,
        ),
      ),
    );
    final String key = waiter.pos.http.newKey();
    final PosOrder sent = await waiter.pos.orders.send(
      uuid,
      version: order.version,
      idempotencyKey: key,
    );
    final PosOrder replay = await waiter.pos.orders.send(
      uuid,
      version: order.version,
      idempotencyKey: key,
    );
    expect(
      replay.version,
      sent.version,
      reason: 'the retry returns the first answer, it does not send again',
    );

    // Clean up: cancel the order with the manager's PIN
    await runWithApproval<PosOrder>(
      (String? token) => waiter.pos.orders.cancel(
        uuid,
        version: sent.version,
        reason: 'Sinov tugadi',
        approvalToken: token,
      ),
      approve: (String permission, PosException cause) =>
          waiter.pos.approvals.request(
            permission: permission,
            userId: manager.id,
            pin: manager.pin!,
          ),
    );
  });
}
