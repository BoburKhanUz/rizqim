import 'dart:async';

import 'package:rizqim_api/rizqim_api.dart';

import 'pos_events.dart';

/// Real-time holati (yuqori paneldagi belgi uchun).
enum PosLiveState {
  /// Real-time sozlanmagan (kalit yo'q: na ilovada, na serverda) yoki to'xtatilgan: faqat polling.
  off,

  /// Ulanmoqda yoki ulangan, obuna hali tasdiqlanmagan.
  connecting,

  /// Ulangan va kanal obunasi tasdiqlangan: hodisalar keladi. M2: [PosRealtime.degraded] bo'lsa (server `live: false`
  /// dedi yoki kanal `silence_seconds` jim) ekranlar baribir tez so'raydi ([PosRealtime.isLive] `false`).
  live,

  /// Ulangan, lekin kanal imzosi rad etildi (403/429) — fonda qayta so'ralmoqda; polling ishlaydi.
  subscriptionFailed,

  /// Ulanish uzildi, qayta ulanmoqda.
  reconnecting,
}

/// M2: `realtime.heartbeat` (har 30 s): kanal tokeni [data] ning `rev` ida. Ekranlarga hodisa sifatida berilmaydi
/// ([PosRealtime.events] da yo'q), faqat [PosRefreshScheduler] solishtiradi ([PosRealtime.heartbeats]).
class PosHeartbeat {
  const PosHeartbeat(this.data);

  final Map<String, Object?> data;
}

/// Filialning POS kanali `private-pos-branch.{branchId}` — **terminal tokeni** bilan imzolanadi (PIN bilan hech kim
/// kirmagan bo'lsa ham ishlaydi). Kanalning o'zi [LiveChannel] da (KDS'dagi `BranchRealtime` bilan bir xil yadro).
///
/// * [events] — shu kanal hodisalari, tiplangan ([PosEvent]); heartbeat ular orasida yo'q ([heartbeats]).
/// * [states] / [state] — ulanish va obuna holati ([PosLiveState]); [degraded] o'zgarganda ham chiqadi.
/// * [resubscribed] — obuna tasdiqlandi (birinchi marta yoki qayta ulangandan keyin): uzilish paytidagi hodisalar
///   yo'qolgan, ilova ro'yxatlarni **to'liq qayta yuklaydi**.
/// * M2: ishga tushganda va har qayta ulanishda `GET /v1/realtime` o'qiladi: ilovada kalit bo'lmasa serverning kaliti,
///   xosti, porti bilan ulanadi; `live: false` yoki kanal jimligi — [degraded] (ekranlar tez so'raydi).
/// * Qayta ulanish oralig'i [Backoff.reconnect]: birinchisi 0-10 s ichida tasodifiy, keyin 2, 4 ... 30 s.
class PosRealtime {
  PosRealtime({
    required PusherClient client,
    required this.branchId,
    Future<RealtimeConfig?> Function()? loadConfig,
    Uri? Function(RealtimeConfig? config)? uriFor,
    bool waitForConfig = false,
  }) : _link = LiveChannel(
         client: client,
         channel: channelFor(branchId),
         loadConfig: loadConfig,
         uriFor: uriFor,
         waitForConfig: waitForConfig,
       ) {
    _changeSub = _link.changes.listen((_) => _update());
    _eventSub = _link.events.listen((RealtimeEvent e) {
      if (!_events.isClosed) _events.add(PosEvent.of(e));
    });
    _heartbeatSub = _link.heartbeats.listen((RealtimeEvent e) {
      if (!_heartbeats.isClosed) _heartbeats.add(PosHeartbeat(e.data));
    });
    _resubSub = _link.resubscribed.listen((_) {
      if (!_resubscribed.isClosed) _resubscribed.add(null);
    });
  }

