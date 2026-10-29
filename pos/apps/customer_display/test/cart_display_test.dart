import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_customer_display/src/qr/qr_view.dart';

import 'support/harness.dart';

/// P6-14, 1-rejim: kassaning ikkinchi ekrani: joriy chek, jami, to'lov QR'i, "Rahmat". Real-time hodisa 2 soniyadan tez
/// ekranga chiqadi (soxta soat 1,5 soniyadan ortiq surilmaydi), hodisa yo'qolsa so'rov o'rnini bosadi.
void main() {
  final Finder total = find.byKey(const ValueKey<String>('cart-total'));

  testWidgets(
    'with no cart the screen greets the customer with the store name',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness();
      h.backend.queueCalling = false;
      await h.pump(tester);
      expect(find.text('Osh markazi'), findsOneWidget);
      expect(find.text('Xush kelibsiz!'), findsOneWidget);
      expect(find.byType(CircularProgressIndicator), findsNothing);
    },
  );

  testWidgets(
    'the till adds dishes: the customer sees lines, modifiers, quantity, weight and the total at once',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness();
      h.backend.queueCalling = false;
      await h.pump(tester);
      final DateTime sentAt = h.now;

      h.backend.publish(
        lines: <Map<String, Object?>>[
          FakeDisplayBackend.line(
            "Lag'mon",
            qty: '2',
            price: '32000.00',
            total: '64000.00',
            modifiers: <String>['Achchiq'],
          ),
          FakeDisplayBackend.line(
            "Qo'y go'shti",
            qty: '0.750',
            unit: 'kg',
            price: '5000.00',
            total: '3750.00',
            note: 'piyozsiz',
          ),
        ],
        total: '67750.00',
        queueNumber: 17,
      );
      // The event is a hint; the display refetches it: well inside two seconds, no poll needed
      await h.advance(tester, const Duration(milliseconds: 400));

      expect(h.now.difference(sentAt), lessThan(const Duration(seconds: 2)));
      expect(find.text("Lag'mon"), findsOneWidget);
      expect(find.text('2 ×'), findsOneWidget);
      expect(find.text('+ Achchiq'), findsOneWidget);
      expect(find.text("Qo'y go'shti"), findsOneWidget);
      expect(find.text('0.75 kg'), findsOneWidget);
      expect(find.text('piyozsiz'), findsOneWidget);
      expect(find.text('64 000 so\'m'), findsOneWidget);
      expect(tester.widget<Text>(total).data, '67 750 so\'m');
      expect(find.text('Raqam 17'), findsOneWidget);
      expect(
        h.backend.stateCalls,
        lessThanOrEqualTo(3),
        reason: 'event driven, not a poll storm',
      );
    },
  );

  testWidgets(
    'a discount is shown, the payment screen shows the amount due, the method and a QR to scan',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness();
      h.backend.queueCalling = false;
      await h.pump(tester);

      h.backend.publish(
        state: 'payment',
        lines: <Map<String, Object?>>[
          FakeDisplayBackend.line('Osh', price: '40000.00'),
        ],
        subtotal: '40000.00',
        discount: '4000.00',
        total: '36000.00',
        payment: <String, Object?>{
          'method': 'qr',
          'qr': <String, Object?>{
            'payload': 'https://pay.example/uz/abc',
            'label': 'Click',
            'provider': 'click',
          },
        },
      );
      await h.advance(tester, const Duration(milliseconds: 400));

      expect(find.text("To'lash kerak"), findsOneWidget);
      expect(find.text('Chegirma'), findsOneWidget);
      expect(find.text('-4 000 so\'m'), findsOneWidget);
      expect(tester.widget<Text>(total).data, '36 000 so\'m');
      expect(find.text('QR orqali'), findsOneWidget);
      expect(find.byType(QrView), findsOneWidget);
      expect(find.textContaining('Click orqali'), findsOneWidget);
    },
  );

  testWidgets('cash: what was handed over and the change; no QR', (
    WidgetTester tester,
  ) async {
    final DisplayHarness h = DisplayHarness();
    h.backend.queueCalling = false;
    await h.pump(tester);

    h.backend.publish(
      state: 'payment',
      lines: <Map<String, Object?>>[
        FakeDisplayBackend.line('Osh', price: '69000.00'),
      ],
      total: '69000.00',
      payment: <String, Object?>{
        'method': 'cash',
        'tendered': '100000.00',
        'change': '31000.00',
      },
    );
    await h.advance(tester, const Duration(milliseconds: 400));

    expect(find.text('Naqd pul'), findsOneWidget);
    expect(find.text('Berildi'), findsOneWidget);
    expect(find.text('Qaytim'), findsOneWidget);
    expect(find.text('31 000 so\'m'), findsOneWidget);
    expect(find.byType(QrView), findsNothing);
  });

  testWidgets(
    '"Rahmat" after payment with the change and the queue number, then back to the greeting after the hold',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness();
      h.backend.queueCalling = false;
      await h.pump(tester);

      h.backend.publish(
        state: 'paid',
        lines: <Map<String, Object?>>[
          FakeDisplayBackend.line('Osh', price: '69000.00'),
        ],
        total: '69000.00',
        queueNumber: 17,
        payment: <String, Object?>{
          'method': 'cash',
          'tendered': '100000.00',
          'change': '31000.00',
        },
      );
      await h.advance(tester, const Duration(milliseconds: 400));

      expect(find.text('Rahmat!'), findsOneWidget);
      expect(find.textContaining('Qaytim: 31'), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('paid-queue-number')),
        findsOneWidget,
      );
      expect(find.text('17'), findsOneWidget);

      // The till forgot "next customer": the thanks leave on their own
      await h.advance(tester, const Duration(seconds: 9));
      await tester.pumpAndSettle();
      expect(find.text('Rahmat!'), findsNothing);
      expect(find.text('Xush kelibsiz!'), findsOneWidget);
    },
  );

  testWidgets(
    '"Keyingi mijoz" (idle) clears the screen; a forgotten cart goes stale after half an hour',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness(
        staleCart: const Duration(minutes: 30),
      );
      h.backend.queueCalling = false;
      await h.pump(tester);

      h.backend.publish(
        lines: <Map<String, Object?>>[
          FakeDisplayBackend.line('Osh', price: '40000.00'),
        ],
        total: '40000.00',
      );
      await h.advance(tester, const Duration(milliseconds: 400));
      expect(find.text('Osh'), findsOneWidget);

      h.backend.publish(state: 'idle');
      await h.advance(tester, const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(find.text('Xush kelibsiz!'), findsOneWidget);

      // A cart nobody finished: after 30 minutes (server clock) the display does not show yesterday's order
      h.backend.publish(
        lines: <Map<String, Object?>>[
          FakeDisplayBackend.line('Somsa', price: '9000.00'),
        ],
        total: '9000.00',
      );
      await h.advance(tester, const Duration(milliseconds: 400));
      expect(find.text('Somsa'), findsOneWidget);
      await h.advance(
        tester,
        const Duration(minutes: 31),
        step: const Duration(seconds: 4),
      );
      await tester.pumpAndSettle();
      expect(find.text('Somsa'), findsNothing);
      expect(find.text('Xush kelibsiz!'), findsOneWidget);
    },
  );

  testWidgets(
    'without realtime the display polls and still follows the till within the poll interval',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness(
        realtime: false,
        pollInterval: const Duration(seconds: 2),
      );
      h.backend.queueCalling = false;
      await h.pump(tester);

      h.backend.publish(
        lines: <Map<String, Object?>>[
          FakeDisplayBackend.line('Osh', price: '40000.00'),
        ],
        total: '40000.00',
      );
      expect(find.text('Osh'), findsNothing);
      await h.advance(tester, const Duration(milliseconds: 2300));
      expect(find.text('Osh'), findsOneWidget);
    },
  );

  testWidgets('a display that follows one till ignores the other till', (
    WidgetTester tester,
  ) async {
    final DisplayHarness h = DisplayHarness();
    h.backend
      ..queueCalling = false
      ..tillUuid = 'till-1';
    await h.pump(tester);

    h.backend.publish(
      lines: <Map<String, Object?>>[
        FakeDisplayBackend.line('Boshqa kassa', price: '1000.00'),
      ],
      total: '1000.00',
      terminal: 'till-2',
    );
    final int calls = h.backend.stateCalls;
    await h.advance(tester, const Duration(milliseconds: 400));
    expect(
      h.backend.stateCalls,
      calls,
      reason: 'the hint of another till does not even refetch',
    );

    h.backend.publish(
      lines: <Map<String, Object?>>[
        FakeDisplayBackend.line('Osh', price: '40000.00'),
      ],
      total: '40000.00',
    );
    await h.advance(tester, const Duration(milliseconds: 400));
    expect(find.text('Osh'), findsOneWidget);
  });

  testWidgets(
    'the connection drops: the last cart stays with a small "Aloqa yo\'q", and it heals by itself',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness(
        pollInterval: const Duration(seconds: 2),
      );
      h.backend.queueCalling = false;
      await h.pump(tester);
      h.backend.publish(
        lines: <Map<String, Object?>>[
          FakeDisplayBackend.line('Osh', price: '40000.00'),
        ],
        total: '40000.00',
      );
      await h.advance(tester, const Duration(milliseconds: 400));
      expect(find.text('Osh'), findsOneWidget);

      h.adapter.offline = true;
      h.hub.dropAll();
      await h.advance(tester, const Duration(seconds: 6));
      expect(
        find.byKey(const ValueKey<String>('link-offline')),
        findsOneWidget,
      );
      expect(
        find.text('Osh'),
        findsOneWidget,
        reason: 'the last known cart is not wiped',
      );

      h.adapter.offline = false;
      h.backend.publish(
        lines: <Map<String, Object?>>[
          FakeDisplayBackend.line('Somsa', price: '9000.00'),
        ],
        total: '9000.00',
      );
      await h.advance(tester, const Duration(seconds: 6));
      expect(find.byKey(const ValueKey<String>('link-offline')), findsNothing);
      expect(find.text('Somsa'), findsOneWidget);
    },
  );

  testWidgets(
    'a cart that grows scrolls to the newest line; the layout holds on a phone and a tablet',
    (WidgetTester tester) async {
      final DisplayHarness h = DisplayHarness();
      h.backend.queueCalling = false;
      await h.pump(tester, size: const Size(390, 780));
      h.backend.publish(
        lines: <Map<String, Object?>>[
          for (var i = 1; i <= 14; i++)
            FakeDisplayBackend.line('Taom $i', price: '10000.00'),
        ],
        total: '140000.00',
      );
      await h.advance(tester, const Duration(milliseconds: 600));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(
        find.text('Taom 14'),
        findsOneWidget,
        reason: 'scrolled to the end',
      );
      expect(tester.widget<Text>(total).data, '140 000 so\'m');
    },
  );
}
