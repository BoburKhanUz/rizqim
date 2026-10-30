import 'dart:convert' show latin1;
import 'dart:typed_data';

import '../escpos/code_page.dart';
import '../raster/mono_bitmap.dart';

/// TSPL buyruqlari (TSC; Xprinter, Gprinter, Zebra-bo'lmagan arzon label printerlar ham tushunadi). Buyruq —
/// ASCII qator, `\r\n` bilan tugaydi; faqat `BITMAP` ma'lumoti ikkilik.
///
/// ```dart
/// final bytes = (TsplBuilder()
///       ..size(40, 30)
///       ..gap(2)
///       ..cls()
///       ..text(8, 12, '2', 'Latte', xMul: 2, yMul: 2)
///       ..printLabels())
///     .build();
/// ```
class TsplBuilder {
  final BytesBuilder _out = BytesBuilder(copy: false);

  int get length => _out.length;

  TsplBuilder line(String command) {
    _out
      ..add(latin1.encode(command))
      ..add(const <int>[0x0D, 0x0A]);
    return this;
  }

  /// `SIZE w mm,h mm`: yorliq o'lchami.
  TsplBuilder size(int widthMm, int heightMm) =>
      line('SIZE $widthMm mm,$heightMm mm');

  /// `GAP m mm,n mm`: yorliqlar orasidagi oraliq (qora belgili rulon — `BLINE`, bu yerda yo'q).
  TsplBuilder gap(int gapMm, [int offsetMm = 0]) =>
      line('GAP $gapMm mm,$offsetMm mm');

  /// `DIRECTION n`: 1 — odatiy yo'nalish (Xprinter, Gprinter), 0 — teskari.
  TsplBuilder direction(int value) => line('DIRECTION $value,0');

  /// `REFERENCE x,y`: koordinata boshi.
  TsplBuilder reference(int x, int y) => line('REFERENCE $x,$y');

  /// `DENSITY n` (0–15): qoralik.
  TsplBuilder density(int value) => line('DENSITY ${value.clamp(0, 15)}');

  /// `CLS`: tasvir buferini tozalaydi.
  TsplBuilder cls() => line('CLS');

  /// `TEXT x,y,"font",rotation,xMul,yMul,"content"`. Matn ASCII bo'lishi kerak ([tsplAscii] almashtiradi);
  /// qo'sh tirnoq `\["]` bilan yoziladi.
  TsplBuilder text(
    int x,
    int y,
    String font,
    String content, {
    int rotation = 0,
    int xMul = 1,
    int yMul = 1,
  }) => line(
    'TEXT $x,$y,"$font",$rotation,$xMul,$yMul,"${tsplAscii(content).replaceAll('"', r'\["]')}"',
  );

  /// `BAR x,y,w,h`: to'ldirilgan to'rtburchak (chiziq).
  TsplBuilder bar(int x, int y, int width, int height) =>
      line('BAR $x,$y,$width,$height');

  /// `REVERSE x,y,w,h`: to'rtburchakdagi ranglarni teskari qiladi (oq matn qora fonda).
  TsplBuilder reverse(int x, int y, int width, int height) =>
      line('REVERSE $x,$y,$width,$height');

  /// `BITMAP x,y,widthBytes,height,0,<data>`: 1 bitli rasm. TSPL da bit `0` — qora nuqta, shuning uchun [image] teskari
  /// (ESC/POS dagi `1` — qora) yoziladi. Qurilma modelida teskari bo'lsa, birinchi sinov chop etishda tekshiriladi.
  TsplBuilder bitmap(int x, int y, MonoBitmap image) {
    final Uint8List data = Uint8List.fromList(
      image.data.map((int b) => ~b & 0xFF).toList(),
    );
    _out
      ..add(
        latin1.encode('BITMAP $x,$y,${image.bytesPerRow},${image.height},0,'),
      )
      ..add(data)
      ..add(const <int>[0x0D, 0x0A]);
    return this;
  }

  /// `PRINT sets,copies`: chop etish. Bir xil yorliqdan [copies] nusxa.
  TsplBuilder printLabels({int copies = 1}) => line('PRINT 1,$copies');

  Uint8List build() => _out.toBytes();
}

/// Matnni ASCII ga keltiradi (TSPL ning o'rnatilgan shrifti faqat ASCII): "oʻ" → "o'", "№" → "N"; qolgan belgilar
/// (kirill) → "?" — butun yorliqni rasm sifatida chop etish uchun [tsplCanEncode] bilan tekshiring.
String tsplAscii(String text) =>
    EscPosCodePage.ascii.decode(EscPosCodePage.ascii.encode(text));

/// Matn printerning o'rnatilgan (ASCII) shriftida to'g'ri chiqadimi: "oʻ", "gʻ", "ʼ" ("o'" ga almashadi) ham ma'qul,
/// kirill emas.
bool tsplCanEncode(String text) {
  for (final int rune in text.runes) {
    if (rune >= 0x20 && rune < 0x7F) continue;
    final String? alt = transliterate(rune);
    if (alt == null || !alt.runes.every((int r) => r >= 0x20 && r < 0x7F)) {
      return false;
    }
  }
  return true;
}
