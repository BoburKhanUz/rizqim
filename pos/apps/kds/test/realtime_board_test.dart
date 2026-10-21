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
    'an event refetches at once (not waiting for the poll) and the poll slows to 60 s while live',
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

      // no poll after 15 s while live; one after 60 s
      final int afterEvent = ticketFetches(h);
      await tester.pump(const Duration(seconds: 20));
      expect(ticketFetches(h), afterEvent);
      await tester.pump(const Duration(seconds: 41));
      await tester.pumpAndSettle();
      expect(ticketFetches(h), afterEvent + 1);
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

        // live now: the next poll waits 60 s
        final int afterLive = ticketFetches(h);
        await tester.pump(const Duration(seconds: 20));
        expect(ticketFetches(h), afterLive);
        await tester.pump(const Duration(seconds: 41));
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
}
