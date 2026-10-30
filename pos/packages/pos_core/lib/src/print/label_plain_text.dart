import '../labels/label_layout.dart';

/// Yorliqni o'qiladigan matnga aylantiradi ("oltin" snapshot testlari va ekrandagi oldindan ko'rish): har satr —
/// yorliqning bitta qatori, `|` orasida [LabelSize.cols] belgi; kattalashtirilgan matn o'z kengligicha, keyingi
/// qatorlarda `^` bilan belgilanadi. Belgilar ustuni: `B` qalin, `I` teskari rang, `2x` kattalik.
String renderLabelPlainText(LabelLayout layout) {
  final int cols = layout.size.cols;
  final int rows = layout.size.rows;
  final List<List<String>> grid = List<List<String>>.generate(
    rows,
    (_) => List<String>.filled(cols, ' '),
  );
  final List<Set<String>> flags = List<Set<String>>.generate(
    rows,
    (_) => <String>{},
  );

  for (final LabelBar bar in layout.bars) {
    for (var c = 0; c < cols; c++) {
      grid[bar.row][c] = '-';
    }
  }
  for (final LabelText t in layout.texts) {
    final List<int> runes = t.text.runes.toList();
    for (var i = 0; i < runes.length; i++) {
      final int col = t.col + i * t.scale;
      if (col >= cols) break;
      grid[t.row][col] = String.fromCharCode(runes[i]);
      // Kattalashtirilgan harf keyingi kataklarni va qatorlarni egallaydi
      for (var dr = 0; dr < t.scale; dr++) {
        for (var dc = 0; dc < t.scale; dc++) {
          if (dr == 0 && dc == 0) continue;
          final int r = t.row + dr;
          final int c = col + dc;
          if (r < rows && c < cols) grid[r][c] = dr == 0 ? '~' : '^';
        }
      }
    }
    if (t.bold) flags[t.row].add('B');
    if (t.inverted) flags[t.row].add('I');
    if (t.scale > 1) flags[t.row].add('${t.scale}x');
  }

  final StringBuffer out = StringBuffer()
    ..writeln('label ${layout.size.wire} mm ($cols x $rows)');
  for (var r = 0; r < rows; r++) {
    out
      ..write((flags[r].toList()..sort()).join().padRight(5))
      ..write('|')
      ..write(grid[r].join())
      ..writeln('|');
  }
  return out.toString();
}
