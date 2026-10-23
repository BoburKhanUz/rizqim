import 'dart:async';

import 'package:rizqim_api/rizqim_api.dart';

import 'pos_events.dart';

/// Real-time holati (yuqori paneldagi belgi uchun).
enum PosLiveState {
  /// Real-time sozlanmagan (kalit yo'q) yoki to'xtatilgan: faqat polling.
  off,

  /// Ulanmoqda yoki ulangan, obuna hali tasdiqlanmagan.
  connecting,

  /// Ulangan va kanal obunasi tasdiqlangan: hodisalar keladi.
  live,

  /// Ulangan, lekin kanal imzosi rad etildi (403/429) — fonda qayta so'ralmoqda; polling ishlaydi.
  subscriptionFailed,

  /// Ulanish uzildi, qayta ulanmoqda.
  reconnecting,
}

/// Filialning POS kanali `private-pos-branch.{branchId}` — **terminal tokeni** bilan imzolanadi (PIN bilan hech kim
/// kirmagan bo'lsa ham ishlaydi). KDS'dagi `BranchRealtime` ning Flutter'siz nusxasi (`pos/apps/kds` ga tegilmagan).
///
/// * [events] — shu kanal hodisalari, tiplangan ([PosEvent]).
/// * [states] / [state] — ulanish va obuna holati ([PosLiveState]).
/// * [resubscribed] — obuna tasdiqlandi (birinchi marta yoki qayta ulangandan keyin): uzilish paytidagi hodisalar
///   yo'qolgan, ilova ro'yxatlarni **to'liq qayta yuklaydi**.
/// * Qayta ulanish oralig'i va ulanish muddati [PusherClient] da (1, 2, 4 ... 30 s, hech qachon taslim bo'lmaydi).
class PosRealtime {
  PosRealtime({required PusherClient client, required this.branchId})
    : _client = client {
    _statusSub = client.statuses.listen((_) => _update());
    _channelSub = client.channelStates.listen((RealtimeChannelState s) {
      if (s.channel != channel) return;
      _update();
      if (s.state == RealtimeSubscriptionState.subscribed &&
          !_resubscribed.isClosed) {
        _resubscribed.add(null);
      }
    });
    _eventSub = client.events.listen((RealtimeEvent e) {
      if (e.channel != channel || _events.isClosed) return;
      _events.add(PosEvent.of(e));
    });
  }

  /// Ilova sozlamalaridan: kalit bo'sh yoki terminal filialsiz bo'lsa `null` (faqat polling).
  static PosRealtime? create({
    required RizqimClient client,
    required PairedTerminal terminal,
    required String apiBaseUrl,
    required String appKey,
    String reverbUrl = '',
    String origin = '',
    String clientName = 'rizqim-pos',
    RealtimeConnector connector = ioRealtimeConnector,
  }) {
    final int? branchId = terminal.branch?.id ?? terminal.device.branchId;
    if (appKey.isEmpty || branchId == null) return null;
    return PosRealtime(
      branchId: branchId,
      client: PusherClient(
        uri: realtimeUri(
          apiBaseUrl: apiBaseUrl,
          appKey: appKey,
          override: reverbUrl,
          clientName: clientName,
        ),
        headers: <String, String>{
          'Origin': origin.isNotEmpty ? origin : originOf(apiBaseUrl),
        },
        connector: connector,
        authorize: client.channelAuth.terminal,
      ),
    );
  }

  static String channelFor(int branchId) => 'private-pos-branch.$branchId';

  final int branchId;
  final PusherClient _client;
  late final StreamSubscription<RealtimeStatus> _statusSub;
  late final StreamSubscription<RealtimeChannelState> _channelSub;
  late final StreamSubscription<RealtimeEvent> _eventSub;
  final StreamController<PosEvent> _events =
      StreamController<PosEvent>.broadcast();
  final StreamController<PosLiveState> _states =
      StreamController<PosLiveState>.broadcast();
  final StreamController<void> _resubscribed =
      StreamController<void>.broadcast();
  PosLiveState _state = PosLiveState.off;
  bool _started = false;

  String get channel => channelFor(branchId);

  Stream<PosEvent> get events => _events.stream;
  Stream<PosLiveState> get states => _states.stream;
  Stream<void> get resubscribed => _resubscribed.stream;

  PosLiveState get state => _state;
  bool get isLive => _state == PosLiveState.live;

  RealtimeStatus get status => _client.status;
  RealtimeSubscriptionState get subscription =>
      _client.channelState(channel) ?? RealtimeSubscriptionState.pending;

  void start() {
    if (_started) return;
    _started = true;
    _client
      ..subscribe(channel)
      ..connect();
    _update();
  }

  Future<void> dispose() async {
    final Future<void> closing = _client.dispose();
    await _statusSub.cancel();
    await _channelSub.cancel();
    await _eventSub.cancel();
    await closing;
    await _events.close();
    await _states.close();
    await _resubscribed.close();
  }

