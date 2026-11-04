import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';

/// The keys of a branch's LAN (README §2), all HMAC-SHA256 of the branch secret `S` the server keeps. Byte for byte
/// the same as the backend's `App\Domain\Pos\Lan\LanKeys` (tests pin both to one vector).
abstract final class LanKeys {
  static const String deviceLabel = 'rizqim-lan/v1/device/';
  static const String beaconLabel = 'rizqim-lan/v1/beacon';
  static const String grantLabel = 'rizqim-lan/v1/grant';

  /// A device's own key `K_D` (only that device and the hub, which derives it from `S`).
  static Uint8List device(List<int> secret, String deviceUuid) =>
      hmac(secret, utf8.encode('$deviceLabel${deviceUuid.toLowerCase()}'));

  /// The beacon key `K_B` (every terminal of the branch).
  static Uint8List beacon(List<int> secret) =>
      hmac(secret, utf8.encode(beaconLabel));

  /// The grant key `K_G` (the server and the hub only).
  static Uint8List grant(List<int> secret) =>
      hmac(secret, utf8.encode(grantLabel));

  static Uint8List hmac(List<int> key, List<int> data) =>
      Uint8List.fromList(Hmac(sha256, key).convert(data).bytes);
}

/// The channel between the hub and one device: encrypt-then-MAC with two keys derived from that device's key
/// (README §5). The keystream is HMAC-SHA256 in counter mode over a fresh random 12-byte nonce per frame.
class LanChannelKey {
  LanChannelKey(List<int> deviceKey)
    : _enc = LanKeys.hmac(deviceKey, utf8.encode('enc')),
      _mac = LanKeys.hmac(deviceKey, utf8.encode('mac'));

  final Uint8List _enc;
  final Uint8List _mac;

  static const int nonceLength = 12;

  /// XORs [data] with the keystream of [nonce] (the same call encrypts and decrypts).
  Uint8List xor(List<int> nonce, List<int> data) {
    final Uint8List out = Uint8List(data.length);
    final Hmac prf = Hmac(sha256, _enc);
    final Uint8List block = Uint8List(nonce.length + 4)..setAll(0, nonce);
    var offset = 0;
    for (var counter = 0; offset < data.length; counter++) {
      block
        ..[nonce.length] = (counter >> 24) & 0xff
        ..[nonce.length + 1] = (counter >> 16) & 0xff
        ..[nonce.length + 2] = (counter >> 8) & 0xff
        ..[nonce.length + 3] = counter & 0xff;
      final List<int> stream = prf.convert(block).bytes;
      for (var i = 0; i < stream.length && offset < data.length; i++) {
        out[offset] = data[offset] ^ stream[i];
        offset++;
      }
    }
    return out;
  }

  Uint8List sign(List<int> data) => LanKeys.hmac(_mac, data);
}

/// Equal in constant time (a signature check must not leak where it differs).
bool lanSameBytes(List<int> a, List<int> b) {
  if (a.length != b.length) return false;
  var diff = 0;
  for (var i = 0; i < a.length; i++) {
    diff |= a[i] ^ b[i];
  }
  return diff == 0;
}

/// Random bytes from the platform's secure source (tests may give their own [Random]).
Uint8List lanRandomBytes(int length, [Random? random]) {
  final Random r = random ?? Random.secure();
  return Uint8List.fromList(<int>[
    for (var i = 0; i < length; i++) r.nextInt(256),
  ]);
}

/// base64url without padding (the grant's encoding).
String lanBase64Url(List<int> bytes) =>
    base64Url.encode(bytes).replaceAll('=', '');

/// Decodes base64url with or without padding; `null` when it is not base64url.
Uint8List? lanFromBase64Url(String text) {
  try {
    return base64Url.decode(base64Url.normalize(text));
  } on FormatException {
    return null;
  }
}

/// Decodes standard base64; `null` when it is not.
Uint8List? lanFromBase64(Object? text) {
  if (text is! String) return null;
  try {
    return base64.decode(text);
  } on FormatException {
    return null;
  }
}

/// JSON with every object's keys in alphabetical order and no spaces: what a signature covers (README §5).
String lanCanonicalJson(Object? value) => jsonEncode(_sorted(value));

Object? _sorted(Object? value) {
  if (value is Map) {
    final List<String> keys = <String>[for (final Object? k in value.keys) '$k']
      ..sort();
    return <String, Object?>{for (final String k in keys) k: _sorted(value[k])};
  }
  if (value is List) {
    return <Object?>[for (final Object? v in value) _sorted(v)];
  }
  return value;
}
