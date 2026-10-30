import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_kds/src/board/grill_timer.dart';
import 'package:rizqim_kds/src/board/skewer_groups.dart';
import 'package:rizqim_kds/src/widgets/grill_timer_label.dart';
import 'package:rizqim_kds/src/widgets/line_marks.dart';
import 'package:rizqim_kds/src/widgets/mangal_card.dart';
import 'package:rizqim_kds/src/widgets/ticket_card.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

/// P6-21 / P6-25: course marks, the course bump, the weight badge, the table plate and the grill timer on the ticket.

KdsHarness kitchen({Map<String, Object?>? stations}) {
  final KdsHarness h = KdsHarness(
    realtime: false,
    stationValues:
        stations ??
        const <String, Object?>{
          'stations_chosen': true,
          'stations_ids': '1,2',
          'stations_general': true,
        },
  );
  h.settings.update(h.settings.value.copyWith(idleTimeout: () => null));
  h.state
    ..addStation(1, 'Mangal', 'mangal')
    ..addStation(2, 'Osh', 'osh');
  return h;
}

Finder card(String code) =>
    find.ancestor(of: find.text(code), matching: find.byType(TicketCard));

Finder lineOf(String title) =>
    find.bySemanticsLabel(RegExp('^${RegExp.escape(title)},'));

/// Three courses on one table: soup (1), kebab and plov (2), dessert (3). Only 1 and 2 are in the kitchen: the
/// dessert is held at the till and the kitchen does not get it yet.
String threeCourses(KdsHarness h) => h.state.addPosOrder(
  table: '7',
  lines: const <PosLine>[
    PosLine("Sho'rva", '2', station: 2, course: 1),
    PosLine("Qo'y kabob", '4', station: 1, course: 2),
    PosLine('Osh', '2', station: 2, course: 2),
  ],
);

KitchenTicket ticketOf(List<KitchenLine> lines) => KitchenTicket(
  uuid: 'u-1',
  status: 'preparing',
  type: 'dine_in',
  lines: lines,
  waitingSeconds: 0,
  receivedAt: DateTime(2026, 10, 30, 19),
);

KitchenLine kl(
  int id, {
  String status = 'sent',
  int? course,
  int? wait,
  DateTime? sentAt,
  String? weight,
  String name = 'Baliq',
}) => KitchenLine(
  id: id,
  productName: name,
  quantityLabel: '1',
  status: status,
  fireMode: 'now',
  sentAt: sentAt,
  courseNo: course,
  waitMinutes: wait,
  weightStatus: weight,
);

