import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'support/harness.dart';

/// Ikkala rejimning suratlari (planshet, televizor, telefon; qorong'i va yorug'). Faqat `DISPLAY_SHOTS_DIR` berilsa
/// ishlaydi (oddiy `flutter test` da o'tkazib yuboriladi):
///
///   DISPLAY_SHOTS_DIR=/tmp/shots flutter test test/screenshots_test.dart
///
/// Shriftlar Flutter SDK'dagi Roboto va Material ikonkalar (testning standart "Ahem" shrifti harf o'rniga to'rtburchak
/// chizadi). Suratlar kodni tekshirmaydi, ko'z bilan ko'rish uchun; to'g'riligini boshqa testlar tekshiradi.
void main() {
  final String? out = Platform.environment['DISPLAY_SHOTS_DIR'];
  final String fonts =
      Platform.environment['FLUTTER_MATERIAL_FONTS'] ??
      '/opt/flutter/bin/cache/artifacts/material_fonts';

  Future<void> loadFonts() async {
    final FontLoader roboto = FontLoader('Roboto');
    for (final String f in <String>['Regular', 'Medium', 'Bold', 'Black']) {
      final File file = File('$fonts/Roboto-$f.ttf');
      roboto.addFont(
        Future<ByteData>.value(ByteData.sublistView(file.readAsBytesSync())),
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
  }

  final GlobalKey shot = GlobalKey();

  Future<void> save(WidgetTester tester, String name) async {
    await tester.pumpAndSettle();
    await tester.runAsync(() async {
      final RenderRepaintBoundary boundary =
          shot.currentContext!.findRenderObject()! as RenderRepaintBoundary;
      final ui.Image image = await boundary.toImage();
      final ByteData? bytes = await image.toByteData(
        format: ui.ImageByteFormat.png,
      );
      Directory(out!).createSync(recursive: true);
      File('$out/$name.png').writeAsBytesSync(bytes!.buffer.asUint8List());
    });
  }

  Widget wrap(Widget app) => RepaintBoundary(key: shot, child: app);

  final List<Map<String, Object?>> lines = <Map<String, Object?>>[
    FakeDisplayBackend.line(
      "Lag'mon",
      qty: '2',
      price: '32000.00',
      total: '64000.00',
      modifiers: <String>['Achchiq', 'Qo\'shimcha go\'sht'],
    ),
    FakeDisplayBackend.line(
      'Somsa',
      qty: '3',
      price: '9000.00',
      total: '27000.00',
    ),
    FakeDisplayBackend.line(
      "Qo'y go'shti",
      qty: '0.750',
      unit: 'kg',
      price: '160000.00',
      total: '120000.00',
      note: 'piyozsiz',
    ),
    FakeDisplayBackend.line(
      'Choy',
      qty: '2',
      price: '5000.00',
      total: '10000.00',
    ),
  ];

  group(
    'screenshots',
    skip: out == null ? 'DISPLAY_SHOTS_DIR is not set' : false,
    () {
      setUpAll(loadFonts);

      for (final (String theme, bool dark) in <(String, bool)>[
        ('dark', true),
        ('light', false),
      ]) {
        testWidgets('cart, payment with QR and thanks on a tablet ($theme)', (
          WidgetTester tester,
        ) async {
          final DisplayHarness h = DisplayHarness(
            settings: <String, Object?>{'dark': dark},
          );
          h.backend.queueCalling = false;
          await h.pump(tester, wrap: wrap);
          h.backend.publish(lines: lines, total: '221000.00', queueNumber: 17);
          await h.advance(tester, const Duration(milliseconds: 600));
          await save(tester, 'cart_tablet_$theme');

          h.backend.publish(
            state: 'payment',
            lines: lines,
            subtotal: '221000.00',
            discount: '21000.00',
            total: '200000.00',
            queueNumber: 17,
            payment: <String, Object?>{
              'method': 'qr',
              'qr': <String, Object?>{
                'payload': 'https://my.click.uz/services/pay?service_id=12345&merchant_id=9876&amount=200000&transaction_param=ORD-20261022-0017',
                'label': 'Click',
                'provider': 'click',
              },
            },
          );
          await h.advance(tester, const Duration(milliseconds: 600));
          await save(tester, 'payment_qr_tablet_$theme');

          h.backend.publish(
            state: 'paid',
            lines: lines,
            total: '200000.00',
            queueNumber: 17,
            payment: <String, Object?>{
              'method': 'cash',
              'tendered': '250000.00',
              'change': '50000.00',
            },
          );
          await h.advance(tester, const Duration(milliseconds: 600));
          await save(tester, 'paid_tablet_$theme');
        });
      }

      testWidgets('cart and payment on a phone (portrait)', (
        WidgetTester tester,
      ) async {
        final DisplayHarness h = DisplayHarness(
          settings: <String, Object?>{'dark': false},
        );
        h.backend.queueCalling = false;
        await h.pump(tester, size: const Size(412, 892), wrap: wrap);
        h.backend.publish(lines: lines, total: '221000.00', queueNumber: 17);
        await h.advance(tester, const Duration(milliseconds: 600));
        await save(tester, 'cart_phone_light');
        h.backend.publish(
          state: 'payment',
          lines: lines,
          total: '221000.00',
          queueNumber: 17,
          payment: <String, Object?>{
            'method': 'qr',
            'qr': <String, Object?>{
              'payload': 'https://pay.example/uz/abc',
              'label': 'Payme',
              'provider': 'payme',
            },
          },
        );
        await h.advance(tester, const Duration(milliseconds: 600));
        await save(tester, 'payment_qr_phone_light');
      });

      testWidgets('the ready-numbers board on a TV and a tablet', (
        WidgetTester tester,
      ) async {
        final DisplayHarness h = DisplayHarness(
          settings: <String, Object?>{'font_scale': 1.0},
        );
        h.backend.customerDisplay = false;
        h.backend.preparing.addAll(<Map<String, Object?>>[
          for (final int n in <int>[23, 24, 26, 27, 28, 29, 31, 32, 33, 34, 35])
            FakeDisplayBackend.entry(n, 'preparing'),
        ]);
        h.backend.ready.addAll(<Map<String, Object?>>[
          for (final (int n, int min) in <(int, int)>[
            (19, 6),
            (20, 5),
            (21, 4),
            (22, 3),
            (25, 2),
            (30, 1),
          ])
            FakeDisplayBackend.entry(
              n,
              'ready',
              readyAt: DateTime.utc(
                2026,
                10,
                22,
                13,
              ).subtract(Duration(minutes: min)),
            ),
        ]);
        await h.pump(tester, size: const Size(1920, 1080), wrap: wrap);
        await save(tester, 'board_tv_dark');

        h.backend.markReady(26);
        await h.advance(tester, const Duration(milliseconds: 700));
        await save(tester, 'board_tv_dark_announce');

        h.settings.update(h.settings.value.copyWith(dark: false));
        await h.advance(tester, const Duration(milliseconds: 500));
        await save(tester, 'board_tv_light_announce');

        tester.view
          ..physicalSize = const Size(800, 1280)
          ..devicePixelRatio = 1;
        await h.advance(tester, const Duration(milliseconds: 500));
        await save(tester, 'board_tablet_portrait_light');
      });

      testWidgets('pairing and the idle greeting', (WidgetTester tester) async {
        final DisplayHarness h = DisplayHarness();
        h.backend.queueCalling = false;
        await h.pump(tester, paired: false, wrap: wrap);
        await save(tester, 'pairing_dark');
      });

      testWidgets('greeting', (WidgetTester tester) async {
        final DisplayHarness h = DisplayHarness();
        h.backend.queueCalling = false;
        await h.pump(tester, wrap: wrap);
        await save(tester, 'welcome_dark');
      });
    },
  );
}
