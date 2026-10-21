// P2-31: the whole kitchen display against the fake backend over REAL sockets: the app's own Dio client talks HTTP to
// `FakeKdsServer` (test/fake_server) on a loopback port and its Pusher client holds a real WebSocket to the same server.
// Pair the terminal, sign the cook in with a PIN, see a ticket arrive over the socket (the poll never gets its turn: the
// clock of a widget test is fake), bump the lines, and see the order become ready on the server and leave the board.
//
// Real I/O needs the real event loop: every wait goes through tester.runAsync, then one pump delivers what arrived.
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_kds/src/app.dart';
import 'package:rizqim_kds/src/realtime/branch_realtime.dart';
import 'package:rizqim_kds/src/settings/kds_settings.dart';
import 'package:rizqim_kds/src/sound/ticket_sound.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'fake_server/fake_kds_server.dart';
import 'support/fake_kitchen_state.dart';

/// Waits (in real time) until [done] holds, delivering what arrived on the sockets to the widget tree as it goes.
Future<void> eventually(
  WidgetTester tester,
  bool Function() done, {
  required String reason,
  Duration timeout = const Duration(seconds: 10),
}) async {
  final Stopwatch clock = Stopwatch()..start();
  while (!done()) {
    if (clock.elapsed > timeout) {
      fail('Timed out after ${timeout.inSeconds} s waiting for: $reason');
    }
    await tester.runAsync(
      () => Future<void>.delayed(const Duration(milliseconds: 20)),
    );
    await tester.pump(const Duration(milliseconds: 20));
  }
}

Finder lineOf(String title) =>
    find.bySemanticsLabel(RegExp('^${RegExp.escape(title)},'));

Finder buttonLabeled(String label) => find.widgetWithText(RzButton, label);

Future<void> typePin(WidgetTester tester, String pin) async {
  for (final String digit in pin.split('')) {
    await tester.tap(find.bySemanticsLabel(digit).first);
    await tester.pump();
  }
  await tester.tap(find.bySemanticsLabel('Kirish'));
  await tester.pump();
}

void main() {
  testWidgets(
    'pair, sign in with the PIN, a ticket arrives over the socket, bump both lines: the order is ready and leaves the board',
    (WidgetTester tester) async {
      // flutter_test answers every HttpClient request with 400; this test wants the real thing
      HttpOverrides.global = null;
      final FakeKdsServer server = (await tester.runAsync(
        FakeKdsServer.start,
      ))!;
      addTearDown(() async => server.stop());
      final FakeKitchenState kitchen = server.state;

      tester.view
        ..physicalSize = const Size(1280, 800)
        ..devicePixelRatio = 1;
      addTearDown(tester.view.reset);

      // The app as main.dart builds it, but with in-memory storage, no sound and Reverb on the fake server
      final RizqimClient client = RizqimClient(
        baseUrl: server.apiBaseUrl,
        store: MemoryKeyValueStore(),
      );
      final KdsServices services = KdsServices(
        client: client,
        settings: KdsSettingsController(MemorySettingsStore()),
        sound: SilentTicketSound(),
        realtimeFactory: (RizqimClient c, PairedTerminal t) =>
            BranchRealtime.create(
              client: c,
              terminal: t,
              apiBaseUrl: server.apiBaseUrl,
              appKey: 'kds-key',
              reverbUrl: server.reverbUrl,
            ),
      );
      await tester.pumpWidget(KdsApp(services: services));
      await tester.pump();
      expect(find.text('Terminalni ulash'), findsOneWidget);

      // --- Pair: the code the owner got from the panel
      await tester.enterText(find.byType(TextField), 'abcdefgh');
      await tester.pump();
      await tester.tap(buttonLabeled('Ulash'));
      await eventually(
        tester,
        () => find.text('Kim ishlaydi?').evaluate().isNotEmpty,
        reason: 'the PIN screen after pairing',
      );
      expect(kitchen.log, contains('POST /api/v1/devices/pair'));
      expect(find.text('Rizqim Osh Markazi / Chilonzor'), findsOneWidget);
      expect(find.text('Aziz Karimov'), findsOneWidget);

      // The terminal listens on its branch channel, signed with its own token (nobody is signed in yet)
      await eventually(
        tester,
        () => server.subscriptions.any(
          (Set<String> c) =>
              c.contains(BranchRealtime.channelFor(FakeKitchenState.branchId)),
        ),
        reason: 'the branch channel subscription on the socket',
      );
      await eventually(
        tester,
        () => find.text('Jonli').evaluate().isNotEmpty,
        reason: 'the "Jonli" status',
      );

      // --- PIN: the cook signs in and the (empty) board opens
      await tester.tap(find.text('Aziz Karimov'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      await typePin(tester, kitchen.pin);
      await eventually(
        tester,
        () => find.text('0 ta chipta').evaluate().isNotEmpty,
        reason: 'the board after the PIN',
      );
      expect(
        kitchen.log.where((String l) => l.contains('/staff-session')),
        hasLength(1),
      );

      // --- A new order reaches the kitchen: the event comes over the socket and the board refetches
      final int fetches = kitchen.log
          .where((String l) => l.endsWith('/kitchen/tickets'))
          .length;
      kitchen.addOrder(
        type: 'pickup',
        notes: "Piyozsiz bo'lsin",
        items: const <(String, String, String, List<String>)>[
          ('Lag\'mon', '2', 'pcs', <String>['Achchiq']),
          ('Kompot', '1 l', 'l', <String>[]),
        ],
      );
      await eventually(
        tester,
        () => find.text('1 ta chipta').evaluate().isNotEmpty,
        reason: 'the new ticket (pushed by the event, not polled)',
      );
      expect(find.text('#K101'), findsOneWidget);
      expect(find.text("Piyozsiz bo'lsin"), findsOneWidget);
      expect(find.text('+ Achchiq'), findsOneWidget);
      expect(
        kitchen.log.where((String l) => l.endsWith('/kitchen/tickets')).length,
        fetches + 1,
        reason: 'exactly one refetch, caused by the event',
      );

      // --- Bump: the first line starts the order, the last one finishes it
      await tester.tap(lineOf("Lag'mon"));
      await eventually(
        tester,
        () => kitchen.orders.single['status'] == 'preparing',
        reason: 'the first bump reaching the server',
      );
      await tester.pump(const Duration(milliseconds: 500));
      expect(find.text('Yaqinda tayyor'), findsOneWidget);
      expect(find.text('1 ta chipta'), findsOneWidget);

      await tester.tap(lineOf('Kompot'));
      await eventually(
        tester,
        () => kitchen.orders.single['status'] == 'ready',
        reason: 'the last bump making the order ready on the server',
      );

      // A pickup order that is ready is the server's to decide: it leaves the board (no more tickets)
      await eventually(
        tester,
        () => find.text('0 ta chipta').evaluate().isNotEmpty,
        reason: 'the ready order leaving the board',
      );
      final List<String> bumps = kitchen.log
          .where((String l) => RegExp(r'/items/\d+/ready$').hasMatch(l))
          .toList();
      expect(bumps, hasLength(2));
      expect(kitchen.log.every((String l) => !l.contains('/recall')), isTrue);
      expect(tester.takeException(), isNull);

      // Leave nothing running: the screen goes first (it closes the socket), the HTTP client drops its keep-alive
      // connections, and the fake clock runs past their idle timers
      await tester.pumpWidget(const SizedBox());
      client.dio.close(force: true);
      await tester.pump(const Duration(seconds: 30));
    },
  );
}
