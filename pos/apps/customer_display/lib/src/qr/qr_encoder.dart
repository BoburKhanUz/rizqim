import 'dart:convert';
import 'dart:typed_data';

/// Xato tuzatish darajasi (ISO/IEC 18004): [low] ~7%, [medium] ~15% buzilishni tuzatadi.
enum QrEcc {
  low(1),
  medium(0);

  const QrEcc(this.formatBits);

  /// Format ma'lumotidagi ikki bitli belgi.
  final int formatBits;
}

/// To'lov havolasi sig'maydigan darajada uzun (versiya 10 dan katta kerak).
class QrTooLongException implements Exception {
  const QrTooLongException(this.bytes);

  final int bytes;

  @override
  String toString() => 'QrTooLongException($bytes bytes)';
}

/// QR kod matritsasi: [size] x [size], `true` — qora modul. Faqat bayt rejimi (UTF-8), versiya 1-10, daraja M, sig'masa L.
///
/// Mijoz ekrani to'lov havolasini (Click, Payme ...) chizish uchun shuncha yetadi (<= 271 bayt); tashqi paket
/// qo'shilmagan (loyiha qoidasi). To'g'riligi `test/qr_encoder_test.dart` da xalqaro kutubxona (python `qrcode`)
/// bilan modulma-modul solishtirilgan matritsalar bilan tekshirilgan.
class QrCode {
  QrCode._(this.version, this.ecc, this.mask, this._modules);

  /// [text] ni kodlaydi. [ecc] berilsa shu daraja (sig'masa [QrTooLongException]); berilmasa M, sig'masa L.
  /// [mask] berilsa (0-7) shu niqob, aks holda eng kam jarimali.
  factory QrCode.encode(String text, {QrEcc? ecc, int? mask}) {
    final Uint8List bytes = Uint8List.fromList(utf8.encode(text));
    final List<QrEcc> levels = ecc != null ? <QrEcc>[ecc] : QrEcc.values.reversed.toList();
    // QrEcc.values = [low, medium]; reversed = [medium, low]: avval M
    for (final QrEcc level in levels) {
      for (var v = 1; v <= _maxVersion; v++) {
        if (bytes.length <= _byteCapacity(v, level)) {
          return _build(bytes, v, level, mask);
        }
      }
    }
    throw QrTooLongException(bytes.length);
  }

  static const int _maxVersion = 10;

  final int version;
  final QrEcc ecc;
  final int mask;
  final List<List<bool>> _modules;

  int get size => 17 + 4 * version;

  bool isDark(int row, int col) => _modules[row][col];

  /// Matritsa matn ko'rinishida (`#` / `.`), testlar uchun.
  String toText() => <String>[
    for (final List<bool> row in _modules)
      row.map((bool d) => d ? '#' : '.').join(),
  ].join('\n');

  // --- jadvallar ------------------------------------------------------------------------------------------------

  /// (bloklar soni, blokdagi ma'lumot baytlari) guruhlari va blokdagi tuzatish baytlari, versiya 1-10.
  static const Map<QrEcc, List<(int ecPerBlock, List<(int blocks, int data)> groups)>>
  _blocks = <QrEcc, List<(int, List<(int, int)>)>>{
    QrEcc.medium: <(int, List<(int, int)>)>[
      (10, <(int, int)>[(1, 16)]),
      (16, <(int, int)>[(1, 28)]),
      (26, <(int, int)>[(1, 44)]),
      (18, <(int, int)>[(2, 32)]),
      (24, <(int, int)>[(2, 43)]),
      (16, <(int, int)>[(4, 27)]),
      (18, <(int, int)>[(4, 31)]),
      (22, <(int, int)>[(2, 38), (2, 39)]),
      (22, <(int, int)>[(3, 36), (2, 37)]),
      (26, <(int, int)>[(4, 43), (1, 44)]),
    ],
    QrEcc.low: <(int, List<(int, int)>)>[
      (7, <(int, int)>[(1, 19)]),
      (10, <(int, int)>[(1, 34)]),
      (15, <(int, int)>[(1, 55)]),
      (20, <(int, int)>[(1, 80)]),
      (26, <(int, int)>[(1, 108)]),
      (18, <(int, int)>[(2, 68)]),
      (20, <(int, int)>[(2, 78)]),
      (24, <(int, int)>[(2, 97)]),
      (30, <(int, int)>[(2, 116)]),
      (18, <(int, int)>[(2, 68), (2, 69)]),
    ],
  };

