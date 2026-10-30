import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:pos_data/pos_data.dart';

import '../../common/errors.dart';

/// Hostes stoli holati (P6-24/P6-26): kunning kutish ro'yxati, zal (stollar va "tozalanmoqda" belgisi) va bugungi
/// bronlar. Yangilanish: `waitlist.changed`, `table.state_changed`, buyurtma va bron hodisalari (jonli bo'lmasa
/// polling). Har amal `Idempotency-Key` bilan; javob kelmasa xuddi shu amal qayta bosilganda o'sha kalit ketadi.
class HostessController extends ChangeNotifier {
  HostessController({
    required this.data,
    this.realtime,
    this.pollInterval = const Duration(seconds: 15),
    this.livePollInterval = const Duration(seconds: 60),
  });

  final PosData data;
  final PosRealtime? realtime;
  final Duration pollInterval;
  final Duration livePollInterval;

  PosWaitlist? waitlist;
  PosFloor? floor;
  List<PosBooking>? bookings;
  Object? error;
  bool loading = false;

  /// Bir vaqtda bitta amal (tugmalar o'chadi).
  bool busy = false;

  PosRefreshScheduler? _scheduler;
  final Map<String, String> _lostKeys = <String, String>{};
  bool _disposed = false;

  PosSession get session => data.session;
  bool get online => data.connectivity.state.online;
  bool get hasData => waitlist != null || floor != null;

  /// Bugungi biznes kun (`YYYY-MM-DD`).
  String? get today {
    final String? d = session.bootstrap?.branch.businessDate;
    return d == null || d.isEmpty ? null : d;
  }

  void start() {
    _scheduler = PosRefreshScheduler(
      realtime: realtime,
      pollInterval: pollInterval,
      livePollInterval: livePollInterval,
      eventFilter: (PosEvent e) =>
          e is PosWaitlistChanged ||
          e is PosTableStateChanged ||
          e is PosBookingChanged ||
          e is PosOrderEvent ||
          e is PosOrderTransferred ||
          e is PosOrderBillChanged,
      onRefresh: (PosRefreshReason _) => load(),
    )..start();
    unawaited(load());
  }

  Future<void> refresh() =>
      _scheduler == null ? load() : _scheduler!.refreshNow();

  /// Uchala ro'yxat; birortasi yuklanmasa oldingisi qoladi, xato ko'rsatiladi.
  Future<void> load() async {
    if (_disposed) return;
    loading = true;
    _notify();
    Object? failure;
    await Future.wait(<Future<void>>[
      () async {
        try {
          waitlist = await data.hostess.waitlist();
        } on Object catch (e) {
          failure ??= e;
        }
      }(),
      () async {
        try {
          floor = await data.floor.load();
        } on Object catch (e) {
          failure ??= e;
        }
      }(),
      () async {
        final String? d = today;
        if (d == null) return;
        try {
          bookings = await data.bookings.list(from: d, to: d);
        } on Object catch (e) {
          // Bookings are a side panel: an error there does not hide the waitlist
          if (e is! PosForbidden) failure ??= e;
        }
      }(),
    ]);
    error = failure;
    loading = false;
    _notify();
  }

  // --- kutish ro'yxati -----------------------------------------------------------------------------------------------

  Future<String?> add({
    required String name,
    required int partySize,
    String? phone,
    int? quotedWaitMinutes,
    String? note,
  }) => _run(
    'add:$name:$partySize:${phone ?? ''}:${quotedWaitMinutes ?? ''}:${note ?? ''}',
    (String? key) => data.hostess.add(
      guestName: name,
      partySize: partySize,
      guestPhone: phone,
      quotedWaitMinutes: quotedWaitMinutes,
      note: note,
      idempotencyKey: key,
    ),
  );

  Future<String?> notifyGuest(PosWaitlistEntry e) => _run(
    'notify:${e.uuid}',
    (String? key) =>
        data.hostess.notify(e.uuid, version: e.version, idempotencyKey: key),
  );

  /// Joylashtirish; [openOrder] — stolda buyurtma ham ochiladi ([waiterId] — uning ofitsianti). Natija: xato matni
  /// (`null` — bajarildi) va yangi yozuv.
  Future<({String? error, PosWaitlistEntry? entry})> seat(
    PosWaitlistEntry e, {
    required int tableId,
    bool openOrder = false,
    int? waiterId,
  }) async {
    PosWaitlistEntry? seated;
    final String? err = await _run(
      'seat:${e.uuid}:$tableId:$openOrder:${waiterId ?? ''}',
      (String? key) async => seated = await data.hostess.seat(
        e.uuid,
        diningTableId: tableId,
        openOrder: openOrder,
        waiterId: waiterId,
        version: e.version,
        idempotencyKey: key,
      ),
    );
    return (error: err, entry: seated);
  }

  Future<String?> cancel(PosWaitlistEntry e, {String? reason}) => _run(
    'cancel:${e.uuid}',
    (String? key) => data.hostess.cancel(
      e.uuid,
      reason: reason,
      version: e.version,
      idempotencyKey: key,
    ),
  );

  Future<String?> noShow(PosWaitlistEntry e) => _run(
    'no_show:${e.uuid}',
    (String? key) =>
        data.hostess.noShow(e.uuid, version: e.version, idempotencyKey: key),
  );

  // --- stollar -------------------------------------------------------------------------------------------------------

  /// "Tozalanmoqda" ([cleaning] `true`) yoki "Tayyor".
  Future<String?> setCleaning(FloorTable t, {required bool cleaning}) => _run(
    'table:${t.id}:$cleaning',
    (String? key) => data.hostess.setTableCleaning(
      t.id,
      cleaning: cleaning,
      idempotencyKey: key,
    ),
  );

  // --- bron ---------------------------------------------------------------------------------------------------------

  Future<({String? error, PosBooking? booking})> createBooking(
    PosBookingInput input,
  ) async {
    PosBooking? made;
    final String? err = await _run(
      'booking:${input.toJson()}',
      (String? key) async =>
          made = await data.bookings.create(input, idempotencyKey: key),
    );
    return (error: err, booking: made);
  }

  // --- ichki --------------------------------------------------------------------------------------------------------

  /// Amal → `null` (bajarildi, ro'yxatlar qayta yuklanadi) yoki ekranga xato matni.
  Future<String?> _run(
    String signature,
    Future<Object?> Function(String? key) call,
  ) async {
    if (busy) return '';
    if (!online) {
      return "Aloqa yo'q. Hozircha faqat ko'rish mumkin: aloqa qaytgach amalni takrorlang.";
    }
    busy = true;
    _notify();
    try {
      await call(_lostKeys[signature]);
      _lostKeys.remove(signature);
      unawaited(load());
      return null;
    } on PosNetworkException catch (e) {
      if (e.idempotencyKey != null) _lostKeys[signature] = e.idempotencyKey!;
      return describeError(e);
    } on Object catch (e) {
      _lostKeys.remove(signature);
      // Someone else changed the entry (stale_version, entry_closed): show what is there now
      if (e is PosConflict) unawaited(load());
      return describeError(e);
    } finally {
      busy = false;
      _notify();
    }
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    final PosRefreshScheduler? s = _scheduler;
    if (s != null) unawaited(s.dispose());
    super.dispose();
  }
}
