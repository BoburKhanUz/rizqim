import 'dart:io';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support.dart';

/// The till as the hub over the fake backend (real sockets on localhost): the internet is gone, two waiters' phones,
/// the KDS and the customer display work through the till; then the internet comes back and the till's queue goes to
/// the server in order, the LAN operations with their origin.
void main() {
  late FakePosAdapter adapter;
  late PosData pos;
  late FakeSyncBackend sync;
  late PosOfflineLanApplier applier;
  late LanHub hub;
  late TestBranch branch;
  final List<LanClient> clients = <LanClient>[];
  DateTime now = DateTime.utc(2026, 10, 22, 13);

  setUp(() async {
    now = DateTime.utc(2026, 10, 22, 13);
    adapter = FakePosAdapter(clock: () => now);
    final FakePosState state = adapter.state;
    state.staff.addAll(<Map<String, Object?>>[
      <String, Object?>{
        'id': 5,
        'name': 'Sardor Usmonov',
        'role': 'waiter',
        'staff_code': '1005',
      },
      <String, Object?>{
        'id': 6,
        'name': 'Oshpaz Ali',
        'role': 'cook',
        'staff_code': '1006',
      },
    ]);
    state.rolePermissions['cook'] = <String>['orders.view', 'kitchen.manage'];
    FakeCashierBackend(state);
    sync = FakeSyncBackend(state);
    state.openShift();
    final RizqimClient client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => now,
    );
    final SyncStore store = await SyncStore.open(
      MemorySyncStorage(),
      clock: () => now,
      random: Random(11),
    );
    pos = PosData(
      client,
      clock: () => now,
      random: Random(7),
      offlineStore: store,
    );
    await client.auth.pairTerminal('ABCD-EFGH');
    await client.auth.signInStaff(userId: 3, pin: state.pins[3]!);
    final PosBootstrap boot = await pos.session.loadBootstrap();
    await pos.catalog.load(PosChannel.hall, menuVersion: boot.menuVersion);
    await pos.catalog.load(PosChannel.takeaway, menuVersion: boot.menuVersion);
    await pos.floor.load();
    await pos.orders.openOrders();
    // The internet goes; the till finds out with its next request
    adapter.offline = true;
    await pos.floor.load();

    branch = TestBranch();
    applier = PosOfflineLanApplier(offline: pos.offline!);
    hub = LanHub(
      credentials: branch.hubCredentials(),
      applier: applier,
      address: InternetAddress.loopbackIPv4,
      port: 0,
      beaconEvery: null,
      advertisedHost: '127.0.0.1',
    );
    await hub.start();
  });

  tearDown(() async {
    for (final LanClient c in clients) {
      await c.dispose();
    }
    clients.clear();
    await hub.dispose();
    await applier.dispose();
    await pos.dispose();
  });

  Future<LanClient> connect(
    String device, {
    String? grant,
    LanApp app = LanApp.waiter,
  }) async {
    final LanClient c = LanClient(
      credentials: branch.client(device, port: hub.port, grant: grant),
      app: app,
    );
    clients.add(c);
    await c.connect(LanEndpoint('127.0.0.1', hub.port));
    return c;
  }

  const List<String> waiterPerms = <String>[
    'orders.view',
    'orders.manage',
    'tables.manage',
    'menu.view',
  ];

  Map<String, Object?> line(int variant, String qty) => <String, Object?>{
    'variant_id': FakePosState.variantUuid(variant),
    'quantity': qty,
  };

  test('a waiter\'s order goes into the till\'s queue as theirs, the kitchen gets the ticket, the others see it; '
      'the waiter rule, permissions and the till-only money hold', () async {
    expect(pos.offline!.active, isTrue);
    final LanClient botir = await connect(
      TestBranch.phone1,
      grant: branch.grant(
        TestBranch.phone1,
        4,
        name: 'Botir Aliyev',
        permissions: waiterPerms,
      ),
    );
    final LanClient sardor = await connect(
      TestBranch.phone2,
      grant: branch.grant(
        TestBranch.phone2,
        5,
        name: 'Sardor Usmonov',
        permissions: waiterPerms,
      ),
    );
    final LanClient kds = await connect(
      TestBranch.kds,
      app: LanApp.kds,
      grant: branch.grant(
        TestBranch.kds,
        6,
        role: 'cook',
        permissions: <String>['orders.view', 'kitchen.manage'],
      ),
    );
    final LanClient display = await connect(
      TestBranch.display,
      app: LanApp.display,
    );
    final Inbox atKds = Inbox(kds.events),
        atSardor = Inbox(sardor.events),
        atDisplay = Inbox(display.events);

    // Botir opens room 2 with two teas, sent to the kitchen
    const String order = '9a000000-0000-4000-8000-000000000001';
    final LanOpResult opened = await botir.send(
      LanOpType.orderOpen,
      <String, Object?>{
        'order': order,
        'channel': 'pos_hall',
        'table_id': 2,
        'guests_count': 3,
        'items': <Object?>[line(1, '2')],
        'send': true,
      },
    );
    expect(
      opened.accepted,
      isTrue,
      reason: '${opened.reason} ${opened.message}',
    );
    expect((opened.order!['waiter']! as Map<String, Object?>)['id'], 4);
    expect(opened.order!['short_code'], startsWith('K1-'));

    final SyncOperation queued = pos.offline!.store.find(opened.opId)!;
    expect(queued.type, SyncOpType.orderOpen);
    expect(queued.origin, TestBranch.phone1);
    expect(queued.staffId, 4);
    // Priced from the till's own menu copy, as the till would
    expect(
      (queued.payload['items']! as List<Object?>)
          .cast<Map<String, Object?>>()
          .single['unit_price'],
      isNotNull,
    );
    expect(queued.payload['waiter_id'], 4);

    final LanEvent ticket = await atKds.wait(LanMsg.kitchenTicket);
    final List<Map<String, Object?>> lines =
        (ticket.payload['lines']! as List<Object?>)
            .cast<Map<String, Object?>>();
    expect(lines.single['quantity'], '2.000');
    expect(ticket.payload['table_ref'], '2-xona');
    await atSardor.wait(
      LanMsg.orderChanged,
      where: (LanEvent e) =>
          (e.payload['order']! as Map<String, Object?>)['uuid'] == order,
    );
    // The till's own floor has it too
    expect(pos.offline!.floor().table(2)?.orders, isNotEmpty);

    // Sardor may not touch Botir's table offline (no manager can approve)
    final LanOpResult other = await sardor.send(
      LanOpType.orderRound,
      <String, Object?>{
        'order': order,
        'items': <Object?>[line(1, '1')],
      },
    );
    expect(other.reason, 'approval_required');
    // The cook opens no orders, but bumps the tea ready
    expect(
      (await kds.send(LanOpType.orderOpen, <String, Object?>{
        'order': '9a000000-0000-4000-8000-000000000002',
        'channel': 'pos_takeaway',
        'items': <Object?>[line(1, '1')],
      })).reason,
      'permission_denied',
    );
    final LanOpResult bumped = await kds.send(
      LanOpType.kitchenBump,
      <String, Object?>{'order': order, 'line': lines.single['id']},
    );
    expect(
      bumped.accepted,
      isTrue,
      reason: '${bumped.reason} ${bumped.message}',
    );
    expect(
      ((bumped.order!['items']! as List<Object?>).single!
          as Map<String, Object?>)['kitchen_status'],
      'ready',
    );
    final LanEvent ready = await atKds.wait(LanMsg.kitchenItemStatus);
    // Review P6 S7: the display (no staff grant) sees the queue, not the kitchen
    expect(atDisplay.of(LanMsg.kitchenItemStatus), isEmpty);
    expect(atDisplay.of(LanMsg.kitchenTicket), isEmpty);
    expect(ready.payload, <String, Object?>{
      'order': order,
      'line': lines.single['id'],
      'status': 'ready',
    });
    // Bumped already: the KDS is told, nothing queued
    expect(
      (await kds.send(LanOpType.kitchenBump, <String, Object?>{
        'order': order,
        'line': lines.single['id'],
      })).reason,
      'kitchen_status',
    );

    // Botir adds a round and asks for the bill; the money stays at the till
    expect(
      (await botir.send(LanOpType.orderRound, <String, Object?>{
        'order': order,
        'items': <Object?>[line(2, '1')],
        'send': true,
      })).accepted,
      isTrue,
    );
    final LanOpResult asked = await botir.send(
      LanOpType.billRequest,
      <String, Object?>{'order': order},
    );
    expect(asked.accepted, isTrue);
    expect(asked.order!['bill_requested_at'], isNotNull);
    expect(
      (await botir.send('bill.pay', <String, Object?>{'order': order})).reason,
      'not_allowed_on_lan',
    );
    // A module the branch does not have
    expect(
      (await botir.send(LanOpType.orderCourseSend, <String, Object?>{
        'order': order,
        'course_no': 2,
      })).reason,
      'module_disabled',
    );
    // A line already in the kitchen cannot be voided offline (the till's own rule)
    expect(
      (await botir.send(LanOpType.itemsVoid, <String, Object?>{
        'order': order,
        'line': lines.single['id'],
      })).reason,
      'offline_unavailable',
    );

    // The same operation sent again (its answer was lost on the Wi-Fi): once
    final int before = pos.offline!.store.pendingCount;
    final LanOpResult again = await botir.send(
      LanOpType.orderOpen,
      <String, Object?>{
        'order': order,
        'channel': 'pos_hall',
        'table_id': 2,
        'items': <Object?>[line(1, '2')],
      },
      opId: opened.opId,
    );
    expect(again.duplicate, isTrue);
    expect(pos.offline!.store.pendingCount, before);
    expect(
      pos.offline!.store.pending.where(
        (SyncOperation o) => o.type == SyncOpType.orderOpen,
      ),
      hasLength(1),
    );
  });

  test('when the internet returns the till\'s queue reaches the server in order, the LAN operations with their origin, '
      'and the devices are told the queue drained', () async {
    final LanClient botir = await connect(
      TestBranch.phone1,
      grant: branch.grant(TestBranch.phone1, 4, permissions: waiterPerms),
    );
    final LanClient kds = await connect(
      TestBranch.kds,
      app: LanApp.kds,
      grant: branch.grant(
        TestBranch.kds,
        6,
        role: 'cook',
        permissions: <String>['kitchen.manage'],
      ),
    );
    final Inbox atBotir = Inbox(botir.events);
    const String order = '9a000000-0000-4000-8000-000000000003';

    expect(
      (await botir.send(LanOpType.orderOpen, <String, Object?>{
        'order': order,
        'channel': 'pos_takeaway',
        'items': <Object?>[line(1, '1')],
        'send': true,
      })).accepted,
      isTrue,
    );
    // The cashier's own round at the till, between the LAN operations
    final PosOrder own = await pos.orders.addRound(
      order,
      version: pos.offline!.order(order).version,
      items: <PosLineInput>[
        PosLineInput(
          variantUuid: FakePosState.variantUuid(2),
          quantity: PosQuantity.parse('1'),
        ),
      ],
    );
    expect(own.items, hasLength(2));
    await atBotir.wait(
      LanMsg.orderChanged,
      where: (LanEvent e) =>
          ((e.payload['order']! as Map<String, Object?>)['items']!
                  as List<Object?>)
              .length ==
          2,
    );
    final int lineId =
        (((pos.offline!.order(order).raw['items']! as List<Object?>).first!
                    as Map<String, Object?>)['id']!
                as num)
            .toInt();
    expect(
      (await kds.send(LanOpType.kitchenBump, <String, Object?>{
        'order': order,
        'line': lineId,
      })).accepted,
      isTrue,
    );
    expect(pos.offline!.store.pendingCount, 3);
    expect(LanModeRule.drained(botir.lanState), isFalse);

    // The internet is back
    adapter.offline = false;
    final SyncDrainResult drained = await pos.offline!.syncNow();
    expect(drained.drained, isTrue);
    final List<Map<String, Object?>> sent = sync.received;
    expect(sent.map((Map<String, Object?> o) => o['type']), <String>[
      SyncOpType.orderOpen,
      SyncOpType.orderRound,
      SyncOpType.kitchenBump,
    ]);
    expect(sent.map((Map<String, Object?> o) => o['origin_device']), <String?>[
      TestBranch.phone1,
      null,
      TestBranch.kds,
    ]);
    expect(sent.map((Map<String, Object?> o) => o['staff_id']), <int>[4, 3, 6]);
    final List<int> seqs = <int>[
      for (final Map<String, Object?> o in sent) o['seq']! as int,
    ];
    expect(seqs, List<int>.of(seqs)..sort());
    await atBotir.wait(
      LanMsg.lanState,
      where: (LanEvent e) => LanModeRule.drained(e.payload),
    );
    expect(botir.hubDrained, isTrue);
  });

  test('a bill request the hub prints itself goes into the queue marked printed_on_lan (the server does not print it '
      'again); one it leaves to the server is not marked', () async {
    // The till's glue decides per request (the cashier app: offline, auto print on, a bill printer)
    await hub.dispose();
    await applier.dispose();
    final List<String> asked = <String>[];
    var prints = true;
    applier = PosOfflineLanApplier(
      offline: pos.offline!,
      printsBill: (LanRemoteOp op) {
        asked.add(op.opId);
        return prints;
      },
    );
    hub = LanHub(
      credentials: branch.hubCredentials(),
      applier: applier,
      address: InternetAddress.loopbackIPv4,
      port: 0,
      beaconEvery: null,
      advertisedHost: '127.0.0.1',
    );
    await hub.start();
    final LanClient botir = await connect(
      TestBranch.phone1,
      grant: branch.grant(TestBranch.phone1, 4, permissions: waiterPerms),
    );
    Future<SyncOperation> billAsked(String order) async {
      expect(
        (await botir.send(LanOpType.orderOpen, <String, Object?>{
          'order': order,
          'channel': 'pos_takeaway',
          'items': <Object?>[line(1, '1')],
        })).accepted,
        isTrue,
      );
      final LanOpResult r = await botir.send(
        LanOpType.billRequest,
        <String, Object?>{'order': order},
      );
      expect(r.accepted, isTrue, reason: '${r.reason} ${r.message}');
      expect(asked.last, r.opId);
      return pos.offline!.store.find(r.opId)!;
    }

    final SyncOperation printed = await billAsked(
      '9a000000-0000-4000-8000-000000000011',
    );
    expect(printed.type, SyncOpType.billRequest);
    expect(printed.origin, TestBranch.phone1);
    expect(printed.payload['printed_on_lan'], isTrue);

    prints = false;
    final SyncOperation left = await billAsked(
      '9a000000-0000-4000-8000-000000000012',
    );
    expect(left.payload.containsKey('printed_on_lan'), isFalse);
  });

  test('review P6 C1: every operation a device sends carries its op_id as the Idempotency-Key, so a request the '
      'server already did online (the waiter app retries it over the LAN with the same key) is replayed, not applied '
      'twice', () async {
    final LanClient botir = await connect(
      TestBranch.phone1,
      grant: branch.grant(
        TestBranch.phone1,
        4,
        name: 'Botir Aliyev',
        permissions: waiterPerms,
      ),
    );
    const String order = '9a000000-0000-4000-8000-0000000000aa';
    Future<SyncOperation> sent(
      String type,
      Map<String, Object?> payload,
      String key,
    ) async {
      final LanOpResult r = await botir.send(type, payload, opId: key);
      expect(r.accepted, isTrue, reason: '$type ${r.reason} ${r.message}');
      final SyncOperation queued = pos.offline!.store.find(key)!;
      // What PosSyncApplier::replayed() looks for
      expect(queued.payload['idempotency_key'], key, reason: type);
      return queued;
    }

    final SyncOperation opened = await sent(
      LanOpType.orderOpen,
      <String, Object?>{
        'order': order,
        'channel': 'pos_hall',
        'table_id': 2,
        'guests_count': 3,
        'items': <Object?>[line(1, '2')],
      },
      '7e000000-0000-4000-8000-000000000001',
    );
    expect(opened.type, SyncOpType.orderOpen);
    await sent(LanOpType.orderRound, <String, Object?>{
      'order': order,
      'items': <Object?>[line(2, '1')],
    }, '7e000000-0000-4000-8000-000000000002');
    await sent(LanOpType.orderSend, <String, Object?>{
      'order': order,
    }, '7e000000-0000-4000-8000-000000000003');
    final Map<String, Object?> seen = pos.offline!.book.order(order)!;
    final Object? first =
        ((seen['items']! as List<Object?>).first!
            as Map<String, Object?>)['id'];
    await sent(LanOpType.orderServed, <String, Object?>{
      'order': order,
      'line': first,
    }, '7e000000-0000-4000-8000-000000000004');
    await sent(LanOpType.billRequest, <String, Object?>{
      'order': order,
    }, '7e000000-0000-4000-8000-000000000005');
  });
}
