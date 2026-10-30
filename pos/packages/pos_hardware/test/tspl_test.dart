import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_hardware/testing.dart';

import 'support/golden_text.dart';

const PrinterConfig labelPrinter = PrinterConfig(
  id: 'l1',
  name: 'Stiker',
  target: LanPrinterTarget('192.168.1.60'),
  language: PrinterLanguage.tspl,
);

final DateTime printedAt = DateTime(2026, 11, 20, 12, 15);

CupSticker latte({String? customer, int index = 1, int total = 1}) =>
    CupSticker(
      number: '57',
      itemName: 'Latte',
      variantName: '0,4 l',
      modifiers: const <String>['Sut 0%', 'Vanil sirop'],
      customerName: customer,
      printedAt: printedAt,
      index: index,
      total: total,
    );

BagLabel bag() => BagLabel(
  number: '57',
  itemCount: 3,
  printedAt: printedAt,
  customerName: 'Dilnoza',
  pickupAt: DateTime(2026, 11, 20, 12, 40),
);

/// The command text of a label (CRLF as LF) for a readable golden.
String commandsOf(Uint8List bytes) =>
    latin1.decode(bytes).replaceAll('\r\n', '\n');

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const TsplRenderer renderer = TsplRenderer();

  group('TSPL builder', () {
    test('every command is an ASCII line ending in CRLF', () {
      final Uint8List bytes = renderer.renderText(
        layoutCupSticker(latte(), LabelSize.mm40x30),
      );
      final String text = latin1.decode(bytes);
      expect(text, endsWith('\r\n'));
      expect(text.replaceAll('\r\n', ''), isNot(contains('\n')));
      expect(bytes.every((int b) => b < 0x80), isTrue);
    });

    test('a quote in the text is escaped; Uzbek letters fold to ASCII', () {
      final String line = latin1.decode(
        (TsplBuilder()..text(8, 12, '2', 'Oʻrta "katta" gʻalla')).build(),
      );
      expect(
        line,
        r'TEXT 8,12,"2",0,1,1,"O'
        "'"
        r'rta \["]katta\["] g'
        "'"
        'alla"\r\n',
      );
      expect(tsplCanEncode('Oʻrta gʻalla'), isTrue);
      expect(tsplCanEncode('Латте'), isFalse);
      expect(tsplAscii('Oʻzbek gʻalla'), "O'zbek g'alla");
      expect(tsplAscii('Латте'), '?????');
    });

    test(
      'the bitmap is sent inverted (TSPL: 0 is a black dot) and parsed back',
      () {
        final MonoBitmap image = MonoBitmap(16, 2)
          ..setPixel(0, 0)
          ..setPixel(9, 1);
        final Uint8List bytes =
            (TsplBuilder()
                  ..size(40, 30)
                  ..bitmap(0, 0, image)
                  ..printLabels())
                .build();
        final TsplDocument doc = TsplDocument.parse(bytes);
        final TsplBitmap bitmap = doc.bitmaps.single;
        expect(bitmap.widthBytes, 2);
        expect(bitmap.height, 2);
        expect(bitmap.data.first, 0x7F); // the black dot is a 0 bit
        expect(bitmap.image.pixel(0, 0), isTrue);
        expect(bitmap.image.pixel(9, 1), isTrue);
        expect(bitmap.image.blackPixels, 2);
        expect(doc.copies, 1);
      },
    );

    test('binary data that holds a line feed does not confuse the parser', () {
      final MonoBitmap image = MonoBitmap(8, 3);
      // 0xF5 inverted is 0x0A: the bitmap data contains a line feed byte
      for (final int y in <int>[0, 1, 2]) {
        for (final int x in <int>[0, 1, 2, 3, 5, 7]) {
          image.setPixel(x, y);
        }
      }
      final TsplDocument doc = TsplDocument.parse(
        (TsplBuilder()
              ..bitmap(0, 0, image)
              ..printLabels(copies: 2))
            .build(),
      );
      expect(doc.bitmaps.single.data, <int>[0x0A, 0x0A, 0x0A]);
      expect(doc.bitmaps.single.image.blackPixels, 18);
      expect(doc.copies, 2);
    });
  });

  group('text mode', () {
    test('a fixed cup sticker is exactly these commands (40x30)', () {
      final Uint8List bytes = renderer.renderText(
        layoutCupSticker(latte(), LabelSize.mm40x30),
      );
      expectGoldenText(commandsOf(bytes), 'cup_sticker_40x30_tspl');
    });

    test('a fixed bag label is exactly these commands (58x40, 2 copies)', () {
      final Uint8List bytes = renderer.renderText(
        layoutBagLabel(bag(), LabelSize.mm58x40),
        copies: 2,
      );
      expectGoldenText(commandsOf(bytes), 'bag_label_58x40_tspl');
      expect(TsplDocument.parse(bytes).copies, 2);
    });

    test('options: gap, density and direction reach the header', () {
      final String text = commandsOf(
        renderer.renderText(
          layoutCupSticker(latte(), LabelSize.mm58x40),
          options: const LabelPrintOptions(gapMm: 3, density: 12, direction: 0),
        ),
      );
      expect(
        text,
        startsWith('SIZE 58 mm,40 mm\nGAP 3 mm,0 mm\nDIRECTION 0,0\n'),
      );
      expect(text, contains('DENSITY 12\nCLS\n'));
    });

    test('the emulator draws the same label the template laid out', () {
      for (final LabelSize size in LabelSize.values) {
        final List<LabelLayout> layouts = <LabelLayout>[
          layoutCupSticker(latte(), size),
          layoutCupSticker(
            latte(customer: 'Dilnoza', index: 2, total: 3),
            size,
          ),
          layoutBagLabel(bag(), size),
        ];
        for (final LabelLayout layout in layouts) {
          final TsplDocument doc = TsplDocument.parse(
            renderer.renderText(layout),
          );
          expect(doc.size, size);
          expect(doc.isImage, isFalse);
          expect(
            doc.render(),
            renderLabelPlainText(layout),
            reason: '${layout.title} on ${size.wire}',
          );
        }
      }
    });
  });

  group('mode choice', () {
    test('auto: ASCII and Uzbek apostrophe letters stay text, Cyrillic is an image', () {
      const LabelPrintOptions auto = LabelPrintOptions();
      final LabelLayout uzbek = layoutCupSticker(
        CupSticker(
          number: '5',
          itemName: 'Oʻrik sharbati',
          printedAt: printedAt,
        ),
        LabelSize.mm40x30,
      );
      final LabelLayout cyrillic = layoutCupSticker(
        CupSticker(number: '5', itemName: 'Латте', printedAt: printedAt),
        LabelSize.mm40x30,
      );
      expect(renderer.usesImage(uzbek, auto), isFalse);
      expect(renderer.usesImage(cyrillic, auto), isTrue);
      expect(
        renderer.usesImage(
          uzbek,
          const LabelPrintOptions(textMode: PrintTextMode.image),
        ),
        isTrue,
      );
      expect(
        renderer.usesImage(
          cyrillic,
          const LabelPrintOptions(textMode: PrintTextMode.text),
        ),
        isFalse,
      );
      // the Uzbek letter prints as an ASCII apostrophe
      final String text = commandsOf(renderer.renderText(uzbek));
      expect(text, contains("O'rik"));
    });
  });

  group('image mode', () {
    testWidgets('the whole label becomes one bitmap of the label size', (
      WidgetTester tester,
    ) async {
      final Uint8List bytes = (await tester.runAsync(
        () => renderer.render(
          layoutCupSticker(
            CupSticker(
              number: '57',
              itemName: 'Латте',
              variantName: '0,4 л',
              printedAt: printedAt,
            ),
            LabelSize.mm40x30,
          ),
        ),
      ))!;
      final TsplDocument doc = TsplDocument.parse(bytes);
      expect(doc.isImage, isTrue);
      expect(doc.size, LabelSize.mm40x30);
      final TsplBitmap bitmap = doc.bitmaps.single;
      expect(bitmap.widthBytes * 8, 320);
      expect(bitmap.height, 240);
      expect(bitmap.image.blackPixels, greaterThan(300));
      expect(doc.copies, 1);
      // the bitmap sits right after CLS and before PRINT
      final List<String> names = doc.ops
          .map((TsplOp o) => o.runtimeType.toString())
          .toList();
      expect(names.last, 'TsplPrint');
      expect(names[names.length - 2], 'TsplBitmap');
    });

    testWidgets('an inverted line is mostly black and a bar is drawn', (
      WidgetTester tester,
    ) async {
      final LabelLayout layout = layoutBagLabel(bag(), LabelSize.mm40x30);
      final MonoBitmap bitmap = (await tester.runAsync(
        () => renderer.rasterizer.rasterize(layout),
      ))!;
      final LabelText pickup = layout.texts.firstWhere(
        (LabelText t) => t.inverted,
      );
      final LabelText plain = layout.texts.firstWhere(
        (LabelText t) => !t.inverted && t.row == pickup.row - 1,
      );
      final int top = LabelGrid.rowTop(pickup.row);
      final int left = LabelGrid.colLeft(pickup.col) - 1;
      final int right =
          LabelGrid.colLeft(pickup.col) +
          pickup.cells * LabelSize.cellWidth +
          1;
      // The band is black beyond the text on both sides and top to bottom (the test font draws solid boxes, so the
      // glyphs themselves are not compared)
      expect(bitmap.pixel(left, top + 1), isTrue);
      expect(bitmap.pixel(left, top + LabelSize.rowHeight - 2), isTrue);
      expect(bitmap.pixel(right, top + 1), isTrue);
      // a plain row above has a white margin at the same place
      expect(bitmap.pixel(left, LabelGrid.rowTop(plain.row) + 1), isFalse);

      final int barY =
          LabelGrid.rowTop(layout.bars.single.row) + LabelGrid.barOffset;
      expect(bitmap.pixel(40, barY), isTrue);
      expect(bitmap.pixel(160, barY + 1), isTrue);
    });
  });

  group('printing', () {
    test('a label goes through the print queue to the label printer', () async {
      final MemoryPrinterTransport memory = MemoryPrinterTransport();
      final PosPrinters printers = PosPrinters(
        queue: PrintQueue(transportFor: (PrinterConfig _) => memory),
      );
      final LabelLayout layout = layoutCupSticker(latte(), LabelSize.mm40x30);
      final PrintJob job = await printers.printLabel(
        labelPrinter,
        layout,
        copies: 3,
      );
      await job.done;

      expect(job.status, PrintJobStatus.done);
      expect(job.title, layout.title);
      final TsplDocument doc = memory.lastLabel;
      expect(doc.copies, 3);
      expect(doc.render(), renderLabelPlainText(layout));
      await printers.dispose();
    });

    test('over the LAN the label printer gets TSPL, not a DLE EOT', () async {
      final LanPrinterEmulator emu = await LanPrinterEmulator.start();
      addTearDown(emu.stop);
      final PrinterConfig config = PrinterConfig(
        id: 'l2',
        name: 'Stiker',
        target: LanPrinterTarget('127.0.0.1', port: emu.port),
        language: PrinterLanguage.tspl,
      );
      final PosPrinters printers = PosPrinters(
        queue: PrintQueue(retryPolicy: RetryPolicy.none),
      );
      addTearDown(printers.dispose);

      final Future<EmulatedPrint> next = emu.printed.firstWhere(
        (EmulatedPrint p) => p.bytes.length > 10,
      );
      final LabelLayout layout = layoutBagLabel(bag(), LabelSize.mm58x40);
      await (await printers.printLabel(config, layout)).done;
      final EmulatedPrint received = await next;

      // the paper check is TSPL's own immediate command, never ESC/POS
      expect(received.bytes.contains(0x10), isFalse);
      final TsplDocument doc = received.label;
      expect(doc.ops.first, isA<TsplStatusRequest>());
      expect(doc.render(), renderLabelPlainText(layout));
      expect(doc.copies, 1);
    });

    test('the status byte tells paper out and an open head', () async {
      final LanPrinterEmulator emu = await LanPrinterEmulator.start();
      addTearDown(emu.stop);
      final LanPrinterTransport transport = LanPrinterTransport(
        LanPrinterTarget('127.0.0.1', port: emu.port),
        language: PrinterLanguage.tspl,
        statusTimeout: const Duration(milliseconds: 300),
      );

      expect(await transport.status(), PrinterStatus.ready);
      emu.paperOut = true;
      expect(await transport.status(), PrinterStatus.paperOut);
      emu
        ..paperOut = false
        ..coverOpen = true;
      expect(await transport.status(), PrinterStatus.coverOpen);
      emu
        ..coverOpen = false
        ..jammed = true;
      expect(await transport.status(), PrinterStatus.error);

      // out of paper stops the job before anything is sent
      emu
        ..jammed = false
        ..paperOut = true;
      await expectLater(
        transport.send(Uint8List.fromList(<int>[0x43])),
        throwsA(
          isA<PrinterException>().having(
            (PrinterException e) => e.kind,
            'kind',
            PrinterErrorKind.paperOut,
          ),
        ),
      );
    });

    test('parseTsplStatus', () {
      expect(parseTsplStatus(0x00), PrinterStatus.ready);
      expect(parseTsplStatus(0x20), PrinterStatus.ready); // printing
      expect(parseTsplStatus(0x04), PrinterStatus.paperOut);
      expect(parseTsplStatus(0x01), PrinterStatus.coverOpen);
      expect(parseTsplStatus(0x40), PrinterStatus.coverOpen);
      expect(parseTsplStatus(0x02), PrinterStatus.error);
      expect(parseTsplStatus(0x08), PrinterStatus.error);
    });

    test('the printer language round-trips through the settings', () {
      expect(
        PrinterConfig.fromJson(labelPrinter.toJson()).language,
        PrinterLanguage.tspl,
      );
      final Map<String, Object?> receipt = const PrinterConfig(
        id: 'r',
        name: 'Kassa',
        target: LanPrinterTarget('1.2.3.4'),
      ).toJson();
      expect(receipt.containsKey('language'), isFalse);
      expect(PrinterConfig.fromJson(receipt).language, PrinterLanguage.escpos);
      expect(
        labelPrinter.copyWith(name: 'Boshqa').language,
        PrinterLanguage.tspl,
      );
    });
  });
}
