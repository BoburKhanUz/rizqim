import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// P6 lokal ko'prik (docs/POS_SPEC.md 5.5): filialning markaz kassasi bo'lsa, ilova ochiq turguncha ofitsiant, KDS va
/// mijoz ekrani uchun LAN serverini ([LanHub]) va beacon'ni yuritadi.
///
/// * Kalitlar bootstrap'ning `lan` qismidan; xavfsiz xotirada ([store]) saqlanadi — internetsiz qayta ochilganda ham
///   markaz ishlaydi. Oddiy oflayn nusxaga yozilmaydi.
/// * Bootstrap `lan: null` desa (markaz boshqa kassa, terminal filialdan chiqqan) yoki terminal uzilsa — to'xtaydi va
///   kalitlar o'chiriladi.
/// * Ekran uchun: [running], [peers] ("Lokal tarmoq: 3 qurilma"), [link] (IP/QR zaxirasi), [error].
class CashierLanHub extends ChangeNotifier {
  CashierLanHub({
    required this.session,
    required this.auth,
    required this.offline,
    this.store,
    this.hubFactory,
  });

  final PosSession session;
  final AuthSession auth;
  final PosOffline? offline;

  /// Xavfsiz xotira (`null`: faqat shu ishga tushish davomida).
  final SecureKeyValueStore? store;

  /// Testlar uchun: markazni boshqa manzil/port bilan yaratish.
  final LanHub Function(LanCredentials credentials, LanOpApplier applier)?
  hubFactory;

  static const String storageKey = 'pos_lan.credentials';

  LanHub? _hub;
  PosOfflineLanApplier? _applier;
  LanCredentials? _credentials;
  String? _error;
  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];
  Future<void> _work = Future<void>.value();
  bool _disposed = false;

  bool get running => _hub?.running ?? false;

  /// Ulangan qurilmalar.
  List<LanPeerInfo> get peers => _hub?.peers ?? const <LanPeerInfo>[];

  /// Shu kassa filialning markazi (kalitlari bor).
  bool get isHub => _credentials?.isHub ?? false;

  /// Qurilmalar qo'lda kiritadigan manzil (`rizqim-lan://192.168.1.20:18480?...`).
  String? get link => running ? _hub!.beacon().link : null;

  /// Server ishga tushmadi (port band va h.k.).
  String? get error => _error;

  /// Kuzatishni boshlaydi: saqlangan kalitlar bilan darhol, keyin har bootstrap'da yangilanadi.
  Future<void> start() async {
    _subs
      ..add(session.changes.listen((_) => _schedule(_fromBootstrap)))
      ..add(
        auth.events.listen((AuthEvent e) {
          if (e.kind == AuthEventKind.terminalForgotten ||
              e.kind == AuthEventKind.terminalRevoked) {
            _schedule(_forget);
          }
        }),
      );
    _schedule(() async {
      final LanCredentials? known = await _stored();
      if (known != null && _credentials == null) await _apply(known);
      await _fromBootstrap();
    });
    await _work;
  }

  void _schedule(Future<void> Function() step) {
    _work = _work
        .then((_) => _disposed ? null : step())
        .catchError((Object _) {});
  }

  Future<void> _fromBootstrap() async {
    final PosBootstrap? b = session.bootstrap;
    if (b == null || !b.raw.containsKey('lan')) return;
    final LanCredentials? creds = LanCredentials.fromBootstrap(b.raw);
    if (creds == null) {
      await _forget();
      return;
    }
    await _save(creds);
    await _apply(creds);
  }

  Future<void> _apply(LanCredentials creds) async {
    _credentials = creds;
    final PosOffline? o = offline;
    if (!creds.isHub || o == null) {
      await _stop();
      return;
    }
    final LanHub? hub = _hub;
    if (hub != null && hub.running && hub.credentials.port == creds.port) {
      hub.updateCredentials(creds);
      _notify();
      return;
    }
    await _stop();
    final PosOfflineLanApplier applier = PosOfflineLanApplier(offline: o);
    final LanHub next =
        hubFactory?.call(creds, applier) ??
        LanHub(credentials: creds, applier: applier);
    try {
      await next.start();
      _error = null;
    } on Object catch (e) {
      await applier.dispose();
      _error = "Lokal tarmoq ishga tushmadi (port ${creds.port}): $e";
      _notify();
      return;
    }
    _applier = applier;
    _hub = next;
    _subs.add(next.changes.listen((_) => notifyListeners()));
    _notify();
  }

  Future<void> _stop() async {
    final LanHub? hub = _hub;
    _hub = null;
    await hub?.dispose();
    await _applier?.dispose();
    _applier = null;
    _notify();
  }

  Future<void> _forget() async {
    _credentials = null;
    await _stop();
    try {
      await store?.delete(storageKey);
    } on Object {
      // Nothing stored
    }
  }

  Future<LanCredentials?> _stored() async {
    try {
      final String? text = await store?.read(storageKey);
      if (text == null) return null;
      final Object? json = jsonDecode(text);
      return json is Map
          ? LanCredentials.fromJson(json.cast<String, Object?>())
          : null;
    } on Object {
      return null;
    }
  }

  Future<void> _save(LanCredentials creds) async {
    try {
      await store?.write(storageKey, jsonEncode(creds.toJson()));
    } on Object {
      // Not kept: the hub still runs until the app closes
    }
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    for (final StreamSubscription<Object?> s in _subs) {
      unawaited(s.cancel());
    }
    _subs.clear();
    unawaited(_stop());
    super.dispose();
  }
}