  static const List<List<int>> _alignment = <List<int>>[
    <int>[],
    <int>[6, 18],
    <int>[6, 22],
    <int>[6, 26],
    <int>[6, 30],
    <int>[6, 34],
    <int>[6, 22, 38],
    <int>[6, 24, 42],
    <int>[6, 26, 46],
    <int>[6, 28, 50],
  ];

  static int _dataCodewords(int version, QrEcc level) {
    var total = 0;
    for (final (int blocks, int data) in _blocks[level]![version - 1].$2) {
      total += blocks * data;
    }
    return total;
  }

  /// Bayt rejimida sig'im: mode(4) + soni(8/16) bit chiqarib tashlanadi.
  static int _byteCapacity(int version, QrEcc level) {
    final int countBits = version <= 9 ? 8 : 16;
    return (_dataCodewords(version, level) * 8 - 4 - countBits) ~/ 8;
  }

  // --- qurish ---------------------------------------------------------------------------------------------------

  static QrCode _build(Uint8List data, int version, QrEcc level, int? forcedMask) {
    final List<int> codewords = _interleave(_dataBits(data, version, level), version, level);
    final int size = 17 + 4 * version;
    final List<List<bool>> function = List<List<bool>>.generate(size, (_) => List<bool>.filled(size, false));
    final List<List<bool>> base = List<List<bool>>.generate(size, (_) => List<bool>.filled(size, false));

    void set(int r, int c, bool dark) {
      base[r][c] = dark;
      function[r][c] = true;
    }

    // Qidiruv naqshlari + ajratgichlar
    for (final (int, int) o in <(int, int)>[(0, 0), (0, size - 7), (size - 7, 0)]) {
      for (var dr = -1; dr <= 7; dr++) {
        for (var dc = -1; dc <= 7; dc++) {
          final int r = o.$1 + dr;
          final int c = o.$2 + dc;
          if (r < 0 || r >= size || c < 0 || c >= size) continue;
          final bool ring = dr >= 0 && dr <= 6 && dc >= 0 && dc <= 6 &&
              (dr == 0 || dr == 6 || dc == 0 || dc == 6 || (dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4));
          set(r, c, ring);
        }
      }
    }
    // Vaqt naqshlari
    for (var i = 8; i < size - 8; i++) {
      set(6, i, i.isEven);
      set(i, 6, i.isEven);
    }
    // Tekislash naqshlari
    final List<int> pos = _alignment[version - 1];
    for (final int r in pos) {
      for (final int c in pos) {
        final bool onFinder = (r == 6 && c == 6) || (r == 6 && c == pos.last && pos.length > 1) || (c == 6 && r == pos.last && pos.length > 1);
        if (onFinder) continue;
        for (var dr = -2; dr <= 2; dr++) {
          for (var dc = -2; dc <= 2; dc++) {
            set(r + dr, c + dc, dr.abs() == 2 || dc.abs() == 2 || (dr == 0 && dc == 0));
          }
        }
      }
    }
    // Qorong'i modul va format/versiya joylari band qilinadi
    set(size - 8, 8, true);
    for (var i = 0; i < 9; i++) {
      if (!function[8][i]) set(8, i, false);
      if (!function[i][8]) set(i, 8, false);
    }
    for (var i = 0; i < 8; i++) {
      if (!function[8][size - 1 - i]) set(8, size - 1 - i, false);
      if (!function[size - 1 - i][8]) set(size - 1 - i, 8, false);
    }
    if (version >= 7) {
      for (var i = 0; i < 6; i++) {
        for (var j = 0; j < 3; j++) {
          set(i, size - 11 + j, false);
          set(size - 11 + j, i, false);
        }
      }
    }

    // Ma'lumot modullari: o'ngdan chapga ikki ustunli ilon yo'li
    final List<List<bool>> raw = List<List<bool>>.generate(size, (int r) => List<bool>.of(base[r]));
    var bit = 0;
    final int totalBits = codewords.length * 8;
    for (var right = size - 1; right >= 1; right -= 2) {
      if (right == 6) right = 5;
      for (var vert = 0; vert < size; vert++) {
        for (var j = 0; j < 2; j++) {
          final int c = right - j;
          final bool upward = ((right + 1) & 2) == 0;
          final int r = upward ? size - 1 - vert : vert;
          if (function[r][c]) continue;
          if (bit < totalBits) {
            raw[r][c] = ((codewords[bit >> 3] >> (7 - (bit & 7))) & 1) == 1;
          }
          bit++;
        }
      }
    }

    // Niqob tanlash
    var bestMask = forcedMask ?? 0;
    List<List<bool>>? best;
    if (forcedMask != null) {
      best = _masked(raw, function, forcedMask, version, level);
    } else {
      var bestPenalty = 1 << 30;
      for (var m = 0; m < 8; m++) {
        final List<List<bool>> candidate = _masked(raw, function, m, version, level);
        final int penalty = _penalty(candidate);
        if (penalty < bestPenalty) {
          bestPenalty = penalty;
          bestMask = m;
          best = candidate;
        }
      }
    }
    return QrCode._(version, level, bestMask, best!);
  }

