import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';
import 'package:rizqim_waiter/src/features/hostess/hostess_page.dart';
import 'package:rizqim_waiter/src/features/me/my_tips_page.dart';
import 'package:rizqim_waiter/src/features/order/order_page.dart';
import 'package:rizqim_waiter/src/shell/approval_dialog.dart';

import 'support/harness.dart';
import 'support/restaurant.dart';

/// P6 2-to'lqin: ofitsiant ilovasidagi restoran imkoniyatlari (kurslar, mehmon raqami, six ×N, og'irlik, stolni
/// berish, xabarlar, choy puli, ish vaqti) va hostes stoli. Soxta backend — `support/restaurant.dart`.

/// Botir (ofitsiant) o'z buyurtmasini (1-xona) ochgan; [before] — buyurtma qatorlarini o'zgartirish.
Future<String> openRestaurantOrder(
  WidgetTester tester,
  WaiterHarness h, {
  List<(int, String)> lines = const <(int, String)>[(3, '1.500'), (1, '2')],
  void Function(String uuid, List<Map<String, Object?>> items)? before,
}) async {
  final String uuid = seedWaiterOrder(h, tableId: 1, lines: lines);
  before?.call(
    uuid,
    (h.state.orders[uuid]!['items']! as List<Object?>)
        .cast<Map<String, Object?>>(),
  );
  await pumpHome(tester, h);
  await tester.tap(find.text('1-xona'));
  await tester.pumpAndSettle();
  expect(find.byType(OrderPage), findsOneWidget);
  return uuid;
}

/// Qatorni keyingi (ushlab turilgan) kursga qo'yadi.
void holdAsCourse(Map<String, Object?> item, int course) {
  item
    ..['course_no'] = course
    ..['kitchen_status'] = 'pending'
    ..['fire_mode'] = 'hold'
    ..['sent_at'] = null;
}

Future<void> openMenu(WidgetTester tester) async {
  await tester.tap(find.byTooltip('Menyu').first);
  await tester.pumpAndSettle();
}

Finder chip(String label) => find.widgetWithText(ChoiceChip, label);

/// Klaviatura tugmasi miqdor bilan yoziladi ("Qo'shish: 1 kg").
Future<void> tapButtonStarting(WidgetTester tester, String prefix) async {
  final Finder f = find.byWidgetPredicate(
    (Widget w) => w is RzButton && w.label.startsWith(prefix),
  );
  await tester.ensureVisible(f.first);
  await tester.tap(f.first);
  await tester.pumpAndSettle();
}

Future<void> tapChip(WidgetTester tester, String label) async {
  await tester.ensureVisible(chip(label).first);
  await tester.pumpAndSettle();
  await tester.tap(chip(label).first);
  await tester.pump();
}

