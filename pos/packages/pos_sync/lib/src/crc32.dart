import 'dart:convert';

/// CRC-32 (IEEE 802.3) of a string's UTF-8 bytes, as 8 hex digits: each journal line carries it, so a line torn by a
/// power cut is recognised and dropped instead of being read as a different operation.
String crc32Hex(String text) {
  var crc = 0xffffffff;
  for (final int byte in utf8.encode(text)) {
    crc = _table[(crc ^ byte) & 0xff] ^ (crc >>> 8);
  }
  return ((crc ^ 0xffffffff) & 0xffffffff).toRadixString(16).padLeft(8, '0');
}

final List<int> _table = List<int>.generate(256, (int n) {
  var c = n;
  for (var k = 0; k < 8; k++) {
    c = (c & 1) != 0 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  }
  return c;
});
