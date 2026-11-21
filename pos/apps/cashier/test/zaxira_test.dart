import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';

import 'support/harness.dart';
import 'support/p22.dart';

/// M5 S4: the settings card "Zaxira" and the offer of the export file before the till is disconnected.
void main() {
  late Directory dir;

  setUp(() => dir = Directory.systemTemp.createTempSync('zaxira_test'));
  tearDown(() {
    if (dir.existsSync()) dir.deleteSync(recursive: true);
  });

  Future<P22> till(
    WidgetTester tester, {
    bool signing = true,
    Directory? Function()? folder,
  }) async {
    final SyncStore store = await SyncStore.open(MemorySyncStorage());
    final P22 p = P22(offlineStore: store, exportDirectory: folder);
    p.state.openShift();
    p.state.offlineSigning = signing;
    FakeSyncBackend(p.state);
    return p;
  }

  /// The server says `offline.export` (the fake bootstrap does not carry the flag).
  void exportOn(P22 p, {bool on = true}) {
    final PosOffline o = p.h.data.offline!;
    final PosOfflineSettings s = o.settings;
    o.settings = PosOfflineSettings(
      devicePrefix: s.devicePrefix,
      nextLocalNumber: s.nextLocalNumber,
      businessDate: s.businessDate,
      dayType: s.dayType,
      fees: s.fees,
      places: s.places,
      guestItems: s.guestItems,
      limits: s.limits,
      serviceChargeLocked: s.serviceChargeLocked,
      packaging: s.packaging,
      discount: s.discount,
      signing: s.signing,
      signingKid: s.signingKid,
      export: on,
    );
  }

  Future<void> tapReal(WidgetTester tester, Finder f) async {
    await tester.ensureVisible(f);
    await tester.pumpAndSettle();
    await tester.runAsync(() async {
      await tester.tap(f);
      await Future<void>.delayed(const Duration(milliseconds: 700));
    });
    await tester.pumpAndSettle();
  }

  testWidgets('the card is hidden while offline.export is off', (
    WidgetTester tester,
  ) async {
    final P22 p = await till(tester, folder: () => dir);
    await pumpHome(tester, p.h);
    exportOn(p, on: false);
    await openSection(tester, 'Sozlamalar');
    expect(find.byKey(const ValueKey<String>('zaxira-card')), findsNothing);
    expect(find.text('Navbatni faylga saqlash'), findsNothing);
  });

  testWidgets('export writes the file and shows where it went', (
    WidgetTester tester,
  ) async {
    final P22 p = await till(tester, folder: () => dir);
    await pumpHome(tester, p.h);
    exportOn(p);
    await openSection(tester, 'Sozlamalar');
    expect(find.byKey(const ValueKey<String>('zaxira-card')), findsOneWidget);

    await tapReal(tester, find.byKey(const ValueKey<String>('zaxira-export')));
    expect(find.textContaining('Navbat faylga saqlandi'), findsOneWidget);
    expect(find.textContaining(dir.path), findsOneWidget);
    final List<FileSystemEntity> files = dir.listSync();
    expect(files, hasLength(1));
    expect(files.single.path, endsWith(SyncExportFormat.extension));
  });

  testWidgets('a refusal shows its message as it is', (
    WidgetTester tester,
  ) async {
    // No folder on this device and none chosen
    final P22 p = await till(tester);
    await pumpHome(tester, p.h);
    exportOn(p);
    await openSection(tester, 'Sozlamalar');
    String? message;
    await tester.runAsync(() async {
      try {
        await p.h.services.exporter!.export();
      } on SyncExportRefused catch (e) {
        message = e.message;
      }
    });
    expect(message, isNotNull);

    await tapReal(tester, find.byKey(const ValueKey<String>('zaxira-export')));
    expect(find.text(message!), findsOneWidget);
  });

  testWidgets(
    'before unpairing, a queue that is not on the server is offered a file first',
    (WidgetTester tester) async {
      final P22 p = await till(tester, folder: () => dir);
      await pumpHome(tester, p.h, name: 'Malika Rahimova', pin: '5926');
      exportOn(p);
      p.h.adapter.offline = true;
      await _drive(tester, p.h.data.floor.load());
      await tester.pumpAndSettle();
      await _drive(
        tester,
        p.h.data.orders.open(
          channel: PosChannel.takeaway,
          items: <PosLineInput>[
            PosLineInput(
              variantUuid: FakePosState.variantUuid(1),
              quantity: PosQuantity.parse('1'),
            ),
          ],
        ),
      );
      await tester.tap(find.byTooltip('Xodim menyusi'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Terminalni uzish'));
      await tester.pumpAndSettle();

      expect(find.text('Navbatni faylga saqlaysizmi?'), findsOneWidget);
      // The file is written in real time: the whole step runs in the real zone, with frames pumped by hand
      await tester.runAsync(() async {
        await tester.tap(find.byKey(const ValueKey<String>('unpair-export')));
        for (var i = 0; i < 20; i++) {
          await tester.pump(const Duration(milliseconds: 200));
          await Future<void>.delayed(const Duration(milliseconds: 50));
        }
      });
      await tester.pumpAndSettle();
      expect(find.text('Saqlandi'), findsOneWidget);
      expect(dir.listSync(), hasLength(1));
      await tester.tap(buttonLabeled('Davom etish'));
      await tester.pumpAndSettle();
      // The usual refusal follows: the queue is still not on the server
      expect(find.text("Terminalni uzib bo'lmaydi"), findsOneWidget);
      expect(p.h.client.auth.terminal, isNotNull);
      await tester.tap(buttonLabeled('Tushunarli'));
      await tester.pumpAndSettle();

      // Back online the queue is empty: nothing to offer any more, and no timer is left
      p.h.adapter.offline = false;
      await _drive(tester, p.h.data.offline!.syncNow());
      await tester.pumpAndSettle();
      expect(p.h.services.exporter!.offerBeforeDisconnect, isNull);
      await tester.tap(find.byTooltip('Xodim menyusi'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Terminalni uzish'));
      await tester.pumpAndSettle();
      expect(find.text('Navbatni faylga saqlaysizmi?'), findsNothing);
      await tester.tap(buttonLabeled('Uzish'));
      await tester.pumpAndSettle();
      expect(p.h.client.auth.terminal, isNull);
    },
  );
}

Future<T> _drive<T>(WidgetTester tester, Future<T> future) async {
  var done = false;
  late T value;
  Object? error;
  unawaited(
    future.then(
      (T v) {
        value = v;
        done = true;
      },
      onError: (Object e) {
        error = e;
        done = true;
      },
    ),
  );
  for (var i = 0; i < 300 && !done; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
  if (!done) throw StateError('did not finish');
  if (error != null) throw error!;
  await tester.pumpAndSettle();
  return value;
}
