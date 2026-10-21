import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:rizqim_api/rizqim_api.dart';

import '../realtime/branch_realtime.dart';
import '../settings/kds_settings.dart';
import '../sound/ticket_sound.dart';

/// "Tayyor" deb belgilangan qator: "Yaqinda tayyor" lentasida turadi, undan qaytarish mumkin.
@immutable
class RecentBump {
  const RecentBump({
    required this.orderUuid,
    required this.code,
    required this.lineId,
    required this.title,
    required this.quantityLabel,
    required this.at,
  });

  final String orderUuid;
  final String code;
  final int lineId;
  final String title;
  final String quantityLabel;
  final DateTime at;
}

/// Ekranga chiqadigan qisqa xabar (xato yoki ma'lumot).
@immutable
class BoardNotice {
  const BoardNotice(this.message, {this.error = true});

  final String message;
  final bool error;
}

/// Oshxona doskasi holati: chiptalarni yuklash (real-time hodisa, qayta ulanish va polling bo'yicha), qatorni
/// "tayyor" deb belgilash va qaytarish (optimistik: ekran darhol o'zgaradi, server rad etsa orqaga qaytadi).
///
/// Biznes qoidalari serverda: qaysi buyurtma doskada, qachon `ready` bo'lishi, kim nima qila olishi. Controller
/// faqat server javobini ko'rsatadi va xatoni o'zbekcha xabar qilib beradi.
class KitchenBoardController extends ChangeNotifier {
  KitchenBoardController({
    required this.service,
    required this.settings,
    required this.sound,
    this.realtime,
    this.branchId,
    this.canManage = true,
    DateTime Function()? clock,
    this.pollInterval = const Duration(seconds: 15),
    this.livePollInterval = const Duration(seconds: 60),
    this.recentWindow = const Duration(minutes: 10),
    this.recentMax = 12,
    this.newBadgeFor = const Duration(minutes: 1),
    this.eventDebounce = const Duration(milliseconds: 300),
  }) : _clock = clock ?? DateTime.now;

  final KitchenService service;
  final KdsSettingsController settings;
  final TicketSound sound;
  final DateTime Function() _clock;

  /// `null` — real-time yo'q (kalit sozlanmagan yoki terminal filialsiz): faqat polling.
  final BranchRealtime? realtime;

  /// So'rovdagi `branch_id` (terminal filiali). `null` — server o'zi hal qiladi.
  final int? branchId;

  /// Xodimda `kitchen.manage` bor: "tayyor" va "qaytarish" tugmalari. Yo'q bo'lsa doska faqat ko'rish uchun.
  final bool canManage;

  final Duration pollInterval;
  final Duration livePollInterval;
  final Duration recentWindow;
  final int recentMax;
  final Duration newBadgeFor;
  final Duration eventDebounce;

  // --- holat ------------------------------------------------------------------------------------------------------

  List<KitchenTicket> _server = <KitchenTicket>[];
  bool _loaded = false;
  bool _truncated = false;
  ApiException? _error;
  DateTime? _lastSuccess;

  /// Javobi kutilayotgan amallar: qator id -> ko'rsatiladigan holat.
  final Map<int, String> _lineOverrides = <int, String>{};

  /// "Buyurtma tayyor" yuborilgan, javob kutilmoqda: doskada ko'rinmaydi.
  final Set<String> _hidden = <String>{};

  /// "Hammasi tayyor" bajarilmoqda.
  final Set<String> _busyOrders = <String>{};

  final List<RecentBump> _recent = <RecentBump>[];
  final Map<String, DateTime> _firstSeen = <String, DateTime>{};

  static final DateTime _longAgo = DateTime.fromMillisecondsSinceEpoch(0);

  final StreamController<BoardNotice> _notices =
      StreamController<BoardNotice>.broadcast();

