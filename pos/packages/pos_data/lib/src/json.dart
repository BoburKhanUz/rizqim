import 'package:pos_core/pos_core.dart';

/// Javob JSON'ini o'qish uchun yupqa yordamchi: kalit yo'q yoki turi boshqa bo'lsa [FormatException] (majburiy
/// maydonlar) yoki `null` (ixtiyoriylari). Pul — [PosMoney] (tiyin), miqdor — [PosQuantity].
///
/// Modellar yaratilgan klient (`rizqim_api/generated.dart`) o'rniga shu bilan qo'lda yozilgan: POS javoblarining bir
/// qismi spetsifikatsiyada `object` (bootstrap `halls`, `tables`, `shift`; POS katalogi), pul `"12000.00"` satr va bitta
/// noma'lum maydon butun javobni yiqitmasligi kerak. Har modelda asl xarita (`raw`) ham saqlanadi: keyingi ishlar
/// yangi maydonni modelni o'zgartirmasdan o'qiy oladi.
extension type const PosJson(Map<String, Object?> map) {
  /// Javob qiymatidan (Map bo'lishi shart).
  static PosJson of(Object? value, [String what = 'object']) {
    if (value is Map<String, Object?>) return PosJson(value);
    if (value is Map) return PosJson(value.cast<String, Object?>());
    throw FormatException('Expected $what to be an object', value);
  }

  Object? operator [](String key) => map[key];

  bool has(String key) => map[key] != null;

  String str(String key) {
    final Object? v = map[key];
    if (v is String) return v;
    if (v is num || v is bool) return '$v';
    throw FormatException('Expected "$key" to be a string', v);
  }

  String? strOrNull(String key) {
    final Object? v = map[key];
    if (v == null) return null;
    if (v is String) return v;
    if (v is num || v is bool) return '$v';
    throw FormatException('Expected "$key" to be a string', v);
  }

  int integer(String key) {
    final int? v = intOrNull(key);
    if (v == null) throw FormatException('Expected "$key" to be an integer');
    return v;
  }

  int? intOrNull(String key) {
    final Object? v = map[key];
    if (v == null) return null;
    if (v is int) return v;
    if (v is num) return v.toInt();
    if (v is String) {
      final int? parsed = int.tryParse(v);
      if (parsed != null) return parsed;
    }
    throw FormatException('Expected "$key" to be an integer', v);
  }

  bool boolean(String key, {bool fallback = false}) {
    final Object? v = map[key];
    if (v is bool) return v;
    if (v is num) return v != 0;
    return fallback;
  }

  /// Pul (`"12000.00"`, son). Yo'q yoki `null` — [fallback] (standart 0).
  PosMoney money(String key, {PosMoney fallback = PosMoney.zero}) =>
      moneyOrNull(key) ?? fallback;

  PosMoney? moneyOrNull(String key) {
    final Object? v = map[key];
    if (v == null) return null;
    final PosMoney? m = PosMoney.tryParse(v);
    if (m == null) throw FormatException('Expected "$key" to be money', v);
    return m;
  }

  PosQuantity quantity(String key, {PosQuantity fallback = PosQuantity.zero}) =>
      quantityOrNull(key) ?? fallback;

  /// Miqdor. Backend uch kasr raqamdan ko'p bermaydi; bersa (suzuvchi nuqta shovqini) mingdan birgacha yaxlitlanadi.
  PosQuantity? quantityOrNull(String key) {
    final Object? v = map[key];
    if (v == null) return null;
    final PosQuantity? q =
        PosQuantity.tryParse(v) ??
        (v is num ? PosQuantity.milli((v * 1000).round()) : null);
    if (q == null) throw FormatException('Expected "$key" to be a quantity', v);
    return q;
  }

  /// ISO 8601 vaqt (UTC'ga keltirilmaydi: `DateTime.parse` zonani o'zi hisobga oladi).
  DateTime? dateOrNull(String key) {
    final Object? v = map[key];
    if (v == null || (v is String && v.isEmpty)) return null;
    if (v is String) {
      final DateTime? d = DateTime.tryParse(v);
      if (d != null) return d;
    }
    throw FormatException('Expected "$key" to be a date-time', v);
  }

  DateTime date(String key) {
    final DateTime? d = dateOrNull(key);
    if (d == null) throw FormatException('Expected "$key" to be a date-time');
    return d;
  }

  PosJson obj(String key) => PosJson.of(map[key], key);

  PosJson? objOrNull(String key) {
    final Object? v = map[key];
    return v == null ? null : PosJson.of(v, key);
  }

  /// Ro'yxat; yo'q yoki `null` — bo'sh.
  List<Object?> list(String key) {
    final Object? v = map[key];
    if (v == null) return const <Object?>[];
    if (v is List) return v;
    throw FormatException('Expected "$key" to be a list', v);
  }

  /// Obyektlar ro'yxati, har biri [parse] bilan.
  List<T> objects<T>(String key, T Function(PosJson json) parse) => <T>[
    for (final Object? e in list(key)) parse(PosJson.of(e, key)),
  ];

  List<String> strings(String key) => <String>[
    for (final Object? e in list(key))
      if (e != null) '$e',
  ];

  List<int> ints(String key) => <int>[
    for (final Object? e in list(key))
      if (e is num)
        e.toInt()
      else if (e is String && int.tryParse(e) != null)
        int.parse(e),
  ];
}

/// `{id, name}` ko'rinishidagi havola (ofitsiant, kim qo'shdi, zal, kim tasdiqladi).
class PosRef {
  const PosRef({required this.id, this.name});

  factory PosRef.fromJson(PosJson j) =>
      PosRef(id: j.integer('id'), name: j.strOrNull('name'));

  /// [key] dagi havola: `{id, name}` obyekt yoki faqat id (ba'zi javoblarda `approved_by: 12`); yo'q — `null`.
  static PosRef? maybe(PosJson j, String key) {
    final Object? v = j[key];
    if (v == null) return null;
    if (v is num) return PosRef(id: v.toInt());
    return PosRef.fromJson(PosJson.of(v, key));
  }

  final int id;
  final String? name;

  @override
  bool operator ==(Object other) =>
      other is PosRef && other.id == id && other.name == name;

  @override
  int get hashCode => Object.hash(id, name);

  @override
  String toString() => 'PosRef($id, $name)';
}
