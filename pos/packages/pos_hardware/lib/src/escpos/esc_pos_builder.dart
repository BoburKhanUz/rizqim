import 'dart:convert';
import 'dart:typed_data';

import '../raster/mono_bitmap.dart';
import 'code_page.dart';

/// ESC/POS buyruqlari (Epson TM standarti; Xprinter, Rongta, HPRT, Sunmi ham tushunadi).
abstract final class EscPos {
  static const int esc = 0x1B;
  static const int gs = 0x1D;
  static const int dle = 0x10;
  static const int lf = 0x0A;
  static const int eot = 0x04;
}

/// QR xatolarni tuzatish darajasi (`GS ( k`, fn 169).
enum QrErrorLevel {
  l(48),
  m(49),
  q(50),
  h(51);

  const QrErrorLevel(this.code);
  final int code;
}

/// Qog'ozni kesish turi.
enum CutMode {
  /// To'liq kesish.
  full,

  /// Bitta nuqtada ushlab qoladi (chek osilib turadi) — ko'p printerlarda standart.
  partial,
}

/// ESC/POS baytlarini yig'adi. Har usul `this` qaytaradi:
///
/// ```dart
/// final bytes = (EscPosBuilder(codePage: EscPosCodePage.cp866)
///       ..init()
///       ..align(EscPosAlign.center)
///       ..bold()
///       ..textLine('SOTUV CHEKI')
///       ..cut())
///     .build();
/// ```
class EscPosBuilder {
  EscPosBuilder({this.codePage = EscPosCodePage.cp866});

  /// Matn qaysi jadvalda kodlanadi ([init] dan keyin `ESC t` bilan tanlanadi).
  final EscPosCodePage codePage;
  final BytesBuilder _out = BytesBuilder(copy: false);

  int get length => _out.length;

  EscPosBuilder raw(List<int> bytes) {
    _out.add(bytes);
    return this;
  }

  /// `ESC @` (printerni tiklash) va belgilar jadvalini tanlash (`ESC t n`).
  EscPosBuilder init() {
    raw(<int>[EscPos.esc, 0x40]);
    return selectCodePage(codePage.table);
  }

  /// `ESC t n`.
  EscPosBuilder selectCodePage(int table) =>
      raw(<int>[EscPos.esc, 0x74, table & 0xFF]);

  /// `ESC a n`: 0 chap, 1 markaz, 2 o'ng.
  EscPosBuilder align(EscPosAlign align) =>
      raw(<int>[EscPos.esc, 0x61, align.index]);

  /// `ESC E n`.
  EscPosBuilder bold([bool on = true]) =>
      raw(<int>[EscPos.esc, 0x45, on ? 1 : 0]);

  /// `ESC - n`: tagiga chizish (1 nuqta).
  EscPosBuilder underline([bool on = true]) =>
      raw(<int>[EscPos.esc, 0x2D, on ? 1 : 0]);

  /// `GS B n`: oq matn qora fonda.
  EscPosBuilder invert([bool on = true]) =>
      raw(<int>[EscPos.gs, 0x42, on ? 1 : 0]);

  /// `GS ! n`: kenglik va balandlik ko'paytuvchisi (1–8).
  EscPosBuilder size({int width = 1, int height = 1}) {
    if (width < 1 || width > 8 || height < 1 || height > 8) {
      throw RangeError('ESC/POS character size is 1..8');
    }
    return raw(<int>[EscPos.gs, 0x21, ((width - 1) << 4) | (height - 1)]);
  }

  /// Ikki baravar kenglik va balandlik (yoki oddiy).
  EscPosBuilder doubleSize([bool on = true]) =>
      on ? size(width: 2, height: 2) : size();

  /// Matn ([codePage] bilan kodlanadi), qator oxirisiz.
  EscPosBuilder text(String text) => raw(codePage.encode(text));

  /// Matn va `LF`.
  EscPosBuilder textLine(String text) {
    this.text(text);
    return newLine();
  }

  EscPosBuilder newLine() => raw(const <int>[EscPos.lf]);

