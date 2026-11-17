import 'dart:async';

import 'pusher_client.dart';
import 'realtime_config.dart';

/// The heartbeat event the server sends on `pos-branch.*`, `branch.*` and `store.*` (docs/API.md 8, "M2").
const String heartbeatEvent = 'realtime.heartbeat';

/// One private channel on a [PusherClient] with what wave M2 adds around it (Flutter-free; the POS apps' `PosRealtime`
/// and the KDS `BranchRealtime` are thin views of it):
///
/// * the server's `GET /v1/realtime` ([loadConfig]) at start and on every reconnect: `live: false` and silence make
///   [degraded] true (poll at the fallback interval); a build without a compiled Reverb key ([waitForConfig]) connects
///   with the key, host, port and scheme the server names ([uriFor]);
/// * [heartbeats] (`realtime.heartbeat`) apart from [events], so a screen never re-reads just because the server is
///   alive;
/// * silence: no event and no heartbeat for `silence_seconds` while subscribed marks the channel [degraded] until the
///   next heartbeat or event.
class LiveChannel {
  LiveChannel({
    required this.client,
    required this.channel,
    this.loadConfig,
    this.uriFor,
    this.waitForConfig = false,
    this.configRetry = const Duration(seconds: 60),
    this.degradedRecheck = const Duration(minutes: 5),
    this.reconfigureGap = const Duration(seconds: 10),
  }) {
    _subs
      ..add(
        client.statuses.listen((RealtimeStatus s) {
          if (s != RealtimeStatus.connected) _stopSilence();
          if (s == RealtimeStatus.reconnecting) unawaited(_configure());
          _changed();
        }),
      )
      ..add(
        client.channelStates.listen((RealtimeChannelState s) {
          if (s.channel != channel) return;
          if (s.state == RealtimeSubscriptionState.subscribed) {
            _silent = false;
            _armSilence();
          } else {
            _stopSilence();
          }
          _changed();
          if (s.state == RealtimeSubscriptionState.subscribed &&
              !_resubscribed.isClosed) {
            _resubscribed.add(null);
          }
        }),
      )
      ..add(
        client.events.listen((RealtimeEvent e) {
          if (e.channel != channel) return;
          final bool heartbeat = e.event == heartbeatEvent;
          if (heartbeat) _serverLive = true;
          _signal();
          if (heartbeat) {
            if (!_heartbeats.isClosed) _heartbeats.add(e);
          } else if (!_events.isClosed) {
            _events.add(e);
          }
        }),
      );
  }

  final PusherClient client;

  /// `private-pos-branch.5`.
  final String channel;

  /// `GET /v1/realtime` (`RealtimeConfig.fetch(client.dio)`); `null` — never asked (old behaviour: live when
  /// subscribed, no silence detection).
  final Future<RealtimeConfig?> Function()? loadConfig;

  /// The socket address for a config (`null`: keep [PusherClient.uri]).
  final Uri? Function(RealtimeConfig? config)? uriFor;

  /// No key compiled in: connect only once [uriFor] gives an address from the server's config.
  final bool waitForConfig;

  /// Without an address (the server unreachable at start, or real-time off there): ask again this often.
  final Duration configRetry;

  /// While the server says `live: false`: ask again this often (it may come back without a reconnect).
  final Duration degradedRecheck;

