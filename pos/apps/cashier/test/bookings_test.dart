import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_cashier/src/features/bookings/booking_detail_page.dart';
import 'package:rizqim_cashier/src/features/bookings/booking_form_page.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';

import 'support/harness.dart';
import 'support/p22.dart';

void main() {
  testWidgets(
    'calendar shows rooms × hours; an empty slot opens the form; a busy room is a conflict',
    (WidgetTester tester) async {
      final P22 p = P22();
      final String jamshid = p.backend.seedBooking(
        tableId: 2,
        start: DateTime.utc(2026, 10, 22, 14),
        name: 'Jamshid aka',
        guests: 12,
        deposit: '1000000.00',
      );
      await pumpHome(tester, p.h);
      await openSection(tester, 'Bronlar');
      expect(find.text('1-xona'), findsWidgets);
      expect(find.text('Tapchan 1'), findsWidgets);
      expect(find.text('Jamshid aka · 12 kishi'), findsOneWidget);
      expect(find.byKey(ValueKey<String>('booking-$jamshid')), findsOneWidget);
      expect(find.text('Shu kungi bronlar (1)'), findsOneWidget);

      // 3-xona, 15:00 — yangi bron
      await tapKey(tester, 'slot-3-15');
      expect(find.byType(BookingFormPage), findsOneWidget);
      expect(find.text('Yangi bron'), findsOneWidget);
      await enter(tester, 'booking-name', 'Dilshod');
      await enter(tester, 'booking-phone', '90 111 22 33');
      await tester.tap(find.widgetWithText(ChoiceChip, "Tug'ilgan kun"));
      await tester.pumpAndSettle();
      await tapKey(tester, 'booking-save');
      expect(find.byType(BookingDetailPage), findsOneWidget);
      final Map<String, Object?> created = p.backend.bookings.values.last;
      expect(created['contact_name'], 'Dilshod');
      expect(created['contact_phone'], '+998901112233');
      expect(created['dining_table_id'], 3);
      expect(created['event_type'], 'tugilgan_kun');
      expect(created['status'], 'confirmed');
      expect(DateTime.parse('${created['starts_at']}').toLocal().hour, 15);
      // Standart davomiylik — 5 soat
      expect(
        DateTime.parse('${created['ends_at']}')
            .difference(DateTime.parse('${created['starts_at']}')),
        const Duration(hours: 5),
      );
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text('Shu kungi bronlar (2)'), findsOneWidget);

      // 2-xona 12:00 (5 soat) — Jamshid akaning broni (14:00) bilan to'qnashadi
      await tapKey(tester, 'slot-2-12');
      await enter(tester, 'booking-name', 'Olim');
      await tapKey(tester, 'booking-save');
      expect(find.byType(BookingFormPage), findsOneWidget);
      expect(find.textContaining('Bu vaqtda band: 2-xona'), findsOneWidget);
      expect(p.backend.bookings, hasLength(2));
    },
  );

  testWidgets('a new booking with a pre-order: osh by kg with a due time', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    await pumpHome(tester, p.h);
    await openSection(tester, 'Bronlar');
    await tapKey(tester, 'slot-1-19');
    await enter(tester, 'booking-name', 'Bobur');
    await tapKey(tester, 'preorder-open');
    expect(find.text('Oldindan buyurtma: taom tanlang'), findsOneWidget);
    await tester.tap(
      find.descendant(of: find.byType(AlertDialog), matching: find.text('Osh')),
    );
    await tester.pumpAndSettle();
    await tester.tap(
      find
          .descendant(of: find.byType(AlertDialog), matching: find.text('2'))
          .first,
    );
    await tester.pumpAndSettle();
    await tapOn(tester, find.textContaining('Belgilash'));
    await tapOn(tester, find.widgetWithText(ChoiceChip, '+1 soat'));
    await tapKey(tester, 'preorder-add');
    expect(find.textContaining('2 kg (guruch) · tayyor 20:00'), findsOneWidget);
    expect(find.text('Oldindan buyurtma jami'), findsOneWidget);
    await tapKey(tester, 'booking-save');

    final Map<String, Object?> b = p.backend.bookings.values.single;
    final Map<String, Object?> item =
        (b['items']! as List<Object?>).single! as Map<String, Object?>;
    expect(item['product_name'], 'Osh');
    expect(item['quantity'], 2.0);
    expect(DateTime.parse('${item['due_at']}').toLocal().hour, 20);
    expect(find.byType(BookingDetailPage), findsOneWidget);
    expect(find.text('Osh (Choyxona oshi)'), findsOneWidget);
  });

  testWidgets("deposit prints a BO'NAK receipt; \"Keldi\" opens the order", (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    final String uuid = p.backend.seedBooking(
      tableId: 2,
      start: DateTime.utc(2026, 10, 22, 14),
      name: 'Jamshid aka',
    );
    await pumpHome(tester, p.h);
    await openSection(tester, 'Bronlar');
    await tapKey(tester, 'booking-$uuid');
    expect(find.byType(BookingDetailPage), findsOneWidget);

    await tapKey(tester, 'booking-deposit');
    expect(find.text('Avans olish'), findsWidgets);
    await enter(tester, 'tender-amount', '200000');
    await tapKey(tester, 'deposit-submit');
    expect(p.backend.bookings[uuid]!['deposit_available'], '200000.00');
    final String receipt = p.receipts.single;
    expect(receipt, contains("BO'NAK CHEKI"));
    expect(receipt, contains('Avans (bron)'));
    expect(receipt, contains('Mijoz: Jamshid aka'));
    expect(receipt, isNot(contains('Fiskal belgi')));
    // P3-26: avans javobidagi chek — server raqami va kassa (fiskal modul)
    expect(receipt, contains('Kassa: EP000000000123'));
    expect(receipt, matches(RegExp(r'Chek: 20\d\d')));
    expect(p.drawerKicks, 1);
    expect(find.text(uzs(200000)), findsWidgets);

    await tapKey(tester, 'booking-arrive');
    expect(find.text('Mehmonlar keldi'), findsOneWidget);
    await tapKey(tester, 'arrival-confirm');
    expect(p.backend.bookings[uuid]!['status'], 'arrived');
    final String order = p.backend.bookings[uuid]!['order_uuid']! as String;
    expect(find.byType(OrderPage), findsOneWidget);
    expect(tester.widget<OrderPage>(find.byType(OrderPage)).orderUuid, order);
  });

  testWidgets('"Keldi": fewer guests came and they sit in another room', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    final String uuid = p.backend.seedBooking(
      tableId: 2,
      start: DateTime.utc(2026, 10, 22, 14),
      name: 'Jamshid aka',
      guests: 8,
    );
    await pumpHome(tester, p.h);
    await openSection(tester, 'Bronlar');
    await tapKey(tester, 'booking-$uuid');
    await tapKey(tester, 'booking-arrive');
    expect(find.text('Mehmonlar keldi'), findsOneWidget);
    await tester.tap(find.widgetWithText(ChoiceChip, '6'));
    await tester.pumpAndSettle();
    await tapButton(tester, 'Boshqa joy');
    expect(find.text("Boshqa joyga ko'chirish"), findsOneWidget);
    await tester.tap(find.text('3-xona'));
    await tester.pumpAndSettle();
    expect(
      tester
          .widget<Text>(find.byKey(const ValueKey<String>('arrival-place')))
          .data,
      startsWith('3-xona'),
    );
    await tapKey(tester, 'arrival-confirm');

    final Map<String, Object?> booking = p.backend.bookings[uuid]!;
    expect(booking['status'], 'arrived');
    final Map<String, Object?> order =
        p.state.orders[booking['order_uuid']! as String]!;
    expect(order['guests_count'], 6);
    expect((order['table']! as Map<String, Object?>)['id'], 3);
    expect(find.byType(OrderPage), findsOneWidget);
  });

  testWidgets(
    'a canceled booking keeps its deposit only with the manager approval',
    (WidgetTester tester) async {
      final P22 p = P22();
      final String uuid = p.backend.seedBooking(
        tableId: 2,
        start: DateTime.utc(2026, 10, 22, 14),
        name: 'Jamshid aka',
        deposit: '300000.00',
      );
      await pumpHome(tester, p.h);
      await openSection(tester, 'Bronlar');
      await tapKey(tester, 'booking-$uuid');
      await tapButton(tester, 'Bekor qilish');
      await tester.tap(find.text('Mijoz bekor qildi'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Bekor qilish').last);
      await tester.pumpAndSettle();
      expect(p.backend.bookings[uuid]!['status'], 'canceled');
      expect(
        find.text('Avans hal qilinmagan: qaytaring yoki ushlab qoling.'),
        findsOneWidget,
      );

      await tapText(tester, 'Ushlab qolish');
      await tester.tap(find.text('Kech bekor qildi'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Ushlab qolish').last);
      await tester.pumpAndSettle();
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      await typePin(tester, '5926');
      await tester.pumpAndSettle();
      final Map<String, Object?> payment =
          (p.backend.bookings[uuid]!['payments']! as List<Object?>).single!
              as Map<String, Object?>;
      expect(payment['status'], 'retained');
      expect(payment['retained_amount'], '300000.00');
      expect(p.backend.bookings[uuid]!['deposit_available'], '0.00');
    },
  );
}
