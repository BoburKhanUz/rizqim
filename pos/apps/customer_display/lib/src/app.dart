import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart' show openLink;
import 'package:pos_lan/pos_lan.dart';
import 'package:pos_ui/pos_ui.dart' show PosUpdateMark;
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'app_config.dart';
import 'controller/display_controller.dart';
import 'lan/display_lan.dart';
import 'screens/display_screen.dart';
import 'screens/pairing_screen.dart';
import 'settings/display_settings.dart';
import 'sound/ready_sound.dart';

/// Ulangan terminal uchun real-time (kalit sozlanmagan yoki terminal filialsiz bo'lsa `null`).
typedef RealtimeFactory = PosRealtime? Function(
  RizqimClient client,
  PairedTerminal terminal,
);

/// Ilovaning tashqi bog'liqliklari (testlarda soxtalari beriladi).
class DisplayServices {
  DisplayServices({
    required this.client,
    required this.settings,
    ReadySound? sound,
    this.realtimeFactory,
    this.clock,
    this.pollInterval = AppConfig.pollInterval,
    this.livePollInterval = AppConfig.livePollInterval,
    this.paidHold = AppConfig.paidHold,
    this.staleCart = AppConfig.staleCart,
    this.announceHold = AppConfig.announceHold,
    this.lanEnabled = true,
    this.lanLinkFactory,
    this.lanStore,
    this.releases,
    this.linkOpener = _openLink,
  }) : sound = sound ?? SilentReadySound(),
       pos = PosData(client, clock: clock);

  final RizqimClient client;
  final DisplaySettingsController settings;
  final ReadySound sound;

  /// Terminal tokeni bilan so'rovlar ([PosDisplayRepository]); xodim sessiyasi kerak emas.
  final PosData pos;
  final RealtimeFactory? realtimeFactory;
  final DateTime Function()? clock;
  final Duration pollInterval;
  final Duration livePollInterval;
  final Duration paidHold;
  final Duration staleCart;
  final Duration announceHold;

  /// P6 lokal ko'prik: internet uzilsa chek va raqamlar markaz kassadan (`GET /pos/lan` kalitlari bilan).
  final bool lanEnabled;

  /// Testlar uchun: ko'prikni boshqa vaqtlar yoki topish bilan yaratish.
  final LanLink Function(PosData pos)? lanLinkFactory;

  /// LAN kalitlari va qo'lda kiritilgan markaz manzili saqlanadigan joy (`null` — faqat shu ishga tushish).
  final SecureKeyValueStore? lanStore;

  /// M5 S5: versiya xabari ([PosReleaseRepository]). Ulangandan keyin [TerminalHome] ishga tushiradi va terminal
  /// uzilganda to'xtatadi; testlar tayyor bergan bo'lsa, unga tegilmaydi.
  PosReleaseRepository? releases;

  /// M5 S5: yangi versiya havolasini ochadi (ulash ekranidagi "Yuklab olish"); testlarda almashtiriladi.
  Future<bool> Function(Uri url) linkOpener;

  static Future<bool> _openLink(Uri url) => openLink(url);
}

/// Mijoz ekrani ilovasi: ulanmagan — ulash, ulangan — ekran. Standart mavzu qorong'i.
class DisplayApp extends StatelessWidget {
  const DisplayApp({super.key, required this.services});

  final DisplayServices services;

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<DisplaySettings>(
      valueListenable: services.settings,
      builder: (BuildContext context, DisplaySettings s, _) => MaterialApp(
        title: 'Mijoz ekrani',
        debugShowCheckedModeBanner: false,
        theme: RzTheme.light(),
        darkTheme: RzTheme.dark(),
        themeMode: s.dark ? ThemeMode.dark : ThemeMode.light,
        builder: (BuildContext context, Widget? child) => MediaQuery(
          data: MediaQuery.of(context)
              .copyWith(textScaler: TextScaler.linear(s.fontScale)),
          child: child ?? const SizedBox.shrink(),
        ),
        home: AuthGate(services: services),
      ),
    );
  }
}

/// [AuthSession] holatiga qarab ekranni almashtiradi (ulandi, server terminalni o'chirdi).
class AuthGate extends StatefulWidget {
  const AuthGate({super.key, required this.services});

