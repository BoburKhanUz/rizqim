import 'dart:convert';
import 'dart:math' as math;
import 'dart:typed_data';

/// MD5 and SHA-1 of a string as lower-case hex, for signing the payment providers' sample webhooks (Click's
/// `sign_string`) and checking the `Auth` header of a Click merchant API call. The e2e package has no crypto dependency
/// (P6-49: no new dependencies), so the two classic digests are written out here; checked against the RFC 1321 and
/// FIPS 180 test vectors in stolovaya_lunch_test.dart.
String md5Hex(String input) => _hex(_md5(utf8.encode(input)));

String sha1Hex(String input) => _hex(_sha1(utf8.encode(input)));

String _hex(List<int> bytes) =>
    bytes.map((int b) => b.toRadixString(16).padLeft(2, '0')).join();

int _rotl(int x, int n) => ((x << n) | (x >> (32 - n))) & 0xffffffff;

Uint8List _pad(List<int> message, {required bool bigEndian}) {
  final int bitLength = message.length * 8;
  final int padded = ((message.length + 8) ~/ 64 + 1) * 64;
  final Uint8List out = Uint8List(padded)..setRange(0, message.length, message);
  out[message.length] = 0x80;
  final ByteData view = ByteData.sublistView(out);
  if (bigEndian) {
    view.setUint32(padded - 8, (bitLength ~/ 0x100000000) & 0xffffffff);
    view.setUint32(padded - 4, bitLength & 0xffffffff);
  } else {
    view.setUint32(padded - 8, bitLength & 0xffffffff, Endian.little);
    view.setUint32(
      padded - 4,
      (bitLength ~/ 0x100000000) & 0xffffffff,
      Endian.little,
    );
  }
  return out;
}

const List<int> _md5Shift = <int>[
  7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22, //
  5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20, //
  4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23, //
  6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21, //
];

// floor(abs(sin(i + 1)) * 2^32), RFC 1321
final List<int> _md5K = List<int>.generate(
  64,
  (int i) => (math.sin(i + 1.0).abs() * 4294967296.0).floor() & 0xffffffff,
);

List<int> _md5(List<int> message) {
  final Uint8List data = _pad(message, bigEndian: false);
  final ByteData view = ByteData.sublistView(data);
  int a0 = 0x67452301, b0 = 0xefcdab89, c0 = 0x98badcfe, d0 = 0x10325476;
  for (var chunk = 0; chunk < data.length; chunk += 64) {
    final List<int> m = <int>[
      for (var j = 0; j < 16; j++) view.getUint32(chunk + j * 4, Endian.little),
    ];
    int a = a0, b = b0, c = c0, d = d0;
    for (var i = 0; i < 64; i++) {
      int f;
      int g;
      if (i < 16) {
        f = (b & c) | (~b & 0xffffffff & d);
        g = i;
      } else if (i < 32) {
        f = (d & b) | (~d & 0xffffffff & c);
        g = (5 * i + 1) % 16;
      } else if (i < 48) {
        f = b ^ c ^ d;
        g = (3 * i + 5) % 16;
      } else {
        f = c ^ (b | (~d & 0xffffffff));
        g = (7 * i) % 16;
      }
      f = (f + a + _md5K[i] + m[g]) & 0xffffffff;
      a = d;
      d = c;
      c = b;
      b = (b + _rotl(f, _md5Shift[i])) & 0xffffffff;
    }
    a0 = (a0 + a) & 0xffffffff;
    b0 = (b0 + b) & 0xffffffff;
    c0 = (c0 + c) & 0xffffffff;
    d0 = (d0 + d) & 0xffffffff;
  }
  final ByteData out = ByteData(16)
    ..setUint32(0, a0, Endian.little)
    ..setUint32(4, b0, Endian.little)
    ..setUint32(8, c0, Endian.little)
    ..setUint32(12, d0, Endian.little);
  return out.buffer.asUint8List();
}

List<int> _sha1(List<int> message) {
  final Uint8List data = _pad(message, bigEndian: true);
  final ByteData view = ByteData.sublistView(data);
  int h0 = 0x67452301,
      h1 = 0xefcdab89,
      h2 = 0x98badcfe,
      h3 = 0x10325476,
      h4 = 0xc3d2e1f0;
  final List<int> w = List<int>.filled(80, 0);
  for (var chunk = 0; chunk < data.length; chunk += 64) {
    for (var j = 0; j < 16; j++) {
      w[j] = view.getUint32(chunk + j * 4);
    }
    for (var j = 16; j < 80; j++) {
      w[j] = _rotl(w[j - 3] ^ w[j - 8] ^ w[j - 14] ^ w[j - 16], 1);
    }
    int a = h0, b = h1, c = h2, d = h3, e = h4;
    for (var i = 0; i < 80; i++) {
      int f;
      int k;
      if (i < 20) {
        f = (b & c) | (~b & 0xffffffff & d);
        k = 0x5a827999;
      } else if (i < 40) {
        f = b ^ c ^ d;
        k = 0x6ed9eba1;
      } else if (i < 60) {
        f = (b & c) | (b & d) | (c & d);
        k = 0x8f1bbcdc;
      } else {
        f = b ^ c ^ d;
        k = 0xca62c1d6;
      }
      final int t = (_rotl(a, 5) + f + e + k + w[i]) & 0xffffffff;
      e = d;
      d = c;
      c = _rotl(b, 30);
      b = a;
      a = t;
    }
    h0 = (h0 + a) & 0xffffffff;
    h1 = (h1 + b) & 0xffffffff;
    h2 = (h2 + c) & 0xffffffff;
    h3 = (h3 + d) & 0xffffffff;
    h4 = (h4 + e) & 0xffffffff;
  }
  final ByteData out = ByteData(20)
    ..setUint32(0, h0)
    ..setUint32(4, h1)
    ..setUint32(8, h2)
    ..setUint32(12, h3)
    ..setUint32(16, h4);
  return out.buffer.asUint8List();
}
