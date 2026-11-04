import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_hardware/testing.dart';
import 'package:pos_ui/customer_display.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_cashier/src/features/settings/settings_page.dart';
import 'package:rizqim_cashier/src/hardware/cashier_hardware.dart';
import 'package:rizqim_cashier/src/hardware/customer_screen_app.dart';
import 'package:rizqim_cashier/src/printing/printing_service.dart';

import 'support/harness.dart';
import 'support/p22.dart';

TestDefaultBinaryMessenger get _messenger =>
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;

/// Records the calls on a plugin channel and answers them.
class _Channel {
  _Channel(this.channel, this.answer) {
    _messenger.setMockMethodCallHandler(channel, (MethodCall c) async {
      calls.add(c);
      return answer(c);
    });
    addTearDown(() => _messenger.setMockMethodCallHandler(channel, null));
  }

  final MethodChannel channel;
  Object? Function(MethodCall c) answer;
  final List<MethodCall> calls = <MethodCall>[];

  List<String> get methods => <String>[
    for (final MethodCall c in calls) c.method,
  ];

  List<Map<String, Object?>> get messages => <Map<String, Object?>>[
    for (final MethodCall c in calls)
      if (c.method == 'send')
        jsonDecode((c.arguments as Map<Object?, Object?>)['message']! as String)
            as Map<String, Object?>,
  ];
}

PosPrinterInfo _printer(int id, String connection, {String? address}) =>
    PosPrinterInfo(
      id: id,
      name: 'P$id',
      purpose: 'receipt',
      connection: connection,
      address: address,
    );

/// A discovery that finds 192.168.1.50 on the LAN, two paired Bluetooth devices, a USB printer and the Sunmi one.
PrinterDiscovery _fakeDiscovery() {
  _Channel(
    BluetoothPrinterTransport.defaultChannel,
    (MethodCall c) => <Object?>[
      <String, Object?>{
        'name': 'MPT-II',
        'address': '66:22:01:B3:4F:10',
        'device_class': 0x0680,
      },
      <String, Object?>{
        'name': 'JBL Flip 5',
        'address': '11:22:33:44:55:66',
        'device_class': 0x0414,
      },
    ],
  );
  _Channel(
    UsbPrinterTransport.defaultChannel,
    (MethodCall c) => <Object?>[
      <String, Object?>{
        'device_id': '0fe6:811e',
        'manufacturer': 'Xprinter',
        'product': 'XP-80C',
        'printer_class': true,
        'bulk_out': true,
      },
    ],
  );
  _Channel(
    SunmiPrinterTransport.defaultChannel,
    (MethodCall c) => switch (c.method) {
      'bind' => true,
      'info' => <String, Object?>{'model': 'T2mini'},
      _ => null,
    },
  );
  return PrinterDiscovery(
    android: true,
    localAddresses: () async => <String>['192.168.1.23'],
    probe: (String host, int port, Duration timeout) async =>
        host == '192.168.1.50',
  );
}

