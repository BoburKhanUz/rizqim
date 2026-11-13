import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:pos_sync/pos_sync.dart' show newUuid;

import 'beacon.dart';
import 'credentials.dart';
import 'crypto.dart';
import 'frame.dart';
import 'messages.dart';

/// The hub could not be reached or refused the device (with the reason it gave: `unknown_device`, `bad_sig`,
/// `key_mismatch`...).
class LanUnreachable implements Exception {
  const LanUnreachable(this.reason, [this.message]);

  final String reason;
  final String? message;

  @override
  String toString() => 'LanUnreachable($reason)';
}

/// The hub's `welcome`.
class LanWelcome {
  const LanWelcome({
    required this.hubDevice,
    required this.hubTime,
    this.hubName,
    this.staff,
    this.grantError,
    this.state = const <String, Object?>{},
  });

  final String hubDevice;
  final String? hubName;
  final DateTime hubTime;

  /// Who the hub takes this device's operations from (`null`: nobody, see [grantError]).
  final Map<String, Object?>? staff;
  final String? grantError;

  /// `lan.state` at connect time.
  final Map<String, Object?> state;
}

/// A device's side of the bridge (waiter, KDS, customer display; README §5–6): connects to the hub, proves itself with
/// its device key and grant, sends operations and requests, and gets the hub's events. Frames from anyone but the
/// branch's hub, unsigned, on another key or replayed are dropped.
class LanClient {
  LanClient({
    required this.credentials,
    required this.app,
    this.version = '1',
    DateTime Function()? clock,
    this._random,
    this.timeout = const Duration(seconds: 10),
  }) : _clock = clock ?? DateTime.now,
       _key = LanChannelKey(credentials.deviceKey) {
    _seq = LanSequence(_clock());
    _replay = LanReplayGuard(window: credentials.clockWindow);
  }

  LanCredentials credentials;
  final LanApp app;
  final String version;
  final Duration timeout;
  final DateTime Function() _clock;
  final Random? _random;
  final LanChannelKey _key;
  late final LanSequence _seq;
  late final LanReplayGuard _replay;

  WebSocket? _ws;
  LanEndpoint? _endpoint;
  LanWelcome? _welcome;
  Duration _offset = Duration.zero;
  Completer<LanWelcome>? _welcoming;
  Completer<String>? _challenged;
  String? _nonce;
  final Map<String, Completer<LanOpResult>> _pendingOps =
      <String, Completer<LanOpResult>>{};
  final List<Completer<Map<String, Object?>>> _pendingState =
      <Completer<Map<String, Object?>>>[];
  final StreamController<LanEvent> _events =
      StreamController<LanEvent>.broadcast();
  final StreamController<bool> _connection = StreamController<bool>.broadcast();
  Map<String, Object?> _lanState = const <String, Object?>{};

  /// Frames dropped (bad signature, replayed, not from the hub).
  int droppedFrames = 0;

  bool get connected => _ws != null && _welcome != null;

  LanEndpoint? get endpoint => _endpoint;

  LanWelcome? get welcome => _welcome;

  /// The hub's latest `lan.state` (`cloud`, `pending`, `devices`).
  Map<String, Object?> get lanState => _lanState;

  /// The hub's queue reached the server (switch back to `cloud`).
  bool get hubDrained => LanModeRule.drained(_lanState);

  /// The hub's clock as this device estimates it.
  DateTime hubNow() => _clock().add(_offset);

  Stream<LanEvent> get events => _events.stream;

  /// `true` when connected, `false` when the connection went.
  Stream<bool> get connection => _connection.stream;

