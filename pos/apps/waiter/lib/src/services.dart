import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'app_config.dart';

/// Ulangan telefon uchun real-time (kalit sozlanmagan yoki terminal filialsiz bo'lsa `null`).
typedef RealtimeFactory = PosRealtime? Function(
  RizqimClient client,
  PairedTerminal terminal,
);

/// Ilovaning tashqi bog'liqliklari (DI): testlarda soxtalari beriladi. Ekranlar ularni `WaiterScope.of(context)` dan
/// oladi.
class WaiterServices {
  WaiterServices({
    required this.client,
    PosData? data,
    this.realtimeFactory,
    this.clock,
    this.pollInterval = AppConfig.pollInterval,
    this.livePollInterval = AppConfig.livePollInterval,
  }) : data = data ?? PosData(client, clock: clock);

  final RizqimClient client;

  /// POS ma'lumot qatlami: sessiya, tasdiq, repozitoriylar.
  final PosData data;
  final RealtimeFactory? realtimeFactory;
  final DateTime Function()? clock;
  final Duration pollInterval;
  final Duration livePollInterval;

  AuthSession get auth => client.auth;
  PosSession get session => data.session;

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
      clientName: 'rizqim-waiter',
    );
