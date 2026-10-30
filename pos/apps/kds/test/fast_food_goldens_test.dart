import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

// P6-33 / P6-31 states: the expo (assembly) screen, late timers on the grill board, the compact drinks board, a phone.
// Plain run: golden files (`test/goldens/ff_*.png`, test font: layout, colours, states). `KDS_SHOTS=<folder>` with
// `--update-goldens`: readable screenshots with the SDK's Roboto and Material icons go to that folder. After a change:
//   flutter test --update-goldens test/fast_food_goldens_test.dart
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
            ? 'goldens/ff_$name.png'
            : Uri.file('$shots/ff_$name.png'),
      ),
    );
  }

  KdsHarness kitchen(String ids, {bool general = false}) {
    final KdsHarness h = KdsHarness(
      realtime: false,
      stationValues: <String, Object?>{
        'stations_chosen': true,
        'stations_ids': ids,
        'stations_general': general,
      },
    );
    h.settings.update(h.settings.value.copyWith(idleTimeout: () => null));
    h.state
      ..addStation(1, 'Grill', 'grill', target: 300)
      ..addStation(2, 'Fritur', 'fryer', target: 240)
      ..addStation(3, 'Ichimliklar', 'drinks', target: 90)
      ..addStation(4, 'Barista', 'barista', target: 180)
      ..addStation(5, "Yig'ish", 'expo', target: 120);
    return h;
  }

  void queueOrder(
    KdsHarness h,
    int number,
    List<PosLine> lines, {
    Duration age = const Duration(minutes: 3),
  }) {
    h.state.addPosOrder(
      type: 'pickup',
      channel: 'pos_takeaway',
      age: age,
      lines: lines,
    );
    h.state.orders.last
      ..['queue_number'] = number
      ..['pickup_status'] = 'preparing';
  }

  /// Three orders on the expo: one ready to assemble, one with the grill running late, one just started.
  void seedExpo(KdsHarness h) {
    queueOrder(h, 41, const <PosLine>[
      PosLine(
        'Lavash katta',
        '2',
        station: 1,
        status: 'ready',
        combo: 'Lavash kombo',
        comboId: 9,
        comboQty: 2,
      ),
      PosLine(
        'Kartoshka fri',
        '2',
        station: 2,
        status: 'ready',
        combo: 'Lavash kombo',
        comboId: 9,
        comboQty: 2,
      ),
      PosLine(
        'Pepsi 0,4',
        '2',
        station: 3,
        status: 'ready',
        combo: 'Lavash kombo',
        comboId: 9,
        comboQty: 2,
      ),
    ], age: const Duration(minutes: 6));
    queueOrder(h, 42, const <PosLine>[
      PosLine(
        'Burger dublyor',
        '1',
        station: 1,
        sentAgo: Duration(minutes: 6),
        note: 'Piyozsiz',
      ),
      PosLine('Kola 0,5', '1', station: 3, status: 'ready'),
    ], age: const Duration(minutes: 6));
    queueOrder(h, 43, const <PosLine>[
      PosLine('Tovuq krispi', '3', station: 1, sentAgo: Duration(seconds: 40)),
      PosLine('Naggets', '1', station: 2, sentAgo: Duration(seconds: 40)),
      PosLine(
        'Latte',
        '1',
        station: 3,
        mods: <String>['Sutsiz'],
        sentAgo: Duration(seconds: 40),
      ),
    ], age: const Duration(seconds: 45));
  }

  testWidgets(
    'tablet: the expo screen (ready to assemble, a late grill, a new order)',
    (WidgetTester tester) async {
      final KdsHarness h = kitchen('5');
      seedExpo(h);
      await pumpBoard(tester, h);
      await shot(tester, 'tablet_expo');
    },
  );

  testWidgets('phone (400 wide): the expo screen in one column', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = kitchen('5');
    seedExpo(h);
    await pumpBoard(tester, h, size: const Size(400, 900));
    await shot(tester, 'phone_expo');
  });

  testWidgets(
    'tablet: the grill board with timers (counting, amber, red) and a combo',
    (WidgetTester tester) async {
      final KdsHarness h = kitchen('1');
      h.state
        ..addPosOrder(
          type: 'pickup',
          channel: 'pos_takeaway',
          age: const Duration(minutes: 1),
          lines: const <PosLine>[
            PosLine(
              'Lavash katta',
              '1',
              station: 1,
              sentAgo: Duration(seconds: 50),
              combo: 'Lavash kombo',
              comboId: 9,
            ),
            PosLine(
              'Tovuq krispi',
              '3',
              station: 1,
              sentAgo: Duration(seconds: 50),
            ),
          ],
        )
        ..addPosOrder(
          type: 'pickup',
          channel: 'pos_takeaway',
          age: const Duration(minutes: 4, seconds: 20),
          lines: const <PosLine>[
            PosLine(
              'Burger dublyor',
              '2',
              station: 1,
              sentAgo: Duration(minutes: 4, seconds: 15),
            ),
          ],
        )
        ..addPosOrder(
          type: 'pickup',
          channel: 'pos_takeaway',
          age: const Duration(minutes: 7),
          lines: const <PosLine>[
            PosLine(
              'Shaurma',
              '1',
              station: 1,
              sentAgo: Duration(minutes: 6, seconds: 40),
            ),
            PosLine(
              'Hot-dog',
              '1',
              station: 1,
              sentAgo: Duration(minutes: 6, seconds: 40),
              status: 'ready',
            ),
          ],
        );
      await pumpBoard(tester, h);
      await shot(tester, 'tablet_late_timers');
    },
  );

  testWidgets('tablet: the compact drinks and barista board', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = kitchen('3,4');
    for (var i = 0; i < 6; i++) {
      h.state.addPosOrder(
        type: 'pickup',
        channel: 'pos_takeaway',
        age: Duration(seconds: 30 + i * 35),
        lines: <PosLine>[
          PosLine(
            i.isEven ? 'Latte' : 'Kapuchino',
            '1',
            station: 4,
            mods: const <String>['Sutsiz'],
            sentAgo: Duration(seconds: 30 + i * 35),
          ),
          if (i % 3 == 0)
            PosLine(
              'Pepsi 0,4',
              '2',
              station: 3,
              sentAgo: Duration(seconds: 30 + i * 35),
              combo: 'Lavash kombo',
              comboId: 9,
            ),
        ],
      );
    }
    await pumpBoard(tester, h);
    await shot(tester, 'tablet_compact');
  });
}
