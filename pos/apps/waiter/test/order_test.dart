import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_ui/rizqim_ui.dart';
import 'package:rizqim_waiter/src/features/order/order_controller.dart';
import 'package:rizqim_waiter/src/features/order/order_page.dart';

import 'support/harness.dart';

/// Ofitsiantning o'z buyurtmasi ochilgan ekran.
Future<String> openOwnOrder(
  WidgetTester tester,
  WaiterHarness h, {
  List<(int, String)> lines = const <(int, String)>[(3, '1.500'), (1, '2')],
  bool sent = true,
  void Function(String uuid)? before,
}) async {
  final String uuid = seedWaiterOrder(h, tableId: 1, lines: lines, sent: sent);
  before?.call(uuid);
  await pumpHome(tester, h);
  await tester.tap(find.text('1-xona'));
  await tester.pumpAndSettle();
  expect(find.byType(OrderPage), findsOneWidget);
  return uuid;
}

Future<void> openAddSheet(WidgetTester tester) async {
  await tapButton(tester, "Taom qo'shish");
  expect(find.text('Taom qidirish'), findsOneWidget);
}

void main() {
  testWidgets('shows rounds with line statuses and the server totals', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    await openOwnOrder(tester, h);

    expect(find.text('1-bosqich'), findsOneWidget);
    expect(find.text('Osh (Choyxona oshi)'), findsOneWidget);
    expect(find.text('1.5 kg (guruch)'), findsOneWidget);
    expect(find.text('Choy (Choynak)'), findsOneWidget);
    expect(find.text('Oshxonada'), findsNWidgets(2));
    expect(find.text('4 mehmon'), findsOneWidget);
    expect(find.text('Taomlar'), findsOneWidget);
    expect(find.text('Jami'), findsOneWidget);
    expect(find.text('Xizmat haqi (10%)'), findsOneWidget);
    expect(find.text('Xona puli'), findsOneWidget);
    expect(buttonLabeled("Taom qo'shish"), findsOneWidget);
    expect(buttonLabeled("Schyot so'rash"), findsOneWidget);
    // oshxonaga yuborilmagan qator yo'q: "Yuborish" tugmasi ham yo'q
    expect(buttonLabeled('Yuborish (1)'), findsNothing);
  });

  testWidgets(
    'P3-37: the cashier\'s discount shows in the totals; the waiter has no discount action',
    (WidgetTester tester) async {
      final WaiterHarness h = WaiterHarness();
      await openOwnOrder(
        tester,
        h,
        before: (String uuid) {
          h.state.orders[uuid]!
            ..['discount_kind'] = 'percent'
            ..['discount_value'] = '10.00'
            ..['discount_reason'] = 'Doimiy mijoz';
          h.state.orders[uuid]!['discount'] = '19000.00';
        },
      );
      expect(find.text('Chegirma 10%'), findsOneWidget);
      expect(find.text('−${const PosMoney.som(19000).format()}'), findsNothing);
      expect(find.text(const PosMoney.som(-19000).format()), findsOneWidget);
      expect(find.textContaining('Chegirma berish'), findsNothing);
      expect(find.byIcon(Icons.percent_rounded), findsNothing);
    },
  );

  testWidgets('adds a round: quick buttons, kg keypad, modifiers, then sends', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = await openOwnOrder(tester, h);
    await openAddSheet(tester);

    // choy-non tez tugmalari
    expect(find.text('+ Choy'), findsOneWidget);
    expect(find.text('+ Non'), findsOneWidget);
    await tester.tap(find.text('+ Choy'));
    await tester.pump();
    expect(find.text('+ Choy  x1'), findsOneWidget);

    // kg taom: klaviatura (qadam 0.5): 1 kg
    await tester.tap(find.widgetWithText(ChoiceChip, 'Taomlar'));
    await tester.pumpAndSettle();
    expect(find.text('Qoldi: 8'), findsOneWidget);
    await tester.tap(find.text('Osh'));
    await tester.pumpAndSettle();
    await tester.tap(find.bySemanticsLabel('2').last);
    await tester.pump();
    // 2 → "2": qadamga to'g'ri; 0.7 tez tugmasi ko'rsatilmaydi (qadam 0.5)
    expect(find.bySemanticsLabel('0.7'), findsNothing);
    await tapButton(tester, 'Qo\'shish: 2 kg (guruch)');

    // modifikatorli taom: "Piyoz"
    await tester.tap(find.byTooltip('Shashlik: tanlash'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Piyoz'));
    await tester.pump();
    await tapButton(tester, "Qo'shish");

    // savat
    expect(find.text('Savat'), findsOneWidget);
    await tapButton(tester, 'Yuborish');

    expect(find.text('Taom qidirish'), findsNothing);
    expect(find.text('2-bosqich'), findsOneWidget);
    expect(find.text('Shashlik (Six)'), findsOneWidget);
    expect(find.text('+ Piyoz'), findsOneWidget);
    expect(find.text('2 kg (guruch)'), findsOneWidget);

    final Map<String, Object?> body = bodies(
      h,
      'POST',
      '/v1/pos/orders/$uuid/rounds',
    ).single;
    expect(body['version'], 1);
    expect(body['fire_mode'], 'now');
    expect(body['send'], true);
    final List<Map<String, Object?>> items = (body['items']! as List<Object?>)
        .cast<Map<String, Object?>>();
    expect(
      items.map((Map<String, Object?> i) => i['variant_id']).toList(),
      <String>[
        FakePosState.variantUuid(1),
        FakePosState.variantUuid(3),
        FakePosState.variantUuid(4),
      ],
    );
    expect(items[0]['quantity'], '1.000');
    expect(items[1]['quantity'], '2.000');
    expect(items[2]['modifiers'], <Object?>[
      <String, Object?>{
        'id': '00000000-0000-4000-8000-0000000000a1',
        'quantity': 1,
      },
    ]);
    // har o'zgartiruvchi so'rovda Idempotency-Key
    expect(
      h.adapter.to('POST', '/rounds').single.headers['Idempotency-Key'],
      isNotNull,
    );
    // yangi bosqich oshxonaga ketdi
    expect(
      h.state.orders[uuid]!['items'],
      everyElement(
        predicate<Object?>(
          (Object? i) =>
              (i! as Map<String, Object?>)['kitchen_status'] != 'pending',
        ),
      ),
    );
  });

  testWidgets('hold mode keeps the round back until its line is sent', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = await openOwnOrder(tester, h);
    await openAddSheet(tester);
    await tester.tap(find.text('+ Non'));
    await tester.pump();
    await tester.tap(find.text('Ushlab turish'));
    await tester.pump();
    // ushlab turishda tugma "Saqlash"
    expect(buttonLabeled('Saqlash'), findsOneWidget);
    await tapButton(tester, 'Saqlash');
    // xabar yo'qolguncha (u tugmalar ustini yopib turadi)
    await tester.pump(const Duration(seconds: 4));
    await tester.pumpAndSettle();

    expect(find.text('Ushlab turilgan'), findsOneWidget);
    final Map<String, Object?> body = bodies(
      h,
      'POST',
      '/v1/pos/orders/$uuid/rounds',
    ).single;
    expect(body['fire_mode'], 'hold');
    expect(body.containsKey('send'), isFalse);
    // ushlab turilgan qator "Yuborish (N)" ga kirmaydi, o'zining tugmasi bor
    expect(buttonLabeled('Yuborish (1)'), findsNothing);

    await tapButton(tester, 'Yuborish');
    expect(find.text('Ushlab turilgan'), findsNothing);
    expect(
      bodies(h, 'POST', '/v1/pos/orders/$uuid/send').single['item_ids'],
      hasLength(1),
    );
  });

  testWidgets('the order screen sends lines left unsent', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = await openOwnOrder(tester, h, sent: false);
    expect(find.text('Yuborilmagan'), findsNWidgets(2));
    expect(buttonLabeled('Yuborish (2)'), findsOneWidget);
    await tapButton(tester, 'Yuborish (2)');
    expect(find.text('Oshxonada'), findsNWidgets(2));
    expect(buttonLabeled('Yuborish (2)'), findsNothing);
    expect(bodies(h, 'POST', '/v1/pos/orders/$uuid/send'), hasLength(1));
  });

  testWidgets('a pending line can be removed before sending', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = await openOwnOrder(tester, h, sent: false);
    await tester.tap(find.byTooltip('Olib tashlash').first);
    await tester.pumpAndSettle();
    expect(h.adapter.to('POST', '/items/'), isNotEmpty);
    expect(find.text("O'chirildi"), findsOneWidget);
    expect(h.state.orders[uuid], isNotNull);
  });

  testWidgets('required modifier groups block the add button until chosen', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    // Shashlik: "Pishirish" guruhi majburiy, 1 tadan 1 tagacha
    final Map<String, Object?> shashlik = h.state.products.firstWhere(
      (Map<String, Object?> p) => p['id'] == 4,
    );
    final List<Object?> groups = shashlik['modifier_groups']! as List<Object?>;
    groups.add(<String, Object?>{
      'id': 2,
      'uuid': 'mg-2',
      'name': 'Pishirish',
      'min_select': 1,
      'max_select': 1,
      'is_required': true,
      'sort_order': -1,
      'is_active': true,
      'modifiers': <Object?>[
        <String, Object?>{
          'id': 5,
          'uuid': '00000000-0000-4000-8000-0000000000b1',
          'name': 'Yaxshi pishgan',
          'price_delta': '0.00',
          'max_quantity': 1,
          'is_available': true,
        },
        <String, Object?>{
          'id': 6,
          'uuid': '00000000-0000-4000-8000-0000000000b2',
          'name': 'Yarim pishgan',
          'price_delta': '0.00',
          'max_quantity': 1,
          'is_available': true,
        },
      ],
    });
    await openOwnOrder(tester, h);
    await openAddSheet(tester);
    await tester.tap(find.widgetWithText(ChoiceChip, 'Taomlar'));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Shashlik: tanlash'));
    await tester.pumpAndSettle();

    expect(find.text('Pishirish'), findsOneWidget);
    expect(find.text('Majburiy: bittasini tanlang'), findsOneWidget);
    expect(find.text('Kamida bitta tanlang'), findsOneWidget);
    final Finder add = buttonLabeled("Qo'shish");
    expect(
      tester
          .widget<FilledButton>(
            find.descendant(of: add, matching: find.byType(FilledButton)),
          )
          .onPressed,
      isNull,
    );

    await tester.tap(find.text('Yaxshi pishgan'));
    await tester.pump();
    expect(find.text('Kamida bitta tanlang'), findsNothing);
    // bitta tanlov: ikkinchisini tanlash birinchisini almashtiradi
    await tester.tap(find.text('Yarim pishgan'));
    await tester.pump();
    await tester.tap(find.text('Piyoz'));
    await tester.pump();
    await tapButton(tester, "Qo'shish");
    expect(find.text('Savat'), findsOneWidget);
    await tapButton(tester, 'Yuborish');

    final Map<String, Object?> body = bodies(h, 'POST', '/rounds').single;
    final List<Object?> mods =
        ((body['items']! as List<Object?>).single!
                as Map<String, Object?>)['modifiers']!
            as List<Object?>;
    expect(
      mods.map((Object? m) => (m! as Map<String, Object?>)['id']).toList(),
      <String>[
        '00000000-0000-4000-8000-0000000000b2',
        '00000000-0000-4000-8000-0000000000a1',
      ],
    );
  });

  testWidgets('ready lines are highlighted and marked served', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = await openOwnOrder(
      tester,
      h,
      before: (String uuid) {
        markLineReady(h, uuid, 0);
        markLineReady(h, uuid, 1);
      },
    );
    expect(find.text('2 ta taom tayyor: olib borish kerak'), findsOneWidget);
    expect(find.text('Tayyor'), findsNWidgets(2));
    expect(buttonLabeled('Berildi'), findsNWidgets(2));

    await tester.tap(buttonLabeled('Berildi').first);
    await tester.pumpAndSettle();
    expect(find.text('Tayyor'), findsOneWidget);
    // berilgan qatorning belgisi + qolgan tayyor qatorning tugmasi
    expect(find.text('Berildi'), findsNWidgets(2));
    expect(find.text('1 ta taom tayyor: olib borish kerak'), findsOneWidget);
    final RequestOptions served = h.adapter.to('POST', '/served').single;
    expect(served.headers['Idempotency-Key'], isNotNull);
    expect(bodies(h, 'POST', '/served').single['version'], 1);

    // oxirgisini ham berdi
    await tester.tap(buttonLabeled('Berildi').first);
    await tester.pumpAndSettle();
    expect(find.text('Tayyor'), findsNothing);
    expect(find.textContaining('taom tayyor'), findsNothing);
    expect(h.adapter.to('POST', '/served'), hasLength(2));
    expect(h.state.orders[uuid], isNotNull);
  });

  testWidgets('"Hammasini berdim" serves every ready line in one tap', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    await openOwnOrder(
      tester,
      h,
      before: (String uuid) {
        markLineReady(h, uuid, 0);
        markLineReady(h, uuid, 1);
      },
    );
    await tapButton(tester, 'Hammasini berdim (2)');
    expect(h.adapter.to('POST', '/served'), hasLength(2));
    expect(find.text('Tayyor'), findsNothing);
    expect(find.text('Berildi'), findsNWidgets(2));
  });

  testWidgets('guests can be changed from the order screen', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = await openOwnOrder(tester, h);
    await tester.tap(find.text('4 mehmon'));
    await tester.pumpAndSettle();
    expect(find.text('Mehmonlar soni'), findsOneWidget);
    await tester.tap(find.bySemanticsLabel("Mehmonni ko'paytirish"));
    await tester.pump();
    await tapButton(tester, 'Saqlash');
    expect(find.text('5 mehmon'), findsOneWidget);
    expect(
      bodies(h, 'POST', '/v1/pos/orders/$uuid/guests').single['guests_count'],
      5,
    );
  });

  testWidgets(
    'P3-35: a change a paid bill carries is refused: the waiter is told to ask the cashier, the order reloads',
    (WidgetTester tester) async {
      final WaiterHarness h = WaiterHarness();
      final String uuid = await openOwnOrder(tester, h);
      h.adapter.respond = (RequestOptions o) =>
          o.method == 'POST' && o.uri.path.endsWith('/guests')
          ? const FakeResponse(409, <String, Object?>{
              'success': false,
              'message': "Bu o'zgarish to'lovi olingan schyotga tegadi.",
              'data': <String, Object?>{
                'reason': 'paid_bill_affected',
                'bills_reason': 'bills_out_of_date',
              },
            })
          : null;
      final int reads = h.adapter.to('GET', '/v1/pos/orders/$uuid').length;
      await tester.tap(find.text('4 mehmon'));
      await tester.pumpAndSettle();
      await tester.tap(find.bySemanticsLabel("Mehmonni kamaytirish"));
      await tester.pump();
      await tapButton(tester, 'Saqlash');
      expect(find.textContaining("schyoti to'lana boshlagan"), findsWidgets);
      expect(find.textContaining('kassirga ayting'), findsWidgets);
      expect(find.text('4 mehmon'), findsOneWidget, reason: 'nothing changed');
      expect(
        h.adapter.to('GET', '/v1/pos/orders/$uuid').length,
        greaterThan(reads),
      );
    },
  );

  testWidgets('the order can be moved to a free room', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = await openOwnOrder(tester, h);
    await tester.tap(find.byTooltip('Menyu'));
    await tester.pumpAndSettle();
    await tester.tap(find.text("Boshqa joyga ko'chirish"));
    await tester.pumpAndSettle();
    // faqat bo'sh joylar: o'zining 1-xonasi yo'q
    final Finder sheet = find.byType(BottomSheet);
    expect(
      find.descendant(of: sheet, matching: find.text('1-xona')),
      findsNothing,
    );
    await tester.tap(find.descendant(of: sheet, matching: find.text('3-xona')));
    await tester.pumpAndSettle();
    expect(find.text('3-xona'), findsWidgets);
    expect(
      bodies(h, 'POST', '/v1/pos/orders/$uuid/move').single['table_id'],
      3,
    );
  });

  testWidgets('"Schyot so\'rash" sends the bill to the cashier, no payment', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = await openOwnOrder(tester, h);
    expect(find.text("Schyot so'ralgan"), findsNothing);
    await tester.tap(buttonLabeled("Schyot so'rash"));
    await tester.pumpAndSettle();
    // tasdiq so'raladi, to'lov haqida gap yo'q
    expect(find.textContaining("ilovasida to'lov yo'q"), findsOneWidget);
    await tapButton(tester, "So'rash");

    // P3-26: alohida "schyot so'rash" endpointi — schyotni ofitsiant tuzmaydi
    final Map<String, Object?> body = bodies(
      h,
      'POST',
      '/v1/pos/orders/$uuid/request-bill',
    ).single;
    expect(body['version'], 1);
    expect(h.adapter.to('POST', '/bills'), isEmpty);
    expect(h.state.orders[uuid]!['bill_requested_at'], isNotNull);
    expect(find.text("Schyot so'ralgan"), findsWidgets);
    expect(find.textContaining("Schyot so'raldi: kassir"), findsOneWidget);
    // ikkinchi marta so'rab bo'lmaydi (tugma o'chiq)
    expect(
      tester.widget<RzButton>(buttonLabeled("Schyot so'ralgan")).onPressed,
      isNull,
    );
    // ofitsiant ilovasida to'lov va chek chiqarish chaqirilmaydi
    expect(h.adapter.to('POST', '/payments'), isEmpty);
    expect(h.adapter.to('POST', '/print'), isEmpty);
  });

  testWidgets(
    'a round whose answer was lost is not added twice when repeated after a refresh',
    (WidgetTester tester) async {
      final WaiterHarness h = WaiterHarness();
      final String uuid = seedWaiterOrder(h, tableId: 1);
      await pumpHome(tester, h);
      final OrderController ctl = OrderController(
        data: h.services.data,
        uuid: uuid,
      );
      addTearDown(ctl.dispose);
      await settle(tester, ctl.load());
      List<Object?> items() => h.state.orders[uuid]!['items']! as List<Object?>;
      final int before = items().length;

      // The server applies the round, the answer never reaches the phone (Wi-Fi drop)
      var lose = true;
      h.adapter.respond = (RequestOptions o) {
        if (lose && o.method == 'POST' && o.uri.path.endsWith('/rounds')) {
          lose = false;
          h.state.handle(
            o.method,
            o.uri.path,
            o.uri.queryParameters,
            o.data,
            (o.headers['Authorization'] as String?)?.replaceFirst(
              'Bearer ',
              '',
            ),
            idempotencyKey: o.headers['Idempotency-Key'] as String?,
          );
          throw DioException.connectionError(requestOptions: o, reason: 'lost');
        }
        return null;
      };
      final List<PosLineInput> lines = <PosLineInput>[
        PosLineInput(
          variantUuid: FakePosState.variantUuid(2),
          quantity: const PosQuantity.whole(1),
        ),
      ];
      Future<T> direct<T>(Future<T> Function(String?) a) => a(null);
      expect(
        await settle(tester, ctl.addRound(direct, lines, PosFireMode.now)),
        isFalse,
      );
      expect(items(), hasLength(before + 1));

      // The poll refreshes the order (the version moved on); the waiter repeats the action as told
      await settle(tester, ctl.load(silent: true));
      expect(
        await settle(tester, ctl.addRound(direct, lines, PosFireMode.now)),
        isTrue,
      );
      expect(items(), hasLength(before + 1), reason: 'applied once');
      final List<RequestOptions> sent = h.adapter
          .to('POST', '/rounds')
          .toList();
      expect(sent, hasLength(2));
      expect(
        sent.map((RequestOptions r) => r.headers['Idempotency-Key']).toSet(),
        hasLength(1),
      );
      expect(h.state.replayed, 1);
      expect(ctl.order!.items, hasLength(before + 1));

      // The same lines again, on purpose: a new round
      expect(
        await settle(tester, ctl.addRound(direct, lines, PosFireMode.now)),
        isTrue,
      );
      expect(items(), hasLength(before + 2));
    },
  );
}
