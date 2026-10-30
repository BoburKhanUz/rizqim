import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

// P6-21 / P6-25 ticket states: course marks and the course button, the weight badge, the grill timer (counting down
// and overdue), the table plate, and the skewer card. Plain run: golden files (`test/goldens/course_*.png`, test font:
// layout, colours, states). `KDS_SHOTS=<folder>` with `--update-goldens`: readable screenshots with the SDK's Roboto
// and Material icons go to that folder (docs and review). After a change:
//   flutter test --update-goldens test/course_goldens_test.dart
void main() {
  final String? shots = Platform.environment['KDS_SHOTS'];

  setUpAll(() async {
    if (shots == null) return;
    final String fonts =
        '${Platform.environment['FLUTTER_ROOT'] ?? '/opt/flutter'}/bin/cache/artifacts/material_fonts';
    final FontLoader roboto = FontLoader('Roboto');
    for (final String f in <String>[
      'Roboto-Regular.ttf',
      'Roboto-Medium.ttf',
      'Roboto-Bold.ttf',
      'Roboto-Black.ttf',
    ]) {
      roboto.addFont(
        Future<ByteData>.value(
          ByteData.sublistView(File('$fonts/$f').readAsBytesSync()),
        ),
      );
    }
    await roboto.load();
    final FontLoader icons = FontLoader('MaterialIcons')
      ..addFont(
        Future<ByteData>.value(
          ByteData.sublistView(
            File('$fonts/MaterialIcons-Regular.otf').readAsBytesSync(),
          ),
        ),
      );
    await icons.load();
  });

  Future<void> shot(WidgetTester tester, String name) async {
    if (shots != null) {
      // Labels whose style has no family (component themes, RzButton) would draw in the test font: Roboto for them
      for (final RenderParagraph r in tester.renderObjectList<RenderParagraph>(
        find.byType(RichText),
      )) {
        if (r.text.style?.fontFamily != null) continue;
        r.text = TextSpan(
          style: const TextStyle(fontFamily: 'Roboto'),
          children: <InlineSpan>[r.text],
        );
      }
      await tester.pump();
    }
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile(
        shots == null
            ? 'goldens/course_$name.png'
            : Uri.file('$shots/course_$name.png'),
      ),
    );
  }

  KdsHarness kitchen(Map<String, Object?> stations) {
    final KdsHarness h = KdsHarness(realtime: false, stationValues: stations);
    h.settings.update(h.settings.value.copyWith(idleTimeout: () => null));
    h.state
      ..addStation(1, 'Mangal', 'mangal')
      ..addStation(2, 'Osh', 'osh');
    return h;
  }

  const Map<String, Object?> both = <String, Object?>{
    'stations_chosen': true,
    'stations_ids': '1,2',
    'stations_general': true,
  };
  const Map<String, Object?> onlyMangal = <String, Object?>{
    'stations_chosen': true,
    'stations_ids': '1',
    'stations_general': false,
  };

  /// A table on its second course (the soup is ready, the main course is cooking: one dish late on the grill, the fish
  /// waiting for its weight) and a counter order carried to table 12.
  void seedTickets(KdsHarness h) {
    h.state
      ..addPosOrder(
        table: '7',
        age: const Duration(minutes: 14),
        lines: const <PosLine>[
          PosLine(
            "Sho'rva",
            '2',
            station: 2,
            course: 1,
            status: 'ready',
            sentAgo: Duration(minutes: 13),
          ),
          PosLine(
            "Qo'y kabob",
            '4',
            station: 1,
            course: 2,
            waitMinutes: 12,
            sentAgo: Duration(minutes: 13, seconds: 20),
          ),
          PosLine(
            'Sazan',
            '1.2 kg',
            unit: 'kg',
            station: 1,
            course: 2,
            weight: 'awaiting',
            waitMinutes: 25,
            sentAgo: Duration(minutes: 13),
          ),
          PosLine('Osh', '2', station: 2, course: 2, note: 'Achchiq'),
        ],
      )
      ..addPosOrder(
        type: 'pickup',
        channel: 'pos_takeaway',
        tableLabel: '12',
        age: const Duration(minutes: 3),
        lines: const <PosLine>[
          PosLine("Lag'mon", '2', station: 2),
          PosLine('Tandir somsa', '4', station: 1, waitMinutes: 8),
        ],
      );
  }

  testWidgets(
    'tablet: course marks, course button, weight badge, grill timer, table plate',
    (WidgetTester tester) async {
      final KdsHarness h = kitchen(both);
      seedTickets(h);
      await pumpBoard(tester, h);
      await shot(tester, 'tablet_tickets');
    },
  );

  testWidgets('narrow screen (600 wide): the same tickets in one column', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = kitchen(both);
    seedTickets(h);
    await pumpBoard(tester, h, size: const Size(600, 1000));
    await shot(tester, 'narrow_tickets');
  });

  testWidgets(
    'tablet: the skewer card with the course mark, the weight badge and the grill timer',
    (WidgetTester tester) async {
      final KdsHarness h = kitchen(onlyMangal);
      seedTickets(h);
      await pumpBoard(tester, h);
      await shot(tester, 'tablet_mangal');
    },
  );
}
