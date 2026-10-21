import 'print_line.dart';
import 'print_writer.dart';

/// Joylashuvni o'qiladigan matnga aylantiradi: "oltin" snapshot testlari, ekrandagi oldindan ko'rish va jurnal uchun.
///
/// Har qator: belgilar ustuni (`B` qalin, `I` inversiya, `2x2` kattalik), so'ng `|` orasida qog'oz kengligidagi
/// matn (tekislash qo'llangan). Masalan:
///
/// ```text
///     |          SOTUV CHEKI           |
/// B   |JAMI:                    450 000|
///     [QR] https://ofd.soliq.uz/check?t=...
///     ----------------------- kesish -----
/// ```
String renderPlainText(PrintLayout layout) {
  final StringBuffer out = StringBuffer();
  final int chars = layout.paper.chars;
  for (final PrintLine line in layout.lines) {
    switch (line) {
      case PrintText():
        final int width = chars ~/ line.size.width;
        final String flags = <String>[
          if (line.bold) 'B',
          if (line.inverted) 'I',
          if (!line.size.isNormal) '${line.size}',
        ].join();
        out
          ..write(flags.padRight(5))
          ..write('|')
          ..write(_aligned(line.text, width, line.align))
          ..writeln('|');
      case PrintDivider():
        out.writeln('     |${line.char * chars}|');
      case PrintFeed():
        for (var i = 0; i < line.lines; i++) {
          out.writeln('     |${' ' * chars}|');
        }
      case PrintQr():
        out.writeln('     [QR ${line.moduleSize}] ${line.data}');
      case PrintCut():
        out.writeln('     ${'- ' * (chars ~/ 2 - 4)}kesish -');
    }
  }
  return out.toString();
}

String _aligned(String text, int width, PrintAlign align) {
  final int len = textWidth(text);
  if (len >= width) return text;
  switch (align) {
    case PrintAlign.left:
      return padRight(text, width);
    case PrintAlign.right:
      return padLeft(text, width);
    case PrintAlign.center:
      final int left = (width - len) ~/ 2;
      return padRight('${' ' * left}$text', width);
  }
}
