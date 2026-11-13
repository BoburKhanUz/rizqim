import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_lan/pos_lan.dart';

import '../app_config.dart';
import '../lan/display_lan.dart';
import '../settings/display_settings.dart';
import '../sound/ready_sound.dart';

/// Ekranda hozir nima ko'rinadi.
enum DisplayView {
  /// Birinchi javob hali kelmagan.
  loading,

  /// Chek yo'q: salomlashish.
  welcome,

  /// Joriy chek, to'lov, "Rahmat".
  cart,

  /// "Tayyorlanmoqda / Tayyor" raqamlar.
  board,

  /// Filial formatida bu ekran uchun modul yoqilmagan (yoki tanlangan rejim uchun).
  notEnabled,
}

/// Server bilan aloqa holati (burchakdagi belgi).
enum DisplayLink {
  /// Ulanmoqda.
  connecting,

  /// Real-time jonli: o'zgarish 1-2 soniyada keladi.
  live,

  /// Real-time yo'q yoki uzilgan: ekran har bir necha soniyada o'zi so'raydi.
  polling,

  /// Server javob bermayapti: oxirgi ma'lum holat ko'rsatilmoqda.
  offline,

  /// P6: internet yo'q, chek va raqamlar Wi-Fi orqali markaz kassadan.
  lan,
}

/// Tayyor bo'lgan (yoki qayta chaqirilgan) raqam: ekranda yirik ko'rsatiladi.
@immutable
class ReadyAnnouncement {
  const ReadyAnnouncement({
    required this.number,
    required this.at,
    this.again = false,
  });

  final int number;
  final DateTime at;

  /// Kassa raqamni qayta chaqirdi.
  final bool again;
}

/// Mijoz ekranining holati: kassa cheki (`display.cart` hodisasi va so'rov), "Tayyorlanmoqda / Tayyor" taxtasi
/// (`queue.*` hodisalari va so'rov), aloqa va ovoz.
///
/// * **Tezlik:** hodisa kelsa o'sha zahoti qayta o'qiladi (kutmasdan); tayyor raqam ovozi va yirik ko'rsatish hodisaning
///   o'zidan, so'rovni kutmasdan. Real-time bo'lmasa yoki uzilsa — [pollInterval] da so'raladi, jonli bo'lsa ham ehtiyot
///   uchun [livePollInterval] da; obuna (qayta) tasdiqlanganda darhol.
/// * **Aloqa uzilsa** oxirgi ma'lum chek va raqamlar turadi, burchakda "Aloqa yo'q"; tiklanganda o'zi yangilanadi.
/// * **Chek:** "Rahmat" [paidHold] turadi, kassa unutgan chek [staleCart] dan keyin salomlashishga qaytadi.
/// * **P6 lokal ko'prik** ([lan]): server javob bermasa chek (`cart.display`) va raqamlar (`queue.*`, `order.changed`)
///   markaz kassadan; shu paytda server so'ralmaydi (ko'prik o'zi tekshiradi), markaz navbati serverga yetgach odatdagi
///   rejim va darhol yangilanadi.
class DisplayController extends ChangeNotifier {
  DisplayController({
    required this.repository,
    required this.settings,
    required this.sound,
    this.realtime,
    DateTime Function()? clock,
    this.pollInterval = AppConfig.pollInterval,
    this.livePollInterval = AppConfig.livePollInterval,
    this.paidHold = AppConfig.paidHold,
    this.staleCart = AppConfig.staleCart,
    this.announceHold = AppConfig.announceHold,
    this.lan,
  }) : _clock = clock ?? DateTime.now;

  final PosDisplayRepository repository;
  final DisplaySettingsController settings;
  final ReadySound sound;
  final PosRealtime? realtime;
  final DateTime Function() _clock;
  final Duration pollInterval;
  final Duration livePollInterval;
  final Duration paidHold;
  final Duration staleCart;
  final Duration announceHold;

  /// P6 lokal ko'prik; `null` — yo'q.
  final DisplayLan? lan;

  PosDisplayState? _state;
  PosQueueBoard? _board;
  DateTime? _fetchedAt;
  DateTime? _lastUpdate;
  String? _error;
  bool _offline = false;
  bool _disposed = false;
  bool _started = false;

