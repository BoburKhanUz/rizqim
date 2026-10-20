import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

void main() {
  group(
    'PairingCode.normalize (same rules as DevicePairingService::normalize)',
    () {
      test('accepts the code with or without the dash, in any case', () {
        expect(PairingCode.normalize('ABCD-EFGH'), 'ABCDEFGH');
        expect(PairingCode.normalize('ABCDEFGH'), 'ABCDEFGH');
        expect(PairingCode.normalize('abcd-efgh'), 'ABCDEFGH');
        expect(PairingCode.normalize('  abcd efgh '), 'ABCDEFGH');
        expect(PairingCode.normalize('AB-CD-EF-GH'), 'ABCDEFGH');
      });

      test('reads O as 0 and I, L as 1', () {
        expect(PairingCode.normalize('O0O0-I1L1'), '00001111');
        expect(PairingCode.normalize('oilo-oilo'), '01100110');
      });

      test('rejects what cannot be a code', () {
        expect(PairingCode.normalize(''), isNull);
        expect(PairingCode.normalize('ABCD-EFG'), isNull); // 7
        expect(PairingCode.normalize('ABCD-EFGHJ'), isNull); // 9
        expect(
          PairingCode.normalize('ABCD-EFGU'),
          isNull,
        ); // U is not in the alphabet
        expect(PairingCode.normalize('ABCD-EFG!'), isNull);
        expect(PairingCode.normalize('ABCD_EFGH'), isNull);
      });

      test('every character of the alphabet is accepted', () {
        for (final String c in PairingCode.alphabet.split('')) {
          expect(PairingCode.normalize(c * 8), isNotNull, reason: c);
        }
        expect(PairingCode.alphabet.length, 32);
      });
    },
  );

  group('PairingCode.format / formatInput', () {
    test('groups as ABCD-EFGH', () {
      expect(PairingCode.format('ABCDEFGH'), 'ABCD-EFGH');
      expect(PairingCode.format('ABC'), 'ABC');
    });

    test('cleans what is being typed', () {
      expect(PairingCode.formatInput('abcd'), 'ABCD');
      expect(PairingCode.formatInput('abcde'), 'ABCD-E');
      expect(PairingCode.formatInput('abcd-ef'), 'ABCD-EF');
      expect(PairingCode.formatInput('ab cd ef gh'), 'ABCD-EFGH');
      expect(PairingCode.formatInput('abcdefghjk'), 'ABCD-EFGH');
      expect(PairingCode.formatInput('ab!@#'), 'AB');
      expect(PairingCode.formatInput(''), '');
    });
  });
}
