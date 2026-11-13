import 'dart:async';
import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_hardware/testing.dart';

import 'support/golden_text.dart';

/// A fake Android plugin channel: records the calls and answers them with [answer].
class FakeChannel {
  FakeChannel(this.channel, this.answer) {
    messenger.setMockMethodCallHandler(channel, (MethodCall call) async {
      calls.add(call);
      return answer(call);
    });
  }

  final MethodChannel channel;
  Object? Function(MethodCall call) answer;
  final List<MethodCall> calls = <MethodCall>[];

  static TestDefaultBinaryMessenger get messenger =>
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;

  List<String> get methods => <String>[
    for (final MethodCall c in calls) c.method,
  ];

  void dispose() => messenger.setMockMethodCallHandler(channel, null);
}

/// `DLE EOT 2` and `DLE EOT 4` answers of a ready printer (bits 1 and 4 always set).
final Uint8List readyStatus = Uint8List.fromList(const <int>[0x12, 0x12]);

Uint8List bytesOf(String text) =>
    (EscPosBuilder()
          ..init()
          ..textLine(text)
          ..cut())
        .build();

Matcher throwsKind(PrinterErrorKind kind) => throwsA(
  isA<PrinterException>().having((PrinterException e) => e.kind, 'kind', kind),
);

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('printer targets', () {
    test('Bluetooth and USB targets round-trip through JSON', () {
      final BluetoothPrinterTarget bt = BluetoothPrinterTarget(
        ' aa:bb:cc:dd:ee:ff ',
      );
      expect(bt.address, 'AA:BB:CC:DD:EE:FF');
      expect(bt.toJson(), <String, Object?>{
        'type': 'bluetooth',
        'address': 'AA:BB:CC:DD:EE:FF',
      });
      expect(PrinterTarget.fromJson(bt.toJson()), bt);
      expect(bt.description, 'Bluetooth AA:BB:CC:DD:EE:FF');

      const UsbPrinterTarget usb = UsbPrinterTarget('0fe6:811e');
      expect(PrinterTarget.fromJson(usb.toJson()), usb);
      expect(
        PrinterTarget.fromJson(const <String, Object?>{'type': 'usb'}),
        UsbPrinterTarget.any,
      );
      expect(UsbPrinterTarget.any.description, 'USB (Android)');

      final PrinterConfig config = PrinterConfig.fromJson(
        PrinterConfig(
          id: 'bt-1',
          name: 'Cho\'ntak printer',
          target: bt,
          paper: PaperWidth.mm58,
          language: PrinterLanguage.escpos,
        ).toJson(),
      );
      expect(config.target, bt);
      expect(config.paper, PaperWidth.mm58);
    });

    test('the default queue factory picks the native transports', () {
      final PrinterTransport bt = defaultTransportFor(
        PrinterConfig(
          id: 'a',
          name: 'A',
          target: BluetoothPrinterTarget('AA:BB:CC:DD:EE:FF'),
          language: PrinterLanguage.tspl,
        ),
      );
      expect(bt, isA<BluetoothPrinterTransport>());
      expect((bt as BluetoothPrinterTransport).language, PrinterLanguage.tspl);
      expect(
        defaultTransportFor(
          const PrinterConfig(
            id: 'b',
            name: 'B',
            target: UsbPrinterTarget('0fe6:811e'),
          ),
        ),
        isA<UsbPrinterTransport>(),
      );
    });
  });

  group('Bluetooth transport (fake channel)', () {
    late FakeChannel fake;
    late BluetoothPrinterTransport transport;
    final BluetoothPrinterTarget target = BluetoothPrinterTarget(
      '00:11:22:33:44:55',
    );

    setUp(() {
      fake = FakeChannel(
        BluetoothPrinterTransport.defaultChannel,
        (MethodCall c) => c.method == 'query' ? readyStatus : null,
      );
      transport = BluetoothPrinterTransport(target);
    });

    tearDown(() => fake.dispose());

    test(
      'asks for the paper status, then sends the bytes to the MAC',
      () async {
        final Uint8List ticket = bytesOf('Xona 3 - Osh');
        await transport.send(ticket);
        expect(fake.methods, <String>['query', 'send']);
        final Map<Object?, Object?> query =
            fake.calls.first.arguments as Map<Object?, Object?>;
        expect(query['address'], '00:11:22:33:44:55');
        expect(query['bytes'], <int>[0x10, 0x04, 0x02, 0x10, 0x04, 0x04]);
        expect(query['expect'], 2);
        expect(query['timeout_ms'], 600);
        final Map<Object?, Object?> send =
            fake.calls.last.arguments as Map<Object?, Object?>;
        expect(send['bytes'], ticket);
        expect(transport.answersStatus, isTrue);
      },
    );

    test(
      'paper out and cover open stop the job before any byte is printed',
      () async {
        fake.answer = (MethodCall c) => c.method == 'query'
            ? Uint8List.fromList(const <int>[0x32, 0x72])
            : null;
        await expectLater(
          transport.send(bytesOf('A')),
          throwsKind(PrinterErrorKind.paperOut),
        );
        fake.answer = (MethodCall c) => c.method == 'query'
            ? Uint8List.fromList(const <int>[0x16, 0x12])
            : null;
        await expectLater(
          transport.send(bytesOf('A')),
          throwsKind(PrinterErrorKind.coverOpen),
        );
        expect(fake.methods.where((String m) => m == 'send'), isEmpty);
        expect(await transport.status(), PrinterStatus.coverOpen);
      },
    );

    test(
      'a printer that never answers is not asked again before each job',
      () async {
        fake.answer = (MethodCall c) => null;
        await transport.send(bytesOf('A'));
        await transport.send(bytesOf('B'));
        expect(fake.methods, <String>['query', 'send', 'send']);
        expect(transport.answersStatus, isFalse);
        expect(await transport.status(), PrinterStatus.unknown);
      },
    );

    test('TSPL label printers are asked with ESC ! ? (one byte)', () async {
      final BluetoothPrinterTransport label = BluetoothPrinterTransport(
        target,
        language: PrinterLanguage.tspl,
      );
      fake.answer = (MethodCall c) =>
          c.method == 'query' ? Uint8List.fromList(const <int>[0x04]) : null;
      expect(await label.status(), PrinterStatus.paperOut);
      final Map<Object?, Object?> q =
          fake.calls.single.arguments as Map<Object?, Object?>;
      expect(q['bytes'], <int>[0x1B, 0x21, 0x3F]);
      expect(q['expect'], 1);
    });

    test('the cash drawer kick goes out without a paper check', () async {
      fake.answer = (MethodCall c) => c.method == 'query'
          ? Uint8List.fromList(const <int>[0x32, 0x72])
          : null;
      final Uint8List kick = const LayoutRenderer().drawerKick(
        PrinterConfig(id: 'x', name: 'X', target: target),
      );
      await transport.openDrawer(kick);
      expect(fake.methods, <String>['send']);
      expect(
        EscPosDocument.parse(
          (fake.calls.single.arguments as Map<Object?, Object?>)['bytes']!
              as Uint8List,
        ).ops.whereType<OpDrawerKick>(),
        hasLength(1),
      );
    });

    test('a missing permission is asked once, then the job goes on', () async {
      var asked = 0;
      var granted = false;
      fake.answer = (MethodCall c) {
        if (c.method == 'requestPermission') {
          asked++;
          granted = true;
          return true;
        }
        if (!granted) {
          throw PlatformException(code: 'permission_denied');
        }
        return c.method == 'query' ? readyStatus : null;
      };
      await transport.send(bytesOf('A'));
      expect(asked, 1);
      expect(fake.methods, <String>[
        'query',
        'requestPermission',
        'query',
        'send',
      ]);
    });

    test('channel errors become clear Uzbek printer errors', () async {
      Future<PrinterException> errorFor(String code) async {
        fake.answer = (MethodCall c) {
          if (c.method == 'requestPermission') return false;
          throw PlatformException(code: code, message: 'native detail');
        };
        try {
          await transport.send(bytesOf('A'));
        } on PrinterException catch (e) {
          return e;
        }
        fail('no error for $code');
      }

      final PrinterException denied = await errorFor('permission_denied');
      expect(denied.kind, PrinterErrorKind.unsupported);
      expect(denied.message, contains('Yaqin-atrofdagi qurilmalar'));
      expect((await errorFor('bluetooth_off')).kind, PrinterErrorKind.offline);
      final PrinterException notPaired = await errorFor('not_found');
      expect(notPaired.kind, PrinterErrorKind.unsupported);
      expect(notPaired.message, contains('juftlanmagan'));
      final PrinterException connect = await errorFor('connect_failed');
      expect(connect.kind, PrinterErrorKind.offline);
      expect(connect.message, contains('Bluetooth printerga ulanib'));
      expect((await errorFor('write_failed')).kind, PrinterErrorKind.failed);
      // Review P6 C7: part of the document reached the printer: never sent again by itself
      final PrinterException partial = await errorFor('write_partial');
      expect(partial.kind, PrinterErrorKind.interrupted);
      expect(partial.retryable, isFalse);
      expect(partial.message, contains('qisman'));
      expect((await errorFor('timeout')).kind, PrinterErrorKind.timeout);
      expect(
        (await errorFor('unsupported')).kind,
        PrinterErrorKind.unsupported,
      );
      // offline printers are retried by the queue, permission problems are not
      expect((await errorFor('connect_failed')).retryable, isTrue);
      expect((await errorFor('permission_denied')).retryable, isFalse);
      fake.answer = (MethodCall c) =>
          throw PlatformException(code: 'write_failed');
      expect(await transport.status(), PrinterStatus.unknown);
      fake.answer = (MethodCall c) =>
          throw PlatformException(code: 'connect_failed');
      expect(await transport.status(), PrinterStatus.offline);
    });

    test('without the Android plugin the printer is unsupported', () async {
      fake.dispose();
      await expectLater(
        transport.send(bytesOf('A')),
        throwsKind(PrinterErrorKind.unsupported),
      );
      expect(await transport.status(), PrinterStatus.offline);
    });

    test('close drops the open Bluetooth connection', () async {
      await transport.close();
      expect(fake.methods, <String>['disconnect']);
    });

    test(
      'the print queue prints and kicks the drawer through Bluetooth',
      () async {
        final PosPrinters printers = PosPrinters(
          queue: PrintQueue(retryPolicy: RetryPolicy.none),
        );
        final PrinterConfig config = PrinterConfig(
          id: 'bt',
          name: 'Kassa (Bluetooth)',
          target: target,
          textMode: PrintTextMode.text,
        );
        final PrintJob drawer = printers.openDrawer(config);
        await printers.queue.whenSettledOrFailed(drawer);
        expect(drawer.status, PrintJobStatus.done);
        final PrintJob page = await printers.testPrint(
          config,
          now: DateTime(2026, 11, 3, 10),
          storeName: 'Rizqim choyxonasi',
        );
        await printers.queue.whenSettledOrFailed(page);
        expect(page.status, PrintJobStatus.done);
        expect(fake.methods, <String>['send', 'query', 'send']);
        await printers.dispose();
      },
    );
  });

  group('USB transport (fake channel)', () {
    late FakeChannel fake;

    setUp(() {
      fake = FakeChannel(
        UsbPrinterTransport.defaultChannel,
        (MethodCall c) => null,
      );
    });

    tearDown(() => fake.dispose());

    test('sends to the device id; an unplugged printer is offline', () async {
      final UsbPrinterTransport t = UsbPrinterTransport(
        const UsbPrinterTarget('0fe6:811e'),
      );
      await t.send(bytesOf('USB'));
      expect(fake.methods, <String>['query', 'send']);
      expect(
        (fake.calls.last.arguments as Map<Object?, Object?>)['device_id'],
        '0fe6:811e',
      );
      fake.answer = (MethodCall c) =>
          throw PlatformException(code: 'not_found');
      await expectLater(
        t.send(bytesOf('A')),
        throwsKind(PrinterErrorKind.offline),
      );
    });

    test(
      'the USB permission dialog: allowed once, then printing goes on',
      () async {
        var allowed = false;
        fake.answer = (MethodCall c) {
          if (c.method == 'requestPermission') return allowed = true;
          if (!allowed) throw PlatformException(code: 'permission_denied');
          return null;
        };
        final UsbPrinterTransport t = UsbPrinterTransport(
          UsbPrinterTarget.any,
          checkPaperBeforePrint: false,
        );
        await t.send(bytesOf('A'));
        expect(fake.methods, <String>['send', 'requestPermission', 'send']);
        allowed = false;
        fake.answer = (MethodCall c) {
          if (c.method == 'requestPermission') return false;
          throw PlatformException(code: 'permission_denied');
        };
        await expectLater(
          t.send(bytesOf('A')),
          throwsKind(PrinterErrorKind.unsupported),
        );
      },
    );
  });

  group('printer discovery', () {
    test('/24 subnets of the device addresses', () {
      expect(
        subnetsOf(<String>['192.168.1.23', '192.168.1.40', '10.0.0.5', 'x']),
        <String>['192.168.1', '10.0.0'],
      );
    });

    test('finds the LAN printer emulator on port 9100 of the subnet', () async {
      final LanPrinterEmulator emu = await LanPrinterEmulator.start();
      addTearDown(emu.stop);
      final List<(int, int)> progress = <(int, int)>[];
      final PrinterDiscovery d = PrinterDiscovery(
        android: false,
        localAddresses: () async => <String>['127.0.0.9'],
        lanTimeout: const Duration(milliseconds: 300),
      );
      final List<DiscoveredPrinter> found = await d
          .scanLan(
            ports: <int>[emu.port],
            onProgress: (int done, int total) => progress.add((done, total)),
          )
          .toList();
      expect(found, hasLength(1));
      expect(found.single.kind, DiscoveryKind.lan);
      expect(
        found.single.target,
        LanPrinterTarget('127.0.0.1', port: emu.port),
      );
      expect(found.single.name, '127.0.0.1');
      // the device's own address is skipped: 253 hosts
      expect(progress.first, (0, 253));
      expect(progress.last, (253, 253));
      // the scan connected and hung up without printing anything
      await Future<void>.delayed(const Duration(milliseconds: 50));
      expect(emu.prints.every((EmulatedPrint p) => p.bytes.isEmpty), isTrue);
    });

    test('the LAN scan keeps at most `concurrency` probes in flight', () async {
      var inFlight = 0;
      var peak = 0;
      final PrinterDiscovery d = PrinterDiscovery(
        android: false,
        localAddresses: () async => const <String>[],
        concurrency: 8,
        probe: (String host, int port, Duration timeout) async {
          inFlight++;
          peak = inFlight > peak ? inFlight : peak;
          await Future<void>.delayed(const Duration(milliseconds: 2));
          inFlight--;
          return host.endsWith('.50') || host.endsWith('.51');
        },
      );
      final List<DiscoveredPrinter> found = await d
          .scanLan(subnets: <String>['192.168.1'])
          .toList();
      expect(peak, 8);
      expect(found.map((DiscoveredPrinter p) => p.name), <String>[
        '192.168.1.50',
        '192.168.1.51',
      ]);
    });

    test('canceling the LAN scan stops the probes', () async {
      var probes = 0;
      final PrinterDiscovery d = PrinterDiscovery(
        android: false,
        localAddresses: () async => const <String>[],
        concurrency: 4,
        probe: (String host, int port, Duration timeout) async {
          probes++;
          await Future<void>.delayed(const Duration(milliseconds: 5));
          return true;
        },
      );
      final StreamSubscription<DiscoveredPrinter> sub = d
          .scanLan(subnets: <String>['10.0.0'])
          .listen((_) {});
      await Future<void>.delayed(const Duration(milliseconds: 30));
      await sub.cancel();
      final int seen = probes;
      await Future<void>.delayed(const Duration(milliseconds: 60));
      expect(probes, lessThanOrEqualTo(seen + 4));
      expect(probes, lessThan(254));
    });

    test(
      'paired Bluetooth devices, printers first; permission asked once',
      () async {
        var granted = false;
        final FakeChannel bt = FakeChannel(
          BluetoothPrinterTransport.defaultChannel,
          (MethodCall c) {
            if (c.method == 'requestPermission') return granted = true;
            if (!granted) throw PlatformException(code: 'permission_denied');
            return <Object?>[
              <String, Object?>{
                'name': 'JBL Flip',
                'address': '11:11:11:11:11:11',
                'device_class': 0x0414,
              },
              <String, Object?>{
                'name': 'MPT-II',
                'address': '22:22:22:22:22:22',
                'device_class': 0x1F00,
              },
              <String, Object?>{
                'name': 'Printer001',
                'address': '33:33:33:33:33:33',
                'device_class': 0x0680,
              },
            ];
          },
        );
        addTearDown(bt.dispose);
        final PrinterDiscovery d = PrinterDiscovery(android: true);
        final List<DiscoveredPrinter> list = await d.bluetoothPaired();
        expect(bt.methods, <String>[
          'bondedDevices',
          'requestPermission',
          'bondedDevices',
        ]);
        expect(list.map((DiscoveredPrinter p) => p.name), <String>[
          'MPT-II',
          'Printer001',
          'JBL Flip',
        ]);
        expect(list.first.target, BluetoothPrinterTarget('22:22:22:22:22:22'));
        expect(list.last.likelyPrinter, isFalse);

        bt.answer = (MethodCall c) =>
            throw PlatformException(code: 'bluetooth_off');
        await expectLater(
          d.bluetoothPaired(),
          throwsA(
            isA<DiscoveryException>().having(
              (DiscoveryException e) => e.message,
              'message',
              contains("Bluetooth o'chiq"),
            ),
          ),
        );
      },
    );

    test('USB devices with a bulk OUT endpoint, printer class first', () async {
      final FakeChannel usb = FakeChannel(
        UsbPrinterTransport.defaultChannel,
        (MethodCall c) => <Object?>[
          <String, Object?>{
            'device_id': '1a86:7523',
            'manufacturer': null,
            'product': 'USB Serial',
            'printer_class': false,
            'bulk_out': true,
          },
          <String, Object?>{
            'device_id': '046d:c52b',
            'product': 'Receiver',
            'printer_class': false,
            'bulk_out': false,
          },
          <String, Object?>{
            'device_id': '0fe6:811e',
            'manufacturer': 'Xprinter',
            'product': 'XP-80C',
            'printer_class': true,
            'bulk_out': true,
          },
        ],
      );
      addTearDown(usb.dispose);
      final List<DiscoveredPrinter> list = await PrinterDiscovery(android: true)
          .usbDevices();
      expect(list.map((DiscoveredPrinter p) => p.name), <String>[
        'Xprinter XP-80C',
        'USB Serial',
      ]);
      expect(list.first.target, const UsbPrinterTarget('0fe6:811e'));
    });

    test('the Sunmi built-in printer is found by its service', () async {
      final FakeChannel sunmi = FakeChannel(
        SunmiPrinterTransport.defaultChannel,
        (MethodCall c) => switch (c.method) {
          'bind' => true,
          'info' => <String, Object?>{'model': 'T2mini', 'paper': 80},
          _ => null,
        },
      );
      addTearDown(sunmi.dispose);
      final DiscoveredPrinter? p = await PrinterDiscovery(android: true)
          .sunmi();
      expect(p!.name, 'Sunmi T2mini');
      expect(p.target, const SunmiPrinterTarget());
    });

    test('off Android only the LAN is searched', () async {
      final PrinterDiscovery d = PrinterDiscovery(android: false);
      expect(d.nativeSupported, isFalse);
      expect(await d.bluetoothPaired(), isEmpty);
      expect(await d.usbDevices(), isEmpty);
      expect(await d.sunmi(), isNull);
    });

    test('Bluetooth printer hints: the class bit and common names', () {
      expect(isLikelyBluetoothPrinter(deviceClass: 0x0680), isTrue);
      expect(isLikelyBluetoothPrinter(name: 'XP-P323B'), isTrue);
      expect(
        isLikelyBluetoothPrinter(name: 'Galaxy Buds', deviceClass: 0x0404),
        isFalse,
      );
    });

    test('a discovered printer becomes a test config', () {
      final DiscoveredPrinter p = DiscoveredPrinter(
        kind: DiscoveryKind.bluetooth,
        target: BluetoothPrinterTarget('22:22:22:22:22:22'),
        name: 'MPT-II',
      );
      final PrinterConfig c = p.toConfig(
        paper: PaperWidth.mm58,
        language: PrinterLanguage.tspl,
      );
      expect(c.target, p.target);
      expect(c.paper, PaperWidth.mm58);
      expect(c.language, PrinterLanguage.tspl);
      expect(c.id, 'discovered-bluetooth-Bluetooth 22:22:22:22:22:22');
    });
  });

  group('test page', () {
    test('store name, printer, code page line, QR and cut (text snapshot)', () {
      const PrinterConfig config = PrinterConfig(
        id: 'k',
        name: 'Kassa',
        target: LanPrinterTarget('192.168.1.50'),
        textMode: PrintTextMode.text,
      );
      final PrintLayout page = buildTestPage(
        config,
        now: DateTime(2026, 11, 3, 9, 30),
        storeName: 'Rizqim choyxonasi',
      );
      final EscPosDocument doc = EscPosDocument.parse(
        const LayoutRenderer().renderText(page, config),
      );
      final StringBuffer out = StringBuffer();
      for (final String line in doc.lines) {
        out.writeln(line);
      }
      for (final OpQrCode qr in doc.ops.whereType<OpQrCode>()) {
        out.writeln('[QR ${qr.data} module ${qr.moduleSize}]');
      }
      out.writeln('[CUT x${doc.cuts}]');
      expectGoldenText(out.toString(), 'test_page_80mm_cp866');
      expect(doc.lines.first.trim(), 'Rizqim choyxonasi');
      expect(
        doc.lines.any((String l) => l.contains('Kod sahifasi: АБВГДЕЁЖЗ')),
        isTrue,
      );
    });
  });

  group('built-in scanner (Sunmi broadcast)', () {
    test('codes from the event channel, empty ones dropped', () async {
      Object? listenArgs;
      FakeChannel.messenger.setMockStreamHandler(
        BroadcastScanner.defaultChannel,
        MockStreamHandler.inline(
          onListen: (Object? args, MockStreamHandlerEventSink sink) {
            listenArgs = args;
            sink
              ..success(<String, Object?>{
                'code': '4780000000017\n',
                'action': ScanBroadcast.sunmi.action,
              })
              ..success(<String, Object?>{'code': '  '})
              ..success('garbage')
              ..success(<String, Object?>{'code': 'https://rizqim.uz/o/42'})
              ..endOfStream();
          },
        ),
      );
      addTearDown(
        () => FakeChannel.messenger.setMockStreamHandler(
          BroadcastScanner.defaultChannel,
          null,
        ),
      );
      final List<BarcodeScan> scans = await BroadcastScanner().scans.toList();
      expect(scans.map((BarcodeScan s) => s.code), <String>[
        '4780000000017',
        'https://rizqim.uz/o/42',
      ]);
      expect(scans.first.source, ScanSource.broadcast);
      expect(scans.first.action, 'com.sunmi.scanner.ACTION_DATA_CODE_RECEIVED');
      expect(listenArgs, <String, Object?>{
        'actions': <Object?>[
          <String, Object?>{
            'action': 'com.sunmi.scanner.ACTION_DATA_CODE_RECEIVED',
            'extra': 'data',
          },
        ],
      });
    });
  });

  group('customer second screen', () {
    test(
      'show, send and hide over the channel; no plugin — nothing happens',
      () async {
        final CustomerScreen screen = CustomerScreen();
        expect(await screen.show(), isFalse);
        await screen.send(<String, Object?>{'type': 'store'});

        final FakeChannel fake = FakeChannel(
          CustomerScreen.defaultChannel,
          (MethodCall c) => switch (c.method) {
            'show' => true,
            'displays' => <Object?>[
              <String, Object?>{
                'id': 2,
                'name': 'D2 customer',
                'width': 1024,
                'height': 600,
              },
            ],
            _ => null,
          },
        );
        addTearDown(fake.dispose);
        final List<SecondaryDisplay> displays = await screen.displays();
        expect(displays.single.name, 'D2 customer');
        expect(displays.single.width, 1024);
        expect(await screen.show(), isTrue);
        expect(
          (fake.calls.last.arguments as Map<Object?, Object?>)['entrypoint'],
          'customerScreenMain',
        );
        await screen.send(<String, Object?>{'type': 'store', 'name': 'Rizqim'});
        expect(
          jsonDecode(
            (fake.calls.last.arguments as Map<Object?, Object?>)['message']!
                as String,
          ),
          <String, Object?>{'type': 'store', 'name': 'Rizqim'},
        );
        await screen.hide();
        expect(screen.isShown, isFalse);
        await screen.send(<String, Object?>{'type': 'store'});
        expect(fake.methods, <String>['displays', 'show', 'send', 'hide']);
      },
    );

    test(
      'the receiver replays the kept messages, then gets new ones',
      () async {
        const BasicMessageChannel<String?> feed =
            CustomerScreenReceiver.defaultChannel;
        FakeChannel.messenger.setMockDecodedMessageHandler<String?>(feed, (
          String? message,
        ) async {
          expect(message, 'hello');
          return jsonEncode(<String>[
            jsonEncode(<String, Object?>{'type': 'store', 'name': 'Rizqim'}),
            'not json',
            jsonEncode(<String, Object?>{
              'type': 'cart',
              'cart': <String, Object?>{'state': 'cart'},
            }),
          ]);
        });
        addTearDown(
          () => FakeChannel.messenger.setMockDecodedMessageHandler<String?>(
            feed,
            null,
          ),
        );
        final CustomerScreenReceiver receiver = CustomerScreenReceiver();
        final List<Map<String, Object?>> got = <Map<String, Object?>>[];
        receiver.messages.listen(got.add);
        await receiver.start();
        await FakeChannel.messenger.handlePlatformMessage(
          feed.name,
          const StringCodec().encodeMessage(
            jsonEncode(<String, Object?>{'type': 'cart'}),
          ),
          (ByteData? _) {},
        );
        await Future<void>.delayed(Duration.zero);
        expect(got.map((Map<String, Object?> m) => m['type']), <String>[
          'store',
          'cart',
          'cart',
        ]);
        expect(got.first['name'], 'Rizqim');
        await receiver.dispose();
      },
    );

    test('screen messages need a type', () {
      expect(decodeScreenMessage(null), isNull);
      expect(decodeScreenMessage('[]'), isNull);
      expect(decodeScreenMessage('{"name":"x"}'), isNull);
      expect(decodeScreenMessage('{"type":"store"}'), <String, Object?>{
        'type': 'store',
      });
    });
  });
}