  Future<void>? _refreshing;
  bool _refreshAgain = false;
  Timer? _pollTimer;
  Timer? _debounce;
  StreamSubscription<RealtimeEvent>? _eventSub;
  RealtimeStatusListener? _statusListener;
  bool _wasLive = false;
  bool _disposed = false;

  /// Doskadagi chiptalar: eskisidan yangisiga, javobi kutilayotgan amallar bilan.
  List<KitchenTicket> get tickets {
    final List<KitchenTicket> out = <KitchenTicket>[];
    for (final KitchenTicket t in _server) {
      if (_hidden.contains(t.uuid)) continue;
      if (_lineOverrides.isEmpty) {
        out.add(t);
        continue;
      }
      var changed = false;
      final List<KitchenLine> lines = <KitchenLine>[];
      for (final KitchenLine l in t.lines) {
        final String? status = _lineOverrides[l.id];
        if (status != null && status != l.status) {
          lines.add(l.copyWith(status: status));
          changed = true;
        } else {
          lines.add(l);
        }
      }
      out.add(changed ? t.copyWith(lines: lines) : t);
    }
    return out;
  }

  bool get loaded => _loaded;
  bool get truncated => _truncated;

  /// Oxirgi yuklash xatosi (chiptalar baribir oxirgi holatda ko'rsatiladi).
  ApiException? get error => _error;

  /// Server bilan aloqa yo'q (tarmoq, vaqt tugashi, 5xx): "Aloqa yo'q" lentasi.
  bool get offline {
    final ApiException? e = _error;
    if (e == null) return false;
    final int? code = e.statusCode;
    return code == null || code >= 500;
  }

  DateTime? get lastSuccess => _lastSuccess;
  List<RecentBump> get recent => List<RecentBump>.unmodifiable(_recent);
  Stream<BoardNotice> get notices => _notices.stream;

  bool isLineBusy(int lineId) => _lineOverrides.containsKey(lineId);
  bool isOrderBusy(String uuid) => _busyOrders.contains(uuid);

  /// Chipta yaqinda paydo bo'ldi ("Yangi" belgisi).
  bool isNew(String uuid, DateTime now) {
    final DateTime? seen = _firstSeen[uuid];
    return seen != null && now.difference(seen) < newBadgeFor;
  }

  // --- yuklash ------------------------------------------------------------------------------------------------------

  void start() {
    final BranchRealtime? rt = realtime;
    if (rt != null) {
      _eventSub = rt.events.listen((_) => _scheduleEventRefresh());
      _wasLive = rt.isLive;
      void onStatus() {
        final bool live = rt.isLive;
        // Qayta ulanganda uzilish paytidagi hodisalar yo'qolgan: ro'yxat to'liq qayta yuklanadi
        if (live && !_wasLive) unawaited(refresh());
        _wasLive = live;
        _schedulePoll();
        _notify();
      }

      _statusListener = onStatus;
      rt.status.addListener(onStatus);
    }
    unawaited(refresh());
  }

  /// Chiptalarni qayta so'raydi. Bir vaqtda bittadan; ish paytida yana chaqirilsa, tugagach yana bir marta.
  Future<void> refresh() {
    if (_refreshing != null) {
      _refreshAgain = true;
      return _refreshing!;
    }
    return _refreshing = _refreshLoop().whenComplete(() => _refreshing = null);
  }

  Future<void> _refreshLoop() async {
    do {
      _refreshAgain = false;
      await _fetch();
    } while (_refreshAgain && !_disposed);
    _schedulePoll();
  }

