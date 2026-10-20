import 'package:dio/dio.dart';
import 'package:rizqim_api/generated.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/fake_backend.dart';

// The generated Retrofit client (lib/src/generated) must compile, parse the backend envelope, and go through the
// hand-written AuthInterceptor like any other call.
void main() {
  test('the generated client parses responses and carries the right token', () async {
    final DateTime now = DateTime.utc(2026, 10, 20, 12);
    final FakeAdapter adapter = FakeAdapter((RequestOptions o) {
      switch (o.uri.path) {
        case '/api/v1/devices/pair':
          return ok(pairData(), status: 201);
        case '/api/v1/terminal/staff':
          return ok(<Object?>[
            <String, Object?>{
              'id': 5,
              'name': 'Aziz',
              'role': 'cook',
              'staff_code': '1234',
              'photo_url': null,
            },
          ]);
        case '/api/v1/devices/11111111-1111-4111-8111-111111111111/staff-session':
          return ok(staffData(expiresAt: now.add(const Duration(hours: 12))));
        case '/api/v1/orders':
          return json(200, <String, Object?>{
            'success': true,
            'message': 'OK',
            'data': <Object?>[],
            'meta': <String, Object?>{'next_cursor': null, 'per_page': 20},
          });
      }
      return refuse(404, 'unhandled');
    });
    final RizqimClient client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => now,
    );
    await client.auth.pairTerminal('ABCD-EFGH');

    // GET /v1/terminal/staff through the generated TerminalApi: the terminal token is chosen by path
    final GetV1TerminalStaffResponse staff = await client.api.terminal
        .terminalStaff();
    expect(staff.success, isTrue);
    expect(staff.data.single.name, 'Aziz');
    expect(staff.data.single.staffCode, '1234');
    expect(
      adapter.requests.last.headers['Authorization'],
      'Bearer terminal-token',
    );

    await client.auth.signInStaff(userId: 5, pin: '4821');

    // GET /v1/orders (a union response, generated as dynamic): the staff token
    final Object? orders = await client.api.orders.orderIndex();
    expect((orders! as Map<Object?, Object?>)['success'], isTrue);
    expect(
      adapter.requests.last.headers['Authorization'],
      'Bearer staff-token',
    );
    expect(adapter.requests.last.baseUrl, 'https://api.test/api');
  });

  test('enums keep the backend values', () {
    expect(
      OrderStatus.values.map((OrderStatus s) => s.json),
      contains('courier_assigned'),
    );
    expect(
      DeviceType.values.map((DeviceType s) => s.json),
      containsAll(<String>['pos', 'kds']),
    );
  });
}
