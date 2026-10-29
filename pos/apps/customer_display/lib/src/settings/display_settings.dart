import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Ekran nimani ko'rsatadi.
enum DisplayMode {
  /// Chek bor paytda chek, qolgan paytda "Tayyor" raqamlar (formatda ikkalasi ham yoqilgan bo'lsa); bittasi bo'lsa shu.
  auto('auto', 'Avto'),

  /// Kassaning ikkinchi ekrani: joriy chek va to'lov.
  cart('cart', 'Ikkinchi ekran'),

  /// "Tayyorlanmoqda / Tayyor" raqamlar taxtasi (televizor).
  board('board', 'Raqamlar taxtasi');

  const DisplayMode(this.wire, this.label);

  final String wire;
  final String label;

  static DisplayMode? fromWire(Object? wire) {
    for (final DisplayMode m in values) {
      if (m.wire == wire) return m;
    }
    return null;
  }
}

/// Mijoz ekrani sozlamalari (qurilmada saqlanadi, maxfiy emas).
@immutable
class DisplaySettings {
  const DisplaySettings({
    this.mode = DisplayMode.auto,
    this.muted = false,
    this.dark = true,
    this.fontScale = 1.0,
  });

  final DisplayMode mode;

  /// "Tayyor" raqam ovozi o'chirilgan.
  final bool muted;

  /// Qorong'i mavzu (televizor va xira zal uchun standart).
  final bool dark;

  /// Shrift kattaligi: 1.0, 1.25, 1.5 (uzoqdan o'qiladigan televizor).
  final double fontScale;

  static const List<double> fontChoices = <double>[1.0, 1.25, 1.5];

  DisplaySettings copyWith({
    DisplayMode? mode,
    bool? muted,
    bool? dark,
    double? fontScale,
  }) => DisplaySettings(
    mode: mode ?? this.mode,
    muted: muted ?? this.muted,
    dark: dark ?? this.dark,
    fontScale: fontScale ?? this.fontScale,
  );

  Map<String, Object> toMap() => <String, Object>{
    'mode': mode.wire,
    'muted': muted,
    'dark': dark,
    'font_scale': fontScale,
  };

  /// Saqlangan qiymatlardan; yo'q yoki buzilgan qiymat o'rniga standart.
  factory DisplaySettings.fromMap(Map<String, Object?> map) {
    final Object? scale = map['font_scale'];
    return const DisplaySettings().copyWith(
      mode: DisplayMode.fromWire(map['mode']),
      muted: map['muted'] is bool ? map['muted']! as bool : null,
      dark: map['dark'] is bool ? map['dark']! as bool : null,
      fontScale: scale is double && scale >= 0.8 && scale <= 2 ? scale : null,
    );
  }

  @override
  bool operator ==(Object other) =>
      other is DisplaySettings &&
      other.mode == mode &&
      other.muted == muted &&
      other.dark == dark &&
      other.fontScale == fontScale;

  @override
  int get hashCode => Object.hash(mode, muted, dark, fontScale);
}

/// Sozlamalar ombori.
abstract interface class DisplaySettingsStore {
  Future<Map<String, Object?>> load();

  Future<void> save(Map<String, Object> values);
}

/// `shared_preferences` (Android SharedPreferences, Windows/Linux JSON fayl). Token emas, faqat ekran sozlamalari.
class PrefsSettingsStore implements DisplaySettingsStore {
  static const String _prefix = 'display.settings.';

  @override
  Future<Map<String, Object?>> load() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    return <String, Object?>{
      for (final String key in prefs.getKeys())
        if (key.startsWith(_prefix))
          key.substring(_prefix.length): prefs.get(key),
    };
  }

  @override
  Future<void> save(Map<String, Object> values) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    for (final MapEntry<String, Object> e in values.entries) {
      final String key = '$_prefix${e.key}';
      switch (e.value) {
        case final bool v:
          await prefs.setBool(key, v);
        case final int v:
          await prefs.setInt(key, v);
        case final double v:
          await prefs.setDouble(key, v);
        case final Object v:
          await prefs.setString(key, '$v');
      }
    }
  }
}

/// Testlar uchun.
class MemorySettingsStore implements DisplaySettingsStore {
  MemorySettingsStore([Map<String, Object?>? values])
    : values = values ?? <String, Object?>{};

  final Map<String, Object?> values;

  @override
  Future<Map<String, Object?>> load() async => Map<String, Object?>.of(values);

  @override
  Future<void> save(Map<String, Object> v) async => values.addAll(v);
}

/// Joriy sozlamalar; o'zgarsa ekranlar qayta chiziladi va qiymat saqlanadi.
class DisplaySettingsController extends ValueNotifier<DisplaySettings> {
  DisplaySettingsController(
    this._store, [
    super.initial = const DisplaySettings(),
  ]);

  final DisplaySettingsStore _store;

  /// Saqlangan sozlamalarni o'qiydi; o'qib bo'lmasa standartlar qoladi.
  Future<void> load() async {
    try {
      value = DisplaySettings.fromMap(await _store.load());
    } on Object {
      // Standart sozlamalar bilan ishlayveradi
    }
  }

  void update(DisplaySettings next) {
    if (next == value) return;
    value = next;
    unawaited(_save(next));
  }

  Future<void> _save(DisplaySettings s) async {
    try {
      await _store.save(s.toMap());
    } on Object {
      // Saqlanmasa ham joriy ishga tushirishda amal qiladi
    }
  }
}
