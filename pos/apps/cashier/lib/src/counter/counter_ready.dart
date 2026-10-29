import 'dart:async';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:pos_data/pos_data.dart';

import '../features/order/order_format.dart';
import '../features/settings/cashier_prefs.dart';

/// Ekrandagi e'lon: raqam tayyor yoki qayta chaqirildi.
class ReadyAnnouncement {
  const ReadyAnnouncement({
    required this.number,
    required this.at,
    this.recall = false,
  });

  final int number;
  final DateTime at;

  /// Qayta chaqiruv (`called: true`).
  final bool recall;

  String get text => recall
      ? 'Raqam $number qayta chaqirildi'
      : 'Raqam $number tayyor — mijozni chaqiring';
}

/// P6-13 kassada: "Tayyor" ro'yxati (`GET /pos/queue`), `queue.ready` (va qayta chaqiruv) kelganda ovoz va ekranda
/// e'lon, "Berildi" (`.../handed`), "Qayta chaqirish" (`.../call`) va KDS'siz kassada "Tayyor" (`.../ready`). Real-time
/// hodisa yoki polling bilan yangilanadi; modul o'chiq bo'lsa (403 `module_disabled`) jim to'xtaydi.
///
/// Hech biri to'lov, schyot yoki chekka tegmaydi.
class CounterReadyBoard extends ChangeNotifier {
  CounterReadyBoard({
    required this.repository,
    required this.now,
    this.realtime,
    this.branchId,
    this.pollInterval = const Duration(seconds: 15),
    this.livePollInterval = const Duration(seconds: 60),
    this.sound,
  });

  final PosQueueRepository repository;
  final DateTime Function() now;
  final PosRealtime? realtime;
  final int? branchId;
  final Duration pollInterval;
  final Duration livePollInterval;

  /// Ovoz (test o'rnini bosadi); `null` — tizim signali, kassa sozlamasida ovoz yoqilgan bo'lsa.
  final void Function()? sound;

  List<PosQueueEntry> _ready = const <PosQueueEntry>[];
  List<PosQueueEntry> _preparing = const <PosQueueEntry>[];
  ReadyAnnouncement? _announcement;
  bool _disabled = false;
  bool _disposed = false;
  final Set<String> _busy = <String>{};
  final Map<String, String> _keys = <String, String>{};
  PosRefreshScheduler? _scheduler;
  StreamSubscription<PosEvent>? _sub;
  Timer? _hide;
  var _seq = 0;
  final Random _random = Random();
  final StreamController<String> _errors = StreamController<String>.broadcast();

  List<PosQueueEntry> get ready => _ready;
  List<PosQueueEntry> get preparing => _preparing;
  ReadyAnnouncement? get announcement => _announcement;

  /// Modul o'chiq: ro'yxat ko'rsatilmaydi.
  bool get disabled => _disabled;
  bool isBusy(String orderUuid) => _busy.contains(orderUuid);
  Stream<String> get errors => _errors.stream;

  void start() {
    _sub ??= realtime?.events.listen((PosEvent e) {
      if (e is! PosQueueEvent) return;
      if (e.announce && e.number != null) {
        announce(e.number!, recall: e.called);
      }
      // The board moves at once (the debounced refresh below is the fallback)
      unawaited(refresh());
    });
    _scheduler ??= PosRefreshScheduler(
      realtime: realtime,
      pollInterval: pollInterval,
      livePollInterval: livePollInterval,
      eventFilter: (PosEvent e) => e is PosOrderEvent,
      onRefresh: (_) => refresh(),
    )..start();
    unawaited(refresh());
  }

  /// Ovoz va ekrandagi e'lon (10 soniya ko'rinadi).
  void announce(int number, {bool recall = false}) {
    _announcement = ReadyAnnouncement(
      number: number,
      at: now(),
      recall: recall,
    );
    _hide?.cancel();
    _hide = Timer(const Duration(seconds: 10), dismissAnnouncement);
    final void Function()? custom = sound;
    if (custom != null) {
      custom();
    } else if (CashierPrefs.instance.sound) {
      unawaited(SystemSound.play(SystemSoundType.alert));
    }
    _notify();
  }

  void dismissAnnouncement() {
    _hide?.cancel();
    _announcement = null;
    _notify();
  }

  Future<void> refresh() async {
    if (_disabled) return;
    try {
      final PosQueueBoard board = await repository.board(branchId: branchId);
      _ready = board.ready;
      _preparing = board.preparing;
      _notify();
    } on PosModuleDisabled {
      _disable();
    } on PosForbidden {
      _disable();
    } on PosNotFound {
      _disable();
    } on PosException {
      // Aloqa yo'q: oxirgi ro'yxat qoladi
    }
  }

  void _disable() {
    _disabled = true;
    _ready = const <PosQueueEntry>[];
    _preparing = const <PosQueueEntry>[];
    unawaited(_scheduler?.dispose());
    _scheduler = null;
    _notify();
  }

  /// Mijoz oldi.
  Future<bool> handed(PosQueueEntry e) => _act(
    e,
    'handed',
    (String key) => repository.handed(e.orderUuid, idempotencyKey: key),
  );

  /// Tayyor raqamni qayta e'lon qilish.
  Future<bool> call(PosQueueEntry e) => _act(
    e,
    'call:${e.callCount}',
    (String key) => repository.call(e.orderUuid, idempotencyKey: key),
  );

  /// KDS'siz kassa: raqam tayyor.
  Future<bool> markReady(PosQueueEntry e) => _act(
    e,
    'ready',
    (String key) => repository.ready(e.orderUuid, idempotencyKey: key),
  );

  Future<bool> _act(
    PosQueueEntry e,
    String what,
    Future<PosQueueEntry> Function(String key) call,
  ) async {
    if (!_busy.add(e.orderUuid)) return false;
    _notify();
    final String keyFor = '${e.orderUuid}:$what';
    final String key = _keys[keyFor] ??=
        'queue-${e.orderUuid}-$what-${++_seq}-${_random.nextInt(1 << 32)}';
    try {
      final PosQueueEntry next = await call(key);
      _keys.remove(keyFor);
      _apply(next);
      return true;
    } on PosNetworkException {
      if (!_errors.isClosed) {
        _errors.add("Aloqa yo'q: amal bajarilmadi. Qayta bosish xavfsiz.");
      }
      return false;
    } on PosException catch (x) {
      _keys.remove(keyFor);
      if (!_errors.isClosed) _errors.add(posErrorText(x));
      unawaited(refresh());
      return false;
    } finally {
      _busy.remove(e.orderUuid);
      _notify();
    }
  }

  void _apply(PosQueueEntry next) {
    bool same(PosQueueEntry x) => x.orderUuid == next.orderUuid;
    final List<PosQueueEntry> ready = <PosQueueEntry>[
      for (final PosQueueEntry x in _ready)
        if (!same(x)) x,
    ];
    final List<PosQueueEntry> preparing = <PosQueueEntry>[
      for (final PosQueueEntry x in _preparing)
        if (!same(x)) x,
    ];
    switch (next.status) {
      case PosPickupStatus.ready:
        ready.add(next);
      case PosPickupStatus.preparing:
        preparing.add(next);
      case PosPickupStatus.handed || null:
        break;
    }
    _ready = ready;
    _preparing = preparing;
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _hide?.cancel();
    unawaited(_sub?.cancel());
    unawaited(_scheduler?.dispose());
    unawaited(_errors.close());
    super.dispose();
  }
}
