import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:pos_data/pos_data.dart';

import '../../common/errors.dart';

/// P6-23 "Ishga keldim / Ishdan ketdim": kirgan xodimning ish vaqti (`/pos/clock`). Boshlang'ich holat bootstrap'dagi
/// `staff_pay.session`, keyin server. Internetsiz belgilash yo'q (backendda sinxronlash amali yo'q): aloqa bo'lmasa
/// oddiy "internet kerak" xabari.
class ClockController extends ChangeNotifier {
  ClockController({required this.data}) {
    final PosWorkSession? s = data.session.bootstrap?.staffPay?.session;
    clockedIn = s != null;
    since = s?.clockInAt;
  }

  final PosData data;

  bool clockedIn = false;

  /// Ishga kelgan vaqt (ishda bo'lsa).
  DateTime? since;

  /// Bugun yopilgan ish vaqtlari (daqiqa).
  int todayMinutes = 0;
  bool busy = false;

  /// Javobi kelmagan amalning kaliti: qayta bosilsa o'sha so'rov ketadi.
  ({bool clockIn, String key})? _lost;
  bool _disposed = false;

  /// Serverdagi holat (xato jim: bootstrap'dagi holat qoladi).
  Future<void> refresh() async {
    try {
      _apply(await data.staffClock.state());
    } on Object {
      // the bootstrap's state stays
    }
  }

  /// "Ishga keldim" ([clockIn] `true`) yoki "Ishdan ketdim". Natija — ekranga xabar (xato bo'lsa `error`).
  Future<({String text, bool error})> toggle({required bool clockIn}) async {
    if (busy) return (text: '', error: false);
    if (!data.connectivity.state.online) {
      return (
        text: "Ish vaqtini belgilash uchun internet kerak. Aloqa qaytgach qayta bosing.",
        error: true,
      );
    }
    busy = true;
    _notify();
    final String? key = _lost?.clockIn == clockIn ? _lost!.key : null;
    try {
      final PosClockState next = clockIn
          ? await data.staffClock.clockIn(idempotencyKey: key)
          : await data.staffClock.clockOut(idempotencyKey: key);
      _lost = null;
      _apply(next);
      return clockIn
          ? (text: 'Ishga kelganingiz belgilandi.', error: false)
          : (text: 'Ishdan ketganingiz belgilandi.', error: false);
    } on PosNetworkException catch (e) {
      if (e.idempotencyKey != null) {
        _lost = (clockIn: clockIn, key: e.idempotencyKey!);
      }
      return (
        text: "Ish vaqtini belgilash uchun internet kerak. Aloqa qaytgach qayta bosing.",
        error: true,
      );
    } on PosConflict catch (e) {
      // already_clocked_in / not_clocked_in: another device did it; show what the server has
      _lost = null;
      await refresh();
      return (text: e.message, error: true);
    } on Object catch (e) {
      _lost = null;
      return (text: describeError(e), error: true);
    } finally {
      busy = false;
      _notify();
    }
  }

  void _apply(PosClockState s) {
    clockedIn = s.clockedIn;
    since = s.session?.clockInAt;
    todayMinutes = s.todayMinutes;
    _notify();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }
}
