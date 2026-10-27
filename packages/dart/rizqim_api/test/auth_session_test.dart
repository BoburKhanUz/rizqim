import 'dart:async';

import 'package:dio/dio.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/fake_backend.dart';

const String deviceUuid = '22222222-2222-4222-8222-222222222222';

void main() {
  late DateTime now;
  late MemoryKeyValueStore store;
  late FakeAdapter adapter;
  late RizqimClient client;

  RizqimClient build({Duration skew = Duration.zero}) {
    final Dio dio = Dio()..httpClientAdapter = adapter;
    return RizqimClient(
      baseUrl: 'https://api.test/api',
      store: store,
      dio: dio,
      clock: () => now,
      refreshSkew: skew,
    );
  }

  setUp(() {
    now = DateTime.utc(2026, 10, 20, 12);
    store = MemoryKeyValueStore();
    adapter = FakeAdapter(
      (RequestOptions o) => refuse(404, 'unhandled ${o.method} ${o.uri.path}'),
    );
    client = build();
  });

  tearDown(() => client.auth.dispose());

  UserSession userSession({
    String access = 'access-1',
    String refresh = 'refresh-1',
    Duration ttl = const Duration(minutes: 15),
  }) => UserSession(
    accessToken: access,
    accessExpiresAt: now.add(ttl),
    refreshToken: refresh,
    refreshExpiresAt: now.add(const Duration(days: 30)),
    deviceUuid: deviceUuid,
  );

  String? bearer(RequestOptions o) => o.headers['Authorization'] as String?;

  group('refresh token rotation', () {
    test('two concurrent 401s cause exactly one refresh, and both requests succeed with the new token', () async {
      final Completer<void> refreshGate = Completer<void>();
      var rotations = 0;
      adapter.handler = (RequestOptions o) async {
        if (o.uri.path == '/api/v1/auth/refresh') {
          rotations++;
          await refreshGate.future; // hold the refresh open so the second 401 arrives while it is in flight
          return ok(
            tokenData(
              access: 'access-2',
              refresh: 'refresh-2',
              accessExpires: now.add(const Duration(minutes: 15)),
            ),
          );
        }
        return bearer(o) == 'Bearer access-2'
            ? ok(<String, Object?>{'path': o.uri.path})
            : refuse(401, 'Unauthenticated.');
      };
      await client.auth.signInUser(userSession());

      final Future<Response<Object?>> a = client.dio.get<Object?>('/v1/orders');
      final Future<Response<Object?>> b = client.dio.get<Object?>(
        '/v1/addresses',
      );
      // wait until both requests have been rejected (not a fixed sleep: the machine may be slow)
      for (
        var i = 0;
        i < 400 &&
            adapter.count('/api/v1/orders') +
                    adapter.count('/api/v1/addresses') <
                2;
        i++
      ) {
        await Future<void>.delayed(const Duration(milliseconds: 5));
      }
      await Future<void>.delayed(
        const Duration(milliseconds: 30),
      ); // let the second 401 reach the interceptor
      expect(
        rotations,
        1,
        reason: 'the second 401 must wait for the refresh in flight',
      );
      refreshGate.complete();
      final List<Response<Object?>> responses = await Future.wait(
        <Future<Response<Object?>>>[a, b],
      );

      expect(responses.map((Response<Object?> r) => r.statusCode), <int>[
        200,
        200,
      ]);
      expect(adapter.count('/api/v1/auth/refresh'), 1);
      expect(client.auth.refreshCalls, 1);
      expect(client.auth.user!.refreshToken, 'refresh-2');
      // the new pair is already on disk
      expect(
        (await CredentialStorage(store).read()).user!.refreshToken,
        'refresh-2',
      );
    });

    test('a request that failed with a stale token after the refresh finished is retried without refreshing again', () async {
      adapter.handler = (RequestOptions o) {
        if (o.uri.path == '/api/v1/auth/refresh') {
          return ok(
            tokenData(
              access: 'access-2',
              refresh: 'refresh-2',
              accessExpires: now.add(const Duration(minutes: 15)),
            ),
          );
        }
        return bearer(o) == 'Bearer access-2'
            ? ok(<String, Object?>{})
            : refuse(401, 'Unauthenticated.');
      };
      await client.auth.signInUser(userSession());

      // A request that already left with access-1 is rejected after another request has refreshed the pair
      final RequestOptions stale = RequestOptions(
        path: '/v1/orders',
        baseUrl: 'https://api.test/api',
        extra: <String, Object?>{
          'rizqim.sentToken': 'access-1',
          'rizqim.sentKind': 'user',
        },
      );
      await client.dio.get<Object?>('/v1/orders'); // refreshes once
      expect(client.auth.refreshCalls, 1);
      final DioException error = DioException(
        requestOptions: stale,
        response: Response<Object?>(requestOptions: stale, statusCode: 401),
      );
      final Completer<Response<Object?>> done = Completer<Response<Object?>>();
      AuthInterceptor(client.dio, client.auth).onError(
        error,
        _Handler(onResolve: done.complete, onNext: done.completeError),
      );
      expect((await done.future).statusCode, 200);
      expect(
        client.auth.refreshCalls,
        1,
        reason: 'the token had already been rotated',
      );
    });

    test('each refresh spends the latest refresh token (rotation)', () async {
      final List<Object?> spent = <Object?>[];
      var valid = 'access-1'; // the only access token the fake server accepts
      var generation = 1;
      adapter.handler = (RequestOptions o) {
        if (o.uri.path == '/api/v1/auth/refresh') {
          spent.add((o.data as Map<Object?, Object?>)['refresh_token']);
          generation++;
          valid = 'access-$generation';
          return ok(
            tokenData(
              access: valid,
              refresh: 'refresh-$generation',
              accessExpires: now.add(const Duration(minutes: 15)),
            ),
          );
        }
        return bearer(o) == 'Bearer $valid'
            ? ok(<String, Object?>{})
            : refuse(401, 'Unauthenticated.');
      };
      await client.auth.signInUser(userSession());

      await client.dio.get<Object?>(
        '/v1/orders',
      ); // access-1 is valid: no refresh
      expect(spent, isEmpty);
      valid = 'expired-on-the-server';
      await client.dio.get<Object?>(
        '/v1/orders',
      ); // 401 -> refresh(refresh-1) -> retry
      valid = 'expired-again';
      await client.dio.get<Object?>(
        '/v1/orders',
      ); // 401 -> refresh(refresh-2) -> retry

      expect(spent, <Object?>['refresh-1', 'refresh-2']);
      expect(client.auth.user!.refreshToken, 'refresh-3');
    });

    test(
      'the refresh request carries the device uuid and no Authorization header',
      () async {
        adapter.handler = (RequestOptions o) {
          if (o.uri.path == '/api/v1/auth/refresh') {
            return ok(
              tokenData(
                access: 'access-2',
                refresh: 'refresh-2',
                accessExpires: now.add(const Duration(minutes: 15)),
              ),
            );
          }
          return bearer(o) == 'Bearer access-2'
              ? ok(<String, Object?>{})
              : refuse(401, 'Unauthenticated.');
        };
        await client.auth.signInUser(userSession());
        await client.dio.get<Object?>('/v1/orders');

        final RequestOptions refresh = adapter.requests.firstWhere(
          (RequestOptions r) => r.uri.path == '/api/v1/auth/refresh',
        );
        expect(refresh.data, <String, Object?>{
          'refresh_token': 'refresh-1',
          'device_uuid': deviceUuid,
        });
        expect(refresh.headers.containsKey('Authorization'), isFalse);
      },
    );

    test('a dead refresh token signs the user out, clears the disk and surfaces the original 401', () async {
      adapter.handler = (RequestOptions o) =>
          o.uri.path == '/api/v1/auth/refresh'
          ? refuse(401, 'Qayta kiring.', reason: AuthReason.refreshReused)
          : refuse(401, 'Unauthenticated.');
      await client.auth.signInUser(userSession());
      final List<AuthEvent> events = <AuthEvent>[];
      client.auth.events.listen(events.add);

      await expectLater(
        client.dio.get<Object?>('/v1/orders'),
        throwsA(
          isA<DioException>().having(
            (DioException e) => e.response?.statusCode,
            'status',
            401,
          ),
        ),
      );
      await Future<void>.delayed(Duration.zero);

      expect(client.auth.user, isNull);
      expect(store.values, isEmpty);
      expect(events.map((AuthEvent e) => e.kind), <AuthEventKind>[
        AuthEventKind.userSignedOut,
      ]);
      expect(events.single.reason, AuthReason.refreshReused);
    });

    test('two concurrent requests with a dead refresh token still send one refresh', () async {
      adapter.handler = (RequestOptions o) async {
        if (o.uri.path == '/api/v1/auth/refresh') {
          await Future<void>.delayed(const Duration(milliseconds: 20));
          return refuse(
            401,
            'Qayta kiring.',
            reason: AuthReason.refreshExpired,
          );
        }
        return refuse(401, 'Unauthenticated.');
      };
      await client.auth.signInUser(userSession());

      final List<Object?> results = await Future.wait(<Future<Object?>>[
        client.dio
            .get<Object?>('/v1/orders')
            .then<Object?>(
              (Response<Object?> r) => r,
              onError: (Object e) => e,
            ),
        client.dio
            .get<Object?>('/v1/addresses')
            .then<Object?>(
              (Response<Object?> r) => r,
              onError: (Object e) => e,
            ),
      ]);

      expect(results, everyElement(isA<DioException>()));
      expect(adapter.count('/api/v1/auth/refresh'), 1);
    });

    test('a network failure during refresh keeps the tokens', () async {
      adapter.handler = (RequestOptions o) {
        if (o.uri.path == '/api/v1/auth/refresh') {
          throw DioException.connectionError(
            requestOptions: o,
            reason: 'offline',
          );
        }
        return refuse(401, 'Unauthenticated.');
      };
      await client.auth.signInUser(userSession());

      await expectLater(
        client.dio.get<Object?>('/v1/orders'),
        throwsA(isA<DioException>()),
      );

      expect(client.auth.user?.refreshToken, 'refresh-1');
      expect((await CredentialStorage(store).read()).user, isNotNull);
    });

    test(
      'a retried request that gets 401 again is not retried forever',
      () async {
        adapter.handler = (RequestOptions o) =>
            o.uri.path == '/api/v1/auth/refresh'
            ? ok(
                tokenData(
                  access: 'access-2',
                  refresh: 'refresh-2',
                  accessExpires: now.add(const Duration(minutes: 15)),
                ),
              )
            : refuse(401, 'Unauthenticated.');
        await client.auth.signInUser(userSession());

        await expectLater(
          client.dio.get<Object?>('/v1/orders'),
          throwsA(isA<DioException>()),
        );

        expect(adapter.count('/api/v1/auth/refresh'), 1);
        expect(adapter.count('/api/v1/orders'), 2);
      },
    );

    test(
      'refreshes before the request when the access token is about to expire',
      () async {
        client = build(skew: const Duration(seconds: 30));
        adapter.handler = (RequestOptions o) {
          if (o.uri.path == '/api/v1/auth/refresh') {
            return ok(
              tokenData(
                access: 'access-2',
                refresh: 'refresh-2',
                accessExpires: now.add(const Duration(minutes: 15)),
              ),
            );
          }
          return bearer(o) == 'Bearer access-2'
              ? ok(<String, Object?>{})
              : refuse(401, 'Unauthenticated.');
        };
        await client.auth.signInUser(
          userSession(ttl: const Duration(seconds: 10)),
        );

        await client.dio.get<Object?>('/v1/orders');

        expect(
          adapter.count('/api/v1/orders'),
          1,
          reason: 'no wasted 401 round trip',
        );
        expect(client.auth.refreshCalls, 1);
      },
    );

    test('a request without a session carries no token and a plain 401 passes through', () async {
      adapter.handler = (RequestOptions o) => refuse(401, 'Unauthenticated.');
      await expectLater(
        client.dio.get<Object?>('/v1/orders'),
        throwsA(isA<DioException>()),
      );
      expect(
        adapter.requests.single.headers.containsKey('Authorization'),
        isFalse,
      );
      expect(client.auth.refreshCalls, 0);
    });
  });

  group('sign-in calls', () {
    test(
      'carry no token and never trigger a refresh, select-account included',
      () async {
        adapter.handler = (RequestOptions o) => refuse(401, 'Unauthenticated.');
        await client.auth.signInUser(userSession());

        for (final String path in <String>[
          '/v1/auth/phone/send-code',
          '/v1/auth/phone/verify',
          '/v1/auth/phone/select-account',
        ]) {
          await expectLater(
            client.dio.post<Object?>(path, data: <String, Object?>{}),
            throwsA(isA<DioException>()),
          );
          expect(bearer(adapter.requests.last), isNull, reason: path);
        }
        expect(client.auth.refreshCalls, 0);
        expect(adapter.count('/api/v1/auth/refresh'), 0);
      },
    );
  });

  group('terminal pairing and staff sessions', () {
    test('pairs with a code typed without the dash and in lower case, and stores the terminal token', () async {
      adapter.handler = (RequestOptions o) =>
          o.uri.path == '/api/v1/devices/pair'
          ? ok(pairData(), status: 201)
          : refuse(404, 'x');

      final PairedTerminal paired = await client.auth.pairTerminal(
        'abcd efgh',
        name: 'KDS 1',
        platform: 'linux',
        appVersion: '0.1.0',
      );

      expect(paired.token, 'terminal-token');
      expect(paired.store?.name, 'Rizqim Cafe');
      expect(paired.branch?.id, 3);
      expect(adapter.requests.single.data, <String, Object?>{
        'code': 'ABCDEFGH',
        'name': 'KDS 1',
        'platform': 'linux',
        'app_version': '0.1.0',
      });
      expect(
        adapter.requests.single.headers.containsKey('Authorization'),
        isFalse,
      );
      expect(client.auth.isPaired, isTrue);

      // a restart: the terminal comes back from the secure store
      final RizqimClient again = build();
      await again.auth.restore();
      expect(again.auth.terminal?.device.uuid, deviceJson()['uuid']);
      expect(again.auth.terminal?.token, 'terminal-token');
    });

    test('a malformed code never reaches the server', () async {
      await expectLater(
        client.auth.pairTerminal('ABCD-EF'),
        throwsA(isA<InvalidPairingCodeException>()),
      );
      expect(adapter.requests, isEmpty);
    });

    test('the backend refusal is an ApiException with its reason', () async {
      adapter.handler = (RequestOptions o) => refuse(
        422,
        "Kod noto'g'ri yoki muddati o'tgan.",
        reason: AuthReason.pairingInvalid,
      );

      await expectLater(
        client.auth.pairTerminal('ABCD-EFGH'),
        throwsA(
          isA<ApiException>()
              .having((ApiException e) => e.reason, 'reason', 'pairing_invalid')
              .having((ApiException e) => e.statusCode, 'status', 422)
              .having(
                (ApiException e) => e.message,
                'message',
                contains("noto'g'ri"),
              ),
        ),
      );
      expect(client.auth.isPaired, isFalse);
    });

    test('rate limiting exposes Retry-After', () async {
      adapter.handler = (RequestOptions o) => refuse(
        429,
        'Juda ko\'p urinish.',
        headers: <String, List<String>>{
          'retry-after': <String>['42'],
        },
      );

      await expectLater(
        client.auth.pairTerminal('ABCD-EFGH'),
        throwsA(
          isA<ApiException>().having(
            (ApiException e) => e.retryAfter,
            'retryAfter',
            const Duration(seconds: 42),
          ),
        ),
      );
    });

    test('staff PIN session: terminal token on the sign-in call, staff token on order calls, never persisted', () async {
      adapter.handler = (RequestOptions o) {
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
            return o.method == 'POST'
                ? ok(staffData(expiresAt: now.add(const Duration(hours: 12))))
                : ok(null);
          default:
            return ok(<String, Object?>{'ok': true});
        }
      };
      await client.auth.pairTerminal('ABCD-EFGH');

      final List<StaffMember> staff = await client.auth.loadStaff();
      expect(staff.single.staffCode, '1234');
      final StaffSession session = await client.auth.signInStaff(
        userId: 5,
        pin: '4821',
      );
      expect(session.can('kitchen.manage'), isTrue);
      expect(session.can('finance.view'), isFalse);
      expect(session.branchId, 3);

      await client.dio.get<Object?>('/v1/orders');
      await client.dio.get<Object?>(
        '/v1/terminal/staff',
      ); // still the terminal token while a staff member is signed in

      Map<String, String?> auth() => <String, String?>{
        for (final RequestOptions r in adapter.requests) r.uri.path: bearer(r),
      };
      expect(auth()['/api/v1/devices/pair'], isNull);
      expect(auth()['/api/v1/terminal/staff'], 'Bearer terminal-token');
      expect(
        auth()['/api/v1/devices/11111111-1111-4111-8111-111111111111/staff-session'],
        'Bearer terminal-token',
      );
      expect(auth()['/api/v1/orders'], 'Bearer staff-token');
      expect(
        adapter.requests
            .firstWhere(
              (RequestOptions r) =>
                  r.method == 'POST' && r.uri.path.endsWith('staff-session'),
            )
            .data,
        <String, Object?>{'user_id': 5, 'pin': '4821'},
      );

      // not on disk: a restart asks for the PIN again
      expect(store.values.values.single.contains('staff-token'), isFalse);
      final RizqimClient again = build();
      await again.auth.restore();
      expect(again.auth.terminal, isNotNull);
      expect(again.auth.staff, isNull);
    });

    test('the terminal token is never sent to the ordinary API', () async {
      adapter.handler = (RequestOptions o) =>
          o.uri.path == '/api/v1/devices/pair'
          ? ok(pairData(), status: 201)
          : refuse(401, 'Unauthenticated.');
      await client.auth.pairTerminal('ABCD-EFGH');

      await expectLater(
        client.dio.get<Object?>('/v1/orders'),
        throwsA(isA<DioException>()),
      );

      expect(bearer(adapter.requests.last), isNull);
      expect(
        client.auth.isPaired,
        isTrue,
        reason: 'a 401 on an ordinary route says nothing about the terminal',
      );
    });

    test('staff sign-in by staff code', () async {
      adapter.handler = (RequestOptions o) =>
          o.uri.path.endsWith('staff-session')
          ? ok(staffData(expiresAt: now.add(const Duration(hours: 12))))
          : ok(pairData(), status: 201);
      await client.auth.pairTerminal('ABCD-EFGH');
      await client.auth.signInStaff(staffCode: '1234', pin: '4821');
      expect(adapter.requests.last.data, <String, Object?>{
        'staff_code': '1234',
        'pin': '4821',
      });
    });

    test('a rejected staff token ends the staff session, a rejected terminal token unpairs', () async {
      adapter.handler = (RequestOptions o) {
        if (o.uri.path == '/api/v1/devices/pair') {
          return ok(pairData(), status: 201);
        }
        if (o.uri.path.endsWith('staff-session') && o.method == 'POST') {
          return ok(staffData(expiresAt: now.add(const Duration(hours: 12))));
        }
        return refuse(401, 'Unauthenticated.');
      };
      await client.auth.pairTerminal('ABCD-EFGH');
      await client.auth.signInStaff(userId: 5, pin: '4821');
      final List<AuthEvent> events = <AuthEvent>[];
      client.auth.events.listen(events.add);

      await expectLater(
        client.dio.get<Object?>('/v1/orders'),
        throwsA(isA<DioException>()),
      );
      expect(client.auth.staff, isNull);
      expect(client.auth.isPaired, isTrue);

      await expectLater(
        client.dio.get<Object?>('/v1/terminal'),
        throwsA(isA<DioException>()),
      );
      expect(client.auth.isPaired, isFalse);
      expect(store.values, isEmpty);
      await Future<void>.delayed(Duration.zero);
      expect(events.map((AuthEvent e) => e.kind), <AuthEventKind>[
        AuthEventKind.staffSignedOut,
        AuthEventKind.terminalRevoked,
      ]);
    });

    test(
      'an expired staff session is dropped locally before the request',
      () async {
        adapter.handler = (RequestOptions o) {
          if (o.uri.path == '/api/v1/devices/pair') {
            return ok(pairData(), status: 201);
          }
          if (o.uri.path.endsWith('staff-session')) {
            return ok(staffData(expiresAt: now.add(const Duration(hours: 12))));
          }
          return ok(<String, Object?>{});
        };
        await client.auth.pairTerminal('ABCD-EFGH');
        await client.auth.signInStaff(userId: 5, pin: '4821');

        now = now.add(const Duration(hours: 13));
        await client.dio.get<Object?>('/v1/orders');

        expect(client.auth.staff, isNull);
        expect(bearer(adapter.requests.last), isNull);
      },
    );

    test('signing the staff member out works offline', () async {
      var offline = false;
      adapter.handler = (RequestOptions o) {
        if (offline) {
          throw DioException.connectionError(
            requestOptions: o,
            reason: 'offline',
          );
        }
        if (o.uri.path == '/api/v1/devices/pair') {
          return ok(pairData(), status: 201);
        }
        return ok(staffData(expiresAt: now.add(const Duration(hours: 12))));
      };
      await client.auth.pairTerminal('ABCD-EFGH');
      await client.auth.signInStaff(userId: 5, pin: '4821');

      offline = true;
      await client.auth.signOutStaff();

      expect(client.auth.staff, isNull);
    });

    test(
      'a wrong PIN is an ApiException (422/429 with the backend text)',
      () async {
        adapter.handler = (RequestOptions o) {
          if (o.uri.path == '/api/v1/devices/pair') {
            return ok(pairData(), status: 201);
          }
          return json(422, <String, Object?>{
            'success': false,
            'message': "PIN noto'g'ri.",
            'errors': <String, Object?>{
              'pin': <String>["PIN noto'g'ri."],
            },
          });
        };
        await client.auth.pairTerminal('ABCD-EFGH');

        await expectLater(
          client.auth.signInStaff(userId: 5, pin: '0000'),
          throwsA(
            isA<ApiException>().having(
              (ApiException e) => e.errors['pin'],
              'errors',
              <String>["PIN noto'g'ri."],
            ),
          ),
        );
        expect(client.auth.staff, isNull);
      },
    );
  });

  group('storage', () {
    test('a corrupt record reads as signed out', () async {
      store.values[CredentialStorage.key] = '{not json';
      await client.auth.restore();
      expect(client.auth.terminal, isNull);
      expect(client.auth.user, isNull);
    });

    test('signing out the user removes everything from the store', () async {
      adapter.handler = (RequestOptions o) => ok(null);
      await client.auth.signInUser(userSession());
      expect(store.values, isNotEmpty);

      await client.auth.signOutUser();

      expect(store.values, isEmpty);
      expect(adapter.requests.single.uri.path, '/api/v1/auth/logout');
    });

    test(
      'a failing secure store does not break pairing but is reported',
      () async {
        final _BrokenStore broken = _BrokenStore();
        adapter.handler = (RequestOptions o) => ok(pairData(), status: 201);
        final Dio dio = Dio()..httpClientAdapter = adapter;
        final RizqimClient c = RizqimClient(
          baseUrl: 'https://api.test/api',
          store: broken,
          dio: dio,
          clock: () => now,
        );

        await c.auth.pairTerminal('ABCD-EFGH');

        expect(c.auth.isPaired, isTrue);
        expect(c.auth.storageError, isNotNull);
        await c.auth
            .restore(); // reading a broken store is "signed out", not a crash
        expect(c.auth.terminal, isNull);
      },
    );

    test(
      'a failed read is reported and never wipes the saved terminal',
      () async {
        adapter.handler = (RequestOptions o) => ok(pairData(), status: 201);
        final _FlakyStore flaky = _FlakyStore();
        RizqimClient open() => RizqimClient(
          baseUrl: 'https://api.test/api',
          store: flaky,
          dio: Dio()..httpClientAdapter = adapter,
          clock: () => now,
        );
        final RizqimClient first = open();
        await first.auth.pairTerminal('ABCD-EFGH');
        await first.auth.dispose();
        expect(flaky.values, contains(CredentialStorage.key));

        // The keyring is locked for a moment: the read throws, the stored token is still there
        flaky.failReads = true;
        final RizqimClient second = open();
        await second.auth.restore();
        expect(second.auth.isPaired, isFalse);
        expect(
          second.auth.storageError,
          isNotNull,
        ); // the app can say why it asks to pair again

        // Nothing the app does with the empty session may delete the record it could not read
        await second.auth.forgetTerminal();
        expect(second.auth.storageError, isNotNull);
        expect(flaky.deletes, 0);
        expect(flaky.values, contains(CredentialStorage.key));
        await second.auth.dispose();

        // The keyring is back: the same record is read, the error is gone
        flaky.failReads = false;
        final RizqimClient third = open();
        await third.auth.restore();
        expect(third.auth.isPaired, isTrue);
        expect(third.auth.storageError, isNull);
        await third.auth.dispose();
      },
    );

    test(
      'an unreadable (corrupt) record is "signed out", not a storage error',
      () async {
        store.values[CredentialStorage.key] = 'not json';
        await client.auth.restore();
        expect(client.auth.isPaired, isFalse);
        expect(client.auth.storageError, isNull);
      },
    );

    test('forgetting the terminal clears the store and says so', () async {
      adapter.handler = (RequestOptions o) => ok(pairData(), status: 201);
      await client.auth.pairTerminal('ABCD-EFGH');
      final List<AuthEvent> events = <AuthEvent>[];
      client.auth.events.listen(events.add);

      await client.auth.forgetTerminal();
      await Future<void>.delayed(Duration.zero);

      expect(client.auth.isPaired, isFalse);
      expect(store.values, isEmpty);
      expect(events.single.kind, AuthEventKind.terminalForgotten);
    });

    test('UserSession reads a login response', () {
      final UserSession s = UserSession.fromJson(<String, Object?>{
        ...tokenData(
          access: 'a',
          refresh: 'r',
          accessExpires: DateTime.utc(2026, 10, 20, 12, 15),
        ),
        'user': <String, Object?>{},
      });
      expect(s.accessToken, 'a');
      expect(s.refreshToken, 'r');
      expect(s.deviceUuid, deviceUuid);
      expect(s.accessExpiresAt, DateTime.utc(2026, 10, 20, 12, 15));
    });
  });
}

class _Handler extends ErrorInterceptorHandler {
  _Handler({required this.onResolve, required this.onNext});

  final void Function(Response<Object?>) onResolve;
  final void Function(Object) onNext;

  @override
  void resolve(Response<dynamic> response) =>
      onResolve(response as Response<Object?>);

  @override
  void next(DioException err) => onNext(err);
}

class _BrokenStore implements SecureKeyValueStore {
  @override
  Future<String?> read(String key) =>
      Future<String?>.error(StateError('no keyring'));

  @override
  Future<void> write(String key, String value) =>
      Future<void>.error(StateError('no keyring'));

  @override
  Future<void> delete(String key) =>
      Future<void>.error(StateError('no keyring'));
}

/// A store whose reads can fail for a while (a locked or still starting keyring) and that counts deletes.
class _FlakyStore extends MemoryKeyValueStore {
  bool failReads = false;
  int deletes = 0;

  @override
  Future<String?> read(String key) => failReads
      ? Future<String?>.error(StateError('keyring locked'))
      : super.read(key);

  @override
  Future<void> delete(String key) {
    deletes++;
    return super.delete(key);
  }
}
