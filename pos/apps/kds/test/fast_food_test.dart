import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_kds/src/board/board_view.dart';
import 'package:rizqim_kds/src/board/line_timer.dart';
import 'package:rizqim_kds/src/board/wait_level.dart';
import 'package:rizqim_kds/src/settings/kds_settings.dart';
import 'package:rizqim_kds/src/stations/station_selection.dart';
import 'package:rizqim_kds/src/widgets/compact_ticket_card.dart';
import 'package:rizqim_kds/src/widgets/expo_card.dart';
import 'package:rizqim_kds/src/widgets/target_timer_label.dart';
import 'package:rizqim_kds/src/widgets/ticket_card.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

// P6-33 (KDS v2) and P6-31 (combo context): the fast food kitchen. Stations: 1 grill (target 5:00), 2 fryer (4:00),
// 3 drinks (1:30), 4 barista (3:00), 5 expo (assembly, 2:00).

const int grill = 1;
const int fryer = 2;
const int drinks = 3;
const int barista = 4;
const int expo = 5;

Map<String, Object?> pick(String ids, {bool general = false}) =>
    <String, Object?>{
      'stations_chosen': true,
      'stations_ids': ids,
      'stations_general': general,
    };

KdsHarness fastFood({Map<String, Object?>? stations, bool withExpo = true}) {
  final KdsHarness h = KdsHarness(stationValues: stations);
  h.settings.update(h.settings.value.copyWith(idleTimeout: () => null));
  h.state
    ..addStation(grill, 'Grill', 'grill', target: 300)
    ..addStation(fryer, 'Fritur', 'fryer', target: 240)
    ..addStation(drinks, 'Ichimliklar', 'drinks', target: 90)
    ..addStation(barista, 'Barista', 'barista', target: 180);
  if (withExpo) h.state.addStation(expo, "Yig'ish", 'expo', target: 120);
  return h;
}

Future<void> event(WidgetTester tester) async {
  await tester.pump(const Duration(milliseconds: 400));
  await tester.pumpAndSettle();
}

/// A takeaway order with a queue number cooked on grill, fryer and drinks (goes through the expo).
String expoOrder(
  KdsHarness h, {
  int number = 45,
  Duration age = const Duration(minutes: 2),
  List<PosLine>? lines,
}) {
  final String uuid = h.state.addPosOrder(
    type: 'pickup',
    channel: 'pos_takeaway',
    age: age,
    lines:
        lines ??
        const <PosLine>[
          PosLine('Lavash', '2', station: grill, sentAgo: Duration(minutes: 1)),
          PosLine(
            'Kartoshka fri',
            '1',
            station: fryer,
            sentAgo: Duration(minutes: 1),
          ),
          PosLine(
            'Pepsi 0,4',
            '2',
            station: drinks,
            sentAgo: Duration(minutes: 1),
          ),
        ],
  );
  final Map<String, Object?> order = h.state.orders.last;
  order['queue_number'] = number;
  order['pickup_status'] = 'preparing';
  return uuid;
}

Finder timerText(String text) => find.descendant(
  of: find.byType(TargetTimerLabel),
  matching: find.textContaining(text),
);