  /// Connects and says `hello`; completes with the hub's `welcome` or throws [LanUnreachable].
  Future<LanWelcome> connect(LanEndpoint endpoint) async {
    await close();
    final String hub = credentials.hubDevice ?? '';
    if (hub.isEmpty) {
      throw const LanUnreachable(
        'no_hub',
        "Filialda markaz kassa tanlanmagan.",
      );
    }
    WebSocket ws;
    try {
      // No permessage-deflate (the hub agrees to none) and no message larger than a frame may be (review P6 S1)
      ws = await WebSocket.connect(
        endpoint.ws.toString(),
        compression: CompressionOptions.compressionOff,
        maxPayloadLength: LanFrameCodec.maxBytes,
      ).timeout(timeout);
    } on Object {
      throw const LanUnreachable(
        'unreachable',
        'Markaz kassaga ulanib bo\'lmadi.',
      );
    }
    ws.pingInterval = const Duration(seconds: 10);
    _ws = ws;
    _endpoint = endpoint;
    final Completer<LanWelcome> welcoming = Completer<LanWelcome>();
    _welcoming = welcoming;
    final Completer<String> challenged = Completer<String>();
    _challenged = challenged;
    ws.listen(
      _onFrame,
      onDone: () => _gone(ws),
      onError: (Object _) => _gone(ws),
      cancelOnError: true,
    );
    _nonce = base64.encode(lanRandomBytes(16, _random));
    _offset = Duration.zero;
    try {
      // The hub's challenge for this connection first (review P6 S6: a captured hello opens nothing)
      final String challenge = await Future.any(<Future<String>>[
        challenged.future,
        welcoming.future.then((LanWelcome _) => ''),
      ]).timeout(timeout);
      _send(LanMsg.hello, <String, Object?>{
        'nonce': _nonce,
        'challenge': challenge,
        'app': app.wire,
        'version': version,
        'grant': credentials.grant,
      });
      return await welcoming.future.timeout(timeout);
    } on TimeoutException {
      await close();
      throw const LanUnreachable('timeout', 'Markaz kassa javob bermadi.');
    }
  }

  /// Sends an operation (README §6); completes with the hub's answer. The same [opId] sent again is applied once: send
  /// it again after a lost connection.
  Future<LanOpResult> send(
    String type,
    Map<String, Object?> payload, {
    String? opId,
    DateTime? occurredAt,
  }) async {
    if (!connected) throw const LanUnreachable('not_connected');
    final String id = (opId ?? newUuid(_random)).toLowerCase();
    final Completer<LanOpResult> done = _pendingOps[id] ??=
        Completer<LanOpResult>();
    _send(LanMsg.op, <String, Object?>{
      'op_id': id,
      'type': type,
      'occurred_at': (occurredAt ?? hubNow()).toUtc().toIso8601String(),
      'staff_id': welcome?.staff?['id'],
      'payload': payload,
    });
    try {
      return await done.future.timeout(timeout);
    } on TimeoutException {
      throw const LanUnreachable('timeout', 'Markaz kassa javob bermadi.');
    } finally {
      _pendingOps.remove(id);
    }
  }

  /// The hub's floor, open orders and `lan` state.
  Future<Map<String, Object?>> state() async {
    if (!connected) throw const LanUnreachable('not_connected');
    final Completer<Map<String, Object?>> done =
        Completer<Map<String, Object?>>();
    _pendingState.add(done);
    _send(LanMsg.stateGet, const <String, Object?>{});
    try {
      return await done.future.timeout(timeout);
    } on TimeoutException {
      _pendingState.remove(done);
      throw const LanUnreachable('timeout', 'Markaz kassa javob bermadi.');
    }
  }

  /// Asks for one order (it comes as `order.changed` on [events]).
  void requestOrder(String order) =>
      _send(LanMsg.orderGet, <String, Object?>{'order': order});

  /// The staff member changed on this device (signed in online): the hub takes the new grant.
  Future<LanWelcome> updateGrant(String? grant) async {
    credentials = credentials.withGrant(grant);
    if (!connected) throw const LanUnreachable('not_connected');
    final Completer<LanWelcome> welcoming = Completer<LanWelcome>();
    _welcoming = welcoming;
    _nonce = base64.encode(lanRandomBytes(16, _random));
    _send(LanMsg.grant, <String, Object?>{'grant': grant, 'nonce': _nonce});
    return welcoming.future.timeout(timeout);
  }

  Future<void> close() async {
    final WebSocket? ws = _ws;
    if (ws == null) return;
    _ws = null;
    await ws.close(1000).timeout(const Duration(seconds: 2), onTimeout: () {});
    _failPending(const LanUnreachable('closed'));
  }

  Future<void> dispose() async {
    await close();
    await _events.close();
    await _connection.close();
  }

  // --- internals ----------------------------------------------------------------------------------------------------

  void _send(String type, Map<String, Object?> payload) {
    final WebSocket? ws = _ws;
    if (ws == null) return;
    ws.add(
      LanFrameCodec.seal(
        LanFrame(
          type: type,
          device: credentials.device,
          seq: _seq.take(),
          ts: hubNow().millisecondsSinceEpoch,
          keyId: credentials.keyId,
          payload: payload,
        ),
        _key,
        random: _random,
      ),
    );
  }

