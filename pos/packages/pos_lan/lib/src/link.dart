import 'dart:async';

import 'beacon.dart';
import 'client.dart';
import 'credentials.dart';
import 'messages.dart';

/// Android's `WifiManager.MulticastLock` (or nothing elsewhere): many phones drop broadcast datagrams while the screen
/// is on Wi-Fi power save unless an app holds it. Held only while the beacon is being listened for.
abstract interface class LanMulticastLock {
  Future<void> acquire();
  Future<void> release();
}

/// How a device finds its hub (tests replace it): the beacon, at most [timeout].
typedef LanBeaconListener = Future<LanBeacon?> Function(
  LanCredentials credentials, {
  Duration timeout,
});

/// The shared client side of the bridge for the waiter, KDS and customer display apps (README §1, §4): finds the hub
/// (the manual address first, then the last one that worked, the UDP beacon, and this machine's own address — a display
/// on the till's PC), connects with the device's credentials, keeps the mode by [LanModeRule] and reconnects.
///
/// * The app tells it when its own requests reach the server or not ([reportCloud]); while the server is away it also
///   asks [probeCloud] every [probeEvery] (requests go to the hub then, so nothing else would notice the server back).
/// * `cloud` → the LAN is idle (no connection, no discovery). The server stops answering → it connects: `lan` if the
///   hub answers, `alone` if not (and keeps trying, [retry]). Back to `cloud` only when the server answers **and** the
///   hub says its queue reached the server (`cloud: true, pending: 0`), or the hub is gone; then it disconnects.
/// * Pure Dart (no Flutter): [changes] for the screen.
class LanLink {
  LanLink({
    required this.app,
    required this._probeCloud,
    this.multicast,
    LanClient Function(LanCredentials credentials, LanApp app)? clientFactory,
    LanBeaconListener? listen,
    this.listenFor = const Duration(seconds: 3),
    this.retry = const <Duration>[
      Duration(seconds: 2),
      Duration(seconds: 5),
      Duration(seconds: 10),
      Duration(seconds: 30),
    ],
    this.probeEvery = const Duration(seconds: 15),
    this.tickEvery = const Duration(seconds: 1),
    this.tryLoopback = true,
    DateTime Function()? clock,
  }) : _clientFactory =
           clientFactory ??
           ((LanCredentials c, LanApp a) => LanClient(credentials: c, app: a)),
       _listen =
           listen ??
           ((
             LanCredentials c, {
             Duration timeout = const Duration(seconds: 3),
           }) => LanDiscovery.listen(c, timeout: timeout)),
       _clock = clock ?? DateTime.now;

  final LanApp app;
  final LanMulticastLock? multicast;
  final Duration listenFor;
  final List<Duration> retry;
  final Duration probeEvery;
  final Duration tickEvery;

  /// Also try `127.0.0.1` (a customer display on the till's own PC, Windows).
  final bool tryLoopback;

  final Future<bool> Function() _probeCloud;
  final LanClient Function(LanCredentials credentials, LanApp app)
  _clientFactory;
  final LanBeaconListener _listen;
  final DateTime Function() _clock;

  LanCredentials? _credentials;
  LanClient? _client;
  final List<StreamSubscription<Object?>> _clientSubs =
      <StreamSubscription<Object?>>[];
  LanEndpoint? _manual;
  LanEndpoint? _last;
  LanMode _mode = LanMode.cloud;
  bool _cloud = true;
  bool _connecting = false;
  bool _probing = false;
  int _failures = 0;
  DateTime? _nextAttempt;
  DateTime? _nextProbe;
  String? _error;
  String? _reason;
  Timer? _ticker;
  bool _started = false;
  bool _disposed = false;

  final StreamController<void> _changes = StreamController<void>.broadcast();
  final StreamController<LanEvent> _events =
      StreamController<LanEvent>.broadcast();
  final StreamController<LanMode> _modes =
      StreamController<LanMode>.broadcast();

  LanMode get mode => _mode;

  /// The server answers (the app's last request or the probe).
  bool get cloud => _cloud;

  /// Operations go to the hub.
  bool get viaHub => _mode == LanMode.lan && connected;

  bool get connected => _client?.connected ?? false;

  LanClient? get client => connected ? _client : null;

  LanCredentials? get credentials => _credentials;

  /// The hub this device is connected to (its address).
  LanEndpoint? get endpoint => connected ? _client!.endpoint : null;

