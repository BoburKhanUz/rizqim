import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Klaviaturaning pastki chap tugmasi.
enum PosKeypadExtraKey {
  none(''),

  /// "00".
  doubleZero('00'),

  /// "000": so'm summalari minglarda, tez terish uchun.
  tripleZero('000'),

  /// Kasr nuqtasi (kg, ulush).
  decimal('.');

  const PosKeypadExtraKey(this.key);
  final String key;
}

/// Terilgan son matni (`"125000"`, `"1.5"`): klaviatura tugmalari shu yerda qoidaga ko'ra qo'llanadi — bosh nol,
/// bitta nuqta, kasr raqamlar soni, butun qism uzunligi.
class PosKeypadController extends ValueNotifier<String> {
  PosKeypadController({
    String initial = '',
    this.maxIntegerDigits = 9,
    this.maxDecimals = 0,
  }) : super(initial);

  final int maxIntegerDigits;

  /// 0 — faqat butun son.
  final int maxDecimals;

  String get text => value;

  bool get isEmpty => value.isEmpty;

  /// Raqam(lar) yoki nuqta qo'shadi; qoidaga zid bo'lsa e'tiborsiz.
  void input(String key) {
    var next = value;
    for (final String ch in key.split('')) {
      next = _apply(next, ch);
    }
    value = next;
  }

  String _apply(String current, String ch) {
    if (ch == '.' || ch == ',') {
      if (maxDecimals == 0 || current.contains('.')) return current;
      return current.isEmpty ? '0.' : '$current.';
    }
    if (!RegExp(r'^\d$').hasMatch(ch)) return current;
    final int dot = current.indexOf('.');
    if (dot >= 0) {
      if (current.length - dot - 1 >= maxDecimals) return current;
      return '$current$ch';
    }
    if (current == '0') return ch == '0' ? current : ch; // bosh nol
    if (current.isEmpty && ch == '0' && maxDecimals == 0) return '0';
    if (current.length >= maxIntegerDigits) return current;
    return '$current$ch';
  }

  void backspace() {
    if (value.isEmpty) return;
    value = value.substring(0, value.length - 1);
  }

  void clear() => value = '';

  /// Butun so'm sifatida (bo'sh — 0).
  PosMoney get amount =>
      PosMoney.som(int.tryParse(value.split('.').first) ?? 0);

  /// Miqdor sifatida (bo'sh yoki "0." — `null`/0).
  PosQuantity? get quantity => value.isEmpty
      ? null
      : PosQuantity.tryParse(value.endsWith('.') ? '${value}0' : value);
}

/// Raqam klaviaturasi: 1–9, pastda [extraKey], 0 va o'chirish (uzoq bosish — hammasini tozalash). Jismoniy klaviatura
/// ham ishlaydi (raqamlar, nuqta, Backspace, Delete).
class PosNumericKeypad extends StatelessWidget {
  const PosNumericKeypad({
    super.key,
    required this.onKey,
    required this.onBackspace,
    this.onClear,
    this.extraKey = PosKeypadExtraKey.none,
    this.keySize = 72,
    this.enabled = true,
    this.autofocus = false,
  });

  final ValueChanged<String> onKey;
  final VoidCallback onBackspace;
  final VoidCallback? onClear;
  final PosKeypadExtraKey extraKey;
  final double keySize;
  final bool enabled;
  final bool autofocus;

  KeyEventResult _onKey(FocusNode node, KeyEvent event) {
    if (!enabled || (event is! KeyDownEvent && event is! KeyRepeatEvent)) {
      return KeyEventResult.ignored;
    }
    final LogicalKeyboardKey key = event.logicalKey;
    if (key == LogicalKeyboardKey.backspace) {
      onBackspace();
      return KeyEventResult.handled;
    }
    if (key == LogicalKeyboardKey.delete && onClear != null) {
      onClear!();
      return KeyEventResult.handled;
    }
    final String? ch = event.character;
    if (ch != null && RegExp(r'^\d$').hasMatch(ch)) {
      onKey(ch);
      return KeyEventResult.handled;
    }
    if ((ch == '.' || ch == ',') && extraKey == PosKeypadExtraKey.decimal) {
      onKey('.');
      return KeyEventResult.handled;
    }
    return KeyEventResult.ignored;
  }

  @override
  Widget build(BuildContext context) {
    Widget digit(String d) => PosKey(
      size: keySize,
      label: d,
      onPressed: enabled ? () => onKey(d) : null,
      child: Text(d),
    );

    Widget row(List<Widget> keys) => Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        for (final Widget k in keys)
          Padding(padding: const EdgeInsets.all(5), child: k),
      ],
    );

    final Widget extra = extraKey == PosKeypadExtraKey.none
        ? SizedBox.square(dimension: keySize)
        : PosKey(
            size: keySize,
            label: extraKey == PosKeypadExtraKey.decimal
                ? 'Nuqta'
                : extraKey.key,
            onPressed: enabled ? () => onKey(extraKey.key) : null,
            child: Text(extraKey.key),
          );

    return Focus(
      autofocus: autofocus,
      onKeyEvent: _onKey,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          row(<Widget>[digit('1'), digit('2'), digit('3')]),
          row(<Widget>[digit('4'), digit('5'), digit('6')]),
          row(<Widget>[digit('7'), digit('8'), digit('9')]),
          row(<Widget>[
            extra,
            digit('0'),
            PosKey(
              size: keySize,
              label: "O'chirish",
              onPressed: enabled ? onBackspace : null,
              onLongPress: enabled ? onClear : null,
              child: const Icon(Icons.backspace_outlined),
            ),
          ]),
        ],
      ),
    );
  }
}

