import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_cashier/src/counter/counter_page.dart';
import 'package:rizqim_cashier/src/counter/counter_queue.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/billing/receipts.dart';
import 'package:rizqim_cashier/src/features/order/combo_sheet.dart';
import 'package:rizqim_cashier/src/printing/label_preview.dart';

import 'support/counter.dart';
import 'support/fast_food.dart';
import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

/// Phase 6 wave 3 stage B (fast food at the till): the combo sheet (the default in 2 taps, deltas, a sold-out option
/// hidden, editing the line), quick modifiers (sizes on the tile and on the line, defaults preselected, explicit
/// modifiers sent), cup stickers and bag labels on the label printer after payment (drinks inside combos too), packaging
/// lines with "Olib tashlash" and "Qaytarish" (removal disabled offline), the pickup time ("Vaqtga") on the order and the
/// talon, the queue blocks rolling at the business day start, and an offline combo sale.
Map<String, Object?> fastFoodFormat() => formatBlock(
  profile: 'fast_food',
  label: 'Fast food',
  modules: const <String>[
    'quick_counter',
    'queue_numbers',
    'queue_calling',
    'terminal_card_flow',
    'combo',
    'quick_modifiers',
    'labels',
    'packaging',
    'scheduled_pickup',
    'round_the_clock',
  ],
);

Future<(P22, FakeQueueBackend, FastFoodBackend)> fastFoodTill({
  bool labelPrinter = true,
  DateTime? nextDayAt,
}) async {
  final P22 p = P22(offlineStore: await SyncStore.open(MemorySyncStorage()));
  p.state.format = fastFoodFormat();
  p.state.openShift();
  // The queue layer first: the fast food layer adds its work on top of the order it opened
  final FakeQueueBackend q = FakeQueueBackend(p);
  final FastFoodBackend f = FastFoodBackend(
    p,
    labelPrinter: labelPrinter,
    nextDayAt: nextDayAt,
  );
  return (p, q, f);
}

Future<void> payExact(WidgetTester tester) async {
  await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
  await tester.pumpAndSettle();
  expect(find.byType(PaymentPage), findsOneWidget);
  await tester.tap(find.byKey(const ValueKey<String>('payment-submit')));
  await tester.pumpAndSettle();
}

Finder key(String k) => find.byKey(ValueKey<String>(k));