  /// Ilova sozlamalaridan; terminal filialsiz bo'lsa `null` (faqat polling). [appKey] bo'sh bo'lsa (M2: kalit ilovaga
  /// yozilmagan) kalit, xost va port serverning `GET /v1/realtime` javobidan olinadi; u ham bermasa — [PosLiveState.off]
  /// (polling), keyinroq yana so'raladi.
  static PosRealtime? create({
    required RizqimClient client,
    required PairedTerminal terminal,
    required String apiBaseUrl,
    required String appKey,
    String reverbUrl = '',
    String origin = '',
    String clientName = 'rizqim-pos',
    RealtimeConnector connector = ioRealtimeConnector,
    Backoff backoff = Backoff.reconnect,
    Future<RealtimeConfig?> Function()? loadConfig,
  }) {
    final int? branchId = terminal.branch?.id ?? terminal.device.branchId;
    if (branchId == null) return null;
    Uri? uriFor(RealtimeConfig? config) => RealtimeConfig.socketUri(
      apiBaseUrl: apiBaseUrl,
      appKey: appKey,
      config: config,
      override: reverbUrl,
      clientName: clientName,
    );
    return PosRealtime(
      branchId: branchId,
      loadConfig: loadConfig ?? () => RealtimeConfig.fetch(client.dio),
      uriFor: uriFor,
      waitForConfig: appKey.isEmpty,
      client: PusherClient(
        uri:
            uriFor(null) ??
            realtimeUri(
              apiBaseUrl: apiBaseUrl,
              appKey: '',
              override: reverbUrl,
              clientName: clientName,
            ),
        headers: <String, String>{
          'Origin': origin.isNotEmpty ? origin : originOf(apiBaseUrl),
        },
        connector: connector,
        backoff: backoff,
        authorize: client.channelAuth.terminal,
      ),
    );
  }

  static String channelFor(int branchId) => 'private-pos-branch.$branchId';

  final int branchId;
  final LiveChannel _link;
  late final StreamSubscription<void> _changeSub;
  late final StreamSubscription<RealtimeEvent> _eventSub;
  late final StreamSubscription<RealtimeEvent> _heartbeatSub;
  late final StreamSubscription<void> _resubSub;
  final StreamController<PosEvent> _events =
      StreamController<PosEvent>.broadcast();
  final StreamController<PosHeartbeat> _heartbeats =
      StreamController<PosHeartbeat>.broadcast();
  final StreamController<PosLiveState> _states =
      StreamController<PosLiveState>.broadcast();
  final StreamController<void> _resubscribed =
      StreamController<void>.broadcast();
  PosLiveState _state = PosLiveState.off;
  bool _degraded = false;
  bool _started = false;

  String get channel => channelFor(branchId);

  Stream<PosEvent> get events => _events.stream;

  /// M2: `realtime.heartbeat` on the channel.
  Stream<PosHeartbeat> get heartbeats => _heartbeats.stream;
  Stream<PosLiveState> get states => _states.stream;
  Stream<void> get resubscribed => _resubscribed.stream;

  PosLiveState get state => _state;

  /// Events can be trusted: subscribed and not [degraded]. Screens poll at their long (safety) interval only then.
  bool get isLive => _state == PosLiveState.live && !_degraded;

  /// M2: subscribed, but the server says real-time is not live (`GET /v1/realtime`) or the channel has been silent for
  /// `silence_seconds`: screens poll at the fast interval until a heartbeat or an event comes back.
  bool get degraded => _degraded;

  /// The underlying channel (config, silence; tests and diagnostics).
  LiveChannel get link => _link;

  RealtimeStatus get status => _link.status;
  RealtimeSubscriptionState get subscription => _link.subscription;

  void start() {
    if (_started) return;
    _started = true;
    _link.start();
    _update();
  }

  Future<void> dispose() async {
    final Future<void> closing = _link.dispose();
    await _changeSub.cancel();
    await _eventSub.cancel();
    await _heartbeatSub.cancel();
    await _resubSub.cancel();
    await closing;
    await _events.close();
    await _heartbeats.close();
    await _states.close();
    await _resubscribed.close();
  }

  void _update() {
    final PosLiveState next = _link.waitingForKey
        ? PosLiveState.off
        : switch (_link.status) {
            RealtimeStatus.idle =>
              _started ? PosLiveState.reconnecting : PosLiveState.off,
            RealtimeStatus.connecting => PosLiveState.connecting,
            RealtimeStatus.reconnecting => PosLiveState.reconnecting,
            RealtimeStatus.connected => switch (_link.subscription) {
              RealtimeSubscriptionState.subscribed => PosLiveState.live,
              RealtimeSubscriptionState.failed =>
                PosLiveState.subscriptionFailed,
              RealtimeSubscriptionState.pending => PosLiveState.connecting,
            },
          };
    final bool degraded = _link.degraded;
    if (next == _state && degraded == _degraded) return;
    _state = next;
    _degraded = degraded;
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

  /// M2: heartbeat'dagi `rev` ekran bilgan tokendan farq qiladi (hodisa o'tkazib yuborilgan).
  heartbeat,

  /// M2: ilova oldingi planga qaytdi ([AppResume]).
  resume,
}

/// Wave M2 fix round 2: the floors' event table (cashier floor, waiter rooms), by event name ([ScreenRelevance.floor]
/// keeps the table: each event, where the backend dispatches it, and why each omission is safe).
abstract final class PosRelevance {
  /// `order.*` (a bill change only when it is not `payment` or `fiscal`), `kitchen.item_status_changed`,
  /// `kitchen.order_assembled`, `booking.changed`, `table.state_changed`, and names this build does not know.
  static bool floor(PosEvent e) => ScreenRelevance.floor(e.name, e.data);