  final DisplayServices services;

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  late final StreamSubscription<AuthEvent> _subscription;

  AuthSession get _auth => widget.services.client.auth;

  @override
  void initState() {
    super.initState();
    _subscription = _auth.events.listen((AuthEvent e) {
      if (e.kind == AuthEventKind.terminalPaired ||
          e.kind == AuthEventKind.terminalRevoked ||
          e.kind == AuthEventKind.terminalForgotten) {
        setState(() {});
      }
    });
  }

  @override
  void dispose() {
    unawaited(_subscription.cancel());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final PairedTerminal? terminal = _auth.terminal;
    if (terminal == null) {
      return PairingScreen(auth: _auth, openUrl: widget.services.linkOpener);
    }
    return TerminalHome(
      key: ValueKey<String>(terminal.device.uuid),
      services: widget.services,
      terminal: terminal,
    );
  }
}

/// Ulangan terminal: real-time va ma'lumot shu yerda yashaydi.
class TerminalHome extends StatefulWidget {
  const TerminalHome({
    super.key,
    required this.services,
    required this.terminal,
  });

  final DisplayServices services;
  final PairedTerminal terminal;

  @override
  State<TerminalHome> createState() => _TerminalHomeState();
}

class _TerminalHomeState extends State<TerminalHome> {
  late final AppLifecycleListener _lifecycle;

  late final PosRealtime? _realtime;
  late final DisplayController _controller;
  DisplayLan? _lan;
  bool _ownsReleases = false;

  @override
  void initState() {
    super.initState();
    // M2: back in the foreground (Android resume, a Windows window focused again): every screen re-reads at once
    _lifecycle = AppLifecycleListener(onResume: AppResume.notify);
    final DisplayServices s = widget.services;
    _realtime = s.realtimeFactory?.call(s.client, widget.terminal);
    if (s.lanEnabled) {
      _lan = DisplayLan(
        pos: s.pos,
        link: s.lanLinkFactory?.call(s.pos),
        store: s.lanStore,
      );
      unawaited(_lan!.start());
    }
    _controller = DisplayController(
      repository: s.pos.display,
      settings: s.settings,
      sound: s.sound,
      realtime: _realtime,
      clock: s.clock,
      pollInterval: s.pollInterval,
      livePollInterval: s.livePollInterval,
      paidHold: s.paidHold,
      staleCart: s.staleCart,
      announceHold: s.announceHold,
      lan: _lan,
    )..start();
    // M5 S5: the update notice is read once the screen is paired (now and every 6 h); a corner mark only, never a gate
    if (s.releases == null) {
      _ownsReleases = true;
      s.releases = PosReleaseRepository.fromClient(s.client);
      unawaited(s.releases!.start());
    }
  }

  @override
  void dispose() {
    // Unpaired (or the app closed): the version reads stop
    if (_ownsReleases) {
      widget.services.releases?.dispose();
      widget.services.releases = null;
    }
    _controller.dispose();
    _lan?.dispose();
    unawaited(_realtime?.dispose());
    _lifecycle.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Stack(
    children: <Widget>[
      Positioned.fill(child: DisplayScreen(controller: _controller)),
      UpdateCornerMark(releases: widget.services.releases),
    ],
  );
}

/// M5 S5: the small corner mark when a newer version exists (red when this one is below the minimum). Only a mark:
/// it takes no taps, covers a few pixels in the bottom-left corner and shows nothing without a notice. Place it as a
/// child of a [Stack].
class UpdateCornerMark extends StatelessWidget {
  const UpdateCornerMark({super.key, required this.releases});

  final PosReleaseRepository? releases;

  @override
  Widget build(BuildContext context) {
    final PosReleaseRepository? repo = releases;
    return Positioned(
      left: 8,
      bottom: 8,
      child: repo == null
          ? const SizedBox.shrink()
          : StreamBuilder<PosAppRelease?>(
              stream: repo.changes,
              initialData: repo.release,
              builder: (BuildContext context, _) {
                final PosAppRelease? notice = repo.notice;
                if (notice == null) return const SizedBox.shrink();
                return IgnorePointer(child: PosUpdateMark(release: notice));
              },
            ),
    );
  }
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
      clientName: 'rizqim-display',
    );
