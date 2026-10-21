import 'dart:async';

import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'app_config.dart';
import 'board/kitchen_board_controller.dart';
import 'realtime/branch_realtime.dart';
import 'screens/kitchen_board_screen.dart';
import 'screens/pairing_screen.dart';
import 'screens/staff_sign_in_screen.dart';
import 'settings/kds_settings.dart';
import 'sound/ticket_sound.dart';

/// Ulangan terminal uchun real-time (kalit sozlanmagan yoki terminal filialsiz bo'lsa `null`).
typedef RealtimeFactory = BranchRealtime? Function(
  RizqimClient client,
  PairedTerminal terminal,
);

/// Ilovaning tashqi bog'liqliklari (testlarda soxtalari beriladi).
class KdsServices {
  KdsServices({
    required this.client,
    required this.settings,
    TicketSound? sound,
    this.realtimeFactory,
    this.clock,
    this.pollInterval = AppConfig.pollInterval,
    this.livePollInterval = AppConfig.livePollInterval,
  }) : sound = sound ?? SilentTicketSound();

  final RizqimClient client;
  final KdsSettingsController settings;
  final TicketSound sound;
  final RealtimeFactory? realtimeFactory;
  final DateTime Function()? clock;
  final Duration pollInterval;
  final Duration livePollInterval;
}

/// Oshxona ekrani ilovasi: ulanmagan — ulash, ulangan — PIN, PIN bilan kirilgan — doska. Standart mavzu qorong'i.
class KdsApp extends StatefulWidget {
  const KdsApp({super.key, required this.services});

  final KdsServices services;

  @override
  State<KdsApp> createState() => _KdsAppState();
}

class _KdsAppState extends State<KdsApp> {
  final GlobalKey<NavigatorState> _navigator = GlobalKey<NavigatorState>();

  @override
  Widget build(BuildContext context) {
    final KdsServices services = widget.services;
    return ValueListenableBuilder<KdsSettings>(
      valueListenable: services.settings,
      builder: (BuildContext context, KdsSettings s, _) => MaterialApp(
        title: 'Oshxona ekrani',
        debugShowCheckedModeBanner: false,
        navigatorKey: _navigator,
        theme: kdsTheme(RzTheme.light()),
        darkTheme: kdsTheme(RzTheme.dark()),
        themeMode: s.dark ? ThemeMode.dark : ThemeMode.light,
        // Navigator'dan yuqorida: dialoglardagi teginish ham hisoblanadi, chiqishda dialoglar yopiladi
        builder: (BuildContext context, Widget? child) => StaffIdleScope(
          auth: services.client.auth,
          navigator: _navigator,
          idleTimeout: s.idleTimeout,
          child: child ?? const SizedBox.shrink(),
        ),
        home: AuthGate(services: services),
      ),
    );
  }
}

/// Xodim kirgan paytda harakatsizlikda avtomatik chiqaradi ([RzIdleDetector] butun ilova ustida, dialoglar ham ichida)
/// va xodim chiqqanda (qo'lda, harakatsizlik, server sessiyani yopdi) yoki terminal uzilganda ochiq dialoglarni yopadi:
/// PIN ekrani ustida oldingi xodimning oynasi qolmasin.
class StaffIdleScope extends StatefulWidget {
  const StaffIdleScope({
    super.key,
    required this.auth,
    required this.navigator,
    required this.idleTimeout,
    required this.child,
  });

  final AuthSession auth;
  final GlobalKey<NavigatorState> navigator;
  final Duration? idleTimeout;
  final Widget child;

  @override
  State<StaffIdleScope> createState() => _StaffIdleScopeState();
}

class _StaffIdleScopeState extends State<StaffIdleScope> {
  late StreamSubscription<AuthEvent> _subscription;

  @override
  void initState() {
    super.initState();
    _subscription = widget.auth.events.listen(_onAuth);
  }

  @override
  void didUpdateWidget(StaffIdleScope oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.auth, widget.auth)) {
      unawaited(_subscription.cancel());
      _subscription = widget.auth.events.listen(_onAuth);
    }
  }

  @override
  void dispose() {
    unawaited(_subscription.cancel());
    super.dispose();
  }

  void _onAuth(AuthEvent e) {
    switch (e.kind) {
      case AuthEventKind.staffSignedOut ||
          AuthEventKind.terminalRevoked ||
          AuthEventKind.terminalForgotten:
        widget.navigator.currentState?.popUntil(
          (Route<dynamic> r) => r.isFirst,
        );
      case AuthEventKind.staffSignedIn:
        break;
      default:
        return;
    }
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final bool signedIn = widget.auth.staff != null;
    return RzIdleDetector(
      timeout: signedIn ? widget.idleTimeout : null,
      onIdle: () => unawaited(widget.auth.signOutStaff(reason: 'idle')),
      child: widget.child,
    );
  }
}

/// Rizqim mavzusi oshxona uchun: ikonka tugmalari ham kamida 48px (barmoq, qo'lqop).
ThemeData kdsTheme(ThemeData base) => base.copyWith(
  iconButtonTheme: IconButtonThemeData(
    style: IconButton.styleFrom(minimumSize: const Size.square(48)),
  ),
);

