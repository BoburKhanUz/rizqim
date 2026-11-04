import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';

import 'package:pos_lan/pos_lan.dart';
import 'package:test/test.dart';

String hex(List<int> b) =>
    b.map((int x) => x.toRadixString(16).padLeft(2, '0')).join();

void main() {
  final Uint8List secret = Uint8List.fromList(
    List<int>.generate(32, (int i) => i),
  );
  const String device = '0f6a6c1e-2b7d-4c1a-9a51-3c2f1e0d9b88';

  test('the keys are the backend\'s, byte for byte (App\\Domain\\Pos\\Lan\\LanKeys, the same vector)', () {
    expect(
      hex(LanKeys.device(secret, device)),
      '8eeeaf0d18e62a6085f622deea049fdc1139322ce3bf301d5a2c493ef9a5554f',
    );
    expect(
      hex(LanKeys.device(secret, device.toUpperCase())),
      hex(LanKeys.device(secret, device)),
    );
    expect(
      hex(LanKeys.beacon(secret)),
      'aec44fd45366bb12d4141bcbb61a9bb00a46b8a53936798ede9ab26be5c01504',
    );
    expect(
      hex(LanKeys.grant(secret)),
      'ab45536716e8567647eb795b765bffb5dc9a9c32e0ffd13a65c235b846979c95',
    );
  });

  group('frames', () {
    final LanChannelKey key = LanChannelKey(LanKeys.device(secret, device));
    const LanFrame frame = LanFrame(
      type: 'op',
      device: device,
      seq: 1793700000123456,
      ts: 1793700000123,
      keyId: 1,
      payload: <String, Object?>{
        'op_id': 'x',
        'payload': <String, Object?>{
          'order': 'o',
          'note': "Qo'shimcha — achchiq",
        },
      },
    );

    test(
      'a sealed frame opens with the same key, its payload is not in the clear',
      () {
        final String text = LanFrameCodec.seal(frame, key, random: Random(1));
        expect(text, isNot(contains('achchiq')));
        expect(text, isNot(contains('op_id')));
        final LanFrame opened = LanFrameCodec.open(
          LanFrameCodec.envelope(text),
          key,
        );
        expect(opened.type, 'op');
        expect(opened.seq, frame.seq);
        expect(opened.payload, frame.payload);
        // A fresh nonce every time: the same frame never looks the same twice
        expect(
          LanFrameCodec.seal(frame, key),
          isNot(LanFrameCodec.seal(frame, key)),
        );
      },
    );

    test('a changed field, payload or signature, or another device\'s key, is refused before decrypting', () {
      final Map<String, Object?> e = LanFrameCodec.envelope(
        LanFrameCodec.seal(frame, key),
      );
      LanFrameException refusal(
        Map<String, Object?> envelope,
        LanChannelKey k,
      ) {
        try {
          LanFrameCodec.open(envelope, k);
        } on LanFrameException catch (x) {
          return x;
        }
        fail('opened');
      }

      expect(
        refusal(<String, Object?>{
          ...e,
          'seq': (e['seq']! as int) + 1,
        }, key).reason,
        'bad_sig',
      );
      expect(
        refusal(<String, Object?>{...e, 'type': 'hello'}, key).reason,
        'bad_sig',
      );
      expect(
        refusal(<String, Object?>{...e, 'device': 'someone-else'}, key).reason,
        'bad_sig',
      );
      final List<int> ct = base64.decode(e['payload']! as String)..[0] ^= 1;
      expect(
        refusal(<String, Object?>{
          ...e,
          'payload': base64.encode(ct),
        }, key).reason,
        'bad_sig',
      );
      expect(
        refusal(<String, Object?>{
          ...e,
          'sig': base64.encode(List<int>.filled(32, 0)),
        }, key).reason,
        'bad_sig',
      );
      final LanChannelKey other = LanChannelKey(
        LanKeys.device(secret, 'b3f1c2d4-0000-4000-8000-000000000001'),
      );
      expect(refusal(e, other).reason, 'bad_sig');
      expect(refusal(e, other).closeCode, LanClose.unauthorized);
    });

    test('a broken envelope is a bad frame', () {
      for (final Object? text in <Object?>[
        'nope',
        '[]',
        '{"v":2}',
        jsonEncode(<String, Object?>{'v': 1, 'kid': 1}),
        42,
      ]) {
        expect(
          () => LanFrameCodec.envelope(text),
          throwsA(
            isA<LanFrameException>().having(
              (LanFrameException e) => e.closeCode,
              'code',
              LanClose.badFrame,
            ),
          ),
        );
      }
    });

    test('the canonical JSON sorts keys at every level', () {
      expect(
        lanCanonicalJson(<String, Object?>{
          'b': 1,
          'a': <String, Object?>{
            'd': 2,
            'c': <Object?>[
              3,
              <String, Object?>{'f': 1, 'e': 0},
            ],
          },
        }),
        '{"a":{"c":[3,{"e":0,"f":1}],"d":2},"b":1}',
      );
    });
  });

  test('replays: a frame outside the window or seen before is refused, a new one taken', () {
    final LanReplayGuard guard = LanReplayGuard(
      window: const Duration(minutes: 2),
    );
    const int now = 1793700000000;
    expect(guard.check('d', 1, now - 1000, now), isNull);
    expect(guard.check('d', 1, now - 1000, now), 'replay');
    expect(guard.check('d', 2, now - 121000, now), 'stale');
    expect(guard.check('d', 3, now + 121000, now), 'stale');
    expect(guard.check('e', 1, now, now), isNull);
    // The hello gets a wider window (the device does not know the hub's clock yet)
    expect(
      guard.check(
        'd',
        4,
        now - 300000,
        now,
        window: const Duration(minutes: 10),
      ),
      isNull,
    );
    final LanSequence seq = LanSequence(DateTime.utc(2026, 11, 3));
    expect(seq.take() + 1, seq.take());
  });

  group('grant', () {
    final Uint8List grantKey = LanKeys.grant(secret);
    // Signed by the backend (LanKeys::signGrant) with the same secret
    const String fromServer =
        'eyJ2IjoxLCJraWQiOjEsImJyYW5jaF9pZCI6MTIsImRldmljZSI6IjBmNmE2YzFlLTJiN2QtNGMxYS05YTUxLTNjMmYxZTBkOWI4OCIsInN0YWZmX2lkIjo0LCJuYW1lIjoiQm90aXIgQWxpeWV2Iiwicm9sZSI6IndhaXRlciIsInBlcm1pc3Npb25zIjpbIm9yZGVycy52aWV3Iiwib3JkZXJzLm1hbmFnZSJdLCJzZXNzaW9uIjo3NywiaWF0IjoxNzkzNzAwMDAwLCJleHAiOjE3OTM4Mjk2MDB9.uCxfjDnTdiPMgW_9tLa8NelGrF6KqO-3-nJs547yads';
    final DateTime now = DateTime.fromMillisecondsSinceEpoch(
      1793700000000 + 3600000,
      isUtc: true,
    );

    (LanGrant?, LanGrantError?) check(
      String token, {
      int branch = 12,
      int kid = 1,
      String dev = device,
      DateTime? at,
    }) => LanGrant.verify(
      token,
      grantKey: grantKey,
      branchId: branch,
      keyId: kid,
      device: dev,
      now: at ?? now,
    );

    test(
      'the server\'s grant is taken with its staff, role and permissions',
      () {
        final (LanGrant? g, LanGrantError? e) = check(fromServer);
        expect(e, isNull);
        expect(g!.staffId, 4);
        expect(g.isWaiter, isTrue);
        expect(g.can('orders.manage'), isTrue);
        expect(g.can('pos.take_payment'), isFalse);
        expect(g.session, 77);
      },
    );

    test('a forged, another device\'s, another branch\'s, an old key\'s or an expired grant is refused', () {
      // Signed with what a client holds (its own device key) instead of the grant key
      final String forged = LanGrant.sign(<String, Object?>{
        'v': 1,
        'kid': 1,
        'branch_id': 12,
        'device': device,
        'staff_id': 2,
        'role': 'manager',
        'permissions': <String>['orders.cancel'],
        'iat': 1,
        'exp': 9999999999,
      }, LanKeys.device(secret, device));
      expect(check(forged).$2, LanGrantError.badSignature);
      final List<String> parts = fromServer.split('.');
      final String other = utf8
          .decode(base64Url.decode(base64Url.normalize(parts[0])))
          .replaceFirst('"staff_id":4', '"staff_id":2');
      expect(
        check(
          '${base64Url.encode(utf8.encode(other)).replaceAll('=', '')}.${parts[1]}',
        ).$2,
        LanGrantError.badSignature,
      );
      expect(
        check(fromServer, dev: 'b3f1c2d4-0000-4000-8000-000000000001').$2,
        LanGrantError.otherDevice,
      );
      expect(check(fromServer, branch: 13).$2, LanGrantError.otherBranch);
      expect(check(fromServer, kid: 2).$2, LanGrantError.otherKey);
      expect(
        check(
          fromServer,
          at: DateTime.fromMillisecondsSinceEpoch(1793829600000, isUtc: true),
        ).$2,
        LanGrantError.expired,
      );
      expect(check('garbage').$2, LanGrantError.malformed);
    });
  });

  group('credentials and beacon', () {
    Map<String, Object?> section({String role = 'client', int branch = 12}) =>
        <String, Object?>{
          'v': 1,
          'branch_id': branch,
          'key_id': 1,
          'role': role,
          'device': device,
          'device_key': base64.encode(LanKeys.device(secret, device)),
          'beacon_key': base64.encode(LanKeys.beacon(secret)),
          'hub': <String, Object?>{
            'device': 'aaaaaaaa-0000-4000-8000-000000000001',
            'name': 'Kassa 1',
            'port': 18480,
          },
          'hub_secret': role == 'hub' ? base64.encode(secret) : null,
          'devices': role == 'hub'
              ? <Object?>[
                  <String, Object?>{
                    'uuid': 'B3F1C2D4-0000-4000-8000-000000000001',
                    'type': 'kds',
                    'name': 'KDS',
                  },
                ]
              : null,
          'grant': 'g',
          'clock_window_seconds': 90,
        };

    test('a client\'s section has only its own key; the hub derives every device\'s and lets in the listed ones', () {
      final LanCredentials client = LanCredentials.fromBootstrap(
        <String, Object?>{'lan': section()},
      )!;
      expect(client.isHub, isFalse);
      expect(client.keyFor(device), LanKeys.device(secret, device));
      expect(client.keyFor('b3f1c2d4-0000-4000-8000-000000000001'), isNull);
      expect(client.grantKey, isNull);
      expect(client.clockWindow, const Duration(seconds: 90));
      expect(
        LanCredentials.fromJson(client.toJson())!.toJson(),
        client.toJson(),
      );

      final LanCredentials hub = LanCredentials.fromJson(section(role: 'hub'))!;
      expect(hub.member('b3f1c2d4-0000-4000-8000-000000000001')?.type, 'kds');
      expect(hub.member(device), isNull); // itself
      expect(hub.member('cccccccc-0000-4000-8000-000000000009'), isNull);
      expect(
        hub.keyFor('b3f1c2d4-0000-4000-8000-000000000001'),
        LanKeys.device(secret, 'b3f1c2d4-0000-4000-8000-000000000001'),
      );
      expect(hub.grantKey, LanKeys.grant(secret));
      // A hub section without its secret, an unknown version or no section: no LAN
      expect(
        LanCredentials.fromJson(<String, Object?>{
          ...section(role: 'hub'),
          'hub_secret': null,
        }),
        isNull,
      );
      expect(
        LanCredentials.fromJson(<String, Object?>{...section(), 'v': 2}),
        isNull,
      );
      expect(
        LanCredentials.fromBootstrap(<String, Object?>{'lan': null}),
        isNull,
      );
    });

    test('a beacon is taken only signed with the branch\'s key, for its branch and hub, and fresh', () {
      final LanCredentials creds = LanCredentials.fromJson(section())!;
      final DateTime now = DateTime.utc(2026, 11, 3, 12);
      final LanBeacon b = LanBeacon(
        branchId: 12,
        keyId: 1,
        hubDevice: 'aaaaaaaa-0000-4000-8000-000000000001',
        host: '192.168.1.20',
        port: 18480,
        ts: now.millisecondsSinceEpoch,
      );
      final String signed = b.sign(creds.beaconKey);
      expect(
        LanBeacon.verify(signed, creds, now: now)?.endpoint,
        const LanEndpoint('192.168.1.20', 18480),
      );
      expect(
        LanBeacon.verify(
          signed.replaceFirst('192.168.1.20', '192.168.1.66'),
          creds,
          now: now,
        ),
        isNull,
      );
      expect(
        LanBeacon.verify(
          b.sign(LanKeys.beacon(Uint8List(32))),
          creds,
          now: now,
        ),
        isNull,
      );
      expect(
        LanBeacon.verify(
          LanBeacon(
            branchId: 13,
            keyId: 1,
            hubDevice: b.hubDevice,
            host: b.host,
            port: b.port,
            ts: b.ts,
          ).sign(creds.beaconKey),
          creds,
          now: now,
        ),
        isNull,
      );
      expect(
        LanBeacon.verify(
          LanBeacon(
            branchId: 12,
            keyId: 1,
            hubDevice: device,
            host: b.host,
            port: b.port,
            ts: b.ts,
          ).sign(creds.beaconKey),
          creds,
          now: now,
        ),
        isNull,
      );
      expect(
        LanBeacon.verify(
          signed,
          creds,
          now: now.add(const Duration(minutes: 11)),
        ),
        isNull,
      );
      expect(
        LanEndpoint.parse(b.link),
        const LanEndpoint('192.168.1.20', 18480),
      );
      expect(
        LanEndpoint.parse('10.0.0.5'),
        const LanEndpoint('10.0.0.5', 18480),
      );
    });
  });

  test('the mode goes back to cloud only after the hub\'s queue reached the server', () {
    LanMode next(
      LanMode c, {
      bool cloud = false,
      bool hub = false,
      bool drained = false,
    }) => LanModeRule.next(
      current: c,
      cloudReachable: cloud,
      hubReachable: hub,
      hubDrained: drained,
    );
    expect(next(LanMode.cloud, cloud: true, hub: true), LanMode.cloud);
    expect(next(LanMode.cloud, hub: true), LanMode.lan);
    expect(next(LanMode.cloud), LanMode.alone);
    expect(next(LanMode.lan, cloud: true, hub: true), LanMode.lan);
    expect(
      next(LanMode.lan, cloud: true, hub: true, drained: true),
      LanMode.cloud,
    );
    expect(next(LanMode.lan, cloud: true), LanMode.cloud);
    expect(next(LanMode.alone, hub: true), LanMode.lan);
    expect(
      LanModeRule.drained(<String, Object?>{'cloud': true, 'pending': 0}),
      isTrue,
    );
    expect(
      LanModeRule.drained(<String, Object?>{'cloud': true, 'pending': 2}),
      isFalse,
    );
  });
}