  /// The address typed in or scanned (settings), tried first.
  LanEndpoint? get manualEndpoint => _manual;

  String? get hubName => _client?.welcome?.hubName ?? _credentials?.hubName;

  /// Who the hub takes this device's operations from (`null`: nobody — see [grantError]).
  Map<String, Object?>? get staff => _client?.welcome?.staff;

  String? get grantError => _client?.welcome?.grantError;

  /// The hub's queue: operations not on the server yet.
  int get hubPending =>
      ((_client?.lanState ?? const <String, Object?>{})['pending'] as num?)
          ?.toInt() ??
      0;

  /// Why the hub could not be reached (Uzbek), `null` when connected or not tried.
  String? get lastError => _error;

  /// The hub's reason for shutting this device out (`unknown_device`, `key_mismatch`, `bad_sig` ...), `null` when
  /// connected, not tried or simply not found. `key_mismatch`: the keys were rotated — fetch them again.
  String? get lastReason => _reason;

  /// This terminal can use the bridge: its branch has a hub and it is not the hub itself.
  bool get available {
    final LanCredentials? c = _credentials;
    return c != null && !c.isHub && (c.hubDevice ?? '').isNotEmpty;
  }

  Stream<void> get changes => _changes.stream;

  /// The hub's events (only while connected; in `cloud` mode there is no connection).
  Stream<LanEvent> get events => _events.stream;

  Stream<LanMode> get modes => _modes.stream;

  /// The hub's clock as this device estimates it (the device's own without a connection).
  DateTime hubNow() => _client?.hubNow() ?? _clock();

  /// Starts the loop. Its timer runs only while this terminal can use the bridge ([available]).
  void start() {
    if (_disposed) return;
    _started = true;
    _arm();
    _tick();
  }

  void _arm() {
    if (!_started || _disposed || !available) {
      _ticker?.cancel();
      _ticker = null;
      return;
    }
    _ticker ??= Timer.periodic(tickEvery, (_) => _tick());
  }

  /// New credentials (a later bootstrap or `GET /pos/lan`): `null` stops the bridge (signed out, no LAN for this
  /// terminal). The grant changed with the same keys → the hub is told on the open connection.
  Future<void> setCredentials(LanCredentials? credentials) async {
    final LanCredentials? was = _credentials;
    _credentials = credentials;
    _arm();
    final LanClient? c = _client;
    if (credentials == null || !available) {
      await _drop();
      _recompute();
      return;
    }
    if (c != null && was != null && _sameKeys(was, credentials)) {
      if (was.grant != credentials.grant && c.connected) {
        try {
          await c.updateGrant(credentials.grant);
        } on Object {
          // The next connect says hello with it
        }
      } else {
        c.credentials = credentials;
      }
      _emit();
      return;
    }
    // Other keys (rotated, another hub): reconnect with the new ones
    await _drop();
    _failures = 0;
    _nextAttempt = null;
    _recompute();
    _tick();
  }

  static bool _sameKeys(LanCredentials a, LanCredentials b) =>
      a.branchId == b.branchId &&
      a.keyId == b.keyId &&
      a.device == b.device &&
      a.hubDevice == b.hubDevice &&
      a.port == b.port;

  /// The app's own request reached the server ([reachable]) or got no answer.
  void reportCloud(bool reachable) {
    if (_disposed || reachable == _cloud) return;
    _cloud = reachable;
    if (!reachable) {
      // Look for the hub now (not after the backoff of an earlier, idle failure)
      _nextAttempt = null;
      _failures = 0;
    }
    _recompute();
    _tick();
  }

  /// The hub's address typed in or scanned (`rizqim-lan://host:port?...` or `host[:port]`); `null`/empty clears it.
  /// Returns an Uzbek error for text that is not an address.
  String? setManual(String? text) {
    final String t = (text ?? '').trim();
    if (t.isEmpty) {
      _manual = null;
      _emit();
      return null;
    }
    final LanEndpoint? e = LanEndpoint.parse(
      t,
      defaultPort: _credentials?.port ?? 18480,
    );
    if (e == null) {
      return "Manzil noto'g'ri: masalan, 192.168.1.20 yoki 192.168.1.20:18480.";
    }
    _manual = e;
    _nextAttempt = null;
    _failures = 0;
    _emit();
    _tick();
    return null;
  }