  Future<void>? _refreshing;
  bool _again = false;

  Timer? _poll;
  Timer? _tick;
  final List<Timer> _timers = <Timer>[];

  /// Muddat tugaganda qayta chizish uchun zaxira: soat bilan taymer orasidagi mayda farq ekranni "qotib" qoldirmasin.
  static const Duration _slack = Duration(milliseconds: 200);
  StreamSubscription<PosEvent>? _eventSub;
  StreamSubscription<PosLiveState>? _liveSub;
  StreamSubscription<void>? _resubSub;
  StreamSubscription<LanEvent>? _lanSub;
  bool _viaHub = false;

  // The till's last cart over the LAN (its run and number): an older one that arrives late is dropped
  String? _lanCartSession;
  int _lanCartSeq = -1;

  // "Rahmat" ekrani qachondan ko'rinadi (mahalliy soat: server soatiga bog'liq emas)
  String? _paidKey;
  DateTime? _paidSince;

  // Tayyor raqamlar: e'lon qilingan "raqam:chaqiruv" kalitlari va yirik ko'rsatiladiganlar
  final Map<String, DateTime> _announced = <String, DateTime>{};
  final List<ReadyAnnouncement> _recent = <ReadyAnnouncement>[];
  final StreamController<ReadyAnnouncement> _announcements =
      StreamController<ReadyAnnouncement>.broadcast();
  bool _boardLoaded = false;

  PosDisplayState? get state => _state;
  PosQueueBoard? get board => _board;
  String? get storeName => _state?.storeName;
  String? get branchName => _state?.branchName;

  /// Oxirgi muvaffaqiyatli yangilanish.
  DateTime? get lastUpdate => _lastUpdate;

  /// Serverning tushunarli rad etishi ("filialga ulanmagan", modul o'chiq) — tarmoq xatosi emas.
  String? get error => _error;

  Stream<ReadyAnnouncement> get announcements => _announcements.stream;

  /// Hozir yirik ko'rsatiladigan tayyor raqamlar (yangi: [announceHold] ichida).
  List<ReadyAnnouncement> get highlighted {
    final DateTime now = _clock();
    return <ReadyAnnouncement>[
      for (final ReadyAnnouncement a in _recent)
        if (now.difference(a.at) < announceHold) a,
    ];
  }

  /// Eng oxirgi e'lon (tepadagi yo'l uchun), muddati o'tmagan bo'lsa.
  ReadyAnnouncement? get latestAnnouncement {
    final List<ReadyAnnouncement> list = highlighted;
    return list.isEmpty ? null : list.last;
  }

  DisplayLink get link {
    if (lan?.viaHub ?? false) return DisplayLink.lan;
    if (_offline) return DisplayLink.offline;
    if (_state == null) return DisplayLink.connecting;
    final PosRealtime? rt = realtime;
    if (rt == null) return DisplayLink.polling;
    return switch (rt.state) {
      PosLiveState.live => DisplayLink.live,
      PosLiveState.connecting => DisplayLink.connecting,
      _ => DisplayLink.polling,
    };
  }

  /// Hozirgi chek: eskirgan yoki "Rahmat" muddati o'tgan bo'lsa salomlashish.
  PosDisplayCart get cart {
    final PosDisplayState? s = _state;
    final PosDisplayCart? c = s?.cart;
    if (s == null || c == null || c.isIdle) return const PosDisplayCart.idle();
    final DateTime now = _clock();
    final Duration? age = s.cartAge;
    final DateTime? fetched = _fetchedAt;
    if (age != null && fetched != null) {
      // Chekning serverdagi yoshi + javob kelgandan beri o'tgan vaqt (qurilma soati farqi tushmaydi)
      if (age + now.difference(fetched) > staleCart) {
        return const PosDisplayCart.idle();
      }
    }
    final DateTime? since = _paidSince;
    if (c.state == PosDisplayScreen.paid &&
        since != null &&
        now.difference(since) >= paidHold) {
      return const PosDisplayCart.idle();
    }
    return c;
  }

