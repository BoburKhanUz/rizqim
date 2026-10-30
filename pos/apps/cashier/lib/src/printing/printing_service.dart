import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';

/// Server sozlamasidagi printer qurilmada ishlatib bo'lmaydi (masalan, Bluetooth hozircha yo'q).
class UnsupportedPrinter {
  const UnsupportedPrinter(this.info, this.reason);

  final PosPrinterInfo info;

  /// O'zbekcha sabab (printer sozlamalari ekranida ko'rsatiladi).
  final String reason;
}

/// Kassaning printerlari: bootstrap'dagi printerlar (`GET /pos/bootstrap` → `printers`, shu terminal ishlata
/// oladiganlari) `pos_hardware` [PrinterConfig] larga aylantiriladi va [PosPrinters] (chop etish navbati) orqali
/// ishlatiladi. Chop etishni server emas, terminal o'zi bajaradi (docs/POS_SPEC.md 2.11).
///
/// * [receiptPrinter] — chek va schyot (`purpose: receipt`);
/// * [kitchenPrinterFor] — sex markasi (`kitchen`, sexi bo'yicha; sexsiz qatorlar — sexsiz kitchen printeri);
/// * [labelPrinter] — stakan stikeri va paket yorlig'i (P6-32, `label`; TSPL tilida);
/// * [unsupported] — ishlatib bo'lmaydiganlari va sababi.
///
/// Mos kelish: `lan` → LAN 9100 (`address`, `port`), `builtin` → Sunmi ichki printeri, `usb` → Windows spooler (printer
/// nomi — serverdagi `name`; Windows'dagi nom boshqacha bo'lsa P3-22 printer sozlamalarida qurilmada almashtiradi),
/// `bluetooth` — hozircha yo'q. `encoding` → kod sahifasi, `print_as_image` → matn rasm sifatida. P6-32: `purpose: label`
/// printeri TSPL tilida ([PrinterLanguage.tspl]: Xprinter, Gprinter yorliq printerlari); boshqalari ESC/POS — serverda
/// alohida "til" maydoni yo'q, maqsadning o'zi tilni aytadi.
class CashierPrinting extends ChangeNotifier {
  CashierPrinting({PosPrinters? printers})
    : printers = printers ?? PosPrinters();

  final PosPrinters printers;

  List<PrinterConfig> _configs = const <PrinterConfig>[];
  List<PosPrinterInfo> _infos = const <PosPrinterInfo>[];
  List<UnsupportedPrinter> _unsupported = const <UnsupportedPrinter>[];

  List<PrinterConfig> get configs => _configs;
  List<UnsupportedPrinter> get unsupported => _unsupported;

  /// Bootstrap printerlaridan sozlaydi (har bootstrap'dan keyin).
  void configure(List<PosPrinterInfo> infos) {
    final List<PrinterConfig> configs = <PrinterConfig>[];
    final List<PosPrinterInfo> used = <PosPrinterInfo>[];
    final List<UnsupportedPrinter> unsupported = <UnsupportedPrinter>[];
    for (final PosPrinterInfo info in infos) {
      if (!info.isActive) continue;
      final (PrinterConfig? config, String? reason) = toPrinterConfig(info);
      if (config != null) {
        configs.add(config);
        used.add(info);
      } else {
        unsupported.add(UnsupportedPrinter(info, reason!));
      }
    }
    _configs = List<PrinterConfig>.unmodifiable(configs);
    _infos = List<PosPrinterInfo>.unmodifiable(used);
    _unsupported = List<UnsupportedPrinter>.unmodifiable(unsupported);
    notifyListeners();
  }

  /// Xodim chiqqanda yoki terminal uzilganda.
  void clear() => configure(const <PosPrinterInfo>[]);

  /// Chek va schyot printeri (birinchi `receipt`).
  PrinterConfig? get receiptPrinter =>
      _first((PosPrinterInfo i) => i.isReceipt);

  /// P6-32: stiker printeri (birinchi `label`, TSPL); yo'q — `null` (stiker chiqmaydi, to'lov davom etadi).
  PrinterConfig? get labelPrinter => _first((PosPrinterInfo i) => i.isLabel);

  /// Sex markasi printeri: shu sexniki, bo'lmasa sexsiz `kitchen` printeri.
  PrinterConfig? kitchenPrinterFor(int? stationId) =>
      _first((PosPrinterInfo i) => i.isKitchen && i.stationId == stationId) ??
      _first((PosPrinterInfo i) => i.isKitchen && i.stationId == null);

  /// Server printeriga mos qurilma sozlamasi: [PosPrinterInfo] id'si `server-<id>`.
  PrinterConfig? configFor(int printerId) {
    for (var i = 0; i < _infos.length; i++) {
      if (_infos[i].id == printerId) return _configs[i];
    }
    return null;
  }

  PrinterConfig? _first(bool Function(PosPrinterInfo info) test) {
    for (var i = 0; i < _infos.length; i++) {
      if (test(_infos[i])) return _configs[i];
    }
    return null;
  }

  @override
  void dispose() {
    unawaited(printers.dispose());
    super.dispose();
  }
}

/// [PosPrinterInfo] → [PrinterConfig]; ishlatib bo'lmasa `(null, sabab)`.
(PrinterConfig?, String?) toPrinterConfig(PosPrinterInfo info) {
  final PrinterTarget? target = switch (info.connection) {
    'lan' when (info.address ?? '').trim().isNotEmpty => LanPrinterTarget(
      info.address!.trim(),
      port: info.port ?? LanPrinterTarget.defaultPort,
    ),
    'builtin' => const SunmiPrinterTarget(),
    'usb' => WindowsPrinterTarget(info.name),
    _ => null,
  };
  if (target == null) {
    return (
      null,
      switch (info.connection) {
        'bluetooth' => "Bluetooth printer hozircha qo'llab-quvvatlanmaydi.",
        'lan' => "Printer manzili (IP) kiritilmagan.",
        _ => "Noma'lum ulanish turi: ${info.connection}.",
      },
    );
  }
  return (
    PrinterConfig(
      id: 'server-${info.id}',
      name: info.name,
      target: target,
      paper: PaperWidth.fromMillimetres(info.paperWidth),
      codePage: EscPosCodePage.byName(info.encoding),
      textMode: info.printAsImage ? PrintTextMode.image : PrintTextMode.auto,
      language: info.isLabel ? PrinterLanguage.tspl : PrinterLanguage.escpos,
    ),
    null,
  );
}
