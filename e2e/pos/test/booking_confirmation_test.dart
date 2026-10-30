/// P3-38: the booking confirmation the guest receives, on the REAL backend through `pos_data`: a booking of a room on a
/// weekday with a pre-order and a deposit; the confirmation's text carries the room fee from the seeded tariff (the
/// weekday price), the pre-ordered dish and the deposit. Sending it "auto" to a phone with no Telegram customer while
/// the branch has SMS off is refused `no_channel`; once the owner switches SMS on (the owner settings API, as the
/// partner web does) the same send goes out by SMS (the stack's SMS driver is `log`: nothing reaches a real provider).
/// The file leaves the branch as it found it: SMS off again, the booking cancelled and its deposit refunded.
@Timeout(Duration(minutes: 3))
library;

import 'dart:io';

import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:test/test.dart';

import 'support/menu.dart';
import 'support/stack.dart';

/// 19:00 in Tashkent (UTC+5) on the first Monday to Thursday at least a week ahead: a weekday, far from the evening's
/// bookings, whatever day the test runs on.
DateTime nextWeekdayEvening() {
  final DateTime tashkent = DateTime.now().toUtc().add(
    const Duration(hours: 5),
  );
  DateTime day = DateTime.utc(
    tashkent.year,
    tashkent.month,
    tashkent.day,
  ).add(const Duration(days: 7));
  while (day.weekday > DateTime.thursday) {
    day = day.add(const Duration(days: 1));
  }
  return DateTime.utc(day.year, day.month, day.day, 14);
}