  Future<void> _fetch() async {
    try {
      final KitchenTicketList list = await service.tickets(branchId: branchId);
      if (_disposed) return;
      final DateTime now = _clock();
      var fresh = false;
      for (final KitchenTicket t in list.tickets) {
        if (_firstSeen.containsKey(t.uuid)) continue;
        // Birinchi yuklashdagilar "yangi" emas
        _firstSeen[t.uuid] = _loaded ? now : _longAgo;
        if (_loaded) fresh = true;
      }
      final Set<String> present = <String>{
        for (final KitchenTicket t in list.tickets) t.uuid,
      };
      _firstSeen.removeWhere(
        (String uuid, DateTime at) =>
            !present.contains(uuid) && now.difference(at) > recentWindow,
      );
      // "Buyurtma tayyor" javob kutayotgan bo'lsa ham, serverda yo'q bo'lsa endi yashirish shart emas
      _hidden.removeWhere((String uuid) => !present.contains(uuid));
      _server = list.tickets;
      _truncated = list.truncated;
      _error = null;
      _lastSuccess = now;
      _loaded = true;
      if (fresh && !settings.value.muted) unawaited(sound.play());
    } on ApiException catch (e) {
      if (_disposed) return;
      _error = e;
    }
    _pruneRecent();
    _notify();
  }

  void _scheduleEventRefresh() {
    _debounce?.cancel();
    _debounce = Timer(eventDebounce, () => unawaited(refresh()));
  }

  void _schedulePoll() {
    _pollTimer?.cancel();
    if (_disposed) return;
    final bool live = realtime?.isLive ?? false;
    _pollTimer = Timer(
      live && !offline ? livePollInterval : pollInterval,
      () => unawaited(refresh()),
    );
  }

  // --- amallar ------------------------------------------------------------------------------------------------------

  /// Qator tayyor ("bump").
  Future<void> bumpLine(KitchenTicket ticket, KitchenLine line) async {
    if (!canManage || _lineOverrides.containsKey(line.id) || line.isReady) {
      return;
    }
    final RecentBump entry = _recentFor(ticket, line);
    _lineOverrides[line.id] = KitchenLineStatus.ready;
    _addRecent(entry);
    _notify();
    try {
      final KitchenTicket? updated = await service.bumpLine(
        ticket.uuid,
        line.id,
      );
      _replace(updated);
    } on ApiException catch (e) {
      _recent.remove(entry);
      _fail(e);
    } finally {
      _lineOverrides.remove(line.id);
      _afterChange();
    }
  }

  /// Xato bosilgan "tayyor"ni qaytarish (doskadan yoki "Yaqinda tayyor" lentasidan).
  Future<void> recallLine(String orderUuid, int lineId) async {
    if (!canManage || _lineOverrides.containsKey(lineId)) return;
    final int index = _recent.indexWhere((RecentBump r) => r.lineId == lineId);
    final RecentBump? entry = index < 0 ? null : _recent.removeAt(index);
    _lineOverrides[lineId] = KitchenLineStatus.pending;
    _notify();
    try {
      final KitchenTicket? updated = await service.recallLine(
        orderUuid,
        lineId,
      );
      _replace(updated);
    } on ApiException catch (e) {
      if (entry != null) {
        // Server rad etdi: tarmoq yoki server xatosida lentaga qaytadi (keyin yana urinish mumkin). Oxirgi "tayyor"
        // buyurtmani o'zi `ready` qilgan bo'lsa ham qaytarish ishlaydi (P2-30: buyurtma yana `preparing` bo'ladi);
        // 422 faqat kuryer biriktirilgan, yo'lda yoki topshirilgan buyurtmada — u lentadan olib tashlanadi
        if (e.statusCode == null || e.statusCode! >= 500) {
          _recent.insert(index.clamp(0, _recent.length), entry);
        }
      }
      _fail(e);
    } finally {
      _lineOverrides.remove(lineId);
      _afterChange();
    }
  }

