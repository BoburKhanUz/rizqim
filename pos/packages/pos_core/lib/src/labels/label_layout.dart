import '../print/print_writer.dart' show textWidth;

/// Yorliq (stiker) qog'ozi o'lchami (P6-32): label printer (Xprinter, Gprinter) 203 dpi — 8 nuqta/mm. Yorliq katak
/// to'riga bo'linadi: har katak [LabelSize.cellWidth] × [LabelSize.rowHeight] nuqta (printerning 2-shrifti, 12×20,
/// qatorlar oralig'i bilan). Shablonlar katak bo'yicha joylashtiradi; drayver nuqtaga aylantiradi.
enum LabelSize {
  mm40x30('40x30', widthMm: 40, heightMm: 30),
  mm58x40('58x40', widthMm: 58, heightMm: 40);

  const LabelSize(this.wire, {required this.widthMm, required this.heightMm});

  /// Backend qiymati (`label_settings.size`).
  final String wire;
  final int widthMm;
  final int heightMm;

  /// 203 dpi: 8 nuqta/mm.
  static const int dotsPerMm = 8;

  /// Chetdan bo'sh joy (nuqta): yorliq chetidagi bosilmaydigan zona.
  static const int margin = 8;

  /// Bitta belgi katagi kengligi (nuqta) — printer 2-shrifti.
  static const int cellWidth = 12;

  /// Bitta qator balandligi (nuqta): 20 nuqtali harf + oraliq.
  static const int rowHeight = 28;

  int get dotsWidth => widthMm * dotsPerMm;
  int get dotsHeight => heightMm * dotsPerMm;

  /// Qatordagi belgilar soni.
  int get cols => (dotsWidth - 2 * margin) ~/ cellWidth;

  /// Yorliqdagi qatorlar soni.
  int get rows => (dotsHeight - 2 * margin) ~/ rowHeight;

  /// Noma'lum qiymat (eski server) — 40×30.
  static LabelSize fromWire(String? value) {
    for (final LabelSize size in values) {
      if (size.wire == value) return size;
    }
    return mm40x30;
  }
}

/// Yorliqdagi bitta matn: [row] qatordan, [col] belgidan boshlanadi; [scale] marta kattalashtirilgan matn [scale]
/// qator va [scale] baravar keng katak egallaydi.
final class LabelText {
  const LabelText(
    this.row,
    this.col,
    this.text, {
    this.scale = 1,
    this.bold = false,
    this.inverted = false,
  }) : assert(scale >= 1 && scale <= 4);

  final int row;
  final int col;
  final String text;
  final int scale;
  final bool bold;

  /// Oq matn qora fonda.
  final bool inverted;

  /// Egallagan katak kengligi.
  int get cells => textWidth(text) * scale;

  /// Egallagan qatorlar.
  int get rowSpan => scale;
}

/// Butun kenglikdagi gorizontal chiziq, [row] qatorning o'rtasida.
final class LabelBar {
  const LabelBar(this.row);

  final int row;
}

/// Joylashtirilgan yorliq: matnlar va chiziqlar katak to'rida. Hamma matn [size] ga sig'adi (shablon kafolatlaydi).
final class LabelLayout {
  const LabelLayout(
    this.size,
    this.texts, {
    this.bars = const <LabelBar>[],
    this.title = '',
  });

  final LabelSize size;
  final List<LabelText> texts;
  final List<LabelBar> bars;

  /// Navbat va jurnal uchun qisqa nom ("Stiker 57: Latte").
  final String title;

  /// Hamma matn (printer jadvalida yo'q belgi bormi tekshirish uchun).
  Iterable<String> get allText => texts.map((LabelText t) => t.text);
}

/// Yorliq qatorlarini yig'adi: matn katak to'riga sig'adi (uzun matn qatorlarga bo'linadi, joy tugasa kesiladi).
class LabelWriter {
  LabelWriter(this.size);

  final LabelSize size;
  final List<LabelText> texts = <LabelText>[];
  final List<LabelBar> bars = <LabelBar>[];
  int _row = 0;

  /// Keyingi bo'sh qator.
  int get row => _row;

