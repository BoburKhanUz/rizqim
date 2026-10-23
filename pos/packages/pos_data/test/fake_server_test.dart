import 'dart:async';

import 'package:pos_core/pos_core.dart' show FloorTableStatus;
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

/// Soxta server (Linux'da ilovani ishga tushirish uchun) haqiqiy HTTP va WebSocket orqali: ulash, PIN, bootstrap,
/// real-time obuna va hodisa.
void main() {
  late FakePosServer server;

  setUp(() async => server = await FakePosServer.start(demo: true));
  tearDown(() => server.stop());

  test('the whole shell flow over real HTTP and WebSocket', () async {
    final RizqimClient client = RizqimClient(
      baseUrl: server.apiBaseUrl,
      store: MemoryKeyValueStore(),
    );
    final PosData pos = PosData(client);
    await client.auth.pairTerminal('ABCD-EFGH');
    await client.auth.signInStaff(userId: 3, pin: '4827');
    final PosBootstrap boot = await pos.session.loadBootstrap();
    expect(boot.shift!.isOpen, isTrue);
    expect(pos.session.phase, PosSessionPhase.ready);

    final PosFloor floor = await pos.floor.load();
    expect(floor.table(1)!.status, FloorTableStatus.occupied);
    expect(floor.table(3)!.status, FloorTableStatus.overdue);
    expect(floor.untabledOrders, hasLength(1));

    final PosRealtime rt = PosRealtime.create(
      client: client,
      terminal: client.auth.terminal!,
      apiBaseUrl: server.apiBaseUrl,
      appKey: FakePosState.realtimeKey,
      reverbUrl: server.reverbUrl,
    )!;
    final Completer<PosEvent> created = Completer<PosEvent>();
    rt.events.listen((PosEvent e) {
      if (e is PosOrderEvent && e.isCreated && !created.isCompleted) {
        created.complete(e);
      }
    });
    final Completer<void> live = Completer<void>();
    rt.resubscribed.listen((_) {
      if (!live.isCompleted) live.complete();
    });
    rt.start();
    await live.future.timeout(const Duration(seconds: 5));
    expect(rt.isLive, isTrue);

    final PosOrder order = await pos.orders.open(
      channel: PosChannel.hall,
      tableId: 2,
      guestsCount: 2,
    );
    final PosEvent e = await created.future.timeout(const Duration(seconds: 5));
    expect(e.orderUuid, order.uuid);

    await rt.dispose();
    await pos.dispose();
  });
}
