import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/banquets/banquet_event_page.dart';
import 'package:rizqim_cashier/src/features/banquets/banquet_format.dart';
import 'package:rizqim_cashier/src/features/billing/billing_page.dart';
import 'package:rizqim_cashier/src/features/billing/cashier_ui.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';
import 'package:rizqim_cashier/src/features/order/order_page.dart';

import 'support/banquets.dart';
import 'support/harness.dart';
import 'support/p22.dart';

/// P6-51…P6-54 (to'lqin 5, B): kassadagi "Tadbirlar" — taqvim (kun, zal, holat, muddati o'tgan), tadbir kartasi,
/// zalni band qilish va o'zgartirish, jadval qatorini olish (qisman, naqd/karta, bo'nak cheki), tadbirni boshlash
/// (oddiy buyurtma ekrani, sarlavhada tadbir nomi), kelgan mehmonlar, yakuniy hisob (oddiy to'lov sahifasi, bitta
/// sotuv cheki), ortiqcha zaklad (menejer PIN'i), bekor qilish (qoida, ushlab qolish, qaytarish cheklari), chop
/// etish, X/Z "Tadbirlar" qismi, internetsiz oddiy xabar.
void main() {
  // Now: 2026-10-22 13:00 UTC (Tashkent 18:00). 200 × 150 000 + evening hall 10 000 000 = 40 000 000;
  // the schedule: deposit 12 000 000 today, interim 16 000 000 14 days before, the rest on the day.

  group('the calendar', () {
    testWidgets('only with the banquet module', (WidgetTester tester) async {
      final BanquetTill t = BanquetTill(module: false);
      await pumpHome(tester, t.p.h);
      expect(
        find.descendant(
          of: find.byType(NavigationRail),
          matching: find.text('Tadbirlar'),
        ),
        findsNothing,
      );
    });

    testWidgets(
      'by day with the hall, time, status and overdue badges; the hall filter; search by phone',
      (WidgetTester tester) async {
        final BanquetTill t = BanquetTill();
        final String a = t.b.seedEvent(date: '2026-10-25');
        final String b = t.b.seedEvent(
          date: '2026-10-30',
          hallIds: <int>[6],
          title: "Aziz va Malika to'yi",
          customer: 'Rahimovlar',
          phone: '+998935554433',
          status: 'confirmed',
        );
        t.b.makeOverdue(b);
        await pumpBanquets(tester, t);

        expect(find.byKey(ValueKey<String>('banquet-$a')), findsOneWidget);
        expect(find.byKey(ValueKey<String>('banquet-$b')), findsOneWidget);
        expect(find.text('Karimovlar'), findsOneWidget);
        expect(find.text("Aziz va Malika to'yi"), findsOneWidget);
        expect(find.text('Vaqtincha band'), findsOneWidget);
        expect(find.text('Tasdiqlangan'), findsOneWidget);
        expect(
          find.byKey(const ValueKey<String>('banquet-overdue')),
          findsOneWidget,
        );
        expect(
          find.textContaining(
            "Kechqurun 18:00–23:00 · Oq zal · To'y · 200 kishi",
          ),
          findsOneWidget,
        );
        expect(find.text('Yakshanba, 25-oktabr'), findsOneWidget);
        expect(find.text(uzs(40000000)), findsOneWidget);
        // Ko'k zal in the evening: 6 000 000
        expect(find.text(uzs(36000000)), findsOneWidget);
        // The cashier cannot hold a hall
        expect(
          find.byKey(const ValueKey<String>('banquets-new')),
          findsNothing,
        );

        await tapKey(tester, 'banquets-hall-6');
        expect(find.text('Karimovlar'), findsNothing);
        expect(find.text("Aziz va Malika to'yi"), findsOneWidget);

        await enter(tester, 'banquets-phone', '5554433');
        await tester.testTextInput.receiveAction(TextInputAction.search);
        await tester.pumpAndSettle();
        expect(find.text('Qidiruv: 1 ta'), findsOneWidget);
      },
    );
  });

  testWidgets(
    'the event card: customer, halls, guests, lines, totals, the warning and the schedule',
    (WidgetTester tester) async {
      final BanquetTill t = BanquetTill();
      final String a = t.b.seedEvent(
        date: '2026-11-15',
        guests: 300,
        extraIds: <(int, int)>[(1, 1)],
      );
      await pumpBanquets(tester, t);
      await openEvent(tester, a);

      expect(find.byType(BanquetEventPage), findsOneWidget);
      expect(find.text('Karimovlar'), findsWidgets);
      expect(find.text('+998901234567'), findsOneWidget);
      expect(
        find.byKey(
          const ValueKey<String>('banquet-warning-guests_over_threshold'),
        ),
        findsOneWidget,
      );
      expect(find.text('Standart · ${uzs(150000)} × 300'), findsOneWidget);
      expect(find.text("Zal ijarasi: Oq zal"), findsOneWidget);
      expect(find.text("To'y torti"), findsOneWidget);
      // 300 × 150 000 + 10 000 000 + 1 500 000
      expect(
        tester
            .widget<AmountRow>(
              find.byKey(const ValueKey<String>('banquet-event-total')),
            )
            .amount,
        const PosMoney.som(56500000),
      );
      expect(find.text('Zaklad'), findsOneWidget);
      expect(find.text("Oraliq to'lov"), findsOneWidget);
      expect(find.text("Yakuniy to'lov"), findsOneWidget);
      expect(find.text('Kutilmoqda'), findsNWidgets(3));
      // The cashier pays rows but does not edit, cancel or set the guests
      expect(find.byKey(const ValueKey<String>('banquet-edit')), findsNothing);
      expect(
        find.byKey(const ValueKey<String>('banquet-cancel')),
        findsNothing,
      );
      expect(find.text(banquetsA4Note), findsOneWidget);
    },
  );

  testWidgets(
    'a scheduled payment: a part in cash (change, drawer, advance receipt), the rest by card → confirmed; '
    'the X report has the banquet section and the cash line',
    (WidgetTester tester) async {
      final BanquetTill t = BanquetTill();
      final String a = t.b.seedEvent(date: '2026-12-20');
      await pumpBanquets(tester, t);
      await openEvent(tester, a);

      final String deposit = '${t.rows(a).first['uuid']}';
      await tapKey(tester, 'banquet-pay-$deposit');
      expect(
        find.byKey(const ValueKey<String>('banquet-pay-dialog')),
        findsOneWidget,
      );
      // More than the row is not taken
      await enter(tester, 'tender-amount', '13000000');
      await tapKey(tester, 'banquet-pay-submit');
      expect(find.textContaining("qatordan ko'p"), findsOneWidget);

      await enter(tester, 'tender-amount', '5000000');
      await enter(tester, 'tender-given', '5100000');
      await tapKey(tester, 'banquet-pay-submit');
      expect(find.textContaining('Qaytim: ${uzs(100000)}'), findsOneWidget);
      expect(t.receipts.last, contains("BO'NAK CHEKI"));
      expect(t.receipts.last, contains('Tadbir zakladi (avans)'));
      expect(t.p.drawerKicks, 1);
      expect(t.rows(a), hasLength(4));
      expect(t.rows(a)[0]['status'], 'paid');
      expect(t.rows(a)[1]['amount'], 700000000);
      expect(find.text('Vaqtincha band'), findsOneWidget);

      await tapKey(tester, 'banquet-pay-${t.rows(a)[1]['uuid']}');
      await tester.tap(find.widgetWithText(ChoiceChip, 'Karta').first);
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(ChoiceChip, 'Humo').first);
      await tester.pumpAndSettle();
      await enter(tester, 'tender-txn', 'RRN-77');
      await tapKey(tester, 'banquet-pay-submit');
      expect(t.receipts.last, contains("BO'NAK CHEKI"));
      expect(t.p.drawerKicks, 1);
      expect(find.text('Tasdiqlangan'), findsOneWidget);
      expect(t.b.events[a]!['status'], 'confirmed');
      // Reprint: a copy
      await tapKey(tester, 'banquet-reprint-$deposit');
      expect(t.receipts.last, contains('NUSXA'));

      await tester.pageBack();
      await tester.pumpAndSettle();
      await openSection(tester, 'Smena');
      await tapButton(tester, 'X hisobot');
      expect(find.text('Tadbirlar'), findsWidgets);
      expect(find.text("Olindi (bo'nak) (2)"), findsOneWidget);
      expect(
        tester
            .widget<AmountRow>(
              find.byKey(const ValueKey<String>('x-cash-banquets')),
            )
            .amount,
        const PosMoney.som(5000000),
      );
      await tapButton(tester, 'Chop etish');
      expect(t.receipts.last, contains('TADBIRLAR'));
      expect(t.receipts.last, contains('Tadbir avansi (naqd)'));
    },
  );

  testWidgets('no shift: a plain message, nothing is sent', (
    WidgetTester tester,
  ) async {
    final BanquetTill t = BanquetTill(shift: false);
    final String a = t.b.seedEvent(date: '2026-12-20');
    await pumpBanquets(tester, t);
    await openEvent(tester, a);
    await tapKey(tester, 'banquet-pay-${t.rows(a).first['uuid']}');
    expect(find.textContaining('Smena ochilmagan'), findsOneWidget);
    expect(
      find.byKey(const ValueKey<String>('banquet-pay-dialog')),
      findsNothing,
    );
    expect(t.rows(a).first['status'], 'due');
  });

  testWidgets(
    'a manager holds a hall (form → hold → the card), edits the guests; the same hall and time again is refused',
    (WidgetTester tester) async {
      final BanquetTill t = BanquetTill();
      await pumpBanquets(tester, t, name: 'Malika Rahimova');
      await tapKey(tester, 'banquets-new');
      await tapKey(tester, 'banquet-hall-5');
      await enter(tester, 'banquet-form-title', "Sardor to'yi");
      await enter(tester, 'banquet-form-name', 'Sardor Aliyev');
      await enter(tester, 'banquet-form-phone', '90 111 22 33');
      await enter(tester, 'banquet-form-guests', '180');
      await tapKey(tester, 'banquet-extra-add-1');
      await tapKey(tester, 'banquet-form-save');

      expect(find.byType(BanquetEventPage), findsOneWidget);
      expect(find.text("Sardor to'yi"), findsWidgets);
      expect(find.text('Vaqtincha band'), findsOneWidget);
      final Map<String, Object?> e = t.b.events.values.single;
      expect(e['customer_phone'], '+998901112233');
      expect(e['hall_ids'], <int>[5]);
      expect(e['event_date'], '2026-10-22');
      // 180 × 150 000 + 10 000 000 + cake 1 500 000
      expect(
        tester
            .widget<AmountRow>(
              find.byKey(const ValueKey<String>('banquet-event-total')),
            )
            .amount,
        const PosMoney.som(38500000),
      );

      await tapKey(tester, 'banquet-edit');
      await enter(tester, 'banquet-form-guests', '220');
      await tapKey(tester, 'banquet-form-save');
      expect(find.text('Tadbir saqlandi.'), findsOneWidget);
      expect(e['guests_guaranteed'], 220);
      expect(
        tester
            .widget<AmountRow>(
              find.byKey(const ValueKey<String>('banquet-event-total')),
            )
            .amount,
        const PosMoney.som(44500000),
      );

      await tester.pageBack();
      await tester.pumpAndSettle();
      await tapKey(tester, 'banquets-new');
      await tapKey(tester, 'banquet-hall-5');
      await enter(tester, 'banquet-form-name', 'Boshqa mijoz');
      await enter(tester, 'banquet-form-phone', '901112234');
      await enter(tester, 'banquet-form-guests', '100');
      await tapKey(tester, 'banquet-form-save');
      expect(find.textContaining('Zal shu vaqtda band'), findsOneWidget);
      expect(t.b.events, hasLength(1));
    },
  );

  testWidgets(
    'the event day: start opens the normal order screen titled with the event; guests actual 185 billed at the '
    'guaranteed 200; the remainder on the payment page → one sale receipt; settled',
    (WidgetTester tester) async {
      final BanquetTill t = BanquetTill();
      final String a = t.b.seedEvent(
        date: '2026-10-22',
        title: "Aziz va Malika to'yi",
        status: 'confirmed',
      );
      await pumpBanquets(tester, t, name: 'Malika Rahimova');
      await openEvent(tester, a);

      await tapKey(tester, 'banquet-start');
      expect(find.byType(OrderPage), findsOneWidget);
      expect(find.text("Tadbir: Aziz va Malika to'yi"), findsOneWidget);
      expect(t.b.events[a]!['status'], 'in_progress');
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(find.text("O'tkazilmoqda"), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('banquet-open-order')),
        findsOneWidget,
      );

      await tapKey(tester, 'banquet-guests');
      await enter(tester, 'banquet-guests-field', '185');
      await tapKey(tester, 'banquet-guests-save');
      expect(t.b.events[a]!['guests_actual'], 185);

      await tapKey(tester, 'banquet-settlement');
      expect(find.text('Hisobda: 200 kishi'), findsOneWidget);
      expect(find.textContaining('Kelgan: 185'), findsOneWidget);
      expect(
        tester
            .widget<AmountRow>(
              find.byKey(const ValueKey<String>('banquet-settlement-due')),
            )
            .amount,
        const PosMoney.som(28000000),
      );
      await tapKey(tester, 'banquet-settle');
      expect(find.byType(PaymentPage), findsOneWidget);
      await tapKey(tester, 'payment-submit');
      expect(find.byType(PaymentPage), findsNothing);
      expect(t.receipts.last, contains('SOTUV CHEKI'));
      expect(t.receipts.last, contains('Banket paketi: Standart'));
      expect(
        find.byKey(const ValueKey<String>('banquet-settled')),
        findsOneWidget,
      );
      expect(t.b.events[a]!['status'], 'settled');
    },
  );

  testWidgets(
    'advances above the total: the bill is covered by them; the cashier refunds the excess with the manager PIN',
    (WidgetTester tester) async {
      final BanquetTill t = BanquetTill();
      final String a = t.b.seedEvent(date: '2026-10-22', status: 'confirmed');
      await pumpHome(tester, t.p.h);
      for (final Map<String, Object?> r in t.rows(a).skip(1).toList()) {
        await settle(
          tester,
          t.p.h.data.banquetPayments.pay(
            a,
            '${r['uuid']}',
            const PosBanquetPayInput(tender: PosTender.cash),
          ),
        );
      }
      // 150 guests after all: 150 × 150 000 + 10 000 000 = 32 500 000 of the 40 000 000 paid
      t.b.events[a]!
        ..['guests_guaranteed'] = 150
        ..['guests_minimum'] = 150;
      await openBanquets(tester);
      await openEvent(tester, a);
      await tapKey(tester, 'banquet-settlement');
      expect(
        tester
            .widget<AmountRow>(
              find.byKey(const ValueKey<String>('banquet-settlement-excess')),
            )
            .amount,
        const PosMoney.som(7500000),
      );
      await tapKey(tester, 'banquet-settle');
      expect(find.byType(BillingPage), findsOneWidget);
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(t.b.events[a]!['status'], 'settled');

      await tapKey(tester, 'banquet-refund-excess');
      await tapButton(tester, 'Qaytarish');
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      await typePin(tester, '5926');
      expect(find.text('Ortiqcha zaklad qaytarildi.'), findsOneWidget);
      expect(t.receipts.last, contains('QAYTARISH CHEKI'));
      expect(t.receipts.last, contains('7 500 000'));
      expect(t.b.settlements[a]!['excess_status'], 'refunded');
    },
  );

  group('cancellation at the till', () {
    testWidgets(
      '20 days before: the rule, 30 % retained, the cash back from the drawer; the refund and the retained receipts',
      (WidgetTester tester) async {
        final BanquetTill t = BanquetTill();
        final String a = t.b.seedEvent(date: '2026-11-11', status: 'confirmed');
        await pumpBanquets(tester, t, name: 'Malika Rahimova');
        await openEvent(tester, a);
        await tapKey(tester, 'banquet-cancel');
        expect(
          find.textContaining(
            "Tadbirgacha 20 kun: qoida bo'yicha to'langanning 30%",
          ),
          findsOneWidget,
        );
        expect(
          tester
              .widget<AmountRow>(
                find.byKey(const ValueKey<String>('banquet-cancel-retained')),
              )
              .amount,
          const PosMoney.som(3600000),
        );
        expect(
          tester
              .widget<AmountRow>(
                find.byKey(const ValueKey<String>('banquet-cancel-refund')),
              )
              .amount,
          const PosMoney.som(8400000),
        );
        expect(find.textContaining('naqd, shu kassadan'), findsOneWidget);
        // A reason first
        await tapKey(tester, 'banquet-cancel-submit');
        expect(find.text('Bekor qilish sababini yozing.'), findsOneWidget);
        await tapText(tester, 'Mijoz voz kechdi');
        await tapKey(tester, 'banquet-cancel-submit');

        expect(find.text('Bekor qilingan'), findsWidgets);
        expect(t.b.events[a]!['status'], 'cancelled');
        final List<String> printed = t.receipts.sublist(t.receipts.length - 2);
        expect(printed[0], contains('QAYTARISH CHEKI'));
        expect(printed[0], contains('8 400 000'));
        expect(printed[1], contains('SOTUV CHEKI'));
        expect(printed[1], contains('Bekor qilish haqi'));
        expect(t.p.drawerKicks, 1);
        expect(
          find.byKey(const ValueKey<String>('banquet-cancel')),
          findsNothing,
        );
      },
    );

    testWidgets(
      "the manager's own retained amount needs a reason; nothing retained → only the refund",
      (WidgetTester tester) async {
        final BanquetTill t = BanquetTill();
        final String a = t.b.seedEvent(date: '2026-10-30', status: 'confirmed');
        await pumpBanquets(tester, t, name: 'Malika Rahimova');
        await openEvent(tester, a);
        await tapKey(tester, 'banquet-cancel');
        // < 14 days: 100 %
        expect(find.textContaining('100%'), findsOneWidget);
        await tapKey(tester, 'banquet-cancel-own');
        await enter(tester, 'banquet-cancel-amount', '0');
        expect(
          tester
              .widget<AmountRow>(
                find.byKey(const ValueKey<String>('banquet-cancel-refund')),
              )
              .amount,
          const PosMoney.som(12000000),
        );
        await enter(tester, 'banquet-cancel-reason', 'Mijoz kasal');
        await tapKey(tester, 'banquet-cancel-submit');
        expect(find.textContaining("o'zgartirish sababini"), findsOneWidget);
        await enter(tester, 'banquet-cancel-override', 'Doimiy mijoz');
        await tapKey(tester, 'banquet-cancel-submit');
        expect(t.b.events[a]!['status'], 'cancelled');
        expect(t.b.cancellations[a]!['override_reason'], 'Doimiy mijoz');
        expect(t.receipts.last, contains('QAYTARISH CHEKI'));
        expect(
          t.receipts.where((String r) => r.contains('Bekor qilish haqi')),
          isEmpty,
        );
      },
    );
  });

  testWidgets(
    'printing: the production sheet and the contract on the receipt printer; the shopping list from the calendar',
    (WidgetTester tester) async {
      final BanquetTill t = BanquetTill();
      final String a = t.b.seedEvent(date: '2026-10-28', status: 'confirmed');
      await pumpBanquets(tester, t, name: 'Malika Rahimova');
      await tapButton(tester, "Bozorlik ro'yxati");
      expect(t.receipts.last, contains("BOZORLIK RO'YXATI"));
      await openEvent(tester, a);
      await tapKey(tester, 'banquet-print-sheet');
      expect(t.receipts.last, contains("ISHLAB CHIQARISH VARAG'I"));
      expect(t.receipts.last, isNot(contains('Karimovlar')));
      await tapKey(tester, 'banquet-print-contract');
      expect(t.receipts.last, contains('SHARTNOMA'));
      expect(t.receipts.last, contains('Karimovlar'));
    },
  );

  testWidgets('offline: the section says so in plain words', (
    WidgetTester tester,
  ) async {
    final SyncStore store = await SyncStore.open(MemorySyncStorage());
    final BanquetTill t = BanquetTill(offlineStore: store);
    FakeSyncBackend(t.p.state);
    t.b.seedEvent(date: '2026-10-25');
    await pumpHome(tester, t.p.h);
    t.p.h.adapter.offline = true;
    await drive(tester, t.p.h.data.floor.load());
    await openBanquets(tester);
    expect(find.text(banquetsOfflineText), findsOneWidget);
    expect(
      find.byKey(const ValueKey<String>('banquets-error')),
      findsOneWidget,
    );
    // Back online: no offline timer left behind
    t.p.h.adapter.offline = false;
    await drive(tester, t.p.h.data.offline!.syncNow());
    await tester.pump(const Duration(seconds: 31));
    await tester.pumpAndSettle();
    expect(t.p.h.data.offline!.active, isFalse);
  });
}

/// Runs [future] to the end, moving the fake clock (Dio waits on timers); its error is ignored.
Future<void> drive(WidgetTester tester, Future<Object?> future) async {
  var done = false;
  future.then((_) => done = true, onError: (Object _) => done = true).ignore();
  for (var i = 0; i < 300 && !done; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
  await tester.pumpAndSettle();
}
