import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';

import 'crypto.dart';

/// WebSocket close codes and reasons of the bridge (README §5).
abstract final class LanClose {
  /// A malformed frame, a frame before `hello`, too big.
  static const int badFrame = 4400;

  /// Unknown device, bad signature, another key version.
  static const int unauthorized = 4401;

  /// Another branch's beacon or grant.
  static const int forbidden = 4403;

  /// The hub serves as many devices as it can already (`too_many_devices`).
  static const int busy = 4429;

  /// The hub stopped (the app closed, the terminal was unpaired).
  static const int goingAway = 1001;
}

/// A frame the receiver refused, with the reason it tells and the WebSocket close code.
class LanFrameException implements Exception {
  const LanFrameException(this.reason, this.closeCode, [this.detail]);

  final String reason;
  final int closeCode;
  final String? detail;

  @override
  String toString() =>
      'LanFrameException($reason${detail == null ? '' : ': $detail'})';
}

/// One message on the bridge, opened (README §5): who sent it ([device]), its sequence number and time (the hub's
/// clock, ms), its type and its payload.
class LanFrame {
  const LanFrame({
    required this.type,
    required this.device,
    required this.seq,
    required this.ts,
    required this.keyId,
    this.payload = const <String, Object?>{},
  });

  final String type;
  final String device;
  final int seq;
  final int ts;
  final int keyId;
  final Map<String, Object?> payload;
}

/// The outer, signed shape of a frame (README §5): `{v, kid, type, device, seq, ts, n, payload, sig}`; the payload
/// is base64 of the encrypted JSON, the signature HMAC-SHA256 over the canonical JSON of the rest.
abstract final class LanFrameCodec {
  static const int version = 1;

  /// A frame larger than this is refused unread (a floor with every order fits in far less).
  static const int maxBytes = 4 << 20;

  static String seal(LanFrame frame, LanChannelKey key, {Random? random}) {
    final Uint8List nonce = lanRandomBytes(LanChannelKey.nonceLength, random);
    final Uint8List cipher = key.xor(
      nonce,
      utf8.encode(jsonEncode(frame.payload)),
    );
    final Map<String, Object?> envelope = <String, Object?>{
      'v': version,
      'kid': frame.keyId,
      'type': frame.type,
      'device': frame.device,
      'seq': frame.seq,
      'ts': frame.ts,
      'n': base64.encode(nonce),
      'payload': base64.encode(cipher),
    };
    final Uint8List sig = key.sign(utf8.encode(lanCanonicalJson(envelope)));
    envelope['sig'] = base64.encode(sig);
    return jsonEncode(envelope);
  }

  /// The envelope of [text], its fields checked for shape (not yet for the signature).
  static Map<String, Object?> envelope(Object? text) {
    if (text is! String) {
      throw const LanFrameException('malformed', LanClose.badFrame, 'not text');
    }
    if (text.length > maxBytes) {
      throw const LanFrameException('too_big', LanClose.badFrame);
    }
    Object? json;
    try {
      json = jsonDecode(text);
    } on FormatException {
      throw const LanFrameException('malformed', LanClose.badFrame, 'not json');
    }
    if (json is! Map) {
      throw const LanFrameException(
        'malformed',
        LanClose.badFrame,
        'not an object',
      );
    }
    final Map<String, Object?> e = json.cast<String, Object?>();
    if (e['v'] != version) {
      throw const LanFrameException('version', LanClose.badFrame);
    }
    if (e['kid'] is! int ||
        e['type'] is! String ||
        e['device'] is! String ||
        e['seq'] is! int ||
        e['ts'] is! int ||
        e['n'] is! String ||
        e['payload'] is! String ||
        e['sig'] is! String) {
      throw const LanFrameException('malformed', LanClose.badFrame, 'fields');
    }
    return e;
  }

  /// Checks the signature of [envelope] with [key] and decrypts its payload.
  static LanFrame open(Map<String, Object?> envelope, LanChannelKey key) {
    final Uint8List? sig = lanFromBase64(envelope['sig']);
    final Map<String, Object?> signed = <String, Object?>{
      for (final MapEntry<String, Object?> e in envelope.entries)
        if (e.key != 'sig') e.key: e.value,
    };
    final Uint8List expected = key.sign(utf8.encode(lanCanonicalJson(signed)));
    if (sig == null || !lanSameBytes(sig, expected)) {
      throw const LanFrameException('bad_sig', LanClose.unauthorized);
    }
    final Uint8List? nonce = lanFromBase64(envelope['n']);
    final Uint8List? cipher = lanFromBase64(envelope['payload']);
    if (nonce == null ||
        nonce.length != LanChannelKey.nonceLength ||
        cipher == null) {
      throw const LanFrameException('malformed', LanClose.badFrame, 'payload');
    }
    Object? payload;
    try {
      payload = jsonDecode(utf8.decode(key.xor(nonce, cipher)));
    } on FormatException {
      payload = null;
    }
    if (payload is! Map) {
      throw const LanFrameException('malformed', LanClose.badFrame, 'payload');
    }
    return LanFrame(
      type: envelope['type']! as String,
      device: (envelope['device']! as String).toLowerCase(),
      seq: envelope['seq']! as int,
      ts: envelope['ts']! as int,
      keyId: envelope['kid']! as int,
      payload: payload.cast<String, Object?>(),
    );
  }
}

/// Replay protection (README §5): a frame's time must be within the window of the receiver's estimate of the hub's
/// clock, and a `(device, seq)` is taken once while it could still be in the window (kept 2 × window).
class LanReplayGuard {
  LanReplayGuard({required this.window});

  final Duration window;
  final Map<String, Map<int, int>> _seen = <String, Map<int, int>>{};
  int _checks = 0;

  /// `null` when [seq] of [device] at [ts] is taken (and remembered); else `stale` or `replay`. [window] overrides the
  /// guard's own (the `hello`, sent before the device knows the hub's clock, gets a wider one).
  String? check(String device, int seq, int ts, int nowMs, {Duration? window}) {
    final int span = (window ?? this.window).inMilliseconds;
    if ((nowMs - ts).abs() > span) return 'stale';
    final Map<int, int> seen = _seen.putIfAbsent(device, () => <int, int>{});
    if (seen.containsKey(seq)) return 'replay';
    seen[seq] = nowMs;
    if (++_checks % 256 == 0) _prune(nowMs);
    return null;
  }

  void _prune(int nowMs) {
    final int keep =
        2 *
        max(window.inMilliseconds, const Duration(minutes: 10).inMilliseconds);
    for (final Map<int, int> seen in _seen.values) {
      seen.removeWhere((int _, int at) => nowMs - at > keep);
    }
    _seen.removeWhere((String _, Map<int, int> seen) => seen.isEmpty);
  }
}

/// The next sequence numbers of a sender: from the microsecond it started, +1 per frame (README §5).
class LanSequence {
  LanSequence(DateTime start) : _next = start.microsecondsSinceEpoch;

  int _next;

  int take() => _next++;
}
