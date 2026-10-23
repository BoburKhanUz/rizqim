import 'package:dio/dio.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart' show ApiException;
import 'package:test/test.dart';

import 'support/harness.dart';

void main() {
  late Harness h;

  setUp(() async {
    h = Harness();
    await h.signIn();
  });

  group('Idempotency-Key', () {
    test(
      'every change carries a fresh UUID v4 key, reads carry none',
      () async {
        final PosOrder order = await h.pos.orders.open(
          channel: PosChannel.hall,
          tableId: 1,
          guestsCount: 2,
        );
        final String? first = h.last.headers['Idempotency-Key'] as String?;
        await h.pos.orders.setGuests(
          order.uuid,
          version: order.version,
          guestsCount: 3,
        );
        final String? second = h.last.headers['Idempotency-Key'] as String?;
        await h.pos.orders.get(order.uuid);

        expect(first, matches(uuidV4));
        expect(second, matches(uuidV4));
        expect(second, isNot(first));
        expect(h.last.method, 'GET');
        expect(h.last.headers.containsKey('Idempotency-Key'), isFalse);
      },
    );

    test('a given key is sent as is (retrying the same action)', () async {
      await h.pos.orders.open(
        channel: PosChannel.takeaway,
        idempotencyKey: 'retry-key-1',
      );
      expect(h.last.headers['Idempotency-Key'], 'retry-key-1');
    });

    test(
      'a network failure keeps the key so the same action can be retried',
      () async {
        h.adapter.offline = true;
        final PosNetworkException e = await _throws<PosNetworkException>(
          () => h.pos.orders.open(channel: PosChannel.takeaway),
        );
        expect(e.idempotencyKey, matches(uuidV4));
        expect(e.isNetworkError, isTrue);
        expect(e.message, contains("Serverga ulanib bo'lmadi"));
        expect(h.pos.connectivity.state.online, isFalse);

        h.adapter.offline = false;
        await h.pos.orders.open(
          channel: PosChannel.takeaway,
          idempotencyKey: e.idempotencyKey,
        );
        expect(h.last.headers['Idempotency-Key'], e.idempotencyKey);
        expect(h.pos.connectivity.state.online, isTrue);
      },
    );

    test('newIdempotencyKey makes RFC 4122 v4 ids', () {
      final Set<String> keys = <String>{
        for (var i = 0; i < 200; i++) newIdempotencyKey(),
      };
      expect(keys, hasLength(200));
      expect(keys.every(uuidV4.hasMatch), isTrue);
    });
  });

  group('error mapping', () {
    Future<T> respond<T extends Object>(
      int status,
      String fixtureName,
      Future<Object?> Function() call,
    ) {
      h.adapter.respond = (RequestOptions o) =>
          FakeResponse(status, fixture(fixtureName));
      return _throws<T>(call);
    }

    test(
      '409 version_conflict → PosVersionConflict with the current version',
      () async {
        final PosVersionConflict e = await respond<PosVersionConflict>(
          409,
          'order_conflict',
          () => h.pos.orders.addRound(
            'u',
            version: 1,
            items: const <PosLineInput>[],
          ),
        );
        expect(e.currentVersion, 2);
        expect(e.reason, 'version_conflict');
        expect(e.message, contains('boshqa qurilmada'));
        expect(e.idempotencyKey, matches(uuidV4));
      },
    );

    test(
      '403 approval_required → PosApprovalRequired with the permission',
      () async {
        final PosApprovalRequired e = await respond<PosApprovalRequired>(
          403,
          'void_noapproval',
          () => h.pos.orders.voidItem('u', 1, version: 3, reason: 'xato'),
        );
        expect(e.permission, 'orders.void_item');
        expect(e.message, "Bu amal uchun menejer tasdig'i kerak.");
      },
    );

    test('403 approval rejections → PosApprovalRejected', () async {
      for (final String reason in PosReasons.approvalRejections) {
        h.adapter.respond = (RequestOptions o) =>
            FakeResponse(403, <String, Object?>{
              'success': false,
              'message': 'Tasdiq yaroqsiz.',
              'data': <String, Object?>{'reason': reason},
            });
        final PosApprovalRejected e = await _throws<PosApprovalRejected>(
          () => h.pos.orders.cancel(
            'u',
            version: 1,
            reason: 'x',
            approvalToken: 't',
          ),
        );
        expect(e.reason, reason);
      }
    });

    test('other 409 → PosConflict with data', () async {
      h.adapter.respond = (RequestOptions o) =>
          const FakeResponse(409, <String, Object?>{
            'success': false,
            'message': "Buyurtmada to'lov bor.",
            'data': <String, Object?>{
              'reason': 'has_payments',
              'paid_amount': '261100.00',
            },
          });
      final PosConflict e = await _throws<PosConflict>(
        () => h.pos.orders.cancel('u', version: 1, reason: 'x'),
      );
      expect(e.reason, 'has_payments');
      expect(PosMoney.parse(e.data['paid_amount']), const PosMoney.som(261100));
    });

    test('422 with a reason → PosRejected; server text kept', () async {
      final PosRejected e = await respond<PosRejected>(
        422,
        'closed_round',
        () => h.pos.orders.addRound(
          'u',
          version: 2,
          items: const <PosLineInput>[],
        ),
      );
      expect(e.reason, 'order_closed');
      expect(e.message, startsWith('Buyurtma yopilgan'));
    });

    test('422 field errors → PosValidationException', () async {
      final PosValidationException e = await respond<PosValidationException>(
        422,
        'bad_qty',
        () => h.pos.orders.addRound(
          'u',
          version: 2,
          items: const <PosLineInput>[],
        ),
      );
      expect(e.errors.keys, contains('items.0.quantity'));
      expect(e.firstError, contains('kamida 0.5 kg'));
    });

    test(
      'pin_invalid on approval → PosRejected, approvalFailure gives Uzbek text',
      () async {
        final PosRejected e = await respond<PosRejected>(
          422,
          'approval_bad',
          () => h.pos.approvals.request(
            permission: 'orders.void_item',
            userId: 2,
            pin: '0000',
          ),
        );
        expect(
          approvalFailure(e).message,
          "PIN noto'g'ri. Qayta urinib ko'ring.",
        );
      },
    );

    test('English or empty server text is replaced with Uzbek', () async {
      h.adapter.respond = (RequestOptions o) => const FakeResponse(
        403,
        <String, Object?>{'message': 'This action is unauthorized.'},
      );
      final PosForbidden forbidden = await _throws<PosForbidden>(
        () => h.pos.floor.load(),
      );
      expect(forbidden.message, "Bu amal uchun ruxsat yo'q.");

      h.adapter.respond = (RequestOptions o) =>
          const FakeResponse(404, <String, Object?>{'message': ''});
      final PosNotFound missing = await _throws<PosNotFound>(
        () => h.pos.orders.get('x'),
      );
      expect(missing.message, contains('Topilmadi'));

      h.adapter.respond = (RequestOptions o) =>
          const FakeResponse(500, <String, Object?>{'message': 'Server Error'});
      final PosException server = await _throws<PosException>(
        () => h.pos.floor.load(),
      );
      expect(server.statusCode, 500);
      expect(server.message, contains('Serverda xato'));

      h.adapter.respond = (RequestOptions o) =>
          const FakeResponse(409, <String, Object?>{
            'success': false,
            'message': '',
            'data': <String, Object?>{'reason': 'shift_not_open'},
          });
      final PosConflict conflict = await _throws<PosConflict>(
        () => h.pos.floor.load(),
      );
      expect(conflict.message, PosReasons.describe('shift_not_open'));
    });

    test(
      'an unreadable 2xx body is unexpected_response, not a network error',
      () async {
        h.adapter.respond = (RequestOptions o) =>
            const FakeResponse(200, <String, Object?>{
              'success': true,
              'message': '',
              'data': <String, Object?>{},
            });
        final PosException e = await _throws<PosException>(
          () => h.pos.floor.load(),
        );
        expect(e.isUnexpectedResponse, isTrue);
        expect(e.isNetworkError, isFalse);
        expect(e.statusCode, 200);
      },
    );

    test(
      'PosException is an ApiException (existing catch blocks keep working)',
      () async {
        h.adapter.respond = (RequestOptions o) =>
            FakeResponse(409, fixture('order_conflict'));
        await expectLater(
          h.pos.orders.send('u', version: 1),
          throwsA(isA<ApiException>()),
        );
      },
    );
  });

  test('query nulls are dropped and booleans sent as 1', () async {
    h.adapter.respond = (RequestOptions o) =>
        FakeResponse(200, fixture('booking_cal'));
    await h.pos.bookings.calendar(
      from: '2026-10-22',
      to: '2026-10-23',
      includeCanceled: true,
    );
    expect(h.last.uri.queryParameters, <String, String>{
      'from': '2026-10-22',
      'to': '2026-10-23',
      'include_canceled': '1',
    });
  });
}

Future<T> _throws<T extends Object>(Future<Object?> Function() call) async {
  try {
    await call();
  } on T catch (e) {
    return e;
  }
  fail('Expected $T');
}
