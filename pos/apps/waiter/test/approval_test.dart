import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_waiter/src/features/order/order_page.dart';
import 'package:rizqim_waiter/src/shell/approval_dialog.dart';

import 'support/harness.dart';

/// Botir (ofitsiant) kirgan; 2-xonada Aziz Qodirovning buyurtmasi ochiq. "Hammasi" yorlig'idan ochiladi.
Future<String> openForeignOrder(WidgetTester tester, WaiterHarness h) async {
  addOtherWaiter(h);
  enforceWaiterRule(h);
  final String uuid = seedWaiterOrder(
    h,
    tableId: 2,
    waiterId: otherWaiterId,
    waiterName: otherWaiterName,
  );
  await pumpHome(tester, h);
  await tester.tap(find.bySemanticsLabel('Hammasi, 7 ta'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('2-xona'));
  await tester.pumpAndSettle();
  expect(find.byType(OrderPage), findsOneWidget);
  return uuid;
}

/// Yuborish tugmasi (yuklanish belgisi doim aylanadi: `pumpAndSettle` emas, aniq kadrlar).
Future<void> sendAndWaitForDialog(WidgetTester tester) async {
  await tester.tap(buttonLabeled('Yuborish').first);
  for (var i = 0; i < 4; i++) {
    await tester.pump(const Duration(milliseconds: 250));
  }
}

Future<void> addChoyAndSend(WidgetTester tester) async {
  await tapButton(tester, "Taom qo'shish");
  await tester.tap(find.text('+ Choy'));
  await tester.pump();
  await sendAndWaitForDialog(tester);
}

void main() {
  testWidgets("another waiter's order is read-only until editing is enabled", (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    await openForeignOrder(tester, h);

    expect(
      find.textContaining('Aziz Qodirov ofitsiantniki: faqat ko\'rish mumkin'),
      findsOneWidget,
    );
    // ko'rish mumkin, o'zgartirish tugmalari yo'q
    expect(find.text('Osh (Choyxona oshi)'), findsOneWidget);
    expect(buttonLabeled("Taom qo'shish"), findsNothing);
    expect(buttonLabeled("Schyot so'rash"), findsNothing);
    expect(find.byTooltip('Menyu'), findsNothing);

    await tapButton(tester, "Tahrirlash (menejer tasdig'i)");
    expect(find.textContaining('Tahrirlash yoqildi'), findsOneWidget);
    expect(buttonLabeled("Taom qo'shish"), findsOneWidget);
    // tasdiq amal paytida so'raladi, tugma bosilishi bilan emas
    expect(find.byType(ManagerApprovalDialog), findsNothing);
  });

  testWidgets('403 approval_required opens the manager dialog, then retries', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = await openForeignOrder(tester, h);
    await tapButton(tester, "Tahrirlash (menejer tasdig'i)");
    await addChoyAndSend(tester);

    expect(find.byType(ManagerApprovalDialog), findsOneWidget);
    expect(find.text("Buyurtmani o'zgartirish"), findsOneWidget);
    expect(
      find.textContaining('Bu buyurtma boshqa ofitsiantniki'),
      findsOneWidget,
    );
    await typePin(tester, '5926');

    // qayta yuborildi: tasdiq tokeni bilan va yangi Idempotency-Key bilan
    final List<Map<String, Object?>> rounds = bodies(
      h,
      'POST',
      '/v1/pos/orders/$uuid/rounds',
    );
    expect(rounds, hasLength(2));
    expect(rounds.first.containsKey('approval_token'), isFalse);
    expect(rounds.last['approval_token'], isNotNull);
    final List<String?> keys = h.adapter
        .to('POST', '/rounds')
        .map((r) => r.headers['Idempotency-Key'] as String?)
        .toList();
    expect(keys.first, isNot(keys.last));
    expect(find.text('Taom qidirish'), findsNothing);
    expect(find.text('2-bosqich'), findsOneWidget);
    // tasdiq faqat shu amal uchun: keyingisi yana so'raydi
    await tester.tap(find.text('4 mehmon'));
    await tester.pumpAndSettle();
    await tester.tap(find.bySemanticsLabel("Mehmonni ko'paytirish"));
    await tester.pump();
    await tapButton(tester, 'Saqlash');
    expect(find.byType(ManagerApprovalDialog), findsOneWidget);
  });

  testWidgets('a manager without orders.manage cannot approve', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    h.state.rolePermissions['manager'] = <String>[
      for (final String p in h.state.rolePermissions['manager']!)
        if (p != 'orders.manage') p,
    ];
    final String uuid = await openForeignOrder(tester, h);
    await tapButton(tester, "Tahrirlash (menejer tasdig'i)");
    await addChoyAndSend(tester);

    await typePinBriefly(tester, '5926');
    // oyna ochiq qoladi va aniq sabab aytiladi
    expect(find.byType(ManagerApprovalDialog), findsOneWidget);
    expect(
      find.text(
        "Bu xodim tasdiqlay olmaydi. Menejer yoki ega PIN'ini kiriting.",
      ),
      findsOneWidget,
    );

    // bekor qilindi: buyurtma o'zgarmadi, savat saqlanib turibdi
    await tapButton(tester, 'Bekor qilish');
    expect(find.byType(ManagerApprovalDialog), findsNothing);
    expect(bodies(h, 'POST', '/v1/pos/orders/$uuid/rounds'), hasLength(1));
    expect(find.text('Taom qidirish'), findsOneWidget);
    expect(find.text('Savat'), findsOneWidget);
  });

  testWidgets('cancelling the dialog leaves the order untouched, silently', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = await openForeignOrder(tester, h);
    await tapButton(tester, "Tahrirlash (menejer tasdig'i)");
    await addChoyAndSend(tester);
    await tapButton(tester, 'Bekor qilish');
    expect(find.byType(ManagerApprovalDialog), findsNothing);
    expect(find.byType(SnackBar), findsNothing);
    expect(bodies(h, 'POST', '/v1/pos/orders/$uuid/rounds'), hasLength(1));
  });

  testWidgets('own orders never ask for approval', (WidgetTester tester) async {
    final WaiterHarness h = WaiterHarness();
    addOtherWaiter(h);
    enforceWaiterRule(h);
    seedWaiterOrder(h, tableId: 1);
    await pumpHome(tester, h);
    await tester.tap(find.text('1-xona'));
    await tester.pumpAndSettle();
    await addChoyAndSend(tester);
    expect(find.byType(ManagerApprovalDialog), findsNothing);
    expect(find.text('2-bosqich'), findsOneWidget);
    expect(h.adapter.to('POST', '/v1/pos/approvals'), isEmpty);
  });

  testWidgets('the approval token goes with the retried request only', (
    WidgetTester tester,
  ) async {
    final WaiterHarness h = WaiterHarness();
    final String uuid = await openForeignOrder(tester, h);
    await tapButton(tester, "Tahrirlash (menejer tasdig'i)");
    await tester.tap(find.byTooltip('Menyu'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Mehmonlar soni'));
    await tester.pumpAndSettle();
    await tester.tap(find.bySemanticsLabel("Mehmonni ko'paytirish"));
    await tester.pump();
    await tapButton(tester, 'Saqlash');
    await typePin(tester, '5926');
    expect(find.text('5 mehmon'), findsOneWidget);
    final List<Map<String, Object?>> guests = bodies(
      h,
      'POST',
      '/v1/pos/orders/$uuid/guests',
    );
    expect(guests.first.containsKey('approval_token'), isFalse);
    expect(guests.last['approval_token'], isA<String>());
    expect(h.state.log, contains('POST /api/v1/pos/approvals'));
  });
}