  static List<List<bool>> _masked(List<List<bool>> raw, List<List<bool>> function, int mask, int version, QrEcc level) {
    final int size = raw.length;
    final List<List<bool>> out = List<List<bool>>.generate(size, (int r) => List<bool>.of(raw[r]));
    for (var r = 0; r < size; r++) {
      for (var c = 0; c < size; c++) {
        if (function[r][c]) continue;
        final bool invert = switch (mask) {
          0 => (r + c) % 2 == 0,
          1 => r % 2 == 0,
          2 => c % 3 == 0,
          3 => (r + c) % 3 == 0,
          4 => (r ~/ 2 + c ~/ 3) % 2 == 0,
          5 => (r * c) % 2 + (r * c) % 3 == 0,
          6 => ((r * c) % 2 + (r * c) % 3) % 2 == 0,
          _ => ((r + c) % 2 + (r * c) % 3) % 2 == 0,
        };
        if (invert) out[r][c] = !out[r][c];
      }
    }
    _drawFormat(out, mask, level);
    if (version >= 7) _drawVersion(out, version);
    return out;
  }

  static void _drawFormat(List<List<bool>> m, int mask, QrEcc level) {
    final int size = m.length;
    final int data = (level.formatBits << 3) | mask;
    var rem = data;
    for (var i = 0; i < 10; i++) {
      rem = (rem << 1) ^ ((rem >> 9) * 0x537);
    }
    final int bits = ((data << 10) | rem) ^ 0x5412;
    bool b(int i) => ((bits >> i) & 1) == 1;
    for (var i = 0; i <= 5; i++) {
      m[i][8] = b(i);
    }
    m[7][8] = b(6);
    m[8][8] = b(7);
    m[8][7] = b(8);
    for (var i = 9; i < 15; i++) {
      m[8][14 - i] = b(i);
    }
    for (var i = 0; i < 8; i++) {
      m[8][size - 1 - i] = b(i);
    }
    for (var i = 8; i < 15; i++) {
      m[size - 15 + i][8] = b(i);
    }
    m[size - 8][8] = true;
  }

  static void _drawVersion(List<List<bool>> m, int version) {
    final int size = m.length;
    var rem = version;
    for (var i = 0; i < 12; i++) {
      rem = (rem << 1) ^ ((rem >> 11) * 0x1F25);
    }
    final int bits = (version << 12) | rem;
    for (var i = 0; i < 18; i++) {
      final bool bit = ((bits >> i) & 1) == 1;
      final int a = size - 11 + i % 3;
      final int b = i ~/ 3;
      m[b][a] = bit;
      m[a][b] = bit;
    }
  }

  static int _penalty(List<List<bool>> m) {
    final int size = m.length;
    var result = 0;
    // N1: bir xil rangdagi 5+ ketma-ket modul (qator va ustun)
    for (var pass = 0; pass < 2; pass++) {
      for (var i = 0; i < size; i++) {
        var run = 1;
        for (var j = 1; j < size; j++) {
          final bool same = pass == 0 ? m[i][j] == m[i][j - 1] : m[j][i] == m[j - 1][i];
          if (same) {
            run++;
            if (run == 5) {
              result += 3;
            } else if (run > 5) {
              result += 1;
            }
          } else {
            run = 1;
          }
        }
      }
    }
    // N2: 2x2 bloklar
    for (var r = 0; r < size - 1; r++) {
      for (var c = 0; c < size - 1; c++) {
        final bool v = m[r][c];
        if (v == m[r][c + 1] && v == m[r + 1][c] && v == m[r + 1][c + 1]) result += 3;
      }
    }
    // N3: 1:1:3:1:1 naqsh (oldida yoki orqasida 4 ta och modul)
    const List<bool> pattern = <bool>[true, false, true, true, true, false, true];
    for (var pass = 0; pass < 2; pass++) {
      for (var i = 0; i < size; i++) {
        for (var j = 0; j <= size - 7; j++) {
          var match = true;
          for (var k = 0; k < 7 && match; k++) {
            final bool v = pass == 0 ? m[i][j + k] : m[j + k][i];
            if (v != pattern[k]) match = false;
          }
          if (!match) continue;
          bool light(int idx) {
            if (idx < 0 || idx >= size) return true;
            return !(pass == 0 ? m[i][idx] : m[idx][i]);
          }
          final bool before = light(j - 1) && light(j - 2) && light(j - 3) && light(j - 4);
          final bool after = light(j + 7) && light(j + 8) && light(j + 9) && light(j + 10);
          if (before) result += 40;
          if (after) result += 40;
        }
      }
    }
    // N4: qora modullar ulushi 50% dan chetlanishi
    var dark = 0;
    for (final List<bool> row in m) {
      for (final bool d in row) {
        if (d) dark++;
      }
    }
    final int total = size * size;
    final int k = ((dark * 20 - total * 10).abs() + total - 1) ~/ total - 1;
    result += k * 10;
    return result;
  }

