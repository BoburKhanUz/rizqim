import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_cashier/src/shell/approval_dialog.dart';

import 'support/harness.dart';
import 'support/p22.dart';

/// Money requests whose answer is lost (review P3-35: F5, F6): the retry is the very same request — key, body and the
/// manager's approval — so the server does it once.
void main() {
  Map<String, Object?> bodyOf(RequestOptions o) => o.data is Map
      ? (o.data! as Map<Object?, Object?>).cast<String, Object?>()
      : (jsonDecode(o.data! as String) as Map<Object?, Object?>)
            .cast<String, Object?>();

  /// The first request to [path] that carries a manager approval reaches the server, its answer does not.
  void loseApproved(P22 p, String path) {
    var lose = true;
    p.h.adapter.respond = (RequestOptions o) {
      if (!lose || o.method != 'POST' || !o.uri.path.endsWith(path)) {
        return null;
      }
      if (bodyOf(o)['approval_token'] == null) return null;
      lose = false;
      p.state.handle(
        o.method,
        o.uri.path,
        o.uri.queryParameters,
        o.data,
        (o.headers['Authorization'] as String?)?.replaceFirst('Bearer ', ''),
        idempotencyKey: o.headers['Idempotency-Key'] as String?,
      );
      throw DioException.connectionError(requestOptions: o, reason: 'lost');
    };
  }

  Future<PosBill> billOf(WidgetTester tester, P22 p, String uuid) async {
    final PosOrder order = await settle(tester, p.h.data.orders.get(uuid));
    return (await settle(
      tester,
      p.h.data.billing.createBills(uuid, version: order.version),
    )).bills.single;
  }

  testWidgets(
    'an approved nasiya payment whose answer was lost goes again with the same approval: one payment, no 409',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String account = p.backend.seedAccount(
        name: 'Jamshid aka',
        limit: '2000000.00',
      );
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      final PosBill bill = await billOf(tester, p, uuid);
      loseApproved(p, '/payments');
      final BuildContext context = tester.element(find.byType(NavigationRail));
      final LostRequest lost = LostRequest();
      Future<PosBillResult> pay() => lost.run<PosBillResult>(
        context,
        'nasiya ${bill.total.toApiString()}',
        (String? token, String? key) => p.h.data.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[
            PosTenderInput.account(bill.total, accountUuid: account),
          ],
          approvalToken: token,
          idempotencyKey: key,
        ),
      );

      Object? first;
      unawaited(pay().then((_) {}, onError: (Object e) => first = e));
      await tester.pumpAndSettle();
      expect(find.text("Menejer tasdig'i"), findsOneWidget);
      await typePin(tester, '5926');
      await tester.pumpAndSettle();
      expect(first, isA<PosNetworkException>());
      expect(lost.pending, isTrue);
      final List<Object?> paid =
          p.backend.bills[uuid]!.single['payments']! as List<Object?>;
      expect(paid, hasLength(1), reason: 'the server took it');

      // Tapped again: no new PIN, the same approved request; the server answers it from its record
      final PosBillResult again = await settle(tester, pay());
      expect(find.text("Menejer tasdig'i"), findsNothing);
      expect(again.bill.dueAmount, PosMoney.zero);
      expect(paid, hasLength(1));
      expect(p.state.replayed, 1);
      expect(lost.pending, isFalse);
    },
  );

  testWidgets(
    'a refund whose answer was lost is not refunded twice when the cashier taps again',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      final String uuid = p.state.seedOrder(tableId: 1, guests: 2);
      await pumpHome(tester, p.h);
      final PosBill bill = await billOf(tester, p, uuid);
      await settle(
        tester,
        p.h.data.billing.pay(
          bill.uuid,
          tenders: <PosTenderInput>[PosTenderInput.cash(bill.total)],
        ),
      );
      loseApproved(p, '/refunds');

      await openSection(tester, 'Navbat');
      await tester.tap(find.byKey(ValueKey<String>('finished-$uuid')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(ValueKey<String>('refund-${bill.uuid}')));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.widgetWithText(TextField, 'Qaytariladigan summa'),
        '20000',
      );
      await tester.tap(find.text('Taom sifatsiz'));
      await tester.pumpAndSettle();
      await tapButton(tester, 'Qaytarish');
      await typePin(tester, '5926');
      await tester.pumpAndSettle();
      expect(p.backend.refunds, hasLength(1));
      expect(
        find.textContaining("Aloqa yo'q: qaytarish holati"),
        findsOneWidget,
      );

      await tapButton(tester, 'Qaytarish');
      expect(find.text("Menejer tasdig'i"), findsNothing);
      expect(p.backend.refunds, hasLength(1), reason: 'refunded once');
      expect(p.state.replayed, 1);
      expect(
        find.widgetWithText(AlertDialog, 'Pulni qaytarish'),
        findsNothing,
        reason: 'the dialog closed',
      );
    },
  );

  testWidgets(
    'a cash out whose answer was lost is written once when the cashier taps again',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.state.openShift();
      // P3-37: cashiers hold pos.cash_in_out by default; the owner took it away here, so a manager approves it
      p.state.rolePermissions['cashier']!.remove('pos.cash_in_out');
      await pumpHome(tester, p.h);
      await openSection(tester, 'Smena');
      loseApproved(p, '/cash-movements');
      await tapButton(tester, 'Kirim');
      await enter(tester, 'movement-amount', '30000');
      await tapKey(tester, 'movement-submit');
      await typePin(tester, '5926');
      await tester.pumpAndSettle();
      expect(p.backend.movements, hasLength(1));
      expect(find.textContaining("Aloqa yo'q: yozuv holati"), findsOneWidget);

      await tapKey(tester, 'movement-submit');
      expect(find.text("Menejer tasdig'i"), findsNothing);
      expect(p.backend.movements, hasLength(1), reason: 'written once');
      expect(p.state.replayed, 1);
    },
  );
}
