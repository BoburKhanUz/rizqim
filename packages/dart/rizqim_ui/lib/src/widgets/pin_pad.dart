import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/rz_colors.dart';
import '../theme/rz_theme.dart';

/// Terilgan PIN raqamlari soni: to'ldirilgan va bo'sh doirachalar. Raqamlarning o'zi ko'rsatilmaydi.
class RzPinDots extends StatelessWidget {
  const RzPinDots({
    super.key,
    required this.filled,
    required this.length,
    this.error = false,
  });

  final int filled;

  /// Nechta doiracha (eng ko'p raqam soni).
  final int length;

  /// Xato: doirachalar qizil chegara bilan.
  final bool error;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final Color on = error ? c.danger : c.brand;
    return Semantics(
      label: 'PIN: $filled ta raqam terildi',
      excludeSemantics: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          for (var i = 0; i < length; i++)
            Container(
              width: 20,
              height: 20,
              margin: const EdgeInsets.symmetric(horizontal: 8),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: i < filled ? on : Colors.transparent,
                border: Border.all(
                  color: error ? c.danger : c.fgMuted,
                  width: 2,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Raqamli klaviatura: 1–9, 0, o'chirish va tasdiqlash. Har bir tugma kamida [keySize] (standart 72px).
class RzPinPad extends StatelessWidget {
  const RzPinPad({
    super.key,
    required this.onDigit,
    required this.onBackspace,
    this.onSubmit,
    this.enabled = true,
    this.keySize = 72,
    this.submitLabel = 'Kirish',
    this.backspaceLabel = "O'chirish",
  });

  final ValueChanged<String> onDigit;
  final VoidCallback onBackspace;

  /// `null` bo'lsa tasdiqlash tugmasi o'chirilgan (hali yetarli raqam yo'q).
  final VoidCallback? onSubmit;
  final bool enabled;
  final double keySize;
  final String submitLabel;
  final String backspaceLabel;

  @override
  Widget build(BuildContext context) {
    Widget digit(String d) => _PadKey(
      size: keySize,
      label: d,
      onPressed: enabled ? () => onDigit(d) : null,
      child: Text(d),
    );

    Widget row(List<Widget> keys) => Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        for (final Widget k in keys)
          Padding(padding: const EdgeInsets.all(6), child: k),
      ],
    );

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        row(<Widget>[digit('1'), digit('2'), digit('3')]),
        row(<Widget>[digit('4'), digit('5'), digit('6')]),
        row(<Widget>[digit('7'), digit('8'), digit('9')]),
        row(<Widget>[
          _PadKey(
            size: keySize,
            label: backspaceLabel,
            onPressed: enabled ? onBackspace : null,
            child: const Icon(Icons.backspace_outlined),
          ),
          digit('0'),
          _PadKey(
            size: keySize,
            label: submitLabel,
            primary: true,
            onPressed: enabled ? onSubmit : null,
            child: const Icon(Icons.check_rounded),
          ),
        ]),
      ],
    );
  }
}

class _PadKey extends StatelessWidget {
  const _PadKey({
    required this.size,
    required this.label,
    required this.onPressed,
    required this.child,
    this.primary = false,
  });

  final double size;
  final String label;
  final VoidCallback? onPressed;
  final Widget child;
  final bool primary;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    // Raqam tugmalari fondan aniq ajralib tursin (surface ham, surface2 ham ustida)
    final Color bg = primary
        ? c.brand
        : Color.alphaBlend(c.fg.withValues(alpha: 0.08), c.surface);
    final Color fg = primary ? c.onBrand : c.fg;
    return Semantics(
      button: true,
      label: label,
      enabled: onPressed != null,
      excludeSemantics: true,
      child: SizedBox.square(
        dimension: size,
        child: FilledButton(
          onPressed: onPressed,
          style: FilledButton.styleFrom(
            backgroundColor: bg,
            foregroundColor: fg,
            disabledBackgroundColor: c.fgSubtle.withValues(alpha: 0.18),
            disabledForegroundColor: c.fgSubtle,
            padding: EdgeInsets.zero,
            minimumSize: Size.square(size),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(RzRadius.card),
              side: primary ? BorderSide.none : BorderSide(color: c.line),
            ),
            textStyle: RzType.display,
            iconSize: 30,
          ),
          child: child,
        ),
      ),
    );
  }
}

