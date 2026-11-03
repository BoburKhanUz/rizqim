import 'dart:io';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/painting.dart';
import 'package:pos_core/pos_core.dart';

import '../raster/mono_bitmap.dart';
import 'label_grid.dart';

/// Yorliqni 1 bitli rasmga chizadi (printer shriftida chiqmaydigan belgilar — kirill, oʻ gʻ — uchun). Har belgi o'z
/// katagiga ([LabelSize.cellWidth] × [LabelSize.rowHeight]) chiziladi: matn rejimidagi joylashuv bilan bir xil. Flutter
/// dvigateli kerak (ilovada yoki `flutter test` ichida `tester.runAsync` bilan).
class LabelRasterizer {
  const LabelRasterizer({this.glyphHeight = 22, this.fontFamily});

  /// Oddiy shriftdagi harf balandligi (font size), nuqtada.
  final double glyphHeight;

  /// Shrift; `null` — platforma monospace shrifti.
  final String? fontFamily;

  static String get _defaultFamily {
    if (Platform.isWindows) return 'Consolas';
    if (Platform.isIOS || Platform.isMacOS) return 'Menlo';
    if (Platform.isLinux) return 'DejaVu Sans Mono';
    return 'monospace';
  }

  Future<MonoBitmap> rasterize(LabelLayout layout, {String? fontFamily}) async {
    final int width = layout.size.dotsWidth;
    final int height = layout.size.dotsHeight;
    final String family = fontFamily ?? this.fontFamily ?? _defaultFamily;

    final ui.PictureRecorder recorder = ui.PictureRecorder();
    final Canvas canvas = Canvas(recorder)
      ..drawRect(
        Rect.fromLTWH(0, 0, width.toDouble(), height.toDouble()),
        Paint()..color = const Color(0xFFFFFFFF),
      );
    final Paint black = Paint()..color = const Color(0xFF000000);
    final Map<String, TextPainter> glyphs = <String, TextPainter>{};

    TextPainter glyph(String ch, double size, bool bold, bool white) {
      final String key = '$ch|$size|$bold|$white';
      return glyphs[key] ??= TextPainter(
        text: TextSpan(
          text: ch,
          style: TextStyle(
            fontFamily: family,
            fontSize: size,
            height: 1,
            fontWeight: bold ? FontWeight.w700 : FontWeight.w400,
            color: white ? const Color(0xFFFFFFFF) : const Color(0xFF000000),
          ),
        ),
        textDirection: TextDirection.ltr,
      )..layout();
    }

    for (final LabelBar bar in layout.bars) {
      canvas.drawRect(
        Rect.fromLTWH(
          LabelSize.margin.toDouble(),
          (LabelGrid.rowTop(bar.row) + LabelGrid.barOffset).toDouble(),
          (width - 2 * LabelSize.margin).toDouble(),
          LabelGrid.barThickness.toDouble(),
        ),
        black,
      );
    }

    for (final LabelText t in layout.texts) {
      final double cellW = (LabelSize.cellWidth * t.scale).toDouble();
      final double lineH = (LabelSize.rowHeight * t.scale).toDouble();
      final double top = LabelGrid.rowTop(t.row).toDouble();
      final double left = LabelGrid.colLeft(t.col).toDouble();
      final List<String> chars = t.text.runes.map(String.fromCharCode).toList();
      if (t.inverted) {
        canvas.drawRect(
          Rect.fromLTWH(left - 2, top, chars.length * cellW + 4, lineH),
          black,
        );
      }
      final double size = glyphHeight * t.scale;
      for (var i = 0; i < chars.length; i++) {
        if (chars[i] == ' ') continue;
        final TextPainter tp = glyph(chars[i], size, t.bold, t.inverted);
        final double maxW = cellW * 0.95;
        final double sx = tp.width > maxW ? maxW / tp.width : 1.0;
        final double x = left + i * cellW + (cellW - tp.width * sx) / 2;
        final double y = top + (lineH - tp.height) / 2;
        canvas
          ..save()
          ..translate(x, y)
          ..scale(sx, 1);
        tp.paint(canvas, Offset.zero);
        canvas.restore();
      }
    }

    final ui.Picture picture = recorder.endRecording();
    final ui.Image image = await picture.toImage(width, height);
    try {
      final ByteData? rgba = await image.toByteData(
        format: ui.ImageByteFormat.rawRgba,
      );
      if (rgba == null) throw StateError('Could not read the rendered image');
      return MonoBitmap.fromRgba(rgba.buffer.asUint8List(), width, height);
    } finally {
      image.dispose();
      picture.dispose();
      for (final TextPainter tp in glyphs.values) {
        tp.dispose();
      }
    }
  }
}
