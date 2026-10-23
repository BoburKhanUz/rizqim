import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_kds/src/widgets/line_marks.dart';
import 'package:rizqim_kds/src/widgets/mangal_card.dart';
import 'package:rizqim_kds/src/widgets/ticket_card.dart';

import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

/// Sexlar: 1 mangal, 2 osh, 3 salat.
KdsHarness posKitchen({
  Map<String, Object?>? stationValues,
  bool realtime = true,
}) {
  final KdsHarness h = KdsHarness(
    realtime: realtime,
    stationValues: stationValues,
  );
  // Auto sign-out off: some tests run the fake clock for half an hour
  h.settings.update(h.settings.value.copyWith(idleTimeout: () => null));
  h.state
    ..addStation(1, 'Mangal', 'mangal')
    ..addStation(2, 'Osh', 'osh')
    ..addStation(3, 'Salat', 'salad_bar')
    ..addStation(4, 'Yashirin', 'other', showOnKds: false);
  return h;
}

/// Hamma sex va "Umumiy" tanlangan (tanlash ekrani chiqmaydi).
const Map<String, Object?> allStations = <String, Object?>{
  'stations_chosen': true,
  'stations_ids': '1,2,3',
  'stations_general': true,
};

const Map<String, Object?> onlyMangal = <String, Object?>{
  'stations_chosen': true,
  'stations_ids': '1',
  'stations_general': false,
};

Future<void> event(WidgetTester tester) async {
  await tester.pump(const Duration(milliseconds: 400));
  await tester.pumpAndSettle();
}

Finder card(String code) =>
    find.ancestor(of: find.text(code), matching: find.byType(TicketCard));

