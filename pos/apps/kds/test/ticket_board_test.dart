import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_kds/src/screens/kitchen_board_screen.dart';
import 'package:rizqim_kds/src/settings/kds_settings.dart';
import 'package:rizqim_kds/src/widgets/ticket_card.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

typedef Item = (String, String, String, List<String>);

/// Uchta chipta: zal (23 daq, kechikkan), olib ketish (12 daq), yetkazish (2 daq).
KdsHarness seeded({bool realtime = false}) {
  final KdsHarness h = KdsHarness(realtime: realtime);
  h.state
    ..addOrder(
      type: 'dine_in',
      channel: 'pos_hall',
      table: '7',
      age: const Duration(minutes: 23),
      items: const <Item>[
        ('Osh', '1.5 kg', 'kg', <String>["Qazi qo'shilsin"]),
        ('Non', '2', 'pcs', <String>[]),
      ],
    )
    ..addOrder(
      type: 'pickup',
      channel: 'pos_takeaway',
      age: const Duration(minutes: 12),
      notes: "Piyozsiz bo'lsin",
      items: const <Item>[
        ("Lag'mon", '2', 'pcs', <String>['Achchiq']),
        ('Kompot', '1 l', 'l', <String>[]),
      ],
    )
    ..addOrder(
      age: const Duration(minutes: 2),
      items: const <Item>[('Somsa', '4', 'pcs', <String>[])],
    );
  return h;
}

Finder card(String code) =>
    find.ancestor(of: find.text(code), matching: find.byType(TicketCard));

Finder lineOf(String title) =>
    find.bySemanticsLabel(RegExp('^${RegExp.escape(title)},'));

