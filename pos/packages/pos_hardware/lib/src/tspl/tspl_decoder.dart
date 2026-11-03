import 'dart:convert' show latin1;
import 'dart:typed_data';

import 'package:pos_core/pos_core.dart';

import '../raster/mono_bitmap.dart';
import 'label_grid.dart';

/// TSPL oqimidagi bitta buyruq (emulyator, testlar va "nima chop etildi" jurnali uchun).
sealed class TsplOp {
  const TsplOp();
}

final class TsplSize extends TsplOp {
  const TsplSize(this.widthMm, this.heightMm);
  final int widthMm;
  final int heightMm;
}

final class TsplText extends TsplOp {
  const TsplText({
    required this.x,
    required this.y,
    required this.font,
    required this.rotation,
    required this.xMul,
    required this.yMul,
    required this.text,
  });

  final int x;
  final int y;
  final String font;
  final int rotation;
  final int xMul;
  final int yMul;
  final String text;
}

final class TsplBar extends TsplOp {
  const TsplBar(this.x, this.y, this.width, this.height);
  final int x;
  final int y;
  final int width;
  final int height;
}

final class TsplReverse extends TsplOp {
  const TsplReverse(this.x, this.y, this.width, this.height);
  final int x;
  final int y;
  final int width;
  final int height;
}

final class TsplBitmap extends TsplOp {
  TsplBitmap(
    this.x,
    this.y,
    this.widthBytes,
    this.height,
    this.mode,
    this.data,
  );
  final int x;
  final int y;
  final int widthBytes;
  final int height;
  final int mode;

  /// Printerga ketgan baytlar (TSPL da `0` — qora).
  final Uint8List data;

  /// Rasm sifatida (ESC/POS tartibi: `1` — qora).
  MonoBitmap get image => MonoBitmap(
    widthBytes * 8,
    height,
    Uint8List.fromList(data.map((int b) => ~b & 0xFF).toList()),
  );
}

final class TsplPrint extends TsplOp {
  const TsplPrint(this.sets, this.copies);
  final int sets;
  final int copies;
}

/// `ESC !?`: holat so'rovi (darhol buyruq, yorliqning qismi emas).
final class TsplStatusRequest extends TsplOp {
  const TsplStatusRequest();
}

/// Boshqa buyruq (`GAP`, `DIRECTION`, `DENSITY`, `CLS`, ...): to'liq qator.
final class TsplCommand extends TsplOp {
  const TsplCommand(this.line);
  final String line;

  String get name => line.split(RegExp(r'[ ,]')).first;
}

/// TSPL baytlarini buyruqlarga ajratadi: label printer emulyatori va testlar uchun.
class TsplDocument {
  TsplDocument(this.ops);

  factory TsplDocument.parse(List<int> bytes) {
    final List<TsplOp> ops = <TsplOp>[];
    var i = 0;
    while (i < bytes.length) {
      // Darhol buyruq `ESC !?` (holat so'rovi) keyingi buyruq bilan bir qatorda kelishi mumkin
      if (i + 2 < bytes.length &&
          bytes[i] == 0x1B &&
          bytes[i + 1] == 0x21 &&
          bytes[i + 2] == 0x3F) {
        ops.add(const TsplStatusRequest());
        i += 3;
        continue;
      }
      final int start = i;
      var end = i;
      while (end < bytes.length && bytes[end] != 0x0A) {
        end++;
      }
      final String line = latin1
          .decode(bytes.sublist(start, end))
          .replaceAll('\r', '');
      i = end + 1;
      if (line.isEmpty) continue;

      if (line.startsWith('BITMAP ')) {
        // BITMAP x,y,widthBytes,height,mode,<binary>\r\n
        final List<String> head = _split(line, 5);
        final int widthBytes = int.parse(head[2]);
        final int height = int.parse(head[3]);
        // the header ends with its 5th comma, the binary data follows
        var commas = 0;
        var p = start;
        while (commas < 5) {
          if (bytes[p] == 0x2C) commas++;
          p++;
        }
        final int length = widthBytes * height;
        ops.add(
          TsplBitmap(
            int.parse(head[0].substring(7)),
            int.parse(head[1]),
            widthBytes,
            height,
            int.parse(head[4]),
            Uint8List.fromList(bytes.sublist(p, p + length)),
          ),
        );
        i = p + length;
        while (i < bytes.length && (bytes[i] == 0x0D || bytes[i] == 0x0A)) {
          i++;
        }
        continue;
      }
      ops.add(_parseLine(line));
    }
    return TsplDocument(ops);
  }

  final List<TsplOp> ops;

  static List<String> _split(String line, int parts) {
    final List<String> out = <String>[];
    var start = 0;
    for (var n = 0; n < parts; n++) {
      final int comma = line.indexOf(',', start);
      out.add(line.substring(start, comma));
      start = comma + 1;
    }
    return out;
  }

