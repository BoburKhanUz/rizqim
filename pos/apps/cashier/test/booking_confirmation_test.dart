import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_cashier/src/features/billing/cashier_ui.dart';
import 'package:rizqim_cashier/src/features/bookings/booking_confirmation.dart';

import 'support/harness.dart';
import 'support/p22.dart';

/// P3-38: the "Tasdiq" section of the booking page — the guest's text with the room fee line, "Yuborish" (Telegram,
/// SMS, no channel, a lost answer), "Nusxa olish" (clipboard), "Chop etish" (a slip on the receipt printer) and the
/// sent status.
void main() {
  /// Thursday 22 October, 19:00 in Tashkent: 2-xona (a room: 300 000 so'm on a weekday).
  String seed(P22 p, {String status = 'confirmed'}) => p.backend.seedBooking(
    tableId: 2,
    start: DateTime.utc(2026, 10, 22, 14),
    name: 'Jamshid aka',
    guests: 12,
    status: status,
  );

  Future<void> open(WidgetTester tester, P22 p, String uuid) async {
    await pumpHome(tester, p.h);
    await openSection(tester, 'Bronlar');
    await tapKey(tester, 'booking-$uuid');
  }

  String preview(WidgetTester tester) => tester
      .widget<SelectableText>(
        find.byKey(const ValueKey<String>('confirmation-text')),
      )
      .data!;

  String? notice(WidgetTester tester) {
    final Finder f = find.byKey(const ValueKey<String>('confirmation-notice'));
    return f.evaluate().isEmpty ? null : tester.widget<NoticeBanner>(f).message;
  }

  testWidgets(
    'the preview has the room fee line; "Yuborish" goes over Telegram and shows when it was sent',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.backend.telegramPhones.add('+998901234567');
      final String uuid = seed(p);
      await open(tester, p, uuid);

      expect(find.text('Tasdiq'), findsOneWidget);
      expect(find.text('Mehmonga tasdiq hali yuborilmagan.'), findsOneWidget);
      final String text = preview(tester);
      expect(text, startsWith('Bron tasdiqlandi ✅'));
      expect(text, contains('📅 Payshanba, 22-oktabr, 19:00–00:00'));
      expect(text, contains('🚪 Xona: "2-xona" (10 kishilik) · 12 kishi'));
      expect(text, contains("💵 Xona puli: 300 000 so'm (hafta ichi narxi)"));

      await tapKey(tester, 'confirmation-send');
      expect(notice(tester), "Telegram'ga yuborildi");
      expect(p.backend.sentConfirmations, hasLength(1));
      expect(p.backend.sentConfirmations.single['channel'], 'telegram');
      expect(
        find.text('Tasdiq yuborilgan: ${hm(p.h.now)}, Telegram'),
        findsOneWidget,
      );
      expect(find.text('Mehmonga tasdiq hali yuborilmagan.'), findsNothing);
      expect(
        p.h.adapter
            .to('POST', '/confirmation/send')
            .single
            .headers['Idempotency-Key'],
        isNotNull,
      );

      // Re-sending is allowed: a new key, a second message
      await tapKey(tester, 'confirmation-send');
      expect(p.backend.sentConfirmations, hasLength(2));
      final List<RequestOptions> sends = p.h.adapter
          .to('POST', '/confirmation/send')
          .toList();
      expect(
        sends.first.headers['Idempotency-Key'],
        isNot(sends.last.headers['Idempotency-Key']),
      );
    },
  );

  testWidgets('SMS when the branch turned it on', (WidgetTester tester) async {
    final P22 p = P22();
    p.state.bookingConfirmation['sms'] = true;
    final String uuid = seed(p);
    await open(tester, p, uuid);
    await tapKey(tester, 'confirmation-send');
    expect(notice(tester), 'SMS yuborildi');
    expect(find.text('Tasdiq yuborilgan: ${hm(p.h.now)}, SMS'), findsOneWidget);
  });

  testWidgets(
    'no channel: the cashier is told to copy or print the text; nothing is sent',
    (WidgetTester tester) async {
      final P22 p = P22();
      final String uuid = seed(p);
      await open(tester, p, uuid);
      expect(
        find.textContaining('Mehmon telefoni Telegram botga ulanmagan'),
        findsOneWidget,
      );
      await tapKey(tester, 'confirmation-send');
      // The server's own reason and what to do instead
      expect(
        notice(tester),
        "Tasdiqni yuborib bo'lmaydi: mehmon Telegram botga ulanmagan, SMS o'chirilgan. "
        'Matnni "Nusxa olish" bilan o\'zingiz yuboring yoki chop etib bering.',
      );
      expect(p.backend.sentConfirmations, isEmpty);
      expect(find.text('Mehmonga tasdiq hali yuborilmagan.'), findsOneWidget);
    },
  );

  testWidgets(
    'a send whose answer was lost goes again with the same key: the guest gets one message',
    (WidgetTester tester) async {
      final P22 p = P22();
      p.backend.telegramPhones.add('+998901234567');
      final String uuid = seed(p);
      var lose = true;
      p.h.adapter.respond = (RequestOptions o) {
        if (!lose ||
            o.method != 'POST' ||
            !o.uri.path.endsWith('/confirmation/send')) {
          return null;
        }
        lose = false;
        p.state.handle(
          o.method,
          o.uri.path,
          o.uri.queryParameters,
          o.data,
          (o.headers['Authorization'] as String?)?.replaceFirst('Bearer ', ''),
          idempotencyKey: o.headers['Idempotency-Key'] as String?,
        );
        throw DioException.connectionError(requestOptions: o, reason: 'lost');
      };
      await open(tester, p, uuid);
      await tapKey(tester, 'confirmation-send');
      expect(notice(tester), startsWith("Aloqa yo'q: tasdiq yuborilgani"));
      expect(p.backend.sentConfirmations, hasLength(1));

      await tapKey(tester, 'confirmation-send');
      expect(notice(tester), "Telegram'ga yuborildi");
      expect(p.backend.sentConfirmations, hasLength(1));
      final List<RequestOptions> sends = p.h.adapter
          .to('POST', '/confirmation/send')
          .toList();
      expect(sends, hasLength(2));
      expect(
        sends.last.headers['Idempotency-Key'],
        sends.first.headers['Idempotency-Key'],
      );
    },
  );

  testWidgets('"Nusxa olish" puts the whole text on the clipboard', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    final String uuid = seed(p);
    final List<String> copied = <String>[];
    tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
      SystemChannels.platform,
      (MethodCall call) async {
        if (call.method == 'Clipboard.setData') {
          copied.add(
            (call.arguments as Map<Object?, Object?>)['text']! as String,
          );
        }
        return null;
      },
    );
    addTearDown(
      () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
        SystemChannels.platform,
        null,
      ),
    );
    await open(tester, p, uuid);
    await tapKey(tester, 'confirmation-copy');
    expect(copied, <String>[preview(tester)]);
    expect(copied.single, contains("Xona puli: 300 000 so'm"));
    expect(find.textContaining('Matn nusxalandi'), findsOneWidget);
    expect(p.backend.sentConfirmations, isEmpty);
  });

  testWidgets(
    '"Chop etish" prints a slip on the receipt printer with the room fee line',
    (WidgetTester tester) async {
      final P22 p = P22();
      final String uuid = seed(p);
      await open(tester, p, uuid);
      await tapKey(tester, 'confirmation-print');
      final String slip = p.receipts.single;
      expect(slip, contains("BRON TASDIG'I"));
      expect(slip, contains('Rohat choyxonasi'));
      expect(slip, matches(RegExp(r"Xona puli: +300 000 so'm")));
      expect(slip, contains('(hafta ichi narxi)'));
      expect(slip, contains('Payshanba, 22-oktabr, 19:00-00:00'));
      expect(slip, contains('Xizmat haqi:'));
      expect(
        slip,
        contains('Bron raqami: ${FakeCashierBackend.shortCodeOf(uuid)}'),
      );
      // No emoji on paper (the printer has no glyph for them)
      expect(slip, isNot(contains('?')));
      expect(slip, isNot(contains('💵')));
      expect(find.text('Tasdiq chop etildi.'), findsOneWidget);
      expect(p.drawerKicks, 0);
    },
  );

  testWidgets(
    'without internet the text cannot be had: "Internet kerak", then it loads on retry',
    (WidgetTester tester) async {
      final P22 p = P22();
      final String uuid = seed(p);
      var offline = true;
      p.h.adapter.respond = (RequestOptions o) {
        if (offline && o.uri.path.endsWith('/confirmation')) {
          throw DioException.connectionError(
            requestOptions: o,
            reason: 'offline',
          );
        }
        return null;
      };
      await open(tester, p, uuid);
      expect(
        find.text('Internet kerak: tasdiq matni serverdan olinadi.'),
        findsOneWidget,
      );
      expect(
        tester
            .widget<ButtonStyleButton>(
              find.descendant(
                of: find.byKey(const ValueKey<String>('confirmation-send')),
                matching: find.bySubtype<ButtonStyleButton>(),
              ),
            )
            .enabled,
        isFalse,
      );

      offline = false;
      await tapText(tester, 'Qayta urinish');
      expect(preview(tester), contains('Xona puli'));
    },
  );

  testWidgets('a requested booking has the section; an arrived one does not', (
    WidgetTester tester,
  ) async {
    final P22 p = P22();
    final String requested = seed(p, status: 'requested');
    final String arrived = p.backend.seedBooking(
      tableId: 3,
      start: DateTime.utc(2026, 10, 22, 12),
      name: 'Olim',
      status: 'arrived',
    );
    await open(tester, p, requested);
    expect(find.byType(BookingConfirmationCard), findsOneWidget);
    expect(preview(tester), startsWith('Bron qabul qilindi'));
    await tester.pageBack();
    await tester.pumpAndSettle();
    await tapKey(tester, 'booking-$arrived');
    expect(find.text('Olim'), findsWidgets);
    expect(find.byType(BookingConfirmationCard), findsNothing);
  });

  test('paper text drops emoji and keeps the words', () {
    expect(paperText('💵 Xona puli'), 'Xona puli');
    expect(paperText('Bron tasdiqlandi ✅'), 'Bron tasdiqlandi');
    expect(paperText('ℹ️ 24 soat oldin'), '24 soat oldin');
    expect(paperText('19:00–23:00 · 12 kishi'), '19:00-23:00 · 12 kishi');
    expect(sentLabel('telegram'), "Telegram'ga yuborildi");
    expect(sentLabel('sms'), 'SMS yuborildi');
    // A generic answer gets the till's words; one that already says "copy" is kept as it is
    expect(noChannelText(''), PosReasons.describe(PosReasons.noChannel));
    expect(
      noChannelText("Mehmonning telefon raqami yo'q."),
      startsWith("Mehmonning telefon raqami yo'q. Matnni \"Nusxa olish\""),
    );
    expect(
      noChannelText("SMS yoqilmagan. Matnni nusxalab o'zingiz yuboring."),
      "SMS yoqilmagan. Matnni nusxalab o'zingiz yuboring.",
    );
  });
}
