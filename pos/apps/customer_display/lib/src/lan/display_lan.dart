import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// P6 lokal ko'prik (docs/POS_SPEC.md 5.5) mijoz ekranida: internet uzilsa, chek (`cart.display`) va raqamlar
/// (`queue.ready|called|handed`, `order.changed`) Wi-Fi orqali filialning markaz kassasidan keladi ([LanLink]).
///
/// * Kalitlar — terminal tokeni bilan `GET /pos/lan` (xodim yo'q, grant ham yo'q: ekran faqat hodisalarni oladi, amal
///   yubormaydi). Qurilmaning xavfsiz xotirasida saqlanadi ([store]): ekran internetsiz qayta ochilsa ham kassaga
///   ulanadi. Kalit yangilansa (`key_mismatch`) internet qaytgach yangisi olinadi.
/// * Server javob bermasa ([reportCloud], [DisplayController] aytadi) markazga ulanadi: avval qo'lda kiritilgan manzil,
///   keyin beacon, keyin shu kompyuterning o'zi (`127.0.0.1` — Windows'da kassaning ikkinchi monitori).
/// * Qo'lda kiritilgan manzil (IP yoki `rizqim-lan://` havolasi) ham [store] da.
class DisplayLan extends ChangeNotifier {
  DisplayLan({required this.pos, LanLink? link, this.store})
    : link =
          link ??
          LanLink(
            app: LanApp.display,
            probeCloud: () => lanProbeServer(pos.http),
            multicast: _HardwareLock(WifiMulticastLock()),
          );

  final PosData pos;
  final LanLink link;
  final SecureKeyValueStore? store;

  static const String sectionKey = 'pos_lan.display_section';
  static const String manualKey = 'pos_lan.manual_endpoint';

  StreamSubscription<void>? _sub;
  bool _fetched = false;
  bool _fetching = false;
  bool _disposed = false;

  LanMode get mode => link.mode;

  /// Chek va raqamlar markaz orqali.
  bool get viaHub => link.viaHub;

  /// Markaz kassaning qurilma uuid'i (ekran faqat shu kassaning chekini oladi).
  String? get hubDevice => link.credentials?.hubDevice?.toLowerCase();

  Stream<LanEvent> get events => link.events;

  Future<void> start() async {
    _sub = link.changes.listen((_) {
      if (!_disposed) notifyListeners();
      // A rotated key: the hub says so; the next time the server answers, the new one is fetched
      if (link.lastReason == 'key_mismatch' ||
          link.lastReason == 'unknown_device') {
        _fetched = false;
      }
    });
    link.start();
    try {
      final String? manual = await store?.read(manualKey);
      if (manual != null && !_disposed) link.setManual(manual);
      final String? kept = await store?.read(sectionKey);
      if (kept != null && !_disposed && link.credentials == null) {
        final Object? json = jsonDecode(kept);
        if (json is Map) {
          await link.setCredentials(
            LanCredentials.fromJson(json.cast<String, Object?>()),
          );
        }
      }
    } on Object {
      // Nothing kept (or unreadable): the server gives them
    }
    await _fetch();
  }

  /// [DisplayController]: the server answered ([reachable]) or not. Also fetches the keys once the server is back.
  void reportCloud(bool reachable) {
    if (_disposed) return;
    link.reportCloud(reachable);
    if (reachable && !_fetched) unawaited(_fetch());
  }

  Future<void> _fetch() async {
    if (_fetching || _disposed) return;
    _fetching = true;
    try {
      final Map<String, Object?>? section = await pos.display.lan();
      if (_disposed) return;
      _fetched = true;
      await link.setCredentials(
        section == null ? null : LanCredentials.fromJson(section),
      );
      try {
        if (section == null) {
          await store?.delete(sectionKey);
        } else {
          await store?.write(sectionKey, jsonEncode(section));
        }
      } on Object {
        // Kept for this run only
      }
    } on PosNetworkException {
      // No server now: the kept keys (if any) work; asked again when it answers
    } on PosException {
      // Refused (an old server, a store-wide terminal): no LAN for this screen
      _fetched = true;
    } finally {
      _fetching = false;
    }
  }

  /// Sozlamalardagi "Markaz manzili": xato matni yoki `null`.
  Future<String?> setManual(String? text) async {
    final String? error = link.setManual(text);
    if (error != null) return error;
    try {
      final String t = (text ?? '').trim();
      if (t.isEmpty) {
        await store?.delete(manualKey);
      } else {
        await store?.write(manualKey, t);
      }
    } on Object {
      // Kept for this run only
    }
    return null;
  }

  @override
  void dispose() {
    _disposed = true;
    unawaited(_sub?.cancel());
    unawaited(link.dispose());
    super.dispose();
  }
}

class _HardwareLock implements LanMulticastLock {
  _HardwareLock(this.lock);

  final WifiMulticastLock lock;

  @override
  Future<void> acquire() => lock.acquire();

  @override
  Future<void> release() => lock.release();
}
