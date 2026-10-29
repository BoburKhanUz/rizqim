import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/billing/payment_page.dart';

import 'support/counter.dart';
import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

/// Review P6-12 (C#L3): the counter never charges for a cart other than the one on screen after a failed open. An open
/// whose answer was lost locks the cart (the retry is the very same request, so the server answers with that order); an
/// open the server refused is forgotten: a changed cart is a new request with its own external id.
void main() {
  Map<String, Object?> bodyOf(RequestOptions o) => o.data is Map
      ? (o.data! as Map<Object?, Object?>).cast<String, Object?>()
      : (jsonDecode(o.data! as String) as Map<Object?, Object?>)
            .cast<String, Object?>();

  bool isOpen(RequestOptions o) =>
      o.method == 'POST' && o.uri.path.endsWith('/pos/orders');

  Future<P22> till() async {
    final P22 p = P22();
    p.state.format = formatBlock();
    seedCounterMenu(p.state);
    p.state.openShift();
    return p;
  }

  final Finder pay = find.byKey(const ValueKey<String>('counter-pay'));

  /// The error banner covers the pay button for a few seconds.
  Future<void> waitBanner(WidgetTester tester) async {
    await tester.pump(const Duration(seconds: 10));
    await tester.pumpAndSettle();
  }

  testWidgets(
    'a lost answer to the open locks the cart; the retry is the same request and the sale is the cart that was sent',
    (WidgetTester tester) async {
      final P22 p = await till();
      await pumpHome(tester, p.h);
      final List<String?> externalIds = <String?>[];
      final List<String?> keys = <String?>[];
      var lose = true;
      p.h.adapter.respond = (RequestOptions o) {
        if (!isOpen(o)) return null;
        externalIds.add(bodyOf(o)['external_id'] as String?);
        keys.add(o.headers['Idempotency-Key'] as String?);
        if (!lose) return null;
        lose = false;
        // The server opens the order, the answer is lost
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

      await tester.tap(menuTile(oshId));
      await tester.pumpAndSettle();
      await tester.tap(pay);
      await tester.pumpAndSettle();
      expect(find.byType(PaymentPage), findsNothing);
      expect(p.state.orders, hasLength(1));

      // The cart is locked: another dish does not join it, and the screen still shows the cart that was sent
      await tester.tap(menuTile(somsaId));
      await tester.pumpAndSettle();
      expect(find.text(som(35000)), findsWidgets);
      expect(find.text(som(43000)), findsNothing);

      // The same request again: same external id and key, the order that exists is the sale
      await waitBanner(tester);
      await tester.tap(pay);
      await tester.pumpAndSettle();
      expect(find.byType(PaymentPage), findsOneWidget);
      expect(externalIds, hasLength(2));
      expect(externalIds[1], externalIds[0]);
      expect(keys[1], keys[0]);
      expect(p.state.orders, hasLength(1));
      final List<Map<String, Object?>> lines = items(
        p.state.orders.values.single,
      );
      expect(lines, hasLength(1));
      expect(lines.single['product_name'], 'Osh porsiya');
    },
  );

  testWidgets(
    'giving up a cart whose open is unknown clears it and says the order may exist',
    (WidgetTester tester) async {
      final P22 p = await till();
      await pumpHome(tester, p.h);
      p.h.adapter.respond = (RequestOptions o) {
        if (!isOpen(o)) return null;
        throw DioException.connectionError(requestOptions: o, reason: 'down');
      };

      await tester.tap(menuTile(oshId));
      await tester.pumpAndSettle();
      await tester.tap(pay);
      await tester.pumpAndSettle();
      await tester.tap(menuTile(somsaId));
      await tester.pumpAndSettle();
      expect(find.text(som(43000)), findsNothing);

      await waitBanner(tester);
      await tester.tap(find.byKey(const ValueKey<String>('counter-clear')));
      await tester.pumpAndSettle();
      expect(find.text("Savat bo'sh"), findsOneWidget);
      // The next sale is editable again
      await tester.tap(menuTile(somsaId));
      await tester.pumpAndSettle();
      expect(find.text(som(8000)), findsWidgets);
    },
  );

  testWidgets(
    'an open the server refused is forgotten: the changed cart goes as a new request and is what gets charged',
    (WidgetTester tester) async {
      final P22 p = await till();
      await pumpHome(tester, p.h);
      final List<String?> externalIds = <String?>[];
      var refuse = true;
      p.h.adapter.respond = (RequestOptions o) {
        if (!isOpen(o)) return null;
        externalIds.add(bodyOf(o)['external_id'] as String?);
        if (!refuse) return null;
        refuse = false;
        return const FakeResponse(422, <String, Object?>{
          'success': false,
          'message': 'Osh tugadi.',
          'data': <String, Object?>{'reason': 'sold_out'},
        });
      };

      await tester.tap(menuTile(oshId));
      await tester.pumpAndSettle();
      await tester.tap(pay);
      await tester.pumpAndSettle();
      expect(find.byType(PaymentPage), findsNothing);
      expect(p.state.orders, isEmpty);

      // Nothing was opened, so the cashier may change the cart
      await tester.tap(menuTile(somsaId));
      await tester.pumpAndSettle();
      await waitBanner(tester);
      await tester.tap(pay);
      await tester.pumpAndSettle();
      expect(find.byType(PaymentPage), findsOneWidget);
      expect(externalIds, hasLength(2));
      expect(externalIds[1], isNot(externalIds[0]));
      final List<Map<String, Object?>> lines = items(
        p.state.orders.values.single,
      );
      expect(
        lines.map((Map<String, Object?> l) => l['product_name']).toSet(),
        <Object?>{'Osh porsiya', 'Somsa'},
      );
    },
  );
}