void main() {
  group('modules off (choyxona)', () {
    testWidgets('no course, guest, transfer, tips or clock controls', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      await openRestaurantOrder(tester, h);
      await tapButton(tester, "Taom qo'shish");
      expect(chip('Kurssiz'), findsNothing);
      expect(chip('Umumiy'), findsNothing);
      await tester.tap(find.byTooltip('Yopish'));
      await tester.pumpAndSettle();
      await openMenu(tester);
      expect(find.text('Boshqa ofitsiantga berish'), findsNothing);
      expect(find.text('Mehmonlar soni'), findsOneWidget);
      expect(find.text('Kurslar'), findsNothing);
      expect(find.textContaining('Ish vaqtingiz'), findsNothing);
    });
  });

  group('courses (P6-21)', () {
    testWidgets('a line gets the picked course and guest', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      RestaurantFake(h);
      await openRestaurantOrder(tester, h);
      await tapButton(tester, "Taom qo'shish");

      expect(chip('Kurssiz'), findsOneWidget);
      await tapChip(tester, '2-kurs');
      await tester.pump();
      // 4 guests at the table: seat chips 1..4
      expect(chip('4'), findsOneWidget);
      await tapChip(tester, '3');
      await tester.pump();
      await tester.tap(find.text('+ Choy'));
      await tester.pump();
      await tapButton(tester, 'Yuborish');

      final Map<String, Object?> body = bodies(h, 'POST', '/rounds').single;
      final Map<Object?, Object?> line =
          (body['items']! as List<Object?>).single! as Map<Object?, Object?>;
      expect(line['course_no'], 2);
      expect(line['seat_no'], 3);
      expect(line.containsKey('awaiting_weight'), isFalse);
      // the server holds course 2: its line waits for "2-kursni yuborish"
      await tester.scrollUntilVisible(
        find.text('2-kurs kutmoqda'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('2-kurs kutmoqda'), findsOneWidget);
      expect(find.text('3-mehmon'), findsOneWidget);
    });

    testWidgets('a held course is sent with "N-kursni yuborish"', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      RestaurantFake(h);
      final String uuid = await openRestaurantOrder(
        tester,
        h,
        before: (String _, List<Map<String, Object?>> items) {
          items[0]['course_no'] = 1;
          holdAsCourse(items[1], 2);
        },
      );

      expect(find.text('Kurslar'), findsOneWidget);
      expect(find.text('Yuborildi · tayyorlanmoqda'), findsOneWidget);
      expect(find.text('Kutmoqda'), findsOneWidget);
      // a held course is not sent by the ordinary send
      expect(buttonLabeled('Yuborish (1)'), findsNothing);

      await tapButton(tester, '2-kursni yuborish');
      expect(
        h.adapter.to('POST', '/v1/pos/orders/$uuid/courses/2/send'),
        hasLength(1),
      );
      expect(find.text('2-kurs oshxonaga yuborildi.'), findsOneWidget);
      expect(buttonLabeled('2-kursni yuborish'), findsNothing);
    });

    testWidgets('course ready: a toast for my order, none for another waiter', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      final RestaurantFake fake = RestaurantFake(h);
      final String uuid = seedWaiterOrder(h, tableId: 1);
      await pumpHome(tester, h);

      fake.courseReady(uuid, 2, waiterId: otherWaiterId);
      await tester.pumpAndSettle();
      expect(find.textContaining('kurs tayyor'), findsNothing);

      fake.courseReady(uuid, 2);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 500));
      expect(find.text('2-kurs tayyor — 1-xona'), findsOneWidget);
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(SnackBarAction, 'Ochish'));
      await tester.pumpAndSettle();
      expect(find.byType(OrderPage), findsOneWidget);
    });
  });

  group('skewers and weight later (P6-25)', () {
    testWidgets('×10 adds ten skewers; the grill time shows', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      RestaurantFake(h).seedMenu();
      await openRestaurantOrder(tester, h);
      await tapButton(tester, "Taom qo'shish");
      await tapChip(tester, 'Taomlar');
      await tester.pumpAndSettle();

      expect(find.text('~12 daq'), findsOneWidget);
      await tester.tap(find.bySemanticsLabel("Jigar: 10 dona qo'shish"));
      await tester.pump();
      await tapButton(tester, 'Yuborish');
      final Map<Object?, Object?> line =
          (bodies(h, 'POST', '/rounds').single['items']! as List<Object?>)
                  .single!
              as Map<Object?, Object?>;
      expect(line['quantity'], '10.000');
    });

    testWidgets('a fish weighed later: "Tortiladi", no bill until weighed', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      RestaurantFake(h).seedMenu();
      final String uuid = await openRestaurantOrder(tester, h);
      await tapButton(tester, "Taom qo'shish");
      await tapChip(tester, 'Taomlar');
      await tester.pumpAndSettle();
      expect(find.textContaining("og'irligi keyin tortiladi"), findsOneWidget);
      await tester.tap(find.text('Sazan'));
      await tester.pumpAndSettle();
      expect(find.textContaining('taxminiy miqdor'), findsOneWidget);
      await tester.tap(
        find.descendant(
          of: find.byType(PosNumericKeypad),
          matching: find.text('1'),
        ),
      );
      await tester.pump();
      await tapButtonStarting(tester, "Qo'shish");
      await tapButton(tester, 'Yuborish');

      expect(find.textContaining("Og'irlik kutilmoqda (1)"), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text("Og'irlikni kiritish"),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Tortiladi'), findsOneWidget);

      // (under the action bar on a short phone: pressed directly)
      tester
          .widget<RzButton>(buttonLabeled("Og'irlikni kiritish"))
          .onPressed!();
      await tester.pumpAndSettle();
      for (final String key in <String>['1', '.', '3', '5']) {
        await tester.tap(
          find.descendant(
            of: find.byType(PosNumericKeypad),
            matching: find.text(key),
          ),
        );
        await tester.pump();
      }
      await tapButtonStarting(tester, 'Saqlash: 1.35');
      final Map<String, Object?> body = bodies(h, 'POST', '/weight').single;
      expect(body['quantity'], '1.350');
      expect(h.adapter.to('POST', '/v1/pos/orders/$uuid/items/'), isNotEmpty);
      expect(find.text('Tortildi'), findsOneWidget);
      expect(find.textContaining("Og'irlik kutilmoqda"), findsNothing);
    });
  });

  group('guests and transfer (P6-24)', () {
    testWidgets("a sent line's guest is changed from the order", (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      RestaurantFake(h);
      await openRestaurantOrder(tester, h);
      await tester.tap(
        find.bySemanticsLabel("Mehmon: umumiy, o'zgartirish").first,
      );
      await tester.pumpAndSettle();
      await tapChip(tester, '2');
      await tester.pump();
      await tapButton(tester, 'Saqlash');
      expect(bodies(h, 'POST', '/seat').single['seat_no'], 2);
      expect(find.text('2-mehmon'), findsOneWidget);
    });

    testWidgets('a waiter hands the table over with a manager PIN', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      RestaurantFake(h);
      addOtherWaiter(h);
      final String uuid = await openRestaurantOrder(tester, h);
      await openMenu(tester);
      await tester.tap(find.text('Boshqa ofitsiantga berish'));
      await tester.pumpAndSettle();
      await tester.tap(find.text(otherWaiterName));
      await tester.pump();
      await tester.enterText(find.byType(TextField), 'smena tugadi');
      await tester.tap(buttonLabeled('Berish'));
      for (var i = 0; i < 4; i++) {
        await tester.pump(const Duration(milliseconds: 250));
      }

      // the waiter has no orders.transfer: the manager approves
      expect(find.byType(ManagerApprovalDialog), findsOneWidget);
      expect(find.text("Stolni boshqa ofitsiantga o'tkazish"), findsOneWidget);
      await typePin(tester, '5926');

      final List<Map<String, Object?>> sent = bodies(
        h,
        'POST',
        '/v1/pos/orders/$uuid/transfer',
      );
      expect(sent.last['waiter_id'], otherWaiterId);
      expect(sent.last['approval_token'], isNotNull);
      expect(sent.last['reason'], 'smena tugadi');
      expect(
        h.state.orders[uuid]!['waiter'],
        containsPair('id', otherWaiterId),
      );
      expect(find.text('Stol berildi: $otherWaiterName.'), findsOneWidget);
      // the order is someone else's now (read-only)
      expect(buttonLabeled("Taom qo'shish"), findsNothing);
    });

    testWidgets('orders.transfer alone shows the hand-over and needs no PIN', (
      WidgetTester tester,
    ) async {
      // Review S9: the server lets orders.transfer in without orders.manage
      final WaiterHarness h = WaiterHarness();
      RestaurantFake(h);
      addOtherWaiter(h);
      h.state.rolePermissions['waiter'] = <String>[
        'orders.view',
        'tables.manage',
        'menu.view',
        'orders.transfer',
      ];
      final String uuid = await openRestaurantOrder(tester, h);
      await openMenu(tester);
      await tester.tap(find.text('Boshqa ofitsiantga berish'));
      await tester.pumpAndSettle();
      await tester.tap(find.text(otherWaiterName));
      await tester.pump();
      await tester.tap(buttonLabeled('Berish'));
      for (var i = 0; i < 4; i++) {
        await tester.pump(const Duration(milliseconds: 250));
      }

      expect(find.byType(ManagerApprovalDialog), findsNothing);
      final List<Map<String, Object?>> sent = bodies(
        h,
        'POST',
        '/v1/pos/orders/$uuid/transfer',
      );
      expect(sent.single['waiter_id'], otherWaiterId);
      expect(sent.single['approval_token'], isNull);
      expect(
        h.state.orders[uuid]!['waiter'],
        containsPair('id', otherWaiterId),
      );
    });

    testWidgets(
      'neither orders.transfer nor orders.manage: no hand-over item',
      (WidgetTester tester) async {
        final WaiterHarness h = WaiterHarness();
        RestaurantFake(h);
        addOtherWaiter(h);
        h.state.rolePermissions['waiter'] = <String>[
          'orders.view',
          'tables.manage',
          'menu.view',
        ];
        await openRestaurantOrder(tester, h);
        final Finder menu = find.byTooltip('Menyu');
        if (menu.evaluate().isNotEmpty) {
          await tester.tap(menu.first);
          await tester.pumpAndSettle();
        }
        expect(find.text('Boshqa ofitsiantga berish'), findsNothing);
      },
    );

    testWidgets('the old waiter is told when a manager moves the table', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      RestaurantFake(h);
      addOtherWaiter(h);
      final String uuid = seedWaiterOrder(h, tableId: 1);
      await pumpHome(tester, h);
      h.state.emit('order.transferred', <String, Object?>{
        'order': <String, Object?>{
          'uuid': uuid,
          'status': 'open',
          'branch_id': 1,
        },
        'version': 3,
        'table_ref': '1-xona',
        'from_waiter_id': 4,
        'to_waiter_id': otherWaiterId,
      });
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 500));
      expect(
        find.text("1-xona boshqa ofitsiantga o'tkazildi: $otherWaiterName"),
        findsOneWidget,
      );
    });

    testWidgets('the new waiter is told the table is theirs', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      RestaurantFake(h);
      addOtherWaiter(h);
      final String uuid = seedWaiterOrder(
        h,
        tableId: 2,
        waiterId: otherWaiterId,
        waiterName: otherWaiterName,
      );
      await pumpHome(tester, h);
      h.state.emit('order.transferred', <String, Object?>{
        'order': <String, Object?>{
          'uuid': uuid,
          'status': 'open',
          'branch_id': 1,
        },
        'version': 3,
        'table_ref': '2-xona',
        'from_waiter_id': otherWaiterId,
        'to_waiter_id': 4,
      });
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 500));
      expect(find.text('2-xona sizga berildi'), findsOneWidget);
    });
  });

  group('my tips and clock (P6-22, P6-23)', () {
    testWidgets('"Mening choy pulim" sums my tips of the day', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      final RestaurantFake fake = RestaurantFake(h)
        ..addTip('20000.00', source: 'change')
        ..addTip('15000.00', method: 'card');
      await pumpHome(tester, h);
      await openMenu(tester);
      await tester.tap(find.text('Mening choy pulim'));
      await tester.pumpAndSettle();
      expect(find.byType(MyTipsPage), findsOneWidget);
      expect(find.bySemanticsLabel(RegExp('Choy pulim: 35')), findsOneWidget);
      expect(find.text('Karta'), findsOneWidget);
      expect(find.text('Naqd · qaytimdan'), findsOneWidget);
      expect(fake.tipRows, hasLength(2));
    });

    testWidgets('pool mode explains the shared pot', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      final RestaurantFake fake = RestaurantFake(h);
      fake.tips['mode'] = 'pool';
      await pumpHome(tester, h);
      await openMenu(tester);
      await tester.tap(find.text('Mening choy pulim'));
      await tester.pumpAndSettle();
      expect(find.textContaining('umumiy qozonga'), findsOneWidget);
      expect(find.text("Hali choy puli yo'q"), findsOneWidget);
    });

    testWidgets('"Ishga keldim" then "Ishdan ketdim"', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      final RestaurantFake fake = RestaurantFake(h);
      await pumpHome(tester, h);
      expect(find.text('Ish vaqtingiz belgilanmagan.'), findsOneWidget);

      await tapButton(tester, 'Ishga keldim');
      expect(fake.clockSession, isNotNull);
      expect(find.text('Ish vaqtingiz belgilanmagan.'), findsNothing);
      expect(find.text('Ishga kelganingiz belgilandi.'), findsOneWidget);

      await openMenu(tester);
      await tester.tap(find.text('Ishdan ketdim'));
      await tester.pumpAndSettle();
      expect(find.text('Ishdan ketdingizmi?'), findsOneWidget);
      await tapButton(tester, 'Ishdan ketdim');
      expect(fake.clockSession, isNull);
      expect(find.text('Ish vaqtingiz belgilanmagan.'), findsOneWidget);
    });

    testWidgets('offline: clocking in says the internet is needed', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      final RestaurantFake fake = RestaurantFake(h);
      await pumpHome(tester, h);
      h.adapter.offline = true;
      await tester.pump(const Duration(seconds: 61));
      await tester.pumpAndSettle();
      await tapButton(tester, 'Ishga keldim');
      expect(find.textContaining('internet kerak'), findsOneWidget);
      expect(fake.clockSession, isNull);
      h.adapter.offline = false;
    });
  });

  group('hostess desk (P6-24, P6-26)', () {
    Future<RestaurantFake> signInHostess(
      WidgetTester tester,
      WaiterHarness h, {
      Size size = const Size(412, 860),
    }) async {
      final RestaurantFake fake = RestaurantFake(h)..addHostess();
      fake
        ..addWaiting('Jasur', party: 4, waited: 22, quoted: 15)
        ..addWaiting('Madina', party: 2, waited: 3, phone: null)
        ..addBooking(
          name: 'Anvar',
          startsAt: h.now.add(const Duration(hours: 2)),
          guests: 6,
          tableId: 11,
        );
      h.state.tables.add(<String, Object?>{
        'id': 21,
        'hall_id': 1,
        'name': 'Stol 5',
        'kind': 'table',
        'seats': 4,
        'layout': <String, Object?>{'x': null, 'y': null, 'w': null, 'h': null},
        'sort_order': 9,
        'min_spend': null,
        'min_spend_mode': 'per_table',
      });
      // a table booking: the deposit rule applies to tables only
      fake.bookings.single['dining_table_id'] = 21;
      await settle(tester, h.client.auth.pairTerminal('ABCD-EFGH'));
      await pumpWaiter(tester, h, size: size);
      await tester.tap(find.text(hostessName));
      await tester.pumpAndSettle();
      await typePin(tester, hostessPin);
      return fake;
    }

    testWidgets('the waitlist: add, call, seat; over-quoted wait stands out', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      final RestaurantFake fake = await signInHostess(
        tester,
        h,
        // a tall phone: the whole list in view
        size: const Size(412, 1400),
      );
      expect(find.byType(HostessPage), findsOneWidget);
      expect(find.text('Kutish (2)'), findsOneWidget);
      expect(find.text('Jasur'), findsOneWidget);
      expect(find.text('22 / 15 daq'), findsOneWidget);
      expect(find.text('+998901234567'), findsOneWidget);

      // add
      await tapButton(tester, 'Mehmon yozish');
      await tester.enterText(find.widgetWithText(TextField, 'Ismi'), 'Olim');
      await tester.tap(find.bySemanticsLabel("Mehmonni ko'paytirish"));
      await tester.pump();
      await tapChip(tester, '30 daq');
      await tester.pump();
      await tapButton(tester, "Ro'yxatga yozish");
      final Map<String, Object?> added = bodies(
        h,
        'POST',
        '/v1/pos/waitlist',
      ).single;
      expect(added['guest_name'], 'Olim');
      expect(added['party_size'], 3);
      expect(added['quoted_wait_minutes'], 30);
      expect(find.text('Olim'), findsOneWidget);

      // call
      await tapButton(tester, 'Chaqirish');
      expect(h.adapter.to('POST', '/notify'), hasLength(1));
      expect(find.textContaining('Chaqirildi'), findsOneWidget);

      // seat Jasur at 1-xona (the hostess cannot open orders: no switch)
      await tapButton(tester, 'Joylashtirish');
      expect(find.text('Buyurtmani ham ochish'), findsNothing);
      await tester.tap(find.text('1-xona'));
      await tester.pump();
      await tapButton(tester, '1-xonaga joylashtirish');
      expect(bodies(h, 'POST', '/seat').single['dining_table_id'], 1);
      expect(fake.waitlist.first['status'], 'seated');
      expect(find.text('Joylashdi: 1-xona'), findsOneWidget);
    });

    testWidgets('tables: cleaning, then ready', (WidgetTester tester) async {
      final WaiterHarness h = WaiterHarness();
      final RestaurantFake fake = await signInHostess(tester, h);
      await tester.tap(find.text('Stollar'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Tozalash').first);
      await tester.pumpAndSettle();
      expect(bodies(h, 'PUT', '/state').single['state'], 'cleaning');
      expect(fake.serviceStates[1], 'cleaning');
      expect(find.text('1 ta stol tozalanmoqda'), findsOneWidget);

      await tapButton(tester, 'Tayyor');
      expect(bodies(h, 'PUT', '/state').last['state'], 'free');
      expect(fake.serviceStates, isEmpty);
    });

    testWidgets("today's bookings show the missing deposit", (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      await signInHostess(tester, h);
      await tester.tap(find.text('Bronlar'));
      await tester.pumpAndSettle();
      expect(find.text('Anvar'), findsOneWidget);
      // 6 guests × 50 000 (per guest from 6), nothing taken
      expect(find.textContaining("Bo'nak yetmaydi"), findsOneWidget);
      expect(find.textContaining('300'), findsWidgets);
      // The desk takes no money: the deposit is taken at the till (a cash one needs its open shift)
      expect(find.textContaining("Bo'nak kassada olinadi"), findsOneWidget);
      expect(find.textContaining("Bo'nak olish"), findsNothing);
      expect(find.textContaining('Avans olish'), findsNothing);
    });

    testWidgets('a manager books a walk-in without the lead time', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      final RestaurantFake fake = RestaurantFake(h);
      h.state.tables.add(<String, Object?>{
        'id': 21,
        'hall_id': 1,
        'name': 'Stol 5',
        'kind': 'table',
        'seats': 4,
        'layout': <String, Object?>{'x': null, 'y': null, 'w': null, 'h': null},
        'sort_order': 9,
        'min_spend': null,
        'min_spend_mode': 'per_table',
      });
      await pumpHome(tester, h, name: 'Malika Rahimova');
      // a manager has both: the rooms and the hostess tab
      await tester.tap(find.text('Hostes'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Bronlar'));
      await tester.pumpAndSettle();
      await tapButton(tester, "Bron qo'shish");
      await tester.enterText(
        find.widgetWithText(TextField, 'Mehmon ismi'),
        'Sardor',
      );
      await tester.tap(find.byType(DropdownButtonFormField<int>));
      await tester.pumpAndSettle();
      await tester.tap(find.textContaining('Stol 5').last);
      await tester.pumpAndSettle();
      await tapChip(tester, 'Hozir');
      await tester.pump();
      // 60 min lead time: "now" is refused until the walk-in switch
      expect(find.textContaining('kamida 60 daqiqa oldin'), findsOneWidget);
      await tester.ensureVisible(find.text("Yo'lda kelayotgan mehmon"));
      await tester.tap(find.text("Yo'lda kelayotgan mehmon"));
      await tester.pump();
      await tapButton(tester, 'Bron qilish');

      final Map<String, Object?> body = fake.bookingBodies.single;
      expect(body['skip_lead_time'], isTrue);
      expect(body['source'], 'walk_in');
      expect(body['dining_table_id'], 21);
      expect(find.text('Sardor'), findsOneWidget);
    });

    testWidgets('a tablet shows the waitlist beside the tables', (
      WidgetTester tester,
    ) async {
      final WaiterHarness h = WaiterHarness();
      await signInHostess(tester, h, size: const Size(1280, 800));
      expect(find.text("Kutish ro'yxati"), findsOneWidget);
      expect(find.text('Stollar'), findsOneWidget);
      expect(find.text('Kutish (2)'), findsNothing);
      expect(buttonLabeled('Tozalash'), findsWidgets);
    });
  });
}