  DisplayView get view {
    final PosDisplayState? s = _state;
    // Hech narsa yuklanmagan, lekin server sababini aytdi (masalan, filialsiz ulangan): bo'sh aylana emas, tushuntirish
    if (s == null) {
      return _error != null ? DisplayView.notEnabled : DisplayView.loading;
    }
    final bool cd = s.modules.customerDisplay;
    final bool qc = s.modules.queueCalling && _board != null;
    final bool active = !cart.isIdle;
    switch (settings.value.mode) {
      case DisplayMode.cart:
        if (!cd) return DisplayView.notEnabled;
        return active ? DisplayView.cart : DisplayView.welcome;
      case DisplayMode.board:
        return s.modules.queueCalling
            ? DisplayView.board
            : DisplayView.notEnabled;
      case DisplayMode.auto:
        if (cd && active) return DisplayView.cart;
        if (s.modules.queueCalling) return DisplayView.board;
        if (cd) return DisplayView.welcome;
        // qc bo'lib, taxta hali kelmagan bo'lsa ham "yoqilmagan" demaymiz
        return qc ? DisplayView.board : DisplayView.notEnabled;
    }
  }

  // --- ishga tushirish -----------------------------------------------------------------------------------------

  void start() {
    if (_started || _disposed) return;
    _started = true;
    settings.addListener(_notify);
    final PosRealtime? rt = realtime;
    if (rt != null) {
      _eventSub = rt.events.listen(_onEvent);
      _liveSub = rt.states.listen((_) {
        _armPoll();
        _notify();
      });
      _resubSub = rt.resubscribed.listen((_) => unawaited(refresh()));
      rt.start();
    }
    final DisplayLan? l = lan;
    if (l != null) {
      _lanSub = l.events.listen(_onLanEvent);
      l.addListener(_onLanChanged);
    }
    _armPoll();
    unawaited(refresh());
  }

  /// The bridge's mode changed: back on the server — load from it now.
  void _onLanChanged() {
    final bool viaHub = lan?.viaHub ?? false;
    if (viaHub != _viaHub) {
      _viaHub = viaHub;
      if (!viaHub) {
        _lanCartSession = null;
        _lanCartSeq = -1;
        unawaited(refresh());
      }
    }
    _notify();
  }

  /// Hozir qayta o'qiydi. Bir vaqtda bitta so'rov; davomida yana so'ralsa — tugagach bir marta qayta.
  Future<void> refresh() {
    if (_disposed) return Future<void>.value();
    final Future<void>? running = _refreshing;
    if (running != null) {
      _again = true;
      return running;
    }
    return _refreshing = _loop().whenComplete(() => _refreshing = null);
  }

  Future<void> _loop() async {
    do {
      _again = false;
      await _load();
    } while (_again && !_disposed);
  }

  Future<void> _load() async {
    // Over the LAN the hub is the source; the bridge itself asks the server
    if (lan?.viaHub ?? false) return;
    try {
      final PosDisplayState s = await repository.state();
      if (_disposed) return;
      lan?.reportCloud(true);
      // The server answered while the bridge kept the LAN mode (the hub's queue not on the server yet): the hub's
      // picture stays until the bridge says it is over
      if (lan?.viaHub ?? false) return;
      _state = s;
      _fetchedAt = _clock();
      _trackPaid(s.cart);
      _error = null;
      _offline = false;
      if (s.modules.queueCalling) {
        try {
          final PosQueueBoard b = await repository.queue();
          if (_disposed) return;
          _applyBoard(b);
        } on PosModuleDisabled {
          _board = null;
        }
      } else {
        _board = null;
      }
      _lastUpdate = _clock();
    } on PosNetworkException {
      _offline = true;
      lan?.reportCloud(false);
    } on PosException catch (e) {
      // Server javob berdi, lekin rad etdi: tarmoq muammosi emas
      if (e.isNetworkError) {
        _offline = true;
        lan?.reportCloud(false);
      } else {
        _offline = false;
        _error = e.message;
      }
    } on Object catch (e) {
      _offline = true;
      if (kDebugMode) debugPrint('display refresh failed: $e');
    }
    _schedulePaidTick();
    _notify();
  }

  // --- hodisalar -----------------------------------------------------------------------------------------------