  void _update() {
    final PosLiveState next = switch (_client.status) {
      RealtimeStatus.idle =>
        _started ? PosLiveState.reconnecting : PosLiveState.off,
      RealtimeStatus.connecting => PosLiveState.connecting,
      RealtimeStatus.reconnecting => PosLiveState.reconnecting,
      RealtimeStatus.connected => switch (subscription) {
        RealtimeSubscriptionState.subscribed => PosLiveState.live,
        RealtimeSubscriptionState.failed => PosLiveState.subscriptionFailed,
        RealtimeSubscriptionState.pending => PosLiveState.connecting,
      },
    };
    if (next == _state) return;
    _state = next;
    if (!_states.isClosed) _states.add(next);
  }
}

/// Nima uchun qayta yuklanmoqda.
enum PosRefreshReason {
  /// Real-time jonli emas: navbatdagi so'rov.
  poll,

  /// Jonli bo'lsa ham ehtiyot uchun (hodisa yo'qolishi mumkin).
  safetyPoll,

  /// Obuna (qayta) tasdiqlandi: uzilish paytidagi hodisalar yo'qolgan.
  resync,

  /// Tegishli hodisa keldi.
  event,

  /// Ilova so'radi ([PosRefreshScheduler.refreshNow]).
  manual,
}

/// Polling zaxirasi: real-time bo'lmasa yoki jonli bo'lmasa har [pollInterval], jonli bo'lsa ham har
/// [livePollInterval] da, obuna qayta tasdiqlanganda darhol va [eventFilter] ga mos hodisada ([debounce] bilan)
/// [onRefresh] ni chaqiradi. Bir vaqtda bitta yuklash (davomida yana so'ralsa — tugagach bir marta qayta).
///
/// ```dart
/// final scheduler = PosRefreshScheduler(
///   realtime: realtime,
///   eventFilter: (e) => e is PosOrderEvent || e is PosOrderItemsChanged,
///   onRefresh: (_) => floorController.reload(),
/// )..start();
/// ```
class PosRefreshScheduler {
  PosRefreshScheduler({
    required this.onRefresh,
    this.realtime,
    this.eventFilter,
    this.pollInterval = const Duration(seconds: 15),
    this.livePollInterval = const Duration(seconds: 60),
    this.debounce = const Duration(milliseconds: 300),
  });

  final PosRealtime? realtime;
  final Future<void> Function(PosRefreshReason reason) onRefresh;

  /// Qaysi hodisa yuklashni boshlaydi (`null` — hech qaysi: faqat polling va resync).
  final bool Function(PosEvent event)? eventFilter;
  final Duration pollInterval;

  /// `Duration.zero` — jonli paytda polling yo'q.
  final Duration livePollInterval;
  final Duration debounce;

  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];
  Timer? _poll;
  Timer? _debounce;
  bool _running = false;
  PosRefreshReason? _pending;
  bool _started = false;
  bool _disposed = false;

  /// Necha marta yuklandi (testlar).
  int refreshes = 0;

  void start() {
    if (_started) return;
    _started = true;
    final PosRealtime? rt = realtime;
    if (rt != null) {
      _subs
        ..add(rt.states.listen((_) => _arm()))
        ..add(rt.resubscribed.listen((_) => _trigger(PosRefreshReason.resync)))
        ..add(
          rt.events.listen((PosEvent e) {
            final bool Function(PosEvent)? filter = eventFilter;
            if (filter == null || !filter(e)) return;
            _debounce?.cancel();
            _debounce = Timer(debounce, () => _trigger(PosRefreshReason.event));
          }),
        );
    }
    _arm();
  }

  /// Hozir yuklash (masalan, ekran ochilganda yoki "Yangilash" bosilganda).
  Future<void> refreshNow() => _trigger(PosRefreshReason.manual);

  void _arm() {
    _poll?.cancel();
    if (_disposed) return;
    final bool live = realtime?.isLive ?? false;
    final Duration every = live ? livePollInterval : pollInterval;
    if (every <= Duration.zero) return;
    _poll = Timer.periodic(
      every,
      (_) =>
          _trigger(live ? PosRefreshReason.safetyPoll : PosRefreshReason.poll),
    );
  }

  Future<void> _trigger(PosRefreshReason reason) async {
    if (_disposed) return;
    if (_running) {
      _pending = reason;
      return;
    }
    _running = true;
    try {
      var next = reason;
      while (true) {
        refreshes++;
        try {
          await onRefresh(next);
        } on Object {
          // Yuklash xatosi chaqiruvchida ko'rsatiladi; jadval to'xtamaydi
        }
        final PosRefreshReason? again = _pending;
        _pending = null;
        if (again == null || _disposed) break;
        next = again;
      }
    } finally {
      _running = false;
    }
  }

  Future<void> dispose() async {
    _disposed = true;
    _poll?.cancel();
    _debounce?.cancel();
    for (final StreamSubscription<Object?> s in _subs) {
      await s.cancel();
    }
    _subs.clear();
  }
}
