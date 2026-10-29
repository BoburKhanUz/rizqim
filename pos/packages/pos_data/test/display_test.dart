import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

Map<String, Object?> cartJson({
  String state = 'cart',
  int seq = 3,
  String total = '69000.00',
}) => <String, Object?>{
  'terminal': 'till-1',
  'seq': seq,
  'session': 'run-1',
  'state': state,
  'lines': <Object?>[
    <String, Object?>{
      'name': "Lag'mon",
      'qty': '2',
      'unit': null,
      'price': '32000.00',
      'total': '64000.00',
      'modifiers': <Object?>['Achchiq'],
      'note': null,
    },
    <String, Object?>{
      'name': 'Qo\'y go\'shti',
      'qty': '0.750',
      'unit': 'kg',
      'price': '5000.00',
      'total': '3750.00',
      'modifiers': <Object?>[],
      'note': 'piyozsiz',
    },
  ],
  'subtotal': '69000.00',
  'discount': '0.00',
  'total': total,
  'queue_number': 17,
  'payment': <String, Object?>{
    'method': 'qr',
    'tendered': null,
    'change': null,
    'qr': <String, Object?>{
      'payload': 'https://pay.example/uz/abc',
      'label': 'Click',
      'provider': 'click',
    },
  },
  'updated_at': '2026-10-22T13:00:00.000000Z',
};

Map<String, Object?> stateJson({Object? cart}) => <String, Object?>{
  'success': true,
  'message': '',
  'data': <String, Object?>{
    'branch': <String, Object?>{'id': 3, 'name': 'Chilonzor'},
    'store': <String, Object?>{'id': 1, 'name': 'Osh markazi'},
    'device': <String, Object?>{
      'uuid': 'd1',
      'type': 'display',
      'name': 'Ikkinchi ekran',
    },
    'till_uuid': 'till-1',
    'modules': <String, Object?>{'customer_display': true, 'queue_calling': false},
    'server_time': '2026-10-22T13:00:30.000000Z',
    'cart': cart,
  },
};

