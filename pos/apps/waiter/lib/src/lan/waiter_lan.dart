import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// P6 lokal ko'prik (docs/POS_SPEC.md 5.5) ofitsiant telefonida: internet uzilsa, buyurtmalar Wi-Fi orqali filialning
/// markaz kassasiga ([LanLink], [LanPosRoute]).
///
/// * Kalitlar va grant — PIN bilan kirilgandagi bootstrap'ning `lan` qismidan (faqat xotirada: internetsiz PIN
///   tekshirilmaydi, yangi kirish baribir server bilan). Xodim chiqsa — ko'prik to'xtaydi.
/// * Server javob bermasa ([PosConnectivity]) markazga ulanadi: `lan` — zal va buyurtmalar markazdan, amallar
///   markaz navbatiga; `alone` — faqat ko'rish. Markaz navbati serverga yetgach odatdagi rejimga qaytadi.
/// * Qo'lda kiritilgan manzil (IP yoki `rizqim-lan://` havolasi) qurilmada saqlanadi ([store]).
class WaiterLan extends ChangeNotifier {
  WaiterLan({required this.data, LanLink? link, this.store})
    : link =
          link ??
          LanLink(
            app: LanApp.waiter,
            probeCloud: () => lanProbeServer(data.http),
            multicast: HardwareMulticastLock(WifiMulticastLock()),
          );

  final PosData data;
  final LanLink link;
  final SecureKeyValueStore? store;

  static const String manualKey = 'pos_lan.manual_endpoint';

  late final LanPosRoute _route = LanPosRoute(link);
  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];
  bool _viaHub = false;
  bool _disposed = false;

  LanMode get mode => link.mode;

  Future<void> start() async {
    data.lan = _route;
    _subs
      ..add(link.changes.listen((_) => _onLink()))
      ..add(
        data.connectivity.changes.listen(
          (PosConnectivityState s) => link.reportCloud(s.online),
        ),
      )
      ..add(data.session.changes.listen((_) => unawaited(_fromSession())));
    link.start();
    try {
      final String? manual = await store?.read(manualKey);
      if (manual != null && !_disposed) link.setManual(manual);
    } on Object {
      // Nothing kept
    }
    await _fromSession();
  }

  /// Kalitlar hozirgi PIN sessiyasining bootstrap'idan; sessiya yo'q — ko'prik to'xtaydi.
  Future<void> _fromSession() async {
    if (_disposed) return;
    final PosBootstrap? b = data.session.bootstrap;
    final bool signedIn = data.session.phase == PosSessionPhase.ready;
    await link.setCredentials(
      signedIn && b != null ? LanCredentials.fromBootstrap(b.raw) : null,
    );
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

  void _onLink() {
    final bool viaHub = link.viaHub;
    if (viaHub != _viaHub) {
      _viaHub = viaHub;
      data.connectivity.setLan(viaHub);
    }
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    for (final StreamSubscription<Object?> s in _subs) {
      unawaited(s.cancel());
    }
    _subs.clear();
    if (identical(data.lan, _route)) data.lan = null;
    if (_viaHub) data.connectivity.setLan(false);
    unawaited(_route.dispose());
    unawaited(link.dispose());
    super.dispose();
  }
}

/// [WifiMulticastLock] (pos_hardware) [LanLink] uchun.
class HardwareMulticastLock implements LanMulticastLock {
  HardwareMulticastLock(this.lock);

  final WifiMulticastLock lock;

  @override
  Future<void> acquire() => lock.acquire();

  @override
  Future<void> release() => lock.release();
}
