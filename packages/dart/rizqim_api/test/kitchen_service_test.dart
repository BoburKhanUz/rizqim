import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/fake_backend.dart';
import 'support/kitchen_fixtures.dart';

void main() {
  late DateTime now;
  late FakeAdapter adapter;
  late RizqimClient client;

  setUp(() async {
    now = DateTime.utc(2026, 10, 20, 7);
    adapter = FakeAdapter((RequestOptions o) {
      if (o.uri.path == '/api/v1/devices/pair') return ok(pairData());
      if (o.uri.path.endsWith('/staff-session')) {
        return ok(staffData(expiresAt: now.add(const Duration(hours: 12))));
      }
      return refuse(404, 'unhandled ${o.uri.path}');
    });
    client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: Dio()..httpClientAdapter = adapter,
      clock: () => now,
    );
    await client.auth.pairTerminal('ABCD-EFGH');
    await client.auth.signInStaff(userId: 5, pin: '4821');
    adapter.requests.clear();
  });

  tearDown(() => client.auth.dispose());

  String? bearer(RequestOptions o) => o.headers['Authorization'] as String?;

  test('tickets come through the generated client with the PIN session token, oldest first', () async {
    adapter.handler = (RequestOptions o) => json(200, <String, Object?>{
      'success': true,
      'message': '',
      'data': <Object?>[
        ticketJson(),
        ticketJson(
          uuid: 'bbbbbbbb-0000-4000-8000-000000000002',
          shortCode: null,
          type: 'dine_in',
          status: 'preparing',
          items: <Map<String, Object?>>[
            itemJson(
              id: 21,
              name: 'Lag\'mon',
              status: 'ready',
              variant: 'katta',
            ),
            itemJson(id: 22, name: 'Non'),
          ],
        ),
      ],
      'meta': <String, Object?>{'truncated': true},
    });

    final KitchenTicketList list = await client.kitchen.tickets(branchId: 3);

    final RequestOptions request = adapter.requests.single;
    expect(request.uri.path, '/api/v1/staff/kitchen/tickets');
    expect(request.uri.queryParameters, <String, String>{'branch_id': '3'});
    expect(bearer(request), 'Bearer staff-token');
    expect(list.truncated, isTrue);
    expect(list.tickets, hasLength(2));

    final KitchenTicket first = list.tickets.first;
    expect(first.code, '#E101');
    expect(first.type, 'delivery');
    expect(first.notes, contains('*****'));
    expect(first.lines.first.quantityLabel, '1.5 kg');
    expect(first.lines.first.quantity, 1.5);
    expect(first.lines.last.modifiers.single.label, 'Limon');
    expect(first.readyCount, 0);
    expect(first.receivedAt, now);
    expect(
      first.waitingAt(now.add(const Duration(seconds: 30))),
      const Duration(seconds: 150),
    );

    final KitchenTicket second = list.tickets.last;
    expect(second.code, '#BBBBBBBB');
    expect(second.lines.first.title, "Lag'mon (katta)");
    expect(second.lines.first.isReady, isTrue);
    expect(second.readyCount, 1);
    expect(second.allLinesReady, isFalse);
  });

  test('include_ready is sent only when asked', () async {
    adapter.handler = (RequestOptions o) => json(200, <String, Object?>{
      'success': true,
      'message': '',
      'data': <Object?>[],
      'meta': <String, Object?>{'truncated': false},
    });
    await client.kitchen.tickets(includeReady: true);
    expect(
      adapter.requests.single.uri.queryParameters['include_ready'],
      'true',
    );
  });

  test('bump and recall return the updated ticket', () async {
    adapter.handler = (RequestOptions o) {
      final bool ready = o.uri.path.endsWith('/ready');
      return ok(
        ticketJson(
          status: 'preparing',
          items: <Map<String, Object?>>[
            itemJson(id: 11, name: 'Osh', status: ready ? 'ready' : 'pending'),
          ],
        ),
      );
    };

    final KitchenTicket bumped = (await client.kitchen.bumpLine(
      'aaaaaaaa-0000-4000-8000-000000000001',
      11,
    ))!;
    expect(adapter.requests.last.method, 'POST');
    expect(
      adapter.requests.last.uri.path,
      '/api/v1/staff/orders/aaaaaaaa-0000-4000-8000-000000000001/items/11/ready',
    );
    expect(bearer(adapter.requests.last), 'Bearer staff-token');
    expect(bumped.lines.single.isReady, isTrue);
    expect(bumped.status, 'preparing');

    final KitchenTicket recalled = (await client.kitchen.recallLine(
      'aaaaaaaa-0000-4000-8000-000000000001',
      11,
    ))!;
    expect(adapter.requests.last.uri.path, endsWith('/items/11/recall'));
    expect(recalled.lines.single.isReady, isFalse);
  });

  test('a change the server made but whose body cannot be read is not an error (null: refetch)', () async {
    adapter.handler = (RequestOptions o) => ok(<String, Object?>{'uuid': 'x'});
    expect(
      await client.kitchen.bumpLine('aaaaaaaa-0000-4000-8000-000000000001', 11),
      isNull,
    );
  });

  test('marking the whole order ready posts status=ready', () async {
    adapter.handler = (RequestOptions o) => ok(<String, Object?>{'uuid': 'x'});
    await client.kitchen.markOrderReady('aaaaaaaa-0000-4000-8000-000000000001');
    final RequestOptions request = adapter.requests.single;
    expect(
      request.uri.path,
      '/api/v1/staff/orders/aaaaaaaa-0000-4000-8000-000000000001/status',
    );
    expect(
      jsonDecode(jsonEncode(request.data)),
      containsPair('status', 'ready'),
    );
  });

  test(
    'a refusal becomes an ApiException with the backend message and reason',
    () async {
      adapter.handler = (RequestOptions o) =>
          refuse(422, "Buyurtma oshxonada emas.", reason: 'kitchen_status');
      await expectLater(
        client.kitchen.recallLine('aaaaaaaa-0000-4000-8000-000000000001', 11),
        throwsA(
          isA<ApiException>()
              .having((ApiException e) => e.statusCode, 'status', 422)
              .having((ApiException e) => e.reason, 'reason', 'kitchen_status')
              .having(
                (ApiException e) => e.message,
                'message',
                'Buyurtma oshxonada emas.',
              ),
        ),
      );
    },
  );

  test('a network failure is an ApiException without a status', () async {
    adapter.handler = (RequestOptions o) => throw DioException.connectionError(
      requestOptions: o,
      reason: 'offline',
    );
    await expectLater(
      client.kitchen.tickets(),
      throwsA(
        isA<ApiException>().having(
          (ApiException e) => e.isNetworkError,
          'network',
          isTrue,
        ),
      ),
    );
  });

  test('one ticket that does not match the spec is skipped and reported; the rest still show', () async {
    adapter.handler = (RequestOptions o) => json(200, <String, Object?>{
      'success': true,
      'message': '',
      'data': <Object?>[
        ticketJson(),
        <String, Object?>{'uuid': 'broken-ticket'},
        'not even an object',
        ticketJson(uuid: 'bbbbbbbb-0000-4000-8000-000000000002'),
      ],
      'meta': <String, Object?>{'truncated': false},
    });
    final KitchenTicketList list = await client.kitchen.tickets();
    expect(list.tickets.map((KitchenTicket t) => t.uuid), <String>[
      'aaaaaaaa-0000-4000-8000-000000000001',
      'bbbbbbbb-0000-4000-8000-000000000002',
    ]);
    expect(list.skipped, hasLength(2));
    expect(list.skipped.first.index, 1);
    expect(list.skipped.first.uuid, 'broken-ticket');
    expect(list.skipped.first.error, isNotNull);
    expect(list.skipped.last.index, 2);
    expect(list.skipped.last.uuid, isNull);
  });

  test('a body that cannot be read at all is an "unexpected response", not a network error', () async {
    adapter.handler = (RequestOptions o) => json(200, <String, Object?>{
      'success': true,
      'message': '',
      'data': <String, Object?>{'tickets': <Object?>[]},
    });
    await expectLater(
      client.kitchen.tickets(),
      throwsA(
        isA<ApiException>()
            .having((ApiException e) => e.statusCode, 'status', 200)
            .having(
              (ApiException e) => e.reason,
              'reason',
              ApiException.unexpectedResponse,
            )
            .having(
              (ApiException e) => e.isUnexpectedResponse,
              'unexpected',
              isTrue,
            )
            .having((ApiException e) => e.isNetworkError, 'network', isFalse)
            .having(
              (ApiException e) => e.message,
              'message',
              contains("Server javobini o'qib bo'lmadi"),
            ),
      ),
    );
  });

  test('a ticket that is ready stops its timer at the server value', () {
    final KitchenTicket t = KitchenTicket.fromJson(
      ticketJson(status: 'ready', waitingSeconds: 600),
      receivedAt: now,
    );
    expect(t.isCooking, isFalse);
    expect(
      t.waitingAt(now.add(const Duration(minutes: 5))),
      const Duration(minutes: 10),
    );
  });
}
