import 'dart:async';

import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'app_config.dart';
import 'printing/printing_service.dart';
import 'storage/shell_settings.dart';

/// Ulangan terminal uchun real-time (kalit sozlanmagan yoki terminal filialsiz bo'lsa `null`).
typedef RealtimeFactory = PosRealtime? Function(
  RizqimClient client,
  PairedTerminal terminal,
);

/// Ilovaning tashqi bog'liqliklari (DI): testlarda soxtalari beriladi. Ekranlar ularni `PosScope.of(context)` dan
/// oladi.
class CashierServices {
  CashierServices({
    required this.client,
    required this.settings,
    PosData? data,
    CashierPrinting? printing,
    this.realtimeFactory,
    this.clock,
    this.pollInterval = AppConfig.pollInterval,
    this.livePollInterval = AppConfig.livePollInterval,
    SyncStore? offlineStore,
  }) : data = data ?? PosData(client, clock: clock, offlineStore: offlineStore),
       printing = printing ?? CashierPrinting();

  final RizqimClient client;

  /// POS ma'lumot qatlami: sessiya, tasdiq, repozitoriylar.
  final PosData data;
  final ShellSettingsController settings;
  final CashierPrinting printing;
  final RealtimeFactory? realtimeFactory;
  final DateTime Function()? clock;

  /// Real-time jonli bo'lmaganda ro'yxatlar shu oraliqda yangilanadi ([PosRefreshScheduler]).
  final Duration pollInterval;
  final Duration livePollInterval;

  AuthSession get auth => client.auth;
  PosSession get session => data.session;

  /// P3-31: ekran qulfi internetsiz rejimda to'xtaydi — PIN faqat serverda tekshiriladi, qulflangan kassaga uzilish
  /// paytida hech kim qayta kira olmaydi (docs/POS_SPEC.md 5.4).
  Duration? idleTimeout() =>
      data.offline?.active ?? false ? null : session.idleTimeout();

  /// Ekran qulfi muddati o'zgarishi mumkin bo'lgan paytlar (bootstrap, internetsiz rejimga kirish va chiqish).
  Stream<Object?> get idleChanges {
    final PosOffline? offline = data.offline;
    if (offline == null) return session.changes;
    late final StreamController<Object?> out;
    final List<StreamSubscription<Object?>> subs =
        <StreamSubscription<Object?>>[];
    bool? active;
    out = StreamController<Object?>.broadcast(
      onListen: () {
        subs
          ..add(session.changes.listen(out.add))
          ..add(
            offline.changes.listen((OfflineStatus s) {
              if (s.active != active) {
                active = s.active;
                out.add(s);
              }
            }),
          );
      },
      onCancel: () async {
        for (final StreamSubscription<Object?> s in subs) {
          await s.cancel();
        }
        subs.clear();
      },
    );
    return out.stream;
  }

  DateTime now() => (clock ?? DateTime.now)();
}

/// Ilova sozlamalaridan real-time ([AppConfig.reverbKey] bo'sh bo'lsa `null`).
PosRealtime? defaultRealtime(RizqimClient client, PairedTerminal terminal) =>
    PosRealtime.create(
      client: client,
      terminal: terminal,
      apiBaseUrl: AppConfig.apiBaseUrl,
      appKey: AppConfig.reverbKey,
      reverbUrl: AppConfig.reverbUrl,
      origin: AppConfig.reverbOrigin,
      clientName: 'rizqim-cashier',
    );