  // --- ma'lumot bitlari va Reed-Solomon -----------------------------------------------------------------------

  static List<int> _dataBits(Uint8List data, int version, QrEcc level) {
    final List<int> bits = <int>[];
    void put(int value, int length) {
      for (var i = length - 1; i >= 0; i--) {
        bits.add((value >> i) & 1);
      }
    }

    put(0x4, 4); // bayt rejimi
    put(data.length, version <= 9 ? 8 : 16);
    for (final int b in data) {
      put(b, 8);
    }
    final int capacityBits = _dataCodewords(version, level) * 8;
    put(0, (capacityBits - bits.length).clamp(0, 4)); // tugatuvchi
    while (bits.length % 8 != 0) {
      bits.add(0);
    }
    for (var pad = 0xEC; bits.length < capacityBits; pad ^= 0xEC ^ 0x11) {
      put(pad, 8);
    }
    return <int>[
      for (var i = 0; i < bits.length; i += 8)
        bits.sublist(i, i + 8).fold<int>(0, (int a, int b) => (a << 1) | b),
    ];
  }

  static List<int> _interleave(List<int> data, int version, QrEcc level) {
    final (int ecPerBlock, List<(int, int)> groups) spec = _blocks[level]![version - 1];
    final List<List<int>> dataBlocks = <List<int>>[];
    var offset = 0;
    for (final (int blocks, int size) in spec.$2) {
      for (var i = 0; i < blocks; i++) {
        dataBlocks.add(data.sublist(offset, offset + size));
        offset += size;
      }
    }
    final List<int> divisor = _rsDivisor(spec.$1);
    final List<List<int>> ecBlocks = <List<int>>[
      for (final List<int> block in dataBlocks) _rsRemainder(block, divisor),
    ];
    final List<int> out = <int>[];
    final int maxData = dataBlocks.map((List<int> b) => b.length).reduce((int a, int b) => a > b ? a : b);
    for (var i = 0; i < maxData; i++) {
      for (final List<int> block in dataBlocks) {
        if (i < block.length) out.add(block[i]);
      }
    }
    for (var i = 0; i < spec.$1; i++) {
      for (final List<int> block in ecBlocks) {
        out.add(block[i]);
      }
    }
    return out;
  }

  static int _gfMul(int x, int y) {
    var z = 0;
    for (var i = 7; i >= 0; i--) {
      z = (z << 1) ^ ((z >> 7) * 0x11D);
      z ^= ((y >> i) & 1) * x;
    }
    return z;
  }

  static List<int> _rsDivisor(int degree) {
    final List<int> result = List<int>.filled(degree, 0);
    result[degree - 1] = 1;
    var root = 1;
    for (var i = 0; i < degree; i++) {
      for (var j = 0; j < degree; j++) {
        result[j] = _gfMul(result[j], root);
        if (j + 1 < degree) result[j] ^= result[j + 1];
      }
      root = _gfMul(root, 0x02);
    }
    return result;
  }

  static List<int> _rsRemainder(List<int> data, List<int> divisor) {
    final List<int> result = List<int>.filled(divisor.length, 0, growable: true);
    for (final int b in data) {
      final int factor = b ^ result.removeAt(0);
      result.add(0);
      for (var i = 0; i < result.length; i++) {
        result[i] ^= _gfMul(divisor[i], factor);
      }
    }
    return result;
  }
}