  static TsplOp _parseLine(String line) {
    final RegExpMatch? size = RegExp(r'^SIZE (\d+) mm,(\d+) mm$')
        .firstMatch(line);
    if (size != null) {
      return TsplSize(int.parse(size[1]!), int.parse(size[2]!));
    }
    final RegExpMatch? text = RegExp(
      r'^TEXT (-?\d+),(-?\d+),"([^"]*)",(\d+),(\d+),(\d+),"(.*)"$',
    ).firstMatch(line);
    if (text != null) {
      return TsplText(
        x: int.parse(text[1]!),
        y: int.parse(text[2]!),
        font: text[3]!,
        rotation: int.parse(text[4]!),
        xMul: int.parse(text[5]!),
        yMul: int.parse(text[6]!),
        text: text[7]!.replaceAll(r'\["]', '"'),
      );
    }
    final RegExpMatch? rect = RegExp(
      r'^(BAR|REVERSE) (-?\d+),(-?\d+),(\d+),(\d+)$',
    ).firstMatch(line);
    if (rect != null) {
      final int x = int.parse(rect[2]!);
      final int y = int.parse(rect[3]!);
      final int w = int.parse(rect[4]!);
      final int h = int.parse(rect[5]!);
      return rect[1] == 'BAR' ? TsplBar(x, y, w, h) : TsplReverse(x, y, w, h);
    }
    final RegExpMatch? print = RegExp(r'^PRINT (\d+),(\d+)$').firstMatch(line);
    if (print != null) {
      return TsplPrint(int.parse(print[1]!), int.parse(print[2]!));
    }
    return TsplCommand(line);
  }

  Iterable<T> _all<T extends TsplOp>() => ops.whereType<T>();

  /// Yorliq o'lchami (`SIZE`).
  LabelSize? get size {
    final TsplSize? s = _all<TsplSize>().firstOrNull;
    if (s == null) return null;
    for (final LabelSize size in LabelSize.values) {
      if (size.widthMm == s.widthMm && size.heightMm == s.heightMm) return size;
    }
    return null;
  }

  /// Nechta nusxa chop etiladi.
  int get copies =>
      _all<TsplPrint>().fold(0, (int n, TsplPrint p) => n + p.copies);

  List<TsplBitmap> get bitmaps => _all<TsplBitmap>().toList();

  /// Yorliq rasm sifatida chop etilganmi (matn buyruqlari yo'q).
  bool get isImage => bitmaps.isNotEmpty && _all<TsplText>().isEmpty;

  /// Matn buyruqlaridan [LabelLayout] ni qayta tiklaydi (emulyatorning "chizishi"): qalin (bir nuqta surilgan
  /// ikkinchi o'tish) va teskari (`REVERSE`) birlashtiriladi. Rasm yorlig'i uchun `null`.
  LabelLayout? toLayout() {
    final LabelSize? size = this.size;
    if (size == null || isImage) return null;

    final List<LabelText> texts = <LabelText>[];
    final List<TsplText> raw = _all<TsplText>().toList();
    final List<TsplReverse> reverses = _all<TsplReverse>().toList();
    final Set<int> skip = <int>{};
    for (var i = 0; i < raw.length; i++) {
      if (skip.contains(i)) continue;
      final TsplText t = raw[i];
      var bold = false;
      for (var j = i + 1; j < raw.length; j++) {
        final TsplText u = raw[j];
        if (u.y == t.y && u.x == t.x + 1 && u.text == t.text) {
          bold = true;
          skip.add(j);
          break;
        }
      }
      final int row =
          (t.y - LabelSize.margin - LabelGrid.glyphOffset * t.yMul) ~/
          LabelSize.rowHeight;
      final int col = (t.x - LabelSize.margin) ~/ LabelSize.cellWidth;
      final bool inverted = reverses.any(
        (TsplReverse r) =>
            r.y == LabelGrid.rowTop(row) && r.x == LabelGrid.colLeft(col) - 2,
      );
      texts.add(
        LabelText(
          row,
          col,
          t.text,
          scale: t.xMul,
          bold: bold,
          inverted: inverted,
        ),
      );
    }

    final List<LabelBar> bars = <LabelBar>[
      for (final TsplBar b in _all<TsplBar>())
        LabelBar(
          (b.y - LabelSize.margin - LabelGrid.barOffset) ~/ LabelSize.rowHeight,
        ),
    ];
    return LabelLayout(size, texts, bars: bars);
  }

  /// Emulyator "ekrani": matn yorlig'i uchun `pos_core` bilan bir xil ASCII chizma, rasm yorlig'i uchun `#`/`.`.
  String render() {
    final LabelLayout? layout = toLayout();
    if (layout != null) return renderLabelPlainText(layout);
    final TsplBitmap? bitmap = bitmaps.firstOrNull;
    return bitmap == null ? '' : bitmap.image.toAscii();
  }
}
