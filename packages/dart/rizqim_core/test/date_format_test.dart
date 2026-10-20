import 'package:rizqim_core/rizqim_core.dart';
import 'package:test/test.dart';

String plain(String text) => text.replaceAll(nbsp, ' ');

void main() {
  // 19 October 2026, 20:12 local time (the Mini App tests use the same moment)
  final DateTime evening = DateTime(2026, 10, 19, 20, 12);
  final DateTime now = DateTime(2026, 10, 20, 9);

  test('writes Uzbek months itself', () {
    expect(formatDate(evening, now: now), '19-okt');
    expect(plain(formatDateTime(evening, now: now)), '19-okt, 20:12');
    expect(formatDate(DateTime(2026, 1, 3), now: now), '3-yan');
    expect(formatDate(DateTime(2026, 5, 9), now: now), '9-may');
    expect(formatDate(DateTime(2026, 12, 1), now: now), '1-dek');
  });

  test('has all twelve Uzbek month names', () {
    const List<String> expected = <String>[
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
      'dek',
    ];
    for (var m = 1; m <= 12; m++) {
      expect(
        formatDate(DateTime(2026, m, 15), now: now),
        '15-${expected[m - 1]}',
      );
    }
  });

  test('Russian and English match the Mini App', () {
    expect(
      plain(formatDateTime(evening, locale: RzLocale.ru, now: now)),
      '19 окт., 20:12',
    );
    expect(
      plain(formatDateTime(evening, locale: RzLocale.en, now: now)),
      'Oct 19, 20:12',
    );
  });

  test('adds the year to a date of another year', () {
    expect(plain(formatDate(DateTime(2025, 12, 31), now: now)), '31-dek 2025');
    expect(
      plain(formatDate(DateTime(2025, 12, 31), locale: RzLocale.en, now: now)),
      'Dec 31, 2025',
    );
  });

  test('is 24-hour with leading zeros', () {
    expect(formatTime(DateTime(2026, 10, 19, 7, 5)), '07:05');
    expect(formatTime(evening), '20:12');
    expect(formatTime(DateTime(2026, 10, 19, 0, 0)), '00:00');
  });

  test('reads ISO strings in the device time zone and shows a dash for nothing or garbage', () {
    expect(formatTime(evening.toUtc().toIso8601String()), '20:12');
    expect(formatTime(evening.toIso8601String()), '20:12');
    expect(formatDateTime('2026-10-19 20:12:00', now: now), '19-okt, 20:12');
    expect(formatDate(null), '—');
    expect(formatDateTime('not a date'), '—');
    expect(formatTime(''), '—');
    expect(formatTime(42), '—');
  });

  test('spaces inside a value are non-breaking', () {
    expect(formatDate(DateTime(2025, 12, 31), now: now), '31-dek 2025');
    expect(formatDate(evening, locale: RzLocale.en, now: now), 'Oct 19');
  });

  test('RzLocale.parse', () {
    expect(RzLocale.parse('ru_RU'), RzLocale.ru);
    expect(RzLocale.parse('EN-us'), RzLocale.en);
    expect(RzLocale.parse('de'), RzLocale.uz);
    expect(RzLocale.parse(null), RzLocale.uz);
  });
}
