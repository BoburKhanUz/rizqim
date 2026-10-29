import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_customer_display/src/qr/qr_encoder.dart';

/// To'lov QR'ini chizadigan kodlagich (tashqi paket yo'q): xalqaro kutubxona (python `qrcode`, ISO/IEC 18004) bilan
/// modulma-modul solishtiriladi. `test/fixtures/qr_expected.json`: matn, daraja, niqob, versiya va qatorlar.
/// Qayta yaratish: `python3 test/fixtures/gen_qr_fixtures.py` (`pip install qrcode`).
void main() {
  final List<Map<String, Object?>> expected =
      (jsonDecode(File('test/fixtures/qr_expected.json').readAsStringSync())
              as List<Object?>)
          .cast<Map<String, Object?>>();

  test('the fixture covers versions 1 to 10, both levels and every mask', () {
    expect(expected.map((Map<String, Object?> e) => e['version']).toSet(), <int>{1, 2, 3, 4, 5, 6, 7, 8, 9, 10});
    expect(expected.map((Map<String, Object?> e) => e['ecc']).toSet(), <String>{'M', 'L'});
    expect(expected.map((Map<String, Object?> e) => e['mask']).toSet(), <int>{0, 1, 2, 3, 4, 5, 6, 7});
  });

  for (final Map<String, Object?> e in expected) {
    final String text = e['text']! as String;
    final String label = '${text.length > 28 ? '${text.substring(0, 28)}...' : text} '
        '(v${e['version']}-${e['ecc']}, mask ${e['mask']})';
    test('matches the reference matrix module by module: $label', () {
      final QrCode qr = QrCode.encode(
        text,
        ecc: e['ecc'] == 'M' ? QrEcc.medium : QrEcc.low,
        mask: e['mask']! as int,
      );
      expect(qr.version, e['version']);
      expect(qr.size, 17 + 4 * (e['version']! as int));
      final List<String> rows = (e['rows']! as List<Object?>).cast<String>();
      expect(qr.toText().split('\n'), rows);
    });
  }

  test('without a forced mask the best-scoring one is used and the result is a valid matrix', () {
    final QrCode qr = QrCode.encode('https://pay.example/uz/abc');
    expect(qr.version, 2);
    expect(qr.ecc, QrEcc.medium);
    expect(qr.mask, inInclusiveRange(0, 7));
    // The finder patterns and the always-dark module are where the standard puts them
    for (final (int, int) o in <(int, int)>[(0, 0), (0, qr.size - 7), (qr.size - 7, 0)]) {
      expect(qr.isDark(o.$1, o.$2), isTrue);
      expect(qr.isDark(o.$1 + 3, o.$2 + 3), isTrue);
      expect(qr.isDark(o.$1 + 1, o.$2 + 1), isFalse);
    }
    expect(qr.isDark(qr.size - 8, 8), isTrue);
  });

  test('a link too long for medium falls back to low and one too long for version 10 is refused', () {
    final QrCode longer = QrCode.encode('x' * 250);
    expect(longer.ecc, QrEcc.low);
    expect(longer.version, 10);
    expect(() => QrCode.encode('x' * 272), throwsA(isA<QrTooLongException>()));
    expect(() => QrCode.encode('x' * 250, ecc: QrEcc.medium), throwsA(isA<QrTooLongException>()));
  });
}
