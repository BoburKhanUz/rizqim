import 'dart:typed_data';

import 'package:pos_core/pos_core.dart';

import 'escpos/layout_renderer.dart';
import 'printer/printer.dart';
import 'queue/print_queue.dart';

/// Ilova uchun bitta kirish nuqtasi: hujjatni chizadi ([LayoutRenderer]) va navbatga qo'yadi ([PrintQueue]).
///
/// ```dart
/// final printers = PosPrinters();
/// final job = await printers.print(kitchen, layoutStationTicket(ticket, kitchen.paper));
/// printers.queue.stateChanges.listen(...);           // printer belgisi: "Qog'oz tugadi"
/// ```
class PosPrinters {
  PosPrinters({PrintQueue? queue, this.renderer = const LayoutRenderer()})
    : queue = queue ?? PrintQueue();

  final PrintQueue queue;
  final LayoutRenderer renderer;

  /// Hujjatni chop etish navbatiga qo'yadi. [openDrawer] — naqd to'lovda chek bilan birga kassa qutisi ochiladi.
  Future<PrintJob> print(
    PrinterConfig printer,
    PrintLayout layout, {
    bool openDrawer = false,
  }) async {
    final Uint8List bytes = await renderer.render(
      layout,
      printer,
      openDrawer: openDrawer,
    );
    return queue.submit(printer, bytes, title: layout.title);
  }

  /// Sinov sahifasi (printer sozlamasida "Sinov chop etish").
  Future<PrintJob> testPrint(PrinterConfig printer, {DateTime? now}) =>
      print(printer, buildTestPage(printer, now: now ?? DateTime.now()));

  /// Kassa qutisini ochish (kirim/chiqim, naqd to'lov).
  PrintJob openDrawer(PrinterConfig printer) =>
      queue.openDrawer(printer, renderer.drawerKick(printer));

  Future<PrinterStatus> status(PrinterConfig printer) =>
      queue.checkStatus(printer);

  Future<void> dispose() => queue.dispose();
}

/// Sinov sahifasi: printer nomi va manzili, qog'oz, jadval, o'zbek (lotin va kirill) harflari, ustunlar o'lchagichi,
/// shrift kattaliklari va QR. Lotin "oʻ gʻ ʼ" to'g'ri chiqsa — rasm rejimi ishlayapti; "o' g' '" chiqsa — matn rejimi.
PrintLayout buildTestPage(PrinterConfig printer, {required DateTime now}) {
  final PrintWriter w = PrintWriter(printer.paper);
  final int chars = printer.paper.chars;
  w.center('SINOV CHOP ETISH', bold: true, size: PrintTextSize.large);
  w.divider('=');
  w.text('Printer: ${printer.name}');
  w.text(printer.target.description);
  w.text(
    "Qog'oz: ${printer.paper == PaperWidth.mm58 ? 58 : 80} mm, $chars belgi",
  );
  w.text('Jadval: ${printer.codePage.name} (ESC t ${printer.codePage.table})');
  w.text('Rejim: ${printer.textMode.name}');
  w.text('Vaqt: ${formatReceiptDateTime(now)}');
  w.divider();
  w.text('Lotin: Oʻzbekiston, gʻalla, maʼlumot');
  w.text("ASCII: O'zbekiston, g'alla, ma'lumot");
  w.text('Kirill: Ўзбекистон, қишлоқ, ғалла, ҳаёт');
  w.text('Rus: Съешь же ещё этих мягких булок');
  w.divider();
  final StringBuffer ruler = StringBuffer();
  for (var i = 1; i <= chars; i++) {
    ruler.write(i % 10);
  }
  w.text(ruler.toString());
  w.pair('Osh (devzira) 2.5 kg', '400 000');
  w.text('Chapda');
  w.text('Markazda', align: PrintAlign.center);
  w.text("O'ngda", align: PrintAlign.right);
  w.text('Qalin matn', bold: true);
  w.text('Baland', size: PrintTextSize.tall);
  w.text('Katta', size: PrintTextSize.large);
  w.center(' NUSXA ', inverted: true, bold: true);
  w.feed();
  w.qr('https://rizqim.uz', moduleSize: 5);
  w.center('Printer ishlayapti');
  w.cut();
  return w.build(title: 'Sinov: ${printer.name}');
}