  /// Chiptaning qolgan hamma qatorlari tayyor. Qatorlar ketma-ket yuboriladi (oxirgisida server buyurtmani o'zi
  /// `ready` qilishi mumkin); bittasi rad etilsa qolganlari orqaga qaytadi.
  Future<void> bumpAll(KitchenTicket ticket) async {
    if (!canManage || _busyOrders.contains(ticket.uuid)) return;
    final List<KitchenLine> pending = ticket.lines
        .where(
          (KitchenLine l) => !l.isReady && !_lineOverrides.containsKey(l.id),
        )
        .toList();
    if (pending.isEmpty) return;
    _busyOrders.add(ticket.uuid);
    final Map<int, RecentBump> entries = <int, RecentBump>{
      for (final KitchenLine l in pending) l.id: _recentFor(ticket, l),
    };
    for (final KitchenLine l in pending) {
      _lineOverrides[l.id] = KitchenLineStatus.ready;
      _addRecent(entries[l.id]!);
    }
    _notify();
    try {
      for (final KitchenLine l in pending) {
        final KitchenTicket? updated = await service.bumpLine(
          ticket.uuid,
          l.id,
        );
        _replace(updated);
        _lineOverrides.remove(l.id);
      }
    } on ApiException catch (e) {
      for (final KitchenLine l in pending) {
        if (_lineOverrides.remove(l.id) != null) _recent.remove(entries[l.id]);
      }
      _fail(e);
    } finally {
      for (final KitchenLine l in pending) {
        _lineOverrides.remove(l.id);
      }
      _busyOrders.remove(ticket.uuid);
      _afterChange();
    }
  }

  /// Butun buyurtma tayyor (`status = ready`). Masalan, zal buyurtmasi: qatorlar tayyor bo'lsa ham server uni o'zi
  /// `ready` qilmaydi (docs/API.md 9.1).
  Future<void> markOrderReady(KitchenTicket ticket) async {
    if (!canManage || _hidden.contains(ticket.uuid)) return;
    _hidden.add(ticket.uuid);
    _notify();
    try {
      await service.markOrderReady(ticket.uuid);
      // Yangilangan ro'yxat kelguncha yashirin turadi
    } on ApiException catch (e) {
      _hidden.remove(ticket.uuid);
      _fail(e);
    } finally {
      _afterChange();
    }
  }

  // --- yordamchilar -----------------------------------------------------------------------------------------------

  RecentBump _recentFor(KitchenTicket t, KitchenLine l) => RecentBump(
    orderUuid: t.uuid,
    code: t.code,
    lineId: l.id,
    title: l.title,
    quantityLabel: l.quantityLabel,
    at: _clock(),
  );

  void _addRecent(RecentBump entry) {
    _recent.removeWhere((RecentBump r) => r.lineId == entry.lineId);
    _recent.insert(0, entry);
    _pruneRecent();
  }

  void _pruneRecent() {
    final DateTime now = _clock();
    _recent.removeWhere((RecentBump r) => now.difference(r.at) > recentWindow);
    if (_recent.length > recentMax) {
      _recent.removeRange(recentMax, _recent.length);
    }
  }

  /// Server qaytargan chipta ro'yxatdagi eskisining o'rniga.
  void _replace(KitchenTicket? updated) {
    if (updated == null) return;
    final int i = _server.indexWhere(
      (KitchenTicket t) => t.uuid == updated.uuid,
    );
    if (i < 0) return;
    _server = List<KitchenTicket>.of(_server)..[i] = updated;
  }

  void _fail(ApiException e) {
    final String message = e.isNetworkError
        ? "Aloqa yo'q: o'zgarish saqlanmadi. Qayta urinib ko'ring."
        : e.message;
    if (!_notices.isClosed) _notices.add(BoardNotice(message));
  }

  /// Har amaldan keyin ro'yxat server bilan solishtiriladi (buyurtma oshxonadan chiqqan bo'lishi mumkin).
  void _afterChange() {
    _notify();
    if (!_disposed) _scheduleEventRefresh();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _pollTimer?.cancel();
    _debounce?.cancel();
    unawaited(_eventSub?.cancel());
    final RealtimeStatusListener? l = _statusListener;
    if (l != null) realtime?.status.removeListener(l);
    unawaited(_notices.close());
    super.dispose();
  }
}

typedef RealtimeStatusListener = VoidCallback;
