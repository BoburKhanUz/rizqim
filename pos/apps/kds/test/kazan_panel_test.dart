import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'support/fake_kds.dart';
import 'support/fake_kitchen_state.dart';
import 'support/helpers.dart';

/// Oshpaz `batches.manage` bilan, bitta ochiq qozon, tugagan qozon va yopilgan qozon.
KdsHarness kazanKitchen({bool manage = true}) {
  final KdsHarness h = KdsHarness(stationValues: const <String, Object?>{});
  h.settings.update(h.settings.value.copyWith(idleTimeout: () => null));
  if (manage) h.state.permissions.add('batches.manage');
  h.state
    ..addBatch('Osh', 40, left: 12, label: '1-qozon')
    ..addBatch("Sho'rva", 30, left: 0, productId: 2)
    ..addBatch('Mastava', 20, left: 5, productId: 3, open: false)
    ..catalogProducts = <Map<String, Object?>>[
      <String, Object?>{
        'id': 5,
        'name': 'Qozon kabob',
        'batch_tracked': true,
        'variants': <Map<String, Object?>>[
          <String, Object?>{'id': 51, 'name': '1 kg'},
        ],
      },
      <String, Object?>{
        'id': 6,
        'name': 'Somsa',
        'batch_tracked': false,
        'variants': <Map<String, Object?>>[],
      },
    ];
  return h;
}

Future<void> openKazan(WidgetTester tester, KdsHarness h) async {
  await pumpBoard(tester, h);
  await tester.tap(find.text('Qozon'));
  await tester.pumpAndSettle();
}

Map<String, Object?> body(RequestOptions r) =>
    (r.data! as Map<Object?, Object?>).cast<String, Object?>();

