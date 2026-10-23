import 'dart:async';

import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Ekran qulfi: xodim kirgan paytda [idleTimeout] davomida ekranga tegilmasa avtomatik chiqaradi ([RzIdleDetector]
/// butun ilova ustida — dialoglar ham ichida) va xodim chiqqanda (qo'lda, harakatsizlik, server sessiyani yopdi) yoki
/// terminal uzilganda ochiq dialog va sahifalarni yopadi: PIN ekrani ustida oldingi xodimning oynasi qolmasin.
///
/// `MaterialApp.builder` da, Navigator'dan yuqorida turadi.
class StaffIdleScope extends StatefulWidget {
  const StaffIdleScope({
    super.key,
    required this.auth,
    required this.navigator,
    required this.idleTimeout,
    required this.child,
    this.changes,
  });

  final AuthSession auth;
  final GlobalKey<NavigatorState> navigator;

  /// `null` — qulflanmaydi (filial sozlamasi `screen_lock_minutes: 0`).
  final Duration? Function() idleTimeout;
  final Widget child;

  /// [idleTimeout] o'zgarishi mumkin bo'lgan paytlar (bootstrap yuklandi: filialning `screen_lock_minutes`).
  final Stream<Object?>? changes;

  @override
  State<StaffIdleScope> createState() => _StaffIdleScopeState();
}

class _StaffIdleScopeState extends State<StaffIdleScope> {
  late StreamSubscription<AuthEvent> _subscription;
  StreamSubscription<Object?>? _changes;

  @override
  void initState() {
    super.initState();
    _subscription = widget.auth.events.listen(_onAuth);
    _changes = widget.changes?.listen((_) {
      if (mounted) setState(() {});
    });
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
    unawaited(_changes?.cancel());
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
      timeout: signedIn ? widget.idleTimeout() : null,
      onIdle: () => unawaited(widget.auth.signOutStaff(reason: 'idle')),
      child: widget.child,
    );
  }
}
