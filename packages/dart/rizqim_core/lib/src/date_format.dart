import 'locale.dart';

/// Sana yoki vaqt yo'q yoki o'qib bo'lmaydi.
const String missingDate = '—';

const List<String> _uzMonths = <String>[
  'yan',
  'fev',
  'mar',
  'apr',
  'may',
  'iyn',
  'iyl',
  'avg',
  'sen',
  'okt',
  'noy',
  'dek', //
];
const List<String> _ruMonths = <String>[
  'янв.',
  'февр.',
  'мар.',
  'апр.',
  'мая',
  'июн.',
  'июл.',
  'авг.',
  'сент.',
  'окт.',
  'нояб.',
  'дек.', //
];
const List<String> _enMonths = <String>[
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec', //
];

/// API'dagi ISO 8601 qatori yoki [DateTime] ni qurilmaning vaqt mintaqasidagi [DateTime] ga aylantiradi.
/// Bo'sh yoki o'qib bo'lmasa `null`.
DateTime? toLocalDate(Object? value) {
  if (value is DateTime) return value.toLocal();
  if (value is String && value.trim().isNotEmpty) {
    return DateTime.tryParse(value.trim())?.toLocal();
  }
  return null;
}

String _pad2(int n) => n.toString().padLeft(2, '0');

/// "20:12": 24 soat, har bir tilda bir xil.
String formatTime(Object? value) {
  final DateTime? d = toLocalDate(value);
  if (d == null) return missingDate;
  return '${_pad2(d.hour)}:${_pad2(d.minute)}';
}

/// "19-okt" (uz), "19 окт." (ru), "Oct 19" (en). Boshqa yil bo'lsa yil ham: "19-okt 2025".
///
/// [now] faqat "shu yilmi" degan savol uchun (testlarda beriladi).
String formatDate(
  Object? value, {
  RzLocale locale = RzLocale.uz,
  DateTime? now,
}) {
  final DateTime? d = toLocalDate(value);
  if (d == null) return missingDate;
  final bool withYear = d.year != (now ?? DateTime.now()).year;
  switch (locale) {
    case RzLocale.uz:
      final String day = '${d.day}-${_uzMonths[d.month - 1]}';
      return withYear ? '$day$nbsp${d.year}' : day;
    case RzLocale.ru:
      final String day = '${d.day}$nbsp${_ruMonths[d.month - 1]}';
      return withYear ? '$day$nbsp${d.year}$nbspг.' : day;
    case RzLocale.en:
      final String day = '${_enMonths[d.month - 1]}$nbsp${d.day}';
      return withYear ? '$day,$nbsp${d.year}' : day;
  }
}

/// "19-okt, 20:12" (uz), "19 окт., 20:12" (ru), "Oct 19, 20:12" (en).
String formatDateTime(
  Object? value, {
  RzLocale locale = RzLocale.uz,
  DateTime? now,
}) {
  final DateTime? d = toLocalDate(value);
  if (d == null) return missingDate;
  return '${formatDate(d, locale: locale, now: now)}, ${formatTime(d)}';
}
