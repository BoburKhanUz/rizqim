import 'print_line.dart';

/// Matn uzunligi belgilarda (Unicode kod nuqtalari: "oʻ" ham, "o'" ham ikki belgi).
int textWidth(String text) => text.runes.length;

String _take(String text, int count) =>
    String.fromCharCodes(text.runes.take(count));

String _skip(String text, int count) =>
    String.fromCharCodes(text.runes.skip(count));

/// [text] ni [width] belgili qatorlarga bo'ladi: so'z chegarasida, juda uzun so'z — qattiq bo'linadi. Bo'sh matn —
/// bitta bo'sh qator. Qatordagi ortiqcha bo'shliqlar olib tashlanadi.
List<String> wrapText(String text, int width) {
  if (width <= 0) throw ArgumentError.value(width, 'width', 'must be > 0');
  final List<String> out = <String>[];
  for (final String paragraph in text.split('\n')) {
    final List<String> words = paragraph
        .split(RegExp(r'\s+'))
        .where((String w) => w.isNotEmpty)
        .toList();
    if (words.isEmpty) {
      out.add('');
      continue;
    }
    var line = '';
    for (var word in words) {
      while (textWidth(word) > width) {
        // uzun so'z: joriy qatorni yopib, so'zni bo'lib chiqaramiz
        if (line.isNotEmpty) {
          out.add(line);
          line = '';
        }
        out.add(_take(word, width));
        word = _skip(word, width);
      }
      if (word.isEmpty) continue;
      if (line.isEmpty) {
        line = word;
      } else if (textWidth(line) + 1 + textWidth(word) <= width) {
        line = '$line $word';
      } else {
        out.add(line);
        line = word;
      }
    }
    if (line.isNotEmpty) out.add(line);
  }
  return out;
}

/// [text] o'ngga [width] gacha bo'shliq bilan to'ldiriladi (kesilmaydi).
String padRight(String text, int width) {
  final int pad = width - textWidth(text);
  return pad > 0 ? text + ' ' * pad : text;
}

/// [text] chapdan [width] gacha bo'shliq bilan to'ldiriladi.
String padLeft(String text, int width) {
  final int pad = width - textWidth(text);
  return pad > 0 ? ' ' * pad + text : text;
}

/// Hujjat qatorlarini yig'adi: so'zlarni bo'lish, ikki ustunli "nom ....... summa" qatorlar, chiziqlar. Har
/// [PrintText] qog'oz kengligiga ([PaperWidth.chars] / shrift kengligi) sig'adi.
class PrintWriter {
  PrintWriter(this.paper);

  final PaperWidth paper;
  final List<PrintLine> lines = <PrintLine>[];

  /// [size] shriftda qatorga sig'adigan belgilar.
  int widthFor([PrintTextSize size = PrintTextSize.normal]) =>
      paper.chars ~/ size.width;

  /// Matn (kerak bo'lsa bir necha qatorga bo'linadi).
  void text(
    String text, {
    PrintAlign align = PrintAlign.left,
    bool bold = false,
    PrintTextSize size = PrintTextSize.normal,
    bool inverted = false,
    int indent = 0,
  }) {
    final int width = widthFor(size) - indent;
    // Sig'adigan bitta qator o'zgarmaydi (" NUSXA " kabi inversiya belgilarining chetidagi bo'shliqlar qoladi)
    final List<String> parts = !text.contains('\n') && textWidth(text) <= width
        ? <String>[text]
        : wrapText(text, width);
    for (final String line in parts) {
      lines.add(
        PrintText(
          indent > 0 ? '${' ' * indent}$line' : line,
          align: align,
          bold: bold,
          size: size,
          inverted: inverted,
        ),
      );
    }
  }

  void center(
    String text, {
    bool bold = false,
    PrintTextSize size = PrintTextSize.normal,
    bool inverted = false,
  }) => this.text(
    text,
    align: PrintAlign.center,
    bold: bold,
    size: size,
    inverted: inverted,
  );

  /// Chapda [left], o'ngda [right] ("Jami: ........ 450 000"). Chap matn uzun bo'lsa bo'linadi va [right] oxirgi
  /// qatorga qo'yiladi; joy yetmasa — alohida qatorda o'ngga tekislanadi. [indent] — chap matnning chekinishi.
  void pair(
    String left,
    String right, {
    bool bold = false,
    PrintTextSize size = PrintTextSize.normal,
    int indent = 0,
  }) {
    final int width = widthFor(size);
    final int rightWidth = textWidth(right);
    final String prefix = ' ' * indent;
    final List<String> wrapped = left.isEmpty
        ? <String>['']
        : wrapText(left, width - indent);
    for (var i = 0; i < wrapped.length - 1; i++) {
      lines.add(PrintText('$prefix${wrapped[i]}', bold: bold, size: size));
    }
    final String last = '$prefix${wrapped.last}';
    if (rightWidth == 0) {
      lines.add(PrintText(last, bold: bold, size: size));
    } else if (textWidth(last) + 1 + rightWidth <= width) {
      lines.add(
        PrintText(
          padRight(last, width - rightWidth) + right,
          bold: bold,
          size: size,
        ),
      );
    } else {
      lines.add(PrintText(last, bold: bold, size: size));
      lines.add(PrintText(padLeft(right, width), bold: bold, size: size));
    }
  }

  /// Ikki qisqa qiymat bir qatorda chap va o'ng chetda ("Chek: 000123     21.10.2026 20:15"). Sig'masa ikki qator.
  void spread(String left, String right) {
    final int width = widthFor();
    if (textWidth(left) + 1 + textWidth(right) <= width) {
      lines.add(PrintText(padRight(left, width - textWidth(right)) + right));
    } else {
      text(left);
      text(right, align: PrintAlign.right);
    }
  }

  void divider([String char = '-']) => lines.add(PrintDivider(char));

  void feed([int count = 1]) => lines.add(PrintFeed(count));

  void qr(String data, {int moduleSize = 6}) =>
      lines.add(PrintQr(data, moduleSize: moduleSize));

  void cut() => lines.add(const PrintCut());

  PrintLayout build({String title = ''}) =>
      PrintLayout(paper, List<PrintLine>.unmodifiable(lines), title: title);
}