  /// `ESC d n`: n qator surish.
  EscPosBuilder feed([int lines = 1]) =>
      raw(<int>[EscPos.esc, 0x64, lines.clamp(0, 255)]);

  /// `GS V 65/66 n`: qog'ozni pichoqqacha surib kesadi (to'liq yoki qisman), so'ng yana [extraFeedDots] nuqta.
  EscPosBuilder cut({CutMode mode = CutMode.partial, int extraFeedDots = 0}) =>
      raw(<int>[
        EscPos.gs,
        0x56,
        mode == CutMode.full ? 65 : 66,
        extraFeedDots.clamp(0, 255),
      ]);

  /// `ESC p m t1 t2`: kassa qutisini ochish. [pin] 0 — ulagichning 2-oyog'i (ko'p qutilar), 1 — 5-oyog'i. Impuls
  /// [onMs] / [offMs] (2 ms qadam bilan).
  EscPosBuilder openCashDrawer({int pin = 0, int onMs = 50, int offMs = 500}) =>
      raw(<int>[
        EscPos.esc,
        0x70,
        pin == 1 ? 1 : 0,
        (onMs ~/ 2).clamp(1, 255),
        (offMs ~/ 2).clamp(1, 255),
      ]);

  /// `ESC B n t`: signal (n marta). Hamma printerda yo'q; tushunmagan printer e'tiborsiz qoldiradi.
  EscPosBuilder beep({int times = 1, int duration = 3}) =>
      raw(<int>[EscPos.esc, 0x42, times.clamp(1, 9), duration.clamp(1, 9)]);

  /// QR kod (`GS ( k`): model 2, katak [moduleSize] nuqta (1–16), xato darajasi [level]. Ma'lumot UTF-8.
  EscPosBuilder qrCode(
    String data, {
    int moduleSize = 6,
    QrErrorLevel level = QrErrorLevel.m,
  }) {
    final List<int> payload = utf8.encode(data);
    final int len = payload.length + 3;
    if (len > 7092) throw ArgumentError('QR data is too long');
    // fn 165: model 2
    raw(<int>[EscPos.gs, 0x28, 0x6B, 4, 0, 0x31, 0x41, 0x32, 0x00]);
    // fn 167: module size
    raw(<int>[
      EscPos.gs,
      0x28,
      0x6B,
      3,
      0,
      0x31,
      0x43,
      moduleSize.clamp(1, 16),
    ]);
    // fn 169: error correction
    raw(<int>[EscPos.gs, 0x28, 0x6B, 3, 0, 0x31, 0x45, level.code]);
    // fn 180: store data
    raw(<int>[EscPos.gs, 0x28, 0x6B, len & 0xFF, len >> 8, 0x31, 0x50, 0x30]);
    raw(payload);
    // fn 181: print
    return raw(<int>[EscPos.gs, 0x28, 0x6B, 3, 0, 0x31, 0x51, 0x30]);
  }

  /// 1 bitli rasm (`GS v 0`), [maxBandHeight] qatorli bo'laklarda (ba'zi printerlar katta rasmni bufferga sig'dirmaydi).
  EscPosBuilder rasterImage(MonoBitmap image, {int maxBandHeight = 255}) {
    for (var top = 0; top < image.height; top += maxBandHeight) {
      final MonoBitmap band = image.band(top, maxBandHeight);
      final int xBytes = band.bytesPerRow;
      final int rows = band.height;
      raw(<int>[
        EscPos.gs,
        0x76,
        0x30,
        0, // m = 0: oddiy o'lcham
        xBytes & 0xFF,
        xBytes >> 8,
        rows & 0xFF,
        rows >> 8,
      ]);
      raw(band.data);
    }
    return this;
  }

  /// `DLE EOT n` holat so'rovi (javob — bitta bayt): 1 printer, 2 offline sababi, 4 qog'oz sensori.
  EscPosBuilder statusRequest(int n) => raw(<int>[EscPos.dle, EscPos.eot, n]);

  Uint8List build() => _out.toBytes();
}

enum EscPosAlign { left, center, right }