void main() {
  group('stations', () {
    testWidgets(
      'after the PIN the cook picks stations (only those shown on KDS), the board filters by them and the header names them',
      (WidgetTester tester) async {
        final KdsHarness h = posKitchen();
        h.state.addPosOrder(
          lines: const <PosLine>[
            PosLine("Qo'y shashlik", '4', station: 1),
            PosLine('Osh', '1.5 kg', unit: 'kg', station: 2),
            PosLine('Salat "Achichuk"', '2', station: 3),
          ],
        );
        h.state.addOrder(
          items: const <(String, String, String, List<String>)>[
            ('Choy', '2', 'pcs', <String>[]),
          ],
        );
        await pumpBoard(tester, h);

        expect(find.text("Qaysi sexni ko'rsatamiz?"), findsOneWidget);
        expect(find.byKey(const ValueKey<String>('station-1')), findsOneWidget);
        expect(find.byKey(const ValueKey<String>('station-3')), findsOneWidget);
        // show_on_kds = false: not offered
        expect(find.text('Yashirin'), findsNothing);
        expect(find.text('Umumiy'), findsOneWidget);
        // nothing chosen yet: cannot continue
        expect(
          tester.widget<RzButton>(buttonLabeled('Davom etish')).onPressed,
          isNull,
        );

        await tester.tap(find.byKey(const ValueKey<String>('station-1')));
        await tester.tap(find.byKey(const ValueKey<String>('station-2')));
        await tester.pump();
        await tester.tap(buttonLabeled('Davom etish'));
        await tester.pumpAndSettle();

        expect(find.text("Qo'y shashlik"), findsOneWidget);
        expect(find.text('Osh'), findsOneWidget);
        expect(find.textContaining('Achichuk'), findsNothing);
        expect(find.text('Choy'), findsNothing, reason: 'not in Umumiy');
        expect(find.text('Sex: Mangal, Osh'), findsOneWidget);
        expect(h.stationStore.values['stations_ids'], '1,2');
        expect(h.stationStore.values['stations_chosen'], isTrue);
        // header counts only the lines of the chosen stations
        expect(find.textContaining('0/2 tayyor'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );

    testWidgets(
      'lines without a station show only on the "Umumiy" choice; the choice can be changed from the header',
      (WidgetTester tester) async {
        final KdsHarness h = posKitchen(stationValues: onlyMangal);
        h.state.addPosOrder(
          lines: const <PosLine>[PosLine("Qo'y shashlik", '4', station: 1)],
        );
        h.state.addOrder(
          items: const <(String, String, String, List<String>)>[
            ('Choy', '2', 'pcs', <String>[]),
          ],
        );
        await pumpBoard(tester, h);
        // remembered: straight to the board
        expect(find.text("Qaysi sexni ko'rsatamiz?"), findsNothing);
        expect(find.text("Qo'y shashlik"), findsOneWidget);
        expect(find.text('Choy'), findsNothing);
        expect(find.text('1 ta chipta'), findsOneWidget);

        await tester.tap(find.byKey(const ValueKey<String>('stations-button')));
        await tester.pumpAndSettle();
        expect(find.text("Qaysi sexni ko'rsatamiz?"), findsOneWidget);
        await tester.tap(find.byKey(const ValueKey<String>('station-general')));
        await tester.pump();
        await tester.tap(buttonLabeled('Davom etish'));
        await tester.pumpAndSettle();

        expect(find.text('Choy'), findsOneWidget);
        expect(find.text('Sex: Mangal, Umumiy'), findsOneWidget);
        expect(find.text('2 ta chipta'), findsOneWidget);
        expect(h.stationStore.values['stations_general'], isTrue);
      },
    );

    testWidgets('the choice is remembered on the device across app starts', (
      WidgetTester tester,
    ) async {
      final KdsHarness first = posKitchen();
      first.state.addPosOrder(
        lines: const <PosLine>[PosLine('Osh', '1', station: 2)],
      );
      await pumpBoard(tester, first);
      await tester.tap(find.byKey(const ValueKey<String>('station-2')));
      await tester.pump();
      await tester.tap(buttonLabeled('Davom etish'));
      await tester.pumpAndSettle();
      final Map<String, Object?> saved = Map<String, Object?>.of(
        first.stationStore.values,
      );
      expect(saved, containsPair('stations_ids', '2'));

      // a new start reads the saved values
      await tester.pumpWidget(const SizedBox());
      final KdsHarness second = posKitchen(stationValues: saved);
      second.state.addPosOrder(
        lines: const <PosLine>[PosLine('Osh', '1', station: 2)],
      );
      await pumpBoard(tester, second);
      expect(find.text("Qaysi sexni ko'rsatamiz?"), findsNothing);
      expect(find.text('Sex: Osh'), findsOneWidget);
    });

    testWidgets(
      'a server without stations (404) shows every ticket, no picker',
      (WidgetTester tester) async {
        final KdsHarness h = KdsHarness();
        h.state.addOrder();
        await pumpBoard(tester, h);
        expect(find.text("Qaysi sexni ko'rsatamiz?"), findsNothing);
        expect(find.text('1 ta chipta'), findsOneWidget);
        expect(
          find.byKey(const ValueKey<String>('stations-button')),
          findsNothing,
        );
      },
    );
  });

  group('mangal', () {
    testWidgets(
      'only mangal stations chosen: skewers are grouped by type per round, with room and round on the card',
      (WidgetTester tester) async {
        final KdsHarness h = posKitchen(stationValues: onlyMangal);
        h.state.addPosOrder(
          table: 'Xona 3',
          lines: const <PosLine>[
            PosLine("Qo'y shashlik", '4', station: 1, note: 'achchiq'),
            PosLine("Qo'y shashlik", '2', station: 1),
            PosLine('Tovuq shashlik', '4', station: 1),
            PosLine("Qo'y shashlik", '3', station: 1, round: 2),
          ],
        );
        await pumpBoard(tester, h);

        expect(find.byType(MangalCard), findsOneWidget);
        expect(find.byType(TicketCard), findsNothing);
        expect(find.text('Xona 3'), findsOneWidget);
        // round 1: 4 + 2 lamb in one row, chicken; round 2 on its own
        expect(find.text('6 six'), findsOneWidget);
        expect(find.text('4 six'), findsOneWidget);
        expect(find.text('3 six'), findsOneWidget);
        expect(find.text("Qo'y shashlik"), findsNWidgets(2));
        expect(find.text('1-bosqich'), findsWidgets);
        expect(find.text('2-bosqich'), findsWidgets);
        expect(find.text('Jami: 13 six'), findsOneWidget);
        expect(find.text('achchiq'), findsOneWidget);
        expect(tester.takeException(), isNull);

        // the group bumps all its lines at once
        await tester.tap(find.text('6 six'));
        await tester.pumpAndSettle();
        expect(h.backend.to('POST', '/ready').length, 2);
        expect(find.text('Jami: 7 six'), findsOneWidget);
      },
    );

    testWidgets(
      'the wait colours follow the oldest sent line, not the whole room session',
      (WidgetTester tester) async {
        final KdsHarness h = posKitchen(stationValues: onlyMangal);
        h.state.addPosOrder(
          age: const Duration(hours: 3),
          lines: const <PosLine>[
            PosLine(
              "Qo'y shashlik",
              '4',
              station: 1,
              sentAgo: Duration(minutes: 3),
            ),
          ],
        );
        await pumpBoard(tester, h);
        expect(find.text('3:00'), findsOneWidget);
        expect(find.text('Kechikdi'), findsNothing);
        await advance(tester, h, const Duration(minutes: 8));
        expect(find.text('Shoshiling'), findsOneWidget);
        await advance(tester, h, const Duration(minutes: 10));
        expect(find.text('Kechikdi'), findsOneWidget);
      },
    );

    testWidgets('the view toggle switches between mangal and normal cards', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = posKitchen(stationValues: onlyMangal);
      h.state.addPosOrder(
        lines: const <PosLine>[PosLine("Qo'y shashlik", '4', station: 1)],
      );
      await pumpBoard(tester, h);
      expect(find.byType(MangalCard), findsOneWidget);
      await tester.tap(find.text('Oddiy'));
      await tester.pumpAndSettle();
      expect(find.byType(MangalCard), findsNothing);
      expect(find.byType(TicketCard), findsOneWidget);
      expect(h.stationStore.values['view_mode'], 'standard');
      await tester.tap(find.text('Mangal').last);
      await tester.pumpAndSettle();
      expect(find.byType(MangalCard), findsOneWidget);
    });

    testWidgets('mixed stations stay on the normal view by default', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = posKitchen(stationValues: allStations);
      h.state.addPosOrder(
        lines: const <PosLine>[PosLine("Qo'y shashlik", '4', station: 1)],
      );
      await pumpBoard(tester, h);
      expect(find.byType(TicketCard), findsOneWidget);
      expect(find.byType(MangalCard), findsNothing);
    });
  });

  group('pre-orders', () {
    Future<KdsHarness> queued(WidgetTester tester) async {
      final KdsHarness h = posKitchen(stationValues: allStations);
      h.state.addPosOrder(
        table: 'Xona 5',
        lines: const <PosLine>[
          PosLine(
            'Toy oshi',
            '5 kg',
            unit: 'kg',
            station: 2,
            status: 'pending',
            fire: 'hold',
            dueIn: Duration(hours: 3),
          ),
          PosLine(
            "Qo'y kabob",
            '10',
            station: 1,
            status: 'pending',
            fire: 'hold',
            dueIn: Duration(hours: 1),
          ),
          PosLine(
            "Sho'rva",
            '4',
            station: 2,
            status: 'pending',
            fire: 'hold',
            dueIn: Duration(hours: 2, minutes: 10),
          ),
        ],
      );
      await pumpBoard(tester, h);
      return h;
    }

    testWidgets(
      'held lines with a due time wait in their own tab, soonest first, with a countdown',
      (WidgetTester tester) async {
        final KdsHarness h = await queued(tester);
        expect(find.text('0 ta chipta'), findsOneWidget);
        expect(find.textContaining('Oldindan buyurtmalar (3)'), findsOneWidget);
        expect(find.text('Toy oshi'), findsNothing, reason: 'not on the board');

        await tester.tap(find.textContaining('Oldindan buyurtmalar'));
        await tester.pumpAndSettle();
        final double yKabob = tester.getTopLeft(find.text("Qo'y kabob")).dy;
        final double yShorva = tester.getTopLeft(find.text("Sho'rva")).dy;
        final double yOsh = tester.getTopLeft(find.text('Toy oshi')).dy;
        expect(yKabob < yShorva && yShorva < yOsh, isTrue);
        expect(find.text('1 soat'), findsOneWidget);
        expect(find.text('2 soat 10 daq'), findsOneWidget);
        expect(find.text('3 soat'), findsOneWidget);
        expect(find.text('Xona 5'), findsNWidgets(3));
        // when each one moves to the board: due minus the lead of its station kind
        final DateTime base = h.now;
        expect(
          find.text('Doskada: ${_hm(base.add(const Duration(minutes: 30)))}'),
          findsOneWidget,
        );

        await advance(tester, h, const Duration(seconds: 30));
        expect(find.text('59:30'), findsNothing);
        expect(find.text('59 daq'), findsOneWidget);
      },
    );

    testWidgets(
      'a line moves to the active board when its lead time comes: 120 min for osh, 30 min for mangal (fake clock)',
      (WidgetTester tester) async {
        final KdsHarness h = await queued(tester);

        // soup is due in 2:10, osh lead 120 min: on the board after 10 min
        await advance(tester, h, const Duration(minutes: 9));
        expect(find.textContaining('Oldindan buyurtmalar (3)'), findsOneWidget);
        await advance(tester, h, const Duration(minutes: 2));
        expect(find.textContaining('Oldindan buyurtmalar (2)'), findsOneWidget);
        expect(find.text("Sho'rva"), findsOneWidget);
        expect(find.textContaining('Muddat '), findsOneWidget);
        expect(find.text('1 ta chipta'), findsOneWidget);
        expect(find.text("Qo'y kabob"), findsNothing);

        // lamb is due in 1 h, mangal lead 30 min: after 30 min total
        await advance(tester, h, const Duration(minutes: 18));
        expect(find.text("Qo'y kabob"), findsNothing);
        await advance(tester, h, const Duration(minutes: 2));
        expect(find.text("Qo'y kabob"), findsOneWidget);
        expect(find.textContaining('Oldindan buyurtmalar (1)'), findsOneWidget);
      },
    );

    testWidgets('lead times are configurable in the settings', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = await queued(tester);
      expect(find.text("Qo'y kabob"), findsNothing);
      h.settings.update(
        h.settings.value.copyWith(leadMangal: const Duration(minutes: 60)),
      );
      await tester.pumpAndSettle();
      expect(find.text("Qo'y kabob"), findsOneWidget);
      expect(find.textContaining('Oldindan buyurtmalar (2)'), findsOneWidget);
      h.settings.update(
        h.settings.value.copyWith(leadOsh: const Duration(hours: 4)),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('Oldindan buyurtmalar (0)'), findsOneWidget);
      expect(find.text('Toy oshi'), findsOneWidget);
    });

    testWidgets(
      'a held pre-order is marked ready before it is sent (P3-26: ready implies sent)',
      (WidgetTester tester) async {
        final KdsHarness h = posKitchen(stationValues: allStations);
        h.state.addPosOrder(
          lines: const <PosLine>[
            PosLine(
              'Toy oshi',
              '5 kg',
              unit: 'kg',
              station: 2,
              status: 'pending',
              fire: 'hold',
              dueIn: Duration(minutes: 20),
            ),
          ],
        );
        await pumpBoard(tester, h);
        // within the lead: on the board, "not sent" yet, but the cook may finish it now
        expect(find.text('Toy oshi'), findsOneWidget);
        expect(find.textContaining('hali yuborilmagan'), findsOneWidget);
        await tester.tap(find.text('Toy oshi'));
        await tester.pumpAndSettle();
        expect(h.backend.to('POST', '/ready').length, 1);
        final Map<String, Object?> item =
            (h.state.orders.single['items']! as List<Object?>).single!
                as Map<String, Object?>;
        expect(item['kitchen_status'], 'ready');
        expect(item['sent_at'], isNotNull);
      },
    );

    testWidgets('a pre-order in the queue tab has its own "Tayyor"', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = await queued(tester);
      await tester.tap(find.textContaining('Oldindan buyurtmalar'));
      await tester.pumpAndSettle();
      final Map<String, Object?> osh =
          (h.state.orders.single['items']! as List<Object?>).first!
              as Map<String, Object?>;
      await tester.tap(
        find.byKey(ValueKey<String>('preorder-ready-${osh['id']}')),
      );
      await tester.pumpAndSettle();
      expect(h.backend.to('POST', '/items/${osh['id']}/ready').length, 1);
      expect(osh['kitchen_status'], 'ready');
      expect(find.textContaining('Oldindan buyurtmalar (2)'), findsOneWidget);
    });

    testWidgets(
      'a line sent early leaves the queue at once and shows on the board',
      (WidgetTester tester) async {
        final KdsHarness h = await queued(tester);
        final List<Object?> items =
            h.state.orders.single['items']! as List<Object?>;
        final int ohsId = (items.first! as Map<String, Object?>)['id']! as int;
        h.state.sendLine(h.state.orders.single['uuid']! as String, ohsId);
        await event(tester);
        expect(find.text('Toy oshi'), findsOneWidget);
        expect(find.textContaining('Oldindan buyurtmalar (2)'), findsOneWidget);
      },
    );

    testWidgets('an empty queue explains itself', (WidgetTester tester) async {
      final KdsHarness h = posKitchen(stationValues: allStations);
      h.state.addPosOrder(
        lines: const <PosLine>[PosLine('Osh', '1', station: 2)],
      );
      await pumpBoard(tester, h);
      await tester.tap(find.textContaining('Oldindan buyurtmalar'));
      await tester.pumpAndSettle();
      expect(find.text("Oldindan buyurtma yo'q"), findsOneWidget);
    });
  });

  group('void', () {
    testWidgets(
      'a voided line is struck through with a red badge and the reason, plays the cue, and disappears after a short time',
      (WidgetTester tester) async {
        final KdsHarness h = posKitchen(stationValues: allStations);
        final String uuid = h.state.addPosOrder(
          lines: const <PosLine>[
            PosLine("Qo'y shashlik", '4', station: 1),
            PosLine('Tovuq shashlik', '2', station: 1),
          ],
        );
        await pumpBoard(tester, h);
        expect(find.text(VoidBadge.label), findsNothing);
        expect(h.sound.voidPlays, 0);

        final int id =
            ((h.state.orders.single['items']! as List<Object?>).first!
                    as Map<String, Object?>)['id']!
                as int;
        h.state.voidLine(uuid, id, reason: 'Mehmon xohlamadi');
        await event(tester);

        expect(find.text(VoidBadge.label), findsOneWidget);
        expect(find.text('Sabab: Mehmon xohlamadi'), findsOneWidget);
        expect(h.sound.voidPlays, 1);
        expect(h.sound.plays, 0, reason: 'a void is not a new ticket');
        final Text title = tester.widget<Text>(find.text("Qo'y shashlik"));
        expect(title.style?.decoration, TextDecoration.lineThrough);
        // the other line is untouched and still bumpable; the voided one is not counted
        expect(find.textContaining('0/1 tayyor'), findsOneWidget);
        await tester.tap(find.text("Qo'y shashlik"));
        await tester.pumpAndSettle();
        expect(h.backend.to('POST', '/ready'), isEmpty);

        await advance(tester, h, const Duration(minutes: 1));
        expect(find.text(VoidBadge.label), findsOneWidget);
        await advance(tester, h, const Duration(minutes: 1, seconds: 5));
        expect(find.text(VoidBadge.label), findsNothing);
        expect(find.text("Qo'y shashlik"), findsNothing);
        expect(find.text('Tovuq shashlik'), findsOneWidget);
      },
    );

    testWidgets(
      'a cancelled order leaves the ticket list but its voided ticket stays for a short time',
      (WidgetTester tester) async {
        final KdsHarness h = posKitchen(stationValues: allStations);
        final String uuid = h.state.addPosOrder(
          lines: const <PosLine>[
            PosLine("Qo'y shashlik", '4', station: 1),
            PosLine('Osh', '1', station: 2),
          ],
        );
        await pumpBoard(tester, h);
        expect(find.text('1 ta chipta'), findsOneWidget);

        h.state.cancelOrder(uuid, reason: 'Mehmonlar ketdi');
        await event(tester);

        // P3-26: the tickets feed brings it (canceled_within); no lookup per ticket
        expect(h.backend.to('GET', '/v1/staff/orders/$uuid'), isEmpty);
        expect(
          h.backend
              .to('GET', '/v1/staff/kitchen/tickets')
              .last
              .uri
              .queryParameters['canceled_within'],
          '2',
        );
        expect(find.text(VoidBadge.label), findsWidgets);
        expect(find.text('Sabab: Mehmonlar ketdi'), findsNWidgets(2));
        expect(h.sound.voidPlays, 1);
        // no "Hammasi tayyor" on a voided ticket
        expect(buttonLabeled('Hammasi tayyor'), findsNothing);

        await advance(tester, h, const Duration(minutes: 2, seconds: 5));
        expect(find.text(VoidBadge.label), findsNothing);
        expect(find.text('0 ta chipta'), findsOneWidget);
      },
    );

    testWidgets(
      'the cook finishing an order is not mistaken for a cancellation',
      (WidgetTester tester) async {
        final KdsHarness h = posKitchen(stationValues: allStations);
        h.state.addOrder(
          items: const <(String, String, String, List<String>)>[
            ('Somsa', '4', 'pcs', <String>[]),
          ],
        );
        await pumpBoard(tester, h);
        await tester.tap(buttonLabeled('Hammasi tayyor'));
        await tester.pumpAndSettle();
        await advance(tester, h, const Duration(seconds: 20));
        expect(find.text('0 ta chipta'), findsOneWidget);
        expect(find.text(VoidBadge.label), findsNothing);
        expect(h.sound.voidPlays, 0);
        expect(
          h.backend.requests.where(
            (r) => r.method == 'GET' && r.uri.path.contains('/staff/orders/'),
          ),
          isEmpty,
        );
      },
    );

    testWidgets(
      'a void that is already there on the first load is shown, silently',
      (WidgetTester tester) async {
        final KdsHarness h = posKitchen(stationValues: allStations);
        h.state.addPosOrder(
          lines: const <PosLine>[
            PosLine(
              "Qo'y shashlik",
              '4',
              station: 1,
              status: 'void',
              voidReason: 'Xato kiritilgan',
            ),
            PosLine('Osh', '1', station: 2),
          ],
        );
        await pumpBoard(tester, h);
        expect(find.text(VoidBadge.label), findsOneWidget);
        expect(find.text('Sabab: Xato kiritilgan'), findsOneWidget);
        expect(h.sound.voidPlays, 0);
      },
    );

    testWidgets('a voided mangal row shows the badge too', (
      WidgetTester tester,
    ) async {
      final KdsHarness h = posKitchen(stationValues: onlyMangal);
      final String uuid = h.state.addPosOrder(
        lines: const <PosLine>[
          PosLine("Qo'y shashlik", '4', station: 1),
          PosLine('Tovuq shashlik', '2', station: 1),
        ],
      );
      await pumpBoard(tester, h);
      final int id =
          ((h.state.orders.single['items']! as List<Object?>).last!
                  as Map<String, Object?>)['id']!
              as int;
      h.state.voidLine(uuid, id, reason: 'Tugadi');
      await event(tester);
      expect(find.byType(MangalCard), findsOneWidget);
      expect(find.text(VoidBadge.label), findsOneWidget);
      expect(find.text('Sabab: Tugadi'), findsOneWidget);
      expect(find.text('Jami: 4 six'), findsOneWidget);
    });
  });

  group('served', () {
    testWidgets(
      'served lines are shown as done ("Berildi"); ready to served is the waiter\'s action',
      (WidgetTester tester) async {
        final KdsHarness h = posKitchen(stationValues: allStations);
        final String uuid = h.state.addPosOrder(
          lines: const <PosLine>[
            PosLine("Qo'y shashlik", '4', station: 1, status: 'ready'),
            PosLine('Tovuq shashlik', '2', station: 1),
            PosLine('Salat', '1', station: 3),
          ],
        );
        await pumpBoard(tester, h);
        expect(find.textContaining('1/3 tayyor'), findsOneWidget);
        expect(find.text('Berildi'), findsNothing);

        final int id =
            ((h.state.orders.single['items']! as List<Object?>).first!
                    as Map<String, Object?>)['id']!
                as int;
        h.state.serveLine(uuid, id);
        await event(tester);
        expect(find.text('Berildi'), findsOneWidget);
        expect(find.textContaining('1/3 tayyor'), findsOneWidget);
        expect(find.byIcon(Icons.done_all_rounded), findsWidgets);

        // the kitchen cannot recall a served line
        await tester.tap(find.text("Qo'y shashlik"));
        await tester.pumpAndSettle();
        expect(h.backend.to('POST', '/recall'), isEmpty);
        expect(find.text('Berildi'), findsOneWidget);

        // a POS order never offers the whole-order button: the waiter closes the table
        for (final String name in <String>['Tovuq shashlik', 'Salat']) {
          await tester.tap(find.text(name));
          await tester.pumpAndSettle();
        }
        expect(find.textContaining('3/3 tayyor'), findsOneWidget);
        expect(buttonLabeled('Buyurtma tayyor'), findsNothing);
      },
    );
  });

  test('wait formatting helpers', () {
    expect(formatCount(6), '6');
    expect(formatCount(1.5), '1.5');
    expect(formatCount(2.0), '2');
  });
}

String _hm(DateTime t) =>
    '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';