  /// Try now (the "Ulanish" button): the server too.
  Future<void> retryNow() async {
    _nextAttempt = null;
    _nextProbe = null;
    _failures = 0;
    await _probe();
    if (_wantHub) await _connect();
    _recompute();
  }

  // --- requests (only through the hub, `lan` mode) ------------------------------------------------------------------

  /// An operation to the hub (README §6). Throws [LanUnreachable] without a connection.
  Future<LanOpResult> send(
    String type,
    Map<String, Object?> payload, {
    String? opId,
  }) {
    final LanClient? c = client;
    if (c == null) {
      return Future<LanOpResult>.error(
        const LanUnreachable('not_connected', 'Markaz kassaga ulanmagan.'),
      );
    }
    return c.send(type, payload, opId: opId);
  }

  /// The hub's `{lan, floor, open_orders}`.
  Future<Map<String, Object?>> state() {
    final LanClient? c = client;
    if (c == null) {
      return Future<Map<String, Object?>>.error(
        const LanUnreachable('not_connected', 'Markaz kassaga ulanmagan.'),
      );
    }
    return c.state();
  }

  /// One order as the hub shows it (`order.get` → `order.changed`); `null` when the hub does not know it.
  Future<Map<String, Object?>?> order(
    String ref, {
    Duration timeout = const Duration(seconds: 5),
  }) async {
    final LanClient? c = client;
    if (c == null) {
      throw const LanUnreachable('not_connected', 'Markaz kassaga ulanmagan.');
    }
    final String id = ref.toLowerCase();
    final Completer<Map<String, Object?>?> done =
        Completer<Map<String, Object?>?>();
    final StreamSubscription<LanEvent> sub = c.events.listen((LanEvent e) {
      if (done.isCompleted) return;
      if (e.type == LanMsg.orderChanged && _isOrder(e.payload, id)) {
        done.complete((e.payload['order']! as Map).cast<String, Object?>());
      } else if (e.type == LanMsg.error &&
          e.payload['reason'] == 'not_found' &&
          '${e.payload['order']}'.toLowerCase() == id) {
        done.complete(null);
      }
    });
    try {
      c.requestOrder(ref);
      return await done.future.timeout(timeout);
    } on TimeoutException {
      throw const LanUnreachable('timeout', 'Markaz kassa javob bermadi.');
    } finally {
      await sub.cancel();
    }
  }

  static bool _isOrder(Map<String, Object?> payload, String id) {
    final Object? o = payload['order'];
    if (o is! Map) return false;
    final Object? ref = payload['ref'];
    return (ref is String && ref.toLowerCase() == id) ||
        '${o['uuid']}'.toLowerCase() == id ||
        '${o['external_id']}'.toLowerCase() == id;
  }

  // --- the loop -----------------------------------------------------------------------------------------------------

  /// The hub is needed: the server is away, or the LAN mode is not over yet.
  bool get _wantHub => available && (!_cloud || _mode != LanMode.cloud);

  void _tick() {
    if (_disposed || !_started || !available) return;
    final DateTime now = _clock();
    if ((!_cloud || _mode == LanMode.lan) &&
        !_probing &&
        (_nextProbe == null || !now.isBefore(_nextProbe!))) {
      unawaited(_probe());
    }
    if (_wantHub &&
        !connected &&
        !_connecting &&
        (_nextAttempt == null || !now.isBefore(_nextAttempt!))) {
      unawaited(_connect());
    }
  }

  Future<void> _probe() async {
    if (_probing) return;
    _probing = true;
    bool ok;
    try {
      ok = await _probeCloud();
    } on Object {
      ok = false;
    } finally {
      _probing = false;
    }
    _nextProbe = _clock().add(probeEvery);
    if (_disposed) return;
    if (ok != _cloud) {
      _cloud = ok;
      _recompute();
    }
  }