  void _onFrame(Object? data) {
    // Disposed: a frame still on its way is dropped
    if (_events.isClosed) return;
    final Completer<String>? challenged = _challenged;
    if (challenged != null && !challenged.isCompleted && data is String) {
      _challenged = null;
      Object? j;
      try {
        j = jsonDecode(data);
      } on FormatException {
        j = null;
      }
      if (j is Map && j['type'] == LanMsg.challenge && j['nonce'] is String) {
        challenged.complete(j['nonce']! as String);
        return;
      }
      droppedFrames++;
      return;
    }
    final LanFrame frame;
    try {
      final Map<String, Object?> envelope = LanFrameCodec.envelope(data);
      if (envelope['kid'] != credentials.keyId ||
          (envelope['device']! as String).toLowerCase() !=
              credentials.hubDevice) {
        droppedFrames++;
        return;
      }
      frame = LanFrameCodec.open(envelope, _key);
    } on LanFrameException {
      droppedFrames++;
      return;
    }

    if (frame.type == LanMsg.welcome) {
      // Fresh by its nonce (the hub echoes ours), so its time is the hub's clock
      if (frame.payload['nonce'] != _nonce ||
          _welcoming == null ||
          _welcoming!.isCompleted) {
        droppedFrames++;
        return;
      }
      if (_replay.check(frame.device, frame.seq, frame.ts, frame.ts) != null) {
        droppedFrames++;
        return;
      }
      _takeWelcome(frame);
      return;
    }
    if (_replay.check(
          frame.device,
          frame.seq,
          frame.ts,
          hubNow().millisecondsSinceEpoch,
        ) !=
        null) {
      droppedFrames++;
      return;
    }
    switch (frame.type) {
      case LanMsg.opResult:
        final LanOpResult r = LanOpResult.fromJson(frame.payload);
        final Completer<LanOpResult>? c = _pendingOps[r.opId];
        if (c != null && !c.isCompleted) c.complete(r);
      case LanMsg.state:
        final Object? lan = frame.payload['lan'];
        if (lan is Map) _lanState = lan.cast<String, Object?>();
        if (_pendingState.isNotEmpty) {
          _pendingState.removeAt(0).complete(frame.payload);
        }
        _events.add(LanEvent(frame.type, frame.payload));
      case LanMsg.lanState:
        _lanState = frame.payload;
        _events.add(LanEvent(frame.type, frame.payload));
      default:
        _events.add(LanEvent(frame.type, frame.payload));
    }
  }

  void _takeWelcome(LanFrame frame) {
    final Map<String, Object?> p = frame.payload;
    final int hubTime = (p['hub_time'] as num?)?.toInt() ?? frame.ts;
    _offset = Duration(milliseconds: hubTime - _clock().millisecondsSinceEpoch);
    final Object? state = p['state'];
    if (state is Map) _lanState = state.cast<String, Object?>();
    final Map<String, Object?> hub = p['hub'] is Map
        ? (p['hub']! as Map).cast<String, Object?>()
        : const <String, Object?>{};
    final LanWelcome w = LanWelcome(
      hubDevice: frame.device,
      hubName: hub['name'] as String?,
      hubTime: DateTime.fromMillisecondsSinceEpoch(hubTime, isUtc: true),
      staff: p['staff'] is Map
          ? (p['staff']! as Map).cast<String, Object?>()
          : null,
      grantError: p['grant_error'] as String?,
      state: _lanState,
    );
    final bool first = _welcome == null;
    _welcome = w;
    _welcoming?.complete(w);
    _welcoming = null;
    if (first && !_connection.isClosed) _connection.add(true);
  }

  void _gone(WebSocket ws) {
    final int? code = ws.closeCode;
    final String? reason = ws.closeReason;
    if (identical(_ws, ws)) _ws = null;
    final bool was = _welcome != null;
    _welcome = null;
    final LanUnreachable why = LanUnreachable(
      reason == null || reason.isEmpty ? 'closed' : reason,
      code == null ? null : 'WebSocket $code',
    );
    final Completer<LanWelcome>? w = _welcoming;
    _welcoming = null;
    if (w != null && !w.isCompleted) w.completeError(why);
    _failPending(why);
    if (was && !_connection.isClosed) _connection.add(false);
  }

  void _failPending(LanUnreachable why) {
    for (final Completer<LanOpResult> c in _pendingOps.values) {
      if (!c.isCompleted) c.completeError(why);
    }
    for (final Completer<Map<String, Object?>> c in _pendingState) {
      if (!c.isCompleted) c.completeError(why);
    }
    _pendingState.clear();
  }
}
