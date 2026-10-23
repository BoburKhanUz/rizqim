import 'package:pos_core/pos_core.dart' show FloorTableStatus;
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// Soxta backend'ning P3-21 qo'shimchalari: yaqin bron va schyot chiqqan joy holati, birlashtirish, `seedRound`.
void main() {
  test('floor: reserved_soon with the reservation, bill_printed', () async {
    final Harness h = Harness();
    h.state.reservations[2] = <String, Object?>{
      'booking_uuid': 'b-1',
      'starts_at': '2026-10-22T13:40:00Z',
      'guests': 12,
    };
    final String billed = h.state.seedOrder(tableId: 4);
    h.state.billPrinted.add(billed);
    await h.signIn();
    final PosFloor floor = await h.pos.floor.load();
    expect(floor.table(2)!.status, FloorTableStatus.booked);
    expect(floor.table(2)!.reservation!.guests, 12);
    expect(floor.table(4)!.status, FloorTableStatus.billPrinted);
    expect(floor.table(4)!.orders.single.billPrinted, isTrue);
  });

  test(
    'merge: source lines move after the target rounds, guests add up',
    () async {
      final Harness h = Harness();
      final String target = h.state.seedOrder(tableId: 1, guests: 4);
      final String source = h.state.seedOrder(
        tableId: 12,
        guests: 2,
        lines: const <(int, String)>[(4, '3')],
      );
      h.state.seedRound(
        target,
        const <(int, String)>[(1, '1')],
        fireMode: 'hold',
        sent: false,
      );
      await h.signIn();
      final PosOrder t = await h.pos.orders.get(target);
      expect(t.rounds, 2);
      expect(t.items.last.fireMode, PosFireMode.hold);
      final PosOrder s = await h.pos.orders.get(source);

      await expectLater(
        h.pos.orders.merge(
          target,
          version: t.version,
          sourceUuid: source,
          sourceVersion: s.version + 1,
        ),
        throwsA(isA<PosVersionConflict>()),
      );
      final PosOrder merged = await h.pos.orders.merge(
        target,
        version: t.version,
        sourceUuid: source,
        sourceVersion: s.version,
      );
      expect(merged.guestsCount, 6);
      expect(merged.items.last.productName, 'Shashlik');
      expect(merged.items.last.roundNo, 3);
      expect((await h.pos.orders.get(source)).status, 'canceled');
    },
  );
}
