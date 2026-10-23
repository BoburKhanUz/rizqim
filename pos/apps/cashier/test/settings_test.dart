import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_cashier/src/features/settings/cashier_prefs.dart';

import 'support/harness.dart';
import 'support/p22.dart';

void main() {
  testWidgets(
    'printers from bootstrap: test print, status, 58 mm on this device; sound switch; device info',
    (WidgetTester tester) async {
      final P22 p = P22();
      await pumpHome(tester, p.h);
      await openSection(tester, 'Sozlamalar');

      expect(find.text('Kassa printeri'), findsOneWidget);
      expect(find.text('Osh sexi'), findsOneWidget);
      expect(
        find.textContaining('Chek va schyot · LAN 192.168.1.50:9100 · 80 mm'),
        findsOneWidget,
      );
      expect(find.textContaining('Sex markasi: Osh'), findsOneWidget);

      await printInRealTime(tester, 'test-print-1');
      // Sinov sahifasida oʻ va kirill harflari bor: 80 mm (576 nuqta) rasm sifatida chiqadi
      expect(p.receipts.single, contains('[RASM 576x'));
      expect(find.text('Tayyor'), findsOneWidget);

      // Shu qurilmada 58 mm qog'oz: sinov sahifasi 384 nuqtali
      await tester.tap(find.text('58 mm').first);
      await tester.pumpAndSettle();
      expect(CashierPrefs.instance.paperOf('server-1'), 58);
      await printInRealTime(tester, 'test-print-1');
      expect(p.receipts.last, contains('[RASM 384x'));

      // Ovoz o'chirildi va saqlandi
      expect(CashierPrefs.instance.sound, isTrue);
      await tapKey(tester, 'sound-switch');
      expect(CashierPrefs.instance.sound, isFalse);

      expect(find.text('Kassa 1'), findsOneWidget);
      expect(
        find.text('Terminalni faqat menejer yoki ega uza oladi.'),
        findsOneWidget,
      );
    },
  );

  testWidgets('a manager unpairs the till from settings', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    await pumpHome(tester, p.h, name: 'Malika Rahimova');
    await openSection(tester, 'Sozlamalar');
    await tapButton(tester, 'Terminalni uzish');
    await tester.tap(buttonLabeled('Uzish'));
    await tester.pumpAndSettle();
    expect(find.text('Kassani ulash'), findsOneWidget);
    expect(p.state.paired, isFalse);
  });
}

/// Sinov sahifasida jadvalda yo'q harflar (oʻ, kirill) bor: u rasm sifatida chiziladi — buning uchun haqiqiy vaqt
/// kerak, shuning uchun tugma `runAsync` ichida bosiladi.
Future<void> printInRealTime(WidgetTester tester, String key) async {
  final Finder f = find.byKey(ValueKey<String>(key));
  await tester.ensureVisible(f);
  await tester.pumpAndSettle();
  await tester.runAsync(() async {
    await tester.tap(f);
    await Future<void>.delayed(const Duration(seconds: 2));
  });
  await tester.pumpAndSettle();
  // Xabar haqiqiy vaqtda ko'rsatilgan: uning taymeri test tugagach ishlamasin
  ScaffoldMessenger.of(tester.element(f)).clearSnackBars();
  await tester.pumpAndSettle();
}
