import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:pos_lan/pos_lan.dart';
import 'package:test/test.dart';

import 'support.dart';

/// Review P6 hardware/LAN (S1, S2, S3, S6, S7): what anyone on the branch's Wi-Fi can do to the hub without a key — a
/// huge or compressed frame, idle or slow sockets, a beacon for their own address, a captured `hello` — and what a
/// device without a staff grant may see.
class _Applier implements LanOpApplier {
  final StreamController<LanEvent> _events =
      StreamController<LanEvent>.broadcast();
  final StreamController<void> _status = StreamController<void>.broadcast();

  static const Map<String, Object?> fullOrder = <String, Object?>{
    'uuid': 'o-1',
    'status': 'preparing',
    'channel': 'pos_takeaway',
    'short_code': 'K1-007',
    'total_price': '84000.00',
    'waiter': <String, Object?>{'id': 4, 'name': 'Botir Aliyev'},
    'items': <Object?>[
      <String, Object?>{'id': 1, 'product_name': 'Osh', 'quantity': '2.000'},
    ],
    'queue': <String, Object?>{'number': 17, 'status': 'waiting'},
  };

  @override
  Future<LanOpResult> apply(LanRemoteOp op, LanGrantView staff) async =>
      LanOpResult(opId: op.opId, accepted: true);

  @override
  Map<String, Object?> state() => <String, Object?>{
    'floor': null,
    'open_orders': <Object?>[fullOrder],
  };

  @override
  Map<String, Object?>? order(String ref) => ref == 'o-1' ? fullOrder : null;

  @override
  Stream<LanEvent> get events => _events.stream;

  void emit(LanEvent e) => _events.add(e);

  @override
  bool get cloud => false;

  @override
  int get pending => 0;

  @override
  Stream<void> get statusChanges => _status.stream;
}

/// A TCP client of the hub that writes what a real client never would.
class _Raw {
  _Raw._(this.socket) {
    unawaited(socket.done.then((Object? _) {}, onError: (Object _) {}));
    socket.listen(
      _onData,
      onDone: _done,
      onError: (Object _) => _done(),
      cancelOnError: true,
    );
  }

  static Future<_Raw> connect(int port) async =>
      _Raw._(await Socket.connect(InternetAddress.loopbackIPv4, port));

  /// A WebSocket upgrade with no key and no `hello` (anyone on the Wi-Fi).
  static Future<_Raw> upgrade(int port, {String extra = ''}) async {
    final _Raw r = await connect(port);
    r.write(
      'GET /lan/v1/ws HTTP/1.1\r\n'
      'Host: 127.0.0.1:$port\r\n'
      'Upgrade: websocket\r\n'
      'Connection: Upgrade\r\n'
      'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n'
      'Sec-WebSocket-Version: 13\r\n'
      '$extra\r\n',
    );
    await r.head.future.timeout(const Duration(seconds: 5));
    return r;
  }

  final Socket socket;
  final List<int> _buf = <int>[];
  final Completer<String> head = Completer<String>();
  final Completer<void> closed = Completer<void>();

  /// The hub's text frames after the HTTP head.
  final List<String> texts = <String>[];

  void _onData(Uint8List d) {
    _buf.addAll(d);
    if (!head.isCompleted) {
      final int i = latin1.decode(_buf, allowInvalid: true).indexOf('\r\n\r\n');
      if (i < 0) return;
      head.complete(latin1.decode(_buf.sublist(0, i)));
      _buf.removeRange(0, i + 4);
    }
    while (_buf.length >= 2) {
      int len = _buf[1] & 0x7f;
      int off = 2;
      if (len == 126) {
        if (_buf.length < 4) return;
        len = (_buf[2] << 8) | _buf[3];
        off = 4;
      } else if (len == 127) {
        if (_buf.length < 10) return;
        len = 0;
        for (int i = 2; i < 10; i++) {
          len = (len << 8) | _buf[i];
        }
        off = 10;
      }
      if (_buf.length < off + len) return;
      final int opcode = _buf[0] & 0x0f;
      final List<int> payload = _buf.sublist(off, off + len);
      _buf.removeRange(0, off + len);
      if (opcode == 1) texts.add(utf8.decode(payload));
    }
  }

  void _done() {
    if (!closed.isCompleted) closed.complete();
  }

  void write(String text) => add(latin1.encode(text));

