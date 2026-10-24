import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart' show FakeResponse;
import 'package:rizqim_cashier/src/features/billing/billing_page.dart';
import 'package:rizqim_cashier/src/features/order/frequent_items.dart';
import 'package:rizqim_cashier/src/features/order/kitchen_tickets.dart';
import 'package:rizqim_cashier/src/features/order/order_controller.dart';
import 'package:rizqim_cashier/src/features/order/order_format.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

/// P3-37: the discount at the till (the cashier alone up to the branch limit, a manager PIN above it, removal, no
/// discount offline, the discount on the pre-check and the receipt), the cashier's new default permissions (reprint,
/// cash in/out without a manager; a big cash-out is told to the owner) and cancelling a closed, fully refunded order.
/// The order editor with the discount line (test fonts are wide: a taller screen keeps the totals from wrapping).
const Size tall = Size(1280, 1100);

void main() {
  /// 1-xona: 1.5 kg osh (180 000) + 2 choy (10 000) = 190 000 dishes.
  Map<String, Object?> order(P22 p, String uuid) => p.state.orders[uuid]!;

  Future<void> giveDiscount(
    WidgetTester tester, {
    int? quick,
    String? custom,
    String reason = 'Doimiy mijoz',
  }) async {
    if (quick != null) {
      await tapKey(tester, 'discount-quick-$quick');
    }
    if (custom != null) await enter(tester, 'discount-value', custom);
    await tapOn(tester, find.widgetWithText(ChoiceChip, reason));
    await tapKey(tester, 'discount-submit');
  }

  testWidgets(
    'within the limit: the cashier gives 10% alone; the totals show it with its reason',
    (WidgetTester tester) async {
      final P22 p = P22();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h, size: tall);
      await openPlace(tester, '1-xona');
      expect(find.byType(OrderPage), findsOneWidget);

      await tapKey(tester, 'order-discount');
      expect(
        find.text(
          "Siz 10% gacha o'zingiz bera olasiz. Ko'prog'i — menejer PIN'i bilan.",
        ),
        findsOneWidget,
      );
      // The submit waits for a value and a reason
      expect(
        tester
            .widget<RzButton>(
              find.byKey(const ValueKey<String>('discount-submit')),
            )
            .onPressed,
        isNull,
      );
      await tapKey(tester, 'discount-quick-10');
      expect(find.textContaining('Chegirma: ${som(19000)}'), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('discount-above-limit')),
        findsNothing,
      );
      await tapOn(tester, find.widgetWithText(ChoiceChip, 'Doimiy mijoz'));
      await tapKey(tester, 'discount-submit');

      expect(find.text("Menejer tasdig'i"), findsNothing);
      expect(order(p, uuid)['discount_kind'], 'percent');
      expect(order(p, uuid)['discount_value'], '10.00');
      expect(order(p, uuid)['discount_reason'], 'Doimiy mijoz');
      expect(order(p, uuid)['discount'], '19000.00');
      expect(find.text('Chegirma 10%'), findsOneWidget);
      expect(find.text('Doimiy mijoz'), findsOneWidget);
      expect(find.text('Chegirma berildi'), findsOneWidget);
    },
  );

  testWidgets(
    'above the limit the manager types the PIN; an amount above it too; then the discount is removed',
    (WidgetTester tester) async {
      final P22 p = P22();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h, size: tall);
      await openPlace(tester, '1-xona');

      await tapKey(tester, 'order-discount');
      await tapKey(tester, 'discount-quick-15');
      expect(
        find.byKey(const ValueKey<String>('discount-above-limit')),
        findsOneWidget,
      );
      await tapOn(tester, find.widgetWithText(ChoiceChip, 'Ega ruxsati'));
      await tapKey(tester, 'discount-submit');
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      expect(
        find.text(PosPermissions.label(PosPermissions.ordersDiscount)),
        findsOneWidget,
      );
      await typePin(tester, '5926');
      await tester.pumpAndSettle();
      expect(order(p, uuid)['discount_value'], '15.00');
      expect(order(p, uuid)['discount'], '28500.00');
      expect(find.text('Chegirma 15%'), findsOneWidget);

      // An amount: 30 000 of 190 000 is above 10% — the PIN again (the dialog starts from the current discount)
      await tapKey(tester, 'order-discount');
      await tapText(tester, "Summa (so'm)");
      await enter(tester, 'discount-value', '30000');
      expect(
        find.byKey(const ValueKey<String>('discount-above-limit')),
        findsOneWidget,
      );
      await tapOn(
        tester,
        find.widgetWithText(ChoiceChip, 'Kechikish uchun uzr'),
      );
      await tapKey(tester, 'discount-submit');
      await typePin(tester, '5926');
      await tester.pumpAndSettle();
      expect(order(p, uuid)['discount_kind'], 'amount');
      expect(order(p, uuid)['discount'], '30000.00');
      expect(find.text('Chegirma'), findsWidgets);

      // Removal: no reason, no PIN
      await tapText(tester, 'Olib tashlash');
      expect(find.text('Chegirma olib tashlansinmi?'), findsOneWidget);
      await tester.tap(buttonLabeled('Olib tashlash').last);
      await tester.pumpAndSettle();
      expect(find.text("Menejer tasdig'i"), findsNothing);
      expect(order(p, uuid)['discount_kind'], isNull);
      expect(order(p, uuid)['discount'], '0.00');
      expect(find.text('Chegirma olib tashlandi'), findsOneWidget);
    },
  );

  testWidgets(
    'no limit set by the owner: every discount asks a manager; a manager signed in gives any without a PIN',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.cashierDiscountMaxPercent = null;
      p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h, size: tall);
      await openPlace(tester, '1-xona');
      await tapKey(tester, 'order-discount');
      expect(
        find.text("Chegirma menejer PIN'i bilan beriladi."),
        findsOneWidget,
      );
      await giveDiscount(tester, quick: 5);
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
    },
  );

  testWidgets('offline: the discount is refused with the reason', (
    WidgetTester tester,
  ) async {
    final SyncStore store = await SyncStore.open(MemorySyncStorage());
    final P22 p = P22(offlineStore: store);
    p.state.openShift();
    final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
    await pumpHome(tester, p.h, size: tall);
    p.h.adapter.offline = true;
    p.h.data.floor.load().ignore();
    for (var i = 0; i < 50 && !p.h.data.offline!.active; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    await tester.pumpAndSettle();
    expect(p.h.data.offline!.active, isTrue);
    await openPlace(tester, '1-xona');
    final Finder button = find.byKey(const ValueKey<String>('order-discount'));
    expect(tester.widget<TextButton>(button).onPressed, isNull);
    expect(find.byTooltip('Chegirma uchun internet kerak.'), findsOneWidget);

    // The data layer says it too (and nothing is queued)
    await expectLater(
      p.h.data.orders.discount(
        uuid,
        version: 1,
        kind: PosDiscountKind.percent,
        value: '10',
        reason: 'Doimiy mijoz',
      ),
      throwsA(
        isA<PosOfflineUnavailable>().having(
          (PosOfflineUnavailable e) => e.message,
          'message',
          contains('Chegirma uchun internet kerak'),
        ),
      ),
    );
    expect(order(p, uuid)['discount_kind'], isNull);

    // Back online: the offline timers stop
    p.h.adapter.offline = false;
    p.h.data.offline!.syncNow().ignore();
    for (var i = 0; i < 300 && p.h.data.offline!.active; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    await tester.pumpAndSettle();
    expect(p.h.data.offline!.active, isFalse);
  });

  testWidgets(
    'on the bill screen: the discount re-makes the bill; the pre-check and the sale receipt show it',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      await openBilling(tester, uuid);
      await tapButton(tester, 'Schyot tuzish');
      await tapKey(tester, 'billing-discount');
      await giveDiscount(tester, quick: 10);
      expect(find.text("Menejer tasdig'i"), findsNothing);
      expect(order(p, uuid)['discount'], '19000.00');
      // The bill was made before the discount: it is made again
      expect(find.text('Schyot tuzish'), findsWidgets);
      expect(find.text('Chegirma 10%'), findsOneWidget);
      await tapButton(tester, 'Schyot tuzish');
      expect(find.byType(BillPreview), findsOneWidget);
      expect(find.text('Chegirma 10%'), findsOneWidget);

      await tapButton(tester, 'Schyotni chiqarish');
      final String check = p.receipts.single;
      expect(check, contains('SCHYOT'));
      expect(check, contains('Chegirma 10%:'));
      expect(check, contains('-19 000'));
      // 171 000 + service 17 100 + room 300 000
      expect(check, contains('488 100'));

      // A printed bill is locked: a discount now needs the manager (the server says so)
      await tapKey(tester, 'billing-discount');
      await giveDiscount(tester, quick: 5);
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      await tester.tap(buttonLabeled('Bekor qilish').last);
      await tester.pumpAndSettle();
      expect(order(p, uuid)['discount_value'], '10.00');

      await tapText(tester, "To'lash: ", contains: true);
      await tester.tap(find.widgetWithText(ChoiceChip, 'QR'));
      await tester.pumpAndSettle();
      await tapKey(tester, 'payment-submit');
      final String receipt = p.receipts.last;
      expect(receipt, contains('SOTUV CHEKI'));
      expect(receipt, contains('Berilgan chegirma 10%:'));
      expect(receipt, contains('19 000'));
    },
  );

  testWidgets(
    'a bill being paid: the discount buttons are gone and the server refusal is said in Uzbek',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      // Test fonts are wide: the part-paid pill needs room
      await pumpHome(tester, p.h, size: const Size(1600, 900));
      final PosOrder o = await settle(tester, p.h.data.orders.get(uuid));
      final PosBill bill = (await settle(
        tester,
        p.h.data.billing.createBills(uuid, version: o.version),
      )).bills.single;
      await settle(
        tester,
        p.h.data.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[
            const PosTenderInput.cash(PosMoney.som(10000)),
          ],
        ),
      );
      await openBilling(tester, uuid);
      expect(
        find.byKey(const ValueKey<String>('billing-discount')),
        findsNothing,
      );
      final PosOrder now = await settle(tester, p.h.data.orders.get(uuid));
      final Object? refused = await settle(
        tester,
        p.h.data.orders
            .discount(
              uuid,
              version: now.version,
              kind: PosDiscountKind.percent,
              value: '5',
              reason: 'Doimiy mijoz',
            )
            .then<Object?>((PosOrder _) => null, onError: (Object e) => e),
      );
      expect(
        refused,
        isA<PosConflict>().having(
          (PosConflict e) => e.reason,
          'reason',
          PosReasons.paidBillAffected,
        ),
      );
      expect(posErrorText(refused! as PosException), contains("to'langan"));
    },
  );

  testWidgets(
    'a discount whose answer was lost goes again with the same key and version: given once',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      final String uuid = h.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, h);
      final OrderController ctl = OrderController(
        data: h.data,
        tickets: KitchenTickets(
          printing: h.services.printing,
          bootstrap: () => h.data.session.bootstrap,
          now: () => h.now,
        ),
        frequent: FrequentItems.of(h.data),
        orderUuid: uuid,
      );
      addTearDown(ctl.dispose);
      await settle(tester, ctl.load());
      final List<OrderNotice> notices = <OrderNotice>[];
      ctl.notices.listen(notices.add);
      final List<String?> keys = <String?>[];
      var lose = true;
      h.adapter.respond = (RequestOptions o) {
        if (o.method != 'POST' || !o.uri.path.endsWith('/discount')) {
          return null;
        }
        keys.add(o.headers['Idempotency-Key'] as String?);
        if (!lose) return null;
        lose = false;
        h.state.handle(
          o.method,
          o.uri.path,
          o.uri.queryParameters,
          o.data,
          (o.headers['Authorization'] as String?)?.replaceFirst('Bearer ', ''),
          idempotencyKey: o.headers['Idempotency-Key'] as String?,
        );
        throw DioException.connectionError(requestOptions: o, reason: 'lost');
      };
      Future<T> direct<T>(Future<T> Function(String? token) action) =>
          action(null);

      final int before = ctl.order!.version;
      expect(
        await settle(
          tester,
          ctl.discount(
            PosDiscountKind.percent,
            '10',
            reason: 'Doimiy mijoz',
            approve: direct,
          ),
        ),
        isFalse,
      );
      expect(notices.last.error, isTrue);
      expect(
        h.state.orders[uuid]!['version'],
        before + 1,
        reason: 'the server did it',
      );
      // A poll meanwhile moves the screen to the new version; the retry still goes as the first one went
      await settle(tester, ctl.refresh());
      expect(
        await settle(
          tester,
          ctl.discount(
            PosDiscountKind.percent,
            '10',
            reason: 'Doimiy mijoz',
            approve: direct,
          ),
        ),
        isTrue,
      );
      expect(keys, hasLength(2));
      expect(keys.toSet(), hasLength(1), reason: 'the same key');
      expect(h.state.replayed, 1);
      expect(
        h.state.orders[uuid]!['version'],
        before + 1,
        reason: 'given once',
      );
      expect(ctl.order!.discount, const PosMoney.som(19000));
      expect(jsonEncode(h.state.orders[uuid]!['discount_value']), '"10.00"');
    },
  );

  testWidgets(
    'the server field errors (422) are shown as they come: amount above the dishes',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      final String uuid = h.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, h);
      final OrderController ctl = OrderController(
        data: h.data,
        tickets: KitchenTickets(
          printing: h.services.printing,
          bootstrap: () => h.data.session.bootstrap,
          now: () => h.now,
        ),
        frequent: FrequentItems.of(h.data),
        orderUuid: uuid,
      );
      addTearDown(ctl.dispose);
      await settle(tester, ctl.load());
      final List<OrderNotice> notices = <OrderNotice>[];
      ctl.notices.listen(notices.add);
      Future<T> direct<T>(Future<T> Function(String? token) action) =>
          action(null);
      expect(
        await settle(
          tester,
          ctl.discount(
            PosDiscountKind.amount,
            '999999',
            reason: 'Doimiy mijoz',
            approve: direct,
          ),
        ),
        isFalse,
      );
      await tester.pumpAndSettle();
      expect(notices.last.error, isTrue);
      expect(notices.last.message, 'Chegirma taomlar summasidan oshmaydi.');
    },
  );

  testWidgets(
    'a copy of a bill the order has outgrown: order_changed is said plainly',
    (WidgetTester tester) async {
      final P22 p = P22();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      await openBilling(tester, uuid);
      await tapButton(tester, 'Schyot tuzish');
      await tapButton(tester, 'Schyotni chiqarish');
      p.h.adapter.respond = (RequestOptions o) =>
          o.method == 'POST' && o.uri.path.endsWith('/print')
          ? const FakeResponse(409, <String, Object?>{
              'success': false,
              'message': "Schyotdan keyin buyurtma o'zgardi.",
              'data': <String, Object?>{'reason': 'order_changed'},
            })
          : null;
      await tapButton(tester, 'Nusxa chiqarish');
      expect(
        find.text("Buyurtma o'zgargan: schyotni qayta tuzing."),
        findsOneWidget,
      );
      expect(p.receipts, hasLength(1));
    },
  );

  group('P3-37 cashier defaults', () {
    testWidgets(
      'the cashier holds reprint and cash in/out: no PIN; a big cash-out says the owner is told',
      (WidgetTester tester) async {
        final P22 p = P22();
        p.state.openShift(openingCash: '1000000.00');
        final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
        await pumpHome(tester, p.h);
        expect(p.h.data.session.can(PosPermissions.reprintBill), isTrue);
        expect(p.h.data.session.can(PosPermissions.cashInOut), isTrue);
        expect(p.h.data.session.can(PosPermissions.refund), isFalse);
        expect(p.h.data.session.can(PosPermissions.ordersCancel), isFalse);

        await openBilling(tester, uuid);
        await tapButton(tester, 'Schyot tuzish');
        await tapButton(tester, 'Schyotni chiqarish');
        await tapButton(tester, 'Nusxa chiqarish');
        expect(find.text("Menejer tasdig'i"), findsNothing);
        expect(p.receipts, hasLength(2));
        expect(p.receipts.last, contains('NUSXA'));
        Navigator.of(tester.element(find.byType(BillingPage))).pop();
        await tester.pumpAndSettle();

        await openSection(tester, 'Smena');
        await tapKey(tester, 'cash-out');
        await tapText(tester, 'Boshqa chiqim');
        await enter(tester, 'movement-amount', '100000');
        expect(
          find.byKey(const ValueKey<String>('movement-owner-alert')),
          findsNothing,
        );
        await enter(tester, 'movement-amount', '600000');
        expect(find.text('Katta chiqim: egaga xabar boradi.'), findsOneWidget);
        await enter(tester, 'movement-note', 'Inkassatsiya');
        await tapKey(tester, 'movement-submit');
        expect(find.text("Menejer tasdig'i"), findsNothing);
        expect(p.backend.movements.single['amount'], '600000.00');

        await tapButton(tester, 'Kirim');
        await enter(tester, 'movement-amount', '50000');
        expect(
          find.byKey(const ValueKey<String>('movement-owner-alert')),
          findsNothing,
        );
        await tapKey(tester, 'movement-submit');
        expect(find.text("Menejer tasdig'i"), findsNothing);
        expect(p.backend.movements, hasLength(2));
      },
    );

    testWidgets(
      'an owner who takes reprint away: the copy asks the manager again (the UI follows the permissions)',
      (WidgetTester tester) async {
        final P22 p = P22();
        p.state.rolePermissions['cashier']!.remove(PosPermissions.reprintBill);
        final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
        await pumpHome(tester, p.h);
        await openBilling(tester, uuid);
        await tapButton(tester, 'Schyot tuzish');
        await tapButton(tester, 'Schyotni chiqarish');
        expect(find.textContaining("(menejer tasdig'i)"), findsOneWidget);
        await tapButton(tester, 'Nusxa chiqarish');
        expect(find.text("Menejer tasdig'i"), findsOneWidget);
        expect(find.text('Schyotni qayta chiqarish'), findsOneWidget);
        await typePin(tester, '5926');
        await tester.pumpAndSettle();
        expect(p.receipts.last, contains('NUSXA'));
      },
    );
  });

  testWidgets(
    'cancel after a full refund: a closed order opened from the queue is cancelled with the manager PIN',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      final PosOrder o = await settle(tester, p.h.data.orders.get(uuid));
      final PosBill bill = (await settle(
        tester,
        p.h.data.billing.createBills(uuid, version: o.version),
      )).bills.single;
      await settle(
        tester,
        p.h.data.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        ),
      );
      expect(order(p, uuid)['status'], 'delivered');

      await openSection(tester, 'Navbat');
      await tapKey(tester, 'finished-$uuid');
      expect(find.text('Buyurtma yopildi'), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('cancel-after-refund')),
        findsNothing,
        reason: 'paid: nothing to cancel',
      );
      await tapKey(tester, 'refund-${bill.uuid}');
      await tapText(tester, "Xato to'lov");
      await tapButton(tester, 'Qaytarish');
      await typePin(tester, '5926');
      await tester.pumpAndSettle();
      expect(p.backend.refunds.single['amount'], bill.total.toApiString());

      expect(find.textContaining("hamma to'lovi qaytarildi"), findsOneWidget);
      await tapKey(tester, 'cancel-after-refund');
      expect(
        find.textContaining('Yopilgan buyurtma bekor qilingan'),
        findsOneWidget,
      );
      await tapText(tester, "Xato to'lov");
      await tester.tap(buttonLabeled('Buyurtmani bekor qilish').last);
      await tester.pumpAndSettle();
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      expect(find.text('Buyurtmani bekor qilish'), findsWidgets);
      await typePin(tester, '5926');
      await tester.pumpAndSettle();

      expect(order(p, uuid)['status'], 'canceled');
      expect(order(p, uuid)['closed_at'], isNotNull);
      expect(find.text('Buyurtma bekor qilingan'), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('cancel-after-refund')),
        findsNothing,
      );
      // No kitchen "O'CHIRILDI" tickets for an order that was eaten and paid back
      expect(
        p.h
            .printerFor('server-2')
            .documents
            .where((d) => d.text.contains("O'CHIRILDI")),
        isEmpty,
      );

      // Back in the queue it reads as cancelled
      Navigator.of(tester.element(find.byType(BillingPage))).pop();
      await tester.pumpAndSettle();
      expect(find.text('Bekor qilingan'), findsOneWidget);
    },
  );
}