  /// At most one config read per this period (a flapping connection does not hammer the endpoint).
  final Duration reconfigureGap;

  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];
  final StreamController<RealtimeEvent> _events =
      StreamController<RealtimeEvent>.broadcast();
  final StreamController<RealtimeEvent> _heartbeats =
      StreamController<RealtimeEvent>.broadcast();
  final StreamController<void> _changes = StreamController<void>.broadcast();
  final StreamController<void> _resubscribed =
      StreamController<void>.broadcast();

  RealtimeConfig? _config;
  bool _serverLive = true;
  bool _silent = false;
  bool _started = false;
  bool _connected = false;
  bool _disposed = false;
  Timer? _silenceTimer;
  Timer? _configTimer;
  Timer? _gapTimer;
  Future<void>? _configuring;

  /// Events on [channel], heartbeats excluded.
  Stream<RealtimeEvent> get events => _events.stream;

  /// `realtime.heartbeat` on [channel].
  Stream<RealtimeEvent> get heartbeats => _heartbeats.stream;

  /// Connection, subscription or [degraded] changed.
  Stream<void> get changes => _changes.stream;

  /// The subscription was confirmed (first time or after a reconnect): events in between were lost, re-read.
  Stream<void> get resubscribed => _resubscribed.stream;

  RealtimeStatus get status => client.status;

  RealtimeSubscriptionState get subscription =>
      client.channelState(channel) ?? RealtimeSubscriptionState.pending;

  /// Connected and the subscription confirmed: events arrive.
  bool get subscribed =>
      client.status == RealtimeStatus.connected &&
      subscription == RealtimeSubscriptionState.subscribed;

  /// Subscribed, but the server says real-time is not live, or the channel has been silent for `silence_seconds`.
  bool get degraded => subscribed && (!_serverLive || _silent);

  /// Events can be trusted to arrive: subscribed and not [degraded]. Screens poll at the long interval only then.
  bool get isLive => subscribed && !degraded;

  /// Silent for `silence_seconds` (no event, no heartbeat).
  bool get silent => _silent;

  /// The server said `live: false`.
  bool get serverNotLive => !_serverLive;

  RealtimeConfig? get config => _config;

  /// No compiled key and none from the server yet: not connecting.
  bool get waitingForKey => waitForConfig && !_connected;

  void start() {
    if (_started || _disposed) return;
    _started = true;
    client.subscribe(channel);
    if (!waitForConfig) {
      _connected = true;
      client.connect();
    }
    unawaited(_configure(force: true));
  }

  Future<void> _configure({bool force = false}) {
    final Future<void>? running = _configuring;
    if (running != null) return running;
    if (!force && (_gapTimer?.isActive ?? false)) return Future<void>.value();
    _gapTimer?.cancel();
    _gapTimer = Timer(reconfigureGap, () {});
    return _configuring = _readConfig().whenComplete(() => _configuring = null);
  }

  Future<void> _readConfig() async {
    final Future<RealtimeConfig?> Function()? load = loadConfig;
    RealtimeConfig? cfg;
    if (load != null) {
      try {
        cfg = await load();
      } on Object {
        cfg = null;
      }
    }
    if (_disposed) return;
    if (cfg != null) {
      _config = cfg;
      _serverLive = cfg.live;
    }
    final Uri? uri = uriFor?.call(_config);
    if (uri != null && uri != client.uri) client.uri = uri;
    _configTimer?.cancel();
    if (!_connected) {
      if (uri != null) {
        _connected = true;
        client.connect();
      } else {
        _configTimer = Timer(
          configRetry,
          () => unawaited(_configure(force: true)),
        );
      }
    } else if (!_serverLive) {
      _configTimer = Timer(
        degradedRecheck,
        () => unawaited(_configure(force: true)),
      );
    }
    if (subscribed) _armSilence();
    _changed();
  }

  void _signal() {
    _silent = false;
    _armSilence();
    _changed();
  }

  void _armSilence() {
    _silenceTimer?.cancel();
    _silenceTimer = null;
    final int seconds = _config?.silenceSeconds ?? 0;
    if (_disposed || seconds <= 0 || !subscribed) return;
    _silenceTimer = Timer(Duration(seconds: seconds), () {
      _silenceTimer = null;
      if (!subscribed) return;
      _silent = true;
      _changed();
    });
  }

  void _stopSilence() {
    _silenceTimer?.cancel();
    _silenceTimer = null;
    _silent = false;
  }

  void _changed() {
    if (!_changes.isClosed) _changes.add(null);
  }

  Future<void> dispose() async {
    _disposed = true;
    _silenceTimer?.cancel();
    _configTimer?.cancel();
    _gapTimer?.cancel();
    final Future<void> closing = client.dispose();
    for (final StreamSubscription<Object?> s in _subs) {
      await s.cancel();
    }
    await closing;
    await _events.close();
    await _heartbeats.close();
    await _changes.close();
    await _resubscribed.close();
  }
}
