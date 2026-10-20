import 'dart:async';

import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'screens/kitchen_screen.dart';
import 'screens/pairing_screen.dart';

/// Oshxona ekrani ilovasi. Terminal ulanmagan bo'lsa ulash ekrani, ulangan bo'lsa oshxona ekrani.
class KdsApp extends StatelessWidget {
  const KdsApp({super.key, required this.client});

  final RizqimClient client;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Oshxona ekrani',
      debugShowCheckedModeBanner: false,
      theme: RzTheme.light(),
      darkTheme: RzTheme.dark(),
      home: AuthGate(auth: client.auth),
    );
  }
}

/// [AuthSession] holatiga qarab ekranni almashtiradi (ulandi, server terminalni o'chirdi, "uzish" bosildi).
class AuthGate extends StatefulWidget {
  const AuthGate({super.key, required this.auth});

  final AuthSession auth;

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  late final StreamSubscription<AuthEvent> _subscription;

  @override
  void initState() {
    super.initState();
    _subscription = widget.auth.events.listen((AuthEvent _) => setState(() {}));
  }

  @override
  void dispose() {
    unawaited(_subscription.cancel());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final PairedTerminal? terminal = widget.auth.terminal;
    if (terminal == null) return PairingScreen(auth: widget.auth);
    return KitchenScreen(auth: widget.auth, terminal: terminal);
  }
}