void main() {
  test(
    'Android: Bluetooth and USB printers from bootstrap get native targets',
    () {
      final (PrinterConfig? bt, _) = toPrinterConfig(
        _printer(4, 'bluetooth', address: '66:22:01:b3:4f:10'),
        android: true,
      );
      expect(bt!.target, BluetoothPrinterTarget('66:22:01:B3:4F:10'));
      final (PrinterConfig? usb, _) = toPrinterConfig(
        _printer(3, 'usb'),
        android: true,
      );
      expect(usb!.target, UsbPrinterTarget.any);
      final (PrinterConfig? noMac, String? why) = toPrinterConfig(
        _printer(5, 'bluetooth'),
        android: true,
      );
      expect(noMac, isNull);
      expect(why, contains('MAC'));
      // Windows and Linux tills keep the spooler and cannot reach Bluetooth
      final (PrinterConfig? win, _) = toPrinterConfig(_printer(3, 'usb'));
      expect(win!.target, const WindowsPrinterTarget('P3'));
      final (_, String? windowsBt) = toPrinterConfig(
        _printer(4, 'bluetooth', address: 'AA'),
      );
      expect(windowsBt, contains('faqat Android'));
    },
  );

  test(
    'device scans (Sunmi broadcast) and keyboard scans land in one feed',
    () async {
      final StreamController<BarcodeScan> device =
          StreamController<BarcodeScan>();
      final CashierHardware hw = CashierHardware(
        android: false,
        deviceScans: device.stream,
      );
      final List<BarcodeScan> seen = <BarcodeScan>[];
      hw.scans.stream.listen(seen.add);
      device.add(
        const BarcodeScan('4780000000017', source: ScanSource.broadcast),
      );
      await Future<void>.delayed(Duration.zero);
      hw.scans.addKeyboard('ABC-123');
      await Future<void>.delayed(Duration.zero);
      expect(seen.map((BarcodeScan s) => s.code), <String>[
        '4780000000017',
        'ABC-123',
      ]);
      expect(hw.scans.last!.source, ScanSource.keyboard);
      await hw.dispose();
      await device.close();
    },
  );

  test(
    'the second screen gets the store name and every cart, without the server',
    () async {
      TestWidgetsFlutterBinding.ensureInitialized();
      final _Channel screen = _Channel(
        CustomerScreen.defaultChannel,
        (MethodCall c) => c.method == 'show' ? true : null,
      );
      final P22 p = P22();
      final DisplayFeed feed = p.h.data.displayFeed;
      final CashierHardware hw = CashierHardware(
        android: true,
        deviceScans: const Stream<BarcodeScan>.empty(),
      );
      await hw.startCustomerScreen(feed, storeName: 'Rohat choyxonasi');
      feed.publish(
        PosDisplayCart(
          state: PosDisplayScreen.cart,
          lines: <PosDisplayLine>[
            PosDisplayLine(
              name: 'Osh',
              quantity: PosQuantity.parse('2'),
              price: PosMoney.parse('40000'),
              total: PosMoney.parse('80000'),
            ),
          ],
          total: PosMoney.parse('80000'),
        ),
      );
      feed.idle();
      await Future<void>.delayed(Duration.zero);
      expect(screen.methods.first, 'show');
      expect(
        screen.messages.map((Map<String, Object?> m) => m['type']),
        <String>['store', 'cart', 'cart'],
      );
      expect(screen.messages.first['name'], 'Rohat choyxonasi');
      final Map<String, Object?> cart =
          screen.messages[1]['cart']! as Map<String, Object?>;
      expect(cart['total'], '80000.00');
      expect(
        (cart['lines']! as List<Object?>).single,
        containsPair('name', 'Osh'),
      );
      expect(
        (screen.messages[2]['cart']! as Map<String, Object?>)['state'],
        'idle',
      );
      // a second start (a new bootstrap) does not subscribe twice
      await hw.startCustomerScreen(feed, storeName: 'Rohat');
      feed.idle();
      await Future<void>.delayed(Duration.zero);
      await feed.flush();
      expect(
        screen.messages.where((Map<String, Object?> m) => m['type'] == 'cart'),
        hasLength(3),
      );
      await hw.dispose();
      expect(screen.methods.last, 'hide');
    },
  );

  testWidgets(
    'the second screen app: welcome, the cart, «Rahmat!» for 8 s, then welcome again',
    (WidgetTester tester) async {
      const BasicMessageChannel<String?> feed =
          CustomerScreenReceiver.defaultChannel;
      _messenger.setMockDecodedMessageHandler<String?>(
        feed,
        (String? m) async => jsonEncode(<String>[
          jsonEncode(<String, Object?>{
            'type': 'store',
            'name': 'Rohat choyxonasi',
          }),
        ]),
      );
      addTearDown(
        () => _messenger.setMockDecodedMessageHandler<String?>(feed, null),
      );
      Future<void> send(Map<String, Object?> cart) async {
        await _messenger.handlePlatformMessage(
          feed.name,
          const StringCodec().encodeMessage(
            jsonEncode(<String, Object?>{'type': 'cart', 'cart': cart}),
          ),
          (ByteData? _) {},
        );
        await tester.pumpAndSettle();
      }

      setScreen(tester, const Size(1024, 600));
      await tester.pumpWidget(
        CustomerScreenApp(receiver: CustomerScreenReceiver()),
      );
      await tester.pumpAndSettle();
      expect(find.byType(DisplayWelcome), findsOneWidget);
      expect(find.text('Rohat choyxonasi'), findsOneWidget);

      final PosDisplayCart cart = PosDisplayCart(
        state: PosDisplayScreen.cart,
        lines: <PosDisplayLine>[
          PosDisplayLine(
            name: "Lag'mon",
            quantity: PosQuantity.parse('1'),
            price: PosMoney.parse('32000'),
            total: PosMoney.parse('32000'),
          ),
        ],
        subtotal: PosMoney.parse('32000'),
        total: PosMoney.parse('32000'),
      );
      await send(cart.toJson());
      expect(find.byType(CartView), findsOneWidget);
      expect(find.text("Lag'mon"), findsOneWidget);

      await send(
        PosDisplayCart(
          state: PosDisplayScreen.paid,
          total: PosMoney.parse('32000'),
          queueNumber: 17,
        ).toJson(),
      );
      expect(find.byType(PaidView), findsOneWidget);
      await tester.pump(const Duration(seconds: 9));
      await tester.pumpAndSettle();
      expect(find.byType(DisplayWelcome), findsOneWidget);

      // a broken message changes nothing
      await send(<String, Object?>{'state': 42, 'lines': 'x'});
      expect(find.byType(DisplayWelcome), findsOneWidget);
    },
  );

  testWidgets('a keyboard scanner anywhere shows up in Sozlamalar → Skaner', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    await pumpHome(tester, p.h);
    await openSection(tester, 'Sozlamalar');
    // the listener at the app root is the one the scanner types into (its timing is tested in pos_ui)
    tester
        .widget<KeyboardScanListener>(find.byType(KeyboardScanListener))
        .onScan('4780000000017');
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byType(ScannerCard),
      300,
      scrollable: find
          .descendant(
            of: find.byType(SettingsPage),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    expect(find.text('4780000000017'), findsOneWidget);
    expect(find.textContaining('klaviatura skaneri'), findsOneWidget);
  });

  group('Printerni qidirish', () {
    Future<P22> openDiscovery(
      WidgetTester tester, {
      Size size = const Size(1280, 800),
    }) async {
      final P22 p = P22(
        hardware: CashierHardware(
          android: true,
          discovery: _fakeDiscovery(),
          deviceScans: const Stream<BarcodeScan>.empty(),
        ),
      );
      await pumpHome(tester, p.h, size: size);
      await openSection(tester, 'Sozlamalar');
      await tester.tap(
        find.byKey(const ValueKey<String>('open-printer-discovery')),
      );
      await tester.pumpAndSettle();
      return p;
    }

    testWidgets('LAN, Sunmi, Bluetooth (printers first) and USB lists', (
      WidgetTester tester,
    ) async {
      await openDiscovery(tester);
      expect(find.text('Printerni qidirish'), findsWidgets);
      expect(find.text('192.168.1.50'), findsOneWidget);
      expect(find.text('Sunmi T2mini'), findsOneWidget);
      expect(find.text('MPT-II'), findsOneWidget);
      expect(find.text('JBL Flip 5'), findsOneWidget);
      expect(find.textContaining('printer emasdir'), findsOneWidget);
      expect(find.text('Xprinter XP-80C'), findsOneWidget);
      final double mpt = tester.getTopLeft(find.text('MPT-II')).dy;
      expect(tester.getTopLeft(find.text('JBL Flip 5')).dy, greaterThan(mpt));
    });

    testWidgets(
      'pick a LAN printer → «Sinov cheki» with the store name, «Pul qutisini ochish»',
      (WidgetTester tester) async {
        final P22 p = await openDiscovery(tester);
        await tester.tap(find.text('192.168.1.50'));
        await tester.pumpAndSettle();
        expect(find.textContaining('manzil — 192.168.1.50'), findsOneWidget);
        // 58 mm and the test page: it has oʻ and Cyrillic, so it goes out as an image (needs real time)
        await tester.tap(find.text('58 mm'));
        await tester.pumpAndSettle();
        await tester.runAsync(() async {
          await tester.tap(
            find.byKey(const ValueKey<String>('discovery-test-print')),
          );
          await Future<void>.delayed(const Duration(seconds: 2));
        });
        await tester.pumpAndSettle();
        const String id = 'discovered-lan-LAN 192.168.1.50:9100';
        final MemoryPrinterTransport printer = p.h.printerFor(id);
        expect(printer.received, hasLength(1));
        expect(printer.lastDocument.text, contains('[RASM 384x'));
        expect(find.textContaining('Yuborildi: Sinov'), findsOneWidget);

        await tester.tap(
          find.byKey(const ValueKey<String>('discovery-drawer')),
        );
        await tester.pumpAndSettle();
        expect(printer.lastDocument.drawerKicks, 1);
      },
    );

    testWidgets(
      'a label printer gets a TSPL sample sticker, no drawer button',
      (WidgetTester tester) async {
        final P22 p = await openDiscovery(tester);
        await tester.tap(find.text('MPT-II'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Yorliq'));
        await tester.pumpAndSettle();
        expect(
          find.byKey(const ValueKey<String>('discovery-drawer')),
          findsNothing,
        );
        await tester.runAsync(() async {
          await tester.tap(
            find.byKey(const ValueKey<String>('discovery-test-print')),
          );
          await Future<void>.delayed(const Duration(seconds: 2));
        });
        await tester.pumpAndSettle();
        final MemoryPrinterTransport printer = p.h.printerFor(
          'discovered-bluetooth-Bluetooth 66:22:01:B3:4F:10',
        );
        expect(printer.lastLabel.size, isNotNull);
      },
    );

    testWidgets('a failed test print says why and leaves nothing queued', (
      WidgetTester tester,
    ) async {
      final P22 p = await openDiscovery(tester);
      const String id = 'discovered-usb-USB (Android): 0fe6:811e';
      p.h
          .printerFor(id)
          .failNext(
            1,
            const PrinterException(
              PrinterErrorKind.offline,
              'USB printer ulanmagan.',
            ),
          );
      await tester.scrollUntilVisible(find.text('Xprinter XP-80C'), 200);
      await tester.tap(find.text('Xprinter XP-80C'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey<String>('discovery-drawer')));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('Chiqmadi: USB printer ulanmagan.'),
        findsOneWidget,
      );
      expect(p.h.services.printing.printers.queue.jobsOf(id), isEmpty);
    });

    testWidgets('golden: the discovery screen (tablet)', (
      WidgetTester tester,
    ) async {
      await openDiscovery(tester);
      await expectLater(
        find.byType(MaterialApp),
        matchesGoldenFile('goldens/printer_discovery_tablet.png'),
      );
    });

    testWidgets('golden: the test sheet', (WidgetTester tester) async {
      await openDiscovery(tester);
      await tester.tap(find.text('192.168.1.50'));
      await tester.pumpAndSettle();
      await expectLater(
        find.byType(MaterialApp),
        matchesGoldenFile('goldens/printer_discovery_sheet.png'),
      );
    });
  });
}