/// PIN terish: doirachalar + klaviatura + jismoniy klaviatura (raqamlar, Backspace, Enter).
///
/// [maxLength] raqam terilsa o'zi yuboriladi; undan kam bo'lsa (kamida [minLength]) tasdiqlash tugmasi bilan.
/// [onSubmit] `false` qaytarsa (PIN rad etildi) terilgan raqamlar tozalanadi.
class RzPinEntry extends StatefulWidget {
  const RzPinEntry({
    super.key,
    required this.onSubmit,
    this.minLength = 4,
    this.maxLength = 6,
    this.enabled = true,
    this.error = false,
    this.keySize = 72,
    this.onChanged,
    this.autofocus = true,
  });

  final Future<bool> Function(String pin) onSubmit;
  final int minLength;
  final int maxLength;
  final bool enabled;

  /// Oxirgi urinish xato bo'ldi (doirachalar qizil).
  final bool error;
  final double keySize;

  /// Raqam terilganda yoki o'chirilganda (masalan, xato matnini yashirish uchun).
  final VoidCallback? onChanged;
  final bool autofocus;

  @override
  State<RzPinEntry> createState() => _RzPinEntryState();
}

class _RzPinEntryState extends State<RzPinEntry> {
  String _pin = '';
  bool _busy = false;

  bool get _active => widget.enabled && !_busy;

  void _digit(String d) {
    if (!_active || _pin.length >= widget.maxLength) return;
    setState(() => _pin += d);
    widget.onChanged?.call();
    if (_pin.length == widget.maxLength) unawaited(_submit());
  }

  void _backspace() {
    if (!_active || _pin.isEmpty) return;
    setState(() => _pin = _pin.substring(0, _pin.length - 1));
    widget.onChanged?.call();
  }

  Future<void> _submit() async {
    if (!_active || _pin.length < widget.minLength) return;
    setState(() => _busy = true);
    final bool ok = await widget.onSubmit(_pin);
    if (!mounted) return;
    setState(() {
      _busy = false;
      if (!ok) _pin = '';
    });
  }

  KeyEventResult _onKey(FocusNode node, KeyEvent event) {
    if (event is! KeyDownEvent && event is! KeyRepeatEvent) {
      return KeyEventResult.ignored;
    }
    final LogicalKeyboardKey key = event.logicalKey;
    if (key == LogicalKeyboardKey.backspace) {
      _backspace();
      return KeyEventResult.handled;
    }
    if (key == LogicalKeyboardKey.enter ||
        key == LogicalKeyboardKey.numpadEnter) {
      unawaited(_submit());
      return KeyEventResult.handled;
    }
    final String? ch = event.character;
    if (ch != null && ch.length == 1 && '0123456789'.contains(ch)) {
      _digit(ch);
      return KeyEventResult.handled;
    }
    return KeyEventResult.ignored;
  }

  @override
  Widget build(BuildContext context) {
    return Focus(
      autofocus: widget.autofocus,
      onKeyEvent: _onKey,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          SizedBox(
            height: 36,
            child: Center(
              child: _busy
                  ? const SizedBox.square(
                      dimension: 28,
                      child: CircularProgressIndicator(strokeWidth: 3),
                    )
                  : RzPinDots(
                      filled: _pin.length,
                      length: widget.maxLength,
                      error: widget.error && _pin.isEmpty,
                    ),
            ),
          ),
          const SizedBox(height: 20),
          RzPinPad(
            keySize: widget.keySize,
            enabled: _active,
            onDigit: _digit,
            onBackspace: _backspace,
            onSubmit: _pin.length >= widget.minLength
                ? () => unawaited(_submit())
                : null,
          ),
        ],
      ),
    );
  }
}
