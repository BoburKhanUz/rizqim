import 'package:flutter/services.dart';

import 'printer.dart';

/// Sunmi ichki printeri: platform kanali (Android plagini `PosHardwarePlugin`, Sunmi `printerlibrary` ustida).
///
/// ### Kanal shartnomasi: `uz.rizqim.pos_hardware/sunmi` (MethodChannel, standart kodek)
///
/// | Usul | Argument | Natija | Xato (`PlatformException.code`) |
/// |---|---|---|---|
/// | `bind` | — | `bool`: Sunmi printer xizmatiga ulandi | — (Sunmi bo'lmasa `false`) |
/// | `status` | — | `int`: Sunmi `updatePrinterState()` kodi (pastda) | `unavailable` |
/// | `sendRaw` | `{'bytes': Uint8List}` | `null` (printer qabul qildi) | `unavailable`, `printer_error` (`details`: holat kodi), `failed` |
/// | `openDrawer` | — | `null` | `unavailable`, `failed` |
/// | `info` | — | `{'model': String?, 'serial': String?, 'version': String?, 'paper': int? (58/80)}` | `unavailable` |
///
/// Holat kodlari: 1 tayyor, 2 tayyorlanmoqda, 3 aloqa xatosi, 4 qog'oz tugadi, 5 qizib ketdi, 6 qopqoq ochiq,
/// 7 kesgich xatosi, 8 kesgich tiklandi, 9 qora belgi topilmadi, 505 printer yo'q, 507 proshivka xatosi.
class SunmiPrinterTransport implements PrinterTransport {
  SunmiPrinterTransport({MethodChannel? channel})
    : _channel = channel ?? defaultChannel;

  static const MethodChannel defaultChannel = MethodChannel(
    'uz.rizqim.pos_hardware/sunmi',
  );

  final MethodChannel _channel;
  bool _bound = false;

  /// Sunmi xizmatiga ulanadi (birinchi chaqiruvda). Sunmi bo'lmasa — `unsupported`.
  Future<void> _ensureBound() async {
    if (_bound) return;
    bool ok;
    try {
      ok = await _channel.invokeMethod<bool>('bind') ?? false;
    } on MissingPluginException {
      ok = false;
    }
    if (!ok) {
      throw const PrinterException(
        PrinterErrorKind.unsupported,
        'Sunmi ichki printeri topilmadi (bu qurilma Sunmi emas).',
      );
    }
    _bound = true;
  }

  /// Bu qurilmada Sunmi printeri bormi (sozlamada "Ichki printer" ni ko'rsatish uchun).
  Future<bool> isAvailable() async {
    try {
      await _ensureBound();
      return true;
    } on PrinterException {
      return false;
    }
  }

  /// Model va qog'oz kengligi.
  Future<Map<String, Object?>> info() async {
    await _ensureBound();
    final Map<Object?, Object?>? raw = await _channel
        .invokeMethod<Map<Object?, Object?>>('info');
    return raw?.cast<String, Object?>() ?? <String, Object?>{};
  }

  @override
  Future<void> send(Uint8List bytes) async {
    await _ensureBound();
    try {
      await _channel.invokeMethod<void>('sendRaw', <String, Object?>{
        'bytes': bytes,
      });
    } on PlatformException catch (e) {
      throw _mapError(e);
    }
  }

  @override
  Future<PrinterStatus> status() async {
    try {
      await _ensureBound();
      final int code = await _channel.invokeMethod<int>('status') ?? 0;
      return statusFromSunmiCode(code);
    } on PrinterException {
      return PrinterStatus.offline;
    } on PlatformException {
      return PrinterStatus.unknown;
    }
  }

  @override
  Future<void> openDrawer(Uint8List kickBytes) async {
    await _ensureBound();
    try {
      await _channel.invokeMethod<void>('openDrawer');
    } on PlatformException catch (e) {
      throw _mapError(e);
    }
  }

  @override
  Future<void> close() async {}

  PrinterException _mapError(PlatformException e) {
    if (e.code == 'unavailable') {
      _bound = false;
      return PrinterException(
        PrinterErrorKind.offline,
        'Sunmi printer xizmati javob bermadi: ${e.message ?? ''}'.trim(),
      );
    }
    if (e.code == 'printer_error' && e.details is int) {
      final PrinterStatus s = statusFromSunmiCode(e.details as int);
      return switch (s) {
        PrinterStatus.paperOut => const PrinterException(
          PrinterErrorKind.paperOut,
          "Qog'oz tugadi. Yangi rulon qo'ying va qayta chop eting.",
        ),
        PrinterStatus.coverOpen => const PrinterException(
          PrinterErrorKind.coverOpen,
          "Printer qopqog'i ochiq. Yoping va qayta chop eting.",
        ),
        _ => PrinterException(PrinterErrorKind.failed, s.label),
      };
    }
    return PrinterException(
      PrinterErrorKind.failed,
      'Sunmi printer xatosi: ${e.message ?? e.code}',
    );
  }
}

/// Sunmi `updatePrinterState()` kodidan holat.
PrinterStatus statusFromSunmiCode(int code) => switch (code) {
  1 || 8 => PrinterStatus.ready,
  2 => PrinterStatus.unknown,
  3 || 505 => PrinterStatus.offline,
  4 => PrinterStatus.paperOut,
  6 => PrinterStatus.coverOpen,
  _ => PrinterStatus.error,
};
