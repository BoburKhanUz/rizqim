import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';

/// Klaviatura sifatida ulangan (HID, "keyboard wedge") shtrix-kod skanerini oddiy yozishdan ajratadi: skaner kodni juda
/// tez (har belgi orasida bir necha millisoniya) yozadi va oxirida Enter bosadi; odam bunday tez yoza olmaydi.
///
/// Qoida: kamida [minLength] belgi, har ikki belgi orasidagi vaqt [maxGap] dan oshmaydi va ketma-ketlik Enter (yoki
/// [terminators] dagi tugma) bilan tugaydi. Oraliq [maxGap] dan oshsa, yig'ilgan belgilar tashlanadi (odam yozgan).
///
/// Platformaga bog'liq emas: Windows, Linux, Android (USB/Bluetooth skaner, Sunmi'da "klaviatura" rejimi).
class KeyboardScanDetector {
  KeyboardScanDetector({
    this.minLength = 4,
    this.maxGap = const Duration(milliseconds: 50),
    Duration Function()? clock,
  }) : _clock = clock ?? _stopwatchClock();

  /// Eng qisqa kod (EAN-8 — 8, ichki kodlar qisqaroq bo'lishi mumkin).
  final int minLength;

  /// Ikki belgi orasidagi eng uzun vaqt (skanerlar odatda 5–30 ms).
  final Duration maxGap;

  final Duration Function() _clock;
  final StringBuffer _buffer = StringBuffer();
  Duration? _last;

  static Duration Function() _stopwatchClock() {
    final Stopwatch watch = Stopwatch()..start();
    return () => watch.elapsed;
  }

  /// Yig'ilayotgan belgilar soni (testlar uchun).
  int get pending => _buffer.length;

  /// Bitta yozilgan belgi.
  void addCharacter(String character) {
    final Duration now = _clock();
    final Duration? last = _last;
    if (last != null && now - last > maxGap) _buffer.clear();
    _buffer.write(character);
    _last = now;
  }

  /// Enter (yoki boshqa tugatuvchi tugma): skaner kodi bo'lsa uni qaytaradi, bo'lmasa `null`.
  String? addTerminator() {
    final Duration now = _clock();
    final Duration? last = _last;
    final String code = _buffer.toString();
    reset();
    if (last == null || code.length < minLength) return null;
    // Enter oxirgi belgidan biroz kechroq kelishi mumkin (ba'zi skanerlar suffiksni alohida yuboradi)
    if (now - last > maxGap * 2) return null;
    return code;
  }

  void reset() {
    _buffer.clear();
    _last = null;
  }
}

/// [KeyboardScanListener] skanerlagan kod.
typedef ScanCallback = void Function(String code);

/// Butun ilova bo'yicha HID skanerini tinglaydi ([HardwareKeyboard] orqali, fokus qayerda bo'lishidan qat'i nazar) va
/// kod o'qilganda [onScan] ni chaqiradi.
///
/// * Matn maydoni fokusda bo'lsa (qidiruv, izoh), kod o'sha maydonga yoziladi — [ignoreWhileEditingText] `true` bo'lsa
///   [onScan] chaqirilmaydi (ikki marta ishlamasin).
/// * Kodni tugatgan Enter yutiladi: fokusdagi tugma tasodifan bosilmaydi.
///
/// ```dart
/// KeyboardScanListener(onScan: (code) => scans.add(code), child: app)
/// ```
class KeyboardScanListener extends StatefulWidget {
  const KeyboardScanListener({
    super.key,
    required this.onScan,
    required this.child,
    this.enabled = true,
    this.ignoreWhileEditingText = true,
    this.detector,
    this.terminators = const <LogicalKeyboardKey>[
      LogicalKeyboardKey.enter,
      LogicalKeyboardKey.numpadEnter,
    ],
  });

  final ScanCallback onScan;
  final Widget child;
  final bool enabled;
  final bool ignoreWhileEditingText;

  /// `null` — standart qoidalar ([KeyboardScanDetector]).
  final KeyboardScanDetector? detector;

  /// Kodni tugatuvchi tugmalar (ba'zi skanerlar Tab yuboradi).
  final List<LogicalKeyboardKey> terminators;

  @override
  State<KeyboardScanListener> createState() => _KeyboardScanListenerState();
}

class _KeyboardScanListenerState extends State<KeyboardScanListener> {
  late KeyboardScanDetector _detector =
      widget.detector ?? KeyboardScanDetector();
  bool _swallowTerminator = false;

  @override
  void initState() {
    super.initState();
    HardwareKeyboard.instance.addHandler(_onKey);
  }

  @override
  void didUpdateWidget(KeyboardScanListener old) {
    super.didUpdateWidget(old);
    if (widget.detector != old.detector) {
      _detector = widget.detector ?? KeyboardScanDetector();
    }
  }

  @override
  void dispose() {
    HardwareKeyboard.instance.removeHandler(_onKey);
    super.dispose();
  }

  bool get _editingText {
    final BuildContext? focused = FocusManager.instance.primaryFocus?.context;
    if (focused == null) return false;
    return focused.widget is EditableText ||
        focused.findAncestorWidgetOfExactType<EditableText>() != null;
  }

  /// Hamma tugma shu yerdan o'tadi (fokus tizimidan oldin); hech narsani to'xtatmaydi.
  bool _onKey(KeyEvent event) {
    if (event is! KeyDownEvent) return false;
    // Each key press decides anew (the focus system sees the same event right after this handler)
    _swallowTerminator = false;
    if (!widget.enabled) return false;
    if (widget.terminators.contains(event.logicalKey)) {
      final String? code = _detector.addTerminator();
      if (code == null) return false;
      if (widget.ignoreWhileEditingText && _editingText) return false;
      _swallowTerminator = true;
      widget.onScan(code);
      return true;
    }
    final String? ch = event.character;
    if (ch != null && ch.isNotEmpty && ch.codeUnitAt(0) >= 0x20) {
      _detector.addCharacter(ch);
    }
    return false;
  }

  @override
  Widget build(BuildContext context) => Focus(
    canRequestFocus: false,
    skipTraversal: true,
    // Fokus tizimi tugmani keyinroq oladi: skaner kodini tugatgan Enter fokusdagi tugmani bosmasin
    onKeyEvent: (FocusNode node, KeyEvent event) {
      if (_swallowTerminator &&
          event is KeyDownEvent &&
          widget.terminators.contains(event.logicalKey)) {
        return KeyEventResult.handled;
      }
      return KeyEventResult.ignored;
    },
    child: widget.child,
  );
}
