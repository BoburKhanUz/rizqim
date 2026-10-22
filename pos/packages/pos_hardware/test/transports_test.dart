import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_hardware/testing.dart';

Uint8List ticket(String text) =>
    (EscPosBuilder()
          ..init()
          ..textLine(text)
          ..cut())
        .build();

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('LAN printer (with the emulator)', () {
    late LanPrinterEmulator emu;
    late LanPrinterTransport transport;

    setUp(() async {
      emu = await LanPrinterEmulator.start();
      transport = LanPrinterTransport(
        LanPrinterTarget('127.0.0.1', port: emu.port),
        statusTimeout: const Duration(milliseconds: 300),
      );
    });

    tearDown(() => emu.stop());

    test('sends the bytes over raw TCP after a paper check', () async {
      final Future<EmulatedPrint> next = emu.printed.firstWhere(
        (EmulatedPrint p) => p.document.lines.isNotEmpty,
      );
      await transport.send(ticket('Xona 3 — Osh 5 kg'));
      final EmulatedPrint p = await next;
      final EscPosDocument doc = p.document;
      expect(
        doc.ops.whereType<OpStatusRequest>().map((OpStatusRequest o) => o.n),
        <int>[2, 4],
      );
      expect(doc.lines, <String>['Xona 3 - Osh 5 kg']);
      expect(doc.cuts, 1);
    });

    test('reports paper out and cover open before printing', () async {
      emu.paperOut = true;
      await expectLater(
        transport.send(ticket('A')),
        throwsA(
          isA<PrinterException>().having(
            (PrinterException e) => e.kind,
            'kind',
            PrinterErrorKind.paperOut,
          ),
        ),
      );
      emu
        ..paperOut = false
        ..coverOpen = true;
      await expectLater(
        transport.send(ticket('A')),
        throwsA(
          isA<PrinterException>().having(
            (PrinterException e) => e.kind,
            'kind',
            PrinterErrorKind.coverOpen,
          ),
        ),
      );
      // nothing but the status queries reached the printer
      await Future<void>.delayed(const Duration(milliseconds: 50));
      expect(
        emu.prints.every((EmulatedPrint p) => p.document.lines.isEmpty),
        isTrue,
      );
    });

    test(
      'status: ready, paper near end, unknown without an answer, offline',
      () async {
        expect(await transport.status(), PrinterStatus.ready);
        emu.paperNearEnd = true;
        expect(await transport.status(), PrinterStatus.paperNearEnd);
        emu.answerStatus = false;
        expect(await transport.status(), PrinterStatus.unknown);
        // a printer that does not answer status queries still prints
        final Future<EmulatedPrint> next = emu.printed.firstWhere(
          (EmulatedPrint p) => p.document.lines.isNotEmpty,
        );
        await transport.send(ticket('B'));
        expect((await next).document.lines, <String>['B']);
        await emu.stop();
        expect(await transport.status(), PrinterStatus.offline);
        emu = await LanPrinterEmulator.start(); // for tearDown
      },
    );

    test('unreachable printer is an offline error', () async {
      final int port = emu.port;
      await emu.stop();
      final LanPrinterTransport dead = LanPrinterTransport(
        LanPrinterTarget('127.0.0.1', port: port),
        connectTimeout: const Duration(milliseconds: 500),
      );
      await expectLater(
        dead.send(ticket('X')),
        throwsA(
          isA<PrinterException>().having(
            (PrinterException e) => e.kind,
            'kind',
            PrinterErrorKind.offline,
          ),
        ),
      );
      emu = await LanPrinterEmulator.start();
    });

    test('the queue retries until the printer comes back', () async {
      final int port = emu.port;
      final PrinterConfig config = PrinterConfig(
        id: 'lan',
        name: 'Oshxona',
        target: LanPrinterTarget('127.0.0.1', port: port),
      );
      await emu.stop(); // the printer is switched off
      final PrintQueue queue = PrintQueue(
        transportFor: (PrinterConfig c) => LanPrinterTransport(
          c.target as LanPrinterTarget,
          connectTimeout: const Duration(milliseconds: 500),
          statusTimeout: const Duration(milliseconds: 200),
        ),
        // ... and switched on while the queue waits before the next attempt
        sleep: (Duration d) async {
          emu = await LanPrinterEmulator.start(port: port);
        },
      );
      final PosPrinters printers = PosPrinters(queue: queue);
      final PrintLayout layout = layoutStationTicket(
        StationTicket(
          stationName: 'Mangal',
          sentAt: DateTime(2026, 10, 22, 20, 5),
          tableLabel: 'Tapchan 2',
          round: 3,
          items: <StationTicketItem>[
            const StationTicketItem(
              name: "Shashlik (qo'y)",
              quantity: ItemQuantity(PosQuantity.whole(10)),
            ),
          ],
        ),
        PaperWidth.mm80,
      );
      final PrintJob job = await printers.print(config, layout);
      await job.done;
      expect(job.attempts, 2);
      for (var i = 0; i < 100 && emu.prints.isEmpty; i++) {
        await Future<void>.delayed(const Duration(milliseconds: 20));
      }
      final String text = emu.prints.last.document.text;
      expect(text, contains('MANGAL'));
      expect(text, contains('Tapchan 2'));
      expect(text, contains("10 x Shashlik (qo'y)"));
      await queue.dispose();
    });
  });

  group('Sunmi (method channel contract)', () {
    const MethodChannel channel = SunmiPrinterTransport.defaultChannel;
    final List<MethodCall> calls = <MethodCall>[];
    Object? Function(MethodCall call)? reply;

    setUp(() {
      calls.clear();
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(channel, (MethodCall call) async {
            calls.add(call);
            return reply?.call(call);
          });
    });

    tearDown(() {
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(channel, null);
      reply = null;
    });

    test('binds once, sends raw ESC/POS bytes and maps the status', () async {
      reply = (MethodCall c) => switch (c.method) {
        'bind' => true,
        'status' => 4,
        _ => null,
      };
      final SunmiPrinterTransport t = SunmiPrinterTransport();
      final Uint8List bytes = ticket('Chek');
      await t.send(bytes);
      await t.send(bytes);
      expect(calls.map((MethodCall c) => c.method), <String>[
        'bind',
        'sendRaw',
        'sendRaw',
      ]);
      expect((calls[1].arguments as Map<Object?, Object?>)['bytes'], bytes);
      expect(await t.status(), PrinterStatus.paperOut);
      await t.openDrawer(Uint8List(0));
      expect(calls.last.method, 'openDrawer');
    });

    test('not a Sunmi device: unsupported, status offline', () async {
      reply = (MethodCall c) => c.method == 'bind' ? false : null;
      final SunmiPrinterTransport t = SunmiPrinterTransport();
      expect(await t.isAvailable(), isFalse);
      await expectLater(
        t.send(Uint8List(1)),
        throwsA(
          isA<PrinterException>().having(
            (PrinterException e) => e.kind,
            'kind',
            PrinterErrorKind.unsupported,
          ),
        ),
      );
      expect(await t.status(), PrinterStatus.offline);
    });

    test('printer_error with a state code becomes paper out', () async {
      reply = (MethodCall c) {
        if (c.method == 'bind') return true;
        if (c.method == 'sendRaw') {
          throw PlatformException(code: 'printer_error', details: 4);
        }
        if (c.method == 'info') {
          return <String, Object?>{'model': 'T2', 'paper': 80};
        }
        return null;
      };
      final SunmiPrinterTransport t = SunmiPrinterTransport();
      await expectLater(
        t.send(Uint8List(1)),
        throwsA(
          isA<PrinterException>().having(
            (PrinterException e) => e.kind,
            'kind',
            PrinterErrorKind.paperOut,
          ),
        ),
      );
      expect((await t.info())['model'], 'T2');
      expect(statusFromSunmiCode(1), PrinterStatus.ready);
      expect(statusFromSunmiCode(505), PrinterStatus.offline);
      expect(statusFromSunmiCode(6), PrinterStatus.coverOpen);
      expect(statusFromSunmiCode(5), PrinterStatus.error);
    });
  });

  group('Windows spooler', () {
    test('writes RAW bytes through the spooler', () async {
      final _FakeSpooler spooler = _FakeSpooler();
      final WindowsPrinterTransport t = WindowsPrinterTransport(
        const WindowsPrinterTarget('XP-80C'),
        spooler: spooler,
      );
      await t.send(ticket('USB'));
      expect(spooler.written.single.$1, 'XP-80C');
      expect(spooler.written.single.$2, 'Rizqim POS');
      expect(EscPosDocument.parse(spooler.written.single.$3).lines, <String>[
        'USB',
      ]);
      spooler.bits = 0x10;
      expect(await t.status(), PrinterStatus.paperOut);
      spooler.fail = true;
      await expectLater(t.send(ticket('x')), throwsA(isA<PrinterException>()));
    });

    test('spooler status bits', () {
      expect(statusFromSpoolerBits(0), PrinterStatus.unknown);
      expect(statusFromSpoolerBits(0x80), PrinterStatus.offline);
      expect(statusFromSpoolerBits(0x400000), PrinterStatus.coverOpen);
      expect(statusFromSpoolerBits(0x2), PrinterStatus.error);
    });

    test('outside Windows the real spooler is unsupported', () async {
      final WindowsPrinterTransport t = WindowsPrinterTransport(
        const WindowsPrinterTarget('XP-80C'),
      );
      await expectLater(
        t.send(ticket('x')),
        throwsA(
          isA<PrinterException>().having(
            (PrinterException e) => e.kind,
            'kind',
            PrinterErrorKind.unsupported,
          ),
        ),
      );
    }, testOn: '!windows');
  });
}

class _FakeSpooler implements RawSpooler {
  final List<(String, String, Uint8List)> written =
      <(String, String, Uint8List)>[];
  int bits = 0;
  bool fail = false;

  @override
  Future<List<String>> printers() async => <String>['XP-80C'];

  @override
  Future<int> statusBits(String printerName) async => bits;

  @override
  Future<void> writeRaw(
    String printerName,
    String documentName,
    Uint8List bytes,
  ) async {
    if (fail) throw StateError('spooler');
    written.add((printerName, documentName, bytes));
  }
}
