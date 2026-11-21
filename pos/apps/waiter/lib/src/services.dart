import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart' show openLink;
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_api/rizqim_api.dart';

import 'app_config.dart';
import 'storage/update_notice_prefs.dart';

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
    this.lanEnabled = true,
    this.lanLinkFactory,
    this.lanStore,
    this.releases,
    this.linkOpener = _openLink,
    SecureKeyValueStore? prefsStore,
  }) : data = data ?? PosData(client, clock: clock),
       updatePrefs = UpdateNoticePrefs(prefsStore);

  final RizqimClient client;

  /// POS ma'lumot qatlami: sessiya, tasdiq, repozitoriylar.
  final PosData data;
  final RealtimeFactory? realtimeFactory;
  final DateTime Function()? clock;
  final Duration pollInterval;
  final Duration livePollInterval;

  /// P6 lokal ko'prik: internet uzilsa markaz kassa orqali ishlash (bootstrap'da `lan` bo'lsa).
  final bool lanEnabled;

  /// Testlar uchun: ko'prikni boshqa vaqtlar yoki topish bilan yaratish.
  final LanLink Function(PosData data)? lanLinkFactory;

  /// Qo'lda kiritilgan markaz manzili saqlanadigan joy (`null` — faqat shu ishga tushish).
  final SecureKeyValueStore? lanStore;

  /// M5 S5: versiya xabari ([PosReleaseRepository]). Ulangandan keyin [TerminalHome] ishga tushiradi va terminal
  /// uzilganda to'xtatadi; testlarda soxtasi beriladi (shunda [TerminalHome] unga tegmaydi).
  PosReleaseRepository? releases;

  /// M5 S5: yuklab olish havolasini ochadi ("Yangilash", "Yuklab olish"); testlarda almashtiriladi.
  Future<bool> Function(Uri url) linkOpener;

  static Future<bool> _openLink(Uri url) => openLink(url);

  /// M5 S5: "Yangi versiya" lentasi qaysi kuni yopilgani (qurilmada saqlanadi).
  final UpdateNoticePrefs updatePrefs;

  /// M5 S5: ilova ishga tushganda to'liq xabar ko'rsatildimi (bir ishga tushirishda bir marta).
  bool startNoticeShown = false;

  /// M5 S5: to'liq xabar dialogi hozir ochiq.
  bool noticeOpen = false;

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