void main() {
  testWidgets(
    'combo: the default combo in 2 taps; its parts on the cart, the receipt and the stickers; the bag label',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend _, FastFoodBackend f) =
          await fastFoodTill();
      await pumpHome(tester, p.h);
      expect(find.byType(CounterPage), findsOneWidget);
      // The tile tells the default parts
      expect(find.text('Lavash · Pepsi 0,4'), findsOneWidget);

      final TapCounter taps = TapCounter(tester);
      await taps.tap(menuTile(comboId));
      expect(find.byType(ComboSheet), findsOneWidget);
      // The sold-out Fanta is not offered; Kola shows its delta
      expect(key('combo-option-${optionUuid(5)}'), findsNothing);
      expect(key('combo-option-${optionUuid(4)}'), findsOneWidget);
      expect(
        find.text('+${const PosMoney.som(1000).formatAmount()}'),
        findsOneWidget,
      );
      expect(
        find.text('+${const PosMoney.som(5000).formatAmount()}'),
        findsOneWidget,
      );
      await taps.tap(key('combo-add'));
      expect(taps.count, 2);
      expect(find.byType(ComboSheet), findsNothing);
      expect(find.text('· Asosiy — Lavash'), findsOneWidget);
      expect(find.text('· Ichimlik — Pepsi 0,4'), findsOneWidget);

      await payExact(tester);
      expect(key('counter-next'), findsOneWidget);

      // The server got the picks (explicit, one per slot) and priced the combo
      final List<Object?> sent = f.opened.single['items']! as List<Object?>;
      expect((sent.single! as Map<Object?, Object?>)['combo'], <Object?>[
        <String, Object?>{'option_id': optionUuid(1)},
        <String, Object?>{'option_id': optionUuid(3)},
      ]);
      // The receipt: one line, its parts below without prices
      final String receipt = p.receipts.firstWhere(
        (String r) => r.contains('SOTUV CHEKI'),
      );
      expect(receipt, contains('Lavash kombo'));
      expect(receipt, contains('  Asosiy - Lavash'));
      expect(receipt, contains('  Ichimlik - Pepsi 0,4'));

      // Labels: the drink inside the combo (with the combo's name) and the takeaway bag
      final List<String> labels = f.printedLabels;
      expect(labels, hasLength(2));
      expect(labels.first, contains('Pepsi 0,4'));
      expect(labels.first, contains('[Lavash kombo]'));
      expect(labels.first, contains('#~1~'));
      expect(labels.last, contains('Mahsulot: 2 ta'));
      expect(labels.last, contains('Olib ketish'));
    },
  );

  testWidgets('combo: other picks with their deltas, then the line is edited', (
    WidgetTester tester,
  ) async {
    final (P22 p, FakeQueueBackend _, FastFoodBackend f) = await fastFoodTill();
    await pumpHome(tester, p.h);
    await tester.tap(menuTile(comboId));
    await tester.pumpAndSettle();
    await tester.tap(key('combo-option-${optionUuid(2)}'));
    await tester.tap(key('combo-option-${optionUuid(4)}'));
    await tester.tap(key('combo-option-${optionUuid(6)}'));
    await tester.pumpAndSettle();
    // 39 000 + 5 000 + 1 000
    expect(
      tester.widget<Text>(key('combo-price')).data,
      const PosMoney.som(45000).format(),
    );
    await tester.tap(key('combo-add'));
    await tester.pumpAndSettle();
    expect(find.text('· Sous — Sous'), findsOneWidget);
    expect(find.text(som(45000)), findsWidgets);

    // A tap on the line opens the sheet with its picks: Pepsi instead of Kola, two of them
    await tester.tap(key('cart-1'));
    await tester.pumpAndSettle();
    expect(find.byType(ComboSheet), findsOneWidget);
    await tester.tap(key('combo-option-${optionUuid(3)}'));
    await tester.tap(key('combo-plus'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Saqlash'), findsOneWidget);
    await tester.tap(key('combo-add'));
    await tester.pumpAndSettle();
    expect(find.text('· Ichimlik — Pepsi 0,4'), findsOneWidget);
    // (39 000 + 5 000) × 2 and the bag
    expect(find.text(som(88000)), findsOneWidget);

    await payExact(tester);
    final Map<Object?, Object?> line =
        (f.opened.single['items']! as List<Object?>).single!
            as Map<Object?, Object?>;
    expect(line['quantity'], '2.000');
    expect(line['combo'], <Object?>[
      <String, Object?>{'option_id': optionUuid(2)},
      <String, Object?>{'option_id': optionUuid(3)},
      <String, Object?>{'option_id': optionUuid(6)},
    ]);
    // Two drinks inside the two combos: "1/2", "2/2"
    expect(
      f.printedLabels.where((String l) => l.contains('[Lavash kombo]')),
      hasLength(2),
    );
    expect(f.printedLabels.first, contains('1/2'));
  });

  testWidgets(
    'quick modifiers: a size on the tile in one tap, the line switches its size, defaults go out explicitly',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend _, FastFoodBackend f) =
          await fastFoodTill();
      await pumpHome(tester, p.h);
      // 0,7 straight from the tile (no dialog)
      await tester.tap(key('menu-$coffeeId-size-${sizeUuid(63)}'));
      await tester.pumpAndSettle();
      expect(find.byType(Dialog), findsNothing);
      expect(find.text(som(22000)), findsWidgets);
      // The tile itself adds the default size (0,5), no dialog either
      await tester.tap(menuTile(coffeeId));
      await tester.pumpAndSettle();
      expect(find.byType(Dialog), findsNothing);
      expect(key('cart-2'), findsOneWidget);
      expect(find.text(som(19000)), findsWidgets);

      // The first line goes to 0,3 and gets "Muzsiz" in one tap each
      await tester.tap(key('cart-1-mod-${sizeUuid(61)}'));
      await tester.pumpAndSettle();
      await tester.tap(key('cart-1-mod-${sizeUuid(65)}'));
      await tester.pumpAndSettle();
      expect(find.text(som(15000)), findsWidgets);
      // A required size cannot be taken off by tapping it again
      await tester.tap(key('cart-1-mod-${sizeUuid(61)}'));
      await tester.pumpAndSettle();
      expect(find.text(som(15000)), findsWidgets);

      await payExact(tester);
      final List<Object?> sent = f.opened.single['items']! as List<Object?>;
      List<Object?> mods(int i) =>
          (sent[i]! as Map<Object?, Object?>)['modifiers']! as List<Object?>;
      expect(
        mods(0).map((Object? m) => (m! as Map<Object?, Object?>)['id']).toSet(),
        <String>{sizeUuid(61), sizeUuid(65)},
      );
      expect(mods(1), <Object?>[
        <String, Object?>{'id': sizeUuid(62), 'quantity': 1},
      ]);
      // Stickers for the two coffees (drinks), the size and "Muzsiz" on the first
      final List<String> stickers = f.printedLabels
          .where((String l) => l.contains('Kofe'))
          .toList();
      expect(stickers, hasLength(2));
      expect(stickers.first, contains('+ 0,3, Muzsiz'));
    },
  );

  testWidgets(
    'packaging: the bag on the cart, taken off with a reason before paying, put back after opening',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend _, FastFoodBackend f) =
          await fastFoodTill();
      // A tall tablet: the waiting order's lines, the bag and the total on one screen
      await pumpHome(tester, p.h, size: const Size(1280, 1100));
      await tester.tap(menuTile(friId));
      await tester.pumpAndSettle();
      expect(key('packaging-lines'), findsOneWidget);
      expect(find.text('Paket'), findsOneWidget);
      // 14 000 + 1 000
      expect(find.text(som(15000)), findsWidgets);

      await tester.tap(key('packaging-remove-$bagRuleId'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.widgetWithText(ChoiceChip, "Mijoz o'z paketini olib keldi"),
      );
      await tester.pumpAndSettle();
      await tapButton(tester, 'Olib tashlash');
      expect(find.textContaining('Olib tashlandi: Mijoz'), findsOneWidget);
      expect(find.text(som(14000)), findsWidgets);

      // «To'lash»: the order opens, the bag comes off on the server too, the bill is without it
      await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
      await tester.pumpAndSettle();
      expect(f.removed, <(int, String)>[
        (bagRuleId, "Mijoz o'z paketini olib keldi"),
      ]);
      expect(
        tester.widget<Text>(key('payment-due')).data,
        const PosMoney.som(14000).format(),
      );
      // The guest wants the bag after all: back from the payment page, «Qaytarish», a new bill
      Navigator.of(tester.element(find.byType(PaymentPage))).pop();
      await tester.pumpAndSettle();
      await tester.tap(key('packaging-restore-$bagRuleId'));
      await tester.pumpAndSettle();
      expect(f.restored, <int>[bagRuleId]);
      await payExact(tester);
      expect(key('counter-next'), findsOneWidget);
      final List<Map<String, Object?>> bills = p.backend.bills.values.single;
      expect(
        bills
            .where((Map<String, Object?> b) => b['status'] == 'paid')
            .single['total'],
        '15000.00',
      );
    },
  );

  testWidgets(
    'packaging offline: shown, removal disabled with a plain message',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend _, FastFoodBackend _) =
          await fastFoodTill();
      FakeSyncBackend(p.state);
      await pumpHome(tester, p.h);
      p.h.adapter.offline = true;
      // The next request finds the network gone: the till is in the limited mode
      try {
        await drive(tester, p.h.data.shifts.current());
      } on Object {
        // expected
      }
      await tester.pumpAndSettle();
      expect(p.h.data.offline!.active, isTrue);
      await tester.tap(menuTile(friId));
      await tester.pumpAndSettle();
      expect(find.text('Paket'), findsOneWidget);
      expect(key('packaging-offline'), findsOneWidget);
      expect(
        tester.widget<TextButton>(key('packaging-remove-$bagRuleId')).onPressed,
        isNull,
      );
      // No pickup time offline either
      expect(key('pickup-open'), findsNothing);
      p.h.adapter.offline = false;
      await drive(tester, p.h.data.offline!.syncNow());
      await drive(tester, p.h.data.shifts.current());
    },
  );

  testWidgets(
    'pickup time: «Vaqtga» picks a slot, the order opens with it and the talon shows it',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend _, FastFoodBackend f) =
          await fastFoodTill();
      await pumpHome(tester, p.h);
      await tester.tap(menuTile(lavashId));
      await tester.pumpAndSettle();
      await tester.tap(key('pickup-open'));
      await tester.pumpAndSettle();
      await tester.tap(key('pickup-slot-13:45'));
      await tester.pumpAndSettle();
      expect(find.text('Olib ketish: 13:45'), findsOneWidget);

      await payExact(tester);
      expect(f.opened.single['pickup_at'], '2026-10-22T08:45:00.000Z');
      final String talon = p.receipts.firstWhere(
        (String r) => r.contains('Navbat raqamingiz'),
      );
      expect(talon, contains('Olib ketish: 13:45'));
    },
  );

  testWidgets('pickup time changes on an open order until the kitchen has it', (
    WidgetTester tester,
  ) async {
    final (P22 p, FakeQueueBackend _, FastFoodBackend f) = await fastFoodTill();
    await pumpHome(tester, p.h);
    await tester.tap(menuTile(lavashId));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
    await tester.pumpAndSettle();
    Navigator.of(tester.element(find.byType(PaymentPage))).pop();
    await tester.pumpAndSettle();
    // «To'lov kutilmoqda»: the time is set on the server
    await tester.tap(key('pickup-open'));
    await tester.pumpAndSettle();
    await tester.tap(key('pickup-slot-14:00'));
    await tester.pumpAndSettle();
    expect(f.pickupChanges, <String?>['2026-10-22T09:00:00.000Z']);
    expect(find.text('Olib ketish: 14:00'), findsOneWidget);
    // «Hozir» takes it off
    await tester.tap(key('pickup-open'));
    await tester.pumpAndSettle();
    await tester.tap(key('pickup-now'));
    await tester.pumpAndSettle();
    expect(f.pickupChanges.last, isNull);
  });

  testWidgets(
    'queue: at the business day start the till takes the new day\'s blocks and starts at 1 again',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend q, FastFoodBackend _) = await fastFoodTill(
        nextDayAt: DateTime.utc(2026, 10, 23),
      );
      await pumpHome(tester, p.h);
      await tester.tap(menuTile(friId));
      await tester.pumpAndSettle();
      await payExact(tester);
      await tester.tap(key('counter-next'));
      await tester.pumpAndSettle();
      expect(q.sentNumbers, <int?>[1]);
      final int calls = q.reserveCalls;

      // 05:30 in Tashkent the next day (a 24/7 shift goes on): the old day's block is not used
      p.h.now = DateTime.utc(2026, 10, 23, 0, 30);
      final CounterQueueNumbers numbers = CounterQueueNumbers.of(p.h.data);
      expect(numbers.dayRolled, isTrue);
      await tester.tap(menuTile(friId));
      await tester.pumpAndSettle();
      await payExact(tester);
      expect(q.reserveCalls, greaterThan(calls));
      expect(numbers.state.businessDate, '2026-10-23');
      expect(q.sentNumbers, <int?>[1, 1]);
      expect(numbers.dayRolled, isFalse);
    },
  );

  testWidgets('offline: a combo is sold through the till\'s own pricing', (
    WidgetTester tester,
  ) async {
    final (P22 p, FakeQueueBackend _, FastFoodBackend _) = await fastFoodTill();
    FakeSyncBackend(p.state);
    await pumpHome(tester, p.h);
    p.h.adapter.offline = true;
    await tester.tap(menuTile(comboId));
    await tester.pumpAndSettle();
    await tester.tap(key('combo-option-${optionUuid(4)}'));
    await tester.pumpAndSettle();
    await tester.tap(key('combo-add'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey<String>('counter-pay')));
    await tester.pumpAndSettle();
    final SyncOperation open = p.h.data.offline!.store.pending.firstWhere(
      (SyncOperation o) => o.type == SyncOpType.orderOpen,
    );
    final Map<Object?, Object?> line =
        (open.payload['items']! as List<Object?>).single!
            as Map<Object?, Object?>;
    expect(line['combo_delta'], '1000.00');
    expect(line['combo'], <Object?>[
      <String, Object?>{'option_id': optionUuid(1)},
      <String, Object?>{'option_id': optionUuid(4)},
    ]);
    // 40 000 for the combo and the bag priced offline as the server would
    expect(
      tester.widget<Text>(key('payment-due')).data,
      const PosMoney.som(41000).format(),
    );
    await tester.tap(key('payment-submit'));
    await tester.pumpAndSettle();
    p.h.adapter.offline = false;
    await drive(tester, p.h.data.offline!.syncNow());
    expect(p.h.data.offline!.store.pendingCount, 0);
  });

  testWidgets('label printer missing: the sale goes on, no sticker, no error', (
    WidgetTester tester,
  ) async {
    final (P22 p, FakeQueueBackend _, FastFoodBackend f) = await fastFoodTill(
      labelPrinter: false,
    );
    await pumpHome(tester, p.h);
    await tester.tap(key('menu-$coffeeId-size-${sizeUuid(62)}'));
    await tester.pumpAndSettle();
    await payExact(tester);
    expect(key('counter-next'), findsOneWidget);
    expect(f.printedLabels, isEmpty);
    // The stickers can still be looked at
    await tester.tap(key('counter-labels'));
    await tester.pumpAndSettle();
    // The coffee's sticker and the bag label
    expect(find.byType(LabelPreview), findsNWidgets(2));
    expect(find.textContaining('Stiker printeri sozlanmagan'), findsOneWidget);
  });

  testWidgets(
    'a label printer that fails never blocks payment: a plain warning with a retry',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend _, FastFoodBackend f) =
          await fastFoodTill();
      p.h
          .printerFor('server-$labelPrinterId')
          .failNext(
            1,
            const PrinterException(
              PrinterErrorKind.offline,
              'Printer javob bermadi',
            ),
          );
      await pumpHome(tester, p.h);
      await tester.tap(key('menu-$coffeeId-size-${sizeUuid(62)}'));
      await tester.pumpAndSettle();
      await payExact(tester);
      expect(key('counter-next'), findsOneWidget);
      expect(find.textContaining('Stiker chop etilmadi'), findsOneWidget);
      await tapButton(tester, 'Qayta urinish');
      expect(find.textContaining('Stiker chop etilmadi'), findsNothing);
      expect(f.printedLabels, isNotEmpty);
    },
  );

  test('the sale receipt puts a combo\'s parts under its server line (single mode)', () {
    final PosBill bill = PosBill.fromJson(
      PosJson.of(<String, Object?>{
        'uuid': 'b1',
        'number': 1,
        'status': 'paid',
        'items': <Object?>[
          <String, Object?>{
            'order_item_id': 1,
            'product_name': 'Lavash kombo',
            'variant_name': null,
            'unit': 'piece',
            'quantity': '1.000',
            'amount': '45000.00',
            'discount_amount': '0.00',
            'combo_parts': <Object?>[
              <String, Object?>{
                'slot_name': 'Asosiy',
                'name': 'Lavash katta',
                'label': "1 ta lavash — katta, mol go'shtli",
              },
              <String, Object?>{
                'slot_name': 'Ichimlik',
                'name': 'Pepsi',
                'label': 'Ichimlik — 0,4 l Pepsi',
              },
            ],
          },
        ],
        'packaging_amount': '1000.00',
        'packaging_lines': <Object?>[
          <String, Object?>{
            'rule_id': 1,
            'name': 'Paket',
            'quantity': 1,
            'unit': 'dona',
            'amount': '1000.00',
          },
        ],
        'subtotal': '45000.00',
        'total': '46000.00',
        'paid_amount': '46000.00',
        'due_amount': '0.00',
        'payments': const <Object?>[],
      }),
    );
    const ReceiptHeader header = ReceiptHeader(storeName: 'Rizqim Fast Food');
    // No server receipt yet: the bill's lines, the parts and the packaging line
    final FiscalReceipt local = saleReceiptOf(
      bill: bill,
      header: header,
      now: DateTime(2026, 10, 22, 18),
    );
    expect(local.lines.first.parts, <String>[
      "1 ta lavash — katta, mol go'shtli",
      'Ichimlik — 0,4 l Pepsi',
    ]);
    expect(local.lines.last.name, 'Paket');
    expect(local.total, const PosMoney.som(46000));
    // The server's lines: the combo line gets its parts, a split part line (another name) does not
    final List<ReceiptLine> lines = withComboParts(const <ReceiptLine>[
      ReceiptLine(
        name: 'Lavash kombo',
        quantity: ItemQuantity(PosQuantity.one),
        unitPrice: PosMoney.som(45000),
        amount: PosMoney.som(45000),
      ),
      ReceiptLine(
        name: 'Paket',
        quantity: ItemQuantity(PosQuantity.one),
        unitPrice: PosMoney.som(1000),
        amount: PosMoney.som(1000),
      ),
    ], bill.items);
    expect(lines.first.parts, hasLength(2));
    expect(lines.last.parts, isEmpty);
    expect(PosOrderLineTile, isNotNull);
  });
}
