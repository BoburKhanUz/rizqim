import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:rizqim_cashier/src/features/floor/floor_page.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/harness.dart';
import 'support/order_helpers.dart';

void main() {
  testWidgets(
    "voiding a sent line asks for a reason and the manager's PIN; the station gets O'CHIRILDI",
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      final String uuid = h.state.seedOrder(tableId: 1);
      await pumpHome(tester, h);
      await openPlace(tester, '1-xona');
      final int oshId = items(h.state.orders[uuid]!).first['id']! as int;

      await tester.tap(find.byKey(ValueKey<String>('item-$oshId')));
      await tester.pumpAndSettle();
      expect(buttonLabeled('Berildi'), findsOneWidget);
      await tester.tap(buttonLabeled("O'chirish"));
      await tester.pumpAndSettle();
      expect(find.textContaining("«O'CHIRILDI» markasi"), findsOneWidget);
      // Sababsiz o'chirib bo'lmaydi
      expect(
        tester.widget<RzButton>(buttonLabeled("O'chirish").last).onPressed,
        isNull,
      );
      await tester.tap(find.widgetWithText(ChoiceChip, 'Mehmon voz kechdi'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled("O'chirish").last);
      await tester.pumpAndSettle();
      // Kassirda orders.void_item yo'q: menejer tasdig'i
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      await typePin(tester, '5926');
      await tester.pumpAndSettle();

      final Map<String, Object?> osh = items(h.state.orders[uuid]!).first;
      expect(osh['kitchen_status'], 'void');
      expect(osh['void_reason'], 'Mehmon voz kechdi');
      expect(find.text("O'chirildi"), findsOneWidget);
      expect(find.text('Sabab: Mehmon voz kechdi'), findsOneWidget);
      final String ticket = h.printerFor('server-2').documents.last.text;
      expect(ticket, contains("O'CHIRILDI"));
      expect(ticket, contains('Sabab: Mehmon voz kechdi'));
    },
  );

  testWidgets(
    'an unsent line is voided without approval; served marks a line',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      final String uuid = h.state.seedOrder(tableId: 1);
      h.state.seedRound(uuid, const <(int, String)>[(1, '1')], sent: false);
      await pumpHome(tester, h);
      await openPlace(tester, '1-xona');
      final List<Map<String, Object?>> lines = items(h.state.orders[uuid]!);

      await tester.tap(
        find.byKey(ValueKey<String>('item-${lines.last['id']}')),
      );
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled("O'chirish"));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled("O'chirish").last);
      await tester.pumpAndSettle();
      expect(find.text("Menejer tasdig'i"), findsNothing);
      expect(items(h.state.orders[uuid]!).last['kitchen_status'], 'void');
      expect(h.printerFor('server-2').received, isEmpty);

      await tester.tap(find.byKey(ValueKey<String>('item-${lines[1]['id']}')));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Berildi'));
      await tester.pumpAndSettle();
      expect(items(h.state.orders[uuid]!)[1]['kitchen_status'], 'served');
    },
  );

  testWidgets('move to another room and merge another order into this one', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    final String uuid = h.state.seedOrder(tableId: 1, guests: 4);
    final String other = h.state.seedOrder(
      tableId: 12,
      guests: 2,
      lines: const <(int, String)>[(4, '3')],
    );
    await pumpHome(tester, h);
    await openPlace(tester, '1-xona');

    await tester.tap(find.byTooltip("Ko'chirish"));
    await tester.pumpAndSettle();
    expect(find.text("Boshqa joyga ko'chirish"), findsOneWidget);
    await tester.tap(find.text('3-xona'));
    await tester.pumpAndSettle();
    expect(
      (h.state.orders[uuid]!['table']! as Map<String, Object?>)['name'],
      '3-xona',
    );
    expect(find.text("Buyurtma ko'chirildi: 3-xona"), findsOneWidget);
    expect(find.text('3-xona'), findsOneWidget);

    await tester.tap(find.byTooltip('Birlashtirish'));
    await tester.pumpAndSettle();
    await tester.tap(find.textContaining('Tapchan 2'));
    await tester.pumpAndSettle();
    await tester.tap(buttonLabeled('Birlashtirish'));
    await tester.pumpAndSettle();
    expect(h.state.orders[other]!['status'], 'canceled');
    expect(h.state.orders[uuid]!['guests_count'], 6);
    expect(find.text('2-bosqich'), findsOneWidget);
    expect(find.text('6 mehmon'), findsOneWidget);

    await tester.pageBack();
    await tester.pumpAndSettle();
    expect(find.byType(FloorPage), findsOneWidget);
  });

  testWidgets(
    'service charge is waived with a reason and approval, then restored',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      final String uuid = h.state.seedOrder(tableId: 1);
      await pumpHome(tester, h);
      await openPlace(tester, '1-xona');
      expect(find.text('Xizmat haqi 10%'), findsOneWidget);
      expect(find.text('Xona puli'), findsOneWidget);

      await tester.tap(find.text('Olib tashlash').last);
      await tester.pumpAndSettle();
      expect(find.text('Xizmat haqi: olib tashlash'), findsOneWidget);
      await tester.tap(find.widgetWithText(ChoiceChip, "Mehmon e'tirozi"));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Olib tashlash'));
      await tester.pumpAndSettle();
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      expect(
        find.text('Chegirma yoki xizmat haqini olib tashlash'),
        findsOneWidget,
      );
      await typePin(tester, '5926');
      await tester.pumpAndSettle();

      final Map<String, Object?> order = h.state.orders[uuid]!;
      expect(
        (order['charges']! as Map<String, Object?>)['waived'],
        contains('service_charge'),
      );
      expect(order['service_charge_amount'], '0.00');
      expect(find.text('Olib tashlandi'), findsOneWidget);
      expect(find.text('Qaytarish'), findsOneWidget);

      await tester.tap(find.text('Qaytarish'));
      await tester.pumpAndSettle();
      expect(
        (h.state.orders[uuid]!['charges']! as Map<String, Object?>)['waived'],
        isEmpty,
      );
    },
  );

  testWidgets(
    'version conflict: the round is re-applied on the fresh order; a void is not',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness(realtime: false);
      final String uuid = h.state.seedOrder(tableId: 1, guests: 4);
      await pumpHome(tester, h);
      await openPlace(tester, '1-xona');

      // Boshqa qurilma (ofitsiant) mehmonlar sonini o'zgartirdi — bu sahifa hali bilmaydi
      await settle(
        tester,
        h.data.orders.setGuests(
          uuid,
          version: h.state.orders[uuid]!['version']! as int,
          guestsCount: 7,
        ),
      );
      await tester.tap(menuTile(1));
      await tester.pumpAndSettle();
      await tester.tap(sendButton);
      await tester.pumpAndSettle();
      expect(h.state.log.where((String l) => l.endsWith('/rounds')).length, 2);
      expect(items(h.state.orders[uuid]!).last['product_name'], 'Choy');
      expect(find.text('7 mehmon'), findsOneWidget);
      expect(find.text('2-bosqich'), findsOneWidget);

      // Yana o'zgardi: endi o'chirish takrorlanmaydi, foydalanuvchiga aytiladi
      await settle(
        tester,
        h.data.orders.setGuests(
          uuid,
          version: h.state.orders[uuid]!['version']! as int,
          guestsCount: 8,
        ),
      );
      final int chId = items(h.state.orders[uuid]!).last['id']! as int;
      await tester.tap(find.byKey(ValueKey<String>('item-$chId')));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled("O'chirish"));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(ChoiceChip, 'Xato kiritildi'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled("O'chirish").last);
      await tester.pumpAndSettle();
      await tester.pumpAndSettle();
      await tester.pumpAndSettle();
      expect(
        find.textContaining("boshqa qurilmada o'zgartirildi va yangilandi"),
        findsOneWidget,
      );
      expect(items(h.state.orders[uuid]!).last['kitchen_status'], 'sent');
      expect(find.text('8 mehmon'), findsOneWidget);
    },
  );

  testWidgets(
    'a failed station ticket shows a non-blocking banner; retry prints it',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      final String uuid = h.state.seedOrder(tableId: 1);
      h
          .printerFor('server-2')
          .failNext(
            1,
            const PrinterException(PrinterErrorKind.paperOut, "Qog'oz tugadi"),
          );
      await pumpHome(tester, h);
      await openPlace(tester, '1-xona');
      await tester.tap(menuTile(3));
      await tester.pumpAndSettle();
      await tester.tap(find.text('1').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text("Qo'shish: 1 kg (guruch)"));
      await tester.pumpAndSettle();
      await tester.tap(sendButton);
      await tester.pumpAndSettle();

      // Sotuv to'xtamadi: bosqich yuborildi
      expect(items(h.state.orders[uuid]!).last['kitchen_status'], 'sent');
      expect(find.text('Marka chop etilmadi (1)'), findsOneWidget);
      expect(find.textContaining("Qog'oz tugadi"), findsOneWidget);
      expect(h.printerFor('server-2').received, isEmpty);

      await tester.tap(buttonLabeled('Qayta urinish'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Marka chop etilmadi'), findsNothing);
      expect(h.printerFor('server-2').documents.single.text, contains('OSH'));

      // Zalda ham ko'rinadi (bitta kassa ro'yxati)
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.byType(OrderPage), findsNothing);
    },
  );

  testWidgets('cancel the whole order: reason + manager, back to the floor', (
    WidgetTester tester,
  ) async {
    final CashierHarness h = CashierHarness();
    final String uuid = h.state.seedOrder(tableId: 1);
    await pumpHome(tester, h);
    await openPlace(tester, '1-xona');
    await tester.tap(find.byTooltip('Boshqa amallar'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Buyurtmani bekor qilish'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(ChoiceChip, 'Mehmonlar ketdi'));
    await tester.pumpAndSettle();
    await tester.tap(buttonLabeled('Bekor qilish').last);
    await tester.pumpAndSettle();
    await typePin(tester, '5926');
    await tester.pumpAndSettle();
    expect(h.state.orders[uuid]!['status'], 'canceled');
    expect(find.byType(FloorPage), findsOneWidget);
    expect(
      h.printerFor('server-2').documents.last.text,
      contains("O'CHIRILDI"),
    );
  });

  testWidgets('guests change from the app bar', (WidgetTester tester) async {
    final CashierHarness h = CashierHarness();
    final String uuid = h.state.seedOrder(tableId: 1, guests: 4);
    await pumpHome(tester, h);
    await openPlace(tester, '1-xona');
    await tester.tap(find.text('4 mehmon'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(ChoiceChip, '8'));
    await tester.pumpAndSettle();
    await tester.tap(buttonLabeled('Saqlash').last);
    await tester.pumpAndSettle();
    expect(h.state.orders[uuid]!['guests_count'], 8);
    expect(find.text('8 mehmon'), findsOneWidget);
    expect(PosPermissions.label(PosPermissions.ordersDiscount), isNotEmpty);
  });
}