void main() {
  testWidgets(
    'tickets show number, type, channel, quantities and weights, modifiers and notes, oldest first',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      await pumpBoard(tester, h);

      expect(find.text('3 ta chipta'), findsOneWidget);
      expect(find.text('#K101'), findsOneWidget);
      expect(find.textContaining('Zal · Asosiy zal · Stol 7'), findsOneWidget);
      expect(find.textContaining('Zal kassasi'), findsOneWidget);
      expect(find.textContaining('Olib ketish'), findsOneWidget);
      expect(find.textContaining('Yetkazish'), findsOneWidget);
      expect(find.text('1.5 kg'), findsOneWidget);
      expect(find.text('2×'), findsNWidgets(2));
      expect(find.text('1 l'), findsOneWidget);
      expect(find.text("+ Qazi qo'shilsin"), findsOneWidget);
      expect(find.text('+ Achchiq'), findsOneWidget);
      expect(find.text("Piyozsiz bo'lsin"), findsOneWidget);
      // oldest first: left to right on a 3-column tablet
      final double x101 = tester.getTopLeft(find.text('#K101')).dx;
      final double x102 = tester.getTopLeft(find.text('#K102')).dx;
      final double x103 = tester.getTopLeft(find.text('#K103')).dx;
      expect(x101 < x102 && x102 < x103, isTrue);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'the waiting time counts up and changes colour at the thresholds (with icon and label)',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      await pumpBoard(tester, h);

      // 23 min: late; 12 min: hurry; 2 min: normal
      expect(
        find.descendant(of: card('#K101'), matching: find.text('Kechikdi')),
        findsOneWidget,
      );
      expect(
        find.descendant(of: card('#K102'), matching: find.text('Shoshiling')),
        findsOneWidget,
      );
      expect(
        find.descendant(of: card('#K103'), matching: find.text('2:00')),
        findsOneWidget,
      );
      expect(
        find.descendant(
          of: card('#K103'),
          matching: find.byIcon(Icons.timer_outlined),
        ),
        findsOneWidget,
      );

      await advance(tester, h, const Duration(seconds: 5));
      expect(
        find.descendant(of: card('#K103'), matching: find.text('2:05')),
        findsOneWidget,
      );

      // configurable: 2 minutes is already "hurry" with a 2-minute threshold
      h.settings.update(
        h.settings.value.copyWith(warnAfter: const Duration(minutes: 2)),
      );
      await tester.pump();
      expect(
        find.descendant(of: card('#K103'), matching: find.text('Shoshiling')),
        findsOneWidget,
      );

      // header colours come from the tokens: warning / danger solid with on-colour text
      const RzColors c = RzColors.dark;
      Color headerOf(String code) => (tester
          .widgetList<Container>(
            find.descendant(of: card(code), matching: find.byType(Container)),
          )
          .firstWhere((Container w) => w.color != null)
          .color)!;
      expect(headerOf('#K101'), c.danger);
      expect(headerOf('#K102'), c.warning);
    },
  );

  testWidgets(
    'bumping a line is optimistic, then confirmed, and lands in the recently-ready strip',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      await pumpBoard(tester, h);
      final Completer<void> gate = Completer<void>();
      h.backend.hold = gate;

      await tester.tap(lineOf('Somsa'));
      await tester.pump();
      // shown ready before the server answers
      expect(
        find.bySemanticsLabel(RegExp('^Somsa, 4×, tayyor[.]')),
        findsOneWidget,
      );
      expect(find.text('Yaqinda tayyor'), findsOneWidget);

      gate.complete();
      h.backend.hold = null;
      await tester.pumpAndSettle();
      expect(
        h.backend.to('POST', '/items/').single.uri.path,
        endsWith('/ready'),
      );
      // a delivery order with every line ready is ready (the server decides) and leaves the board
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(find.text('#K103'), findsNothing);
      expect(find.text('2 ta chipta'), findsOneWidget);
      expect(
        find.textContaining('#K103 · Somsa 4'),
        findsOneWidget,
        reason: 'still in the strip',
      );
    },
  );

  testWidgets('a refused bump rolls back and shows the reason', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = seeded();
    await pumpBoard(tester, h);
    h.backend.respond = (RequestOptions o) => o.uri.path.endsWith('/ready')
        ? const FakeResponse(422, <String, Object?>{
            'success': false,
            'message': "Buyurtma oshxonada emas: taomni o'zgartirib bo'lmaydi.",
            'data': <String, Object?>{'reason': 'kitchen_status'},
          })
        : null;
    final Completer<void> gate = Completer<void>();
    h.backend.hold = gate;

    await tester.tap(lineOf("Lag'mon"));
    await tester.pump();
    expect(
      find.bySemanticsLabel(RegExp("^Lag'mon, 2×, tayyor[.]")),
      findsOneWidget,
    );

    gate.complete();
    h.backend.hold = null;
    await tester.pumpAndSettle();
    expect(
      find.bySemanticsLabel(RegExp("^Lag'mon, 2×, tayyor emas")),
      findsOneWidget,
    );
    expect(
      find.text("Buyurtma oshxonada emas: taomni o'zgartirib bo'lmaydi."),
      findsOneWidget,
    );
    expect(find.text('Yaqinda tayyor'), findsNothing);
  });

  testWidgets('a bump without network rolls back with an Uzbek message', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = seeded();
    await pumpBoard(tester, h);
    h.backend.offline = true;
    await tester.tap(lineOf('Non'));
    await tester.pumpAndSettle();
    expect(
      find.bySemanticsLabel(RegExp('^Non, 2×, tayyor emas')),
      findsOneWidget,
    );
    expect(
      find.textContaining("Aloqa yo'q: o'zgarish saqlanmadi"),
      findsOneWidget,
    );
  });

  testWidgets(
    'a bumped line can be recalled by tapping it again or from the strip',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      await pumpBoard(tester, h);

      await tester.tap(lineOf('Non'));
      await tester.pumpAndSettle();
      expect(
        find.bySemanticsLabel(RegExp('^Non, 2×, tayyor[.]')),
        findsOneWidget,
      );
      await tester.tap(lineOf('Non'));
      await tester.pumpAndSettle();
      expect(
        find.bySemanticsLabel(RegExp('^Non, 2×, tayyor emas')),
        findsOneWidget,
      );
      expect(h.backend.to('POST', '/recall'), hasLength(1));

      await tester.tap(lineOf('Osh'));
      await tester.pumpAndSettle();
      expect(find.textContaining('#K101 · Osh 1.5 kg'), findsOneWidget);
      await tester.tap(find.widgetWithText(TextButton, 'Qaytarish'));
      await tester.pumpAndSettle();
      expect(
        find.bySemanticsLabel(RegExp('^Osh, 1.5 kg, tayyor emas')),
        findsOneWidget,
      );
      expect(find.text('Yaqinda tayyor'), findsNothing);
      expect(h.backend.to('POST', '/recall'), hasLength(2));
    },
  );

  testWidgets(
    'recall after the order became ready is refused by the server and explained',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      await pumpBoard(tester, h);
      await tester.tap(lineOf('Somsa'));
      await tester.pumpAndSettle();
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(find.text('#K103'), findsNothing);

      await tester.tap(find.widgetWithText(TextButton, 'Qaytarish'));
      await tester.pumpAndSettle();
      expect(
        find.text("Buyurtma oshxonada emas: taomni o'zgartirib bo'lmaydi."),
        findsOneWidget,
      );
      expect(find.text('Yaqinda tayyor'), findsNothing);
    },
  );

  testWidgets(
    '"Hammasi tayyor" bumps every pending line; a pickup order then leaves the board',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      await pumpBoard(tester, h);
      await tester.tap(
        find.descendant(
          of: card('#K102'),
          matching: buttonLabeled('Hammasi tayyor'),
        ),
      );
      await tester.pumpAndSettle();
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(h.backend.to('POST', '/ready'), hasLength(2));
      expect(find.text('#K102'), findsNothing);
      expect(find.textContaining("#K102 · Lag'mon"), findsOneWidget);
      expect(find.textContaining('#K102 · Kompot'), findsOneWidget);
    },
  );

  testWidgets(
    'a failure in the middle of "Hammasi tayyor" rolls back the rest',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      await pumpBoard(tester, h);
      var n = 0;
      h.backend.respond = (RequestOptions o) =>
          o.uri.path.endsWith('/ready') && ++n == 2
          ? const FakeResponse(500, <String, Object?>{
              'message': 'Server xatosi.',
            })
          : null;
      await tester.tap(
        find.descendant(
          of: card('#K102'),
          matching: buttonLabeled('Hammasi tayyor'),
        ),
      );
      await tester.pumpAndSettle();
      expect(
        find.bySemanticsLabel(RegExp("^Lag'mon, 2×, tayyor[.]")),
        findsOneWidget,
      );
      expect(
        find.bySemanticsLabel(RegExp('^Kompot, 1 l, tayyor emas')),
        findsOneWidget,
      );
      expect(find.text('Server xatosi.'), findsOneWidget);
    },
  );

  testWidgets(
    'a hall order with every line ready stays until the cook marks the whole order ready',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      await pumpBoard(tester, h);
      await tester.tap(
        find.descendant(
          of: card('#K101'),
          matching: buttonLabeled('Hammasi tayyor'),
        ),
      );
      await tester.pumpAndSettle();
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(
        find.text('#K101'),
        findsOneWidget,
        reason: 'dine_in stays preparing',
      );

      final Completer<void> gate = Completer<void>();
      h.backend.hold = gate;
      await tester.tap(
        find.descendant(
          of: card('#K101'),
          matching: buttonLabeled('Buyurtma tayyor'),
        ),
      );
      await tester.pump();
      expect(find.text('#K101'), findsNothing, reason: 'hidden at once');
      gate.complete();
      h.backend.hold = null;
      await tester.pumpAndSettle();
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(find.text('#K101'), findsNothing);
      final RequestOptions status = h.backend.to('POST', '/status').single;
      expect(status.data.toString(), contains('ready'));
    },
  );

  testWidgets('marking the order ready comes back when the server refuses', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = seeded();
    h.state.orders.first['status'] = 'preparing';
    for (final Object? i in h.state.orders.first['items']! as List<Object?>) {
      (i! as Map<String, Object?>)['kitchen_status'] = 'ready';
    }
    await pumpBoard(tester, h);
    h.backend.offline = true;
    await tester.tap(
      find.descendant(
        of: card('#K101'),
        matching: buttonLabeled('Buyurtma tayyor'),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('#K101'), findsOneWidget);
    expect(find.textContaining("Aloqa yo'q"), findsWidgets);
  });

  testWidgets('without kitchen.manage the board is read-only', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = seeded();
    h.state.permissions = <String>['orders.view'];
    await pumpBoard(tester, h);
    expect(buttonLabeled('Hammasi tayyor'), findsNothing);
    await tester.tap(lineOf('Somsa'), warnIfMissed: false);
    await tester.pumpAndSettle();
    expect(h.backend.to('POST', '/items/'), isEmpty);
  });

  testWidgets(
    'offline: the "Aloqa yo\'q" banner shows while the last tickets stay; it clears when the server is back',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      await pumpBoard(tester, h);
      h.backend.offline = true;
      await tester.pump(const Duration(seconds: 15));
      await tester.pumpAndSettle();
      expect(find.byType(RzOfflineBanner), findsOneWidget);
      expect(find.textContaining('Oxirgi yangilanish 12:00'), findsOneWidget);
      expect(find.text('#K101'), findsOneWidget);
      expect(find.text('3 ta chipta'), findsOneWidget);

      h.backend.offline = false;
      await tester.tap(find.text('Qayta urinish'));
      await tester.pumpAndSettle();
      expect(find.byType(RzOfflineBanner), findsNothing);
    },
  );

  testWidgets(
    'one unreadable ticket is skipped with a warning; the others stay on the board and it is not "Aloqa yo\'q"',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      h.backend.respond = (RequestOptions o) {
        if (o.method != 'GET' || !o.uri.path.contains('/kitchen/tickets')) {
          return null;
        }
        final String? auth = o.headers['Authorization'] as String?;
        final FakeResponse real = h.state.handle(
          o.method,
          o.uri.path,
          o.uri.queryParameters,
          o.data,
          auth?.replaceFirst('Bearer ', ''),
        );
        final Map<String, Object?> body = Map<String, Object?>.of(
          real.body! as Map<String, Object?>,
        );
        body['data'] = <Object?>[
          ...(body['data']! as List<Object?>),
          <String, Object?>{'uuid': 'broken-ticket', 'items': 'nope'},
        ];
        return FakeResponse(real.status, body);
      };
      await pumpBoard(tester, h);

      expect(find.text('3 ta chipta'), findsOneWidget);
      expect(find.text('#K101'), findsOneWidget);
      expect(
        find.textContaining("1 ta chiptani o'qib bo'lmadi"),
        findsOneWidget,
      );
      expect(find.byType(RzOfflineBanner), findsNothing);
      expect(find.textContaining("Aloqa yo'q"), findsNothing);
    },
  );

  testWidgets(
    'a 200 the app cannot read says so (not "Aloqa yo\'q") and keeps the last tickets',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      await pumpBoard(tester, h);
      expect(find.text('3 ta chipta'), findsOneWidget);

      h.backend.respond = (RequestOptions o) =>
          o.method == 'GET' && o.uri.path.contains('/kitchen/tickets')
          ? const FakeResponse(200, <String, Object?>{
              'success': true,
              'message': '',
              'data': <String, Object?>{'tickets': <Object?>[]},
            })
          : null;
      await tester.pump(const Duration(seconds: 15));
      await tester.pumpAndSettle();

      expect(find.byType(RzOfflineBanner), findsNothing);
      expect(find.textContaining("Aloqa yo'q"), findsNothing);
      expect(
        find.textContaining("Server javobini o'qib bo'lmadi"),
        findsOneWidget,
      );
      expect(find.text('3 ta chipta'), findsOneWidget);
    },
  );

  testWidgets(
    'an unreadable first load shows the reason, not "server bilan aloqa yo\'q"',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      h.backend.respond = (RequestOptions o) =>
          o.method == 'GET' && o.uri.path.contains('/kitchen/tickets')
          ? const FakeResponse(200, <String, Object?>{'success': true})
          : null;
      await pumpBoard(tester, h);
      expect(
        find.textContaining("Server javobini o'qib bo'lmadi"),
        findsOneWidget,
      );
      expect(find.textContaining("aloqa yo'q"), findsNothing);
      expect(find.byType(RzOfflineBanner), findsNothing);
    },
  );

  testWidgets(
    'polling every 15 s picks up new tickets when there is no realtime, with sound and a "Yangi" badge',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      await pumpBoard(tester, h);
      expect(find.text('Har 15 soniyada'), findsOneWidget);
      expect(
        find.text('Yangi'),
        findsNothing,
        reason: 'the first load is not new',
      );
      expect(h.sound.plays, 0);

      h.state.addOrder(items: const <Item>[('Kabob', '3', 'pcs', <String>[])]);
      await tester.pump(const Duration(seconds: 14));
      expect(find.text('#K104'), findsNothing);
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(find.text('#K104'), findsOneWidget);
      expect(find.text('Yangi'), findsOneWidget);
      expect(h.sound.plays, 1);

      await advance(tester, h, const Duration(minutes: 1));
      expect(find.text('Yangi'), findsNothing);
    },
  );

  testWidgets('a muted terminal does not play the sound', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = seeded();
    await pumpBoard(tester, h);
    await tester.tap(find.byTooltip("Ovozni o'chirish"));
    await tester.pump();
    expect(h.settings.value.muted, isTrue);
    h.state.addOrder();
    await tester.pump(const Duration(seconds: 15));
    await tester.pumpAndSettle();
    expect(find.text('#K104'), findsOneWidget);
    expect(h.sound.plays, 0);
  });

  testWidgets(
    'responsive columns: tablet 3, 1080p TV 5, phone 1, no overflow in light and dark',
    (WidgetTester tester) async {
      expect(KitchenBoardScreen.columnsFor(1280 - 24), 3);
      expect(KitchenBoardScreen.columnsFor(1920 - 24), 5);
      expect(KitchenBoardScreen.columnsFor(800 - 24), 2);
      expect(KitchenBoardScreen.columnsFor(360 - 24), 1);

      for (final Size size in const <Size>[
        Size(1920, 1080),
        Size(1280, 800),
        Size(800, 1280),
        Size(360, 640),
      ]) {
        final KdsHarness h = seeded();
        for (final bool dark in <bool>[true, false]) {
          h.settings.value = KdsSettings(
            dark: dark,
            fontScale: size.width > 1800 ? 1.2 : 1.0,
          );
          if (dark) {
            await pumpBoard(tester, h, size: size);
          } else {
            await tester.pumpAndSettle();
          }
          expect(tester.takeException(), isNull, reason: '$size dark=$dark');
          expect(find.text('#K101'), findsOneWidget);
        }
        await tester.pumpWidget(const SizedBox());
      }
    },
  );

  testWidgets('touch targets on the board are at least 48px', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = seeded();
    await pumpBoard(tester, h);
    for (final Finder f in <Finder>[
      lineOf('Somsa'),
      find.byTooltip("Ovozni o'chirish"),
      find.byTooltip('Sozlamalar'),
      buttonLabeled('Chiqish'),
      buttonLabeled('Hammasi tayyor').first,
    ]) {
      final Size s = tester.getSize(f);
      expect(s.height, greaterThanOrEqualTo(48), reason: '$f');
      expect(s.width, greaterThanOrEqualTo(48), reason: '$f');
    }
  });

  testWidgets(
    'the settings dialog changes thresholds, idle time, sound and theme',
    (WidgetTester tester) async {
      final KdsHarness h = seeded();
      await pumpBoard(tester, h);
      await tester.tap(find.byTooltip('Sozlamalar'));
      await tester.pumpAndSettle();
      expect(find.text('Sozlamalar'), findsOneWidget);

      await tester.tap(find.byType(Switch).at(1)); // dark theme off
      await tester.pumpAndSettle();
      expect(h.settings.value.dark, isFalse);

      await tester.tap(find.text('15 daqiqa').last); // idle
      await tester.pumpAndSettle();
      await tester.tap(find.text("O'chiq").last);
      await tester.pumpAndSettle();
      expect(h.settings.value.idleTimeout, isNull);

      await tester.tap(find.text('120%'));
      await tester.pumpAndSettle();
      expect(h.settings.value.fontScale, 1.2);

      await tester.tap(buttonLabeled('Yopish'));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(h.state.log, isNotEmpty);
      expect(FakeKitchenState.branchId, 3);
    },
  );
}