/// P6-14: mijoz ekrani — modellar, repozitoriy (terminal tokeni), kassaning [DisplayFeed]i va `display.cart` hodisasi.
void main() {
  group('models', () {
    test('the state reads the branch, the followed till, the modules and the cart; money and quantities exactly', () {
      final PosDisplayState s = PosDisplayState.fromJson(
        PosJson.of(stateJson(cart: cartJson())['data']),
      );
      expect(s.branchId, 3);
      expect(s.storeName, 'Osh markazi');
      expect(s.tillUuid, 'till-1');
      expect(s.modules.customerDisplay, isTrue);
      expect(s.modules.queueCalling, isFalse);
      final PosDisplayCart cart = s.cart!;
      expect(cart.state, PosDisplayScreen.cart);
      expect(cart.total, const PosMoney.som(69000));
      expect(cart.lines.first.modifiers, <String>['Achchiq']);
      expect(cart.lines.last.quantity, const PosQuantity.milli(750));
      expect(cart.lines.last.unit, 'kg');
      expect(cart.queueNumber, 17);
      expect(cart.payment?.qr?.payload, 'https://pay.example/uz/abc');
      // Server soati bo'yicha: chek 30 soniya oldin yangilangan
      expect(s.cartAge, const Duration(seconds: 30));
    });

    test('no cart, an unknown screen state and an empty payment all read as idle / null', () {
      final PosDisplayState s = PosDisplayState.fromJson(
        PosJson.of(stateJson()['data']),
      );
      expect(s.cart, isNull);
      expect(s.cartAge, isNull);
      expect(PosDisplayScreen.fromWire('weird'), PosDisplayScreen.idle);
      expect(const PosDisplayCart.idle().isIdle, isTrue);
    });

    test('the body sent to the server carries decimal strings and no null clutter that the API refuses', () {
      final PosDisplayCart cart = PosDisplayCart.fromJson(PosJson(cartJson()));
      final Map<String, Object?> body = cart.copyWith(seq: 9, session: 's').toJson();
      expect(body['seq'], 9);
      expect(body['session'], 's');
      expect(body['total'], '69000.00');
      expect((body['lines']! as List<Object?>).last, containsPair('qty', '0.750'));
      expect((body['payment']! as Map<Object?, Object?>)['qr'], containsPair('label', 'Click'));
    });
  });

  group('repository', () {
    late Harness h;

    setUp(() async {
      h = Harness();
      await h.client.auth.pairTerminal('ABCD-EFGH');
    });

    test('the display reads its state and the board with the terminal token, no staff session', () async {
      h.adapter.respond = (RequestOptions o) => o.uri.path.endsWith('/queue')
          ? const FakeResponse(200, <String, Object?>{
              'success': true,
              'message': '',
              'data': <String, Object?>{
                'business_date': '2026-10-22',
                'preparing': <Object?>[
                  <String, Object?>{
                    'order_uuid': 'o1',
                    'number': 4,
                    'business_date': '2026-10-22',
                    'status': 'preparing',
                    'channel': 'pos_takeaway',
                    'order_status': 'delivered',
                    'call_count': 0,
                  },
                ],
                'ready': <Object?>[],
              },
            })
          : FakeResponse(200, stateJson(cart: cartJson()));

      final PosDisplayState state = await h.pos.display.state();
      expect(state.cart?.lines, hasLength(2));
      expect(h.last.uri.path, '/api/v1/pos/display/state');
      expect(h.last.method, 'GET');
      expect(h.last.headers['Authorization'], startsWith('Bearer '));
      expect(h.last.headers['Authorization'], contains('terminal'));

      final PosQueueBoard board = await h.pos.display.queue();
      expect(h.last.uri.path, '/api/v1/pos/display/queue');
      expect(board.preparing.single.number, 4);
    });

    test('publishing posts the cart as JSON without an Idempotency-Key and says whether the server kept it', () async {
      h.adapter.respond = (RequestOptions o) => const FakeResponse(200, <String, Object?>{
        'success': true,
        'message': '',
        'data': <String, Object?>{'stored': false},
      });
      final bool stored = await h.pos.display.publish(
        PosDisplayCart.fromJson(PosJson(cartJson())),
      );
      expect(stored, isFalse);
      expect(h.last.method, 'POST');
      expect(h.last.uri.path, '/api/v1/pos/display/cart');
      expect(h.last.headers.containsKey('Idempotency-Key'), isFalse);
      expect(Harness.bodyOf(h.last)['total'], '69000.00');
    });

    test('a switched-off module is the usual module_disabled error', () async {
      h.adapter.respond = (RequestOptions o) => const FakeResponse(403, <String, Object?>{
        'success': false,
        'message': 'Mijoz ekrani moduli bu filialda o\'chirilgan.',
        'code': 'module_disabled',
        'module': 'customer_display',
        'data': <String, Object?>{'reason': 'module_disabled', 'module': 'customer_display'},
      });
      await expectLater(h.pos.display.state(), throwsA(isA<PosModuleDisabled>()));
    });
  });

  group('DisplayFeed', () {
    late Harness h;
    late List<Map<String, Object?>> posted;

    setUp(() async {
      h = Harness();
      await h.client.auth.pairTerminal('ABCD-EFGH');
      posted = <Map<String, Object?>>[];
      h.adapter.respond = (RequestOptions o) {
        posted.add(Harness.bodyOf(o));
        return const FakeResponse(200, <String, Object?>{
          'success': true,
          'message': '',
          'data': <String, Object?>{'stored': true},
        });
      };
    });

    PosDisplayCart cart(String total, {PosDisplayScreen state = PosDisplayScreen.cart}) =>
        PosDisplayCart(
          state: state,
          lines: <PosDisplayLine>[
            PosDisplayLine(
              name: 'Osh',
              quantity: PosQuantity.one,
              price: PosMoney.parse(total),
              total: PosMoney.parse(total),
            ),
          ],
          subtotal: PosMoney.parse(total),
          total: PosMoney.parse(total),
        );

    test('taps in quick succession become one publish with the last cart; seq grows, the session stays', () async {
      final DisplayFeed feed = DisplayFeed(h.pos.display, debounce: const Duration(milliseconds: 20), session: 'run-9');
      feed.publish(cart('1000'));
      feed.publish(cart('2000'));
      feed.publish(cart('3000'));
      await feed.flush();
      expect(posted, hasLength(1));
      expect(posted.single['total'], '3000.00');
      expect(posted.single['session'], 'run-9');
      expect(posted.single['seq'], 1);

      feed.publish(cart('4000'));
      await feed.flush();
      expect(posted.last['seq'], 2);
      expect(feed.sent, 2);
    });

    test('payment, paid and idle go at once, a repeated identical cart is not sent again', () async {
      final DisplayFeed feed = DisplayFeed(h.pos.display, debounce: const Duration(hours: 1));
      feed.publish(cart('5000', state: PosDisplayScreen.payment));
      await feed.flush();
      feed.publish(cart('5000', state: PosDisplayScreen.payment));
      await feed.flush();
      expect(posted, hasLength(1));

      feed.publish(cart('5000', state: PosDisplayScreen.paid));
      feed.idle();
      await feed.flush();
      expect(posted.map((Map<String, Object?> b) => b['state']), <Object?>['payment', 'paid', 'idle']);
    });

    test('a refused or unreachable display never throws into the till; the next change is tried again', () async {
      var fail = true;
      h.adapter.respond = (RequestOptions o) => fail
          ? const FakeResponse(403, <String, Object?>{
              'success': false,
              'message': 'off',
              'code': 'module_disabled',
              'data': <String, Object?>{'reason': 'module_disabled'},
            })
          : const FakeResponse(200, <String, Object?>{'success': true, 'message': '', 'data': <String, Object?>{'stored': true}});
      final DisplayFeed feed = DisplayFeed(h.pos.display, debounce: Duration.zero);
      feed.publish(cart('1000'));
      await feed.flush();
      expect(feed.lastError, isA<PosModuleDisabled>());
      expect(feed.sent, 0);

      fail = false;
      feed.publish(cart('1000'));
      await feed.flush();
      expect(feed.sent, 1);
      expect(feed.lastError, isNull);
    });

    test('a disabled feed (module off) sends nothing', () async {
      final DisplayFeed feed = DisplayFeed(h.pos.display, debounce: Duration.zero)..enabled = false;
      feed.publish(cart('1000'));
      feed.idle();
      await feed.flush();
      expect(h.adapter.requests.where((RequestOptions r) => r.uri.path.contains('display')), isEmpty);
    });
  });

  group('event', () {
    test('display.cart is a hint with the till, the sequence and the screen state', () {
      final PosEvent e = PosEvent.fromWire('display.cart', <String, Object?>{
        'terminal': 'till-1',
        'seq': 12,
        'state': 'payment',
        'branch_id': 3,
        'store_id': 1,
      });
      expect(e, isA<PosDisplayCartChanged>());
      e as PosDisplayCartChanged;
      expect(e.terminal, 'till-1');
      expect(e.seq, 12);
      expect(e.state, 'payment');
    });
  });
}
