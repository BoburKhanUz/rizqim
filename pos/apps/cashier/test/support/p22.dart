import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_hardware/testing.dart';
import 'package:rizqim_cashier/src/features/settings/cashier_prefs.dart';
import 'package:rizqim_cashier/src/shell/routes.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'harness.dart';

/// P3-22 testlari uchun: soxta backend'ning kassa qismi ([FakeCashierBackend]), bo'limga o'tish, schyotni ochish va
/// chek printeriga chiqqan hujjatlar.
class P22 {
  P22({bool realtime = true, SyncStore? offlineStore})
    : h = CashierHarness(realtime: realtime, offlineStore: offlineStore) {
    backend = FakeCashierBackend(h.state);
    SharedPreferences.setMockInitialValues(<String, Object>{});
    CashierPrefs.instance.reset();
  }

  final CashierHarness h;
  late final FakeCashierBackend backend;

  FakePosState get state => h.state;

  /// Chek printeri (`server-1`) ga chiqqan hujjatlar matni.
  List<String> get receipts => <String>[
    for (final EscPosDocument d in h.printerFor('server-1').documents) d.text,
  ];

  /// Kassa qutisi necha marta ochildi.
  int get drawerKicks => h
      .printerFor('server-1')
      .documents
      .fold(0, (int s, EscPosDocument d) => s + d.drawerKicks);
}

/// "509 000 so'm" (ekrandagi ko'rinish: ajralmas bo'shliq bilan).
String uzs(num som) => PosMoney((som * 100).round()).format();

/// Navigatsiya panelidagi bo'lim.
Future<void> openSection(WidgetTester tester, String label) async {
  await tester.tap(
    find.descendant(
      of: find.byType(NavigationRail),
      matching: find.text(label),
    ),
  );
  await tester.pumpAndSettle();
}

/// Buyurtma schyotini ochadi (zal xaritasidagi "Schyot" tugmasi o'rniga).
Future<void> openBilling(WidgetTester tester, String orderUuid) async {
  final BuildContext context = tester.element(find.byType(NavigationRail));
  CashierRoutes.openBilling<void>(context, orderUuid: orderUuid).ignore();
  await tester.pumpAndSettle();
}

/// Signal ovozi chaqiruvlari (`SystemSound.play(alert)`).
List<MethodCall> recordSystemSounds(WidgetTester tester) {
  final List<MethodCall> calls = <MethodCall>[];
  tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
    SystemChannels.platform,
    (MethodCall call) async {
      // Tugma bosilganda "click" ham chalinadi: faqat signal
      if (call.method == 'SystemSound.play' &&
          call.arguments == 'SystemSoundType.alert') {
        calls.add(call);
      }
      return null;
    },
  );
  addTearDown(
    () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
      SystemChannels.platform,
      null,
    ),
  );
  return calls;
}

/// Matn maydoniga yozish (kalit bo'yicha).
Future<void> enter(WidgetTester tester, String key, String text) async {
  await tester.enterText(find.byKey(ValueKey<String>(key)), text);
  await tester.pumpAndSettle();
}

/// Kalit bo'yicha bosish (ko'rinmasa — avval aylantiriladi).
Future<void> tapKey(WidgetTester tester, String key) async {
  final Finder f = find.byKey(ValueKey<String>(key));
  await tester.ensureVisible(f);
  await tester.pumpAndSettle();
  await tester.tap(f);
  await tester.pumpAndSettle();
}

/// Matn bo'yicha bosish (ko'rinmasa — avval aylantiriladi).
Future<void> tapText(
  WidgetTester tester,
  String text, {
  bool last = false,
  bool contains = false,
}) async {
  final Finder all = contains ? find.textContaining(text) : find.text(text);
  final Finder f = last ? all.last : all.first;
  await tester.ensureVisible(f);
  await tester.pumpAndSettle();
  await tester.tap(f);
  await tester.pumpAndSettle();
}

/// [RzButton] ni yorlig'i bo'yicha bosish (ko'rinmasa — avval aylantiriladi).
Future<void> tapButton(WidgetTester tester, String label) async {
  final Finder f = buttonLabeled(label).first;
  await tester.ensureVisible(f);
  await tester.pumpAndSettle();
  await tester.tap(f);
  await tester.pumpAndSettle();
}

/// Istalgan topuvchi bo'yicha bosish (avval ko'rinadigan joyga aylantiriladi).
Future<void> tapOn(WidgetTester tester, Finder finder) async {
  await tester.ensureVisible(finder);
  await tester.pumpAndSettle();
  await tester.tap(finder);
  await tester.pumpAndSettle();
}
