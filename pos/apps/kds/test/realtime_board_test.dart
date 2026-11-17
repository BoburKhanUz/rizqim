import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_kds/src/realtime/branch_realtime.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

KdsHarness live() {
  final KdsHarness h = KdsHarness();
  h.state.addOrder(age: const Duration(minutes: 3));
  return h;
}

int ticketFetches(KdsHarness h) =>
    h.backend.to('GET', '/kitchen/tickets').length;

void main() {
  const String channel = 'private-pos-branch.${FakeKitchenState.branchId}';

  testWidgets(
    'the terminal joins the branch channel with its own token, before anyone signs in',
    (WidgetTester tester) async {
      final KdsHarness h = live();
      await pumpPaired(tester, h);

      expect(h.hub.open.single.channels, <String>{channel});
      expect(h.hub.open.single.headers['Origin'], 'https://api.test');
      final RequestOptions auth = h.backend
          .to('POST', '/broadcasting/auth')
          .single;
      expect(
        auth.headers['Authorization'],
        'Bearer ${FakeKitchenState.terminalToken}',
      );
      expect(auth.data, containsPair('channel_name', channel));
      expect(find.text('Jonli'), findsOneWidget);
      expect(BranchRealtime.channelFor(3), channel);
    },
  );

  testWidgets(
    'while nobody is signed in, a new kitchen order plays the sound and is counted',
    (WidgetTester tester) async {
      final KdsHarness h = live();
      await pumpPaired(tester, h);
      h.state.addOrder();
      await tester.pumpAndSettle();
      expect(h.sound.plays, 1);
      expect(
        find.textContaining('1 ta yangi buyurtma oshxonaga keldi'),
        findsOneWidget,
      );
      expect(ticketFetches(h), 0, reason: 'tickets need a PIN session');

      await tester.tap(find.text('Aziz Karimov'));
      await tester.pumpAndSettle();
      await typePin(tester, h.state.pin);
      expect(find.text('2 ta chipta'), findsOneWidget);
    },
  );

  testWidgets(
    'an event refetches at once (not waiting for the poll); M2: the kitchen keeps its 15 s poll while live, conditional',
    (WidgetTester tester) async {
      final KdsHarness h = live();
      await pumpBoard(tester, h);
      final int before = ticketFetches(h);

      h.state.addOrder(
        items: const <(String, String, String, List<String>)>[
          ('Kabob', '2', 'pcs', <String>[]),
        ],
      );
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(ticketFetches(h), before + 1);
      expect(find.text('2 ta chipta'), findsOneWidget);
      expect(h.sound.plays, 1);

      // M2: the kitchen board keeps 15 s while live (each poll a cheap 304 when nothing changed)
      final int afterEvent = ticketFetches(h);
      final int notModified = h.backend.notModified;
      await tester.pump(const Duration(seconds: 10));
      expect(ticketFetches(h), afterEvent);
      await tester.pump(const Duration(seconds: 6));
      await tester.pumpAndSettle();
      expect(ticketFetches(h), afterEvent + 1);
      expect(h.backend.notModified, notModified + 1);
      expect(find.text('2 ta chipta'), findsOneWidget);
    },
  );

  testWidgets(
    'a dropped connection reconnects with backoff, polls every 15 s meanwhile, and refetches on reconnect',
    (WidgetTester tester) async {
      final KdsHarness h = live();
      await pumpBoard(tester, h);
      expect(find.text('Jonli'), findsOneWidget);
      final int connects = h.hub.connects;

      h.hub
        ..down = true
        ..dropAll();
      await tester.pump(const Duration(milliseconds: 50));
      await tester.pump(const Duration(milliseconds: 50));
      expect(find.text('Qayta ulanmoqda'), findsOneWidget);

      // the server stays down: attempts back off (1 s, 2 s, 4 s ...)
      await tester.pump(const Duration(seconds: 2));
      await tester.pump(const Duration(seconds: 3));
      expect(h.hub.connects, greaterThanOrEqualTo(connects + 2));

      // polling every 15 s while realtime is down
      final int fetches = ticketFetches(h);
      h.state.addOrder();
      await tester.pump(const Duration(seconds: 15));
      await tester.pumpAndSettle();
      expect(ticketFetches(h), greaterThan(fetches));
      expect(find.text('2 ta chipta'), findsOneWidget);

      // back up: the next attempt connects, resubscribes and refetches the whole list
      h.hub.down = false;
      final int beforeReconnect = ticketFetches(h);
      await tester.pump(const Duration(seconds: 40));
      await tester.pumpAndSettle();
      expect(find.text('Jonli'), findsOneWidget);
      expect(h.hub.open.single.channels, <String>{channel});
      expect(ticketFetches(h), greaterThan(beforeReconnect));
    },
  );

  testWidgets(
    'signing out keeps the realtime connection; unpairing closes it',
    (WidgetTester tester) async {
      final KdsHarness h = live();
      await pumpBoard(tester, h);
      await tester.tap(buttonLabeled('Chiqish'));
      await tester.pumpAndSettle();
      expect(h.hub.open, hasLength(1));
      expect(find.text('Jonli'), findsOneWidget);

      // a manager signs in and unpairs
      await tester.tap(find.text('Bobur Aliyev'));
      await tester.pumpAndSettle();
      await typePin(tester, h.state.pin);
      await tester.tap(find.byTooltip('Sozlamalar'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Terminalni uzish'));
      await tester.pumpAndSettle();
      await tester.tap(buttonLabeled('Uzish'));
      await tester.pumpAndSettle();
      expect(h.hub.open, isEmpty);
      expect(find.text('Terminalni ulash'), findsOneWidget);
    },
  );

  group('the channel subscription decides "Jonli", not the connection alone', () {
    for (final int code in <int>[403, 429]) {
      testWidgets(
        'a $code from /broadcasting/auth shows the polling state and keeps 15 s polling; once signed it goes live and refetches',
        (WidgetTester tester) async {
          final KdsHarness h = live();
          var refuse = true;
          h.backend.respond = (RequestOptions o) =>
              refuse && o.uri.path.endsWith('/broadcasting/auth')
              ? FakeResponse(
                  code,
                  <String, Object?>{
                    'success': false,
                    'message': code == 429
                        ? "Juda ko'p so'rov."
                        : 'Ruxsat etilmagan.',
                  },
                  headers: code == 429
                      ? const <String, String>{'Retry-After': '1'}
                      : const <String, String>{},
                )
              : null;
          await pumpBoard(tester, h);

          expect(h.hub.open, hasLength(1), reason: 'the socket is connected');
          expect(h.hub.open.single.channels, isEmpty);
          expect(find.text('Jonli'), findsNothing);
          expect(find.text('Har 15 soniyada'), findsOneWidget);
          expect(
            find.byTooltip(RegExp("Real-time kanaliga ulanib bo'lmadi")),
            findsOneWidget,
          );

          // polling stays at 15 s (not 60 s) while the subscription is refused
          final int fetches = ticketFetches(h);
          h.state.addOrder();
          await tester.pump(const Duration(seconds: 15));
          await tester.pumpAndSettle();
          expect(ticketFetches(h), greaterThan(fetches));
          expect(find.text('2 ta chipta'), findsOneWidget);
          expect(
            h.backend.to('POST', '/broadcasting/auth').length,
            greaterThan(1),
            reason: 'the signature is retried in the background',
          );

          // the signature works again: the retry subscribes, the board goes live and refetches the whole list
          refuse = false;
          final int beforeLive = ticketFetches(h);
          await tester.pump(const Duration(seconds: 31));
          await tester.pumpAndSettle();
          expect(h.hub.open.single.channels, <String>{channel});
          expect(find.text('Jonli'), findsOneWidget);
          expect(ticketFetches(h), greaterThan(beforeLive));
        },
      );
    }

    testWidgets(
      'a late subscription confirmation: "Ulanmoqda" and 15 s polling until confirmed, then "Jonli" with a full refetch',
      (WidgetTester tester) async {
        final KdsHarness h = live();
        h.hub.holdSubscriptions = true;
        await pumpBoard(tester, h);
        expect(h.hub.open, hasLength(1));
        expect(find.text('Jonli'), findsNothing);
        expect(find.text('Ulanmoqda'), findsOneWidget);

        final int fetches = ticketFetches(h);
        await tester.pump(const Duration(seconds: 15));
        await tester.pumpAndSettle();
        expect(
          ticketFetches(h),
          fetches + 1,
          reason: '15 s polling while unconfirmed',
        );

        final int beforeConfirm = ticketFetches(h);
        h.hub.releaseSubscriptions();
        await tester.pump();
        await tester.pumpAndSettle();
        expect(find.text('Jonli'), findsOneWidget);
        expect(
          ticketFetches(h),
          beforeConfirm + 1,
          reason: 'confirmation triggers the full refetch',
        );

        // live now: the kitchen's safety poll (M2: 15 s, conditional)
        final int afterLive = ticketFetches(h);
        await tester.pump(const Duration(seconds: 10));
        expect(ticketFetches(h), afterLive);
        await tester.pump(const Duration(seconds: 6));
        await tester.pumpAndSettle();
        expect(ticketFetches(h), afterLive + 1);
      },
    );

    testWidgets(
      'on reconnect the refetch waits for subscription_succeeded, so events in between are not lost',
      (WidgetTester tester) async {
        final KdsHarness h = live();
        await pumpBoard(tester, h);
        expect(find.text('Jonli'), findsOneWidget);

        h.hub
          ..holdSubscriptions = true
          ..dropAll();
        await tester.pump(const Duration(milliseconds: 50));
        await tester.pump(const Duration(seconds: 2));
        await tester.pumpAndSettle();
        expect(h.hub.open, hasLength(1), reason: 'reconnected');
        expect(find.text('Jonli'), findsNothing);
        expect(find.text('Ulanmoqda'), findsOneWidget);

        // an order arrives after the handshake but before the subscription is confirmed (no event reaches us)
        h.state.addOrder();
        final int before = ticketFetches(h);
        h.hub.releaseSubscriptions();
        await tester.pump();
        await tester.pumpAndSettle();
        expect(find.text('Jonli'), findsOneWidget);
        expect(ticketFetches(h), before + 1);
        expect(find.text('2 ta chipta'), findsOneWidget);
      },
    );
  });

  group('M2: revisions, heartbeat, conditional polls', () {
    Map<String, Object?> orderEvent(KdsHarness h) => <String, Object?>{
      'order': <String, Object?>{
        'uuid': 'aaaaaaaa-0000-4000-8000-0000000000ff',
        'status': 'preparing',
        'branch_id': FakeKitchenState.branchId,
      },
      'rev': h.state.revMap,
    };

    testWidgets(
      'an event whose rev the board already read is not re-read; the next token (N+1) is',
      (WidgetTester tester) async {
        final KdsHarness h = live();
        await pumpBoard(tester, h);
        await tester.pumpAndSettle();
        final int before = ticketFetches(h);
        // The board's last read carried X-Revision N: an event with rev N (a late duplicate) changes nothing
        h.hub.broadcast('order.status_changed', orderEvent(h));
        await tester.pump(const Duration(milliseconds: 400));
        await tester.pumpAndSettle();
        expect(ticketFetches(h), before);

        // A new order: its event is N+1, the board re-reads and shows it
        h.state.addOrder();
        await tester.pump(const Duration(milliseconds: 400));
        await tester.pumpAndSettle();
        expect(ticketFetches(h), greaterThan(before));
        expect(find.text('2 ta chipta'), findsOneWidget);
      },
    );

    testWidgets(
      'a missed event: the heartbeat carries another token and the board re-reads at once',
      (WidgetTester tester) async {
        final KdsHarness h = live();
        await pumpBoard(tester, h);
        await tester.pumpAndSettle();
        final int before = ticketFetches(h);
        // A heartbeat with the token the board read: nothing to do
        h.hub.heartbeat(<String, Object?>{'rev': h.state.revMap});
        await tester.pump();
        await tester.pumpAndSettle();
        expect(ticketFetches(h), before);

        // An order whose event never reached the screen (lost): only the heartbeat says the token moved
        h.state
          ..onEvent = null
          ..addOrder();
        h.state.onEvent = h.hub.broadcast;
        h.hub.heartbeat(<String, Object?>{'rev': h.state.revMap});
        await tester.pump();
        await tester.pumpAndSettle();
        expect(ticketFetches(h), before + 1);
        expect(find.text('2 ta chipta'), findsOneWidget);
      },
    );

    testWidgets(
      'M2 fix: bill, payment, fiscal and shift events read nothing but feed the revision (the heartbeat after them is fine)',
      (WidgetTester tester) async {
        final KdsHarness h = live();
        await pumpBoard(tester, h);
        await tester.pumpAndSettle();
        final int before = ticketFetches(h);
        Map<String, Object?> next(Map<String, Object?> body) {
          h.state.revision++;
          return <String, Object?>{...body, 'rev': h.state.revMap};
        }

        h.hub
          ..broadcast(
            'order.bill_changed',
            next(<String, Object?>{...orderEvent(h), 'change': 'payment'}),
          )
          ..broadcast(
            'fiscal.receipt_updated',
            next(<String, Object?>{'receipt': <String, Object?>{}}),
          )
          ..broadcast(
            'shift.changed',
            next(<String, Object?>{
              'shift': <String, Object?>{'id': 1},
            }),
          )
          ..broadcast('order.bill_requested', next(orderEvent(h)));
        await tester.pump(const Duration(seconds: 3));
        await tester.pumpAndSettle();
        expect(ticketFetches(h), before);
        h.hub.heartbeat(<String, Object?>{'rev': h.state.revMap});
        await tester.pump(const Duration(seconds: 3));
        await tester.pumpAndSettle();
        expect(ticketFetches(h), before, reason: 'S followed every event');

        // A kitchen event right after them is the next token, not a gap
        h.state.addOrder();
        await tester.pump(const Duration(milliseconds: 100));
        await tester.pumpAndSettle();
        expect(ticketFetches(h), before + 1);
        expect(find.text('2 ta chipta'), findsOneWidget);
      },
    );

    testWidgets(
      'M2 fix: a burst of kitchen events is one prompt read and one at the end of the 2 s window',
      (WidgetTester tester) async {
        final KdsHarness h = live();
        await pumpBoard(tester, h);
        await tester.pumpAndSettle();
        final int before = ticketFetches(h);
        h.state.addOrder();
        await tester.pump(const Duration(milliseconds: 100));
        expect(ticketFetches(h), before + 1, reason: 'the first one at once');
        for (var i = 0; i < 3; i++) {
          h.state.addOrder();
          await tester.pump(const Duration(milliseconds: 400));
        }
        expect(ticketFetches(h), before + 1);
        await tester.pump(const Duration(milliseconds: 700));
        expect(ticketFetches(h), before + 2, reason: 'one at the window end');
        await tester.pump(const Duration(seconds: 5));
        await tester.pumpAndSettle();
        expect(ticketFetches(h), before + 2);
        expect(find.text('5 ta chipta'), findsOneWidget);
      },
    );

    testWidgets(
      'while live the 15 s polls are conditional (304), and at least every 2 minutes one goes without If-None-Match',
      (WidgetTester tester) async {
        final KdsHarness h = live();
        await pumpBoard(tester, h);
        await tester.pumpAndSettle();
        final int from = h.backend.requests.length;
        for (var i = 0; i < 9; i++) {
          await tester.pump(const Duration(seconds: 15));
          await tester.pumpAndSettle();
        }
        final List<bool> conditional = <bool>[
          for (final RequestOptions r in h.backend.requests.skip(from))
            if (r.uri.path.endsWith('/kitchen/tickets'))
              r.headers['If-None-Match'] != null,
        ];
        expect(conditional, hasLength(9));
        expect(conditional.where((bool c) => !c), hasLength(1));
        expect(conditional.indexOf(false), 7, reason: 'the 8th poll: 120 s');
        expect(h.backend.notModified, greaterThanOrEqualTo(7));
        expect(find.text('1 ta chipta'), findsOneWidget);
      },
    );
  });
}