  /// Qolgan qatorlar.
  int get rowsLeft => size.rows - reserved - _row;

  /// [text] ni keyingi qator(lar)ga qo'yadi: [maxRows] dan ko'p qatorga bo'linsa kesiladi (oxirgi qator ".." bilan).
  /// Nechta qator egallagani qaytariladi.
  int text(
    String text, {
    int scale = 1,
    bool bold = false,
    bool inverted = false,
    bool center = false,
    int maxRows = 1,
  }) {
    final int cols = size.cols ~/ scale;
    final int budget = _clamp(maxRows ~/ scale, 0, rowsLeft ~/ scale);
    if (budget == 0 || text.trim().isEmpty) return 0;
    final List<String> lines = _wrap(text.trim(), cols);
    var shown = lines.length > budget ? lines.sublist(0, budget) : lines;
    if (lines.length > budget) {
      shown = <String>[...shown.take(budget - 1), _ellipsis(shown.last, cols)];
    }
    for (final String line in shown) {
      final int col = center ? ((cols - textWidth(line)) ~/ 2) * scale : 0;
      texts.add(
        LabelText(
          _row,
          col,
          line,
          scale: scale,
          bold: bold,
          inverted: inverted,
        ),
      );
      _row += scale;
    }
    return shown.length * scale;
  }

  /// Bir qatorda chapda [left] va o'ngda [right] (o'ng chetga tekis). Sig'masa [right] tashlanadi.
  void pair(
    String left,
    String right, {
    int scale = 1,
    bool bold = false,
    int rightScale = 1,
  }) {
    if (rowsLeft < scale) return;
    final int cols = size.cols;
    final int rightCells = textWidth(right) * rightScale;
    final int leftMax = right.isEmpty ? cols : cols - rightCells - 1;
    final String leftText = _clip(left, leftMax ~/ scale);
    texts.add(LabelText(_row, 0, leftText, scale: scale, bold: bold));
    if (right.isNotEmpty && textWidth(leftText) * scale <= leftMax) {
      texts.add(LabelText(_row, cols - rightCells, right, scale: rightScale));
    }
    _row += scale;
  }

  /// Oxirgi [rows] qator tagidagi matn uchun band qilinadi: tana matni ularga tushmaydi ([footer] yozadi).
  int reserved = 0;

  /// Ostki qator(lar): [reserved] qatordan boshlab yorliq tagida (tana qancha joy olganidan qat'i nazar).
  void footer(String text, {bool bold = false, bool inverted = false}) {
    final int row = size.rows - 1;
    texts.add(
      LabelText(row, 0, _clip(text, size.cols), bold: bold, inverted: inverted),
    );
  }

  void bar() {
    if (rowsLeft < 1) return;
    bars.add(LabelBar(_row));
    _row += 1;
  }

  LabelLayout build({String title = ''}) => LabelLayout(
    size,
    List<LabelText>.unmodifiable(texts),
    bars: List<LabelBar>.unmodifiable(bars),
    title: title,
  );

  static int _clamp(int v, int lo, int hi) => v < lo ? lo : (v > hi ? hi : v);

  static String _clip(String text, int width) {
    if (width <= 0) return '';
    if (textWidth(text) <= width) return text;
    return _ellipsis(text, width);
  }

  /// Qatorni [width] gacha qisqartiradi, oxiri ".." (ASCII: label printer shriftida "…" yo'q).
  static String _ellipsis(String text, int width) {
    if (width <= 2) return String.fromCharCodes(text.runes.take(width));
    final String head = String.fromCharCodes(text.runes.take(width - 2));
    return '${head.trimRight()}..';
  }

  static List<String> _wrap(String text, int width) {
    final List<String> out = <String>[];
    var line = '';
    for (var word in text.split(RegExp(r'\s+'))) {
      while (textWidth(word) > width) {
        if (line.isNotEmpty) {
          out.add(line);
          line = '';
        }
        out.add(String.fromCharCodes(word.runes.take(width)));
        word = String.fromCharCodes(word.runes.skip(width));
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
    return out;
  }
}