  /// The till's pickup board: `queue.*` and `order.created` / `status_changed` / `assigned`.
  static bool pickup(PosEvent e) => ScreenRelevance.pickup(e.name);
}

/// Polling zaxirasi: real-time bo'lmasa yoki jonli bo'lmasa har [pollInterval], jonli bo'lsa ham har
/// [livePollInterval] da, obuna qayta tasdiqlanganda darhol va [eventFilter] ga mos hodisada ([pacing] bilan)
/// [onRefresh] ni chaqiradi. Bir vaqtda bitta yuklash (davomida yana so'ralsa — tugagach bir marta qayta).
///
/// M2 (docs/API.md 8, "M2: `rev`, heartbeat, holat"):
/// * Har yuklash [ReadTracking.capture] ichida: uning GET'laridan (bitta doirali javobning `X-Revision`) ekran qaysi
///   tokenni ko'rsatayotgani isbotlansa, u R bo'ladi ([ChannelRevision]). Isbotlanmasa (o'qish xatosi, do'kon bo'ylab
///   ro'yxat, aralash o'qishlar) — R noma'lum.
/// * Hodisalar va heartbeat [PacedRefresh] orqali: HAR hodisa (mos yoki mos emas) S ni yuritadi; mos hodisa `rev == R`
///   bo'lsa yoki xuddi shu `rev` uchun o'qish kutilayotgan bo'lsa — hech narsa, aks holda [pacing] bilan o'qish
///   (birinchisi U(0, spread) dan keyin, oyna ichidagilar oyna oxirida bitta o'qishga yig'iladi). Heartbeat: `rev != S`
///   yoki shubha bor — [pacing] bilan o'qish ([PosRefreshReason.heartbeat]). Lokal ko'prik hodisalari ([localEvents])
///   `rev` siz: mos bo'lsa har doim (pacing bilan) yuklaydi.
/// * Pacing faqat hodisa va heartbeat o'qishlariga: [refreshNow] (xodimning o'z amali), resync, polling (ehtiyot
///   so'rovi ham), jimlik va ilova qaytishi darhol o'qiydi.
/// * Polling so'rovlari shartli (`If-None-Match`, `304`), lekin har [unconditionalEvery] da kamida bir marta
///   `If-None-Match` siz (serverda yo'qolgan "touch" ekranni eskirgan holda ushlab turolmaydi).
/// * Ilova oldingi planga qaytganda ([AppResume]) darhol qayta o'qiydi.
///
/// ```dart
/// final scheduler = PosRefreshScheduler(
///   realtime: realtime,
///   eventFilter: PosRelevance.floor,
///   pacing: RefreshPacing.floor,
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
    this.pacing = RefreshPacing.list,
    this.unconditionalEvery = const Duration(seconds: 110),
    this.localEvents,
    Stream<void>? resumed,
  }) : _resumedSource = resumed ?? AppResume.resumed;

  final PosRealtime? realtime;

  /// P6 lokal ko'prik: markaz kassa hodisalari (`PosData.lanEvents`) — [eventFilter] bilan xuddi server hodisalaridek.
  final Stream<PosEvent>? localEvents;
  final Future<void> Function(PosRefreshReason reason) onRefresh;

  /// Qaysi hodisa yuklashni boshlaydi (`null` — hech qaysi: faqat polling va resync).
  final bool Function(PosEvent event)? eventFilter;
  final Duration pollInterval;

  /// `Duration.zero` — jonli paytda polling yo'q.
  final Duration livePollInterval;

  /// M2 fix round 2: how event and heartbeat reads are paced ([RefreshPacing]).
  final RefreshPacing pacing;

  /// M2: polling so'rovlari shu vaqtda kamida bir marta `If-None-Match` siz yuboriladi (2 daqiqadan kam).
  final Duration unconditionalEvery;
  final Stream<void> _resumedSource;

  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];
  Timer? _poll;
  bool _running = false;
  PosRefreshReason? _pending;
  bool _started = false;
  bool _disposed = false;
  PacedRefresh? _paced;

  /// Polling vaqti oxirgi shartsiz o'qishdan beri (taymer bo'yicha sanaladi, soatga bog'liq emas).
  Duration _sinceUnconditional = Duration.zero;
  bool _nextUnconditional = false;

  /// Necha marta yuklandi (testlar).
  int refreshes = 0;

  /// Mos, lekin o'qilmagan hodisalar: `rev == R` yoki shu `rev` uchun o'qish allaqachon so'ralgan (testlar).
  int skippedEvents = 0;

  /// `If-None-Match` siz yuborilgan polling yuklashlari (testlar).
  int unconditionalRefreshes = 0;

  /// M2: this screen's revision state on the realtime channel (`null` without realtime).
  ChannelRevision? get revision => _paced?.revision;

  /// M2 fix round 2: the paced reads (tests).
  PacedRefresh? get paced => _paced;

  bool _relevant(PosEvent e) => eventFilter?.call(e) ?? false;

  void _onEvent(PosEvent e) {
    final PacedRefresh? p = _paced;
    if (p == null) return;
    if (p.event(e.data, relevant: _relevant(e)) == PacedVerdict.skipped) {
      skippedEvents++;
    }
  }

  void start() {
    if (_started) return;
    _started = true;
    final PosRealtime? rt = realtime;
    _paced = PacedRefresh(
      channel: rt?.channel,
      pacing: pacing,
      onRead: (PacedReason r) => unawaited(
        _trigger(
          r == PacedReason.heartbeat
              ? PosRefreshReason.heartbeat
              : PosRefreshReason.event,
        ),
      ),
    );
    if (rt != null) {
      _subs
        ..add(rt.states.listen((_) => _arm()))
        ..add(rt.resubscribed.listen((_) => _trigger(PosRefreshReason.resync)))
        ..add(rt.events.listen(_onEvent))
        ..add(
          rt.heartbeats.listen((PosHeartbeat h) => _paced?.heartbeat(h.data)),
        );
    }
    final Stream<PosEvent>? local = localEvents;
    if (local != null) {
      // The hub's events carry no `rev` of the server channel: a relevant one always reads (paced)
      _subs.add(local.listen(_onEvent));
    }
    _subs.add(
      _resumedSource.listen(
        (_) => unawaited(_trigger(PosRefreshReason.resume)),
      ),
    );
    _arm();
  }

  /// Hozir yuklash (masalan, ekran ochilganda, "Yangilash" bosilganda yoki xodimning o'z amalidan keyin): pacing yo'q.
  Future<void> refreshNow() => _trigger(PosRefreshReason.manual);

  void _arm() {
    _poll?.cancel();
    if (_disposed) return;
    final bool live = realtime?.isLive ?? false;
    final Duration every = live ? livePollInterval : pollInterval;
    if (every <= Duration.zero) return;
    _poll = Timer.periodic(every, (_) {
      _sinceUnconditional += every;
      if (_sinceUnconditional >= unconditionalEvery) {
        _sinceUnconditional = Duration.zero;
        _nextUnconditional = true;
      }
      unawaited(
        _trigger(live ? PosRefreshReason.safetyPoll : PosRefreshReason.poll),
      );
    });
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
        final bool unconditional =
            _nextUnconditional &&
            (next == PosRefreshReason.poll ||
                next == PosRefreshReason.safetyPoll);
        if (unconditional) {
          _nextUnconditional = false;
          unconditionalRefreshes++;
        }
        final PacedRefresh? paced = _paced;
        // Every full read shows what was asked so far: a paced read still due for it is dropped when covered
        paced?.readStarted();
        try {
          final CapturedRead<void> captured = await ReadTracking.capture<void>(
            () => onRefresh(next),
            unconditional: unconditional,
          );
          final String? scope = paced?.revision?.scope;
          paced?.readDone(
            scope == null ? null : captured.reads.revisionFor(scope),
          );
        } on Object {
          // Yuklash xatosi chaqiruvchida ko'rsatiladi; jadval to'xtamaydi. Ekran nimani ko'rsatayotgani noma'lum
          paced?.readFailed();
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
    _paced?.dispose();
    for (final StreamSubscription<Object?> s in _subs) {
      await s.cancel();
    }
    _subs.clear();
  }
}
