import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_cashier/src/app.dart';
import 'package:rizqim_cashier/src/services.dart';
import 'package:rizqim_cashier/src/storage/shell_settings.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

/// Testdagi butun kassa: soxta backend ([FakePosAdapter]), soxta Reverb, xotiradagi omborlar, qo'lda suriladigan soat.
class CashierHarness {
  CashierHarness({bool realtime = true}) {
    adapter = FakePosAdapter(clock: () => now);
    hub = FakePosRealtimeHub();
    adapter.state.onEvent = hub.broadcast;
    client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => now,
    );
    settings = ShellSettingsController(MemoryShellSettingsStore());
    services = CashierServices(
      client: client,
      settings: settings,
      clock: () => now,
      realtimeFactory: realtime
          ? (RizqimClient c, PairedTerminal t) => PosRealtime.create(
              client: c,
              terminal: t,
              apiBaseUrl: 'https://api.test/api',
              appKey: FakePosState.realtimeKey,
              connector: hub.connect,
            )
          : null,
    );
  }

  DateTime now = DateTime.utc(2026, 10, 22, 13);
  late final FakePosAdapter adapter;
  late final FakePosRealtimeHub hub;
  late final RizqimClient client;
  late final ShellSettingsController settings;
  late final CashierServices services;

  FakePosState get state => adapter.state;
  PosData get data => services.data;
}

/// Dio taymerlari faqat soxta soat surilganda ishlaydi.
Future<T> settle<T>(WidgetTester tester, Future<T> future) async {
  await tester.pumpAndSettle();
  return future;
}

void setScreen(WidgetTester tester, Size size) {
  tester.view
    ..physicalSize = size
    ..devicePixelRatio = 1;
  addTearDown(tester.view.reset);
}

/// Ilovani ochadi (planshet, landshaft).
Future<void> pumpCashier(
  WidgetTester tester,
  CashierHarness h, {
  Size size = const Size(1280, 800),
}) async {
  setScreen(tester, size);
  await tester.pumpWidget(CashierApp(services: h.services));
  await tester.pumpAndSettle();
}

/// Ulangan terminal, PIN ekrani ochiq.
Future<void> pumpPaired(
  WidgetTester tester,
  CashierHarness h, {
  Size size = const Size(1280, 800),
}) async {
  await settle(tester, h.client.auth.pairTerminal('ABCD-EFGH'));
  await pumpCashier(tester, h, size: size);
}

/// PIN klaviaturasida terish.
Future<void> typePin(
  WidgetTester tester,
  String pin, {
  bool submit = true,
}) async {
  for (final String d in pin.split('')) {
    await tester.tap(find.bySemanticsLabel(d).last);
    await tester.pump();
  }
  if (submit) await tester.tap(find.bySemanticsLabel('Kirish').last);
  await tester.pumpAndSettle();
}

/// Ulangan, [name] (standart — kassir) PIN bilan kirgan, ish ekrani ochiq.
Future<void> pumpHome(
  WidgetTester tester,
  CashierHarness h, {
  String name = 'Kamola Karimova',
  String? pin,
  Size size = const Size(1280, 800),
}) async {
  await pumpPaired(tester, h, size: size);
  await tester.tap(find.text(name));
  await tester.pumpAndSettle();
  final int id =
      h.state.staff.firstWhere(
            (Map<String, Object?> s) => s['name'] == name,
          )['id']!
          as int;
  await typePin(tester, pin ?? h.state.pins[id]!);
}

Finder buttonLabeled(String label) => find.widgetWithText(RzButton, label);
