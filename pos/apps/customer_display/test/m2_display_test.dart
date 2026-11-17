import 'package:flutter_test/flutter_test.dart';

import 'support/harness.dart';

/// Wave M2 on the customer display: conditional polls (a 304 keeps the moment of the original 200), event revisions,
/// the heartbeat that catches a lost event.
void main() {
  testWidgets(
    'polls answered 304 keep the cart\'s receive time: a forgotten cart still goes stale on time',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness(
        staleCart: const Duration(minutes: 1),
      );
      h.backend.queueCalling = false;
      await h.pump(tester);
      h.backend.publish(
        lines: <Map<String, Object?>>[
          FakeDisplayBackend.line('Somsa', price: '9000.00'),
        ],
        total: '9000.00',
      );
      await h.advance(tester, const Duration(milliseconds: 400));
      expect(find.text('Somsa'), findsOneWidget);
      final int before = h.backend.notModified;
      // Two live safety polls (30 s): nothing changed, both 304 with the old body and its old server_time
      await h.advance(
        tester,
        const Duration(seconds: 59),
        step: const Duration(seconds: 1),
      );
      expect(h.backend.notModified, greaterThan(before));
      expect(find.text('Somsa'), findsOneWidget);
      await h.advance(
        tester,
        const Duration(seconds: 3),
        step: const Duration(seconds: 1),
      );
      await tester.pumpAndSettle();
      expect(find.text('Somsa'), findsNothing, reason: 'stale after a minute');
      expect(find.text('Xush kelibsiz!'), findsOneWidget);
    },
  );

  testWidgets(
    'an event with the token already read is not re-read; the next token (N+1) is',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness();
      h.backend.queueCalling = false;
      await h.pump(tester);
      final int calls = h.backend.stateCalls;
      // A late duplicate of something the screen already read
      h.hub.broadcast('display.cart', <String, Object?>{
        'terminal': 'till-1',
        'seq': 0,
        'state': 'idle',
        'rev': h.backend.revMap,
      });
      await h.advance(tester, const Duration(milliseconds: 400));
      expect(h.backend.stateCalls, calls);

      h.backend.publish(
        lines: <Map<String, Object?>>[
          FakeDisplayBackend.line('Osh', price: '40000.00'),
        ],
        total: '40000.00',
      );
      await h.advance(tester, const Duration(milliseconds: 400));
      expect(h.backend.stateCalls, calls + 1);
      expect(find.text('Osh'), findsOneWidget);
    },
  );

  testWidgets(
    'a lost event: the heartbeat names another token and the screen re-reads at once',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness();
      h.backend.queueCalling = false;
      await h.pump(tester);
      final int calls = h.backend.stateCalls;
      h.backend.heartbeat();
      await h.advance(tester, const Duration(milliseconds: 200));
      expect(h.backend.stateCalls, calls, reason: 'same token: nothing');

      // The till's cart changed on the server, its display.cart never arrived
      h.backend
        ..cart = <String, Object?>{
          'terminal': 'till-1',
          'seq': 9,
          'session': 'run-1',
          'state': 'cart',
          'lines': <Object?>[
            FakeDisplayBackend.line('Manti', price: '30000.00'),
          ],
          'subtotal': '30000.00',
          'discount': '0.00',
          'total': '30000.00',
          'queue_number': null,
          'payment': null,
          'updated_at': h.now.toUtc().toIso8601String(),
        }
        ..revision += 1;
      h.backend.heartbeat();
      await h.advance(tester, const Duration(milliseconds: 200));
      expect(h.backend.stateCalls, calls + 1);
      expect(find.text('Manti'), findsOneWidget);
    },
  );

  testWidgets(
    'M2 fix: order events and another till\'s cart read nothing but feed the token: the heartbeat after them is fine',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness();
      h.backend
        ..queueCalling = false
        ..tillUuid = 'till-1';
      await h.pump(tester);
      final int calls = h.backend.stateCalls;
      Map<String, Object?> next(Map<String, Object?> body) {
        h.backend.revision += 1;
        return <String, Object?>{...body, 'rev': h.backend.revMap};
      }

      final Map<String, Object?> order = <String, Object?>{
        'order': <String, Object?>{'uuid': 'o-1', 'status': 'accepted'},
      };
      h.hub
        ..broadcast('order.created', next(order))
        ..broadcast(
          'order.bill_changed',
          next(<String, Object?>{...order, 'change': 'payment'}),
        )
        ..broadcast(
          'display.cart',
          next(<String, Object?>{
            'terminal': 'till-2',
            'seq': 1,
            'state': 'cart',
          }),
        );
      await h.advance(tester, const Duration(seconds: 1));
      expect(h.backend.stateCalls, calls);
      h.backend.heartbeat();
      await h.advance(tester, const Duration(seconds: 1));
      expect(
        h.backend.stateCalls,
        calls,
        reason: 'the heartbeat matches S: no read',
      );
    },
  );

  testWidgets(
    'M2 fix: doubt alone (an ignored event jumped) is left to the safety poll; a cart burst is one paced read',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness();
      h.backend.queueCalling = false;
      await h.pump(tester);
      final int calls = h.backend.stateCalls;
      h.backend.revision += 2; // one commit's event lost before this one
      h.hub.broadcast('order.created', <String, Object?>{
        'order': <String, Object?>{'uuid': 'o-2', 'status': 'accepted'},
        'rev': h.backend.revMap,
      });
      h.backend.heartbeat();
      await h.advance(tester, const Duration(seconds: 1));
      expect(h.backend.stateCalls, calls, reason: 'no heartbeat read on doubt');

      for (var i = 0; i < 3; i++) {
        h.backend.publish(
          lines: <Map<String, Object?>>[
            FakeDisplayBackend.line('Somsa', price: '9000.00'),
          ],
          total: '${9000 * (i + 1)}.00',
        );
      }
      await h.advance(tester, const Duration(milliseconds: 100));
      expect(h.backend.stateCalls, calls + 1, reason: 'the first one at once');
      await h.advance(tester, const Duration(milliseconds: 400));
      expect(h.backend.stateCalls, lessThanOrEqualTo(calls + 2));
      expect(find.text('Somsa'), findsOneWidget);
    },
  );
}