/// Klaviatura tugmasi (rizqim_ui PIN klaviaturasi bilan bir xil ko'rinish).
class PosKey extends StatelessWidget {
  const PosKey({
    super.key,
    required this.size,
    required this.label,
    required this.onPressed,
    required this.child,
    this.onLongPress,
    this.primary = false,
    this.width,
  });

  final double size;

  /// Ekran o'quvchi uchun nom.
  final String label;
  final VoidCallback? onPressed;
  final VoidCallback? onLongPress;
  final Widget child;
  final bool primary;

  /// Kengroq tugma (tez summalar); `null` — kvadrat.
  final double? width;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final Color bg = primary
        ? c.brand
        : Color.alphaBlend(c.fg.withValues(alpha: 0.08), c.surface);
    return Semantics(
      button: true,
      label: label,
      enabled: onPressed != null,
      excludeSemantics: true,
      child: SizedBox(
        width: width ?? size,
        height: size,
        child: FilledButton(
          onPressed: onPressed,
          onLongPress: onLongPress,
          style: FilledButton.styleFrom(
            backgroundColor: bg,
            foregroundColor: primary ? c.onBrand : c.fg,
            disabledBackgroundColor: c.fgSubtle.withValues(alpha: 0.18),
            disabledForegroundColor: c.fgSubtle,
            padding: EdgeInsets.zero,
            minimumSize: Size(width ?? size, size),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(RzRadius.card),
              side: primary ? BorderSide.none : BorderSide(color: c.line),
            ),
            textStyle: size >= 64 ? RzType.display : RzType.title,
            iconSize: 28,
          ),
          child: child,
        ),
      ),
    );
  }
}

/// Katta raqamli ko'rsatkich (summa yoki miqdor) — klaviatura tepasida.
class PosValueDisplay extends StatelessWidget {
  const PosValueDisplay({
    super.key,
    required this.value,
    this.suffix,
    this.placeholder = '0',
    this.error,
  });

  final String value;
  final String? suffix;
  final String placeholder;
  final String? error;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool empty = value.isEmpty;
    return Semantics(
      liveRegion: true,
      label:
          '${empty ? placeholder : value}${suffix == null ? '' : ' $suffix'}',
      excludeSemantics: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.end,
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Container(
            constraints: const BoxConstraints(minHeight: 64),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
              color: Color.alphaBlend(c.surface2, c.surface),
              borderRadius: BorderRadius.circular(RzRadius.card),
              border: Border.all(color: error == null ? c.line : c.danger),
            ),
            alignment: Alignment.centerRight,
            child: Text.rich(
              TextSpan(
                children: <InlineSpan>[
                  TextSpan(
                    text: empty ? placeholder : value,
                    style: RzType.display.copyWith(
                      color: empty ? c.fgSubtle : c.fg,
                    ),
                  ),
                  if (suffix != null)
                    TextSpan(
                      text: ' $suffix',
                      style: RzType.title.copyWith(color: c.fgMuted),
                    ),
                ],
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                error!,
                style: RzType.footnote.copyWith(color: c.dangerSoftFg),
              ),
            ),
        ],
      ),
    );
  }
}

/// Summa kiritish (to'lov, kassaga kirim/chiqim, boshlang'ich naqd): butun so'm, "000" tugmasi va tez summalar.
class PosAmountEntry extends StatefulWidget {
  const PosAmountEntry({
    super.key,
    required this.onChanged,
    this.initial,
    this.quickAmounts = const <PosMoney>[],
    this.keySize = 72,
    this.autofocus = false,
  });

  final ValueChanged<PosMoney> onChanged;
  final PosMoney? initial;

  /// Tez tugmalar: "To'liq" summa, 50 000, 100 000 ...
  final List<PosMoney> quickAmounts;
  final double keySize;
  final bool autofocus;

  @override
  State<PosAmountEntry> createState() => _PosAmountEntryState();
}

class _PosAmountEntryState extends State<PosAmountEntry> {
  late final PosKeypadController _input = PosKeypadController(
    initial: widget.initial == null || widget.initial!.isZero
        ? ''
        : '${widget.initial!.wholeSom}',
  )..addListener(_changed);

  void _changed() {
    setState(() {});
    widget.onChanged(_input.amount);
  }

  @override
  void dispose() {
    _input.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final String shown = _input.isEmpty
        ? ''
        : groupDigits(int.parse(_input.text));
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        PosValueDisplay(value: shown, suffix: PosMoney.currencyLabel),
        if (widget.quickAmounts.isNotEmpty) ...<Widget>[
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              for (final PosMoney m in widget.quickAmounts)
                PosKey(
                  size: 52,
                  width: 120,
                  label: m.format(),
                  onPressed: () => _input.value = '${m.wholeSom}',
                  child: Text(m.formatAmount(), style: RzType.headline),
                ),
            ],
          ),
        ],
        const SizedBox(height: 12),
        Center(
          child: PosNumericKeypad(
            keySize: widget.keySize,
            autofocus: widget.autofocus,
            extraKey: PosKeypadExtraKey.tripleZero,
            onKey: _input.input,
            onBackspace: _input.backspace,
            onClear: _input.clear,
          ),
        ),
      ],
    );
  }
}
