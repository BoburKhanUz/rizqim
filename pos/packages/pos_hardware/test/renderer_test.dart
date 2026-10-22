import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_hardware/pos_hardware.dart';

const PrinterConfig kitchen = PrinterConfig(
  id: 'k1',
  name: 'Oshxona',
  target: LanPrinterTarget('192.168.1.50'),
);

PrintLayout sample(PaperWidth paper, {String name = 'Osh (devzira)'}) {
  final PrintWriter w = PrintWriter(paper)
    ..center('SOTUV CHEKI', bold: true, size: PrintTextSize.large)
    ..divider()
    ..pair(name, '400 000')
    ..text('Jami', bold: true, size: PrintTextSize.tall)
    ..center(' NUSXA ', inverted: true)
    ..feed()
    ..qr('https://ofd.soliq.uz/check?t=1')
    ..cut();
  return w.build(title: 'Chek');
}

void main() {
  const LayoutRenderer renderer = LayoutRenderer();

  group('text mode', () {
    test('lines, styles, QR and cut become ESC/POS commands', () {
      final Uint8List bytes = renderer.renderText(
        sample(PaperWidth.mm80),
        kitchen,
      );
      final EscPosDocument doc = EscPosDocument.parse(bytes);
      expect(doc.ops.first, isA<OpInit>());
      expect(doc.lines, <String>[
        'SOTUV CHEKI',
        '-' * 48,
        '${'Osh (devzira)'.padRight(41)}400 000',
        'Jami',
        ' NUSXA ',
        '[QR https://ofd.soliq.uz/check?t=1]',
        '',
      ]);
      expect(doc.ops.whereType<OpInvert>().map((OpInvert o) => o.on), <bool>[
        false,
        false,
        false,
        true,
        false,
      ]);
      expect(doc.cuts, 1);
      expect(doc.drawerKicks, 0);
      // styles are reset after every styled line
      final List<OpSize> sizes = doc.ops.whereType<OpSize>().toList();
      expect(sizes.first.width, 2);
      expect(sizes[1].width, 1);
    });

    test('opens the cash drawer first when asked; no cutter → only feed', () {
      final EscPosDocument doc = EscPosDocument.parse(
        renderer.renderText(
          sample(PaperWidth.mm58),
          kitchen.copyWith(hasCutter: false, drawerPin: 1),
          openDrawer: true,
        ),
      );
      expect(doc.ops[2], isA<OpDrawerKick>());
      expect((doc.ops[2] as OpDrawerKick).pin, 1);
      expect(doc.cuts, 0);
      expect(doc.ops.whereType<OpFeed>().last.lines, 5);
    });

    test('text mode transliterates Uzbek letters the code page lacks', () {
      final EscPosDocument doc = EscPosDocument.parse(
        renderer.renderText(
          sample(PaperWidth.mm80, name: 'Oʻrik sharbati'),
          kitchen,
        ),
      );
      expect(doc.lines[2], startsWith("O'rik sharbati"));
    });
  });

  group('mode choice', () {
    test(
      'auto: text when every character is in the code page, image otherwise',
      () {
        expect(renderer.usesImage(sample(PaperWidth.mm80), kitchen), isFalse);
        expect(
          renderer.usesImage(sample(PaperWidth.mm80, name: 'Oʻrik'), kitchen),
          isTrue,
        );
        expect(
          renderer.usesImage(
            sample(PaperWidth.mm80, name: 'Oʻrik'),
            kitchen.copyWith(textMode: PrintTextMode.text),
          ),
          isFalse,
        );
        expect(
          renderer.usesImage(
            sample(PaperWidth.mm80),
            kitchen.copyWith(textMode: PrintTextMode.image),
          ),
          isTrue,
        );
      },
    );
  });

  group('image mode (text as image)', () {
    testWidgets('text becomes a raster at 576 dots; QR and cut stay commands', (
      WidgetTester tester,
    ) async {
      final Uint8List bytes = (await tester.runAsync(
        () => renderer.render(
          sample(PaperWidth.mm80, name: 'Oʻrik, gʻalla, maʼlumot'),
          kitchen,
        ),
      ))!;
      final EscPosDocument doc = EscPosDocument.parse(bytes);
      expect(doc.ops.whereType<OpText>(), isEmpty);
      expect(doc.images, hasLength(1));
      final MonoBitmap image = doc.images.single;
      expect(image.width, 576);
      // large (x2) title 60 + divider 30 + pair 30 + tall 60 + inverted 30 + feed 30, plus 1
      expect(image.height, 241);
      expect(image.blackPixels, greaterThan(500));
      expect(doc.qrCodes.single.data, 'https://ofd.soliq.uz/check?t=1');
      expect(doc.cuts, 1);
      // QR comes after the image and before the cut
      final int img = doc.ops.indexWhere((EscPosOp o) => o is OpRaster);
      final int qr = doc.ops.indexWhere((EscPosOp o) => o is OpQrCode);
      final int cut = doc.ops.indexWhere((EscPosOp o) => o is OpCut);
      expect(img < qr && qr < cut, isTrue);
    });

    testWidgets('58 mm is 384 dots wide; columns stay in their cells', (
      WidgetTester tester,
    ) async {
      const LayoutRasterizer r = LayoutRasterizer();
      final MonoBitmap bitmap = (await tester.runAsync(
        () => r.rasterize(const <PrintLine>[
          PrintText('${'                               '}X'),
        ], PaperWidth.mm58),
      ))!;
      expect(bitmap.width, 384);
      expect(bitmap.height, 31);
      // only the last 12-dot cell (column 32) has ink
      var minX = 384;
      for (var y = 0; y < bitmap.height; y++) {
        for (var x = 0; x < bitmap.width; x++) {
          if (bitmap.pixel(x, y) && x < minX) minX = x;
        }
      }
      expect(minX, greaterThanOrEqualTo(31 * 12));
    });

    testWidgets('inverted line is mostly black; divider is drawn', (
      WidgetTester tester,
    ) async {
      const LayoutRasterizer r = LayoutRasterizer();
      final MonoBitmap inverted = (await tester.runAsync(
        () => r.rasterize(const <PrintLine>[
          PrintText(' NUSXA ', inverted: true, align: PrintAlign.center),
        ], PaperWidth.mm58),
      ))!;
      // 7 cells × 12 dots × 30 rows of background, minus the (white) letters
      expect(inverted.blackPixels, greaterThan(7 * 12 * 30 ~/ 3));
      final MonoBitmap divider = (await tester.runAsync(
        () =>
            r.rasterize(const <PrintLine>[PrintDivider('=')], PaperWidth.mm58),
      ))!;
      expect(divider.blackPixels, 384 * 4);
    });
  });

  test('test page names the printer, code page and has Uzbek samples', () {
    final PrintLayout page = buildTestPage(
      kitchen,
      now: DateTime(2026, 10, 22, 9, 30),
    );
    final String text = page.texts.join('\n');
    expect(text, contains('Printer: Oshxona'));
    expect(text, contains('LAN 192.168.1.50:9100'));
    expect(text, contains('Jadval: cp866 (ESC t 17)'));
    expect(text, contains('Oʻzbekiston'));
    expect(page.lines.whereType<PrintQr>(), hasLength(1));
    // the Uzbek samples make the auto mode print the test page as an image
    expect(renderer.usesImage(page, kitchen), isTrue);
  });

  test('printer config JSON round trip', () {
    final PrinterConfig c = PrinterConfig.fromJson(
      const PrinterConfig(
        id: 'p2',
        name: 'Kassa',
        target: WindowsPrinterTarget('XP-80C'),
        paper: PaperWidth.mm58,
        codePage: EscPosCodePage.cp1251,
        textMode: PrintTextMode.text,
        hasCutter: false,
        drawerPin: 1,
      ).toJson(),
    );
    expect(c.target, const WindowsPrinterTarget('XP-80C'));
    expect(c.paper, PaperWidth.mm58);
    expect(c.codePage.name, 'cp1251');
    expect(c.codePage.table, 46);
    expect(c.textMode, PrintTextMode.text);
    expect(c.hasCutter, isFalse);
    expect(c.drawerPin, 1);
    expect(
      PrinterTarget.fromJson(
        const LanPrinterTarget('10.0.0.5', port: 9101).toJson(),
      ),
      const LanPrinterTarget('10.0.0.5', port: 9101),
    );
    expect(
      PrinterTarget.fromJson(const <String, Object?>{'type': 'sunmi'}),
      isA<SunmiPrinterTarget>(),
    );
    expect(
      () => PrinterTarget.fromJson(const <String, Object?>{'type': 'x'}),
      throwsFormatException,
    );
  });
}
