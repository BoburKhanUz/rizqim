import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_waiter/src/features/rooms/rooms_controller.dart';

/// Wave M2: the waiter's floor is read with If-None-Match; a 304 returns the kept floor AND the moment it was first
/// received, so "open for N minutes" keeps counting instead of jumping back.
void main() {
  test(
    'a 304 floor keeps loadedAt: open times continue from the original read',
    () async {
      // The server's clock stands still (its floor body does not change), the phone's clock moves
      final DateTime serverNow = DateTime.utc(2026, 10, 22, 13);
      DateTime phoneNow = serverNow;
      final FakePosAdapter adapter = FakePosAdapter(clock: () => serverNow);
      final RizqimClient client = RizqimClient(
        baseUrl: 'https://api.test/api',
        store: MemoryKeyValueStore(),
        dio: Dio()..httpClientAdapter = adapter,
        clock: () => phoneNow,
      );
      final PosData data = PosData(client, clock: () => phoneNow);
      await client.auth.pairTerminal('ABCD-EFGH');
      await client.auth.signInStaff(userId: 4, pin: adapter.state.pins[4]!);
      adapter.state.seedOrder(tableId: 1, age: const Duration(minutes: 10));

      final RoomsController rooms = RoomsController(
        data: data,
        staffId: 4,
        clock: () => phoneNow,
      );
      await rooms.load();
      final DateTime first = rooms.loadedAt!;
      final FloorOrder order = rooms.floor!.orders.single;
      expect(rooms.openFor(order), const Duration(minutes: 10));

      phoneNow = phoneNow.add(const Duration(minutes: 2));
      await rooms.load();
      expect(adapter.state.notModified, 1, reason: 'the second read is a 304');
      expect(rooms.loadedAt, first);
      expect(
        rooms.openFor(rooms.floor!.orders.single),
        const Duration(minutes: 12),
        reason: 'not back to 10 minutes',
      );

      rooms.dispose();
      await data.dispose();
      await client.auth.dispose();
    },
  );
}
