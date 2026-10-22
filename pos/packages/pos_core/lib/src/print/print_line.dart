/// Chek lentasi kengligi. [chars] — oddiy shrift (Font A, 12×24 nuqta) bilan bir qatordagi belgi soni, [dots] —
/// printer boshchasidagi nuqtalar (203 dpi): 58 mm → 384, 80 mm → 576.
enum PaperWidth {
  mm58(chars: 32, dots: 384),
  mm80(chars: 48, dots: 576);

  const PaperWidth({required this.chars, required this.dots});

  final int chars;
  final int dots;

  /// Sozlamadagi qiymat: `58` yoki `80` (boshqasi — 80).
  static PaperWidth fromMillimetres(int? mm) => mm == 58 ? mm58 : mm80;
}

enum PrintAlign { left, center, right }

/// Shrift kattaligi: kenglik va balandlik ko'paytuvchisi (ESC/POS `GS !`, 1–8). Qatordagi belgi soni
/// `paper.chars ~/ width`.
final class PrintTextSize {
  const PrintTextSize(this.width, this.height)
    : assert(width >= 1 && width <= 8),
      assert(height >= 1 && height <= 8);

  final int width;
  final int height;

  static const PrintTextSize normal = PrintTextSize(1, 1);

  /// Ikki baravar baland (marka taomlari, jami summa): kenglik o'zgarmaydi.
  static const PrintTextSize tall = PrintTextSize(1, 2);
  static const PrintTextSize wide = PrintTextSize(2, 1);

  /// Ikki baravar (sarlavha, xona nomi).
  static const PrintTextSize large = PrintTextSize(2, 2);

  /// Navbat raqami.
  static const PrintTextSize huge = PrintTextSize(4, 4);

  bool get isNormal => width == 1 && height == 1;

  @override
  bool operator ==(Object other) =>
      other is PrintTextSize && other.width == width && other.height == height;

  @override
  int get hashCode => Object.hash(width, height);

  @override
  String toString() => '${width}x$height';
}

/// Qog'ozga chiqadigan bitta element. Joylashuv funksiyalari (`layout*`) hujjatni shu ro'yxatga aylantiradi, keyin
/// `pos_hardware` uni ESC/POS baytlariga (matn rejimi) yoki 1 bitli rasmga (matn rasm sifatida) aylantiradi.
///
/// Matn qatorlari allaqachon kenglikka moslangan (ustunlar bo'shliq bilan to'ldirilgan, uzun nom bo'lingan): printer
/// ham, rasm chizuvchi ham har qatorni o'zgartirmasdan, monospace shriftda chiqaradi.
sealed class PrintLine {
  const PrintLine();
}

/// Bir qator matn. [text] qatorga sig'adi (`paper.chars ~/ size.width` belgidan oshmaydi).
final class PrintText extends PrintLine {
  const PrintText(
    this.text, {
    this.align = PrintAlign.left,
    this.bold = false,
    this.size = PrintTextSize.normal,
    this.inverted = false,
  });

  final String text;
  final PrintAlign align;
  final bool bold;
  final PrintTextSize size;

  /// Oq matn qora fonda ("NUSXA", "O'CHIRILDI").
  final bool inverted;
}

/// Butun kenglikdagi chiziq: `----` yoki `====`.
final class PrintDivider extends PrintLine {
  const PrintDivider([this.char = '-']);

  final String char;
}

/// Bo'sh qatorlar.
final class PrintFeed extends PrintLine {
  const PrintFeed([this.lines = 1]);

  final int lines;
}

/// QR kod (fiskal chek havolasi). [moduleSize] — bitta katakning nuqtadagi o'lchami (ESC/POS 1–16).
final class PrintQr extends PrintLine {
  const PrintQr(this.data, {this.moduleSize = 6});

  final String data;
  final int moduleSize;
}

/// Qog'ozni kesish (kesgichi yo'q printerda — bir necha bo'sh qator).
final class PrintCut extends PrintLine {
  const PrintCut();
}

/// Joylashtirilgan hujjat: qatorlar va qaysi qog'oz uchun.
final class PrintLayout {
  const PrintLayout(this.paper, this.lines, {this.title = ''});

  final PaperWidth paper;
  final List<PrintLine> lines;

  /// Navbat va jurnal uchun qisqa nom ("Chek 000123", "Marka: Osh sexi").
  final String title;

  /// Hujjatdagi barcha matn (charset tekshiruvi uchun: printer kodlash jadvalida yo'q belgi bormi).
  Iterable<String> get texts sync* {
    for (final PrintLine line in lines) {
      if (line is PrintText) yield line.text;
    }
  }
}
