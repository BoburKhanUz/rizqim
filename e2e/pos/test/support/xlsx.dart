import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

/// Just enough of a ZIP reader to open an .xlsx the backend exported (openspout): its entries by name, inflated. The
/// e2e package has no archive dependency; an XLSX is a ZIP of XML parts (ECMA-376), stored or deflated.
class XlsxFile {
  XlsxFile._(this.entries);

  /// Reads the central directory; throws a FormatException when the bytes are not a ZIP.
  factory XlsxFile.parse(List<int> raw) {
    final Uint8List bytes = Uint8List.fromList(raw);
    if (bytes.length < 22 ||
        bytes[0] != 0x50 ||
        bytes[1] != 0x4b ||
        bytes[2] != 0x03 ||
        bytes[3] != 0x04) {
      throw const FormatException('not a ZIP file (no PK\\x03\\x04 signature)');
    }
    final ByteData view = ByteData.sublistView(bytes);
    // The end of central directory record: the last PK\x05\x06
    var eocd = -1;
    for (var i = bytes.length - 22; i >= 0; i--) {
      if (view.getUint32(i, Endian.little) == 0x06054b50) {
        eocd = i;
        break;
      }
    }
    if (eocd < 0) throw const FormatException('no end of central directory');
    final int count = view.getUint16(eocd + 10, Endian.little);
    var at = view.getUint32(eocd + 16, Endian.little);
    final Map<String, List<int>> entries = <String, List<int>>{};
    for (var n = 0; n < count; n++) {
      if (view.getUint32(at, Endian.little) != 0x02014b50) {
        throw const FormatException('bad central directory entry');
      }
      final int method = view.getUint16(at + 10, Endian.little);
      final int compressed = view.getUint32(at + 20, Endian.little);
      final int nameLength = view.getUint16(at + 28, Endian.little);
      final int extraLength = view.getUint16(at + 30, Endian.little);
      final int commentLength = view.getUint16(at + 32, Endian.little);
      final int local = view.getUint32(at + 42, Endian.little);
      final String name = utf8.decode(
        bytes.sublist(at + 46, at + 46 + nameLength),
      );
      final int dataAt =
          local +
          30 +
          view.getUint16(local + 26, Endian.little) +
          view.getUint16(local + 28, Endian.little);
      final Uint8List data = bytes.sublist(dataAt, dataAt + compressed);
      entries[name] = switch (method) {
        0 => data,
        8 => ZLibDecoder(raw: true).convert(data),
        _ => throw FormatException('$name: compression method $method'),
      };
      at += 46 + nameLength + extraLength + commentLength;
    }
    return XlsxFile._(entries);
  }

  final Map<String, List<int>> entries;

  String text(String name) => utf8.decode(entries[name]!);

  /// The sheet names of the workbook, in order.
  List<String> get sheetNames => <String>[
    for (final RegExpMatch m in RegExp(
      r'<sheet\b[^>]*\bname="([^"]*)"',
    ).allMatches(text('xl/workbook.xml')))
      _unescape(m.group(1)!),
  ];

  /// All the text of the workbook's sheets and shared strings (for "this name is in it" checks).
  String get allText => _unescape(
    <String>[
      for (final String name in entries.keys)
        if (name.startsWith('xl/worksheets/') || name == 'xl/sharedStrings.xml')
          text(name),
    ].join('\n'),
  );

  static String _unescape(String s) => s
      .replaceAll('&apos;', "'")
      .replaceAll('&quot;', '"')
      .replaceAll('&lt;', '<')
      .replaceAll('&gt;', '>')
      .replaceAll('&amp;', '&');
}
