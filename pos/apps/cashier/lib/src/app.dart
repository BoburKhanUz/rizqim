import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'services.dart';
import 'shell/home_shell.dart';
import 'shell/idle_scope.dart';
import 'shell/pairing_screen.dart';
import 'shell/staff_sign_in_screen.dart';

/// Kassa ilovasi: ulanmagan — ulash, ulangan — PIN (ekran qulfi), PIN bilan kirilgan — bootstrap, so'ng ish ekrani
/// (zal, navbat, bronlar, nasiya, smena, sozlamalar). Mavzu — tizim, yorug' yoki qorong'i (xodim menyusida).
class CashierApp extends StatefulWidget {
  const CashierApp({super.key, required this.services});

  final CashierServices services;

  @override
  State<CashierApp> createState() => _CashierAppState();
}

class _CashierAppState extends State<CashierApp> {
  final GlobalKey<NavigatorState> _navigator = GlobalKey<NavigatorState>();

  @override
  Widget build(BuildContext context) {
    final CashierServices services = widget.services;
    return ValueListenableBuilder<ThemeMode>(
      valueListenable: services.settings,
      builder: (BuildContext context, ThemeMode mode, _) => MaterialApp(
        title: 'Kassa',
        debugShowCheckedModeBanner: false,
        navigatorKey: _navigator,
        theme: cashierTheme(RzTheme.light()),
        darkTheme: cashierTheme(RzTheme.dark()),
        themeMode: mode,
        // Navigator'dan yuqorida: dialoglardagi teginish ham hisoblanadi, chiqishda dialoglar yopiladi
        builder: (BuildContext context, Widget? child) => StaffIdleScope(
          auth: services.auth,
          navigator: _navigator,
          idleTimeout: services.idleTimeout,
          changes: services.idleChanges,
          child: child ?? const SizedBox.shrink(),
        ),
        home: AuthGate(services: services),
      ),
    );
  }
}

/// Rizqim mavzusi kassa uchun: ikonka tugmalari kamida 48px (barmoq bilan bosiladi).
ThemeData cashierTheme(ThemeData base) => base.copyWith(
  iconButtonTheme: IconButtonThemeData(
    style: IconButton.styleFrom(minimumSize: const Size.square(48)),
  ),
);

/// [AuthSession] holatiga qarab ekranni almashtiradi (ulandi, server terminalni o'chirdi, "uzish" bosildi).
class AuthGate extends StatefulWidget {
  const AuthGate({super.key, required this.services});

  final CashierServices services;

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  late final StreamSubscription<AuthEvent> _subscription;

  AuthSession get _auth => widget.services.auth;

