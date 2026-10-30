import 'dart:typed_data';

import 'package:pos_core/pos_core.dart';

import '../printer/printer.dart';
import '../raster/mono_bitmap.dart';
import 'label_grid.dart';
import 'label_rasterizer.dart';
import 'tspl_builder.dart';

/// Yorliq nusxalari sozlamasi.
class LabelPrintOptions {
  const LabelPrintOptions({
    this.textMode = PrintTextMode.auto,
    this.gapMm = 2,
    this.density = 8,
    this.direction = 1,
    this.fontFamily,
  });

  /// `auto`: yorliqda printer shriftida (ASCII) chiqmaydigan belgi (kirill) bo'lsa butun yorliq rasm sifatida;
  /// `text`: doim printer shrifti (yo'q belgi "?"); `image`: doim rasm.
  final PrintTextMode textMode;

  /// Yorliqlar oralig'i (mm).
  final int gapMm;

  /// Qoralik (0–15).
  final int density;

  /// `DIRECTION` (1 — odatiy).
  final int direction;

  /// Rasm rejimidagi shrift.
  final String? fontFamily;
}

/// `pos_core` yorlig'ini ([LabelLayout]) TSPL baytlariga aylantiradi: matn rejimi (printerning 2-shrifti, 12×20 nuqta,
/// `TEXT` buyruqlari) yoki rasm rejimi (yorliq 1 bitli rasm, `BITMAP`). Baytlar mavjud transportlar (LAN 9100, Windows
/// spooler, Sunmi) orqali yuboriladi ([PrintQueue]).
class TsplRenderer {
  const TsplRenderer({this.rasterizer = const LabelRasterizer()});

  final LabelRasterizer rasterizer;

  /// Rasm kerakmi: `auto` da — matnda ASCII ga to'g'ri kelmaydigan belgi bo'lsa.
  bool usesImage(LabelLayout layout, LabelPrintOptions options) =>
      switch (options.textMode) {
        PrintTextMode.text => false,
        PrintTextMode.image => true,
        PrintTextMode.auto => !layout.allText.every(tsplCanEncode),
      };

  /// Rejimni o'zi tanlaydi ([usesImage]). [copies] — bir xil yorliqdan nechta.
  Future<Uint8List> render(
    LabelLayout layout, {
    int copies = 1,
    LabelPrintOptions options = const LabelPrintOptions(),
  }) => usesImage(layout, options)
      ? renderImage(layout, copies: copies, options: options)
      : Future<Uint8List>.value(
          renderText(layout, copies: copies, options: options),
        );

  TsplBuilder _header(LabelLayout layout, LabelPrintOptions options) =>
      TsplBuilder()
        ..size(layout.size.widthMm, layout.size.heightMm)
        ..gap(options.gapMm)
        ..direction(options.direction)
        ..reference(0, 0)
        ..density(options.density)
        ..cls();

  /// Matn rejimi (sof Dart, Flutter dvigatelisiz).
  Uint8List renderText(
    LabelLayout layout, {
    int copies = 1,
    LabelPrintOptions options = const LabelPrintOptions(),
  }) {
    final TsplBuilder b = _header(layout, options);
    final LabelSize size = layout.size;
    for (final LabelBar bar in layout.bars) {
      b.bar(
        LabelSize.margin,
        LabelGrid.rowTop(bar.row) + LabelGrid.barOffset,
        size.dotsWidth - 2 * LabelSize.margin,
        LabelGrid.barThickness,
      );
    }
    for (final LabelText t in layout.texts) {
      final int x = LabelGrid.colLeft(t.col);
      final int y = LabelGrid.rowTop(t.row) + LabelGrid.glyphOffset * t.scale;
      b.text(x, y, LabelGrid.font, t.text, xMul: t.scale, yMul: t.scale);
      // Qalin: bir nuqta surilgan ikkinchi o'tish
      if (t.bold) {
        b.text(x + 1, y, LabelGrid.font, t.text, xMul: t.scale, yMul: t.scale);
      }
      if (t.inverted) {
        b.reverse(
          x - 2,
          LabelGrid.rowTop(t.row),
          t.cells * LabelSize.cellWidth + 4,
          LabelSize.rowHeight * t.scale,
        );
      }
    }
    return (b..printLabels(copies: copies)).build();
  }

  /// Rasm rejimi: butun yorliq bitta rasm.
  Future<Uint8List> renderImage(
    LabelLayout layout, {
    int copies = 1,
    LabelPrintOptions options = const LabelPrintOptions(),
  }) async {
    final MonoBitmap image = await rasterizer.rasterize(
      layout,
      fontFamily: options.fontFamily,
    );
    final TsplBuilder b = _header(layout, options)..bitmap(0, 0, image);
    return (b..printLabels(copies: copies)).build();
  }
}
