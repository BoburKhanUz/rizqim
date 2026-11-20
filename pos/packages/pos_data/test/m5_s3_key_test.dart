import 'dart:convert';

import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

import 'offline_test.dart' show OfflineHarness;

/// Wave M5 stage S3-c (docs/scale/M5_DESIGN.md 4, docs/API.md "Imzolangan navbat"): the key of the signed offline
/// queue on the till. It comes with the pairing answer or, for a till paired before signing existed, from
/// `POST /terminal/sync-key`; the queue is signed only while bootstrap says `offline.signing: true`; the till never
/// asks for another key while its queue is not empty (the server keeps only one older key).
void main() {
  late OfflineHarness h;
  late FakeSyncBackend sync;

  /// [signing]: the server signs from the start (the pairing answer carries the key).
  Future<void> start({bool signing = true}) async {
    h = await OfflineHarness.create();
    FakeCashierBackend(h.state);
    sync = FakeSyncBackend(h.state);
    h.state.offlineSigning = signing;
    h.state.openShift();
    await h.signIn();
  }

  tearDown(() => h.pos.dispose());

  PosSyncKeys keys() => h.pos.syncKeys!;

  Future<void> settle() async {
    for (var i = 0; i < 20; i++) {
      await Future<void>.delayed(Duration.zero);
    }
  }

  /// One takeaway sale queued without the network: one `order.open`.
  Future<PosOrder> sell() => h.pos.orders.open(
    channel: PosChannel.takeaway,
    items: <PosLineInput>[
      PosLineInput(
        variantUuid: FakePosState.variantUuid(1),
        quantity: PosQuantity.parse('1'),
      ),
    ],
    send: true,
  );

  Future<void> backOnline() async {
    h.adapter.offline = false;
    expect((await h.offline.syncNow()).drained, isTrue);
    await settle();
  }

  int keyRequests() => h.adapter.to('POST', '/v1/terminal/sync-key').length;

  group('a till paired on a server that signs', () {
    test('queues signed operations with the pairing key and the server finds them in order', () async {
      await start();
      await h.warmUp();
      expect(h.offline.settings.signing, isTrue);
      expect(keys().current()?.kid, 1);
      expect(keys().current()?.deviceUuid, FakePosState.deviceUuid);

      await h.goOffline();
      await sell();
      await sell();
      final List<SyncOperation> queued = h.offline.store.pending;
      expect(queued, isNotEmpty);
      expect(queued.every((SyncOperation o) => o.seal?.kid == 1), isTrue);

      await backOnline();

      expect(sync.sigStatus.values.toSet(), <String>{'ok'});
      expect(sync.sigStatus.length, queued.length);
      // The pairing key was enough: the till asked for no other
      expect(keyRequests(), 0);
      expect(h.state.syncKid, 1);
    });

    test('the key is in the secure storage only: never in the journal or the copies on the disk', () async {
      await start();
      await h.warmUp();
      await h.goOffline();
      await sell();
      final String key = h.state.syncKey!;

      expect(h.keys.values.values.join('\n'), contains(key));
      expect(h.disk.lines.join('\n'), isNot(contains(key)));
      expect(h.disk.docs.values.join('\n'), isNot(contains(key)));
      expect('${keys().current()}', isNot(contains(key)));
      // Not its bytes in another coat either
      final String hex = base64Decode(key)
          .map((int b) => b.toRadixString(16).padLeft(2, '0'))
          .join();
      expect(h.disk.lines.join('\n'), isNot(contains(hex)));
    });

    test('after a restart the saved key signs on, in the same chain', () async {
      await start();
      await h.warmUp();
      await h.goOffline();
      await sell();
      await backOnline();
      await h.pos.dispose();

      final OfflineHarness again = await OfflineHarness.create(
        disk: h.disk,
        adapter: h.adapter,
        clock: h.clock,
        keys: h.keys,
        restore: true,
      );
      h = again;
      expect(keys().current()?.kid, 1);
      await h.client.auth.signInStaff(userId: 3, pin: h.state.pins[3]!);
      await h.goOffline();
      await sell();
      await backOnline();

      expect(sync.sigStatus.values.toSet(), <String>{'ok'});
      expect(keyRequests(), 0);
    });

    test('unpaired: nothing to sign with', () async {
      await start();
      await h.warmUp();
      expect(keys().current(), isNotNull);

      await h.client.auth.forgetTerminal();
      await settle();

      expect(keys().current(), isNull);
    });
  });

  group('an older server (bootstrap does not say offline.signing)', () {
    test(
      'the queue stays in the old unsigned shape and no key is asked for',
      () async {
        await start(signing: false);
        await h.warmUp();
        expect(h.offline.settings.signing, isFalse);
        expect(keys().current(), isNull);

        await h.goOffline();
        await sell();
        expect(
          h.offline.store.pending.every((SyncOperation o) => o.seal == null),
          isTrue,
        );
        await backOnline();

        expect(sync.sigStatus.values.toSet(), <String>{'missing'});
        expect(keyRequests(), 0);
      },
    );

    test(
      'a till that holds a key does not sign while the server does not say so',
      () async {
        await start();
        // Bootstrap never loaded: the till has the pairing key but has not heard `offline.signing`
        expect(h.client.auth.syncKey, isNotNull);
        expect(keys().current(), isNull);
      },
    );
  });

  group('a till paired before signing existed', () {
    test(
      'fetches its key once bootstrap says signing, and signs from then on',
      () async {
        await start(signing: false);
        await h.warmUp();
        expect(h.client.auth.syncKey, isNull);

        // The server is updated
        h.state.offlineSigning = true;
        await h.pos.session.loadBootstrap();
        await settle();

        expect(h.state.syncKeyCalls, 1);
        expect(keys().current()?.kid, 1);
        // Kept for the next start
        expect(h.keys.values.values.join(), contains(h.state.syncKey!));

        await h.goOffline();
        await sell();
        await backOnline();
        expect(sync.sigStatus.values.toSet(), <String>{'ok'});
        // Asked once, not at every change of the queue
        expect(h.state.syncKeyCalls, 1);
      },
    );

    test(
      'does not ask while its queue is not empty; asks when the queue has gone',
      () async {
        await start(signing: false);
        await h.warmUp();
        await h.goOffline();
        await sell();
        expect(h.offline.store.hasPending, isTrue);

        // The server is updated while the till is cut off; the queue is held back (the sender is stopped) and
        // bootstrap arrives first
        await h.offline.sender.stop();
        h.state.offlineSigning = true;
        h.adapter.offline = false;
        await h.pos.session.loadBootstrap();
        await settle();
        expect(h.offline.settings.signing, isTrue);
        expect(h.state.syncKeyCalls, 0);

        h.offline.sender.start();
        await backOnline();

        expect(h.state.syncKeyCalls, 1);
        expect(keys().current()?.kid, 1);
        // What was queued before the key went as it was
        expect(sync.sigStatus.values.toSet(), <String>{'missing'});

        await h.goOffline();
        await sell();
        await backOnline();
        expect(sync.sigStatus.values.last, 'ok');
      },
    );

    test(
      'a failed request waits before the next try and never stops a sale',
      () async {
        await start(signing: false);
        await h.warmUp();
        h.state
          ..offlineSigning = true
          ..syncKeyFailure = const FakeResponse(500, <String, Object?>{
            'success': false,
            'message': 'Server xatosi',
            'data': <String, Object?>{},
          });
        await h.pos.session.loadBootstrap();
        await settle();
        expect(h.state.syncKeyCalls, 1);

        // More changes at once: no second request yet
        await h.pos.session.loadBootstrap();
        await settle();
        expect(h.state.syncKeyCalls, 1);

        // Selling goes on, unsigned
        await h.goOffline();
        await sell();
        h.state.syncKeyFailure = null;
        h.clock.now = h.clock.now.add(const Duration(seconds: 30));
        await backOnline();
        expect(h.state.syncKeyCalls, 1);
        expect(sync.sigStatus.values.toSet(), <String>{'missing'});

        h.clock.now = h.clock.now.add(const Duration(minutes: 2));
        await h.pos.session.loadBootstrap();
        await settle();
        expect(h.state.syncKeyCalls, 2);
        expect(keys().current()?.kid, 1);
      },
    );

    test(
      'a server that refuses this terminal for good (403) is not asked again',
      () async {
        await start(signing: false);
        await h.warmUp();
        h.state
          ..offlineSigning = true
          ..syncKeyFailure = const FakeResponse(403, <String, Object?>{
            'success': false,
            'message': 'Faqat POS terminali',
            'data': <String, Object?>{'reason': 'terminal_required'},
          });
        await h.pos.session.loadBootstrap();
        await settle();
        expect(h.state.syncKeyCalls, 1);

        h.clock.now = h.clock.now.add(const Duration(hours: 2));
        await h.pos.session.loadBootstrap();
        await settle();
        expect(h.state.syncKeyCalls, 1);
        expect(keys().current(), isNull);
      },
    );
  });

  group('rotation', () {
    test(
      'refused while the queue is not empty: no request leaves the till',
      () async {
        await start();
        await h.warmUp();
        await h.goOffline();
        await sell();
        await h.offline.sender.stop();
        h.adapter.offline = false;

        await expectLater(
          keys().rotate(),
          throwsA(
            isA<PosSyncKeyRefused>()
                .having(
                  (PosSyncKeyRefused e) => e.reason,
                  'reason',
                  PosSyncKeys.queueNotEmpty,
                )
                .having(
                  (PosSyncKeyRefused e) => e.message,
                  'message',
                  "Navbatda yuborilmagan amallar bor: kalit navbat bo'shagandan keyin almashtiriladi.",
                ),
          ),
        );
        expect(h.state.syncKeyCalls, 0);
        expect(keys().current()?.kid, 1);

        h.offline.sender.start();
        await backOnline();
        expect(sync.sigStatus.values.toSet(), <String>{'ok'});
      },
    );

    test('with an empty queue the new key starts its own chain and the server accepts it', () async {
      await start();
      await h.warmUp();
      await h.goOffline();
      await sell();
      await backOnline();

      expect(await keys().rotate(), 2);
      expect(keys().current()?.kid, 2);
      expect(h.state.syncKid, 2);

      await h.goOffline();
      await sell();
      expect(h.offline.store.pending.first.seal?.kid, 2);
      await backOnline();

      expect(sync.sigStatus.values.toSet(), <String>{'ok'});
      expect(sync.signedSeen.values.toSet(), <int>{1, 2});
    });

    test('refused when the server does not sign', () async {
      await start(signing: false);
      await h.warmUp();
      await expectLater(
        keys().rotate(),
        throwsA(
          isA<PosSyncKeyRefused>().having(
            (PosSyncKeyRefused e) => e.reason,
            'reason',
            PosSyncKeys.notSigning,
          ),
        ),
      );
      expect(keyRequests(), 0);
    });
  });

  group(
    'the server rotated the key itself (bootstrap offline.signing_kid)',
    () {
      test('a newer kid on the server makes the till fetch a key once its queue is empty', () async {
        await start();
        h.state.advertiseSigningKid = true;
        await h.warmUp();
        await settle();
        // The same kid on both sides: nothing to do
        expect(h.state.syncKeyCalls, 0);

        // An owner's action later: the server holds kid 2, the till still signs with kid 1
        h.state.rotateSyncKey();
        await h.pos.session.loadBootstrap();
        await settle();

        expect(h.state.syncKeyCalls, 1);
        expect(keys().current()?.kid, 3);
        expect(h.state.syncKid, 3);

        // The copy of bootstrap on the disk still says kid 2: no second request for that
        await settle();
        expect(h.state.syncKeyCalls, 1);

        await h.goOffline();
        await sell();
        await backOnline();
        expect(sync.sigStatus.values.toSet(), <String>{'ok'});
        // The till's key is now ahead of the copy of bootstrap: never a reason to ask again
        expect(h.state.syncKeyCalls, 1);
      });
    },
  );
}
