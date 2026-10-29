import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_customer_display/src/widgets/auto_scroller.dart';

import 'support/harness.dart';

/// P6-14, 2-rejim: "Tayyorlanmoqda / Tayyor" raqamlar taxtasi (televizor). Tayyor raqam hodisadan 2 soniyadan tez
/// yirik chiqadi va ovoz chalinadi; hodisa yo'qolsa so'rov ham ovozni chiqaradi (bir raqamga bir marta).
void main() {
  Finder chip(int n) => find.byKey(ValueKey<String>('number-$n'));
  Finder inPanel(String panel, int n) => find.descendant(
    of: find.byKey(ValueKey<String>('panel-$panel')),
    matching: chip(n),
  );

  DisplayHarness board({
    bool realtime = true,
    Duration poll = const Duration(seconds: 5),
  }) {
    final DisplayHarness h = DisplayHarness(
      realtime: realtime,
      pollInterval: poll,
    );
    h.backend.customerDisplay = false;
    h.backend.preparing.addAll(<Map<String, Object?>>[
      FakeDisplayBackend.entry(21, 'preparing'),
      FakeDisplayBackend.entry(18, 'preparing'),
    ]);
    h.backend.ready.add(
      FakeDisplayBackend.entry(
        15,
        'ready',
        readyAt: DateTime.utc(2026, 10, 22, 12, 58),
      ),
    );
    return h;
  }

  testWidgets(
    'the board lists what is being prepared (by number) and what is ready; nothing is announced on the first load',
    (WidgetTester tester) async {
      final DisplayHarness h = board();
      await h.pump(tester);

      expect(find.text('Tayyorlanmoqda'), findsOneWidget);
      expect(find.text('Tayyor'), findsOneWidget);
      expect(inPanel('preparing', 18), findsOneWidget);
      expect(inPanel('preparing', 21), findsOneWidget);
      expect(inPanel('ready', 15), findsOneWidget);
      // Preparing numbers go in ascending order
      expect(
        tester.getTopLeft(chip(18)).dx,
        lessThan(tester.getTopLeft(chip(21)).dx),
      );
      expect(
        h.sound.plays,
        0,
        reason: 'numbers already ready at start-up are not "new"',
      );
      expect(find.textContaining('raqam tayyor'), findsNothing);
    },
  );

  testWidgets(
    'a number turns ready: sound at once, a big banner, the chip moves to "Tayyor" and is highlighted',
    (WidgetTester tester) async {
      final DisplayHarness h = board();
      await h.pump(tester);
      final DateTime sentAt = h.now;

      h.backend.markReady(18);
      await h.advance(tester, const Duration(milliseconds: 300));

      expect(h.now.difference(sentAt), lessThan(const Duration(seconds: 2)));
      expect(h.sound.plays, 1);
      expect(find.text('18 raqam tayyor!'), findsOneWidget);
      expect(inPanel('ready', 18), findsOneWidget);
      expect(inPanel('preparing', 18), findsNothing);

      // The next refresh (poll) does not play it a second time
      await h.advance(tester, const Duration(seconds: 6));
      expect(h.sound.plays, 1);

      // After the hold the banner leaves, the number stays on the board
      await h.advance(tester, const Duration(seconds: 9));
      await tester.pumpAndSettle();
      expect(find.text('18 raqam tayyor!'), findsNothing);
      expect(inPanel('ready', 18), findsOneWidget);
    },
  );

  testWidgets('"Qayta chaqirish" announces the number again with sound', (
    WidgetTester tester,
  ) async {
    final DisplayHarness h = board();
    await h.pump(tester);

    h.backend.markReady(21);
    await h.advance(tester, const Duration(milliseconds: 300));
    expect(h.sound.plays, 1);
    await h.advance(tester, const Duration(seconds: 10));

    h.backend.callAgain(21);
    await h.advance(tester, const Duration(milliseconds: 300));
    expect(h.sound.plays, 2);
    expect(find.text('21 raqam, buyurtmangizni oling!'), findsOneWidget);
  });

  testWidgets(
    'handing a number over takes it off the board; a muted display shows the banner but stays silent',
    (WidgetTester tester) async {
      final DisplayHarness h = board();
      h.settings.update(h.settings.value.copyWith(muted: true));
      await h.pump(tester);

      h.backend.markReady(18);
      await h.advance(tester, const Duration(milliseconds: 300));
      expect(h.sound.plays, 0);
      expect(find.text('18 raqam tayyor!'), findsOneWidget);

      h.backend.hand(15);
      await h.advance(tester, const Duration(milliseconds: 300));
      expect(inPanel('ready', 15), findsNothing);
    },
  );

  testWidgets(
    'without realtime the poll finds the new ready number and still plays the sound once',
    (WidgetTester tester) async {
      final DisplayHarness h = board(
        realtime: false,
        poll: const Duration(seconds: 2),
      );
      await h.pump(tester);

      h.backend.markReady(18);
      await h.advance(tester, const Duration(milliseconds: 2400));
      expect(h.sound.plays, 1);
      expect(inPanel('ready', 18), findsOneWidget);
      await h.advance(tester, const Duration(seconds: 5));
      expect(h.sound.plays, 1);
    },
  );

  testWidgets(
    'a number sent back to the kitchen and ready again is announced again',
    (WidgetTester tester) async {
      final DisplayHarness h = board();
      await h.pump(tester);
      h.backend.markReady(18);
      await h.advance(tester, const Duration(milliseconds: 300));
      expect(h.sound.plays, 1);
      await h.advance(tester, const Duration(seconds: 10));

      // A line was recalled: preparing again, then ready again
      h.backend.ready.removeWhere(
        (Map<String, Object?> e) => e['number'] == 18,
      );
      h.backend.addPreparing(18);
      await h.advance(tester, const Duration(milliseconds: 300));
      expect(inPanel('preparing', 18), findsOneWidget);
      h.backend.markReady(18);
      await h.advance(tester, const Duration(milliseconds: 300));
      expect(h.sound.plays, 2);
    },
  );

  testWidgets(
    'a long list scrolls on its own and starts over; a short one stays still',
    (WidgetTester tester) async {
      final DisplayHarness h = board();
      h.backend.preparing
        ..clear()
        ..addAll(<Map<String, Object?>>[
          for (var n = 100; n < 160; n++)
            FakeDisplayBackend.entry(n, 'preparing'),
        ]);
      await h.pump(tester, size: const Size(1280, 720));

      final Finder scroller = find.descendant(
        of: find.byKey(const ValueKey<String>('panel-preparing')),
        matching: find.byType(AutoScroller),
      );
      expect(scroller, findsOneWidget);
      final ScrollableState state = tester.state<ScrollableState>(
        find.descendant(of: scroller, matching: find.byType(Scrollable)),
      );
      expect(state.position.maxScrollExtent, greaterThan(0));
      expect(state.position.pixels, 0);

      // Waits a few seconds at the top, then moves
      await h.advance(tester, const Duration(seconds: 6));
      final double moved = state.position.pixels;
      expect(moved, greaterThan(0));
      await h.advance(tester, const Duration(seconds: 3));
      expect(state.position.pixels, greaterThan(moved));

      // ... to the end, a pause, and back to the top
      await h.advance(tester, const Duration(seconds: 120));
      expect(
        state.position.pixels,
        lessThanOrEqualTo(state.position.maxScrollExtent),
      );
      var wrapped = false;
      for (var i = 0; i < 400 && !wrapped; i++) {
        await h.advance(
          tester,
          const Duration(seconds: 1),
          step: const Duration(milliseconds: 500),
        );
        wrapped = state.position.pixels < moved;
      }
      expect(wrapped, isTrue, reason: 'after the end it returns to the top');

      // The ready panel holds one number: nothing to scroll
      final ScrollableState ready = tester.state<ScrollableState>(
        find.descendant(
          of: find.descendant(
            of: find.byKey(const ValueKey<String>('panel-ready')),
            matching: find.byType(AutoScroller),
          ),
          matching: find.byType(Scrollable),
        ),
      );
      expect(ready.position.maxScrollExtent, 0);
    },
  );

  testWidgets(
    'both modules on: a cart takes the screen, and the board returns when the till is idle (auto mode)',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness();
      h.backend.ready.add(
        FakeDisplayBackend.entry(
          15,
          'ready',
          readyAt: DateTime.utc(2026, 10, 22, 12, 58),
        ),
      );
      await h.pump(tester);
      expect(find.text('Tayyorlanmoqda'), findsOneWidget);

      h.backend.publish(
        lines: <Map<String, Object?>>[
          FakeDisplayBackend.line('Osh', price: '40000.00'),
        ],
        total: '40000.00',
      );
      await h.advance(tester, const Duration(milliseconds: 600));
      expect(find.text('Osh'), findsOneWidget);
      expect(find.text('Tayyorlanmoqda'), findsNothing);

      h.backend.publish(state: 'idle');
      await h.advance(tester, const Duration(milliseconds: 600));
      await tester.pumpAndSettle();
      expect(find.text('Tayyorlanmoqda'), findsOneWidget);
    },
  );
}
