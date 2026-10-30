import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'support/harness.dart';

/// P6-33 / P6-34 (wave 3): the board shows what the server calls ready. An order cooked on several stations is ready
/// only when the expo station assembles it (`queue.ready` from the backend, after `kitchen.order_assembled`); a pickup
/// scheduled for later is not on the board until the kitchen gets it (`queue.preparing`). The display needed no change:
/// these tests pin that.
void main() {
  Finder chip(int n) => find.byKey(ValueKey<String>('number-$n'));
  Finder inPanel(String panel, int n) => find.descendant(
    of: find.byKey(ValueKey<String>('panel-$panel')),
    matching: chip(n),
  );

  DisplayHarness board({bool realtime = true}) {
    final DisplayHarness h = DisplayHarness(
      realtime: realtime,
      pollInterval: const Duration(seconds: 2),
    );
    h.backend.customerDisplay = false;
    h.backend.preparing.addAll(<Map<String, Object?>>[
      FakeDisplayBackend.entry(45, 'preparing'),
      FakeDisplayBackend.entry(46, 'preparing'),
    ]);
    return h;
  }

  Map<String, Object?> assembled(int number, {bool done = true}) =>
      <String, Object?>{
        'order': <String, Object?>{
          'uuid': 'o$number',
          'status': 'ready',
          'store_id': 1,
          'branch_id': 1,
          'courier_id': null,
          'updated_at': DateTime.utc(2026, 10, 22, 13).toIso8601String(),
        },
        'assembled': done,
        'assembled_at': done
            ? DateTime.utc(2026, 10, 22, 13).toIso8601String()
            : null,
        'queue_number': number,
      };

  Map<String, Object?> stationBumped(int number) => <String, Object?>{
    'order': <String, Object?>{
      'uuid': 'o$number',
      'status': 'preparing',
      'store_id': 1,
      'branch_id': 1,
      'courier_id': null,
      'updated_at': DateTime.utc(2026, 10, 22, 13).toIso8601String(),
    },
  };

  testWidgets(
    'stations finishing do not call the number; the expo bump (queue.ready) does, at once and once',
    (WidgetTester tester) async {
      final DisplayHarness h = board();
      await h.pump(tester);
      expect(inPanel('preparing', 45), findsOneWidget);

      // Every station bumped its lines: the order waits on the expo, the number stays "being prepared"
      h.hub.broadcast('kitchen.item_status_changed', stationBumped(45));
      await h.advance(tester, const Duration(milliseconds: 500));
      expect(inPanel('preparing', 45), findsOneWidget);
      expect(inPanel('ready', 45), findsNothing);
      expect(h.sound.plays, 0);
      expect(find.text('45 raqam tayyor!'), findsNothing);

      // The expo bumps "Yig'ildi": the server sends kitchen.order_assembled (staff screens) and queue.ready (the board)
      final DateTime bump = h.now;
      h.hub.broadcast('kitchen.order_assembled', assembled(45));
      h.backend.markReady(45);
      await h.advance(tester, const Duration(milliseconds: 300));
      // latency: the number is ready on the display within a fraction of a second of the bump
      expect(h.now.difference(bump), lessThan(const Duration(seconds: 1)));
      expect(h.sound.plays, 1);
      expect(find.text('45 raqam tayyor!'), findsOneWidget);
      expect(inPanel('ready', 45), findsOneWidget);
      expect(inPanel('preparing', 45), findsNothing);
      // the other number is untouched
      expect(inPanel('preparing', 46), findsOneWidget);

      await h.advance(tester, const Duration(seconds: 6));
      expect(h.sound.plays, 1);
    },
  );

  testWidgets(
    'without realtime the poll shows the assembled order as ready and plays the sound once',
    (WidgetTester tester) async {
      final DisplayHarness h = board(realtime: false);
      await h.pump(tester);
      h.backend.markReady(46);
      await h.advance(tester, const Duration(milliseconds: 2400));
      expect(inPanel('ready', 46), findsOneWidget);
      expect(h.sound.plays, 1);
      await h.advance(tester, const Duration(seconds: 6));
      expect(h.sound.plays, 1);
    },
  );

  testWidgets(
    'an assembly undone (a line recalled): the number goes back to "Tayyorlanmoqda" and is announced again when re-assembled',
    (WidgetTester tester) async {
      final DisplayHarness h = board();
      await h.pump(tester);
      h.backend.markReady(45);
      await h.advance(tester, const Duration(milliseconds: 300));
      expect(h.sound.plays, 1);
      await h.advance(tester, const Duration(seconds: 10));

      // kitchen.order_assembled {assembled: false}, then the number is being prepared again
      h.hub.broadcast('kitchen.order_assembled', assembled(45, done: false));
      h.backend.ready.removeWhere(
        (Map<String, Object?> e) => e['number'] == 45,
      );
      h.backend.addPreparing(45);
      await h.advance(tester, const Duration(milliseconds: 300));
      expect(inPanel('preparing', 45), findsOneWidget);
      expect(inPanel('ready', 45), findsNothing);
      expect(h.sound.plays, 1);

      h.backend.markReady(45);
      await h.advance(tester, const Duration(milliseconds: 300));
      expect(h.sound.plays, 2);
      expect(inPanel('ready', 45), findsOneWidget);
    },
  );

  testWidgets(
    'a scheduled pickup is not on the board before it fires; it joins "Tayyorlanmoqda" without a call, then ready',
    (WidgetTester tester) async {
      final DisplayHarness h = board();
      await h.pump(tester);
      // 77 is held for its pickup time: the server lists no pickup_status for it, so the board has no entry
      expect(chip(77), findsNothing);
      // time passes, nothing changes (the held order sends no queue event)
      await h.advance(tester, const Duration(seconds: 30));
      expect(chip(77), findsNothing);
      expect(h.sound.plays, 0);

      // pickup time − prep time: the kitchen gets it (ScheduledPickup::fire → queue.preparing)
      h.backend.addPreparing(77);
      await h.advance(tester, const Duration(milliseconds: 400));
      expect(inPanel('preparing', 77), findsOneWidget);
      expect(h.sound.plays, 0);
      expect(find.text('77 raqam tayyor!'), findsNothing);

      h.backend.markReady(77);
      await h.advance(tester, const Duration(milliseconds: 300));
      expect(inPanel('ready', 77), findsOneWidget);
      expect(h.sound.plays, 1);
    },
  );

  testWidgets(
    'kitchen events the display does not know do not disturb the board',
    (WidgetTester tester) async {
      final DisplayHarness h = board();
      await h.pump(tester);
      h.hub.broadcast('kitchen.order_assembled', assembled(45));
      h.hub.broadcast('kitchen.item_status_changed', stationBumped(46));
      await h.advance(tester, const Duration(milliseconds: 500));
      expect(inPanel('preparing', 45), findsOneWidget);
      expect(inPanel('preparing', 46), findsOneWidget);
      expect(h.sound.plays, 0);
      expect(tester.takeException(), isNull);
    },
  );
}
