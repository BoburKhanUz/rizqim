import 'dart:typed_data';

/// 1 bitli rasm (termoprinter uchun): har qator `ceil(width / 8)` bayt, katta bit — chapdagi nuqta, 1 — qora.
/// ESC/POS `GS v 0` aynan shu tartibni kutadi.
final class MonoBitmap {
  MonoBitmap(this.width, this.height, [Uint8List? data])
    : assert(width > 0 && height >= 0),
      data = data ?? Uint8List(((width + 7) >> 3) * height) {
    if (this.data.length != bytesPerRow * height) {
      throw ArgumentError('MonoBitmap data must be bytesPerRow * height');
    }
  }

  /// RGBA piksellardan (`ui.Image.toByteData(format: rawRgba)`): yorqinligi [threshold] dan past va shaffof emas
  /// piksel — qora. Shaffof fon oq hisoblanadi.
  factory MonoBitmap.fromRgba(
    Uint8List rgba,
    int width,
    int height, {
    int threshold = 128,
  }) {
    if (rgba.length < width * height * 4) {
      throw ArgumentError('RGBA buffer is smaller than width * height * 4');
    }
    final MonoBitmap bitmap = MonoBitmap(width, height);
    for (var y = 0; y < height; y++) {
      for (var x = 0; x < width; x++) {
        final int i = (y * width + x) * 4;
        final int a = rgba[i + 3];
        if (a < 128) continue;
        // ITU-R BT.601 yorqinlik, butun sonlarda
        final int lum =
            (rgba[i] * 299 + rgba[i + 1] * 587 + rgba[i + 2] * 114) ~/ 1000;
        if (lum < threshold) bitmap.setPixel(x, y);
      }
    }
    return bitmap;
  }

  final int width;
  final int height;
  final Uint8List data;

  int get bytesPerRow => (width + 7) >> 3;

  bool pixel(int x, int y) =>
      (data[y * bytesPerRow + (x >> 3)] & (0x80 >> (x & 7))) != 0;

  void setPixel(int x, int y, [bool black = true]) {
    final int i = y * bytesPerRow + (x >> 3);
    final int bit = 0x80 >> (x & 7);
    data[i] = black ? data[i] | bit : data[i] & ~bit;
  }

  /// Qora nuqtalar soni.
  int get blackPixels {
    var n = 0;
    for (final int b in data) {
      var v = b;
      while (v != 0) {
        n += v & 1;
        v >>= 1;
      }
    }
    return n;
  }

  /// `[top, top + rows)` qatorlari (printerga bo'laklab yuborish uchun).
  MonoBitmap band(int top, int rows) {
    final int end = (top + rows).clamp(0, height);
    return MonoBitmap(
      width,
      end - top,
      Uint8List.fromList(data.sublist(top * bytesPerRow, end * bytesPerRow)),
    );
  }

  /// Bir nechta rasmni ustma-ust qo'shadi (kenglik eng kattasi).
  static MonoBitmap stack(List<MonoBitmap> parts) {
    final int width = parts.fold(
      1,
      (int w, MonoBitmap p) => p.width > w ? p.width : w,
    );
    final int height = parts.fold(0, (int h, MonoBitmap p) => h + p.height);
    final MonoBitmap out = MonoBitmap(width, height);
    var top = 0;
    for (final MonoBitmap p in parts) {
      for (var y = 0; y < p.height; y++) {
        out.data.setRange(
          (top + y) * out.bytesPerRow,
          (top + y) * out.bytesPerRow + p.bytesPerRow,
          p.data,
          y * p.bytesPerRow,
        );
      }
      top += p.height;
    }
    return out;
  }

  /// Testlar va jurnal uchun: `#` qora, `.` oq.
  String toAscii() {
    final StringBuffer out = StringBuffer();
    for (var y = 0; y < height; y++) {
      for (var x = 0; x < width; x++) {
        out.write(pixel(x, y) ? '#' : '.');
      }
      out.writeln();
    }
    return out.toString();
  }
}
