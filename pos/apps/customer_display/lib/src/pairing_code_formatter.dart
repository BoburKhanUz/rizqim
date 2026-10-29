import 'package:flutter/services.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// Ulash kodi maydoni: katta harf, ortiqcha belgilar tashlanadi, 4-belgidan keyin chiziqcha qo'yiladi. Chiziqchani
/// o'zi yozgan ham, yozmagan ham `ABCD-EFGH` oladi.
class PairingCodeFormatter extends TextInputFormatter {
  const PairingCodeFormatter();

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final String text = PairingCode.formatInput(newValue.text);
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}
