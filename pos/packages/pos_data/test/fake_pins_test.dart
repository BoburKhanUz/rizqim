import 'package:pos_data/testing.dart';
import 'package:test/test.dart';

/// The server's rule for staff PINs (backend `App\Services\Auth\StaffPinService::weakness`), ported: the fake backend's
/// PINs must be ones the real one accepts, or a demo built on them cannot be set up for real.
String? weakness(String pin) {
  const List<String> weak = <String>[
    '2580',
    '0852',
    '1379',
    '1397',
    '2468',
    '1357',
    '1122',
    '1212',
    '6969',
    '1004',
    '2000', //
    '2020',
    '2021',
    '2022',
    '2023',
    '2024',
    '2025',
    '2026',
    '7777',
    '121212',
    '112233',
    '123123',
    '159753',
    '147258', '258369', '654321', '696969', '102030', '111222',
  ];
  if (!RegExp(r'^\d{4,6}$').hasMatch(pin)) return 'length';
  final List<int> d = pin.split('').map(int.parse).toList();
  final Set<int> steps = <int>{
    for (var i = 0; i < d.length - 1; i++) (d[i + 1] - d[i] + 10) % 10,
  };
  if (steps.length == 1 && <int>{0, 1, 9}.contains(steps.single)) {
    return 'sequence';
  }
  for (final int size in <int>[1, 2, 3]) {
    if (pin.length % size == 0 &&
        pin.length > size &&
        pin.substring(0, size) * (pin.length ~/ size) == pin) {
      return 'repeated';
    }
  }
  return weak.contains(pin) ? 'common' : null;
}

void main() {
  test('the port says what the server says', () {
    expect(weakness('1234'), 'sequence');
    expect(weakness('9876'), 'sequence');
    expect(weakness('1111'), 'sequence');
    expect(weakness('4545'), 'repeated');
    expect(weakness('2468'), 'common');
    expect(weakness('1357'), 'common');
    expect(weakness('123'), 'length');
    expect(weakness('5926'), isNull);
  });

  test('the fake backend\'s PINs pass the server\'s rule', () {
    final FakePosState state = FakePosState(clock: () => DateTime.utc(2026));
    for (final MapEntry<int, String> e in state.pins.entries) {
      expect(weakness(e.value), isNull, reason: 'user ${e.key}: ${e.value}');
    }
  });
}
