import 'package:pos_core/pos_core.dart';

/// Yorliq katak to'rining nuqtadagi geometriyasi (matn rejimi va rasm rejimi bir xil joylaydi).
abstract final class LabelGrid {
  /// Printerning o'rnatilgan shrifti: 12×20 nuqta.
  static const String font = '2';

  /// Harf qator ichida shuncha nuqta pastroq turadi (qator 28, harf 20 → 4).
  static const int glyphOffset = 4;

  /// Chiziq qator ichida shuncha nuqta pastda, qalinligi.
  static const int barOffset = 13;
  static const int barThickness = 2;

  static int colLeft(int col) => LabelSize.margin + col * LabelSize.cellWidth;

  static int rowTop(int row) => LabelSize.margin + row * LabelSize.rowHeight;
}