  void _onEvent(PosEvent e) {
    if (_disposed) return;
    switch (e) {
      case PosDisplayCartChanged():
        final String? till = _state?.tillUuid;
        if (till != null && e.terminal != null && e.terminal != till) return;
        unawaited(refresh());
      case PosQueueEvent():
        final int? number = e.number;
        if (number != null && e.name == 'queue.ready') {
          // Ovoz va yirik raqam hodisaning o'zidan: so'rov javobini kutmaymiz
          _announce(number, callCount: e.callCount, again: e.called);
        }
        unawaited(refresh());
      default:
        break;
    }
  }

  // --- P6: markaz kassa hodisalari ----------------------------------------------------------------------------

  void _onLanEvent(LanEvent e) {
    if (_disposed || !(lan?.viaHub ?? false)) return;
    final Map<String, Object?> p = e.payload;
    switch (e.type) {
      case LanMsg.cartDisplay:
        _applyLanCart(p);
      case LanMsg.queueReady || LanMsg.queueCalled:
        final int? number = (p['number'] as num?)?.toInt();
        final String? order = p['order'] as String?;
        if (number == null || order == null) return;
        final bool called = e.type == LanMsg.queueCalled || p['called'] == true;
        final int calls =
            (p['call_count'] as num?)?.toInt() ?? (called ? 1 : 0);
        _lanQueue(order, number, ready: true, callCount: calls);
        _announce(number, callCount: calls, again: called);
      case LanMsg.queueHanded:
        final String? order = p['order'] as String?;
        final int? number = (p['number'] as num?)?.toInt();
        _lanRemove(order, number);
        _notify();
      case LanMsg.orderChanged:
        _applyLanOrder(p);
      default:
        break;
    }
  }

  /// The till's cart (`POST /pos/display/cart` body) over the LAN. Only the hub's own cart: a screen that follows
  /// another till keeps its last one.
  void _applyLanCart(Map<String, Object?> body) {
    final String? hub = lan?.hubDevice;
    final String? till = _state?.tillUuid?.toLowerCase();
    if (till != null && hub != null && till != hub) return;
    final String? session = body['session'] as String?;
    final int seq = (body['seq'] as num?)?.toInt() ?? 0;
    if (session != null &&
        session == _lanCartSession &&
        seq <= _lanCartSeq &&
        seq > 0) {
      return;
    }
    _lanCartSession = session;
    _lanCartSeq = seq;
    final DateTime hubNow = lan!.link.hubNow();
    final PosDisplayCart cart = PosDisplayCart.fromJson(
      PosJson(<String, Object?>{
        ...body,
        'terminal': hub,
        'updated_at': hubNow.toUtc().toIso8601String(),
      }),
    );
    final PosDisplayState? was = _state;
    _state = PosDisplayState(
      branchId: was?.branchId ?? lan!.link.credentials?.branchId ?? 0,
      branchName: was?.branchName ?? '',
      storeName: was?.storeName,
      deviceName: was?.deviceName ?? '',
      tillUuid: was?.tillUuid,
      // A screen started without the internet: the till sends a cart, so this screen shows carts
      modules:
          was?.modules ??
          PosDisplayModules(
            customerDisplay: true,
            queueCalling: _board != null,
          ),
      serverTime: hubNow,
      cart: cart,
    );
    _fetchedAt = _clock();
    _trackPaid(cart);
    _lastUpdate = _clock();
    _schedulePaidTick();
    _notify();
  }

  /// An order on the hub with a queue number (README §6 `order.changed`): new on the board as "preparing", gone when
  /// handed, closed or canceled. "Ready" comes as `queue.ready` (announced once).
  void _applyLanOrder(Map<String, Object?> p) {
    final Object? raw = p['order'];
    if (raw is! Map) return;
    final Object? queue = raw['queue'];
    if (queue is! Map || queue['number'] is! num) return;
    final String ref = '${p['ref'] ?? raw['uuid']}';
    final int number = (queue['number']! as num).toInt();
    final String status = '${queue['status'] ?? ''}';
    final String orderStatus = '${raw['status'] ?? ''}';
    if (status == 'handed' ||
        orderStatus == 'canceled' ||
        orderStatus == 'cancelled' ||
        (orderStatus == 'closed' && status != 'ready')) {
      _lanRemove(ref, number);
    } else if (status != 'ready') {
      _lanQueue(ref, number, ready: false, channel: raw['channel'] as String?);
    }
    _notify();
  }