void main() {
  group('line timer', () {
    final DateTime base = DateTime.utc(2026, 10, 30, 12);

    KitchenTicket ticket({
      String? sentAt,
      bool late = false,
      int? target = 300,
    }) {
      return KitchenTicket.fromJson(<String, Object?>{
        'uuid': 'u1',
        'status': 'preparing',
        'type': 'pickup',
        'accepted_at': base
            .subtract(const Duration(minutes: 5))
            .toIso8601String(),
        'waiting_seconds': 300,
        'items': <Object?>[
          <String, Object?>{
            'id': 1,
            'product_name': 'Lavash',
            'quantity': 1,
            'unit': 'piece',
            'quantity_label': '1',
            'kitchen_status': 'sent',
            'fire_mode': 'now',
            'station_id': 1,
            'sent_at':
                sentAt ??
                base.subtract(const Duration(minutes: 1)).toIso8601String(),
            'target_seconds': target,
            'late': late,
          },
        ],
      }, receivedAt: base);
    }

    test('counts down to the target; amber at 80 percent; red past it; the server flag wins', () {
      WaitLevel at(Duration d, {bool late = false}) {
        final KitchenTicket t = ticket(late: late);
        return LineTimer.of(t, t.lines.single, base.add(d))!.level;
      }

      // sent 1:00 before base, target 5:00: 80 percent is 4:00 elapsed
      expect(at(Duration.zero), WaitLevel.normal);
      expect(at(const Duration(minutes: 2, seconds: 59)), WaitLevel.normal);
      expect(at(const Duration(minutes: 3)), WaitLevel.warn);
      expect(at(const Duration(minutes: 3, seconds: 59)), WaitLevel.warn);
      expect(at(const Duration(minutes: 4)), WaitLevel.warn);
      expect(at(const Duration(minutes: 4, seconds: 1)), WaitLevel.late);
      // the server said late (its clock is the truth)
      expect(at(Duration.zero, late: true), WaitLevel.late);
    });

    test('text: remaining, then how late', () {
      final KitchenTicket t = ticket();
      final KitchenLine l = t.lines.single;
      expect(LineTimer.of(t, l, base)!.text, 'Maqsad 5:00 · 4:00 qoldi');
      expect(
        LineTimer.of(
          t,
          l,
          base.add(const Duration(minutes: 4, seconds: 15)),
        )!.text,
        'Kechikdi +0:15 (maqsad 5:00)',
      );
    });

    test('no timer without a target, for a done, void or held line', () {
      final KitchenTicket none = ticket(target: null);
      expect(LineTimer.of(none, none.lines.single, base), isNull);
      final KitchenTicket t = ticket();
      expect(
        LineTimer.of(t, t.lines.single.copyWith(status: 'ready'), base),
        isNull,
      );
      expect(
        LineTimer.of(t, t.lines.single.copyWith(status: 'void'), base),
        isNull,
      );
      expect(LineTimer.worstLevel(none, none.lines, base), WaitLevel.normal);
    });

    test('an online line counts from acceptance', () {
      final KitchenTicket t = KitchenTicket.fromJson(<String, Object?>{
        'uuid': 'u2',
        'status': 'accepted',
        'type': 'delivery',
        'accepted_at': base
            .subtract(const Duration(minutes: 4))
            .toIso8601String(),
        'waiting_seconds': 240,
        'items': <Object?>[
          <String, Object?>{
            'id': 1,
            'product_name': 'Burger',
            'quantity': 1,
            'unit': 'piece',
            'quantity_label': '1',
            'kitchen_status': 'pending',
            'target_seconds': 300,
            'late': false,
          },
        ],
      }, receivedAt: base);
      expect(
        LineTimer.of(t, t.lines.single, base)!.elapsed,
        const Duration(minutes: 4),
      );
      expect(LineTimer.of(t, t.lines.single, base)!.level, WaitLevel.warn);
    });
  });

  group('views from the station choice', () {
    List<KitchenStation> stations() => const <KitchenStation>[
      KitchenStation(id: 1, name: 'Grill', kind: StationKind.grill),
      KitchenStation(id: 3, name: 'Ichimliklar', kind: StationKind.drinks),
      KitchenStation(id: 4, name: 'Barista', kind: StationKind.barista),
      KitchenStation(id: 5, name: "Yig'ish", kind: StationKind.expo),
    ];

    StationSelection sel(
      Set<int> ids, {
      bool general = false,
      KitchenViewMode mode = KitchenViewMode.auto,
    }) => StationSelection(
      chosen: true,
      stationIds: ids,
      general: general,
      mode: mode,
    );

    test('drinks and barista together make the compact view; anything else does not', () {
      expect(isCompactView(sel(<int>{3, 4}), stations()), isTrue);
      expect(isCompactView(sel(<int>{3}), stations()), isTrue);
      expect(isCompactView(sel(<int>{1, 3}), stations()), isFalse);
      expect(isCompactView(sel(<int>{3}, general: true), stations()), isFalse);
      expect(
        isCompactView(
          sel(<int>{3}, mode: KitchenViewMode.standard),
          stations(),
        ),
        isFalse,
      );
      expect(
        isCompactView(sel(<int>{1}, mode: KitchenViewMode.compact), stations()),
        isTrue,
      );
      expect(
        isMangalView(sel(<int>{3}, mode: KitchenViewMode.compact), stations()),
        isFalse,
      );
      expect(KitchenViewMode.fromWire('compact'), KitchenViewMode.compact);
    });

    test('the expo is chosen: its ids; only the expo: no ordinary board', () {
      expect(chosenExpoIds(sel(<int>{1, 5}), stations()), <int>{5});
      expect(chosenExpoIds(sel(<int>{1}), stations()), isEmpty);
      expect(isExpoOnly(sel(<int>{5}), stations()), isTrue);
      expect(isExpoOnly(sel(<int>{1, 5}), stations()), isFalse);
      expect(isExpoOnly(sel(<int>{5}, general: true), stations()), isFalse);
    });

    KitchenTicket order(
      String uuid, {
      required bool waiting,
      bool assembled = false,
      int expoId = 5,
    }) => KitchenTicket.fromJson(<String, Object?>{
      'uuid': uuid,
      'status': 'preparing',
      'type': 'pickup',
      'waiting_seconds': 60,
      'items': <Object?>[
        for (final (int id, int st) in <(int, int)>[(1, 1), (2, 3)])
          <String, Object?>{
            'id': id,
            'product_name': 'A$id',
            'quantity': 1,
            'unit': 'piece',
            'quantity_label': '1',
            'kitchen_status': waiting ? 'ready' : 'sent',
            'fire_mode': 'now',
            'station_id': st,
            'sent_at': '2026-10-30T11:58:00Z',
          },
        <String, Object?>{
          'id': 3,
          'product_name': 'Voided',
          'quantity': 1,
          'unit': 'piece',
          'quantity_label': '1',
          'kitchen_status': 'void',
          'fire_mode': 'now',
          'station_id': 1,
          'sent_at': '2026-10-30T11:58:00Z',
        },
      ],
      'assembly': <String, Object?>{
        'station_id': expoId,
        'required': true,
        'waiting': waiting,
        'assembled_at': assembled ? '2026-10-30T11:59:30Z' : null,
        'stations': <Object?>[],
      },
    }, receivedAt: DateTime.utc(2026, 10, 30, 12));

    BoardView build(List<KitchenTicket> tickets, StationSelection s) =>
        buildBoardView(
          tickets: tickets,
          selection: s,
          filter: true,
          stations: stations(),
          settings: const KdsSettings(),
          now: DateTime.utc(2026, 10, 30, 12),
          voidSeen: const <int, DateTime>{},
          voidWindow: const Duration(minutes: 2),
        );

    test('the expo list: unassembled orders of the chosen expo, ready ones first, void lines dropped', () {
      final BoardView v = build(<KitchenTicket>[
        order('cooking', waiting: false),
        order('ready', waiting: true),
        order('done', waiting: true, assembled: true),
        order('other-expo', waiting: true, expoId: 9),
      ], sel(<int>{5}));
      expect(v.hasExpo, isTrue);
      expect(v.expoOnly, isTrue);
      expect(v.active, isEmpty);
      expect(v.expo.map((BoardTicket e) => e.ticket.uuid), <String>[
        'ready',
        'cooking',
      ]);
      expect(v.expo.first.ticket.lines.map((KitchenLine l) => l.id), <int>[
        1,
        2,
      ]);
    });

    test('with the grill chosen too: the grill board keeps its lines and the expo list is separate', () {
      final BoardView v = build(<KitchenTicket>[
        order('o', waiting: false),
      ], sel(<int>{1, 5}));
      expect(v.expoOnly, isFalse);
      expect(v.active.single.ticket.lines.map((KitchenLine l) => l.id), <int>[
        1,
      ]);
      expect(v.expo, hasLength(1));
    });

    test('no expo chosen: no expo list', () {
      final BoardView v = build(<KitchenTicket>[
        order('o', waiting: true),
      ], sel(<int>{1}));
      expect(v.hasExpo, isFalse);
      expect(v.expo, isEmpty);
    });
  });

  group('station picker', () {
    testWidgets('offers the new kinds with their icons, names and targets', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = fastFood();
      await pumpBoard(tester, h);
      expect(find.text("Qaysi sexni ko'rsatamiz?"), findsOneWidget);
      for (final String name in <String>[
        'Grill',
        'Fritur',
        'Ichimliklar',
        'Barista',
        "Yig'ish",
      ]) {
        expect(find.text(name), findsWidgets);
      }
      expect(find.byIcon(Icons.outdoor_grill_outlined), findsOneWidget);
      expect(find.byIcon(Icons.fastfood_outlined), findsOneWidget);
      expect(find.byIcon(Icons.local_drink_outlined), findsOneWidget);
      expect(find.byIcon(Icons.coffee_outlined), findsOneWidget);
      expect(find.byIcon(Icons.takeout_dining_outlined), findsOneWidget);
      // the kind label and the target; the expo says what it does
      expect(find.textContaining('maqsad 5:00'), findsOneWidget);
      expect(
        find.textContaining("ko'p sexli buyurtmalarni yig'adi · maqsad 2:00"),
        findsOneWidget,
      );
    });
  });

  group('timers against the target', () {
    testWidgets(
      'a line counts down, turns amber at 80 percent and red past the target; the ticket follows',
      (WidgetTester tester) async {
        final KdsHarness h = fastFood(stations: pick('$grill'));
        h.state.addPosOrder(
          type: 'pickup',
          channel: 'pos_takeaway',
          lines: const <PosLine>[
            PosLine(
              'Lavash',
              '2',
              station: grill,
              sentAgo: Duration(minutes: 3),
            ),
            PosLine(
              'Burger',
              '1',
              station: grill,
              sentAgo: Duration(seconds: 30),
              target: 600,
            ),
          ],
        );
        await pumpBoard(tester, h);

        expect(find.byType(TicketCard), findsOneWidget);
        // Lavash: 3:00 of 5:00 gone; Burger has its own 10:00 target
        expect(timerText('Maqsad 5:00 · 2:00 qoldi'), findsOneWidget);
        expect(timerText('Maqsad 10:00 · 9:30 qoldi'), findsOneWidget);
        expect(find.text('Shoshiling'), findsNothing);
        expect(find.text('Kechikdi'), findsNothing);

        await advance(tester, h, const Duration(minutes: 1));
        // 4:00 of 5:00: amber (icon and word, not colour alone), the ticket warns too
        expect(timerText('Maqsad 5:00 · 1:00 qoldi'), findsOneWidget);
        expect(
          find.descendant(
            of: find.byType(TargetTimerLabel),
            matching: find.byIcon(Icons.hourglass_bottom_rounded),
          ),
          findsOneWidget,
        );
        expect(find.text('Shoshiling'), findsOneWidget);

        await advance(tester, h, const Duration(minutes: 1, seconds: 15));
        expect(timerText('Kechikdi +0:15 (maqsad 5:00)'), findsOneWidget);
        expect(
          find.descendant(
            of: find.byType(TargetTimerLabel),
            matching: find.byIcon(Icons.warning_amber_rounded),
          ),
          findsOneWidget,
        );
        expect(find.text('Kechikdi'), findsOneWidget);
        // The burger is still within its own target
        expect(timerText('Maqsad 10:00 · '), findsOneWidget);
      },
    );

    testWidgets(
      'the server late flag shows red at once; a ready line has no timer; a line without target none',
      (WidgetTester tester) async {
        final KdsHarness h = fastFood(stations: pick('$grill'));
        h.state.addPosOrder(
          type: 'pickup',
          channel: 'pos_takeaway',
          lines: const <PosLine>[
            PosLine(
              'Lavash',
              '2',
              station: grill,
              sentAgo: Duration(minutes: 6),
            ),
            PosLine(
              'Tovuq',
              '1',
              station: grill,
              sentAgo: Duration(minutes: 6),
              status: 'ready',
            ),
          ],
        );
        h.state.addStation(9, 'Sexsiz', 'other');
        h.state.addPosOrder(
          lines: const <PosLine>[
            PosLine(
              'Choy',
              '1',
              station: grill,
              sentAgo: Duration(minutes: 1),
              target: 60,
            ),
          ],
        );
        await pumpBoard(tester, h);
        // Lavash (6:00 of 5:00) and the second order's tea (1:00 of 1:00 is not yet late)
        expect(timerText('Kechikdi +1:00 (maqsad 5:00)'), findsOneWidget);
        // the ready chicken shows none: only the two cooking lines have a timer
        expect(
          find.byWidgetPredicate(
            (Widget w) =>
                w.key is ValueKey<String> &&
                (w.key! as ValueKey<String>).value.startsWith('target-timer-'),
          ),
          findsNWidgets(2),
        );
      },
    );
  });

  group('compact view for drinks and barista', () {
    testWidgets(
      'many small tickets fit; tapping a line bumps it; the mode can be switched',
      (WidgetTester tester) async {
        final KdsHarness h = fastFood(stations: pick('$drinks,$barista'));
        for (var i = 0; i < 8; i++) {
          h.state.addPosOrder(
            type: 'pickup',
            channel: 'pos_takeaway',
            age: Duration(seconds: 20 + i),
            lines: <PosLine>[
              PosLine(
                'Latte',
                '1',
                station: barista,
                mods: const <String>['Sutsiz'],
                sentAgo: Duration(seconds: 20 + i),
              ),
              if (i.isEven) const PosLine('Kola 0,5', '2', station: drinks),
            ],
          );
        }
        await pumpBoard(tester, h);
        expect(find.byType(CompactTicketCard), findsNWidgets(8));
        expect(find.byType(TicketCard), findsNothing);
        // more than three columns on a tablet (the ordinary card allows three)
        final Set<double> lefts = <double>{
          for (final Element e in find.byType(CompactTicketCard).evaluate())
            (e.renderObject! as RenderBox)
                .localToGlobal(Offset.zero)
                .dx
                .roundToDouble(),
        };
        expect(lefts.length, greaterThanOrEqualTo(4));
        // the modifier is on the line, comma separated
        expect(find.text('Sutsiz'), findsWidgets);

        await tester.tap(find.text('Latte').first);
        await tester.pumpAndSettle();
        expect(h.backend.to('POST', '/ready'), hasLength(1));

        // back to the ordinary tickets
        await tester.tap(find.text('Oddiy'));
        await tester.pumpAndSettle();
        expect(find.byType(TicketCard), findsWidgets);
        expect(find.byType(CompactTicketCard), findsNothing);
        await tester.tap(find.text('Ixcham'));
        await tester.pumpAndSettle();
        expect(find.byType(CompactTicketCard), findsWidgets);
      },
    );

    testWidgets('a late drink turns its small ticket red', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = fastFood(stations: pick('$barista'));
      h.state.addPosOrder(
        type: 'pickup',
        channel: 'pos_takeaway',
        lines: const <PosLine>[
          PosLine(
            'Kapuchino',
            '1',
            station: barista,
            sentAgo: Duration(minutes: 4),
          ),
        ],
      );
      await pumpBoard(tester, h);
      expect(timerText('Kechikdi +1:00 (maqsad 3:00)'), findsOneWidget);
      expect(
        find.descendant(
          of: find.byType(CompactTicketCard),
          matching: find.byIcon(Icons.warning_amber_rounded),
        ),
        findsWidgets,
      );
    });
  });

  group('expo screen', () {
    testWidgets(
      'shows each station progress; Yig\'ildi is off until all are ready; then it assembles the order',
      (WidgetTester tester) async {
        final KdsHarness h = fastFood(stations: pick('$expo'));
        final String uuid = expoOrder(h);
        // a single-station order never reaches the expo
        h.state.addPosOrder(
          type: 'pickup',
          channel: 'pos_takeaway',
          lines: const <PosLine>[PosLine('Choy', '1', station: drinks)],
        );
        await pumpBoard(tester, h);

        // only the expo section: no ordinary board tab
        expect(find.text('Buyurtmalar'), findsNothing);
        expect(find.textContaining("Yig'ish (1)"), findsOneWidget);
        expect(find.byType(ExpoCard), findsOneWidget);
        expect(find.byKey(const ValueKey<String>('expo-id')), findsOneWidget);
        expect(find.text('№45'), findsOneWidget);
        for (final String s in <String>['Grill', 'Fritur', 'Ichimliklar']) {
          expect(find.text(s), findsOneWidget);
        }
        expect(find.text('0/1'), findsNWidgets(3));
        expect(find.textContaining('2× Lavash'), findsOneWidget);
        // not ready: the button is disabled and the hint tells why
        RzButton button() => tester.widget<RzButton>(
          find.byKey(const ValueKey<String>('expo-assembled')),
        );
        expect(button().onPressed, isNull);
        expect(
          find.text("Hamma sexlar tayyor bo'lishini kuting."),
          findsOneWidget,
        );

        // the stations bump their lines (another screen); the events refresh this one
        h.state.bumpStation(uuid, grill);
        h.state.bumpStation(uuid, fryer);
        await event(tester);
        expect(find.text('1/1'), findsNWidgets(2));
        expect(button().onPressed, isNull);

        h.state.bumpStation(uuid, drinks);
        await event(tester);
        expect(button().onPressed, isNotNull);
        expect(
          find.text("Hamma sexlar tayyor bo'lishini kuting."),
          findsNothing,
        );

        await tester.tap(find.byKey(const ValueKey<String>('expo-assembled')));
        await tester.pumpAndSettle();
        final Iterable<RequestOptions> posts = h.backend.to(
          'POST',
          '/assembled',
        );
        expect(posts, hasLength(1));
        expect(
          posts.single.uri.path,
          endsWith('/v1/staff/orders/$uuid/assembled'),
        );
        expect(posts.single.headers['Idempotency-Key'], isNotNull);
        expect(h.state.assembledLog, <String>[uuid]);
        // the number is called (the customer display gets queue.ready from the server)
        expect(
          h.state.orders.firstWhere((o) => o['uuid'] == uuid)['pickup_status'],
          'ready',
        );
        // and the order left the expo screen
        expect(find.byType(ExpoCard), findsNothing);
        expect(find.text("Yig'ishga buyurtma yo'q"), findsOneWidget);
      },
    );

    testWidgets(
      'an assembly done on another expo screen removes the order here (kitchen.order_assembled)',
      (WidgetTester tester) async {
        final KdsHarness h = fastFood(stations: pick('$expo'));
        final String uuid = expoOrder(
          h,
          lines: const <PosLine>[
            PosLine('Lavash', '1', station: grill, status: 'ready'),
            PosLine('Pepsi', '1', station: drinks, status: 'ready'),
          ],
        );
        await pumpBoard(tester, h);
        expect(find.byType(ExpoCard), findsOneWidget);
        h.state.assembleOrder(uuid);
        await event(tester);
        expect(find.byType(ExpoCard), findsNothing);
      },
    );

    testWidgets(
      'a counter order that stays on the board after assembly returns to the expo when a new line undoes it',
      (WidgetTester tester) async {
        final KdsHarness h = fastFood(stations: pick('$expo'));
        final String uuid = h.state.addPosOrder(
          type: 'dine_in',
          channel: 'pos_hall',
          lines: const <PosLine>[
            PosLine('Lavash', '1', station: grill, status: 'ready'),
            PosLine('Pepsi', '1', station: drinks, status: 'ready'),
          ],
        );
        h.state.orders.last
          ..['queue_number'] = 50
          ..['pickup_status'] = 'preparing';
        await pumpBoard(tester, h);
        expect(find.byType(ExpoCard), findsOneWidget);

        await tester.tap(find.byKey(const ValueKey<String>('expo-assembled')));
        await tester.pumpAndSettle();
        await event(tester);
        expect(h.state.assembledLog, <String>[uuid]);
        expect(find.byType(ExpoCard), findsNothing);

        // The guest added a dish: the backend undoes the assembly (assembled_at cleared) and the expo shows it again
        h.state.orders.last['assembled_at'] = null;
        h.state.addLines(uuid, const <PosLine>[
          PosLine('Burger', '1', station: grill),
        ]);
        await event(tester);
        expect(find.byType(ExpoCard), findsOneWidget);
        expect(find.text('1/2'), findsOneWidget);
      },
    );

    testWidgets(
      'the server refuses (stations_not_ready): the order stays and the message shows',
      (WidgetTester tester) async {
        final KdsHarness h = fastFood(stations: pick('$expo'));
        expoOrder(
          h,
          lines: const <PosLine>[
            PosLine('Lavash', '1', station: grill, status: 'ready'),
            PosLine('Pepsi', '1', station: drinks, status: 'ready'),
          ],
        );
        await pumpBoard(tester, h);
        h.backend.respond = (RequestOptions o) =>
            o.uri.path.endsWith('/assembled')
            ? const FakeResponse(422, <String, Object?>{
                'success': false,
                'message': 'Hamma sexlar hali tayyor emas: yig\'ishdan oldin ularni kuting.',
                'data': <String, Object?>{'reason': 'stations_not_ready'},
              })
            : null;
        await tester.tap(find.byKey(const ValueKey<String>('expo-assembled')));
        await tester.pumpAndSettle();
        expect(
          find.textContaining('Hamma sexlar hali tayyor emas'),
          findsOneWidget,
        );
        expect(find.byType(ExpoCard), findsOneWidget);
      },
    );

    testWidgets(
      'ready orders come first; assembly time runs against the expo target and turns red',
      (WidgetTester tester) async {
        final KdsHarness h = fastFood(stations: pick('$expo'));
        expoOrder(h, number: 11, age: const Duration(minutes: 3));
        expoOrder(
          h,
          number: 12,
          lines: const <PosLine>[
            PosLine('Lavash', '1', station: grill, status: 'ready'),
            PosLine('Pepsi', '1', station: drinks, status: 'ready'),
          ],
        );
        await pumpBoard(tester, h);
        final List<String> ids = <String>[
          for (final Element e
              in find.byKey(const ValueKey<String>('expo-id')).evaluate())
            (e.widget as Text).data!,
        ];
        expect(ids, <String>['№12', '№11']);
        // all stations done 0:00 ago, target 2:00
        expect(
          find.textContaining("Yig'ish: Maqsad 2:00 · 2:00 qoldi"),
          findsOneWidget,
        );

        await advance(tester, h, const Duration(seconds: 100));
        expect(
          find.textContaining("Yig'ish: Maqsad 2:00 · 0:20 qoldi"),
          findsOneWidget,
        );
        expect(find.text('Shoshiling'), findsWidgets);

        await advance(tester, h, const Duration(seconds: 30));
        expect(find.textContaining("Yig'ish: Kechikdi +0:10"), findsOneWidget);
        expect(find.text('Kechikdi'), findsWidgets);
      },
    );

    testWidgets(
      'a station that falls behind is named on the expo card; the combo name groups its parts',
      (WidgetTester tester) async {
        final KdsHarness h = fastFood(stations: pick('$expo'));
        expoOrder(
          h,
          lines: const <PosLine>[
            PosLine(
              'Lavash katta',
              '1',
              station: grill,
              sentAgo: Duration(minutes: 6),
              combo: 'Lavash kombo',
              comboId: 9,
            ),
            PosLine(
              'Pepsi 0,4',
              '1',
              station: drinks,
              status: 'ready',
              combo: 'Lavash kombo',
              comboId: 9,
            ),
          ],
        );
        await pumpBoard(tester, h);
        final Finder grillBlock = find.byKey(
          const ValueKey<String>('expo-station-$grill'),
        );
        expect(
          find.descendant(of: grillBlock, matching: find.text('Kechikdi')),
          findsOneWidget,
        );
        expect(find.text('Kombo: Lavash kombo'), findsNWidgets(2));
      },
    );

    testWidgets(
      'with the grill and the expo both chosen the tabs are Buyurtmalar and Yig\'ish',
      (WidgetTester tester) async {
        final KdsHarness h = fastFood(stations: pick('$grill,$expo'));
        expoOrder(h);
        await pumpBoard(tester, h);
        expect(find.text('Buyurtmalar (1)'), findsOneWidget);
        expect(find.textContaining("Yig'ish (1)"), findsOneWidget);
        expect(find.byType(TicketCard), findsOneWidget);
        expect(find.byType(ExpoCard), findsNothing);
        await tester.tap(find.textContaining("Yig'ish (1)"));
        await tester.pumpAndSettle();
        expect(find.byType(ExpoCard), findsOneWidget);
        expect(find.byType(TicketCard), findsNothing);
      },
    );

    testWidgets('without kitchen.manage the expo card is only a display', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = fastFood(stations: pick('$expo'));
      h.state.permissions = <String>['orders.view'];
      expoOrder(
        h,
        lines: const <PosLine>[
          PosLine('Lavash', '1', station: grill, status: 'ready'),
          PosLine('Pepsi', '1', station: drinks, status: 'ready'),
        ],
      );
      await pumpBoard(tester, h);
      expect(find.byType(ExpoCard), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('expo-assembled')),
        findsNothing,
      );
    });

    testWidgets(
      'a branch without an expo station keeps the ordinary board only',
      (WidgetTester tester) async {
        final KdsHarness h = fastFood(
          stations: pick('$grill,$drinks'),
          withExpo: false,
        );
        final String uuid = expoOrder(h);
        await pumpBoard(tester, h);
        // no Yig'ish section, no expo card; the stations work their lines as before
        expect(find.textContaining("Yig'ish"), findsNothing);
        expect(find.byType(ExpoCard), findsNothing);
        expect(find.byType(TicketCard), findsOneWidget);
        h.state.bumpStation(uuid, grill);
        await event(tester);
        expect(find.byType(TicketCard), findsOneWidget);
        expect(find.byType(ExpoCard), findsNothing);
      },
    );
  });
}
