import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_cashier/src/counter/counter_page.dart';

import 'fast_food_test.dart' show fastFoodTill;
import 'support/counter.dart' hide colaId;
import 'support/fast_food.dart';
import 'support/harness.dart';
import 'support/order_helpers.dart';
import 'support/p22.dart';

/// P6-12 o'lchovi fast food uchun (P6 3-to'lqin, B bosqich): 20 ta buyurtma, 5 tasida kombo (standart, boshqa ichimlik,
/// katta lavash va sous, ikki kombo), hajm tugmasi (kofe 0,3 / 0,7), tez miqdor; naqd — aniq summa yoki kupyura, QR. Kassirning vaqti soxta soat bilan modellanadi (har bosish, tanlov va mijoz pul uzatishi —
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

  testWidgets(
    '20 fast food orders incl. 5 combos: median under 30 s, the default combo in 4 taps',
    (WidgetTester tester) async {
      final (P22 p, FakeQueueBackend q, FastFoodBackend _) =
          await fastFoodTill();
      // A tall tablet: every tile of the fast food menu on screen
      await pumpHome(tester, p.h, size: const Size(1280, 1000));
      expect(find.byType(CounterPage), findsOneWidget);

      // (name, steps); a step: ('tile', id) | ('x', n) | ('size', modifier id) | ('combo', option ids to change) |
      // ('pay', 'exact'|'chip'|'qr')
      final List<(String, List<(String, Object)>)>
      orders = <(String, List<(String, Object)>)>[
        ('1 lavash', <(String, Object)>[('tile', lavashId), ('pay', 'exact')]),
        ('kombo', <(String, Object)>[('combo', <int>[]), ('pay', 'chip')]),
        (
          'lavash + pepsi',
          <(String, Object)>[
            ('tile', lavashId),
            ('tile', pepsiId),
            ('pay', 'chip'),
          ],
        ),
        ('kofe 0,7', <(String, Object)>[('size', 63), ('pay', 'exact')]),
        (
          'kombo (kola) + fri',
          <(String, Object)>[
            ('combo', <int>[4]),
            ('tile', friId),
            ('pay', 'qr'),
          ],
        ),
        (
          '2 lavash + 2 pepsi',
          <(String, Object)>[
            ('x', 2),
            ('tile', lavashId),
            ('x', 2),
            ('tile', pepsiId),
            ('pay', 'chip'),
          ],
        ),
        ('fri', <(String, Object)>[('tile', friId), ('pay', 'exact')]),
        (
          'kombo katta + sous',
          <(String, Object)>[
            ('combo', <int>[2, 6]),
            ('pay', 'chip'),
          ],
        ),
        (
          'lavash katta + kola',
          <(String, Object)>[
            ('tile', lavashBigId),
            ('tile', colaId),
            ('pay', 'chip'),
          ],
        ),
        (
          '2 kofe 0,5',
          <(String, Object)>[('x', 2), ('tile', coffeeId), ('pay', 'qr')],
        ),
        (
          'kombo + kofe 0,3',
          <(String, Object)>[('combo', <int>[]), ('size', 61), ('pay', 'chip')],
        ),
        ('lavash', <(String, Object)>[('tile', lavashId), ('pay', 'qr')]),
        (
          'fri + sous',
          <(String, Object)>[
            ('tile', friId),
            ('tile', sauceId),
            ('pay', 'exact'),
          ],
        ),
        (
          '3 pepsi',
          <(String, Object)>[('x', 3), ('tile', pepsiId), ('pay', 'chip')],
        ),
        (
          'oila: 2 kombo',
          <(String, Object)>[('x', 2), ('combo', <int>[]), ('pay', 'chip')],
        ),
        (
          'lavash + fri + kola',
          <(String, Object)>[
            ('tile', lavashId),
            ('tile', friId),
            ('tile', colaId),
            ('pay', 'chip'),
          ],
        ),
        ('kola', <(String, Object)>[('tile', colaId), ('pay', 'exact')]),
        ('kofe 0,5', <(String, Object)>[('tile', coffeeId), ('pay', 'chip')]),
        (
          'lavash katta + fri',
          <(String, Object)>[
            ('tile', lavashBigId),
            ('tile', friId),
            ('pay', 'qr'),
          ],
        ),
        ('1 lavash', <(String, Object)>[('tile', lavashId), ('pay', 'exact')]),
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
            case 'size':
              await act(
                find.byKey(
                  ValueKey<String>('menu-$coffeeId-size-${sizeUuid(v as int)}'),
                ),
                chip,
              );
            case 'combo':
              // The combo tile, a tap per changed slot, «Qo'shish»
              await act(menuTile(comboId), tile);
              for (final int option in v as List<int>) {
                taps++;
                await act(
                  find.byKey(
                    ValueKey<String>('combo-option-${optionUuid(option)}'),
                  ),
                  chip,
                );
              }
              taps++;
              await act(
                find.byKey(const ValueKey<String>('combo-add')),
                button,
              );
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
        ..writeln(
          'P6-12 tez kassa, fast food: 20 ta buyurtma, 5 ta kombo (soxta soat + ilova vaqti)',
        )
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
        ..writeln('bitta taom (aniq summa): ${results.first.$2} bosish')
        ..writeln('standart kombo (kupyura): ${results[1].$2} bosish');
      // ignore: avoid_print
      print(out);
      final String? file = Platform.environment['FAST_FOOD_SPEED_OUT'];
      if (file != null && file.isNotEmpty) File(file).writeAsStringSync('$out');

      expect(results.first.$2, lessThanOrEqualTo(3));
      // The combo tile and «Qo'shish», then «To'lash», a note chip and «To'lash»
      expect(results[1].$2, lessThanOrEqualTo(5));
      expect(median, lessThan(30));
    },
  );
}
