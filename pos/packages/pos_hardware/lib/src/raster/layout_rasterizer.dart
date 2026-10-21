import 'dart:io';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/painting.dart';
import 'package:pos_core/pos_core.dart';

import 'mono_bitmap.dart';

/// Matn qatorlarini 1 bitli rasmga chizadi ("matnni rasm sifatida chop etish"): printer jadvalida yo'q belgilar
/// (oʻ, gʻ, ʼ, Қ, Ғ, Ҳ) ham to'g'ri chiqadi. Kenglik qog'oz nuqtalarida (58 mm — 384, 80 mm — 576).
///
/// Har belgi o'z katagiga ([PaperWidth.dots] / [PaperWidth.chars] = 12 nuqta) chiziladi: shrift monospace bo'lmasa
/// ham ustunlar (nom ..... summa) printer matn rejimidagidek tekis turadi. Kattalashtirilgan shrift (2×, 4×) printer
/// kabi kenglik va balandlik bo'yicha cho'ziladi.
class LayoutRasterizer {
  const LayoutRasterizer({
    this.rowHeight = 30,
    this.glyphHeight = 22,
    this.fontFamily,
    this.fontFamilyFallback,
  });

  /// Oddiy qator balandligi, nuqtada (printer standarti ~30).
  final int rowHeight;

  /// Oddiy shriftdagi harf balandligi (font size), nuqtada.
  final double glyphHeight;

  /// Shrift; `null` — platforma monospace shrifti ([defaultFontFamily]).
  final String? fontFamily;
  final List<String>? fontFamilyFallback;

  /// Platformaning monospace shrifti (ilova o'z shriftini qo'shsa, [fontFamily] bilan bering).
  static String get defaultFontFamily {
    if (Platform.isWindows) return 'Consolas';
    if (Platform.isIOS || Platform.isMacOS) return 'Menlo';
    if (Platform.isLinux) return 'DejaVu Sans Mono';
    return 'monospace';
  }

  /// Qatorlar balandligi (nuqtada).
  int heightOf(PrintLine line) => switch (line) {
    PrintText() => rowHeight * line.size.height,
    PrintDivider() => rowHeight,
    PrintFeed() => rowHeight * line.lines,
    PrintQr() => 0,
    PrintCut() => 0,
  };

  /// [lines] (QR va kesish e'tiborsiz) → rasm. Flutter dvigateli kerak (ilovada yoki `flutter test` ichida
  /// `tester.runAsync` bilan).
  Future<MonoBitmap> rasterize(
    List<PrintLine> lines,
    PaperWidth paper, {
    String? fontFamily,
  }) async {
    final int width = paper.dots;
    final int height = lines.fold(1, (int h, PrintLine l) => h + heightOf(l));
    final double cell = paper.dots / paper.chars;
    final String family = fontFamily ?? this.fontFamily ?? defaultFontFamily;

    final ui.PictureRecorder recorder = ui.PictureRecorder();
    final Canvas canvas = Canvas(recorder)
      ..drawRect(
        Rect.fromLTWH(0, 0, width.toDouble(), height.toDouble()),
        Paint()..color = const Color(0xFFFFFFFF),
      );
    final Map<String, TextPainter> glyphs = <String, TextPainter>{};
    final Paint black = Paint()..color = const Color(0xFF000000);

    TextPainter glyph(String ch, double size, bool bold, bool white) {
      final String key = '$ch|$size|$bold|$white';
      return glyphs[key] ??= TextPainter(
        text: TextSpan(
          text: ch,
          style: TextStyle(
            fontFamily: family,
            fontFamilyFallback: fontFamilyFallback,
            fontSize: size,
            height: 1,
            fontWeight: bold ? FontWeight.w700 : FontWeight.w400,
            color: white ? const Color(0xFFFFFFFF) : const Color(0xFF000000),
          ),
        ),
        textDirection: TextDirection.ltr,
      )..layout();
    }

    var top = 0.0;
    for (final PrintLine line in lines) {
      switch (line) {
        case PrintText():
          final PrintTextSize s = line.size;
          final double lineH = (rowHeight * s.height).toDouble();
          final double cellW = cell * s.width;
          final int columns = paper.chars ~/ s.width;
          final List<String> chars = line.text.runes
              .map(String.fromCharCode)
              .toList();
          final int used = chars.length > columns ? columns : chars.length;
          final int startCol = switch (line.align) {
            PrintAlign.left => 0,
            PrintAlign.center => (columns - used) ~/ 2,
            PrintAlign.right => columns - used,
          };
          if (line.inverted) {
            canvas.drawRect(
              Rect.fromLTWH(startCol * cellW, top, used * cellW, lineH),
              black,
            );
          }
          final double size = glyphHeight * s.height;
          for (var i = 0; i < used; i++) {
            if (chars[i] == ' ') continue;
            final TextPainter tp = glyph(
              chars[i],
              size,
              line.bold,
              line.inverted,
            );
            // Printer kabi: kenglik bo'yicha katakka sig'diriladi
            final double maxW = cellW * 0.95;
            final double sx = tp.width > maxW ? maxW / tp.width : 1.0;
            final double x =
                (startCol + i) * cellW + (cellW - tp.width * sx) / 2;
            final double y = top + (lineH - tp.height) / 2;
            canvas
              ..save()
              ..translate(x, y)
              ..scale(sx, 1);
            tp.paint(canvas, Offset.zero);
            canvas.restore();
          }
          top += lineH;
        case PrintDivider():
          final double mid = top + rowHeight / 2;
          if (line.char == '=') {
            canvas
              ..drawRect(Rect.fromLTWH(0, mid - 4, width.toDouble(), 2), black)
              ..drawRect(Rect.fromLTWH(0, mid + 2, width.toDouble(), 2), black);
          } else {
            // uzuq chiziq: 8 nuqta qora, 4 oq
            for (var x = 0.0; x < width; x += 12) {
              canvas.drawRect(Rect.fromLTWH(x, mid - 1, 8, 2), black);
            }
          }
          top += rowHeight;
        case PrintFeed():
          top += rowHeight * line.lines;
        case PrintQr():
        case PrintCut():
          break;
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