  void add(List<int> bytes) {
    try {
      socket.add(bytes);
    } on Object {
      // closed by the hub
    }
  }

  Future<void> flush() async {
    try {
      await socket.flush();
    } on Object {
      // closed by the hub
    }
  }

  Future<bool> closedWithin(Duration d) =>
      closed.future.then((void _) => true).timeout(d, onTimeout: () => false);

  /// The next text frame of the hub (`null`: none within [timeout]).
  Future<String?> nextText({
    Duration timeout = const Duration(seconds: 2),
    int after = 0,
  }) async {
    final DateTime until = DateTime.now().add(timeout);
    while (texts.length <= after && DateTime.now().isBefore(until)) {
      await Future<void>.delayed(const Duration(milliseconds: 10));
    }
    return texts.length > after ? texts[after] : null;
  }

  void destroy() => socket.destroy();

  /// A client frame header: [first] (FIN, RSV, opcode), the payload's [length], a zero mask (payload as is).
  static List<int> header(int first, int length) => <int>[
    first,
    if (length < 126)
      0x80 | length
    else if (length < 65536) ...<int>[
      0x80 | 126,
      length >> 8,
      length & 0xff,
    ] else ...<int>[
      0x80 | 127,
      for (int shift = 56; shift >= 0; shift -= 8) (length >> shift) & 0xff,
    ],
    0,
    0,
    0,
    0,
  ];

  /// A whole client text frame.
  static List<int> text(String s) {
    final List<int> bytes = utf8.encode(s);
    return <int>[...header(0x81, bytes.length), ...bytes];
  }
}

