import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:pos_hardware/pos_hardware.dart';

List<int> bytesOf(void Function(EscPosBuilder b) build) {
  final EscPosBuilder b = EscPosBuilder();
  build(b);
  return b.build();
}

void main() {
  group('EscPosBuilder bytes', () {
    test('init resets the printer and selects the code page table', () {
      expect(bytesOf((EscPosBuilder b) => b.init()), <int>[
        0x1B, 0x40, // ESC @
        0x1B, 0x74, 17, // ESC t 17 (PC866)
      ]);
      expect(
        (EscPosBuilder(codePage: EscPosCodePage.cp1251)..init()).build(),
        <int>[0x1B, 0x40, 0x1B, 0x74, 46],
      );
      expect(
        (EscPosBuilder(
          codePage: EscPosCodePage.cp866.withTable(73),
        )..init()).build().last,
        73,
      );
    });

    test('alignment, bold, underline, invert, size', () {
      expect(bytesOf((EscPosBuilder b) => b.align(EscPosAlign.center)), <int>[
        0x1B,
        0x61,
        1,
      ]);
      expect(bytesOf((EscPosBuilder b) => b.align(EscPosAlign.right)), <int>[
        0x1B,
        0x61,
        2,
      ]);
      expect(bytesOf((EscPosBuilder b) => b.bold()), <int>[0x1B, 0x45, 1]);
      expect(bytesOf((EscPosBuilder b) => b.bold(false)), <int>[0x1B, 0x45, 0]);
      expect(bytesOf((EscPosBuilder b) => b.underline()), <int>[0x1B, 0x2D, 1]);
      expect(bytesOf((EscPosBuilder b) => b.invert()), <int>[0x1D, 0x42, 1]);
      expect(bytesOf((EscPosBuilder b) => b.doubleSize()), <int>[
        0x1D,
        0x21,
        0x11,
      ]);
      expect(bytesOf((EscPosBuilder b) => b.size(width: 1, height: 2)), <int>[
        0x1D,
        0x21,
        0x01,
      ]);
      expect(bytesOf((EscPosBuilder b) => b.size(width: 4, height: 4)), <int>[
        0x1D,
        0x21,
        0x33,
      ]);
      expect(bytesOf((EscPosBuilder b) => b.size()), <int>[0x1D, 0x21, 0x00]);
      expect(() => EscPosBuilder().size(width: 9), throwsRangeError);
    });

    test('feed, cut, cash drawer kick, beep', () {
      expect(bytesOf((EscPosBuilder b) => b.feed(3)), <int>[0x1B, 0x64, 3]);
      expect(bytesOf((EscPosBuilder b) => b.cut()), <int>[0x1D, 0x56, 66, 0]);
      expect(
        bytesOf(
          (EscPosBuilder b) => b.cut(mode: CutMode.full, extraFeedDots: 10),
        ),
        <int>[0x1D, 0x56, 65, 10],
      );
      // ESC p 0 25 250: pin 2, 50 ms on, 500 ms off
      expect(bytesOf((EscPosBuilder b) => b.openCashDrawer()), <int>[
        0x1B,
        0x70,
        0,
        25,
        250,
      ]);
      expect(bytesOf((EscPosBuilder b) => b.openCashDrawer(pin: 1))[2], 1);
      expect(bytesOf((EscPosBuilder b) => b.beep()), <int>[0x1B, 0x42, 1, 3]);
    });

    test('QR code: model 2, module size, error level, store and print', () {
      const String url = 'https://ofd.soliq.uz/check?t=1';
      final List<int> data = utf8.encode(url);
      final int len = data.length + 3;
      expect(bytesOf((EscPosBuilder b) => b.qrCode(url, moduleSize: 5)), <int>[
        0x1D, 0x28, 0x6B, 4, 0, 0x31, 0x41, 0x32, 0x00, // model 2
        0x1D, 0x28, 0x6B, 3, 0, 0x31, 0x43, 5, // module size
        0x1D, 0x28, 0x6B, 3, 0, 0x31, 0x45, 49, // level M
        0x1D, 0x28, 0x6B, len, 0, 0x31, 0x50, 0x30, ...data, // store
        0x1D, 0x28, 0x6B, 3, 0, 0x31, 0x51, 0x30, // print
      ]);
    });

    test('raster image: GS v 0 header and rows, split into bands', () {
      final MonoBitmap img = MonoBitmap(10, 3)
        ..setPixel(0, 0)
        ..setPixel(9, 2);
      expect(bytesOf((EscPosBuilder b) => b.rasterImage(img)), <int>[
        0x1D, 0x76, 0x30, 0, 2, 0, 3, 0, // 2 bytes per row, 3 rows
        0x80, 0x00, //
        0x00, 0x00,
        0x00, 0x40,
      ]);
      final List<int> banded = bytesOf(
        (EscPosBuilder b) => b.rasterImage(MonoBitmap(8, 5), maxBandHeight: 2),
      );
      final EscPosDocument doc = EscPosDocument.parse(banded);
      expect(doc.images.map((MonoBitmap m) => m.height), <int>[2, 2, 1]);
    });

    test('status request', () {
      expect(bytesOf((EscPosBuilder b) => b.statusRequest(4)), <int>[
        0x10,
        0x04,
        4,
      ]);
    });
  });

  group('code pages', () {
    test('Cyrillic in PC866 and WPC1251', () {
      expect(EscPosCodePage.cp866.encode('Ош'), <int>[0x8E, 0xE8]);
      expect(EscPosCodePage.cp1251.encode('Ош'), <int>[0xCE, 0xF8]);
      expect(EscPosCodePage.cp866.encode('Ўў№'), <int>[0xF6, 0xF7, 0xFC]);
      expect(EscPosCodePage.cp1251.encode('Ўў№'), <int>[0xA1, 0xA2, 0xB9]);
      expect(EscPosCodePage.cp866.decode(<int>[0x8E, 0xE8, 0x20]), 'Ош ');
    });

    test(
      'Uzbek letters that the tables lack are transliterated in text mode',
      () {
        expect(
          latin1.decode(EscPosCodePage.cp866.encode('Oʻzbekiston, maʼlumot')),
          "O'zbekiston, ma'lumot",
        );
        expect(
          EscPosCodePage.cp866.decode(
            EscPosCodePage.cp866.encode('Қишлоқ ғалла ҳа'),
          ),
          'Кишлок галла ха',
        );
        expect(EscPosCodePage.ascii.encode('Ош'), <int>[0x3F, 0x3F]);
        expect(EscPosCodePage.cp866.encode('25 000'), <int>[
          0x32,
          0x35,
          0xFF,
          0x30,
          0x30,
          0x30,
        ]);
        expect(EscPosCodePage.ascii.encode('25 000'), utf8.encode('25 000'));
        expect(EscPosCodePage.cp866.encode('🙂'), <int>[0x3F]);
      },
    );

    test('canEncode tells when the text needs the image path', () {
      expect(EscPosCodePage.cp866.canEncode("O'zbekiston"), isTrue);
      expect(EscPosCodePage.cp866.canEncode('Oʻzbekiston'), isFalse);
      expect(EscPosCodePage.cp866.canEncode('Ўзбекистон'), isTrue);
      expect(EscPosCodePage.cp866.canEncode('Қишлоқ'), isFalse);
      expect(EscPosCodePage.byName('cp1251'), EscPosCodePage.cp1251);
      expect(EscPosCodePage.byName('nope'), EscPosCodePage.cp866);
    });
  });

  group('EscPosDocument', () {
    test('decodes what the builder wrote', () {
      final Uint8List bytes =
          (EscPosBuilder()
                ..init()
                ..align(EscPosAlign.center)
                ..bold()
                ..doubleSize()
                ..textLine('SOTUV CHEKI')
                ..bold(false)
                ..size()
                ..align(EscPosAlign.left)
                ..textLine('Ош 1 x 30 000')
                ..qrCode('https://x.uz/1')
                ..feed(2)
                ..openCashDrawer()
                ..cut())
              .build();
      final EscPosDocument doc = EscPosDocument.parse(bytes);
      expect(doc.lines, <String>[
        'SOTUV CHEKI',
        'Ош 1 x 30 000',
        '[QR https://x.uz/1]',
      ]);
      expect(doc.cuts, 1);
      expect(doc.drawerKicks, 1);
      expect(doc.qrCodes.single.moduleSize, 6);
      expect(doc.ops.whereType<OpSize>().first.width, 2);
      expect(doc.ops.whereType<OpAlign>().first.value, 1);
      expect(doc.ops.first, isA<OpInit>());
    });
  });

  group('MonoBitmap', () {
    test('thresholds RGBA, ignores transparent pixels', () {
      final Uint8List rgba = Uint8List.fromList(<int>[
        0, 0, 0, 255, // black
        255, 255, 255, 255, // white
        100, 100, 100, 255, // dark grey -> black
        0, 0, 0, 0, // transparent -> white
      ]);
      final MonoBitmap m = MonoBitmap.fromRgba(rgba, 4, 1);
      expect(m.toAscii(), '#.#.\n');
      expect(m.blackPixels, 2);
      expect(m.data, <int>[0xA0]);
    });

    test('stack and band', () {
      final MonoBitmap a = MonoBitmap(8, 1)..setPixel(0, 0);
      final MonoBitmap b = MonoBitmap(16, 2)..setPixel(15, 1);
      final MonoBitmap s = MonoBitmap.stack(<MonoBitmap>[a, b]);
      expect(s.width, 16);
      expect(s.height, 3);
      expect(s.pixel(0, 0), isTrue);
      expect(s.pixel(15, 2), isTrue);
      expect(s.band(1, 5).height, 2);
    });
  });

  test('LAN status bytes', () {
    expect(parseStatusBytes(offline: 0x12, paper: 0x12), PrinterStatus.ready);
    expect(
      parseStatusBytes(offline: 0x12, paper: 0x1E),
      PrinterStatus.paperNearEnd,
    );
    expect(
      parseStatusBytes(offline: 0x32, paper: 0x72),
      PrinterStatus.paperOut,
    );
    expect(
      parseStatusBytes(offline: 0x16, paper: 0x12),
      PrinterStatus.coverOpen,
    );
    expect(parseStatusBytes(offline: 0x52, paper: 0x12), PrinterStatus.error);
    expect(parseStatusBytes(offline: 0xFF, paper: 0x12), PrinterStatus.unknown);
  });
}
