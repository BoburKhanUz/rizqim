import 'dart:typed_data';

import 'package:pos_core/pos_core.dart';

import '../printer/printer.dart';
import '../raster/layout_rasterizer.dart';
import '../raster/mono_bitmap.dart';
import 'esc_pos_builder.dart';

/// `pos_core` joylashuvini ([PrintLayout]) printer baytlariga aylantiradi: matn rejimi (printer shrifti, jadval
/// bo'yicha kodlash) yoki rasm rejimi (matn 1 bitli rasm, QR va kesish — oddiy ESC/POS buyruqlari).
class LayoutRenderer {
  const LayoutRenderer({this.rasterizer = const LayoutRasterizer()});

  final LayoutRasterizer rasterizer;

  /// [config] rejimiga ko'ra rasm kerakmi: `auto` da — matnda printer jadvalida yo'q belgi bo'lsa.
  bool usesImage(PrintLayout layout, PrinterConfig config) =>
      switch (config.textMode) {
        PrintTextMode.text => false,
        PrintTextMode.image => true,
        PrintTextMode.auto => !layout.texts.every(config.codePage.canEncode),
      };

  /// Rejimni o'zi tanlaydi ([usesImage]).
  Future<Uint8List> render(
    PrintLayout layout,
    PrinterConfig config, {
    bool openDrawer = false,
  }) => usesImage(layout, config)
      ? renderImage(layout, config, openDrawer: openDrawer)
      : Future<Uint8List>.value(
          renderText(layout, config, openDrawer: openDrawer),
        );

  /// Matn rejimi (sof Dart, Flutter dvigatelisiz).
  Uint8List renderText(
    PrintLayout layout,
    PrinterConfig config, {
    bool openDrawer = false,
  }) {
    final EscPosBuilder b = EscPosBuilder(codePage: config.codePage)..init();
    if (openDrawer) _drawer(b, config);
    for (final PrintLine line in layout.lines) {
      switch (line) {
        case PrintText():
          b
            ..align(_align(line.align))
            ..bold(line.bold)
            ..size(width: line.size.width, height: line.size.height)
            ..invert(line.inverted)
            ..textLine(line.text);
          if (line.bold) b.bold(false);
          if (!line.size.isNormal) b.size();
          if (line.inverted) b.invert(false);
          if (line.align != PrintAlign.left) b.align(EscPosAlign.left);
        case PrintDivider():
          b.textLine(line.char * layout.paper.chars);
        case PrintFeed():
          b.feed(line.lines);
        case PrintQr():
          _qr(b, line);
        case PrintCut():
          _cut(b, config);
      }
    }
    return b.build();
  }

  /// Rasm rejimi: ketma-ket matn qatorlari bitta rasm bo'lagiga yig'iladi.
  Future<Uint8List> renderImage(
    PrintLayout layout,
    PrinterConfig config, {
    bool openDrawer = false,
  }) async {
    final EscPosBuilder b = EscPosBuilder(codePage: config.codePage)..init();
    if (openDrawer) _drawer(b, config);
    final List<PrintLine> block = <PrintLine>[];

    Future<void> flush() async {
      if (block.isEmpty) return;
      final MonoBitmap image = await rasterizer.rasterize(
        List<PrintLine>.of(block),
        layout.paper,
        fontFamily: config.fontFamily,
      );
      block.clear();
      b.rasterImage(image);
    }

    for (final PrintLine line in layout.lines) {
      switch (line) {
        case PrintText():
        case PrintDivider():
        case PrintFeed():
          block.add(line);
        case PrintQr():
          await flush();
          _qr(b, line);
        case PrintCut():
          await flush();
          _cut(b, config);
      }
    }
    await flush();
    return b.build();
  }

  /// Faqat kassa qutisini ochish baytlari.
  Uint8List drawerKick(PrinterConfig config) =>
      (EscPosBuilder(codePage: config.codePage)
            ..init()
            ..openCashDrawer(pin: config.drawerPin))
          .build();

  void _drawer(EscPosBuilder b, PrinterConfig config) =>
      b.openCashDrawer(pin: config.drawerPin);

  void _qr(EscPosBuilder b, PrintQr line) => b
    ..align(EscPosAlign.center)
    ..qrCode(line.data, moduleSize: line.moduleSize)
    ..newLine()
    ..align(EscPosAlign.left);

  void _cut(EscPosBuilder b, PrinterConfig config) {
    if (config.hasCutter) {
      b
        ..feed(1)
        ..cut();
    } else {
      b.feed(5);
    }
  }

  EscPosAlign _align(PrintAlign a) => switch (a) {
    PrintAlign.left => EscPosAlign.left,
    PrintAlign.center => EscPosAlign.center,
    PrintAlign.right => EscPosAlign.right,
  };
}
