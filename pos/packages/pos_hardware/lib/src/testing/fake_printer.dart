import 'dart:async';
import 'dart:collection';
import 'dart:typed_data';

import '../escpos/code_page.dart';
import '../escpos/esc_pos_decoder.dart';
import '../printer/printer.dart';
import '../tspl/tspl_decoder.dart';

/// Xotiradagi soxta printer: yuborilgan baytlarni saqlaydi, xato va holatni "o'ynaydi" (testlar, demo rejim).
class MemoryPrinterTransport implements PrinterTransport {
  MemoryPrinterTransport({
    this.printerStatus = PrinterStatus.ready,
    this.codePage = EscPosCodePage.cp866,
  });

  /// [status] qaytaradigan holat.
  PrinterStatus printerStatus;
  final EscPosCodePage codePage;

  /// Qabul qilingan hujjatlar (kassa qutisi buyrug'i ham).
  final List<Uint8List> received = <Uint8List>[];
  int drawerOpens = 0;
  int sendCalls = 0;
  bool closed = false;

  /// Shu ro'yxatdagi xatolar navbat bilan keyingi yuborishlarda tashlanadi.
  final Queue<PrinterException> _failures = Queue<PrinterException>();

  /// Har yuborish oldidan kutish (sekin printer).
  Duration delay = Duration.zero;

  /// Keyingi [count] ta yuborish [error] bilan tugaydi.
  void failNext(int count, PrinterException error) {
    for (var i = 0; i < count; i++) {
      _failures.add(error);
    }
  }

  /// Oxirgi hujjat buyruqlari.
  EscPosDocument get lastDocument =>
      EscPosDocument.parse(received.last, initial: codePage);

  /// Oxirgi hujjat label printer (TSPL) sifatida.
  TsplDocument get lastLabel => TsplDocument.parse(received.last);

  List<EscPosDocument> get documents => <EscPosDocument>[
    for (final Uint8List b in received)
      EscPosDocument.parse(b, initial: codePage),
  ];

  @override
  Future<void> send(Uint8List bytes) async {
    sendCalls++;
    if (delay > Duration.zero) await Future<void>.delayed(delay);
    if (_failures.isNotEmpty) throw _failures.removeFirst();
    received.add(Uint8List.fromList(bytes));
  }

  @override
  Future<PrinterStatus> status() async => printerStatus;

  @override
  Future<void> openDrawer(Uint8List kickBytes) async {
    await send(kickBytes);
    drawerOpens++;
  }

  @override
  Future<void> close() async {
    closed = true;
  }
}
