import 'dart:math';

final Random _secure = Random.secure();

/// UUID v4 (`Random.secure`; testlarda o'z [random] i bilan takrorlanadigan).
String newUuid([Random? random]) {
  final Random r = random ?? _secure;
  final List<int> b = List<int>.generate(16, (_) => r.nextInt(256));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  String hex(int from, int to) => <String>[
    for (var i = from; i < to; i++) b[i].toRadixString(16).padLeft(2, '0'),
  ].join();
  return '${hex(0, 4)}-${hex(4, 6)}-${hex(6, 8)}-${hex(8, 10)}-${hex(10, 16)}';
}
