import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart' show KeyboardScanListener;
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
        // P6 hardware: a keyboard-wedge barcode scanner anywhere in the app (CashierHardware.scans)
        builder: (BuildContext context, Widget? child) => KeyboardScanListener(
          onScan: services.hardware.scans.addKeyboard,
          child: StaffIdleScope(
            auth: services.auth,
            navigator: _navigator,
            idleTimeout: services.idleTimeout,
            changes: services.idleChanges,
            child: child ?? const SizedBox.shrink(),
          ),
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
  final List<StreamSubscription<Object?>> _rtSubs =
      <StreamSubscription<Object?>>[];
  Timer? _printPoll;
  late DateTime _lanReadAt = _s.now();

  /// Review P6 S5: the hub reads `GET /pos/lan` again at least this often while online.
  static const Duration lanRefreshEvery = Duration(minutes: 5);
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
    _listenForTill();
    if (_session.phase == PosSessionPhase.signedIn) unawaited(_loadBootstrap());
  }

  /// Review P6 hardware/LAN (C2, S5): what the paired till does whoever is signed in, its screen locked too: the
  /// waiter's bill prints (`bill.print_requested`, and the server's list of the ones it missed: on start, after the
  /// subscription comes back, on unlock and every [CashierServices.billPrintPoll]); the hub reads its LAN keys again
  /// on `pos.lan_changed`. Printers come from the last bootstrap (kept while locked) or the till's stored copy.
  void _listenForTill() {
    if (_s.printing.configs.isEmpty) {
      final PosBootstrap? stored = _s.data.offline?.storedBootstrap();
      if (stored != null) _s.printing.configure(stored.printers);
    }
    final PosRealtime? rt = _realtime;
    if (rt != null) {
      _rtSubs
        ..add(
          rt.events.listen((PosEvent e) {
            if (e is PosBillPrintRequested) {
              unawaited(_s.billPrints.handle(e.request));
            }
            if (e is PosLanChanged) unawaited(_s.lan.refresh());
          }),
        )
        ..add(
          rt.resubscribed.listen((_) {
            unawaited(_s.billPrints.catchUp());
            unawaited(_s.lan.refresh());
          }),
        );
    }
    final Duration? every = _s.billPrintPoll;
    if (every != null) {
      _printPoll = Timer.periodic(every, (_) {
        unawaited(_s.billPrints.catchUp());
        // S5: the hub's device list and keys, every few minutes while online (a lost pos.lan_changed)
        final DateTime now = _s.now();
        if (now.difference(_lanReadAt) >= lanRefreshEvery &&
            (_s.data.offline?.connectivity.state.online ?? true)) {
          _lanReadAt = now;
          unawaited(_s.lan.refresh());
        }
      });
    }
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => unawaited(_s.billPrints.catchUp()),
    );
  }

  @override
  void dispose() {
    _printPoll?.cancel();
    for (final StreamSubscription<Object?> s in _rtSubs) {
      unawaited(s.cancel());
    }
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
        // Review P6 hardware/LAN (C2): the printers stay (cleared on unpair): a locked till still prints the bills
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
      // C2: what came while the screen was locked (or before this sign-in)
      unawaited(_s.billPrints.catchUp());
      // P6 hardware: the till's own second screen (Sunmi D2) shows the cart without the server
      if (_s.hardware.android) {
        unawaited(
          _s.hardware.startCustomerScreen(
            _s.data.displayFeed,
            storeName: boot.store.name,
          ),
        );
      }
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