  @override
  void initState() {
    super.initState();
    _subscription = _auth.events.listen((AuthEvent e) {
      if (e.kind == AuthEventKind.terminalPaired ||
          e.kind == AuthEventKind.terminalRevoked ||
          e.kind == AuthEventKind.terminalForgotten) {
        if (e.kind != AuthEventKind.terminalPaired) {
          widget.services.printing.clear();
          unawaited(widget.services.data.catalog.clear());
        }
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

/// Ulangan terminal: real-time shu yerda yashaydi (PIN sessiyasidan mustaqil), xodim kirsa bootstrap va ish ekrani,
/// chiqsa PIN ekrani.
class TerminalHome extends StatefulWidget {
  const TerminalHome({
    super.key,
    required this.services,
    required this.terminal,
  });

  final CashierServices services;
  final PairedTerminal terminal;

  @override
  State<TerminalHome> createState() => _TerminalHomeState();
}

class _TerminalHomeState extends State<TerminalHome> {
  PosRealtime? _realtime;
  late final StreamSubscription<AuthEvent> _authSub;
  late final StreamSubscription<PosSessionPhase> _phaseSub;
  String? _notice;
  PosException? _bootstrapError;
  bool _loading = false;

  CashierServices get _s => widget.services;
  PosSession get _session => _s.session;

  @override
  void initState() {
    super.initState();
    _realtime = _s.realtimeFactory?.call(_s.client, widget.terminal)?..start();
    _authSub = _s.auth.events.listen(_onAuth);
    _phaseSub = _session.changes.listen((_) {
      if (mounted) setState(() {});
    });
    if (_session.phase == PosSessionPhase.signedIn) unawaited(_loadBootstrap());
  }

  @override
  void dispose() {
    unawaited(_authSub.cancel());
    unawaited(_phaseSub.cancel());
    unawaited(_realtime?.dispose());
    super.dispose();
  }

  void _onAuth(AuthEvent e) {
    switch (e.kind) {
      case AuthEventKind.staffSignedIn:
        _notice = null;
        unawaited(_loadBootstrap());
      case AuthEventKind.staffSignedOut:
        _s.printing.clear();
        _bootstrapError = null;
        _notice = switch (e.reason) {
          'idle' => "Ekranga uzoq tegilmagani uchun ekran qulflandi. Davom etish uchun PIN tering.",
          'expired' ||
          'unauthorized' => 'Sessiya tugadi. PIN bilan qayta kiring.',
          _ => null,
        };
      default:
        return;
    }
    if (mounted) setState(() {});
  }

  /// `GET /pos/bootstrap`, so'ng printerlar va menyu (kesh) tayyorlanadi.
  Future<void> _loadBootstrap() async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _bootstrapError = null;
    });
    try {
      final PosBootstrap boot = await _session.loadBootstrap();
      _s.printing.configure(boot.printers);
      // Menyu fonda: ish ekrani uni kutmaydi (buyurtma muharriri o'zi kutadi)
      unawaited(
        _s.data.catalog
            .load(PosChannel.hall, menuVersion: boot.menuVersion)
            .then<void>((_) {}, onError: (Object _) {}),
      );
      // P3-31: the takeaway menu too, so the till can sell walk-ins through an outage
      if (_s.data.offline != null) {
        unawaited(
          _s.data.catalog
              .load(PosChannel.takeaway, menuVersion: boot.menuVersion)
              .then<void>((_) {}, onError: (Object _) {}),
        );
      }
    } on PosException catch (e) {
      _bootstrapError = e;
    } finally {
      _loading = false;
      if (mounted) setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    switch (_session.phase) {
      case PosSessionPhase.ready:
        return HomeShell(
          services: _s,
          terminal: widget.terminal,
          realtime: _realtime,
        );
      case PosSessionPhase.signedIn:
        return _BootstrapView(
          error: _bootstrapError,
          onRetry: () => unawaited(_loadBootstrap()),
          onSignOut: () => unawaited(_session.lock()),
        );
      case PosSessionPhase.locked || PosSessionPhase.unpaired:
        return StaffSignInScreen(
          auth: _s.auth,
          terminal: widget.terminal,
          realtime: _realtime,
          connectivity: _s.data.connectivity,
          notice: _notice,
          clock: _s.clock,
        );
    }
  }
}

/// PIN'dan keyin bootstrap yuklanmoqda (yoki xato: qayta urinish, chiqish).
class _BootstrapView extends StatelessWidget {
  const _BootstrapView({
    required this.error,
    required this.onRetry,
    required this.onSignOut,
  });

  final PosException? error;
  final VoidCallback onRetry;
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosException? e = error;
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: e == null
                ? Column(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      const CircularProgressIndicator(),
                      const SizedBox(height: 16),
                      Text(
                        'Kassa tayyorlanmoqda…',
                        style: RzType.headline.copyWith(color: c.fg),
                      ),
                    ],
                  )
                : Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      InlineError(
                        message: e.isNetworkError
                            ? "Aloqa yo'q: kassa sozlamalarini yuklab bo'lmadi."
                            : e.message,
                        onRetry: onRetry,
                      ),
                      const SizedBox(height: 12),
                      RzButton(
                        label: 'Chiqish',
                        icon: Icons.lock_outline_rounded,
                        variant: RzButtonVariant.ghost,
                        onPressed: onSignOut,
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }
}
