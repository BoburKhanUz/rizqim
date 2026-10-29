import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_cashier/src/counter/counter_page.dart';

import 'support/counter.dart';
import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

/// P6-12 o'lchovi: 20 ta odatiy osh markazi buyurtmasi (1–4 taom, tez miqdor, majburiy qo'shimcha, naqd — aniq summa yoki
/// kupyura tugmasi, QR). Kassirning vaqti soxta soat bilan modellanadi (har bosish, tanlov va mijoz pul uzatishi —
/// pastdagi jadval), ilova va soxta backend'ning o'z vaqti esa haqiqiy soat bilan o'lchanadi va qo'shiladi.
/// Maqsad: mediana < 30 s, bitta taom — ≤ 3 bosish. Natija: konsolda va `COUNTER_SPEED_OUT` fayliga (berilgan bo'lsa).
///
/// Vaqt modeli (soniya; planshetda katta tugmalar, Fitts qonuni bo'yicha ehtiyotkor qiymatlar):
/// * taom tugmasi — 1.5; tez miqdor (×N) — 1.2; asosiy tugma ("To'lash", "Keyingi mijoz") — 1.0;
/// * qo'shimcha tanlash — 1.5 (+ "Qo'shish" 1.0);
/// * kupyura tugmasi (qaytim) — 1.5; QR yorlig'i — 1.2;
/// * mijoz pul yoki QR uzatadi — 4.0; talonni berish — 2.0.
void main() {
  const double tile = 1.5;
  const double multiplier = 1.2;
  const double button = 1.0;
  const double modifier = 1.5;
  const double chip = 1.5;
  const double tab = 1.2;
  const double handOver = 4.0;
  const double talon = 2.0;

  testWidgets('20 typical counter orders: median under 30 s, one dish in 3 taps', (
    WidgetTester tester,
  ) async {
    final P22 p = P22(offlineStore: await SyncStore.open(MemorySyncStorage()));
    p.state.format = formatBlock();
    seedCounterMenu(p.state);
    p.state.openShift();
    final FakeQueueBackend q = FakeQueueBackend(p);
    await pumpHome(tester, p.h);
    expect(find.byType(CounterPage), findsOneWidget);

    // (name, steps); a step: ('tile', id) | ('x', n) | ('mod', label) | ('pay', 'exact'|'chip'|'qr')
    final List<(String, List<(String, Object)>)>
    orders = <(String, List<(String, Object)>)>[
      ('1 osh', <(String, Object)>[('tile', oshId), ('pay', 'exact')]),
      ('2 osh', <(String, Object)>[('x', 2), ('tile', oshId), ('pay', 'chip')]),
      (
        'osh + choy',
        <(String, Object)>[('tile', oshId), ('tile', 1), ('pay', 'chip')],
      ),
      (
        "lag'mon achchiq",
        <(String, Object)>[
          ('tile', lagmonId),
          ('mod', 'Achchiq'),
          ('pay', 'exact'),
        ],
      ),
      (
        '3 somsa + choy',
        <(String, Object)>[
          ('x', 3),
          ('tile', somsaId),
          ('tile', 1),
          ('pay', 'chip'),
        ],
      ),
      (
        'osh + kola',
        <(String, Object)>[('tile', oshId), ('tile', colaId), ('pay', 'qr')],
      ),
      ('1 osh', <(String, Object)>[('tile', oshId), ('pay', 'exact')]),
      (
        '2 osh + 2 non + choy',
        <(String, Object)>[
          ('x', 2),
          ('tile', oshId),
          ('x', 2),
          ('tile', 2),
          ('tile', 1),
          ('pay', 'chip'),
        ],
      ),
      ('somsa', <(String, Object)>[('tile', somsaId), ('pay', 'exact')]),
      (
        "lag'mon + kola",
        <(String, Object)>[
          ('tile', lagmonId),
          ('mod', 'Achchiqmas'),
          ('tile', colaId),
          ('pay', 'chip'),
        ],
      ),
      (
        '4 osh (oila)',
        <(String, Object)>[
          ('x', 4),
          ('tile', oshId),
          ('tile', 1),
          ('tile', 2),
          ('pay', 'chip'),
        ],
      ),
      ('1 osh', <(String, Object)>[('tile', oshId), ('pay', 'qr')]),
      (
        'osh + somsa',
        <(String, Object)>[
          ('tile', oshId),
          ('tile', somsaId),
          ('pay', 'exact'),
        ],
      ),
      (
        '2 somsa',
        <(String, Object)>[('x', 2), ('tile', somsaId), ('pay', 'chip')],
      ),
      (
        "2 lag'mon",
        <(String, Object)>[
          ('tile', lagmonId),
          ('mod', 'Achchiq'),
          ('tile', lagmonId),
          ('mod', 'Achchiq'),
          ('pay', 'chip'),
        ],
      ),
      (
        'osh + choy + non',
        <(String, Object)>[
          ('tile', oshId),
          ('tile', 1),
          ('tile', 2),
          ('pay', 'chip'),
        ],
      ),
      ('kola', <(String, Object)>[('tile', colaId), ('pay', 'exact')]),
      ('3 osh', <(String, Object)>[('x', 3), ('tile', oshId), ('pay', 'chip')]),
      (
        'osh + kola + somsa',
        <(String, Object)>[
          ('tile', oshId),
          ('tile', colaId),
          ('tile', somsaId),
          ('pay', 'qr'),
        ],
      ),
      ('1 osh', <(String, Object)>[('tile', oshId), ('pay', 'exact')]),
    ];

    final List<(String name, int taps, double human, double app)> results =
        <(String, int, double, double)>[];
    final Stopwatch app = Stopwatch();

    Future<void> act(Finder f, double cost) async {
      p.h.now = p.h.now.add(Duration(milliseconds: (cost * 1000).round()));
      app.start();
      await tester.tap(f);
      await tester.pumpAndSettle();
      app.stop();
    }

    for (final (String name, List<(String, Object)> steps) in orders) {
      app.reset();
      final DateTime start = p.h.now;
      var taps = 0;
      for (final (String kind, Object v) in steps) {
        switch (kind) {
          case 'tile':
            await act(menuTile(v as int), tile);
          case 'x':
            await act(find.byKey(ValueKey<String>('qty-x$v')), multiplier);
          case 'mod':
            await act(find.text(v as String), modifier);
            taps++;
            await act(find.textContaining("Qo'shish").last, button);
          case 'pay':
            await act(
              find.byKey(const ValueKey<String>('counter-pay')),
              button,
            );
            switch (v) {
              case 'chip':
                // The customer hands a bigger note: its chip, the change is shown
                taps++;
                await act(find.byType(ActionChip).at(1), chip);
              case 'qr':
                taps++;
                await act(find.text('QR'), tab);
            }
            taps++;
            p.h.now = p.h.now.add(
              Duration(milliseconds: (handOver * 1000).round()),
            );
            await act(
              find.byKey(const ValueKey<String>('payment-submit')),
              button,
            );
        }
        taps++;
      }
      expect(
        find.byKey(const ValueKey<String>('counter-next')),
        findsOneWidget,
        reason: name,
      );
      p.h.now = p.h.now.add(Duration(milliseconds: (talon * 1000).round()));
      final double human = p.h.now.difference(start).inMilliseconds / 1000;
      results.add((name, taps, human, app.elapsedMilliseconds / 1000));
      // The next customer: the next dish tap starts a fresh cart (no extra tap)
    }

    // Every order was paid and got its own number
    expect(q.entries, hasLength(orders.length));
    expect(
      q.entries.values.map((Map<String, Object?> e) => e['number']).toSet(),
      hasLength(orders.length),
    );

    final List<double> totals = <double>[
      for (final (String _, int _, double h, double a) in results) h + a,
    ]..sort();
    final double median = (totals[9] + totals[10]) / 2;
    final double p90 = totals[17];
    final StringBuffer out = StringBuffer()
      ..writeln('P6-12 tez kassa: 20 ta buyurtma (soxta soat + ilova vaqti)')
      ..writeln(
        '#  | buyurtma                  | bosish | kassir, s | ilova, s | jami, s',
      );
    var i = 0;
    for (final (String name, int taps, double h, double a) in results) {
      i++;
      out.writeln(
        '${'$i'.padLeft(2)} | ${name.padRight(25)} | ${'$taps'.padLeft(6)} | '
        '${h.toStringAsFixed(1).padLeft(9)} | ${a.toStringAsFixed(2).padLeft(8)} | ${(h + a).toStringAsFixed(1).padLeft(7)}',
      );
    }
    out
      ..writeln(
        'mediana: ${median.toStringAsFixed(1)} s, p90: ${p90.toStringAsFixed(1)} s, '
        'eng uzoq: ${totals.last.toStringAsFixed(1)} s',
      )
      ..writeln('bitta taom (aniq summa): ${results.first.$2} bosish');
    // ignore: avoid_print
    print(out);
    final String? file = Platform.environment['COUNTER_SPEED_OUT'];
    if (file != null && file.isNotEmpty) File(file).writeAsStringSync('$out');

    expect(results.first.$2, lessThanOrEqualTo(3));
    expect(median, lessThan(30));
  });
}