void main() {
  late TestBranch branch;
  late _Applier applier;
  late LanHub hub;
  final List<LanClient> clients = <LanClient>[];
  final List<_Raw> raws = <_Raw>[];

  setUp(() async {
    branch = TestBranch();
    applier = _Applier();
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
    for (final _Raw r in raws) {
      r.destroy();
    }
    raws.clear();
    for (final LanClient c in clients) {
      await c.dispose();
    }
    clients.clear();
    await hub.dispose();
  });

  Future<_Raw> upgrade({String extra = ''}) async {
    final _Raw r = await _Raw.upgrade(hub.port, extra: extra);
    raws.add(r);
    return r;
  }

  LanClient client(String device, {String? grant, LanApp app = LanApp.waiter}) {
    final LanClient c = LanClient(
      credentials: branch.client(device, port: hub.port, grant: grant),
      app: app,
      timeout: const Duration(seconds: 3),
    );
    clients.add(c);
    return c;
  }

  LanEndpoint at() => LanEndpoint('127.0.0.1', hub.port);

  /// A raw connection that said a valid `hello` as [device] (the hub's challenge in it).
  Future<_Raw> helloed(String device) async {
    final _Raw r = await upgrade();
    final Object? ch = jsonDecode(await r.nextText() ?? '{}');
    final String? challenge = ch is Map && ch['type'] == 'challenge'
        ? ch['nonce'] as String?
        : null;
    r.add(
      _Raw.text(
        LanFrameCodec.seal(
          LanFrame(
            type: LanMsg.hello,
            device: device,
            seq: DateTime.now().microsecondsSinceEpoch,
            ts: DateTime.now().millisecondsSinceEpoch,
            keyId: 1,
            payload: <String, Object?>{
              'nonce': 'n',
              'app': 'waiter',
              'challenge': challenge,
            },
          ),
          LanChannelKey(branch.client(device).deviceKey),
        ),
      ),
    );
    await r.flush();
    final String? welcome = await r.nextText(after: challenge == null ? 0 : 1);
    expect(welcome, isNotNull, reason: 'the hub did not welcome a valid hello');
    expect(hub.peers.map((LanPeerInfo p) => p.device), contains(device));
    return r;
  }

  group('S1: frame size before the WebSocket layer buffers it', () {
    test('a frame declared bigger than 64 KB before hello (1 GB) is cut at its header', () async {
      final _Raw r = await upgrade();
      r
        ..add(_Raw.header(0x81, 1 << 30))
        ..add(Uint8List(1 << 20)..fillRange(0, 1 << 20, 0x61));
      await r.flush();
      expect(await r.closedWithin(const Duration(seconds: 2)), isTrue);
      expect(hub.peers, isEmpty);
    });

    test(
      'small frames of one message add up: 96 KB before hello is cut',
      () async {
        final _Raw r = await upgrade();
        final Uint8List chunk = Uint8List(16 << 10)
          ..fillRange(0, 16 << 10, 0x61);
        r
          ..add(_Raw.header(0x01, chunk.length)) // text, not FIN
          ..add(chunk);
        for (int i = 0; i < 5; i++) {
          r
            ..add(_Raw.header(0x00, chunk.length)) // continuation, not FIN
            ..add(chunk);
        }
        await r.flush();
        expect(await r.closedWithin(const Duration(seconds: 2)), isTrue);
      },
    );

    test('after hello a frame declared bigger than 4 MB (5 MB) is cut at its header', () async {
      final _Raw r = await helloed(TestBranch.phone1);
      r
        ..add(_Raw.header(0x81, 5 << 20))
        ..add(Uint8List(1 << 20)..fillRange(0, 1 << 20, 0x61));
      await r.flush();
      expect(await r.closedWithin(const Duration(seconds: 2)), isTrue);
      await Future<void>.delayed(const Duration(milliseconds: 100));
      expect(hub.peers, isEmpty);
    });

    test(
      'after hello a message of 1 MB is still taken (a floor with every order)',
      () async {
        final _Raw r = await helloed(TestBranch.phone1);
        final int before = r.texts.length;
        final String big = 'x' * (1 << 20);
        r.add(
          _Raw.text(
            LanFrameCodec.seal(
              LanFrame(
                type: LanMsg.ping,
                device: TestBranch.phone1,
                seq: DateTime.now().microsecondsSinceEpoch + 5,
                ts: DateTime.now().millisecondsSinceEpoch,
                keyId: 1,
                payload: <String, Object?>{'pad': big},
              ),
              LanChannelKey(branch.client(TestBranch.phone1).deviceKey),
            ),
          ),
        );
        await r.flush();
        expect(await r.nextText(after: before), isNotNull, reason: 'no pong');
        expect(r.closed.isCompleted, isFalse);
      },
    );

    test('permessage-deflate is not agreed, and a compressed frame ends the connection', () async {
      final _Raw r = await upgrade(
        extra: 'Sec-WebSocket-Extensions: permessage-deflate; client_no_context_takeover; server_no_context_takeover\r\n',
      );
      expect(await r.head.future, startsWith('HTTP/1.1 101'));
      expect((await r.head.future).toLowerCase(), isNot(contains('deflate')));
      r
        ..add(_Raw.header(0xC1, 10)) // FIN + RSV1 (compressed) + text
        ..add(List<int>.filled(10, 0));
      await r.flush();
      expect(await r.closedWithin(const Duration(seconds: 2)), isTrue);
    });

    test(
      'a request that sends its head slowly (slowloris) is closed',
      () async {
        final _Raw r = await _Raw.connect(hub.port);
        raws.add(r);
        r.write('GET /lan/v1/ws HTTP/1.1\r\nHost: 127.0.0.1\r\n');
        final DateTime until = DateTime.now().add(const Duration(seconds: 6));
        int n = 0;
        while (!r.closed.isCompleted && DateTime.now().isBefore(until)) {
          r.write('X-Slow-${n++}: 1\r\n');
          await r.flush();
          await Future<void>.delayed(const Duration(milliseconds: 200));
        }
        expect(r.closed.isCompleted, isTrue);
      },
    );

    test('an HTTP head bigger than 8 KB is closed', () async {
      final _Raw r = await _Raw.connect(hub.port);
      raws.add(r);
      r.write('GET /lan/v1/ws HTTP/1.1\r\nX-Big: ${'a' * (64 << 10)}\r\n');
      await r.flush();
      expect(await r.closedWithin(const Duration(seconds: 2)), isTrue);
    });
  });

  group('S2: key-less sockets', () {
    test('64 idle sockets that never say hello do not lock a real phone out, and are dropped soon', () async {
      for (int i = 0; i < 64; i++) {
        final _Raw r = await _Raw.connect(hub.port);
        raws.add(r);
        r.write(
          'GET /lan/v1/ws HTTP/1.1\r\nHost: 127.0.0.1\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n'
          'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\nSec-WebSocket-Version: 13\r\n\r\n',
        );
      }
      await Future<void>.delayed(const Duration(milliseconds: 300));
      final LanClient phone = client(
        TestBranch.phone1,
        grant: branch.grant(TestBranch.phone1, 7),
      );
      final LanWelcome w = await phone.connect(at());
      expect(w.staff?['id'], 7);
      expect(hub.peers.map((LanPeerInfo p) => p.device), <String>[
        TestBranch.phone1,
      ]);
      // The idle ones go within a few seconds; the phone stays
      final DateTime until = DateTime.now().add(const Duration(seconds: 5));
      while (raws.any((_Raw r) => !r.closed.isCompleted) &&
          DateTime.now().isBefore(until)) {
        await Future<void>.delayed(const Duration(milliseconds: 50));
      }
      expect(raws.where((_Raw r) => !r.closed.isCompleted), isEmpty);
      expect(phone.connected, isTrue);
    });
  });

  group('S3: the beacon', () {
    test('GET /lan/v1/beacon names only the hub\'s own address, whatever Host the caller sends', () async {
      Future<LanBeacon?> fetch(String host) async {
        final HttpClient http = HttpClient();
        try {
          final HttpClientRequest req = await http.get(
            '127.0.0.1',
            hub.port,
            '/lan/v1/beacon',
          );
          req.headers.host = host;
          final HttpClientResponse res = await req.close();
          return LanBeacon.verify(
            await utf8.decodeStream(res),
            branch.client(TestBranch.phone1),
            now: DateTime.now(),
          );
        } finally {
          http.close(force: true);
        }
      }

      final LanBeacon? forged = await fetch('10.66.66.66');
      expect(forged, isNotNull);
      expect(forged!.host, '127.0.0.1');
      expect(forged.port, hub.port);
      expect((await fetch('127.0.0.1:${hub.port}'))?.host, '127.0.0.1');
    });
  });

  test('S3: UDP discovery keeps every valid beacon heard in its window, each address once, in the order heard', () async {
    final LanCredentials phone = branch.client(TestBranch.phone1);
    final RawDatagramSocket probe = await RawDatagramSocket.bind(
      InternetAddress.loopbackIPv4,
      0,
    );
    final int udpPort = probe.port;
    probe.close();
    final Future<List<LanBeacon>> found = LanDiscovery.collect(
      phone,
      port: udpPort,
      timeout: const Duration(milliseconds: 800),
    );
    await Future<void>.delayed(const Duration(milliseconds: 100));
    final RawDatagramSocket sender = await RawDatagramSocket.bind(
      InternetAddress.loopbackIPv4,
      0,
    );
    String signed(String host) => LanBeacon(
      branchId: branch.branchId,
      keyId: branch.keyId,
      hubDevice: TestBranch.hub,
      host: host,
      port: hub.port,
      ts: DateTime.now().millisecondsSinceEpoch,
    ).sign(phone.beaconKey);
    for (final String text in <String>[
      signed('10.0.0.9'),
      signed('10.0.0.9'),
      'not a beacon',
      // Another branch's hub (its own beacon key)
      LanBeacon(
        branchId: 13,
        keyId: 1,
        hubDevice: TestBranch.hub,
        host: '10.0.0.66',
        port: 1,
        ts: DateTime.now().millisecondsSinceEpoch,
      ).sign(TestBranch(branchId: 13).hubCredentials().beaconKey),
      signed('127.0.0.1'),
    ]) {
      sender.send(utf8.encode(text), InternetAddress.loopbackIPv4, udpPort);
      await Future<void>.delayed(const Duration(milliseconds: 20));
    }
    final List<LanBeacon> all = await found;
    sender.close();
    expect(all.map((LanBeacon b) => b.host), <String>['10.0.0.9', '127.0.0.1']);
  });

  group('S6: replay of a captured hello', () {
    test('a hello captured on the Wi-Fi does not open another connection, after a hub restart either', () async {
      final RawPeer real = await RawPeer.connect(
        hub.port,
        TestBranch.phone1,
        branch.client(TestBranch.phone1).deviceKey,
      );
      final String hello = real.seal(LanMsg.hello, <String, Object?>{
        'nonce': 'n1',
        'app': 'waiter',
        'grant': branch.grant(TestBranch.phone1, 4),
      });
      real.sendRaw(hello);
      await real.nextOf(LanMsg.welcome);
      await real.close();

      // The till app restarted (a new hub, the replay store empty)
      await hub.dispose();
      hub = LanHub(
        credentials: branch.hubCredentials(),
        applier: applier,
        address: InternetAddress.loopbackIPv4,
        port: 0,
        beaconEvery: null,
        advertisedHost: '127.0.0.1',
      );
      await hub.start();
      final RawPeer replay = await RawPeer.connect(
        hub.port,
        TestBranch.phone1,
        branch.client(TestBranch.phone1).deviceKey,
      );
      replay.sendRaw(hello);
      final (int?, String?) closed = await replay.closed();
      expect(closed.$2, 'challenge');
      expect(hub.peers, isEmpty);

      // The phone itself connects as always
      final LanClient phone = client(
        TestBranch.phone1,
        grant: branch.grant(TestBranch.phone1, 4),
      );
      expect((await phone.connect(at())).staff?['id'], 4);
    });
  });

  group('S7: devices without a staff grant', () {
    test('a display gets the cart and the queue only, a KDS before sign-in nothing of the orders; '
        'state and orders need a grant', () async {
      final LanClient waiter = client(
        TestBranch.phone1,
        grant: branch.grant(TestBranch.phone1, 4),
      );
      final LanClient display = client(TestBranch.display, app: LanApp.display);
      final LanClient kds = client(TestBranch.kds, app: LanApp.kds);
      final Inbox atWaiter = Inbox(waiter.events),
          atDisplay = Inbox(display.events),
          atKds = Inbox(kds.events);
      await waiter.connect(at());
      await display.connect(at());
      await kds.connect(at());

      applier
        ..emit(
          const LanEvent(LanMsg.orderChanged, <String, Object?>{
            'order': _Applier.fullOrder,
            'ref': 'o-1',
          }),
        )
        ..emit(
          const LanEvent(LanMsg.kitchenTicket, <String, Object?>{
            'order': 'o-1',
            'lines': <Object?>[
              <String, Object?>{'id': 1, 'name': 'Osh'},
            ],
          }),
        );
      hub
        ..publish(LanMsg.cartDisplay, <String, Object?>{'total': '42000.00'})
        ..publish(LanMsg.queueReady, <String, Object?>{
          'number': 17,
          'order': 'o-1',
        });

      for (final String type in <String>[
        LanMsg.orderChanged,
        LanMsg.kitchenTicket,
        LanMsg.cartDisplay,
        LanMsg.queueReady,
      ]) {
        await atWaiter.wait(type);
      }
      await atDisplay.wait(LanMsg.queueReady);
      await atDisplay.wait(LanMsg.cartDisplay);
      await Future<void>.delayed(const Duration(milliseconds: 200));

      // The display: the queue number as the screen shows it, nothing else of the order
      expect(atDisplay.of(LanMsg.kitchenTicket), isEmpty);
      final List<LanEvent> shown = atDisplay.of(LanMsg.orderChanged).toList();
      expect(shown, hasLength(1));
      expect(shown.single.payload, <String, Object?>{
        'ref': 'o-1',
        'order': <String, Object?>{
          'uuid': 'o-1',
          'status': 'preparing',
          'channel': 'pos_takeaway',
          'queue': <String, Object?>{'number': 17, 'status': 'waiting'},
        },
      });
      // The KDS before sign-in: nothing of the orders, the cart or the queue
      for (final String type in <String>[
        LanMsg.orderChanged,
        LanMsg.kitchenTicket,
        LanMsg.cartDisplay,
        LanMsg.queueReady,
      ]) {
        expect(atKds.of(type), isEmpty, reason: type);
      }

      // state.get and order.get need a grant
      final RawPeer screen = await RawPeer.connect(
        hub.port,
        TestBranch.display,
        branch.client(TestBranch.display).deviceKey,
      );
      screen.sendRaw(
        screen.seal(LanMsg.hello, <String, Object?>{
          'nonce': 'n',
          'app': 'display',
        }),
      );
      await screen.nextOf(LanMsg.welcome);
      screen.sendRaw(screen.seal(LanMsg.stateGet, const <String, Object?>{}));
      expect(
        (await screen.nextOf(LanMsg.error)).payload['reason'],
        'grant_required',
      );
      screen.sendRaw(
        screen.seal(LanMsg.orderGet, const <String, Object?>{'order': 'o-1'}),
      );
      expect(
        (await screen.nextOf(LanMsg.error)).payload['reason'],
        'grant_required',
      );
      await screen.close();
      // ... which the waiter has
      expect(
        ((await waiter.state())['open_orders']! as List<Object?>),
        hasLength(1),
      );
    });
  });
}
