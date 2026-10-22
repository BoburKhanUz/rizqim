import 'dart:convert';
import 'dart:typed_data';

import '../raster/mono_bitmap.dart';
import 'code_page.dart';

/// ESC/POS oqimidagi bitta buyruq (emulyator, testlar va "nima chop etildi" jurnali uchun).
sealed class EscPosOp {
  const EscPosOp();
}

final class OpInit extends EscPosOp {
  const OpInit();
}

final class OpCodePage extends EscPosOp {
  const OpCodePage(this.table);
  final int table;
}

final class OpAlign extends EscPosOp {
  const OpAlign(this.value);
  final int value;
}

final class OpBold extends EscPosOp {
  const OpBold(this.on);
  final bool on;
}

final class OpUnderline extends EscPosOp {
  const OpUnderline(this.on);
  final bool on;
}

final class OpInvert extends EscPosOp {
  const OpInvert(this.on);
  final bool on;
}

final class OpSize extends EscPosOp {
  const OpSize(this.width, this.height);
  final int width;
  final int height;
}

final class OpText extends EscPosOp {
  const OpText(this.text);
  final String text;
}

final class OpLineFeed extends EscPosOp {
  const OpLineFeed();
}

final class OpFeed extends EscPosOp {
  const OpFeed(this.lines);
  final int lines;
}

final class OpCut extends EscPosOp {
  const OpCut({required this.partial});
  final bool partial;
}

final class OpDrawerKick extends EscPosOp {
  const OpDrawerKick(this.pin);
  final int pin;
}

final class OpBeep extends EscPosOp {
  const OpBeep();
}

final class OpQrCode extends EscPosOp {
  const OpQrCode(this.data, {required this.moduleSize, required this.level});
  final String data;
  final int moduleSize;
  final int level;
}

final class OpRaster extends EscPosOp {
  const OpRaster(this.image);
  final MonoBitmap image;
}

final class OpStatusRequest extends EscPosOp {
  const OpStatusRequest(this.n);
  final int n;
}

/// Tanilmagan bayt(lar).
final class OpUnknown extends EscPosOp {
  const OpUnknown(this.bytes);
  final List<int> bytes;
}

/// ESC/POS baytlarini buyruqlarga ajratadi. Jadval (`ESC t n`) bo'yicha matn [codePages] dan dekodlanadi.
final class EscPosDocument {
  EscPosDocument._(this.ops);