  void _lanQueue(
    String order,
    int number, {
    required bool ready,
    int callCount = 0,
    String? channel,
  }) {
    final PosQueueBoard b = _board ?? _emptyBoard();
    bool same(PosQueueEntry x) => x.orderUuid == order || x.number == number;
    final PosQueueEntry? was = <PosQueueEntry>[
      ...b.preparing,
      ...b.ready,
    ].where(same).firstOrNull;
    // Already ready: a late "preparing" from an older order.changed does not take it back
    if (!ready && b.ready.any(same)) return;
    final DateTime now = lan!.link.hubNow();
    final PosQueueEntry entry = PosQueueEntry(
      orderUuid: order,
      number: number,
      channel: channel ?? was?.channel ?? 'pos_takeaway',
      orderStatus: was?.orderStatus ?? 'open',
      businessDate: was?.businessDate ?? b.businessDate,
      status: ready ? PosPickupStatus.ready : PosPickupStatus.preparing,
      readyAt: ready ? (was?.readyAt ?? now) : null,
      calledAt: callCount > 0 ? now : was?.calledAt,
      callCount: callCount > (was?.callCount ?? 0)
          ? callCount
          : (was?.callCount ?? 0),
      openedAt: was?.openedAt,
    );
    _board = PosQueueBoard(
      businessDate: b.businessDate,
      preparing: <PosQueueEntry>[
        for (final PosQueueEntry x in b.preparing)
          if (!same(x)) x,
        if (!ready) entry,
      ],
      ready: <PosQueueEntry>[
        for (final PosQueueEntry x in b.ready)
          if (!same(x)) x,
        if (ready) entry,
      ],
    );
    _boardLoaded = true;
    _ensureQueueModule();
  }

  void _lanRemove(String? order, int? number) {
    final PosQueueBoard? b = _board;
    if (b == null) return;
    bool same(PosQueueEntry x) =>
        (order != null && x.orderUuid == order) ||
        (number != null && x.number == number);
    _board = PosQueueBoard(
      businessDate: b.businessDate,
      preparing: <PosQueueEntry>[
        for (final PosQueueEntry x in b.preparing)
          if (!same(x)) x,
      ],
      ready: <PosQueueEntry>[
        for (final PosQueueEntry x in b.ready)
          if (!same(x)) x,
      ],
    );
  }

  PosQueueBoard _emptyBoard() {
    final DateTime d = lan!.link.hubNow();
    String two(int n) => n.toString().padLeft(2, '0');
    return PosQueueBoard(
      businessDate: '${d.year}-${two(d.month)}-${two(d.day)}',
    );
  }

  /// A screen started without the internet: the hub sends numbers, so this screen shows them.
  void _ensureQueueModule() {
    final PosDisplayState? s = _state;
    final DateTime now = lan!.link.hubNow();
    if (s == null) {
      _state = PosDisplayState(
        branchId: lan!.link.credentials?.branchId ?? 0,
        branchName: '',
        deviceName: '',
        modules: const PosDisplayModules(queueCalling: true),
        serverTime: now,
      );
      _fetchedAt = _clock();
    }
  }

  void _announce(int number, {required int callCount, required bool again}) {
    final String key = '$number:$callCount';
    final DateTime now = _clock();
    if (_announced.containsKey(key)) return;
    _announced[key] = now;
    final ReadyAnnouncement a = ReadyAnnouncement(
      number: number,
      at: now,
      again: again || callCount > 0,
    );
    _recent
      ..removeWhere((ReadyAnnouncement r) => r.number == number)
      ..add(a);
    if (!_announcements.isClosed) _announcements.add(a);
    if (!settings.value.muted) unawaited(sound.play());
    // Yirik ko'rsatish tugaganda ekran qayta chiziladi (soat va taymer bir necha millisoniya farq qilsa ham)
    _timers.add(Timer(announceHold + _slack, _notify));
    _timers.removeWhere((Timer t) => !t.isActive);
    _notify();
  }

