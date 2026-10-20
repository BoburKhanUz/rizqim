/// POS/KDS terminalni ulash kodi: 8 belgi, ko'rinishi `ABCD-EFGH` (docs/AUTH.md, "POS/KDS terminalni ulash").
///
/// Qoidalar backend'dagi `DevicePairingService::normalize` bilan bir xil: chiziqcha va bo'shliqlar tashlanadi, katta-kichik
/// harf farqi yo'q, `O` -> `0`, `I` va `L` -> `1`. Alfavit: Crockford base32 (I, L, O, U yo'q).
abstract final class PairingCode {
  static const String alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
  static const int length = 8;

  static final RegExp _separators = RegExp(r'[\s\-]+');

  /// Terilgan matndan kanonik kod (8 ta katta harf/raqam, chiziqchasiz) yoki kod bo'la olmasa `null`.
  static String? normalize(String input) {
    final String code = input
        .replaceAll(_separators, '')
        .toUpperCase()
        .replaceAll('O', '0')
        .replaceAll('I', '1')
        .replaceAll('L', '1');
    if (code.length != length) return null;
    for (var i = 0; i < code.length; i++) {
      if (!alphabet.contains(code[i])) return null;
    }
    return code;
  }

  /// Kanonik kodni odamga ko'rsatiladigan ko'rinishga: `ABCDEFGH` -> `ABCD-EFGH`.
  static String format(String code) => code.length <= length ~/ 2
      ? code
      : '${code.substring(0, length ~/ 2)}-${code.substring(length ~/ 2)}';

  /// Terish paytida maydondagi matnni tozalaydi: harf/raqamlardan boshqasini tashlaydi, katta harfga o'tkazadi,
  /// 8 belgi bilan cheklaydi va 4-belgidan keyin chiziqcha qo'yadi (`abcd ef` -> `ABCD-EF`). Chiziqchani o'zi
  /// yozmagan odam ham, yozgan odam ham bir xil natija oladi.
  static String formatInput(String input) {
    var raw = input.replaceAll(RegExp('[^A-Za-z0-9]'), '').toUpperCase();
    if (raw.length > length) raw = raw.substring(0, length);
    return format(raw);
  }
}
