/// Interfeys tillari. Mini App'dagi `Language` bilan bir xil (`uz` asosiy).
enum RzLocale {
  uz,
  ru,
  en;

  /// "uz", "ru", "en" (yoki "uz_UZ", "ru-RU") dan til; noma'lum bo'lsa [fallback].
  static RzLocale parse(String? code, {RzLocale fallback = RzLocale.uz}) {
    if (code == null || code.length < 2) return fallback;
    final String lang = code.substring(0, 2).toLowerCase();
    for (final RzLocale locale in RzLocale.values) {
      if (locale.name == lang) return locale;
    }
    return fallback;
  }
}

/// Ajralmas bo'shliq (U+00A0). Qiymat ichidagi bo'shliqlar shu: "25 000 so'm" qatorda bo'linmaydi.
const String nbsp = ' ';
