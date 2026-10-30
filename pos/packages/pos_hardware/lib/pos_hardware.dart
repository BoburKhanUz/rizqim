/// Rizqim POS uskunasi: ESC/POS printerlar (LAN 9100, USB Windows spooler orqali, Sunmi ichki printeri), kassa
/// qutisi, matnni rasm sifatida chop etish (oʻ, gʻ, ʼ uchun), chop etish navbati va printer holati.
///
/// Hujjatlar `pos_core` da joylashtiriladi ([PrintLayout]); bu paket ularni baytlarga aylantirib printerga yetkazadi.
/// P6-32: stakan stikeri va paket yorlig'i label printerga TSPL buyruqlari bilan ([TsplRenderer], [PosPrinters.printLabel]).
/// Testlar uchun soxta printer va LAN emulyatori: `package:pos_hardware/testing.dart`.
library;

export 'src/escpos/code_page.dart';
export 'src/escpos/esc_pos_builder.dart';
export 'src/escpos/esc_pos_decoder.dart';
export 'src/escpos/layout_renderer.dart';
export 'src/pos_printers.dart';
export 'src/printer/lan_transport.dart'
    show LanPrinterTransport, parseStatusBytes, parseTsplStatus;
export 'src/printer/printer.dart';
export 'src/printer/sunmi_transport.dart';
export 'src/printer/windows_spooler.dart';
export 'src/queue/print_queue.dart';
export 'src/raster/layout_rasterizer.dart';
export 'src/raster/mono_bitmap.dart';
export 'src/tspl/label_grid.dart';
export 'src/tspl/label_rasterizer.dart';
export 'src/tspl/tspl_builder.dart';
export 'src/tspl/tspl_decoder.dart';
export 'src/tspl/tspl_renderer.dart';
