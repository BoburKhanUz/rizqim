import 'package:dio/dio.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

/// P6-23: bootstrap `staff_pay` va ish vaqtini belgilash (`/pos/clock`, `/pos/clock/in`, `/pos/clock/out`).
void main() {
  late Harness h;

  setUp(() async {
    h = Harness();
    FakeCashierBackend(h.state);
    await h.signIn();
  });

  Map<String, Object?> state({Map<String, Object?>? session, int today = 0}) =>
      <String, Object?>{
        'success': true,
        'message': 'ok',
        'data': <String, Object?>{
          'session': session,
          'today_minutes': today,
          'business_date': '2026-11-14',
        },
      };

  final Map<String, Object?> open = <String, Object?>{
    'uuid': 's-1',
    'branch_id': 1,
    'user_id': 3,
    'user_name': 'Kassir',
    'business_date': '2026-11-14',
    'clock_in_at': '2026-11-14T06:00:00+00:00',
    'clock_out_at': null,
    'abandoned': false,
    'open': true,
    'minutes': 0,
    'source': 'pin',
    'note': null,
  };

  test('bootstrap staff_pay: the clock button and the open session; null without the module', () async {
    expect((await h.pos.session.loadBootstrap()).staffPay, isNull);

    h.state.bootstrapExtras['staff_pay'] = <String, Object?>{
      'clock': true,
      'session': <String, Object?>{
        'uuid': 's-1',
        'business_date': '2026-11-14',
        'clock_in_at': '2026-11-14T06:00:00+00:00',
      },
    };
    final PosStaffPayBlock block =
        (await h.pos.session.loadBootstrap()).staffPay!;
    expect(block.clock, isTrue);
    expect(block.session!.uuid, 's-1');
    expect(block.session!.clockInAt, DateTime.utc(2026, 11, 14, 6));

    h.state.bootstrapExtras['staff_pay'] = <String, Object?>{
      'clock': true,
      'session': null,
    };
    expect((await h.pos.session.loadBootstrap()).staffPay!.session, isNull);
  });

  test(
    'clock in and out with an Idempotency-Key, reading the state back',
    () async {
      h.adapter.respond = (RequestOptions o) =>
          FakeResponse(201, state(session: open));
      PosClockState s = await h.pos.staffClock.clockIn();
      RequestOptions r = h.last;
      expect(r.method, 'POST');
      expect(r.uri.path, '/api/v1/pos/clock/in');
      expect(r.headers['Idempotency-Key'], matches(uuidV4));
      expect(s.clockedIn, isTrue);
      expect(s.session!.open, isTrue);

      h.adapter.respond = (RequestOptions o) =>
          FakeResponse(200, state(today: 510));
      s = await h.pos.staffClock.clockOut(idempotencyKey: 'k-1');
      r = h.last;
      expect(r.uri.path, '/api/v1/pos/clock/out');
      expect(r.headers['Idempotency-Key'], 'k-1');
      expect(s.clockedIn, isFalse);
      expect(s.todayMinutes, 510);

      h.adapter.respond = (RequestOptions o) =>
          FakeResponse(200, state(session: open, today: 30));
      s = await h.pos.staffClock.state();
      expect(h.last.method, 'GET');
      expect(h.last.uri.path, '/api/v1/pos/clock');
      expect(s.businessDate, '2026-11-14');
    },
  );

  test('a second clock-in is a conflict with its reason', () async {
    h.adapter.respond = (RequestOptions o) =>
        const FakeResponse(409, <String, Object?>{
          'success': false,
          'message': 'Siz allaqachon ishdasiz.',
          'data': <String, Object?>{'reason': 'already_clocked_in'},
        });
    await expectLater(
      h.pos.staffClock.clockIn(),
      throwsA(
        isA<PosConflict>().having(
          (PosConflict e) => e.reason,
          'reason',
          'already_clocked_in',
        ),
      ),
    );
  });
}
