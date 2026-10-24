import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_cashier/src/features/shift/z_close_page.dart';

import 'support/harness.dart';
import 'support/p22.dart';

void main() {
  testWidgets(
    'open a shift with opening cash, cash out for bozorlik (no manager), X report view and print',
    (WidgetTester tester) async {
      final P22 p = P22();
      await pumpHome(tester, p.h);
      await openSection(tester, 'Smena');
      expect(find.textContaining('X va Z hisobot'), findsOneWidget);
      await enter(tester, 'opening-cash', '100000');
      await tapKey(tester, 'shift-open');
      expect(p.state.shift, isNotNull);
      expect(p.state.shift!['opening_cash'], '100000.00');
      expect(find.text('Smena ochiq'), findsWidgets);
      expect(find.text(uzs(100000)), findsWidgets);

      // Chiqim: bozorlik, go'sht — P3-37: kassirda pos.cash_in_out bor, menejer kerak emas
      await tapKey(tester, 'cash-out');
      expect(find.text('Kassadan chiqim'), findsOneWidget);
      await tester.tap(find.widgetWithText(ChoiceChip, "Go'sht"));
      await enter(tester, 'movement-amount', '150000');
      await tapKey(tester, 'movement-submit');
      expect(
        find.textContaining("Kassada buncha naqd pul yo'q"),
        findsOneWidget,
      );
      await enter(tester, 'movement-amount', '30000');
      await tapKey(tester, 'movement-submit');
      expect(find.text("Menejer tasdig'i"), findsNothing);
      expect(p.backend.movements.single['category'], 'meat');
      expect(p.drawerKicks, 1);
      expect(find.text("Bozorlik · Go'sht"), findsOneWidget);
      expect(find.text(uzs(70000)), findsWidgets);

      // Ish haqi: kimga — majburiy
      await tapKey(tester, 'cash-out');
      await tester.tap(find.widgetWithText(ChoiceChip, 'Ish haqi'));
      await tester.pumpAndSettle();
      await enter(tester, 'movement-amount', '20000');
      await tapKey(tester, 'movement-submit');
      expect(find.text('Kimga berilganini tanlang.'), findsOneWidget);
      await tester.tap(buttonLabeled('Bekor qilish'));
      await tester.pumpAndSettle();

      await tapKey(tester, 'x-report');
      expect(find.textContaining('X-hisobot'), findsOneWidget);
      expect(find.text("Kassada bo'lishi kerak"), findsOneWidget);
      expect(find.text('Pul emas (bugun kassaga tushmagan)'), findsOneWidget);
      await tapButton(tester, 'Chop etish');
      final String x = p.receipts.last;
      expect(x, contains('X-HISOBOT'));
      expect(x, contains('Bozorlik'));
    },
  );

  testWidgets(
    'Z close: banknotes counted, difference shown; open orders refuse; forced close with approval prints Z',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      p.state.seedOrder(tableId: 3, guests: 4);
      await pumpHome(tester, p.h);
      await openSection(tester, 'Smena');
      await tapKey(tester, 'z-open');
      expect(find.byType(ZClosePage), findsOneWidget);
      expect(find.text(uzs(100000)), findsWidgets);

      await enter(tester, 'denom-50000', '1');
      await enter(tester, 'denom-10000', '4');
      await tester.tap(find.byTooltip("Qo'shish").at(4)); // 10 000 → 5 ta
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<Text>(find.byKey(const ValueKey<String>('z-difference')))
            .data,
        uzs(0),
      );
      expect(find.text("Farq yo'q"), findsOneWidget);
      await tester.tap(find.byTooltip('Kamaytirish').at(4)); // 10 000 → 4 ta
      await tester.pumpAndSettle();
      expect(find.text('Kamomad'), findsOneWidget);
      expect(
        tester
            .widget<Text>(find.byKey(const ValueKey<String>('z-difference')))
            .data,
        '−${uzs(10000)}',
      );

      await tapKey(tester, 'z-close');
      expect(find.text('Smena yopilmadi'), findsOneWidget);
      expect(find.text('Ochiq buyurtmalar: 1 ta'), findsOneWidget);
      expect(p.state.shift, isNotNull);

      await tapKey(tester, 'z-force');
      await tester.tap(find.text('Xona ertaga yopiladi'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Majburan yopish').last);
      await tester.pumpAndSettle();
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      expect(find.text('Buyurtmani bekor qilish'), findsOneWidget);
      await typePin(tester, '5926');
      await tester.pumpAndSettle();

      expect(p.state.shift, isNull);
      final Map<String, Object?> closed = p.backend.closedShifts.values.single;
      expect(closed['counted_cash'], '90000.00');
      expect(closed['difference'], '-10000.00');
      expect(closed['force_closed'], isTrue);
      expect(closed['counted_breakdown'], <String, Object?>{
        '50000': 1,
        '10000': 4,
      });
      final String z = p.receipts.last;
      expect(z, contains('Z-HISOBOT'));
      expect(z, contains('SMENA YOPILDI'));
      expect(find.byType(ZClosePage), findsNothing);
      expect(find.text('Smena yopildi · Z-1'), findsOneWidget);
      expect(find.text('Smena yopiq'), findsOneWidget);
      expect(find.byKey(const ValueKey<String>('shift-open')), findsOneWidget);
    },
  );
}
