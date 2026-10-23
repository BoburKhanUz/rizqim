import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:rizqim_cashier/src/features/order/frequent_items.dart';
import 'package:rizqim_cashier/src/features/order/kitchen_tickets.dart';
import 'package:rizqim_cashier/src/features/order/order_controller.dart';

import 'support/harness.dart';

/// The order editor's rounds when the network or another device gets in the way (review P3-35: F4, F8).
void main() {
  Future<T> direct<T>(Future<T> Function(String? token) action) => action(null);

  Future<OrderController> editor(
    WidgetTester tester,
    CashierHarness h,
    String uuid,
  ) async {
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
    return ctl;
  }

  void addOsh(OrderController ctl, String kg) {
    final PosVariant v = ctl.catalog!.variantByUuid(
      FakePosState.variantUuid(3),
    )!;
    ctl.addLine(ctl.catalog!.product(v.productId)!, v, PosQuantity.parse(kg));
  }

  List<Object?> lines(CashierHarness h, String uuid) =>
      h.state.orders[uuid]!['items']! as List<Object?>;

  String? bearer(RequestOptions o) =>
      (o.headers['Authorization'] as String?)?.replaceFirst('Bearer ', '');

  testWidgets(
    'a round whose answer was lost goes again as the same request after a refresh: added once',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      final String uuid = h.state.seedOrder(tableId: 1, guests: 2);
      final OrderController ctl = await editor(tester, h, uuid);
      final int before = lines(h, uuid).length;

      // The server applies the round; the answer never comes back
      var lose = true;
      h.adapter.respond = (RequestOptions o) {
        if (lose && o.method == 'POST' && o.uri.path.endsWith('/rounds')) {
          lose = false;
          h.state.handle(
            o.method,
            o.uri.path,
            o.uri.queryParameters,
            o.data,
            bearer(o),
            idempotencyKey: o.headers['Idempotency-Key'] as String?,
          );
          throw DioException.connectionError(requestOptions: o, reason: 'lost');
        }
        return null;
      };
      addOsh(ctl, '1');
      expect(
        await settle(tester, ctl.submit(send: true, approve: direct)),
        isFalse,
      );
      expect(lines(h, uuid), hasLength(before + 1));
      expect(ctl.draft, hasLength(1), reason: 'the draft stays for the retry');

      // A poll brings the new version; the cashier taps "Yuborish" again
      await settle(tester, ctl.refresh());
      expect(
        await settle(tester, ctl.submit(send: true, approve: direct)),
        isTrue,
      );
      expect(lines(h, uuid), hasLength(before + 1), reason: 'applied once');
      expect(h.state.replayed, 1);
      expect(
        h.adapter
            .to('POST', '/rounds')
            .map((RequestOptions r) => r.headers['Idempotency-Key'])
            .toSet(),
        hasLength(1),
      );
    },
  );

  testWidgets(
    'after a version conflict only this till\'s lines get a kitchen ticket',
    (WidgetTester tester) async {
      final CashierHarness h = CashierHarness();
      final String uuid = h.state.seedOrder(tableId: 1, guests: 2);
      final OrderController ctl = await editor(tester, h, uuid);
      final int printedBefore = h.printerFor('server-2').documents.length;

      // A waiter's phone sends 2 kg osh just before this till's round reaches the server
      var other = true;
      h.adapter.respond = (RequestOptions o) {
        if (other && o.method == 'POST' && o.uri.path.endsWith('/rounds')) {
          other = false;
          h.state.handle(
            'POST',
            o.uri.path,
            const <String, String>{},
            <String, Object?>{
              'version': h.state.orders[uuid]!['version'],
              'items': <Object?>[
                <String, Object?>{
                  'variant_id': FakePosState.variantUuid(3),
                  'quantity': '2.000',
                },
              ],
              'send': true,
            },
            bearer(o),
          );
        }
        return null;
      };
      addOsh(ctl, '1');
      expect(
        await settle(tester, ctl.submit(send: true, approve: direct)),
        isTrue,
      );
      final List<String> tickets = <String>[
        for (final EscPosDocument d
            in h.printerFor('server-2').documents.skip(printedBefore))
          d.text,
      ];
      expect(tickets, hasLength(1));
      expect(tickets.single, contains('1 kg (guruch) Osh'));
      expect(tickets.single, isNot(contains('2 kg')));
    },
  );

  testWidgets(
    'lines sent offline are not ticketed again after the sync, when the screen still holds the offline view',
    (WidgetTester tester) async {
      final SyncStore store = await SyncStore.open(MemorySyncStorage());
      final CashierHarness h = CashierHarness(offlineStore: store);
      FakeSyncBackend(h.state);
      h.state.openShift();
      final String uuid = h.state.seedOrder(tableId: 1, guests: 2);
      final OrderController ctl = await editor(tester, h, uuid);
      await settle(tester, h.data.floor.load());
      await settle(tester, h.data.orders.openOrders());

      h.adapter.offline = true;
      await settle(
        tester,
        h.data.floor.load().then<void>((_) {}, onError: (Object _) {}),
      );
      expect(h.data.offline!.active, isTrue);
      final int printedBefore = h.printerFor('server-2').documents.length;
      addOsh(ctl, '1');
      expect(
        await settle(tester, ctl.submit(send: true, approve: direct)),
        isTrue,
      );
      expect(h.printerFor('server-2').documents.length, printedBefore + 1);
      expect(ctl.order!.items.any((PosOrderItem i) => i.id < 0), isTrue);

      h.adapter.offline = false;
      expect((await settle(tester, h.data.offline!.syncNow())).drained, isTrue);
      // The next change on the same screen: the offline lines have server ids now
      expect(await settle(tester, ctl.setGuests(3, approve: direct)), isTrue);
      expect(
        h.printerFor('server-2').documents.length,
        printedBefore + 1,
        reason: 'no second ticket',
      );
    },
  );
}
