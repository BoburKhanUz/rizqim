import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_kds/src/settings/kds_settings.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

void main() {
  // Harness test ichida yaratiladi (setUp zonasidagi Dio soxta soat bilan qotadi).
  KdsHarness harness() {
    final KdsHarness h = KdsHarness(realtime: false);
    h.state.addOrder(
      items: const <(String, String, String, List<String>)>[
        ('Osh', '1', 'pcs', <String>[]),
      ],
    );
    return h;
  }

  Future<void> choose(WidgetTester tester, String name) async {
    await tester.tap(find.text(name));
    await tester.pumpAndSettle();
  }

  testWidgets(
    'a wrong PIN shows the Uzbek message and clears the dots; the right PIN opens the board',
    (WidgetTester tester) async {
      final KdsHarness h = harness();
      await pumpPaired(tester, h);
      await choose(tester, 'Aziz Karimov');
      expect(
        find.text('Aziz Karimov'),
        findsNWidgets(2),
        reason: 'the list and the PIN panel',
      );

      await typePin(tester, '7350');
      expect(find.text("PIN noto'g'ri. Qayta urinib ko'ring."), findsOneWidget);
      expect(find.bySemanticsLabel('PIN: 0 ta raqam terildi'), findsOneWidget);
      final RequestOptions attempt = h.backend
          .to('POST', '/staff-session')
          .single;
      expect(
        attempt.headers['Authorization'],
        'Bearer ${FakeKitchenState.terminalToken}',
      );
      expect(attempt.data, <String, Object?>{'user_id': 5, 'pin': '7350'});

      // typing again hides the message
      await typePin(tester, '7', submit: false);
      expect(find.text("PIN noto'g'ri. Qayta urinib ko'ring."), findsNothing);
      await typePin(tester, '305');

      expect(find.text('Kim ishlaydi?'), findsNothing);
      expect(find.text('1 ta chipta'), findsOneWidget);
      expect(
        find.text('Aziz Karimov'),
        findsOneWidget,
        reason: 'the cook in the top bar',
      );
      final RequestOptions tickets = h.backend
          .to('GET', '/kitchen/tickets')
          .first;
      expect(tickets.headers['Authorization'], 'Bearer staff-token-5');
      expect(
        tickets.uri.queryParameters['branch_id'],
        '${FakeKitchenState.branchId}',
      );
    },
  );

  testWidgets(
    'after too many wrong PINs the pad is locked with a countdown, then opens again',
    (WidgetTester tester) async {
      final KdsHarness h = harness();
      await pumpPaired(tester, h);
      await choose(tester, 'Aziz Karimov');
      await typePin(tester, '7350');
      await typePin(tester, '7035');
      await typePin(tester, '3705');

      expect(find.textContaining('Kirish vaqtincha bloklandi'), findsOneWidget);
      expect(
        find.textContaining("1:00 dan keyin qayta urinib ko'ring"),
        findsOneWidget,
      );
      FilledButton key5() => tester.widget<FilledButton>(
        find.descendant(
          of: find.bySemanticsLabel('5'),
          matching: find.byType(FilledButton),
        ),
      );
      expect(key5().onPressed, isNull);

      await advance(tester, h, const Duration(seconds: 30));
      expect(find.textContaining("0:30 dan keyin"), findsOneWidget);
      await advance(tester, h, const Duration(seconds: 31));
      expect(find.textContaining('bloklandi'), findsNothing);
      expect(key5().onPressed, isNotNull);

      await typePin(tester, h.state.pin);
      expect(find.text('1 ta chipta'), findsOneWidget);
    },
  );

  testWidgets('no network while signing in says so in Uzbek', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = harness();
    await pumpPaired(tester, h);
    await choose(tester, 'Aziz Karimov');
    h.backend.offline = true;
    await typePin(tester, '7305');
    expect(find.textContaining("Serverga ulanib bo'lmadi"), findsOneWidget);
  });

  testWidgets('a store without PINs explains what to do', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = harness();
    h.state.staff = <Map<String, Object?>>[];
    await pumpPaired(tester, h);
    expect(find.text("PIN o'rnatilgan xodim yo'q"), findsOneWidget);
    h.state.staff = <Map<String, Object?>>[
      <String, Object?>{'id': 5, 'name': 'Aziz Karimov', 'role': 'cook'},
    ];
    await tester.tap(find.text('Yangilash'));
    await tester.pumpAndSettle();
    // a single cook is chosen at once
    expect(find.bySemanticsLabel('PIN: 0 ta raqam terildi'), findsOneWidget);
  });

  testWidgets('the staff list failing to load can be retried', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = harness();
    await settle(tester, h.client.auth.pairTerminal('ABCD-EFGH'));
    h.backend.offline = true;
    await pumpKds(tester, h);
    expect(find.textContaining("Aloqa yo'q"), findsOneWidget);
    h.backend.offline = false;
    await tester.tap(find.text('Qayta urinish'));
    await tester.pumpAndSettle();
    expect(find.text('Kim ishlaydi?'), findsOneWidget);
  });

  testWidgets(
    'idle auto sign-out returns to the PIN pad but keeps the terminal paired',
    (WidgetTester tester) async {
      final KdsHarness h = harness();
      h.settings.value = const KdsSettings(idleTimeout: Duration(minutes: 5));
      await pumpBoard(tester, h);
      expect(find.text('1 ta chipta'), findsOneWidget);

      await tester.pump(const Duration(minutes: 4));
      await tester.tap(find.text('1 ta chipta'));
      await tester.pump(const Duration(minutes: 4));
      expect(
        find.text('1 ta chipta'),
        findsOneWidget,
        reason: 'the touch restarted the countdown',
      );

      await tester.pump(const Duration(minutes: 1));
      await tester.pumpAndSettle();
      expect(find.text('Kim ishlaydi?'), findsOneWidget);
      expect(
        find.textContaining('uzoq tegilmagani uchun chiqildi'),
        findsOneWidget,
      );
      expect(find.text('Terminalni ulash'), findsNothing);
      expect(h.client.auth.isPaired, isTrue);
      expect(h.client.auth.staff, isNull);
      expect(h.backend.to('DELETE', '/staff-session'), hasLength(1));
    },
  );

  testWidgets(
    'touches inside the settings dialog count as activity; idle sign-out then closes the dialog',
    (WidgetTester tester) async {
      final KdsHarness h = harness();
      h.settings.value = const KdsSettings(idleTimeout: Duration(minutes: 5));
      await pumpBoard(tester, h);
      await tester.tap(find.byTooltip('Sozlamalar'));
      await tester.pumpAndSettle();
      expect(find.byType(Dialog), findsOneWidget);

      for (var i = 0; i < 3; i++) {
        await tester.pump(const Duration(minutes: 4));
        await tester.tap(find.text('Shrift'));
      }
      await tester.pump();
      expect(
        h.client.auth.staff,
        isNotNull,
        reason: '12 minutes of work inside the dialog kept the cook signed in',
      );
      expect(find.byType(Dialog), findsOneWidget);

      await tester.pump(const Duration(minutes: 5));
      await tester.pumpAndSettle();
      expect(h.client.auth.staff, isNull);
      expect(
        find.byType(Dialog),
        findsNothing,
        reason: "the cook's dialog does not stay over the PIN screen",
      );
      expect(find.text('Kim ishlaydi?'), findsOneWidget);
      expect(
        find.textContaining('uzoq tegilmagani uchun chiqildi'),
        findsOneWidget,
      );
    },
  );

  testWidgets('a session the server ends also closes an open dialog', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = harness();
    await pumpBoard(tester, h);
    await tester.tap(find.byTooltip('Sozlamalar'));
    await tester.pumpAndSettle();
    h.backend.respond = (RequestOptions o) =>
        o.uri.path.contains('/kitchen/tickets')
        ? const FakeResponse(401, <String, Object?>{
            'message': 'Unauthenticated.',
          })
        : null;
    await tester.pump(const Duration(seconds: 16));
    await tester.pumpAndSettle();
    expect(find.byType(Dialog), findsNothing);
    expect(
      find.text('Sessiya tugadi. PIN bilan qayta kiring.'),
      findsOneWidget,
    );
  });

  testWidgets('no idle countdown runs on the PIN screen', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = harness();
    h.settings.value = const KdsSettings(idleTimeout: Duration(minutes: 5));
    await pumpPaired(tester, h);
    await tester.pump(const Duration(minutes: 30));
    await tester.pumpAndSettle();
    expect(find.text('Kim ishlaydi?'), findsOneWidget);
    expect(find.textContaining('chiqildi'), findsNothing);
  });

  testWidgets('idle sign-out can be turned off in settings', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = harness();
    h.settings.value = const KdsSettings(idleTimeout: null);
    await pumpBoard(tester, h);
    await tester.pump(const Duration(hours: 2));
    await tester.pump();
    expect(find.text('1 ta chipta'), findsOneWidget);
    expect(find.byType(RzPinPad), findsNothing);
  });

  testWidgets('the lock button signs the cook out', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = harness();
    await pumpBoard(tester, h);
    await tester.tap(buttonLabeled('Chiqish'));
    await tester.pumpAndSettle();
    expect(find.text('Kim ishlaydi?'), findsOneWidget);
    expect(find.textContaining('chiqildi'), findsNothing);
    expect(h.backend.to('DELETE', '/staff-session'), hasLength(1));
  });

  testWidgets(
    'a session the server ended goes back to the PIN pad with a message',
    (WidgetTester tester) async {
      final KdsHarness h = harness();
      await pumpBoard(tester, h);
      h.backend.respond = (RequestOptions o) =>
          o.uri.path.contains('/kitchen/tickets')
          ? const FakeResponse(401, <String, Object?>{
              'message': 'Unauthenticated.',
            })
          : null;
      await tester.pump(const Duration(seconds: 16)); // the next poll
      await tester.pumpAndSettle();
      expect(find.text('Kim ishlaydi?'), findsOneWidget);
      expect(
        find.text('Sessiya tugadi. PIN bilan qayta kiring.'),
        findsOneWidget,
      );
      expect(h.client.auth.isPaired, isTrue);
    },
  );
}