  void _applyBoard(PosQueueBoard b) {
    _board = b;
    final DateTime now = _clock();
    // Birinchi yuklashdagi tayyorlar yangi emas
    if (!_boardLoaded) {
      _boardLoaded = true;
      for (final PosQueueEntry e in b.ready) {
        _announced['${e.number}:${e.callCount}'] = now;
      }
      return;
    }
    // Real-time yo'q yoki hodisa yo'qolgan bo'lsa ham yangi tayyor raqam ovoz bilan chiqadi
    for (final PosQueueEntry e in b.ready) {
      _announce(e.number, callCount: e.callCount, again: e.callCount > 0);
    }
    // Qaytib "tayyorlanmoqda"ga tushgan raqam keyin yana tayyor bo'lsa, yangidan e'lon qilinadi
    final Set<int> preparing = <int>{
      for (final PosQueueEntry e in b.preparing) e.number,
    };
    _announced.removeWhere(
      (String key, DateTime at) =>
          preparing.contains(int.tryParse(key.split(':').first)),
    );
    // Taxtadan ketgan (berilgan) raqamlarning kalitlari eskirgach tozalanadi
    final Set<int> onBoard = <int>{
      for (final PosQueueEntry e in b.ready) e.number,
      ...preparing,
    };
    _announced.removeWhere(
      (String key, DateTime at) =>
          !onBoard.contains(int.tryParse(key.split(':').first)) &&
          now.difference(at) > const Duration(minutes: 1),
    );
    _recent.removeWhere(
      (ReadyAnnouncement r) => now.difference(r.at) >= announceHold,
    );
  }

  // --- chek: "Rahmat" muddati ----------------------------------------------------------------------------------

  void _trackPaid(PosDisplayCart? c) {
    if (c == null || c.state != PosDisplayScreen.paid) {
      _paidKey = null;
      _paidSince = null;
      return;
    }
    final String key = '${c.terminal}|${c.session}|${c.seq}';
    if (key != _paidKey) {
      _paidKey = key;
      _paidSince = _clock();
    }
  }

  void _schedulePaidTick() {
    _tick?.cancel();
    _tick = null;
    final PosDisplayCart? c = _state?.cart;
    if (c == null || c.isIdle) return;
    final DateTime? since = _paidSince;
    Duration? wait;
    if (c.state == PosDisplayScreen.paid && since != null) {
      wait = paidHold - _clock().difference(since);
    } else {
      final Duration? age = _state?.cartAge;
      final DateTime? fetched = _fetchedAt;
      if (age != null && fetched != null) {
        wait = staleCart - (age + _clock().difference(fetched));
      }
    }
    if (wait == null) return;
    _tick = Timer(wait.isNegative ? Duration.zero : wait + _slack, _notify);
  }

  // --- so'rov jadvali ------------------------------------------------------------------------------------------

  void _armPoll() {
    _poll?.cancel();
    if (_disposed) return;
    final bool live = realtime?.isLive ?? false;
    final Duration every = live ? livePollInterval : pollInterval;
    if (every <= Duration.zero) return;
    _poll = Timer.periodic(every, (_) => unawaited(refresh()));
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _poll?.cancel();
    _tick?.cancel();
    for (final Timer t in _timers) {
      t.cancel();
    }
    settings.removeListener(_notify);
    unawaited(_eventSub?.cancel());
    unawaited(_liveSub?.cancel());
    unawaited(_resubSub?.cancel());
    unawaited(_lanSub?.cancel());
    lan?.removeListener(_onLanChanged);
    unawaited(_announcements.close());
    super.dispose();
  }
}

/// Real-time ulanish holati sarlavhasi (testlar va belgi uchun).
String describeLink(DisplayLink link) => switch (link) {
  DisplayLink.connecting => 'Ulanmoqda',
  DisplayLink.live => 'Jonli',
  DisplayLink.polling => 'Har bir necha soniyada',
  DisplayLink.offline => "Aloqa yo'q",
  DisplayLink.lan => 'Lokal tarmoq',
};
