import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P3-38: the booking confirmation for the guest — the repository calls (path, Idempotency-Key, body, parsing), the
/// booking and bootstrap fields, and the fake backend's contract (room fee per day type, unassigned room, min spend,
/// pre-order, deposits net of refunds, channels, `no_channel`, idempotent send, auto-send on confirm).
void main() {
  late Harness h;

  setUp(() async {
    h = Harness();
    await h.signIn();
  });

  group('BookingRepository confirmation', () {
    final Map<String, Object?> confirmation = <String, Object?>{
      'text': "Bron tasdiqlandi ✅\n💵 Xona puli: 300 000 so'm (dam olish kuni narxi)",
      'lines': <Object?>[
        <String, Object?>{
          'kind': 'header',
          'label': '',
          'value': 'Bron tasdiqlandi',
        },
        <String, Object?>{
          'kind': 'room_fee',
          'label': 'Xona puli',
          'value': "300 000 so'm (dam olish kuni narxi)",
        },
      ],
      'room_fee': <String, Object?>{
        'amount': '300000.00',
        'day_type': 'weekend',
        'tariff_id': 7,
      },
      'channels': <String, Object?>{'telegram': true, 'sms': false},
    };

    test('GET reads the text, lines, room fee and channels', () async {
      h.adapter.respond = (RequestOptions o) => FakeResponse(
        200,
        <String, Object?>{'success': true, 'message': '', 'data': confirmation},
      );
      final PosBookingConfirmation c = await h.pos.bookings.confirmation('bk');
      expect(h.last.method, 'GET');
      expect(h.last.uri.path, '/api/v1/pos/bookings/bk/confirmation');
      expect(h.last.headers['Idempotency-Key'], isNull);
      expect(c.text, startsWith('Bron tasdiqlandi'));
      expect(c.lines, hasLength(2));
      expect(c.lines.last.kind, PosConfirmationLine.roomFee);
      expect(c.lines.last.label, 'Xona puli');
      expect(c.roomFee!.amount, const PosMoney.som(300000));
      expect(c.roomFee!.dayType, 'weekend');
      expect(c.roomFee!.tariffId, 7);
      expect(c.channels.telegram, isTrue);
      expect(c.channels.sms, isFalse);
      expect(c.channels.any, isTrue);
    });

    test('a missing room fee and channels read as none', () {
      final PosBookingConfirmation c = PosBookingConfirmation.fromJson(
        PosJson.of(<String, Object?>{
          'text': 'Bron tasdiqlandi',
          'lines': <Object?>[],
          'room_fee': null,
        }),
      );
      expect(c.roomFee, isNull);
      expect(c.channels.any, isFalse);
    });

    test(
      'send: POST with an Idempotency-Key, the channel only when given',
      () async {
        h.adapter.respond = (RequestOptions o) =>
            const FakeResponse(200, <String, Object?>{
              'success': true,
              'message': 'Tasdiq yuborildi.',
              'data': <String, Object?>{'channel': 'telegram', 'queued': true},
            });
        final PosConfirmationSent sent = await h.pos.bookings.sendConfirmation(
          'bk',
        );
        expect(h.last.method, 'POST');
        expect(h.last.uri.path, '/api/v1/pos/bookings/bk/confirmation/send');
        expect(h.last.headers['Idempotency-Key'], matches(uuidV4));
        expect(Harness.bodyOf(h.last), isEmpty);
        expect(sent.channel, 'telegram');
        expect(sent.queued, isTrue);

        await h.pos.bookings.sendConfirmation(
          'bk',
          channel: 'sms',
          idempotencyKey: 'k-1',
        );
        expect(h.last.headers['Idempotency-Key'], 'k-1');
        expect(Harness.bodyOf(h.last), <String, Object?>{'channel': 'sms'});
        expect(PosConfirmationSent.label('telegram'), 'Telegram');
        expect(PosConfirmationSent.label('sms'), 'SMS');
      },
    );

    test('a lost answer hands back its key for the very same retry', () async {
      h.adapter.offline = true;
      final PosNetworkException e = await h.pos.bookings
          .sendConfirmation('bk')
          .then<PosNetworkException>(
            (_) => fail('must fail'),
            onError: (Object e) => e as PosNetworkException,
          );
      expect(e.idempotencyKey, matches(uuidV4));
      expect(e.idempotencyKey, h.last.headers['Idempotency-Key']);
    });

    test('no_channel is a PosRejected with its own words', () async {
      h.adapter.respond = (RequestOptions o) =>
          const FakeResponse(422, <String, Object?>{
            'success': false,
            'message': 'The given data was invalid.',
            'data': <String, Object?>{'reason': 'no_channel'},
          });
      await expectLater(
        h.pos.bookings.sendConfirmation('bk'),
        throwsA(
          isA<PosRejected>()
              .having((PosRejected e) => e.reason, 'reason', 'no_channel')
              .having(
                (PosRejected e) => e.message,
                'message',
                contains('Telegram botiga ulanmagan'),
              ),
        ),
      );
    });

    test('booking JSON: confirmation_sent_at and confirmation_channel', () {
      PosBooking parse(Map<String, Object?> extra) => PosBooking.fromJson(
        PosJson.of(<String, Object?>{
          'uuid': 'bk',
          'status': 'confirmed',
          'starts_at': '2026-10-31T14:00:00Z',
          'ends_at': '2026-10-31T18:00:00Z',
          ...extra,
        }),
      );
      final PosBooking sent = parse(<String, Object?>{
        'confirmation_sent_at': '2026-10-24T14:42:00Z',
        'confirmation_channel': 'telegram',
      });
      expect(sent.confirmationSentAt, DateTime.utc(2026, 10, 24, 14, 42));
      expect(sent.confirmationChannel, 'telegram');
      final PosBooking fresh = parse(<String, Object?>{
        'confirmation_sent_at': null,
        'confirmation_channel': null,
      });
      expect(fresh.confirmationSentAt, isNull);
      expect(fresh.confirmationChannel, isNull);
    });

    test('bootstrap: pos_settings.booking_confirmation and its defaults', () {
      final PosSettings set = PosSettings.fromJson(
        PosJson.of(<String, Object?>{
          'booking_confirmation': <String, Object?>{
            'auto_send': false,
            'sms': true,
            'cancel_policy': '24 soat oldin bekor qilinsa bo\'nak qaytariladi.',
          },
        }),
      );
      expect(set.bookingConfirmation.autoSend, isFalse);
      expect(set.bookingConfirmation.sms, isTrue);
      expect(set.bookingConfirmation.cancelPolicy, contains('24 soat'));
      final PosSettings old = PosSettings.fromJson(
        PosJson.of(<String, Object?>{}),
      );
      expect(old.bookingConfirmation.autoSend, isTrue);
      expect(old.bookingConfirmation.sms, isFalse);
      expect(old.bookingConfirmation.cancelPolicy, isNull);
    });
  });

  group('fake backend confirmation', () {
    late FakeCashierBackend backend;

    setUp(() {
      backend = FakeCashierBackend(h.state);
    });

    String line(PosBookingConfirmation c, String kind) =>
        c.lines.firstWhere((PosConfirmationLine l) => l.kind == kind).value;

    test('bootstrap carries booking_confirmation and holidays', () async {
      h.state
        ..holidays = <String>['2026-11-02']
        ..bookingConfirmation['cancel_policy'] = 'Qoida';
      final PosBootstrap b = await h.pos.bootstrap.load();
      expect(b.branch.settings.holidays, <String>['2026-11-02']);
      expect(b.branch.settings.bookingConfirmation.autoSend, isTrue);
      expect(b.branch.settings.bookingConfirmation.sms, isFalse);
      expect(b.branch.settings.bookingConfirmation.cancelPolicy, 'Qoida');
    });

    test(
      'the room fee follows the day type: weekday, weekend, holiday',
      () async {
        backend.roomFees
          ..['weekday'] = '300000.00'
          ..['weekend'] = '400000.00'
          ..['holiday'] = '500000.00';
        h.state.holidays = <String>['2026-11-02'];
        // Thursday 19:00 Tashkent
        final String thursday = backend.seedBooking(
          tableId: 2,
          start: DateTime.utc(2026, 10, 22, 14),
          guests: 12,
        );
        PosBookingConfirmation c = await h.pos.bookings.confirmation(thursday);
        expect(c.roomFee!.amount, const PosMoney.som(300000));
        expect(c.roomFee!.dayType, 'weekday');
        expect(line(c, 'room_fee'), "300 000 so'm (hafta ichi narxi)");
        expect(line(c, 'time'), 'Payshanba, 22-oktabr, 19:00–00:00');
        expect(line(c, 'room'), '"2-xona" (10 kishilik) · 12 kishi');
        expect(line(c, 'service_charge'), '10%');
        expect(
          c.text,
          contains("💵 Xona puli: 300 000 so'm (hafta ichi narxi)"),
        );

        final String saturday = backend.seedBooking(
          tableId: 1,
          start: DateTime.utc(2026, 10, 31, 14),
          hours: 4,
        );
        c = await h.pos.bookings.confirmation(saturday);
        expect(c.roomFee!.dayType, 'weekend');
        expect(line(c, 'room_fee'), "400 000 so'm (dam olish kuni narxi)");
        expect(line(c, 'time'), 'Shanba, 31-oktabr, 19:00–23:00');

        // Monday 2 November is a holiday in pos_settings
        final String holiday = backend.seedBooking(
          tableId: 3,
          start: DateTime.utc(2026, 11, 2, 10),
        );
        c = await h.pos.bookings.confirmation(holiday);
        expect(c.roomFee!.dayType, 'holiday');
        expect(c.roomFee!.amount, const PosMoney.som(500000));
        expect(line(c, 'room_fee'), "500 000 so'm (bayram kuni narxi)");
      },
    );

    test('an unassigned room has no fee line; min spend, pre-order, deposits net of refunds', () async {
      final PosBooking created = await h.pos.bookings.create(
        PosBookingInput(
          startsAt: DateTime.utc(2026, 10, 23, 14),
          contactName: 'Dilshod',
          contactPhone: '901112233',
          guestsPlanned: 6,
          minSpend: const PosMoney.som(1500000),
        ),
      );
      PosBookingConfirmation c = await h.pos.bookings.confirmation(
        created.uuid,
      );
      expect(line(c, 'room'), 'keyin belgilanadi · 6 kishi');
      expect(c.roomFee, isNull);
      expect(
        c.lines.where(
          (PosConfirmationLine l) => l.kind == PosConfirmationLine.roomFee,
        ),
        isEmpty,
      );
      expect(line(c, 'min_spend'), "1 500 000 so'm");

      final String uuid = backend.seedBooking(
        tableId: 2,
        start: DateTime.utc(2026, 10, 23, 14),
        deposit: '500000.00',
      );
      await h.pos.bookings.addItem(
        uuid,
        productVariantId: 3,
        quantity: PosQuantity.parse('3'),
      );
      c = await h.pos.bookings.confirmation(uuid);
      expect(line(c, 'pre_order'), startsWith('Osh 3'));
      expect(line(c, 'deposit'), "500 000 so'm");
      // A refunded part is not "taken" any more
      final Map<String, Object?> payment =
          (backend.bookings[uuid]!['payments']! as List<Object?>).single!
              as Map<String, Object?>;
      payment['refunded_amount'] = '200000.00';
      c = await h.pos.bookings.confirmation(uuid);
      expect(line(c, 'deposit'), "300 000 so'm");
      expect(c.lines.last.kind, 'contact');
      expect(
        c.lines.last.value,
        'Bron raqami: ${FakeCashierBackend.shortCodeOf(uuid)}',
      );
      expect(
        c.text,
        endsWith('📞 Bron raqami: ${uuid.substring(30).toUpperCase()}'),
      );
    });

    test('send: Telegram when the phone is linked, SMS when the branch allows it, else no_channel', () async {
      final String uuid = backend.seedBooking(
        tableId: 2,
        start: DateTime.utc(2026, 10, 23, 14),
      );
      PosBookingConfirmation c = await h.pos.bookings.confirmation(uuid);
      expect(c.channels.any, isFalse);
      await expectLater(
        h.pos.bookings.sendConfirmation(uuid),
        throwsA(
          isA<PosRejected>().having(
            (PosRejected e) => e.reason,
            'reason',
            PosReasons.noChannel,
          ),
        ),
      );
      expect(backend.sentConfirmations, isEmpty);

      h.state.bookingConfirmation['sms'] = true;
      PosConfirmationSent sent = await h.pos.bookings.sendConfirmation(uuid);
      expect(sent.channel, 'sms');

      backend.telegramPhones.add('+998901234567');
      c = await h.pos.bookings.confirmation(uuid);
      expect(c.channels.telegram, isTrue);
      sent = await h.pos.bookings.sendConfirmation(uuid);
      expect(sent.channel, 'telegram');
      final PosBooking b = await h.pos.bookings.get(uuid);
      expect(b.confirmationChannel, 'telegram');
      expect(b.confirmationSentAt, h.now);
      expect(backend.sentConfirmations, hasLength(2));
      expect(
        backend.sentConfirmations.last['text'],
        contains('Xona puli: 300 000'),
      );
    });

    test('the same key sends once; a new key sends again', () async {
      backend.telegramPhones.add('+998901234567');
      final String uuid = backend.seedBooking(
        tableId: 2,
        start: DateTime.utc(2026, 10, 23, 14),
      );
      await h.pos.bookings.sendConfirmation(uuid, idempotencyKey: 'k1');
      await h.pos.bookings.sendConfirmation(uuid, idempotencyKey: 'k1');
      expect(backend.sentConfirmations, hasLength(1));
      await h.pos.bookings.sendConfirmation(uuid);
      expect(backend.sentConfirmations, hasLength(2));
    });

    test(
      'auto-send on confirm when the branch wants it; a change does not resend',
      () async {
        backend.telegramPhones.add('+998901112233');
        final PosBooking requested = await h.pos.bookings.create(
          PosBookingInput(
            diningTableId: 1,
            startsAt: DateTime.utc(2026, 10, 23, 14),
            contactPhone: '901112233',
            confirm: false,
          ),
        );
        expect(requested.confirmationSentAt, isNull);
        final PosBooking confirmed = await h.pos.bookings.confirm(
          requested.uuid,
        );
        expect(confirmed.confirmationChannel, 'telegram');
        expect(backend.sentConfirmations.single['auto'], isTrue);

        await h.pos.bookings.update(
          requested.uuid,
          const PosBookingInput(guestsPlanned: 14),
        );
        expect(backend.sentConfirmations, hasLength(1));

        h.state.bookingConfirmation['auto_send'] = false;
        final PosBooking quiet = await h.pos.bookings.create(
          PosBookingInput(
            diningTableId: 2,
            startsAt: DateTime.utc(2026, 10, 23, 14),
            contactPhone: '901112233',
            confirm: true,
          ),
        );
        expect(quiet.confirmationSentAt, isNull);
        expect(backend.sentConfirmations, hasLength(1));
      },
    );
  });
}