void main() {
  group('course marks', () {
    testWidgets(
      'a ticket with courses groups the lines under "1-kurs" / "2-kurs" headings with their progress',
      (WidgetTester tester) async {
        final KdsHarness h = kitchen();
        threeCourses(h);
        await pumpBoard(tester, h);

        expect(find.byKey(const ValueKey<String>('course-1')), findsOneWidget);
        expect(find.byKey(const ValueKey<String>('course-2')), findsOneWidget);
        expect(find.text('1-kurs'), findsOneWidget);
        expect(find.text('2-kurs'), findsOneWidget);
        expect(find.text('0/1 tayyor'), findsOneWidget);
        expect(find.text('0/2 tayyor'), findsOneWidget);
        // the line's semantics name its course
        expect(
          find.bySemanticsLabel(RegExp("^Qo'y kabob, 4×, tayyor emas, 2-kurs")),
          findsOneWidget,
        );
        // a held course is not on the ticket: the kitchen sees only what was sent
        expect(find.text('3-kurs'), findsNothing);
        expect(tester.takeException(), isNull);
      },
    );

    testWidgets(
      'lines added in a mixed order are shown course by course (round first, then course)',
      (WidgetTester tester) async {
        final KdsHarness h = kitchen();
        h.state.addPosOrder(
          table: '9',
          lines: const <PosLine>[
            PosLine('Somsa', '2', station: 2, course: 2),
            PosLine('Salat', '1', station: 2, course: 1),
            PosLine('Choy', '2', station: 2, course: 2),
            PosLine('Non', '1', station: 2),
          ],
        );
        await pumpBoard(tester, h);

        double y(String t) => tester.getTopLeft(find.text(t)).dy;
        // no course first ("Kurssiz"), then 1, then 2 (server order inside a course)
        expect(find.text('Kurssiz'), findsOneWidget);
        expect(y('Non') < y('Salat'), isTrue);
        expect(y('Salat') < y('Somsa'), isTrue);
        expect(y('Somsa') < y('Choy'), isTrue);
        // "Kurssiz" has no course bump button
        expect(
          find.byKey(const ValueKey<String>('bump-course-null')),
          findsNothing,
        );
      },
    );

    testWidgets(
      'one course on the ticket: the mark is there, the course button is not (it would repeat "Hammasi tayyor")',
      (WidgetTester tester) async {
        final KdsHarness h = kitchen();
        h.state.addPosOrder(
          table: '4',
          lines: const <PosLine>[
            PosLine('Somsa', '2', station: 2, course: 2),
            PosLine('Choy', '2', station: 2, course: 2),
          ],
        );
        await pumpBoard(tester, h);

        expect(find.text('2-kurs'), findsOneWidget);
        expect(
          find.byKey(const ValueKey<String>('bump-course-2')),
          findsNothing,
        );
        expect(buttonLabeled('Hammasi tayyor'), findsOneWidget);
      },
    );

    testWidgets('a ticket without courses has no course heading at all', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = kitchen();
      h.state.addPosOrder(
        lines: const <PosLine>[PosLine('Somsa', '2', station: 2)],
      );
      await pumpBoard(tester, h);

      expect(find.textContaining('-kurs'), findsNothing);
      expect(find.text('Kurssiz'), findsNothing);
      expect(find.textContaining('tayyor emas'), findsNothing);
    });
  });

  group('course bump', () {
    testWidgets(
      '"1-kurs tayyor" marks only that course ready, line by line, and the course is completed for the waiter',
      (WidgetTester tester) async {
        final KdsHarness h = kitchen();
        final String uuid = threeCourses(h);
        await pumpBoard(tester, h);

        expect(buttonLabeled('1-kurs tayyor'), findsOneWidget);
        expect(buttonLabeled('2-kurs tayyor'), findsOneWidget);

        await tester.tap(buttonLabeled('1-kurs tayyor'));
        await tester.pumpAndSettle();

        final List<String> posts = h.backend
            .to('POST', '/items/')
            .map((r) => r.uri.path)
            .toList();
        expect(posts, hasLength(1));
        expect(posts.single, endsWith('/ready'));
        // the course the soup belongs to is complete; the second is still cooking
        expect(h.state.courseReady, <(String, int)>[(uuid, 1)]);
        expect(find.text('1/1 tayyor'), findsOneWidget);
        expect(find.text('0/2 tayyor'), findsOneWidget);
        // the finished course has no button any more
        expect(buttonLabeled('1-kurs tayyor'), findsNothing);
        expect(buttonLabeled('2-kurs tayyor'), findsOneWidget);
      },
    );

    testWidgets(
      'bumping the last line of a course by hand completes it the same way (the button is a shortcut)',
      (WidgetTester tester) async {
        final KdsHarness h = kitchen();
        final String uuid = threeCourses(h);
        await pumpBoard(tester, h);

        await tester.tap(lineOf("Qo'y kabob"));
        await tester.pumpAndSettle();
        expect(h.state.courseReady, isEmpty, reason: 'one more line to cook');
        await tester.tap(lineOf('Osh'));
        await tester.pumpAndSettle();
        expect(h.state.courseReady, <(String, int)>[(uuid, 2)]);
      },
    );

    testWidgets(
      'without kitchen.manage the courses are shown but there is no course button',
      (WidgetTester tester) async {
        final KdsHarness h = kitchen();
        h.state.permissions = <String>['orders.view'];
        threeCourses(h);
        await pumpBoard(tester, h);

        expect(find.text('1-kurs'), findsOneWidget);
        expect(find.text('2-kurs'), findsOneWidget);
        expect(buttonLabeled('1-kurs tayyor'), findsNothing);
        expect(buttonLabeled('2-kurs tayyor'), findsNothing);
        expect(h.backend.to('POST', '/items/'), isEmpty);
      },
    );
  });

  group('weight badge', () {
    testWidgets(
      'a weight-later line says "Og\'irlik kutilmoqda" until it is weighed or ready',
      (WidgetTester tester) async {
        final KdsHarness h = kitchen();
        h.state.addPosOrder(
          table: '5',
          lines: const <PosLine>[
            PosLine(
              'Sazan',
              '1.2 kg',
              unit: 'kg',
              station: 1,
              weight: 'awaiting',
            ),
            PosLine(
              'Losos',
              '0.8 kg',
              unit: 'kg',
              station: 1,
              weight: 'weighed',
            ),
            PosLine('Non', '2', station: 2),
          ],
        );
        await pumpBoard(tester, h);

        expect(find.text(WeightBadge.label), findsOneWidget);
        expect(
          find.bySemanticsLabel(
            RegExp("^Sazan, 1.2 kg, tayyor emas, Og'irlik"),
          ),
          findsOneWidget,
        );

        // bumped ready: the badge goes (the cook may bump it; the price is the till's business)
        await tester.tap(lineOf('Sazan'));
        await tester.pumpAndSettle();
        expect(find.text(WeightBadge.label), findsNothing);
      },
    );
  });

  group('table plate', () {
    testWidgets(
      'a counter order carried to a table shows the plate "Stol 12" next to its number',
      (WidgetTester tester) async {
        final KdsHarness h = kitchen();
        h.state.addPosOrder(
          type: 'pickup',
          channel: 'pos_takeaway',
          tableLabel: '12',
          lines: const <PosLine>[PosLine('Lag\'mon', '2', station: 2)],
        );
        h.state.addPosOrder(
          type: 'pickup',
          channel: 'pos_takeaway',
          lines: const <PosLine>[PosLine('Somsa', '2', station: 2)],
        );
        await pumpBoard(tester, h);

        // exactly one ticket has a plate
        expect(find.text('Stol 12'), findsOneWidget);
        expect(find.byType(TablePlate), findsOneWidget);
        expect(
          find.descendant(of: card('#P101'), matching: find.byType(TablePlate)),
          findsOneWidget,
        );
        expect(
          find.descendant(of: card('#P102'), matching: find.byType(TablePlate)),
          findsNothing,
        );
      },
    );

    test('a plate that is not a number is shown as written; a table order keeps its table', () {
      expect(TablePlate.text('12'), 'Stol 12');
      expect(TablePlate.text(' A-4 '), 'A-4');
      final KitchenTicket plated = KitchenTicket(
        uuid: 'u',
        status: 'preparing',
        type: 'pickup',
        lines: const <KitchenLine>[],
        waitingSeconds: 0,
        receivedAt: DateTime(2026),
        tableLabel: '12',
      );
      expect(MangalCard.placeLabel(plated), 'Stol 12');
      final KitchenTicket table = KitchenTicket(
        uuid: 'u',
        status: 'preparing',
        type: 'dine_in',
        lines: const <KitchenLine>[],
        waitingSeconds: 0,
        receivedAt: DateTime(2026),
        tableRef: '7',
        tableLabel: '12',
      );
      expect(MangalCard.placeLabel(table), 'Stol 7');
    });
  });

  group('grill timer', () {
    testWidgets(
      'counts the dish\'s grill time down from when it was sent, then shows how late it is (with icon and text)',
      (WidgetTester tester) async {
        final KdsHarness h = kitchen();
        h.state.addPosOrder(
          table: '3',
          lines: const <PosLine>[
            PosLine(
              'Tandir kabob',
              '2',
              station: 1,
              waitMinutes: 12,
              sentAgo: Duration(minutes: 4),
            ),
            PosLine('Non', '2', station: 2),
          ],
        );
        await pumpBoard(tester, h);

        expect(find.text('Mangal 12 daq · 8:00 qoldi'), findsOneWidget);
        expect(
          find.descendant(
            of: find.byType(TicketCard),
            matching: find.byIcon(Icons.local_fire_department_outlined),
          ),
          findsOneWidget,
        );
        // only a line with a grill time has a timer
        expect(find.textContaining('Mangal '), findsOneWidget);

        await advance(tester, h, const Duration(seconds: 5));
        expect(find.text('Mangal 12 daq · 7:55 qoldi'), findsOneWidget);

        await advance(tester, h, const Duration(minutes: 7, seconds: 55));
        expect(find.text('Mangal 12 daq · 0:00 qoldi'), findsOneWidget);

        await advance(tester, h, const Duration(seconds: 70));
        expect(find.text("Mangal 12 daq · vaqti o'tdi +1:10"), findsOneWidget);
        expect(find.byIcon(Icons.warning_amber_rounded), findsWidgets);

        // ready: no timer
        await tester.tap(lineOf('Tandir kabob'));
        await tester.pumpAndSettle();
        expect(find.textContaining('Mangal '), findsNothing);
      },
    );

    test(
      'GrillTimer: needs a grill time, a sent line that is not done or void',
      () {
        final DateTime sent = DateTime(2026, 10, 30, 19);
        final KitchenTicket t = ticketOf(<KitchenLine>[]);
        // the ticket was received at 19:00 with nothing waited yet: server now = 19:00 + elapsed on the device
        final DateTime now = t.receivedAt.add(const Duration(minutes: 5));

        expect(
          GrillTimer.of(t, kl(1, wait: 12, sentAt: sent), now)!.remaining,
          const Duration(minutes: 7),
        );
        expect(GrillTimer.of(t, kl(1, sentAt: sent), now), isNull);
        expect(GrillTimer.of(t, kl(1, wait: 0, sentAt: sent), now), isNull);
        expect(
          GrillTimer.of(t, kl(1, wait: 12), now),
          isNull,
          reason: 'not sent',
        );
        expect(
          GrillTimer.of(t, kl(1, wait: 12, sentAt: sent, status: 'ready'), now),
          isNull,
        );
        expect(
          GrillTimer.of(t, kl(1, wait: 12, sentAt: sent, status: 'void'), now),
          isNull,
        );
        final GrillTimer late = GrillTimer.of(
          t,
          kl(1, wait: 3, sentAt: sent),
          now,
        )!;
        expect(late.overdue, isTrue);
        expect(late.soon, isTrue);
        expect(
          GrillTimerLabel.textOf(late),
          "Mangal 3 daq · vaqti o'tdi +2:00",
        );
      },
    );
  });

  group('mangal view', () {
    const Map<String, Object?> onlyMangal = <String, Object?>{
      'stations_chosen': true,
      'stations_ids': '1',
      'stations_general': false,
    };

    testWidgets(
      'the skewer card keeps courses apart and shows the course mark, the weight badge and the grill timer',
      (WidgetTester tester) async {
        final KdsHarness h = kitchen(stations: onlyMangal);
        h.state.addPosOrder(
          table: 'Xona 2',
          lines: const <PosLine>[
            PosLine("Qo'y", '6', station: 1, course: 1, waitMinutes: 10),
            PosLine("Qo'y", '4', station: 1, course: 2),
            PosLine(
              'Baliq',
              '1.5 kg',
              unit: 'kg',
              station: 1,
              weight: 'awaiting',
            ),
          ],
        );
        await pumpBoard(tester, h);

        // same dish in two courses: two rows, each with its own mark
        expect(find.text("Qo'y"), findsNWidgets(2));
        expect(find.text('1-kurs'), findsOneWidget);
        expect(find.text('2-kurs'), findsOneWidget);
        expect(find.text('6 six'), findsOneWidget);
        expect(find.text('4 six'), findsOneWidget);
        expect(find.text(WeightBadge.label), findsOneWidget);
        expect(find.text('Mangal 10 daq · 9:00 qoldi'), findsOneWidget);
      },
    );

    test(
      'groupSkewers: another course or another weight state is another group',
      () {
        final List<SkewerGroup> groups = groupSkewers(<KitchenLine>[
          kl(1, course: 1, name: "Qo'y"),
          kl(2, course: 1, name: "Qo'y"),
          kl(3, course: 2, name: "Qo'y"),
        ]);
        expect(groups, hasLength(2));
        expect(groups.first.courseNo, 1);
        expect(groups.first.lines, hasLength(2));
        expect(groups.last.courseNo, 2);

        final SkewerGroup fish = groupSkewers(<KitchenLine>[
          kl(4, weight: 'awaiting'),
        ]).single;
        expect(fish.awaitingWeight, isTrue);
      },
    );
  });
}
