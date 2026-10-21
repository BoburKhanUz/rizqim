import '../settings/kds_settings.dart';

/// Chiptaning kutish darajasi (rang, ikonka va yorliq bilan ko'rsatiladi).
enum WaitLevel {
  /// Chegaradan oldin.
  normal,

  /// [KdsSettings.warnAfter] dan oshdi: "Shoshiling".
  warn,

  /// [KdsSettings.lateAfter] dan oshdi: "Kechikdi".
  late;

  static WaitLevel of(Duration waiting, KdsSettings settings) {
    if (waiting >= settings.lateAfter) return WaitLevel.late;
    if (waiting >= settings.warnAfter) return WaitLevel.warn;
    return WaitLevel.normal;
  }
}
