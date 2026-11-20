import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';

/// Wave M5 S3 (docs/scale/M5_DESIGN.md 4): the till signs every operation it queues with a secret it shares with the
/// server, so a journal line changed or removed on the disk is noticed when the queue arrives. HMAC-SHA256 only
/// (`package:crypto`); byte for byte the same as the backend's `App\Domain\Pos\Sync\SyncSignature`, both pinned to
/// backend/tests/Fixtures/sync_signature_vectors.json.
///
/// * raw: the operation's JSON text, serialised ONCE when it is queued; the journal keeps that text and the sender
///   posts it as `signed`. Nobody ever writes that JSON a second time;
/// * mac = HMAC(key, prev || raw), `prev` being the mac of the signed operation before it (its 32 bytes, not its hex
///   text); the first operation under a key uses [firstPrev] = HMAC(key, "rizqim-sync/v1/" + device uuid, lower case);
/// * on the wire and in the journal mac and prev are 64 lower-case hex characters, kid an integer.
abstract final class SyncSignature {
  static const String label = 'rizqim-sync/v1/';

  static const int keyBytes = 32;

  /// What the first operation under [key] carries as its `prev`.
  static String firstPrev(SyncKey key) => _hex(
    Hmac(
      sha256,
      key._bytes,
    ).convert(utf8.encode('$label${key.deviceUuid.toLowerCase()}')).bytes,
  );

  /// The mac of [raw] after [prev] (64 lower-case hex characters, [isMac]).
  static String mac(SyncKey key, String prev, String raw) {
    if (!isMac(prev)) throw ArgumentError.value(prev, 'prev', 'not a mac');
    return _hex(
      Hmac(
        sha256,
        key._bytes,
      ).convert(<int>[..._unhex(prev), ...utf8.encode(raw)]).bytes,
    );
  }

  /// Signs [raw] as the operation after [prev].
  static SyncSeal seal(SyncKey key, String prev, String raw) => SyncSeal(
    raw: raw,
    mac: mac(key, prev, raw),
    prev: prev,
    kid: key.kid,
    device: key.deviceUuid.toLowerCase(),
  );

  /// 64 lower-case hex characters (what a mac or a prev looks like).
  static bool isMac(Object? value) =>
      value is String && value.length == 64 && _macShape.hasMatch(value);

  static final RegExp _macShape = RegExp(r'^[0-9a-f]{64}$');

  static String _hex(List<int> bytes) {
    final StringBuffer out = StringBuffer();
    for (final int b in bytes) {
      out.write(b.toRadixString(16).padLeft(2, '0'));
    }
    return out.toString();
  }

  static Uint8List _unhex(String hex) {
    final Uint8List out = Uint8List(hex.length ~/ 2);
    for (var i = 0; i < out.length; i++) {
      out[i] = int.parse(hex.substring(i * 2, i * 2 + 2), radix: 16);
    }
    return out;
  }
}

/// The till's signing key as the server issued it (the pairing answer's `sync_key`, or `POST /terminal/sync-key`):
/// 32 bytes, its number ([kid]) and the uuid of the device it was issued to. Lives in memory and in the device's
/// secure storage only: it is never written to the journal, never logged ([toString] does not show it).
class SyncKey {
  SyncKey({required List<int> key, required this.kid, required this.deviceUuid})
    : _bytes = Uint8List.fromList(key) {
    if (_bytes.length != SyncSignature.keyBytes) {
      throw ArgumentError('A sync key is ${SyncSignature.keyBytes} bytes');
    }
    if (kid < 1) throw ArgumentError.value(kid, 'kid');
    if (deviceUuid.isEmpty) throw ArgumentError.value(deviceUuid, 'deviceUuid');
  }

  /// From the server's form: standard base64 of 32 bytes. Throws [ArgumentError] (a [FormatException] for text that
  /// is not base64) when it is not a key.
  factory SyncKey.fromBase64(
    String key, {
    required int kid,
    required String deviceUuid,
  }) => SyncKey(key: base64Decode(key), kid: kid, deviceUuid: deviceUuid);

  final Uint8List _bytes;

  /// The key's number on the server (1 for the first one, +1 at every rotation).
  final int kid;

  /// The uuid of the device row the key belongs to.
  final String deviceUuid;

  @override
  String toString() => 'SyncKey(kid: $kid, device: $deviceUuid)';
}

/// Gives the key to sign with NOW, or `null`: the operation is queued unsigned, in the old shape (no key yet, or the
/// server does not advertise `offline.signing`). Asked at every enqueue (and at a renumbering), so a key that arrives
/// or is rotated later is picked up without reopening the store. It must be quick (an in-memory copy); if it throws,
/// the operation is queued unsigned: a signature never stops a sale.
typedef SyncKeyProvider = FutureOr<SyncKey?> Function();

/// The signature of one queued operation: the exact text that was signed, its mac, the mac before it and the key's
/// number. [device]: the device uuid of the key (journal only, not sent; tells a re-paired till's chain apart).
class SyncSeal {
  const SyncSeal({
    required this.raw,
    required this.mac,
    required this.prev,
    required this.kid,
    this.device,
  });

  final String raw;
  final String mac;
  final String prev;
  final int kid;
  final String? device;

  /// The operation as `POST /pos/sync` takes a signed one.
  Map<String, Object?> toWire() => <String, Object?>{
    'signed': raw,
    'mac': mac,
    'prev': prev,
    'kid': kid,
  };
}
