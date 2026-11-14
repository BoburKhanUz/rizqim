import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'support/banquets.dart';
import 'support/p22.dart';

// To'lqin 5 (B) kassa suratlari: "Tadbirlar" taqvimi, tadbir kartasi (jadval bilan), jadval qatorini to'lash,
// yakuniy hisob — planshet (1280×800) va telefon (390×844). Oddiy ishga tushirishda — test shrifti bilan golden'lar
// (`test/goldens/banquets_*.png`). `BANQUETS_SHOTS=<papka>` va `--update-goldens` bilan — haqiqiy Roboto shrifti bilan
// o'qiladigan suratlar shu papkaga.
// O'zgarishdan keyin: flutter test --update-goldens test/banquets_goldens_test.dart
void main() {
  final String? shots = Platform.environment['BANQUETS_SHOTS'];
  const Size tablet = Size(1280, 800);
  const Size phone = Size(390, 844);

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
            ? 'goldens/banquets_$name.png'
            : Uri.file('$shots/banquets_$name.png'),
      ),
    );
  }

  /// Ikki tadbir (biri muddati o'tgan), biri bugun — tasdiqlangan, kelgan mehmonlar bilan.
  (BanquetTill, String, String) seeded() {
    final BanquetTill t = BanquetTill();
    final String toy = t.b.seedEvent(
      date: '2026-10-25',
      title: "Aziz va Malika to'yi",
      guests: 260,
      extraIds: <(int, int)>[(1, 1), (2, 1)],
    );
    final String today = t.b.seedEvent(
      date: '2026-10-22',
      hallIds: <int>[5, 6],
      customer: 'Rahimovlar',
      type: 'osh',
      slot: 'day',
      status: 'confirmed',
    );
    t.b.seedEvent(
      date: '2026-10-30',
      hallIds: <int>[6],
      customer: 'Toshmatov MChJ',
      type: 'corporate',
      guests: 120,
      packageId: 2,
      status: 'confirmed',
    );
    t.b.makeOverdue(today);
    return (t, toy, today);
  }

  for (final (String device, Size size) in <(String, Size)>[
    ('tablet', tablet),
    ('phone', phone),
  ]) {
    testWidgets('$device: the calendar', (WidgetTester tester) async {
      final (BanquetTill t, String _, String _) = seeded();
      await pumpBanquets(tester, t, name: 'Malika Rahimova', size: size);
      await shot(tester, '${device}_calendar');
    });

    testWidgets('$device: the event card with its schedule', (
      WidgetTester tester,
    ) async {
      final (BanquetTill t, String toy, String _) = seeded();
      await pumpBanquets(tester, t, name: 'Malika Rahimova', size: size);
      await openEvent(tester, toy);
      await shot(tester, '${device}_event_card');
    });

    testWidgets('$device: taking a scheduled payment', (
      WidgetTester tester,
    ) async {
      final (BanquetTill t, String toy, String _) = seeded();
      await pumpBanquets(tester, t, size: size);
      await openEvent(tester, toy);
      await tapKey(tester, 'banquet-pay-${t.rows(toy).first['uuid']}');
      await enter(tester, 'tender-given', '17000000');
      await shot(tester, '${device}_schedule_payment');
    });

    testWidgets('$device: the final settlement', (WidgetTester tester) async {
      final (BanquetTill t, String _, String today) = seeded();
      t.b.events[today]!['guests_actual'] = 185;
      t.b.events[today]!['status'] = 'in_progress';
      await pumpBanquets(tester, t, name: 'Malika Rahimova', size: size);
      await openEvent(tester, today);
      await tapKey(tester, 'banquet-settlement');
      await shot(tester, '${device}_settlement');
    });
  }
}
