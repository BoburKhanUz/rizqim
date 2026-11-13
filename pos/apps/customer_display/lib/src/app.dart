import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_lan/pos_lan.dart';
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
    if (terminal == null) return PairingScreen(auth: _auth);
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
  late final PosRealtime? _realtime;
  late final DisplayController _controller;
  DisplayLan? _lan;

  @override
  void initState() {
    super.initState();
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
  }

  @override
  void dispose() {
    _controller.dispose();
    _lan?.dispose();
    unawaited(_realtime?.dispose());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => DisplayScreen(controller: _controller);
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
