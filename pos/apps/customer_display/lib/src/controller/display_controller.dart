import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:pos_data/pos_data.dart';

import '../app_config.dart';
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
    _armPoll();
    unawaited(refresh());
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
    try {
      final PosDisplayState s = await repository.state();
      if (_disposed) return;
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
    } on PosException catch (e) {
      // Server javob berdi, lekin rad etdi: tarmoq muammosi emas
      if (e.isNetworkError) {
        _offline = true;
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
};
