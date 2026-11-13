import 'dart:io';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:pos_hardware/pos_hardware.dart';

/// P6 Windows: the installed printers in "Printerni qidirish" (a fake spooler here) and, on a Windows machine (the
/// CI `windows-build` job), the real `winspool.drv` bindings.
void main() {
  group('Windows printer discovery (fake spooler)', () {
    test('lists the installed printers, receipt printers first and virtual ones last', () async {
      final _ListSpooler spooler = _ListSpooler(<String>[
        'Microsoft Print to PDF',
        'XP-80C',
        'OneNote (Desktop)',
        'POS-58 (redirected 2)',
        'Microsoft XPS Document Writer',
        'Fax',
        'EPSON TM-T20II Receipt',
        'XP-80C',
      ]);
      final PrinterDiscovery d = PrinterDiscovery(
        android: false,
        windows: true,
        spooler: spooler,
      );
      expect(d.windowsSupported, isTrue);
      expect(d.nativeSupported, isFalse);
      final List<DiscoveredPrinter> found = await d.windowsPrinters();
      expect(
        <String>[for (final DiscoveredPrinter p in found) p.name],
        <String>[
          'XP-80C',
          'EPSON TM-T20II Receipt',
          'Microsoft Print to PDF',
          'OneNote (Desktop)',
          'POS-58 (redirected 2)',
          'Microsoft XPS Document Writer',
          'Fax',
        ],
      );
      expect(found.first.kind, DiscoveryKind.windows);
      expect(found.first.target, const WindowsPrinterTarget('XP-80C'));
      expect(found.first.likelyPrinter, isTrue);
      expect(found[2].likelyPrinter, isFalse);
      // the picked printer prints through the spooler under its Windows name
      final PrinterConfig config = found.first.toConfig();
      expect(config.target, const WindowsPrinterTarget('XP-80C'));
      expect(config.id, 'discovered-windows-USB (Windows): XP-80C');
    });

    test(
      'a test print and the drawer kick go through the spooler as RAW',
      () async {
        final _ListSpooler spooler = _ListSpooler(<String>['XP-80C']);
        final PosPrinters printers = PosPrinters(
          queue: PrintQueue(
            transportFor: (PrinterConfig c) => WindowsPrinterTransport(
              c.target as WindowsPrinterTarget,
              spooler: spooler,
            ),
          ),
        );
        addTearDown(printers.dispose);
        final DiscoveredPrinter p = (await PrinterDiscovery(
          windows: true,
          android: false,
          spooler: spooler,
        ).windowsPrinters()).single;
        // text mode: the image mode needs the engine's rasterizer (covered in renderer_test.dart)
        final PrinterConfig config = p.toConfig(textMode: PrintTextMode.text);
        final PrintJob job = await printers.testPrint(
          config,
          now: DateTime(2026, 11, 4, 9, 30),
          storeName: 'Rizqim choyxona',
        );
        final PrintJob done = await printers.queue.whenSettledOrFailed(job);
        expect(done.status, PrintJobStatus.done);
        await printers.queue.whenSettledOrFailed(printers.openDrawer(config));
        expect(spooler.written, hasLength(2));
        expect(spooler.written.first.$1, 'XP-80C');
        expect(spooler.written.first.$2, 'Rizqim POS');
        // the drawer pulse: ESC p
        final List<int> kick = spooler.written.last.$3;
        expect(<int>[
          for (var i = 0; i + 1 < kick.length; i++)
            if (kick[i] == 0x1B && kick[i + 1] == 0x70) i,
        ], isNotEmpty);
      },
    );

    test('outside Windows (or with no spooler) the list is empty, a broken spooler gives a plain message', () async {
      expect(await PrinterDiscovery(windows: false).windowsPrinters(), isEmpty);
      final PrinterDiscovery broken = PrinterDiscovery(
        windows: true,
        android: false,
        spooler: _ListSpooler(
          const <String>[],
          error: const PrinterException(
            PrinterErrorKind.failed,
            'EnumPrinters: xato 1722',
          ),
        ),
      );
      await expectLater(
        broken.windowsPrinters(),
        throwsA(
          isA<DiscoveryException>().having(
            (DiscoveryException e) => e.message,
            'message',
            allOf(
              contains('EnumPrinters: xato 1722'),
              contains('Print Spooler'),
            ),
          ),
        ),
      );
    });

    test('virtual printers and Windows error texts', () {
      expect(isVirtualWindowsPrinter('Microsoft Print to PDF'), isTrue);
      expect(isVirtualWindowsPrinter('Adobe PDF'), isTrue);
      expect(isVirtualWindowsPrinter('Send To OneNote 2016'), isTrue);
      expect(isVirtualWindowsPrinter('XP-58 (copy 1)'), isFalse);
      expect(isVirtualWindowsPrinter('POS-80C'), isFalse);
      expect(windowsErrorText(1801), contains("o'rnatilmagan"));
      expect(windowsErrorText(1722), contains('Print Spooler'));
      expect(windowsErrorText(42), 'xato 42');
    });
  });

  // The real winspool.drv: run by the CI windows-build job (GitHub's Windows runners have the Print Spooler and
  // "Microsoft Print to PDF"; nothing is sent to a real printer).
  group('Windows spooler (real winspool.drv)', () {
    const String missing = 'Rizqim sinov printeri yo‘q 7f3c';

    test('lists the installed printers without throwing', () async {
      final List<String> names = await const FfiRawSpooler().printers();
      expect(names, isA<List<String>>());
      expect(names.where((String n) => n.trim().isEmpty), isEmpty);
      // the discovery wraps the same list
      final List<DiscoveredPrinter> found = await PrinterDiscovery(
        android: false,
      ).windowsPrinters();
      expect(found.length, names.toSet().length);
    });

    test('writing to a printer that is not installed fails clearly (offline, with its name)', () async {
      await expectLater(
        const FfiRawSpooler().writeRaw(
          missing,
          'Rizqim POS',
          Uint8List.fromList(<int>[0x1B, 0x40]),
        ),
        throwsA(
          isA<PrinterException>()
              .having(
                (PrinterException e) => e.kind,
                'kind',
                PrinterErrorKind.offline,
              )
              .having(
                (PrinterException e) => e.message,
                'message',
                contains(missing),
              ),
        ),
      );
      final WindowsPrinterTransport t = WindowsPrinterTransport(
        const WindowsPrinterTarget(missing),
      );
      expect(await t.status(), PrinterStatus.offline);
      await expectLater(
        t.send(Uint8List.fromList(<int>[0x1B, 0x40])),
        throwsA(isA<PrinterException>()),
      );
    });

    test(
      'the print queue reports the missing printer instead of hanging',
      () async {
        final PosPrinters printers = PosPrinters(
          queue: PrintQueue(retryPolicy: RetryPolicy.none),
        );
        addTearDown(printers.dispose);
        const PrinterConfig config = PrinterConfig(
          id: 'w1',
          name: 'Kassa',
          target: WindowsPrinterTarget(missing),
          textMode: PrintTextMode.text,
        );
        final PrintJob job = await printers.testPrint(
          config,
          now: DateTime(2026, 11, 4, 9, 30),
        );
        final PrintJob done = await printers.queue
            .whenSettledOrFailed(job)
            .timeout(const Duration(seconds: 30));
        expect(done.status, isNot(PrintJobStatus.done));
        expect(done.lastError, contains(missing));
        printers.queue.cancel(done.id);
      },
    );
  }, skip: Platform.isWindows ? false : 'Windows only (CI windows-build job)');
}

class _ListSpooler implements RawSpooler {
  _ListSpooler(this.names, {this.error});

  final List<String> names;
  final Object? error;
  final List<(String, String, Uint8List)> written =
      <(String, String, Uint8List)>[];

  @override
  Future<List<String>> printers() async {
    if (error != null) throw error!;
    return names;
  }

  @override
  Future<int> statusBits(String printerName) async => 0;

  @override
  Future<void> writeRaw(
    String printerName,
    String documentName,
    Uint8List bytes,
  ) async => written.add((printerName, documentName, bytes));
}