/// [AuthSession] holatiga qarab ekranni almashtiradi (ulandi, server terminalni o'chirdi, "uzish" bosildi).
class AuthGate extends StatefulWidget {
  const AuthGate({super.key, required this.services});

  final KdsServices services;

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

/// Ulangan terminal: real-time shu yerda yashaydi (PIN sessiyasidan mustaqil), xodim kirsa doska, chiqsa PIN ekrani.
class TerminalHome extends StatefulWidget {
  const TerminalHome({
    super.key,
    required this.services,
    required this.terminal,
  });

  final KdsServices services;
  final PairedTerminal terminal;

  @override
  State<TerminalHome> createState() => _TerminalHomeState();
}

class _TerminalHomeState extends State<TerminalHome> {
  BranchRealtime? _realtime;
  KitchenBoardController? _board;
  late final StreamSubscription<AuthEvent> _authSub;
  StreamSubscription<RealtimeEvent>? _eventSub;
  String? _notice;

  /// Hech kim kirmagan paytda oshxonaga kelgan buyurtmalar.
  final ValueNotifier<int> _waiting = ValueNotifier<int>(0);
  final Set<String> _waitingUuids = <String>{};

  KdsServices get _s => widget.services;
  AuthSession get _auth => _s.client.auth;

  @override
  void initState() {
    super.initState();
    _realtime = _s.realtimeFactory?.call(_s.client, widget.terminal)?..start();
    _eventSub = _realtime?.events.listen(_onLockedEvent);
    _authSub = _auth.events.listen(_onAuth);
    if (_auth.staff != null) _openBoard();
  }

  @override
  void dispose() {
    unawaited(_authSub.cancel());
    unawaited(_eventSub?.cancel());
    _board?.dispose();
    unawaited(_realtime?.dispose());
    _waiting.dispose();
    super.dispose();
  }

  void _onAuth(AuthEvent e) {
    switch (e.kind) {
      case AuthEventKind.staffSignedIn:
        _notice = null;
        _waitingUuids.clear();
        _waiting.value = 0;
        _openBoard();
      case AuthEventKind.staffSignedOut:
        _board?.dispose();
        _board = null;
        _notice = switch (e.reason) {
          'idle' => "Ekranga uzoq tegilmagani uchun chiqildi. Davom etish uchun PIN tering.",
          'expired' ||
          'unauthorized' => 'Sessiya tugadi. PIN bilan qayta kiring.',
          _ => null,
        };
      default:
        return;
    }
    if (mounted) setState(() {});
  }

  void _openBoard() {
    final StaffSession? staff = _auth.staff;
    if (staff == null) return;
    _board?.dispose();
    _board = KitchenBoardController(
      service: _s.client.kitchen,
      settings: _s.settings,
      sound: _s.sound,
      realtime: _realtime,
      branchId: widget.terminal.branch?.id ?? widget.terminal.device.branchId,
      canManage: staff.can('kitchen.manage'),
      clock: _s.clock,
      pollInterval: _s.pollInterval,
      livePollInterval: _s.livePollInterval,
    )..start();
  }

  /// PIN ekranida (doska yopiq) oshxonaga yangi buyurtma kelsa ovoz chalinadi va son ko'rsatiladi. Hodisada faqat
  /// uuid va holat bor; "oshxonaga keldi" — holati `accepted` bo'lgan yangi buyurtma.
  void _onLockedEvent(RealtimeEvent e) {
    if (_board != null) return;
    if (e.event != 'order.created' && e.event != 'order.status_changed') return;
    final Object? order = e.data['order'];
    if (order is! Map) return;
    if (order['status'] != 'accepted') return;
    final Object? uuid = order['uuid'];
    if (uuid is! String || !_waitingUuids.add(uuid)) return;
    _waiting.value = _waitingUuids.length;
    if (!_s.settings.value.muted) unawaited(_s.sound.play());
  }

  @override
  Widget build(BuildContext context) {
    final KitchenBoardController? board = _board;
    if (board != null && _auth.staff != null) {
      return KitchenBoardScreen(
        controller: board,
        auth: _auth,
        terminal: widget.terminal,
        settings: _s.settings,
        realtime: _realtime,
        clock: _s.clock,
      );
    }
    return StaffSignInScreen(
      auth: _auth,
      terminal: widget.terminal,
      realtime: _realtime,
      notice: _notice,
      waitingTickets: _waiting,
      clock: _s.clock,
    );
  }
}

/// Ilova sozlamalaridan real-time ([AppConfig.reverbKey] bo'sh bo'lsa `null`).
BranchRealtime? defaultRealtime(RizqimClient client, PairedTerminal terminal) =>
    BranchRealtime.create(
      client: client,
      terminal: terminal,
      apiBaseUrl: AppConfig.apiBaseUrl,
      appKey: AppConfig.reverbKey,
      reverbUrl: AppConfig.reverbUrl,
      origin: AppConfig.reverbOrigin,
    );
