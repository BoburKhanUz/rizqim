import 'dart:async';

import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';

/// [timeout] davomida ekranga tegilmasa (sichqoncha, klaviatura ham) [onIdle] ni bir marta chaqiradi. Har bir
/// teginishda hisob qaytadan boshlanadi. Terminalda xodimni avtomatik chiqarish uchun.
///
/// [timeout] `null` yoki nol — o'chirilgan.
///
/// **Navigator'dan yuqorida qo'ying** (`MaterialApp.builder`): dialog va menyular alohida route'da ochiladi, sahifa ichidagi
/// detektor ularga tegishni sezmaydi (oshpaz sozlamalar oynasida ishlayotganda ham chiqarib yuborilardi). Chiqarilganda
/// ochiq dialoglarni ilova o'zi yopishi kerak (masalan, `navigatorKey.currentState?.popUntil((r) => r.isFirst)`).
class RzIdleDetector extends StatefulWidget {
  const RzIdleDetector({
    super.key,
    required this.timeout,
    required this.onIdle,
    required this.child,
  });

  final Duration? timeout;
  final VoidCallback onIdle;
  final Widget child;

  @override
  State<RzIdleDetector> createState() => _RzIdleDetectorState();
}

class _RzIdleDetectorState extends State<RzIdleDetector> {
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    HardwareKeyboard.instance.addHandler(_onKey);
    _arm();
  }

  @override
  void didUpdateWidget(RzIdleDetector oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.timeout != widget.timeout) _arm();
  }

  @override
  void dispose() {
    HardwareKeyboard.instance.removeHandler(_onKey);
    _timer?.cancel();
    super.dispose();
  }

  bool _onKey(KeyEvent event) {
    _arm();
    return false;
  }

  void _arm() {
    _timer?.cancel();
    final Duration? timeout = widget.timeout;
    if (timeout == null || timeout <= Duration.zero) return;
    _timer = Timer(timeout, () {
      if (mounted) widget.onIdle();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: (_) => _arm(),
      onPointerSignal: (_) => _arm(),
      child: widget.child,
    );
  }
}
