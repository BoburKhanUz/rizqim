import 'package:flutter/foundation.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Shu qurilmaning kassa sozlamalari (serverga yuborilmaydi): yangi onlayn buyurtma ovozi va printerning qog'oz eni
/// hamda "matnni rasm sifatida" tanlovi (server sozlamasi ustidan; masalan 80 mm deb kiritilgan printerga 58 mm qog'oz
/// qo'yilgan bo'lsa).
class CashierPrefs extends ChangeNotifier {
  CashierPrefs._();

  /// Ilova bo'yicha bitta nusxa.
  static final CashierPrefs instance = CashierPrefs._();

  static const String _soundKey = 'cashier.sound';
  static const String _paperKey = 'cashier.printer_paper.';
  static const String _imageKey = 'cashier.printer_image.';

  bool _sound = true;
  final Map<String, int> _paper = <String, int>{};
  final Map<String, bool> _image = <String, bool>{};
  Future<void>? _loading;

  /// Yangi onlayn buyurtma kelganda ovoz.
  bool get sound => _sound;

  /// Printer (`server-<id>`) uchun qurilmada tanlangan qog'oz eni (mm); `null` — server sozlamasi.
  int? paperOf(String printerId) => _paper[printerId];

  /// Printer uchun qurilmada tanlangan "matnni rasm sifatida"; `null` — server sozlamasi.
  bool? imageOf(String printerId) => _image[printerId];

  /// Saqlangan qiymatlarni o'qiydi (bir marta).
  Future<void> load() => _loading ??= _load();

  Future<void> _load() async {
    try {
      final SharedPreferences p = await SharedPreferences.getInstance();
      _sound = p.getBool(_soundKey) ?? true;
      for (final String key in p.getKeys()) {
        if (key.startsWith(_paperKey)) {
          final int? v = p.getInt(key);
          if (v != null) _paper[key.substring(_paperKey.length)] = v;
        } else if (key.startsWith(_imageKey)) {
          final bool? v = p.getBool(key);
          if (v != null) _image[key.substring(_imageKey.length)] = v;
        }
      }
      notifyListeners();
    } on Object {
      // O'qib bo'lmadi: standart qiymatlar
    }
  }

  Future<void> setSound(bool on) async {
    _sound = on;
    notifyListeners();
    await _write((SharedPreferences p) => p.setBool(_soundKey, on));
  }

  Future<void> setPaper(String printerId, int mm) async {
    _paper[printerId] = mm;
    notifyListeners();
    await _write((SharedPreferences p) => p.setInt('$_paperKey$printerId', mm));
  }

  Future<void> setImage(String printerId, bool on) async {
    _image[printerId] = on;
    notifyListeners();
    await _write(
      (SharedPreferences p) => p.setBool('$_imageKey$printerId', on),
    );
  }

  Future<void> _write(Future<bool> Function(SharedPreferences p) write) async {
    try {
      await write(await SharedPreferences.getInstance());
    } on Object {
      // Saqlanmadi: shu ishga tushirishda ishlaydi
    }
  }

  /// Server sozlamasi ustiga qurilmadagi tanlovlar.
  PrinterConfig apply(PrinterConfig config) {
    final int? mm = _paper[config.id];
    final bool? image = _image[config.id];
    if (mm == null && image == null) return config;
    return config.copyWith(
      paper: mm == null ? null : PaperWidth.fromMillimetres(mm),
      textMode: image == null
          ? null
          : (image ? PrintTextMode.image : PrintTextMode.auto),
    );
  }

  /// Testlar uchun: standart holatga qaytaradi.
  @visibleForTesting
  void reset() {
    _sound = true;
    _paper.clear();
    _image.clear();
    _loading = null;
  }
}
