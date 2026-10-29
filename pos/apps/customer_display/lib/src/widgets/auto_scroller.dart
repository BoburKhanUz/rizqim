import 'dart:async';

import 'package:flutter/material.dart';

/// Sig'may qolgan ro'yxatni o'zi sekin pastga suradi, oxirida to'xtab, boshiga qaytadi (televizor: hech kim tegmaydi).
/// Hammasi sig'sa — siljimaydi va taymer ishlamaydi.
class AutoScroller extends StatefulWidget {
  const AutoScroller({
    super.key,
    required this.child,
    this.pixelsPerSecond = 36,
    this.pause = const Duration(seconds: 4),
    this.padding = EdgeInsets.zero,
  });

  final Widget child;
  final double pixelsPerSecond;

  /// Boshida va oxirida to'xtash.
  final Duration pause;
  final EdgeInsetsGeometry padding;

  @override
  State<AutoScroller> createState() => _AutoScrollerState();
}

class _AutoScrollerState extends State<AutoScroller> {
  static const Duration _step = Duration(milliseconds: 40);

  final ScrollController _controller = ScrollController();
  Timer? _timer;
  Duration _paused = Duration.zero;
  bool _wrapping = false;

  @override
  void initState() {
    super.initState();
    _paused = widget.pause;
    _timer = Timer.periodic(_step, (_) => _tick());
  }

  void _tick() {
    if (!mounted || !_controller.hasClients) return;
    final ScrollPosition p = _controller.position;
    final double max = p.maxScrollExtent;
    if (max <= 0) return; // hammasi sig'di
    if (_paused > Duration.zero) {
      _paused -= _step;
      return;
    }
    if (_wrapping) {
      _wrapping = false;
      _controller.jumpTo(0);
      _paused = widget.pause;
      return;
    }
    final double next =
        p.pixels + widget.pixelsPerSecond * _step.inMilliseconds / 1000;
    if (next >= max) {
      _controller.jumpTo(max);
      _wrapping = true;
      _paused = widget.pause;
    } else {
      _controller.jumpTo(next);
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    controller: _controller,
    physics: const NeverScrollableScrollPhysics(),
    padding: widget.padding,
    child: widget.child,
  );
}
