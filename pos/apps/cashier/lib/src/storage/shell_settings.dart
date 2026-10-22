import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Qurilmadagi oddiy sozlamalar (maxfiy emas): hozircha mavzu. Kalit-qiymat ombori orqali (testlarda xotirada).
abstract interface class ShellSettingsStore {
  Future<String?> read(String key);

  Future<void> write(String key, String value);
}

class PrefsShellSettingsStore implements ShellSettingsStore {
  @override
  Future<String?> read(String key) async =>
      (await SharedPreferences.getInstance()).getString(key);

  @override
  Future<void> write(String key, String value) async {
    await (await SharedPreferences.getInstance()).setString(key, value);
  }
}

class MemoryShellSettingsStore implements ShellSettingsStore {
  MemoryShellSettingsStore([Map<String, String>? values])
    : values = values ?? <String, String>{};

  final Map<String, String> values;

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async => values[key] = value;
}

/// Mavzu: tizim, yorug' yoki qorong'i. O'zgarsa ilova qayta chiziladi.
class ShellSettingsController extends ValueNotifier<ThemeMode> {
  ShellSettingsController(this._store, {ThemeMode initial = ThemeMode.system})
    : super(initial);

  final ShellSettingsStore _store;

  static const String _themeKey = 'cashier.theme_mode';

  Future<void> load() async {
    try {
      final String? saved = await _store.read(_themeKey);
      value = ThemeMode.values.firstWhere(
        (ThemeMode m) => m.name == saved,
        orElse: () => value,
      );
    } on Object {
      // O'qib bo'lmadi: standart mavzu
    }
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    value = mode;
    try {
      await _store.write(_themeKey, mode.name);
    } on Object {
      // Saqlanmadi: shu ishga tushirishda ishlaydi
    }
  }
}