  Future<void> _connect() async {
    final LanCredentials? creds = _credentials;
    if (creds == null || _connecting || _disposed) return;
    _connecting = true;
    _emit();
    try {
      final List<LanEndpoint> tried = <LanEndpoint>[];
      for (final LanEndpoint? e in <LanEndpoint?>[_manual, _last]) {
        if (e != null && !tried.contains(e)) {
          tried.add(e);
          if (await _tryEndpoint(creds, e)) return;
        }
      }
      final LanBeacon? beacon = await _discover(creds);
      if (beacon != null &&
          !tried.contains(beacon.endpoint) &&
          await _tryEndpoint(creds, beacon.endpoint)) {
        return;
      }
      if (beacon != null) tried.add(beacon.endpoint);
      final LanEndpoint loop = LanEndpoint('127.0.0.1', creds.port);
      if (tryLoopback &&
          !tried.contains(loop) &&
          await _tryEndpoint(creds, loop, quiet: true)) {
        return;
      }
      _error ??= beacon == null
          ? "Markaz kassa topilmadi: kassa ilovasi ochiq va shu Wi-Fi'da ekanini tekshiring (yoki manzilni qo'lda kiriting)."
          : "Markaz kassaga ulanib bo'lmadi.";
      _failed();
    } finally {
      _connecting = false;
      _recompute();
    }
  }

  Future<LanBeacon?> _discover(LanCredentials creds) async {
    final LanMulticastLock? lock = multicast;
    try {
      await lock?.acquire();
    } on Object {
      // No lock (not Android, no permission): listen anyway
    }
    try {
      return await _listen(creds, timeout: listenFor);
    } on Object {
      return null;
    } finally {
      try {
        await lock?.release();
      } on Object {
        // Already released
      }
    }
  }

  Future<bool> _tryEndpoint(
    LanCredentials creds,
    LanEndpoint endpoint, {
    bool quiet = false,
  }) async {
    if (_disposed || _credentials != creds) return false;
    final LanClient c = _clientFactory(creds, app);
    try {
      await c.connect(endpoint);
    } on LanUnreachable catch (e) {
      await c.dispose();
      if (!quiet) {
        _error = _describe(e);
        _reason = e.reason;
      }
      return false;
    }
    if (_disposed || _credentials != creds) {
      await c.dispose();
      return false;
    }
    await _drop();
    _client = c;
    _last = endpoint;
    _error = null;
    _reason = null;
    _failures = 0;
    _nextAttempt = null;
    _clientSubs
      ..add(
        c.events.listen((LanEvent e) {
          if (!_events.isClosed) _events.add(e);
          if (e.type == LanMsg.lanState || e.type == LanMsg.state) {
            _recompute();
          }
        }),
      )
      ..add(
        c.connection.listen((bool up) {
          if (!up) {
            _error = "Markaz kassa bilan aloqa uzildi.";
            _failed();
          }
          _recompute();
        }),
      );
    return true;
  }

  void _failed() {
    final Duration wait = retry.isEmpty
        ? const Duration(seconds: 5)
        : retry[_failures < retry.length ? _failures : retry.length - 1];
    _failures++;
    _nextAttempt = _clock().add(wait);
  }

  static String _describe(LanUnreachable e) => switch (e.reason) {
    'unknown_device' => "Markaz bu qurilmani tanimadi: kassada internet bilan ilovani qayta oching (qurilmalar ro'yxati yangilanadi).",
    'key_mismatch' =>
      "Lokal tarmoq kaliti yangilangan: internet bilan qayta kiring.",
    'bad_sig' => "Markaz kassa imzoni rad etdi.",
    'no_hub' => "Filialda markaz kassa tanlanmagan.",
    _ => e.message ?? "Markaz kassaga ulanib bo'lmadi.",
  };

  void _recompute() {
    if (_disposed) return;
    final LanClient? c = _client;
    final LanMode next = LanModeRule.next(
      current: _mode,
      cloudReachable: _cloud,
      hubReachable: available && connected,
      hubDrained: c?.hubDrained ?? false,
    );
    final bool changed = next != _mode;
    _mode = next;
    if (changed) {
      if (!_modes.isClosed) _modes.add(next);
      // Back on the server: the LAN goes idle
      if (next == LanMode.cloud) unawaited(_drop());
    }
    _emit();
  }

  Future<void> _drop() async {
    for (final StreamSubscription<Object?> s in _clientSubs) {
      await s.cancel();
    }
    _clientSubs.clear();
    final LanClient? c = _client;
    _client = null;
    await c?.dispose();
  }

  void _emit() {
    if (!_changes.isClosed) _changes.add(null);
  }

  Future<void> dispose() async {
    _disposed = true;
    _ticker?.cancel();
    _ticker = null;
    await _drop();
    await _changes.close();
    await _events.close();
    await _modes.close();
  }
}
