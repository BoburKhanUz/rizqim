import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';
import 'package:rizqim_waiter/src/app.dart';
import 'package:rizqim_waiter/src/services.dart';

/// Testdagi butun ofitsiant ilovasi: soxta backend, soxta Reverb, xotiradagi ombor, qo'lda suriladigan soat.
class WaiterHarness {
  WaiterHarness() {
    adapter = FakePosAdapter(clock: () => now);
    hub = FakePosRealtimeHub();
    adapter.state.onEvent = hub.broadcast;
    client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => now,
    );
    services = WaiterServices(
      client: client,
      clock: () => now,
      realtimeFactory: (RizqimClient c, PairedTerminal t) => PosRealtime.create(
        client: c,
        terminal: t,
        apiBaseUrl: 'https://api.test/api',
        appKey: FakePosState.realtimeKey,
        connector: hub.connect,
      ),
    );
  }

  DateTime now = DateTime.utc(2026, 10, 22, 13);
  late final FakePosAdapter adapter;
  late final FakePosRealtimeHub hub;
  late final RizqimClient client;
  late final WaiterServices services;

  FakePosState get state => adapter.state;
}

Future<T> settle<T>(WidgetTester tester, Future<T> future) async {
  await tester.pumpAndSettle();
  return future;
}

/// Telefon (portret).
Future<void> pumpWaiter(
  WidgetTester tester,
  WaiterHarness h, {
  Size size = const Size(412, 860),
}) async {
  tester.view
    ..physicalSize = size
    ..devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(WaiterApp(services: h.services));
  await tester.pumpAndSettle();
}

Future<void> typePin(WidgetTester tester, String pin) async {
  for (final String d in pin.split('')) {
    await tester.tap(find.bySemanticsLabel(d).last);
    await tester.pump();
  }
  await tester.tap(find.bySemanticsLabel('Kirish').last);
  await tester.pumpAndSettle();
}

/// Ulangan, [name] (standart — ofitsiant) PIN bilan kirgan.
Future<void> pumpHome(
  WidgetTester tester,
  WaiterHarness h, {
  String name = 'Botir Aliyev',
}) async {
  await settle(tester, h.client.auth.pairTerminal('ABCD-EFGH'));
  await pumpWaiter(tester, h);
  await tester.tap(find.text(name));
  await tester.pumpAndSettle();
  final int id =
      h.state.staff.firstWhere(
            (Map<String, Object?> s) => s['name'] == name,
          )['id']!
          as int;
  await typePin(tester, h.state.pins[id]!);
}

Finder buttonLabeled(String label) => find.widgetWithText(RzButton, label);
