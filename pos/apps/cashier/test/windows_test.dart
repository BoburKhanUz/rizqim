import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_hardware/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_cashier/src/hardware/cashier_hardware.dart';
import 'package:rizqim_cashier/src/lan/cashier_lan_hub.dart';
import 'package:rizqim_cashier/src/lan/windows_firewall.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/harness.dart';
import 'support/p22.dart';

/// P6 Windows on the till: "Printerni qidirish" lists the printers installed in Windows (a fake spooler here) and
/// tests them through the spooler; the LAN hub's "no device reached it" hint with the firewall rule.
void main() {
  group('Printerni qidirish (Windows)', () {
    Future<P22> openDiscovery(WidgetTester tester, RawSpooler spooler) async {
      final P22 p = P22(
        hardware: CashierHardware(
          android: false,
          discovery: PrinterDiscovery(
            android: false,
            windows: true,
            spooler: spooler,
            localAddresses: () async => <String>['192.168.1.23'],
            probe: (String host, int port, Duration timeout) async => false,
          ),
          deviceScans: const Stream<BarcodeScan>.empty(),
        ),
      );
      await pumpHome(tester, p.h);
      await openSection(tester, 'Sozlamalar');
      await tester.tap(
        find.byKey(const ValueKey<String>('open-printer-discovery')),
      );
      await tester.pumpAndSettle();
      return p;
    }

    testWidgets(
      'the installed Windows printers come first; virtual ones are marked',
      (WidgetTester tester) async {
        await openDiscovery(
          tester,
          _Spooler(<String>['Microsoft Print to PDF', 'XP-80C', 'POS-58']),
        );
        expect(find.text('Windows printerlari'), findsOneWidget);
        expect(find.text('XP-80C'), findsOneWidget);
        expect(find.text('POS-58'), findsOneWidget);
        expect(find.text('Microsoft Print to PDF'), findsOneWidget);
        expect(find.textContaining('printer emasdir'), findsOneWidget);
        final double pdf = tester
            .getTopLeft(find.text('Microsoft Print to PDF'))
            .dy;
        expect(tester.getTopLeft(find.text('XP-80C')).dy, lessThan(pdf));
        // Android-only lists are not shown on Windows; the LAN scan still is
        expect(find.text('Bluetooth'), findsNothing);
        expect(find.text('Tarmoq (LAN)'), findsOneWidget);
      },
    );

    testWidgets(
      'pick → «Sinov cheki» and «Pul qutisini ochish» go to that Windows printer; the panel hint names it',
      (WidgetTester tester) async {
        final P22 p = await openDiscovery(tester, _Spooler(<String>['XP-80C']));
        await tester.tap(find.text('XP-80C'));
        await tester.pumpAndSettle();
        expect(
          find.byKey(const ValueKey<String>('discovery-windows-hint')),
          findsOneWidget,
        );
        expect(find.textContaining('nomi — «XP-80C»'), findsOneWidget);
        await tester.runAsync(() async {
          await tester.tap(
            find.byKey(const ValueKey<String>('discovery-test-print')),
          );
          await Future<void>.delayed(const Duration(seconds: 2));
        });
        await tester.pumpAndSettle();
        const String id = 'discovered-windows-USB (Windows): XP-80C';
        final MemoryPrinterTransport printer = p.h.printerFor(id);
        expect(printer.received, hasLength(1));
        expect(find.textContaining('Yuborildi: Sinov'), findsOneWidget);
        await tester.tap(
          find.byKey(const ValueKey<String>('discovery-drawer')),
        );
        await tester.pumpAndSettle();
        expect(printer.lastDocument.drawerKicks, 1);
      },
    );

    testWidgets('a stopped Print Spooler is said plainly', (
      WidgetTester tester,
    ) async {
      await openDiscovery(
        tester,
        _Spooler(
          const <String>[],
          error: const PrinterException(
            PrinterErrorKind.failed,
            'EnumPrinters: xato 1722',
          ),
        ),
      );
      expect(find.textContaining('Print Spooler'), findsOneWidget);
    });

    testWidgets('no printer installed: what to do', (
      WidgetTester tester,
    ) async {
      await openDiscovery(tester, _Spooler(const <String>[]));
      expect(
        find.textContaining("Windows'da printer o'rnatilmagan"),
        findsOneWidget,
      );
    });

    testWidgets('golden: the discovery screen on Windows', (
      WidgetTester tester,
    ) async {
      await openDiscovery(
        tester,
        _Spooler(<String>['XP-80C', 'Microsoft Print to PDF']),
      );
      await expectLater(
        find.byType(MaterialApp),
        matchesGoldenFile('goldens/printer_discovery_windows.png'),
      );
    });
  });

  group('Windows firewall', () {
    test(
      'the rule check reads netsh\'s exit code; outside Windows nothing runs',
      () async {
        final List<List<String>> calls = <List<String>>[];
        var exit = 1;
        final WindowsFirewall fw = WindowsFirewall(
          windows: true,
          program: r'C:\Rizqim\rizqim_cashier.exe',
          run: (String exe, List<String> args) async {
            calls.add(<String>[exe, ...args]);
            return ProcessResult(1, exit, '', '');
          },
        );
        expect(await fw.hasRule(), isFalse);
        exit = 0;
        expect(await fw.hasRule(), isTrue);
        expect(calls.first, <String>[
          'netsh',
          'advfirewall',
          'firewall',
          'show',
          'rule',
          'name=Rizqim POS lokal tarmoq',
        ]);

        final WindowsFirewall linux = WindowsFirewall(
          windows: false,
          run: (String exe, List<String> args) => throw StateError('ran $exe'),
        );
        expect(await linux.hasRule(), isNull);
        expect(await linux.allow(18480), isFalse);
      },
    );

    test('«Ruxsat berish» runs one elevated step that replaces only its own rules and allows TCP, UDP and the program '
        'on private and domain networks (review S9)', () async {
      final List<List<String>> calls = <List<String>>[];
      var added = false;
      final WindowsFirewall fw = WindowsFirewall(
        windows: true,
        program: r"C:\Program Files\Rizqim's\rizqim_cashier.exe",
        run: (String exe, List<String> args) async {
          calls.add(<String>[exe, ...args]);
          if (exe == 'powershell.exe') added = true;
          return ProcessResult(1, exe == 'netsh' && !added ? 1 : 0, '', '');
        },
      );
      expect(await fw.allow(18481), isTrue);
      final List<String> ps = calls.firstWhere(
        (List<String> c) => c.first == 'powershell.exe',
      );
      expect(ps.sublist(1, ps.length - 1), <String>[
        '-NoProfile',
        '-NonInteractive',
        '-ExecutionPolicy',
        'Bypass',
        '-EncodedCommand',
      ]);
      final Uint8List utf16 = base64.decode(ps.last);
      final String script = String.fromCharCodes(<int>[
        for (var i = 0; i < utf16.length; i += 2)
          utf16[i] | (utf16[i + 1] << 8),
      ]);
      expect(
        script,
        startsWith("Start-Process -FilePath cmd.exe -ArgumentList '/c "),
      );
      expect(script, endsWith("' -Verb RunAs -Wait -WindowStyle Hidden"));
      // Only the app's own rule is replaced: an IT administrator's rule for the program (a block) stays
      expect(script, isNot(contains('name=all')));
      expect(
        RegExp(r'delete rule name="([^"]*)"')
            .allMatches(script)
            .map((Match m) => m.group(1))
            .toSet(),
        <String>{'Rizqim POS lokal tarmoq'},
      );
      // Never on a public network (a café's guest Wi-Fi): private and domain only
      expect(script, isNot(contains('profile=any')));
      // the quote in the path is doubled for PowerShell
      expect(
        script,
        contains(
          '''program="C:\\Program Files\\Rizqim''s\\rizqim_cashier.exe" enable=yes profile=private,domain''',
        ),
      );
      for (final String protocol in <String>['TCP', 'UDP']) {
        expect(
          script,
          contains(
            'add rule name="Rizqim POS lokal tarmoq" dir=in action=allow '
            'protocol=$protocol localport=18481 profile=private,domain',
          ),
        );
      }
      expect(script, contains('action=allow program="C:\\Program Files'));
      // what an administrator types by hand: the two port rules
      expect(WindowsFirewall.manualCommands(18480), <String>[
        'netsh advfirewall firewall add rule name="Rizqim POS lokal tarmoq" dir=in action=allow protocol=TCP '
            'localport=18480 profile=private,domain',
        'netsh advfirewall firewall add rule name="Rizqim POS lokal tarmoq" dir=in action=allow protocol=UDP '
            'localport=18480 profile=private,domain',
      ]);
    });

    group('the hub\'s "no device" hint', () {
      late RizqimClient client;
      late PosData pos;
      late CashierLanHub hub;

      setUp(() async {
        final DateTime now = DateTime.utc(2026, 11, 4, 9);
        final FakePosAdapter adapter = FakePosAdapter(clock: () => now);
        client = RizqimClient(
          baseUrl: 'https://api.test/api',
          store: MemoryKeyValueStore(),
          dio: Dio()..httpClientAdapter = adapter,
          clock: () => now,
        );
        pos = PosData(client, clock: () => now);
        hub = CashierLanHub(
          session: pos.session,
          auth: client.auth,
          offline: null,
        );
      });

      tearDown(() async {
        hub.dispose();
        await pos.dispose();
      });

      Future<void> pump(WidgetTester tester, WindowsFirewall fw) async {
        await tester.pumpWidget(
          MaterialApp(
            theme: RzTheme.light(),
            home: Scaffold(
              body: LanReachHint(hub: hub, firewall: fw),
            ),
          ),
        );
        await tester.pumpAndSettle();
      }

      testWidgets(
        'Windows without the rule: the warning and «Ruxsat berish» fixes it',
        (WidgetTester tester) async {
          var added = false;
          int? allowedPort;
          await pump(
            tester,
            WindowsFirewall(
              windows: true,
              program: r'C:\Rizqim\rizqim_cashier.exe',
              run: (String exe, List<String> args) async {
                if (exe == 'powershell.exe') {
                  added = true;
                  allowedPort = 18480;
                }
                return ProcessResult(
                  1,
                  exe == 'netsh' && !added ? 1 : 0,
                  '',
                  '',
                );
              },
            ),
          );
          expect(find.textContaining('AP isolation'), findsOneWidget);
          expect(
            find.byKey(const ValueKey<String>('lan-firewall-missing')),
            findsOneWidget,
          );
          expect(find.textContaining('18480-portni'), findsOneWidget);
          await tester.tap(
            find.byKey(const ValueKey<String>('lan-firewall-allow')),
          );
          await tester.pumpAndSettle();
          expect(allowedPort, 18480);
          expect(find.textContaining('Ruxsat berildi'), findsOneWidget);
          expect(
            find.byKey(const ValueKey<String>('lan-firewall-allow')),
            findsNothing,
          );
        },
      );

      testWidgets('UAC refused: it says an administrator must confirm', (
        WidgetTester tester,
      ) async {
        await pump(
          tester,
          WindowsFirewall(
            windows: true,
            program: r'C:\Rizqim\rizqim_cashier.exe',
            run: (String exe, List<String> args) async =>
                ProcessResult(1, 1, '', ''),
          ),
        );
        await tester.tap(
          find.byKey(const ValueKey<String>('lan-firewall-allow')),
        );
        await tester.pumpAndSettle();
        expect(find.textContaining('Ruxsat berilmadi'), findsOneWidget);
        expect(
          find.byKey(const ValueKey<String>('lan-firewall-allow')),
          findsOneWidget,
        );
      });

      testWidgets('the rule is there, or not Windows: no warning', (
        WidgetTester tester,
      ) async {
        await pump(
          tester,
          WindowsFirewall(
            windows: true,
            run: (String exe, List<String> args) async =>
                ProcessResult(1, 0, '', ''),
          ),
        );
        expect(
          find.byKey(const ValueKey<String>('lan-firewall-ok')),
          findsOneWidget,
        );
        expect(
          find.byKey(const ValueKey<String>('lan-firewall-missing')),
          findsNothing,
        );
        // a new hint (the state keeps its firewall)
        await tester.pumpWidget(const SizedBox());
        await pump(tester, WindowsFirewall(windows: false));
        expect(find.textContaining('AP isolation'), findsOneWidget);
        expect(
          find.byKey(const ValueKey<String>('lan-firewall-missing')),
          findsNothing,
        );
        expect(
          find.byKey(const ValueKey<String>('lan-firewall-ok')),
          findsNothing,
        );
      });
    });
  });
}

class _Spooler implements RawSpooler {
  _Spooler(this.names, {this.error});

  final List<String> names;
  final Object? error;

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
  ) async {}
}