void main() {
  testWidgets(
    'the "Qozon" tab shows the open batches with what is left, sold-out ones flagged',
    (WidgetTester tester) async {
      final KdsHarness h = kazanKitchen();
      await openKazan(tester, h);

      expect(find.text("Qozon qoldig'i"), findsOneWidget);
      expect(find.text('12'), findsOneWidget);
      expect(find.text('/ 40 porsiya'), findsOneWidget);
      expect(find.text('1-qozon'), findsOneWidget);
      expect(find.text('TUGADI'), findsOneWidget);
      expect(find.text("Sho'rva"), findsOneWidget);
      // closed batch is listed apart, with a way to reopen it
      expect(find.text('Yaqinda yopilgan'), findsOneWidget);
      expect(find.textContaining('Mastava'), findsOneWidget);
      final RequestOptions req = h.backend.to('GET', '/owner/batches').first;
      expect(req.queryParameters['include_closed'], true);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('without batches.manage there is no kazan tab', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = kazanKitchen(manage: false);
    await pumpBoard(tester, h);
    expect(find.text('Qozon'), findsNothing);
    expect(find.textContaining('Buyurtmalar'), findsOneWidget);
    expect(h.backend.to('GET', '/owner/batches'), isEmpty);
  });

  testWidgets('refill adds portions with the quick chips or a typed number', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = kazanKitchen();
    await openKazan(tester, h);

    await tester.tap(find.byKey(const ValueKey<String>('kazan-refill-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('+10'));
    await tester.pump();
    await tester.tap(find.text('+5'));
    await tester.pump();
    await tester.tap(
      find.byKey(const ValueKey<String>('kazan-number-confirm')),
    );
    await tester.pumpAndSettle();

    final RequestOptions post = h.backend
        .to('POST', '/owner/batches/1/adjust')
        .single;
    expect(body(post)['add'], 15);
    // reloaded: 12 + 15 left, 55 produced
    expect(find.text('27'), findsOneWidget);
    expect(find.text('/ 55 porsiya'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey<String>('kazan-refill-1')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey<String>('kazan-number')),
      '2,5',
    );
    await tester.pump();
    await tester.tap(
      find.byKey(const ValueKey<String>('kazan-number-confirm')),
    );
    await tester.pumpAndSettle();
    expect(
      body(h.backend.to('POST', '/owner/batches/1/adjust').last)['add'],
      2.5,
    );
  });

  testWidgets('recount sets what is left', (WidgetTester tester) async {
    final KdsHarness h = kazanKitchen();
    await openKazan(tester, h);
    await tester.tap(find.byKey(const ValueKey<String>('kazan-recount-1')));
    await tester.pumpAndSettle();
    // starts from the current number
    expect(
      tester
          .widget<TextField>(find.byKey(const ValueKey<String>('kazan-number')))
          .controller!
          .text,
      '12',
    );
    await tester.enterText(
      find.byKey(const ValueKey<String>('kazan-number')),
      '7',
    );
    await tester.pump();
    await tester.tap(
      find.byKey(const ValueKey<String>('kazan-number-confirm')),
    );
    await tester.pumpAndSettle();
    final Map<String, Object?> sent = body(
      h.backend.to('POST', '/owner/batches/1/adjust').single,
    );
    expect(sent['quantity_left'], 7);
    expect(sent.containsKey('add'), isFalse);
    expect(find.text('7'), findsOneWidget);
  });

  testWidgets(
    'close asks first, then closes and moves the batch to the closed list',
    (WidgetTester tester) async {
      final KdsHarness h = kazanKitchen();
      await openKazan(tester, h);
      await tester.tap(find.byKey(const ValueKey<String>('kazan-close-1')));
      await tester.pumpAndSettle();
      expect(find.textContaining('qozonni yopamizmi'), findsOneWidget);
      await tester.tap(find.text('Bekor'));
      await tester.pumpAndSettle();
      expect(h.backend.to('POST', '/owner/batches/1/close'), isEmpty);

      await tester.tap(find.byKey(const ValueKey<String>('kazan-close-1')));
      await tester.pumpAndSettle();
      await tester.tap(
        find.byKey(const ValueKey<String>('kazan-close-confirm')),
      );
      await tester.pumpAndSettle();
      expect(h.backend.to('POST', '/owner/batches/1/close'), hasLength(1));
      expect(
        find.byKey(const ValueKey<String>('kazan-refill-1')),
        findsNothing,
      );
      expect(
        find.byKey(const ValueKey<String>('kazan-reopen-1')),
        findsOneWidget,
      );
    },
  );

  testWidgets(
    'a new batch is opened for a batch-tracked dish (and only those are offered)',
    (WidgetTester tester) async {
      final KdsHarness h = kazanKitchen();
      await openKazan(tester, h);
      await tester.tap(find.byKey(const ValueKey<String>('kazan-open')));
      await tester.pumpAndSettle();
      expect(h.backend.to('GET', '/v1/catalog'), isNotEmpty);

      // nothing yet: cannot open
      expect(
        tester
            .widget<FilledButton>(
              find.byKey(const ValueKey<String>('kazan-open-confirm')),
            )
            .onPressed,
        isNull,
      );
      await tester.tap(find.byKey(const ValueKey<String>('kazan-product')));
      await tester.pumpAndSettle();
      expect(find.text('Somsa'), findsNothing);
      await tester.tap(find.text('Qozon kabob').last);
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const ValueKey<String>('kazan-open-qty')),
        '35',
      );
      await tester.pump();
      await tester.tap(
        find.byKey(const ValueKey<String>('kazan-open-confirm')),
      );
      await tester.pumpAndSettle();

      final Map<String, Object?> sent = body(
        h.backend.to('POST', '/owner/batches').single,
      );
      expect(sent['product_id'], 5);
      expect(sent['product_variant_id'], 51);
      expect(sent['quantity'], 35);
      expect(find.text('Qozon kabob'), findsOneWidget);
      expect(find.text('/ 35 porsiya'), findsOneWidget);
    },
  );

  testWidgets('a closed batch can be reopened with a new quantity', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = kazanKitchen();
    await openKazan(tester, h);
    await tester.tap(find.byKey(const ValueKey<String>('kazan-reopen-3')));
    await tester.pumpAndSettle();
    expect(find.textContaining('Mastava'), findsWidgets);
    await tester.enterText(
      find.byKey(const ValueKey<String>('kazan-open-qty')),
      '25',
    );
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey<String>('kazan-open-confirm')));
    await tester.pumpAndSettle();
    final Map<String, Object?> sent = body(
      h.backend.to('POST', '/owner/batches').single,
    );
    expect(sent['product_id'], 3);
    expect(sent['quantity'], 25);
  });

  testWidgets('a refused action shows the server message and keeps the panel', (
    WidgetTester tester,
  ) async {
    final KdsHarness h = kazanKitchen();
    h.backend.respond = (RequestOptions o) =>
        o.method == 'POST' && o.uri.path.endsWith('/owner/batches')
        ? const FakeResponse(422, <String, Object?>{
            'success': false,
            'message': 'Bu taom uchun ochiq qozon bor.',
          })
        : null;
    await openKazan(tester, h);
    await tester.tap(find.byKey(const ValueKey<String>('kazan-reopen-3')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey<String>('kazan-open-qty')),
      '10',
    );
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey<String>('kazan-open-confirm')));
    await tester.pumpAndSettle();
    expect(find.text('Bu taom uchun ochiq qozon bor.'), findsOneWidget);
    expect(find.text("Qozon qoldig'i"), findsOneWidget);
  });

  testWidgets(
    'the panel refreshes on menu.availability_changed and shows offline',
    (WidgetTester tester) async {
      final KdsHarness h = kazanKitchen();
      await openKazan(tester, h);
      expect(find.text('12'), findsOneWidget);
      // an order took portions elsewhere
      h.state.batches.first['quantity_left'] = 9;
      h.hub.broadcast('menu.availability_changed', <String, Object?>{});
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(find.text('9'), findsOneWidget);

      h.backend.offline = true;
      await tester.tap(find.byTooltip('Yangilash'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Server bilan aloqa yo'), findsWidgets);
      expect(find.text('9'), findsOneWidget, reason: 'keeps the last numbers');
    },
  );

  testWidgets('the panel fits a phone and a tablet in light and dark', (
    WidgetTester tester,
  ) async {
    for (final Size size in const <Size>[Size(360, 640), Size(1280, 800)]) {
      for (final bool dark in <bool>[true, false]) {
        final KdsHarness h = kazanKitchen();
        h.settings.update(h.settings.value.copyWith(dark: dark));
        setScreen(tester, size);
        await pumpBoard(tester, h, size: size);
        await tester.tap(find.byIcon(Icons.rice_bowl_outlined).first);
        await tester.pumpAndSettle();
        expect(find.text('TUGADI'), findsOneWidget, reason: '$size dark=$dark');
        expect(tester.takeException(), isNull, reason: '$size dark=$dark');
        for (final Finder f in <Finder>[
          buttonLabeled("Qo'shish"),
          buttonLabeled('Sanash'),
          buttonLabeled('Yopish'),
        ]) {
          expect(tester.getSize(f.first).height, greaterThanOrEqualTo(44));
        }
        await tester.pumpWidget(const SizedBox());
      }
    }
  });
}
