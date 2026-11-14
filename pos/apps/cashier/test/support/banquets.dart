import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/banquets/banquet_event_page.dart';
import 'package:rizqim_cashier/src/shell/routes.dart';

import 'harness.dart';
import 'p22.dart';

/// To'lqin 5 (B) "Tadbirlar" testlari uchun: to'yxona formati (`banquet` moduli), soxta tadbirlar backend'i.
Map<String, Object?> toyxonaFormat({bool banquet = true}) => <String, Object?>{
  'profile': 'toyxona',
  'profile_label': "To'yxona",
  'mode': 'table',
  'first_screen': 'hall_map',
  'modules': <String>[
    'bookings',
    'service_charge',
    'kds',
    if (banquet) 'banquet',
  ],
  'locks': <String, Object?>{'service_charge_zero': false},
};

/// Kassa, smena ochiq ([shift]), tadbirlar yoqiq.
class BanquetTill {
  BanquetTill({bool shift = true, bool module = true, SyncStore? offlineStore})
    : p = P22(offlineStore: offlineStore) {
    p.state.format = toyxonaFormat(banquet: module);
    if (shift) p.state.openShift();
    b = p.backend.useBanquets();
  }

  final P22 p;
  late final FakeBanquetBackend b;

  /// Bo'nak, qaytarish va sotuv cheklari matni.
  List<String> get receipts => p.receipts;

  List<Map<String, Object?>> rows(String uuid) => b.rows[uuid]!;
}

/// "Tadbirlar" bo'limini ochadi (planshet: chap panel, telefon: pastki panel).
Future<void> openBanquets(WidgetTester tester) async {
  final Finder rail = find.byType(NavigationRail);
  final Finder bar = find.byType(NavigationBar);
  final Finder where = rail.evaluate().isNotEmpty ? rail : bar;
  await tester.tap(
    find.descendant(of: where, matching: find.text('Tadbirlar')),
  );
  await tester.pumpAndSettle();
}

/// Kassir (yoki [name]) kiradi, "Tadbirlar" ochiq.
Future<void> pumpBanquets(
  WidgetTester tester,
  BanquetTill t, {
  String name = 'Kamola Karimova',
  Size size = const Size(1280, 800),
}) async {
  await pumpHome(tester, t.p.h, name: name, size: size);
  await openBanquets(tester);
}

/// Tadbir kartasini ochadi: taqvimda ko'rinsa — bosib, aks holda (14 kundan keyin) to'g'ridan-to'g'ri.
Future<void> openEvent(WidgetTester tester, String uuid) async {
  final Finder tile = find.byKey(ValueKey<String>('banquet-$uuid'));
  if (tile.evaluate().isNotEmpty) {
    await tapKey(tester, 'banquet-$uuid');
    return;
  }
  final Finder rail = find.byType(NavigationRail);
  final BuildContext context = tester.element(
    rail.evaluate().isNotEmpty ? rail : find.byType(NavigationBar),
  );
  CashierRoutes.push<void>(context, BanquetEventPage(uuid: uuid)).ignore();
  await tester.pumpAndSettle();
}
