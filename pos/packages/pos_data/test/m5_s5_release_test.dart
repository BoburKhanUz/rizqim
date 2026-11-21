import 'package:dio/dio.dart';
import 'package:fake_async/fake_async.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

/// Wave M5 S5-b: the release notice (only a notice) and the "Yangilash" gate.
PosAppRelease rel(String state) => PosAppRelease(
  state: PosReleaseState.of(state),
  current: '1.0.0+1',
  latest: '1.4.0+7',
  minimum: '1.2.0+5',
  url: 'https://pos.rizqim.uz/files/cashier/android/a.apk',
  notes: 'Yangi',
);

void main() {
  group('PosAppRelease.tryParse', () {
    test('reads the server shape', () {
      final PosAppRelease? r = PosAppRelease.tryParse(<String, Object?>{
        'current': '1.0.0+1',
        'latest': '1.4.0+7',
        'minimum': '1.2.0+5',
        'state': 'update_required',
        'url': 'https://pos.rizqim.uz/a.apk',
        'notes': 'Yangi',
        'minimum_from': '2026-12-01T00:00:00+00:00',
      });
      expect(r!.state, PosReleaseState.updateRequired);
      expect(r.isRequired, isTrue);
      expect(r.minimumFrom, DateTime.utc(2026, 12, 1));
      expect(r.notes, 'Yangi');
    });

    test('null, junk and an unknown state', () {
      expect(PosAppRelease.tryParse(null), isNull);
      expect(PosAppRelease.tryParse('x'), isNull);
      expect(PosAppRelease.tryParse(<String, Object?>{'latest': '1'}), isNull);
      expect(
        PosAppRelease.tryParse(<String, Object?>{
          'latest': '1.0.0',
          'url': 'https://x',
          'state': 'brand_new',
          'minimum': null,
          'notes': null,
          'minimum_from': null,
        })!.state,
        PosReleaseState.ok,
      );
    });
  });

  group('PosReleaseRepository', () {
    test('starts empty: null means no notice', () {
      final PosReleaseRepository repo = PosReleaseRepository(
        fetch: () async => null,
      );
      expect(repo.release, isNull);
      expect(repo.notice, isNull);
      repo.dispose();
    });

    test('reads at start and exposes each state', () async {
      PosAppRelease? answer = rel('update_available');
      final PosReleaseRepository repo = PosReleaseRepository(
        fetch: () async => answer,
      );
      final List<PosAppRelease?> seen = <PosAppRelease?>[];
      repo.changes.listen(seen.add);

      await repo.start();
      expect(repo.notice!.state, PosReleaseState.updateAvailable);

      answer = rel('update_required');
      await repo.refresh();
      expect(repo.notice!.state, PosReleaseState.updateRequired);

      answer = rel('ok');
      await repo.refresh();
      expect(repo.release!.state, PosReleaseState.ok);
      expect(repo.notice, isNull, reason: 'ok is no notice');

      answer = null;
      await repo.refresh();
      expect(repo.release, isNull);
      await Future<void>.delayed(Duration.zero);
      expect(seen.length, 4);
      repo.dispose();
    });

    test('the same answer does not emit again', () async {
      final PosReleaseRepository repo = PosReleaseRepository(
        fetch: () async => rel('update_available'),
      );
      final List<PosAppRelease?> seen = <PosAppRelease?>[];
      repo.changes.listen(seen.add);
      await repo.refresh();
      await repo.refresh();
      await Future<void>.delayed(Duration.zero);
      expect(seen.length, 1);
      repo.dispose();
    });

    test('refreshes every 6 hours', () {
      fakeAsync((FakeAsync async) {
        int calls = 0;
        final PosReleaseRepository repo = PosReleaseRepository(
          fetch: () async {
            calls++;
            return rel('update_available');
          },
        );
        repo.start();
        async.flushMicrotasks();
        expect(calls, 1);
        async.elapse(const Duration(hours: 5, minutes: 59));
        expect(calls, 1);
        async.elapse(const Duration(minutes: 1));
        expect(calls, 2);
        async.elapse(const Duration(hours: 12));
        expect(calls, 4);
        repo.dispose();
        async.elapse(const Duration(hours: 12));
        expect(calls, 4, reason: 'no reads after dispose');
      });
    });

    test('an error keeps the last state and never throws', () async {
      bool fail = false;
      final PosReleaseRepository repo = PosReleaseRepository(
        fetch: () async {
          if (fail) throw DioException(requestOptions: RequestOptions());
          return rel('update_required');
        },
      );
      await repo.start();
      fail = true;
      await repo.refresh();
      expect(repo.notice!.state, PosReleaseState.updateRequired);
      repo.dispose();
    });

    test('an error before any answer leaves no notice', () async {
      final PosReleaseRepository repo = PosReleaseRepository(
        fetch: () async => throw StateError('offline'),
      );
      await repo.start();
      expect(repo.release, isNull);
      repo.dispose();
    });

    test('a read in flight is shared', () async {
      int calls = 0;
      final PosReleaseRepository repo = PosReleaseRepository(
        fetch: () async {
          calls++;
          return null;
        },
      );
      await Future.wait(<Future<void>>[repo.refresh(), repo.refresh()]);
      expect(calls, 1);
      repo.dispose();
    });
  });

  test('fromClient reads data.app_release of GET /v1/terminal', () async {
    final Dio dio = Dio()..httpClientAdapter = _Adapter();
    final RizqimClient client = RizqimClient(
      baseUrl: 'https://api.test/api',
      store: MemoryKeyValueStore(),
      dio: dio,
    );
    addTearDown(client.auth.dispose);
    final PosReleaseRepository repo = PosReleaseRepository.fromClient(client);
    await repo.refresh();
    expect(repo.notice!.latest, '1.4.0+7');
    repo.dispose();
  });

  group('canUpdateNow', () {
    test('open with an empty queue and no payment', () {
      final PosUpdateGate g = canUpdateNow(
        pendingOperations: 0,
        paymentInProgress: false,
      );
      expect(g.allowed, isTrue);
      expect(g.reason, isNull);
    });

    test('closed while operations are queued, with the Uzbek reason', () {
      final PosUpdateGate g = canUpdateNow(
        pendingOperations: 3,
        paymentInProgress: false,
      );
      expect(g.allowed, isFalse);
      expect(g.reason, PosUpdateReasons.queued);
      expect(g.reason, contains('navbat'));
    });

    test('closed while an order is mid-payment', () {
      final PosUpdateGate g = canUpdateNow(
        pendingOperations: 0,
        paymentInProgress: true,
      );
      expect(g.allowed, isFalse);
      expect(g.reason, PosUpdateReasons.paying);
    });

    test('the queue reason wins when both hold', () {
      expect(
        canUpdateNow(pendingOperations: 1, paymentInProgress: true).reason,
        PosUpdateReasons.queued,
      );
    });

    test('a terminal without an offline queue has nothing pending', () {
      expect(pendingOperationsOf(null), 0);
    });
  });
}

class _Adapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async => ResponseBody.fromString(
    '{"success":true,"message":"OK","data":{"app_release":{"current":"1.0.0+1",'
    '"latest":"1.4.0+7","minimum":null,"state":"update_available",'
    '"url":"https://pos.rizqim.uz/a.apk","notes":null,"minimum_from":null}}}',
    200,
    headers: <String, List<String>>{
      Headers.contentTypeHeader: <String>[Headers.jsonContentType],
    },
  );

  @override
  void close({bool force = false}) {}
}