void main() {
  late StackConfig config;
  late Terminal till;
  late Menu menu;
  late Staff manager;
  late Dio owner;
  late FloorTable room;

  PosBooking? booking;
  PosBookingPayment? deposit;

  /// `PUT /owner/branches/{id}/pos-settings` with the branch's `booking_confirmation.sms` switch.
  Future<Map<String, Object?>> setSms(bool on) async {
    final Response<Object?> response = await owner.put<Object?>(
      '/v1/owner/branches/${config.seed.branchId}/pos-settings',
      options: Options(
        headers: <String, Object?>{'Idempotency-Key': till.pos.http.newKey()},
      ),
      data: <String, Object?>{
        'pos_settings': <String, Object?>{
          'booking_confirmation': <String, Object?>{'sms': on},
        },
      },
    );
    final Map<String, Object?> data =
        ((response.data! as Map<Object?, Object?>)['data']!
                as Map<Object?, Object?>)
            .cast<String, Object?>();
    return (data['pos_settings']! as Map<Object?, Object?>)
        .cast<String, Object?>();
  }

  setUpAll(() async {
    config = StackConfig.fromEnvironment();
    till = await Terminal.open(config, 'cashier');
    manager = config.seed.staff('manager');
    await till.signIn('cashier');
    menu = Menu(
      await till.pos.catalog.load(
        PosChannel.hall,
        menuVersion: till.bootstrap.menuVersion,
      ),
    );
    room = (await till.pos.floor.load()).tables.firstWhere(
      (t) => t.name == 'Kabinka 4',
    );
    // The owner, signed in to the partner web
    owner = Dio(
      BaseOptions(
        baseUrl: config.apiUrl,
        headers: <String, Object?>{
          'Authorization': 'Bearer ${config.seed.ownerToken}',
          'Accept': 'application/json',
        },
      ),
    );
  });

  tearDownAll(() async {
    // Whatever happened above: SMS off again, the booking cancelled and its deposit given back
    await setSms(false);
    final PosBooking? left = booking;
    if (left != null) {
      final PosBooking now = await till.pos.bookings.get(left.uuid);
      if (now.status == 'requested' || now.status == 'confirmed') {
        await till.pos.bookings.cancel(left.uuid, reason: 'Sinov tugadi');
      }
      final PosBookingPayment? taken = deposit;
      if (taken != null) {
        final ApprovalToken approval = await till.pos.approvals.request(
          permission: PosPermissions.refund,
          userId: manager.id,
          pin: manager.pin!,
        );
        await till.pos.bookings.refundDeposit(
          left.uuid,
          taken.uuid,
          reason: 'Sinov tugadi',
          approvalToken: approval.token,
        );
      }
    }
    owner.close();
    await till.close();
  });

  test('the confirmation has the weekday room fee, the pre-order and the deposit; no channel, then SMS', () async {
    final DateTime startsAt = nextWeekdayEvening();
    // Starts with the branch's default: SMS off (an SMS costs money)
    expect(
      (await setSms(false))['booking_confirmation'],
      containsPair('sms', false),
    );

    // A phone that belongs to no customer of the bot: no Telegram
    booking = await till.pos.bookings.create(
      PosBookingInput(
        diningTableId: room.id,
        startsAt: startsAt,
        guestsPlanned: 6,
        eventType: 'tugilgan_kun',
        contactName: 'Sardor aka',
        contactPhone: '+998907771122',
        source: 'phone',
        confirm: true,
      ),
    );
    expect(booking!.status, 'confirmed');
    booking = await till.pos.bookings.addItem(
      booking!.uuid,
      productVariantUuid: menu.variant('Osh').uuid,
      quantity: Dish.of('Osh', 2).quantity,
      dueAt: startsAt,
    );
    final PosDepositResult taken = await till.pos.bookings.takeDeposit(
      booking!.uuid,
      amount: const PosMoney.som(200000),
      tender: 'qr', // a cash deposit needs a shift open on the till (409 deposit_needs_shift)
      idempotencyKey: till.pos.http.newKey(),
    );
    deposit = taken.payment;
    booking = taken.booking;

    // GET .../confirmation: the room fee is the seeded weekday tariff of a room (300 000)
    final PosBookingConfirmation confirmation = await till.pos.bookings
        .confirmation(booking!.uuid);
    expect(confirmation.roomFee, isNotNull);
    expect(confirmation.roomFee!.amount, const PosMoney.som(300000));
    expect(confirmation.roomFee!.dayType, 'weekday');
    expect(confirmation.roomFee!.tariffId, isNotNull);

    String line(String kind) => confirmation.lines
        .firstWhere(
          (PosConfirmationLine l) => l.kind == kind,
          orElse: () => fail('no "$kind" line in ${confirmation.text}'),
        )
        .value;
    expect(line('header'), 'Bron tasdiqlandi');
    expect(line('time'), contains('19:00'));
    expect(line('room'), contains('"Kabinka 4"'));
    expect(line('room_fee'), "300 000 so'm (hafta ichi narxi)");
    expect(line('pre_order'), 'Osh (Choyxona oshi) 2 kg');
    expect(line('deposit'), "200 000 so'm");

    final String text = confirmation.text;
    expect(text, startsWith('Bron tasdiqlandi ✅'));
    expect(text, contains("💵 Xona puli: 300 000 so'm (hafta ichi narxi)"));
    expect(text, contains('🍽 Oldindan buyurtma: Osh (Choyxona oshi) 2 kg'));
    expect(text, contains("💳 Bo'nak olindi: 200 000 so'm"));
    expect(text, contains('Bron raqami: '));
    expect(confirmation.channels.telegram, isFalse);
    expect(confirmation.channels.sms, isFalse);

    // Send "auto": no Telegram customer and SMS off → no_channel, mapped by pos_data
    await expectLater(
      till.pos.bookings.sendConfirmation(booking!.uuid, channel: 'auto'),
      throwsA(
        isA<PosRejected>()
            .having((PosRejected e) => e.statusCode, 'statusCode', 422)
            .having((PosRejected e) => e.reason, 'reason', PosReasons.noChannel)
            .having((PosRejected e) => e.message, 'message', contains('SMS')),
      ),
    );
    expect(
      (await till.pos.bookings.get(booking!.uuid)).confirmationSentAt,
      isNull,
      reason: 'nothing was sent',
    );

    // The owner switches SMS on for the branch: the confirmation can go by SMS now
    expect(
      (await setSms(true))['booking_confirmation'],
      containsPair('sms', true),
    );
    expect(
      (await till.pos.bookings.confirmation(booking!.uuid)).channels.sms,
      isTrue,
    );
    final PosConfirmationSent sent = await till.pos.bookings.sendConfirmation(
      booking!.uuid,
      channel: 'auto',
    );
    expect(sent.channel, 'sms');
    expect(sent.queued, isTrue);

    // The stack's SMS driver only logs the message (scripts/pos-e2e.sh: SMS_DRIVER=log): it reached the SMS service
    final String? logFile = Platform.environment['POS_E2E_LOG_FILE'];
    if (logFile != null && logFile.isNotEmpty) {
      bool logged() =>
          File(logFile)
              .readAsStringSync()
              .contains('SMS Mocked to +998907771122');
      for (var i = 0; i < 50 && !logged(); i++) {
        await Future<void>.delayed(const Duration(milliseconds: 100));
      }
      expect(logged(), isTrue, reason: 'the SMS (log driver) in $logFile');
    }

    booking = await till.pos.bookings.get(booking!.uuid);
    expect(booking!.confirmationChannel, 'sms');
    expect(booking!.confirmationSentAt, isNotNull);
  });
}