  factory EscPosDocument.parse(
    List<int> bytes, {
    List<EscPosCodePage> codePages = EscPosCodePage.values,
    EscPosCodePage initial = EscPosCodePage.cp866,
  }) {
    final List<EscPosOp> ops = <EscPosOp>[];
    var page = initial;
    final List<int> text = <int>[];
    // QR parametrlari "store" va "print" orasida saqlanadi
    var qrSize = 3;
    var qrLevel = 48;
    String? qrData;

    void flushText() {
      if (text.isEmpty) return;
      ops.add(OpText(page.decode(text)));
      text.clear();
    }

    int at(int i) => i < bytes.length ? bytes[i] : 0;

    var i = 0;
    while (i < bytes.length) {
      final int b = bytes[i];
      if (b == 0x1B) {
        flushText();
        final int cmd = at(i + 1);
        switch (cmd) {
          case 0x40:
            ops.add(const OpInit());
            i += 2;
          case 0x74:
            final int n = at(i + 2);
            ops.add(OpCodePage(n));
            for (final EscPosCodePage p in codePages) {
              if (p.table == n) page = p;
            }
            i += 3;
          case 0x61:
            ops.add(OpAlign(at(i + 2) % 48));
            i += 3;
          case 0x45:
            ops.add(OpBold(at(i + 2) & 1 == 1));
            i += 3;
          case 0x2D:
            ops.add(OpUnderline(at(i + 2) % 48 != 0));
            i += 3;
          case 0x64:
            ops.add(OpFeed(at(i + 2)));
            i += 3;
          case 0x70:
            ops.add(OpDrawerKick(at(i + 2) % 48));
            i += 5;
          case 0x42:
            ops.add(const OpBeep());
            i += 4;
          default:
            ops.add(OpUnknown(<int>[b, cmd]));
            i += 2;
        }
      } else if (b == 0x1D) {
        flushText();
        final int cmd = at(i + 1);
        switch (cmd) {
          case 0x21:
            final int n = at(i + 2);
            ops.add(OpSize((n >> 4) + 1, (n & 0x0F) + 1));
            i += 3;
          case 0x42:
            ops.add(OpInvert(at(i + 2) & 1 == 1));
            i += 3;
          case 0x56:
            final int m = at(i + 2);
            ops.add(OpCut(partial: m == 1 || m == 49 || m == 66));
            i += (m == 65 || m == 66) ? 4 : 3;
          case 0x76:
            // GS v 0 m xL xH yL yH d...
            final int xBytes = at(i + 4) | (at(i + 5) << 8);
            final int rows = at(i + 6) | (at(i + 7) << 8);
            final int start = i + 8;
            final int end = (start + xBytes * rows).clamp(0, bytes.length);
            final Uint8List data = Uint8List(xBytes * rows)
              ..setRange(0, end - start, bytes.sublist(start, end));
            ops.add(OpRaster(MonoBitmap(xBytes * 8, rows, data)));
            i = start + xBytes * rows;
          case 0x28:
            // GS ( k pL pH cn fn ...
            final int len = at(i + 3) | (at(i + 4) << 8);
            final int fn = at(i + 6);
            final int start = i + 7;
            if (at(i + 2) == 0x6B) {
              switch (fn) {
                case 0x43:
                  qrSize = at(start);
                case 0x45:
                  qrLevel = at(start);
                case 0x50:
                  final int end = (i + 5 + len).clamp(0, bytes.length);
                  qrData = utf8.decode(
                    bytes.sublist(start + 1, end),
                    allowMalformed: true,
                  );
                case 0x51:
                  ops.add(
                    OpQrCode(qrData ?? '', moduleSize: qrSize, level: qrLevel),
                  );
              }
            }
            i += 5 + len;
          default:
            ops.add(OpUnknown(<int>[b, cmd]));
            i += 2;
        }
      } else if (b == 0x10 && at(i + 1) == 0x04) {
        flushText();
        ops.add(OpStatusRequest(at(i + 2)));
        i += 3;
      } else if (b == 0x0A) {
        flushText();
        ops.add(const OpLineFeed());
        i += 1;
      } else if (b < 0x20) {
        flushText();
        ops.add(OpUnknown(<int>[b]));
        i += 1;
      } else {
        text.add(b);
        i += 1;
      }
    }
    flushText();
    return EscPosDocument._(List<EscPosOp>.unmodifiable(ops));
  }

  final List<EscPosOp> ops;

  /// Qog'ozdagi matn qatorlari (rasm va QR o'rnida `[RASM 576x312]`, `[QR ...]`).
  List<String> get lines {
    final List<String> out = <String>[];
    final StringBuffer line = StringBuffer();
    var dirty = false;
    for (final EscPosOp op in ops) {
      switch (op) {
        case OpText():
          line.write(op.text);
          dirty = true;
        case OpLineFeed():
          out.add(line.toString());
          line.clear();
          dirty = false;
        case OpQrCode():
          out.add('[QR ${op.data}]');
        case OpRaster():
          out.add('[RASM ${op.image.width}x${op.image.height}]');
        default:
          break;
      }
    }
    if (dirty) out.add(line.toString());
    return out;
  }

  String get text => lines.join('\n');

  int get cuts => ops.whereType<OpCut>().length;

  int get drawerKicks => ops.whereType<OpDrawerKick>().length;

  List<OpQrCode> get qrCodes => ops.whereType<OpQrCode>().toList();

  List<MonoBitmap> get images =>
      ops.whereType<OpRaster>().map((OpRaster r) => r.image).toList();
}
